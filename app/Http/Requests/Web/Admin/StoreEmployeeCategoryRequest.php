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
            'paid_leave_carry_forward_allowed' => ['nullable', 'boolean'],
            'paid_leave_maximum_carry_forward_days' => ['nullable', 'numeric', 'min:0'],
            'paid_leave_carry_forward_expiry_months' => ['nullable', 'integer', 'min:0', 'max:24'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [
            'present_day_weight' => 1,
            'paid_leave_weight' => 1,
        ];

        foreach ([
            'half_day_salary_percent' => 'half_day_weight',
            'unpaid_leave_salary_percent' => 'excess_leave_weight',
            'absent_day_salary_percent' => 'absent_day_weight',
        ] as $percentField => $weightField) {
            if ($this->filled($percentField)) {
                $normalized[$weightField] = round(((float) $this->input($percentField)) / 100, 2);
            }
        }

        foreach (['half_day_weight', 'excess_leave_weight', 'absent_day_weight'] as $field) {
            if ($this->filled($field)) {
                $normalized[$field] = $this->input($field);
            }
        }

        $this->merge($normalized);
    }

    public function attributes(): array
    {
        return [
            'default_monthly_salary' => 'default monthly salary',
            'monthly_paid_leave_limit' => 'paid leaves per month',
            'half_day_weight' => 'half day pay',
            'excess_leave_weight' => 'unpaid leave pay',
            'absent_day_weight' => 'absent day pay',
        ];
    }
}
