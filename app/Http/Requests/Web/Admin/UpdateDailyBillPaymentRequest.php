<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Models\ShopInvoice;
use App\Support\AccountingAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDailyBillPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return AccountingAccess::canManageOwnedShops($this->user());
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

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $invoice = $this->route('invoice');

                if (! $invoice instanceof ShopInvoice || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $discountTotal = round((float) $this->input('discount_total', $invoice->discount_total), 2);
                $paidAmount = round((float) $this->input('paid_amount'), 2);
                $currentPaidAmount = round((float) $invoice->paid_amount, 2);
                $payableAmount = round(max(0, (float) $invoice->subtotal - (float) $invoice->shortage_total - $discountTotal), 2);

                if ($paidAmount <= $currentPaidAmount) {
                    $validator->errors()->add('paid_amount', 'Paid amount must be greater than the current collected amount.');
                }

                if ($paidAmount > $payableAmount) {
                    $validator->errors()->add('paid_amount', 'Paid amount cannot be greater than the bill payable amount.');
                }
            },
        ];
    }
}
