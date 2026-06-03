<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceived;
use App\Models\PurchaseOrder;
use App\Models\ShopOrder;
use App\Models\StockBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DailyProgressController extends Controller
{
    /**
     * Display the workflow daily progress tracker.
     */
    public function __invoke(Request $request): View
    {
        if (! $request->user()->can('admin.daily-progress.view') &&
            ! $request->user()->hasRole('legacy-admin') &&
            ! $request->user()->hasRole('admin') &&
            ! $request->user()->hasRole('super-admin')) {
            abort(403, 'Unauthorized access to daily progress tracker.');
        }

        $dateInput = $request->input('date');
        $date = $dateInput ? Carbon::parse($dateInput)->format('Y-m-d') : Carbon::today()->format('Y-m-d');

        // 1. Purchase Orders Approved & Received
        $pos = PurchaseOrder::whereDate('order_date', $date)
            ->with(['supplier', 'goodsReceiveds'])
            ->get();
        $approvedPoCount = $pos->where('status', POStatus::Approved)->count();
        $receivedPoCount = $pos->whereIn('status', [POStatus::Received, POStatus::Closed])->count();
        $totalPoCount = $pos->count();

        // 2. Goods Receipts recorded today
        $grns = GoodsReceived::whereDate('created_at', $date)
            ->with(['purchaseOrder.supplier'])
            ->get();
        $grnCount = $grns->count();

        // 3. Stock batches created/received today
        $batches = StockBatch::whereDate('received_at', $date)->get();
        $totalStockBatches = $batches->count();
        $totalStockKg = (float) $batches->sum('total_kg');
        $pendingBatchesCount = $batches->where('status', 'pending')->count();

        // 4. Shop Orders Consolidation for delivery on this date
        $orders = ShopOrder::whereDate('business_date', $date)
            ->with(['shop', 'items.product', 'items.sortedBy', 'deliveredBy'])
            ->get();

        $totalOrdersCount = $orders->count();
        $approvedOrdersCount = $orders->where('state', 'approved')->count();
        $allocationCompletedCount = $orders->where('is_allocation_completed', true)->count();
        $deliveredOrdersCount = $orders->where('is_delivered', true)->count();

        // Calculate item allocation details across all orders
        $totalItemsCount = 0;
        $allocatedItemsCount = 0;
        $loadedItemsCount = 0;

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $totalItemsCount++;
                if ($item->is_sorted) {
                    $allocatedItemsCount++;
                }
                if ($item->sorting_status === 'loaded') {
                    $loadedItemsCount++;
                }
            }
        }

        // Calculate shortages & cash collection totals for today
        $totalShortagesValue = (float) $orders->sum('total_shortage_value');
        $totalCashCollected = (float) $orders->sum('cash_collected');
        $totalCashDiscrepancies = (float) $orders->sum('cash_discrepancy');

        // Compile workflow steps checklist status
        $stages = [
            'po_approved' => [
                'name' => '1. Purchase Orders Approved',
                'description' => "{$approvedPoCount} of {$totalPoCount} POs approved",
                'completed' => $totalPoCount > 0 && $approvedPoCount === 0 && $receivedPoCount === $totalPoCount ? true : ($totalPoCount > 0 && $approvedPoCount > 0),
            ],
            'goods_received' => [
                'name' => '2-4. Goods Received & GRN Created',
                'description' => "{$grnCount} GRNs created, {$receivedPoCount} POs checked-in",
                'completed' => $grnCount > 0,
            ],
            'stock_available' => [
                'name' => '5. Stock Available in Warehouse',
                'description' => "{$totalStockBatches} batches received ({$totalStockKg} kg)",
                'completed' => $totalStockKg > 0,
            ],
            'shop_orders' => [
                'name' => '6. Shop Orders Approved',
                'description' => "{$approvedOrdersCount} of {$totalOrdersCount} orders approved",
                'completed' => $totalOrdersCount > 0 && $approvedOrdersCount === $totalOrdersCount,
            ],
            'items_picked' => [
                'name' => '7-8. Items Picked & Allocated',
                'description' => "{$allocatedItemsCount} of {$totalItemsCount} items sorted/allocated",
                'completed' => $totalItemsCount > 0 && $allocatedItemsCount === $totalItemsCount,
            ],
            'ready_dispatch' => [
                'name' => '9. Ready for Dispatch (Loaded)',
                'description' => "{$loadedItemsCount} of {$totalItemsCount} items loaded into trucks",
                'completed' => $totalItemsCount > 0 && $loadedItemsCount === $totalItemsCount,
            ],
            'shipped' => [
                'name' => '10. Shipped / Finalized',
                'description' => "{$allocationCompletedCount} of {$totalOrdersCount} shop allocation sheets finalized",
                'completed' => $totalOrdersCount > 0 && $allocationCompletedCount === $totalOrdersCount,
            ],
            'delivered' => [
                'name' => '11. Delivered & Checked-in',
                'description' => "{$deliveredOrdersCount} of {$totalOrdersCount} shop branches checked-in",
                'completed' => $totalOrdersCount > 0 && $deliveredOrdersCount === $totalOrdersCount,
            ],
        ];

        return view('admin.daily_progress.index', compact(
            'date',
            'pos',
            'grns',
            'batches',
            'orders',
            'totalOrdersCount',
            'approvedOrdersCount',
            'allocationCompletedCount',
            'deliveredOrdersCount',
            'totalItemsCount',
            'allocatedItemsCount',
            'loadedItemsCount',
            'totalShortagesValue',
            'totalCashCollected',
            'totalCashDiscrepancies',
            'stages'
        ));
    }
}
