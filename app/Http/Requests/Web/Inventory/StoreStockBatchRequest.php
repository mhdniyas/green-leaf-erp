<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inventory.stock.adjust');
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'total_kg' => ['required', 'numeric', 'min:0.1'],
            'cost_per_kg' => ['required', 'numeric', 'min:0.01'],
            'transport_cost' => ['nullable', 'numeric', 'min:0'],
            'labour_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
