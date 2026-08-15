<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cashbook\LedgerClient;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminCashbookReportsController extends Controller
{
    public function __construct(
        private readonly CashbookShopSyncService $shopSyncService,
    ) {}

    /**
     * Owned Shops Reports Hub Dashboard with 3-in-a-row compact cards.
     */
    public function hub(Request $request): View
    {
        $this->ensureAuthorized($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $timeframe = (string) $request->input('timeframe', 'today');
        $dateRange = $this->resolveDateRange($timeframe, $request);

        $shopMetrics = $this->calculateMultiShopMetrics($shops, $dateRange['start'], $dateRange['end']);

        $totals = [
            'sales' => round((float) $shopMetrics->sum('sales'), 2),
            'expense' => round((float) $shopMetrics->sum('expense'), 2),
            'net' => round((float) $shopMetrics->sum('net'), 2),
            'gl_bills' => round((float) $shopMetrics->sum('gl_bills'), 2),
        ];

        return view('admin.cashbook.reports.hub', [
            'shops' => $shops,
            'totals' => $totals,
            'shopMetrics' => $shopMetrics,
            'timeframe' => $timeframe,
            'startDate' => $dateRange['start'],
            'endDate' => $dateRange['end'],
            'activeTab' => 'hub',
        ]);
    }

    /**
     * Detailed Single Shop Report Drill-down.
     */
    public function detail(Request $request, string $shopParam): View
    {
        $this->ensureAuthorized($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $shop = $this->resolveShop($shopParam);

        $timeframe = (string) $request->input('timeframe', 'today');
        $dateRange = $this->resolveDateRange($timeframe, $request);

        $metrics = $this->calculateSingleShopDetail($shop->shop_id, $dateRange['start'], $dateRange['end']);

        return view('admin.cashbook.reports.detail', [
            'shops' => $shops,
            'currentShop' => $shop,
            'metrics' => $metrics,
            'timeframe' => $timeframe,
            'startDate' => $dateRange['start'],
            'endDate' => $dateRange['end'],
            'activeTab' => 'detail',
        ]);
    }

    /**
     * Category-Wise Dynamic Graph and Expense Distribution (Owned Shops Only).
     */
    public function charts(Request $request): View
    {
        $this->ensureAuthorized($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $ownedShops = $shops->filter(fn ($s) => $s->client_id !== null)->values();
        $shops = $ownedShops->isNotEmpty() ? $ownedShops : $shops;

        $timeframe = (string) $request->input('timeframe', 'monthly');
        $dateRange = $this->resolveDateRange($timeframe, $request);
        $selectedShopId = $request->filled('shop_id') ? (int) $request->input('shop_id') : null;
        $selectedShop = $selectedShopId ? $shops->firstWhere('shop_id', $selectedShopId) : null;

        $chartData = $this->generateCategoryChartData($shops, $dateRange['start'], $dateRange['end'], $selectedShopId);

        return view('admin.cashbook.reports.charts', [
            'shops' => $shops,
            'selectedShop' => $selectedShop,
            'selectedShopId' => $selectedShopId,
            'chartData' => $chartData,
            'timeframe' => $timeframe,
            'startDate' => $dateRange['start'],
            'endDate' => $dateRange['end'],
            'activeTab' => 'charts',
        ]);
    }

    /**
     * Intelligent Analytics Engine with Weekday Profitability & Purchase Optimization (Owned Shops Only).
     */
    public function analytics(Request $request): View
    {
        $this->ensureAuthorized($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $ownedShops = $shops->filter(fn ($s) => $s->client_id !== null)->values();
        $shops = $ownedShops->isNotEmpty() ? $ownedShops : $shops;

        $selectedShopId = $request->filled('shop_id') ? (int) $request->input('shop_id') : ($shops->first()?->shop_id);
        $selectedShop = $shops->firstWhere('shop_id', $selectedShopId) ?? $shops->first();

        $analyticsResult = $this->generateAnalyticsReport($selectedShop?->shop_id);

        return view('admin.cashbook.reports.analytics', [
            'shops' => $shops,
            'selectedShop' => $selectedShop,
            'analytics' => $analyticsResult,
            'activeTab' => 'analytics',
        ]);
    }

    /**
     * Daily GL Bills & Shop Invoice Deliveries Report Page.
     */
    public function glBills(Request $request): View
    {
        $this->ensureAuthorized($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $ownedShops = $shops->filter(fn ($s) => $s->client_id !== null)->values();
        $shops = $ownedShops->isNotEmpty() ? $ownedShops : $shops;

        $selectedShopId = $request->filled('shop_id') ? (int) $request->input('shop_id') : null;
        $selectedShop = $selectedShopId ? $shops->firstWhere('shop_id', $selectedShopId) : null;

        $timeframe = (string) $request->input('timeframe', 'monthly');
        $dateRange = $this->resolveDateRange($timeframe, $request);

        if ($selectedShopId) {
            $query = ShopInvoice::query()
                ->with(['shop', 'order', 'items.product'])
                ->where('shop_id', $selectedShopId)
                ->whereBetween('business_date', [$dateRange['start'], $dateRange['end']]);

            $totalsQuery = clone $query;
            $totals = [
                'total_billed' => round((float) $totalsQuery->sum('final_total'), 2),
                'total_paid' => round((float) $totalsQuery->sum('paid_amount'), 2),
                'total_balance' => round((float) $totalsQuery->sum('balance_amount'), 2),
                'count' => $totalsQuery->count(),
            ];

            $invoices = $query->orderByDesc('business_date')
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString();
        } else {
            $totals = [
                'total_billed' => 0.00,
                'total_paid' => 0.00,
                'total_balance' => 0.00,
                'count' => 0,
            ];
            $invoices = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);
        }

        return view('admin.cashbook.reports.gl_bills', [
            'shops' => $shops,
            'selectedShop' => $selectedShop,
            'selectedShopId' => $selectedShopId,
            'invoices' => $invoices,
            'totals' => $totals,
            'timeframe' => $timeframe,
            'startDate' => $dateRange['start'],
            'endDate' => $dateRange['end'],
            'activeTab' => 'gl-bills',
        ]);
    }

    /**
     * JSON API Endpoint for dynamic filtering on Hub and Detail screens.
     */
    public function apiHubData(Request $request): JsonResponse
    {
        $this->ensureAuthorized($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $timeframe = (string) $request->input('timeframe', 'today');
        $dateRange = $this->resolveDateRange($timeframe, $request);

        $shopMetrics = $this->calculateMultiShopMetrics($shops, $dateRange['start'], $dateRange['end']);

        $totals = [
            'sales' => round($shopMetrics->sum('sales'), 2),
            'expense' => round($shopMetrics->sum('expense'), 2),
            'net' => round($shopMetrics->sum('net'), 2),
            'gl_bills' => round($shopMetrics->sum('gl_bills'), 2),
            'shops_count' => $shopMetrics->count(),
            'profitable_count' => $shopMetrics->where('net', '>', 0)->count(),
        ];

        return response()->json([
            'success' => true,
            'shopMetrics' => $shopMetrics->values(),
            'totals' => $totals,
            'startDate' => $dateRange['start'],
            'endDate' => $dateRange['end'],
            'timeframe' => $timeframe,
        ]);
    }

    /**
     * Security guard for Admin Cashbook Reports (Accessible by Main Admin, Admins, and Accounts roles).
     */
    private function ensureAuthorized(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User && (
                $user->isMainAdmin()
                || $user->hasRole('admin')
                || $user->hasRole('accounts')
                || $user->hasRole('accountant')
                || $user->hasRole('account')
                || $user->hasAnyPermission([
                    'accounting.report.view',
                    'accounting.dashboard.view',
                    'accounting.ledger.view',
                    'finance.dashboard.view',
                ])
            ),
            403
        );
    }

    /**
     * Resolve shop from slug, code, or shop_id.
     */
    private function resolveShop(int|string $shopParam): ShopLedgerProfile
    {
        $this->shopSyncService->syncAndGetProfiles();

        if (is_numeric($shopParam)) {
            $shop = ShopLedgerProfile::where('shop_id', (int) $shopParam)->first();
            if ($shop) {
                return $shop;
            }
        }

        $shop = ShopLedgerProfile::where('slug', $shopParam)
            ->orWhere('code', $shopParam)
            ->orWhere('uuid', $shopParam)
            ->first();

        return $shop ?: ShopLedgerProfile::orderBy('shop_id')->firstOrFail();
    }

    /**
     * Resolve start and end dates based on timeframe preset.
     */
    private function resolveDateRange(string $timeframe, Request $request): array
    {
        $today = today();

        return match ($timeframe) {
            'yesterday' => [
                'start' => $today->copy()->subDay()->toDateString(),
                'end' => $today->copy()->subDay()->toDateString(),
            ],
            'weekly' => [
                'start' => $today->copy()->startOfWeek()->toDateString(),
                'end' => $today->copy()->endOfWeek()->toDateString(),
            ],
            'monthly' => [
                'start' => $today->copy()->startOfMonth()->toDateString(),
                'end' => $today->copy()->endOfMonth()->toDateString(),
            ],
            'custom' => [
                'start' => $request->input('start_date', $today->toDateString()),
                'end' => $request->input('end_date', $today->toDateString()),
            ],
            default => [ // 'today' or 'daily'
                'start' => $today->toDateString(),
                'end' => $today->toDateString(),
            ],
        };
    }

    /**
     * Calculate Sales, Expense, Net P/L, and GL Bills across multiple shops.
     */
    private function calculateMultiShopMetrics(Collection $shops, string $startDate, string $endDate): Collection
    {
        if ($shops->isEmpty()) {
            return collect();
        }

        $shopIds = $shops->pluck('shop_id')->all();

        $transactions = ShopLedgerTransaction::query()
            ->whereIn('shop_id', $shopIds)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->where('status', '!=', 'void')
            ->with('entryType')
            ->get();

        return $shops->map(function (ShopLedgerProfile $shop) use ($transactions) {
            $shopTx = $transactions->where('shop_id', $shop->shop_id);

            $sales = (float) $shopTx
                ->filter(fn ($t) => $t->direction === 'income' || ($t->entryType && $t->entryType->category === 'income'))
                ->sum('amount');

            $expense = (float) $shopTx
                ->filter(fn ($t) => $t->direction === 'expense' || ($t->entryType && $t->entryType->category === 'expense'))
                ->sum('amount');

            $net = round($sales - $expense, 2);

            $glBills = (float) $shopTx
                ->filter(function ($t) {
                    $code = $t->entryType?->code ?: $t->entry_type_code;
                    return in_array($code, ['purchase_bill', 'gl_bill', 'invoice_bill'], true)
                        || str_contains(strtolower((string) $t->notes), 'invoice')
                        || $t->reference_type === 'App\Models\ShopInvoice';
                })
                ->sum('amount');

            $marginPct = $sales > 0 ? round(($net / $sales) * 100, 1) : ($net < 0 ? -100 : 0);

            return [
                'shop_id' => $shop->shop_id,
                'shop_name' => $shop->name ?: 'Shop #' . $shop->shop_id,
                'shop_code' => $shop->code ?: ('SHP-' . $shop->shop_id),
                'shop_slug' => $shop->slug ?: (string) $shop->shop_id,
                'client_id' => $shop->client_id,
                'is_client_owned' => $shop->client_id !== null,
                'sales' => round($sales, 2),
                'expense' => round($expense, 2),
                'net' => $net,
                'gl_bills' => round($glBills, 2),
                'margin_pct' => $marginPct,
                'entries_count' => $shopTx->count(),
                'status' => $net >= 0 ? 'profit' : 'loss',
            ];
        });
    }

    /**
     * Calculate itemized single shop metrics for drill-down.
     */
    private function calculateSingleShopDetail(int $shopId, string $startDate, string $endDate): array
    {
        $transactions = ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->where('status', '!=', 'void')
            ->with('entryType')
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->get();

        $sales = (float) $transactions
            ->filter(fn ($t) => $t->direction === 'income' || ($t->entryType && $t->entryType->category === 'income'))
            ->sum('amount');

        $expense = (float) $transactions
            ->filter(fn ($t) => $t->direction === 'expense' || ($t->entryType && $t->entryType->category === 'expense'))
            ->sum('amount');

        $net = round($sales - $expense, 2);

        $glBills = (float) $transactions
            ->filter(fn ($t) => in_array($t->entryType?->code ?: $t->entry_type_code, ['purchase_bill', 'gl_bill'], true) || $t->reference_type === 'App\Models\ShopInvoice')
            ->sum('amount');

        $petty = (float) $transactions
            ->filter(fn ($t) => $t->funding_source === 'petty')
            ->sum('amount');

        // Category breakdown
        $categoryBreakdown = $transactions
            ->groupBy(fn ($t) => $t->entryType?->name ?: ($t->entry_type_code ?: 'General Entry'))
            ->map(function ($group, $categoryName) {
                $first = $group->first();
                $direction = $first->direction ?: ($first->entryType?->category ?: 'expense');
                $total = round((float) $group->sum('amount'), 2);
                $count = $group->count();

                return [
                    'category' => $categoryName,
                    'direction' => $direction,
                    'amount' => $total,
                    'count' => $count,
                ];
            })
            ->sortByDesc('amount')
            ->values();

        return [
            'sales' => round($sales, 2),
            'expense' => round($expense, 2),
            'net' => $net,
            'gl_bills' => round($glBills, 2),
            'petty' => round($petty, 2),
            'margin_pct' => $sales > 0 ? round(($net / $sales) * 100, 1) : 0,
            'categories' => $categoryBreakdown,
            'transactions' => $transactions,
            'total_entries' => $transactions->count(),
        ];
    }

    /**
     * Generate Category Chart Breakdown data.
     */
    private function generateCategoryChartData(Collection $shops, string $startDate, string $endDate, ?int $selectedShopId): array
    {
        $query = ShopLedgerTransaction::query()
            ->whereBetween('business_date', [$startDate, $endDate])
            ->where('status', '!=', 'void')
            ->with('entryType');

        if ($selectedShopId) {
            $query->where('shop_id', $selectedShopId);
        } else {
            $query->whereIn('shop_id', $shops->pluck('shop_id'));
        }

        $transactions = $query->get();

        // Expense categories
        $expenseCategories = $transactions
            ->filter(fn ($t) => $t->direction === 'expense' || ($t->entryType && $t->entryType->category === 'expense'))
            ->groupBy(fn ($t) => $t->entryType?->name ?: 'Other Expense')
            ->map(fn ($g) => round((float) $g->sum('amount'), 2))
            ->sortDesc();

        // Income categories
        $incomeCategories = $transactions
            ->filter(fn ($t) => $t->direction === 'income' || ($t->entryType && $t->entryType->category === 'income'))
            ->groupBy(fn ($t) => $t->entryType?->name ?: 'Sales & Inflow')
            ->map(fn ($g) => round((float) $g->sum('amount'), 2))
            ->sortDesc();

        // Daily trend data: continuous daily sequence capped at today for month/week range
        $periodStart = Carbon::parse($startDate);
        $periodEnd = Carbon::parse($endDate);

        if ($periodEnd->isFuture()) {
            $periodEnd = today();
        }

        $dailyTrend = collect();
        $current = $periodStart->copy();

        while ($current->lte($periodEnd)) {
            $dateStr = $current->toDateString();
            $dayTx = $transactions->filter(fn ($t) => Carbon::parse($t->business_date)->toDateString() === $dateStr);

            $daySales = (float) $dayTx
                ->filter(fn ($t) => $t->direction === 'income' || ($t->entryType && $t->entryType->category === 'income'))
                ->sum('amount');

            $dayExpense = (float) $dayTx
                ->filter(fn ($t) => $t->direction === 'expense' || ($t->entryType && $t->entryType->category === 'expense'))
                ->sum('amount');

            $dailyTrend->push([
                'date' => $current->format('d M'),
                'sales' => round($daySales, 2),
                'expense' => round($dayExpense, 2),
                'net' => round($daySales - $dayExpense, 2),
            ]);

            $current->addDay();
        }

        $totalExp = max(1, (float) $expenseCategories->sum());
        $expenseCategoriesDetailed = $transactions
            ->filter(fn ($t) => $t->direction === 'expense' || ($t->entryType && $t->entryType->category === 'expense'))
            ->groupBy(fn ($t) => $t->entryType?->name ?: 'Other Expense')
            ->map(function ($group, $name) use ($totalExp) {
                $amount = (float) $group->sum('amount');
                return [
                    'name' => $name,
                    'amount' => round($amount, 2),
                    'pct' => round(($amount / $totalExp) * 100, 1),
                    'count' => $group->count(),
                    'avg' => $group->count() > 0 ? round($amount / $group->count(), 2) : 0,
                ];
            })
            ->sortByDesc('amount')
            ->values();

        return [
            'expense_categories' => [
                'labels' => $expenseCategories->keys()->values(),
                'data' => $expenseCategories->values(),
                'detailed' => $expenseCategoriesDetailed,
            ],
            'income_categories' => [
                'labels' => $incomeCategories->keys()->values(),
                'data' => $incomeCategories->values(),
            ],
            'daily_trend' => $dailyTrend->values(),
            'total_sales' => round($incomeCategories->sum(), 2),
            'total_expense' => round($expenseCategories->sum(), 2),
            'net_profit' => round($incomeCategories->sum() - $expenseCategories->sum(), 2),
        ];
    }

    /**
     * Heuristic & Algorithmic Analytics Engine.
     */
    private function generateAnalyticsReport(?int $shopId): array
    {
        if (! $shopId) {
            return [
                'weekday_analysis' => [],
                'recommendations' => [],
                'best_profit_day' => null,
                'slowest_profit_day' => null,
                'overpurchase_warnings' => [],
            ];
        }

        $historicalStart = today()->subDays(30)->toDateString();
        $historicalEnd = today()->toDateString();

        $transactions = ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$historicalStart, $historicalEnd])
            ->where('status', '!=', 'void')
            ->with('entryType')
            ->get();

        $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $weekdayData = [];

        foreach ($dayNames as $dayIndex => $dayName) {
            $dayTransactions = $transactions->filter(function ($tx) use ($dayIndex) {
                return Carbon::parse($tx->business_date)->dayOfWeekIso === ($dayIndex + 1);
            });

            $datesCount = max(1, $dayTransactions->pluck('business_date')->unique()->count());

            $totalSales = (float) $dayTransactions
                ->filter(fn ($t) => $t->direction === 'income' || ($t->entryType && $t->entryType->category === 'income'))
                ->sum('amount');

            $totalExpense = (float) $dayTransactions
                ->filter(fn ($t) => $t->direction === 'expense' || ($t->entryType && $t->entryType->category === 'expense'))
                ->sum('amount');

            $totalGLBills = (float) $dayTransactions
                ->filter(fn ($t) => in_array($t->entryType?->code ?: $t->entry_type_code, ['purchase_bill', 'gl_bill'], true) || $t->reference_type === 'App\Models\ShopInvoice')
                ->sum('amount');

            $avgSales = round($totalSales / $datesCount, 2);
            $avgExpense = round($totalExpense / $datesCount, 2);
            $avgGLBills = round($totalGLBills / $datesCount, 2);
            $avgNet = round($avgSales - $avgExpense, 2);
            $purchaseToSalesRatio = $avgSales > 0 ? round(($avgGLBills / $avgSales) * 100, 1) : 0;
            $profitMargin = $avgSales > 0 ? round(($avgNet / $avgSales) * 100, 1) : 0;

            $weekdayData[$dayName] = [
                'day' => $dayName,
                'avg_sales' => $avgSales,
                'avg_expense' => $avgExpense,
                'avg_gl_bills' => $avgGLBills,
                'avg_net' => $avgNet,
                'purchase_ratio' => $purchaseToSalesRatio,
                'margin_pct' => $profitMargin,
                'sample_days' => $datesCount,
                'profit_score' => $avgNet > 0 ? min(100, (int) ($profitMargin * 2.5)) : 0,
            ];
        }

        $weekdayCollection = collect($weekdayData);
        $bestProfitDay = $weekdayCollection->sortByDesc('avg_net')->first();
        $slowestProfitDay = $weekdayCollection->sortBy('avg_net')->first();

        $recommendations = [];
        $overpurchaseWarnings = [];

        foreach ($weekdayData as $day => $metrics) {
            if ($metrics['avg_sales'] > 500 && $metrics['purchase_ratio'] > 65) {
                $warning = [
                    'day' => $day,
                    'title' => "Reduce Purchases on {$day}s",
                    'message' => "GL procurement takes {$metrics['purchase_ratio']}% revenue ({$metrics['margin_pct']}% margin). Trim stock orders by 15-20%.",
                    'severity' => $metrics['purchase_ratio'] > 80 ? 'danger' : 'warning',
                ];
                $overpurchaseWarnings[] = $warning;
                $recommendations[] = [
                    'category' => 'Inventory Optimization',
                    'badge' => 'High Impact',
                    'badge_color' => 'rose',
                    'title' => "Trim Procurement on {$day}s",
                    'description' => "Shift bulk replenishments away from {$day}s to peak days like {$bestProfitDay['day']}.",
                ];
            }

            if ($metrics['avg_sales'] >= ($weekdayCollection->avg('avg_sales') * 1.25)) {
                $recommendations[] = [
                    'category' => 'Sales Maximization',
                    'badge' => 'Growth Opportunity',
                    'badge_color' => 'emerald',
                    'title' => "Capitalize on {$day} Peak Volume",
                    'description' => "{$day} averages ₹" . number_format($metrics['avg_sales'], 0) . " in gross sales. Ensure zero stock-outs on top moving vegetables and fruit lines.",
                ];
            }
        }

        if ($bestProfitDay && $bestProfitDay['avg_net'] > 0) {
            $recommendations[] = [
                'category' => 'Profit Hotspot',
                'badge' => 'Highest Net Profit',
                'badge_color' => 'teal',
                'title' => "{$bestProfitDay['day']} is your Most Profitable Day",
                'description' => "Generates an average net profit of ₹" . number_format($bestProfitDay['avg_net'], 0) . " with a {$bestProfitDay['margin_pct']}% profit margin.",
            ];
        }

        return [
            'weekday_analysis' => $weekdayData,
            'best_profit_day' => $bestProfitDay,
            'slowest_profit_day' => $slowestProfitDay,
            'overpurchase_warnings' => $overpurchaseWarnings,
            'recommendations' => collect($recommendations)->unique('title')->values()->all(),
            'period_sales' => round($transactions->filter(fn ($t) => $t->direction === 'income')->sum('amount'), 2),
            'period_expense' => round($transactions->filter(fn ($t) => $t->direction === 'expense')->sum('amount'), 2),
        ];
    }

    /**
     * Mobile-Friendly Single Shop Ledger view.
     */
    public function mobileLedger(Request $request, string $shopParam): View
    {
        $this->ensureAuthorized($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $shop = $this->resolveShop($shopParam);

        $timeframe = (string) $request->input('timeframe', 'today');
        $dateRange = $this->resolveDateRange($timeframe, $request);

        $metrics = $this->calculateSingleShopDetail($shop->shop_id, $dateRange['start'], $dateRange['end']);

        return view('admin.cashbook.reports.mobile_ledger', [
            'shops' => $shops,
            'currentShop' => $shop,
            'metrics' => $metrics,
            'timeframe' => $timeframe,
            'startDate' => $dateRange['start'],
            'endDate' => $dateRange['end'],
            'activeTab' => 'mobile-ledger',
        ]);
    }
}
