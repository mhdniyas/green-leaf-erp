<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\Inventory\ProductGrade;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ShopOwner\StoreShopCompanyPaymentRequest;
use App\Http\Requests\Web\ShopOwner\StoreShopInvoicePaymentRequest;
use App\Http\Requests\Web\ShopOwner\StoreShopOwnerAccountingEntryRequest;
use App\Models\Category;
use App\Models\Shop;
use App\Models\ShopAccountingEntry;
use App\Models\ShopCredit;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopOrder;
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
                'invoice.shop',
            ])
            ->firstOrFail();

        return view('shop-owner.deliveries.show', [
            'order' => $order,
        ]);
    }

    public function financeIndex(Request $request): View
    {
        $activeShop = $this->currentShop($request);
        $isOwnedAccountingShop = $activeShop->isOwnedAccountingEnabled();
        $tab = (string) $request->input('tab', 'invoices');

        if ($tab !== 'payments') {
            $tab = 'invoices';
        }

        $invoices = ShopInvoice::query()
            ->where('shop_id', $activeShop->id)
            ->with(['order', 'items', 'paymentRequests' => fn ($query) => $query->latest('id')])
            ->latest('business_date')
            ->get();
        $companyPayments = ShopCredit::query()
            ->where('shop_id', $activeShop->id)
            ->where('type', 'out')
            ->with('creator')
            ->latest('id')
            ->paginate(12, ['*'], 'payments_page')
            ->withQueryString();
        $invoicePaymentRequests = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $activeShop->id)
            ->with(['invoice', 'requestedBy', 'reviewedBy', 'allocations'])
            ->latest('id')
            ->paginate(12, ['*'], 'payment_requests_page')
            ->withQueryString();
        $latestBalanceDate = $this->latestShopBalanceDate($activeShop);
        $latestClosingBalance = $this->ownedShopAccountingService->closingBalanceForDate($activeShop, $latestBalanceDate);
        $pendingBillApprovalSummary = $this->ownedShopAccountingService->pendingDeliveryBillApprovalSummary($activeShop);
        $companyPaymentTotal = (float) ShopCredit::query()
            ->approved()
            ->where('shop_id', $activeShop->id)
            ->where('type', 'out')
            ->sum('amount');
        $pendingInvoicePaymentAmount = (float) ShopInvoicePaymentRequest::query()
            ->where('shop_id', $activeShop->id)
            ->where('status', 'pending')
            ->sum('requested_amount');
        $availableInvoicePaymentCredit = $isOwnedAccountingShop ? 0.0 : $this->shopInvoiceService->availableShopCredit((int) $activeShop->id);

        return view('shop-owner.finance.index', [
            'invoices' => $invoices,
            'companyPayments' => $companyPayments,
            'invoicePaymentRequests' => $invoicePaymentRequests,
            'activeTab' => $tab,
            'isOwnedAccountingShop' => $isOwnedAccountingShop,
            'totalBilled' => (float) $invoices->sum(fn (ShopInvoice $invoice): float => (float) $invoice->final_total),
            'outstandingBalance' => (float) $invoices->sum(fn (ShopInvoice $invoice): float => (float) $invoice->balance_amount),
            'paidAmount' => (float) $invoices->sum(fn (ShopInvoice $invoice): float => (float) $invoice->paid_amount),
            'shortageValue' => (float) $invoices->sum(fn (ShopInvoice $invoice): float => (float) $invoice->shortage_total),
            'pendingPaymentAmount' => $isOwnedAccountingShop ? $companyPaymentTotal : $pendingInvoicePaymentAmount,
            'availableInvoicePaymentCredit' => $availableInvoicePaymentCredit,
            'companyPaymentTotal' => $companyPaymentTotal,
            'latestBalanceDate' => $latestBalanceDate,
            'latestClosingBalance' => $latestClosingBalance,
            'payableToCompany' => max(0.0, round($latestClosingBalance, 2)),
            'pendingBillApprovalSummary' => $pendingBillApprovalSummary,
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
        $ledgerEntriesByStatus = collect();
        $deliveryExpenseByDate = collect();
        $shopCreditByDate = collect();
        $cashGivenToShopByDate = collect();
        $paymentToCompanyByDate = collect();
        $shopCredits = collect();
        $greenLeafDirectLedgerDates = collect();
        $selectedDeliveryExpense = 0.0;
        $selectedShopCredit = 0.0;
        $incomeTotal = 0.0;
        $expenseTotal = 0.0;
        $netAmount = 0.0;
        $suggestedOpeningBalance = 0.0;
        $receiptSummary = $this->ownedShopAccountingService->receiptSummary(null);

        if ($shop->isOwnedAccountingEnabled()) {
            $entry = $this->ownedShopAccountingService->entryForDate($shop, $selectedDate);
            $suggestedOpeningBalance = $this->ownedShopAccountingService->previousClosingBalance($shop, $selectedDate);
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
                        ->limit(20)
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
            $shopCredits = ShopCredit::query()
                ->where('shop_id', $shop->id)
                ->with('creator')
                ->latest('business_date')
                ->latest('id')
                ->limit(8)
                ->get();
            $selectedDeliveryExpense = (float) ($deliveryExpenseByDate->get($selectedDate->toDateString()) ?? 0);
            $selectedShopCredit = (float) ($shopCreditByDate->get($selectedDate->toDateString()) ?? 0);
            $receiptSummary = $this->ownedShopAccountingService->receiptSummaryForDate($shop, $selectedDate);
            $ledgerEntriesByStatus = $ledgerEntriesByStatus->map(
                fn (Collection $statusEntries): Collection => $statusEntries
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
                    ->values()
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
            'invoices' => $invoices,
            'paymentRequests' => $paymentRequests,
            'entry' => $entry,
            'availableCategories' => $availableCategories,
            'recentEntries' => $recentEntries,
            'ledgerEntriesByStatus' => $ledgerEntriesByStatus,
            'deliveryExpenseByDate' => $deliveryExpenseByDate,
            'shopCreditByDate' => $shopCreditByDate,
            'cashGivenToShopByDate' => $cashGivenToShopByDate,
            'paymentToCompanyByDate' => $paymentToCompanyByDate,
            'shopCredits' => $shopCredits,
            'greenLeafDirectLedgerDates' => $greenLeafDirectLedgerDates,
            'selectedDeliveryExpense' => $selectedDeliveryExpense,
            'selectedShopCredit' => $selectedShopCredit,
            'incomeTotal' => $incomeTotal,
            'expenseTotal' => $expenseTotal,
            'netAmount' => $netAmount,
            'suggestedOpeningBalance' => $suggestedOpeningBalance,
            'receiptSummary' => $receiptSummary,
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
        $moneyReport = $this->shopAccountingMoneyReport($shop);

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
            'moneyReport' => $moneyReport,
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
        $page = max(1, $request->integer('daily_page', 1));
        $perPage = 12;
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
        $hasPreviousActivity = $allPreviousActivityDates->isNotEmpty();

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateKey = $date->toDateString();
            $dayEntries = $entriesByDate->get($dateKey, collect());
            $openingBalance = $activityDates->contains($dateKey)
                ? ($hasPreviousActivity
                    ? $this->ownedShopAccountingService->previousClosingBalance($shop, $date)
                    : round((float) ($dayEntries->first()?->opening_cash ?? 0.0), 2))
                : 0.0;
            $closingBalance = $this->ownedShopAccountingService->closingBalanceForDate($shop, $date);
            $hasPreviousActivity = $hasPreviousActivity || $activityDates->contains($dateKey);

            $rows->push([
                'date' => $date->copy(),
                'opening_balance' => $openingBalance,
                'closing_balance' => $closingBalance,
                'net_difference' => round($closingBalance - $openingBalance, 2),
            ]);
        }

        return $rows;
    }

    /**
     * @return array{
     *     totals: array<string, float>,
     *     transactions: Collection<int, array{date:string, label:string, detail:string, direction:string, amount:float, status:string, source:string}>
     * }
     */
    private function shopAccountingMoneyReport(Shop $shop): array
    {
        $invoices = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->get();
        $paymentRequests = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shop->id)
            ->with('invoice')
            ->orderByDesc('id')
            ->get();
        $shopCredits = ShopCredit::query()
            ->where('shop_id', $shop->id)
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->get();
        $accountingEntries = ShopAccountingEntry::query()
            ->where('shop_id', $shop->id)
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
            'transactions' => $transactions,
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
                ->where('status', 'approved')
                ->exists();

            if (! $hasApprovedEntry) {
                return back()->withErrors([
                    'business_date' => 'Additional entries can only be added after the day is approved.',
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
        abort_if($shop->isOwnedAccountingEnabled(), 404);
        $invoice = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->when(
                filled($request->validated('invoice_id')),
                fn ($query) => $query->whereKey((int) $request->validated('invoice_id')),
                fn ($query) => $query->where('balance_amount', '>', 0)
                    ->oldest('business_date')
                    ->oldest('id'),
            )
            ->firstOrFail();

        try {
            $this->shopInvoiceService->requestPayment(
                $invoice,
                $request->validated(),
                (int) $user->id,
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        $fallbackUrl = route('shop-owner.finance.index', ['tab' => 'payments']);
        $redirectUrl = url()->previous();

        if ($redirectUrl === url()->current()) {
            $redirectUrl = $fallbackUrl;
        }

        return redirect()->to($redirectUrl)
            ->with('success', 'Payment request sent for admin or purchase manager approval.');
    }

    public function storeCompanyPayment(StoreShopCompanyPaymentRequest $request): RedirectResponse
    {
        $shop = $this->ownedAccountingShop($request);
        $validated = $request->validated();
        $businessDate = Carbon::parse($validated['business_date']);
        $amount = round((float) $validated['amount'], 2);
        $availableBalance = max(0.0, $this->ownedShopAccountingService->closingBalanceForDate($shop, $businessDate));
        $pendingPaymentAmount = round((float) ShopCredit::query()
            ->where('shop_id', $shop->id)
            ->where('type', 'out')
            ->where('status', 'pending')
            ->sum('amount'), 2);

        if ($amount > max(0.0, round($availableBalance - $pendingPaymentAmount, 2))) {
            throw ValidationException::withMessages([
                'amount' => 'Payment cannot be more than the current payable company balance after pending approvals.',
            ]);
        }

        ShopCredit::query()->create([
            'shop_id' => $shop->id,
            'type' => 'out',
            'is_petty_cash' => true,
            'amount' => $amount,
            'description' => filled($validated['description'] ?? null)
                ? trim((string) $validated['description'])
                : 'Cash paid to company',
            'created_by' => $request->user()?->id,
            'business_date' => $businessDate->toDateString(),
            'status' => 'pending',
        ]);

        return redirect()->route('shop-owner.finance.index', ['tab' => 'payments'])
            ->with('success', 'Company payment sent for admin approval.');
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
        if ($tab === 'cashbook') {
            abort_unless($shop->isOwnedAccountingEnabled(), 404);

            return 'cashbook';
        }

        return 'bills';
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
}
