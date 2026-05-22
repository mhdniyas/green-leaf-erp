<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Inventory;

use App\Enums\Inventory\ProductGrade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SortBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inventory.sorting.process');
    }

    public function rules(): array
    {
        return [
            'grades' => ['required', 'array', 'min:1'],
            'grades.*.grade' => ['required', 'string', Rule::enum(ProductGrade::class)],
            'grades.*.quantity' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'grades.required' => 'Grade breakdown is required.',
            'grades.*.grade.required' => 'Each grade entry must have a grade.',
            'grades.*.quantity.min' => 'Quantity cannot be negative.',
        ];
    }
}
