<?php

namespace App\Integrations\WooCommerce;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use UnexpectedValueException;

class WooCommerceClient
{
    /** @return array<string, mixed> */
    public function systemStatus(): array
    {
        $baseUrl = rtrim($this->configuration('base_url'), '/');

        if (parse_url($baseUrl, PHP_URL_SCHEME) !== 'https') {
            throw new InvalidArgumentException('WooCommerce must use an HTTPS base URL.');
        }

        $status = Http::baseUrl($baseUrl.'/wp-json/wc/v3')
            ->withBasicAuth($this->configuration('key'), $this->configuration('secret'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->get('system_status')
            ->throw()
            ->json();

        if (! is_array($status)) {
            throw new UnexpectedValueException('WooCommerce returned an invalid system status response.');
        }

        return $status;
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
