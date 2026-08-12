<?php

namespace App\Http\Requests\Web\Purchasing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseGradePricesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyRole(['purchaser', 'purchase', 'admin']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'business_date' => ['required', 'date'],
            'prices' => ['required', 'array'],
            'prices.*' => ['array'],
            'prices.*.A' => ['nullable', 'numeric', 'min:0.0001'],
            'prices.*.B' => ['nullable', 'numeric', 'min:0.0001'],
        ];
    }
}
