<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Checkout\CompleteCashCheckout;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCheckoutRequest;
use App\Http\Resources\Api\V1\CheckoutResource;
use App\Models\Device;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class CheckoutController extends Controller
{
    public function store(StoreCheckoutRequest $request, CompleteCashCheckout $completeCashCheckout): CheckoutResource
    {
        $cashier = $request->user();
        $device = $request->attributes->get('device');

        if (! $cashier instanceof User || ! $device instanceof Device) {
            throw new AuthorizationException;
        }

        /** @var array{payment_method: string, tendered_amount_cents: int, idempotency_key: string, items: list<array<string, mixed>>} $data */
        $data = $request->validated();

        return new CheckoutResource($completeCashCheckout->handle($data, $cashier, $device));
    }
}
