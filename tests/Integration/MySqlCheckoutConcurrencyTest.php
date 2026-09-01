<?php

use App\Actions\Checkout\CompleteCashCheckout;
use App\Exceptions\CheckoutConflict;
use App\Models\Device;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql' || ! str_ends_with((string) config('database.connections.mysql.database'), '_test')) {
        $this->markTestSkipped('Requires a disposable MySQL database whose name ends in _test.');
    }

    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('The pcntl extension is required.');
    }

    $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
});

it('allows only one of two devices to sell the final unit', function () {
    $vat = TaxRate::factory()->create(['rate_basis_points' => 900]);
    $product = Product::factory()->create([
        'selling_price_including_tax_cents' => 109,
        'stock_on_hand' => '1.000',
        'tax_rate_id' => $vat,
    ]);
    $cashier = User::factory()->create();
    $devices = Device::factory()->count(2)->create();
    $barrier = tempnam(sys_get_temp_dir(), 'checkout-barrier-');
    unlink($barrier);
    $resultPaths = [
        tempnam(sys_get_temp_dir(), 'checkout-result-'),
        tempnam(sys_get_temp_dir(), 'checkout-result-'),
    ];
    DB::disconnect();
    $childProcessIds = [];

    foreach ($devices as $index => $device) {
        $processId = pcntl_fork();

        if ($processId === 0) {
            while (! file_exists($barrier)) {
                usleep(1000);
            }

            DB::purge();

            try {
                app(CompleteCashCheckout::class)->handle([
                    'payment_method' => 'cash',
                    'tendered_amount_cents' => 109,
                    'idempotency_key' => '2b4d1d50-d05c-49cd-9d69-b78f3cc2500'.$index,
                    'items' => [[
                        'type' => 'product',
                        'product_id' => $product->id,
                        'quantity' => 1,
                        'expected_unit_price_including_tax_cents' => 109,
                        'expected_tax_rate_basis_points' => 900,
                    ]],
                ], User::query()->findOrFail($cashier->id), Device::query()->findOrFail($device->id));
                file_put_contents($resultPaths[$index], 'created');
            } catch (CheckoutConflict $exception) {
                file_put_contents($resultPaths[$index], $exception->errorCode);
            } catch (Throwable $exception) {
                file_put_contents($resultPaths[$index], 'error:'.$exception::class.':'.$exception->getMessage());
            }

            DB::disconnect();
            exit(0);
        }

        expect($processId)->toBeGreaterThan(0);
        $childProcessIds[] = $processId;
    }

    touch($barrier);

    foreach ($childProcessIds as $processId) {
        pcntl_waitpid($processId, $status);
        expect(pcntl_wexitstatus($status))->toBe(0);
    }

    $results = array_map(fn (string $path): string => (string) file_get_contents($path), $resultPaths);
    unlink($barrier);
    array_map('unlink', $resultPaths);
    sort($results);
    DB::purge();

    expect($results)->toBe(['created', 'insufficient_stock']);
    expect(Product::query()->findOrFail($product->id)->stock_on_hand)->toBe('0.000');
    $this->assertDatabaseCount('orders', 1);
    $this->assertDatabaseCount('order_items', 1);
    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('inventory_transactions', 1);
});
