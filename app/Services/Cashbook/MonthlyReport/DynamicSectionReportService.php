<?php

declare(strict_types=1);

namespace App\Services\Cashbook\MonthlyReport;

use App\Models\DirectCompanySale;
use App\Models\ShopInvoice;
use App\Models\WarehouseSale;
use App\Services\Purchasing\PurchaseReportingService;
use Carbon\Carbon;

final class DynamicSectionReportService
{
    public function __construct(
        private readonly ReportPeriodResolver $periodResolver,
        private readonly DynamicSectionReportResolver $sectionResolver,
        private readonly ShopReportBridge $shopReportBridge,
        private readonly PurchaseReportingService $purchaseReportingService,
        private readonly OperatingExpenseAggregationService $operatingExpenseAggregationService,
        private readonly FinalReportSettingsService $settingsService,
    ) {}

    /**
     * Build the canonical Dynamic Section Report dataset.
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function buildReport(array $inputs, ?string $sectionKey = null): array
    {
        $period = $this->periodResolver->resolve($inputs);
        $startDate = $period['start_date'];
        $endDate = $period['end_date'];
        $dates = $period['dates'];

        // 1. Resolve all active dynamic sections
        $allSections = $this->sectionResolver->resolveActiveSections();

        // 2. Query bounded data sources once for the entire period
        $clientShopData = $this->shopReportBridge->calculateClientShopsData($startDate, $endDate, $dates);

        // Shop Invoices (for non-client direct shop sales apportionment)
        $shopInvoices = ShopInvoice::query()
            ->with(['shop', 'items.product'])
            ->where('status', '!=', 'cancelled')
            ->whereBetween('business_date', [$startDate, $endDate])
            ->orderBy('business_date')
            ->orderBy('id')
            ->get();

        // Direct Company Sales
        $directCompanySales = DirectCompanySale::query()
            ->with(['items.product'])
            ->where('sale_status', 'confirmed')
            ->whereBetween('business_date', [$startDate, $endDate])
            ->orderBy('business_date')
            ->orderBy('id')
            ->get();

        // Warehouse Sales
        $warehouseSales = WarehouseSale::query()
            ->with(['items.product'])
            ->where('status', 'confirmed')
            ->whereBetween('business_date', [$startDate, $endDate])
            ->orderBy('business_date')
            ->orderBy('id')
            ->get();

        // Purchaser Cart & Purchase Invoice Items (canonical purchase reporting)
        $purchaseItems = $this->purchaseReportingService->filteredItems([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ])->get();

        // Operating Expenses (deduplicated across shop cashbook, procurement, other expenses, company entries)
        $operatingExpensesData = $this->operatingExpenseAggregationService->calculate(
            $startDate,
            $endDate,
            $dates,
            [],
            $clientShopData['transactions']
        );

        // 3. Prepare product to section lookup map for fast O(1) matching
        $productIdToSectionKeys = [];
        foreach ($allSections as $secKey => $secDef) {
            if ($secDef->isTrading()) {
                foreach ($secDef->productIds as $pId) {
                    $productIdToSectionKeys[(int) $pId][] = $secKey;
                }
            }
        }

        // 4. Initialize daily metrics per section
        $sectionDailyData = [];
        foreach ($allSections as $secKey => $secDef) {
            $sectionDailyData[$secKey] = [];
            foreach ($dates as $d) {
                if ($secDef->isTrading()) {
                    $sectionDailyData[$secKey][$d] = [
                        'date' => $d,
                        'formatted_date' => Carbon::parse($d)->format('d M Y, D'),
                        'day_name' => Carbon::parse($d)->format('D'),
                        'sale' => 0.0,
                        'purchase' => 0.0,
                        'other_expense' => 0.0,
                        'balance' => 0.0,
                    ];
                } else {
                    $sectionDailyData[$secKey][$d] = [
                        'date' => $d,
                        'formatted_date' => Carbon::parse($d)->format('d M Y, D'),
                        'day_name' => Carbon::parse($d)->format('D'),
                        'expense' => 0.0,
                    ];
                }
            }
        }

        // 5. Aggregate Sales into Trading Sections
        // 5a. All Active Shop Invoices (apportioned by product line item)
        foreach ($shopInvoices as $inv) {
            $invTotal = round((float) $inv->final_total, 2);
            $invDate = $inv->business_date ? Carbon::parse($inv->business_date)->format('Y-m-d') : $startDate;
            if (! isset($dates[$invDate]) && ! in_array($invDate, $dates, true)) {
                continue;
            }

            $items = $inv->items;
            $itemCount = $items->count();
            if ($itemCount === 0 || $invTotal == 0.0) {
                continue;
            }

            $grossSum = round((float) $items->sum(fn ($it) => (float) ($it->final_line_total ?: $it->line_subtotal ?: 0)), 2);
            $allocations = [];
            $allocatedTotal = 0.0;

            foreach ($items as $idx => $it) {
                $rawLine = (float) ($it->final_line_total ?: $it->line_subtotal ?: 0);
                $allocatedLine = ($grossSum > 0)
                    ? round(($invTotal * $rawLine) / $grossSum, 2)
                    : round($invTotal / $itemCount, 2);

                $allocations[$idx] = [
                    'product_id' => (int) $it->product_id,
                    'amount' => $allocatedLine,
                ];
                $allocatedTotal = round($allocatedTotal + $allocatedLine, 2);
            }

            $diff = round($invTotal - $allocatedTotal, 2);
            if ($diff != 0.0 && count($allocations) > 0) {
                $maxKey = 0;
                $maxAmount = -1.0;
                foreach ($allocations as $k => $alloc) {
                    if ($alloc['amount'] > $maxAmount) {
                        $maxAmount = $alloc['amount'];
                        $maxKey = $k;
                    }
                }
                $allocations[$maxKey]['amount'] = round($allocations[$maxKey]['amount'] + $diff, 2);
            }

            foreach ($allocations as $alloc) {
                $pid = $alloc['product_id'];
                $amt = $alloc['amount'];
                $targetSections = $productIdToSectionKeys[$pid] ?? [];
                foreach ($targetSections as $tKey) {
                    if (isset($sectionDailyData[$tKey][$invDate])) {
                        $sectionDailyData[$tKey][$invDate]['sale'] = round($sectionDailyData[$tKey][$invDate]['sale'] + $amt, 2);
                    }
                }
            }
        }

        // 5b. Direct Company Sales
        foreach ($directCompanySales as $dcs) {
            $headerAmount = round((float) $dcs->amount, 2);
            $saleDate = $dcs->business_date ? Carbon::parse($dcs->business_date)->format('Y-m-d') : $startDate;
            if (! in_array($saleDate, $dates, true)) {
                continue;
            }

            $items = $dcs->items;
            $itemCount = $items->count();
            if ($itemCount === 0 || $headerAmount == 0.0) {
                continue;
            }

            $itemSum = round((float) $items->sum('line_total'), 2);
            $allocations = [];
            $allocatedTotal = 0.0;

            foreach ($items as $idx => $it) {
                $rawLine = (float) $it->line_total;
                $allocatedLine = ($itemSum > 0)
                    ? round(($headerAmount * $rawLine) / $itemSum, 2)
                    : round($headerAmount / $itemCount, 2);

                $allocations[$idx] = [
                    'product_id' => (int) $it->product_id,
                    'amount' => $allocatedLine,
                ];
                $allocatedTotal = round($allocatedTotal + $allocatedLine, 2);
            }

            $diff = round($headerAmount - $allocatedTotal, 2);
            if ($diff != 0.0 && count($allocations) > 0) {
                $maxKey = 0;
                $maxAmount = -1.0;
                foreach ($allocations as $k => $alloc) {
                    if ($alloc['amount'] > $maxAmount) {
                        $maxAmount = $alloc['amount'];
                        $maxKey = $k;
                    }
                }
                $allocations[$maxKey]['amount'] = round($allocations[$maxKey]['amount'] + $diff, 2);
            }

            foreach ($allocations as $alloc) {
                $pid = $alloc['product_id'];
                $amt = $alloc['amount'];
                $targetSections = $productIdToSectionKeys[$pid] ?? [];
                foreach ($targetSections as $tKey) {
                    if (isset($sectionDailyData[$tKey][$saleDate])) {
                        $sectionDailyData[$tKey][$saleDate]['sale'] = round($sectionDailyData[$tKey][$saleDate]['sale'] + $amt, 2);
                    }
                }
            }
        }

        // 5c. Warehouse Sales
        foreach ($warehouseSales as $whs) {
            $headerTotal = round((float) $whs->total_amount, 2);
            $saleDate = $whs->business_date ? Carbon::parse($whs->business_date)->format('Y-m-d') : $startDate;
            if (! in_array($saleDate, $dates, true)) {
                continue;
            }

            $items = $whs->items;
            $itemCount = $items->count();
            if ($itemCount === 0 || $headerTotal == 0.0) {
                continue;
            }

            $itemSum = round((float) $items->sum('line_total'), 2);
            $allocations = [];
            $allocatedTotal = 0.0;

            foreach ($items as $idx => $it) {
                $rawLine = (float) $it->line_total;
                $allocatedLine = ($itemSum > 0)
                    ? round(($headerTotal * $rawLine) / $itemSum, 2)
                    : round($headerTotal / $itemCount, 2);

                $allocations[$idx] = [
                    'product_id' => (int) $it->product_id,
                    'amount' => $allocatedLine,
                ];
                $allocatedTotal = round($allocatedTotal + $allocatedLine, 2);
            }

            $diff = round($headerTotal - $allocatedTotal, 2);
            if ($diff != 0.0 && count($allocations) > 0) {
                $maxKey = 0;
                $maxAmount = -1.0;
                foreach ($allocations as $k => $alloc) {
                    if ($alloc['amount'] > $maxAmount) {
                        $maxAmount = $alloc['amount'];
                        $maxKey = $k;
                    }
                }
                $allocations[$maxKey]['amount'] = round($allocations[$maxKey]['amount'] + $diff, 2);
            }

            foreach ($allocations as $alloc) {
                $pid = $alloc['product_id'];
                $amt = $alloc['amount'];
                $targetSections = $productIdToSectionKeys[$pid] ?? [];
                foreach ($targetSections as $tKey) {
                    if (isset($sectionDailyData[$tKey][$saleDate])) {
                        $sectionDailyData[$tKey][$saleDate]['sale'] = round($sectionDailyData[$tKey][$saleDate]['sale'] + $amt, 2);
                    }
                }
            }
        }

        // 5d. Client Shop Sales from ShopReportBridge
        foreach ($dates as $d) {
            $clientDaySalesByHeading = $clientShopData['daily_breakdown'][$d]['sales_by_heading'] ?? [];
            foreach ($allSections as $secKey => $secDef) {
                if ($secDef->isTrading() && $secDef->monthlyReportGroup) {
                    $headingKey = match ($secDef->monthlyReportGroup) {
                        'fruits' => ReportHeadingDictionary::FRUITS_SALE,
                        'vegetables' => ReportHeadingDictionary::VEGETABLES_SALE,
                        'stationery' => ReportHeadingDictionary::STATIONERY_SALE,
                        default => null,
                    };
                    if ($headingKey && isset($clientDaySalesByHeading[$headingKey])) {
                        $amt = (float) $clientDaySalesByHeading[$headingKey];
                        $sectionDailyData[$secKey][$d]['sale'] = round($sectionDailyData[$secKey][$d]['sale'] + $amt, 2);
                    }
                }
            }
        }

        // 6. Aggregate Purchases into Trading Sections
        // 6a. Purchase items from PurchaseReportingService
        foreach ($purchaseItems as $item) {
            $pid = (int) $item->product_id;
            $netAmount = round((float) $item->item_net, 2);
            $itemDate = $item->business_date ? substr((string) $item->business_date, 0, 10) : $startDate;

            $targetSections = $productIdToSectionKeys[$pid] ?? [];
            foreach ($targetSections as $tKey) {
                if (isset($sectionDailyData[$tKey][$itemDate])) {
                    $sectionDailyData[$tKey][$itemDate]['purchase'] = round($sectionDailyData[$tKey][$itemDate]['purchase'] + $netAmount, 2);
                }
            }
        }

        // 6b. Standalone Shop Cash Purchases from ShopReportBridge
        foreach ($dates as $d) {
            $shopProductExpByHeading = $clientShopData['daily_breakdown'][$d]['product_expenses_by_heading'] ?? [];
            foreach ($allSections as $secKey => $secDef) {
                if ($secDef->isTrading() && $secDef->monthlyReportGroup) {
                    $headingKey = match ($secDef->monthlyReportGroup) {
                        'fruits' => ReportHeadingDictionary::FRUITS_EXPENSE,
                        'vegetables' => ReportHeadingDictionary::VEGETABLES_EXPENSE,
                        'stationery' => ReportHeadingDictionary::STATIONERY_EXPENSE,
                        default => null,
                    };
                    if ($headingKey && isset($shopProductExpByHeading[$headingKey])) {
                        $amt = (float) $shopProductExpByHeading[$headingKey];
                        $sectionDailyData[$secKey][$d]['purchase'] = round($sectionDailyData[$secKey][$d]['purchase'] + $amt, 2);
                    }
                }
            }
        }

        // 7. Aggregate Operating Expenses
        if ($allSections->has('operating_expense')) {
            $dailyOpMatrix = $operatingExpensesData['daily_matrix'];
            foreach ($dates as $d) {
                $opAmt = (float) ($dailyOpMatrix[$d]['total'] ?? 0.0);
                $sectionDailyData['operating_expense'][$d]['expense'] = round($opAmt, 2);
            }
        }

        // 8. Compile Section Data and Summaries
        $sectionsReport = [];
        $overallTotalSales = 0.0;
        $overallTotalPurchases = 0.0;
        $overallTotalOperatingExpenses = 0.0;

        foreach ($allSections as $secKey => $secDef) {
            $dailyRows = [];
            $secSaleTotal = 0.0;
            $secPurchaseTotal = 0.0;
            $secOtherExpTotal = 0.0;
            $secExpenseTotal = 0.0;

            foreach ($dates as $d) {
                if ($secDef->isTrading()) {
                    $dSale = round($sectionDailyData[$secKey][$d]['sale'], 2);
                    $dPurchase = round($sectionDailyData[$secKey][$d]['purchase'], 2);
                    $dBalance = round($dSale - $dPurchase, 2);

                    $secSaleTotal = round($secSaleTotal + $dSale, 2);
                    $secPurchaseTotal = round($secPurchaseTotal + $dPurchase, 2);

                    $dailyRows[] = [
                        'date' => $d,
                        'formatted_date' => Carbon::parse($d)->format('d M Y, D'),
                        'day_name' => Carbon::parse($d)->format('D'),
                        'sale' => $dSale,
                        'purchase' => $dPurchase,
                        'balance' => $dBalance,
                    ];
                } else {
                    // Expense-only section
                    $dExp = round($sectionDailyData[$secKey][$d]['expense'], 2);
                    $secExpenseTotal = round($secExpenseTotal + $dExp, 2);

                    $dailyRows[] = [
                        'date' => $d,
                        'formatted_date' => Carbon::parse($d)->format('d M Y, D'),
                        'day_name' => Carbon::parse($d)->format('D'),
                        'expense' => $dExp,
                    ];
                }
            }

            if ($secDef->isTrading()) {
                $secBalance = round($secSaleTotal - $secPurchaseTotal, 2);

                $overallTotalSales = round($overallTotalSales + $secSaleTotal, 2);
                $overallTotalPurchases = round($overallTotalPurchases + $secPurchaseTotal, 2);

                $summary = [
                    'sales' => $secSaleTotal,
                    'purchases' => $secPurchaseTotal,
                    'balance' => $secBalance,
                ];
            } else {
                $overallTotalOperatingExpenses = round($overallTotalOperatingExpenses + $secExpenseTotal, 2);

                $summary = [
                    'total_expenses' => $secExpenseTotal,
                    'salary' => $operatingExpensesData['salary'] ?? 0.0,
                    'rent' => $operatingExpensesData['rent'] ?? 0.0,
                    'vehicle_fuel' => $operatingExpensesData['vehicle_fuel'] ?? 0.0,
                    'food_mess' => $operatingExpensesData['food_mess'] ?? 0.0,
                    'other_expense' => $operatingExpensesData['other_expense'] ?? 0.0,
                    'summary_by_category' => $operatingExpensesData['summary_by_category'] ?? [],
                ];
            }

            $sectionsReport[$secKey] = [
                'definition' => $secDef,
                'key' => $secKey,
                'name' => $secDef->name,
                'type' => $secDef->type,
                'summary' => $summary,
                'daily_rows' => $dailyRows,
            ];
        }

        $overallBalance = round($overallTotalSales - $overallTotalPurchases, 2);

        $overallSummary = [
            'total_sales' => $overallTotalSales,
            'total_purchases' => $overallTotalPurchases,
            'balance' => $overallBalance,
            'section_count' => count($sectionsReport),
        ];

        // 9. Single section focus/filtering
        $isSingleSection = false;
        $activeSectionKey = 'all';

        if ($sectionKey !== null && $sectionKey !== '' && $sectionKey !== 'all') {
            if (isset($sectionsReport[$sectionKey])) {
                $isSingleSection = true;
                $activeSectionKey = $sectionKey;
            }
        }

        return [
            'period' => $period,
            'sections' => $sectionsReport,
            'overall_summary' => $overallSummary,
            'selected_section_key' => $activeSectionKey,
            'is_single_section' => $isSingleSection,
            'readiness' => $this->settingsService->getReadinessSummary(),
        ];
    }
}
