<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\ShopOrder\Actions\ResolveDeliveryReviewAction;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Purchasing\ReviewDeliveryDiscrepancyRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPreset;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\LateRequisitionSubmittedNotification;
use App\Notifications\PurchaseOrderCreatedNotification;
use App\Notifications\PurchasingOrderRevisionRequestedNotification;
use App\Notifications\PurchasingOrderSubmittedNotification;
use App\Services\Inventory\StockLedgerService;
use App\Services\Pricing\PriceBoardService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Requisition\ShopOrderChangeRequestRecorder;
use App\Services\Requisition\ShopOrderRevisionService;
use App\Services\ShopInvoices\ShopInvoiceService;
use App\Services\ShopOrders\DeliveryVerificationEligibility;
use App\Support\ShopOwner\ActiveShopResolver;
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
        private readonly PurchaserBusinessDayService $businessDayService,
        private readonly ShopOrderRevisionService $shopOrderRevisionService,
        private readonly ShopOrderChangeRequestRecorder $shopOrderChangeRequestRecorder,
        private readonly ShopInvoiceService $shopInvoiceService,
        private readonly ActiveShopResolver $activeShopResolver,
        private readonly ResolveDeliveryReviewAction $resolveDeliveryReviewAction,
        private readonly DeliveryVerificationEligibility $deliveryVerificationEligibility,
    ) {}

    /**
     * Store a newly created shop requisition.
     *
     * @return JsonResponse|RedirectResponse
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('shop') && $this->activeShopResolver->authorizedShops($user)->isEmpty()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'User is not associated with any shop.'], 400);
            }

            return redirect()->route('shop-owner.orders.create')
                ->withErrors(['items' => 'User is not associated with any shop.'])
                ->withInput();
        }

        $items = $this->resolveRequestedProducts($request->input('items', []), $request->input('item_units', []), $request->input('item_measures', []));

        if ($items === []) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Requisition cannot be empty.'], 400);
            }

            return redirect()->route('shop-owner.orders.create')
                ->withErrors(['items' => 'Requisition cannot be empty.'])
                ->withInput();
        }

        $activeShop = $user->hasRole('shop') ? $this->activeShopResolver->resolve($request) : $user->shop;
        abort_unless($activeShop instanceof Shop, 403, 'User is not associated with any shop.');

        $nowInKolkata = now('Asia/Kolkata');
        $businessDate = $nowInKolkata->copy()->addDay()->toDateString();
        $cutoff = $this->businessDayService->rolloverStartsAt($nowInKolkata);
        $isLate = $nowInKolkata->greaterThan($cutoff);
        $autoApproveOrder = ! $isLate && $this->businessDayService->autoApproveShopOrders();

        try {
            $outcome = DB::transaction(function () use ($activeShop, $user, $items, $businessDate, $isLate, $cutoff, $autoApproveOrder): array {
                $dailyOrderKey = ShopOrder::dailyOrderKey((int) $activeShop->id, $businessDate);
                $existingOrder = $this->existingShopOwnerOrderForDate((int) $activeShop->id, $businessDate, $dailyOrderKey);

                if ($existingOrder) {
                    return $this->resubmitExistingShopOwnerOrder(
                        order: $existingOrder,
                        user: $user,
                        items: $items,
                        isLate: $isLate,
                        cutoff: $cutoff,
                        autoApproveOrder: $autoApproveOrder,
                        dailyOrderKey: $dailyOrderKey,
                    );
                }

                $shopOrder = ShopOrder::create([
                    'shop_id' => $activeShop->id,
                    'order_source' => 'shop_owner',
                    'shop_daily_order_key' => $dailyOrderKey,
                    'business_date' => $businessDate,
                    'state' => $autoApproveOrder ? 'approved' : 'submitted',
                    'is_late' => $isLate,
                    'submitted_at' => now(),
                    'deadline_at' => $cutoff->utc(),
                    'created_by' => $user->id,
                    'reviewed_at' => $autoApproveOrder ? now() : null,
                    'manager_note' => $autoApproveOrder ? PurchaserBusinessDayService::AUTO_APPROVE_MANAGER_NOTE : null,
                ]);

                $this->syncShopOrderItems($shopOrder, $items);

                if ($isLate) {
                    $this->shopOrderChangeRequestRecorder->recordLateSubmission(
                        $shopOrder,
                        $user,
                        'Shop owner submitted this order after cutoff.'
                    );
                }

                if ($autoApproveOrder) {
                    $shopOrder->items()->update([
                        'approved_qty' => DB::raw('requested_qty'),
                        'notes' => PurchaserBusinessDayService::AUTO_APPROVE_MANAGER_NOTE,
                    ]);
                }

                return [
                    'order' => $shopOrder->fresh(['items.product', 'shop']),
                    'notification' => $isLate ? 'late' : ($autoApproveOrder ? null : 'submitted'),
                    'message' => match (true) {
                        $isLate => 'Late order request submitted successfully for manager approval.',
                        $autoApproveOrder => 'Tomorrow order submitted and automatically approved.',
                        default => 'Tomorrow order submitted successfully.',
                    },
                ];
            });
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['error' => collect($exception->errors())->flatten()->first()], 422);
            }

            return redirect()->route('shop-owner.orders.create')
                ->withErrors($exception->errors())
                ->with('error', collect($exception->errors())->flatten()->first())
                ->withInput();
        }

        /** @var ShopOrder $order */
        $order = $outcome['order'];

        if (($outcome['blocked'] ?? false) === true) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $outcome['message']], 422);
            }

            return redirect()->route('shop-owner.orders.show', $order->order_number)
                ->with('error', $outcome['message']);
        }

        if (($outcome['notification'] ?? null) === 'late') {
            $this->shopOrderRevisionService->notifyPurchaseManagers(
                new LateRequisitionSubmittedNotification($order->loadMissing('shop'))
            );
        } elseif (($outcome['notification'] ?? null) === 'submitted') {
            $this->shopOrderRevisionService->notifyPurchaseManagers(
                new PurchasingOrderSubmittedNotification($order->loadMissing('shop'))
            );
        } elseif (($outcome['notification'] ?? null) === 'revision' && isset($outcome['revision'])) {
            $this->shopOrderRevisionService->notifyPurchaseManagers(
                new PurchasingOrderRevisionRequestedNotification($outcome['revision']->loadMissing(['shopOrder.shop', 'items']))
            );
        }

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
            ->with('success', $outcome['message']);
    }

    private function existingShopOwnerOrderForDate(int $shopId, string $businessDate, string $dailyOrderKey): ?ShopOrder
    {
        /** @var ShopOrder|null $keyedOrder */
        $keyedOrder = ShopOrder::query()
            ->where('shop_daily_order_key', $dailyOrderKey)
            ->lockForUpdate()
            ->first();

        if ($keyedOrder) {
            return $keyedOrder;
        }

        /** @var ShopOrder|null $legacyOrder */
        $legacyOrder = ShopOrder::query()
            ->where('shop_id', $shopId)
            ->whereDate('business_date', $businessDate)
            ->where(function ($query): void {
                $query
                    ->where('order_source', 'shop_owner')
                    ->orWhereNull('order_source');
            })
            ->latest('id')
            ->lockForUpdate()
            ->first();

        return $legacyOrder;
    }

    /**
     * @param  array<int, array{product: Product, quantity: float}>  $items
     * @return array{order: ShopOrder, notification?: string|null, message: string, blocked?: bool, revision?: mixed}
     */
    private function resubmitExistingShopOwnerOrder(
        ShopOrder $order,
        User $user,
        array $items,
        bool $isLate,
        Carbon $cutoff,
        bool $autoApproveOrder,
        string $dailyOrderKey,
    ): array {
        $order->loadMissing(['items.product', 'invoice']);

        if ($order->shop_daily_order_key === null) {
            $order->forceFill([
                'order_source' => 'shop_owner',
                'shop_daily_order_key' => $dailyOrderKey,
            ])->save();
        }

        if ($order->isFinanciallyLocked() || $order->is_delivered) {
            return [
                'order' => $order,
                'blocked' => true,
                'message' => 'This order is already locked. Create an adjustment request instead.',
            ];
        }

        if ($order->state === 'approved') {
            $revision = $this->shopOrderRevisionService->createApprovedOrderRevision(
                $order,
                $items,
                $user,
                $isLate
                    ? 'Shop owner resubmitted this approved order after cutoff.'
                    : 'Shop owner resubmitted this approved order.'
            );

            if (! $revision) {
                return [
                    'order' => $order->fresh(['items.product', 'shop']),
                    'notification' => null,
                    'message' => 'This order was already submitted with the same quantities.',
                ];
            }

            $this->shopOrderChangeRequestRecorder->recordApprovedOrderRevision($revision);

            return [
                'order' => $order->fresh(['items.product', 'shop']),
                'revision' => $revision,
                'notification' => 'revision',
                'message' => sprintf(
                    'Your updated order request (Update #%d) has been submitted to the Purchase Manager.',
                    $revision->revision_no
                ),
            ];
        }

        $wasRejected = $order->state === 'rejected';

        if ($isLate) {
            $this->shopOrderChangeRequestRecorder->recordSubmittedOrderUpdate(
                $order,
                $items,
                $user,
                'Shop owner resubmitted this order after cutoff.'
            );
        }

        $this->syncShopOrderItems($order, $items);

        $order->update([
            'state' => $autoApproveOrder ? 'approved' : ($isLate ? 'update_requested' : 'submitted'),
            'is_late' => $isLate,
            'submitted_at' => now(),
            'deadline_at' => $cutoff->utc(),
            'update_reason' => $isLate ? 'Shop owner requested quantity changes after cutoff.' : null,
            'reviewed_by' => $autoApproveOrder ? $user->id : null,
            'reviewed_at' => $autoApproveOrder ? now() : null,
            'manager_note' => $autoApproveOrder ? PurchaserBusinessDayService::AUTO_APPROVE_MANAGER_NOTE : null,
            'has_pending_revision' => false,
        ]);

        if ($autoApproveOrder) {
            $order->items()->update([
                'approved_qty' => DB::raw('requested_qty'),
                'notes' => PurchaserBusinessDayService::AUTO_APPROVE_MANAGER_NOTE,
            ]);
        } else {
            $order->items()->update([
                'approved_qty' => null,
                'notes' => null,
            ]);
        }

        return [
            'order' => $order->fresh(['items.product', 'shop']),
            'notification' => $isLate ? 'late' : ($wasRejected && ! $autoApproveOrder ? 'submitted' : null),
            'message' => match (true) {
                $isLate => 'Late order update submitted successfully for manager approval.',
                $autoApproveOrder => 'Tomorrow order updated and automatically approved.',
                default => 'Tomorrow order updated successfully.',
            },
        ];
    }

    public function createAdminDirectPurchase(Request $request): View
    {
        $this->authorizeAdminDirectPurchase($request);

        return view('admin.accounting.purchasers.direct-purchase', [
            ...$this->directPurchaseFormData($request, route('admin.accounting.purchasers.direct-purchase.store')),
            'directPurchaseAudience' => 'admin',
        ]);
    }

    public function createPurchaserDirectPurchase(Request $request): View
    {
        $this->authorizePurchaserDirectPurchase($request);

        return view('purchasing.purchaser.direct_purchase', [
            ...$this->directPurchaseFormData($request, route('purchaser.add-ons.store')),
            'directPurchaseAudience' => 'purchaser',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function directPurchaseFormData(Request $request, string $formAction): array
    {
        $businessDate = Carbon::parse($request->input('date', $this->businessDayService->operationalDate()->toDateString()));
        $user = $request->user();

        $categoryQuery = Category::with(['products' => function ($query): void {
            $query->where('is_active', true)->with('orderUnits')->ordered();
        }])
            ->where('is_active', true);

        if ($user && $user->hasAssignedCategoryFilter()) {
            $categoryQuery->whereIn('id', $user->assignedCategoryIds());
        }

        $productsByCategory = $categoryQuery
            ->get()
            ->filter(fn (Category $category): bool => $category->products->isNotEmpty());

        $productsByCategory->each(function (Category $category): void {
            $category->products->each(function (Product $product): void {
                $price = $this->priceBoardService->sellingPriceFor($product, null, ProductGrade::GradeA);
                $product->setAttribute('effective_price', $price['price']);
            });
        });

        return [
            'productsByCategory' => $productsByCategory,
            'frequentProducts' => collect(),
            'presets' => ShopPreset::query()->whereRaw('1 = 0')->with('items.product')->get(),
            'yesterdayOrder' => null,
            'tomorrowOrder' => null,
            'tomorrowDate' => $businessDate,
            'cutoffPassed' => false,
            'cutoffLabel' => $this->businessDayService->cutoffLabel(),
            'purchaseOrdersLockedForTomorrow' => false,
            'businessDate' => $businessDate,
            'orderFormAction' => $formAction,
            'orderFormMode' => 'admin-direct-purchase',
            'allowPresetSave' => false,
        ];
    }

    public function storeAdminDirectPurchase(Request $request): RedirectResponse
    {
        $this->authorizeAdminDirectPurchase($request);

        return $this->storeDirectPurchaseDemand(
            request: $request,
            emptyRedirectRoute: 'admin.accounting.purchasers.direct-purchase.create',
            successRedirectRoute: 'purchaser.vendors',
            managerNote: 'Green Leaf Direct Purchase',
            successPrefix: 'Green Leaf Direct Purchase order',
        );
    }

    public function storePurchaserDirectPurchase(Request $request): RedirectResponse
    {
        $this->authorizePurchaserDirectPurchase($request);

        return $this->storeDirectPurchaseDemand(
            request: $request,
            emptyRedirectRoute: 'purchaser.add-ons.create',
            successRedirectRoute: 'purchaser.daily',
            managerNote: 'Purchaser Add-on',
            successPrefix: 'Purchaser add-on order',
        );
    }

    private function storeDirectPurchaseDemand(
        Request $request,
        string $emptyRedirectRoute,
        string $successRedirectRoute,
        string $managerNote,
        string $successPrefix,
    ): RedirectResponse {
        $validated = $request->validate([
            'business_date' => ['required', 'date'],
            'items' => ['required', 'array'],
        ]);

        $items = $this->resolveRequestedProducts($validated['items'], $request->input('item_units', []), $request->input('item_measures', []));

        if ($items === []) {
            return redirect()->route($emptyRedirectRoute, [
                'date' => Carbon::parse($validated['business_date'])->toDateString(),
            ])
                ->withErrors(['items' => 'Direct purchase order cannot be empty.'])
                ->withInput();
        }

        $businessDate = Carbon::parse($validated['business_date']);
        $user = $request->user();

        $order = DB::transaction(function () use ($businessDate, $user, $items, $managerNote): ShopOrder {
            $shopOrder = ShopOrder::query()->create([
                'shop_id' => null,
                'business_date' => $businessDate,
                'order_source' => 'admin_direct_purchase',
                'state' => 'approved',
                'is_late' => false,
                'submitted_at' => now(),
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'created_by' => $user->id,
                'manager_note' => $managerNote,
            ]);

            $this->syncShopOrderItems($shopOrder, $items);

            $shopOrder->items()->update([
                'approved_qty' => DB::raw('requested_qty'),
                'notes' => $managerNote,
            ]);

            return $shopOrder->fresh(['items.product']);
        });

        return redirect()->route($successRedirectRoute, ['date' => $businessDate->toDateString()])
            ->with('success', $successPrefix.' '.$order->order_number.' added to purchaser demand.');
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

        $this->authorizeShopOrderAccess($request, $order);

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

        $this->authorizeShopOrderAccess($request, $order);

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

        $this->authorizeShopOrderAccess($request, $order);

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

        $this->authorizeShopOrderAccess($request, $order);

        $canRequestApprovedUpdate = $order->state === 'approved';

        if ($order->isFinanciallyLocked()) {
            return redirect()->route('shop-owner.orders.show', $orderNumber)
                ->with('error', 'This order is linked to a finalized shop invoice. Create an adjustment request instead of changing the original order.');
        }

        if ((! in_array($order->state, ['submitted', 'update_requested'], true) && ! $canRequestApprovedUpdate) || $order->is_delivered) {
            return redirect()->route('shop-owner.orders.show', $orderNumber)
                ->with('error', 'This order can no longer be modified from the shop owner workflow.');
        }

        $items = $this->resolveRequestedProducts($request->input('items', []), $request->input('item_units', []), $request->input('item_measures', []));
        if ($items === []) {
            return redirect()->route('shop-owner.orders.create')
                ->withErrors(['items' => 'Updated order cannot be empty.']);
        }

        $reason = trim((string) $request->input('reason', ''));

        if ($order->state === 'approved') {
            try {
                $revision = DB::transaction(function () use ($order, $items, $reason, $request) {
                    $revision = $this->shopOrderRevisionService->createApprovedOrderRevision(
                        $order,
                        $items,
                        $request->user(),
                        $reason
                    );

                    if ($revision) {
                        $this->shopOrderChangeRequestRecorder->recordApprovedOrderRevision($revision);
                    }

                    return $revision;
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

        DB::transaction(function () use ($order, $items, $reason, $request): void {
            $this->shopOrderChangeRequestRecorder->recordSubmittedOrderUpdate($order, $items, $request->user(), $reason);
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

        $this->authorizeShopOrderAccess($request, $order);

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

        $this->authorizeShopOrderAccess($request, $order);

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
        $this->shopOrderChangeRequestRecorder->markLatestPending(
            $order,
            'late_submission',
            'approved',
            $request->user(),
            'Late requisition request accepted.'
        );

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
            $this->shopOrderChangeRequestRecorder->markLatestPending(
                $order,
                'late_submission',
                'rejected',
                $request->user(),
                $managerNote
            );
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
                    $this->shopOrderChangeRequestRecorder->markRevisionRequest(
                        $revision,
                        'blocked',
                        $request->user(),
                        $managerNote
                    );

                    throw ValidationException::withMessages([
                        'purchase_orders' => 'This revision cannot be applied because the linked purchasing or shop invoice workflow is already locked.',
                    ]);
                }

                if ($revision) {
                    $this->shopOrderChangeRequestRecorder->markRevisionRequest(
                        $revision,
                        $revision->status === 'applied' ? 'approved' : $revision->status,
                        $request->user(),
                        $managerNote
                    );
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
                $this->shopOrderChangeRequestRecorder->markRevisionRequest(
                    $revision,
                    'rejected',
                    $request->user(),
                    $managerNote
                );
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

        $date = $request->input('date', $this->businessDayService->operationalDate()->toDateString());

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

        $date = $request->input('date', $this->businessDayService->operationalDate()->toDateString());

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
                        'deadline_at' => $this->businessDayService->rolloverStartsAt(Carbon::parse($date)->subDay()),
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

        $date = $request->input('date', $this->businessDayService->operationalDate()->toDateString());

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
        $autoApprovedOrders = $orders
            ->filter(fn (ShopOrder $order): bool => $order->manager_note === PurchaserBusinessDayService::AUTO_APPROVE_MANAGER_NOTE)
            ->loadMissing(['shop', 'items.product'])
            ->sortBy(fn (ShopOrder $order): string => (string) ($order->shop?->name ?? $order->order_number))
            ->values();
        $autoApproveShopOrdersEnabled = $this->businessDayService->autoApproveShopOrders();
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
            'shopPoStatusMeta',
            'autoApprovedOrders',
            'autoApproveShopOrdersEnabled'
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
                        ->with('error', 'This revision cannot be applied because the linked purchasing or shop invoice workflow is already locked.');
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
                        'deadline_at' => $this->businessDayService->rolloverStartsAt(Carbon::parse($date)->subDay()),
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

        $date = $request->input('date', $this->businessDayService->operationalDate()->toDateString());
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

        $date = $request->input('date', $this->businessDayService->operationalDate()->toDateString());
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

        $date = $request->input('date', $this->businessDayService->operationalDate()->toDateString());
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

        $date = $request->input('date', $this->businessDayService->operationalDate()->toDateString());
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
            ->with(['items' => fn ($query) => $query->where('sorting_status', 'loaded'), 'items.product', 'shop'])
            ->firstOrFail();

        // Access control: Shop Owner can only see their own shop orders
        $this->authorizeShopOrderAccess($request, $order);

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
            ->with(['items' => fn ($query) => $query->where('sorting_status', 'loaded'), 'items.product'])
            ->firstOrFail();

        // Access control: Shop Owner can only see their own shop orders
        $this->authorizeShopOrderAccess($request, $order);

        if (! $order->is_allocation_completed) {
            return redirect()->route('requisitions.show', $orderNumber)
                ->with('error', 'This order has not been dispatched/allocated from the warehouse yet.');
        }

        if ($order->is_delivered) {
            return redirect()->route('requisitions.show', $orderNumber)
                ->with('error', 'This order has already been checked-in and marked as delivered.');
        }

        $this->ensureDeliveryInvoiceExists($order, (int) $request->user()->id);
        $order->load(['invoice.items.product', 'invoice.shop.priceGroup']);

        $deliveryEligibility = $this->deliveryVerificationEligibility->forOrder($order);

        if (! $deliveryEligibility['allowed']) {
            return redirect()->route(
                $request->user()->hasRole('shop') ? 'shop-owner.deliveries.show' : 'requisitions.show',
                $orderNumber
            )->with('error', $deliveryEligibility['message']);
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

        $hasDiscrepancy = false;
        foreach ($order->items as $item) {
            $deliveredQty = (float) ($deliveredQtys[$item->id] ?? 0.00);
            $expectedQty = $item->loaded_qty !== null ? (float) $item->loaded_qty : (float) ($item->approved_qty ?? 0.00);

            if (abs($deliveredQty - $expectedQty) > 0.001) {
                $hasDiscrepancy = true;
            }
        }

        foreach ($order->items as $item) {
            $item->update([
                'unit_cost' => $this->resolveProductUnitCost(
                    $item->product_id,
                    $order->business_date->format('Y-m-d')
                ),
            ]);
        }

        $order = $this->resolveDeliveryReviewAction->submit(
            $order,
            array_map(static fn ($value): float => (float) $value, $deliveredQtys),
            (int) $request->user()->id,
            $request->input('delivery_notes'),
        );

        $reportedFinalValue = $order->items->sum(function (ShopOrderItem $item): float {
            return round((float) ($item->shop_reported_received_qty ?? 0) * (float) ($item->locked_selling_price ?? 0), 2);
        });
        $reportedShortageValue = $order->items->sum(function (ShopOrderItem $item): float {
            return round((float) ($item->shop_reported_missing_qty ?? 0) * (float) ($item->unit_cost ?? 0), 2);
        });
        $cashDiscrepancy = round($reportedFinalValue - $cashCollected, 2);
        $reportedBalance = round(max(0, $cashDiscrepancy), 2);

        $order->update([
            'cash_collected' => $cashCollected,
            'cash_discrepancy' => $cashDiscrepancy,
            'finance_note' => $financeNote,
            'balance_amount' => $reportedBalance,
            'total_shortage_value' => $reportedShortageValue,
            'payment_status' => match (true) {
                $cashCollected <= 0.0 => 'unpaid',
                $reportedBalance > 0.0 => 'partial',
                default => 'paid',
            },
        ]);

        $message = $hasDiscrepancy
            ? 'Delivery check-in submitted. Admin review is required before the invoice is updated.'
            : 'Delivery check-in submitted. Admin approval is still required before the invoice is updated.';

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

        $order = $this->resolveDeliveryReviewAction->approve(
            $order,
            $request->validated('approved_delivered_qty', []),
            $request->validated('item_review_notes', []),
            $request->validated('item_inventory_actions', []),
            $request->validated('delivery_discrepancy_types', []),
            $request->validated('delivery_discrepancy_notes', []),
            (int) $request->user()->id,
            $request->validated('review_note')
        );

        return redirect()->route(
            $this->deliveryReviewRedirectRoute($request),
            $this->deliveryReviewRedirectParameter($request, $order)
        )
            ->with('success', 'Delivery review approved and invoice totals were recalculated.');
    }

    public function rejectDeliveryDiscrepancy(ReviewDeliveryDiscrepancyRequest $request, string $orderNumber): RedirectResponse
    {
        $user = $request->user();
        $canApprove = $user->hasRole('purchase') || $user->can('purchasing.order.approve') || $user->hasRole('admin');
        abort_unless($canApprove, 403, 'Unauthorized to reject delivery discrepancies.');

        $order = ShopOrder::where('order_number', $orderNumber)
            ->with(['items', 'invoice.items'])
            ->firstOrFail();

        $order = $this->resolveDeliveryReviewAction->reject(
            $order,
            (int) $request->user()->id,
            $request->validated('review_note')
        );

        return redirect()->route(
            $this->deliveryReviewRedirectRoute($request),
            $this->deliveryReviewRedirectParameter($request, $order)
        )->with('success', 'Delivery review rejected. Shop owner can submit delivery check-in again.');
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
    private function resolveRequestedProducts(array $rawItems, array $rawUnits = [], array $rawMeasures = []): array
    {
        $requestedQuantities = [];
        $lineMeta = [];

        foreach ($rawItems as $lineKey => $quantity) {
            if (! is_string($lineKey) && ! is_int($lineKey)) {
                continue;
            }

            $normalizedLineKey = (string) $lineKey;
            [$normalizedSku, $embeddedMeasure] = array_pad(explode('|', $normalizedLineKey, 2), 2, null);
            $normalizedSku = (string) $normalizedSku;
            $numericQuantity = (float) $quantity;

            if ($numericQuantity <= 0) {
                continue;
            }

            $requestedQuantities[$normalizedLineKey] = $numericQuantity;
            $lineMeta[$normalizedLineKey] = [
                'sku' => $normalizedSku,
                'measure' => filled($rawMeasures[$normalizedLineKey] ?? null)
                    ? (string) $rawMeasures[$normalizedLineKey]
                    : ($embeddedMeasure ?: null),
            ];
        }

        if ($requestedQuantities === []) {
            return [];
        }

        $productsBySku = Product::query()
            ->with('orderUnits')
            ->whereIn('sku', collect($lineMeta)->pluck('sku')->unique()->all())
            ->get()
            ->keyBy('sku');

        $resolvedItems = [];

        foreach ($requestedQuantities as $lineKey => $quantity) {
            $sku = $lineMeta[$lineKey]['sku'];
            /** @var Product|null $product */
            $product = $productsBySku->get($sku);

            if (! $product) {
                continue;
            }

            $selectedMeasure = $this->selectedProductUnitForLine(
                product: $product,
                measureUuid: $lineMeta[$lineKey]['measure'],
                requestedUnit: strtolower(trim((string) ($rawUnits[$lineKey] ?? $rawUnits[$sku] ?? $product->unit))),
            );

            $requestedUnit = strtolower((string) ($selectedMeasure?->unit ?? $product->unit));
            $requestedUnitLabel = $selectedMeasure?->label ?: strtoupper($requestedUnit);
            $conversionToBase = $selectedMeasure
                ? ($selectedMeasure->conversion_to_base !== null ? (float) $selectedMeasure->conversion_to_base : null)
                : $product->conversionToBaseForUnit($requestedUnit);
            $baseQuantity = $conversionToBase !== null ? round($quantity * $conversionToBase, 2) : $quantity;

            $resolvedItems[] = [
                'product' => $product,
                'line_key' => $lineKey,
                'requested_product_unit_id' => $selectedMeasure?->id,
                'quantity' => $baseQuantity,
                'unit' => $conversionToBase !== null ? $product->unit : $requestedUnit,
                'requested_unit' => $requestedUnit,
                'requested_unit_label' => $requestedUnitLabel,
                'requested_unit_quantity' => $quantity,
                'requested_unit_conversion_to_base' => $conversionToBase,
            ];
        }

        return collect($resolvedItems)
            ->groupBy(fn (array $item): string => $this->resolvedOrderItemKey($item))
            ->map(function (Collection $items): array {
                $first = $items->first();
                $first['quantity'] = round((float) $items->sum('quantity'), 2);
                $first['requested_unit_quantity'] = round((float) $items->sum('requested_unit_quantity'), 2);

                return $first;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{product: Product, quantity: float}>  $items
     */
    private function syncShopOrderItems(ShopOrder $order, array $items): void
    {
        $existingItems = $order->items()->get()->keyBy(fn (ShopOrderItem $item): string => $this->shopOrderItemKey($item));
        $incomingKeys = [];

        foreach ($items as $item) {
            $product = $item['product'];
            $incomingKey = $this->resolvedOrderItemKey($item);
            $incomingKeys[] = $incomingKey;

            /** @var ShopOrderItem|null $existingItem */
            $existingItem = $existingItems->get($incomingKey);

            if ($existingItem) {
                $pricePayload = $this->lockedPricePayload($order, $product, (float) $item['quantity']);
                $existingItem->update([
                    'requested_qty' => $item['quantity'],
                    'unit' => $item['unit'] ?? $product->unit,
                    'requested_product_unit_id' => $item['requested_product_unit_id'] ?? null,
                    'requested_unit' => $item['requested_unit'] ?? $product->unit,
                    'requested_unit_label' => $item['requested_unit_label'] ?? strtoupper((string) ($item['requested_unit'] ?? $product->unit)),
                    'requested_unit_quantity' => $item['requested_unit_quantity'] ?? $item['quantity'],
                    'requested_unit_conversion_to_base' => $item['requested_unit_conversion_to_base'] ?? null,
                    ...$pricePayload,
                ]);

                continue;
            }

            $pricePayload = $this->lockedPricePayload($order, $product, (float) $item['quantity']);
            ShopOrderItem::create([
                'shop_order_id' => $order->id,
                'product_id' => $product->id,
                'requested_qty' => $item['quantity'],
                'unit' => $item['unit'] ?? $product->unit,
                'requested_product_unit_id' => $item['requested_product_unit_id'] ?? null,
                'requested_unit' => $item['requested_unit'] ?? $product->unit,
                'requested_unit_label' => $item['requested_unit_label'] ?? strtoupper((string) ($item['requested_unit'] ?? $product->unit)),
                'requested_unit_quantity' => $item['requested_unit_quantity'] ?? $item['quantity'],
                'requested_unit_conversion_to_base' => $item['requested_unit_conversion_to_base'] ?? null,
                ...$pricePayload,
            ]);
        }

        $itemsToDelete = $order->items()->get()
            ->reject(fn (ShopOrderItem $item): bool => in_array($this->shopOrderItemKey($item), $incomingKeys, true))
            ->pluck('id')
            ->all();

        if ($itemsToDelete !== []) {
            $order->items()->whereIn('id', $itemsToDelete)->delete();
        }
    }

    private function selectedProductUnitForLine(Product $product, ?string $measureUuid, string $requestedUnit): ?ProductUnit
    {
        $orderableUnits = $product->orderUnits->where('is_orderable', true);

        if (filled($measureUuid)) {
            /** @var ProductUnit|null $selected */
            $selected = $orderableUnits->firstWhere('public_uuid', $measureUuid);
            if ($selected) {
                return $selected;
            }
        }

        /** @var ProductUnit|null $selected */
        $selected = $orderableUnits->first(fn (ProductUnit $unit): bool => strtolower((string) $unit->unit) === $requestedUnit);

        return $selected;
    }

    private function resolvedOrderItemKey(array $item): string
    {
        $product = $item['product'];
        $measureId = $item['requested_product_unit_id'] ?? null;

        return $product->id.'|'.($measureId ?: ($item['requested_unit'] ?? $product->unit));
    }

    private function shopOrderItemKey(ShopOrderItem $item): string
    {
        return $item->product_id.'|'.($item->requested_product_unit_id ?: ($item->requested_unit ?? $item->unit));
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

    private function authorizeAdminDirectPurchase(Request $request): void
    {
        $user = $request->user();

        abort_unless($user?->hasRole('admin') && $user->hasRole('purchaser'), 403, 'Unauthorized access.');
    }

    private function authorizePurchaserDirectPurchase(Request $request): void
    {
        abort_unless($request->user()?->hasRole('purchaser'), 403, 'Unauthorized access.');
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

    private function authorizeShopOrderAccess(Request $request, ShopOrder $order): void
    {
        if (! $request->user()->hasRole('shop')) {
            return;
        }

        $activeShop = $this->activeShopResolver->resolve($request);

        abort_unless($order->shop_id === $activeShop->id, 403, 'Unauthorized access to shop order.');
    }

    private function ensureDeliveryInvoiceExists(ShopOrder $order, int $userId): void
    {
        if ($order->invoice || $order->delivery_status !== 'in_transit' || ! $order->is_allocation_completed) {
            return;
        }

        try {
            $this->shopInvoiceService->synchronizeOrderInvoice($order, $userId);
            $order->unsetRelation('invoice');
        } catch (ValidationException) {
            return;
        }
    }
}
