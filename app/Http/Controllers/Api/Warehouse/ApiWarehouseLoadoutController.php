<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Warehouse;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopOrderLoadoutState;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Services\Inventory\StockLedgerService;
use App\Services\Pricing\PriceBoardService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\ShopInvoices\ShopInvoiceService;
use App\Support\PerformanceProbe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ApiWarehouseLoadoutController extends Controller
{
    public function __construct(
        private readonly StockLedgerService $stockLedgerService,
        private readonly PriceBoardService $priceBoardService,
        private readonly ShopInvoiceService $shopInvoiceService,
    ) {}

    /**
     * Get loadout orders list with filters and status tab counters.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);
        $probe = PerformanceProbe::start('loadout.index', [
            'route' => $request->route()?->getName(),
            'date' => $request->query('date'),
        ]);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'date' => ['nullable', 'date'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'source' => ['nullable', 'string', 'in:all,shop,direct'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'category_ids' => ['nullable'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $selectedDate = $validated['date'] ?? app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();
        $selectedShopId = isset($validated['shop_id']) ? (int) $validated['shop_id'] : null;
        $selectedSource = (string) ($validated['source'] ?? 'all');

        $selectedCategoryIds = null;
        if ($request->has('category_ids')) {
            $rawIds = $request->get('category_ids');
            if (is_array($rawIds)) {
                $selectedCategoryIds = array_map('intval', $rawIds);
            } elseif (is_string($rawIds) && strlen($rawIds) > 0) {
                $selectedCategoryIds = array_map('intval', explode(',', $rawIds));
            }
        } elseif (isset($validated['category_id'])) {
            $selectedCategoryIds = [(int) $validated['category_id']];
        }

        $selectedWarehouseId = isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null;

        $orders = ShopOrder::query()
            ->whereIn('delivery_status', [
                'pending_delivery',
                'ready_for_dispatch',
                'in_transit',
                'delivered',
                'pending_approval',
                'partially_delivered',
                'delivery_issue',
            ])
            ->with(['shop', 'items.product.category'])
            ->whereHas('items')
            ->when($selectedDate, fn ($query) => $query->whereDate('business_date', $selectedDate))
            ->when($selectedShopId, fn ($query) => $query->where('shop_id', $selectedShopId))
            ->when($selectedSource === 'all', fn ($query) => $query->where('order_source', '!=', 'admin_direct_purchase'))
            ->when($selectedSource === 'shop', fn ($query) => $query->where('order_source', 'shop_owner'))
            ->when($selectedSource === 'direct', fn ($query) => $query->whereIn('order_source', ['admin_direct_purchase', 'direct_sale']))
            ->when($selectedCategoryIds, function ($query) use ($selectedCategoryIds): void {
                $query->whereHas('items.product', fn ($productQuery) => $productQuery->whereIn('category_id', $selectedCategoryIds));
            })
            ->when($selectedWarehouseId, function ($query) use ($selectedWarehouseId): void {
                $query->whereHas('items.product', fn ($productQuery) => $productQuery->where('default_warehouse_id', $selectedWarehouseId));
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('shop', function ($shopQuery) use ($search): void {
                            $shopQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%")
                                ->orWhere('warehouse_tag', 'like', "%{$search}%");
                        })
                        ->orWhereHas('items.product', function ($productQuery) use ($search): void {
                            $productQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%")
                                ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
                        });
                });
            })
            ->orderBy('business_date', 'desc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function (ShopOrder $shopOrder) {
                $loadedCount = $shopOrder->items->where('sorting_status', 'loaded')->count();
                $totalCount = $shopOrder->items->count();

                return [
                    'id' => $shopOrder->id,
                    'order_number' => $shopOrder->order_number,
                    'business_date' => $shopOrder->business_date->toDateString(),
                    'delivery_status' => $shopOrder->delivery_status,
                    'order_source' => $shopOrder->order_source,
                    'display_name' => $shopOrder->loadoutDisplayName(),
                    'shop' => $shopOrder->shop ? [
                        'id' => $shopOrder->shop->id,
                        'name' => $shopOrder->shop->name,
                        'code' => $shopOrder->shop->code,
                        'warehouse_tag' => $shopOrder->shop->warehouse_tag,
                    ] : null,
                    'loaded_count' => $loadedCount,
                    'total_count' => $totalCount,
                    'progress_percentage' => $totalCount > 0 ? round(($loadedCount / $totalCount) * 100) : 0,
                ];
            });
        $probe?->checkpoint('orders_query_and_map');

        $shops = Shop::query()->whereHas('orders')->orderBy('name')->get(['id', 'name', 'code', 'warehouse_tag']);
        $categories = Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $probe?->checkpoint('filter_reference_data');
        $probe?->finish([
            'order_count' => $orders->count(),
            'shop_count' => $shops->count(),
            'category_count' => $categories->count(),
        ]);

        return response()->json([
            'success' => true,
            'selected_date' => $selectedDate,
            'orders' => $orders,
            'shops' => $shops,
            'categories' => $categories,
        ]);
    }

    /**
     * Get detailed loadout info for a single shop order.
     */
    public function show(ShopOrder $shopOrder, Request $request): JsonResponse
    {
        $this->authorizeAccess($request);
        $selectedWarehouseId = $request->integer('warehouse_id') ?: null;
        $probe = PerformanceProbe::start('loadout.show', [
            'route' => $request->route()?->getName(),
            'shop_order_id' => $shopOrder->id,
            'warehouse_id' => $selectedWarehouseId,
        ]);
        $etag = $this->loadoutEditorEtag($shopOrder, $selectedWarehouseId);

        if ($this->requestEtagsContain($request, $etag)) {
            $probe?->checkpoint('etag_not_modified');
            $probe?->finish([
                'not_modified' => true,
            ]);

            return response()->json(null, 304)->setEtag($etag);
        }

        $shopOrder->load(['shop', 'items.product.category', 'items.product.orderUnits', 'items.product.defaultWarehouse', 'deliveredBy', 'invoice.items.product']);
        $probe?->checkpoint('load_order_relations');

        $productGroups = $shopOrder->items
            ->groupBy('product_id')
            ->map(function ($items) {
                $productId = $items->first()->product_id;
                $totalApproved = $this->loadoutApprovedQuantity($items);
                $totalLoaded = (float) $items->where('sorting_status', 'loaded')->sum('loaded_qty');
                $totalLoadedOrderUnit = (float) $items->where('sorting_status', 'loaded')->sum(fn (ShopOrderItem $item): float => (float) ($item->loaded_order_unit_qty ?? 0));
                $totalRequestedOrderUnit = (float) $items->sum(fn (ShopOrderItem $item): float => (float) ($item->requested_qty ?? 0));
                $totalBalance = max(0.0, round($totalApproved - $totalLoaded, 3));
                $available = round($this->stockLedgerService->availableSortedStockForProduct($productId) + $totalLoaded, 3);

                $firstItem = $items->first();
                $productBaseUnit = strtolower((string) ($firstItem->product->unit ?? 'kg'));
                $requestedUnit = strtolower((string) ($firstItem->requested_unit ?? ''));
                $hasSecondaryUnit = $requestedUnit !== '' && $requestedUnit !== $productBaseUnit;
                $measurementCount = (int) ($firstItem->product?->orderUnits?->count() ?? 0);
                $conversionToBase = (float) ($firstItem->requested_unit_conversion_to_base ?? 1.0);
                $defaultLoadedOrderUnitQty = $hasSecondaryUnit && $conversionToBase > 0
                    ? round($totalApproved / $conversionToBase, 2)
                    : null;

                return [
                    'product_id' => $productId,
                    'product_name' => $firstItem->product?->name ?? 'Unknown Product',
                    'product_sku' => $firstItem->product?->sku ?? '',
                    'default_warehouse_id' => $firstItem->product?->default_warehouse_id,
                    'default_warehouse_name' => $firstItem->product?->defaultWarehouse?->name,
                    'default_warehouse_code' => $firstItem->product?->defaultWarehouse?->code,
                    'category_name' => $firstItem->product?->category?->name ?? 'General',
                    'unit' => $firstItem->product->unit ?? 'KG',
                    'product_grade' => $firstItem->product_grade ?? 'A',
                    'total_approved' => $totalApproved,
                    'total_loaded' => $totalLoaded,
                    'loaded_order_unit_qty' => $totalLoadedOrderUnit,
                    'default_loaded_qty' => $totalApproved,
                    'default_loaded_order_unit_qty' => $defaultLoadedOrderUnitQty,
                    'has_secondary_unit' => $hasSecondaryUnit,
                    'measurement_count' => $measurementCount,
                    'use_dual_measurement_inputs' => $hasSecondaryUnit && $measurementCount > 1,
                    'requested_unit_total' => $totalRequestedOrderUnit,
                    'requested_unit_quantity' => (float) ($firstItem->requested_unit_quantity ?? 1.0),
                    'requested_unit_label' => $firstItem->requested_unit_label ?? strtoupper($firstItem->requested_unit ?? ''),
                    'requested_unit_conversion_to_base' => (float) ($firstItem->requested_unit_conversion_to_base ?? 1.0),
                    'total_balance' => $totalBalance,
                    'available_stock' => $available,
                    'is_fully_loaded' => $totalLoaded > 0.0 && $totalBalance <= 0.001,
                    'is_partially_loaded' => $totalLoaded > 0.0 && $totalBalance > 0.001,
                    'sorting_status' => $firstItem->sorting_status,
                    'discrepancy_note' => $firstItem->loadout_discrepancy_note,
                ];
            })
            ->sortBy(fn (array $group) => Product::sortableSku((string) ($group['product_sku'] ?? '')))
            ->values();
        $probe?->checkpoint('build_product_groups');

        $canEdit = $shopOrder->delivery_status !== 'delivered';
        $anyLoaded = $shopOrder->items->where('sorting_status', 'loaded')->count() > 0;
        $hasRemainingBalance = $productGroups->contains(fn (array $group): bool => (float) $group['total_balance'] > 0.001);
        $canMoveToDelivery = $shopOrder->delivery_status === 'ready_for_dispatch' && $anyLoaded && ! $hasRemainingBalance;
        $canMoveToPartialDelivery = $shopOrder->delivery_status === 'ready_for_dispatch' && $anyLoaded && $hasRemainingBalance;
        $canMoveToLoadout = $shopOrder->delivery_status !== 'delivered' && $shopOrder->delivery_status !== 'pending_delivery';
        $mergeCandidates = $this->duplicateMergeCandidates($shopOrder->items);
        $unpricedProductNames = $this->shopInvoiceService->getUnpricedOrderItemNames($shopOrder);
        $loadoutState = $this->loadoutStateSummary($shopOrder, $selectedWarehouseId);
        $probe?->checkpoint('build_status_metadata');

        $invoiceData = null;
        if ($shopOrder->invoice) {
            $invoiceData = [
                'id' => $shopOrder->invoice->id,
                'invoice_number' => $shopOrder->invoice->invoice_number,
                'business_date' => $shopOrder->invoice->business_date->toDateString(),
                'status' => $shopOrder->invoice->status,
                'delivery_status' => $shopOrder->invoice->delivery_status,
                'payment_status' => $shopOrder->invoice->payment_status,
                'subtotal' => (float) $shopOrder->invoice->subtotal,
                'shortage_total' => (float) $shopOrder->invoice->shortage_total,
                'excess_total' => (float) $shopOrder->invoice->excess_total,
                'discount_total' => (float) $shopOrder->invoice->discount_total,
                'final_total' => (float) $shopOrder->invoice->final_total,
                'paid_amount' => (float) $shopOrder->invoice->paid_amount,
                'balance_amount' => (float) $shopOrder->invoice->balance_amount,
                'delivery_note' => $shopOrder->invoice->delivery_note,
                'items' => $shopOrder->invoice->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product?->sku ?? '',
                    'product_category_id' => $item->product?->category_id,
                    'unit' => $item->unit,
                    'approved_qty' => (float) $item->approved_qty,
                    'delivered_qty' => (float) $item->delivered_qty,
                    'shortage_qty' => (float) $item->shortage_qty,
                    'excess_qty' => (float) $item->excess_qty,
                    'unit_price' => (float) $item->unit_price,
                    'line_subtotal' => (float) $item->line_subtotal,
                    'final_line_total' => (float) $item->final_line_total,
                ])->values()->toArray(),
            ];
        } else {
            $items = [];
            $subtotal = 0.0;
            $excessTotal = 0.0;

            foreach ($shopOrder->items as $item) {
                if ($item->sorting_status !== 'loaded') {
                    continue;
                }

                $unitPrice = (float) ($item->locked_selling_price ?? 0.0);
                $loadedQty = (float) $item->loaded_qty;
                $approvedQty = (float) $item->approved_qty;
                $excessQty = (float) ($item->excess_qty ?? 0.0);

                $lineSubtotal = round($approvedQty * $unitPrice, 2);
                $finalLineTotal = round($loadedQty * $unitPrice, 2);

                $subtotal += $lineSubtotal;
                $excessTotal += ($excessQty * $unitPrice);

                $items[] = [
                    'product_name' => $item->product?->name ?? 'Unknown Product',
                    'product_sku' => $item->product?->sku ?? '',
                    'product_category_id' => $item->product?->category_id,
                    'unit' => $item->product?->unit ?? 'KG',
                    'approved_qty' => $approvedQty,
                    'delivered_qty' => $loadedQty,
                    'shortage_qty' => max(0.0, round($approvedQty - $loadedQty, 2)),
                    'excess_qty' => $excessQty,
                    'unit_price' => $unitPrice,
                    'line_subtotal' => $lineSubtotal,
                    'final_line_total' => $finalLineTotal,
                ];
            }

            $invoiceData = [
                'invoice_number' => 'DRAFT-'.$shopOrder->order_number,
                'business_date' => $shopOrder->business_date->toDateString(),
                'status' => 'draft',
                'subtotal' => $subtotal,
                'shortage_total' => 0.0,
                'excess_total' => $excessTotal,
                'discount_total' => 0.0,
                'final_total' => $subtotal + $excessTotal,
                'paid_amount' => 0.0,
                'balance_amount' => $subtotal + $excessTotal,
                'delivery_note' => null,
                'items' => $items,
            ];
        }
        $probe?->checkpoint('build_invoice_data');

        $categories = Category::query()
            ->whereHas('products')
            ->orderBy('name')
            ->get(['id', 'name']);
        $probe?->checkpoint('load_categories');
        $probe?->finish([
            'item_count' => $shopOrder->items->count(),
            'product_group_count' => $productGroups->count(),
            'invoice_item_count' => is_array($invoiceData) ? count($invoiceData['items'] ?? []) : 0,
            'category_count' => $categories->count(),
        ]);

        return response()->json([
            'success' => true,
            'categories' => $categories,
            'order' => [
                'id' => $shopOrder->id,
                'order_number' => $shopOrder->order_number,
                'business_date' => $shopOrder->business_date->toDateString(),
                'delivery_status' => $shopOrder->delivery_status,
                'display_name' => $shopOrder->loadoutDisplayName(),
                'delivery_notes' => $shopOrder->delivery_notes,
                'shop' => $shopOrder->shop ? [
                    'id' => $shopOrder->shop->id,
                    'name' => $shopOrder->shop->name,
                    'code' => $shopOrder->shop->code,
                    'warehouse_tag' => $shopOrder->shop->warehouse_tag,
                ] : null,
            ],
            'product_groups' => $productGroups,
            'can_edit' => $canEdit,
            'any_loaded' => $anyLoaded,
            'can_move_to_delivery' => $canMoveToDelivery,
            'can_move_to_partial_delivery' => $canMoveToPartialDelivery,
            'has_remaining_balance' => $hasRemainingBalance,
            'can_move_to_loadout' => $canMoveToLoadout,
            'has_loadout_started' => $loadoutState['has_loadout_started'],
            'loadout_initialized_at' => $loadoutState['loadout_initialized_at'],
            'loadout_states_by_warehouse' => $loadoutState['loadout_states_by_warehouse'],
            'has_duplicates' => $mergeCandidates->isNotEmpty(),
            'merge_candidates' => $mergeCandidates,
            'unpriced_product_names' => $unpricedProductNames,
            'invoice' => $invoiceData,
        ])->setEtag($etag);
    }

    /**
     * Initialize untouched warehouse-scoped loadout rows with full approved quantities.
     */
    public function initialize(ShopOrder $shopOrder, Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Automatic persisted loadout initialization is not enabled because it would update loadout rows and consume stock on editor open.',
        ], 409);
    }

    /**
     * Save loadout quantities & update stock ledger.
     */
    public function save(Request $request, ShopOrder $shopOrder): JsonResponse
    {
        $this->authorizeAccess($request);
        $probe = PerformanceProbe::start('loadout.save', [
            'route' => $request->route()?->getName(),
            'shop_order_id' => $shopOrder->id,
            'submitted_product_count' => collect($request->input('items', []))
                ->keys()
                ->merge(collect($request->input('item_unit_qtys', []))->keys())
                ->merge(collect($request->input('item_status', []))->keys())
                ->merge(collect($request->input('item_notes', []))->keys())
                ->unique()
                ->count(),
        ]);

        if (! in_array($shopOrder->delivery_status, ['pending_delivery', 'ready_for_dispatch'])) {
            $msg = $shopOrder->delivery_status === 'in_transit'
                ? 'This order is already out for delivery.'
                : 'This order is already delivered.';

            return response()->json(['success' => false, 'message' => $msg], 422);
        }

        $request->validate([
            'items' => ['nullable', 'array'],
            'items.*' => ['nullable', 'numeric', 'min:0'],
            'item_unit_qtys' => ['nullable', 'array'],
            'item_unit_qtys.*' => ['nullable', 'numeric', 'min:0'],
            'item_status' => ['nullable', 'array'],
            'item_notes' => ['nullable', 'array'],
        ]);

        $userId = (int) $request->user()->id;

        try {
            DB::transaction(function () use ($shopOrder, $request, $userId) {
                ShopOrder::lockForUpdate()->find($shopOrder->id);

                $anyItemLoaded = false;
                $itemsInput = $request->input('items', []);
                $unitQtysInput = $request->input('item_unit_qtys', []);

                $productIds = collect(array_keys($itemsInput))
                    ->merge(array_keys($unitQtysInput))
                    ->merge(array_keys($request->input('item_status', [])))
                    ->merge(array_keys($request->input('item_notes', [])))
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->sort()
                    ->values();

                $this->markLoadoutStartedForProducts($shopOrder, $productIds);

                foreach ($productIds as $productId) {
                    $actualWeight = isset($itemsInput[$productId]) && $itemsInput[$productId] !== ''
                        ? max(0.0, (float) $itemsInput[$productId])
                        : 0.0;

                    $submittedUnitQty = isset($unitQtysInput[$productId]) && $unitQtysInput[$productId] !== ''
                        ? max(0.0, (float) $unitQtysInput[$productId])
                        : null;

                    $rows = $shopOrder->items()
                        ->where('product_id', $productId)
                        ->lockForUpdate()
                        ->get();

                    if ($rows->isEmpty()) {
                        continue;
                    }

                    $totalApproved = $this->loadoutApprovedQuantity($rows);
                    $totalRequested = $this->loadoutRequestedQuantity($rows);
                    $firstRow = $rows->first();

                    $requestedUnitQty = (float) ($firstRow->requested_unit_quantity ?? 0.0);
                    $conversionToBase = (float) ($firstRow->requested_unit_conversion_to_base ?? 1.0);
                    $hasRequestedUnit = filled($firstRow->requested_unit)
                        && strtolower((string) $firstRow->requested_unit) !== 'kg';
                    $loadedOrderUnitQty = $submittedUnitQty ?? ($hasRequestedUnit ? $requestedUnitQty : null);
                    $convertedQty = round(max(0.0, (float) $loadedOrderUnitQty) * max(0.0, $conversionToBase), 3);

                    $submittedQty = $hasRequestedUnit
                        ? ($actualWeight > 0.0001 ? $actualWeight : $convertedQty)
                        : ($actualWeight > 0.0001
                            ? $actualWeight
                            : max(0.0, (float) ($itemsInput[$productId] ?? 0)));

                    $oldLoadedQty = (float) $rows->where('sorting_status', 'loaded')->sum('loaded_qty');
                    $diff = $submittedQty - $oldLoadedQty;

                    if ($diff > 0.001) {
                        $this->stockLedgerService->consumeStockForProductAllowingNegative(
                            $productId,
                            $diff,
                            $userId,
                            StockMovementType::Out,
                            "Loadout dispatch to delivery — Order: {$shopOrder->order_number}"
                        );
                    } elseif ($diff < -0.001) {
                        $lastOut = StockMovement::where('product_id', $productId)
                            ->where('type', StockMovementType::Out)
                            ->where('notes', 'like', "%Order: {$shopOrder->order_number}%")
                            ->latest()
                            ->first();

                        $batchId = $lastOut?->batch_id;
                        if (! $batchId) {
                            $anyBatch = StockBatch::where('product_id', $productId)->latest()->first();
                            $batchId = $anyBatch?->id;
                        }

                        if ($batchId) {
                            $batch = StockBatch::find($batchId);
                            StockMovement::create([
                                'batch_id' => $batchId,
                                'product_id' => $productId,
                                'created_by' => $userId,
                                'grade' => $lastOut?->grade ?? $firstRow->product_grade ?? 'A',
                                'type' => StockMovementType::SaleReversal,
                                'quantity' => abs($diff),
                                'cost_per_unit' => $lastOut?->cost_per_unit ?? 0.0,
                                'warehouse_id' => $batch?->warehouse_id,
                                'notes' => "Loadout adjustment (decrease) — Order: {$shopOrder->order_number}",
                            ]);
                        }
                    }

                    $unitSellingPrice = (float) ($firstRow->locked_selling_price ?? 0.0);

                    $basePriceData = [
                        'locked_price_group_id' => $firstRow->locked_price_group_id,
                        'locked_selling_price' => $firstRow->locked_selling_price,
                        'locked_price_source' => $firstRow->locked_price_source,
                        'unit_cost' => $firstRow->unit_cost,
                        'unit' => $firstRow->unit,
                        'requested_product_unit_id' => $firstRow->requested_product_unit_id,
                        'requested_unit' => $firstRow->requested_unit,
                        'requested_unit_label' => $firstRow->requested_unit_label,
                        'requested_unit_conversion_to_base' => $firstRow->requested_unit_conversion_to_base,
                        'product_grade' => $firstRow->product_grade ?? 'A',
                        'fulfillment_type' => $firstRow->fulfillment_type,
                    ];

                    $isNotAvailable = ($request->input("item_status.{$productId}") === 'not_available');
                    $discrepancyNote = $request->input("item_notes.{$productId}") ?? null;

                    $targetRows = [];

                    if ($isNotAvailable) {
                        $targetRows[] = array_merge($basePriceData, [
                            'requested_qty' => $totalRequested,
                            'approved_qty' => $totalApproved,
                            'loaded_qty' => 0.0,
                            'loaded_order_unit_qty' => $hasRequestedUnit ? 0.0 : null,
                            'requested_unit_quantity' => $requestedUnitQty,
                            'line_total' => round($totalApproved * $unitSellingPrice, 2),
                            'actual_weight' => null,
                            'delivered_qty' => 0.0,
                            'excess_qty' => 0.0,
                            'excess_value' => 0.0,
                            'loadout_discrepancy_type' => 'not_available',
                            'loadout_discrepancy_note' => $discrepancyNote ?: 'Marked as Not Available by warehouse',
                            'sorting_status' => 'not_available',
                            'is_sorted' => true,
                            'sorted_at' => now(),
                            'sorted_by' => $userId,
                        ]);
                    } else {
                        $excessQty = max(0.0, round($submittedQty - $totalApproved, 3));
                        $excessValue = round($excessQty * $unitSellingPrice, 2);

                        if ($submittedQty > 0) {
                            $anyItemLoaded = true;
                            $targetRows[] = array_merge($basePriceData, [
                                'requested_qty' => $totalRequested,
                                'approved_qty' => $totalApproved,
                                'loaded_qty' => $submittedQty,
                                'loaded_order_unit_qty' => $hasRequestedUnit ? ($loadedOrderUnitQty ?? round($submittedQty / $conversionToBase, 2)) : null,
                                'requested_unit_quantity' => $requestedUnitQty,
                                'line_total' => round($totalApproved * $unitSellingPrice, 2),
                                'actual_weight' => $actualWeight > 0.0001 ? $actualWeight : null,
                                'delivered_qty' => null,
                                'excess_qty' => $excessQty,
                                'excess_value' => $excessValue,
                                'loadout_discrepancy_type' => 'none',
                                'loadout_discrepancy_note' => null,
                                'sorting_status' => 'loaded',
                                'is_sorted' => true,
                                'sorted_at' => now(),
                                'sorted_by' => $userId,
                            ]);
                        }
                    }

                    $this->applyOrderItemRows($rows, $shopOrder->id, $productId, $targetRows);
                }

                $newStatus = $anyItemLoaded ? 'ready_for_dispatch' : 'pending_delivery';
                $shopOrder->update(['delivery_status' => $newStatus]);

                // Synchronize and reprice the invoice immediately on loadout save
                $shopOrder->loadMissing(['shop.priceGroup', 'items.product', 'invoice.items']);
                $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($shopOrder, $userId);
                if ($invoice && ! $invoice->isFinalLocked()) {
                    $this->shopInvoiceService->repriceInvoice(
                        $invoice,
                        $userId,
                        "Invoice recalculated during loadout save for order {$shopOrder->order_number}."
                    );
                }
            });
            $probe?->checkpoint('transaction_save_and_invoice_sync');
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first() ?? 'Validation error';

            return response()->json([
                'success' => false,
                'message' => $firstError,
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Save failed: '.$e->getMessage(),
            ], 500);
        }

        $duplicateCount = $this->duplicateMergeCandidates($shopOrder->fresh('items')->items)->count();
        $probe?->checkpoint('duplicate_check');
        $probe?->finish([
            'duplicate_count' => $duplicateCount,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Loadout saved successfully.',
            'duplicate_count' => $duplicateCount,
            'merge_needed' => $duplicateCount > 0,
        ]);
    }

    /**
     * Transition order to delivery or partial delivery.
     */
    public function moveToDelivery(ShopOrder $shopOrder, Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $partialDelivery = $request->boolean('partial', false);

        if ($this->duplicateMergeCandidates($shopOrder->items)->isNotEmpty()) {
            return response()->json(['success' => false, 'message' => 'Duplicate product rows found. Merge duplicates first.'], 422);
        }

        if ($shopOrder->delivery_status === 'in_transit') {
            return response()->json(['success' => false, 'message' => 'Order is already in transit.'], 422);
        }

        if ($shopOrder->delivery_status !== 'ready_for_dispatch') {
            return response()->json(['success' => false, 'message' => 'Order is not ready for delivery.'], 422);
        }

        $remainingItems = $shopOrder->items()
            ->where(function ($query): void {
                $query->where('sorting_status', '!=', 'loaded')
                    ->orWhereColumn('loaded_qty', '<', 'approved_qty');
            })
            ->get();

        if (! $partialDelivery && $remainingItems->isNotEmpty()) {
            return response()->json(['success' => false, 'message' => 'Order has short-loaded items. Use partial delivery.'], 422);
        }

        if ($partialDelivery && $remainingItems->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'All items are fully loaded. Use regular delivery.'], 422);
        }

        try {
            DB::transaction(function () use ($shopOrder, $request) {
                ShopOrder::lockForUpdate()->find($shopOrder->id);

                $shopOrder->update([
                    'delivery_status' => 'in_transit',
                    'is_allocation_completed' => true,
                ]);

                $userId = (int) $request->user()->id;
                $shopOrder->loadMissing(['shop.priceGroup', 'items.product', 'invoice.items']);
                $invoice = $this->shopInvoiceService->synchronizeOrderInvoice($shopOrder, $userId);

                if (! $invoice->isFinalLocked()) {
                    $this->shopInvoiceService->repriceInvoice(
                        $invoice,
                        $userId,
                        "Invoice recalculated during delivery transition for order {$shopOrder->order_number}."
                    );
                }
            });
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Move to delivery failed: '.$e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $partialDelivery ? 'Moved to partial delivery.' : 'Moved to delivery successfully.',
        ]);
    }

    /**
     * Transition order to partial delivery.
     */
    public function moveToPartialDelivery(ShopOrder $shopOrder, Request $request): JsonResponse
    {
        $request->merge(['partial' => true]);

        return $this->moveToDelivery($shopOrder, $request);
    }

    /**
     * Reopen order for loadout edits (Move back to Loadout).
     */
    public function moveToLoadout(ShopOrder $shopOrder, Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        if ($shopOrder->delivery_status === 'delivered') {
            return response()->json(['success' => false, 'message' => 'Delivered orders cannot be reopened.'], 422);
        }

        DB::transaction(function () use ($shopOrder) {
            $shopOrder->update([
                'delivery_status' => 'ready_for_dispatch',
                'is_allocation_completed' => false,
                'delivery_notes' => 'Re-opened for loadout quantity correction.',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Order moved back to loadout. You can now edit loadout quantities and save updates.',
        ]);
    }

    private function authorizeAccess(Request $request): void
    {
        if (
            ! $request->user()->hasRole('warehouse_receiver')
            && ! $request->user()->hasRole('admin')
            && ! $request->user()->hasRole('purchaser')
            && ! $request->user()->can('warehouse.receive.confirm')
        ) {
            abort(403, 'Unauthorized access.');
        }
    }

    /**
     * @return array<int>
     */
    private function warehouseIdsForOrder(ShopOrder $shopOrder, ?int $warehouseId = null): array
    {
        if ($warehouseId !== null) {
            return [$warehouseId];
        }

        return $shopOrder->items()
            ->join('products', 'products.id', '=', 'shop_order_items.product_id')
            ->whereNotNull('products.default_warehouse_id')
            ->distinct()
            ->orderBy('products.default_warehouse_id')
            ->pluck('products.default_warehouse_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    private function lockOrCreateLoadoutState(int $shopOrderId, int $warehouseId): ShopOrderLoadoutState
    {
        $state = ShopOrderLoadoutState::query()
            ->where('shop_order_id', $shopOrderId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if ($state) {
            return $state;
        }

        ShopOrderLoadoutState::query()->create([
            'shop_order_id' => $shopOrderId,
            'warehouse_id' => $warehouseId,
        ]);

        return ShopOrderLoadoutState::query()
            ->where('shop_order_id', $shopOrderId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function markLoadoutStartedForProducts(ShopOrder $shopOrder, Collection $productIds): void
    {
        $ids = $productIds
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $warehouseIds = Product::query()
            ->whereIn('id', $ids)
            ->whereNotNull('default_warehouse_id')
            ->distinct()
            ->pluck('default_warehouse_id')
            ->map(fn ($id): int => (int) $id);

        foreach ($warehouseIds as $warehouseId) {
            $state = $this->lockOrCreateLoadoutState($shopOrder->id, $warehouseId);
            if ($state->started_at === null) {
                $state->forceFill(['started_at' => now()])->save();
            }
        }
    }

    /**
     * @return array{has_loadout_started: bool, loadout_initialized_at: ?string, loadout_states_by_warehouse: array<int, array<string, mixed>>}
     */
    private function loadoutStateSummary(ShopOrder $shopOrder, ?int $selectedWarehouseId): array
    {
        $warehouseIds = $this->warehouseIdsForOrder($shopOrder, $selectedWarehouseId);
        $states = ShopOrderLoadoutState::query()
            ->where('shop_order_id', $shopOrder->id)
            ->whereIn('warehouse_id', $warehouseIds)
            ->get()
            ->keyBy('warehouse_id');

        $statesByWarehouse = [];
        $hasStarted = false;
        $initializedAt = null;

        foreach ($warehouseIds as $warehouseId) {
            $state = $states->get($warehouseId);
            $startedAt = $state?->started_at?->toIso8601String();
            $warehouseInitializedAt = $state?->initialized_at?->toIso8601String();
            $started = $startedAt !== null
                || $warehouseInitializedAt !== null
                || $this->warehouseHasLegacyLoadoutActivity($shopOrder, $warehouseId);
            $hasStarted = $hasStarted || $started;
            $initializedAt ??= $warehouseInitializedAt;
            $statesByWarehouse[$warehouseId] = [
                'warehouse_id' => $warehouseId,
                'has_loadout_started' => $started,
                'started_at' => $startedAt,
                'loadout_initialized_at' => $warehouseInitializedAt,
            ];
        }

        return [
            'has_loadout_started' => $hasStarted,
            'loadout_initialized_at' => $initializedAt,
            'loadout_states_by_warehouse' => $statesByWarehouse,
        ];
    }

    private function warehouseHasLegacyLoadoutActivity(ShopOrder $shopOrder, int $warehouseId): bool
    {
        return $shopOrder->items()
            ->whereHas('product', fn ($query) => $query->where('default_warehouse_id', $warehouseId))
            ->where(function ($query): void {
                $query
                    ->where('sorting_status', '!=', 'allocated')
                    ->orWhere('loaded_qty', '>', 0)
                    ->orWhere('loaded_order_unit_qty', '>', 0)
                    ->orWhereNotNull('loadout_discrepancy_note')
                    ->orWhere(function ($inner): void {
                        $inner->whereNotNull('loadout_discrepancy_type')
                            ->where('loadout_discrepancy_type', '!=', 'none');
                    });
            })
            ->exists();
    }

    private function applyOrderItemRows(Collection $existingRows, int $shopOrderId, int $productId, array $targetRows): void
    {
        $existingRows = $existingRows->values();

        foreach ($targetRows as $index => $attributes) {
            $attributes['shop_order_id'] = $shopOrderId;
            $attributes['product_id'] = $productId;

            $existing = $existingRows->get($index);

            if ($existing) {
                $existing->update($attributes);

                continue;
            }

            ShopOrderItem::create($attributes);
        }

        $existingRows->slice(count($targetRows))
            ->each(fn (ShopOrderItem $row) => $row->delete());
    }

    private function loadoutApprovedQuantity(Collection $items): float
    {
        $loadedRows = $items->where('sorting_status', 'loaded');
        $openApproved = (float) $items
            ->reject(fn (ShopOrderItem $item): bool => $item->sorting_status === 'loaded')
            ->sum('approved_qty');

        if ($loadedRows->isNotEmpty() && $openApproved > 0.001) {
            $loadedApproved = (float) $loadedRows->sum('approved_qty');
            $loadedQty = (float) $loadedRows->sum('loaded_qty');

            return round(max($loadedApproved, $loadedQty + $openApproved), 3);
        }

        return round((float) $items->sum('approved_qty'), 3);
    }

    private function loadoutRequestedQuantity(Collection $items): float
    {
        $source = $items->first(function (ShopOrderItem $item): bool {
            return (float) ($item->requested_unit_quantity ?? 0) > 0
                && (float) ($item->requested_unit_conversion_to_base ?? 0) > 0;
        });

        if (! $source) {
            return round((float) $items->sum('requested_qty'), 3);
        }

        return round((float) $source->requested_unit_quantity * (float) $source->requested_unit_conversion_to_base, 3);
    }

    private function duplicateMergeCandidates(Collection $rows): Collection
    {
        return $rows
            ->groupBy('product_id')
            ->filter(fn (Collection $productRows): bool => $this->needsMerge($productRows))
            ->map(function (Collection $productRows, int|string $productId): array {
                $first = $productRows->first();

                return [
                    'product_id' => (int) $productId,
                    'product_name' => (string) ($first?->product?->name ?? 'Unknown Product'),
                    'row_count' => $productRows->count(),
                ];
            })
            ->values();
    }

    private function needsMerge(Collection $rows): bool
    {
        if ($rows->count() <= 1) {
            return false;
        }

        $loadedRows = $rows->where('sorting_status', 'loaded')->count();
        $allocatedRows = $rows->where('sorting_status', 'allocated')->count();
        $notAvailableRows = $rows->where('sorting_status', 'not_available')->count();

        if ($notAvailableRows > 0) {
            return $rows->count() > 1;
        }

        if ($rows->count() > 2) {
            return true;
        }

        if ($loadedRows > 1 || $allocatedRows > 1) {
            return true;
        }

        return ! ($rows->count() === 2 && $loadedRows === 1 && $allocatedRows === 1);
    }

    /**
     * Get available products that can be added as addons for this loadout order.
     */
    public function addonProducts(ShopOrder $shopOrder, Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $existingProductIds = $shopOrder->items
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $categories = Category::query()
            ->where('is_active', true)
            ->with(['products' => function ($query) use ($existingProductIds): void {
                $query
                    ->where('is_active', true)
                    ->whereNotIn('id', $existingProductIds)
                    ->ordered();
            }])
            ->orderBy('name')
            ->get()
            ->filter(fn (Category $category): bool => $category->products->isNotEmpty())
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'products' => $category->products->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'unit' => $product->unit ?? 'KG',
                ])->values()->toArray(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'categories' => $categories,
        ]);
    }

    /**
     * Add an addon product to the loadout order.
     */
    public function storeAddon(ShopOrder $shopOrder, Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        if ($shopOrder->delivery_status === 'delivered') {
            return response()->json(['success' => false, 'message' => 'Cannot add addon to a delivered order.'], 422);
        }

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        $product = Product::query()
            ->active()
            ->findOrFail((int) $validated['product_id']);
        $quantity = round((float) $validated['quantity'], 3);

        if ($shopOrder->items()->where('product_id', $product->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => "{$product->name} is already in this loadout order. Edit the existing line instead.",
            ], 422);
        }

        $shopOrder->loadMissing('shop');
        $price = $this->priceBoardService->sellingPriceFor($product, $shopOrder->shop, ProductGrade::GradeA);

        $item = DB::transaction(function () use ($shopOrder, $product, $quantity, $price, $request) {
            $userId = (int) $request->user()->id;

            $item = ShopOrderItem::create([
                'shop_order_id' => $shopOrder->id,
                'product_id' => $product->id,
                'product_grade' => ProductGrade::GradeA->value,
                'requested_qty' => $quantity,
                'approved_qty' => $quantity,
                'loaded_qty' => $quantity,
                'loaded_order_unit_qty' => $quantity,
                'unit' => $product->unit ?: 'KG',
                'locked_price_group_id' => $price['group']->id,
                'locked_selling_price' => $price['price'],
                'locked_price_source' => $price['source'],
                'line_total' => round($quantity * (float) $price['price'], 2),
                'notes' => 'Addon item added from warehouse loadout.',
                'fulfillment_type' => 'warehouse',
                'sorting_status' => 'loaded',
                'is_sorted' => true,
            ]);

            $this->stockLedgerService->consumeStockForProductAllowingNegative(
                (int) $product->id,
                $quantity,
                $userId,
                StockMovementType::Out,
                "Loadout dispatch to delivery — Order: {$shopOrder->order_number}"
            );

            $this->markLoadoutStartedForProducts($shopOrder, collect([(int) $product->id]));

            return $item;
        });

        return response()->json([
            'success' => true,
            'message' => "{$product->name} added as addon product successfully and marked loaded. Inventory updated.",
            'item' => $item,
        ]);
    }

    /**
     * Load all products in the shop order at 100% of approved quantity.
     */
    public function loadAll(ShopOrder $shopOrder, Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        if (! in_array($shopOrder->delivery_status, ['pending_delivery', 'ready_for_dispatch'])) {
            return response()->json(['success' => false, 'message' => 'Order is not in editable status.'], 422);
        }

        $userId = (int) $request->user()->id;
        $warehouseId = $request->input('warehouse_id') ? (int) $request->input('warehouse_id') : null;

        try {
            DB::transaction(function () use ($shopOrder, $userId, $warehouseId) {
                ShopOrder::lockForUpdate()->find($shopOrder->id);

                $rows = $shopOrder->items()
                    ->when($warehouseId, function ($query) use ($warehouseId) {
                        $query->whereHas('product', fn ($q) => $q->where('default_warehouse_id', $warehouseId));
                    })
                    ->lockForUpdate()
                    ->get();
                $grouped = $rows->groupBy('product_id')->sortKeys();

                foreach ($grouped as $productId => $productRows) {
                    $totalApproved = $this->loadoutApprovedQuantity($productRows);
                    $firstRow = $productRows->first();

                    $conversionToBase = (float) ($firstRow->requested_unit_conversion_to_base ?? 1.0);
                    $hasRequestedUnit = filled($firstRow->requested_unit)
                        && strtolower((string) $firstRow->requested_unit) !== 'kg';

                    $requestedUnitQty = $hasRequestedUnit && $conversionToBase > 0
                        ? round($totalApproved / $conversionToBase, 2)
                        : $totalApproved;

                    $oldLoadedQty = (float) $productRows->where('sorting_status', 'loaded')->sum('loaded_qty');
                    $diff = $totalApproved - $oldLoadedQty;

                    if ($diff > 0.001) {
                        $this->stockLedgerService->consumeStockForProductAllowingNegative(
                            $productId,
                            $diff,
                            $userId,
                            StockMovementType::Out,
                            "Loadout dispatch to delivery — Order: {$shopOrder->order_number}"
                        );
                    } elseif ($diff < -0.001) {
                        $lastOut = StockMovement::where('product_id', $productId)
                            ->where('type', StockMovementType::Out)
                            ->where('notes', 'like', "%Order: {$shopOrder->order_number}%")
                            ->latest()
                            ->first();

                        $batchId = $lastOut?->batch_id;
                        if (! $batchId) {
                            $anyBatch = StockBatch::where('product_id', $productId)->latest()->first();
                            $batchId = $anyBatch?->id;
                        }

                        if ($batchId) {
                            StockMovement::create([
                                'batch_id' => $batchId,
                                'product_id' => $productId,
                                'created_by' => $userId,
                                'grade' => $lastOut?->grade ?? $firstRow->product_grade ?? 'A',
                                'type' => StockMovementType::SaleReversal,
                                'quantity' => abs($diff),
                                'cost_per_unit' => $lastOut?->cost_per_unit ?? 0.0,
                                'warehouse_id' => $lastOut?->warehouse_id ?? ($batchId ? StockBatch::find($batchId)?->warehouse_id : null),
                                'notes' => "Loadout adjustment (decrease) — Order: {$shopOrder->order_number}",
                            ]);
                        }
                    }

                    $unitSellingPrice = (float) ($firstRow->locked_selling_price ?? 0.0);

                    $basePriceData = [
                        'locked_price_group_id' => $firstRow->locked_price_group_id,
                        'locked_selling_price' => $firstRow->locked_selling_price,
                        'locked_price_source' => $firstRow->locked_price_source,
                        'unit_cost' => $firstRow->unit_cost,
                        'unit' => $firstRow->unit,
                        'requested_product_unit_id' => $firstRow->requested_product_unit_id,
                        'requested_unit' => $firstRow->requested_unit,
                        'requested_unit_label' => $firstRow->requested_unit_label,
                        'requested_unit_conversion_to_base' => $firstRow->requested_unit_conversion_to_base,
                        'product_grade' => $firstRow->product_grade ?? 'A',
                        'fulfillment_type' => $firstRow->fulfillment_type,
                    ];

                    $targetRows = [
                        array_merge($basePriceData, [
                            'requested_qty' => $totalApproved,
                            'approved_qty' => $totalApproved,
                            'loaded_qty' => $totalApproved,
                            'loaded_order_unit_qty' => $hasRequestedUnit ? $requestedUnitQty : null,
                            'requested_unit_quantity' => $requestedUnitQty,
                            'line_total' => round($totalApproved * $unitSellingPrice, 2),
                            'actual_weight' => $hasRequestedUnit ? null : $totalApproved,
                            'delivered_qty' => null,
                            'excess_qty' => 0.0,
                            'excess_value' => 0.0,
                            'loadout_discrepancy_type' => 'none',
                            'loadout_discrepancy_note' => null,
                            'sorting_status' => 'loaded',
                            'is_sorted' => true,
                            'sorted_at' => now(),
                            'sorted_by' => $userId,
                        ]),
                    ];

                    $this->applyOrderItemRows($productRows, $shopOrder->id, $productId, $targetRows);
                }

                $shopOrder->update(['delivery_status' => 'ready_for_dispatch']);
            });
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Load All failed: '.$e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'All products loaded at full approved quantities successfully.',
        ]);
    }

    private function loadoutEditorEtag(ShopOrder $shopOrder, ?int $selectedWarehouseId): string
    {
        $itemState = $shopOrder->items()
            ->selectRaw('COUNT(*) as item_count, MAX(updated_at) as max_updated_at')
            ->toBase()
            ->first();
        $itemVersion = ($itemState?->item_count ?? 0).'|'.($itemState?->max_updated_at ?? '');

        $invoiceVersion = (string) DB::table('shop_invoices')
            ->leftJoin('shop_invoice_items', 'shop_invoice_items.shop_invoice_id', '=', 'shop_invoices.id')
            ->where('shop_invoices.shop_order_id', $shopOrder->id)
            ->selectRaw('MAX(GREATEST(shop_invoices.updated_at, COALESCE(shop_invoice_items.updated_at, shop_invoices.updated_at))) as max_updated_at')
            ->value('max_updated_at');

        $stateVersion = (string) ShopOrderLoadoutState::query()
            ->where('shop_order_id', $shopOrder->id)
            ->when($selectedWarehouseId, fn ($query) => $query->where('warehouse_id', $selectedWarehouseId))
            ->max('updated_at');

        return sha1(implode('|', [
            'loadout-editor-v1',
            $shopOrder->id,
            $selectedWarehouseId ?? 'all',
            $shopOrder->updated_at?->toJSON(),
            $itemVersion,
            $invoiceVersion,
            $stateVersion,
        ]));
    }

    private function requestEtagsContain(Request $request, string $etag): bool
    {
        $header = (string) $request->headers->get('If-None-Match', '');

        if ($header === '') {
            return false;
        }

        return collect(explode(',', $header))
            ->map(fn (string $value): string => trim(str_replace('W/', '', $value), " \t\n\r\0\x0B\""))
            ->contains($etag);
    }
}
