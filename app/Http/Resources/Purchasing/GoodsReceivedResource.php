<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchasing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class GoodsReceivedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isBillPending = $this->isBillPending();
        $receivedDate = $this->received_at ? Carbon::parse($this->received_at) : null;

        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'destination_shop_id' => $this->destination_shop_id ?? $this->purchaseOrder?->destination_shop_id,
            'warehouse_id' => $this->warehouse_id ?? $this->destination_shop_id ?? $this->purchaseOrder?->destination_shop_id,
            'grn_number' => $this->grn_number,
            'status' => $this->status,
            'source' => $this->sourceLabel(),
            'bill_status' => $this->bill_status ?: ($isBillPending ? 'bill_pending' : 'bill_available'),
            'bill_number' => $this->bill_number,
            'is_bill_pending' => $isBillPending,
            'status_label' => $isBillPending ? 'BILL PENDING' : 'RECEIVED WITH BILL',
            'inventory_posted' => true,
            'received_by' => $this->received_by,
            'received_by_name' => $this->receivedBy?->name ?? 'Receiver',
            'updated_by' => $this->updated_by,
            'updated_by_name' => $this->updatedBy?->name,
            'matched_by' => $this->matched_by,
            'matched_by_name' => $this->matchedBy?->name,
            'matched_at' => $this->matched_at?->toDateTimeString(),
            'age_days' => $receivedDate ? $receivedDate->diffInDays(now()) : 0,
            'supplier_name' => $this->purchaseOrder?->supplier?->name ?? 'Direct Advance Receipt',
            'supplier_id' => $this->purchaseOrder?->supplier_id,
            'destination_shop_name' => $this->destinationShop?->name ?? $this->purchaseOrder?->destinationShop?->name ?? 'Central Warehouse',
            'received_at' => $this->received_at?->toDateString(),
            'transport_cost' => (float) $this->transport_cost,
            'labour_cost' => (float) $this->labour_cost,
            'notes' => $this->notes,
            'total_items_count' => $this->relationLoaded('items') ? $this->items->count() : 0,
            'total_received_qty' => $this->relationLoaded('items') ? (float) $this->items->sum('received_qty') : 0.0,
            'purchase_order' => new PurchaseOrderResource($this->whenLoaded('purchaseOrder')),
            'items' => GoodsReceivedItemResource::collection($this->whenLoaded('items')),
            'invoices' => PurchaseInvoiceResource::collection($this->whenLoaded('purchaseInvoices')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
