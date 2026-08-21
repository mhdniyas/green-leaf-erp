<?php

declare(strict_types=1);

namespace App\DTO\Cashbook;

use App\Enums\Cashbook\BalanceMove;

/**
 * The output of the FundingSourceEffectResolver: exactly what one ledger
 * line does to the four independent balances (never merge these into one
 * ambiguous "balance").
 */
final class LedgerEffect
{
    public function __construct(
        public readonly float $plDelta,
        public readonly float $settlementDelta,
        public readonly BalanceMove $settlementDirection,
        public readonly float $pettyDelta,
        public readonly BalanceMove $pettyDirection,
        public readonly float $companyPendingDelta,
        public readonly BalanceMove $companyPendingDirection,
    ) {}
}
