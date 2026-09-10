<?php

declare(strict_types=1);

namespace App\Services\Cashbook\CashFlow\Sources;

use App\DTOs\Cashbook\MoneyMovement;
use Illuminate\Support\Collection;

interface CashFlowSourceInterface
{
    /**
     * Fetch normalized money movements for a given month (format: YYYY-MM).
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, MoneyMovement>
     */
    public function forMonth(string $month, array $filters = []): Collection;

    /**
     * Compute opening balances held/payable as of the given date (YYYY-MM-DD).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function openingBalances(string $startDate, array $filters = []): array;
}
