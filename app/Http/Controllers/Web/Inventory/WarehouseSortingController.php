<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\DTOs\Inventory\WastageEntryData;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\WastageReason;
use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Services\Inventory\WastageService;
use App\Services\Purchasing\GoodsReceivedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class WarehouseSortingController extends Controller
{
    public function __construct(
        private readonly GoodsReceivedService $grnService,
        private readonly WastageService $wastageService,
    ) {}

    /**
     * Display the warehouse sorting checklist.
     */
    public function index(Request $request): View
    {
        if (! $request->user()->can('warehouse.checklist.view') &&
            ! $request->user()->hasRole('warehouse') &&
            ! $request->user()->hasRole('admin')) {
            abort(403, 'Unauthorized.');
        }

        $date = $request->input('date');

        if (! $date) {
            $tomorrow = Carbon::tomorrow()->format('Y-m-d');
            $today = Carbon::today()->format('Y-m-d');

            $hasTodayOrders = ShopOrder::whereDate('business_date', $today)->where('state', 'approved')->exists();
            $hasTomorrowOrders = ShopOrder::whereDate('business_date', $tomorrow)->where('state', 'approved')->exists();

            if ($hasTomorrowOrders && ! $hasTodayOrders) {
                $date = $tomorrow;
            } else {
                $cutoffTime = today()->setTime(21, 30);
                $date = now()->greaterThan($cutoffTime) ? $tomorrow : $today;
            }
        }

        // Fetch all approved shop orders for the selected date
        $orders = ShopOrder::whereDate('business_date', $date)
            ->where('state', 'approved')
            ->with(['shop', 'items.product', 'items.sortedBy'])
            ->get();

        // Calculate global statistics
        $allApprovedItems = ShopOrderItem::whereHas('order', function ($query) use ($date) {
            $query->whereDate('business_date', $date)
                ->where('state', 'approved');
        })->get();

        $totalItems = $allApprovedItems->count();
        $sortedItems = $allApprovedItems->where('is_sorted', true)->count();
        $globalPercentage = $totalItems > 0 ? (int) round(($sortedItems / $totalItems) * 100) : 0;

        // Fetch Purchase Orders sent to supplier or partially received (pending delivery receipt)
        $purchaseOrders = PurchaseOrder::whereIn('status', [
            POStatus::SentToSupplier,
            POStatus::PartiallyReceived,
            POStatus::Received,
        ])
            ->with(['supplier', 'items.product'])
            ->get();

        // Fetch Stock Batches received on this date
        $stockBatches = StockBatch::whereDate('received_at', $date)
            ->with(['product', 'wastageEntries'])
            ->get();

        $wastageReasons = WastageReason::cases();
        $productGrades = ProductGrade::cases();

        return view('inventory.sorting-checklist.index', compact(
            'date',
            'orders',
            'totalItems',
            'sortedItems',
            'globalPercentage',
            'purchaseOrders',
            'stockBatches',
            'wastageReasons',
            'productGrades'
        ));
    }

    /**
     * Toggle the sorting status / progress of a shop order item.
     */
    public function toggle(Request $request, ShopOrderItem $item): JsonResponse
    {
        // Require inventory sorting process permission
        if (! $request->user()->hasRole('warehouse') &&
            ! $request->user()->hasRole('admin') &&
            ! $request->user()->can('inventory.sorting.process')) {
            abort(403, 'Unauthorized.');
        }

        $status = $request->input('status');

        // Cycle status if not specified (binary toggle for backward compatibility)
        if (! $status) {
            $status = $item->is_sorted ? 'pending' : 'allocated';
        }

        if (! in_array($status, ['pending', 'allocated', 'loaded'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid status.'], 400);
        }

        $item->sorting_status = $status;
        $item->is_sorted = ($status !== 'pending');

        if ($item->is_sorted) {
            $item->sorted_at = now();
            $item->sorted_by = $request->user()->id;
            $item->load('sortedBy');
        } else {
            $item->sorted_at = null;
            $item->sorted_by = null;
        }
        $item->save();

        // Calculate shop-specific progress
        $order = $item->order;
        $orderItems = $order->items;
        $orderTotal = $orderItems->count();
        $orderSorted = $orderItems->where('is_sorted', true)->count();
        $orderPercentage = $orderTotal > 0 ? (int) round(($orderSorted / $orderTotal) * 100) : 0;

        // Calculate global progress
        $allApprovedItems = ShopOrderItem::whereHas('order', function ($query) use ($order) {
            $query->whereDate('business_date', $order->business_date)
                ->where('state', 'approved');
        })->get();

        $globalTotal = $allApprovedItems->count();
        $globalSorted = $allApprovedItems->where('is_sorted', true)->count();
        $globalPercentage = $globalTotal > 0 ? (int) round(($globalSorted / $globalTotal) * 100) : 0;

        return response()->json([
            'success' => true,
            'item' => [
                'id' => $item->id,
                'is_sorted' => $item->is_sorted,
                'sorting_status' => $item->sorting_status,
                'sorted_at' => $item->sorted_at ? $item->sorted_at->format('Y-m-d H:i:s') : null,
                'sorted_at_formatted' => $item->sorted_at ? $item->sorted_at->setTimezone('Asia/Kolkata')->format('h:i A') : null,
                'sorted_by_name' => $item->is_sorted && $item->sortedBy ? $item->sortedBy->name : null,
            ],
            'shop_progress' => [
                'shop_id' => $order->shop_id,
                'sorted' => $orderSorted,
                'total' => $orderTotal,
                'percentage' => $orderPercentage,
            ],
            'global_progress' => [
                'sorted' => $globalSorted,
                'total' => $globalTotal,
                'percentage' => $globalPercentage,
            ],
        ]);
    }

    /**
     * Record a Goods Received Note (GRN) for an approved Purchase Order.
     */
    public function storeGrn(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('warehouse') &&
            ! $request->user()->hasRole('admin') &&
            ! $request->user()->can('purchasing.grn.create')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'received_at' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.received_qty' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $grn = $this->grnService->create(
                GoodsReceivedData::fromRequest($request),
                (int) $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Goods Received Note recorded successfully.',
                'grn_id' => $grn->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record Goods Receipt: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Carry over a stock batch to the next business day.
     */
    public function carryOver(Request $request, StockBatch $batch): JsonResponse
    {
        if (! $request->user()->hasRole('warehouse') &&
            ! $request->user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $targetDate = $request->input('target_date');
        if (! $targetDate) {
            $targetDate = Carbon::parse($batch->received_at)->addDay()->format('Y-m-d');
        }

        try {
            $oldDate = $batch->received_at->format('Y-m-d');
            $batch->update([
                'received_at' => $targetDate,
                'notes' => trim(($batch->notes ? $batch->notes."\n" : '')."Carried over from {$oldDate} to {$targetDate}."),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Stock batch successfully carried over to {$targetDate}.",
                'received_at' => $targetDate,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to carry over stock batch: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Record wastage for a stock batch.
     */
    public function recordWastage(Request $request, StockBatch $batch): JsonResponse
    {
        if (! $request->user()->hasRole('warehouse') &&
            ! $request->user()->hasRole('admin') &&
            ! $request->user()->can('inventory.wastage.record')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:'.$batch->total_kg],
            'reason' => ['required', 'string', 'in:'.implode(',', array_column(WastageReason::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            // Merge internal parameters to match DTO fromRequest structure
            $request->merge([
                'product_id' => $batch->product_id,
                'batch_id' => $batch->id,
                'grade' => ProductGrade::Damage->value,
                'cost_per_kg' => (float) $batch->cost_per_kg,
                'wastage_date' => today()->format('Y-m-d'),
            ]);

            $dto = WastageEntryData::fromRequest($request);
            $this->wastageService->record($dto, (int) $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Wastage entry successfully recorded.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record wastage: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Complete the allocation and update shop order sheet status & notes.
     */
    public function completeAllocation(Request $request, ShopOrder $order): JsonResponse
    {
        if (! $request->user()->hasRole('warehouse') &&
            ! $request->user()->hasRole('admin') &&
            ! $request->user()->can('inventory.sorting.process')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'sorting_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $order->update([
                'is_allocation_completed' => true,
                'sorting_notes' => $request->input('sorting_notes'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Shop order allocation sheet completed successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete order sheet: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the shop orders list (cards) for warehouse allocation.
     */
    public function shopOrders(Request $request): View
    {
        if (! $request->user()->can('warehouse.checklist.view') &&
            ! $request->user()->hasRole('warehouse') &&
            ! $request->user()->hasRole('admin')) {
            abort(403, 'Unauthorized.');
        }

        $date = $request->input('date');

        if (! $date) {
            $tomorrow = Carbon::tomorrow()->format('Y-m-d');
            $today = Carbon::today()->format('Y-m-d');

            $hasTodayOrders = ShopOrder::whereDate('business_date', $today)->where('state', 'approved')->exists();
            $hasTomorrowOrders = ShopOrder::whereDate('business_date', $tomorrow)->where('state', 'approved')->exists();

            if ($hasTomorrowOrders && ! $hasTodayOrders) {
                $date = $tomorrow;
            } else {
                $cutoffTime = today()->setTime(21, 30);
                $date = now()->greaterThan($cutoffTime) ? $tomorrow : $today;
            }
        }

        // Fetch all approved shop orders for the selected date
        $orders = ShopOrder::whereDate('business_date', $date)
            ->where('state', 'approved')
            ->with(['shop', 'items.product', 'items.sortedBy'])
            ->get();

        return view('inventory.sorting-checklist.shop_orders', compact('date', 'orders'));
    }
}
