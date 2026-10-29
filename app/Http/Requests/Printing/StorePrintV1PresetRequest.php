<?php

declare(strict_types=1);

namespace App\Http\Requests\Printing;

use Illuminate\Foundation\Http\FormRequest;

class StorePrintV1PresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sort.sheet.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'section' => 'required|string|in:sort-sheet,selection,segregation-grid,shop-wise-portrait',
            'settings' => 'required|array',
            'settings.warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'settings.price_group_id' => 'nullable|integer|exists:shop_price_groups,id',
            'settings.shop_id' => 'nullable|integer|exists:shops,id',
            'settings.category_ids' => 'nullable|array',
            'settings.category_ids.*' => 'integer|exists:categories,id',
            'settings.product_ids' => 'nullable|array',
            'settings.product_ids.*' => 'integer|exists:products,id',
            'settings.grades' => 'nullable|array',
            'settings.grades.*' => ['string', 'in:A,B,C,U,DMG', 'not_in:D,GRADE D,d,grade d'],
            'settings.skip_empty_shops' => 'nullable|boolean',
            'settings.separate_category_pages' => 'nullable|boolean',
            'settings.page_break_category_ids' => 'nullable|array',
            'settings.page_break_category_ids.*' => 'integer|exists:categories,id',
            'settings.show_category_titles' => 'nullable|boolean',
            'settings.code_sort' => 'nullable|boolean',
            'settings.show_code' => 'nullable|boolean',
            'settings.items_per_page' => 'nullable|integer|min:1|max:200',
            'settings.orientation' => 'nullable|string|in:portrait,landscape',
            'settings.filter_scope_present' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ];
    }
}
