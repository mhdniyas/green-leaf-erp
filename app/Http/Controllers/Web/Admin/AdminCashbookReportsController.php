<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\DTOs\Purchasing\GoodsReceivedData;
use App\Http\Controllers\Controller;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\DailyPricePublication;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseProductFilter;
use App\Models\Shop;
use App\Models\ShopDailyProductPrice;
use App\Models\ShopInvoice;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Pricing\PriceBoardService;
use App\Services\Purchasing\AdvanceInventoryService;
use App\Services\Purchasing\AdvanceReceiveReconciliationService;
use App\Services\Purchasing\AutoAdvanceClearExecutionService;
use App\Services\Purchasing\AutoAdvanceClearPlanningService;
use App\Services\Purchasing\GoodsReceivedService;
use App\Services\Purchasing\WarehouseReceiptReadScope;
use App\Services\Purchasing\WarehouseReceiptStateResolver;
use App\Services\Reports\ShopProfitIntelligenceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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

        $scope = strtolower((string) $request->input('scope', 'owned'));
        if (! in_array($scope, ['owned', 'own', 'direct', 'all'], true)) {
            $scope = 'owned';
        }
        $normalizedScope = match ($scope) {
            'direct' => 'direct',
            'all' => 'all',
            default => 'owned',
        };

        $shopMetrics = $this->calculateMultiShopMetrics($shops, $dateRange['start'], $dateRange['end']);

        $filteredMetrics = match ($normalizedScope) {
            'owned' => $shopMetrics->filter(fn ($s) => $s['is_client_owned'])->values(),
            'direct' => $shopMetrics->filter(fn ($s) => ! $s['is_client_owned'])->values(),
            default => $shopMetrics->values(),
        };

        $totals = [
            'sales' => round((float) $filteredMetrics->sum('sales'), 2),
            'expense' => round((float) $filteredMetrics->sum('expense'), 2),
            'net' => round((float) $filteredMetrics->sum('net'), 2),
            'gl_bills' => round((float) $filteredMetrics->sum('gl_bills'), 2),
            'gl_bills_count' => (int) $filteredMetrics->sum('gl_bills_count'),
        ];

        return view('admin.cashbook.reports.hub', [
            'shops' => $shops,
            'totals' => $totals,
            'shopMetrics' => $shopMetrics,
            'timeframe' => $timeframe,
            'startDate' => $dateRange['start'],
            'endDate' => $dateRange['end'],
            'scope' => $normalizedScope,
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
            $weekEndObj = $weekStartObj->copy()->endOfWeek();

            $weekLabel = match (true) {
                $weekOffset === 0 => 'This Week',
                $weekOffset === -1 => 'Last Week',
                default => abs($weekOffset).' Weeks Ago',
            };

            $intelligence = $selectedShop?->shop_id
                ? $this->profitIntelligence->analyse($selectedShop->shop_id, $weekStartObj->toDateString(), $weekEndObj->toDateString(), 1)
                : $this->profitIntelligence->analyse(0);
        } else {
            $weekStartObj = today()->startOfWeek();
            $weekEndObj = today()->endOfWeek();
            $weekLabel = 'This Week';

            $intelligence = $selectedShop?->shop_id
                ? $this->profitIntelligence->analyse($selectedShop->shop_id)
                : $this->profitIntelligence->analyse(0);
        }

        return view('admin.cashbook.reports.analytics', [
            'shops' => $shops,
            'selectedShop' => $selectedShop,
            'intelligence' => $intelligence,
            'mode' => $mode,
            'weekOffset' => $weekOffset,
            'weekStart' => $weekStartObj->toDateString(),
            'weekEnd' => $weekEndObj->toDateString(),
            'weekLabel' => $weekLabel,
            'activeTab' => 'analytics',
        ]);
    }

    /**
     * Daily GL Bills & Shop Invoice Deliveries Report Page.
     */
    public function glBills(Request $request): View
    {
        $report = $this->glBillsReport($request, true, false);

        return view('admin.cashbook.reports.gl_bills', [
            ...$report,
            'activeTab' => 'gl-bills',
        ]);
    }

    public function glBillsExportCsv(Request $request): StreamedResponse
    {
        $report = $this->glBillsReport($request, false, true);
        $rows = $this->glBillsExportRows($report);
        $filename = $this->glBillsFilename($report, 'csv');

        return response()->streamDownload(function () use ($rows): void {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                return;
            }

            foreach ($rows as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function glBillsExportPdf(Request $request): mixed
    {
        $report = $this->glBillsReport($request, false, true);

        $viewData = [
            ...$report,
            'title' => 'GL Bills Export',
            'exportRows' => $this->glBillsExportRows($report),
        ];

        if ($request->boolean('download', false) || $request->input('download') === '1') {
            return Pdf::loadView('admin.cashbook.reports.gl_bills_pdf_download', $viewData)
                ->setPaper('a4', 'portrait')
                ->setOption(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true])
                ->download($this->glBillsFilename($report, 'pdf'));
        }

        return view('admin.cashbook.reports.gl_bills_pdf', $viewData);
    }

    /**
     * @return array{
     *     shops: Collection<int, mixed>,
     *     scopedShops: Collection<int, mixed>,
     *     scope: string,
     *     selectedShop: mixed,
     *     selectedShopId: int|null,
     *     exportScopeLabel: string,
     *     productFilters: Collection<int, PurchaseProductFilter>,
     *     selectedProductFilter: PurchaseProductFilter|null,
     *     selectedProductFilterUuid: string,
     *     filterProductIds: array<int, int>,
     *     invoices: Collection<int, ShopInvoice>|LengthAwarePaginator,
     *     totals: array{total_billed: float, total_paid: float, total_balance: float, count: int},
     *     timeframe: string,
     *     startDate: string,
     *     endDate: string
     * }
     */
    private function glBillsReport(Request $request, bool $paginate, bool $forExport): array
    {
        $this->ensureAuthorized($request);

        $scope = strtolower((string) $request->input('scope', 'all'));
        if (! in_array($scope, ['all', 'owned', 'own', 'direct'], true)) {
            $scope = 'all';
        }
        $normalizedScope = match ($scope) {
            'owned', 'own' => 'owned',
            'direct' => 'direct',
            default => 'all',
        };

        $allShops = Shop::query()->orderBy('name')->get();
        $scopedShops = match ($normalizedScope) {
            'owned' => $allShops->filter(fn ($s) => $s->client_id !== null)->values(),
            'direct' => $allShops->filter(fn ($s) => $s->client_id === null)->values(),
            default => $allShops,
        };

        $selectedShopId = $request->filled('shop_id') ? (int) $request->input('shop_id') : null;
        $selectedShop = $selectedShopId ? ($scopedShops->firstWhere('id', $selectedShopId) ?? $scopedShops->firstWhere('shop_id', $selectedShopId)) : null;

        // If selected outlet is not in the active scope, reset it
        if ($selectedShopId && ! $selectedShop) {
            $selectedShopId = null;
        }

        $shopIds = $selectedShopId
            ? ($selectedShop ? [(int) ($selectedShop->shop_id ?? $selectedShop->id)] : [$selectedShopId])
            : ($normalizedScope !== 'all' ? $scopedShops->pluck('id')->all() : []);

        $timeframe = (string) $request->input('timeframe', 'monthly');
        $dateRange = $this->resolveDateRange($timeframe, $request);
        $productFilters = PurchaseProductFilter::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedProductFilterUuid = (string) $request->input('product_filter', '');
        $selectedProductFilter = $selectedProductFilterUuid === ''
            ? null
            : ($productFilters->firstWhere('uuid', $selectedProductFilterUuid)
                ?? PurchaseProductFilter::query()->where('uuid', $selectedProductFilterUuid)->first());

        $filterProductIds = $selectedProductFilter
            ? $selectedProductFilter->getProductIds()
            : [];

        $query = $this->glBillsQuery($shopIds, $dateRange['start'], $dateRange['end'], $selectedProductFilter, $filterProductIds);
        $totals = $this->glBillsTotalsFromQuery($query, $filterProductIds);

        $invoices = $paginate
            ? $query->paginate(15)->withQueryString()
            : $query->get();
        $this->annotateGlBillsInvoices($paginate ? $invoices->getCollection() : $invoices, $filterProductIds);

        $exportScopeLabel = $selectedShop?->name ?: match ($normalizedScope) {
            'owned' => 'Own Outlets',
            'direct' => 'Direct Outlets',
            default => 'All Outlets',
        };

        return [
            'shops' => $scopedShops,
            'scopedShops' => $scopedShops,
            'scope' => $normalizedScope,
            'selectedShop' => $selectedShop,
            'selectedShopId' => $selectedShopId,
            'exportScopeLabel' => $exportScopeLabel,
            'productFilters' => $productFilters,
            'selectedProductFilter' => $selectedProductFilter,
            'selectedProductFilterUuid' => $selectedProductFilterUuid,
            'filterProductIds' => $filterProductIds,
            'invoices' => $invoices,
            'totals' => $totals,
            'timeframe' => $timeframe,
            'startDate' => $dateRange['start'],
            'endDate' => $dateRange['end'],
        ];
    }

    /**
     * @param  array<int, int>  $shopIds
     * @param  array<int, int>  $filterProductIds
     */
    private function glBillsQuery(
        array $shopIds,
        string $startDate,
        string $endDate,
        ?PurchaseProductFilter $selectedProductFilter,
        array $filterProductIds
    ): Builder {
        $itemsEager = $filterProductIds === []
            ? ['shop', 'order', 'items.product']
            : [
                'shop',
                'order',
                'items' => fn ($query) => $query->whereIn('product_id', $filterProductIds)->with('product'),
            ];

        $query = ShopInvoice::query()
            ->with($itemsEager)
            ->when($shopIds !== [], fn (Builder $query): Builder => $query->whereIn('shop_id', $shopIds))
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate);

        if ($selectedProductFilter && $filterProductIds === []) {
            $query->whereRaw('1 = 0');
        }

        if ($selectedProductFilter && $filterProductIds !== []) {
            $filterId = (int) $selectedProductFilter->id;
            $query->whereExists(function ($subQuery) use ($filterId): void {
                $subQuery->selectRaw('1')
                    ->from('shop_invoice_items')
                    ->join('purchase_product_filter_items', 'purchase_product_filter_items.product_id', '=', 'shop_invoice_items.product_id')
                    ->whereColumn('shop_invoice_items.shop_invoice_id', 'shop_invoices.id')
                    ->where('purchase_product_filter_items.filter_id', $filterId);
            });
        }

        return $query->orderByDesc('business_date')->orderByDesc('id');
    }

    /**
     * @param  Collection<int, ShopInvoice>  $invoices
     * @param  array<int, int>  $filterProductIds
     */
    private function annotateGlBillsInvoices(Collection $invoices, array $filterProductIds): void
    {
        foreach ($invoices as $invoice) {
            $invoice->filtered_display_total = $filterProductIds === []
                ? null
                : $invoice->items->sum(fn ($item): float => $this->glBillsItemTotal($item));
        }
    }

    private function glBillsItemTotal(mixed $item): float
    {
        return (float) ($item->final_line_total ?? (
            ((float) ($item->delivered_price_quantity ?? $item->price_quantity ?? $item->delivered_qty ?? 0))
            * ((float) ($item->unit_price ?? 0))
        ));
    }

    /**
     * @param  Collection<int, ShopInvoice>  $invoices
     * @param  array<int, int>  $filterProductIds
     * @return array{total_billed: float, total_paid: float, total_balance: float, count: int}
     */
    private function glBillsTotals(Collection $invoices, array $filterProductIds): array
    {
        return [
            'total_billed' => round((float) $invoices->sum(fn (ShopInvoice $invoice): float => $filterProductIds === [] ? (float) $invoice->final_total : (float) $invoice->filtered_display_total), 2),
            'total_paid' => round((float) $invoices->sum('paid_amount'), 2),
            'total_balance' => round((float) $invoices->sum('balance_amount'), 2),
            'count' => $invoices->count(),
        ];
    }

    /**
     * @return array{total_billed: float, total_paid: float, total_balance: float, count: int}
     */
    private function emptyGlBillsTotals(): array
    {
        return [
            'total_billed' => 0.00,
            'total_paid' => 0.00,
            'total_balance' => 0.00,
            'count' => 0,
        ];
    }

    /**
     * @param  array<int, int>  $filterProductIds
     * @return array{total_billed: float, total_paid: float, total_balance: float, count: int}
     */
    private function glBillsTotalsFromQuery(Builder $query, array $filterProductIds): array
    {
        if ($filterProductIds === []) {
            return [
                'total_billed' => round((float) (clone $query)->reorder()->sum('final_total'), 2),
                'total_paid' => round((float) (clone $query)->reorder()->sum('paid_amount'), 2),
                'total_balance' => round((float) (clone $query)->reorder()->sum('balance_amount'), 2),
                'count' => (int) (clone $query)->reorder()->count(),
            ];
        }

        $totalBilled = (clone $query)
            ->reorder()
            ->join('shop_invoice_items', 'shop_invoice_items.shop_invoice_id', '=', 'shop_invoices.id')
            ->whereIn('shop_invoice_items.product_id', $filterProductIds)
            ->sum(DB::raw('COALESCE(shop_invoice_items.final_line_total, COALESCE(shop_invoice_items.delivered_price_quantity, shop_invoice_items.price_quantity, shop_invoice_items.delivered_qty, 0) * COALESCE(shop_invoice_items.unit_price, 0))'));

        return [
            'total_billed' => round((float) $totalBilled, 2),
            'total_paid' => round((float) (clone $query)->reorder()->sum('paid_amount'), 2),
            'total_balance' => round((float) (clone $query)->reorder()->sum('balance_amount'), 2),
            'count' => (int) (clone $query)->reorder()->count(),
        ];
    }

    /**
     * @param  array{invoices: Collection<int, ShopInvoice>|LengthAwarePaginator, totals: array<string, float|int>, selectedShop: mixed, selectedProductFilter: PurchaseProductFilter|null, exportScopeLabel: string, startDate: string, endDate: string}  $report
     * @return array<int, array<int, float|int|string|null>>
     */
    private function glBillsExportRows(array $report): array
    {
        $rows = [
            ['GL Bills Export'],
            ['Shop', $report['exportScopeLabel']],
            ['Period', $report['startDate'].' to '.$report['endDate']],
            ['Product Filter', $report['selectedProductFilter']?->name ?: 'All Products'],
            [],
            ['Total Billed', $report['totals']['total_billed']],
            ['Paid Amount', $report['totals']['total_paid']],
            ['Balance Due', $report['totals']['total_balance']],
            ['Invoice Count', $report['totals']['count']],
            [],
            ['Date', 'Invoice Number', 'Shop', 'Product Filter Total', 'Invoice Total', 'Paid Amount', 'Balance Due', 'Status'],
        ];

        foreach ($report['invoices'] as $invoice) {
            $rows[] = [
                $invoice->business_date?->format('Y-m-d'),
                $invoice->invoice_number,
                $invoice->shop?->name ?: 'Shop #'.$invoice->shop_id,
                $invoice->filtered_display_total === null ? '' : round((float) $invoice->filtered_display_total, 2),
                round((float) $invoice->final_total, 2),
                round((float) $invoice->paid_amount, 2),
                round((float) $invoice->balance_amount, 2),
                $invoice->status ?: $invoice->payment_status,
            ];
        }

        return $rows;
    }

    /**
     * @param  array{selectedShop: mixed, startDate: string, endDate: string}  $report
     */
    private function glBillsFilename(array $report, string $extension): string
    {
        $shopCode = $report['selectedShop']?->code ?: 'all-shops';

        return 'gl-bills-'.$shopCode.'-'.$report['startDate'].'-to-'.$report['endDate'].'.'.$extension;
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
            ->with(['updatedBy:id,name', 'approvedBy:id,name'])
            ->whereDate('business_date', $targetBusinessDate)
            ->whereIn('product_id', $pageProductIds)
            ->get()
            ->keyBy('product_id');

        $previousApprovals = DailyPriceApproval::query()
            ->with(['updatedBy:id,name', 'approvedBy:id,name'])
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
                ->with(['createdBy:id,name', 'approvedBy:id,name'])
                ->where('shop_id', $activeShop->id)
                ->whereDate('business_date', $targetBusinessDate)
                ->whereIn('product_id', $pageProductIds)
                ->get()
                ->keyBy('product_id');
        }

        $products->setCollection(
            $products->getCollection()->map(function (Product $product) use ($currentApprovals, $previousApprovals, $shopDailyPrices, $groupName, $activeShop, $targetBusinessDate): array {
                $shopCustomPrice = $shopDailyPrices->get($product->id);
                $priceKey = 'price_'.strtolower($groupName);

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
                $updatedByName = null;
                if ($shopCustomPrice && (float) $shopCustomPrice->selling_price > 0 && $shopCustomPrice->business_date) {
                    $priceDate = Carbon::parse($shopCustomPrice->business_date)->format('d M');
                    $updatedByName = $shopCustomPrice->approvedBy?->name ?? $shopCustomPrice->createdBy?->name;
                } elseif (($curr = $currentApprovals->get($product->id)) && (float) ($curr->$priceKey ?? 0) > 0 && $curr->business_date) {
                    $priceDate = Carbon::parse($curr->business_date)->format('d M');
                    $updatedByName = $curr->updatedBy?->name ?? $curr->approvedBy?->name;
                } elseif (($prev = $previousApprovals->get($product->id)) && (float) ($prev->$priceKey ?? 0) > 0 && $prev->business_date) {
                    $priceDate = Carbon::parse($prev->business_date)->format('d M');
                    $updatedByName = $prev->updatedBy?->name ?? $prev->approvedBy?->name;
                }

                if (! $updatedByName && ($curr = $currentApprovals->get($product->id))) {
                    $updatedByName = $curr->updatedBy?->name ?? $curr->approvedBy?->name;
                }

                if (! $updatedByName && ($prev = $previousApprovals->get($product->id))) {
                    $updatedByName = $prev->updatedBy?->name ?? $prev->approvedBy?->name;
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
                    'updated_by_name' => $updatedByName,
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

        $scope = strtolower((string) $request->input('scope', 'all'));
        if (! in_array($scope, ['owned', 'own', 'direct', 'all'], true)) {
            $scope = 'all';
        }
        $normalizedScope = match ($scope) {
            'owned', 'own' => 'owned',
            'direct' => 'direct',
            default => 'all',
        };

        $shopMetrics = $this->calculateMultiShopMetrics($shops, $dateRange['start'], $dateRange['end']);

        $filteredMetrics = match ($normalizedScope) {
            'owned' => $shopMetrics->filter(fn ($s) => $s['is_client_owned'])->values(),
            'direct' => $shopMetrics->filter(fn ($s) => ! $s['is_client_owned'])->values(),
            default => $shopMetrics->values(),
        };

        $totals = [
            'sales' => round((float) $filteredMetrics->sum('sales'), 2),
            'expense' => round((float) $filteredMetrics->sum('expense'), 2),
            'net' => round((float) $filteredMetrics->sum('net'), 2),
            'gl_bills' => round((float) $filteredMetrics->sum('gl_bills'), 2),
            'gl_bills_count' => (int) $filteredMetrics->sum('gl_bills_count'),
            'shops_count' => $filteredMetrics->count(),
            'profitable_count' => $filteredMetrics->where('net', '>', 0)->count(),
        ];

        return response()->json([
            'success' => true,
            'shopMetrics' => $shopMetrics->values(),
            'totals' => $totals,
            'startDate' => $dateRange['start'],
            'endDate' => $dateRange['end'],
            'timeframe' => $timeframe,
            'scope' => $normalizedScope,
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

        $invoicesByShop = ShopInvoice::query()
            ->whereIn('shop_id', $shopIds)
            ->where('status', '!=', 'cancelled')
            ->where('final_total', '>', 0)
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->selectRaw('shop_id, COUNT(*) as bill_count, SUM(final_total) as total_gl')
            ->groupBy('shop_id')
            ->get()
            ->keyBy('shop_id');

        return $shops->map(function (ShopLedgerProfile $shop) use ($transactions, $invoicesByShop) {
            $isClientOwned = $shop->client_id !== null;
            $shopTx = $transactions->where('shop_id', $shop->shop_id);

            $pendingGlOnlyDates = [];
            $activeTx = collect();
            $pendingGlBillTotal = 0.0;

            if ($isClientOwned) {
                // For client-owned shops, identify GL-bill-only dates (pending daily shop owner entry)
                $txByDate = $shopTx->groupBy(
                    fn ($tx) => Carbon::parse($tx->business_date)->toDateString()
                );

                foreach ($txByDate as $dateStr => $dayTxs) {
                    $hasNonGlBill = $dayTxs->contains(function ($t) {
                        $code = $t->entryType?->code ?: $t->entry_type_code;

                        return $t->reference_type !== 'App\Models\ShopInvoice'
                            && $t->reference_type !== ShopInvoice::class
                            && ! in_array($code, ['purchase_bill', 'gl_bill', 'invoice_bill'], true);
                    });

                    if (! $hasNonGlBill) {
                        $pendingGlOnlyDates[] = $dateStr;
                        $pendingGlBillTotal += (float) $dayTxs->sum('amount');
                    } else {
                        $activeTx = $activeTx->concat($dayTxs);
                    }
                }
            } else {
                // Direct buyer shops do not have retail cashbook entries; their transactions are direct
                $activeTx = $shopTx;
            }

            $sales = (float) $activeTx
                ->filter(fn ($t) => $t->direction === 'income' || ($t->entryType && $t->entryType->category === 'income'))
                ->sum('amount');

            $expense = (float) $activeTx
                ->filter(fn ($t) => $t->direction === 'expense' || ($t->entryType && $t->entryType->category === 'expense'))
                ->sum('amount');

            $net = round($sales - $expense, 2);

            // GL bills from ShopInvoice canonical source
            $invData = $invoicesByShop->get($shop->shop_id);
            $glBills = $invData ? (float) $invData->total_gl : 0.0;
            $glBillsCount = $invData ? (int) $invData->bill_count : 0;

            $marginPct = $sales > 0 ? round(($net / $sales) * 100, 1) : 0;

            $status = ($isClientOwned && $activeTx->isEmpty() && count($pendingGlOnlyDates) > 0)
                ? 'pending'
                : ($net >= 0 ? 'profit' : 'loss');

            return [
                'shop_id' => $shop->shop_id,
                'shop_name' => $shop->name ?: 'Shop #'.$shop->shop_id,
                'shop_code' => $shop->code ?: ('SHP-'.$shop->shop_id),
                'shop_slug' => $shop->slug ?: (string) $shop->shop_id,
                'client_id' => $shop->client_id,
                'is_client_owned' => $isClientOwned,
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
        $shopProfile = ShopLedgerProfile::where('shop_id', $shopId)->first();
        $isClientOwned = $shopProfile?->client_id !== null;

        $allTransactions = ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->where('status', '!=', 'void')
            ->with('entryType')
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->get();

        $pendingGlOnlyDates = [];
        $activeTransactions = collect();

        if ($isClientOwned) {
            $txByDate = $allTransactions->groupBy(
                fn ($tx) => Carbon::parse($tx->business_date)->toDateString()
            );

            foreach ($txByDate as $dateStr => $dayTxs) {
                $hasNonGlBill = $dayTxs->contains(function ($t) {
                    $code = $t->entryType?->code ?: $t->entry_type_code;

                    return $t->reference_type !== 'App\Models\ShopInvoice'
                        && $t->reference_type !== ShopInvoice::class
                        && ! in_array($code, ['purchase_bill', 'gl_bill', 'invoice_bill'], true);
                });

                if (! $hasNonGlBill) {
                    $pendingGlOnlyDates[] = $dateStr;
                } else {
                    $activeTransactions = $activeTransactions->concat($dayTxs);
                }
            }
        } else {
            $activeTransactions = $allTransactions;
        }

        $transactions = $skipGlOnlyDays ? $activeTransactions : $allTransactions;

        $sales = (float) $activeTransactions
            ->filter(fn ($t) => $t->direction === 'income' || ($t->entryType && $t->entryType->category === 'income'))
            ->sum('amount');

        $expense = (float) $activeTransactions
            ->filter(fn ($t) => $t->direction === 'expense' || ($t->entryType && $t->entryType->category === 'expense'))
            ->sum('amount');

        $net = round($sales - $expense, 2);

        // Total GL bills from canonical ShopInvoice source
        $invQuery = ShopInvoice::query()
            ->where('shop_id', $shopId)
            ->where('status', '!=', 'cancelled')
            ->where('final_total', '>', 0)
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate);
        $glBills = (float) (clone $invQuery)->sum('final_total');
        $glBillsCount = (int) (clone $invQuery)->count();

        $petty = (float) $activeTransactions
            ->filter(fn ($t) => $t->funding_source === 'petty')
            ->sum('amount');

        $settledAmount = (float) $allTransactions
            ->filter(fn ($t) => $t->entryType?->code === 'shop_paid_company')
            ->sum('amount');

        // Filter out system settlement transfers for category breakdown (include rule child expense entries)
        $userCategoriesTxs = $transactions->filter(function ($t) {
            $code = $t->entryType?->code ?: $t->entry_type_code;
            if (in_array($code, ['sales_company', 'shop_paid_company'], true)) {
                return false;
            }
            if (in_array($t->direction, ['settlement', 'transfer'], true)) {
                return false;
            }

            return true;
        });

        // Detailed category breakdown with itemized list for each category
        $categoryBreakdown = $userCategoriesTxs
            ->groupBy(function ($t) {
                $isGlBill = in_array($t->entryType?->code ?: $t->entry_type_code, ['purchase_bill', 'gl_bill', 'invoice_bill'], true)
                    || $t->reference_type === 'App\Models\ShopInvoice'
                    || $t->reference_type === ShopInvoice::class;

                $name = $isGlBill ? 'GL Bill' : ($t->entryType?->name ?: ($t->entry_type_code ?: 'General Entry'));
                $dir = $isGlBill ? 'expense' : ($t->entryType?->category ?: ($t->direction ?: 'expense'));

                return $name.'___'.$dir;
            })
            ->map(function ($group, $key) {
                $parts = explode('___', $key);
                $categoryName = $parts[0];
                $direction = $parts[1] ?? 'expense';

                $first = $group->first();
                $total = round((float) $group->sum('amount'), 2);
                $count = $group->count();
                $isGlBill = in_array($first->entryType?->code ?: $first->entry_type_code, ['purchase_bill', 'gl_bill', 'invoice_bill'], true)
                    || $first->reference_type === 'App\Models\ShopInvoice'
                    || $first->reference_type === ShopInvoice::class;

                return [
                    'category_key' => $key,
                    'category' => $categoryName,
                    'direction' => $direction,
                    'amount' => $total,
                    'count' => $count,
                    'is_gl_bill' => $isGlBill,
                    'items' => $group->map(function ($t) use ($direction) {
                        return [
                            'id' => $t->id,
                            'amount' => (float) $t->amount,
                            'direction' => $t->direction ?: ($t->entryType?->category ?: $direction),
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
                                || $t->reference_type === ShopInvoice::class,
                        ];
                    })->values()->all(),
                ];
            })
            ->sort(function ($a, $b) {
                // 1. GL Bill first
                if ($a['is_gl_bill']) {
                    return -1;
                }
                if ($b['is_gl_bill']) {
                    return 1;
                }

                // 2. Expenses next (sorted by amount desc)
                if ($a['direction'] === 'expense' && $b['direction'] !== 'expense') {
                    return -1;
                }
                if ($a['direction'] !== 'expense' && $b['direction'] === 'expense') {
                    return 1;
                }

                // 3. Amount desc within same direction group
                return $b['amount'] <=> $a['amount'];
            })
            ->values();

        return [
            'sales' => round($sales, 2),
            'expense' => round($expense, 2),
            'net' => $net,
            'gl_bills' => round($glBills, 2),
            'gl_bills_count' => $glBillsCount,
            'gl_bills_pct' => $sales > 0 ? round(($glBills / $sales) * 100, 1) : 0,
            'petty' => round($petty, 2),
            'settled_amount' => round($settledAmount, 2),
            'margin_pct' => $sales > 0 ? round(($net / $sales) * 100, 1) : 0,
            'categories' => $categoryBreakdown,
            'transactions' => $transactions,
            'total_entries' => $transactions->count(),
            'pending_days_count' => count($pendingGlOnlyDates),
            'pending_dates' => $pendingGlOnlyDates,
            'skip_gl_only_days' => $skipGlOnlyDays,
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
                    && $tx->reference_type !== ShopInvoice::class;
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

        $totalExpense = (float) $expenseCategories->sum();
        $totalInflow = (float) $incomeCategories->sum();
        $totalExpForDiv = max(1, $totalExpense);

        $expenseCategoriesDetailed = $activeTransactions
            ->filter(fn ($t) => $t->direction === 'expense' || ($t->entryType && $t->entryType->category === 'expense'))
            ->groupBy(fn ($t) => $t->entryType?->name ?: 'Other Expense')
            ->map(function ($group, $name) use ($totalExpense, $totalExpForDiv, $totalInflow) {
                $amount = (float) $group->sum('amount');

                return [
                    'name' => $name,
                    'amount' => round($amount, 2),
                    'pct' => $totalExpense > 0 ? round(($amount / $totalExpForDiv) * 100, 1) : 0,
                    'inflow_pct' => $totalInflow > 0 ? round(($amount / $totalInflow) * 100, 1) : null,
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
                    'description' => "{$day} averages ₹".number_format($metrics['avg_sales'], 0).' in gross sales. Ensure zero stock-outs on top moving vegetables and fruit lines.',
                ];
            }
        }

        if ($bestProfitDay && $bestProfitDay['avg_net'] > 0) {
            $recommendations[] = [
                'category' => 'Profit Hotspot',
                'badge' => 'Highest Net Profit',
                'badge_color' => 'teal',
                'title' => "{$bestProfitDay['day']} is your Most Profitable Day",
                'description' => 'Generates an average net profit of ₹'.number_format($bestProfitDay['avg_net'], 0)." with a {$bestProfitDay['margin_pct']}% profit margin.",
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

    /**
     * Cashbook Operational Inventory Reconciliation Screen:
     * 1. Daily Inventory (Billed vs Physical/Advance carry forward as-of date)
     * 2. Pending Bills (Authoritative source-of-truth from AdvanceReceiveReconciliationService)
     * 3. Advance Bills (Individual advance GRNs with 3-day operational retention for cleared)
     * 4. Invoice Adjustments (Secondary audit tab for delivery review and admin edits)
     */
    public function inventory(Request $request): View
    {
        $this->ensureAuthorized($request);

        // 1. Warehouse Authorization Scoping
        $requestedWarehouseId = $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null;
        if ($requestedWarehouseId !== null && ! Warehouse::query()->where('id', $requestedWarehouseId)->exists()) {
            abort(403, 'Unauthorized warehouse access.');
        }
        $authorizedWarehouseIds = app(WarehouseReceiptReadScope::class)->warehouseIds(
            $request->user(),
            $requestedWarehouseId
        );

        if ($requestedWarehouseId !== null && $authorizedWarehouseIds !== null && ! in_array($requestedWarehouseId, $authorizedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        $availableWarehouses = Warehouse::query()
            ->active()
            ->when($authorizedWarehouseIds !== null, fn (Builder $q) => $q->whereIn('id', $authorizedWarehouseIds))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $selectedWarehouseId = $requestedWarehouseId;

        // 2. Date Controls
        $selectedDate = $request->input('date', today()->toDateString());
        $date = Carbon::parse($selectedDate)->toDateString();
        $prevDate = Carbon::parse($date)->subDay()->toDateString();
        $nextDate = Carbon::parse($date)->addDay()->toDateString();
        $isToday = ($date === today()->toDateString());

        // 3. Tab Routing (Default: daily_inventory)
        $tab = (string) ($request->input('tab') ?: $request->input('section', 'daily_inventory'));
        if ($tab === 'bill_pending') {
            $tab = 'pending_bills';
        }
        if (! in_array($tab, ['daily_inventory', 'pending_bills', 'advance_bills', 'unit_differences', 'invoice_adjustments', 'bill_notes', 'loadout_not_billed', 'history'], true)) {
            $tab = 'daily_inventory';
        }

        $search = trim((string) $request->input('search', ''));
        $shopId = $request->input('shop_id');
        $reasonFilter = $request->input('reason');
        $resolutionFilter = $request->input('resolution');
        $timeframe = (string) $request->input('timeframe', $isToday ? 'today' : 'custom');

        // 4. Bounded Summary Metrics (Exact same accounting logic)
        $dailySummary = app(AdvanceInventoryService::class)->getDailySummary([
            'date' => $date,
            'warehouse_id' => $selectedWarehouseId,
            'authorized_warehouse_ids' => $authorizedWarehouseIds,
        ]);

        $pendingBillsCountQuery = PurchaseOrder::query()
            ->whereNotIn('status', ['draft', 'cancelled', 'rejected'])
            ->where(function (Builder $pending): void {
                $pending->whereHas('goodsReceiveds', fn ($receipts) => app(WarehouseReceiptStateResolver::class)->filter($receipts, 'pending'))
                    ->orWhere(function ($withoutReceipt): void {
                        $withoutReceipt->whereDoesntHave('goodsReceiveds')->whereIn('status', ['approved', 'sent_to_supplier', 'partially_received']);
                    });
            });
        app(WarehouseReceiptReadScope::class)->orders(
            $pendingBillsCountQuery,
            $selectedWarehouseId !== null ? [$selectedWarehouseId] : $authorizedWarehouseIds
        );
        $pendingBillsCount = $pendingBillsCountQuery->count();

        $openAdvancesCount = GoodsReceived::query()
            ->openWarehouseAdvance($selectedWarehouseId !== null ? $selectedWarehouseId : $authorizedWarehouseIds)
            ->count();

        $unitDifferencesCount = app(AdvanceReceiveReconciliationService::class)->countUnitDifferences([
            'warehouse_id' => $selectedWarehouseId,
            'authorized_warehouse_ids' => $authorizedWarehouseIds,
        ]);

        $summary = [
            'excess_count' => $dailySummary['excess_count'],
            'short_count' => $dailySummary['short_count'],
            'balanced_count' => $dailySummary['balanced_count'],
            'pending_bills_count' => $pendingBillsCount,
            'open_advances_count' => $openAdvancesCount,
            'unit_differences_count' => $unitDifferencesCount,
            'total_products' => $dailySummary['total_products'],
            'total_physical_base' => $dailySummary['total_physical_base'],
            'total_billed_base' => $dailySummary['total_billed_base'],
            'total_difference_base' => $dailySummary['total_difference_base'],
        ];

        // 5. Query Active Tab Data
        $dailyInventory = null;
        $pendingBills = null;
        $advanceBills = null;
        $unitDifferences = null;
        $adjustedInvoices = null;
        $activities = null;
        $activitiesByInvoice = collect();
        $billPendingReceipts = null;
        $loadoutNotBilled = null;
        $historyRecords = null;
        $summaryAdjustments = null;

        if ($tab === 'daily_inventory') {
            $dailyInventory = app(AdvanceInventoryService::class)->paginateDailyInventory([
                'date' => $date,
                'search' => $search,
                'warehouse_id' => $selectedWarehouseId,
                'authorized_warehouse_ids' => $authorizedWarehouseIds,
            ], 20)->withQueryString();
        } elseif ($tab === 'pending_bills') {
            $pendingBills = app(AdvanceReceiveReconciliationService::class)->paginateMatchCandidates([
                'search' => $search,
                'warehouse_id' => $selectedWarehouseId,
                'authorized_warehouse_ids' => $authorizedWarehouseIds,
            ], 20)->withQueryString();
        } elseif ($tab === 'unit_differences') {
            $unitDifferences = app(AdvanceReceiveReconciliationService::class)->paginateUnitDifferences([
                'search' => $search,
                'warehouse_id' => $selectedWarehouseId,
                'authorized_warehouse_ids' => $authorizedWarehouseIds,
            ], 20)->withQueryString();
        } elseif ($tab === 'advance_bills') {
            $advQuery = GoodsReceived::query()
                ->where(function (Builder $typeQ): void {
                    $typeQ->where('receipt_type', 'warehouse_advance')
                        ->orWhere(function ($leg): void {
                            $leg->whereNull('receipt_type')->whereNull('purchase_order_id');
                        });
                })
                ->where('status', '!=', 'cancelled')
                ->when($selectedWarehouseId !== null, function ($q) use ($selectedWarehouseId): void {
                    $q->where(function ($whQ) use ($selectedWarehouseId): void {
                        $whQ->where('warehouse_id', $selectedWarehouseId)
                            ->orWhere('destination_shop_id', $selectedWarehouseId);
                    });
                }, function ($q) use ($authorizedWarehouseIds): void {
                    if (! empty($authorizedWarehouseIds)) {
                        $q->where(function ($whQ) use ($authorizedWarehouseIds): void {
                            $whQ->whereIn('warehouse_id', $authorizedWarehouseIds)
                                ->orWhereIn('destination_shop_id', $authorizedWarehouseIds);
                        });
                    }
                })
                ->with([
                    'items.product',
                    'warehouse:id,name,code',
                    'destinationShop:id,name,code',
                    'receivedBy:id,name',
                    'advanceMatchesAsAdvance',
                ]);

            // Operational Retention: Pending/partial always visible; Cleared visible only for 3 days from authoritative clear event
            $cutoffDate = now()->subDays(3)->startOfDay();
            $advQuery->where(function (Builder $q) use ($cutoffDate): void {
                $q->where('bill_status', 'bill_pending')
                    ->orWhere(function (Builder $clearedQ) use ($cutoffDate): void {
                        $clearedQ->where('bill_status', 'bill_available')
                            ->where(function (Builder $dateQ) use ($cutoffDate): void {
                                $dateQ->whereExists(function ($armQ) use ($cutoffDate): void {
                                    $armQ->selectRaw('1')
                                        ->from('advance_receive_matches as arm')
                                        ->whereColumn('arm.advance_goods_received_id', 'goods_received.id')
                                        ->where('arm.confirmed_at', '>=', $cutoffDate);
                                })
                                    ->orWhere('goods_received.matched_at', '>=', $cutoffDate)
                                    ->orWhere('goods_received.updated_at', '>=', $cutoffDate);
                            });
                    });
            });

            if ($search !== '') {
                $advQuery->where(function (Builder $q) use ($search): void {
                    $q->where('grn_number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('items.product', function (Builder $pq) use ($search): void {
                            $pq->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%");
                        });
                });
            }

            $advanceBills = $advQuery->orderByDesc('received_at')->orderByDesc('id')->paginate(20)->withQueryString();
        } else {
            // Secondary / Legacy tabs (invoice adjustments, history, etc.)
            $baseQuery = Activity::query()
                ->with(['causer', 'subject.shop'])
                ->where('subject_type', ShopInvoice::class)
                ->whereIn(
                    'properties->source',
                    ['admin_delivery_review_finalized', 'admin_item_adjustment', 'admin_discount']
                )
                ->whereDate('created_at', $date);

            if ($shopId) {
                $baseQuery->whereHasMorph('subject', [ShopInvoice::class], fn ($q) => $q->where('shop_id', $shopId));
            }

            if ($search !== '') {
                $baseQuery->where(function ($q) use ($search): void {
                    $q->whereHas('causer', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                        ->orWhereHasMorph('subject', [ShopInvoice::class], function ($s) use ($search): void {
                            $s->where('invoice_number', 'like', "%{$search}%")
                                ->orWhereHas('shop', fn ($sh) => $sh->where('name', 'like', "%{$search}%"));
                        })
                        ->orWhere('properties', 'like', "%{$search}%");
                });
            }

            $allDayActivities = (clone $baseQuery)->get();
            $summaryAdjustments = [
                'total_adjustments' => 0,
                'returned_to_warehouse_count' => 0,
                'returned_to_warehouse_qty' => 0.0,
                'wastage_count' => 0,
                'wastage_qty' => 0.0,
                'extra_stock_count' => 0,
                'extra_stock_qty' => 0.0,
                'already_accounted_count' => 0,
                'already_accounted_qty' => 0.0,
            ];

            foreach ($allDayActivities as $act) {
                $resolutions = data_get($act->properties, 'inventory_resolutions', []);
                if (is_array($resolutions) && count($resolutions) > 0) {
                    foreach ($resolutions as $res) {
                        $summaryAdjustments['total_adjustments']++;
                        $resType = (string) ($res['resolution'] ?? '');
                        $resQty = (float) ($res['resolution_qty'] ?? 0);
                        if (in_array($resType, ['return_to_warehouse', 'add_back'], true)) {
                            $summaryAdjustments['returned_to_warehouse_count']++;
                            $summaryAdjustments['returned_to_warehouse_qty'] += $resQty;
                        } elseif ($resType === 'wastage') {
                            $summaryAdjustments['wastage_count']++;
                            $summaryAdjustments['wastage_qty'] += $resQty;
                        } elseif ($resType === 'deduct_extra') {
                            $summaryAdjustments['extra_stock_count']++;
                            $summaryAdjustments['extra_stock_qty'] += $resQty;
                        } elseif ($resType === 'already_accounted') {
                            $summaryAdjustments['already_accounted_count']++;
                            $summaryAdjustments['already_accounted_qty'] += $resQty;
                        }
                    }
                } else {
                    $summaryAdjustments['total_adjustments']++;
                }
            }

            if (in_array($tab, ['invoice_adjustments', 'bill_notes'], true)) {
                $filteredQuery = clone $baseQuery;
                if ($reasonFilter) {
                    $filteredQuery->where(function ($q) use ($reasonFilter): void {
                        $q->where('properties->reason', $reasonFilter)
                            ->orWhere('properties->inventory_resolutions', 'like', "%\"reason\":\"{$reasonFilter}\"%");
                    });
                }
                if ($resolutionFilter) {
                    $filteredQuery->where(function ($q) use ($resolutionFilter): void {
                        $q->where('properties->inventory_resolutions', 'like', "%\"resolution\":\"{$resolutionFilter}\"%");
                    });
                }

                $invoiceIds = (clone $filteredQuery)->pluck('subject_id')->unique()->all();

                $adjustedInvoices = ShopInvoice::query()
                    ->with(['shop', 'finalizedBy', 'items.product', 'order.items.product'])
                    ->whereIn('id', $invoiceIds)
                    ->orderByDesc('business_date')
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->paginate(25, ['*'], 'page')
                    ->withQueryString();

                $pageInvoiceIds = $adjustedInvoices->pluck('id')->all();
                $activitiesByInvoice = Activity::query()
                    ->with('causer')
                    ->where('subject_type', ShopInvoice::class)
                    ->whereIn('subject_id', $pageInvoiceIds)
                    ->whereIn('properties->source', ['admin_delivery_review_finalized', 'admin_item_adjustment', 'admin_discount'])
                    ->whereDate('created_at', $date)
                    ->orderByDesc('created_at')
                    ->get()
                    ->groupBy('subject_id');

                $activities = $filteredQuery
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->paginate(25, ['*'], 'page')
                    ->withQueryString();
            }
        }

        $availableShops = Shop::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('admin.cashbook.reports.inventory', [
            'tab' => $tab,
            'section' => $tab,
            'timeframe' => $timeframe,
            'selectedDate' => $date,
            'prevDate' => $prevDate,
            'nextDate' => $nextDate,
            'isToday' => $isToday,
            'search' => $search,
            'shopId' => $shopId,
            'selectedWarehouseId' => $selectedWarehouseId,
            'availableWarehouses' => $availableWarehouses,
            'reasonFilter' => $reasonFilter,
            'resolutionFilter' => $resolutionFilter,
            'summary' => $summary,
            'summaryAdjustments' => $summaryAdjustments,
            'dailyInventory' => $dailyInventory,
            'pendingBills' => $pendingBills,
            'advanceBills' => $advanceBills,
            'unitDifferences' => $unitDifferences,
            'activities' => $activities,
            'adjustedInvoices' => $adjustedInvoices,
            'activitiesByInvoice' => $activitiesByInvoice,
            'billPendingReceipts' => $billPendingReceipts,
            'loadoutNotBilled' => $loadoutNotBilled,
            'historyRecords' => $historyRecords,
            'availableShops' => $availableShops,
            'activeTab' => 'inventory',
        ]);
    }

    /**
     * Get deterministic read-only auto-clear plan preview for web UI.
     */
    public function autoClearPlan(Request $request): JsonResponse
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ]);

        $warehouseId = (int) $validated['warehouse_id'];
        $authorizedWarehouseIds = app(WarehouseReceiptReadScope::class)->warehouseIds($request->user(), $warehouseId);
        if ($authorizedWarehouseIds !== null && ! in_array($warehouseId, $authorizedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        $plan = app(AutoAdvanceClearPlanningService::class)->buildAutoClearPlan(
            $warehouseId,
            (int) $request->user()->id
        );

        return response()->json([
            'status' => 'success',
            'data' => $plan,
        ]);
    }

    /**
     * Execute auto-match clear plan idempotently from web UI.
     */
    public function autoClearExecute(Request $request): JsonResponse
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'plan_hash' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i'],
            'client_submission_id' => ['required', 'string', 'uuid'],
        ]);

        $warehouseId = (int) $validated['warehouse_id'];
        $authorizedWarehouseIds = app(WarehouseReceiptReadScope::class)->warehouseIds($request->user(), $warehouseId);
        if ($authorizedWarehouseIds !== null && ! in_array($warehouseId, $authorizedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        try {
            $result = app(AutoAdvanceClearExecutionService::class)->execute(
                $warehouseId,
                (string) $validated['plan_hash'],
                (string) $validated['client_submission_id'],
                (int) $request->user()->id
            );

            if (isset($result['status_code']) && $result['status_code'] === 409) {
                return response()->json([
                    'status' => 'conflict',
                    'message' => 'The auto-clear plan is stale or conflicted with concurrent receipts. Please review the updated preview.',
                    'error' => $result['error'] ?? null,
                ], 409);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Automatic advance reconciliation completed successfully.',
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'validation_error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('AutoAdvanceClear web execution failed', [
                'warehouse_id' => $warehouseId,
                'plan_hash' => $validated['plan_hash'],
                'client_submission_id' => $validated['client_submission_id'],
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Reconciliation execution could not complete: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get candidate advance matches for a specific Purchase Order.
     */
    public function manualMatchSuggestions(Request $request, PurchaseOrder $order): JsonResponse
    {
        $this->ensureAuthorized($request);

        $requestedWarehouseId = $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null;
        $authorizedWarehouseIds = app(WarehouseReceiptReadScope::class)->warehouseIds($request->user(), $requestedWarehouseId);

        $targetWarehouseId = $requestedWarehouseId ?? (int) ($order->warehouse_id ?? $order->destination_shop_id ?? 1);

        if ($authorizedWarehouseIds !== null && ! in_array($targetWarehouseId, $authorizedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        $suggestions = app(AdvanceReceiveReconciliationService::class)->getSuggestionsForOrder($order, $targetWarehouseId);

        return response()->json([
            'status' => 'success',
            'data' => $suggestions,
        ]);
    }

    /**
     * Execute manual match for a specific Purchase Order against open Advance GRNs.
     */
    public function manualMatchExecute(Request $request, PurchaseOrder $order): JsonResponse
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.goods_received_item_id' => ['nullable', 'integer'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.received_qty' => ['required', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'advance_matches' => ['required', 'array', 'min:1'],
            'advance_matches.*.advance_goods_received_id' => ['required', 'integer', 'exists:goods_received,id'],
            'advance_matches.*.advance_goods_received_item_id' => ['nullable', 'integer'],
            'advance_matches.*.purchase_order_item_id' => ['nullable', 'integer'],
            'advance_matches.*.goods_received_item_id' => ['nullable', 'integer'],
            'advance_matches.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'advance_matches.*.matched_qty' => ['required', 'numeric', 'min:0.001'],
            'advance_matches.*.unit' => ['nullable', 'string', 'max:20'],
        ]);

        $warehouseId = (int) $validated['warehouse_id'];
        $authorizedWarehouseIds = app(WarehouseReceiptReadScope::class)->warehouseIds($request->user(), $warehouseId);
        if ($authorizedWarehouseIds !== null && ! in_array($warehouseId, $authorizedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        $userId = (int) $request->user()->id;

        $pendingGrn = $order->goodsReceiveds()
            ->where(function (Builder $q): void {
                $q->where('receipt_type', 'normal_purchase')
                    ->orWhereNull('receipt_type');
            })
            ->where('status', '!=', 'approved')
            ->first();

        try {
            if ($pendingGrn) {
                $result = app(AdvanceReceiveReconciliationService::class)->reconcileExistingGrn(
                    $pendingGrn,
                    $validated['items'],
                    $validated['advance_matches'],
                    $warehouseId,
                    $userId
                );
            } else {
                $dtoItems = [];
                foreach ($validated['items'] as $it) {
                    $dtoItems[] = [
                        'product_id' => (int) $it['product_id'],
                        'purchase_order_item_id' => isset($it['purchase_order_item_id']) ? (int) $it['purchase_order_item_id'] : null,
                        'received_qty' => (float) $it['received_qty'],
                        'received_unit' => $it['unit'] ?? 'kg',
                    ];
                }

                $grnData = new GoodsReceivedData(
                    purchaseOrderId: $order->id,
                    receivedAt: now()->toDateTimeString(),
                    transportCost: 0.0,
                    labourCost: 0.0,
                    notes: "Manual match for PO #{$order->po_number}",
                    items: $dtoItems,
                    billStatus: 'bill_available',
                    billNumber: null,
                    destinationShopId: $order->destination_shop_id,
                    warehouseId: $warehouseId,
                    clientSubmissionId: (string) Str::uuid(),
                    advanceMatches: $validated['advance_matches'],
                    receiptType: 'normal_purchase',
                );

                $result = app(AdvanceReceiveReconciliationService::class)->reconcileAndExecute(
                    $grnData,
                    $userId
                );
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Purchase bill manually matched with advance successfully.',
                'grn_id' => $result->id,
                'grn_number' => $result->grn_number,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'validation_error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Manual match execution failed', [
                'purchase_order_id' => $order->id,
                'warehouse_id' => $warehouseId,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Manual match failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolve unit difference line on a purchase order.
     */
    public function resolveUnitDifference(Request $request): JsonResponse
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'advance_goods_received_id' => ['required', 'integer', 'exists:goods_received,id'],
            'advance_goods_received_item_id' => ['nullable', 'integer', 'exists:goods_received_items,id'],
            'matched_qty' => ['required', 'numeric', 'min:0.001'],
            'conversion_factor' => ['required', 'numeric', 'min:0.0001'],
            'notes' => ['nullable', 'string', 'max:500'],
            'client_submission_id' => ['nullable', 'string', 'uuid'],
        ]);

        $warehouseId = (int) $validated['warehouse_id'];
        $authorizedWarehouseIds = app(WarehouseReceiptReadScope::class)->warehouseIds($request->user(), $warehouseId);
        if ($authorizedWarehouseIds !== null && ! in_array($warehouseId, $authorizedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        $userId = (int) $request->user()->id;

        try {
            $result = app(AdvanceReceiveReconciliationService::class)->resolveUnitDifference($validated, $userId);

            return response()->json([
                'status' => 'success',
                'message' => 'Unit difference resolved successfully.',
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'validation_error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Resolve unit difference execution failed', [
                'purchase_order_id' => $validated['purchase_order_id'],
                'purchase_order_item_id' => $validated['purchase_order_item_id'],
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Resolution could not be saved: '.$e->getMessage(),
            ], 500);
        }
    }

    public function matchBill(Request $request, GoodsReceived $goodsReceived): RedirectResponse
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'invoice_number' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        app(GoodsReceivedService::class)->matchBill($goodsReceived, $validated, (int) auth()->id());

        return redirect()->back()->with('success', 'Purchase bill successfully matched to goods receipt. Inventory was preserved.');
    }

    public function billChanges(Request $request): View
    {
        $this->ensureAuthorized($request);

        $selectedDate = $request->input('date');
        $timeframe = (string) $request->input('timeframe', $selectedDate ? 'custom' : 'today');

        if ($timeframe === 'yesterday') {
            $selectedDate = today()->subDay()->toDateString();
        } elseif ($timeframe === 'today' || ! $selectedDate) {
            $selectedDate = today()->toDateString();
            $timeframe = 'today';
        }

        $shopId = $request->input('shop_id');
        $search = trim((string) $request->input('search', ''));

        $invoiceQuery = ShopInvoice::query()
            ->with(['shop', 'finalizedBy', 'items.product', 'order.items.product'])
            ->whereDate('business_date', $selectedDate);

        if ($shopId) {
            $invoiceQuery->where('shop_id', $shopId);
        }

        if ($search !== '') {
            $invoiceQuery->where(function ($q) use ($search): void {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('shop', fn ($sq) => $sq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('items.product', fn ($pq) => $pq->where('name', 'like', "%{$search}%"))
                    ->orWhere('delivery_note', 'like', "%{$search}%");
            });
        }

        $allDayInvoices = (clone $invoiceQuery)->get();
        $invoiceIds = $allDayInvoices->pluck('id')->all();

        $activitiesByInvoice = Activity::query()
            ->with('causer')
            ->where('subject_type', ShopInvoice::class)
            ->whereIn('subject_id', $invoiceIds)
            ->whereIn('properties->source', ['admin_delivery_review_finalized', 'admin_item_adjustment', 'admin_discount'])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('subject_id');

        $shopSummaries = $allDayInvoices
            ->groupBy('shop_id')
            ->map(function (Collection $invoices, int $shopIdKey) use ($activitiesByInvoice) {
                $shop = $invoices->first()?->shop;
                $totalBills = $invoices->count();
                $finalizedBills = $invoices->filter(fn (ShopInvoice $inv) => $inv->isFinalized())->count();
                $totalFinalAmount = (float) $invoices->sum('final_total');

                $changedBills = 0;
                $totalAdjustments = 0;
                foreach ($invoices as $inv) {
                    $invActs = $activitiesByInvoice->get($inv->id, collect());
                    if ($invActs->isNotEmpty() || $inv->shortage_total > 0 || $inv->discount_total > 0) {
                        $changedBills++;
                        $totalAdjustments += $invActs->count();
                    }
                }

                return [
                    'shop_id' => $shopIdKey,
                    'shop_name' => $shop?->name ?? 'Shop #'.$shopIdKey,
                    'shop_code' => $shop?->code ?? '',
                    'total_bills' => $totalBills,
                    'changed_bills' => $changedBills,
                    'finalized_bills' => $finalizedBills,
                    'total_final_amount' => $totalFinalAmount,
                    'total_adjustments' => $totalAdjustments,
                    'invoices' => $invoices,
                ];
            })
            ->values();

        $paginatedInvoices = $invoiceQuery
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'page')
            ->withQueryString();

        $availableShops = Shop::query()->orderBy('name')->get(['id', 'name', 'code']);

        return view('admin.cashbook.reports.bill-changes', [
            'timeframe' => $timeframe,
            'selectedDate' => $selectedDate,
            'search' => $search,
            'shopId' => $shopId,
            'shopSummaries' => $shopSummaries,
            'invoices' => $paginatedInvoices,
            'activitiesByInvoice' => $activitiesByInvoice,
            'availableShops' => $availableShops,
            'activeTab' => 'bill-changes',
        ]);
    }

    public function billChangesShopDay(Request $request): JsonResponse
    {
        $this->ensureAuthorized($request);

        $shopId = (int) $request->input('shop_id');
        $date = $request->input('date') ?: today()->toDateString();
        $invoiceId = $request->input('invoice_id');

        $query = ShopInvoice::query()
            ->with(['shop', 'finalizedBy', 'items.product', 'order.items.product'])
            ->whereDate('business_date', $date);

        if ($shopId > 0) {
            $query->where('shop_id', $shopId);
        }
        if ($invoiceId) {
            $query->where('id', $invoiceId);
        }

        $invoices = $query->get();
        $invoiceIds = $invoices->pluck('id')->all();

        $activities = Activity::query()
            ->with('causer')
            ->where('subject_type', ShopInvoice::class)
            ->whereIn('subject_id', $invoiceIds)
            ->whereIn('properties->source', ['admin_delivery_review_finalized', 'admin_item_adjustment', 'admin_discount'])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('subject_id');

        $data = $invoices->map(function (ShopInvoice $invoice) use ($activities) {
            $invActivities = $activities->get($invoice->id, collect());
            $latestAct = $invActivities->first();
            $props = $latestAct?->properties ?? [];

            $productChanges = data_get($props, 'product_changes', []);
            $resolutions = data_get($props, 'inventory_resolutions', []);
            $autoSummary = data_get($props, 'auto_change_summary');
            $overallNote = data_get($props, 'overall_note') ?: $invoice->delivery_note;
            $isAdminOnBehalf = (bool) data_get($props, 'is_admin_on_behalf');

            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'shop_name' => $invoice->shop?->name,
                'business_date' => $invoice->business_date?->toDateString(),
                'status' => $invoice->isFinalized() ? 'FINALIZED' : strtoupper(str_replace('_', ' ', $invoice->status)),
                'final_total' => (float) $invoice->final_total,
                'subtotal' => (float) $invoice->subtotal,
                'discount_total' => (float) $invoice->discount_total,
                'finalized_by' => $invoice->finalizedBy?->name ?? data_get($props, 'actor.name') ?? 'Admin',
                'finalized_at' => $invoice->finalized_at?->format('d M Y • H:i'),
                'is_admin_on_behalf' => $isAdminOnBehalf,
                'overall_note' => $overallNote,
                'auto_change_summary' => $autoSummary,
                'product_changes' => $productChanges,
                'inventory_resolutions' => $resolutions,
                'activities_count' => $invActivities->count(),
                'has_changes' => $invActivities->isNotEmpty() || $invoice->discount_total > 0,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
