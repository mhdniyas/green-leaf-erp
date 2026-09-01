<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\CompanyPaymentReconciliation;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Services\Finance\JournalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CashbookTransactionReversalService
{
    public function __construct(
        private readonly BalanceCalculator $calculator,
        private readonly DailyLedgerService $dailyLedgerService,
        private readonly JournalService $journalService,
    ) {}

    /**
     * Atomically reverse a finalized/reconciled shop collection transaction.
     * Undoes company balance movement, reverses shop settlement (shop_paid_company),
     * records offsetting reversal journal, recalculates daily snapshots, and marks
     * transaction as REVERSED without deleting audit rows.
     */
    public function reverseReconciledTransaction(
        ShopLedgerTransaction|int $transaction,
        int $userId,
        string $reason
    ): ShopLedgerTransaction {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw ValidationException::withMessages([
                'reason' => 'A valid reversal reason (at least 3 characters) is required.',
            ]);
        }

        return DB::transaction(function () use ($transaction, $userId, $reason): ShopLedgerTransaction {
            $txId = $transaction instanceof ShopLedgerTransaction ? $transaction->id : $transaction;

            /** @var ShopLedgerTransaction $model */
            $model = ShopLedgerTransaction::query()
                ->with(['entryType', 'shop', 'companyAccount'])
                ->whereKey($txId)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotency: If already reversed or void, return without duplicating effects
            if (in_array($model->status, ['reversed', 'void', 'voided'], true)) {
                return $model->fresh(['entryType', 'shop', 'companyAccount']);
            }

            $statement = CompanyAccountStatementEntry::query()
                ->where('source_type', ShopLedgerTransaction::class)
                ->where('source_id', $model->id)
                ->lockForUpdate()
                ->first();

            $companyAccount = null;
            if ($statement instanceof CompanyAccountStatementEntry) {
                // 1. Reverse Company Account balance if statement was finalized
                if ($statement->is_finalized && $statement->company_account_id) {
                    $companyAccount = CompanyAccount::query()
                        ->whereKey($statement->company_account_id)
                        ->lockForUpdate()
                        ->first();

                    if ($companyAccount instanceof CompanyAccount) {
                        if ($statement->direction === 'in' || $statement->direction === 'credit') {
                            $companyAccount->decrement('current_balance', (float) $statement->amount);
                        } elseif ($statement->direction === 'out' || $statement->direction === 'debit') {
                            $companyAccount->increment('current_balance', (float) $statement->amount);
                        }

                        // 2. Record Reversing Journal Entry (offsetting debit/credit)
                        $this->journalService->recordShopCollectionReversal($model, $companyAccount, $userId, $reason);
                    }
                }

                // 3. Reverse Shop Settlement reduction (shop_paid_company)
                $settlementTx = ShopLedgerTransaction::query()
                    ->where('shop_id', $model->shop_id)
                    ->where('reference_type', CompanyAccountStatementEntry::class)
                    ->where('reference_id', $statement->id)
                    ->whereHas('entryType', fn ($q) => $q->where('code', 'shop_paid_company'))
                    ->lockForUpdate()
                    ->first();

                if ($settlementTx instanceof ShopLedgerTransaction) {
                    $settlementTx->update([
                        'status' => 'void',
                        'notes' => trim(($settlementTx->notes ?? '')." [Reversed: {$reason}]"),
                    ]);
                }

                // 4. Void the linked statement entry
                $statement->update([
                    'is_finalized' => false,
                    'status' => 'void',
                    'matched_amount' => 0,
                    'notes' => trim(($statement->notes ?? '')." [Reversed: {$reason}]"),
                ]);

                // 4b. Cancel any linked ShopInvoicePaymentRequest & CompanyPaymentReconciliation
                $reconciliations = CompanyPaymentReconciliation::query()
                    ->where('statement_entry_id', $statement->id)
                    ->lockForUpdate()
                    ->get();

                foreach ($reconciliations as $recon) {
                    $recon->update(['status' => 'cancelled', 'is_finalized' => false]);
                    if ($recon->paymentRequest) {
                        $recon->paymentRequest->update([
                            'status' => 'cancelled',
                            'reconciliation_status' => 'cancelled',
                            'reconciled_amount' => 0.00,
                            'admin_note' => trim(($recon->paymentRequest->admin_note ?? '')." [Reversed: {$reason}]"),
                        ]);
                    }
                }
            }

            // 5. Update original transaction state to REVERSED with audit trail
            $model->update([
                'status' => 'reversed',
                'voided_by' => $userId,
                'voided_at' => now(),
                'void_reason' => $reason,
                'notes' => trim(($model->notes ?? '')." [Reversed by admin: {$reason}]"),
            ]);

            // 6. Recalculate daily ledger snapshot for shop
            if ($model->business_date) {
                $this->calculator->recalculate($model->shop_id, $model->business_date->toDateString());
            }

            Log::info('Shop ledger transaction reversed', [
                'transaction_id' => $model->id,
                'shop_id' => $model->shop_id,
                'amount' => $model->amount,
                'reason' => $reason,
                'user_id' => $userId,
            ]);

            return $model->fresh(['entryType', 'shop', 'companyAccount']);
        }, attempts: 3);
    }

    /**
     * Correct a reconciled transaction by reversing its financial effects
     * and putting it back into an editable posted state with corrected parameters.
     *
     * @param  array{amount?: float, business_date?: string, notes?: string|null, entry_type_id?: int|null}  $correctedData
     */
    public function correctReconciledTransaction(
        ShopLedgerTransaction|int $transaction,
        array $correctedData,
        int $userId,
        string $reason
    ): ShopLedgerTransaction {
        return DB::transaction(function () use ($transaction, $correctedData, $userId, $reason): ShopLedgerTransaction {
            $txId = $transaction instanceof ShopLedgerTransaction ? $transaction->id : $transaction;

            /** @var ShopLedgerTransaction $model */
            $model = ShopLedgerTransaction::query()
                ->whereKey($txId)
                ->lockForUpdate()
                ->firstOrFail();

            $oldDate = $model->business_date?->toDateString();

            // Step 1: Safely reverse all existing financial effects
            $this->reverseReconciledTransaction($model, $userId, 'Correction: '.$reason);

            // Step 2 & 3: Reset status to posted and apply updated fields
            $updates = [
                'status' => 'posted',
                'approved_by' => null,
                'approved_at' => null,
            ];

            if (isset($correctedData['amount']) && (float) $correctedData['amount'] > 0) {
                $updates['amount'] = round((float) $correctedData['amount'], 2);
            }
            if (! empty($correctedData['business_date'])) {
                $updates['business_date'] = $correctedData['business_date'];
            }
            if (isset($correctedData['notes'])) {
                $updates['notes'] = $correctedData['notes'];
            }
            if (! empty($correctedData['entry_type_id'])) {
                $updates['entry_type_id'] = (int) $correctedData['entry_type_id'];
            }

            $model->update($updates);

            // Step 4: Recalculate daily balance snapshots
            if ($oldDate) {
                $this->calculator->recalculate($model->shop_id, $oldDate);
            }
            if ($model->business_date && $model->business_date->toDateString() !== $oldDate) {
                $this->calculator->recalculate($model->shop_id, $model->business_date->toDateString());
            }

            return $model->fresh(['entryType', 'shop', 'companyAccount']);
        }, attempts: 3);
    }

    /**
     * Update an unreconciled transaction directly.
     *
     * @param  array{amount?: float, business_date?: string, notes?: string|null, entry_type_id?: int|null, funding_source?: string|null}  $data
     */
    public function updateUnreconciledTransaction(
        ShopLedgerTransaction|int $transaction,
        array $data,
        int $userId
    ): ShopLedgerTransaction {
        return DB::transaction(function () use ($transaction, $data): ShopLedgerTransaction {
            $txId = $transaction instanceof ShopLedgerTransaction ? $transaction->id : $transaction;

            /** @var ShopLedgerTransaction $model */
            $model = ShopLedgerTransaction::query()
                ->whereKey($txId)
                ->lockForUpdate()
                ->firstOrFail();

            $isReconciled = CompanyAccountStatementEntry::query()
                ->where('source_type', ShopLedgerTransaction::class)
                ->where('source_id', $model->id)
                ->where('is_finalized', true)
                ->where('status', 'reconciled')
                ->exists();

            if ($isReconciled) {
                throw new RuntimeException('This transaction is reconciled and must be corrected using the correction workflow.');
            }

            $oldDate = $model->business_date?->toDateString();

            $updates = [];
            if (isset($data['amount']) && (float) $data['amount'] > 0) {
                $updates['amount'] = round((float) $data['amount'], 2);
            }
            if (! empty($data['business_date'])) {
                $updates['business_date'] = $data['business_date'];
            }
            if (isset($data['notes'])) {
                $updates['notes'] = $data['notes'];
            }
            if (! empty($data['entry_type_id'])) {
                $updates['entry_type_id'] = (int) $data['entry_type_id'];
            }
            if (isset($data['funding_source'])) {
                $updates['funding_source'] = $data['funding_source'];
            }

            $model->update($updates);

            // Update unfinalized pending statement if exists
            $statement = CompanyAccountStatementEntry::query()
                ->where('source_type', ShopLedgerTransaction::class)
                ->where('source_id', $model->id)
                ->where('is_finalized', false)
                ->lockForUpdate()
                ->first();

            if ($statement instanceof CompanyAccountStatementEntry) {
                $statement->update([
                    'amount' => round((float) $model->amount, 2),
                    'transaction_date' => $model->business_date?->toDateString() ?: $statement->transaction_date,
                    'value_date' => $model->business_date?->toDateString() ?: $statement->value_date,
                    'notes' => $model->notes ?: $statement->notes,
                ]);
            }

            if ($oldDate) {
                $this->calculator->recalculate($model->shop_id, $oldDate);
            }
            if ($model->business_date && $model->business_date->toDateString() !== $oldDate) {
                $this->calculator->recalculate($model->shop_id, $model->business_date->toDateString());
            }

            return $model->fresh(['entryType', 'shop', 'companyAccount']);
        }, attempts: 3);
    }

    /**
     * Delete / void an unreconciled transaction.
     */
    public function deleteUnreconciledTransaction(ShopLedgerTransaction|int $transaction, int $userId, ?string $reason = null): void
    {
        $txId = $transaction instanceof ShopLedgerTransaction ? $transaction->id : $transaction;

        $isReconciled = CompanyAccountStatementEntry::query()
            ->where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $txId)
            ->where('is_finalized', true)
            ->where('status', 'reconciled')
            ->exists();

        if ($isReconciled) {
            throw new RuntimeException('Reconciled transactions cannot be deleted. Use reverse entry instead.');
        }

        $this->dailyLedgerService->deleteEntry($txId);
    }
}
