<?php

use App\Actions\Inventory\PostInventory;
use App\Enums\InventoryTransactionType;
use App\Enums\UserRole;
use App\Jobs\SyncProductStockToWooCommerce;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;

it('posts an opening balance exactly once when retried', function () {
    $product = Product::factory()->create();
    $manager = User::factory()->withRole(UserRole::Manager)->create();
    $action = new PostInventory;

    $firstTransaction = $action->handle(
        $product,
        '12.125',
        InventoryTransactionType::OpeningBalance,
        'opening-balance-1',
        $manager,
    );
    $retriedTransaction = $action->handle(
        $product,
        '12.125',
        InventoryTransactionType::OpeningBalance,
        'opening-balance-1',
        $manager,
    );

    expect($retriedTransaction->is($firstTransaction))->toBeTrue();
    expect($product->refresh()->stock_on_hand)->toBe('12.125');
    $this->assertDatabaseCount('inventory_transactions', 1);
    $this->assertDatabaseHas('inventory_transactions', [
        'product_id' => $product->id,
        'quantity_delta' => 12.125,
        'stock_before' => 0,
        'stock_after' => 12.125,
        'idempotency_key' => 'opening-balance-1',
    ]);
});

it('rejects an idempotency key reused with different data', function () {
    $product = Product::factory()->create();
    $action = new PostInventory;
    $action->handle($product, '1.000', InventoryTransactionType::OpeningBalance, 'opening-balance-1');

    expect(fn () => $action->handle(
        $product,
        '2.000',
        InventoryTransactionType::OpeningBalance,
        'opening-balance-1',
    ))->toThrow(DomainException::class, 'Inventory idempotency key was already used with different data.');

    expect($product->refresh()->stock_on_hand)->toBe('1.000');
    $this->assertDatabaseCount('inventory_transactions', 1);
});

it('rejects a second opening balance with a new idempotency key', function () {
    $product = Product::factory()->create();
    $action = new PostInventory;
    $action->handle($product, '1.000', InventoryTransactionType::OpeningBalance, 'opening-balance-1');

    expect(fn () => $action->handle(
        $product,
        '2.000',
        InventoryTransactionType::OpeningBalance,
        'opening-balance-2',
    ))->toThrow(DomainException::class, 'An opening balance has already been posted for this product.');

    expect($product->refresh()->stock_on_hand)->toBe('1.000');
    $this->assertDatabaseCount('inventory_transactions', 1);
});

it('rolls back an ordinary posting that would make stock negative', function () {
    $product = Product::factory()->create();

    expect(fn () => (new PostInventory)->handle(
        $product,
        '-0.001',
        InventoryTransactionType::OpeningBalance,
        'negative-opening-balance',
    ))->toThrow(DomainException::class, 'Inventory posting would reduce stock below zero.');

    expect($product->refresh()->stock_on_hand)->toBe('0.000');
    $this->assertDatabaseEmpty('inventory_transactions');
});

it('forbids cashiers from manually adjusting stock', function () {
    $product = Product::factory()->create();
    $cashier = User::factory()->create();

    expect(fn () => (new PostInventory)->handle(
        $product,
        '1.000',
        InventoryTransactionType::ManualAdjustment,
        'manual-adjustment-1',
        $cashier,
        'Stock count correction',
    ))->toThrow(AuthorizationException::class);

    expect($product->refresh()->stock_on_hand)->toBe('0.000');
    $this->assertDatabaseEmpty('inventory_transactions');
});

it('forbids inactive managers from manually adjusting stock', function () {
    $product = Product::factory()->create();
    $manager = User::factory()->withRole(UserRole::Manager)->inactive()->create();

    expect(fn () => (new PostInventory)->handle(
        $product,
        '1.000',
        InventoryTransactionType::ManualAdjustment,
        'manual-adjustment-1',
        $manager,
        'Stock count correction',
    ))->toThrow(AuthorizationException::class);

    expect($product->refresh()->stock_on_hand)->toBe('0.000');
    $this->assertDatabaseEmpty('inventory_transactions');
});

it('allows authorized roles to make a reasoned adjustment', function (UserRole $role) {
    $product = Product::factory()->create();
    $actor = User::factory()->withRole($role)->create();

    (new PostInventory)->handle(
        $product,
        '-1.250',
        InventoryTransactionType::ManualAdjustment,
        'manual-adjustment-'.$role->value,
        $actor,
        'Damaged stock discovered',
    );

    expect($product->refresh()->stock_on_hand)->toBe('-1.250');
    $this->assertDatabaseHas('inventory_transactions', [
        'product_id' => $product->id,
        'quantity_delta' => -1.250,
        'stock_before' => 0,
        'stock_after' => -1.250,
        'actor_user_id' => $actor->id,
        'reason' => 'Damaged stock discovered',
    ]);
})->with([
    'manager' => UserRole::Manager,
    'admin' => UserRole::Admin,
]);

it('requires a reason for a manual adjustment', function () {
    $product = Product::factory()->create();
    $manager = User::factory()->withRole(UserRole::Manager)->create();

    expect(fn () => (new PostInventory)->handle(
        $product,
        '1.000',
        InventoryTransactionType::ManualAdjustment,
        'manual-adjustment-1',
        $manager,
    ))->toThrow(DomainException::class, 'Manual stock adjustments require a reason.');

    expect($product->refresh()->stock_on_hand)->toBe('0.000');
    $this->assertDatabaseEmpty('inventory_transactions');
});

it('dispatches one mapped stock projection after commit for an idempotent posting', function () {
    Queue::fake([SyncProductStockToWooCommerce::class]);
    $product = Product::factory()->create(['woocommerce_product_id' => 123]);
    $action = app(PostInventory::class);

    $action->handle($product, '1.000', InventoryTransactionType::OpeningBalance, 'mapped-opening');
    $action->handle($product, '1.000', InventoryTransactionType::OpeningBalance, 'mapped-opening');

    Queue::assertPushedTimes(SyncProductStockToWooCommerce::class, 1);
    Queue::assertPushed(
        SyncProductStockToWooCommerce::class,
        fn (SyncProductStockToWooCommerce $job): bool => $job->productId === $product->id
            && $job->afterCommit === true,
    );
});
