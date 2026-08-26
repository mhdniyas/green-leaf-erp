<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchasing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceivedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isBillPending = $this->isBillPending();

        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'grn_number' => $this->grn_number,
            'status' => $this->status,
            'bill_status' => $this->bill_status ?: ($isBillPending ? 'bill_pending' : 'bill_available'),
            'bill_number' => $this->bill_number,
            'is_bill_pending' => $isBillPending,
            'status_label' => $isBillPending ? 'BILL PENDING' : 'RECEIVED WITH BILL',
            'received_by' => $this->received_by,
            'received_by_name' => $this->receivedBy?->name ?? 'Receiver',
            'updated_by' => $this->updated_by,
            'updated_by_name' => $this->updatedBy?->name,
            'supplier_name' => $this->purchaseOrder?->supplier?->name ?? 'Unknown Vendor',
            'supplier_id' => $this->purchaseOrder?->supplier_id,
            'destination_shop_name' => $this->purchaseOrder?->destinationShop?->name,
            'received_at' => $this->received_at?->toDateString(),
            'transport_cost' => (float) $this->transport_cost,
            'labour_cost' => (float) $this->labour_cost,
            'notes' => $this->notes,
            'purchase_order' => new PurchaseOrderResource($this->whenLoaded('purchaseOrder')),
            'items' => GoodsReceivedItemResource::collection($this->whenLoaded('items')),
            'invoices' => PurchaseInvoiceResource::collection($this->whenLoaded('purchaseInvoices')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
