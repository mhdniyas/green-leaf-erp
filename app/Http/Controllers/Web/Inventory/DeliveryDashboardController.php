<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceived;
use App\Models\PurchaseOrder;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class DeliveryDashboardController extends Controller
{
    /**
     * Display the daily delivery dashboard.
     */
    public function __invoke(Request $request): Response
    {
        $dateInput = $request->input('date');
        $date = $dateInput ? Carbon::parse($dateInput)->format('Y-m-d') : Carbon::today()->format('Y-m-d');

        $user = $request->user();
        $isShop = $user->hasRole('shop');
        $shopId = $user->shop_id;

        // Fetch all shop orders for the selected business date
        $ordersQuery = ShopOrder::whereDate('business_date', $date)
            ->with(['shop', 'items.product', 'deliveredBy']);

        if ($isShop && $shopId) {
            $ordersQuery->where('shop_id', $shopId);
        }

        $orders = $ordersQuery->get();

        $warehouseApprovedCount = $orders
            ->filter(fn (ShopOrder $order): bool => $order->warehouseWorkflowStage() === 'approved')
            ->count();
        $packingCount = $orders
            ->filter(fn (ShopOrder $order): bool => $order->warehouseWorkflowStage() === 'packing')
            ->count();
        $inTransitCount = $orders
            ->filter(fn (ShopOrder $order): bool => $order->warehouseWorkflowStage() === 'in_transit')
            ->count();

        // Calculate summary metrics
        $totalOrdersCount = $orders->count();
        $allocationCompletedCount = $orders->where('is_allocation_completed', true)->count();
        $awaitingAllocationCount = $totalOrdersCount - $allocationCompletedCount;
        $deliveredCount = $orders->where('is_delivered', true)->count();
        $awaitingDeliveryCount = $totalOrdersCount - $deliveredCount;

        $totalShortageValue = (float) $orders->sum('total_shortage_value');
        $totalCashCollected = (float) $orders->sum('cash_collected');
        $totalCashDiscrepancy = (float) $orders->sum('cash_discrepancy');

        $receiveQueueCount = PurchaseOrder::query()
            ->whereIn('status', [POStatus::SentToSupplier, POStatus::PartiallyReceived])
            ->count();

        $pendingGrnApprovalCount = GoodsReceived::query()
            ->whereDate('received_at', $date)
            ->where('status', 'pending_approval')
            ->count();

        $shopCards = $orders
            ->map(function (ShopOrder $order): array {
                $items = $order->items;
                $totalItems = $items->count();
                $packedItems = $items->whereIn('sorting_status', ['allocated', 'loaded'])->count();
                $inTransitItems = $items->where('sorting_status', 'loaded')->count();
                $deliveredItems = $items->filter(fn (ShopOrderItem $item): bool => $item->warehouseWorkflowStage() === 'delivered')->count();

                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'shop_name' => $order->shop?->name ?? 'Unknown Shop',
                    'status_label' => $order->warehouseWorkflowLabel(),
                    'status_tone' => $order->warehouseWorkflowTone(),
                    'total_items' => $totalItems,
                    'packed_items' => $packedItems,
                    'in_transit_items' => $inTransitItems,
                    'delivered_items' => $deliveredItems,
                    'progress_percentage' => $totalItems > 0 ? (int) round(($packedItems / $totalItems) * 100) : 0,
                    'details_url' => route('inventory.sorting.shop-orders', ['date' => $order->business_date->format('Y-m-d')]).'#shop-card-'.$order->id,
                    'check_in_url' => $order->is_allocation_completed && ! $order->is_delivered
                        ? route('requisitions.delivery.show', $order->order_number)
                        : null,
                ];
            })
            ->sortBy([
                ['progress_percentage', 'asc'],
                ['shop_name', 'asc'],
            ])
            ->values();

        // Itemized shortages: ShopOrderItems with shortage_qty > 0 on this date
        $shortageQuery = ShopOrderItem::whereHas('order', function ($query) use ($date, $isShop, $shopId): void {
            $query->whereDate('business_date', $date);
            if ($isShop && $shopId) {
                $query->where('shop_id', $shopId);
            }
        })
            ->where('shortage_qty', '>', 0.00)
            ->with(['order.shop', 'product']);

        $shortageItems = $shortageQuery->get();

        // Cash discrepancies: Delivered shop orders with non-zero cash discrepancy
        $discrepancyOrders = $orders->filter(function (ShopOrder $order): bool {
            return $order->is_delivered && abs((float) $order->cash_discrepancy) > 0.01;
        });

        $latestOrderUpdateAt = $orders->max('updated_at');
        $latestItemUpdateAt = $orders
            ->flatMap(fn (ShopOrder $order) => $order->items)
            ->max('updated_at');

        $lastUpdatedAt = collect([$latestOrderUpdateAt, $latestItemUpdateAt])
            ->filter()
            ->sortDesc()
            ->first();

        return response()
            ->view('deliveries.dashboard', compact(
                'date',
                'orders',
                'totalOrdersCount',
                'allocationCompletedCount',
                'awaitingAllocationCount',
                'deliveredCount',
                'awaitingDeliveryCount',
                'warehouseApprovedCount',
                'packingCount',
                'inTransitCount',
                'receiveQueueCount',
                'pendingGrnApprovalCount',
                'shopCards',
                'totalShortageValue',
                'totalCashCollected',
                'totalCashDiscrepancy',
                'shortageItems',
                'discrepancyOrders',
                'lastUpdatedAt'
            ))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');
    }
}
