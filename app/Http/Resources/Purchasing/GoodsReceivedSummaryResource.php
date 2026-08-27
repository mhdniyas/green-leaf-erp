<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchasing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class GoodsReceivedSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $receivedDate = $this->received_at ? Carbon::parse($this->received_at) : null;

        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'destination_shop_id' => $this->destination_shop_id ?? $this->purchaseOrder?->destination_shop_id,
            'warehouse_id' => $this->warehouse_id,
            'grn_number' => $this->grn_number,
            'status' => $this->status,
            'bill_status' => $this->bill_status ?: 'bill_pending',
            'bill_number' => $this->bill_number,
            'is_bill_pending' => true,
            'status_label' => 'BILL PENDING',
            'received_by' => $this->received_by,
            'received_by_name' => $this->receivedBy?->name ?? 'Receiver',
            'age_days' => $receivedDate ? $receivedDate->diffInDays(now()) : 0,
            'supplier_name' => $this->purchaseOrder?->supplier?->name ?? 'Direct Advance Receipt',
            'supplier_id' => $this->purchaseOrder?->supplier_id,
            'destination_shop_name' => $this->destinationShop?->name ?? $this->purchaseOrder?->destinationShop?->name ?? 'Central Warehouse',
            'received_at' => $this->received_at?->toDateString(),
            'total_items_count' => (int) ($this->items_count ?? 0),
            'total_received_qty' => (float) ($this->items_sum_received_qty ?? 0),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
