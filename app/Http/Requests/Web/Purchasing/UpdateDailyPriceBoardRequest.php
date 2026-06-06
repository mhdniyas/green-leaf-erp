<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDailyPriceBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('purchase') || $this->user()->hasRole('admin');
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'base_prices' => ['array'],
            'base_prices.*' => ['nullable', 'numeric', 'min:0'],
            'daily_prices' => ['array'],
            'daily_prices.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
