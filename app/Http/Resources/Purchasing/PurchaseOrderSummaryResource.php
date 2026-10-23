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
        $itemCount = (int) ($this->items_count ?? ($this->relationLoaded('items') ? $this->items->count() : 0));
        $firstGrn = $this->relationLoaded('goodsReceiveds') ? $this->goodsReceiveds->first() : null;
        $billNumber = $firstGrn?->bill_number ?? ($firstGrn?->relationLoaded('purchaseInvoices') ? $firstGrn->purchaseInvoices->first()?->invoice_number : null);
        $productNames = $this->relationLoaded('items')
            ? $this->items->map(fn ($it) => $it->product?->name)->filter()->unique()->values()->all()
            : [];

        return [
            ...($orderState = app(WarehouseReceiptStateResolver::class)->forOrder($this->resource)),
            'warehouse_receipt_status' => $orderState['receipt_status'] ?? 'pending',
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'po_number' => $this->po_number,
            'bill_number' => $billNumber,
            'grn_number' => $firstGrn?->grn_number,
            'status' => $this->status->value ?? $this->status,
            'purchase_grade' => $this->purchase_grade ?? 'A',
            'fulfillment_type' => $this->fulfillment_type ?? 'warehouse',
            'destination_shop_id' => $this->destination_shop_id,
            'order_date' => $this->order_date?->toDateString(),
            'business_date' => $this->order_date?->toDateString(),
            'created_by' => $this->created_by,
            'notes' => $this->notes,
            'total_amount' => (float) $this->total_amount,
            'supplier_name' => $this->supplier?->name,
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier?->id,
                'name' => $this->supplier?->name,
            ]),
            'item_count' => $itemCount,
            'product_names' => $productNames,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
