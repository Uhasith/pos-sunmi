<?php

use App\Actions\Inventory\PostInventory;
use App\Enums\InventoryTransactionType;
use App\Enums\UserRole;
use App\Exceptions\FractionalWooCommerceStock;
use App\Exceptions\WooCommerceRequestFailed;
use App\Integrations\WooCommerce\WooCommerceClient;
use App\Jobs\SyncProductStockToWooCommerce;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('services.woocommerce', [
        'base_url' => 'https://shop.example',
        'key' => 'ck_test',
        'secret' => 'cs_test',
    ]);
});

it('projects the current simple-product balance on every retry', function () {
    Queue::fake([SyncProductStockToWooCommerce::class]);
    $product = Product::factory()->create([
        'woocommerce_product_id' => 123,
    ]);
    app(PostInventory::class)->handle(
        $product,
        '2.000',
        InventoryTransactionType::OpeningBalance,
        'opening-simple-stock',
    );
    app(PostInventory::class)->handle(
        $product,
        '5.000',
        InventoryTransactionType::ManualAdjustment,
        'adjust-simple-stock',
        User::factory()->withRole(UserRole::Manager)->create(),
        'Stock count',
    );
    Http::preventStrayRequests();
    Http::fake([
        'https://shop.example/wp-json/wc/v3/products/123' => Http::response(['id' => 123]),
    ]);

    $job = new SyncProductStockToWooCommerce($product->id);
    $job->handle(app(WooCommerceClient::class));
    $job->handle(app(WooCommerceClient::class));

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://shop.example/wp-json/wc/v3/products/123'
        && $request->data() === [
            'manage_stock' => true,
            'stock_quantity' => 7,
        ]);
});

it('projects variation stock through its parent product endpoint', function () {
    Queue::fake([SyncProductStockToWooCommerce::class]);
    $product = Product::factory()->create([
        'woocommerce_product_id' => 123,
        'woocommerce_variation_id' => 456,
    ]);
    app(PostInventory::class)->handle(
        $product,
        '3.000',
        InventoryTransactionType::OpeningBalance,
        'opening-variation-stock',
    );
    Http::preventStrayRequests();
    Http::fake([
        'https://shop.example/wp-json/wc/v3/products/123/variations/456' => Http::response(['id' => 456]),
    ]);

    (new SyncProductStockToWooCommerce($product->id))->handle(app(WooCommerceClient::class));

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://shop.example/wp-json/wc/v3/products/123/variations/456'
        && $request['stock_quantity'] === 3);
});

it('leaves HTTP failures retryable without exposing the provider response', function () {
    $product = Product::factory()->create([
        'woocommerce_product_id' => 123,
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://shop.example/wp-json/wc/v3/products/123' => Http::response([
            'message' => 'sensitive provider response',
        ], 503),
    ]);

    expect(fn () => (new SyncProductStockToWooCommerce($product->id))
        ->handle(app(WooCommerceClient::class)))
        ->toThrow(WooCommerceRequestFailed::class, 'WooCommerce stock update failed with HTTP 503.');

    Http::assertSentCount(1);
});

it('permanently fails fractional stock without making a request', function () {
    $product = Product::factory()->create();
    app(PostInventory::class)->handle(
        $product,
        '1.250',
        InventoryTransactionType::OpeningBalance,
        'opening-fractional-stock',
    );
    $product->update(['woocommerce_product_id' => 123]);
    Http::preventStrayRequests();
    $job = (new SyncProductStockToWooCommerce($product->id))->withFakeQueueInteractions();

    $job->handle(app(WooCommerceClient::class));

    $job->assertFailedWith(FractionalWooCommerceStock::class);
    Http::assertNothingSent();
});
