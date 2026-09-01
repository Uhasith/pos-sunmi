<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\TaxRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'article_number' => fake()->unique()->bothify('ART-#####'),
            'barcode' => fake()->unique()->ean13(),
            'selling_price_including_tax_cents' => fake()->numberBetween(100, 100_000),
            'purchase_price_excluding_tax_cents' => fake()->numberBetween(50, 50_000),
            'stock_on_hand' => '0.000',
            'minimum_stock' => '0.000',
            'brand_id' => Brand::factory(),
            'supplier_id' => Supplier::factory(),
            'tax_rate_id' => TaxRate::factory(),
            'woocommerce_product_id' => null,
            'woocommerce_variation_id' => null,
            'active' => true,
        ];
    }
}
