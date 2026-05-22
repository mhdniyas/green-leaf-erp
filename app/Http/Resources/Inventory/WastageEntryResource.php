<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WastageEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grade' => $this->grade?->value,
            'grade_label' => $this->grade?->label(),
            'quantity' => (float) $this->quantity,
            'cost_per_kg' => (float) $this->cost_per_kg,
            'total_cost' => $this->total_cost,
            'reason' => $this->reason?->value,
            'reason_label' => $this->reason?->label(),
            'wastage_date' => $this->wastage_date?->toDateString(),
            'notes' => $this->notes,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
            'batch' => $this->whenLoaded('batch', fn () => $this->batch ? [
                'id' => $this->batch->id,
                'reference' => $this->batch->reference,
            ] : null),
            'recorded_by' => $this->whenLoaded('recordedBy', fn () => [
                'id' => $this->recordedBy->id,
                'name' => $this->recordedBy->name,
            ]),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
