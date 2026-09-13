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
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseProductFilter;
use App\Models\Shop;
use App\Models\ShopDailyProductPrice;
use App\Models\ShopInvoice;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Inventory\WastageService;
use App\Services\Pricing\PriceBoardService;
use App\Services\Purchasing\AdvanceAvailableBalanceCalculator;
use App\Services\Purchasing\AdvanceReceiveReconciliationService;
use App\Services\Purchasing\AutoAdvanceClearExecutionService;
use App\Services\Purchasing\AutoAdvanceClearPlanningService;
use App\Services\Purchasing\DailyPendingAdvanceWhatsAppService;
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
     * Daily Inventory Comparison (Advance vs Bill by Product and Unit)
     */
    public function inventory(Request $request): View
    {
        $this->ensureAuthorized($request);

        $selectedWarehouseId = $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null;
        if ($selectedWarehouseId !== null && ! Warehouse::query()->where('id', $selectedWarehouseId)->exists()) {
            abort(404, 'Warehouse not found.');
        }

        $authorizedWarehouseIds = app(WarehouseReceiptReadScope::class)->warehouseIds(
            $request->user(),
            $selectedWarehouseId
        );

        if ($selectedWarehouseId !== null && $authorizedWarehouseIds !== null && ! in_array($selectedWarehouseId, $authorizedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        if ($request->filled('date')) {
            $date = Carbon::parse((string) $request->input('date'))->toDateString();
        } else {
            $latestAdvDate = GoodsReceived::query()
                ->where('receipt_type', 'warehouse_advance')
                ->where('status', '!=', 'cancelled')
                ->whereNotNull('received_at')
                ->when($selectedWarehouseId !== null, fn (Builder $wq) => $wq->where('warehouse_id', $selectedWarehouseId))
                ->when($selectedWarehouseId === null && $authorizedWarehouseIds !== null, fn (Builder $wq) => $wq->whereIn('warehouse_id', $authorizedWarehouseIds))
                ->latest('received_at')
                ->value('received_at');

            $latestBillDate = GoodsReceivedItem::query()
                ->whereHas('goodsReceived', function (Builder $q): void {
                    $q->where(function (Builder $sub): void {
                        $sub->where('receipt_type', '!=', 'warehouse_advance')
                            ->orWhereNull('receipt_type');
                    })
                        ->where('status', '!=', 'cancelled')
                        ->whereNotNull('received_at');
                })
                ->when($selectedWarehouseId !== null, fn (Builder $q) => $q->whereHas('product', fn (Builder $pq) => $pq->where('default_warehouse_id', $selectedWarehouseId)))
                ->when($selectedWarehouseId === null && $authorizedWarehouseIds !== null, fn (Builder $q) => $q->whereHas('product', fn (Builder $pq) => $pq->whereIn('default_warehouse_id', $authorizedWarehouseIds)))
                ->join('goods_received', 'goods_received_items.goods_received_id', '=', 'goods_received.id')
                ->latest('goods_received.received_at')
                ->value('goods_received.received_at');

            $latestDates = array_filter([$latestAdvDate, $latestBillDate]);
            $latestReceiptDate = ! empty($latestDates) ? max($latestDates) : null;

            $date = $latestReceiptDate
                ? ($latestReceiptDate instanceof Carbon ? $latestReceiptDate->toDateString() : Carbon::parse($latestReceiptDate)->toDateString())
                : today()->toDateString();
        }

        $carbonDate = Carbon::parse($date);
        $prevDate = $carbonDate->copy()->subDay()->toDateString();
        $nextDate = $carbonDate->copy()->addDay()->toDateString();

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->when($authorizedWarehouseIds !== null, fn (Builder $q) => $q->whereIn('id', $authorizedWarehouseIds))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $selectedWarehouse = $selectedWarehouseId !== null ? $warehouses->firstWhere('id', $selectedWarehouseId) : null;
        $comparisonRows = $this->buildInventoryComparisonRows($date, $selectedWarehouseId, $authorizedWarehouseIds);
        $pendingBillsCollection = $this->getPendingBillsForDate($date, $selectedWarehouseId, $authorizedWarehouseIds);
        $pendingBillsCount = $pendingBillsCollection->count();
        $pendingBillsList = $this->formatPendingBillsList($pendingBillsCollection);

        $summary = [
            'total_advance_qty' => round((float) $comparisonRows->sum('advance_qty'), 2),
            'total_bill_qty' => round((float) $comparisonRows->sum('bill_qty'), 2),
            'total_matched_qty' => round((float) $comparisonRows->sum('matched_bill_qty'), 2),
            'total_unmatched_bill_qty' => round((float) $comparisonRows->sum('unmatched_bill_qty'), 2),
            'unit_fix_count' => $comparisonRows->where('unit_mismatch', true)->count(),
            'overall_match_pct' => (float) $comparisonRows->sum('bill_qty') > 0
                ? round(((float) $comparisonRows->sum('matched_bill_qty') / (float) $comparisonRows->sum('bill_qty')) * 100, 1)
                : 0.0,
        ];

        return view('admin.cashbook.reports.inventory', [
            'date' => $date,
            'prevDate' => $prevDate,
            'nextDate' => $nextDate,
            'warehouses' => $warehouses,
            'selectedWarehouse' => $selectedWarehouse,
            'selectedWarehouseId' => $selectedWarehouseId,
            'rows' => $comparisonRows,
            'pendingBillsCount' => $pendingBillsCount,
            'pendingBillsList' => $pendingBillsList,
            'summary' => $summary,
        ]);
    }

    /**
     * Build day-wise comparison rows between Advance receipts and Purchase Bills for a given date.
     */
    protected function buildInventoryComparisonRows(string $date, ?int $selectedWarehouseId, ?array $authorizedWarehouseIds): Collection
    {
        // 1. Query all Advance received items for the selected date (filtered by goods_received.warehouse_id)
        $advanceItems = GoodsReceivedItem::query()
            ->whereHas('goodsReceived', function (Builder $q) use ($date, $selectedWarehouseId, $authorizedWarehouseIds): void {
                $q->where('receipt_type', 'warehouse_advance')
                    ->where('status', '!=', 'cancelled')
                    ->whereDate('received_at', $date)
                    ->when($selectedWarehouseId !== null, fn (Builder $wq) => $wq->where('warehouse_id', $selectedWarehouseId))
                    ->when($selectedWarehouseId === null && $authorizedWarehouseIds !== null, fn (Builder $wq) => $wq->whereIn('warehouse_id', $authorizedWarehouseIds));
            })
            ->with(['product', 'goodsReceived'])
            ->get();

        // 2. Query all normal Purchase Bill received items for the selected date (filtered by product.default_warehouse_id)
        $billItems = GoodsReceivedItem::query()
            ->whereHas('goodsReceived', function (Builder $q) use ($date): void {
                $q->where(function (Builder $sub): void {
                    $sub->where('receipt_type', '!=', 'warehouse_advance')
                        ->orWhereNull('receipt_type');
                })
                    ->where('status', '!=', 'cancelled')
                    ->whereDate('received_at', $date);
            })
            ->when($selectedWarehouseId !== null, function (Builder $q) use ($selectedWarehouseId): void {
                $q->whereHas('product', fn (Builder $pq) => $pq->where('default_warehouse_id', $selectedWarehouseId));
            })
            ->when($selectedWarehouseId === null && $authorizedWarehouseIds !== null, function (Builder $q) use ($authorizedWarehouseIds): void {
                $q->whereHas('product', fn (Builder $pq) => $pq->whereIn('default_warehouse_id', $authorizedWarehouseIds));
            })
            ->with(['product', 'goodsReceived'])
            ->get();

        $allProductIds = $advanceItems->pluck('product_id')->merge($billItems->pluck('product_id'))->unique();
        $products = Product::query()->whereIn('id', $allProductIds)->get()->keyBy('id');

        // Preload matches for all advance and bill items on this date
        $advItemIds = $advanceItems->pluck('id')->all();
        $billItemIds = $billItems->pluck('id')->all();

        $existingMatches = AdvanceReceiveMatch::query()
            ->where(function (Builder $mq) use ($advItemIds, $billItemIds): void {
                $mq->whereIn('advance_goods_received_item_id', $advItemIds)
                    ->orWhereIn('bill_goods_received_item_id', $billItemIds);
            })
            ->get();

        $rows = [];

        $formatNumber = static function (float $val): string {
            return (abs($val - (int) $val) < 0.0001)
                ? (string) (int) $val
                : rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.');
        };

        foreach ($allProductIds as $productId) {
            $product = $products->get($productId);
            $prodAdvItems = $advanceItems->where('product_id', $productId);
            $prodBillItems = $billItems->where('product_id', $productId);

            // Group advance items by unit
            $advByUnit = [];
            foreach ($prodAdvItems as $item) {
                $unit = trim((string) ($item->received_unit ?: ($product?->unit ?? 'kg')));
                $uKey = mb_strtolower($unit);
                if (! isset($advByUnit[$uKey])) {
                    $advByUnit[$uKey] = ['unit' => $unit, 'qty' => 0.0, 'items' => []];
                }
                $advByUnit[$uKey]['qty'] += (float) $item->received_qty;
                $advByUnit[$uKey]['items'][] = [
                    'id' => $item->id,
                    'grn_number' => $item->goodsReceived?->grn_number ?? 'GRN-'.$item->goods_received_id,
                    'type' => 'Advance',
                    'qty' => (float) $item->received_qty,
                    'unit' => $unit,
                ];
            }

            // Group bill items by unit
            $billByUnit = [];
            foreach ($prodBillItems as $item) {
                $unit = trim((string) ($item->received_unit ?: ($product?->unit ?? 'kg')));
                $uKey = mb_strtolower($unit);
                if (! isset($billByUnit[$uKey])) {
                    $billByUnit[$uKey] = ['unit' => $unit, 'qty' => 0.0, 'items' => []];
                }
                $billByUnit[$uKey]['qty'] += (float) $item->received_qty;
                $billByUnit[$uKey]['items'][] = [
                    'id' => $item->id,
                    'grn_number' => $item->goodsReceived?->grn_number ?? 'GRN-'.$item->goods_received_id,
                    'type' => 'Bill',
                    'qty' => (float) $item->received_qty,
                    'unit' => $unit,
                ];
            }

            $advUnitKeys = array_keys($advByUnit);
            $billUnitKeys = array_keys($billByUnit);

            $matchingUnitKeys = array_intersect($advUnitKeys, $billUnitKeys);
            $unmatchedAdvKeys = array_diff($advUnitKeys, $matchingUnitKeys);
            $unmatchedBillKeys = array_diff($billUnitKeys, $matchingUnitKeys);

            // 1. Process exact matching units
            foreach ($matchingUnitKeys as $uKey) {
                $advData = $advByUnit[$uKey];
                $billData = $billByUnit[$uKey];
                $unit = $advData['unit'];

                $advQty = (float) $advData['qty'];
                $billQty = (float) $billData['qty'];
                $diff = $advQty - $billQty;

                $advItemIdsInGroup = collect($advData['items'])->pluck('id')->all();
                $billItemIdsInGroup = collect($billData['items'])->pluck('id')->all();

                $matchedAdvQty = (float) $existingMatches->whereIn('advance_goods_received_item_id', $advItemIdsInGroup)->sum('matched_qty');
                $matchedBillQty = (float) $existingMatches->whereIn('bill_goods_received_item_id', $billItemIdsInGroup)->sum('matched_qty');

                $unmatchedAdvQty = max(0.0, round($advQty - $matchedAdvQty, 3));
                $unmatchedBillQty = max(0.0, round($billQty - $matchedBillQty, 3));

                $matchPct = $billQty > 0 ? ($matchedBillQty / $billQty) * 100 : 0.0;

                if (abs($diff) < 0.0001) {
                    $formattedDiff = '0';
                } else {
                    $prefix = $diff > 0 ? '+' : '';
                    $formattedDiff = $prefix.$formatNumber($diff).' '.$unit;
                }

                $actionType = 'none';
                if ($billQty > 0 && $unmatchedBillQty <= 0.0001) {
                    $actionType = 'matched';
                } elseif ($unmatchedAdvQty > 0.0001 && $unmatchedBillQty > 0.0001) {
                    $actionType = $matchedBillQty <= 0.0001 ? 'match' : 'match_remaining';
                }

                $rows[] = [
                    'product_id' => $productId,
                    'product_name' => $product?->name ?? 'Unknown Product',
                    'sku' => $product?->sku ?? '',
                    'product_code' => $product?->sku ?? '',
                    'unit' => $unit,
                    'advance_qty' => $advQty,
                    'bill_qty' => $billQty,
                    'formatted_advance' => $formatNumber($advQty).' '.$unit,
                    'formatted_bill' => $formatNumber($billQty).' '.$unit,
                    'diff' => $diff,
                    'formatted_diff' => $formattedDiff,
                    'matched_bill_qty' => $matchedBillQty,
                    'unmatched_bill_qty' => $unmatchedBillQty,
                    'bill_pending' => $unmatchedBillQty,
                    'unmatched_adv_qty' => $unmatchedAdvQty,
                    'match_pct' => $matchPct,
                    'formatted_match_pct' => round($matchPct).'%',
                    'unit_mismatch' => false,
                    'action_type' => $actionType,
                    'editable_items' => array_merge($advData['items'], $billData['items']),
                ];
            }

            // 2. Process unmatched: If both unmatched advance and unmatched bill exist, it's a UNIT MISMATCH
            if (! empty($unmatchedAdvKeys) && ! empty($unmatchedBillKeys)) {
                $advQtyTotal = 0.0;
                $advUnitNames = [];
                $advItemsList = [];
                foreach ($unmatchedAdvKeys as $uKey) {
                    $advQtyTotal += $advByUnit[$uKey]['qty'];
                    $advUnitNames[] = $advByUnit[$uKey]['unit'];
                    $advItemsList = array_merge($advItemsList, $advByUnit[$uKey]['items']);
                }

                $billQtyTotal = 0.0;
                $billUnitNames = [];
                $billItemsList = [];
                foreach ($unmatchedBillKeys as $uKey) {
                    $billQtyTotal += $billByUnit[$uKey]['qty'];
                    $billUnitNames[] = $billByUnit[$uKey]['unit'];
                    $billItemsList = array_merge($billItemsList, $billByUnit[$uKey]['items']);
                }

                $advUnitStr = implode('/', array_unique($advUnitNames));
                $billUnitStr = implode('/', array_unique($billUnitNames));

                $rows[] = [
                    'product_id' => $productId,
                    'product_name' => $product?->name ?? 'Unknown Product',
                    'sku' => $product?->sku ?? '',
                    'product_code' => $product?->sku ?? '',
                    'unit' => $advUnitStr.' vs '.$billUnitStr,
                    'advance_qty' => $advQtyTotal,
                    'bill_qty' => $billQtyTotal,
                    'formatted_advance' => $formatNumber($advQtyTotal).' '.$advUnitStr,
                    'formatted_bill' => $formatNumber($billQtyTotal).' '.$billUnitStr,
                    'diff' => null,
                    'formatted_diff' => 'Unit Mismatch',
                    'matched_bill_qty' => 0.0,
                    'unmatched_bill_qty' => $billQtyTotal,
                    'bill_pending' => $billQtyTotal,
                    'unmatched_adv_qty' => $advQtyTotal,
                    'match_pct' => null,
                    'formatted_match_pct' => '--',
                    'unit_mismatch' => true,
                    'action_type' => 'fix_unit',
                    'editable_items' => array_merge($advItemsList, $billItemsList),
                ];
            } else {
                // Unmatched Advance only (No bill with this unit)
                foreach ($unmatchedAdvKeys as $uKey) {
                    $advData = $advByUnit[$uKey];
                    $unit = $advData['unit'];
                    $advQty = (float) $advData['qty'];
                    $diff = $advQty;

                    $rows[] = [
                        'product_id' => $productId,
                        'product_name' => $product?->name ?? 'Unknown Product',
                        'sku' => $product?->sku ?? '',
                        'product_code' => $product?->sku ?? '',
                        'unit' => $unit,
                        'advance_qty' => $advQty,
                        'bill_qty' => 0.0,
                        'formatted_advance' => $formatNumber($advQty).' '.$unit,
                        'formatted_bill' => '0 '.$unit,
                        'diff' => $diff,
                        'formatted_diff' => '+'.$formatNumber($diff).' '.$unit,
                        'matched_bill_qty' => 0.0,
                        'unmatched_bill_qty' => 0.0,
                        'bill_pending' => 0.0,
                        'unmatched_adv_qty' => $advQty,
                        'match_pct' => 0.0,
                        'formatted_match_pct' => '0%',
                        'unit_mismatch' => false,
                        'action_type' => 'none',
                        'editable_items' => $advData['items'],
                    ];
                }

                // Unmatched Bill only (No advance with this unit)
                foreach ($unmatchedBillKeys as $uKey) {
                    $billData = $billByUnit[$uKey];
                    $unit = $billData['unit'];
                    $billQty = (float) $billData['qty'];
                    $diff = -$billQty;

                    $rows[] = [
                        'product_id' => $productId,
                        'product_name' => $product?->name ?? 'Unknown Product',
                        'sku' => $product?->sku ?? '',
                        'product_code' => $product?->sku ?? '',
                        'unit' => $unit,
                        'advance_qty' => 0.0,
                        'bill_qty' => $billQty,
                        'formatted_advance' => '0 '.$unit,
                        'formatted_bill' => $formatNumber($billQty).' '.$unit,
                        'diff' => $diff,
                        'formatted_diff' => '-'.$formatNumber($billQty).' '.$unit,
                        'matched_bill_qty' => 0.0,
                        'unmatched_bill_qty' => $billQty,
                        'bill_pending' => $billQty,
                        'unmatched_adv_qty' => 0.0,
                        'match_pct' => 0.0,
                        'formatted_match_pct' => '0%',
                        'unit_mismatch' => false,
                        'action_type' => 'none',
                        'editable_items' => $billData['items'],
                    ];
                }
            }
        }

        return collect($rows)
            ->sortBy([
                ['product_name', 'asc'],
                ['unit', 'asc'],
            ])
            ->values();
    }

    /**
     * Update received_unit metadata on a specific goods received item.
     */
    public function updateItemUnit(Request $request): JsonResponse|RedirectResponse
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'goods_received_item_id' => ['required', 'integer', 'exists:goods_received_items,id'],
            'new_unit' => ['required', 'string', 'max:50'],
        ]);

        $item = GoodsReceivedItem::with(['goodsReceived', 'product'])->findOrFail($validated['goods_received_item_id']);

        $warehouseId = $item->goodsReceived?->warehouse_id;
        $authorizedWarehouseIds = app(WarehouseReceiptReadScope::class)->warehouseIds($request->user(), $warehouseId);
        if ($authorizedWarehouseIds !== null && $warehouseId !== null && ! in_array($warehouseId, $authorizedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        $oldUnit = $item->received_unit;
        $newUnit = trim($validated['new_unit']);

        // Update ONLY received_unit metadata on the item - do not alter quantities or stock batches
        $item->received_unit = $newUnit;
        $item->save();

        activity()
            ->performedOn($item)
            ->causedBy($request->user())
            ->withProperties([
                'action' => 'update_received_item_unit',
                'goods_received_id' => $item->goods_received_id,
                'goods_received_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'old_unit' => $oldUnit,
                'new_unit' => $newUnit,
            ])
            ->log("Updated received unit for item #{$item->id} from {$oldUnit} to {$newUnit}");

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Unit updated successfully.',
                'data' => [
                    'id' => $item->id,
                    'old_unit' => $oldUnit,
                    'new_unit' => $newUnit,
                ],
            ]);
        }

        return redirect()->back()->with('success', "Unit updated from {$oldUnit} to {$newUnit}.");
    }

    /**
     * Perform FIFO inventory matching for a specific product and unit within the selected date.
     */
    protected function executeDayInventoryMatch(
        string $date,
        int $productId,
        string $unit,
        ?int $warehouseId,
        ?array $authorizedWarehouseIds,
        int $userId
    ): array {
        /** @var Product $product */
        $product = Product::findOrFail($productId);
        $normalizedUnit = ProductUnit::normalizeUnit($unit);

        // 1. Fetch same-day Advance items
        $advanceItems = GoodsReceivedItem::query()
            ->where('product_id', $productId)
            ->whereHas('goodsReceived', function (Builder $q) use ($date, $warehouseId, $authorizedWarehouseIds): void {
                $q->where('receipt_type', 'warehouse_advance')
                    ->where('status', '!=', 'cancelled')
                    ->whereDate('received_at', $date)
                    ->when($warehouseId !== null, fn (Builder $wq) => $wq->where('warehouse_id', $warehouseId))
                    ->when($warehouseId === null && $authorizedWarehouseIds !== null, fn (Builder $wq) => $wq->whereIn('warehouse_id', $authorizedWarehouseIds));
            })
            ->with(['goodsReceived.stockBatches'])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->filter(fn (GoodsReceivedItem $item): bool => ProductUnit::normalizeUnit((string) $item->received_unit) === $normalizedUnit);

        // 2. Fetch same-day RECEIVED Bill items (must be approved / received)
        $billItems = GoodsReceivedItem::query()
            ->where('product_id', $productId)
            ->whereHas('goodsReceived', function (Builder $q) use ($date): void {
                $q->where(function (Builder $sub): void {
                    $sub->where('receipt_type', '!=', 'warehouse_advance')
                        ->orWhereNull('receipt_type');
                })
                    ->where('status', '!=', 'cancelled')
                    ->whereDate('received_at', $date);
            })
            ->when($warehouseId !== null, function (Builder $q) use ($warehouseId): void {
                $q->whereHas('product', fn (Builder $pq) => $pq->where('default_warehouse_id', $warehouseId));
            })
            ->when($warehouseId === null && $authorizedWarehouseIds !== null, function (Builder $q) use ($authorizedWarehouseIds): void {
                $q->whereHas('product', fn (Builder $pq) => $pq->whereIn('default_warehouse_id', $authorizedWarehouseIds));
            })
            ->with(['goodsReceived.stockBatches', 'purchaseOrderItem'])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->filter(fn (GoodsReceivedItem $item): bool => ProductUnit::normalizeUnit((string) $item->received_unit) === $normalizedUnit);

        if ($advanceItems->isEmpty() || $billItems->isEmpty()) {
            return [
                'matched_qty' => 0.0,
                'matches_count' => 0,
                'message' => 'No matching same-day Advance and Bill items found for this product and unit.',
            ];
        }

        // Calculate remaining available qty on each item
        $advItemAvail = [];
        foreach ($advanceItems as $advItem) {
            $alreadyMatched = (float) AdvanceReceiveMatch::query()
                ->where('advance_goods_received_item_id', $advItem->id)
                ->sum('matched_qty');
            $advItemAvail[$advItem->id] = max(0.0, round((float) $advItem->received_qty - $alreadyMatched, 3));
        }

        $billItemAvail = [];
        foreach ($billItems as $billItem) {
            $alreadyMatched = (float) AdvanceReceiveMatch::query()
                ->where('bill_goods_received_item_id', $billItem->id)
                ->sum('matched_qty');
            $billItemAvail[$billItem->id] = max(0.0, round((float) $billItem->received_qty - $alreadyMatched, 3));
        }

        $totalMatchedInRun = 0.0;
        $matchesCreated = 0;

        // FIFO matching loop within selected date only
        foreach ($billItems as $billItem) {
            if ($billItemAvail[$billItem->id] <= 0.0001) {
                continue;
            }

            foreach ($advanceItems as $advItem) {
                if ($advItemAvail[$advItem->id] <= 0.0001) {
                    continue;
                }

                $matchQty = min($billItemAvail[$billItem->id], $advItemAvail[$advItem->id]);
                if ($matchQty <= 0.0001) {
                    continue;
                }

                $advGrn = $advItem->goodsReceived;
                $billGrn = $billItem->goodsReceived;

                $advBatch = $advGrn?->stockBatches->firstWhere('goods_received_item_id', $advItem->id)
                    ?? $advGrn?->stockBatches->firstWhere('product_id', $productId);

                $billBatch = $billGrn?->stockBatches->firstWhere('goods_received_item_id', $billItem->id)
                    ?? $billGrn?->stockBatches->firstWhere('product_id', $productId);

                // Create AdvanceReceiveMatch
                AdvanceReceiveMatch::create([
                    'advance_goods_received_id' => $advItem->goods_received_id,
                    'advance_goods_received_item_id' => $advItem->id,
                    'advance_stock_batch_id' => $advBatch?->id,
                    'bill_goods_received_id' => $billItem->goods_received_id,
                    'bill_goods_received_item_id' => $billItem->id,
                    'purchase_order_id' => $billGrn?->purchase_order_id ?? $billItem->purchaseOrderItem?->purchase_order_id,
                    'purchase_order_item_id' => $billItem->purchase_order_item_id,
                    'product_id' => $productId,
                    'matched_qty' => $matchQty,
                    'matched_unit' => $unit,
                    'base_qty' => $matchQty,
                    'conversion_to_base' => 1.0,
                    'confirmed_by' => $userId,
                    'confirmed_at' => now(),
                    'notes' => "Day-wise inventory match for {$product->name} on {$date}",
                ]);

                // Reduce BILL-side StockBatch only (Advance StockBatch remains intact!)
                if ($billBatch) {
                    $newBillBatchQty = max(0.0, round((float) $billBatch->total_kg - $matchQty, 3));
                    $billBatch->update([
                        'total_kg' => $newBillBatchQty,
                        'notes' => trim(($billBatch->notes ?? '')." | Matched {$matchQty} {$unit} with Advance GRN #{$advGrn?->grn_number}"),
                    ]);
                }

                // Update local available quantities
                $billItemAvail[$billItem->id] = round($billItemAvail[$billItem->id] - $matchQty, 3);
                $advItemAvail[$advItem->id] = round($advItemAvail[$advItem->id] - $matchQty, 3);

                $totalMatchedInRun = round($totalMatchedInRun + $matchQty, 3);
                $matchesCreated++;

                if ($billItemAvail[$billItem->id] <= 0.0001) {
                    break;
                }
            }
        }

        // Check if any Advance GRNs or Bill GRNs are now fully matched
        foreach ($advanceItems as $advItem) {
            $advGrn = $advItem->goodsReceived;
            if ($advGrn) {
                $freshMatches = AdvanceReceiveMatch::where('advance_goods_received_id', $advGrn->id)->get();
                $allAdvItemsMatched = $advGrn->items->every(function (GoodsReceivedItem $it) use ($freshMatches): bool {
                    $matched = (float) $freshMatches->where('advance_goods_received_item_id', $it->id)->sum('matched_qty');

                    return $matched >= (float) $it->received_qty - 0.0001;
                });
                if ($allAdvItemsMatched && $advGrn->items->isNotEmpty()) {
                    $advGrn->update(['bill_status' => 'bill_available']);
                }
            }
        }

        foreach ($billItems as $billItem) {
            $billGrn = $billItem->goodsReceived;
            if ($billGrn) {
                $freshMatches = AdvanceReceiveMatch::where('bill_goods_received_id', $billGrn->id)->get();
                $allBillItemsMatched = $billGrn->items->every(function (GoodsReceivedItem $it) use ($freshMatches): bool {
                    $matched = (float) $freshMatches->where('bill_goods_received_item_id', $it->id)->sum('matched_qty');

                    return $matched >= (float) $it->received_qty - 0.0001;
                });
                if ($allBillItemsMatched && $billGrn->items->isNotEmpty()) {
                    $billGrn->update(['bill_status' => 'bill_available']);
                }
            }
        }

        if ($totalMatchedInRun > 0) {
            activity()
                ->performedOn($product)
                ->causedBy(User::find($userId))
                ->withProperties([
                    'action' => 'day_wise_inventory_match',
                    'date' => $date,
                    'product_id' => $productId,
                    'product_name' => $product->name,
                    'unit' => $unit,
                    'matched_qty' => $totalMatchedInRun,
                    'matches_count' => $matchesCreated,
                ])
                ->log("Matched {$totalMatchedInRun} {$unit} for {$product->name} on {$date}");
        }

        return [
            'matched_qty' => $totalMatchedInRun,
            'matches_count' => $matchesCreated,
            'message' => $totalMatchedInRun > 0
                ? "Successfully matched {$totalMatchedInRun} {$unit} for {$product->name}."
                : 'No additional quantities could be matched.',
        ];
    }

    public function matchDayInventory(Request $request): JsonResponse|RedirectResponse
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'unit' => ['required', 'string', 'max:50'],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $productId = (int) $validated['product_id'];
        $unit = trim((string) $validated['unit']);
        $warehouseId = ! empty($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null;

        $authorizedWarehouseIds = app(WarehouseReceiptReadScope::class)->warehouseIds($request->user(), $warehouseId);
        if ($authorizedWarehouseIds !== null && $warehouseId !== null && ! in_array($warehouseId, $authorizedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        $userId = (int) $request->user()->id;

        $result = DB::transaction(function () use ($date, $productId, $unit, $warehouseId, $authorizedWarehouseIds, $userId): array {
            return $this->executeDayInventoryMatch($date, $productId, $unit, $warehouseId, $authorizedWarehouseIds, $userId);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => $result,
                'message' => $result['message'],
            ]);
        }

        return redirect()->back()->with('success', $result['message']);
    }

    /**
     * Match all eligible rows for the selected date and warehouse.
     */
    public function matchAllDayInventory(Request $request): JsonResponse|RedirectResponse
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $warehouseId = ! empty($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null;

        $authorizedWarehouseIds = app(WarehouseReceiptReadScope::class)->warehouseIds($request->user(), $warehouseId);
        if ($authorizedWarehouseIds !== null && $warehouseId !== null && ! in_array($warehouseId, $authorizedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        $userId = (int) $request->user()->id;

        $rows = $this->buildInventoryComparisonRows($date, $warehouseId, $authorizedWarehouseIds);

        $matchedProductsCount = 0;
        $totalMatchedQty = 0.0;
        $skippedUnitMismatches = 0;

        DB::transaction(function () use ($rows, $date, $warehouseId, $authorizedWarehouseIds, $userId, &$matchedProductsCount, &$totalMatchedQty, &$skippedUnitMismatches): void {
            foreach ($rows as $row) {
                if ($row['unit_mismatch']) {
                    $skippedUnitMismatches++;

                    continue;
                }

                if (in_array($row['action_type'], ['match', 'match_remaining'], true)) {
                    $matchResult = $this->executeDayInventoryMatch(
                        $date,
                        (int) $row['product_id'],
                        (string) $row['unit'],
                        $warehouseId,
                        $authorizedWarehouseIds,
                        $userId
                    );

                    if ($matchResult['matched_qty'] > 0.0001) {
                        $matchedProductsCount++;
                        $totalMatchedQty = round($totalMatchedQty + $matchResult['matched_qty'], 3);
                    }
                }
            }
        });

        // Recompute rows to get current pending count
        $updatedRows = $this->buildInventoryComparisonRows($date, $warehouseId, $authorizedWarehouseIds);
        $stillPendingRows = collect($updatedRows)->filter(function (array $r): bool {
            return ($r['bill_qty'] > 0 && $r['unmatched_bill_qty'] > 0.0001) || $r['unit_mismatch'];
        })->count();

        $message = "Matched {$matchedProductsCount} products. Skipped {$skippedUnitMismatches} unit mismatches. {$stillPendingRows} rows still pending.";

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => [
                    'matched_products_count' => $matchedProductsCount,
                    'total_matched_qty' => $totalMatchedQty,
                    'skipped_unit_mismatches' => $skippedUnitMismatches,
                    'still_pending_rows' => $stillPendingRows,
                ],
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Day-wise Admin Receive All Pending Purchase Bills for selected date & warehouse.
     * Uses canonical ApproveGoodsReceiptAction::executeAndConfirmReceive flow.
     */
    public function receiveAllPendingBills(Request $request): JsonResponse|RedirectResponse
    {
        $this->ensureAuthorized($request);

        $user = $request->user();
        abort_unless(
            $user->isMainAdmin()
            || $user->hasRole('admin')
            || $user->hasRole('purchase')
            || $user->hasAnyPermission(['purchasing.grn.approve', 'accounting.dashboard.view', 'accounting.report.view']),
            403,
            'Unauthorized to receive bills.'
        );

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $requestedWarehouseId = ! empty($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null;

        $authorizedWarehouseIds = app(WarehouseReceiptReadScope::class)->warehouseIds(
            $user,
            $requestedWarehouseId
        );

        if ($requestedWarehouseId !== null && $authorizedWarehouseIds !== null && ! in_array($requestedWarehouseId, $authorizedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        $pendingCandidates = $this->getPendingBillsForDate($date, $requestedWarehouseId, $authorizedWarehouseIds);

        $receivedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        $approveAction = app(ApproveGoodsReceiptAction::class);
        $userId = (int) $user->id;

        foreach ($pendingCandidates as $candidate) {
            try {
                DB::transaction(function () use ($candidate, $approveAction, $userId, $requestedWarehouseId, &$receivedCount, &$skippedCount): void {
                    $targetWarehouseId = $requestedWarehouseId;

                    if ($candidate['type'] === 'po') {
                        /** @var PurchaseOrder $po */
                        $po = $candidate['record'];
                        $po->loadMissing(['items.product', 'goodsReceiveds.stockBatches']);

                        // Lock and check if a GRN already exists or was created concurrently
                        $existingGrn = GoodsReceived::query()
                            ->where('purchase_order_id', $po->id)
                            ->where(function (Builder $sub): void {
                                $sub->where('receipt_type', '!=', 'warehouse_advance')
                                    ->orWhereNull('receipt_type');
                            })
                            ->lockForUpdate()
                            ->first();

                        if ($existingGrn !== null) {
                            $hasBatches = StockBatch::query()->where('goods_received_id', $existingGrn->id)->exists();
                            $hasPendingBatches = StockBatch::query()->where('goods_received_id', $existingGrn->id)->where('warehouse_receive_pending', true)->exists();
                            if ($existingGrn->status === 'approved' && $hasBatches && ! $hasPendingBatches) {
                                $skippedCount++;

                                return;
                            }

                            $effectiveWh = $targetWarehouseId ?? $existingGrn->warehouse_id ?? $po->items->first()?->product?->default_warehouse_id;
                            $approveAction->executeAndConfirmReceive($existingGrn, $userId, $effectiveWh);
                            $receivedCount++;

                            return;
                        }

                        // Create GRN for this PO
                        $effectiveWh = $targetWarehouseId ?? $po->warehouse_id ?? $po->destination_shop_id ?? $po->items->first()?->product?->default_warehouse_id;
                        $grn = GoodsReceived::create([
                            'public_uuid' => (string) Str::uuid(),
                            'warehouse_id' => $effectiveWh,
                            'destination_shop_id' => $po->destination_shop_id,
                            'purchase_order_id' => $po->id,
                            'grn_number' => 'GRN-PO-'.str_replace('PO-', '', (string) ($po->po_number ?? $po->id)),
                            'status' => 'pending_approval',
                            'bill_status' => 'bill_pending',
                            'receipt_type' => 'normal_purchase',
                            'received_by' => $userId,
                            'received_at' => $po->order_date ?? now(),
                            'notes' => 'Created via Admin Inventory Receive All',
                        ]);

                        foreach ($po->items as $poItem) {
                            GoodsReceivedItem::create([
                                'goods_received_id' => $grn->id,
                                'purchase_order_item_id' => $poItem->id,
                                'product_id' => $poItem->product_id,
                                'received_qty' => (float) $poItem->quantity,
                                'received_unit' => $poItem->purchase_unit ?: ($poItem->product?->unit ?? 'kg'),
                                'variance' => 0.0,
                                'grade' => 'A',
                            ]);
                        }

                        $approveAction->executeAndConfirmReceive($grn, $userId, $effectiveWh);
                        $receivedCount++;
                    } elseif ($candidate['type'] === 'grn') {
                        /** @var GoodsReceived $grn */
                        $grn = $candidate['record'];

                        $lockedGrn = GoodsReceived::query()->whereKey($grn->id)->lockForUpdate()->first();
                        if ($lockedGrn === null) {
                            $skippedCount++;

                            return;
                        }

                        $hasBatches = StockBatch::query()->where('goods_received_id', $lockedGrn->id)->exists();
                        $hasPendingBatches = StockBatch::query()->where('goods_received_id', $lockedGrn->id)->where('warehouse_receive_pending', true)->exists();
                        if ($lockedGrn->status === 'approved' && $hasBatches && ! $hasPendingBatches) {
                            $skippedCount++;

                            return;
                        }

                        $effectiveWh = $targetWarehouseId ?? $lockedGrn->warehouse_id ?? $lockedGrn->items->first()?->product?->default_warehouse_id;
                        $approveAction->executeAndConfirmReceive($lockedGrn, $userId, $effectiveWh);
                        $receivedCount++;
                    }
                });
            } catch (Throwable $e) {
                Log::error("Failed to receive pending bill: {$e->getMessage()}", [
                    'candidate' => $candidate,
                    'exception' => $e,
                ]);
                $failedCount++;
            }
        }

        $remainingPending = $this->getPendingBillsForDate($date, $requestedWarehouseId, $authorizedWarehouseIds)->count();

        $message = "{$receivedCount} bills received successfully".($remainingPending > 0 ? " ({$remainingPending} pending)" : ' (0 pending)');

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => [
                    'received' => $receivedCount,
                    'skipped' => $skippedCount,
                    'failed' => $failedCount,
                    'pending_remaining' => $remainingPending,
                ],
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Get pending purchase bills / POs for a specific date and warehouse.
     *
     * @return Collection<int, array{type: 'po'|'grn', record: PurchaseOrder|GoodsReceived}>
     */
    protected function getPendingBillsForDate(string $date, ?int $selectedWarehouseId, ?array $authorizedWarehouseIds): Collection
    {
        $pendingBills = collect();

        // 1. Pending Purchase Orders for the selected date
        $pos = PurchaseOrder::query()
            ->whereDate('order_date', $date)
            ->whereNotIn('status', ['draft', 'cancelled', 'rejected'])
            ->when($selectedWarehouseId !== null, function (Builder $q) use ($selectedWarehouseId): void {
                $q->whereHas('items.product', fn (Builder $pq) => $pq->where('default_warehouse_id', $selectedWarehouseId));
            })
            ->when($selectedWarehouseId === null && $authorizedWarehouseIds !== null, function (Builder $q) use ($authorizedWarehouseIds): void {
                $q->whereHas('items.product', fn (Builder $pq) => $pq->whereIn('default_warehouse_id', $authorizedWarehouseIds));
            })
            ->with(['items.product', 'goodsReceiveds.stockBatches', 'goodsReceiveds.items.product'])
            ->get();

        foreach ($pos as $po) {
            $nonAdvanceGrns = $po->goodsReceiveds->filter(fn (GoodsReceived $g) => $g->receipt_type !== 'warehouse_advance' && $g->status !== 'cancelled');

            if ($nonAdvanceGrns->isEmpty()) {
                // PO has no non-advance GRN yet -> pending receive
                $pendingBills->push([
                    'type' => 'po',
                    'record' => $po,
                ]);
            } else {
                foreach ($nonAdvanceGrns as $grn) {
                    $hasBatches = $grn->stockBatches->isNotEmpty();
                    $hasPendingBatches = $grn->stockBatches->contains('warehouse_receive_pending', true);
                    $isAlreadyFullyReceived = $grn->status === 'approved' && $hasBatches && ! $hasPendingBatches;

                    if (! $isAlreadyFullyReceived) {
                        $pendingBills->push([
                            'type' => 'grn',
                            'record' => $grn,
                        ]);
                    }
                }
            }
        }

        // 2. Standalone GoodsReceived for the selected date (where purchase_order_id IS NULL)
        $standaloneGrns = GoodsReceived::query()
            ->whereNull('purchase_order_id')
            ->whereDate('received_at', $date)
            ->where(function (Builder $sub): void {
                $sub->where('receipt_type', '!=', 'warehouse_advance')
                    ->orWhereNull('receipt_type');
            })
            ->where('status', '!=', 'cancelled')
            ->when($selectedWarehouseId !== null, function (Builder $q) use ($selectedWarehouseId): void {
                $q->whereHas('items.product', fn (Builder $pq) => $pq->where('default_warehouse_id', $selectedWarehouseId));
            })
            ->when($selectedWarehouseId === null && $authorizedWarehouseIds !== null, function (Builder $q) use ($authorizedWarehouseIds): void {
                $q->whereHas('items.product', fn (Builder $pq) => $pq->whereIn('default_warehouse_id', $authorizedWarehouseIds));
            })
            ->with(['items.product', 'stockBatches'])
            ->get();

        foreach ($standaloneGrns as $grn) {
            $hasBatches = $grn->stockBatches->isNotEmpty();
            $hasPendingBatches = $grn->stockBatches->contains('warehouse_receive_pending', true);
            $isAlreadyFullyReceived = $grn->status === 'approved' && $hasBatches && ! $hasPendingBatches;

            if (! $isAlreadyFullyReceived) {
                $pendingBills->push([
                    'type' => 'grn',
                    'record' => $grn,
                ]);
            }
        }

        return $pendingBills;
    }

    /**
     * Redirect to WhatsApp with pending advance bills message.
     */
    public function shareUnmatchedAdvancesWhatsApp(Request $request, DailyPendingAdvanceWhatsAppService $whatsAppService): RedirectResponse
    {
        $this->ensureAuthorized($request);

        $date = Carbon::parse($request->input('date', today()->toDateString()))->toDateString();
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

        $url = $whatsAppService->generateShareUrl($date, $requestedWarehouseId, $authorizedWarehouseIds);
        if ($url === null) {
            return redirect()->back()->with('warning', 'No pending Advance bills for this day.');
        }

        return redirect()->away($url);
    }

    /**
     * Format pending bill candidates for UI / API consumption.
     *
     * @param  Collection<int, array{type: 'po'|'grn', record: PurchaseOrder|GoodsReceived}>  $pendingCandidates
     * @return array<int, array<string, mixed>>
     */
    protected function formatPendingBillsList(Collection $pendingCandidates): array
    {
        return $pendingCandidates->map(function (array $candidate, int $index): array {
            $record = $candidate['record'];
            $type = $candidate['type'];

            if ($type === 'po') {
                /** @var PurchaseOrder $po */
                $po = $record;
                $supplierName = $po->supplier?->name ?? 'N/A';
                $billNumber = $po->po_number ?: 'PO #'.$po->id;
                $itemsCount = $po->items->count();
                $itemsSummary = $po->items->map(function ($it): string {
                    $name = $it->product?->name ?? 'Item #'.$it->product_id;
                    $qty = (float) $it->quantity;
                    $unit = $it->purchase_unit ?: ($it->product?->unit ?? 'kg');

                    return "{$name} ({$qty} {$unit})";
                })->implode(', ');
            } else {
                /** @var GoodsReceived $grn */
                $grn = $record;
                $supplierName = $grn->purchaseOrder?->supplier?->name ?? 'Supplier';
                $billNumber = $grn->grn_number ?: ($grn->purchaseOrder?->po_number ?: 'GRN #'.$grn->id);
                $itemsCount = $grn->items->count();
                $itemsSummary = $grn->items->map(function ($it): string {
                    $name = $it->product?->name ?? 'Item #'.$it->product_id;
                    $qty = (float) $it->received_qty;
                    $unit = $it->received_unit ?: ($it->product?->unit ?? 'kg');

                    return "{$name} ({$qty} {$unit})";
                })->implode(', ');
            }

            return [
                'sl' => $index + 1,
                'id' => $record->id,
                'type' => $type,
                'bill_number' => $billNumber,
                'supplier_name' => $supplierName,
                'items_count' => $itemsCount,
                'items_summary' => $itemsSummary ?: 'No items',
                'status' => 'Pending Receive',
            ];
        })->values()->all();
    }

    /**
     * Day-wise Admin Receive a single pending Purchase Bill (by PO or GRN ID).
     * Uses canonical ApproveGoodsReceiptAction::executeAndConfirmReceive flow.
     */
    public function receiveSingleBill(Request $request): JsonResponse
    {
        $this->ensureAuthorized($request);

        $user = $request->user();
        abort_unless(
            $user->isMainAdmin()
            || $user->hasRole('admin')
            || $user->hasRole('purchase')
            || $user->hasAnyPermission(['purchasing.grn.approve', 'accounting.dashboard.view', 'accounting.report.view']),
            403,
            'Unauthorized to receive bills.'
        );

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:po,grn'],
            'id' => ['required', 'integer'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'date' => ['nullable', 'date'],
        ]);

        $type = $validated['type'];
        $id = (int) $validated['id'];
        $requestedWarehouseId = ! empty($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null;

        $authorizedWarehouseIds = app(WarehouseReceiptReadScope::class)->warehouseIds(
            $user,
            $requestedWarehouseId
        );

        if ($requestedWarehouseId !== null && $authorizedWarehouseIds !== null && ! in_array($requestedWarehouseId, $authorizedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        $approveAction = app(ApproveGoodsReceiptAction::class);
        $userId = (int) $user->id;

        $grn = DB::transaction(function () use ($type, $id, $requestedWarehouseId, $approveAction, $userId): GoodsReceived {
            if ($type === 'po') {
                $po = PurchaseOrder::query()->with(['items.product'])->findOrFail($id);

                // Re-check if GRN was already created
                $existingGrn = GoodsReceived::query()
                    ->where('purchase_order_id', $po->id)
                    ->where(function (Builder $sub): void {
                        $sub->where('receipt_type', '!=', 'warehouse_advance')
                            ->orWhereNull('receipt_type');
                    })
                    ->lockForUpdate()
                    ->first();

                if ($existingGrn !== null) {
                    $effectiveWh = $requestedWarehouseId ?? $existingGrn->warehouse_id ?? $po->items->first()?->product?->default_warehouse_id;

                    return $approveAction->executeAndConfirmReceive($existingGrn, $userId, $effectiveWh);
                }

                // Create GRN for this PO
                $effectiveWh = $requestedWarehouseId ?? $po->warehouse_id ?? $po->destination_shop_id ?? $po->items->first()?->product?->default_warehouse_id;
                $newGrn = GoodsReceived::create([
                    'public_uuid' => (string) Str::uuid(),
                    'warehouse_id' => $effectiveWh,
                    'destination_shop_id' => $po->destination_shop_id,
                    'purchase_order_id' => $po->id,
                    'grn_number' => 'GRN-PO-'.str_replace('PO-', '', (string) ($po->po_number ?? $po->id)),
                    'status' => 'pending_approval',
                    'bill_status' => 'bill_pending',
                    'receipt_type' => 'normal_purchase',
                    'received_by' => $userId,
                    'received_at' => $po->order_date ?? now(),
                    'notes' => 'Created via Admin Inventory Receive Single',
                ]);

                foreach ($po->items as $poItem) {
                    GoodsReceivedItem::create([
                        'goods_received_id' => $newGrn->id,
                        'purchase_order_item_id' => $poItem->id,
                        'product_id' => $poItem->product_id,
                        'received_qty' => (float) $poItem->quantity,
                        'received_unit' => $poItem->purchase_unit ?: ($poItem->product?->unit ?? 'kg'),
                        'variance' => 0.0,
                        'grade' => 'A',
                    ]);
                }

                return $approveAction->executeAndConfirmReceive($newGrn, $userId, $effectiveWh);
            } else {
                $lockedGrn = GoodsReceived::query()->whereKey($id)->lockForUpdate()->firstOrFail();
                $effectiveWh = $requestedWarehouseId ?? $lockedGrn->warehouse_id ?? $lockedGrn->items->first()?->product?->default_warehouse_id;

                return $approveAction->executeAndConfirmReceive($lockedGrn, $userId, $effectiveWh);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => "Bill #{$grn->grn_number} received successfully",
            'data' => [
                'grn_id' => $grn->id,
                'grn_number' => $grn->grn_number,
            ],
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

        // Query pending POs grouped by order_date
        $pos = PurchaseOrder::query()
            ->whereNotIn('status', ['draft', 'cancelled', 'rejected'])
            ->when($requestedWarehouseId !== null, function (Builder $q) use ($requestedWarehouseId): void {
                $q->whereHas('items.product', fn (Builder $pq) => $pq->where('default_warehouse_id', $requestedWarehouseId));
            })
            ->when($requestedWarehouseId === null && $authorizedWarehouseIds !== null, function (Builder $q) use ($authorizedWarehouseIds): void {
                $q->whereHas('items.product', fn (Builder $pq) => $pq->whereIn('default_warehouse_id', $authorizedWarehouseIds));
            })
            ->with(['items.product', 'goodsReceiveds.stockBatches'])
            ->orderByDesc('order_date')
            ->get();

        $dayCounts = [];
        foreach ($pos as $po) {
            $dateStr = $po->order_date instanceof Carbon ? $po->order_date->toDateString() : Carbon::parse($po->order_date)->toDateString();
            $nonAdvanceGrns = $po->goodsReceiveds->filter(fn (GoodsReceived $g) => $g->receipt_type !== 'warehouse_advance' && $g->status !== 'cancelled');

            $isPending = false;
            if ($nonAdvanceGrns->isEmpty()) {
                $isPending = true;
            } else {
                foreach ($nonAdvanceGrns as $grn) {
                    $hasBatches = $grn->stockBatches->isNotEmpty();
                    $hasPendingBatches = $grn->stockBatches->contains('warehouse_receive_pending', true);
                    if (! ($grn->status === 'approved' && $hasBatches && ! $hasPendingBatches)) {
                        $isPending = true;
                        break;
                    }
                }
            }

            if ($isPending) {
                $dayCounts[$dateStr] = ($dayCounts[$dateStr] ?? 0) + 1;
            }
        }

        $days = [];
        foreach ($dayCounts as $dateStr => $count) {
            $days[] = [
                'date' => $dateStr,
                'formatted_date' => Carbon::parse($dateStr)->format('d-m-Y'),
                'pending_count' => $count,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
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

        $candidates = $this->getPendingBillsForDate($dateStr, $requestedWarehouseId, $authorizedWarehouseIds);
        $bills = $this->formatPendingBillsList($candidates);

        return response()->json([
            'status' => 'success',
            'data' => [
                'date' => $dateStr,
                'formatted_date' => Carbon::parse($dateStr)->format('d-m-Y'),
                'total_pending' => count($bills),
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
            'grn_id' => ['nullable', 'integer', 'exists:goods_received,id'],
            'purchase_order_ids' => ['nullable', 'array'],
            'purchase_order_ids.*' => ['integer', 'exists:purchase_orders,id'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
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
        $grnIds = array_values(array_filter(array_unique(array_merge(
            isset($validated['grn_id']) ? [(int) $validated['grn_id']] : [],
            isset($validated['grn_ids']) ? array_map('intval', $validated['grn_ids']) : []
        ))));
        $poIds = array_values(array_filter(array_unique(array_merge(
            isset($validated['purchase_order_id']) ? [(int) $validated['purchase_order_id']] : [],
            isset($validated['purchase_order_ids']) ? array_map('intval', $validated['purchase_order_ids']) : []
        ))));

        $candidates = collect();

        if (! empty($grnIds)) {
            $grnQuery = GoodsReceived::query()
                ->whereIn('id', $grnIds)
                ->where(function (Builder $typeQ): void {
                    $typeQ->where('receipt_type', '!=', 'warehouse_advance')
                        ->orWhereNull('receipt_type');
                })
                ->with(['items.product', 'items.purchaseOrderItem', 'purchaseOrder']);

            app(WarehouseReceiptReadScope::class)->receipts(
                $grnQuery,
                $requestedWarehouseId !== null ? [$requestedWarehouseId] : $authorizedWarehouseIds
            );

            $candidates = $grnQuery->get();
        } elseif (! empty($poIds)) {
            $pos = PurchaseOrder::query()
                ->whereIn('id', $poIds)
                ->with(['items.product', 'goodsReceiveds.items.product'])
                ->get();

            foreach ($pos as $po) {
                if ($po->goodsReceiveds->isNotEmpty()) {
                    foreach ($po->goodsReceiveds as $grn) {
                        if ($grn->receipt_type !== 'warehouse_advance') {
                            $candidates->push($grn);
                        }
                    }
                } else {
                    $newGrn = DB::transaction(function () use ($po, $user, $requestedWarehouseId): GoodsReceived {
                        $targetWh = $requestedWarehouseId ?? $po->warehouse_id ?? $po->destination_shop_id ?? 1;
                        $grn = GoodsReceived::create([
                            'public_uuid' => (string) Str::uuid(),
                            'warehouse_id' => $targetWh,
                            'destination_shop_id' => $po->destination_shop_id,
                            'purchase_order_id' => $po->id,
                            'grn_number' => 'GRN-PO-'.str_replace('PO-', '', (string) ($po->po_number ?? $po->id)),
                            'status' => 'pending_approval',
                            'bill_status' => 'bill_pending',
                            'receipt_type' => 'normal_purchase',
                            'received_by' => $user->id,
                            'received_at' => $po->order_date ?? now(),
                            'notes' => 'Created via Admin Inventory Approve & Receive',
                        ]);

                        foreach ($po->items as $poItem) {
                            GoodsReceivedItem::create([
                                'goods_received_id' => $grn->id,
                                'purchase_order_item_id' => $poItem->id,
                                'product_id' => $poItem->product_id,
                                'received_qty' => (float) $poItem->quantity,
                                'received_unit' => $poItem->purchase_unit ?: ($poItem->product?->unit ?? 'kg'),
                                'variance' => 0.0,
                                'grade' => 'A',
                            ]);
                        }

                        return $grn->fresh(['items.product', 'purchaseOrder']);
                    });

                    $candidates->push($newGrn);
                }
            }
        } elseif (! empty($dates)) {
            $grnQuery = GoodsReceived::query()
                ->where(function (Builder $typeQ): void {
                    $typeQ->where('receipt_type', '!=', 'warehouse_advance')
                        ->orWhereNull('receipt_type');
                })
                ->where(function (Builder $statusQ): void {
                    $statusQ->where('status', 'pending_approval')
                        ->orWhere(function (Builder $approvedPending): void {
                            $approvedPending->where('status', 'approved')
                                ->whereHas('stockBatches', fn (Builder $b) => $b->where('warehouse_receive_pending', true));
                        });
                })
                ->where(function (Builder $q) use ($dates): void {
                    foreach ($dates as $d) {
                        $q->orWhereDate('received_at', $d);
                    }
                })
                ->with(['items.product', 'items.purchaseOrderItem', 'purchaseOrder']);

            app(WarehouseReceiptReadScope::class)->receipts(
                $grnQuery,
                $requestedWarehouseId !== null ? [$requestedWarehouseId] : $authorizedWarehouseIds
            );

            $candidates = $grnQuery->get();
        } else {
            $grnQuery = GoodsReceived::query()
                ->where(function (Builder $typeQ): void {
                    $typeQ->where('receipt_type', '!=', 'warehouse_advance')
                        ->orWhereNull('receipt_type');
                })
                ->where(function (Builder $statusQ): void {
                    $statusQ->where('status', 'pending_approval')
                        ->orWhere(function (Builder $approvedPending): void {
                            $approvedPending->where('status', 'approved')
                                ->whereHas('stockBatches', fn (Builder $b) => $b->where('warehouse_receive_pending', true));
                        });
                })
                ->with(['items.product', 'items.purchaseOrderItem', 'purchaseOrder']);

            app(WarehouseReceiptReadScope::class)->receipts(
                $grnQuery,
                $requestedWarehouseId !== null ? [$requestedWarehouseId] : $authorizedWarehouseIds
            );

            $candidates = $grnQuery->get();
        }

        if ($candidates->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No pending GRNs were found for the submitted IDs.',
                'data' => [
                    'approved' => 0,
                    'already_approved' => 0,
                    'skipped' => 0,
                    'failed' => 0,
                    'now_awaiting_reconciliation' => 0,
                ],
            ], 422);
        }

        $approvedCount = 0;
        $alreadyApprovedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        $approveAction = app(ApproveGoodsReceiptAction::class);
        $userId = (int) $user->id;

        foreach ($candidates as $grn) {
            $hasBatches = StockBatch::query()
                ->where('goods_received_id', $grn->id)
                ->exists();

            $isAlreadyFullyReceived = $grn->status === 'approved'
                && $hasBatches
                && ! StockBatch::query()
                    ->where('goods_received_id', $grn->id)
                    ->where('warehouse_receive_pending', true)
                    ->exists();

            if ($isAlreadyFullyReceived) {
                $alreadyApprovedCount++;

                continue;
            }

            if ($grn->items->isEmpty()) {
                $skippedCount++;

                continue;
            }

            try {
                $approveAction->executeAndConfirmReceive($grn, $userId, $requestedWarehouseId);
                $approvedCount++;
            } catch (Throwable $e) {
                Log::error("Failed to approve & receive GRN #{$grn->id} in acceptPendingBills: {$e->getMessage()}", [
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

        $billWord = $approvedCount === 1 ? 'bill' : 'bills';
        $message = $approvedCount > 0
            ? "{$approvedCount} {$billWord} approved and received."
            : ($alreadyApprovedCount > 0 ? 'All selected bills are already approved and received.' : 'No pending GRNs were found for the submitted IDs.');

        return response()->json([
            'status' => ($approvedCount > 0 || $alreadyApprovedCount > 0) ? 'success' : 'error',
            'message' => $message,
            'data' => [
                'approved' => $approvedCount,
                'already_approved' => $alreadyApprovedCount,
                'skipped' => $skippedCount,
                'failed' => $failedCount,
                'now_awaiting_reconciliation' => $nowAwaitingReconciliationCount,
                'warehouse_id' => $requestedWarehouseId,
                'auto_match_preview' => $autoMatchPreview,
            ],
        ], ($approvedCount > 0 || $alreadyApprovedCount > 0) ? 200 : 422);
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

    /**
     * Format and group unbilled loadout items by Business Date -> Shop.
     *
     * @return array<string, array<string, mixed>>
     */
    private function formatGroupedUnbilledLoadouts(Collection $items, AdvanceAvailableBalanceCalculator $calc): array
    {
        $productIds = $items->pluck('product_id')->filter()->unique()->all();
        $productsMap = $productIds !== []
            ? Product::with('orderUnits')->whereIn('id', $productIds)->get()->keyBy('id')
            : collect();

        $groupedByDate = [];

        foreach ($items as $r) {
            $bDate = $r->business_date ? Carbon::parse($r->business_date)->toDateString() : today()->toDateString();
            $shopId = (int) ($r->shop_id ?? 0);
            $shopKey = $shopId > 0 ? (string) $shopId : ($r->shop_name ?? 'Unknown Shop');

            if (! isset($groupedByDate[$bDate])) {
                $groupedByDate[$bDate] = [
                    'date' => $bDate,
                    'formatted_date' => Carbon::parse($bDate)->format('d M Y'),
                    'shops' => [],
                ];
            }

            if (! isset($groupedByDate[$bDate]['shops'][$shopKey])) {
                $groupedByDate[$bDate]['shops'][$shopKey] = [
                    'shop_id' => $shopId,
                    'shop_name' => $r->shop_name ?? '—',
                    'shop_code' => $r->shop_code ?? '',
                    'order_numbers' => [],
                    'unit_totals' => [],
                    'product_count' => 0,
                    'items' => [],
                ];
            }

            if (! empty($r->order_number) && ! in_array($r->order_number, $groupedByDate[$bDate]['shops'][$shopKey]['order_numbers'], true)) {
                $groupedByDate[$bDate]['shops'][$shopKey]['order_numbers'][] = $r->order_number;
            }

            /** @var Product|null $product */
            $product = $productsMap->get($r->product_id) ?? ($r->product_id ? Product::find($r->product_id) : null);
            $baseUnit = $product?->unit ?? $r->product_unit ?? 'kg';
            $normBase = ProductUnit::normalizeUnit($baseUnit);

            // Determine source quantity & unit
            $itemUnit = $r->item_unit ?: ($r->requested_unit ?: $baseUnit);
            if ($r->actual_weight !== null && (float) $r->actual_weight > 0) {
                $rawQty = (float) $r->actual_weight;
                $sourceUnit = $r->item_unit ?: 'kg';
            } elseif ($r->loaded_qty !== null && (float) $r->loaded_qty > 0) {
                $rawQty = (float) $r->loaded_qty;
                $sourceUnit = $itemUnit;
            } elseif (isset($r->loaded_order_unit_qty) && (float) $r->loaded_order_unit_qty > 0) {
                $rawQty = (float) $r->loaded_order_unit_qty;
                $sourceUnit = $r->requested_unit ?: $itemUnit;
            } else {
                $rawQty = (float) ($r->loaded_qty ?? 0.0);
                $sourceUnit = $itemUnit;
            }

            $normSource = ProductUnit::normalizeUnit($sourceUnit);

            // Unit conversion & normalization rules
            $convertedBaseQty = null;
            $unitWarning = null;

            if ($normSource === $normBase || $normSource === '') {
                // Rule A & Rule 4: Same normalized unit -> 1:1 direct display, no conversion needed
                $displayQty = $rawQty;
                $displayUnit = $normSource ?: $normBase;
            } else {
                // Rule B & C: Different normalized units
                $conv = $calc->resolveStrictUnitConversion($product, $normSource);
                if ($conv !== null && $conv > 0.0) {
                    // Configured conversion exists
                    $displayQty = $rawQty;
                    $displayUnit = $normSource;
                    $convertedBaseQty = round($rawQty * $conv, 2);
                } else {
                    // No conversion configured
                    $displayQty = $rawQty;
                    $displayUnit = $normSource;
                    $unitWarning = 'Unit conversion not configured';
                }
            }

            $itemData = [
                'item_id' => $r->item_id,
                'product_id' => $r->product_id,
                'product_name' => $r->product_name ?? "Product #{$r->product_id}",
                'product_sku' => $r->product_sku ?? '',
                'loaded_qty' => $displayQty,
                'unit' => $displayUnit,
                'base_unit' => $normBase,
                'converted_base_qty' => $convertedBaseQty,
                'unit_warning' => $unitWarning,
                'status' => 'No Bill Created',
            ];

            $groupedByDate[$bDate]['shops'][$shopKey]['items'][] = $itemData;
            $groupedByDate[$bDate]['shops'][$shopKey]['product_count']++;

            // Separate totals per unit (NEVER add unlike units together)
            $groupedByDate[$bDate]['shops'][$shopKey]['unit_totals'][$displayUnit] =
                ($groupedByDate[$bDate]['shops'][$shopKey]['unit_totals'][$displayUnit] ?? 0.0) + $displayQty;
        }

        return $groupedByDate;
    }
}
