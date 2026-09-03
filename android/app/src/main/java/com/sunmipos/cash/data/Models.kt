package com.sunmipos.cash.data

import com.google.gson.annotations.SerializedName

data class DataEnvelope<T>(val data: T)

data class PaginatedEnvelope<T>(
    val data: List<T>,
    val meta: PaginationMeta? = null,
)

data class PaginationMeta(
    @SerializedName("current_page") val currentPage: Int = 1,
    @SerializedName("last_page") val lastPage: Int = 1,
    @SerializedName("per_page") val perPage: Int = 20,
)

data class ErrorEnvelope(val error: ApiError)

data class ApiError(
    val code: String,
    val message: String,
    val details: Map<String, List<String>>? = null,
    @SerializedName("request_id") val requestId: String? = null,
)

data class LoginRequest(
    val email: String,
    val password: String,
    @SerializedName("device_uuid") val deviceUuid: String,
)

data class LoginData(
    val token: String,
    val user: UserDto,
    val device: DeviceDto,
)

data class MeData(
    val user: UserDto,
    val device: DeviceDto,
)

data class UserDto(
    val id: Long,
    val name: String,
    val email: String,
    val role: String,
)

data class DeviceDto(
    val uuid: String,
    val name: String,
    val printer: PrinterDto,
)

data class PrinterDto(
    val model: String? = null,
    @SerializedName("ip_address") val ipAddress: String? = null,
    val port: Int? = null,
    @SerializedName("paper_width") val paperWidth: Int? = null,
    val configured: Boolean = false,
)

data class ProductDto(
    val id: Long,
    val title: String,
    @SerializedName("article_number") val articleNumber: String,
    val barcode: String? = null,
    @SerializedName("selling_price_including_tax_cents") val sellingPriceIncludingTaxCents: Int,
    @SerializedName("stock_on_hand") val stockOnHand: String,
    @SerializedName("tax_rate") val taxRate: TaxRateDto,
)

data class TaxRateDto(
    val code: String,
    val label: String,
    @SerializedName("rate_basis_points") val rateBasisPoints: Int,
)

data class CheckoutRequest(
    @SerializedName("payment_method") val paymentMethod: String = "cash",
    @SerializedName("tendered_amount_cents") val tenderedAmountCents: Int,
    val items: List<CheckoutItemRequest>,
)

data class CheckoutItemRequest(
    val type: String,
    @SerializedName("product_id") val productId: Long? = null,
    val quantity: Int,
    @SerializedName("expected_unit_price_including_tax_cents") val expectedUnitPriceIncludingTaxCents: Int? = null,
    @SerializedName("expected_tax_rate_basis_points") val expectedTaxRateBasisPoints: Int? = null,
    val description: String? = null,
    @SerializedName("unit_price_including_tax_cents") val unitPriceIncludingTaxCents: Int? = null,
    @SerializedName("tax_rate_basis_points") val taxRateBasisPoints: Int? = null,
)

data class OrderDto(
    val id: Long,
    @SerializedName("order_number") val orderNumber: String,
    val source: String,
    val status: String,
    @SerializedName("payment_status") val paymentStatus: String,
    @SerializedName("payment_method") val paymentMethod: String,
    val currency: String,
    @SerializedName("ordered_at_local") val orderedAtLocal: String,
    @SerializedName("shop_timezone") val shopTimezone: String,
    val cashier: NamedActorDto,
    val device: OrderDeviceDto,
    val items: List<OrderItemDto>? = null,
    val totals: OrderTotalsDto,
    val payment: PaymentDto? = null,
    val receipt: ReceiptDto? = null,
)

data class NamedActorDto(val id: Long?, val name: String)

data class OrderDeviceDto(val uuid: String, val name: String)

data class OrderItemDto(
    val id: Long,
    val position: Int,
    val type: String,
    @SerializedName("product_id") val productId: Long? = null,
    @SerializedName("article_number") val articleNumber: String? = null,
    val description: String,
    val quantity: String,
    @SerializedName("unit_price_including_tax_cents") val unitPriceIncludingTaxCents: Int,
    @SerializedName("tax_rate_basis_points") val taxRateBasisPoints: Int,
    @SerializedName("line_excluding_tax_cents") val lineExcludingTaxCents: Int,
    @SerializedName("tax_amount_cents") val taxAmountCents: Int,
    @SerializedName("line_including_tax_cents") val lineIncludingTaxCents: Int,
)

data class OrderTotalsDto(
    @SerializedName("excluding_tax_cents") val excludingTaxCents: Int,
    @SerializedName("vat_9_cents") val vat9Cents: Int,
    @SerializedName("vat_21_cents") val vat21Cents: Int,
    @SerializedName("tax_total_cents") val taxTotalCents: Int,
    @SerializedName("including_tax_cents") val includingTaxCents: Int,
)

data class PaymentDto(
    val id: Long,
    val method: String,
    val status: String,
    @SerializedName("amount_cents") val amountCents: Int,
    @SerializedName("tendered_amount_cents") val tenderedAmountCents: Int,
    @SerializedName("change_due_cents") val changeDueCents: Int,
    val currency: String,
)

data class ReceiptDto(val shop: ShopDto)

data class ShopDto(
    val name: String? = null,
    @SerializedName("address_line_1") val addressLine1: String? = null,
    @SerializedName("address_line_2") val addressLine2: String? = null,
    @SerializedName("postal_code") val postalCode: String? = null,
    val city: String? = null,
    @SerializedName("country_code") val countryCode: String? = null,
    @SerializedName("vat_number") val vatNumber: String? = null,
    @SerializedName("chamber_of_commerce_number") val chamberOfCommerceNumber: String? = null,
)

data class CartLine(
    val key: String,
    val type: String,
    val productId: Long? = null,
    val articleNumber: String? = null,
    val description: String,
    val quantity: Int,
    val unitPriceIncludingTaxCents: Int,
    val taxRateBasisPoints: Int,
) {
    val totalCents: Int get() = quantity * unitPriceIncludingTaxCents
}

data class CartSnapshot(val lines: List<CartLine> = emptyList())

data class PendingCheckout(
    val idempotencyKey: String,
    val cartFingerprint: String,
    val request: CheckoutRequest,
)
