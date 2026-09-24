<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MonthlyReportDrilldownRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'metric' => ['required', 'string', 'max:50'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'purchaser_id' => ['nullable', 'integer', 'exists:users,id'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'period_mode' => ['nullable', 'string', 'in:month,day,custom'],
            'month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
