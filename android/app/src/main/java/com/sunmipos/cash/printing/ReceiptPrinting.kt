package com.sunmipos.cash.printing

import com.sunmipos.cash.data.Money
import com.sunmipos.cash.data.OrderDto
import com.sunmipos.cash.data.PrinterDto
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.ByteArrayOutputStream
import java.net.InetSocketAddress
import java.net.Socket
import java.nio.charset.Charset

interface ReceiptPrinter {
    suspend fun print(order: OrderDto, settings: PrinterDto, isCopy: Boolean): Result<Unit>
    suspend fun test(settings: PrinterDto): Result<Unit>
}

class NetworkReceiptPrinter : ReceiptPrinter {
    override suspend fun print(order: OrderDto, settings: PrinterDto, isCopy: Boolean): Result<Unit> =
        send(settings, ReceiptBuilder.sale(order, settings.paperWidth ?: 80, isCopy))

    override suspend fun test(settings: PrinterDto): Result<Unit> =
        send(settings, ReceiptBuilder.test(settings.paperWidth ?: 80))

    private suspend fun send(settings: PrinterDto, bytes: ByteArray): Result<Unit> = withContext(Dispatchers.IO) {
        runCatching {
            require(settings.configured && settings.ipAddress !== null && settings.port !== null) {
                "Printer settings are incomplete."
            }
            Socket().use { socket ->
                socket.connect(InetSocketAddress(settings.ipAddress, settings.port), 4_000)
                socket.soTimeout = 5_000
                socket.getOutputStream().use { output ->
                    output.write(bytes)
                    output.flush()
                }
            }
        }
    }
}

object ReceiptBuilder {
    private val charset: Charset = Charset.forName("Cp858")
    private val initialize = byteArrayOf(0x1B, 0x40)
    private val cp858 = byteArrayOf(0x1B, 0x74, 19)
    private val alignLeft = byteArrayOf(0x1B, 0x61, 0)
    private val alignCenter = byteArrayOf(0x1B, 0x61, 1)
    private val boldOn = byteArrayOf(0x1B, 0x45, 1)
    private val boldOff = byteArrayOf(0x1B, 0x45, 0)
    private val cut = byteArrayOf(0x1D, 0x56, 1)

    fun sale(order: OrderDto, paperWidth: Int, isCopy: Boolean): ByteArray {
        val width = if (paperWidth == 58) 32 else 48
        return ByteArrayOutputStream().apply {
            write(initialize)
            write(cp858)
            write(alignCenter)
            write(boldOn)
            line(order.receipt?.shop?.name ?: "Receipt")
            write(boldOff)
            order.receipt?.shop?.let { shop ->
                listOfNotNull(shop.addressLine1, shop.addressLine2)
                    .filter(String::isNotBlank)
                    .forEach { address -> line(address) }
                val cityLine = listOfNotNull(shop.postalCode, shop.city).filter(String::isNotBlank).joinToString(" ")
                if (cityLine.isNotBlank()) line(cityLine)
                shop.vatNumber?.takeIf(String::isNotBlank)?.let { line("VAT: $it") }
                shop.chamberOfCommerceNumber?.takeIf(String::isNotBlank)?.let { line("KvK: $it") }
            }
            if (isCopy) {
                line("")
                write(boldOn)
                line("*** COPY / REPRINT ***")
                write(boldOff)
            }
            write(alignLeft)
            line("-".repeat(width))
            line("Order: ${order.orderNumber}")
            line(order.orderedAtLocal.replace('T', ' ').substringBefore('.'))
            line("Cashier: ${order.cashier.name}")
            line("Register: ${order.device.name}")
            line("-".repeat(width))
            order.items.orEmpty().forEach { item ->
                line(item.description.take(width))
                val quantity = item.quantity.removeSuffix(".000")
                twoColumns("$quantity x ${Money.format(item.unitPriceIncludingTaxCents)}", Money.format(item.lineIncludingTaxCents), width)
            }
            line("-".repeat(width))
            twoColumns("Excluding VAT", Money.format(order.totals.excludingTaxCents), width)
            twoColumns("VAT 9%", Money.format(order.totals.vat9Cents), width)
            twoColumns("VAT 21%", Money.format(order.totals.vat21Cents), width)
            write(boldOn)
            twoColumns("TOTAL", Money.format(order.totals.includingTaxCents), width)
            write(boldOff)
            order.payment?.let { payment ->
                twoColumns("Cash", Money.format(payment.tenderedAmountCents), width)
                twoColumns("Change", Money.format(payment.changeDueCents), width)
            }
            line("")
            write(alignCenter)
            line("Thank you")
            line("")
            line("")
            line("")
            write(cut)
        }.toByteArray()
    }

    fun test(paperWidth: Int): ByteArray = ByteArrayOutputStream().apply {
        write(initialize)
        write(cp858)
        write(alignCenter)
        write(boldOn)
        line("SUNMI CASH POS")
        write(boldOff)
        line("Printer test - ${paperWidth}mm")
        line("EUR euro: ${Money.format(1234)}")
        line("")
        line("")
        line("")
        write(cut)
    }.toByteArray()

    private fun ByteArrayOutputStream.line(value: String) {
        write(value.toByteArray(charset))
        write('\n'.code)
    }

    private fun ByteArrayOutputStream.twoColumns(left: String, right: String, width: Int) {
        val safeLeft = left.take((width - right.length - 1).coerceAtLeast(1))
        val spaces = (width - safeLeft.length - right.length).coerceAtLeast(1)
        line(safeLeft + " ".repeat(spaces) + right)
    }
}
