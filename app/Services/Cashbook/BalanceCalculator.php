<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Enums\Cashbook\TransactionStatus;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BalanceCalculator
{
    /**
     * Recalculate and persist the snapshot for one shop/day, carrying
     * forward the previous day's closing balances as today's opening.
     * Totals are always derived from source transactions, never stored
     * as the sole record. Uses DB transaction and lockForUpdate for concurrency safety.
     */
    public function recalculate(int $shopId, string $businessDate): ShopDailyLedgerSnapshot
    {
        return DB::transaction(function () use ($shopId, $businessDate) {
            $date    = Carbon::parse($businessDate)->toDateString();
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
                ->where('status', '!=', TransactionStatus::Void->value)
                ->lockForUpdate()
                ->get();

            $totalSales   = (float) $transactions->where('affects_sales', true)->sum('amount');
            $totalIncome  = (float) $transactions->where('affects_income', true)->sum('amount');
            $totalExpense = (float) $transactions->where('affects_expense', true)->sum('amount');
            $netPl        = (float) $transactions->sum('pl_delta');

            $settlementIncrease = (float) $transactions->where('settlement_delta', '>', 0)->sum('settlement_delta');
            $settlementDecrease = abs((float) $transactions->where('settlement_delta', '<', 0)->sum('settlement_delta'));

            $pettyIn  = (float) $transactions->where('petty_delta', '>', 0)->sum('petty_delta');
            $pettyOut = abs((float) $transactions->where('petty_delta', '<', 0)->sum('petty_delta'));

            $companyPendingIn  = (float) $transactions->where('company_pending_delta', '>', 0)->sum('company_pending_delta');
            $companyPendingOut = abs((float) $transactions->where('company_pending_delta', '<', 0)->sum('company_pending_delta'));

            return ShopDailyLedgerSnapshot::updateOrCreate(
                ['shop_id' => $shopId, 'business_date' => $date],
                [
                    'total_sales'   => $totalSales,
                    'total_income'  => $totalIncome,
                    'total_expense' => $totalExpense,
                    'net_pl'        => $netPl,

                    'opening_petty'  => $opening['petty'],
                    'petty_in'       => $pettyIn,
                    'petty_out'      => $pettyOut,
                    'closing_petty'  => $opening['petty'] + $pettyIn - $pettyOut,

                    'opening_shop_position'  => $opening['shop_position'],
                    'settlement_increase'    => $settlementIncrease,
                    'settlement_decrease'    => $settlementDecrease,
                    'closing_shop_position'  => $opening['shop_position'] + $settlementIncrease - $settlementDecrease,

                    'opening_company_pending'  => $opening['company_pending'],
                    'company_pending_in'       => $companyPendingIn,
                    'company_pending_out'      => $companyPendingOut,
                    'closing_company_pending'  => $opening['company_pending'] + $companyPendingIn - $companyPendingOut,
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
            'petty'           => (float) ($previous?->closing_petty ?? 0),
            'shop_position'   => (float) ($previous?->closing_shop_position ?? 0),
            'company_pending' => (float) ($previous?->closing_company_pending ?? 0),
        ];
    }
}
