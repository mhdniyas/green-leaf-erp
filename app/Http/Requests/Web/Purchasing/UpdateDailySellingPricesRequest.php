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
            'reason' => ['nullable', 'string', 'max:1000'],
            'prices' => ['required', 'array', 'min:1'],
            'prices.*' => ['array'],
            'prices.*.price_a' => ['required', 'numeric', 'min:0.01'],
            'prices.*.price_b' => ['required', 'numeric', 'min:0.01'],
            'prices.*.price_c' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
