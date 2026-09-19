<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\PurchaseInvoice;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\Supplier;
use App\Services\Cashbook\DTO\AccountBalanceReportData;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AccountBalanceReportService
{
    /**
     * Generate the comprehensive Cashbook Account Balance Report.
     */
    public function generateReport(string $preset = 'this_month', ?string $fromDate = null, ?string $toDate = null): AccountBalanceReportData
    {
        [$startDate, $endDate, $periodLabel] = $this->resolvePeriodDates($preset, $fromDate, $toDate);

        $accountsData = $this->calculateAccountBreakdowns($startDate, $endDate);
        $floatingInData = $this->calculateFloatingIn($endDate);
        $floatingOutData = $this->calculateFloatingOut($endDate);
        $receivablesData = $this->calculateReceivables($startDate, $endDate, $floatingInData['items']);
        $payablesData = $this->calculatePayables($startDate, $endDate);
        $movementsData = $this->calculatePeriodMovements($startDate, $endDate, $accountsData, $floatingInData, $floatingOutData, $receivablesData, $payablesData);
        $transactionsCollection = $this->collectPeriodTransactions($startDate, $endDate);

        $totalActualBalance = round((float) array_sum(array_column($accountsData, 'closing_actual')), 2);
        $totalFloatingIn = round((float) $floatingInData['total'], 2);
        $totalFloatingOut = round((float) $floatingOutData['total'], 2);
        $netFloating = round($totalFloatingIn - $totalFloatingOut, 2);
        $totalReceivables = round((float) $receivablesData['total_closing'], 2);
        $totalPayables = round((float) $payablesData['total_closing'], 2);
        $expectedBalance = round($totalActualBalance + $totalFloatingIn - $totalFloatingOut, 2);

        $summary = [
            'actual_balance' => $totalActualBalance,
            'floating_in' => $totalFloatingIn,
            'floating_out' => $totalFloatingOut,
            'net_floating' => $netFloating,
            'receivables' => $totalReceivables,
            'payables' => $totalPayables,
            'expected_balance' => $expectedBalance,
        ];

        $period = [
            'preset' => $preset,
            'from_date' => $startDate,
            'to_date' => $endDate,
            'label' => $periodLabel,
        ];

        return new AccountBalanceReportData(
            period: $period,
            summary: $summary,
            accounts: $accountsData,
            floatingIn: $floatingInData['items'],
            floatingOut: $floatingOutData['items'],
            receivables: $receivablesData['items'],
            payables: $payablesData['items'],
            movements: $movementsData,
            transactions: $transactionsCollection,
        );
    }

    /**
     * Resolve date ranges from preset or custom dates.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolvePeriodDates(string $preset, ?string $fromDate, ?string $toDate): array
    {
        $today = Carbon::today();

        return match ($preset) {
            'today' => [
                $today->toDateString(),
                $today->toDateString(),
                'Today ('.$today->format('d M Y').')',
            ],
            'yesterday' => [
                $today->copy()->subDay()->toDateString(),
                $today->copy()->subDay()->toDateString(),
                'Yesterday ('.$today->copy()->subDay()->format('d M Y').')',
            ],
            'this_week' => [
                $today->copy()->startOfWeek()->toDateString(),
                $today->toDateString(),
                'This Week ('.$today->copy()->startOfWeek()->format('d M').' - '.$today->format('d M Y').')',
            ],
            'last_week' => [
                $today->copy()->subWeek()->startOfWeek()->toDateString(),
                $today->copy()->subWeek()->endOfWeek()->toDateString(),
                'Last Week ('.$today->copy()->subWeek()->startOfWeek()->format('d M').' - '.$today->copy()->subWeek()->endOfWeek()->format('d M Y').')',
            ],
            'last_month' => [
                $today->copy()->subMonth()->startOfMonth()->toDateString(),
                $today->copy()->subMonth()->endOfMonth()->toDateString(),
                'Last Month ('.$today->copy()->subMonth()->format('M Y').')',
            ],
            'this_quarter' => [
                $today->copy()->startOfQuarter()->toDateString(),
                $today->toDateString(),
                'This Quarter (Q'.$today->quarter.' '.$today->year.')',
            ],
            'this_year' => [
                $today->copy()->startOfYear()->toDateString(),
                $today->toDateString(),
                'This Year ('.$today->year.')',
            ],
            'all_time' => [
                '2020-01-01',
                $today->toDateString(),
                'All Time (Up to '.$today->format('d M Y').')',
            ],
            'custom' => [
                $fromDate ?: $today->copy()->startOfMonth()->toDateString(),
                $toDate ?: $today->toDateString(),
                'Custom ('.Carbon::parse($fromDate ?: $today->copy()->startOfMonth()->toDateString())->format('d M Y').' - '.Carbon::parse($toDate ?: $today->toDateString())->format('d M Y').')',
            ],
            default => [
                $today->copy()->startOfMonth()->toDateString(),
                $today->toDateString(),
                'This Month ('.$today->format('M Y').')',
            ],
        };
    }

    /**
     * Calculate Account Breakdown per CompanyAccount.
     *
     * @return array<int, array<string, mixed>>
     */
    private function calculateAccountBreakdowns(string $startDate, string $endDate): array
    {
        $accounts = CompanyAccount::query()
            ->where('enabled', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        $result = [];

        foreach ($accounts as $account) {
            $currentBalance = (float) $account->current_balance;

            // Confirmed movements after endDate to reconcile historical balance
            $afterEndDateIn = (float) CompanyAccountStatementEntry::query()
                ->where('company_account_id', $account->id)
                ->where('is_finalized', 1)
                ->whereIn('status', ['cleared', 'verified', 'reconciled'])
                ->whereDate('transaction_date', '>', $endDate)
                ->where('direction', 'in')
                ->sum('amount');

            $afterEndDateOut = (float) CompanyAccountStatementEntry::query()
                ->where('company_account_id', $account->id)
                ->where('is_finalized', 1)
                ->whereIn('status', ['cleared', 'verified', 'reconciled'])
                ->whereDate('transaction_date', '>', $endDate)
                ->where('direction', 'out')
                ->sum('amount');

            $closingActual = round($currentBalance - $afterEndDateIn + $afterEndDateOut, 2);

            // Confirmed movements within period [startDate, endDate]
            $periodIn = (float) CompanyAccountStatementEntry::query()
                ->where('company_account_id', $account->id)
                ->where('is_finalized', 1)
                ->whereIn('status', ['cleared', 'verified', 'reconciled'])
                ->whereDate('transaction_date', '>=', $startDate)
                ->whereDate('transaction_date', '<=', $endDate)
                ->where('direction', 'in')
                ->sum('amount');

            $periodOut = (float) CompanyAccountStatementEntry::query()
                ->where('company_account_id', $account->id)
                ->where('is_finalized', 1)
                ->whereIn('status', ['cleared', 'verified', 'reconciled'])
                ->whereDate('transaction_date', '>=', $startDate)
                ->whereDate('transaction_date', '<=', $endDate)
                ->where('direction', 'out')
                ->sum('amount');

            $openingActual = round($closingActual - $periodIn + $periodOut, 2);

            $result[] = [
                'id' => $account->id,
                'name' => $account->name,
                'account_type' => $account->account_type,
                'bank_name' => $account->bank_name ?: ($account->account_type === 'cash' ? 'Company Cash' : 'Bank'),
                'account_number' => $account->account_number ?: 'N/A',
                'is_default' => (bool) $account->is_default,
                'opening_actual' => $openingActual,
                'confirmed_in' => round($periodIn, 2),
                'confirmed_out' => round($periodOut, 2),
                'closing_actual' => $closingActual,
            ];
        }

        return $result;
    }

    /**
     * Calculate Floating In money (money inbound to company not yet cleared).
     *
     * @return array{total: float, items: array<int, array<string, mixed>>}
     */
    private function calculateFloatingIn(string $endDate): array
    {
        $items = [];
        $today = Carbon::today();

        // 1. Pending Shop Invoice Payment Requests (Cheques, bank transfers, online)
        $paymentRequests = ShopInvoicePaymentRequest::query()
            ->with(['shop'])
            ->where('status', '!=', 'rejected')
            ->where(function ($query) use ($endDate): void {
                $query->whereNull('payment_date')
                    ->orWhereDate('payment_date', '<=', $endDate);
            })
            ->where(function ($query): void {
                $query->where('status', 'pending')
                    ->orWhere('cheque_status', 'pending')
                    ->orWhereIn('reconciliation_status', ['pending', 'floating']);
            })
            ->get();

        foreach ($paymentRequests as $req) {
            $amount = (float) ($req->floating_amount > 0 ? $req->floating_amount : ($req->approved_amount ?: $req->requested_amount));
            if ($amount <= 0) {
                continue;
            }

            $date = Carbon::parse($req->payment_date ?: $req->created_at);
            $ageDays = max(0, (int) $date->diffInDays($today));

            $items[] = [
                'id' => 'payment_req_'.$req->id,
                'type' => 'Shop Payment Request',
                'date' => $date->format('Y-m-d'),
                'from' => $req->shop?->name ?: 'Shop #'.$req->shop_id,
                'shop_id' => $req->shop_id,
                'to_account' => $req->companyAccount?->name ?: 'Company Bank Account',
                'source' => ucfirst((string) $req->payment_method).' Payment',
                'amount' => round($amount, 2),
                'status' => strtoupper((string) ($req->cheque_status ?: $req->status)),
                'reference' => $req->cheque_number ?: ($req->transaction_reference ?: 'REQ-'.$req->id),
                'age' => $ageDays,
            ];
        }

        // 2. Unfinalized Inbound Statement Entries
        $unfinalizedStatements = CompanyAccountStatementEntry::query()
            ->with(['companyAccount', 'sourceRecord'])
            ->where('is_finalized', 0)
            ->where('direction', 'in')
            ->whereNotIn('status', ['superseded', 'duplicate_flagged', 'rejected'])
            ->where(function ($query) use ($endDate): void {
                $query->whereNull('transaction_date')
                    ->orWhereDate('transaction_date', '<=', $endDate);
            })
            ->get();

        foreach ($unfinalizedStatements as $stmt) {
            $amount = (float) $stmt->amount;
            if ($amount <= 0) {
                continue;
            }

            $date = Carbon::parse($stmt->transaction_date ?: $stmt->created_at);
            $ageDays = max(0, (int) $date->diffInDays($today));

            $fromName = 'External Party';
            if ($stmt->sourceRecord && isset($stmt->sourceRecord->shop_id)) {
                $shop = Shop::find($stmt->sourceRecord->shop_id);
                $fromName = $shop?->name ?: 'Shop #'.$stmt->sourceRecord->shop_id;
            }

            $items[] = [
                'id' => 'stmt_'.$stmt->id,
                'type' => 'Statement Entry',
                'date' => $date->format('Y-m-d'),
                'from' => $fromName,
                'shop_id' => $stmt->sourceRecord?->shop_id ?? null,
                'to_account' => $stmt->companyAccount?->name ?: 'Company Account',
                'source' => 'Bank Statement Record',
                'amount' => round($amount, 2),
                'status' => strtoupper((string) ($stmt->status ?: 'FLOATING')),
                'reference' => $stmt->reference_number ?: 'STMT-'.$stmt->id,
                'age' => $ageDays,
            ];
        }

        $totalFloatingIn = array_sum(array_column($items, 'amount'));

        return [
            'total' => round((float) $totalFloatingIn, 2),
            'items' => $items,
        ];
    }

    /**
     * Calculate Floating Out money (money outbound from company not yet cleared).
     *
     * @return array{total: float, items: array<int, array<string, mixed>>}
     */
    private function calculateFloatingOut(string $endDate): array
    {
        $items = [];
        $today = Carbon::today();

        // Unfinalized Outbound Statement Entries & Pending Vendor Payments
        $unfinalizedStatements = CompanyAccountStatementEntry::query()
            ->with(['companyAccount', 'sourceRecord'])
            ->where('is_finalized', 0)
            ->where('direction', 'out')
            ->whereNotIn('status', ['superseded', 'duplicate_flagged', 'rejected'])
            ->where(function ($query) use ($endDate): void {
                $query->whereNull('transaction_date')
                    ->orWhereDate('transaction_date', '<=', $endDate);
            })
            ->get();

        foreach ($unfinalizedStatements as $stmt) {
            $amount = (float) $stmt->amount;
            if ($amount <= 0) {
                continue;
            }

            $date = Carbon::parse($stmt->transaction_date ?: $stmt->created_at);
            $ageDays = max(0, (int) $date->diffInDays($today));

            $items[] = [
                'id' => 'stmt_out_'.$stmt->id,
                'type' => 'Outbound Statement Entry',
                'date' => $date->format('Y-m-d'),
                'from_account' => $stmt->companyAccount?->name ?: 'Company Account',
                'to' => $stmt->party_name ?: 'Vendor / Party',
                'source' => 'Outbound Bank Transfer / Payment',
                'amount' => round($amount, 2),
                'status' => strtoupper((string) ($stmt->status ?: 'FLOATING OUT')),
                'reference' => $stmt->reference_number ?: 'STMT-OUT-'.$stmt->id,
                'age' => $ageDays,
            ];
        }

        $totalFloatingOut = array_sum(array_column($items, 'amount'));

        return [
            'total' => round((float) $totalFloatingOut, 2),
            'items' => $items,
        ];
    }

    /**
     * Calculate Shop Receivables (Company is owed money).
     * Applies Double-Count Prevention using Floating In allocations.
     *
     * @param  array<int, array<string, mixed>>  $floatingInItems
     * @return array{total_closing: float, items: array<int, array<string, mixed>>}
     */
    private function calculateReceivables(string $startDate, string $endDate, array $floatingInItems): array
    {
        $shops = Shop::query()->where('status', 'active')->orderBy('name')->get();

        // Index floating in per shop to prevent double counting
        $floatingByShop = [];
        foreach ($floatingInItems as $item) {
            $sId = $item['shop_id'] ?? null;
            if ($sId) {
                $floatingByShop[$sId] = ($floatingByShop[$sId] ?? 0.0) + (float) $item['amount'];
            }
        }

        $items = [];
        $totalClosing = 0.0;

        foreach ($shops as $shop) {
            // Latest snapshot as of endDate
            $closingSnapshot = ShopDailyLedgerSnapshot::query()
                ->where('shop_id', $shop->id)
                ->whereDate('business_date', '<=', $endDate)
                ->orderBy('business_date', 'desc')
                ->first();

            // Opening snapshot before startDate
            $openingSnapshot = ShopDailyLedgerSnapshot::query()
                ->where('shop_id', $shop->id)
                ->whereDate('business_date', '<', $startDate)
                ->orderBy('business_date', 'desc')
                ->first();

            $openingPosition = round((float) ($openingSnapshot?->closing_shop_position ?? 0.0), 2);
            $grossClosingPosition = round((float) ($closingSnapshot?->closing_shop_position ?? 0.0), 2);

            // New receivables & received during period
            $periodTransactions = ShopLedgerTransaction::query()
                ->where('shop_id', $shop->id)
                ->whereBetween('business_date', [$startDate, $endDate])
                ->whereNotIn('status', ['void', 'voided', 'reversed'])
                ->get();

            $newReceivable = (float) $periodTransactions->filter(fn ($t) => $t->direction === 'expense' || $t->payable_direction === 'plus')->sum('amount');
            $received = (float) $periodTransactions->filter(fn ($t) => $t->direction === 'income' || $t->payable_direction === 'minus')->sum('amount');
            $reversed = (float) ShopLedgerTransaction::query()
                ->where('shop_id', $shop->id)
                ->whereBetween('business_date', [$startDate, $endDate])
                ->whereIn('status', ['void', 'voided', 'reversed'])
                ->sum('amount');

            // DOUBLE-COUNT PREVENTION: Subtract floating in allocated to this shop's receivable
            $floatingInOffset = round((float) ($floatingByShop[$shop->id] ?? 0.0), 2);
            $netClosingPosition = max(0.0, round($grossClosingPosition - $floatingInOffset, 2));

            $items[] = [
                'party' => $shop->name.' ('.$shop->code.')',
                'shop_id' => $shop->id,
                'type' => 'Shop Receivable',
                'opening_outstanding' => $openingPosition,
                'new_receivable' => round($newReceivable, 2),
                'received' => round($received, 2),
                'reversed' => round($reversed, 2),
                'floating_in_offset' => $floatingInOffset,
                'gross_closing' => $grossClosingPosition,
                'closing_outstanding' => $netClosingPosition,
                'status' => $netClosingPosition > 0 ? 'OUTSTANDING' : 'SETTLED',
                'reference' => 'SHOP-'.$shop->code,
            ];

            $totalClosing += $netClosingPosition;
        }

        return [
            'total_closing' => round($totalClosing, 2),
            'items' => $items,
        ];
    }

    /**
     * Calculate Payables (Company owes money to vendors / parties).
     *
     * @return array{total_closing: float, items: array<int, array<string, mixed>>}
     */
    private function calculatePayables(string $startDate, string $endDate): array
    {
        $suppliers = Supplier::query()->orderBy('name')->take(50)->get();

        $items = [];
        $totalClosing = 0.0;

        foreach ($suppliers as $supplier) {
            $invoicesBeforeStart = PurchaseInvoice::query()
                ->where('supplier_id', $supplier->id)
                ->whereDate('created_at', '<', $startDate)
                ->where('status', '!=', 'cancelled')
                ->get();

            $openingPayable = round((float) $invoicesBeforeStart->sum(fn ($i) => (float) ($i->total_amount ?: $i->amount) - (float) $i->paid_amount), 2);

            $periodInvoices = PurchaseInvoice::query()
                ->where('supplier_id', $supplier->id)
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->where('status', '!=', 'cancelled')
                ->get();

            $newPayable = round((float) $periodInvoices->sum(fn ($i) => (float) ($i->total_amount ?: $i->amount)), 2);
            $paid = round((float) $periodInvoices->sum('paid_amount'), 2);
            $reversed = round((float) PurchaseInvoice::query()
                ->where('supplier_id', $supplier->id)
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->where('status', 'cancelled')
                ->sum('amount'), 2);

            $closingPayable = max(0.0, round($openingPayable + $newPayable - $paid - $reversed, 2));

            if ($openingPayable > 0 || $newPayable > 0 || $closingPayable > 0) {
                $items[] = [
                    'party' => $supplier->name,
                    'type' => 'Vendor Payable',
                    'opening_payable' => $openingPayable,
                    'new_payable' => $newPayable,
                    'paid' => $paid,
                    'reversed' => $reversed,
                    'closing_payable' => $closingPayable,
                    'status' => $closingPayable > 0 ? 'PAYABLE OUTSTANDING' : 'PAID',
                    'reference' => 'VENDOR-'.$supplier->id,
                ];

                $totalClosing += $closingPayable;
            }
        }

        return [
            'total_closing' => round($totalClosing, 2),
            'items' => $items,
        ];
    }

    /**
     * Calculate Period Movement matrix (Opening -> Activity -> Closing).
     *
     * @param  array<int, array<string, mixed>>  $accountsData
     * @param  array{total: float, items: array<int, array<string, mixed>>}  $floatingInData
     * @param  array{total: float, items: array<int, array<string, mixed>>}  $floatingOutData
     * @param  array{total_closing: float, items: array<int, array<string, mixed>>}  $receivablesData
     * @param  array{total_closing: float, items: array<int, array<string, mixed>>}  $payablesData
     * @return array<string, array{opening: float, activity: float, closing: float}>
     */
    private function calculatePeriodMovements(
        string $startDate,
        string $endDate,
        array $accountsData,
        array $floatingInData,
        array $floatingOutData,
        array $receivablesData,
        array $payablesData
    ): array {
        $actualOpening = round((float) array_sum(array_column($accountsData, 'opening_actual')), 2);
        $actualClosing = round((float) array_sum(array_column($accountsData, 'closing_actual')), 2);
        $actualActivity = round($actualClosing - $actualOpening, 2);

        $recOpening = round((float) array_sum(array_column($receivablesData['items'], 'opening_outstanding')), 2);
        $recClosing = round((float) $receivablesData['total_closing'], 2);
        $recActivity = round($recClosing - $recOpening, 2);

        $payOpening = round((float) array_sum(array_column($payablesData['items'], 'opening_payable')), 2);
        $payClosing = round((float) $payablesData['total_closing'], 2);
        $payActivity = round($payClosing - $payOpening, 2);

        $floatInClosing = round((float) $floatingInData['total'], 2);
        $floatOutClosing = round((float) $floatingOutData['total'], 2);

        return [
            'actual_balance' => [
                'opening' => $actualOpening,
                'activity' => $actualActivity,
                'closing' => $actualClosing,
            ],
            'receivables' => [
                'opening' => $recOpening,
                'activity' => $recActivity,
                'closing' => $recClosing,
            ],
            'payables' => [
                'opening' => $payOpening,
                'activity' => $payActivity,
                'closing' => $payClosing,
            ],
            'floating_in' => [
                'opening' => 0.0,
                'activity' => $floatInClosing,
                'closing' => $floatInClosing,
            ],
            'floating_out' => [
                'opening' => 0.0,
                'activity' => $floatOutClosing,
                'closing' => $floatOutClosing,
            ],
        ];
    }

    /**
     * Collect period transactions flat list for drill down.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function collectPeriodTransactions(string $startDate, string $endDate): Collection
    {
        $statements = CompanyAccountStatementEntry::query()
            ->with(['companyAccount'])
            ->whereDate('transaction_date', '>=', $startDate)
            ->whereDate('transaction_date', '<=', $endDate)
            ->whereNotIn('status', ['superseded', 'duplicate_flagged'])
            ->latest('id')
            ->take(100)
            ->get()
            ->map(fn ($s) => [
                'id' => 'stmt_'.$s->id,
                'date' => $s->transaction_date ? $s->transaction_date->format('Y-m-d') : $s->created_at->format('Y-m-d'),
                'account' => $s->companyAccount?->name ?: 'Company Account',
                'description' => $s->narration ?: 'Statement Transaction',
                'direction' => strtoupper((string) $s->direction),
                'amount' => (float) $s->amount,
                'status' => strtoupper((string) ($s->is_finalized ? 'CLEARED' : ($s->status ?: 'FLOATING'))),
                'reference' => $s->reference ?: 'STMT-'.$s->id,
            ]);

        return collect($statements->all());
    }
}
