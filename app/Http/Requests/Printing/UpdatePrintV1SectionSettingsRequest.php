<?php

declare(strict_types=1);

namespace App\Http\Requests\Printing;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrintV1SectionSettingsRequest extends FormRequest
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
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'price_group_id' => 'nullable|integer|exists:shop_price_groups,id',
            'shop_id' => 'nullable|integer|exists:shops,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
            'grades' => 'nullable|array',
            'grades.*' => ['string', 'in:A,B,C,U,DMG', 'not_in:D,GRADE D,d,grade d'],
            'skip_empty_shops' => 'nullable|boolean',
            'separate_category_pages' => 'nullable|boolean',
            'page_break_category_ids' => 'nullable|array',
            'page_break_category_ids.*' => 'integer|exists:categories,id',
            'show_category_titles' => 'nullable|boolean',
            'code_sort' => 'nullable|boolean',
            'show_code' => 'nullable|boolean',
            'items_per_page' => 'nullable|integer|min:1|max:200',
            'orientation' => 'nullable|string|in:portrait,landscape',
            'filter_scope_present' => 'nullable|boolean',
        ];
    }
}
