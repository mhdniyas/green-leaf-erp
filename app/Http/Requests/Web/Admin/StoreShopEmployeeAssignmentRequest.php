<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShopEmployeeAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.employee.update') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'shop_id' => [
                'required',
                'integer',
                Rule::exists('shops', 'id')
                    ->where('status', 'active')
                    ->where('accounting_enabled', true)
                    ->whereIn('accounting_mode', ['owned', 'partnership']),
            ],
            'effective_from' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
