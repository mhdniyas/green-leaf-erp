<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentCompanyPayableMatch;
use App\Models\ShopInvoicePaymentRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyPayableReceiptMatchingService
{
    private const MATCH_TOLERANCE = 0.01;

    public function __construct(
        private readonly ShopSettlementService $settlementService,
        private readonly RelationSettlementCalculator $relationCalculator,
        private readonly ShopAccountingOpeningService $openingService,
    ) {}

    /**
     * Get all business dates with outstanding (unmatched) Company Payable, ordered oldest first (FIFO).
     *
     * @return Collection<int, array{
     *     business_date: string,
     *     formatted_date: string,
     *     gross_payable: float,
     *     already_matched: float,
     *     remaining_due: float
     * }>
     */
    public function getOutstandingPayables(int $shopId, ?string $throughDate = null, ?string $fromDate = null): Collection
    {
        $accountingStartDate = $this->openingService->getAccountingStartDate($shopId);
        $startDate = $fromDate ?? $accountingStartDate ?? '2026-01-01';
        $endDate = $throughDate ?? '2099-12-31';

        if ($startDate > $endDate) {
            $startDate = $endDate;
        }

        $relation = $this->settlementService->getCompanyPayableSettlement($shopId);

        if (! $relation) {
            return collect();
        }

        $relationItemSettingIds = $relation->items
            ->pluck('shop_ledger_entry_setting_id')
            ->filter()
            ->unique()
            ->all();

        if (empty($relationItemSettingIds)) {
            return collect();
        }

        // Fetch all active transactions for the relation items across the period
        $transactions = ShopLedgerTransaction::query()
            ->with(['entryType'])
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->whereNotIn('status', ['void', 'voided', 'reversed'])
            ->whereIn('entry_type_id', function ($query) use ($relationItemSettingIds, $shopId): void {
                $query->select('entry_type_id')
                    ->from('shop_ledger_entry_settings')
                    ->where('shop_id', $shopId)
                    ->whereIn('id', $relationItemSettingIds);
            })
            ->get();

        $settingsByEntryTypeId = ShopLedgerEntrySetting::query()
            ->where('shop_id', $shopId)
            ->whereIn('id', $relationItemSettingIds)
            ->pluck('id', 'entry_type_id')
            ->all();

        $txByDate = $transactions->groupBy(fn (ShopLedgerTransaction $tx) => $tx->business_date?->toDateString());

        // Fetch all active matches grouped by payable_business_date
        $matchesByDate = ShopPaymentCompanyPayableMatch::query()
            ->where('shop_id', $shopId)
            ->where('status', 'active')
            ->whereBetween('payable_business_date', [$startDate, $endDate])
            ->selectRaw('payable_business_date, SUM(amount) as total_matched')
            ->groupBy('payable_business_date')
            ->pluck('total_matched', 'payable_business_date')
            ->all();

        $results = collect();

        foreach ($txByDate as $dateStr => $dayTxs) {
            if (! $dateStr) {
                continue;
            }

            $dayAmounts = [];
            foreach ($dayTxs as $tx) {
                $settingId = $settingsByEntryTypeId[$tx->entry_type_id] ?? null;
                if ($settingId) {
                    $dayAmounts[$settingId] = ((float) ($dayAmounts[$settingId] ?? 0.0)) + (float) $tx->amount;
                }
            }

            $calc = $this->relationCalculator->calculate($relation, $dayAmounts);
            $grossPayable = round((float) ($calc['formula_net'] ?? ($calc['netSettlement'] ?? 0.0)), 2);

            if ($grossPayable <= self::MATCH_TOLERANCE) {
                continue;
            }

            $alreadyMatched = round((float) ($matchesByDate[$dateStr] ?? 0.0), 2);
            $remainingDue = round(max(0, $grossPayable - $alreadyMatched), 2);

            if ($remainingDue > self::MATCH_TOLERANCE) {
                $results->push([
                    'business_date' => $dateStr,
                    'formatted_date' => Carbon::parse($dateStr)->format('d M Y'),
                    'gross_payable' => $grossPayable,
                    'already_matched' => $alreadyMatched,
                    'remaining_due' => $remainingDue,
                ]);
            }
        }

        return $results->sortBy('business_date')->values();
    }

    /**
     * Preview FIFO match for a verified payment request against oldest outstanding Company Payable dates.
     *
     * @return array{
     *     payment_id: int,
     *     total_payment: float,
     *     already_matched: float,
     *     unmatched_available: float,
     *     matches: array<int, array{
     *         business_date: string,
     *         formatted_date: string,
     *         gross_payable: float,
     *         already_matched: float,
     *         remaining_due: float,
     *         match_now: float
     *     }>,
     *     total_proposed_match: float
     * }
     */
    public function previewFifoMatch(ShopInvoicePaymentRequest $payment, ?string $throughDate = null): array
    {
        $paymentAmount = $this->resolveReceiptAmount($payment);
        $alreadyMatched = round((float) ShopPaymentCompanyPayableMatch::query()
            ->where('payment_request_id', $payment->id)
            ->where('status', 'active')
            ->sum('amount'), 2);

        $unmatchedAvailable = round(max(0, $paymentAmount - $alreadyMatched), 2);

        if ($unmatchedAvailable <= self::MATCH_TOLERANCE) {
            return [
                'payment_id' => (int) $payment->id,
                'total_payment' => $paymentAmount,
                'already_matched' => $alreadyMatched,
                'unmatched_available' => 0.0,
                'matches' => [],
                'total_proposed_match' => 0.0,
            ];
        }

        $outstandingPayables = $this->getOutstandingPayables((int) $payment->shop_id, $throughDate);

        $remainingToMatch = $unmatchedAvailable;
        $proposedMatches = [];
        $totalProposed = 0.0;

        foreach ($outstandingPayables as $payable) {
            if ($remainingToMatch <= self::MATCH_TOLERANCE) {
                break;
            }

            $dateRemaining = (float) $payable['remaining_due'];
            $matchNow = round(min($remainingToMatch, $dateRemaining), 2);

            if ($matchNow <= self::MATCH_TOLERANCE) {
                continue;
            }

            $proposedMatches[] = [
                'business_date' => $payable['business_date'],
                'formatted_date' => $payable['formatted_date'],
                'gross_payable' => $payable['gross_payable'],
                'already_matched' => $payable['already_matched'],
                'remaining_due' => $payable['remaining_due'],
                'match_now' => $matchNow,
                'amount' => $matchNow,
            ];

            $remainingToMatch = round($remainingToMatch - $matchNow, 2);
            $totalProposed = round($totalProposed + $matchNow, 2);
        }

        return [
            'payment_id' => (int) $payment->id,
            'total_payment' => $paymentAmount,
            'already_matched' => $alreadyMatched,
            'unmatched_available' => $unmatchedAvailable,
            'matches' => $proposedMatches,
            'total_proposed_match' => $totalProposed,
        ];
    }

    /**
     * Match a verified receipt to specific Company Payable business dates.
     *
     * @param  array<int, array{business_date: string, amount?: float|int|string, match_now?: float|int|string}>  $matches
     * @return Collection<int, ShopPaymentCompanyPayableMatch>
     */
    public function matchReceipt(ShopInvoicePaymentRequest $payment, array $matches, int $userId): Collection
    {
        if (empty($matches)) {
            throw ValidationException::withMessages([
                'matches' => 'Please provide at least one Company Payable date match.',
            ]);
        }

        return DB::transaction(function () use ($payment, $matches, $userId): Collection {
            $payment = ShopInvoicePaymentRequest::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status !== 'approved' || $payment->reconciliation_status !== 'reconciled') {
                throw ValidationException::withMessages([
                    'payment_request_id' => 'Only verified and reconciled receipts can be matched to Company Payable.',
                ]);
            }

            $shopId = (int) $payment->shop_id;
            $paymentAmount = $this->resolveReceiptAmount($payment);

            $alreadyMatched = (float) ShopPaymentCompanyPayableMatch::query()
                ->where('payment_request_id', $payment->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->sum('amount');

            $availableToMatch = round(max(0, $paymentAmount - $alreadyMatched), 2);

            $requestedTotal = 0.0;
            foreach ($matches as $match) {
                $amt = round((float) ($match['amount'] ?? ($match['match_now'] ?? 0)), 2);
                if ($amt <= 0.0) {
                    continue;
                }
                $requestedTotal += $amt;
            }

            $requestedTotal = round($requestedTotal, 2);
            if ($requestedTotal > $availableToMatch + self::MATCH_TOLERANCE) {
                throw ValidationException::withMessages([
                    'matches' => "Total match amount (₹{$requestedTotal}) cannot exceed remaining unmatched receipt balance (₹{$availableToMatch}).",
                ]);
            }

            $created = collect();

            foreach ($matches as $match) {
                $businessDate = Carbon::parse((string) $match['business_date'])->toDateString();
                $amount = round((float) ($match['amount'] ?? ($match['match_now'] ?? 0)), 2);

                if ($amount <= self::MATCH_TOLERANCE) {
                    continue;
                }

                // Verify outstanding payable for this date does not get exceeded
                $dateOutstanding = $this->getDateOutstandingPayable($shopId, $businessDate);

                if ($amount > $dateOutstanding + self::MATCH_TOLERANCE) {
                    $formatted = Carbon::parse($businessDate)->format('d M Y');
                    throw ValidationException::withMessages([
                        'matches' => "Match amount ₹{$amount} exceeds remaining outstanding Company Payable ₹{$dateOutstanding} for {$formatted}.",
                    ]);
                }

                // Check existing active match for this payment & date
                $existing = ShopPaymentCompanyPayableMatch::query()
                    ->where('payment_request_id', $payment->id)
                    ->where('payable_business_date', $businessDate)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof ShopPaymentCompanyPayableMatch) {
                    $existing->update([
                        'amount' => round((float) $existing->amount + $amount, 2),
                        'matched_by' => $userId,
                        'matched_at' => now(),
                    ]);
                    $record = $existing->fresh();
                } else {
                    $record = ShopPaymentCompanyPayableMatch::query()->create([
                        'shop_id' => $shopId,
                        'payment_request_id' => $payment->id,
                        'payable_business_date' => $businessDate,
                        'amount' => $amount,
                        'status' => 'active',
                        'matched_by' => $userId,
                        'matched_at' => now(),
                    ]);
                }

                $created->push($record);
            }

            return $created;
        }, attempts: 3);
    }

    /**
     * Automatically record same-day Company Payable match for a verified direct bank receipt.
     */
    public function autoMatchDirectBank(
        ShopInvoicePaymentRequest $payment,
        string $businessDate,
        float $amount,
        int $userId,
    ): ShopPaymentCompanyPayableMatch {
        return DB::transaction(function () use ($payment, $businessDate, $amount, $userId): ShopPaymentCompanyPayableMatch {
            $shopId = (int) $payment->shop_id;
            $businessDate = Carbon::parse($businessDate)->toDateString();
            $amount = round($amount, 2);

            $existing = ShopPaymentCompanyPayableMatch::query()
                ->where('payment_request_id', $payment->id)
                ->where('payable_business_date', $businessDate)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($existing instanceof ShopPaymentCompanyPayableMatch) {
                if (abs((float) $existing->amount - $amount) > self::MATCH_TOLERANCE) {
                    $existing->update([
                        'amount' => $amount,
                        'matched_by' => $userId,
                        'matched_at' => now(),
                    ]);
                }

                return $existing->fresh();
            }

            return ShopPaymentCompanyPayableMatch::query()->create([
                'shop_id' => $shopId,
                'payment_request_id' => $payment->id,
                'payable_business_date' => $businessDate,
                'amount' => $amount,
                'status' => 'active',
                'matched_by' => $userId,
                'matched_at' => now(),
            ]);
        }, attempts: 3);
    }

    /**
     * Safely reverse all active matches for a receipt upon cancellation or reversal.
     */
    public function reverseReceiptMatches(ShopInvoicePaymentRequest $payment, ?int $userId = null, string $reason = 'Reversed match'): int
    {
        return DB::transaction(function () use ($payment, $userId, $reason): int {
            return ShopPaymentCompanyPayableMatch::query()
                ->where('payment_request_id', $payment->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->update([
                    'status' => 'reversed',
                    'reversed_by' => $userId && $userId > 0 ? $userId : null,
                    'reversed_at' => now(),
                    'reversal_reason' => $reason,
                    'updated_at' => now(),
                ]);
        }, attempts: 3);
    }

    /**
     * Get remaining outstanding Company Payable for a single date.
     */
    public function getDateOutstandingPayable(int $shopId, string $businessDate): float
    {
        $relation = $this->settlementService->getCompanyPayableSettlement($shopId);

        if (! $relation) {
            return 0.0;
        }

        $relationItemSettingIds = $relation->items
            ->pluck('shop_ledger_entry_setting_id')
            ->filter()
            ->unique()
            ->all();

        if (empty($relationItemSettingIds)) {
            return 0.0;
        }

        $dayTxs = ShopLedgerTransaction::query()
            ->with(['entryType'])
            ->where('shop_id', $shopId)
            ->where('business_date', $businessDate)
            ->whereNotIn('status', ['void', 'voided', 'reversed'])
            ->whereIn('entry_type_id', function ($query) use ($relationItemSettingIds, $shopId): void {
                $query->select('entry_type_id')
                    ->from('shop_ledger_entry_settings')
                    ->where('shop_id', $shopId)
                    ->whereIn('id', $relationItemSettingIds);
            })
            ->get();

        $settingsByEntryTypeId = ShopLedgerEntrySetting::query()
            ->where('shop_id', $shopId)
            ->whereIn('id', $relationItemSettingIds)
            ->pluck('id', 'entry_type_id')
            ->all();

        $dayAmounts = [];
        foreach ($dayTxs as $tx) {
            $settingId = $settingsByEntryTypeId[$tx->entry_type_id] ?? null;
            if ($settingId) {
                $dayAmounts[$settingId] = ((float) ($dayAmounts[$settingId] ?? 0.0)) + (float) $tx->amount;
            }
        }

        $calc = $this->relationCalculator->calculate($relation, $dayAmounts);
        $grossPayable = round((float) ($calc['formula_net'] ?? ($calc['netSettlement'] ?? 0.0)), 2);

        $alreadyMatched = (float) ShopPaymentCompanyPayableMatch::query()
            ->where('shop_id', $shopId)
            ->where('status', 'active')
            ->where('payable_business_date', $businessDate)
            ->sum('amount');

        return round(max(0, $grossPayable - $alreadyMatched), 2);
    }

    /**
     * Resolve verified/canonical receipt amount.
     */
    public function resolveReceiptAmount(ShopInvoicePaymentRequest $payment): float
    {
        $reconciled = (float) ($payment->reconciled_amount ?? 0);
        $approved = (float) ($payment->approved_amount ?? 0);
        $requested = (float) $payment->requested_amount;

        if ($reconciled > self::MATCH_TOLERANCE) {
            return round($reconciled, 2);
        }

        if ($approved > self::MATCH_TOLERANCE) {
            return round($approved, 2);
        }

        return round($requested, 2);
    }
}
