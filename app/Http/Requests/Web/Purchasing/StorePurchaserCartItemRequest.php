<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Purchasing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaserCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('purchaser');
    }

    public function rules(): array
    {
        return [
            'business_date' => ['required', 'date', Rule::date()->todayOrBefore()],
            'product_id' => ['required', 'exists:products,id'],
            'cart_id' => ['nullable', 'exists:purchaser_carts,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
