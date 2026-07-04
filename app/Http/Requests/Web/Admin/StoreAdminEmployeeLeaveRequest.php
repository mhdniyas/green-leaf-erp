<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Models\EmployeeLeaveRequest;
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
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'min:3'],
        ];
    }
}
