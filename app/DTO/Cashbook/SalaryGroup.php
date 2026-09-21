<?php

declare(strict_types=1);

namespace App\DTO\Cashbook;

use App\Enums\Cashbook\SalaryHrTransactionType;

class SalaryGroup
{
    /** @param SalaryTransactionRow[] $transactions */
    public function __construct(
        public readonly SalaryHrTransactionType $type,
        public readonly string $typeLabel,
        public readonly array $transactions,
        public readonly float $total,
        public readonly bool $isMapped,
    ) {}
}
