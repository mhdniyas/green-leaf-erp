<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Purchasing\ApproveGoodsReceiptAction;
use App\DTOs\Inventory\WastageEntryData;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Http\Controllers\Controller;
use App\Models\AdvanceReceiveMatch;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\DailyPricePublication;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseProductFilter;
use App\Models\Shop;
use App\Models\ShopDailyProductPrice;
use App\Models\ShopInvoice;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WastageEntry;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Inventory\WastageService;
use App\Services\Pricing\PriceBoardService;
use App\Services\Purchasing\AdvanceAvailableBalanceCalculator;
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
     * Show the Inventory Operational Reconciliation Dashboard.
     *
     * 1. Today Advances (Advance Receives recorded on selected date)
    /**
     * Cashbook Inventory Action Center:
     * 1. Current Inventory (Sellable balance, With Bill, Without Bill)
     * 2. Receive Bills (Pending Bills, Approvals, Advance Matching)
     * 3. Stock Without Bill (Unbilled Advances > 0, Age, Share Missing Bills)
     * 4. Shop Returns (StockMovement type = sale_reversal)
     * 5. Damage (WastageEntry on date, Move to Damage)
     * 6. Physical Check (Reconciliation count vs ERP balance)
     * 7. Unit Differences (Exception tab)
     */
    public function inventory(Request $request): View
    {
        $this->ensureAuthorized($request);

        // 1. Warehouse Authorization Scoping
        $requestedWarehouseId = $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null;
        $authorizedWarehouseIds = app(WarehouseReceiptReadScope::class)->warehouseIds(
            $request->user(),
            null
        );

        $availableWarehouses = Warehouse::query()
            ->active()
            ->when($authorizedWarehouseIds !== null, fn (Builder $q) => $q->whereIn('id', $authorizedWarehouseIds))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        if ($requestedWarehouseId !== null) {
            if (! $availableWarehouses->contains('id', $requestedWarehouseId)) {
                if (! Warehouse::query()->where('id', $requestedWarehouseId)->exists() || ($authorizedWarehouseIds !== null && ! in_array($requestedWarehouseId, $authorizedWarehouseIds, true))) {
                    abort(403, 'Unauthorized warehouse access.');
                }
            }
        }

        $selectedWarehouseId = $requestedWarehouseId;

        // 2. Date Controls
        $selectedDate = $request->input('date', today()->toDateString());
        $date = Carbon::parse($selectedDate)->toDateString();
        $prevDate = Carbon::parse($date)->subDay()->toDateString();
        $nextDate = Carbon::parse($date)->addDay()->toDateString();
        $isToday = ($date === today()->toDateString());

        // 3. Tab Routing (Default: current_inventory)
        $rawTab = (string) ($request->input('tab') ?: $request->input('section', 'current_inventory'));
        $tab = match ($rawTab) {
            'current_inventory', 'current_stock', 'stock', 'inventory' => 'current_inventory',
            'receive_bills', 'pending_bills', 'bill_pending', 'pending_reconciliation' => 'receive_bills',
            'stock_without_bill', 'unbilled_inventory', 'today_advances', 'advance_bills' => 'stock_without_bill',
            'shop_returns', 'returns', 'shop_return' => 'shop_returns',
            'damage', 'wastage' => 'damage',
            'physical_check', 'physical_count', 'adjustments' => 'physical_check',
            'match_details' => 'match_details',
            'unit_differences' => 'unit_differences',
            'unbilled_loadout' => 'current_inventory',
            default => 'current_inventory',
        };

        $search = trim((string) $request->input('search', ''));
        $shopId = $request->input('shop_id');
        $timeframe = (string) $request->input('timeframe', $isToday ? 'today' : 'custom');
        $calc = app(AdvanceAvailableBalanceCalculator::class);
        $stockRepo = app(StockMovementRepository::class);

        // 4. Base Queries & Summary Metrics
        // A. Bills Received (Direct or standard bill GRNs on date)
        $billsReceivedStats = GoodsReceivedItem::query()
            ->whereHas('goodsReceived', function (Builder $g) use ($date, $selectedWarehouseId, $authorizedWarehouseIds): void {
                $g->where('receipt_type', '!=', 'warehouse_advance')
                    ->where('status', '!=', 'cancelled')
                    ->whereDate('received_at', $date);
                app(WarehouseReceiptReadScope::class)->receipts(
                    $g,
                    $selectedWarehouseId !== null ? [$selectedWarehouseId] : $authorizedWarehouseIds
                );
            })
            ->selectRaw('COUNT(DISTINCT goods_received_id) as grn_count, COALESCE(SUM(received_qty), 0) as total_qty')
            ->first();
        $billsReceivedCount = (int) ($billsReceivedStats->grn_count ?? 0);
        $billsReceivedQty = (float) ($billsReceivedStats->total_qty ?? 0);

        // B. Advance Receives (Warehouse advances on date)
        $advReceivesStats = GoodsReceivedItem::query()
            ->whereHas('goodsReceived', function (Builder $g) use ($date, $selectedWarehouseId, $authorizedWarehouseIds): void {
                $g->where('receipt_type', 'warehouse_advance')
                    ->where('status', '!=', 'cancelled')
                    ->whereDate('received_at', $date);
                app(WarehouseReceiptReadScope::class)->receipts(
                    $g,
                    $selectedWarehouseId !== null ? [$selectedWarehouseId] : $authorizedWarehouseIds
                );
            })
            ->selectRaw('COUNT(DISTINCT goods_received_id) as grn_count, COALESCE(SUM(received_qty), 0) as total_qty')
            ->first();
        $advanceReceivesCount = (int) ($advReceivesStats->grn_count ?? 0);
        $advanceReceivesQty = (float) ($advReceivesStats->total_qty ?? 0);

        // C. Advance Matched Qty (Matches confirmed on date)
        $advanceMatchedQuery = AdvanceReceiveMatch::query()
            ->where(function (Builder $q) use ($date): void {
                $q->whereDate('confirmed_at', $date)
                    ->orWhere(fn (Builder $q2) => $q2->whereNull('confirmed_at')->whereDate('created_at', $date));
            })
            ->when($selectedWarehouseId !== null || $authorizedWarehouseIds !== null, function (Builder $q) use ($selectedWarehouseId, $authorizedWarehouseIds): void {
                $q->whereHas('advanceGoodsReceived', function (Builder $g) use ($selectedWarehouseId, $authorizedWarehouseIds): void {
                    app(WarehouseReceiptReadScope::class)->receipts(
                        $g,
                        $selectedWarehouseId !== null ? [$selectedWarehouseId] : $authorizedWarehouseIds
                    );
                });
            });
        $advanceMatchedQty = (float) $advanceMatchedQuery->sum('base_qty');

        // D. New Physical Receive Qty (Physical direct non-advance intake on date)
        $newPhysicalReceiveQuery = StockMovement::query()
            ->where('type', StockMovementType::In->value)
            ->whereDate('created_at', $date)
            ->whereHas('batch', function (Builder $bq): void {
                $bq->whereDoesntHave('goodsReceived', fn (Builder $rq) => $rq->where('receipt_type', 'warehouse_advance'));
            })
            ->when($selectedWarehouseId !== null, fn (Builder $q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->when($authorizedWarehouseIds !== null, fn (Builder $q) => $q->whereIn('warehouse_id', $authorizedWarehouseIds));
        $newPhysicalReceiveQty = (float) $newPhysicalReceiveQuery->sum('quantity');

        // E. Shop Returns (StockMovement type = sale_reversal on date)
        $shopReturnsStats = StockMovement::query()
            ->where('type', StockMovementType::SaleReversal->value)
            ->whereDate('created_at', $date)
            ->when($selectedWarehouseId !== null, fn (Builder $q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->when($authorizedWarehouseIds !== null, fn (Builder $q) => $q->whereIn('warehouse_id', $authorizedWarehouseIds))
            ->selectRaw('COUNT(*) as aggregate_count, COALESCE(SUM(quantity), 0) as aggregate_qty')
            ->first();
        $shopReturnsCount = (int) ($shopReturnsStats->aggregate_count ?? 0);
        $shopReturnsQty = (float) ($shopReturnsStats->aggregate_qty ?? 0);

        // F. Loadout Qty (StockMovement type = out on date)
        $loadoutQtyQuery = StockMovement::query()
            ->where('type', StockMovementType::Out->value)
            ->whereDate('created_at', $date)
            ->when($selectedWarehouseId !== null, fn (Builder $q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->when($authorizedWarehouseIds !== null, fn (Builder $q) => $q->whereIn('warehouse_id', $authorizedWarehouseIds));
        $loadoutQty = (float) $loadoutQtyQuery->sum('quantity');

        // G. Damage Qty (WastageEntry on date)
        $damageQtyQuery = WastageEntry::query()
            ->whereDate('wastage_date', $date)
            ->when($selectedWarehouseId !== null, function (Builder $q) use ($selectedWarehouseId): void {
                $q->where(function (Builder $sub) use ($selectedWarehouseId): void {
                    $sub->whereHas('batch', fn (Builder $bq) => $bq->where('warehouse_id', $selectedWarehouseId))
                        ->orWhereNull('batch_id');
                });
            })
            ->when($authorizedWarehouseIds !== null, function (Builder $q) use ($authorizedWarehouseIds): void {
                $q->where(function (Builder $sub) use ($authorizedWarehouseIds): void {
                    $sub->whereHas('batch', fn (Builder $bq) => $bq->whereIn('warehouse_id', $authorizedWarehouseIds))
                        ->orWhereNull('batch_id');
                });
            });
        $damageQty = (float) $damageQtyQuery->sum('quantity');

        // H. Physical Adjustments (StockAdjustment variance on date)
        $adjSumQuery = StockAdjustment::query()
            ->whereDate('business_date', $date)
            ->when($selectedWarehouseId !== null, fn (Builder $q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->when($authorizedWarehouseIds !== null, fn (Builder $q) => $q->whereIn('warehouse_id', $authorizedWarehouseIds));
        $physicalAdjustmentQty = (float) $adjSumQuery->sum('variance_qty');

        // I. Closing / Current Inventory Balance (Sum of sellable balance)
        $currentStockCollection = $stockRepo->currentStockByProductAndGrade($date, $selectedWarehouseId);
        $closingInventoryQty = (float) $currentStockCollection->sum('current_stock');
        $currentStockByProduct = $currentStockCollection->groupBy('product_id')->map(fn (Collection $rows): float => (float) $rows->sum('current_stock'));

        // J. Pending Bills & Approvals (Combined Single Aggregate Query)
        $receiptCounts = GoodsReceived::query()
            ->where(function (Builder $g) use ($selectedWarehouseId, $authorizedWarehouseIds): void {
                app(WarehouseReceiptReadScope::class)->receipts(
                    $g,
                    $selectedWarehouseId !== null ? [$selectedWarehouseId] : $authorizedWarehouseIds
                );
            })
            ->selectRaw("
                SUM(CASE WHEN status = 'approved' AND bill_status = 'bill_pending' AND (receipt_type != 'warehouse_advance' OR receipt_type IS NULL) THEN 1 ELSE 0 END) as pending_bills_count,
                SUM(CASE WHEN status = 'pending_approval' AND (receipt_type != 'warehouse_advance' OR receipt_type IS NULL) THEN 1 ELSE 0 END) as awaiting_approval_count
            ")
            ->first();
        $pendingBillsCount = (int) ($receiptCounts->pending_bills_count ?? 0);
        $awaitingApprovalCount = (int) ($receiptCounts->awaiting_approval_count ?? 0);

        // K. Matchable Bills Plan (Computed on demand for matching tabs)
        $matchPlan = null;
        $matchableBillsCount = 0;
        $matchedBaseQtyPlan = 0.0;
        if ($tab === 'receive_bills' || $tab === 'match_details') {
            $targetWarehouseForPlan = $selectedWarehouseId ?? ($availableWarehouses->first()?->id ?? 1);
            try {
                $matchPlan = app(AutoAdvanceClearPlanningService::class)->buildAutoClearPlan($targetWarehouseForPlan, (int) $request->user()->id);
                $matchableBillsCount = count($matchPlan['ready_bills'] ?? []);
                $matchedBaseQtyPlan = (float) ($matchPlan['summary']['matched_base_qty'] ?? 0.0);
            } catch (Throwable $e) {
                Log::warning("Could not build auto clear plan for warehouse {$targetWarehouseForPlan}: {$e->getMessage()}");
            }
        }

        // L. Open Unbilled Advances Calculation
        $openAdvancesQuery = GoodsReceived::query()
            ->where(function (Builder $tq): void {
                $tq->where('receipt_type', 'warehouse_advance')
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->whereNull('receipt_type')
                            ->whereNull('purchase_order_id');
                    });
            })
            ->where('status', '!=', 'cancelled')
            ->where('bill_status', 'bill_pending')
            ->with('items.product');
        app(WarehouseReceiptReadScope::class)->receipts(
            $openAdvancesQuery,
            $selectedWarehouseId !== null ? [$selectedWarehouseId] : $authorizedWarehouseIds
        );
        $openAdvances = $openAdvancesQuery->orderBy('received_at')->get();
        $openAdvIds = $openAdvances->pluck('id');
        $preloadedMatches = $openAdvIds->isNotEmpty()
            ? AdvanceReceiveMatch::query()->whereIn('advance_goods_received_id', $openAdvIds)->get()
            : collect();

        $unbilledInventoryCount = 0;
        $unbilledInventoryKg = 0.0;
        $unbilledByProductId = [];
        $unbilledAdvRows = [];
        $unbilledAdvGrns = [];

        $isStockWithoutBillTab = ($tab === 'stock_without_bill' || $tab === 'unbilled_inventory');

        foreach ($openAdvances as $adv) {
            $itemBalances = $calc->calculateItemAvailableBase($adv, null, $preloadedMatches);
            $advDate = Carbon::parse($adv->received_at ?? $adv->created_at);
            $ageDays = max(0, (int) $advDate->diffInDays(Carbon::parse($date), false));

            $hasActiveUnbilledItem = false;
            $grnItems = [];
            $grnMissingKg = 0.0;

            foreach ($adv->items as $advItem) {
                $rem = (float) ($itemBalances[$advItem->id] ?? 0.0);
                if ($rem > 0.0001) {
                    $hasActiveUnbilledItem = true;
                    $prodId = (int) $advItem->product_id;
                    $unbilledByProductId[$prodId] = ($unbilledByProductId[$prodId] ?? 0.0) + $rem;
                    $unbilledInventoryKg += $rem;
                    $grnMissingKg += $rem;

                    if ($isStockWithoutBillTab) {
                        $matchesSearch = true;
                        if ($search !== '') {
                            $searchLower = strtolower($search);
                            $matchesSearch = str_contains(strtolower($adv->grn_number ?? ''), $searchLower)
                                || str_contains(strtolower($advItem->product?->name ?? ''), $searchLower)
                                || str_contains(strtolower($advItem->product?->sku ?? ''), $searchLower);
                        }

                        if ($matchesSearch) {
                            $unbilledAdvRows[] = [
                                'advance' => $adv,
                                'advance_id' => $adv->id,
                                'item' => $advItem,
                                'product' => $advItem->product,
                                'received_qty' => (float) $advItem->received_qty,
                                'matched_qty' => (float) ($advItem->matched_qty ?? 0.0),
                                'missing_qty' => $rem,
                                'age_days' => $ageDays,
                                'received_at' => $adv->received_at,
                                'grn_number' => $adv->grn_number,
                            ];
                        }

                        $grnItems[] = [
                            'item_id' => $advItem->id,
                            'product_id' => $advItem->product_id,
                            'name' => $advItem->product?->name ?? "Product #{$advItem->product_id}",
                            'sku' => $advItem->product?->sku ?? '',
                            'unit' => $advItem->received_unit ?? $advItem->product?->unit ?? 'KG',
                            'received_qty' => (float) $advItem->received_qty,
                            'matched_qty' => (float) ($advItem->matched_qty ?? 0.0),
                            'missing_qty' => $rem,
                        ];
                    }
                }
            }
            if ($hasActiveUnbilledItem) {
                $unbilledInventoryCount++;

                if ($isStockWithoutBillTab) {
                    $grnMatchesSearch = true;
                    if ($search !== '') {
                        $searchLower = strtolower($search);
                        $grnMatchesSearch = str_contains(strtolower($adv->grn_number ?? ''), $searchLower);
                        if (! $grnMatchesSearch) {
                            foreach ($grnItems as $it) {
                                if (str_contains(strtolower($it['name'] ?? ''), $searchLower) || str_contains(strtolower($it['sku'] ?? ''), $searchLower)) {
                                    $grnMatchesSearch = true;
                                    break;
                                }
                            }
                        }
                    }

                    if ($grnMatchesSearch) {
                        $unbilledAdvGrns[] = [
                            'id' => $adv->id,
                            'grn_number' => $adv->grn_number,
                            'received_at' => $adv->received_at,
                            'age_days' => $ageDays,
                            'warehouse_id' => $adv->warehouse_id,
                            'total_missing_qty' => round($grnMissingKg, 2),
                            'items' => $grnItems,
                            'items_summary' => implode(', ', array_map(fn ($i) => "{$i['name']} (".number_format($i['missing_qty'], 2)." {$i['unit']})", $grnItems)),
                        ];
                    }
                }
            }
        }
        $unbilledInventoryKg = round($unbilledInventoryKg, 2);

        // M. Unit Differences Count
        $unitDifferencesCount = app(AdvanceReceiveReconciliationService::class)->countUnitDifferences([
            'warehouse_id' => $selectedWarehouseId,
            'authorized_warehouse_ids' => $authorizedWarehouseIds,
        ]);

        $summary = [
            'bills_received_count' => $billsReceivedCount,
            'bills_received_qty' => round($billsReceivedQty, 2),
            'advance_receives_count' => $advanceReceivesCount,
            'advance_receives_qty' => round($advanceReceivesQty, 2),
            'advance_matched_qty' => round($advanceMatchedQty, 2),
            'new_physical_receive_qty' => round($newPhysicalReceiveQty, 2),
            'shop_returns_count' => $shopReturnsCount,
            'shop_returns_qty' => round($shopReturnsQty, 2),
            'loadout_qty' => round($loadoutQty, 2),
            'damage_qty' => round($damageQty, 2),
            'physical_adjustment_qty' => round($physicalAdjustmentQty, 2),
            'closing_inventory_qty' => round($closingInventoryQty, 2),
            'today_advances_count' => $advanceReceivesCount,
            'pending_bills_count' => $pendingBillsCount,
            'awaiting_approval_count' => $awaitingApprovalCount,
            'matchable_bills_count' => $matchableBillsCount,
            'matched_base_qty_plan' => $matchedBaseQtyPlan,
            'unbilled_inventory_count' => $unbilledInventoryCount,
            'unbilled_inventory_kg' => $unbilledInventoryKg,
            'unit_differences_count' => $unitDifferencesCount,
        ];

        // 5. Query Active Tab Specific Data
        $currentInventory = null;
        $pendingBills = null;
        $matchDetailsPlan = null;
        $stockWithoutBill = null;
        $shopReturns = null;
        $damageEntries = null;
        $physicalCheckProducts = null;
        $recentAdjustments = null;
        $unitDifferences = null;

        $allowedSorts = [
            'product',
            'category',
            'current_sellable',
            'with_bill',
            'without_bill',
            'pending_vendor_bill',
            'stock_deficit',
        ];

        $sort = $request->input('sort');
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = null;
        }

        $rawDirection = strtolower((string) $request->input('direction', 'asc'));
        $direction = in_array($rawDirection, ['asc', 'desc'], true) ? $rawDirection : 'asc';

        if ($tab === 'current_inventory') {
            $productsQuery = Product::query()
                ->where('is_active', true)
                ->with('category:id,name');

            if ($search !== '') {
                $productsQuery->where(function (Builder $pq) use ($search): void {
                    $pq->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            }

            $allProducts = $productsQuery->orderBy('name')->get();
            $damageProducts = $allProducts->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'unit' => $p->unit ?? 'KG',
            ]);

            $stockByProduct = $currentStockByProduct;

            $currentInventoryRows = $allProducts->map(function (Product $p) use ($stockByProduct, $unbilledByProductId): array {
                $ledgerBalance = (float) ($stockByProduct[$p->id] ?? 0.0);
                $unmatchedAdvanceQty = (float) ($unbilledByProductId[$p->id] ?? 0.0);

                $currentSellable = max(0.0, $ledgerBalance);
                $stockDeficit = max(0.0, -$ledgerBalance);
                $pendingVendorBill = max(0.0, $unmatchedAdvanceQty);
                $withoutBillOnHand = min($currentSellable, $pendingVendorBill);
                $withBillOnHand = max(0.0, round($currentSellable - $withoutBillOnHand, 3));

                return [
                    'product' => $p,
                    'product_id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'unit' => $p->unit,
                    'category' => $p->category?->name ?? '—',
                    'ledger_balance' => $ledgerBalance,
                    'current_balance' => $ledgerBalance,
                    'current_sellable' => $currentSellable,
                    'stock_deficit' => $stockDeficit,
                    'pending_vendor_bill' => $pendingVendorBill,
                    'without_bill' => $withoutBillOnHand,
                    'with_bill' => $withBillOnHand,
                    'without_bill_on_hand' => $withoutBillOnHand,
                    'with_bill_on_hand' => $withBillOnHand,
                ];
            });

            // Global server-side sorting
            if ($sort !== null) {
                $currentInventoryRows = $currentInventoryRows->sort(function (array $a, array $b) use ($sort, $direction): int {
                    $cmp = match ($sort) {
                        'product' => strcasecmp($a['name'], $b['name']),
                        'category' => strcasecmp($a['category'], $b['category']) ?: strcasecmp($a['name'], $b['name']),
                        'current_sellable' => ($a['current_sellable'] <=> $b['current_sellable']) ?: strcasecmp($a['name'], $b['name']),
                        'with_bill' => ($a['with_bill'] <=> $b['with_bill']) ?: strcasecmp($a['name'], $b['name']),
                        'without_bill' => ($a['without_bill'] <=> $b['without_bill']) ?: strcasecmp($a['name'], $b['name']),
                        'pending_vendor_bill' => ($a['pending_vendor_bill'] <=> $b['pending_vendor_bill']) ?: strcasecmp($a['name'], $b['name']),
                        'stock_deficit' => ($a['stock_deficit'] <=> $b['stock_deficit']) ?: strcasecmp($a['name'], $b['name']),
                        default => 0,
                    };

                    return $direction === 'desc' ? -$cmp : $cmp;
                })->values();
            } else {
                // Default sorting:
                // If no search filter is given, order products with positive sellable, pending vendor bill, or stock deficit first
                if ($search === '') {
                    $currentInventoryRows = $currentInventoryRows->sortBy([
                        fn (array $a, array $b): int => ($b['current_sellable'] > 0 || $b['pending_vendor_bill'] > 0 || $b['stock_deficit'] > 0) <=> ($a['current_sellable'] > 0 || $a['pending_vendor_bill'] > 0 || $a['stock_deficit'] > 0),
                        fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']),
                    ])->values();
                } else {
                    $currentInventoryRows = $currentInventoryRows->sortBy(fn (array $a): string => strtolower($a['name']))->values();
                }
            }

            $page = max(1, $request->integer('page', 1));
            $perPage = 30;
            $currentInventory = new LengthAwarePaginator(
                $currentInventoryRows->forPage($page, $perPage)->values(),
                $currentInventoryRows->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $damageProductsQuery = Product::query()->where('is_active', true);
            if ($search !== '') {
                $damageProductsQuery->where(function (Builder $pq) use ($search): void {
                    $pq->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            }
            $damageProducts = $damageProductsQuery->orderBy('name')->get(['id', 'name', 'sku', 'unit']);
        }

        if ($tab === 'receive_bills') {
            $pendingBills = app(AdvanceReceiveReconciliationService::class)->paginateMatchCandidates([
                'search' => $search,
                'warehouse_id' => $selectedWarehouseId,
                'authorized_warehouse_ids' => $selectedWarehouseId !== null ? [$selectedWarehouseId] : $authorizedWarehouseIds,
            ], 20)->withQueryString();
        } elseif ($tab === 'match_details') {
            $matchDetailsPlan = $matchPlan ?? app(AutoAdvanceClearPlanningService::class)->buildAutoClearPlan($targetWarehouseForPlan, (int) $request->user()->id);
        } elseif ($tab === 'stock_without_bill') {
            $filteredUnbilledGrns = collect($unbilledAdvGrns);
            if ($search !== '') {
                $searchLower = strtolower($search);
                $filteredUnbilledGrns = $filteredUnbilledGrns->filter(function (array $grn) use ($searchLower): bool {
                    if (str_contains(strtolower($grn['grn_number'] ?? ''), $searchLower)) {
                        return true;
                    }
                    foreach ($grn['items'] as $item) {
                        if (str_contains(strtolower($item['name'] ?? ''), $searchLower) || str_contains(strtolower($item['sku'] ?? ''), $searchLower)) {
                            return true;
                        }
                    }

                    return false;
                })->values();
            }

            $page = max(1, $request->integer('page', 1));
            $perPage = 20;
            $stockWithoutBill = new LengthAwarePaginator(
                $filteredUnbilledGrns->forPage($page, $perPage)->values(),
                $filteredUnbilledGrns->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } elseif ($tab === 'shop_returns') {
            $shopReturnsQuery = StockMovement::query()
                ->where('type', StockMovementType::SaleReversal->value)
                ->whereDate('created_at', $date)
                ->when($selectedWarehouseId !== null, fn (Builder $q) => $q->where('warehouse_id', $selectedWarehouseId))
                ->when($authorizedWarehouseIds !== null, fn (Builder $q) => $q->whereIn('warehouse_id', $authorizedWarehouseIds))
                ->with([
                    'product:id,name,sku,unit',
                    'warehouse:id,name,code',
                    'shopOrderItem.shopOrder.shop:id,name,code',
                    'createdBy:id,name',
                ])
                ->orderByDesc('id');

            if ($search !== '') {
                $shopReturnsQuery->where(function (Builder $q) use ($search): void {
                    $q->whereHas('product', fn (Builder $pq) => $pq->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))
                        ->orWhereHas('shopOrderItem.shopOrder.shop', fn (Builder $sq) => $sq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            }

            $shopReturns = $shopReturnsQuery->paginate(20)->withQueryString();
        } elseif ($tab === 'damage') {
            $damageQuery = WastageEntry::query()
                ->whereDate('wastage_date', $date)
                ->when($selectedWarehouseId !== null, function (Builder $q) use ($selectedWarehouseId): void {
                    $q->where(function (Builder $sub) use ($selectedWarehouseId): void {
                        $sub->whereHas('batch', fn (Builder $bq) => $bq->where('warehouse_id', $selectedWarehouseId))
                            ->orWhereNull('batch_id');
                    });
                })
                ->when($authorizedWarehouseIds !== null, function (Builder $q) use ($authorizedWarehouseIds): void {
                    $q->where(function (Builder $sub) use ($authorizedWarehouseIds): void {
                        $sub->whereHas('batch', fn (Builder $bq) => $bq->whereIn('warehouse_id', $authorizedWarehouseIds))
                            ->orWhereNull('batch_id');
                    });
                })
                ->with([
                    'product:id,name,sku,unit',
                    'batch:id,reference,warehouse_id',
                    'recordedBy:id,name',
                ])
                ->orderByDesc('id');

            if ($search !== '') {
                $damageQuery->where(function (Builder $q) use ($search): void {
                    $q->whereHas('product', fn (Builder $pq) => $pq->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            }

            $damageEntries = $damageQuery->paginate(20)->withQueryString();
        } elseif ($tab === 'physical_check') {
            $productsQuery = Product::query()
                ->where('is_active', true)
                ->with('category:id,name');

            if ($search !== '') {
                $productsQuery->where(function (Builder $pq) use ($search): void {
                    $pq->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            }

            $checkProducts = $productsQuery->orderBy('name')->get();
            $stockByProduct = $currentStockByProduct;

            $checkRows = $checkProducts->map(function (Product $p) use ($stockByProduct): array {
                return [
                    'product' => $p,
                    'product_id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'unit' => $p->unit,
                    'category' => $p->category?->name ?? '—',
                    'erp_balance' => (float) ($stockByProduct[$p->id] ?? 0.0),
                ];
            });

            if ($search === '') {
                $checkRows = $checkRows->sortBy([
                    fn (array $a, array $b): int => ($b['erp_balance'] > 0) <=> ($a['erp_balance'] > 0),
                    fn (array $a, array $b): int => strcmp($a['name'], $b['name']),
                ])->values();
            }

            $page = max(1, $request->integer('page', 1));
            $perPage = 25;
            $physicalCheckProducts = new LengthAwarePaginator(
                $checkRows->forPage($page, $perPage)->values(),
                $checkRows->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            $adjustmentsQuery = StockAdjustment::query()
                ->whereDate('business_date', $date)
                ->when($selectedWarehouseId !== null, fn (Builder $q) => $q->where('warehouse_id', $selectedWarehouseId))
                ->when($authorizedWarehouseIds !== null, fn (Builder $q) => $q->whereIn('warehouse_id', $authorizedWarehouseIds))
                ->with(['product:id,name,sku,unit', 'warehouse:id,name', 'createdBy:id,name'])
                ->orderByDesc('id');
            $recentAdjustments = $adjustmentsQuery->get();
        } elseif ($tab === 'unit_differences') {
            $unitDifferences = app(AdvanceReceiveReconciliationService::class)->paginateUnitDifferences([
                'search' => $search,
                'warehouse_id' => $selectedWarehouseId,
                'authorized_warehouse_ids' => $selectedWarehouseId !== null ? [$selectedWarehouseId] : $authorizedWarehouseIds,
            ], 20)->withQueryString();
        }

        return view('admin.cashbook.reports.inventory', [
            'tab' => $tab,
            'section' => $tab,
            'timeframe' => $timeframe,
            'selectedDate' => $date,
            'prevDate' => $prevDate,
            'nextDate' => $nextDate,
            'isToday' => $isToday,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'shopId' => $shopId,
            'selectedWarehouseId' => $selectedWarehouseId,
            'availableWarehouses' => $availableWarehouses,
            'summary' => $summary,
            'currentStockByProduct' => $currentStockByProduct,
            'currentInventory' => $currentInventory,
            'pendingBills' => $pendingBills,
            'matchDetailsPlan' => $matchDetailsPlan,
            'stockWithoutBill' => $stockWithoutBill,
            'unbilledAdvRows' => $unbilledAdvRows,
            'unbilledAdvGrns' => $unbilledAdvGrns,
            'shopReturns' => $shopReturns,
            'damageEntries' => $damageEntries,
            'damageProducts' => $damageProducts,
            'physicalCheckProducts' => $physicalCheckProducts,
            'recentAdjustments' => $recentAdjustments,
            'unitDifferences' => $unitDifferences,
            'activeTab' => 'inventory',
        ]);
    }

    /**
     * Get day-level summary of pending bills for popup.
     */
    public function pendingBillsDaysSummary(Request $request): JsonResponse
    {
        $this->ensureAuthorized($request);

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

        $query = GoodsReceived::query()
            ->where('status', 'pending_approval')
            ->where(function (Builder $typeQ): void {
                $typeQ->where('receipt_type', '!=', 'warehouse_advance')
                    ->orWhereNull('receipt_type');
            })
            ->with(['items']);

        app(WarehouseReceiptReadScope::class)->receipts(
            $query,
            $requestedWarehouseId !== null ? [$requestedWarehouseId] : $authorizedWarehouseIds
        );

        $grns = $query->orderByDesc('received_at')->orderByDesc('id')->get();

        $grouped = $grns->groupBy(function (GoodsReceived $g): string {
            return $g->received_at instanceof Carbon
                ? $g->received_at->toDateString()
                : (string) Carbon::parse($g->received_at ?? now())->toDateString();
        });

        $days = [];
        $totalBills = 0;
        $totalQty = 0.0;

        foreach ($grouped as $dateStr => $dayGrns) {
            $billCount = $dayGrns->count();
            $dayQty = round((float) $dayGrns->sum(fn (GoodsReceived $g) => $g->items->sum('received_qty')), 2);
            $totalBills += $billCount;
            $totalQty += $dayQty;

            $days[] = [
                'date' => $dateStr,
                'formatted_date' => Carbon::parse($dateStr)->format('d M Y'),
                'bill_count' => $billCount,
                'total_qty' => $dayQty,
                'grn_ids' => $dayGrns->pluck('id')->all(),
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'warehouse_id' => $requestedWarehouseId,
                'total_bills' => $totalBills,
                'total_qty' => round($totalQty, 2),
                'days' => $days,
            ],
        ]);
    }

    /**
     * Get specific bills for an expanded date in the pending bills popup.
     */
    public function pendingBillsDayDetails(Request $request): JsonResponse
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        $dateStr = $validated['date'];
        $requestedWarehouseId = isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null;

        $authorizedWarehouseIds = app(WarehouseReceiptReadScope::class)->warehouseIds(
            $request->user(),
            $requestedWarehouseId
        );

        if ($requestedWarehouseId !== null && $authorizedWarehouseIds !== null && ! in_array($requestedWarehouseId, $authorizedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        $query = GoodsReceived::query()
            ->where('status', 'pending_approval')
            ->whereDate('received_at', $dateStr)
            ->where(function (Builder $typeQ): void {
                $typeQ->where('receipt_type', '!=', 'warehouse_advance')
                    ->orWhereNull('receipt_type');
            })
            ->with([
                'purchaseOrder.supplier',
                'items.product',
                'receivedBy:id,name',
            ]);

        app(WarehouseReceiptReadScope::class)->receipts(
            $query,
            $requestedWarehouseId !== null ? [$requestedWarehouseId] : $authorizedWarehouseIds
        );

        $grns = $query->orderBy('grn_number')->get();

        $bills = $grns->map(function (GoodsReceived $grn): array {
            $itemsSummary = $grn->items->map(function ($item): string {
                $pName = $item->product?->name ?? 'Product #'.$item->product_id;
                $qty = (float) $item->received_qty;
                $unit = $item->received_unit ?: ($item->product?->unit ?? 'kg');

                return "{$pName} ({$qty} {$unit})";
            })->implode(', ');

            return [
                'id' => $grn->id,
                'grn_number' => $grn->grn_number,
                'po_number' => $grn->purchaseOrder?->po_number ?? ($grn->purchaseOrder?->order_number ?? '—'),
                'supplier_name' => $grn->purchaseOrder?->supplier?->name ?? 'Supplier',
                'products_summary' => $itemsSummary ?: 'No items',
                'qty' => round((float) $grn->items->sum('received_qty'), 2),
                'unit' => $grn->items->first()?->received_unit ?: 'KG',
                'status' => 'Pending Approval',
                'is_extra' => (bool) $grn->is_extra,
            ];
        })->values()->all();

        return response()->json([
            'status' => 'success',
            'data' => [
                'date' => $dateStr,
                'formatted_date' => Carbon::parse($dateStr)->format('d M Y'),
                'bills' => $bills,
            ],
        ]);
    }

    /**
     * Batch accept selected pending bills using canonical approval flow and return auto-match preview.
     */
    public function acceptPendingBills(Request $request): JsonResponse
    {
        $this->ensureAuthorized($request);

        $user = $request->user();
        abort_unless(
            $user->isMainAdmin()
            || $user->hasRole('admin')
            || $user->hasRole('purchase')
            || $user->hasAnyPermission(['purchasing.grn.approve', 'accounting.dashboard.view', 'accounting.report.view']),
            403,
            'Unauthorized to approve bills.'
        );

        $validated = $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'dates' => ['nullable', 'array'],
            'dates.*' => ['string', 'date_format:Y-m-d'],
            'grn_ids' => ['nullable', 'array'],
            'grn_ids.*' => ['integer', 'exists:goods_received,id'],
        ]);

        $requestedWarehouseId = isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null;
        $authorizedWarehouseIds = app(WarehouseReceiptReadScope::class)->warehouseIds(
            $user,
            $requestedWarehouseId
        );

        if ($requestedWarehouseId !== null && $authorizedWarehouseIds !== null && ! in_array($requestedWarehouseId, $authorizedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        $dates = $validated['dates'] ?? null;
        $grnIds = $validated['grn_ids'] ?? null;

        $query = GoodsReceived::query()
            ->where(function (Builder $typeQ): void {
                $typeQ->where('receipt_type', '!=', 'warehouse_advance')
                    ->orWhereNull('receipt_type');
            })
            ->with(['items.product', 'items.purchaseOrderItem', 'purchaseOrder']);

        if (! empty($grnIds)) {
            $query->whereIn('id', $grnIds);
        } elseif (! empty($dates)) {
            $query->where('status', 'pending_approval')
                ->where(function (Builder $q) use ($dates): void {
                    foreach ($dates as $d) {
                        $q->orWhereDate('received_at', $d);
                    }
                });
        } else {
            $query->where('status', 'pending_approval');
        }

        app(WarehouseReceiptReadScope::class)->receipts(
            $query,
            $requestedWarehouseId !== null ? [$requestedWarehouseId] : $authorizedWarehouseIds
        );

        $candidates = $query->get();

        $approvedCount = 0;
        $alreadyApprovedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        $approveAction = app(ApproveGoodsReceiptAction::class);
        $userId = (int) $user->id;

        foreach ($candidates as $grn) {
            if ($grn->status === 'approved') {
                $alreadyApprovedCount++;

                continue;
            }

            if ($grn->status !== 'pending_approval' || $grn->items->isEmpty()) {
                $skippedCount++;

                continue;
            }

            try {
                $approveAction->execute($grn, $userId);
                $approvedCount++;
            } catch (Throwable $e) {
                Log::error("Failed to approve GRN #{$grn->id} in acceptPendingBills: {$e->getMessage()}", [
                    'grn_id' => $grn->id,
                    'exception' => $e,
                ]);
                $failedCount++;
            }
        }

        // Generate Auto Match Preview if warehouse is known
        $autoMatchPreview = null;
        if ($requestedWarehouseId !== null) {
            try {
                $plan = app(AutoAdvanceClearPlanningService::class)->buildAutoClearPlan(
                    $requestedWarehouseId,
                    $userId
                );

                $fullBills = (int) ($plan['summary']['full_bills'] ?? 0);
                $partialBills = (int) ($plan['summary']['partial_bills'] ?? 0);
                $skippedBills = (int) ($plan['summary']['skipped_bills'] ?? 0);
                $advancesFullyCleared = (int) ($plan['summary']['advances_fully_cleared'] ?? 0);

                $autoMatchPreview = [
                    'matchable_with_advances' => $fullBills,
                    'partial_match' => $partialBills,
                    'no_matching_advance' => $skippedBills,
                    'advances_that_can_fully_clear' => $advancesFullyCleared,
                    'matched_base_qty' => (float) ($plan['summary']['matched_base_qty'] ?? 0.0),
                    'plan_hash' => $plan['plan_hash'] ?? null,
                    'plan_data' => $plan,
                ];
            } catch (Throwable $e) {
                Log::warning("Could not build auto-match preview after acceptPendingBills: {$e->getMessage()}");
            }
        }

        $nowAwaitingReconciliationQuery = PurchaseOrder::query()
            ->whereNotIn('status', ['draft', 'cancelled', 'rejected'])
            ->where(function (Builder $pending): void {
                $pending->whereHas('goodsReceiveds', fn ($receipts) => app(WarehouseReceiptStateResolver::class)->filter($receipts, 'pending'))
                    ->orWhere(function ($withoutReceipt): void {
                        $withoutReceipt->whereDoesntHave('goodsReceiveds')->whereIn('status', ['approved', 'sent_to_supplier', 'partially_received']);
                    });
            });
        app(WarehouseReceiptReadScope::class)->orders(
            $nowAwaitingReconciliationQuery,
            $requestedWarehouseId !== null ? [$requestedWarehouseId] : $authorizedWarehouseIds
        );
        $nowAwaitingReconciliationCount = $nowAwaitingReconciliationQuery->count();

        return response()->json([
            'status' => 'success',
            'message' => "Approved: {$approvedCount}, Now awaiting reconciliation: {$nowAwaitingReconciliationCount}",
            'data' => [
                'approved' => $approvedCount,
                'already_approved' => $alreadyApprovedCount,
                'skipped' => $skippedCount,
                'failed' => $failedCount,
                'now_awaiting_reconciliation' => $nowAwaitingReconciliationCount,
                'warehouse_id' => $requestedWarehouseId,
                'auto_match_preview' => $autoMatchPreview,
            ],
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

    public function fixAdvanceUnits(Request $request): JsonResponse
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $warehouseId = isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null;
        $authorizedWarehouseIds = app(WarehouseReceiptReadScope::class)->warehouseIds($request->user(), $warehouseId);
        if ($authorizedWarehouseIds !== null && $warehouseId !== null && ! in_array($warehouseId, $authorizedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        $filters = [
            'date' => $validated['date'] ?? null,
            'search' => trim((string) ($validated['search'] ?? '')),
            'warehouse_id' => $warehouseId,
            'authorized_warehouse_ids' => $authorizedWarehouseIds,
        ];

        try {
            $result = app(AdvanceReceiveReconciliationService::class)->fixAdvanceUnits($filters, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Advance units fixed successfully.',
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            Log::error('Fix advance units execution failed', [
                'filters' => $filters,
                'exception_class' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fix advance units: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Move one or multiple products to damage (wastage) in a single transaction.
     */
    public function moveToDamage(Request $request): RedirectResponse
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.reason' => ['nullable', 'string'],
            'items.*.grade' => ['nullable', 'string'],
            'common_reason' => ['nullable', 'string'],
            'wastage_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'tab' => ['nullable', 'string'],
        ]);

        $requestedWarehouseId = $validated['warehouse_id'] ?? ($request->filled('warehouse_id') ? $request->integer('warehouse_id') : null);
        $authorizedWarehouseIds = app(WarehouseReceiptReadScope::class)->warehouseIds(
            $request->user(),
            $requestedWarehouseId
        );

        if ($requestedWarehouseId !== null && $authorizedWarehouseIds !== null && ! in_array($requestedWarehouseId, $authorizedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        $targetWarehouseId = $requestedWarehouseId ?? ($authorizedWarehouseIds[0] ?? Warehouse::query()->active()->first()?->id ?? 1);
        $defaultReasonStr = $validated['common_reason'] ?? 'transit_damage';
        $defaultReason = WastageReason::tryFrom($defaultReasonStr) ?? WastageReason::TransitDamage;
        $wastageDate = $validated['wastage_date'];
        $notes = $validated['notes'] ?? null;
        $userId = (int) $request->user()->id;

        /** @var WastageService $wastageService */
        $wastageService = app(WastageService::class);
        $stockRepo = app(StockMovementRepository::class);

        $productIds = collect($validated['items'])->pluck('product_id')->unique()->map(fn ($id) => (int) $id)->values()->all();
        $recordedCount = 0;

        try {
            DB::transaction(function () use ($validated, $productIds, $targetWarehouseId, $defaultReason, $wastageDate, $notes, $userId, $wastageService, $stockRepo, &$recordedCount) {
                // 1. Concurrency lock on StockBatch rows for selected products & warehouse
                StockBatch::query()
                    ->whereIn('product_id', $productIds)
                    ->where('warehouse_id', $targetWarehouseId)
                    ->lockForUpdate()
                    ->get(['id']);

                $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

                // 2. Canonical stock levels from StockMovementRepository for target warehouse & date
                $currentStockCollection = $stockRepo->currentStockByProductAndGrade($wastageDate, $targetWarehouseId);
                $stockByProduct = $currentStockCollection->groupBy('product_id')->map(fn (Collection $rows): float => (float) $rows->sum('current_stock'));

                // 3. First Pass: Aggregate requested qty by product and prevalidate all items against current sellable stock
                $requestedByProduct = [];
                foreach ($validated['items'] as $item) {
                    $pId = (int) $item['product_id'];
                    $qty = (float) $item['quantity'];

                    if ($qty <= 0.0) {
                        $prod = $products->get($pId);
                        $pName = $prod?->name ?? "Product #{$pId}";
                        throw ValidationException::withMessages([
                            'items' => "{$pName}: requested damage quantity must be greater than zero.",
                        ]);
                    }

                    $requestedByProduct[$pId] = ($requestedByProduct[$pId] ?? 0.0) + $qty;
                }

                foreach ($requestedByProduct as $pId => $totalRequested) {
                    $prod = $products->get($pId);
                    $pName = $prod?->name ?? "Product #{$pId}";
                    $unit = $prod?->unit ?? 'KG';
                    $currentBalance = (float) ($stockByProduct[$pId] ?? 0.0);
                    $currentSellable = max(0.0, $currentBalance);

                    if ($totalRequested > $currentSellable + 0.0001) {
                        if ($currentSellable <= 0.0) {
                            $errorMsg = "{$pName}: requested ".number_format($totalRequested, 2)." {$unit}, but currently has no sellable stock available (0.00 {$unit}).";
                        } else {
                            $errorMsg = "{$pName}: requested ".number_format($totalRequested, 2)." {$unit}, but only ".number_format($currentSellable, 2)." {$unit} is currently available.";
                        }

                        throw ValidationException::withMessages([
                            'items' => $errorMsg,
                        ]);
                    }
                }

                // 4. Second Pass: Atomic execution for all items
                foreach ($validated['items'] as $item) {
                    $pId = (int) $item['product_id'];
                    $qty = (float) $item['quantity'];
                    $prod = $products->get($pId);
                    $unit = $prod?->unit ?? 'kg';

                    $itemReason = ! empty($item['reason'])
                        ? (WastageReason::tryFrom((string) $item['reason']) ?? $defaultReason)
                        : $defaultReason;

                    $grade = ! empty($item['grade']) ? (string) $item['grade'] : 'U';

                    // Look up latest batch in target warehouse
                    $batch = StockBatch::query()
                        ->where('product_id', $pId)
                        ->where('warehouse_id', $targetWarehouseId)
                        ->latest('id')
                        ->first();

                    $costPerKg = $batch?->cost_per_kg ? (float) $batch->cost_per_kg : 0.0;
                    if ($costPerKg <= 0.0) {
                        $costPerKg = (float) ($prod?->cost_price ?? 0.0);
                    }

                    $data = new WastageEntryData(
                        productId: $pId,
                        batchId: $batch?->id,
                        grade: $grade,
                        quantity: $qty,
                        costPerKg: $costPerKg,
                        reason: $itemReason,
                        wastageDate: $wastageDate,
                        notes: $notes,
                    );

                    $wastageService->record($data, $userId);

                    if ($batch) {
                        StockMovement::create([
                            'batch_id' => $batch->id,
                            'warehouse_id' => $targetWarehouseId,
                            'product_id' => $pId,
                            'grade' => $grade,
                            'type' => StockMovementType::Wastage->value,
                            'quantity' => $qty,
                            'unit' => $unit,
                            'cost_per_unit' => $costPerKg,
                            'created_by' => $userId,
                            'created_at' => Carbon::parse($wastageDate)->setTime(12, 0, 0),
                        ]);
                    }

                    $recordedCount++;
                }
            });
        } catch (ValidationException $e) {
            $returnTab = $request->input('tab', 'damage');
            $params = array_filter([
                'tab' => $returnTab,
                'date' => $request->input('date', $wastageDate),
                'warehouse_id' => $requestedWarehouseId,
            ], fn ($v) => $v !== null && $v !== '');

            return redirect()->route('admin.cashbook.inventory', $params)
                ->withErrors($e->errors())
                ->withInput();
        }

        $returnTab = $request->input('tab', 'damage');
        $params = array_filter([
            'tab' => $returnTab,
            'date' => $request->input('date', $wastageDate),
            'warehouse_id' => $requestedWarehouseId,
        ], fn ($v) => $v !== null && $v !== '');

        return redirect()->route('admin.cashbook.inventory', $params)
            ->with('success', "{$recordedCount} product(s) moved to damage successfully.");
    }

    /**
     * Admin Manual Clear for selected open Advance GRNs without matching them to a vendor bill.
     * Pure paperwork/administrative clear with zero physical inventory effect.
     */
    public function clearAdvances(Request $request): RedirectResponse
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'advance_ids' => ['required', 'array', 'min:1'],
            'advance_ids.*' => ['required', 'integer', 'exists:goods_received,id'],
            'reason' => ['required', 'string', 'min:2', 'max:500'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'date' => ['nullable', 'date'],
            'tab' => ['nullable', 'string'],
        ]);

        $advanceIds = array_values(array_unique(array_map('intval', $validated['advance_ids'])));
        $requestedWarehouseId = $validated['warehouse_id'] ?? ($request->filled('warehouse_id') ? $request->integer('warehouse_id') : null);
        $authorizedWarehouseIds = app(WarehouseReceiptReadScope::class)->warehouseIds(
            $request->user(),
            $requestedWarehouseId
        );

        if ($requestedWarehouseId !== null && $authorizedWarehouseIds !== null && ! in_array($requestedWarehouseId, $authorizedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        $calc = app(AdvanceAvailableBalanceCalculator::class);
        $clearedCount = 0;
        $reason = trim($validated['reason']);

        try {
            DB::transaction(function () use ($advanceIds, $requestedWarehouseId, $authorizedWarehouseIds, $reason, $calc, $request, &$clearedCount): void {
                // Concurrency lock on selected GoodsReceived advance rows
                $lockedAdvances = GoodsReceived::query()
                    ->whereIn('id', $advanceIds)
                    ->lockForUpdate()
                    ->with('items')
                    ->get();

                if ($lockedAdvances->count() !== count($advanceIds)) {
                    throw ValidationException::withMessages([
                        'advance_ids' => 'One or more selected Advances could not be found or are locked. Please refresh and try again.',
                    ]);
                }

                // Preload matches to accurately calculate remaining unmatched quantity at time of clear
                $matches = AdvanceReceiveMatch::query()
                    ->whereIn('advance_goods_received_id', $advanceIds)
                    ->get();

                foreach ($lockedAdvances as $adv) {
                    // Check warehouse scope
                    if ($requestedWarehouseId !== null && (int) $adv->warehouse_id !== (int) $requestedWarehouseId) {
                        throw ValidationException::withMessages([
                            'advance_ids' => "Advance #{$adv->grn_number} does not belong to the selected warehouse.",
                        ]);
                    }

                    if ($authorizedWarehouseIds !== null && ! in_array((int) $adv->warehouse_id, $authorizedWarehouseIds, true)) {
                        throw ValidationException::withMessages([
                            'advance_ids' => "Advance #{$adv->grn_number} belongs to an unauthorized warehouse.",
                        ]);
                    }

                    // Check receipt type
                    $isAdvance = ($adv->receipt_type === 'warehouse_advance')
                        || ($adv->receipt_type === null && $adv->purchase_order_id === null);

                    if (! $isAdvance) {
                        throw ValidationException::withMessages([
                            'advance_ids' => "Receipt #{$adv->grn_number} is not an advance receipt.",
                        ]);
                    }

                    // Check status & bill_status
                    if ($adv->status === 'cancelled' || $adv->bill_status !== 'bill_pending') {
                        throw ValidationException::withMessages([
                            'advance_ids' => "Advance #{$adv->grn_number} is no longer available for manual clear. Refresh and try again.",
                        ]);
                    }

                    // Calculate remaining unmatched quantity
                    $itemBalances = $calc->calculateItemAvailableBase($adv, null, $matches);
                    $totalRemaining = array_sum($itemBalances);

                    $oldBillStatus = $adv->bill_status;

                    // Update bill_status to bill_available (matching ClearOldAdvancesCommand semantics)
                    $adv->update([
                        'bill_status' => 'bill_available',
                        'updated_at' => now(),
                    ]);

                    // Mandatory Spatie activity log recording administrative clear
                    activity('purchasing')
                        ->causedBy($request->user())
                        ->performedOn($adv)
                        ->withProperties([
                            'action' => 'manual_advance_clear',
                            'advance_id' => $adv->id,
                            'grn_number' => $adv->grn_number,
                            'warehouse_id' => $adv->warehouse_id,
                            'reason' => $reason,
                            'cleared_at' => now()->toIso8601String(),
                            'previous_bill_status' => $oldBillStatus,
                            'new_bill_status' => 'bill_available',
                            'remaining_unmatched_qty' => round((float) $totalRemaining, 2),
                        ])
                        ->log('Advance manually cleared without vendor bill match');

                    $clearedCount++;
                }
            });
        } catch (ValidationException $e) {
            $returnTab = $request->input('tab', 'stock_without_bill');
            $params = array_filter([
                'tab' => $returnTab,
                'date' => $request->input('date'),
                'warehouse_id' => $requestedWarehouseId,
            ], fn ($v) => $v !== null && $v !== '');

            return redirect()->route('admin.cashbook.inventory', $params)
                ->withErrors($e->errors())
                ->withInput();
        }

        $returnTab = $request->input('tab', 'stock_without_bill');
        $params = array_filter([
            'tab' => $returnTab,
            'date' => $request->input('date'),
            'warehouse_id' => $requestedWarehouseId,
        ], fn ($v) => $v !== null && $v !== '');

        return redirect()->route('admin.cashbook.inventory', $params)
            ->with('success', "{$clearedCount} Advance(s) manually cleared. Warehouse inventory was not changed.");
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
