package com.sunmipos.cash.data

import okhttp3.ResponseBody
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.Header
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

interface PosApi {
    @GET("up")
    suspend fun health(): Response<ResponseBody>

    @POST("api/v1/auth/login")
    suspend fun login(@Body request: LoginRequest): Response<DataEnvelope<LoginData>>

    @POST("api/v1/auth/logout")
    suspend fun logout(): Response<Unit>

    @GET("api/v1/me")
    suspend fun me(): Response<DataEnvelope<MeData>>

    @GET("api/v1/products")
    suspend fun products(@Query("search") search: String): Response<PaginatedEnvelope<ProductDto>>

    @GET("api/v1/products/by-barcode/{barcode}")
    suspend fun productByBarcode(@Path("barcode") barcode: String): Response<DataEnvelope<ProductDto>>

    @POST("api/v1/checkouts")
    suspend fun checkout(
        @Header("Idempotency-Key") idempotencyKey: String,
        @Body request: CheckoutRequest,
    ): Response<DataEnvelope<OrderDto>>

    @GET("api/v1/orders")
    suspend fun orders(): Response<PaginatedEnvelope<OrderDto>>

    @GET("api/v1/orders/{orderNumber}")
    suspend fun order(@Path("orderNumber") orderNumber: String): Response<DataEnvelope<OrderDto>>

    @GET("api/v1/orders/{orderNumber}/receipt")
    suspend fun receipt(@Path("orderNumber") orderNumber: String): Response<DataEnvelope<OrderDto>>
}
