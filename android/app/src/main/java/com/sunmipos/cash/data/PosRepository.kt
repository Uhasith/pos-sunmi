package com.sunmipos.cash.data

import com.google.gson.Gson
import retrofit2.Response

class ApiException(
    val status: Int,
    val code: String,
    override val message: String,
    val details: Map<String, List<String>>? = null,
    val requestId: String? = null,
) : Exception(message)

class PosRepository(
    private val api: PosApi,
    private val tokenStore: SecureTokenStore,
    private val gson: Gson,
) {
    suspend fun health(): Boolean = runCatching { api.health().isSuccessful }.getOrDefault(false)

    suspend fun login(email: String, password: String, deviceUuid: String): LoginData {
        val data = body(api.login(LoginRequest(email.trim(), password, deviceUuid.trim()))).data
        tokenStore.write(data.token)
        return data
    }

    suspend fun logout() {
        runCatching { api.logout() }
        tokenStore.clear()
    }

    suspend fun me(): MeData = body(api.me()).data

    suspend fun searchProducts(search: String): List<ProductDto> = body(api.products(search.trim())).data

    suspend fun productByBarcode(barcode: String): ProductDto = body(api.productByBarcode(barcode)).data

    suspend fun checkout(key: String, request: CheckoutRequest): OrderDto = body(api.checkout(key, request)).data

    suspend fun orders(): List<OrderDto> = body(api.orders()).data

    suspend fun order(orderNumber: String): OrderDto = body(api.order(orderNumber)).data

    suspend fun receipt(orderNumber: String): OrderDto = body(api.receipt(orderNumber)).data

    private fun <T> body(response: Response<T>): T {
        if (response.isSuccessful) return response.body() ?: throw ApiException(
            response.code(),
            "empty_response",
            "The server returned an empty response.",
        )

        val parsed = runCatching {
            response.errorBody()?.charStream()?.use { gson.fromJson(it, ErrorEnvelope::class.java) }
        }.getOrNull()?.error

        if (response.code() == 401) tokenStore.clear()

        throw ApiException(
            status = response.code(),
            code = parsed?.code ?: "request_failed",
            message = parsed?.message ?: "The request could not be completed.",
            details = parsed?.details,
            requestId = parsed?.requestId,
        )
    }
}
