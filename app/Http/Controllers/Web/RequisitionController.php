<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\Supplier;
use App\Models\User;
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
        if ($request->user()->hasRole('shop') && $order->shop_id !== $request->user()->shop_id) {
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

        if ($request->user()->hasRole('shop') && $order->shop_id !== $request->user()->shop_id) {
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

        if ($request->user()->hasRole('shop') && $order->shop_id !== $request->user()->shop_id) {
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

        if ($request->user()->hasRole('shop') && $order->shop_id !== $request->user()->shop_id) {
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

        if ($request->user()->hasRole('shop') && $order->shop_id !== $request->user()->shop_id) {
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

        if ($request->user()->hasRole('shop') && $order->shop_id !== $request->user()->shop_id) {
            abort(403, 'Unauthorized access.');
        }

        return view('requisitions.print', compact('order'));
    }

    /**
     * Approve or reject a shop requisition and optionally adjust approved quantities.
     */
    public function review(Request $request, string $orderNumber): RedirectResponse
    {
        $order = ShopOrder::where('order_number', $orderNumber)->firstOrFail();

        // Enforce authorization: only purchase or users with purchasing.order.approve permission
        if (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve')) {
            abort(403, 'Unauthorized access.');
        }

        $action = $request->input('action');
        if ($action === 'reject') {
            DB::transaction(function () use ($order) {
                $order->update(['state' => 'rejected']);
                foreach ($order->items as $item) {
                    $item->update(['approved_qty' => 0.00]);
                }
            });

            return redirect()->route('requisitions.show', $order->order_number)
                ->with('success', 'Requisition rejected successfully.');
        }

        // Action is approve/update details
        $approvedQtys = $request->input('approved_qty', []);
        $fulfillmentTypes = $request->input('fulfillment_types', []);

        DB::transaction(function () use ($order, $approvedQtys, $fulfillmentTypes) {
            foreach ($order->items as $item) {
                // If an approved quantity is supplied, use it; otherwise default to requested quantity
                $qty = $approvedQtys[$item->id] ?? $item->requested_qty;
                $type = $fulfillmentTypes[$item->id] ?? $item->fulfillment_type ?? 'warehouse';
                $item->update([
                    'approved_qty' => max(0.00, (float) $qty),
                    'fulfillment_type' => $type,
                ]);
            }
            $order->update(['state' => 'approved']);

            // Sync Purchase Orders dynamically for this requisition's business date
            $this->syncPurchaseOrdersForDate($order->business_date);
        });

        return redirect()->route('requisitions.show', $order->order_number)
            ->with('success', 'Requisition approved, quantities updated, and Purchase Orders synced successfully.');
    }

    /**
     * Display the Consolidated Requisitions Board.
     */
    public function board(Request $request): View
    {
        // Enforce authorization: purchase or can approve orders
        if (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve')) {
            abort(403, 'Unauthorized access.');
        }

        $date = $request->input('date', Carbon::tomorrow()->format('Y-m-d'));

        // Load all active shops
        $shops = Shop::where('status', 'active')->orderBy('name')->get();

        // Load all active products (optionally grouped by category)
        $products = Product::with('category')->where('is_active', true)->orderBy('name')->get();

        // Load all shop orders for the selected date
        $orders = ShopOrder::where('business_date', $date)
            ->with(['items'])
            ->get();

        // Build a grid of quantities: [product_id][shop_id] = approved_qty ?? requested_qty
        $matrix = [];
        $productFulfillmentTypes = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $matrix[$item->product_id][$order->shop_id] = [
                    'requested_qty' => (float) $item->requested_qty,
                    'approved_qty' => $item->approved_qty !== null ? (float) $item->approved_qty : null,
                    'fulfillment_type' => $item->fulfillment_type ?? 'warehouse',
                ];
                if (! isset($productFulfillmentTypes[$item->product_id])) {
                    $productFulfillmentTypes[$item->product_id] = $item->fulfillment_type ?? 'warehouse';
                }
            }
        }

        // Set default 'warehouse' for any product not yet in $productFulfillmentTypes
        foreach ($products as $product) {
            if (! isset($productFulfillmentTypes[$product->id])) {
                $productFulfillmentTypes[$product->id] = 'warehouse';
            }
        }

        return view('requisitions.board', compact('date', 'shops', 'products', 'matrix', 'productFulfillmentTypes'));
    }

    /**
     * Save/Approve adjusted quantities on the Requisitions Board.
     */
    public function saveBoard(Request $request): RedirectResponse
    {
        // Enforce authorization
        if (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve')) {
            abort(403, 'Unauthorized access.');
        }

        $date = $request->input('date');
        if (! $date) {
            return redirect()->back()->with('error', 'Invalid date selected.');
        }

        // quantites is a 2D array: [product_id][shop_id] => value
        $quantities = $request->input('quantities', []);
        $fulfillmentTypes = $request->input('fulfillment_types', []);

        DB::transaction(function () use ($date, $quantities, $fulfillmentTypes, $request) {
            // Find all active shops to know who we might need to create orders for
            $shops = Shop::where('status', 'active')->get();

            foreach ($shops as $shop) {
                // Check if any product has a quantity for this shop
                $hasQty = false;
                foreach ($quantities as $productId => $shopQtys) {
                    if (isset($shopQtys[$shop->id]) && (float) $shopQtys[$shop->id] > 0) {
                        $hasQty = true;
                        break;
                    }
                }

                // Find or create the shop order for this shop and date
                $order = ShopOrder::where('shop_id', $shop->id)
                    ->where('business_date', $date)
                    ->first();

                if (! $order && $hasQty) {
                    // Create an approved order
                    $order = ShopOrder::create([
                        'shop_id' => $shop->id,
                        'business_date' => $date,
                        'state' => 'approved',
                        'submitted_at' => now(),
                        'deadline_at' => Carbon::parse($date)->subDay()->setTime(21, 30, 0),
                        'created_by' => $request->user()->id,
                    ]);
                }

                if ($order) {
                    // Update state to approved if not already
                    if ($order->state !== 'approved') {
                        $order->update(['state' => 'approved']);
                    }

                    // Process items
                    foreach ($quantities as $productId => $shopQtys) {
                        $qty = isset($shopQtys[$shop->id]) ? (float) $shopQtys[$shop->id] : 0.00;
                        $fulfillmentType = $fulfillmentTypes[$productId] ?? 'warehouse';

                        $item = ShopOrderItem::where('shop_order_id', $order->id)
                            ->where('product_id', $productId)
                            ->first();

                        if ($qty > 0) {
                            $product = Product::find($productId);
                            if ($product) {
                                if ($item) {
                                    $item->update([
                                        'approved_qty' => $qty,
                                        'fulfillment_type' => $fulfillmentType,
                                    ]);
                                } else {
                                    ShopOrderItem::create([
                                        'shop_order_id' => $order->id,
                                        'product_id' => $productId,
                                        'requested_qty' => $qty, // default requested_qty to the same as approved when created by manager
                                        'approved_qty' => $qty,
                                        'unit' => $product->unit,
                                        'fulfillment_type' => $fulfillmentType,
                                    ]);
                                }
                            }
                        } elseif ($item) {
                            // If quantity is set to 0/empty and an item existed, delete it
                            $item->delete();
                        }
                    }

                    // Clean up order if it has no items left
                    if ($order->items()->count() === 0) {
                        $order->delete();
                    }
                }
            }
        });

        return redirect()->route('requisitions.board', ['date' => $date])
            ->with('success', 'Consolidated requisitions saved and approved successfully.');
    }

    /**
     * Display the Approved Requisitions Board.
     */
    public function approvedBoard(Request $request): View
    {
        // Enforce authorization: purchase or can approve orders
        if (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve')) {
            abort(403, 'Unauthorized access.');
        }

        $date = $request->input('date', Carbon::tomorrow()->format('Y-m-d'));

        // Load all active shops
        $shops = Shop::where('status', 'active')->orderBy('name')->get();

        // Load all active products (optionally grouped by category)
        $products = Product::with('category')->where('is_active', true)->orderBy('name')->get();

        // Load only APPROVED shop orders for the selected date
        $orders = ShopOrder::where('business_date', $date)
            ->where('state', 'approved')
            ->with(['items'])
            ->get();

        // Build a grid of quantities: [product_id][shop_id] = approved_qty
        $matrix = [];
        $productFulfillmentTypes = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $matrix[$item->product_id][$order->shop_id] = [
                    'requested_qty' => (float) $item->requested_qty,
                    'approved_qty' => $item->approved_qty !== null ? (float) $item->approved_qty : null,
                    'fulfillment_type' => $item->fulfillment_type ?? 'warehouse',
                ];
                if (! isset($productFulfillmentTypes[$item->product_id])) {
                    $productFulfillmentTypes[$item->product_id] = $item->fulfillment_type ?? 'warehouse';
                }
            }
        }

        // Set default 'warehouse' for any product not yet in $productFulfillmentTypes
        foreach ($products as $product) {
            if (! isset($productFulfillmentTypes[$product->id])) {
                $productFulfillmentTypes[$product->id] = 'warehouse';
            }
        }

        // Load categorized suppliers
        $ownPurchaseSuppliers = Supplier::where('category', 'own_purchase')->orderBy('name')->get();
        $b2bSuppliers = Supplier::where('category', 'b2b')->orderBy('name')->get();

        // Build product supplier map based on existing POs for this date
        $existingPos = PurchaseOrder::whereDate('order_date', $date)->with(['items', 'supplier'])->get();
        $approvedBoardSynced = $existingPos->isNotEmpty();
        $productSupplierMap = [];
        foreach ($existingPos as $po) {
            foreach ($po->items as $item) {
                $productSupplierMap[$item->product_id] = $po->supplier_id;
            }
        }

        // Fall back to the last supplier used overall for each product
        $lastPoItems = PurchaseOrderItem::whereIn('product_id', $products->pluck('id'))
            ->with('purchaseOrder')
            ->orderBy('id', 'desc')
            ->get()
            ->unique('product_id');

        foreach ($lastPoItems as $item) {
            if (! isset($productSupplierMap[$item->product_id]) && $item->purchaseOrder) {
                $productSupplierMap[$item->product_id] = $item->purchaseOrder->supplier_id;
            }
        }

        return view('requisitions.approved_board', compact(
            'date',
            'shops',
            'products',
            'matrix',
            'productFulfillmentTypes',
            'ownPurchaseSuppliers',
            'b2bSuppliers',
            'productSupplierMap',
            'approvedBoardSynced',
            'existingPos'
        ));
    }

    /**
     * Save/Approve adjusted quantities on the Approved Requisitions Board.
     */
    public function saveApprovedBoard(Request $request): RedirectResponse
    {
        // Enforce authorization
        if (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve')) {
            abort(403, 'Unauthorized access.');
        }

        $date = $request->input('date');
        if (! $date) {
            return redirect()->back()->with('error', 'Invalid date selected.');
        }

        if (PurchaseOrder::whereDate('order_date', $date)->exists()) {
            return redirect()->route('requisitions.approved_board', ['date' => $date])
                ->with('error', 'Purchase Orders have already been generated for this date. Continue from the Purchase Orders screen.');
        }

        // quantities is a 2D array: [product_id][shop_id] => value
        $quantities = $request->input('quantities', []);
        $fulfillmentTypes = $request->input('fulfillment_types', []);
        $suppliers = $request->input('suppliers', []);
        $poSelectionEnabled = $request->has('po_selection_enabled');
        $selectedProductIds = $request->input('selected_products', []);

        DB::transaction(function () use ($date, $quantities, $fulfillmentTypes, $suppliers, $poSelectionEnabled, $selectedProductIds, $request): void {
            // Find all active shops to know who we might need to create orders for
            $shops = Shop::where('status', 'active')->get();

            foreach ($shops as $shop) {
                // Check if any product has a quantity for this shop
                $hasQty = false;
                foreach ($quantities as $productId => $shopQtys) {
                    if (isset($shopQtys[$shop->id]) && (float) $shopQtys[$shop->id] > 0) {
                        $hasQty = true;
                        break;
                    }
                }

                // Find or create the shop order for this shop and date
                $order = ShopOrder::where('shop_id', $shop->id)
                    ->whereDate('business_date', $date)
                    ->first();

                if (! $order && $hasQty) {
                    // Create an approved order
                    $order = ShopOrder::create([
                        'shop_id' => $shop->id,
                        'business_date' => $date,
                        'state' => 'approved',
                        'submitted_at' => now(),
                        'deadline_at' => Carbon::parse($date)->subDay()->setTime(21, 30, 0),
                        'created_by' => $request->user()->id,
                    ]);
                }

                if ($order) {
                    // Update state to approved if not already
                    if ($order->state !== 'approved') {
                        $order->update(['state' => 'approved']);
                    }

                    // Process items
                    foreach ($quantities as $productId => $shopQtys) {
                        $qty = isset($shopQtys[$shop->id]) ? (float) $shopQtys[$shop->id] : 0.00;
                        $fulfillmentType = $fulfillmentTypes[$productId] ?? 'warehouse';

                        $item = ShopOrderItem::where('shop_order_id', $order->id)
                            ->where('product_id', $productId)
                            ->first();

                        if ($qty > 0) {
                            $product = Product::find($productId);
                            if ($product) {
                                if ($item) {
                                    $item->update([
                                        'approved_qty' => $qty,
                                        'fulfillment_type' => $fulfillmentType,
                                    ]);
                                } else {
                                    ShopOrderItem::create([
                                        'shop_order_id' => $order->id,
                                        'product_id' => $productId,
                                        'requested_qty' => $qty,
                                        'approved_qty' => $qty,
                                        'unit' => $product->unit,
                                        'fulfillment_type' => $fulfillmentType,
                                    ]);
                                }
                            }
                        } elseif ($item) {
                            // If quantity is set to 0/empty and an item existed, delete it
                            $item->delete();
                        }
                    }

                    // Clean up order if it has no items left
                    if ($order->items()->count() === 0) {
                        $order->delete();
                    }
                }
            }

            // --- Generate and Sync Purchase Orders to Suppliers ---
            $this->syncPurchaseOrdersForDate($date, $suppliers, $poSelectionEnabled, $selectedProductIds);
        });

        return redirect()->route('requisitions.approved_board', ['date' => $date])
            ->with('success', 'Approved requisitions updated and Purchase Orders generated successfully.');
    }

    /**
     * Export the Approved Requisitions Board as CSV.
     */
    public function exportApprovedBoardCsv(Request $request): StreamedResponse
    {
        if (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve')) {
            abort(403, 'Unauthorized access.');
        }

        $date = $request->input('date', Carbon::tomorrow()->format('Y-m-d'));
        $shops = Shop::where('status', 'active')->orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('name')->get();
        $orders = ShopOrder::where('business_date', $date)
            ->where('state', 'approved')
            ->with(['items'])
            ->get();

        $matrix = [];
        $productFulfillmentTypes = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $matrix[$item->product_id][$order->shop_id] = $item->approved_qty;
                if (! isset($productFulfillmentTypes[$item->product_id])) {
                    $productFulfillmentTypes[$item->product_id] = $item->fulfillment_type ?? 'warehouse';
                }
            }
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="approved_board_export_'.$date.'.csv"',
        ];

        $callback = function () use ($shops, $products, $matrix, $productFulfillmentTypes, $date): void {
            $file = fopen('php://output', 'w');
            if ($file) {
                fputcsv($file, ['Approved Consolidated Requisitions Board', 'Date: '.$date]);
                fputcsv($file, []);

                // Header Row
                $headerRow = ['SL No', 'Item Name', 'SKU', 'Fulfillment'];
                foreach ($shops as $shop) {
                    $headerRow[] = $shop->name;
                }
                $headerRow[] = 'Total';
                fputcsv($file, $headerRow);

                // Body Rows
                foreach ($products as $index => $product) {
                    $rowTotal = 0;
                    $row = [
                        $index + 1,
                        $product->name,
                        $product->sku,
                        ucfirst($productFulfillmentTypes[$product->id] ?? 'warehouse'),
                    ];

                    foreach ($shops as $shop) {
                        $qty = $matrix[$product->id][$shop->id] ?? 0;
                        $row[] = $qty > 0 ? number_format((float) $qty, 2) : '-';
                        if ($qty > 0) {
                            $rowTotal += $qty;
                        }
                    }

                    $row[] = number_format($rowTotal, 2).' '.$product->unit;

                    fputcsv($file, $row);
                }
                fclose($file);
            }
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export the Approved Requisitions Board as a print-friendly HTML view.
     */
    public function exportApprovedBoardPdf(Request $request): View
    {
        if (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve')) {
            abort(403, 'Unauthorized access.');
        }

        $date = $request->input('date', Carbon::tomorrow()->format('Y-m-d'));
        $type = $request->input('type', 'both');
        $shops = Shop::where('status', 'active')->orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('name')->get();
        $orders = ShopOrder::where('business_date', $date)
            ->where('state', 'approved')
            ->with(['items'])
            ->get();

        $matrix = [];
        $productFulfillmentTypes = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $matrix[$item->product_id][$order->shop_id] = $item->approved_qty;
                if (! isset($productFulfillmentTypes[$item->product_id])) {
                    $productFulfillmentTypes[$item->product_id] = $item->fulfillment_type ?? 'warehouse';
                }
            }
        }

        foreach ($products as $product) {
            if (! isset($productFulfillmentTypes[$product->id])) {
                $productFulfillmentTypes[$product->id] = 'warehouse';
            }
        }

        return view('requisitions.approved_board_pdf', compact('date', 'shops', 'products', 'matrix', 'productFulfillmentTypes', 'type'));
    }

    /**
     * Synchronize and generate/update Purchase Orders for a given business date.
     */
    private function syncPurchaseOrdersForDate(
        string|Carbon $date,
        array $suppliers = [],
        bool $poSelectionEnabled = false,
        array $selectedProductIds = []
    ): void {
        $dateStr = $date instanceof \Carbon\Carbon ? $date->format('Y-m-d') : (string) $date;

        // Group approved product quantities by supplier and fulfillment type
        $poGroups = []; // Format: [supplier_id][fulfillment_type][product_id] => total_qty

        // We can query all approved shop order items for this date
        $approvedItems = ShopOrderItem::whereHas('order', function ($query) use ($dateStr): void {
            $query->whereDate('business_date', $dateStr)
                ->where('state', 'approved');
        })->get();

        // Load all active products to resolve fallback suppliers if needed
        $products = Product::where('is_active', true)->get();

        // Get fallback suppliers (last PO supplier used overall for each product)
        $productSupplierMap = [];
        $lastPoItems = PurchaseOrderItem::whereIn('product_id', $products->pluck('id'))
            ->with('purchaseOrder')
            ->orderBy('id', 'desc')
            ->get()
            ->unique('product_id');

        foreach ($lastPoItems as $item) {
            if ($item->purchaseOrder) {
                $productSupplierMap[$item->product_id] = $item->purchaseOrder->supplier_id;
            }
        }

        // Aggregate them by product
        $productTotals = [];
        foreach ($approvedItems as $item) {
            // Filter by selection if enabled
            if (! $poSelectionEnabled || in_array($item->product_id, $selectedProductIds)) {
                if (! isset($productTotals[$item->product_id])) {
                    $productTotals[$item->product_id] = 0.00;
                }
                $productTotals[$item->product_id] += (float) ($item->approved_qty ?? $item->requested_qty);
            }
        }

        // Group by supplier and fulfillment type
        foreach ($productTotals as $productId => $totalQty) {
            if ($totalQty <= 0) {
                continue;
            }

            $supplierId = isset($suppliers[$productId]) ? (int) $suppliers[$productId] : ($productSupplierMap[$productId] ?? null);

            if (! $supplierId) {
                // Default to the first own_purchase supplier as a final fallback
                $firstSupplier = Supplier::where('category', 'own_purchase')->first() ?? Supplier::first();
                if ($firstSupplier) {
                    $supplierId = $firstSupplier->id;
                }
            }

            $fulfillmentType = ShopOrderItem::whereHas('order', function ($query) use ($dateStr): void {
                $query->whereDate('business_date', $dateStr)
                    ->where('state', 'approved');
            })->where('product_id', $productId)->value('fulfillment_type') ?? 'warehouse';

            if ($supplierId) {
                $poGroups[$supplierId][$fulfillmentType][$productId] = $totalQty;
            }
        }

        // Track which PO IDs we touched or created
        $activePoIds = [];

        foreach ($poGroups as $supplierId => $types) {
            foreach ($types as $fulfillmentType => $items) {
                // Find or create the PO for this supplier, date, and fulfillment type
                $po = PurchaseOrder::whereDate('order_date', $dateStr)
                    ->where('supplier_id', $supplierId)
                    ->where('fulfillment_type', $fulfillmentType)
                    ->first();

                if (! $po) {
                    // Generate PO number
                    $dateStrStr = Carbon::parse($dateStr)->format('Ymd');
                    do {
                        $suffix = strtoupper(bin2hex(random_bytes(2)));
                        $poNumber = "PO-{$dateStrStr}-{$suffix}";
                    } while (PurchaseOrder::where('po_number', $poNumber)->exists());

                    $po = PurchaseOrder::create([
                        'supplier_id' => $supplierId,
                        'po_number' => $poNumber,
                        'status' => POStatus::Approved, // Directly approved
                        'order_date' => $dateStr,
                        'created_by' => auth()->id() ?? User::role('purchase')->first()?->id ?? 1,
                        'fulfillment_type' => $fulfillmentType,
                        'notes' => 'Auto-generated from Requisitions System',
                    ]);
                } else {
                    // If it exists, clear existing items so we can recreate them
                    $po->items()->delete();
                    // If it's a draft, make sure to set it to Approved
                    if ($po->status !== POStatus::Approved) {
                        $po->update(['status' => POStatus::Approved]);
                    }
                }

                $activePoIds[] = $po->id;

                // Create items
                foreach ($items as $productId => $qty) {
                    $product = Product::find($productId);
                    if ($product) {
                        // Find the last purchase price for this product
                        $lastPrice = PurchaseOrderItem::where('product_id', $productId)
                            ->latest('id')
                            ->value('unit_price') ?? 1.00;

                        $po->items()->create([
                            'product_id' => $productId,
                            'quantity' => $qty,
                            'unit_price' => $lastPrice,
                        ]);
                    }
                }
            }
        }

        // Clean up any empty POs that were generated for this date but are no longer in our active list
        $allGeneratedPos = PurchaseOrder::whereDate('order_date', $dateStr)
            ->where(function ($q) {
                $q->where('notes', 'Auto-generated from Approved Requisitions Board')
                    ->orWhere('notes', 'Auto-generated from Requisitions System');
            })
            ->get();

        foreach ($allGeneratedPos as $po) {
            if (! in_array($po->id, $activePoIds, true)) {
                $po->items()->delete();
                $po->forceDelete(); // Force delete empty auto-generated POs
            }
        }
    }

    /**
     * Show the delivery check-in form.
     */
    public function showDelivery(Request $request, string $orderNumber): View|RedirectResponse
    {
        $order = ShopOrder::where('order_number', $orderNumber)
            ->with(['items.product', 'shop'])
            ->firstOrFail();

        // Access control: Shop Owner can only see their own shop orders
        if ($request->user()->hasRole('shop') && $order->shop_id !== $request->user()->shop_id) {
            abort(403, 'Unauthorized access to shop order.');
        }

        if (! $order->is_allocation_completed) {
            return redirect()->route('requisitions.show', $orderNumber)
                ->with('error', 'This order has not been dispatched/allocated from the warehouse yet.');
        }

        if ($order->is_delivered) {
            return redirect()->route('requisitions.show', $orderNumber)
                ->with('error', 'This order has already been checked-in and marked as delivered.');
        }

        // Resolve unit cost for each item
        foreach ($order->items as $item) {
            $item->resolved_unit_cost = $this->resolveProductUnitCost(
                $item->product_id,
                $order->business_date->format('Y-m-d')
            );
        }

        return view('requisitions.delivery', compact('order'));
    }

    /**
     * Record delivery check-in and verify discrepancies.
     */
    public function recordDelivery(Request $request, string $orderNumber): RedirectResponse
    {
        $order = ShopOrder::where('order_number', $orderNumber)
            ->with(['items'])
            ->firstOrFail();

        // Access control: Shop Owner can only see their own shop orders
        if ($request->user()->hasRole('shop') && $order->shop_id !== $request->user()->shop_id) {
            abort(403, 'Unauthorized access to shop order.');
        }

        if (! $order->is_allocation_completed) {
            return redirect()->route('requisitions.show', $orderNumber)
                ->with('error', 'This order has not been dispatched/allocated from the warehouse yet.');
        }

        if ($order->is_delivered) {
            return redirect()->route('requisitions.show', $orderNumber)
                ->with('error', 'This order has already been checked-in and marked as delivered.');
        }

        $request->validate([
            'delivered_qty' => ['required', 'array'],
            'delivered_qty.*' => ['required', 'numeric', 'min:0'],
            'cash_collected' => ['required', 'numeric', 'min:0'],
            'delivery_notes' => ['nullable', 'string'],
        ]);

        $deliveredQtys = $request->input('delivered_qty', []);
        $cashCollected = (float) $request->input('cash_collected', 0.00);

        DB::transaction(function () use ($order, $deliveredQtys, $cashCollected, $request): void {
            $totalShortageValue = 0.00;
            $expectedDeliveredValue = 0.00;

            foreach ($order->items as $item) {
                $deliveredQty = (float) ($deliveredQtys[$item->id] ?? 0.00);
                $approvedQty = (float) ($item->approved_qty ?? 0.00);

                // shortage_qty = approved_qty - delivered_qty (shorted amount)
                $shortageQty = max(0.00, $approvedQty - $deliveredQty);

                // Fetch unit cost
                $unitCost = $this->resolveProductUnitCost(
                    $item->product_id,
                    $order->business_date->format('Y-m-d')
                );

                $shortageValue = $shortageQty * $unitCost;
                $itemExpectedValue = $deliveredQty * $unitCost;

                $item->update([
                    'delivered_qty' => $deliveredQty,
                    'shortage_qty' => $shortageQty,
                    'unit_cost' => $unitCost,
                    'shortage_value' => $shortageValue,
                ]);

                $totalShortageValue += $shortageValue;
                $expectedDeliveredValue += $itemExpectedValue;
            }

            // cash_discrepancy = Expected Delivered Value - cash_collected
            $cashDiscrepancy = $expectedDeliveredValue - $cashCollected;

            $order->update([
                'is_delivered' => true,
                'delivered_at' => now(),
                'delivered_by' => $request->user()->id,
                'delivery_notes' => $request->input('delivery_notes'),
                'cash_collected' => $cashCollected,
                'cash_discrepancy' => $cashDiscrepancy,
                'total_shortage_value' => $totalShortageValue,
            ]);

            activity()
                ->performedOn($order)
                ->causedBy($request->user())
                ->log('delivered');
        });

        return redirect()->route('requisitions.show', $order->order_number)
            ->with('success', 'Delivery checked-in and discrepancies recorded successfully.');
    }

    /**
     * Resolve the unit cost (cost_per_kg) for a product from the daily StockBatch or latest overall.
     */
    private function resolveProductUnitCost(int $productId, string $businessDate): float
    {
        $cost = DB::table('stock_batches')
            ->where('product_id', $productId)
            ->whereDate('received_at', $businessDate)
            ->whereNull('deleted_at')
            ->latest('id')
            ->value('cost_per_kg');

        if ($cost !== null) {
            return (float) $cost;
        }

        $cost = DB::table('stock_batches')
            ->where('product_id', $productId)
            ->whereNull('deleted_at')
            ->latest('received_at')
            ->latest('id')
            ->value('cost_per_kg');

        return $cost !== null ? (float) $cost : 0.00;
    }
}
