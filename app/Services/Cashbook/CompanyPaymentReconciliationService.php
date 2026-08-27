<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\CompanyPaymentReconciliation;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\CompanyAccountingEntry;
use App\Models\CompanyPayableSettlement;
use App\Models\DirectCompanySale;
use App\Models\JournalEntry;
use App\Models\PayrollPayment;
use App\Models\PurchaserCredit;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\VendorSettlement;
use App\Services\Finance\JournalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyPaymentReconciliationService
{
    public function __construct(
        private readonly DailyLedgerService $dailyLedgerService,
        private readonly JournalService $journalService,
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
                'journal_entry_id' => $input['journal_entry_id'] ?? null,
                'transaction_date' => $input['transaction_date'],
                'value_date' => $input['value_date'] ?? null,
                'direction' => $input['direction'] ?? 'in',
                'amount' => round((float) $input['amount'], 2),
                'reference' => filled($input['reference'] ?? null) ? trim((string) $input['reference']) : null,
                'narration' => filled($input['narration'] ?? null) ? trim((string) $input['narration']) : null,
                'source' => $input['source'] ?? 'manual',
                'source_type' => $input['source_type'] ?? null,
                'source_id' => $input['source_id'] ?? null,
                'counterpart_type' => $input['counterpart_type'] ?? null,
                'counterpart_id' => $input['counterpart_id'] ?? null,
                'request_uuid' => $input['request_uuid'] ?? null,
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

    public function reconcileStatementJournal(CompanyAccountStatementEntry $statementEntry, JournalEntry $journalEntry, float $clearedAmount, int $userId): CompanyAccountStatementEntry
    {
        return DB::transaction(function () use ($statementEntry, $journalEntry, $clearedAmount, $userId): CompanyAccountStatementEntry {
            $statementEntry = CompanyAccountStatementEntry::query()
                ->with('companyAccount')
                ->whereKey($statementEntry->id)
                ->lockForUpdate()
                ->firstOrFail();

            $journalEntry = JournalEntry::query()
                ->with(['transactions.account', 'statementEntries'])
                ->whereKey($journalEntry->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($statementEntry->is_finalized) {
                throw ValidationException::withMessages([
                    'statement_entry_id' => 'This statement entry is already finalized and cannot be modified.',
                ]);
            }

            $remainingStatementAmount = round((float) $statementEntry->amount - (float) $statementEntry->matched_amount, 2);
            if ($remainingStatementAmount <= 0.0) {
                throw ValidationException::withMessages([
                    'statement_entry_id' => 'This statement entry has no open balance left to reconcile.',
                ]);
            }

            if ($clearedAmount <= 0.0 || $clearedAmount > $remainingStatementAmount) {
                throw ValidationException::withMessages([
                    'cleared_amount' => 'Cleared amount must fit within the remaining statement balance.',
                ]);
            }

            $primaryAmount = round((float) $journalEntry->primary_amount, 2);
            if ($primaryAmount <= 0.0) {
                throw ValidationException::withMessages([
                    'journal_entry_id' => 'Selected journal entry has no reconcilable amount.',
                ]);
            }

            $matchedToJournal = round((float) $journalEntry->statementEntries()->sum('matched_amount'), 2);
            $remainingJournalAmount = round($primaryAmount - $matchedToJournal, 2);

            if ($remainingJournalAmount <= 0.0) {
                throw ValidationException::withMessages([
                    'journal_entry_id' => 'Selected journal entry is already fully reconciled.',
                ]);
            }

            if ($clearedAmount > $remainingJournalAmount) {
                throw ValidationException::withMessages([
                    'cleared_amount' => 'Cleared amount is greater than the remaining journal balance.',
                ]);
            }

            $this->validateJournalAgainstStatement($journalEntry, $statementEntry, $clearedAmount);

            $statementEntry->matched_amount = round((float) $statementEntry->matched_amount + $clearedAmount, 2);
            $statementEntry->journal_entry_id = $journalEntry->id;
            $statementEntry->source = in_array($statementEntry->source, ['imported', 'manual'], true)
                ? $statementEntry->source
                : ($this->cashbookSourceForJournal($journalEntry) ?? $statementEntry->source);
            $statementEntry->source_type = $journalEntry->source_type ?: $statementEntry->source_type;
            $statementEntry->source_id = $journalEntry->source_id ?: $statementEntry->source_id;
            $statementEntry->status = $statementEntry->matched_amount >= ((float) $statementEntry->amount - 0.01)
                ? 'reconciled'
                : 'partially_matched';
            $statementEntry->is_finalized = $statementEntry->status === 'reconciled';
            $statementEntry->finalized_at = $statementEntry->is_finalized ? now() : null;
            $statementEntry->reconciled_by = $userId;
            $statementEntry->reconciled_at = now();
            $statementEntry->save();

            if ($journalEntry->source_type === VendorSettlement::class && $statementEntry->is_finalized) {
                VendorSettlement::query()->whereKey($journalEntry->source_id)->update([
                    'reconciliation_status' => 'finalized',
                    'is_finalized' => true,
                    'finalized_at' => now(),
                ]);
            }

            if ($journalEntry->source_type === DirectCompanySale::class && $statementEntry->is_finalized) {
                DirectCompanySale::query()->whereKey($journalEntry->source_id)->update([
                    'reconciliation_status' => 'finalized',
                    'is_finalized' => true,
                    'finalized_at' => now(),
                ]);
            }

            if ($statementEntry->is_finalized) {
                CompanyAccountStatementEntry::query()
                    ->where('journal_entry_id', $journalEntry->id)
                    ->where('id', '!=', $statementEntry->id)
                    ->where('is_finalized', false)
                    ->where('status', 'unmatched')
                    ->where('matched_amount', '<=', 0)
                    ->update([
                        'status' => 'superseded',
                        'duplicate_status' => 'manual_cleared',
                        'duplicate_of_statement_entry_id' => $statementEntry->id,
                    ]);
            }

            return $statementEntry->fresh(['companyAccount', 'journalEntry.transactions.account']);
        });
    }

    public function unmatchStatementJournal(CompanyAccountStatementEntry $statementEntry, int $userId): CompanyAccountStatementEntry
    {
        return DB::transaction(function () use ($statementEntry): CompanyAccountStatementEntry {
            $statementEntry = CompanyAccountStatementEntry::query()
                ->with(['companyAccount', 'journalEntry'])
                ->whereKey($statementEntry->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $statementEntry->is_finalized && $statementEntry->status === 'unmatched' && $statementEntry->journal_entry_id === null) {
                throw ValidationException::withMessages([
                    'statement_entry_id' => 'This statement entry is not currently reconciled.',
                ]);
            }

            $isImported = $statementEntry->source === 'imported'
                || ! empty($statementEntry->import_file_name)
                || ! empty($statementEntry->import_fingerprint);

            if ($isImported) {
                $statementEntry->journal_entry_id = null;
                $statementEntry->source = 'imported';
                $statementEntry->source_type = null;
                $statementEntry->source_id = null;
                $statementEntry->matched_amount = 0;
                $statementEntry->status = 'unmatched';
                $statementEntry->is_finalized = false;
                $statementEntry->finalized_at = null;
                $statementEntry->reconciled_by = null;
                $statementEntry->reconciled_at = null;
                $statementEntry->save();
            } else {
                throw ValidationException::withMessages([
                    'statement_entry_id' => 'Manual cash/statement counterparts represent committed cashbook ledger movements and cannot be unlinked.',
                ]);
            }

            return $statementEntry->fresh(['companyAccount', 'journalEntry']);
        });
    }

    /** @param array{company_account_id:int,statement_entry_id?:int,transaction_date:string,reference?:string|null,narration?:string|null,notes?:string|null} $input */
    public function finalizeVendorSettlementMovement(VendorSettlement $settlement, JournalEntry $journalEntry, array $input, int $userId): CompanyAccountStatementEntry
    {
        return DB::transaction(function () use ($settlement, $journalEntry, $input, $userId): CompanyAccountStatementEntry {
            $settlement = VendorSettlement::query()->whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            $journalEntry = JournalEntry::query()->whereKey($journalEntry->id)->lockForUpdate()->firstOrFail();
            $amount = round((float) $settlement->actual_payment_amount, 2);

            if ($settlement->is_finalized || $amount <= 0.0) {
                throw ValidationException::withMessages(['vendor_settlement' => 'Vendor settlement is already finalized or has no cash movement.']);
            }

            if (! empty($input['statement_entry_id'])) {
                $entry = CompanyAccountStatementEntry::query()
                    ->whereKey((int) $input['statement_entry_id'])
                    ->where('company_account_id', (int) $input['company_account_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($entry->direction !== 'out' || $entry->is_finalized || $entry->journal_entry_id !== null || abs((float) $entry->amount - $amount) > 0.01) {
                    throw ValidationException::withMessages(['statement_entry_id' => 'Statement transaction cannot be used for this vendor settlement.']);
                }
            } else {
                $account = CompanyAccount::query()->whereKey((int) $input['company_account_id'])->where('enabled', true)->lockForUpdate()->firstOrFail();
                $entry = CompanyAccountStatementEntry::query()->create([
                    'company_account_id' => $account->id,
                    'journal_entry_id' => $journalEntry->id,
                    'transaction_date' => $input['transaction_date'],
                    'value_date' => $input['transaction_date'],
                    'direction' => 'out',
                    'amount' => $amount,
                    'reference' => filled($input['reference'] ?? null) ? trim((string) $input['reference']) : 'VENDOR-SETTLEMENT-'.$settlement->id,
                    'narration' => $input['narration'] ?? 'Vendor settlement',
                    'source' => 'vendor_settlement',
                    'source_type' => VendorSettlement::class,
                    'source_id' => $settlement->id,
                    'status' => 'reconciled',
                    'matched_amount' => $amount,
                    'is_finalized' => true,
                    'finalized_at' => now(),
                    'duplicate_status' => 'clear',
                    'notes' => $input['notes'] ?? null,
                    'imported_by' => $userId,
                    'reconciled_by' => $userId,
                    'reconciled_at' => now(),
                ]);
                $this->applyStatementBalanceMovement($account, $entry);
            }

            $entry->update([
                'journal_entry_id' => $journalEntry->id,
                'source' => 'vendor_settlement',
                'source_type' => VendorSettlement::class,
                'source_id' => $settlement->id,
                'matched_amount' => $amount,
                'status' => 'reconciled',
                'is_finalized' => true,
                'finalized_at' => now(),
                'reconciled_by' => $userId,
                'reconciled_at' => now(),
            ]);
            $settlement->update(['reconciliation_status' => 'finalized', 'is_finalized' => true, 'finalized_at' => now()]);

            return $entry->fresh(['companyAccount', 'journalEntry.transactions.account']);
        }, attempts: 3);
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
                    ->with('companyAccount')
                    ->whereKey((int) $input['statement_entry_id'])
                    ->where('company_account_id', $account->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($statementEntry->is_finalized) {
                    throw ValidationException::withMessages([
                        'statement_entry_id' => 'This statement entry is already finalized and cannot be modified.',
                    ]);
                }

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

            $journalEntry = $this->journalService->recordShopPaymentRequest($paymentRequest, $userId);

            $journalEntry = $this->validateAndResolveJournalEntry(
                isset($input['journal_entry_id']) ? (int) $input['journal_entry_id'] : $journalEntry->id,
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
            $isStatementFullyMatched = round((float) $statementEntry->amount - ($statementEntry->matched_amount + $statementAmount), 2) <= 0.0;
            $isFinalized = $isFullyCleared && $isStatementFullyMatched;

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
                'is_finalized' => $isFinalized,
                'finalized_at' => $isFinalized ? now() : null,
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
                'is_finalized' => $isFinalized,
                'finalized_at' => $isFinalized ? now() : null,
                'reconciled_by' => $userId,
                'reconciled_at' => now(),
            ]);

            $this->refreshPaymentReconciliationTotals($paymentRequest);

            if ($isFinalized) {
                CompanyPaymentReconciliation::query()
                    ->where('payment_request_id', $paymentRequest->id)
                    ->update([
                        'is_finalized' => true,
                        'finalized_at' => now(),
                    ]);
            }

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

    private function validateJournalAgainstStatement(JournalEntry $journalEntry, CompanyAccountStatementEntry $statementEntry, float $clearedAmount): void
    {
        $debits = round((float) $journalEntry->transactions->where('type', 'debit')->sum('amount'), 2);
        $credits = round((float) $journalEntry->transactions->where('type', 'credit')->sum('amount'), 2);

        if (abs($debits - $credits) > 0.01) {
            throw ValidationException::withMessages([
                'journal_entry_id' => "JournalEntry #{$journalEntry->id} is unbalanced (Debits: ₹{$debits}, Credits: ₹{$credits}).",
            ]);
        }

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

        $matchingTransaction = $journalEntry->transactions->first(function ($transaction) use ($statementEntry): bool {
            $code = $transaction->account?->code ?? '';

            if ($statementEntry->direction === 'in') {
                return $transaction->type === 'debit' && in_array($code, ['1010', '1020'], true);
            }

            return $transaction->type === 'credit' && in_array($code, ['1010', '1020'], true);
        });

        if (! $matchingTransaction) {
            throw ValidationException::withMessages([
                'journal_entry_id' => "JournalEntry #{$journalEntry->id} does not contain a matching ".($statementEntry->direction === 'in' ? 'Debit' : 'Credit').' Bank/Cash line.',
            ]);
        }

        $expectedCode = match ($statementEntry->companyAccount?->account_type) {
            'cash' => '1010',
            'bank' => '1020',
            default => null,
        };

        if ($expectedCode !== null && $matchingTransaction->account?->code !== $expectedCode) {
            throw ValidationException::withMessages([
                'journal_entry_id' => "JournalEntry #{$journalEntry->id} uses account {$matchingTransaction->account?->code} ({$matchingTransaction->account?->name}), but statement account is a ".strtoupper((string) $statementEntry->companyAccount?->account_type)." account (expected {$expectedCode}).",
            ]);
        }

        $transactionAmount = round((float) $matchingTransaction->amount, 2);

        if ($clearedAmount > $transactionAmount + 0.01) {
            throw ValidationException::withMessages([
                'cleared_amount' => "Cleared amount (₹{$clearedAmount}) exceeds JournalEntry #{$journalEntry->id} amount (₹{$transactionAmount}).",
            ]);
        }
    }

    private function cashbookSourceForJournal(JournalEntry $journalEntry): ?string
    {
        return match ($journalEntry->source_type) {
            ShopInvoicePaymentRequest::class => 'shop_payment',
            DirectCompanySale::class => 'direct_company_sale',
            CompanyAccountingEntry::class => 'company_accounting_entry',
            VendorSettlement::class => 'vendor_settlement',
            PurchaserCredit::class => 'purchaser_funding',
            ShopLedgerTransaction::class => 'shop_petty_funding',
            PayrollPayment::class => $journalEntry->source_event === 'salary_advance' ? 'salary_advance' : 'salary_payment',
            CompanyPayableSettlement::class => 'company_payable',
            default => $journalEntry->source_event,
        };
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

        if ($journalEntry->source_type !== ShopInvoicePaymentRequest::class || (int) $journalEntry->source_id !== (int) $paymentRequest->id) {
            throw ValidationException::withMessages([
                'journal_entry_id' => 'Selected Journal Entry does not belong to this shop payment.',
            ]);
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

        // 4. Account Type validation (Bank vs Cash mapping)
        $expectedCode = match ($statementEntry->companyAccount?->account_type) {
            'cash' => '1010',
            'bank' => '1020',
            default => null,
        };

        if ($expectedCode !== null && $matchingTxn->account?->code !== $expectedCode) {
            throw ValidationException::withMessages([
                'journal_entry_id' => "JournalEntry #{$journalEntry->id} uses account {$matchingTxn->account?->code} ({$matchingTxn->account?->name}), but statement account is a ".strtoupper((string) $statementEntry->companyAccount?->account_type)." account (expected {$expectedCode}).",
            ]);
        }

        // 5. Amount validation check: cleared amount cannot exceed the journal transaction amount
        $txnAmount = round((float) $matchingTxn->amount, 2);
        if ($clearedAmount > $txnAmount + 0.01) {
            throw ValidationException::withMessages([
                'cleared_amount' => "Cleared amount (₹{$clearedAmount}) exceeds JournalEntry #{$journalEntry->id} amount (₹{$txnAmount}).",
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
