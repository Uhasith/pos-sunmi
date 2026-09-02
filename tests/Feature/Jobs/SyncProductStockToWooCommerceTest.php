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
    $this->travelTo('2026-09-02 12:00:00');
    Queue::fake([SyncProductStockToWooCommerce::class]);
    $product = Product::factory()->create([
        'woocommerce_product_id' => 123,
    ]);
    $product->forceFill([
        'woocommerce_stock_sync_failed_at' => now()->subMinute(),
        'woocommerce_stock_sync_failure_code' => 'request_failed',
    ])->saveQuietly();
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
    expect($product->refresh())
        ->woocommerce_stock_synced_at->toDateTimeString()->toBe('2026-09-02 12:00:00')
        ->woocommerce_stock_sync_failed_at->toBeNull()
        ->woocommerce_stock_sync_failure_code->toBeNull();
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

it('does not mark a changed WooCommerce mapping as synchronized by an older request', function () {
    $product = Product::factory()->create([
        'woocommerce_product_id' => 123,
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://shop.example/wp-json/wc/v3/products/123' => function () use ($product) {
            $product->update(['woocommerce_variation_id' => 456]);

            return Http::response(['id' => 123]);
        },
    ]);

    (new SyncProductStockToWooCommerce($product->id))->handle(app(WooCommerceClient::class));

    expect($product->refresh())
        ->woocommerce_variation_id->toBe(456)
        ->woocommerce_stock_synced_at->toBeNull();
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

    $job = new SyncProductStockToWooCommerce($product->id);

    expect(fn () => $job->handle(app(WooCommerceClient::class)))
        ->toThrow(WooCommerceRequestFailed::class, 'WooCommerce stock update failed with HTTP 503.');

    $job->failed(new WooCommerceRequestFailed('Safe failure'));

    Http::assertSentCount(1);
    expect($product->refresh())
        ->woocommerce_stock_sync_failed_at->not->toBeNull()
        ->woocommerce_stock_sync_failure_code->toBe('request_failed');
    expect(json_encode($product->getAttributes(), JSON_THROW_ON_ERROR))
        ->not->toContain('sensitive provider response');
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
    $job->failed(new FractionalWooCommerceStock('Fractional stock'));

    $job->assertFailedWith(FractionalWooCommerceStock::class);
    expect($product->refresh()->woocommerce_stock_sync_failure_code)->toBe('fractional_stock');
    Http::assertNothingSent();
});

it('clears stale sync status when a WooCommerce mapping changes', function () {
    $product = Product::factory()->create(['woocommerce_product_id' => 123]);
    $product->forceFill([
        'woocommerce_stock_synced_at' => now(),
        'woocommerce_stock_sync_failed_at' => now(),
        'woocommerce_stock_sync_failure_code' => 'request_failed',
    ])->saveQuietly();

    $product->update(['woocommerce_variation_id' => 456]);

    expect($product->refresh())
        ->woocommerce_stock_synced_at->toBeNull()
        ->woocommerce_stock_sync_failed_at->toBeNull()
        ->woocommerce_stock_sync_failure_code->toBeNull();
});
