<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceivedItem;
use App\Models\ShopOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DiscrepancyReportController extends Controller
{
    /**
     * Display the discrepancy and wastage reports.
     */
    public function __invoke(Request $request): View
    {
        if (! $request->user()->can('admin.discrepancies.view') &&
            ! $request->user()->hasRole('admin')) {
            abort(403, 'Unauthorized access to discrepancy and wastage reports.');
        }

        $dateInput = $request->input('date');
        $date = $dateInput ? Carbon::parse($dateInput)->format('Y-m-d') : Carbon::today()->format('Y-m-d');

        // Stock-In Discrepancies (Purchaser Product vs Warehouse Receiver)
        $goodsReceivedItems = GoodsReceivedItem::with([
            'goodsReceived.purchaseOrder.supplier',
            'goodsReceived.purchaseOrder.purchaserCart.user',
            'product',
        ])
            ->whereHas('goodsReceived', function ($query) use ($date): void {
                $query->whereDate('received_at', $date);
            })
            ->where(function ($query): void {
                $query->whereColumn('purchased_qty', '!=', 'received_qty')
                    ->orWhere('discrepancy_type', '!=', 'none');
            })
            ->get();

        // Stock-Out / Delivery Discrepancies (Approved vs Loaded vs Delivered)
        $shopOrderItems = ShopOrderItem::with([
            'order.shop',
            'product',
        ])
            ->whereHas('order', function ($query) use ($date): void {
                $query->whereDate('business_date', $date);
            })
            ->where(function ($query): void {
                $query->where(function ($q): void {
                    $q->whereColumn('approved_qty', '!=', 'loaded_qty')
                        ->orWhere('loadout_discrepancy_type', '!=', 'none');
                })
                    ->orWhere(function ($q): void {
                        $q->whereColumn('loaded_qty', '!=', 'delivered_qty')
                            ->orWhere('delivery_discrepancy_type', '!=', 'none');
                    });
            })
            ->get();

        return view('admin.discrepancies.index', compact(
            'date',
            'goodsReceivedItems',
            'shopOrderItems'
        ));
    }
}
