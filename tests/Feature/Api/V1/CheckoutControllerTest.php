<?php

use App\Enums\InventoryTransactionType;
use App\Enums\UserRole;
use App\Jobs\SyncProductStockToWooCommerce;
use App\Models\Device;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake([SyncProductStockToWooCommerce::class]);
    $this->vat9 = TaxRate::factory()->create(['code' => 'VAT_9', 'rate_basis_points' => 900]);
    $this->vat21 = TaxRate::factory()->create(['code' => 'VAT_21', 'rate_basis_points' => 2100]);
    $this->cashier = User::factory()->create();
    $this->device = Device::factory()->create();
    $this->token = posToken($this->cashier, $this->device)->plainTextToken;
});

it('atomically completes a receipt-ready catalog and manual cash checkout', function () {
    $this->travelTo('2026-01-15 12:00:00');
    $product = Product::factory()->create([
        'title' => 'Coffee beans',
        'article_number' => 'COFFEE-1',
        'selling_price_including_tax_cents' => 109,
        'stock_on_hand' => '10.000',
        'tax_rate_id' => $this->vat9,
    ]);

    $response = $this->withToken($this->token)
        ->withHeader('Idempotency-Key', '89da3858-f385-49c3-a048-aea9be0026e3')
        ->postJson(route('api.v1.checkouts.store'), [
            'payment_method' => 'cash',
            'tendered_amount_cents' => 500,
            'items' => [
                [
                    'type' => 'product',
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'expected_unit_price_including_tax_cents' => 109,
                    'expected_tax_rate_basis_points' => 900,
                ],
                [
                    'type' => 'manual',
                    'description' => '  Gift wrap  ',
                    'quantity' => 2,
                    'unit_price_including_tax_cents' => 121,
                    'tax_rate_basis_points' => 2100,
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.order_number', 'POS-000001')
        ->assertJsonPath('data.ordered_at_utc', '2026-01-15T12:00:00.000000Z')
        ->assertJsonPath('data.ordered_at_local', '2026-01-15T13:00:00.000000+01:00')
        ->assertJsonPath('data.cashier.name', $this->cashier->name)
        ->assertJsonPath('data.device.uuid', $this->device->uuid)
        ->assertJsonPath('data.items.0.article_number', 'COFFEE-1')
        ->assertJsonPath('data.items.0.quantity', '2.000')
        ->assertJsonPath('data.items.0.tax_amount_cents', 18)
        ->assertJsonPath('data.items.1.description', 'Gift wrap')
        ->assertJsonPath('data.items.1.tax_amount_cents', 42)
        ->assertJsonPath('data.totals.excluding_tax_cents', 400)
        ->assertJsonPath('data.totals.vat_9_cents', 18)
        ->assertJsonPath('data.totals.vat_21_cents', 42)
        ->assertJsonPath('data.totals.including_tax_cents', 460)
        ->assertJsonPath('data.payment.amount_cents', 460)
        ->assertJsonPath('data.payment.tendered_amount_cents', 500)
        ->assertJsonPath('data.payment.change_due_cents', 40);

    expect($product->refresh()->stock_on_hand)->toBe('8.000');
    $this->assertDatabaseCount('orders', 1);
    $this->assertDatabaseCount('order_items', 2);
    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('inventory_transactions', 1);
    $this->assertDatabaseHas('inventory_transactions', [
        'product_id' => $product->id,
        'type' => InventoryTransactionType::PosSale->value,
        'quantity_delta' => -2,
        'stock_after' => 8,
    ]);
    expect((int) InventoryTransaction::query()->value('order_item_id'))
        ->toBe((int) $response->json('data.items.0.id'));
    expect($response->json('data.items.1.product_id'))->toBeNull();
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('allows every active staff role to sell from an active device', function (UserRole $role) {
    $user = User::factory()->withRole($role)->create();
    $device = Device::factory()->create();
    $token = posToken($user, $device)->plainTextToken;

    $this->withToken($token)
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson(route('api.v1.checkouts.store'), [
            'payment_method' => 'cash',
            'tendered_amount_cents' => 0,
            'items' => [[
                'type' => 'manual',
                'description' => 'Complimentary item',
                'quantity' => 1,
                'unit_price_including_tax_cents' => 0,
                'tax_rate_basis_points' => 900,
            ]],
        ])
        ->assertCreated();
})->with(UserRole::cases());

it('returns the immutable order on an equivalent retry and rejects changed input', function () {
    $product = Product::factory()->create([
        'title' => 'Original title',
        'article_number' => 'ORIGINAL-1',
        'selling_price_including_tax_cents' => 109,
        'stock_on_hand' => '2.000',
        'tax_rate_id' => $this->vat9,
    ]);
    $key = '3bf4f0cf-8409-44ce-9f64-df5f898df5d7';
    $payload = [
        'payment_method' => 'cash',
        'tendered_amount_cents' => 200,
        'items' => [[
            'type' => 'product',
            'product_id' => $product->id,
            'quantity' => 1,
            'expected_unit_price_including_tax_cents' => 109,
            'expected_tax_rate_basis_points' => 900,
        ]],
    ];
    $first = $this->withToken($this->token)->withHeader('Idempotency-Key', $key)
        ->postJson(route('api.v1.checkouts.store'), $payload)
        ->assertCreated();

    $product->update(['title' => 'Changed title', 'selling_price_including_tax_cents' => 999, 'active' => false]);
    $this->cashier->update(['name' => 'Changed cashier']);
    $this->device->update(['name' => 'Changed device']);

    $this->withToken($this->token)->withHeader('Idempotency-Key', $key)
        ->postJson(route('api.v1.checkouts.store'), $payload)
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'))
        ->assertJsonPath('data.items.0.description', 'Original title')
        ->assertJsonPath('data.items.0.unit_price_including_tax_cents', 109)
        ->assertJsonPath('data.cashier.name', $first->json('data.cashier.name'))
        ->assertJsonPath('data.device.name', $first->json('data.device.name'));

    $payload['tendered_amount_cents'] = 300;
    $this->withToken($this->token)->withHeader('Idempotency-Key', $key)
        ->postJson(route('api.v1.checkouts.store'), $payload)
        ->assertConflict()
        ->assertJsonPath('error.code', 'idempotency_conflict');

    $this->assertDatabaseCount('orders', 1);
    $this->assertDatabaseCount('order_items', 1);
    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('inventory_transactions', 1);
    expect($product->refresh()->stock_on_hand)->toBe('1.000');
});

it('queues one after-commit Woo stock projection for an idempotent mapped checkout', function () {
    $product = Product::factory()->create([
        'selling_price_including_tax_cents' => 121,
        'stock_on_hand' => '3.000',
        'tax_rate_id' => $this->vat21,
        'woocommerce_product_id' => 123,
        'woocommerce_variation_id' => 456,
    ]);
    $payload = catalogCheckoutPayload($product, 121, 2100);
    $key = '2ecdc247-3a2f-4d7a-a620-04a48b6e63b2';

    $this->withToken($this->token)->withHeader('Idempotency-Key', $key)
        ->postJson(route('api.v1.checkouts.store'), $payload)->assertCreated();
    $this->withToken($this->token)->withHeader('Idempotency-Key', $key)
        ->postJson(route('api.v1.checkouts.store'), $payload)->assertOk();

    Queue::assertPushedTimes(SyncProductStockToWooCommerce::class, 1);
    Queue::assertPushed(SyncProductStockToWooCommerce::class, fn (SyncProductStockToWooCommerce $job): bool => $job->productId === $product->id
        && $job->afterCommit === true);
    Http::assertNothingSent();
});

it('rejects stale, unavailable, understocked, and fractional mapped catalog products without effects', function (
    array $overrides,
    int $expectedPrice,
    int $expectedVat,
    string $errorCode,
) {
    $product = Product::factory()->create([
        'selling_price_including_tax_cents' => 109,
        'stock_on_hand' => '1.000',
        'tax_rate_id' => $this->vat9,
        ...$overrides,
    ]);

    $this->withToken($this->token)
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson(route('api.v1.checkouts.store'), catalogCheckoutPayload($product, $expectedPrice, $expectedVat))
        ->assertConflict()
        ->assertJsonPath('error.code', $errorCode);

    $this->assertDatabaseEmpty('orders');
    $this->assertDatabaseEmpty('payments');
    $this->assertDatabaseEmpty('inventory_transactions');
    Queue::assertNothingPushed();
    Http::assertNothingSent();
})->with([
    'price drift' => [[], 108, 900, 'product_changed'],
    'VAT drift' => [[], 109, 2100, 'product_changed'],
    'inactive' => [['active' => false], 109, 900, 'product_unavailable'],
    'insufficient stock' => [['stock_on_hand' => '0.000'], 109, 900, 'insufficient_stock'],
    'mapped fractional stock' => [['stock_on_hand' => '1.250', 'woocommerce_product_id' => 123], 109, 900, 'fractional_woocommerce_stock'],
]);

it('rejects missing products, unavailable VAT, and insufficient tender without effects', function () {
    $missingProduct = new Product;
    $missingProduct->id = 999999;
    $missingPayload = catalogCheckoutPayload($missingProduct, 109, 900);
    $this->withToken($this->token)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson(route('api.v1.checkouts.store'), $missingPayload)
        ->assertConflict()->assertJsonPath('error.code', 'product_unavailable');

    $this->vat21->update(['active' => false]);
    $manualPayload = manualCheckoutPayload(121, 2100, 200);
    $this->withToken($this->token)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson(route('api.v1.checkouts.store'), $manualPayload)
        ->assertConflict()->assertJsonPath('error.code', 'tax_rate_unavailable');

    $this->vat21->update(['active' => true]);
    $manualPayload['tendered_amount_cents'] = 120;
    $this->withToken($this->token)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson(route('api.v1.checkouts.store'), $manualPayload)
        ->assertConflict()->assertJsonPath('error.code', 'insufficient_tender');

    $this->assertDatabaseEmpty('orders');
    $this->assertDatabaseEmpty('inventory_transactions');
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('rolls back a multi-product checkout when any product cannot be sold', function () {
    $first = Product::factory()->create([
        'selling_price_including_tax_cents' => 109,
        'stock_on_hand' => '2.000',
        'tax_rate_id' => $this->vat9,
    ]);
    $second = Product::factory()->create([
        'selling_price_including_tax_cents' => 121,
        'stock_on_hand' => '0.000',
        'tax_rate_id' => $this->vat21,
    ]);
    $payload = catalogCheckoutPayload($first, 109, 900);
    $payload['items'][] = catalogCheckoutPayload($second, 121, 2100)['items'][0];

    $this->withToken($this->token)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson(route('api.v1.checkouts.store'), $payload)
        ->assertConflict()->assertJsonPath('error.code', 'insufficient_stock');

    expect($first->refresh()->stock_on_hand)->toBe('2.000');
    expect($second->refresh()->stock_on_hand)->toBe('0.000');
    $this->assertDatabaseEmpty('orders');
    $this->assertDatabaseEmpty('inventory_transactions');
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('validates the checkout trust boundary before creating effects', function (array $payload, ?string $key = null) {
    $request = $this->withToken($this->token);

    if ($key !== null) {
        $request = $request->withHeader('Idempotency-Key', $key);
    }

    $request->postJson(route('api.v1.checkouts.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed');

    $this->assertDatabaseEmpty('orders');
    $this->assertDatabaseEmpty('inventory_transactions');
    Queue::assertNothingPushed();
    Http::assertNothingSent();
})->with([
    'missing idempotency header' => [manualCheckoutPayload(), null],
    'non UUID header' => [manualCheckoutPayload(), 'not-a-uuid'],
    'fractional quantity' => [manualCheckoutPayload(quantity: 1.5), 'd76efc54-c098-451d-baae-0b71db08512e'],
    'unsupported VAT' => [manualCheckoutPayload(vat: 600), '540551d8-a77a-40dd-bb38-c1626bb0015c'],
    'unexpected item key' => [[...manualCheckoutPayload(), 'items' => [[...manualCheckoutPayload()['items'][0], 'discount' => 10]]], '7f4983b7-50cc-4125-8ce6-3be02dd5f3d7'],
    'more than 100 items' => [[...manualCheckoutPayload(), 'items' => array_fill(0, 101, manualCheckoutPayload()['items'][0])], '6a43d86f-160e-463a-a860-e860b5248f57'],
    'duplicate products' => [function (): array {
        $product = Product::factory()->create();
        $payload = catalogCheckoutPayload($product, $product->selling_price_including_tax_cents, $product->taxRate->rate_basis_points);
        $payload['items'][] = $payload['items'][0];

        return $payload;
    }, '59e38880-3022-44ff-b600-c035f3c0a8c4'],
]);

it('requires an authenticated active POS context', function () {
    $this->postJson(route('api.v1.checkouts.store'), manualCheckoutPayload(), [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertUnauthorized();
});

/** @return array<string, mixed> */
function catalogCheckoutPayload(Product $product, int $price, int $vat, int $quantity = 1): array
{
    return [
        'payment_method' => 'cash',
        'tendered_amount_cents' => $price * $quantity,
        'items' => [[
            'type' => 'product',
            'product_id' => $product->id,
            'quantity' => $quantity,
            'expected_unit_price_including_tax_cents' => $price,
            'expected_tax_rate_basis_points' => $vat,
        ]],
    ];
}

/** @return array<string, mixed> */
function manualCheckoutPayload(int $price = 109, int $vat = 900, int $tendered = 200, int|float $quantity = 1): array
{
    return [
        'payment_method' => 'cash',
        'tendered_amount_cents' => $tendered,
        'items' => [[
            'type' => 'manual',
            'description' => 'Manual item',
            'quantity' => $quantity,
            'unit_price_including_tax_cents' => $price,
            'tax_rate_basis_points' => $vat,
        ]],
    ];
}
