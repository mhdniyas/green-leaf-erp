<?php

declare(strict_types=1);

namespace App\Services\Cashbook\MonthlyReport;

final class MonthlyReportReconciliationService
{
    public const TOLERANCE = 0.01;

    /**
     * @param  array<string, mixed>  $overview
     * @param  array<string, mixed>  $saleSplit
     * @param  array<string, mixed>  $expenseReport
     * @param  array<string, mixed>  $salesData
     * @param  array<string, mixed>  $purchasesData
     * @param  array<string, mixed>  $operatingExpensesData
     * @param  array<string, mixed>  $clientShopData
     * @return array{
     *     status: string,
     *     status_code: string,
     *     status_class: string,
     *     differences: array<string, float>,
     *     is_reconciled: bool,
     *     warnings: array<int, string>
     * }
     */
    public function reconcile(
        array $overview,
        array $saleSplit,
        array $expenseReport,
        array $salesData,
        array $purchasesData,
        array $operatingExpensesData,
        array $clientShopData
    ): array {
        $overviewTotalSales = (float) ($overview['total_sales'] ?? 0.0);
        $overviewExpenses = (float) ($overview['total_expenses'] ?? 0.0);

        // Sum daily sales and expenses from overview daily rows
        $sumDailySales = 0.0;
        $sumDailyExpenses = 0.0;
        foreach ($overview['daily_rows'] ?? [] as $row) {
            $sumDailySales = round($sumDailySales + (float) $row['total_sales'], 2);
            $sumDailyExpenses = round($sumDailyExpenses + (float) $row['total_expenses'], 2);
        }

        $saleSplitSales = (float) ($saleSplit['total_sales'] ?? 0.0);
        $saleSplitExpenses = (float) ($saleSplit['total_expenses'] ?? 0.0);
        $saleSplitOtherExpenses = (float) ($saleSplit['other_expenses'] ?? 0.0);

        $expenseReportTotal = (float) ($expenseReport['total_operating_expenses'] ?? 0.0);

        $clientSales = (float) ($salesData['client_sales'] ?? 0.0);
        $sumClientShopsSales = (float) ($clientShopData['total_client_sales'] ?? 0.0);

        $allOtherSales = (float) ($salesData['all_other_sales'] ?? 0.0);
        $sumOtherSources = round(
            (float) ($salesData['direct_gl_bills_sales'] ?? 0.0)
            + (float) ($salesData['direct_company_sales'] ?? 0.0)
            + (float) ($salesData['warehouse_sales'] ?? 0.0),
            2
        );

        $differences = [
            'overview_sales_difference' => round(abs($overviewTotalSales - $sumDailySales), 2),
            'overview_expense_difference' => round(abs($overviewExpenses - $sumDailyExpenses), 2),
            'sale_split_difference' => round(abs($overviewTotalSales - $saleSplitSales), 2),
            'expense_split_difference' => round(abs($overviewExpenses - $saleSplitExpenses), 2),
            'operating_expense_difference' => round(abs($saleSplitOtherExpenses - $expenseReportTotal), 2),
            'client_difference' => round(abs($clientSales - $sumClientShopsSales), 2),
            'other_sales_difference' => round(abs($allOtherSales - $sumOtherSources), 2),
        ];

        $warnings = [];
        $isReconciled = true;

        foreach ($differences as $diffKey => $diffValue) {
            if ($diffValue > self::TOLERANCE) {
                $isReconciled = false;
                $readableKey = str_replace('_', ' ', $diffKey);
                $warnings[] = "Reconciliation variance detected: {$readableKey} is ₹{$diffValue}.";
            }
        }

        if (($salesData['unmapped_sales'] ?? 0.0) > 0.0) {
            $isReconciled = false;
            $warnings[] = 'Unmapped product sales found: ₹'.number_format((float) $salesData['unmapped_sales'], 2).'. Check product group assignments.';
        }

        if (($purchasesData['unmapped_expense'] ?? 0.0) > 0.0) {
            $isReconciled = false;
            $warnings[] = 'Unmapped product purchases found: ₹'.number_format((float) $purchasesData['unmapped_expense'], 2).'. Check product group assignments.';
        }

        $statusCode = $isReconciled ? 'reconciled' : 'needs_review';
        $statusLabel = $isReconciled ? 'Reconciled' : 'Needs Review';
        $statusClass = $isReconciled
            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
            : 'bg-amber-50 text-amber-700 border-amber-200';

        return [
            'status' => $statusLabel,
            'status_code' => $statusCode,
            'status_class' => $statusClass,
            'difference' => round(array_sum($differences), 2),
            'overview_total_sales' => $overviewTotalSales,
            'split_total_sales' => $saleSplitSales,
            'differences' => $differences,
            'is_reconciled' => $isReconciled,
            'warnings' => $warnings,
        ];
    }
}
