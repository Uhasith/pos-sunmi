<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
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
            'title' => $this->title,
            'article_number' => $this->article_number,
            'barcode' => $this->barcode,
            'selling_price_including_tax_cents' => $this->selling_price_including_tax_cents,
            'stock_on_hand' => $this->stock_on_hand,
            'tax_rate' => $this->whenLoaded('taxRate', fn (): array => [
                'code' => $this->taxRate->code,
                'label' => $this->taxRate->label,
                'rate_basis_points' => $this->taxRate->rate_basis_points,
            ]),
        ];
    }
}
