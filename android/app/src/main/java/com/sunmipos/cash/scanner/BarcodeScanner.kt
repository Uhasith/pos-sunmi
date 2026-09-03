package com.sunmipos.cash.scanner

import android.view.KeyEvent

interface BarcodeScanner {
    fun accept(event: KeyEvent): String?
}

class HardwareKeyBarcodeScanner : BarcodeScanner {
    private val buffer = StringBuilder()
    private var lastCharacterAt = 0L

    override fun accept(event: KeyEvent): String? {
        if (event.action != KeyEvent.ACTION_DOWN) return null

        if (event.keyCode == KeyEvent.KEYCODE_ENTER) {
            val barcode = buffer.toString().trim()
            buffer.clear()
            return barcode.takeIf(String::isNotEmpty)
        }

        val character = event.unicodeChar.takeIf { it > 0 }?.toChar() ?: return null
        val now = event.eventTime
        if (lastCharacterAt > 0 && now - lastCharacterAt > 500) buffer.clear()
        buffer.append(character)
        lastCharacterAt = now
        return null
    }
}
