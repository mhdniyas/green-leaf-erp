<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Exports\PurchaserReportArrayExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashbook\AcceptPaymentRequest;
use App\Http\Requests\Cashbook\AddShopRequest;
use App\Http\Requests\Cashbook\AssignShopPresetRequest;
use App\Http\Requests\Cashbook\CreatePresetRequest;
use App\Http\Requests\Cashbook\DeleteEntryRequest;
use App\Http\Requests\Cashbook\PayShopRequest;
use App\Http\Requests\Cashbook\RecordEntryRequest;
use App\Http\Requests\Cashbook\UpdateEntryRequest;
use App\Http\Requests\Cashbook\UpdatePresetSettingRequest;
use App\Http\Requests\Cashbook\UpdateRuleRequest;
use App\Http\Requests\Cashbook\VoidEntryRequest;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\CompanyPaymentReconciliation;
use App\Models\Cashbook\LedgerClient;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\PresetCollectionGroup;
use App\Models\Cashbook\PresetCollectionGroupEntryType;
use App\Models\Cashbook\PresetEntrySetting;
use App\Models\Cashbook\ShopConfigPreset;
use App\Models\Cashbook\ShopLedgerCollectionGroup;
use App\Models\Cashbook\ShopLedgerCollectionGroupEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\CollectionGroupPostingService;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use App\Services\Cashbook\DailyLedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
        private readonly CompanyPaymentReconciliationService $companyPaymentReconciliationService
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

    public function acceptPaymentPage(Request $request): View
    {
        $this->ensureMainAdmin($request);

        [$month, $startDate, $endDate] = $this->paymentMonthWindow($request);
        $shops = $this->shopSyncService->syncAndGetProfiles();
        $shops->load('client');
        $companyAccounts = CompanyAccount::where('enabled', true)->orderBy('name')->get();
        $company = config('greenleaf');
        $currentShop = $shops->first();

        $shopCards = $shops
            ->map(fn (ShopLedgerProfile $shop): array => $this->shopPaymentCard($shop, $startDate, $endDate))
            ->sortByDesc(fn (array $card): float => $card['after_balance'] + $card['floating_amount'] + $card['pending_amount'])
            ->values();

        $totals = [
            'received' => round((float) $shopCards->sum('received_amount'), 2),
            'approved' => round((float) $shopCards->sum('approved_amount'), 2),
            'floating' => round((float) $shopCards->sum('floating_amount'), 2),
            'pending' => round((float) $shopCards->sum('pending_amount'), 2),
            'payable' => round((float) $shopCards->sum('payable_balance'), 2),
            'after_balance' => round((float) $shopCards->sum('after_balance'), 2),
        ];

        return view('admin.cashbook.payments.index', compact(
            'shops',
            'companyAccounts',
            'company',
            'currentShop',
            'month',
            'startDate',
            'endDate',
            'shopCards',
            'totals',
        ));
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
        $clients = LedgerClient::with('shops')->where('enabled', true)->get();
        $entryTypes = LedgerEntryType::where('active', true)->orderBy('display_order')->get();
        $companyAccounts = CompanyAccount::where('enabled', true)->get();
        $company = config('greenleaf');
        $currentShop = $this->resolveShop($shop);
        $currentShop->load('client', 'preset');

        return view('admin.cashbook.shops.show', compact(
            'shops', 'clients', 'entryTypes', 'companyAccounts', 'company', 'currentShop'
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
        $companyAccounts = CompanyAccount::where('enabled', true)->orderBy('name')->get();
        $company = config('greenleaf');
        $currentShop = $this->resolveShop($shop);
        $currentShop->load('client', 'preset');
        $shopCard = $this->shopPaymentCard($currentShop, $monthStart, $monthEnd);
        $payableDetails = $this->shopPayableDetails($currentShop, $dateFrom, $dateTo, $search);
        $paymentRequests = ShopInvoicePaymentRequest::query()
            ->with(['requestedBy', 'reviewedBy', 'reconciliations.companyAccount', 'reconciliations.statementEntry'])
            ->where('shop_id', $currentShop->shop_id)
            ->where(function ($query) use ($monthStart, $monthEnd): void {
                $query->whereBetween('payment_date', [$monthStart, $monthEnd])
                    ->orWhereBetween('created_at', [$monthStart.' 00:00:00', $monthEnd.' 23:59:59']);
            })
            ->latest('id')
            ->get();

        $cashAccounts = $companyAccounts->where('account_type', 'cash')->values();

        return view('admin.cashbook.payments.shop', compact(
            'shops',
            'companyAccounts',
            'cashAccounts',
            'company',
            'currentShop',
            'month',
            'monthStart',
            'monthEnd',
            'dateFrom',
            'dateTo',
            'search',
            'shopCard',
            'payableDetails',
            'paymentRequests',
        ));
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

        return view('admin.cashbook.settings.shop', compact(
            'shops', 'clients', 'companyAccounts', 'company', 'currentShop', 'settingsByCategory', 'collectionGroup'
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
            ->latest('transaction_date')
            ->latest('id')
            ->limit(12)
            ->get();

        $recentReconciliations = CompanyPaymentReconciliation::query()
            ->with(['paymentRequest.shop', 'statementEntry', 'reconciledBy'])
            ->where('company_account_id', $account->id)
            ->latest('id')
            ->limit(12)
            ->get();

        return view('admin.cashbook.bank-accounts.show', compact(
            'shops',
            'companyAccounts',
            'company',
            'currentShop',
            'account',
            'statementSummary',
            'recentStatementEntries',
            'recentReconciliations',
        ));
    }

    public function showBankAccountStatement(Request $request, CompanyAccount $account): View
    {
        $this->ensureMainAdmin($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $companyAccounts = CompanyAccount::orderBy('is_default', 'desc')->orderBy('name')->get();
        $company = config('greenleaf');
        $currentShop = $shops->first();

        $statementEntries = $account->statementEntries()
            ->with(['reconciliations.paymentRequest.shop', 'reconciliations.reconciledBy'])
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $statementSummary = CompanyAccountStatementEntry::query()
            ->where('company_account_id', $account->id)
            ->selectRaw("SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END) as money_in")
            ->selectRaw("SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END) as money_out")
            ->selectRaw('SUM(matched_amount) as matched_total')
            ->first();

        return view('admin.cashbook.bank-accounts.statement', compact(
            'shops',
            'companyAccounts',
            'company',
            'currentShop',
            'account',
            'statementEntries',
            'statementSummary',
        ));
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
            ->with('companyAccount')
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
        $today = today()->toDateString();
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
            : $companyAccounts->firstWhere('account_type', 'bank');
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

    public function companyFinanceJournal(Request $request): View
    {
        $this->ensureMainAdmin($request);

        $status = (string) $request->input('status', 'all');
        $method = (string) $request->input('payment_method', 'all');

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $companyAccounts = CompanyAccount::where('enabled', true)->orderBy('name')->get();
        $company = config('greenleaf');
        $currentShop = $shops->first();

        $paymentRequests = ShopInvoicePaymentRequest::query()
            ->with(['shop', 'requestedBy', 'reviewedBy', 'reconciliations.companyAccount', 'reconciliations.statementEntry'])
            ->when($status !== 'all', fn ($query) => $query->where('reconciliation_status', $status))
            ->when($method !== 'all', fn ($query) => $query->where('payment_method', $method))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $summaryBase = ShopInvoicePaymentRequest::query()->where('status', '!=', 'rejected');
        $totals = [
            'requested' => round((float) (clone $summaryBase)->sum('requested_amount'), 2),
            'reconciled' => round((float) (clone $summaryBase)->sum('reconciled_amount'), 2),
            'floating' => round((float) (clone $summaryBase)->sum('floating_amount'), 2),
            'pending' => round((float) (clone $summaryBase)
                ->where('status', 'pending')
                ->sum('requested_amount'), 2),
        ];

        return view('admin.cashbook.finance.journal', compact(
            'shops',
            'companyAccounts',
            'company',
            'currentShop',
            'paymentRequests',
            'totals',
            'status',
            'method',
        ));
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
        $selectedAccountId = $request->integer('company_account_id') ?: (int) ($companyAccounts->first()?->id ?? 0);
        $search = trim((string) $request->input('search', ''));

        $statementEntries = CompanyAccountStatementEntry::query()
            ->with('companyAccount')
            ->where('direction', 'in')
            ->whereIn('status', ['unmatched', 'partially_matched'])
            ->when($selectedAccountId > 0, fn ($query) => $query->where('company_account_id', $selectedAccountId))
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('reference', 'like', '%'.$search.'%')
                        ->orWhere('narration', 'like', '%'.$search.'%')
                        ->orWhere('amount', 'like', '%'.$search.'%');
                });
            })
            ->oldest('transaction_date')
            ->oldest('id')
            ->get();

        $selectedStatement = $statementRef
            ? $this->resolveSecureStatementEntry($statementRef)
            : $statementEntries->first();

        if ($selectedStatement instanceof CompanyAccountStatementEntry) {
            $selectedStatement->load('companyAccount', 'reconciliations.paymentRequest.shop');
            $selectedAccountId = (int) $selectedStatement->company_account_id;
        }

        $possiblePayments = $selectedStatement instanceof CompanyAccountStatementEntry
            ? $this->possiblePaymentsForStatement($selectedStatement, $graceDays, $search)
            : collect();

        $reconciliationEntryTypes = LedgerEntryType::query()
            ->where('active', true)
            ->whereIn('code', ['reconciliation_adjustment', 'bank_charges', 'short_receipt', 'excess_receipt'])
            ->orderBy('display_order')
            ->get();

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
            'search',
            'statementEntries',
            'selectedStatement',
            'possiblePayments',
            'reconciliationEntryTypes',
        ));
    }

    public function matchStatementReconciliation(Request $request, string $statementRef): RedirectResponse
    {
        $this->ensureMainAdmin($request);

        $statementEntry = $this->resolveSecureStatementEntry($statementRef);
        $validated = $request->validate([
            'payment_request_ref' => ['required', 'string'],
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
     * API: Accept Payment from Shop (validated via AcceptPaymentRequest).
     */
    public function acceptPayment(AcceptPaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $shopId = (int) $validated['shop_id'];
        $date = $validated['business_date'];
        $settle = (float) ($validated['settle_amount'] ?? 0);
        $petty = (float) ($validated['petty_amount'] ?? 0);
        $amount = round($settle + $petty, 2);
        $paymentMethod = (string) ($validated['payment_method'] ?? 'cash');
        $categoryCode = $validated['category_code'] ?? null;
        $companyAccountId = isset($validated['company_account_id']) ? (int) $validated['company_account_id'] : null;
        $notes = $validated['notes'] ?? 'Payment received by admin';

        if ($amount <= 0.0) {
            return response()->json(['success' => false, 'message' => 'Enter a payment amount greater than zero.'], 422);
        }

        if ($categoryCode && $categoryCode !== 'all') {
            $entryType = LedgerEntryType::where('code', $categoryCode)->first();
            $categoryLabel = $entryType ? $entryType->name : $categoryCode;
            if (! str_contains(strtolower($notes), strtolower($categoryLabel))) {
                $notes = "[{$categoryLabel}] ".$notes;
            }
        }

        $userId = $request->user()?->id ?? 1;

        try {
            $shop = Shop::query()->findOrFail($shopId);
            $invoice = ShopInvoice::query()
                ->where('shop_id', $shop->id)
                ->where('balance_amount', '>', 0)
                ->oldest('business_date')
                ->oldest('id')
                ->first();

            $paymentRequest = ShopInvoicePaymentRequest::query()->create([
                'shop_invoice_id' => $invoice?->id,
                'shop_id' => $shop->id,
                'requested_by' => $userId,
                'request_type' => $invoice instanceof ShopInvoice ? 'admin_cashbook' : 'shop_balance',
                'payment_method' => $paymentMethod,
                'payment_reference' => filled($validated['payment_reference'] ?? null) ? trim((string) $validated['payment_reference']) : null,
                'payment_date' => $date,
                'cheque_status' => $paymentMethod === 'cheque' ? 'pending' : null,
                'cheque_bank_name' => $validated['cheque_bank_name'] ?? null,
                'cheque_date' => $validated['cheque_date'] ?? null,
                'requested_amount' => $amount,
                'admin_verified_amount' => $paymentMethod === 'cash' ? $amount : null,
                'approved_amount' => null,
                'applied_amount' => 0,
                'credit_amount' => 0,
                'status' => 'pending',
                'reconciliation_status' => 'floating',
                'reconciled_amount' => 0,
                'floating_amount' => $amount,
                'shop_advance_amount' => 0,
                'shop_note' => $notes,
            ]);

            $message = 'Payment recorded and moved to reconciliation.';

            if ($paymentMethod === 'cash') {
                $cashAccount = $companyAccountId
                    ? CompanyAccount::query()->whereKey($companyAccountId)->where('account_type', 'cash')->first()
                    : CompanyAccount::query()->where('enabled', true)->where('account_type', 'cash')->orderByDesc('is_default')->first();

                if (! $cashAccount instanceof CompanyAccount) {
                    return response()->json(['success' => false, 'message' => 'Create or select a Cash in Hand account before accepting cash.'], 422);
                }

                $this->companyPaymentReconciliationService->reconcilePayment($paymentRequest, [
                    'company_account_id' => $cashAccount->id,
                    'statement_amount' => $amount,
                    'cleared_amount' => $amount,
                    'difference_amount' => 0,
                    'difference_action' => 'none',
                    'business_date' => $date,
                    'admin_note' => $notes.' Cash received directly by admin.',
                ], (int) $userId);

                $message = 'Cash received, approved, and added to Cash in Hand statement.';
            }

            $snapshot = $this->ledgerService->dailySummary($shopId, $date);

            return response()->json([
                'success' => true,
                'message' => $message,
                'payment_request' => $paymentRequest->fresh(['reconciliations.companyAccount', 'reconciliations.statementEntry']),
                'snapshot' => $snapshot,
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * API: Company reimburses / pays a shop (validated via PayShopRequest).
     */
    public function payShop(PayShopRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $res = $this->ledgerService->recordEntry([
                'shop_id' => (int) $validated['shop_id'],
                'business_date' => $validated['business_date'],
                'entry_type_code' => 'company_to_petty',
                'amount' => (float) $validated['amount'],
                'funding_source' => 'company',
                'entered_by' => $request->user()?->id ?? 1,
                'notes' => $validated['notes'] ?? 'Company reimbursement to shop',
            ]);

            return response()->json([
                'success' => true,
                'message' => '₹'.number_format($validated['amount'], 2)." paid to Shop #{$validated['shop_id']}.",
                'transaction' => $res['transaction']->load('entryType'),
                'snapshot' => $res['snapshot'],
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
                'default_funding_source' => $validated['default_funding_source'],
                'include_in_sales' => (bool) $validated['include_in_sales'],
                'include_in_income' => (bool) $validated['include_in_income'],
                'include_in_expense' => (bool) $validated['include_in_expense'],
                'include_in_pl' => (bool) $validated['include_in_pl'],
                'include_in_payable' => (bool) $validated['include_in_payable'],
                'payable_direction' => $validated['payable_direction'] ?? null,
                'settlement_behavior' => $validated['settlement_behavior'] ?: 'none',
                'petty_behavior' => $validated['petty_behavior'] ?: 'none',
                'company_pending_behavior' => $validated['company_pending_behavior'] ?: 'none',
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

            $transaction->update([
                'status' => 'approved',
                'approved_by' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Entry approved.',
                'transaction' => $transaction->fresh()->load('entryType'),
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
            $query = ShopLedgerTransaction::query()
                ->where('shop_id', (int) $validated['shop_id'])
                ->where('status', '!=', 'approved')
                ->where('status', '!=', 'void');

            if ($tillDate) {
                $query->whereDate('business_date', '<=', $validated['business_date']);
            } else {
                $query->whereDate('business_date', $validated['business_date']);
            }

            $updated = $query->update([
                'status' => 'approved',
                'approved_by' => $request->user()?->id,
            ]);

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
        $selected = Carbon::parse((string) $request->input('month', today()->format('Y-m')) . '-01');
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
}
