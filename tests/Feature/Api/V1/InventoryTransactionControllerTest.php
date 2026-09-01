<?php

use App\Actions\Inventory\PostInventory;
use App\Enums\InventoryTransactionType;
use App\Enums\UserRole;
use App\Models\Device;
use App\Models\Product;
use App\Models\User;

it('returns 401 when no POS token is provided', function () {
    $product = Product::factory()->create();

    $this->getJson(route('api.v1.products.inventory-transactions.index', $product))
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('returns 403 when a cashier reads or adjusts inventory', function () {
    $cashier = User::factory()->create();
    $token = posToken($cashier, Device::factory()->create());
    $product = Product::factory()->create();

    $this->withToken($token->plainTextToken)
        ->getJson(route('api.v1.products.inventory-transactions.index', $product))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden');

    $this->withToken($token->plainTextToken)
        ->withHeader('Idempotency-Key', 'cashier-adjustment')
        ->postJson(route('api.v1.products.inventory-transactions.store', $product), [
            'quantity_delta' => '1.000',
            'reason' => 'Cycle count',
        ])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden');

    expect($product->refresh()->stock_on_hand)->toBe('0.000');
    $this->assertDatabaseEmpty('inventory_transactions');
});

it('lists a product ledger newest first for a manager', function () {
    $manager = User::factory()->withRole(UserRole::Manager)->create();
    $token = posToken($manager, Device::factory()->create());
    $product = Product::factory()->create();
    $postInventory = app(PostInventory::class);
    $postInventory->handle(
        $product,
        '2.000',
        InventoryTransactionType::ManualAdjustment,
        'adjustment-one',
        $manager,
        'First count',
    );
    $latest = $postInventory->handle(
        $product,
        '-0.500',
        InventoryTransactionType::ManualAdjustment,
        'adjustment-two',
        $manager,
        'Second count',
    );

    $this->withToken($token->plainTextToken)
        ->getJson(route('api.v1.products.inventory-transactions.index', $product))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $latest->id)
        ->assertJsonPath('data.0.quantity_delta', '-0.500')
        ->assertJsonPath('data.0.stock_after', '1.500')
        ->assertJsonPath('data.0.actor.id', $manager->id)
        ->assertJsonPath('data.0.actor.name', $manager->name);
});

it('posts an idempotent manager adjustment through the inventory ledger', function () {
    $manager = User::factory()->withRole(UserRole::Manager)->create();
    $token = posToken($manager, Device::factory()->create());
    $product = Product::factory()->create();
    $route = route('api.v1.products.inventory-transactions.store', $product);
    $payload = [
        'quantity_delta' => '1.250',
        'reason' => 'Cycle count',
        'idempotency_key' => 'ignored-body-key',
    ];

    $firstResponse = $this->withToken($token->plainTextToken)
        ->withHeader('Idempotency-Key', 'api-adjustment-001')
        ->postJson($route, $payload)
        ->assertCreated()
        ->assertJsonPath('data.quantity_delta', '1.250')
        ->assertJsonPath('data.stock_before', '0.000')
        ->assertJsonPath('data.stock_after', '1.250')
        ->assertJsonPath('data.type', 'manual_adjustment')
        ->assertJsonPath('data.reason', 'Cycle count');

    $this->withToken($token->plainTextToken)
        ->withHeader('Idempotency-Key', 'api-adjustment-001')
        ->postJson($route, $payload)
        ->assertOk()
        ->assertJsonPath('data.id', $firstResponse->json('data.id'));

    expect($product->refresh()->stock_on_hand)->toBe('1.250');
    $this->assertDatabaseCount('inventory_transactions', 1);
    $this->assertDatabaseHas('inventory_transactions', [
        'product_id' => $product->id,
        'actor_user_id' => $manager->id,
        'idempotency_key' => 'api-adjustment-001',
    ]);
});

it('returns 409 when an adjustment key is reused with different data', function () {
    $manager = User::factory()->withRole(UserRole::Manager)->create();
    $token = posToken($manager, Device::factory()->create());
    $product = Product::factory()->create();
    $route = route('api.v1.products.inventory-transactions.store', $product);

    $this->withToken($token->plainTextToken)
        ->withHeader('Idempotency-Key', 'api-adjustment-conflict')
        ->postJson($route, [
            'quantity_delta' => '1.000',
            'reason' => 'Cycle count',
        ])
        ->assertCreated();

    $this->withToken($token->plainTextToken)
        ->withHeader('Idempotency-Key', 'api-adjustment-conflict')
        ->postJson($route, [
            'quantity_delta' => '2.000',
            'reason' => 'Cycle count',
        ])
        ->assertConflict()
        ->assertJsonPath('error.code', 'conflict');

    expect($product->refresh()->stock_on_hand)->toBe('1.000');
    $this->assertDatabaseCount('inventory_transactions', 1);
});

it('returns 422 for a zero adjustment or missing idempotency header', function () {
    $manager = User::factory()->withRole(UserRole::Manager)->create();
    $token = posToken($manager, Device::factory()->create());
    $product = Product::factory()->create();
    $route = route('api.v1.products.inventory-transactions.store', $product);

    $this->withToken($token->plainTextToken)
        ->withHeader('Idempotency-Key', 'zero-adjustment')
        ->postJson($route, [
            'quantity_delta' => '0.000',
            'reason' => 'Cycle count',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonPath('error.details.quantity_delta.0', 'The quantity delta field format is invalid.');

    $this->withoutHeader('Idempotency-Key')
        ->withToken($token->plainTextToken)
        ->postJson($route, [
            'quantity_delta' => '1.000',
            'reason' => 'Cycle count',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonPath('error.details.idempotency_key.0', 'The idempotency key field is required.');

    expect($product->refresh()->stock_on_hand)->toBe('0.000');
    $this->assertDatabaseEmpty('inventory_transactions');
});
