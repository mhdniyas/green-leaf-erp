<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HistoricalBankCollectionFetchService
{
    public function __construct(
        private readonly BankSettlementExpectedAmountService $expectedAmountService = new BankSettlementExpectedAmountService
    ) {}

    /**
     * Preview historical transactions classification for a shop, category, and period.
     *
     * @return array<string, mixed>
     */
    public function preview(
        int $shopId,
        int $entryTypeId,
        int $companyAccountId,
        string $fromDate,
        string $toDate
    ): array {
        $this->validatePeriod($fromDate, $toDate);

        $shop = Shop::findOrFail($shopId);
        $entryType = LedgerEntryType::findOrFail($entryTypeId);
        $account = CompanyAccount::findOrFail($companyAccountId);

        $transactions = ShopLedgerTransaction::query()
            ->where('shop_id', $shop->id)
            ->where('entry_type_id', $entryType->id)
            ->whereBetween('business_date', [$fromDate, $toDate])
            ->orderBy('business_date')
            ->get();

        $expectedMap = $this->expectedAmountService->resolveBulk($transactions);

        $classification = $this->classifyTransactions($transactions, $account->id, $expectedMap);

        // Detect potential same-date amount differences against unmatched bank statements
        $sameDateDifferences = $this->detectSameDateAmountDifferences(
            $transactions,
            $account->id,
            $fromDate,
            $toDate,
            $expectedMap
        );

        return [
            'shop' => [
                'id' => $shop->id,
                'name' => $shop->name,
            ],
            'entry_type' => [
                'id' => $entryType->id,
                'name' => $entryType->name,
                'code' => $entryType->code,
            ],
            'company_account' => [
                'id' => $account->id,
                'name' => $account->name,
                'bank_name' => $account->bank_name,
                'public_uuid' => $account->public_uuid,
            ],
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'source_count' => $classification['source_count'],
            'source_amount' => $classification['source_amount'],
            'source_base_amount' => $classification['source_base_amount'],
            'source_adjustment_amount' => $classification['source_adjustment_amount'],
            'source_expected_amount' => $classification['source_expected_amount'],
            'eligible_count' => $classification['eligible_count'],
            'eligible_amount' => $classification['eligible_amount'],
            'eligible_base_amount' => $classification['eligible_base_amount'],
            'eligible_adjustment_amount' => $classification['eligible_adjustment_amount'],
            'eligible_expected_amount' => $classification['eligible_expected_amount'],
            'already_linked_count' => $classification['already_linked_count'],
            'already_linked_amount' => $classification['already_linked_amount'],
            'different_bank_count' => $classification['different_bank_count'],
            'different_bank_amount' => $classification['different_bank_amount'],
            'different_banks_detail' => $classification['different_banks_detail'],
            'reconciled_count' => $classification['reconciled_count'],
            'reconciled_amount' => $classification['reconciled_amount'],
            'void_count' => $classification['void_count'],
            'void_amount' => $classification['void_amount'],
            'duplicate_source_warnings_count' => $classification['duplicate_source_warnings_count'],
            'duplicate_source_warnings_detail' => $classification['duplicate_source_warnings_detail'],
            'same_date_amount_differences_count' => count($sameDateDifferences),
            'same_date_amount_differences_detail' => $sameDateDifferences,
            'eligible_ids' => $classification['eligible_ids'],
        ];
    }

    /**
     * Fetch and link eligible historical transactions to the configured company account.
     *
     * @return array<string, mixed>
     */
    public function fetch(
        int $shopId,
        int $entryTypeId,
        int $companyAccountId,
        string $fromDate,
        string $toDate,
        ?int $performedBy = null
    ): array {
        $this->validatePeriod($fromDate, $toDate);

        $shop = Shop::findOrFail($shopId);
        $entryType = LedgerEntryType::findOrFail($entryTypeId);
        $account = CompanyAccount::findOrFail($companyAccountId);

        return DB::transaction(function () use ($shop, $entryType, $account, $fromDate, $toDate, $performedBy): array {
            $transactions = ShopLedgerTransaction::query()
                ->where('shop_id', $shop->id)
                ->where('entry_type_id', $entryType->id)
                ->whereBetween('business_date', [$fromDate, $toDate])
                ->lockForUpdate()
                ->get();

            $expectedMap = $this->expectedAmountService->resolveBulk($transactions);
            $classification = $this->classifyTransactions($transactions, $account->id, $expectedMap);
            $eligibleIds = $classification['eligible_ids'];

            $updatedCount = 0;
            $updatedAmount = 0.0;

            if (! empty($eligibleIds)) {
                $updatedCount = ShopLedgerTransaction::query()
                    ->whereIn('id', $eligibleIds)
                    ->whereNull('company_account_id')
                    ->whereNotIn('status', ['void', 'voided'])
                    ->update(['company_account_id' => $account->id]);

                $updatedAmount = (float) $classification['eligible_amount'];
            }

            if (function_exists('activity')) {
                $actor = $performedBy ? User::find($performedBy) : null;
                $activity = activity('cashbook_historical_fetch')
                    ->performedOn($shop)
                    ->withProperties([
                        'shop_id' => $shop->id,
                        'shop_name' => $shop->name,
                        'entry_type_id' => $entryType->id,
                        'entry_type_name' => $entryType->name,
                        'company_account_id' => $account->id,
                        'company_account_name' => $account->name,
                        'from_date' => $fromDate,
                        'to_date' => $toDate,
                        'source_count' => $classification['source_count'],
                        'eligible_count' => $classification['eligible_count'],
                        'updated_count' => $updatedCount,
                        'updated_amount' => $updatedAmount,
                        'already_linked_count' => $classification['already_linked_count'],
                        'different_bank_count' => $classification['different_bank_count'],
                        'reconciled_count' => $classification['reconciled_count'],
                        'void_count' => $classification['void_count'],
                    ]);

                if ($actor) {
                    $activity->causedBy($actor);
                }

                $activity->log("Fetched {$updatedCount} historical {$entryType->name} collections to {$account->name}");
            }

            return [
                'shop' => [
                    'id' => $shop->id,
                    'name' => $shop->name,
                ],
                'entry_type' => [
                    'id' => $entryType->id,
                    'name' => $entryType->name,
                ],
                'company_account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'public_uuid' => $account->public_uuid,
                ],
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'updated_count' => $updatedCount,
                'updated_amount' => $updatedAmount,
                'skipped' => [
                    'already_linked_count' => $classification['already_linked_count'],
                    'already_linked_amount' => $classification['already_linked_amount'],
                    'different_bank_count' => $classification['different_bank_count'],
                    'different_bank_amount' => $classification['different_bank_amount'],
                    'different_banks_detail' => $classification['different_banks_detail'],
                    'reconciled_count' => $classification['reconciled_count'],
                    'reconciled_amount' => $classification['reconciled_amount'],
                    'void_count' => $classification['void_count'],
                    'void_amount' => $classification['void_amount'],
                ],
            ];
        });
    }

    /**
     * @param  Collection<int, ShopLedgerTransaction>  $transactions
     * @param  Collection<string, array{base_amount: float, plus_adjustments: float, minus_adjustments: float, adjustment_total: float, expected_amount: float}>  $expectedMap
     * @return array<string, mixed>
     */
    private function classifyTransactions(Collection $transactions, int $targetAccountId, SupportCollection $expectedMap): array
    {
        $txIds = $transactions->pluck('id')->all();

        // Batch query reconciled transactions (no N+1)
        $reconciledIds = empty($txIds)
            ? []
            : CompanyAccountStatementEntry::query()
                ->where('source_type', ShopLedgerTransaction::class)
                ->whereIn('source_id', $txIds)
                ->where('is_finalized', true)
                ->pluck('source_id')
                ->map(fn ($id) => (int) $id)
                ->flip()
                ->all();

        // Batch query company account names for different bank details
        $differentAccountIds = $transactions
            ->pluck('company_account_id')
            ->filter(fn ($id) => $id !== null && (int) $id !== $targetAccountId)
            ->unique()
            ->all();

        $accountNames = empty($differentAccountIds)
            ? []
            : CompanyAccount::query()
                ->whereIn('id', $differentAccountIds)
                ->pluck('name', 'id')
                ->all();

        $sourceCount = $transactions->count();
        $sourceBaseAmount = 0.0;
        $sourceAdjustmentAmount = 0.0;
        $sourceExpectedAmount = 0.0;

        $eligibleCount = 0;
        $eligibleBaseAmount = 0.0;
        $eligibleAdjustmentAmount = 0.0;
        $eligibleExpectedAmount = 0.0;
        $eligibleIds = [];

        $alreadyLinkedCount = 0;
        $alreadyLinkedAmount = 0.0;

        $differentBankCount = 0;
        $differentBankAmount = 0.0;
        $differentBanksDetail = [];

        $reconciledCount = 0;
        $reconciledAmount = 0.0;

        $voidCount = 0;
        $voidAmount = 0.0;

        foreach ($transactions as $tx) {
            $baseAmt = round((float) $tx->amount, 2);
            $bDate = $tx->business_date?->toDateString() ?? '';
            $mapKey = "{$tx->shop_id}_{$bDate}_{$tx->entry_type_id}";
            $adjInfo = $expectedMap->get($mapKey) ?? [
                'base_amount' => $baseAmt,
                'plus_adjustments' => 0.0,
                'minus_adjustments' => 0.0,
                'adjustment_total' => 0.0,
                'expected_amount' => $baseAmt,
            ];

            $expectedAmt = (float) $adjInfo['expected_amount'];
            $adjAmt = (float) $adjInfo['adjustment_total'];

            $sourceBaseAmount += $baseAmt;
            $sourceAdjustmentAmount += $adjAmt;
            $sourceExpectedAmount += $expectedAmt;

            // 1. Void / Excluded
            if (in_array($tx->status, ['void', 'voided'], true)) {
                $voidCount++;
                $voidAmount += $expectedAmt;

                continue;
            }

            // 2. Reconciled / Locked (checked batch-wise)
            if (isset($reconciledIds[$tx->id])) {
                $reconciledCount++;
                $reconciledAmount += $expectedAmt;

                continue;
            }

            // 3. Already Assigned to the Target Bank
            if ($tx->company_account_id !== null && (int) $tx->company_account_id === $targetAccountId) {
                $alreadyLinkedCount++;
                $alreadyLinkedAmount += $expectedAmt;

                continue;
            }

            // 4. Assigned to a Different Bank
            if ($tx->company_account_id !== null && (int) $tx->company_account_id !== $targetAccountId) {
                $differentBankCount++;
                $differentBankAmount += $expectedAmt;
                $bankId = (int) $tx->company_account_id;
                $bankName = $accountNames[$bankId] ?? "Account #{$bankId}";

                if (! isset($differentBanksDetail[$bankId])) {
                    $differentBanksDetail[$bankId] = [
                        'bank_name' => $bankName,
                        'count' => 0,
                        'amount' => 0.0,
                    ];
                }
                $differentBanksDetail[$bankId]['count']++;
                $differentBanksDetail[$bankId]['amount'] += $expectedAmt;

                continue;
            }

            // 5. Eligible (company_account_id is null, not void, not reconciled)
            $eligibleCount++;
            $eligibleBaseAmount += $baseAmt;
            $eligibleAdjustmentAmount += $adjAmt;
            $eligibleExpectedAmount += $expectedAmt;
            $eligibleIds[] = (int) $tx->id;
        }

        // Detect Duplicate Source Warnings (multiple active transactions on same business_date)
        $activeTxs = $transactions->filter(fn ($t) => ! in_array($t->status, ['void', 'voided'], true));
        $dateGroups = $activeTxs->groupBy(fn ($t) => $t->business_date?->toDateString() ?? '');
        $duplicateSourceWarningsDetail = [];

        foreach ($dateGroups as $dateStr => $txsForDate) {
            if ($dateStr !== '' && $txsForDate->count() > 1) {
                $duplicateSourceWarningsDetail[] = [
                    'business_date' => $dateStr,
                    'count' => $txsForDate->count(),
                    'total_amount' => round((float) $txsForDate->sum('amount'), 2),
                    'transaction_ids' => $txsForDate->pluck('id')->all(),
                ];
            }
        }

        return [
            'source_count' => $sourceCount,
            'source_amount' => round($sourceExpectedAmount, 2),
            'source_base_amount' => round($sourceBaseAmount, 2),
            'source_adjustment_amount' => round($sourceAdjustmentAmount, 2),
            'source_expected_amount' => round($sourceExpectedAmount, 2),
            'eligible_count' => $eligibleCount,
            'eligible_amount' => round($eligibleExpectedAmount, 2),
            'eligible_base_amount' => round($eligibleBaseAmount, 2),
            'eligible_adjustment_amount' => round($eligibleAdjustmentAmount, 2),
            'eligible_expected_amount' => round($eligibleExpectedAmount, 2),
            'eligible_ids' => $eligibleIds,
            'already_linked_count' => $alreadyLinkedCount,
            'already_linked_amount' => round($alreadyLinkedAmount, 2),
            'different_bank_count' => $differentBankCount,
            'different_bank_amount' => round($differentBankAmount, 2),
            'different_banks_detail' => array_values($differentBanksDetail),
            'reconciled_count' => $reconciledCount,
            'reconciled_amount' => round($reconciledAmount, 2),
            'void_count' => $voidCount,
            'void_amount' => round($voidAmount, 2),
            'duplicate_source_warnings_count' => count($duplicateSourceWarningsDetail),
            'duplicate_source_warnings_detail' => $duplicateSourceWarningsDetail,
        ];
    }

    /**
     * @param  Collection<int, ShopLedgerTransaction>  $transactions
     * @param  Collection<string, array{base_amount: float, plus_adjustments: float, minus_adjustments: float, adjustment_total: float, expected_amount: float}>  $expectedMap
     * @return list<array<string, mixed>>
     */
    private function detectSameDateAmountDifferences(
        Collection $transactions,
        int $targetAccountId,
        string $fromDate,
        string $toDate,
        SupportCollection $expectedMap
    ): array {
        $activeTxs = $transactions->filter(fn ($t) => ! in_array($t->status, ['void', 'voided'], true));
        $dateGroups = $activeTxs->groupBy(fn ($t) => $t->business_date?->toDateString() ?? '');

        if ($dateGroups->isEmpty()) {
            return [];
        }

        $unmatchedStatements = CompanyAccountStatementEntry::query()
            ->where('company_account_id', $targetAccountId)
            ->where('direction', 'in')
            ->where('is_finalized', false)
            ->where('status', 'unmatched')
            ->whereBetween('transaction_date', [$fromDate, $toDate])
            ->get()
            ->groupBy(fn ($s) => $s->transaction_date?->toDateString() ?? '');

        $differences = [];

        foreach ($dateGroups as $dateStr => $txsForDate) {
            if ($dateStr === '' || ! $unmatchedStatements->has($dateStr)) {
                continue;
            }

            $firstTx = $txsForDate->first();
            $mapKey = "{$firstTx->shop_id}_{$dateStr}_{$firstTx->entry_type_id}";
            $adjInfo = $expectedMap->get($mapKey);
            $expectedAmount = $adjInfo ? (float) $adjInfo['expected_amount'] : round((float) $firstTx->amount, 2);

            $stmtsOnDate = $unmatchedStatements->get($dateStr);

            // Check if there is an exact matching statement on this date
            $hasExact = $stmtsOnDate->contains(fn ($s) => abs((float) $s->amount - $expectedAmount) < 0.01);
            if (! $hasExact) {
                foreach ($stmtsOnDate as $stmt) {
                    $stmtAmount = round((float) $stmt->amount, 2);
                    $differences[] = [
                        'business_date' => $dateStr,
                        'expected_amount' => $expectedAmount,
                        'statement_amount' => $stmtAmount,
                        'difference' => round($stmtAmount - $expectedAmount, 2),
                        'statement_reference' => $stmt->reference,
                        'statement_narration' => $stmt->narration,
                    ];
                }
            }
        }

        return $differences;
    }

    /**
     * Validate date boundaries.
     *
     * @throws ValidationException
     */
    private function validatePeriod(string $fromDate, string $toDate): void
    {
        try {
            $from = Carbon::parse($fromDate)->startOfDay();
            $to = Carbon::parse($toDate)->startOfDay();
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'period' => 'Invalid date format provided for historical period fetch.',
            ]);
        }

        if ($from->gt($to)) {
            throw ValidationException::withMessages([
                'from_date' => 'The from date must be on or before the to date.',
            ]);
        }
    }
}
