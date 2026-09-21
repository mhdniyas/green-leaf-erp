<?php

declare(strict_types=1);

namespace App\DTO\Cashbook;

class SalaryTransactionRow
{
    public function __construct(
        public readonly int $transactionId,
        public readonly int $paymentId,
        public readonly string $employeeName,
        public readonly float $amount,
        public readonly string $businessDate,
        public readonly string $fundingSource,
        public readonly string $fundingLabel,
        public readonly string $status,
    ) {}
}
