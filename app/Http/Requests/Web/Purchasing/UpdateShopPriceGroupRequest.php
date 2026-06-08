<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Purchasing;

use App\Models\ShopPriceGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShopPriceGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('purchase') || $this->user()?->hasRole('admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'relationship_type' => ['required', Rule::in(array_keys(ShopPriceGroup::relationshipTypes()))],
            'name' => ['required', 'string', 'max:50'],
            'default_margin_percent' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
