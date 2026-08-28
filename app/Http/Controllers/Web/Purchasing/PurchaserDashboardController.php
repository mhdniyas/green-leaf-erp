<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Enums\Purchasing\InvoiceStatus;
use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Purchasing\StorePurchaserCartItemRequest;
use App\Http\Requests\Web\Purchasing\StorePurchaserCorrectionRequest;
use App\Http\Requests\Web\Purchasing\SubmitPurchaserCartRequest;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\DailyProductPrice;
use App\Models\DailyProductPriceRevision;
use App\Models\GoodsReceived;
use App\Models\ProcurementExpense;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\PurchaserCorrectionRequest;
use App\Models\PurchaserCredit;
use App\Models\PurchaserSharePreset;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Finance\JournalService;
use App\Services\Purchasing\BulkPaymentService;
use App\Services\Purchasing\PurchaseGradePriceResolver;
use App\Services\Purchasing\PurchaseInvoiceService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Purchasing\PurchaserCartBatchStateResolver;
use App\Services\Purchasing\PurchaserReadCacheService;
use App\Services\Purchasing\VendorPriceService;
use App\Services\ShopInvoices\ShopInvoiceService;
use App\Support\PerformanceProbe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaserDashboardController extends Controller
{
    /** @var array<int, Collection<int, PurchaserCart>> */
    private array $memoizedOverdueCarts = [];

    /** @var array<int, array<int, int>> */
    private array $memoizedFrequentProductIds = [];

    /** @var array<int, array<int, string>> */
    private array $memoizedQuickFilters = [];

    private const array QUICK_FILTERS = [
        'Frequent',
        'All',
        'Supply',
        'VEG',
        'Leaf',
        'English',
        'Kolkata',
        'Banana',
        'Onion',
        'C',
        'Frut',
        'Stationory',
    ];

    public function __construct(
        private readonly VendorPriceService $vendorPriceService,
        private readonly PurchaserBusinessDayService $businessDayService,
        private readonly PurchaserCartBatchStateResolver $purchaserCartBatchStateResolver,
        private readonly JournalService $journalService,
        private readonly ShopInvoiceService $shopInvoiceService,
        private readonly PurchaseGradePriceResolver $purchaseGradePriceResolver,
        private readonly PurchaserReadCacheService $readCacheService,
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('purchaser.daily');
    }

    public function settings(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);
        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $user = $request->user();
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('purchasing.purchaser.settings', [
            'user' => $user,
            'categories' => $categories,
            'assignedCategoryIds' => $user->assignedCategoryIds(),
            'date' => $date->format('Y-m-d'),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $validated = $request->validate([
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $categoryIds = array_values(array_map('intval', $validated['category_ids'] ?? []));

        $request->user()->update([
            'assigned_category_ids' => count($categoryIds) > 0 ? $categoryIds : null,
        ]);

        return redirect()
            ->route('purchaser.settings')
            ->with('status', 'Order category preferences saved successfully.');
    }

    public function products(Request $request): View
    {
        $this->ensurePurchaser($request);

        $user = $request->user();
        $status = in_array($request->string('status')->toString(), ['active', 'inactive'], true)
            ? $request->string('status')->toString()
            : 'active';
        $search = trim($request->string('search')->toString());
        $categoryId = $request->integer('category_id') ?: null;
        $unit = $request->string('unit')->toString() ?: null;
        $assignedCategoryIds = $user->hasAssignedCategoryFilter() ? $user->assignedCategoryIds() : null;

        $categories = Category::query()
            ->where('is_active', true)
            ->when($assignedCategoryIds !== null, fn ($query) => $query->whereIn('id', $assignedCategoryIds))
            ->orderBy('name')
            ->get(['id', 'name']);

        $products = Product::query()
            ->select(['id', 'category_id', 'name', 'sku', 'unit', 'is_active', 'image'])
            ->where('show_in_purchaser_order', true)
            ->with(['category:id,name', 'orderUnits'])
            ->when($assignedCategoryIds !== null, fn ($query) => $query->whereIn('category_id', $assignedCategoryIds))
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($unit, fn ($query) => $query->where('unit', $unit))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('unit', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('is_active')
            ->ordered()
            ->get()
            ->sortBy([
                fn (Product $product): string => $product->category?->name ?? 'Uncategorized',
                fn (Product $product): string => $product->sku_sort_value,
                fn (Product $product): string => $product->name,
            ])
            ->values();

        return view('purchasing.purchaser.products', [
            'products' => $products,
            'productsByCategory' => $products->groupBy(fn (Product $product): string => $product->category?->name ?? 'Uncategorized')->sortKeys(),
            'categories' => $categories,
            'selectedStatus' => $status,
            'selectedUnit' => $unit,
            'search' => $search,
        ]);
    }

    public function daily(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $selectedChip = $this->resolveQuickFilter($request->string('chip')->toString());
        $search = trim($request->string('search')->toString());
        $user = $request->user();
        $purchaseGrade = $request->routeIs('purchaser.b-grade') ? 'B' : 'A';
        $frequentProductIds = $this->frequentProductIds((int) $user->id);

        $dailySummary = $purchaseGrade === 'B'
            ? $this->buildGradeBPurchaseCatalog($date, (int) $user->id)
            : $this->buildDailySummary($date, $frequentProductIds);
        $filteredDailySummary = $this->filterProductsForChip($dailySummary, $selectedChip, $search, $frequentProductIds);

        $draftCarts = $this->draftCartsForDate((int) $user->id, $date)
            ->where('purchase_grade', $purchaseGrade)
            ->values();

        $quickFilters = $this->quickFiltersForPurchaser($user);

        return view('purchasing.purchaser.daily', [
            'date' => $date->format('Y-m-d'),
            'quickFilters' => $quickFilters,
            'selectedChip' => $selectedChip,
            'search' => $search,
            'dailySummary' => $filteredDailySummary,
            'draftCarts' => $draftCarts,
            'purchaseGrade' => $purchaseGrade,
            'dailyFulfillment' => [
                'products' => $dailySummary->count(),
                'approved_qty' => (float) $dailySummary->sum('total_approved_qty'),
                'bought_qty' => (float) $dailySummary->sum('bought_qty'),
                'remaining_qty' => (float) $dailySummary->sum('remaining_qty'),
                'draft_carts' => $draftCarts->count(),
            ],
            'deadlineAlert' => $this->buildDeadlineAlert((int) $user->id, $date),
        ]);
    }

    public function bGrade(Request $request): View|RedirectResponse
    {
        return $this->daily($request);
    }

    public function dailyShare(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $user = $request->user();
        $purchaseGrade = $request->string('purchase_grade')->upper()->toString() === 'B' ? 'B' : 'A';
        $frequentProductIds = $this->frequentProductIds((int) $user->id);
        $dailySummary = $purchaseGrade === 'B'
            ? $this->buildGradeBPurchaseCatalog($date, (int) $user->id)
            : $this->buildDailySummary($date, $frequentProductIds);

        $availableTags = $dailySummary
            ->pluck('category_name')
            ->filter(fn (?string $categoryName): bool => filled($categoryName))
            ->unique()
            ->sort()
            ->values();

        $shareMode = $this->resolveDailyShareMode($request->string('share_mode')->toString());
        $availableProductIds = $dailySummary
            ->pluck('product_id')
            ->map(fn ($productId): int => (int) $productId)
            ->all();

        $sharePresets = PurchaserSharePreset::query()
            ->where('user_id', (int) $user->id)
            ->where('purchase_grade', $purchaseGrade)
            ->orderBy('name')
            ->get(['id', 'name', 'product_ids']);

        $selectedPresetId = $request->integer('preset_id');
        $selectedPreset = $sharePresets->firstWhere('id', $selectedPresetId);

        $selectedTags = collect($request->input('tags', []))
            ->filter(fn ($tag): bool => is_string($tag) && $availableTags->contains($tag))
            ->values()
            ->all();
        $selectedProductIds = collect($request->input('product_ids', []))
            ->map(fn ($productId): int => (int) $productId)
            ->filter(fn (int $productId): bool => in_array($productId, $availableProductIds, true))
            ->unique()
            ->values()
            ->all();

        if ($selectedPreset instanceof PurchaserSharePreset) {
            $selectedProductIds = collect($selectedPreset->product_ids ?? [])
                ->map(fn ($productId): int => (int) $productId)
                ->filter(fn (int $productId): bool => in_array($productId, $availableProductIds, true))
                ->unique()
                ->values()
                ->all();
            $shareMode = 'tag';
        }

        $selectedProductId = $request->integer('product_id');
        if (! $dailySummary->contains(fn (array $summary): bool => (int) $summary['product_id'] === $selectedProductId)) {
            $selectedProductId = 0;
        }

        $shareSummary = $this->filterDailySummaryForShare(
            dailySummary: $dailySummary,
            shareMode: $shareMode,
            selectedTags: $selectedTags,
            selectedProductIds: $selectedProductIds,
            selectedProductId: $selectedProductId,
        );

        $sharePreviewText = $this->buildDailySummaryShareText($shareSummary, $date);
        $shareTotalPreviewText = $this->buildDailySummaryShareTotalText($shareSummary, $date);
        $shareUrl = 'https://api.whatsapp.com/send?text='.rawurlencode($sharePreviewText);
        $shareTotalUrl = 'https://api.whatsapp.com/send?text='.rawurlencode($shareTotalPreviewText);

        return view('purchasing.purchaser.daily_share', [
            'date' => $date->format('Y-m-d'),
            'purchaseGrade' => $purchaseGrade,
            'shareMode' => $shareMode,
            'selectedTags' => $selectedTags,
            'selectedProductIds' => $selectedProductIds,
            'selectedProductId' => $selectedProductId,
            'selectedPresetId' => $selectedPreset instanceof PurchaserSharePreset ? (int) $selectedPreset->id : 0,
            'sharePresets' => $sharePresets,
            'availableTags' => $availableTags,
            'availableProducts' => $dailySummary
                ->sortBy(fn (array $summary): string => Product::sortableSku((string) ($summary['sku'] ?? '')))
                ->map(fn (array $summary): array => [
                    'product_id' => (int) $summary['product_id'],
                    'product_name' => (string) $summary['product_name'],
                    'category_name' => (string) $summary['category_name'],
                    'remaining_qty' => (float) $summary['remaining_qty'],
                    'unit' => (string) $summary['unit'],
                    'search_index' => strtolower(trim(implode(' ', [
                        (string) $summary['product_name'],
                        (string) $summary['category_name'],
                        (string) ($summary['sku'] ?? ''),
                    ]))),
                ])
                ->values(),
            'shareSummary' => $shareSummary,
            'sharePreviewText' => $sharePreviewText,
            'shareTotalPreviewText' => $shareTotalPreviewText,
            'shareUrl' => $shareUrl,
            'shareTotalUrl' => $shareTotalUrl,
        ]);
    }

    public function dailySharePresets(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $user = $request->user();
        $purchaseGrade = $request->string('purchase_grade')->upper()->toString() === 'B' ? 'B' : 'A';
        $availableProducts = $this->presetProductCatalogForUser($user);

        $sharePresets = PurchaserSharePreset::query()
            ->where('user_id', (int) $user->id)
            ->where('purchase_grade', $purchaseGrade)
            ->orderBy('name')
            ->get();

        return view('purchasing.purchaser.daily_share_presets', [
            'date' => $date->format('Y-m-d'),
            'purchaseGrade' => $purchaseGrade,
            'availableProducts' => $availableProducts,
            'sharePresets' => $sharePresets,
        ]);
    }

    public function dailySharePresetStore(Request $request): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'purchase_grade' => ['nullable', 'string', 'in:A,B'],
            'name' => ['required', 'string', 'max:80'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $user = $request->user();
        $date = Carbon::createFromFormat('Y-m-d', (string) $validated['date']);
        $purchaseGrade = ($validated['purchase_grade'] ?? 'A') === 'B' ? 'B' : 'A';
        $availableProductIds = Product::query()
            ->active()
            ->where('show_in_purchaser_order', true)
            ->when(
                $user->hasAssignedCategoryFilter(),
                fn ($query) => $query->whereIn('category_id', $user->assignedCategoryIds())
            )
            ->pluck('id')
            ->map(fn ($productId): int => (int) $productId)
            ->all();

        $productIds = collect($validated['product_ids'])
            ->map(fn ($productId): int => (int) $productId)
            ->filter(fn (int $productId): bool => in_array($productId, $availableProductIds, true))
            ->unique()
            ->values()
            ->all();

        if ($productIds === []) {
            return redirect()
                ->route('purchaser.daily.share.presets', [
                    'date' => $date->format('Y-m-d'),
                    'purchase_grade' => $purchaseGrade,
                ])
                ->withErrors(['Choose at least one valid product for preset.']);
        }

        $preset = PurchaserSharePreset::query()->updateOrCreate(
            [
                'user_id' => (int) $user->id,
                'purchase_grade' => $purchaseGrade,
                'name' => trim((string) $validated['name']),
            ],
            [
                'product_ids' => $productIds,
            ],
        );

        return redirect()
            ->route('purchaser.daily.share', [
                'date' => $date->format('Y-m-d'),
                'purchase_grade' => $purchaseGrade,
                'share_mode' => 'tag',
                'preset_id' => $preset->id,
            ])
            ->with('success', 'Preset saved. Items loaded into Daily Share.');
    }

    /**
     * @return Collection<int, array{product_id:int,product_name:string,category_name:string,remaining_qty:float,unit:string,search_index:string}>
     */
    private function presetProductCatalogForUser(User $user): Collection
    {
        return Product::query()
            ->active()
            ->where('show_in_purchaser_order', true)
            ->with('category:id,name')
            ->when(
                $user->hasAssignedCategoryFilter(),
                fn ($query) => $query->whereIn('category_id', $user->assignedCategoryIds())
            )
            ->ordered()
            ->get(['id', 'name', 'category_id', 'unit', 'sku'])
            ->map(fn (Product $product): array => [
                'product_id' => (int) $product->id,
                'product_name' => (string) $product->name,
                'category_name' => (string) ($product->category?->name ?? ''),
                'remaining_qty' => 0.0,
                'unit' => (string) $product->unit,
                'search_index' => strtolower(trim(implode(' ', [
                    (string) $product->name,
                    (string) ($product->category?->name ?? ''),
                    (string) ($product->sku ?? ''),
                ]))),
            ])
            ->values();
    }

    public function shopOrders(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $status = $request->string('status')->toString();
        $source = $request->string('source')->toString();
        $search = trim($request->string('search')->toString());

        if (! in_array($status, ['submitted', 'update_requested', 'approved', 'rejected'], true)) {
            $status = '';
        }

        if (! in_array($source, ['shop_owner', 'admin_direct_purchase'], true)) {
            $source = '';
        }

        $baseQuery = ShopOrder::query()
            ->with(['shop.client', 'items.product', 'creator', 'reviewedBy'])
            ->withCount('items')
            ->whereDate('business_date', $date)
            ->when($status !== '', fn ($query) => $query->where('state', $status))
            ->when($source !== '', fn ($query) => $query->where('order_source', $source))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('order_number', 'like', '%'.$search.'%')
                        ->orWhereHas('shop', function ($shopQuery) use ($search): void {
                            $shopQuery
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhere('code', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('items.product', function ($productQuery) use ($search): void {
                            $productQuery
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhere('sku', 'like', '%'.$search.'%');
                        });
                });
            });

        $orders = (clone $baseQuery)
            ->orderByRaw("CASE state WHEN 'submitted' THEN 1 WHEN 'update_requested' THEN 2 WHEN 'approved' THEN 3 WHEN 'rejected' THEN 4 ELSE 5 END")
            ->latest('id')
            ->paginate(15, ['*'], 'orders_page')
            ->withQueryString();

        $statusCounts = ShopOrder::query()
            ->whereDate('business_date', $date)
            ->selectRaw('state, count(*) as aggregate')
            ->groupBy('state')
            ->pluck('aggregate', 'state')
            ->all();

        return view('purchasing.purchaser.shop_orders.index', [
            'date' => $date->format('Y-m-d'),
            'orders' => $orders,
            'status' => $status,
            'source' => $source,
            'search' => $search,
            'statusCounts' => $statusCounts,
            'totalOrders' => array_sum($statusCounts),
            'deadlineAlert' => $this->buildDeadlineAlert((int) $request->user()->id, $date),
        ]);
    }

    public function shopOrderShow(Request $request, string $orderNumber): View
    {
        $this->ensurePurchaser($request);

        $order = ShopOrder::query()
            ->where('order_number', $orderNumber)
            ->with([
                'shop.client',
                'items.product.category',
                'creator',
                'reviewedBy',
                'latestPendingRevision.items.product',
                'latestResolvedRevision.items.product',
                'latestResolvedRevision.reviewedBy',
            ])
            ->firstOrFail();

        return view('purchasing.purchaser.shop_orders.show', [
            'order' => $order,
            'date' => $order->business_date->format('Y-m-d'),
        ]);
    }

    public function bulkBuy(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $user = $request->user();
        $purchaseGrade = $request->string('purchase_grade')->upper()->toString() === 'B' ? 'B' : 'A';
        $frequentProductIds = $this->frequentProductIds((int) $user->id);
        $dailySummary = $purchaseGrade === 'B'
            ? $this->buildGradeBPurchaseCatalog($date, (int) $user->id)
            : $this->buildDailySummary($date, $frequentProductIds, includeDetails: false);

        $summaryProductIds = $dailySummary->pluck('product_id')->all();
        $gradeBAddOnProductIds = $purchaseGrade === 'B'
            ? $dailySummary->where('is_direct_catalog', true)->pluck('product_id')->all()
            : [];
        $addOnProducts = Product::query()
            ->select(['id', 'category_id', 'name', 'sku', 'unit', 'is_active'])
            ->with('category:id,name')
            ->active()
            ->where('show_in_purchaser_order', true)
            ->ordered()
            ->when(
                $purchaseGrade === 'B',
                fn ($query) => $query->whereIn('id', $gradeBAddOnProductIds),
                fn ($query) => $query->whereNotIn('id', $summaryProductIds),
            )
            ->when(
                $user->hasAssignedCategoryFilter(),
                fn ($query) => $query->whereIn('category_id', $user->assignedCategoryIds()),
            )
            ->get();

        $orderedSummary = $purchaseGrade === 'B' ? $dailySummary->where('has_grade_b_order', true) : $dailySummary;
        $pendingSummary = $orderedSummary->filter(fn (array $summary): bool => (float) $summary['remaining_qty'] > 0)->values();
        $fulfilledSummary = $orderedSummary->filter(fn (array $summary): bool => (float) $summary['remaining_qty'] <= 0)->values();
        $quickFilters = $this->quickFiltersForPurchaser($user);

        return view('purchasing.purchaser.bulk_buy', [
            'date' => $date->format('Y-m-d'),
            'purchaseGrade' => $purchaseGrade,
            'quickFilters' => $quickFilters,
            'dailySummary' => $dailySummary,
            'pendingSummary' => $pendingSummary,
            'fulfilledSummary' => $fulfilledSummary,
            'addOnProducts' => $addOnProducts,
            'deadlineAlert' => $this->buildDeadlineAlert((int) $user->id, $date),
        ]);
    }

    public function bulkBuyDetails(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $purchaseGrade = $request->string('purchase_grade')->upper()->toString() === 'B' ? 'B' : 'A';
        $productIds = $request->input('product_ids');
        if (empty($productIds) || ! is_array($productIds)) {
            return redirect()
                ->route('purchaser.bulk-buy', ['date' => $date->format('Y-m-d'), 'purchase_grade' => $purchaseGrade])
                ->with('error', 'Please select at least one product.');
        }

        $user = $request->user();
        $frequentProductIds = $this->frequentProductIds((int) $user->id);

        $dailySummaryMap = ($purchaseGrade === 'B'
            ? $this->buildGradeBPurchaseCatalog($date, (int) $user->id)
            : $this->buildDailySummary($date, $frequentProductIds))->keyBy('product_id');
        $products = Product::query()
            ->with(['category', 'orderUnits'])
            ->whereIn('id', array_map('intval', $productIds))
            ->get();

        $selectedSummary = collect();
        foreach ($products as $product) {
            if ($dailySummaryMap->has($product->id)) {
                $selectedSummary->push([
                    ...$dailySummaryMap->get($product->id),
                    'orderable_units' => $this->allMeasurementUnitOptions($product),
                ]);
            } else {
                $selectedSummary->push([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'category_name' => $product->category?->name,
                    'sku' => $product->sku,
                    'unit' => $product->unit,
                    'orderable_units' => $this->allMeasurementUnitOptions($product),
                    'total_approved_qty' => 0.0,
                    'bought_qty' => 0.0,
                    'draft_qty' => 0.0,
                    'remaining_qty' => 0.0,
                    'is_frequent' => in_array($product->id, $frequentProductIds, true),
                ]);
            }
        }

        if ($selectedSummary->isEmpty()) {
            return redirect()
                ->route('purchaser.bulk-buy', ['date' => $date->format('Y-m-d'), 'purchase_grade' => $purchaseGrade])
                ->with('error', 'Selected products are invalid.');
        }

        $draftCarts = $this->draftCartsForDate((int) $user->id, $date)
            ->where('purchase_grade', $purchaseGrade)
            ->values();

        $uniqueSupplierIds = $draftCarts->pluck('supplier_id')->unique()->all();
        $pricesBySupplier = [];
        $selectedProductIds = $selectedSummary->pluck('product_id')->all();
        foreach ($uniqueSupplierIds as $supId) {
            $pricesBySupplier[$supId] = $this->vendorPriceService->previousPricesForSupplier(
                $supId,
                $selectedProductIds,
            );
        }

        $bulkPriceHintsByCart = $draftCarts->mapWithKeys(fn (PurchaserCart $cart): array => [
            $cart->id => $pricesBySupplier[$cart->supplier_id] ?? [],
        ])->all();

        return view('purchasing.purchaser.bulk_buy_details', [
            'date' => $date->format('Y-m-d'),
            'purchaseGrade' => $purchaseGrade,
            'dailySummary' => $selectedSummary,
            'draftCarts' => $draftCarts,
            'bulkPriceHintsByCart' => $bulkPriceHintsByCart,
            'bulkFallbackPriceHints' => $this->vendorPriceService->previousPricesForSupplier(
                null,
                $selectedSummary->pluck('product_id')->all(),
            ),
            'deadlineAlert' => $this->buildDeadlineAlert((int) $user->id, $date),
        ]);
    }

    public function cart(Request $request): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        return redirect()->route('purchaser.vendors', ['date' => $date->format('Y-m-d')]);
    }

    public function bill(Request $request, PurchaserCart $cart): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = PurchaserCart::query()
            ->whereKey($cart->id)
            ->where('user_id', $request->user()->id)
            ->with(['supplier', 'items.product.category', 'goodsReceived', 'purchaseInvoice'])
            ->firstOrFail();

        if ($cart->status === 'submitted' || $cart->purchase_invoice_id || $cart->purchaseInvoice) {
            if ($cart->purchaseInvoice) {
                return redirect()
                    ->route('purchaser.invoices.show', $cart->purchaseInvoice)
                    ->with('info', 'Cart has already been submitted.');
            }

            return redirect()
                ->route('purchaser.vendors', ['date' => $cart->business_date->format('Y-m-d')])
                ->with('info', 'Cart has already been submitted.');
        }

        if ($cart->items->isEmpty()) {
            return redirect()
                ->route('purchaser.vendors', ['date' => $cart->business_date->format('Y-m-d')])
                ->withErrors(['The selected cart is empty.']);
        }

        return view('purchasing.purchaser.bill', [
            'date' => $cart->business_date->format('Y-m-d'),
            'cart' => $cart,
            'suppliers' => $this->scopedSuppliersForUser($request->user()),
            'subtotal' => (float) $cart->items->sum(
                fn ($item) => round((float) $item->quantity * (float) $item->unit_price, 2)
            ),
            'companyDetails' => $this->companyDetailsForBill(),
            'vendorPriceHints' => $this->vendorPriceService->previousPricesForSupplier(
                $cart->supplier_id,
                $cart->items->pluck('product_id')->all(),
            ),
            'deadlineAlert' => $this->buildDeadlineAlert((int) $request->user()->id, $cart->business_date),
        ]);
    }

    /**
     * @return array{name: string, address: string|null, phone: string|null, email: string|null}
     */
    private function companyDetailsForBill(): array
    {
        $settings = BusinessSetting::query()
            ->whereIn('key', [
                'company_name',
                'company_address',
                'company_phone',
                'company_email',
                'business_name',
                'business_address',
                'business_phone',
                'business_email',
            ])
            ->pluck('value', 'key');

        return [
            'name' => $settings->get('company_name') ?: $settings->get('business_name') ?: 'Green Leaf',
            'address' => $settings->get('company_address') ?: $settings->get('business_address'),
            'phone' => $settings->get('company_phone') ?: $settings->get('business_phone'),
            'email' => $settings->get('company_email') ?: $settings->get('business_email'),
        ];
    }

    public function history(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $userId = (int) $request->user()->id;
        $includeExpenses = $request->boolean('include_expenses', false);

        $todayCarts = PurchaserCart::query()
            ->where('user_id', $userId)
            ->whereDate('business_date', $date)
            ->with([
                'supplier',
                'items.product.category',
                'purchaseOrder',
                'goodsReceived',
                'purchaseInvoice',
            ])
            ->orderByDesc('updated_at')
            ->get();

        $historyCarts = PurchaserCart::query()
            ->where('user_id', $userId)
            ->whereDate('business_date', '<', $date)
            ->with([
                'supplier',
                'items.product.category',
                'purchaseOrder',
                'goodsReceived',
                'purchaseInvoice',
            ])
            ->orderByDesc('business_date')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        $overdueCarts = $this->overdueCartsForUser($userId);

        $historyCarts = $historyCarts
            ->merge($overdueCarts)
            ->unique('id')
            ->values();

        $allCarts = $todayCarts->merge($historyCarts)->unique('id')->values();

        $groupedCarts = collect([
            'today' => $todayCarts->sortByDesc(fn (PurchaserCart $cart) => $cart->purchaseInvoice?->updated_at ?? $cart->updated_at)->values(),
            'history' => $historyCarts->sortByDesc(fn (PurchaserCart $cart) => $cart->purchaseInvoice?->updated_at ?? $cart->updated_at)->values(),
        ]);

        $relatedBatchState = $this->relatedBatchStateForCarts($allCarts);

        $todayTotalPurchase = (float) $todayCarts->sum(function (PurchaserCart $cart) {
            if ($cart->status === 'draft') {
                return (float) $cart->items->sum('line_total') - (float) $cart->discount_amount;
            }
            if ($cart->purchaseInvoice) {
                return max(0.0, (float) $cart->purchaseInvoice->amount - (float) $cart->purchaseInvoice->discount_amount);
            }

            return max(0.0, (float) $cart->items->sum('line_total') - (float) $cart->discount_amount);
        });

        $todayTotalCash = (float) $todayCarts->sum(function (PurchaserCart $cart) {
            if ($cart->purchaseInvoice) {
                $net = max(0.0, (float) $cart->purchaseInvoice->amount - (float) $cart->purchaseInvoice->discount_amount);

                return min($net, max(0.0, (float) $cart->purchaseInvoice->paid_amount));
            }

            return strcasecmp((string) $cart->payment_method, 'Cash') === 0
                ? max(0.0, (float) $cart->items->sum('line_total') - (float) $cart->discount_amount)
                : 0.0;
        });

        $todaySummary = [
            'date_formatted' => $date->format('l, d M Y'),
            'total_carts' => $todayCarts->count(),
            'total_purchase' => $todayTotalPurchase,
            'total_cash' => $todayTotalCash,
            'total_credit' => max(0.0, $todayTotalPurchase - $todayTotalCash),
        ];

        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();

        $monthCarts = PurchaserCart::query()
            ->where('user_id', $userId)
            ->whereBetween('business_date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
            ->with(['items', 'purchaseInvoice'])
            ->get();

        $monthTotalPurchase = (float) $monthCarts->sum(function (PurchaserCart $cart) {
            if ($cart->status === 'draft') {
                return (float) $cart->items->sum('line_total') - (float) $cart->discount_amount;
            }
            if ($cart->purchaseInvoice) {
                return max(0.0, (float) $cart->purchaseInvoice->amount - (float) $cart->purchaseInvoice->discount_amount);
            }

            return max(0.0, (float) $cart->items->sum('line_total') - (float) $cart->discount_amount);
        });

        $monthTotalCash = (float) $monthCarts->sum(function (PurchaserCart $cart) {
            if ($cart->purchaseInvoice) {
                $net = max(0.0, (float) $cart->purchaseInvoice->amount - (float) $cart->purchaseInvoice->discount_amount);

                return min($net, max(0.0, (float) $cart->purchaseInvoice->paid_amount));
            }

            return strcasecmp((string) $cart->payment_method, 'Cash') === 0
                ? max(0.0, (float) $cart->items->sum('line_total') - (float) $cart->discount_amount)
                : 0.0;
        });

        $todayExpenseTotal = round((float) ProcurementExpense::query()
            ->where('user_id', $userId)
            ->whereDate('expense_date', $date->toDateString())
            ->sum('amount'), 2);

        $monthExpenseTotal = round((float) ProcurementExpense::query()
            ->where('user_id', $userId)
            ->whereBetween('expense_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount'), 2);

        $todaySummary = array_merge($todaySummary, [
            'expense_total' => $todayExpenseTotal,
            'grand_total' => round($todayTotalPurchase + ($includeExpenses ? $todayExpenseTotal : 0.0), 2),
        ]);

        $monthSummary = [
            'month_name' => $date->format('F Y'),
            'start_date_formatted' => $monthStart->format('d M Y'),
            'end_date_formatted' => $monthEnd->format('d M Y'),
            'total_carts' => $monthCarts->count(),
            'total_purchase' => $monthTotalPurchase,
            'total_cash' => $monthTotalCash,
            'total_credit' => max(0.0, $monthTotalPurchase - $monthTotalCash),
            'expense_total' => $monthExpenseTotal,
            'grand_total' => round($monthTotalPurchase + ($includeExpenses ? $monthExpenseTotal : 0.0), 2),
        ];

        return view('purchasing.purchaser.history', [
            'date' => $date->format('Y-m-d'),
            'todaySummary' => $todaySummary,
            'monthSummary' => $monthSummary,
            'includeExpenses' => $includeExpenses,
            'groupedCarts' => $groupedCarts,
            'statusBadges' => $this->statusBadgesForCarts($allCarts, $relatedBatchState),
            'relatedBatchState' => $relatedBatchState,
            'relatedReceiptNotes' => $this->relatedReceiptNotesForCarts($allCarts),
            'deadlineAlert' => $this->buildDeadlineAlert($userId, $date, $overdueCarts, $relatedBatchState),
        ]);
    }

    public function vendors(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $user = $request->user();
        $purchaseGrade = $request->string('purchase_grade')->upper()->toString();
        $purchaseGrade = in_array($purchaseGrade, ['A', 'B'], true) ? $purchaseGrade : null;
        $focusCartId = $request->integer('focus_cart');
        $probe = PerformanceProbe::start('purchaser.vendors', [
            'route' => $request->route()?->getName(),
            'date' => $date->format('Y-m-d'),
            'tab' => $request->query('tab'),
            'user_id' => $user->id,
        ]);

        $carts = PurchaserCart::query()
            ->where('user_id', $user->id)
            ->whereDate('business_date', $date)
            ->when($purchaseGrade !== null, fn ($query) => $query->where('purchase_grade', $purchaseGrade))
            ->with([
                'supplier',
                'items.product.category',
                'goodsReceived.items.product',
                'goodsReceived.items.purchaseOrderItem.product',
                'purchaseOrder',
                'purchaseInvoice',
            ])
            ->orderByDesc('updated_at')
            ->get();
        $probe?->checkpoint('load_carts_with_relations');

        $relatedBatchState = $this->relatedBatchStateForCarts($carts);
        $probe?->checkpoint('related_batch_state');
        $relatedReceiptNotes = $this->relatedReceiptNotesForCarts($carts);
        $probe?->checkpoint('related_receipt_notes');
        $relatedReceiptDiscrepancies = $carts->mapWithKeys(fn (PurchaserCart $cart): array => [
            (int) $cart->id => $this->buildReceiptDiscrepancySummary($cart->goodsReceived),
        ])->all();
        $probe?->checkpoint('receipt_discrepancies');
        $draftCarts = $carts->where('status', 'draft')->values();
        $submittedCarts = $carts->where('status', 'submitted')->values();
        $cancelledCarts = $carts->where('status', 'cancelled')->values();
        $pendingCarts = $submittedCarts
            ->filter(fn (PurchaserCart $cart): bool => ! $this->isWarehouseConfirmed($relatedBatchState[(int) $cart->id] ?? []) || $this->cartHasPaymentPending($cart))
            ->values();
        $completedCarts = $submittedCarts
            ->filter(fn (PurchaserCart $cart): bool => $this->isWarehouseConfirmed($relatedBatchState[(int) $cart->id] ?? []) && ! $this->cartHasPaymentPending($cart))
            ->values();
        $activeTab = $request->string('tab')->toString();

        if (! in_array($activeTab, ['draft', 'pending', 'completed', 'cancelled'], true)) {
            $activeTab = match (true) {
                $completedCarts->contains('id', $focusCartId) => 'completed',
                $pendingCarts->contains('id', $focusCartId) => 'pending',
                $cancelledCarts->contains('id', $focusCartId) => 'cancelled',
                default => 'draft',
            };
        }
        $probe?->checkpoint('split_tabs');

        $mergeSuggestions = $this->buildDraftMergeSuggestions($draftCarts);
        $mergeableDraftCounts = $mergeSuggestions
            ->mapWithKeys(fn (array $suggestion): array => [
                (int) $suggestion['target_cart']->id => (int) $suggestion['count'] - 1,
            ])
            ->all();
        $probe?->checkpoint('merge_suggestions');

        $productCatalog = Product::query()
            ->with('category')
            ->active()
            ->where('show_in_purchaser_order', true)
            ->ordered()
            ->get();
        $probe?->checkpoint('product_catalog');

        $suppliers = $this->scopedSuppliersForUser($request->user());
        $probe?->checkpoint('suppliers');
        $vendorPriceHintsByCart = $carts->mapWithKeys(fn (PurchaserCart $cart): array => [
            $cart->id => $this->vendorPriceService->previousPricesForSupplier(
                $cart->supplier_id,
                $cart->items->pluck('product_id')->all(),
            ),
        ])->all();
        $probe?->checkpoint('vendor_price_hints');
        $deadlineAlert = $this->buildDeadlineAlert((int) $user->id, $date);
        $probe?->checkpoint('deadline_alert');
        $probe?->finish([
            'cart_count' => $carts->count(),
            'draft_count' => $draftCarts->count(),
            'pending_count' => $pendingCarts->count(),
            'completed_count' => $completedCarts->count(),
            'cancelled_count' => $cancelledCarts->count(),
            'product_count' => $productCatalog->count(),
            'supplier_count' => $suppliers->count(),
        ]);

        return view('purchasing.purchaser.vendors', [
            'date' => $date->format('Y-m-d'),
            'purchaseGrade' => $purchaseGrade,
            'draftCarts' => $draftCarts,
            'pendingCarts' => $pendingCarts,
            'completedCarts' => $completedCarts,
            'cancelledCarts' => $cancelledCarts,
            'mergeSuggestions' => $mergeSuggestions,
            'mergeableDraftCounts' => $mergeableDraftCounts,
            'productCatalog' => $productCatalog,
            'suppliers' => $suppliers,
            'vendorPriceHintsByCart' => $vendorPriceHintsByCart,
            'activeTab' => $activeTab,
            'focusCartId' => $focusCartId,
            'relatedBatchState' => $relatedBatchState,
            'relatedReceiptNotes' => $relatedReceiptNotes,
            'relatedReceiptDiscrepancies' => $relatedReceiptDiscrepancies,
            'deadlineAlert' => $deadlineAlert,
        ]);
    }

    public function finance(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $selectedTab = $request->string('tab')->toString();
        if (! in_array($selectedTab, ['today', 'old'], true)) {
            $selectedTab = 'today';
        }
        $search = trim($request->string('search')->toString());

        $baseInvoiceQuery = PurchaseInvoice::query()
            ->with(['supplier', 'goodsReceived', 'purchaserCart'])
            ->whereHas('purchaserCart', function ($query) use ($request, $date): void {
                $query
                    ->where('user_id', $request->user()->id)
                    ->whereDate('business_date', '<=', $date);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('invoice_number', 'like', '%'.$search.'%')
                        ->orWhere('payment_status', 'like', '%'.$search.'%')
                        ->orWhere('payment_method', 'like', '%'.$search.'%')
                        ->orWhereHas('supplier', function ($supplierQuery) use ($search): void {
                            $supplierQuery
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhere('mobile_number', 'like', '%'.$search.'%')
                                ->orWhere('location', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('purchaserCart', function ($cartQuery) use ($search): void {
                            $cartQuery
                                ->where('cart_number', 'like', '%'.$search.'%')
                                ->orWhere('bill_number', 'like', '%'.$search.'%');
                        });
                });
            });

        $invoices = (clone $baseInvoiceQuery)
            ->whereHas('purchaserCart', function ($query) use ($selectedTab, $date): void {
                $query->when(
                    $selectedTab === 'old',
                    fn ($cartQuery) => $cartQuery->whereDate('business_date', '<', $date),
                    fn ($cartQuery) => $cartQuery->whereDate('business_date', $date),
                );
            })
            ->orderByDesc('id')
            ->paginate(20);

        return view('purchasing.purchaser.finance', [
            'date' => $date->format('Y-m-d'),
            'invoices' => $invoices,
            'search' => $search,
            'selectedTab' => $selectedTab,
            'financeTabs' => [
                'today' => (clone $baseInvoiceQuery)
                    ->whereHas('purchaserCart', fn ($query) => $query->whereDate('business_date', $date))
                    ->count(),
                'old' => (clone $baseInvoiceQuery)
                    ->whereHas('purchaserCart', fn ($query) => $query->whereDate('business_date', '<', $date))
                    ->count(),
            ],
            'financeAudience' => 'purchaser',
            'canManageSuppliers' => true,
            'deadlineAlert' => $this->buildDeadlineAlert((int) $request->user()->id, $date),
        ]);
    }

    public function cash(Request $request): View
    {
        $this->ensurePurchaser($request);

        $user = $request->user();
        $credits = PurchaserCredit::query()
            ->where('purchaser_id', $user->id)
            ->with(['purchaseInvoice', 'creator'])
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->get();

        $totalIn = round((float) $credits->where('type', 'in')->sum('amount'), 2);
        $totalOut = round((float) $credits->where('type', 'out')->sum('amount'), 2);
        $balance = round($totalIn - $totalOut, 2);

        return view('purchasing.purchaser.cash', [
            'credits' => $credits,
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'balance' => $balance,
        ]);
    }

    public function supplierHub(Request $request): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $search = trim($request->string('search')->toString());
        $normalizedSearch = mb_strtolower($search);
        $selectedTab = $request->string('tab')->toString();
        if (! in_array($selectedTab, ['pending', 'credit'], true)) {
            $selectedTab = 'pending';
        }
        $userId = (int) $request->user()->id;
        $suppliers = Supplier::query()
            ->when($search !== '', function ($query) use ($search, $normalizedSearch): void {
                $query->where(function ($innerQuery) use ($search, $normalizedSearch): void {
                    $innerQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('mobile_number', 'like', '%'.$search.'%')
                        ->orWhere('location', 'like', '%'.$search.'%')
                        ->orWhereHas('purchaseInvoices', function ($invoiceQuery) use ($search): void {
                            $invoiceQuery
                                ->where('payment_status', 'like', '%'.$search.'%')
                                ->orWhere('payment_method', 'like', '%'.$search.'%')
                                ->orWhere('invoice_number', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('purchaserCarts', function ($cartQuery) use ($search): void {
                            $cartQuery
                                ->where('cart_number', 'like', '%'.$search.'%')
                                ->orWhere('status', 'like', '%'.$search.'%');
                        });

                    if (str_contains($normalizedSearch, 'pending')) {
                        $innerQuery->orWhereHas('purchaseInvoices', function ($invoiceQuery): void {
                            $invoiceQuery->whereIn('payment_status', ['unpaid', 'partial', 'credit_pending_approval']);
                        });
                    }

                    if (str_contains($normalizedSearch, 'credit')) {
                        $innerQuery->orWhere('credit_approved', true);
                    }

                    if (str_contains($normalizedSearch, 'paid')) {
                        $innerQuery->orWhereHas('purchaseInvoices', function ($invoiceQuery): void {
                            $invoiceQuery->where('payment_status', 'paid');
                        });
                    }
                });
            })
            ->with([
                'purchaseInvoices' => fn ($query) => $query
                    ->with('purchaserCart')
                    ->latest('updated_at'),
                'purchaserCarts' => fn ($query) => $query
                    ->with('purchaseInvoice')
                    ->latest('business_date'),
            ])
            ->orderBy('name')
            ->get();

        $sameDayAssignedDrafts = PurchaserCart::query()
            ->where('user_id', $userId)
            ->whereDate('business_date', $date)
            ->where('status', 'draft')
            ->whereNotNull('supplier_id')
            ->whereHas('items')
            ->with(['supplier', 'items.product', 'purchaseInvoice', 'goodsReceived'])
            ->orderByDesc('updated_at')
            ->get();

        $overdueCarts = $this->overdueCartsForUser($userId)->loadMissing(['supplier', 'items.product', 'purchaseInvoice', 'goodsReceived']);
        $overdueBatchState = $this->relatedBatchStateForCarts($overdueCarts);

        $tabCounts = [
            'pending' => $suppliers->filter(fn (Supplier $supplier): bool => $this->supplierPendingHubIssueCount($supplier, $sameDayAssignedDrafts, $overdueCarts, $overdueBatchState, 'pending') > 0)->count(),
            'credit' => $suppliers->filter(fn (Supplier $supplier): bool => $this->supplierPendingHubIssueCount($supplier, $sameDayAssignedDrafts, $overdueCarts, $overdueBatchState, 'credit') > 0)->count(),
        ];

        $issueSections = $this->buildSupplierIssueSections(
            userId: $userId,
            selectedDate: $date,
            suppliers: $suppliers,
            selectedTab: $selectedTab,
            sameDayAssignedDrafts: $sameDayAssignedDrafts,
            overdueCarts: $overdueCarts,
            overdueBatchState: $overdueBatchState,
        );

        $filteredSuppliers = $suppliers->filter(fn (Supplier $supplier): bool => $this->supplierPendingHubIssueCount($supplier, $sameDayAssignedDrafts, $overdueCarts, $overdueBatchState, $selectedTab) > 0)->values();

        $supplierRows = $suppliers->map(function (Supplier $supplier) use ($date, $selectedTab, $sameDayAssignedDrafts, $overdueCarts, $overdueBatchState): array {
            $relevantInvoices = $this->linkedInvoicesForSupplier($supplier);
            $recentInvoice = $relevantInvoices
                ->sortByDesc(fn (PurchaseInvoice $invoice): int => $invoice->updated_at?->getTimestamp() ?? 0)
                ->first();
            $recentCart = $supplier->purchaserCarts->first();
            $totalAmount = round((float) $relevantInvoices->sum('amount'), 2);
            $discountAmount = round((float) $relevantInvoices->sum('discount_amount'), 2);
            $paidAmount = round((float) $relevantInvoices->sum('paid_amount'), 2);
            $balanceAmount = round((float) $relevantInvoices->sum(fn (PurchaseInvoice $invoice): float => $this->invoiceRemainingBalance($invoice)), 2);
            $recentBusinessDate = $recentInvoice?->purchaserCart?->business_date ?? $recentCart?->business_date;
            $pendingIssueSummary = $this->supplierPendingHubIssueSummary($supplier, $sameDayAssignedDrafts, $overdueCarts, $overdueBatchState, $selectedTab);

            return [
                'supplier' => $supplier,
                'pending_count' => $pendingIssueSummary['count'],
                'pending_issue_label' => $pendingIssueSummary['label'],
                'pending_issue_tone' => $pendingIssueSummary['tone'],
                'pending_issue_paid' => $pendingIssueSummary['paid'],
                'recent_invoice_number' => $recentInvoice?->invoice_number ?: 'None yet',
                'recent_cart_number' => $recentCart?->cart_number ?: 'No cart yet',
                'recent_business_date' => $recentBusinessDate?->format('d M Y') ?: '—',
                'recent_updated_label' => $recentInvoice?->updated_at?->format('d M Y') ?: '—',
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'paid_amount' => $paidAmount,
                'balance_amount' => $balanceAmount,
                'history_route' => route('purchaser.suppliers.show', ['supplier' => $supplier, 'date' => $date->format('Y-m-d')]),
            ];
        });

        return view('purchasing.purchaser.suppliers.index', [
            'date' => $date->format('Y-m-d'),
            'search' => $search,
            'suppliers' => $filteredSuppliers,
            'supplierRows' => $supplierRows,
            'issueSections' => $issueSections,
            'selectedTab' => $selectedTab,
            'tabCounts' => $tabCounts,
            'deadlineAlert' => $this->buildDeadlineAlert($userId, $date, $overdueCarts, $overdueBatchState),
        ]);
    }

    public function supplierShow(Request $request, Supplier $supplier): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $userId = (int) $request->user()->id;
        $supplier = Supplier::query()
            ->whereKey($supplier->id)
            ->with([
                'purchaseInvoices' => fn ($query) => $query
                    ->with(['purchaserCart.goodsReceived.items.product', 'purchaserCart.goodsReceived.items.purchaseOrderItem.product', 'goodsReceived.items.product', 'goodsReceived.items.purchaseOrderItem.product'])
                    ->latest('updated_at'),
                'purchaserCarts' => fn ($query) => $query
                    ->with(['items.product', 'purchaseInvoice', 'goodsReceived.items.product', 'goodsReceived.items.purchaseOrderItem.product'])
                    ->latest('business_date'),
            ])
            ->firstOrFail();

        $operationalDate = $this->businessDayService->operationalDate();
        $relatedBatchState = $this->relatedBatchStateForCarts($supplier->purchaserCarts);
        $vendorHistoryEntries = $supplier->purchaserCarts
            ->map(function (PurchaserCart $cart) use ($relatedBatchState, $operationalDate): array {
                $invoice = $cart->purchaseInvoice;
                $batchState = $relatedBatchState[(int) $cart->id] ?? [];
                $isCancelled = $cart->status === 'cancelled' || ($cart->status === 'draft' && $cart->business_date->lt($operationalDate));
                $operationalState = $isCancelled
                    ? [
                        'label' => 'Cancelled',
                        'tone' => 'bg-rose-100 text-rose-700',
                        'unresolved' => false,
                        'payment_pending' => false,
                    ]
                    : $this->cartOperationalState($cart, $batchState);
                $receiptNotes = $cart->goodsReceived?->notes;
                $discrepancySummary = $this->buildReceiptDiscrepancySummary($cart->goodsReceived);
                $itemCount = (int) $cart->items->count();
                $totalQuantity = round((float) $cart->items->sum('quantity'), 2);
                $itemSummary = $cart->items
                    ->map(function ($item): array {
                        $productName = $item->product?->name ?? 'Product';
                        $quantity = (float) $item->quantity;
                        $unit = $item->product?->unit ?? '';
                        $price = (float) $item->unit_price;
                        $lineTotal = (float) $item->line_total;

                        return [
                            'name' => $productName,
                            'quantity' => $this->trimTrailingZeros($quantity),
                            'unit' => $unit,
                            'price' => number_format($price, 2),
                            'total' => number_format($lineTotal, 2),
                            'display' => trim("{$productName} {$this->trimTrailingZeros($quantity)} {$unit}"),
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'date_key' => $cart->business_date->format('Y-m-d'),
                    'date_label' => $cart->business_date->format('d M Y'),
                    'cart_number' => $cart->cart_number,
                    'status' => $cart->status,
                    'is_cancelled' => $isCancelled,
                    'invoice_id' => $invoice?->id,
                    'invoice_number' => $invoice?->invoice_number,
                    'amount' => (float) ($invoice ? max(0, (float) $invoice->amount - (float) $invoice->discount_amount) : max(0, (float) $cart->items->sum('line_total') - (float) $cart->discount_amount)),
                    'updated_at' => $invoice?->updated_at ?? $cart->updated_at,
                    'updated_label' => ($invoice?->updated_at ?? $cart->updated_at)?->format('d M Y h:i A'),
                    'payment_status' => $isCancelled ? 'Cancelled' : str($invoice?->payment_status ?: $cart->payment_status ?: 'unpaid')->replace('_', ' ')->title()->toString(),
                    'payment_method' => $invoice?->payment_method ?: $cart->payment_method ?: 'Cash',
                    'paid_amount' => (float) ($invoice?->paid_amount ?? $cart->paid_amount ?? 0),
                    'balance_amount' => $isCancelled ? 0.0 : ($invoice ? $this->invoiceRemainingBalance($invoice) : 0.0),
                    'item_count' => $itemCount,
                    'total_quantity' => $totalQuantity,
                    'item_summary' => $itemSummary,
                    'receipt_notes' => $receiptNotes,
                    'discrepancy_summary' => $discrepancySummary,
                    'status_label' => $isCancelled ? 'Cancelled' : $operationalState['label'],
                    'status_tone' => $isCancelled ? 'bg-rose-100 text-rose-700' : $operationalState['tone'],
                    'is_operationally_unresolved' => $isCancelled ? false : $operationalState['unresolved'],
                    'is_payment_pending' => $isCancelled ? false : $operationalState['payment_pending'],
                    'payment_route' => (! $isCancelled && $operationalState['payment_pending'] && $invoice) ? route('purchaser.invoices.payment', $invoice) : null,
                    'payment_modal' => (! $isCancelled && $operationalState['payment_pending'] && $invoice) ? [
                        'number' => $invoice->invoice_number,
                        'supplier' => $cart->supplier?->name,
                        'amount' => round((float) $invoice->amount, 2),
                        'discountAmount' => round((float) $invoice->discount_amount, 2),
                        'paidAmount' => round((float) $invoice->paid_amount, 2),
                        'paymentMethod' => $invoice->payment_method ?: 'Cash',
                        'paymentNote' => $invoice->payment_note,
                        'paymentDetails' => $invoice->payment_details,
                        'creditApproved' => (bool) $cart->supplier?->credit_approved,
                    ] : null,
                ];
            });

        $activeEntries = $vendorHistoryEntries->where('is_cancelled', false)->values();
        $cancelledEntries = $vendorHistoryEntries->where('is_cancelled', true)->values();

        $groupHistory = function ($entries) {
            return collect($entries)
                ->groupBy('date_key')
                ->map(function ($dayEntries, $historyDate): array {
                    $dayEntries = collect($dayEntries)->values();
                    $firstEntry = $dayEntries->first();

                    return [
                        'date_key' => (string) $historyDate,
                        'date_label' => $firstEntry['date_label'],
                        'record_count' => $dayEntries->count(),
                        'item_count' => (int) $dayEntries->sum('item_count'),
                        'total_quantity' => round((float) $dayEntries->sum('total_quantity'), 2),
                        'total_amount' => round((float) $dayEntries->sum('amount'), 2),
                        'paid_amount' => round((float) $dayEntries->sum('paid_amount'), 2),
                        'balance_amount' => round((float) $dayEntries->sum('balance_amount'), 2),
                        'pending_count' => $dayEntries->where('is_operationally_unresolved', true)->count(),
                        'completed_count' => $dayEntries->where('is_operationally_unresolved', false)->count(),
                        'entries' => $dayEntries,
                    ];
                })
                ->sortByDesc('date_key')
                ->values();
        };

        $vendorHistory = $groupHistory($activeEntries);
        $cancelledHistory = $groupHistory($cancelledEntries);

        $historyTotals = [
            'pending_amount' => round((float) $activeEntries->sum('balance_amount'), 2),
            'paid_amount' => round((float) $activeEntries->sum('paid_amount'), 2),
            'total_amount' => round((float) $activeEntries->sum('amount'), 2),
            'discount_amount' => round((float) $activeEntries->sum(fn (array $entry): float => max(0, $entry['amount'] - $entry['paid_amount'] - $entry['balance_amount'])), 2),
            'item_count' => (int) $activeEntries->sum('item_count'),
            'record_count' => (int) $activeEntries->count(),
        ];

        $pendingEntries = $activeEntries->where('is_operationally_unresolved', true)->values();
        $completedEntries = $activeEntries->where('is_operationally_unresolved', false)->values();

        return view('purchasing.purchaser.suppliers.show', [
            'date' => $date->format('Y-m-d'),
            'supplier' => $supplier,
            'pendingInvoices' => $pendingEntries,
            'completedInvoices' => $completedEntries,
            'vendorHistory' => $vendorHistory,
            'cancelledHistory' => $cancelledHistory,
            'historyTotals' => $historyTotals,
            'deadlineAlert' => $this->buildDeadlineAlert($userId, $date),
        ]);
    }

    public function invoicePdf(Request $request, PurchaseInvoice $invoice): View
    {
        $this->ensurePurchaser($request);

        $invoice = PurchaseInvoice::query()
            ->whereKey($invoice->id)
            ->whereHas('purchaserCart', function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id);
            })
            ->with([
                'supplier',
                'purchaserCart.items.product',
                'goodsReceived.items.product',
                'goodsReceived.purchaseOrder',
            ])
            ->firstOrFail();

        return view('purchasing.invoices.pdf', compact('invoice'));
    }

    public function invoiceShow(Request $request, PurchaseInvoice $invoice): View
    {
        $this->ensurePurchaser($request);

        $invoice = PurchaseInvoice::query()
            ->whereKey($invoice->id)
            ->whereHas('purchaserCart', function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id);
            })
            ->with([
                'supplier',
                'purchaserCart.items.product',
                'goodsReceived.items.product',
                'goodsReceived.purchaseOrder',
                'purchaserCart',
            ])
            ->firstOrFail();

        return view('purchasing.invoices.show', [
            'invoice' => $invoice,
            'paymentUpdateRouteName' => 'purchaser.invoices.payment',
            'billPdfRouteName' => 'purchaser.invoices.pdf',
            'backRouteName' => 'purchaser.finance',
            'backRouteParameters' => ['date' => $invoice->purchaserCart?->business_date?->format('Y-m-d')],
            'financeAudience' => 'purchaser',
        ]);
    }

    public function mergeDraftCarts(Request $request, PurchaserCart $cart): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = $this->ownedCart($request, $cart, ['draft']);
        $mergeGroup = $this->mergeGroupDraftCarts($cart)->values();

        if ($mergeGroup->count() <= 1) {
            return redirect()
                ->route('purchaser.vendors', ['date' => $cart->business_date->format('Y-m-d')])
                ->with('error', 'No other draft carts are available to merge.');
        }

        /** @var PurchaserCart $targetCart */
        $targetCart = $mergeGroup->sortByDesc('updated_at')->first();

        foreach ($mergeGroup as $sourceCart) {
            if ($sourceCart->is($targetCart)) {
                continue;
            }

            $this->mergeDraftCartIntoTarget($sourceCart, $targetCart);
            $targetCart = $targetCart->fresh(['supplier', 'items.product.category', 'goodsReceived']);
        }

        return redirect()
            ->route('purchaser.vendors', ['date' => $targetCart->business_date->format('Y-m-d')])
            ->with('success', 'Draft carts merged into one cart.');
    }

    public function bulkStoreCart(Request $request): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $validated = $request->validate([
            'business_date' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->businessDayService->isSelectableDate((string) $value)) {
                        $fail('The selected business date is not available for purchaser flow.');
                    }
                },
            ],
            'product_ids' => ['required', 'array'],
            'product_ids.*' => ['required', 'exists:products,id'],
            'cart_id' => ['nullable', 'integer'],
            'purchase_grade' => ['sometimes', 'string', 'in:A,B'],
            'submission_key' => ['nullable', 'string', 'max:80'],
            'items' => ['required', 'array'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price' => ['nullable', 'numeric'],
        ]);

        $selectedItems = collect($validated['items'])
            ->filter(fn (array $item): bool => (float) ($item['quantity'] ?? 0) > 0);

        if ($selectedItems->isEmpty()) {
            return back()
                ->withInput()
                ->with('error', 'Enter quantity for at least one product before adding to cart.');
        }

        $missingPriceProductId = $selectedItems
            ->filter(fn (array $item): bool => ($validated['purchase_grade'] ?? 'A') === 'A' && (float) ($item['unit_price'] ?? 0) <= 0)
            ->keys()
            ->first();

        if ($missingPriceProductId !== null) {
            return back()
                ->withInput()
                ->withErrors(["items.{$missingPriceProductId}.unit_price" => 'Enter a price greater than zero for selected products.']);
        }

        $date = Carbon::parse($validated['business_date']);
        $purchaseGrade = (string) ($validated['purchase_grade'] ?? 'A');
        $destinationShopId = null;
        $user = $request->user();
        $cartId = filled($validated['cart_id'] ?? null) ? (int) $validated['cart_id'] : null;
        $submissionKey = trim((string) ($validated['submission_key'] ?? ''));
        $sessionSubmissionKey = $submissionKey !== ''
            ? 'purchaser.bulk_store.processed.'.$user->id.'.'.$submissionKey
            : '';

        if ($sessionSubmissionKey !== '' && $request->session()->has($sessionSubmissionKey)) {
            return redirect()
                ->route('purchaser.vendors', ['date' => $date->format('Y-m-d')])
                ->with('success', 'This bulk purchase was already added to cart.');
        }

        $cart = $cartId
            ? PurchaserCart::query()
                ->whereKey($cartId)
                ->where('user_id', $user->id)
                ->whereDate('business_date', $date)
                ->where('status', 'draft')
                ->where('purchase_grade', $purchaseGrade)
                ->where('destination_shop_id', $destinationShopId)
                ->firstOrFail()
            : PurchaserCart::query()
                ->where('user_id', $user->id)
                ->whereDate('business_date', $date)
                ->whereNull('supplier_id')
                ->where('status', 'draft')
                ->where('purchase_grade', $purchaseGrade)
                ->where('destination_shop_id', $destinationShopId)
                ->first();

        if (! $cart) {
            $cart = PurchaserCart::query()->create([
                'user_id' => $user->id,
                'business_date' => $date,
                'cart_number' => PurchaserCart::generateCartNumber($date),
                'status' => 'draft',
                'purchase_grade' => $purchaseGrade,
                'destination_shop_id' => $destinationShopId,
                'purchase_source' => $purchaseGrade === 'B' ? 'green_leaf_direct_purchase' : 'shop_order',
            ]);
        } elseif ($purchaseGrade === 'B' && $cart->purchase_source !== 'green_leaf_direct_purchase') {
            $cart->update(['purchase_source' => 'green_leaf_direct_purchase']);
        }

        $addedCount = 0;
        $hasRegularPurchase = false;
        $hasExtraPurchase = false;
        foreach ($validated['product_ids'] as $productId) {
            $productId = (int) $productId;
            $itemData = $selectedItems->get((string) $productId) ?? $selectedItems->get($productId);

            if (! is_array($itemData)) {
                continue;
            }

            $remainingApproved = $this->remainingApprovedQuantityForProduct($date, $productId, (int) $cart->id, $purchaseGrade);
            $quantity = (float) $itemData['quantity'];
            $unitPrice = $this->purchaseGradePriceResolver->resolve($productId, $date->toDateString(), $purchaseGrade, (float) $itemData['unit_price']);

            $existingItem = $cart->items()->where('product_id', $productId)->where('grade', $purchaseGrade)->first();
            $newQuantity = $existingItem instanceof PurchaserCartItem
                ? (float) $existingItem->quantity + $quantity
                : $quantity;
            $isExtraPurchase = $newQuantity > $remainingApproved;
            $hasExtraPurchase = $hasExtraPurchase || $isExtraPurchase;
            $hasRegularPurchase = $hasRegularPurchase || ! $isExtraPurchase;

            if ($existingItem instanceof PurchaserCartItem) {
                $existingItem->update([
                    'quantity' => $newQuantity,
                    'unit_price' => $unitPrice,
                    'line_total' => round($newQuantity * $unitPrice, 2),
                    'is_extra_purchase' => $isExtraPurchase,
                ]);
            } else {
                $cart->items()->create([
                    'product_id' => $productId,
                    'grade' => $purchaseGrade,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => round($quantity * $unitPrice, 2),
                    'is_extra_purchase' => $isExtraPurchase,
                ]);
            }

            $addedCount++;
        }

        if ($purchaseGrade === 'B') {
            $cart->update(['purchase_source' => $hasRegularPurchase
                ? ($hasExtraPurchase ? 'mixed' : 'shop_order')
                : 'green_leaf_direct_purchase']);
        }

        $productLabel = $addedCount === 1 ? 'product' : 'products';

        if ($sessionSubmissionKey !== '') {
            $request->session()->put($sessionSubmissionKey, true);
        }

        return redirect()
            ->route('purchaser.vendors', ['date' => $date->format('Y-m-d')])
            ->with('success', "{$addedCount} {$productLabel} added to cart.")
            ->with('cart_success_actions', true)
            ->with('cart_success_date', $date->format('Y-m-d'));
    }

    public function storeCart(Request $request): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $validated = $request->validate([
            'business_date' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->businessDayService->isSelectableDate((string) $value)) {
                        $fail('The selected business date is not available for purchaser flow.');
                    }
                },
            ],
            'purchase_grade' => ['sometimes', 'string', 'in:A,B'],
        ]);

        $date = Carbon::parse($validated['business_date']);
        $purchaseGrade = (string) ($validated['purchase_grade'] ?? 'A');
        $destinationShopId = null;
        $cart = $this->findReusableDraftCart(
            userId: (int) $request->user()->id,
            date: $date,
            supplierId: null,
            purchaseGrade: $purchaseGrade,
            destinationShopId: $destinationShopId,
        ) ?? PurchaserCart::query()->create([
            'user_id' => $request->user()->id,
            'business_date' => $date,
            'cart_number' => PurchaserCart::generateCartNumber($date),
            'status' => 'draft',
            'purchase_grade' => $purchaseGrade,
            'destination_shop_id' => $destinationShopId,
            'purchase_source' => $purchaseGrade === 'B' ? 'green_leaf_direct_purchase' : 'shop_order',
        ]);

        if ($purchaseGrade === 'B' && $cart->purchase_source !== 'green_leaf_direct_purchase') {
            $cart->update(['purchase_source' => 'green_leaf_direct_purchase']);
        }

        return redirect()
            ->route('purchaser.vendors', ['date' => $date->format('Y-m-d')])
            ->with('success', 'Draft cart ready.');
    }

    public function storeCartItem(StorePurchaserCartItemRequest $request): RedirectResponse
    {
        $date = Carbon::parse($request->validated('business_date'));
        $user = $request->user();
        $cartId = $request->integer('cart_id');
        $purchaseGrade = (string) ($request->validated('purchase_grade') ?? 'A');
        $destinationShopId = null;

        $cart = $cartId > 0
            ? PurchaserCart::query()
                ->whereKey($cartId)
                ->where('user_id', $user->id)
                ->where('status', 'draft')
                ->where('purchase_grade', $purchaseGrade)
                ->where('destination_shop_id', $destinationShopId)
                ->with(['items.product', 'goodsReceived'])
                ->firstOrFail()
            : (PurchaserCart::query()
                ->where('user_id', $user->id)
                ->whereDate('business_date', $date)
                ->whereNull('supplier_id')
                ->where('status', 'draft')
                ->where('purchase_grade', $purchaseGrade)
                ->where('destination_shop_id', $destinationShopId)
                ->first()
              ?? PurchaserCart::query()->create([
                  'user_id' => $user->id,
                  'business_date' => $date,
                  'cart_number' => PurchaserCart::generateCartNumber($date),
                  'status' => 'draft',
                  'purchase_grade' => $purchaseGrade,
                  'destination_shop_id' => $destinationShopId,
                  'purchase_source' => $purchaseGrade === 'B' ? 'green_leaf_direct_purchase' : 'shop_order',
              ]));

        $product = Product::query()->with('category')->findOrFail($request->integer('product_id'));
        $quantity = (float) $request->validated('quantity');
        $unitPrice = $this->purchaseGradePriceResolver->resolve(
            (int) $product->id,
            $date->toDateString(),
            $purchaseGrade,
            (float) $request->validated('unit_price'),
        );
        $cartHasItems = $cart->items()->exists();
        $purchaseSource = $this->resolveCartPurchaseSource(
            currentSource: $cartHasItems ? (string) ($cart->purchase_source ?? 'shop_order') : '',
            incomingSource: (string) ($request->validated('purchase_source') ?? ($purchaseGrade === 'B' ? 'green_leaf_direct_purchase' : 'shop_order'))
        );

        if ($purchaseSource !== $cart->purchase_source) {
            $cart->update(['purchase_source' => $purchaseSource]);
        }

        $existingItem = $cart->items()->where('product_id', $product->id)->where('grade', $purchaseGrade)->first();
        $newQuantity = $existingItem instanceof PurchaserCartItem
            ? (float) $existingItem->quantity + $quantity
            : $quantity;

        $remainingApproved = $this->remainingApprovedQuantityForProduct($date, (int) $product->id, (int) $cart->id, $purchaseGrade);
        $isExtraPurchase = $newQuantity > $remainingApproved;

        if ($existingItem instanceof PurchaserCartItem) {
            $existingItem->update([
                'quantity' => $newQuantity,
                'unit_price' => $unitPrice,
                'line_total' => round($newQuantity * $unitPrice, 2),
                'is_extra_purchase' => $isExtraPurchase,
                'notes' => $request->validated('notes'),
            ]);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'grade' => $purchaseGrade,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => round($quantity * $unitPrice, 2),
                'is_extra_purchase' => $isExtraPurchase,
                'notes' => $request->validated('notes'),
            ]);
        }

        return $this->redirectAfterMutation(
            $request->string('return_to')->toString(),
            $date,
            $cart,
            $isExtraPurchase
                ? "{$product->name} added to cart. Over-demand quantity will be flagged as extra purchase."
                : "{$product->name} added to cart."
        );
    }

    public function updateCartItem(Request $request, PurchaserCartItem $item): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = $item->cart()
            ->where('user_id', $request->user()->id)
            ->where('status', 'draft')
            ->with('items')
            ->firstOrFail();

        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $quantity = (float) $validated['quantity'];
        $unitPrice = (float) $validated['unit_price'];
        $remainingApproved = $this->remainingApprovedQuantityForProduct($cart->business_date, (int) $item->product_id, (int) $cart->id);

        $item->update([
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => round($quantity * $unitPrice, 2),
            'is_extra_purchase' => $quantity > $remainingApproved,
            'notes' => $validated['notes'] ?? null,
        ]);

        return $this->redirectAfterMutation(
            $request->string('return_to')->toString(),
            $cart->business_date,
            $cart,
            'Vendor cart item updated.'
        );
    }

    public function updateCartItems(Request $request, PurchaserCart $cart): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = $this->ownedCart($request, $cart, ['draft', 'submitted']);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0.01'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($validated, $cart): void {
            $itemsData = collect($validated['items']);
            foreach ($cart->items as $cartItem) {
                $itemInput = $itemsData->get((string) $cartItem->id);
                if (! $itemInput) {
                    continue;
                }

                $quantity = (float) $itemInput['quantity'];
                $unitPrice = (float) $itemInput['unit_price'];
                $remainingApproved = $this->remainingApprovedQuantityForProduct($cart->business_date, (int) $cartItem->product_id, (int) $cart->id);

                $cartItem->update([
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => round($quantity * $unitPrice, 2),
                    'is_extra_purchase' => $quantity > $remainingApproved,
                    'notes' => $itemInput['notes'] ?? null,
                ]);
            }

            if ($cart->status === 'submitted' && $cart->purchaseInvoice) {
                app(PurchaseInvoiceService::class)->fixCalculationError($cart->purchaseInvoice);
            }
        });

        $message = $cart->status === 'submitted'
            ? 'Processed bill updated successfully. Qty, price, and total were recalculated.'
            : 'Vendor cart updated successfully.';

        if ($request->input('action') === 'process' && $cart->status === 'draft') {
            return redirect()
                ->route('purchaser.bill', ['cart' => $cart, 'date' => $cart->business_date->format('Y-m-d')])
                ->with('success', $message);
        }

        return redirect()
            ->route('purchaser.vendors', ['date' => $cart->business_date->format('Y-m-d')])
            ->with('success', $message);
    }

    public function destroyCartItem(Request $request, PurchaserCartItem $item): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = $item->cart()->where('user_id', $request->user()->id)->where('status', 'draft')->firstOrFail();
        $item->delete();

        if ($cart->items()->count() === 0) {
            $cart->delete();

            $date = $cart->business_date->format('Y-m-d');
            $returnTo = $request->string('return_to')->toString();
            $message = 'Vendor cart removed because it had no products left.';

            return match ($returnTo) {
                'bill', 'cart', 'vendors' => redirect()->route('purchaser.vendors', ['date' => $date])->with('success', $message),
                default => redirect()->route('purchaser.daily', array_filter([
                    'date' => $date,
                    'chip' => $request->input('chip'),
                    'search' => $request->input('search'),
                ]))->with('success', $message),
            };
        }

        return $this->redirectAfterMutation(
            $request->string('return_to')->toString(),
            $cart->business_date,
            $cart,
            'Vendor cart item removed.'
        );
    }

    public function markCartSent(Request $request, PurchaserCart $cart): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = $this->ownedCart($request, $cart, ['draft', 'submitted']);

        $returnTo = $request->input('return_to', 'cart');

        if ($cart->items->isEmpty()) {
            return $this->redirectAfterMutation($returnTo, $cart->business_date, $cart, '')
                ->withErrors(['The selected cart is empty.']);
        }

        $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'vendor_name' => ['nullable', 'string', 'max:255', 'required_without:supplier_id'],
            'vendor_location' => ['nullable', 'string', 'max:255'],
            'vendor_mobile_number' => ['nullable', 'string', 'max:50', 'required_without:supplier_id'],
            'vendor_bank_details' => ['nullable', 'string', 'max:1000'],
            'bank_details' => ['nullable', 'string', 'max:1000'],
            'vendor_type' => ['nullable', 'string', 'max:255'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'preferred_payment_method' => ['nullable', 'string', 'max:100'],
            'share_mode' => ['nullable', 'string', 'in:saved,custom,any'],
            'share_format' => ['nullable', 'string', 'in:total,selection'],
            'show_price' => ['nullable', 'boolean'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($request->string('share_mode')->toString() === 'custom') {
            $digits = preg_replace('/\D+/', '', (string) $request->input('vendor_mobile_number'));

            if ($digits === null || strlen($digits) !== 10) {
                return $this->redirectAfterMutation($request->input('return_to', 'cart'), $cart->business_date, $cart, '')
                    ->withErrors(['Enter a valid 10 digit India mobile number.']);
            }
        }

        $supplier = $this->resolveSubmissionSupplier($request);
        $cart = $this->assignSupplierToCart($cart, $supplier);

        $cart->update([
            'supplier_id' => $supplier->id,
            'whatsapp_sent_at' => now(),
        ]);

        if ($cart->status === 'submitted') {
            if ($cart->purchaseOrder) {
                $cart->purchaseOrder->update(['supplier_id' => $supplier->id]);
            }
            if ($cart->purchaseInvoice) {
                $cart->purchaseInvoice->update(['supplier_id' => $supplier->id]);
            }
        }

        $discountAmount = round((float) $request->input('discount_amount', 0), 2);
        $showPrice = $request->boolean('show_price', false) || $discountAmount > 0;
        $message = $this->buildCartShareText(
            $cart->fresh(['items.product', 'supplier']),
            $showPrice,
            $discountAmount,
            $request->string('share_format')->toString() === 'selection' ? 'selection' : 'total',
        );

        $shareMode = $request->string('share_mode')->toString() ?: 'saved';
        $customMobile = $request->input('vendor_mobile_number');

        if ($shareMode === 'any') {
            $whatsAppUrl = 'https://api.whatsapp.com/send?text='.rawurlencode($message);
        } elseif ($shareMode === 'custom') {
            $digits = preg_replace('/\D+/', '', (string) $customMobile);
            if ($digits !== null && strlen($digits) === 10) {
                $digits = '91'.$digits;
            }
            $whatsAppUrl = $digits ? 'https://api.whatsapp.com/send?phone='.$digits.'&text='.rawurlencode($message) : null;
        } else {
            $whatsAppUrl = $this->buildSupplierWhatsAppUrl($supplier, $message);
        }

        if ($whatsAppUrl === null) {
            return $this->redirectAfterMutation($returnTo, $cart->business_date, $cart, '')
                ->withErrors(['Selected vendor does not have a mobile number for WhatsApp.']);
        }

        return redirect()->away($whatsAppUrl);
    }

    public function updateCartSupplier(Request $request, PurchaserCart $cart): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = PurchaserCart::query()
            ->whereKey($cart->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (! in_array($cart->status, ['draft', 'submitted'], true)) {
            return redirect()
                ->route('purchaser.vendors', ['date' => $cart->business_date?->format('Y-m-d')])
                ->withErrors(['Only draft or submitted carts can be assigned to a supplier.']);
        }

        $cart = $this->ownedCart($request, $cart, ['draft', 'submitted']);

        $returnTo = $request->input('return_to', 'vendors');

        $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'vendor_name' => ['nullable', 'string', 'max:255', 'required_without:supplier_id'],
            'vendor_location' => ['nullable', 'string', 'max:255'],
            'vendor_mobile_number' => ['nullable', 'string', 'max:50', 'required_without:supplier_id'],
            'vendor_bank_details' => ['nullable', 'string', 'max:1000'],
            'bank_details' => ['nullable', 'string', 'max:1000'],
        ]);

        $supplier = $this->resolveSubmissionSupplier($request);
        $cart = $this->assignSupplierToCart($cart, $supplier);

        $cart->update([
            'supplier_id' => $supplier->id,
        ]);

        if ($cart->status === 'submitted') {
            if ($cart->purchaseOrder) {
                $cart->purchaseOrder->update(['supplier_id' => $supplier->id]);
            }
            if ($cart->purchaseInvoice) {
                $cart->purchaseInvoice->update(['supplier_id' => $supplier->id]);
            }
        }

        return $this->redirectAfterMutation($returnTo, $cart->business_date, $cart, 'Vendor updated successfully.');
    }

    public function submitCart(SubmitPurchaserCartRequest $request): RedirectResponse
    {
        $date = Carbon::parse($request->validated('business_date'));
        $user = $request->user();
        $cartId = $request->integer('cart_id');

        /** @var PurchaserCart $cart */
        $cart = PurchaserCart::query()
            ->whereKey($cartId)
            ->where('user_id', $user->id)
            ->with(['items.product', 'supplier', 'purchaseInvoice'])
            ->firstOrFail();

        if ($cart->status === 'submitted' || $cart->purchase_invoice_id || $cart->purchaseInvoice) {
            return $this->resolveSubmitRedirect($request, $date, 'Cart was already submitted.');
        }

        if ($cart->items->isEmpty()) {
            return redirect()
                ->route('purchaser.vendors', ['date' => $date->format('Y-m-d')])
                ->withErrors(['The selected cart is empty.']);
        }

        $supplier = $this->resolveSubmissionSupplier($request);
        $paymentMethod = $request->validated('payment_method');

        if (strcasecmp($paymentMethod, 'Credit') === 0 && ! $supplier->credit_approved) {
            $supplier->update(['credit_approved' => true]);
        }

        $rawBillNumber = trim((string) $request->validated('bill_number'));
        $userProvidedBillNumber = $rawBillNumber !== '' && ! str_starts_with(strtoupper($rawBillNumber), 'PENDING-BILL-');

        if ($userProvidedBillNumber) {
            $normalizedBillNumber = strtolower($rawBillNumber);
            $existingVendorInvoice = PurchaseInvoice::query()
                ->where('supplier_id', $supplier->id)
                ->notCancelled()
                ->whereRaw('LOWER(TRIM(invoice_number)) = ?', [$normalizedBillNumber])
                ->first();

            if ($existingVendorInvoice instanceof PurchaseInvoice) {
                $cart->update([
                    'supplier_id' => $supplier->id,
                    'bill_number' => $existingVendorInvoice->invoice_number,
                    'status' => 'submitted',
                    'purchase_invoice_id' => $existingVendorInvoice->id,
                    'goods_received_id' => $existingVendorInvoice->goods_received_id,
                    'submitted_at' => now(),
                ]);

                $duplicateMsg = sprintf(
                    'Duplicate Vendor Bill: Bill no. "%s" already exists for vendor "%s" (Existing Bill #%s, Amount: ₹%s).',
                    $rawBillNumber,
                    $supplier->name,
                    $existingVendorInvoice->invoice_number,
                    number_format((float) $existingVendorInvoice->amount, 2)
                );

                return $this->resolveSubmitRedirect($request, $date, $duplicateMsg);
            }
        }

        DB::transaction(function () use ($request, $cartId, $user, $date, $supplier, $paymentMethod, $userProvidedBillNumber, $rawBillNumber): void {
            /** @var PurchaserCart $cart */
            $cart = PurchaserCart::query()
                ->whereKey($cartId)
                ->where('user_id', $user->id)
                ->with(['items.product', 'supplier', 'purchaseInvoice'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($cart->status === 'submitted' || $cart->purchase_invoice_id || $cart->purchaseInvoice) {
                return;
            }

            $existingInvoice = PurchaseInvoice::query()->where('purchaser_cart_id', $cart->id)->first();
            if ($existingInvoice instanceof PurchaseInvoice) {
                $cart->update([
                    'status' => 'submitted',
                    'purchase_invoice_id' => $existingInvoice->id,
                ]);

                return;
            }

            if ($userProvidedBillNumber) {
                $normalizedBillNumber = strtolower($rawBillNumber);
                $existingVendorInvoice = PurchaseInvoice::query()
                    ->where('supplier_id', $supplier->id)
                    ->notCancelled()
                    ->whereRaw('LOWER(TRIM(invoice_number)) = ?', [$normalizedBillNumber])
                    ->lockForUpdate()
                    ->first();

                if ($existingVendorInvoice instanceof PurchaseInvoice) {
                    $cart->update([
                        'supplier_id' => $supplier->id,
                        'bill_number' => $existingVendorInvoice->invoice_number,
                        'status' => 'submitted',
                        'purchase_invoice_id' => $existingVendorInvoice->id,
                        'goods_received_id' => $existingVendorInvoice->goods_received_id,
                        'purchase_order_id' => $existingVendorInvoice->goodsReceived?->purchase_order_id,
                    ]);

                    return;
                }
            }

            $cartItemsData = collect($request->input('items', []));
            foreach ($cart->items as $cartItem) {
                $itemInput = $cartItemsData->get((string) $cartItem->id, []);
                $unitPrice = (float) ($itemInput['unit_price'] ?? $cartItem->unit_price ?? 0);

                $cartItem->update([
                    'unit_price' => $unitPrice,
                    'line_total' => round((float) $cartItem->quantity * $unitPrice, 2),
                ]);
            }

            $cart->refresh()->load('items.product');

            $subtotalAmount = 0.0;
            $discountAmount = (float) $request->input('discount_amount', 0);
            $paidAmountInput = (float) $request->input('paid_amount', 0);
            $regularLines = [];
            $addOnLines = [];

            // Pre-fetch approved and already submitted quantities in a single query for N+1 query optimization
            $productIds = $cart->items->pluck('product_id')->unique()->all();

            $approvedQuantities = ShopOrderItem::query()
                ->whereIn('product_id', $productIds)
                ->where('product_grade', $cart->purchase_grade ?? 'A')
                ->whereHas('order', function ($query) use ($date): void {
                    $query->whereDate('business_date', $date)->where('state', 'approved');
                })
                ->groupBy('product_id')
                ->select('product_id', DB::raw('SUM(approved_qty) as total_qty'))
                ->pluck('total_qty', 'product_id')
                ->all();

            $alreadySubmittedQuantities = PurchaserCartItem::query()
                ->whereIn('product_id', $productIds)
                ->where('grade', $cart->purchase_grade ?? 'A')
                ->whereHas('cart', function ($query) use ($date, $cart): void {
                    $query->whereDate('business_date', $date)
                        ->where('status', 'submitted')
                        ->whereKeyNot($cart->id);
                })
                ->groupBy('product_id')
                ->select('product_id', DB::raw('SUM(quantity) as total_qty'))
                ->pluck('total_qty', 'product_id')
                ->all();

            foreach ($cart->items as $cartItem) {
                $unitPrice = (float) $cartItem->unit_price;
                $quantity = (float) $cartItem->quantity;
                $subtotalAmount += round($quantity * $unitPrice, 2);

                $approvedQty = (float) ($approvedQuantities[$cartItem->product_id] ?? 0);
                $submittedQty = (float) ($alreadySubmittedQuantities[$cartItem->product_id] ?? 0);
                $remainingApproved = max(0.0, $approvedQty - $submittedQty);

                $regularQuantity = min($quantity, $remainingApproved);
                $addOnQuantity = max(0, $quantity - $regularQuantity);

                if ($regularQuantity > 0) {
                    $regularLines[] = [
                        'cart_item' => $cartItem,
                        'quantity' => $regularQuantity,
                        'unit_price' => $unitPrice,
                    ];
                }

                if ($addOnQuantity > 0) {
                    $addOnLines[] = [
                        'cart_item' => $cartItem,
                        'quantity' => $addOnQuantity,
                        'unit_price' => $unitPrice,
                    ];
                }
            }

            $regularDocuments = $regularLines === []
                ? null
                : $this->createPurchaseDocumentsFromLines(
                    supplier: $supplier,
                    date: $date,
                    userId: (int) $user->id,
                    lines: $regularLines,
                    isExtra: false,
                    notes: $this->buildPurchaseDocumentNotes($cart, $request->string('notes')->toString()),
                    cartId: (int) $cart->id
                );

            $addOnDocuments = $addOnLines === []
                ? null
                : $this->createPurchaseDocumentsFromLines(
                    supplier: $supplier,
                    date: $date,
                    userId: (int) $user->id,
                    lines: $addOnLines,
                    isExtra: true,
                    notes: $this->buildPurchaseDocumentNotes(
                        $cart,
                        trim(($request->string('notes')->toString() ?: '')."\nAdd-on quantity from purchaser vendor cart.")
                    ),
                    cartId: (int) $cart->id
                );

            $primaryDocuments = $regularDocuments ?? $addOnDocuments;
            $grossSubtotal = max(0, round($subtotalAmount, 2));
            $netInvoiceAmount = max(0, round($subtotalAmount - $discountAmount, 2));
            $paidAmount = min($netInvoiceAmount, round($paidAmountInput, 2));
            $isCreditPurchase = strcasecmp($paymentMethod, 'Credit') === 0;
            if ($isCreditPurchase) {
                $paidAmount = 0.0;
            }
            $paymentStatus = $this->resolvePaymentStatus($paymentMethod, $netInvoiceAmount, $paidAmount);
            $paymentPaidBy = $isCreditPurchase ? 'vendor_credit' : 'purchaser';
            $invoiceStatus = $paymentStatus === 'paid'
                ? InvoiceStatus::Paid
                : InvoiceStatus::Pending;

            $invoice = PurchaseInvoice::query()->create([
                'goods_received_id' => $primaryDocuments['grn']->id,
                'supplier_id' => $supplier->id,
                'purchaser_cart_id' => $cart->id,
                'purchase_source' => $cart->purchase_source ?? 'shop_order',
                'invoice_number' => $request->validated('bill_number') ?: 'PENDING-BILL-'.$cart->cart_number,
                'amount' => $grossSubtotal,
                'discount_amount' => round($discountAmount, 2),
                'status' => $invoiceStatus,
                'payment_method' => $paymentMethod,
                'payment_paid_by' => $paymentPaidBy,
                'payment_status' => $paymentStatus,
                'paid_amount' => $paidAmount,
                'payment_note' => $request->validated('payment_note'),
                'payment_details' => $request->validated('payment_details'),
                'purchaser_submitted_by' => $user->id,
                'purchaser_submitted_at' => now(),
                'notes' => $request->validated('notes'),
            ]);

            $cart->update([
                'supplier_id' => $supplier->id,
                'bill_number' => $request->validated('bill_number'),
                'discount_amount' => round($discountAmount, 2),
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'paid_amount' => $paidAmount,
                'payment_note' => $request->validated('payment_note'),
                'payment_details' => $request->validated('payment_details'),
                'notes' => $request->validated('notes'),
                'status' => 'submitted',
                'purchase_order_id' => $primaryDocuments['purchase_order']->id,
                'goods_received_id' => $primaryDocuments['grn']->id,
                'purchase_invoice_id' => $invoice->id,
                'submitted_at' => now(),
                'bill_received_at' => now(),
                'goods_received_at' => null,
                'payment_made_at' => $paymentStatus === 'paid' ? now() : null,
            ]);

            if ($invoice->isGreenLeafDirectPurchase() && $paidAmount > 0) {
                $this->journalService->recordGreenLeafDirectPurchasePayment(
                    invoice: $invoice->fresh(['purchaserCart', 'supplier']),
                    amount: $paidAmount,
                    userId: (int) $user->id,
                    paymentMode: $paymentMethod,
                );
            } elseif ($paymentPaidBy === 'purchaser' && $paidAmount > 0) {
                $this->journalService->recordPurchaserDailyPurchasePayment(
                    invoice: $invoice->fresh(['purchaserCart', 'supplier']),
                    amount: $paidAmount,
                    userId: (int) $user->id,
                    paymentMode: $paymentMethod,
                );
            }

            $this->vendorPriceService->syncMany(
                $supplier->id,
                ($cart->purchase_grade ?? 'A') === 'A'
                    ? collect($cart->items)->map(fn (PurchaserCartItem $item): array => [
                        'product_id' => (int) $item->product_id,
                        'unit_price' => (float) $item->unit_price,
                    ])->all()
                    : [],
            );

            if ($paymentPaidBy === 'purchaser') {
                PurchaserCredit::create([
                    'purchaser_id' => $user->id,
                    'type' => 'out',
                    'amount' => $invoice->amount,
                    'description' => "Debit for invoice: {$invoice->invoice_number}",
                    'purchase_invoice_id' => $invoice->id,
                    'created_by' => $user->id,
                    'business_date' => $date,
                ]);
            }
        });

        return $this->resolveSubmitRedirect($request, $date, 'Cart submitted successfully.');
    }

    private function resolveSubmitRedirect(Request $request, Carbon $date, string $message): RedirectResponse
    {
        if ($request->string('return_to')->toString() === 'history') {
            return redirect()
                ->route('purchaser.history', array_filter([
                    'date' => $request->string('date', $date->format('Y-m-d'))->toString(),
                    'tab' => $request->string('tab', 'today')->toString(),
                ]))
                ->with('success', $message);
        }

        if ($request->string('return_to')->toString() === 'suppliers') {
            return redirect()
                ->route('purchaser.suppliers', ['date' => $request->string('date', $date->format('Y-m-d'))->toString()])
                ->with('success', $message);
        }

        return redirect()
            ->route('purchaser.vendors', ['date' => $date->format('Y-m-d'), 'tab' => 'pending'])
            ->with('success', $message);
    }

    private function resolveCartPurchaseSource(string $currentSource, string $incomingSource): string
    {
        if ($currentSource === '') {
            return in_array($incomingSource, ['shop_order', 'green_leaf_direct_purchase', 'mixed'], true)
                ? $incomingSource
                : 'shop_order';
        }

        $currentSource = in_array($currentSource, ['shop_order', 'green_leaf_direct_purchase', 'mixed'], true)
            ? $currentSource
            : 'shop_order';
        $incomingSource = in_array($incomingSource, ['shop_order', 'green_leaf_direct_purchase', 'mixed'], true)
            ? $incomingSource
            : 'shop_order';

        if ($currentSource === $incomingSource) {
            return $currentSource;
        }

        if ($currentSource === 'mixed' || $incomingSource === 'mixed') {
            return 'mixed';
        }

        return 'mixed';
    }

    public function updateOperationalStatus(Request $request, PurchaserCart $cart): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $cart = $this->ownedCart($request, $cart, ['submitted']);

        $validated = $request->validate([
            'flag' => ['required', 'string', 'in:goods_received'],
        ]);

        $column = match ($validated['flag']) {
            'goods_received' => 'goods_received_at',
        };

        $cart->update([
            $column => $cart->{$column} ? null : now(),
        ]);

        if ($request->string('return_to')->toString() === 'suppliers') {
            return redirect()
                ->route('purchaser.suppliers', ['date' => $request->string('date', $cart->business_date->format('Y-m-d'))->toString()])
                ->with('success', 'Purchase status updated.');
        }

        return redirect()
            ->route('purchaser.history', ['date' => $cart->business_date->format('Y-m-d')])
            ->with('success', 'Purchase status updated.');
    }

    public function updateInvoicePayment(Request $request, PurchaseInvoice $invoice): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $invoice = PurchaseInvoice::query()
            ->whereKey($invoice->id)
            ->whereHas('purchaserCart', function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id);
            })
            ->with(['supplier', 'purchaserCart'])
            ->firstOrFail();

        if ($invoice->hasCalculationError() && ! $request->user()?->hasRole('admin')) {
            abort(403, 'This bill has a calculation discrepancy and can only be updated by an Admin.');
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'in:Cash,Online,GPay,Credit'],
            'payment_paid_by' => ['nullable', 'string', 'in:purchaser,company,vendor_credit'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'additional_paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_note' => ['nullable', 'string', 'max:1000'],
            'payment_details' => ['nullable', 'string', 'max:1000'],
            'bill_number' => ['nullable', 'string', 'max:255'],
        ]);

        $existingPaidAmount = (float) ($invoice->paid_amount ?? 0);
        $isCredit = strcasecmp($validated['payment_method'], 'Credit') === 0;
        $hasAdditionalPaidAmount = $request->filled('additional_paid_amount') && (float) $validated['additional_paid_amount'] > 0;
        $resolvedPaidAmount = $isCredit && ! $hasAdditionalPaidAmount
            ? (float) ($validated['paid_amount'] ?? 0.0)
            : ($hasAdditionalPaidAmount
                ? round($existingPaidAmount + (float) ($validated['additional_paid_amount'] ?? 0), 2)
                : (float) ($validated['paid_amount'] ?? $existingPaidAmount));

        $updatedInvoice = app(PurchaseInvoiceService::class)->updatePayment($invoice, [
            'payment_method' => $validated['payment_method'],
            'payment_paid_by' => $validated['payment_paid_by'] ?? ($isCredit && $resolvedPaidAmount <= 0 ? 'vendor_credit' : 'purchaser'),
            'discount_amount' => (float) ($validated['discount_amount'] ?? $invoice->discount_amount ?? 0),
            'paid_amount' => $resolvedPaidAmount,
            'payment_note' => $validated['payment_note'] ?? null,
            'payment_details' => $validated['payment_details'] ?? null,
            'bill_number' => $validated['bill_number'] ?? null,
        ]);

        $remainingBalance = max(
            0,
            round(((float) $updatedInvoice->amount - (float) $updatedInvoice->discount_amount) - (float) $updatedInvoice->paid_amount, 2)
        );
        $message = $remainingBalance > 0 || $updatedInvoice->payment_status === 'credit_pending_approval'
            ? 'Payment updated. Remaining balance or credit is still pending.'
            : 'Payment completed successfully.';

        if ($request->string('return_to')->toString() === 'history') {
            return redirect()
                ->route('purchaser.history', array_filter([
                    'date' => $request->string('date', $updatedInvoice->purchaserCart?->business_date?->format('Y-m-d') ?? now()->format('Y-m-d'))->toString(),
                    'tab' => $request->string('tab', 'today')->toString(),
                ]))
                ->with('success', $message);
        }

        if ($request->string('return_to')->toString() === 'vendors') {
            return redirect()
                ->route('purchaser.vendors', ['date' => $request->string('date', $updatedInvoice->purchaserCart?->business_date?->format('Y-m-d') ?? now()->format('Y-m-d'))->toString()])
                ->with('success', $message);
        }

        if ($request->string('return_to')->toString() === 'suppliers') {
            return redirect()
                ->route('purchaser.suppliers', ['date' => $request->string('date', $updatedInvoice->purchaserCart?->business_date?->format('Y-m-d') ?? now()->format('Y-m-d'))->toString()])
                ->with('success', $message);
        }

        if ($request->string('return_to')->toString() === 'supplier_detail' && $request->filled('supplier_id')) {
            $redirectSupplier = $updatedInvoice->supplier;

            if (! $redirectSupplier || (int) $redirectSupplier->id !== (int) $request->integer('supplier_id')) {
                $redirectSupplier = Supplier::query()->find($request->integer('supplier_id'));
            }

            return redirect()
                ->route('purchaser.suppliers.show', [
                    'supplier' => $redirectSupplier ?? (int) $request->integer('supplier_id'),
                    'date' => $request->string('date', $updatedInvoice->purchaserCart?->business_date?->format('Y-m-d') ?? now()->format('Y-m-d'))->toString(),
                ])
                ->with('success', $message);
        }

        return redirect()
            ->route('purchaser.finance', array_filter([
                'date' => $updatedInvoice->purchaserCart?->business_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
                'tab' => $request->string('tab')->toString(),
            ]))
            ->with('success', $message);
    }

    public function showBulkPayment(Request $request, Supplier $supplier): View|RedirectResponse
    {
        $this->ensurePurchaser($request);

        $date = $this->resolveBusinessDate($request);

        if ($date instanceof RedirectResponse) {
            return $date;
        }

        $userId = (int) $request->user()->id;

        // Get all carts for this supplier and purchaser
        $carts = PurchaserCart::query()
            ->where('user_id', $userId)
            ->where('supplier_id', $supplier->id)
            ->with(['purchaseInvoice'])
            ->orderBy('business_date', 'asc')
            ->get();

        // Build pending bills collection from both invoiced and non-invoiced carts
        $pendingBills = $carts
            ->map(function (PurchaserCart $cart): ?array {
                $invoice = $cart->purchaseInvoice;

                if ($invoice) {
                    // Cart has invoice - check if it has any remaining balance
                    $netAmount = max(0, (float) $invoice->amount - (float) $invoice->discount_amount);
                    $remaining = max(0, $netAmount - (float) $invoice->paid_amount);

                    // Skip fully paid invoices
                    if ($invoice->payment_status === 'paid' || $remaining <= 0) {
                        return null;
                    }

                    // Include any invoice with remaining balance (unpaid, partial, or credit)
                    return [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number ?: 'PENDING-'.$cart->cart_number,
                        'cart_number' => $cart->cart_number,
                        'date' => $cart->business_date->format('d M Y'),
                        'amount' => round((float) $invoice->amount, 2),
                        'paid' => round((float) $invoice->paid_amount, 2),
                        'pending' => round($remaining, 2),
                    ];
                } else {
                    // Cart without invoice - skip for bulk payment
                    // They need to be submitted first to create an invoice
                    return null;
                }
            })
            ->filter()
            ->values();

        return view('purchasing.purchaser.suppliers.bulk-payment', [
            'date' => $date->format('Y-m-d'),
            'supplier' => $supplier,
            'pendingBills' => $pendingBills,
        ]);
    }

    public function bulkPayment(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $validated = $request->validate([
            'bill_ids' => ['required', 'array', 'min:1'],
            'bill_ids.*' => ['required', 'integer', 'exists:purchase_invoices,id'],
            'amount_paid' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'in:Cash,Online,GPay'],
            'payment_paid_by' => ['nullable', 'string', 'in:purchaser,company'],
            'discount_allocations' => ['nullable', 'array'],
            'discount_allocations.*' => ['nullable', 'numeric', 'min:0'],
            'payment_note' => ['nullable', 'string', 'max:1000'],
        ]);

        // Verify all bills belong to this supplier and to this purchaser
        $billIds = $validated['bill_ids'];
        $verifiedInvoices = PurchaseInvoice::query()
            ->whereIn('id', $billIds)
            ->where('supplier_id', $supplier->id)
            ->whereHas('purchaserCart', function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id);
            })
            ->count();

        if ($verifiedInvoices !== count($billIds)) {
            return back()->withErrors([
                'bill_ids' => 'Some bills do not belong to this supplier or you do not have access to them.',
            ]);
        }

        $bulkPaymentService = app(BulkPaymentService::class);
        $result = $bulkPaymentService->processBulkPayment($supplier, [
            'bill_ids' => $billIds,
            'amount_paid' => (float) $validated['amount_paid'],
            'payment_method' => $validated['payment_method'],
            'payment_paid_by' => $validated['payment_paid_by'] ?? 'purchaser',
            'discount_allocations' => $validated['discount_allocations'] ?? [],
            'payment_note' => $validated['payment_note'] ?? null,
        ]);

        if (! $result['success']) {
            return back()->withErrors([
                'payment' => $result['message'] ?? 'Unable to process bulk payment.',
            ]);
        }

        $message = "Successfully paid ₹{$result['total_paid']} across {$result['processed']} bill(s).";
        if ($result['total_discount'] > 0) {
            $message .= " Total discount: ₹{$result['total_discount']}.";
        }
        if (($result['remaining_payment'] ?? 0) > 0) {
            $message .= " Remaining unallocated: ₹{$result['remaining_payment']}.";
        }

        return redirect()
            ->route('purchaser.suppliers.show', [
                'supplier' => $supplier,
                'date' => $request->string('date', now()->format('Y-m-d'))->toString(),
            ])
            ->with('success', $message);
    }

    public function storeCorrectionRequest(StorePurchaserCorrectionRequest $request): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $shopOrderItem = ShopOrderItem::query()
            ->with('order')
            ->findOrFail($request->integer('shop_order_item_id'));

        PurchaserCorrectionRequest::query()->create([
            'business_date' => $request->validated('business_date'),
            'shop_order_item_id' => $shopOrderItem->id,
            'current_approved_qty' => (float) $shopOrderItem->approved_qty,
            'proposed_corrected_qty' => (float) $request->validated('proposed_corrected_qty'),
            'purchaser_note' => $request->validated('purchaser_note'),
            'requester_user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        return redirect()
            ->route('purchaser.daily', ['date' => Carbon::parse($request->validated('business_date'))->format('Y-m-d')])
            ->with('success', 'Correction request sent to purchase manager.');
    }

    public function approveCorrectionRequest(Request $request, PurchaserCorrectionRequest $correctionRequest): RedirectResponse
    {
        $this->ensurePurchaseManager($request);

        abort_unless($correctionRequest->status === 'pending', 400, 'This correction request is no longer pending.');

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $correctionRequest, $validated): void {
            $shopOrderItem = $correctionRequest->shopOrderItem()->lockForUpdate()->firstOrFail();

            $shopOrderItem->update([
                'approved_qty' => $correctionRequest->proposed_corrected_qty,
                'notes' => trim(implode("\n", array_filter([
                    $shopOrderItem->notes,
                    'Purchaser correction approved: '.($validated['review_note'] ?? 'No note'),
                ]))),
            ]);

            $correctionRequest->update([
                'status' => 'approved',
                'review_note' => $validated['review_note'] ?? null,
                'reviewer_user_id' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        return redirect()->back()->with('success', 'Correction request approved and approved qty updated.');
    }

    public function rejectCorrectionRequest(Request $request, PurchaserCorrectionRequest $correctionRequest): RedirectResponse
    {
        $this->ensurePurchaseManager($request);

        abort_unless($correctionRequest->status === 'pending', 400, 'This correction request is no longer pending.');

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $correctionRequest->update([
            'status' => 'rejected',
            'review_note' => $validated['review_note'] ?? null,
            'reviewer_user_id' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Correction request rejected.');
    }

    private function draftCartsForDate(int $userId, Carbon $date): Collection
    {
        return PurchaserCart::query()
            ->where('user_id', $userId)
            ->where('business_date', $date->toDateString())
            ->where('status', 'draft')
            ->with(['supplier', 'items.product.category', 'goodsReceived'])
            ->orderByDesc('updated_at')
            ->get();
    }

    private function findReusableDraftCart(int $userId, Carbon $date, ?int $supplierId, string $purchaseGrade = 'A', ?int $destinationShopId = null, ?int $exceptCartId = null): ?PurchaserCart
    {
        return PurchaserCart::query()
            ->where('user_id', $userId)
            ->whereDate('business_date', $date)
            ->where('status', 'draft')
            ->where('purchase_grade', $purchaseGrade)
            ->where('destination_shop_id', $destinationShopId)
            ->when(
                $supplierId !== null,
                fn ($query) => $query->where('supplier_id', $supplierId),
                fn ($query) => $query->whereNull('supplier_id'),
            )
            ->when($exceptCartId !== null, fn ($query) => $query->whereKeyNot($exceptCartId))
            ->with(['supplier', 'items.product.category', 'goodsReceived'])
            ->orderByDesc('updated_at')
            ->first();
    }

    private function assignSupplierToCart(PurchaserCart $cart, Supplier $supplier): PurchaserCart
    {
        if ($cart->status !== 'draft') {
            return $cart;
        }

        $targetCart = $this->findReusableDraftCart(
            userId: (int) $cart->user_id,
            date: $cart->business_date,
            supplierId: (int) $supplier->id,
            purchaseGrade: (string) ($cart->purchase_grade ?? 'A'),
            destinationShopId: $cart->destination_shop_id ? (int) $cart->destination_shop_id : null,
            exceptCartId: (int) $cart->id,
        );

        if (! $targetCart instanceof PurchaserCart) {
            return $cart;
        }

        $targetCart->update(['supplier_id' => $supplier->id]);

        return $this->mergeDraftCartIntoTarget($cart, $targetCart);
    }

    private function mergeDraftCartIntoTarget(PurchaserCart $sourceCart, PurchaserCart $targetCart): PurchaserCart
    {
        if ($sourceCart->is($targetCart)) {
            return $targetCart;
        }

        if (($sourceCart->purchase_grade ?? 'A') !== ($targetCart->purchase_grade ?? 'A')) {
            throw ValidationException::withMessages(['purchase_grade' => 'Grade A and Grade B carts cannot be merged.']);
        }
        if ($sourceCart->destination_shop_id !== $targetCart->destination_shop_id) {
            throw ValidationException::withMessages(['destination_shop_id' => 'Carts for different destination shops cannot be merged.']);
        }

        return DB::transaction(function () use ($sourceCart, $targetCart): PurchaserCart {
            $sourceCart->loadMissing('items.product');
            $targetCart->loadMissing('items.product');

            $productIds = $sourceCart->items->pluck('product_id')->unique()->all();

            $approvedQuantities = ShopOrderItem::query()
                ->whereIn('product_id', $productIds)
                ->whereHas('order', function ($query) use ($sourceCart): void {
                    $query->whereDate('business_date', $sourceCart->business_date)->where('state', 'approved');
                })
                ->groupBy('product_id')
                ->select('product_id', DB::raw('SUM(approved_qty) as total_qty'))
                ->pluck('total_qty', 'product_id')
                ->all();

            $alreadySubmittedQuantities = PurchaserCartItem::query()
                ->whereIn('product_id', $productIds)
                ->whereHas('cart', function ($query) use ($sourceCart, $targetCart): void {
                    $query->whereDate('business_date', $sourceCart->business_date)
                        ->where('status', 'submitted')
                        ->whereKeyNot($targetCart->id);
                })
                ->groupBy('product_id')
                ->select('product_id', DB::raw('SUM(quantity) as total_qty'))
                ->pluck('total_qty', 'product_id')
                ->all();

            foreach ($sourceCart->items as $sourceItem) {
                $targetItem = $targetCart->items()
                    ->where('product_id', $sourceItem->product_id)
                    ->where('grade', $sourceItem->grade ?? $sourceCart->purchase_grade ?? 'A')
                    ->first();
                $mergedQuantity = $sourceItem->quantity + (float) ($targetItem?->quantity ?? 0);

                $approvedQty = (float) ($approvedQuantities[$sourceItem->product_id] ?? 0);
                $submittedQty = (float) ($alreadySubmittedQuantities[$sourceItem->product_id] ?? 0);
                $remainingApproved = max(0.0, $approvedQty - $submittedQty);

                if ($targetItem instanceof PurchaserCartItem) {
                    $unitPrice = (float) ($sourceItem->unit_price > 0 ? $sourceItem->unit_price : $targetItem->unit_price);

                    $targetItem->update([
                        'quantity' => $mergedQuantity,
                        'unit_price' => $unitPrice,
                        'line_total' => round($mergedQuantity * $unitPrice, 2),
                        'is_extra_purchase' => $mergedQuantity > $remainingApproved,
                        'notes' => $targetItem->notes ?: $sourceItem->notes,
                    ]);

                    $sourceItem->delete();

                    continue;
                }

                $sourceItem->update([
                    'purchaser_cart_id' => $targetCart->id,
                    'quantity' => $mergedQuantity,
                    'line_total' => round($mergedQuantity * (float) $sourceItem->unit_price, 2),
                    'is_extra_purchase' => $mergedQuantity > $remainingApproved,
                ]);
            }

            $targetCart->touch();
            $sourceCart->delete();

            return $targetCart->fresh(['supplier', 'items.product.category', 'goodsReceived']);
        });
    }

    private function mergeGroupDraftCarts(PurchaserCart $cart): Collection
    {
        return PurchaserCart::query()
            ->where('user_id', $cart->user_id)
            ->whereDate('business_date', $cart->business_date)
            ->where('status', 'draft')
            ->where('purchase_grade', $cart->purchase_grade ?? 'A')
            ->where('destination_shop_id', $cart->destination_shop_id)
            ->when(
                $cart->supplier_id !== null,
                fn ($query) => $query->where('supplier_id', $cart->supplier_id),
                fn ($query) => $query->whereNull('supplier_id'),
            )
            ->with(['supplier', 'items.product.category', 'goodsReceived'])
            ->orderByDesc('updated_at')
            ->get();
    }

    private function buildDraftMergeSuggestions(Collection $draftOrders): Collection
    {
        return $draftOrders
            ->groupBy(fn (PurchaserCart $cart): string => ($cart->supplier_id !== null ? 'supplier:'.$cart->supplier_id : 'pending').':grade:'.($cart->purchase_grade ?? 'A').':shop:'.($cart->destination_shop_id ?? 'none'))
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->map(function (Collection $group): array {
                /** @var PurchaserCart $targetCart */
                $targetCart = $group->sortByDesc('updated_at')->first();

                return [
                    'target_cart' => $targetCart,
                    'count' => $group->count(),
                    'label' => $targetCart->supplier?->name ?: 'draft carts',
                ];
            })
            ->values();
    }

    private function ownedCart(Request $request, PurchaserCart $cart, array $statuses): PurchaserCart
    {
        return PurchaserCart::query()
            ->whereKey($cart->id)
            ->where('user_id', $request->user()->id)
            ->whereIn('status', $statuses)
            ->with(['supplier', 'items.product.category', 'goodsReceived'])
            ->firstOrFail();
    }

    private function redirectAfterMutation(string $returnTo, Carbon $date, PurchaserCart $cart, string $message): RedirectResponse
    {
        $vendorRouteParameters = array_filter([
            'date' => $date->format('Y-m-d'),
            'tab' => request()->input('tab'),
            'focus_cart' => request()->input('focus_cart'),
        ]);

        return match ($returnTo) {
            'bill' => redirect()->route('purchaser.bill', ['cart' => $cart, 'date' => $date->format('Y-m-d')])->with('success', $message),
            'cart' => redirect()->route('purchaser.vendors', $vendorRouteParameters)->with('success', $message),
            'vendors' => redirect()->route('purchaser.vendors', $vendorRouteParameters)->with('success', $message),
            default => redirect()->route(($cart->purchase_grade ?? 'A') === 'B' ? 'purchaser.b-grade' : 'purchaser.daily', array_filter([
                'date' => $date->format('Y-m-d'),
                'chip' => request()->input('chip'),
                'search' => request()->input('search'),
            ]))->with('success', $message),
        };
    }

    private function buildDailySummary(Carbon $date, array $frequentProductIds, bool $includeDetails = true, string $purchaseGrade = 'A'): Collection
    {
        $dateString = $date->toDateString();
        $authUser = auth()->user();
        $hasCategoryFilter = $authUser && $authUser->hasAssignedCategoryFilter();
        $userId = $authUser ? (int) $authUser->id : null;
        $filters = [
            'details' => $includeDetails,
            'frequent' => $frequentProductIds,
            'assigned_cats' => $hasCategoryFilter ? $authUser->assignedCategoryIds() : null,
        ];

        /** @var array<int, array<string, mixed>> $rawArray */
        $rawArray = $this->readCacheService->remember(
            scopes: ['orders', 'carts', 'products', 'settings'],
            dataset: $includeDetails ? 'daily_summary_detailed' : 'daily_summary_compact',
            ttlSeconds: 45,
            callback: function () use ($date, $dateString, $frequentProductIds, $includeDetails, $purchaseGrade, $hasCategoryFilter, $authUser): array {
                $approvedItems = ShopOrderItem::query()
                    ->where('product_grade', $purchaseGrade)
                    ->whereHas('order', function ($query) use ($dateString): void {
                        $query->where('business_date', $dateString)->where('state', 'approved');
                    })
                    ->with($this->dailySummaryRelations($includeDetails))
                    ->get();

                if ($hasCategoryFilter && $authUser) {
                    $assignedCatIds = $authUser->assignedCategoryIds();
                    $approvedItems = $approvedItems->filter(fn (ShopOrderItem $item): bool => in_array((int) $item->product?->category_id, $assignedCatIds, true));
                }

                $draftCartItems = PurchaserCartItem::query()
                    ->where('grade', $purchaseGrade)
                    ->whereHas('cart', function ($query) use ($dateString, $purchaseGrade): void {
                        $query->where('business_date', $dateString)
                            ->where('status', 'draft')
                            ->where('purchase_grade', $purchaseGrade);
                    })
                    ->with('cart.user')
                    ->get()
                    ->groupBy(fn ($item) => $item->product_id.'_'.$item->cart->business_date->timezone(config('app.timezone'))->format('Y-m-d'));

                $submittedQuantities = PurchaserCartItem::query()
                    ->where('grade', $purchaseGrade)
                    ->whereHas('cart', function ($query) use ($dateString, $purchaseGrade): void {
                        $query->where('business_date', $dateString)
                            ->where('status', 'submitted')
                            ->where('purchase_grade', $purchaseGrade);
                    })
                    ->with('cart')
                    ->get()
                    ->groupBy(fn ($item) => $item->product_id.'_'.$item->cart->business_date->timezone(config('app.timezone'))->format('Y-m-d'))
                    ->map(fn ($group) => (float) $group->sum('quantity'));

                return $approvedItems
                    ->groupBy(fn (ShopOrderItem $item) => $item->product_id.'_'.$item->order->business_date->timezone(config('app.timezone'))->format('Y-m-d'))
                    ->map(function (Collection $items, string $key) use ($draftCartItems, $submittedQuantities, $frequentProductIds, $date, $includeDetails): ?array {
                        [$productId, $itemDateStr] = explode('_', $key);
                        $itemDate = Carbon::parse($itemDateStr);

                        /** @var ShopOrderItem $firstItem */
                        $firstItem = $items->first();
                        $product = $firstItem->product;

                        $productDraftItems = $draftCartItems->get($key) ?? collect();
                        $draftQty = (float) $productDraftItems->sum('quantity');
                        $draftPurchasers = $productDraftItems
                            ->groupBy('cart.user_id')
                            ->map(function ($itemsByPurchaser) use ($product) {
                                $user = $itemsByPurchaser->first()->cart->user;
                                $purchaserQty = (float) $itemsByPurchaser->sum('quantity');
                                $formattedQty = $product->unit === 'kg' ? number_format($purchaserQty, 1) : number_format($purchaserQty, 0);

                                return $user ? "{$user->name} ({$formattedQty} {$product->unit})" : null;
                            })
                            ->filter()
                            ->values()
                            ->all();

                        $boughtQty = (float) ($submittedQuantities->get($key) ?? 0);
                        $totalApprovedQty = (float) $items->sum('approved_qty');
                        $remainingQty = max(0, $totalApprovedQty - $boughtQty);

                        if ($itemDate->lt($date) && $remainingQty <= 0) {
                            return null;
                        }

                        $categoryName = (string) ($product->category?->name ?? '');

                        $summary = [
                            'product_id' => (int) $productId,
                            'product_name' => $product->name,
                            'sku' => $product->sku,
                            'unit' => $product->unit,
                            'category_name' => $categoryName,
                            'is_frequent' => in_array((int) $productId, $frequentProductIds, true),
                            'total_approved_qty' => $totalApprovedQty,
                            'bought_qty' => $boughtQty,
                            'draft_qty' => $draftQty,
                            'draft_purchasers' => $draftPurchasers,
                            'remaining_qty' => $remainingQty,
                            'order_date' => $itemDate->format('Y-m-d'),
                            'search_index' => strtolower(implode(' ', [
                                $product->name,
                                $product->sku,
                                $categoryName,
                            ])),
                        ];

                        if (! $includeDetails) {
                            return $summary;
                        }

                        $summary['orderable_units'] = $this->orderableUnitOptions($product);
                        $summary['quantity_buckets'] = $this->dailySummaryQuantityBuckets($items, $firstItem);
                        $summary['measure_breakdown'] = $this->dailySummaryMeasureBreakdown($items);
                        $summary['shop_details'] = $items->map(fn (ShopOrderItem $item): array => [
                            'shop_order_item_id' => $item->id,
                            'shop_name' => $item->order->demandSourceLabel(),
                            'is_direct_purchase' => $item->order->isAdminDirectPurchase(),
                            'approved_qty' => (float) $item->approved_qty,
                            'unit' => $item->unit,
                            'requested_measure_label' => $item->requestedMeasureBreakdownLabel(),
                            'order_number' => $item->order->order_number,
                            'notes' => $item->notes,
                        ])->sortBy('shop_name')->values()->all();

                        return $summary;
                    })
                    ->filter()
                    ->sortBy(fn (array $item): string => Product::sortableSku((string) $item['sku']).'_'.$item['order_date'])
                    ->values()
                    ->all();
            },
            userId: $userId,
            businessDate: $date,
            grade: $purchaseGrade,
            filters: $filters,
        );

        return collect($rawArray)->map(function (array $item): array {
            if (isset($item['order_date']) && is_string($item['order_date'])) {
                $item['order_date'] = Carbon::parse($item['order_date']);
            }

            return $item;
        });
    }

    private function buildGradeBPurchaseCatalog(Carbon $date, int $userId): Collection
    {
        $dateString = $date->toDateString();
        $authUser = auth()->user();
        $hasCategoryFilter = $authUser && $authUser->hasAssignedCategoryFilter();
        $filters = [
            'assigned_cats' => $hasCategoryFilter ? $authUser->assignedCategoryIds() : null,
        ];

        /** @var array<int, array<string, mixed>> $rawArray */
        $rawArray = $this->readCacheService->remember(
            scopes: ['orders', 'carts', 'products', 'settings'],
            dataset: 'b_grade_catalog',
            ttlSeconds: 45,
            callback: function () use ($dateString, $userId, $authUser, $hasCategoryFilter): array {
                $products = Product::query()
                    ->active()
                    ->where('show_in_purchaser_order', true)
                    ->with('category:id,name')
                    ->ordered()
                    ->get(['id', 'name', 'sku', 'unit', 'category_id']);

                if ($hasCategoryFilter && $authUser) {
                    $products = $products->whereIn('category_id', $authUser->assignedCategoryIds())->values();
                }

                $approvedGradeBQuantities = ShopOrderItem::query()
                    ->where('product_grade', 'B')
                    ->whereHas('order', fn ($query) => $query
                        ->whereDate('business_date', $dateString)
                        ->where('state', 'approved'))
                    ->selectRaw('product_id, SUM(approved_qty) as approved_quantity')
                    ->groupBy('product_id')
                    ->pluck('approved_quantity', 'product_id');

                $cartItems = PurchaserCartItem::query()
                    ->where('grade', 'B')
                    ->whereHas('cart', fn ($query) => $query
                        ->whereDate('business_date', $dateString)
                        ->where('purchase_grade', 'B')
                        ->whereIn('status', ['draft', 'submitted']))
                    ->with('cart:id,user_id,business_date,status')
                    ->get()
                    ->groupBy('product_id');

                return $products->map(function (Product $product) use ($approvedGradeBQuantities, $cartItems, $userId, $dateString): array {
                    $items = $cartItems->get($product->id, collect());
                    $draftQuantity = (float) $items->filter(fn (PurchaserCartItem $item): bool => $item->cart?->status === 'draft' && (int) $item->cart->user_id === $userId)->sum('quantity');
                    $submittedQuantity = (float) $items->filter(fn (PurchaserCartItem $item): bool => $item->cart?->status === 'submitted')->sum('quantity');
                    $approvedQuantity = (float) ($approvedGradeBQuantities->get($product->id) ?? 0);
                    $hasGradeBOrder = $approvedQuantity > 0;

                    return [
                        'product_id' => (int) $product->id,
                        'product_name' => $product->name,
                        'sku' => $product->sku,
                        'unit' => $product->unit,
                        'category_name' => $product->category?->name ?? '',
                        'is_frequent' => false,
                        'is_direct_catalog' => ! $hasGradeBOrder,
                        'is_grade_b_catalog' => true,
                        'has_grade_b_order' => $hasGradeBOrder,
                        'total_approved_qty' => $approvedQuantity,
                        'bought_qty' => $submittedQuantity,
                        'draft_qty' => $draftQuantity,
                        'draft_purchasers' => [],
                        'remaining_qty' => max(0, $approvedQuantity - $submittedQuantity),
                        'order_date' => $dateString,
                        'search_index' => strtolower(implode(' ', [$product->name, $product->sku, $product->category?->name])),
                        'shop_details' => [],
                        'quantity_buckets' => [],
                        'measure_breakdown' => [],
                    ];
                })
                    ->sortBy(fn (array $item): string => Product::sortableSku((string) $item['sku']))
                    ->values()
                    ->all();
            },
            userId: $userId,
            businessDate: $date,
            grade: 'B',
            filters: $filters,
        );

        return collect($rawArray)->map(function (array $item): array {
            if (isset($item['order_date']) && is_string($item['order_date'])) {
                $item['order_date'] = Carbon::parse($item['order_date']);
            }

            return $item;
        });
    }

    /**
     * @return array<int|string, mixed>
     */
    private function dailySummaryRelations(bool $includeDetails): array
    {
        $relations = ['product.category', 'order'];

        if ($includeDetails) {
            $relations['product.orderUnits'] = fn ($query) => $query->where('is_orderable', true);
            $relations[] = 'order.shop';
        }

        return $relations;
    }

    /**
     * @return array<int, array{quantity:float,formatted:string,count:int}>
     */
    private function dailySummaryQuantityBuckets(Collection $items, ShopOrderItem $firstItem): array
    {
        return $items
            ->groupBy(fn (ShopOrderItem $item): string => $this->normalizeBucketKey((float) $item->approved_qty))
            ->map(function (Collection $bucketItems) use ($firstItem): array {
                $bucketQuantity = (float) $bucketItems->first()->approved_qty;

                return [
                    'quantity' => $bucketQuantity,
                    'formatted' => $this->formatBucketLabel($bucketQuantity, $firstItem->unit),
                    'count' => $bucketItems->count(),
                ];
            })
            ->sortBy('quantity')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label:string,requested_qty:float,approved_qty:float,count:int}>
     */
    private function dailySummaryMeasureBreakdown(Collection $items): array
    {
        return $items
            ->groupBy(fn (ShopOrderItem $item): string => (string) ($item->requested_unit_label ?: $item->requested_unit ?: $item->unit))
            ->map(function (Collection $measureItems, string $label): array {
                $requestedQty = (float) $measureItems->sum('requested_unit_quantity');
                $approvedQty = (float) $measureItems->sum('approved_qty');

                return [
                    'label' => $label,
                    'requested_qty' => $requestedQty,
                    'approved_qty' => $approvedQty,
                    'count' => $measureItems->count(),
                ];
            })
            ->sortBy('label')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{unit:string,label:string,conversion_to_base:float,is_base:bool}>
     */
    private function orderableUnitOptions(Product $product): array
    {
        $units = $product->relationLoaded('orderUnits')
            ? $product->orderUnits
            : $product->orderUnits()->orderBy('sort_order')->orderBy('id')->get();

        $orderableUnits = $units
            ->filter(fn ($unit): bool => (bool) $unit->is_orderable)
            ->values();

        if ($orderableUnits->isEmpty()) {
            return [[
                'unit' => $product->unit,
                'label' => strtoupper((string) $product->unit),
                'conversion_to_base' => 1.0,
                'is_base' => true,
            ]];
        }

        return $orderableUnits
            ->map(fn ($unit): array => [
                'unit' => (string) $unit->unit,
                'label' => (string) ($unit->label ?: strtoupper((string) $unit->unit)),
                'conversion_to_base' => (float) $unit->conversion_to_base,
                'is_base' => (bool) $unit->is_base,
            ])
            ->all();
    }

    /**
     * @return array<int, array{unit:string,label:string,conversion_to_base:float,is_base:bool}>
     */
    private function allMeasurementUnitOptions(Product $product): array
    {
        $units = $product->relationLoaded('orderUnits')
            ? $product->orderUnits
            : $product->orderUnits()->orderBy('sort_order')->orderBy('id')->get();

        $measurementUnits = $units
            ->filter(fn ($unit): bool => (float) $unit->conversion_to_base > 0)
            ->values();

        if ($measurementUnits->isEmpty()) {
            return [[
                'unit' => $product->unit,
                'label' => strtoupper((string) $product->unit),
                'conversion_to_base' => 1.0,
                'is_base' => true,
            ]];
        }

        return $measurementUnits
            ->map(fn ($unit): array => [
                'unit' => (string) $unit->unit,
                'label' => (string) ($unit->label ?: strtoupper((string) $unit->unit)),
                'conversion_to_base' => (float) $unit->conversion_to_base,
                'is_base' => (bool) $unit->is_base,
            ])
            ->all();
    }

    private function filterProductsForChip(Collection $items, string $selectedChip, string $search, array $frequentProductIds): Collection
    {
        return $items->filter(function ($item) use ($selectedChip, $search, $frequentProductIds): bool {
            $categoryName = is_array($item)
                ? (string) ($item['category_name'] ?? '')
                : (string) ($item->category?->name ?? '');
            $productId = is_array($item) ? (int) ($item['product_id'] ?? 0) : (int) $item->id;
            $searchIndex = is_array($item)
                ? (string) ($item['search_index'] ?? '')
                : strtolower(implode(' ', [$item->name, $item->sku, $categoryName]));

            $matchesChip = match ($selectedChip) {
                'Frequent' => in_array($productId, $frequentProductIds, true),
                'All' => true,
                default => $categoryName === $selectedChip,
            };

            if (! $matchesChip) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            return str_contains($searchIndex, strtolower($search));
        })->values();
    }

    private function quickFiltersForPurchaser(User $user): array
    {
        $userId = (int) $user->id;
        if (isset($this->memoizedQuickFilters[$userId])) {
            return $this->memoizedQuickFilters[$userId];
        }

        if (! $user->hasAssignedCategoryFilter()) {
            return $this->memoizedQuickFilters[$userId] = self::QUICK_FILTERS;
        }

        $assignedCatNames = Category::query()
            ->whereIn('id', $user->assignedCategoryIds())
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return $this->memoizedQuickFilters[$userId] = array_values(array_unique(array_merge(['All', 'Frequent'], $assignedCatNames)));
    }

    private function frequentProductIds(int $userId): array
    {
        if (isset($this->memoizedFrequentProductIds[$userId])) {
            return $this->memoizedFrequentProductIds[$userId];
        }

        $cartItems = PurchaserCartItem::query()
            ->selectRaw('product_id, COUNT(*) as usage_count')
            ->whereHas('cart', function ($query) use ($userId): void {
                $query->where('user_id', $userId)
                    ->whereDate('business_date', '>=', now()->subDays(14)->toDateString());
            })
            ->whereHas('product', function ($query): void {
                $query->active()->where('show_in_purchaser_order', true);
            })
            ->groupBy('product_id')
            ->orderByDesc('usage_count')
            ->limit(12)
            ->pluck('product_id')
            ->map(fn ($productId): int => (int) $productId)
            ->all();

        if ($cartItems !== []) {
            return $this->memoizedFrequentProductIds[$userId] = $cartItems;
        }

        return $this->memoizedFrequentProductIds[$userId] = Product::query()
            ->active()
            ->where('show_in_purchaser_order', true)
            ->whereHas('category', function ($query): void {
                $query->whereIn('name', ['Supply', 'VEG']);
            })
            ->ordered()
            ->limit(12)
            ->pluck('id')
            ->map(fn ($productId): int => (int) $productId)
            ->all();
    }

    private function resolveSubmissionSupplier(Request $request): Supplier
    {
        $supplierId = $request->integer('supplier_id');

        if ($supplierId > 0) {
            return Supplier::query()->findOrFail($supplierId);
        }

        return Supplier::query()->create([
            'name' => $request->string('vendor_name')->toString(),
            'type' => $request->string('vendor_type')->toString() ?: 'Vendor',
            'category' => 'market',
            'is_default_purchase' => false,
            'contact' => (string) $request->input('vendor_mobile_number', ''),
            'location' => $request->input('vendor_location'),
            'mobile_number' => $request->input('vendor_mobile_number'),
            'payment_terms' => $request->input('payment_terms', 'Cash'),
            'preferred_payment_method' => $request->input('preferred_payment_method', $request->string('payment_method')->toString() ?: 'Cash'),
            'bank_details' => $request->input('vendor_bank_details') ?: $request->input('bank_details'),
            'credit_approved' => true,
            'credit_terms' => null,
            'quality_score' => 100,
        ]);
    }

    /**
     * @param  array<int, array{cart_item: PurchaserCartItem, quantity: float, unit_price: float}>  $lines
     * @return array{purchase_order: PurchaseOrder, grn: GoodsReceived}
     */
    private function createPurchaseDocumentsFromLines(Supplier $supplier, Carbon $date, int $userId, array $lines, bool $isExtra, string $notes, ?int $cartId = null): array
    {
        $purchaseGrade = (string) ($lines[0]['cart_item']->grade ?? 'A');
        $destinationShopId = $cartId ? PurchaserCart::query()->whereKey($cartId)->value('destination_shop_id') : null;
        $purchaseOrder = PurchaseOrder::query()->create([
            'supplier_id' => $supplier->id,
            'destination_shop_id' => $destinationShopId,
            'purchaser_cart_id' => $cartId,
            'po_number' => $this->generatePurchaseOrderNumber($date),
            'status' => POStatus::Received,
            'fulfillment_type' => 'warehouse',
            'order_date' => $date,
            'created_by' => $userId,
            'notes' => $notes,
            'purchase_grade' => $purchaseGrade,
        ]);

        $grn = GoodsReceived::query()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'purchaser_cart_id' => $cartId,
            'grn_number' => $this->generateGrnNumber($date),
            'status' => 'pending_approval',
            'receipt_type' => 'normal_purchase',
            'received_by' => $userId,
            'received_at' => $date,
            'notes' => $notes,
            'is_extra' => $isExtra,
            'purchase_grade' => $purchaseGrade,
        ]);

        foreach ($lines as $line) {
            $cartItem = $line['cart_item'];
            $quantity = $line['quantity'];
            $isPerKg = $cartItem->product->unit === 'kg';

            $purchaseOrderItem = $purchaseOrder->items()->create([
                'product_id' => $cartItem->product_id,
                'grade' => $purchaseGrade,
                'purchase_unit' => $cartItem->product->unit,
                'packet_qty' => $isPerKg ? null : $quantity,
                'quantity' => $quantity,
                'unit_price' => $line['unit_price'],
                'price_basis' => $isPerKg ? 'per_kg' : 'per_unit',
            ]);

            $grn->items()->create([
                'purchase_order_item_id' => $purchaseOrderItem->id,
                'product_id' => $cartItem->product_id,
                'grade' => $purchaseGrade,
                'received_qty' => $quantity,
                'variance' => 0,
            ]);
        }

        return [
            'purchase_order' => $purchaseOrder,
            'grn' => $grn,
        ];
    }

    private function remainingApprovedQuantityForProduct(Carbon $date, int $productId, int $currentCartId, string $grade = 'A'): float
    {
        $approvedQuantity = (float) ShopOrderItem::query()
            ->where('product_id', $productId)
            ->where('product_grade', $grade)
            ->whereHas('order', function ($query) use ($date): void {
                $query->whereDate('business_date', $date)->where('state', 'approved');
            })
            ->sum('approved_qty');

        $alreadySubmittedQuantity = (float) PurchaserCartItem::query()
            ->where('product_id', $productId)
            ->where('grade', $grade)
            ->whereHas('cart', function ($query) use ($date, $currentCartId): void {
                $query->whereDate('business_date', $date)
                    ->where('status', 'submitted')
                    ->whereKeyNot($currentCartId);
            })
            ->sum('quantity');

        return max(0, $approvedQuantity - $alreadySubmittedQuantity);
    }

    private function isAdminUserAccess(?Request $request = null): bool
    {
        $req = $request ?? request();
        if (! $req || ! $req->hasSession()) {
            return false;
        }

        return $req->session()->has('admin_impersonator_id')
            || ($req->user() && $req->user()->hasRole('admin'));
    }

    private function resolveBusinessDate(Request $request): Carbon|RedirectResponse
    {
        $operationalDate = $this->businessDayService->operationalDate();
        $dateInput = $request->input('date');

        if ($dateInput) {
            $date = Carbon::parse($dateInput)->startOfDay();

            if (! $this->isAdminUserAccess($request)) {
                $routeName = $request->route()?->getName();
                if (in_array($routeName, ['purchaser.daily', 'purchaser.b-grade', 'purchaser.vendors', 'purchaser.bulk-buy', 'purchaser.bulk-buy.details'], true)) {
                    if (! $date->isSameDay($operationalDate)) {
                        $fallbackDate = $operationalDate->format('Y-m-d');

                        return redirect()
                            ->route($routeName, array_filter([
                                'date' => $fallbackDate,
                                'chip' => $request->input('chip'),
                                'search' => $request->input('search'),
                                'tab' => $request->input('tab'),
                            ]))
                            ->with('error', 'Only the active business day order can be viewed/processed.');
                    }
                }

                if (! $this->businessDayService->isSelectableDate($date)) {
                    $fallbackDate = $operationalDate->format('Y-m-d');

                    return redirect()
                        ->route($routeName === 'purchaser.b-grade' ? 'purchaser.b-grade' : 'purchaser.daily', [
                            'date' => $fallbackDate,
                            'chip' => $request->input('chip'),
                            'search' => $request->input('search'),
                        ])
                        ->with('error', 'That purchase date is not available right now. Showing the active business day instead.');
                }
            }

            return $date;
        }

        return $operationalDate;
    }

    private function buildPurchaseDocumentNotes(PurchaserCart $cart, string $notes): string
    {
        return trim(implode("\n", array_filter([
            trim($notes) !== '' ? trim($notes) : 'Generated from purchaser vendor cart.',
            'Cart: '.$cart->cart_number,
        ])));
    }

    /**
     * @return array{show: bool, same_day: bool, overdue_count: int, vendor_missing_count: int, bill_pending_count: int, warehouse_pending_count: int, pending_total_count: int, overdue_carts: Collection<int, PurchaserCart>, operational_date: string}
     */
    private function filterOverdueCartsForPurchaser(Collection $overdueCarts, array $batchState): Collection
    {
        return $overdueCarts->filter(function (PurchaserCart $cart) use ($batchState): bool {
            if ($cart->status === 'draft') {
                return true;
            }
            $isConfirmed = $this->isWarehouseConfirmed($batchState[(int) $cart->id] ?? []);
            if (! $isConfirmed) {
                return false; // Skip warehouse receipt pending
            }

            return $this->cartHasPaymentPending($cart);
        });
    }

    /**
     * @param  Collection<int, PurchaserCart>|null  $overdueCarts
     * @param  array<int, array{warehouse_confirmed: bool, total_batches: int, confirmed_batches: int}>|null  $overdueBatchState
     * @return array{show: bool, same_day: bool, overdue_count: int, credit_overdue_count: int, payment_overdue_count: int, vendor_missing_count: int, bill_pending_count: int, warehouse_pending_count: int, pending_total_count: int, overdue_carts: Collection<int, PurchaserCart>, operational_date: string}
     */
    private function buildDeadlineAlert(
        int $userId,
        Carbon $selectedDate,
        ?Collection $overdueCarts = null,
        ?array $overdueBatchState = null,
    ): array {
        $operationalDate = $this->businessDayService->operationalDate();
        $calendarDate = $this->businessDayService->currentCalendarDate();
        $warningOpen = $this->businessDayService->isWarningWindowOpen($calendarDate) && $selectedDate->isSameDay($calendarDate);

        $sameDayCarts = $warningOpen
            ? PurchaserCart::query()
                ->where('user_id', $userId)
                ->whereDate('business_date', $calendarDate)
                ->with(['supplier', 'items.product.category', 'goodsReceived', 'purchaseInvoice'])
                ->orderByDesc('updated_at')
                ->get()
            : collect();

        $overdueCarts = $overdueCarts ?? $this->overdueCartsForUser($userId);
        $overdueBatchState = $overdueBatchState ?? $this->relatedBatchStateForCarts($overdueCarts);
        $overdueCarts = $this->filterOverdueCartsForPurchaser($overdueCarts, $overdueBatchState);
        $creditOverdueCount = $overdueCarts
            ->filter(fn (PurchaserCart $cart): bool => ($cart->purchaseInvoice?->payment_method ?: $cart->payment_method) === 'Credit')
            ->count();
        $paymentOverdueCount = $overdueCarts->count() - $creditOverdueCount;

        $vendorMissingCount = $sameDayCarts
            ->filter(fn (PurchaserCart $cart): bool => $cart->status === 'draft' && $cart->items->isNotEmpty() && $cart->supplier_id === null)
            ->count();
        $billPendingCount = $sameDayCarts
            ->filter(fn (PurchaserCart $cart): bool => $cart->status === 'draft' && $cart->items->isNotEmpty() && $cart->supplier_id !== null)
            ->count();

        return [
            'show' => $overdueCarts->isNotEmpty() || ($warningOpen && ($vendorMissingCount > 0 || $billPendingCount > 0)),
            'same_day' => $warningOpen,
            'overdue_count' => $overdueCarts->count(),
            'credit_overdue_count' => $creditOverdueCount,
            'payment_overdue_count' => $paymentOverdueCount,
            'vendor_missing_count' => $vendorMissingCount,
            'bill_pending_count' => $billPendingCount,
            'warehouse_pending_count' => 0,
            'pending_total_count' => $overdueCarts->count() + $vendorMissingCount + $billPendingCount,
            'overdue_carts' => $overdueCarts,
            'operational_date' => $operationalDate->format('Y-m-d'),
        ];
    }

    /**
     * @param  Collection<int, PurchaserCart>  $carts
     * @return array<int, array{warehouse_confirmed: bool, total_batches: int, confirmed_batches: int}>
     */
    private function relatedBatchStateForCarts(Collection $carts): array
    {
        return $this->purchaserCartBatchStateResolver->statesForCarts($carts);
    }

    /**
     * @param  array{warehouse_confirmed?: bool}  $batchState
     */
    private function isWarehouseConfirmed(array $batchState): bool
    {
        return (bool) ($batchState['warehouse_confirmed'] ?? false);
    }

    /**
     * @param  Collection<int, PurchaserCart>  $carts
     * @return array<int, array{label: string, tone: string}>
     */
    private function statusBadgesForCarts(Collection $carts, array $relatedBatchState): array
    {
        $operationalDate = $this->businessDayService->operationalDate();

        return $carts->mapWithKeys(function (PurchaserCart $cart) use ($relatedBatchState, $operationalDate): array {
            $batchState = $relatedBatchState[(int) $cart->id] ?? [];
            $isOverdue = $cart->business_date->lt($operationalDate) && $this->isCartOperationallyUnresolved($cart, $batchState);

            if ($isOverdue) {
                return [(int) $cart->id => ['label' => 'Overdue', 'tone' => 'bg-rose-100 text-rose-700']];
            }

            if ($cart->status === 'draft') {
                $label = $cart->supplier_id === null ? 'Vendor Pending' : 'Bill Pending';

                return [(int) $cart->id => ['label' => $label, 'tone' => 'bg-amber-100 text-amber-700']];
            }

            if (! $this->cartHasPaymentPending($cart)) {
                return [(int) $cart->id => ['label' => 'Completed', 'tone' => 'bg-emerald-100 text-emerald-700']];
            }

            if (! $this->isWarehouseConfirmed($batchState)) {
                return [(int) $cart->id => ['label' => 'Processing', 'tone' => 'bg-teal-100 text-teal-700']];
            }

            return [(int) $cart->id => ['label' => 'Payment Pending', 'tone' => 'bg-amber-100 text-amber-700']];
        })->all();
    }

    /**
     * @param  Collection<int, PurchaserCart>  $carts
     * @return array<int, string>
     */
    private function relatedReceiptNotesForCarts(Collection $carts): array
    {
        return $carts->mapWithKeys(function (PurchaserCart $cart): array {
            $notes = $this->relatedGoodsReceiptsForCart($cart)
                ->pluck('notes')
                ->filter(fn (?string $note): bool => filled($note))
                ->unique()
                ->implode("\n");

            return [(int) $cart->id => $notes];
        })->all();
    }

    /**
     * @return Collection<int, GoodsReceived>
     */
    private function relatedGoodsReceiptsForCart(PurchaserCart $cart): Collection
    {
        if ($cart->goods_received_id !== null) {
            return GoodsReceived::query()
                ->select(['id', 'grn_number', 'notes', 'received_at'])
                ->whereKey($cart->goods_received_id)
                ->orderByDesc('received_at')
                ->get();
        }

        return GoodsReceived::query()
            ->select(['id', 'grn_number', 'notes', 'received_at'])
            ->where('notes', 'like', '%Cart: '.$cart->cart_number.'%')
            ->orderByDesc('received_at')
            ->get();
    }

    /**
     * @return Collection<int, PurchaserCart>
     */
    private function overdueCartsForUser(int $userId): Collection
    {
        if (isset($this->memoizedOverdueCarts[$userId])) {
            return clone $this->memoizedOverdueCarts[$userId];
        }

        $operationalDate = $this->businessDayService->operationalDate();
        $carts = PurchaserCart::query()
            ->where('user_id', $userId)
            ->whereDate('business_date', '<', $operationalDate)
            ->with(['supplier', 'items.product.category', 'goodsReceived', 'purchaseInvoice'])
            ->orderBy('business_date')
            ->orderByDesc('updated_at')
            ->get();
        $batchState = $this->relatedBatchStateForCarts($carts);

        $unresolved = $carts
            ->filter(fn (PurchaserCart $cart): bool => $this->isCartOperationallyUnresolved($cart, $batchState[(int) $cart->id] ?? []))
            ->values();

        $this->memoizedOverdueCarts[$userId] = $unresolved;

        return clone $unresolved;
    }

    /**
     * @param  array{warehouse_confirmed?: bool}  $batchState
     */
    private function isCartOperationallyUnresolved(PurchaserCart $cart, array $batchState): bool
    {
        if ($cart->status === 'cancelled') {
            return false;
        }

        if ($cart->status === 'draft') {
            return $cart->items->isNotEmpty();
        }

        if (! $this->isWarehouseConfirmed($batchState)) {
            return true;
        }

        return $this->cartHasPaymentPending($cart);
    }

    private function cartHasPaymentPending(PurchaserCart $cart): bool
    {
        if ($cart->purchaseInvoice) {
            $paymentStatus = (string) ($cart->purchaseInvoice->payment_status ?: $cart->payment_status ?: 'unpaid');
            $paymentMethod = (string) ($cart->purchaseInvoice->payment_method ?: $cart->payment_method ?: 'Cash');
            $remainingBalance = $this->invoiceRemainingBalance($cart->purchaseInvoice);

            if (strcasecmp($paymentMethod, 'Credit') === 0) {
                return $paymentStatus !== 'paid' || $remainingBalance > 0;
            }

            return $remainingBalance > 0;
        }

        $paymentStatus = (string) ($cart->payment_status ?: 'unpaid');

        return in_array($paymentStatus, ['unpaid', 'partial', 'credit_pending_approval'], true);
    }

    /**
     * @param  array{warehouse_confirmed?: bool}  $batchState
     * @return array{label:string,tone:string,unresolved:bool,payment_pending:bool}
     */
    private function cartOperationalState(PurchaserCart $cart, array $batchState): array
    {
        if ($cart->status === 'cancelled') {
            return [
                'label' => 'Cancelled',
                'tone' => 'bg-rose-100 text-rose-700',
                'unresolved' => false,
                'payment_pending' => false,
            ];
        }

        if ($cart->status === 'draft') {
            return [
                'label' => $cart->supplier_id === null ? 'Vendor Pending' : 'Bill Pending',
                'tone' => 'bg-amber-100 text-amber-700',
                'unresolved' => $cart->items->isNotEmpty(),
                'payment_pending' => false,
            ];
        }

        if (! $this->isWarehouseConfirmed($batchState)) {
            return [
                'label' => 'Receipt Pending',
                'tone' => 'bg-teal-100 text-teal-700',
                'unresolved' => true,
                'payment_pending' => false,
            ];
        }

        if ($this->cartHasPaymentPending($cart)) {
            return [
                'label' => 'Payment Pending',
                'tone' => 'bg-amber-100 text-amber-700',
                'unresolved' => true,
                'payment_pending' => true,
            ];
        }

        return [
            'label' => 'Completed',
            'tone' => 'bg-emerald-100 text-emerald-700',
            'unresolved' => false,
            'payment_pending' => false,
        ];
    }

    /**
     * @param  Collection<int, PurchaserCart>  $sameDayAssignedDrafts
     * @param  Collection<int, PurchaserCart>  $overdueCarts
     * @param  array<int, array{warehouse_confirmed: bool, total_batches: int, confirmed_batches: int}>  $overdueBatchState
     */
    private function supplierPendingHubIssueCount(
        Supplier $supplier,
        Collection $sameDayAssignedDrafts,
        Collection $overdueCarts,
        array $overdueBatchState,
        string $selectedTab,
    ): int {
        $relevantInvoices = $this->linkedInvoicesForSupplier($supplier);
        $hasCreditBalance = $relevantInvoices->contains(fn (PurchaseInvoice $invoice): bool => $this->invoiceRemainingBalance($invoice) > 0);

        if ($selectedTab === 'credit') {
            $creditInvoices = $relevantInvoices->filter(function (PurchaseInvoice $invoice): bool {
                $method = $invoice->payment_method ?: $invoice->purchaserCart?->payment_method;

                return (strcasecmp((string) $method, 'Credit') === 0 || $invoice->payment_status === 'credit_pending_approval')
                    && $this->invoiceRemainingBalance($invoice) > 0;
            });

            $creditCarts = $supplier->purchaserCarts->filter(function (PurchaserCart $cart): bool {
                $method = $cart->purchaseInvoice?->payment_method ?: $cart->payment_method;

                return strcasecmp((string) $method, 'Credit') === 0
                    && ($cart->purchaseInvoice === null || $this->invoiceRemainingBalance($cart->purchaseInvoice) > 0);
            });

            $count = $creditInvoices->count() + $creditCarts->count();

            if ($count === 0 && $hasCreditBalance) {
                return 1;
            }

            return $count;
        }

        $sameDayCount = $sameDayAssignedDrafts
            ->filter(fn (PurchaserCart $cart): bool => (int) $cart->supplier_id === (int) $supplier->id)
            ->count();

        $pendingInvoices = $relevantInvoices->filter(fn (PurchaseInvoice $invoice): bool => $this->invoiceRemainingBalance($invoice) > 0);

        $overdueCount = $overdueCarts
            ->filter(fn (PurchaserCart $cart): bool => (int) $cart->supplier_id === (int) $supplier->id)
            ->count();

        return $sameDayCount + $pendingInvoices->count() + $overdueCount;
    }

    /**
     * @param  Collection<int, PurchaserCart>  $sameDayAssignedDrafts
     * @param  Collection<int, PurchaserCart>  $overdueCarts
     * @param  array<int, array{warehouse_confirmed: bool, total_batches: int, confirmed_batches: int}>  $overdueBatchState
     * @return array{count:int,label:string,tone:string,paid:bool}
     */
    private function supplierPendingHubIssueSummary(
        Supplier $supplier,
        Collection $sameDayAssignedDrafts,
        Collection $overdueCarts,
        array $overdueBatchState,
        string $selectedTab,
    ): array {
        if ($selectedTab === 'credit') {
            $creditCart = $overdueCarts
                ->first(function (PurchaserCart $cart) use ($supplier, $overdueBatchState): bool {
                    if ((int) $cart->supplier_id !== (int) $supplier->id) {
                        return false;
                    }

                    if (! $this->isWarehouseConfirmed($overdueBatchState[(int) $cart->id] ?? [])) {
                        return false;
                    }

                    if (! $this->cartHasPaymentPending($cart)) {
                        return false;
                    }

                    return ($cart->purchaseInvoice?->payment_method ?: $cart->payment_method) === 'Credit';
                });

            return [
                'count' => $this->supplierPendingHubIssueCount($supplier, $sameDayAssignedDrafts, $overdueCarts, $overdueBatchState, $selectedTab),
                'label' => $creditCart ? 'Credit Pending' : 'No issue',
                'tone' => $creditCart ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600',
                'paid' => false,
            ];
        }

        $sameDayDraft = $sameDayAssignedDrafts->first(fn (PurchaserCart $cart): bool => (int) $cart->supplier_id === (int) $supplier->id);
        if ($sameDayDraft) {
            return [
                'count' => $this->supplierPendingHubIssueCount($supplier, $sameDayAssignedDrafts, $overdueCarts, $overdueBatchState, $selectedTab),
                'label' => 'Bill Pending',
                'tone' => 'bg-amber-100 text-amber-700',
                'paid' => false,
            ];
        }

        $overdueDraft = $overdueCarts
            ->first(fn (PurchaserCart $cart): bool => (int) $cart->supplier_id === (int) $supplier->id && $cart->status === 'draft');
        if ($overdueDraft) {
            return [
                'count' => $this->supplierPendingHubIssueCount($supplier, $sameDayAssignedDrafts, $overdueCarts, $overdueBatchState, $selectedTab),
                'label' => 'Overdue Bill Pending',
                'tone' => 'bg-rose-100 text-rose-700',
                'paid' => false,
            ];
        }

        $receiptPendingCart = $overdueCarts
            ->first(function (PurchaserCart $cart) use ($supplier, $overdueBatchState): bool {
                if ((int) $cart->supplier_id !== (int) $supplier->id || $cart->status === 'draft') {
                    return false;
                }

                return ! $this->isWarehouseConfirmed($overdueBatchState[(int) $cart->id] ?? []);
            });
        if ($receiptPendingCart) {
            return [
                'count' => $this->supplierPendingHubIssueCount($supplier, $sameDayAssignedDrafts, $overdueCarts, $overdueBatchState, $selectedTab),
                'label' => 'Receipt Pending',
                'tone' => 'bg-teal-100 text-teal-700',
                'paid' => (string) ($receiptPendingCart->purchaseInvoice?->payment_status ?: $receiptPendingCart->payment_status ?: 'unpaid') === 'paid',
            ];
        }

        $paymentPendingCart = $overdueCarts
            ->first(function (PurchaserCart $cart) use ($supplier, $overdueBatchState): bool {
                if ((int) $cart->supplier_id !== (int) $supplier->id || $cart->status === 'draft') {
                    return false;
                }

                if (! $this->isWarehouseConfirmed($overdueBatchState[(int) $cart->id] ?? [])) {
                    return false;
                }

                $paymentMethod = $cart->purchaseInvoice?->payment_method ?: $cart->payment_method;

                return $paymentMethod !== 'Credit' && $this->cartHasPaymentPending($cart);
            });

        return [
            'count' => $this->supplierPendingHubIssueCount($supplier, $sameDayAssignedDrafts, $overdueCarts, $overdueBatchState, $selectedTab),
            'label' => $paymentPendingCart ? 'Payment Pending' : 'No issue',
            'tone' => $paymentPendingCart ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600',
            'paid' => false,
        ];
    }

    private function buildReceiptDiscrepancySummary(?GoodsReceived $goodsReceived): ?string
    {
        if (! $goodsReceived) {
            return null;
        }

        $lines = $goodsReceived->items
            ->filter(fn ($item): bool => abs((float) $item->variance) > 0.0001)
            ->map(function ($item): string {
                $productName = $item->product?->name
                    ?? $item->purchaseOrderItem?->product?->name
                    ?? 'Item';
                $unit = $item->product?->unit
                    ?? $item->purchaseOrderItem?->product?->unit
                    ?? 'qty';
                $variance = (float) $item->variance;
                $direction = $variance < 0 ? 'Short' : 'Extra';

                return $productName.': '.$direction.' '.number_format(abs($variance), 2).' '.$unit;
            })
            ->values();

        if ($lines->isEmpty()) {
            return null;
        }

        return $lines->implode("\n");
    }

    /**
     * @param  Collection<int, Supplier>  $suppliers
     * @param  Collection<int, PurchaserCart>|null  $sameDayAssignedDrafts
     * @param  Collection<int, PurchaserCart>|null  $overdueCarts
     * @param  array<int, array{warehouse_confirmed: bool, total_batches: int, confirmed_batches: int}>|null  $overdueBatchState
     * @return Collection<int, array{key:string,label:string,description:string,count:int,empty:string,rows:Collection<int, array{supplier:Supplier,cart:PurchaserCart,route:string,button:string,popup_title:string,popup_message:string}>}>
     */
    private function buildSupplierIssueSections(
        int $userId,
        Carbon $selectedDate,
        Collection $suppliers,
        string $selectedTab,
        ?Collection $sameDayAssignedDrafts = null,
        ?Collection $overdueCarts = null,
        ?array $overdueBatchState = null,
    ): Collection {
        $sameDayAssignedDrafts = ($sameDayAssignedDrafts ?? PurchaserCart::query()
            ->where('user_id', $userId)
            ->whereDate('business_date', $selectedDate)
            ->where('status', 'draft')
            ->whereNotNull('supplier_id')
            ->with(['supplier', 'items.product', 'purchaseInvoice', 'goodsReceived'])
            ->orderByDesc('updated_at')
            ->get())
            ->filter(fn (PurchaserCart $cart): bool => $cart->items->isNotEmpty());

        $overdueCarts = $overdueCarts ?? $this->overdueCartsForUser($userId)->loadMissing(['supplier', 'items.product', 'purchaseInvoice', 'goodsReceived']);
        $overdueBatchState = $overdueBatchState ?? $this->relatedBatchStateForCarts($overdueCarts);

        $overdueDraftRows = $overdueCarts
            ->filter(fn (PurchaserCart $cart): bool => $cart->status === 'draft')
            ->filter(fn (PurchaserCart $cart): bool => $cart->supplier !== null)
            ->map(function (PurchaserCart $cart): array {
                return [
                    'supplier' => $cart->supplier,
                    'cart' => $cart,
                    'route' => route('purchaser.bill', ['cart' => $cart, 'date' => $cart->business_date->format('Y-m-d')]),
                    'button' => 'Open Bill Page',
                    'action_type' => 'link',
                    'popup_title' => 'Finish overdue cart',
                    'popup_message' => "This older business-day cart still needs bill processing for {$cart->cart_number}.",
                ];
            })
            ->values();

        $receiptPendingRows = $overdueCarts
            ->filter(fn (PurchaserCart $cart): bool => $cart->status !== 'draft')
            ->filter(fn (PurchaserCart $cart): bool => $cart->supplier !== null)
            ->filter(fn (PurchaserCart $cart): bool => ! $this->isWarehouseConfirmed($overdueBatchState[(int) $cart->id] ?? []))
            ->map(function (PurchaserCart $cart): array {
                return [
                    'supplier' => $cart->supplier,
                    'cart' => $cart,
                    'route' => $cart->purchaseInvoice ? route('purchaser.invoices.show', $cart->purchaseInvoice) : route('purchaser.history', ['date' => $cart->business_date->format('Y-m-d')]),
                    'button' => 'View Bill',
                    'action_type' => 'link',
                    'popup_title' => 'Receipt still pending',
                    'popup_message' => "Warehouse receipt confirmation is still pending for {$cart->cart_number}.",
                ];
            })
            ->values();

        $billPendingRows = $sameDayAssignedDrafts
            ->filter(fn (PurchaserCart $cart): bool => $cart->supplier !== null)
            ->map(function (PurchaserCart $cart): array {
                return [
                    'supplier' => $cart->supplier,
                    'cart' => $cart,
                    'route' => route('purchaser.bill', ['cart' => $cart, 'date' => $cart->business_date->format('Y-m-d')]),
                    'button' => 'Open Bill Page',
                    'action_type' => 'link',
                    'popup_title' => 'Finish bill processing',
                    'popup_message' => "Finish bill processing from the bill page for {$cart->cart_number}.",
                ];
            })
            ->values();

        $paymentFollowUpRows = $overdueCarts
            ->filter(fn (PurchaserCart $cart): bool => $cart->supplier !== null)
            ->filter(fn (PurchaserCart $cart): bool => $this->isWarehouseConfirmed($overdueBatchState[(int) $cart->id] ?? []))
            ->filter(fn (PurchaserCart $cart): bool => $this->cartHasPaymentPending($cart))
            ->filter(function (PurchaserCart $cart) use ($selectedTab): bool {
                $paymentMethod = $cart->purchaseInvoice?->payment_method ?: $cart->payment_method;

                if ($selectedTab === 'credit') {
                    return $paymentMethod === 'Credit';
                }

                return $paymentMethod !== 'Credit';
            })
            ->map(function (PurchaserCart $cart) use ($selectedDate, $selectedTab): array {
                return [
                    'supplier' => $cart->supplier,
                    'cart' => $cart,
                    'route' => '',
                    'button' => 'Update Payment',
                    'action_type' => 'update_payment',
                    'invoice' => [
                        'id' => $cart->purchaseInvoice?->id,
                        'number' => $cart->purchaseInvoice?->invoice_number,
                        'supplier' => $cart->supplier?->name,
                        'amount' => (float) ($cart->purchaseInvoice?->amount ?? 0),
                        'discountAmount' => (float) ($cart->purchaseInvoice?->discount_amount ?? 0),
                        'paidAmount' => (float) ($cart->purchaseInvoice?->paid_amount ?? 0),
                        'paymentMethod' => $cart->purchaseInvoice?->payment_method ?: 'Cash',
                        'paymentNote' => $cart->purchaseInvoice?->payment_note,
                        'paymentDetails' => $cart->purchaseInvoice?->payment_details,
                        'creditApproved' => (bool) $cart->supplier?->credit_approved,
                    ],
                    'payment_route' => $cart->purchaseInvoice ? route('purchaser.invoices.payment', $cart->purchaseInvoice) : null,
                    'popup_title' => 'Resolve payment follow-up',
                    'popup_message' => $selectedTab === 'credit'
                        ? "Credit follow-up is still open for {$cart->cart_number}."
                        : "Payment settlement is still open for {$cart->cart_number}.",
                    'vendor_route' => route('purchaser.suppliers.show', ['supplier' => $cart->supplier, 'date' => $selectedDate->format('Y-m-d')]),
                ];
            })
            ->values();

        if ($selectedTab === 'credit') {
            return collect([
                [
                    'key' => 'credit',
                    'label' => 'Credit Follow-up',
                    'description' => 'Approved credit purchases still waiting for settlement or confirmation.',
                    'count' => $paymentFollowUpRows->count(),
                    'empty' => 'No open credit follow-up right now.',
                    'rows' => $paymentFollowUpRows,
                ],
            ]);
        }

        return collect([
            [
                'key' => 'bill_pending',
                'label' => 'Bill Pending',
                'description' => 'Current business-day draft carts with supplier selected but bill not processed yet.',
                'count' => $billPendingRows->count(),
                'empty' => 'No bill-pending vendor issue right now.',
                'rows' => $billPendingRows,
            ],
            [
                'key' => 'overdue_bill_pending',
                'label' => 'Overdue Bill Pending',
                'description' => 'Older business-day carts still waiting for bill processing or completion.',
                'count' => $overdueDraftRows->count(),
                'empty' => 'No overdue bill-processing issue right now.',
                'rows' => $overdueDraftRows,
            ],
            [
                'key' => 'receipt_pending',
                'label' => 'Receipt Pending',
                'description' => 'Older business-date submitted carts still waiting for warehouse receipt confirmation.',
                'count' => $receiptPendingRows->count(),
                'empty' => 'No old receipt-pending issue right now.',
                'rows' => $receiptPendingRows,
            ],
            [
                'key' => 'payment_pending',
                'label' => 'Payment Pending',
                'description' => 'Older business-date bills still waiting for payment follow-up.',
                'count' => $paymentFollowUpRows->count(),
                'empty' => 'No old payment follow-up issue right now.',
                'rows' => $paymentFollowUpRows,
            ],
        ]);
    }

    private function resolveQuickFilter(string $selectedChip): string
    {
        return in_array($selectedChip, self::QUICK_FILTERS, true) ? $selectedChip : 'All';
    }

    private function resolveDailyShareMode(string $shareMode): string
    {
        $shareMode = $shareMode === 'all' ? 'any' : $shareMode;

        return in_array($shareMode, ['changed', 'any', 'tag', 'product'], true) ? $shareMode : 'changed';
    }

    /**
     * @return Collection<int, PurchaseInvoice>
     */
    private function linkedInvoicesForSupplier(Supplier $supplier): Collection
    {
        return $supplier->purchaserCarts
            ->pluck('purchaseInvoice')
            ->filter(fn ($invoice): bool => $invoice instanceof PurchaseInvoice)
            ->unique(fn (PurchaseInvoice $invoice): int|string => $invoice->getKey())
            ->values();
    }

    private function invoiceRemainingBalance(PurchaseInvoice $invoice): float
    {
        return max(0, round(((float) $invoice->amount - (float) $invoice->discount_amount) - (float) $invoice->paid_amount, 2));
    }

    /**
     * @param  array<int, string>  $selectedTags
     * @param  array<int, int>  $selectedProductIds
     */
    private function filterDailySummaryForShare(
        Collection $dailySummary,
        string $shareMode,
        array $selectedTags,
        array $selectedProductIds,
        int $selectedProductId,
    ): Collection {
        return match ($shareMode) {
            'changed' => $dailySummary
                ->filter(fn (array $summary): bool => ((float) $summary['remaining_qty'] - (float) $summary['draft_qty']) > 0)
                ->values(),
            'any' => $dailySummary->values(),
            'tag' => $dailySummary
                ->filter(function (array $summary) use ($selectedProductIds, $selectedTags): bool {
                    // If specific products are checked, use those
                    if (! empty($selectedProductIds)) {
                        return in_array((int) $summary['product_id'], $selectedProductIds, true);
                    }
                    // If tags are selected, filter by category
                    if (! empty($selectedTags)) {
                        return in_array((string) $summary['category_name'], $selectedTags, true);
                    }

                    // Nothing selected: show all
                    return true;
                })
                ->values(),
            'product' => $dailySummary
                ->filter(fn (array $summary): bool => (int) $summary['product_id'] === $selectedProductId)
                ->values(),
            default => $dailySummary
                ->filter(fn (array $summary): bool => ((float) $summary['remaining_qty'] - (float) $summary['draft_qty']) > 0)
                ->values(),
        };
    }

    private function buildDailySummaryShareText(Collection $dailySummary, Carbon $date): string
    {
        $lines = [
            '*Daily Purchase Summary*',
            $date->format('d M Y'),
            '---',
            '',
        ];

        foreach ($dailySummary as $summary) {
            $productHeader = '*'.$summary['product_name'].'*';
            $orderDate = $summary['order_date'];
            if ($orderDate->format('Y-m-d') !== $date->format('Y-m-d')) {
                $productHeader .= ' (Pending '.$orderDate->format('d M Y').')';
            }
            $lines[] = $productHeader;

            foreach ($summary['quantity_buckets'] as $bucket) {
                $lines[] = $bucket['formatted'].' x '.$bucket['count'];
            }

            $lines[] = 'Total '.$this->formatShareQuantity((float) $summary['total_approved_qty'], $summary['unit']);
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    private function buildDailySummaryShareTotalText(Collection $dailySummary, Carbon $date): string
    {
        $lines = [
            '*Daily Purchase Total Qty*',
            $date->format('d M Y'),
            '---',
            '',
        ];

        foreach ($dailySummary as $summary) {
            $productHeader = '*'.$summary['product_name'].'*';
            $orderDate = $summary['order_date'];
            if ($orderDate->format('Y-m-d') !== $date->format('Y-m-d')) {
                $productHeader .= ' (Pending '.$orderDate->format('d M Y').')';
            }
            $lines[] = $productHeader;
            $lines[] = 'Total '.$this->formatShareQuantity((float) $summary['remaining_qty'], $summary['unit']);
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    private function buildCartShareText(PurchaserCart $cart, bool $includePrice, float $discountAmount = 0, string $shareFormat = 'total'): string
    {
        $nameWidth = 14;
        $qtyWidth = 4;
        $rateWidth = 5;
        $totalWidth = 6;

        $lines = [
            'Green Leaf Traders - Purchase Order',
            'Date: '.$cart->business_date->format('d/m/Y').' | '.$cart->cart_number,
            '',
            '---',
        ];

        $subTotal = 0.0;
        $formattedRows = [];
        $dailySummaryByProduct = $includePrice || $shareFormat !== 'selection'
            ? collect()
            : $this->buildDailySummary($cart->business_date->copy()->startOfDay(), [])
                ->keyBy(fn (array $summary): int => (int) $summary['product_id']);

        foreach ($cart->items as $item) {
            if ($includePrice) {
                $quantity = $this->formatCompactShareNumber((float) $item->quantity);
                $unitPrice = (float) $item->unit_price;
                $lineTotal = round((float) $item->quantity * $unitPrice, 2);
                $subTotal += $lineTotal;

                array_push(
                    $formattedRows,
                    ...$this->formatSharePriceRows(
                        (string) $item->product->name,
                        $quantity,
                        $this->formatCompactShareNumber($unitPrice),
                        $this->formatCompactShareNumber($lineTotal),
                        $nameWidth,
                        $qtyWidth,
                        $rateWidth,
                        $totalWidth,
                    )
                );

                continue;
            }

            foreach ($this->wrapShareProductName((string) $item->product->name, $nameWidth) as $index => $wrappedLine) {
                if ($index === 0) {
                    $quantityText = $shareFormat === 'selection'
                        ? $this->formatCartShareQuantityBreakdown($item, $dailySummaryByProduct)
                        : $this->formatShareQuantity((float) $item->quantity, $item->product->unit);

                    $lines[] = str_pad($wrappedLine, $nameWidth).' '.$quantityText;

                    continue;
                }

                $lines[] = $wrappedLine;
            }
        }

        if ($includePrice) {
            $netTotal = max(0, round($subTotal - $discountAmount, 2));

            $lines[] = '```';
            $lines[] = rtrim(sprintf("%-{$nameWidth}s %{$qtyWidth}s %{$rateWidth}s %{$totalWidth}s", 'Item', 'Qty', 'Rate', 'Total'));
            $lines[] = rtrim(sprintf("%-{$nameWidth}s %{$qtyWidth}s %{$rateWidth}s %{$totalWidth}s", str_repeat('-', $nameWidth), str_repeat('-', $qtyWidth), str_repeat('-', $rateWidth), str_repeat('-', $totalWidth)));
            $lines[] = '';
            foreach ($formattedRows as $formattedRow) {
                $lines[] = rtrim($formattedRow);
            }
            $lines[] = '';
            $lines[] = sprintf("%-{$nameWidth}s %{$qtyWidth}s %{$rateWidth}s %{$totalWidth}s", 'Total', '', '', $this->formatCompactShareNumber($subTotal));
            $lines[] = sprintf("%-{$nameWidth}s %{$qtyWidth}s %{$rateWidth}s %{$totalWidth}s", 'Discount', '', '', $this->formatCompactShareNumber($discountAmount));
            $lines[] = sprintf("%-{$nameWidth}s %{$qtyWidth}s %{$rateWidth}s %{$totalWidth}s", 'Net Total', '', '', $this->formatCompactShareNumber($netTotal));
            $lines[] = '```';
        }

        $lines[] = '';
        $lines[] = '---';
        $lines[] = 'Please pack and confirm.';

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $dailySummaryByProduct
     */
    private function formatCartShareQuantityBreakdown(PurchaserCartItem $item, Collection $dailySummaryByProduct): string
    {
        $dailySummary = $dailySummaryByProduct->get((int) $item->product_id);
        $quantityBuckets = is_array($dailySummary) ? ($dailySummary['quantity_buckets'] ?? []) : [];

        if (! empty($quantityBuckets)) {
            $bucketBreakdown = collect($quantityBuckets)
                ->map(fn (array $bucket): string => $bucket['formatted'].' x '.$bucket['count'])
                ->implode(', ');

            return $bucketBreakdown.' = '.$this->formatShareQuantity((float) $item->quantity, $item->product->unit);
        }

        return $this->formatShareQuantity((float) $item->quantity, $item->product->unit);
    }

    /**
     * @return array<int, string>
     */
    private function formatSharePriceRows(
        string $productName,
        string $quantity,
        string $unitPrice,
        string $lineTotal,
        int $nameWidth,
        int $qtyWidth,
        int $rateWidth,
        int $totalWidth,
    ): array {
        $nameLines = $this->wrapShareProductName($productName, $nameWidth);

        if (count($nameLines) === 1) {
            return [
                sprintf(
                    "%-{$nameWidth}s %{$qtyWidth}s %{$rateWidth}s %{$totalWidth}s",
                    $nameLines[0],
                    $quantity,
                    $unitPrice,
                    $lineTotal,
                ),
            ];
        }

        return [
            $nameLines[0],
            sprintf(
                "%-{$nameWidth}s %{$qtyWidth}s %{$rateWidth}s %{$totalWidth}s",
                $nameLines[1],
                $quantity,
                $unitPrice,
                $lineTotal,
            ),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function wrapShareProductName(string $productName, int $lineWidth): array
    {
        $trimmed = preg_replace('/\s+/', ' ', trim($productName)) ?? trim($productName);

        if (mb_strlen($trimmed) <= $lineWidth) {
            return [$trimmed];
        }

        $words = preg_split('/\s+/', $trimmed) ?: [$trimmed];
        $firstLine = '';
        $usedWords = 0;

        foreach ($words as $index => $word) {
            $candidate = $firstLine === '' ? $word : $firstLine.' '.$word;

            if (mb_strlen($candidate) > $lineWidth) {
                break;
            }

            $firstLine = $candidate;
            $usedWords = $index + 1;
        }

        if ($firstLine === '') {
            return [$this->truncateShareProductName($trimmed, $lineWidth)];
        }

        $secondLine = trim(implode(' ', array_slice($words, $usedWords)));

        if ($secondLine === '') {
            return [$firstLine];
        }

        return [
            $firstLine,
            $this->truncateShareProductName($secondLine, $lineWidth),
        ];
    }

    private function truncateShareProductName(string $productName, int $maxLength = 14): string
    {
        $trimmed = trim($productName);

        if (mb_strlen($trimmed) <= $maxLength) {
            return $trimmed;
        }

        return rtrim(mb_substr($trimmed, 0, max(1, $maxLength - 1))).'.';
    }

    private function formatCompactShareNumber(float $value): string
    {
        $formatted = number_format($value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function buildSupplierWhatsAppUrl(Supplier $supplier, string $message): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $supplier->mobile_number);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        return 'https://api.whatsapp.com/send?phone='.$digits.'&text='.rawurlencode($message);
    }

    private function resolvePaymentStatus(string $paymentMethod, float $invoiceAmount, float $paidAmount): string
    {
        if (strcasecmp($paymentMethod, 'Credit') === 0) {
            return 'credit_pending_approval';
        }

        if ($paidAmount <= 0) {
            return 'unpaid';
        }

        if ($paidAmount < $invoiceAmount) {
            return 'partial';
        }

        return 'paid';
    }

    private function generatePurchaseOrderNumber(Carbon $date): string
    {
        do {
            $suffix = strtoupper(bin2hex(random_bytes(2)));
            $number = 'PO-PURCH-'.$date->format('Ymd').'-'.$suffix;
        } while (PurchaseOrder::query()->where('po_number', $number)->exists());

        return $number;
    }

    private function generateGrnNumber(Carbon $date): string
    {
        do {
            $suffix = strtoupper(bin2hex(random_bytes(2)));
            $number = 'GRN-PURCH-'.$date->format('Ymd').'-'.$suffix;
        } while (GoodsReceived::query()->where('grn_number', $number)->exists());

        return $number;
    }

    private function normalizeBucketKey(float $quantity): string
    {
        return number_format($quantity, 3, '.', '');
    }

    private function formatBucketLabel(float $quantity, string $unit): string
    {
        $value = $this->trimTrailingZeros($quantity);

        return $unit === 'kg' ? $value.'kg' : $value;
    }

    private function formatShareQuantity(float $quantity, string $unit): string
    {
        return $this->trimTrailingZeros($quantity).' '.$unit;
    }

    private function trimTrailingZeros(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    public function dailyPrices(Request $request): View
    {
        $this->authorizeDailyPriceAccess();

        $dateInput = $request->input('date');
        if ($dateInput) {
            try {
                $operationalDate = Carbon::parse($dateInput);
            } catch (\Throwable $e) {
                $operationalDate = $this->businessDayService->operationalDate();
            }
        } else {
            $operationalDate = $this->businessDayService->operationalDate();
        }

        $searchQuery = trim((string) $request->input('search', ''));
        $doubleCheck = (bool) $request->boolean('double_check');

        $selectedCategory = $request->input('category_id');
        $user = $request->user();
        $assignedCategoryIds = $user?->hasAssignedCategoryFilter() ? $user->assignedCategoryIds() : null;

        $categoriesQuery = Category::query()
            ->whereHas('products', fn ($q) => $q->active())
            ->orderBy('name');

        if ($assignedCategoryIds !== null) {
            $categoriesQuery->whereIn('id', $assignedCategoryIds);
        }

        $categories = $categoriesQuery->get();

        $productsQuery = Product::query()
            ->active()
            ->with(['category'])
            ->ordered();

        if ($assignedCategoryIds !== null) {
            $productsQuery->whereIn('category_id', $assignedCategoryIds);
        }

        if ($selectedCategory && $selectedCategory !== 'all') {
            $productsQuery->where('category_id', (int) $selectedCategory);
        }

        if ($searchQuery !== '') {
            $productsQuery->where(function ($q) use ($searchQuery): void {
                $q->where('name', 'LIKE', "%{$searchQuery}%")
                    ->orWhere('sku', 'LIKE', "%{$searchQuery}%");
            });
        }

        $products = $productsQuery->get();
        $productIds = $products->pluck('id')->toArray();

        $purchaserAveragePrices = PurchaserCartItem::query()
            ->whereIn('product_id', $productIds)
            ->where('quantity', '>', 0)
            ->where('unit_price', '>', 0)
            ->whereHas('cart', fn ($q) => $q->whereDate('business_date', $operationalDate->toDateString()))
            ->selectRaw('product_id, SUM(quantity * unit_price) as total_cost, SUM(quantity) as total_qty')
            ->groupBy('product_id')
            ->get()
            ->mapWithKeys(function ($row): array {
                $totalQty = (float) ($row->total_qty ?? 0);

                if ($totalQty <= 0.0001) {
                    return [(int) $row->product_id => null];
                }

                $avg = round(((float) $row->total_cost) / $totalQty, 2);

                return [(int) $row->product_id => $avg > 0 ? $avg : null];
            });

        // Get latest valid price approvals per product for comparison and history
        $today = $operationalDate->toDateString();

        $allApprovals = DailyPriceApproval::query()
            ->with(['updatedBy'])
            ->whereIn('product_id', $productIds)
            ->where('purchase_price', '>', 0)
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('product_id');

        $productsWithPrices = $products->map(function ($product) use ($allApprovals, $today, $purchaserAveragePrices) {
            $approvals = $allApprovals->get($product->id, collect());
            $finalApprovals = $approvals->filter(fn ($a): bool => $a->status === 'approved' && $a->approved_at !== null)->values();

            $todayApproval = $finalApprovals->first(fn ($a) => $a->business_date->toDateString() === $today);
            $previousApproval = $finalApprovals->first(fn ($a) => $a->business_date->toDateString() < $today);

            $purchaseToday = $todayApproval?->purchase_price > 0 ? (float) $todayApproval->purchase_price : null;
            $purchasePrevious = $previousApproval?->purchase_price > 0 ? (float) $previousApproval->purchase_price : null;
            $todayPrice = $todayApproval?->price_a !== null && (float) $todayApproval->price_a > 0
                ? (float) $todayApproval->price_a
                : null;
            $previousPrice = $previousApproval?->price_a !== null && (float) $previousApproval->price_a > 0
                ? (float) $previousApproval->price_a
                : null;

            $diffAmount = null;
            $diffPercentage = null;
            $priceState = 'not_set';

            if ($todayPrice === null) {
                $priceState = 'not_set';
            } elseif ($previousPrice === null) {
                $priceState = 'no_previous';
            } else {
                $diffAmount = round($todayPrice - $previousPrice, 2);
                if (abs($diffAmount) < 0.001) {
                    $priceState = 'no_change';
                    $diffAmount = 0.00;
                    $diffPercentage = 0.0;
                } elseif ($diffAmount > 0) {
                    $priceState = 'increased';
                    $diffPercentage = round(($diffAmount / $previousPrice) * 100, 1);
                } else {
                    $priceState = 'decreased';
                    $diffPercentage = round((abs($diffAmount) / $previousPrice) * 100, 1);
                }
            }

            // Up to 3 valid recent price history entries
            $history = $approvals->take(3)->map(function ($app) {
                $historyPrice = $app->price_a !== null && (float) $app->price_a > 0
                    ? (float) $app->price_a
                    : (float) $app->purchase_price;

                return [
                    'date' => $app->business_date->format('d M Y'),
                    'price' => $historyPrice,
                    'updated_by' => $app->updatedBy?->name ?? 'System',
                ];
            })->values()->all();

            $updatedAtFormatted = null;
            if ($todayApproval?->updated_at) {
                $updatedAtFormatted = $todayApproval->updated_at->format('d M Y \a\t g:i A');
            }

            return [
                'id' => $product->id,
                'public_uuid' => $product->public_uuid,
                'name' => $product->name,
                'sku' => $product->sku,
                'show_in_purchaser_order' => (bool) $product->show_in_purchaser_order,
                'unit' => strtoupper((string) ($product->unit ?: 'KG')),
                'unit_info_price' => $todayPrice ?? $previousPrice,
                'purchaser_avg_price' => $purchaserAveragePrices->get((int) $product->id),
                'selling_price_a' => $todayPrice,
                'purchase_today' => $purchaseToday,
                'purchase_previous' => $purchasePrevious,
                'price_today' => $todayPrice,
                'previous_price' => $previousPrice,
                'diff_amount' => $diffAmount,
                'diff_percentage' => $diffPercentage,
                'price_state' => $priceState,
                'updated_by_name' => $todayApproval?->updatedBy?->name,
                'updated_time' => $todayApproval?->updated_at ? $todayApproval->updated_at->format('g:i A') : null,
                'updated_at_formatted' => $updatedAtFormatted,
                'history' => $history,
                'status' => $todayApproval?->status ?? 'none',
            ];
        });

        return view('purchasing.purchaser.daily-prices', [
            'products' => $productsWithPrices,
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
            'operationalDate' => $operationalDate,
            'searchQuery' => $searchQuery,
            'doubleCheck' => $doubleCheck,
            'cutoffLabel' => $this->businessDayService->cutoffLabel(),
        ]);
    }

    public function updateDailyPrices(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeDailyPriceAccess();

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'refresh_invoices_only' => ['nullable', 'boolean'],
            'prices' => ['required', 'array'],
            'prices.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'prices.*.purchase_price' => ['required', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $userId = (int) $user->id;
        $isRefreshAction = (bool) $request->boolean('refresh_invoices_only');
        $dateInput = $request->input('date');
        $businessDateStr = $dateInput ? Carbon::parse($dateInput)->toDateString() : $this->businessDayService->operationalDate()->toDateString();

        $updatedCount = 0;
        $targetProductId = null;
        $updatedProductIds = [];

        DB::transaction(function () use ($validated, $userId, $businessDateStr, &$updatedCount, &$targetProductId, &$updatedProductIds): void {
            foreach ($validated['prices'] as $priceData) {
                $productId = (int) $priceData['product_id'];
                $targetProductId = $productId;
                $newSellingPrice = round((float) $priceData['purchase_price'], 2);

                if ($newSellingPrice <= 0) {
                    continue;
                }

                $product = Product::query()->find($productId);
                if (! $product) {
                    continue;
                }

                $approval = DailyPriceApproval::query()
                    ->where('product_id', $productId)
                    ->where('business_date', $businessDateStr)
                    ->first();

                $previousApproval = DailyPriceApproval::query()
                    ->where('product_id', $productId)
                    ->where('business_date', '<', $businessDateStr)
                    ->where('status', 'approved')
                    ->whereNotNull('approved_at')
                    ->orderByDesc('business_date')
                    ->first();

                if (! $approval) {
                    $approval = new DailyPriceApproval([
                        'product_id' => $productId,
                        'business_date' => $businessDateStr,
                        'purchase_price' => (float) ($previousApproval?->purchase_price ?? ($product->vendor_price > 0 ? $product->vendor_price : $product->base_price)),
                        'price_unit' => ProductUnit::normalizeUnit((string) $product->unit ?: 'kg'),
                        'price_a' => $newSellingPrice,
                        'price_b' => (float) ($previousApproval?->price_b ?? $product->base_price),
                        'price_c' => (float) ($previousApproval?->price_c ?? $product->base_price),
                        'status' => 'approved',
                        'approved_by' => $userId,
                        'approved_at' => now(),
                    ]);
                } else {
                    $approval->price_a = $newSellingPrice;
                    if ($approval->price_b === null || (float) $approval->price_b <= 0) {
                        $approval->price_b = (float) ($previousApproval?->price_b ?? $newSellingPrice);
                    }
                    if ($approval->price_c === null || (float) $approval->price_c <= 0) {
                        $approval->price_c = (float) ($previousApproval?->price_c ?? $newSellingPrice);
                    }
                    $approval->status = 'approved';
                    $approval->approved_by = $userId;
                    $approval->approved_at = now();
                }

                $approval->updated_by = $userId;
                $approval->save();

                // Sync to matrix tables and vendor_price
                $groupA = ShopPriceGroup::query()->where('name', 'A')->first();
                $groupB = ShopPriceGroup::query()->where('name', 'B')->first();
                $groupC = ShopPriceGroup::query()->where('name', 'C')->first();

                $this->updateActivePricesForGroup($product, $groupA, (float) $approval->price_a, $userId);
                $this->updateActivePricesForGroup($product, $groupB, (float) $approval->price_b, $userId);
                $this->updateActivePricesForGroup($product, $groupC, (float) $approval->price_c, $userId);

                $updatedProductIds[] = $productId;
                $updatedCount++;
            }
        });

        $invoiceSyncSummary = $this->syncRelatedInvoicesForApprovedPriceUpdates(
            productIds: $updatedProductIds,
            businessDate: $businessDateStr,
            userId: $userId,
        );
        $invoiceUpdatesCount = (int) $invoiceSyncSummary['updated'];
        $invoiceSyncSkippedCount = (int) ($invoiceSyncSummary['skipped'] ?? 0);
        $invoiceSyncFailedCount = (int) $invoiceSyncSummary['failed'];
        $invoiceSyncTargetedCount = (int) $invoiceSyncSummary['targeted'];
        $invoiceSyncOk = $invoiceSyncFailedCount === 0;

        if ($request->wantsJson()) {
            $productId = $targetProductId;
            $allApprovals = DailyPriceApproval::query()
                ->with(['updatedBy'])
                ->where('product_id', $productId)
                ->where('purchase_price', '>', 0)
                ->orderByDesc('business_date')
                ->orderByDesc('id')
                ->get();

            $finalApprovals = $allApprovals
                ->filter(fn ($a): bool => $a->status === 'approved' && $a->approved_at !== null)
                ->values();

            $todayApproval = $finalApprovals->first(fn ($a) => $a->business_date->toDateString() === $businessDateStr);
            $previousApproval = $finalApprovals->first(fn ($a) => $a->business_date->toDateString() < $businessDateStr);

            $purchaseToday = $todayApproval?->purchase_price > 0 ? (float) $todayApproval->purchase_price : null;
            $purchasePrevious = $previousApproval?->purchase_price > 0 ? (float) $previousApproval->purchase_price : null;
            $todayPrice = $todayApproval?->price_a !== null && (float) $todayApproval->price_a > 0
                ? (float) $todayApproval->price_a
                : null;
            $previousPrice = $previousApproval?->price_a !== null && (float) $previousApproval->price_a > 0
                ? (float) $previousApproval->price_a
                : null;

            $diffAmount = null;
            $diffPercentage = null;
            $priceState = 'not_set';

            if ($todayPrice === null) {
                $priceState = 'not_set';
            } elseif ($previousPrice === null) {
                $priceState = 'no_previous';
            } else {
                $diffAmount = round($todayPrice - $previousPrice, 2);
                if (abs($diffAmount) < 0.001) {
                    $priceState = 'no_change';
                    $diffAmount = 0.00;
                    $diffPercentage = 0.0;
                } elseif ($diffAmount > 0) {
                    $priceState = 'increased';
                    $diffPercentage = round(($diffAmount / $previousPrice) * 100, 1);
                } else {
                    $priceState = 'decreased';
                    $diffPercentage = round((abs($diffAmount) / $previousPrice) * 100, 1);
                }
            }

            $history = $allApprovals->take(3)->map(function ($app) {
                $historyPrice = $app->price_a !== null && (float) $app->price_a > 0
                    ? (float) $app->price_a
                    : (float) $app->purchase_price;

                return [
                    'date' => $app->business_date->format('d M Y'),
                    'price' => $historyPrice,
                    'updated_by' => $app->updatedBy?->name ?? 'System',
                ];
            })->values()->all();

            $purchaserAvgPrice = PurchaserCartItem::query()
                ->where('product_id', $productId)
                ->where('quantity', '>', 0)
                ->where('unit_price', '>', 0)
                ->whereHas('cart', fn ($q) => $q->whereDate('business_date', $businessDateStr))
                ->selectRaw('SUM(quantity * unit_price) as total_cost, SUM(quantity) as total_qty')
                ->first();

            $avgPrice = null;
            if ($purchaserAvgPrice) {
                $totalQty = (float) ($purchaserAvgPrice->total_qty ?? 0);
                if ($totalQty > 0.0001) {
                    $avgPrice = round(((float) ($purchaserAvgPrice->total_cost ?? 0)) / $totalQty, 2);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Successfully updated product price.',
                'global_update_message' => $invoiceSyncTargetedCount === 0
                    ? ($isRefreshAction ? 'No related invoices found for refresh.' : null)
                    : ($invoiceSyncOk
                        ? ($invoiceSyncSkippedCount > 0
                            ? ($isRefreshAction
                                ? "Invoice refresh completed for {$invoiceUpdatesCount} invoice(s); {$invoiceSyncSkippedCount} skipped due to pending/missing approved prices."
                                : "Price approved and updated in {$invoiceUpdatesCount} invoice(s); {$invoiceSyncSkippedCount} skipped due to pending/missing approved prices.")
                            : ($isRefreshAction
                                ? "Invoice refresh completed for {$invoiceUpdatesCount} related invoice(s)."
                                : "Price approved and updated globally in {$invoiceUpdatesCount} related invoice(s)."))
                        : ($isRefreshAction
                            ? "Invoice refresh failed for {$invoiceSyncFailedCount} related invoice(s)."
                            : "Price approved, but invoice sync failed for {$invoiceSyncFailedCount} related invoice(s).")),
                'invoice_updates_count' => $invoiceUpdatesCount,
                'invoice_updates_targeted_count' => $invoiceSyncTargetedCount,
                'invoice_updates_skipped_count' => $invoiceSyncSkippedCount,
                'invoice_updates_failed_count' => $invoiceSyncFailedCount,
                'invoice_sync_ok' => $invoiceSyncOk,
                'refresh_action' => $isRefreshAction,
                'product_id' => $productId,
                'today_price' => $todayPrice,
                'previous_price' => $previousPrice,
                'selling_price_a' => $todayPrice,
                'purchase_today' => $purchaseToday,
                'purchase_previous' => $purchasePrevious,
                'purchaser_avg_price' => $avgPrice,
                'diff_amount' => $diffAmount,
                'diff_percentage' => $diffPercentage,
                'price_state' => $priceState,
                'updated_by_name' => $todayApproval?->updatedBy?->name ?? $user->name,
                'updated_time' => $todayApproval?->updated_at ? $todayApproval->updated_at->format('g:i A') : now()->format('g:i A'),
                'updated_at_formatted' => $todayApproval?->updated_at ? $todayApproval->updated_at->format('d M Y \a\t g:i A') : now()->format('d M Y \a\t g:i A'),
                'history' => $history,
            ]);
        }

        return redirect()
            ->route('purchaser.daily-prices')
            ->with('success', $invoiceSyncTargetedCount === 0
                ? "Successfully updated {$updatedCount} product price(s) and synced to matrix."
                : ($invoiceSyncOk
                    ? ($invoiceSyncSkippedCount > 0
                        ? "Successfully updated {$updatedCount} product price(s), repriced {$invoiceUpdatesCount} invoice(s), and skipped {$invoiceSyncSkippedCount} invoice(s) with pending/missing approved prices."
                        : "Successfully updated {$updatedCount} product price(s), synced to matrix, and repriced {$invoiceUpdatesCount} related invoice(s).")
                    : "Updated {$updatedCount} product price(s), but invoice sync failed for {$invoiceSyncFailedCount} related invoice(s)."));
    }

    /**
     * Reprice all non-finalized invoices for the same business date that include at least one updated product.
     *
     * @param  array<int>  $productIds
     */
    private function syncRelatedInvoicesForApprovedPriceUpdates(array $productIds, string $businessDate, int $userId): array
    {
        $uniqueProductIds = array_values(array_unique(array_filter(array_map('intval', $productIds), fn (int $id): bool => $id > 0)));

        if ($uniqueProductIds === []) {
            return [
                'targeted' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
            ];
        }

        $targetInvoices = ShopInvoice::query()
            ->whereDate('business_date', $businessDate)
            ->whereHas('items', fn ($query) => $query->whereIn('product_id', $uniqueProductIds))
            ->with(['shop.priceGroup', 'items.product', 'order.items.product'])
            ->get()
            ->reject(fn (ShopInvoice $invoice): bool => $invoice->isFinalLocked())
            ->values();

        $targetedInvoices = $targetInvoices->count();
        $updatedInvoices = 0;
        $skippedInvoices = 0;
        $failedInvoices = 0;

        $targetInvoices
            ->each(function (ShopInvoice $invoice) use ($userId, &$updatedInvoices, &$skippedInvoices, &$failedInvoices): void {
                try {
                    $this->shopInvoiceService->repriceInvoice(
                        $invoice,
                        $userId,
                        'Auto repriced after purchaser daily price approval',
                    );
                    $updatedInvoices++;
                } catch (ValidationException $exception) {
                    $skippedInvoices++;
                } catch (\Throwable $exception) {
                    $failedInvoices++;
                    report($exception);
                }
            });

        return [
            'targeted' => $targetedInvoices,
            'updated' => $updatedInvoices,
            'skipped' => $skippedInvoices,
            'failed' => $failedInvoices,
        ];
    }

    private function authorizeDailyPriceAccess(): void
    {
        abort_unless(
            auth()->user()?->hasRole('purchaser') ||
            auth()->user()?->hasRole('purchase') ||
            auth()->user()?->hasRole('admin'),
            403
        );
    }

    private function updateActivePricesForGroup(Product $product, ?ShopPriceGroup $group, float $priceGradeA, int $userId): void
    {
        if (! $group) {
            return;
        }

        $grades = [
            'A' => 1.00,
            'B' => 0.90,
            'C' => 0.80,
        ];

        foreach ($grades as $gradeVal => $multiplier) {
            $calculatedPrice = round($priceGradeA * $multiplier, 2);

            $activePrice = DailyProductPrice::firstOrNew([
                'product_id' => $product->id,
                'shop_price_group_id' => $group->id,
                'grade' => $gradeVal,
            ]);

            $oldPrice = $activePrice->exists ? (float) $activePrice->selling_price : null;

            $activePrice->fill([
                'selling_price' => $calculatedPrice,
                'price_source' => 'manual',
                'margin_percent' => null,
                'manual_override' => true,
                'override_reason' => 'Purchaser daily price update',
                'changed_by' => $userId,
            ]);
            $activePrice->save();

            if ($oldPrice === null || abs($oldPrice - $calculatedPrice) > 0.0001) {
                DailyProductPriceRevision::create([
                    'daily_product_price_id' => $activePrice->id,
                    'product_id' => $product->id,
                    'shop_price_group_id' => $group->id,
                    'grade' => $gradeVal,
                    'old_price' => $oldPrice,
                    'new_price' => $calculatedPrice,
                    'old_margin_percent' => null,
                    'new_margin_percent' => null,
                    'change_type' => 'manual',
                    'reason' => 'Purchaser daily price update',
                    'changed_by' => $userId,
                    'changed_at' => now(),
                ]);
            }
        }
    }

    private function ensurePurchaser(Request $request): void
    {
        if (
            ! $request->user()->hasRole('purchaser')
            && ! $request->user()->hasRole('admin')
            && ! $request->user()->hasRole('purchase')
        ) {
            abort(403, 'Unauthorized access.');
        }

    }

    private function ensurePurchaseManager(Request $request): void
    {
        if (
            ! $request->user()->hasRole('purchase')
            && ! $request->user()->hasRole('admin')
            && ! $request->user()->can('purchasing.order.approve')
        ) {
            abort(403, 'Unauthorized access.');
        }
    }

    private function scopedSuppliersForUser(?User $user = null): Collection
    {
        return Supplier::query()->orderBy('name')->get();
    }
}
