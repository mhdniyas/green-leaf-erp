<?php

declare(strict_types=1);

namespace App\DTOs\Purchasing;

use Illuminate\Http\Request;

final readonly class GoodsReceivedData
{
    /**
     * @param  array<int, array{purchase_order_item_id: ?int, product_id: int, received_qty: float, received_unit?: ?string}>  $items
     */
    public function __construct(
        public ?int $purchaseOrderId,
        public string $receivedAt,
        public float $transportCost,
        public float $labourCost,
        public ?string $notes,
        public array $items,
        public string $billStatus = 'bill_available',
        public ?string $billNumber = null,
        public ?int $destinationShopId = null,
        public ?int $warehouseId = null,
        public ?string $clientSubmissionId = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $items = [];
        $reqItems = $request->input('items', []);

        foreach ($reqItems as $item) {
            $items[] = [
                'purchase_order_item_id' => isset($item['purchase_order_item_id']) && $item['purchase_order_item_id'] !== null ? (int) $item['purchase_order_item_id'] : null,
                'product_id' => (int) ($item['product_id'] ?? 0),
                'received_qty' => (float) ($item['received_qty'] ?? 0.000),
                'received_unit' => isset($item['received_unit']) && $item['received_unit'] !== null ? (string) $item['received_unit'] : null,
            ];
        }

        $poId = $request->filled('purchase_order_id') ? (int) $request->input('purchase_order_id') : null;
        $destShopId = $request->filled('destination_shop_id') ? (int) $request->input('destination_shop_id') : null;
        $whId = $request->filled('warehouse_id') ? (int) $request->input('warehouse_id') : null;
        $clientSubId = $request->filled('client_submission_id') ? (string) $request->input('client_submission_id') : null;

        return new self(
            purchaseOrderId: $poId,
            receivedAt: $request->string('received_at')->toString() ?: now()->format('Y-m-d'),
            transportCost: (float) $request->input('transport_cost', 0.00),
            labourCost: (float) $request->input('labour_cost', 0.00),
            notes: $request->string('notes')->toString() ?: null,
            items: $items,
            billStatus: (string) $request->input('bill_status', 'bill_available'),
            billNumber: $request->filled('bill_number') ? (string) $request->input('bill_number') : null,
            destinationShopId: $destShopId,
            warehouseId: $whId,
            clientSubmissionId: $clientSubId,
        );
    }

    public function calculatePayloadHash(): string
    {
        $canonicalItems = $this->items;
        usort($canonicalItems, function (array $a, array $b): int {
            $cmp = ($a['product_id'] ?? 0) <=> ($b['product_id'] ?? 0);
            if ($cmp !== 0) {
                return $cmp;
            }

            return ($a['purchase_order_item_id'] ?? 0) <=> ($b['purchase_order_item_id'] ?? 0);
        });

        $normalizedItems = array_map(fn (array $item): array => [
            'product_id' => (int) ($item['product_id'] ?? 0),
            'purchase_order_item_id' => isset($item['purchase_order_item_id']) && $item['purchase_order_item_id'] !== null ? (int) $item['purchase_order_item_id'] : null,
            'received_qty' => number_format((float) ($item['received_qty'] ?? 0), 3, '.', ''),
        ], $canonicalItems);

        $payload = [
            'purchase_order_id' => $this->purchaseOrderId,
            'destination_shop_id' => $this->destinationShopId,
            'warehouse_id' => $this->warehouseId,
            'bill_status' => $this->billStatus,
            'received_at' => $this->receivedAt,
            'items' => $normalizedItems,
        ];

        return hash('sha256', (string) json_encode($payload));
    }

    public function toArray(): array
    {
        return [
            'purchase_order_id' => $this->purchaseOrderId,
            'destination_shop_id' => $this->destinationShopId,
            'warehouse_id' => $this->warehouseId,
            'received_at' => $this->receivedAt,
            'transport_cost' => $this->transportCost,
            'labour_cost' => $this->labourCost,
            'notes' => $this->notes,
            'bill_status' => $this->billStatus,
            'bill_number' => $this->billNumber,
            'client_submission_id' => $this->clientSubmissionId,
        ];
    }
}
