<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $printer_model
 * @property string|null $printer_ip_address
 * @property int|null $printer_port
 * @property int|null $printer_paper_width
 * @property bool $active
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'uuid',
    'name',
    'printer_model',
    'printer_ip_address',
    'printer_port',
    'printer_paper_width',
    'active',
    'last_seen_at',
])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function hasPrinterConfiguration(): bool
    {
        return $this->printer_model !== null
            && $this->printer_ip_address !== null
            && $this->printer_port !== null
            && $this->printer_paper_width !== null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_seen_at' => 'datetime',
            'printer_port' => 'integer',
            'printer_paper_width' => 'integer',
        ];
    }
}
