<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashbook;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShopPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in(['cash', 'online_upi', 'cheque'])],
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'cheque_bank_name' => ['nullable', 'required_if:payment_method,cheque', 'string', 'max:120'],
            'cheque_date' => ['nullable', 'required_if:payment_method,cheque', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'request_uuid' => ['required', 'uuid'],
        ];
    }
}
