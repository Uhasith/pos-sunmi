<?php

namespace App\Console\Commands;

use App\Models\InventoryTransaction;
use App\Models\Product;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inventory:reconcile')]
#[Description('Report differences between inventory ledger totals and cached product stock')]
class ReconcileInventory extends Command
{
    public function handle(): int
    {
        /** @var array<int, int> $ledgerTotals */
        $ledgerTotals = [];

        foreach (InventoryTransaction::query()->select(['id', 'product_id', 'quantity_delta'])->cursor() as $transaction) {
            $ledgerTotals[$transaction->product_id] = ($ledgerTotals[$transaction->product_id] ?? 0)
                + $this->quantityToInteger($transaction->quantity_delta);
        }

        $mismatches = [];

        foreach (Product::query()->select(['id', 'title', 'stock_on_hand'])->orderBy('id')->cursor() as $product) {
            $cachedStock = $this->quantityToInteger($product->stock_on_hand);
            $ledgerTotal = $ledgerTotals[$product->id] ?? 0;

            if ($cachedStock === $ledgerTotal) {
                continue;
            }

            $mismatches[] = [
                $product->id,
                $product->title,
                $this->integerToQuantity($cachedStock),
                $this->integerToQuantity($ledgerTotal),
            ];
        }

        if ($mismatches === []) {
            $this->info('Inventory ledger balances match cached product stock.');

            return self::SUCCESS;
        }

        $this->error('Inventory ledger mismatch detected.');
        $this->table(
            ['Product ID', 'Product', 'Cached stock', 'Ledger total'],
            $mismatches,
        );

        return self::FAILURE;
    }

    private function quantityToInteger(string $quantity): int
    {
        $isNegative = str_starts_with($quantity, '-');
        $absoluteQuantity = $isNegative ? substr($quantity, 1) : $quantity;
        [$whole, $fraction] = array_pad(explode('.', $absoluteQuantity, 2), 2, '');

        $value = ((int) $whole * 1000)
            + (int) str_pad($fraction, 3, '0');

        return $isNegative ? -$value : $value;
    }

    private function integerToQuantity(int $quantity): string
    {
        $absoluteQuantity = abs($quantity);

        return ($quantity < 0 ? '-' : '')
            .intdiv($absoluteQuantity, 1000)
            .'.'
            .str_pad((string) ($absoluteQuantity % 1000), 3, '0', STR_PAD_LEFT);
    }
}
