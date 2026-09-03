package com.sunmipos.cash.data

import java.security.MessageDigest

object Cart {
    fun addProduct(lines: List<CartLine>, product: ProductDto): List<CartLine> {
        val key = "product:${product.id}"
        val existing = lines.firstOrNull { it.key == key }
        if (existing !== null) {
            return lines.map { if (it.key == key) it.copy(quantity = it.quantity + 1) else it }
        }

        return lines + CartLine(
            key = key,
            type = "product",
            productId = product.id,
            articleNumber = product.articleNumber,
            description = product.title,
            quantity = 1,
            unitPriceIncludingTaxCents = product.sellingPriceIncludingTaxCents,
            taxRateBasisPoints = product.taxRate.rateBasisPoints,
        )
    }

    fun addManual(
        lines: List<CartLine>,
        description: String,
        quantity: Int,
        unitPriceCents: Int,
        vatBasisPoints: Int,
        key: String,
    ): List<CartLine> = lines + CartLine(
        key = "manual:$key",
        type = "manual",
        description = description.trim(),
        quantity = quantity,
        unitPriceIncludingTaxCents = unitPriceCents,
        taxRateBasisPoints = vatBasisPoints,
    )

    fun checkoutRequest(lines: List<CartLine>, tenderedCents: Int): CheckoutRequest = CheckoutRequest(
        tenderedAmountCents = tenderedCents,
        items = lines.map { line ->
            if (line.type == "product") {
                CheckoutItemRequest(
                    type = "product",
                    productId = line.productId,
                    quantity = line.quantity,
                    expectedUnitPriceIncludingTaxCents = line.unitPriceIncludingTaxCents,
                    expectedTaxRateBasisPoints = line.taxRateBasisPoints,
                )
            } else {
                CheckoutItemRequest(
                    type = "manual",
                    quantity = line.quantity,
                    description = line.description,
                    unitPriceIncludingTaxCents = line.unitPriceIncludingTaxCents,
                    taxRateBasisPoints = line.taxRateBasisPoints,
                )
            }
        },
    )

    fun fingerprint(lines: List<CartLine>): String {
        val canonical = lines.joinToString("|") {
            listOf(
                it.key,
                it.type,
                it.productId,
                it.description,
                it.quantity,
                it.unitPriceIncludingTaxCents,
                it.taxRateBasisPoints,
            ).joinToString(":")
        }
        return MessageDigest.getInstance("SHA-256")
            .digest(canonical.toByteArray())
            .joinToString("") { "%02x".format(it) }
    }
}

object Money {
    fun parseCents(value: String): Int? {
        val normalized = value.trim().replace(',', '.')
        if (!normalized.matches(Regex("\\d+(?:\\.\\d{1,2})?"))) return null
        val parts = normalized.split('.')
        val euros = parts[0].toLongOrNull() ?: return null
        val cents = parts.getOrNull(1)?.padEnd(2, '0')?.toIntOrNull() ?: 0
        val total = euros * 100 + cents
        return total.takeIf { it <= Int.MAX_VALUE }?.toInt()
    }

    fun format(cents: Int, currency: String = "EUR"): String = "%s %.2f".format(currency, cents / 100.0)
}
