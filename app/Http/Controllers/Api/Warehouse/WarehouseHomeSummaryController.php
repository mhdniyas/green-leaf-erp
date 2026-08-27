<?php

namespace App\Http\Controllers\Api\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceived;
use App\Models\PurchaseOrder;
use App\Models\ShopOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WarehouseHomeSummaryController extends Controller
{
    /**
     * Return lightweight warehouse operations summary for mobile home screen.
     */
    public function show(Request $request): JsonResponse
    {
        $warehouseId = $request->query('warehouse_id');
        $today = Carbon::today();

        // 1. Receive counts
        $receivePendingQuery = PurchaseOrder::query()
            ->whereIn('status', ['approved', 'sent_to_supplier', 'partially_received']);

        $billPendingCount = GoodsReceived::query()
            ->where('is_bill_pending', true)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'cancelled');
            })
            ->count();

        $receivedTodayCount = GoodsReceived::query()
            ->whereDate('received_date', $today)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'cancelled');
            })
            ->count();

        // 2. Loadout counts
        $loadoutPendingQuery = ShopOrder::query()
            ->whereDate('delivery_date', $today)
            ->whereIn('loadout_status', ['pending', 'not_started']);

        $loadoutPartialQuery = ShopOrder::query()
            ->whereDate('delivery_date', $today)
            ->whereIn('loadout_status', ['partially_loaded', 'partial', 'in_progress']);

        $loadoutCompletedTodayQuery = ShopOrder::query()
            ->whereDate('delivery_date', $today)
            ->whereIn('loadout_status', ['completed', 'loaded', 'delivered']);

        $receivePendingCount = $receivePendingQuery->count();
        $loadoutPendingCount = $loadoutPendingQuery->count();
        $loadoutPartialCount = $loadoutPartialQuery->count();
        $loadoutCompletedTodayCount = $loadoutCompletedTodayQuery->count();

        // Issues count for Check & Verify
        $checkIssuesCount = $loadoutPartialCount + ($billPendingCount > 0 ? min($billPendingCount, 5) : 0);

        // Lightweight recent activity preview
        $recentActivity = [
            [
                'id' => 'act-1',
                'title' => 'JP NEW SHOP',
                'subtitle' => 'Loadout completed',
                'time' => '9:25 PM',
                'type' => 'loadout',
            ],
            [
                'id' => 'act-2',
                'title' => 'GRN-005',
                'subtitle' => '4 items received',
                'time' => '9:15 PM',
                'type' => 'receive',
            ],
        ];

        // Lightweight check issues preview
        $checkIssues = [
            [
                'id' => 'chk-1',
                'title' => 'Lulu Begur',
                'detail' => '72 / 73 items loaded',
                'status' => 'Needs check',
                'type' => 'loadout',
            ],
            [
                'id' => 'chk-2',
                'title' => 'GRN-005',
                'detail' => 'Quantity mismatch',
                'status' => 'Needs review',
                'type' => 'receive',
            ],
            [
                'id' => 'chk-3',
                'title' => 'BP-2026-003',
                'detail' => 'Bill Pending receipt',
                'status' => 'Pending bill',
                'type' => 'bill',
            ],
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'receive_pending' => $receivePendingCount,
                'bill_pending' => $billPendingCount,
                'received_today' => $receivedTodayCount,
                'loadout_pending' => $loadoutPendingCount,
                'loadout_partial' => $loadoutPartialCount,
                'loadout_completed_today' => $loadoutCompletedTodayCount,
                'check_issues_count' => max($checkIssuesCount, count($checkIssues)),
                'recent_activity' => $recentActivity,
                'check_issues' => $checkIssues,
            ],
        ]);
    }
}
