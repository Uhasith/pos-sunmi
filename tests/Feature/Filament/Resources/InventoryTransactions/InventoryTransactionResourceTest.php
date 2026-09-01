<?php

use App\Actions\Inventory\PostInventory;
use App\Enums\InventoryTransactionType;
use App\Enums\UserRole;
use App\Filament\Resources\InventoryTransactions\InventoryTransactionResource;
use App\Filament\Resources\InventoryTransactions\Pages\ManageInventoryTransactions;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('allows only active managers and administrators to view the inventory ledger', function (UserRole $role, bool $active, bool $allowed) {
    $user = User::factory()->withRole($role)->create(['active' => $active]);

    expect($user->can('viewAny', InventoryTransaction::class))->toBe($allowed);
})->with([
    'active manager' => [UserRole::Manager, true, true],
    'active administrator' => [UserRole::Admin, true, true],
    'cashier' => [UserRole::Cashier, true, false],
    'inactive administrator' => [UserRole::Admin, false, false],
]);

it('renders inventory transactions without mutation actions', function () {
    $administrator = User::factory()->withRole(UserRole::Admin)->create();
    $actor = User::factory()->withRole(UserRole::Manager)->create(['name' => 'Morgan Manager']);
    $product = Product::factory()->create(['title' => 'Ledger product']);
    $transaction = app(PostInventory::class)->handle(
        product: $product,
        quantityDelta: '1.250',
        type: InventoryTransactionType::ManualAdjustment,
        idempotencyKey: 'c84c70fd-a7bc-45ac-a25e-c6aa13f567ef',
        actor: $actor,
        reason: 'Cycle count',
    );
    actingAs($administrator);

    expect($administrator->can('create', InventoryTransaction::class))->toBeFalse()
        ->and($administrator->can('update', $transaction))->toBeFalse()
        ->and($administrator->can('delete', $transaction))->toBeFalse();

    $this->get(InventoryTransactionResource::getUrl())
        ->assertOk()
        ->assertSee('Inventory ledger');

    Livewire::test(ManageInventoryTransactions::class)
        ->assertCanSeeTableRecords([$transaction])
        ->assertTableColumnFormattedStateSet('quantity_delta', '+1.250', $transaction)
        ->assertTableColumnFormattedStateSet('type', 'Manual Adjustment', $transaction)
        ->assertSee([
            'Ledger product',
            'Cycle count',
            'Morgan Manager',
        ])
        ->assertActionDoesNotExist(CreateAction::class)
        ->assertTableActionDoesNotExist(EditAction::class, record: $transaction)
        ->assertTableActionDoesNotExist(DeleteAction::class, record: $transaction)
        ->assertTableBulkActionDoesNotExist(DeleteBulkAction::class);
});
