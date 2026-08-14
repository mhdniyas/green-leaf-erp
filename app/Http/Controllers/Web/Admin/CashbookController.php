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
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\CollectionGroupPostingService;
use App\Services\Cashbook\DailyLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
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
        private readonly CollectionGroupPostingService $collectionGroupPostingService
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
        $rows = $this->cashbookReportExportRows(
            $filters['selected_date'],
            $filters['timeframe'],
            $filters['start_date'],
            $filters['end_date']
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

        return Excel::download(
            new PurchaserReportArrayExport(
                $this->cashbookReportExportRows(
                    $filters['selected_date'],
                    $filters['timeframe'],
                    $filters['start_date'],
                    $filters['end_date']
                ),
                'Cashbook Report'
            ),
            $this->reportFilename('cashbook-report', $filters['start_date'], $filters['end_date'], 'xlsx'),
        );
    }

    public function payables(Request $request): View
    {
        $this->ensureMainAdmin($request);

        return $this->renderApp('payables', 1);
    }

    public function acceptPaymentPage(Request $request): View
    {
        $this->ensureMainAdmin($request);

        return $this->renderApp('payments', 1);
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
        $startDate = (string) $request->input('start_date', $date);
        $endDate = (string) $request->input('end_date', $date);

        $carbon = Carbon::parse($date);

        [$finalStart, $finalEnd] = match ($timeframe) {
            'daily' => [$date, $date],
            'weekly' => [$carbon->copy()->startOfWeek()->toDateString(), $carbon->copy()->endOfWeek()->toDateString()],
            'monthly' => [$carbon->copy()->startOfMonth()->toDateString(), $carbon->copy()->endOfMonth()->toDateString()],
            'custom' => [$startDate, $endDate],
            default => [$date, $date],
        };

        $transactions = ShopLedgerTransaction::query()
            ->with('entryType')
            ->where('shop_id', (int) $resolvedShop->id)
            ->whereBetween('business_date', [$finalStart, $finalEnd])
            ->orderBy('business_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $totalSales = (float) $transactions
            ->filter(fn ($t) => $t->direction === 'income' || ($t->entryType && $t->entryType->category === 'income'))
            ->sum('amount');

        $totalExpense = (float) $transactions
            ->filter(fn ($t) => $t->direction === 'expense' || ($t->entryType && $t->entryType->category === 'expense'))
            ->sum('amount');

        $netPosition = $totalSales - $totalExpense;

        if ($format === 'pdf') {
            return view('admin.cashbook.shops.export-pdf', [
                'shop' => $resolvedShop,
                'timeframe' => $timeframe,
                'startDate' => $finalStart,
                'endDate' => $finalEnd,
                'transactions' => $transactions,
                'totalSales' => $totalSales,
                'totalExpense' => $totalExpense,
                'netPosition' => $netPosition,
            ]);
        }

        $delimiter = $format === 'excel' ? "\t" : ',';
        $ext = $format === 'excel' ? 'xls' : 'csv';
        $contentType = $format === 'excel' ? 'application/vnd.ms-excel; charset=UTF-8' : 'text/csv; charset=UTF-8';
        $filename = "cashbook_{$resolvedShop->slug}_{$timeframe}_{$finalStart}_to_{$finalEnd}.{$ext}";

        $headers = [
            'Content-Type' => $contentType,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($transactions, $resolvedShop, $finalStart, $finalEnd, $totalSales, $totalExpense, $netPosition, $delimiter) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Shop Name', $resolvedShop->name], $delimiter);
            fputcsv($handle, ['Export Period', "{$finalStart} to {$finalEnd}"], $delimiter);
            fputcsv($handle, ['Total Sales', number_format($totalSales, 2)], $delimiter);
            fputcsv($handle, ['Total Expense', number_format($totalExpense, 2)], $delimiter);
            fputcsv($handle, ['Net Balance', number_format($netPosition, 2)], $delimiter);
            fputcsv($handle, [], $delimiter);

            fputcsv($handle, ['Transaction ID', 'Business Date', 'Entry Type', 'Category', 'Direction', 'Funding Source', 'Amount (Rs)', 'Notes', 'Created At'], $delimiter);

            foreach ($transactions as $tx) {
                fputcsv($handle, [
                    '#'.$tx->id,
                    $tx->business_date,
                    $tx->entryType ? $tx->entryType->name : $tx->entry_type_code,
                    $tx->entryType ? $tx->entryType->category : '-',
                    strtoupper($tx->direction ?? ''),
                    $tx->funding_source ?: 'default',
                    number_format((float) $tx->amount, 2, '.', ''),
                    $tx->notes ?: '-',
                    $tx->created_at ? $tx->created_at->format('Y-m-d H:i:s') : '-',
                ], $delimiter);
            }

            fclose($handle);
        }, 200, $headers);
    }

    public function shopSettlementPage(Request $request, int|string $shop): View
    {
        $this->ensureMainAdmin($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $clients = LedgerClient::with('shops')->where('enabled', true)->get();
        $companyAccounts = CompanyAccount::where('enabled', true)->get();
        $company = config('greenleaf');
        $currentShop = $this->resolveShop($shop);
        $currentShop->load('client', 'preset');

        return view('admin.cashbook.shops.settlement', compact(
            'shops', 'clients', 'companyAccounts', 'company', 'currentShop'
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
            'shop_id' => ['nullable', 'integer'],
            'business_date' => ['nullable', 'date_format:Y-m-d'],
            'timeframe' => ['nullable', 'in:daily,weekly,monthly,custom'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $shopId = (int) ($validated['shop_id'] ?? 1);
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

        $companyPendingEntries = ShopLedgerTransaction::with('entryType')
            ->where('shop_id', $shopId)
            ->where(fn ($q) => $q->where('company_pending_delta', '!=', 0)->orWhere('funding_source', 'company'))
            ->orderBy('id', 'desc')
            ->get();

        $settings = ShopLedgerEntrySetting::with('entryType')
            ->where('shop_id', $shopId)
            ->where('enabled', true)
            ->get();

        $payableEntryTypeIds = $settings
            ->where('include_in_payable', true)
            ->pluck('entry_type_id');
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
            ->map(function ($group, $name) use ($settlementTransactions) {
                $first = $group->first();
                $code = (string) ($first->entryType?->code ?: $first->entry_type_code);
                $recordedAmount = round((float) $group->sum(function ($tx) {
                    $direction = $tx->direction ?? ($tx->entryType?->category ?? 'income');
                    return $direction === 'expense' ? -(float) $tx->amount : (float) $tx->amount;
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

        $payableTotal = round((float) $payableRows->sum(function ($tx) {
            $direction = $tx->direction ?? ($tx->entryType?->category ?? 'income');
            return $direction === 'expense' ? -(float) $tx->amount : (float) $tx->amount;
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

            if (! empty($validated['funding_source'])) {
                $input['funding_source'] = $validated['funding_source'];
            }

            $result = $this->ledgerService->recordEntry($input);
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
        $categoryCode = $validated['category_code'] ?? null;
        $companyAccountId = isset($validated['company_account_id']) ? (int) $validated['company_account_id'] : null;
        $notes = $validated['notes'] ?? 'Payment received by admin';

        if ($categoryCode && $categoryCode !== 'all') {
            $entryType = LedgerEntryType::where('code', $categoryCode)->first();
            $categoryLabel = $entryType ? $entryType->name : $categoryCode;
            if (! str_contains(strtolower($notes), strtolower($categoryLabel))) {
                $notes = "[{$categoryLabel}] " . $notes;
            }
        }

        $userId = $request->user()?->id ?? 1;

        try {
            $posted = [];

            if ($settle > 0) {
                $res = $this->ledgerService->recordEntry([
                    'shop_id' => $shopId,
                    'business_date' => $date,
                    'entry_type_code' => 'shop_paid_company',
                    'amount' => $settle,
                    'funding_source' => 'sales',
                    'entered_by' => $userId,
                    'notes' => $notes,
                    'company_account_id' => $companyAccountId,
                ]);
                $posted[] = $res['transaction'];
            }

            if ($petty > 0) {
                $res = $this->ledgerService->recordEntry([
                    'shop_id' => $shopId,
                    'business_date' => $date,
                    'entry_type_code' => 'company_to_petty',
                    'amount' => $petty,
                    'funding_source' => 'company',
                    'entered_by' => $userId,
                    'notes' => $notes.' (Petty Top-up)',
                    'company_account_id' => $companyAccountId,
                ]);
                $posted[] = $res['transaction'];
            }

            if ($companyAccountId && ($settle + $petty) > 0) {
                $companyAccount = CompanyAccount::find($companyAccountId);
                if ($companyAccount) {
                    $companyAccount->increment('current_balance', $settle + $petty);
                }
            }

            $snapshot = $this->ledgerService->dailySummary($shopId, $date);

            return response()->json([
                'success' => true,
                'message' => '₹'.number_format($settle + $petty, 2)." accepted & processed for Shop #{$shopId}.",
                'posted' => $posted,
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
            'shop_id'                => 'required|integer',
            'entry_type_id'          => 'required|integer|exists:ledger_entry_types,id',
            'default_funding_source' => 'required|string',
            'include_in_sales'       => 'nullable|boolean',
            'include_in_expense'     => 'nullable|boolean',
            'include_in_pl'          => 'nullable|boolean',
            'generates_secondary'    => 'nullable|boolean',
        ]);

        try {
            $setting = ShopLedgerEntrySetting::updateOrCreate(
                [
                    'shop_id'       => $validated['shop_id'],
                    'entry_type_id' => $validated['entry_type_id'],
                ],
                [
                    'enabled'                   => true,
                    'default_funding_source'    => $validated['default_funding_source'],
                    'include_in_sales'          => (bool) ($validated['include_in_sales'] ?? false),
                    'include_in_expense'        => (bool) ($validated['include_in_expense'] ?? false),
                    'include_in_pl'             => (bool) ($validated['include_in_pl'] ?? true),
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
     * API: Approve every non-approved income/expense transaction for a day.
     */
    public function approveDay(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $validated = $request->validate([
            'shop_id' => ['required', 'integer'],
            'business_date' => ['required', 'date_format:Y-m-d'],
        ]);

        try {
            $updated = ShopLedgerTransaction::query()
                ->where('shop_id', (int) $validated['shop_id'])
                ->whereDate('business_date', $validated['business_date'])
                ->where('status', '!=', 'approved')
                ->where('status', '!=', 'void')
                ->update([
                    'status' => 'approved',
                    'approved_by' => $request->user()?->id,
                ]);

            return response()->json([
                'success' => true,
                'message' => $updated > 0
                    ? "Approved {$updated} entries for {$validated['business_date']}."
                    : "No pending entries found for {$validated['business_date']}.",
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
     * @param array<int, string> $entryTypeRoles
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
            $code = !empty($validated['code'])
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
                        'include_in_sales' => (bool)($validated['include_in_sales'] ?? false),
                        'include_in_income' => (bool)($validated['include_in_sales'] ?? false),
                        'include_in_expense' => (bool)($validated['include_in_expense'] ?? false),
                        'include_in_pl' => (bool)($validated['include_in_pl'] ?? true),
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
        abort_unless($user instanceof User && $user->isMainAdmin(), 403);
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
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'business_date' => ['nullable', 'date_format:Y-m-d'],
            'timeframe' => ['nullable', 'in:daily,weekly,monthly,custom'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $selectedDate = $validated['date']
            ?? $validated['business_date']
            ?? today()->toDateString();
        $timeframe = $validated['timeframe'] ?? 'daily';
        $reqStart = $validated['start_date'] ?? null;
        $reqEnd = $validated['end_date'] ?? null;

        [$startDate, $endDate] = $this->cashbookRange(
            $selectedDate,
            $timeframe,
            $reqStart,
            $reqEnd
        );

        if ($reqStart && $reqEnd && ($reqStart !== $reqEnd || $timeframe === 'custom')) {
            $timeframe = 'custom';
        }

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
    private function cashbookReportExportRows(string $selectedDate, string $timeframe, string $startDate, string $endDate): array
    {
        $overviewRequest = Request::create('/admin/cashbook/api/all-shops-overview', 'GET', [
            'business_date' => $selectedDate,
            'timeframe' => $timeframe,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
        $clientRequest = Request::create('/admin/cashbook/api/client-summary', 'GET', [
            'business_date' => $selectedDate,
            'timeframe' => $timeframe,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
        $billRequest = Request::create('/admin/cashbook/api/report-bills', 'GET', [
            'business_date' => $selectedDate,
            'timeframe' => $timeframe,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $userResolver = fn () => auth()->user();
        $overviewRequest->setUserResolver($userResolver);
        $clientRequest->setUserResolver($userResolver);
        $billRequest->setUserResolver($userResolver);

        $overview = $this->getAllShopsOverview($overviewRequest)->getData(true);
        $clients = $this->getClientSummary($clientRequest)->getData(true);
        $bills = $this->getReportBills($billRequest)->getData(true);

        $rows = [
            ['Cashbook CEO Report'],
            ['Selected Date', $selectedDate, 'Timeframe', $timeframe, 'From', $startDate, 'To', $endDate],
            [],
            ['Executive Summary'],
            ['Metric', 'Amount'],
            ['Total Sales', $overview['totals']['total_sales'] ?? 0],
            ['Total Expense', $overview['totals']['total_expense'] ?? 0],
            ['Net P/L', $overview['totals']['net_pl'] ?? 0],
            ['Closing Shop Position', $overview['totals']['closing_shop_position'] ?? 0],
            ['Company Pending', $overview['totals']['closing_company_pending'] ?? 0],
            ['Green Leaf Bills', $overview['totals']['total_green_leaf_bills'] ?? 0],
            ['Received', $overview['totals']['total_received_today'] ?? 0],
            ['Net Receivable', $clients['grand_totals']['net_receivable'] ?? 0],
            [],
            ['Client Groups'],
            ['Client', 'Shop Count', 'GL Bills', 'Received', 'Company Pending', 'Shop Position'],
        ];

        foreach ($overview['client_groups'] ?? [] as $group) {
            $groupTotals = [
                'gl_bills' => 0.0,
                'received' => 0.0,
                'company_pending' => 0.0,
                'shop_position' => 0.0,
            ];

            foreach ($group['shops'] ?? [] as $shopRow) {
                $groupTotals['gl_bills'] += (float) ($shopRow['green_leaf_bill'] ?? 0);
                $groupTotals['received'] += (float) ($shopRow['received_today'] ?? 0);
                $groupTotals['company_pending'] += (float) ($shopRow['snapshot']['closing_company_pending'] ?? 0);
                $groupTotals['shop_position'] += (float) ($shopRow['snapshot']['closing_shop_position'] ?? 0);
            }

            $rows[] = [
                $group['client']['name'] ?? 'Client',
                count($group['shops'] ?? []),
                round($groupTotals['gl_bills'], 2),
                round($groupTotals['received'], 2),
                round($groupTotals['company_pending'], 2),
                round($groupTotals['shop_position'], 2),
            ];
        }

        $rows[] = [];
        $rows[] = ['Direct Shops'];
        $rows[] = ['Shop', 'Code', 'GL Bill', 'Received', 'Company Pending', 'Shop Position'];

        foreach ($overview['direct_owned_shops'] ?? [] as $shopRow) {
            $rows[] = [
                $shopRow['shop']['name'] ?? 'Shop',
                $shopRow['shop']['code'] ?? '',
                round((float) ($shopRow['green_leaf_bill'] ?? 0), 2),
                round((float) ($shopRow['received_today'] ?? 0), 2),
                round((float) ($shopRow['snapshot']['closing_company_pending'] ?? 0), 2),
                round((float) ($shopRow['snapshot']['closing_shop_position'] ?? 0), 2),
            ];
        }

        $rows[] = [];
        $rows[] = ['Bill Details'];
        $rows[] = ['Date', 'Invoice', 'Scope', 'Client', 'Shop', 'Bill', 'Paid', 'Balance', 'Payment Status'];

        foreach ($bills['rows'] ?? [] as $bill) {
            $rows[] = [
                $bill['business_date'] ?? '',
                $bill['invoice_number'] ?? '',
                $bill['scope'] ?? '',
                $bill['client']['name'] ?? '',
                $bill['shop']['name'] ?? '',
                round((float) ($bill['final_total'] ?? 0), 2),
                round((float) ($bill['paid_amount'] ?? 0), 2),
                round((float) ($bill['balance_amount'] ?? 0), 2),
                $bill['payment_status'] ?? $bill['status'] ?? '',
            ];
        }

        return $rows;
    }

    private function reportFilename(string $prefix, string $startDate, string $endDate, string $extension): string
    {
        return "{$prefix}-{$startDate}_{$endDate}.{$extension}";
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

        if ($startDate && $endDate) {
            $parsedStart = Carbon::parse($startDate);
            $parsedEnd = Carbon::parse($endDate)->min(today());
            if ($timeframe === 'custom' || $parsedStart->toDateString() !== $parsedEnd->toDateString()) {
                if ($parsedStart->greaterThan($parsedEnd)) {
                    $parsedStart = $parsedEnd->copy();
                }

                return [$parsedStart->toDateString(), $parsedEnd->toDateString()];
            }
        }

        [$rangeStart, $rangeEnd] = match ($timeframe) {
            'weekly' => [$selectedDate->copy()->startOfWeek(), $selectedDate->copy()],
            'monthly' => [$selectedDate->copy()->startOfMonth(), $selectedDate->copy()],
            'custom' => [
                Carbon::parse($startDate ?? $businessDate),
                Carbon::parse($endDate ?? $businessDate)->min(today()),
            ],
            default => [$selectedDate->copy(), $selectedDate->copy()],
        };

        if ($rangeStart->greaterThan($rangeEnd)) {
            $rangeStart = $rangeEnd->copy();
        }

        return [$rangeStart->toDateString(), $rangeEnd->toDateString()];
    }
}
