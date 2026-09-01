<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Inventory\PostInventory;
use App\Enums\InventoryTransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInventoryTransactionRequest;
use App\Http\Resources\Api\V1\InventoryTransactionResource;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class InventoryTransactionController extends Controller
{
    public function index(Product $product): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', InventoryTransaction::class);

        return InventoryTransactionResource::collection(
            $product->inventoryTransactions()
                ->with('actor')
                ->orderByDesc('id')
                ->paginate(20),
        );
    }

    public function store(
        StoreInventoryTransactionRequest $request,
        Product $product,
        PostInventory $postInventory,
    ): InventoryTransactionResource {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new AuthorizationException;
        }

        $data = $request->validated();
        $transaction = $postInventory->handle(
            product: $product,
            quantityDelta: (string) $data['quantity_delta'],
            type: InventoryTransactionType::ManualAdjustment,
            idempotencyKey: (string) $data['idempotency_key'],
            actor: $actor,
            reason: (string) $data['reason'],
        );

        return new InventoryTransactionResource($transaction->load('actor'));
    }
}
