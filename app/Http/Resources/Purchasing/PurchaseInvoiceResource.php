<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchasing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'goods_received_id' => $this->goods_received_id,
            'supplier_id' => $this->supplier_id,
            'invoice_number' => $this->invoice_number,
            'amount' => (float) $this->amount,
            'status' => $this->status->value ?? $this->status,
            'notes' => $this->notes,
            'goods_received' => new GoodsReceivedResource($this->whenLoaded('goodsReceived')),
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
