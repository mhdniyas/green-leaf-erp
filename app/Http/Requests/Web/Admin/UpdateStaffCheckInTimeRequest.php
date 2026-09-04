<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffCheckInTimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.employee.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'shop_attendance_cutoff_time' => ['required', 'date_format:H:i'],
        ];
    }

    public function attributes(): array
    {
        return [
            'shop_attendance_cutoff_time' => 'check-in time',
        ];
    }
}
