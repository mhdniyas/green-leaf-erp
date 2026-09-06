<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\JournalEntry;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCredit;
use App\Models\User;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class PurchaserSettlementService
{
    public function __construct(
        private readonly PurchaserFinanceService $purchaserFinanceService,
        private readonly JournalService $journalService,
        private readonly CompanyPaymentReconciliationService $companyPaymentReconciliationService
    ) {}

    /**
     * Parse or default a year-month string (YYYY-MM) and return half-open date range.
     *
     * @return array{yearMonth: string, startDate: string, endDate: string, nextMonthDate: string}
     */
    public function resolveMonthRange(?string $monthInput = null): array
    {
        $now = now('Asia/Kolkata');
        $input = trim((string) $monthInput);

        if ($input !== '' && preg_match('/^\d{4}-\d{2}$/', $input) === 1) {
            try {
                $carbon = Carbon::createFromFormat('Y-m-d', $input.'-01', 'Asia/Kolkata')->startOfDay();
            } catch (\Throwable) {
                $carbon = $now->copy()->startOfMonth();
            }
        } else {
            $carbon = $now->copy()->startOfMonth();
        }

        $yearMonth = $carbon->format('Y-m');
        $startDate = $carbon->toDateString();
        $nextMonth = $carbon->copy()->addMonth();
        $endDate = $nextMonth->toDateString(); // Half-open boundary: business_date >= startDate AND business_date < endDate

        return [
            'yearMonth' => $yearMonth,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'nextMonthDate' => $nextMonth->toDateString(),
        ];
    }

    /**
     * Get comprehensive settlement position and activity for a given purchaser and month.
     *
     * @return array<string, mixed>
     */
    public function monthSettlement(User $purchaser, ?string $monthInput = null): array
    {
        $range = $this->resolveMonthRange($monthInput);
        $purchaserId = (int) $purchaser->id;

        // 1. Opening Balance prior to startDate
        $opening = $this->openingBalanceBefore($purchaserId, $range['startDate']);

        // 2. Month Activity (business_date >= startDate AND business_date < endDate)
        $monthCredits = DB::table('purchaser_credits')
            ->where('purchaser_id', $purchaserId)
            ->whereDate('business_date', '>=', $range['startDate'])
            ->whereDate('business_date', '<', $range['endDate'])
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) as funding_added,
                COALESCE(SUM(CASE WHEN type = 'out' AND purchase_invoice_id IS NULL THEN amount ELSE 0 END), 0) as cash_returned,
                COALESCE(SUM(CASE WHEN type = 'out' AND purchase_invoice_id IS NOT NULL THEN amount ELSE 0 END), 0) as advance_utilized
            ")
            ->first();

        $monthFundingAdded = round((float) ($monthCredits->funding_added ?? 0), 2);
        $monthCashReturned = round((float) ($monthCredits->cash_returned ?? 0), 2);
        $monthAdvanceUtilized = round((float) ($monthCredits->advance_utilized ?? 0), 2);

        // Month Total Purchases (Cash + Credit)
        $monthInvoices = DB::table('purchase_invoices')
            ->leftJoin('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->whereRaw('(purchase_invoices.purchaser_submitted_by = ? OR purchaser_carts.user_id = ?)', [$purchaserId, $purchaserId])
            ->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) >= ?', [$range['startDate']])
            ->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) < ?', [$range['endDate']])
            ->selectRaw('
                COALESCE(SUM(purchase_invoices.amount - purchase_invoices.discount_amount), 0) as total_purchases,
                COALESCE(SUM(CASE WHEN purchase_invoices.payment_method = "Credit" OR purchase_invoices.payment_status = "credit_pending_approval" OR purchase_invoices.payment_paid_by = "vendor_credit" THEN (purchase_invoices.amount - purchase_invoices.discount_amount - purchase_invoices.paid_amount) ELSE 0 END), 0) as credit_outstanding
            ')
            ->first();

        $monthPurchases = round((float) ($monthInvoices->total_purchases ?? 0), 2);
        $monthCreditOutstanding = round((float) ($monthInvoices->credit_outstanding ?? 0), 2);

        // Cumulative overall totals as of today
        $cumulative = $this->purchaserFinanceService->summaryFor($purchaserId);

        // Closing advance position as of end of month
        $closingAdvance = round($opening['advance'] + $monthFundingAdded - $monthCashReturned - $monthAdvanceUtilized, 2);

        return [
            'month' => $range['yearMonth'],
            'start_date' => $range['startDate'],
            'end_date' => $range['endDate'],
            'opening_balance' => $opening['advance'],
            'month_funding_added' => $monthFundingAdded,
            'month_cash_returned' => $monthCashReturned,
            'month_purchases' => $monthPurchases,
            'month_advance_utilized' => $monthAdvanceUtilized,
            'month_credit_outstanding' => $monthCreditOutstanding,
            'available_advance' => max(0.0, $closingAdvance),
            'closing_balance' => $closingAdvance,
            'cumulative_summary' => $cumulative,
        ];
    }

    /**
     * Calculate opening advance balance as of startDate (transactions prior to startDate).
     *
     * @return array{cash_given: float, cash_returned: float, advance_utilized: float, advance: float}
     */
    public function openingBalanceBefore(int $purchaserId, string $startDate): array
    {
        $row = DB::table('purchaser_credits')
            ->where('purchaser_id', $purchaserId)
            ->whereDate('business_date', '<', $startDate)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) as cash_given,
                COALESCE(SUM(CASE WHEN type = 'out' AND purchase_invoice_id IS NULL THEN amount ELSE 0 END), 0) as cash_returned,
                COALESCE(SUM(CASE WHEN type = 'out' AND purchase_invoice_id IS NOT NULL THEN amount ELSE 0 END), 0) as advance_utilized
            ")
            ->first();

        $given = round((float) ($row->cash_given ?? 0), 2);
        $returned = round((float) ($row->cash_returned ?? 0), 2);
        $utilized = round((float) ($row->advance_utilized ?? 0), 2);
        $advance = round($given - $returned - $utilized, 2);

        return [
            'cash_given' => $given,
            'cash_returned' => $returned,
            'advance_utilized' => $utilized,
            'advance' => $advance,
        ];
    }

    /**
     * Generates a preview of affected purchase bills if a funding entry is edited or deleted.
     *
     * @return array{affected_bills_count: int, utilized_amount: float, requires_reversal: bool}
     */
    public function reversalPreview(User $purchaser, PurchaserCredit $credit, ?float $proposedAmount = null): array
    {
        if ((int) $credit->purchaser_id !== (int) $purchaser->id || $credit->type !== 'in') {
            return [
                'affected_bills_count' => 0,
                'utilized_amount' => 0.0,
                'requires_reversal' => false,
            ];
        }

        $creditAmount = (float) $credit->amount;
        $targetAmount = $proposedAmount !== null ? round($proposedAmount, 2) : 0.0;
        $reduction = round($creditAmount - $targetAmount, 2);

        if ($reduction <= 0.0) {
            return [
                'affected_bills_count' => 0,
                'utilized_amount' => 0.0,
                'requires_reversal' => false,
            ];
        }

        // Check if removing $reduction causes available advance to drop below 0 at any point
        $purchaserId = (int) $purchaser->id;
        $credits = PurchaserCredit::query()
            ->where('purchaser_id', $purchaserId)
            ->orderBy('business_date')
            ->orderBy('id')
            ->get();

        $running = 0.0;
        $affectedInvoiceIds = [];
        $utilizedAmountReversed = 0.0;

        foreach ($credits as $row) {
            $amt = (float) $row->amount;
            if ((int) $row->id === (int) $credit->id) {
                $amt = $targetAmount;
            }

            if ($row->type === 'in') {
                $running += $amt;
            } else {
                $running -= $amt;
                if ($running < -0.009 && $row->purchase_invoice_id) {
                    $deficit = abs($running);
                    $affectedInvoiceIds[(int) $row->purchase_invoice_id] = true;
                    $utilizedAmountReversed += min($amt, $deficit);
                    $running += $amt; // simulate reversing this cash purchase
                }
            }
        }

        return [
            'affected_bills_count' => count($affectedInvoiceIds),
            'utilized_amount' => round($utilizedAmountReversed, 2),
            'requires_reversal' => count($affectedInvoiceIds) > 0,
        ];
    }

    /**
     * Assert that a purchaser credit funding entry can be safely updated or deleted.
     *
     * @throws ValidationException
     */
    public function assertFundingMutable(PurchaserCredit $credit): void
    {
        $journalId = JournalEntry::query()
            ->where('source_type', PurchaserCredit::class)
            ->where('source_id', $credit->id)
            ->value('id');

        $linkedStatementExists = CompanyAccountStatementEntry::query()
            ->where(function ($q) use ($credit, $journalId): void {
                $q->where(function ($q2) use ($credit): void {
                    $q2->whereIn('source_type', [PurchaserCredit::class, 'purchaser_funding'])
                        ->where('source_id', $credit->id);
                })
                    ->orWhere(function ($q2) use ($credit): void {
                        $q2->whereIn('counterpart_type', [PurchaserCredit::class, 'purchaser_funding'])
                            ->where('counterpart_id', $credit->id);
                    });
                if ($journalId) {
                    $q->orWhere('journal_entry_id', $journalId);
                }
            })
            ->where(function ($q): void {
                $q->where('is_finalized', true)
                    ->orWhereIn('status', ['matched', 'reconciled', 'partially_matched'])
                    ->orWhere('matched_amount', '>', 0);
            })
            ->exists();

        if ($linkedStatementExists) {
            throw ValidationException::withMessages([
                'credit' => 'Funding is linked to a matched or reconciled statement entry. Unmatch first before editing or deleting.',
            ]);
        }

        if ($journalId) {
            $manualReconciliationExists = DB::table('cashbook_company_payment_reconciliations as allocation')
                ->where('allocation.journal_entry_id', $journalId)
                ->exists();

            if ($manualReconciliationExists) {
                throw ValidationException::withMessages([
                    'credit' => 'Manual reconciliation allocation exists for this funding entry.',
                ]);
            }

            $journal = JournalEntry::query()->find($journalId);
            if (! $journal || ! in_array($journal->source_event, ['purchaser_funding', 'purchaser_funding_return'], true)) {
                throw ValidationException::withMessages([
                    'credit' => 'Historical or invalid funding entry cannot be modified.',
                ]);
            }
        }

        if ($credit->purchase_invoice_id !== null) {
            throw ValidationException::withMessages([
                'credit' => 'Funding entry linked directly to a purchase invoice cannot be edited or deleted directly.',
            ]);
        }
    }

    /**
     * Safe admin operation to delete a funding entry (even if utilized), with chronological reversal & audit.
     */
    public function deleteFundingWithReversal(
        User $purchaser,
        PurchaserCredit $credit,
        User $actor,
        string $reason,
        ?string $notes = null
    ): void {
        abort_unless($actor->isMainAdmin() || $actor->hasRole('admin'), 403, 'Unauthorized admin action.');
        if ((int) $credit->purchaser_id !== (int) $purchaser->id) {
            throw ValidationException::withMessages(['credit' => 'Funding record does not belong to this purchaser.']);
        }

        DB::transaction(function () use ($credit, $purchaser, $actor, $reason, $notes): void {
            // Lock purchaser credits for update
            $credit = PurchaserCredit::query()->whereKey($credit->id)->lockForUpdate()->firstOrFail();
            $this->assertFundingMutable($credit);
            $originalAmount = (float) $credit->amount;
            $originalDate = $credit->business_date->format('Y-m-d');
            $originalRef = (string) ($credit->reference ?? '');

            // Unmatch statement reconciliations if matched
            $journalEntry = JournalEntry::query()
                ->where('source_type', PurchaserCredit::class)
                ->where('source_id', $credit->id)
                ->lockForUpdate()
                ->first();

            if ($journalEntry instanceof JournalEntry) {
                $statementEntries = CompanyAccountStatementEntry::query()
                    ->where('journal_entry_id', $journalEntry->id)
                    ->orWhere(function ($q) use ($credit): void {
                        $q->where('source_type', PurchaserCredit::class)
                            ->where('source_id', $credit->id);
                    })
                    ->lockForUpdate()
                    ->get();

                foreach ($statementEntries as $stmt) {
                    if ($stmt->status !== 'unmatched' || $stmt->is_finalized) {
                        $this->companyPaymentReconciliationService->unmatchStatementJournal(
                            $stmt,
                            $journalEntry,
                            (int) $actor->id
                        );
                    }
                }

                $journalEntry->transactions()->delete();
                $journalEntry->delete();
            }

            // Soft-delete or delete the credit record
            $credit->delete();

            // Rebuild chronological settlement timeline to ensure no negative advance balance remains
            $reversalInfo = $this->rebuildChronologicalSettlement($purchaser);

            // Record comprehensive audit log
            Log::info('Purchaser funding deleted with reversal by admin', [
                'purchaser_id' => $purchaser->id,
                'credit_id' => $credit->id,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'notes' => $notes,
                'original_amount' => $originalAmount,
                'affected_bills_count' => $reversalInfo['reversed_bills_count'],
            ]);

            if (function_exists('activity')) {
                activity('purchaser_finance')
                    ->causedBy($actor)
                    ->performedOn($purchaser)
                    ->withProperties([
                        'action' => 'delete_funding_with_reversal',
                        'credit_id' => $credit->id,
                        'original_amount' => $originalAmount,
                        'original_date' => $originalDate,
                        'original_reference' => $originalRef,
                        'reason' => $reason,
                        'notes' => $notes,
                        'reversed_bills_count' => $reversalInfo['reversed_bills_count'],
                        'reversed_bills' => $reversalInfo['reversed_invoice_numbers'],
                    ])
                    ->log("Admin deleted funding #{$credit->id} (₹".number_format($originalAmount, 2).") for {$purchaser->name}. Reason: {$reason}");
            }
        });
    }

    /**
     * Safe admin operation to update a funding entry (even if utilized or reduced), with chronological reversal & audit.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateFundingWithReversal(
        User $purchaser,
        PurchaserCredit $credit,
        array $data,
        User $actor,
        string $reason,
        ?string $notes = null
    ): void {
        abort_unless($actor->isMainAdmin() || $actor->hasRole('admin'), 403, 'Unauthorized admin action.');
        if ((int) $credit->purchaser_id !== (int) $purchaser->id) {
            throw ValidationException::withMessages(['credit' => 'Funding record does not belong to this purchaser.']);
        }

        $newAmount = round((float) $data['amount'], 2);
        $newDate = (string) $data['business_date'];
        $paymentSource = (string) ($data['payment_source'] ?? 'Cash');
        $companyAccountId = isset($data['company_account_id']) ? (int) $data['company_account_id'] : null;
        $reference = isset($data['reference']) ? (string) $data['reference'] : null;
        $description = isset($data['description']) ? (string) $data['description'] : null;

        if ($companyAccountId) {
            $account = CompanyAccount::query()->whereKey($companyAccountId)->where('enabled', true)->first();
            if (! $account || $account->account_type !== strtolower($paymentSource)) {
                throw ValidationException::withMessages(['company_account_id' => 'Select an enabled company account matching the payment source.']);
            }
        }

        DB::transaction(function () use ($credit, $purchaser, $actor, $newAmount, $newDate, $paymentSource, $companyAccountId, $reference, $description, $reason, $notes): void {
            $credit = PurchaserCredit::query()->whereKey($credit->id)->lockForUpdate()->firstOrFail();
            $this->assertFundingMutable($credit);
            $oldAmount = (float) $credit->amount;
            $oldDate = $credit->business_date->format('Y-m-d');
            $oldRef = (string) ($credit->reference ?? '');

            // Metadata-only check
            $isFinancialChange = abs($oldAmount - $newAmount) > 0.009
                || $oldDate !== $newDate
                || (int) ($credit->company_account_id ?? 0) !== ($companyAccountId ?? 0);

            $credit->update([
                'amount' => $newAmount,
                'business_date' => $newDate,
                'payment_source' => $paymentSource,
                'company_account_id' => $companyAccountId,
                'reference' => $reference,
                'description' => $description ?? ($credit->type === 'in' ? 'Company funding to purchaser' : 'Purchaser return of funding to company'),
            ]);

            // Update associated journal entry
            $this->journalService->recordPurchaserCredit($credit, updateExisting: true);

            // Rebuild chronological timeline if financial fields changed
            $reversalInfo = ['reversed_bills_count' => 0, 'reversed_invoice_numbers' => []];
            if ($isFinancialChange) {
                $reversalInfo = $this->rebuildChronologicalSettlement($purchaser);
            }

            Log::info('Purchaser funding updated with reversal by admin', [
                'purchaser_id' => $purchaser->id,
                'credit_id' => $credit->id,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'old_amount' => $oldAmount,
                'new_amount' => $newAmount,
            ]);

            if (function_exists('activity')) {
                activity('purchaser_finance')
                    ->causedBy($actor)
                    ->performedOn($purchaser)
                    ->withProperties([
                        'action' => 'update_funding_with_reversal',
                        'credit_id' => $credit->id,
                        'old_amount' => $oldAmount,
                        'new_amount' => $newAmount,
                        'old_date' => $oldDate,
                        'new_date' => $newDate,
                        'reason' => $reason,
                        'notes' => $notes,
                        'reversed_bills_count' => $reversalInfo['reversed_bills_count'],
                        'reversed_bills' => $reversalInfo['reversed_invoice_numbers'],
                    ])
                    ->log("Admin updated funding #{$credit->id} from ₹".number_format($oldAmount, 2).' to ₹'.number_format($newAmount, 2)." for {$purchaser->name}. Reason: {$reason}");
            }
        });
    }

    /**
     * Chronological settlement rebuild (Case B rule):
     * Evaluates `business_date ASC, id ASC`. If available advance drops below 0 at any point,
     * converts earliest affected cash purchase invoices to Credit so advance remains >= 0.
     *
     * @return array{reversed_bills_count: int, reversed_invoice_numbers: array<int, string>}
     */
    public function rebuildChronologicalSettlement(User $purchaser): array
    {
        $purchaserId = (int) $purchaser->id;
        $credits = PurchaserCredit::query()
            ->where('purchaser_id', $purchaserId)
            ->orderBy('business_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $running = 0.0;
        $reversedBills = [];

        foreach ($credits as $row) {
            $amt = (float) $row->amount;
            if ($row->type === 'in') {
                $running += $amt;
            } else {
                $running -= $amt;
                if ($running < -0.009 && $row->purchase_invoice_id) {
                    // Advance dropped below 0: convert this cash purchase invoice to Credit
                    $invoice = PurchaseInvoice::query()->whereKey($row->purchase_invoice_id)->lockForUpdate()->first();
                    if ($invoice instanceof PurchaseInvoice) {
                        $invoice->update([
                            'payment_method' => 'Credit',
                            'payment_status' => 'credit_pending_approval',
                            'payment_paid_by' => 'vendor_credit',
                            'paid_amount' => 0.00,
                        ]);
                        $reversedBills[] = (string) $invoice->invoice_number;
                    }

                    // Delete the cash spend credit record
                    $row->delete();
                    $running += $amt; // restore balance
                }
            }
        }

        return [
            'reversed_bills_count' => count($reversedBills),
            'reversed_invoice_numbers' => array_values($reversedBills),
        ];
    }
}
