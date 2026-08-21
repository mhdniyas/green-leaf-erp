<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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

        $transaction = $this->generator->void($transaction, $voidedBy, $reason);
        $snapshot = $this->calculator->recalculate($transaction->shop_id, $transaction->business_date->toDateString());

        return ['transaction' => $transaction, 'snapshot' => $snapshot];
    }

    public function deleteEntry(int $transactionId): array
    {
        $transaction = ShopLedgerTransaction::findOrFail($transactionId);
        $this->assertDayOpen($transaction->shop_id, $transaction->business_date->toDateString());

        if ($transaction->generated_by_rule && $transaction->parent_transaction_id) {
            throw new RuntimeException('Generated child entries cannot be deleted directly; delete the parent transaction instead.');
        }

        $shopId = $transaction->shop_id;
        $date = $transaction->business_date->toDateString();

        DB::transaction(function () use ($transaction) {
            $transaction->children()->delete();
            $transaction->delete();
        });

        $snapshot = $this->calculator->recalculate($shopId, $date);

        return ['snapshot' => $snapshot];
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
