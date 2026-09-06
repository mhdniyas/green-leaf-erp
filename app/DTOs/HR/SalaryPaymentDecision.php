<?php

declare(strict_types=1);

namespace App\DTOs\HR;

readonly class SalaryPaymentDecision
{
    /**
     * @param  array<string>  $reasons
     */
    public function __construct(
        public string $status,
        public float $requestedAmount,
        public float $allowedAmount,
        public array $reasons = [],
    ) {}

    public function isAllowed(): bool
    {
        return $this->status === 'allowed';
    }

    public function requiresHr(): bool
    {
        return $this->status === 'requires_hr';
    }

    public function isInvalid(): bool
    {
        return $this->status === 'invalid';
    }
}
