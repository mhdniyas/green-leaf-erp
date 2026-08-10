<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Purchaser;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PurchaserReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'status' => ['nullable', Rule::in(['all', 'finalized', 'payment_pending', 'paid'])],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('date_from') || ! $this->filled('date_to')) {
                    return;
                }

                $days = Carbon::parse((string) $this->input('date_from'))
                    ->diffInDays(Carbon::parse((string) $this->input('date_to')));

                if ($days > 366) {
                    $validator->errors()->add('date_to', 'The report period may not exceed 366 days.');
                }
            },
        ];
    }
}
