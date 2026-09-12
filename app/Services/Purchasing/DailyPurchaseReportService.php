<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\ProcurementExpense;
use App\Models\Warehouse;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DailyPurchaseReportService
{
    private const INCLUDED_SALES_STATUSES = [
        'generated',
        'delivery_review',
        'finalized',
        'payment_pending',
        'paid',
    ];

    /**
     * Resolve date range from filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    public function resolveDateRange(array $filters): array
    {
        $period = (string) ($filters['period'] ?? 'month');
        $dateFrom = (string) ($filters['start_date'] ?? $filters['date_from'] ?? '');
        $dateTo = (string) ($filters['end_date'] ?? $filters['date_to'] ?? '');

        $today = now('Asia/Kolkata')->startOfDay();

        if ($period === 'today') {
            return [$today->copy(), $today->copy()->endOfDay(), 'today'];
        }

        if ($period === 'yesterday') {
            $yesterday = $today->copy()->subDay();

            return [$yesterday->copy(), $yesterday->copy()->endOfDay(), 'yesterday'];
        }

        if (in_array($period, ['month', 'this_month'], true)) {
            $start = $today->copy()->startOfMonth();
            $end = $today->copy()->endOfMonth()->endOfDay();

            return [$start, $end, 'month'];
        }

        if (in_array($period, ['custom', 'between', 'range'], true) || (filled($dateFrom) && filled($dateTo))) {
            $start = filled($dateFrom) ? Carbon::parse($dateFrom, 'Asia/Kolkata')->startOfDay() : $today->copy()->startOfMonth();
            $end = filled($dateTo) ? Carbon::parse($dateTo, 'Asia/Kolkata')->endOfDay() : $today->copy()->endOfDay();

            if ($start->greaterThan($end)) {
                [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            }

            return [$start, $end, 'custom'];
        }

        $start = $today->copy()->startOfMonth();
        $end = $today->copy()->endOfMonth()->endOfDay();

        return [$start, $end, 'month'];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getDailyReportData(array $filters): array
    {
        [$startDate, $endDate, $resolvedPeriod] = $this->resolveDateRange($filters);

        $warehouseId = filled($filters['warehouse_id'] ?? null) ? (int) $filters['warehouse_id'] : null;
        $purchaserId = filled($filters['purchaser_id'] ?? null) ? (int) $filters['purchaser_id'] : null;

        $startDateStr = $startDate->toDateString();
        $endDateStr = $endDate->toDateString();

        // 1. Fetch Sales Rows
        $salesRows = $this->fetchSalesRows($startDateStr, $endDateStr, $warehouseId);

        // 2. Fetch Purchases Rows & Item Breakdown
        $purchaseRows = $this->fetchPurchaseRows($startDateStr, $endDateStr, $warehouseId, $purchaserId);

        // 3. Fetch Purchaser Expenses
        $expenseRows = $this->fetchExpenseRows($startDateStr, $endDateStr, $warehouseId, $purchaserId);

        // 4. Collect all distinct business dates
        $allDates = collect()
            ->merge($salesRows->keys())
            ->merge($purchaseRows->keys())
            ->merge($expenseRows->keys())
            ->unique()
            ->sortDesc()
            ->values();

        $dailyRows = [];
        $totalSales = 0.0;
        $totalPurchase = 0.0;
        $totalExpenses = 0.0;

        foreach ($allDates as $dateStr) {
            $daySalesList = $salesRows->get($dateStr, collect());
            $dayPurchaseData = $purchaseRows->get($dateStr, [
                'total_purchase' => 0.0,
                'by_warehouse' => [],
                'by_purchaser' => [],
                'by_supplier' => [],
                'items' => [],
            ]);
            $dayExpensesList = $expenseRows->get($dateStr, collect());

            $daySales = round((float) $daySalesList->sum('sales'), 2);
            $dayPurchase = round((float) $dayPurchaseData['total_purchase'], 2);
            $dayExpense = round((float) $dayExpensesList->sum('amount'), 2);

            $dayTotalCost = round($dayPurchase + $dayExpense, 2);
            $dayDifference = round($daySales - $dayTotalCost, 2);

            $totalSales += $daySales;
            $totalPurchase += $dayPurchase;
            $totalExpenses += $dayExpense;

            $carbonDate = Carbon::parse($dateStr, 'Asia/Kolkata');

            $dailyRows[] = [
                'date' => $dateStr,
                'date_formatted' => $carbonDate->format('d M Y'),
                'day_name' => $carbonDate->format('D'),
                'sales' => $daySales,
                'purchase' => $dayPurchase,
                'purchaser_expenses' => $dayExpense,
                'total_cost' => $dayTotalCost,
                'difference' => $dayDifference,
                'sales_breakdown' => $daySalesList->values()->all(),
                'purchase_breakdown' => [
                    'by_warehouse' => $dayPurchaseData['by_warehouse'],
                    'by_purchaser' => $dayPurchaseData['by_purchaser'],
                    'by_supplier' => $dayPurchaseData['by_supplier'],
                    'items' => $dayPurchaseData['items'],
                ],
                'expense_breakdown' => $dayExpensesList->values()->all(),
            ];
        }

        $totalCost = round($totalPurchase + $totalExpenses, 2);
        $totalDifference = round($totalSales - $totalCost, 2);

        return [
            'period' => $resolvedPeriod,
            'start_date' => $startDateStr,
            'end_date' => $endDateStr,
            'summary' => [
                'total_sales' => round($totalSales, 2),
                'total_purchase' => round($totalPurchase, 2),
                'purchaser_expenses' => round($totalExpenses, 2),
                'total_cost' => round($totalCost, 2),
                'difference' => round($totalDifference, 2),
                'days_count' => count($dailyRows),
            ],
            'rows' => $dailyRows,
        ];
    }

    /**
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    private function fetchSalesRows(string $startDate, string $endDate, ?int $warehouseId): Collection
    {
        if ($warehouseId !== null) {
            $rawSales = DB::table('shop_invoices')
                ->join('shops', 'shops.id', '=', 'shop_invoices.shop_id')
                ->join('shop_invoice_items', 'shop_invoice_items.shop_invoice_id', '=', 'shop_invoices.id')
                ->join('products', 'products.id', '=', 'shop_invoice_items.product_id')
                ->whereDate('shop_invoices.business_date', '>=', $startDate)
                ->whereDate('shop_invoices.business_date', '<=', $endDate)
                ->whereIn('shop_invoices.status', self::INCLUDED_SALES_STATUSES)
                ->where('products.default_warehouse_id', $warehouseId)
                ->select([
                    DB::raw('DATE(shop_invoices.business_date) as business_date_str'),
                    'shop_invoices.shop_id',
                    'shops.name as shop_name',
                    'shops.code as shop_code',
                ])
                ->selectRaw('COUNT(DISTINCT shop_invoices.id) as invoice_count')
                ->selectRaw('SUM(shop_invoice_items.final_line_total) as shop_sales')
                ->groupBy('business_date_str', 'shop_invoices.shop_id', 'shops.name', 'shops.code')
                ->orderBy('shops.name')
                ->get();
        } else {
            $rawSales = DB::table('shop_invoices')
                ->join('shops', 'shops.id', '=', 'shop_invoices.shop_id')
                ->whereDate('shop_invoices.business_date', '>=', $startDate)
                ->whereDate('shop_invoices.business_date', '<=', $endDate)
                ->whereIn('shop_invoices.status', self::INCLUDED_SALES_STATUSES)
                ->select([
                    DB::raw('DATE(shop_invoices.business_date) as business_date_str'),
                    'shop_invoices.shop_id',
                    'shops.name as shop_name',
                    'shops.code as shop_code',
                ])
                ->selectRaw('COUNT(shop_invoices.id) as invoice_count')
                ->selectRaw('SUM(shop_invoices.final_total) as shop_sales')
                ->groupBy('business_date_str', 'shop_invoices.shop_id', 'shops.name', 'shops.code')
                ->orderBy('shops.name')
                ->get();
        }

        return $rawSales->groupBy('business_date_str')->map(function ($items): Collection {
            return $items->map(fn (object $item): array => [
                'shop_id' => (int) $item->shop_id,
                'shop_name' => (string) $item->shop_name,
                'shop_code' => (string) $item->shop_code,
                'invoice_count' => (int) $item->invoice_count,
                'sales' => round((float) $item->shop_sales, 2),
            ]);
        });
    }

    /**
     * @return Collection<string, array<string, mixed>>
     */
    private function fetchPurchaseRows(string $startDate, string $endDate, ?int $warehouseId, ?int $purchaserId): Collection
    {
        $cartTotals = DB::table('purchaser_cart_items')
            ->selectRaw('purchaser_cart_id, SUM(CASE WHEN line_total > 0 THEN line_total ELSE quantity * unit_price END) as gross_total')
            ->groupBy('purchaser_cart_id');

        $grossExpression = 'CASE WHEN purchaser_cart_items.line_total > 0 THEN purchaser_cart_items.line_total ELSE purchaser_cart_items.quantity * purchaser_cart_items.unit_price END';
        $invoiceNetExpression = 'CASE WHEN purchase_invoices.amount - purchase_invoices.discount_amount > 0 THEN purchase_invoices.amount - purchase_invoices.discount_amount ELSE 0 END';
        $netExpression = "({$grossExpression} * CASE WHEN COALESCE(cart_totals.gross_total, 0) > 0 THEN ({$invoiceNetExpression}) / cart_totals.gross_total ELSE 1 END)";

        $query = DB::table('purchase_invoices')
            ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
            ->join('purchaser_cart_items', 'purchaser_cart_items.purchaser_cart_id', '=', 'purchaser_carts.id')
            ->join('products', 'products.id', '=', 'purchaser_cart_items.product_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'products.default_warehouse_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
            ->leftJoin('users', 'users.id', '=', 'purchaser_carts.user_id')
            ->leftJoinSub($cartTotals, 'cart_totals', 'cart_totals.purchaser_cart_id', '=', 'purchaser_carts.id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->whereDate('purchaser_carts.business_date', '>=', $startDate)
            ->whereDate('purchaser_carts.business_date', '<=', $endDate)
            ->when($warehouseId !== null, fn (Builder $q) => $q->where('products.default_warehouse_id', $warehouseId))
            ->when($purchaserId !== null, fn (Builder $q) => $q->where('purchaser_carts.user_id', $purchaserId))
            ->select([
                DB::raw('DATE(purchaser_carts.business_date) as business_date_str'),
                'purchaser_carts.id as cart_id',
                'purchase_invoices.id as invoice_id',
                'purchase_invoices.supplier_id',
                'suppliers.name as supplier_name',
                'purchaser_carts.user_id as purchaser_id',
                'users.name as purchaser_name',
                'products.id as product_id',
                'products.name as product_name',
                'products.default_warehouse_id as warehouse_id',
                'warehouses.name as warehouse_name',
                'warehouses.code as warehouse_code',
                'purchaser_cart_items.quantity',
                'purchaser_cart_items.unit_price',
                DB::raw("{$netExpression} as item_net"),
            ]);

        $items = $query->get();

        return $items->groupBy('business_date_str')->map(function ($dateItems): array {
            $totalPurchase = round((float) $dateItems->sum('item_net'), 2);

            // Group by warehouse
            $byWarehouse = $dateItems->groupBy(fn ($i) => (string) ($i->warehouse_id ?? 'none'))->map(function ($wItems): array {
                $first = $wItems->first();

                return [
                    'warehouse_id' => $first->warehouse_id ? (int) $first->warehouse_id : null,
                    'warehouse_name' => $first->warehouse_name ?: 'Unassigned',
                    'warehouse_code' => $first->warehouse_code ?: '-',
                    'amount' => round((float) $wItems->sum('item_net'), 2),
                    'items_count' => $wItems->count(),
                ];
            })->values()->sortByDesc('amount')->values()->all();

            // Group by purchaser
            $byPurchaser = $dateItems->groupBy(fn ($i) => (string) ($i->purchaser_id ?? 'none'))->map(function ($pItems): array {
                $first = $pItems->first();

                return [
                    'purchaser_id' => $first->purchaser_id ? (int) $first->purchaser_id : null,
                    'purchaser_name' => $first->purchaser_name ?: 'Unassigned',
                    'amount' => round((float) $pItems->sum('item_net'), 2),
                    'carts_count' => $pItems->unique('cart_id')->count(),
                ];
            })->values()->sortByDesc('amount')->values()->all();

            // Group by supplier
            $bySupplier = $dateItems->groupBy(fn ($i) => (string) ($i->supplier_id ?? 'none'))->map(function ($sItems): array {
                $first = $sItems->first();

                return [
                    'supplier_id' => $first->supplier_id ? (int) $first->supplier_id : null,
                    'supplier_name' => $first->supplier_name ?: 'Unassigned',
                    'amount' => round((float) $sItems->sum('item_net'), 2),
                    'invoices_count' => $sItems->unique('invoice_id')->count(),
                ];
            })->values()->sortByDesc('amount')->values()->all();

            // Group by product
            $byProduct = $dateItems->groupBy('product_id')->map(function ($pItems): array {
                $first = $pItems->first();

                return [
                    'product_id' => (int) $first->product_id,
                    'product_name' => (string) $first->product_name,
                    'quantity' => round((float) $pItems->sum('quantity'), 2),
                    'amount' => round((float) $pItems->sum('item_net'), 2),
                ];
            })->values()->sortByDesc('amount')->take(15)->values()->all();

            return [
                'total_purchase' => $totalPurchase,
                'by_warehouse' => $byWarehouse,
                'by_purchaser' => $byPurchaser,
                'by_supplier' => $bySupplier,
                'items' => $byProduct,
            ];
        });
    }

    /**
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    private function fetchExpenseRows(string $startDate, string $endDate, ?int $warehouseId, ?int $purchaserId): Collection
    {
        // Procurement expenses are purchaser-level expenses; if a warehouse filter is explicitly active, expenses without warehouse are excluded
        if ($warehouseId !== null) {
            return collect();
        }

        $categoryLabels = ProcurementExpense::categories();

        $expenses = DB::table('procurement_expenses')
            ->join('users', 'users.id', '=', 'procurement_expenses.user_id')
            ->whereDate('procurement_expenses.expense_date', '>=', $startDate)
            ->whereDate('procurement_expenses.expense_date', '<=', $endDate)
            ->when($purchaserId !== null, fn (Builder $q) => $q->where('procurement_expenses.user_id', $purchaserId))
            ->select([
                DB::raw('DATE(procurement_expenses.expense_date) as expense_date_str'),
                'procurement_expenses.id',
                'procurement_expenses.user_id',
                'users.name as purchaser_name',
                'procurement_expenses.category',
                'procurement_expenses.amount',
                'procurement_expenses.note',
            ])
            ->orderBy('procurement_expenses.id')
            ->get();

        return $expenses->groupBy('expense_date_str')->map(function ($dateExpenses) use ($categoryLabels): Collection {
            return $dateExpenses->map(fn (object $e): array => [
                'id' => (int) $e->id,
                'purchaser_id' => (int) $e->user_id,
                'purchaser_name' => (string) $e->purchaser_name,
                'category' => (string) $e->category,
                'category_label' => $categoryLabels[$e->category] ?? ucfirst((string) $e->category),
                'amount' => round((float) $e->amount, 2),
                'note' => (string) ($e->note ?? ''),
            ]);
        });
    }
}
