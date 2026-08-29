<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\ShopLedgerTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class ReconciliationAutoMatchSuggestionService
{
    public function __construct(
        private readonly CompanyPaymentReconciliationService $reconciliationService,
        private readonly BankSettlementExpectedAmountService $expectedAmountService = new BankSettlementExpectedAmountService,
    ) {}

    /**
     * @param  Collection<int, object>  $transactions
     * @return Collection<int, object>
     */
    public function suggest(Collection $transactions, int $graceDays): Collection
    {
        $this->enrichWithExpectedAmounts($transactions);

        $candidatePools = $this->reconciliationService->findEligibleStatementCandidatePools($transactions, $graceDays);

        return $transactions->map(fn (object $transaction): object => $this->classify($transaction, $candidatePools, $graceDays));
    }

    /**
     * Enriches shop collection candidate transactions with expected bank settlement amounts.
     *
     * @param  Collection<int, object>  $transactions
     */
    private function enrichWithExpectedAmounts(Collection $transactions): void
    {
        $shopCandidates = $transactions->filter(function (object $t): bool {
            $isShopLedger = ($t instanceof ShopLedgerTransaction)
                || (($t->source_type ?? null) === ShopLedgerTransaction::class)
                || (($t->transaction_type_key ?? null) === 'shop_collection');
            $dir = (string) ($t->direction ?? '');
            $isIncome = in_array(strtolower($dir), ['income', 'in'], true);

            return $isShopLedger && $isIncome;
        });

        if ($shopCandidates->isEmpty()) {
            return;
        }

        // Identify any missing shop_id or entry_type_id to fetch in a single batch query
        $missingSourceIds = [];
        foreach ($shopCandidates as $c) {
            $sId = (int) ($c->shop_id ?? 0);
            $eId = (int) ($c->entry_type_id ?? 0);
            $sourceId = (int) ($c->source_id ?? ($c->id ?? 0));
            if (($sId === 0 || $eId === 0) && $sourceId > 0) {
                $missingSourceIds[] = $sourceId;
            }
        }

        $loadedTxMap = empty($missingSourceIds)
            ? collect()
            : ShopLedgerTransaction::query()
                ->whereIn('id', array_unique($missingSourceIds))
                ->get()
                ->keyBy('id');

        $bulkItems = [];
        foreach ($shopCandidates as $c) {
            $sId = (int) ($c->shop_id ?? 0);
            $eId = (int) ($c->entry_type_id ?? 0);
            $sourceId = (int) ($c->source_id ?? ($c->id ?? 0));
            $bDate = (string) ($c->business_date ?? $c->transaction_date ?? '');

            if (($sId === 0 || $eId === 0) && $sourceId > 0 && $loadedTxMap->has($sourceId)) {
                $loaded = $loadedTxMap->get($sourceId);
                $sId = (int) $loaded->shop_id;
                $eId = (int) $loaded->entry_type_id;
                $bDate = $loaded->business_date ? $loaded->business_date->toDateString() : $bDate;
                $c->shop_id = $sId;
                $c->entry_type_id = $eId;
            }

            $amt = (float) ($c->amount ?? 0);

            if ($sId > 0 && $eId > 0 && $bDate !== '') {
                $bulkItems[] = [
                    'shop_id' => $sId,
                    'entry_type_id' => $eId,
                    'business_date' => $bDate,
                    'base_amount' => $amt,
                ];
            }
        }

        $resolvedMap = empty($bulkItems)
            ? collect()
            : $this->expectedAmountService->resolveBulk($bulkItems);

        foreach ($shopCandidates as $c) {
            $sId = (int) ($c->shop_id ?? 0);
            $eId = (int) ($c->entry_type_id ?? 0);
            $bDate = (string) ($c->business_date ?? $c->transaction_date ?? '');
            if ($bDate !== '') {
                $bDate = Carbon::parse($bDate)->toDateString();
            }
            $key = "{$sId}_{$bDate}_{$eId}";

            if ($resolvedMap->has($key)) {
                $res = $resolvedMap->get($key);
                $c->base_collection_amount = (float) $res['base_amount'];
                $c->plus_adjustments = (float) $res['plus_adjustments'];
                $c->minus_adjustments = (float) $res['minus_adjustments'];
                $c->adjustment_total = (float) $res['adjustment_total'];
                $c->expected_bank_amount = (float) $res['expected_amount'];
                $c->effective_match_amount = (float) $res['expected_amount'];
            } else {
                $c->effective_match_amount = round((float) ($c->amount ?? 0), 2);
            }
        }
    }

    /**
     * @param  Collection<string, Collection<int, CompanyAccountStatementEntry>>  $candidatePools
     */
    private function classify(object $transaction, Collection $candidatePools, int $graceDays): object
    {
        if ($transaction->reconciliation_status === 'RECONCILED') {
            return $transaction;
        }

        $accountId = (int) ($transaction->company_account_id ?? 0);
        if ($accountId <= 0) {
            return $this->setNoMatch($transaction, 'Company account is not recorded for this transaction.');
        }

        $dir = strtolower((string) ($transaction->direction ?? ''));
        $normDirection = match ($dir) {
            'income', 'in' => 'in',
            'expense', 'out' => 'out',
            default => $dir,
        };

        $matchAmount = round((float) ($transaction->effective_match_amount ?? $transaction->amount), 2);
        $key = $accountId.'|'.$normDirection.'|'.$matchAmount;
        $transactionDate = Carbon::parse((string) ($transaction->business_date ?? $transaction->transaction_date));
        $candidates = ($candidatePools->get($key) ?? collect())
            ->map(function (CompanyAccountStatementEntry $entry) use ($transaction, $transactionDate): array {
                $difference = abs($entry->transaction_date->diffInDays($transactionDate, false));

                return [
                    'entry' => $entry,
                    'date_difference_days' => $difference,
                    'reference_exact' => filled($transaction->reference ?? null) && strcasecmp((string) $entry->reference, (string) ($transaction->reference ?? '')) === 0,
                    'reference_similarity' => $this->similarity((string) ($transaction->reference ?? ''), (string) $entry->reference),
                    'narration_similarity' => $this->similarity(trim(($transaction->party_name ?? '').' '.($transaction->description ?? '')), (string) $entry->narration),
                ];
            })
            ->filter(fn (array $candidate): bool => $candidate['date_difference_days'] <= $graceDays)
            ->sortBy([
                ['date_difference_days', 'asc'],
                ['reference_exact', 'desc'],
                ['reference_similarity', 'desc'],
                ['narration_similarity', 'desc'],
                [fn (array $candidate): string => $candidate['entry']->transaction_date->toDateString(), 'desc'],
                [fn (array $candidate): int => $candidate['entry']->id, 'desc'],
            ])
            ->values();

        $exactCandidates = $candidates->where('date_difference_days', 0);
        if ($exactCandidates->count() === 1) {
            return $this->setSuggestion($transaction, $exactCandidates->first(), 'high', 'Same account, direction, amount, and date; one eligible statement.');
        }

        if ($exactCandidates->count() > 1) {
            return $this->setNeedsReview($transaction, $exactCandidates->count().' possible exact-date matches.');
        }

        if ($candidates->isEmpty()) {
            return $this->setNoMatch($transaction, 'No eligible statement found in the configured date window.');
        }

        $closestDifference = (int) $candidates->first()['date_difference_days'];
        $closestCandidates = $candidates->where('date_difference_days', $closestDifference);
        if ($closestCandidates->count() > 1) {
            return $this->setNeedsReview($transaction, $closestCandidates->count().' equally close statement matches.');
        }

        return $this->setSuggestion($transaction, $closestCandidates->first(), 'likely', $closestDifference.' day'.($closestDifference === 1 ? '' : 's').' apart.');
    }

    private function setSuggestion(object $transaction, array $candidate, string $confidence, string $reason): object
    {
        /** @var CompanyAccountStatementEntry $entry */
        $entry = $candidate['entry'];
        $transaction->reconciliation_status = 'SUGGESTED';
        $transaction->suggestion = [
            'status' => 'SUGGESTED',
            'confidence' => $confidence,
            'reason' => $reason,
            'statement_entry_id' => $entry->id,
            'statement_uuid' => $entry->public_uuid,
            'statement_date' => $entry->transaction_date->toDateString(),
            'statement_amount' => (float) $entry->amount,
            'statement_reference' => $entry->reference,
            'statement_narration' => $entry->narration,
            'company_account_id' => $entry->company_account_id,
            'company_account_name' => $entry->companyAccount?->name,
            'date_match' => $candidate['date_difference_days'] === 0 ? 'exact' : 'nearby',
            'date_difference_days' => $candidate['date_difference_days'],
            'eligible_candidate_count' => 1,
            'base_collection_amount' => isset($transaction->base_collection_amount) ? (float) $transaction->base_collection_amount : (float) $transaction->amount,
            'adjustment_total' => isset($transaction->adjustment_total) ? (float) $transaction->adjustment_total : 0.0,
            'expected_bank_amount' => isset($transaction->expected_bank_amount) ? (float) $transaction->expected_bank_amount : (float) $transaction->amount,
        ];

        return $transaction;
    }

    private function setNeedsReview(object $transaction, string $reason): object
    {
        $transaction->reconciliation_status = 'NEEDS_REVIEW';
        $transaction->suggestion = ['status' => 'NEEDS_REVIEW', 'confidence' => null, 'reason' => $reason];

        return $transaction;
    }

    private function setNoMatch(object $transaction, string $reason): object
    {
        $transaction->reconciliation_status = 'NEEDS_REVIEW';
        $transaction->suggestion = ['status' => 'NO_MATCH', 'confidence' => null, 'reason' => $reason];

        return $transaction;
    }

    private function similarity(string $left, string $right): int
    {
        $left = mb_strtolower(trim($left));
        $right = mb_strtolower(trim($right));

        if ($left === '' || $right === '') {
            return 0;
        }

        similar_text($left, $right, $percent);

        return (int) round($percent);
    }
}
