<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\ShopOwner;

use App\Models\EmployeeAttendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertOwnedShopAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', EmployeeAttendance::class);
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'attendance_date' => ['required', 'date'],
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'status' => ['required', Rule::in(['present', 'half_day', 'absent', 'leave'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
