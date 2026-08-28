<?php

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\LocalDevelopmentSeeder;
use Database\Seeders\TaxRateSeeder;
use Illuminate\Support\Facades\Hash;

it('seeds reusable local catalog data and an administrator', function () {
    $this->seed(TaxRateSeeder::class);
    $this->seed(LocalDevelopmentSeeder::class);
    $this->seed(LocalDevelopmentSeeder::class);

    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

    expect($admin->role)->toBe(UserRole::Admin)
        ->and($admin->active)->toBeTrue()
        ->and(Hash::check('password', $admin->password))->toBeTrue();

    $this->assertDatabaseCount('brands', 3);
    $this->assertDatabaseCount('suppliers', 3);
    $this->assertDatabaseCount('products', 3);
    $this->assertDatabaseCount('inventory_transactions', 3);

    expect(Product::query()->where('article_number', 'DEMO-1001')->firstOrFail()->stock_on_hand)
        ->toBe('24.000');
});
