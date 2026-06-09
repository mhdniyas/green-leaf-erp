<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
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
use App\Notifications\PurchaseOrderCreatedNotification;
use App\Notifications\PurchasingOrderRevisionRequestedNotification;
use App\Notifications\PurchasingOrderSubmittedNotification;
use App\Services\Inventory\StockLedgerService;
use App\Services\Pricing\PriceBoardService;
use App\Services\Requisition\ShopOrderRevisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequisitionController extends Controller
{
    public function __construct(
        private readonly StockLedgerService $stockLedgerService,
        private readonly PriceBoardService $priceBoardService,
        private readonly ShopOrderRevisionService $shopOrderRevisionService,
    ) {}

    /**
     * Store a newly created shop requisition.
     *
     * @return JsonResponse|RedirectResponse
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->shop_id) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'User is not associated with any shop.'], 400);
            }

            return redirect()->route('shop-owner.orders.create')
                ->withErrors(['items' => 'User is not associated with any shop.'])
                ->withInput();
        }

        $items = $this->resolveRequestedProducts($request->input('items', []));

        if ($items === []) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Requisition cannot be empty.'], 400);
            }

            return redirect()->route('shop-owner.orders.create')
                ->withErrors(['items' => 'Requisition cannot be empty.'])
                ->withInput();
        }

        $businessDate = Carbon::tomorrow()->format('Y-m-d');

        // Enforcement: check if cutoff has passed for tomorrow's date
        // Cutoff is today 9:30 PM.
        $cutoff = Carbon::today()->setTime(21, 30, 0);

        if (now()->greaterThan($cutoff)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Requisition submission window has closed (9:30 PM cutoff).'], 400);
            }

            return redirect()->route('shop-owner.orders.create')
                ->withErrors(['items' => 'Requisition submission window has closed (9:30 PM cutoff).'])
                ->withInput();
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

            $this->syncShopOrderItems($shopOrder, $items);

            return $shopOrder;
        });

        $this->shopOrderRevisionService->notifyPurchaseManagers(
            new PurchasingOrderSubmittedNotification($order->loadMissing('shop'))
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'order_number' => $order->order_number,
                'redirect_url' => $user->hasRole('shop')
                    ? route('shop-owner.orders.show', $order->order_number)
                    : route('requisitions.show', $order->order_number),
            ]);
        }

        return redirect()->route(
            $user->hasRole('shop') ? 'shop-owner.orders.show' : 'requisitions.show',
            $order->order_number
        )
            ->with('success', 'Tomorrow order submitted successfully.');
    }

    /**
     * Display the specified requisition details.
     */
    public function show(Request $request, string $orderNumber): View|RedirectResponse
    {
        $order = ShopOrder::where('order_number', $orderNumber)
            ->with(['items.product', 'shop', 'creator'])
            ->firstOrFail();

        $user = $request->user();

        if ($user->hasRole('shop') && $order->shop_id !== $user->shop_id) {
            abort(403, 'Unauthorized access to shop order.');
        }

        if ($user->hasRole('shop')) {
            return redirect()->route('shop-owner.orders.show', $order->order_number);
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

        if ($request->user()->hasRole('shop')) {
            return redirect()->route(
                $order->canEditDirectly() ? 'shop-owner.orders.create' : 'shop-owner.orders.show',
                $order->canEditDirectly() ? [] : $order->order_number
            );
        }

        if (! $order->canEditDirectly()) {
            return redirect()->route(
                $request->user()->hasRole('shop') ? 'shop-owner.orders.show' : 'requisitions.show',
                $orderNumber
            )
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
            return redirect()->route(
                $request->user()->hasRole('shop') ? 'shop-owner.orders.show' : 'requisitions.show',
                $orderNumber
            )
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

        return redirect()->route(
            $request->user()->hasRole('shop') ? 'shop-owner.orders.show' : 'requisitions.show',
            $orderNumber
        )
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

        $canRequestApprovedUpdate = $order->state === 'approved';

        if ((! in_array($order->state, ['submitted', 'update_requested'], true) && ! $canRequestApprovedUpdate) || $order->is_delivered) {
            return redirect()->route('shop-owner.orders.show', $orderNumber)
                ->with('error', 'This order can no longer be modified from the shop owner workflow.');
        }

        $items = $this->resolveRequestedProducts($request->input('items', []));
        if ($items === []) {
            return redirect()->route('shop-owner.orders.create')
                ->withErrors(['items' => 'Updated order cannot be empty.']);
        }

        $reason = trim((string) $request->input('reason', ''));

        if ($order->state === 'approved') {
            try {
                $revision = DB::transaction(function () use ($order, $items, $reason, $request) {
                    return $this->shopOrderRevisionService->createApprovedOrderRevision(
                        $order,
                        $items,
                        $request->user(),
                        $reason
                    );
                });
            } catch (ValidationException $exception) {
                return redirect()->route('shop-owner.orders.show', $orderNumber)
                    ->withErrors($exception->errors())
                    ->with('error', collect($exception->errors())->flatten()->first());
            }

            if (! $revision) {
                return redirect()->route('shop-owner.orders.show', $orderNumber)
                    ->with('error', 'No quantity changes were found in this updated request.');
            }

            $this->shopOrderRevisionService->notifyPurchaseManagers(
                new PurchasingOrderRevisionRequestedNotification($revision->loadMissing(['shopOrder.shop', 'items']))
            );

            return redirect()->route(
                $request->user()->hasRole('shop') ? 'shop-owner.orders.show' : 'requisitions.show',
                $orderNumber
            )
                ->with('success', sprintf(
                    'Your updated order request (Update #%d) has been submitted to the Purchase Manager.',
                    $revision->revision_no
                ));
        }

        DB::transaction(function () use ($order, $items, $reason): void {
            $this->syncShopOrderItems($order, $items);

            $order->update([
                'state' => 'update_requested',
                'update_reason' => $reason !== '' ? $reason : 'Shop owner requested quantity changes after cutoff.',
                'submitted_at' => now(),
            ]);
        });

        return redirect()->route(
            $request->user()->hasRole('shop') ? 'shop-owner.orders.show' : 'requisitions.show',
            $orderNumber
        )
            ->with('success', 'Your updated order request has been submitted to the Purchase Manager.');
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
        if ($request->user()->hasRole('shop') || (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve'))) {
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
        if ($request->user()->hasRole('shop') || (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve'))) {
            abort(403, 'Unauthorized access.');
        }

        $date = $request->input('date', Carbon::tomorrow()->format('Y-m-d'));

        // Load all active shops
        $shops = Shop::where('status', 'active')->orderBy('name')->get();

        // Load all active products (optionally grouped by category)
        $products = Product::with('category')->where('is_active', true)->orderBy('name')->get();

        // Load all shop orders for the selected date
        $orders = ShopOrder::whereDate('business_date', $date)
            ->with(['items', 'latestPendingRevision.items'])
            ->get();
        [
            'matrix' => $matrix,
            'productFulfillmentTypes' => $productFulfillmentTypes,
            'shopUpdateMeta' => $shopUpdateMeta,
            'shopPoStatusMeta' => $shopPoStatusMeta,
        ] = $this->buildBoardPresentationData($orders, $products);
        $boardFullyApproved = $orders->isNotEmpty()
            && $orders->every(static fn (ShopOrder $order): bool => $order->state === 'approved');

        return view('requisitions.board', compact(
            'date',
            'shops',
            'products',
            'matrix',
            'productFulfillmentTypes',
            'shopUpdateMeta',
            'shopPoStatusMeta',
            'boardFullyApproved'
        ));
    }

    /**
     * Save/Approve adjusted quantities on the Requisitions Board.
     */
    public function saveBoard(Request $request): RedirectResponse
    {
        // Enforce authorization
        if ($request->user()->hasRole('shop') || (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve'))) {
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
                    if ($order->state !== 'approved' || $order->update_reason !== null) {
                        $order->update([
                            'state' => 'approved',
                            'update_reason' => null,
                        ]);
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
                                $pricePayload = $this->lockedPricePayload($order, $product, $qty);
                                if ($item) {
                                    $item->update([
                                        'approved_qty' => $qty,
                                        'fulfillment_type' => $fulfillmentType,
                                        ...$pricePayload,
                                    ]);
                                } else {
                                    ShopOrderItem::create([
                                        'shop_order_id' => $order->id,
                                        'product_id' => $productId,
                                        'requested_qty' => $qty, // default requested_qty to the same as approved when created by manager
                                        'approved_qty' => $qty,
                                        'unit' => $product->unit,
                                        'fulfillment_type' => $fulfillmentType,
                                        ...$pricePayload,
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

            $pendingRevisionOrders = ShopOrder::query()
                ->whereDate('business_date', $date)
                ->where('has_pending_revision', true)
                ->with(['latestPendingRevision.items'])
                ->get();

            foreach ($pendingRevisionOrders as $order) {
                $shopQuantities = [];

                foreach ($order->latestPendingRevision?->items ?? [] as $revisionItem) {
                    $productId = (int) $revisionItem->product_id;
                    $shopQuantities[$productId] = isset($quantities[$productId][$order->shop_id])
                        ? (float) $quantities[$productId][$order->shop_id]
                        : (float) $revisionItem->new_requested_qty;
                }

                $this->shopOrderRevisionService->applyPendingRevision(
                    $order,
                    $request->user(),
                    $shopQuantities,
                    $fulfillmentTypes,
                    []
                );
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
        if ($request->user()->hasRole('shop') || (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve'))) {
            abort(403, 'Unauthorized access.');
        }

        $date = $request->input('date', Carbon::tomorrow()->format('Y-m-d'));

        // Load all active shops
        $shops = Shop::where('status', 'active')->orderBy('name')->get();

        $existingPos = PurchaseOrder::whereDate('order_date', $date)->with(['items', 'supplier'])->get();
        $approvedBoardSynced = $existingPos->isNotEmpty();
        $poBackedProductIds = $approvedBoardSynced
            ? $existingPos->flatMap(fn (PurchaseOrder $po) => $po->items->pluck('product_id'))->unique()->values()->all()
            : [];

        // Load all active products (optionally grouped by category)
        $products = Product::with('category')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Load only APPROVED shop orders for the selected date
        $orders = ShopOrder::whereDate('business_date', $date)
            ->whereIn('state', ['approved', 'update_requested'])
            ->with(['items', 'latestPendingRevision.items'])
            ->get();
        [
            'matrix' => $matrix,
            'productFulfillmentTypes' => $productFulfillmentTypes,
            'shopUpdateMeta' => $shopUpdateMeta,
            'shopPoStatusMeta' => $shopPoStatusMeta,
        ] = $this->buildBoardPresentationData($orders, $products);

        $approvedProductIds = $products
            ->filter(fn (Product $product): bool => $this->calculateProductRowTotal($matrix[$product->id] ?? []) > 0)
            ->pluck('id')
            ->values()
            ->all();
        $poBackedApprovedProductCount = count(array_intersect($approvedProductIds, $poBackedProductIds));

        // Load categorized suppliers
        $ownPurchaseSuppliers = Supplier::where('category', 'own_purchase')->orderBy('name')->get();
        $b2bSuppliers = Supplier::where('category', 'b2b')->orderBy('name')->get();
        $defaultPurchaseSupplier = Supplier::defaultPurchase()->first();

        // Build product supplier map based on existing POs for this date
        $productSupplierMap = [];
        foreach ($existingPos as $po) {
            foreach ($po->items as $item) {
                $productSupplierMap[$item->product_id] = $po->supplier_id;
            }
        }

        if ($defaultPurchaseSupplier) {
            foreach ($products as $product) {
                $productSupplierMap[$product->id] ??= $defaultPurchaseSupplier->id;
            }
        } else {
            // Fall back to the last supplier used overall for each product when no global default is configured.
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
            'poBackedProductIds',
            'approvedProductIds',
            'poBackedApprovedProductCount',
            'existingPos',
            'shopUpdateMeta',
            'shopPoStatusMeta'
        ));
    }

    /**
     * Save/Approve adjusted quantities on the Approved Requisitions Board.
     */
    public function saveApprovedBoard(Request $request): RedirectResponse
    {
        // Enforce authorization
        if ($request->user()->hasRole('shop') || (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve'))) {
            abort(403, 'Unauthorized access.');
        }

        $date = $request->input('date');
        if (! $date) {
            return redirect()->back()->with('error', 'Invalid date selected.');
        }

        if (PurchaseOrder::whereDate('order_date', $date)->exists()) {
            $pendingRevisionOrders = ShopOrder::query()
                ->whereDate('business_date', $date)
                ->where('has_pending_revision', true)
                ->with(['latestPendingRevision.items'])
                ->get();

            if ($pendingRevisionOrders->isEmpty()) {
                return redirect()->route('requisitions.approved_board', ['date' => $date])
                    ->with('error', 'Purchase Orders have already been generated for this date. Continue from the Purchase Orders screen.');
            }

            $quantities = $request->input('quantities', []);
            $fulfillmentTypes = $request->input('fulfillment_types', []);
            $suppliers = $request->input('suppliers', []);

            foreach ($pendingRevisionOrders as $order) {
                $shopQuantities = [];

                foreach ($order->latestPendingRevision?->items ?? [] as $revisionItem) {
                    $productId = (int) $revisionItem->product_id;
                    $shopQuantities[$productId] = isset($quantities[$productId][$order->shop_id])
                        ? (float) $quantities[$productId][$order->shop_id]
                        : (float) $revisionItem->new_requested_qty;
                }

                $revision = $this->shopOrderRevisionService->applyPendingRevision(
                    $order,
                    $request->user(),
                    $shopQuantities,
                    $fulfillmentTypes,
                    $suppliers
                );

                if ($revision && $revision->status === 'blocked') {
                    return redirect()->route('requisitions.approved_board', ['date' => $date])
                        ->withInput()
                        ->with('error', 'This revision cannot be applied because goods receipt has already started for the linked purchase order.');
                }
            }

            return redirect()->route('requisitions.approved_board', ['date' => $date])
                ->with('success', 'Pending approved-order updates were applied to the linked purchase orders.');
        }

        // quantities is a 2D array: [product_id][shop_id] => value
        $quantities = $request->input('quantities', []);
        $fulfillmentTypes = $request->input('fulfillment_types', []);
        $suppliers = $request->input('suppliers', []);

        $missingSupplierProducts = Product::query()
            ->whereIn('id', $this->resolveProductsMissingSuppliers(
                $quantities,
                $suppliers
            ))
            ->orderBy('name')
            ->pluck('name')
            ->all();

        if ($missingSupplierProducts !== []) {
            return redirect()->route('requisitions.approved_board', ['date' => $date])
                ->withInput()
                ->with('error', 'Select a supplier for every selected product before generating purchase orders. Missing suppliers: '.implode(', ', $missingSupplierProducts).'.');
        }

        DB::transaction(function () use ($date, $quantities, $fulfillmentTypes, $suppliers, $request): void {
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
                    if ($order->state !== 'approved' || $order->update_reason !== null) {
                        $order->update([
                            'state' => 'approved',
                            'update_reason' => null,
                        ]);
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
                                $pricePayload = $this->lockedPricePayload($order, $product, $qty);
                                if ($item) {
                                    $item->update([
                                        'approved_qty' => $qty,
                                        'fulfillment_type' => $fulfillmentType,
                                        ...$pricePayload,
                                    ]);
                                } else {
                                    ShopOrderItem::create([
                                        'shop_order_id' => $order->id,
                                        'product_id' => $productId,
                                        'requested_qty' => $qty,
                                        'approved_qty' => $qty,
                                        'unit' => $product->unit,
                                        'fulfillment_type' => $fulfillmentType,
                                        ...$pricePayload,
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
            $this->syncPurchaseOrdersForDate($date, $suppliers);
        });

        return redirect()->route('requisitions.approved_board', ['date' => $date])
            ->with('success', 'Approved requisitions updated and Purchase Orders generated successfully.');
    }

    /**
     * Export the Approved Requisitions Board as CSV.
     */
    public function exportApprovedBoardCsv(Request $request): StreamedResponse
    {
        if ($request->user()->hasRole('shop') || (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve'))) {
            abort(403, 'Unauthorized access.');
        }

        $date = $request->input('date', Carbon::tomorrow()->format('Y-m-d'));
        $shops = Shop::where('status', 'active')->orderBy('name')->get();
        $products = Product::with('category')->where('is_active', true)->orderBy('name')->get();
        $orders = ShopOrder::whereDate('business_date', $date)
            ->whereIn('state', ['approved', 'update_requested'])
            ->with(['items'])
            ->get();
        [
            'matrix' => $matrix,
            'productFulfillmentTypes' => $productFulfillmentTypes,
        ] = $this->buildBoardPresentationData($orders, $products);
        $filteredProducts = $this->filterBoardProductsForExport($products, $matrix, $productFulfillmentTypes, $request);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="approved_board_export_'.$date.'.csv"',
        ];

        $callback = function () use ($shops, $filteredProducts, $matrix, $productFulfillmentTypes, $date): void {
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
                foreach ($filteredProducts->values() as $index => $product) {
                    $rowTotal = 0.0;
                    $row = [
                        $index + 1,
                        $product->name,
                        $product->sku,
                        ucfirst($productFulfillmentTypes[$product->id] ?? 'warehouse'),
                    ];

                    foreach ($shops as $shop) {
                        $qty = $this->resolveMatrixQuantity($matrix[$product->id][$shop->id] ?? null);
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
        if ($request->user()->hasRole('shop') || (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve'))) {
            abort(403, 'Unauthorized access.');
        }

        $date = $request->input('date', Carbon::tomorrow()->format('Y-m-d'));
        $type = (string) $request->input('type', 'both');
        $shops = Shop::where('status', 'active')->orderBy('name')->get();
        $products = Product::with('category')->where('is_active', true)->orderBy('name')->get();
        $orders = ShopOrder::whereDate('business_date', $date)
            ->whereIn('state', ['approved', 'update_requested'])
            ->with(['items'])
            ->get();
        [
            'matrix' => $matrix,
            'productFulfillmentTypes' => $productFulfillmentTypes,
        ] = $this->buildBoardPresentationData($orders, $products);
        $filteredProducts = $this->filterBoardProductsForExport($products, $matrix, $productFulfillmentTypes, $request);

        return view('requisitions.board_pdf', [
            'date' => $date,
            'shops' => $shops,
            'products' => $filteredProducts,
            'matrix' => $matrix,
            'productFulfillmentTypes' => $productFulfillmentTypes,
            'boardTitle' => match ($type) {
                'warehouse' => 'Approved Warehouse (Bulk) Requisitions Board',
                'selection' => 'Approved Selection (Packet) Requisitions Board',
                default => 'Approved Consolidated Requisitions Board',
            },
        ]);
    }

    public function exportBoardCsv(Request $request): StreamedResponse
    {
        if ($request->user()->hasRole('shop') || (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve'))) {
            abort(403, 'Unauthorized access.');
        }

        $date = $request->input('date', Carbon::tomorrow()->format('Y-m-d'));
        $shops = Shop::where('status', 'active')->orderBy('name')->get();
        $products = Product::with('category')->where('is_active', true)->orderBy('name')->get();
        $orders = ShopOrder::whereDate('business_date', $date)
            ->with(['items'])
            ->get();
        [
            'matrix' => $matrix,
            'productFulfillmentTypes' => $productFulfillmentTypes,
        ] = $this->buildBoardPresentationData($orders, $products);
        $filteredProducts = $this->filterBoardProductsForExport($products, $matrix, $productFulfillmentTypes, $request);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="requisitions_board_export_'.$date.'.csv"',
        ];

        $callback = function () use ($shops, $filteredProducts, $matrix, $productFulfillmentTypes, $date): void {
            $file = fopen('php://output', 'w');
            if ($file) {
                fputcsv($file, ['Consolidated Requisitions Board', 'Date: '.$date]);
                fputcsv($file, []);

                $headerRow = ['SL No', 'Item Name', 'SKU', 'Fulfillment'];
                foreach ($shops as $shop) {
                    $headerRow[] = $shop->name;
                }
                $headerRow[] = 'Total';
                fputcsv($file, $headerRow);

                foreach ($filteredProducts->values() as $index => $product) {
                    $rowTotal = 0.0;
                    $row = [
                        $index + 1,
                        $product->name,
                        $product->sku,
                        ucfirst($productFulfillmentTypes[$product->id] ?? 'warehouse'),
                    ];

                    foreach ($shops as $shop) {
                        $qty = $this->resolveMatrixQuantity($matrix[$product->id][$shop->id] ?? null);
                        $row[] = $qty > 0 ? number_format($qty, 2) : '-';
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

    public function exportBoardPdf(Request $request): View
    {
        if ($request->user()->hasRole('shop') || (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve'))) {
            abort(403, 'Unauthorized access.');
        }

        $date = $request->input('date', Carbon::tomorrow()->format('Y-m-d'));
        $shops = Shop::where('status', 'active')->orderBy('name')->get();
        $products = Product::with('category')->where('is_active', true)->orderBy('name')->get();
        $orders = ShopOrder::whereDate('business_date', $date)
            ->with(['items'])
            ->get();
        [
            'matrix' => $matrix,
            'productFulfillmentTypes' => $productFulfillmentTypes,
        ] = $this->buildBoardPresentationData($orders, $products);
        $filteredProducts = $this->filterBoardProductsForExport($products, $matrix, $productFulfillmentTypes, $request);

        return view('requisitions.board_pdf', [
            'date' => $date,
            'shops' => $shops,
            'products' => $filteredProducts,
            'matrix' => $matrix,
            'productFulfillmentTypes' => $productFulfillmentTypes,
            'boardTitle' => 'Consolidated Requisitions Board',
        ]);
    }

    /**
     * Synchronize and generate/update Purchase Orders for a given business date.
     */
    private function syncPurchaseOrdersForDate(
        string|Carbon $date,
        array $suppliers = []
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
        $defaultPurchaseSupplier = Supplier::defaultPurchase()->first();

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
        $productFulfillmentTypes = [];

        foreach ($approvedItems as $item) {
            if (! isset($productTotals[$item->product_id])) {
                $productTotals[$item->product_id] = 0.00;
            }
            $productTotals[$item->product_id] += (float) ($item->approved_qty ?? $item->requested_qty);
            $productFulfillmentTypes[$item->product_id] ??= $item->fulfillment_type ?? 'warehouse';
        }

        $approvedProductIds = collect(array_keys($productTotals));
        $productsById = Product::whereIn('id', $approvedProductIds)->get()->keyBy('id');
        $lastPrices = PurchaseOrderItem::whereIn('product_id', $approvedProductIds)
            ->orderBy('id', 'desc')
            ->get()
            ->unique('product_id')
            ->pluck('unit_price', 'product_id');

        // Group by supplier and fulfillment type
        $groupedProductIds = [];

        foreach ($productTotals as $productId => $totalQty) {
            if ($totalQty <= 0) {
                continue;
            }

            $supplierId = isset($suppliers[$productId]) ? (int) $suppliers[$productId] : null;

            if (! $supplierId && $defaultPurchaseSupplier) {
                $supplierId = $defaultPurchaseSupplier->id;
            }

            if (! $supplierId) {
                $supplierId = $productSupplierMap[$productId] ?? null;
            }

            if (! $supplierId) {
                $fallbackSupplier = Supplier::where('category', 'own_purchase')->first() ?? Supplier::first();
                if ($fallbackSupplier) {
                    $supplierId = $fallbackSupplier->id;
                }
            }

            $fulfillmentType = $productFulfillmentTypes[$productId] ?? 'warehouse';

            if ($supplierId) {
                $poGroups[$supplierId][$fulfillmentType][$productId] = $totalQty;
                $groupedProductIds[] = $productId;
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

                    $this->shopOrderRevisionService->notifyPurchaseManagers(
                        new PurchaseOrderCreatedNotification($po->loadMissing('supplier'))
                    );
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
                    $product = $productsById->get($productId);
                    if ($product) {
                        $po->items()->create([
                            'product_id' => $productId,
                            'quantity' => $qty,
                            'unit_price' => $lastPrices[$productId] ?? 1.00,
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

        $generatedProductIds = PurchaseOrderItem::query()
            ->whereIn('purchase_order_id', collect($activePoIds))
            ->pluck('product_id')
            ->unique()
            ->values();

        $missingProductIds = collect($groupedProductIds)
            ->diff($generatedProductIds)
            ->values();

        if ($missingProductIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'purchase_orders' => 'Purchase order generation did not include every approved product. Missing product IDs: '.$missingProductIds->implode(', '),
            ]);
        }
    }

    /**
     * Resolve selected products that still have approved quantity but no explicit supplier selection.
     *
     * @param  array<int|string, array<int|string, mixed>>  $quantities
     * @return array<int, int>
     */
    private function resolveProductsMissingSuppliers(
        array $quantities,
        array $suppliers
    ): array {
        $missingProductIds = [];

        foreach ($quantities as $productId => $shopQuantities) {
            $normalizedProductId = (int) $productId;

            $hasApprovedQuantity = collect($shopQuantities)
                ->contains(fn ($quantity) => (float) $quantity > 0);

            if (! $hasApprovedQuantity) {
                continue;
            }

            if (! isset($suppliers[$productId]) || (int) $suppliers[$productId] <= 0) {
                $missingProductIds[] = $normalizedProductId;
            }
        }

        return array_values(array_unique($missingProductIds));
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
            return redirect()->route(
                $request->user()->hasRole('shop') ? 'shop-owner.deliveries.show' : 'requisitions.show',
                $orderNumber
            )
                ->with('error', 'This order has not been dispatched/allocated from the warehouse yet.');
        }

        if ($order->is_delivered) {
            return redirect()->route(
                $request->user()->hasRole('shop') ? 'shop-owner.deliveries.show' : 'requisitions.show',
                $orderNumber
            )
                ->with('error', 'This order has already been checked-in and marked as delivered.');
        }

        if ($request->user()->hasRole('shop')) {
            return redirect()->route('shop-owner.deliveries.show', $order->order_number);
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
            'finance_note' => ['nullable', 'string'],
        ]);

        $deliveredQtys = $request->input('delivered_qty', []);
        $cashCollected = (float) $request->input('cash_collected', 0.00);

        DB::transaction(function () use ($order, $deliveredQtys, $cashCollected, $request): void {
            $totalShortageValue = 0.00;
            $expectedDeliveredValue = 0.00;
            $totalApprovedQuantity = 0.00;
            $totalDeliveredQuantity = 0.00;

            foreach ($order->items as $item) {
                $deliveredQty = (float) ($deliveredQtys[$item->id] ?? 0.00);
                $approvedQty = (float) ($item->approved_qty ?? 0.00);

                if ($deliveredQty > $approvedQty) {
                    throw ValidationException::withMessages([
                        "delivered_qty.{$item->id}" => 'Received quantity cannot be more than the approved warehouse quantity.',
                    ]);
                }

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

                $this->stockLedgerService->consumeSortedStockForProduct(
                    $item->product_id,
                    $deliveredQty,
                    (int) $request->user()->id,
                    StockMovementType::Out,
                    "Warehouse delivery out: {$order->order_number}"
                );

                $this->stockLedgerService->consumeSortedStockForProduct(
                    $item->product_id,
                    $shortageQty,
                    (int) $request->user()->id,
                    StockMovementType::Wastage,
                    "Delivery shortage discrepancy: {$order->order_number}"
                );

                $totalShortageValue += $shortageValue;
                $expectedDeliveredValue += $itemExpectedValue;
                $totalApprovedQuantity += $approvedQty;
                $totalDeliveredQuantity += $deliveredQty;
            }

            // cash_discrepancy = Expected Delivered Value - cash_collected
            $cashDiscrepancy = $expectedDeliveredValue - $cashCollected;
            $balanceAmount = max(0.00, $cashDiscrepancy);

            $deliveryStatus = match (true) {
                $totalDeliveredQuantity <= 0.00 => 'delivery_issue',
                $totalDeliveredQuantity < $totalApprovedQuantity => 'partially_delivered',
                default => 'delivered',
            };

            $paymentStatus = match (true) {
                $cashCollected <= 0.00 => 'unpaid',
                $cashCollected + 0.01 < $expectedDeliveredValue => 'partially_paid',
                default => 'paid',
            };

            $order->update([
                'is_delivered' => true,
                'delivered_at' => now(),
                'delivered_by' => $request->user()->id,
                'delivery_status' => $deliveryStatus,
                'delivery_notes' => $request->input('delivery_notes'),
                'cash_collected' => $cashCollected,
                'cash_discrepancy' => $cashDiscrepancy,
                'payment_status' => $paymentStatus,
                'balance_amount' => $balanceAmount,
                'finance_note' => $request->input('finance_note'),
                'total_shortage_value' => $totalShortageValue,
            ]);

            activity()
                ->performedOn($order)
                ->causedBy($request->user())
                ->log('delivered');
        });

        return redirect()->route(
            $request->user()->hasRole('shop') ? 'shop-owner.deliveries.show' : 'requisitions.show',
            $order->order_number
        )
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

    /**
     * @param  array<string, mixed>  $rawItems
     * @return array<int, array{product: Product, quantity: float}>
     */
    private function resolveRequestedProducts(array $rawItems): array
    {
        $requestedQuantities = [];

        foreach ($rawItems as $sku => $quantity) {
            $numericQuantity = (float) $quantity;

            if (! is_string($sku) || $numericQuantity <= 0) {
                continue;
            }

            $requestedQuantities[$sku] = $numericQuantity;
        }

        if ($requestedQuantities === []) {
            return [];
        }

        $productsBySku = Product::query()
            ->whereIn('sku', array_keys($requestedQuantities))
            ->get()
            ->keyBy('sku');

        $resolvedItems = [];

        foreach ($requestedQuantities as $sku => $quantity) {
            /** @var Product|null $product */
            $product = $productsBySku->get($sku);

            if (! $product) {
                continue;
            }

            $resolvedItems[] = [
                'product' => $product,
                'quantity' => $quantity,
            ];
        }

        return $resolvedItems;
    }

    /**
     * @param  array<int, array{product: Product, quantity: float}>  $items
     */
    private function syncShopOrderItems(ShopOrder $order, array $items): void
    {
        $existingItems = $order->items()->get()->keyBy('product_id');
        $incomingProductIds = [];

        foreach ($items as $item) {
            $product = $item['product'];
            $incomingProductIds[] = $product->id;

            /** @var ShopOrderItem|null $existingItem */
            $existingItem = $existingItems->get($product->id);

            if ($existingItem) {
                $pricePayload = $this->lockedPricePayload($order, $product, (float) $item['quantity']);
                $existingItem->update([
                    'requested_qty' => $item['quantity'],
                    'unit' => $product->unit,
                    ...$pricePayload,
                ]);

                continue;
            }

            $pricePayload = $this->lockedPricePayload($order, $product, (float) $item['quantity']);
            ShopOrderItem::create([
                'shop_order_id' => $order->id,
                'product_id' => $product->id,
                'requested_qty' => $item['quantity'],
                'unit' => $product->unit,
                ...$pricePayload,
            ]);
        }

        $order->items()
            ->whereNotIn('product_id', $incomingProductIds)
            ->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function lockedPricePayload(ShopOrder $order, Product $product, float $quantity): array
    {
        $price = $this->priceBoardService->sellingPriceFor($product, $order->shop, ProductGrade::GradeA);

        return [
            'product_grade' => ProductGrade::GradeA->value,
            'locked_price_group_id' => $price['group']->id,
            'locked_selling_price' => $price['price'],
            'locked_price_source' => $price['source'],
            'line_total' => round($quantity * $price['price'], 2),
        ];
    }

    /**
     * @param  Collection<int, ShopOrder>  $orders
     * @param  Collection<int, Product>  $products
     * @return array{matrix: array<int, array<int, array{requested_qty: float, approved_qty: ?float, display_qty: float, fulfillment_type: string, needs_attention: bool, order_state: string, previous_approved_qty: ?float, revision_no: ?int}>>, productFulfillmentTypes: array<int, string>, shopUpdateMeta: array<int, array{has_update_request: bool, update_reason: ?string, order_number: ?string, changed_items_count: int, revision_no: ?int}>, shopPoStatusMeta: array<int, array{status: string, label: string, po_count: int}>}
     */
    private function buildBoardPresentationData($orders, $products): array
    {
        $matrix = [];
        $productFulfillmentTypes = [];
        $shopUpdateMeta = [];
        $shopPoStatusMeta = [];

        foreach ($orders as $order) {
            $changedItemsCount = 0;
            $pendingRevision = $order->relationLoaded('latestPendingRevision') ? $order->latestPendingRevision : null;
            $revisionItems = $pendingRevision?->relationLoaded('items') ? $pendingRevision->items->keyBy('product_id') : collect();

            foreach ($order->items as $item) {
                $approvedQty = $item->approved_qty !== null ? (float) $item->approved_qty : null;
                $requestedQty = (float) $item->requested_qty;
                $revisionItem = $revisionItems->get($item->product_id);
                $baselineQty = $approvedQty;

                if ($baselineQty === null) {
                    $baselineQty = $order->state === 'approved' ? 0.0 : $requestedQty;
                }

                $displayQty = $revisionItem ? (float) $revisionItem->new_requested_qty : $baselineQty;
                $needsAttention = $revisionItem !== null;

                if ($needsAttention) {
                    $changedItemsCount++;
                }

                $matrix[$item->product_id][$order->shop_id] = [
                    'requested_qty' => $requestedQty,
                    'approved_qty' => $approvedQty,
                    'display_qty' => $displayQty,
                    'fulfillment_type' => $item->fulfillment_type ?? 'warehouse',
                    'needs_attention' => $needsAttention,
                    'order_state' => $order->state,
                    'previous_approved_qty' => $approvedQty,
                    'revision_no' => $pendingRevision?->revision_no,
                ];

                if (! isset($productFulfillmentTypes[$item->product_id])) {
                    $productFulfillmentTypes[$item->product_id] = $item->fulfillment_type ?? 'warehouse';
                }
            }

            foreach ($revisionItems as $productId => $revisionItem) {
                if (isset($matrix[$productId][$order->shop_id])) {
                    continue;
                }

                $matrix[$productId][$order->shop_id] = [
                    'requested_qty' => 0.0,
                    'approved_qty' => null,
                    'display_qty' => (float) $revisionItem->new_requested_qty,
                    'fulfillment_type' => $productFulfillmentTypes[$productId] ?? 'warehouse',
                    'needs_attention' => true,
                    'order_state' => $order->state,
                    'previous_approved_qty' => 0.0,
                    'revision_no' => $pendingRevision?->revision_no,
                ];

                $changedItemsCount++;
            }

            $existingShopMeta = $shopUpdateMeta[$order->shop_id] ?? [
                'has_update_request' => false,
                'update_reason' => null,
                'order_number' => null,
                'changed_items_count' => 0,
                'revision_no' => null,
            ];

            if ($order->has_pending_revision && $pendingRevision) {
                $shopUpdateMeta[$order->shop_id] = [
                    'has_update_request' => true,
                    'update_reason' => $pendingRevision->reason ?? $order->update_reason,
                    'order_number' => $order->order_number,
                    'changed_items_count' => max($existingShopMeta['changed_items_count'], $changedItemsCount),
                    'revision_no' => $pendingRevision->revision_no,
                ];
            } else {
                $shopUpdateMeta[$order->shop_id] = $existingShopMeta;
            }

            $shopPoStatusMeta[$order->shop_id] = $this->purchaseOrderStatusMeta($order);
        }

        foreach ($products as $product) {
            if (! isset($productFulfillmentTypes[$product->id])) {
                $productFulfillmentTypes[$product->id] = 'warehouse';
            }
        }

        return [
            'matrix' => $matrix,
            'productFulfillmentTypes' => $productFulfillmentTypes,
            'shopUpdateMeta' => $shopUpdateMeta,
            'shopPoStatusMeta' => $shopPoStatusMeta,
        ];
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  array<int, array<int, array{requested_qty: float, approved_qty: ?float, fulfillment_type: string, needs_attention: bool, order_state: string}>>  $matrix
     * @param  array<int, string>  $productFulfillmentTypes
     * @return Collection<int, Product>
     */
    private function filterBoardProductsForExport(
        Collection $products,
        array $matrix,
        array $productFulfillmentTypes,
        Request $request
    ): Collection {
        $search = strtolower(trim((string) $request->input('search', '')));
        $fulfillment = (string) $request->input('fulfillment', 'all');
        $produce = (string) $request->input('produce', 'all');
        $hasOrders = $request->boolean('has_orders', true);
        $orderedProductIds = collect((array) $request->input('ordered_product_ids', []))
            ->map(static fn ($productId): int => (int) $productId)
            ->filter()
            ->values()
            ->all();

        $filteredProducts = $products->filter(function (Product $product) use ($search, $fulfillment, $produce, $hasOrders, $matrix, $productFulfillmentTypes): bool {
            $productFulfillment = $productFulfillmentTypes[$product->id] ?? 'warehouse';
            if ($fulfillment !== 'all' && $productFulfillment !== $fulfillment) {
                return false;
            }

            if (! $this->matchesProduceFilter($product, $produce)) {
                return false;
            }

            if ($search !== '') {
                $haystacks = [
                    strtolower($product->name),
                    strtolower($product->sku),
                    strtolower((string) optional($product->category)->name),
                ];

                $matchesSearch = collect($haystacks)->contains(
                    static fn (string $value): bool => str_contains($value, $search)
                );

                if (! $matchesSearch) {
                    return false;
                }
            }

            if ($hasOrders && $this->calculateProductRowTotal($matrix[$product->id] ?? []) <= 0) {
                return false;
            }

            return true;
        });

        if ($orderedProductIds === []) {
            return $filteredProducts->values();
        }

        $orderMap = array_flip($orderedProductIds);

        return $filteredProducts
            ->sortBy(static fn (Product $product): int => $orderMap[$product->id] ?? PHP_INT_MAX)
            ->values();
    }

    private function matchesProduceFilter(Product $product, string $produce): bool
    {
        $normalizedProduce = strtolower(trim($produce));
        $categoryName = strtolower(trim((string) optional($product->category)->name));

        return match ($normalizedProduce) {
            'veg' => in_array($categoryName, [
                'supply',
                'veg',
                'hal',
                'leaf',
                'english',
                'kolkata',
                'banana',
                'onion',
                'c',
            ], true),
            'fruit' => in_array($categoryName, ['frut', 'fruit'], true),
            default => true,
        };
    }

    /**
     * @param  array<int, array{requested_qty: float, approved_qty: ?float, display_qty: float, fulfillment_type: string, needs_attention: bool, order_state: string, previous_approved_qty: ?float, revision_no: ?int}>  $productShopMatrix
     */
    private function calculateProductRowTotal(array $productShopMatrix): float
    {
        $rowTotal = 0.0;

        foreach ($productShopMatrix as $qtyData) {
            $rowTotal += $this->resolveMatrixQuantity($qtyData);
        }

        return $rowTotal;
    }

    /**
     * @param  array{requested_qty?: float, approved_qty?: ?float, display_qty?: float}|null  $qtyData
     */
    private function resolveMatrixQuantity(?array $qtyData): float
    {
        if ($qtyData === null) {
            return 0.0;
        }

        if (array_key_exists('display_qty', $qtyData)) {
            return (float) ($qtyData['display_qty'] ?? 0.0);
        }

        if (array_key_exists('approved_qty', $qtyData) && $qtyData['approved_qty'] !== null) {
            return (float) $qtyData['approved_qty'];
        }

        return (float) ($qtyData['requested_qty'] ?? 0.0);
    }

    /**
     * @return array{status: string, label: string, po_count: int}
     */
    private function purchaseOrderStatusMeta(ShopOrder $order): array
    {
        $productIds = $order->items->pluck('product_id')->all();

        if ($productIds === [] && $order->relationLoaded('latestPendingRevision') && $order->latestPendingRevision) {
            $productIds = $order->latestPendingRevision->items->pluck('product_id')->all();
        }

        if ($productIds === []) {
            return [
                'status' => 'none',
                'label' => 'No PO',
                'po_count' => 0,
            ];
        }

        $linkedPos = PurchaseOrder::query()
            ->whereDate('order_date', $order->business_date)
            ->whereHas('items', function ($query) use ($productIds): void {
                $query->whereIn('product_id', $productIds);
            })
            ->get();

        if ($linkedPos->isEmpty()) {
            return [
                'status' => 'none',
                'label' => 'No PO',
                'po_count' => 0,
            ];
        }

        if ($linkedPos->contains(fn (PurchaseOrder $purchaseOrder): bool => $purchaseOrder->goodsReceiveds()->exists())) {
            return [
                'status' => 'grn_locked',
                'label' => 'PO Locked by GRN',
                'po_count' => $linkedPos->count(),
            ];
        }

        return [
            'status' => 'created',
            'label' => 'PO Created',
            'po_count' => $linkedPos->count(),
        ];
    }
}
