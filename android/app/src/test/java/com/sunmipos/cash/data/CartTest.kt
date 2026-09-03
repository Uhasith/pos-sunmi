package com.sunmipos.cash.data

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotEquals
import org.junit.Assert.assertNull
import org.junit.Test

class CartTest {
    private val product = ProductDto(
        id = 42,
        title = "Coffee beans",
        articleNumber = "COFFEE-1",
        barcode = "0012345678905",
        sellingPriceIncludingTaxCents = 109,
        stockOnHand = "10.000",
        taxRate = TaxRateDto("VAT_9", "VAT 9%", 900),
    )

    @Test
    fun `scanning a product adds it and a repeated scan increments it`() {
        val once = Cart.addProduct(emptyList(), product)
        val twice = Cart.addProduct(once, product)

        assertEquals(1, once.size)
        assertEquals("0012345678905", product.barcode)
        assertEquals(1, once.single().quantity)
        assertEquals(2, twice.single().quantity)
    }

    @Test
    fun `manual lines contain no catalog product and preserve VAT`() {
        val lines = Cart.addManual(emptyList(), " Gift wrap ", 2, 121, 2100, "line-1")
        val request = Cart.checkoutRequest(lines, 500)

        assertEquals("Gift wrap", lines.single().description)
        assertNull(request.items.single().productId)
        assertEquals(2100, request.items.single().taxRateBasisPoints)
        assertEquals(500, request.tenderedAmountCents)
    }

    @Test
    fun `editing the cart changes its checkout fingerprint`() {
        val once = Cart.addProduct(emptyList(), product)
        val twice = Cart.addProduct(once, product)

        assertNotEquals(Cart.fingerprint(once), Cart.fingerprint(twice))
    }

    @Test
    fun `money input accepts cents and rejects excess precision`() {
        assertEquals(1250, Money.parseCents("12.50"))
        assertEquals(1250, Money.parseCents("12,5"))
        assertNull(Money.parseCents("12.500"))
        assertNull(Money.parseCents("-1"))
    }
}
