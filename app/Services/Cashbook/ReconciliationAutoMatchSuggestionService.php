<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccountStatementEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class ReconciliationAutoMatchSuggestionService
{
    public function __construct(private readonly CompanyPaymentReconciliationService $reconciliationService) {}

    /**
     * @param  Collection<int, object>  $transactions
     * @return Collection<int, object>
     */
    public function suggest(Collection $transactions, int $graceDays): Collection
    {
        $candidatePools = $this->reconciliationService->findEligibleStatementCandidatePools($transactions, $graceDays);

        return $transactions->map(fn (object $transaction): object => $this->classify($transaction, $candidatePools, $graceDays));
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

        $key = $accountId.'|'.$transaction->direction.'|'.round((float) $transaction->amount, 2);
        $transactionDate = Carbon::parse((string) $transaction->transaction_date);
        $candidates = ($candidatePools->get($key) ?? collect())
            ->map(function (CompanyAccountStatementEntry $entry) use ($transaction, $transactionDate): array {
                $difference = abs($entry->transaction_date->diffInDays($transactionDate, false));

                return [
                    'entry' => $entry,
                    'date_difference_days' => $difference,
                    'reference_exact' => filled($transaction->reference) && strcasecmp((string) $entry->reference, (string) $transaction->reference) === 0,
                    'reference_similarity' => $this->similarity((string) $transaction->reference, (string) $entry->reference),
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
