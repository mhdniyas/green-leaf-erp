<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoodsReceivedRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(['admin', 'purchase', 'purchaser', 'warehouse_receiver'])
            || $user->can('purchasing.grn.create')
            || $user->can('warehouse.receive.view');
    }

    public function rules(): array
    {
        return [
            'client_submission_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'destination_shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'received_at' => ['required', 'date'],
            'transport_cost' => ['sometimes', 'numeric', 'min:0'],
            'labour_cost' => ['sometimes', 'numeric', 'min:0'],
            'bill_status' => ['sometimes', 'string', 'in:bill_available,bill_pending'],
            'bill_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.received_qty' => ['required', 'numeric', 'min:0.001'],
            'items.*.received_unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'advance_matches' => ['sometimes', 'array'],
            'advance_matches.*.advance_goods_received_id' => ['required', 'integer', 'exists:goods_received,id'],
            'advance_matches.*.advance_goods_received_item_id' => ['nullable', 'integer', 'exists:goods_received_items,id'],
            'advance_matches.*.purchase_order_item_id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'advance_matches.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'advance_matches.*.matched_qty' => ['required', 'numeric', 'min:0.001'],
            'advance_matches.*.unit' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }
}
