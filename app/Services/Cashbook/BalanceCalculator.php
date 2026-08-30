<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Enums\Cashbook\FundingSource;
use App\Enums\Cashbook\LedgerDirection;
use App\Enums\Cashbook\TransactionStatus;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BalanceCalculator
{
    public function __construct(
        private readonly FundingSourceEffectResolver $effectResolver,
        private readonly LedgerRuleResolver $ruleResolver,
    ) {}

    /**
     * Recalculate and persist the snapshot for one shop/day, carrying
     * forward the previous day's closing balances as today's opening.
     *
     * Draft/posted entries are re-derived using the effective rule for
     * their business date. Accepted/approved/reconciled entries preserve
     * their stored canonical snapshot deltas.
     *
     * Uses DB transaction and lockForUpdate for concurrency safety.
     */
    public function recalculate(int $shopId, string $businessDate): ShopDailyLedgerSnapshot
    {
        return DB::transaction(function () use ($shopId, $businessDate) {
            $date = Carbon::parse($businessDate)->toDateString();
            $opening = $this->openingBalances($shopId, $date);

            // Acquire lock on existing snapshot row if present
            ShopDailyLedgerSnapshot::query()
                ->where('shop_id', $shopId)
                ->where('business_date', $date)
                ->lockForUpdate()
                ->first();

            $transactions = ShopLedgerTransaction::query()
                ->where('shop_id', $shopId)
                ->where('business_date', $date)
                ->whereNotIn('status', [TransactionStatus::Void->value, TransactionStatus::Reversed->value, 'void', 'reversed'])
                ->lockForUpdate()
                ->get();

            // Re-derive deltas only for draft/posted unaccepted transactions using the effective rule for the business date.
            // Accepted/approved/closed entries and rule-generated entries preserve their canonical snapshot deltas.
            foreach ($transactions as $tx) {
                if ($tx->generated_by_rule || in_array($tx->status, [TransactionStatus::Approved->value, TransactionStatus::Closed->value, 'approved', 'closed', 'reconciled'], true)) {
                    continue;
                }

                try {
                    $setting = $this->ruleResolver->resolve((int) $shopId, (int) $tx->entry_type_id, $date);
                } catch (\Throwable) {
                    continue;
                }

                $direction = LedgerDirection::tryFrom((string) $tx->direction) ?? LedgerDirection::Expense;
                $source = FundingSource::tryFrom((string) $tx->funding_source) ?? FundingSource::None;
                $amount = (float) $tx->amount;

                $effect = $this->effectResolver->resolve($direction, $source, $amount, $setting);

                // Only write back when values differ to avoid unnecessary UPDATE churn
                $changes = [];
                if ((float) $tx->pl_delta !== $effect->plDelta) {
                    $changes['pl_delta'] = $effect->plDelta;
                }
                if ((float) $tx->settlement_delta !== $effect->settlementDelta) {
                    $changes['settlement_delta'] = $effect->settlementDelta;
                    $changes['settlement_direction'] = $effect->settlementDirection->value;
                }
                if ((float) $tx->petty_delta !== $effect->pettyDelta) {
                    $changes['petty_delta'] = $effect->pettyDelta;
                    $changes['petty_direction'] = $effect->pettyDirection->value;
                }
                if ((float) $tx->company_pending_delta !== $effect->companyPendingDelta) {
                    $changes['company_pending_delta'] = $effect->companyPendingDelta;
                    $changes['company_pending_direction'] = $effect->companyPendingDirection->value;
                }

                if (! empty($changes)) {
                    $tx->update($changes);
                    // Reflect changes on in-memory object so the sums below are correct
                    foreach ($changes as $k => $v) {
                        $tx->$k = $v;
                    }
                }
            }

            $totalSales = (float) $transactions->where('affects_sales', true)->sum('amount');
            $totalIncome = (float) $transactions->where('affects_income', true)->sum('amount');
            $totalExpense = (float) $transactions->where('affects_expense', true)->sum('amount');
            $netPl = (float) $transactions->sum('pl_delta');

            $settlementIncrease = (float) $transactions->where('settlement_delta', '>', 0)->sum('settlement_delta');
            $settlementDecrease = abs((float) $transactions->where('settlement_delta', '<', 0)->sum('settlement_delta'));

            $pettyIn = (float) $transactions->where('petty_delta', '>', 0)->sum('petty_delta');
            $pettyOut = abs((float) $transactions->where('petty_delta', '<', 0)->sum('petty_delta'));

            $companyPendingIn = (float) $transactions->where('company_pending_delta', '>', 0)->sum('company_pending_delta');
            $companyPendingOut = abs((float) $transactions->where('company_pending_delta', '<', 0)->sum('company_pending_delta'));

            return ShopDailyLedgerSnapshot::updateOrCreate(
                ['shop_id' => $shopId, 'business_date' => $date],
                [
                    'total_sales' => $totalSales,
                    'total_income' => $totalIncome,
                    'total_expense' => $totalExpense,
                    'net_pl' => $netPl,

                    'opening_petty' => $opening['petty'],
                    'petty_in' => $pettyIn,
                    'petty_out' => $pettyOut,
                    'closing_petty' => $opening['petty'] + $pettyIn - $pettyOut,

                    'opening_shop_position' => $opening['shop_position'],
                    'settlement_increase' => $settlementIncrease,
                    'settlement_decrease' => $settlementDecrease,
                    'closing_shop_position' => $opening['shop_position'] + $settlementIncrease - $settlementDecrease,

                    'opening_company_pending' => $opening['company_pending'],
                    'company_pending_in' => $companyPendingIn,
                    'company_pending_out' => $companyPendingOut,
                    'closing_company_pending' => $opening['company_pending'] + $companyPendingIn - $companyPendingOut,
                ]
            );
        });
    }

    /**
     * Opening balances = previous business day's closing snapshot for this
     * shop. First day for a shop opens everything at zero.
     */
    private function openingBalances(int $shopId, string $date): array
    {
        $previous = ShopDailyLedgerSnapshot::query()
            ->where('shop_id', $shopId)
            ->where('business_date', '<', $date)
            ->orderByDesc('business_date')
            ->first();

        return [
            'petty' => (float) ($previous?->closing_petty ?? 0),
            'shop_position' => (float) ($previous?->closing_shop_position ?? 0),
            'company_pending' => (float) ($previous?->closing_company_pending ?? 0),
        ];
    }
}
