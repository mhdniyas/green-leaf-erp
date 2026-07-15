<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Support\AccountingAccess;
use Illuminate\Foundation\Http\FormRequest;

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
            'type' => ['required', 'string', 'in:in,out'],
            'is_petty_cash' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
            'business_date' => ['required', 'date'],
        ];
    }
}
