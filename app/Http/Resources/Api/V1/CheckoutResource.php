<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class CheckoutResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'source' => $this->source,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'currency' => $this->currency,
            'ordered_at_utc' => $this->ordered_at->copy()->utc()->toISOString(),
            'ordered_at_local' => $this->ordered_at->copy()->setTimezone(Order::SHOP_TIMEZONE)->format('Y-m-d\TH:i:s.uP'),
            'shop_timezone' => Order::SHOP_TIMEZONE,
            'cashier' => [
                'id' => $this->cashier_user_id,
                'name' => $this->cashier_name,
            ],
            'device' => [
                'uuid' => $this->device_uuid,
                'name' => $this->device_name,
            ],
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (OrderItem $item): array => [
                'id' => $item->id,
                'position' => $item->position,
                'type' => $item->is_manual ? 'manual' : 'product',
                'product_id' => $item->product_id,
                'article_number' => $item->article_number,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price_including_tax_cents' => $item->unit_price_including_tax_cents,
                'tax_rate_basis_points' => $item->tax_rate_basis_points,
                'line_excluding_tax_cents' => $item->line_excluding_tax_cents,
                'tax_amount_cents' => $item->tax_amount_cents,
                'line_including_tax_cents' => $item->line_including_tax_cents,
            ])->all()),
            'totals' => [
                'excluding_tax_cents' => $this->subtotal_excluding_tax_cents,
                'vat_9_cents' => $this->tax_9_cents,
                'vat_21_cents' => $this->tax_21_cents,
                'tax_total_cents' => $this->tax_total_cents,
                'including_tax_cents' => $this->total_including_tax_cents,
            ],
            'payment' => $this->whenLoaded('payment', fn (): ?array => $this->payment === null ? null : [
                'id' => $this->payment->id,
                'method' => $this->payment->method,
                'status' => $this->payment->status,
                'amount_cents' => $this->payment->amount_cents,
                'tendered_amount_cents' => $this->payment->tendered_amount_cents,
                'change_due_cents' => $this->payment->change_due_cents,
                'currency' => $this->payment->currency,
                'paid_at_utc' => $this->payment->paid_at->copy()->utc()->toISOString(),
            ]),
        ];
    }
}
