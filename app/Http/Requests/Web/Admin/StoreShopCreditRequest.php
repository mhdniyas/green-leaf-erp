<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Models\ShopCashMovementCategory;
use App\Support\AccountingAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShopCreditRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'gt:0'],
            'type' => ['nullable', 'string', 'in:in'],
            'is_petty_cash' => ['nullable', 'boolean'],
            'shop_cash_movement_category_id' => [
                'nullable',
                'integer',
                Rule::exists('shop_cash_movement_categories', 'id')
                    ->where('is_active', true)
                    ->whereIn('name', [ShopCashMovementCategory::LOAN, ShopCashMovementCategory::ADVANCE_LOAN_FOR_SALARY]),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'business_date' => ['required', 'date'],
        ];
    }
}
