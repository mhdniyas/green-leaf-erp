<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\Cashbook\TransactionStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ShopOwner\StoreShopInvoicePaymentRequest;
use App\Http\Requests\Web\ShopOwner\StoreShopOwnerAccountingEntryRequest;
use App\Models\BusinessSetting;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerProductEntry;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\DailyPricePublication;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopAccountingEntry;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopCredit;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPreset;
use App\Models\ShopStaffPayment;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\BalanceCalculator;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\CashFlowResolutionService;
use App\Services\Cashbook\CollectionGroupPostingService;
use App\Services\Cashbook\DailyLedgerService;
use App\Services\Cashbook\InvoiceCashbookProjectionService;
use App\Services\Cashbook\ShopCashbookSalarySectionService;
use App\Services\Cashbook\ShopSettlementService;
use App\Services\Cashbook\StaffPaymentCashbookProjectionService;
use App\Services\Finance\CompanyPayableService;
use App\Services\Finance\OwnedShopAccountingService;
use App\Services\Finance\ShopLoanService;
use App\Services\Pricing\PriceBoardService;
use App\Services\Pricing\SellingPriceResolverService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Purchasing\ShopVendorReportService;
use App\Services\Requisition\MultiGradeOrderGate;
use App\Services\ShopInvoices\ShopFinalBill;
use App\Services\ShopInvoices\ShopFinalBillService;
use App\Services\ShopInvoices\ShopInvoiceService;
use App\Services\ShopOrders\DeliveryPriceReadinessService;
use App\Services\ShopOrders\DeliveryVerificationEligibility;
use App\Support\ShopOwner\ActiveShopResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ShopOwnerController extends Controller
{
    public function __construct(
        private readonly PriceBoardService $priceBoardService,
        private readonly SellingPriceResolverService $sellingPriceResolverService,
        private readonly OwnedShopAccountingService $ownedShopAccountingService,
        private readonly ShopLoanService $shopLoanService,
        private readonly CompanyPayableService $companyPayableService,
        private readonly PurchaserBusinessDayService $businessDayService,
        private readonly ShopInvoiceService $shopInvoiceService,
        private readonly ActiveShopResolver $activeShopResolver,
        private readonly DeliveryPriceReadinessService $deliveryPriceReadinessService,
        private readonly DeliveryVerificationEligibility $deliveryVerificationEligibility,
        private readonly DailyLedgerService $dailyLedgerService,
        private readonly CashbookShopSyncService $cashbookShopSyncService,
        private readonly CollectionGroupPostingService $collectionGroupPostingService,
        private readonly InvoiceCashbookProjectionService $invoiceCashbookProjectionService,
        private readonly StaffPaymentCashbookProjectionService $staffPaymentCashbookProjectionService,
        private readonly ShopSettlementService $shopSettlementService,
        private readonly ShopVendorReportService $vendorReportService,
        private readonly ShopCashbookSalarySectionService $shopCashbookSalarySectionService,
        private readonly MultiGradeOrderGate $multiGradeOrderGate,
    ) {}

    public function dashboard(Request $request): View
    {
        $activeShop = $this->currentShop($request);

        return view('shop-owner.dashboard.index', $this->buildDashboardData($activeShop));
    }

    public function productsIndex(Request $request): View
    {
        $activeShop = $this->currentShop($request);
        $selectedDate = $request->input('date', today()->toDateString());
        $targetBusinessDate = Carbon::parse($selectedDate)->toDateString();
        $search = trim((string) $request->input('search', ''));
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;

        $isPublished = DailyPricePublication::isPublishedForDate($targetBusinessDate);

        $shopGroup = $this->priceBoardService->groupForShop($activeShop);
        $groupName = strtoupper(trim((string) ($shopGroup?->name ?? 'A')));
        if (! in_array($groupName, ['A', 'B', 'C'], true)) {
            $groupName = 'A';
        }

        $sort = (string) $request->input('sort', 'code_asc');
        if (! in_array($sort, ['code_asc', 'price_desc', 'price_asc'], true)) {
            $sort = 'code_asc';
        }

        $productQuery = Product::query()
            ->active()
            ->with([
                'category',
                'sellingPriceCategory',
                'sellingPriceConfig',
                'shopSellingPrices' => fn ($query) => $query->where('shop_id', $activeShop->id),
            ]);

        if ($sort === 'price_desc') {
            $productQuery->orderBy('base_price', 'desc')->orderBy('name', 'asc');
        } elseif ($sort === 'price_asc') {
            $productQuery->orderBy('base_price', 'asc')->orderBy('name', 'asc');
        } else {
            // Default: Code wise (SKU ascending)
            $productQuery->ordered();
        }

        if ($categoryId) {
            $productQuery->where('category_id', $categoryId);
        }

        if ($search !== '') {
            $productQuery->where(function ($query) use ($search): void {
                $query->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        $products = $productQuery->paginate(24)->withQueryString();
        $pageProductIds = $products->getCollection()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $currentApprovals = DailyPriceApproval::query()
            ->whereDate('business_date', $targetBusinessDate)
            ->whereIn('product_id', $pageProductIds)
            ->get()
            ->keyBy('product_id');

        $previousApprovals = DailyPriceApproval::query()
            ->whereDate('business_date', '<', $targetBusinessDate)
            ->whereIn('product_id', $pageProductIds)
            ->where('status', 'approved')
            ->orderByDesc('business_date')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->first());

        $rawSellingPercentage = BusinessSetting::query()
            ->where('key', 'selling_price_default_percentage')
            ->value('value');
        $sellingPricePercentage = ($rawSellingPercentage !== null && is_numeric($rawSellingPercentage) && (float) $rawSellingPercentage >= 0)
            ? (float) $rawSellingPercentage
            : 25.0;
        $resolver = $this->sellingPriceResolverService;

        $products->setCollection(
            $products->getCollection()->map(function (Product $product) use ($currentApprovals, $previousApprovals, $groupName, $activeShop, $targetBusinessDate, $resolver, $sellingPricePercentage): array {
                $currentApproval = $currentApprovals->get($product->id);
                $previousApproval = $previousApprovals->get($product->id);

                $priceKey = 'price_'.strtolower($groupName);

                $candidatePrices = [];
                $priceUnit = $product->unit ?: 'kg';

                if ($currentApproval && (float) ($currentApproval->$priceKey ?? 0) > 0) {
                    $candidatePrices[] = (float) $currentApproval->$priceKey;
                    if ($currentApproval->price_unit) {
                        $priceUnit = $currentApproval->price_unit;
                    }
                }

                if ($previousApproval && (float) ($previousApproval->$priceKey ?? 0) > 0) {
                    $candidatePrices[] = (float) $previousApproval->$priceKey;
                    if ($previousApproval->price_unit) {
                        $priceUnit = $previousApproval->price_unit;
                    }
                }

                $boardPrice = $this->priceBoardService->sellingPriceFor($product, $activeShop);
                if ((float) ($boardPrice['price'] ?? 0) > 0) {
                    $candidatePrices[] = (float) $boardPrice['price'];
                }

                if ((float) ($product->base_price ?? 0) > 0) {
                    $candidatePrices[] = (float) $product->base_price;
                }

                // Pick the max price among all valid shop & approval price sources
                $sellingPrice = $candidatePrices !== [] ? max($candidatePrices) : 0.0;
                $supplyPriceForResolver = $sellingPrice > 0.0 ? $sellingPrice : null;
                $resolution = $resolver->resolve($product, $supplyPriceForResolver, $activeShop, $sellingPricePercentage);
                $minimumMrp = $resolution['retail_price'];

                $priceDate = null;
                if ($currentApproval && (float) ($currentApproval->$priceKey ?? 0) > 0 && $currentApproval->business_date) {
                    $priceDate = Carbon::parse($currentApproval->business_date)->format('d M');
                } elseif ($previousApproval && (float) ($previousApproval->$priceKey ?? 0) > 0 && $previousApproval->business_date) {
                    $priceDate = Carbon::parse($previousApproval->business_date)->format('d M');
                }

                if (! $priceDate) {
                    $priceDate = Carbon::parse($targetBusinessDate)->format('d M');
                }

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'category_name' => $product->category?->name ?? 'General',
                    'unit' => $priceUnit,
                    'image' => $product->image,
                    'selling_price' => $sellingPrice,
                    'minimum_mrp' => $minimumMrp,
                    'price_date' => $priceDate,
                    'group_name' => $groupName,
                ];
            })
        );

        if ($sort === 'price_desc') {
            $sortedItems = $products->getCollection()->sortByDesc('selling_price')->values();
            $products->setCollection($sortedItems);
        } elseif ($sort === 'price_asc') {
            $sortedItems = $products->getCollection()->sortBy('selling_price')->values();
            $products->setCollection($sortedItems);
        }

        return view('shop-owner.products.index', [
            'activeShop' => $activeShop,
            'products' => $products,
            'selectedDate' => $targetBusinessDate,
            'isPublished' => $isPublished,
            'search' => $search,
            'categoryId' => $categoryId,
            'sort' => $sort,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'shopGroup' => $groupName,
        ]);
    }

    public function ordersIndex(Request $request): View
    {
        $activeShop = $this->currentShop($request);
        [$filterStartDate, $filterEndDate] = $this->nullableDateRangeFromRequest($request);

        return view('shop-owner.orders.index', [
            'orders' => $this->shopOrdersQuery($activeShop)
                ->when($filterStartDate, fn ($query) => $query->whereDate('business_date', '>=', $filterStartDate))
                ->when($filterEndDate, fn ($query) => $query->whereDate('business_date', '<=', $filterEndDate))
                ->latest('business_date')
                ->paginate(12, ['*'], 'orders_page')
                ->withQueryString(),
            'tomorrowOrder' => $this->tomorrowOrder($activeShop),
            'filterStartDate' => $filterStartDate,
            'filterEndDate' => $filterEndDate,
        ]);
    }

    public function ordersCreate(Request $request): View
    {
        $activeShop = $this->currentShop($request);

        return view('shop-owner.orders.create', $this->buildOrderFormData($activeShop));
    }

    public function clearTomorrowOrder(Request $request): RedirectResponse
    {
        $activeShop = $this->currentShop($request);
        $businessDate = Carbon::tomorrow()->toDateString();

        $result = DB::transaction(function () use ($activeShop, $businessDate): array {
            $orders = ShopOrder::query()
                ->where('shop_id', $activeShop->id)
                ->whereDate('business_date', $businessDate)
                ->where(function ($query): void {
                    $query
                        ->where('order_source', 'shop_owner')
                        ->orWhereNull('order_source');
                })
                ->where('state', '!=', 'approved')
                ->with('invoice')
                ->latest('id')
                ->lockForUpdate()
                ->get();

            if ($orders->isEmpty()) {
                return ['status' => 'success', 'message' => 'Cart already clear.'];
            }

            $lockedOrder = $orders->first(fn (ShopOrder $order): bool => $order->isFinanciallyLocked() || $order->is_delivered);

            if ($lockedOrder) {
                return ['status' => 'warning', 'message' => 'This order is already locked and cannot be cleared.'];
            }

            $orders->each->delete();

            return ['status' => 'success', 'message' => 'Unapproved order cancelled.'];
        });

        return redirect()
            ->route('shop-owner.orders.create')
            ->with($result['status'], $result['message']);
    }

    public function ordersShow(Request $request, string $orderNumber): View
    {
        $activeShop = $this->currentShop($request);

        return view('shop-owner.orders.show', [
            'order' => $this->shopOrderByNumber($request, $orderNumber),
            'tomorrowOrder' => $this->tomorrowOrder($activeShop),
        ]);
    }

    public function ordersHistory(Request $request): View
    {
        $activeShop = $this->currentShop($request);
        [$filterStartDate, $filterEndDate] = $this->nullableDateRangeFromRequest($request);

        return view('shop-owner.orders.history', [
            'orders' => $this->shopOrdersQuery($activeShop)
                ->when($filterStartDate, fn ($query) => $query->whereDate('business_date', '>=', $filterStartDate))
                ->when($filterEndDate, fn ($query) => $query->whereDate('business_date', '<=', $filterEndDate))
                ->latest('business_date')
                ->paginate(12, ['*'], 'orders_page')
                ->withQueryString(),
            'tomorrowOrder' => $this->tomorrowOrder($activeShop),
            'filterStartDate' => $filterStartDate,
            'filterEndDate' => $filterEndDate,
        ]);
    }

    public function deliveriesIndex(Request $request): View
    {
        $activeShop = $this->currentShop($request);
        [$filterStartDate, $filterEndDate] = $this->nullableDateRangeFromRequest($request);

        $pendingInvoicesTillToday = ShopInvoice::query()
            ->where('shop_id', $activeShop->id)
            ->whereDate('business_date', '<=', today())
            ->with(['creditNotes'])
            ->get();
        $pendingBillsTillToday = app(ShopFinalBillService::class)->calculateMany($pendingInvoicesTillToday);

        $pendingBillTillToday = round((float) $pendingBillsTillToday->sum('remainingBalance'), 2);
        $pendingBillCountTillToday = $pendingBillsTillToday->filter(fn (ShopFinalBill $b): bool => $b->remainingBalance > 0.0001)->count();

        return view('shop-owner.deliveries.index', [
            'deliveries' => $this->shopOrdersQuery($activeShop)
                ->with(['invoice', 'deliveredBy'])
                ->when($filterStartDate, fn ($query) => $query->whereDate('business_date', '>=', $filterStartDate))
                ->when($filterEndDate, fn ($query) => $query->whereDate('business_date', '<=', $filterEndDate))
                ->where(function ($query): void {
                    $query->where('is_allocation_completed', true)
                        ->orWhere('is_delivered', true)
                        ->orWhereHas('invoice');
                })
                ->latest('business_date')
                ->latest('id')
                ->paginate(12, ['*'], 'deliveries_page')
                ->withQueryString(),
            'pendingBillTillToday' => $pendingBillTillToday,
            'pendingBillCountTillToday' => $pendingBillCountTillToday,
            'filterStartDate' => $filterStartDate,
            'filterEndDate' => $filterEndDate,
        ]);
    }

    public function deliveriesShow(Request $request, string $orderNumber): View
    {
        $activeShop = $this->currentShop($request);

        $order = ShopOrder::where('order_number', $orderNumber)
            ->where('shop_id', $activeShop->id)
            ->with([
                'shop.client',
                'shop.priceGroup',
                'invoice.paymentRequests.requestedBy',
                'invoice.paymentRequests.reviewedBy',
                'items',
                'items.product.orderUnits',
                'deliveredBy',
                'invoice.shop',
            ])
            ->firstOrFail();

        $this->ensureDeliveryInvoiceExists($order, (int) $request->user()->id);
        $order->load(['invoice.paymentRequests.requestedBy', 'invoice.paymentRequests.reviewedBy', 'invoice.shop', 'invoice.items.product']);

        return view('shop-owner.deliveries.show', [
            'order' => $order,
            'deliveryPriceReadiness' => $this->deliveryPriceReadinessService->forOrder($order),
            'deliveryEligibility' => $this->deliveryVerificationEligibility->forOrder($order),
        ]);
    }

    public function deliveriesPdf(Request $request, string $orderNumber): View
    {
        $activeShop = $this->currentShop($request);

        $order = ShopOrder::where('order_number', $orderNumber)
            ->where('shop_id', $activeShop->id)
            ->with([
                'shop',
                'items.product',
                'invoice.items.product',
            ])
            ->firstOrFail();

        return view('shop-owner.deliveries.pdf', [
            'order' => $order,
            'companyDetails' => $this->companyDetailsForPdf(),
        ]);
    }

    /**
     * @return array{name: string, address: string|null, phone: string|null, email: string|null}
     */
    private function companyDetailsForPdf(): array
    {
        $settings = BusinessSetting::query()
            ->whereIn('key', [
                'company_name',
                'company_address',
                'company_phone',
                'company_email',
                'business_name',
                'business_address',
                'business_phone',
                'business_email',
            ])
            ->pluck('value', 'key');

        return [
            'name' => $settings->get('company_name') ?: $settings->get('business_name') ?: 'Green Leaf',
            'address' => $settings->get('company_address') ?: $settings->get('business_address'),
            'phone' => $settings->get('company_phone') ?: $settings->get('business_phone'),
            'email' => $settings->get('company_email') ?: $settings->get('business_email'),
        ];
    }

    public function verifyDeliveryItem(Request $request, string $orderNumber, ShopOrderItem $item): JsonResponse
    {
        $activeShop = $this->currentShop($request);
        $validated = $request->validate([
            'received_qty' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $order = ShopOrder::query()
            ->where('order_number', $orderNumber)
            ->where('shop_id', $activeShop->id)
            ->with(['items.product', 'invoice.items.product', 'invoice.shop.priceGroup'])
            ->firstOrFail();

        abort_unless((int) $item->shop_order_id === (int) $order->id, 404);

        if ($item->sorting_status !== 'loaded' || (float) ($item->loaded_qty ?? 0) <= 0) {
            return response()->json([
                'message' => 'This product was not fulfilled in loadout.',
            ], 422);
        }

        $eligibility = $this->deliveryVerificationEligibility->forOrder($order);
        if (! $eligibility['allowed']) {
            return response()->json([
                'message' => $eligibility['message'],
            ], 422);
        }

        $expectedQty = $item->loaded_qty !== null
            ? round((float) $item->loaded_qty, 2)
            : round((float) ($item->approved_qty ?? 0), 2);
        $receivedQty = round((float) $validated['received_qty'], 2);

        if ($expectedQty <= 0) {
            return response()->json([
                'message' => 'This product does not have an approved delivery quantity.',
            ], 422);
        }

        $result = DB::transaction(function () use ($order, $item, $receivedQty, $expectedQty, $validated, $request): array {
            /** @var ShopOrder $lockedOrder */
            $lockedOrder = ShopOrder::query()
                ->with(['items.product', 'invoice'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (
                ! in_array((string) $lockedOrder->delivery_status, ['in_transit', 'ready_for_dispatch'], true)
                || ! in_array($lockedOrder->delivery_review_status, ['not_started', 'correction_requested'], true)
                || ! $lockedOrder->invoice
            ) {
                throw ValidationException::withMessages([
                    'order' => 'This delivery is no longer accepting shop verification.',
                ]);
            }

            /** @var ShopOrderItem $lockedItem */
            $lockedItem = ShopOrderItem::query()
                ->where('shop_order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->findOrFail($item->id);

            $shortQty = round(max(0, $expectedQty - $receivedQty), 2);
            $excessQty = round(max(0, $receivedQty - $expectedQty), 2);
            $lockedItem->update([
                'shop_reported_received_qty' => $receivedQty,
                'shop_reported_missing_qty' => $shortQty,
                'shop_reported_excess_qty' => $excessQty,
                'shop_reported_damaged_qty' => 0,
                'shop_reported_returned_qty' => 0,
                'shop_verified_by' => $request->user()?->id,
                'shop_verified_at' => now(),
                'shop_verification_note' => filled($validated['note'] ?? null) ? trim((string) $validated['note']) : null,
            ]);

            $items = $lockedOrder->items()
                ->where('sorting_status', 'loaded')
                ->where('loaded_qty', '>', 0)
                ->get();
            $verifiedCount = $items->whereNotNull('shop_verified_at')->count();
            $totalCount = $items->count();
            $orderSubmitted = $totalCount > 0 && $verifiedCount === $totalCount;

            if ($orderSubmitted) {
                $lockedOrder->invoice->update([
                    'delivery_status' => 'awaiting_review',
                    'status' => 'delivery_review',
                    'delivery_confirmed_by' => $request->user()?->id,
                    'delivery_confirmed_at' => now(),
                ]);

                $lockedOrder->update([
                    'delivery_status' => 'delivered',
                    'delivery_review_status' => 'pending',
                    'shop_checked_by' => $request->user()?->id,
                    'shop_checked_at' => now(),
                    'admin_reviewed_by' => null,
                    'admin_reviewed_at' => null,
                    'admin_review_note' => null,
                    'is_delivered' => true,
                    'delivered_at' => now(),
                    'delivered_by' => $request->user()?->id,
                ]);

                activity()
                    ->performedOn($lockedOrder)
                    ->causedBy($request->user())
                    ->log('shop_order.delivery_confirmed');
            }

            return [
                'item' => [
                    'id' => $lockedItem->id,
                    'received_qty' => number_format($receivedQty, 2, '.', ''),
                    'short_qty' => number_format($shortQty, 2, '.', ''),
                    'excess_qty' => number_format($excessQty, 2, '.', ''),
                    'status' => $excessQty > 0 ? 'excess' : ($shortQty > 0 ? 'short' : 'submitted'),
                    'status_label' => match (true) {
                        $excessQty > 0 => 'Excess Submitted',
                        $shortQty > 0 => 'Short Submitted',
                        default => 'Submitted',
                    },
                ],
                'progress' => [
                    'verified' => $verifiedCount,
                    'total' => $totalCount,
                    'label' => "{$verifiedCount} / {$totalCount} products submitted",
                ],
                'order_submitted' => $orderSubmitted,
                'order_status_label' => $orderSubmitted ? 'Submitted For Admin Review' : 'Waiting For Remaining Products',
                'message' => $orderSubmitted
                    ? 'All products submitted for admin review.'
                    : 'Product submitted. Continue with the remaining products.',
            ];
        });

        return response()->json($result);
    }

    public function verifyInvoiceChanges(Request $request, string $orderNumber): RedirectResponse
    {
        $activeShop = $this->currentShop($request);

        $order = ShopOrder::query()
            ->where('order_number', $orderNumber)
            ->where('shop_id', $activeShop->id)
            ->with(['invoice'])
            ->firstOrFail();

        $invoice = $order->invoice;
        if (! $invoice) {
            return redirect()->back()->withErrors(['invoice' => 'No invoice found for this order.']);
        }

        activity('shop_invoice')
            ->performedOn($invoice)
            ->causedBy($request->user())
            ->withProperties(['source' => 'shop_changes_verified'])
            ->log('shop_changes_verified');

        return redirect()->back()->with('success', 'Invoice changes verified successfully.');
    }

    public function financeIndex(Request $request): View
    {
        $tab = (string) $request->input('tab', 'invoices');

        return view('shop-owner.finance.index', $this->financeViewData($request, $tab));
    }

    public function paymentsIndex(Request $request): View
    {
        return view('shop-owner.payments.index', $this->paymentsOverviewData($request));
    }

    public function payExpense(Request $request): RedirectResponse
    {
        $activeShop = $this->currentShop($request);

        $validated = $request->validate([
            'entry_setting_id' => ['required', 'integer', 'exists:shop_ledger_entry_settings,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['nullable', 'date_format:Y-m-d'],
            'paid_from_source' => ['required', 'string', 'in:shop_balance,petty_cash'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $setting = ShopLedgerEntrySetting::with('entryType')
            ->where('shop_id', (int) $activeShop->id)
            ->where('id', (int) $validated['entry_setting_id'])
            ->firstOrFail();

        $fundingSource = $validated['paid_from_source'] === 'petty_cash' ? 'petty_cash' : 'shop_cash';
        $businessDate = ! empty($validated['payment_date']) ? $validated['payment_date'] : now()->toDateString();

        DB::transaction(function () use ($activeShop, $setting, $validated, $fundingSource, $businessDate): void {
            $tx = ShopLedgerTransaction::create([
                'shop_id' => $activeShop->id,
                'entry_type_id' => $setting->entry_type_id,
                'entry_type_code' => $setting->entryType?->code,
                'business_date' => $businessDate,
                'amount' => (float) $validated['amount'],
                'direction' => 'expense',
                'funding_source' => $fundingSource,
                'status' => 'posted',
                'notes' => $validated['notes'] ?: 'Payment for '.$setting->displayName(),
                'reference_type' => 'expense_payment',
            ]);

            // Create payment request to track and allocate across individual open expense bills
            $paymentRequest = ShopInvoicePaymentRequest::create([
                'shop_id' => $activeShop->id,
                'requested_by' => auth()->id() ?? User::query()->whereHas('roles', fn ($q) => $q->where('name', 'shop'))->value('id') ?? 1,
                'submission_uuid' => (string) Str::uuid(),
                'request_type' => 'expense_payment',
                'payment_method' => $validated['paid_from_source'] === 'petty_cash' ? 'cash' : 'shop_balance',
                'payment_date' => $businessDate,
                'requested_amount' => (float) $validated['amount'],
                'approved_amount' => (float) $validated['amount'],
                'reconciled_amount' => (float) $validated['amount'],
                'status' => 'approved',
                'reconciliation_status' => 'reconciled',
                'shop_note' => $validated['notes'] ?: 'Payment for '.$setting->displayName(),
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'last_reconciled_at' => now(),
            ]);

            // Allocate payment amount across open individual expense transactions oldest-first
            $openTxs = ShopLedgerTransaction::query()
                ->where('shop_id', $activeShop->id)
                ->where('entry_type_id', $setting->entry_type_id)
                ->where('id', '!=', $tx->id)
                ->whereIn('status', ['posted', 'approved'])
                ->whereNull('voided_at')
                ->where('direction', 'expense')
                ->whereNull('company_account_id')
                ->orderBy('business_date', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            $remainingToAllocate = (float) $validated['amount'];
            foreach ($openTxs as $openTx) {
                if ($remainingToAllocate <= 0.001) {
                    break;
                }

                $alreadyAllocated = (float) ShopPaymentLedgerAllocation::query()
                    ->where('shop_ledger_transaction_id', $openTx->id)
                    ->lockForUpdate()
                    ->sum('amount');

                $txRemaining = max(0.0, (float) $openTx->amount - $alreadyAllocated);
                if ($txRemaining <= 0.001) {
                    continue;
                }

                $allocAmt = min($remainingToAllocate, $txRemaining);

                ShopPaymentLedgerAllocation::create([
                    'payment_request_id' => $paymentRequest->id,
                    'shop_id' => $activeShop->id,
                    'shop_ledger_transaction_id' => $openTx->id,
                    'amount' => $allocAmt,
                    'reconciled_by' => auth()->id(),
                ]);

                $remainingToAllocate = round($remainingToAllocate - $allocAmt, 2);
            }

            app(BalanceCalculator::class)->recalculate((int) $activeShop->id, $businessDate);
        });

        return redirect()
            ->route('shop-owner.payments.index')
            ->with('success', 'Payment of ₹'.number_format((float) $validated['amount'], 2).' for '.$setting->displayName().' recorded successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentsOverviewData(Request $request): array
    {
        $activeShop = $this->currentShop($request);
        $this->shopSettlementService->ensureDefaults($activeShop);

        $selectedMonth = (string) $request->input('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
            $selectedMonth = now()->format('Y-m');
        }

        $carbonMonth = Carbon::parse($selectedMonth.'-01');
        $periodStart = $carbonMonth->copy()->startOfMonth()->toDateString();
        $periodEnd = $carbonMonth->copy()->endOfMonth()->toDateString();
        $formattedMonthLabel = $carbonMonth->format('F Y');

        $paymentsData = $this->shopSettlementService->calculateShopPayments((int) $activeShop->id, $periodStart, $periodEnd);

        $payable = (float) $paymentsData['payable'];
        $paid = (float) $paymentsData['paid'];
        $directToCompany = (float) ($paymentsData['direct_to_company'] ?? $paymentsData['paid']);
        $cashInShop = (float) ($paymentsData['cash_in_shop'] ?? 0.0);
        $totalSales = (float) ($paymentsData['total_sales'] ?? ($directToCompany + $cashInShop));
        $salesCollectionItems = $paymentsData['sales_collection_items'] ?? [];
        $cashCollectionItems = $paymentsData['cash_collection_items'] ?? [];
        $moneyKeptByShop = (float) ($paymentsData['money_kept_by_shop'] ?? 0.0);
        $expensesPaid = (float) ($paymentsData['expenses_paid'] ?? 0.0);
        $shopBalance = (float) $paymentsData['shop_balance'];
        $manualPayments = $paymentsData['manual_payments'] ?? [];
        $manualTotal = (float) ($paymentsData['manual_total'] ?? 0.0);
        $manualReceived = (float) ($paymentsData['manual_received'] ?? 0.0);
        $manualPending = (float) ($paymentsData['manual_pending'] ?? 0.0);
        $manualRequests = $paymentsData['manual_requests'] ?? collect();

        $previousSnapshot = ShopDailyLedgerSnapshot::query()
            ->where('shop_id', (int) $activeShop->id)
            ->where('business_date', '<', $periodStart)
            ->orderByDesc('business_date')
            ->first();

        $pettyBalance = round((float) ($previousSnapshot?->closing_petty ?? 0.0), 2);

        return [
            'activeShop' => $activeShop,
            'targetMonth' => $selectedMonth,
            'formattedMonthLabel' => $formattedMonthLabel,
            'payable' => $payable,
            'paid' => $paid,
            'totalSales' => $totalSales,
            'directToCompany' => $directToCompany,
            'cashInShop' => $cashInShop,
            'moneyKeptByShop' => $moneyKeptByShop,
            'expensesPaid' => $expensesPaid,
            'shopBalance' => $shopBalance,
            'currentShopBalance' => $shopBalance,
            'companyPayable' => $payable,
            'totalPaid' => $paid,
            'companyDue' => $shopBalance,
            'pettyBalance' => $pettyBalance,
            'payableRelation' => $paymentsData['payable_relation'],
            'paidRelation' => $paymentsData['paid_relation'],
            'directRelation' => $paymentsData['direct_relation'] ?? $paymentsData['paid_relation'],
            'payableItems' => $paymentsData['payable_items'],
            'expensePayables' => $paymentsData['expense_payables'] ?? [],
            'settledExpenses' => $paymentsData['settled_expenses'] ?? [],
            'paidItems' => $paymentsData['paid_items'],
            'directItems' => $paymentsData['direct_items'] ?? $paymentsData['paid_items'],
            'salesCollectionItems' => $salesCollectionItems,
            'cashCollectionItems' => $cashCollectionItems,
            'manualPayments' => $manualPayments,
            'manualTotal' => $manualTotal,
            'manualReceived' => $manualReceived,
            'manualPending' => $manualPending,
            'manualRequests' => $manualRequests,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function financeViewData(Request $request, string $tab): array
    {
        $activeShop = $this->currentShop($request);
        $isOwnedAccountingShop = $activeShop->isOwnedAccountingEnabled();
        $selectedDays = $request->input('days');
        if (filled($selectedDays) && $selectedDays !== 'all') {
            $daysCount = (int) $selectedDays;
            if ($daysCount > 0) {
                $filterStartDate = now()->subDays($daysCount - 1)->startOfDay();
                $filterEndDate = now()->endOfDay();
            }
        } else {
            [$filterStartDate, $filterEndDate] = $this->nullableDateRangeFromRequest($request);
        }

        $invoiceQuery = ShopInvoice::query()
            ->where('shop_id', $activeShop->id)
            ->when($filterStartDate, fn ($query) => $query->whereDate('business_date', '>=', $filterStartDate))
            ->when($filterEndDate, fn ($query) => $query->whereDate('business_date', '<=', $filterEndDate));

        // Canonical Final Bill aggregation across all invoices in scope
        $allInvoicesInRange = (clone $invoiceQuery)
            ->with(['creditNotes'])
            ->get();
        $finalBillsInRange = app(ShopFinalBillService::class)->calculateMany($allInvoicesInRange);

        $grossBilled = round($finalBillsInRange->sum('originalInvoiceTotal'), 2);
        $totalFinalBilled = round($finalBillsInRange->sum('finalNetPayable'), 2);
        $totalPaidAmount = round($finalBillsInRange->sum('amountPaid'), 2);
        $totalOutstandingBalance = round($finalBillsInRange->sum('remainingBalance'), 2);
        $totalOverpaymentCredit = round($finalBillsInRange->sum('overpaymentCredit'), 2);
        $totalCreditAdjustments = round($finalBillsInRange->sum('finalizedCreditTotal'), 2);
        $totalDebitAdjustments = round($finalBillsInRange->sum('finalizedDebitTotal'), 2);
        $shortageValue = (float) ((clone $invoiceQuery)->sum('shortage_total') ?? 0);

        $invoices = (clone $invoiceQuery)
            ->with(['order', 'items', 'creditNotes', 'paymentRequests' => fn ($query) => $query->latest('id')])
            ->latest('business_date')
            ->latest('id')
            ->paginate(12, ['*'], 'invoices_page')
            ->withQueryString();

        // Pending/payable invoices: strictly those with an effective balance remaining
        $payableInvoiceIds = $allInvoicesInRange
            ->filter(fn (ShopInvoice $inv): bool => $inv->effectiveBalanceAmount() > 0.0001)
            ->pluck('id')
            ->all();

        $payableInvoices = (clone $invoiceQuery)
            ->whereIn('id', $payableInvoiceIds)
            ->with(['order', 'items', 'creditNotes'])
            ->oldest('business_date')
            ->oldest('id')
            ->paginate(8, ['*'], 'payable_invoices_page')
            ->withQueryString();

        $payableInvoiceTotal = round($finalBillsInRange->whereIn('invoiceId', $payableInvoiceIds)->sum('remainingBalance'), 2);
        $invoicePaymentRequests = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $activeShop->id)
            ->when($filterStartDate, fn ($query) => $query->whereDate('created_at', '>=', $filterStartDate))
            ->when($filterEndDate, fn ($query) => $query->whereDate('created_at', '<=', $filterEndDate))
            ->with(['invoice', 'requestedBy', 'reviewedBy', 'allocations'])
            ->latest('id')
            ->paginate(12, ['*'], 'payment_requests_page')
            ->withQueryString();
        $latestBalanceDate = $this->latestShopBalanceDate($activeShop);
        $latestClosingBalance = $this->ownedShopAccountingService->closingBalanceForDate($activeShop, $latestBalanceDate);
        $pendingBillApprovalSummary = $this->ownedShopAccountingService->pendingDeliveryBillApprovalSummary($activeShop);
        $pendingInvoicePaymentAmount = (float) ShopInvoicePaymentRequest::query()
            ->where('shop_id', $activeShop->id)
            ->where('status', 'pending')
            ->sum('requested_amount');
        $availableInvoicePaymentCredit = $this->shopInvoiceService->availableShopCredit((int) $activeShop->id);
        $carryOver = (float) ShopCredit::query()
            ->approved()
            ->where('shop_id', $activeShop->id)
            ->where(function ($query) {
                $query->where('description', 'like', '%carry-over%')
                    ->orWhere('description', 'like', '%carry over%')
                    ->orWhere('description', 'like', '%carry_over%')
                    ->orWhere('description', 'like', '%carryover%');
            })
            ->when($filterStartDate, fn ($query) => $query->whereDate('business_date', '>=', $filterStartDate))
            ->when($filterEndDate, fn ($query) => $query->whereDate('business_date', '<=', $filterEndDate))
            ->sum('amount');

        $payableCategories = collect();
        $payableTotal = 0.0;
        $payableReceivedTotal = 0.0;
        $payableBalance = 0.0;
        $dailyPayableBalances = collect();

        if ($isOwnedAccountingShop) {
            $settings = ShopLedgerEntrySetting::query()
                ->with('entryType:id,name,code,category')
                ->where('shop_id', (int) $activeShop->id)
                ->where('enabled', true)
                ->where('include_in_payable', true)
                ->get();

            $payableEntryTypeIds = $settings->pluck('entry_type_id')->filter()->all();

            $txQuery = ShopLedgerTransaction::query()
                ->with('entryType')
                ->where('shop_id', (int) $activeShop->id)
                ->when($filterStartDate, fn ($query) => $query->whereDate('business_date', '>=', $filterStartDate))
                ->when($filterEndDate, fn ($query) => $query->whereDate('business_date', '<=', $filterEndDate));

            $allTx = (clone $txQuery)->get();
            $payableRows = $allTx->filter(function ($tx) use ($payableEntryTypeIds) {
                return in_array($tx->entry_type_id, $payableEntryTypeIds, true)
                    || $tx->reference_type === 'collection_group';
            })->values();
            $settlementTransactions = $allTx->filter(function ($tx) {
                return ($tx->entryType && $tx->entryType->category === 'settlement')
                    || $tx->entry_type_code === 'shop_paid_company';
            });

            $approvedPaymentRequests = ShopInvoicePaymentRequest::query()
                ->where('shop_id', $activeShop->id)
                ->where('status', '!=', 'rejected')
                ->when($filterStartDate, fn ($query) => $query->whereDate('payment_date', '>=', $filterStartDate))
                ->when($filterEndDate, fn ($query) => $query->whereDate('payment_date', '<=', $filterEndDate))
                ->get();

            $payableReceivedTotal = round(max(
                (float) $settlementTransactions->sum('amount'),
                (float) $approvedPaymentRequests->sum('reconciled_amount')
            ), 2);

            $payableCategories = $payableRows
                ->groupBy(fn ($tx) => $tx->entryType?->name ?: $tx->entry_type_code)
                ->map(function ($group, $name) use ($settlementTransactions) {
                    $first = $group->first();
                    $code = (string) ($first->entryType?->code ?: $first->entry_type_code);
                    $recordedAmount = round((float) $group->sum('amount'), 2);

                    $categoryReceived = (float) $settlementTransactions->filter(function ($st) use ($name, $code) {
                        $notes = strtolower((string) ($st->notes ?? ''));

                        return str_contains($notes, strtolower($name)) || str_contains($notes, strtolower($code));
                    })->sum('amount');

                    $receivedAmount = round($categoryReceived, 2);
                    $balance = max(0, round($recordedAmount - $receivedAmount, 2));

                    $status = 'pending';
                    if ($receivedAmount >= $recordedAmount && $recordedAmount > 0) {
                        $status = 'received';
                    } elseif ($receivedAmount > 0) {
                        $status = 'partial';
                    }

                    return [
                        'name' => $name,
                        'code' => $code,
                        'recorded_amount' => $recordedAmount,
                        'received_amount' => $receivedAmount,
                        'balance' => $balance,
                        'status' => $status,
                        'count' => $group->count(),
                    ];
                })
                ->values();

            $unallocatedReceived = max(0, $payableReceivedTotal - (float) $payableCategories->sum('received_amount'));
            if ($unallocatedReceived > 0) {
                $remainingToAllocate = $unallocatedReceived;
                $payableCategories = $payableCategories->map(function ($cat) use (&$remainingToAllocate) {
                    if ($remainingToAllocate <= 0) {
                        return $cat;
                    }
                    $needed = $cat['balance'];
                    $alloc = min($remainingToAllocate, $needed);
                    $cat['received_amount'] = round($cat['received_amount'] + $alloc, 2);
                    $cat['balance'] = max(0, round($cat['recorded_amount'] - $cat['received_amount'], 2));
                    $remainingToAllocate -= $alloc;

                    if ($cat['received_amount'] >= $cat['recorded_amount'] && $cat['recorded_amount'] > 0) {
                        $cat['status'] = 'received';
                    } elseif ($cat['received_amount'] > 0) {
                        $cat['status'] = 'partial';
                    }

                    return $cat;
                });
            }

            $payableTotal = round((float) $payableRows->sum('amount'), 2);
            $totalReceivedAllocated = (float) $payableCategories->sum('received_amount');
            $effectiveReceived = max($payableReceivedTotal, $totalReceivedAllocated);
            $payableBalance = max(0, round($payableTotal - $effectiveReceived, 2));

            $dailyPendingPaymentRequests = ShopInvoicePaymentRequest::query()
                ->where('shop_id', $activeShop->id)
                ->where('status', 'pending')
                ->get();

            $dailyPayableBalances = $allTx
                ->groupBy(fn ($tx) => $tx->business_date?->format('Y-m-d') ?: 'Unknown')
                ->map(function ($group, $dateStr) use ($payableEntryTypeIds, $dailyPendingPaymentRequests, $approvedPaymentRequests) {
                    $datePayableRows = $group->filter(function ($tx) use ($payableEntryTypeIds) {
                        return in_array($tx->entry_type_id, $payableEntryTypeIds, true)
                            || $tx->reference_type === 'collection_group';
                    });

                    $dateSettlements = $group->filter(function ($tx) {
                        return ($tx->entryType && $tx->entryType->category === 'settlement')
                            || $tx->entry_type_code === 'shop_paid_company';
                    });

                    $dateApprovedAmount = (float) $approvedPaymentRequests
                        ->filter(function (ShopInvoicePaymentRequest $paymentRequest) use ($dateStr): bool {
                            return $paymentRequest->payment_date?->toDateString() === $dateStr
                                || str_contains((string) $paymentRequest->shop_note, $dateStr)
                                || str_contains((string) $paymentRequest->payment_reference, $dateStr);
                        })
                        ->sum('reconciled_amount');

                    $outAmount = round((float) $datePayableRows->sum('amount'), 2);
                    $inAmount = round(max((float) $dateSettlements->sum('amount'), $dateApprovedAmount), 2);
                    $netBalance = max(0, round($outAmount - $inAmount, 2));

                    $hasPendingRequest = $dailyPendingPaymentRequests->contains(function ($pr) use ($dateStr) {
                        return str_contains((string) $pr->shop_note, $dateStr) || str_contains((string) $pr->payment_reference, $dateStr);
                    }) || ($dailyPendingPaymentRequests->isNotEmpty() && $netBalance > 0);

                    $status = 'unpaid';
                    if ($outAmount > 0 && $netBalance <= 0) {
                        $status = 'fully_settled';
                    } elseif ($inAmount > 0 && $netBalance > 0) {
                        $status = 'partially_settled';
                    } elseif ($hasPendingRequest) {
                        $status = 'pending_approval';
                    }

                    $dateCarbon = $group->first()?->business_date;

                    return [
                        'date' => $dateStr,
                        'date_label' => $dateCarbon ? $dateCarbon->format('d M Y') : $dateStr,
                        'out_amount' => $outAmount,
                        'in_amount' => $inAmount,
                        'net_balance' => $netBalance,
                        'status' => $status,
                        'items_count' => $datePayableRows->count(),
                        'has_pending_request' => $hasPendingRequest,
                    ];
                })
                ->filter(fn ($row) => $row['out_amount'] > 0 || $row['in_amount'] > 0)
                ->sortByDesc('date')
                ->values();

            $currentPage = LengthAwarePaginator::resolveCurrentPage('daily_payables_page');
            $perPage = 11;
            $currentItems = $dailyPayableBalances->slice(($currentPage - 1) * $perPage, $perPage)->values();
            $dailyPayableBalances = new LengthAwarePaginator(
                $currentItems,
                $dailyPayableBalances->count(),
                $perPage,
                $currentPage,
                [
                    'path' => $request->url(),
                    'pageName' => 'daily_payables_page',
                ]
            );
            $dailyPayableBalances->withQueryString();
        }

        $companyPayableQuery = ShopAccountingEntryLine::query()
            ->with(['entry.shop', 'category', 'settlements.paymentRequest', 'settlements.creator', 'approvedBy', 'rejectedBy'])
            ->whereHas('entry', fn ($q) => $q->where('shop_id', $activeShop->id))
            ->where(function ($q) {
                $q->where('funding_source', ShopAccountingEntryLine::FundingCompany)
                    ->orWhereNotNull('company_payable_status');
            })
            ->when($filterStartDate, fn ($query) => $query->whereHas('entry', fn ($q) => $q->whereDate('business_date', '>=', $filterStartDate)))
            ->when($filterEndDate, fn ($query) => $query->whereHas('entry', fn ($q) => $q->whereDate('business_date', '<=', $filterEndDate)));

        $allCompanyPayableLines = (clone $companyPayableQuery)->get();

        $companyPayableTotals = [
            'total_out' => round((float) $allCompanyPayableLines->sum(fn ($l) => (float) ($l->company_approved_amount ?? $l->company_payable_amount ?? $l->amount)), 2),
            'total_settled' => round((float) $allCompanyPayableLines->sum(fn ($l) => (float) ($l->company_settled_amount ?? 0)), 2),
            'remaining_balance' => round((float) $allCompanyPayableLines->sum(fn ($l) => $l->remainingCompanyPayableAmount()), 2),
            'pending_count' => $allCompanyPayableLines->where('company_payable_status', 'pending')->count(),
            'approved_count' => $allCompanyPayableLines->where('company_payable_status', 'approved')->count(),
        ];

        $companyPayableLines = (clone $companyPayableQuery)
            ->latest('id')
            ->paginate(11, ['*'], 'company_payables_page')
            ->withQueryString();

        $currentMonthStart = now()->startOfMonth();
        $currentMonthEnd = now()->endOfMonth();

        if ($isOwnedAccountingShop) {
            $monthlySettings = ShopLedgerEntrySetting::query()
                ->where('shop_id', (int) $activeShop->id)
                ->where('enabled', true)
                ->where('include_in_payable', true)
                ->pluck('entry_type_id')
                ->filter()
                ->all();

            $monthlyTx = ShopLedgerTransaction::query()
                ->with('entryType')
                ->where('shop_id', (int) $activeShop->id)
                ->whereDate('business_date', '>=', $currentMonthStart)
                ->whereDate('business_date', '<=', $currentMonthEnd)
                ->get();

            $monthlyPayableRows = $monthlyTx->filter(function ($tx) use ($monthlySettings) {
                return in_array($tx->entry_type_id, $monthlySettings, true)
                    || $tx->reference_type === 'collection_group';
            });

            $monthlySettlements = $monthlyTx->filter(function ($tx) {
                return ($tx->entryType && $tx->entryType->category === 'settlement')
                    || $tx->entry_type_code === 'shop_paid_company';
            });

            $monthlyReconciledPaymentAmount = (float) ShopInvoicePaymentRequest::query()
                ->where('shop_id', $activeShop->id)
                ->where('status', '!=', 'rejected')
                ->whereDate('payment_date', '>=', $currentMonthStart)
                ->whereDate('payment_date', '<=', $currentMonthEnd)
                ->sum('reconciled_amount');

            $monthlyPaidAmount = round(max((float) $monthlySettlements->sum('amount'), $monthlyReconciledPaymentAmount), 2);
            $monthlyTotalOut = round((float) $monthlyPayableRows->sum('amount'), 2);
            $monthlyBalanceToPay = max(0, round($monthlyTotalOut - $monthlyPaidAmount, 2));
        } else {
            $monthlyInvoices = ShopInvoice::query()
                ->where('shop_id', $activeShop->id)
                ->whereDate('business_date', '>=', $currentMonthStart)
                ->whereDate('business_date', '<=', $currentMonthEnd)
                ->with(['creditNotes'])
                ->get();

            $monthlyBills = app(ShopFinalBillService::class)->calculateMany($monthlyInvoices);
            $monthlyPaidAmount = round($monthlyBills->sum('amountPaid'), 2);
            $monthlyBalanceToPay = round($monthlyBills->sum('remainingBalance'), 2);
        }

        return [
            'invoices' => $invoices,
            'payableInvoices' => $payableInvoices,
            'payableInvoiceTotal' => $payableInvoiceTotal,
            'invoicePaymentRequests' => $invoicePaymentRequests,
            'activeTab' => $tab,
            'isOwnedAccountingShop' => $isOwnedAccountingShop,
            'grossBilled' => $grossBilled,
            'totalBilled' => $totalFinalBilled,
            'totalFinalBilled' => $totalFinalBilled,
            'totalCreditAdjustments' => $totalCreditAdjustments,
            'totalDebitAdjustments' => $totalDebitAdjustments,
            'outstandingBalance' => $totalOutstandingBalance,
            'paidAmount' => $totalPaidAmount,
            'overpaymentCredit' => $totalOverpaymentCredit,
            'monthlyPaidAmount' => $monthlyPaidAmount,
            'monthlyBalanceToPay' => $monthlyBalanceToPay,
            'shortageValue' => $shortageValue,
            'pendingPaymentAmount' => $pendingInvoicePaymentAmount,
            'availableInvoicePaymentCredit' => $availableInvoicePaymentCredit,
            'latestBalanceDate' => $latestBalanceDate,
            'latestClosingBalance' => $latestClosingBalance,
            'pendingBillApprovalSummary' => $pendingBillApprovalSummary,
            'filterStartDate' => $filterStartDate,
            'filterEndDate' => $filterEndDate,
            'carryOver' => $carryOver,
            'payableCategories' => $payableCategories,
            'payableTotal' => $payableTotal,
            'payableReceivedTotal' => $payableReceivedTotal,
            'payableBalance' => $payableBalance,
            'dailyPayableBalances' => $dailyPayableBalances,
            'companyPayableLines' => $companyPayableLines,
            'companyPayableTotals' => $companyPayableTotals,
            'selectedDays' => $selectedDays,
            'statementData' => $this->statementOfAccountData($request, $activeShop),
        ];
    }

    public function financeShow(Request $request, ShopInvoice $invoice): View
    {
        $activeShop = $this->currentShop($request);
        abort_unless($invoice->shop_id === $activeShop->id, 403);

        return view('shop-owner.finance.show', [
            'invoice' => $invoice->load(['shop', 'items.product', 'order', 'paymentRequests.requestedBy', 'paymentRequests.reviewedBy']),
        ]);
    }

    public function financePdf(Request $request, ShopInvoice $invoice): View
    {
        $activeShop = $this->currentShop($request);
        abort_unless($invoice->shop_id === $activeShop->id, 403);

        return view('shop-owner.finance.pdf', [
            'invoice' => $invoice->load(['shop', 'items.product', 'order']),
        ]);
    }

    public function accountingCashbookPdf(Request $request): RedirectResponse
    {
        $shop = $this->currentShop($request);

        return $this->redirectToNewCashbook(
            $shop,
            'show',
            ['date' => Carbon::parse($request->input('date', today()->toDateString()))->toDateString()]
        )->with('warning', 'Cashbook PDF moved to the new cashbook dashboard.');
    }

    public function accountingIndex(Request $request): RedirectResponse
    {
        $shop = $this->currentShop($request);
        $tab = (string) $request->input('tab', $shop->isOwnedAccountingEnabled() ? 'cashbook' : 'bills');
        $date = Carbon::parse($request->input('date', today()->toDateString()))->toDateString();

        if ($tab === 'bills') {
            return redirect()->route('shop-owner.finance.index', [
                'tab' => 'invoices',
                'date' => $date,
            ]);
        }

        if ($tab === 'create') {
            return $this->redirectToNewCashbook($shop, 'post-entry', ['date' => $date, 'open' => 'line'])
                ->with('success', 'Cashbook create moved to the new dashboard.');
        }

        if (in_array($tab, ['loan', 'others'], true)) {
            return $this->redirectToNewCashbook($shop, 'settings', ['date' => $date])
                ->with('success', 'Cashbook settings moved to the new dashboard.');
        }

        return $this->redirectToNewCashbook($shop, 'show', ['date' => $date])
            ->with('success', 'Cashbook moved to the new dashboard.');
    }

    public function accountingHistory(Request $request): RedirectResponse
    {
        $shop = $this->currentShop($request);

        return $this->redirectToNewCashbook(
            $shop,
            'show',
            ['date' => Carbon::parse($request->input('date', today()->toDateString()))->toDateString()]
        )->with('success', 'Cashbook history moved to the new dashboard.');
    }

    public function accountingDailyReport(Request $request): RedirectResponse
    {
        $shop = $this->currentShop($request);
        $date = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', (string) $request->input('month'))->startOfMonth()->toDateString()
            : today()->toDateString();

        return $this->redirectToNewCashbook($shop, 'show', ['date' => $date])
            ->with('success', 'Daily cashbook report moved to the new dashboard.');
    }

    public function cashbookShow(Request $request): View|RedirectResponse
    {
        $shop = $this->ownedAccountingShop($request);
        $tab = (string) $request->input('tab', 'cashbook');
        $open = (string) $request->input('open', '');
        $timeframe = (string) $request->input('timeframe', 'daily');
        $requestedDate = $request->string('date', '')->toString();
        $date = $this->dailyLedgerService->resolveActiveBusinessDate($shop, $requestedDate ?: null);
        $startDate = (string) $request->input('start_date', $date);
        $endDate = (string) $request->input('end_date', $date);
        $month = (string) $request->input('month', substr($date, 0, 7));

        if ($open === 'vendor_purchase' || $open === 'purchase') {
            $redirectParams = [
                'date' => $date,
                'open' => 'new',
            ];
            if ($request->filled('category_id')) {
                $redirectParams['category_id'] = $request->input('category_id');
            }

            return redirect()->route('shop-owner.cashbook.vendor-purchases', $redirectParams);
        }

        if ($tab === 'reports') {
            if ($timeframe === 'monthly') {
                $syncStartDate = Carbon::parse($month.'-01')->startOfMonth()->toDateString();
                $syncEndDate = Carbon::parse($month.'-01')->endOfMonth()->toDateString();
            } elseif ($timeframe === 'custom') {
                $syncStartDate = $startDate;
                $syncEndDate = $endDate;
            } else {
                $syncStartDate = $date;
                $syncEndDate = $date;
            }
        } else {
            $syncStartDate = $date;
            $syncEndDate = $date;
        }

        $this->cashbookShopSyncService->syncAndGetProfiles();

        $settings = ShopLedgerEntrySetting::query()
            ->with(['entryType:id,name,code,category', 'companyAccount:id,name,bank_name,account_number', 'headerGroup:id,name,type,cash_flow_mode,company_account_id,note_enabled,show_both_sides,product_tagging_enabled', 'vendorSettlementRelation:id,name', 'definedShopSuppliers.supplier'])
            ->where('shop_id', (int) $shop->id)
            ->where('enabled', true)
            ->orderBy('display_order')
            ->get();

        $headerGroups = ShopLedgerHeaderGroup::query()
            ->where('shop_id', (int) $shop->id)
            ->where('enabled', true)
            ->orderBy('display_order')
            ->get();

        $relations = ShopCashbookRelation::query()
            ->with(['items.setting.entryType', 'items.setting.companyAccount', 'items.headerGroup', 'items.sourceSettlement'])
            ->where('shop_id', (int) $shop->id)
            ->where('enabled', true)
            ->orderBy('display_order')
            ->get();

        $companyAccounts = CompanyAccount::query()
            ->where('enabled', true)
            ->get();

        // Ensure approved invoice bills and staff payments are reflected in cashbook as default daily expenses.
        $this->syncApprovedInvoiceBillsToCashbook(
            $shop,
            $syncStartDate,
            $syncEndDate,
            (int) ($request->user()?->id ?? 1),
        );
        $this->syncStaffPaymentsToCashbook(
            $shop,
            $syncStartDate,
            $syncEndDate,
            (int) ($request->user()?->id ?? 1),
        );

        $txQuery = ShopLedgerTransaction::query()
            ->with('entryType')
            ->where('shop_id', (int) $shop->id)
            ->where('status', '!=', TransactionStatus::Void->value);

        if ($tab === 'reports') {
            if ($timeframe === 'monthly') {
                $txQuery->where('business_date', 'like', $month.'%');
            } elseif ($timeframe === 'custom') {
                $txQuery->whereBetween('business_date', [$startDate, $endDate]);
            } else {
                $txQuery->where('business_date', $date);
            }
        } else {
            $txQuery->where('business_date', $date);
        }

        $todayTransactions = $txQuery->get();

        $entryTypes = $settings
            ->pluck('entryType')
            ->filter()
            ->unique('id')
            ->values();
        $collectionGroups = $this->collectionGroupPostingService->groupsForShop((int) $shop->id);

        $snapshot = $this->dailyLedgerService->dailySummary((int) $shop->id, $syncEndDate);

        $productEntries = ShopLedgerProductEntry::query()
            ->with(['product.orderUnits'])
            ->where('shop_id', (int) $shop->id)
            ->where('business_date', $date)
            ->get();

        $linkedVendors = $shop->isPurchasingEnabled()
            ? $shop->suppliers()
                ->wherePivot('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Supplier $s): object => (object) [
                    'id' => $s->id,
                    'name' => $s->name,
                    'mobile_number' => $s->mobile_number,
                    'credit_approved' => (bool) ($s->pivot?->credit_approved ?? $s->credit_approved ?? false),
                ])
            : collect();

        $purchasableProducts = $shop->isPurchasingEnabled()
            ? Product::query()->active()->with(['category:id,name'])->orderBy('name')->get(['id', 'category_id', 'name', 'sku', 'unit'])
                ->map(fn (Product $p): array => [
                    'id' => (int) $p->id,
                    'category_id' => $p->category_id ? (int) $p->category_id : null,
                    'category_name' => $p->category?->name,
                    'name' => (string) $p->name,
                    'sku' => (string) ($p->sku ?? ''),
                    'unit' => (string) ($p->unit ?: 'kg'),
                ])
            : collect();

        $vendorPurchaseSummaries = $this->getVendorPurchaseSummaries($shop, $date, $settings);

        $salarySectionData = $this->shopCashbookSalarySectionService
            ->getSalarySection($shop, $syncStartDate, $syncEndDate);

        return view('shop-owner.cashbook.index', [
            'shop' => $shop,
            'selectedDate' => Carbon::parse($date),
            'entryTypes' => $entryTypes,
            'settings' => $settings,
            'headerGroups' => $headerGroups,
            'relations' => $relations,
            'companyAccounts' => $companyAccounts,
            'todayTransactions' => $todayTransactions,
            'productEntries' => $productEntries,
            'collectionGroups' => $collectionGroups,
            'snapshot' => $snapshot,
            'activeTab' => in_array($tab, ['cashbook', 'settings', 'reports'], true) ? $tab : 'cashbook',
            'openModal' => $open === 'line',
            'openVendorPurchase' => false,
            'linkedVendors' => $linkedVendors,
            'purchasableProducts' => $purchasableProducts,
            'vendorPurchaseSummaries' => $vendorPurchaseSummaries,
            'timeframe' => $timeframe,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedMonth' => $month,
            'salarySectionData' => $salarySectionData,
        ]);
    }

    public function cashbookCreate(Request $request): RedirectResponse
    {
        return redirect()->route('shop-owner.cashbook.show', [
            'date' => (string) $request->input('date', today()->toDateString()),
            'open' => 'line',
        ]);
    }

    public function cashbookSettings(Request $request): RedirectResponse
    {
        return redirect()->route('shop-owner.cashbook.show', [
            'date' => (string) $request->input('date', today()->toDateString()),
            'tab' => 'settings',
        ]);
    }

    public function cashbookReports(Request $request): RedirectResponse
    {
        return redirect()->route('shop-owner.cashbook.show', array_filter([
            'date' => (string) $request->input('date', today()->toDateString()),
            'tab' => 'reports',
            'timeframe' => (string) $request->input('timeframe', 'daily'),
            'start_date' => (string) $request->input('start_date', ''),
            'end_date' => (string) $request->input('end_date', ''),
        ]));
    }

    public function cashbookData(Request $request): JsonResponse
    {
        $shop = $this->ownedAccountingShop($request);
        $date = Carbon::parse((string) $request->input('business_date', today()->toDateString()))->toDateString();
        $timeframe = (string) $request->input('timeframe', 'daily');
        $startDateParam = $request->input('start_date');
        $endDateParam = $request->input('end_date');
        $month = substr($date, 0, 7);

        $this->cashbookShopSyncService->syncAndGetProfiles();

        $carbon = Carbon::parse($date);
        $startOfWeek = $carbon->copy()->startOfWeek()->toDateString();
        $endOfWeek = $carbon->copy()->endOfWeek()->toDateString();

        $customStart = $startDateParam ? Carbon::parse((string) $startDateParam)->toDateString() : $date;
        $customEnd = $endDateParam ? Carbon::parse((string) $endDateParam)->toDateString() : $date;

        [$syncStartDate, $syncEndDate] = match ($timeframe) {
            'weekly' => [$startOfWeek, $endOfWeek],
            'monthly' => [$carbon->copy()->startOfMonth()->toDateString(), $carbon->copy()->endOfMonth()->toDateString()],
            'custom' => [$customStart, $customEnd],
            default => [$date, $date],
        };

        $this->syncApprovedInvoiceBillsToCashbook(
            $shop,
            $syncStartDate,
            $syncEndDate,
            (int) ($request->user()?->id ?? 1),
        );
        $this->syncStaffPaymentsToCashbook(
            $shop,
            $syncStartDate,
            $syncEndDate,
            (int) ($request->user()?->id ?? 1),
        );

        $query = ShopLedgerTransaction::query()
            ->with('entryType')
            ->where('shop_id', (int) $shop->id)
            ->where('status', '!=', TransactionStatus::Void->value);

        if ($timeframe === 'weekly') {
            $query->whereBetween('business_date', [$startOfWeek, $endOfWeek]);
        } elseif ($timeframe === 'monthly') {
            $query->where('business_date', 'like', $month.'%');
        } elseif ($timeframe === 'custom') {
            $query->whereBetween('business_date', [$syncStartDate, $syncEndDate]);
        } else {
            $query->where('business_date', $date);
        }

        $transactions = $query
            ->orderBy('business_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $monthTransactionsQuery = ShopLedgerTransaction::query()
            ->with('entryType')
            ->where('shop_id', (int) $shop->id)
            ->where('status', '!=', TransactionStatus::Void->value);

        if (in_array($timeframe, ['weekly', 'monthly', 'custom'], true)) {
            $monthTransactionsQuery->whereBetween('business_date', [$syncStartDate, $syncEndDate]);
        } else {
            $monthTransactionsQuery->where('business_date', 'like', $month.'%');
        }

        $monthTransactions = $monthTransactionsQuery
            ->orderBy('business_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $settings = ShopLedgerEntrySetting::query()
            ->with('entryType:id,name,code,category')
            ->where('shop_id', (int) $shop->id)
            ->where('enabled', true)
            ->orderBy('display_order')
            ->get();
        $collectionGroups = $this->collectionGroupPostingService->groupsForShop((int) $shop->id);
        $collectionSummaries = $this->collectionGroupPostingService->summaries($transactions);
        $payableRowCodes = $settings
            ->filter(fn (ShopLedgerEntrySetting $setting): bool => (bool) $setting->include_in_payable && (bool) $setting->entryType)
            ->pluck('entryType.code')
            ->filter()
            ->values();
        $payableTransactions = $transactions->filter(function (ShopLedgerTransaction $transaction) use ($payableRowCodes): bool {
            return in_array($transaction->entryType?->code, $payableRowCodes->all(), true)
                || $transaction->reference_type === 'collection_group';
        });
        $payableTotal = round((float) $payableTransactions->sum(function ($tx) use ($settings) {
            $code = (string) ($tx->entryType?->code ?: $tx->entry_type_code);
            $direction = (string) ($tx->direction ?: ($tx->entryType?->category ?: 'income'));
            $category = (string) ($tx->entryType?->category ?: $direction);
            $setting = $settings->first(function ($s) use ($tx) {
                if ((int) $s->entry_type_id !== (int) $tx->entry_type_id) {
                    return false;
                }
                if ($tx->funding_source) {
                    $resolver = app(CashFlowResolutionService::class);

                    return $resolver->resolveFundingSource($s) === $tx->funding_source;
                }

                return true;
            }) ?? $settings->firstWhere('entry_type_id', $tx->entry_type_id);
            $payableDir = $setting?->payable_direction;
            $isDeduction = $payableDir ? ($payableDir === 'minus') : ($direction === 'expense' || $category === 'expense' || in_array($code, ['company_to_petty', 'company_paid_shop', 'company_paid_vendor'], true));

            return $isDeduction ? -(float) $tx->amount : (float) $tx->amount;
        }), 2);

        $totalSales = (float) $transactions->sum(function ($t) use ($settings) {
            $setting = $settings->first(function ($s) use ($t) {
                if ((int) $s->entry_type_id !== (int) $t->entry_type_id) {
                    return false;
                }
                if ($t->funding_source) {
                    $resolver = app(CashFlowResolutionService::class);

                    return $resolver->resolveFundingSource($s) === $t->funding_source;
                }

                return true;
            }) ?? $settings->firstWhere('entry_type_id', $t->entry_type_id);
            $isIncome = $t->direction === 'income' || ($t->entryType && $t->entryType->category === 'income');
            if ($isIncome) {
                return (float) $t->amount;
            }
            if ($setting && $setting->include_in_sales && ($setting->payable_direction === 'minus' || $t->direction === 'transfer' || $t->entryType?->category === 'transfer')) {
                return -(float) $t->amount;
            }

            return 0.0;
        });

        $totalExpense = (float) $transactions
            ->filter(fn ($t) => $t->direction === 'expense' || ($t->entryType && $t->entryType->category === 'expense'))
            ->sum('amount');

        $dailySnapshot = $this->dailyLedgerService->dailySummary((int) $shop->id, $date);

        $profile = ShopLedgerProfile::query()->where('shop_id', (int) $shop->id)->first();
        $pettyConfig = $profile?->getPaymentConfiguration()['petty'] ?? [
            'configured' => false,
            'enabled' => true,
            'shop_owner_view_petty' => true,
        ];
        $canViewPetty = ! ($pettyConfig['configured'] ?? false) || (($pettyConfig['enabled'] ?? true) && ($pettyConfig['shop_owner_view_petty'] ?? true));

        $snapshot = [
            'total_sales' => $totalSales,
            'total_expense' => $totalExpense,
            'closing_shop_position' => $timeframe === 'daily'
                ? ((float) ($dailySnapshot->closing_shop_position ?? ($totalSales - $totalExpense)))
                : ($totalSales - $totalExpense),
            'closing_petty' => $canViewPetty ? (float) ($dailySnapshot->closing_petty ?? 0) : null,
            'opening_petty' => $canViewPetty ? (float) ($dailySnapshot->opening_petty ?? 0) : null,
            'petty_in' => $canViewPetty ? (float) ($dailySnapshot->petty_in ?? 0) : null,
            'petty_out' => $canViewPetty ? (float) ($dailySnapshot->petty_out ?? 0) : null,
            'closing_company_pending' => (float) ($dailySnapshot->closing_company_pending ?? 0),
        ];

        $settlementTransactions = $transactions->filter(function ($tx) {
            return ($tx->entryType && $tx->entryType->category === 'settlement')
                || $tx->entry_type_code === 'shop_paid_company';
        });
        $payableReceivedTotal = round((float) $settlementTransactions->sum('amount'), 2);

        $payableByCategory = $payableTransactions
            ->groupBy(fn ($tx) => $tx->entryType?->name ?: $tx->entry_type_code)
            ->map(function ($group, $name) use ($settlementTransactions) {
                $first = $group->first();
                $code = (string) ($first->entryType?->code ?: $first->entry_type_code);
                $recordedAmount = round((float) $group->sum('amount'), 2);

                $categoryReceived = (float) $settlementTransactions->filter(function ($st) use ($name, $code) {
                    $notes = strtolower((string) ($st->notes ?? ''));

                    return str_contains($notes, strtolower($name)) || str_contains($notes, strtolower($code));
                })->sum('amount');

                $receivedAmount = round($categoryReceived, 2);
                $balance = max(0, round($recordedAmount - $receivedAmount, 2));

                $status = 'pending';
                if ($receivedAmount >= $recordedAmount && $recordedAmount > 0) {
                    $status = 'received';
                } elseif ($receivedAmount > 0) {
                    $status = 'partial';
                }

                return [
                    'name' => $name,
                    'code' => $code,
                    'recorded_amount' => $recordedAmount,
                    'received_amount' => $receivedAmount,
                    'balance' => $balance,
                    'status' => $status,
                    'count' => $group->count(),
                ];
            })
            ->values();

        $unallocatedReceived = max(0, $payableReceivedTotal - (float) $payableByCategory->sum('received_amount'));
        if ($unallocatedReceived > 0) {
            $remainingToAllocate = $unallocatedReceived;
            $payableByCategory = $payableByCategory->map(function ($cat) use (&$remainingToAllocate) {
                if ($remainingToAllocate <= 0) {
                    return $cat;
                }
                $needed = $cat['balance'];
                $alloc = min($remainingToAllocate, $needed);
                $cat['received_amount'] = round($cat['received_amount'] + $alloc, 2);
                $cat['balance'] = max(0, round($cat['recorded_amount'] - $cat['received_amount'], 2));
                $remainingToAllocate -= $alloc;

                if ($cat['received_amount'] >= $cat['recorded_amount'] && $cat['recorded_amount'] > 0) {
                    $cat['status'] = 'received';
                } elseif ($cat['received_amount'] > 0) {
                    $cat['status'] = 'partial';
                }

                return $cat;
            });
        }

        $totalReceivedAllocated = (float) $payableByCategory->sum('received_amount');
        $effectiveReceived = max($payableReceivedTotal, $totalReceivedAllocated);
        $payableBalance = max(0, round($payableTotal - $effectiveReceived, 2));

        $companyPendingEntries = ShopLedgerTransaction::with('entryType')
            ->where('shop_id', (int) $shop->id)
            ->where(function ($q) use ($payableRowCodes) {
                $q->where('company_pending_delta', '!=', 0)
                    ->orWhere('funding_source', 'company')
                    ->when($payableRowCodes->isNotEmpty(), function ($sub) use ($payableRowCodes) {
                        $sub->orWhereHas('entryType', fn ($eq) => $eq->whereIn('code', $payableRowCodes->all()));
                    })
                    ->orWhereHas('entryType', function ($eq) {
                        $eq->whereIn('code', [
                            'vehicle',
                            'company_paid_shop',
                            'company_paid_vendor',
                            'company_to_petty',
                            'company_reimbursement',
                        ]);
                    });
            })
            ->orderBy('business_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $productEntries = ShopLedgerProductEntry::query()
            ->with(['product.orderUnits'])
            ->where('shop_id', (int) $shop->id)
            ->where('business_date', $date)
            ->get();
        $productRowsMap = $this->formatProductRowsByHeader($productEntries);
        $vendorPurchaseSummaries = $this->getVendorPurchaseSummaries($shop, $date, $settings);

        return response()->json([
            'success' => true,
            'snapshot' => $snapshot,
            'transactions' => $transactions,
            'month_transactions' => $monthTransactions,
            'settings' => $settings,
            'vendor_purchase_summaries' => $vendorPurchaseSummaries,
            'collection_groups' => $collectionGroups,
            'collection_summaries' => $collectionSummaries,
            'company_pending_entries' => $companyPendingEntries,
            'payable_rows' => $payableTransactions->map(fn (ShopLedgerTransaction $transaction): array => [
                'date' => $transaction->business_date->toDateString(),
                'entry_type_code' => $transaction->entryType?->code,
                'entry_type_name' => $transaction->entryType?->name,
                'funding_source' => $transaction->funding_source,
                'amount' => (float) $transaction->amount,
                'notes' => $transaction->notes,
            ])->values(),
            'payable_total' => $payableTotal,
            'payable_received_total' => $effectiveReceived,
            'payable_balance' => $payableBalance,
            'payable_by_category' => $payableByCategory,
            'vendor_purchase_summaries' => $this->getVendorPurchaseSummaries($shop, $date, $settings),
            'product_rows' => $productRowsMap,
            'productRows' => $productRowsMap,
            'timeframe' => $timeframe,
        ]);
    }

    /**
     * @param  Collection<int, ShopLedgerEntrySetting>  $settings
     * @return array<int, array{setting_id: int, name: string, total_amount: float, cash_amount: float, credit_amount: float, count: int}>
     */
    private function getVendorPurchaseSummaries(Shop $shop, string $date, Collection $settings): array
    {
        return $this->vendorReportService->getVendorPurchaseSummariesForCashbook($shop, $date, $settings);
    }

    /**
     * @param  Collection<int, ShopLedgerProductEntry>  $productEntries
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function formatProductRowsByHeader(Collection $productEntries): array
    {
        $rowsByHeader = [];
        foreach ($productEntries as $pe) {
            $hId = (string) $pe->header_group_id;
            $units = [];
            $baseUnit = strtolower(trim((string) ($pe->unit ?: $pe->product?->unit ?: 'unit')));
            $units[] = [
                'unit' => $baseUnit,
                'label' => strtoupper($baseUnit),
                'conversion_to_base' => 1.0,
                'is_base' => true,
            ];
            if ($pe->product) {
                foreach ($pe->product->orderUnits as $ou) {
                    $ouUnit = strtolower(trim((string) $ou->unit));
                    if ($ouUnit !== '' && ! collect($units)->contains('unit', $ouUnit)) {
                        $units[] = [
                            'unit' => $ouUnit,
                            'label' => $ou->label ?: strtoupper($ouUnit),
                            'conversion_to_base' => $ou->conversion_to_base !== null ? (float) $ou->conversion_to_base : 1.0,
                            'is_base' => (bool) $ou->is_base,
                        ];
                    }
                }
            }

            $qty = (float) $pe->quantity;
            $amt = (float) $pe->amount;
            $avgPrice = ($qty > 0 && $amt > 0) ? round($amt / $qty, 2) : null;

            $rowsByHeader[$hId][] = [
                'productId' => (int) $pe->product_id,
                'product_id' => (int) $pe->product_id,
                'productName' => (string) ($pe->product_name ?: ($pe->product?->name ?? '')),
                'product_name' => (string) ($pe->product_name ?: ($pe->product?->name ?? '')),
                'sku' => (string) ($pe->product_sku ?: ($pe->product?->sku ?? '')),
                'product_sku' => (string) ($pe->product_sku ?: ($pe->product?->sku ?? '')),
                'qty' => $qty > 0 ? $qty : '',
                'quantity' => $qty,
                'unit' => $pe->unit ?: $baseUnit,
                'units' => $units,
                'amount' => $amt,
                'avgPrice' => $avgPrice,
            ];
        }

        return $rowsByHeader;
    }

    private function syncApprovedInvoiceBillsToCashbook(Shop $shop, string $startDate, string $endDate, int $userId): void
    {
        $invoices = ShopInvoice::query()
            ->where('shop_id', (int) $shop->id)
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->orderBy('business_date')
            ->orderBy('id')
            ->get();

        foreach ($invoices as $invoice) {
            $this->invoiceCashbookProjectionService->syncInvoice($invoice, $userId);
        }
    }

    private function syncStaffPaymentsToCashbook(Shop $shop, string $startDate, string $endDate, int $userId): void
    {
        if (! $this->staffPaymentCashbookProjectionService instanceof StaffPaymentCashbookProjectionService) {
            return;
        }

        $payments = ShopStaffPayment::query()
            ->where('shop_id', (int) $shop->id)
            ->whereDate('paid_on', '>=', $startDate)
            ->whereDate('paid_on', '<=', $endDate)
            ->orderBy('paid_on')
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            $this->staffPaymentCashbookProjectionService->syncPayment($payment, $userId);
        }
    }

    public function cashbookSearchProducts(Request $request): JsonResponse
    {
        $shop = $this->ownedAccountingShop($request);
        $q = trim((string) $request->input('q', ''));
        $headerId = $request->input('header_id') ? (int) $request->input('header_id') : null;

        $query = Product::query()->active();

        if ($headerId) {
            $header = ShopLedgerHeaderGroup::with('allowedProducts')->find($headerId);
            if ($header && $header->allowedProducts->isNotEmpty()) {
                $allowedIds = $header->allowedProducts->pluck('id')->all();
                $query->whereIn('id', $allowedIds);
            }
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q): void {
                $sub->where('name', 'LIKE', "%{$q}%")
                    ->orWhere('sku', 'LIKE', "%{$q}%");
                if (is_numeric($q)) {
                    $sub->orWhere('id', (int) $q);
                }
            });
        }

        $products = $query->with('orderUnits')->orderBy('name')->limit(50)->get(['id', 'name', 'sku', 'unit', 'base_price'])
            ->map(function (Product $product): array {
                $units = [];
                $baseUnit = strtolower(trim((string) ($product->unit ?: 'unit')));
                $units[] = [
                    'unit' => $baseUnit,
                    'label' => strtoupper($baseUnit),
                    'conversion_to_base' => 1.0,
                    'is_base' => true,
                ];

                foreach ($product->orderUnits as $ou) {
                    $ouUnit = strtolower(trim((string) $ou->unit));
                    if ($ouUnit !== '' && ! collect($units)->contains('unit', $ouUnit)) {
                        $units[] = [
                            'unit' => $ouUnit,
                            'label' => $ou->label ?: strtoupper($ouUnit),
                            'conversion_to_base' => $ou->conversion_to_base !== null ? (float) $ou->conversion_to_base : 1.0,
                            'is_base' => (bool) $ou->is_base,
                        ];
                    }
                }

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'unit' => $baseUnit,
                    'base_price' => (float) ($product->base_price ?? 0),
                    'units' => $units,
                ];
            });

        return response()->json([
            'success' => true,
            'products' => $products,
        ]);
    }

    public function cashbookBulkRecordEntries(Request $request): JsonResponse
    {
        $shop = $this->ownedAccountingShop($request);
        $validated = $request->validate([
            'business_date' => ['required', 'date_format:Y-m-d'],
            'header_group_id' => ['nullable', 'integer'],
            'entries' => ['nullable', 'array'],
            'entries.*.entry_type_code' => ['required', 'string', 'exists:ledger_entry_types,code'],
            'entries.*.amount' => ['required', 'numeric', 'min:0'],
            'entries.*.funding_source' => ['nullable', 'string', 'in:sales,petty,company,bank,external,company_later,none'],
            'entries.*.notes' => ['nullable', 'string', 'max:255'],
            'product_rows' => ['nullable', 'array'],
            'product_rows.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'product_rows.*.quantity' => ['required', 'numeric', 'min:0'],
            'product_rows.*.unit' => ['required', 'string', 'max:40'],
            'product_rows.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        if (empty($validated['entries']) && ! $request->filled('header_group_id') && ! $request->has('product_rows')) {
            return response()->json([
                'success' => false,
                'message' => 'The entries field is required when no header group is specified.',
                'errors' => ['entries' => ['The entries field is required.']],
            ], 422);
        }

        $headerGroup = null;
        if ($request->filled('header_group_id')) {
            $headerGroupId = (int) $request->input('header_group_id');
            $headerGroup = ShopLedgerHeaderGroup::with('allowedProducts')->find($headerGroupId);

            if (! $headerGroup || (int) $headerGroup->shop_id !== (int) $shop->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected header group does not belong to this shop.',
                ], 403);
            }

            if (! $headerGroup->enabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected header group is disabled.',
                ], 422);
            }

            $submittedProductRows = $validated['product_rows'] ?? null;
            if (! empty($submittedProductRows) && ! $headerGroup->product_tagging_enabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product tagging is not enabled for this header group.',
                ], 422);
            }

            if (! empty($submittedProductRows) && $headerGroup->allowedProducts->isNotEmpty()) {
                $allowedIds = $headerGroup->allowedProducts->pluck('id')->map(fn ($id) => (int) $id)->all();
                foreach ($submittedProductRows as $pRow) {
                    if (! in_array((int) $pRow['product_id'], $allowedIds, true)) {
                        return response()->json([
                            'success' => false,
                            'message' => "Product #{$pRow['product_id']} is not allowed for this header group.",
                        ], 422);
                    }
                }
            }
        } elseif (! empty($validated['product_rows'])) {
            return response()->json([
                'success' => false,
                'message' => 'header_group_id is required when product rows are provided.',
            ], 422);
        }

        $created = [];
        $userId = (int) ($request->user()?->id ?? 1);

        $entries = $validated['entries'] ?? [];
        foreach ($entries as $checkItem) {
            $this->ensurePettyExpensesAllowed((int) $shop->id, $checkItem['funding_source'] ?? null);
        }
        if (! empty($entries)) {
            $submittedCodes = array_column($entries, 'entry_type_code');
            $entryTypes = LedgerEntryType::query()
                ->whereIn('code', $submittedCodes)
                ->get()
                ->keyBy('code');

            $shopSettings = ShopLedgerEntrySetting::query()
                ->where('shop_id', (int) $shop->id)
                ->whereIn('entry_type_id', $entryTypes->pluck('id')->all())
                ->get();

            $headerId = ! empty($validated['header_group_id']) ? (int) $validated['header_group_id'] : null;

            foreach ($entries as $item) {
                $code = (string) $item['entry_type_code'];
                if (in_array($code, ['gl_bill', 'purchase_bill'], true)) {
                    continue;
                }

                $entryType = $entryTypes->get($code);
                if (! $entryType) {
                    continue;
                }

                $setting = $shopSettings->first(function ($s) use ($entryType, $headerId) {
                    if ((int) $s->entry_type_id !== (int) $entryType->id) {
                        return false;
                    }

                    return $headerId ? (int) $s->header_group_id === $headerId : true;
                }) ?? $shopSettings->firstWhere('entry_type_id', $entryType->id);
                $isStaffCode = in_array(strtolower($code), ['salary', 'staff_advance', 'advance'], true);
                $isReadonly = (bool) ($setting?->is_readonly);

                if ($isReadonly || $isStaffCode) {
                    return response()->json([
                        'success' => false,
                        'message' => "Manual cashbook entry for {$entryType->name} is prohibited. Salary payments and staff advances must be created and updated only from the Staff section.",
                        'errors' => [
                            'entries' => ["Manual entry for {$entryType->name} is not allowed from Cashbook."],
                        ],
                    ], 422);
                }

                if ($setting?->isTodayOnly()) {
                    $activeDate = app(DailyLedgerService::class)->resolveActiveBusinessDate((int) $shop->id);
                    if ($validated['business_date'] !== $activeDate) {
                        return response()->json([
                            'success' => false,
                            'message' => "Category '{$entryType->name}' is restricted to today's active business day ({$activeDate}) and cannot be modified for {$validated['business_date']}.",
                            'errors' => [
                                'entries' => ["Category '{$entryType->name}' cannot be modified for past business dates."],
                            ],
                        ], 422);
                    }
                }
            }
        }

        DB::transaction(function () use ($shop, $validated, $userId, $headerGroup, &$created, $entries): void {
            if (! empty($entries)) {
                $entryTypes = LedgerEntryType::query()
                    ->whereIn('code', array_column($entries, 'entry_type_code'))
                    ->get()
                    ->keyBy('code');

                $existingTxs = ShopLedgerTransaction::query()
                    ->where('shop_id', (int) $shop->id)
                    ->where('business_date', $validated['business_date'])
                    ->where('generated_by_rule', false)
                    ->whereNull('reference_type')
                    ->get();

                $existingTxMap = [];
                foreach ($existingTxs as $t) {
                    $fs = $t->funding_source ?: 'sales';
                    $existingTxMap[(int) $t->entry_type_id.'_'.$fs] = $t;
                    if (! isset($existingTxMap[(int) $t->entry_type_id])) {
                        $existingTxMap[(int) $t->entry_type_id] = $t;
                    }
                }

                foreach ($entries as $item) {
                    $code = (string) $item['entry_type_code'];
                    // Skip automated invoice bill entry codes if any
                    if (in_array($code, ['gl_bill', 'purchase_bill'], true)) {
                        continue;
                    }

                    $entryType = $entryTypes->get($code);
                    if (! $entryType) {
                        continue;
                    }

                    $amount = (float) $item['amount'];
                    $fundingSource = (! empty($item['funding_source']) && $item['funding_source'] !== 'none') ? (string) $item['funding_source'] : null;
                    $notes = $item['notes'] ?? null;

                    $txKey = (int) $entryType->id.'_'.($fundingSource ?: 'sales');
                    $existingTx = $existingTxMap[$txKey] ?? null;
                    if (! $existingTx && ! $fundingSource) {
                        $existingTx = $existingTxMap[(int) $entryType->id] ?? null;
                    }

                    if ($existingTx) {
                        if (! $existingTx->isReconciled()) {
                            if ($amount > 0) {
                                $result = $this->dailyLedgerService->updateEntry(
                                    $existingTx->id,
                                    $amount,
                                    $fundingSource,
                                    $notes,
                                    $userId
                                );
                                if (! empty($result['transaction'])) {
                                    $created[] = $result['transaction'];
                                }
                            } else {
                                $this->dailyLedgerService->deleteEntry($existingTx->id);
                            }
                        }
                    } else {
                        if ($amount > 0) {
                            $payload = [
                                'shop_id' => (int) $shop->id,
                                'business_date' => $validated['business_date'],
                                'entry_type_code' => $code,
                                'amount' => $amount,
                                'entered_by' => $userId,
                                'notes' => $notes,
                            ];
                            if ($fundingSource) {
                                $payload['funding_source'] = $fundingSource;
                            }
                            $result = $this->dailyLedgerService->recordEntry($payload);
                            if (! empty($result['transaction'])) {
                                $created[] = $result['transaction'];
                            }
                        }
                    }
                }
            }

            if ($headerGroup && $headerGroup->product_tagging_enabled && array_key_exists('product_rows', $validated)) {
                $submittedProductRows = collect($validated['product_rows'] ?? []);
                $submittedProductIds = $submittedProductRows->pluck('product_id')->map(fn ($id) => (int) $id)->all();

                ShopLedgerProductEntry::query()
                    ->where('shop_id', (int) $shop->id)
                    ->where('business_date', $validated['business_date'])
                    ->where('header_group_id', (int) $headerGroup->id)
                    ->whereNotIn('product_id', $submittedProductIds)
                    ->delete();

                if ($submittedProductRows->isNotEmpty()) {
                    $products = Product::query()
                        ->whereIn('id', $submittedProductIds)
                        ->get()
                        ->keyBy('id');

                    foreach ($submittedProductRows as $row) {
                        $productId = (int) $row['product_id'];
                        $product = $products->get($productId);

                        ShopLedgerProductEntry::query()->updateOrCreate(
                            [
                                'shop_id' => (int) $shop->id,
                                'business_date' => $validated['business_date'],
                                'header_group_id' => (int) $headerGroup->id,
                                'product_id' => $productId,
                            ],
                            [
                                'product_name' => $product?->name ?? (string) ($row['product_name'] ?? 'Product'),
                                'product_sku' => $product?->sku ?? ($row['product_sku'] ?? ($row['sku'] ?? null)),
                                'quantity' => (float) ($row['quantity'] ?? 0),
                                'unit' => (string) ($row['unit'] ?? ($product?->unit ?: 'unit')),
                                'amount' => (float) ($row['amount'] ?? 0),
                                'entered_by' => $userId,
                            ]
                        );
                    }
                }
            }
        });

        $snapshot = $this->dailyLedgerService->dailySummary((int) $shop->id, $validated['business_date']);

        $savedProductEntries = ShopLedgerProductEntry::query()
            ->with(['product.orderUnits'])
            ->where('shop_id', (int) $shop->id)
            ->where('business_date', $validated['business_date'])
            ->get();

        $productRowsMap = $this->formatProductRowsByHeader($savedProductEntries);

        return response()->json([
            'success' => true,
            'message' => count($created).' entries saved successfully.',
            'count' => count($created),
            'snapshot' => $snapshot,
            'product_rows' => $productRowsMap,
            'productRows' => $productRowsMap,
        ]);
    }

    public function cashbookRecordEntry(Request $request): JsonResponse
    {
        $shop = $this->ownedAccountingShop($request);
        $validated = $request->validate([
            'business_date' => ['required', 'date_format:Y-m-d'],
            'entry_type_code' => ['required_without:collection_group_id', 'nullable', 'string', 'exists:ledger_entry_types,code'],
            'amount' => ['required_without:collection_group_id', 'nullable', 'numeric', 'min:0.01'],
            'funding_source' => ['nullable', 'string', 'in:sales,petty,company,bank,external,company_later,none'],
            'notes' => ['nullable', 'string', 'max:255'],
            'collection_group_id' => ['nullable', 'integer', 'exists:shop_ledger_collection_groups,id'],
            'collection_lines' => ['nullable', 'array'],
            'collection_lines.*.entry_type_id' => ['required_with:collection_lines', 'integer', 'exists:ledger_entry_types,id'],
            'collection_lines.*.amount' => ['required_with:collection_lines', 'numeric', 'min:0'],
        ]);

        try {
            if (! empty($validated['collection_group_id'])) {
                $result = $this->collectionGroupPostingService->record(
                    (int) $shop->id,
                    $validated['business_date'],
                    (int) $validated['collection_group_id'],
                    collect($validated['collection_lines'] ?? [])->mapWithKeys(
                        fn (array $line): array => [(int) $line['entry_type_id'] => (float) $line['amount']]
                    )->all(),
                    (int) ($request->user()?->id ?? 1),
                    $validated['notes'] ?? null,
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Collection created successfully.',
                    'transactions' => $result['transactions'],
                    'snapshot' => $result['snapshot'],
                ]);
            }

            $this->ensurePettyExpensesAllowed((int) $shop->id, $validated['funding_source'] ?? null);

            $payload = [
                'shop_id' => (int) $shop->id,
                'business_date' => $validated['business_date'],
                'entry_type_code' => $validated['entry_type_code'],
                'amount' => (float) $validated['amount'],
                'entered_by' => (int) ($request->user()?->id ?? 1),
                'notes' => $validated['notes'] ?? null,
            ];

            if (! empty($validated['funding_source']) && $validated['funding_source'] !== 'none') {
                $payload['funding_source'] = $validated['funding_source'];
            }

            $result = $this->dailyLedgerService->recordEntry($payload);

            return response()->json([
                'success' => true,
                'message' => 'Entry created successfully.',
                'transaction' => $result['transaction']->load('entryType'),
                'snapshot' => $result['snapshot'],
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->validator->errors()->first() ?: $exception->getMessage(),
                'errors' => $exception->validator->errors(),
            ], 422);
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function cashbookUpdateEntry(Request $request): JsonResponse
    {
        $shop = $this->ownedAccountingShop($request);
        $validated = $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:shop_ledger_transactions,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'funding_source' => ['nullable', 'string', 'in:sales,petty,company,bank,external,company_later,none'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $transaction = ShopLedgerTransaction::query()
                ->where('shop_id', (int) $shop->id)
                ->with('entryType')
                ->findOrFail((int) $validated['transaction_id']);

            if (! $transaction->canBeEditedByShopOwner()) {
                throw ValidationException::withMessages([
                    'transaction_id' => 'Approved or auto-synced invoice entries cannot be changed from shop cashbook.',
                ]);
            }

            $fundingSource = array_key_exists('funding_source', $validated) ? $validated['funding_source'] : null;
            $notes = array_key_exists('notes', $validated) ? $validated['notes'] : null;

            $this->ensurePettyExpensesAllowed((int) $shop->id, $fundingSource);

            $result = $this->dailyLedgerService->updateEntry(
                (int) $transaction->id,
                (float) $validated['amount'],
                $fundingSource,
                $notes,
                (int) ($request->user()?->id ?? 1)
            );

            return response()->json([
                'success' => true,
                'message' => 'Entry updated successfully.',
                'transaction' => $result['transaction']->load('entryType'),
                'snapshot' => $result['snapshot'],
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->validator->errors()->first() ?: $exception->getMessage(),
                'errors' => $exception->validator->errors(),
            ], 422);
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function cashbookDeleteEntry(Request $request): JsonResponse
    {
        $shop = $this->ownedAccountingShop($request);
        $validated = $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:shop_ledger_transactions,id'],
        ]);

        try {
            $transaction = ShopLedgerTransaction::query()
                ->where('shop_id', (int) $shop->id)
                ->findOrFail((int) $validated['transaction_id']);

            if (! $transaction->canBeEditedByShopOwner()) {
                throw ValidationException::withMessages([
                    'transaction_id' => 'Approved or auto-synced invoice entries cannot be changed from shop cashbook.',
                ]);
            }

            $result = $this->dailyLedgerService->deleteEntry((int) $transaction->id);

            return response()->json([
                'success' => true,
                'message' => 'Entry deleted successfully.',
                'snapshot' => $result['snapshot'],
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function cashbookDeleteCollection(Request $request): JsonResponse
    {
        $shop = $this->ownedAccountingShop($request);
        $validated = $request->validate([
            'reference_id' => ['required', 'integer'],
        ]);

        try {
            $result = $this->collectionGroupPostingService->deleteCollectionGroup(
                (int) $shop->id,
                (int) $validated['reference_id']
            );

            return response()->json([
                'success' => true,
                'message' => 'Collection deleted successfully.',
                'snapshot' => $result['snapshot'],
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    /**
     * @return Collection<int, array{date: Carbon, opening_balance: float, closing_balance: float, net_difference: float}>
     */
    private function shopDailyBalanceRows(Shop $shop, Carbon $month): Collection
    {
        $startDate = $month->copy()->startOfMonth();
        $endDate = $month->copy()->endOfMonth();
        $entriesByDate = ShopAccountingEntry::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->with('lines')
            ->orderBy('business_date')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (ShopAccountingEntry $entry): string => $entry->business_date->toDateString());
        $activityDates = $entriesByDate
            ->keys()
            ->merge(ShopCredit::query()
                ->approved()
                ->where('shop_id', $shop->id)
                ->whereDate('business_date', '>=', $startDate)
                ->whereDate('business_date', '<=', $endDate)
                ->pluck('business_date')
                ->map(fn ($businessDate): string => Carbon::parse($businessDate)->toDateString()))
            ->merge(ShopInvoice::query()
                ->where('shop_id', $shop->id)
                ->where('final_total', '>', 0)
                ->where(function ($query): void {
                    $query
                        ->whereIn('delivery_status', ['received_full', 'approved_after_discrepancy'])
                        ->orWhereIn('status', ['finalized', 'payment_pending', 'paid'])
                        ->orWhereIn('payment_status', ['partially_paid', 'paid']);
                })
                ->whereDate('business_date', '>=', $startDate)
                ->whereDate('business_date', '<=', $endDate)
                ->pluck('business_date')
                ->map(fn ($businessDate): string => Carbon::parse($businessDate)->toDateString()))
            ->unique();
        $allPreviousActivityDates = ShopAccountingEntry::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', '<', $startDate)
            ->pluck('business_date')
            ->map(fn ($businessDate): string => Carbon::parse($businessDate)->toDateString())
            ->merge(ShopCredit::query()
                ->approved()
                ->where('shop_id', $shop->id)
                ->whereDate('business_date', '<', $startDate)
                ->pluck('business_date')
                ->map(fn ($businessDate): string => Carbon::parse($businessDate)->toDateString()))
            ->merge(ShopInvoice::query()
                ->where('shop_id', $shop->id)
                ->where('final_total', '>', 0)
                ->where(function ($query): void {
                    $query
                        ->whereIn('delivery_status', ['received_full', 'approved_after_discrepancy'])
                        ->orWhereIn('status', ['finalized', 'payment_pending', 'paid'])
                        ->orWhereIn('payment_status', ['partially_paid', 'paid']);
                })
                ->whereDate('business_date', '<', $startDate)
                ->pluck('business_date')
                ->map(fn ($businessDate): string => Carbon::parse($businessDate)->toDateString()))
            ->unique();
        $rows = collect();
        $runningCash = 0.0;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateKey = $date->toDateString();
            $dayEntries = $entriesByDate->get($dateKey, collect());

            $dailyIncome = 0.0;
            $dailyExpenses = 0.0;
            $loanTotal = 0.0;
            foreach ($dayEntries as $entry) {
                $dailyIncome += (float) $entry->lines
                    ->where('type', 'income')
                    ->sum('amount');
                $dailyExpenses += (float) $entry->lines
                    ->where('type', 'expense')
                    ->where('is_loan_entry', false)
                    ->sum('amount');
                $loanTotal += (float) $entry->lines
                    ->where('type', 'expense')
                    ->where('is_loan_entry', true)
                    ->sum('amount');
            }

            $openingBalance = $runningCash;
            $closingBalance = $openingBalance + $dailyIncome - $dailyExpenses;
            $runningCash = $closingBalance;

            $rows->push([
                'date' => $date->copy(),
                'opening_balance' => $openingBalance,
                'closing_balance' => $closingBalance,
                'net_difference' => round($closingBalance - $openingBalance, 2),
                'daily_income' => $dailyIncome,
                'daily_expenses' => $dailyExpenses,
                'loan_total' => $loanTotal,
            ]);
        }

        return $rows;
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param  Collection<TKey, TValue>  $items
     * @return LengthAwarePaginator<int, TValue>
     */
    private function paginateCollection(Collection $items, Request $request, string $pageName, int $perPage): LengthAwarePaginator
    {
        $page = max(1, $request->integer($pageName, 1));

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => $pageName,
                'query' => $request->query(),
            ],
        );
    }

    /**
     * @return array{
     *     totals: array<string, float>,
     *     transactions: LengthAwarePaginator<int, array{date:string, label:string, detail:string, direction:string, amount:float, status:string, source:string}>
     * }
     */
    private function shopAccountingMoneyReport(Shop $shop, Request $request, ?Carbon $filterStartDate = null, ?Carbon $filterEndDate = null): array
    {
        $invoices = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->when($filterStartDate, fn ($query) => $query->whereDate('business_date', '>=', $filterStartDate))
            ->when($filterEndDate, fn ($query) => $query->whereDate('business_date', '<=', $filterEndDate))
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->get();
        $paymentRequests = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shop->id)
            ->when($filterStartDate, fn ($query) => $query->whereDate('created_at', '>=', $filterStartDate))
            ->when($filterEndDate, fn ($query) => $query->whereDate('created_at', '<=', $filterEndDate))
            ->with('invoice')
            ->orderByDesc('id')
            ->get();
        $shopCredits = ShopCredit::query()
            ->where('shop_id', $shop->id)
            ->when($filterStartDate, fn ($query) => $query->whereDate('business_date', '>=', $filterStartDate))
            ->when($filterEndDate, fn ($query) => $query->whereDate('business_date', '<=', $filterEndDate))
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->get()
            ->reject(fn (ShopCredit $credit): bool => Str::contains(
                strtolower((string) $credit->description),
                ['carry_over', 'carry-over', 'carryover', 'carry over']
            ));
        $accountingEntries = ShopAccountingEntry::query()
            ->where('shop_id', $shop->id)
            ->when($filterStartDate, fn ($query) => $query->whereDate('business_date', '>=', $filterStartDate))
            ->when($filterEndDate, fn ($query) => $query->whereDate('business_date', '<=', $filterEndDate))
            ->with(['lines.category'])
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->get();

        $approvedEntries = $accountingEntries->where('status', 'approved');
        $cashbookIncome = round((float) $approvedEntries->sum(
            fn (ShopAccountingEntry $entry): float => (float) $entry->lines->where('type', 'income')->sum('amount')
        ), 2);
        $cashbookExpense = round((float) $approvedEntries->sum(
            fn (ShopAccountingEntry $entry): float => (float) $entry->lines->where('type', 'expense')->sum('amount')
        ), 2);
        $approvedShopCredits = $shopCredits->where('status', 'approved');
        $shopCashIn = round((float) $approvedShopCredits->sum(
            fn (ShopCredit $credit): float => max(0.0, $credit->shopSignedAmount())
        ), 2);
        $shopCashOut = round((float) $approvedShopCredits->sum(
            fn (ShopCredit $credit): float => abs(min(0.0, $credit->shopSignedAmount()))
        ), 2);
        $billTotal = round((float) $invoices->sum('final_total'), 2);
        $billPaid = round((float) $invoices->sum('paid_amount'), 2);

        $transactions = collect()
            ->merge($invoices->map(fn (ShopInvoice $invoice): array => [
                'date' => $invoice->business_date?->toDateString() ?? $invoice->created_at?->toDateString() ?? now()->toDateString(),
                'label' => 'Cash Bill',
                'detail' => (string) $invoice->invoice_number,
                'direction' => 'OUT',
                'amount' => round((float) $invoice->final_total, 2),
                'status' => str((string) $invoice->payment_status)->replace('_', ' ')->title()->toString(),
                'source' => 'bill',
            ]))
            ->merge($paymentRequests->where('status', 'approved')->map(fn (ShopInvoicePaymentRequest $paymentRequest): array => [
                'date' => $paymentRequest->reviewed_at?->toDateString() ?? $paymentRequest->created_at?->toDateString() ?? now()->toDateString(),
                'label' => 'Bill Payment',
                'detail' => (string) ($paymentRequest->invoice?->invoice_number ?? 'Payment approved'),
                'direction' => 'OUT',
                'amount' => round((float) ($paymentRequest->approved_amount ?? $paymentRequest->requested_amount), 2),
                'status' => $paymentRequest->statusLabel(),
                'source' => 'bill_payment',
            ]))
            ->merge($shopCredits->map(fn (ShopCredit $credit): array => [
                'date' => $credit->business_date?->toDateString() ?? $credit->created_at?->toDateString() ?? now()->toDateString(),
                'label' => $credit->shopCashLabel(),
                'detail' => (string) ($credit->description ?: 'Shop cash movement'),
                'direction' => $credit->shopSignedAmount() >= 0 ? 'IN' : 'OUT',
                'amount' => round(abs($credit->shopSignedAmount()), 2),
                'status' => $credit->statusLabel(),
                'source' => 'shop_cash',
            ]))
            ->merge($accountingEntries->flatMap(fn (ShopAccountingEntry $entry): Collection => $entry->lines->map(fn ($line): array => [
                'date' => $entry->business_date?->toDateString() ?? $entry->created_at?->toDateString() ?? now()->toDateString(),
                'label' => (string) ($line->category?->name ?? str((string) $line->type)->title()),
                'detail' => (string) ($line->description ?: 'Cashbook line'),
                'direction' => $line->type === 'income' ? 'IN' : 'OUT',
                'amount' => round((float) $line->amount, 2),
                'status' => $entry->statusLabel(),
                'source' => 'cashbook',
            ])))
            ->sortByDesc(fn (array $transaction): string => $transaction['date'].'|'.str_pad((string) (int) round($transaction['amount'] * 100), 12, '0', STR_PAD_LEFT))
            ->values();

        $combinedIn = round($cashbookIncome + $shopCashIn, 2);
        $combinedOut = round($billTotal + $cashbookExpense + $shopCashOut, 2);

        return [
            'totals' => [
                'bill_total' => $billTotal,
                'bill_paid' => $billPaid,
                'bill_due' => round((float) app(ShopFinalBillService::class)->calculateMany($invoices->loadMissing('creditNotes'))->sum('remainingBalance'), 2),
                'shop_cash_in' => $shopCashIn,
                'shop_cash_out' => $shopCashOut,
                'cashbook_income' => $cashbookIncome,
                'cashbook_expense' => $cashbookExpense,
                'cashbook_net' => round($cashbookIncome - $cashbookExpense, 2),
                'combined_in' => $combinedIn,
                'combined_out' => $combinedOut,
                'combined_net' => round($combinedIn - $combinedOut, 2),
            ],
            'transactions' => $this->paginateCollection($transactions, $request, 'money_report_page', 12),
        ];
    }

    public function storeAccountingEntry(StoreShopOwnerAccountingEntryRequest $request): RedirectResponse
    {
        $shop = $this->currentShop($request);
        $date = Carbon::parse((string) $request->input('business_date', today()->toDateString()))->toDateString();

        return $this->redirectToNewCashbook($shop, 'post-entry', ['date' => $date, 'open' => 'line'])
            ->with('warning', 'Legacy cashbook form was removed. Use the new cashbook create flow.');
    }

    public function storePaymentRequest(StoreShopInvoicePaymentRequest $request): RedirectResponse
    {
        $user = $this->shopUser($request);
        $shop = $this->currentShop($request);
        $validated = $request->validated();

        if (($validated['amount_mode'] ?? null) === 'shop_balance') {
            if (! $shop->isOwnedAccountingEnabled()) {
                abort(403);
            }

            $latestBalanceDate = $this->latestShopBalanceDate($shop);
            $closingBalance = $this->ownedShopAccountingService->closingBalanceForDate($shop, $latestBalanceDate);

            try {
                $this->shopInvoiceService->requestShopBalancePayment(
                    $shop,
                    $latestBalanceDate,
                    $closingBalance,
                    $validated,
                    (int) $user->id,
                );
            } catch (ValidationException $exception) {
                return back()->withErrors($exception->errors())->withInput();
            }

            return redirect()->route('shop-owner.payments.index')
                ->with('success', 'Payment request submitted for admin approval.');
        }

        if (filled($validated['invoice_id'] ?? null)) {
            $invoice = ShopInvoice::query()
                ->where('shop_id', $shop->id)
                ->whereKey((int) $validated['invoice_id'])
                ->firstOrFail();
        } else {
            $invoice = ShopInvoice::query()
                ->where('shop_id', $shop->id)
                ->where('balance_amount', '>', 0)
                ->with(['creditNotes'])
                ->oldest('business_date')
                ->oldest('id')
                ->get()
                ->first(fn (ShopInvoice $inv): bool => $inv->effectiveBalanceAmount() > 0.0001);

            if (! $invoice) {
                abort(404, 'No pending payable invoice found.');
            }
        }

        try {
            $this->shopInvoiceService->requestPayment(
                $invoice,
                $validated,
                (int) $user->id,
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        } catch (Throwable $exception) {
            return back()->withErrors(['amount' => $exception->getMessage()])->withInput();
        }

        $fallbackUrl = route('shop-owner.payments.index');
        $redirectUrl = url()->previous();

        if ($redirectUrl === url()->current()) {
            $redirectUrl = $fallbackUrl;
        }

        return redirect()->to($redirectUrl)
            ->with('success', 'Payment request submitted for company reconciliation.');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboardData(Shop $shop): array
    {
        $businessDate = today();
        $isOwnedAccountingShop = $shop->isOwnedAccountingEnabled();
        $recentOrders = $this->shopOrdersQuery($shop)->latest('business_date')->limit(8)->get();
        $deliveredOrders = $recentOrders->where('is_delivered', true);
        $allInvoices = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->with(['creditNotes'])
            ->get();
        $todayInvoices = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', $businessDate->toDateString())
            ->with(['order', 'items.product', 'creditNotes'])
            ->latest('id')
            ->get();
        $recentInvoices = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->with(['order', 'items.product', 'creditNotes'])
            ->latest('business_date')
            ->latest('id')
            ->limit(8)
            ->get();
        $pendingDeliveries = $recentOrders->filter(
            fn (ShopOrder $order): bool => $order->is_allocation_completed && ! $order->is_delivered
        );
        $todayBillingSummary = $this->billingSummary($todayInvoices);
        $billingSummary = $this->billingSummary($allInvoices);
        $receiptSummary = $isOwnedAccountingShop
            ? $this->ownedShopAccountingService->receiptSummaryForDate($shop, $businessDate)
            : $this->ownedShopAccountingService->receiptSummary(null);
        $pendingBillApprovalSummary = $isOwnedAccountingShop
            ? $this->ownedShopAccountingService->pendingDeliveryBillApprovalSummary($shop)
            : ['count' => 0, 'amount' => 0.0];
        $latestBalanceDate = $isOwnedAccountingShop ? $this->latestShopBalanceDate($shop) : $businessDate;
        $latestClosingBalance = $isOwnedAccountingShop
            ? $this->ownedShopAccountingService->closingBalanceForDate($shop, $latestBalanceDate)
            : 0.0;

        return [
            'shop' => $shop,
            'isOwnedAccountingShop' => $isOwnedAccountingShop,
            'businessDate' => $businessDate,
            'stats' => [
                'pending_approval_count' => $recentOrders->whereIn('state', ['submitted', 'update_requested'])->count(),
                'pending_delivery_count' => $pendingDeliveries->count(),
                'delivered_orders_count' => $deliveredOrders->count(),
                'gross_billed' => (float) $billingSummary['gross_billed'],
                'outstanding_balance' => (float) $billingSummary['total_balance'],
                'today_bill_total' => (float) $todayBillingSummary['total_billed'],
                'today_bill_count' => $todayInvoices->count(),
                'today_approved_bill_debit' => (float) $receiptSummary['approved_delivery_bill'],
                'today_closing_balance' => (float) ($receiptSummary['entered_closing'] ?? $receiptSummary['expected_closing']),
                'pending_bill_approval_count' => $isOwnedAccountingShop
                    ? $pendingBillApprovalSummary['count']
                    : $billingSummary['open_bills'],
                'pending_bill_approval_amount' => $isOwnedAccountingShop
                    ? $pendingBillApprovalSummary['amount']
                    : $billingSummary['total_balance'],
                'overpayment_credit' => (float) $billingSummary['overpayment_credit'],
            ],
            'todayOrder' => $this->todayOrder($shop),
            'tomorrowOrder' => $this->tomorrowOrder($shop),
            'pendingDeliveries' => $pendingDeliveries,
            'recentOrders' => $recentOrders,
            'recentInvoices' => $recentInvoices,
            'todayInvoices' => $todayInvoices,
            'financeSummary' => [
                'gross_billed' => (float) $billingSummary['gross_billed'],
                'total_billed' => (float) $billingSummary['total_billed'],
                'total_final_billed' => (float) $billingSummary['total_final_billed'],
                'total_credits' => (float) $billingSummary['total_credits'],
                'total_debits' => (float) $billingSummary['total_debits'],
                'paid_amount' => (float) $billingSummary['total_paid'],
                'shortage_value' => (float) $allInvoices->sum(fn (ShopInvoice $invoice): float => (float) $invoice->shortage_total),
                'outstanding_balance' => (float) $billingSummary['total_balance'],
                'overpayment_credit' => (float) $billingSummary['overpayment_credit'],
                'today_bill_total' => (float) $todayBillingSummary['total_billed'],
                'today_approved_bill_debit' => (float) $receiptSummary['approved_delivery_bill'],
                'today_closing_balance' => (float) ($receiptSummary['entered_closing'] ?? $receiptSummary['expected_closing']),
                'pending_bill_approval_amount' => (float) $pendingBillApprovalSummary['amount'],
                'pending_bill_approval_count' => (int) $pendingBillApprovalSummary['count'],
                'latest_balance_date' => $latestBalanceDate,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOrderFormData(Shop $shop): array
    {
        $tomorrowDate = Carbon::tomorrow();

        $productsByCategory = Category::with(['products' => function ($query): void {
            $query->where('is_active', true)->where('show_in_purchaser_order', true)->with(['orderUnits' => fn ($q) => $q->where('is_orderable', true)])->ordered();
        }])
            ->where('is_active', true)
            ->get()
            ->filter(fn (Category $category): bool => $category->products->isNotEmpty());

        $gradeConfig = $this->multiGradeOrderGate->resolveGradeConfiguration($shop);
        $defaultGradeEnum = ProductGrade::tryResolve($gradeConfig['default']) ?? ProductGrade::GradeA;

        $productsByCategory->each(function (Category $category) use ($shop, $defaultGradeEnum): void {
            $category->products->each(function ($product) use ($shop, $defaultGradeEnum): void {
                $price = $this->priceBoardService->sellingPriceFor($product, $shop, $defaultGradeEnum);
                $product->setAttribute('effective_price', $price['price']);
            });
        });

        $frequentProducts = $this->frequentProducts($shop);
        $frequentProducts->each(function (array $item) use ($shop, $defaultGradeEnum): void {
            $product = $item['product'];
            $price = $this->priceBoardService->sellingPriceFor($product, $shop, $defaultGradeEnum);
            $product->setAttribute('effective_price', $price['price']);
        });

        $tomorrowOrder = $this->tomorrowOrder($shop);

        return [
            'productsByCategory' => $productsByCategory,
            'frequentProducts' => $frequentProducts,
            'presets' => ShopPreset::where('shop_id', $shop->id)->with('items.product')->get(),
            'yesterdayOrder' => $this->yesterdayOrder($shop),
            'tomorrowOrder' => $tomorrowOrder,
            'tomorrowDate' => $tomorrowDate,
            'cutoffPassed' => $this->businessDayService->hasRolledOver(),
            'cutoffLabel' => $this->businessDayService->cutoffLabel(),
            'purchaseOrdersLockedForTomorrow' => $tomorrowOrder?->linkedPurchaseOrdersHaveGoodsReceived() ?? false,
            'multiGradeOrderingEnabled' => (bool) ($gradeConfig['multi_grade_ordering_enabled'] ?? false),
            'allowedProductGrades' => $gradeConfig['allowed'],
            'defaultProductGrade' => $gradeConfig['default'],
            'orderedProductGrades' => $gradeConfig['ordered'],
        ];
    }

    private function shopUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->hasRole('shop') && $this->activeShopResolver->authorizedShops($user)->isNotEmpty(), 403);

        return $user;
    }

    private function shopOrderByNumber(Request $request, string $orderNumber): ShopOrder
    {
        $user = $this->shopUser($request);
        $shop = $this->currentShop($request);

        return $this->shopOrdersQuery($shop)
            ->where('order_number', $orderNumber)
            ->firstOrFail();
    }

    private function todayOrder(Shop $shop): ?ShopOrder
    {
        return $this->shopOrdersQuery($shop)
            ->whereDate('business_date', today())
            ->first();
    }

    private function tomorrowOrder(Shop $shop): ?ShopOrder
    {
        return $this->shopOrdersQuery($shop)
            ->whereDate('business_date', Carbon::tomorrow())
            ->first();
    }

    private function yesterdayOrder(Shop $shop): ?ShopOrder
    {
        $yesterdayOrder = $this->shopOrdersQuery($shop)
            ->whereDate('business_date', today()->subDay())
            ->first();

        if ($yesterdayOrder) {
            return $yesterdayOrder;
        }

        return $this->shopOrdersQuery($shop)
            ->whereDate('business_date', '<', today())
            ->latest('business_date')
            ->first();
    }

    private function shopOrdersQuery(Shop $shop)
    {
        return ShopOrder::query()
            ->where('shop_id', $shop->id)
            ->with([
                'shop',
                'items.product',
                'deliveredBy',
                'creator',
                'reviewedBy',
                'latestResolvedRevision.items.product.orderUnits',
                'latestResolvedRevision.reviewedBy',
                'revisions.items.product.orderUnits',
                'revisions.reviewedBy',
            ]);
    }

    private function frequentProducts(Shop $shop)
    {
        $historicalOrders = $this->shopOrdersQuery($shop)
            ->whereDate('business_date', '<', Carbon::tomorrow())
            ->latest('business_date')
            ->limit(20)
            ->get();

        $productStats = [];

        foreach ($historicalOrders as $order) {
            foreach ($order->items as $item) {
                if (! $item->product) {
                    continue;
                }

                if (! isset($productStats[$item->product_id])) {
                    $productStats[$item->product_id] = [
                        'product' => $item->product,
                        'order_count' => 0,
                        'total_quantity' => 0.0,
                        'last_quantity' => (float) $item->requested_qty,
                    ];
                }

                $productStats[$item->product_id]['order_count']++;
                $productStats[$item->product_id]['total_quantity'] += (float) $item->requested_qty;
            }
        }

        return collect($productStats)
            ->filter(fn (array $product): bool => (bool) ($product['product']->is_active ?? false) && (bool) ($product['product']->show_in_purchaser_order ?? true))
            ->sortByDesc(fn (array $product): array => [$product['order_count'], $product['total_quantity']])
            ->take(12)
            ->values();
    }

    private function currentShop(Request $request): Shop
    {
        return $this->activeShopResolver->resolve($request);
    }

    private function ensureDeliveryInvoiceExists(ShopOrder $order, int $userId): void
    {
        if (! $order->is_allocation_completed && ! in_array($order->delivery_status, ['in_transit', 'ready_for_dispatch', 'delivered'], true)) {
            return;
        }

        try {
            $this->shopInvoiceService->synchronizeOrderInvoice($order, $userId);
        } catch (ValidationException $exception) {
            report($exception);

            return;
        }

        $order->unsetRelation('invoice');
        $order->load(['invoice.items.product', 'invoice.paymentRequests']);
    }

    private function ownedAccountingShop(Request $request): Shop
    {
        $shop = $this->currentShop($request);

        abort_unless($shop->isOwnedAccountingEnabled(), 404);

        return $shop;
    }

    private function normalizeAccountingTab(Shop $shop, string $tab): string
    {
        if ($tab === 'others') {
            $tab = 'loan';
        }

        if (in_array($tab, ['cashbook', 'create', 'loan'], true)) {
            abort_unless($shop->isOwnedAccountingEnabled(), 404);

            return $tab;
        }

        return 'bills';
    }

    private function normalizeOthersSubtab(Request $request): string
    {
        $subtab = (string) $request->input('others', 'petty');

        return in_array($subtab, ['petty', 'company'], true) ? $subtab : 'petty';
    }

    private function redirectToNewCashbook(Shop $shop, string $target, array $query = []): RedirectResponse
    {
        if ($target === 'post-entry') {
            return redirect()->route('shop-owner.cashbook.create', $query);
        }

        if ($target === 'settings') {
            return redirect()->route('shop-owner.cashbook.settings', $query);
        }

        if ($target === 'reports') {
            return redirect()->route('shop-owner.cashbook.reports', $query);
        }

        return redirect()->route('shop-owner.cashbook.show', $query);
    }

    private function latestShopBalanceDate(Shop $shop): Carbon
    {
        $entryDate = ShopAccountingEntry::query()
            ->where('shop_id', $shop->id)
            ->max('business_date');
        $creditDate = ShopCredit::query()
            ->approved()
            ->where('shop_id', $shop->id)
            ->max('business_date');
        $invoiceDate = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->where('final_total', '>', 0)
            ->where(function ($query): void {
                $query
                    ->whereIn('delivery_status', ['received_full', 'approved_after_discrepancy'])
                    ->orWhereIn('status', ['finalized', 'payment_pending', 'paid'])
                    ->orWhereIn('payment_status', ['partially_paid', 'paid']);
            })
            ->max('business_date');
        $latestDate = collect([$entryDate, $creditDate, $invoiceDate])
            ->filter()
            ->map(fn (string $date): string => Carbon::parse($date)->toDateString())
            ->sort()
            ->last();

        return Carbon::parse($latestDate ?? today()->toDateString());
    }

    /**
     * @param  Collection<int, ShopInvoice>  $invoices
     * @return array{
     *     gross_billed: float,
     *     total_billed: float,
     *     total_final_billed: float,
     *     total_paid: float,
     *     total_balance: float,
     *     total_credits: float,
     *     total_debits: float,
     *     overpayment_credit: float,
     *     open_bills: int
     * }
     */
    public function billingSummary(Collection $invoices): array
    {
        if ($invoices instanceof \Illuminate\Database\Eloquent\Collection) {
            $invoices->loadMissing('creditNotes');
        } else {
            $invoices->each(fn ($inv) => method_exists($inv, 'loadMissing') ? $inv->loadMissing('creditNotes') : null);
        }

        $bills = app(ShopFinalBillService::class)->calculateMany($invoices);

        $grossBilled = round((float) $bills->sum('originalInvoiceTotal'), 2);
        $totalFinalBilled = round((float) $bills->sum('finalNetPayable'), 2);
        $totalPaid = round((float) $bills->sum('amountPaid'), 2);
        $totalBalance = round((float) $bills->sum('remainingBalance'), 2);
        $totalCredits = round((float) $bills->sum('finalizedCreditTotal'), 2);
        $totalDebits = round((float) $bills->sum('finalizedDebitTotal'), 2);
        $overpaymentCredit = round((float) $bills->sum('overpaymentCredit'), 2);
        $openBills = $bills->filter(fn (ShopFinalBill $b): bool => $b->remainingBalance > 0.0001)->count();

        return [
            'gross_billed' => $grossBilled,
            'total_billed' => $totalFinalBilled,
            'total_final_billed' => $totalFinalBilled,
            'total_paid' => $totalPaid,
            'total_balance' => $totalBalance,
            'total_credits' => $totalCredits,
            'total_debits' => $totalDebits,
            'overpayment_credit' => $overpaymentCredit,
            'open_bills' => $openBills,
        ];
    }

    /**
     * Calculate effective pending balance for a shop using canonical final bill logic.
     */
    public function pendingBalance(Shop|Request $target): float
    {
        $shop = $target instanceof Shop ? $target : $this->currentShop($target);

        $invoices = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->with(['creditNotes'])
            ->get();

        $bills = app(ShopFinalBillService::class)->calculateMany($invoices);

        return round((float) $bills->sum('remainingBalance'), 2);
    }

    /**
     * Get detailed breakdown of pending balances and adjustment totals for a shop.
     *
     * @return array<string, float|int>
     */
    public function pendingBalanceDetails(Shop|Request $target): array
    {
        $shop = $target instanceof Shop ? $target : $this->currentShop($target);

        $invoices = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->with(['creditNotes'])
            ->get();

        $bills = app(ShopFinalBillService::class)->calculateMany($invoices);

        return [
            'gross_billed' => round((float) $bills->sum('originalInvoiceTotal'), 2),
            'final_billed' => round((float) $bills->sum('finalNetPayable'), 2),
            'paid_amount' => round((float) $bills->sum('amountPaid'), 2),
            'outstanding_balance' => round((float) $bills->sum('remainingBalance'), 2),
            'overpayment_credit' => round((float) $bills->sum('overpaymentCredit'), 2),
            'credit_adjustments' => round((float) $bills->sum('finalizedCreditTotal'), 2),
            'debit_adjustments' => round((float) $bills->sum('finalizedDebitTotal'), 2),
            'settled_invoices_count' => $bills->filter(fn (ShopFinalBill $b): bool => $b->remainingBalance <= 0.0001)->count(),
            'unsettled_invoices_count' => $bills->filter(fn (ShopFinalBill $b): bool => $b->remainingBalance > 0.0001)->count(),
        ];
    }

    /**
     * Chronological Statement of Account data containing invoices, credit/debit adjustments, and payments.
     *
     * @return array<string, mixed>
     */
    public function statementOfAccountData(Request $request, ?Shop $shop = null): array
    {
        $activeShop = $shop ?? $this->currentShop($request);

        [$filterStartDate, $filterEndDate] = $this->nullableDateRangeFromRequest($request);

        // 1. Calculate opening balance strictly before filterStartDate
        $openingBalance = 0.0;
        if ($filterStartDate) {
            $priorStartDateStr = $filterStartDate->toDateString();

            $priorInvoiced = (float) ShopInvoice::query()
                ->where('shop_id', $activeShop->id)
                ->whereDate('business_date', '<', $priorStartDateStr)
                ->sum('final_total');

            $priorCreditNotes = (float) ShopCreditNote::query()
                ->where('shop_id', $activeShop->id)
                ->where('status', 'approved')
                ->where('direction', ShopCreditNoteDirection::Credit->value)
                ->whereDate('credit_note_date', '<', $priorStartDateStr)
                ->sum('total_credit_amount');

            $priorDebitNotes = (float) ShopCreditNote::query()
                ->where('shop_id', $activeShop->id)
                ->where('status', 'approved')
                ->where('direction', ShopCreditNoteDirection::Debit->value)
                ->whereDate('credit_note_date', '<', $priorStartDateStr)
                ->sum('total_credit_amount');

            $priorRequestPayments = (float) ShopInvoicePaymentRequest::query()
                ->where('shop_id', $activeShop->id)
                ->where('status', 'approved')
                ->whereDate('payment_date', '<', $priorStartDateStr)
                ->sum('approved_amount');

            $priorDirectPayments = 0.0;
            $priorInvoicesWithPayments = ShopInvoice::query()
                ->where('shop_id', $activeShop->id)
                ->whereDate('business_date', '<', $priorStartDateStr)
                ->where('paid_amount', '>', 0)
                ->with(['paymentRequests' => fn ($q) => $q->where('status', 'approved')->whereDate('payment_date', '<', $priorStartDateStr)])
                ->get();

            foreach ($priorInvoicesWithPayments as $pInv) {
                $covered = (float) $pInv->paymentRequests->sum('approved_amount');
                if ((float) $pInv->paid_amount > $covered) {
                    $priorDirectPayments += ((float) $pInv->paid_amount - $covered);
                }
            }

            $priorPayments = round($priorRequestPayments + $priorDirectPayments, 2);

            $openingBalance = round($priorInvoiced - $priorCreditNotes + $priorDebitNotes - $priorPayments, 2);
        }

        // 2. Fetch Period Invoices
        $invoices = ShopInvoice::query()
            ->where('shop_id', $activeShop->id)
            ->when($filterStartDate, fn ($q) => $q->whereDate('business_date', '>=', $filterStartDate->toDateString()))
            ->when($filterEndDate, fn ($q) => $q->whereDate('business_date', '<=', $filterEndDate->toDateString()))
            ->with(['creditNotes', 'items.product', 'paymentRequests'])
            ->orderBy('business_date')
            ->orderBy('id')
            ->get();

        // 3. Fetch Period Credit & Debit Notes
        $creditNotes = ShopCreditNote::query()
            ->where('shop_id', $activeShop->id)
            ->where('status', 'approved')
            ->when($filterStartDate, fn ($q) => $q->whereDate('credit_note_date', '>=', $filterStartDate->toDateString()))
            ->when($filterEndDate, fn ($q) => $q->whereDate('credit_note_date', '<=', $filterEndDate->toDateString()))
            ->with(['invoice'])
            ->orderBy('credit_note_date')
            ->orderBy('id')
            ->get();

        // 4. Fetch Period Payments
        $paymentRequests = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $activeShop->id)
            ->where('status', 'approved')
            ->when($filterStartDate, fn ($q) => $q->whereDate('payment_date', '>=', $filterStartDate->toDateString()))
            ->when($filterEndDate, fn ($q) => $q->whereDate('payment_date', '<=', $filterEndDate->toDateString()))
            ->with(['invoice'])
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        // 5. Assemble and sort chronological ledger rows
        $rawEntries = collect();

        foreach ($invoices as $inv) {
            $rawEntries->push([
                'timestamp' => Carbon::parse($inv->business_date)->startOfDay()->timestamp,
                'id' => $inv->id,
                'order_tiebreaker' => 1,
                'date' => Carbon::parse($inv->business_date)->toDateString(),
                'type' => 'invoice',
                'type_label' => 'Invoice',
                'reference' => $inv->invoice_number,
                'description' => 'Delivery Invoice Charges',
                'debit' => round((float) $inv->final_total, 2),
                'credit' => 0.00,
                'url' => route('shop-owner.finance.show', $inv),
            ]);
        }

        foreach ($creditNotes as $cn) {
            $isCredit = $cn->direction === ShopCreditNoteDirection::Credit;
            $amount = round((float) ($cn->total_credit_amount ?? 0), 2);
            $reasonLabel = $cn->reason_type?->label() ?? 'Correction';
            $invRef = $cn->invoice?->invoice_number ? " (Invoice: {$cn->invoice->invoice_number})" : '';

            $rawEntries->push([
                'timestamp' => Carbon::parse($cn->credit_note_date)->midDay()->timestamp,
                'id' => $cn->id,
                'order_tiebreaker' => 2,
                'date' => Carbon::parse($cn->credit_note_date)->toDateString(),
                'type' => $isCredit ? 'credit_note' : 'debit_note',
                'type_label' => $isCredit ? 'Credit Note' : 'Debit Note',
                'reference' => $cn->credit_note_number,
                'description' => ($isCredit ? 'Credit Note' : 'Debit Note')." - {$reasonLabel}{$invRef}",
                'debit' => $isCredit ? 0.00 : $amount,
                'credit' => $isCredit ? $amount : 0.00,
                'url' => $cn->invoice ? route('shop-owner.finance.show', $cn->invoice) : null,
            ]);
        }

        $recordedPaymentRequestIds = [];
        foreach ($paymentRequests as $pr) {
            $amount = round((float) ($pr->approved_amount > 0 ? $pr->approved_amount : $pr->requested_amount), 2);
            $methodLabel = $pr->payment_method ? ' via '.strtoupper(str_replace('_', ' ', $pr->payment_method)) : '';
            $recordedPaymentRequestIds[] = $pr->id;

            $rawEntries->push([
                'timestamp' => Carbon::parse($pr->payment_date ?? $pr->created_at)->endOfDay()->timestamp,
                'id' => $pr->id,
                'order_tiebreaker' => 3,
                'date' => Carbon::parse($pr->payment_date ?? $pr->created_at)->toDateString(),
                'type' => 'payment',
                'type_label' => 'Payment',
                'reference' => $pr->payment_reference ?: "PAY-{$pr->id}",
                'description' => "Payment Received{$methodLabel}",
                'debit' => 0.00,
                'credit' => $amount,
                'url' => $pr->invoice ? route('shop-owner.finance.show', $pr->invoice) : null,
            ]);
        }

        // Account for any direct payments recorded on invoice without payment request
        foreach ($invoices as $inv) {
            $coveredPaid = (float) $inv->paymentRequests
                ->whereIn('id', $recordedPaymentRequestIds)
                ->sum(fn ($pr) => (float) ($pr->approved_amount > 0 ? $pr->approved_amount : $pr->requested_amount));

            $uncoveredPaid = round((float) $inv->paid_amount - $coveredPaid, 2);
            if ($uncoveredPaid > 0.0001) {
                $rawEntries->push([
                    'timestamp' => Carbon::parse($inv->business_date)->endOfDay()->timestamp,
                    'id' => $inv->id,
                    'order_tiebreaker' => 4,
                    'date' => Carbon::parse($inv->business_date)->toDateString(),
                    'type' => 'payment',
                    'type_label' => 'Payment',
                    'reference' => "RCP-{$inv->invoice_number}",
                    'description' => "Invoice Payment Received ({$inv->invoice_number})",
                    'debit' => 0.00,
                    'credit' => $uncoveredPaid,
                    'url' => route('shop-owner.finance.show', $inv),
                ]);
            }
        }

        // Sort chronologically: date ASC, then tiebreaker, then ID
        $sortedEntries = $rawEntries->sortBy([
            ['date', 'asc'],
            ['order_tiebreaker', 'asc'],
            ['id', 'asc'],
        ])->values();

        // 6. Compute cumulative running balances
        $runningBalance = $openingBalance;
        $totalDebits = 0.0;
        $totalCredits = 0.0;
        $totalInvoiced = 0.0;
        $totalAdjustments = 0.0;
        $totalPayments = 0.0;

        $computedEntries = $sortedEntries->map(function ($entry) use (&$runningBalance, &$totalDebits, &$totalCredits, &$totalInvoiced, &$totalAdjustments, &$totalPayments) {
            $debit = (float) $entry['debit'];
            $credit = (float) $entry['credit'];

            $runningBalance = round($runningBalance + $debit - $credit, 2);

            $totalDebits = round($totalDebits + $debit, 2);
            $totalCredits = round($totalCredits + $credit, 2);

            if ($entry['type'] === 'invoice') {
                $totalInvoiced = round($totalInvoiced + $debit, 2);
            } elseif ($entry['type'] === 'credit_note') {
                $totalAdjustments = round($totalAdjustments - $credit, 2);
            } elseif ($entry['type'] === 'debit_note') {
                $totalAdjustments = round($totalAdjustments + $debit, 2);
            } elseif ($entry['type'] === 'payment') {
                $totalPayments = round($totalPayments + $credit, 2);
            }

            $entry['balance'] = $runningBalance;

            return $entry;
        });

        $closingBalance = $runningBalance;

        return [
            'shop' => $activeShop,
            'startDate' => $filterStartDate,
            'endDate' => $filterEndDate,
            'openingBalance' => $openingBalance,
            'closingBalance' => $closingBalance,
            'totalDebits' => $totalDebits,
            'totalCredits' => $totalCredits,
            'totalInvoiced' => $totalInvoiced,
            'totalAdjustments' => $totalAdjustments,
            'totalPayments' => $totalPayments,
            'entries' => $computedEntries,
        ];
    }

    public function statementOfAccount(Request $request): View
    {
        return $this->financeIndex($request->merge(['tab' => 'statement']));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dateRangeFromRequest(Request $request, Carbon $fallbackDate): array
    {
        $startDate = Carbon::parse($request->input('start_date', $fallbackDate->toDateString()));
        $endDate = Carbon::parse($request->input('end_date', $fallbackDate->toDateString()));

        if ($startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return [$startDate, $endDate];
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function nullableDateRangeFromRequest(Request $request): array
    {
        $startDate = $request->filled('start_date')
            ? Carbon::parse((string) $request->input('start_date'))->startOfDay()
            : null;
        $endDate = $request->filled('end_date')
            ? Carbon::parse((string) $request->input('end_date'))->endOfDay()
            : null;

        if ($startDate && $endDate && $startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        return [$startDate, $endDate];
    }

    private function ensurePettyExpensesAllowed(int $shopId, ?string $fundingSource): void
    {
        if ($fundingSource !== 'petty') {
            return;
        }

        $profile = ShopLedgerProfile::query()->where('shop_id', $shopId)->first();
        $pettyConfig = $profile?->getPaymentConfiguration()['petty'] ?? [
            'configured' => false,
            'enabled' => true,
            'allow_expenses_from_petty' => true,
        ];

        if (! empty($pettyConfig['configured']) && (! ($pettyConfig['enabled'] ?? true) || ! ($pettyConfig['allow_expenses_from_petty'] ?? true))) {
            throw ValidationException::withMessages([
                'funding_source' => 'Petty cash expenses are disabled for this shop in payment settings.',
            ]);
        }
    }
}
