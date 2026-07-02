<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('employee'));
    }

    public function rules(): array
    {
        /** @var Employee $employee */
        $employee = $this->route('employee');

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'employee_code' => ['required', 'string', 'max:50', Rule::unique('employees', 'employee_code')->ignore($employee->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'default_shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'employee_category_id' => ['required', 'integer', 'exists:employee_categories,id'],
            'staff_area' => ['required', Rule::in(['office', 'shop'])],
            'employment_status' => ['required', Rule::in(['active', 'inactive'])],
            'joined_on' => ['nullable', 'date'],
            'monthly_salary' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
