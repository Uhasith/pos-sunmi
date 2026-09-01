<?php

namespace App\Integrations\WooCommerce;

use App\Exceptions\WooCommerceRequestFailed;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use UnexpectedValueException;

class WooCommerceClient
{
    /** @return array<string, mixed> */
    public function systemStatus(): array
    {
        $status = $this->request()
            ->get('system_status')
            ->throw()
            ->json();

        if (! is_array($status)) {
            throw new UnexpectedValueException('WooCommerce returned an invalid system status response.');
        }

        return $status;
    }

    public function updateStock(int $productId, ?int $variationId, int $stockQuantity): void
    {
        $endpoint = $variationId === null
            ? "products/{$productId}"
            : "products/{$productId}/variations/{$variationId}";

        try {
            $this->request()
                ->put($endpoint, [
                    'manage_stock' => true,
                    'stock_quantity' => $stockQuantity,
                ])
                ->throw();
        } catch (RequestException $exception) {
            throw new WooCommerceRequestFailed(
                "WooCommerce stock update failed with HTTP {$exception->response->status()}.",
            );
        }
    }

    private function request(): PendingRequest
    {
        $baseUrl = rtrim($this->configuration('base_url'), '/');

        if (parse_url($baseUrl, PHP_URL_SCHEME) !== 'https') {
            throw new InvalidArgumentException('WooCommerce must use an HTTPS base URL.');
        }

        return Http::baseUrl($baseUrl.'/wp-json/wc/v3')
            ->withBasicAuth($this->configuration('key'), $this->configuration('secret'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15);
    }

    private function configuration(string $key): string
    {
        $value = config("services.woocommerce.{$key}");

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('WooCommerce configuration is incomplete.');
        }

        return trim($value);
    }
}
