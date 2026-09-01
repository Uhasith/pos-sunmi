<?php

namespace App\Actions\Checkout;

use App\Actions\Inventory\PostInventory;
use App\Enums\InventoryTransactionType;
use App\Exceptions\CheckoutConflict;
use App\Models\Device;
use App\Models\Order;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\User;
use App\Support\DecimalQuantity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use JsonException;
use OverflowException;

class CompleteCashCheckout
{
    public function __construct(
        private CalculateInclusiveLineTotals $calculateLineTotals,
        private PostInventory $postInventory,
    ) {}

    /**
     * @param  array{payment_method: string, tendered_amount_cents: int, idempotency_key: string, items: list<array<string, mixed>>}  $data
     */
    public function handle(array $data, User $cashier, Device $device): Order
    {
        $fingerprint = $this->fingerprint($data);

        return DB::transaction(function () use ($data, $cashier, $device, $fingerprint): Order {
            $lockedDevice = Device::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();
            $existingOrder = Order::query()
                ->where('device_id', $lockedDevice->id)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existingOrder !== null) {
                if (! hash_equals($existingOrder->payload_fingerprint, $fingerprint)) {
                    throw new CheckoutConflict(
                        'idempotency_conflict',
                        'The idempotency key was already used with different checkout data.',
                    );
                }

                return $existingOrder->load(['items', 'payment']);
            }

            if (! $lockedDevice->active) {
                throw new CheckoutConflict('device_unavailable', 'The POS device is no longer active.');
            }

            $productIds = collect($data['items'])
                ->where('type', 'product')
                ->pluck('product_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->sort()
                ->values();
            /** @var Collection<int, Product> $products */
            $products = Product::query()
                ->with('taxRate')
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $productIds->count()) {
                throw new CheckoutConflict('product_unavailable', 'One or more products are unavailable.');
            }

            $manualTaxRates = collect($data['items'])
                ->where('type', 'manual')
                ->pluck('tax_rate_basis_points')
                ->map(fn (mixed $rate): int => (int) $rate)
                ->unique();
            $availableManualTaxRates = TaxRate::query()
                ->where('active', true)
                ->whereIn('rate_basis_points', $manualTaxRates)
                ->pluck('rate_basis_points')
                ->map(fn (mixed $rate): int => (int) $rate)
                ->unique();

            if ($availableManualTaxRates->count() !== $manualTaxRates->count()) {
                throw new CheckoutConflict('tax_rate_unavailable', 'A selected VAT rate is unavailable.');
            }

            $preparedItems = [];
            $subtotalExcludingTaxCents = 0;
            $taxByRate = [900 => 0, 2100 => 0];
            $totalIncludingTaxCents = 0;

            try {
                foreach ($data['items'] as $index => $item) {
                    $product = null;

                    if ($item['type'] === 'product') {
                        $product = $products->get((int) $item['product_id']);
                        $this->validateProduct($product, $item, $index);
                        $description = $product->title;
                        $articleNumber = $product->article_number;
                        $unitPriceCents = $product->selling_price_including_tax_cents;
                        $taxRateBasisPoints = $product->taxRate->rate_basis_points;
                    } else {
                        $description = (string) $item['description'];
                        $articleNumber = null;
                        $unitPriceCents = (int) $item['unit_price_including_tax_cents'];
                        $taxRateBasisPoints = (int) $item['tax_rate_basis_points'];
                    }

                    $quantity = (int) $item['quantity'];
                    $totals = $this->calculateLineTotals->handle($unitPriceCents, $quantity, $taxRateBasisPoints);
                    $subtotalExcludingTaxCents = $this->checkedAdd($subtotalExcludingTaxCents, $totals['excluding_tax_cents']);
                    $taxByRate[$taxRateBasisPoints] = $this->checkedAdd($taxByRate[$taxRateBasisPoints], $totals['tax_cents']);
                    $totalIncludingTaxCents = $this->checkedAdd($totalIncludingTaxCents, $totals['including_tax_cents']);
                    $preparedItems[] = [
                        'position' => $index + 1,
                        'product' => $product,
                        'is_manual' => $item['type'] === 'manual',
                        'article_number' => $articleNumber,
                        'description' => $description,
                        'quantity' => $quantity.'.000',
                        'unit_price_including_tax_cents' => $unitPriceCents,
                        'tax_rate_basis_points' => $taxRateBasisPoints,
                        'line_excluding_tax_cents' => $totals['excluding_tax_cents'],
                        'tax_amount_cents' => $totals['tax_cents'],
                        'line_including_tax_cents' => $totals['including_tax_cents'],
                    ];
                }
            } catch (OverflowException) {
                throw new CheckoutConflict('amount_overflow', 'Checkout totals exceed the supported amount.');
            }

            if ($data['tendered_amount_cents'] < $totalIncludingTaxCents) {
                throw new CheckoutConflict(
                    'insufficient_tender',
                    'The tendered amount is less than the checkout total.',
                    ['required_amount_cents' => $totalIncludingTaxCents],
                );
            }

            $now = now();
            $order = Order::query()->create([
                'source' => 'pos',
                'device_id' => $lockedDevice->id,
                'device_uuid' => $lockedDevice->uuid,
                'device_name' => $lockedDevice->name,
                'cashier_user_id' => $cashier->id,
                'cashier_name' => $cashier->name,
                'currency' => Order::CURRENCY,
                'subtotal_excluding_tax_cents' => $subtotalExcludingTaxCents,
                'tax_9_cents' => $taxByRate[900],
                'tax_21_cents' => $taxByRate[2100],
                'tax_total_cents' => $this->checkedAdd($taxByRate[900], $taxByRate[2100]),
                'total_including_tax_cents' => $totalIncludingTaxCents,
                'status' => 'paid',
                'payment_status' => 'paid',
                'payment_method' => 'cash',
                'idempotency_key' => $data['idempotency_key'],
                'payload_fingerprint' => $fingerprint,
                'ordered_at' => $now,
                'paid_at' => $now,
            ]);
            $order->order_number = 'POS-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
            $order->save();

            $catalogOrderItems = [];

            foreach ($preparedItems as $preparedItem) {
                /** @var Product|null $product */
                $product = $preparedItem['product'];
                unset($preparedItem['product']);
                $orderItem = $order->items()->create([
                    ...$preparedItem,
                    'product_id' => $product?->id,
                ]);

                if ($product !== null) {
                    $catalogOrderItems[] = [$product, $orderItem];
                }
            }

            $order->payment()->create([
                'method' => 'cash',
                'amount_cents' => $totalIncludingTaxCents,
                'tendered_amount_cents' => $data['tendered_amount_cents'],
                'change_due_cents' => $data['tendered_amount_cents'] - $totalIncludingTaxCents,
                'currency' => Order::CURRENCY,
                'status' => 'paid',
                'paid_at' => $now,
            ]);

            usort($catalogOrderItems, fn (array $left, array $right): int => $left[0]->id <=> $right[0]->id);

            foreach ($catalogOrderItems as [$product, $orderItem]) {
                $this->postInventory->handle(
                    product: $product,
                    quantityDelta: '-'.$orderItem->quantity,
                    type: InventoryTransactionType::PosSale,
                    idempotencyKey: 'pos-order-item:'.$orderItem->id,
                    actor: $cashier,
                    reason: 'POS sale '.$order->order_number,
                    orderItem: $orderItem,
                );
            }

            return $order->load(['items', 'payment']);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function validateProduct(?Product $product, array $item, int $index): void
    {
        if ($product === null || ! $product->active) {
            throw new CheckoutConflict('product_unavailable', 'A product is unavailable.', ['item_index' => $index]);
        }

        if (! $product->taxRate->active || ! in_array($product->taxRate->rate_basis_points, [900, 2100], true)) {
            throw new CheckoutConflict('tax_rate_unavailable', 'A product VAT rate is unavailable.', ['item_index' => $index]);
        }

        if (
            $product->selling_price_including_tax_cents !== (int) $item['expected_unit_price_including_tax_cents']
            || $product->taxRate->rate_basis_points !== (int) $item['expected_tax_rate_basis_points']
        ) {
            throw new CheckoutConflict('product_changed', 'A product price or VAT rate has changed.', ['item_index' => $index]);
        }

        $stockMilliunits = DecimalQuantity::toMilliunits($product->stock_on_hand);

        if ($product->woocommerce_product_id !== null && $stockMilliunits % 1000 !== 0) {
            throw new CheckoutConflict(
                'fractional_woocommerce_stock',
                'A WooCommerce-mapped product has fractional stock.',
                ['item_index' => $index],
            );
        }

        if ($stockMilliunits < ((int) $item['quantity'] * 1000)) {
            throw new CheckoutConflict('insufficient_stock', 'Insufficient stock for a product.', ['item_index' => $index]);
        }
    }

    private function checkedAdd(int $left, int $right): int
    {
        if ($right > PHP_INT_MAX - $left) {
            throw new OverflowException;
        }

        return $left + $right;
    }

    /**
     * @param  array{payment_method: string, tendered_amount_cents: int, idempotency_key: string, items: list<array<string, mixed>>}  $data
     *
     * @throws JsonException
     */
    private function fingerprint(array $data): string
    {
        $items = array_map(function (array $item): array {
            if ($item['type'] === 'product') {
                return [
                    'type' => 'product',
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                    'expected_unit_price_including_tax_cents' => (int) $item['expected_unit_price_including_tax_cents'],
                    'expected_tax_rate_basis_points' => (int) $item['expected_tax_rate_basis_points'],
                ];
            }

            return [
                'type' => 'manual',
                'description' => trim((string) $item['description']),
                'quantity' => (int) $item['quantity'],
                'unit_price_including_tax_cents' => (int) $item['unit_price_including_tax_cents'],
                'tax_rate_basis_points' => (int) $item['tax_rate_basis_points'],
            ];
        }, $data['items']);

        return hash('sha256', json_encode([
            'payment_method' => $data['payment_method'],
            'tendered_amount_cents' => $data['tendered_amount_cents'],
            'items' => $items,
        ], JSON_THROW_ON_ERROR));
    }
}
