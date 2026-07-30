<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\ShopOwner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShopInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('shop') ?? false;
    }

    public function rules(): array
    {
        return [
            'invoice_id' => ['nullable', 'integer', 'exists:shop_invoices,id'],
            'amount_mode' => ['required', 'string', Rule::in(['balance_due', 'custom', 'shop_balance'])],
            'payment_method' => ['nullable', 'string', Rule::in(['cash', 'online_upi', 'cheque'])],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'payment_date' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'shop_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
