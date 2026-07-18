<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Support\AccountingAccess;
use Illuminate\Foundation\Http\FormRequest;

class ApproveShopInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return AccountingAccess::canViewDashboard($this->user());
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
