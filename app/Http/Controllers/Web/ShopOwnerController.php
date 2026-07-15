<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\Inventory\ProductGrade;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ShopOwner\StoreShopInvoicePaymentRequest;
use App\Http\Requests\Web\ShopOwner\StoreShopOwnerAccountingEntryRequest;
use App\Http\Requests\Web\ShopOwner\StoreShopPettyCashExpenseRequest;
use App\Models\Category;
use App\Models\Shop;
use App\Models\ShopAccountingEntry;
use App\Models\ShopCredit;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopOrder;
use App\Models\ShopPettyCashExpense;
use App\Models\ShopPreset;
use App\Models\User;
use App\Services\Finance\OwnedShopAccountingService;
use App\Services\Pricing\PriceBoardService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\ShopInvoices\ShopInvoiceService;
use App\Support\ShopOwner\ActiveShopResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShopOwnerController extends Controller
{
    public function __construct(
        private readonly PriceBoardService $priceBoardService,
        private readonly OwnedShopAccountingService $ownedShopAccountingService,
        private readonly PurchaserBusinessDayService $businessDayService,
        private readonly ShopInvoiceService $shopInvoiceService,
        private readonly ActiveShopResolver $activeShopResolver,
    ) {}

    public function dashboard(Request $request): View
    {
        $activeShop = $this->currentShop($request);

        return view('shop-owner.dashboard.index', $this->buildDashboardData($activeShop));
    }

    public function ordersIndex(Request $request): View
    {
        $activeShop = $this->currentShop($request);

        return view('shop-owner.orders.index', [
            'orders' => $this->shopOrdersQuery($activeShop)->latest('business_date')->get(),
            'tomorrowOrder' => $this->tomorrowOrder($activeShop),
        ]);
    }

    public function ordersCreate(Request $request): View
    {
        $activeShop = $this->currentShop($request);

        return view('shop-owner.orders.create', $this->buildOrderFormData($activeShop));
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

        return view('shop-owner.orders.history', [
            'orders' => $this->shopOrdersQuery($activeShop)->latest('business_date')->paginate(12),
            'tomorrowOrder' => $this->tomorrowOrder($activeShop),
        ]);
    }

    public function deliveriesIndex(Request $request): View
    {
        $activeShop = $this->currentShop($request);

        return view('shop-owner.deliveries.index', [
            'deliveries' => $this->shopOrdersQuery($activeShop)
                ->where(function ($query): void {
                    $query->where('is_allocation_completed', true)
                        ->orWhere('is_delivered', true);
                })
                ->latest('business_date')
                ->get(),
        ]);
    }

    public function deliveriesShow(Request $request, string $orderNumber): View
    {
        $activeShop = $this->currentShop($request);

        $order = ShopOrder::where('order_number', $orderNumber)
            ->where('shop_id', $activeShop->id)
            ->with([
                'shop',
                'invoice.paymentRequests.requestedBy',
                'invoice.paymentRequests.reviewedBy',
                'items' => fn ($q) => $q->where('sorting_status', 'loaded'),
                'items.product',
                'deliveredBy',
            ])
            ->firstOrFail();

        return view('shop-owner.deliveries.show', [
            'order' => $order,
        ]);
    }

    public function financeIndex(Request $request): View
    {
        $activeShop = $this->currentShop($request);
        $invoices = ShopInvoice::query()
            ->where('shop_id', $activeShop->id)
            ->with(['order', 'items'])
            ->latest('business_date')
            ->get();

        return view('shop-owner.finance.index', [
            'invoices' => $invoices,
            'outstandingBalance' => (float) $invoices->sum(fn (ShopInvoice $invoice): float => (float) $invoice->balance_amount),
            'paidAmount' => (float) $invoices->sum(fn (ShopInvoice $invoice): float => (float) $invoice->paid_amount),
            'shortageValue' => (float) $invoices->sum(fn (ShopInvoice $invoice): float => (float) $invoice->shortage_total),
        ]);
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

    public function accountingIndex(Request $request): View
    {
        $shop = $this->currentShop($request);
        $tab = $this->normalizeAccountingTab($shop, (string) $request->input('tab', 'bills'));
        $selectedDate = Carbon::parse($request->input('date', today()->toDateString()));
        $ledgerDateFilterActive = $request->filled('start_date') || $request->filled('end_date');
        $ledgerSourceFilter = in_array($request->input('ledger_source'), ['greenleaf_direct'], true)
            ? (string) $request->input('ledger_source')
            : 'all';
        [$startDate, $endDate] = $this->dateRangeFromRequest($request, $selectedDate);
        $invoices = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->with(['order', 'paymentRequests' => fn ($query) => $query->latest('id')])
            ->latest('business_date')
            ->latest('id')
            ->paginate(10, ['*'], 'bills_page');
        $paymentRequests = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shop->id)
            ->with(['invoice', 'requestedBy', 'reviewedBy'])
            ->latest('id')
            ->paginate(8, ['*'], 'requests_page');
        $billingSummary = $this->billingSummary(
            ShopInvoice::query()->where('shop_id', $shop->id)->get()
        );

        $entry = null;
        $availableCategories = collect();
        $recentEntries = collect();
        $ledgerEntries = null;
        $deliveryExpenseByDate = collect();
        $shopCreditByDate = collect();
        $shopCredits = collect();
        $pettyCashRows = collect();
        $pettyCashBalance = 0.0;
        $selectedPettyCashExpense = null;
        $greenLeafDirectLedgerDates = collect();
        $selectedDeliveryExpense = 0.0;
        $selectedShopCredit = 0.0;
        $incomeTotal = 0.0;
        $expenseTotal = 0.0;
        $netAmount = 0.0;

        if ($shop->isOwnedAccountingEnabled()) {
            $entry = $this->ownedShopAccountingService->entryForDate($shop, $selectedDate);
            $availableCategories = $this->ownedShopAccountingService->availableCategoriesForShop($shop);
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
            $ledgerEntries = ShopAccountingEntry::query()
                ->where('shop_id', $shop->id)
                ->with(['lines.category', 'submittedBy', 'reviewedBy'])
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
                ->paginate(10, ['*'], 'ledger_page');
            $deliveryExpenseByDate = ShopInvoice::query()
                ->where('shop_id', $shop->id)
                ->when($ledgerDateFilterActive, fn ($query) => $query
                    ->whereDate('business_date', '>=', $startDate)
                    ->whereDate('business_date', '<=', $endDate))
                ->selectRaw('DATE(business_date) as ledger_date, SUM(final_total) as total')
                ->groupByRaw('DATE(business_date)')
                ->pluck('total', 'ledger_date')
                ->map(fn ($total): float => round((float) $total, 2));
            $shopCreditByDate = ShopCredit::query()
                ->where('shop_id', $shop->id)
                ->when($ledgerDateFilterActive, fn ($query) => $query
                    ->whereDate('business_date', '>=', $startDate)
                    ->whereDate('business_date', '<=', $endDate))
                ->selectRaw("DATE(business_date) as ledger_date, SUM(CASE WHEN type = 'in' THEN amount ELSE -amount END) as total")
                ->groupByRaw('DATE(business_date)')
                ->pluck('total', 'ledger_date')
                ->map(fn ($total): float => round((float) $total, 2));
            $shopCredits = ShopCredit::query()
                ->where('shop_id', $shop->id)
                ->with('creator')
                ->latest('business_date')
                ->latest('id')
                ->limit(8)
                ->get();
            $pettyCashRows = $this->ownedShopAccountingService->pettyCashRows($shop, $startDate, $endDate);
            $pettyCashBalanceRows = $this->ownedShopAccountingService->pettyCashRows($shop, Carbon::parse('2000-01-01'), $endDate);
            $pettyCashBalance = (float) ($pettyCashBalanceRows->first()['balance'] ?? 0.0);
            $selectedPettyCashExpense = ShopPettyCashExpense::query()
                ->where('shop_id', $shop->id)
                ->whereDate('business_date', $selectedDate)
                ->first();
            $selectedDeliveryExpense = (float) ($deliveryExpenseByDate->get($selectedDate->toDateString()) ?? 0);
            $selectedShopCredit = (float) ($shopCreditByDate->get($selectedDate->toDateString()) ?? 0);

            $incomeTotal = $entry instanceof ShopAccountingEntry
                ? round((float) $entry->lines->where('type', 'income')->sum('amount'), 2)
                : 0.0;
            $expenseTotal = ($entry instanceof ShopAccountingEntry
                ? round((float) $entry->lines->where('type', 'expense')->sum('amount'), 2)
                : 0.0) + $selectedDeliveryExpense;
            $netAmount = round($incomeTotal + $selectedShopCredit - $expenseTotal, 2);
        }

        return view('shop-owner.accounting.index', [
            'shop' => $shop,
            'tab' => $tab,
            'selectedDate' => $selectedDate,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'billingSummary' => $billingSummary,
            'invoices' => $invoices,
            'paymentRequests' => $paymentRequests,
            'entry' => $entry,
            'availableCategories' => $availableCategories,
            'recentEntries' => $recentEntries,
            'ledgerEntries' => $ledgerEntries ?? new LengthAwarePaginator([], 0, 10),
            'deliveryExpenseByDate' => $deliveryExpenseByDate,
            'shopCreditByDate' => $shopCreditByDate,
            'shopCredits' => $shopCredits,
            'pettyCashRows' => $pettyCashRows,
            'pettyCashBalance' => $pettyCashBalance,
            'selectedPettyCashExpense' => $selectedPettyCashExpense,
            'greenLeafDirectLedgerDates' => $greenLeafDirectLedgerDates,
            'selectedDeliveryExpense' => $selectedDeliveryExpense,
            'selectedShopCredit' => $selectedShopCredit,
            'incomeTotal' => $incomeTotal,
            'expenseTotal' => $expenseTotal,
            'netAmount' => $netAmount,
            'reserveAmount' => round((float) ($shop->reserve_amount ?? 0), 2),
            'ledgerDateFilterActive' => $ledgerDateFilterActive,
            'ledgerSourceFilter' => $ledgerSourceFilter,
        ]);
    }

    public function accountingHistory(Request $request): View
    {
        $shop = $this->currentShop($request);
        $tab = $this->normalizeAccountingTab($shop, (string) $request->input('tab', 'bills'));
        $entries = collect();
        $invoiceHistory = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->with(['order', 'paymentRequests' => fn ($query) => $query->latest('id')])
            ->latest('business_date')
            ->latest('id')
            ->paginate(12, ['*'], 'bill_history_page');
        $paymentRequestHistory = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shop->id)
            ->with(['invoice', 'requestedBy', 'reviewedBy'])
            ->latest('id')
            ->paginate(12, ['*'], 'payment_history_page');

        if ($shop->isOwnedAccountingEnabled()) {
            $entries = ShopAccountingEntry::query()
                ->where('shop_id', $shop->id)
                ->with(['lines.category', 'submittedBy', 'reviewedBy'])
                ->latest('business_date')
                ->paginate(12);
        }

        return view('shop-owner.accounting.history', [
            'shop' => $shop,
            'tab' => $tab,
            'entries' => $entries,
            'invoiceHistory' => $invoiceHistory,
            'paymentRequestHistory' => $paymentRequestHistory,
        ]);
    }

    public function pettyCashIndex(Request $request): View
    {
        $shop = $this->ownedAccountingShop($request);
        $startDate = Carbon::parse($request->input('start_date', today()->startOfMonth()->toDateString()));
        $endDate = Carbon::parse($request->input('end_date', today()->toDateString()));

        if ($endDate->isFuture()) {
            $endDate = today();
        }

        if ($startDate->gt($endDate)) {
            $startDate = $endDate->copy()->startOfMonth();
        }

        $pettyCashRows = $this->ownedShopAccountingService->pettyCashRows($shop, $startDate, $endDate, includeEmptyDays: true);
        $pettyCashBalanceRows = $this->ownedShopAccountingService->pettyCashRows($shop, Carbon::parse('2000-01-01'), $endDate);
        $pettyCashBalance = (float) ($pettyCashBalanceRows->first()['balance'] ?? 0.0);

        return view('shop-owner.accounting.petty-cash', [
            'shop' => $shop,
            'tab' => 'cashbook',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'pettyCashRows' => $pettyCashRows,
            'pettyCashBalance' => $pettyCashBalance,
        ]);
    }

    public function storeAccountingEntry(StoreShopOwnerAccountingEntryRequest $request): RedirectResponse
    {
        $user = $this->shopUser($request);
        $shop = $this->ownedAccountingShop($request);
        $validated = $request->validated();
        $businessDate = Carbon::parse($validated['business_date']);
        $entry = ShopAccountingEntry::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', $businessDate)
            ->first();

        try {
            $entry = $this->ownedShopAccountingService->saveShopOwnerEntry($shop, $validated, (int) $user->id, $entry);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        $message = $validated['submission_action'] === 'submit'
            ? 'Daily accounting sent to admin for approval.'
            : 'Daily accounting draft saved.';

        return redirect()->route('shop-owner.accounting.index', ['tab' => 'cashbook', 'date' => $entry->business_date?->toDateString()])
            ->with('success', $message);
    }

    public function storePettyCashExpense(StoreShopPettyCashExpenseRequest $request): RedirectResponse
    {
        $user = $this->shopUser($request);
        $shop = $this->ownedAccountingShop($request);
        $validated = $request->validated();
        $businessDate = Carbon::parse($validated['business_date']);

        try {
            $this->ownedShopAccountingService->recordManualPettyCashExpense(
                $shop,
                $businessDate,
                round((float) $validated['amount'], 2),
                (int) $user->id,
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('shop-owner.accounting.index', [
            'tab' => 'cashbook',
            'date' => $businessDate->toDateString(),
        ])->with('success', 'Petty cash expense updated for '.$businessDate->format('d M Y').'.');
    }

    public function storePaymentRequest(StoreShopInvoicePaymentRequest $request): RedirectResponse
    {
        $user = $this->shopUser($request);
        $shop = $this->currentShop($request);
        $invoice = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->findOrFail((int) $request->validated('invoice_id'));

        try {
            $this->shopInvoiceService->requestPayment(
                $invoice,
                $request->validated(),
                (int) $user->id,
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        $fallbackUrl = route('shop-owner.accounting.index', ['tab' => 'bills']);
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
        $recentOrders = $this->shopOrdersQuery($shop)->latest('business_date')->limit(8)->get();
        $deliveredOrders = $recentOrders->where('is_delivered', true);
        $recentInvoices = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->with('order')
            ->latest('business_date')
            ->limit(8)
            ->get();
        $pendingDeliveries = $recentOrders->filter(
            fn (ShopOrder $order): bool => $order->is_allocation_completed && ! $order->is_delivered
        );

        return [
            'stats' => [
                'pending_approval_count' => $recentOrders->whereIn('state', ['submitted', 'update_requested'])->count(),
                'pending_delivery_count' => $pendingDeliveries->count(),
                'delivered_orders_count' => $deliveredOrders->count(),
                'outstanding_balance' => (float) $recentInvoices->sum(fn (ShopInvoice $invoice): float => (float) $invoice->balance_amount),
            ],
            'todayOrder' => $this->todayOrder($shop),
            'tomorrowOrder' => $this->tomorrowOrder($shop),
            'pendingDeliveries' => $pendingDeliveries,
            'recentOrders' => $recentOrders,
            'recentInvoices' => $recentInvoices,
            'financeSummary' => [
                'paid_amount' => (float) $recentInvoices->sum(fn (ShopInvoice $invoice): float => (float) $invoice->paid_amount),
                'shortage_value' => (float) $recentInvoices->sum(fn (ShopInvoice $invoice): float => (float) $invoice->shortage_total),
                'outstanding_balance' => (float) $recentInvoices->sum(fn (ShopInvoice $invoice): float => (float) $invoice->balance_amount),
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
            $query->where('is_active', true)->ordered();
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
                'latestResolvedRevision.items.product',
                'latestResolvedRevision.reviewedBy',
                'revisions.items.product',
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

    private function ownedAccountingShop(Request $request): Shop
    {
        $shop = $this->currentShop($request);

        abort_unless($shop->isOwnedAccountingEnabled(), 404);

        return $shop;
    }

    private function normalizeAccountingTab(Shop $shop, string $tab): string
    {
        if ($tab === 'cashbook' && $shop->isOwnedAccountingEnabled()) {
            return 'cashbook';
        }

        return 'bills';
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
}
