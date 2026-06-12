<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Purchasing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePendingDailyPriceApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyRole(['purchase', 'admin']);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.price_a' => ['required', 'numeric', 'min:0'],
            'prices.*.price_b' => ['required', 'numeric', 'min:0'],
            'prices.*.price_c' => ['required', 'numeric', 'min:0'],
        ];
    }
}
