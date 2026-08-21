<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashbook;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AcceptPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isMainAdmin();
    }

    public function rules(): array
    {
        return [
            'shop_id' => 'required|integer',
            'business_date' => 'required|date_format:Y-m-d',
            'company_account_id' => 'nullable|integer',
            'payment_method' => 'nullable|string|in:cash,online_upi,cheque,bank_transfer,other',
            'payment_reference' => 'nullable|string|max:120',
            'cheque_bank_name' => 'nullable|required_if:payment_method,cheque|string|max:120',
            'cheque_date' => 'nullable|required_if:payment_method,cheque|date_format:Y-m-d',
            'category_code' => 'nullable|string|max:100',
            'settle_amount' => 'nullable|numeric|min:0',
            'petty_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:255',
        ];
    }
}
