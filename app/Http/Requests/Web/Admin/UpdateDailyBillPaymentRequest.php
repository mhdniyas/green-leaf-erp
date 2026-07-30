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
            'payment_application' => ['nullable', 'string', 'in:invoice_pending,client_balance'],
            'payment_method' => ['nullable', 'string', 'in:cash,online_upi,cheque'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'payment_date' => ['nullable', 'date'],
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

                $submittedDiscountTotal = round((float) $this->input('discount_total', $invoice->discount_total), 2);
                $discountTotal = round((float) $invoice->discount_total, 2);
                $paidAmount = round((float) $this->input('paid_amount'), 2);
                $paymentApplication = (string) $this->input('payment_application', 'invoice_pending');

                if ($paymentApplication === 'client_balance') {
                    if ($paidAmount <= 0.0) {
                        $validator->errors()->add('paid_amount', 'Received amount must be greater than zero.');
                    }

                    return;
                }

                $currentPaidAmount = round((float) $invoice->paid_amount, 2);
                $payableAmount = round(max(
                    0,
                    (float) $invoice->subtotal - (float) $invoice->shortage_total + (float) $invoice->excess_total - $discountTotal
                ), 2);

                if ($submittedDiscountTotal !== $discountTotal) {
                    $validator->errors()->add('discount_total', 'Use the discount action before approving payment.');
                }

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
