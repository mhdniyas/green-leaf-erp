<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Enums\Cashbook\TransactionStatus;
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
use App\Models\User;
use App\Models\VendorSettlement;
use App\Services\Finance\JournalService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CompanyPaymentReconciliationService
{
    public function __construct(
        private readonly DailyLedgerService $dailyLedgerService,
        private readonly JournalService $journalService,
        private readonly BankSettlementExpectedAmountService $expectedAmountService = new BankSettlementExpectedAmountService,
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

    public function reconcileStatementShopLedger(
        CompanyAccountStatementEntry $statementEntry,
        ShopLedgerTransaction $transaction,
        float $clearedAmount,
        int $userId
    ): CompanyAccountStatementEntry {
        return DB::transaction(function () use ($statementEntry, $transaction, $clearedAmount, $userId): CompanyAccountStatementEntry {
            $statementEntry = CompanyAccountStatementEntry::query()
                ->with('companyAccount')
                ->whereKey($statementEntry->id)
                ->lockForUpdate()
                ->firstOrFail();

            $transaction = ShopLedgerTransaction::query()
                ->whereKey($transaction->id)
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

            $txAmount = round((float) $transaction->amount, 2);
            if ($txAmount <= 0.0) {
                throw ValidationException::withMessages([
                    'transaction_id' => 'Selected transaction has no reconcilable amount.',
                ]);
            }

            $alreadyReconciled = CompanyAccountStatementEntry::query()
                ->where('source_type', ShopLedgerTransaction::class)
                ->where('source_id', $transaction->id)
                ->where('is_finalized', true)
                ->exists();

            if ($alreadyReconciled) {
                throw ValidationException::withMessages([
                    'transaction_id' => 'Selected shop collection is already reconciled.',
                ]);
            }

            CompanyAccountStatementEntry::query()
                ->where('source_type', ShopLedgerTransaction::class)
                ->where('source_id', $transaction->id)
                ->where('id', '!=', $statementEntry->id)
                ->where('is_finalized', false)
                ->update([
                    'status' => 'superseded',
                    'source_type' => null,
                    'source_id' => null,
                    'duplicate_status' => 'manual_cleared',
                    'duplicate_of_statement_entry_id' => $statementEntry->id,
                ]);

            $statementEntry->matched_amount = round((float) $statementEntry->matched_amount + $clearedAmount, 2);
            $statementEntry->source = 'shop_collection';
            $statementEntry->source_type = ShopLedgerTransaction::class;
            $statementEntry->source_id = $transaction->id;
            $statementEntry->status = $statementEntry->matched_amount >= ((float) $statementEntry->amount - 0.01)
                ? 'reconciled'
                : 'partially_matched';
            $statementEntry->is_finalized = $statementEntry->status === 'reconciled';
            $statementEntry->finalized_at = $statementEntry->is_finalized ? now() : null;
            $statementEntry->reconciled_by = $userId;
            $statementEntry->save();

            return $statementEntry;
        });
    }

    public function verifyPendingShopCollection(
        CompanyAccountStatementEntry|int $statement,
        int $userId
    ): CompanyAccountStatementEntry {
        return DB::transaction(function () use ($statement, $userId): CompanyAccountStatementEntry {
            $statementEntry = $statement instanceof CompanyAccountStatementEntry
                ? $statement
                : CompanyAccountStatementEntry::query()->findOrFail($statement);

            $statementEntry = CompanyAccountStatementEntry::query()
                ->with(['companyAccount', 'sourceRecord'])
                ->whereKey($statementEntry->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($statementEntry->source_type !== ShopLedgerTransaction::class || ! $statementEntry->source_id) {
                throw ValidationException::withMessages([
                    'statement' => 'This statement entry is not linked to a shop collection transaction.',
                ]);
            }

            $transaction = ShopLedgerTransaction::query()
                ->with(['entryType', 'shop'])
                ->whereKey($statementEntry->source_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Check if already verified and finalized (Idempotency)
            if ($statementEntry->is_finalized && $statementEntry->status === 'reconciled') {
                return $statementEntry->fresh(['companyAccount', 'journalEntry.transactions.account', 'sourceRecord.entryType']);
            }

            if ($statementEntry->status === 'superseded') {
                throw ValidationException::withMessages([
                    'statement' => 'Superseded statement entries cannot be verified.',
                ]);
            }

            if ($transaction->status !== TransactionStatus::Approved->value && $transaction->status !== 'approved') {
                throw ValidationException::withMessages([
                    'transaction' => 'Only approved shop collection transactions can be verified.',
                ]);
            }

            if ($transaction->status === TransactionStatus::Void->value || $transaction->status === 'void') {
                throw ValidationException::withMessages([
                    'transaction' => 'Voided shop collection transactions cannot be verified.',
                ]);
            }

            if ((int) $statementEntry->company_account_id !== (int) $transaction->company_account_id) {
                throw ValidationException::withMessages([
                    'statement' => 'Statement company account does not match the transaction destination account.',
                ]);
            }

            $expectedDirection = $transaction->direction === 'income' ? 'in' : 'out';
            if ($statementEntry->direction !== $expectedDirection) {
                throw ValidationException::withMessages([
                    'statement' => 'Statement direction does not match the transaction direction.',
                ]);
            }

            $statementAmount = round((float) $statementEntry->amount, 2);
            $resolved = $this->expectedAmountService->resolve(
                (int) $transaction->shop_id,
                $transaction->business_date->toDateString(),
                (int) $transaction->entry_type_id,
                (float) $transaction->amount
            );
            $expectedPaymentAmount = (float) $resolved['expected_amount'];

            if (abs($statementAmount - $expectedPaymentAmount) > 0.005) {
                throw ValidationException::withMessages([
                    'statement' => 'Statement amount does not match the expected shop payment amount.',
                ]);
            }

            $companyAccount = $statementEntry->companyAccount;
            if (! $companyAccount instanceof CompanyAccount || ! $companyAccount->enabled) {
                throw ValidationException::withMessages([
                    'statement' => 'The target company account is not active or enabled.',
                ]);
            }

            // 1. Exactly-once balance movement on CompanyAccount
            $this->applyStatementBalanceMovement($companyAccount, $statementEntry);

            // 2. Finalize the SAME statement
            $statementEntry->matched_amount = $statementAmount;
            $statementEntry->status = 'reconciled';
            $statementEntry->is_finalized = true;
            $statementEntry->finalized_at = now();
            $statementEntry->reconciled_by = $userId;
            $statementEntry->reconciled_at = now();
            $statementEntry->save();

            // 3. Record Journal Entry
            $journal = $this->journalService->recordShopCollection($transaction, $companyAccount, $userId, $statementAmount);
            $statementEntry->update(['journal_entry_id' => $journal->id]);

            // 4. Reduce Shop Payable by recording shop_paid_company settlement transaction
            $existingSettlement = ShopLedgerTransaction::query()
                ->where('shop_id', $transaction->shop_id)
                ->where('reference_type', CompanyAccountStatementEntry::class)
                ->where('reference_id', $statementEntry->id)
                ->whereHas('entryType', fn ($q) => $q->where('code', 'shop_paid_company'))
                ->lockForUpdate()
                ->first();

            if ($existingSettlement instanceof ShopLedgerTransaction) {
                if (in_array($existingSettlement->status, ['void', 'voided', 'reversed'], true)) {
                    $existingSettlement->update([
                        'amount' => $statementAmount,
                        'business_date' => $transaction->business_date->toDateString(),
                        'company_account_id' => $companyAccount->id,
                        'status' => 'posted',
                        'notes' => 'Verified company receipt for '.($transaction->entryType?->name ?? 'Collection').' #'.$transaction->id,
                    ]);
                    $this->dailyLedgerService->dailySummary((int) $transaction->shop_id, $transaction->business_date->toDateString());
                }
            } else {
                $this->dailyLedgerService->recordEntry([
                    'shop_id' => (int) $transaction->shop_id,
                    'business_date' => $transaction->business_date->toDateString(),
                    'entry_type_code' => 'shop_paid_company',
                    'amount' => $statementAmount,
                    'funding_source' => 'sales',
                    'company_account_id' => $companyAccount->id,
                    'reference_type' => CompanyAccountStatementEntry::class,
                    'reference_id' => $statementEntry->id,
                    'notes' => 'Verified company receipt for '.($transaction->entryType?->name ?? 'Collection').' #'.$transaction->id,
                    'entered_by' => $userId,
                ]);
            }

            return $statementEntry->fresh(['companyAccount', 'journalEntry.transactions.account', 'sourceRecord.entryType']);
        }, attempts: 3);
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

    public function unmatchStatementJournal(CompanyAccountStatementEntry $statementEntry, int $userId, ?string $month = null): CompanyAccountStatementEntry
    {
        return DB::transaction(function () use ($statementEntry, $userId, $month): CompanyAccountStatementEntry {
            $statementEntry = CompanyAccountStatementEntry::query()
                ->with(['companyAccount', 'journalEntry', 'reconciliations.paymentRequest'])
                ->whereKey($statementEntry->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $statementEntry->is_finalized && $statementEntry->status === 'unmatched' && $statementEntry->journal_entry_id === null && $statementEntry->source_id === null && $statementEntry->reconciliations->isEmpty()) {
                throw ValidationException::withMessages([
                    'statement_entry_id' => 'This statement entry is not currently reconciled.',
                ]);
            }

            $isImported = $statementEntry->source === 'imported'
                || ! empty($statementEntry->import_file_name)
                || ! empty($statementEntry->import_fingerprint);

            if ($isImported) {
                $oldSourceType = $statementEntry->source_type;
                $oldSourceId = $statementEntry->source_id;
                $oldJournalEntryId = $statementEntry->journal_entry_id;
                $oldStatus = $statementEntry->status;

                if ($oldSourceType === VendorSettlement::class && $oldSourceId) {
                    VendorSettlement::query()->whereKey($oldSourceId)->update([
                        'reconciliation_status' => 'pending',
                        'is_finalized' => false,
                        'finalized_at' => null,
                    ]);
                }

                if ($oldSourceType === DirectCompanySale::class && $oldSourceId) {
                    DirectCompanySale::query()->whereKey($oldSourceId)->update([
                        'reconciliation_status' => 'pending',
                        'is_finalized' => false,
                        'finalized_at' => null,
                    ]);
                }

                if ($statementEntry->reconciliations->isNotEmpty()) {
                    $payments = $statementEntry->reconciliations->pluck('paymentRequest')->filter();
                    $statementEntry->reconciliations()->delete();
                    foreach ($payments as $payment) {
                        $this->refreshPaymentReconciliationTotals($payment);
                    }
                }

                CompanyAccountStatementEntry::query()
                    ->where('duplicate_of_statement_entry_id', $statementEntry->id)
                    ->when($month !== null, fn ($query) => $query->whereDate('transaction_date', '>=', Carbon::parse($month.'-01')->startOfMonth()->toDateString())->whereDate('transaction_date', '<=', Carbon::parse($month.'-01')->endOfMonth()->toDateString()))
                    ->update([
                        'status' => 'unmatched',
                        'duplicate_status' => 'clear',
                        'duplicate_of_statement_entry_id' => null,
                    ]);

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

                Log::info('Reconciliation match unlinked', [
                    'statement_entry_id' => $statementEntry->id,
                    'old_source_type' => $oldSourceType,
                    'old_source_id' => $oldSourceId,
                    'old_journal_entry_id' => $oldJournalEntryId,
                    'previous_status' => $oldStatus,
                    'actor' => $userId,
                    'timestamp' => now()->toIso8601String(),
                    'reason' => 'manual_unmatch',
                ]);
            } else {
                throw ValidationException::withMessages([
                    'statement_entry_id' => 'Manual cash/statement counterparts represent committed cashbook ledger movements and cannot be unlinked.',
                ]);
            }

            return $statementEntry->fresh(['companyAccount', 'journalEntry']);
        });
    }

    /**
     * Resets reconciliation matches for a selected month.
     * Clears imported matches back to unmatched / needs review, and safely skips manual counterparts.
     *
     * @return array{cleared: int, skipped: int, skipped_entries: list<array<string, mixed>>, month: string}
     */
    public function resetMonthReconciliation(string $month, int $userId): array
    {
        return DB::transaction(function () use ($month, $userId): array {
            $selectedMonth = Carbon::parse($month.'-01');
            $monthStart = $selectedMonth->copy()->startOfMonth()->toDateString();
            $monthEnd = $selectedMonth->copy()->endOfMonth()->toDateString();

            $statementEntries = CompanyAccountStatementEntry::query()
                ->with(['companyAccount', 'journalEntry', 'reconciliations.paymentRequest'])
                ->whereDate('transaction_date', '>=', $monthStart)
                ->whereDate('transaction_date', '<=', $monthEnd)
                ->where(function ($query): void {
                    $query->where('is_finalized', true)
                        ->orWhereIn('status', ['matched', 'reconciled', 'partially_matched'])
                        ->orWhereNotNull('journal_entry_id')
                        ->orWhereNotNull('source_id')
                        ->orWhereHas('reconciliations');
                })
                ->lockForUpdate()
                ->get();

            $clearedCount = 0;
            $skippedCount = 0;
            $skippedEntries = [];

            foreach ($statementEntries as $entry) {
                $isImported = $entry->source === 'imported'
                    || ! empty($entry->import_file_name)
                    || ! empty($entry->import_fingerprint);

                if ($isImported) {
                    $oldSourceType = $entry->source_type;
                    $oldSourceId = $entry->source_id;
                    $oldJournalEntryId = $entry->journal_entry_id;
                    $oldStatus = $entry->status;

                    $this->unmatchStatementJournal($entry, $userId, $month);

                    Log::info('Reconciliation match cleared during month reset', [
                        'statement_entry_id' => $entry->id,
                        'month' => $month,
                        'old_source_type' => $oldSourceType,
                        'old_source_id' => $oldSourceId,
                        'old_journal_entry_id' => $oldJournalEntryId,
                        'previous_status' => $oldStatus,
                        'action' => 'monthly_reconciliation_reset',
                        'actor' => $userId,
                        'timestamp' => now()->toIso8601String(),
                    ]);

                    if (function_exists('activity')) {
                        activity('reconciliation')
                            ->causedBy(User::find($userId))
                            ->performedOn($entry)
                            ->withProperties([
                                'statement_entry_id' => $entry->id,
                                'month' => $month,
                                'old_source_type' => $oldSourceType,
                                'old_source_id' => $oldSourceId,
                                'old_journal_entry_id' => $oldJournalEntryId,
                                'previous_status' => $oldStatus,
                                'action' => 'monthly_reconciliation_reset',
                            ])
                            ->log("Reconciliation reset for statement entry #{$entry->id} ({$month})");
                    }

                    $clearedCount++;
                } else {
                    $skippedCount++;
                    $skippedEntries[] = [
                        'id' => $entry->id,
                        'reference' => $entry->reference ?: '—',
                        'amount' => (float) $entry->amount,
                        'source' => $entry->source,
                        'reason' => 'Manual cash/statement counterpart protected',
                    ];
                }
            }

            Log::info('Reconciliation month reset batch completed', [
                'action' => 'monthly_reconciliation_reset',
                'actor' => $userId,
                'month' => $month,
                'timestamp' => now()->toIso8601String(),
                'cleared_count' => $clearedCount,
                'skipped_count' => $skippedCount,
            ]);

            if (function_exists('activity')) {
                activity('reconciliation')
                    ->causedBy(User::find($userId))
                    ->withProperties([
                        'month' => $month,
                        'cleared_count' => $clearedCount,
                        'skipped_count' => $skippedCount,
                        'action' => 'monthly_reconciliation_reset',
                    ])
                    ->log("Batch reconciliation reset completed for month {$month}: {$clearedCount} cleared, {$skippedCount} skipped.");
            }

            return [
                'cleared' => $clearedCount,
                'skipped' => $skippedCount,
                'skipped_entries' => $skippedEntries,
                'month' => $month,
            ];
        });
    }

    public function replaceStatementJournalMatch(
        CompanyAccountStatementEntry $statementEntry,
        JournalEntry $newJournalEntry,
        float $amount,
        int $userId
    ): CompanyAccountStatementEntry {
        return DB::transaction(function () use ($statementEntry, $newJournalEntry, $amount, $userId): CompanyAccountStatementEntry {
            $statementEntry = CompanyAccountStatementEntry::query()
                ->with(['companyAccount', 'journalEntry'])
                ->whereKey($statementEntry->id)
                ->lockForUpdate()
                ->firstOrFail();

            $newJournalEntry = JournalEntry::query()
                ->with('transactions.account')
                ->whereKey($newJournalEntry->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldJournalEntryId = $statementEntry->journal_entry_id;
            $oldSourceType = $statementEntry->source_type;
            $oldSourceId = $statementEntry->source_id;

            // If statement is not linked to any journal entry, just regular reconcile
            if ($oldJournalEntryId === null) {
                return $this->reconcileStatementJournal($statementEntry, $newJournalEntry, $amount, $userId);
            }

            if ($oldJournalEntryId === $newJournalEntry->id) {
                if ($statementEntry->is_finalized) {
                    throw ValidationException::withMessages([
                        'candidate_ref' => 'This transaction is already matched to this statement.',
                    ]);
                }

                return $statementEntry;
            }

            if ($oldSourceType === VendorSettlement::class && $oldSourceId) {
                VendorSettlement::query()->whereKey($oldSourceId)->update([
                    'reconciliation_status' => 'pending',
                    'is_finalized' => false,
                    'finalized_at' => null,
                ]);
            }

            if ($oldSourceType === DirectCompanySale::class && $oldSourceId) {
                DirectCompanySale::query()->whereKey($oldSourceId)->update([
                    'reconciliation_status' => 'pending',
                    'is_finalized' => false,
                    'finalized_at' => null,
                ]);
            }

            // Link to new journal entry
            $statementEntry->journal_entry_id = $newJournalEntry->id;
            $statementEntry->source_type = $newJournalEntry->source_type;
            $statementEntry->source_id = $newJournalEntry->source_id;
            $statementEntry->matched_amount = $amount;
            $statementEntry->status = 'matched';
            $statementEntry->is_finalized = true;
            $statementEntry->finalized_at = now();
            $statementEntry->reconciled_by = $userId;
            $statementEntry->reconciled_at = now();
            $statementEntry->save();

            if ($newJournalEntry->source_type === PurchaserCredit::class && $newJournalEntry->source_id) {
                PurchaserCredit::query()->whereKey($newJournalEntry->source_id)->update([
                    'company_account_id' => $statementEntry->company_account_id,
                    'payment_source' => $statementEntry->companyAccount?->account_type === 'cash' ? 'Cash' : 'Bank',
                ]);
            }

            if ($newJournalEntry->source_type === VendorSettlement::class && $newJournalEntry->source_id) {
                VendorSettlement::query()->whereKey($newJournalEntry->source_id)->update([
                    'reconciliation_status' => 'finalized',
                    'is_finalized' => true,
                    'finalized_at' => now(),
                ]);
            }

            if ($newJournalEntry->source_type === DirectCompanySale::class && $newJournalEntry->source_id) {
                DirectCompanySale::query()->whereKey($newJournalEntry->source_id)->update([
                    'reconciliation_status' => 'finalized',
                    'is_finalized' => true,
                    'finalized_at' => now(),
                ]);
            }

            Log::info('Reconciliation match replaced', [
                'statement_entry_id' => $statementEntry->id,
                'old_source_type' => $oldSourceType,
                'old_source_id' => $oldSourceId,
                'old_journal_entry_id' => $oldJournalEntryId,
                'new_source_type' => $newJournalEntry->source_type,
                'new_source_id' => $newJournalEntry->source_id,
                'new_journal_entry_id' => $newJournalEntry->id,
                'actor' => $userId,
                'timestamp' => now()->toIso8601String(),
                'reason' => 'reconciliation_match_replaced',
            ]);

            return $statementEntry->fresh(['companyAccount', 'journalEntry.transactions.account']);
        });
    }

    /**
     * @return array<int, class-string>
     */
    public function matchableCashbookSourceTypes(): array
    {
        return [
            ShopInvoicePaymentRequest::class,
            DirectCompanySale::class,
            CompanyAccountingEntry::class,
            VendorSettlement::class,
            PurchaserCredit::class,
            ShopLedgerTransaction::class,
            PayrollPayment::class,
            CompanyPayableSettlement::class,
        ];
    }

    /**
     * Finds statement candidates for a given cashbook/journal source.
     *
     * @return array{
     *     pending: list<array<string, mixed>>,
     *     reconciled: list<array<string, mixed>>,
     *     counts: array{
     *         pending: int,
     *         reconciled: int,
     *         exact_date_pending: int,
     *         exact_date_reconciled: int
     *     }
     * }
     */
    public function findStatementCandidates(
        ?int $companyAccountId,
        float $amount,
        string $direction,
        \DateTimeInterface|string|null $referenceDate = null,
        ?string $search = null
    ): array {
        $amount = round($amount, 2);
        $referenceDate = $referenceDate ? Carbon::parse($referenceDate) : today();
        $referenceDateString = $referenceDate->toDateString();
        $search = trim((string) $search);

        $pendingEntries = CompanyAccountStatementEntry::query()
            ->with('companyAccount')
            ->where('direction', $direction)
            ->where('amount', $amount)
            ->where('is_finalized', false)
            ->where('status', 'unmatched')
            ->whereNull('journal_entry_id')
            ->when($companyAccountId !== null && $companyAccountId > 0, fn ($query) => $query->where('company_account_id', $companyAccountId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(fn ($sub) => $sub->where('reference', 'like', '%'.$search.'%')->orWhere('narration', 'like', '%'.$search.'%'));
            })
            ->get();

        $reconciledEntries = CompanyAccountStatementEntry::query()
            ->with(['companyAccount', 'journalEntry', 'reconciledBy'])
            ->where('direction', $direction)
            ->where('amount', $amount)
            ->where(function ($query): void {
                $query->where('is_finalized', true)
                    ->orWhere('status', '!=', 'unmatched')
                    ->orWhereNotNull('journal_entry_id');
            })
            ->when($companyAccountId !== null && $companyAccountId > 0, fn ($query) => $query->where('company_account_id', $companyAccountId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(fn ($sub) => $sub->where('reference', 'like', '%'.$search.'%')->orWhere('narration', 'like', '%'.$search.'%'));
            })
            ->get();

        $mapStatement = function (CompanyAccountStatementEntry $stmt, string $status) use ($referenceDate, $referenceDateString): array {
            $stmtDate = $stmt->transaction_date;
            $diffDays = 99999;
            $isExactDate = false;

            if ($stmtDate !== null) {
                $diffDays = (int) abs($stmtDate->diffInDays($referenceDate, false));
                $isExactDate = ($diffDays === 0);
            }

            $dateMatch = $isExactDate ? 'exact' : 'other';
            $dateBadgeText = $isExactDate
                ? 'EXACT DATE'
                : ($diffDays === 1 ? '1 DAY AWAY' : ($diffDays < 99999 ? "{$diffDays} DAYS AWAY" : 'NO DATE'));

            $item = [
                'id' => $stmt->id,
                'public_uuid' => $stmt->public_uuid,
                'transaction_date' => $stmt->transaction_date?->format('d M Y') ?? '—',
                'raw_date' => $stmt->transaction_date?->toDateString() ?? '',
                'funding_date' => $referenceDateString,
                'date_match' => $dateMatch,
                'date_difference_days' => $diffDays,
                'date_badge_text' => $dateBadgeText,
                'account_name' => $stmt->companyAccount?->name ?? 'Company Account',
                'account_type' => $stmt->companyAccount?->account_type ?? 'bank',
                'reference' => $stmt->reference ?: '—',
                'narration' => $stmt->narration ?: $stmt->notes ?: '—',
                'amount' => (float) $stmt->amount,
                'formatted_amount' => '₹'.number_format((float) $stmt->amount, 2),
                'status' => $status,
            ];

            if ($status === 'MATCHED') {
                $matchedTo = 'Reconciled Entry #'.$stmt->id;
                if ($stmt->source_type === PurchaserCredit::class && $stmt->source_id) {
                    $matchedCredit = PurchaserCredit::with('purchaser')->find($stmt->source_id);
                    $matchedTo = 'Purchaser Funding #'.$stmt->source_id.($matchedCredit?->purchaser ? ' ('.$matchedCredit->purchaser->name.')' : '');
                } elseif ($stmt->journalEntry) {
                    $matchedTo = $stmt->journalEntry->formatted_reference.($stmt->journalEntry->description ? ' ('.$stmt->journalEntry->description.')' : '');
                }

                $item['matched_to'] = $matchedTo;
                $item['matched_date'] = $stmt->reconciled_at?->format('d M Y H:i') ?? $stmt->finalized_at?->format('d M Y H:i') ?? '—';
                $item['matched_by'] = $stmt->reconciledBy?->name ?? 'System';
            }

            return $item;
        };

        $sortCandidates = function (array $a, array $b): int {
            // 1. EXACT DATE first
            $aExact = $a['date_match'] === 'exact' ? 0 : 1;
            $bExact = $b['date_match'] === 'exact' ? 0 : 1;
            if ($aExact !== $bExact) {
                return $aExact <=> $bExact;
            }

            // 2. Smallest absolute date difference
            if ($a['date_difference_days'] !== $b['date_difference_days']) {
                return $a['date_difference_days'] <=> $b['date_difference_days'];
            }

            // 3. transaction_date DESC
            $dateCompare = strcmp((string) ($b['raw_date'] ?? ''), (string) ($a['raw_date'] ?? ''));
            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            // 4. id DESC
            return $b['id'] <=> $a['id'];
        };

        $pendingList = $pendingEntries->map(fn (CompanyAccountStatementEntry $stmt) => $mapStatement($stmt, 'UNMATCHED'))->all();
        usort($pendingList, $sortCandidates);

        $reconciledList = $reconciledEntries->map(fn (CompanyAccountStatementEntry $stmt) => $mapStatement($stmt, 'MATCHED'))->all();
        usort($reconciledList, $sortCandidates);

        $exactPendingCount = count(array_filter($pendingList, fn ($item) => $item['date_match'] === 'exact'));
        $exactReconciledCount = count(array_filter($reconciledList, fn ($item) => $item['date_match'] === 'exact'));

        return [
            'pending' => $pendingList,
            'reconciled' => $reconciledList,
            'counts' => [
                'pending' => count($pendingList),
                'reconciled' => count($reconciledList),
                'exact_date_pending' => $exactPendingCount,
                'exact_date_reconciled' => $exactReconciledCount,
            ],
        ];
    }

    /**
     * @param  Collection<int, object>  $transactions
     * @return Collection<string, Collection<int, CompanyAccountStatementEntry>>
     */
    public function findEligibleStatementCandidatePools(Collection $transactions, int $graceDays): Collection
    {
        $keys = $transactions
            ->filter(fn (object $transaction): bool => (int) ($transaction->company_account_id ?? 0) > 0)
            ->map(function (object $transaction): array {
                $dir = strtolower((string) $transaction->direction);
                $normDirection = match ($dir) {
                    'income', 'in' => 'in',
                    'expense', 'out' => 'out',
                    default => $dir,
                };

                return [
                    'company_account_id' => (int) $transaction->company_account_id,
                    'direction' => $normDirection,
                    'amount' => round((float) ($transaction->effective_match_amount ?? $transaction->amount), 2),
                ];
            })
            ->unique(fn (array $key): string => $key['company_account_id'].'|'.$key['direction'].'|'.$key['amount'])
            ->values();

        if ($keys->isEmpty()) {
            return collect();
        }

        $dates = $transactions->map(function (object $t): ?string {
            $d = $t->business_date ?? $t->transaction_date ?? null;

            return $d ? Carbon::parse((string) $d)->toDateString() : null;
        })->filter();
        $startDate = Carbon::parse((string) $dates->min())->subDays($graceDays)->toDateString();
        $endDate = Carbon::parse((string) $dates->max())->addDays($graceDays)->toDateString();

        $entries = CompanyAccountStatementEntry::query()
            ->with('companyAccount')
            ->where('is_finalized', false)
            ->where('status', 'unmatched')
            ->whereNull('journal_entry_id')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->where(function ($query) use ($keys): void {
                foreach ($keys as $key) {
                    $query->orWhere(function ($candidateQuery) use ($key): void {
                        $candidateQuery
                            ->where('company_account_id', $key['company_account_id'])
                            ->where('direction', $key['direction'])
                            ->where('amount', $key['amount']);
                    });
                }
            })
            ->get();

        return $entries->groupBy(fn (CompanyAccountStatementEntry $entry): string => $entry->company_account_id.'|'.$entry->direction.'|'.round((float) $entry->amount, 2));
    }

    /**
     * Finds journal candidates for a given statement entry.
     *
     * @return array{
     *     pending: list<array<string, mixed>>,
     *     reconciled: list<array<string, mixed>>,
     *     counts: array{
     *         pending: int,
     *         reconciled: int,
     *         exact_date_pending: int,
     *         exact_date_reconciled: int
     *     }
     * }
     */
    public function findJournalCandidatesForStatement(
        CompanyAccountStatementEntry $statementEntry,
        ?string $search = null
    ): array {
        $statementEntry->loadMissing(['companyAccount']);
        $statementDate = $statementEntry->transaction_date ?: today();
        $statementDateString = $statementDate->toDateString();
        $statementAmount = round((float) $statementEntry->amount - (float) $statementEntry->matched_amount, 2);
        $expectedCode = $statementEntry->companyAccount?->account_type === 'cash' ? '1010' : '1020';
        $cashBankType = $statementEntry->direction === 'in' ? 'debit' : 'credit';
        $search = trim((string) $search);

        $pendingEntries = JournalEntry::query()
            ->with(['transactions.account', 'statementEntries.companyAccount', 'createdBy'])
            ->whereIn('source_type', $this->matchableCashbookSourceTypes())
            ->whereHas('transactions', fn ($query) => $query
                ->where('type', $cashBankType)
                ->whereHas('account', fn ($accountQuery) => $accountQuery->where('code', $expectedCode)))
            ->whereDoesntHave('statementEntries', fn ($query) => $query->where('is_finalized', true))
            ->when($search !== '', function ($query) use ($search): void {
                $numericSearch = is_numeric($search) ? round((float) $search, 2) : null;
                $query->where(function ($sub) use ($numericSearch, $search): void {
                    $sub->where('reference', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhere('source_event', 'like', '%'.$search.'%');

                    if ($numericSearch !== null) {
                        $sub->orWhereHas('transactions', fn ($txQuery) => $txQuery->where('amount', $numericSearch));
                    }
                });
            })
            ->get()
            ->filter(function (JournalEntry $journalEntry) use ($statementAmount): bool {
                $openAmount = round((float) $journalEntry->primary_amount - (float) $journalEntry->statementEntries->sum('matched_amount'), 2);

                return abs($openAmount - $statementAmount) <= 0.01;
            });

        $reconciledEntries = JournalEntry::query()
            ->with(['transactions.account', 'statementEntries.companyAccount', 'createdBy'])
            ->whereIn('source_type', $this->matchableCashbookSourceTypes())
            ->whereHas('transactions', fn ($query) => $query
                ->where('type', $cashBankType)
                ->whereHas('account', fn ($accountQuery) => $accountQuery->where('code', $expectedCode)))
            ->whereHas('statementEntries', fn ($query) => $query->where('is_finalized', true))
            ->when($search !== '', function ($query) use ($search): void {
                $numericSearch = is_numeric($search) ? round((float) $search, 2) : null;
                $query->where(function ($sub) use ($numericSearch, $search): void {
                    $sub->where('reference', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhere('source_event', 'like', '%'.$search.'%');

                    if ($numericSearch !== null) {
                        $sub->orWhereHas('transactions', fn ($txQuery) => $txQuery->where('amount', $numericSearch));
                    }
                });
            })
            ->get()
            ->filter(function (JournalEntry $journalEntry) use ($statementEntry): bool {
                return abs((float) $journalEntry->primary_amount - (float) $statementEntry->amount) <= 0.01;
            });

        $mapJournal = function (JournalEntry $journal, string $status) use ($statementDate, $statementDateString): array {
            $entryDate = $journal->entry_date;
            $diffDays = 99999;
            $isExactDate = false;

            if ($entryDate !== null) {
                $diffDays = (int) abs($entryDate->diffInDays($statementDate, false));
                $isExactDate = ($diffDays === 0);
            }

            $dateMatch = $isExactDate ? 'exact' : 'other';
            $dateBadgeText = $isExactDate
                ? 'EXACT DATE'
                : ($diffDays === 1 ? '1 DAY AWAY' : ($diffDays < 99999 ? "{$diffDays} DAYS AWAY" : 'NO DATE'));

            $cashBankTransaction = $journal->transactions->first(fn ($tx) => in_array($tx->account?->code, ['1010', '1020'], true));
            $accountName = $cashBankTransaction?->account?->name ?? 'Cash/Bank Account';
            $accountType = $cashBankTransaction?->account?->code === '1010' ? 'cash' : 'bank';

            $openAmount = round((float) $journal->primary_amount - (float) $journal->statementEntries->sum('matched_amount'), 2);

            $item = [
                'id' => $journal->id,
                'journal_entry' => $journal,
                'candidate_ref' => rtrim(strtr(base64_encode(Crypt::encryptString('journal-entry:'.$journal->getKey())), '+/', '-_'), '='),
                'formatted_reference' => $journal->reference ?: $journal->formatted_reference,
                'reference' => $journal->reference ?: $journal->formatted_reference,
                'source_label' => $journal->source_label,
                'source_type' => $journal->source_type,
                'source_id' => $journal->source_id,
                'description' => $journal->description ?: 'No description',
                'entry_date' => $journal->entry_date?->format('d M Y') ?? '—',
                'raw_date' => $journal->entry_date?->toDateString() ?? '',
                'statement_date' => $statementDateString,
                'date_match' => $dateMatch,
                'date_difference_days' => $diffDays,
                'date_badge_text' => $dateBadgeText,
                'account_name' => $accountName,
                'account_type' => $accountType,
                'amount' => (float) $journal->primary_amount,
                'floating_amount' => max(0, $openAmount),
                'formatted_amount' => '₹'.number_format((float) $journal->primary_amount, 2),
                'status' => $status,
                'reconciliation_status_label' => $journal->reconciliation_status_label,
            ];

            if ($status === 'MATCHED') {
                $finalizedStmt = $journal->statementEntries->firstWhere('is_finalized', true);
                $item['matched_statement_id'] = $finalizedStmt?->id;
                $item['matched_statement_ref'] = $finalizedStmt?->reference ?: '—';
                $item['matched_to'] = $finalizedStmt ? 'Statement #'.$finalizedStmt->id.' ('.$finalizedStmt->companyAccount?->name.')' : 'Reconciled Statement';
                $item['matched_date'] = $finalizedStmt?->finalized_at?->format('d M Y H:i') ?? '—';
                $item['matched_by'] = $finalizedStmt?->reconciledBy?->name ?? 'System';
            }

            return $item;
        };

        $sortCandidates = function (array $a, array $b): int {
            // 1. EXACT DATE first
            $aExact = $a['date_match'] === 'exact' ? 0 : 1;
            $bExact = $b['date_match'] === 'exact' ? 0 : 1;
            if ($aExact !== $bExact) {
                return $aExact <=> $bExact;
            }

            // 2. Smallest absolute date difference
            if ($a['date_difference_days'] !== $b['date_difference_days']) {
                return $a['date_difference_days'] <=> $b['date_difference_days'];
            }

            // 3. entry_date DESC
            $dateCompare = strcmp((string) ($b['raw_date'] ?? ''), (string) ($a['raw_date'] ?? ''));
            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            // 4. id DESC
            return $b['id'] <=> $a['id'];
        };

        $pendingList = $pendingEntries->map(fn (JournalEntry $journal) => $mapJournal($journal, 'UNMATCHED'))->values()->all();
        usort($pendingList, $sortCandidates);

        $reconciledList = $reconciledEntries->map(fn (JournalEntry $journal) => $mapJournal($journal, 'MATCHED'))->values()->all();
        usort($reconciledList, $sortCandidates);

        $exactPendingCount = count(array_filter($pendingList, fn ($item) => $item['date_match'] === 'exact'));
        $exactReconciledCount = count(array_filter($reconciledList, fn ($item) => $item['date_match'] === 'exact'));

        return [
            'pending' => $pendingList,
            'reconciled' => $reconciledList,
            'counts' => [
                'pending' => count($pendingList),
                'reconciled' => count($reconciledList),
                'exact_date_pending' => $exactPendingCount,
                'exact_date_reconciled' => $exactReconciledCount,
            ],
        ];
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
