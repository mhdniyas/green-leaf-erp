<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\Inventory\ProductGrade;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ShopOwner\StoreShopInvoicePaymentRequest;
use App\Http\Requests\Web\ShopOwner\StoreShopOwnerAccountingEntryRequest;
use App\Models\Category;
use App\Models\Shop;
use App\Models\ShopAccountingEntry;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopCredit;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPreset;
use App\Models\User;
use App\Services\Finance\CompanyPayableService;
use App\Services\Finance\OwnedShopAccountingService;
use App\Services\Finance\ShopLoanService;
use App\Services\Pricing\PriceBoardService;
use App\Services\Purchasing\PurchaserBusinessDayService;
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
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShopOwnerController extends Controller
{
    public function __construct(
        private readonly PriceBoardService $priceBoardService,
        private readonly OwnedShopAccountingService $ownedShopAccountingService,
        private readonly ShopLoanService $shopLoanService,
        private readonly CompanyPayableService $companyPayableService,
        private readonly PurchaserBusinessDayService $businessDayService,
        private readonly ShopInvoiceService $shopInvoiceService,
        private readonly ActiveShopResolver $activeShopResolver,
        private readonly DeliveryPriceReadinessService $deliveryPriceReadinessService,
        private readonly DeliveryVerificationEligibility $deliveryVerificationEligibility,
    ) {}

    public function dashboard(Request $request): View
    {
        $activeShop = $this->currentShop($request);

        return view('shop-owner.dashboard.index', $this->buildDashboardData($activeShop));
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

        return view('shop-owner.deliveries.index', [
            'deliveries' => $this->shopOrdersQuery($activeShop)
                ->when($filterStartDate, fn ($query) => $query->whereDate('business_date', '>=', $filterStartDate))
                ->when($filterEndDate, fn ($query) => $query->whereDate('business_date', '<=', $filterEndDate))
                ->where(function ($query): void {
                    $query->where('is_allocation_completed', true)
                        ->orWhere('is_delivered', true)
                        ->orWhereHas('invoice');
                })
                ->latest('business_date')
                ->paginate(12, ['*'], 'deliveries_page')
                ->withQueryString(),
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
                $lockedOrder->delivery_status !== 'in_transit'
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
                    'delivery_status' => 'pending_approval',
                    'delivery_review_status' => 'pending',
                    'shop_checked_by' => $request->user()?->id,
                    'shop_checked_at' => now(),
                    'admin_reviewed_by' => null,
                    'admin_reviewed_at' => null,
                    'admin_review_note' => null,
                    'is_delivered' => false,
                    'delivered_at' => null,
                    'delivered_by' => $request->user()?->id,
                ]);
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

    public function financeIndex(Request $request): View
    {
        $tab = (string) $request->input('tab', 'invoices');

        if ($tab === 'payments') {
            return view('shop-owner.payments.index', $this->financeViewData($request, 'payments'));
        }

        return view('shop-owner.finance.index', $this->financeViewData($request, 'invoices'));
    }

    public function paymentsIndex(Request $request): View
    {
        return view('shop-owner.payments.index', $this->financeViewData($request, 'payments'));
    }

    /**
     * @return array<string, mixed>
     */
    private function financeViewData(Request $request, string $tab): array
    {
        $activeShop = $this->currentShop($request);
        $isOwnedAccountingShop = $activeShop->isOwnedAccountingEnabled();
        [$filterStartDate, $filterEndDate] = $this->nullableDateRangeFromRequest($request);

        $invoiceQuery = ShopInvoice::query()
            ->where('shop_id', $activeShop->id)
            ->when($filterStartDate, fn ($query) => $query->whereDate('business_date', '>=', $filterStartDate))
            ->when($filterEndDate, fn ($query) => $query->whereDate('business_date', '<=', $filterEndDate));
        $invoiceTotals = (clone $invoiceQuery)
            ->selectRaw('COALESCE(SUM(final_total), 0) as total_billed')
            ->selectRaw('COALESCE(SUM(balance_amount), 0) as outstanding_balance')
            ->selectRaw('COALESCE(SUM(paid_amount), 0) as paid_amount')
            ->selectRaw('COALESCE(SUM(shortage_total), 0) as shortage_value')
            ->first();
        $invoices = (clone $invoiceQuery)
            ->with(['order', 'items', 'paymentRequests' => fn ($query) => $query->latest('id')])
            ->latest('business_date')
            ->latest('id')
            ->paginate(12, ['*'], 'invoices_page')
            ->withQueryString();
        $payableInvoices = (clone $invoiceQuery)
            ->where('balance_amount', '>', 0)
            ->with(['order', 'items'])
            ->oldest('business_date')
            ->oldest('id')
            ->paginate(8, ['*'], 'payable_invoices_page')
            ->withQueryString();
        $payableInvoiceTotal = (float) (clone $invoiceQuery)
            ->where('balance_amount', '>', 0)
            ->sum('balance_amount');
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

        return [
            'invoices' => $invoices,
            'payableInvoices' => $payableInvoices,
            'payableInvoiceTotal' => $payableInvoiceTotal,
            'invoicePaymentRequests' => $invoicePaymentRequests,
            'activeTab' => $tab,
            'isOwnedAccountingShop' => $isOwnedAccountingShop,
            'totalBilled' => (float) ($invoiceTotals?->total_billed ?? 0),
            'outstandingBalance' => (float) ($invoiceTotals?->outstanding_balance ?? 0),
            'paidAmount' => (float) ($invoiceTotals?->paid_amount ?? 0),
            'shortageValue' => (float) ($invoiceTotals?->shortage_value ?? 0),
            'pendingPaymentAmount' => $pendingInvoicePaymentAmount,
            'availableInvoicePaymentCredit' => $availableInvoicePaymentCredit,
            'latestBalanceDate' => $latestBalanceDate,
            'latestClosingBalance' => $latestClosingBalance,
            'pendingBillApprovalSummary' => $pendingBillApprovalSummary,
            'filterStartDate' => $filterStartDate,
            'filterEndDate' => $filterEndDate,
            'carryOver' => $carryOver,
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

    public function accountingCashbookPdf(Request $request): View
    {
        $shop = $this->currentShop($request);
        abort_unless($shop->isOwnedAccountingEnabled(), 404);

        $selectedDate = Carbon::parse($request->input('date', today()->toDateString()));
        $entry = $this->ownedShopAccountingService->entryForDate($shop, $selectedDate);

        return view('shop-owner.accounting.cashbook-pdf', [
            'shop' => $shop,
            'selectedDate' => $selectedDate,
            'entry' => $entry?->load(['lines.category', 'submittedBy', 'reviewedBy']),
            'receiptSummary' => $this->ownedShopAccountingService->receiptSummaryForDate($shop, $selectedDate),
        ]);
    }

    public function accountingIndex(Request $request): View
    {
        $shop = $this->currentShop($request);
        $defaultTab = $shop->isOwnedAccountingEnabled() ? 'cashbook' : 'bills';
        $tab = $this->normalizeAccountingTab($shop, (string) $request->input('tab', $defaultTab));
        $selectedDate = Carbon::parse($request->input('date', today()->toDateString()));
        $ledgerDateFilterActive = $request->filled('start_date') || $request->filled('end_date');
        $ledgerSourceFilter = in_array($request->input('ledger_source'), ['greenleaf_direct'], true)
            ? (string) $request->input('ledger_source')
            : 'all';
        $ledgerStatusTab = in_array((string) $request->input('ledger_status', 'draft'), ['draft', 'submitted', 'approved', 'recheck'], true)
            ? (string) $request->input('ledger_status', 'draft')
            : 'draft';
        $ledgerStatuses = [
            'draft' => 'draft',
            'submitted' => 'submitted',
            'approved' => 'approved',
            'recheck' => 'recheck_required',
        ];
        [$startDate, $endDate] = $this->dateRangeFromRequest($request, $selectedDate);
        $invoices = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->with(['order', 'items.product', 'paymentRequests' => fn ($query) => $query->latest('id')])
            ->latest('business_date')
            ->latest('id')
            ->paginate(10, ['*'], 'bills_page')
            ->withQueryString();
        $selectedBillInvoices = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', $selectedDate->toDateString())
            ->with(['order', 'items.product', 'paymentRequests' => fn ($query) => $query->latest('id')])
            ->latest('id')
            ->get();
        $paymentRequests = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shop->id)
            ->with(['invoice', 'requestedBy', 'reviewedBy'])
            ->latest('id')
            ->paginate(8, ['*'], 'requests_page')
            ->withQueryString();
        $billingSummary = $this->billingSummary(
            ShopInvoice::query()->where('shop_id', $shop->id)->get()
        );
        $dailyBillingSummary = $this->billingSummary($selectedBillInvoices);

        $entry = null;
        $availableCategories = collect();
        $recentEntries = collect();
        $ledgerEntriesByStatus = collect();
        $deliveryExpenseByDate = collect();
        $shopCreditByDate = collect();
        $cashGivenToShopByDate = collect();
        $paymentToCompanyByDate = collect();
        $greenLeafDirectLedgerDates = collect();
        $selectedDeliveryExpense = 0.0;
        $selectedShopCredit = 0.0;
        $incomeTotal = 0.0;
        $expenseTotal = 0.0;
        $netAmount = 0.0;
        $suggestedOpeningBalance = 0.0;
        $receiptSummary = $this->ownedShopAccountingService->receiptSummary(null);
        $loanRows = collect();
        $loanBalance = 0.0;
        $loanCategoryIds = collect();
        $loanCategorySettings = collect();
        $othersSubtab = $this->normalizeOthersSubtab($request);
        $companyPayableLines = collect();

        if ($shop->isOwnedAccountingEnabled()) {
            $entry = $this->ownedShopAccountingService->entryForDate($shop, $selectedDate);
            $suggestedOpeningBalance = $this->ownedShopAccountingService->previousClosingBalance($shop, $selectedDate);
            $availableCategories = $this->ownedShopAccountingService->availableCategoriesForShop($shop);
            $loanRows = $this->shopLoanService->ledgerRows($shop);
            $loanBalance = $this->shopLoanService->approvedBalance($shop);
            if ($tab === 'loan' && $othersSubtab === 'company') {
                $companyPayableLines = $this->companyPayableService->linesForShop($shop);
            }
            $loanCategorySettings = $this->shopLoanService->settingsForShop($shop);
            $loanCategoryIds = $loanCategorySettings
                ->pluck('shop_accounting_category_id')
                ->map(fn ($categoryId): int => (int) $categoryId)
                ->values();
            $recentEntries = ShopAccountingEntry::query()
                ->where('shop_id', $shop->id)
                ->with(['lines.category', 'submittedBy', 'reviewedBy'])
                ->latest('business_date')
                ->limit(8)
                ->get();
            $greenLeafDirectLedgerDates = ShopInvoice::query()
                ->where('shop_id', $shop->id)
                ->when($ledgerDateFilterActive, fn ($query) => $query
                    ->whereDate('business_date', '>=', $startDate)
                    ->whereDate('business_date', '<=', $endDate))
                ->pluck('business_date')
                ->map(fn ($businessDate): string => Carbon::parse($businessDate)->toDateString())
                ->unique()
                ->values();
            $ledgerEntriesByStatus = collect($ledgerStatuses)
                ->mapWithKeys(fn (string $status, string $statusKey): array => [
                    $statusKey => ShopAccountingEntry::query()
                        ->where('shop_id', $shop->id)
                        ->with(['lines.category', 'submittedBy', 'reviewedBy'])
                        ->where('status', $status)
                        ->when($ledgerDateFilterActive, fn ($query) => $query
                            ->whereDate('business_date', '>=', $startDate)
                            ->whereDate('business_date', '<=', $endDate))
                        ->when($ledgerSourceFilter === 'greenleaf_direct', function ($query) use ($greenLeafDirectLedgerDates): void {
                            if ($greenLeafDirectLedgerDates->isEmpty()) {
                                $query->whereRaw('0 = 1');

                                return;
                            }

                            $query->where(function ($dateQuery) use ($greenLeafDirectLedgerDates): void {
                                $greenLeafDirectLedgerDates->each(
                                    fn (string $ledgerDate) => $dateQuery->orWhereDate('business_date', $ledgerDate)
                                );
                            });
                        })
                        ->latest('business_date')
                        ->latest('id')
                        ->get(),
                ]);
            $deliveryExpenseByDate = ShopInvoice::query()
                ->where('shop_id', $shop->id)
                ->where('final_total', '>', 0)
                ->where(function ($query): void {
                    $query
                        ->whereIn('delivery_status', ['received_full', 'approved_after_discrepancy'])
                        ->orWhereIn('status', ['finalized', 'payment_pending', 'paid'])
                        ->orWhereIn('payment_status', ['partially_paid', 'paid']);
                })
                ->when($ledgerDateFilterActive, fn ($query) => $query
                    ->whereDate('business_date', '>=', $startDate)
                    ->whereDate('business_date', '<=', $endDate))
                ->selectRaw('DATE(business_date) as ledger_date, SUM(final_total) as total')
                ->groupByRaw('DATE(business_date)')
                ->pluck('total', 'ledger_date')
                ->map(fn ($total): float => round((float) $total, 2));
            $shopCreditByDate = ShopCredit::query()
                ->approved()
                ->where('shop_id', $shop->id)
                ->when($ledgerDateFilterActive, fn ($query) => $query
                    ->whereDate('business_date', '>=', $startDate)
                    ->whereDate('business_date', '<=', $endDate))
                ->selectRaw("DATE(business_date) as ledger_date, SUM(CASE WHEN type = 'in' THEN amount ELSE -amount END) as total")
                ->groupByRaw('DATE(business_date)')
                ->pluck('total', 'ledger_date')
                ->map(fn ($total): float => round((float) $total, 2));
            $cashGivenToShopByDate = ShopCredit::query()
                ->approved()
                ->where('shop_id', $shop->id)
                ->where('type', 'in')
                ->when($ledgerDateFilterActive, fn ($query) => $query
                    ->whereDate('business_date', '>=', $startDate)
                    ->whereDate('business_date', '<=', $endDate))
                ->selectRaw('DATE(business_date) as ledger_date, SUM(amount) as total')
                ->groupByRaw('DATE(business_date)')
                ->pluck('total', 'ledger_date')
                ->map(fn ($total): float => round((float) $total, 2));
            $paymentToCompanyByDate = ShopCredit::query()
                ->approved()
                ->where('shop_id', $shop->id)
                ->where('type', 'out')
                ->when($ledgerDateFilterActive, fn ($query) => $query
                    ->whereDate('business_date', '>=', $startDate)
                    ->whereDate('business_date', '<=', $endDate))
                ->selectRaw('DATE(business_date) as ledger_date, SUM(amount) as total')
                ->groupByRaw('DATE(business_date)')
                ->pluck('total', 'ledger_date')
                ->map(fn ($total): float => round((float) $total, 2));
            $selectedDeliveryExpense = (float) ($deliveryExpenseByDate->get($selectedDate->toDateString()) ?? 0);
            $selectedShopCredit = (float) ($shopCreditByDate->get($selectedDate->toDateString()) ?? 0);
            $receiptSummary = $this->ownedShopAccountingService->receiptSummaryForDate($shop, $selectedDate);
            $ledgerEntriesByStatus = $ledgerEntriesByStatus->map(
                fn (Collection $statusEntries, string $statusKey): LengthAwarePaginator => $this->paginateCollection(
                    $statusEntries
                        ->groupBy(fn (ShopAccountingEntry $ledgerEntry): string => $ledgerEntry->business_date->toDateString())
                        ->map(function (Collection $dayEntries, string $ledgerDate) use ($cashGivenToShopByDate, $deliveryExpenseByDate, $paymentToCompanyByDate, $shop): array {
                            $firstEntry = $dayEntries->first();
                            $income = round((float) $dayEntries->sum(
                                fn (ShopAccountingEntry $ledgerEntry): float => (float) $ledgerEntry->lines->where('type', 'income')->sum('amount')
                            ), 2);
                            $manualExpense = round((float) $dayEntries->sum(
                                fn (ShopAccountingEntry $ledgerEntry): float => (float) $ledgerEntry->lines->where('type', 'expense')->sum('amount')
                            ), 2);
                            $warehouseExpense = (float) ($deliveryExpenseByDate->get($ledgerDate) ?? 0.0);
                            $cashGivenToShop = (float) ($cashGivenToShopByDate->get($ledgerDate) ?? 0.0);
                            $paymentToCompany = (float) ($paymentToCompanyByDate->get($ledgerDate) ?? 0.0);

                            return [
                                'date' => $ledgerDate,
                                'status_label' => $firstEntry?->statusLabel() ?? 'No Entry',
                                'status_tone' => $firstEntry?->statusTone() ?? 'neutral',
                                'income' => $income,
                                'cash_given_to_shop' => $cashGivenToShop,
                                'payment_to_company' => $paymentToCompany,
                                'manual_expense' => $manualExpense,
                                'warehouse_expense' => $warehouseExpense,
                                'closing' => $this->ownedShopAccountingService->closingBalanceForDate($shop, Carbon::parse($ledgerDate)),
                                'items' => $dayEntries->sum(fn (ShopAccountingEntry $ledgerEntry): int => $ledgerEntry->lines->count()),
                            ];
                        })
                        ->values(),
                    $request,
                    'ledger_'.$statusKey.'_page',
                    12,
                )
            );

            $incomeTotal = (float) $receiptSummary['total_income'];
            $expenseTotal = (float) $receiptSummary['total_debit'];
            $netAmount = round((float) $receiptSummary['expected_closing'] - (float) $receiptSummary['opening_balance'], 2);
        }

        return view('shop-owner.accounting.index', [
            'shop' => $shop,
            'tab' => $tab,
            'selectedDate' => $selectedDate,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'billingSummary' => $billingSummary,
            'dailyBillingSummary' => $dailyBillingSummary,
            'invoices' => $invoices,
            'selectedBillInvoices' => $selectedBillInvoices,
            'paymentRequests' => $paymentRequests,
            'entry' => $entry,
            'availableCategories' => $availableCategories,
            'recentEntries' => $recentEntries,
            'ledgerEntriesByStatus' => $ledgerEntriesByStatus,
            'deliveryExpenseByDate' => $deliveryExpenseByDate,
            'shopCreditByDate' => $shopCreditByDate,
            'cashGivenToShopByDate' => $cashGivenToShopByDate,
            'paymentToCompanyByDate' => $paymentToCompanyByDate,
            'greenLeafDirectLedgerDates' => $greenLeafDirectLedgerDates,
            'selectedDeliveryExpense' => $selectedDeliveryExpense,
            'selectedShopCredit' => $selectedShopCredit,
            'incomeTotal' => $incomeTotal,
            'expenseTotal' => $expenseTotal,
            'netAmount' => $netAmount,
            'suggestedOpeningBalance' => $suggestedOpeningBalance,
            'receiptSummary' => $receiptSummary,
            'loanRows' => $loanRows,
            'loanBalance' => $loanBalance,
            'loanCategoryIds' => $loanCategoryIds,
            'loanCategorySettings' => $loanCategorySettings,
            'othersSubtab' => $othersSubtab,
            'companyPayableLines' => $companyPayableLines,
            'reserveAmount' => round((float) ($shop->reserve_amount ?? 0), 2),
            'ledgerDateFilterActive' => $ledgerDateFilterActive,
            'ledgerSourceFilter' => $ledgerSourceFilter,
            'ledgerStatusTab' => $ledgerStatusTab,
        ]);
    }

    public function accountingHistory(Request $request): View
    {
        $shop = $this->currentShop($request);
        $tab = $this->normalizeAccountingTab($shop, (string) $request->input('tab', 'bills'));
        [$filterStartDate, $filterEndDate] = $this->nullableDateRangeFromRequest($request);

        if (! $filterStartDate && ! $filterEndDate) {
            $filterStartDate = now()->startOfMonth();
            $filterEndDate = now()->endOfMonth();
        }

        $entries = collect();
        $invoiceHistory = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->when($filterStartDate, fn ($query) => $query->whereDate('business_date', '>=', $filterStartDate))
            ->when($filterEndDate, fn ($query) => $query->whereDate('business_date', '<=', $filterEndDate))
            ->with(['order', 'paymentRequests' => fn ($query) => $query->latest('id')])
            ->latest('business_date')
            ->latest('id')
            ->paginate(12, ['*'], 'bill_history_page')
            ->withQueryString();
        $paymentRequestHistory = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shop->id)
            ->when($filterStartDate, fn ($query) => $query->whereDate('created_at', '>=', $filterStartDate))
            ->when($filterEndDate, fn ($query) => $query->whereDate('created_at', '<=', $filterEndDate))
            ->with(['invoice', 'requestedBy', 'reviewedBy'])
            ->latest('id')
            ->paginate(12, ['*'], 'payment_history_page')
            ->withQueryString();
        $moneyReport = $this->shopAccountingMoneyReport($shop, $request, $filterStartDate, $filterEndDate);

        if ($shop->isOwnedAccountingEnabled()) {
            $entries = ShopAccountingEntry::query()
                ->where('shop_id', $shop->id)
                ->when($filterStartDate, fn ($query) => $query->whereDate('business_date', '>=', $filterStartDate))
                ->when($filterEndDate, fn ($query) => $query->whereDate('business_date', '<=', $filterEndDate))
                ->with(['lines.category', 'submittedBy', 'reviewedBy'])
                ->latest('business_date')
                ->paginate(12, ['*'], 'entries_page')
                ->withQueryString();
        }

        return view('shop-owner.accounting.history', [
            'shop' => $shop,
            'tab' => $tab,
            'entries' => $entries,
            'invoiceHistory' => $invoiceHistory,
            'paymentRequestHistory' => $paymentRequestHistory,
            'moneyReport' => $moneyReport,
            'filterStartDate' => $filterStartDate,
            'filterEndDate' => $filterEndDate,
        ]);
    }

    public function accountingDailyReport(Request $request): View
    {
        $shop = $this->ownedAccountingShop($request);
        $tab = $this->normalizeAccountingTab($shop, 'cashbook');
        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', (string) $request->input('month'))->startOfMonth()
            : today()->startOfMonth();
        $rows = $this->shopDailyBalanceRows($shop, $month);
        $perPage = 12;
        $defaultPage = $month->isSameMonth(today())
            ? (int) ceil(today()->day / $perPage)
            : 1;
        $page = max(1, $request->integer('daily_page', $defaultPage));
        $dailyRows = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => 'daily_page',
                'query' => $request->query(),
            ],
        );

        return view('shop-owner.accounting.daily-report', [
            'shop' => $shop,
            'tab' => $tab,
            'month' => $month,
            'dailyRows' => $dailyRows,
        ]);
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
            ->reject(fn (ShopCredit $credit): bool => \Illuminate\Support\Str::contains(
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
                'bill_due' => round((float) $invoices->sum('balance_amount'), 2),
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
        $user = $this->shopUser($request);
        $shop = $this->ownedAccountingShop($request);
        $validated = $request->validated();
        $businessDate = Carbon::parse($validated['business_date']);
        $isAdjustment = $request->boolean('create_adjustment');
        $entry = $isAdjustment
            ? null
            : $this->ownedShopAccountingService->entryForDate($shop, $businessDate);

        if ($isAdjustment) {
            $hasApprovedEntry = ShopAccountingEntry::query()
                ->where('shop_id', $shop->id)
                ->whereDate('business_date', $businessDate)
                ->where('entry_type', ShopAccountingEntry::TypeDaily)
                ->where('status', 'approved')
                ->exists();

            if (! $hasApprovedEntry) {
                return back()->withErrors([
                    'business_date' => 'Additional entries can only be added after the day is approved.',
                ])->withInput();
            }

            if ($this->ownedShopAccountingService->hasSimilarAdjustment($shop, $businessDate, $validated['lines'] ?? [])) {
                return back()->withErrors([
                    'lines' => 'A similar adjustment already exists for this date. Change the note or amount if this is a separate transaction.',
                ])->withInput();
            }
        }

        try {
            $entry = $this->ownedShopAccountingService->saveShopOwnerEntry($shop, $validated, (int) $user->id, $entry);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        $message = $validated['submission_action'] === 'submit'
            ? ($isAdjustment ? 'Additional entry sent to admin for approval.' : 'Daily accounting sent to admin for approval.')
            : 'Daily accounting draft saved.';

        return redirect()->route('shop-owner.accounting.index', [
            'tab' => 'cashbook',
            'ledger_status' => $validated['submission_action'] === 'submit' ? 'submitted' : 'draft',
            'date' => $entry->business_date?->toDateString(),
        ])
            ->with('success', $message);
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

            return redirect()->route('shop-owner.finance.index', ['tab' => 'payments'])
                ->with('success', 'Closing balance payment request sent for admin approval.');
        }

        $invoice = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->when(
                filled($validated['invoice_id'] ?? null),
                fn ($query) => $query->whereKey((int) $validated['invoice_id']),
                fn ($query) => $query->where('balance_amount', '>', 0)
                    ->oldest('business_date')
                    ->oldest('id'),
            )
            ->firstOrFail();

        try {
            $this->shopInvoiceService->requestPayment(
                $invoice,
                $validated,
                (int) $user->id,
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        $fallbackUrl = route('shop-owner.payments.index');
        $redirectUrl = url()->previous();

        if ($redirectUrl === url()->current()) {
            $redirectUrl = $fallbackUrl;
        }

        return redirect()->to($redirectUrl)
            ->with('success', 'Payment request sent for admin or purchase manager approval.');
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
            ->get();
        $todayInvoices = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', $businessDate->toDateString())
            ->with(['order', 'items.product'])
            ->latest('id')
            ->get();
        $recentInvoices = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->with(['order', 'items.product'])
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
            ],
            'todayOrder' => $this->todayOrder($shop),
            'tomorrowOrder' => $this->tomorrowOrder($shop),
            'pendingDeliveries' => $pendingDeliveries,
            'recentOrders' => $recentOrders,
            'recentInvoices' => $recentInvoices,
            'todayInvoices' => $todayInvoices,
            'financeSummary' => [
                'paid_amount' => (float) $billingSummary['total_paid'],
                'shortage_value' => (float) $allInvoices->sum(fn (ShopInvoice $invoice): float => (float) $invoice->shortage_total),
                'outstanding_balance' => (float) $billingSummary['total_balance'],
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
            $query->where('is_active', true)->with(['orderUnits' => fn ($q) => $q->where('is_orderable', true)])->ordered();
        }])
            ->where('is_active', true)
            ->get()
            ->filter(fn (Category $category): bool => $category->products->isNotEmpty());

        $productsByCategory->each(function (Category $category) use ($shop): void {
            $category->products->each(function ($product) use ($shop): void {
                $price = $this->priceBoardService->sellingPriceFor($product, $shop, ProductGrade::GradeA);
                $product->setAttribute('effective_price', $price['price']);
            });
        });

        $frequentProducts = $this->frequentProducts($shop);
        $frequentProducts->each(function (array $item) use ($shop): void {
            $product = $item['product'];
            $price = $this->priceBoardService->sellingPriceFor($product, $shop, ProductGrade::GradeA);
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

        $this->shopInvoiceService->synchronizeOrderInvoice($order, $userId);
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
     * @return array{total_billed:float,total_paid:float,total_balance:float,open_bills:int}
     */
    private function billingSummary(Collection $invoices): array
    {
        return [
            'total_billed' => round((float) $invoices->sum('final_total'), 2),
            'total_paid' => round((float) $invoices->sum('paid_amount'), 2),
            'total_balance' => round((float) $invoices->sum('balance_amount'), 2),
            'open_bills' => $invoices->filter(fn (ShopInvoice $invoice): bool => (float) $invoice->balance_amount > 0)->count(),
        ];
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
}
