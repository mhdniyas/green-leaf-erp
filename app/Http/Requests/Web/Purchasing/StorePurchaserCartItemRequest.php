<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Purchasing;

use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaserCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('purchaser');
    }

    public function rules(PurchaserBusinessDayService $businessDayService): array
    {
        return [
            'business_date' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail) use ($businessDayService): void {
                    if (! $businessDayService->isSelectableDate((string) $value)) {
                        $fail('The selected business date is not available for purchaser flow.');
                    }
                },
            ],
            'product_id' => ['required', 'exists:products,id'],
            'cart_id' => ['nullable', 'exists:purchaser_carts,id'],
            'purchase_grade' => ['sometimes', 'string', 'in:A,B'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => [Rule::requiredIf(fn (): bool => ($this->input('purchase_grade', 'A') === 'A')), 'nullable', 'numeric', 'min:0.01'],
            'purchase_source' => ['nullable', 'string', 'in:shop_order,green_leaf_direct_purchase,mixed'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
