<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Inventory;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inventory.product.update');
    }

    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product->id), 'regex:/^[A-Za-z0-9\-_]+$/'],
            'unit' => ['required', 'string', 'in:kg,box,bunch,piece,bag,packet,crate,tray,roll'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'image_data' => ['nullable', 'string'],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }
}
