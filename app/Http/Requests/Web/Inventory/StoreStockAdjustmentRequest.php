<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Inventory;

use App\Enums\Inventory\ProductGrade;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.stock.adjust') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('grade') && $this->input('grade') !== null && $this->input('grade') !== '') {
            $resolved = ProductGrade::tryResolve((string) $this->input('grade'));
            if ($resolved !== null) {
                $this->merge(['grade' => $resolved->value]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'counted_qty' => ['required', 'numeric', 'min:0'],
            'system_qty' => ['required', 'numeric'],
            'business_date' => ['required', 'date', 'before_or_equal:today'],
            'preset_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'grade' => [
                'nullable',
                'string',
                'max:20',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value !== null && $value !== '' && ProductGrade::tryResolve((string) $value) === null) {
                        $fail('The selected grade is invalid.');
                    }
                },
            ],
        ];
    }
}
