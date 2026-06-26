<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShopAccountingCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && (
                $this->user()->hasRole('admin')
                || $this->user()->can('admin.user.view')
                || $this->user()->can('admin.daily-progress.view')
                || $this->user()->can('admin.activity-log.view')
            );
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
