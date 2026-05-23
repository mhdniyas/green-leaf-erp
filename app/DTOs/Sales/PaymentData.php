<?php

declare(strict_types=1);

namespace App\DTOs\Sales;

use Illuminate\Http\Request;

final readonly class PaymentData
{
    public function __construct(
        public float $amount,
        public string $paymentMethod,
        public ?string $reference,
        public ?string $notes,
        public string $paidAt,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            amount: (float) $request->input('amount'),
            paymentMethod: $request->string('payment_method')->toString(),
            reference: $request->input('reference') ?: null,
            notes: $request->input('notes') ?: null,
            paidAt: $request->string('paid_at')->toString(),
        );
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'payment_method' => $this->paymentMethod,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'paid_at' => $this->paidAt,
        ];
    }
}
