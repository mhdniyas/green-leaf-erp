<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchasing;

use App\Services\Purchasing\WarehouseReceiptStateResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class GoodsReceivedSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $receivedDate = $this->received_at ? Carbon::parse($this->received_at) : null;
        $receiptState = app(WarehouseReceiptStateResolver::class)->forReceipt($this->resource);
        $source = $this->sourceLabel();

        return [
            ...$receiptState,
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'destination_shop_id' => $this->destination_shop_id ?? $this->purchaseOrder?->destination_shop_id,
            'warehouse_id' => $this->warehouse_id,
            'grn_number' => $this->grn_number,
            'status' => $this->status,
            'source' => $source,
            'bill_number' => $this->bill_number,
            'received_by' => $this->received_by,
            'received_by_name' => $this->receivedBy?->name ?? 'Receiver',
            'age_days' => $receivedDate ? $receivedDate->diffInDays(now()) : 0,
            'supplier_name' => $this->purchaseOrder?->supplier?->name ?? ($source === 'ADVANCE' ? 'Direct Advance Receipt' : 'Vendor'),
            'supplier_id' => $this->purchaseOrder?->supplier_id,
            'destination_shop_name' => $this->destinationShop?->name ?? $this->purchaseOrder?->destinationShop?->name ?? 'Central Warehouse',
            'received_at' => $this->received_at?->toDateString(),
            'received_at_formatted' => $this->approved_at?->format('h:i A') ?? ($this->created_at?->format('h:i A') ?? ''),
            'total_items_count' => (int) ($this->items_count ?? ($this->relationLoaded('items') ? $this->items->count() : 0)),
            'total_received_qty' => (float) ($this->items_sum_received_qty ?? ($this->relationLoaded('items') ? $this->items->sum('received_qty') : 0.0)),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
