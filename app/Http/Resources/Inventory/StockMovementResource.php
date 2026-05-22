<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grade' => $this->grade?->value,
            'grade_label' => $this->grade?->label(),
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'quantity' => (float) $this->quantity,
            'cost_per_unit' => (float) $this->cost_per_unit,
            'total_value' => $this->total_value,
            'notes' => $this->notes,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
            'batch' => $this->whenLoaded('batch', fn () => [
                'id' => $this->batch->id,
                'reference' => $this->batch->reference,
            ]),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
