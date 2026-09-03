package com.sunmipos.cash

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.sunmipos.cash.data.ApiException
import com.sunmipos.cash.data.AppPreferences
import com.sunmipos.cash.data.Cart
import com.sunmipos.cash.data.CartLine
import com.sunmipos.cash.data.CartSnapshot
import com.sunmipos.cash.data.DeviceDto
import com.sunmipos.cash.data.Money
import com.sunmipos.cash.data.OrderDto
import com.sunmipos.cash.data.PendingCheckout
import com.sunmipos.cash.data.PosRepository
import com.sunmipos.cash.data.PrinterDto
import com.sunmipos.cash.data.ProductDto
import com.sunmipos.cash.data.UserDto
import com.sunmipos.cash.printing.ReceiptPrinter
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.util.UUID

enum class Screen { LOGIN, POS, CHECKOUT, CONFIRMATION, ORDERS, SETTINGS }

data class PosUiState(
    val screen: Screen = Screen.LOGIN,
    val loading: Boolean = false,
    val serverOnline: Boolean? = null,
    val message: String? = null,
    val user: UserDto? = null,
    val device: DeviceDto? = null,
    val deviceUuid: String = "",
    val cart: List<CartLine> = emptyList(),
    val searchResults: List<ProductDto> = emptyList(),
    val orders: List<OrderDto> = emptyList(),
    val selectedOrder: OrderDto? = null,
    val printing: Boolean = false,
) {
    val cartTotalCents: Int get() = cart.sumOf(CartLine::totalCents)
}

class PosViewModel(
    private val repository: PosRepository,
    private val preferences: AppPreferences,
    private val printer: ReceiptPrinter,
) : ViewModel() {
    private val _uiState = MutableStateFlow(
        PosUiState(
            deviceUuid = preferences.deviceUuid,
            cart = preferences.cart().lines,
        ),
    )
    val uiState: StateFlow<PosUiState> = _uiState.asStateFlow()

    init {
        restoreSession()
    }

    fun checkServer() {
        viewModelScope.launch {
            _uiState.update { it.copy(serverOnline = null) }
            val online = repository.health()
            _uiState.update {
                it.copy(
                    serverOnline = online,
                    message = if (online) "Server is reachable." else "Cannot reach the POS server.",
                )
            }
        }
    }

    fun login(email: String, password: String, deviceUuid: String) {
        if (email.isBlank() || password.isBlank() || deviceUuid.isBlank()) {
            showMessage("Email, password, and device UUID are required.")
            return
        }
        launchRequest {
            val login = repository.login(email, password, deviceUuid)
            preferences.deviceUuid = login.device.uuid
            _uiState.update {
                it.copy(
                    screen = Screen.POS,
                    user = login.user,
                    device = login.device,
                    deviceUuid = login.device.uuid,
                    message = "Signed in as ${login.user.name}.",
                )
            }
        }
    }

    fun logout() {
        viewModelScope.launch {
            repository.logout()
            _uiState.value = PosUiState(deviceUuid = preferences.deviceUuid)
        }
    }

    fun showPos() = _uiState.update { it.copy(screen = Screen.POS, message = null) }

    fun showCheckout() {
        if (_uiState.value.cart.isEmpty()) {
            showMessage("Add at least one item before checkout.")
            return
        }
        _uiState.update { it.copy(screen = Screen.CHECKOUT, message = null) }
    }

    fun showOrders() {
        _uiState.update { it.copy(screen = Screen.ORDERS, message = null) }
        loadOrders()
    }

    fun showSettings() = _uiState.update { it.copy(screen = Screen.SETTINGS, message = null) }

    fun dismissMessage() = _uiState.update { it.copy(message = null) }

    fun scan(barcode: String) {
        val normalized = barcode.trim()
        if (normalized.isEmpty()) return
        launchRequest {
            addProduct(repository.productByBarcode(normalized))
            _uiState.update { it.copy(message = "Scanned $normalized") }
        }
    }

    fun search(query: String) {
        if (query.isBlank()) {
            _uiState.update { it.copy(searchResults = emptyList()) }
            return
        }
        launchRequest {
            val products = repository.searchProducts(query)
            _uiState.update {
                it.copy(
                    searchResults = products,
                    message = if (products.isEmpty()) "No active products found." else null,
                )
            }
        }
    }

    fun addProduct(product: ProductDto) {
        mutateCart(Cart.addProduct(_uiState.value.cart, product))
        _uiState.update { it.copy(searchResults = emptyList()) }
    }

    fun addManual(description: String, quantityText: String, priceText: String, vatBasisPoints: Int) {
        val quantity = quantityText.toIntOrNull()
        val price = Money.parseCents(priceText)
        when {
            description.trim().isEmpty() -> showMessage("Manual item description is required.")
            quantity === null || quantity < 1 -> showMessage("Quantity must be a positive whole number.")
            price === null -> showMessage("Enter a valid price such as 12.50.")
            vatBasisPoints !in setOf(900, 2100) -> showMessage("Choose VAT 9% or VAT 21%.")
            else -> {
                mutateCart(
                    Cart.addManual(
                        lines = _uiState.value.cart,
                        description = description,
                        quantity = quantity,
                        unitPriceCents = price,
                        vatBasisPoints = vatBasisPoints,
                        key = UUID.randomUUID().toString(),
                    ),
                )
                showMessage("Manual item added.")
            }
        }
    }

    fun increment(line: CartLine) {
        mutateCart(_uiState.value.cart.map { if (it.key == line.key) it.copy(quantity = it.quantity + 1) else it })
    }

    fun decrement(line: CartLine) {
        val updated = if (line.quantity <= 1) {
            _uiState.value.cart.filterNot { it.key == line.key }
        } else {
            _uiState.value.cart.map { if (it.key == line.key) it.copy(quantity = it.quantity - 1) else it }
        }
        mutateCart(updated)
    }

    fun remove(line: CartLine) = mutateCart(_uiState.value.cart.filterNot { it.key == line.key })

    fun submitCheckout(tenderedText: String) {
        val tenderedCents = Money.parseCents(tenderedText)
        val state = _uiState.value
        when {
            state.cart.isEmpty() -> showMessage("The cart is empty.")
            tenderedCents === null -> showMessage("Enter a valid cash amount such as 20.00.")
            tenderedCents < state.cartTotalCents -> showMessage("Cash tendered is less than the estimated total.")
            else -> {
                val fingerprint = Cart.fingerprint(state.cart)
                val request = Cart.checkoutRequest(state.cart, tenderedCents)
                val stored = preferences.pendingCheckout()
                val pending = stored?.takeIf {
                    it.cartFingerprint == fingerprint && it.request == request
                } ?: PendingCheckout(UUID.randomUUID().toString(), fingerprint, request)
                preferences.savePendingCheckout(pending)

                launchRequest {
                    val order = repository.checkout(pending.idempotencyKey, pending.request)
                    preferences.savePendingCheckout(null)
                    _uiState.update {
                        it.copy(
                            screen = Screen.CONFIRMATION,
                            selectedOrder = order,
                            message = "Sale ${order.orderNumber} completed.",
                        )
                    }
                    print(order, isCopy = false)
                }
            }
        }
    }

    fun retryPrint() {
        _uiState.value.selectedOrder?.let { print(it, isCopy = false) }
    }

    fun reprint(orderNumber: String) {
        launchRequest {
            val order = repository.receipt(orderNumber)
            _uiState.update { it.copy(selectedOrder = order, screen = Screen.CONFIRMATION) }
            print(order, isCopy = true)
        }
    }

    fun newSale() {
        preferences.clearSaleState()
        _uiState.update {
            it.copy(
                screen = Screen.POS,
                cart = emptyList(),
                selectedOrder = null,
                message = null,
            )
        }
    }

    fun loadOrders() {
        launchRequest {
            _uiState.update { it.copy(orders = repository.orders()) }
        }
    }

    fun openOrder(orderNumber: String) {
        launchRequest {
            val order = repository.order(orderNumber)
            _uiState.update {
                it.copy(selectedOrder = order, screen = Screen.CONFIRMATION)
            }
        }
    }

    fun refreshSettings() {
        launchRequest {
            val me = repository.me()
            _uiState.update { it.copy(user = me.user, device = me.device, message = "Settings refreshed.") }
        }
    }

    fun testPrinter() {
        val settings = _uiState.value.device?.printer
        if (settings === null || !settings.configured) {
            showMessage("Configure this device's printer in the Laravel admin first.")
            return
        }
        viewModelScope.launch {
            _uiState.update { it.copy(printing = true, message = "Sending test receipt…") }
            val result = printer.test(settings)
            _uiState.update {
                it.copy(
                    printing = false,
                    message = result.fold(
                        onSuccess = { "Test receipt sent to ${settings.ipAddress}." },
                        onFailure = { error -> "Printer failed: ${error.message ?: "connection error"}" },
                    ),
                )
            }
        }
    }

    private fun restoreSession() {
        viewModelScope.launch {
            _uiState.update { it.copy(loading = true) }
            runCatching { repository.me() }
                .onSuccess { me ->
                    _uiState.update {
                        it.copy(screen = Screen.POS, user = me.user, device = me.device, loading = false)
                    }
                }
                .onFailure {
                    _uiState.update { state -> state.copy(screen = Screen.LOGIN, loading = false) }
                }
        }
    }

    private fun mutateCart(lines: List<CartLine>) {
        preferences.saveCart(CartSnapshot(lines))
        preferences.savePendingCheckout(null)
        _uiState.update { it.copy(cart = lines) }
    }

    private fun print(order: OrderDto, isCopy: Boolean) {
        val settings = _uiState.value.device?.printer
        if (settings === null || !settings.configured) {
            _uiState.update { it.copy(message = "Sale confirmed. Printer is not configured; reprint remains available.") }
            return
        }
        viewModelScope.launch {
            _uiState.update { it.copy(printing = true, message = "Printing receipt…") }
            val result = printer.print(order, settings, isCopy)
            _uiState.update {
                it.copy(
                    printing = false,
                    message = result.fold(
                        onSuccess = { "Receipt sent to ${settings.model}." },
                        onFailure = { error -> "Sale confirmed, but printing failed: ${error.message ?: "connection error"}" },
                    ),
                )
            }
        }
    }

    private fun launchRequest(block: suspend () -> Unit) {
        if (_uiState.value.loading) return
        viewModelScope.launch {
            _uiState.update { it.copy(loading = true, message = null) }
            try {
                block()
            } catch (error: ApiException) {
                if (error.status == 401) {
                    _uiState.update { it.copy(screen = Screen.LOGIN, user = null, device = null) }
                }
                showMessage(messageFor(error))
            } catch (error: Exception) {
                showMessage("Network error. Check the server and retry; the same sale key will be reused.")
            } finally {
                _uiState.update { it.copy(loading = false) }
            }
        }
    }

    private fun messageFor(error: ApiException): String = when (error.code) {
        "not_found" -> "No active product was found. Search manually or add an open item."
        "product_changed" -> "The product price or VAT changed. Remove it and scan it again."
        "product_unavailable" -> "A product is no longer available. Remove it from the cart."
        "insufficient_stock" -> "There is not enough stock for one of the products."
        "insufficient_tender" -> "The cash tendered is below the server-approved total."
        "tax_rate_unavailable" -> "The selected VAT rate is unavailable."
        "idempotency_conflict" -> "This saved checkout no longer matches the cart. Edit the cart and retry."
        "validation_failed" -> error.details?.values?.flatten()?.firstOrNull() ?: error.message
        "unauthenticated" -> "Your session expired. Sign in again."
        else -> "${error.message}${error.requestId?.let { " (Request $it)" }.orEmpty()}"
    }

    private fun showMessage(message: String) = _uiState.update { it.copy(message = message) }
}

class PosViewModelFactory(
    private val repository: PosRepository,
    private val preferences: AppPreferences,
    private val printer: ReceiptPrinter,
) : ViewModelProvider.Factory {
    @Suppress("UNCHECKED_CAST")
    override fun <T : ViewModel> create(modelClass: Class<T>): T =
        PosViewModel(repository, preferences, printer) as T
}
