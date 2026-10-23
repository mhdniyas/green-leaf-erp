<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ReverseSegregationRequest extends FormRequest
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
            'quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'client_submission_id' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.required' => 'Please enter the quantity to reverse.',
            'quantity.gt' => 'Quantity must be greater than zero.',
        ];
    }
}
