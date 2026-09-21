<?php

declare(strict_types=1);

namespace App\Services\Cashbook\PaymentsSettings;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\ShopCashbookRelationItem;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Services\Cashbook\CashFlowResolutionService;
use Illuminate\Support\Facades\DB;

class PaymentsCompanyCollectionsService
{
    public function __construct(
        private readonly CashFlowResolutionService $cashFlowResolutionService,
    ) {}

    /**
     * Get view model for Company Collections tab.
     *
     * @return array<string, mixed>
     */
    public function getViewModel(Shop $shop, string $startDate, string $endDate): array
    {
        $shopId = (int) $shop->id;

        $settings = ShopLedgerEntrySetting::query()
            ->with(['entryType', 'headerGroup', 'companyAccount'])
            ->where('shop_id', $shopId)
            ->where('enabled', true)
            ->get();

        $companyAccounts = CompanyAccount::query()
            ->where('enabled', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        // Find relations where settings are included
        $relationItems = ShopCashbookRelationItem::query()
            ->with('relation')
            ->whereIn('shop_ledger_entry_setting_id', $settings->pluck('id'))
            ->get()
            ->groupBy('shop_ledger_entry_setting_id');

        // Transactions in month
        $transactions = ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->whereIn('status', ['posted', 'approved'])
            ->whereNull('voided_at')
            ->get();

        $statements = CompanyAccountStatementEntry::query()
            ->where('source_type', ShopLedgerTransaction::class)
            ->whereIn('source_id', $transactions->pluck('id'))
            ->get()
            ->keyBy('source_id');

        $categories = [];
        $totalDirectCollections = 0.0;
        $totalVerified = 0.0;
        $totalPendingVerification = 0.0;
        $totalReconciled = 0.0;
        $accountSplit = [];

        foreach ($companyAccounts as $acc) {
            $accountSplit[$acc->id] = [
                'account' => $acc,
                'name' => $acc->name,
                'total' => 0.0,
            ];
        }

        foreach ($settings as $setting) {
            $isDirect = $setting->isDirectBankCollection();
            $effectiveAccount = $setting->companyAccount;
            $catTxs = $transactions->where('entry_type_id', $setting->entry_type_id);
            $catAmount = (float) $catTxs->sum('amount');

            $relNames = [];
            if ($relationItems->has($setting->id)) {
                foreach ($relationItems->get($setting->id) as $ri) {
                    if ($ri->relation && $ri->relation->enabled) {
                        $relNames[] = $ri->relation->name.' ('.strtoupper((string) $ri->role).')';
                    }
                }
            }
            $settlementLabel = empty($relNames) ? 'Not mapped' : implode(', ', $relNames);
            $reportLabel = ucfirst((string) $setting->resolveSalesReportBucket());

            // Build dynamic explanation
            $accountName = $effectiveAccount?->name ?? 'None';
            $headerName = $setting->headerGroup?->name ?? 'General';
            $explanation = "{$setting->displayName()} is an {$setting->entryType?->category} category under {$headerName}. ";
            if ($isDirect) {
                $explanation .= "It is collected directly into {$accountName}, participates in {$settlementLabel}, and reports under {$reportLabel}.";
            } else {
                $explanation .= 'It is held as shop cash drawer balance.';
            }

            if ($isDirect) {
                $totalDirectCollections += $catAmount;
                if ($effectiveAccount) {
                    $accountSplit[$effectiveAccount->id]['total'] += $catAmount;
                }

                foreach ($catTxs as $tx) {
                    $stmt = $statements->get($tx->id);
                    if ($stmt && $stmt->is_finalized) {
                        $totalReconciled += (float) $tx->amount;
                        $totalVerified += (float) $tx->amount;
                    } elseif ($stmt) {
                        $totalVerified += (float) $tx->amount;
                    } else {
                        $totalPendingVerification += (float) $tx->amount;
                    }
                }
            }

            $categories[] = [
                'setting' => $setting,
                'name' => $setting->displayName(),
                'code' => $setting->entryType?->code ?? '',
                'header_name' => $headerName,
                'company_account' => $effectiveAccount,
                'is_direct' => $isDirect,
                'settlement_label' => $settlementLabel,
                'report_label' => $reportLabel,
                'is_enabled' => (bool) $setting->enabled,
                'month_amount' => round($catAmount, 2),
                'explanation' => $explanation,
            ];
        }

        // Filter out zero accounts from split for display
        $filteredAccountSplit = array_filter($accountSplit, fn ($a) => $a['total'] > 0);

        return [
            'categories' => $categories,
            'company_accounts' => $companyAccounts,
            'output' => [
                'total_direct_collections' => round($totalDirectCollections, 2),
                'verified' => round($totalVerified, 2),
                'pending_verification' => round($totalPendingVerification, 2),
                'reconciled' => round($totalReconciled, 2),
                'account_split' => $filteredAccountSplit,
            ],
        ];
    }

    /**
     * Save company collection mappings for a shop (strict shop isolation).
     *
     * @param  array<int, int|null>  $mappings  [setting_id => company_account_id]
     */
    public function saveMappings(Shop $shop, array $mappings, int $userId): void
    {
        $shopId = (int) $shop->id;

        DB::transaction(function () use ($shopId, $mappings): void {
            $validAccounts = CompanyAccount::query()->where('enabled', true)->pluck('id')->all();

            foreach ($mappings as $settingId => $companyAccountId) {
                $setting = ShopLedgerEntrySetting::query()
                    ->where('shop_id', $shopId)
                    ->where('id', $settingId)
                    ->first();

                if (! $setting) {
                    continue;
                }

                $accountId = (! empty($companyAccountId) && in_array((int) $companyAccountId, $validAccounts, true))
                    ? (int) $companyAccountId
                    : null;

                $setting->update(['company_account_id' => $accountId]);
            }
        });
    }
}
