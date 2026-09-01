<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $orderedAt = now();

        return [
            'order_number' => null,
            'source' => 'pos',
            'device_id' => Device::factory(),
            'device_uuid' => fake()->uuid(),
            'device_name' => fake()->words(2, true),
            'cashier_user_id' => User::factory(),
            'cashier_name' => fake()->name(),
            'currency' => Order::CURRENCY,
            'subtotal_excluding_tax_cents' => 100,
            'tax_9_cents' => 9,
            'tax_21_cents' => 0,
            'tax_total_cents' => 9,
            'total_including_tax_cents' => 109,
            'status' => 'paid',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'idempotency_key' => fake()->uuid(),
            'payload_fingerprint' => hash('sha256', fake()->uuid()),
            'ordered_at' => $orderedAt,
            'paid_at' => $orderedAt,
        ];
    }
}
