<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Enums\Cashbook\TransactionStatus;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * UI → DailyLedgerService → … → Snapshot / Reports.
 *
 * Controllers should only ever call this class. Never call
 * TransactionGenerator, FundingSourceEffectResolver, or the models'
 * balance logic directly from a controller.
 */
class DailyLedgerService
{
    public function __construct(
        private readonly TransactionGenerator $generator,
        private readonly BalanceCalculator $calculator,
        private readonly LedgerRuleResolver $ruleResolver,
        private readonly BankSettlementExpectedAmountService $expectedAmountService = new BankSettlementExpectedAmountService,
    ) {}

    public function recordEntry(array $input): array
    {
        $this->assertDayOpen($input['shop_id'], $input['business_date']);

        $transaction = $this->generator->record($input);
        $snapshot = $this->calculator->recalculate($input['shop_id'], $input['business_date']);

        return ['transaction' => $transaction, 'snapshot' => $snapshot];
    }

    public function updateEntry(int $transactionId, float $newAmount, ?string $fundingSource = null, ?string $notes = null, ?int $updatedBy = null): array
    {
        $transaction = ShopLedgerTransaction::findOrFail($transactionId);
        $this->assertDayOpen($transaction->shop_id, $transaction->business_date->toDateString());

        if ($transaction->isReconciled()) {
            throw new RuntimeException('Reconciled transactions cannot be modified.');
        }

        $transaction = $this->generator->updateEntry($transaction, $newAmount, $fundingSource, $notes, $updatedBy);
        $snapshot = $this->calculator->recalculate($transaction->shop_id, $transaction->business_date->toDateString());

        return ['transaction' => $transaction, 'snapshot' => $snapshot];
    }

    public function updateEntryAmount(int $transactionId, float $newAmount, ?int $updatedBy = null): array
    {
        return $this->updateEntry($transactionId, $newAmount, null, null, $updatedBy);
    }

    public function voidEntry(int $transactionId, int $voidedBy, string $reason): array
    {
        $transaction = ShopLedgerTransaction::findOrFail($transactionId);
        $this->assertDayOpen($transaction->shop_id, $transaction->business_date->toDateString());

        if ($transaction->isReconciled()) {
            throw new RuntimeException('Reconciled transactions cannot be voided.');
        }

        $transaction = $this->generator->void($transaction, $voidedBy, $reason);
        $snapshot = $this->calculator->recalculate($transaction->shop_id, $transaction->business_date->toDateString());

        return ['transaction' => $transaction, 'snapshot' => $snapshot];
    }

    public function deleteEntry(int $transactionId): array
    {
        $transaction = ShopLedgerTransaction::findOrFail($transactionId);
        $this->assertDayOpen($transaction->shop_id, $transaction->business_date->toDateString());

        if ($transaction->isReconciled()) {
            throw new RuntimeException('Reconciled transactions cannot be deleted.');
        }

        if ($transaction->generated_by_rule && $transaction->parent_transaction_id) {
            throw new RuntimeException('Generated child entries cannot be deleted directly; delete the parent transaction instead.');
        }

        $shopId = $transaction->shop_id;
        $date = $transaction->business_date->toDateString();

        DB::transaction(function () use ($transaction) {
            CompanyAccountStatementEntry::query()
                ->where('source_type', ShopLedgerTransaction::class)
                ->where('source_id', $transaction->id)
                ->where('is_finalized', false)
                ->delete();

            $transaction->children()->delete();
            $transaction->delete();
        });

        $snapshot = $this->calculator->recalculate($shopId, $date);

        return ['snapshot' => $snapshot];
    }

    public function approveEntry(ShopLedgerTransaction|int $transaction, int $userId): ShopLedgerTransaction
    {
        return DB::transaction(function () use ($transaction, $userId): ShopLedgerTransaction {
            $model = $transaction instanceof ShopLedgerTransaction
                ? $transaction
                : ShopLedgerTransaction::query()->findOrFail($transaction);

            $model = ShopLedgerTransaction::query()
                ->whereKey($model->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($model->status === TransactionStatus::Void->value) {
                throw new RuntimeException('Voided transactions cannot be approved.');
            }

            // Resolve destination account from transaction or shop setting
            $companyAccountId = $model->company_account_id;
            if (! $companyAccountId) {
                try {
                    $setting = $this->ruleResolver->resolve(
                        (int) $model->shop_id,
                        (int) $model->entry_type_id,
                        $model->business_date->toDateString()
                    );
                    $companyAccountId = $setting->company_account_id;
                } catch (Throwable) {
                    $companyAccountId = null;
                }
            }

            $companyAccount = null;
            if ($companyAccountId) {
                $companyAccount = CompanyAccount::query()
                    ->whereKey($companyAccountId)
                    ->where('enabled', true)
                    ->first();
            }

            $model->update([
                'status' => TransactionStatus::Approved->value,
                'approved_by' => $userId,
                'company_account_id' => $companyAccount?->id ?? $model->company_account_id,
            ]);

            // If a valid enabled company account is configured for this transaction, ensure exactly ONE pending statement entry exists
            if ($companyAccount instanceof CompanyAccount) {
                $statement = CompanyAccountStatementEntry::query()
                    ->where('source_type', ShopLedgerTransaction::class)
                    ->where('source_id', $model->id)
                    ->lockForUpdate()
                    ->first();

                $direction = $model->direction === 'income' ? 'in' : 'out';
                $narration = ($model->entryType?->name ?? 'Shop transaction').' from '.($model->shop?->name ?? 'Shop #'.$model->shop_id);

                $resolvedExpected = $this->expectedAmountService->resolve(
                    (int) $model->shop_id,
                    $model->business_date->toDateString(),
                    (int) $model->entry_type_id,
                    (float) $model->amount
                );
                $statementAmount = (float) $resolvedExpected['expected_amount'];

                if (! $statement instanceof CompanyAccountStatementEntry) {
                    CompanyAccountStatementEntry::query()->create([
                        'company_account_id' => $companyAccount->id,
                        'transaction_date' => $model->business_date->toDateString(),
                        'value_date' => $model->business_date->toDateString(),
                        'direction' => $direction,
                        'amount' => $statementAmount,
                        'reference' => $model->reference_id ?: 'SHOP-TX-'.$model->id,
                        'narration' => $narration,
                        'source' => 'shop_collection',
                        'source_type' => ShopLedgerTransaction::class,
                        'source_id' => $model->id,
                        'status' => 'unmatched',
                        'is_finalized' => false,
                        'matched_amount' => 0,
                        'notes' => $model->notes ?: 'Pending verification from shop cashbook',
                        'imported_by' => $userId,
                    ]);
                } elseif (! $statement->is_finalized) {
                    $statement->update([
                        'company_account_id' => $companyAccount->id,
                        'transaction_date' => $model->business_date->toDateString(),
                        'value_date' => $model->business_date->toDateString(),
                        'direction' => $direction,
                        'amount' => $statementAmount,
                        'reference' => $model->reference_id ?: 'SHOP-TX-'.$model->id,
                        'narration' => $narration,
                        'status' => 'unmatched',
                        'matched_amount' => 0,
                        'notes' => $model->notes ?: 'Pending verification from shop cashbook',
                    ]);
                }
            }

            return $model->fresh(['entryType', 'shop', 'companyAccount']);
        }, attempts: 3);
    }

    public function approveDay(int $shopId, string $businessDate, int $userId, bool $tillDate = false): int
    {
        return DB::transaction(function () use ($shopId, $businessDate, $userId, $tillDate): int {
            $query = ShopLedgerTransaction::query()
                ->where('shop_id', $shopId)
                ->where('status', '!=', TransactionStatus::Approved->value)
                ->where('status', '!=', TransactionStatus::Void->value);

            if ($tillDate) {
                $query->whereDate('business_date', '<=', $businessDate);
            } else {
                $query->whereDate('business_date', $businessDate);
            }

            $transactions = $query->lockForUpdate()->get();
            $count = 0;
            foreach ($transactions as $transaction) {
                $this->approveEntry($transaction, $userId);
                $count++;
            }

            return $count;
        }, attempts: 3);
    }

    public function dailySummary(int $shopId, string $businessDate): ShopDailyLedgerSnapshot
    {
        return $this->calculator->recalculate($shopId, $businessDate);
    }

    /**
     * Once closed, an operator can't post directly. Admin must reopen the day,
     * or post a dated adjustment referencing the closed date.
     */
    public function closeDay(int $shopId, string $businessDate, int $closedBy): ShopDailyLedgerSnapshot
    {
        $snapshot = $this->calculator->recalculate($shopId, $businessDate);
        $snapshot->update([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by' => $closedBy,
        ]);

        return $snapshot->fresh();
    }

    public function reopenDay(int $shopId, string $businessDate): ShopDailyLedgerSnapshot
    {
        $snapshot = ShopDailyLedgerSnapshot::where('shop_id', $shopId)
            ->where('business_date', $businessDate)
            ->firstOrFail();

        $snapshot->update(['status' => 'reopened', 'closed_at' => null, 'closed_by' => null]);

        return $snapshot->fresh();
    }

    public function assertDayOpen(int $shopId, string $businessDate): void
    {
        $snapshot = ShopDailyLedgerSnapshot::where('shop_id', $shopId)
            ->where('business_date', $businessDate)
            ->first();

        if ($snapshot && $snapshot->status === 'closed') {
            throw new RuntimeException(
                "Business date {$businessDate} is closed for shop {$shopId}. Reopen the day or post a dated adjustment referencing it."
            );
        }
    }
}
