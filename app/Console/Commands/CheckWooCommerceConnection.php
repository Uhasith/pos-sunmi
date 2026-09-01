<?php

namespace App\Console\Commands;

use App\Integrations\WooCommerce\WooCommerceClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('woocommerce:check')]
#[Description('Verify the configured WooCommerce REST API credentials')]
class CheckWooCommerceConnection extends Command
{
    public function handle(WooCommerceClient $client): int
    {
        try {
            $client->systemStatus();
        } catch (Throwable) {
            $this->components->error('WooCommerce connection failed. Check the URL, credentials, and API permissions.');

            return self::FAILURE;
        }

        $this->components->info('WooCommerce REST API connection successful.');

        return self::SUCCESS;
    }
}
