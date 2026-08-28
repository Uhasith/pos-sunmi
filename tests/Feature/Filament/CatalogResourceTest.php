<?php

use App\Enums\UserRole;
use App\Filament\Resources\Brands\BrandResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Resources\TaxRates\TaxRateResource;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('renders catalog resources for an active administrator', function (string $resource) {
    actingAs(User::factory()->withRole(UserRole::Admin)->create());

    $this->get($resource::getUrl())->assertOk();
})->with([
    BrandResource::class,
    ProductResource::class,
    SupplierResource::class,
    TaxRateResource::class,
]);
