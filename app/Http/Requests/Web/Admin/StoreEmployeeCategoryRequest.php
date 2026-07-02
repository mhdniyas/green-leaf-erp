<?php

namespace App\Http\Requests\Web\Admin;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Employee::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:employee_categories,name'],
            'code' => ['required', 'string', 'max:50', 'unique:employee_categories,code'],
            'staff_area' => ['required', Rule::in(['office', 'shop'])],
            'default_monthly_salary' => ['required', 'numeric', 'min:0'],
            'monthly_paid_leave_limit' => ['required', 'integer', 'min:0', 'max:31'],
            'present_day_weight' => ['required', 'numeric', 'min:0', 'max:1'],
            'half_day_weight' => ['required', 'numeric', 'min:0', 'max:1'],
            'paid_leave_weight' => ['required', 'numeric', 'min:0', 'max:1'],
            'excess_leave_weight' => ['required', 'numeric', 'min:0', 'max:1'],
            'absent_day_weight' => ['required', 'numeric', 'min:0', 'max:1'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
