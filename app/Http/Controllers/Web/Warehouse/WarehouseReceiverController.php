<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Warehouse;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\StockMovementType;
use App\Http\Controllers\Controller;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WarehouseReceiverController extends Controller
{
    /**
     * Show the warehouse receive checklist — all stock batches pending physical receipt confirmation.
     */
    public function index(Request $request): View
    {
        $this->authorizeReceiverAccess($request);

        $date = $request->input('date', Carbon::today()->format('Y-m-d'));

        // All batches awaiting warehouse confirmation for the selected date
        $pendingBatches = StockBatch::where('warehouse_receive_pending', true)
            ->whereDate('received_at', $date)
            ->with(['product.defaultWarehouse', 'createdBy'])
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

        $warehouses = Warehouse::active()->orderBy('name')->get();

        return view('warehouse-receiver.checklist', compact(
            'date',
            'pendingBatches',
            'confirmedBatches',
            'inMovements',
            'outMovements',
            'stockLevels',
            'approvedOrders',
            'warehouses',
        ));
    }

    /**
     * Confirm physical receipt of a stock batch.
     */
    public function confirm(StockBatch $batch, Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        if (! $batch->warehouse_receive_pending) {
            return redirect()->back()->withErrors(['This batch has already been confirmed.']);
        }

        $validated = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
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

        try {
            DB::transaction(function () use ($item, $request) {
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
                    'sorting_status' => 'loaded',
                    'is_sorted' => true,
                    'sorted_at' => now(),
                    'sorted_by' => $request->user()->id,
                ]);

                StockMovement::create([
                    'batch_id' => $batchId,
                    'product_id' => $item->product_id,
                    'created_by' => $request->user()->id,
                    'grade' => $item->product_grade ?? 'A',
                    'type' => StockMovementType::Out->value,
                    'quantity' => $item->approved_qty > 0 ? $item->approved_qty : $item->requested_qty,
                    'cost_per_unit' => $latestIn?->cost_per_unit ?? 0,
                    'warehouse_id' => $batch?->warehouse_id,
                    'notes' => "Loadout: Shop Order {$item->order->order_number} to {$item->order->shop->name}",
                ]);
            });

            return redirect()->back()->with('success', "{$item->product->name} marked as loaded and reduced from inventory.");
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([$e->getMessage()]);
        }
    }

    /**
     * Mark all pending items in a shop order as loaded and reduce from inventory.
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

                foreach ($pendingItems as $item) {
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
                        'quantity' => $item->approved_qty > 0 ? $item->approved_qty : $item->requested_qty,
                        'cost_per_unit' => $latestIn?->cost_per_unit ?? 0,
                        'warehouse_id' => $batch?->warehouse_id,
                        'notes' => "Loadout: Shop Order {$order->order_number} to {$order->shop->name}",
                    ]);
                }
            });

            return redirect()->back()->with('success', "All {$pendingItems->count()} pending item(s) marked as loaded and reduced from inventory.");
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([$e->getMessage()]);
        }
    }
}
