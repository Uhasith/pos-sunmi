<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
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
            'position' => 1,
            'product_id' => null,
            'is_manual' => true,
            'article_number' => null,
            'description' => fake()->words(3, true),
            'quantity' => '1.000',
            'unit_price_including_tax_cents' => 109,
            'tax_rate_basis_points' => 900,
            'line_excluding_tax_cents' => 100,
            'tax_amount_cents' => 9,
            'line_including_tax_cents' => 109,
        ];
    }
}
