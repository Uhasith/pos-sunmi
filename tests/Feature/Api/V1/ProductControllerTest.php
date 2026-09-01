<?php

use App\Models\Device;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\User;

it('returns 401 when no POS token is provided', function () {
    $this->getJson(route('api.v1.products.index'))
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('lists only active products without exposing back-office purchase data', function () {
    $user = User::factory()->create();
    $device = Device::factory()->create();
    $token = posToken($user, $device);
    $activeProduct = Product::factory()->create([
        'title' => 'Active product',
        'article_number' => 'ACTIVE-1',
        'selling_price_including_tax_cents' => 1299,
        'purchase_price_excluding_tax_cents' => 600,
        'stock_on_hand' => '3.250',
    ]);
    Product::factory()->create(['active' => false]);

    $this->withToken($token->plainTextToken)
        ->getJson(route('api.v1.products.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $activeProduct->id)
        ->assertJsonPath('data.0.selling_price_including_tax_cents', 1299)
        ->assertJsonPath('data.0.stock_on_hand', '3.250')
        ->assertJsonMissingPath('data.0.purchase_price_excluding_tax_cents');
});

it('filters active products by the search query', function () {
    $user = User::factory()->create();
    $device = Device::factory()->create();
    $token = posToken($user, $device);
    $matchingProduct = Product::factory()->create(['article_number' => 'NEEDLE-100']);
    Product::factory()->create(['article_number' => 'OTHER-200']);

    $this->withToken($token->plainTextToken)
        ->getJson(route('api.v1.products.index', ['search' => 'NEEDLE']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingProduct->id);
});

it('finds an active product by its exact leading-zero barcode', function () {
    $user = User::factory()->create();
    $device = Device::factory()->create();
    $token = posToken($user, $device);
    $product = Product::factory()->create(['barcode' => '0012345678905']);

    $this->withToken($token->plainTextToken)
        ->getJson(route('api.v1.products.by-barcode', ['barcode' => '0012345678905']))
        ->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.barcode', '0012345678905');
});

it('returns 404 for an inactive barcode', function () {
    $user = User::factory()->create();
    $device = Device::factory()->create();
    $token = posToken($user, $device);
    $product = Product::factory()->create([
        'barcode' => '0012345678905',
        'active' => false,
    ]);

    $this->withToken($token->plainTextToken)
        ->getJson(route('api.v1.products.by-barcode', ['barcode' => $product->barcode]))
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
});

it('returns product detail with its VAT snapshot inputs', function () {
    $user = User::factory()->create();
    $device = Device::factory()->create();
    $token = posToken($user, $device);
    $taxRate = TaxRate::factory()->create([
        'code' => 'VAT_9',
        'label' => 'VAT 9%',
        'rate_basis_points' => 900,
    ]);
    $product = Product::factory()->for($taxRate)->create();

    $this->withToken($token->plainTextToken)
        ->getJson(route('api.v1.products.show', $product))
        ->assertOk()
        ->assertJsonPath('data.tax_rate.code', 'VAT_9')
        ->assertJsonPath('data.tax_rate.rate_basis_points', 900);
});
