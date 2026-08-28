<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchasing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdvanceReceiveMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'advance_goods_received_id' => $this->advance_goods_received_id,
            'advance_grn_number' => $this->advanceGoodsReceived?->grn_number,
            'advance_received_at' => $this->advanceGoodsReceived?->received_at?->toDateString(),
            'advance_goods_received_item_id' => $this->advance_goods_received_item_id,
            'advance_stock_batch_id' => $this->advance_stock_batch_id,
            'advance_stock_batch_reference' => $this->advanceStockBatch?->reference,
            'bill_goods_received_id' => $this->bill_goods_received_id,
            'bill_goods_received_item_id' => $this->bill_goods_received_item_id,
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
            'product_sku' => $this->product?->sku,
            'matched_qty' => (float) $this->matched_qty,
            'matched_unit' => $this->matched_unit,
            'base_qty' => (float) $this->base_qty,
            'conversion_to_base' => (float) $this->conversion_to_base,
            'confirmed_by' => $this->confirmed_by,
            'confirmed_by_name' => $this->confirmedBy?->name,
            'confirmed_at' => $this->confirmed_at?->toDateTimeString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
