<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Controller;
use App\Models\EmployeeAttendance;
use App\Models\GoodsReceived;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\ShopAccountingEntry;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
        $previousDate = Carbon::parse($date)->subDay()->format('Y-m-d');

        $pos = PurchaseOrder::whereDate('order_date', $date)
            ->with(['supplier', 'goodsReceiveds'])
            ->get();
        $previousPoCount = PurchaseOrder::whereDate('order_date', $previousDate)->count();
        $approvedPoCount = $pos->filter(fn (PurchaseOrder $purchaseOrder): bool => $this->statusValue($purchaseOrder->status) === POStatus::Approved->value)->count();
        $receivedPoCount = $pos->filter(fn (PurchaseOrder $purchaseOrder): bool => in_array($this->statusValue($purchaseOrder->status), [POStatus::Received->value, POStatus::Closed->value], true))->count();
        $openPoCount = $pos->filter(fn (PurchaseOrder $purchaseOrder): bool => in_array($this->statusValue($purchaseOrder->status), [POStatus::Draft->value, POStatus::Approved->value, POStatus::SentToSupplier->value, POStatus::PartiallyReceived->value], true))->count();
        $totalPoCount = $pos->count();

        $grns = GoodsReceived::whereDate('received_at', $date)
            ->with(['purchaseOrder.supplier', 'items'])
            ->get();
        $grnCount = $grns->count();
        $grnPendingApprovalCount = $grns->whereIn('status', ['pending_approval', 'recheck_required'])->count();
        $grnApprovedCount = $grns->whereIn('status', ['approved', 'received', 'closed'])->count();
        $receivedKg = (float) $grns->flatMap->items->sum('received_qty');

        $batches = StockBatch::whereDate('received_at', $date)
            ->with(['product', 'warehouse'])
            ->get();
        $totalStockBatches = $batches->count();
        $totalStockKg = (float) $batches->sum('total_kg');
        $pendingBatchesCount = $batches->filter(fn (StockBatch $batch): bool => $this->statusValue($batch->status) === 'pending')->count();
        $sortedBatchesCount = $batches->filter(fn (StockBatch $batch): bool => $this->statusValue($batch->status) === 'sorted')->count();
        $closedBatchesCount = $batches->filter(fn (StockBatch $batch): bool => $this->statusValue($batch->status) === 'closed')->count();

        $orders = ShopOrder::whereDate('business_date', $date)
            ->with(['shop', 'items.product', 'deliveredBy', 'invoice'])
            ->get();
        $previousOrdersCount = ShopOrder::whereDate('business_date', $previousDate)->count();

        $invoices = ShopInvoice::whereDate('business_date', $date)
            ->with(['shop', 'order'])
            ->get();

        $accountingEntries = ShopAccountingEntry::whereDate('business_date', $date)
            ->with(['shop', 'submittedBy', 'reviewedBy'])
            ->get();

        $attendances = EmployeeAttendance::whereDate('attendance_date', $date)
            ->with(['shop', 'employee'])
            ->get();

        $shops = Shop::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'status']);

        $activeShopCount = $shops->count();
        $totalOrdersCount = $orders->count();
        $submittedOrdersCount = $orders->whereIn('state', ['submitted', 'update_requested'])->count();
        $approvedOrdersCount = $orders->where('state', 'approved')->count();
        $lateOrdersCount = $orders->where('is_late', true)->count();
        $pendingRevisionOrdersCount = $orders->where('has_pending_revision', true)->count();
        $outForDeliveryOrdersCount = $orders
            ->filter(fn (ShopOrder $order): bool => $order->warehouseWorkflowStage() === 'in_transit')
            ->count();
        $packingOrdersCount = $orders
            ->filter(fn (ShopOrder $order): bool => in_array($order->warehouseWorkflowStage(), ['approved', 'packing', 'ready_for_dispatch'], true))
            ->count();
        $deliveredOrdersCount = $orders->where('is_delivered', true)->count();
        $pendingDeliveryReviewCount = $orders
            ->filter(fn (ShopOrder $order): bool => $order->hasPendingDeliveryReview())
            ->count();
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
        $shopsWithoutOrdersCount = max(0, $activeShopCount - $shopsWithOrdersCount);

        $generatedInvoicesCount = $invoices->count();
        $paidInvoicesCount = $invoices->where('payment_status', 'paid')->count();
        $unpaidInvoicesCount = $invoices->whereIn('payment_status', ['unpaid', 'pending'])->count();
        $partialInvoicesCount = $invoices->whereIn('payment_status', ['partial', 'partially_paid'])->count();
        $invoiceTotal = (float) $invoices->sum('final_total');
        $invoicePaidTotal = (float) $invoices->sum('paid_amount');
        $invoiceBalanceTotal = (float) $invoices->sum('balance_amount');

        $accountingSubmittedCount = $accountingEntries->whereIn('status', ['submitted', 'approved', 'recheck_required'])->count();
        $accountingApprovedCount = $accountingEntries->where('status', 'approved')->count();
        $accountingRecheckCount = $accountingEntries->where('status', 'recheck_required')->count();
        $accountingMissingCount = max(0, $activeShopCount - $accountingEntries->pluck('shop_id')->unique()->count());
        $closingCashTotal = (float) $accountingEntries->sum('closing_cash');

        $attendancePresentCount = $attendances->where('status', 'present')->count();
        $attendanceHalfDayCount = $attendances->where('status', 'half_day')->count();
        $attendanceAbsentCount = $attendances->where('status', 'absent')->count();
        $attendanceLeaveCount = $attendances->whereIn('status', ['leave', 'paid_leave', 'unpaid_leave'])->count();

        $orderCompletionPercent = $totalOrdersCount === 0 ? 0 : (int) round($deliveredOrdersCount / $totalOrdersCount * 100);
        $dispatchProgressPercent = $totalOrdersCount === 0 ? 0 : (int) round(($outForDeliveryOrdersCount + $deliveredOrdersCount) / $totalOrdersCount * 100);
        $collectionProgressPercent = $invoiceTotal <= 0 ? 0 : min(100, (int) round($invoicePaidTotal / $invoiceTotal * 100));
        $accountingProgressPercent = $activeShopCount === 0 ? 0 : min(100, (int) round($accountingSubmittedCount / $activeShopCount * 100));

        $atRiskOrders = $orders
            ->filter(fn (ShopOrder $order): bool => $this->orderHasDiscrepancy($order) || $order->hasPendingDeliveryReview() || $order->has_pending_revision || $order->is_late)
            ->sortByDesc(fn (ShopOrder $order): int => (int) $order->hasPendingDeliveryReview() + (int) $this->orderHasDiscrepancy($order) + (int) $order->is_late)
            ->take(8)
            ->values();

        $recentOrders = $orders
            ->sortByDesc('updated_at')
            ->take(8)
            ->values();

        $topShortageOrders = $orders
            ->filter(fn (ShopOrder $order): bool => (float) $order->total_shortage_value > 0.01)
            ->sortByDesc('total_shortage_value')
            ->take(6)
            ->values();

        $shopProgressRows = $shops->map(function (Shop $shop) use ($orders, $invoices, $accountingEntries, $attendances): array {
            $shopOrders = $orders
                ->where('shop_id', $shop->id)
                ->values();
            $shopInvoices = $invoices
                ->where('shop_id', $shop->id)
                ->values();
            $shopAccountingEntry = $accountingEntries
                ->where('shop_id', $shop->id)
                ->sortByDesc('updated_at')
                ->first();
            $shopAttendances = $attendances
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
            $invoiceTotal = round((float) $shopInvoices->sum('final_total'), 2);
            $paidTotal = round((float) $shopInvoices->sum('paid_amount'), 2);
            $balanceTotal = round((float) $shopInvoices->sum('balance_amount'), 2);

            return [
                'shop' => $shop,
                'orders' => $shopOrders,
                'invoices' => $shopInvoices,
                'accounting_entry' => $shopAccountingEntry,
                'attendance_count' => $shopAttendances->count(),
                'attendance_present' => $shopAttendances->where('status', 'present')->count(),
                'total_orders' => $totalOrders,
                'approved_orders' => $approvedOrders,
                'out_for_delivery_orders' => $outForDeliveryOrders,
                'delivered_orders' => $deliveredOrders,
                'discrepancy_orders' => $discrepancyOrders,
                'pending_review_orders' => $pendingReviewOrders,
                'cash_discrepancy_total' => round((float) $shopOrders->sum('cash_discrepancy'), 2),
                'shortage_total' => round((float) $shopOrders->sum('total_shortage_value'), 2),
                'invoice_total' => $invoiceTotal,
                'paid_total' => $paidTotal,
                'balance_total' => $balanceTotal,
                'invoice_count' => $shopInvoices->count(),
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
                'label' => 'Purchase Orders',
                'count' => $totalPoCount,
                'meta' => $totalPoCount > 0 ? "of {$totalPoCount} purchase orders" : 'No purchase orders',
                'detail' => "{$approvedPoCount} approved, {$openPoCount} still open",
                'tone' => $openPoCount > 0 ? 'amber' : ($totalPoCount > 0 ? 'emerald' : 'slate'),
            ],
            'goods_received' => [
                'label' => 'Goods Received',
                'count' => $grnCount,
                'meta' => "{$receivedPoCount} PO receipts closed",
                'detail' => number_format($receivedKg, 2).' kg recorded on GRNs',
                'tone' => $grnPendingApprovalCount > 0 ? 'amber' : ($grnCount > 0 ? 'sky' : 'slate'),
            ],
            'stock_available' => [
                'label' => 'Stock Batches',
                'count' => $totalStockBatches,
                'meta' => number_format($totalStockKg, 2).' kg available',
                'detail' => "{$sortedBatchesCount} sorted, {$pendingBatchesCount} awaiting sort",
                'tone' => $pendingBatchesCount > 0 ? 'amber' : ($totalStockKg > 0 ? 'indigo' : 'slate'),
            ],
            'shop_orders' => [
                'label' => 'Orders Raised',
                'count' => $totalOrdersCount,
                'meta' => "{$shopsWithOrdersCount} shops active",
                'detail' => "{$shopsWithoutOrdersCount} active shops without an order",
                'tone' => $totalOrdersCount > 0 ? 'amber' : 'slate',
            ],
            'approved_orders' => [
                'label' => 'Warehouse Prep',
                'count' => $approvedOrdersCount,
                'meta' => "{$allocatedItemsCount} / {$totalItemsCount} items allocated",
                'detail' => "{$packingOrdersCount} orders still before dispatch",
                'tone' => $packingOrdersCount > 0 ? 'amber' : ($approvedOrdersCount > 0 ? 'emerald' : 'slate'),
            ],
            'out_for_delivery' => [
                'label' => 'Out for Delivery',
                'count' => $outForDeliveryOrdersCount,
                'meta' => "{$loadedItemsCount} / {$totalItemsCount} items loaded",
                'detail' => "{$dispatchProgressPercent}% dispatch progress",
                'tone' => $outForDeliveryOrdersCount > 0 ? 'sky' : 'slate',
            ],
            'delivered' => [
                'label' => 'Delivered',
                'count' => $deliveredOrdersCount,
                'meta' => 'Cash collected Rs. '.number_format($totalCashCollected, 2),
                'detail' => "{$orderCompletionPercent}% delivery completion",
                'tone' => $deliveredOrdersCount > 0 ? 'teal' : 'slate',
            ],
            'discrepancies' => [
                'label' => 'Exceptions',
                'count' => $discrepancyOrdersCount,
                'meta' => 'Variance Rs. '.number_format($totalCashDiscrepancies, 2),
                'detail' => "{$pendingDeliveryReviewCount} pending delivery reviews",
                'tone' => $discrepancyOrdersCount > 0 ? 'rose' : 'emerald',
            ],
        ];

        $decisionCards = [
            [
                'label' => 'Operational Health',
                'value' => $this->healthLabel($totalOrdersCount, $deliveredOrdersCount, $discrepancyOrdersCount, $pendingDeliveryReviewCount),
                'meta' => "{$orderCompletionPercent}% delivered, {$dispatchProgressPercent}% dispatched",
                'tone' => $discrepancyOrdersCount > 0 || $pendingDeliveryReviewCount > 0 ? 'rose' : ($totalOrdersCount === 0 ? 'slate' : 'emerald'),
            ],
            [
                'label' => 'Collection Health',
                'value' => $collectionProgressPercent.'%',
                'meta' => 'Rs. '.number_format($invoicePaidTotal, 2).' paid of Rs. '.number_format($invoiceTotal, 2),
                'tone' => $invoiceBalanceTotal > 0 ? 'amber' : ($invoiceTotal > 0 ? 'emerald' : 'slate'),
            ],
            [
                'label' => 'Accounting Submissions',
                'value' => $accountingProgressPercent.'%',
                'meta' => "{$accountingSubmittedCount} submitted, {$accountingMissingCount} missing",
                'tone' => $accountingMissingCount > 0 || $accountingRecheckCount > 0 ? 'amber' : ($accountingSubmittedCount > 0 ? 'emerald' : 'slate'),
            ],
            [
                'label' => 'Staff Attendance',
                'value' => $attendancePresentCount,
                'meta' => "{$attendanceAbsentCount} absent, {$attendanceLeaveCount} on leave, {$attendanceHalfDayCount} half day",
                'tone' => $attendanceAbsentCount > 0 ? 'amber' : ($attendances->isNotEmpty() ? 'emerald' : 'slate'),
            ],
        ];

        $adminFocusItems = $this->adminFocusItems(
            totalOrdersCount: $totalOrdersCount,
            shopsWithoutOrdersCount: $shopsWithoutOrdersCount,
            pendingBatchesCount: $pendingBatchesCount,
            pendingDeliveryReviewCount: $pendingDeliveryReviewCount,
            discrepancyOrdersCount: $discrepancyOrdersCount,
            invoiceBalanceTotal: $invoiceBalanceTotal,
            accountingMissingCount: $accountingMissingCount,
            accountingRecheckCount: $accountingRecheckCount,
            grnPendingApprovalCount: $grnPendingApprovalCount,
        );

        return view('admin.daily_progress.index', compact(
            'date',
            'previousDate',
            'flowStages',
            'decisionCards',
            'adminFocusItems',
            'shopProgressRows',
            'atRiskOrders',
            'recentOrders',
            'topShortageOrders',
            'activeShopCount',
            'shopsWithOrdersCount',
            'shopsWithoutOrdersCount',
            'totalOrdersCount',
            'submittedOrdersCount',
            'approvedOrdersCount',
            'outForDeliveryOrdersCount',
            'packingOrdersCount',
            'deliveredOrdersCount',
            'discrepancyOrdersCount',
            'pendingDeliveryReviewCount',
            'pendingRevisionOrdersCount',
            'lateOrdersCount',
            'totalShortagesValue',
            'totalCashCollected',
            'totalCashDiscrepancies',
            'pendingBatchesCount',
            'totalStockKg',
            'sortedBatchesCount',
            'closedBatchesCount',
            'totalPoCount',
            'previousPoCount',
            'previousOrdersCount',
            'openPoCount',
            'grnCount',
            'grnApprovedCount',
            'grnPendingApprovalCount',
            'receivedKg',
            'generatedInvoicesCount',
            'paidInvoicesCount',
            'unpaidInvoicesCount',
            'partialInvoicesCount',
            'invoiceTotal',
            'invoicePaidTotal',
            'invoiceBalanceTotal',
            'collectionProgressPercent',
            'orderCompletionPercent',
            'dispatchProgressPercent',
            'accountingSubmittedCount',
            'accountingApprovedCount',
            'accountingRecheckCount',
            'accountingMissingCount',
            'closingCashTotal',
            'accountingProgressPercent',
            'attendancePresentCount',
            'attendanceHalfDayCount',
            'attendanceAbsentCount',
            'attendanceLeaveCount',
        ));
    }

    private function statusValue(mixed $status): string
    {
        return $status instanceof \BackedEnum ? (string) $status->value : (string) $status;
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

    private function healthLabel(int $totalOrdersCount, int $deliveredOrdersCount, int $discrepancyOrdersCount, int $pendingDeliveryReviewCount): string
    {
        if ($totalOrdersCount === 0) {
            return 'No Orders';
        }

        if ($discrepancyOrdersCount > 0 || $pendingDeliveryReviewCount > 0) {
            return 'Needs Attention';
        }

        if ($deliveredOrdersCount === $totalOrdersCount) {
            return 'Completed';
        }

        return 'In Progress';
    }

    /**
     * @return Collection<int, array{label: string, detail: string, tone: string}>
     */
    private function adminFocusItems(
        int $totalOrdersCount,
        int $shopsWithoutOrdersCount,
        int $pendingBatchesCount,
        int $pendingDeliveryReviewCount,
        int $discrepancyOrdersCount,
        float $invoiceBalanceTotal,
        int $accountingMissingCount,
        int $accountingRecheckCount,
        int $grnPendingApprovalCount,
    ): Collection {
        $items = collect();

        if ($totalOrdersCount === 0) {
            $items->push([
                'label' => 'No shop orders found',
                'detail' => 'No requisitions exist for this date. Confirm whether shops have submitted their daily demand.',
                'tone' => 'slate',
            ]);
        }

        if ($shopsWithoutOrdersCount > 0) {
            $items->push([
                'label' => 'Shops missing orders',
                'detail' => "{$shopsWithoutOrdersCount} active shop(s) have no requisition for this date.",
                'tone' => 'amber',
            ]);
        }

        if ($pendingBatchesCount > 0) {
            $items->push([
                'label' => 'Stock sorting pending',
                'detail' => "{$pendingBatchesCount} received batch(es) still need warehouse sorting.",
                'tone' => 'amber',
            ]);
        }

        if ($grnPendingApprovalCount > 0) {
            $items->push([
                'label' => 'GRN approval pending',
                'detail' => "{$grnPendingApprovalCount} goods receipt(s) need approval or recheck.",
                'tone' => 'amber',
            ]);
        }

        if ($pendingDeliveryReviewCount > 0) {
            $items->push([
                'label' => 'Delivery review pending',
                'detail' => "{$pendingDeliveryReviewCount} delivery report(s) are waiting for admin review.",
                'tone' => 'rose',
            ]);
        }

        if ($discrepancyOrdersCount > 0) {
            $items->push([
                'label' => 'Delivery discrepancies',
                'detail' => "{$discrepancyOrdersCount} order(s) have shortage, damage, or cash variance flags.",
                'tone' => 'rose',
            ]);
        }

        if ($invoiceBalanceTotal > 0.01) {
            $items->push([
                'label' => 'Outstanding shop invoice balance',
                'detail' => 'Rs. '.number_format($invoiceBalanceTotal, 2).' is still unpaid or partially paid.',
                'tone' => 'amber',
            ]);
        }

        if ($accountingMissingCount > 0) {
            $items->push([
                'label' => 'Accounting not submitted',
                'detail' => "{$accountingMissingCount} active shop(s) have no accounting entry for the date.",
                'tone' => 'amber',
            ]);
        }

        if ($accountingRecheckCount > 0) {
            $items->push([
                'label' => 'Accounting recheck required',
                'detail' => "{$accountingRecheckCount} shop accounting submission(s) were sent back.",
                'tone' => 'rose',
            ]);
        }

        if ($items->isEmpty()) {
            $items->push([
                'label' => 'No urgent admin blockers',
                'detail' => 'Orders, delivery, collection, and accounting have no flagged issues for this date.',
                'tone' => 'emerald',
            ]);
        }

        return $items;
    }
}
