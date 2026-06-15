<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Purchasing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaserCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('purchaser');
    }

    public function rules(): array
    {
        return [
            'business_date' => ['required', 'date', Rule::date()->todayOrBefore()],
            'shop_order_item_id' => ['required', 'exists:shop_order_items,id'],
            'proposed_corrected_qty' => ['required', 'numeric', 'min:0'],
            'purchaser_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
