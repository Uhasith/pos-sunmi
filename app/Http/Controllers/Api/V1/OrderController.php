<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CheckoutResource;
use App\Models\Order;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Order::class);

        return CheckoutResource::collection(
            Order::query()
                ->orderByDesc('ordered_at')
                ->orderByDesc('id')
                ->paginate(20),
        );
    }

    public function show(Order $order): CheckoutResource
    {
        Gate::authorize('view', $order);

        return new CheckoutResource($order->load('items', 'payment'));
    }

    public function receipt(Order $order): CheckoutResource
    {
        Gate::authorize('view', $order);

        return (new CheckoutResource($order->load('items', 'payment')))
            ->additional([
                'meta' => [
                    'receipt' => [
                        'type' => 'sale',
                        'is_reprint' => true,
                    ],
                ],
            ]);
    }
}
