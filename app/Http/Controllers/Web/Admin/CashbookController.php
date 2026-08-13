<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

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
use App\Models\Cashbook\PresetEntrySetting;
use App\Models\Cashbook\ShopConfigPreset;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\DailyLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
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
        private readonly CashbookShopSyncService $shopSyncService
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

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $clients = LedgerClient::with('shops')->where('enabled', true)->get();
        $companyAccounts = CompanyAccount::where('enabled', true)->get();
        $company = config('greenleaf');

        return view('admin.cashbook.reports.index', compact('shops', 'clients', 'companyAccounts', 'company'));
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
        $presets = ShopConfigPreset::with(['entrySettings.entryType', 'shops'])->where('enabled', true)->get();
        $entryTypes = LedgerEntryType::where('active', true)->orderBy('display_order')->get();
        $companyAccounts = CompanyAccount::where('enabled', true)->get();
        $company = config('greenleaf');
        $currentShop = $shops->first();

        return view('admin.cashbook.settings.index', compact(
            'shops', 'clients', 'presets', 'entryTypes', 'companyAccounts', 'company', 'currentShop'
        ));
    }

    public function presetsPage(Request $request): View
    {
        $this->ensureMainAdmin($request);

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $clients = LedgerClient::with('shops')->where('enabled', true)->get();
        $presets = ShopConfigPreset::with(['entrySettings.entryType', 'shops'])->where('enabled', true)->get();
        $entryTypes = LedgerEntryType::where('active', true)->orderBy('display_order')->get();
        $companyAccounts = CompanyAccount::where('enabled', true)->get();
        $company = config('greenleaf');
        $currentShop = $shops->first();

        return view('admin.cashbook.settings.presets', compact(
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

        $shopId = (int) $request->input('shop_id', 1);
        $date = $request->input('business_date', today()->toDateString());
        $timeframe = $request->input('timeframe', 'daily');
        $perPage = (int) $request->input('per_page', 50);
        $month = substr($date, 0, 7);

        $carbon = Carbon::parse($date);
        $startOfWeek = $carbon->copy()->startOfWeek()->format('Y-m-d');
        $endOfWeek = $carbon->copy()->endOfWeek()->format('Y-m-d');
        $startOfMonth = $carbon->copy()->startOfMonth()->format('Y-m-d');
        $endOfMonth = $carbon->copy()->endOfMonth()->format('Y-m-d');

        [$finalStart, $finalEnd] = match ($timeframe) {
            'weekly' => [$startOfWeek, $endOfWeek],
            'monthly' => [$startOfMonth, $endOfMonth],
            'custom' => [
                (string) $request->input('start_date', $date),
                (string) $request->input('end_date', $date),
            ],
            default => [$date, $date],
        };

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

        $dailySnapshot = $this->ledgerService->dailySummary($shopId, $date);

        $snapshotData = [
            'total_sales' => $totalSales,
            'total_expense' => $totalExpense,
            'net_pl' => $netPl,
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

        return response()->json([
            'success' => true,
            'timeframe' => $timeframe,
            'start_of_week' => $startOfWeek,
            'end_of_week' => $endOfWeek,
            'snapshot' => $snapshotData,
            'transactions' => $transactions,
            'month_transactions' => $monthTransactions,
            'petty_entries' => $pettyEntries,
            'company_pending_entries' => $companyPendingEntries,
            'settings' => $settings,
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
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
        $defaultDate = today()->subDay()->toDateString();
        $startDate = $validated['start_date'] ?? $validated['business_date'] ?? $defaultDate;
        $endDate = $validated['end_date'] ?? $validated['business_date'] ?? $defaultDate;

        $shops = $this->shopSyncService->syncAndGetProfiles();
        $shops->load('client');

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

            $glBills = (float) ShopLedgerTransaction::where('shop_id', $shop->shop_id)
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

            $isDirect = $shop->client_id === null
                && $shop->profile_template === 'direct_buyer'
                && (optional($shop->shop)->accounting_mode === 'owned');

            $overview[] = [
                'shop' => $shop,
                'is_direct' => $isDirect,
                'snapshot' => $snapshot,
                'green_leaf_bill' => $glBills,
                'company_paid_expenses' => $compExpenses,
                'received_today' => $receivedToday,
                'net_receivable' => $netReceivable,
            ];

            $totals['total_sales'] += (float) $snapshot->total_sales;
            $totals['total_expense'] += (float) $snapshot->total_expense;
            $totals['net_pl'] += (float) $snapshot->net_pl;
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
        $notes = $validated['notes'] ?? 'Payment received by admin';
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
                ]);
                $posted[] = $res['transaction'];
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

        $date = $request->input('business_date', today()->toDateString());
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
                $snapshot = $this->ledgerService->dailySummary($shop->shop_id, $date);

                $glBills = (float) ShopLedgerTransaction::where('shop_id', $shop->shop_id)
                    ->where('business_date', $date)
                    ->where('entry_type_id', fn ($q) => $q->select('id')->from('ledger_entry_types')->where('code', 'purchase_bill'))
                    ->sum('amount');

                $received = (float) ShopLedgerTransaction::where('shop_id', $shop->shop_id)
                    ->where('business_date', $date)
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
     * API: All preset configurations with entry settings.
     */
    public function getPresets(Request $request): JsonResponse
    {
        $this->ensureMainAdmin($request);

        $presets = ShopConfigPreset::with(['entrySettings.entryType', 'shops'])
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

            return response()->json([
                'success' => true,
                'message' => "Shop '{$shop->name}' assigned to preset.",
                'shop' => $shop,
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

        $selectedDate ??= today()->subDay()->toDateString();

        return view('admin.cashbook.index', compact(
            'shops', 'clients', 'entryTypes', 'companyAccounts', 'company', 'initialTab', 'initialShopId', 'selectedDate'
        ));
    }

    private function selectedDate(Request $request): string
    {
        $validated = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        return $validated['date'] ?? today()->subDay()->toDateString();
    }
}
