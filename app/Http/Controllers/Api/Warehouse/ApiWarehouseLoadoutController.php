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
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Services\Inventory\StockLedgerService;
use App\Services\Pricing\PriceBoardService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\ShopInvoices\ShopInvoiceService;
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

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'date' => ['nullable', 'date'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'source' => ['nullable', 'string', 'in:all,shop,direct'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $selectedDate = $validated['date'] ?? app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();
        $selectedShopId = isset($validated['shop_id']) ? (int) $validated['shop_id'] : null;
        $selectedSource = (string) ($validated['source'] ?? 'all');
        $selectedCategoryId = isset($validated['category_id']) ? (int) $validated['category_id'] : null;

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
            ->when($selectedSource === 'direct', fn ($query) => $query->where('order_source', 'admin_direct_purchase'))
            ->when($selectedCategoryId, function ($query) use ($selectedCategoryId): void {
                $query->whereHas('items.product', fn ($productQuery) => $productQuery->where('category_id', $selectedCategoryId));
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

        $shops = Shop::query()->whereHas('orders')->orderBy('name')->get(['id', 'name', 'code', 'warehouse_tag']);
        $categories = Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

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

        $shopOrder->load(['shop', 'items.product.category', 'items.product.orderUnits', 'deliveredBy', 'invoice.items']);

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

                return [
                    'product_id' => $productId,
                    'product_name' => $firstItem->product?->name ?? 'Unknown Product',
                    'product_sku' => $firstItem->product?->sku ?? '',
                    'category_name' => $firstItem->product?->category?->name ?? 'General',
                    'unit' => $firstItem->product->unit ?? 'KG',
                    'product_grade' => $firstItem->product_grade ?? 'A',
                    'total_approved' => $totalApproved,
                    'total_loaded' => $totalLoaded,
                    'loaded_order_unit_qty' => $totalLoadedOrderUnit,
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
            ->sortBy(fn (array $group) => \App\Models\Product::sortableSku((string) ($group['product_sku'] ?? '')))
            ->values();

        $canEdit = $shopOrder->delivery_status !== 'delivered';
        $anyLoaded = $shopOrder->items->where('sorting_status', 'loaded')->count() > 0;
        $hasRemainingBalance = $productGroups->contains(fn (array $group): bool => (float) $group['total_balance'] > 0.001);
        $canMoveToDelivery = $shopOrder->delivery_status === 'ready_for_dispatch' && $anyLoaded && ! $hasRemainingBalance;
        $canMoveToPartialDelivery = $shopOrder->delivery_status === 'ready_for_dispatch' && $anyLoaded && $hasRemainingBalance;
        $canMoveToLoadout = $shopOrder->delivery_status !== 'delivered' && $shopOrder->delivery_status !== 'pending_delivery';
        $mergeCandidates = $this->duplicateMergeCandidates($shopOrder->items);
        $unpricedProductNames = $this->shopInvoiceService->getUnpricedOrderItemNames($shopOrder);

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
                if ($item->sorting_status !== 'loaded') continue;
                
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
                'invoice_number' => 'DRAFT-' . $shopOrder->order_number,
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

        return response()->json([
            'success' => true,
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
            'has_duplicates' => $mergeCandidates->isNotEmpty(),
            'merge_candidates' => $mergeCandidates,
            'unpriced_product_names' => $unpricedProductNames,
            'invoice' => $invoiceData,
        ]);
    }

    /**
     * Save loadout quantities & update stock ledger.
     */
    public function save(Request $request, ShopOrder $shopOrder): JsonResponse
    {
        $this->authorizeAccess($request);

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
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

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
                        $rowReqUnitQty = $hasRequestedUnit && $conversionToBase > 0
                            ? round($totalApproved / $conversionToBase, 2)
                            : $totalApproved;

                        $targetRows[] = array_merge($basePriceData, [
                            'requested_qty' => $totalApproved,
                            'approved_qty' => $totalApproved,
                            'loaded_qty' => 0.0,
                            'loaded_order_unit_qty' => $hasRequestedUnit ? 0.0 : null,
                            'requested_unit_quantity' => $rowReqUnitQty,
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

                        $remaining = round($totalApproved - $submittedQty, 3);

                        if ($submittedQty > 0) {
                            $anyItemLoaded = true;
                            $loadedQtyToRecord = $remaining > 0.001 ? min($submittedQty, $totalApproved) : $totalApproved;
                            $loadedReqUnitQty = $hasRequestedUnit && $conversionToBase > 0
                                ? round($loadedQtyToRecord / $conversionToBase, 2)
                                : $loadedQtyToRecord;

                            $targetRows[] = array_merge($basePriceData, [
                                'requested_qty' => $loadedQtyToRecord,
                                'approved_qty' => $loadedQtyToRecord,
                                'loaded_qty' => $submittedQty,
                                'loaded_order_unit_qty' => $hasRequestedUnit ? ($loadedOrderUnitQty ?? round($submittedQty / $conversionToBase, 2)) : null,
                                'requested_unit_quantity' => $loadedReqUnitQty,
                                'line_total' => round($loadedQtyToRecord * $unitSellingPrice, 2),
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

                        if ($remaining > 0.001) {
                            $remainderReqUnitQty = $hasRequestedUnit && $conversionToBase > 0
                                ? round($remaining / $conversionToBase, 2)
                                : $remaining;

                            $targetRows[] = array_merge($basePriceData, [
                                'requested_qty' => $remaining,
                                'approved_qty' => $remaining,
                                'loaded_qty' => null,
                                'loaded_order_unit_qty' => null,
                                'requested_unit_quantity' => $remainderReqUnitQty,
                                'line_total' => round($remaining * $unitSellingPrice, 2),
                                'actual_weight' => null,
                                'delivered_qty' => null,
                                'excess_qty' => 0.0,
                                'excess_value' => 0.0,
                                'loadout_discrepancy_type' => 'none',
                                'loadout_discrepancy_note' => null,
                                'sorting_status' => 'allocated',
                                'is_sorted' => false,
                                'sorted_at' => null,
                                'sorted_by' => null,
                            ]);
                        }
                    }

                    $this->applyOrderItemRows($rows, $shopOrder->id, $productId, $targetRows);
                }

                $newStatus = $anyItemLoaded ? 'ready_for_dispatch' : 'pending_delivery';
                $shopOrder->update(['delivery_status' => $newStatus]);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Save failed: '.$e->getMessage(),
            ], 500);
        }

        $duplicateCount = $this->duplicateMergeCandidates($shopOrder->fresh('items')->items)->count();

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

        try {
            DB::transaction(function () use ($shopOrder, $partialDelivery, $request) {
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
            && ! $request->user()->can('warehouse.receive.confirm')
        ) {
            abort(403, 'Unauthorized access.');
        }
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
        $originalApproved = $this->originalRequestedQuantity($items);

        if ($originalApproved > 0.001) {
            return $originalApproved;
        }

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

    private function originalRequestedQuantity(Collection $items): float
    {
        $source = $items->first(function (ShopOrderItem $item): bool {
            return (float) ($item->requested_unit_quantity ?? 0) > 0
                && (float) ($item->requested_unit_conversion_to_base ?? 0) > 0;
        });

        if (! $source) {
            return 0.0;
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
}
