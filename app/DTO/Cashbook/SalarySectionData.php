<?php

declare(strict_types=1);

namespace App\DTO\Cashbook;

class SalarySectionData
{
    /**
     * @param  SalaryGroup[]  $groups  Only groups that have transactions.
     * @param  int[]  $excludedTxIds  ShopLedgerTransaction IDs owned by this section.
     * @param  int[]  $excludedSettingIds  ShopLedgerEntrySetting IDs mapped by the bridge.
     * @param  array<string, float>  $fundingBreakdown  ['Sales Cash' => 18000, 'Petty' => 4500, …]
     */
    public function __construct(
        public readonly array $groups,
        public readonly float $grandTotal,
        public readonly array $excludedTxIds,
        public readonly array $excludedSettingIds,
        public readonly array $fundingBreakdown,
    ) {}

    public function hasAnyData(): bool
    {
        return $this->groups !== [];
    }
}
