<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Warehouse;

use App\Actions\Purchasing\ApproveGoodsReceiptAction;
use App\DTOs\Inventory\WastageEntryData;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\PurchaserCart;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Inventory\StockLedgerService;
use App\Services\Inventory\WastageService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WarehouseReceiverController extends Controller
{
    private const BULK_RECEIVE_GRN_LIMIT = 5;

    public function __construct(private readonly StockLedgerService $stockLedgerService) {}

    /**
     * Show the warehouse receive checklist — pending vendor sheets (GRNs) and approved shop orders.
     */
    public function index(Request $request): View
    {
        $this->authorizeReceiverAccess($request);
        $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'receive_search' => ['nullable', 'string', 'max:120'],
            'receive_source' => ['nullable', 'string', 'in:all,vendor,direct,batch'],
            'receive_category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $date = $request->input('date', app(PurchaserBusinessDayService::class)->operationalDate()->toDateString());
        $selectedWarehouseId = $request->integer('warehouse_id') ?: null;
        $receiveSearch = trim($request->string('receive_search')->toString());
        $receiveSource = $request->string('receive_source')->toString() ?: 'all';
        $receiveCategoryId = $request->integer('receive_category_id') ?: null;

        // All pending vendor sheets (GRNs) awaiting warehouse receipt confirmation
        $pendingGrns = GoodsReceived::where('status', 'pending_approval')
            ->whereDate('received_at', $date)
            ->with(['purchaseOrder.supplier', 'purchaseOrder.purchaserCart.user', 'items.product.category'])
            ->when($receiveSource !== 'all' && $receiveSource !== 'vendor', fn ($query) => $query->whereRaw('1 = 0'))
            ->when($receiveCategoryId, function ($query) use ($receiveCategoryId): void {
                $query->whereHas('items.product', fn ($productQuery) => $productQuery->where('category_id', $receiveCategoryId));
            })
            ->when($receiveSearch !== '', function ($query) use ($receiveSearch): void {
                $query->where(function ($searchQuery) use ($receiveSearch): void {
                    $searchQuery
                        ->where('grn_number', 'like', "%{$receiveSearch}%")
                        ->orWhereHas('purchaseOrder.supplier', fn ($supplierQuery) => $supplierQuery->where('name', 'like', "%{$receiveSearch}%"))
                        ->orWhereHas('purchaseOrder.purchaserCart.user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$receiveSearch}%"))
                        ->orWhereHas('items.product', function ($productQuery) use ($receiveSearch): void {
                            $productQuery
                                ->where('name', 'like', "%{$receiveSearch}%")
                                ->orWhere('sku', 'like', "%{$receiveSearch}%")
                                ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$receiveSearch}%"));
                        });
                });
            })
            ->orderBy('created_at', 'asc')
            ->get();

        // Fetch pending batches for test compatibility
        $pendingBatches = StockBatch::where('warehouse_receive_pending', true)
            ->whereDate('received_at', $date)
            ->with(['product.category'])
            ->when($receiveSource !== 'all' && $receiveSource !== 'batch', fn ($query) => $query->whereRaw('1 = 0'))
            ->when($receiveCategoryId, function ($query) use ($receiveCategoryId): void {
                $query->whereHas('product', fn ($productQuery) => $productQuery->where('category_id', $receiveCategoryId));
            })
            ->when($receiveSearch !== '', function ($query) use ($receiveSearch): void {
                $query->where(function ($searchQuery) use ($receiveSearch): void {
                    $searchQuery
                        ->where('reference', 'like', "%{$receiveSearch}%")
                        ->orWhereHas('product', function ($productQuery) use ($receiveSearch): void {
                            $productQuery
                                ->where('name', 'like', "%{$receiveSearch}%")
                                ->orWhere('sku', 'like', "%{$receiveSearch}%")
                                ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$receiveSearch}%"));
                        });
                });
            })
            ->orderBy('created_at', 'asc')
            ->get();

        // Also fetch recently confirmed batches (today) for context
        $confirmedBatches = StockBatch::where('warehouse_receive_pending', false)
            ->whereDate('received_at', $date)
            ->when($selectedWarehouseId, fn ($query) => $query->where('warehouse_id', $selectedWarehouseId))
            ->with(['product.category', 'warehouseConfirmedBy', 'warehouse'])
            ->orderBy('warehouse_confirmed_at', 'desc')
            ->get();

        // Fetch recent "In" movements
        $inMovements = StockMovement::whereIn('type', [
            StockMovementType::In,
            StockMovementType::SaleReversal,
        ])
            ->when($selectedWarehouseId, fn ($query) => $query->where('warehouse_id', $selectedWarehouseId))
            ->with(['product.category', 'batch'])
            ->orderBy('created_at', 'desc')
            ->take(15)
            ->get();

        // Map confirmed batches as part of inflows
        $inflows = collect();

        foreach ($inMovements as $mov) {
            $inflows->push((object) [
                'product_name' => $mov->product->name,
                'category_name' => $mov->product->category->name ?? 'Other',
                'grade_label' => $mov->grade instanceof ProductGrade ? $mov->grade->label() : ($mov->grade ? (ProductGrade::tryFrom($mov->grade)?->label() ?? $mov->grade) : 'Unsorted'),
                'reference' => $mov->batch?->reference,
                'quantity' => (float) $mov->quantity,
                'unit' => $mov->product->unit,
                'timestamp' => $mov->created_at,
                'time_formatted' => $mov->created_at->format('H:i'),
                'source' => 'movement',
            ]);
        }

        foreach ($confirmedBatches as $batch) {
            $inflows->push((object) [
                'product_name' => $batch->product->name,
                'category_name' => $batch->product->category->name ?? 'Other',
                'grade_label' => 'Unsorted',
                'reference' => $batch->reference,
                'quantity' => (float) $batch->total_kg,
                'unit' => $batch->product->unit,
                'timestamp' => $batch->warehouse_confirmed_at ?? $batch->created_at,
                'time_formatted' => ($batch->warehouse_confirmed_at ?? $batch->created_at)->format('H:i'),
                'source' => 'batch',
            ]);
        }

        $inflows = $inflows->sortByDesc('timestamp')->take(15);

        // Fetch recent "Out" movements
        $outMovements = StockMovement::whereIn('type', [
            StockMovementType::Out,
            StockMovementType::Wastage,
            StockMovementType::Sale,
        ])
            ->when($selectedWarehouseId, fn ($query) => $query->where('warehouse_id', $selectedWarehouseId))
            ->with(['product.category'])
            ->orderBy('created_at', 'desc')
            ->take(15)
            ->get();

        // Fetch active stock levels
        $stockLevels = app(StockMovementRepository::class)->currentStockByProductAndGrade(null, $selectedWarehouseId);

        // Calculate latest activity timestamp for each stock level
        foreach ($stockLevels as $item) {
            if ($item->grade === 'Unsorted') {
                $latestBatch = StockBatch::where('product_id', $item->product_id)
                    ->where('status', BatchStatus::Pending)
                    ->when($selectedWarehouseId, fn ($query) => $query->where('warehouse_id', $selectedWarehouseId))
                    ->orderBy('created_at', 'desc')
                    ->first();
                $item->latest_activity = $latestBatch ? $latestBatch->created_at : null;
            } else {
                $latestMovement = StockMovement::where('product_id', $item->product_id)
                    ->where('grade', $item->grade)
                    ->when($selectedWarehouseId, fn ($query) => $query->where('warehouse_id', $selectedWarehouseId))
                    ->orderBy('created_at', 'desc')
                    ->first();
                $item->latest_activity = $latestMovement ? $latestMovement->created_at : null;
            }
        }

        // Sort stock levels by latest_activity descending (latest updates on top)
        $stockLevels = $stockLevels->sortByDesc(function ($item) {
            if (! $item->latest_activity) {
                return 0;
            }

            return $item->latest_activity instanceof \Carbon\Carbon
                ? $item->latest_activity->timestamp
                : Carbon::parse($item->latest_activity)->timestamp;
        });

        // Build stock map for fulfillment calculations (summed by product_id across all grades)
        $stockMap = [];
        foreach ($stockLevels as $level) {
            $stockMap[$level->product_id] = ($stockMap[$level->product_id] ?? 0.0) + (float) $level->current_stock;
        }

        $directPurchaseGrnProductIds = GoodsReceived::query()
            ->whereDate('received_at', $date)
            ->whereIn('status', ['pending_approval', 'approved'])
            ->whereIn('purchaser_cart_id', PurchaserCart::query()
                ->where('purchase_source', 'green_leaf_direct_purchase')
                ->select('id'))
            ->with('items:id,goods_received_id,product_id')
            ->get()
            ->flatMap(fn (GoodsReceived $goodsReceived) => $goodsReceived->items->pluck('product_id'))
            ->unique()
            ->values();

        $pendingDirectPurchaseOrders = ShopOrder::query()
            ->whereDate('business_date', $date)
            ->where('order_source', 'admin_direct_purchase')
            ->where('state', 'approved')
            ->where('delivery_status', 'pending_delivery')
            ->where('is_allocation_completed', false)
            ->with(['items.product.category'])
            ->whereHas('items')
            ->when($receiveSource !== 'all' && $receiveSource !== 'direct', fn ($query) => $query->whereRaw('1 = 0'))
            ->when($receiveCategoryId, function ($query) use ($receiveCategoryId): void {
                $query->whereHas('items.product', fn ($productQuery) => $productQuery->where('category_id', $receiveCategoryId));
            })
            ->when($receiveSearch !== '', function ($query) use ($receiveSearch): void {
                $query->where(function ($searchQuery) use ($receiveSearch): void {
                    $searchQuery
                        ->where('order_number', 'like', "%{$receiveSearch}%")
                        ->orWhereHas('items.product', function ($productQuery) use ($receiveSearch): void {
                            $productQuery
                                ->where('name', 'like', "%{$receiveSearch}%")
                                ->orWhere('sku', 'like', "%{$receiveSearch}%")
                                ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$receiveSearch}%"));
                        });
                });
            })
            ->get()
            ->filter(function (ShopOrder $order) use ($directPurchaseGrnProductIds): bool {
                return $order->items
                    ->pluck('product_id')
                    ->intersect($directPurchaseGrnProductIds)
                    ->isEmpty();
            })
            ->values();

        // Fetch approved daily shop orders for Loadout
        $approvedOrders = ShopOrder::whereDate('business_date', $date)
            ->where('state', 'approved')
            ->with(['shop', 'items.product'])
            ->get();

        foreach ($approvedOrders as $order) {
            $totalItemsCount = $order->items->count();
            $loadedItemsCount = $order->items->where('sorting_status', 'loaded')->count();

            $order->loaded_items_count = $loadedItemsCount;
            $order->total_items_count = $totalItemsCount;

            if ($totalItemsCount === 0) {
                $order->loading_status = 'Pending';
            } elseif ($loadedItemsCount === $totalItemsCount) {
                $order->loading_status = 'Loaded';
            } elseif ($loadedItemsCount > 0) {
                $order->loading_status = 'Partially Loaded';
            } else {
                $order->loading_status = 'Pending';
            }
        }

        // Fetch all daily shop orders (for Received outflows tab)
        $shopOrders = ShopOrder::whereDate('business_date', $date)
            ->with(['shop', 'items.product'])
            ->get();

        foreach ($shopOrders as $order) {
            $totalItemsCount = $order->items->count();
            $loadedItemsCount = $order->items->where('sorting_status', 'loaded')->count();

            $order->loaded_items_count = $loadedItemsCount;
            $order->total_items_count = $totalItemsCount;

            if ($totalItemsCount === 0) {
                $order->loading_status = 'Pending';
            } elseif ($loadedItemsCount === $totalItemsCount) {
                $order->loading_status = 'Loaded';
            } elseif ($loadedItemsCount > 0) {
                $order->loading_status = 'Partially Loaded';
            } else {
                $order->loading_status = 'Pending';
            }

            // Compute fulfillment stats based on active stock levels
            $totalRequested = 0;
            $totalAvailableStock = 0;

            foreach ($order->items as $item) {
                $qty = $item->approved_qty > 0 ? (float) $item->approved_qty : (float) $item->requested_qty;
                $stock = $stockMap[$item->product_id] ?? 0.0;

                $totalRequested += $qty;
                $totalAvailableStock += min($qty, max(0.0, $stock));
            }

            $order->fulfillment_percentage = $totalRequested > 0
                ? (int) round(($totalAvailableStock / $totalRequested) * 100)
                : 100;
        }

        $warehouses = Warehouse::active()->orderBy('name')->get();
        $receiveCategories = Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('warehouse-receiver.checklist', compact(
            'date',
            'pendingBatches',
            'pendingGrns',
            'confirmedBatches',
            'inflows',
            'inMovements',
            'outMovements',
            'stockLevels',
            'pendingDirectPurchaseOrders',
            'approvedOrders',
            'shopOrders',
            'selectedWarehouseId',
            'warehouses',
            'receiveSearch',
            'receiveSource',
            'receiveCategoryId',
            'receiveCategories',
        ));
    }

    public function receiveDirectPurchase(ShopOrder $order, Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        abort_unless($order->isAdminDirectPurchase(), 404);

        if ($order->delivery_status !== 'pending_delivery' || $order->is_allocation_completed) {
            return redirect()->back()->withErrors(['This direct purchase has already been received for warehouse flow.']);
        }

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ]);

        $order->loadMissing('items.product');

        if ($order->items->isEmpty()) {
            return redirect()->back()->withErrors(['This direct purchase has no items to receive.']);
        }

        $userId = (int) $request->user()->id;

        DB::transaction(function () use ($order, $validated, $userId): void {
            foreach ($order->items as $item) {
                $itemData = $validated['items'][$item->id] ?? null;
                if (! is_array($itemData)) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.warehouse_id" => 'Select a warehouse for every direct purchase item.',
                    ]);
                }

                $quantity = (float) ($item->approved_qty > 0 ? $item->approved_qty : $item->requested_qty);

                if ($quantity <= 0.0) {
                    continue;
                }

                StockBatch::query()->create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => (int) $itemData['warehouse_id'],
                    'created_by' => $userId,
                    'reference' => $this->generateDirectPurchaseBatchReference(),
                    'received_at' => $order->business_date,
                    'total_kg' => $quantity,
                    'cost_per_kg' => 0,
                    'transport_cost' => 0,
                    'labour_cost' => 0,
                    'status' => BatchStatus::Pending,
                    'warehouse_receive_pending' => false,
                    'warehouse_confirmed_at' => now(),
                    'warehouse_confirmed_by' => $userId,
                    'notes' => "Direct purchase received from admin order: {$order->order_number}",
                ]);
            }

            $order->update([
                'delivery_status' => 'ready_for_dispatch',
                'sorting_notes' => trim((string) $order->sorting_notes."\nDirect purchase received by warehouse."),
            ]);
        });

        return redirect()
            ->route('warehouse.receiver.checklist', ['date' => $order->business_date->format('Y-m-d'), 'tab' => 'pending'])
            ->with('success', 'Direct Purchase received into warehouse inventory.');
    }

    /**
     * Show the receive form for a specific vendor sheet (GRN).
     */
    public function receiveGrnForm(GoodsReceived $grn, Request $request): View
    {
        $this->authorizeReceiverAccess($request);

        $grn->load(['purchaseOrder.supplier', 'purchaseOrder.purchaserCart.user', 'items.product.category', 'items.purchaseOrderItem']);
        $warehouses = Warehouse::active()->orderBy('name')->get();

        // Group items by category name
        $groupedItems = $grn->items->groupBy(fn ($item) => $item->product->category->name ?? 'Uncategorized');

        return view('warehouse-receiver.receive_grn', compact('grn', 'groupedItems', 'warehouses'));
    }

    /**
     * Process physical receipt of the vendor sheet (GRN).
     */
    public function processReceiveGrn(GoodsReceived $grn, Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'items' => ['required', 'array'],
            'items.*.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'items.*.received_qty' => ['required', 'numeric', 'min:0'],
            'items.*.discrepancy_type' => ['required', 'string', 'in:none,wastage,other'],
            'items.*.discrepancy_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $zeroPricedItems = $this->zeroPricedReceivedItems($grn, $validated['items']);

        if ($zeroPricedItems !== []) {
            return redirect()
                ->back()
                ->withInput()
                ->with('warning', 'Price is zero on: '.implode(', ', $zeroPricedItems).'. Update the purchaser bill price before receiving this vendor sheet.');
        }

        $userId = (int) $request->user()->id;

        $this->receiveGrnIntoWarehouse($grn, $validated['items'], (int) $validated['warehouse_id'], $userId);

        return redirect()
            ->route('warehouse.receiver.checklist', ['date' => $grn->received_at->format('Y-m-d')])
            ->with('success', 'Vendor sheet received and stock moved to inventory.');
    }

    /**
     * Receive the next pending vendor sheet batch for the selected date using current GRN quantities.
     */
    public function processReceiveAllGrns(Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $userId = (int) $request->user()->id;
        $fallbackWarehouseId = Warehouse::active()->orderBy('id')->value('id');

        if (! $fallbackWarehouseId) {
            return redirect()->back()->withErrors(['No active warehouse is available for receiving vendor sheets.']);
        }

        $pendingGrns = GoodsReceived::query()
            ->where('status', 'pending_approval')
            ->whereDate('received_at', $date)
            ->with(['items.product', 'items.purchaseOrderItem'])
            ->orderBy('created_at')
            ->limit(self::BULK_RECEIVE_GRN_LIMIT)
            ->get();

        if ($pendingGrns->isEmpty()) {
            return redirect()->back()->withErrors(['No pending vendor sheets to receive for this date.']);
        }

        $zeroPricedItems = [];
        $receivePayloads = [];

        foreach ($pendingGrns as $pendingGrn) {
            $items = $this->defaultReceiveItemsForGrn($pendingGrn, (int) $fallbackWarehouseId);
            $zeroPriced = $this->zeroPricedReceivedItems($pendingGrn, $items);

            if ($zeroPriced !== []) {
                $zeroPricedItems[] = "{$pendingGrn->grn_number}: ".implode(', ', $zeroPriced);
            }

            $receivePayloads[(int) $pendingGrn->id] = $items;
        }

        if ($zeroPricedItems !== []) {
            return redirect()
                ->back()
                ->with('warning', 'Price is zero on: '.implode('; ', $zeroPricedItems).'. Update purchaser bill prices before receiving this vendor sheet batch.');
        }

        foreach ($pendingGrns as $pendingGrn) {
            $this->receiveGrnIntoWarehouse(
                $pendingGrn,
                $receivePayloads[(int) $pendingGrn->id],
                (int) $fallbackWarehouseId,
                $userId
            );
        }

        $remainingCount = GoodsReceived::query()
            ->where('status', 'pending_approval')
            ->whereDate('received_at', $date)
            ->count();

        return redirect()
            ->route('warehouse.receiver.checklist', ['date' => $date])
            ->with('success', "{$pendingGrns->count()} vendor sheet(s) received and moved to inventory. {$remainingCount} still pending.");
    }

    /**
     * Confirm a single pending batch.
     */
    public function confirm(StockBatch $batch, Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        if (! $batch->warehouse_receive_pending) {
            return redirect()->back()->withErrors(['This batch has already been confirmed.']);
        }

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ]);

        $batch->update([
            'warehouse_id' => $validated['warehouse_id'],
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $request->user()->id,
        ]);

        activity()
            ->performedOn($batch)
            ->causedBy($request->user()->id)
            ->log('stock_batch.warehouse_confirmed');

        if ($batch->goodsReceived) {
            $this->updatePurchaserCartReceiptStatus($batch->goodsReceived);
        }

        return redirect()
            ->route('warehouse.receiver.checklist', ['date' => $batch->received_at->format('Y-m-d')])
            ->with('success', "{$batch->product->name} confirmed as received in warehouse.");
    }

    /**
     * Confirm all pending batches for a date at once.
     */
    public function confirmAll(Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        $date = $request->input('date', app(PurchaserBusinessDayService::class)->operationalDate()->toDateString());
        $userId = (int) $request->user()->id;

        $pending = StockBatch::where('warehouse_receive_pending', true)
            ->whereDate('received_at', $date)
            ->with('product')
            ->get();

        if ($pending->isEmpty()) {
            return redirect()->back()->withErrors(['No pending batches to confirm for this date.']);
        }

        $grnIds = $pending->pluck('goods_received_id')->filter()->unique();

        $firstWarehouse = Warehouse::active()->orderBy('id')->first();
        $firstWarehouseId = $firstWarehouse?->id;

        foreach ($pending as $batch) {
            $warehouseId = $batch->product->default_warehouse_id ?? $firstWarehouseId;
            $batch->update([
                'warehouse_id' => $warehouseId,
                'warehouse_receive_pending' => false,
                'warehouse_confirmed_at' => now(),
                'warehouse_confirmed_by' => $userId,
            ]);
        }

        foreach ($grnIds as $grnId) {
            $grn = GoodsReceived::find($grnId);
            if ($grn) {
                $this->updatePurchaserCartReceiptStatus($grn);
            }
        }

        return redirect()
            ->route('warehouse.receiver.checklist', ['date' => $date])
            ->with('success', "All {$pending->count()} batch(es) confirmed as received.");
    }

    private function authorizeReceiverAccess(Request $request): void
    {
        if (
            ! $request->user()->hasRole('warehouse_receiver')
            && ! $request->user()->hasRole('admin')
            && ! $request->user()->can('warehouse.receive.confirm')
        ) {
            abort(403, 'Unauthorized access.');
        }
    }

    /**
     * Show the loadout details for a single shop order.
     */
    public function loadoutDetails(ShopOrder $order, Request $request): View
    {
        $this->authorizeReceiverAccess($request);

        $order->load(['shop', 'items.product']);

        foreach ($order->items as $item) {
            $item->inventory_stock = $this->stockLedgerService->availableSortedStockForProduct($item->product_id) + (float) $item->loaded_qty;
        }

        return view('warehouse-receiver.loadout_details', compact('order'));
    }

    /**
     * Mark a single shop order item as loaded and reduce from inventory.
     */
    public function loadoutItem(ShopOrderItem $item, Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        if ($item->sorting_status === 'loaded') {
            return redirect()->back()->withErrors(['This item is already loaded.']);
        }

        $validated = $request->validate([
            'loaded_qty' => ['nullable', 'numeric', 'min:0'],
            'discrepancy_type' => ['nullable', 'string', 'in:none,wastage,other'],
            'discrepancy_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $approvedQty = (float) ($item->approved_qty > 0 ? $item->approved_qty : $item->requested_qty);
        $loadedQty = $request->has('loaded_qty') ? (float) $validated['loaded_qty'] : $approvedQty;
        $discrepancyType = $validated['discrepancy_type'] ?? 'none';
        $discrepancyNote = $validated['discrepancy_note'] ?? null;

        if ($loadedQty > $approvedQty) {
            return redirect()->back()->withErrors(['Loaded quantity cannot exceed approved quantity.']);
        }

        try {
            DB::transaction(function () use ($item, $loadedQty, $request) {
                $userId = $request->user()->id;

                if ($loadedQty > 0) {
                    $this->stockLedgerService->consumeStockForProductAllowingNegative(
                        $item->product_id,
                        $loadedQty,
                        $userId,
                        StockMovementType::Out,
                        "Loadout: Shop Order {$item->order->order_number} to {$item->order->shop->name}"
                    );
                }

                $item->update([
                    'loaded_qty' => $loadedQty,
                    'loadout_discrepancy_type' => 'none',
                    'loadout_discrepancy_note' => null,
                    'sorting_status' => 'loaded',
                    'is_sorted' => true,
                    'sorted_at' => now(),
                    'sorted_by' => $userId,
                ]);
            });

            return redirect()->back()->with('success', "{$item->product->name} marked as loaded and reduced from inventory.");
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([$e->getMessage()]);
        }
    }

    public function loadoutOrderAll(ShopOrder $order, Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        $pendingItems = $order->items()->where('sorting_status', '!=', 'loaded')->get();

        if ($pendingItems->isEmpty()) {
            return redirect()->back()->withErrors(['All items in this order are already loaded.']);
        }

        $skipUnavailable = $request->boolean('skip_unavailable');
        $skippedNames = [];
        $loadedCount = 0;

        try {
            DB::transaction(function () use ($pendingItems, $order, $request, $skipUnavailable, &$skippedNames, &$loadedCount) {
                $userId = $request->user()->id;

                foreach ($pendingItems as $item) {
                    $approvedQty = $item->approved_qty > 0 ? (float) $item->approved_qty : (float) $item->requested_qty;
                    $availableStock = $this->stockLedgerService->availableSortedStockForProduct($item->product_id);

                    // Skip if skip_unavailable is checked and available stock is less than approved quantity
                    if ($skipUnavailable && $availableStock < $approvedQty) {
                        $skippedNames[] = $item->product->name;

                        continue;
                    }

                    $qtyToLoad = $approvedQty;

                    if ($qtyToLoad <= 0.0) {
                        $skippedNames[] = $item->product->name;

                        continue;
                    }

                    // Deduct stock immediately (allows negative stock)
                    $this->stockLedgerService->consumeStockForProductAllowingNegative(
                        $item->product_id,
                        $qtyToLoad,
                        $userId,
                        StockMovementType::Out,
                        "Loadout: Shop Order {$order->order_number} to {$order->shop->name}"
                    );

                    $item->update([
                        'loaded_qty' => $qtyToLoad,
                        'loadout_discrepancy_type' => 'none',
                        'loadout_discrepancy_note' => null,
                        'sorting_status' => 'loaded',
                        'is_sorted' => true,
                        'sorted_at' => now(),
                        'sorted_by' => $userId,
                    ]);

                    $loadedCount++;
                }
            });

            if (! empty($skippedNames)) {
                $skippedList = implode(', ', array_unique($skippedNames));

                return redirect()->route('warehouse.receiver.loadout.show', $order)
                    ->with('success', "Loaded {$loadedCount} item(s). (Skipped/Partial: {$skippedList} due to insufficient stock)");
            }

            return redirect()->route('warehouse.receiver.loadout.show', $order)
                ->with('success', "All {$loadedCount} pending item(s) processed for loadout successfully.");
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([$e->getMessage()]);
        }
    }

    /**
     * Dispatch order and mark as in transit out for delivery.
     */
    public function dispatchOrder(ShopOrder $order, Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        if ($order->delivery_status !== 'pending_delivery' && $order->delivery_status !== 'in_transit' && $order->delivery_status !== 'ready_for_dispatch') {
            return redirect()->back()->withErrors(['This order is already completed.']);
        }

        $loadedCount = $order->items()->where('sorting_status', 'loaded')->count();
        if ($loadedCount === 0) {
            return redirect()->back()->withErrors(['Cannot dispatch: No items have been loaded yet.']);
        }

        DB::transaction(function () use ($order, $request): void {
            $userId = $request->user()->id;

            // Split items where loaded_qty < approved_qty (but > 0)
            $items = $order->items()->get();
            foreach ($items as $item) {
                $approvedQty = $item->approved_qty > 0 ? (float) $item->approved_qty : (float) $item->requested_qty;
                $loadedQty = $item->loaded_qty !== null ? (float) $item->loaded_qty : 0.0;

                if ($loadedQty > 0.0 && $loadedQty < $approvedQty) {
                    $remainingQty = $approvedQty - $loadedQty;

                    // Update existing item to represent only the loaded part
                    $item->update([
                        'requested_qty' => $loadedQty,
                        'approved_qty' => $loadedQty,
                    ]);

                    // Create a new pending item for the remaining balance
                    ShopOrderItem::create([
                        'shop_order_id' => $order->id,
                        'product_id' => $item->product_id,
                        'product_grade' => $item->product_grade ?? 'A',
                        'unit' => $item->unit,
                        'requested_qty' => $remainingQty,
                        'approved_qty' => $remainingQty,
                        'loaded_qty' => null,
                        'sorting_status' => 'allocated',
                        'is_sorted' => false,
                    ]);
                }
            }

            $order->update([
                'delivery_status' => 'ready_for_dispatch',
                'is_allocation_completed' => false,
            ]);

            activity()
                ->performedOn($order)
                ->causedBy($userId)
                ->log('shop_order.moved_to_delivery');
        });

        return redirect()
            ->route('warehouse.receiver.checklist', ['date' => $order->business_date->format('Y-m-d')])
            ->with('success', "Order {$order->order_number} moved to delivery successfully.");
    }

    public function dispatchPartialOrder(ShopOrder $order, Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        if ($order->delivery_status !== 'pending_delivery' && $order->delivery_status !== 'in_transit' && $order->delivery_status !== 'ready_for_dispatch') {
            return redirect()->back()->withErrors(['This order is already completed.']);
        }

        try {
            DB::transaction(function () use ($order, $request) {
                $userId = $request->user()->id;

                // Split items where loaded_qty < approved_qty (but > 0)
                $items = $order->items()->get();
                foreach ($items as $item) {
                    $approvedQty = $item->approved_qty > 0 ? (float) $item->approved_qty : (float) $item->requested_qty;
                    $loadedQty = $item->loaded_qty !== null ? (float) $item->loaded_qty : 0.0;

                    if ($loadedQty > 0.0 && $loadedQty < $approvedQty) {
                        $remainingQty = $approvedQty - $loadedQty;

                        // Update existing item to represent only the loaded part
                        $item->update([
                            'requested_qty' => $loadedQty,
                            'approved_qty' => $loadedQty,
                        ]);

                        // Create a new pending item for the remaining balance
                        ShopOrderItem::create([
                            'shop_order_id' => $order->id,
                            'product_id' => $item->product_id,
                            'product_grade' => $item->product_grade ?? 'A',
                            'unit' => $item->unit,
                            'requested_qty' => $remainingQty,
                            'approved_qty' => $remainingQty,
                            'loaded_qty' => null,
                            'sorting_status' => 'allocated',
                            'is_sorted' => false,
                        ]);
                    }
                }

                // Transition order to ready_for_dispatch state
                $order->update([
                    'delivery_status' => 'ready_for_dispatch',
                    'is_allocation_completed' => false,
                ]);

                activity()
                    ->performedOn($order)
                    ->causedBy($userId)
                    ->log('shop_order.moved_to_delivery_partial');
            });

            return redirect()
                ->route('warehouse.receiver.checklist', ['date' => $order->business_date->format('Y-m-d')])
                ->with('success', "Order {$order->order_number} moved to delivery as a partial delivery.");
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([$e->getMessage()]);
        }
    }

    /**
     * Mark order out for delivery (dispatch from ready_for_dispatch to in_transit).
     */
    public function shipOrder(ShopOrder $order, Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        if ($order->delivery_status !== 'ready_for_dispatch') {
            return redirect()->back()->withErrors(['This order is not ready for dispatch or already dispatched.']);
        }

        try {
            DB::transaction(function () use ($order, $request) {
                $userId = $request->user()->id;

                $order->update([
                    'delivery_status' => 'in_transit',
                    'is_allocation_completed' => true,
                ]);

                activity()
                    ->performedOn($order)
                    ->causedBy($userId)
                    ->log('shop_order.marked_out_for_delivery');
            });

            return redirect()->route('warehouse.receiver.checklist', ['date' => $order->business_date->format('Y-m-d'), 'tab' => 'confirmed'])
                ->with('success', "Order {$order->order_number} marked out for delivery successfully.");
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([$e->getMessage()]);
        }
    }

    /**
     * @param  array<int|string, array{received_qty:mixed}>  $items
     * @return array<int, string>
     */
    private function zeroPricedReceivedItems(GoodsReceived $grn, array $items): array
    {
        $grn->loadMissing(['items.product', 'items.purchaseOrderItem']);

        return $grn->items
            ->filter(function ($item) use ($items): bool {
                $receivedQty = (float) ($items[$item->id]['received_qty'] ?? 0);
                $unitPrice = (float) ($item->purchaseOrderItem?->unit_price ?? 0);

                return $receivedQty > 0.0 && $unitPrice <= 0.0;
            })
            ->map(fn ($item): string => $item->product?->name ?? "Item #{$item->id}")
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{warehouse_id:int, received_qty:float, discrepancy_type:string, discrepancy_note:null}>
     */
    private function defaultReceiveItemsForGrn(GoodsReceived $grn, int $fallbackWarehouseId): array
    {
        $grn->loadMissing(['items.product']);

        return $grn->items
            ->mapWithKeys(function ($item) use ($fallbackWarehouseId): array {
                return [
                    $item->id => [
                        'warehouse_id' => (int) ($item->product?->default_warehouse_id ?: $fallbackWarehouseId),
                        'received_qty' => (float) $item->received_qty,
                        'discrepancy_type' => 'none',
                        'discrepancy_note' => null,
                    ],
                ];
            })
            ->all();
    }

    /**
     * @param  array<int|string, array{warehouse_id?:mixed, received_qty:mixed, discrepancy_type?:string, discrepancy_note?:string|null}>  $items
     */
    private function receiveGrnIntoWarehouse(GoodsReceived $grn, array $items, int $fallbackWarehouseId, int $userId): void
    {
        DB::transaction(function () use ($grn, $items, $fallbackWarehouseId, $userId): void {
            $grn->loadMissing(['items.purchaseOrderItem', 'items.product']);

            foreach ($items as $itemId => $itemData) {
                $item = $grn->items->firstWhere('id', (int) $itemId) ?? $grn->items()->findOrFail((int) $itemId);
                $originalPurchasedQty = (float) ($item->purchaseOrderItem?->quantity ?? $item->received_qty);
                $receivedQty = (float) $itemData['received_qty'];
                $variance = $receivedQty - (float) ($item->purchaseOrderItem?->quantity ?? 0.0);

                $item->update([
                    'received_qty' => $receivedQty,
                    'variance' => $variance,
                    'purchased_qty' => $originalPurchasedQty,
                    'discrepancy_type' => $itemData['discrepancy_type'] ?? 'none',
                    'discrepancy_note' => $itemData['discrepancy_note'] ?? null,
                ]);
            }

            $approvedGrn = app(ApproveGoodsReceiptAction::class)->execute($grn, $userId);
            $approvedGrn->loadMissing('items');

            StockBatch::where('goods_received_id', $approvedGrn->id)
                ->get()
                ->each(function (StockBatch $batch) use ($approvedGrn, $items, $fallbackWarehouseId, $userId): void {
                    $grnItem = $batch->goods_received_item_id
                        ? $approvedGrn->items->firstWhere('id', $batch->goods_received_item_id)
                        : $approvedGrn->items->firstWhere('product_id', $batch->product_id);
                    $discrepancyType = $grnItem?->discrepancy_type ?? 'none';
                    $discrepancyNote = $grnItem?->discrepancy_note;
                    $itemWarehouseId = (int) ($items[$grnItem?->id]['warehouse_id'] ?? $fallbackWarehouseId);

                    $batch->update([
                        'warehouse_id' => $itemWarehouseId,
                        'warehouse_receive_pending' => false,
                        'warehouse_confirmed_at' => now(),
                        'warehouse_confirmed_by' => $userId,
                    ]);

                    if ($batch->grading_mode === 'fixed_purchase_grade') {
                        StockMovement::query()->firstOrCreate(
                            [
                                'batch_id' => $batch->id,
                                'type' => StockMovementType::In->value,
                                'grade' => $batch->purchase_grade,
                            ],
                            [
                                'product_id' => $batch->product_id,
                                'warehouse_id' => $itemWarehouseId,
                                'created_by' => $userId,
                                'quantity' => $batch->total_kg,
                                'cost_per_unit' => $batch->cost_per_kg,
                                'notes' => "Fixed Grade {$batch->purchase_grade} receipt from {$approvedGrn->grn_number}",
                            ],
                        );

                        $batch->update([
                            'status' => BatchStatus::Sorted,
                            'sorted_at' => now(),
                        ]);
                    }

                    activity()
                        ->performedOn($batch)
                        ->causedBy($userId)
                        ->log('stock_batch.warehouse_confirmed');

                    if ($discrepancyType === 'wastage') {
                        $purchasedQty = $grnItem?->purchased_qty ?? 0.0;
                        $receivedQty = $grnItem?->received_qty ?? 0.0;
                        $diff = $purchasedQty - $receivedQty;

                        if ($diff > 0.0) {
                            app(WastageService::class)->record(new WastageEntryData(
                                productId: $batch->product_id,
                                batchId: $batch->id,
                                grade: 'U',
                                quantity: $diff,
                                costPerKg: (float) $batch->cost_per_kg,
                                reason: WastageReason::TransitDamage,
                                wastageDate: now()->toDateString(),
                                notes: 'Receiving discrepancy wastage: '.($discrepancyNote ?? 'Vendor goods discrepancy'),
                            ), $userId);
                        }
                    }
                });

            $this->updatePurchaserCartReceiptStatus($approvedGrn);
        });
    }

    private function updatePurchaserCartReceiptStatus(GoodsReceived $grn): void
    {
        $hasPendingBatches = StockBatch::where('goods_received_id', $grn->id)
            ->where('warehouse_receive_pending', true)
            ->exists();

        if (! $hasPendingBatches) {
            $cart = $grn->purchaseOrder?->purchaserCart;
            if ($cart && ! $cart->goods_received_at) {
                $cart->update([
                    'goods_received_at' => now(),
                ]);
            }
        }
    }

    private function generateDirectPurchaseBatchReference(): string
    {
        do {
            $reference = 'DP-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(2)));
        } while (StockBatch::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
