<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->active
            && in_array($user->role, [UserRole::Cashier, UserRole::Manager, UserRole::Admin], true);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'payment_method' => ['required', Rule::in(['cash'])],
            'tendered_amount_cents' => ['required', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:type,product_id,quantity,expected_unit_price_including_tax_cents,expected_tax_rate_basis_points,description,unit_price_including_tax_cents,tax_rate_basis_points'],
            'items.*.type' => ['required', Rule::in(['product', 'manual'])],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'items.*.product_id' => ['required_if:items.*.type,product', 'prohibited_unless:items.*.type,product', 'integer', 'min:1'],
            'items.*.expected_unit_price_including_tax_cents' => ['required_if:items.*.type,product', 'prohibited_unless:items.*.type,product', 'integer', 'min:0'],
            'items.*.expected_tax_rate_basis_points' => ['required_if:items.*.type,product', 'prohibited_unless:items.*.type,product', 'integer', Rule::in([900, 2100])],
            'items.*.description' => ['required_if:items.*.type,manual', 'prohibited_unless:items.*.type,manual', 'string', 'max:255'],
            'items.*.unit_price_including_tax_cents' => ['required_if:items.*.type,manual', 'prohibited_unless:items.*.type,manual', 'integer', 'min:0'],
            'items.*.tax_rate_basis_points' => ['required_if:items.*.type,manual', 'prohibited_unless:items.*.type,manual', 'integer', Rule::in([900, 2100])],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $items = $this->input('items');

            if (! is_array($items)) {
                return;
            }

            $productIds = [];

            foreach ($items as $item) {
                if (
                    is_array($item)
                    && ($item['type'] ?? null) === 'product'
                    && isset($item['product_id'])
                    && is_scalar($item['product_id'])
                ) {
                    $productIds[] = (string) $item['product_id'];
                }
            }

            if (count($productIds) !== count(array_unique($productIds))) {
                $validator->errors()->add('items', 'Catalog products may only appear once per checkout.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (is_array($items)) {
            $items = array_map(function (mixed $item): mixed {
                if (is_array($item) && isset($item['description']) && is_string($item['description'])) {
                    $item['description'] = trim($item['description']);
                }

                return $item;
            }, $items);
        }

        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
            'items' => $items,
        ]);
    }
}
