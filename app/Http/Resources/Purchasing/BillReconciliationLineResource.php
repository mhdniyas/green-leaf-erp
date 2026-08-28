<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchasing;

use App\Models\BillReconciliationLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BillReconciliationLine
 */
class BillReconciliationLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bill_reconciliation_id' => $this->bill_reconciliation_id,
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name ?? 'Product',
            'product_sku' => $this->product?->sku ?? '',
            'bill_qty' => (float) $this->bill_qty,
            'bill_unit' => $this->bill_unit,
            'bill_base_qty' => (float) $this->bill_base_qty,
            'advance_matched_qty' => (float) $this->advance_matched_qty,
            'advance_matched_unit' => $this->advance_matched_unit,
            'advance_matched_base_qty' => (float) $this->advance_matched_base_qty,
            'new_receive_qty' => (float) $this->new_receive_qty,
            'new_receive_unit' => $this->new_receive_unit,
            'new_receive_base_qty' => (float) $this->new_receive_base_qty,
            'relevant_loadout_qty' => (float) $this->relevant_loadout_qty,
            'unbilled_loadout_qty' => (float) $this->unbilled_loadout_qty,
            'reconciled_qty' => (float) $this->reconciled_qty,
            'reconciled_base_qty' => (float) $this->reconciled_base_qty,
            'difference_status' => $this->difference_status,
            'advance_matches' => AdvanceReceiveMatchResource::collection($this->whenLoaded('advanceMatches')),
        ];
    }
}
