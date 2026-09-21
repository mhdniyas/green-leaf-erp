<?php

declare(strict_types=1);

namespace App\DTO\Cashbook;

use App\Enums\Cashbook\FundingSource;
use App\Models\Cashbook\ShopCashbookRelation;

class ResolvedSalaryPaymentMode
{
    public function __construct(
        public readonly string $bridgeMode,
        public readonly FundingSource $fundingSource,
        public readonly ?ShopCashbookRelation $settlementRelation = null,
    ) {}

    /**
     * Get the normalized string representation for storage in ShopStaffPayment.fund_source.
     */
    public function toStorageFundSource(): string
    {
        return match ($this->fundingSource) {
            FundingSource::Petty => 'petty',
            FundingSource::Company => 'company',
            default => 'sales',
        };
    }
}
