<?php

use App\Actions\Inventory\PostInventory;
use App\Enums\InventoryTransactionType;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

it('succeeds when cached stock matches the inventory ledger', function () {
    $product = Product::factory()->create();
    (new PostInventory)->handle(
        $product,
        '2.500',
        InventoryTransactionType::OpeningBalance,
        'reconcile-matching-stock',
    );

    $this->artisan('inventory:reconcile')
        ->expectsOutput('Inventory ledger balances match cached product stock.')
        ->assertSuccessful();
});

it('fails and reports products whose cached stock differs from the ledger', function () {
    $product = Product::factory()->create(['title' => 'Mismatched product']);
    (new PostInventory)->handle(
        $product,
        '2.500',
        InventoryTransactionType::OpeningBalance,
        'reconcile-mismatched-stock',
    );
    DB::table('products')->where('id', $product->id)->update(['stock_on_hand' => '1.000']);

    $this->artisan('inventory:reconcile')
        ->expectsOutput('Inventory ledger mismatch detected.')
        ->expectsTable(
            ['Product ID', 'Product', 'Cached stock', 'Ledger total'],
            [[$product->id, 'Mismatched product', '1.000', '2.500']],
        )
        ->assertFailed();
});
