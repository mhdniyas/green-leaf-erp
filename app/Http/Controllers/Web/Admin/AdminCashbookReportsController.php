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
use App\Services\Reports\ShopProfitIntelligenceService;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\DailyPricePublication;
use App\Models\Product;
use App\Models\ShopDailyProductPrice;
use App\Services\Pricing\PriceBoardService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminCashbookReportsController extends Controller
{
    public function __construct(
        private readonly CashbookShopSyncService $shopSyncService,
        private readonly ShopProfitIntelligenceService $profitIntelligence,
        private readonly PriceBoardService $priceBoardService,
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
            'gl_bills_count' => (int) $shopMetrics->sum('gl_bills_count'),
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

        $mode = (string) $request->input('mode', '30day'); // '30day' or 'weekly'
        $weekOffset = (int) $request->input('week_offset', 0);

        if ($mode === 'weekly') {
            $weekStartObj = today()->startOfWeek()->addWeeks($weekOffset);
            $weekEndObj   = $weekStartObj->copy()->endOfWeek();

            $weekLabel = match (true) {
                $weekOffset === 0  => 'This Week',
                $weekOffset === -1 => 'Last Week',
                default            => abs($weekOffset) . ' Weeks Ago',
            };

            $intelligence = $selectedShop?->shop_id
                ? $this->profitIntelligence->analyse($selectedShop->shop_id, $weekStartObj->toDateString(), $weekEndObj->toDateString(), 1)
                : $this->profitIntelligence->analyse(0);
        } else {
            $weekStartObj = today()->startOfWeek();
            $weekEndObj   = today()->endOfWeek();
            $weekLabel = 'This Week';

            $intelligence = $selectedShop?->shop_id
                ? $this->profitIntelligence->analyse($selectedShop->shop_id)
                : $this->profitIntelligence->analyse(0);
        }

        return view('admin.cashbook.reports.analytics', [
            'shops'        => $shops,
            'selectedShop' => $selectedShop,
            'intelligence' => $intelligence,
            'mode'         => $mode,
            'weekOffset'   => $weekOffset,
            'weekStart'    => $weekStartObj->toDateString(),
            'weekEnd'      => $weekEndObj->toDateString(),
            'weekLabel'    => $weekLabel,
            'activeTab'    => 'analytics',
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
     * Products Marketplace & Daily Price Catalog.
     */
    public function products(Request $request): View
    {
        $this->ensureAuthorized($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();

        $selectedShopId = $request->filled('shop_id') ? (int) $request->input('shop_id') : null;
        $currentShopProfile = null;
        if ($selectedShopId) {
            $currentShopProfile = $shops->firstWhere('shop_id', $selectedShopId);
        }
        if (! $currentShopProfile) {
            $currentShopProfile = $shops->first();
        }

        $activeShop = $currentShopProfile ? Shop::find($currentShopProfile->shop_id) : null;

        $selectedDate = $request->input('date', today()->toDateString());
        $targetBusinessDate = Carbon::parse($selectedDate)->toDateString();
        $search = trim((string) $request->input('search', ''));
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;

        $isPublished = DailyPricePublication::isPublishedForDate($targetBusinessDate);

        $groupName = 'A';
        if ($activeShop) {
            $shopGroup = $this->priceBoardService->groupForShop($activeShop);
            $groupName = strtoupper(trim((string) ($shopGroup?->name ?? 'A')));
            if (! in_array($groupName, ['A', 'B', 'C'], true)) {
                $groupName = 'A';
            }
        }

        $sort = (string) $request->input('sort', 'code_asc');
        if (! in_array($sort, ['code_asc', 'price_desc', 'price_asc'], true)) {
            $sort = 'code_asc';
        }

        $productQuery = Product::query()
            ->active()
            ->with(['category']);

        if ($sort === 'price_desc') {
            $productQuery->orderBy('base_price', 'desc')->orderBy('name', 'asc');
        } elseif ($sort === 'price_asc') {
            $productQuery->orderBy('base_price', 'asc')->orderBy('name', 'asc');
        } else {
            $productQuery->ordered();
        }

        if ($categoryId) {
            $productQuery->where('category_id', $categoryId);
        }

        if ($search !== '') {
            $productQuery->where(function ($query) use ($search): void {
                $query->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        $products = $productQuery->paginate(24)->withQueryString();
        $pageProductIds = $products->getCollection()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $currentApprovals = DailyPriceApproval::query()
            ->whereDate('business_date', $targetBusinessDate)
            ->whereIn('product_id', $pageProductIds)
            ->get()
            ->keyBy('product_id');

        $previousApprovals = DailyPriceApproval::query()
            ->whereDate('business_date', '<', $targetBusinessDate)
            ->whereIn('product_id', $pageProductIds)
            ->where('status', 'approved')
            ->orderByDesc('business_date')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->first());

        $shopDailyPrices = collect();
        if ($activeShop) {
            $shopDailyPrices = ShopDailyProductPrice::query()
                ->where('shop_id', $activeShop->id)
                ->whereDate('business_date', $targetBusinessDate)
                ->whereIn('product_id', $pageProductIds)
                ->get()
                ->keyBy('product_id');
        }

        $products->setCollection(
            $products->getCollection()->map(function (Product $product) use ($currentApprovals, $previousApprovals, $shopDailyPrices, $groupName, $activeShop, $targetBusinessDate): array {
                $shopCustomPrice = $shopDailyPrices->get($product->id);
                $priceKey = 'price_' . strtolower($groupName);

                $candidatePrices = [];
                $priceUnit = $product->unit ?: 'kg';

                if ($shopCustomPrice && (float) $shopCustomPrice->selling_price > 0) {
                    $candidatePrices[] = (float) $shopCustomPrice->selling_price;
                    if ($shopCustomPrice->price_unit) {
                        $priceUnit = $shopCustomPrice->price_unit;
                    }
                }

                if ($currentApproval = $currentApprovals->get($product->id)) {
                    if ((float) ($currentApproval->$priceKey ?? 0) > 0) {
                        $candidatePrices[] = (float) $currentApproval->$priceKey;
                        if ($currentApproval->price_unit) {
                            $priceUnit = $currentApproval->price_unit;
                        }
                    }
                }

                if ($previousApproval = $previousApprovals->get($product->id)) {
                    if ((float) ($previousApproval->$priceKey ?? 0) > 0) {
                        $candidatePrices[] = (float) $previousApproval->$priceKey;
                        if ($previousApproval->price_unit) {
                            $priceUnit = $previousApproval->price_unit;
                        }
                    }
                }

                if ($activeShop) {
                    $boardPrice = $this->priceBoardService->sellingPriceFor($product, $activeShop);
                    if ((float) ($boardPrice['price'] ?? 0) > 0) {
                        $candidatePrices[] = (float) $boardPrice['price'];
                    }
                }

                if ((float) ($product->base_price ?? 0) > 0) {
                    $candidatePrices[] = (float) $product->base_price;
                }

                $sellingPrice = $candidatePrices !== [] ? max($candidatePrices) : 0.0;

                $priceDate = null;
                if ($shopCustomPrice && (float) $shopCustomPrice->selling_price > 0 && $shopCustomPrice->business_date) {
                    $priceDate = Carbon::parse($shopCustomPrice->business_date)->format('d M');
                } elseif (($curr = $currentApprovals->get($product->id)) && (float) ($curr->$priceKey ?? 0) > 0 && $curr->business_date) {
                    $priceDate = Carbon::parse($curr->business_date)->format('d M');
                } elseif (($prev = $previousApprovals->get($product->id)) && (float) ($prev->$priceKey ?? 0) > 0 && $prev->business_date) {
                    $priceDate = Carbon::parse($prev->business_date)->format('d M');
                }

                if (! $priceDate) {
                    $priceDate = Carbon::parse($targetBusinessDate)->format('d M');
                }

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'category_name' => $product->category?->name ?? 'General',
                    'unit' => $priceUnit,
                    'image' => $product->image,
                    'selling_price' => $sellingPrice,
                    'price_date' => $priceDate,
                    'group_name' => $groupName,
                    'has_custom_price' => $shopCustomPrice !== null,
                ];
            })
        );

        if ($sort === 'price_desc') {
            $products->setCollection($products->getCollection()->sortByDesc('selling_price')->values());
        } elseif ($sort === 'price_asc') {
            $products->setCollection($products->getCollection()->sortBy('selling_price')->values());
        }

        return view('admin.cashbook.reports.products', [
            'shops' => $shops,
            'currentShop' => $currentShopProfile,
            'activeShop' => $activeShop,
            'products' => $products,
            'selectedDate' => $targetBusinessDate,
            'isPublished' => $isPublished,
            'search' => $search,
            'categoryId' => $categoryId,
            'sort' => $sort,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'shopGroup' => $groupName,
            'activeTab' => 'products',
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
            'upto_yesterday' => [
                'start' => $today->copy()->startOfMonth()->toDateString(),
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

            // Identify GL-bill-only dates for this shop (pending daily shop owner entry)
            $txByDate = $shopTx->groupBy(
                fn ($tx) => Carbon::parse($tx->business_date)->toDateString()
            );

            $pendingGlOnlyDates = [];
            $activeTx = collect();
            $pendingGlBillTotal = 0.0;

            foreach ($txByDate as $dateStr => $dayTxs) {
                $hasNonGlBill = $dayTxs->contains(function ($t) {
                    $code = $t->entryType?->code ?: $t->entry_type_code;
                    return $t->reference_type !== 'App\Models\ShopInvoice'
                        && $t->reference_type !== \App\Models\ShopInvoice::class
                        && ! in_array($code, ['purchase_bill', 'gl_bill', 'invoice_bill'], true);
                });

                if (! $hasNonGlBill) {
                    $pendingGlOnlyDates[] = $dateStr;
                    $pendingGlBillTotal += (float) $dayTxs->sum('amount');
                } else {
                    $activeTx = $activeTx->concat($dayTxs);
                }
            }

            $sales = (float) $activeTx
                ->filter(fn ($t) => $t->direction === 'income' || ($t->entryType && $t->entryType->category === 'income'))
                ->sum('amount');

            $expense = (float) $activeTx
                ->filter(fn ($t) => $t->direction === 'expense' || ($t->entryType && $t->entryType->category === 'expense'))
                ->sum('amount');

            $net = round($sales - $expense, 2);

            $glBillTxs = $activeTx
                ->filter(function ($t) {
                    $code = $t->entryType?->code ?: $t->entry_type_code;
                    return in_array($code, ['purchase_bill', 'gl_bill', 'invoice_bill'], true)
                        || str_contains(strtolower((string) $t->notes), 'invoice')
                        || $t->reference_type === 'App\Models\ShopInvoice'
                        || $t->reference_type === \App\Models\ShopInvoice::class;
                });

            $glBills = (float) $glBillTxs->sum('amount');
            $glBillsCount = (int) $glBillTxs->count();

            $marginPct = $sales > 0 ? round(($net / $sales) * 100, 1) : 0;

            $status = $activeTx->isEmpty() && count($pendingGlOnlyDates) > 0
                ? 'pending'
                : ($net >= 0 ? 'profit' : 'loss');

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
                'gl_bills_count' => $glBillsCount,
                'pending_gl_bills' => round($pendingGlBillTotal, 2),
                'margin_pct' => $marginPct,
                'entries_count' => $activeTx->count(),
                'pending_days_count' => count($pendingGlOnlyDates),
                'pending_dates' => $pendingGlOnlyDates,
                'status' => $status,
            ];
        });
    }

    /**
     * Calculate itemized single shop metrics for drill-down.
     */
    private function calculateSingleShopDetail(int $shopId, string $startDate, string $endDate, bool $skipGlOnlyDays = false): array
    {
        $allTransactions = ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->where('status', '!=', 'void')
            ->with('entryType')
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->get();

        $txByDate = $allTransactions->groupBy(
            fn ($tx) => Carbon::parse($tx->business_date)->toDateString()
        );

        $pendingGlOnlyDates = [];
        $activeTransactions = collect();

        foreach ($txByDate as $dateStr => $dayTxs) {
            $hasNonGlBill = $dayTxs->contains(function ($t) {
                $code = $t->entryType?->code ?: $t->entry_type_code;
                return $t->reference_type !== 'App\Models\ShopInvoice'
                    && $t->reference_type !== \App\Models\ShopInvoice::class
                    && ! in_array($code, ['purchase_bill', 'gl_bill', 'invoice_bill'], true);
            });

            if (! $hasNonGlBill) {
                $pendingGlOnlyDates[] = $dateStr;
            } else {
                $activeTransactions = $activeTransactions->concat($dayTxs);
            }
        }

        $transactions = $skipGlOnlyDays ? $activeTransactions : $allTransactions;

        $sales = (float) $activeTransactions
            ->filter(fn ($t) => $t->direction === 'income' || ($t->entryType && $t->entryType->category === 'income'))
            ->sum('amount');

        $expense = (float) $activeTransactions
            ->filter(fn ($t) => $t->direction === 'expense' || ($t->entryType && $t->entryType->category === 'expense'))
            ->sum('amount');

        $net = round($sales - $expense, 2);

        // Total GL bills for the period (including invoices on days pending sales submission)
        $glBillTxs = $transactions
            ->filter(fn ($t) => in_array($t->entryType?->code ?: $t->entry_type_code, ['purchase_bill', 'gl_bill', 'invoice_bill'], true) || $t->reference_type === 'App\Models\ShopInvoice' || $t->reference_type === \App\Models\ShopInvoice::class);

        $glBills = (float) $glBillTxs->sum('amount');
        $glBillsCount = (int) $glBillTxs->count();

        $petty = (float) $activeTransactions
            ->filter(fn ($t) => $t->funding_source === 'petty')
            ->sum('amount');

        // Detailed category breakdown with itemized list for each category
        $categoryBreakdown = $transactions
            ->groupBy(fn ($t) => $t->entryType?->name ?: ($t->entry_type_code ?: 'General Entry'))
            ->map(function ($group, $categoryName) {
                $first = $group->first();
                $direction = $first->direction ?: ($first->entryType?->category ?: 'expense');
                $total = round((float) $group->sum('amount'), 2);
                $count = $group->count();
                $isGlBill = in_array($first->entryType?->code ?: $first->entry_type_code, ['purchase_bill', 'gl_bill', 'invoice_bill'], true)
                    || $first->reference_type === 'App\Models\ShopInvoice'
                    || $first->reference_type === \App\Models\ShopInvoice::class;

                return [
                    'category' => $categoryName,
                    'direction' => $direction,
                    'amount' => $total,
                    'count' => $count,
                    'is_gl_bill' => $isGlBill,
                    'items' => $group->map(function ($t) {
                        return [
                            'id' => $t->id,
                            'amount' => (float) $t->amount,
                            'direction' => $t->direction ?: ($t->entryType?->category ?: 'expense'),
                            'business_date' => Carbon::parse($t->business_date)->toDateString(),
                            'formatted_date' => Carbon::parse($t->business_date)->format('d M Y'),
                            'category_name' => $t->entryType?->name ?: ($t->entry_type_code ?: 'General Entry'),
                            'notes' => $t->notes,
                            'status' => $t->status,
                            'funding_source' => $t->funding_source,
                            'reference_type' => $t->reference_type,
                            'reference_id' => $t->reference_id,
                            'is_gl_bill' => in_array($t->entryType?->code ?: $t->entry_type_code, ['purchase_bill', 'gl_bill', 'invoice_bill'], true)
                                || $t->reference_type === 'App\Models\ShopInvoice'
                                || $t->reference_type === \App\Models\ShopInvoice::class,
                        ];
                    })->values()->all(),
                ];
            })
            ->sortByDesc('amount')
            ->values();

        return [
            'sales' => round($sales, 2),
            'expense' => round($expense, 2),
            'net' => $net,
            'gl_bills' => round($glBills, 2),
            'gl_bills_count' => $glBillsCount,
            'petty' => round($petty, 2),
            'margin_pct' => $sales > 0 ? round(($net / $sales) * 100, 1) : 0,
            'categories' => $categoryBreakdown,
            'transactions' => $transactions,
            'total_entries' => $transactions->count(),
            'pending_days_count' => count($pendingGlOnlyDates),
            'pending_dates' => $pendingGlOnlyDates,
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

        // Identify dates that have ONLY GL bills (pending shop daily entries)
        $transactionsByDate = $transactions->groupBy(
            fn ($tx) => Carbon::parse($tx->business_date)->toDateString()
        );

        $pendingGlOnlyDates = [];
        $activeTransactions = collect();

        foreach ($transactionsByDate as $dateStr => $dayTxs) {
            $hasNonGlBill = $dayTxs->contains(function ($tx) {
                return $tx->reference_type !== 'App\Models\ShopInvoice'
                    && $tx->reference_type !== \App\Models\ShopInvoice::class;
            });

            if (! $hasNonGlBill) {
                $pendingGlOnlyDates[] = $dateStr;
            } else {
                $activeTransactions = $activeTransactions->concat($dayTxs);
            }
        }

        // Expense categories
        $expenseCategories = $activeTransactions
            ->filter(fn ($t) => $t->direction === 'expense' || ($t->entryType && $t->entryType->category === 'expense'))
            ->groupBy(fn ($t) => $t->entryType?->name ?: 'Other Expense')
            ->map(fn ($g) => round((float) $g->sum('amount'), 2))
            ->sortDesc();

        // Income categories
        $incomeCategories = $activeTransactions
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
            // Skip days with only GL bills from continuous trend curve
            if (in_array($dateStr, $pendingGlOnlyDates, true)) {
                $current->addDay();
                continue;
            }

            $dayTx = $activeTransactions->filter(fn ($t) => Carbon::parse($t->business_date)->toDateString() === $dateStr);

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
        $expenseCategoriesDetailed = $activeTransactions
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
            'pending_days_count' => count($pendingGlOnlyDates),
            'pending_dates' => $pendingGlOnlyDates,
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
        $skipGlOnlyDays = $request->boolean('skip_gl_only_days');

        $metrics = $this->calculateSingleShopDetail($shop->shop_id, $dateRange['start'], $dateRange['end'], $skipGlOnlyDays);

        return view('admin.cashbook.reports.mobile_ledger', [
            'shops' => $shops,
            'currentShop' => $shop,
            'metrics' => $metrics,
            'timeframe' => $timeframe,
            'startDate' => $dateRange['start'],
            'endDate' => $dateRange['end'],
            'skipGlOnlyDays' => $skipGlOnlyDays,
            'activeTab' => 'mobile-ledger',
        ]);
    }
}
