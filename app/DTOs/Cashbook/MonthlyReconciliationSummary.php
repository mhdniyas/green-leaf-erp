<?php

declare(strict_types=1);

namespace App\DTOs\Cashbook;

final class MonthlyReconciliationSummary
{
    public bool $is_balanced = false;

    /**
     * @param  array<string, float>  $locations
     */
    public function __construct(
        public float $openingCompanyMoney,
        public float $externalMoneyIn,
        public float $externalMoneyOut,
        public float $expectedClosing,
        public array $locations,
        public float $locatedMoney,
        public float $unexplainedDifference,
        public float $needsReviewAmount = 0.0,
    ) {
        $this->openingCompanyMoney = round($this->openingCompanyMoney, 2);
        $this->externalMoneyIn = round($this->externalMoneyIn, 2);
        $this->externalMoneyOut = round($this->externalMoneyOut, 2);
        $this->expectedClosing = round($this->expectedClosing, 2);
        $this->locatedMoney = round($this->locatedMoney, 2);
        $this->unexplainedDifference = round($this->unexplainedDifference, 2);
        $this->needsReviewAmount = round($this->needsReviewAmount, 2);
        $this->is_balanced = abs($this->unexplainedDifference) < 0.01;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'opening_company_money' => $this->openingCompanyMoney,
            'external_money_in' => $this->externalMoneyIn,
            'external_money_out' => $this->externalMoneyOut,
            'expected_closing' => $this->expectedClosing,
            'locations' => $this->locations,
            'located_money' => $this->locatedMoney,
            'unexplained_difference' => $this->unexplainedDifference,
            'needs_review_amount' => $this->needsReviewAmount,
            'is_balanced' => abs($this->unexplainedDifference) < 0.01,
        ];
    }
}
