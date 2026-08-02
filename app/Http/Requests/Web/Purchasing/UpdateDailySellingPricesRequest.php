<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDailySellingPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('purchase') || $this->user()?->hasRole('admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'movement' => ['nullable', 'in:changed,up,down,all'],
            'sort' => ['nullable', 'in:code,name,status,movement'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'confirm_publish' => ['nullable'],
            'prices' => ['required', 'array', 'min:1'],
            'prices.*' => ['array'],
            'prices.*.price_a' => ['nullable', 'numeric', 'min:0'],
            'prices.*.price_b' => ['nullable', 'numeric', 'min:0'],
            'prices.*.price_c' => ['nullable', 'numeric', 'min:0'],
            'prices.*.price_unit' => ['nullable', 'string', 'max:20'],
        ];
    }
}
