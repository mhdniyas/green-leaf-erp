<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Purchasing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitPurchaserCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('purchaser');
    }

    public function rules(): array
    {
        return [
            'business_date' => ['required', 'date', Rule::date()->todayOrBefore()],
            'cart_id' => ['required', 'exists:purchaser_carts,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'vendor_name' => ['nullable', 'string', 'max:255', 'required_without:supplier_id'],
            'vendor_type' => ['nullable', 'string', 'max:255'],
            'vendor_location' => ['nullable', 'string', 'max:255'],
            'vendor_mobile_number' => ['nullable', 'string', 'max:50', 'required_without:supplier_id'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'preferred_payment_method' => ['nullable', 'string', 'max:100'],
            'bill_number' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['required', 'string', 'in:Cash,Online,Credit'],
            'payment_status' => ['nullable', 'string', 'in:unpaid,partial,paid,credit_pending_approval'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_note' => ['nullable', 'string', 'max:1000'],
            'payment_details' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
