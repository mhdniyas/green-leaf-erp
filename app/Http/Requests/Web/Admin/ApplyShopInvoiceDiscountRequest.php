<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Models\ShopInvoice;
use App\Support\AccountingAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApplyShopInvoiceDiscountRequest extends FormRequest
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
            'discount_total' => ['required', 'numeric', 'min:0'],
            'discount_note' => ['required', 'string', 'max:1000'],
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

                $discountTotal = round((float) $this->input('discount_total'), 2);
                $grossPayable = round(max(
                    0,
                    (float) $invoice->subtotal - (float) $invoice->shortage_total + (float) $invoice->excess_total
                ), 2);
                $finalPayable = round(max(0, $grossPayable - $discountTotal), 2);
                $paidAmount = round((float) $invoice->paid_amount, 2);

                if ($discountTotal > $grossPayable) {
                    $validator->errors()->add('discount_total', 'Discount cannot be greater than the bill payable amount.');
                }

                if ($paidAmount > $finalPayable) {
                    $validator->errors()->add('discount_total', 'Discount cannot reduce the bill below the amount already collected.');
                }
            },
        ];
    }
}
