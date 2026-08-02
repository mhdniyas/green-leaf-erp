<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ShopOrderQuantityCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin')
            || $this->user()?->can('inventory.stock.adjust')
            || $this->user()?->can('inventory.product.edit');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'requested_unit_quantity' => ['required', 'numeric', 'min:0'],
            'requested_unit' => ['required', 'string', 'max:30'],
            'requested_unit_conversion_to_base' => ['required', 'numeric', 'gt:0'],
            'requested_qty' => ['required', 'numeric', 'min:0'],
            'approved_qty' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
