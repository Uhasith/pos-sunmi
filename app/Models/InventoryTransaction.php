<?php

namespace App\Models;

use App\Enums\InventoryTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property string $quantity_delta
 * @property InventoryTransactionType $type
 * @property string $stock_before
 * @property string $stock_after
 * @property string|null $reason
 * @property int|null $actor_user_id
 * @property string $idempotency_key
 * @property Carbon $created_at
 */
#[Fillable([
    'product_id',
    'quantity_delta',
    'type',
    'stock_before',
    'stock_after',
    'reason',
    'actor_user_id',
    'idempotency_key',
])]
class InventoryTransaction extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:3',
            'type' => InventoryTransactionType::class,
            'stock_before' => 'decimal:3',
            'stock_after' => 'decimal:3',
        ];
    }
}
