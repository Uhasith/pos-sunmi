package com.sunmipos.cash

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.RadioButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.input.key.onPreviewKeyEvent
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.sunmipos.cash.data.ApiFactory
import com.sunmipos.cash.data.AppPreferences
import com.sunmipos.cash.data.CartLine
import com.sunmipos.cash.data.Money
import com.sunmipos.cash.data.OrderDto
import com.sunmipos.cash.data.PosRepository
import com.sunmipos.cash.data.ProductDto
import com.sunmipos.cash.data.SecureTokenStore
import com.sunmipos.cash.printing.NetworkReceiptPrinter
import com.sunmipos.cash.scanner.HardwareKeyBarcodeScanner

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val gson = ApiFactory.gson()
        val tokenStore = SecureTokenStore(applicationContext)
        val preferences = AppPreferences(applicationContext, gson)
        val repository = PosRepository(ApiFactory.create(tokenStore, gson), tokenStore, gson)
        val factory = PosViewModelFactory(repository, preferences, NetworkReceiptPrinter())
        val scanner = HardwareKeyBarcodeScanner()

        setContent {
            val viewModel: PosViewModel = viewModel(factory = factory)
            val state by viewModel.uiState.collectAsState()

            MaterialTheme {
                Surface(
                    modifier = Modifier
                        .fillMaxSize()
                        .onPreviewKeyEvent { event ->
                            if (state.screen == Screen.POS) {
                                scanner.accept(event.nativeKeyEvent)?.let(viewModel::scan)
                            }
                            false
                        },
                ) {
                    PosApp(state, viewModel)
                }
            }
        }
    }
}

@Composable
@OptIn(ExperimentalMaterial3Api::class)
private fun PosApp(state: PosUiState, viewModel: PosViewModel) {
    if (state.screen == Screen.LOGIN) {
        LoginScreen(state, viewModel)
        return
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Sunmi Cash POS") },
                actions = {
                    TextButton(onClick = viewModel::showPos) { Text("Sale") }
                    TextButton(onClick = viewModel::showOrders) { Text("Orders") }
                    TextButton(onClick = viewModel::showSettings) { Text("Settings") }
                },
            )
        },
    ) { padding ->
        Column(Modifier.padding(padding).fillMaxSize()) {
            state.message?.let {
                MessageBanner(it, viewModel::dismissMessage)
            }
            Box(Modifier.fillMaxSize()) {
                when (state.screen) {
                    Screen.POS -> PosScreen(state, viewModel)
                    Screen.CHECKOUT -> CheckoutScreen(state, viewModel)
                    Screen.CONFIRMATION -> ConfirmationScreen(state, viewModel)
                    Screen.ORDERS -> OrdersScreen(state, viewModel)
                    Screen.SETTINGS -> SettingsScreen(state, viewModel)
                    Screen.LOGIN -> Unit
                }
                if (state.loading) {
                    Surface(Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.surface.copy(alpha = 0.72f)) {
                        Box(contentAlignment = Alignment.Center) { CircularProgressIndicator() }
                    }
                }
            }
        }
    }
}

@Composable
private fun LoginScreen(state: PosUiState, viewModel: PosViewModel) {
    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var deviceUuid by remember(state.deviceUuid) { mutableStateOf(state.deviceUuid) }

    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        Card(Modifier.width(520.dp)) {
            Column(Modifier.padding(32.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                Text("Sunmi Cash POS", style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
                Text("Sign in with an active cashier and the UUID provisioned in Laravel.")
                OutlinedTextField(email, { email = it }, label = { Text("Email") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                OutlinedTextField(
                    password,
                    { password = it },
                    label = { Text("Password") },
                    visualTransformation = PasswordVisualTransformation(),
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                )
                OutlinedTextField(deviceUuid, { deviceUuid = it }, label = { Text("Device UUID") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                state.message?.let { Text(it, color = MaterialTheme.colorScheme.error) }
                Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    Button(
                        onClick = { viewModel.login(email, password, deviceUuid) },
                        enabled = !state.loading,
                    ) { Text("Sign in") }
                    OutlinedButton(onClick = viewModel::checkServer) { Text("Test server") }
                    Text(
                        when (state.serverOnline) {
                            true -> "Online"
                            false -> "Offline"
                            null -> ""
                        },
                        modifier = Modifier.align(Alignment.CenterVertically),
                    )
                }
            }
        }
        if (state.loading) CircularProgressIndicator()
    }
}

@Composable
private fun PosScreen(state: PosUiState, viewModel: PosViewModel) {
    var barcode by remember { mutableStateOf("") }
    var search by remember { mutableStateOf("") }
    var manualOpen by remember { mutableStateOf(false) }

    Row(Modifier.fillMaxSize().padding(18.dp), horizontalArrangement = Arrangement.spacedBy(18.dp)) {
        Column(Modifier.weight(1f).fillMaxHeight(), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Text("Scan or find a product", style = MaterialTheme.typography.headlineSmall)
            Text("The SUNMI scan key is ready. Manual barcode entry is available for testing.")
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedTextField(
                    barcode,
                    { barcode = it },
                    label = { Text("Barcode") },
                    singleLine = true,
                    modifier = Modifier.weight(1f),
                )
                Button(onClick = { viewModel.scan(barcode); barcode = "" }) { Text("Add") }
            }
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedTextField(
                    search,
                    { search = it },
                    label = { Text("Title, article number, or barcode") },
                    singleLine = true,
                    modifier = Modifier.weight(1f),
                )
                OutlinedButton(onClick = { viewModel.search(search) }) { Text("Search") }
            }
            OutlinedButton(onClick = { manualOpen = true }) { Text("Add manual item") }
            LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                items(state.searchResults, key = ProductDto::id) { product ->
                    ProductResult(product) { viewModel.addProduct(product) }
                }
            }
        }
        CartPanel(state, viewModel, Modifier.weight(1.15f).fillMaxHeight())
    }

    if (manualOpen) {
        ManualItemDialog(
            onDismiss = { manualOpen = false },
            onAdd = { description, quantity, price, vat ->
                viewModel.addManual(description, quantity, price, vat)
                manualOpen = false
            },
        )
    }
}

@Composable
private fun ProductResult(product: ProductDto, onAdd: () -> Unit) {
    Card(Modifier.fillMaxWidth()) {
        Row(Modifier.padding(12.dp).fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(product.title, fontWeight = FontWeight.Bold)
                Text("${product.articleNumber} • Stock ${product.stockOnHand} • ${product.taxRate.label}")
            }
            Text(Money.format(product.sellingPriceIncludingTaxCents))
            Spacer(Modifier.width(12.dp))
            Button(onClick = onAdd) { Text("Add") }
        }
    }
}

@Composable
private fun CartPanel(state: PosUiState, viewModel: PosViewModel, modifier: Modifier = Modifier) {
    Card(modifier) {
        Column(Modifier.padding(16.dp).fillMaxSize()) {
            Text("Cart", style = MaterialTheme.typography.headlineSmall)
            Spacer(Modifier.height(8.dp))
            if (state.cart.isEmpty()) {
                Box(Modifier.weight(1f).fillMaxWidth(), contentAlignment = Alignment.Center) {
                    Text("Scan a product or add a manual item.")
                }
            } else {
                LazyColumn(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    items(state.cart, key = CartLine::key) { line ->
                        CartRow(line, viewModel)
                    }
                }
            }
            HorizontalDivider()
            Row(Modifier.fillMaxWidth().padding(vertical = 12.dp), horizontalArrangement = Arrangement.SpaceBetween) {
                Text("Estimated total", style = MaterialTheme.typography.titleLarge)
                Text(Money.format(state.cartTotalCents), style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
            }
            Button(
                onClick = viewModel::showCheckout,
                enabled = state.cart.isNotEmpty(),
                modifier = Modifier.fillMaxWidth().height(56.dp),
            ) { Text("Cash checkout") }
        }
    }
}

@Composable
private fun CartRow(line: CartLine, viewModel: PosViewModel) {
    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Column(Modifier.weight(1f)) {
            Text(line.description, fontWeight = FontWeight.SemiBold)
            Text("${line.quantity} × ${Money.format(line.unitPriceIncludingTaxCents)} • VAT ${line.taxRateBasisPoints / 100}%")
        }
        OutlinedButton(onClick = { viewModel.decrement(line) }) { Text("−") }
        Spacer(Modifier.width(4.dp))
        OutlinedButton(onClick = { viewModel.increment(line) }) { Text("+") }
        Spacer(Modifier.width(8.dp))
        Text(Money.format(line.totalCents), modifier = Modifier.width(90.dp))
        TextButton(onClick = { viewModel.remove(line) }) { Text("Remove") }
    }
}

@Composable
private fun ManualItemDialog(onDismiss: () -> Unit, onAdd: (String, String, String, Int) -> Unit) {
    var description by remember { mutableStateOf("") }
    var quantity by remember { mutableStateOf("1") }
    var price by remember { mutableStateOf("") }
    var vat by remember { mutableIntStateOf(2100) }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Manual item") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                OutlinedTextField(description, { description = it }, label = { Text("Description") }, modifier = Modifier.fillMaxWidth())
                OutlinedTextField(
                    quantity,
                    { quantity = it },
                    label = { Text("Quantity") },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                    modifier = Modifier.fillMaxWidth(),
                )
                OutlinedTextField(
                    price,
                    { price = it },
                    label = { Text("Unit price including VAT (EUR)") },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                    modifier = Modifier.fillMaxWidth(),
                )
                Row(verticalAlignment = Alignment.CenterVertically) {
                    RadioButton(vat == 900, { vat = 900 }); Text("9% VAT")
                    Spacer(Modifier.width(20.dp))
                    RadioButton(vat == 2100, { vat = 2100 }); Text("21% VAT")
                }
            }
        },
        confirmButton = { Button(onClick = { onAdd(description, quantity, price, vat) }) { Text("Add") } },
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel") } },
    )
}

@Composable
private fun CheckoutScreen(state: PosUiState, viewModel: PosViewModel) {
    var tendered by remember { mutableStateOf("") }
    Column(
        Modifier.fillMaxSize().padding(28.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Card(Modifier.width(620.dp)) {
            Column(Modifier.padding(28.dp), verticalArrangement = Arrangement.spacedBy(18.dp)) {
                Text("Cash checkout", style = MaterialTheme.typography.headlineMedium)
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("Estimated total", style = MaterialTheme.typography.titleLarge)
                    Text(Money.format(state.cartTotalCents), style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
                }
                Text("Laravel will validate stock and return the accepted VAT totals before the sale is confirmed.")
                OutlinedTextField(
                    tendered,
                    { tendered = it },
                    label = { Text("Cash tendered (EUR)") },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                    modifier = Modifier.fillMaxWidth(),
                )
                Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    OutlinedButton(onClick = viewModel::showPos) { Text("Back") }
                    Button(
                        onClick = { viewModel.submitCheckout(tendered) },
                        enabled = !state.loading,
                        modifier = Modifier.weight(1f),
                    ) { Text("Confirm cash sale") }
                }
            }
        }
    }
}

@Composable
private fun ConfirmationScreen(state: PosUiState, viewModel: PosViewModel) {
    val order = state.selectedOrder
    if (order === null) {
        Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Text("Order details are unavailable.")
                Button(onClick = viewModel::showOrders) { Text("Back to orders") }
            }
        }
        return
    }

    Row(Modifier.fillMaxSize().padding(22.dp), horizontalArrangement = Arrangement.spacedBy(20.dp)) {
        OrderDetails(order, Modifier.weight(1f).fillMaxHeight())
        Card(Modifier.width(340.dp).fillMaxHeight()) {
            Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                Text("Sale confirmed", style = MaterialTheme.typography.headlineSmall, color = MaterialTheme.colorScheme.primary)
                Text(order.orderNumber, style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                Text("Accepted total")
                Text(Money.format(order.totals.includingTaxCents), style = MaterialTheme.typography.headlineMedium)
                order.payment?.let {
                    Text("Tendered: ${Money.format(it.tenderedAmountCents)}")
                    Text("Change: ${Money.format(it.changeDueCents)}", style = MaterialTheme.typography.titleLarge)
                }
                Spacer(Modifier.weight(1f))
                Button(onClick = viewModel::retryPrint, enabled = !state.printing, modifier = Modifier.fillMaxWidth()) {
                    Text(if (state.printing) "Printing…" else "Print receipt")
                }
                OutlinedButton(onClick = { viewModel.reprint(order.orderNumber) }, modifier = Modifier.fillMaxWidth()) {
                    Text("Print COPY")
                }
                Button(onClick = viewModel::newSale, modifier = Modifier.fillMaxWidth()) { Text("New sale") }
                TextButton(onClick = viewModel::showOrders, modifier = Modifier.fillMaxWidth()) { Text("Orders") }
            }
        }
    }
}

@Composable
private fun OrderDetails(order: OrderDto, modifier: Modifier = Modifier) {
    Card(modifier) {
        Column(Modifier.padding(18.dp).fillMaxSize()) {
            Text("Receipt preview", style = MaterialTheme.typography.headlineSmall)
            Text("${order.orderedAtLocal.replace('T', ' ').substringBefore('.')} • ${order.cashier.name}")
            HorizontalDivider(Modifier.padding(vertical = 10.dp))
            LazyColumn(Modifier.weight(1f)) {
                items(order.items.orEmpty(), key = { it.id }) { item ->
                    Row(Modifier.fillMaxWidth().padding(vertical = 7.dp), horizontalArrangement = Arrangement.SpaceBetween) {
                        Column {
                            Text(item.description, fontWeight = FontWeight.SemiBold)
                            Text("${item.quantity.removeSuffix(".000")} × ${Money.format(item.unitPriceIncludingTaxCents)}")
                        }
                        Text(Money.format(item.lineIncludingTaxCents))
                    }
                }
            }
            HorizontalDivider()
            SummaryLine("Excluding VAT", order.totals.excludingTaxCents)
            SummaryLine("VAT 9%", order.totals.vat9Cents)
            SummaryLine("VAT 21%", order.totals.vat21Cents)
            SummaryLine("Total", order.totals.includingTaxCents, true)
        }
    }
}

@Composable
private fun SummaryLine(label: String, cents: Int, bold: Boolean = false) {
    Row(Modifier.fillMaxWidth().padding(vertical = 3.dp), horizontalArrangement = Arrangement.SpaceBetween) {
        Text(label, fontWeight = if (bold) FontWeight.Bold else FontWeight.Normal)
        Text(Money.format(cents), fontWeight = if (bold) FontWeight.Bold else FontWeight.Normal)
    }
}

@Composable
private fun OrdersScreen(state: PosUiState, viewModel: PosViewModel) {
    Column(Modifier.fillMaxSize().padding(20.dp)) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Text("Recent orders", style = MaterialTheme.typography.headlineSmall)
            OutlinedButton(onClick = viewModel::loadOrders) { Text("Refresh") }
        }
        Spacer(Modifier.height(12.dp))
        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            items(state.orders, key = OrderDto::id) { order ->
                Card(Modifier.fillMaxWidth()) {
                    Row(Modifier.fillMaxWidth().padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                        Column(Modifier.weight(1f)) {
                            Text(order.orderNumber, fontWeight = FontWeight.Bold)
                            Text("${order.orderedAtLocal.replace('T', ' ').substringBefore('.')} • ${order.paymentStatus}")
                        }
                        Text(Money.format(order.totals.includingTaxCents), style = MaterialTheme.typography.titleMedium)
                        Spacer(Modifier.width(12.dp))
                        OutlinedButton(onClick = { viewModel.openOrder(order.orderNumber) }) { Text("View") }
                        Spacer(Modifier.width(8.dp))
                        Button(onClick = { viewModel.reprint(order.orderNumber) }) { Text("Reprint") }
                    }
                }
            }
        }
    }
}

@Composable
private fun SettingsScreen(state: PosUiState, viewModel: PosViewModel) {
    val device = state.device
    val printer = device?.printer
    Column(Modifier.fillMaxSize().padding(24.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        Text("Settings & diagnostics", style = MaterialTheme.typography.headlineSmall)
        Card(Modifier.fillMaxWidth()) {
            Column(Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text("Device", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                Text("${device?.name.orEmpty()} • ${device?.uuid.orEmpty()}")
                Text("Cashier: ${state.user?.name.orEmpty()} (${state.user?.role.orEmpty()})")
            }
        }
        Card(Modifier.fillMaxWidth()) {
            Column(Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text("Backend-managed printer", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                Text("Status: ${if (printer?.configured == true) "Configured" else "Not configured"}")
                Text("Model: ${printer?.model ?: "—"}")
                Text("Address: ${printer?.ipAddress ?: "—"}:${printer?.port ?: "—"}")
                Text("Paper: ${printer?.paperWidth?.let { "$it mm" } ?: "—"}")
                Text("Change these values in Laravel Admin → POS → Devices.")
            }
        }
        Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            Button(onClick = viewModel::refreshSettings) { Text("Refresh settings") }
            OutlinedButton(onClick = viewModel::checkServer) { Text("Test server") }
            OutlinedButton(onClick = viewModel::testPrinter, enabled = printer?.configured == true && !state.printing) {
                Text(if (state.printing) "Printing…" else "Test printer")
            }
            Spacer(Modifier.weight(1f))
            TextButton(onClick = viewModel::logout) { Text("Sign out") }
        }
        Text("API: ${BuildConfig.API_BASE_URL} • Host: ${BuildConfig.API_HOST}")
    }
}

@Composable
private fun MessageBanner(message: String, onDismiss: () -> Unit) {
    Surface(color = MaterialTheme.colorScheme.secondaryContainer, modifier = Modifier.fillMaxWidth()) {
        Row(Modifier.padding(horizontal = 18.dp, vertical = 10.dp), verticalAlignment = Alignment.CenterVertically) {
            Text(message, modifier = Modifier.weight(1f))
            TextButton(onClick = onDismiss) { Text("Dismiss") }
        }
    }
}
