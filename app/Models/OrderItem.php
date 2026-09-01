<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'order_id',
    'position',
    'product_id',
    'is_manual',
    'article_number',
    'description',
    'quantity',
    'unit_price_including_tax_cents',
    'tax_rate_basis_points',
    'line_excluding_tax_cents',
    'tax_amount_cents',
    'line_including_tax_cents',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasOne<InventoryTransaction, $this> */
    public function inventoryTransaction(): HasOne
    {
        return $this->hasOne(InventoryTransaction::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_manual' => 'boolean',
            'quantity' => 'decimal:3',
            'unit_price_including_tax_cents' => 'integer',
            'tax_rate_basis_points' => 'integer',
            'line_excluding_tax_cents' => 'integer',
            'tax_amount_cents' => 'integer',
            'line_including_tax_cents' => 'integer',
        ];
    }
}
