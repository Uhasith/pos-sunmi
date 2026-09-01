<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.woocommerce', [
        'base_url' => 'https://shop.example',
        'key' => 'ck_test',
        'secret' => 'cs_test',
    ]);
});

it('verifies WooCommerce credentials with a read-only request', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://shop.example/wp-json/wc/v3/system_status' => Http::response(['environment' => []]),
    ]);

    $this->artisan('woocommerce:check')
        ->expectsOutputToContain('WooCommerce REST API connection successful.')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://shop.example/wp-json/wc/v3/system_status'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('ck_test:cs_test')));
});

it('fails safely when WooCommerce rejects the request', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://shop.example/wp-json/wc/v3/system_status' => Http::response([
            'code' => 'woocommerce_rest_cannot_view',
        ], 401),
    ]);

    $this->artisan('woocommerce:check')
        ->expectsOutputToContain('WooCommerce connection failed.')
        ->assertFailed();

    Http::assertSentCount(1);
});

it('rejects an insecure WooCommerce base URL before sending a request', function () {
    config()->set('services.woocommerce.base_url', 'http://shop.example');
    Http::preventStrayRequests();

    $this->artisan('woocommerce:check')
        ->expectsOutputToContain('WooCommerce connection failed.')
        ->assertFailed();

    Http::assertNothingSent();
});
