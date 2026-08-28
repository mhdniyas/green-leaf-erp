<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchasing;

use App\Services\Purchasing\WarehouseReceiptStateResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $itemCount = (int) ($this->items_count ?? 0);

        return [
            ...app(WarehouseReceiptStateResolver::class)->forOrder($this->resource),
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'po_number' => $this->po_number,
            'status' => $this->status->value ?? $this->status,
            'order_date' => $this->order_date?->toDateString(),
            'business_date' => $this->order_date?->toDateString(),
            'created_by' => $this->created_by,
            'supplier_name' => $this->supplier?->name,
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier?->id,
                'name' => $this->supplier?->name,
            ]),
            'item_count' => $itemCount,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
