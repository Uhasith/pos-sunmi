<?php

namespace Database\Factories;

use App\Models\WebhookReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookReceipt>
 */
class WebhookReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'woocommerce',
            'webhook_id' => fake()->numberBetween(1, 100000),
            'delivery_id' => fake()->unique()->numberBetween(1, 1000000),
            'topic' => 'order.updated',
            'external_resource_id' => fake()->numberBetween(1, 1000000),
            'payload_hash' => hash('sha256', fake()->uuid()),
            'status' => 'pending',
            'received_at' => now(),
        ];
    }
}
