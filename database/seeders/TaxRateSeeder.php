<?php

namespace Database\Seeders;

use App\Models\TaxRate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TaxRateSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TaxRate::query()->upsert([
            [
                'code' => 'VAT_9',
                'label' => 'VAT 9%',
                'rate_basis_points' => 900,
                'active' => true,
            ],
            [
                'code' => 'VAT_21',
                'label' => 'VAT 21%',
                'rate_basis_points' => 2100,
                'active' => true,
            ],
        ], ['code'], ['label', 'rate_basis_points', 'active']);
    }
}
