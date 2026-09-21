<?php

declare(strict_types=1);

namespace App\Services\Cashbook\PaymentsSettings;

use App\Models\Cashbook\ShopAccountingOpening;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\ShopPettyCashExpense;
use App\Services\Cashbook\BalanceCalculator;
use Illuminate\Support\Facades\DB;

class PaymentsPettySettingsService
{
    public function __construct(
        private readonly BalanceCalculator $balanceCalculator,
    ) {}

    /**
     * Get view model for Petty tab.
     *
     * @return array<string, mixed>
     */
    public function getViewModel(Shop $shop, string $startDate, string $endDate): array
    {
        $shopId = (int) $shop->id;
        $profile = ShopLedgerProfile::query()->where('shop_id', $shopId)->first();
        $paymentConfig = is_array($profile?->payment_configuration) ? $profile->payment_configuration : [];
        $pettyConfig = $paymentConfig['petty'] ?? [
            'enabled' => false,
            'allow_company_to_petty' => false,
            'shop_owner_view_petty' => true,
            'allow_expenses_from_petty' => true,
        ];

        // 1. Categories involved in Petty
        $allSettings = ShopLedgerEntrySetting::query()
            ->with('entryType')
            ->where('shop_id', $shopId)
            ->where('enabled', true)
            ->get();

        $companyToPettyCategories = $allSettings->filter(fn ($s) => in_array($s->entryType?->code, ['company_to_petty', 'bank_to_petty'], true) || $s->petty_behavior === 'add');
        $salesToPettyCategories = $allSettings->filter(fn ($s) => in_array($s->entryType?->code, ['sales_to_petty'], true));
        $pettyExpenseCategories = $allSettings->filter(fn ($s) => (bool) $s->include_in_expense && (in_array('petty', (array) $s->allowed_funding_sources, true) || $s->default_funding_source === 'petty'));
        $pettyToCompanyCategories = $allSettings->filter(fn ($s) => in_array($s->entryType?->code, ['petty_to_company'], true));

        // 2. Output Calculation using existing petty_delta-based engine
        $openingRecord = ShopAccountingOpening::query()
            ->where('shop_id', $shopId)
            ->whereDate('accounting_start_date', '<=', $startDate)
            ->orderByDesc('accounting_start_date')
            ->first();
        $openingPetty = (float) ($openingRecord?->opening_petty_balance ?? 0.0);

        // Pre-period delta
        $preDelta = (float) ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->where('business_date', '<', $startDate)
            ->whereNotIn('status', ['void', 'voided', 'reversed'])
            ->whereNull('voided_at')
            ->sum('petty_delta');
        $effectiveOpeningPetty = round($openingPetty + $preDelta, 2);

        // Period transactions
        $periodTransactions = ShopLedgerTransaction::query()
            ->with('entryType')
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->whereNotIn('status', ['void', 'voided', 'reversed'])
            ->whereNull('voided_at')
            ->get();

        $companyFunding = (float) $periodTransactions->filter(fn ($tx) => $tx->entryType?->code === 'company_to_petty')->sum('amount');
        $salesFunding = (float) $periodTransactions->filter(fn ($tx) => $tx->entryType?->code === 'sales_to_petty')->sum('amount');
        $pettyReturned = (float) $periodTransactions->filter(fn ($tx) => $tx->entryType?->code === 'petty_to_company')->sum('amount');

        $pettyExpenses = (float) $periodTransactions->filter(function (ShopLedgerTransaction $tx): bool {
            return $tx->funding_source === 'petty' && ($tx->direction === 'expense' || $tx->petty_delta < 0);
        })->sum('amount');

        $closingPetty = round($effectiveOpeningPetty + $companyFunding + $salesFunding - $pettyExpenses - $pettyReturned, 2);

        // Discrepancy Check: Compare against shop_petty_cash_expenses table
        $pettyTableExpenses = (float) ShopPettyCashExpense::query()
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->sum('amount');

        $hasDiscrepancy = abs($pettyExpenses - $pettyTableExpenses) > 0.01;
        $discrepancyMessage = null;
        if ($hasDiscrepancy) {
            $diff = abs(round($pettyExpenses - $pettyTableExpenses, 2));
            $discrepancyMessage = "Warning: Ledger petty expenses (₹{$pettyExpenses}) differ from ShopPettyCashExpense records (₹{$pettyTableExpenses}) by ₹{$diff}. The system authoritatively uses the ledger petty_delta.";
        }

        return [
            'config' => $pettyConfig,
            'categories' => [
                'company_to_petty' => $companyToPettyCategories,
                'sales_to_petty' => $salesToPettyCategories,
                'petty_expenses' => $pettyExpenseCategories,
                'petty_to_company' => $pettyToCompanyCategories,
            ],
            'output' => [
                'opening_petty' => $effectiveOpeningPetty,
                'company_funding' => round($companyFunding, 2),
                'sales_funding' => round($salesFunding, 2),
                'petty_expenses' => round($pettyExpenses, 2),
                'petty_returned' => round($pettyReturned, 2),
                'closing_petty' => $closingPetty,
                'has_discrepancy' => $hasDiscrepancy,
                'discrepancy_message' => $discrepancyMessage,
            ],
        ];
    }

    /**
     * Save petty settings for a shop.
     */
    public function saveSettings(Shop $shop, array $input, int $userId): void
    {
        $shopId = (int) $shop->id;

        DB::transaction(function () use ($shopId, $input): void {
            $profile = ShopLedgerProfile::query()->where('shop_id', $shopId)->lockForUpdate()->firstOrFail();
            $config = is_array($profile->payment_configuration) ? $profile->payment_configuration : [];

            $config['petty'] = [
                'enabled' => (bool) ($input['enabled'] ?? false),
                'allow_company_to_petty' => (bool) ($input['allow_company_to_petty'] ?? false),
                'shop_owner_view_petty' => (bool) ($input['shop_owner_view_petty'] ?? true),
                'allow_expenses_from_petty' => (bool) ($input['allow_expenses_from_petty'] ?? true),
            ];

            $profile->update(['payment_configuration' => $config]);
        });
    }
}
