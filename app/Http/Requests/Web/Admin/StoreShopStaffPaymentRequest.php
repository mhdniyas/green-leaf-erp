<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Models\PayrollRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShopStaffPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PayrollRun::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'payroll_run_item_id' => ['required', 'integer', 'exists:payroll_run_items,id'],
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'paid_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_type' => ['required', 'string', Rule::in(['salary', 'advance'])],
            'fund_source' => ['required', 'string', Rule::in(['petty_cash', 'sales_income'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
