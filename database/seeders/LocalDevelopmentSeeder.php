<?php

namespace Database\Seeders;

use App\Actions\Inventory\PostInventory;
use App\Enums\InventoryTransactionType;
use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LocalDevelopmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(PostInventory $postInventory): void
    {
        $admin = User::query()->firstOrNew(['email' => 'admin@example.com']);
        $admin->name = 'Local Admin';
        $admin->role = UserRole::Admin;
        $admin->active = true;

        if (! $admin->exists) {
            $admin->password = Hash::make('password');
        }

        $admin->save();

        foreach (['Northstar Drinks', 'Sunrise Bakery', 'Harbor Foods'] as $name) {
            Brand::query()->updateOrCreate(['name' => $name], ['active' => true]);
        }

        foreach (['Central Wholesale', 'Fresh Route Supply', 'Metro Distribution'] as $name) {
            Supplier::query()->updateOrCreate(['name' => $name], ['active' => true]);
        }

        $products = [
            [
                'title' => 'Sparkling Water 500 ml',
                'article_number' => 'DEMO-1001',
                'barcode' => '8710000001001',
                'selling_price_including_tax_cents' => 249,
                'purchase_price_excluding_tax_cents' => 90,
                'minimum_stock' => '8.000',
                'brand' => 'Northstar Drinks',
                'supplier' => 'Central Wholesale',
                'tax_rate' => 'VAT_9',
                'opening_stock' => '24.000',
            ],
            [
                'title' => 'Sourdough Loaf',
                'article_number' => 'DEMO-1002',
                'barcode' => '8710000001002',
                'selling_price_including_tax_cents' => 425,
                'purchase_price_excluding_tax_cents' => 175,
                'minimum_stock' => '5.000',
                'brand' => 'Sunrise Bakery',
                'supplier' => 'Fresh Route Supply',
                'tax_rate' => 'VAT_9',
                'opening_stock' => '12.000',
            ],
            [
                'title' => 'Insulated Travel Mug',
                'article_number' => 'DEMO-1003',
                'barcode' => '8710000001003',
                'selling_price_including_tax_cents' => 1299,
                'purchase_price_excluding_tax_cents' => 600,
                'minimum_stock' => '3.000',
                'brand' => 'Harbor Foods',
                'supplier' => 'Metro Distribution',
                'tax_rate' => 'VAT_21',
                'opening_stock' => '6.000',
            ],
        ];

        foreach ($products as $data) {
            $product = Product::query()->updateOrCreate(
                ['article_number' => $data['article_number']],
                [
                    'title' => $data['title'],
                    'barcode' => $data['barcode'],
                    'selling_price_including_tax_cents' => $data['selling_price_including_tax_cents'],
                    'purchase_price_excluding_tax_cents' => $data['purchase_price_excluding_tax_cents'],
                    'minimum_stock' => $data['minimum_stock'],
                    'brand_id' => Brand::query()->where('name', $data['brand'])->firstOrFail()->id,
                    'supplier_id' => Supplier::query()->where('name', $data['supplier'])->firstOrFail()->id,
                    'tax_rate_id' => TaxRate::query()->where('code', $data['tax_rate'])->firstOrFail()->id,
                    'active' => true,
                ],
            );

            $postInventory->handle(
                product: $product,
                quantityDelta: $data['opening_stock'],
                type: InventoryTransactionType::OpeningBalance,
                idempotencyKey: 'local-demo:opening-balance:'.$data['article_number'],
                actor: $admin,
                reason: 'Local demo opening balance',
            );
        }
    }
}
