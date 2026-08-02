<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Warehouse;

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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WarehouseLoadoutController extends Controller
{
    public function __construct(
        private readonly StockLedgerService $stockLedgerService,
        private readonly PriceBoardService $priceBoardService,
    ) {}

    /**
     * Loadout list — show pending_delivery and ready_for_dispatch orders.
     */
    public function index(Request $request): View
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
                $shopOrder->loaded_count = $shopOrder->items->where('sorting_status', 'loaded')->count();
                $shopOrder->total_count = $shopOrder->items->count();

                return $shopOrder;
            });

        $shops = Shop::query()
            ->whereHas('orders')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'warehouse_tag']);
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('warehouse.loadout.index', compact(
            'orders',
            'search',
            'selectedDate',
            'selectedShopId',
            'selectedSource',
            'selectedCategoryId',
            'shops',
            'categories',
        ));
    }

    /**
     * Show loadout detail for a single shop order.
     * Items are grouped by product for the UI.
     */
    public function show(ShopOrder $shopOrder, Request $request): View
    {
        $this->authorizeAccess($request);

        $shopOrder->load(['shop', 'items.product.category', 'deliveredBy']);

        // Group items by product — UI shows one merged row per product
        $productGroups = $shopOrder->items
            ->groupBy('product_id')
            ->map(function ($items) {
                $productId = $items->first()->product_id;
                $totalApproved = $this->loadoutApprovedQuantity($items);
                $totalLoaded = (float) $items->where('sorting_status', 'loaded')->sum('loaded_qty');
                $totalBalance = max(0.0, round($totalApproved - $totalLoaded, 3));
                $available = round($this->stockLedgerService->availableSortedStockForProduct($productId) + $totalLoaded, 3);

                $firstItem = $items->first();
                $loadedItem = $items->firstWhere('sorting_status', 'loaded');
                $hasSecondaryUnit = ! empty($firstItem->requested_unit) && strtolower($firstItem->requested_unit) !== 'kg';

                return [
                    'product_id' => $productId,
                    'product' => $firstItem->product,
                    'unit' => $firstItem->unit ?? 'KG',
                    'product_grade' => $firstItem->product_grade ?? 'A',
                    'total_approved' => $totalApproved,
                    'total_loaded' => $totalLoaded,
                    'loaded_order_unit_qty' => (float) ($loadedItem->loaded_order_unit_qty ?? $firstItem->requested_unit_quantity ?? 1.0),
                    'has_secondary_unit' => $hasSecondaryUnit,
                    'requested_unit_quantity' => (float) ($firstItem->requested_unit_quantity ?? 1.0),
                    'requested_unit_label' => $firstItem->requested_unit_label ?? strtoupper($firstItem->requested_unit ?? ''),
                    'requested_unit_conversion_to_base' => (float) ($firstItem->requested_unit_conversion_to_base ?? 1.0),
                    'total_balance' => $totalBalance,
                    'available_stock' => $available,
                    'is_fully_loaded' => $totalLoaded > 0.0 && $totalBalance <= 0.001,
                    'is_partially_loaded' => $totalLoaded > 0.0 && $totalBalance > 0.001,
                    'items' => $items,
                ];
            })
            ->sortBy(fn (array $group) => \App\Models\Product::sortableSku((string) ($group['product']?->sku ?? '')))
            ->values();

        $canEdit = $shopOrder->delivery_status !== 'delivered';
        $anyLoaded = $shopOrder->items->where('sorting_status', 'loaded')->count() > 0;
        $hasRemainingBalance = $productGroups->contains(fn (array $group): bool => (float) $group['total_balance'] > 0.001);
        $canMoveToDelivery = $shopOrder->delivery_status === 'ready_for_dispatch' && $anyLoaded && ! $hasRemainingBalance;
        $canMoveToPartialDelivery = $shopOrder->delivery_status === 'ready_for_dispatch' && $anyLoaded && $hasRemainingBalance;
        $canMoveToLoadout = $shopOrder->delivery_status !== 'delivered' && $shopOrder->delivery_status !== 'pending_delivery';

        return view('warehouse.loadout.show', compact(
            'shopOrder',
            'productGroups',
            'canEdit',
            'anyLoaded',
            'canMoveToDelivery',
            'canMoveToPartialDelivery',
            'hasRemainingBalance',
            'canMoveToLoadout',
        ));
    }

    public function createAddon(ShopOrder $shopOrder, Request $request): View
    {
        $this->authorizeAccess($request);
        $this->ensureLoadoutEditable($shopOrder);

        $shopOrder->load(['shop', 'items.product']);

        $existingProductIds = $shopOrder->items->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->all();
        $productsByCategory = Category::query()
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
            ->values();

        return view('warehouse.loadout.addon', compact('shopOrder', 'productsByCategory'));
    }

    public function storeAddon(ShopOrder $shopOrder, Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $this->ensureLoadoutEditable($shopOrder);

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        $product = Product::query()
            ->active()
            ->findOrFail((int) $validated['product_id']);
        $quantity = round((float) $validated['quantity'], 3);

        if ($shopOrder->items()->where('product_id', $product->id)->exists()) {
            return redirect()
                ->route('warehouse.loadout.show', $shopOrder)
                ->withErrors(["{$product->name} is already in this loadout order. Edit the existing line instead."]);
        }

        $shopOrder->loadMissing('shop');
        $price = $this->priceBoardService->sellingPriceFor($product, $shopOrder->shop, ProductGrade::GradeA);

        ShopOrderItem::create([
            'shop_order_id' => $shopOrder->id,
            'product_id' => $product->id,
            'product_grade' => ProductGrade::GradeA->value,
            'requested_qty' => $quantity,
            'approved_qty' => $quantity,
            'unit' => $product->unit ?: 'KG',
            'locked_price_group_id' => $price['group']->id,
            'locked_selling_price' => $price['price'],
            'locked_price_source' => $price['source'],
            'line_total' => round($quantity * (float) $price['price'], 2),
            'notes' => 'Addon item added from warehouse loadout.',
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'allocated',
            'is_sorted' => false,
        ]);

        return redirect()
            ->route('warehouse.loadout.show', $shopOrder)
            ->with('success', "{$product->name} added to the purchaser order. You can now load it normally.");
    }

    /**
     * Save loadout quantities and update inventory immediately.
     * Updates loaded_qty per product, handles partial splits.
     * Editable while pending_delivery or ready_for_dispatch.
     */
    public function save(Request $request, ShopOrder $shopOrder): RedirectResponse|JsonResponse
    {
        $this->authorizeAccess($request);

        if (! in_array($shopOrder->delivery_status, ['pending_delivery', 'ready_for_dispatch'])) {
            $msg = $shopOrder->delivery_status === 'in_transit'
                ? 'This order is already out for delivery.'
                : 'This order is already delivered.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return redirect()->back()->withErrors([$msg]);
        }

        $request->validate([
            'items' => ['nullable', 'array'],
            'items.*' => ['nullable', 'numeric', 'min:0'],
            'item_unit_qtys' => ['nullable', 'array'],
            'item_unit_qtys.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $userId = (int) $request->user()->id;

        try {
            DB::transaction(function () use ($shopOrder, $request, $userId) {
                // Lock the order row against concurrent saves
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

                    // Lock rows for this product in this order
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
                    $submittedQty = $actualWeight > 0.0001
                        ? $actualWeight
                        : ($hasRequestedUnit
                            ? round(max(0.0, (float) $loadedOrderUnitQty) * max(0.0, $conversionToBase), 3)
                            : max(0.0, (float) ($itemsInput[$productId] ?? 0)));

                    // Calculate difference-based deduction
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

                    $priceData = [
                        'locked_price_group_id' => $firstRow->locked_price_group_id,
                        'locked_selling_price' => $firstRow->locked_selling_price,
                        'locked_price_source' => $firstRow->locked_price_source,
                        'line_total' => $firstRow->line_total,
                        'unit_cost' => $firstRow->unit_cost,
                        'unit' => $firstRow->unit,
                        'requested_product_unit_id' => $firstRow->requested_product_unit_id,
                        'requested_unit' => $firstRow->requested_unit,
                        'requested_unit_label' => $firstRow->requested_unit_label,
                        'requested_unit_quantity' => $firstRow->requested_unit_quantity,
                        'requested_unit_conversion_to_base' => $firstRow->requested_unit_conversion_to_base,
                        'product_grade' => $firstRow->product_grade ?? 'A',
                        'fulfillment_type' => $firstRow->fulfillment_type,
                    ];

                    $shopOrder->items()->where('product_id', $productId)->delete();

                    $isNotAvailable = ($request->input("item_status.{$productId}") === 'not_available');
                    $discrepancyNote = $request->input("item_notes.{$productId}") ?? null;

                    if ($isNotAvailable) {
                        ShopOrderItem::create(array_merge($priceData, [
                            'shop_order_id' => $shopOrder->id,
                            'product_id' => $productId,
                            'requested_qty' => $firstRow->requested_qty ?? $totalApproved,
                            'approved_qty' => $totalApproved,
                            'loaded_qty' => 0.0,
                            'loaded_order_unit_qty' => $hasRequestedUnit ? 0.0 : null,
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
                        ]));
                    } else {
                        $excessQty = max(0.0, round($submittedQty - $totalApproved, 3));
                        $excessValue = round($excessQty * (float) ($firstRow->locked_selling_price ?? 0.0), 2);

                        $remaining = round($totalApproved - $submittedQty, 3);

                        if ($submittedQty > 0) {
                            $anyItemLoaded = true;

                            ShopOrderItem::create(array_merge($priceData, [
                                'shop_order_id' => $shopOrder->id,
                                'product_id' => $productId,
                                'requested_qty' => $firstRow->requested_qty ?? $totalApproved,
                                'approved_qty' => $remaining > 0.001 ? min($submittedQty, $totalApproved) : $totalApproved,
                                'loaded_qty' => $submittedQty,
                                'loaded_order_unit_qty' => $hasRequestedUnit ? ($loadedOrderUnitQty ?? 0.0) : null,
                                'actual_weight' => $actualWeight > 0.0001 ? $actualWeight : null,
                                'excess_qty' => $excessQty,
                                'excess_value' => $excessValue,
                                'sorting_status' => 'loaded',
                                'is_sorted' => true,
                                'sorted_at' => now(),
                                'sorted_by' => $userId,
                            ]));
                        }

                        if ($remaining > 0.001) {
                            ShopOrderItem::create(array_merge($priceData, [
                                'shop_order_id' => $shopOrder->id,
                                'product_id' => $productId,
                                'requested_qty' => $remaining,
                                'approved_qty' => $remaining,
                                'loaded_qty' => null,
                                'loaded_order_unit_qty' => null,
                                'actual_weight' => null,
                                'excess_qty' => 0.0,
                                'excess_value' => 0.0,
                                'sorting_status' => 'allocated',
                                'is_sorted' => false,
                                'sorted_at' => null,
                                'sorted_by' => null,
                            ]));
                        }
                    }
                }

                $newStatus = $anyItemLoaded ? 'ready_for_dispatch' : 'pending_delivery';
                $shopOrder->update(['delivery_status' => $newStatus]);
            });
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $e->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Loadout saved and inventory updated successfully.',
            ]);
        }

        return redirect()
            ->route('warehouse.loadout.show', $shopOrder)
            ->with('success', 'Loadout saved and inventory updated successfully.');
    }

    public function moveToDelivery(ShopOrder $shopOrder, Request $request): RedirectResponse
    {
        return $this->moveOrderToDelivery($shopOrder, $request, false);
    }

    public function moveToPartialDelivery(ShopOrder $shopOrder, Request $request): RedirectResponse
    {
        return $this->moveOrderToDelivery($shopOrder, $request, true);
    }

    public function moveToLoadout(ShopOrder $shopOrder, Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);

        if ($shopOrder->delivery_status === 'delivered') {
            return redirect()->back()->withErrors(['Delivered orders cannot be edited. Delivery has already been verified and locked.']);
        }

        DB::transaction(function () use ($shopOrder) {
            $shopOrder->update([
                'delivery_status' => 'ready_for_dispatch',
                'is_allocation_completed' => false,
                'delivery_notes' => 'Re-opened for loadout quantity correction.',
            ]);
        });

        return redirect()
            ->route('warehouse.loadout.show', $shopOrder)
            ->with('success', 'Order moved back to loadout. You can now edit loadout quantities and save updates.');
    }

    private function moveOrderToDelivery(ShopOrder $shopOrder, Request $request, bool $partialDelivery): RedirectResponse
    {
        $this->authorizeAccess($request);

        if ($shopOrder->delivery_status === 'in_transit') {
            return redirect()->back()->withErrors(['This order is already moved to delivery. Inventory was already updated.']);
        }

        if ($shopOrder->delivery_status === 'delivered') {
            return redirect()->back()->withErrors(['This order is already delivered.']);
        }

        if ($shopOrder->delivery_status !== 'ready_for_dispatch') {
            return redirect()->back()->withErrors(['Order is not ready for delivery. Save loadout first.']);
        }

        $loadedItems = $shopOrder->items()
            ->where('sorting_status', 'loaded')
            ->where('loaded_qty', '>', 0)
            ->with('product')
            ->get();

        $remainingItems = $shopOrder->items()
            ->where('sorting_status', '!=', 'loaded')
            ->get();

        if ($loadedItems->isEmpty()) {
            return redirect()->back()->withErrors(['Please load at least one item before moving to delivery.']);
        }

        if (! $partialDelivery && $remainingItems->isNotEmpty()) {
            return redirect()->back()->withErrors(['This order still has unfulfilled items. Use partial delivery instead.']);
        }

        if ($partialDelivery && $remainingItems->isEmpty()) {
            return redirect()->back()->withErrors(['All items are fully loaded. Use the regular move to delivery action.']);
        }

        try {
            DB::transaction(function () use ($shopOrder, $partialDelivery, $remainingItems) {
                // Lock order
                ShopOrder::lockForUpdate()->find($shopOrder->id);

                $shopOrder->update([
                    'delivery_status' => 'in_transit',
                    'is_allocation_completed' => true,
                    'delivery_notes' => $partialDelivery
                        ? 'Moved to delivery as a partial delivery with '.$remainingItems->count().' item(s) still pending loadout.'
                        : $shopOrder->delivery_notes,
                ]);
            });
        } catch (\RuntimeException $e) {
            return redirect()->back()->withErrors([$e->getMessage()]);
        }

        return redirect()
            ->route('warehouse.loadout.index')
            ->with('success', $partialDelivery
                ? 'Order moved to delivery as a partial delivery.'
                : 'Order moved to delivery. Status updated.');
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

    private function ensureLoadoutEditable(ShopOrder $shopOrder): void
    {
        if (! in_array($shopOrder->delivery_status, ['pending_delivery', 'ready_for_dispatch'], true)) {
            abort(403, 'This loadout order cannot be edited.');
        }
    }

    /**
     * Return the original approved target for a product even after loadout has
     * split it into loaded and remaining rows.
     */
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
}
