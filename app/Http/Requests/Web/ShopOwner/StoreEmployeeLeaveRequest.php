<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\ShopOwner;

use App\Models\EmployeeLeaveRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', EmployeeLeaveRequest::class);
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'min:3'],
        ];
    }
}
