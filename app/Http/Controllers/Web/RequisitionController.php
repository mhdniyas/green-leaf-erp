<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\DTOs\Inventory\WastageEntryData;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Purchasing\ReviewDeliveryDiscrepancyRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\LateRequisitionSubmittedNotification;
use App\Notifications\PurchaseOrderCreatedNotification;
use App\Notifications\PurchasingOrderRevisionRequestedNotification;
use App\Notifications\PurchasingOrderSubmittedNotification;
use App\Services\Inventory\StockLedgerService;
use App\Services\Inventory\WastageService;
use App\Services\Pricing\PriceBoardService;
use App\Services\Requisition\ShopOrderRevisionService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
        private readonly ShopInvoiceService $shopInvoiceService,
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
        $isLate = now()->greaterThan($cutoff);

        $order = DB::transaction(function () use ($user, $items, $businessDate, $isLate) {
            // Delete any existing draft/submitted order for tomorrow
            ShopOrder::where('shop_id', $user->shop_id)
                ->where('business_date', $businessDate)
                ->delete();

            $shopOrder = ShopOrder::create([
                'shop_id' => $user->shop_id,
                'business_date' => $businessDate,
                'state' => 'submitted',
                'is_late' => $isLate,
                'submitted_at' => now(),
                'deadline_at' => Carbon::today()->setTime(21, 30, 0),
                'created_by' => $user->id,
            ]);

            $this->syncShopOrderItems($shopOrder, $items);

            return $shopOrder;
        });

        if ($isLate) {
            $this->shopOrderRevisionService->notifyPurchaseManagers(
                new LateRequisitionSubmittedNotification($order->loadMissing('shop'))
            );
        } else {
            $this->shopOrderRevisionService->notifyPurchaseManagers(
                new PurchasingOrderSubmittedNotification($order->loadMissing('shop'))
            );
        }

        $successMessage = $isLate
            ? 'Late order request submitted successfully for manager approval.'
            : 'Tomorrow order submitted successfully.';

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
            ->with('success', $successMessage);
    }

    /**
     * Display the specified requisition details.
     */
    public function show(Request $request, string $orderNumber): View|RedirectResponse
    {
        $order = ShopOrder::where('order_number', $orderNumber)
            ->with(['items.product', 'shop', 'creator', 'reviewedBy', 'latestResolvedRevision.items', 'latestResolvedRevision.reviewedBy'])
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
        $managerNote = $this->normalizedManagerNote((string) $request->input('manager_note', ''));
        if ($action === 'reject') {
            $approvedQtys = $request->input('approved_qty', []);

            DB::transaction(function () use ($order, $approvedQtys, $managerNote, $request): void {
                $this->applyRejectedReviewOutcome($order, $approvedQtys, $managerNote, $request->user()->id);
            });

            return redirect()->route('requisitions.show', $order->order_number)
                ->with('success', 'Requisition rejected successfully.');
        }

        // Action is approve/update details
        $approvedQtys = $request->input('approved_qty', []);
        $fulfillmentTypes = $request->input('fulfillment_types', []);

        DB::transaction(function () use ($order, $approvedQtys, $fulfillmentTypes, $managerNote, $request) {
            foreach ($order->items as $item) {
                // If an approved quantity is supplied, use it; otherwise default to requested quantity
                $qty = max(0.0, (float) ($approvedQtys[$item->id] ?? $item->requested_qty));
                $type = $fulfillmentTypes[$item->id] ?? $item->fulfillment_type ?? 'warehouse';
                $item->update([
                    'approved_qty' => $qty,
                    'fulfillment_type' => $type,
                    'notes' => null,
                ]);
            }
            $order->update([
                'state' => 'approved',
                'is_late' => false,
                'update_reason' => null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'manager_note' => $managerNote,
            ]);

            // No longer sync Purchase Orders here; that is handled by the purchaser dashboard
        });

        return redirect()->route('requisitions.show', $order->order_number)
            ->with('success', 'Requisition approved and quantities updated successfully.');
    }

    public function acceptLateRequisition(Request $request, string $orderNumber): RedirectResponse
    {
        if ($request->user()->hasRole('shop') || (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve'))) {
            abort(403, 'Unauthorized access.');
        }

        $order = ShopOrder::where('order_number', $orderNumber)->firstOrFail();
        $order->update([
            'is_late' => false,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Late requisition request accepted.');
    }

    public function rejectLateRequisition(Request $request, string $orderNumber): RedirectResponse
    {
        if ($request->user()->hasRole('shop') || (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve'))) {
            abort(403, 'Unauthorized access.');
        }

        $order = ShopOrder::where('order_number', $orderNumber)->firstOrFail();
        $approvedQtys = $request->input('approved_qty', []);
        $managerNote = $this->normalizedManagerNote((string) $request->input('manager_note', ''));

        DB::transaction(function () use ($order, $approvedQtys, $managerNote, $request): void {
            $this->applyRejectedReviewOutcome($order, $approvedQtys, $managerNote, $request->user()->id);
        });

        return redirect()->back()->with('success', 'Late requisition request rejected.');
    }

    public function approveUpdate(Request $request, string $orderNumber): RedirectResponse
    {
        if ($request->user()->hasRole('shop') || (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve'))) {
            abort(403, 'Unauthorized access.');
        }

        $order = ShopOrder::where('order_number', $orderNumber)->firstOrFail();

        $approvedQtys = $request->input('approved_qty', []);
        $fulfillmentTypes = $request->input('fulfillment_types', []);
        $suppliers = $request->input('suppliers', []);
        $managerNote = $this->normalizedManagerNote((string) $request->input('manager_note', ''));

        try {
            DB::transaction(function () use ($order, $approvedQtys, $fulfillmentTypes, $suppliers, $managerNote, $request): void {
                $revision = $this->shopOrderRevisionService->applyPendingRevision(
                    $order,
                    $request->user(),
                    $approvedQtys,
                    $fulfillmentTypes,
                    $suppliers,
                    $managerNote
                );

                if ($revision && $revision->status === 'blocked') {
                    throw ValidationException::withMessages([
                        'purchase_orders' => 'This revision cannot be applied because goods receipt has already started for the linked purchase order.',
                    ]);
                }

                $order->update([
                    'update_reason' => null,
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                ]);
            });
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Order update request approved and applied successfully.');
    }

    public function rejectUpdate(Request $request, string $orderNumber): RedirectResponse
    {
        if ($request->user()->hasRole('shop') || (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve'))) {
            abort(403, 'Unauthorized access.');
        }

        $order = ShopOrder::where('order_number', $orderNumber)->firstOrFail();
        $managerNote = $this->normalizedManagerNote((string) $request->input('manager_note', ''));

        DB::transaction(function () use ($order, $request, $managerNote): void {
            $revision = $order->latestPendingRevision()->first();
            if ($revision) {
                $revision->update([
                    'status' => 'rejected',
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                    'manager_note' => $managerNote,
                ]);
            }
            $order->update([
                'state' => 'approved',
                'update_reason' => $managerNote ?? 'Purchase manager rejected the requested update.',
                'has_pending_revision' => false,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'manager_note' => $managerNote,
            ]);
        });

        return redirect()->back()->with('success', 'Order update request rejected successfully.');
    }

    public function approveAllForDate(Request $request): RedirectResponse
    {
        if ($request->user()->hasRole('shop') || (! $request->user()->hasRole('purchase') && ! $request->user()->can('purchasing.order.approve'))) {
            abort(403, 'Unauthorized access.');
        }

        $date = $request->input('date', Carbon::tomorrow()->format('Y-m-d'));

        $orders = ShopOrder::query()
            ->whereDate('business_date', $date)
            ->where('is_late', false)
            ->whereIn('state', ['submitted', 'update_requested'])
            ->where('has_pending_revision', false)
            ->with('items')
            ->get();

        DB::transaction(function () use ($orders, $request): void {
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    $item->update([
                        'approved_qty' => (float) $item->requested_qty,
                        'notes' => null,
                    ]);
                }

                $order->update([
                    'state' => 'approved',
                    'is_late' => false,
                    'update_reason' => null,
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                    'manager_note' => null,
                ]);
            }
        });

        return redirect()->route('requisitions.board', ['date' => $date])
            ->with('success', "Approved {$orders->count()} shop orders for {$date}.");
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
        $products = Product::with('category')->where('is_active', true)->ordered()->get();

        // Load all shop orders for the selected date
        $orders = ShopOrder::whereDate('business_date', $date)
            ->where('is_late', false)
            ->with([
                'items.product',
                'latestPendingRevision.items.product',
                'latestResolvedRevision.items.product',
                'latestResolvedRevision.reviewedBy',
                'revisions.items.product',
                'revisions.reviewedBy',
                'shop',
                'reviewedBy',
            ])
            ->get();

        // Load all late pending requests for the selected date
        $lateOrders = ShopOrder::whereDate('business_date', $date)
            ->where('is_late', true)
            ->whereIn('state', ['submitted', 'update_requested'])
            ->with(['shop', 'items.product', 'reviewedBy', 'revisions.items.product', 'revisions.reviewedBy'])
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
            'boardFullyApproved',
            'lateOrders',
            'orders'
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
                            'reviewed_by' => $request->user()->id,
                            'reviewed_at' => now(),
                            'manager_note' => null,
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
            ->ordered()
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
            $suppliers = [];

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
                ->with('success', 'Pending approved-order updates were applied.');
        }

        // quantities is a 2D array: [product_id][shop_id] => value
        $quantities = $request->input('quantities', []);
        $fulfillmentTypes = $request->input('fulfillment_types', []);

        DB::transaction(function () use ($date, $quantities, $fulfillmentTypes, $request): void {
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
                            'reviewed_by' => $request->user()->id,
                            'reviewed_at' => now(),
                            'manager_note' => null,
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
            // Automatically sync/generate Purchase Orders for the date based on allocations
            $this->syncPurchaseOrdersForDate($date);
        });

        return redirect()->route('requisitions.approved_board', ['date' => $date])
            ->with('success', 'Approved requisitions saved and Purchase Orders generated successfully.');
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
        $products = Product::with('category')->where('is_active', true)->ordered()->get();
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
        $products = Product::with('category')->where('is_active', true)->ordered()->get();
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
        $products = Product::with('category')->where('is_active', true)->ordered()->get();
        $orders = ShopOrder::whereDate('business_date', $date)
            ->where('is_late', false)
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
        $products = Product::with('category')->where('is_active', true)->ordered()->get();
        $orders = ShopOrder::whereDate('business_date', $date)
            ->where('is_late', false)
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
            'cash_collected' => ['nullable', 'numeric', 'min:0'],
            'finance_note' => ['nullable', 'string'],
            'delivery_notes' => ['nullable', 'string'],
        ]);

        $deliveredQtys = $request->input('delivered_qty', []);
        $cashCollected = (float) $request->input('cash_collected', 0.00);
        $financeNote = $request->input('finance_note');

        // First validate that no delivered_qty is more than loaded_qty (or approved_qty fallback)
        $hasDiscrepancy = false;
        foreach ($order->items as $item) {
            $deliveredQty = (float) ($deliveredQtys[$item->id] ?? 0.00);
            $expectedQty = $item->loaded_qty !== null ? (float) $item->loaded_qty : (float) ($item->approved_qty ?? 0.00);

            if ($deliveredQty > $expectedQty) {
                throw ValidationException::withMessages([
                    "delivered_qty.{$item->id}" => 'Received quantity cannot be more than the loaded/approved warehouse quantity.',
                ]);
            }

            if (abs($deliveredQty - $expectedQty) > 0.001) {
                $hasDiscrepancy = true;
            }
        }

        DB::transaction(function () use ($order, $deliveredQtys, $cashCollected, $financeNote, $request, $hasDiscrepancy): void {
            foreach ($order->items as $item) {
                $deliveredQty = (float) ($deliveredQtys[$item->id] ?? 0.00);
                $expectedQty = $item->loaded_qty !== null ? (float) $item->loaded_qty : (float) ($item->approved_qty ?? 0.00);

                // shortage_qty = expected_qty - delivered_qty (shorted amount)
                $shortageQty = max(0.00, $expectedQty - $deliveredQty);

                // Fetch unit cost
                $unitCost = $this->resolveProductUnitCost(
                    $item->product_id,
                    $order->business_date->format('Y-m-d')
                );

                $item->update([
                    'delivered_qty' => $deliveredQty,
                    'shortage_qty' => $shortageQty,
                    'unit_cost' => $unitCost,
                    'shortage_value' => $shortageQty * $unitCost,
                ]);

                // ONLY consume stock and wastage immediately if there is NO discrepancy
                if (! $hasDiscrepancy) {
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
                }
            }

            $invoice = $this->shopInvoiceService->applyDeliveryCheckin(
                $order,
                $deliveredQtys,
                (int) $request->user()->id,
                $request->input('delivery_notes'),
            );

            $expectedDeliveredValue = (float) ($invoice->subtotal - $invoice->shortage_total);
            $cashDiscrepancy = $expectedDeliveredValue - $cashCollected;

            $invoice->update([
                'paid_amount' => $cashCollected,
            ]);

            $invoice = $this->shopInvoiceService->recalculate($invoice);

            $order->update([
                'cash_collected' => $cashCollected,
                'cash_discrepancy' => $cashDiscrepancy,
                'finance_note' => $financeNote,
                'balance_amount' => $invoice->balance_amount,
                'payment_status' => $invoice->payment_status,
            ]);

            activity()
                ->performedOn($order)
                ->causedBy($request->user())
                ->log($hasDiscrepancy ? 'delivery_checkin_discrepancy_filed' : 'delivered');
        });

        $message = $hasDiscrepancy
            ? 'Delivery check-in submitted with discrepancies. Sent to manager for approval.'
            : 'Delivery checked-in successfully.';

        return redirect()->route(
            $request->user()->hasRole('shop') ? 'shop-owner.deliveries.show' : 'requisitions.show',
            $order->order_number
        )->with('success', $message);
    }

    /**
     * Approve delivery discrepancies and finalize check-in.
     */
    public function approveDeliveryDiscrepancy(ReviewDeliveryDiscrepancyRequest $request, string $orderNumber): RedirectResponse
    {
        $user = $request->user();
        $canApprove = $user->hasRole('purchase') || $user->can('purchasing.order.approve') || $user->hasRole('admin');
        abort_unless($canApprove, 403, 'Unauthorized to approve delivery discrepancies.');

        $order = ShopOrder::where('order_number', $orderNumber)
            ->with(['items', 'invoice'])
            ->firstOrFail();

        if ($order->is_delivered) {
            return redirect()->route('requisitions.show', $orderNumber)
                ->with('error', 'This order has already been marked as delivered.');
        }

        if ($order->delivery_status !== 'pending_approval') {
            return redirect()->route('requisitions.show', $orderNumber)
                ->with('error', 'This order is not pending discrepancy approval.');
        }

        DB::transaction(function () use ($order, $request): void {
            $this->applyApprovedDeliveryAdjustments(
                $order,
                $request->validated('approved_delivered_qty', []),
                $request->validated('item_review_notes', []),
                $request->validated('delivery_discrepancy_types', []),
                $request->validated('delivery_discrepancy_notes', [])
            );

            $order->refresh()->loadMissing(['items', 'invoice.items']);
            $totalDeliveredQuantity = 0.00;
            $totalApprovedQuantity = 0.00;
            $userId = (int) $request->user()->id;

            foreach ($order->items as $item) {
                $deliveredQty = (float) $item->delivered_qty;
                $expectedQty = $item->loaded_qty !== null ? (float) $item->loaded_qty : (float) $item->approved_qty;
                $shortageQty = (float) $item->shortage_qty;
                $discrepancyType = $item->delivery_discrepancy_type;
                if ($discrepancyType === 'none' || ! $discrepancyType) {
                    $discrepancyType = 'wastage';
                }
                $discrepancyNote = $item->delivery_discrepancy_note;

                // Consume stock
                $this->stockLedgerService->consumeSortedStockForProduct(
                    $item->product_id,
                    $deliveredQty,
                    $userId,
                    StockMovementType::Out,
                    "Warehouse delivery out (approved): {$order->order_number}"
                );

                if ($shortageQty > 0.0) {
                    if ($discrepancyType === 'wastage') {
                        // Consume wastage in stock ledger
                        $notes = "Delivery shortage discrepancy (approved): {$order->order_number}";
                        $this->stockLedgerService->consumeSortedStockForProduct(
                            $item->product_id,
                            $shortageQty,
                            $userId,
                            StockMovementType::Wastage,
                            $notes
                        );

                        // Find the movements we just created to record corresponding WastageEntry records
                        $movements = StockMovement::where('product_id', $item->product_id)
                            ->where('type', StockMovementType::Wastage->value)
                            ->where('created_by', $userId)
                            ->where('notes', $notes)
                            ->get();

                        $wastageService = app(WastageService::class);
                        foreach ($movements as $m) {
                            $wastageService->record(new WastageEntryData(
                                productId: $m->product_id,
                                batchId: $m->batch_id,
                                grade: $m->grade instanceof ProductGrade ? $m->grade->value : (string) $m->grade,
                                quantity: (float) $m->quantity,
                                costPerKg: (float) $m->cost_per_unit,
                                reason: WastageReason::TransitDamage,
                                wastageDate: now()->toDateString(),
                                notes: 'Delivery discrepancy wastage: '.($discrepancyNote ?? 'Order delivery discrepancy'),
                            ), $userId);
                        }
                    } else {
                        // Consume as adjustment/other in stock ledger
                        $this->stockLedgerService->consumeSortedStockForProduct(
                            $item->product_id,
                            $shortageQty,
                            $userId,
                            StockMovementType::Adjustment,
                            "Delivery shortage discrepancy other (approved): {$order->order_number}"
                        );
                    }
                }

                $totalDeliveredQuantity += $deliveredQty;
                $totalApprovedQuantity += $expectedQty;
            }

            $deliveryStatus = match (true) {
                $totalDeliveredQuantity <= 0.00 => 'delivery_issue',
                $totalDeliveredQuantity < $totalApprovedQuantity => 'partially_delivered',
                default => 'delivered',
            };

            $this->shopInvoiceService->finalizeDiscrepancy($order, (int) $request->user()->id);

            $order->refresh();
            $order->update([
                'delivery_status' => $deliveryStatus,
                'delivery_notes' => $this->appendReviewNote(
                    $order->delivery_notes,
                    'Discrepancy approved',
                    $request->validated('review_note')
                ),
            ]);

            $order->invoice?->update([
                'delivery_note' => $this->appendReviewNote(
                    $order->invoice->delivery_note,
                    'Discrepancy approved',
                    $request->validated('review_note')
                ),
            ]);

            activity()
                ->performedOn($order)
                ->causedBy($request->user())
                ->log('delivery_discrepancy_approved');
        });

        return redirect()->route(
            $this->deliveryReviewRedirectRoute($request),
            $this->deliveryReviewRedirectParameter($request, $order)
        )
            ->with('success', 'Delivery discrepancies approved and check-in finalized.');
    }

    public function rejectDeliveryDiscrepancy(ReviewDeliveryDiscrepancyRequest $request, string $orderNumber): RedirectResponse
    {
        $user = $request->user();
        $canApprove = $user->hasRole('purchase') || $user->can('purchasing.order.approve') || $user->hasRole('admin');
        abort_unless($canApprove, 403, 'Unauthorized to reject delivery discrepancies.');

        $order = ShopOrder::where('order_number', $orderNumber)
            ->with(['items', 'invoice.items'])
            ->firstOrFail();

        if ($order->is_delivered) {
            return redirect()->route(
                $this->deliveryReviewRedirectRoute($request),
                $this->deliveryReviewRedirectParameter($request, $order)
            )->with('error', 'This order has already been marked as delivered.');
        }

        if ($order->delivery_status !== 'pending_approval') {
            return redirect()->route(
                $this->deliveryReviewRedirectRoute($request),
                $this->deliveryReviewRedirectParameter($request, $order)
            )->with('error', 'This order is not pending discrepancy approval.');
        }

        DB::transaction(function () use ($order, $request): void {
            foreach ($order->items as $item) {
                $item->update([
                    'delivered_qty' => 0,
                    'shortage_qty' => 0,
                    'shortage_value' => 0,
                ]);
            }

            if ($order->invoice) {
                foreach ($order->invoice->items as $invoiceItem) {
                    $invoiceItem->update([
                        'delivered_qty' => 0,
                        'shortage_qty' => 0,
                        'shortage_amount' => 0,
                        'final_line_total' => (float) $invoiceItem->line_subtotal,
                    ]);
                }

                $order->invoice->update([
                    'delivery_status' => 'pending',
                    'status' => 'generated',
                    'delivery_note' => $this->appendReviewNote(
                        $order->invoice->delivery_note,
                        'Discrepancy rejected',
                        $request->validated('review_note')
                    ),
                    'delivery_confirmed_by' => null,
                    'delivery_confirmed_at' => null,
                ]);

                $invoice = $this->shopInvoiceService->recalculate($order->invoice->fresh('items'));

                $order->update([
                    'balance_amount' => $invoice->balance_amount,
                ]);
            }

            $order->update([
                'delivery_status' => 'in_transit',
                'is_delivered' => false,
                'delivered_at' => null,
                'delivered_by' => null,
                'delivery_notes' => $this->appendReviewNote(
                    $order->delivery_notes,
                    'Discrepancy rejected',
                    $request->validated('review_note')
                ),
                'total_shortage_value' => 0,
            ]);

            activity()
                ->performedOn($order)
                ->causedBy($request->user())
                ->log('delivery_discrepancy_rejected');
        });

        return redirect()->route(
            $this->deliveryReviewRedirectRoute($request),
            $this->deliveryReviewRedirectParameter($request, $order)
        )->with('success', 'Delivery discrepancy rejected. Shop owner can submit delivery check-in again.');
    }

    private function appendReviewNote(?string $existing, string $label, ?string $note): string
    {
        $message = trim($label.($note ? ': '.$note : ''));

        return trim(implode("\n", array_filter([
            $existing,
            '['.now()->format('d M Y H:i').'] '.$message,
        ])));
    }

    /**
     * @param  array<int|string, mixed>  $approvedDeliveredQuantities
     * @param  array<int|string, mixed>  $itemReviewNotes
     * @param  array<int|string, string>  $deliveryDiscrepancyTypes
     * @param  array<int|string, string>  $deliveryDiscrepancyNotes
     */
    private function applyApprovedDeliveryAdjustments(
        ShopOrder $order,
        array $approvedDeliveredQuantities,
        array $itemReviewNotes,
        array $deliveryDiscrepancyTypes = [],
        array $deliveryDiscrepancyNotes = []
    ): void {
        $order->loadMissing(['items', 'invoice.items']);
        $invoiceItemsByOrderItemId = collect($order->invoice?->items ?? [])
            ->keyBy(fn ($invoiceItem) => (int) $invoiceItem->shop_order_item_id);

        foreach ($order->items as $item) {
            $expectedQty = $item->loaded_qty !== null ? round((float) $item->loaded_qty, 2) : round((float) $item->approved_qty, 2);
            $deliveredQty = round((float) Arr::get($approvedDeliveredQuantities, $item->id, $item->delivered_qty ?? 0), 2);

            if ($deliveredQty < 0 || $deliveredQty > $expectedQty) {
                $productName = $item->product?->name ?? 'this item';

                throw ValidationException::withMessages([
                    "approved_delivered_qty.{$item->id}" => "Approved delivered quantity for {$productName} must be between 0 and {$expectedQty}.",
                ]);
            }

            $shortageQty = round(max(0, $expectedQty - $deliveredQty), 2);
            $shortageValue = round($shortageQty * (float) $item->unit_cost, 2);
            $itemReviewNote = trim((string) Arr::get($itemReviewNotes, $item->id, ''));

            $item->update([
                'delivered_qty' => $deliveredQty,
                'shortage_qty' => $shortageQty,
                'shortage_value' => $shortageValue,
                'delivery_discrepancy_type' => Arr::get($deliveryDiscrepancyTypes, $item->id, 'none'),
                'delivery_discrepancy_note' => Arr::get($deliveryDiscrepancyNotes, $item->id),
                'notes' => $itemReviewNote !== ''
                    ? $this->appendReviewNote($item->notes, 'Delivery review', $itemReviewNote)
                    : $item->notes,
            ]);

            $invoiceItem = $invoiceItemsByOrderItemId->get((int) $item->id);

            if (! $invoiceItem) {
                continue;
            }

            $shortageAmount = round($shortageQty * (float) $invoiceItem->unit_price, 2);

            $invoiceItem->update([
                'delivered_qty' => $deliveredQty,
                'shortage_qty' => $shortageQty,
                'shortage_amount' => $shortageAmount,
                'final_line_total' => round((float) $invoiceItem->line_subtotal - $shortageAmount, 2),
            ]);
        }
    }

    private function deliveryReviewRedirectRoute(Request $request): string
    {
        return $request->filled('invoice_number')
            ? 'purchasing.shop-invoices.show'
            : 'requisitions.show';
    }

    private function deliveryReviewRedirectParameter(Request $request, ShopOrder $order): string
    {
        return $request->input('invoice_number', $order->order_number);
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
            if (! is_string($sku) && ! is_int($sku)) {
                continue;
            }

            $normalizedSku = (string) $sku;
            $numericQuantity = (float) $quantity;

            if ($numericQuantity <= 0) {
                continue;
            }

            $requestedQuantities[$normalizedSku] = $numericQuantity;
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

    private function normalizedManagerNote(string $managerNote): ?string
    {
        $trimmedManagerNote = trim($managerNote);

        return $trimmedManagerNote !== '' ? $trimmedManagerNote : null;
    }

    private function resolvedApprovedQty(ShopOrderItem $item, mixed $approvedQty): float
    {
        return max(0.0, min((float) $item->requested_qty, (float) $approvedQty));
    }

    private function applyRejectedReviewOutcome(ShopOrder $order, array $approvedQtys, ?string $managerNote, int $reviewerId): void
    {
        foreach ($order->items as $item) {
            $approvedQty = $this->resolvedApprovedQty($item, $approvedQtys[$item->id] ?? 0);
            $rejectedQty = max(0.0, (float) $item->requested_qty - $approvedQty);

            $item->update([
                'approved_qty' => $approvedQty,
                'notes' => $this->rejectionLineNote($item, $approvedQty, $rejectedQty),
            ]);
        }

        $order->update([
            'state' => 'rejected',
            'is_late' => false,
            'update_reason' => $managerNote ?? 'Purchase manager rejected this order after review.',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'manager_note' => $managerNote,
        ]);
    }

    private function rejectionLineNote(ShopOrderItem $item, float $approvedQty, float $rejectedQty): string
    {
        if ($approvedQty <= 0) {
            return sprintf(
                'Rejected by purchase manager. Rejected qty: %s %s.',
                number_format($rejectedQty, 2, '.', ''),
                $item->unit
            );
        }

        return sprintf(
            'Purchase manager kept %s %s and rejected %s %s.',
            number_format($approvedQty, 2, '.', ''),
            $item->unit,
            number_format($rejectedQty, 2, '.', ''),
            $item->unit
        );
    }
}
