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
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopOrder;
use App\Models\ShopPreset;
use App\Models\User;
use App\Services\Finance\OwnedShopAccountingService;
use App\Services\Pricing\PriceBoardService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShopOwnerController extends Controller
{
    public function __construct(
        private readonly PriceBoardService $priceBoardService,
        private readonly OwnedShopAccountingService $ownedShopAccountingService,
        private readonly ShopInvoiceService $shopInvoiceService,
    ) {}

    public function dashboard(Request $request): View
    {
        $user = $this->shopUser($request);

        return view('shop-owner.dashboard.index', $this->buildDashboardData($user));
    }

    public function ordersIndex(Request $request): View
    {
        $user = $this->shopUser($request);

        return view('shop-owner.orders.index', [
            'orders' => $this->shopOrdersQuery($user)->latest('business_date')->get(),
            'tomorrowOrder' => $this->tomorrowOrder($user),
        ]);
    }

    public function ordersCreate(Request $request): View
    {
        $user = $this->shopUser($request);

        return view('shop-owner.orders.create', $this->buildOrderFormData($user));
    }

    public function ordersShow(Request $request, string $orderNumber): View
    {
        $user = $this->shopUser($request);

        return view('shop-owner.orders.show', [
            'order' => $this->shopOrderByNumber($request, $orderNumber),
            'tomorrowOrder' => $this->tomorrowOrder($user),
        ]);
    }

    public function ordersHistory(Request $request): View
    {
        $user = $this->shopUser($request);

        return view('shop-owner.orders.history', [
            'orders' => $this->shopOrdersQuery($user)->latest('business_date')->paginate(12),
            'tomorrowOrder' => $this->tomorrowOrder($user),
        ]);
    }

    public function deliveriesIndex(Request $request): View
    {
        $user = $this->shopUser($request);

        return view('shop-owner.deliveries.index', [
            'deliveries' => $this->shopOrdersQuery($user)
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
        $order = ShopOrder::where('order_number', $orderNumber)
            ->where('shop_id', $request->user()->shop_id)
            ->with([
                'shop',
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
        $user = $this->shopUser($request);
        $invoices = ShopInvoice::query()
            ->where('shop_id', $user->shop_id)
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
        $user = $this->shopUser($request);
        abort_unless($invoice->shop_id === $user->shop_id, 403);

        return view('shop-owner.finance.show', [
            'invoice' => $invoice->load(['shop', 'items.product', 'order']),
        ]);
    }

    public function financePdf(Request $request, ShopInvoice $invoice): View
    {
        $user = $this->shopUser($request);
        abort_unless($invoice->shop_id === $user->shop_id, 403);

        return view('shop-owner.finance.pdf', [
            'invoice' => $invoice->load(['shop', 'items.product', 'order']),
        ]);
    }

    public function accountingIndex(Request $request): View
    {
        $user = $this->shopUser($request);
        $shop = $this->currentShop($user);
        $tab = $this->normalizeAccountingTab($shop, (string) $request->input('tab', 'bills'));
        $selectedDate = Carbon::parse($request->input('date', today()->toDateString()));
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

            $incomeTotal = $entry instanceof ShopAccountingEntry
                ? round((float) $entry->lines->where('type', 'income')->sum('amount'), 2)
                : 0.0;
            $expenseTotal = $entry instanceof ShopAccountingEntry
                ? round((float) $entry->lines->where('type', 'expense')->sum('amount'), 2)
                : 0.0;
            $netAmount = round($incomeTotal - $expenseTotal, 2);
        }

        return view('shop-owner.accounting.index', [
            'shop' => $shop,
            'tab' => $tab,
            'selectedDate' => $selectedDate,
            'billingSummary' => $billingSummary,
            'invoices' => $invoices,
            'paymentRequests' => $paymentRequests,
            'entry' => $entry,
            'availableCategories' => $availableCategories,
            'recentEntries' => $recentEntries,
            'incomeTotal' => $incomeTotal,
            'expenseTotal' => $expenseTotal,
            'netAmount' => $netAmount,
            'reserveAmount' => round((float) ($shop->reserve_amount ?? 0), 2),
        ]);
    }

    public function accountingHistory(Request $request): View
    {
        $user = $this->shopUser($request);
        $shop = $this->currentShop($user);
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

    public function storeAccountingEntry(StoreShopOwnerAccountingEntryRequest $request): RedirectResponse
    {
        $user = $this->shopUser($request);
        $shop = $this->ownedAccountingShop($user);
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

    public function storePaymentRequest(StoreShopInvoicePaymentRequest $request): RedirectResponse
    {
        $user = $this->shopUser($request);
        $shop = $this->currentShop($user);
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

        return redirect()->route('shop-owner.accounting.index', ['tab' => 'bills'])
            ->with('success', 'Payment request sent for admin approval.');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboardData(User $user): array
    {
        $recentOrders = $this->shopOrdersQuery($user)->latest('business_date')->limit(8)->get();
        $deliveredOrders = $recentOrders->where('is_delivered', true);
        $recentInvoices = ShopInvoice::query()
            ->where('shop_id', $user->shop_id)
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
            'todayOrder' => $this->todayOrder($user),
            'tomorrowOrder' => $this->tomorrowOrder($user),
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
    private function buildOrderFormData(User $user): array
    {
        $tomorrowDate = Carbon::tomorrow();

        $productsByCategory = Category::with(['products' => function ($query): void {
            $query->where('is_active', true)->ordered();
        }])
            ->where('is_active', true)
            ->get()
            ->filter(fn (Category $category): bool => $category->products->isNotEmpty());

        $productsByCategory->each(function (Category $category) use ($user): void {
            $category->products->each(function ($product) use ($user): void {
                $price = $this->priceBoardService->sellingPriceFor($product, $user->shop, ProductGrade::GradeA);
                $product->setAttribute('effective_price', $price['price']);
            });
        });

        $frequentProducts = $this->frequentProducts($user);
        $frequentProducts->each(function (array $item) use ($user): void {
            $product = $item['product'];
            $price = $this->priceBoardService->sellingPriceFor($product, $user->shop, ProductGrade::GradeA);
            $product->setAttribute('effective_price', $price['price']);
        });

        $tomorrowOrder = $this->tomorrowOrder($user);

        return [
            'productsByCategory' => $productsByCategory,
            'frequentProducts' => $frequentProducts,
            'presets' => ShopPreset::where('shop_id', $user->shop_id)->with('items.product')->get(),
            'yesterdayOrder' => $this->yesterdayOrder($user),
            'tomorrowOrder' => $tomorrowOrder,
            'tomorrowDate' => $tomorrowDate,
            'cutoffPassed' => now()->greaterThan(now()->copy()->setTime(21, 30)),
            'purchaseOrdersLockedForTomorrow' => $tomorrowOrder?->linkedPurchaseOrdersHaveGoodsReceived() ?? false,
        ];
    }

    private function shopUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->hasRole('shop') && $user->shop_id, 403);

        return $user;
    }

    private function shopOrderByNumber(Request $request, string $orderNumber): ShopOrder
    {
        $user = $this->shopUser($request);

        return $this->shopOrdersQuery($user)
            ->where('order_number', $orderNumber)
            ->firstOrFail();
    }

    private function todayOrder(User $user): ?ShopOrder
    {
        return $this->shopOrdersQuery($user)
            ->whereDate('business_date', today())
            ->first();
    }

    private function tomorrowOrder(User $user): ?ShopOrder
    {
        return $this->shopOrdersQuery($user)
            ->whereDate('business_date', Carbon::tomorrow())
            ->first();
    }

    private function yesterdayOrder(User $user): ?ShopOrder
    {
        $yesterdayOrder = $this->shopOrdersQuery($user)
            ->whereDate('business_date', today()->subDay())
            ->first();

        if ($yesterdayOrder) {
            return $yesterdayOrder;
        }

        return $this->shopOrdersQuery($user)
            ->whereDate('business_date', '<', today())
            ->latest('business_date')
            ->first();
    }

    private function shopOrdersQuery(User $user)
    {
        return ShopOrder::query()
            ->where('shop_id', $user->shop_id)
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

    private function frequentProducts(User $user)
    {
        $historicalOrders = $this->shopOrdersQuery($user)
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

    private function currentShop(User $user): Shop
    {
        return Shop::query()->findOrFail($user->shop_id);
    }

    private function ownedAccountingShop(User $user): Shop
    {
        $shop = $this->currentShop($user);

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
}
