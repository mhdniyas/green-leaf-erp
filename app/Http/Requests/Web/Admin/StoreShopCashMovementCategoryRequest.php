<?php

namespace App\Http\Requests\Web\Admin;

use App\Support\AccountingAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShopCashMovementCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return AccountingAccess::canManageOwnedShops($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('shop_cash_movement_categories', 'name')],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
