<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Actions\Purchasing\ApproveGoodsReceiptAction;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceived;
use App\Models\ShopOrderItem;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseManagerGrnApprovalController extends Controller
{
    public function __construct(
        private readonly ApproveGoodsReceiptAction $approveAction,
    ) {}

    /**
     * Show the daily GRN approval screen for the Purchase Manager.
     *
     * Groups all pending_approval GRNs for the date by product and splits them into:
     *  - daily_items  → products that were in the daily purchase order
     *  - extra_items  → ad-hoc products not in the daily PO
     */
    public function index(Request $request): View
    {
        $this->authorizeManagerAccess($request);

        $date = $request->input('date', app(PurchaserBusinessDayService::class)->operationalDate()->toDateString());

        // Load all pending_approval GRNs for the date with their items
        $pendingGrns = GoodsReceived::where('status', 'pending_approval')
            ->whereDate('received_at', $date)
            ->with([
                'purchaseOrder.supplier',
                'purchaseOrder.items.product',
                'items.product',
                'items.purchaseOrderItem',
                'receivedBy',
            ])
            ->get();

        // Load shop order demands for the date to show splits and identify daily vs extra
        $shopOrderItems = ShopOrderItem::whereHas('order', function ($query) use ($date): void {
            $query->whereDate('business_date', $date)->where('state', 'approved');
        })->with(['product', 'order.shop'])->get();

        // Build product-level shop splits map
        $shopSplitsByProduct = $shopOrderItems
            ->groupBy('product_id')
            ->map(fn ($items) => $items->map(fn ($item) => [
                'shop_name' => $item->order->shop->name,
                'quantity' => (float) $item->approved_qty,
                'unit' => $item->unit,
            ])->values()->all());

        // Aggregate GRN items by product and is_extra
        $productGroups = [];
        foreach ($pendingGrns as $grn) {
            $isExtra = (bool) $grn->is_extra;
            foreach ($grn->items as $item) {
                $pid = $item->product_id;
                $key = "{$pid}-".($isExtra ? '1' : '0');

                if (! isset($productGroups[$key])) {
                    $productGroups[$key] = [
                        'product_id' => $pid,
                        'product_name' => $item->product->name,
                        'sku' => $item->product->sku,
                        'unit' => $item->product->unit,
                        'total_qty' => 0.0,
                        'weighted_price_sum' => 0.0,
                        'entries' => [],
                        'shop_splits' => $isExtra ? [] : ($shopSplitsByProduct[$pid] ?? []),
                        'daily_needed' => $isExtra ? 0.0 : (float) ($shopOrderItems->where('product_id', $pid)->sum('approved_qty')),
                        'is_extra' => $isExtra,
                    ];
                }

                $qty = (float) $item->received_qty;
                $price = $item->purchaseOrderItem ? (float) $item->purchaseOrderItem->unit_price : 0.0;

                $productGroups[$key]['total_qty'] += $qty;
                $productGroups[$key]['weighted_price_sum'] += $qty * $price;
                $productGroups[$key]['entries'][] = [
                    'supplier' => $grn->purchaseOrder->supplier->name ?? 'Unknown',
                    'purchaser' => $grn->receivedBy->name ?? 'Unknown',
                    'qty' => $qty,
                    'unit_price' => $price,
                    'grn_number' => $grn->grn_number,
                ];
            }
        }

        $dailyItems = [];
        $extraItems = [];

        foreach ($productGroups as $key => $pg) {
            $avgPrice = $pg['total_qty'] > 0
                ? round($pg['weighted_price_sum'] / $pg['total_qty'], 2)
                : 0.0;

            $pg['avg_price'] = $avgPrice;

            if ($pg['is_extra']) {
                $extraItems[] = $pg;
            } else {
                $dailyItems[] = $pg;
            }
        }

        $totalPending = $pendingGrns->count();

        return view('purchase-manager.grns.daily-approval', compact(
            'date',
            'pendingGrns',
            'dailyItems',
            'extraItems',
            'totalPending',
        ));
    }

    /**
     * Approve all pending GRNs for the given date.
     */
    public function approve(Request $request): RedirectResponse
    {
        $this->authorizeManagerAccess($request);

        $date = $request->input('date', app(PurchaserBusinessDayService::class)->operationalDate()->toDateString());
        $userId = (int) $request->user()->id;

        $pendingGrns = GoodsReceived::where('status', 'pending_approval')
            ->whereDate('received_at', $date)
            ->with(['items.product', 'items.purchaseOrderItem', 'purchaseOrder'])
            ->get();

        if ($pendingGrns->isEmpty()) {
            return redirect()->back()->withErrors(['No pending GRNs found for this date.']);
        }

        foreach ($pendingGrns as $grn) {
            $this->approveAction->execute($grn, $userId);
        }

        return redirect()
            ->route('purchasing.grns.daily-approval', ['date' => $date])
            ->with('success', "All {$pendingGrns->count()} GRN(s) approved. Stock batches created with warehouse receive pending.");
    }

    private function authorizeManagerAccess(Request $request): void
    {
        if (
            ! $request->user()->hasRole('purchase')
            && ! $request->user()->hasRole('admin')
            && ! $request->user()->can('purchasing.grn.approve')
        ) {
            abort(403, 'Unauthorized access.');
        }
    }
}
