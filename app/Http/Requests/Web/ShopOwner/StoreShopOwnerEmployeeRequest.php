<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\ShopOwner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShopOwnerEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('shop') && $this->user()?->can('hr.attendance.mark-owned-shop');
    }

    protected function prepareForValidation(): void
    {
        $salaryType = $this->input('salary_type') === 'daily_wage' ? 'daily_wage' : 'monthly';

        $this->merge([
            'salary_type' => $salaryType,
            'monthly_salary' => $salaryType === 'monthly' ? $this->input('monthly_salary') : null,
            'daily_wage' => $salaryType === 'daily_wage' ? $this->input('daily_wage') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'alternate_phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'joined_on' => ['required', 'date'],
            'id_type' => ['required', Rule::in(['aadhaar', 'passport', 'driving_licence', 'voter_id', 'pan', 'other'])],
            'other_id_type' => ['nullable', 'required_if:id_type,other', 'string', 'max:50'],
            'id_number' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:1000'],
            'salary_type' => ['required', Rule::in(['monthly', 'daily_wage'])],
            'monthly_salary' => ['exclude_unless:salary_type,monthly', 'required', 'numeric', 'min:0'],
            'daily_wage' => ['exclude_unless:salary_type,daily_wage', 'required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photo_data_url' => ['required', 'string'],
            'id_front_data_url' => ['required', 'string'],
            'id_back_data_url' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Primary phone number is required.',
            'alternate_phone.required' => 'Emergency contact number is required.',
            'salary_type.required' => 'Salary type is required.',
            'monthly_salary.required_if' => 'Monthly salary is required.',
            'daily_wage.required_if' => 'Daily wage is required.',
        ];
    }
}
