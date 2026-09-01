<?php

namespace App\Actions\Inventory;

use App\Enums\InventoryTransactionType;
use App\Enums\UserRole;
use App\Exceptions\InventoryIdempotencyConflict;
use App\Jobs\SyncProductStockToWooCommerce;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PostInventory
{
    public function handle(
        Product $product,
        string $quantityDelta,
        InventoryTransactionType $type,
        string $idempotencyKey,
        ?User $actor = null,
        ?string $reason = null,
    ): InventoryTransaction {
        $delta = $this->quantityToInteger($quantityDelta);
        $normalizedDelta = $this->integerToQuantity($delta);
        $idempotencyKey = trim($idempotencyKey);
        $reason = $reason === null ? null : trim($reason);

        if ($delta === 0) {
            throw new DomainException('Inventory quantity delta cannot be zero.');
        }

        if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 255) {
            throw new InvalidArgumentException('Inventory idempotency key must contain 1 to 255 characters.');
        }

        if ($type === InventoryTransactionType::ManualAdjustment) {
            $this->authorizeManualAdjustment($actor, $reason);
        }

        $transaction = DB::transaction(function () use ($product, $normalizedDelta, $delta, $type, $idempotencyKey, $actor, $reason): InventoryTransaction {
            $lockedProduct = Product::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();
            $existingTransaction = InventoryTransaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingTransaction !== null) {
                $this->ensureEquivalentRetry(
                    $existingTransaction,
                    $lockedProduct,
                    $normalizedDelta,
                    $type,
                    $actor,
                    $reason,
                );

                return $existingTransaction;
            }

            if (
                $type === InventoryTransactionType::OpeningBalance
                && $lockedProduct->inventoryTransactions()
                    ->where('type', InventoryTransactionType::OpeningBalance->value)
                    ->exists()
            ) {
                throw new DomainException('An opening balance has already been posted for this product.');
            }

            $stockBefore = $this->quantityToInteger($lockedProduct->stock_on_hand);
            $stockAfter = $stockBefore + $delta;

            if ($type !== InventoryTransactionType::ManualAdjustment && $stockAfter < 0) {
                throw new DomainException('Inventory posting would reduce stock below zero.');
            }

            $stockAfterQuantity = $this->integerToQuantity($stockAfter);
            $transaction = InventoryTransaction::query()->create([
                'product_id' => $lockedProduct->id,
                'quantity_delta' => $normalizedDelta,
                'type' => $type,
                'stock_before' => $lockedProduct->stock_on_hand,
                'stock_after' => $stockAfterQuantity,
                'reason' => $reason,
                'actor_user_id' => $actor?->id,
                'idempotency_key' => $idempotencyKey,
            ]);

            $lockedProduct->stock_on_hand = $stockAfterQuantity;
            $lockedProduct->save();

            return $transaction;
        }, 3);

        if ($transaction->wasRecentlyCreated && $product->woocommerce_product_id !== null) {
            SyncProductStockToWooCommerce::dispatch($product->id)->afterCommit();
        }

        return $transaction;
    }

    private function authorizeManualAdjustment(?User $actor, ?string $reason): void
    {
        if (
            $actor === null
            || ! $actor->active
            || ! in_array($actor->role, [UserRole::Manager, UserRole::Admin], true)
        ) {
            throw new AuthorizationException('Only managers and administrators may adjust stock.');
        }

        if ($reason === null || $reason === '') {
            throw new DomainException('Manual stock adjustments require a reason.');
        }
    }

    private function ensureEquivalentRetry(
        InventoryTransaction $transaction,
        Product $product,
        string $quantityDelta,
        InventoryTransactionType $type,
        ?User $actor,
        ?string $reason,
    ): void {
        if (
            $transaction->product_id !== $product->id
            || $transaction->quantity_delta !== $quantityDelta
            || $transaction->type !== $type
            || $transaction->actor_user_id !== $actor?->id
            || $transaction->reason !== $reason
        ) {
            throw new InventoryIdempotencyConflict('Inventory idempotency key was already used with different data.');
        }
    }

    private function quantityToInteger(string $quantity): int
    {
        if (! preg_match('/\A(?<sign>-?)(?<whole>\d{1,12})(?:\.(?<fraction>\d{1,3}))?\z/', $quantity, $parts)) {
            throw new InvalidArgumentException('Inventory quantities must have at most three decimal places.');
        }

        $value = ((int) $parts['whole'] * 1000)
            + (int) str_pad($parts['fraction'] ?? '', 3, '0');

        return $parts['sign'] === '-' ? -$value : $value;
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
