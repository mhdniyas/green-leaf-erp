<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('inventory.stock.adjust') ?? false; }

    public function rules(): array
    {
        return [
            'counted_qty' => ['required', 'numeric', 'min:0'],
            'system_qty' => ['required', 'numeric', 'min:0'],
            'business_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['required', 'string', 'max:1000'],
        ];
    }
}
