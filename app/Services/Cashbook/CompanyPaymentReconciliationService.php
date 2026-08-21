<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\CompanyPaymentReconciliation;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\JournalEntry;
use App\Models\ShopInvoicePaymentRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyPaymentReconciliationService
{
    public function __construct(
        private readonly DailyLedgerService $dailyLedgerService,
    ) {}

    public function createStatementEntry(array $input, int $userId): CompanyAccountStatementEntry
    {
        return DB::transaction(function () use ($input, $userId): CompanyAccountStatementEntry {
            $account = CompanyAccount::query()
                ->whereKey((int) $input['company_account_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $entry = CompanyAccountStatementEntry::query()->create([
                'company_account_id' => $account->id,
                'transaction_date' => $input['transaction_date'],
                'value_date' => $input['value_date'] ?? null,
                'direction' => $input['direction'] ?? 'in',
                'amount' => round((float) $input['amount'], 2),
                'reference' => filled($input['reference'] ?? null) ? trim((string) $input['reference']) : null,
                'narration' => filled($input['narration'] ?? null) ? trim((string) $input['narration']) : null,
                'source' => $input['source'] ?? 'manual',
                'status' => 'unmatched',
                'matched_amount' => 0,
                'statement_batch' => $input['statement_batch'] ?? null,
                'notes' => filled($input['notes'] ?? null) ? trim((string) $input['notes']) : null,
                'imported_by' => $userId,
            ]);

            $this->applyStatementBalanceMovement($account, $entry);

            return $entry;
        });
    }

    public function reconcilePayment(ShopInvoicePaymentRequest $paymentRequest, array $input, int $userId): CompanyPaymentReconciliation
    {
        return DB::transaction(function () use ($paymentRequest, $input, $userId): CompanyPaymentReconciliation {
            $paymentRequest = ShopInvoicePaymentRequest::query()
                ->whereKey($paymentRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($paymentRequest->status === 'rejected') {
                throw ValidationException::withMessages([
                    'payment_request_id' => 'Rejected payment requests cannot be reconciled.',
                ]);
            }

            $account = CompanyAccount::query()
                ->whereKey((int) $input['company_account_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $statementEntry = null;
            $statementAmount = round((float) ($input['statement_amount'] ?? $input['cleared_amount']), 2);

            if (! empty($input['statement_entry_id'])) {
                $statementEntry = CompanyAccountStatementEntry::query()
                    ->whereKey((int) $input['statement_entry_id'])
                    ->where('company_account_id', $account->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($statementEntry->direction !== 'in') {
                    throw ValidationException::withMessages([
                        'statement_entry_id' => 'Only incoming statement entries can reconcile shop payments.',
                    ]);
                }

                $remainingStatementAmount = round((float) $statementEntry->amount - (float) $statementEntry->matched_amount, 2);
                if ($statementAmount > $remainingStatementAmount) {
                    throw ValidationException::withMessages([
                        'statement_amount' => 'Statement matched amount is greater than the remaining statement balance.',
                    ]);
                }
            }

            $clearedAmount = round((float) $input['cleared_amount'], 2);
            $differenceAmount = round((float) ($input['difference_amount'] ?? 0), 2);
            $differenceAction = (string) ($input['difference_action'] ?? 'none');

            if ($clearedAmount <= 0.0) {
                throw ValidationException::withMessages([
                    'cleared_amount' => 'Cleared amount must be greater than zero.',
                ]);
            }

            if ($statementAmount <= 0.0) {
                $statementAmount = $clearedAmount;
            }

            if ($statementAmount < 0.0 || $differenceAmount < 0.0) {
                throw ValidationException::withMessages([
                    'cleared_amount' => 'Amounts cannot be negative.',
                ]);
            }

            $bankClearedDifference = round(abs($statementAmount - $clearedAmount), 2);
            if ($bankClearedDifference > 0.0 && $differenceAction === 'none') {
                throw ValidationException::withMessages([
                    'difference_action' => 'Select how to account for the difference between bank amount and cleared amount.',
                ]);
            }

            if ($bankClearedDifference > 0.0 && $differenceAmount <= 0.0) {
                $differenceAmount = $bankClearedDifference;
            }

            if ($differenceAmount > 0.0 && $differenceAction === 'none') {
                throw ValidationException::withMessages([
                    'difference_action' => 'Select a difference action when a difference amount is entered.',
                ]);
            }

            if (! $statementEntry instanceof CompanyAccountStatementEntry) {
                $statementEntry = $this->createAutoStatementEntry($paymentRequest, $account, $input, $statementAmount, $userId);
            }

            // Strict JournalEntry resolution & accounting validation
            $journalEntry = $this->validateAndResolveJournalEntry(
                isset($input['journal_entry_id']) ? (int) $input['journal_entry_id'] : null,
                $paymentRequest,
                $statementEntry,
                $clearedAmount,
            );

            $differenceTransaction = null;
            if (in_array($differenceAction, ['shop_expense', 'shop_income'], true) && $differenceAmount > 0.0) {
                $differenceTransaction = $this->recordDifferenceAdjustment(
                    $paymentRequest,
                    $differenceAction,
                    $differenceAmount,
                    $input,
                    $userId,
                );
            }

            $isFullyCleared = round((float) $paymentRequest->requested_amount - ($paymentRequest->reconciled_amount + $clearedAmount), 2) <= 0.0;

            $reconciliation = CompanyPaymentReconciliation::query()->create([
                'payment_request_id' => $paymentRequest->id,
                'shop_id' => $paymentRequest->shop_id,
                'company_account_id' => $account->id,
                'statement_entry_id' => $statementEntry->id,
                'journal_entry_id' => $journalEntry?->id,
                'statement_amount' => $statementAmount,
                'cleared_amount' => $clearedAmount,
                'difference_amount' => $differenceAmount,
                'difference_action' => $differenceAction,
                'difference_entry_type_id' => $input['difference_entry_type_id'] ?? null,
                'difference_transaction_id' => $differenceTransaction?->id,
                'status' => 'approved',
                'is_finalized' => $isFullyCleared,
                'finalized_at' => $isFullyCleared ? now() : null,
                'admin_note' => filled($input['admin_note'] ?? null) ? trim((string) $input['admin_note']) : null,
                'reconciled_by' => $userId,
                'reconciled_at' => now(),
            ]);

            $statementEntry->increment('matched_amount', $statementAmount);
            $statementEntry->refresh();
            $statementEntry->update([
                'journal_entry_id' => $journalEntry?->id,
                'status' => (float) $statementEntry->matched_amount >= (float) $statementEntry->amount
                    ? 'reconciled'
                    : 'partially_matched',
                'is_finalized' => (float) $statementEntry->matched_amount >= (float) $statementEntry->amount,
                'finalized_at' => (float) $statementEntry->matched_amount >= (float) $statementEntry->amount ? now() : null,
                'reconciled_by' => $userId,
                'reconciled_at' => now(),
            ]);

            $this->refreshPaymentReconciliationTotals($paymentRequest);

            return $reconciliation->fresh([
                'paymentRequest',
                'shop',
                'companyAccount',
                'statementEntry',
                'journalEntry',
                'differenceTransaction.entryType',
                'reconciledBy',
            ]);
        });
    }

    private function validateAndResolveJournalEntry(
        ?int $journalEntryId,
        ShopInvoicePaymentRequest $paymentRequest,
        CompanyAccountStatementEntry $statementEntry,
        float $clearedAmount
    ): ?JournalEntry {
        $journalEntry = null;

        if ($journalEntryId !== null && $journalEntryId > 0) {
            $journalEntry = JournalEntry::query()->with('transactions.account')->find($journalEntryId);
            if (! $journalEntry) {
                throw ValidationException::withMessages([
                    'journal_entry_id' => 'Selected Journal Entry does not exist.',
                ]);
            }
        } else {
            $journalEntry = JournalEntry::query()
                ->with('transactions.account')
                ->where('source_type', ShopInvoicePaymentRequest::class)
                ->where('source_id', $paymentRequest->id)
                ->first();
        }

        if (! $journalEntry instanceof JournalEntry) {
            return null;
        }

        // 1. Balance check
        $debits = round((float) $journalEntry->transactions->where('type', 'debit')->sum('amount'), 2);
        $credits = round((float) $journalEntry->transactions->where('type', 'credit')->sum('amount'), 2);
        if (abs($debits - $credits) > 0.01) {
            throw ValidationException::withMessages([
                'journal_entry_id' => "JournalEntry #{$journalEntry->id} is unbalanced (Debits: ₹{$debits}, Credits: ₹{$credits}).",
            ]);
        }

        // 2. Conflict check: make sure this journal_entry_id is not already linked to another finalized statement entry
        $conflict = CompanyAccountStatementEntry::query()
            ->where('journal_entry_id', $journalEntry->id)
            ->where('is_finalized', true)
            ->where('id', '!=', $statementEntry->id)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'journal_entry_id' => "JournalEntry #{$journalEntry->id} is already linked to another finalized statement entry.",
            ]);
        }

        // 3. Direction check
        $direction = $statementEntry->direction; // 'in' or 'out'
        $matchingTxn = $journalEntry->transactions->first(function ($txn) use ($direction): bool {
            $code = $txn->account?->code ?? '';
            if ($direction === 'in') {
                return $txn->type === 'debit' && in_array($code, ['1010', '1020'], true);
            } else {
                return $txn->type === 'credit' && in_array($code, ['1010', '1020'], true);
            }
        });

        if (! $matchingTxn) {
            throw ValidationException::withMessages([
                'journal_entry_id' => "JournalEntry #{$journalEntry->id} does not contain a matching ".($direction === 'in' ? 'Debit' : 'Credit').' Bank/Cash line.',
            ]);
        }

        // 4. Amount validation check
        $txnAmount = round((float) $matchingTxn->amount, 2);
        if (abs($txnAmount - $clearedAmount) > 0.01 && abs($debits - $clearedAmount) > 0.01) {
            throw ValidationException::withMessages([
                'cleared_amount' => "Cleared amount (₹{$clearedAmount}) does not match JournalEntry #{$journalEntry->id} amount (₹{$txnAmount}).",
            ]);
        }

        return $journalEntry;
    }

    private function createAutoStatementEntry(
        ShopInvoicePaymentRequest $paymentRequest,
        CompanyAccount $account,
        array $input,
        float $statementAmount,
        int $userId
    ): CompanyAccountStatementEntry {
        return $this->createStatementEntry([
            'company_account_id' => $account->id,
            'transaction_date' => $input['business_date'] ?? $paymentRequest->payment_date?->toDateString() ?? now()->toDateString(),
            'value_date' => $input['business_date'] ?? $paymentRequest->payment_date?->toDateString() ?? now()->toDateString(),
            'direction' => 'in',
            'amount' => $statementAmount,
            'reference' => $paymentRequest->payment_reference ?: 'SHOP-PAY-'.$paymentRequest->id,
            'narration' => 'Auto statement entry from shop payment reconciliation: '.($paymentRequest->shop?->name ?? 'Shop #'.$paymentRequest->shop_id),
            'source' => 'reconciliation',
            'status' => 'unmatched',
            'matched_amount' => 0,
            'statement_batch' => 'auto-reconciliation',
            'notes' => filled($input['admin_note'] ?? null)
                ? trim((string) $input['admin_note'])
                : 'Created automatically during reconciliation approval.',
        ], $userId);
    }

    private function applyStatementBalanceMovement(CompanyAccount $account, CompanyAccountStatementEntry $entry): void
    {
        $amount = round((float) $entry->amount, 2);
        $balanceChange = $entry->direction === 'out' ? -$amount : $amount;

        $account->increment('current_balance', $balanceChange);
    }

    private function refreshPaymentReconciliationTotals(ShopInvoicePaymentRequest $paymentRequest): void
    {
        $paymentRequest->load('reconciliations');

        $cleared = round((float) $paymentRequest->reconciliations
            ->where('status', 'approved')
            ->sum('cleared_amount'), 2);
        $requested = round((float) $paymentRequest->requested_amount, 2);
        $floating = round(max(0, $requested - $cleared), 2);
        $advance = round(max(0, $cleared - $requested), 2);

        $paymentRequest->update([
            'reconciled_amount' => $cleared,
            'floating_amount' => $floating,
            'shop_advance_amount' => $advance,
            'reconciliation_status' => match (true) {
                $cleared <= 0.0 => 'floating',
                $floating > 0.0 => 'partially_reconciled',
                default => 'reconciled',
            },
            'approved_amount' => $cleared,
            'credit_amount' => $advance,
            'status' => $floating > 0.0 ? 'partially_reconciled' : 'approved',
            'reviewed_at' => $paymentRequest->reviewed_at ?? now(),
            'last_reconciled_at' => now(),
        ]);
    }

    private function recordDifferenceAdjustment(
        ShopInvoicePaymentRequest $paymentRequest,
        string $differenceAction,
        float $differenceAmount,
        array $input,
        int $userId
    ): ShopLedgerTransaction {
        $entryType = $this->resolveDifferenceEntryType($differenceAction, $input['difference_entry_type_id'] ?? null);
        $this->ensureAdjustmentSetting((int) $paymentRequest->shop_id, $entryType);

        $result = $this->dailyLedgerService->recordEntry([
            'shop_id' => (int) $paymentRequest->shop_id,
            'business_date' => $input['business_date'] ?? now()->toDateString(),
            'entry_type_code' => $entryType->code,
            'amount' => $differenceAmount,
            'funding_source' => 'none',
            'reference_type' => ShopInvoicePaymentRequest::class,
            'reference_id' => $paymentRequest->id,
            'notes' => filled($input['admin_note'] ?? null)
                ? trim((string) $input['admin_note'])
                : 'Company finance reconciliation adjustment',
            'entered_by' => $userId,
        ]);

        return $result['transaction'];
    }

    private function resolveDifferenceEntryType(string $differenceAction, int|string|null $entryTypeId): LedgerEntryType
    {
        if ($entryTypeId !== null) {
            return LedgerEntryType::query()->whereKey((int) $entryTypeId)->firstOrFail();
        }

        $code = $differenceAction === 'shop_income' ? 'excess_receipt' : 'reconciliation_adjustment';

        return LedgerEntryType::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $differenceAction === 'shop_income' ? 'Excess Receipt' : 'Reconciliation Adjustment',
                'category' => $differenceAction === 'shop_income' ? 'income' : 'expense',
                'system_type' => 'system',
                'active' => true,
                'display_order' => 900,
            ]
        );
    }

    private function ensureAdjustmentSetting(int $shopId, LedgerEntryType $entryType): void
    {
        ShopLedgerEntrySetting::query()->firstOrCreate(
            [
                'shop_id' => $shopId,
                'entry_type_id' => $entryType->id,
            ],
            [
                'version' => 1,
                'effective_from' => '2026-01-01',
                'enabled' => true,
                'default_funding_source' => 'none',
                'allowed_funding_sources' => ['none'],
                'include_in_sales' => false,
                'include_in_income' => $entryType->category === 'income',
                'include_in_expense' => $entryType->category === 'expense',
                'include_in_pl' => true,
                'include_in_payable' => true,
                'payable_direction' => $entryType->category === 'income' ? 'plus' : 'minus',
                'settlement_behavior' => 'none',
                'petty_behavior' => 'none',
                'company_pending_behavior' => 'none',
                'generates_secondary_entry' => false,
                'secondary_amount_mode' => 'same_amount',
                'display_order' => 900,
            ]
        );
    }
}
