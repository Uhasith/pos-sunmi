<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Middleware\EnsureActivePosContext;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/login', [AuthTokenController::class, 'store'])
        ->middleware('throttle:login')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', EnsureActivePosContext::class])->group(function () {
        Route::get('me', [AuthTokenController::class, 'show'])->name('me');
        Route::post('auth/logout', [AuthTokenController::class, 'destroy'])->name('auth.logout');

        Route::get('products/by-barcode/{barcode}', [ProductController::class, 'byBarcode'])
            ->name('products.by-barcode');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
    });
});
