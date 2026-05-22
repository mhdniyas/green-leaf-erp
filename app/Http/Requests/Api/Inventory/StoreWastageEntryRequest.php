<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Inventory;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\WastageReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWastageEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inventory.wastage.record');
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'batch_id' => ['nullable', 'integer', 'exists:stock_batches,id'],
            'grade' => ['required', 'string', Rule::enum(ProductGrade::class)],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'cost_per_kg' => ['required', 'numeric', 'min:0.01', 'max:9999'],
            'reason' => ['required', 'string', Rule::enum(WastageReason::class)],
            'wastage_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
