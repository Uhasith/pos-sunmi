<?php

use Database\Seeders\TaxRateSeeder;

it('seeds only the enabled V1 VAT rates idempotently', function () {
    $this->seed(TaxRateSeeder::class);
    $this->seed(TaxRateSeeder::class);

    $this->assertDatabaseCount('tax_rates', 2);
    $this->assertDatabaseHas('tax_rates', [
        'code' => 'VAT_9',
        'label' => 'VAT 9%',
        'rate_basis_points' => 900,
        'active' => true,
    ]);
    $this->assertDatabaseHas('tax_rates', [
        'code' => 'VAT_21',
        'label' => 'VAT 21%',
        'rate_basis_points' => 2100,
        'active' => true,
    ]);
});
