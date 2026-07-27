<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inventory.product.create');
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'default_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku', 'regex:/^[A-Za-z0-9\-_]+$/'],
            'unit' => ['required', 'string', 'in:kg,box,bunch,piece,bag'],
            'description' => ['nullable', 'string', 'max:1000'],
            'buffer_qty' => ['nullable', 'numeric', 'min:0'],
            'carryover_enabled' => ['sometimes', 'boolean'],
            'image_data' => ['nullable', 'string'],
        ];
    }
}
