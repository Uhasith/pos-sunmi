<?php

namespace Database\Factories;

use App\Models\TaxRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxRate>
 */
class TaxRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rateBasisPoints = fake()->randomElement([900, 2100]);

        return [
            'code' => 'VAT_'.fake()->unique()->numberBetween(0, 10_000),
            'label' => 'VAT '.($rateBasisPoints / 100).'%',
            'rate_basis_points' => $rateBasisPoints,
            'active' => true,
        ];
    }
}
