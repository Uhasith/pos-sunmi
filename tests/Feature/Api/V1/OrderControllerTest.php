<?php

use App\Enums\UserRole;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;

it('returns immutable receipt-ready order detail to every active staff role', function (UserRole $role) {
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
