<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\InventoryTransactionController;
use App\Http\Controllers\Api\V1\OrderController;
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

        Route::post('checkouts', [CheckoutController::class, 'store'])->name('checkouts.store');
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order:order_number}', [OrderController::class, 'show'])->name('orders.show');

        Route::get('products/by-barcode/{barcode}', [ProductController::class, 'byBarcode'])
            ->name('products.by-barcode');
        Route::get('products/{product}/inventory-transactions', [InventoryTransactionController::class, 'index'])
            ->name('products.inventory-transactions.index');
        Route::post('products/{product}/inventory-transactions', [InventoryTransactionController::class, 'store'])
            ->name('products.inventory-transactions.store');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
    });
});
