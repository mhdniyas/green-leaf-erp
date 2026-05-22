<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'received_at' => $this->received_at?->toDateString(),
            'total_kg' => (float) $this->total_kg,
            'cost_per_kg' => (float) $this->cost_per_kg,
            'transport_cost' => (float) $this->transport_cost,
            'labour_cost' => (float) $this->labour_cost,
            'total_landed_cost' => $this->total_landed_cost,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'notes' => $this->notes,
            'sorted_at' => $this->sorted_at?->toDateTimeString(),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
                'unit' => $this->product->unit,
            ]),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
