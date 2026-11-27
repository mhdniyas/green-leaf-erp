<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShopCreditNoteReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'timeframe' => ['nullable', 'string', Rule::in(['today', 'yesterday', 'this_week', 'this_month', 'prev_month', 'custom'])],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'shop_id' => ['nullable', 'integer', 'min:1', 'exists:shops,id'],
            'report_type' => ['nullable', 'string', Rule::in(['detailed', 'summary'])],
            'dataset' => ['nullable', 'string', Rule::in(['register', 'adjustments', 'reconciliation', 'daily', 'shop', 'daily_shop', 'bundle'])],
        ];
    }
}
