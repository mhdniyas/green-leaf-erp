<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Warehouse;

use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveWarehouseLoadoutItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $warehouse = $this->route('warehouse');

        return $warehouse instanceof Warehouse
            && $this->user()?->canAccessWarehouse($warehouse) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.requisition_item_id' => [
                'required',
                'integer',
                'distinct:strict',
                Rule::exists('shop_order_items', 'id')->whereNull('deleted_at'),
            ],
            'items.*.loaded_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'items.*.loaded_order_unit_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'items.*.is_not_available' => ['required', 'boolean'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($this->input('items', []) as $index => $item) {
                    if (! (bool) ($item['is_not_available'] ?? false)
                        && ! array_key_exists('loaded_qty', $item)) {
                        $validator->errors()->add(
                            "items.{$index}.loaded_qty",
                            'The loaded quantity field is required unless the item is not available.'
                        );
                    }
                }
            },
        ];
    }
}
