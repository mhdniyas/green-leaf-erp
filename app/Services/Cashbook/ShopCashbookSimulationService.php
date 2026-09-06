<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Enums\Cashbook\FundingSource;
use App\Enums\Cashbook\LedgerDirection;
use App\Models\Cashbook\ShopLedgerEntrySetting;

class ShopCashbookSimulationService
{
    public function __construct(
        private readonly FundingSourceEffectResolver $effectResolver,
        private readonly RelationSettlementCalculator $relationCalculator
    ) {}

    /**
     * Simulates 3 consecutive cashbook days using the shop's active settings.
     * ZERO database writes. Pure in-memory calculation.
     *
     * @param  array<int, ShopLedgerEntrySetting>  $activeSettings
     * @param  array<int, array<int, float>>  $daysInput  Keyed by day index (1, 2, 3) -> [setting_id => amount]
     */
    public function simulate3Days(
        float $openingPayable,
        float $openingPetty,
        iterable $activeSettings,
        array $daysInput,
        iterable $relations = [],
        float $openingShopBalance = 15000.0
    ): array {
        $settingsById = [];
        foreach ($activeSettings as $setting) {
            $settingsById[$setting->id] = $setting;
        }

        $currentPayable = $openingPayable;
        $currentPetty = $openingPetty;
        $currentShopBalance = $openingShopBalance;

        $simulatedDays = [];

        for ($day = 1; $day <= 3; $day++) {
            $dayAmounts = $daysInput[$day] ?? [];
            $dayOpeningPayable = $currentPayable;
            $dayOpeningPetty = $currentPetty;
            $dayOpeningShopBalance = $currentShopBalance;

            $dayResult = $this->simulateSingleDay(
                $dayOpeningPayable,
                $dayOpeningPetty,
                $settingsById,
                $dayAmounts,
                $relations,
                $dayOpeningShopBalance
            );

            $simulatedDays[$day] = $dayResult;

            // Carry forward closing balances to next day's opening
            $currentPayable = $dayResult['closingPayable'];
            $currentPetty = $dayResult['closingPetty'];

            // If there's a relation, use its closing eligible balance as next day's opening shop balance
            if (! empty($dayResult['relations'])) {
                $firstRel = $dayResult['relations'][0];
                if (isset($firstRel['closingEligibleBalance'])) {
                    $currentShopBalance = (float) $firstRel['closingEligibleBalance'];
                }
            }
        }

        return [
            'initialOpeningPayable' => $openingPayable,
            'initialOpeningPetty' => $openingPetty,
            'initialOpeningShopBalance' => $openingShopBalance,
            'days' => $simulatedDays,
        ];
    }

    private function simulateSingleDay(
        float $openingPayable,
        float $openingPetty,
        array $settingsById,
        array $dayAmounts,
        iterable $relations = [],
        float $openingShopBalance = 15000.0
    ): array {
        $totalSales = 0.0;
        $totalExpenses = 0.0;
        $plImpact = 0.0;

        $cashCollectedAtShop = 0.0;
        $directCompanyBankTotal = 0.0;
        $bankBreakdown = [];

        $expensesPaidFromShopCash = 0.0;
        $expensesPaidFromPetty = 0.0;
        $expensesPaidDirectlyByCompany = 0.0;
        $expensesCompanyLater = 0.0;

        $netSettlementDelta = 0.0;
        $netPettyDelta = 0.0;

        $itemDetails = [];

        foreach ($settingsById as $settingId => $setting) {
            $amount = (float) ($dayAmounts[$settingId] ?? 0.0);
            if ($amount < 0) {
                $amount = 0.0;
            }

            $cat = strtolower((string) ($setting->entryType?->category ?? ''));
            $isIncome = $cat === 'income' || $setting->include_in_sales || $setting->include_in_income;
            $direction = $isIncome ? LedgerDirection::Income : LedgerDirection::Expense;

            $fundingSourceStr = $setting->default_funding_source ?: 'sales';
            $fundingSource = FundingSource::tryFrom($fundingSourceStr) ?? FundingSource::Sales;

            $effect = $this->effectResolver->resolve($direction, $fundingSource, $amount, $setting);

            if ($isIncome) {
                $totalSales += $amount;

                if ($setting->isDirectBankCollection()) {
                    $accId = (int) ($setting->company_account_id ?: $setting->companyAccount?->id);
                    $accName = $setting->companyAccount?->name ?? 'Bank';
                    $directCompanyBankTotal += $amount;
                    if (! isset($bankBreakdown[$accId])) {
                        $bankBreakdown[$accId] = [
                            'name' => $accName,
                            'amount' => 0.0,
                        ];
                    }
                    $bankBreakdown[$accId]['amount'] += $amount;
                } else {
                    $cashCollectedAtShop += $amount;
                }
            } else {
                $totalExpenses += $amount;

                match ($fundingSource) {
                    FundingSource::Sales => $expensesPaidFromShopCash += $amount,
                    FundingSource::Petty => $expensesPaidFromPetty += $amount,
                    FundingSource::Company, FundingSource::Bank, FundingSource::External => $expensesPaidDirectlyByCompany += $amount,
                    FundingSource::CompanyLater => $expensesCompanyLater += $amount,
                    default => null,
                };
            }

            $plImpact += $effect->plDelta;
            $netSettlementDelta += $effect->settlementDelta;
            $netPettyDelta += $effect->pettyDelta;

            $itemDetails[$settingId] = [
                'setting_id' => $settingId,
                'name' => $setting->entryType?->name ?? 'Unknown',
                'category' => $setting->entryType?->category ?? 'other',
                'amount' => $amount,
                'funding_source' => $fundingSourceStr,
                'company_account' => $setting->companyAccount?->name,
                'pl_delta' => $effect->plDelta,
                'settlement_delta' => $effect->settlementDelta,
                'petty_delta' => $effect->pettyDelta,
            ];
        }

        $relationResults = [];
        $netShopHeldCollections = max(0.0, $cashCollectedAtShop - $expensesPaidFromShopCash);

        foreach ($relations as $relation) {
            if (! $relation->enabled) {
                continue;
            }

            $calcResult = $this->relationCalculator->calculate(
                $relation,
                $dayAmounts,
                $openingShopBalance,
                $netShopHeldCollections
            );

            $relationResults[] = $calcResult;
        }

        $closingPayable = $openingPayable + $netSettlementDelta;
        $closingPetty = $openingPetty + $netPettyDelta;

        return [
            'openingPayable' => $openingPayable,
            'openingPetty' => $openingPetty,
            'totalSales' => $totalSales,
            'totalExpenses' => $totalExpenses,
            'plImpact' => $plImpact,
            'cashCollectedAtShop' => $cashCollectedAtShop,
            'directCompanyBankTotal' => $directCompanyBankTotal,
            'bankBreakdown' => array_values($bankBreakdown),
            'expensesPaidFromShopCash' => $expensesPaidFromShopCash,
            'expensesPaidFromPetty' => $expensesPaidFromPetty,
            'expensesPaidDirectlyByCompany' => $expensesPaidDirectlyByCompany,
            'expensesCompanyLater' => $expensesCompanyLater,
            'netSettlementDelta' => $netSettlementDelta,
            'netPettyDelta' => $netPettyDelta,
            'closingPayable' => $closingPayable,
            'closingPetty' => $closingPetty,
            'itemDetails' => $itemDetails,
            'relations' => $relationResults,
        ];
    }
}
