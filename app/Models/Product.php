<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $article_number
 * @property string|null $barcode
 * @property int $selling_price_including_tax_cents
 * @property int $purchase_price_excluding_tax_cents
 * @property string $stock_on_hand
 * @property string $minimum_stock
 * @property int|null $brand_id
 * @property int|null $supplier_id
 * @property int $tax_rate_id
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'title',
    'article_number',
    'barcode',
    'selling_price_including_tax_cents',
    'purchase_price_excluding_tax_cents',
    'minimum_stock',
    'brand_id',
    'supplier_id',
    'tax_rate_id',
    'active',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<TaxRate, $this> */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /** @return HasMany<InventoryTransaction, $this> */
    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'selling_price_including_tax_cents' => 'integer',
            'purchase_price_excluding_tax_cents' => 'integer',
            'stock_on_hand' => 'decimal:3',
            'minimum_stock' => 'decimal:3',
            'active' => 'boolean',
        ];
    }
}
