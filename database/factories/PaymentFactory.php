<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'method' => 'cash',
            'amount_cents' => 109,
            'tendered_amount_cents' => 200,
            'change_due_cents' => 91,
            'currency' => Order::CURRENCY,
            'status' => 'paid',
            'paid_at' => now(),
        ];
    }
}
