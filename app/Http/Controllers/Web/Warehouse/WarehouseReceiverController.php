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
use App\Models\GoodsReceived;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Inventory\WastageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WarehouseReceiverController extends Controller
{
    /**
     * Show the warehouse receive checklist — pending vendor sheets (GRNs) and approved shop orders.
     */
    public function index(Request $request): View
    {
        $this->authorizeReceiverAccess($request);

        $date = $request->input('date', Carbon::today()->format('Y-m-d'));

        // All pending vendor sheets (GRNs) awaiting warehouse receipt confirmation
        $pendingGrns = GoodsReceived::where('status', 'pending_approval')
            ->whereDate('received_at', $date)
            ->with(['purchaseOrder.supplier', 'purchaseOrder.purchaserCart.user', 'items.product.category'])
            ->orderBy('created_at', 'asc')
            ->get();

        // Fetch pending batches for test compatibility
        $pendingBatches = StockBatch::where('warehouse_receive_pending', true)
            ->whereDate('received_at', $date)
            ->with(['product'])
            ->orderBy('created_at', 'asc')
            ->get();

        // Also fetch recently confirmed batches (today) for context
        $confirmedBatches = StockBatch::where('warehouse_receive_pending', false)
            ->whereDate('received_at', $date)
            ->with(['product', 'warehouseConfirmedBy', 'warehouse'])
            ->orderBy('warehouse_confirmed_at', 'desc')
            ->get();

        // Fetch recent "In" movements
        $inMovements = StockMovement::whereIn('type', [
            StockMovementType::In,
            StockMovementType::SaleReversal,
        ])
            ->with(['product', 'batch'])
            ->orderBy('created_at', 'desc')
            ->take(15)
            ->get();

        // Map confirmed batches as part of inflows
        $inflows = collect();

        foreach ($inMovements as $mov) {
            $inflows->push((object) [
                'product_name' => $mov->product->name,
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
            ->with(['product'])
            ->orderBy('created_at', 'desc')
            ->take(15)
            ->get();

        // Fetch active stock levels
        $stockLevels = app(StockMovementRepository::class)->currentStockByProductAndGrade();

        // Calculate latest activity timestamp for each stock level
        foreach ($stockLevels as $item) {
            if ($item->grade === 'Unsorted') {
                $latestBatch = StockBatch::where('product_id', $item->product_id)
                    ->where('status', BatchStatus::Pending)
                    ->orderBy('created_at', 'desc')
                    ->first();
                $item->latest_activity = $latestBatch ? $latestBatch->created_at : null;
            } else {
                $latestMovement = StockMovement::where('product_id', $item->product_id)
                    ->where('grade', $item->grade)
                    ->orderBy('created_at', 'desc')
                    ->first();
                $item->latest_activity = $latestMovement ? $latestMovement->created_at : null;
            }
        }

        // Build stock map for fulfillment calculations
        $stockMap = [];
        foreach ($stockLevels as $level) {
            $gradeVal = is_string($level->grade) ? $level->grade : ($level->grade->value ?? 'U');
            $stockMap[$level->product_id][$gradeVal] = (float) $level->current_stock;
        }

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
                $grade = $item->product_grade ?? 'A';
                $stock = $stockMap[$item->product_id][$grade] ?? 0.0;

                $totalRequested += $qty;
                $totalAvailableStock += min($qty, max(0.0, $stock));
            }

            $order->fulfillment_percentage = $totalRequested > 0
                ? (int) round(($totalAvailableStock / $totalRequested) * 100)
                : 100;
        }

        $warehouses = Warehouse::active()->orderBy('name')->get();

        return view('warehouse-receiver.checklist', compact(
            'date',
            'pendingBatches',
            'pendingGrns',
            'confirmedBatches',
            'inflows',
            'inMovements',
            'outMovements',
            'stockLevels',
            'approvedOrders',
            'shopOrders',
            'warehouses',
        ));
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

        $userId = (int) $request->user()->id;

        DB::transaction(function () use ($grn, $validated, $userId): void {
            // 1. Update GRN items
            foreach ($validated['items'] as $itemId => $itemData) {
                $item = $grn->items()->findOrFail((int) $itemId);
                $originalPurchasedQty = (float) ($item->purchaseOrderItem?->quantity ?? $item->received_qty);

                $receivedQty = (float) $itemData['received_qty'];
                $variance = $receivedQty - (float) ($item->purchaseOrderItem?->quantity ?? 0.0);

                $item->update([
                    'received_qty' => $receivedQty,
                    'variance' => $variance,
                    'purchased_qty' => $originalPurchasedQty,
                    'discrepancy_type' => $itemData['discrepancy_type'],
                    'discrepancy_note' => $itemData['discrepancy_note'],
                ]);
            }

            // 2. Approve GRN and generate stock batches
            $approveAction = app(ApproveGoodsReceiptAction::class);
            $grn = $approveAction->execute($grn, $userId);

            // Find batches created for this GRN and update them to confirmed/received
            $batches = StockBatch::where('goods_received_id', $grn->id)->get();
            foreach ($batches as $batch) {
                $grnItem = $grn->items->where('product_id', $batch->product_id)->first();
                $discrepancyType = $grnItem?->discrepancy_type ?? 'none';
                $discrepancyNote = $grnItem?->discrepancy_note;

                $itemWarehouseId = (int) ($validated['items'][$grnItem->id]['warehouse_id'] ?? $validated['warehouse_id']);

                $batch->update([
                    'warehouse_id' => $itemWarehouseId,
                    'warehouse_receive_pending' => false,
                    'warehouse_confirmed_at' => now(),
                    'warehouse_confirmed_by' => $userId,
                ]);

                activity()
                    ->performedOn($batch)
                    ->causedBy($userId)
                    ->log('stock_batch.warehouse_confirmed');

                // If discrepancy is wastage, record a WastageEntry
                if ($discrepancyType === 'wastage') {
                    $purchasedQty = $grnItem?->purchased_qty ?? 0.0;
                    $receivedQty = $grnItem?->received_qty ?? 0.0;
                    $diff = $purchasedQty - $receivedQty;

                    if ($diff > 0.0) {
                        $wastageService = app(WastageService::class);
                        $wastageService->record(new WastageEntryData(
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
            }
            $this->updatePurchaserCartReceiptStatus($grn);
        });

        return redirect()
            ->route('warehouse.receiver.checklist', ['date' => $grn->received_at->format('Y-m-d')])
            ->with('success', 'Vendor sheet received and stock moved to inventory.');
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

        $date = $request->input('date', Carbon::today()->format('Y-m-d'));
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

        $stockLevels = app(StockMovementRepository::class)->currentStockByProductAndGrade();

        // Group by product_id and grade
        $stockMap = [];
        foreach ($stockLevels as $level) {
            $gradeVal = is_string($level->grade) ? $level->grade : ($level->grade->value ?? 'U');
            $stockMap[$level->product_id][$gradeVal] = (float) $level->current_stock;
        }

        foreach ($order->items as $item) {
            $gradeVal = $item->product_grade ?? 'A';
            $item->inventory_stock = $stockMap[$item->product_id][$gradeVal] ?? 0.0;
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
            DB::transaction(function () use ($item, $discrepancyType, $discrepancyNote, $approvedQty, $loadedQty, $request) {
                $userId = $request->user()->id;

                // Find batch to deduct from
                $latestIn = StockMovement::where('product_id', $item->product_id)
                    ->where('grade', $item->product_grade ?? 'A')
                    ->where('type', StockMovementType::In)
                    ->latest()
                    ->first();

                $batchId = $latestIn?->batch_id;

                if (! $batchId) {
                    $anyBatch = StockBatch::where('product_id', $item->product_id)->latest()->first();
                    $batchId = $anyBatch?->id;
                }

                if (! $batchId) {
                    throw new \RuntimeException("No stock batches found for product {$item->product->name} to loadout from.");
                }

                $batch = StockBatch::find($batchId);

                $item->update([
                    'loaded_qty' => $loadedQty,
                    'loadout_discrepancy_type' => $discrepancyType,
                    'loadout_discrepancy_note' => $discrepancyNote,
                    'sorting_status' => 'loaded',
                    'is_sorted' => true,
                    'sorted_at' => now(),
                    'sorted_by' => $userId,
                ]);

                StockMovement::create([
                    'batch_id' => $batchId,
                    'product_id' => $item->product_id,
                    'created_by' => $userId,
                    'grade' => $item->product_grade ?? 'A',
                    'type' => StockMovementType::Out->value,
                    'quantity' => $loadedQty,
                    'cost_per_unit' => $latestIn?->cost_per_unit ?? 0,
                    'warehouse_id' => $batch?->warehouse_id,
                    'notes' => "Loadout: Shop Order {$item->order->order_number} to {$item->order->shop->name}",
                ]);

                // Create wastage entry if type is wastage
                if ($discrepancyType === 'wastage') {
                    $diff = $approvedQty - $loadedQty;
                    if ($diff > 0) {
                        $wastageService = app(WastageService::class);
                        $wastageService->record(new WastageEntryData(
                            productId: $item->product_id,
                            batchId: $batchId,
                            grade: $item->product_grade ?? 'A',
                            quantity: $diff,
                            costPerKg: (float) ($latestIn?->cost_per_unit ?? 0.0),
                            reason: WastageReason::SortingDamage,
                            wastageDate: now()->toDateString(),
                            notes: 'Loadout discrepancy wastage: '.($discrepancyNote ?? 'Order loadout discrepancy'),
                        ), $userId);

                        StockMovement::create([
                            'batch_id' => $batchId,
                            'product_id' => $item->product_id,
                            'created_by' => $userId,
                            'grade' => $item->product_grade ?? 'A',
                            'type' => StockMovementType::Wastage->value,
                            'quantity' => $diff,
                            'cost_per_unit' => $latestIn?->cost_per_unit ?? 0,
                            'warehouse_id' => $batch?->warehouse_id,
                            'notes' => "Loadout discrepancy wastage: Shop Order {$item->order->order_number} to {$item->order->shop->name}",
                        ]);
                    }
                } elseif ($discrepancyType === 'other') {
                    $diff = $approvedQty - $loadedQty;
                    if ($diff > 0) {
                        StockMovement::create([
                            'batch_id' => $batchId,
                            'product_id' => $item->product_id,
                            'created_by' => $userId,
                            'grade' => $item->product_grade ?? 'A',
                            'type' => StockMovementType::Adjustment->value,
                            'quantity' => $diff,
                            'cost_per_unit' => $latestIn?->cost_per_unit ?? 0,
                            'warehouse_id' => $batch?->warehouse_id,
                            'notes' => "Loadout discrepancy adjustment: Shop Order {$item->order->order_number} to {$item->order->shop->name}",
                        ]);
                    }
                }
            });

            return redirect()->back()->with('success', "{$item->product->name} marked as loaded and reduced from inventory.");
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([$e->getMessage()]);
        }
    }

    /**
     * Mark all pending items in a shop order as loaded (with default quantities).
     */
    public function loadoutOrderAll(ShopOrder $order, Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        $pendingItems = $order->items()->where('sorting_status', '!=', 'loaded')->get();

        if ($pendingItems->isEmpty()) {
            return redirect()->back()->withErrors(['All items in this order are already loaded.']);
        }

        try {
            DB::transaction(function () use ($pendingItems, $order, $request) {
                $userId = $request->user()->id;

                // Load current stock map
                $stockLevels = app(StockMovementRepository::class)->currentStockByProductAndGrade();
                $stockMap = [];
                foreach ($stockLevels as $level) {
                    $gradeVal = is_string($level->grade) ? $level->grade : ($level->grade->value ?? 'U');
                    $stockMap[$level->product_id][$gradeVal] = (float) $level->current_stock;
                }

                foreach ($pendingItems as $item) {
                    $approvedQty = $item->approved_qty > 0 ? (float) $item->approved_qty : (float) $item->requested_qty;
                    $gradeVal = $item->product_grade ?? 'A';
                    $availableStock = $stockMap[$item->product_id][$gradeVal] ?? 0.0;

                    // Load what is available, bounded by 0 and approvedQty
                    $qtyToLoad = min($approvedQty, max(0.0, $availableStock));

                    // Find batch to deduct from
                    $latestIn = StockMovement::where('product_id', $item->product_id)
                        ->where('grade', $item->product_grade ?? 'A')
                        ->where('type', StockMovementType::In)
                        ->latest()
                        ->first();

                    $batchId = $latestIn?->batch_id;

                    if (! $batchId) {
                        $anyBatch = StockBatch::where('product_id', $item->product_id)->latest()->first();
                        $batchId = $anyBatch?->id;
                    }

                    if (! $batchId) {
                        throw new \RuntimeException("No stock batches found for product {$item->product->name} to loadout from.");
                    }

                    $batch = StockBatch::find($batchId);

                    $discrepancyType = 'none';
                    $discrepancyNote = null;
                    $diff = $approvedQty - $qtyToLoad;

                    if ($diff > 0) {
                        $discrepancyType = 'other';
                        $discrepancyNote = 'Auto-loaded available stock (inventory shortage)';
                    }

                    $item->update([
                        'loaded_qty' => $qtyToLoad,
                        'loadout_discrepancy_type' => $discrepancyType,
                        'loadout_discrepancy_note' => $discrepancyNote,
                        'sorting_status' => 'loaded',
                        'is_sorted' => true,
                        'sorted_at' => now(),
                        'sorted_by' => $userId,
                    ]);

                    if ($qtyToLoad > 0) {
                        StockMovement::create([
                            'batch_id' => $batchId,
                            'product_id' => $item->product_id,
                            'created_by' => $userId,
                            'grade' => $item->product_grade ?? 'A',
                            'type' => StockMovementType::Out->value,
                            'quantity' => $qtyToLoad,
                            'cost_per_unit' => $latestIn?->cost_per_unit ?? 0,
                            'warehouse_id' => $batch?->warehouse_id,
                            'notes' => "Loadout: Shop Order {$order->order_number} to {$order->shop->name}",
                        ]);
                    }

                    if ($discrepancyType === 'other' && $diff > 0) {
                        StockMovement::create([
                            'batch_id' => $batchId,
                            'product_id' => $item->product_id,
                            'created_by' => $userId,
                            'grade' => $item->product_grade ?? 'A',
                            'type' => StockMovementType::Adjustment->value,
                            'quantity' => $diff,
                            'cost_per_unit' => $latestIn?->cost_per_unit ?? 0,
                            'warehouse_id' => $batch?->warehouse_id,
                            'notes' => "Loadout discrepancy adjustment: Shop Order {$order->order_number} to {$order->shop->name} ({$discrepancyNote})",
                        ]);
                    }
                }
            });

            return redirect()->back()->with('success', "All {$pendingItems->count()} pending item(s) processed for loadout based on available stock.");
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

        $pendingCount = $order->items()->where('sorting_status', '!=', 'loaded')->count();
        if ($pendingCount > 0) {
            return redirect()->back()->withErrors(["Cannot dispatch: {$pendingCount} items are not loaded yet."]);
        }

        DB::transaction(function () use ($order, $request): void {
            $order->update([
                'delivery_status' => 'in_transit',
                'is_allocation_completed' => true,
            ]);

            activity()
                ->performedOn($order)
                ->causedBy($request->user()->id)
                ->log('shop_order.dispatched');
        });

        return redirect()
            ->route('warehouse.receiver.checklist', ['date' => $order->business_date->format('Y-m-d')])
            ->with('success', "Order {$order->order_number} marked out for delivery.");
    }

    /**
     * Dispatch order as partial, marking all remaining items as not loaded (0 quantity discrepancy).
     */
    public function dispatchPartialOrder(ShopOrder $order, Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        if ($order->delivery_status !== 'pending') {
            return redirect()->back()->withErrors(['This order is already dispatched or completed.']);
        }

        $pendingItems = $order->items()->where('sorting_status', '!=', 'loaded')->get();

        try {
            DB::transaction(function () use ($pendingItems, $order, $request) {
                $userId = $request->user()->id;

                foreach ($pendingItems as $item) {
                    $approvedQty = $item->approved_qty > 0 ? (float) $item->approved_qty : (float) $item->requested_qty;

                    // Since we are dispatching without loading these items, loaded_qty is 0
                    $qtyToLoad = 0.0;

                    // Find batch to deduct from/log discrepancy
                    $latestIn = StockMovement::where('product_id', $item->product_id)
                        ->where('grade', $item->product_grade ?? 'A')
                        ->where('type', StockMovementType::In)
                        ->latest()
                        ->first();

                    $batchId = $latestIn?->batch_id;

                    if (! $batchId) {
                        $anyBatch = StockBatch::where('product_id', $item->product_id)->latest()->first();
                        $batchId = $anyBatch?->id;
                    }

                    if (! $batchId) {
                        throw new \RuntimeException("No stock batches found for product {$item->product->name} to log discrepancy.");
                    }

                    $batch = StockBatch::find($batchId);

                    $discrepancyType = 'other';
                    $discrepancyNote = 'Not loaded (partial order dispatch)';
                    $diff = $approvedQty; // entire quantity is discrepancy

                    $item->update([
                        'loaded_qty' => $qtyToLoad,
                        'loadout_discrepancy_type' => $discrepancyType,
                        'loadout_discrepancy_note' => $discrepancyNote,
                        'sorting_status' => 'loaded',
                        'is_sorted' => true,
                        'sorted_at' => now(),
                        'sorted_by' => $userId,
                    ]);

                    // Record adjustment stock movement for the difference
                    if ($diff > 0) {
                        StockMovement::create([
                            'batch_id' => $batchId,
                            'product_id' => $item->product_id,
                            'created_by' => $userId,
                            'grade' => $item->product_grade ?? 'A',
                            'type' => StockMovementType::Adjustment->value,
                            'quantity' => $diff,
                            'cost_per_unit' => $latestIn?->cost_per_unit ?? 0,
                            'warehouse_id' => $batch?->warehouse_id,
                            'notes' => "Loadout discrepancy adjustment: Shop Order {$order->order_number} to {$order->shop->name} ({$discrepancyNote})",
                        ]);
                    }
                }

                // Transition order to dispatched state
                $order->update([
                    'delivery_status' => 'in_transit',
                    'is_allocation_completed' => true,
                ]);

                activity()
                    ->performedOn($order)
                    ->causedBy($userId)
                    ->log('shop_order.dispatched_partial');
            });

            return redirect()
                ->route('warehouse.receiver.checklist', ['date' => $order->business_date->format('Y-m-d')])
                ->with('success', "Order {$order->order_number} partially dispatched and marked as out for delivery.");
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([$e->getMessage()]);
        }
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
}
