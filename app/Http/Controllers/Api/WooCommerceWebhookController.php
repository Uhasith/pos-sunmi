<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidWooCommerceWebhook;
use App\Exceptions\WooCommerceWebhookConflict;
use App\Http\Controllers\Controller;
use App\Models\WebhookReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use UnexpectedValueException;

class WooCommerceWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-WC-Webhook-Signature');
        $secret = config('services.woocommerce.webhook_secret');

        if (strlen($payload) > 1_048_576) {
            throw new HttpException(Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        if (! is_string($secret) || trim($secret) === '') {
            throw new UnexpectedValueException('WooCommerce webhook configuration is incomplete.');
        }

        $expectedSignature = base64_encode(hash_hmac('sha256', $payload, trim($secret), true));

        if (! is_string($signature) || ! hash_equals($expectedSignature, trim($signature))) {
            throw new InvalidWooCommerceWebhook('The WooCommerce webhook signature is invalid.');
        }

        try {
            $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['payload' => 'The webhook payload must be valid JSON.']);
        }

        $validated = Validator::make([
            'webhook_id' => $request->header('X-WC-Webhook-ID'),
            'delivery_id' => $request->header('X-WC-Webhook-Delivery-ID'),
            'topic' => $request->header('X-WC-Webhook-Topic'),
            'external_resource_id' => is_array($decoded) ? ($decoded['id'] ?? null) : null,
        ], [
            'webhook_id' => ['required', 'integer', 'min:1'],
            'delivery_id' => ['required', 'integer', 'min:1'],
            'topic' => ['required', 'in:order.created,order.updated'],
            'external_resource_id' => ['required', 'integer', 'min:1'],
        ])->validate();

        $now = now();
        $payloadHash = hash('sha256', $payload);
        $created = WebhookReceipt::query()->insertOrIgnore([[
            'provider' => 'woocommerce',
            'webhook_id' => $validated['webhook_id'],
            'delivery_id' => $validated['delivery_id'],
            'topic' => $validated['topic'],
            'external_resource_id' => $validated['external_resource_id'],
            'payload_hash' => $payloadHash,
            'status' => 'pending',
            'received_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]]);

        if ($created === 0) {
            $storedHash = WebhookReceipt::query()
                ->where('provider', 'woocommerce')
                ->where('webhook_id', $validated['webhook_id'])
                ->where('delivery_id', $validated['delivery_id'])
                ->value('payload_hash');

            if (! is_string($storedHash) || ! hash_equals($storedHash, $payloadHash)) {
                throw new WooCommerceWebhookConflict('The WooCommerce delivery ID was reused with a different payload.');
            }
        }

        return response()->json([
            'data' => [
                'accepted' => true,
                'duplicate' => $created === 0,
            ],
        ], $created === 0 ? Response::HTTP_OK : Response::HTTP_ACCEPTED);
    }
}
