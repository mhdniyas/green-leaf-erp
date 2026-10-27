<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardSegregateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('inventory.sorting.process')
            || $user->can('warehouse.receive.sort')
            || $user->hasRole('admin')
            || $user->hasRole('warehouse_receiver');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'integer', 'exists:stock_batches,id'],
            'target_grade' => ['required', 'string', Rule::in(['A', 'B', 'C'])],
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
            'batch_id.required' => 'Please select an eligible source batch.',
            'batch_id.exists' => 'The selected source batch does not exist.',
            'target_grade.required' => 'Please select a target grade (Grade A, B, or C).',
            'target_grade.in' => 'Target grade must be Grade A, Grade B, or Grade C.',
            'quantity.required' => 'Please enter the quantity to segregate.',
            'quantity.gt' => 'Quantity to segregate must be greater than zero.',
        ];
    }
}
