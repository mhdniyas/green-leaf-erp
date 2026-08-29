<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Exports\PurchaserReportArrayExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashbook\AddShopRequest;
use App\Http\Requests\Cashbook\AssignShopPresetRequest;
use App\Http\Requests\Cashbook\CreatePresetRequest;
use App\Http\Requests\Cashbook\DeleteEntryRequest;
use App\Http\Requests\Cashbook\FundShopPettyRequest;
use App\Http\Requests\Cashbook\PurchasePriceReportRequest;
use App\Http\Requests\Cashbook\ReconcileShopPaymentLedgerRequest;
use App\Http\Requests\Cashbook\RecordEntryRequest;
use App\Http\Requests\Cashbook\StoreCompanyAccountingCashbookEntryRequest;
use App\Http\Requests\Cashbook\StoreDirectCompanySaleRequest;
use App\Http\Requests\Cashbook\StoreShopPaymentRequest;
use App\Http\Requests\Cashbook\UpdateEntryRequest;
use App\Http\Requests\Cashbook\UpdatePresetSettingRequest;
use App\Http\Requests\Cashbook\UpdateRuleRequest;
use App\Http\Requests\Cashbook\VoidEntryRequest;
use App\Models\Account;
use App\Models\BusinessSetting;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\CompanyPaymentReconciliation;
use App\Models\Cashbook\LedgerClient;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\PresetCollectionGroup;
use App\Models\Cashbook\PresetCollectionGroupEntryType;
use App\Models\Cashbook\PresetEntrySetting;
use App\Models\Cashbook\ShopBankSettlementAdjustment;
use App\Models\Cashbook\ShopBankSettlementAdjustmentRule;
use App\Models\Cashbook\ShopConfigPreset;
use App\Models\Cashbook\ShopLedgerCollectionGroup;
use App\Models\Cashbook\ShopLedgerCollectionGroupEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Category;
use App\Models\CompanyAccountingCategory;
use App\Models\CompanyAccountingEntry;
use App\Models\CompanyPayableSettlement;
use App\Models\DirectCompanySale;
use App\Models\EmployeeAdvanceRequest;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use App\Models\Payment;
use App\Models\PayrollPayment;
use App\Models\PayrollRunItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseProductFilter;
use App\Models\PurchaserCredit;
use App\Models\Shop;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorAdvance;
use App\Models\VendorSettlement;
use App\Models\WastageEntry;
use App\Services\Cashbook\BankSettlementExpectedAmountService;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\CashbookTransactionReversalService;
use App\Services\Cashbook\CashFlowTransactionPresenter;
use App\Services\Cashbook\CollectionGroupPostingService;
use App\Services\Cashbook\CompanyAccountingCashbookService;
use App\Services\Cashbook\CompanyMoneyPositionService;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Cashbook\DailyLedgerService;
use App\Services\Cashbook\DirectCompanySaleInventoryService;
use App\Services\Cashbook\HistoricalBankCollectionFetchService;
use App\Services\Cashbook\ReconciliationAutoMatchSuggestionService;
use App\Services\Cashbook\ReconciliationTransactionQuery;
use App\Services\Cashbook\ShopCollectionAutoMatchService;
use App\Services\Cashbook\ShopPaymentLedgerReconciliationService;
use App\Services\Cashbook\ShopPettyFundingService;
use App\Services\Finance\CompanyPayableService;
use App\Services\Finance\JournalService;
use App\Services\Finance\PurchaserFinanceService;
use App\Services\Finance\VendorSettlementService;
use App\Services\HR\PayrollPaymentService;
use App\Services\Pricing\ApprovedDailyPriceResolver;
use App\Services\Purchasing\PurchaseInvoiceService;
use App\Services\Purchasing\PurchasePriceReportingService;
use App\Services\Purchasing\PurchaseReportingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Admin-only Cashbook dashboard — a complete port of the standalone ledger-app.
 *
 * Dynamically connects to Green Leaf ERP's owned shops (via CashbookShopSyncService).
 * All actions use dedicated FormRequests and Policy-backed authorization.
 */
final class CashbookController extends Controller
{
    public function __construct(
        private readonly DailyLedgerService $ledgerService,
        private readonly CashbookShopSyncService $shopSyncService,
        private readonly CollectionGroupPostingService $collectionGroupPostingService,
        private readonly CompanyPaymentReconciliationService $companyPaymentReconciliationService,
        private readonly ReconciliationTransactionQuery $reconciliationTransactionQuery,
        private readonly ReconciliationAutoMatchSuggestionService $reconciliationAutoMatchSuggestionService,
        private readonly ShopPaymentLedgerReconciliationService $shopPaymentLedgerReconciliationService,
        private readonly ShopPettyFundingService $shopPettyFundingService,
        private readonly CompanyAccountingCashbookService $companyAccountingCashbookService,
        private readonly DirectCompanySaleInventoryService $directCompanySaleInventoryService,
        private readonly ApprovedDailyPriceResolver $approvedDailyPriceResolver,
        private readonly PayrollPaymentService $payrollPaymentService,
        private readonly HistoricalBankCollectionFetchService $historicalBankCollectionFetchService,
        private readonly CompanyMoneyPositionService $moneyPositionService,
        private readonly CashFlowTransactionPresenter $transactionPresenter,
        private readonly CashbookTransactionReversalService $reversalService,
        private readonly BankSettlementExpectedAmountService $expectedAmountService = new BankSettlementExpectedAmountService,
    ) {}

    // ─── Page methods ────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $this->ensureMainAdmin($request);

        return $this->renderApp('all-shops', 1, $this->selectedDate($request));
    }

    public function allShops(Request $request): View
    {
        $this->ensureMainAdmin($request);

        return $this->renderApp('all-shops', 1, $this->selectedDate($request));
    }

    public function reports(Request $request): View
    {
        $this->ensureMainAdmin($request);

        $filters = $this->reportFilters($request);
        $selectedDate = $filters['selected_date'];
        $timeframe = $filters['timeframe'];
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $clients = LedgerClient::with('shops')->where('enabled', true)->get();
        $companyAccounts = CompanyAccount::where('enabled', true)->get();
        $company = config('greenleaf');
        $reportRangeLabel = Carbon::parse($startDate)->format('d M Y').' – '.Carbon::parse($endDate)->format('d M Y');

        return view('admin.cashbook.reports.index', compact(
            'shops',
            'clients',
            'companyAccounts',
            'company',
            'selectedDate',
            'timeframe',
            'startDate',
            'endDate',
            'reportRangeLabel',
        ));
    }

    public function exportReportsCsv(Request $request): StreamedResponse
    {
        $this->ensureMainAdmin($request);

        $filters = $this->reportFilters($request);
        $includeDetails = $request->boolean('include_details', false);
        $scope = (string) $request->input('scope', 'all');
        $rows = $this->cashbookReportExportRows(
            $filters['selected_date'],
            $filters['timeframe'],
            $filters['start_date'],
            $filters['end_date'],
            $includeDetails,
            $scope
        );

        return response()->streamDownload(function () use ($rows): void {
            $file = fopen('php://output', 'w');

            if ($file === false) {
                return;
            }

            foreach ($rows as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        }, $this->reportFilename('cashbook-report', $filters['start_date'], $filters['end_date'], 'csv'), [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function exportReportsExcel(Request $request): BinaryFileResponse
    {
        $this->ensureMainAdmin($request);

        $filters = $this->reportFilters($request);
        $includeDetails = $request->boolean('include_details', false);
        $scope = (string) $request->input('scope', 'all');

        return Excel::download(
            new PurchaserReportArrayExport(
                $this->cashbookReportExportRows(
                    $filters['selected_date'],
                    $filters['timeframe'],
                    $filters['start_date'],
                    $filters['end_date'],
                    $includeDetails,
                    $scope
                ),
                'Cashbook Report'
            ),
            $this->reportFilename('cashbook-report', $filters['start_date'], $filters['end_date'], 'xlsx'),
        );
    }

    public function exportReportsPdf(Request $request): mixed
    {
        $this->ensureMainAdmin($request);

        $filters = $this->reportFilters($request);
        $scope = (string) $request->input('scope', 'all');

        $allShops = $this->shopSyncService->syncAndGetProfiles();
        $allShops->load('client');

        $filteredShops = $allShops->filter(function (ShopLedgerProfile $shop) use ($scope): bool {
            $isDirect = $shop->client_id === null && $shop->profile_template === 'direct_buyer';
            if ($scope === 'owned') {
                return ! $isDirect;
            }
            if ($scope === 'direct') {
                return $isDirect;
            }

            return true;
        })->values();

        $shopIds = $filteredShops->pluck('shop_id')->map(fn ($id) => (int) $id)->all();

        $transactions = ShopLedgerTransaction::query()
            ->whereIn('shop_id', $shopIds)
            ->whereBetween('business_date', [$filters['start_date'], $filters['end_date']])
            ->where('status', '!=', 'void')
            ->with('entryType')
            ->get();

        $shopRows = [];
        $grandSales = 0.0;
        $grandExpense = 0.0;
        $grandNet = 0.0;
        $grandGl = 0.0;

        foreach ($filteredShops as $shop) {
            $isDirect = $shop->client_id === null && $shop->profile_template === 'direct_buyer';
            $scopeLabel = $isDirect ? 'Direct' : ($shop->client?->name ?: 'Own');

            $shopTx = $transactions->where('shop_id', $shop->shop_id);

            $txByDate = $shopTx->groupBy(
                fn ($tx) => Carbon::parse($tx->business_date)->toDateString()
            );

            $activeTx = collect();
            foreach ($txByDate as $dateStr => $dayTxs) {
                $hasNonGlBill = $dayTxs->contains(function ($t) {
                    $code = $t->entryType?->code ?: $t->entry_type_code;

                    return $t->reference_type !== 'App\Models\ShopInvoice'
                        && $t->reference_type !== ShopInvoice::class
                        && ! in_array($code, ['purchase_bill', 'gl_bill', 'invoice_bill'], true);
                });

                if ($hasNonGlBill) {
                    $activeTx = $activeTx->concat($dayTxs);
                }
            }

            $sVal = (float) $activeTx
                ->filter(fn ($t) => $t->direction === 'income' || ($t->entryType && $t->entryType->category === 'income'))
                ->sum('amount');

            $eVal = (float) $activeTx
                ->filter(fn ($t) => $t->direction === 'expense' || ($t->entryType && $t->entryType->category === 'expense'))
                ->sum('amount');

            $nVal = round($sVal - $eVal, 2);

            $glBillTxs = $activeTx
                ->filter(function ($t) {
                    $code = $t->entryType?->code ?: $t->entry_type_code;

                    return in_array($code, ['purchase_bill', 'gl_bill', 'invoice_bill'], true)
                        || str_contains(strtolower((string) $t->notes), 'invoice')
                        || $t->reference_type === 'App\Models\ShopInvoice'
                        || $t->reference_type === ShopInvoice::class;
                });

            $gVal = (float) $glBillTxs->sum('amount');
            $billCount = (int) $glBillTxs->count();

            if ($billCount === 0 && $sVal == 0.0 && $eVal == 0.0) {
                continue;
            }

            $grandSales += $sVal;
            $grandExpense += $eVal;
            $grandNet += $nVal;
            $grandGl += $gVal;

            $shopRows[] = [
                'shop_id' => $shop->shop_id,
                'name' => $shop->name ?: ('Shop #'.$shop->shop_id),
                'scope' => $scopeLabel,
                'sales' => round($sVal, 2),
                'expense' => round($eVal, 2),
                'net' => $nVal,
                'gl_bills' => round($gVal, 2),
                'bill_count' => $billCount,
            ];
        }

        $totals = [
            'sales' => round($grandSales, 2),
            'expense' => round($grandExpense, 2),
            'net' => round($grandNet, 2),
            'gl_bills' => round($grandGl, 2),
        ];

        $viewData = [
            'title' => 'All Shops Executive Financial Overview',
            'selectedDate' => $filters['selected_date'],
            'timeframe' => $filters['timeframe'],
            'startDate' => $filters['start_date'],
            'endDate' => $filters['end_date'],
            'scope' => $scope,
            'shopRows' => $shopRows,
            'totals' => $totals,
        ];

        if ($request->boolean('download', false) || $request->input('download') === '1') {
            $pdf = Pdf::loadView('admin.cashbook.reports.pdf_all_shops_download', $viewData)
                ->setPaper('a4', 'portrait')
                ->setOption(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true]);

            $fileName = 'all_shops_cashbook_report_'.$filters['start_date'].'_to_'.$filters['end_date'].'.pdf';

            return $pdf->download($fileName);
        }

        return view('admin.cashbook.reports.pdf_all_shops', $viewData);
    }

    public function payables(Request $request): View
    {
        $this->ensureMainAdmin($request);

        return $this->renderApp('payables', 1);
    }

    public function acceptPaymentPage(Request $request): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        return redirect()->route('admin.cashbook.all-shops', ['month' => $request->input('month', now()->format('Y-m'))])
            ->with('warning', 'Select a shop, then use Accept Payment from its ledger page.');
    }

    public function incomeExpenses(Request $request): View
    {
        $this->ensureMainAdmin($request);

        return $this->renderApp('income-expense', 1);
    }

    public function postEntryPage(Request $request): View
    {
        $this->ensureMainAdmin($request);

        return $this->renderApp('simulator', 1);
    }

    public function postEntryPageForShop(Request $request, int|string $shop): View
    {
        $this->ensureMainAdmin($request);
        $resolved = $this->resolveShop($shop);

        return $this->renderApp('simulator', $resolved->shop_id);
    }

    public function showShop(Request $request, int|string $shop): View
    {
        $this->ensureMainAdmin($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $company = config('greenleaf');
        $currentShop = $this->resolveShop($shop);
        $currentShop->load('client', 'preset', 'shop');
        $businessDate = $request->input('date') ? (string) $request->input('date') : today()->toDateString();
        $month = Carbon::parse((string) $request->input('month', $businessDate))->format('Y-m');
        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $monthEnd = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();
        $shopId = (int) $currentShop->shop_id;
        $dailySettlement = $this->moneyPositionService->getShopDaySettlementOperationalSummary($shopId, $businessDate);
        $position = $this->ledgerService->dailySummary($shopId, $monthEnd);
        $paymentCard = $this->shopPaymentCard($currentShop, $monthStart, $monthEnd);

        $paymentSubmitted = (float) $paymentCard['received_amount'];
        $cashBankReceived = (float) $paymentCard['approved_amount'];
        $floatingPayments = (float) $paymentCard['floating_amount'];
        $pendingPayable = (float) $paymentCard['after_balance'];
        $glBillPending = (float) ShopInvoice::query()
            ->where('shop_id', $shopId)
            ->where('status', '!=', 'cancelled')
            ->where('balance_amount', '>', 0)
            ->sum('balance_amount');
        $ledgerSettled = (float) ShopPaymentLedgerAllocation::query()
            ->whereHas('paymentRequest', fn (Builder $query): Builder => $query->where('shop_id', $shopId))
            ->whereBetween('created_at', [$monthStart.' 00:00:00', $monthEnd.' 23:59:59'])
            ->sum('amount');

        $recentPayments = ShopInvoicePaymentRequest::query()
            ->with(['reconciliations.statementEntry', 'reconciliations.companyAccount'])
            ->withExists('ledgerAllocations')
            ->withSum('ledgerAllocations as settled_amount', 'amount')
            ->where('shop_id', $shopId)
            ->where(function (Builder $query) use ($monthStart, $monthEnd): void {
                $query->whereBetween('payment_date', [$monthStart, $monthEnd])
                    ->orWhereBetween('created_at', [$monthStart.' 00:00:00', $monthEnd.' 23:59:59']);
            })
            ->latest('payment_date')
            ->latest('id')
            ->limit(5)
            ->get();
        $recentLedgerActivity = ShopLedgerTransaction::query()
            ->with('entryType')
            ->withSum('paymentLedgerAllocations as settled_amount', 'amount')
            ->where('shop_id', $shopId)
            ->whereNotIn('status', ['void', 'voided'])
            ->latest('business_date')
            ->latest('id')
            ->limit(5)
            ->get();
        $recentPettyActivity = ShopLedgerTransaction::query()
            ->with('entryType')
            ->where('shop_id', $shopId)
            ->where('petty_delta', '!=', 0)
            ->whereNotIn('status', ['void', 'voided'])
            ->latest('business_date')
            ->latest('id')
            ->limit(3)
            ->get();
        $openLedgerItems = ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->where('settlement_delta', '!=', 0)
            ->whereNotIn('status', ['void', 'voided'])
            ->count();

        return view('admin.cashbook.shops.show', compact(
            'shops',
            'company',
            'currentShop',
            'businessDate',
            'dailySettlement',
            'month',
            'monthStart',
            'monthEnd',
            'position',
            'paymentCard',
            'paymentSubmitted',
            'cashBankReceived',
            'floatingPayments',
            'pendingPayable',
            'glBillPending',
            'ledgerSettled',
            'recentPayments',
            'recentLedgerActivity',
            'recentPettyActivity',
            'openLedgerItems',
        ));
    }

    public function exportShopData(Request $request, int|string $shop)
    {
        $this->ensureMainAdmin($request);
        $resolvedShop = $this->resolveShop($shop);

        $format = (string) $request->input('format', 'csv');
        $timeframe = (string) $request->input('timeframe', 'daily');
        $date = (string) $request->input('date', today()->toDateString());
        $reqStart = $request->input('start_date');
        $reqEnd = $request->input('end_date');
        $includeDetails = $request->boolean('include_details', false);

        $carbon = Carbon::parse($date);

        [$finalStart, $finalEnd] = match ($timeframe) {
            'daily' => [$date, $date],
            'weekly' => [$carbon->copy()->startOfWeek()->toDateString(), $carbon->copy()->endOfWeek()->toDateString()],
            'monthly' => [$carbon->copy()->startOfMonth()->toDateString(), $carbon->copy()->endOfMonth()->toDateString()],
            'custom' => [$reqStart ?: $date, $reqEnd ?: $date],
            default => [$reqStart ?: $date, $reqEnd ?: $date],
        };

        // 1. Sales per date for this shop
        $salesPerDate = ShopLedgerTransaction::query()
            ->where('shop_id', (int) $resolvedShop->id)
            ->where('status', '!=', 'voided')
            ->where(function ($q) {
                $q->where('direction', 'income')
                    ->orWhereHas('entryType', fn ($e) => $e->where('category', 'income'));
            })
            ->whereDate('business_date', '>=', $finalStart)
            ->whereDate('business_date', '<=', $finalEnd)
            ->selectRaw('DATE(business_date) as b_date, SUM(amount) as total_sales')
            ->groupBy('b_date')
            ->pluck('total_sales', 'b_date');

        // 2. Expense per date for this shop
        $expensePerDate = ShopLedgerTransaction::query()
            ->where('shop_id', (int) $resolvedShop->id)
            ->where('status', '!=', 'voided')
            ->where(function ($q) {
                $q->where('direction', 'expense')
                    ->orWhereHas('entryType', fn ($e) => $e->where('category', 'expense'));
            })
            ->whereDate('business_date', '>=', $finalStart)
            ->whereDate('business_date', '<=', $finalEnd)
            ->selectRaw('DATE(business_date) as b_date, SUM(amount) as total_expense')
            ->groupBy('b_date')
            ->pluck('total_expense', 'b_date');

        // 3. GL Bills per date for this specific shop
        $glBillsPerDate = ShopInvoice::query()
            ->where('shop_id', (int) $resolvedShop->id)
            ->where('status', '!=', 'cancelled')
            ->where('final_total', '>', 0)
            ->whereDate('business_date', '>=', $finalStart)
            ->whereDate('business_date', '<=', $finalEnd)
            ->selectRaw('DATE(business_date) as b_date, SUM(final_total) as total_gl')
            ->groupBy('b_date')
            ->pluck('total_gl', 'b_date');

        $allDates = collect()
            ->merge($salesPerDate->keys())
            ->merge($expensePerDate->keys())
            ->merge($glBillsPerDate->keys())
            ->unique()
            ->sort()
            ->values();

        if ($allDates->isEmpty()) {
            $allDates = collect([$finalStart]);
        }

        $rows = [];

        // Table 1: Summary Table
        $rows[] = ['Date', 'Sales Total', 'Total Expense', 'Net Balance', 'GL Bill'];

        foreach ($allDates as $dStr) {
            $sVal = round((float) ($salesPerDate[$dStr] ?? 0), 2);
            $eVal = round((float) ($expensePerDate[$dStr] ?? 0), 2);
            $nVal = round($sVal - $eVal, 2);
            $gVal = round((float) ($glBillsPerDate[$dStr] ?? 0), 2);

            $rows[] = [$dStr, $sVal, $eVal, $nVal, $gVal];
        }

        if ($includeDetails) {
            $rows[] = [];

            // Table 2: Total Sales Details (with Day header)
            $rows[] = ['Total Sales Details'];
            $rows[] = ['Date', 'Day', 'Income'];

            $incomeTransactions = ShopLedgerTransaction::query()
                ->with('entryType')
                ->where('shop_id', (int) $resolvedShop->id)
                ->where('status', '!=', 'voided')
                ->where(function ($q) {
                    $q->where('direction', 'income')
                        ->orWhereHas('entryType', fn ($e) => $e->where('category', 'income'));
                })
                ->whereDate('business_date', '>=', $finalStart)
                ->whereDate('business_date', '<=', $finalEnd)
                ->orderBy('business_date')
                ->orderBy('id')
                ->get();

            foreach ($incomeTransactions as $tx) {
                $carbonDate = $tx->business_date ? Carbon::parse($tx->business_date) : null;
                $bDate = $carbonDate ? $carbonDate->format('Y-m-d') : '';
                $dayName = $carbonDate ? $carbonDate->format('l') : '';

                $rows[] = [$bDate, $dayName, round((float) $tx->amount, 2)];
            }

            $rows[] = [];

            // Table 3: Total Expense Details (with Day header)
            $rows[] = ['Total Expense Details'];
            $rows[] = ['Date', 'Day', 'Expense'];

            $expenseTransactions = ShopLedgerTransaction::query()
                ->with('entryType')
                ->where('shop_id', (int) $resolvedShop->id)
                ->where('status', '!=', 'voided')
                ->where(function ($q) {
                    $q->where('direction', 'expense')
                        ->orWhereHas('entryType', fn ($e) => $e->where('category', 'expense'));
                })
                ->whereDate('business_date', '>=', $finalStart)
                ->whereDate('business_date', '<=', $finalEnd)
                ->orderBy('business_date')
                ->orderBy('id')
                ->get();

            foreach ($expenseTransactions as $tx) {
                $carbonDate = $tx->business_date ? Carbon::parse($tx->business_date) : null;
                $bDate = $carbonDate ? $carbonDate->format('Y-m-d') : '';
                $dayName = $carbonDate ? $carbonDate->format('l') : '';

                $rows[] = [$bDate, $dayName, round((float) $tx->amount, 2)];
            }
        }

        if ($format === 'pdf') {
            $totSales = round((float) $salesPerDate->sum(), 2);
            $totExpense = round((float) $expensePerDate->sum(), 2);
            $totNet = round($totSales - $totExpense, 2);
            $totGl = round((float) $glBillsPerDate->sum(), 2);

            $totals = [
                'sales' => $totSales,
                'expense' => $totExpense,
                'net' => $totNet,
                'gl_bills' => $totGl,
            ];

            $viewData = [
                'title' => $resolvedShop->name.' — Cashbook Report',
                'shop' => $resolvedShop,
                'selectedDate' => $date,
                'timeframe' => $timeframe,
                'startDate' => $finalStart,
                'endDate' => $finalEnd,
                'totals' => $totals,
                'exportRows' => $rows,
            ];

            if ($request->boolean('download', false) || $request->input('download') === '1') {
                $pdf = Pdf::loadView('admin.cashbook.reports.pdf_download', $viewData)
                    ->setPaper('a4', 'portrait')
                    ->setOption(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true]);

                $fileName = "cashbook_{$resolvedShop->slug}_{$finalStart}_to_{$finalEnd}.pdf";

                return $pdf->download($fileName);
            }

            return view('admin.cashbook.reports.pdf', $viewData);
        }

        if ($format === 'excel') {
            return Excel::download(
                new PurchaserReportArrayExport($rows, $resolvedShop->name.' Report'),
                "cashbook_{$resolvedShop->slug}_{$finalStart}_to_{$finalEnd}.xlsx"
            );
        }

        // CSV Stream Download
        $filename = "cashbook_{$resolvedShop->slug}_{$finalStart}_to_{$finalEnd}.csv";

        return response()->streamDownload(function () use ($rows): void {
            $file = fopen('php://output', 'w');
            if ($file !== false) {
                foreach ($rows as $row) {
                    fputcsv($file, $row);
                }
                fclose($file);
            }
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function shopSettlementPage(Request $request, int|string $shop): View
    {
        $this->ensureMainAdmin($request);

        [$month, $monthStart, $monthEnd] = $this->paymentMonthWindow($request);
        $dateFrom = Carbon::parse((string) $request->input('date_from', $monthStart))->toDateString();
        $dateTo = Carbon::parse((string) $request->input('date_to', $monthEnd))->toDateString();
        $search = trim((string) $request->input('search', ''));
        $shops = $this->shopSyncService->syncAndGetProfiles();
        $shops->load('client');
        $company = config('greenleaf');
        $currentShop = $this->resolveShopPaymentWorkspace($shop);
        $currentShop->load('client', 'preset', 'shop');
        $shopPosition = $this->ledgerService->dailySummary((int) $currentShop->shop_id, $monthEnd);
        $paymentRequests = ShopInvoicePaymentRequest::query()
            ->with(['reconciliations.companyAccount', 'reconciliations.statementEntry.companyAccount'])
            ->withExists('ledgerAllocations')
            ->where('shop_id', $currentShop->shop_id)
            ->latest('payment_date')
            ->latest('id')
            ->limit(100)
            ->get();

        $finalizedPayments = $paymentRequests
            ->filter(fn (ShopInvoicePaymentRequest $payment): bool => $payment->reconciliation_status === 'reconciled'
                && $payment->reconciliations->contains(fn (CompanyPaymentReconciliation $reconciliation): bool => $reconciliation->is_finalized))
            ->values();
        $pendingPayments = $paymentRequests
            ->reject(fn (ShopInvoicePaymentRequest $payment): bool => $finalizedPayments->contains('id', $payment->id))
            ->take(20)
            ->values();
        $recentFinalizedPayments = $finalizedPayments->take(20)->values();

        $selectedPayment = null;
        if ($request->filled('payment_ref')) {
            $selectedPayment = $this->resolveSecurePaymentRequest((string) $request->input('payment_ref'));
            abort_unless((int) $selectedPayment->shop_id === (int) $currentShop->shop_id, 404);
            abort_unless($finalizedPayments->contains('id', $selectedPayment->id), 422);
        }

        $openTransactions = ShopLedgerTransaction::query()
            ->with('entryType')
            ->withSum('paymentLedgerAllocations as reconciled_amount', 'amount')
            ->where('shop_id', $currentShop->shop_id)
            ->whereDate('business_date', '<=', $monthEnd)
            ->whereNotIn('status', ['void', 'voided'])
            ->where('settlement_delta', '!=', 0)
            ->whereHas('entryType', fn (Builder $query): Builder => $query->where('code', '!=', 'shop_paid_company'))
            ->oldest('business_date')
            ->oldest('id')
            ->get()
            ->map(function (ShopLedgerTransaction $transaction): ShopLedgerTransaction {
                $transaction->reconciled_amount = round((float) ($transaction->reconciled_amount ?? 0), 2);
                $transaction->open_amount = round(max(0, abs((float) $transaction->settlement_delta) - $transaction->reconciled_amount), 2);
                $transaction->settlement_side = (float) $transaction->settlement_delta > 0 ? 'credit' : 'debit';

                return $transaction;
            })
            ->filter(fn (ShopLedgerTransaction $transaction): bool => (float) $transaction->open_amount > 0)
            ->values();

        return view('admin.cashbook.payments.shop', compact(
            'shops',
            'company',
            'currentShop',
            'month',
            'monthStart',
            'monthEnd',
            'shopPosition',
            'paymentRequests',
            'pendingPayments',
            'recentFinalizedPayments',
            'finalizedPayments',
            'selectedPayment',
            'openTransactions',
        ));
    }

    public function redirectShopSettlement(Request $request, int|string $shop): RedirectResponse
    {
        $this->ensureMainAdmin($request);
        $currentShop = $this->resolveShopPaymentWorkspace($shop);

        return redirect()->route('admin.cashbook.shop.accept-payment', [
            'shop' => $currentShop->uuid,
            'month' => $request->input('month', now()->format('Y-m')),
        ]);
    }

    public function recordShopPayment(StoreShopPaymentRequest $request, int|string $shop): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $currentShop = $this->resolveShopPaymentWorkspace($shop);
        $validated = $request->validated();
        $payment = DB::transaction(function () use ($currentShop, $validated, $request): ShopInvoicePaymentRequest {
            $shop = Shop::query()->whereKey($currentShop->shop_id)->lockForUpdate()->firstOrFail();
            $existingPayment = ShopInvoicePaymentRequest::query()
                ->where('submission_uuid', $validated['request_uuid'])
                ->lockForUpdate()
                ->first();

            if ($existingPayment instanceof ShopInvoicePaymentRequest) {
                abort_unless((int) $existingPayment->shop_id === (int) $shop->id, 422);

                return $existingPayment;
            }

            $invoice = ShopInvoice::query()
                ->where('shop_id', $shop->id)
                ->where('balance_amount', '>', 0)
                ->oldest('business_date')
                ->oldest('id')
                ->first();
            $amount = round((float) $validated['amount'], 2);
            $payment = ShopInvoicePaymentRequest::query()->create([
                'shop_invoice_id' => $invoice?->id,
                'shop_id' => $shop->id,
                'requested_by' => $request->user()->id,
                'submission_uuid' => $validated['request_uuid'],
                'request_type' => $invoice instanceof ShopInvoice ? 'admin_cashbook' : 'shop_balance',
                'payment_method' => $validated['payment_method'],
                'payment_reference' => filled($validated['payment_reference'] ?? null) ? trim((string) $validated['payment_reference']) : null,
                'payment_date' => $validated['payment_date'],
                'cheque_status' => $validated['payment_method'] === 'cheque' ? 'pending' : null,
                'cheque_bank_name' => $validated['cheque_bank_name'] ?? null,
                'cheque_date' => $validated['cheque_date'] ?? null,
                'requested_amount' => $amount,
                'applied_amount' => 0,
                'credit_amount' => 0,
                'status' => 'pending',
                'reconciliation_status' => 'floating',
                'reconciled_amount' => 0,
                'floating_amount' => $amount,
                'shop_advance_amount' => 0,
                'shop_note' => filled($validated['notes'] ?? null) ? trim((string) $validated['notes']) : null,
            ]);

            return $payment;
        }, attempts: 3);

        return redirect()->route('admin.cashbook.shop.accept-payment', [
            'shop' => $currentShop->uuid,
            'month' => Carbon::parse($payment->payment_date)->format('Y-m'),
        ])->with('success', 'Payment submission recorded. Match it to the actual company Cash/Bank statement before settling shop ledger rows.');
    }

    public function reconcileShopPaymentLedger(
        ReconcileShopPaymentLedgerRequest $request,
        int|string $shop,
    ): RedirectResponse {
        $this->ensureMainAdmin($request);

        $currentShop = $this->resolveShopPaymentWorkspace($shop);
        $validated = $request->validated();
        $paymentRequest = $this->resolveSecurePaymentRequest($validated['payment_ref']);
        $allocations = collect($validated['allocations'])
            ->map(fn (array $allocation): array => [
                'ledger_transaction_id' => $this->decodeFinanceRouteKey($allocation['ledger_ref'], 'shop-ledger'),
                'amount' => round((float) $allocation['amount'], 2),
            ])
            ->values()
            ->all();

        $this->shopPaymentLedgerReconciliationService->reconcile(
            $paymentRequest,
            (int) $currentShop->shop_id,
            $allocations,
            (int) $request->user()->id,
        );

        return redirect()->route('admin.cashbook.shop.accept-payment', [
            'shop' => $currentShop->uuid,
            'month' => $request->input('month', now()->format('Y-m')),
            'payment_ref' => $paymentRequest->secureRouteKey(),
        ])->with('success', 'Shop payment reconciled against the selected ledger transactions.');
    }

    public function rulesPage(Request $request): View
    {
        $this->ensureMainAdmin($request);

        return $this->renderApp('rules', 1);
    }

    public function settingsPage(Request $request): View
    {
        $this->ensureMainAdmin($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $clients = LedgerClient::with('shops')->where('enabled', true)->get();
        $entryTypes = LedgerEntryType::where('active', true)->orderBy('display_order')->get();
        $companyAccounts = CompanyAccount::where('enabled', true)->get();
        $company = config('greenleaf');
        $currentShop = $shops->first();

        return view('admin.cashbook.settings.index', compact(
            'shops', 'clients', 'entryTypes', 'companyAccounts', 'company', 'currentShop'
        ));
    }

    public function shopSettingsPage(Request $request, int|string $shop): View
    {
        $this->ensureMainAdmin($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $clients = LedgerClient::with('shops')->where('enabled', true)->get();
        $companyAccounts = CompanyAccount::where('enabled', true)->get();
        $company = config('greenleaf');
        $currentShop = $this->resolveShop($shop);
        $currentShop->load('client');
        $this->ensureShopSettings($currentShop);

        $settings = ShopLedgerEntrySetting::query()
            ->with('entryType')
            ->where('shop_id', $currentShop->shop_id)
            ->get()
            ->sortBy(fn (ShopLedgerEntrySetting $setting): int => (int) ($setting->entryType?->display_order ?? $setting->display_order))
            ->values();

        $settingsByCategory = $settings->groupBy(fn (ShopLedgerEntrySetting $setting): string => (string) ($setting->entryType?->category ?? 'other'));
        $collectionGroup = ShopLedgerCollectionGroup::query()
            ->where('shop_id', $currentShop->shop_id)
            ->where('code', 'collection')
            ->with('entryTypes.entryType')
            ->first();

        $bankAdjustmentRules = ShopBankSettlementAdjustmentRule::query()
            ->where('shop_id', $currentShop->shop_id)
            ->get()
            ->groupBy('entry_type_id');

        return view('admin.cashbook.settings.shop', compact(
            'shops', 'clients', 'companyAccounts', 'company', 'currentShop', 'settingsByCategory', 'collectionGroup', 'bankAdjustmentRules'
        ));
    }

    public function presetsPage(Request $request): View
    {
        $this->ensureMainAdmin($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $clients = LedgerClient::with('shops')->where('enabled', true)->get();
        $presets = ShopConfigPreset::with(['entrySettings.entryType', 'shops', 'collectionGroups.entryTypes.entryType'])->where('enabled', true)->get();
        $entryTypes = LedgerEntryType::where('active', true)->orderBy('display_order')->get();
        $companyAccounts = CompanyAccount::where('enabled', true)->get();
        $company = config('greenleaf');
        $currentShop = $shops->first();

        return view('admin.cashbook.settings.presets', compact(
            'shops', 'clients', 'presets', 'entryTypes', 'companyAccounts', 'company', 'currentShop'
        ));
    }

    public function collectionGroupsPage(Request $request): View
    {
        $this->ensureMainAdmin($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $clients = LedgerClient::with('shops')->where('enabled', true)->get();
        $presets = ShopConfigPreset::with(['shops', 'collectionGroups.entryTypes.entryType'])
            ->where('enabled', true)
            ->orderBy('name')
            ->get();
        $entryTypes = LedgerEntryType::where('active', true)->orderBy('display_order')->get();
        $companyAccounts = CompanyAccount::where('enabled', true)->get();
        $company = config('greenleaf');
        $currentShop = $shops->first();

        return view('admin.cashbook.settings.collections', compact(
            'shops', 'clients', 'presets', 'entryTypes', 'companyAccounts', 'company', 'currentShop'
        ));
    }

    public function createBankAccountPage(Request $request): View
    {
        $this->ensureMainAdmin($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $companyAccounts = CompanyAccount::orderBy('is_default', 'desc')->orderBy('name')->get();
        $company = config('greenleaf');
        $currentShop = $shops->first();

        return view('admin.cashbook.bank-accounts.create', compact(
            'shops', 'companyAccounts', 'company', 'currentShop'
        ));
    }

    public function showBankAccount(Request $request, CompanyAccount $account): View
    {
        $this->ensureMainAdmin($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $companyAccounts = CompanyAccount::orderBy('is_default', 'desc')->orderBy('name')->get();
        $company = config('greenleaf');
        $currentShop = $shops->first();

        $account->loadCount([
            'statementEntries as unmatched_statement_count' => fn ($query) => $query->whereIn('status', ['unmatched', 'partially_matched']),
            'statementEntries as total_statement_count',
        ]);

        $statementSummary = CompanyAccountStatementEntry::query()
            ->where('company_account_id', $account->id)
            ->selectRaw("SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END) as money_in")
            ->selectRaw("SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END) as money_out")
            ->selectRaw('SUM(matched_amount) as matched_total')
            ->first();

        $recentStatementEntries = $account->statementEntries()
            ->with(['sourceRecord.shop', 'sourceRecord.entryType', 'reconciliations.paymentRequest.shop'])
            ->latest('transaction_date')
            ->latest('id')
            ->limit(20)
            ->get();

        $recentReconciliations = CompanyPaymentReconciliation::query()
            ->with(['paymentRequest.shop', 'statementEntry', 'reconciledBy'])
            ->where('company_account_id', $account->id)
            ->latest('id')
            ->limit(12)
            ->get();

        $accountPosition = $this->moneyPositionService->getAccountPosition($account);
        $cashWithShops = $this->moneyPositionService->getCashWithShopsBreakdown();

        return view('admin.cashbook.bank-accounts.show', compact(
            'shops',
            'companyAccounts',
            'company',
            'currentShop',
            'account',
            'statementSummary',
            'recentStatementEntries',
            'recentReconciliations',
            'accountPosition',
            'cashWithShops',
        ));
    }

    public function showBankAccountStatement(Request $request, CompanyAccount $account): View
    {
        $this->ensureMainAdmin($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $companyAccounts = CompanyAccount::orderBy('is_default', 'desc')->orderBy('name')->get();
        $company = config('greenleaf');
        $currentShop = $shops->first();
        $statementMonth = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month'))
            ? (string) $request->input('month')
            : now()->format('Y-m');
        $monthStart = Carbon::createFromFormat('Y-m-d', $statementMonth.'-01')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $selectedTab = (string) $request->query('tab', 'all');

        $query = $account->statementEntries()
            ->with(['reconciliations.paymentRequest.shop', 'reconciliations.reconciledBy', 'sourceRecord.entryType', 'sourceRecord.shop'])
            ->whereBetween('transaction_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);

        if ($selectedTab === 'needs_verification') {
            $query->where('is_finalized', false)->where('direction', 'in');
        } elseif ($selectedTab === 'verified') {
            $query->where('is_finalized', true);
        } elseif ($selectedTab === 'needs_attention') {
            $query->where(function ($q): void {
                $q->where('duplicate_status', 'possible_duplicate')
                    ->orWhere('status', 'partially_matched');
            });
        }

        $statementEntries = $query
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $statementSummary = CompanyAccountStatementEntry::query()
            ->where('company_account_id', $account->id)
            ->whereBetween('transaction_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->selectRaw("SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END) as money_in")
            ->selectRaw("SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END) as money_out")
            ->selectRaw('SUM(matched_amount) as matched_total')
            ->first();

        $duplicateFlagCount = CompanyAccountStatementEntry::query()
            ->where('company_account_id', $account->id)
            ->where('duplicate_status', 'possible_duplicate')
            ->whereBetween('transaction_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        $accountPosition = $this->moneyPositionService->getAccountPosition($account);

        return view('admin.cashbook.bank-accounts.statement', compact(
            'shops',
            'companyAccounts',
            'company',
            'currentShop',
            'account',
            'statementEntries',
            'statementSummary',
            'statementMonth',
            'monthStart',
            'monthEnd',
            'duplicateFlagCount',
            'accountPosition',
            'selectedTab',
        ));
    }

    /**
     * Money Flow Landing Page.
     */
    public function moneyFlow(Request $request): View
    {
        $this->ensureMainAdmin($request);

        $businessDate = $request->query('date', today()->toDateString());
        $calendarMonth = $request->query('calendar_month');
        $shopId = $request->query('shop_id') ? (int) $request->query('shop_id') : null;
        $statusFilter = $request->query('status', 'all');

        $moneySummary = $this->moneyPositionService->getMoneyPositionSummary($businessDate);
        $moneyFlowItems = $this->moneyPositionService->getUnifiedMoneyFlowList($businessDate, $shopId, $statusFilter);
        $shops = $this->shopSyncService->syncAndGetProfiles();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 25;
        $itemsCollection = collect($moneyFlowItems);
        $paginatedItems = new LengthAwarePaginator(
            $itemsCollection->forPage($page, $perPage)->values(),
            $itemsCollection->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        $calendarData = $this->moneyPositionService->getMonthlyCalendarData($businessDate, $calendarMonth, $shopId);

        return view('admin.cashbook.money-flow.index', [
            'businessDate' => $businessDate,
            'selectedShopId' => $shopId,
            'selectedStatus' => $statusFilter,
            'summary' => $moneySummary,
            'items' => $paginatedItems,
            'calendarData' => $calendarData,
            'shops' => $shops,
        ]);
    }

    /**
     * Canonical Transaction Detail Page (Shop Collections).
     */
    public function showTransaction(Request $request, ShopLedgerTransaction $transaction): View
    {
        $this->ensureMainAdmin($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $presented = $this->transactionPresenter->present($transaction);

        return view('admin.cashbook.transactions.show', [
            'presented' => $presented,
            'transaction' => $transaction,
            'shops' => $shops,
        ]);
    }

    /**
     * Edit Transaction Form (unreconciled edit or reconciled correction).
     */
    public function editTransaction(Request $request, ShopLedgerTransaction $transaction): View
    {
        $this->ensureMainAdmin($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $entryTypes = LedgerEntryType::where('is_active', true)->orderBy('name')->get();
        $presented = $this->transactionPresenter->present($transaction);

        return view('admin.cashbook.transactions.edit', [
            'presented' => $presented,
            'transaction' => $transaction,
            'shops' => $shops,
            'entryTypes' => $entryTypes,
        ]);
    }

    /**
     * Update / Correct Transaction.
     */
    public function updateTransaction(Request $request, ShopLedgerTransaction $transaction): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'business_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'entry_type_id' => ['nullable', 'integer', 'exists:ledger_entry_types,id'],
            'reversal_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $isReconciled = CompanyAccountStatementEntry::query()
            ->where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $transaction->id)
            ->where('is_finalized', true)
            ->where('status', 'reconciled')
            ->exists();

        try {
            if ($isReconciled) {
                $reason = $validated['reversal_reason'] ?? 'Admin correction from transaction detail';
                $this->reversalService->correctReconciledTransaction($transaction, $validated, (int) $request->user()->id, $reason);

                return redirect()->route('admin.cashbook.transaction.show', $transaction->id)
                    ->with('success', 'Reconciled transaction corrected and financial effects reversed. Please approve and verify again.');
            } else {
                $this->reversalService->updateUnreconciledTransaction($transaction, $validated, (int) $request->user()->id);

                return redirect()->route('admin.cashbook.transaction.show', $transaction->id)
                    ->with('success', 'Transaction updated successfully.');
            }
        } catch (Throwable $e) {
            return redirect()->back()->with('error', 'Update failed: '.$e->getMessage())->withInput();
        }
    }

    /**
     * Reverse Finalized / Reconciled Transaction.
     */
    public function reverseTransaction(Request $request, ShopLedgerTransaction $transaction): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'confirm' => ['required', 'string', 'in:REVERSE'],
        ], [
            'confirm.in' => 'Please type REVERSE to confirm reversing this finalized transaction.',
        ]);

        try {
            $this->reversalService->reverseReconciledTransaction($transaction, (int) $request->user()->id, $validated['reason']);

            return redirect()->route('admin.cashbook.transaction.show', $transaction->id)
                ->with('success', 'Transaction reversed successfully. All financial effects have been rolled back and audit history preserved.');
        } catch (Throwable $e) {
            return redirect()->route('admin.cashbook.transaction.show', $transaction->id)
                ->with('error', 'Reversal failed: '.$e->getMessage());
        }
    }

    /**
     * Delete / Void Unreconciled Transaction.
     */
    public function deleteTransaction(Request $request, ShopLedgerTransaction $transaction): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $reason = (string) $request->input('reason', 'Deleted by admin');

        try {
            $this->reversalService->deleteUnreconciledTransaction($transaction, (int) $request->user()->id, $reason);

            return redirect()->route('admin.cashbook.money-flow')
                ->with('success', 'Unreconciled transaction deleted successfully.');
        } catch (Throwable $e) {
            return redirect()->route('admin.cashbook.transaction.show', $transaction->id)
                ->with('error', 'Deletion failed: '.$e->getMessage());
        }
    }

    /**
     * Action: Approve posted collection transaction.
     */
    public function approveTransaction(Request $request, ShopLedgerTransaction $transaction): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        if (in_array($transaction->status, ['void', 'voided'], true)) {
            return redirect()->route('admin.cashbook.transaction.show', $transaction->id)
                ->with('error', 'This transaction was voided and cannot be approved.');
        }

        if ($transaction->status === 'approved') {
            return redirect()->route('admin.cashbook.transaction.show', $transaction->id)
                ->with('info', 'This transaction has already been approved.');
        }

        try {
            $this->ledgerService->approveEntry($transaction, (int) $request->user()->id);

            return redirect()->route('admin.cashbook.transaction.show', $transaction->id)
                ->with('success', 'Collection approved successfully.');
        } catch (Throwable $e) {
            return redirect()->route('admin.cashbook.transaction.show', $transaction->id)
                ->with('error', 'Approval failed: '.$e->getMessage());
        }
    }

    /**
     * Action: Verify approved collection received into company account.
     */
    public function verifyTransaction(Request $request, ShopLedgerTransaction $transaction): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        if ($transaction->status !== 'approved') {
            return redirect()->route('admin.cashbook.transaction.show', $transaction->id)
                ->with('error', 'This transaction requires approval before verification.');
        }

        $statement = CompanyAccountStatementEntry::query()
            ->where('source_type', ShopLedgerTransaction::class)
            ->where('source_id', $transaction->id)
            ->first();

        if (! $statement) {
            return redirect()->route('admin.cashbook.transaction.show', $transaction->id)
                ->with('error', 'Destination account statement is missing or not configured for this collection.');
        }

        if ($statement->is_finalized && $statement->status === 'reconciled') {
            return redirect()->route('admin.cashbook.transaction.show', $transaction->id)
                ->with('info', 'This collection has already been verified.');
        }

        try {
            $this->companyPaymentReconciliationService->verifyPendingShopCollection($statement, (int) $request->user()->id);

            return redirect()->route('admin.cashbook.transaction.show', $transaction->id)
                ->with('success', 'Collection verified and confirmed received into company accounts.');
        } catch (Throwable $e) {
            return redirect()->route('admin.cashbook.transaction.show', $transaction->id)
                ->with('error', 'Verification failed: '.$e->getMessage());
        }
    }

    public function companyFinancePage(Request $request): View
    {
        $this->ensureMainAdmin($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $companyAccounts = CompanyAccount::query()
            ->where('enabled', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
        $pendingPaymentRequests = ShopInvoicePaymentRequest::query()
            ->with(['shop', 'invoice', 'requestedBy', 'reconciliations.companyAccount'])
            ->where('status', '!=', 'rejected')
            ->where(function ($query): void {
                $query->whereIn('status', ['pending', 'partially_reconciled'])
                    ->orWhereIn('reconciliation_status', ['pending', 'floating', 'partially_reconciled']);
            })
            ->latest('id')
            ->limit(40)
            ->get();
        $statementEntries = CompanyAccountStatementEntry::query()
            ->with(['companyAccount', 'sourceRecord.entryType', 'sourceRecord.shop'])
            ->whereIn('status', ['unmatched', 'partially_matched'])
            ->latest('transaction_date')
            ->latest('id')
            ->limit(60)
            ->get();
        $recentReconciliations = CompanyPaymentReconciliation::query()
            ->with(['paymentRequest.shop', 'companyAccount', 'statementEntry', 'reconciledBy'])
            ->latest('id')
            ->limit(30)
            ->get();
        $today = (string) $request->input('date', today()->toDateString());
        $chequeToBank = ShopInvoicePaymentRequest::query()
            ->where('payment_method', 'cheque')
            ->where('status', '!=', 'rejected')
            ->where(function ($query): void {
                $query->whereNull('cheque_status')
                    ->orWhere('cheque_status', 'pending');
            })
            ->where(function ($query) use ($today): void {
                $query->whereDate('payment_date', '<=', $today)
                    ->orWhereDate('cheque_date', '<=', $today)
                    ->orWhereDate('created_at', '<=', $today);
            })
            ->get();
        $reconciliationEntryTypes = LedgerEntryType::query()
            ->where('active', true)
            ->whereIn('code', ['reconciliation_adjustment', 'bank_charges', 'short_receipt', 'excess_receipt'])
            ->orderBy('display_order')
            ->get();
        $company = config('greenleaf');
        $currentShop = $shops->first();

        $totals = [
            'current_balance' => round((float) $companyAccounts->sum('current_balance'), 2),
            'bank_balance' => round((float) $companyAccounts->where('account_type', 'bank')->sum('current_balance'), 2),
            'liquid_cash' => round((float) $companyAccounts->where('account_type', 'cash')->sum('current_balance'), 2),
            'wallet_balance' => round((float) $companyAccounts->where('account_type', 'wallet')->sum('current_balance'), 2),
            'floating_payments' => round((float) $pendingPaymentRequests->sum(
                fn (ShopInvoicePaymentRequest $paymentRequest): float => (float) ($paymentRequest->floating_amount ?: $paymentRequest->requested_amount)
            ), 2),
            'pending_payments' => round((float) $pendingPaymentRequests
                ->where('status', 'pending')
                ->sum('requested_amount'), 2),
            'open_statement' => round((float) $statementEntries->sum(
                fn (CompanyAccountStatementEntry $entry): float => max(0, (float) $entry->amount - (float) $entry->matched_amount)
            ), 2),
            'cheque_to_bank_count' => $chequeToBank->count(),
            'cheque_to_bank_amount' => round((float) $chequeToBank->sum('requested_amount'), 2),
        ];

        $moneyPosition = $this->moneyPositionService->getMoneyPositionSummary($today);

        return view('admin.cashbook.finance.index', compact(
            'shops',
            'companyAccounts',
            'pendingPaymentRequests',
            'statementEntries',
            'recentReconciliations',
            'reconciliationEntryTypes',
            'company',
            'currentShop',
            'totals',
            'moneyPosition',
        ));
    }

    public function companyFinanceChequeSubmission(Request $request): View
    {
        $this->ensureMainAdmin($request);

        $date = Carbon::parse((string) $request->input('date', today()->toDateString()))->toDateString();
        $accountId = $request->integer('company_account_id') ?: null;

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $companyAccounts = CompanyAccount::query()
            ->where('enabled', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
        $selectedAccount = $accountId
            ? $companyAccounts->firstWhere('id', $accountId)
            : ($companyAccounts->where('account_type', 'bank')->firstWhere('is_default', true) ?? $companyAccounts->firstWhere('account_type', 'bank'));
        $company = config('greenleaf');
        $currentShop = $shops->first();

        $chequePayments = ShopInvoicePaymentRequest::query()
            ->with(['shop', 'requestedBy', 'reconciliations.companyAccount'])
            ->where('payment_method', 'cheque')
            ->where('status', '!=', 'rejected')
            ->where(function ($query): void {
                $query->whereNull('cheque_status')
                    ->orWhere('cheque_status', 'pending');
            })
            ->where(function ($query) use ($date): void {
                $query->whereDate('payment_date', '<=', $date)
                    ->orWhereDate('cheque_date', '<=', $date)
                    ->orWhereDate('created_at', '<=', $date);
            })
            ->latest('payment_date')
            ->latest('id')
            ->get();

        $totals = [
            'count' => $chequePayments->count(),
            'amount' => round((float) $chequePayments->sum('requested_amount'), 2),
            'floating' => round((float) $chequePayments->sum(
                fn (ShopInvoicePaymentRequest $paymentRequest): float => (float) ($paymentRequest->floating_amount ?: $paymentRequest->requested_amount)
            ), 2),
        ];

        return view('admin.cashbook.finance.cheque-submission', compact(
            'shops',
            'companyAccounts',
            'selectedAccount',
            'company',
            'currentShop',
            'date',
            'chequePayments',
            'totals',
        ));
    }

    public function companyIncomeExpense(Request $request): View
    {
        $this->ensureMainAdmin($request);

        $type = (string) $request->input('type', 'income');
        $query = CompanyAccountingEntry::query()
            ->with(['category.account', 'companyAccount', 'cashbookMovement', 'journalEntry'])
            ->whereNotNull('company_account_id')
            ->when(in_array($type, ['income', 'expense'], true), fn (Builder $query) => $query->where('type', $type))
            ->when($request->filled('category'), fn (Builder $query) => $query->where('company_accounting_category_id', $request->integer('category')))
            ->when($request->filled('company_account'), fn (Builder $query) => $query->where('company_account_id', $request->integer('company_account')))
            ->when($request->filled('start_date'), fn (Builder $query) => $query->whereDate('business_date', '>=', $request->input('start_date')))
            ->when($request->filled('end_date'), fn (Builder $query) => $query->whereDate('business_date', '<=', $request->input('end_date')))
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $request->input('status') === 'finalized'
                    ? $query->whereHas('cashbookMovement', fn (Builder $movement) => $movement->where('is_finalized', true))
                    : $query->whereDoesntHave('cashbookMovement', fn (Builder $movement) => $movement->where('is_finalized', true));
            })
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = '%'.trim((string) $request->input('search')).'%';
                $query->where(fn (Builder $searchQuery) => $searchQuery->where('reference', 'like', $search)->orWhere('description', 'like', $search));
            })
            ->latest('business_date')
            ->latest('id');

        return view('admin.cashbook.finance.income_expense', array_merge($this->cashbookLayoutData(), [
            'activeType' => $type,
            'entries' => $query->paginate(25)->withQueryString(),
            'incomeCategories' => CompanyAccountingCategory::query()->with('account')->where('type', 'income')->where('is_active', true)->orderBy('name')->get(),
            'expenseCategories' => CompanyAccountingCategory::query()->with('account')->where('type', 'expense')->where('is_active', true)->orderBy('name')->get(),
            'companyAccounts' => CompanyAccount::query()->where('enabled', true)->orderBy('name')->get(),
        ]));
    }

    public function storeCompanyIncomeExpense(StoreCompanyAccountingCashbookEntryRequest $request): RedirectResponse
    {
        $entry = $this->companyAccountingCashbookService->create($request->validated(), (int) $request->user()->id);

        return redirect()->route('admin.cashbook.finance.income-expense', ['type' => $entry->type])
            ->with('success', 'Company '.$entry->type.' created. Reconcile company movement before it appears in All Transactions.');
    }

    public function showCompanyIncomeExpense(Request $request, CompanyAccountingEntry $entry): View
    {
        $this->ensureMainAdmin($request);
        abort_unless($entry->company_account_id !== null, 404);

        return view('admin.cashbook.finance.income_expense_show', array_merge($this->cashbookLayoutData(), [
            'entry' => $entry->load(['category.account', 'companyAccount', 'cashbookMovement.journalEntry.transactions.account', 'creator']),
        ]));
    }

    /**
     * @return array{count:int, amount:float}
     */
    private function pendingReconciliationSummary(): array
    {
        $journal = JournalEntry::query()
            ->whereIn('source_type', $this->matchableCashbookSourceTypes())
            ->whereDoesntHave('statementEntries', fn (Builder $query) => $query->where('is_finalized', true))
            ->whereHas('transactions', fn (Builder $query) => $query->whereHas('account', fn (Builder $accountQuery) => $accountQuery->whereIn('code', ['1010', '1020'])));

        $journalCount = (clone $journal)->count();
        $journalAmount = (float) JournalTransaction::query()
            ->whereIn('journal_entry_id', (clone $journal)->select('id'))
            ->whereHas('account', fn (Builder $query) => $query->whereIn('code', ['1010', '1020']))
            ->sum('amount');

        $shopPayments = ShopInvoicePaymentRequest::query()
            ->where('status', '!=', 'rejected')
            ->whereDoesntHave('allocations')
            ->whereDoesntHave('reconciliations', fn (Builder $query) => $query->where('is_finalized', true))
            ->where(function (Builder $query): void {
                $query->whereIn('status', ['pending', 'partially_reconciled'])
                    ->orWhereIn('reconciliation_status', ['pending', 'floating', 'partially_reconciled']);
            });

        return [
            'count' => $journalCount + (clone $shopPayments)->count(),
            'amount' => round($journalAmount + (float) (clone $shopPayments)->sum('floating_amount'), 2),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array{kind:string, reference:string, source:string, counterparty:string, amount:float, date:string, method:string, account:string, reference_label:string, status:string}>
     */
    private function pendingReconciliationSources(Request $request, string $monthStart, string $monthEnd, string $direction, string $search): LengthAwarePaginator
    {
        $journalRows = JournalEntry::query()
            ->with(['transactions.account', 'statementEntries'])
            ->whereIn('source_type', $this->matchableCashbookSourceTypes())
            ->whereDoesntHave('statementEntries', fn (Builder $query) => $query->where('is_finalized', true))
            ->whereHas('transactions', function (Builder $query) use ($direction): void {
                $query->whereHas('account', fn (Builder $accountQuery) => $accountQuery->whereIn('code', ['1010', '1020']))
                    ->when($direction === 'in', fn (Builder $cashQuery) => $cashQuery->where('type', 'debit'))
                    ->when($direction === 'out', fn (Builder $cashQuery) => $cashQuery->where('type', 'credit'));
            })
            ->whereBetween('entry_date', [$monthStart, $monthEnd])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $sub) => $sub->where('reference', 'like', '%'.$search.'%')->orWhere('description', 'like', '%'.$search.'%')->orWhere('source_event', 'like', '%'.$search.'%')))
            ->latest('entry_date')->latest('id')->limit(100)->get()
            ->filter(fn (JournalEntry $entry): bool => $entry->is_balanced && $entry->primary_amount > 0)
            ->map(function (JournalEntry $entry): array {
                $cashTransaction = $entry->transactions->first(fn ($transaction) => in_array($transaction->account?->code, ['1010', '1020'], true));

                return [
                    'kind' => 'journal',
                    'reference' => $this->secureJournalEntryKey($entry),
                    'source' => preg_replace('/ #\\d+$/', '', $entry->source_label) ?: 'Cashbook Transaction',
                    'counterparty' => $entry->description ?: $entry->reference ?: 'Company transaction',
                    'amount' => $entry->primary_amount,
                    'date' => $entry->entry_date?->toDateString() ?? '',
                    'method' => $cashTransaction?->account?->code === '1010' ? 'Cash' : 'Bank',
                    'account' => $cashTransaction?->account?->name ?? 'Company account',
                    'reference_label' => $entry->reference ?: $entry->formatted_reference,
                    'description' => $entry->description ?: 'No description provided',
                ];
            });

        $shopRows = ShopInvoicePaymentRequest::query()
            ->with(['shop', 'reconciliations'])
            ->withExists('allocations')
            ->where('status', '!=', 'rejected')
            ->whereDoesntHave('allocations')
            ->whereDoesntHave('reconciliations', fn (Builder $query) => $query->where('is_finalized', true))
            ->where(function (Builder $query): void {
                $query->whereIn('status', ['pending', 'partially_reconciled'])
                    ->orWhereIn('reconciliation_status', ['pending', 'floating', 'partially_reconciled']);
            })
            ->when($direction === 'out', fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->whereBetween('payment_date', [$monthStart, $monthEnd])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $sub) => $sub->where('payment_reference', 'like', '%'.$search.'%')->orWhere('shop_note', 'like', '%'.$search.'%')->orWhereHas('shop', fn (Builder $shopQuery) => $shopQuery->where('name', 'like', '%'.$search.'%'))))
            ->latest('payment_date')->latest('id')->limit(100)->get()
            ->map(fn (ShopInvoicePaymentRequest $payment): array => [
                'kind' => 'shop_payment',
                'reference' => $payment->secureRouteKey(),
                'source' => 'Shop Payment',
                'counterparty' => $payment->shop?->name ?? 'Shop',
                'amount' => $this->shopPaymentFloatingAmount($payment),
                'date' => $payment->payment_date?->toDateString() ?? '',
                'method' => $payment->paymentMethodLabel(),
                'account' => $payment->payment_method === 'cash' ? 'Company Cash' : 'Company Bank',
                'reference_label' => $payment->payment_reference ?: 'No reference',
                'description' => $payment->shop_note ?: 'No note provided',
            ]);

        $shopCollectionRows = ShopLedgerTransaction::query()
            ->with(['shop', 'entryType', 'companyAccount'])
            ->where('direction', 'income')
            ->whereNotNull('company_account_id')
            ->whereNotIn('status', ['void', 'voided'])
            ->whereBetween('business_date', [$monthStart, $monthEnd])
            ->when($direction === 'out', fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $sub) => $sub->where('notes', 'like', '%'.$search.'%')
                ->orWhere('reference_id', 'like', '%'.$search.'%')
                ->orWhereHas('shop', fn (Builder $sq) => $sq->where('name', 'like', '%'.$search.'%'))
                ->orWhereHas('entryType', fn (Builder $eq) => $eq->where('name', 'like', '%'.$search.'%'))))
            ->whereDoesntHave('statementEntries', fn (Builder $query) => $query->where('is_finalized', true))
            ->latest('business_date')->latest('id')->limit(100)->get()
            ->map(fn (ShopLedgerTransaction $tx): array => [
                'kind' => 'shop_ledger',
                'reference' => $tx->secureRouteKey(),
                'source' => ($tx->shop?->name ?? 'Shop').' · '.($tx->entryType?->name ?? 'Collection'),
                'counterparty' => $tx->shop?->name ?? 'Shop',
                'amount' => (float) $tx->amount,
                'date' => $tx->business_date?->toDateString() ?? '',
                'method' => $tx->entryType?->name ?? 'Online Collection',
                'account' => $tx->companyAccount?->name ?? 'Company Bank',
                'reference_label' => $tx->reference_id ?: 'No reference',
                'description' => $tx->notes ?: (($tx->shop?->name ?? 'Shop').' '.($tx->entryType?->name ?? 'Collection').' dated '.($tx->business_date?->toDateString() ?? '')),
            ]);

        $rows = collect($journalRows->all())
            ->merge($shopRows->all())
            ->merge($shopCollectionRows->all())
            ->sortByDesc('date')
            ->values();
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator($rows->forPage($page, 20)->values(), $rows->count(), 20, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'query' => $request->query(),
        ]);
    }

    /**
     * @return array{0: array{kind:string, reference:string, source:string, counterparty:string, amount:float, direction:string, date:string, method:string, reference_label:string, description:string, details:array<string, string>}|null, 1: array<string, mixed>}
     */
    private function pendingSourceStatementFinder(Request $request, int $graceDays, string $search): array
    {
        $kind = (string) $request->input('find_kind');
        $reference = (string) $request->input('find_ref');
        if (! in_array($kind, ['journal', 'shop_payment', 'shop_ledger'], true) || $reference === '') {
            return [null, ['pending' => [], 'reconciled' => [], 'counts' => ['pending' => 0, 'reconciled' => 0, 'exact_date_pending' => 0, 'exact_date_reconciled' => 0]]];
        }

        if ($kind === 'shop_payment') {
            $paymentId = $this->tryDecodeFinanceRouteKey($reference, 'shop-payment');
            if ($paymentId === null) {
                return [null, ['pending' => [], 'reconciled' => [], 'counts' => ['pending' => 0, 'reconciled' => 0, 'exact_date_pending' => 0, 'exact_date_reconciled' => 0]]];
            }
            $payment = ShopInvoicePaymentRequest::query()->with(['shop', 'reconciliations'])->withExists('allocations')->find($paymentId);
            if (! $payment) {
                return [null, ['pending' => [], 'reconciled' => [], 'counts' => ['pending' => 0, 'reconciled' => 0, 'exact_date_pending' => 0, 'exact_date_reconciled' => 0]]];
            }
            $source = [
                'kind' => $kind,
                'reference' => $reference,
                'source' => 'Shop Payment',
                'counterparty' => $payment->shop?->name ?? 'Shop',
                'amount' => $this->shopPaymentFloatingAmount($payment),
                'direction' => 'in',
                'date' => $payment->payment_date?->toDateString() ?? '',
                'method' => $payment->paymentMethodLabel(),
                'reference_label' => $payment->payment_reference ?: 'No reference',
                'description' => $payment->shop_note ?: 'No note provided',
                'details' => [
                    'Shop Name' => $payment->shop?->name ?? 'Shop',
                    'Amount Submitted' => '₹'.number_format((float) $payment->requested_amount, 2),
                    'Payment Method' => $payment->paymentMethodLabel(),
                    'Reference / Cheque No' => $payment->payment_reference ?: 'No reference',
                    'Submitted Date' => $payment->payment_date?->format('Y-m-d') ?? '—',
                    'Notes' => $payment->shop_note ?: '—',
                    'Current Status' => 'Pending Reconciliation',
                ],
            ];
            $direction = 'in';
            $amount = (float) $source['amount'];
            $date = $payment->payment_date ?: today();
            $companyAccountId = null;
        } elseif ($kind === 'shop_ledger') {
            $txId = $this->tryDecodeFinanceRouteKey($reference, 'shop-ledger');
            if ($txId === null) {
                return [null, ['pending' => [], 'reconciled' => [], 'counts' => ['pending' => 0, 'reconciled' => 0, 'exact_date_pending' => 0, 'exact_date_reconciled' => 0]]];
            }
            $tx = ShopLedgerTransaction::query()->with(['shop', 'entryType', 'companyAccount'])->find($txId);
            if (! $tx) {
                return [null, ['pending' => [], 'reconciled' => [], 'counts' => ['pending' => 0, 'reconciled' => 0, 'exact_date_pending' => 0, 'exact_date_reconciled' => 0]]];
            }
            $shopName = $tx->shop?->name ?? 'Shop';
            $entryTypeName = $tx->entryType?->name ?? 'Collection';
            $amount = round((float) $tx->amount, 2);
            $date = $tx->business_date ?: today();
            $direction = $tx->direction === 'expense' ? 'out' : 'in';
            $companyAccountId = $tx->company_account_id;

            $source = [
                'kind' => $kind,
                'reference' => $reference,
                'source' => "{$shopName} · {$entryTypeName}",
                'counterparty' => $shopName,
                'amount' => $amount,
                'direction' => $direction,
                'date' => $date->toDateString(),
                'method' => $entryTypeName,
                'reference_label' => $tx->reference_id ?: 'No reference',
                'description' => $tx->notes ?: "{$shopName} {$entryTypeName} dated {$date->toDateString()}",
                'details' => [
                    'Shop' => $shopName,
                    'Category / Entry Type' => $entryTypeName,
                    'Business Date' => $date->toDateString(),
                    'Amount' => '₹'.number_format($amount, 2),
                    'Destination Bank' => $tx->companyAccount?->name ?? '—',
                    'Notes' => $tx->notes ?: '—',
                ],
            ];
        } else {
            $journalId = $this->tryDecodeFinanceRouteKey($reference, 'journal-entry');
            if ($journalId === null) {
                return [null, ['pending' => [], 'reconciled' => [], 'counts' => ['pending' => 0, 'reconciled' => 0, 'exact_date_pending' => 0, 'exact_date_reconciled' => 0]]];
            }
            $journal = JournalEntry::query()->with(['transactions.account', 'statementEntries'])->find($journalId);
            if (! $journal) {
                return [null, ['pending' => [], 'reconciled' => [], 'counts' => ['pending' => 0, 'reconciled' => 0, 'exact_date_pending' => 0, 'exact_date_reconciled' => 0]]];
            }
            $cashTransaction = $journal->transactions->first(fn ($transaction) => in_array($transaction->account?->code, ['1010', '1020'], true));
            if (! ($journal->is_balanced && $cashTransaction)) {
                return [null, ['pending' => [], 'reconciled' => [], 'counts' => ['pending' => 0, 'reconciled' => 0, 'exact_date_pending' => 0, 'exact_date_reconciled' => 0]]];
            }
            $companyEntry = $journal->source_type === CompanyAccountingEntry::class
                ? CompanyAccountingEntry::query()->with(['category', 'companyAccount'])->find($journal->source_id)
                : null;
            $details = $companyEntry ? [
                'Category' => $companyEntry->category?->name ?? '—',
                'Description / Notes' => $companyEntry->description ?: '—',
                'Amount' => '₹'.number_format((float) $companyEntry->amount, 2),
                'Date' => $companyEntry->business_date?->format('Y-m-d') ?? '—',
                'Company Account' => $companyEntry->companyAccount?->name ?? '—',
                'Reference' => $companyEntry->reference ?: '—',
            ] : [
                'Description / Note' => $journal->description ?: '—',
                'Journal Reference' => $journal->reference ?: $journal->formatted_reference,
                'Cash / Bank Account' => $cashTransaction->account?->name ?? '—',
            ];
            $source = [
                'kind' => $kind,
                'reference' => $reference,
                'source' => preg_replace('/ #\\d+$/', '', $journal->source_label) ?: 'Cashbook Transaction',
                'counterparty' => $journal->description ?: $journal->reference ?: 'Company transaction',
                'amount' => round($journal->primary_amount - (float) $journal->statementEntries->sum('matched_amount'), 2),
                'direction' => $cashTransaction->type === 'debit' ? 'in' : 'out',
                'date' => $journal->entry_date?->toDateString() ?? '',
                'method' => $cashTransaction->account?->code === '1010' ? 'Cash' : 'Bank',
                'reference_label' => $journal->reference ?: $journal->formatted_reference,
                'description' => $journal->description ?: 'No description provided',
                'details' => $details,
            ];
            $direction = $source['direction'];
            $amount = (float) $source['amount'];
            $date = $journal->entry_date ?: today();
            $companyAccountId = $companyEntry?->company_account_id;
        }

        $candidatesData = $this->companyPaymentReconciliationService->findStatementCandidates(
            companyAccountId: $companyAccountId,
            amount: $amount,
            direction: $direction,
            referenceDate: $date,
            search: $search
        );

        return [$source, $candidatesData];
    }

    /**
     * @return array{shops: Collection<int, Shop>, company: array<string, mixed>, currentShop: ?Shop}
     */
    private function cashbookLayoutData(): array
    {
        $shops = $this->shopSyncService->syncAndGetProfiles();

        return [
            'shops' => $shops,
            'company' => config('greenleaf'),
            'currentShop' => $shops->first(),
        ];
    }

    public function directCompanySales(Request $request): View
    {
        $this->ensureMainAdmin($request);

        $query = DirectCompanySale::query()
            ->with(['companyAccount', 'journalEntry', 'cashbookMovement', 'shop', 'items.product'])
            ->whereBetween('business_date', [
                Carbon::parse((string) $request->input('month', today()->format('Y-m')).'-01')->startOfMonth()->toDateString(),
                Carbon::parse((string) $request->input('month', today()->format('Y-m')).'-01')->endOfMonth()->toDateString(),
            ])
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = '%'.trim((string) $request->input('search')).'%';
                $query->where(fn (Builder $searchQuery) => $searchQuery
                    ->where('customer_name', 'like', $search)
                    ->orWhere('reference', 'like', $search)
                    ->orWhere('public_uuid', 'like', $search));
            })
            ->latest('business_date')
            ->latest('id');

        $directSaleShop = $this->configuredDirectSaleShopForDisplay();

        return view('admin.cashbook.finance.direct_sales', [
            'sales' => $query->paginate(25)->withQueryString(),
            'companyAccounts' => CompanyAccount::query()->where('enabled', true)->orderBy('name')->get(),
            'month' => (string) $request->input('month', today()->format('Y-m')),
            'search' => trim((string) $request->input('search', '')),
            'shops' => $this->shopSyncService->syncAndGetProfiles(),
            'directSaleShop' => $directSaleShop,
            'productOptions' => $this->directSaleProductOptions($directSaleShop, $request->input('business_date', today()->toDateString())),
        ]);
    }

    public function storeDirectCompanySale(StoreDirectCompanySaleRequest $request): RedirectResponse
    {
        $this->directCompanySaleInventoryService->create($request->validated(), (int) $request->user()->id);

        return redirect()->route('admin.cashbook.finance.direct-sales')
            ->with('success', 'Direct sale confirmed. Reconcile company movement before it appears in All Transactions.');
    }

    public function showDirectCompanySale(Request $request, DirectCompanySale $directCompanySale): View
    {
        $this->ensureMainAdmin($request);

        return view('admin.cashbook.finance.direct_sales_show', [
            'sale' => $directCompanySale->load(['companyAccount', 'journalEntry.transactions.account', 'cashbookMovement.reconciledBy', 'shop', 'items.product', 'items.warehouse']),
            'shops' => $this->shopSyncService->syncAndGetProfiles(),
        ]);
    }

    public function directCompanySaleBill(Request $request, DirectCompanySale $directCompanySale): View
    {
        $this->ensureMainAdmin($request);

        return view('admin.cashbook.finance.direct_sales_bill', [
            'sale' => $directCompanySale->load(['companyAccount', 'shop', 'items.product']),
        ]);
    }

    private function configuredDirectSaleShopForDisplay(): ?Shop
    {
        $shopId = (int) (BusinessSetting::query()->where('key', 'default_direct_sale_shop_id')->value('value') ?? 0);

        if ($shopId <= 0) {
            return null;
        }

        return Shop::query()->with('priceGroup')->whereKey($shopId)->where('status', 'active')->first();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function directSaleProductOptions(?Shop $shop, mixed $businessDate): Collection
    {
        if (! $shop instanceof Shop) {
            return collect();
        }

        return Product::query()
            ->where('is_active', true)
            ->with(['category', 'orderUnits' => fn ($query) => $query->where('is_orderable', true)])
            ->ordered()
            ->get()
            ->map(function (Product $product) use ($shop, $businessDate): array {
                try {
                    $price = $this->approvedDailyPriceResolver->resolve($product, $shop, (string) $businessDate);
                } catch (Throwable $throwable) {
                    $price = null;
                }

                return [
                    'uuid' => $product->public_uuid,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'category' => $product->category?->name,
                    'price' => $price ? (float) $price['price'] : null,
                    'price_unit' => $price ? ProductUnit::normalizeUnit((string) $price['price_unit']) : null,
                    'price_source' => $price['source'] ?? null,
                    'units' => $product->orderUnits
                        ->filter(fn (ProductUnit $unit): bool => (float) $unit->conversion_to_base > 0)
                        ->map(fn (ProductUnit $unit): array => [
                            'unit' => ProductUnit::normalizeUnit((string) $unit->unit),
                            'label' => $unit->label,
                            'conversion_to_base' => (float) $unit->conversion_to_base,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn (array $product): bool => $product['price'] !== null && $product['units'] !== [])
            ->values();
    }

    public function companyFinanceJournal(Request $request): View
    {
        $this->ensureMainAdmin($request);

        $activeTab = (string) $request->input('tab', 'all');
        $status = (string) $request->input('status', 'all');
        $startDate = (string) $request->input('start_date', '');
        $endDate = (string) $request->input('end_date', '');
        $search = trim((string) $request->input('search', ''));
        $accountId = (int) $request->input('company_account_id', 0);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $companyAccounts = CompanyAccount::where('enabled', true)->orderBy('name')->get();
        $company = config('greenleaf');
        $currentShop = $shops->first();

        $query = JournalEntry::query()
            ->with([
                'transactions.account',
                'createdBy',
                'reconciliations.statementEntry.companyAccount',
                'reconciliations.paymentRequest.shop',
                'statementEntries.companyAccount',
            ])
            ->whereHas('transactions.account', fn ($q) => $q->whereIn('code', ['1010', '1020']))
            ->where(function ($q): void {
                $q->whereHas('statementEntries', fn ($sq) => $sq->where('is_finalized', true))
                    ->orWhereHas('reconciliations', fn ($rq) => $rq->where('is_finalized', true));
            });

        // Date filter
        if ($startDate !== '') {
            $query->whereDate('entry_date', '>=', $startDate);
        }
        if ($endDate !== '') {
            $query->whereDate('entry_date', '<=', $endDate);
        }

        // Account filter
        if ($accountId > 0) {
            $selectedAccount = $companyAccounts->firstWhere('id', $accountId);
            if ($selectedAccount) {
                $query->where(function (Builder $accountQuery) use ($selectedAccount): void {
                    $accountQuery->whereHas('statementEntries', function (Builder $statementQuery) use ($selectedAccount): void {
                        $statementQuery->where('company_account_id', $selectedAccount->id)
                            ->where('is_finalized', true);
                    })->orWhereHas('reconciliations', function (Builder $reconciliationQuery) use ($selectedAccount): void {
                        $reconciliationQuery->where('company_account_id', $selectedAccount->id)
                            ->where('is_finalized', true);
                    });
                });
            }
        }

        // Search filter
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('reference', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('id', 'like', '%'.$search.'%')
                    ->orWhere('source_event', 'like', '%'.$search.'%');
            });
        }

        // Tab filter
        match ($activeTab) {
            'bank' => $query->whereHas('transactions.account', fn ($q) => $q->where('code', '1020')),
            'cash' => $query->whereHas('transactions.account', fn ($q) => $q->where('code', '1010')),
            'income' => $query->whereHas('transactions.account', fn ($q) => $q->where('type', 'revenue')->orWhere('code', '4100')),
            'expense' => $query->whereHas('transactions.account', fn ($q) => $q->where('type', 'expense')->orWhere('code', 'like', '5%')),
            'purchaser_funding' => $query->where('source_type', PurchaserCredit::class)->where('source_event', 'purchaser_funding'),
            'vendor_payment' => $query->where(function ($q): void {
                $q->whereIn('source_type', [PurchaseInvoice::class, CompanyPayableSettlement::class])
                    ->orWhere('source_event', 'like', '%purchaser_daily_purchase_payment%')
                    ->orWhere('source_event', 'like', '%company_vendor_credit_payment%')
                    ->orWhereHas('transactions.account', fn ($sq) => $sq->where('code', '2100'));
            }),
            'customer_receipt' => $query->where(function ($q): void {
                $q->whereIn('source_type', [ShopInvoicePaymentRequest::class, ShopInvoice::class, Payment::class])
                    ->orWhere('source_event', 'like', '%client-balance-payment%')
                    ->orWhere('source_event', 'like', '%payment:paid%')
                    ->orWhereHas('transactions.account', fn ($sq) => $sq->where('code', '1100'));
            }),
            'transfer' => $query->whereHas('transactions', fn ($q) => $q->where('type', 'debit')->whereHas('account', fn ($aq) => $aq->whereIn('code', ['1010', '1020'])))
                ->whereHas('transactions', fn ($q) => $q->where('type', 'credit')->whereHas('account', fn ($aq) => $aq->whereIn('code', ['1010', '1020']))),
            'adjustment' => $query->where(function ($q): void {
                $q->where('source_event', 'like', '%reversal%')
                    ->orWhere('source_event', 'like', '%adjustment%')
                    ->orWhere('source_type', WastageEntry::class);
            }),
            default => null,
        };

        // Reconciliation Status filter
        match ($status) {
            'finalized' => $query->where(function ($q): void {
                $q->whereHas('statementEntries', fn ($sq) => $sq->where('is_finalized', true))
                    ->orWhereHas('reconciliations', fn ($rq) => $rq->where('is_finalized', true));
            }),
            'reconciled' => $query->where(function ($q): void {
                $q->whereHas('statementEntries', fn ($sq) => $sq->where('status', 'reconciled'))
                    ->orWhereHas('reconciliations', fn ($rq) => $rq->where('status', 'approved'));
            }),
            'partially_reconciled' => $query->where(function ($q): void {
                $q->whereHas('statementEntries', fn ($sq) => $sq->where('status', 'partially_matched'))
                    ->orWhereHas('reconciliations', fn ($rq) => $rq->where('status', 'partially_matched'));
            }),
            'unreconciled' => $query->whereDoesntHave('statementEntries')->whereDoesntHave('reconciliations'),
            default => null,
        };

        // KPI calculation over current query
        $totalEntriesCount = (clone $query)->count();
        $totalVolume = (float) DB::table('journal_transactions')
            ->whereIn('journal_entry_id', (clone $query)->select('id'))
            ->where('type', 'debit')
            ->sum('amount');
        $finalizedCount = (clone $query)->where(function ($q): void {
            $q->whereHas('statementEntries', fn ($sq) => $sq->where('is_finalized', true))
                ->orWhereHas('reconciliations', fn ($rq) => $rq->where('is_finalized', true));
        })->count();
        $unreconciledCount = (clone $query)->whereDoesntHave('statementEntries')->whereDoesntHave('reconciliations')->count();

        $totals = [
            'count' => $totalEntriesCount,
            'volume' => round($totalVolume, 2),
            'finalized_count' => $finalizedCount,
            'unreconciled_count' => $unreconciledCount,
        ];

        $journalEntries = $query->latest('entry_date')->latest('id')->paginate(30)->withQueryString();

        return view('admin.cashbook.finance.journal', compact(
            'shops',
            'companyAccounts',
            'company',
            'currentShop',
            'journalEntries',
            'totals',
            'activeTab',
            'status',
            'startDate',
            'endDate',
            'search',
            'accountId',
        ));
    }

    public function companyFinanceJournalEntryShow(Request $request, JournalEntry $journalEntry): View
    {
        $this->ensureMainAdmin($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $companyAccounts = CompanyAccount::where('enabled', true)->orderBy('name')->get();
        $company = config('greenleaf');
        $currentShop = $shops->first();

        $journalEntry->load([
            'transactions.account',
            'createdBy',
            'statementEntries.companyAccount',
            'statementEntries.reconciledBy',
            'reconciliations.companyAccount',
            'reconciliations.paymentRequest.shop',
            'reconciliations.reconciledBy',
        ]);

        return view('admin.cashbook.finance.journal-entry-show', compact(
            'shops',
            'companyAccounts',
            'company',
            'currentShop',
            'journalEntry',
        ));
    }

    public function companyFinancePurchasers(Request $request): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $parameters = ['period' => 'month'];
        if ($request->filled(['start_date', 'end_date'])) {
            $parameters = [
                'period' => 'custom',
                'start_date' => (string) $request->input('start_date'),
                'end_date' => (string) $request->input('end_date'),
            ];
        }
        if ($request->filled('search')) {
            $parameters['search'] = trim((string) $request->input('search'));
        }

        return redirect()->route('admin.cashbook.finance.purchase.purchasers', $parameters);
    }

    public function companyFinancePurchaseDashboard(Request $request, PurchaseReportingService $purchaseReportingService): View
    {
        $this->ensureMainAdmin($request);

        $filters = $this->purchaseDashboardFilters($request);
        $dashboard = $purchaseReportingService->dashboard($filters);

        return view('admin.cashbook.finance.purchase.dashboard', array_merge(
            $this->purchaseLayoutData(),
            compact('filters', 'dashboard')
        ));
    }

    public function companyFinancePurchaseReport(Request $request): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        return redirect()->route('admin.cashbook.finance.purchase.reports.purchasers', $request->query());
    }

    public function companyFinancePurchasePurchaserReport(Request $request, PurchaseReportingService $purchaseReportingService): View
    {
        $this->ensureMainAdmin($request);

        $filters = $this->purchaseReportFilters($request, 'month');
        $report = $purchaseReportingService->report($filters);
        $options = $purchaseReportingService->options($filters);

        return view('admin.cashbook.finance.purchase.reports.purchasers', array_merge(
            $this->purchaseLayoutData(),
            compact('filters', 'report', 'options')
        ));
    }

    public function companyFinancePurchaseReports(Request $request): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        return redirect()->route('admin.cashbook.finance.purchase.reports.credit-purchases', $request->query());
    }

    public function companyFinancePurchasePriceReport(PurchasePriceReportRequest $request, PurchasePriceReportingService $priceReportingService): View
    {
        $this->ensureMainAdmin($request);

        $filters = $request->priceFilters();
        $rows = $priceReportingService->priceReport($filters);
        $options = $priceReportingService->options();
        $activePriceGroups = $priceReportingService->activePriceGroups();

        return view('admin.cashbook.finance.purchase.reports.prices', array_merge($this->purchaseLayoutData(), compact('filters', 'rows', 'options', 'activePriceGroups')));
    }

    public function companyFinancePurchasePriceReportPdf(PurchasePriceReportRequest $request, PurchasePriceReportingService $priceReportingService): mixed
    {
        $this->ensureMainAdmin($request);

        $filters = $request->priceFilters();
        $rows = $priceReportingService->priceReport($filters, false);

        $produceName = match ($filters['warehouse_code'] ?? null) {
            'VEG-WH' => 'Vegetables',
            'FRT-WH' => 'Fruits',
            default => 'All Produce',
        };

        $viewData = [
            'filters' => $filters,
            'rows' => $rows,
            'produceName' => $produceName,
            'sort' => $filters['sort'] ?? 'code',
            'generatedAt' => now('Asia/Kolkata'),
        ];

        $produceSuffix = match ($filters['warehouse_code'] ?? null) {
            'VEG-WH' => '-vegetables',
            'FRT-WH' => '-fruits',
            default => '',
        };
        $filename = 'price-report-'.$filters['date'].$produceSuffix.'.pdf';

        $pdf = Pdf::loadView('admin.cashbook.finance.purchase.reports.prices_pdf', $viewData)
            ->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    public function companyFinancePurchasePriceProduct(PurchasePriceReportRequest $request, Product $product, PurchasePriceReportingService $priceReportingService): View
    {
        $this->ensureMainAdmin($request);

        $filters = $request->priceFilters();
        $detail = $priceReportingService->productDetail($product, $filters);
        $activePriceGroups = $priceReportingService->activePriceGroups();

        return view('admin.cashbook.finance.purchase.reports.price-product', array_merge($this->purchaseLayoutData(), compact('filters', 'product', 'detail', 'activePriceGroups')));
    }

    public function companyFinancePurchaseChangedItems(PurchasePriceReportRequest $request, PurchasePriceReportingService $priceReportingService): View
    {
        $this->ensureMainAdmin($request);

        $filters = $request->comparisonFilters();
        $rows = $priceReportingService->changedItems($filters);
        $options = $priceReportingService->options();

        return view('admin.cashbook.finance.purchase.reports.changed-items', array_merge($this->purchaseLayoutData(), compact('filters', 'rows', 'options')));
    }

    public function companyFinancePurchaseChangedItemsWhatsApp(PurchasePriceReportRequest $request, PurchasePriceReportingService $priceReportingService): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $filters = $request->comparisonFilters();
        $rows = $priceReportingService->changedItems($filters, false);
        $lines = ['GREEN LEAF PRICE UPDATE', Carbon::parse($filters['date_b'])->format('d M Y'), ''];
        foreach ($rows as $row) {
            $lines[] = $row->product_name;
            $lines[] = '₹'.number_format((float) $row->previous_price, 2).' -> ₹'.number_format((float) $row->current_price, 2);
            $lines[] = '';
        }
        if ($rows->isEmpty()) {
            $lines[] = 'No changed prices.';
        }

        return redirect()->away('https://api.whatsapp.com/send?text='.rawurlencode(trim(implode("\n", $lines))));
    }

    public function companyFinancePurchaserPriceReport(PurchasePriceReportRequest $request, PurchasePriceReportingService $priceReportingService): View
    {
        $this->ensureMainAdmin($request);

        $filters = $request->comparisonFilters();
        $rows = $priceReportingService->purchaserPriceComparison($filters);
        $options = $priceReportingService->options();
        $changedCount = $priceReportingService->changedPurchaserPrices($filters)->count();

        return view('admin.cashbook.finance.purchase.reports.purchaser-prices', array_merge($this->purchaseLayoutData(), compact('filters', 'rows', 'options', 'changedCount')));
    }

    public function companyFinancePurchaserPriceWhatsApp(PurchasePriceReportRequest $request, PurchasePriceReportingService $priceReportingService): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $filters = $request->comparisonFilters();
        $rows = $priceReportingService->changedPurchaserPrices($filters);

        if ($rows->isEmpty()) {
            return redirect()
                ->route('admin.cashbook.finance.purchase.reports.purchaser-prices', $request->query())
                ->with('error', 'No changed purchaser prices to share.');
        }

        $dateA = Carbon::parse($filters['date_a'])->format('d M Y');
        $dateB = Carbon::parse($filters['date_b'])->format('d M Y');
        $totalChanges = count($rows);

        $col1Width = 10;
        $col2Width = 7;
        $col3Width = 7;

        $tableLines = [];
        $tableLines[] = str_pad('PRODUCT', $col1Width).' '.str_pad("Y'DAY", $col2Width, ' ', STR_PAD_LEFT).' → '.str_pad('TODAY', $col3Width, ' ', STR_PAD_LEFT);
        $tableLines[] = str_repeat('-', $col1Width + $col2Width + $col3Width + 4);

        $lastIndex = count($rows) - 1;
        foreach ($rows as $index => $row) {
            $name = (string) $row->product_name;
            $prevVal = (float) $row->previous_price;
            $currVal = (float) $row->current_price;

            $prevStr = number_format($prevVal, 2, '.', '');
            $currStr = number_format($currVal, 2, '.', '');

            $isLargePrice = $prevVal > 999.99 || $currVal > 999.99;
            $isLongName = mb_strlen($name) > $col1Width;

            if ($isLongName || $isLargePrice) {
                $tableLines[] = $name;
                $tableLines[] = str_repeat(' ', $col1Width).' '.str_pad($prevStr, $col2Width, ' ', STR_PAD_LEFT).' → '.str_pad($currStr, $col3Width, ' ', STR_PAD_LEFT);
            } else {
                $tableLines[] = str_pad($name, $col1Width).' '.str_pad($prevStr, $col2Width, ' ', STR_PAD_LEFT).' → '.str_pad($currStr, $col3Width, ' ', STR_PAD_LEFT);
            }

            if ($index < $lastIndex) {
                $tableLines[] = '';
            }
        }

        $message = "*GREEN LEAF*\n*PURCHASER PRICE CHANGES*\n{$dateA} → {$dateB}\nTotal Changes: {$totalChanges}\n\n```\n".implode("\n", $tableLines)."\n```";

        return redirect()->away('https://api.whatsapp.com/send?text='.rawurlencode($message));
    }

    public function companyFinancePurchaseSection(Request $request, PurchaseReportingService $purchaseReportingService, string $section): View
    {
        $this->ensureMainAdmin($request);

        abort_unless(in_array($section, ['purchasers', 'vendors', 'categories', 'invoices'], true), 404);

        $filters = $this->purchaseSectionFilters($request, $section);
        $sectionData = $purchaseReportingService->section($section, $filters);
        $options = $purchaseReportingService->options($filters);

        return view('admin.cashbook.finance.purchase.section', array_merge($this->purchaseLayoutData(), compact(
            'section',
            'filters',
            'sectionData',
            'options',
        )));
    }

    public function companyFinancePurchasePurchaser(Request $request, User $purchaser, PurchaseReportingService $purchaseReportingService, PurchaserFinanceService $purchaserFinanceService): View
    {
        $this->ensureMainAdmin($request);
        $validated = $request->validate([
            'tab' => ['nullable', 'in:overview,purchases,vendors,categories,finance'],
            'finance_search' => ['nullable', 'string', 'max:100'],
            'finance_payment' => ['nullable', 'in:all,cash,credit'],
        ]);
        $tab = $validated['tab'] ?? 'overview';
        $financeSearch = trim((string) ($validated['finance_search'] ?? ''));
        $financePayment = $validated['finance_payment'] ?? 'all';
        $filters = $this->purchaseDetailFilters($request);
        $detail = $purchaseReportingService->purchaserDetail((int) $purchaser->id, $filters, $tab);
        $financeSummary = $purchaserFinanceService->summaryFor((int) $purchaser->id);
        $finance = $tab === 'finance' ? [
            'activity' => $purchaserFinanceService->activityFor((int) $purchaser->id, $filters['start_date'], $filters['end_date']),
            'reconciliation' => $purchaserFinanceService->reconciliationFor((int) $purchaser->id),
            'transactions' => $purchaserFinanceService->transactionsFor((int) $purchaser->id, $filters['start_date'], $filters['end_date']),
            'history' => $purchaserFinanceService->splitsFor((int) $purchaser->id, $filters['start_date'], $filters['end_date'], $financeSearch, $financePayment),
        ] : null;

        return view('admin.cashbook.finance.purchase.detail', array_merge($this->purchaseLayoutData(), compact('filters', 'detail', 'tab', 'financeSummary', 'finance', 'financeSearch', 'financePayment'), [
            'kind' => 'purchaser',
            'record' => $purchaser,
        ]));
    }

    public function companyFinancePurchaseVendor(Request $request, Supplier $supplier, PurchaseReportingService $purchaseReportingService): View
    {
        $this->ensureMainAdmin($request);
        $filters = $this->purchaseDetailFilters($request);
        $detail = $purchaseReportingService->vendorDetail((int) $supplier->id, $filters);

        return view('admin.cashbook.finance.purchase.detail', array_merge($this->purchaseLayoutData(), compact('filters', 'detail'), [
            'kind' => 'vendor',
            'record' => $supplier,
        ]));
    }

    public function companyFinancePurchaseCategory(Request $request, Category $category, PurchaseReportingService $purchaseReportingService): View
    {
        $this->ensureMainAdmin($request);
        $filters = $this->purchaseDetailFilters($request);
        $detail = $purchaseReportingService->categoryDetail((int) $category->id, $filters);

        return view('admin.cashbook.finance.purchase.detail', array_merge($this->purchaseLayoutData(), compact('filters', 'detail'), [
            'kind' => 'category',
            'record' => $category,
        ]));
    }

    public function companyFinancePurchaserDetails(Request $request, User $purchaser): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        abort_unless($purchaser->hasRole('purchaser'), 404);

        $parameters = [
            'purchaser' => $purchaser->public_uuid,
            'period' => 'month',
            'tab' => 'finance',
        ];
        if ($request->filled(['start_date', 'end_date'])) {
            $parameters['period'] = 'custom';
            $parameters['start_date'] = (string) $request->input('start_date');
            $parameters['end_date'] = (string) $request->input('end_date');
        }
        if ($request->filled('search')) {
            $parameters['finance_search'] = trim((string) $request->input('search'));
        }

        return redirect()->route('admin.cashbook.finance.purchase.purchasers.show', $parameters);
    }

    public function storePurchaserFunding(Request $request, User $purchaser, JournalService $journalService): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        abort_unless($purchaser->hasRole('purchaser'), 404);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'business_date' => ['required', 'date_format:Y-m-d'],
            'payment_source' => ['required', 'string', 'in:Bank,Cash'],
            'company_account_id' => ['nullable', 'integer', 'exists:cashbook_company_accounts,id'],
            'statement_entry_id' => ['nullable', 'integer', 'exists:cashbook_company_account_statement_entries,id'],
            'reference' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $credit = DB::transaction(function () use ($validated, $purchaser, $request, $journalService): PurchaserCredit {
            $credit = PurchaserCredit::query()->create([
                'purchaser_id' => $purchaser->id,
                'type' => 'in',
                'amount' => round((float) $validated['amount'], 2),
                'description' => $validated['description'] ?? 'Company funding to purchaser',
                'payment_source' => $validated['payment_source'],
                'company_account_id' => $validated['company_account_id'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'created_by' => $request->user()->id,
                'business_date' => $validated['business_date'],
            ]);

            $journalEntry = $journalService->recordPurchaserCredit($credit);

            if (! empty($validated['statement_entry_id'])) {
                $statementEntry = CompanyAccountStatementEntry::query()
                    ->whereKey((int) $validated['statement_entry_id'])
                    ->where('direction', 'out')
                    ->where('is_finalized', false)
                    ->where('status', 'unmatched')
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->companyPaymentReconciliationService->reconcileStatementJournal(
                    $statementEntry,
                    $journalEntry,
                    round((float) $validated['amount'], 2),
                    (int) $request->user()->id,
                );

                $credit->update([
                    'company_account_id' => $statementEntry->company_account_id,
                    'payment_source' => $statementEntry->companyAccount?->account_type === 'cash' ? 'Cash' : 'Bank',
                ]);
            }

            return $credit;
        });

        return redirect()
            ->route('admin.cashbook.finance.purchase.purchasers.show', [
                'purchaser' => $purchaser->public_uuid,
                'period' => 'month',
                'tab' => 'finance',
            ])
            ->with('success', 'Purchaser funding of ₹'.number_format((float) $credit->amount, 2).' recorded.');
    }

    public function updatePurchaserFunding(Request $request, User $purchaser, PurchaserCredit $credit): RedirectResponse
    {
        $this->ensureMainAdmin($request);
        abort_unless($request->user()->isMainAdmin() || $request->user()->hasRole('admin'), 403);

        abort_unless($purchaser->hasRole('purchaser') && (int) $credit->purchaser_id === (int) $purchaser->id && $credit->type === 'in', 404);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'business_date' => ['required', 'date_format:Y-m-d'],
            'payment_source' => ['required', 'string', 'in:Bank,Cash'],
            'company_account_id' => ['nullable', 'integer', 'exists:cashbook_company_accounts,id'],
            'reference' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $account = CompanyAccount::query()->whereKey($validated['company_account_id'] ?? 0)->where('enabled', true)->first();
        if (! $account || $account->account_type !== strtolower($validated['payment_source'])) {
            throw ValidationException::withMessages(['company_account_id' => 'Select an enabled company account matching the payment source.']);
        }

        DB::transaction(function () use ($credit, $validated, $purchaser, $request): void {
            /** @var PurchaserCredit $credit */
            $credit = PurchaserCredit::query()->whereKey($credit->id)->lockForUpdate()->firstOrFail();
            $newAmount = round((float) $validated['amount'], 2);
            $oldAmount = round((float) $credit->amount, 2);
            app(PurchaserFinanceService::class)->assertFundingMutable($credit);

            $credit->update([
                'amount' => $newAmount,
                'business_date' => $validated['business_date'],
                'payment_source' => $validated['payment_source'],
                'company_account_id' => $validated['company_account_id'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'description' => $validated['description'] ?? 'Company funding to purchaser',
            ]);

            app(JournalService::class)->recordPurchaserCredit($credit, updateExisting: true);

            Log::info('Purchaser funding updated', [
                'purchaser_id' => $purchaser->id,
                'credit_id' => $credit->id,
                'actor' => $request->user()->id,
                'timestamp' => now()->toIso8601String(),
            ]);

            if (function_exists('activity')) {
                activity('purchaser_finance')
                    ->causedBy($request->user())
                    ->performedOn($purchaser)
                    ->withProperties([
                        'credit_id' => $credit->id,
                        'old_amount' => $oldAmount,
                        'new_amount' => $newAmount,
                        'action' => 'update_funding',
                    ])
                    ->log("Purchaser funding #{$credit->id} updated for {$purchaser->name}");
            }
        });

        return redirect()
            ->route('admin.cashbook.finance.purchase.purchasers.show', [
                'purchaser' => $purchaser->public_uuid,
                'period' => 'month',
                'tab' => 'finance',
            ])
            ->with('success', 'Purchaser funding of ₹'.number_format((float) $validated['amount'], 2).' updated successfully.');
    }

    public function deletePurchaserFunding(
        Request $request,
        User $purchaser,
        PurchaserCredit $credit,
        PurchaserFinanceService $purchaserFinanceService
    ): RedirectResponse {
        $this->ensureMainAdmin($request);
        abort_unless($request->user()->isMainAdmin() || $request->user()->hasRole('admin'), 403);

        abort_unless($purchaser->hasRole('purchaser') && (int) $credit->purchaser_id === (int) $purchaser->id && $credit->type === 'in', 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:duplicate_entry,wrong_entry,other,Duplicate Entry,Wrong Entry,Other'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($credit, $purchaser, $validated, $request, $purchaserFinanceService): void {
            $credit = PurchaserCredit::query()->whereKey($credit->id)->lockForUpdate()->firstOrFail();

            $purchaserFinanceService->assertFundingMutable($credit);
            $creditAmount = (float) $credit->amount;

            $journalEntry = JournalEntry::query()
                ->where('source_type', PurchaserCredit::class)
                ->where('source_id', $credit->id)
                ->lockForUpdate()
                ->first();

            if ($journalEntry instanceof JournalEntry) {
                $journalEntry->transactions()->delete();
                $journalEntry->delete();
            }

            $credit->delete();

            Log::info('Purchaser funding deleted', [
                'purchaser_id' => $purchaser->id,
                'credit_id' => $credit->id,
                'actor' => $request->user()->id,
                'reason' => $validated['reason'],
                'timestamp' => now()->toIso8601String(),
            ]);

            if (function_exists('activity')) {
                activity('purchaser_finance')
                    ->causedBy($request->user())
                    ->performedOn($purchaser)
                    ->withProperties([
                        'credit_id' => $credit->id,
                        'amount' => $creditAmount,
                        'reason' => $validated['reason'],
                        'notes' => $validated['notes'] ?? null,
                        'action' => 'delete_funding',
                    ])
                    ->log('Purchaser funding of ₹'.number_format($creditAmount, 2)." deleted for {$purchaser->name}");
            }
        }, attempts: 3);

        return redirect()
            ->route('admin.cashbook.finance.purchase.purchasers.show', [
                'purchaser' => $purchaser->public_uuid,
                'period' => 'month',
                'tab' => 'finance',
            ])
            ->with('success', 'Purchaser funding of ₹'.number_format((float) $credit->amount, 2).' deleted successfully.');
    }

    public function purchaserFundingCandidates(Request $request, User $purchaser, PurchaserCredit $credit, PurchaserFinanceService $purchaserFinanceService): JsonResponse
    {
        $this->ensureMainAdmin($request);

        abort_unless($purchaser->hasRole('purchaser') && (int) $credit->purchaser_id === (int) $purchaser->id && $credit->type === 'in', 404);

        $data = $purchaserFinanceService->candidateStatementsForCredit($credit);

        return response()->json($data);
    }

    public function replaceMatchPurchaserFunding(Request $request, User $purchaser, PurchaserCredit $credit): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        abort_unless($purchaser->hasRole('purchaser') && (int) $credit->purchaser_id === (int) $purchaser->id && $credit->type === 'in', 404);

        $validated = $request->validate([
            'statement_entry_id' => ['required', 'integer', 'exists:cashbook_company_account_statement_entries,id'],
        ]);

        DB::transaction(function () use ($credit, $validated, $request): void {
            $credit = PurchaserCredit::query()->whereKey($credit->id)->lockForUpdate()->firstOrFail();

            $journalEntry = JournalEntry::query()
                ->where('source_type', PurchaserCredit::class)
                ->where('source_id', $credit->id)
                ->with('transactions.account')
                ->lockForUpdate()
                ->firstOrFail();

            $statementEntry = CompanyAccountStatementEntry::query()
                ->whereKey((int) $validated['statement_entry_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->companyPaymentReconciliationService->replaceStatementJournalMatch(
                $statementEntry,
                $journalEntry,
                (float) $credit->amount,
                (int) $request->user()->id
            );
        });

        return redirect()->back()->with('success', 'Purchaser funding statement match replaced successfully.');
    }

    public function matchStatementPurchaserFunding(Request $request, User $purchaser, PurchaserCredit $credit): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        abort_unless($purchaser->hasRole('purchaser') && (int) $credit->purchaser_id === (int) $purchaser->id && $credit->type === 'in', 404);

        $validated = $request->validate([
            'statement_entry_id' => ['required', 'integer', 'exists:cashbook_company_account_statement_entries,id'],
        ]);

        DB::transaction(function () use ($credit, $validated, $request): void {
            $credit = PurchaserCredit::query()->whereKey($credit->id)->lockForUpdate()->firstOrFail();

            $journalEntry = JournalEntry::query()
                ->where('source_type', PurchaserCredit::class)
                ->where('source_id', $credit->id)
                ->with('transactions.account')
                ->lockForUpdate()
                ->firstOrFail();

            $alreadyReconciled = CompanyAccountStatementEntry::query()
                ->where('source_type', PurchaserCredit::class)
                ->where('source_id', $credit->id)
                ->where('is_finalized', true)
                ->exists();

            if ($alreadyReconciled) {
                throw ValidationException::withMessages([
                    'statement_entry_id' => 'This purchaser funding transaction is already reconciled.',
                ]);
            }

            $statementEntry = CompanyAccountStatementEntry::query()
                ->with('companyAccount')
                ->whereKey((int) $validated['statement_entry_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($statementEntry->direction !== 'out' || $statementEntry->is_finalized || $statementEntry->status !== 'unmatched' || $statementEntry->journal_entry_id !== null) {
                throw ValidationException::withMessages([
                    'statement_entry_id' => 'The selected statement entry cannot be matched.',
                ]);
            }

            $amount = round((float) $credit->amount, 2);
            $stmtAmount = round((float) $statementEntry->amount, 2);

            if ($stmtAmount < $amount - 0.01) {
                throw ValidationException::withMessages([
                    'statement_entry_id' => 'The selected statement entry amount is less than the funding amount.',
                ]);
            }

            $this->companyPaymentReconciliationService->reconcileStatementJournal(
                $statementEntry,
                $journalEntry,
                $amount,
                (int) $request->user()->id,
            );

            $credit->update([
                'company_account_id' => $statementEntry->company_account_id,
                'payment_source' => $statementEntry->companyAccount?->account_type === 'cash' ? 'Cash' : 'Bank',
            ]);
        }, attempts: 3);

        return redirect()->back()->with('success', 'Purchaser funding matched to statement entry.');
    }

    public function matchManualPurchaserFunding(Request $request, User $purchaser, PurchaserCredit $credit): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        abort_unless($purchaser->hasRole('purchaser') && (int) $credit->purchaser_id === (int) $purchaser->id && $credit->type === 'in', 404);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'business_date' => ['required', 'date_format:Y-m-d'],
            'company_account_id' => ['required', 'integer', 'exists:cashbook_company_accounts,id'],
            'reference' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($credit, $validated, $request, $purchaser): void {
            $credit = PurchaserCredit::query()->whereKey($credit->id)->lockForUpdate()->firstOrFail();

            $journalEntry = JournalEntry::query()
                ->where('source_type', PurchaserCredit::class)
                ->where('source_id', $credit->id)
                ->with('transactions.account')
                ->lockForUpdate()
                ->firstOrFail();

            $alreadyReconciled = CompanyAccountStatementEntry::query()
                ->where('source_type', PurchaserCredit::class)
                ->where('source_id', $credit->id)
                ->where('is_finalized', true)
                ->exists();

            if ($alreadyReconciled) {
                throw ValidationException::withMessages([
                    'company_account_id' => 'This purchaser funding transaction is already reconciled.',
                ]);
            }

            $companyAccount = CompanyAccount::query()
                ->whereKey((int) $validated['company_account_id'])
                ->where('enabled', true)
                ->lockForUpdate()
                ->firstOrFail();

            $statementEntry = $this->companyPaymentReconciliationService->createStatementEntry([
                'company_account_id' => $companyAccount->id,
                'transaction_date' => $validated['business_date'],
                'direction' => 'out',
                'amount' => round((float) $validated['amount'], 2),
                'reference' => $validated['reference'] ?? "CASH-{$credit->id}",
                'narration' => $validated['description'] ?? 'Cash given to purchaser '.$purchaser->name,
                'source' => 'manual',
                'source_type' => PurchaserCredit::class,
                'source_id' => $credit->id,
                'notes' => $validated['notes'] ?? null,
            ], (int) $request->user()->id);

            $this->companyPaymentReconciliationService->reconcileStatementJournal(
                $statementEntry,
                $journalEntry,
                round((float) $validated['amount'], 2),
                (int) $request->user()->id,
            );

            $credit->update([
                'company_account_id' => $companyAccount->id,
                'payment_source' => $companyAccount->account_type === 'cash' ? 'Cash' : 'Bank',
            ]);
        }, attempts: 3);

        return redirect()->back()->with('success', 'Manual cash/statement entry created and matched.');
    }

    public function tracePurchaserFunding(Request $request, User $purchaser, PurchaserCredit $credit): JsonResponse
    {
        $this->ensureMainAdmin($request);

        abort_unless($purchaser->hasRole('purchaser') && (int) $credit->purchaser_id === (int) $purchaser->id && $credit->type === 'in', 404);

        $statement = CompanyAccountStatementEntry::query()
            ->with(['companyAccount', 'reconciledBy'])
            ->where('source_type', PurchaserCredit::class)
            ->where('source_id', $credit->id)
            ->where('is_finalized', true)
            ->first();

        if (! $statement) {
            return response()->json([
                'reconciled' => false,
                'purchaser' => [
                    'name' => $purchaser->name,
                    'public_uuid' => $purchaser->public_uuid,
                ],
                'funding' => [
                    'id' => $credit->id,
                    'reference' => $credit->reference ?: "PURCH-FUND-{$credit->id}",
                    'business_date' => $credit->business_date?->toDateString(),
                    'amount' => (float) $credit->amount,
                ],
            ]);
        }

        $isImported = $statement->source === 'imported' || ! empty($statement->import_file_name) || ! empty($statement->import_fingerprint);
        $accountType = $statement->companyAccount?->account_type ?? 'bank';
        $sourceClassification = match (true) {
            $isImported => 'Imported Statement',
            $accountType === 'cash' => 'Manual Cash',
            default => 'Manual Statement',
        };

        return response()->json([
            'reconciled' => true,
            'purchaser' => [
                'name' => $purchaser->name,
                'public_uuid' => $purchaser->public_uuid,
            ],
            'funding' => [
                'id' => $credit->id,
                'reference' => $credit->reference ?: "PURCH-FUND-{$credit->id}",
                'business_date' => $credit->business_date?->toDateString(),
                'amount' => (float) $credit->amount,
            ],
            'matched_account' => [
                'name' => $statement->companyAccount?->name ?? 'Company Account',
                'account_type' => $accountType,
            ],
            'statement' => [
                'id' => $statement->id,
                'public_uuid' => $statement->public_uuid,
                'transaction_date' => $statement->transaction_date?->toDateString(),
                'amount' => (float) $statement->amount,
                'reference' => $statement->reference ?: '—',
                'narration' => $statement->narration ?: '—',
                'notes' => $statement->notes ?: '—',
                'source_classification' => $sourceClassification,
                'is_imported' => $isImported,
                'import_file_name' => $statement->import_file_name,
            ],
            'audit' => [
                'matched_by' => $statement->reconciledBy?->name ?? 'System',
                'matched_at' => $statement->reconciled_at?->toDateTimeString() ?? $statement->finalized_at?->toDateTimeString() ?? '—',
            ],
            'can_unmatch' => $isImported,
        ]);
    }

    public function unmatchPurchaserFunding(Request $request, User $purchaser, PurchaserCredit $credit): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        abort_unless($purchaser->hasRole('purchaser') && (int) $credit->purchaser_id === (int) $purchaser->id && $credit->type === 'in', 404);

        DB::transaction(function () use ($credit, $request): void {
            $credit = PurchaserCredit::query()->whereKey($credit->id)->lockForUpdate()->firstOrFail();

            $statementEntry = CompanyAccountStatementEntry::query()
                ->where('source_type', PurchaserCredit::class)
                ->where('source_id', $credit->id)
                ->where('is_finalized', true)
                ->lockForUpdate()
                ->first();

            if (! $statementEntry) {
                throw ValidationException::withMessages([
                    'credit' => 'No active reconciliation match found for this funding transaction.',
                ]);
            }

            $this->companyPaymentReconciliationService->unmatchStatementJournal(
                $statementEntry,
                (int) $request->user()->id,
            );
        }, attempts: 3);

        return redirect()->back()->with('success', 'Reconciliation unlinked successfully.');
    }

    public function companyFinanceVendorCredit(Request $request): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        return redirect()->route('admin.cashbook.finance.purchase.reports.credit-purchases', $request->query());
    }

    public function companyFinancePurchaseCreditReport(Request $request): View
    {
        $this->ensureMainAdmin($request);

        $filters = $this->purchaseReportFilters($request, 'month');
        $validated = $request->validate([
            'status' => ['nullable', 'in:all,unpaid,partially_paid,paid'],
            'date' => ['nullable', 'date'],
        ]);
        $search = $filters['search'];
        $status = $validated['status'] ?? 'all';
        $legacyAsOfDate = $validated['date'] ?? null;

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $companyAccounts = CompanyAccount::where('enabled', true)->orderBy('name')->get();
        $company = config('greenleaf');
        $currentShop = $shops->first();

        $baseQuery = PurchaseInvoice::query()
            ->join('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
            ->leftJoin('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
            ->leftJoinSub(
                DB::table('vendor_settlement_allocations')
                    ->selectRaw('purchase_invoice_id, SUM(total_settled) as total_settled')
                    ->groupBy('purchase_invoice_id'),
                'vendor_settlement_totals',
                'vendor_settlement_totals.purchase_invoice_id',
                '=',
                'purchase_invoices.id'
            )
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->where(function (Builder $creditQuery): void {
                $creditQuery
                    ->whereRaw("LOWER(COALESCE(purchase_invoices.payment_method, purchaser_carts.payment_method, '')) = 'credit'")
                    ->orWhere('purchase_invoices.payment_paid_by', 'vendor_credit')
                    ->orWhere('purchase_invoices.payment_status', 'credit_pending_approval');
            });

        if ($legacyAsOfDate !== null) {
            $baseQuery->whereRaw(
                'COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) <= ?',
                [$legacyAsOfDate]
            );
        } else {
            $baseQuery->whereRaw(
                'COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) BETWEEN ? AND ?',
                [$filters['start_date'], $filters['end_date']]
            );
        }

        if (! empty($filters['purchase_product_filter_id'])) {
            $filterId = (int) $filters['purchase_product_filter_id'];
            $baseQuery->whereExists(function (QueryBuilder $productQuery) use ($filterId): void {
                $productQuery
                    ->selectRaw('1')
                    ->from('purchaser_cart_items')
                    ->join('purchase_product_filter_items', 'purchase_product_filter_items.product_id', '=', 'purchaser_cart_items.product_id')
                    ->whereColumn('purchaser_cart_items.purchaser_cart_id', 'purchase_invoices.purchaser_cart_id')
                    ->where('purchase_product_filter_items.filter_id', $filterId);
            });
        }

        if ($filters['purchaser_id'] !== null) {
            $baseQuery->where('purchaser_carts.user_id', $filters['purchaser_id']);
        }

        if ($filters['vendor_id'] !== null) {
            $baseQuery->where('purchase_invoices.supplier_id', $filters['vendor_id']);
        }

        if (! empty($filters['category_ids'])) {
            $baseQuery->whereExists(function (QueryBuilder $categoryQuery) use ($filters): void {
                $categoryQuery
                    ->selectRaw('1')
                    ->from('purchaser_cart_items')
                    ->join('products', 'products.id', '=', 'purchaser_cart_items.product_id')
                    ->whereColumn('purchaser_cart_items.purchaser_cart_id', 'purchase_invoices.purchaser_cart_id')
                    ->whereIn('products.category_id', $filters['category_ids']);
            });
        }

        if ($filters['grade'] !== null) {
            $baseQuery->whereExists(function (QueryBuilder $gradeQuery) use ($filters): void {
                $gradeQuery
                    ->selectRaw('1')
                    ->from('purchaser_cart_items')
                    ->whereColumn('purchaser_cart_items.purchaser_cart_id', 'purchase_invoices.purchaser_cart_id')
                    ->where('purchaser_cart_items.grade', $filters['grade']);
            });
        }

        if ($search !== '') {
            $baseQuery->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('suppliers.name', 'like', '%'.$search.'%')
                    ->orWhere('suppliers.mobile_number', 'like', '%'.$search.'%')
                    ->orWhere('suppliers.contact', 'like', '%'.$search.'%');
            });
        }

        $vendorRows = (clone $baseQuery)
            ->selectRaw('
                purchase_invoices.supplier_id,
                suppliers.public_uuid as supplier_public_uuid,
                suppliers.name as supplier_name,
                COALESCE(suppliers.mobile_number, suppliers.contact, "—") as supplier_contact,
                COUNT(*) as invoice_count,
                ROUND(SUM(purchase_invoices.amount - purchase_invoices.discount_amount), 2) as total_net,
                ROUND(SUM(COALESCE(vendor_settlement_totals.total_settled, purchase_invoices.paid_amount)), 2) as total_paid,
                ROUND(SUM(CASE WHEN ((purchase_invoices.amount - purchase_invoices.discount_amount) - COALESCE(vendor_settlement_totals.total_settled, purchase_invoices.paid_amount)) > 0 THEN ((purchase_invoices.amount - purchase_invoices.discount_amount) - COALESCE(vendor_settlement_totals.total_settled, purchase_invoices.paid_amount)) ELSE 0 END), 2) as total_outstanding,
                MIN(COALESCE(purchaser_carts.business_date, purchase_invoices.created_at)) as oldest_date,
                MAX(COALESCE(purchaser_carts.business_date, purchase_invoices.created_at)) as last_date
            ')
            ->groupBy('purchase_invoices.supplier_id', 'suppliers.public_uuid', 'suppliers.name', 'suppliers.mobile_number', 'suppliers.contact');

        if ($status === 'unpaid') {
            $vendorRows->havingRaw('total_paid <= 0.01 AND total_outstanding > 0.01');
        } elseif ($status === 'partially_paid') {
            $vendorRows->havingRaw('total_paid > 0.01 AND total_outstanding > 0.01');
        } elseif ($status === 'paid') {
            $vendorRows->havingRaw('total_outstanding <= 0.01');
        }

        $summaryRows = DB::query()->fromSub($vendorRows, 'vendor_credit_summary');
        $summary = (clone $summaryRows)
            ->selectRaw('
                COUNT(*) as vendor_count,
                COALESCE(SUM(invoice_count), 0) as invoice_count,
                COALESCE(SUM(total_net), 0) as total_invoiced,
                COALESCE(SUM(total_paid), 0) as total_paid,
                COALESCE(SUM(total_outstanding), 0) as total_outstanding
            ')
            ->first();

        $kpi = [
            'total_invoiced' => round((float) ($summary->total_invoiced ?? 0), 2),
            'total_paid' => round((float) ($summary->total_paid ?? 0), 2),
            'total_outstanding' => round((float) ($summary->total_outstanding ?? 0), 2),
            'vendor_count' => (int) ($summary->vendor_count ?? 0),
            'invoice_count' => (int) ($summary->invoice_count ?? 0),
        ];

        $vendors = (clone $summaryRows)
            ->orderByDesc('total_outstanding')
            ->orderBy('supplier_name')
            ->paginate(20)
            ->through(function (object $row): array {
                $oldestDate = $row->oldest_date ? Carbon::parse((string) $row->oldest_date) : null;
                $lastDate = $row->last_date ? Carbon::parse((string) $row->last_date) : null;
                $totalPaid = round((float) $row->total_paid, 2);
                $totalOutstanding = round((float) $row->total_outstanding, 2);

                return [
                    'supplier_id' => (int) $row->supplier_id,
                    'supplier_public_uuid' => (string) $row->supplier_public_uuid,
                    'supplier_name' => (string) $row->supplier_name,
                    'supplier_contact' => (string) $row->supplier_contact,
                    'invoice_count' => (int) $row->invoice_count,
                    'total_net' => round((float) $row->total_net, 2),
                    'total_paid' => $totalPaid,
                    'total_outstanding' => $totalOutstanding,
                    'oldest_date' => $oldestDate,
                    'last_date' => $lastDate,
                    'status_category' => $totalOutstanding <= 0.01 ? 'paid' : ($totalPaid > 0.01 ? 'partially_paid' : 'unpaid'),
                ];
            })
            ->withQueryString();

        return view('admin.cashbook.finance.vendor-credit.index', array_merge(
            $this->purchaseLayoutData(),
            compact('vendors', 'kpi', 'search', 'status', 'filters')
        ));
    }

    public function companyFinanceVendorCreditShow(Request $request, Supplier $supplier): View
    {
        $this->ensureMainAdmin($request);

        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', 'all');
        $startDate = (string) $request->input('start_date', '');
        $endDate = (string) $request->input('end_date', '');

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $companyAccounts = CompanyAccount::where('enabled', true)->orderBy('name')->get();
        $company = config('greenleaf');
        $currentShop = $shops->first();

        $query = PurchaseInvoice::query()
            ->with(['purchaserCart:id,business_date,bill_number,cart_number', 'supplier:id,name,mobile_number,contact'])
            ->withSum('vendorSettlementAllocations', 'total_settled')
            ->where('supplier_id', $supplier->id)
            ->where(function (Builder $creditQuery): void {
                $creditQuery
                    ->where('payment_method', 'Credit')
                    ->orWhere('payment_status', 'credit_pending_approval')
                    ->orWhereHas('purchaserCart', fn (Builder $cq) => $cq->where('payment_method', 'Credit'))
                    ->orWhereRaw('(amount - discount_amount) > paid_amount');
            });

        if ($startDate !== '') {
            $query->where(function (Builder $q) use ($startDate): void {
                $q->whereHas('purchaserCart', fn ($cq) => $cq->whereDate('business_date', '>=', $startDate))
                    ->orWhere(fn ($mq) => $mq->whereNull('purchaser_cart_id')->whereDate('created_at', '>=', $startDate));
            });
        }
        if ($endDate !== '') {
            $query->where(function (Builder $q) use ($endDate): void {
                $q->whereHas('purchaserCart', fn ($cq) => $cq->whereDate('business_date', '<=', $endDate))
                    ->orWhere(fn ($mq) => $mq->whereNull('purchaser_cart_id')->whereDate('created_at', '<=', $endDate));
            });
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('payment_note', 'like', "%{$search}%")
                    ->orWhere('payment_details', 'like', "%{$search}%")
                    ->orWhereHas('purchaserCart', fn ($cq) => $cq->where('bill_number', 'like', "%{$search}%")->orWhere('cart_number', 'like', "%{$search}%"));
            });
        }

        if ($status === 'unpaid') {
            $query->where('paid_amount', '<=', 0);
        } elseif ($status === 'partially_paid') {
            $query->where('paid_amount', '>', 0)->whereRaw('(amount - discount_amount) > paid_amount');
        } elseif ($status === 'paid') {
            $query->whereRaw('(amount - discount_amount) <= paid_amount');
        }

        $allInvoices = (clone $query)->get();
        $totalGross = (float) $allInvoices->sum('amount');
        $totalDiscount = (float) $allInvoices->sum('discount_amount');
        $totalNet = max(0, $totalGross - $totalDiscount);
        $totalPaid = (float) $allInvoices->sum(fn (PurchaseInvoice $invoice): float => (float) ($invoice->vendor_settlement_allocations_sum_total_settled ?? $invoice->paid_amount));
        $totalOutstanding = max(0, $totalNet - $totalPaid);

        $kpi = [
            'total_invoiced' => round($totalNet, 2),
            'total_paid' => round($totalPaid, 2),
            'total_outstanding' => round($totalOutstanding, 2),
            'invoice_count' => $allInvoices->count(),
        ];

        $invoices = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        $availableVendorAdvance = round((float) VendorAdvance::query()
            ->where('supplier_id', $supplier->id)
            ->where('amount_remaining', '>', 0)
            ->sum('amount_remaining'), 2);
        $settlementHistory = VendorSettlement::query()
            ->with('journalEntry')
            ->where('supplier_id', $supplier->id)
            ->latest('payment_date')
            ->latest('id')
            ->get();
        $settlementCandidates = PurchaseInvoice::query()
            ->withSum('vendorSettlementAllocations', 'total_settled')
            ->where('supplier_id', $supplier->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->get()
            ->map(fn (PurchaseInvoice $invoice): array => [
                'id' => $invoice->id,
                'number' => $invoice->invoice_number ?: '#'.$invoice->id,
                'date' => $invoice->created_at?->toDateString(),
                'outstanding' => round(max(0, (float) $invoice->amount - (float) $invoice->discount_amount - (float) $invoice->vendor_settlement_allocations_sum_total_settled), 2),
            ])
            ->filter(fn (array $invoice): bool => $invoice['outstanding'] > 0.01)
            ->values();
        $statementTransactions = CompanyAccountStatementEntry::query()
            ->with('companyAccount:id,name,account_type')
            ->where('direction', 'out')
            ->where('is_finalized', false)
            ->whereNull('journal_entry_id')
            ->whereIn('status', ['unmatched', 'partially_matched'])
            ->latest('transaction_date')
            ->latest('id')
            ->limit(100)
            ->get();

        // Check if there are journal entries for these invoices
        $invoiceIds = $invoices->pluck('id')->all();
        $journalEntries = JournalEntry::query()
            ->where('source_type', PurchaseInvoice::class)
            ->whereIn('source_id', $invoiceIds)
            ->get()
            ->keyBy('source_id');

        return view('admin.cashbook.finance.vendor-credit.show', compact(
            'shops',
            'companyAccounts',
            'company',
            'currentShop',
            'supplier',
            'invoices',
            'journalEntries',
            'kpi',
            'search',
            'status',
            'startDate',
            'endDate',
            'availableVendorAdvance',
            'settlementHistory',
            'settlementCandidates',
            'statementTransactions',
        ));
    }

    public function companyFinanceVendorSettlementHistory(Request $request): View
    {
        $this->ensureMainAdmin($request);

        $month = trim((string) $request->input('month', ''));
        if ($month === '' || ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->timezone('Asia/Kolkata')->format('Y-m');
        }
        $supplierId = $request->integer('supplier_id');
        $accountId = $request->integer('company_account_id');
        $status = (string) $request->input('status', 'all');
        $search = trim((string) $request->input('search', ''));
        $shops = $this->shopSyncService->syncAndGetProfiles();
        $company = config('greenleaf');
        $currentShop = $shops->first();

        $monthDate = Carbon::createFromFormat('Y-m', $month, 'Asia/Kolkata');
        $startOfMonth = $monthDate->copy()->startOfMonth()->toDateString();
        $endOfMonth = $monthDate->copy()->endOfMonth()->toDateString();

        $query = VendorSettlement::query()
            ->with(['supplier:id,name', 'companyAccount:id,name,account_type', 'journalEntry.statementEntries.companyAccount', 'allocations.purchaseInvoice'])
            ->whereBetween('payment_date', [$startOfMonth, $endOfMonth])
            ->when($supplierId > 0, fn (Builder $q) => $q->where('supplier_id', $supplierId))
            ->when($accountId > 0, fn (Builder $q) => $q->where('company_account_id', $accountId))
            ->when($status !== 'all', fn (Builder $q) => $status === 'finalized' ? $q->where('is_finalized', true) : $q->where('is_finalized', false))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $sq) => $sq->where('reference', 'like', '%'.$search.'%')->orWhereHas('supplier', fn (Builder $supplierQuery) => $supplierQuery->where('name', 'like', '%'.$search.'%'))));
        $summary = (clone $query)->selectRaw('COUNT(*) as settlement_count, COALESCE(SUM(actual_payment_amount), 0) as cash_paid, COALESCE(SUM(settlement_discount_amount), 0) as discount_given, COALESCE(SUM(new_vendor_advance_amount), 0) as advance_created, COALESCE(SUM(vendor_advance_used_amount), 0) as advance_used')->first();
        $totalSettled = (float) DB::table('vendor_settlement_allocations')->whereIn('vendor_settlement_id', (clone $query)->select('id'))->sum('total_settled');

        return view('admin.cashbook.finance.vendor-credit.settlements', [
            'shops' => $shops,
            'company' => $company,
            'currentShop' => $currentShop,
            'settlements' => $query->latest('payment_date')->latest('id')->paginate(30)->withQueryString(),
            'summary' => ['cash_paid' => (float) $summary->cash_paid, 'discount_given' => (float) $summary->discount_given, 'advance_created' => (float) $summary->advance_created, 'advance_used' => (float) $summary->advance_used, 'settlement_count' => (int) $summary->settlement_count, 'total_settled' => $totalSettled],
            'month' => $month, 'supplierId' => $supplierId, 'accountId' => $accountId, 'status' => $status, 'search' => $search,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']), 'companyAccounts' => CompanyAccount::query()->where('enabled', true)->orderBy('name')->get(),
        ]);
    }

    public function companyFinanceVendorSettlementDetails(Request $request, VendorSettlement $vendorSettlement): View
    {
        $this->ensureMainAdmin($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $company = config('greenleaf');
        $currentShop = $shops->first();
        $companyAccounts = CompanyAccount::query()->where('enabled', true)->orderBy('name')->get();

        $vendorSettlement->load([
            'supplier',
            'companyAccount',
            'createdBy',
            'journalEntry.statementEntries.companyAccount',
            'journalEntry.statementEntries.reconciledBy',
            'allocations.purchaseInvoice.vendorSettlementAllocations',
        ]);

        $statementEntry = $vendorSettlement->journalEntry?->statementEntries->first();
        $allocationRows = $vendorSettlement->allocations->map(function ($allocation): array {
            $invoice = $allocation->purchaseInvoice;
            $netAmount = round((float) $invoice->amount - (float) $invoice->discount_amount, 2);
            $totalSettled = round((float) $invoice->vendorSettlementAllocations->sum('total_settled'), 2);
            $remainingOutstanding = round(max(0, $netAmount - $totalSettled), 2);

            return [
                'invoice' => $invoice,
                'original_outstanding' => round($remainingOutstanding + (float) $allocation->total_settled, 2),
                'remaining_outstanding' => $remainingOutstanding,
                'allocation' => $allocation,
            ];
        });
        $selectedBillTotal = round((float) $allocationRows->sum('original_outstanding'), 2);

        $statementTransactions = $vendorSettlement->is_finalized ? collect() : CompanyAccountStatementEntry::query()
            ->with('companyAccount:id,name,account_type')
            ->where('direction', 'out')
            ->where('is_finalized', false)
            ->whereNull('journal_entry_id')
            ->whereIn('status', ['unmatched', 'partially_matched'])
            ->where('amount', round((float) $vendorSettlement->actual_payment_amount, 2))
            ->latest('transaction_date')
            ->latest('id')
            ->limit(100)
            ->get();

        return view('admin.cashbook.finance.vendor-credit.settlement-details', compact(
            'shops',
            'company',
            'currentShop',
            'companyAccounts',
            'vendorSettlement',
            'statementEntry',
            'statementTransactions',
            'allocationRows',
            'selectedBillTotal',
        ));
    }

    public function reconcileVendorSettlement(Request $request, VendorSettlement $vendorSettlement): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'company_account_id' => ['required', 'integer', 'exists:cashbook_company_accounts,id'],
            'statement_entry_id' => ['nullable', 'integer', 'exists:cashbook_company_account_statement_entries,id'],
        ]);

        DB::transaction(function () use ($request, $validated, $vendorSettlement): void {
            $settlement = VendorSettlement::query()->whereKey($vendorSettlement->id)->lockForUpdate()->firstOrFail();
            if ($settlement->is_finalized || (float) $settlement->actual_payment_amount <= 0.0) {
                abort(422, 'Only pending cash settlements can be reconciled.');
            }

            $journalEntry = JournalEntry::query()->whereKey($settlement->journal_entry_id)->lockForUpdate()->firstOrFail();
            $companyAccount = CompanyAccount::query()->whereKey($validated['company_account_id'])->where('enabled', true)->lockForUpdate()->firstOrFail();
            $settlement->update(['company_account_id' => $companyAccount->id]);

            $this->companyPaymentReconciliationService->finalizeVendorSettlementMovement($settlement, $journalEntry, [
                'company_account_id' => $companyAccount->id,
                'statement_entry_id' => $validated['statement_entry_id'] ?? null,
                'transaction_date' => $settlement->payment_date->toDateString(),
                'reference' => $settlement->reference ?: 'VENDOR-SETTLEMENT-'.$settlement->id,
                'narration' => 'Vendor settlement for '.$settlement->supplier()->value('name'),
                'notes' => $settlement->note,
            ], (int) $request->user()->id);
        });

        return redirect()
            ->route('admin.cashbook.finance.vendor-credit.settlements.show', $vendorSettlement)
            ->with('success', 'Vendor settlement finalized.');
    }

    public function settleVendorCredit(Request $request, Supplier $supplier, VendorSettlementService $vendorSettlementService): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'actual_payment_amount' => ['required', 'numeric', 'min:0'],
            'settlement_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'vendor_advance_used_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'in:Cash,Bank,Online,GPay'],
            'company_account_id' => ['nullable', 'integer', 'exists:cashbook_company_accounts,id'],
            'statement_entry_id' => ['nullable', 'integer', 'exists:cashbook_company_account_statement_entries,id'],
            'reference' => ['nullable', 'string', 'max:160'],
            'note' => ['nullable', 'string', 'max:1000'],
            'invoice_ids' => ['required_without:allocations', 'array', 'min:1'],
            'invoice_ids.*' => ['integer', 'distinct', 'exists:purchase_invoices,id'],
            'use_vendor_advance' => ['nullable', 'boolean'],
            'difference_treatment' => ['nullable', 'string', 'in:outstanding,discount'],
            'allocation_order' => ['nullable', 'string', 'in:oldest,newest'],
            'allocations' => ['required_without:invoice_ids', 'array', 'min:1'],
            'allocations.*.purchase_invoice_id' => ['required', 'integer', 'distinct', 'exists:purchase_invoices,id'],
            'allocations.*.cash_allocated' => ['nullable', 'numeric', 'min:0'],
            'allocations.*.advance_allocated' => ['nullable', 'numeric', 'min:0'],
            'allocations.*.discount_allocated' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (isset($validated['invoice_ids'])) {
            $vendorSettlementService->createAutomatic($supplier, [
                'invoice_ids' => $validated['invoice_ids'],
                'actual_payment_amount' => (float) $validated['actual_payment_amount'],
                'use_vendor_advance' => (bool) ($validated['use_vendor_advance'] ?? false),
                'difference_treatment' => $validated['difference_treatment'] ?? 'outstanding',
                'allocation_order' => $validated['allocation_order'] ?? 'oldest',
                'payment_date' => $validated['payment_date'], 'payment_method' => $validated['payment_method'] ?? null,
                'company_account_id' => $validated['company_account_id'] ?? null, 'reference' => $validated['reference'] ?? null,
                'statement_entry_id' => $validated['statement_entry_id'] ?? null,
                'note' => $validated['note'] ?? null,
            ], (int) $request->user()->id);
        } else {
            $vendorSettlementService->create($supplier, [
                'actual_payment_amount' => (float) $validated['actual_payment_amount'],
                'settlement_discount_amount' => (float) ($validated['settlement_discount_amount'] ?? 0),
                'vendor_advance_used_amount' => (float) ($validated['vendor_advance_used_amount'] ?? 0),
                'payment_date' => $validated['payment_date'], 'payment_method' => $validated['payment_method'] ?? null,
                'company_account_id' => $validated['company_account_id'] ?? null, 'reference' => $validated['reference'] ?? null,
                'statement_entry_id' => $validated['statement_entry_id'] ?? null,
                'note' => $validated['note'] ?? null, 'allocations' => collect($validated['allocations'])->map(fn (array $row): array => [
                    'purchase_invoice_id' => (int) $row['purchase_invoice_id'], 'cash_allocated' => (float) ($row['cash_allocated'] ?? 0),
                    'advance_allocated' => (float) ($row['advance_allocated'] ?? 0), 'discount_allocated' => (float) ($row['discount_allocated'] ?? 0),
                ])->all(),
            ], (int) $request->user()->id);
        }

        return redirect()->back()->with('success', 'Vendor settlement recorded. Reconcile only actual Cash/Bank payment.');
    }

    public function settleVendorCreditInvoice(Request $request, PurchaseInvoice $invoice, PurchaseInvoiceService $purchaseInvoiceService): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $netAmount = max(0, (float) $invoice->amount - (float) $invoice->discount_amount);
        $currentPaid = (float) $invoice->paid_amount;
        $maxPayable = round(max(0, $netAmount - $currentPaid), 2);

        $validated = $request->validate([
            'payment_amount' => ['required', 'numeric', 'min:0.01', 'max:'.$maxPayable],
            'payment_method' => ['required', 'string', 'in:Bank,Cash,Online,GPay'],
            'company_account_id' => ['nullable', 'integer', 'exists:cashbook_company_accounts,id'],
            'payment_note' => ['nullable', 'string', 'max:500'],
        ]);

        $paymentAmount = round((float) $validated['payment_amount'], 2);
        $newTotalPaid = round($currentPaid + $paymentAmount, 2);

        $updatedInvoice = $purchaseInvoiceService->updatePayment($invoice, [
            'payment_method' => $validated['payment_method'],
            'payment_paid_by' => 'company',
            'payment_purchaser_id' => null,
            'paid_amount' => $newTotalPaid,
            'payment_note' => $validated['payment_note'] ?? 'Company vendor credit settlement',
            'payment_details' => $validated['payment_note'] ?? null,
        ]);

        $paidAmountCents = (int) round((float) $updatedInvoice->paid_amount * 100);
        $journalEntry = JournalEntry::query()
            ->where('source_type', PurchaseInvoice::class)
            ->where('source_id', $updatedInvoice->id)
            ->where('source_event', "company_vendor_credit_payment:paid-{$paidAmountCents}")
            ->latest('id')
            ->first();

        if (
            ! empty($validated['company_account_id'])
            && $journalEntry instanceof JournalEntry
            && ! CompanyAccountStatementEntry::query()
                ->where('journal_entry_id', $journalEntry->id)
                ->where('direction', 'out')
                ->where('amount', $paymentAmount)
                ->exists()
        ) {
            $paymentDate = $updatedInvoice->purchaserCart?->business_date?->toDateString()
                ?? $updatedInvoice->updated_at?->toDateString()
                ?? now()->toDateString();

            $this->companyPaymentReconciliationService->createStatementEntry([
                'company_account_id' => (int) $validated['company_account_id'],
                'journal_entry_id' => $journalEntry->id,
                'transaction_date' => $paymentDate,
                'direction' => 'out',
                'amount' => $paymentAmount,
                'reference' => $validated['payment_note'] ?? $updatedInvoice->invoice_number,
                'narration' => 'Vendor credit settlement for '.($updatedInvoice->supplier?->name ?? 'supplier').' / '.($updatedInvoice->invoice_number ?: '#'.$updatedInvoice->id),
                'source' => 'vendor_credit_payment',
                'notes' => $validated['payment_note'] ?? null,
            ], (int) $request->user()->id);
        }

        return redirect()->back()->with('success', 'Payment of ₹'.number_format($paymentAmount, 2)." recorded for {$updatedInvoice->invoice_number}.");
    }

    public function companyFinanceReconciliation(Request $request, ?string $statementRef = null): View
    {
        $this->ensureMainAdmin($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $companyAccounts = CompanyAccount::query()
            ->where('enabled', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
        $company = config('greenleaf');
        $currentShop = $shops->first();
        $month = (string) $request->input('month', today()->format('Y-m'));
        $selectedMonth = Carbon::parse($month.'-01');
        $monthStart = $selectedMonth->copy()->startOfMonth()->toDateString();
        $monthEnd = $selectedMonth->copy()->endOfMonth()->toDateString();
        $graceDays = max(0, min(60, (int) $request->input('grace_days', 10)));
        $selectedAccount = $request->filled('company_account_uuid')
            ? $companyAccounts->firstWhere('public_uuid', $request->string('company_account_uuid')->toString())
            : null;
        $selectedAccountId = (int) ($selectedAccount?->id ?? ($request->integer('company_account_id') ?: (int) ($companyAccounts->first()?->id ?? 0)));
        $selectedAccountUuid = (string) ($companyAccounts->firstWhere('id', $selectedAccountId)?->public_uuid ?? '');
        $direction = in_array((string) $request->input('direction', 'all'), ['all', 'in', 'out'], true)
            ? (string) $request->input('direction', 'all')
            : 'all';
        $queueStatus = in_array((string) $request->input('status', 'NEEDS_REVIEW'), ['NEEDS_REVIEW', 'SUGGESTED', 'RECONCILED', 'needs_action', 'unmatched', 'pending', 'partial', 'finalized_today'], true)
            ? (string) $request->input('status', 'NEEDS_REVIEW')
            : 'NEEDS_REVIEW';
        $workspaceTab = in_array((string) $request->input('workspace'), ['transactions', 'needs_reconciliation', 'statements', 'history'], true)
            ? (string) $request->input('workspace')
            : 'transactions';
        if ($workspaceTab === 'needs_reconciliation' && (! $request->filled('find_kind') || ! $request->filled('find_ref'))) {
            $workspaceTab = 'transactions';
        }
        $search = $workspaceTab === 'statements'
            ? trim((string) $request->input('search', ''))
            : '';
        $transactionSearch = ($workspaceTab === 'transactions' && $request->input('workspace') !== 'needs_reconciliation')
            ? trim((string) $request->input('search', ''))
            : '';
        $statementSearch = trim((string) $request->input('statement_search', ''));
        $activeTransactionType = (string) $request->input('type', 'all');
        $transactionRows = new LengthAwarePaginator([], 0, 25);
        $transactionCounts = $this->reconciliationTransactionQuery->counts($request, $monthStart, $monthEnd);
        if ($workspaceTab === 'transactions' && $queueStatus === 'RECONCILED') {
            $transactionRows = $this->reconciliationTransactionQuery->paginate($request, $monthStart, $monthEnd);
        }
        if ($workspaceTab === 'transactions' && $queueStatus !== 'RECONCILED') {
            $perPage = 25;
            $page = LengthAwarePaginator::resolveCurrentPage();
            $startIndex = ($page - 1) * $perPage;
            $visibleRows = collect();
            $matchedCount = 0;
            $suggestedCount = 0;
            $needsReviewCount = 0;
            $sourceCount = $this->reconciliationTransactionQuery->unreconciledCount($request, $monthStart, $monthEnd);

            for ($offset = 0; $offset < $sourceCount; $offset += 100) {
                $rows = $this->reconciliationTransactionQuery->unreconciledChunk($request, $monthStart, $monthEnd, $offset, 100);
                $suggestions = $this->reconciliationAutoMatchSuggestionService->suggest($rows, $graceDays);

                foreach ($suggestions as $suggestion) {
                    if ($suggestion->reconciliation_status === 'SUGGESTED') {
                        $suggestedCount++;
                    } else {
                        $needsReviewCount++;
                    }

                    if ($suggestion->reconciliation_status !== $queueStatus) {
                        continue;
                    }

                    if ($matchedCount >= $startIndex && $visibleRows->count() < $perPage) {
                        $visibleRows->push($suggestion);
                    }
                    $matchedCount++;
                }
            }

            $transactionCounts = [
                'needs_review' => $needsReviewCount,
                'suggested' => $suggestedCount,
                'reconciled' => $this->reconciliationTransactionQuery->reconciledCount($request, $monthStart, $monthEnd),
            ];
            $transactionRows = new LengthAwarePaginator($visibleRows, $matchedCount, $perPage, $page, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => $request->query(),
            ]);
        }
        if ($workspaceTab === 'transactions' && $queueStatus === 'RECONCILED') {
            $suggestedCount = 0;
            $needsReviewCount = 0;
            $sourceCount = $this->reconciliationTransactionQuery->unreconciledCount($request, $monthStart, $monthEnd);

            for ($offset = 0; $offset < $sourceCount; $offset += 100) {
                $rows = $this->reconciliationTransactionQuery->unreconciledChunk($request, $monthStart, $monthEnd, $offset, 100);
                foreach ($this->reconciliationAutoMatchSuggestionService->suggest($rows, $graceDays) as $suggestion) {
                    if ($suggestion->reconciliation_status === 'SUGGESTED') {
                        $suggestedCount++;
                    } else {
                        $needsReviewCount++;
                    }
                }
            }

            $transactionCounts = [
                'needs_review' => $needsReviewCount,
                'suggested' => $suggestedCount,
                'reconciled' => $this->reconciliationTransactionQuery->reconciledCount($request, $monthStart, $monthEnd),
            ];
        }
        $transactionTypeFilters = $direction === 'in'
            ? $this->reconciliationTransactionQuery->inTypes()
            : ($direction === 'out' ? $this->reconciliationTransactionQuery->outTypes() : ['all' => 'All']);
        $classifyStatement = null;

        if ($request->filled('classify_statement')) {
            $classifyStatement = CompanyAccountStatementEntry::query()
                ->with('companyAccount')
                ->where('public_uuid', $request->string('classify_statement')->toString())
                ->firstOrFail();

            abort_if(
                $classifyStatement->is_finalized
                || $classifyStatement->status !== 'unmatched'
                || $classifyStatement->journal_entry_id !== null,
                404
            );

            if ($selectedAccountId > 0 && (int) $classifyStatement->company_account_id !== $selectedAccountId) {
                abort(404);
            }
        }

        $summaryRow = CompanyAccountStatementEntry::query()
            ->selectRaw("coalesce(sum(case when direction = 'in' and date(transaction_date) = ? then amount else 0 end), 0) as money_in_today", [today()->toDateString()])
            ->selectRaw("coalesce(sum(case when direction = 'out' and date(transaction_date) = ? then amount else 0 end), 0) as money_out_today", [today()->toDateString()])
            ->selectRaw("sum(case when is_finalized = 0 and status in ('unmatched', 'partially_matched') then 1 else 0 end) as pending_reconciliation")
            ->selectRaw("sum(case when is_finalized = 0 and status = 'unmatched' and journal_entry_id is null then 1 else 0 end) as unmatched_statements")
            ->first();

        $summary = [
            'money_in_today' => round((float) ($summaryRow?->money_in_today ?? 0), 2),
            'money_out_today' => round((float) ($summaryRow?->money_out_today ?? 0), 2),
            'pending_reconciliation' => (int) ($summaryRow?->pending_reconciliation ?? 0),
            'unmatched_statements' => (int) ($summaryRow?->unmatched_statements ?? 0),
        ];

        $pendingSummary = $this->pendingReconciliationSummary();
        $historySummary = CompanyAccountStatementEntry::query()
            ->where('is_finalized', true)
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->selectRaw('count(*) as count, coalesce(sum(amount), 0) as amount')
            ->first();
        $partialSummary = CompanyAccountStatementEntry::query()
            ->where('is_finalized', false)
            ->where('status', 'partially_matched')
            ->selectRaw('count(*) as count, coalesce(sum(amount - matched_amount), 0) as amount')
            ->first();
        $unmatchedSummary = CompanyAccountStatementEntry::query()
            ->where('is_finalized', false)
            ->where('status', 'unmatched')
            ->whereNull('journal_entry_id')
            ->selectRaw('count(*) as count, coalesce(sum(amount), 0) as amount')
            ->first();
        $summary += [
            'awaiting_count' => $pendingSummary['count'],
            'awaiting_amount' => $pendingSummary['amount'],
            'unmatched_amount' => round((float) ($unmatchedSummary?->amount ?? 0), 2),
            'partial_count' => (int) ($partialSummary?->count ?? 0),
            'partial_amount' => round((float) ($partialSummary?->amount ?? 0), 2),
            'finalized_month_count' => (int) ($historySummary?->count ?? 0),
            'finalized_month_amount' => round((float) ($historySummary?->amount ?? 0), 2),
        ];

        $pendingSources = $workspaceTab === 'needs_reconciliation'
            ? $this->pendingReconciliationSources($request, $monthStart, $monthEnd, $direction, $search)
            : new LengthAwarePaginator([], 0, 20);
        $historyEntries = $workspaceTab === 'history'
            ? CompanyAccountStatementEntry::query()
                ->with(['companyAccount', 'journalEntry.transactions.account'])
                ->when($selectedAccountId > 0, fn (Builder $query) => $query->where('company_account_id', $selectedAccountId))
                ->when($direction !== 'all', fn (Builder $query) => $query->where('direction', $direction))
                ->where('is_finalized', true)
                ->whereBetween('transaction_date', [$monthStart, $monthEnd])
                ->latest('finalized_at')->latest('id')->paginate(20)->withQueryString()
            : new LengthAwarePaginator([], 0, 20);
        [$findPendingSource, $findStatementCandidateData] = $this->pendingSourceStatementFinder($request, $graceDays, $statementSearch ?: $search);
        $findStatementCandidates = $findStatementCandidateData['pending'] ?? [];
        $findReconciledStatementCandidates = $findStatementCandidateData['reconciled'] ?? [];
        $showPendingDetails = $request->boolean('details');

        $statementEntries = CompanyAccountStatementEntry::query()
            ->with(['companyAccount', 'journalEntry'])
            ->when($selectedAccountId > 0, fn ($query) => $query->where('company_account_id', $selectedAccountId))
            ->when($direction !== 'all', fn ($query) => $query->where('direction', $direction))
            ->when($queueStatus === 'needs_action', fn ($query) => $query->where('is_finalized', false)->whereIn('status', ['unmatched', 'partially_matched']))
            ->when($queueStatus === 'unmatched', fn ($query) => $query->where('is_finalized', false)->where('status', 'unmatched')->whereNull('journal_entry_id'))
            ->when($queueStatus === 'pending', fn ($query) => $query->where('is_finalized', false)->where('status', 'unmatched')->whereNotNull('journal_entry_id'))
            ->when($queueStatus === 'partial', fn ($query) => $query->where('is_finalized', false)->where('status', 'partially_matched'))
            ->when($queueStatus === 'finalized_today', fn ($query) => $query->where('is_finalized', true)->whereDate('finalized_at', today()->toDateString()))
            ->when($queueStatus !== 'finalized_today', fn ($query) => $query->whereBetween('transaction_date', [$monthStart, $monthEnd]))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('reference', 'like', '%'.$search.'%')
                        ->orWhere('narration', 'like', '%'.$search.'%')
                        ->orWhere('amount', 'like', '%'.$search.'%');
                });
            })
            ->oldest('transaction_date')
            ->oldest('id')
            ->paginate(20)
            ->withQueryString();

        $selectedStatement = $statementRef
            ? $this->resolveSecureStatementEntry($statementRef)
            : ($classifyStatement ?? $statementEntries->getCollection()->first());

        if ($selectedStatement instanceof CompanyAccountStatementEntry) {
            $selectedStatement->load('companyAccount', 'reconciliations.paymentRequest.shop', 'journalEntry.transactions.account');
            $selectedAccountId = (int) $selectedStatement->company_account_id;
            $selectedAccountUuid = (string) $selectedStatement->companyAccount?->public_uuid;
        }

        $possiblePayments = collect();
        if ($selectedStatement instanceof CompanyAccountStatementEntry) {
            $possiblePayments = $selectedStatement->journal_entry_id
                ? $this->possibleLinkedJournalsForStatement($selectedStatement)
                : ($selectedStatement->direction === 'out'
                    ? $this->possibleVendorPaymentJournalsForStatement($selectedStatement, $graceDays, $search)
                    : $this->possiblePaymentsForStatement($selectedStatement, $graceDays, $search));
        }

        $reconciliationEntryTypes = LedgerEntryType::query()
            ->where('active', true)
            ->whereIn('code', ['reconciliation_adjustment', 'bank_charges', 'short_receipt', 'excess_receipt'])
            ->orderBy('display_order')
            ->get();
        $incomeCategories = CompanyAccountingCategory::query()
            ->with('account')
            ->where('type', 'income')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $expenseCategories = CompanyAccountingCategory::query()
            ->with('account')
            ->where('type', 'expense')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $salaryPaymentTargets = collect();
        $salaryAdvanceTargets = collect();
        $companyPayableTargets = collect();
        $vendorPaymentTargets = collect();
        $selectedVendorPaymentTarget = null;
        $purchaserFundingTargets = collect();
        $selectedPurchaserFundingTarget = null;
        $selectedShopPettyTarget = null;
        $createTransactionTabs = $classifyStatement?->direction === 'in'
            ? ['income', 'shop-payment', 'direct-sale']
            : ['expense', 'payable', 'vendor', 'salary', 'advance', 'petty', 'purchaser'];
        $activeCreateTransactionTab = in_array((string) $request->input('type'), $createTransactionTabs, true)
            ? (string) $request->input('type')
            : ($classifyStatement?->direction === 'in' ? 'income' : 'expense');
        $recentCompanyAccountingEntries = collect();
        $recentCompanyPayableSettlements = collect();
        $recentVendorSettlements = collect();
        $recentSalaryPayments = collect();
        $recentSalaryAdvances = collect();
        $recentShopPayments = collect();
        $recentShopPettyFunding = collect();
        $recentPurchaserFunding = collect();
        $eligibleShopPayments = collect();
        $matchExistingCandidates = $classifyStatement instanceof CompanyAccountStatementEntry
            ? $this->possibleExistingJournalCandidatesForStatement($classifyStatement, $graceDays, $search)
            : ['pending' => [], 'reconciled' => [], 'counts' => ['pending' => 0, 'reconciled' => 0, 'exact_date_pending' => 0, 'exact_date_reconciled' => 0]];

        if ($classifyStatement instanceof CompanyAccountStatementEntry && $request->boolean('create_transaction')) {
            $recentCompanyAccountingEntries = CompanyAccountingEntry::query()
                ->with(['category', 'cashbookMovement'])
                ->where('type', $classifyStatement->direction === 'in' ? 'income' : 'expense')
                ->whereNotNull('company_account_id')
                ->latest('business_date')
                ->latest('id')
                ->limit(5)
                ->get();
            $recentCompanyPayableSettlements = CompanyPayableSettlement::query()
                ->with(['shop', 'line.entry'])
                ->latest('settlement_date')
                ->latest('id')
                ->limit(5)
                ->get();
            $recentVendorSettlements = VendorSettlement::query()
                ->with('supplier')
                ->latest('payment_date')
                ->latest('id')
                ->limit(5)
                ->get();
            $recentSalaryPayments = PayrollPayment::query()
                ->with(['employee', 'payrollRunItem.payrollRun'])
                ->whereNull('employee_advance_request_id')
                ->latest('paid_on')
                ->latest('id')
                ->limit(5)
                ->get();
            $recentSalaryAdvances = PayrollPayment::query()
                ->with(['employee', 'advanceRequest'])
                ->whereNotNull('employee_advance_request_id')
                ->latest('paid_on')
                ->latest('id')
                ->limit(5)
                ->get();
            $recentShopPayments = ShopInvoicePaymentRequest::query()
                ->with('shop')
                ->latest('payment_date')
                ->latest('id')
                ->limit(5)
                ->get();
            $recentShopPettyFunding = ShopLedgerTransaction::query()
                ->with('companyAccount')
                ->where('reference_type', CompanyAccountStatementEntry::class)
                ->where('petty_direction', 'in')
                ->latest('business_date')
                ->latest('id')
                ->limit(5)
                ->get();
            $recentPurchaserFunding = PurchaserCredit::query()
                ->with('purchaser')
                ->where('type', 'in')
                ->whereNotNull('company_account_id')
                ->latest('business_date')
                ->latest('id')
                ->limit(5)
                ->get();

            if ($classifyStatement->direction === 'in' && $activeCreateTransactionTab === 'shop-payment') {
                $eligibleShopPayments = $this->eligibleShopPaymentsForStatement($classifyStatement, $search);
            }
        }

        if ($classifyStatement instanceof CompanyAccountStatementEntry && $classifyStatement->direction === 'out') {
            $statementAmount = round((float) $classifyStatement->amount, 2);

            if ($request->boolean('create_transaction') && $activeCreateTransactionTab === 'payable') {
                $companyPayableTargets = ShopAccountingEntryLine::query()
                    ->with(['entry.shop', 'category', 'settlements'])
                    ->where('funding_source', ShopAccountingEntryLine::FundingCompany)
                    ->where('company_payable_status', ShopAccountingEntryLine::PayableApproved)
                    ->latest('id')
                    ->limit(100)
                    ->get()
                    ->filter(fn (ShopAccountingEntryLine $line): bool => $line->remainingCompanyPayableAmount() + 0.01 >= $statementAmount)
                    ->take(30)
                    ->values();
            }

            if ($request->boolean('create_transaction') && $activeCreateTransactionTab === 'vendor') {
                $vendorPaymentTargets = Supplier::query()
                    ->with(['vendorAdvances' => fn ($query) => $query->where('amount_remaining', '>', 0)])
                    ->whereHas('purchaseInvoices', fn (Builder $query) => $query
                        ->whereIn('payment_status', ['credit_pending_approval', 'partial', 'unpaid'])
                        ->where('payment_paid_by', 'vendor_credit'))
                    ->orderBy('name')
                    ->limit(30)
                    ->get();
            }

            if ($request->boolean('create_transaction') && $activeCreateTransactionTab === 'vendor' && $request->filled('supplier_id')) {
                $selectedVendorPaymentTarget = Supplier::query()
                    ->with([
                        'purchaseInvoices' => fn ($query) => $query
                            ->whereIn('payment_status', ['credit_pending_approval', 'partial', 'unpaid'])
                            ->where('payment_paid_by', 'vendor_credit')
                            ->latest('id')
                            ->limit(30),
                        'purchaseInvoices.vendorSettlementAllocations',
                        'vendorAdvances' => fn ($query) => $query->where('amount_remaining', '>', 0),
                    ])
                    ->whereKey($request->integer('supplier_id'))
                    ->first();
            }

            if ($request->boolean('create_transaction') && $activeCreateTransactionTab === 'purchaser') {
                $purchaserFundingTargets = User::query()
                    ->whereHas('roles', fn (Builder $query) => $query->where('name', 'purchaser'))
                    ->orderBy('name')
                    ->limit(50)
                    ->get();
                $selectedPurchaserFundingTarget = $request->filled('purchaser_uuid')
                    ? $purchaserFundingTargets->firstWhere('public_uuid', $request->string('purchaser_uuid')->toString())
                    : null;
            }

            if ($request->boolean('create_transaction') && $activeCreateTransactionTab === 'salary') {
                $salaryPaymentTargets = PayrollRunItem::query()
                    ->with(['employee', 'payrollRun', 'payments'])
                    ->whereHas('payrollRun', fn (Builder $query) => $query->where('status', 'finalized'))
                    ->latest('id')
                    ->limit(100)
                    ->get()
                    ->filter(fn (PayrollRunItem $item): bool => $item->remainingGreenLeafAmount() + 0.01 >= $statementAmount)
                    ->take(30)
                    ->values();
            }

            if ($request->boolean('create_transaction') && $activeCreateTransactionTab === 'advance') {
                $salaryAdvanceTargets = EmployeeAdvanceRequest::query()
                    ->with(['employee', 'shop', 'payrollPayment', 'shopStaffPayment.payrollRunItem.payrollRun'])
                    ->where('status', 'approved')
                    ->whereNull('payroll_payment_id')
                    ->latest('id')
                    ->limit(100)
                    ->get()
                    ->filter(function (EmployeeAdvanceRequest $advanceRequest) use ($statementAmount): bool {
                        $alreadyPaid = (float) $advanceRequest->payrollPayment?->amount;
                        $remaining = round((float) $advanceRequest->approved_amount - $alreadyPaid, 2);

                        return $remaining + 0.01 >= $statementAmount && $advanceRequest->shopStaffPayment?->payrollRunItem instanceof PayrollRunItem;
                    })
                    ->take(30)
                    ->values();
            }

            if ($request->boolean('create_transaction') && $activeCreateTransactionTab === 'petty' && $request->filled('shop_uuid')) {
                $selectedShopPettyTarget = $shops->firstWhere('public_uuid', $request->string('shop_uuid')->toString());
            }
        }

        return view('admin.cashbook.finance.reconciliation', compact(
            'shops',
            'companyAccounts',
            'company',
            'currentShop',
            'month',
            'monthStart',
            'monthEnd',
            'graceDays',
            'selectedAccountId',
            'selectedAccountUuid',
            'search',
            'statementSearch',
            'transactionSearch',
            'direction',
            'queueStatus',
            'workspaceTab',
            'transactionRows',
            'transactionCounts',
            'transactionTypeFilters',
            'activeTransactionType',
            'summary',
            'pendingSources',
            'historyEntries',
            'findPendingSource',
            'findStatementCandidates',
            'findReconciledStatementCandidates',
            'showPendingDetails',
            'classifyStatement',
            'statementEntries',
            'selectedStatement',
            'possiblePayments',
            'reconciliationEntryTypes',
            'incomeCategories',
            'expenseCategories',
            'companyPayableTargets',
            'vendorPaymentTargets',
            'selectedVendorPaymentTarget',
            'purchaserFundingTargets',
            'selectedPurchaserFundingTarget',
            'selectedShopPettyTarget',
            'salaryPaymentTargets',
            'salaryAdvanceTargets',
            'createTransactionTabs',
            'activeCreateTransactionTab',
            'recentCompanyAccountingEntries',
            'recentCompanyPayableSettlements',
            'recentVendorSettlements',
            'recentSalaryPayments',
            'recentSalaryAdvances',
            'recentShopPayments',
            'recentShopPettyFunding',
            'recentPurchaserFunding',
            'eligibleShopPayments',
            'matchExistingCandidates',
        ));
    }

    public function createCompanyFinanceReconciliationTransaction(Request $request, CompanyAccountStatementEntry $statement): View
    {
        $request->merge([
            'classify_statement' => $statement->public_uuid,
            'company_account_uuid' => $statement->companyAccount?->public_uuid,
            'create_transaction' => true,
        ]);

        return $this->companyFinanceReconciliation($request);
    }

    public function resetMonthReconciliation(Request $request): RedirectResponse
    {
        $this->ensureMainAdmin($request);
        abort_unless($request->user()->isMainAdmin() || $request->user()->hasRole('admin'), 403);

        $validated = $request->validate([
            'month' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'confirmation' => ['required', 'string'],
        ]);

        $monthDate = Carbon::createFromFormat('!Y-m', $validated['month']);
        $expectedPhrase = 'CLEAR '.strtoupper($monthDate->format('F Y'));

        if (trim(strtoupper((string) $validated['confirmation'])) !== $expectedPhrase) {
            throw ValidationException::withMessages([
                'confirmation' => "Please type '{$expectedPhrase}' to confirm clearing reconciliation.",
            ]);
        }

        $result = $this->companyPaymentReconciliationService->resetMonthReconciliation(
            $validated['month'],
            (int) $request->user()->id,
        );

        $message = "{$monthDate->format('F Y')} reset: {$result['cleared']} imported matches cleared, {$result['skipped']} manual counterparts skipped, 0 failures.";

        $redirect = redirect()
            ->route('admin.cashbook.finance.reconciliation', [
                'month' => $validated['month'],
                'workspace' => 'transactions',
                'status' => 'NEEDS_REVIEW',
            ])
            ->with('success', $message);

        if ($result['skipped'] > 0) {
            $redirect->with('skipped_reconciliations', $result['skipped_entries']);
        }

        return $redirect;
    }

    public function pendingReconciliationCandidates(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        [, $candidatesData] = $this->pendingSourceStatementFinder($request, 10, (string) $request->input('search', ''));

        if (! is_array($candidatesData)) {
            return response()->json([
                'candidates' => [],
                'reconciled' => [],
                'counts' => [
                    'pending' => 0,
                    'reconciled' => 0,
                    'exact_date_pending' => 0,
                    'exact_date_reconciled' => 0,
                ],
            ]);
        }

        $mapCandidate = fn (array $cand): array => [
            'id' => $cand['id'],
            'public_uuid' => $cand['public_uuid'],
            'date' => $cand['transaction_date'],
            'raw_date' => $cand['raw_date'],
            'date_match' => $cand['date_match'],
            'date_difference_days' => $cand['date_difference_days'],
            'date_badge_text' => $cand['date_badge_text'],
            'account' => $cand['account_name'],
            'account_type' => $cand['account_type'],
            'amount' => $cand['amount'],
            'formatted_amount' => $cand['formatted_amount'],
            'reference' => $cand['reference'],
            'narration' => $cand['narration'],
            'status' => $cand['status'],
            'matched_to' => $cand['matched_to'] ?? null,
            'matched_date' => $cand['matched_date'] ?? null,
            'matched_by' => $cand['matched_by'] ?? null,
            'match_url' => route('admin.cashbook.finance.reconciliation.'.($request->input('find_kind') === 'shop_payment' ? 'classify-shop-payment' : 'match-existing'), ['statement' => $cand['public_uuid']]),
        ];

        return response()->json([
            'candidates' => array_map($mapCandidate, $candidatesData['pending'] ?? []),
            'reconciled' => array_map($mapCandidate, $candidatesData['reconciled'] ?? []),
            'counts' => $candidatesData['counts'] ?? [
                'pending' => count($candidatesData['pending'] ?? []),
                'reconciled' => count($candidatesData['reconciled'] ?? []),
                'exact_date_pending' => 0,
                'exact_date_reconciled' => 0,
            ],
        ]);
    }

    public function classifyCompanyAccountingStatement(Request $request, CompanyAccountStatementEntry $statement): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'type' => ['required', 'in:income,expense'],
            'company_accounting_category_id' => ['required', 'integer', 'exists:company_accounting_categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $entry = $this->companyAccountingCashbookService->createFromStatement($statement, $validated, (int) $request->user()->id);

        return redirect()
            ->route('admin.cashbook.finance.reconciliation', [
                'company_account_uuid' => $statement->fresh('companyAccount')?->companyAccount?->public_uuid,
                'month' => $statement->transaction_date?->format('Y-m'),
            ])
            ->with('success', 'Statement classified as other '.$entry->type.' and finalized.');
    }

    public function classifyShopPettyStatement(Request $request, CompanyAccountStatementEntry $statement): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'shop_uuid' => ['required', 'uuid', 'exists:shops,public_uuid'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $shop = Shop::query()->where('public_uuid', (string) $validated['shop_uuid'])->firstOrFail();

        $this->shopPettyFundingService->fundFromStatement($shop, $statement, [
            'notes' => $validated['notes'] ?? null,
        ], (int) $request->user()->id);

        return redirect()
            ->route('admin.cashbook.finance.reconciliation', [
                'company_account_uuid' => $statement->fresh('companyAccount')?->companyAccount?->public_uuid,
                'month' => $statement->transaction_date?->format('Y-m'),
            ])
            ->with('success', 'Statement classified as shop petty funding and finalized.');
    }

    public function classifyCompanyPayableStatement(Request $request, CompanyAccountStatementEntry $statement, CompanyPayableService $companyPayableService): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'shop_accounting_entry_line_id' => ['required', 'integer', 'exists:shop_accounting_entry_lines,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $line = ShopAccountingEntryLine::query()->whereKey((int) $validated['shop_accounting_entry_line_id'])->firstOrFail();
        $companyPayableService->settleDirectPaymentFromStatement($line, $statement, (int) $request->user()->id, $validated['notes'] ?? null);

        return redirect()
            ->route('admin.cashbook.finance.reconciliation', [
                'company_account_uuid' => $statement->fresh('companyAccount')?->companyAccount?->public_uuid,
                'month' => $statement->transaction_date?->format('Y-m'),
            ])
            ->with('success', 'Statement classified as company payable and finalized.');
    }

    public function classifyVendorPaymentStatement(Request $request, CompanyAccountStatementEntry $statement, VendorSettlementService $vendorSettlementService): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['integer', 'distinct', 'exists:purchase_invoices,id'],
            'use_vendor_advance' => ['nullable', 'boolean'],
            'difference_treatment' => ['nullable', 'string', 'in:outstanding,discount'],
            'allocation_order' => ['nullable', 'string', 'in:oldest,newest'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $statement = CompanyAccountStatementEntry::query()
            ->with('companyAccount')
            ->whereKey($statement->id)
            ->firstOrFail();
        $supplier = Supplier::query()->whereKey((int) $validated['supplier_id'])->firstOrFail();
        $companyAccount = $statement->companyAccount;

        $vendorSettlementService->createAutomatic($supplier, [
            'invoice_ids' => $validated['invoice_ids'],
            'actual_payment_amount' => (float) $statement->amount,
            'use_vendor_advance' => (bool) ($validated['use_vendor_advance'] ?? false),
            'difference_treatment' => $validated['difference_treatment'] ?? 'outstanding',
            'allocation_order' => $validated['allocation_order'] ?? 'oldest',
            'payment_date' => $statement->transaction_date?->toDateString() ?? today()->toDateString(),
            'payment_method' => $companyAccount?->account_type === 'cash' ? 'Cash' : 'Bank',
            'company_account_id' => $statement->company_account_id,
            'statement_entry_id' => $statement->id,
            'reference' => $statement->reference,
            'note' => $validated['note'] ?? null,
        ], (int) $request->user()->id);

        return redirect()
            ->route('admin.cashbook.finance.reconciliation', [
                'company_account_uuid' => $statement->fresh('companyAccount')?->companyAccount?->public_uuid,
                'month' => $statement->transaction_date?->format('Y-m'),
            ])
            ->with('success', 'Statement classified as vendor settlement and finalized.');
    }

    public function classifyPurchaserFundingStatement(Request $request, CompanyAccountStatementEntry $statement, JournalService $journalService): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'purchaser_uuid' => ['required', 'uuid', 'exists:users,public_uuid'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $purchaser = User::query()
            ->where('public_uuid', (string) $validated['purchaser_uuid'])
            ->firstOrFail();

        abort_unless($purchaser->hasRole('purchaser'), 404);

        DB::transaction(function () use ($request, $statement, $purchaser, $journalService, $validated): void {
            $statement = CompanyAccountStatementEntry::query()
                ->with('companyAccount')
                ->whereKey($statement->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($statement->is_finalized || $statement->status !== 'unmatched' || $statement->journal_entry_id !== null || $statement->source_type !== null || $statement->direction !== 'out') {
                throw ValidationException::withMessages(['statement' => 'This statement row cannot be classified as purchaser funding.']);
            }

            $companyAccount = $statement->companyAccount;
            if (! $companyAccount instanceof CompanyAccount || ! $companyAccount->enabled || ! in_array($companyAccount->account_type, ['cash', 'bank'], true)) {
                throw ValidationException::withMessages(['statement' => 'Statement company account is not valid for purchaser funding.']);
            }

            $credit = PurchaserCredit::query()->create([
                'purchaser_id' => $purchaser->id,
                'type' => 'in',
                'amount' => round((float) $statement->amount, 2),
                'description' => $validated['description'] ?? 'Company funding to purchaser',
                'payment_source' => $companyAccount->account_type === 'cash' ? 'Cash' : 'Bank',
                'company_account_id' => $companyAccount->id,
                'reference' => $statement->reference,
                'created_by' => $request->user()->id,
                'business_date' => $statement->transaction_date?->toDateString() ?? today()->toDateString(),
            ]);

            $journalEntry = $journalService->recordPurchaserCredit($credit);

            $statement->update([
                'journal_entry_id' => $journalEntry->id,
                'source' => 'purchaser_funding',
                'source_type' => PurchaserCredit::class,
                'source_id' => $credit->id,
                'narration' => $statement->narration ?: 'Company funding to purchaser '.$purchaser->name,
                'notes' => $validated['description'] ?? null,
            ]);

            $this->companyPaymentReconciliationService->reconcileStatementJournal(
                $statement,
                $journalEntry,
                (float) $statement->amount,
                (int) $request->user()->id,
            );
        }, attempts: 3);

        return redirect()
            ->route('admin.cashbook.finance.reconciliation', [
                'company_account_uuid' => $statement->fresh('companyAccount')?->companyAccount?->public_uuid,
                'month' => $statement->transaction_date?->format('Y-m'),
            ])
            ->with('success', 'Statement classified as purchaser funding and finalized.');
    }

    public function classifySalaryPaymentStatement(Request $request, CompanyAccountStatementEntry $statement): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'payroll_run_item_id' => ['required', 'integer', 'exists:payroll_run_items,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $payrollRunItem = PayrollRunItem::query()->whereKey((int) $validated['payroll_run_item_id'])->firstOrFail();
        $this->payrollPaymentService->recordSalaryFromStatement($payrollRunItem, $statement, $request->user(), $validated['notes'] ?? null);

        return redirect()
            ->route('admin.cashbook.finance.reconciliation', [
                'company_account_uuid' => $statement->fresh('companyAccount')?->companyAccount?->public_uuid,
                'month' => $statement->transaction_date?->format('Y-m'),
            ])
            ->with('success', 'Statement classified as salary payment and finalized.');
    }

    public function classifySalaryAdvanceStatement(Request $request, CompanyAccountStatementEntry $statement): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'employee_advance_request_id' => ['required', 'integer', 'exists:employee_advance_requests,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $advanceRequest = EmployeeAdvanceRequest::query()->whereKey((int) $validated['employee_advance_request_id'])->firstOrFail();
        $this->payrollPaymentService->recordAdvanceFromStatement($advanceRequest, $statement, $request->user(), $validated['notes'] ?? null);

        return redirect()
            ->route('admin.cashbook.finance.reconciliation', [
                'company_account_uuid' => $statement->fresh('companyAccount')?->companyAccount?->public_uuid,
                'month' => $statement->transaction_date?->format('Y-m'),
            ])
            ->with('success', 'Statement classified as salary advance and finalized.');
    }

    public function classifyShopPaymentStatement(Request $request, CompanyAccountStatementEntry $statement): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'payment_request_ref' => ['required', 'string'],
        ]);

        DB::transaction(function () use ($request, $statement, $validated): void {
            $statement = CompanyAccountStatementEntry::query()
                ->with('companyAccount')
                ->whereKey($statement->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($statement->is_finalized || $statement->status !== 'unmatched' || $statement->journal_entry_id !== null || $statement->source_type !== null || $statement->direction !== 'in') {
                throw ValidationException::withMessages(['statement' => 'This statement row cannot be classified as a shop payment.']);
            }

            $paymentRequest = ShopInvoicePaymentRequest::query()
                ->with(['shop', 'reconciliations'])
                ->withExists('allocations')
                ->whereKey($this->decodeFinanceRouteKey((string) $validated['payment_request_ref'], 'shop-payment'))
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->isEligibleShopPaymentForStatement($paymentRequest, $statement)) {
                throw ValidationException::withMessages(['payment_request_ref' => 'Selected shop payment is not eligible for this statement amount and company account.']);
            }

            $amount = round((float) $statement->amount, 2);
            $this->companyPaymentReconciliationService->reconcilePayment(
                $paymentRequest,
                [
                    'company_account_id' => $statement->company_account_id,
                    'statement_entry_id' => $statement->id,
                    'statement_amount' => $amount,
                    'cleared_amount' => $amount,
                    'difference_amount' => 0,
                    'difference_action' => 'none',
                    'business_date' => $statement->transaction_date?->toDateString(),
                ],
                (int) $request->user()->id,
            );

            CompanyAccountStatementEntry::query()
                ->whereKey($statement->id)
                ->update([
                    'source' => 'shop_payment',
                    'source_type' => ShopInvoicePaymentRequest::class,
                    'source_id' => $paymentRequest->id,
                ]);
        }, attempts: 3);

        return redirect()
            ->route('admin.cashbook.finance.reconciliation', [
                'company_account_uuid' => $statement->fresh('companyAccount')?->companyAccount?->public_uuid,
                'month' => $statement->transaction_date?->format('Y-m'),
            ])
            ->with('success', 'Statement matched to shop payment and finalized.');
    }

    public function matchExistingStatement(Request $request, CompanyAccountStatementEntry $statement): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'candidate_ref' => ['required', 'string'],
        ]);

        $isReplaced = $this->confirmExistingStatementMatch(
            $statement,
            (string) $validated['candidate_ref'],
            (int) $request->user()->id,
            allowReplacement: true,
        );

        $message = $isReplaced
            ? 'Statement match replaced successfully. Previous transaction returned to pending reconciliation.'
            : 'Statement matched to existing transaction and finalized.';

        return redirect()
            ->route('admin.cashbook.finance.reconciliation', [
                'company_account_uuid' => $statement->fresh('companyAccount')?->companyAccount?->public_uuid,
                'month' => $statement->transaction_date?->format('Y-m'),
            ])
            ->with('success', $message);
    }

    public function confirmSuggestedStatement(Request $request, CompanyAccountStatementEntry $statement): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'candidate_ref' => ['required', 'string'],
        ]);

        $this->confirmExistingStatementMatch(
            $statement,
            (string) $validated['candidate_ref'],
            (int) $request->user()->id,
            allowReplacement: false,
        );

        return redirect()->back()->with('success', 'Suggested statement match confirmed.');
    }

    public function confirmSuggestedStatements(Request $request): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'matches' => ['required', 'array', 'min:1', 'max:25'],
            'matches.*.statement_uuid' => ['required', 'uuid'],
            'matches.*.candidate_ref' => ['required', 'string'],
        ]);

        $confirmed = 0;
        $failures = [];

        foreach ($validated['matches'] as $match) {
            try {
                $statement = CompanyAccountStatementEntry::query()
                    ->where('public_uuid', $match['statement_uuid'])
                    ->firstOrFail();
                $this->confirmExistingStatementMatch(
                    $statement,
                    (string) $match['candidate_ref'],
                    (int) $request->user()->id,
                    allowReplacement: false,
                );
                $confirmed++;
            } catch (ValidationException $exception) {
                $failures[] = $exception->validator->errors()->first();
            } catch (Throwable $exception) {
                report($exception);
                $failures[] = 'Suggested match is no longer available. Review matches and try again.';
            }
        }

        $response = redirect()->back()->with('success', "Confirmed {$confirmed} suggested match".($confirmed === 1 ? '' : 'es').'.');

        return $failures === []
            ? $response
            : $response->with('reconciliation_failures', $failures);
    }

    public function previewAutoMatchShopCollections(
        Request $request,
        ShopCollectionAutoMatchService $autoMatchService
    ): JsonResponse {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'month_start' => ['required', 'date_format:Y-m-d'],
            'month_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:month_start'],
            'company_account_id' => ['nullable', 'integer', 'exists:cashbook_company_accounts,id'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'entry_type_id' => ['nullable', 'integer', 'exists:cashbook_ledger_entry_types,id'],
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:15'],
        ]);

        $preview = $autoMatchService->preview(
            (string) $validated['month_start'],
            (string) $validated['month_end'],
            isset($validated['company_account_id']) ? (int) $validated['company_account_id'] : null,
            isset($validated['shop_id']) ? (int) $validated['shop_id'] : null,
            isset($validated['entry_type_id']) ? (int) $validated['entry_type_id'] : null,
            (int) ($validated['grace_days'] ?? 2)
        );

        return response()->json([
            'success' => true,
            'preview' => $preview,
        ]);
    }

    public function executeAutoMatchShopCollections(
        Request $request,
        ShopCollectionAutoMatchService $autoMatchService
    ): JsonResponse {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'month_start' => ['required', 'date_format:Y-m-d'],
            'month_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:month_start'],
            'company_account_id' => ['nullable', 'integer', 'exists:cashbook_company_accounts,id'],
        ]);

        $result = $autoMatchService->execute(
            (string) $validated['month_start'],
            (string) $validated['month_end'],
            isset($validated['company_account_id']) ? (int) $validated['company_account_id'] : null,
            (int) $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => "Successfully auto-matched and finalized {$result['reconciled_count']} shop collections (₹".number_format($result['reconciled_amount'], 2).').',
            'result' => $result,
        ]);
    }

    public function reassignAutoMatchBankMapping(
        Request $request,
        ShopCollectionAutoMatchService $autoMatchService
    ): JsonResponse {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['required', 'integer', 'exists:shop_ledger_transactions,id'],
        ]);

        $result = $autoMatchService->reassignToConfiguredBank(
            array_map('intval', $validated['transaction_ids']),
            (int) $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => "Reassigned {$result['reassigned_count']} transactions (₹".number_format($result['reassigned_amount'], 2).') to their configured bank accounts.',
            'result' => $result,
        ]);
    }

    private function confirmExistingStatementMatch(
        CompanyAccountStatementEntry $statement,
        string $candidateReference,
        int $userId,
        bool $allowReplacement,
    ): bool {
        return DB::transaction(function () use ($statement, $candidateReference, $userId, $allowReplacement): bool {
            $statement = CompanyAccountStatementEntry::query()
                ->with('companyAccount')
                ->whereKey($statement->id)
                ->lockForUpdate()
                ->firstOrFail();

            $shopLedgerId = $this->tryDecodeFinanceRouteKey($candidateReference, 'shop-ledger');
            if ($shopLedgerId !== null) {
                $transaction = ShopLedgerTransaction::query()
                    ->whereKey($shopLedgerId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $statementAmount = round((float) $statement->amount, 2);
                $resolved = $this->expectedAmountService->resolve(
                    (int) $transaction->shop_id,
                    $transaction->business_date->toDateString(),
                    (int) $transaction->entry_type_id,
                    (float) $transaction->amount
                );
                $expectedPaymentAmount = (float) $resolved['expected_amount'];

                if (abs($expectedPaymentAmount - $statementAmount) > 0.01) {
                    throw ValidationException::withMessages(['candidate_ref' => 'Statement amount must match the selected transaction payment amount.']);
                }

                $this->companyPaymentReconciliationService->reconcileStatementShopLedger(
                    $statement,
                    $transaction,
                    $statementAmount,
                    $userId
                );

                return false;
            }

            $journalEntryId = $this->decodeFinanceRouteKey($candidateReference, 'journal-entry');
            $journalEntry = JournalEntry::query()
                ->with(['transactions.account', 'statementEntries'])
                ->whereKey($journalEntryId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($journalEntry->source_type, $this->matchableCashbookSourceTypes(), true)) {
                throw ValidationException::withMessages(['candidate_ref' => 'Selected transaction is not supported for Cashbook matching.']);
            }

            $statementAmount = round((float) $statement->amount, 2);
            $journalAmount = round((float) $journalEntry->primary_amount, 2);

            if (abs($journalAmount - $statementAmount) > 0.01) {
                throw ValidationException::withMessages(['candidate_ref' => 'Statement amount must match the selected transaction amount.']);
            }

            $isReconciledJournal = $journalEntry->statementEntries->where('is_finalized', true)->isNotEmpty();
            $isReconciledStatement = $statement->is_finalized || $statement->journal_entry_id !== null;

            if ($isReconciledJournal || $isReconciledStatement) {
                if (! $allowReplacement) {
                    throw ValidationException::withMessages(['candidate_ref' => 'Suggested match is no longer available. Review matches and try again.']);
                }

                $this->companyPaymentReconciliationService->replaceStatementJournalMatch(
                    $statement,
                    $journalEntry,
                    $statementAmount,
                    $userId,
                );

                return true;
            } else {
                $this->companyPaymentReconciliationService->reconcileStatementJournal(
                    $statement,
                    $journalEntry,
                    $statementAmount,
                    $userId,
                );
            }

            return false;
        }, attempts: 3);
    }

    public function matchStatementReconciliation(Request $request, string $statementRef): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $statementEntry = $this->resolveSecureStatementEntry($statementRef);
        $validated = $request->validate([
            'payment_request_ref' => ['required', 'string'],
            'journal_entry_id' => ['nullable', 'integer', 'exists:journal_entries,id'],
            'statement_amount' => ['nullable', 'numeric', 'min:0'],
            'cleared_amount' => ['required', 'numeric', 'min:0.01'],
            'difference_amount' => ['nullable', 'numeric', 'min:0'],
            'difference_action' => ['required', 'in:none,keep_floating,shop_expense,shop_income'],
            'difference_entry_type_id' => ['nullable', 'integer', 'exists:ledger_entry_types,id'],
            'business_date' => ['nullable', 'date_format:Y-m-d'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $paymentRequest = $this->resolveSecurePaymentRequest((string) $validated['payment_request_ref']);
        $remainingStatementAmount = round((float) $statementEntry->amount - (float) $statementEntry->matched_amount, 2);

        $this->companyPaymentReconciliationService->reconcilePayment(
            $paymentRequest,
            [
                'company_account_id' => $statementEntry->company_account_id,
                'statement_entry_id' => $statementEntry->id,
                'journal_entry_id' => $validated['journal_entry_id'] ?? null,
                'statement_amount' => (float) ($validated['statement_amount'] ?? $remainingStatementAmount),
                'cleared_amount' => $validated['cleared_amount'],
                'difference_amount' => $validated['difference_amount'] ?? 0,
                'difference_action' => $validated['difference_action'],
                'difference_entry_type_id' => $validated['difference_entry_type_id'] ?? null,
                'business_date' => $validated['business_date'] ?? $statementEntry->transaction_date?->toDateString(),
                'admin_note' => $validated['admin_note'] ?? null,
            ],
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.cashbook.finance.reconciliation', [
                'company_account_id' => $statementEntry->company_account_id,
                'month' => $statementEntry->transaction_date?->format('Y-m'),
                'grace_days' => $request->input('grace_days', 10),
            ])
            ->with('success', 'Statement row matched and reconciliation approved.');
    }

    public function matchStatementJournalReconciliation(Request $request, string $statementRef): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $statementEntry = $this->resolveSecureStatementEntry($statementRef);
        $validated = $request->validate([
            'journal_entry_id' => ['required', 'integer', 'exists:journal_entries,id'],
            'cleared_amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $journalEntry = JournalEntry::query()->findOrFail((int) $validated['journal_entry_id']);

        $this->companyPaymentReconciliationService->reconcileStatementJournal(
            $statementEntry,
            $journalEntry,
            round((float) $validated['cleared_amount'], 2),
            (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.cashbook.finance.reconciliation', [
                'company_account_id' => $statementEntry->company_account_id,
                'month' => $statementEntry->transaction_date?->format('Y-m'),
                'grace_days' => $request->input('grace_days', 10),
            ])
            ->with('success', 'Vendor payment statement row reconciled to journal entry.');
    }

    public function companyFinanceJournalShowSecure(Request $request, string $paymentRef): View
    {
        return $this->companyFinanceJournalShow(
            $request,
            $this->resolveSecurePaymentRequest($paymentRef),
        );
    }

    public function companyFinanceJournalShow(Request $request, ShopInvoicePaymentRequest $paymentRequest): View
    {
        $this->ensureMainAdmin($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $companyAccounts = CompanyAccount::where('enabled', true)->orderBy('name')->get();
        $company = config('greenleaf');
        $currentShop = $shops->first();

        $paymentRequest->load([
            'shop',
            'invoice',
            'requestedBy',
            'reviewedBy',
            'allocations.invoice',
            'reconciliations.companyAccount',
            'reconciliations.statementEntry',
            'reconciliations.differenceTransaction.entryType',
            'reconciliations.reconciledBy',
        ]);

        $openStatementEntries = CompanyAccountStatementEntry::query()
            ->with('companyAccount')
            ->whereIn('status', ['unmatched', 'partially_matched'])
            ->latest('transaction_date')
            ->latest('id')
            ->limit(30)
            ->get();

        return view('admin.cashbook.finance.journal-show', compact(
            'shops',
            'companyAccounts',
            'company',
            'currentShop',
            'paymentRequest',
            'openStatementEntries',
        ));
    }

    public function storeCompanyStatementEntry(Request $request): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'company_account_id' => ['required', 'integer', 'exists:cashbook_company_accounts,id'],
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'value_date' => ['nullable', 'date_format:Y-m-d'],
            'direction' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:160'],
            'narration' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->companyPaymentReconciliationService->createStatementEntry($validated, (int) $request->user()->id);

        return redirect()->back()
            ->with('success', 'Statement entry added for reconciliation.');
    }

    public function importBankAccountStatement(Request $request, CompanyAccount $account): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'statement_pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'statement_month' => ['required', 'date_format:Y-m'],
            'pdf_password' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $text = $this->extractStatementTextFromPdf(
                $request->file('statement_pdf')->getRealPath(),
                $validated['pdf_password'] ?? null,
            );
            $rows = $this->parseBankStatementRows($text, $validated['statement_month']);
        } catch (Throwable $exception) {
            return redirect()
                ->route('admin.cashbook.bank-accounts.statement', ['account' => $account, 'month' => $validated['statement_month']])
                ->withErrors(['statement_pdf' => $exception->getMessage()]);
        }

        if ($rows === []) {
            return redirect()
                ->route('admin.cashbook.bank-accounts.statement', ['account' => $account, 'month' => $validated['statement_month']])
                ->withErrors(['statement_pdf' => 'No statement rows found for the selected month.']);
        }

        $batch = 'pdf:'.$account->id.':'.$validated['statement_month'].':'.now()->format('YmdHis');
        $fileName = $request->file('statement_pdf')->getClientOriginalName();
        $imported = 0;
        $skipped = 0;
        $flagged = 0;

        foreach ($rows as $row) {
            $fingerprint = $this->statementImportFingerprint($account, $row);

            $exactDuplicate = CompanyAccountStatementEntry::query()
                ->where('company_account_id', $account->id)
                ->where('import_fingerprint', $fingerprint)
                ->exists();

            if ($exactDuplicate) {
                $skipped++;

                continue;
            }

            $possibleDuplicate = CompanyAccountStatementEntry::query()
                ->where('company_account_id', $account->id)
                ->whereDate('transaction_date', $row['transaction_date'])
                ->where('direction', $row['direction'])
                ->where('amount', $row['amount'])
                ->where('status', '!=', 'duplicate_flagged')
                ->oldest('id')
                ->first();

            if ($possibleDuplicate instanceof CompanyAccountStatementEntry) {
                if ($possibleDuplicate->source === 'vendor_settlement'
                    && filled($possibleDuplicate->reference)
                    && $possibleDuplicate->reference === $row['reference']) {
                    $possibleDuplicate->update([
                        'import_fingerprint' => $fingerprint,
                        'imported_month' => $validated['statement_month'],
                        'import_file_name' => $fileName,
                        'statement_batch' => $batch,
                        'duplicate_status' => 'clear',
                        'notes' => trim(($possibleDuplicate->notes ? $possibleDuplicate->notes.' ' : '').'Matched to imported bank statement by reference.'),
                    ]);
                    $skipped++;

                    continue;
                }

                CompanyAccountStatementEntry::query()->create([
                    'company_account_id' => $account->id,
                    'transaction_date' => $row['transaction_date'],
                    'value_date' => $row['transaction_date'],
                    'direction' => $row['direction'],
                    'amount' => $row['amount'],
                    'reference' => $row['reference'],
                    'narration' => $row['narration'],
                    'source' => 'pdf_import',
                    'status' => 'duplicate_flagged',
                    'matched_amount' => 0,
                    'statement_batch' => $batch,
                    'import_fingerprint' => $fingerprint,
                    'imported_month' => $validated['statement_month'],
                    'import_file_name' => $fileName,
                    'duplicate_status' => 'possible_duplicate',
                    'duplicate_of_statement_entry_id' => $possibleDuplicate->id,
                    'notes' => 'Possible duplicate from PDF import. Balance not applied until admin clears this flag.',
                    'imported_by' => (int) $request->user()->id,
                ]);
                $flagged++;

                continue;
            }

            $entry = $this->companyPaymentReconciliationService->createStatementEntry([
                'company_account_id' => $account->id,
                'transaction_date' => $row['transaction_date'],
                'value_date' => $row['transaction_date'],
                'direction' => $row['direction'],
                'amount' => $row['amount'],
                'reference' => $row['reference'],
                'narration' => $row['narration'],
                'source' => 'pdf_import',
                'statement_batch' => $batch,
                'notes' => 'Imported from bank PDF statement.',
            ], (int) $request->user()->id);

            $entry->update([
                'import_fingerprint' => $fingerprint,
                'imported_month' => $validated['statement_month'],
                'import_file_name' => $fileName,
                'duplicate_status' => 'clear',
            ]);
            $imported++;
        }

        return redirect()
            ->route('admin.cashbook.bank-accounts.statement', ['account' => $account, 'month' => $validated['statement_month']])
            ->with('success', "Statement import finished. New: {$imported}, duplicates skipped: {$skipped}, flagged: {$flagged}.");
    }

    public function clearStatementDuplicateFlag(Request $request, CompanyAccount $account, string $statementRef): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $entry = $this->resolveSecureStatementEntry($statementRef);
        abort_unless((int) $entry->company_account_id === (int) $account->id, 404);

        DB::transaction(function () use ($entry, $account, $request): void {
            $lockedEntry = CompanyAccountStatementEntry::query()
                ->whereKey($entry->id)
                ->where('company_account_id', $account->id)
                ->where('status', 'duplicate_flagged')
                ->lockForUpdate()
                ->firstOrFail();

            $lockedAccount = CompanyAccount::query()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->firstOrFail();

            $balanceChange = $lockedEntry->direction === 'out'
                ? -round((float) $lockedEntry->amount, 2)
                : round((float) $lockedEntry->amount, 2);

            $lockedAccount->increment('current_balance', $balanceChange);
            $lockedEntry->update([
                'status' => 'unmatched',
                'duplicate_status' => 'manual_cleared',
                'duplicate_of_statement_entry_id' => null,
                'notes' => trim(((string) $lockedEntry->notes)."\nDuplicate flag cleared by admin on ".now()->format('Y-m-d H:i:s').'.'),
                'imported_by' => $lockedEntry->imported_by ?: (int) $request->user()->id,
            ]);
        });

        return redirect()->back()
            ->with('success', 'Duplicate flag cleared and statement balance applied.');
    }

    public function verifyPendingStatement(Request $request, CompanyAccount $account, string $statementRef): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $entry = $this->resolveSecureStatementEntry($statementRef);
        abort_unless((int) $entry->company_account_id === (int) $account->id, 404);

        try {
            $this->companyPaymentReconciliationService->verifyPendingShopCollection($entry, (int) $request->user()->id);

            return redirect()->back()
                ->with('success', 'Shop collection verified, company account updated, and shop payable reduced.');
        } catch (Throwable $e) {
            return redirect()->back()
                ->with('error', 'Verification failed: '.$e->getMessage());
        }
    }

    public function verifyShopCollectionStatement(Request $request, CompanyAccountStatementEntry $statement): JsonResponse
    {
        $this->ensureMainAdmin($request);

        try {
            $verifiedEntry = $this->companyPaymentReconciliationService->verifyPendingShopCollection($statement, (int) $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Shop collection verified and reconciled.',
                'statement' => $verifiedEntry,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function reconcileCompanyPayment(Request $request, ShopInvoicePaymentRequest $paymentRequest): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'company_account_id' => ['required', 'integer', 'exists:cashbook_company_accounts,id'],
            'statement_entry_id' => ['nullable', 'integer', 'exists:cashbook_company_account_statement_entries,id'],
            'statement_amount' => ['nullable', 'numeric', 'min:0'],
            'cleared_amount' => ['required', 'numeric', 'min:0.01'],
            'difference_amount' => ['nullable', 'numeric', 'min:0'],
            'difference_action' => ['required', 'in:none,keep_floating,shop_expense,shop_income'],
            'difference_entry_type_id' => ['nullable', 'integer', 'exists:ledger_entry_types,id'],
            'business_date' => ['nullable', 'date_format:Y-m-d'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->companyPaymentReconciliationService->reconcilePayment(
            $paymentRequest,
            $validated,
            (int) $request->user()->id,
        );

        return redirect()->route('admin.cashbook.finance')
            ->with('success', 'Payment reconciled and finance balances updated.');
    }

    public function storeBankAccount(Request $request): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'account_type' => 'required|in:bank,cash,wallet',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'opening_balance' => 'nullable|numeric|min:0',
            'is_default' => 'nullable|boolean',
        ]);

        $openingBalance = (float) ($validated['opening_balance'] ?? 0);
        $isDefault = ! empty($validated['is_default']);

        if ($isDefault) {
            CompanyAccount::query()->update(['is_default' => false]);
        }

        CompanyAccount::create([
            'name' => $validated['name'],
            'account_type' => $validated['account_type'],
            'bank_name' => $validated['bank_name'] ?? null,
            'account_number' => $validated['account_number'] ?? null,
            'opening_balance' => $openingBalance,
            'current_balance' => $openingBalance,
            'is_default' => $isDefault,
            'enabled' => true,
        ]);

        return redirect()->route('admin.cashbook.bank-accounts.create')
            ->with('success', 'Bank account created successfully.');
    }

    public function updateBankAccount(Request $request, int $account): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $bankAcc = CompanyAccount::findOrFail($account);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'account_type' => 'required|in:bank,cash,wallet',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'opening_balance' => 'nullable|numeric|min:0',
            'is_default' => 'nullable|boolean',
        ]);

        $openingBalance = (float) ($validated['opening_balance'] ?? $bankAcc->opening_balance);
        $isDefault = ! empty($validated['is_default']);

        if ($isDefault) {
            CompanyAccount::query()->where('id', '!=', $bankAcc->id)->update(['is_default' => false]);
        }

        // Adjust current balance by the difference in opening balance
        $balanceDiff = $openingBalance - (float) $bankAcc->opening_balance;

        $bankAcc->update([
            'name' => $validated['name'],
            'account_type' => $validated['account_type'],
            'bank_name' => $validated['bank_name'] ?? null,
            'account_number' => $validated['account_number'] ?? null,
            'opening_balance' => $openingBalance,
            'current_balance' => (float) $bankAcc->current_balance + $balanceDiff,
            'is_default' => $isDefault,
        ]);

        return redirect()->route('admin.cashbook.bank-accounts.create')
            ->with('success', "Bank account '{$bankAcc->name}' updated successfully.");
    }

    public function deleteBankAccount(Request $request, int $account): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $bankAcc = CompanyAccount::findOrFail($account);

        // Check if transactions exist
        if ($bankAcc->transactions()->count() > 0) {
            return redirect()->route('admin.cashbook.bank-accounts.create')
                ->with('error', "Cannot delete '{$bankAcc->name}' because it has linked transactions.");
        }

        $name = $bankAcc->name;
        $bankAcc->delete();

        return redirect()->route('admin.cashbook.bank-accounts.create')
            ->with('success', "Bank account '{$name}' deleted successfully.");
    }

    // ─── API methods ─────────────────────────────────────────────────────────

    /**
     * API: Get snapshot and transaction log for a shop & date (supports optional pagination).
     */
    public function getShopData(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'shop_id' => ['nullable'],
            'business_date' => ['nullable', 'date_format:Y-m-d'],
            'timeframe' => ['nullable', 'in:daily,weekly,monthly,custom'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $rawShopParam = $validated['shop_id'] ?? $request->input('shop_id') ?? 1;
        $resolvedProfile = $this->resolveShop($rawShopParam);
        $shopId = (int) $resolvedProfile->shop_id;
        $date = $validated['business_date'] ?? today()->toDateString();
        $timeframe = $validated['timeframe'] ?? 'daily';
        $perPage = (int) ($validated['per_page'] ?? 50);
        $month = substr($date, 0, 7);

        [$finalStart, $finalEnd] = $this->cashbookRange(
            $date,
            $timeframe,
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );
        $rangeEnd = Carbon::parse($finalEnd);
        $startOfWeek = $rangeEnd->copy()->startOfWeek()->format('Y-m-d');
        $endOfWeek = $rangeEnd->copy()->endOfWeek()->min(today())->format('Y-m-d');

        $rangeTransactions = ShopLedgerTransaction::with('entryType')
            ->withSum('paymentLedgerAllocations as reconciled_amount', 'amount')
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$finalStart, $finalEnd])
            ->orderBy('business_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $totalSales = (float) $rangeTransactions
            ->filter(fn ($t) => $t->direction === 'income' || ($t->entryType && $t->entryType->category === 'income'))
            ->sum('amount');

        $totalExpense = (float) $rangeTransactions
            ->filter(fn ($t) => $t->direction === 'expense' || ($t->entryType && $t->entryType->category === 'expense'))
            ->sum('amount');

        $netPl = $totalSales - $totalExpense;

        $dailySnapshot = $this->ledgerService->dailySummary($shopId, $finalEnd);

        $collectionSummaries = $this->collectionGroupPostingService->summaries($rangeTransactions);

        $snapshotData = [
            'total_sales' => $totalSales,
            'total_expense' => $totalExpense,
            'net_pl' => $netPl,
            'collection_net' => round((float) collect($collectionSummaries)->sum('net'), 2),
            'closing_petty' => (float) $dailySnapshot->closing_petty,
            'closing_shop_position' => (float) $dailySnapshot->closing_shop_position,
            'closing_company_pending' => (float) $dailySnapshot->closing_company_pending,
            'status' => $dailySnapshot->status,
            'closed_at' => $dailySnapshot->closed_at,
        ];

        $transactions = $request->has('page')
            ? ShopLedgerTransaction::with('entryType')
                ->withSum('paymentLedgerAllocations as reconciled_amount', 'amount')
                ->where('shop_id', $shopId)
                ->whereBetween('business_date', [$finalStart, $finalEnd])
                ->orderBy('business_date', 'desc')
                ->orderBy('id', 'desc')
                ->paginate($perPage)
            : $rangeTransactions;

        $monthTransactions = ShopLedgerTransaction::with('entryType')
            ->where('shop_id', $shopId)
            ->where('business_date', 'like', $month.'%')
            ->orderBy('business_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $pettyEntries = ShopLedgerTransaction::with('entryType')
            ->where('shop_id', $shopId)
            ->where(fn ($q) => $q->where('petty_delta', '!=', 0)->orWhere('funding_source', 'petty'))
            ->orderBy('id', 'desc')
            ->get();

        $settings = ShopLedgerEntrySetting::with('entryType')
            ->where('shop_id', $shopId)
            ->where('enabled', true)
            ->get();

        $payableEntryTypeIds = $settings
            ->where('include_in_payable', true)
            ->pluck('entry_type_id');

        $companyPendingEntries = ShopLedgerTransaction::with('entryType')
            ->where('shop_id', $shopId)
            ->where(function ($q) use ($payableEntryTypeIds) {
                $q->where('company_pending_delta', '!=', 0)
                    ->orWhere('funding_source', 'company')
                    ->when($payableEntryTypeIds->isNotEmpty(), fn ($sub) => $sub->orWhereIn('entry_type_id', $payableEntryTypeIds->all()))
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

        $payableRows = $rangeTransactions
            ->filter(function ($tx) use ($payableEntryTypeIds) {
                return $payableEntryTypeIds->contains($tx->entry_type_id)
                    || $tx->reference_type === 'collection_group';
            })
            ->values();

        $settlementTransactions = $rangeTransactions->filter(function ($tx) {
            return ($tx->entryType && $tx->entryType->category === 'settlement')
                || $tx->entry_type_code === 'shop_paid_company';
        });
        $payableReceivedTotal = round((float) $settlementTransactions->sum('amount'), 2);

        $payableByCategory = $payableRows
            ->groupBy(fn ($tx) => $tx->entryType?->name ?: $tx->entry_type_code)
            ->map(function ($group, $name) use ($settlementTransactions, $settings) {
                $first = $group->first();
                $code = (string) ($first->entryType?->code ?: $first->entry_type_code);
                $recordedAmount = round((float) $group->sum(function ($tx) use ($settings) {
                    $code = (string) ($tx->entryType?->code ?: $tx->entry_type_code);
                    $direction = (string) ($tx->direction ?: ($tx->entryType?->category ?: 'income'));
                    $category = (string) ($tx->entryType?->category ?: $direction);
                    $setting = $settings->firstWhere('entry_type_id', $tx->entry_type_id);
                    $payableDir = $setting?->payable_direction;
                    $isDeduction = $payableDir ? ($payableDir === 'minus') : ($direction === 'expense' || $category === 'expense' || in_array($code, ['company_to_petty', 'company_paid_shop', 'company_paid_vendor'], true));

                    return $isDeduction ? -(float) $tx->amount : (float) $tx->amount;
                }), 2);

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
                    'amount' => $recordedAmount,
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

        $payableTotal = round((float) $payableRows->sum(function ($tx) use ($settings) {
            $code = (string) ($tx->entryType?->code ?: $tx->entry_type_code);
            $direction = (string) ($tx->direction ?: ($tx->entryType?->category ?: 'income'));
            $category = (string) ($tx->entryType?->category ?: $direction);
            $setting = $settings->firstWhere('entry_type_id', $tx->entry_type_id);
            $payableDir = $setting?->payable_direction;
            $isDeduction = $payableDir ? ($payableDir === 'minus') : ($direction === 'expense' || $category === 'expense' || in_array($code, ['company_to_petty', 'company_paid_shop', 'company_paid_vendor'], true));

            return $isDeduction ? -(float) $tx->amount : (float) $tx->amount;
        }), 2);
        $totalReceivedAllocated = (float) $payableByCategory->sum('received_amount');
        $effectiveReceived = max($payableReceivedTotal, $totalReceivedAllocated);
        $payableBalance = max(0, round($payableTotal - $effectiveReceived, 2));

        $collectionGroups = $this->collectionGroupPostingService->groupsForShop($shopId);

        return response()->json([
            'success' => true,
            'timeframe' => $timeframe,
            'start_date' => $finalStart,
            'end_date' => $finalEnd,
            'start_of_week' => $startOfWeek,
            'end_of_week' => $endOfWeek,
            'snapshot' => $snapshotData,
            'transactions' => $transactions,
            'month_transactions' => $monthTransactions,
            'petty_entries' => $pettyEntries,
            'company_pending_entries' => $companyPendingEntries,
            'payable_rows' => $payableRows,
            'payable_total' => $payableTotal,
            'payable_received_total' => $effectiveReceived,
            'payable_balance' => $payableBalance,
            'payable_by_category' => $payableByCategory,
            'settings' => $settings,
            'collection_groups' => $collectionGroups,
            'collection_summaries' => $collectionSummaries,
            'operational_settlement' => $this->moneyPositionService->getShopDaySettlementOperationalSummary($shopId, $finalEnd),
        ]);
    }

    /**
     * API: Operational settlement breakdown for a single shop and date.
     */
    public function getShopSettlementSummary(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'shop_id' => ['required'],
            'business_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $resolvedProfile = $this->resolveShop($validated['shop_id']);
        $shopId = (int) $resolvedProfile->shop_id;
        $date = $validated['business_date'] ?? today()->toDateString();

        $summary = $this->moneyPositionService->getShopDaySettlementOperationalSummary($shopId, $date);

        return response()->json([
            'success' => true,
            'summary' => $summary,
        ]);
    }

    /**
     * API: Today's numbers for every shop in one view.
     */
    public function getAllShopsOverview(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'business_date' => ['nullable', 'date_format:Y-m-d'],
            'timeframe' => ['nullable', 'in:daily,weekly,monthly,custom'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
        $defaultDate = today()->toDateString();
        $businessDate = $validated['business_date'] ?? $defaultDate;
        $timeframe = $validated['timeframe'] ?? 'daily';
        [$startDate, $endDate] = $this->cashbookRange(
            $businessDate,
            $timeframe,
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $shops->load('client');
        $directShopIds = $shops
            ->filter(fn (ShopLedgerProfile $shop): bool => $shop->client_id === null && $shop->profile_template === 'direct_buyer')
            ->pluck('shop_id')
            ->map(fn ($shopId): int => (int) $shopId)
            ->all();
        $directInvoiceTotals = ShopInvoice::query()
            ->whereIn('shop_id', $directShopIds)
            ->where('final_total', '>', 0)
            ->where('status', '!=', 'cancelled')
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->selectRaw('shop_id, COALESCE(SUM(final_total), 0) as invoice_total')
            ->groupBy('shop_id')
            ->pluck('invoice_total', 'shop_id');
        $periodTotals = ShopLedgerTransaction::query()
            ->whereBetween('business_date', [$startDate, $endDate])
            ->selectRaw("
                shop_id,
                COALESCE(SUM(CASE WHEN direction = 'income' THEN amount ELSE 0 END), 0) as total_sales,
                COALESCE(SUM(CASE WHEN direction = 'expense' THEN amount ELSE 0 END), 0) as total_expense
            ")
            ->groupBy('shop_id')
            ->get()
            ->keyBy('shop_id');

        $overview = [];
        $totals = [
            'total_sales' => 0,
            'total_expense' => 0,
            'net_pl' => 0,
            'closing_petty' => 0,
            'closing_shop_position' => 0,
            'closing_company_pending' => 0,
            'total_green_leaf_bills' => 0,
            'total_received_today' => 0,
        ];

        foreach ($shops as $shop) {
            $snapshot = $this->ledgerService->dailySummary($shop->shop_id, $endDate);
            $periodTotal = $periodTotals->get($shop->shop_id);
            $periodSales = (float) ($periodTotal->total_sales ?? 0);
            $periodExpense = (float) ($periodTotal->total_expense ?? 0);
            $periodNetPl = $periodSales - $periodExpense;

            $snapshot->total_sales = $periodSales;
            $snapshot->total_expense = $periodExpense;
            $snapshot->net_pl = $periodNetPl;

            $isDirect = $shop->client_id === null
                && $shop->profile_template === 'direct_buyer';

            $glBills = $isDirect
                ? (float) ($directInvoiceTotals->get($shop->shop_id) ?? 0)
                : (float) ShopLedgerTransaction::where('shop_id', $shop->shop_id)
                    ->whereBetween('business_date', [$startDate, $endDate])
                    ->where('entry_type_id', fn ($q) => $q->select('id')->from('ledger_entry_types')->where('code', 'purchase_bill'))
                    ->sum('amount');

            $compExpenses = (float) ShopLedgerTransaction::where('shop_id', $shop->shop_id)
                ->whereBetween('business_date', [$startDate, $endDate])
                ->where('funding_source', 'company')
                ->where('entry_type_id', '!=', fn ($q) => $q->select('id')->from('ledger_entry_types')->where('code', 'purchase_bill'))
                ->sum('amount');

            $receivedToday = (float) ShopLedgerTransaction::where('shop_id', $shop->shop_id)
                ->whereBetween('business_date', [$startDate, $endDate])
                ->where('entry_type_id', fn ($q) => $q->select('id')->from('ledger_entry_types')->where('code', 'shop_paid_company'))
                ->sum('amount');

            $shopPos = (float) $snapshot->closing_shop_position;
            $compPend = (float) $snapshot->closing_company_pending;

            $netReceivable = $glBills + $compPend - $receivedToday;

            $overview[] = [
                'shop' => $shop,
                'is_direct' => $isDirect,
                'snapshot' => $snapshot,
                'green_leaf_bill' => $glBills,
                'company_paid_expenses' => $compExpenses,
                'received_today' => $receivedToday,
                'net_receivable' => $netReceivable,
            ];

            $totals['total_sales'] += $periodSales;
            $totals['total_expense'] += $periodExpense;
            $totals['net_pl'] += $periodNetPl;
            $totals['closing_petty'] += (float) $snapshot->closing_petty;
            $totals['closing_shop_position'] += (float) $shopPos;
            $totals['closing_company_pending'] += (float) $compPend;
            $totals['total_green_leaf_bills'] += $glBills;
            $totals['total_received_today'] += $receivedToday;
        }

        $totals['net_payable_to_client'] = $totals['closing_shop_position']
            - ($totals['total_green_leaf_bills'] + $totals['closing_company_pending']);

        $overviewCollection = collect($overview);
        $clientGroups = $overviewCollection
            ->filter(fn (array $item): bool => $item['shop']->client_id !== null)
            ->groupBy(fn (array $item): int => (int) $item['shop']->client_id)
            ->map(fn ($items): array => [
                'client' => $items->first()['shop']->client,
                'shops' => $items->values(),
            ])
            ->values();
        $directOwnedShops = $overviewCollection
            ->filter(fn (array $item): bool => $item['is_direct'])
            ->values();

        return response()->json([
            'success' => true,
            'company_name' => config('greenleaf.name', 'Green Leaf'),
            'timeframe' => $timeframe,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'overview' => $overview,
            'client_groups' => $clientGroups,
            'direct_owned_shops' => $directOwnedShops,
            'totals' => $totals,
        ]);
    }

    /**
     * API: Company Payables & Company Pendings lists.
     */
    public function getPayablesAndPendings(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $date = $request->input('business_date', today()->toDateString());
        $shops = $this->shopSyncService->syncAndGetProfiles();

        $payables = [];
        $pendings = [];

        foreach ($shops as $shop) {
            $snapshot = $this->ledgerService->dailySummary($shop->shop_id, $date);

            $shopPos = (float) $snapshot->closing_shop_position;
            if ($shopPos > 0) {
                $payables[] = ['shop' => $shop, 'amount' => $shopPos, 'snapshot' => $snapshot];
            }

            $compPending = (float) $snapshot->closing_company_pending;
            if ($compPending > 0) {
                $pendings[] = ['shop' => $shop, 'amount' => $compPending, 'snapshot' => $snapshot];
            }
        }

        usort($payables, fn ($a, $b) => $b['amount'] <=> $a['amount']);
        usort($pendings, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return response()->json([
            'success' => true,
            'date' => $date,
            'payables' => $payables,
            'pendings' => $pendings,
        ]);
    }

    /**
     * API: Post a new transaction entry (validated via RecordEntryRequest).
     */
    public function recordEntry(RecordEntryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            if (! empty($validated['collection_group_id'])) {
                $result = $this->collectionGroupPostingService->record(
                    (int) $validated['shop_id'],
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
                    'message' => 'Collection recorded successfully.',
                    'transactions' => $result['transactions'],
                    'snapshot' => $result['snapshot'],
                ]);
            }

            $input = [
                'shop_id' => (int) $validated['shop_id'],
                'business_date' => $validated['business_date'],
                'entry_type_code' => $validated['entry_type_code'],
                'amount' => (float) $validated['amount'],
                'entered_by' => $request->user()?->id ?? 1,
                'notes' => $validated['notes'] ?? null,
            ];

            if (! empty($validated['funding_source']) && $validated['funding_source'] !== 'none') {
                $input['funding_source'] = $validated['funding_source'];
            }

            $result = $this->dailyLedgerService->recordEntry($input);
            $transaction = $result['transaction']->load('entryType');

            return response()->json([
                'success' => true,
                'message' => 'Transaction recorded successfully.',
                'transaction' => $transaction,
                'snapshot' => $result['snapshot'],
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function bulkRecordEntries(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'business_date' => ['required', 'date_format:Y-m-d'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.entry_type_code' => ['required', 'string', 'exists:ledger_entry_types,code'],
            'entries.*.amount' => ['required', 'numeric', 'min:0.01'],
            'entries.*.funding_source' => ['nullable', 'string', 'in:sales,petty,company,bank,external,company_later,none'],
            'entries.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $created = [];
        $userId = (int) ($request->user()?->id ?? 1);
        $shopId = (int) $validated['shop_id'];

        foreach ($validated['entries'] as $item) {
            $code = (string) $item['entry_type_code'];
            if (in_array($code, ['gl_bill', 'purchase_bill'], true)) {
                continue;
            }

            $payload = [
                'shop_id' => $shopId,
                'business_date' => $validated['business_date'],
                'entry_type_code' => $code,
                'amount' => (float) $item['amount'],
                'entered_by' => $userId,
                'notes' => $item['notes'] ?? null,
            ];

            if (! empty($item['funding_source']) && $item['funding_source'] !== 'none') {
                $payload['funding_source'] = $item['funding_source'];
            }

            $result = $this->dailyLedgerService->recordEntry($payload);
            if (! empty($result['transaction'])) {
                $created[] = $result['transaction'];
            }
        }

        $snapshot = $this->dailyLedgerService->dailySummary($shopId, $validated['business_date']);

        return response()->json([
            'success' => true,
            'message' => count($created).' entries created successfully.',
            'count' => count($created),
            'snapshot' => $snapshot,
        ]);
    }

    /**
     * Legacy Shop Payment endpoint retained only to provide a safe deprecation response.
     */
    public function acceptPayment(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        return response()->json([
            'success' => false,
            'message' => 'This legacy endpoint is retired. Open the selected shop and use Accept Payment.',
        ], 410);
    }

    /**
     * API: Company reimburses / pays a shop (validated via PayShopRequest).
     */
    public function payShop(FundShopPettyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $shop = Shop::query()->where('public_uuid', $validated['shop_uuid'])->firstOrFail();
            $companyAccount = CompanyAccount::query()
                ->where('public_uuid', $validated['company_account_uuid'])
                ->where('enabled', true)
                ->firstOrFail();
            $statement = $this->shopPettyFundingService->fund($shop, [
                'business_date' => $validated['business_date'],
                'amount' => (float) $validated['amount'],
                'company_account' => $companyAccount,
                'request_uuid' => $validated['request_uuid'],
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ], (int) $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => '₹'.number_format($validated['amount'], 2).' petty funding recorded for '.$shop->name.'.',
                'movement_uuid' => $statement->public_uuid,
                'reconciliation_status' => $statement->status,
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * API: Add a new shop with template-copy flow (validated via AddShopRequest).
     */
    public function addShop(AddShopRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $ownershipType = $validated['ownership_type'] ?? ($validated['client_id'] ? 'client' : 'direct');
            $ledgerClient = $ownershipType === 'client'
                ? LedgerClient::query()->findOrFail((int) $validated['client_id'])
                : null;
            abort_if($ledgerClient && $ledgerClient->erp_client_id === null, 422, 'The selected cashbook client is not linked to an ERP client.');
            $erpClientId = $ledgerClient?->erp_client_id;
            $template = $validated['profile_template'] ?? ($ownershipType === 'direct' ? 'direct_buyer' : 'owned_standard');

            // Create or update underlying ERP Shop record
            Shop::updateOrCreate(
                ['id' => (int) $validated['shop_id']],
                [
                    'code' => $validated['code'],
                    'name' => $validated['name'],
                    'accounting_mode' => 'owned',
                    'accounting_enabled' => true,
                    'client_id' => $erpClientId,
                ]
            );

            $shopProfile = ShopLedgerProfile::create([
                'shop_id' => (int) $validated['shop_id'],
                'code' => $validated['code'],
                'name' => $validated['name'],
                'profile_template' => $template,
                'client_id' => $ledgerClient?->id,
                'enabled' => true,
                'closing_mode' => 'manual',
            ]);

            $sourceShopId = $validated['copy_from_shop_id'] ?? 1;
            $sourceSettings = ShopLedgerEntrySetting::where('shop_id', $sourceShopId)->get();

            foreach ($sourceSettings as $setting) {
                ShopLedgerEntrySetting::create([
                    'shop_id' => $shopProfile->shop_id,
                    'entry_type_id' => $setting->entry_type_id,
                    'version' => 1,
                    'effective_from' => today()->toDateString(),
                    'effective_to' => null,
                    'enabled' => $setting->enabled,
                    'default_funding_source' => $setting->default_funding_source,
                    'allowed_funding_sources' => $setting->allowed_funding_sources,
                    'include_in_sales' => $setting->include_in_sales,
                    'include_in_income' => $setting->include_in_income,
                    'include_in_expense' => $setting->include_in_expense,
                    'include_in_pl' => $setting->include_in_pl,
                    'generates_secondary_entry' => $setting->generates_secondary_entry,
                    'secondary_entry_type_id' => $setting->secondary_entry_type_id,
                    'secondary_amount_mode' => $setting->secondary_amount_mode,
                    'secondary_amount_value' => $setting->secondary_amount_value,
                    'petty_behavior' => $setting->petty_behavior,
                    'settlement_behavior' => $setting->settlement_behavior,
                    'company_pending_behavior' => $setting->company_pending_behavior,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Shop '{$validated['name']}' (#{$validated['shop_id']}) created and configured from template.",
                'shop' => $shopProfile,
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * API: Update a shop's entry-type rule configuration (validated via UpdateRuleRequest).
     */
    public function updateRule(UpdateRuleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $setting = ShopLedgerEntrySetting::findOrFail($validated['setting_id']);
            $setting->update([
                'default_funding_source' => $validated['default_funding_source'],
                'include_in_sales' => (bool) $validated['include_in_sales'],
                'include_in_expense' => (bool) $validated['include_in_expense'],
                'include_in_pl' => (bool) $validated['include_in_pl'],
                'generates_secondary_entry' => (bool) $validated['generates_secondary'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Shop rule configuration updated.',
                'setting' => $setting->load('entryType'),
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function updateShopSetting(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'setting_id' => ['required', 'integer', 'exists:shop_ledger_entry_settings,id'],
            'company_account_id' => ['nullable', 'integer', 'exists:cashbook_company_accounts,id'],
            'enabled' => ['required', 'boolean'],
            'default_funding_source' => ['required', 'string', 'in:none,sales,petty,company,company_later,bank'],
            'include_in_sales' => ['required', 'boolean'],
            'include_in_income' => ['required', 'boolean'],
            'include_in_expense' => ['required', 'boolean'],
            'include_in_pl' => ['required', 'boolean'],
            'include_in_payable' => ['required', 'boolean'],
            'payable_direction' => ['nullable', 'string', 'in:add,minus'],
            'settlement_behavior' => ['nullable', 'string', 'in:none,increase,decrease'],
            'petty_behavior' => ['nullable', 'string', 'in:none,increase,decrease'],
            'company_pending_behavior' => ['nullable', 'string', 'in:none,increase,decrease'],
            'generates_secondary_entry' => ['required', 'boolean'],
            'secondary_entry_type_id' => ['nullable', 'integer', 'exists:ledger_entry_types,id'],
            'secondary_amount_mode' => ['required', 'string', 'in:same_amount,percentage'],
            'secondary_amount_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $setting = ShopLedgerEntrySetting::query()->findOrFail((int) $validated['setting_id']);
            $createsChild = (bool) $validated['generates_secondary_entry'];
            $setting->update([
                'enabled' => (bool) $validated['enabled'],
                'company_account_id' => ! empty($validated['company_account_id']) ? (int) $validated['company_account_id'] : null,
                'default_funding_source' => $validated['default_funding_source'],
                'include_in_sales' => (bool) $validated['include_in_sales'],
                'include_in_income' => (bool) $validated['include_in_income'],
                'include_in_expense' => (bool) $validated['include_in_expense'],
                'include_in_pl' => (bool) $validated['include_in_pl'],
                'include_in_payable' => (bool) $validated['include_in_payable'],
                'payable_direction' => $validated['payable_direction'] ?? null,
                'settlement_behavior' => $validated['settlement_behavior'] ?? 'none',
                'petty_behavior' => $validated['petty_behavior'] ?? 'none',
                'company_pending_behavior' => $validated['company_pending_behavior'] ?? 'none',
                'generates_secondary_entry' => $createsChild,
                'secondary_entry_type_id' => $createsChild ? ($validated['secondary_entry_type_id'] ?? null) : null,
                'secondary_amount_mode' => $validated['secondary_amount_mode'],
                'secondary_amount_value' => $validated['secondary_amount_mode'] === 'percentage'
                    ? ($validated['secondary_amount_value'] ?? null)
                    : null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Shop setting saved.',
                'setting' => $setting->fresh('entryType'),
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function previewHistoricalBankCollections(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'entry_type_id' => ['required', 'integer', 'exists:ledger_entry_types,id'],
            'company_account_id' => ['required', 'integer', 'exists:cashbook_company_accounts,id'],
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ]);

        try {
            $preview = $this->historicalBankCollectionFetchService->preview(
                (int) $validated['shop_id'],
                (int) $validated['entry_type_id'],
                (int) $validated['company_account_id'],
                $validated['from_date'],
                $validated['to_date']
            );

            return response()->json([
                'success' => true,
                'preview' => $preview,
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function fetchHistoricalBankCollections(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'entry_type_id' => ['required', 'integer', 'exists:ledger_entry_types,id'],
            'company_account_id' => ['required', 'integer', 'exists:cashbook_company_accounts,id'],
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ]);

        try {
            $result = $this->historicalBankCollectionFetchService->fetch(
                (int) $validated['shop_id'],
                (int) $validated['entry_type_id'],
                (int) $validated['company_account_id'],
                $validated['from_date'],
                $validated['to_date'],
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => "Successfully fetched {$result['updated_count']} historical collections to {$result['company_account']['name']}.",
                'result' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function saveShopBankAdjustmentRule(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'id' => ['nullable', 'integer', 'exists:shop_bank_settlement_adjustment_rules,id'],
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'entry_type_id' => ['required', 'integer', 'exists:ledger_entry_types,id'],
            'label' => ['required', 'string', 'max:120'],
            'direction' => ['required', 'string', 'in:plus,minus'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        try {
            $shopId = (int) $validated['shop_id'];
            $entryTypeId = (int) $validated['entry_type_id'];

            if (! empty($validated['id'])) {
                $rule = ShopBankSettlementAdjustmentRule::query()
                    ->where('id', (int) $validated['id'])
                    ->where('shop_id', $shopId)
                    ->where('entry_type_id', $entryTypeId)
                    ->firstOrFail();

                $rule->update([
                    'label' => $validated['label'],
                    'direction' => $validated['direction'],
                    'enabled' => (bool) ($validated['enabled'] ?? true),
                ]);
            } else {
                $rule = ShopBankSettlementAdjustmentRule::create([
                    'shop_id' => $shopId,
                    'entry_type_id' => $entryTypeId,
                    'label' => $validated['label'],
                    'direction' => $validated['direction'],
                    'enabled' => (bool) ($validated['enabled'] ?? true),
                    'created_by' => $request->user()?->id,
                ]);
            }

            $rules = ShopBankSettlementAdjustmentRule::query()
                ->where('shop_id', $shopId)
                ->where('entry_type_id', $entryTypeId)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Bank settlement adjustment rule saved.',
                'rule' => $rule,
                'rules' => $rules,
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function deleteShopBankAdjustmentRule(Request $request, int $rule): JsonResponse
    {
        $this->ensureMainAdmin($request);

        try {
            $ruleModel = ShopBankSettlementAdjustmentRule::findOrFail($rule);
            $shopId = $ruleModel->shop_id;
            $entryTypeId = $ruleModel->entry_type_id;
            $ruleModel->delete();

            $rules = ShopBankSettlementAdjustmentRule::query()
                ->where('shop_id', $shopId)
                ->where('entry_type_id', $entryTypeId)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Bank settlement adjustment rule deleted.',
                'rules' => $rules,
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function saveShopDailyBankAdjustments(Request $request, int|string $shop): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $currentShop = $this->resolveShop($shop);

        $validated = $request->validate([
            'business_date' => ['required', 'date_format:Y-m-d'],
            'entry_type_id' => ['required', 'integer', 'exists:ledger_entry_types,id'],
            'adjustments' => ['present', 'array'],
            'adjustments.*.rule_id' => ['required', 'integer', 'exists:shop_bank_settlement_adjustment_rules,id'],
            'adjustments.*.amount' => ['required', 'numeric', 'min:0'],
            'adjustments.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $businessDate = $validated['business_date'];
            $entryTypeId = (int) $validated['entry_type_id'];

            foreach ($validated['adjustments'] as $item) {
                $rule = ShopBankSettlementAdjustmentRule::query()
                    ->where('id', (int) $item['rule_id'])
                    ->where('shop_id', $currentShop->shop_id)
                    ->where('entry_type_id', $entryTypeId)
                    ->firstOrFail();

                $amount = max(0.0, round((float) $item['amount'], 2));

                ShopBankSettlementAdjustment::updateOrCreate(
                    [
                        'shop_id' => $currentShop->shop_id,
                        'business_date' => $businessDate,
                        'entry_type_id' => $entryTypeId,
                        'rule_id' => $rule->id,
                    ],
                    [
                        'label' => $rule->label,
                        'direction' => $rule->direction,
                        'amount' => $amount,
                        'notes' => $item['notes'] ?? null,
                        'updated_by' => $request->user()?->id,
                        'created_by' => $request->user()?->id,
                    ]
                );
            }

            // Find base transaction amount if recorded
            $baseTx = ShopLedgerTransaction::query()
                ->where('shop_id', $currentShop->shop_id)
                ->whereDate('business_date', $businessDate)
                ->where('entry_type_id', $entryTypeId)
                ->whereNotIn('status', ['void', 'voided', 'reversed'])
                ->first();

            $baseAmount = $baseTx ? (float) $baseTx->amount : 0.0;

            $expected = $this->expectedAmountService->resolve(
                $currentShop->shop_id,
                $businessDate,
                $entryTypeId,
                $baseAmount
            );

            return response()->json([
                'success' => true,
                'message' => 'Bank settlement adjustments saved.',
                'resolved' => $expected,
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function createShopCustomRow(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shop_ledger_profiles,shop_id'],
            'name' => ['required', 'string', 'max:80'],
            'category' => ['required', 'string', 'in:income,expense,transfer'],
        ]);

        try {
            $shop = ShopLedgerProfile::query()
                ->where('shop_id', (int) $validated['shop_id'])
                ->firstOrFail();

            $name = trim($validated['name']);
            $baseCode = Str::slug($name, '_') ?: 'custom_row';
            $code = $baseCode;
            $suffix = 2;

            while (LedgerEntryType::query()->where('code', $code)->exists()) {
                $code = $baseCode.'_'.$suffix++;
            }

            $displayOrder = ((int) LedgerEntryType::query()
                ->where('category', $validated['category'])
                ->max('display_order')) + 1;

            $entryType = LedgerEntryType::query()->create([
                'code' => $code,
                'name' => $name,
                'category' => $validated['category'],
                'system_type' => 'custom',
                'active' => true,
                'display_order' => $displayOrder,
            ]);

            $isIncome = $entryType->category === 'income';
            $isExpense = $entryType->category === 'expense';

            $setting = ShopLedgerEntrySetting::query()->create([
                'shop_id' => $shop->shop_id,
                'entry_type_id' => $entryType->id,
                'version' => 1,
                'effective_from' => '2026-01-01',
                'effective_to' => null,
                'enabled' => true,
                'default_funding_source' => $isExpense ? 'sales' : 'none',
                'allowed_funding_sources' => $isExpense ? ['sales', 'petty', 'company', 'company_later'] : ['none', 'sales', 'bank'],
                'include_in_sales' => $isIncome,
                'include_in_income' => $isIncome,
                'include_in_expense' => $isExpense,
                'include_in_pl' => $entryType->category !== 'transfer',
                'include_in_payable' => false,
                'settlement_behavior' => 'none',
                'petty_behavior' => 'none',
                'company_pending_behavior' => 'none',
                'generates_secondary_entry' => false,
                'secondary_entry_type_id' => null,
                'secondary_amount_mode' => 'same_amount',
                'secondary_amount_value' => null,
                'display_order' => $displayOrder,
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$entryType->name} row added for {$shop->name}.",
                'setting' => $setting->load('entryType'),
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function saveShopCollectionSettings(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shop_ledger_profiles,shop_id'],
            'enabled' => ['required', 'boolean'],
            'income_entry_type_ids' => ['nullable', 'array'],
            'income_entry_type_ids.*' => ['integer', 'exists:ledger_entry_types,id'],
            'expense_entry_type_ids' => ['nullable', 'array'],
            'expense_entry_type_ids.*' => ['integer', 'exists:ledger_entry_types,id'],
        ]);

        $incomeIds = array_map('intval', $validated['income_entry_type_ids'] ?? []);
        $expenseIds = array_map('intval', $validated['expense_entry_type_ids'] ?? []);

        if ((bool) $validated['enabled'] && empty($incomeIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Select at least one income row for collection.',
            ], 422);
        }

        try {
            $group = ShopLedgerCollectionGroup::query()->updateOrCreate(
                [
                    'shop_id' => (int) $validated['shop_id'],
                    'code' => 'collection',
                ],
                [
                    'name' => 'Collection',
                    'enabled' => (bool) $validated['enabled'],
                    'display_order' => 1,
                ]
            );

            $group->entryTypes()->delete();

            if ($group->enabled) {
                $order = 1;
                foreach ($incomeIds as $entryTypeId) {
                    ShopLedgerCollectionGroupEntryType::query()->create([
                        'collection_group_id' => $group->id,
                        'entry_type_id' => $entryTypeId,
                        'role' => 'income',
                        'required' => true,
                        'display_order' => $order++,
                    ]);
                }

                foreach ($expenseIds as $entryTypeId) {
                    ShopLedgerCollectionGroupEntryType::query()->create([
                        'collection_group_id' => $group->id,
                        'entry_type_id' => $entryTypeId,
                        'role' => 'expense',
                        'required' => false,
                        'display_order' => $order++,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Collection settings saved.',
                'group' => $group->fresh('entryTypes.entryType'),
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * API: Create a new shop rule configuration.
     */
    public function createRuleConfig(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'shop_id' => 'required|integer',
            'entry_type_id' => 'required|integer|exists:ledger_entry_types,id',
            'default_funding_source' => 'required|string',
            'include_in_sales' => 'nullable|boolean',
            'include_in_expense' => 'nullable|boolean',
            'include_in_pl' => 'nullable|boolean',
            'generates_secondary' => 'nullable|boolean',
        ]);

        try {
            $setting = ShopLedgerEntrySetting::updateOrCreate(
                [
                    'shop_id' => $validated['shop_id'],
                    'entry_type_id' => $validated['entry_type_id'],
                ],
                [
                    'enabled' => true,
                    'default_funding_source' => $validated['default_funding_source'],
                    'include_in_sales' => (bool) ($validated['include_in_sales'] ?? false),
                    'include_in_expense' => (bool) ($validated['include_in_expense'] ?? false),
                    'include_in_pl' => (bool) ($validated['include_in_pl'] ?? true),
                    'generates_secondary_entry' => (bool) ($validated['generates_secondary'] ?? false),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Rule configuration created successfully.',
                'setting' => $setting->load('entryType'),
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * API: Update a transaction entry's amount (validated via UpdateEntryRequest).
     */
    public function updateEntry(UpdateEntryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->ledgerService->updateEntryAmount(
                (int) $validated['transaction_id'],
                (float) $validated['amount'],
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Entry amount updated.',
                'transaction' => $result['transaction']->load('entryType'),
                'snapshot' => $result['snapshot'],
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * API: Hard-delete a transaction entry (validated via DeleteEntryRequest).
     */
    public function deleteEntry(DeleteEntryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->ledgerService->deleteEntry((int) $validated['transaction_id']);

            return response()->json([
                'success' => true,
                'message' => 'Entry deleted.',
                'deleted_transaction_id' => (int) $validated['transaction_id'],
                'snapshot' => $result['snapshot'],
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * API: Void a transaction entry (validated via VoidEntryRequest).
     */
    public function voidEntry(VoidEntryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->ledgerService->voidEntry(
                (int) $validated['transaction_id'],
                $request->user()?->id ?? 1,
                $validated['reason'] ?? 'Voided by admin'
            );

            return response()->json([
                'success' => true,
                'message' => 'Entry voided.',
                'transaction' => $result['transaction']->load('entryType'),
                'snapshot' => $result['snapshot'],
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * API: Mark a single transaction as approved for the current shop.
     */
    public function approveEntry(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:shop_ledger_transactions,id'],
        ]);

        try {
            $transaction = ShopLedgerTransaction::query()
                ->with('entryType')
                ->findOrFail((int) $validated['transaction_id']);

            if ($transaction->status === 'approved') {
                return response()->json([
                    'success' => true,
                    'message' => 'Entry already approved.',
                    'transaction' => $transaction,
                ]);
            }

            if ($transaction->status === 'void') {
                return response()->json(['success' => false, 'message' => 'Voided entries cannot be approved.'], 422);
            }

            $userId = (int) ($request->user()?->id ?? 1);
            $approvedTx = $this->ledgerService->approveEntry($transaction, $userId);

            return response()->json([
                'success' => true,
                'message' => 'Entry approved.',
                'transaction' => $approvedTx->fresh()->load('entryType'),
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * API: Approve every non-approved income/expense transaction for a day or up to a date.
     */
    public function approveDay(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'shop_id' => ['required', 'integer'],
            'business_date' => ['required', 'date_format:Y-m-d'],
            'till_date' => ['nullable', 'boolean'],
        ]);

        $tillDate = $request->boolean('till_date', false);

        try {
            $userId = (int) ($request->user()?->id ?? 1);
            $updated = $this->ledgerService->approveDay(
                (int) $validated['shop_id'],
                $validated['business_date'],
                $userId,
                $tillDate
            );

            $label = $tillDate
                ? "Approved {$updated} entries up to {$validated['business_date']}."
                : "Approved {$updated} entries for {$validated['business_date']}.";

            return response()->json([
                'success' => true,
                'message' => $updated > 0 ? $label : 'No pending entries found to approve.',
                'approved_count' => $updated,
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * API: Toggle day status (close or reopen).
     */
    public function toggleDay(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $shopId = (int) $request->input('shop_id');
        $date = $request->input('business_date');
        $action = $request->input('action');

        try {
            if ($action === 'close') {
                $snapshot = $this->ledgerService->closeDay($shopId, $date, $request->user()?->id ?? 1);
                $msg = "Day {$date} closed for Shop #{$shopId}.";
            } else {
                $snapshot = $this->ledgerService->reopenDay($shopId, $date);
                $msg = "Day {$date} reopened for Shop #{$shopId}.";
            }

            return response()->json(['success' => true, 'message' => $msg, 'snapshot' => $snapshot]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * API: All shop entry-type settings.
     */
    public function getRules(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $rules = ShopLedgerEntrySetting::with('entryType')
            ->where('enabled', true)
            ->get()
            ->groupBy('shop_id');

        return response()->json(['success' => true, 'rules' => $rules]);
    }

    /**
     * API: Enabled company accounts.
     */
    public function getCompanyAccounts(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        return response()->json([
            'success' => true,
            'accounts' => CompanyAccount::where('enabled', true)->get(),
        ]);
    }

    /**
     * API: Client-level summary (GL bills, shop positions, net receivables).
     */
    public function getClientSummary(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'business_date' => ['nullable', 'date_format:Y-m-d'],
            'timeframe' => ['nullable', 'in:daily,weekly,monthly,custom'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
        $date = $validated['business_date'] ?? today()->toDateString();
        $timeframe = $validated['timeframe'] ?? 'daily';
        [$startDate, $endDate] = $this->cashbookRange(
            $date,
            $timeframe,
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );
        $clients = LedgerClient::with('shops')->where('enabled', true)->get();

        $summary = [];
        $grandTotalGlBills = 0;
        $grandTotalShopPos = 0;
        $grandTotalCompPending = 0;
        $grandTotalReceived = 0;

        foreach ($clients as $client) {
            $clientGlBills = 0;
            $clientShopPos = 0;
            $clientCompPending = 0;
            $clientReceived = 0;
            $shopRows = [];

            foreach ($client->shops as $shop) {
                $snapshot = $this->ledgerService->dailySummary($shop->shop_id, $endDate);

                $glBills = (float) ShopLedgerTransaction::where('shop_id', $shop->shop_id)
                    ->whereBetween('business_date', [$startDate, $endDate])
                    ->where('entry_type_id', fn ($q) => $q->select('id')->from('ledger_entry_types')->where('code', 'purchase_bill'))
                    ->sum('amount');

                $received = (float) ShopLedgerTransaction::where('shop_id', $shop->shop_id)
                    ->whereBetween('business_date', [$startDate, $endDate])
                    ->where('entry_type_id', fn ($q) => $q->select('id')->from('ledger_entry_types')->where('code', 'shop_paid_company'))
                    ->sum('amount');

                $shopPos = (float) $snapshot->closing_shop_position;
                $compPend = (float) $snapshot->closing_company_pending;

                $clientGlBills += $glBills;
                $clientShopPos += $shopPos;
                $clientCompPending += $compPend;
                $clientReceived += $received;

                $shopRows[] = [
                    'shop' => $shop,
                    'snapshot' => $snapshot,
                    'gl_bill' => $glBills,
                    'shop_position' => $shopPos,
                    'company_pending' => $compPend,
                    'received_today' => $received,
                ];
            }

            $summary[] = [
                'client' => $client,
                'total_gl_bills' => $clientGlBills,
                'total_shop_position' => $clientShopPos,
                'total_company_pending' => $clientCompPending,
                'total_received_today' => $clientReceived,
                'net_receivable_from_client' => $clientGlBills + $clientCompPending - $clientReceived,
                'shops' => $shopRows,
            ];

            $grandTotalGlBills += $clientGlBills;
            $grandTotalShopPos += $clientShopPos;
            $grandTotalCompPending += $clientCompPending;
            $grandTotalReceived += $clientReceived;
        }

        return response()->json([
            'success' => true,
            'company' => config('greenleaf'),
            'business_date' => $date,
            'timeframe' => $timeframe,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'clients' => $summary,
            'grand_totals' => [
                'total_gl_bills_issued' => $grandTotalGlBills,
                'total_shop_position' => $grandTotalShopPos,
                'total_company_pending' => $grandTotalCompPending,
                'total_received_today' => $grandTotalReceived,
                'net_receivable' => $grandTotalGlBills + $grandTotalCompPending - $grandTotalReceived,
            ],
        ]);
    }

    /**
     * API: Invoice bill rows for the selected reporting period.
     */
    public function getReportBills(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'business_date' => ['nullable', 'date_format:Y-m-d'],
            'timeframe' => ['nullable', 'in:daily,weekly,monthly,custom'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
        $date = $validated['business_date'] ?? today()->toDateString();
        $timeframe = $validated['timeframe'] ?? 'daily';
        [$startDate, $endDate] = $this->cashbookRange(
            $date,
            $timeframe,
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        $rows = ShopInvoice::query()
            ->with(['shop.client'])
            ->where('final_total', '>', 0)
            ->where('status', '!=', 'cancelled')
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (ShopInvoice $invoice): array {
                $shop = $invoice->shop;
                $isDirect = $shop?->client_id === null;

                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'business_date' => $invoice->business_date?->toDateString(),
                    'final_total' => round((float) $invoice->final_total, 2),
                    'paid_amount' => round((float) $invoice->paid_amount, 2),
                    'balance_amount' => round((float) $invoice->balance_amount, 2),
                    'status' => $invoice->status,
                    'payment_status' => $invoice->payment_status,
                    'scope' => $isDirect ? 'direct' : 'client',
                    'shop' => [
                        'id' => $shop?->id,
                        'name' => $shop?->name,
                        'code' => $shop?->code,
                        'slug' => $shop?->slug,
                    ],
                    'client' => $shop?->client ? [
                        'id' => $shop->client->id,
                        'name' => $shop->client->name,
                    ] : null,
                    'invoice_url' => route('purchasing.shop-invoices.show', $invoice),
                    'shop_url' => $shop ? route('admin.cashbook.shop.show', ['shop' => $shop->slug ?: $shop->id, 'date' => $invoice->business_date?->toDateString() ?? today()->toDateString()]) : null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'business_date' => $date,
            'timeframe' => $timeframe,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'rows' => $rows,
            'totals' => [
                'count' => $rows->count(),
                'total_billed' => round((float) $rows->sum('final_total'), 2),
                'total_paid' => round((float) $rows->sum('paid_amount'), 2),
                'total_balance' => round((float) $rows->sum('balance_amount'), 2),
            ],
        ]);
    }

    /**
     * API: All preset configurations with entry settings.
     */
    public function getPresets(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $presets = ShopConfigPreset::with(['entrySettings.entryType', 'shops', 'collectionGroups.entryTypes.entryType'])
            ->where('enabled', true)
            ->get();

        return response()->json(['success' => true, 'presets' => $presets]);
    }

    /**
     * API: Create a new preset configuration (validated via CreatePresetRequest).
     */
    public function createPreset(CreatePresetRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $slug = Str::slug($validated['name']);
            $count = ShopConfigPreset::where('slug', 'like', $slug.'%')->count();
            if ($count > 0) {
                $slug .= '-'.($count + 1);
            }

            $preset = ShopConfigPreset::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'is_default' => $validated['is_default'] ?? false,
                'enabled' => true,
            ]);

            if (! empty($validated['copy_from_preset_id'])) {
                $sourceSettings = PresetEntrySetting::where('preset_id', $validated['copy_from_preset_id'])->get();
                foreach ($sourceSettings as $s) {
                    PresetEntrySetting::create([
                        'preset_id' => $preset->id,
                        'entry_type_id' => $s->entry_type_id,
                        'version' => 1,
                        'effective_from' => today()->toDateString(),
                        'effective_to' => null,
                        'enabled' => $s->enabled,
                        'default_funding_source' => $s->default_funding_source,
                        'allowed_funding_sources' => $s->allowed_funding_sources,
                        'include_in_sales' => $s->include_in_sales,
                        'include_in_income' => $s->include_in_income,
                        'include_in_expense' => $s->include_in_expense,
                        'include_in_pl' => $s->include_in_pl,
                        'settlement_behavior' => $s->settlement_behavior,
                        'petty_behavior' => $s->petty_behavior,
                        'company_pending_behavior' => $s->company_pending_behavior,
                        'generates_secondary_entry' => $s->generates_secondary_entry,
                        'secondary_entry_type_id' => $s->secondary_entry_type_id,
                        'secondary_amount_mode' => $s->secondary_amount_mode,
                        'secondary_amount_value' => $s->secondary_amount_value,
                        'display_order' => $s->display_order,
                    ]);
                }
            } else {
                // Initialize default entry type settings for all active entry types
                $activeEntryTypes = LedgerEntryType::where('active', true)->orderBy('display_order')->get();
                foreach ($activeEntryTypes as $entryType) {
                    $isSales = $entryType->category === 'income';
                    $isExpense = $entryType->category === 'expense';

                    PresetEntrySetting::create([
                        'preset_id' => $preset->id,
                        'entry_type_id' => $entryType->id,
                        'version' => 1,
                        'effective_from' => today()->toDateString(),
                        'effective_to' => null,
                        'enabled' => true,
                        'default_funding_source' => $isExpense ? 'sales' : 'none',
                        'allowed_funding_sources' => $isExpense ? ['sales', 'petty', 'company', 'company_later'] : ['sales', 'bank', 'none'],
                        'include_in_sales' => $isSales,
                        'include_in_income' => $isSales,
                        'include_in_expense' => $isExpense,
                        'include_in_pl' => true,
                        'settlement_behavior' => $isSales ? 'increase' : 'none',
                        'petty_behavior' => 'none',
                        'company_pending_behavior' => 'none',
                        'generates_secondary_entry' => false,
                        'secondary_entry_type_id' => null,
                        'secondary_amount_mode' => 'same_amount',
                        'secondary_amount_value' => null,
                        'display_order' => $entryType->display_order ?? 0,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Preset '{$preset->name}' created successfully.",
                'preset' => $preset->load(['entrySettings.entryType', 'shops']),
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function deletePreset(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'preset_id' => ['required', 'integer', 'exists:cashbook_config_presets,id'],
        ]);

        try {
            $preset = ShopConfigPreset::withCount('shops')->findOrFail((int) $validated['preset_id']);

            if ($preset->is_default) {
                return response()->json(['success' => false, 'message' => 'Default preset cannot be deleted.'], 422);
            }

            if ($preset->shops_count > 0) {
                return response()->json(['success' => false, 'message' => 'Remove this preset from assigned shops before deleting it.'], 422);
            }

            $preset->delete();

            return response()->json(['success' => true, 'message' => 'Preset deleted successfully.']);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function saveCollectionGroup(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'preset_id' => ['required', 'integer', 'exists:cashbook_config_presets,id'],
            'name' => ['required', 'string', 'max:100'],
            'income_entry_type_ids' => ['required', 'array', 'min:1'],
            'income_entry_type_ids.*' => ['integer', 'exists:ledger_entry_types,id'],
            'expense_entry_type_ids' => ['nullable', 'array'],
            'expense_entry_type_ids.*' => ['integer', 'exists:ledger_entry_types,id'],
        ]);

        try {
            $group = PresetCollectionGroup::updateOrCreate(
                [
                    'preset_id' => (int) $validated['preset_id'],
                    'code' => Str::slug($validated['name'], '_'),
                ],
                [
                    'name' => $validated['name'],
                    'enabled' => true,
                    'display_order' => (PresetCollectionGroup::where('preset_id', $validated['preset_id'])->max('display_order') ?? 0) + 1,
                ]
            );

            $group->entryTypes()->delete();
            $order = 1;
            $collectionEntryTypes = [];
            foreach ($validated['income_entry_type_ids'] as $entryTypeId) {
                PresetCollectionGroupEntryType::create([
                    'collection_group_id' => $group->id,
                    'entry_type_id' => $entryTypeId,
                    'role' => 'income',
                    'required' => true,
                    'display_order' => $order++,
                ]);
                $collectionEntryTypes[(int) $entryTypeId] = 'income';
            }

            foreach ($validated['expense_entry_type_ids'] ?? [] as $entryTypeId) {
                PresetCollectionGroupEntryType::create([
                    'collection_group_id' => $group->id,
                    'entry_type_id' => $entryTypeId,
                    'role' => 'expense',
                    'required' => false,
                    'display_order' => $order++,
                ]);
                $collectionEntryTypes[(int) $entryTypeId] = 'expense';
            }

            $this->ensureCollectionEntrySettings((int) $validated['preset_id'], $collectionEntryTypes);

            return response()->json([
                'success' => true,
                'message' => 'Collection group saved.',
                'group' => $group->load('entryTypes.entryType'),
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Collection rows are posted as real ledger transactions, so every selected
     * row also needs a normal preset/shop ledger setting for the rule resolver.
     *
     * @param  array<int, string>  $entryTypeRoles
     */
    private function ensureCollectionEntrySettings(int $presetId, array $entryTypeRoles): void
    {
        $entryTypes = LedgerEntryType::query()
            ->whereIn('id', array_keys($entryTypeRoles))
            ->get()
            ->keyBy('id');

        $nextOrder = (int) (PresetEntrySetting::where('preset_id', $presetId)->max('display_order') ?? 0);

        foreach ($entryTypeRoles as $entryTypeId => $role) {
            $entryType = $entryTypes->get($entryTypeId);
            if (! $entryType instanceof LedgerEntryType) {
                continue;
            }

            PresetEntrySetting::firstOrCreate(
                [
                    'preset_id' => $presetId,
                    'entry_type_id' => $entryTypeId,
                ],
                [
                    'version' => 1,
                    'effective_from' => '2026-01-01',
                    'effective_to' => null,
                    'enabled' => true,
                    'default_funding_source' => 'none',
                    'allowed_funding_sources' => ['none'],
                    'include_in_sales' => $role === 'income',
                    'include_in_income' => $role === 'income',
                    'include_in_expense' => $role === 'expense',
                    'include_in_pl' => true,
                    'settlement_behavior' => 'none',
                    'petty_behavior' => 'none',
                    'company_pending_behavior' => 'none',
                    'generates_secondary_entry' => false,
                    'secondary_entry_type_id' => null,
                    'secondary_amount_mode' => 'same_amount',
                    'secondary_amount_value' => null,
                    'display_order' => ++$nextOrder,
                ]
            );
        }

        ShopLedgerProfile::query()
            ->where('preset_id', $presetId)
            ->with('preset.entrySettings')
            ->get()
            ->each(fn (ShopLedgerProfile $profile) => $this->shopSyncService->syncPresetSettingsToShop($profile, $profile->preset));
    }

    /**
     * API: Update an entry type rule setting within a preset (validated via UpdatePresetSettingRequest).
     */
    public function updatePresetSetting(UpdatePresetSettingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $setting = PresetEntrySetting::findOrFail($validated['setting_id']);
            $updateData = Arr::except($validated, ['setting_id']);

            $setting->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Preset entry setting updated.',
                'setting' => $setting->load('entryType'),
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * API: Assign a shop to a preset (validated via AssignShopPresetRequest).
     */
    public function assignShopPreset(AssignShopPresetRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $shop = ShopLedgerProfile::where('shop_id', $validated['shop_id'])->firstOrFail();
            $shop->update(['preset_id' => $validated['preset_id'] ?? null]);
            $shop->load('preset');
            $this->shopSyncService->syncPresetSettingsToShop($shop, $shop->preset);

            return response()->json([
                'success' => true,
                'message' => "Shop '{$shop->name}' assigned to preset.",
                'shop' => $shop,
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * API: Create a new custom entry type rule and add it to preset configurations.
     */
    public function createEntryRule(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50',
            'category' => 'required|string|in:income,expense,transfer',
            'description' => 'nullable|string|max:255',
            'include_in_sales' => 'nullable|boolean',
            'include_in_expense' => 'nullable|boolean',
            'include_in_pl' => 'nullable|boolean',
            'settlement_behavior' => 'nullable|string|in:none,decrease,increase',
            'petty_behavior' => 'nullable|string|in:none,decrease,increase',
            'company_pending_behavior' => 'nullable|string|in:none,increase,decrease',
        ]);

        try {
            $name = trim($validated['name']);
            $code = ! empty($validated['code'])
                ? strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', str_replace(' ', '_', $validated['code'])))
                : strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', str_replace(' ', '_', $name)));

            $entryType = LedgerEntryType::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'category' => $validated['category'],
                    'system_type' => 'custom',
                    'active' => true,
                    'display_order' => (LedgerEntryType::max('display_order') ?? 0) + 1,
                ]
            );

            $presets = ShopConfigPreset::all();
            $createdSettings = [];
            foreach ($presets as $preset) {
                $setting = PresetEntrySetting::firstOrCreate(
                    [
                        'preset_id' => $preset->id,
                        'entry_type_id' => $entryType->id,
                    ],
                    [
                        'enabled' => true,
                        'include_in_sales' => (bool) ($validated['include_in_sales'] ?? false),
                        'include_in_income' => (bool) ($validated['include_in_sales'] ?? false),
                        'include_in_expense' => (bool) ($validated['include_in_expense'] ?? false),
                        'include_in_pl' => (bool) ($validated['include_in_pl'] ?? true),
                        'settlement_behavior' => $validated['settlement_behavior'] ?? 'none',
                        'petty_behavior' => $validated['petty_behavior'] ?? 'none',
                        'company_pending_behavior' => $validated['company_pending_behavior'] ?? 'none',
                        'display_order' => $entryType->display_order ?? 0,
                    ]
                );
                $createdSettings[] = $setting->load('entryType');
            }

            return response()->json([
                'success' => true,
                'message' => "Entry rule '{$name}' created and added to preset configuration.",
                'entry_type' => $entryType,
                'created_settings' => $createdSettings,
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function ensureMainAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        if (
            $user->isMainAdmin()
            || $user->hasRole('admin')
            || $user->hasRole('accounts')
            || $user->hasRole('accountant')
            || $user->hasRole('account')
            || $user->hasRole('manager')
            || (property_exists($user, 'is_admin') && $user->is_admin)
            || $user->hasAnyPermission([
                'accounting.report.view',
                'accounting.dashboard.view',
                'accounting.ledger.view',
                'finance.dashboard.view',
            ])
        ) {
            return;
        }

        abort(403, 'Unauthorized access to cashbook.');
    }

    private function resolveShop(int|string $shopParam): ShopLedgerProfile
    {
        $this->shopSyncService->syncAndGetProfiles();

        if (is_numeric($shopParam)) {
            $shop = ShopLedgerProfile::where('shop_id', (int) $shopParam)->first();
            if ($shop) {
                return $shop;
            }
        }

        $shop = ShopLedgerProfile::where('slug', $shopParam)
            ->orWhere('code', $shopParam)
            ->orWhere('uuid', $shopParam)
            ->first();

        return $shop ?: ShopLedgerProfile::orderBy('shop_id')->firstOrFail();
    }

    private function resolveShopPaymentWorkspace(int|string $shopParam): ShopLedgerProfile
    {
        $this->shopSyncService->syncAndGetProfiles();

        $shop = ShopLedgerProfile::query()
            ->where('uuid', (string) $shopParam)
            ->orWhereHas('shop', fn (Builder $query): Builder => $query->where('public_uuid', (string) $shopParam))
            ->first();

        if (! $shop instanceof ShopLedgerProfile) {
            abort(404);
        }

        return $shop;
    }

    private function renderApp(
        string $initialTab = 'all-shops',
        int|string|null $initialShopId = 1,
        ?string $selectedDate = null
    ): View {
        $shops = $this->shopSyncService->syncAndGetProfiles();
        $clients = LedgerClient::with('shops')->where('enabled', true)->get();
        $entryTypes = LedgerEntryType::where('active', true)->orderBy('display_order')->get();
        $companyAccounts = CompanyAccount::where('enabled', true)->get();
        $company = config('greenleaf');

        if ($initialShopId !== null && ! is_numeric($initialShopId)) {
            $matched = $shops->firstWhere('slug', $initialShopId)
                ?? $shops->firstWhere('code', $initialShopId)
                ?? $shops->firstWhere('uuid', $initialShopId);
            $initialShopId = $matched ? $matched->shop_id : 1;
        }

        $initialShopId = (int) ($initialShopId ?: 1);

        $selectedDate ??= today()->toDateString();

        return view('admin.cashbook.index', compact(
            'shops', 'clients', 'entryTypes', 'companyAccounts', 'company', 'initialTab', 'initialShopId', 'selectedDate'
        ));
    }

    private function selectedDate(Request $request): string
    {
        $validated = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        return $validated['date'] ?? today()->toDateString();
    }

    /**
     * @return array{selected_date:string,timeframe:string,start_date:string,end_date:string}
     */
    private function reportFilters(Request $request): array
    {
        // Sanitize any HTML-entity escaped parameter keys (e.g., amp;start_date -> start_date)
        foreach ($request->all() as $key => $value) {
            if (str_starts_with($key, 'amp;') || str_starts_with($key, 'amp%3B')) {
                $cleanKey = preg_replace('/^amp(;|%3B)/i', '', $key);
                if ($cleanKey && ! $request->has($cleanKey)) {
                    $request->merge([$cleanKey => $value]);
                }
            }
        }

        $today = today();
        $timeframe = (string) ($request->input('timeframe') ?: 'today');
        $reqStart = (string) ($request->input('start_date') ?: '');
        $reqEnd = (string) ($request->input('end_date') ?: '');

        // If explicit start_date and end_date are provided in the request, prioritize them
        if ($reqStart !== '' && $reqEnd !== '') {
            $startDate = $reqStart;
            $endDate = $reqEnd;
        } else {
            [$startDate, $endDate] = match ($timeframe) {
                'yesterday' => [
                    $today->copy()->subDay()->toDateString(),
                    $today->copy()->subDay()->toDateString(),
                ],
                'upto_yesterday' => [
                    $today->copy()->startOfMonth()->toDateString(),
                    $today->copy()->subDay()->toDateString(),
                ],
                'weekly' => [
                    $today->copy()->startOfWeek()->toDateString(),
                    $today->copy()->endOfWeek()->toDateString(),
                ],
                'monthly' => [
                    $today->copy()->startOfMonth()->toDateString(),
                    $today->copy()->endOfMonth()->toDateString(),
                ],
                'custom' => [
                    (string) $request->input('start_date', $today->toDateString()),
                    (string) $request->input('end_date', $today->toDateString()),
                ],
                default => [ // 'today' or 'daily'
                    $today->toDateString(),
                    $today->toDateString(),
                ],
            };
        }

        $selectedDate = $request->input('date')
            ?? $request->input('business_date')
            ?? $startDate;

        return [
            'selected_date' => $selectedDate,
            'timeframe' => $timeframe,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function cashbookReportExportRows(
        string $selectedDate,
        string $timeframe,
        string $startDate,
        string $endDate,
        bool $includeDetails = true,
        string $scope = 'all'
    ): array {
        $allShops = $this->shopSyncService->syncAndGetProfiles();
        $allShops->load('client');

        $filteredShops = $allShops->filter(function (ShopLedgerProfile $shop) use ($scope): bool {
            $isDirect = $shop->client_id === null && $shop->profile_template === 'direct_buyer';
            if ($scope === 'owned') {
                return ! $isDirect;
            }
            if ($scope === 'direct') {
                return $isDirect;
            }

            return true;
        })->values();

        $shopIds = $filteredShops->pluck('shop_id')->map(fn ($id) => (int) $id)->all();

        // 1. Sales per shop
        $salesPerShop = ShopLedgerTransaction::query()
            ->whereIn('shop_id', $shopIds)
            ->where('status', '!=', 'voided')
            ->where(function ($q) {
                $q->where('direction', 'income')
                    ->orWhereHas('entryType', fn ($e) => $e->where('category', 'income'));
            })
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->selectRaw('shop_id, SUM(amount) as total_sales')
            ->groupBy('shop_id')
            ->pluck('total_sales', 'shop_id');

        // 2. Expense per shop
        $expensePerShop = ShopLedgerTransaction::query()
            ->whereIn('shop_id', $shopIds)
            ->where('status', '!=', 'voided')
            ->where(function ($q) {
                $q->where('direction', 'expense')
                    ->orWhereHas('entryType', fn ($e) => $e->where('category', 'expense'));
            })
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->selectRaw('shop_id, SUM(amount) as total_expense')
            ->groupBy('shop_id')
            ->pluck('total_expense', 'shop_id');

        // 3. GL Bills per shop with counts
        $glBillsPerShop = ShopInvoice::query()
            ->whereIn('shop_id', $shopIds)
            ->where('status', '!=', 'cancelled')
            ->where('final_total', '>', 0)
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->selectRaw('shop_id, COUNT(*) as bill_count, SUM(final_total) as total_gl')
            ->groupBy('shop_id')
            ->get()
            ->keyBy('shop_id');

        $rows = [];

        // Table 1: Summary Table (Shop-by-Shop)
        $rows[] = ['Shop-Wise Finance Overview Summary'];
        $rows[] = ['Shop Name', 'Scope', 'Sales Total', 'Total Expense', 'Net Balance', 'GL Bill'];

        $grandSales = 0.0;
        $grandExpense = 0.0;
        $grandNet = 0.0;
        $grandGl = 0.0;
        $exportedShopsCount = 0;

        foreach ($filteredShops as $shop) {
            $isDirect = $shop->client_id === null && $shop->profile_template === 'direct_buyer';
            $scopeLabel = $isDirect ? 'Direct' : ($shop->client?->name ?: 'Own');

            $glData = $glBillsPerShop->get($shop->shop_id);
            $billCount = (int) ($glData->bill_count ?? 0);
            $gVal = round((float) ($glData->total_gl ?? 0), 2);

            $sVal = round((float) ($salesPerShop[$shop->shop_id] ?? 0), 2);
            $eVal = round((float) ($expensePerShop[$shop->shop_id] ?? 0), 2);
            $nVal = round($sVal - $eVal, 2);

            // Filter rule: Exclude shops that have 0 bills AND 0 sales/expense activity
            if ($billCount === 0 && $sVal == 0.0 && $eVal == 0.0) {
                continue;
            }

            $grandSales += $sVal;
            $grandExpense += $eVal;
            $grandNet += $nVal;
            $grandGl += $gVal;
            $exportedShopsCount++;

            $rows[] = [
                $shop->name ?: ('Shop #'.$shop->shop_id),
                $scopeLabel,
                $sVal,
                $eVal,
                $nVal,
                $gVal,
            ];
        }

        // Grand Total Row
        $rows[] = [
            'Total ('.$exportedShopsCount.' Shops)',
            '-',
            round($grandSales, 2),
            round($grandExpense, 2),
            round($grandNet, 2),
            round($grandGl, 2),
        ];

        if (! $includeDetails) {
            return $rows;
        }

        $rows[] = [];

        $shopMap = $allShops->keyBy('shop_id');

        // Table 2: Total Sales Details (with Day header & Shop name)
        $rows[] = ['Total Sales Details'];
        $rows[] = ['Date', 'Day', 'Shop', 'Income'];

        $incomeTransactions = ShopLedgerTransaction::query()
            ->with('entryType')
            ->whereIn('shop_id', $shopIds)
            ->where('status', '!=', 'voided')
            ->where(function ($q) {
                $q->where('direction', 'income')
                    ->orWhereHas('entryType', fn ($e) => $e->where('category', 'income'));
            })
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->orderBy('business_date')
            ->orderBy('id')
            ->get();

        foreach ($incomeTransactions as $tx) {
            $carbonDate = $tx->business_date ? Carbon::parse($tx->business_date) : null;
            $bDate = $carbonDate ? $carbonDate->format('Y-m-d') : '';
            $dayName = $carbonDate ? $carbonDate->format('l') : '';
            $shopName = isset($shopMap[$tx->shop_id]) ? $shopMap[$tx->shop_id]->name : ('Shop #'.$tx->shop_id);

            $rows[] = [$bDate, $dayName, $shopName, round((float) $tx->amount, 2)];
        }

        $rows[] = [];

        // Table 3: Total Expense Details (with Day header & Shop name)
        $rows[] = ['Total Expense Details'];
        $rows[] = ['Date', 'Day', 'Shop', 'Expense'];

        $expenseTransactions = ShopLedgerTransaction::query()
            ->with('entryType')
            ->whereIn('shop_id', $shopIds)
            ->where('status', '!=', 'voided')
            ->where(function ($q) {
                $q->where('direction', 'expense')
                    ->orWhereHas('entryType', fn ($e) => $e->where('category', 'expense'));
            })
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->orderBy('business_date')
            ->orderBy('id')
            ->get();

        foreach ($expenseTransactions as $tx) {
            $carbonDate = $tx->business_date ? Carbon::parse($tx->business_date) : null;
            $bDate = $carbonDate ? $carbonDate->format('Y-m-d') : '';
            $dayName = $carbonDate ? $carbonDate->format('l') : '';
            $shopName = isset($shopMap[$tx->shop_id]) ? $shopMap[$tx->shop_id]->name : ('Shop #'.$tx->shop_id);

            $rows[] = [$bDate, $dayName, $shopName, round((float) $tx->amount, 2)];
        }

        return $rows;
    }

    private function reportFilename(string $prefix, string $startDate, string $endDate, string $extension): string
    {
        return "{$prefix}-{$startDate}_{$endDate}.{$extension}";
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function paymentMonthWindow(Request $request): array
    {
        $selected = Carbon::parse((string) $request->input('month', today()->format('Y-m')).'-01');
        $end = $selected->isSameMonth(today()) ? today() : $selected->copy()->endOfMonth();

        return [
            $selected->format('Y-m'),
            $selected->copy()->startOfMonth()->toDateString(),
            $end->toDateString(),
        ];
    }

    private function shopPaymentCard(ShopLedgerProfile $shop, string $startDate, string $endDate): array
    {
        $paymentSummary = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shop->shop_id)
            ->where('status', '!=', 'rejected')
            ->where(function ($query) use ($startDate, $endDate): void {
                $query->whereBetween('payment_date', [$startDate, $endDate])
                    ->orWhereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);
            })
            ->selectRaw('COALESCE(SUM(requested_amount), 0) as received_amount')
            ->selectRaw('COALESCE(SUM(reconciled_amount), 0) as approved_amount')
            ->selectRaw('COALESCE(SUM(floating_amount), 0) as floating_amount')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'pending' THEN requested_amount ELSE 0 END), 0) as pending_amount")
            ->selectRaw('COALESCE(SUM(shop_advance_amount), 0) as advance_amount')
            ->first();

        $payableSummary = $this->shopPayableSummary((int) $shop->shop_id, $startDate, $endDate);
        $approved = round((float) ($paymentSummary->approved_amount ?? 0), 2);
        $payable = round((float) $payableSummary['total'], 2);

        return [
            'shop' => $shop,
            'payable_balance' => $payable,
            'approved_amount' => $approved,
            'after_balance' => round(max(0, $payable - $approved), 2),
            'received_amount' => round((float) ($paymentSummary->received_amount ?? 0), 2),
            'floating_amount' => round((float) ($paymentSummary->floating_amount ?? 0), 2),
            'pending_amount' => round((float) ($paymentSummary->pending_amount ?? 0), 2),
            'advance_amount' => round((float) ($paymentSummary->advance_amount ?? 0), 2),
            'entry_count' => $payableSummary['count'],
        ];
    }

    /**
     * @return array{total: float, count: int}
     */
    private function shopPayableSummary(int $shopId, string $startDate, string $endDate): array
    {
        $details = $this->shopPayableDetailsByQuery($shopId, $startDate, $endDate, '');

        return [
            'total' => round((float) $details['rows']->sum('signed_amount'), 2),
            'count' => (int) $details['rows']->count(),
        ];
    }

    private function shopPayableDetails(ShopLedgerProfile $shop, string $startDate, string $endDate, string $search): array
    {
        return $this->shopPayableDetailsByQuery((int) $shop->shop_id, $startDate, $endDate, $search);
    }

    private function shopPayableDetailsByQuery(int $shopId, string $startDate, string $endDate, string $search): array
    {
        $settings = ShopLedgerEntrySetting::query()
            ->with('entryType')
            ->where('shop_id', $shopId)
            ->where('enabled', true)
            ->get();

        $payableEntryTypeIds = $settings
            ->where('include_in_payable', true)
            ->pluck('entry_type_id')
            ->all();

        $rows = ShopLedgerTransaction::query()
            ->with('entryType')
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->whereNotIn('status', ['void', 'voided'])
            ->where(function ($query) use ($payableEntryTypeIds): void {
                if (! empty($payableEntryTypeIds)) {
                    $query->whereIn('entry_type_id', $payableEntryTypeIds);
                }

                $query->orWhere('reference_type', 'collection_group')
                    ->orWhere('funding_source', 'company')
                    ->orWhere('company_pending_delta', '!=', 0);
            })
            ->oldest('business_date')
            ->oldest('id')
            ->get()
            ->map(function (ShopLedgerTransaction $transaction) use ($settings): ShopLedgerTransaction {
                $setting = $settings->firstWhere('entry_type_id', $transaction->entry_type_id);
                $direction = (string) ($transaction->direction ?: ($transaction->entryType?->category ?: 'income'));
                $category = (string) ($transaction->entryType?->category ?: $direction);
                $code = (string) ($transaction->entryType?->code ?: $transaction->entry_type_code);
                $payableDirection = (string) ($setting?->payable_direction ?: '');
                $isDeduction = $payableDirection === 'minus'
                    || $payableDirection === 'decrease'
                    || $direction === 'expense'
                    || $category === 'expense'
                    || in_array($code, ['company_to_petty', 'company_paid_shop', 'company_paid_vendor'], true);

                $transaction->signed_amount = round($isDeduction ? -(float) $transaction->amount : (float) $transaction->amount, 2);

                return $transaction;
            })
            ->filter(function (ShopLedgerTransaction $transaction) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                $haystack = strtolower(implode(' ', [
                    $transaction->entryType?->name,
                    $transaction->entryType?->code,
                    $transaction->notes,
                    $transaction->business_date?->toDateString(),
                    (string) $transaction->amount,
                ]));

                return str_contains($haystack, strtolower($search));
            })
            ->values();

        $groups = $rows
            ->groupBy(fn (ShopLedgerTransaction $transaction): string => $transaction->entryType?->name ?: (string) $transaction->entry_type_code)
            ->map(function ($group, string $name): array {
                $first = $group->first();

                return [
                    'name' => $name,
                    'code' => $first?->entryType?->code ?: $first?->entry_type_code,
                    'count' => $group->count(),
                    'total' => round((float) $group->sum('signed_amount'), 2),
                    'first_date' => $group->sortBy('business_date')->first()?->business_date?->toDateString(),
                    'last_date' => $group->sortByDesc('business_date')->first()?->business_date?->toDateString(),
                ];
            })
            ->sortByDesc(fn (array $group): float => abs((float) $group['total']))
            ->values();

        return [
            'rows' => $rows,
            'groups' => $groups,
            'total' => round((float) $rows->sum('signed_amount'), 2),
            'count' => $rows->count(),
        ];
    }

    private function extractStatementTextFromPdf(string $path, ?string $password): string
    {
        $errors = [];

        foreach ($this->pdftotextCandidates() as $binary) {
            try {
                return $this->extractStatementTextWithPdftotext($binary, $path, $password);
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        foreach ($this->pdfPythonCandidates() as $pythonPath) {
            try {
                return $this->extractStatementTextWithPython($pythonPath, $path, $password);
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if (! filled($password)) {
            try {
                return $this->extractStatementTextWithPhp($path);
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        $allErrors = implode(' | ', $errors);
        if (stripos($allErrors, 'password') !== false || stripos($allErrors, 'encrypted') !== false || stripos($allErrors, 'Incorrect password') !== false) {
            if (filled($password)) {
                throw new \RuntimeException('The PDF password provided was incorrect. Please verify the statement password (case-sensitive) and try again.');
            }

            throw new \RuntimeException('This PDF statement is password-protected. Please enter the statement password and try again.');
        }

        if (stripos($allErrors, 'No module named') !== false || stripos($allErrors, 'pdftotext failed or is not installed') !== false) {
            throw new \RuntimeException('PDF tools are not installed on the server. On Linux, run: sudo apt-get install -y poppler-utils (or: pip install pypdf).');
        }

        throw new \RuntimeException('PDF could not be read. Check the file or password. Last error: '.end($errors));
    }

    private function extractStatementTextWithPhp(string $path): string
    {
        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            throw new \RuntimeException('Unable to read PDF file.');
        }

        $text = '';
        if (preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $content, $matches)) {
            foreach ($matches[1] as $stream) {
                $decompressed = @gzuncompress($stream);
                if ($decompressed === false) {
                    $decompressed = @gzinflate($stream);
                }
                if ($decompressed === false) {
                    $decompressed = $stream;
                }

                if (str_contains($decompressed, 'BT')) {
                    if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decompressed, $arrayMatches)) {
                        foreach ($arrayMatches[1] as $arrayContent) {
                            if (preg_match_all('/\((.*?)(?<!\\\\)\)/s', $arrayContent, $stringMatches)) {
                                $text .= implode('', $stringMatches[1]).' ';
                            }
                        }
                        $text .= "\n";
                    }

                    if (preg_match_all('/\((.*?)(?<!\\\\)\)\s*T[jJ]/s', $decompressed, $textMatches)) {
                        $text .= implode(' ', $textMatches[1])."\n";
                    }
                }
            }
        }

        $text = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $text);
        $cleaned = trim(preg_replace('/[ \t]+/', ' ', $text));

        if ($cleaned === '') {
            throw new \RuntimeException('PDF stream contains no readable unencrypted text.');
        }

        return $cleaned;
    }

    /**
     * @return array<int, string>
     */
    private function pdftotextCandidates(): array
    {
        $candidates = array_filter([
            config('cashbook.pdftotext_path'),
            env('PDFTOTEXT_PATH'),
            '/opt/homebrew/bin/pdftotext',
            '/usr/local/bin/pdftotext',
            '/usr/bin/pdftotext',
            'pdftotext',
        ]);

        return array_values(array_unique(array_map('strval', $candidates)));
    }

    private function extractStatementTextWithPdftotext(string $binary, string $path, ?string $password): string
    {
        /** @var array<int, array<int, string>> $attempts */
        $attempts = [[]];
        if (filled($password)) {
            $attempts = [
                ['-upw', (string) $password],
                ['-opw', (string) $password],
            ];
        }

        $lastError = '';
        foreach ($attempts as $authFlags) {
            $command = array_merge([$binary, '-layout', '-nopgbrk'], $authFlags, [$path, '-']);
            $process = new Process($command);
            $process->setTimeout(60);
            $process->run();

            if ($process->isSuccessful()) {
                $text = trim($process->getOutput());
                if ($text !== '') {
                    return $text;
                }
            } else {
                $lastError = trim($process->getErrorOutput()) ?: 'pdftotext failed or is not installed.';
            }
        }

        throw new \RuntimeException($lastError ?: 'pdftotext returned empty text.');
    }

    private function extractStatementTextWithPython(string $pythonPath, string $path, ?string $password): string
    {
        $script = <<<'PY'
import sys
from pypdf import PdfReader
from pypdf.errors import WrongPasswordError

path = sys.argv[1]
password = sys.argv[2] if len(sys.argv) > 2 and sys.argv[2] else None

try:
    reader = PdfReader(path)
    if reader.is_encrypted:
        if password:
            decrypted = reader.decrypt(password)
            if decrypted == 0:
                print("Incorrect password for encrypted PDF", file=sys.stderr)
                sys.exit(1)
        else:
            print("PDF is encrypted but no password provided", file=sys.stderr)
            sys.exit(1)

    text = "\n".join((page.extract_text() or "") for page in reader.pages)
    if not text.strip():
        print("PDF extracted text is empty", file=sys.stderr)
        sys.exit(1)
    print(text)
except WrongPasswordError:
    print("Incorrect password for encrypted PDF", file=sys.stderr)
    sys.exit(1)
except Exception as e:
    print(str(e), file=sys.stderr)
    sys.exit(1)
PY;

        $process = new Process([$pythonPath, '-c', $script, $path, (string) $password]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'Python PDF extraction failed.');
        }

        $text = trim($process->getOutput());
        if ($text === '') {
            throw new \RuntimeException('Python PDF extraction returned empty text.');
        }

        return $text;
    }

    /**
     * @return array<int, string>
     */
    private function pdfPythonCandidates(): array
    {
        $candidates = array_filter([
            env('PDF_PYTHON_PATH'),
            env('PYTHON_PATH'),
            'python3',
            'python',
            '/opt/homebrew/bin/python3',
            '/usr/local/bin/python3',
            '/usr/bin/python3',
        ]);

        $userProfile = getenv('USERPROFILE');
        if (is_string($userProfile) && $userProfile !== '') {
            $winPath = $userProfile.'\\.cache\\codex-runtimes\\codex-primary-runtime\\dependencies\\python\\python.exe';
            if (file_exists($winPath)) {
                $candidates[] = $winPath;
            }
        }

        $home = getenv('HOME');
        if (is_string($home) && $home !== '') {
            $macPath = $home.'/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/bin/python3';
            if (file_exists($macPath)) {
                $candidates[] = $macPath;
            }
        }

        return array_values(array_unique(array_map('strval', $candidates)));
    }

    /**
     * @return array<int, array{transaction_date: string, direction: string, amount: float, reference: ?string, narration: string}>
     */
    private function parseBankStatementRows(string $text, string $month): array
    {
        $rows = [];
        $current = null;

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim(preg_replace('/\s+/', ' ', (string) $line));
            if ($line === '') {
                continue;
            }

            if (preg_match('/^\d{2}-\d{2}-\d{2}\s+/', $line) === 1) {
                if ($current !== null) {
                    $rows[] = $current;
                }

                $current = $line;

                continue;
            }

            if ($current !== null && ! str_contains(Str::lower($line), 'page total')) {
                $current .= ' '.$line;
            }
        }

        if ($current !== null) {
            $rows[] = $current;
        }

        $parsed = [];
        $previousBalance = null;

        foreach ($rows as $rawRow) {
            if (preg_match('/^(\d{2}-\d{2}-\d{2})\s+(.+)$/', $rawRow, $rowMatch) !== 1) {
                continue;
            }

            $date = Carbon::createFromFormat('d-m-y', $rowMatch[1]);
            if ($date->format('Y-m') !== $month) {
                continue;
            }

            $body = trim($rowMatch[2]);
            if (preg_match('/([\d,]+\.\d{2})\s*(Cr|Dr)\s*$/i', $body, $balanceMatch) !== 1) {
                continue;
            }

            $balance = $this->statementMoneyToFloat($balanceMatch[1]);
            if (Str::lower($balanceMatch[2]) === 'dr') {
                $balance *= -1;
            }

            $beforeBalance = trim(substr($body, 0, -strlen($balanceMatch[0])));
            preg_match_all('/(?:\d{1,3}(?:,\d{2,3})+|\d+)\.\d{2}/', $beforeBalance, $amountMatches, PREG_OFFSET_CAPTURE);
            if (empty($amountMatches[0])) {
                continue;
            }

            $amountMatch = end($amountMatches[0]);
            $amount = $this->statementMoneyToFloat($amountMatch[0]);
            $narration = trim(substr($beforeBalance, 0, (int) $amountMatch[1]));
            if ($narration === '') {
                $narration = 'Imported bank statement row';
            }

            $parsed[] = [
                'transaction_date' => $date->toDateString(),
                'direction' => $this->inferStatementDirection($narration, $amount, $balance, $previousBalance),
                'amount' => $amount,
                'reference' => $this->statementReferenceFromNarration($narration),
                'narration' => Str::limit($narration, 1800, ''),
            ];

            $previousBalance = $balance;
        }

        return $parsed;
    }

    private function inferStatementDirection(string $narration, float $amount, float $balance, ?float $previousBalance): string
    {
        if ($previousBalance !== null) {
            $delta = round($balance - $previousBalance, 2);
            if (abs(abs($delta) - $amount) <= 0.05) {
                return $delta >= 0 ? 'in' : 'out';
            }
        }

        return preg_match('/\b(TO:|NEFT TO|TRANSFER TO|WITHDRAW|DEBIT|CHQ PAID|CHARGES|FEE|TAX)\b/i', $narration) === 1
            ? 'out'
            : 'in';
    }

    private function statementReferenceFromNarration(string $narration): ?string
    {
        foreach (['/RRN-\d+/i', '/\bIMPS\/[A-Z0-9]+\/\d+/i', '/\bUPI\/[A-Z0-9]+\/(?:RRN-)?\d+/i', '/\bNEFT\s+TO:[A-Z0-9]+/i'] as $pattern) {
            if (preg_match($pattern, $narration, $match) === 1) {
                return Str::upper(Str::limit($match[0], 150, ''));
            }
        }

        return null;
    }

    private function statementMoneyToFloat(string $value): float
    {
        return round((float) str_replace(',', '', $value), 2);
    }

    private function statementImportFingerprint(CompanyAccount $account, array $row): string
    {
        return hash('sha256', implode('|', [
            $account->id,
            $row['transaction_date'],
            $row['direction'],
            number_format((float) $row['amount'], 2, '.', ''),
            Str::of((string) ($row['reference'] ?? ''))->lower()->squish()->toString(),
            Str::of((string) $row['narration'])->lower()->squish()->toString(),
        ]));
    }

    private function resolveSecurePaymentRequest(string $paymentRef): ShopInvoicePaymentRequest
    {
        return ShopInvoicePaymentRequest::query()
            ->whereKey($this->decodeFinanceRouteKey($paymentRef, 'shop-payment'))
            ->firstOrFail();
    }

    private function resolveSecureStatementEntry(string $statementRef): CompanyAccountStatementEntry
    {
        return CompanyAccountStatementEntry::query()
            ->whereKey($this->decodeFinanceRouteKey($statementRef, 'statement-entry'))
            ->firstOrFail();
    }

    private function tryDecodeFinanceRouteKey(string $routeKey, string $expectedType): ?int
    {
        try {
            $payload = strtr($routeKey, '-_', '+/');
            $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
            $decoded = Crypt::decryptString(base64_decode($payload, true) ?: '');

            $prefix = $expectedType.':';
            if (! str_starts_with($decoded, $prefix)) {
                return null;
            }

            $id = (int) Str::after($decoded, $prefix);

            return $id > 0 ? $id : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function decodeFinanceRouteKey(string $routeKey, string $expectedType): int
    {
        try {
            $payload = strtr($routeKey, '-_', '+/');
            $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
            $decoded = Crypt::decryptString(base64_decode($payload, true) ?: '');
        } catch (Throwable) {
            abort(404);
        }

        $prefix = $expectedType.':';
        if (! str_starts_with($decoded, $prefix)) {
            abort(404);
        }

        $id = (int) Str::after($decoded, $prefix);
        abort_if($id <= 0, 404);

        return $id;
    }

    private function possiblePaymentsForStatement(CompanyAccountStatementEntry $statementEntry, int $graceDays, string $search)
    {
        $statementDate = $statementEntry->transaction_date ?: today();
        $startDate = $statementDate->copy()->subDays($graceDays)->toDateString();
        $endDate = $statementDate->copy()->addDays($graceDays)->toDateString();
        $remainingStatementAmount = round((float) $statementEntry->amount - (float) $statementEntry->matched_amount, 2);

        return ShopInvoicePaymentRequest::query()
            ->with(['shop', 'invoice', 'requestedBy'])
            ->where('status', '!=', 'rejected')
            ->where(function ($query): void {
                $query->whereIn('status', ['pending', 'partially_reconciled'])
                    ->orWhereIn('reconciliation_status', ['pending', 'floating', 'partially_reconciled']);
            })
            ->where(function ($query) use ($startDate, $endDate): void {
                $query->whereBetween('payment_date', [$startDate, $endDate])
                    ->orWhereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('payment_reference', 'like', '%'.$search.'%')
                        ->orWhere('shop_note', 'like', '%'.$search.'%')
                        ->orWhereHas('shop', fn ($shopQuery) => $shopQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->get()
            ->map(function (ShopInvoicePaymentRequest $paymentRequest) use ($statementEntry, $remainingStatementAmount): array {
                $floatingAmount = (float) $paymentRequest->floating_amount > 0
                    ? (float) $paymentRequest->floating_amount
                    : max(0, (float) $paymentRequest->requested_amount - (float) $paymentRequest->reconciled_amount);
                $score = 0;

                if (abs($floatingAmount - $remainingStatementAmount) < 0.01) {
                    $score += 70;
                } elseif (abs($floatingAmount - $remainingStatementAmount) <= 5) {
                    $score += 40;
                }

                if ($paymentRequest->payment_date && $statementEntry->transaction_date) {
                    $score += max(0, 20 - abs($paymentRequest->payment_date->diffInDays($statementEntry->transaction_date)));
                }

                if ($paymentRequest->payment_reference && $statementEntry->reference && str_contains(
                    strtolower((string) $statementEntry->reference),
                    strtolower((string) $paymentRequest->payment_reference)
                )) {
                    $score += 25;
                }

                return [
                    'payment' => $paymentRequest,
                    'floating_amount' => round($floatingAmount, 2),
                    'score' => $score,
                ];
            })
            ->filter(fn (array $item): bool => $item['floating_amount'] > 0)
            ->sortByDesc('score')
            ->values();
    }

    /**
     * @return Collection<int, array{payment: ShopInvoicePaymentRequest, floating_amount: float}>
     */
    private function eligibleShopPaymentsForStatement(CompanyAccountStatementEntry $statementEntry, string $search): Collection
    {
        $statementAmount = round((float) $statementEntry->amount, 2);

        return ShopInvoicePaymentRequest::query()
            ->with(['shop', 'reconciliations'])
            ->withExists('allocations')
            ->where('status', '!=', 'rejected')
            ->whereDoesntHave('allocations')
            ->where(function (Builder $query): void {
                $query->whereIn('status', ['pending', 'partially_reconciled'])
                    ->orWhereIn('reconciliation_status', ['pending', 'floating', 'partially_reconciled']);
            })
            ->whereDoesntHave('reconciliations', fn (Builder $query) => $query->where('is_finalized', true))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $sub) use ($search): void {
                    $sub->where('payment_reference', 'like', '%'.$search.'%')
                        ->orWhere('shop_note', 'like', '%'.$search.'%')
                        ->orWhereHas('shop', fn (Builder $shopQuery) => $shopQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->latest('payment_date')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(function (ShopInvoicePaymentRequest $paymentRequest): array {
                return [
                    'payment' => $paymentRequest,
                    'floating_amount' => $this->shopPaymentFloatingAmount($paymentRequest),
                ];
            })
            ->filter(fn (array $candidate): bool => $this->isEligibleShopPaymentForStatement($candidate['payment'], $statementEntry)
                && abs($candidate['floating_amount'] - $statementAmount) <= 0.01)
            ->take(30)
            ->values();
    }

    private function isEligibleShopPaymentForStatement(ShopInvoicePaymentRequest $paymentRequest, CompanyAccountStatementEntry $statementEntry): bool
    {
        if ($statementEntry->direction !== 'in'
            || $statementEntry->is_finalized
            || $paymentRequest->status === 'rejected'
            || (bool) $paymentRequest->allocations_exists) {
            return false;
        }

        $paymentIsPending = in_array($paymentRequest->status, ['pending', 'partially_reconciled'], true)
            || in_array($paymentRequest->reconciliation_status, ['pending', 'floating', 'partially_reconciled'], true);
        if (! $paymentIsPending || $paymentRequest->reconciliations->contains(fn (CompanyPaymentReconciliation $reconciliation): bool => $reconciliation->is_finalized)) {
            return false;
        }

        $expectedAccountType = $paymentRequest->payment_method === 'cash' ? 'cash' : 'bank';

        return $statementEntry->companyAccount?->enabled
            && $statementEntry->companyAccount->account_type === $expectedAccountType
            && abs($this->shopPaymentFloatingAmount($paymentRequest) - (float) $statementEntry->amount) <= 0.01;
    }

    private function shopPaymentFloatingAmount(ShopInvoicePaymentRequest $paymentRequest): float
    {
        return round(
            (float) $paymentRequest->floating_amount > 0
                ? (float) $paymentRequest->floating_amount
                : max(0, (float) $paymentRequest->requested_amount - (float) $paymentRequest->reconciled_amount),
            2,
        );
    }

    private function possibleLinkedJournalsForStatement(CompanyAccountStatementEntry $statementEntry): Collection
    {
        if (! $statementEntry->journal_entry_id) {
            return collect();
        }

        return JournalEntry::query()
            ->with(['transactions.account', 'statementEntries', 'createdBy'])
            ->whereKey($statementEntry->journal_entry_id)
            ->get()
            ->map(function (JournalEntry $journalEntry) use ($statementEntry): array {
                $remainingStatementAmount = round((float) $statementEntry->amount - (float) $statementEntry->matched_amount, 2);
                $openAmount = round((float) $journalEntry->primary_amount - (float) $journalEntry->statementEntries->sum('matched_amount'), 2);

                return [
                    'journal_entry' => $journalEntry,
                    'floating_amount' => max(0, min($openAmount, $remainingStatementAmount)),
                    'score' => 100,
                ];
            })
            ->filter(fn (array $item): bool => $item['floating_amount'] > 0)
            ->values();
    }

    private function possibleVendorPaymentJournalsForStatement(CompanyAccountStatementEntry $statementEntry, int $graceDays, string $search): Collection
    {
        $statementDate = $statementEntry->transaction_date ?: today();
        $startDate = $statementDate->copy()->subDays($graceDays)->toDateString();
        $endDate = $statementDate->copy()->addDays($graceDays)->toDateString();
        $remainingStatementAmount = round((float) $statementEntry->amount - (float) $statementEntry->matched_amount, 2);

        return JournalEntry::query()
            ->with(['transactions.account', 'statementEntries', 'createdBy'])
            ->whereDate('entry_date', '>=', $startDate)
            ->whereDate('entry_date', '<=', $endDate)
            ->where(function (Builder $query) use ($statementEntry): void {
                if ($statementEntry->journal_entry_id) {
                    $query->whereKey($statementEntry->journal_entry_id);
                }

                $query->orWhere(function (Builder $vendorQuery): void {
                    $vendorQuery->where(function (Builder $sourceQuery): void {
                        $sourceQuery->where('source_type', PurchaseInvoice::class)
                            ->where('source_event', 'like', 'company_vendor_credit_payment:%')
                            ->orWhere('source_type', VendorSettlement::class);
                    });
                })->orWhere(function (Builder $fundingQuery): void {
                    $fundingQuery->where('source_type', PurchaserCredit::class)
                        ->where('source_event', 'purchaser_funding');
                });
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $sub) use ($search): void {
                    $sub->where('reference', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->get()
            ->map(function (JournalEntry $journalEntry) use ($statementEntry, $remainingStatementAmount): array {
                $openAmount = round((float) $journalEntry->primary_amount - (float) $journalEntry->statementEntries->sum('matched_amount'), 2);
                $score = 0;

                if (abs($openAmount - $remainingStatementAmount) < 0.01) {
                    $score += 70;
                } elseif (abs($openAmount - $remainingStatementAmount) <= 5) {
                    $score += 40;
                }

                if ($journalEntry->entry_date && $statementEntry->transaction_date) {
                    $score += max(0, 20 - abs($journalEntry->entry_date->diffInDays($statementEntry->transaction_date)));
                }

                $statementText = strtolower(trim(($statementEntry->reference ?? '').' '.($statementEntry->narration ?? '')));
                $journalText = strtolower(trim(($journalEntry->reference ?? '').' '.($journalEntry->description ?? '')));

                if ($statementText !== '' && $journalText !== '' && (str_contains($statementText, $journalEntry->reference ?? '') || str_contains($statementText, $journalEntry->description ?? '') || str_contains($journalText, $statementEntry->reference ?? ''))) {
                    $score += 25;
                }

                return [
                    'journal_entry' => $journalEntry,
                    'floating_amount' => max(0, $openAmount),
                    'score' => $score,
                ];
            })
            ->filter(fn (array $item): bool => $item['floating_amount'] > 0)
            ->sortByDesc('score')
            ->values();
    }

    /**
     * @return array{
     *     pending: list<array<string, mixed>>,
     *     reconciled: list<array<string, mixed>>,
     *     counts: array{
     *         pending: int,
     *         reconciled: int,
     *         exact_date_pending: int,
     *         exact_date_reconciled: int
     *     }
     * }
     */
    private function possibleExistingJournalCandidatesForStatement(CompanyAccountStatementEntry $statementEntry, int $graceDays, string $search): array
    {
        return $this->companyPaymentReconciliationService->findJournalCandidatesForStatement($statementEntry, $search);
    }

    /**
     * @return array<int, class-string>
     */
    private function matchableCashbookSourceTypes(): array
    {
        return $this->companyPaymentReconciliationService->matchableCashbookSourceTypes();
    }

    private function secureJournalEntryKey(JournalEntry $journalEntry): string
    {
        return rtrim(strtr(base64_encode(Crypt::encryptString('journal-entry:'.$journalEntry->getKey())), '+/', '-_'), '=');
    }

    private function collectionGroupsForShop(int $shopId)
    {
        $profile = ShopLedgerProfile::query()
            ->where('shop_id', $shopId)
            ->with('preset.collectionGroups.entryTypes.entryType')
            ->first();

        return $profile?->preset?->collectionGroups
            ?->where('enabled', true)
            ->values() ?? collect();
    }

    private function collectionSummaries($transactions): array
    {
        return $transactions
            ->filter(fn (ShopLedgerTransaction $transaction): bool => $transaction->reference_type === 'collection_group' && $transaction->reference_id !== null)
            ->groupBy('reference_id')
            ->map(function ($rows, $referenceId): array {
                $income = (float) $rows
                    ->filter(fn (ShopLedgerTransaction $transaction): bool => $transaction->direction === 'income' || $transaction->entryType?->category === 'income')
                    ->sum('amount');
                $expense = (float) $rows
                    ->filter(fn (ShopLedgerTransaction $transaction): bool => $transaction->direction === 'expense' || $transaction->entryType?->category === 'expense')
                    ->sum('amount');
                $first = $rows->sortBy('id')->first();

                return [
                    'reference_id' => (int) $referenceId,
                    'business_date' => $first?->business_date?->toDateString(),
                    'name' => str((string) ($first?->notes ?: 'Collection'))->before(' collection')->title()->toString(),
                    'income' => round($income, 2),
                    'expense' => round($expense, 2),
                    'net' => round($income - $expense, 2),
                    'lines' => $rows->values(),
                ];
            })
            ->values()
            ->all();
    }

    private function ensureShopSettings(ShopLedgerProfile $profile): void
    {
        if ($profile->preset) {
            $profile->loadMissing('preset.entrySettings');
            $this->shopSyncService->syncPresetSettingsToShop($profile, $profile->preset);
        }

        $existingEntryTypeIds = ShopLedgerEntrySetting::query()
            ->where('shop_id', $profile->shop_id)
            ->pluck('entry_type_id')
            ->all();

        $nextOrder = (int) (ShopLedgerEntrySetting::query()
            ->where('shop_id', $profile->shop_id)
            ->max('display_order') ?? 0);

        LedgerEntryType::query()
            ->where('active', true)
            ->whereNotIn('id', $existingEntryTypeIds)
            ->orderBy('display_order')
            ->get()
            ->each(function (LedgerEntryType $entryType) use ($profile, &$nextOrder): void {
                $isIncome = $entryType->category === 'income';
                $isExpense = $entryType->category === 'expense';
                $isSettlement = $entryType->category === 'settlement';
                $isTransfer = $entryType->category === 'transfer';

                $settlementBehavior = match ($entryType->code) {
                    'shop_paid_company', 'sales_to_company', 'sales_to_petty' => 'decrease',
                    default => 'none',
                };

                $pettyBehavior = match ($entryType->code) {
                    'company_to_petty', 'sales_to_petty', 'bank_to_petty', 'petty_reimbursement' => 'increase',
                    'petty_to_company' => 'decrease',
                    default => 'none',
                };

                $companyPendingBehavior = match ($entryType->code) {
                    'company_paid_shop', 'petty_reimbursement' => 'decrease',
                    default => 'none',
                };

                $defaultFunding = match ($entryType->code) {
                    'company_to_petty', 'company_paid_shop', 'company_paid_vendor', 'petty_reimbursement' => 'company',
                    'bank_to_petty' => 'bank',
                    'petty_to_company' => 'petty',
                    default => $isExpense ? 'sales' : 'none',
                };

                $allowedFunding = match ($entryType->code) {
                    'shop_paid_company' => ['sales', 'company'],
                    'company_to_petty', 'company_paid_shop', 'company_paid_vendor', 'petty_reimbursement' => ['company'],
                    'bank_to_petty' => ['bank'],
                    'petty_to_company' => ['petty'],
                    default => $isExpense
                        ? ['sales', 'petty', 'company', 'company_later']
                        : ['none', 'sales', 'bank'],
                };

                ShopLedgerEntrySetting::query()->create([
                    'shop_id' => $profile->shop_id,
                    'entry_type_id' => $entryType->id,
                    'version' => 1,
                    'effective_from' => '2026-01-01',
                    'effective_to' => null,
                    'enabled' => $isSettlement || $isTransfer,
                    'default_funding_source' => $defaultFunding,
                    'allowed_funding_sources' => $allowedFunding,
                    'include_in_sales' => $isIncome,
                    'include_in_income' => $isIncome,
                    'include_in_expense' => $isExpense,
                    'include_in_pl' => ! $isSettlement && ! $isTransfer,
                    'settlement_behavior' => $settlementBehavior,
                    'petty_behavior' => $pettyBehavior,
                    'company_pending_behavior' => $companyPendingBehavior,
                    'generates_secondary_entry' => false,
                    'secondary_entry_type_id' => null,
                    'secondary_amount_mode' => 'same_amount',
                    'secondary_amount_value' => null,
                    'display_order' => $entryType->display_order ?? ++$nextOrder,
                ]);
            });
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function cashbookRange(string $businessDate, string $timeframe, ?string $startDate = null, ?string $endDate = null): array
    {
        $selectedDate = Carbon::parse($businessDate)->min(today());

        if ($timeframe === 'custom') {
            $parsedStart = Carbon::parse($startDate ?: $businessDate);
            $parsedEnd = Carbon::parse($endDate ?: $businessDate)->min(today());
            if ($parsedStart->greaterThan($parsedEnd)) {
                $temp = $parsedStart->copy();
                $parsedStart = $parsedEnd->copy();
                $parsedEnd = $temp;
            }

            return [$parsedStart->toDateString(), $parsedEnd->toDateString()];
        }

        [$rangeStart, $rangeEnd] = match ($timeframe) {
            'weekly' => [$selectedDate->copy()->startOfWeek(), $selectedDate->copy()],
            'monthly' => [$selectedDate->copy()->startOfMonth(), $selectedDate->copy()],
            default => [$selectedDate->copy(), $selectedDate->copy()],
        };

        if ($rangeStart->greaterThan($rangeEnd)) {
            $rangeStart = $rangeEnd->copy();
        }

        return [$rangeStart->toDateString(), $rangeEnd->toDateString()];
    }

    /** @return array{period:string,start_date:string,end_date:string,purchaser_id:?int,vendor_id:?int,payment:string,warehouse_code:?string,category_ids:array<int, int>,batch_ids:array<int, int>,grade:?string,search:string} */
    private function purchaseReportFilters(Request $request, string $defaultPeriod = 'today'): array
    {
        $validated = $request->validate([
            'period' => ['nullable', 'in:today,yesterday,week,month,custom,between,range'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'purchaser_id' => ['nullable', 'integer', 'exists:users,id'],
            'vendor_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'payment' => ['nullable', 'in:all,cash,credit'],
            'product_filter' => ['nullable', 'string', 'exists:purchase_product_filters,uuid,deleted_at,NULL'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'grade' => ['nullable', 'in:A,B'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $period = $validated['period'] ?? $defaultPeriod;
        $today = now('Asia/Kolkata')->startOfDay();
        [$startDate, $endDate] = match ($period) {
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            'week' => [$today->copy()->startOfWeek(), $today],
            'month' => [$today->copy()->startOfMonth(), $today],
            'custom', 'between', 'range' => [
                Carbon::parse($validated['start_date'] ?? $today)->startOfDay(),
                Carbon::parse($validated['end_date'] ?? $validated['start_date'] ?? $today)->startOfDay(),
            ],
            default => [$today, $today],
        };
        if ($startDate->greaterThan($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $productFilterUuid = $validated['product_filter'] ?? null;
        $purchaseProductFilter = $productFilterUuid
            ? PurchaseProductFilter::query()->where('uuid', $productFilterUuid)->firstOrFail()
            : null;

        return [
            'period' => $period,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'purchaser_id' => isset($validated['purchaser_id']) ? (int) $validated['purchaser_id'] : null,
            'vendor_id' => isset($validated['vendor_id']) ? (int) $validated['vendor_id'] : null,
            'payment' => $validated['payment'] ?? 'all',
            'product_filter' => $productFilterUuid,
            'purchase_product_filter_id' => $purchaseProductFilter?->id,
            'category_ids' => isset($validated['category_id']) ? [(int) $validated['category_id']] : array_map('intval', $validated['category_ids'] ?? []),
            'batch_ids' => [],
            'grade' => $validated['grade'] ?? null,
            'search' => trim((string) ($validated['search'] ?? '')),
        ];
    }

    private function purchaseDashboardFilters(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['nullable', 'in:today,yesterday,week,month,custom,between,range'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'product_filter' => ['nullable', 'string', 'exists:purchase_product_filters,uuid,deleted_at,NULL'],
        ]);

        $period = $validated['period'] ?? 'today';
        $today = now('Asia/Kolkata')->startOfDay();
        [$startDate, $endDate] = match ($period) {
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            'week' => [$today->copy()->startOfWeek(), $today],
            'month' => [$today->copy()->startOfMonth(), $today],
            'custom', 'between', 'range' => [
                Carbon::parse($validated['start_date'] ?? $today)->startOfDay(),
                Carbon::parse($validated['end_date'] ?? $validated['start_date'] ?? $today)->startOfDay(),
            ],
            default => [$today, $today],
        };
        if ($startDate->greaterThan($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $productFilterUuid = $validated['product_filter'] ?? null;
        $purchaseProductFilter = $productFilterUuid
            ? PurchaseProductFilter::query()->where('uuid', $productFilterUuid)->firstOrFail()
            : null;

        return [
            'period' => $period,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'purchaser_id' => null,
            'vendor_id' => null,
            'payment' => 'all',
            'product_filter' => $productFilterUuid,
            'purchase_product_filter_id' => $purchaseProductFilter?->id,
            'category_ids' => [],
            'batch_ids' => [],
            'grade' => null,
            'search' => '',
        ];
    }

    /** @return array<string, mixed> */
    private function purchaseSectionFilters(Request $request, string $section): array
    {
        $filters = $this->purchaseReportFilters($request, 'month');
        $filters['search_scope'] = $section;
        $filters['batch_ids'] = [];

        if ($section !== 'invoices') {
            $filters['grade'] = null;
        }
        if ($section === 'purchasers') {
            $filters['purchaser_id'] = null;
        }
        if ($section === 'vendors') {
            $filters['vendor_id'] = null;
        }
        if ($section === 'categories') {
            $filters['category_ids'] = [];
        }

        return $filters;
    }

    /** @return array<string, mixed> */
    private function purchaseDetailFilters(Request $request): array
    {
        return $this->purchaseReportFilters($request);
    }

    /** @return array<string, mixed> */
    private function purchaseLayoutData(): array
    {
        $shops = $this->shopSyncService->syncAndGetProfiles();

        return [
            'shops' => $shops,
            'companyAccounts' => CompanyAccount::where('enabled', true)->orderBy('name')->get(),
            'company' => config('greenleaf'),
            'currentShop' => $shops->first(),
            'productFilters' => PurchaseProductFilter::query()->orderBy('name')->get(['id', 'uuid', 'name']),
        ];
    }
}
