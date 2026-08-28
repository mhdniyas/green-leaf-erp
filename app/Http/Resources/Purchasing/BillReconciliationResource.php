<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchasing;

use App\Models\BillReconciliation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BillReconciliation
 */
class BillReconciliationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'goods_received_id' => $this->goods_received_id,
            'warehouse_id' => $this->warehouse_id,
            'source_type' => $this->source_type, // 'normal', 'advance', 'mixed'
            'source_type_label' => strtoupper($this->source_type),
            'status' => $this->status,
            'total_bill_base_qty' => (float) $this->total_bill_base_qty,
            'total_matched_base_qty' => (float) $this->total_matched_base_qty,
            'total_new_receive_base_qty' => (float) $this->total_new_receive_base_qty,
            'confirmed_by' => $this->confirmed_by,
            'confirmed_by_name' => $this->confirmedByUser?->name ?? 'Warehouse Receiver',
            'confirmed_at' => $this->confirmed_at?->toDateTimeString(),
            'client_submission_id' => $this->client_submission_id,
            'notes' => $this->notes,
            'lines' => BillReconciliationLineResource::collection($this->whenLoaded('lines')),
            'advance_matches' => AdvanceReceiveMatchResource::collection($this->whenLoaded('advanceMatches')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
