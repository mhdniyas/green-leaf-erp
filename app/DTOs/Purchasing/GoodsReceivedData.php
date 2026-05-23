<?php

declare(strict_types=1);

namespace App\DTOs\Purchasing;

use Illuminate\Http\Request;

final readonly class GoodsReceivedData
{
    /**
     * @param  array<int, array{purchase_order_item_id: ?int, product_id: int, received_qty: float}>  $items
     */
    public function __construct(
        public int $purchaseOrderId,
        public string $receivedAt,
        public float $transportCost,
        public float $labourCost,
        public ?string $notes,
        public array $items,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $items = [];
        $reqItems = $request->input('items', []);

        foreach ($reqItems as $item) {
            $items[] = [
                'purchase_order_item_id' => isset($item['purchase_order_item_id']) ? (int) $item['purchase_order_item_id'] : null,
                'product_id' => (int) ($item['product_id'] ?? 0),
                'received_qty' => (float) ($item['received_qty'] ?? 0.000),
            ];
        }

        return new self(
            purchaseOrderId: (int) $request->input('purchase_order_id'),
            receivedAt: $request->string('received_at')->toString() ?: now()->format('Y-m-d'),
            transportCost: (float) $request->input('transport_cost', 0.00),
            labourCost: (float) $request->input('labour_cost', 0.00),
            notes: $request->string('notes')->toString() ?: null,
            items: $items,
        );
    }

    public function toArray(): array
    {
        return [
            'purchase_order_id' => $this->purchaseOrderId,
            'received_at' => $this->receivedAt,
            'transport_cost' => $this->transportCost,
            'labour_cost' => $this->labourCost,
            'notes' => $this->notes,
        ];
    }
}
