<?php

use App\Enums\UserRole;
use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Models\Brand;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('creates a product without accepting a direct stock balance', function () {
    actingAs(User::factory()->withRole(UserRole::Admin)->create());
    $brand = Brand::factory()->create();
    $supplier = Supplier::factory()->create();
    $taxRate = TaxRate::factory()->create([
        'rate_basis_points' => 900,
        'active' => true,
    ]);

    Livewire::test(ManageProducts::class)
        ->callAction(CreateAction::class, data: [
            'title' => 'Test product',
            'article_number' => 'TEST-1001',
            'barcode' => '8710000099001',
            'selling_price_including_tax_cents' => 1299,
            'purchase_price_excluding_tax_cents' => 600,
            'stock_on_hand' => '99.000',
            'minimum_stock' => '2.500',
            'brand_id' => $brand->id,
            'supplier_id' => $supplier->id,
            'tax_rate_id' => $taxRate->id,
            'woocommerce_product_id' => 123,
            'woocommerce_variation_id' => 456,
            'active' => true,
        ])
        ->assertHasNoFormErrors();

    $product = Product::query()->where('article_number', 'TEST-1001')->firstOrFail();

    expect($product->stock_on_hand)->toBe('0.000');
    expect($product->woocommerce_product_id)->toBe(123)
        ->and($product->woocommerce_variation_id)->toBe(456);
    $this->assertDatabaseCount('inventory_transactions', 0);
});

it('rejects a WooCommerce variation without its parent product', function () {
    actingAs(User::factory()->withRole(UserRole::Admin)->create());
    $taxRate = TaxRate::factory()->create([
        'rate_basis_points' => 900,
        'active' => true,
    ]);

    Livewire::test(ManageProducts::class)
        ->callAction(CreateAction::class, data: [
            'title' => 'Unmapped variation',
            'article_number' => 'TEST-WOO-1',
            'selling_price_including_tax_cents' => 1299,
            'purchase_price_excluding_tax_cents' => 600,
            'minimum_stock' => '0.000',
            'tax_rate_id' => $taxRate->id,
            'woocommerce_variation_id' => 456,
            'active' => true,
        ])
        ->assertHasFormErrors(['woocommerce_variation_id']);

    expect(Product::query()->where('article_number', 'TEST-WOO-1')->exists())->toBeFalse();
});

it('rejects duplicate simple WooCommerce product mappings', function () {
    actingAs(User::factory()->withRole(UserRole::Admin)->create());
    Product::factory()->create(['woocommerce_product_id' => 123]);
    $taxRate = TaxRate::factory()->create([
        'rate_basis_points' => 900,
        'active' => true,
    ]);

    Livewire::test(ManageProducts::class)
        ->callAction(CreateAction::class, data: [
            'title' => 'Duplicate mapping',
            'article_number' => 'TEST-WOO-2',
            'selling_price_including_tax_cents' => 1299,
            'purchase_price_excluding_tax_cents' => 600,
            'minimum_stock' => '0.000',
            'tax_rate_id' => $taxRate->id,
            'woocommerce_product_id' => 123,
            'active' => true,
        ])
        ->assertHasFormErrors(['woocommerce_product_id']);

    expect(Product::query()->where('article_number', 'TEST-WOO-2')->exists())->toBeFalse();
});

it('rejects duplicate WooCommerce variation mappings', function () {
    actingAs(User::factory()->withRole(UserRole::Admin)->create());
    Product::factory()->create([
        'woocommerce_product_id' => 123,
        'woocommerce_variation_id' => 456,
    ]);
    $taxRate = TaxRate::factory()->create([
        'rate_basis_points' => 900,
        'active' => true,
    ]);

    Livewire::test(ManageProducts::class)
        ->callAction(CreateAction::class, data: [
            'title' => 'Duplicate variation',
            'article_number' => 'TEST-WOO-3',
            'selling_price_including_tax_cents' => 1299,
            'purchase_price_excluding_tax_cents' => 600,
            'minimum_stock' => '0.000',
            'tax_rate_id' => $taxRate->id,
            'woocommerce_product_id' => 999,
            'woocommerce_variation_id' => 456,
            'active' => true,
        ])
        ->assertHasFormErrors(['woocommerce_variation_id']);

    expect(Product::query()->where('article_number', 'TEST-WOO-3')->exists())->toBeFalse();
});

it('rejects a VAT rate that is not active and supported in V1', function (int $rateBasisPoints, bool $active) {
    actingAs(User::factory()->withRole(UserRole::Admin)->create());
    $taxRate = TaxRate::factory()->create([
        'rate_basis_points' => $rateBasisPoints,
        'active' => $active,
    ]);

    Livewire::test(ManageProducts::class)
        ->callAction(CreateAction::class, data: [
            'title' => 'Test product',
            'article_number' => 'TEST-1002',
            'selling_price_including_tax_cents' => 1299,
            'purchase_price_excluding_tax_cents' => 600,
            'minimum_stock' => '0.000',
            'tax_rate_id' => $taxRate->id,
            'active' => true,
        ])
        ->assertHasFormErrors(['tax_rate_id']);

    expect(Product::query()->where('article_number', 'TEST-1002')->exists())->toBeFalse();
})->with([
    'inactive 9% rate' => [900, false],
    'unsupported active rate' => [700, true],
]);

it('posts an idempotent stock adjustment through the inventory ledger', function () {
    $manager = User::factory()->withRole(UserRole::Manager)->create();
    $product = Product::factory()->create();
    $action = TestAction::make('adjustStock')->table($product);
    $data = [
        'quantity_delta' => '1.250',
        'reason' => 'Cycle count',
        'idempotency_key' => '81886f54-4f96-4e65-b0c2-f6dd3cb76f73',
    ];
    actingAs($manager);

    Livewire::test(ManageProducts::class)
        ->callAction($action, data: $data)
        ->assertHasNoFormErrors()
        ->assertNotified('Stock adjusted');
    Livewire::test(ManageProducts::class)
        ->callAction($action, data: $data)
        ->assertHasNoFormErrors();

    expect($product->refresh()->stock_on_hand)->toBe('1.250');
    $this->assertDatabaseCount('inventory_transactions', 1);
    expect(InventoryTransaction::query()->sole())
        ->quantity_delta->toBe('1.250')
        ->reason->toBe('Cycle count')
        ->actor_user_id->toBe($manager->id);
});

it('rejects a zero stock adjustment before posting inventory', function () {
    actingAs(User::factory()->withRole(UserRole::Admin)->create());
    $product = Product::factory()->create();

    Livewire::test(ManageProducts::class)
        ->callAction(TestAction::make('adjustStock')->table($product), data: [
            'quantity_delta' => '0.000',
            'reason' => 'Cycle count',
            'idempotency_key' => 'ab16a9d2-b8ca-48c4-b378-3596822996e6',
        ])
        ->assertHasFormErrors(['quantity_delta']);

    expect($product->refresh()->stock_on_hand)->toBe('0.000');
    $this->assertDatabaseCount('inventory_transactions', 0);
});
