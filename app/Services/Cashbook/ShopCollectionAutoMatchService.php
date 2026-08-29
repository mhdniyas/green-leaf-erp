<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ShopCollectionAutoMatchService
{
    public function __construct(
        private readonly CompanyPaymentReconciliationService $reconciliationService
    ) {}

    /**
     * Preview Settings-Driven Shop Collections for Auto-Matching against configured Bank Accounts.
     *
     * @return array{
     *     period: array{month_start: string, month_end: string},
     *     filters: array{company_account_id: ?int, shop_id: ?int, entry_type_id: ?int},
     *     summary: array{
     *         total_collections_count: int,
     *         total_collections_amount: float,
     *         exact_matches_count: int,
     *         exact_matches_amount: float,
     *         amount_differences_count: int,
     *         amount_differences_amount: float,
     *         nearby_dates_count: int,
     *         nearby_dates_amount: float,
     *         ambiguous_count: int,
     *         ambiguous_amount: float,
     *         no_match_count: int,
     *         no_match_amount: float,
     *         no_statement_data_count: int,
     *         no_statement_data_amount: float,
     *         outside_coverage_count: int,
     *         outside_coverage_amount: float,
     *         no_amount_match_count: int,
     *         no_amount_match_amount: float,
     *         bank_mapping_mismatches_count: int,
     *         bank_mapping_mismatches_amount: float,
     *         bank_not_configured_count: int,
     *         bank_not_configured_amount: float,
     *         already_reconciled_count: int,
     *         already_reconciled_amount: float,
     *         duplicate_sources_count: int
     *     },
     *     grouped_by_bank: array<int|string, array{
     *         bank_id: ?int,
     *         bank_name: string,
     *         bank_account_number: ?string,
     *         account_type: string,
     *         is_cash_warning: bool,
     *         statement_coverage: array{
     *             has_data: bool,
     *             min_date: ?string,
     *             max_date: ?string,
     *             total_statements: int
     *         },
     *         exact_matches: array<int, array<string, mixed>>,
     *         amount_differences: array<int, array<string, mixed>>,
     *         nearby_dates: array<int, array<string, mixed>>,
     *         ambiguous: array<int, array<string, mixed>>,
     *         no_match: array<int, array<string, mixed>>,
     *         no_statement_data: array<int, array<string, mixed>>,
     *         outside_coverage: array<int, array<string, mixed>>,
     *         no_amount_match: array<int, array<string, mixed>>,
     *         bank_mapping_mismatches: array<int, array<string, mixed>>,
     *         bank_not_configured: array<int, array<string, mixed>>,
     *         already_reconciled: array<int, array<string, mixed>>,
     *         duplicate_sources: array<int, array<string, mixed>>
     *     }>,
     *     exact_matches: array<int, array<string, mixed>>,
     *     bank_mapping_mismatches: array<int, array<string, mixed>>,
     *     bank_not_configured: array<int, array<string, mixed>>
     * }
     */
    public function preview(
        string $monthStart,
        string $monthEnd,
        ?int $companyAccountId = null,
        ?int $shopId = null,
        ?int $entryTypeId = null,
        int $graceDays = 2
    ): array {
        // 1. Identify relevant online/bank-related payment categories or entry settings
        $entryTypesQuery = LedgerEntryType::query()->where('category', 'income');
        if ($entryTypeId) {
            $entryTypesQuery->whereKey($entryTypeId);
        } else {
            // Default to online/bank settlement categories unless explicitly filtered
            $entryTypesQuery->whereIn('code', ['paytm', 'card', 'upi', 'gpay', 'bank_transfer', 'online']);
        }
        /** @var Collection<int, LedgerEntryType> $entryTypes */
        $entryTypes = $entryTypesQuery->get();
        $entryTypeIds = $entryTypes->pluck('id')->all();

        // 2. Fetch active ShopLedgerEntrySetting records
        $settingsQuery = ShopLedgerEntrySetting::query()
            ->with(['companyAccount'])
            ->whereIn('entry_type_id', $entryTypeIds);

        if ($shopId) {
            $settingsQuery->where('shop_id', $shopId);
        }
        /** @var Collection<int, ShopLedgerEntrySetting> $settings */
        $settings = $settingsQuery->get();
        $settingsMap = $settings->keyBy(fn (ShopLedgerEntrySetting $s) => "{$s->shop_id}:{$s->entry_type_id}");

        // 3. Fetch all company accounts
        /** @var Collection<int, CompanyAccount> $companyAccounts */
        $companyAccounts = CompanyAccount::query()->get()->keyBy('id');

        // 4. Fetch statement coverage metadata across all bank accounts
        $coverageRecords = CompanyAccountStatementEntry::query()
            ->selectRaw('company_account_id, MIN(transaction_date) as min_date, MAX(transaction_date) as max_date, COUNT(*) as total_count')
            ->groupBy('company_account_id')
            ->get()
            ->keyBy('company_account_id');

        // 5. Fetch ShopLedgerTransaction candidates in period
        $txQuery = ShopLedgerTransaction::query()
            ->with(['shop', 'entryType', 'companyAccount', 'statementEntries'])
            ->whereIn('entry_type_id', $entryTypeIds)
            ->where('direction', 'income')
            ->whereNotIn('status', ['void', 'voided'])
            ->whereBetween('business_date', [$monthStart, $monthEnd])
            ->orderBy('business_date')
            ->orderBy('id');

        if ($shopId) {
            $txQuery->where('shop_id', $shopId);
        }
        /** @var Collection<int, ShopLedgerTransaction> $transactions */
        $transactions = $txQuery->get();

        // 6. Fetch all unfinalized statement entries across active bank accounts within the date window
        $startDate = Carbon::parse($monthStart)->subDays($graceDays)->toDateString();
        $endDate = Carbon::parse($monthEnd)->addDays($graceDays)->toDateString();

        /** @var Collection<int, CompanyAccountStatementEntry> $allStatements */
        $allStatements = CompanyAccountStatementEntry::query()
            ->with('companyAccount')
            ->where('direction', 'in')
            ->where('is_finalized', false)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $statementsByAccount = $allStatements->groupBy('company_account_id');

        // Results containers
        $groupedByBank = [];
        $exactMatches = [];
        $amountDifferences = [];
        $nearbyDates = [];
        $ambiguous = [];
        $noMatch = [];
        $noStatementData = [];
        $outsideCoverage = [];
        $noAmountMatch = [];
        $bankMappingMismatches = [];
        $bankNotConfigured = [];
        $alreadyReconciled = [];
        $duplicateSources = [];

        $exactMatchesAmount = 0.0;
        $amountDifferencesAmount = 0.0;
        $nearbyDatesAmount = 0.0;
        $ambiguousAmount = 0.0;
        $noMatchAmount = 0.0;
        $noStatementDataAmount = 0.0;
        $outsideCoverageAmount = 0.0;
        $noAmountMatchAmount = 0.0;
        $bankMappingMismatchesAmount = 0.0;
        $bankNotConfiguredAmount = 0.0;
        $alreadyReconciledAmount = 0.0;
        $totalCollectionsAmount = 0.0;

        // Group transactions by Shop + EntryType + Date to detect multiple competing entries
        $txByShopTypeDate = $transactions->groupBy(fn (ShopLedgerTransaction $t) => "{$t->shop_id}:{$t->entry_type_id}:{$t->business_date->toDateString()}");

        // Keep track of claimed statement IDs per bank account during preview
        $claimedStatementsPerBank = [];

        foreach ($transactions as $tx) {
            $txAmount = round((float) $tx->amount, 2);
            $txDate = $tx->business_date->toDateString();
            $shopName = $tx->shop?->name ?? "Shop #{$tx->shop_id}";
            $categoryName = $tx->entryType?->name ?? 'Collection';
            $settingKey = "{$tx->shop_id}:{$tx->entry_type_id}";

            /** @var ShopLedgerEntrySetting|null $setting */
            $setting = $settingsMap->get($settingKey);
            $targetBankId = $setting?->company_account_id;
            /** @var CompanyAccount|null $targetBank */
            $targetBank = $targetBankId ? $companyAccounts->get($targetBankId) : null;
            $currentBank = $tx->companyAccount;

            $totalCollectionsAmount += $txAmount;

            // Initialize bank group container
            $bankGroupKey = $targetBankId ? (string) $targetBankId : 'unconfigured';
            $bankGroupName = $targetBank?->name ?? ($targetBankId ? "Bank #{$targetBankId}" : 'Bank Not Configured');
            $cov = $targetBankId ? $coverageRecords->get($targetBankId) : null;

            if (! isset($groupedByBank[$bankGroupKey])) {
                $groupedByBank[$bankGroupKey] = [
                    'bank_id' => $targetBankId,
                    'bank_name' => $bankGroupName,
                    'bank_account_number' => $targetBank?->account_number,
                    'account_type' => $targetBank?->account_type ?? 'none',
                    'is_cash_warning' => $targetBank?->account_type === 'cash',
                    'statement_coverage' => [
                        'has_data' => $cov && (int) $cov->total_count > 0,
                        'min_date' => $cov?->min_date ? Carbon::parse($cov->min_date)->toDateString() : null,
                        'max_date' => $cov?->max_date ? Carbon::parse($cov->max_date)->toDateString() : null,
                        'total_statements' => (int) ($cov?->total_count ?? 0),
                    ],
                    'exact_matches' => [],
                    'amount_differences' => [],
                    'nearby_dates' => [],
                    'ambiguous' => [],
                    'no_match' => [],
                    'no_statement_data' => [],
                    'outside_coverage' => [],
                    'no_amount_match' => [],
                    'bank_mapping_mismatches' => [],
                    'bank_not_configured' => [],
                    'already_reconciled' => [],
                    'duplicate_sources' => [],
                ];
            }

            // Check if user applied company_account_id filter
            if ($companyAccountId !== null && $targetBankId !== $companyAccountId) {
                continue;
            }

            // Check Duplicate Source entries on same date
            $competingGroup = $txByShopTypeDate->get("{$tx->shop_id}:{$tx->entry_type_id}:{$txDate}");
            if ($competingGroup && $competingGroup->count() > 1) {
                $dupItem = [
                    'transaction_id' => $tx->id,
                    'transaction_ref' => $tx->secureRouteKey(),
                    'shop_id' => $tx->shop_id,
                    'shop_name' => $shopName,
                    'category_name' => $categoryName,
                    'business_date' => $txDate,
                    'amount' => $txAmount,
                    'count_for_date' => $competingGroup->count(),
                    'warning' => "{$competingGroup->count()} source transactions exist for {$shopName} {$categoryName} on {$txDate}",
                ];
                $duplicateSources[] = $dupItem;
                $groupedByBank[$bankGroupKey]['duplicate_sources'][] = $dupItem;
            }

            // A. Check Already Reconciled
            if ($tx->isReconciled() || $tx->statementEntries->where('is_finalized', true)->isNotEmpty()) {
                $isLockedMismatch = $targetBankId && (int) $tx->company_account_id !== (int) $targetBankId;
                $reconItem = [
                    'transaction_id' => $tx->id,
                    'shop_name' => $shopName,
                    'category_name' => $categoryName,
                    'business_date' => $txDate,
                    'amount' => $txAmount,
                    'current_bank' => $currentBank?->name ?? 'None',
                    'configured_bank' => $targetBank?->name ?? 'None',
                    'historical_bank_locked' => $isLockedMismatch,
                    'status' => 'already_reconciled',
                ];
                $alreadyReconciled[] = $reconItem;
                $alreadyReconciledAmount += $txAmount;
                $groupedByBank[$bankGroupKey]['already_reconciled'][] = $reconItem;

                continue;
            }

            // B. Check Bank Not Configured in Settings
            if (! $targetBankId || ! $targetBank) {
                $unconfItem = [
                    'transaction_id' => $tx->id,
                    'transaction_ref' => $tx->secureRouteKey(),
                    'shop_id' => $tx->shop_id,
                    'shop_name' => $shopName,
                    'category_name' => $categoryName,
                    'business_date' => $txDate,
                    'amount' => $txAmount,
                    'current_bank' => $currentBank?->name ?? 'Unassigned',
                    'status' => 'bank_not_configured',
                    'reason' => 'Bank Not Configured',
                    'message' => "No bank account is configured for {$shopName} · {$categoryName} in Cashbook Settings.",
                ];
                $bankNotConfigured[] = $unconfItem;
                $bankNotConfiguredAmount += $txAmount;
                $groupedByBank['unconfigured']['bank_not_configured'][] = $unconfItem;

                continue;
            }

            // C. Check Bank Mapping Mismatch (Unreconciled transaction assigned to different account than current setting)
            if ((int) $tx->company_account_id !== (int) $targetBankId) {
                $mismatchItem = [
                    'transaction_id' => $tx->id,
                    'transaction_ref' => $tx->secureRouteKey(),
                    'shop_id' => $tx->shop_id,
                    'shop_name' => $shopName,
                    'category_name' => $categoryName,
                    'business_date' => $txDate,
                    'amount' => $txAmount,
                    'current_account_id' => $tx->company_account_id,
                    'current_bank_name' => $currentBank?->name ?? 'Unassigned (None)',
                    'configured_account_id' => $targetBankId,
                    'configured_bank_name' => $targetBank->name,
                    'eligible_for_reassign' => true,
                    'status' => 'bank_mapping_mismatch',
                    'reason' => 'Bank Mapping Mismatch',
                    'message' => "Transaction has {$currentBank?->name} but setting is {$targetBank->name}.",
                ];
                $bankMappingMismatches[] = $mismatchItem;
                $bankMappingMismatchesAmount += $txAmount;
                $groupedByBank[$bankGroupKey]['bank_mapping_mismatches'][] = $mismatchItem;

                // IMPORTANT: Do not search the old/wrong bank account for statements
                continue;
            }

            // D. Check Statement Data Coverage for the Target Bank
            $minStmtDate = $cov?->min_date ? Carbon::parse($cov->min_date)->toDateString() : null;
            $maxStmtDate = $cov?->max_date ? Carbon::parse($cov->max_date)->toDateString() : null;
            $totalStmtCount = (int) ($cov?->total_count ?? 0);

            if ($totalStmtCount === 0 || ! $minStmtDate) {
                $noDataItem = [
                    'transaction_id' => $tx->id,
                    'transaction_ref' => $tx->secureRouteKey(),
                    'shop_name' => $shopName,
                    'category_name' => $categoryName,
                    'business_date' => $txDate,
                    'amount' => $txAmount,
                    'configured_bank_id' => $targetBankId,
                    'configured_bank_name' => $targetBank->name,
                    'status' => 'no_statement_data',
                    'reason' => 'No Statement Data',
                    'message' => "No bank statements have been uploaded for {$targetBank->name}.",
                ];
                $noStatementData[] = $noDataItem;
                $noStatementDataAmount += $txAmount;
                $noMatch[] = $noDataItem;
                $noMatchAmount += $txAmount;
                $groupedByBank[$bankGroupKey]['no_statement_data'][] = $noDataItem;
                $groupedByBank[$bankGroupKey]['no_match'][] = $noDataItem;

                continue;
            }

            if ($txDate < $minStmtDate || $txDate > $maxStmtDate) {
                $outCovItem = [
                    'transaction_id' => $tx->id,
                    'transaction_ref' => $tx->secureRouteKey(),
                    'shop_name' => $shopName,
                    'category_name' => $categoryName,
                    'business_date' => $txDate,
                    'amount' => $txAmount,
                    'configured_bank_id' => $targetBankId,
                    'configured_bank_name' => $targetBank->name,
                    'statement_coverage' => "{$minStmtDate} → {$maxStmtDate}",
                    'status' => 'outside_statement_coverage',
                    'reason' => 'Outside Statement Coverage',
                    'message' => "Transaction on {$txDate} is outside statement range ({$minStmtDate} → {$maxStmtDate}) for {$targetBank->name}.",
                ];
                $outsideCoverage[] = $outCovItem;
                $outsideCoverageAmount += $txAmount;
                $noMatch[] = $outCovItem;
                $noMatchAmount += $txAmount;
                $groupedByBank[$bankGroupKey]['outside_coverage'][] = $outCovItem;
                $groupedByBank[$bankGroupKey]['no_match'][] = $outCovItem;

                continue;
            }

            // E. Target Bank is configured and has statement coverage. Search Statement Entries.
            /** @var Collection<int, CompanyAccountStatementEntry> $bankStatements */
            $bankStatements = $statementsByAccount->get($targetBankId) ?? collect();
            $claimed = $claimedStatementsPerBank[$targetBankId] ?? [];

            // Statements on exact same date
            $sameDateStatements = $bankStatements
                ->filter(fn (CompanyAccountStatementEntry $s) => $s->transaction_date->toDateString() === $txDate)
                ->values();

            $exactAmountSameDate = $sameDateStatements
                ->filter(fn (CompanyAccountStatementEntry $s) => abs((float) $s->amount - $txAmount) <= 0.01)
                ->values();

            // Check if multiple active shop collections on this date share the same amount for this bank
            $competingTxOnDateAmount = $transactions
                ->filter(fn (ShopLedgerTransaction $other) => ! $other->isReconciled()
                    && (int) $other->company_account_id === (int) $targetBankId
                    && $other->business_date->toDateString() === $txDate
                    && abs((float) $other->amount - $txAmount) <= 0.01
                )->count();

            // 1. Exact Same Date Match
            if ($exactAmountSameDate->count() === 1 && $competingTxOnDateAmount === 1) {
                $stmt = $exactAmountSameDate->first();
                if (! in_array($stmt->id, $claimed, true)) {
                    $claimedStatementsPerBank[$targetBankId][] = $stmt->id;
                    $exactItem = [
                        'transaction_id' => $tx->id,
                        'transaction_ref' => $tx->secureRouteKey(),
                        'shop_name' => $shopName,
                        'category_name' => $categoryName,
                        'business_date' => $txDate,
                        'amount' => $txAmount,
                        'configured_bank_id' => $targetBankId,
                        'configured_bank_name' => $targetBank->name,
                        'statement_id' => $stmt->id,
                        'statement_uuid' => $stmt->public_uuid,
                        'statement_date' => $stmt->transaction_date->toDateString(),
                        'statement_amount' => (float) $stmt->amount,
                        'statement_reference' => $stmt->reference ?: $stmt->narration ?: '—',
                        'status' => 'exact_match',
                        'match_type' => 'exact_same_date',
                    ];
                    $exactMatches[] = $exactItem;
                    $exactMatchesAmount += $txAmount;
                    $groupedByBank[$bankGroupKey]['exact_matches'][] = $exactItem;

                    continue;
                }
            }

            // 2. Ambiguous on Same Date (multiple statement entries or competing shop txs)
            if ($exactAmountSameDate->count() > 1 || ($exactAmountSameDate->count() === 1 && $competingTxOnDateAmount > 1)) {
                $ambItem = [
                    'transaction_id' => $tx->id,
                    'transaction_ref' => $tx->secureRouteKey(),
                    'shop_name' => $shopName,
                    'category_name' => $categoryName,
                    'business_date' => $txDate,
                    'amount' => $txAmount,
                    'configured_bank_name' => $targetBank->name,
                    'statement_count' => $exactAmountSameDate->count(),
                    'competing_tx_count' => $competingTxOnDateAmount,
                    'status' => 'ambiguous',
                    'reason' => $exactAmountSameDate->count() > 1
                        ? "{$exactAmountSameDate->count()} matching statement entries in {$targetBank->name} on {$txDate}"
                        : "{$competingTxOnDateAmount} shop collections with the same amount in {$targetBank->name} on {$txDate}",
                ];
                $ambiguous[] = $ambItem;
                $ambiguousAmount += $txAmount;
                $groupedByBank[$bankGroupKey]['ambiguous'][] = $ambItem;

                continue;
            }

            // 3. Same date with Different Amount in Target Bank
            $unclaimedSameDate = $sameDateStatements->filter(fn ($s) => ! in_array($s->id, $claimed, true));
            if ($unclaimedSameDate->isNotEmpty()) {
                $closestStmt = $unclaimedSameDate->sortBy(fn ($s) => abs((float) $s->amount - $txAmount))->first();
                $diff = round((float) $closestStmt->amount - $txAmount, 2);
                $diffItem = [
                    'transaction_id' => $tx->id,
                    'transaction_ref' => $tx->secureRouteKey(),
                    'shop_name' => $shopName,
                    'category_name' => $categoryName,
                    'business_date' => $txDate,
                    'expected_amount' => $txAmount,
                    'configured_bank_name' => $targetBank->name,
                    'statement_id' => $closestStmt->id,
                    'statement_uuid' => $closestStmt->public_uuid,
                    'statement_amount' => (float) $closestStmt->amount,
                    'difference' => $diff,
                    'statement_reference' => $closestStmt->reference ?: $closestStmt->narration ?: '—',
                    'status' => 'amount_difference',
                    'reason' => 'Amount Difference',
                ];
                $amountDifferences[] = $diffItem;
                $amountDifferencesAmount += $txAmount;
                $groupedByBank[$bankGroupKey]['amount_differences'][] = $diffItem;

                continue;
            }

            // 4. Nearby Date with Exact Amount in Target Bank (Within graceDays)
            $nearbyStatements = $bankStatements
                ->filter(fn (CompanyAccountStatementEntry $s) => ! in_array($s->id, $claimed, true)
                    && abs((float) $s->amount - $txAmount) <= 0.01
                    && abs($s->transaction_date->diffInDays(Carbon::parse($txDate))) <= $graceDays
                )
                ->values();

            if ($nearbyStatements->count() === 1) {
                $nearbyStmt = $nearbyStatements->first();
                $daysApart = abs($nearbyStmt->transaction_date->diffInDays(Carbon::parse($txDate)));
                $nearItem = [
                    'transaction_id' => $tx->id,
                    'transaction_ref' => $tx->secureRouteKey(),
                    'shop_name' => $shopName,
                    'category_name' => $categoryName,
                    'business_date' => $txDate,
                    'amount' => $txAmount,
                    'configured_bank_name' => $targetBank->name,
                    'statement_id' => $nearbyStmt->id,
                    'statement_uuid' => $nearbyStmt->public_uuid,
                    'statement_date' => $nearbyStmt->transaction_date->toDateString(),
                    'days_difference' => $daysApart,
                    'statement_reference' => $nearbyStmt->reference ?: $nearbyStmt->narration ?: '—',
                    'status' => 'nearby_date',
                    'reason' => 'Nearby Date Match',
                ];
                $nearbyDates[] = $nearItem;
                $nearbyDatesAmount += $txAmount;
                $groupedByBank[$bankGroupKey]['nearby_dates'][] = $nearItem;

                continue;
            }

            if ($nearbyStatements->count() > 1) {
                $ambNearItem = [
                    'transaction_id' => $tx->id,
                    'transaction_ref' => $tx->secureRouteKey(),
                    'shop_name' => $shopName,
                    'category_name' => $categoryName,
                    'business_date' => $txDate,
                    'amount' => $txAmount,
                    'configured_bank_name' => $targetBank->name,
                    'statement_count' => $nearbyStatements->count(),
                    'status' => 'ambiguous',
                    'reason' => "{$nearbyStatements->count()} nearby statement entries in {$targetBank->name} with exact amount",
                ];
                $ambiguous[] = $ambNearItem;
                $ambiguousAmount += $txAmount;
                $groupedByBank[$bankGroupKey]['ambiguous'][] = $ambNearItem;

                continue;
            }

            // 5. No Amount Match in Target Bank
            $noAmountMatchItem = [
                'transaction_id' => $tx->id,
                'transaction_ref' => $tx->secureRouteKey(),
                'shop_name' => $shopName,
                'category_name' => $categoryName,
                'business_date' => $txDate,
                'amount' => $txAmount,
                'configured_bank_id' => $targetBankId,
                'configured_bank_name' => $targetBank->name,
                'status' => 'no_amount_match',
                'reason' => 'No Amount Match',
                'message' => 'No statement entry matching ₹'.number_format($txAmount, 2)." found in {$targetBank->name}.",
            ];
            $noAmountMatch[] = $noAmountMatchItem;
            $noAmountMatchAmount += $txAmount;
            $noMatch[] = $noAmountMatchItem;
            $noMatchAmount += $txAmount;
            $groupedByBank[$bankGroupKey]['no_amount_match'][] = $noAmountMatchItem;
            $groupedByBank[$bankGroupKey]['no_match'][] = $noAmountMatchItem;
        }

        return [
            'period' => [
                'month_start' => $monthStart,
                'month_end' => $monthEnd,
            ],
            'filters' => [
                'company_account_id' => $companyAccountId,
                'shop_id' => $shopId,
                'entry_type_id' => $entryTypeId,
            ],
            'summary' => [
                'total_collections_count' => $transactions->count(),
                'total_collections_amount' => round($totalCollectionsAmount, 2),
                'exact_matches_count' => count($exactMatches),
                'exact_matches_amount' => round($exactMatchesAmount, 2),
                'amount_differences_count' => count($amountDifferences),
                'amount_differences_amount' => round($amountDifferencesAmount, 2),
                'nearby_dates_count' => count($nearbyDates),
                'nearby_dates_amount' => round($nearbyDatesAmount, 2),
                'ambiguous_count' => count($ambiguous),
                'ambiguous_amount' => round($ambiguousAmount, 2),
                'no_match_count' => count($noMatch),
                'no_match_amount' => round($noMatchAmount, 2),
                'no_statement_data_count' => count($noStatementData),
                'no_statement_data_amount' => round($noStatementDataAmount, 2),
                'outside_coverage_count' => count($outsideCoverage),
                'outside_coverage_amount' => round($outsideCoverageAmount, 2),
                'no_amount_match_count' => count($noAmountMatch),
                'no_amount_match_amount' => round($noAmountMatchAmount, 2),
                'bank_mapping_mismatches_count' => count($bankMappingMismatches),
                'bank_mapping_mismatches_amount' => round($bankMappingMismatchesAmount, 2),
                'bank_not_configured_count' => count($bankNotConfigured),
                'bank_not_configured_amount' => round($bankNotConfiguredAmount, 2),
                'already_reconciled_count' => count($alreadyReconciled),
                'already_reconciled_amount' => round($alreadyReconciledAmount, 2),
                'duplicate_sources_count' => count($duplicateSources),
            ],
            'grouped_by_bank' => $groupedByBank,
            'exact_matches' => $exactMatches,
            'bank_mapping_mismatches' => $bankMappingMismatches,
            'bank_not_configured' => $bankNotConfigured,
        ];
    }

    /**
     * Reassign unreconciled transactions with bank mapping mismatch to their current configured bank setting.
     *
     * @param  array<int, int>  $transactionIds
     * @return array{reassigned_count: int, reassigned_amount: float, skipped_count: int}
     */
    public function reassignToConfiguredBank(array $transactionIds, int $userId): array
    {
        $reassignedCount = 0;
        $reassignedAmount = 0.0;
        $skippedCount = 0;

        foreach ($transactionIds as $txId) {
            DB::transaction(function () use ($txId, &$reassignedCount, &$reassignedAmount, &$skippedCount): void {
                /** @var ShopLedgerTransaction|null $tx */
                $tx = ShopLedgerTransaction::query()
                    ->whereKey($txId)
                    ->lockForUpdate()
                    ->first();

                if (! $tx) {
                    $skippedCount++;

                    return;
                }

                // Strict guard: Reconciled transactions must NEVER be altered
                if ($tx->isReconciled()) {
                    $skippedCount++;

                    return;
                }

                // Find active setting for this shop + category
                /** @var ShopLedgerEntrySetting|null $setting */
                $setting = ShopLedgerEntrySetting::query()
                    ->where('shop_id', $tx->shop_id)
                    ->where('entry_type_id', $tx->entry_type_id)
                    ->first();

                if (! $setting || ! $setting->company_account_id) {
                    $skippedCount++;

                    return;
                }

                // If already matching, skip
                if ((int) $tx->company_account_id === (int) $setting->company_account_id) {
                    return;
                }

                $tx->company_account_id = $setting->company_account_id;
                $tx->save();

                $reassignedCount++;
                $reassignedAmount += (float) $tx->amount;
            }, attempts: 3);
        }

        return [
            'reassigned_count' => $reassignedCount,
            'reassigned_amount' => round($reassignedAmount, 2),
            'skipped_count' => $skippedCount,
        ];
    }

    /**
     * Execute auto-matching for exact deterministic matches.
     *
     * @return array{
     *     period: array{month_start: string, month_end: string},
     *     reconciled_count: int,
     *     reconciled_amount: float,
     *     skipped_count: int,
     *     skipped_amount: float
     * }
     */
    public function execute(
        string $monthStart,
        string $monthEnd,
        ?int $companyAccountId = null,
        int $userId = 1
    ): array {
        $preview = $this->preview($monthStart, $monthEnd, $companyAccountId);

        $reconciledCount = 0;
        $reconciledAmount = 0.0;
        $skippedCount = 0;
        $skippedAmount = 0.0;

        foreach ($preview['exact_matches'] as $match) {
            try {
                DB::transaction(function () use ($match, $userId, &$reconciledCount, &$reconciledAmount, &$skippedCount, &$skippedAmount): void {
                    /** @var ShopLedgerTransaction|null $tx */
                    $tx = ShopLedgerTransaction::query()
                        ->whereKey($match['transaction_id'])
                        ->lockForUpdate()
                        ->first();

                    /** @var CompanyAccountStatementEntry|null $stmt */
                    $stmt = CompanyAccountStatementEntry::query()
                        ->whereKey($match['statement_id'])
                        ->lockForUpdate()
                        ->first();

                    if (! $tx || ! $stmt) {
                        $skippedCount++;
                        $skippedAmount += (float) ($match['amount'] ?? 0);

                        return;
                    }

                    // Strict concurrency & precondition validation
                    if ($tx->isReconciled() || $stmt->is_finalized) {
                        $skippedCount++;
                        $skippedAmount += (float) $tx->amount;

                        return;
                    }

                    if ((int) $tx->company_account_id !== (int) $stmt->company_account_id) {
                        $skippedCount++;
                        $skippedAmount += (float) $tx->amount;

                        return;
                    }

                    if (abs((float) $tx->amount - (float) $stmt->amount) > 0.01) {
                        $skippedCount++;
                        $skippedAmount += (float) $tx->amount;

                        return;
                    }

                    if ($tx->business_date->toDateString() !== $stmt->transaction_date->toDateString()) {
                        $skippedCount++;
                        $skippedAmount += (float) $tx->amount;

                        return;
                    }

                    $this->reconciliationService->reconcileStatementShopLedger(
                        $stmt,
                        $tx,
                        (float) $stmt->amount,
                        $userId
                    );

                    $reconciledCount++;
                    $reconciledAmount += (float) $tx->amount;
                }, attempts: 3);
            } catch (Throwable $e) {
                report($e);
                $skippedCount++;
                $skippedAmount += (float) ($match['amount'] ?? 0);
            }
        }

        return [
            'period' => [
                'month_start' => $monthStart,
                'month_end' => $monthEnd,
            ],
            'reconciled_count' => $reconciledCount,
            'reconciled_amount' => round($reconciledAmount, 2),
            'skipped_count' => $skippedCount,
            'skipped_amount' => round($skippedAmount, 2),
        ];
    }
}
