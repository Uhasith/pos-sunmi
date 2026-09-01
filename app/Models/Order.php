<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $order_number
 * @property string $source
 * @property int|null $device_id
 * @property string $device_uuid
 * @property string $device_name
 * @property int|null $cashier_user_id
 * @property string $cashier_name
 * @property string $currency
 * @property int $subtotal_excluding_tax_cents
 * @property int $tax_9_cents
 * @property int $tax_21_cents
 * @property int $tax_total_cents
 * @property int $total_including_tax_cents
 * @property string $status
 * @property string $payment_status
 * @property string $payment_method
 * @property string $idempotency_key
 * @property string $payload_fingerprint
 * @property Carbon $ordered_at
 * @property Carbon $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'order_number',
    'source',
    'device_id',
    'device_uuid',
    'device_name',
    'cashier_user_id',
    'cashier_name',
    'currency',
    'subtotal_excluding_tax_cents',
    'tax_9_cents',
    'tax_21_cents',
    'tax_total_cents',
    'total_including_tax_cents',
    'status',
    'payment_status',
    'payment_method',
    'idempotency_key',
    'payload_fingerprint',
    'ordered_at',
    'paid_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public const CURRENCY = 'EUR';

    public const SHOP_TIMEZONE = 'Europe/Amsterdam';

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('position');
    }

    /** @return HasOne<Payment, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'subtotal_excluding_tax_cents' => 'integer',
            'tax_9_cents' => 'integer',
            'tax_21_cents' => 'integer',
            'tax_total_cents' => 'integer',
            'total_including_tax_cents' => 'integer',
            'ordered_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
