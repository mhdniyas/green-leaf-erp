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
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'shop_id' => ['nullable', 'integer', Rule::in([$shop?->id])],
        ];
    }
}
