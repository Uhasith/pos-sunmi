<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

beforeEach(function () {
    config(['services.woocommerce.webhook_secret' => 'test-webhook-secret']);
    Http::preventStrayRequests();
});

it('accepts a signed order webhook without retaining its body', function () {
    $payload = json_encode([
        'id' => 481,
        'status' => 'processing',
        'billing' => ['email' => 'customer@example.test'],
    ], JSON_THROW_ON_ERROR);

    wooWebhookRequest($this, $payload)
        ->assertAccepted()
        ->assertJsonPath('data.accepted', true)
        ->assertJsonPath('data.duplicate', false);

    $this->assertDatabaseHas('webhook_receipts', [
        'provider' => 'woocommerce',
        'webhook_id' => 12,
        'delivery_id' => 34,
        'topic' => 'order.updated',
        'external_resource_id' => 481,
        'payload_hash' => hash('sha256', $payload),
        'status' => 'pending',
    ]);
    expect(json_encode(DB::table('webhook_receipts')->first(), JSON_THROW_ON_ERROR))
        ->not->toContain('customer@example.test');
    Http::assertNothingSent();
});

it('deduplicates repeated WooCommerce delivery IDs', function () {
    $payload = json_encode(['id' => 481, 'status' => 'processing'], JSON_THROW_ON_ERROR);

    wooWebhookRequest($this, $payload)->assertAccepted();
    wooWebhookRequest($this, $payload)
        ->assertOk()
        ->assertJsonPath('data.duplicate', true);

    $this->assertDatabaseCount('webhook_receipts', 1);
    Http::assertNothingSent();
});

it('returns 409 when a delivery ID is reused with a different signed body', function () {
    wooWebhookRequest($this, json_encode(['id' => 481, 'status' => 'processing'], JSON_THROW_ON_ERROR))
        ->assertAccepted();

    wooWebhookRequest($this, json_encode(['id' => 481, 'status' => 'completed'], JSON_THROW_ON_ERROR))
        ->assertConflict()
        ->assertJsonPath('error.code', 'woocommerce_webhook_conflict');

    $this->assertDatabaseCount('webhook_receipts', 1);
    Http::assertNothingSent();
});

it('returns 401 for an invalid signature without storing a receipt', function () {
    $payload = json_encode(['id' => 481], JSON_THROW_ON_ERROR);

    wooWebhookRequest($this, $payload, 'invalid-signature')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'invalid_woocommerce_webhook');

    $this->assertDatabaseEmpty('webhook_receipts');
    Http::assertNothingSent();
});

it('returns 422 for invalid signed metadata without storing a receipt', function () {
    $payload = json_encode(['id' => 481], JSON_THROW_ON_ERROR);

    wooWebhookRequest($this, $payload, topic: 'product.updated')
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed');

    $this->assertDatabaseEmpty('webhook_receipts');
    Http::assertNothingSent();
});

it('returns 422 for an invalid signed JSON body without storing a receipt', function () {
    wooWebhookRequest($this, 'not-json')
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed');

    $this->assertDatabaseEmpty('webhook_receipts');
    Http::assertNothingSent();
});

it('fails safely when the webhook secret is not configured', function () {
    config(['services.woocommerce.webhook_secret' => null]);

    wooWebhookRequest($this, json_encode(['id' => 481], JSON_THROW_ON_ERROR))
        ->assertInternalServerError()
        ->assertJsonPath('error.code', 'server_error');

    $this->assertDatabaseEmpty('webhook_receipts');
    Http::assertNothingSent();
});

it('returns 413 for an oversized webhook body without storing a receipt', function () {
    wooWebhookRequest($this, str_repeat('x', 1_048_577))
        ->assertStatus(413)
        ->assertJsonPath('error.code', 'request_failed');

    $this->assertDatabaseEmpty('webhook_receipts');
    Http::assertNothingSent();
});

function wooWebhookRequest(
    TestCase $testCase,
    string $payload,
    ?string $signature = null,
    string $topic = 'order.updated',
): TestResponse {
    return $testCase->call('POST', route('webhooks.woocommerce'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_WC_WEBHOOK_SIGNATURE' => $signature ?? base64_encode(hash_hmac('sha256', $payload, 'test-webhook-secret', true)),
        'HTTP_X_WC_WEBHOOK_ID' => '12',
        'HTTP_X_WC_WEBHOOK_DELIVERY_ID' => '34',
        'HTTP_X_WC_WEBHOOK_TOPIC' => $topic,
    ], content: $payload);
}
