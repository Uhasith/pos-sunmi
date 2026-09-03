package com.sunmipos.cash.printing

import com.sunmipos.cash.data.NamedActorDto
import com.sunmipos.cash.data.OrderDeviceDto
import com.sunmipos.cash.data.OrderDto
import com.sunmipos.cash.data.OrderItemDto
import com.sunmipos.cash.data.OrderTotalsDto
import com.sunmipos.cash.data.PaymentDto
import com.sunmipos.cash.data.ReceiptDto
import com.sunmipos.cash.data.ShopDto
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test
import java.nio.charset.Charset

class ReceiptBuilderTest {
    @Test
    fun `sale receipt contains immutable totals and cash change`() {
        val text = ReceiptBuilder.sale(order(), 80, false).toString(Charset.forName("Cp858"))

        assertTrue(text.contains("POS-000042"))
        assertTrue(text.contains("Coffee beans"))
        assertTrue(text.contains("Excluding VAT"))
        assertTrue(text.contains("VAT 9%"))
        assertTrue(text.contains("EUR 4.60"))
        assertTrue(text.contains("Change"))
        assertFalse(text.contains("COPY / REPRINT"))
    }

    @Test
    fun `reprint is visibly marked as a copy`() {
        val text = ReceiptBuilder.sale(order(), 58, true).toString(Charset.forName("Cp858"))

        assertTrue(text.contains("COPY / REPRINT"))
        assertTrue(text.contains("POS-000042"))
    }

    private fun order(): OrderDto = OrderDto(
        id = 42,
        orderNumber = "POS-000042",
        source = "pos",
        status = "paid",
        paymentStatus = "paid",
        paymentMethod = "cash",
        currency = "EUR",
        orderedAtLocal = "2026-09-03T12:00:00.000000+02:00",
        shopTimezone = "Europe/Amsterdam",
        cashier = NamedActorDto(1, "Local Cashier"),
        device = OrderDeviceDto("device-uuid", "Front register"),
        items = listOf(
            OrderItemDto(1, 1, "product", 4, "COFFEE-1", "Coffee beans", "2.000", 109, 900, 200, 18, 218),
            OrderItemDto(2, 2, "manual", null, null, "Gift wrap", "2.000", 121, 2100, 200, 42, 242),
        ),
        totals = OrderTotalsDto(400, 18, 42, 60, 460),
        payment = PaymentDto(1, "cash", "paid", 460, 500, 40, "EUR"),
        receipt = ReceiptDto(
            ShopDto(
                name = "Example Shop",
                addressLine1 = "Main Street 1",
                postalCode = "1000 AA",
                city = "Amsterdam",
                countryCode = "NL",
                vatNumber = "NL000000000B01",
                chamberOfCommerceNumber = "12345678",
            ),
        ),
    )
}
