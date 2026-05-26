<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequisitionController extends Controller
{
    /**
     * Store a newly created shop requisition.
     *
     * @return JsonResponse|RedirectResponse
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user->shop_id) {
            return response()->json(['error' => 'User is not associated with any shop.'], 400);
        }

        $items = $request->input('items', []);
        if (empty($items)) {
            return response()->json(['error' => 'Requisition cannot be empty.'], 400);
        }

        $businessDate = Carbon::tomorrow()->format('Y-m-d');

        // Enforcement: check if cutoff has passed for tomorrow's date
        // Cutoff is today 9:30 PM.
        $cutoff = Carbon::today()->setTime(21, 30, 0);
        if (now()->greaterThan($cutoff)) {
            return response()->json(['error' => 'Requisition submission window has closed (9:30 PM cutoff).'], 400);
        }

        $order = DB::transaction(function () use ($user, $items, $businessDate) {
            // Delete any existing draft/submitted order for tomorrow
            ShopOrder::where('shop_id', $user->shop_id)
                ->where('business_date', $businessDate)
                ->delete();

            $shopOrder = ShopOrder::create([
                'shop_id' => $user->shop_id,
                'business_date' => $businessDate,
                'state' => 'submitted',
                'submitted_at' => now(),
                'deadline_at' => Carbon::today()->setTime(21, 30, 0),
                'created_by' => $user->id,
            ]);

            foreach ($items as $sku => $qty) {
                $qtyVal = (float) $qty;
                if ($qtyVal <= 0) {
                    continue;
                }

                $product = Product::where('sku', $sku)->first();
                if ($product) {
                    ShopOrderItem::create([
                        'shop_order_id' => $shopOrder->id,
                        'product_id' => $product->id,
                        'requested_qty' => $qtyVal,
                        'unit' => $product->unit,
                    ]);
                }
            }

            return $shopOrder;
        });

        return response()->json([
            'success' => true,
            'order_number' => $order->order_number,
            'redirect_url' => route('requisitions.show', $order->order_number),
        ]);
    }

    /**
     * Display the specified requisition details.
     */
    public function show(Request $request, string $orderNumber): View
    {
        $order = ShopOrder::where('order_number', $orderNumber)
            ->with(['items.product', 'shop', 'creator'])
            ->firstOrFail();

        // Access control: Shop Owner can only see their own shop orders
        if ($request->user()->hasRole('shop-owner') && $order->shop_id !== $request->user()->shop_id) {
            abort(403, 'Unauthorized access to shop order.');
        }

        return view('requisitions.show', compact('order'));
    }

    /**
     * Show the edit form for the requisition.
     *
     * @return View|RedirectResponse
     */
    public function edit(Request $request, string $orderNumber)
    {
        $order = ShopOrder::where('order_number', $orderNumber)
            ->with(['items.product'])
            ->firstOrFail();

        if ($request->user()->hasRole('shop-owner') && $order->shop_id !== $request->user()->shop_id) {
            abort(403, 'Unauthorized access.');
        }

        if (! $order->canEditDirectly()) {
            return redirect()->route('requisitions.show', $orderNumber)
                ->with('error', 'Requisition window has closed. You cannot edit this order directly.');
        }

        return view('requisitions.edit', compact('order'));
    }

    /**
     * Update the requisition details.
     */
    public function update(Request $request, string $orderNumber): RedirectResponse
    {
        $order = ShopOrder::where('order_number', $orderNumber)->firstOrFail();

        if ($request->user()->hasRole('shop-owner') && $order->shop_id !== $request->user()->shop_id) {
            abort(403, 'Unauthorized access.');
        }

        if (! $order->canEditDirectly()) {
            return redirect()->route('requisitions.show', $orderNumber)
                ->with('error', 'Requisition window has closed. You cannot edit this order directly.');
        }

        $itemsInput = $request->input('items', []);

        DB::transaction(function () use ($order, $itemsInput) {
            foreach ($itemsInput as $itemId => $qty) {
                $qtyVal = (float) $qty;
                $item = ShopOrderItem::where('shop_order_id', $order->id)->where('id', $itemId)->first();

                if ($item) {
                    if ($qtyVal <= 0) {
                        $item->delete();
                    } else {
                        $item->update(['requested_qty' => $qtyVal]);
                    }
                }
            }

            // Also check for newly added items via search/form if any (optional extension)
            $order->update([
                'submitted_at' => now(),
            ]);
        });

        return redirect()->route('requisitions.show', $orderNumber)
            ->with('success', 'Requisition updated successfully.');
    }

    /**
     * Request an update to a locked requisition (after cutoff).
     */
    public function requestUpdate(Request $request, string $orderNumber): RedirectResponse
    {
        $order = ShopOrder::where('order_number', $orderNumber)->firstOrFail();

        if ($request->user()->hasRole('shop-owner') && $order->shop_id !== $request->user()->shop_id) {
            abort(403, 'Unauthorized access.');
        }

        $reason = $request->input('reason');
        if (empty($reason)) {
            return redirect()->back()->with('error', 'Please provide a reason for the update request.');
        }

        $order->update([
            'state' => 'update_requested',
            'update_reason' => $reason,
        ]);

        return redirect()->route('requisitions.show', $orderNumber)
            ->with('success', 'Your update request has been submitted to the Purchase Manager.');
    }

    /**
     * Export the requisition items as CSV.
     */
    public function exportCsv(Request $request, string $orderNumber): StreamedResponse
    {
        $order = ShopOrder::where('order_number', $orderNumber)
            ->with(['items.product', 'shop'])
            ->firstOrFail();

        if ($request->user()->hasRole('shop-owner') && $order->shop_id !== $request->user()->shop_id) {
            abort(403, 'Unauthorized access.');
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="requisition_'.$order->order_number.'.csv"',
        ];

        $callback = function () use ($order): void {
            $file = fopen('php://output', 'w');
            if ($file) {
                fputcsv($file, ['Order ID', $order->order_number]);
                fputcsv($file, ['Shop', $order->shop ? $order->shop->name : 'N/A']);
                fputcsv($file, ['Delivery Date', $order->business_date->format('Y-m-d')]);
                fputcsv($file, ['Status', strtoupper($order->state)]);
                fputcsv($file, []);
                fputcsv($file, ['Product SKU', 'Product Name', 'Requested Qty', 'Approved Qty', 'Unit', 'Notes']);

                foreach ($order->items as $item) {
                    fputcsv($file, [
                        $item->product->sku,
                        $item->product->name,
                        $item->requested_qty,
                        $item->approved_qty ?? 'Pending',
                        $item->unit,
                        $item->notes ?? '',
                    ]);
                }
                fclose($file);
            }
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export the requisition as a print-friendly HTML view.
     */
    public function exportPdf(Request $request, string $orderNumber): View
    {
        $order = ShopOrder::where('order_number', $orderNumber)
            ->with(['items.product', 'shop', 'creator'])
            ->firstOrFail();

        if ($request->user()->hasRole('shop-owner') && $order->shop_id !== $request->user()->shop_id) {
            abort(403, 'Unauthorized access.');
        }

        return view('requisitions.print', compact('order'));
    }
}
