<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MonthlyReportPeriodRequest extends FormRequest
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
            'period_mode' => ['nullable', 'string', 'in:month,day,custom'],
            'month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'section' => ['nullable', 'string', 'max:50'],
            'download' => ['nullable'],
            'heading' => ['nullable', 'string', 'max:50'],
            'source_type' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:120'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
