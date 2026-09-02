<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CheckoutResource;
use App\Models\Order;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function show(Order $order): CheckoutResource
    {
        Gate::authorize('view', $order);

        return new CheckoutResource($order->load('items', 'payment'));
    }
}
