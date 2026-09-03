<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Employee::class);
    }

    protected function prepareForValidation(): void
    {
        if (blank($this->input('employee_code'))) {
            $this->merge([
                'employee_code' => Employee::generateNextCode(),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'employee_code' => ['required', 'string', 'max:50', 'unique:employees,employee_code'],
            'phone' => ['required', 'string', 'max:30'],
            'alternate_phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'photo_data_url' => ['nullable', 'string'],
            'id_front_data_url' => ['nullable', 'string'],
            'id_back_data_url' => ['nullable', 'string'],
            // Government ID Validation
            'id_type' => ['required', 'string', Rule::in(['aadhaar', 'passport', 'driving_licence', 'voter_id', 'pan', 'other'])],
            'other_id_type' => ['nullable', 'required_if:id_type,other', 'string', 'max:50'],
            'id_number' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail): void {
                    if (blank($value)) {
                        return;
                    }
                    $idType = $this->input('id_type');
                    if ($idType === 'aadhaar' && ! preg_match('/^[2-9]{1}[0-9]{11}$/', (string) $value)) {
                        $fail('Aadhaar number must be a valid 12-digit number starting with 2-9.');
                    }
                    if ($idType === 'pan' && ! preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/i', (string) $value)) {
                        $fail('PAN number must be a valid 10-character alphanumeric format (e.g. ABCDE1234F).');
                    }
                },
            ],
            'id_front' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'id_front_data_url' => ['nullable', 'string'],
            'id_back' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'id_back_data_url' => ['nullable', 'string'],
            'address' => ['nullable', 'string', 'max:1000'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'default_shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'employee_category_id' => ['required', 'integer', 'exists:employee_categories,id'],
            'staff_area' => ['required', Rule::in(['office', 'shop'])],
            'employment_status' => ['required', Rule::in(['active', 'inactive'])],
            'joined_on' => ['nullable', 'date'],
            'salary_type' => ['required', Rule::in(['monthly', 'daily_wage'])],
            'monthly_salary' => ['nullable', 'required_if:salary_type,monthly', 'numeric', 'min:0'],
            'daily_wage' => ['nullable', 'required_if:salary_type,daily_wage', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Primary phone number is required.',
            'alternate_phone.required' => 'Emergency contact number is required.',
        ];
    }
}
