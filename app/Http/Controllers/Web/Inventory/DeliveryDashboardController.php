<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DeliveryDashboardController extends Controller
{
    /**
     * Display the daily delivery dashboard.
     */
    public function __invoke(Request $request): View
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

        // Calculate summary metrics
        $totalOrdersCount = $orders->count();
        $allocationCompletedCount = $orders->where('is_allocation_completed', true)->count();
        $awaitingAllocationCount = $totalOrdersCount - $allocationCompletedCount;
        $deliveredCount = $orders->where('is_delivered', true)->count();
        $awaitingDeliveryCount = $totalOrdersCount - $deliveredCount;

        $totalShortageValue = (float) $orders->sum('total_shortage_value');
        $totalCashCollected = (float) $orders->sum('cash_collected');
        $totalCashDiscrepancy = (float) $orders->sum('cash_discrepancy');

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

        return view('deliveries.dashboard', compact(
            'date',
            'orders',
            'totalOrdersCount',
            'allocationCompletedCount',
            'awaitingAllocationCount',
            'deliveredCount',
            'awaitingDeliveryCount',
            'totalShortageValue',
            'totalCashCollected',
            'totalCashDiscrepancy',
            'shortageItems',
            'discrepancyOrders'
        ));
    }
}
