<?php

declare(strict_types=1);

namespace App\DTOs\Purchasing;

use Illuminate\Http\Request;

final readonly class PurchaseInvoiceData
{
    public function __construct(
        public int $goodsReceivedId,
        public int $supplierId,
        public string $invoiceNumber,
        public float $amount,
        public string $status,
        public ?string $notes,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            goodsReceivedId: (int) $request->input('goods_received_id'),
            supplierId: (int) $request->input('supplier_id'),
            invoiceNumber: $request->string('invoice_number')->toString(),
            amount: (float) $request->input('amount'),
            status: $request->string('status', 'pending')->toString(),
            notes: $request->string('notes')->toString() ?: null,
        );
    }

    public function toArray(): array
    {
        return [
            'goods_received_id' => $this->goodsReceivedId,
            'supplier_id' => $this->supplierId,
            'invoice_number' => $this->invoiceNumber,
            'amount' => $this->amount,
            'status' => $this->status,
            'notes' => $this->notes,
        ];
    }
}
