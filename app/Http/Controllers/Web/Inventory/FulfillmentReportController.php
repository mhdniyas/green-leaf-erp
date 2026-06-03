<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class FulfillmentReportController extends Controller
{
    /**
     * Display the Fulfillment & Delivery Analytics Report.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        // 1. Resolve Shop Access Scope
        $shops = collect();
        $selectedShopId = null;

        if ($user->hasRole('shop')) {
            $selectedShopId = $user->shop_id;
        } else {
            // Admins/Managers can filter by any shop
            $shops = Shop::where('status', 'active')->orderBy('name')->get();
            $selectedShopId = $request->input('shop_id') ? (int) $request->input('shop_id') : null;
        }

        // 2. Resolve Date Range (default: last 30 days)
        $startDateInput = $request->input('start_date');
        $endDateInput = $request->input('end_date');

        $startDate = $startDateInput ? Carbon::parse($startDateInput) : Carbon::today()->subDays(30);
        $endDate = $endDateInput ? Carbon::parse($endDateInput) : Carbon::today();

        // 3. Query Shop Orders
        $ordersQuery = ShopOrder::whereBetween('business_date', [
            $startDate->copy()->startOfDay(),
            $endDate->copy()->endOfDay(),
        ]);

        if ($selectedShopId) {
            $ordersQuery->where('shop_id', $selectedShopId);
        }

        $orders = $ordersQuery->with(['items.product.category', 'shop', 'deliveredBy'])
            ->orderBy('business_date', 'desc')
            ->get();

        // 4. Calculate Aggregate Metrics
        $totalOrders = $orders->count();
        $deliveredOrders = $orders->where('is_delivered', true);
        $deliveredOrdersCount = $deliveredOrders->count();

        $totalShortageValue = (float) $orders->sum('total_shortage_value');
        $totalCashCollected = (float) $orders->sum('cash_collected');
        $totalCashDiscrepancy = (float) $orders->sum('cash_discrepancy');

        $totalRequestedQty = 0.00;
        $totalApprovedQty = 0.00;
        $totalDeliveredQty = 0.00;
        $totalShortageQty = 0.00;

        $productStats = [];
        $categoryStats = [];

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $req = (float) $item->requested_qty;
                $app = (float) ($item->approved_qty ?? 0.00);
                $del = (float) ($item->delivered_qty ?? 0.00);
                $sho = (float) ($item->shortage_qty ?? 0.00);

                $totalRequestedQty += $req;
                $totalApprovedQty += $app;
                $totalDeliveredQty += $del;
                $totalShortageQty += $sho;

                // Product details aggregation
                $pid = $item->product_id;
                if (! isset($productStats[$pid])) {
                    $productStats[$pid] = [
                        'product' => $item->product,
                        'requested' => 0.00,
                        'approved' => 0.00,
                        'delivered' => 0.00,
                        'shortage' => 0.00,
                        'shortage_value' => 0.00,
                    ];
                }
                $productStats[$pid]['requested'] += $req;
                $productStats[$pid]['approved'] += $app;
                $productStats[$pid]['delivered'] += $del;
                $productStats[$pid]['shortage'] += $sho;
                $productStats[$pid]['shortage_value'] += (float) ($item->shortage_value ?? 0.00);

                // Category details aggregation
                if ($item->product && $item->product->category) {
                    $catId = $item->product->category_id;
                    $catName = $item->product->category->name;
                    if (! isset($categoryStats[$catId])) {
                        $categoryStats[$catId] = [
                            'name' => $catName,
                            'requested' => 0.00,
                            'approved' => 0.00,
                            'delivered' => 0.00,
                            'shortage' => 0.00,
                        ];
                    }
                    $categoryStats[$catId]['requested'] += $req;
                    $categoryStats[$catId]['approved'] += $app;
                    $categoryStats[$catId]['delivered'] += $del;
                    $categoryStats[$catId]['shortage'] += $sho;
                }
            }
        }

        // Calculate rates
        $approvalFulfillmentRate = $totalApprovedQty > 0 ? ($totalDeliveredQty / $totalApprovedQty) * 100 : 0.00;
        $requestFulfillmentRate = $totalRequestedQty > 0 ? ($totalDeliveredQty / $totalRequestedQty) * 100 : 0.00;

        // Sort product stats by fulfillment percentage or shortages (lowest first)
        usort($productStats, function ($a, $b) {
            $aRate = $a['approved'] > 0 ? ($a['delivered'] / $a['approved']) : 1.0;
            $bRate = $b['approved'] > 0 ? ($b['delivered'] / $b['approved']) : 1.0;

            return $aRate <=> $bRate;
        });

        return view('requisitions.reports.fulfillment', compact(
            'startDate',
            'endDate',
            'shops',
            'selectedShopId',
            'orders',
            'totalOrders',
            'deliveredOrdersCount',
            'totalShortageValue',
            'totalCashCollected',
            'totalCashDiscrepancy',
            'totalRequestedQty',
            'totalApprovedQty',
            'totalDeliveredQty',
            'totalShortageQty',
            'approvalFulfillmentRate',
            'requestFulfillmentRate',
            'productStats',
            'categoryStats'
        ));
    }
}
