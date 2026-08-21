<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Exports\CashFlowDayJournalExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\ApplyShopInvoiceDiscountRequest;
use App\Http\Requests\Web\Admin\ApproveShopInvoicePaymentRequest;
use App\Http\Requests\Web\Admin\CloseShopAccountingPeriodRequest;
use App\Http\Requests\Web\Admin\ReviewOwnedShopPaymentRequest;
use App\Http\Requests\Web\Admin\ReviewShopAccountingEntryRequest;
use App\Http\Requests\Web\Admin\ReviewShopInvoicePaymentRequest;
use App\Http\Requests\Web\Admin\StoreShopAccountingCategoryRequest;
use App\Http\Requests\Web\Admin\StoreShopAccountingEntryRequest;
use App\Http\Requests\Web\Admin\UpdateDailyBillPaymentRequest;
use App\Http\Requests\Web\Admin\UpdateShopAccountingEntryRequest;
use App\Http\Requests\Web\Admin\UpdateShopPettyCashSettingsRequest;
use App\Models\BusinessSetting;
use App\Models\Client;
use App\Models\CompanyAccountingEntry;
use App\Models\OtherExpense;
use App\Models\ProcurementExpense;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCredit;
use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopCashMovementCategory;
use App\Models\ShopCredit;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopStaffPayment;
use App\Models\User;
use App\Services\Admin\UserImpersonationService;
use App\Services\Finance\AdminFinancePillarService;
use App\Services\Finance\CompanyMainAccountService;
use App\Services\Finance\CompanySummaryReportService;
use App\Services\Finance\JournalService;
use App\Services\Finance\OwnedShopAccountingService;
use App\Services\Finance\ShopLoanService;
use App\Services\ShopInvoices\ShopInvoiceService;
use App\Support\AccountingAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminAccountingController extends Controller
{
    public function __construct(
        private readonly AdminFinancePillarService $financePillars,
        private readonly CompanyMainAccountService $companyMainAccounts,
        private readonly CompanySummaryReportService $companySummaryReports,
        private readonly JournalService $journalService,
        private readonly OwnedShopAccountingService $ownedShopAccountingService,
        private readonly ShopLoanService $shopLoanService,
        private readonly UserImpersonationService $impersonation,
        private readonly ShopInvoiceService $shopInvoiceService,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::DashboardView);

        $date = Carbon::parse($request->input('date', today()->toDateString()));
        $finance = $this->financePillars->forPeriod($date, $date);
        $ownedMetrics = $this->ownedShopAccountingService->dashboardMetrics($date);
        $allEligibleShops = $this->ownedShopAccountingService->eligibleShops();
        $eligibleShops = $allEligibleShops->take(6);
        $pendingOwnedShopEntries = ShopAccountingEntry::query()
            ->whereIn('shop_id', $allEligibleShops->pluck('id'))
            ->whereDate('business_date', $date)
            ->whereIn('status', ['submitted', 'recheck_required'])
            ->with(['shop', 'lines.category', 'submittedBy'])
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();
        $purchaserCashRows = $this->purchaserCashRows($date);

        return view('admin.accounting.index', compact(
            'date',
            'finance',
            'ownedMetrics',
            'eligibleShops',
            'pendingOwnedShopEntries',
            'purchaserCashRows',
        ));
    }

    public function dailySalesReport(Request $request): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::DashboardView);

        $date = Carbon::parse($request->input('date', today()->toDateString()));
        $statusFilter = (string) $request->input('status', 'all');
        $statusFilter = in_array($statusFilter, ['all', 'pending', 'settled'], true) ? $statusFilter : 'all';
        $clients = Client::query()->active()->orderBy('name')->get();
        $clientShops = $this->ownedShopAccountingService->eligibleShops();
        $salesScope = (string) $request->input('sales_scope', $request->boolean('only_owned_shops') ? 'client' : 'all');
        $salesScope = in_array($salesScope, ['all', 'direct', 'client'], true) ? $salesScope : 'all';
        $selectedClientId = $request->integer('client_id') ?: null;
        $selectedClient = $selectedClientId !== null ? $clients->firstWhere('id', $selectedClientId) : null;
        $selectedClientId = $selectedClient instanceof Client ? $selectedClient->id : null;
        $selectedClientShopId = $request->integer('client_shop_id') ?: $request->integer('owned_shop_id');
        $selectedClientShop = $clientShops->firstWhere('id', $selectedClientShopId);
        $selectedClientShopId = $selectedClientShop instanceof Shop ? $selectedClientShop->id : null;
        $shopIds = match (true) {
            $selectedClientShopId !== null => [$selectedClientShopId],
            $selectedClientId !== null => Shop::query()->where('client_id', $selectedClientId)->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            $salesScope === 'client' => $clientShops->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            $salesScope === 'direct' => Shop::query()->whereNull('client_id')->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            default => null,
        };
        $report = $this->financePillars->salesDailyDetail($date, $statusFilter, $shopIds);
        $pendingPaymentRequests = ShopInvoicePaymentRequest::query()
            ->where('status', 'pending')
            ->with(['shop', 'invoice', 'requestedBy'])
            ->latest('id')
            ->limit(12)
            ->get();
        $paymentRequestPreviews = $pendingPaymentRequests
            ->mapWithKeys(fn (ShopInvoicePaymentRequest $paymentRequest): array => [
                $paymentRequest->id => $this->shopInvoiceService->allocationPreviewForShopPayment($paymentRequest),
            ]);
        $pendingBillPaymentRequests = $pendingPaymentRequests
            ->filter(fn (ShopInvoicePaymentRequest $paymentRequest): bool => (float) ($paymentRequestPreviews->get($paymentRequest->id)['applied_amount'] ?? 0) > 0)
            ->values();
        $pendingClientBalanceRequests = $pendingPaymentRequests
            ->filter(fn (ShopInvoicePaymentRequest $paymentRequest): bool => (float) ($paymentRequestPreviews->get($paymentRequest->id)['credit_amount'] ?? 0) > 0)
            ->values();
        $clientBalanceCredits = ShopInvoicePaymentRequest::query()
            ->where('status', 'approved')
            ->where('credit_amount', '>', 0)
            ->with(['shop', 'invoice', 'requestedBy', 'reviewedBy'])
            ->latest('reviewed_at')
            ->latest('id')
            ->limit(12)
            ->get();

        return view('admin.accounting.daily_sales', compact(
            'date',
            'report',
            'statusFilter',
            'clients',
            'clientShops',
            'salesScope',
            'selectedClientId',
            'selectedClientShopId',
            'pendingPaymentRequests',
            'pendingBillPaymentRequests',
            'pendingClientBalanceRequests',
            'paymentRequestPreviews',
            'clientBalanceCredits',
        ));
    }

    public function updateShopInvoicePayment(ApproveShopInvoicePaymentRequest $request, ShopInvoice $invoice): RedirectResponse
    {
        return $this->redirectLegacyInvoicePaymentToFinanceV2($invoice);
    }

    public function applyShopInvoiceDiscount(ApplyShopInvoiceDiscountRequest $request, ShopInvoice $invoice): RedirectResponse
    {
        try {
            $this->shopInvoiceService->applyAdminDiscount(
                $invoice,
                $request->validated(),
                (int) $request->user()->id,
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return back()->with('success', 'Shop invoice discount applied and bill balance recalculated.');
    }

    public function reviewShopInvoicePaymentRequest(ReviewShopInvoicePaymentRequest $request, ShopInvoicePaymentRequest $paymentRequest): RedirectResponse
    {
        return $this->redirectPaymentRequestToFinanceV2($paymentRequest);
    }

    public function cashFlowReport(Request $request): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::DashboardView);

        $date = Carbon::parse($request->input('date', today()->toDateString()));
        $cashFlowReport = $this->financePillars->cashFlowReport($date);

        return view('admin.accounting.cash_flow', compact('date', 'cashFlowReport'));
    }

    public function loans(Request $request): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);

        $shops = $this->ownedShopAccountingService->eligibleShops();
        $shops->load(['client', 'loanCategorySettings.category']);
        $selectedShop = $request->filled('shop')
            ? $shops->firstWhere('code', (string) $request->input('shop'))
            : $shops->first();
        $availableCategories = $selectedShop instanceof Shop
            ? $this->ownedShopAccountingService->availableCategoriesForShop($selectedShop)
            : collect();
        $loanSettings = $selectedShop instanceof Shop
            ? $this->shopLoanService->settingsForShop($selectedShop)->keyBy('shop_accounting_category_id')
            : collect();
        $loanRows = $selectedShop instanceof Shop
            ? $this->shopLoanService->ledgerRows($selectedShop)
            : collect();
        $loanBalance = $selectedShop instanceof Shop
            ? $this->shopLoanService->approvedBalance($selectedShop)
            : 0.0;
        $shopSummaries = $shops->mapWithKeys(fn (Shop $shop): array => [
            $shop->id => [
                'balance' => $this->shopLoanService->approvedBalance($shop),
                'category_count' => $shop->loanCategorySettings->count(),
            ],
        ]);

        return view('admin.accounting.loans', [
            'shops' => $shops,
            'selectedShop' => $selectedShop,
            'availableCategories' => $availableCategories,
            'loanSettings' => $loanSettings,
            'loanRows' => $loanRows,
            'loanBalance' => $loanBalance,
            'shopSummaries' => $shopSummaries,
        ]);
    }

    public function updateLoanCategorySettings(Request $request, Shop $shop): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);
        $effects = $request->input('loan_effects', []);
        $effects = is_array($effects) ? $effects : [];
        $defaultDailyAmounts = $request->input('loan_default_daily_amounts', []);
        $defaultDailyAmounts = is_array($defaultDailyAmounts) ? $defaultDailyAmounts : [];

        $this->shopLoanService->syncCategorySettings($shop, $effects, $defaultDailyAmounts);

        return redirect()->route('admin.accounting.loans', ['shop' => $shop->code])
            ->with('success', 'Loan category settings updated.');
    }

    public function storeLoanEntry(Request $request, Shop $shop): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:cash_given,repayment'],
            'business_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->shopLoanService->recordCashMovement(
            $shop,
            (string) $validated['type'],
            Carbon::parse((string) $validated['business_date']),
            round((float) $validated['amount'], 2),
            trim((string) $validated['title']),
            filled($validated['description'] ?? null) ? trim((string) $validated['description']) : null,
            (int) $request->user()->id,
        );

        return redirect()->route('admin.accounting.loans', ['shop' => $shop->code])
            ->with('success', 'Loan cash movement recorded.');
    }

    public function companySummary(Request $request): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::DashboardView);

        $date = Carbon::parse($request->input('date', today()->toDateString()));
        $report = $this->companySummaryReports->report($date);

        return view('admin.accounting.company_summary', compact('date', 'report'));
    }

    public function mainAccount(Request $request): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::DashboardView);

        $date = Carbon::parse($request->input('date', today()->toDateString()));
        $report = $this->companyMainAccounts->report($date);
        $categories = $this->companyMainAccounts->categories();
        $incomeAccounts = $this->companyMainAccounts->ledgerAccountsForType('income');
        $expenseAccounts = $this->companyMainAccounts->ledgerAccountsForType('expense');

        return view('admin.accounting.main_account', compact(
            'date',
            'report',
            'categories',
            'incomeAccounts',
            'expenseAccounts',
        ));
    }

    public function storeMainAccountCategory(Request $request): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::EntryCreate);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:income,expense'],
            'name' => ['required', 'string', 'max:120'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        try {
            $this->companyMainAccounts->createCategory($validated);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['category' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('admin.accounting.main-account.index', ['date' => $request->input('date', today()->toDateString())])
            ->with('success', 'Main account category created.');
    }

    public function storeMainAccountEntry(Request $request): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::EntryCreate);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:income,expense'],
            'company_accounting_category_id' => ['required', 'integer', 'exists:company_accounting_categories,id'],
            'business_date' => ['required', 'date'],
            'payment_mode' => ['required', 'string', 'in:cash,bank,upi,cheque'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->companyMainAccounts->createEntry($validated, (int) $request->user()->id);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['entry' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('admin.accounting.main-account.index', ['date' => $validated['business_date']])
            ->with('success', 'Main account entry saved and posted to journal.');
    }

    public function reverseMainAccountEntry(Request $request, CompanyAccountingEntry $entry): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403, 'Only admin can reverse main account entries.');

        $validated = $request->validate([
            'reversal_note' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->companyMainAccounts->reverseEntry($entry, (int) $request->user()->id, $validated['reversal_note']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['reversal' => $exception->getMessage()])->withInput();
        }

        return back()->with('success', 'Main account entry reversed with an audit journal.');
    }

    public function cashFlowCalendar(Request $request): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::DashboardView);

        $date = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', (string) $request->input('month'))->startOfMonth()
            : Carbon::parse($request->input('date', today()->toDateString()));
        $cashFlowReport = $this->financePillars->cashFlowReport($date->copy()->endOfMonth());
        $cashFlowReport['selected_date'] = $date->toDateString();
        $cashFlowReport['selected_day_rows'] = $cashFlowReport['journal_rows']
            ->where('date', $date->toDateString())
            ->values();

        return view('admin.accounting.cash_flow_calendar', compact('date', 'cashFlowReport'));
    }

    public function exportCashFlowDayJournalExcel(Request $request): BinaryFileResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::ReportExport);

        $date = Carbon::parse($request->input('date', today()->toDateString()));
        $cashFlowReport = $this->financePillars->cashFlowReport($date);

        return Excel::download(
            new CashFlowDayJournalExport($date, $cashFlowReport['selected_day_rows']),
            'cash-flow-day-journal-'.$date->toDateString().'.xlsx',
        );
    }

    public function exportCashFlowDayJournalPdf(Request $request): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::ReportExport);

        $date = Carbon::parse($request->input('date', today()->toDateString()));
        $cashFlowReport = $this->financePillars->cashFlowReport($date);

        return view('admin.accounting.cash-flow-day-journal-pdf', [
            'date' => $date,
            'cashFlowReport' => $cashFlowReport,
            'rows' => $cashFlowReport['selected_day_rows'],
        ]);
    }

    public function vendorReports(Request $request): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::DashboardView);

        $date = Carbon::parse($request->input('date', today()->toDateString()));
        $report = $this->financePillars->vendorDailyDetail($date);

        return view('admin.accounting.vendor_reports', compact('date', 'report'));
    }

    public function ownedShopsIndex(Request $request): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);

        $shops = $this->ownedShopAccountingService->eligibleShops();
        $shops->load(['client', 'users:id,shop_id', 'latestAccountingEntry.submittedBy:id,name', 'latestClosingAccountingEntry']);
        $shops->loadSum('invoices as pending_balance_amount', 'balance_amount');
        $shops->loadCount([
            'accountingEntries as pending_updates_count' => fn ($query) => $query->where('status', 'submitted'),
            'accountingEntries as recheck_updates_count' => fn ($query) => $query->where('status', 'recheck_required'),
        ]);
        $availableShops = Shop::query()
            ->where(function ($query): void {
                $query->where('accounting_enabled', false)
                    ->orWhere('accounting_mode', '!=', 'owned');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'accounting_mode', 'accounting_enabled']);
        $clients = Client::query()->active()->orderBy('name')->get();

        return view('admin.accounting.owned_shops.index', compact('shops', 'availableShops', 'clients'));
    }

    public function clientDashboard(Request $request, Client $client): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);

        $startDate = Carbon::parse($request->input('start_date', today()->startOfMonth()->toDateString()))->startOfDay();
        $endDate = Carbon::parse($request->input('end_date', today()->toDateString()))->endOfDay();

        if ($endDate->lt($startDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        $client->load([
            'shops' => fn ($query) => $query
                ->orderBy('name'),
        ]);

        $shops = $client->shops;
        $shopIds = $shops->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $periodInvoices = ShopInvoice::query()
            ->whereIn('shop_id', $shopIds)
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->with(['shop', 'order'])
            ->latest('business_date')
            ->latest('id')
            ->get();
        $periodCredits = ShopCredit::query()
            ->approved()
            ->whereIn('shop_id', $shopIds)
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->with(['shop', 'cashMovementCategory'])
            ->latest('business_date')
            ->latest('id')
            ->get();
        $periodEntries = ShopAccountingEntry::query()
            ->whereIn('shop_id', $shopIds)
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->whereIn('status', ['submitted', 'recheck_required', 'approved', 'finalized'])
            ->with(['shop', 'lines.category'])
            ->latest('business_date')
            ->latest('id')
            ->get();
        $loanCategoryLabels = [
            'primary' => ShopCashMovementCategory::LOAN,
            'salary_advance' => ShopCashMovementCategory::ADVANCE_LOAN_FOR_SALARY,
        ];

        $summary = [
            'shop_count' => $shops->count(),
            'invoice_count' => $periodInvoices->count(),
            'invoice_collected' => round((float) $periodInvoices->sum('paid_amount'), 2),
            'invoice_pending' => round((float) $periodInvoices->sum('balance_amount'), 2),
            'expense_total' => round((float) $periodEntries
                ->flatMap(fn (ShopAccountingEntry $entry): Collection => $entry->lines)
                ->where('type', 'expense')
                ->sum('amount'), 2),
            'loan_given' => round((float) $periodCredits->where('type', 'in')->sum('amount'), 2),
        ];

        $invoicesByShopDate = $periodInvoices->groupBy(
            fn (ShopInvoice $invoice): string => $invoice->shop_id.'|'.$invoice->business_date?->toDateString()
        );
        $creditsByShopDate = $periodCredits->groupBy(
            fn (ShopCredit $credit): string => $credit->shop_id.'|'.$credit->business_date?->toDateString()
        );
        $entriesByShopDate = $periodEntries->groupBy(
            fn (ShopAccountingEntry $entry): string => $entry->shop_id.'|'.$entry->business_date?->toDateString()
        );
        $shopLookup = $shops->keyBy('id');

        $activityKeys = collect()
            ->merge($periodInvoices->map(fn (ShopInvoice $invoice): array => [
                'shop_id' => (int) $invoice->shop_id,
                'shop_name' => $invoice->shop?->name ?? '',
                'date' => $invoice->business_date?->toDateString(),
            ]))
            ->merge($periodCredits->map(fn (ShopCredit $credit): array => [
                'shop_id' => (int) $credit->shop_id,
                'shop_name' => $credit->shop?->name ?? '',
                'date' => $credit->business_date?->toDateString(),
            ]))
            ->merge($periodEntries->map(fn (ShopAccountingEntry $entry): array => [
                'shop_id' => (int) $entry->shop_id,
                'shop_name' => $entry->shop?->name ?? '',
                'date' => $entry->business_date?->toDateString(),
            ]))
            ->filter(fn (array $row): bool => filled($row['date']) && $shopLookup->has($row['shop_id']))
            ->unique(fn (array $row): string => $row['shop_id'].'|'.$row['date'])
            ->sort(function (array $left, array $right): int {
                return strcmp((string) $right['date'], (string) $left['date'])
                    ?: strcmp((string) $left['shop_name'], (string) $right['shop_name']);
            })
            ->values();

        $dailyRows = $activityKeys
            ->map(function (array $activity) use ($invoicesByShopDate, $creditsByShopDate, $entriesByShopDate, $loanCategoryLabels, $shopLookup): array {
                $key = $activity['shop_id'].'|'.$activity['date'];
                $shopInvoices = $invoicesByShopDate->get($key, collect());
                $shopCredits = $creditsByShopDate->get($key, collect());
                $shopEntries = $entriesByShopDate->get($key, collect());
                $balanceEntries = $shopEntries->where('entry_type', ShopAccountingEntry::TypeDaily);

                if ($balanceEntries->isEmpty()) {
                    $balanceEntries = $shopEntries;
                }

                $loanPrimary = $shopCredits
                    ->where('type', 'in')
                    ->filter(fn (ShopCredit $credit): bool => ($credit->cashMovementCategory?->name ?? ShopCashMovementCategory::LOAN) === $loanCategoryLabels['primary'])
                    ->sum('amount');
                $loanSalaryAdvance = $shopCredits
                    ->where('type', 'in')
                    ->filter(fn (ShopCredit $credit): bool => $credit->cashMovementCategory?->name === $loanCategoryLabels['salary_advance'])
                    ->sum('amount');
                $loanTotal = $shopCredits->where('type', 'in')->sum('amount');

                return [
                    'shop' => $shopLookup->get($activity['shop_id']),
                    'date' => Carbon::parse($activity['date']),
                    'invoice_collected' => round((float) $shopInvoices->sum('paid_amount'), 2),
                    'invoice_pending' => round((float) $shopInvoices->sum('balance_amount'), 2),
                    'expense_total' => round((float) $shopEntries
                        ->flatMap(fn (ShopAccountingEntry $entry): Collection => $entry->lines)
                        ->where('type', 'expense')
                        ->sum('amount'), 2),
                    'loan_primary' => round((float) $loanPrimary, 2),
                    'loan_salary_advance' => round((float) $loanSalaryAdvance, 2),
                    'loan_total' => round((float) $loanTotal, 2),
                    'opening_balance' => round((float) $balanceEntries->sum('opening_cash'), 2),
                    'closing_balance' => round((float) $balanceEntries->sum('closing_cash'), 2),
                ];
            })
            ->values();
        $clientOptions = Client::query()->active()->orderBy('name')->get();

        return view('admin.accounting.clients.show', [
            'client' => $client,
            'clientOptions' => $clientOptions,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'shops' => $shops,
            'dailyRows' => $dailyRows,
            'loanCategoryLabels' => $loanCategoryLabels,
            'summary' => $summary,
        ]);
    }

    public function clientsReport(Request $request): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);

        $activeTab = in_array((string) $request->input('tab', 'pending-bills'), ['pending-bills', 'approvals', 'history'], true)
            ? (string) $request->input('tab', 'pending-bills')
            : 'pending-bills';
        $startDate = Carbon::parse($request->input('start_date', today()->startOfMonth()->toDateString()))->startOfDay();
        $endDate = Carbon::parse($request->input('end_date', today()->toDateString()))->endOfDay();

        if ($endDate->lt($startDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        $shops = $this->ownedShopAccountingService->eligibleShops();
        $shopIds = $shops->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $shopLookup = $shops->keyBy('id');
        $activityKeys = collect();

        if ($shopIds !== []) {
            $activityKeys = $activityKeys
                ->merge(ShopAccountingEntry::query()
                    ->whereIn('shop_id', $shopIds)
                    ->whereDate('business_date', '>=', $startDate)
                    ->whereDate('business_date', '<=', $endDate)
                    ->get(['shop_id', 'business_date'])
                    ->map(fn (ShopAccountingEntry $entry): array => [
                        'shop_id' => (int) $entry->shop_id,
                        'date' => $entry->business_date?->toDateString(),
                    ]))
                ->merge(ShopInvoice::query()
                    ->whereIn('shop_id', $shopIds)
                    ->whereDate('business_date', '>=', $startDate)
                    ->whereDate('business_date', '<=', $endDate)
                    ->get(['shop_id', 'business_date'])
                    ->map(fn (ShopInvoice $invoice): array => [
                        'shop_id' => (int) $invoice->shop_id,
                        'date' => $invoice->business_date?->toDateString(),
                    ]))
                ->merge(ShopCredit::query()
                    ->approved()
                    ->whereIn('shop_id', $shopIds)
                    ->whereDate('business_date', '>=', $startDate)
                    ->whereDate('business_date', '<=', $endDate)
                    ->get(['shop_id', 'business_date'])
                    ->map(fn (ShopCredit $credit): array => [
                        'shop_id' => (int) $credit->shop_id,
                        'date' => $credit->business_date?->toDateString(),
                    ]));
        }

        $cashFlowRows = $activityKeys
            ->filter(fn (array $row): bool => filled($row['date']) && $shopLookup->has($row['shop_id']))
            ->unique(fn (array $row): string => $row['shop_id'].'|'.$row['date'])
            ->map(function (array $row) use ($shopLookup): array {
                /** @var Shop $shop */
                $shop = $shopLookup->get($row['shop_id']);
                $date = Carbon::parse($row['date']);
                $summary = $this->ownedShopAccountingService->receiptSummaryForDate($shop, $date);

                return [
                    'shop' => $shop,
                    'date' => $date,
                    'cashbook_total' => (float) $summary['total_income'],
                    'invoice_bill_amount' => (float) $summary['approved_delivery_bill'],
                    'approved_payment' => (float) $summary['payment_to_company'],
                    'closing_balance' => (float) ($summary['entered_closing'] ?? $summary['expected_closing']),
                    'pending_extra' => (float) $summary['to_be_paid_to_company'],
                ];
            })
            ->filter(fn (array $row): bool => $row['pending_extra'] > 0)
            ->sort(function (array $left, array $right): int {
                return strcmp($right['date']->toDateString(), $left['date']->toDateString())
                    ?: strcmp($left['shop']->name, $right['shop']->name);
            })
            ->values();

        $paymentApprovals = ShopInvoicePaymentRequest::query()
            ->whereIn('shop_id', $shopIds)
            ->where('status', 'pending')
            ->with(['shop.client', 'invoice', 'requestedBy'])
            ->latest('id')
            ->paginate(10, ['*'], 'approvals_page')
            ->withQueryString();
        $paymentHistory = ShopInvoicePaymentRequest::query()
            ->whereIn('shop_id', $shopIds)
            ->whereIn('status', ['approved', 'rejected'])
            ->with(['shop.client', 'invoice', 'requestedBy', 'reviewedBy', 'allocations.invoice'])
            ->latest('reviewed_at')
            ->latest('id')
            ->paginate(10, ['*'], 'history_page')
            ->withQueryString();
        $pendingBills = $this->paginateCollection($cashFlowRows, $request, 'pending_bills_page', 10);
        $summary = [
            'pending_shop_count' => $cashFlowRows->pluck('shop.id')->unique()->count(),
            'pending_extra' => round((float) $cashFlowRows->sum('pending_extra'), 2),
            'pending_approvals' => $paymentApprovals->total(),
            'history_count' => $paymentHistory->total(),
        ];

        return view('admin.accounting.clients.report', compact(
            'activeTab',
            'startDate',
            'endDate',
            'pendingBills',
            'paymentApprovals',
            'paymentHistory',
            'summary',
        ));
    }

    public function clientsCategoryReport(Request $request): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);

        $startDate = Carbon::parse($request->input('start_date', today()->startOfMonth()->toDateString()))->startOfDay();
        $endDate = Carbon::parse($request->input('end_date', today()->toDateString()))->endOfDay();

        if ($endDate->lt($startDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        $shops = $this->ownedShopAccountingService->eligibleShops();
        $shopIds = $shops->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $categoryRows = collect();

        if ($shopIds !== []) {
            $categoryRows = DB::table('shop_accounting_entry_lines as lines')
                ->join('shop_accounting_entries as entries', 'entries.id', '=', 'lines.shop_accounting_entry_id')
                ->join('shop_accounting_categories as categories', 'categories.id', '=', 'lines.shop_accounting_category_id')
                ->whereIn('entries.shop_id', $shopIds)
                ->whereDate('entries.business_date', '>=', $startDate)
                ->whereDate('entries.business_date', '<=', $endDate)
                ->whereIn('entries.status', ['submitted', 'recheck_required', 'approved'])
                ->whereIn('lines.type', ['income', 'expense'])
                ->groupBy('lines.type', 'categories.name', 'categories.purpose')
                ->orderByRaw("CASE lines.type WHEN 'income' THEN 0 WHEN 'expense' THEN 1 ELSE 2 END")
                ->orderBy('categories.name')
                ->get([
                    'lines.type',
                    'categories.name as category_name',
                    'categories.purpose',
                    DB::raw('COUNT(*) as line_count'),
                    DB::raw('COUNT(DISTINCT entries.id) as entry_count'),
                    DB::raw('COUNT(DISTINCT entries.shop_id) as shop_count'),
                    DB::raw('SUM(lines.amount) as total_amount'),
                    DB::raw("SUM(CASE WHEN entries.status = 'approved' THEN lines.amount ELSE 0 END) as approved_amount"),
                    DB::raw("SUM(CASE WHEN entries.status = 'submitted' THEN lines.amount ELSE 0 END) as submitted_amount"),
                    DB::raw("SUM(CASE WHEN entries.status = 'recheck_required' THEN lines.amount ELSE 0 END) as recheck_amount"),
                ])
                ->map(fn (object $row): array => [
                    'type' => (string) $row->type,
                    'category_name' => (string) $row->category_name,
                    'purpose' => $row->purpose !== null ? (string) $row->purpose : null,
                    'line_count' => (int) $row->line_count,
                    'entry_count' => (int) $row->entry_count,
                    'shop_count' => (int) $row->shop_count,
                    'total_amount' => round((float) $row->total_amount, 2),
                    'approved_amount' => round((float) $row->approved_amount, 2),
                    'submitted_amount' => round((float) $row->submitted_amount, 2),
                    'recheck_amount' => round((float) $row->recheck_amount, 2),
                ]);
        }

        $incomeRows = $categoryRows->where('type', 'income')->values();
        $expenseRows = $categoryRows->where('type', 'expense')->values();
        $summary = [
            'income_total' => round((float) $incomeRows->sum('total_amount'), 2),
            'expense_total' => round((float) $expenseRows->sum('total_amount'), 2),
            'net_total' => round((float) $incomeRows->sum('total_amount') - (float) $expenseRows->sum('total_amount'), 2),
            'category_count' => $categoryRows->count(),
            'shop_count' => $shops->count(),
        ];

        return view('admin.accounting.clients.category-report', compact(
            'startDate',
            'endDate',
            'incomeRows',
            'expenseRows',
            'summary',
        ));
    }

    public function storeOwnedShop(Request $request): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);

        $validated = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'accounting_mode' => ['required', 'string', 'in:owned'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'client_name' => ['nullable', 'string', 'max:120'],
            'reserve_amount' => ['nullable', 'numeric', 'min:0'],
            'default_petty_cash_amount' => ['nullable', 'numeric'],
        ]);

        /** @var Shop $shop */
        $shop = Shop::query()->findOrFail($validated['shop_id']);

        if ($shop->isOwnedAccountingEnabled()) {
            return redirect()->route('admin.accounting.owned-shops.show', ['shop' => $shop->code])
                ->with('warning', 'This shop is already enabled for owned-shop accounting.');
        }

        $reserveAmount = round((float) ($validated['reserve_amount'] ?? 0), 2);
        $defaultPettyCashAmount = round((float) ($validated['default_petty_cash_amount'] ?? 0), 2);
        $client = $this->resolveClientForShop($validated);

        DB::transaction(function () use ($request, $shop, $validated, $reserveAmount, $defaultPettyCashAmount, $client): void {
            $shop->update([
                'accounting_enabled' => true,
                'accounting_mode' => $validated['accounting_mode'],
                'client_id' => $client->id,
                'reserve_amount' => $reserveAmount,
                'default_petty_cash_amount' => $defaultPettyCashAmount,
            ]);

            if ($reserveAmount > 0) {
                $this->recordReserveAdjustment($shop, 0.0, $reserveAmount, today(), $request->user()?->id);
            }
        });

        return redirect()->route('admin.accounting.owned-shops.show', ['shop' => $shop->code])
            ->with('success', 'Client accounting enabled for '.$shop->name.' under '.$client->name.'.');
    }

    public function updateOwnedShop(Request $request, Shop $shop): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);

        $validated = $request->validate([
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'client_name' => ['nullable', 'string', 'max:120'],
            'reserve_amount' => ['nullable', 'numeric', 'min:0'],
            'default_petty_cash_amount' => ['nullable', 'numeric'],
            'business_date' => ['required', 'date'],
        ]);

        $client = $this->resolveClientForShop($validated);
        $previousReserveAmount = round((float) $shop->reserve_amount, 2);
        $newReserveAmount = round((float) ($validated['reserve_amount'] ?? 0), 2);
        $businessDate = Carbon::parse((string) $validated['business_date']);

        DB::transaction(function () use ($request, $shop, $client, $previousReserveAmount, $newReserveAmount, $validated, $businessDate): void {
            $shop->update([
                'client_id' => $client->id,
                'reserve_amount' => $newReserveAmount,
                'default_petty_cash_amount' => round((float) ($validated['default_petty_cash_amount'] ?? 0), 2),
            ]);

            $this->recordReserveAdjustment($shop, $previousReserveAmount, $newReserveAmount, $businessDate, $request->user()?->id);
        });

        return redirect()->route('admin.accounting.owned-shops.index')
            ->with('success', $shop->name.' client accounting settings updated.');
    }

    public function destroyOwnedShop(Request $request, Shop $shop): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);

        $shop->update([
            'accounting_enabled' => false,
            'accounting_mode' => 'regular',
            'client_id' => null,
        ]);

        return redirect()->route('admin.accounting.owned-shops.index')
            ->with('warning', $shop->name.' removed from client accounting. Existing shop records were kept.');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveClientForShop(array $validated): Client
    {
        $clientName = filled($validated['client_name'] ?? null) ? trim((string) $validated['client_name']) : null;

        if ($clientName !== null) {
            $clientCode = str($clientName)->upper()->replaceMatches('/[^A-Z0-9]+/', '_')->trim('_')->limit(40, '')->toString();

            return Client::query()->firstOrCreate(
                ['code' => $clientCode ?: 'CLIENT'],
                ['name' => $clientName, 'status' => 'active'],
            );
        }

        if (! empty($validated['client_id'])) {
            return Client::query()->findOrFail((int) $validated['client_id']);
        }

        return Client::query()->firstOrCreate(
            ['code' => 'AISHWARYA_VEG'],
            ['name' => 'Aishwarya Veg', 'status' => 'active'],
        );
    }

    public function updateReserveAmount(Request $request, Shop $shop): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);

        $validated = $request->validate([
            'reserve_amount' => ['required', 'numeric', 'min:0'],
            'business_date' => ['required', 'date'],
        ]);

        $previousReserveAmount = round((float) $shop->reserve_amount, 2);
        $newReserveAmount = round((float) $validated['reserve_amount'], 2);
        $businessDate = Carbon::parse($validated['business_date']);

        DB::transaction(function () use ($request, $shop, $previousReserveAmount, $newReserveAmount, $businessDate): void {
            $shop->update([
                'reserve_amount' => $newReserveAmount,
            ]);

            $this->recordReserveAdjustment($shop, $previousReserveAmount, $newReserveAmount, $businessDate, $request->user()?->id);
        });

        return redirect()->route('admin.accounting.owned-shops.show', [
            'shop' => $shop->code,
            'tab' => 'cashbook',
            'date' => $businessDate->toDateString(),
        ])
            ->with('success', 'Reserve amount updated.');
    }

    public function ownedShopShow(Request $request, Shop $shop): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);

        $selectedDate = Carbon::parse($request->input('date', today()->toDateString()));
        $legacyTab = in_array((string) $request->input('tab', ''), ['bills', 'cashbook'], true)
            ? (string) $request->input('tab')
            : null;
        $approvalStatuses = [
            'pending' => 'submitted',
            'recheck' => 'recheck_required',
            'approved' => 'approved',
        ];
        $startDate = Carbon::parse($request->input('start_date', $selectedDate->copy()->startOfMonth()->toDateString()));
        $endDate = Carbon::parse($request->input('end_date', $selectedDate->copy()->endOfMonth()->toDateString()));
        $entry = $this->ownedShopAccountingService->entryForDate($shop, $selectedDate);
        $suggestedOpeningBalance = $this->ownedShopAccountingService->previousClosingBalance($shop, $selectedDate);
        $selectedShopCredit = round((float) ShopCredit::query()
            ->approved()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', $selectedDate)
            ->get()
            ->sum(fn (ShopCredit $credit): float => $credit->shopSignedAmount()), 2);
        $selectedDeliveryExpense = $this->ownedShopAccountingService->approvedDeliveryBillTotalForDate($shop, $selectedDate);
        $receiptSummary = $this->ownedShopAccountingService->receiptSummary($entry, $suggestedOpeningBalance, $selectedShopCredit, $selectedDeliveryExpense);
        $availableCategories = $this->ownedShopAccountingService->availableCategoriesForShop($shop);
        $loanBalance = $this->shopLoanService->approvedBalance($shop);
        $billingInvoices = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->with(['order', 'paymentRequests' => fn ($query) => $query->latest('id')])
            ->latest('business_date')
            ->latest('id')
            ->paginate(12, ['*'], 'bills_page');
        $paymentRequests = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shop->id)
            ->with(['invoice', 'requestedBy', 'reviewedBy'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest('id')
            ->paginate(12, ['*'], 'payment_requests_page');
        $paymentRequestPreviews = $paymentRequests
            ->getCollection()
            ->mapWithKeys(fn (ShopInvoicePaymentRequest $paymentRequest): array => [
                $paymentRequest->id => $this->shopInvoiceService->allocationPreviewForShopPayment($paymentRequest),
            ]);
        $approvalEntriesByTab = collect($approvalStatuses)
            ->mapWithKeys(fn (string $status, string $key): array => [
                $key => ShopAccountingEntry::query()
                    ->where('shop_id', $shop->id)
                    ->where('status', $status)
                    ->whereDate('business_date', '>=', $startDate)
                    ->whereDate('business_date', '<=', $endDate)
                    ->with(['lines.category', 'submittedBy', 'reviewedBy'])
                    ->latest('business_date')
                    ->latest('id')
                    ->limit(20)
                    ->get(),
            ]);

        $requestedApprovalTab = (string) $request->input('approval_tab', '');
        if (in_array($requestedApprovalTab, ['pending', 'approved', 'recheck'], true)
            && $approvalEntriesByTab->get($requestedApprovalTab, collect())->isNotEmpty()) {
            $approvalTab = $requestedApprovalTab;
        } elseif ($approvalEntriesByTab->get('pending', collect())->isNotEmpty()) {
            $approvalTab = 'pending';
        } elseif ($approvalEntriesByTab->get('recheck', collect())->isNotEmpty()) {
            $approvalTab = 'recheck';
        } else {
            $approvalTab = 'approved';
        }

        $pendingPaymentRequestCount = (int) $paymentRequests->getCollection()
            ->where('status', 'pending')
            ->count();

        $hasActionableApprovals = $approvalEntriesByTab->get('pending', collect())->isNotEmpty()
            || $approvalEntriesByTab->get('recheck', collect())->isNotEmpty();

        $requestedSection = (string) $request->input('section', '');
        $defaultSection = match (true) {
            in_array($requestedSection, ['approve', 'cashbook', 'bills'], true) => $requestedSection,
            $legacyTab === 'bills' => 'bills',
            $legacyTab === 'cashbook' => $hasActionableApprovals ? 'approve' : 'cashbook',
            $hasActionableApprovals || $pendingPaymentRequestCount > 0 => 'approve',
            default => 'cashbook',
        };

        $periodInvoices = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->get(['id', 'shop_id', 'business_date', 'final_total', 'paid_amount', 'balance_amount']);
        $periodEntries = ShopAccountingEntry::query()
            ->where('shop_id', $shop->id)
            ->whereIn('status', ['submitted', 'recheck_required', 'approved', 'finalized'])
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->with(['lines:id,shop_accounting_entry_id,type,amount,cash_effect'])
            ->get();
        $periodCredits = ShopCredit::query()
            ->approved()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->get();
        $periodPayrollPayments = ShopStaffPayment::query()
            ->where('shop_id', $shop->id)
            ->whereDate('paid_on', '>=', $startDate)
            ->whereDate('paid_on', '<=', $endDate)
            ->get();
        $analytics = $this->ownedShopAnalytics($periodInvoices, $periodEntries, $periodCredits, $periodPayrollPayments);
        $pettyCashBalance = (float) ($analytics['cards']['closing_balance'] ?? 0);

        $incomeTotal = $entry instanceof ShopAccountingEntry
            ? round((float) $entry->lines->where('type', 'income')->sum('amount'), 2)
            : 0.0;
        $expenseTotal = $entry instanceof ShopAccountingEntry
            ? round((float) $entry->lines->where('type', 'expense')->sum('amount'), 2)
            : 0.0;

        return view('admin.accounting.owned_shops.show', [
            'shop' => $shop->loadMissing(['client']),
            'tab' => $legacyTab ?? 'cashbook',
            'defaultSection' => $defaultSection,
            'approvalTab' => $approvalTab,
            'approvalEntriesByTab' => $approvalEntriesByTab,
            'selectedDate' => $selectedDate,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'entry' => $entry,
            'availableCategories' => $availableCategories,
            'billingInvoices' => $billingInvoices,
            'paymentRequests' => $paymentRequests,
            'paymentRequestPreviews' => $paymentRequestPreviews,
            'pendingPaymentRequestCount' => $pendingPaymentRequestCount,
            'pettyCashBalance' => $pettyCashBalance,
            'analytics' => $analytics,
            'incomeTotal' => $incomeTotal,
            'expenseTotal' => $expenseTotal,
            'netAmount' => round($incomeTotal - $expenseTotal, 2),
            'suggestedOpeningBalance' => $suggestedOpeningBalance,
            'receiptSummary' => $receiptSummary,
            'loanBalance' => $loanBalance,
        ]);
    }

    public function updatePettyCashSettings(UpdateShopPettyCashSettingsRequest $request, Shop $shop): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);
        $validated = $request->validated();

        $shop->update([
            'default_petty_cash_amount' => round((float) $validated['default_petty_cash_amount'], 2),
        ]);

        return back()->with('success', 'Default opening balance updated.');
    }

    public function ownedShopCategories(Request $request, Shop $shop): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);
        $availableCategories = $this->ownedShopAccountingService->availableCategoriesForShop($shop)
            ->loadCount('entryLines');

        return view('admin.accounting.owned_shops.categories', [
            'shop' => $shop,
            'globalCategories' => $availableCategories->whereNull('shop_id')->values(),
            'shopCategories' => $availableCategories->where('shop_id', $shop->id)->values(),
        ]);
    }

    public function storeCategory(StoreShopAccountingCategoryRequest $request, Shop $shop): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);

        $validated = $request->validated();
        $targetShopId = $validated['scope'] === 'global' ? null : $shop->id;

        if ($this->categoryNameExists($validated['type'], trim((string) $validated['name']), $targetShopId)) {
            return back()->withErrors(['name' => 'A category with this name already exists for the selected scope.'])->withInput();
        }

        ShopAccountingCategory::query()->create([
            'shop_id' => $targetShopId,
            'type' => $validated['type'],
            'cash_effect' => (bool) ($validated['cash_effect'] ?? true),
            'purpose' => (string) ($validated['purpose'] ?? 'custom'),
            'name' => trim($validated['name']),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.accounting.owned-shops.categories.index', ['shop' => $shop->code])
            ->with('success', 'Accounting category created.');
    }

    public function updateCategory(StoreShopAccountingCategoryRequest $request, Shop $shop, ShopAccountingCategory $category): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);
        $this->ensureCategoryBelongsToCategoryPage($category, $shop);

        $validated = $request->validated();
        $targetShopId = $validated['scope'] === 'global' ? null : $shop->id;

        if ($this->categoryNameExists($validated['type'], trim((string) $validated['name']), $targetShopId, $category->id)) {
            return back()->withErrors(['name' => 'A category with this name already exists for the selected scope.'])->withInput();
        }

        $category->update([
            'shop_id' => $targetShopId,
            'type' => $validated['type'],
            'cash_effect' => (bool) ($validated['cash_effect'] ?? false),
            'purpose' => (string) ($validated['purpose'] ?? 'custom'),
            'name' => trim((string) $validated['name']),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return redirect()->route('admin.accounting.owned-shops.categories.index', ['shop' => $shop->code])
            ->with('success', 'Accounting category updated.');
    }

    public function destroyCategory(Request $request, Shop $shop, ShopAccountingCategory $category): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);
        $this->ensureCategoryBelongsToCategoryPage($category, $shop);

        if ($category->entryLines()->exists()) {
            return back()->withErrors(['category' => 'This category is already used in shop entries and cannot be deleted.']);
        }

        $category->delete();

        return redirect()->route('admin.accounting.owned-shops.categories.index', ['shop' => $shop->code])
            ->with('success', 'Accounting category deleted.');
    }

    private function categoryNameExists(string $type, string $name, ?int $shopId, ?int $ignoreCategoryId = null): bool
    {
        return ShopAccountingCategory::query()
            ->where('type', $type)
            ->where('name', $name)
            ->when(
                $shopId === null,
                fn ($query) => $query->whereNull('shop_id'),
                fn ($query) => $query->where('shop_id', $shopId)
            )
            ->when($ignoreCategoryId !== null, fn ($query) => $query->whereKeyNot($ignoreCategoryId))
            ->exists();
    }

    private function ensureCategoryBelongsToCategoryPage(ShopAccountingCategory $category, Shop $shop): void
    {
        abort_unless($category->shop_id === null || (int) $category->shop_id === (int) $shop->id, 404);
    }

    public function storeEntry(StoreShopAccountingEntryRequest $request, Shop $shop): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);

        try {
            $entry = $this->ownedShopAccountingService->saveEntry($shop, $request->validated(), (int) $request->user()->id);
            $this->markAdminSubmittedEntry($entry, (int) $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('admin.accounting.owned-shops.show', [
            'shop' => $shop->code,
            'tab' => 'cashbook',
            'approval_tab' => 'pending',
            'date' => $entry->business_date->toDateString(),
        ])->with('success', 'Daily accounting entry saved.');
    }

    public function updateEntry(UpdateShopAccountingEntryRequest $request, Shop $shop, ShopAccountingEntry $entry): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);
        abort_unless($entry->shop_id === $shop->id, 404);

        try {
            $entry = $this->ownedShopAccountingService->saveEntry($shop, $request->validated(), (int) $request->user()->id, $entry);
            $this->markAdminSubmittedEntry($entry, (int) $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('admin.accounting.owned-shops.show', [
            'shop' => $shop->code,
            'tab' => 'cashbook',
            'approval_tab' => 'pending',
            'date' => $entry->business_date->toDateString(),
        ])->with('success', 'Daily accounting entry updated.');
    }

    public function clearEntry(Request $request, Shop $shop, ShopAccountingEntry $entry): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);
        abort_unless($entry->shop_id === $shop->id, 404);

        $request->validate([
            'confirmation' => ['required', 'string', 'in:CLEAR CASHBOOK'],
        ]);

        $entryDate = $entry->business_date?->copy() ?? today();

        try {
            $this->ownedShopAccountingService->clearCashbookEntry($entry, (int) $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('admin.accounting.owned-shops.show', [
            'shop' => $shop->code,
            'tab' => 'cashbook',
            'approval_tab' => 'pending',
            'date' => $entryDate->toDateString(),
        ])->with('success', 'Cashbook cleared. Invoices, bills, payment requests, and loan cash movements were skipped.');
    }

    private function markAdminSubmittedEntry(ShopAccountingEntry $entry, int $userId): void
    {
        if ($entry->status !== 'submitted') {
            return;
        }

        $entry->forceFill([
            'submitted_by' => $entry->submitted_by ?? $userId,
            'submitted_at' => $entry->submitted_at ?? now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
        ])->save();
    }

    public function reviewEntry(ReviewShopAccountingEntryRequest $request, Shop $shop, ShopAccountingEntry $entry): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::EntryReview);
        $shop = $this->loadEligibleShop($shop);
        abort_unless($entry->shop_id === $shop->id, 404);

        try {
            $entry = $this->ownedShopAccountingService->reviewEntry(
                $entry,
                $request->validated('decision'),
                (int) $request->user()->id,
                $request->validated('admin_note'),
                $request->validated('line_reviews', []),
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        $message = match ($entry->status) {
            'approved' => 'Daily accounting approved.',
            'recheck_required' => 'Daily accounting sent back for recheck.',
            default => 'Line item reviewed. Remaining items are still pending.',
        };

        return redirect()->route('admin.accounting.owned-shops.show', [
            'shop' => $shop->code,
            'tab' => 'cashbook',
            'approval_tab' => match ($entry->status) {
                'approved' => 'approved',
                'recheck_required' => 'recheck',
                default => 'pending',
            },
            'date' => $entry->business_date->toDateString(),
        ])->with($entry->status === 'recheck_required' ? 'warning' : 'success', $message);
    }

    public function updateEntryLine(Request $request, Shop $shop, ShopAccountingEntry $entry, ShopAccountingEntryLine $line): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);
        abort_unless($entry->shop_id === $shop->id && $line->shop_accounting_entry_id === $entry->id, 404);

        $validated = $request->validate([
            'shop_accounting_category_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $category = $this->ownedShopAccountingService
            ->availableCategoriesForShop($shop)
            ->firstWhere('id', (int) $validated['shop_accounting_category_id']);

        if (! $category instanceof ShopAccountingCategory) {
            return back()->withErrors([
                'shop_accounting_category_id' => 'Choose an active category available to this shop.',
            ])->withInput();
        }

        DB::transaction(function () use ($line, $category, $validated, $shop, $entry, $request): void {
            $line->forceFill([
                'shop_accounting_category_id' => $category->id,
                'type' => $category->type,
                'cash_effect' => $category->cash_effect,
                'is_loan_entry' => $category->type === 'expense' && (bool) $line->is_loan_entry,
                'amount' => round((float) $validated['amount'], 2),
                'description' => filled($validated['description'] ?? null) ? trim((string) $validated['description']) : null,
            ])->save();

            $this->ownedShopAccountingService->syncStoredClosingBalancesFromDate(
                $shop,
                $entry->business_date ?? today(),
                (int) $request->user()->id,
            );
        });

        return redirect()->route('admin.accounting.owned-shops.show', [
            'shop' => $shop->code,
            'tab' => 'cashbook',
            'approval_tab' => match ($entry->status) {
                'approved', 'finalized' => 'approved',
                'recheck_required' => 'recheck',
                default => 'pending',
            },
            'date' => $entry->business_date?->toDateString(),
        ])->with('success', 'Submitted item updated. Shop cash balance, loan balance, and shop owner ledger now use the revised value.');
    }

    public function closePeriod(CloseShopAccountingPeriodRequest $request, Shop $shop): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);

        try {
            $this->ownedShopAccountingService->closePeriod(
                shop: $shop,
                periodStart: Carbon::parse($request->validated('period_start')),
                periodEnd: Carbon::parse($request->validated('period_end')),
                userId: (int) $request->user()->id,
                notes: $request->validated('notes'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['period_closure' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('admin.accounting.owned-shops.show', [
            'shop' => $shop->code,
            'date' => Carbon::parse($request->validated('period_end'))->toDateString(),
        ])->with('success', 'Accounting period closed successfully.');
    }

    public function updateDailyBillPayment(UpdateDailyBillPaymentRequest $request, Shop $shop, ShopInvoice $invoice): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);
        abort_unless($invoice->shop_id === $shop->id, 404);

        return $this->redirectLegacyInvoicePaymentToFinanceV2($invoice);
    }

    public function reviewOwnedShopPaymentRequest(ReviewOwnedShopPaymentRequest $request, Shop $shop, ShopInvoicePaymentRequest $paymentRequest): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);
        abort_unless($paymentRequest->shop_id === $shop->id, 404);

        return $this->redirectPaymentRequestToFinanceV2($paymentRequest);
    }

    private function redirectLegacyInvoicePaymentToFinanceV2(ShopInvoice $invoice): RedirectResponse
    {
        return redirect()
            ->route('admin.finance-v2.payments.create', [
                'date' => $invoice->business_date?->toDateString() ?? today()->toDateString(),
                'shop_id' => $invoice->shop_id,
                'requested_amount' => max(0, round((float) $invoice->balance_amount, 2)),
            ])
            ->with('warning', 'Payment approvals are handled from Finance V2 Payments.');
    }

    private function redirectPaymentRequestToFinanceV2(ShopInvoicePaymentRequest $paymentRequest): RedirectResponse
    {
        return redirect()
            ->route('admin.finance-v2.payments.show', [
                'paymentRequest' => $paymentRequest,
                'date' => $paymentRequest->payment_date?->toDateString() ?? today()->toDateString(),
            ])
            ->with('warning', 'Payment approvals are handled from Finance V2 Payments.');
    }

    public function generateDailyWorkflowInvoices(Request $request): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::InvoiceGenerate);

        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $summary = $this->shopInvoiceService->generateForBusinessDate($validated['date'], (int) $request->user()->id);
        $skipped = count($summary['skipped']);

        return redirect()->route('admin.accounting.index', ['date' => $validated['date']])
            ->with('success', 'Daily shop invoices generated for the selected date.')
            ->with('warning', $skipped > 0
                ? "Skipped {$skipped} order(s) because daily prices are missing."
                : null);
    }

    public function purchasersIndex(Request $request): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::PurchaserCashManage);

        $sort = (string) $request->input('sort', 'name');
        $direction = strtolower((string) $request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortableColumns = ['name', 'total_in', 'total_out', 'balance'];
        $reportTab = in_array($request->input('report_tab'), ['cash', 'procurement', 'other', 'summary'], true)
            ? (string) $request->input('report_tab')
            : 'cash';
        $fromDate = Carbon::parse($request->input('from_date', now()->startOfMonth()->toDateString()))->startOfDay();
        $toDate = Carbon::parse($request->input('to_date', now()->toDateString()))->endOfDay();
        $defaultPurchaser = $this->resolveDefaultPurchaserUser();
        $selectedPurchaserId = $request->integer('purchaser_id') ?: ($defaultPurchaser?->id ?: null);
        $selectedCategory = (string) $request->input('category', '');
        $procurementCategoryFilter = in_array($selectedCategory, array_keys(ProcurementExpense::categories()), true) ? $selectedCategory : '';
        $otherCategoryFilter = in_array($selectedCategory, array_keys(OtherExpense::categories()), true) ? $selectedCategory : '';

        if (! in_array($sort, $sortableColumns, true)) {
            $sort = 'name';
        }

        $purchaserOptions = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'purchaser'))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'public_uuid']);

        if ($defaultPurchaser && ! $purchaserOptions->contains(fn (User $u): bool => (int) $u->id === (int) $defaultPurchaser->id)) {
            $purchaserOptions->push($defaultPurchaser);
            $purchaserOptions = $purchaserOptions->sortBy('name')->values();
        }

        $allPurchasers = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'purchaser'))
            ->withSum(['purchaserCredits as total_in' => fn ($query) => $query->where('type', 'in')], 'amount')
            ->withSum(['purchaserCredits as total_out' => fn ($query) => $query->where('type', 'out')], 'amount')
            ->orderBy('name')
            ->get()
            ->map(function (User $purchaser): array {
                $totalIn = round((float) ($purchaser->total_in ?? 0), 2);
                $totalOut = round((float) ($purchaser->total_out ?? 0), 2);

                return [
                    'purchaser' => $purchaser,
                    'total_in' => $totalIn,
                    'total_out' => $totalOut,
                    'balance' => round($totalIn - $totalOut, 2),
                ];
            })
            ->sort(function (array $left, array $right) use ($sort, $direction): int {
                $leftValue = $left[$sort] ?? null;
                $rightValue = $right[$sort] ?? null;

                if ($leftValue === $rightValue) {
                    return 0;
                }

                $comparison = $leftValue <=> $rightValue;

                return $direction === 'desc' ? -$comparison : $comparison;
            })
            ->values();

        if ($defaultPurchaser && ! $allPurchasers->contains(fn (array $row): bool => (int) ($row['purchaser']?->id ?? 0) === (int) $defaultPurchaser->id)) {
            $defaultTotalIn = round((float) PurchaserCredit::query()->where('purchaser_id', $defaultPurchaser->id)->where('type', 'in')->sum('amount'), 2);
            $defaultTotalOut = round((float) PurchaserCredit::query()->where('purchaser_id', $defaultPurchaser->id)->where('type', 'out')->sum('amount'), 2);
            $allPurchasers->push([
                'purchaser' => $defaultPurchaser,
                'total_in' => $defaultTotalIn,
                'total_out' => $defaultTotalOut,
                'balance' => round($defaultTotalIn - $defaultTotalOut, 2),
            ]);

            $allPurchasers = $allPurchasers
                ->sort(function (array $left, array $right) use ($sort, $direction): int {
                    $leftValue = $left[$sort] ?? null;
                    $rightValue = $right[$sort] ?? null;

                    if ($leftValue === $rightValue) {
                        return 0;
                    }

                    $comparison = $leftValue <=> $rightValue;

                    return $direction === 'desc' ? -$comparison : $comparison;
                })
                ->values();
        }

        $totals = [
            'total_in' => round((float) $allPurchasers->sum('total_in'), 2),
            'total_out' => round((float) $allPurchasers->sum('total_out'), 2),
            'balance' => round((float) $allPurchasers->sum('balance'), 2),
        ];

        $purchasers = $this->paginateCollection($allPurchasers, $request, 'purchasers_page', 15)->withQueryString();

        $cashQuery = PurchaserCredit::query()
            ->with(['purchaser:id,name,email,public_uuid', 'purchaseInvoice:id,invoice_number,public_uuid,amount,payment_method,payment_status', 'creator:id,name'])
            ->whereBetween('business_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->when($selectedPurchaserId, fn ($query) => $query->where('purchaser_id', $selectedPurchaserId));

        $cashTotals = [
            'in' => round((float) (clone $cashQuery)->where('type', 'in')->sum('amount'), 2),
            'out' => round((float) (clone $cashQuery)->where('type', 'out')->sum('amount'), 2),
        ];
        $cashTotals['balance'] = round($cashTotals['in'] - $cashTotals['out'], 2);

        $cashTransactions = (clone $cashQuery)
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'cash_page')
            ->withQueryString();

        $companyBillQuery = PurchaseInvoice::query()
            ->with([
                'purchaserCart:id,user_id,supplier_id,business_date,cart_number',
                'purchaserCart.user:id,name,email,public_uuid',
                'supplier:id,name',
            ])
            ->whereHas('purchaserCart', function ($query) use ($fromDate, $toDate, $selectedPurchaserId): void {
                $query->whereBetween('business_date', [$fromDate->toDateString(), $toDate->toDateString()])
                    ->when($selectedPurchaserId, fn ($cartQuery) => $cartQuery->where('user_id', $selectedPurchaserId));
            })
            ->where(function ($query): void {
                $query->where(function ($paidQuery): void {
                    $paidQuery->where('payment_paid_by', 'company')
                        ->whereIn('payment_method', ['Cash', 'cash', 'Online', 'GPay', 'online', 'online_upi', 'upi', 'bank'])
                        ->where('paid_amount', '>', 0);
                })->orWhere(function ($creditQuery): void {
                    $creditQuery->where(function ($methodQuery): void {
                        $methodQuery->where('payment_method', 'Credit')
                            ->orWhere('payment_status', 'credit_pending_approval');
                    })->whereRaw('(amount - COALESCE(discount_amount, 0) - COALESCE(paid_amount, 0)) > 0');
                });
            });

        $allCompanyBillTransactions = (clone $companyBillQuery)
            ->orderByDesc(
                PurchaserCart::query()
                    ->select('business_date')
                    ->whereColumn('purchaser_carts.id', 'purchase_invoices.purchaser_cart_id')
                    ->limit(1)
            )
            ->orderByDesc('id')
            ->get()
            ->map(function (PurchaseInvoice $invoice): array {
                $netAmount = round((float) $invoice->amount - (float) $invoice->discount_amount, 2);
                $paidAmount = round((float) $invoice->paid_amount, 2);
                $pendingAmount = max(0, round($netAmount - $paidAmount, 2));
                $isCreditPending = strcasecmp((string) $invoice->payment_method, 'Credit') === 0
                    || $invoice->payment_status === 'credit_pending_approval';
                $isCompanyCashPaid = ! $isCreditPending
                    && strcasecmp((string) $invoice->payment_paid_by, 'company') === 0
                    && strcasecmp((string) $invoice->payment_method, 'cash') === 0
                    && $paidAmount > 0;

                return [
                    'invoice' => $invoice,
                    'date' => $invoice->purchaserCart?->business_date,
                    'purchaser' => $invoice->purchaserCart?->user,
                    'supplier' => $invoice->supplier,
                    'paid_amount' => $isCreditPending ? 0.0 : $paidAmount,
                    'pending_amount' => $isCreditPending ? $pendingAmount : 0.0,
                    'kind' => $isCreditPending ? 'Credit Pending' : ($isCompanyCashPaid ? 'Company Cash Paid' : 'Company Online Paid'),
                    'method' => $invoice->payment_method ?: ($isCompanyCashPaid ? 'Cash' : 'Online'),
                    'status' => str((string) ($invoice->payment_status ?: 'pending'))->replace('_', ' ')->title()->toString(),
                ];
            });

        $companyOnlinePaid = round((float) $allCompanyBillTransactions->sum('paid_amount'), 2);
        $companyCreditPending = round((float) $allCompanyBillTransactions->sum('pending_amount'), 2);
        $companyBillsByPurchaser = $allCompanyBillTransactions->groupBy(fn (array $row): int => (int) ($row['purchaser']?->id ?? 0));

        $companyBillTransactions = $this->paginateCollection($allCompanyBillTransactions, $request, 'company_bills_page', 15)->withQueryString();

        $procurementQuery = ProcurementExpense::query()
            ->with(['purchaser:id,name,email,public_uuid', 'companyAccountingEntry:id,journal_entry_id,reference,business_date,amount,status', 'companyAccountingEntry.journalEntry:id,reference'])
            ->whereBetween('expense_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->when($selectedPurchaserId, fn ($query) => $query->where('user_id', $selectedPurchaserId))
            ->when($procurementCategoryFilter !== '', fn ($query) => $query->where('category', $procurementCategoryFilter));

        $procurementTotals = [
            'amount' => round((float) (clone $procurementQuery)->sum('amount'), 2),
            'count' => (clone $procurementQuery)->count(),
        ];

        $categoryTotals = (clone $procurementQuery)
            ->select('category', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as rows_count'))
            ->groupBy('category')
            ->get()
            ->mapWithKeys(fn (ProcurementExpense $expense): array => [
                (string) $expense->category => [
                    'label' => $expense->categoryLabel(),
                    'amount' => round((float) $expense->total_amount, 2),
                    'count' => (int) $expense->rows_count,
                ],
            ]);

        $procurementTransactions = (clone $procurementQuery)
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'procurement_page')
            ->withQueryString();

        $otherExpenseQuery = OtherExpense::query()
            ->with(['purchaser:id,name,email,public_uuid', 'companyAccountingEntry:id,journal_entry_id,reference,business_date,amount,status', 'companyAccountingEntry.journalEntry:id,reference'])
            ->whereBetween('expense_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->when($selectedPurchaserId, fn ($query) => $query->where('user_id', $selectedPurchaserId))
            ->when($otherCategoryFilter !== '', fn ($query) => $query->where('category', $otherCategoryFilter));

        $otherExpenseTotals = [
            'amount' => round((float) (clone $otherExpenseQuery)->sum('amount'), 2),
            'count' => (clone $otherExpenseQuery)->count(),
        ];

        $otherCategoryTotals = (clone $otherExpenseQuery)
            ->select('category', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as rows_count'))
            ->groupBy('category')
            ->get()
            ->mapWithKeys(fn (OtherExpense $expense): array => [
                (string) $expense->category => [
                    'label' => $expense->categoryLabel(),
                    'amount' => round((float) $expense->total_amount, 2),
                    'count' => (int) $expense->rows_count,
                ],
            ]);

        $otherExpenseTransactions = (clone $otherExpenseQuery)
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'other_page')
            ->withQueryString();

        $cashByPurchaser = PurchaserCredit::query()
            ->select('purchaser_id')
            ->selectRaw("SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) as cash_in")
            ->selectRaw("SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END) as cash_out")
            ->selectRaw('MAX(business_date) as last_cash_date')
            ->whereBetween('business_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->when($selectedPurchaserId, fn ($query) => $query->where('purchaser_id', $selectedPurchaserId))
            ->groupBy('purchaser_id')
            ->get()
            ->keyBy('purchaser_id');

        $procurementByPurchaser = ProcurementExpense::query()
            ->select('user_id')
            ->selectRaw('SUM(amount) as procurement_total')
            ->selectRaw('MAX(expense_date) as last_procurement_date')
            ->whereBetween('expense_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->when($selectedPurchaserId, fn ($query) => $query->where('user_id', $selectedPurchaserId))
            ->when($procurementCategoryFilter !== '', fn ($query) => $query->where('category', $procurementCategoryFilter))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $otherExpensesByPurchaser = OtherExpense::query()
            ->select('user_id')
            ->selectRaw('SUM(amount) as other_expense_total')
            ->selectRaw('MAX(expense_date) as last_other_expense_date')
            ->whereBetween('expense_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->when($selectedPurchaserId, fn ($query) => $query->where('user_id', $selectedPurchaserId))
            ->when($otherCategoryFilter !== '', fn ($query) => $query->where('category', $otherCategoryFilter))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $allSummaryRows = $purchaserOptions
            ->when($selectedPurchaserId, fn (Collection $users) => $users->where('id', $selectedPurchaserId))
            ->map(function (User $purchaser) use ($cashByPurchaser, $procurementByPurchaser, $otherExpensesByPurchaser, $companyBillsByPurchaser): array {
                $cash = $cashByPurchaser->get($purchaser->id);
                $procurement = $procurementByPurchaser->get($purchaser->id);
                $otherExpense = $otherExpensesByPurchaser->get($purchaser->id);
                $companyBills = $companyBillsByPurchaser->get($purchaser->id, collect());
                $cashIn = round((float) ($cash?->cash_in ?? 0), 2);
                $cashOut = round((float) ($cash?->cash_out ?? 0), 2);
                $procurementTotal = round((float) ($procurement?->procurement_total ?? 0), 2);
                $otherExpenseTotal = round((float) ($otherExpense?->other_expense_total ?? 0), 2);
                $totalPurchaserExpenses = round($procurementTotal + $otherExpenseTotal, 2);
                $companyOnlineTotal = round((float) $companyBills->sum('paid_amount'), 2);
                $creditPendingTotal = round((float) $companyBills->sum('pending_amount'), 2);
                $lastDates = collect([$cash?->last_cash_date, $procurement?->last_procurement_date, $otherExpense?->last_other_expense_date])->filter();
                $lastCompanyDate = $companyBills
                    ->pluck('date')
                    ->filter()
                    ->map(fn (Carbon $date): string => $date->toDateString())
                    ->max();

                if ($lastCompanyDate) {
                    $lastDates->push($lastCompanyDate);
                }

                return [
                    'purchaser' => $purchaser,
                    'cash_in' => $cashIn,
                    'cash_out' => $cashOut,
                    'company_online' => $companyOnlineTotal,
                    'credit_pending' => $creditPendingTotal,
                    'procurement' => $procurementTotal,
                    'other_expenses' => $otherExpenseTotal,
                    'total_purchaser_expenses' => $totalPurchaserExpenses,
                    'company_out' => round($cashOut + $companyOnlineTotal + $totalPurchaserExpenses, 2),
                    'balance' => round($cashIn - $cashOut, 2),
                    'last_activity' => $lastDates->isNotEmpty() ? Carbon::parse((string) $lastDates->max()) : null,
                ];
            })
            ->filter(fn (array $row): bool => $row['cash_in'] > 0 || $row['cash_out'] > 0 || $row['company_online'] > 0 || $row['credit_pending'] > 0 || $row['total_purchaser_expenses'] > 0)
            ->sortByDesc(fn (array $row): string => $row['last_activity']?->toDateString() ?? '')
            ->values();

        $summaryRows = $this->paginateCollection($allSummaryRows, $request, 'summary_page', 15)->withQueryString();

        $reportTotals = [
            'cash_in' => $cashTotals['in'],
            'cash_out' => $cashTotals['out'],
            'company_online_out' => $companyOnlinePaid,
            'credit_pending' => $companyCreditPending,
            'procurement' => $procurementTotals['amount'],
            'other_expenses' => $otherExpenseTotals['amount'],
            'total_purchaser_expenses' => round($procurementTotals['amount'] + $otherExpenseTotals['amount'], 2),
            'company_total_out' => round($cashTotals['out'] + $companyOnlinePaid + $procurementTotals['amount'] + $otherExpenseTotals['amount'], 2),
            'balance' => $cashTotals['balance'],
        ];

        $reportFilters = [
            'tab' => $reportTab,
            'from_date' => $fromDate->toDateString(),
            'to_date' => $toDate->toDateString(),
            'purchaser_id' => $selectedPurchaserId,
            'category' => $selectedCategory,
        ];

        return view('admin.accounting.purchasers.index', compact(
            'purchasers',
            'totals',
            'sort',
            'direction',
            'purchaserOptions',
            'defaultPurchaser',
            'reportFilters',
            'reportTotals',
            'cashTransactions',
            'companyBillTransactions',
            'procurementTransactions',
            'otherExpenseTransactions',
            'procurementTotals',
            'otherExpenseTotals',
            'categoryTotals',
            'otherCategoryTotals',
            'summaryRows',
        ));
    }

    public function purchaserShow(Request $request, User $user): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::PurchaserCashManage);
        $defaultPurchaser = $this->resolveDefaultPurchaserUser();
        $isConfiguredDefaultPurchaser = $defaultPurchaser && (int) $defaultPurchaser->id === (int) $user->id;
        abort_unless($user->hasRole('purchaser') || $isConfiguredDefaultPurchaser, 404);

        $query = PurchaserCredit::query()
            ->where('purchaser_id', $user->id)
            ->with(['purchaseInvoice.supplier', 'creator']);

        // Search by invoice number or description
        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('purchaseInvoice', function ($q) use ($search) {
                        $q->where('invoice_number', 'like', "%{$search}%");
                    });
            });
        }

        // Search by vendor details linked to purchase invoices.
        if ($request->filled('vendor_search')) {
            $vendorSearch = $request->string('vendor_search')->trim();
            $query->whereHas('purchaseInvoice.supplier', function ($q) use ($vendorSearch) {
                $q->where('name', 'like', "%{$vendorSearch}%")
                    ->orWhere('contact', 'like', "%{$vendorSearch}%")
                    ->orWhere('mobile_number', 'like', "%{$vendorSearch}%");
            });
        }

        $credits = $query
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Calculate totals from all credits (not just paginated)
        $allCredits = PurchaserCredit::query()
            ->where('purchaser_id', $user->id)
            ->get();
        $totalIn = (float) $allCredits->where('type', 'in')->sum('amount');
        $totalOut = (float) $allCredits->where('type', 'out')->sum('amount');
        $balance = $totalIn - $totalOut;

        return view('admin.accounting.purchasers.show', compact('user', 'credits', 'totalIn', 'totalOut', 'balance', 'isConfiguredDefaultPurchaser'));
    }

    public function storePurchaserCredit(Request $request, User $user): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::PurchaserCashManage);
        $defaultPurchaser = $this->resolveDefaultPurchaserUser();
        $isConfiguredDefaultPurchaser = $defaultPurchaser && (int) $defaultPurchaser->id === (int) $user->id;
        abort_unless($user->hasRole('purchaser') || $isConfiguredDefaultPurchaser, 404);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'business_date' => ['required', 'date'],
        ]);

        $credit = PurchaserCredit::create([
            'purchaser_id' => $user->id,
            'type' => 'in',
            'amount' => (float) $validated['amount'],
            'description' => $validated['description'] ?: 'Cash / Credit from Green Leaf',
            'created_by' => auth()->id(),
            'business_date' => $validated['business_date'],
        ]);

        $this->journalService->recordPurchaserCredit($credit);

        return redirect()->route('admin.accounting.purchasers.show', $user->public_uuid)
            ->with('success', 'Credit added successfully.');
    }

    public function buyAsPurchaser(Request $request, User $user): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::PurchaserCashManage);
        $admin = $request->user();

        abort_unless($admin?->hasRole('admin'), 403);
        abort_unless($admin->hasRole('purchaser'), 403);
        abort_unless($user->hasRole('purchaser'), 404);
        abort_unless($user->is($admin), 403);

        return redirect()
            ->route('admin.accounting.purchasers.direct-purchase.create', ['date' => today()->toDateString()])
            ->with('success', 'Green Leaf Direct Purchase window opened for '.$admin->name.'.');
    }

    public function loginAsPurchaser(Request $request, User $user): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::PurchaserCashManage);
        $admin = $request->user();

        abort_unless($admin?->hasRole('admin'), 403);
        abort_unless($user->hasRole('purchaser'), 404);
        $this->impersonation->start($request, $admin, $user);

        return redirect()
            ->route('purchaser.vendors', ['date' => today()->toDateString()])
            ->with('success', 'Logged in as '.$user->name.' (purchaser view).');
    }

    public function stopPurchaserViewAsAdmin(Request $request): RedirectResponse
    {
        return $this->impersonation->stop($request);
    }

    private function resolveDefaultPurchaserUser(): ?User
    {
        $configuredId = (int) (BusinessSetting::query()->where('key', 'default_purchaser_user_id')->value('value') ?? 0);

        if ($configuredId > 0) {
            $configuredUser = User::query()->find($configuredId);
            if ($configuredUser) {
                return $configuredUser;
            }
        }

        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'purchaser'))
            ->orderBy('name')
            ->first(['id', 'name', 'email', 'public_uuid']);
    }

    private function ensureAccountingAccess(Request $request, string $permission): void
    {
        abort_unless(
            AccountingAccess::allows($request->user(), $permission),
            403,
            'Unauthorized access to admin accounting.'
        );
    }

    private function loadEligibleShop(Shop $shop): Shop
    {
        abort_unless($this->ownedShopAccountingService->isEligibleShop($shop), 404, 'This shop is not enabled for owned-shop accounting.');

        return $shop;
    }

    private function paginateCollection(Collection $items, Request $request, string $pageName, int $perPage): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query($pageName, 1));
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
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
     * @return Collection<int, array{
     *     purchaser:User,
     *     total_in:float,
     *     total_out:float,
     *     balance:float,
     *     today_in:float,
     *     today_out:float,
     *     today_balance:float,
     *     transaction_count:int
     * }>
     */
    private function purchaserCashRows(Carbon $date): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'purchaser'))
            ->withSum(['purchaserCredits as total_in' => fn ($query) => $query->where('type', 'in')], 'amount')
            ->withSum(['purchaserCredits as total_out' => fn ($query) => $query->where('type', 'out')], 'amount')
            ->withSum(['purchaserCredits as today_in' => fn ($query) => $query->where('type', 'in')->whereDate('business_date', $date)], 'amount')
            ->withSum(['purchaserCredits as today_out' => fn ($query) => $query->where('type', 'out')->whereDate('business_date', $date)], 'amount')
            ->withCount(['purchaserCredits as transaction_count' => fn ($query) => $query->whereDate('business_date', $date)])
            ->orderBy('name')
            ->get()
            ->map(function (User $purchaser): array {
                $totalIn = round((float) ($purchaser->total_in ?? 0), 2);
                $totalOut = round((float) ($purchaser->total_out ?? 0), 2);
                $todayIn = round((float) ($purchaser->today_in ?? 0), 2);
                $todayOut = round((float) ($purchaser->today_out ?? 0), 2);

                return [
                    'purchaser' => $purchaser,
                    'total_in' => $totalIn,
                    'total_out' => $totalOut,
                    'balance' => round($totalIn - $totalOut, 2),
                    'today_in' => $todayIn,
                    'today_out' => $todayOut,
                    'today_balance' => round($todayIn - $todayOut, 2),
                    'transaction_count' => (int) $purchaser->transaction_count,
                ];
            });
    }

    /**
     * @param  Collection<int, ShopInvoice>  $invoices
     * @param  Collection<int, ShopAccountingEntry>  $entries
     * @param  Collection<int, ShopCredit>  $credits
     * @param  Collection<int, ShopStaffPayment>  $payrollPayments
     * @return array{
     *     cards:array<string,float>,
     *     daily_summaries:Collection<int, array<string,mixed>>,
     *     expense_breakdown:Collection<int, array<string,mixed>>,
     *     income_breakdown:Collection<int, array<string,mixed>>
     * }
     */
    private function ownedShopAnalytics(Collection $invoices, Collection $entries, Collection $credits, Collection $payrollPayments): array
    {
        $creditAmount = round((float) $credits->sum(
            fn (ShopCredit $credit): float => $credit->signedAccountingAmount()
        ), 2);
        $incomeAmount = round((float) $entries->sum(
            fn (ShopAccountingEntry $entry): float => (float) $entry->lines->where('type', 'income')->sum('amount')
        ), 2);
        $expenseAmount = round((float) $entries->sum(
            fn (ShopAccountingEntry $entry): float => (float) $entry->lines->where('type', 'expense')->sum('amount')
        ), 2);
        $cashCreditAmount = round((float) $entries->sum(
            fn (ShopAccountingEntry $entry): float => (float) $entry->lines
                ->where('type', 'income')
                ->where('cash_effect', true)
                ->sum('amount')
        ), 2);
        $cashDebitAmount = round((float) $entries->sum(
            fn (ShopAccountingEntry $entry): float => (float) $entry->lines
                ->where('type', 'expense')
                ->where('cash_effect', true)
                ->sum('amount')
        ), 2);
        $closingBalance = round((float) $entries
            ->filter(fn (ShopAccountingEntry $entry): bool => $entry->closing_cash !== null)
            ->sortBy('business_date')
            ->last()?->closing_cash ?? 0.0, 2);
        $salaryAmount = round((float) $payrollPayments->where('payment_type', '!=', 'advance')->sum('amount'), 2);
        $advanceAmount = round((float) $payrollPayments->where('payment_type', 'advance')->sum('amount'), 2);
        $shopCashMovement = round((float) $credits->where('is_petty_cash', true)->sum(
            fn (ShopCredit $credit): float => $credit->type === 'in' ? (float) $credit->amount : (float) $credit->amount * -1
        ), 2);

        $dailySummaries = collect($invoices->map(fn (ShopInvoice $invoice): ?string => $invoice->business_date?->toDateString())->all())
            ->merge($entries->map(fn (ShopAccountingEntry $entry): ?string => $entry->business_date?->toDateString()))
            ->merge($credits->map(fn (ShopCredit $credit): ?string => $credit->business_date?->toDateString()))
            ->merge($payrollPayments->map(fn (ShopStaffPayment $payment): ?string => $payment->paid_on?->toDateString()))
            ->filter()
            ->unique()
            ->sortDesc()
            ->map(function (string $date) use ($invoices, $entries, $credits, $payrollPayments): array {
                $dayInvoices = $invoices->filter(
                    fn (ShopInvoice $invoice): bool => $invoice->business_date?->toDateString() === $date
                );
                $dayEntries = $entries->filter(
                    fn (ShopAccountingEntry $entry): bool => $entry->business_date?->toDateString() === $date
                );
                $dayCredits = $credits->filter(
                    fn (ShopCredit $credit): bool => $credit->business_date?->toDateString() === $date
                );
                $dayPayrollPayments = $payrollPayments->filter(
                    fn (ShopStaffPayment $payment): bool => $payment->paid_on?->toDateString() === $date
                );

                $paidAmount = round((float) $dayInvoices->sum('paid_amount'), 2);
                $creditAmount = round((float) $dayCredits->sum(fn (ShopCredit $credit): float => $credit->signedAccountingAmount()), 2);
                $incomeAmount = round((float) $dayEntries->sum(
                    fn (ShopAccountingEntry $entry): float => (float) $entry->lines->where('type', 'income')->sum('amount')
                ), 2);
                $expenseAmount = round((float) $dayEntries->sum(
                    fn (ShopAccountingEntry $entry): float => (float) $entry->lines->where('type', 'expense')->sum('amount')
                ), 2);
                $cashCreditAmount = round((float) $dayEntries->sum(
                    fn (ShopAccountingEntry $entry): float => (float) $entry->lines
                        ->where('type', 'income')
                        ->where('cash_effect', true)
                        ->sum('amount')
                ), 2);
                $cashDebitAmount = round((float) $dayEntries->sum(
                    fn (ShopAccountingEntry $entry): float => (float) $entry->lines
                        ->where('type', 'expense')
                        ->where('cash_effect', true)
                        ->sum('amount')
                ), 2);
                $openingBalance = round((float) $dayEntries->sum('opening_cash'), 2);
                $closingBalance = round((float) $dayEntries->sum('closing_cash'), 2);
                $salaryAmount = round((float) $dayPayrollPayments->where('payment_type', '!=', 'advance')->sum('amount'), 2);
                $advanceAmount = round((float) $dayPayrollPayments->where('payment_type', 'advance')->sum('amount'), 2);
                $shopCashMovement = round((float) $dayCredits->where('is_petty_cash', true)->sum(
                    fn (ShopCredit $credit): float => $credit->type === 'in' ? (float) $credit->amount : (float) $credit->amount * -1
                ), 2);

                return [
                    'date' => $date,
                    'billed' => round((float) $dayInvoices->sum('final_total'), 2),
                    'paid' => $paidAmount,
                    'balance' => round((float) $dayInvoices->sum('balance_amount'), 2),
                    'credit' => $creditAmount,
                    'income' => $incomeAmount,
                    'expense' => $expenseAmount,
                    'cash_credit' => $cashCreditAmount,
                    'cash_debit' => $cashDebitAmount,
                    'opening_balance' => $openingBalance,
                    'closing_balance' => $closingBalance,
                    'staff_salary' => $salaryAmount,
                    'staff_advance' => $advanceAmount,
                    'shop_cash_movement' => $shopCashMovement,
                    'petty_cash_taken' => $shopCashMovement,
                    'cash_flow' => round($closingBalance - $openingBalance, 2),
                ];
            })
            ->values();

        $expenseBreakdown = $entries
            ->flatMap(fn (ShopAccountingEntry $entry) => $entry->lines->where('type', 'expense'))
            ->groupBy(fn ($line): string => $line->category?->name ?? 'Uncategorized')
            ->map(fn (Collection $lines, string $label): array => [
                'label' => $label,
                'amount' => round((float) $lines->sum('amount'), 2),
            ])
            ->sortByDesc('amount')
            ->values();
        $incomeBreakdown = $entries
            ->flatMap(fn (ShopAccountingEntry $entry) => $entry->lines->where('type', 'income'))
            ->groupBy(fn ($line): string => $line->category?->name ?? 'Uncategorized')
            ->map(fn (Collection $lines, string $label): array => [
                'label' => $label,
                'amount' => round((float) $lines->sum('amount'), 2),
            ])
            ->sortByDesc('amount')
            ->values();

        return [
            'cards' => [
                'total_billed' => round((float) $invoices->sum('final_total'), 2),
                'total_paid' => round((float) $invoices->sum('paid_amount'), 2),
                'total_balance' => round((float) $invoices->sum('balance_amount'), 2),
                'credit' => $creditAmount,
                'income' => $incomeAmount,
                'expense' => $expenseAmount,
                'cash_credit' => $cashCreditAmount,
                'cash_debit' => $cashDebitAmount,
                'closing_balance' => $closingBalance,
                'staff_salary' => $salaryAmount,
                'staff_advance' => $advanceAmount,
                'shop_cash_movement' => $shopCashMovement,
                'petty_cash_taken' => $shopCashMovement,
                'cash_flow' => $closingBalance,
            ],
            'daily_summaries' => $dailySummaries,
            'expense_breakdown' => $expenseBreakdown,
            'income_breakdown' => $incomeBreakdown,
        ];
    }

    private function recordReserveAdjustment(Shop $shop, float $previousReserveAmount, float $newReserveAmount, Carbon $businessDate, ?int $userId): void
    {
        $delta = round($newReserveAmount - $previousReserveAmount, 2);

        if ($delta === 0.0) {
            return;
        }

        $isIncrease = $delta > 0;

        ShopCredit::query()->create([
            'shop_id' => $shop->id,
            'type' => $isIncrease ? 'in' : 'out',
            'amount' => abs($delta),
            'description' => $isIncrease
                ? sprintf('Reserve amount increased from Rs. %s to Rs. %s', number_format($previousReserveAmount, 2), number_format($newReserveAmount, 2))
                : sprintf('Reserve amount reduced from Rs. %s to Rs. %s', number_format($previousReserveAmount, 2), number_format($newReserveAmount, 2)),
            'created_by' => $userId,
            'business_date' => $businessDate->toDateString(),
            'status' => 'approved',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ]);
    }
}
