<?php

use App\Enums\UserRole;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('returns newest-first paginated order summaries', function () {
    $user = User::factory()->create();
    $device = Device::factory()->create();
    $older = Order::factory()->create([
        'order_number' => 'POS-000001',
        'ordered_at' => '2026-01-01 10:00:00',
        'total_including_tax_cents' => 109,
    ]);
    $newer = Order::factory()->create([
        'order_number' => 'POS-000002',
        'ordered_at' => '2026-01-02 10:00:00',
        'total_including_tax_cents' => 242,
    ]);

    $this->withToken(posToken($user, $device)->plainTextToken)
        ->getJson(route('api.v1.orders.index'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.0.order_number', 'POS-000002')
        ->assertJsonPath('data.0.totals.including_tax_cents', 242)
        ->assertJsonPath('data.1.id', $older->id)
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonMissingPath('data.0.items')
        ->assertJsonMissingPath('data.0.payment')
        ->assertJsonMissingPath('data.0.receipt');
});

it('returns 401 when listing orders without an active POS context', function () {
    $this->getJson(route('api.v1.orders.index'))
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('returns immutable receipt-ready order detail to every active staff role', function (UserRole $role) {
    config(['pos.receipt.shop.name' => 'Example Shop']);
    $cashier = User::factory()->create(['name' => 'Original cashier']);
    $device = Device::factory()->create(['name' => 'Original till']);
    $order = Order::factory()
        ->for($cashier, 'cashier')
        ->for($device)
        ->has(OrderItem::factory()->state([
            'position' => 1,
            'description' => 'Gift wrap',
            'quantity' => '2.000',
            'line_excluding_tax_cents' => 200,
            'tax_amount_cents' => 18,
            'line_including_tax_cents' => 218,
        ]), 'items')
        ->has(Payment::factory()->state([
            'amount_cents' => 218,
            'tendered_amount_cents' => 500,
            'change_due_cents' => 282,
        ]), 'payment')
        ->create([
            'order_number' => 'POS-000042',
            'device_uuid' => $device->uuid,
            'device_name' => $device->name,
            'cashier_user_id' => $cashier->id,
            'cashier_name' => $cashier->name,
            'subtotal_excluding_tax_cents' => 200,
            'tax_9_cents' => 18,
            'tax_total_cents' => 18,
            'total_including_tax_cents' => 218,
        ]);
    $viewer = User::factory()->withRole($role)->create();
    $viewerDevice = Device::factory()->create();
    $token = posToken($viewer, $viewerDevice)->plainTextToken;

    $cashier->update(['name' => 'Changed cashier']);
    $device->update(['name' => 'Changed till']);

    $this->withToken($token)
        ->getJson(route('api.v1.orders.show', $order->order_number))
        ->assertOk()
        ->assertJsonPath('data.order_number', 'POS-000042')
        ->assertJsonPath('data.cashier.name', 'Original cashier')
        ->assertJsonPath('data.device.name', 'Original till')
        ->assertJsonPath('data.items.0.description', 'Gift wrap')
        ->assertJsonPath('data.items.0.quantity', '2.000')
        ->assertJsonPath('data.totals.including_tax_cents', 218)
        ->assertJsonPath('data.payment.tendered_amount_cents', 500)
        ->assertJsonPath('data.receipt.shop.name', 'Example Shop')
        ->assertJsonMissingPath('data.idempotency_key')
        ->assertJsonMissingPath('data.payload_fingerprint');
})->with(UserRole::cases());

it('returns safe API errors for unauthenticated and unknown order requests', function () {
    $this->getJson(route('api.v1.orders.show', 'POS-999999'))
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');

    $user = User::factory()->create();
    $device = Device::factory()->create();

    $this->withToken(posToken($user, $device)->plainTextToken)
        ->getJson(route('api.v1.orders.show', 'POS-999999'))
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
});

it('returns an immutable sale receipt for reprinting without replaying the sale', function () {
    config(['pos.receipt.shop' => [
        'name' => 'Example Shop',
        'address_line_1' => 'Main Street 1',
        'address_line_2' => '',
        'postal_code' => '1000 AA',
        'city' => 'Amsterdam',
        'country_code' => 'NL',
        'vat_number' => 'NL000000000B01',
        'chamber_of_commerce_number' => '12345678',
    ]]);
    $user = User::factory()->create();
    $device = Device::factory()->create();
    $order = Order::factory()
        ->has(OrderItem::factory()->state(['description' => 'Coffee beans']), 'items')
        ->has(Payment::factory(), 'payment')
        ->create(['order_number' => 'POS-000043']);
    $token = posToken($user, $device)->plainTextToken;
    $countsBefore = [
        'orders' => DB::table('orders')->count(),
        'order_items' => DB::table('order_items')->count(),
        'payments' => DB::table('payments')->count(),
        'inventory_transactions' => DB::table('inventory_transactions')->count(),
    ];

    $this->withToken($token)
        ->getJson(route('api.v1.orders.receipt', $order->order_number))
        ->assertOk()
        ->assertJsonPath('data.order_number', 'POS-000043')
        ->assertJsonPath('data.items.0.description', 'Coffee beans')
        ->assertJsonPath('data.payment.method', 'cash')
        ->assertJsonPath('data.receipt.shop.name', 'Example Shop')
        ->assertJsonPath('data.receipt.shop.address_line_1', 'Main Street 1')
        ->assertJsonPath('data.receipt.shop.country_code', 'NL')
        ->assertJsonPath('data.receipt.shop.vat_number', 'NL000000000B01')
        ->assertJsonPath('data.receipt.shop.chamber_of_commerce_number', '12345678')
        ->assertJsonPath('meta.receipt.type', 'sale')
        ->assertJsonPath('meta.receipt.is_reprint', true);

    $this->withToken($token)
        ->getJson(route('api.v1.orders.receipt', $order->order_number))
        ->assertOk();

    expect([
        'orders' => DB::table('orders')->count(),
        'order_items' => DB::table('order_items')->count(),
        'payments' => DB::table('payments')->count(),
        'inventory_transactions' => DB::table('inventory_transactions')->count(),
    ])->toBe($countsBefore);
});
