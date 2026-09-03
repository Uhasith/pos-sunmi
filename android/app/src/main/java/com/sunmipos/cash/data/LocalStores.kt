package com.sunmipos.cash.data

import android.content.Context
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import com.google.gson.Gson
import java.security.KeyStore
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

class SecureTokenStore(context: Context) {
    private val preferences = context.getSharedPreferences("secure_session", Context.MODE_PRIVATE)
    private val keyStore = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }

    fun read(): String? = runCatching {
        val encoded = preferences.getString(TOKEN, null) ?: return null
        val payload = Base64.decode(encoded, Base64.NO_WRAP)
        val ivSize = payload.first().toInt()
        val iv = payload.copyOfRange(1, ivSize + 1)
        val ciphertext = payload.copyOfRange(ivSize + 1, payload.size)
        val cipher = Cipher.getInstance(TRANSFORMATION)
        cipher.init(Cipher.DECRYPT_MODE, key(), GCMParameterSpec(128, iv))
        String(cipher.doFinal(ciphertext), Charsets.UTF_8)
    }.getOrElse {
        clear()
        null
    }

    fun write(token: String) {
        val cipher = Cipher.getInstance(TRANSFORMATION)
        cipher.init(Cipher.ENCRYPT_MODE, key())
        val ciphertext = cipher.doFinal(token.toByteArray(Charsets.UTF_8))
        val payload = byteArrayOf(cipher.iv.size.toByte()) + cipher.iv + ciphertext
        preferences.edit().putString(TOKEN, Base64.encodeToString(payload, Base64.NO_WRAP)).apply()
    }

    fun clear() {
        preferences.edit().remove(TOKEN).apply()
    }

    private fun key(): SecretKey {
        val existing = keyStore.getKey(KEY_ALIAS, null)
        if (existing is SecretKey) return existing

        return KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, "AndroidKeyStore").run {
            init(
                KeyGenParameterSpec.Builder(
                    KEY_ALIAS,
                    KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT,
                )
                    .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                    .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                    .build(),
            )
            generateKey()
        }
    }

    private companion object {
        const val TOKEN = "token"
        const val KEY_ALIAS = "sunmi_pos_token"
        const val TRANSFORMATION = "AES/GCM/NoPadding"
    }
}

class AppPreferences(context: Context, private val gson: Gson) {
    private val preferences = context.getSharedPreferences("pos_state", Context.MODE_PRIVATE)

    var deviceUuid: String
        get() = preferences.getString(DEVICE_UUID, "").orEmpty()
        set(value) = preferences.edit().putString(DEVICE_UUID, value).apply()

    fun cart(): CartSnapshot = read(CART, CartSnapshot::class.java) ?: CartSnapshot()

    fun saveCart(cart: CartSnapshot) = write(CART, cart)

    fun pendingCheckout(): PendingCheckout? = read(PENDING, PendingCheckout::class.java)

    fun savePendingCheckout(pending: PendingCheckout?) {
        if (pending === null) preferences.edit().remove(PENDING).apply() else write(PENDING, pending)
    }

    fun clearSaleState() {
        preferences.edit().remove(CART).remove(PENDING).apply()
    }

    private fun write(key: String, value: Any) {
        preferences.edit().putString(key, gson.toJson(value)).apply()
    }

    private fun <T> read(key: String, type: Class<T>): T? = runCatching {
        preferences.getString(key, null)?.let { gson.fromJson(it, type) }
    }.getOrNull()

    private companion object {
        const val DEVICE_UUID = "device_uuid"
        const val CART = "cart"
        const val PENDING = "pending_checkout"
    }
}
