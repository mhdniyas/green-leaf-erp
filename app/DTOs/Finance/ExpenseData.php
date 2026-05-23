<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

use Illuminate\Http\Request;

final readonly class ExpenseData
{
    public function __construct(
        public string $expenseDate,
        public int $accountId,
        public float $amount,
        public string $paymentMethod,
        public ?string $reference,
        public ?string $description,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            expenseDate: $request->string('expense_date')->toString(),
            accountId: $request->integer('account_id'),
            amount: (float) $request->input('amount'),
            paymentMethod: $request->string('payment_method')->toString(),
            reference: $request->filled('reference') ? $request->string('reference')->toString() : null,
            description: $request->filled('description') ? $request->string('description')->toString() : null,
        );
    }

    public function toArray(): array
    {
        return [
            'expense_date' => $this->expenseDate,
            'account_id' => $this->accountId,
            'amount' => $this->amount,
            'payment_method' => $this->paymentMethod,
            'reference' => $this->reference,
            'description' => $this->description,
        ];
    }
}
