<?php

declare(strict_types=1);

namespace App\DTOs\Sales;

use Illuminate\Http\Request;

final readonly class CustomerData
{
    public function __construct(
        public string $name,
        public string $type,
        public string $contact,
        public ?string $email,
        public ?string $address,
        public string $paymentTerms,
        public float $creditLimit,
        public bool $isActive,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            type: $request->string('type')->toString(),
            contact: $request->string('contact')->toString(),
            email: $request->input('email') ?: null,
            address: $request->input('address') ?: null,
            paymentTerms: $request->string('payment_terms')->toString(),
            creditLimit: (float) $request->input('credit_limit', 0),
            isActive: (bool) $request->input('is_active', true),
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'contact' => $this->contact,
            'email' => $this->email,
            'address' => $this->address,
            'payment_terms' => $this->paymentTerms,
            'credit_limit' => $this->creditLimit,
            'is_active' => $this->isActive,
        ];
    }
}
