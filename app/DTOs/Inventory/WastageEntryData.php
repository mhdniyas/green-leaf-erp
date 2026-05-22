<?php

declare(strict_types=1);

namespace App\DTOs\Inventory;

use App\Enums\Inventory\WastageReason;
use Illuminate\Http\Request;

final readonly class WastageEntryData
{
    public function __construct(
        public int $productId,
        public ?int $batchId,
        public string $grade,
        public float $quantity,
        public float $costPerKg,
        public WastageReason $reason,
        public string $wastageDate,
        public ?string $notes,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            productId: (int) $request->input('product_id'),
            batchId: $request->input('batch_id') ? (int) $request->input('batch_id') : null,
            grade: $request->string('grade')->toString(),
            quantity: (float) $request->input('quantity'),
            costPerKg: (float) $request->input('cost_per_kg'),
            reason: WastageReason::from($request->string('reason')->toString()),
            wastageDate: $request->string('wastage_date')->toString(),
            notes: $request->string('notes')->toString() ?: null,
        );
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'batch_id' => $this->batchId,
            'grade' => $this->grade,
            'quantity' => $this->quantity,
            'cost_per_kg' => $this->costPerKg,
            'reason' => $this->reason->value,
            'wastage_date' => $this->wastageDate,
            'notes' => $this->notes,
        ];
    }
}
