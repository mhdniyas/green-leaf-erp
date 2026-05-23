<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchasing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceivedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'grn_number' => $this->grn_number,
            'received_by' => $this->received_by,
            'received_at' => $this->received_at?->toDateString(),
            'transport_cost' => (float) $this->transport_cost,
            'labour_cost' => (float) $this->labour_cost,
            'notes' => $this->notes,
            'purchase_order' => new PurchaseOrderResource($this->whenLoaded('purchaseOrder')),
            'items' => GoodsReceivedItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
