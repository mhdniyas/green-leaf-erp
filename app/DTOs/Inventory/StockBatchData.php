<?php

declare(strict_types=1);

namespace App\DTOs\Inventory;

use Illuminate\Http\Request;

final readonly class StockBatchData
{
    public function __construct(
        public int $productId,
        public string $receivedAt,
        public float $totalKg,
        public float $costPerKg,
        public float $transportCost,
        public float $labourCost,
        public ?string $notes,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            productId: (int) $request->input('product_id'),
            receivedAt: $request->string('received_at')->toString(),
            totalKg: (float) $request->input('total_kg'),
            costPerKg: (float) $request->input('cost_per_kg'),
            transportCost: (float) $request->input('transport_cost', 0),
            labourCost: (float) $request->input('labour_cost', 0),
            notes: $request->string('notes')->toString() ?: null,
        );
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'received_at' => $this->receivedAt,
            'total_kg' => $this->totalKg,
            'cost_per_kg' => $this->costPerKg,
            'transport_cost' => $this->transportCost,
            'labour_cost' => $this->labourCost,
            'notes' => $this->notes,
        ];
    }
}
