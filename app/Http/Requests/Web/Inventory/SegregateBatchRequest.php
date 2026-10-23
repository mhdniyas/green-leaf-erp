<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Inventory;

use App\Enums\Inventory\WastageReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SegregateBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && ($this->user()->can('inventory.sorting.process')
                || $this->user()->can('warehouse.receive.sort')
                || $this->user()->hasRole('admin')
                || $this->user()->hasRole('warehouse_receiver'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'target_grade' => ['required', 'string', Rule::in(['A', 'B', 'C', 'DMG', 'Damage', 'damage'])],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', Rule::in(array_column(WastageReason::cases(), 'value'))],
            'client_submission_id' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_grade.required' => 'Please select a target grade (Grade A, B, C, or Damage).',
            'target_grade.in' => 'Target grade must be Grade A, Grade B, Grade C, or Damage.',
            'quantity.required' => 'Please enter the quantity to segregate.',
            'quantity.gt' => 'Quantity must be greater than zero.',
        ];
    }
}
