<?php

declare(strict_types=1);

namespace App\DTOs\Purchasing;

use Illuminate\Http\Request;

final readonly class SupplierData
{
    public function __construct(
        public string $name,
        public string $type,
        public string $contact,
        public string $paymentTerms,
        public float $qualityScore = 100.00,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            type: $request->string('type')->toString(),
            contact: $request->string('contact')->toString(),
            paymentTerms: $request->string('payment_terms')->toString(),
            qualityScore: (float) $request->input('quality_score', 100.00),
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'contact' => $this->contact,
            'payment_terms' => $this->paymentTerms,
            'quality_score' => $this->qualityScore,
        ];
    }
}
