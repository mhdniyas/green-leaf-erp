<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\ShopInvoice;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Isolated Profit Intelligence Service for the Analytics (Target) page.
 *
 * Uses the purpose-built columns `affects_income`, `affects_expense`,
 * `affects_pl`, and `pl_delta` from shop_ledger_transactions — never
 * recomputes totals with ad-hoc direction/category string matching.
 *
 * Does NOT modify Hub, Detail, or Charts page calculations.
 * If those pages later need fixing, that is a separate cleanup task.
 *
 * Open decisions resolved for this build:
 *  1. Status: only posted/approved/closed count (drafts/submitted excluded from profit banner).
 *  2. Minimum sample-days floor: 4 occurrences before awarding Best/Risk/Peak labels.
 *  3. Health badge cutoffs: ≥90% = Optimal, 75-90% = Needs Attention, <75% = Critical.
 *  4. Baseline ratio: median of all 7 weekday ratios (stable, no iterative recomputation).
 */
final class ShopProfitIntelligenceService
{
    /** Statuses counted as confirmed / posted entries for the profit banner. */
    private const CONFIRMED_STATUSES = ['posted', 'approved', 'closed'];

    /** Minimum number of occurrence days a weekday must have to earn a label. */
    private const MIN_SAMPLE_DAYS = 4;

    /** Health badge thresholds. */
    private const HEALTH_OPTIMAL = 90.0;

    private const HEALTH_ATTENTION = 75.0;

    /**
     * Run the full profit intelligence analysis for a single shop over the last 30 days.
     *
     * @return array{
     *     captured_profit: float,
     *     potential_profit: float,
     *     captured_pct: float,
     *     total_leakage: float,
     *     health_badge: string,
     *     health_tone: string,
     *     period_sales: float,
     *     period_expense: float,
     *     period_net: float,
     *     weekday_analysis: array,
     *     best_profit_day: array|null,
     *     risk_day: array|null,
     *     high_sales_day: array|null,
     *     leak_warnings: array,
     *     pending_days_count: int,
     *     pending_dates: array<string>,
     *     excluded_gl_bill_total: float,
     *     has_data: bool,
     * }
     */
    public function analyse(int $shopId, ?string $startDate = null, ?string $endDate = null, int $minSampleDays = self::MIN_SAMPLE_DAYS): array
    {
        $historicalStart = $startDate ?? today()->subDays(30)->toDateString();
        $historicalEnd = $endDate ?? today()->toDateString();

        $transactions = ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$historicalStart, $historicalEnd])
            ->whereIn('status', self::CONFIRMED_STATUSES)
            ->get(['business_date', 'affects_income', 'affects_expense', 'affects_pl',
                'pl_delta', 'amount', 'reference_type', 'entry_type_id']);

        if ($transactions->isEmpty()) {
            return $this->emptyResult();
        }

        // --- Filter out days that ONLY have GL Bills (pending shop owner sales & expenses) ---
        $transactionsByDate = $transactions->groupBy(
            fn ($tx) => Carbon::parse($tx->business_date)->toDateString()
        );

        $pendingGlOnlyDates = [];
        $excludedGlBillTotal = 0.0;
        $activeTransactions = collect();

        foreach ($transactionsByDate as $dateStr => $dayTxs) {
            $hasNonGlBill = $dayTxs->contains(fn ($tx) => ! $this->isGlBillTransaction($tx));
            if (! $hasNonGlBill) {
                $pendingGlOnlyDates[] = $dateStr;
                $excludedGlBillTotal += (float) $dayTxs->sum('amount');
            } else {
                $activeTransactions = $activeTransactions->concat($dayTxs);
            }
        }

        if ($activeTransactions->isEmpty()) {
            return $this->emptyResult($pendingGlOnlyDates, $excludedGlBillTotal);
        }

        // --- Period-level totals (uses purpose-built columns on active reported days) ---
        $periodSales = (float) $activeTransactions->where('affects_income', true)->sum('amount');
        $periodExpense = (float) $activeTransactions->where('affects_expense', true)->sum('amount');
        $periodNet = (float) $activeTransactions->where('affects_pl', true)->sum('pl_delta');

        // --- Weekday analysis (7 rows) ---
        $weekdayAnalysis = $this->buildWeekdayAnalysis($activeTransactions);

        // --- Leakage calculation ---
        $leakageResult = $this->calculateLeakage($weekdayAnalysis);

        $capturedProfit = max(0.0, $periodNet); // negative net = 0 captured
        $totalLeakage = $leakageResult['total_leakage'];
        $potentialProfit = $capturedProfit + $totalLeakage;
        $capturedPct = $potentialProfit > 0
            ? round(($capturedProfit / $potentialProfit) * 100, 1)
            : ($capturedProfit > 0 ? 100.0 : 0.0);

        [$healthBadge, $healthTone] = $this->healthBadge($capturedPct);

        // --- Labelled days (≥ minSampleDays gate) ---
        $eligible = collect($weekdayAnalysis)->filter(
            fn ($row) => $row['sample_days'] >= $minSampleDays
        );

        $bestProfitDay = $eligible->sortByDesc('avg_net')->first();
        $highSalesDay = $eligible->sortByDesc('avg_sales')->first();
        $riskDay = collect($leakageResult['flagged_days'])->sortByDesc('excess_ratio')->first();

        return [
            'captured_profit' => round($capturedProfit, 2),
            'potential_profit' => round($potentialProfit, 2),
            'captured_pct' => $capturedPct,
            'total_leakage' => round($totalLeakage, 2),
            'health_badge' => $healthBadge,
            'health_tone' => $healthTone,
            'period_sales' => round($periodSales, 2),
            'period_expense' => round($periodExpense, 2),
            'period_net' => round($periodNet, 2),
            'weekday_analysis' => $weekdayAnalysis,
            'best_profit_day' => $bestProfitDay,
            'risk_day' => $riskDay,
            'high_sales_day' => $highSalesDay,
            'leak_warnings' => $leakageResult['flagged_days'],
            'pending_days_count' => count($pendingGlOnlyDates),
            'pending_dates' => $pendingGlOnlyDates,
            'excluded_gl_bill_total' => round($excludedGlBillTotal, 2),
            'has_data' => true,
        ];
    }

    /**
     * Determine if a transaction is a system-synced GL bill.
     */
    private function isGlBillTransaction(ShopLedgerTransaction $tx): bool
    {
        return $tx->reference_type === 'App\Models\ShopInvoice'
            || $tx->reference_type === ShopInvoice::class;
    }

    /**
     * Build per-weekday aggregates.
     * Uses affects_income / affects_expense / pl_delta — no direction string matching.
     * GL Bill proxy: reference_type points to ShopInvoice (same logic as existing code).
     */
    private function buildWeekdayAnalysis(Collection $transactions): array
    {
        $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $analysis = [];

        foreach ($dayNames as $isoDay => $dayName) {
            $isoIndex = $isoDay + 1; // Carbon dayOfWeekIso: Mon=1 ... Sun=7
            $dayTx = $transactions->filter(
                fn ($tx) => Carbon::parse($tx->business_date)->dayOfWeekIso === $isoIndex
            );

            $sampleDays = max(1, $dayTx->pluck('business_date')->unique()->count());

            $totalSales = (float) $dayTx->where('affects_income', true)->sum('amount');
            $totalExpense = (float) $dayTx->where('affects_expense', true)->sum('amount');
            $totalNet = (float) $dayTx->where('affects_pl', true)->sum('pl_delta');

            $totalGlBills = (float) $dayTx->filter(
                fn ($t) => $t->reference_type === 'App\Models\ShopInvoice'
                    || $t->reference_type === ShopInvoice::class
            )->sum('amount');

            $avgSales = round($totalSales / $sampleDays, 2);
            $avgExpense = round($totalExpense / $sampleDays, 2);
            $avgNet = round($totalNet / $sampleDays, 2);
            $avgGlBills = round($totalGlBills / $sampleDays, 2);

            $purchaseRatio = $avgSales > 0
                ? round(($avgGlBills / $avgSales) * 100, 1)
                : 0.0;

            $marginPct = $avgSales > 0
                ? round(($avgNet / $avgSales) * 100, 1)
                : 0.0;

            $analysis[$dayName] = [
                'day' => $dayName,
                'avg_sales' => $avgSales,
                'avg_expense' => $avgExpense,
                'avg_gl_bills' => $avgGlBills,
                'avg_net' => $avgNet,
                'purchase_ratio' => $purchaseRatio,
                'margin_pct' => $marginPct,
                'sample_days' => $sampleDays,
                'has_data' => $dayTx->isNotEmpty(),
            ];
        }

        return $analysis;
    }

    /**
     * Calculate leakage using median-baseline approach.
     *
     * Formula (per spec Section 4.1):
     *   Baseline ratio = median of 7 weekday purchase_ratios.
     *   For each day where purchase_ratio > baseline:
     *     excess_ratio       = purchase_ratio - baseline
     *     daily_leakage      = excess_ratio% × avg_sales
     *     monthly_leakage    = daily_leakage × sample_days
     *   Total leakage = sum of monthly_leakage for flagged days.
     *
     * @return array{total_leakage: float, baseline_ratio: float, flagged_days: array}
     */
    private function calculateLeakage(array $weekdayAnalysis): array
    {
        $ratios = array_column($weekdayAnalysis, 'purchase_ratio');
        sort($ratios);
        $mid = (int) floor(count($ratios) / 2);
        $median = count($ratios) % 2 !== 0
            ? (float) $ratios[$mid]
            : ((float) ($ratios[$mid - 1] + $ratios[$mid]) / 2.0);

        $flaggedDays = [];
        $totalLeakage = 0.0;

        foreach ($weekdayAnalysis as $dayName => $row) {
            if ($row['purchase_ratio'] <= $median || $row['avg_sales'] <= 0) {
                continue;
            }

            $excessRatio = round($row['purchase_ratio'] - $median, 1);
            $dailyLeakage = round(($excessRatio / 100.0) * $row['avg_sales'], 2);
            $monthlyLeakage = round($dailyLeakage * $row['sample_days'], 2);
            $totalLeakage += $monthlyLeakage;

            // Suggested cut percentage to bring ratio back to baseline
            $suggestedCut = $row['purchase_ratio'] > 0
                ? round(($excessRatio / $row['purchase_ratio']) * 100, 0)
                : 0;

            $flaggedDays[] = [
                'day' => $dayName,
                'purchase_ratio' => $row['purchase_ratio'],
                'baseline_ratio' => round($median, 1),
                'excess_ratio' => $excessRatio,
                'avg_sales' => $row['avg_sales'],
                'daily_leakage' => $dailyLeakage,
                'monthly_leakage' => $monthlyLeakage,
                'sample_days' => $row['sample_days'],
                'suggested_cut_pct' => (int) $suggestedCut,
                'severity' => $excessRatio > 25 ? 'danger' : 'warning',
            ];
        }

        return [
            'total_leakage' => round($totalLeakage, 2),
            'baseline_ratio' => round($median, 1),
            'flagged_days' => $flaggedDays,
        ];
    }

    /**
     * Return the health badge label and Tailwind tone based on captured_pct.
     *
     * @return array{0: string, 1: string} [badge_label, tone]
     */
    private function healthBadge(float $capturedPct): array
    {
        if ($capturedPct >= self::HEALTH_OPTIMAL) {
            return ['Optimal', 'emerald'];
        }

        if ($capturedPct >= self::HEALTH_ATTENTION) {
            return ['Needs Attention', 'amber'];
        }

        return ['Critical', 'rose'];
    }

    /** Returned when the shop has no confirmed non-GL-bill transactions in the 30-day window. */
    private function emptyResult(array $pendingDates = [], float $excludedGlBillTotal = 0.0): array
    {
        return [
            'captured_profit' => 0.0,
            'potential_profit' => 0.0,
            'captured_pct' => 0.0,
            'total_leakage' => 0.0,
            'health_badge' => 'No Data',
            'health_tone' => 'slate',
            'period_sales' => 0.0,
            'period_expense' => 0.0,
            'period_net' => 0.0,
            'weekday_analysis' => [],
            'best_profit_day' => null,
            'risk_day' => null,
            'high_sales_day' => null,
            'leak_warnings' => [],
            'pending_days_count' => count($pendingDates),
            'pending_dates' => $pendingDates,
            'excluded_gl_bill_total' => round($excludedGlBillTotal, 2),
            'has_data' => false,
        ];
    }
}
