<?php

use App\Enums\UserRole;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('shows the read-only Orders sidebar resource to managers and administrators', function (UserRole $role) {
    $user = User::factory()->withRole($role)->create();
    actingAs($user);

    $this->get(OrderResource::getUrl())
        ->assertOk()
        ->assertSee('Orders');
})->with([UserRole::Manager, UserRole::Admin]);

it('lists immutable orders and opens their line and payment snapshots', function () {
    $administrator = User::factory()->withRole(UserRole::Admin)->create();
    $order = Order::factory()
        ->has(OrderItem::factory()->state([
            'description' => 'Coffee beans',
            'article_number' => 'COFFEE-1',
        ]), 'items')
        ->has(Payment::factory()->state([
            'tendered_amount_cents' => 200,
            'change_due_cents' => 91,
        ]), 'payment')
        ->create([
            'order_number' => 'POS-000042',
            'cashier_name' => 'Morgan Manager',
            'device_name' => 'Front till',
        ]);
    actingAs($administrator);

    expect($administrator->can('create', Order::class))->toBeFalse()
        ->and($administrator->can('update', $order))->toBeFalse()
        ->and($administrator->can('delete', $order))->toBeFalse();

    Livewire::test(ManageOrders::class)
        ->assertCanSeeTableRecords([$order])
        ->assertSee(['POS-000042', 'Morgan Manager', 'Front till'])
        ->assertTableActionExists(ViewAction::class, record: $order)
        ->assertActionDoesNotExist(CreateAction::class)
        ->assertTableActionDoesNotExist(EditAction::class, record: $order)
        ->assertTableActionDoesNotExist(DeleteAction::class, record: $order)
        ->assertTableBulkActionDoesNotExist(DeleteBulkAction::class);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertSee(['Coffee beans', 'COFFEE-1', 'Tendered', 'Change'])
        ->assertActionDoesNotExist(EditAction::class)
        ->assertActionDoesNotExist(DeleteAction::class);
});

it('keeps cashiers outside the admin panel', function () {
    actingAs(User::factory()->withRole(UserRole::Cashier)->create());

    $this->get(OrderResource::getUrl())->assertForbidden();
});
