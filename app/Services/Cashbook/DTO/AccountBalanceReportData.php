<?php

declare(strict_types=1);

namespace App\Services\Cashbook\DTO;

use Illuminate\Support\Collection;

class AccountBalanceReportData
{
    /**
     * @param  array{preset: string, from_date: string, to_date: string, label: string}  $period
     * @param  array{actual_balance: float, floating_in: float, floating_out: float, net_floating: float, receivables: float, payables: float, expected_balance: float}  $summary
     * @param  array<int, array<string, mixed>>  $accounts
     * @param  array<int, array<string, mixed>>  $floatingIn
     * @param  array<int, array<string, mixed>>  $floatingOut
     * @param  array<int, array<string, mixed>>  $receivables
     * @param  array<int, array<string, mixed>>  $payables
     * @param  array<string, array{opening: float, activity: float, closing: float}>  $movements
     * @param  Collection<int, array<string, mixed>>  $transactions
     */
    public function __construct(
        public readonly array $period,
        public readonly array $summary,
        public readonly array $accounts,
        public readonly array $floatingIn,
        public readonly array $floatingOut,
        public readonly array $receivables,
        public readonly array $payables,
        public readonly array $movements,
        public readonly Collection $transactions,
    ) {}
}
