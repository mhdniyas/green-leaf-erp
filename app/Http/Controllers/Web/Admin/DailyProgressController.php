<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceived;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
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
            ! $request->user()->hasRole('admin')) {
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

        // 4. Shop order flow for the selected date
        $orders = ShopOrder::whereDate('business_date', $date)
            ->with(['shop', 'items', 'deliveredBy'])
            ->get();

        $shops = Shop::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'status']);

        $totalOrdersCount = $orders->count();
        $approvedOrdersCount = $orders->where('state', 'approved')->count();
        $outForDeliveryOrdersCount = $orders
            ->filter(fn (ShopOrder $order): bool => $order->warehouseWorkflowStage() === 'in_transit')
            ->count();
        $deliveredOrdersCount = $orders->where('is_delivered', true)->count();
        $discrepancyOrdersCount = $orders
            ->filter(fn (ShopOrder $order): bool => $this->orderHasDiscrepancy($order))
            ->count();

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

        $totalShortagesValue = (float) $orders->sum('total_shortage_value');
        $totalCashCollected = (float) $orders->sum('cash_collected');
        $totalCashDiscrepancies = (float) $orders->sum('cash_discrepancy');
        $shopsWithOrdersCount = $shops
            ->filter(fn (Shop $shop): bool => $orders->contains('shop_id', $shop->id))
            ->count();

        $shopProgressRows = $shops->map(function (Shop $shop) use ($orders): array {
            $shopOrders = $orders
                ->where('shop_id', $shop->id)
                ->values();

            $totalOrders = $shopOrders->count();
            $approvedOrders = $shopOrders->where('state', 'approved')->count();
            $outForDeliveryOrders = $shopOrders
                ->filter(fn (ShopOrder $order): bool => $order->warehouseWorkflowStage() === 'in_transit')
                ->count();
            $deliveredOrders = $shopOrders->where('is_delivered', true)->count();
            $discrepancyOrders = $shopOrders
                ->filter(fn (ShopOrder $order): bool => $this->orderHasDiscrepancy($order))
                ->count();
            $pendingReviewOrders = $shopOrders
                ->filter(fn (ShopOrder $order): bool => $order->hasPendingDeliveryReview())
                ->count();
            $latestOrder = $shopOrders->sortByDesc('created_at')->first();

            return [
                'shop' => $shop,
                'orders' => $shopOrders,
                'total_orders' => $totalOrders,
                'approved_orders' => $approvedOrders,
                'out_for_delivery_orders' => $outForDeliveryOrders,
                'delivered_orders' => $deliveredOrders,
                'discrepancy_orders' => $discrepancyOrders,
                'pending_review_orders' => $pendingReviewOrders,
                'cash_discrepancy_total' => round((float) $shopOrders->sum('cash_discrepancy'), 2),
                'shortage_total' => round((float) $shopOrders->sum('total_shortage_value'), 2),
                'latest_order_number' => $latestOrder?->order_number,
                'status_label' => $this->shopStatusLabel(
                    totalOrders: $totalOrders,
                    deliveredOrders: $deliveredOrders,
                    outForDeliveryOrders: $outForDeliveryOrders,
                    approvedOrders: $approvedOrders,
                    discrepancyOrders: $discrepancyOrders,
                    pendingReviewOrders: $pendingReviewOrders,
                ),
                'progress_percent' => $totalOrders === 0
                    ? 0
                    : min(100, (int) round((($approvedOrders * 0.35) + ($outForDeliveryOrders * 0.75) + ($deliveredOrders * 1.0)) / $totalOrders * 100)),
            ];
        });

        $flowStages = [
            'po_approved' => [
                'label' => 'PO Approved',
                'count' => $approvedPoCount,
                'meta' => $totalPoCount > 0 ? "of {$totalPoCount} purchase orders" : 'No purchase orders',
                'tone' => $approvedPoCount > 0 ? 'emerald' : 'slate',
            ],
            'goods_received' => [
                'label' => 'GRN Logged',
                'count' => $grnCount,
                'meta' => "{$receivedPoCount} PO receipts closed",
                'tone' => $grnCount > 0 ? 'sky' : 'slate',
            ],
            'stock_available' => [
                'label' => 'Stock Ready',
                'count' => $totalStockBatches,
                'meta' => number_format($totalStockKg, 2).' kg available',
                'tone' => $totalStockKg > 0 ? 'indigo' : 'slate',
            ],
            'shop_orders' => [
                'label' => 'Orders Raised',
                'count' => $totalOrdersCount,
                'meta' => "{$shopsWithOrdersCount} shops active",
                'tone' => $totalOrdersCount > 0 ? 'amber' : 'slate',
            ],
            'approved_orders' => [
                'label' => 'Approved',
                'count' => $approvedOrdersCount,
                'meta' => "{$allocatedItemsCount} / {$totalItemsCount} items allocated",
                'tone' => $approvedOrdersCount > 0 ? 'emerald' : 'slate',
            ],
            'out_for_delivery' => [
                'label' => 'Out for Delivery',
                'count' => $outForDeliveryOrdersCount,
                'meta' => "{$loadedItemsCount} / {$totalItemsCount} items loaded",
                'tone' => $outForDeliveryOrdersCount > 0 ? 'sky' : 'slate',
            ],
            'delivered' => [
                'label' => 'Delivered',
                'count' => $deliveredOrdersCount,
                'meta' => 'Cash collected Rs. '.number_format($totalCashCollected, 2),
                'tone' => $deliveredOrdersCount > 0 ? 'teal' : 'slate',
            ],
            'discrepancies' => [
                'label' => 'Discrepancies',
                'count' => $discrepancyOrdersCount,
                'meta' => 'Variance Rs. '.number_format($totalCashDiscrepancies, 2),
                'tone' => $discrepancyOrdersCount > 0 ? 'rose' : 'emerald',
            ],
        ];

        return view('admin.daily_progress.index', compact(
            'date',
            'flowStages',
            'shopProgressRows',
            'shopsWithOrdersCount',
            'totalOrdersCount',
            'approvedOrdersCount',
            'outForDeliveryOrdersCount',
            'deliveredOrdersCount',
            'discrepancyOrdersCount',
            'totalShortagesValue',
            'totalCashCollected',
            'totalCashDiscrepancies',
            'pendingBatchesCount'
        ));
    }

    private function orderHasDiscrepancy(ShopOrder $order): bool
    {
        if (in_array($order->delivery_status, ['pending_approval', 'partially_delivered', 'delivery_issue'], true)) {
            return true;
        }

        if (abs((float) $order->cash_discrepancy) > 0.01 || (float) $order->total_shortage_value > 0.01) {
            return true;
        }

        return $order->items->contains(function (ShopOrderItem $item): bool {
            return (float) $item->shortage_qty > 0.01
                || filled($item->delivery_discrepancy_type)
                || filled($item->delivery_discrepancy_note)
                || filled($item->loadout_discrepancy_type)
                || filled($item->loadout_discrepancy_note);
        });
    }

    private function shopStatusLabel(
        int $totalOrders,
        int $deliveredOrders,
        int $outForDeliveryOrders,
        int $approvedOrders,
        int $discrepancyOrders,
        int $pendingReviewOrders,
    ): string {
        if ($totalOrders === 0) {
            return 'No orders';
        }

        if ($discrepancyOrders > 0) {
            return $pendingReviewOrders > 0 ? 'Needs admin review' : 'Discrepancy flagged';
        }

        if ($deliveredOrders === $totalOrders) {
            return 'Completed';
        }

        if ($outForDeliveryOrders > 0) {
            return 'Out for delivery';
        }

        if ($approvedOrders > 0) {
            return 'Approved for warehouse';
        }

        return 'Order in progress';
    }
}
