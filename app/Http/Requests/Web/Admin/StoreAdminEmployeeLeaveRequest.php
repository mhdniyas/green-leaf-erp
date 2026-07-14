<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Models\EmployeeLeaveRequest;
use App\Models\LeaveType;
use Illuminate\Foundation\Http\FormRequest;

class StoreAdminEmployeeLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', EmployeeLeaveRequest::class);
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['nullable', 'integer', 'exists:leave_types,id'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'min:3'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('leave_type_id')) {
            return;
        }

        $leaveType = LeaveType::query()->firstOrCreate(
            ['code' => LeaveType::CODE_PAID],
            [
                'name' => 'Paid Leave',
                'is_paid' => true,
                'is_active' => true,
                'carry_forward_allowed' => true,
            ],
        );

        $this->merge([
            'leave_type_id' => $leaveType->id,
        ]);
    }
}
