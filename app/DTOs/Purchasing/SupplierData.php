<?php

declare(strict_types=1);

namespace App\DTOs\Purchasing;

use Illuminate\Http\Request;

final readonly class SupplierData
{
    public function __construct(
        public string $name,
        public string $type,
        public string $category,
        public bool $isDefaultPurchase,
        public string $contact,
        public string $location,
        public string $mobileNumber,
        public string $paymentTerms,
        public string $preferredPaymentMethod,
        public ?string $bankDetails = null,
        public bool $creditApproved = true,
        public string $creditTerms = '',
        public float $qualityScore = 100.00,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            type: $request->string('type')->toString(),
            category: $request->string('category')->toString() ?: 'own_purchase',
            isDefaultPurchase: $request->boolean('is_default_purchase'),
            contact: $request->string('contact')->toString(),
            location: $request->string('location')->toString(),
            mobileNumber: $request->string('mobile_number')->toString(),
            paymentTerms: $request->string('payment_terms')->toString(),
            preferredPaymentMethod: $request->string('preferred_payment_method')->toString(),
            bankDetails: $request->input('bank_details') ?: $request->input('vendor_bank_details'),
            creditApproved: $request->has('credit_approved') ? $request->boolean('credit_approved') : true,
            creditTerms: $request->string('credit_terms')->toString(),
            qualityScore: (float) $request->input('quality_score', 100.00),
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'category' => $this->category,
            'is_default_purchase' => $this->category === 'own_purchase' && $this->isDefaultPurchase,
            'contact' => $this->contact,
            'location' => $this->location,
            'mobile_number' => $this->mobileNumber,
            'payment_terms' => $this->paymentTerms,
            'preferred_payment_method' => $this->preferredPaymentMethod,
            'bank_details' => $this->bankDetails ?: null,
            'credit_approved' => $this->creditApproved,
            'credit_terms' => $this->creditTerms ?: null,
            'quality_score' => $this->qualityScore,
        ];
    }
}
