<?php

namespace App\Models;

use Database\Factories\WebhookReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'provider',
    'webhook_id',
    'delivery_id',
    'topic',
    'external_resource_id',
    'payload_hash',
    'status',
    'received_at',
])]
class WebhookReceipt extends Model
{
    /** @use HasFactory<WebhookReceiptFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'webhook_id' => 'integer',
            'delivery_id' => 'integer',
            'external_resource_id' => 'integer',
            'received_at' => 'datetime',
        ];
    }
}
