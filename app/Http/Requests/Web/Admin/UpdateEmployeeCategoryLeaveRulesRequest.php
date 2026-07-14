<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeCategoryLeaveRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.employee.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'rules' => ['required', 'array', 'min:1'],
            'rules.*.leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'rules.*.annual_entitlement' => ['required', 'numeric', 'min:0'],
            'rules.*.monthly_accrual_amount' => ['nullable', 'numeric', 'min:0'],
            'rules.*.allocation_frequency' => ['required', Rule::in(['monthly', 'annual_opening', 'manual'])],
            'rules.*.carry_forward_allowed' => ['nullable', 'boolean'],
            'rules.*.maximum_carry_forward_days' => ['nullable', 'numeric', 'min:0'],
            'rules.*.carry_forward_expiry_months' => ['nullable', 'integer', 'min:0', 'max:24'],
            'rules.*.carry_forward_expiry_date' => ['nullable', 'date'],
            'rules.*.payroll_weight' => ['required', 'numeric', 'min:0', 'max:1'],
            'rules.*.negative_balance_allowed' => ['nullable', 'boolean'],
            'rules.*.effective_from' => ['nullable', 'date'],
            'rules.*.effective_to' => ['nullable', 'date'],
            'rules.*.notes' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                foreach ((array) $this->input('rules', []) as $index => $rule) {
                    $effectiveFrom = $rule['effective_from'] ?? null;
                    $effectiveTo = $rule['effective_to'] ?? null;

                    if (filled($effectiveFrom) && filled($effectiveTo) && $effectiveTo < $effectiveFrom) {
                        $validator->errors()->add("rules.{$index}.effective_to", 'The end date must be on or after the start date.');
                    }
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $rules = collect((array) $this->input('rules', []))
            ->map(function ($rule): array {
                $rule = is_array($rule) ? $rule : [];

                return [
                    ...$rule,
                    'carry_forward_allowed' => filter_var($rule['carry_forward_allowed'] ?? false, FILTER_VALIDATE_BOOL),
                    'negative_balance_allowed' => filter_var($rule['negative_balance_allowed'] ?? false, FILTER_VALIDATE_BOOL),
                    'monthly_accrual_amount' => filled($rule['monthly_accrual_amount'] ?? null) ? $rule['monthly_accrual_amount'] : null,
                    'maximum_carry_forward_days' => filled($rule['maximum_carry_forward_days'] ?? null) ? $rule['maximum_carry_forward_days'] : 0,
                    'carry_forward_expiry_months' => filled($rule['carry_forward_expiry_months'] ?? null) ? $rule['carry_forward_expiry_months'] : null,
                    'carry_forward_expiry_date' => filled($rule['carry_forward_expiry_date'] ?? null) ? $rule['carry_forward_expiry_date'] : null,
                    'effective_from' => filled($rule['effective_from'] ?? null) ? $rule['effective_from'] : null,
                    'effective_to' => filled($rule['effective_to'] ?? null) ? $rule['effective_to'] : null,
                ];
            })
            ->all();

        $this->merge(['rules' => $rules]);
    }
}
