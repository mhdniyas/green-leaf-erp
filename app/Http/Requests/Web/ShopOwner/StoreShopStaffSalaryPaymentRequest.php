<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\ShopOwner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShopStaffSalaryPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('shop') && $this->user()?->can('hr.attendance.mark-owned-shop');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'paid_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'fund_source' => ['required', 'string', Rule::in(['petty_cash', 'sales_income'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
