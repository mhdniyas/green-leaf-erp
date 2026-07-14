<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Exports\CashFlowDayJournalExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\GenerateShopAccountingInvoiceRequest;
use App\Http\Requests\Web\Admin\ReviewOwnedShopPaymentRequest;
use App\Http\Requests\Web\Admin\ReviewShopAccountingEntryRequest;
use App\Http\Requests\Web\Admin\StoreShopAccountingCategoryRequest;
use App\Http\Requests\Web\Admin\StoreShopAccountingEntryRequest;
use App\Http\Requests\Web\Admin\StoreShopCreditRequest;
use App\Http\Requests\Web\Admin\UpdateDailyBillPaymentRequest;
use App\Http\Requests\Web\Admin\UpdateShopAccountingEntryRequest;
use App\Models\PurchaserCredit;
use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopAccountingEntry;
use App\Models\ShopAccountingInvoice;
use App\Models\ShopCredit;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Finance\AdminFinancePillarService;
use App\Services\Finance\OwnedShopAccountingService;
use App\Services\Finance\ShopAccountingInvoiceService;
use App\Services\ShopInvoices\ShopInvoiceService;
use App\Support\AccountingAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        private readonly OwnedShopAccountingService $ownedShopAccountingService,
        private readonly ShopAccountingInvoiceService $shopAccountingInvoiceService,
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
        $ownedShops = $this->ownedShopAccountingService->eligibleShops();
        $onlyOwnedShops = $request->boolean('only_owned_shops');
        $selectedOwnedShopId = $request->integer('owned_shop_id');
        $selectedOwnedShop = $ownedShops->firstWhere('id', $selectedOwnedShopId);
        $selectedOwnedShopId = $selectedOwnedShop instanceof Shop ? $selectedOwnedShop->id : null;
        $shopIds = match (true) {
            $selectedOwnedShopId !== null => [$selectedOwnedShopId],
            $onlyOwnedShops => $ownedShops->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            default => null,
        };
        $report = $this->financePillars->salesDailyDetail($date, $statusFilter, $shopIds);

        return view('admin.accounting.daily_sales', compact('date', 'report', 'statusFilter', 'ownedShops', 'selectedOwnedShopId', 'onlyOwnedShops'));
    }

    public function cashFlowReport(Request $request): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::DashboardView);

        $date = Carbon::parse($request->input('date', today()->toDateString()));
        $cashFlowReport = $this->financePillars->cashFlowReport($date);

        return view('admin.accounting.cash_flow', compact('date', 'cashFlowReport'));
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
        $shops->load(['users:id,shop_id', 'latestAccountingEntry.submittedBy:id,name']);
        $shops->loadSum('invoices as pending_balance_amount', 'balance_amount');
        $shops->loadCount([
            'accountingEntries as pending_updates_count' => fn ($query) => $query->where('status', 'submitted'),
            'accountingEntries as recheck_updates_count' => fn ($query) => $query->where('status', 'recheck_required'),
        ]);
        $availableShops = Shop::query()
            ->where(function ($query): void {
                $query->where('accounting_enabled', false)
                    ->orWhereNotIn('accounting_mode', ['owned', 'partnership']);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'accounting_mode', 'accounting_enabled']);

        return view('admin.accounting.owned_shops.index', compact('shops', 'availableShops'));
    }

    public function storeOwnedShop(Request $request): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);

        $validated = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'accounting_mode' => ['required', 'string', 'in:owned,partnership'],
            'reserve_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        /** @var Shop $shop */
        $shop = Shop::query()->findOrFail($validated['shop_id']);

        if ($shop->isOwnedAccountingEnabled()) {
            return redirect()->route('admin.accounting.owned-shops.show', ['shop' => $shop->code])
                ->with('warning', 'This shop is already enabled for owned-shop accounting.');
        }

        $reserveAmount = round((float) ($validated['reserve_amount'] ?? 0), 2);

        DB::transaction(function () use ($request, $shop, $validated, $reserveAmount): void {
            $shop->update([
                'accounting_enabled' => true,
                'accounting_mode' => $validated['accounting_mode'],
                'reserve_amount' => $reserveAmount,
            ]);

            if ($reserveAmount > 0) {
                $this->recordReserveAdjustment($shop, 0.0, $reserveAmount, today(), $request->user()?->id);
            }
        });

        return redirect()->route('admin.accounting.owned-shops.show', ['shop' => $shop->code])
            ->with('success', 'Owned shop accounting enabled for '.$shop->name.'.');
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
        $tab = in_array((string) $request->input('tab', 'bills'), ['bills', 'cashbook'], true)
            ? (string) $request->input('tab', 'bills')
            : 'bills';
        $startDate = Carbon::parse($request->input('start_date', $selectedDate->copy()->startOfMonth()->toDateString()));
        $endDate = Carbon::parse($request->input('end_date', $selectedDate->copy()->endOfMonth()->toDateString()));
        $entry = $this->ownedShopAccountingService->entryForDate($shop, $selectedDate);
        $availableCategories = $this->ownedShopAccountingService->availableCategoriesForShop($shop);
        $recentEntries = ShopAccountingEntry::query()
            ->where('shop_id', $shop->id)
            ->with(['lines.category', 'submittedBy', 'reviewedBy'])
            ->latest('business_date')
            ->limit(12)
            ->get();
        $invoices = ShopAccountingInvoice::query()
            ->where('shop_id', $shop->id)
            ->with(['generatedBy', 'splits'])
            ->latest('period_end')
            ->limit(12)
            ->get();
        $billingInvoices = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->with(['order', 'paymentRequests' => fn ($query) => $query->latest('id')])
            ->latest('business_date')
            ->latest('id')
            ->paginate(12, ['*'], 'bills_page');
        $paymentRequests = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shop->id)
            ->with(['invoice', 'requestedBy', 'reviewedBy'])
            ->latest('id')
            ->paginate(12, ['*'], 'payment_requests_page');
        $shopCredits = ShopCredit::query()
            ->where('shop_id', $shop->id)
            ->with('creator')
            ->latest('business_date')
            ->latest('id')
            ->limit(12)
            ->get();
        $periodInvoices = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->get();
        $periodEntries = ShopAccountingEntry::query()
            ->where('shop_id', $shop->id)
            ->whereIn('status', ['submitted', 'recheck_required', 'approved', 'finalized'])
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->with(['lines.category'])
            ->get();
        $periodCredits = ShopCredit::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->get();
        $analytics = $this->ownedShopAnalytics($periodInvoices, $periodEntries, $periodCredits);

        $incomeTotal = $entry instanceof ShopAccountingEntry
            ? round((float) $entry->lines->where('type', 'income')->sum('amount'), 2)
            : 0.0;
        $expenseTotal = $entry instanceof ShopAccountingEntry
            ? round((float) $entry->lines->where('type', 'expense')->sum('amount'), 2)
            : 0.0;

        return view('admin.accounting.owned_shops.show', [
            'shop' => $shop->loadMissing(['ownerships.user', 'users']),
            'tab' => $tab,
            'selectedDate' => $selectedDate,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'entry' => $entry,
            'availableCategories' => $availableCategories,
            'globalCategories' => $availableCategories->whereNull('shop_id')->values(),
            'shopCategories' => $availableCategories->where('shop_id', $shop->id)->values(),
            'recentEntries' => $recentEntries,
            'invoices' => $invoices,
            'billingInvoices' => $billingInvoices,
            'paymentRequests' => $paymentRequests,
            'shopCredits' => $shopCredits,
            'analytics' => $analytics,
            'ownershipTotal' => $this->ownedShopAccountingService->ownershipPercentageTotal($shop),
            'incomeTotal' => $incomeTotal,
            'expenseTotal' => $expenseTotal,
            'netAmount' => round($incomeTotal - $expenseTotal, 2),
        ]);
    }

    public function ownedShopCategories(Request $request, Shop $shop): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);
        $availableCategories = $this->ownedShopAccountingService->availableCategoriesForShop($shop);

        return view('admin.accounting.owned_shops.categories', [
            'shop' => $shop,
            'globalCategories' => $availableCategories->whereNull('shop_id')->values(),
            'shopCategories' => $availableCategories->where('shop_id', $shop->id)->values(),
        ]);
    }

    public function storeOwnerships(Request $request, Shop $shop): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);

        $request->merge([
            'ownerships' => collect($request->input('ownerships', []))
                ->filter(function ($ownership): bool {
                    if (! is_array($ownership)) {
                        return false;
                    }

                    return filled($ownership['owner_name'] ?? null)
                        || filled($ownership['ownership_percent'] ?? null)
                        || filled($ownership['role_label'] ?? null)
                        || filled($ownership['user_id'] ?? null);
                })
                ->values()
                ->all(),
        ]);

        $validated = $request->validate([
            'ownerships' => ['required', 'array', 'min:1'],
            'ownerships.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'ownerships.*.owner_name' => ['required', 'string', 'max:255'],
            'ownerships.*.ownership_percent' => ['required', 'numeric', 'gt:0', 'lte:100'],
            'ownerships.*.role_label' => ['nullable', 'string', 'max:100'],
        ]);

        $ownershipTotal = round((float) collect($validated['ownerships'])->sum('ownership_percent'), 2);
        if (abs($ownershipTotal - 100.00) > 0.01) {
            return back()->withErrors(['ownerships' => 'Ownership percentages must total exactly 100%.'])->withInput();
        }

        $this->ownedShopAccountingService->replaceOwnerships($shop, $validated['ownerships']);

        return redirect()->route('admin.accounting.owned-shops.show', ['shop' => $shop->code])
            ->with('success', 'Shop ownership shares updated.');
    }

    public function storeCategory(StoreShopAccountingCategoryRequest $request, Shop $shop): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);

        $validated = $request->validated();
        $targetShopId = $validated['scope'] === 'global' ? null : $shop->id;

        $exists = ShopAccountingCategory::query()
            ->where('type', $validated['type'])
            ->where('name', trim($validated['name']))
            ->when(
                $targetShopId === null,
                fn ($query) => $query->whereNull('shop_id'),
                fn ($query) => $query->where('shop_id', $targetShopId)
            )
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'A category with this name already exists for the selected scope.'])->withInput();
        }

        ShopAccountingCategory::query()->create([
            'shop_id' => $targetShopId,
            'type' => $validated['type'],
            'cash_effect' => (bool) ($validated['cash_effect'] ?? true),
            'name' => trim($validated['name']),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('admin.accounting.owned-shops.categories.index', ['shop' => $shop->code])
            ->with('success', 'Accounting category created.');
    }

    public function storeEntry(StoreShopAccountingEntryRequest $request, Shop $shop): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);

        try {
            $entry = $this->ownedShopAccountingService->saveEntry($shop, $request->validated(), (int) $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('admin.accounting.owned-shops.show', [
            'shop' => $shop->code,
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
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('admin.accounting.owned-shops.show', [
            'shop' => $shop->code,
            'date' => $entry->business_date->toDateString(),
        ])->with('success', 'Daily accounting entry updated.');
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
            'date' => $entry->business_date->toDateString(),
        ])->with($entry->status === 'recheck_required' ? 'warning' : 'success', $message);
    }

    public function storeInvoice(GenerateShopAccountingInvoiceRequest $request, Shop $shop): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::InvoiceGenerate);
        $shop = $this->loadEligibleShop($shop);

        try {
            $invoice = $this->shopAccountingInvoiceService->generate(
                shop: $shop,
                periodStart: Carbon::parse($request->validated('period_start')),
                periodEnd: Carbon::parse($request->validated('period_end')),
                userId: (int) $request->user()->id,
                notes: $request->validated('notes'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['invoice' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('admin.accounting.owned-shops.invoices.show', [
            'shop' => $shop->code,
            'invoice' => $invoice,
        ])->with('success', 'Settlement invoice generated successfully.');
    }

    public function showInvoice(Request $request, Shop $shop, ShopAccountingInvoice $invoice): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);
        abort_unless($invoice->shop_id === $shop->id, 404);

        return view('admin.accounting.owned_shops.invoice', [
            'shop' => $shop,
            'invoice' => $invoice->load(['generatedBy', 'approvedBy', 'splits.ownership']),
        ]);
    }

    public function approveInvoice(Request $request, Shop $shop, ShopAccountingInvoice $invoice): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::InvoiceApprove);
        $shop = $this->loadEligibleShop($shop);
        abort_unless($invoice->shop_id === $shop->id, 404);

        try {
            $this->shopAccountingInvoiceService->approve($invoice, (int) $request->user()->id);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['invoice' => $exception->getMessage()]);
        }

        return back()->with('success', 'Settlement invoice approved and the period is now closed.');
    }

    public function markInvoicePaid(Request $request, Shop $shop, ShopAccountingInvoice $invoice): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::InvoiceApprove);
        $shop = $this->loadEligibleShop($shop);
        abort_unless($invoice->shop_id === $shop->id, 404);

        try {
            $this->shopAccountingInvoiceService->markPaid($invoice, (int) $request->user()->id);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['invoice' => $exception->getMessage()]);
        }

        return back()->with('success', 'Settlement invoice marked as paid.');
    }

    public function updateDailyBillPayment(UpdateDailyBillPaymentRequest $request, Shop $shop, ShopInvoice $invoice): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);
        abort_unless($invoice->shop_id === $shop->id, 404);

        try {
            $this->shopInvoiceService->recordAdminPaymentReceived($invoice, $request->validated(), (int) $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return back()->with('success', 'Daily invoice payment received and approved.');
    }

    public function storeShopCredit(StoreShopCreditRequest $request, Shop $shop): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);
        $validated = $request->validated();

        ShopCredit::query()->create([
            'shop_id' => $shop->id,
            'type' => $validated['type'],
            'amount' => round((float) $validated['amount'], 2),
            'description' => filled($validated['description'] ?? null)
                ? trim((string) $validated['description'])
                : ($validated['type'] === 'in' ? 'Cash given to shop' : 'Cash received from shop'),
            'created_by' => $request->user()?->id,
            'business_date' => $validated['business_date'],
        ]);

        return redirect()->route('admin.accounting.owned-shops.show', [
            'shop' => $shop->code,
            'tab' => 'cashbook',
            'date' => $validated['business_date'],
        ])->with('success', $validated['type'] === 'in' ? 'Cash given to shop recorded as accounting expense.' : 'Cash received from shop recorded as accounting income.');
    }

    public function reviewOwnedShopPaymentRequest(ReviewOwnedShopPaymentRequest $request, Shop $shop, ShopInvoicePaymentRequest $paymentRequest): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::OwnedShopManage);
        $shop = $this->loadEligibleShop($shop);
        abort_unless($paymentRequest->shop_id === $shop->id, 404);

        try {
            $paymentRequest = $this->shopInvoiceService->reviewPaymentRequest(
                $paymentRequest,
                $request->validated('decision'),
                (int) $request->user()->id,
                $request->validated('admin_note'),
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('admin.accounting.owned-shops.show', ['shop' => $shop->code, 'tab' => 'bills'])
            ->with(
                $paymentRequest->status === 'approved' ? 'success' : 'warning',
                $paymentRequest->status === 'approved'
                    ? 'Shop payment request approved and marked as paid.'
                    : 'Shop payment request rejected.'
            );
    }

    public function generateDailyWorkflowInvoices(Request $request): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::InvoiceGenerate);

        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $this->shopInvoiceService->generateForBusinessDate($validated['date'], (int) $request->user()->id);

        return redirect()->route('admin.accounting.index', ['date' => $validated['date']])
            ->with('success', 'Daily shop invoices generated for the selected date.');
    }

    public function purchasersIndex(Request $request): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::PurchaserCashManage);

        $sort = (string) $request->input('sort', 'name');
        $direction = strtolower((string) $request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortableColumns = ['name', 'total_in', 'total_out', 'balance'];

        if (! in_array($sort, $sortableColumns, true)) {
            $sort = 'name';
        }

        $purchasers = User::query()
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

        $totals = [
            'total_in' => round((float) $purchasers->sum('total_in'), 2),
            'total_out' => round((float) $purchasers->sum('total_out'), 2),
            'balance' => round((float) $purchasers->sum('balance'), 2),
        ];

        return view('admin.accounting.purchasers.index', compact('purchasers', 'totals', 'sort', 'direction'));
    }

    public function purchaserShow(Request $request, User $user): View
    {
        $this->ensureAccountingAccess($request, AccountingAccess::PurchaserCashManage);
        abort_unless($user->hasRole('purchaser'), 404);

        $credits = PurchaserCredit::query()
            ->where('purchaser_id', $user->id)
            ->with(['purchaseInvoice', 'creator'])
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->get();

        $totalIn = (float) $credits->where('type', 'in')->sum('amount');
        $totalOut = (float) $credits->where('type', 'out')->sum('amount');
        $balance = $totalIn - $totalOut;

        return view('admin.accounting.purchasers.show', compact('user', 'credits', 'totalIn', 'totalOut', 'balance'));
    }

    public function storePurchaserCredit(Request $request, User $user): RedirectResponse
    {
        $this->ensureAccountingAccess($request, AccountingAccess::PurchaserCashManage);
        abort_unless($user->hasRole('purchaser'), 404);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'business_date' => ['required', 'date'],
        ]);

        PurchaserCredit::create([
            'purchaser_id' => $user->id,
            'type' => 'in',
            'amount' => (float) $validated['amount'],
            'description' => $validated['description'] ?: 'Cash / Credit from Green Leaf',
            'created_by' => auth()->id(),
            'business_date' => $validated['business_date'],
        ]);

        return redirect()->route('admin.accounting.purchasers.show', $user)
            ->with('success', 'Credit added successfully.');
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
     * @return array{
     *     cards:array<string,float>,
     *     daily_summaries:Collection<int, array<string,mixed>>,
     *     expense_breakdown:Collection<int, array<string,mixed>>
     * }
     */
    private function ownedShopAnalytics(Collection $invoices, Collection $entries, Collection $credits): array
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

        $dailySummaries = $invoices
            ->groupBy(fn (ShopInvoice $invoice): string => $invoice->business_date->toDateString())
            ->map(function (Collection $dayInvoices, string $date) use ($entries, $credits): array {
                $dayEntry = $entries->first(
                    fn (ShopAccountingEntry $entry): bool => $entry->business_date?->toDateString() === $date
                );
                $dayCredit = round((float) $credits
                    ->filter(fn (ShopCredit $credit): bool => $credit->business_date?->toDateString() === $date)
                    ->sum(fn (ShopCredit $credit): float => $credit->signedAccountingAmount()), 2);

                return [
                    'date' => $date,
                    'billed' => round((float) $dayInvoices->sum('final_total'), 2),
                    'paid' => round((float) $dayInvoices->sum('paid_amount'), 2),
                    'balance' => round((float) $dayInvoices->sum('balance_amount'), 2),
                    'credit' => $dayCredit,
                    'income' => $dayEntry instanceof ShopAccountingEntry
                        ? round((float) $dayEntry->lines->where('type', 'income')->sum('amount'), 2)
                        : 0.0,
                    'expense' => $dayEntry instanceof ShopAccountingEntry
                        ? round((float) $dayEntry->lines->where('type', 'expense')->sum('amount'), 2)
                        : 0.0,
                ];
            })
            ->sortByDesc('date')
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

        return [
            'cards' => [
                'total_billed' => round((float) $invoices->sum('final_total'), 2),
                'total_paid' => round((float) $invoices->sum('paid_amount'), 2),
                'total_balance' => round((float) $invoices->sum('balance_amount'), 2),
                'credit' => $creditAmount,
                'income' => $incomeAmount,
                'expense' => $expenseAmount,
                'cash_flow' => round(((float) $invoices->sum('paid_amount') + $creditAmount + $incomeAmount) - $expenseAmount, 2),
            ],
            'daily_summaries' => $dailySummaries,
            'expense_breakdown' => $expenseBreakdown,
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
        ]);
    }
}
