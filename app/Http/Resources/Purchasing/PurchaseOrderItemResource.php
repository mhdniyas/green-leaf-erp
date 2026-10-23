<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchasing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $receivedQty = (float) ($this->relationLoaded('goodsReceivedItems') ? $this->goodsReceivedItems->sum('received_qty') : ($this->goodsReceivedItems()->sum('received_qty') ?: 0.0));
        $remainingQty = max(0.0, (float) $this->quantity - $receivedQty);

        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'product_id' => $this->product_id,
            'grade' => $this->grade ?? $this->purchaseOrder?->purchase_grade ?? 'A',
            'quantity' => (float) $this->quantity,
            'packet_qty' => $this->packet_qty !== null ? (float) $this->packet_qty : null,
            'weight_per_packet' => $this->weight_per_packet !== null ? (float) $this->weight_per_packet : null,
            'actual_weight' => $this->actual_weight !== null ? (float) $this->actual_weight : null,
            'unit' => $this->purchase_unit ?: ($this->product?->unit ?? 'kg'),
            'purchase_unit' => $this->purchase_unit ?: ($this->product?->unit ?? 'kg'),
            'unit_price' => (float) $this->unit_price,
            'price_basis' => $this->price_basis ?? 'weight',
            'subtotal' => (float) $this->subtotal,
            'received_qty' => $receivedQty,
            'remaining_qty' => $remainingQty,
            'product' => $this->whenLoaded('product'),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
