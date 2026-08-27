<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchasing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceivedItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'goods_received_id' => $this->goods_received_id,
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name ?? 'Product #'.$this->product_id,
            'product_sku' => $this->product?->sku,
            'product_unit' => $this->product?->unit ?? $this->received_unit ?? 'KG',
            'unit' => $this->product?->unit ?? $this->received_unit ?? 'KG',
            'received_qty' => (float) $this->received_qty,
            'variance' => (float) $this->variance,
            'product' => $this->whenLoaded('product'),
            'purchase_order_item' => new PurchaseOrderItemResource($this->whenLoaded('purchaseOrderItem')),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
