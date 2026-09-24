<?php

declare(strict_types=1);

namespace App\Services\Cashbook\MonthlyReport;

use App\Models\PurchaseProductFilter;
use App\Services\Purchasing\PurchaseReportingService;
use Illuminate\Support\Collection;

final class PurchaseAggregationService
{
    public function __construct(
        private readonly PurchaseReportingService $purchaseReportingService,
    ) {}

    /**
     * @return array{
     *     total_purchases: float,
     *     cash_purchases: float,
     *     credit_purchases: float,
     *     fruits_expense: float,
     *     vegetables_expense: float,
     *     stationery_expense: float,
     *     other_product_expense: float,
     *     category_totals: array<string, float>,
     *     daily_breakdown: array<string, array{
     *         total_expense: float,
     *         fruits_expense: float,
     *         vegetables_expense: float,
     *         stationery_expense: float,
     *         other_product_expense: float,
     *         cash_purchases: float,
     *         credit_purchases: float
     *     }>,
     *     unmapped_products_count: int,
     *     unmapped_expense: float,
     *     items_collection: Collection<int, mixed>
     * }
     */
    public function calculate(string $startDate, string $endDate, array $dates, array $shopProductExpensesByDate = []): array
    {
        // 1. Resolve product memberships for Fruits, Vegetables, Stationery from active PurchaseProductFilters
        $activeFilters = PurchaseProductFilter::query()
            ->active()
            ->whereNotNull('monthly_report_group')
            ->with('filterItems')
            ->get();

        $fruitsProductIds = [];
        $vegProductIds = [];
        $stationeryProductIds = [];

        foreach ($activeFilters as $filter) {
            $productIds = $filter->getProductIds();
            match ($filter->monthly_report_group) {
                'fruits' => $fruitsProductIds = array_unique(array_merge($fruitsProductIds, $productIds)),
                'vegetables' => $vegProductIds = array_unique(array_merge($vegProductIds, $productIds)),
                'stationery' => $stationeryProductIds = array_unique(array_merge($stationeryProductIds, $productIds)),
                default => null,
            };
        }

        // 2. Fetch purchase invoice items via PurchaseReportingService::filteredItems
        $itemsQuery = $this->purchaseReportingService->filteredItems([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $items = $itemsQuery->get();

        $dailyBreakdown = [];
        foreach ($dates as $date) {
            $shopExp = $shopProductExpensesByDate[$date] ?? [];
            $dailyBreakdown[$date] = [
                'total_expense' => round((float) array_sum($shopExp), 2),
                'fruits_expense' => round((float) ($shopExp[ReportHeadingDictionary::FRUITS_EXPENSE] ?? 0.0), 2),
                'vegetables_expense' => round((float) ($shopExp[ReportHeadingDictionary::VEGETABLES_EXPENSE] ?? 0.0), 2),
                'stationery_expense' => round((float) ($shopExp[ReportHeadingDictionary::STATIONERY_EXPENSE] ?? 0.0), 2),
                'other_product_expense' => round((float) ($shopExp[ReportHeadingDictionary::OTHER_PRODUCT_EXPENSE] ?? 0.0), 2),
                'cash_purchases' => 0.0,
                'credit_purchases' => 0.0,
            ];
        }

        $totalFruits = 0.0;
        $totalVeg = 0.0;
        $totalStationery = 0.0;
        $totalOtherProduct = 0.0;
        $totalCashPurchases = 0.0;
        $totalCreditPurchases = 0.0;
        $unmappedProducts = [];
        $unmappedExpense = 0.0;

        foreach ($items as $item) {
            $productId = (int) $item->product_id;
            $netAmount = round((float) $item->item_net, 2);
            $itemDate = $item->business_date ? substr((string) $item->business_date, 0, 10) : $startDate;
            $isCredit = ($item->payment_class === 'credit');

            if ($isCredit) {
                $totalCreditPurchases = round($totalCreditPurchases + $netAmount, 2);
                if (isset($dailyBreakdown[$itemDate])) {
                    $dailyBreakdown[$itemDate]['credit_purchases'] = round($dailyBreakdown[$itemDate]['credit_purchases'] + $netAmount, 2);
                }
            } else {
                $totalCashPurchases = round($totalCashPurchases + $netAmount, 2);
                if (isset($dailyBreakdown[$itemDate])) {
                    $dailyBreakdown[$itemDate]['cash_purchases'] = round($dailyBreakdown[$itemDate]['cash_purchases'] + $netAmount, 2);
                }
            }

            // Categorize into Fruits, Vegetables, Stationery, or Other Product Expense
            if (in_array($productId, $fruitsProductIds, true)) {
                $categoryGroup = 'fruits';
                $totalFruits = round($totalFruits + $netAmount, 2);
                if (isset($dailyBreakdown[$itemDate])) {
                    $dailyBreakdown[$itemDate]['fruits_expense'] = round($dailyBreakdown[$itemDate]['fruits_expense'] + $netAmount, 2);
                }
            } elseif (in_array($productId, $vegProductIds, true)) {
                $categoryGroup = 'vegetables';
                $totalVeg = round($totalVeg + $netAmount, 2);
                if (isset($dailyBreakdown[$itemDate])) {
                    $dailyBreakdown[$itemDate]['vegetables_expense'] = round($dailyBreakdown[$itemDate]['vegetables_expense'] + $netAmount, 2);
                }
            } elseif (in_array($productId, $stationeryProductIds, true)) {
                $categoryGroup = 'stationery';
                $totalStationery = round($totalStationery + $netAmount, 2);
                if (isset($dailyBreakdown[$itemDate])) {
                    $dailyBreakdown[$itemDate]['stationery_expense'] = round($dailyBreakdown[$itemDate]['stationery_expense'] + $netAmount, 2);
                }
            } else {
                $categoryGroup = 'other';
                $totalOtherProduct = round($totalOtherProduct + $netAmount, 2);
                $unmappedExpense = round($unmappedExpense + $netAmount, 2);
                $unmappedProducts[$productId] = $item->product_name;
                if (isset($dailyBreakdown[$itemDate])) {
                    $dailyBreakdown[$itemDate]['other_product_expense'] = round($dailyBreakdown[$itemDate]['other_product_expense'] + $netAmount, 2);
                }
            }

            $item->report_group = $categoryGroup;

            if (isset($dailyBreakdown[$itemDate])) {
                $dailyBreakdown[$itemDate]['total_expense'] = round($dailyBreakdown[$itemDate]['total_expense'] + $netAmount, 2);
            }
        }

        // Add shop standalone product expenses into totals
        foreach ($shopProductExpensesByDate as $date => $shopExp) {
            $totalFruits = round($totalFruits + (float) ($shopExp[ReportHeadingDictionary::FRUITS_EXPENSE] ?? 0.0), 2);
            $totalVeg = round($totalVeg + (float) ($shopExp[ReportHeadingDictionary::VEGETABLES_EXPENSE] ?? 0.0), 2);
            $totalStationery = round($totalStationery + (float) ($shopExp[ReportHeadingDictionary::STATIONERY_EXPENSE] ?? 0.0), 2);
            $totalOtherProduct = round($totalOtherProduct + (float) ($shopExp[ReportHeadingDictionary::OTHER_PRODUCT_EXPENSE] ?? 0.0), 2);
        }

        $totalProductExpenses = round($totalFruits + $totalVeg + $totalStationery + $totalOtherProduct, 2);

        return [
            'total_purchases' => $totalProductExpenses,
            'cash_purchases' => $totalCashPurchases,
            'credit_purchases' => $totalCreditPurchases,
            'fruits_expense' => $totalFruits,
            'vegetables_expense' => $totalVeg,
            'stationery_expense' => $totalStationery,
            'other_product_expense' => $totalOtherProduct,
            'category_totals' => [
                ReportHeadingDictionary::FRUITS_EXPENSE => $totalFruits,
                ReportHeadingDictionary::VEGETABLES_EXPENSE => $totalVeg,
                ReportHeadingDictionary::STATIONERY_EXPENSE => $totalStationery,
                ReportHeadingDictionary::OTHER_PRODUCT_EXPENSE => $totalOtherProduct,
            ],
            'daily_breakdown' => $dailyBreakdown,
            'unmapped_products_count' => count($unmappedProducts),
            'unmapped_expense' => $unmappedExpense,
            'items_collection' => $items,
        ];
    }
}
