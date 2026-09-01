<?php

namespace App\Jobs;

use App\Exceptions\FractionalWooCommerceStock;
use App\Integrations\WooCommerce\WooCommerceClient;
use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncProductStockToWooCommerce implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public int $productId) {}

    public function handle(WooCommerceClient $client): void
    {
        $product = Product::query()->find($this->productId);

        if ($product === null || $product->woocommerce_product_id === null) {
            return;
        }

        if (! str_ends_with($product->stock_on_hand, '.000')) {
            $this->fail(new FractionalWooCommerceStock(
                "Product {$product->id} has fractional stock and cannot be projected to WooCommerce.",
            ));

            return;
        }

        $client->updateStock(
            $product->woocommerce_product_id,
            $product->woocommerce_variation_id,
            (int) $product->stock_on_hand,
        );
    }

    public function uniqueId(): string
    {
        return (string) $this->productId;
    }
}
