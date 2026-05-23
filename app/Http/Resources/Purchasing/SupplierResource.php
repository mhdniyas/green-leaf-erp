<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchasing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'contact' => $this->contact,
            'payment_terms' => $this->payment_terms,
            'quality_score' => $this->quality_score,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
