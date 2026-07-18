<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Support\AccountingAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShopAccountingCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return AccountingAccess::canManageOwnedShops($this->user());
    }

    public function rules(): array
    {
        $shop = $this->route('shop');

        return [
            'scope' => ['required', 'string', Rule::in(['global', 'shop'])],
            'type' => ['required', 'string', Rule::in(['income', 'expense'])],
            'cash_effect' => ['nullable', 'boolean'],
            'purpose' => ['nullable', 'string', Rule::in(['custom', 'sales_cash', 'sales_non_cash', 'shop_cash_credit', 'cash_sent_company', 'staff_salary', 'staff_advance'])],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'shop_id' => ['nullable', 'integer', Rule::in([$shop?->id])],
        ];
    }
}
