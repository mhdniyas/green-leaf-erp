<?php

declare(strict_types=1);

namespace App\DTOs\Sales;

use Illuminate\Http\Request;

final readonly class SalesOrderData
{
    /**
     * @param  array<int, array{product_id: int, grade: string, quantity: float, unit_price: float}>  $items
     */
    public function __construct(
        public int $customerId,
        public string $orderDate,
        public ?string $notes,
        public array $items,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            customerId: (int) $request->input('customer_id'),
            orderDate: $request->string('order_date')->toString(),
            notes: $request->input('notes') ?: null,
            items: $request->input('items', []),
        );
    }
}
