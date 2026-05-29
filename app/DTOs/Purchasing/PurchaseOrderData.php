<?php

declare(strict_types=1);

namespace App\DTOs\Purchasing;

use Illuminate\Http\Request;

final readonly class PurchaseOrderData
{
    /**
     * @param  array<int, array{product_id: int, quantity: float, unit_price: float, price_basis: string}>  $items
     */
    public function __construct(
        public int $supplierId,
        public string $orderDate,
        public ?string $notes,
        public string $fulfillmentType,
        public array $items,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $items = [];
        $reqItems = $request->input('items', []);

        foreach ($reqItems as $item) {
            $items[] = [
                'product_id' => (int) ($item['product_id'] ?? 0),
                'quantity' => (float) ($item['quantity'] ?? 0.000),
                'unit_price' => (float) ($item['unit_price'] ?? 0.0000),
                'price_basis' => (string) ($item['price_basis'] ?? 'per_kg'),
            ];
        }

        return new self(
            supplierId: (int) $request->input('supplier_id'),
            orderDate: $request->string('order_date')->toString() ?: now()->format('Y-m-d'),
            notes: $request->string('notes')->toString() ?: null,
            fulfillmentType: $request->string('fulfillment_type')->toString() ?: 'warehouse',
            items: $items,
        );
    }

    public function toArray(): array
    {
        return [
            'supplier_id' => $this->supplierId,
            'order_date' => $this->orderDate,
            'notes' => $this->notes,
            'fulfillment_type' => $this->fulfillmentType,
        ];
    }
}
