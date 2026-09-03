package com.sunmipos.cash.data

import com.google.gson.Gson
import com.google.gson.GsonBuilder
import com.sunmipos.cash.BuildConfig
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.UUID
import java.util.concurrent.TimeUnit

object ApiFactory {
    fun gson(): Gson = GsonBuilder().create()

    fun create(tokenStore: SecureTokenStore, gson: Gson): PosApi {
        val client = OkHttpClient.Builder()
            .connectTimeout(8, TimeUnit.SECONDS)
            .readTimeout(15, TimeUnit.SECONDS)
            .writeTimeout(15, TimeUnit.SECONDS)
            .addInterceptor { chain ->
                val request = chain.request().newBuilder()
                    .header("Accept", "application/json")
                    .header("X-Request-ID", UUID.randomUUID().toString())
                    .header("User-Agent", "SunmiCashPOS/${BuildConfig.VERSION_NAME}")
                    .apply {
                        tokenStore.read()?.let { header("Authorization", "Bearer $it") }
                        if (BuildConfig.API_HOST.isNotBlank()) header("Host", BuildConfig.API_HOST)
                    }
                    .build()
                chain.proceed(request)
            }
            .apply {
                if (BuildConfig.DEBUG) {
                    addInterceptor(HttpLoggingInterceptor().setLevel(HttpLoggingInterceptor.Level.BASIC))
                }
            }
            .build()

        return Retrofit.Builder()
            .baseUrl(BuildConfig.API_BASE_URL)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create(gson))
            .build()
            .create(PosApi::class.java)
    }
}
