<?php

declare(strict_types=1);

namespace App\Services\Cashbook\MonthlyReport;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

final class GreenLeafMonthlyReportService
{
    public function __construct(
        private readonly ReportPeriodResolver $periodResolver,
        private readonly ShopReportBridge $shopReportBridge,
        private readonly SalesAggregationService $salesAggregationService,
        private readonly PurchaseAggregationService $purchaseAggregationService,
        private readonly OperatingExpenseAggregationService $operatingExpenseAggregationService,
        private readonly PurchaserPositionService $purchaserPositionService,
        private readonly MonthlyReportReconciliationService $reconciliationService,
        private readonly FinalReportSettingsService $settingsService,
    ) {}

    /**
     * Report 1: Overview
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function overview(array $inputs): array
    {
        $period = $this->periodResolver->resolve($inputs);
        $startDate = $period['start_date'];
        $endDate = $period['end_date'];
        $dates = $period['dates'];

        $clientShopData = $this->shopReportBridge->calculateClientShopsData($startDate, $endDate, $dates);
        $salesData = $this->salesAggregationService->calculate($startDate, $endDate, $dates, $clientShopData);

        // Map shop product expenses by date
        $shopProductExpensesByDate = [];
        foreach ($dates as $d) {
            $shopProductExpensesByDate[$d] = $clientShopData['daily_breakdown'][$d]['product_expenses_by_heading'] ?? [];
        }

        $purchasesData = $this->purchaseAggregationService->calculate($startDate, $endDate, $dates, $shopProductExpensesByDate);

        // Shop operating expenses
        $shopOperatingExpensesByDate = [];
        foreach ($dates as $d) {
            $shopOperatingExpensesByDate[$d] = $clientShopData['daily_breakdown'][$d]['operating_expenses_by_heading'] ?? [];
        }

        $operatingExpensesData = $this->operatingExpenseAggregationService->calculate(
            $startDate,
            $endDate,
            $dates,
            $shopOperatingExpensesByDate,
            $clientShopData['transactions']
        );

        $totalSales = $salesData['total_sales'];
        $totalProductExpenses = $purchasesData['total_purchases'];
        $totalOperatingExpenses = $operatingExpensesData['total_operating_expenses'];
        $totalExpenses = round($totalProductExpenses + $totalOperatingExpenses, 2);
        $netBalance = round($totalSales - $totalExpenses, 2);

        // Build daily overview table rows
        $dailyRows = [];
        foreach ($dates as $date) {
            $salesDay = $salesData['daily_breakdown'][$date] ?? [];
            $purchDay = $purchasesData['daily_breakdown'][$date] ?? [];
            $opDay = $operatingExpensesData['daily_matrix'][$date] ?? [];

            $dClientSales = (float) ($salesDay['client_sales'] ?? 0.0);
            $dOtherSales = (float) ($salesDay['all_other_sales'] ?? 0.0);
            $dTotalSales = (float) ($salesDay['total_sales'] ?? 0.0);

            $dProductExp = (float) ($purchDay['total_expense'] ?? 0.0);
            $dOperatingExp = (float) ($opDay['total'] ?? 0.0);
            $dTotalExpenses = round($dProductExp + $dOperatingExp, 2);
            $dBalance = round($dTotalSales - $dTotalExpenses, 2);

            $dailyRows[] = [
                'date' => $date,
                'formatted_date' => Carbon::parse($date)->format('d M Y, D'),
                'day_name' => Carbon::parse($date)->format('D'),
                'client_sales' => $dClientSales,
                'all_other_sales' => $dOtherSales,
                'total_sales' => $dTotalSales,
                'product_expenses' => $dProductExp,
                'operating_expenses' => $dOperatingExp,
                'total_expenses' => $dTotalExpenses,
                'balance' => $dBalance,
            ];
        }

        $overviewSummary = [
            'total_sales' => $totalSales,
            'client_sales' => $salesData['client_sales'],
            'all_other_sales' => $salesData['all_other_sales'],
            'product_expenses' => $totalProductExpenses,
            'operating_expenses' => $totalOperatingExpenses,
            'total_expenses' => $totalExpenses,
            'balance' => $netBalance,
            'daily_rows' => $dailyRows,
        ];

        $saleSplitPlaceholder = [
            'total_sales' => $totalSales,
            'total_expenses' => $totalExpenses,
            'other_expenses' => $totalOperatingExpenses,
        ];
        $expenseReportPlaceholder = [
            'total_operating_expenses' => $totalOperatingExpenses,
        ];

        $reconciliation = $this->reconciliationService->reconcile(
            $overviewSummary,
            $saleSplitPlaceholder,
            $expenseReportPlaceholder,
            $salesData,
            $purchasesData,
            $operatingExpensesData,
            $clientShopData
        );

        $readiness = $this->settingsService->getReadinessSummary();

        return [
            'period' => $period,
            'summary' => $overviewSummary,
            'daily_rows' => $dailyRows,
            'clients' => $clientShopData['clients'],
            'sales_breakdown' => [
                'client_sales' => $salesData['client_sales'],
                'direct_gl_bills' => $salesData['direct_gl_bills_sales'],
                'direct_company_sales' => $salesData['direct_company_sales'],
                'warehouse_sales' => $salesData['warehouse_sales'],
            ],
            'expense_breakdown' => [
                'product_expenses' => $totalProductExpenses,
                'operating_expenses' => $totalOperatingExpenses,
            ],
            'reconciliation' => $reconciliation,
            'readiness' => $readiness,
        ];
    }

    /**
     * Report 2: Monthly Sale Split & Purchaser Position
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function saleSplit(array $inputs): array
    {
        $period = $this->periodResolver->resolve($inputs);
        $startDate = $period['start_date'];
        $endDate = $period['end_date'];
        $dates = $period['dates'];

        $clientShopData = $this->shopReportBridge->calculateClientShopsData($startDate, $endDate, $dates);
        $salesData = $this->salesAggregationService->calculate($startDate, $endDate, $dates, $clientShopData);

        $shopProductExpensesByDate = [];
        foreach ($dates as $d) {
            $shopProductExpensesByDate[$d] = $clientShopData['daily_breakdown'][$d]['product_expenses_by_heading'] ?? [];
        }

        $purchasesData = $this->purchaseAggregationService->calculate($startDate, $endDate, $dates, $shopProductExpensesByDate);

        $shopOperatingExpensesByDate = [];
        foreach ($dates as $d) {
            $shopOperatingExpensesByDate[$d] = $clientShopData['daily_breakdown'][$d]['operating_expenses_by_heading'] ?? [];
        }

        $operatingExpensesData = $this->operatingExpenseAggregationService->calculate(
            $startDate,
            $endDate,
            $dates,
            $shopOperatingExpensesByDate,
            $clientShopData['transactions']
        );

        $purchaserPositions = $this->purchaserPositionService->calculate($startDate, $endDate);

        // Daily split table
        $dailyRows = [];
        foreach ($dates as $date) {
            $sDay = $salesData['daily_breakdown'][$date] ?? [];
            $pDay = $purchasesData['daily_breakdown'][$date] ?? [];
            $oDay = $operatingExpensesData['daily_matrix'][$date] ?? [];

            $fSale = (float) ($sDay['fruits_sale'] ?? 0.0);
            $fExp = (float) ($pDay['fruits_expense'] ?? 0.0);
            $vSale = (float) ($sDay['vegetables_sale'] ?? 0.0);
            $vExp = (float) ($pDay['vegetables_expense'] ?? 0.0);
            $stSale = (float) ($sDay['stationery_sale'] ?? 0.0);
            $stExp = (float) ($pDay['stationery_expense'] ?? 0.0);
            $otherSale = (float) ($sDay['other_sale'] ?? 0.0);
            $otherProdExp = (float) ($pDay['other_product_expense'] ?? 0.0);
            $otherExp = (float) ($oDay['total'] ?? 0.0);

            $dTotalSales = round($fSale + $vSale + $stSale + $otherSale, 2);
            $dTotalExp = round($fExp + $vExp + $stExp + $otherExp, 2);
            $dBalance = round($dTotalSales - $dTotalExp, 2);

            $dailyRows[] = [
                'date' => $date,
                'formatted_date' => Carbon::parse($date)->format('d M Y, D'),
                'day_name' => Carbon::parse($date)->format('D'),
                'fruits_sale' => $fSale,
                'fruits_expense' => $fExp,
                'veg_sale' => $vSale,
                'veg_expense' => $vExp,
                'stationery_sale' => $stSale,
                'stationery_expense' => $stExp,
                'other_sale' => $otherSale,
                'other_product_expense' => $otherProdExp,
                'other_expenses' => $otherExp,
                'total_sales' => $dTotalSales,
                'total_expenses' => $dTotalExp,
                'balance' => $dBalance,
            ];
        }

        $totalSales = round($salesData['fruits_sale'] + $salesData['vegetables_sale'] + $salesData['stationery_sale'] + $salesData['other_sale'], 2);
        $totalProductExpenses = round($purchasesData['fruits_expense'] + $purchasesData['vegetables_expense'] + $purchasesData['stationery_expense'], 2);
        $totalOtherExpenses = $operatingExpensesData['total_operating_expenses'];
        $totalExpenses = round($totalProductExpenses + $totalOtherExpenses, 2);

        $summary = [
            'fruits_sale' => $salesData['fruits_sale'],
            'fruits_expense' => $purchasesData['fruits_expense'],
            'veg_sale' => $salesData['vegetables_sale'],
            'veg_expense' => $purchasesData['vegetables_expense'],
            'stationery_sale' => $salesData['stationery_sale'],
            'stationery_expense' => $purchasesData['stationery_expense'],
            'other_sale' => $salesData['other_sale'],
            'other_product_expense' => $purchasesData['other_product_expense'],
            'other_expenses' => $totalOtherExpenses,
            'total_sales' => $totalSales,
            'total_expenses' => $totalExpenses,
            'balance' => round($totalSales - $totalExpenses, 2),
        ];

        $reconciliation = $this->reconciliationService->reconcile(
            ['total_sales' => $totalSales, 'total_expenses' => $totalExpenses, 'daily_rows' => $dailyRows],
            ['total_sales' => $totalSales, 'total_expenses' => $totalExpenses, 'other_expenses' => $totalOtherExpenses],
            ['total_operating_expenses' => $totalOtherExpenses],
            $salesData,
            $purchasesData,
            $operatingExpensesData,
            $clientShopData
        );

        return [
            'period' => $period,
            'summary' => $summary,
            'daily_rows' => $dailyRows,
            'purchaser_positions' => $purchaserPositions,
            'reconciliation' => $reconciliation,
            'readiness' => $this->settingsService->getReadinessSummary(),
        ];
    }

    /**
     * Report 3: Other Expense (Original Categories & Details)
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function otherExpenses(array $inputs): array
    {
        $period = $this->periodResolver->resolve($inputs);
        $startDate = $period['start_date'];
        $endDate = $period['end_date'];
        $dates = $period['dates'];

        $clientShopData = $this->shopReportBridge->calculateClientShopsData($startDate, $endDate, $dates);
        $operatingExpensesData = $this->operatingExpenseAggregationService->calculate(
            $startDate,
            $endDate,
            $dates,
            [],
            $clientShopData['transactions']
        );

        $detailedRows = $operatingExpensesData['detailed_rows'];

        // Apply page-level filters
        if (! empty($inputs['heading'])) {
            $detailedRows = $detailedRows->where('report_bucket', $inputs['heading']);
        }
        if (! empty($inputs['source_type'])) {
            $detailedRows = $detailedRows->where('source_type', $inputs['source_type']);
        }
        if (! empty($inputs['category'])) {
            $detailedRows = $detailedRows->where('original_category', $inputs['category']);
        }
        if (! empty($inputs['search'])) {
            $search = strtolower(trim((string) $inputs['search']));
            $detailedRows = $detailedRows->filter(function ($row) use ($search) {
                return str_contains(strtolower((string) $row['description']), $search)
                    || str_contains(strtolower((string) $row['reference']), $search)
                    || str_contains(strtolower((string) $row['entity_name']), $search)
                    || str_contains(strtolower((string) $row['original_category']), $search);
            });
        }

        // 2. Run reconciliation across all subsystems
        $shopProductExpensesByDate = [];
        foreach ($dates as $d) {
            $shopProductExpensesByDate[$d] = $clientShopData['daily_breakdown'][$d]['product_expenses_by_heading'] ?? [];
        }
        $purchasesData = $this->purchaseAggregationService->calculate($startDate, $endDate, $dates, $shopProductExpensesByDate);
        $salesData = $this->salesAggregationService->calculate($startDate, $endDate, $dates, $clientShopData);

        $dailyRows = [];
        foreach ($dates as $date) {
            $salesDay = $salesData['daily_breakdown'][$date] ?? [];
            $purchDay = $purchasesData['daily_breakdown'][$date] ?? [];
            $opDay = $operatingExpensesData['daily_matrix'][$date] ?? [];

            $dTotalSales = (float) ($salesDay['total_sales'] ?? 0.0);
            $dProductExp = (float) ($purchDay['total_expense'] ?? 0.0);
            $dOperatingExp = (float) ($opDay['total'] ?? 0.0);
            $dTotalExpenses = round($dProductExp + $dOperatingExp, 2);

            $dailyRows[] = [
                'date' => $date,
                'total_sales' => $dTotalSales,
                'total_expenses' => $dTotalExpenses,
                'product_expenses' => $dProductExp,
                'operating_expenses' => $dOperatingExp,
            ];
        }

        $overviewSummary = [
            'total_sales' => $salesData['total_sales'],
            'total_expenses' => round($purchasesData['total_purchases'] + $operatingExpensesData['total_operating_expenses'], 2),
            'daily_rows' => $dailyRows,
        ];
        $saleSplitSummary = [
            'total_sales' => $salesData['total_sales'],
            'total_expenses' => round($purchasesData['total_purchases'] + $operatingExpensesData['total_operating_expenses'], 2),
            'other_expenses' => $operatingExpensesData['total_operating_expenses'],
        ];

        $reconciliation = $this->reconciliationService->reconcile(
            $overviewSummary,
            $saleSplitSummary,
            ['total_operating_expenses' => $operatingExpensesData['total_operating_expenses']],
            $salesData,
            $purchasesData,
            $operatingExpensesData,
            $clientShopData
        );

        $page = (int) ($inputs['page'] ?? 1);
        $perPage = 50;
        $paginatedRows = new LengthAwarePaginator(
            $detailedRows->forPage($page, $perPage)->values(),
            $detailedRows->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return [
            'period' => $period,
            'total_operating_expenses' => $operatingExpensesData['total_operating_expenses'],
            'total_other_expenses' => $operatingExpensesData['total_operating_expenses'],
            'summary_by_category' => $operatingExpensesData['summary_by_category'],
            'detailed_rows' => $paginatedRows,
            'all_detailed_rows' => $detailedRows->values(),
            'total_filtered_amount' => round((float) $detailedRows->sum('amount'), 2),
            'total_filtered_count' => $detailedRows->count(),
            'headings_filter_options' => [
                ReportHeadingDictionary::SALARY => 'Salary',
                ReportHeadingDictionary::RENT => 'Rent',
                ReportHeadingDictionary::VEHICLE_FUEL => 'Vehicle/Fuel',
                ReportHeadingDictionary::FOOD_MESS => 'Food/Mess',
                ReportHeadingDictionary::OTHER_EXPENSE => 'Others',
            ],
            'sources_filter_options' => [
                'Shop Cashbook' => 'Shop Cashbook',
                'Procurement Expense' => 'Procurement Expense',
                'Purchaser Other Expense' => 'Purchaser Other Expense',
                'Company Expense' => 'Company Expense',
            ],
            'reconciliation' => $reconciliation,
            'readiness' => $this->settingsService->getReadinessSummary(),
        ];
    }

    /**
     * Report 4: Expense Report (Daily Operating Expense Matrix)
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function expenseReport(array $inputs): array
    {
        $period = $this->periodResolver->resolve($inputs);
        $startDate = $period['start_date'];
        $endDate = $period['end_date'];
        $dates = $period['dates'];

        $clientShopData = $this->shopReportBridge->calculateClientShopsData($startDate, $endDate, $dates);
        $shopOperatingExpensesByDate = [];
        foreach ($dates as $d) {
            $shopOperatingExpensesByDate[$d] = $clientShopData['daily_breakdown'][$d]['operating_expenses_by_heading'] ?? [];
        }
        $operatingExpensesData = $this->operatingExpenseAggregationService->calculate(
            $startDate,
            $endDate,
            $dates,
            $shopOperatingExpensesByDate,
            $clientShopData['transactions']
        );
        $shopProductExpensesByDate = [];
        foreach ($dates as $d) {
            $shopProductExpensesByDate[$d] = $clientShopData['daily_breakdown'][$d]['product_expenses_by_heading'] ?? [];
        }
        $purchasesData = $this->purchaseAggregationService->calculate($startDate, $endDate, $dates, $shopProductExpensesByDate);
        $salesData = $this->salesAggregationService->calculate($startDate, $endDate, $dates, $clientShopData);

        // Build daily overview table rows for reconciliation
        $dailyRows = [];
        foreach ($dates as $date) {
            $salesDay = $salesData['daily_breakdown'][$date] ?? [];
            $purchDay = $purchasesData['daily_breakdown'][$date] ?? [];
            $opDay = $operatingExpensesData['daily_matrix'][$date] ?? [];

            $dTotalSales = (float) ($salesDay['total_sales'] ?? 0.0);
            $dProductExp = (float) ($purchDay['total_expense'] ?? 0.0);
            $dOperatingExp = (float) ($opDay['total'] ?? 0.0);
            $dTotalExpenses = round($dProductExp + $dOperatingExp, 2);

            $dailyRows[] = [
                'date' => $date,
                'total_sales' => $dTotalSales,
                'total_expenses' => $dTotalExpenses,
                'product_expenses' => $dProductExp,
                'operating_expenses' => $dOperatingExp,
            ];
        }

        $overviewSummary = [
            'total_sales' => $salesData['total_sales'],
            'total_expenses' => round($purchasesData['total_purchases'] + $operatingExpensesData['total_operating_expenses'], 2),
            'daily_rows' => $dailyRows,
        ];
        $saleSplitSummary = [
            'total_sales' => $salesData['total_sales'],
            'total_expenses' => round($purchasesData['total_purchases'] + $operatingExpensesData['total_operating_expenses'], 2),
            'other_expenses' => $operatingExpensesData['total_operating_expenses'],
        ];

        $reconciliation = $this->reconciliationService->reconcile(
            $overviewSummary,
            $saleSplitSummary,
            ['total_operating_expenses' => $operatingExpensesData['total_operating_expenses']],
            $salesData,
            $purchasesData,
            $operatingExpensesData,
            $clientShopData
        );

        return [
            'period' => $period,
            'totals' => $operatingExpensesData['totals_by_heading'],
            'total_operating_expenses' => $operatingExpensesData['total_operating_expenses'],
            'daily_matrix' => array_values($operatingExpensesData['daily_matrix']),
            'reconciliation' => $reconciliation,
            'readiness' => $this->settingsService->getReadinessSummary(),
        ];
    }

    /**
     * Drilldown endpoint data provider.
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function drilldown(array $inputs): array
    {
        $metric = (string) ($inputs['metric'] ?? 'total_sales');
        $date = $inputs['date'] ?? null;
        $period = $this->periodResolver->resolve($inputs);

        $startDate = $date ?: $period['start_date'];
        $endDate = $date ?: $period['end_date'];
        $dates = [$startDate];

        $clientShopData = $this->shopReportBridge->calculateClientShopsData($startDate, $endDate, $dates);
        $salesData = $this->salesAggregationService->calculate($startDate, $endDate, $dates, $clientShopData);
        $shopProductExpensesByDate = [];
        foreach ($dates as $d) {
            $shopProductExpensesByDate[$d] = $clientShopData['daily_breakdown'][$d]['product_expenses_by_heading'] ?? [];
        }
        $purchasesData = $this->purchaseAggregationService->calculate($startDate, $endDate, $dates, $shopProductExpensesByDate);
        $opData = $this->operatingExpenseAggregationService->calculate($startDate, $endDate, $dates, [], $clientShopData['transactions']);

        $rows = [];
        $metricLabel = 'Metric Breakdown';
        $total = 0.0;

        switch ($metric) {
            case 'total_sales':
                $metricLabel = 'Total Sales Breakdown';
                $total = $salesData['total_sales'];
                foreach ($clientShopData['transactions']->whereIn('report_bucket', [
                    ReportHeadingDictionary::FRUITS_SALE,
                    ReportHeadingDictionary::VEGETABLES_SALE,
                    ReportHeadingDictionary::STATIONERY_SALE,
                    ReportHeadingDictionary::OTHER_SALE,
                ]) as $stx) {
                    $rows[] = [
                        'source_type' => 'Client Shop Cashbook',
                        'business_date' => $stx['business_date'],
                        'entity_name' => $stx['shop_name'],
                        'reference' => $stx['reference'],
                        'amount' => (float) $stx['amount'],
                        'description' => $stx['entry_type_name'].' ('.$stx['bucket_label'].')',
                    ];
                }
                foreach ($salesData['direct_gl_invoices'] as $inv) {
                    $rows[] = [
                        'source_type' => 'Direct Shop GL Bill',
                        'business_date' => $inv->business_date,
                        'entity_name' => $inv->shop?->name ?? 'Shop #'.$inv->shop_id,
                        'reference' => $inv->invoice_number,
                        'amount' => (float) $inv->final_total,
                        'description' => 'Direct Shop Invoice #'.$inv->invoice_number,
                    ];
                }
                foreach ($salesData['direct_company_sale_records'] as $dcs) {
                    $rows[] = [
                        'source_type' => 'Direct Company Sale',
                        'business_date' => $dcs->business_date,
                        'entity_name' => $dcs->customer_name ?: 'Direct Customer',
                        'reference' => $dcs->reference ?: ('DCS-'.$dcs->id),
                        'amount' => (float) $dcs->amount,
                        'description' => $dcs->note ?: 'Direct Company Sale',
                    ];
                }
                foreach ($salesData['warehouse_sale_records'] as $whs) {
                    $rows[] = [
                        'source_type' => 'Warehouse Sale',
                        'business_date' => $whs->business_date,
                        'entity_name' => $whs->customer_name_snapshot ?: ($whs->warehouse?->name ?? 'Warehouse'),
                        'reference' => $whs->invoice_number ?: ('WHS-'.$whs->id),
                        'amount' => (float) $whs->total_amount,
                        'description' => $whs->notes ?: 'Warehouse Sale',
                    ];
                }
                break;

            case 'client_sales':
                $metricLabel = 'Client & Owned Shops Sales';
                $total = $clientShopData['total_client_sales'];
                $rows = $clientShopData['transactions']
                    ->whereIn('report_bucket', [
                        ReportHeadingDictionary::FRUITS_SALE,
                        ReportHeadingDictionary::VEGETABLES_SALE,
                        ReportHeadingDictionary::STATIONERY_SALE,
                        ReportHeadingDictionary::OTHER_SALE,
                    ])
                    ->values()
                    ->all();
                break;

            case 'all_other_sales':
                $metricLabel = 'All Other Sales (Direct Shops, Company, Warehouse)';
                $total = $salesData['all_other_sales'];
                foreach ($salesData['direct_gl_invoices'] as $inv) {
                    $rows[] = [
                        'source_type' => 'Direct Shop GL Bill',
                        'business_date' => $inv->business_date,
                        'entity_name' => $inv->shop?->name ?? 'Shop #'.$inv->shop_id,
                        'reference' => $inv->invoice_number,
                        'amount' => (float) $inv->final_total,
                        'description' => 'Direct Shop Invoice #'.$inv->invoice_number,
                    ];
                }
                foreach ($salesData['direct_company_sale_records'] as $dcs) {
                    $rows[] = [
                        'source_type' => 'Direct Company Sale',
                        'business_date' => $dcs->business_date,
                        'entity_name' => $dcs->customer_name ?: 'Direct Customer',
                        'reference' => $dcs->reference ?: ('DCS-'.$dcs->id),
                        'amount' => (float) $dcs->amount,
                        'description' => $dcs->note ?: 'Direct Company Sale',
                    ];
                }
                foreach ($salesData['warehouse_sale_records'] as $whs) {
                    $rows[] = [
                        'source_type' => 'Warehouse Sale',
                        'business_date' => $whs->business_date,
                        'entity_name' => $whs->customer_name_snapshot ?: ($whs->warehouse?->name ?? 'Warehouse'),
                        'reference' => $whs->invoice_number ?: ('WHS-'.$whs->id),
                        'amount' => (float) $whs->total_amount,
                        'description' => $whs->notes ?: 'Warehouse Sale',
                    ];
                }
                break;

            case 'total_expenses':
                $metricLabel = 'Total Expenses (Purchases + Operating)';
                $total = round($purchasesData['total_purchases'] + $opData['total_operating_expenses'], 2);
                foreach ($purchasesData['items_collection'] as $it) {
                    $rows[] = [
                        'source_type' => 'Product Purchase',
                        'business_date' => $it->business_date,
                        'entity_name' => $it->purchaser_name ?? 'Purchaser',
                        'reference' => $it->invoice_number ?? ('CART-'.$it->purchaser_cart_id),
                        'amount' => (float) $it->item_net,
                        'description' => $it->product_name.' ('.round((float) $it->quantity, 2).' '.$it->unit.')',
                    ];
                }
                foreach ($opData['detailed_rows'] as $r) {
                    $rows[] = $r;
                }
                break;

            case 'product_expenses':
                $metricLabel = 'Product Purchases / Expenses';
                $total = $purchasesData['total_purchases'];
                foreach ($purchasesData['items_collection'] as $it) {
                    $rows[] = [
                        'source_type' => 'Product Purchase',
                        'business_date' => $it->business_date,
                        'entity_name' => $it->purchaser_name ?? 'Purchaser',
                        'reference' => $it->invoice_number ?? ('CART-'.$it->purchaser_cart_id),
                        'amount' => (float) $it->item_net,
                        'description' => $it->product_name.' ('.round((float) $it->quantity, 2).' '.$it->unit.') - '.strtoupper((string) $it->report_group),
                    ];
                }
                break;

            case 'fruits_sale':
            case 'vegetables_sale':
            case 'stationery_sale':
            case 'other_sale':
                $metricLabel = ReportHeadingDictionary::getLabel($metric);
                $filteredSales = $clientShopData['transactions']->where('report_bucket', $metric)->values();
                $total = (float) $filteredSales->sum('amount');
                $rows = $filteredSales->all();
                break;

            case 'fruits_expense':
            case 'vegetables_expense':
            case 'stationery_expense':
                $grpName = match ($metric) {
                    'fruits_expense' => 'fruits',
                    'vegetables_expense' => 'vegetables',
                    'stationery_expense' => 'stationery',
                    default => 'other',
                };
                $metricLabel = ReportHeadingDictionary::getLabel($metric);
                $filteredItems = $purchasesData['items_collection']->where('report_group', $grpName)->values();
                $total = (float) $filteredItems->sum('item_net');
                foreach ($filteredItems as $it) {
                    $rows[] = [
                        'source_type' => 'Product Purchase',
                        'business_date' => $it->business_date,
                        'entity_name' => $it->purchaser_name ?? 'Purchaser',
                        'reference' => $it->invoice_number ?? ('CART-'.$it->purchaser_cart_id),
                        'amount' => (float) $it->item_net,
                        'description' => $it->product_name.' ('.round((float) $it->quantity, 2).' '.$it->unit.')',
                    ];
                }
                break;

            case 'purchaser_timeline':
                $purchaserId = (int) ($inputs['purchaser_id'] ?? 0);
                $metricLabel = 'Purchaser Cash Timeline';
                $rows = $this->purchaserPositionService->getPurchaserTimeline($purchaserId, $startDate, $endDate);
                $total = (float) array_sum(array_column($rows, 'out_amount'));
                break;

            default:
                $metricLabel = ReportHeadingDictionary::getLabel($metric);
                $filtered = $opData['detailed_rows']->where('report_bucket', $metric)->values();
                $total = (float) $filtered->sum('amount');
                $rows = $filtered->all();
                break;
        }

        return [
            'period' => $period,
            'metric' => $metric,
            'metric_label' => $metricLabel,
            'total' => round($total, 2),
            'rows' => $rows,
            'row_count' => count($rows),
        ];
    }
}
