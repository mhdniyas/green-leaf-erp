<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCredit;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorSettlement;
use App\Services\Cashbook\DTO\AccountBalanceReportData;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

class AccountBalanceReportService
{
    /**
     * Generate the comprehensive Cashbook Account Balance Report.
     */
    public function generateReport(
        string $preset = 'this_month',
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $periodMode = null,
        ?string $month = null,
        ?string $date = null
    ): AccountBalanceReportData {
        [$startDate, $endDate, $periodLabel, $mode, $monthStr, $prevMonth, $nextMonth, $monthTitle, $formattedRange] = $this->resolvePeriodDates(
            preset: $preset,
            fromDate: $fromDate,
            toDate: $toDate,
            periodMode: $periodMode,
            month: $month,
            date: $date
        );

        $accountsData = $this->calculateAccountBreakdowns($startDate, $endDate);
        $floatingInData = $this->calculateFloatingIn($startDate, $endDate);
        $floatingOutData = $this->calculateFloatingOut($startDate, $endDate);
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
            'mode' => $mode,
            'month' => $monthStr,
            'prev_month' => $prevMonth,
            'next_month' => $nextMonth,
            'month_title' => $monthTitle,
            'from_date' => $startDate,
            'to_date' => $endDate,
            'formatted_range' => $formattedRange,
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
     * Resolve date ranges from mode, month, date or custom dates.
     *
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string, 7: string, 8: string}
     */
    private function resolvePeriodDates(
        string $preset,
        ?string $fromDate,
        ?string $toDate,
        ?string $periodMode = null,
        ?string $month = null,
        ?string $date = null
    ): array {
        $today = Carbon::today();

        // Determine active mode (Month | Day | Custom)
        $mode = $periodMode ?: (match ($preset) {
            'today', 'yesterday' => 'day',
            'custom' => 'custom',
            default => 'month',
        });

        if ($mode === 'day') {
            $targetDate = $date ?: ($fromDate ?: ($preset === 'yesterday' ? $today->copy()->subDay()->toDateString() : $today->toDateString()));
            $cDate = Carbon::parse($targetDate);
            $startDate = $cDate->toDateString();
            $endDate = $cDate->toDateString();
            $label = $cDate->format('d M Y');
            $monthStr = $cDate->format('Y-m');
        } elseif ($mode === 'custom') {
            $startDate = $fromDate ?: $today->copy()->startOfMonth()->toDateString();
            $endDate = $toDate ?: $today->toDateString();
            $label = Carbon::parse($startDate)->format('d M Y').' – '.Carbon::parse($endDate)->format('d M Y');
            $monthStr = Carbon::parse($startDate)->format('Y-m');
        } else {
            // Month mode (default)
            $mode = 'month';
            $monthStr = $month ?: $today->format('Y-m');
            try {
                $cMonth = Carbon::createFromFormat('Y-m', $monthStr);
            } catch (\Throwable) {
                $cMonth = $today->copy();
                $monthStr = $today->format('Y-m');
            }

            $startDate = $cMonth->copy()->startOfMonth()->toDateString();
            $endDate = $cMonth->copy()->endOfMonth()->toDateString();
            $label = $cMonth->format('F Y');
        }

        $cMonthRef = Carbon::createFromFormat('Y-m', $monthStr);
        $prevMonth = $cMonthRef->copy()->subMonth()->format('Y-m');
        $nextMonth = $cMonthRef->copy()->addMonth()->format('Y-m');
        $monthTitle = strtoupper($cMonthRef->format('F Y'));
        $formattedRange = $startDate === $endDate
            ? Carbon::parse($startDate)->format('d M Y')
            : Carbon::parse($startDate)->format('d M Y').' – '.Carbon::parse($endDate)->format('d M Y');

        return [
            $startDate,
            $endDate,
            $label,
            $mode,
            $monthStr,
            $prevMonth,
            $nextMonth,
            $monthTitle,
            $formattedRange,
        ];
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
    /**
     * Calculate Floating In money (money inbound to company not yet cleared).
     *
     * @return array{total: float, items: array<int, array<string, mixed>>}
     */
    private function calculateFloatingIn(string $startDate, string $endDate): array
    {
        $items = [];
        $today = Carbon::today();

        // 1. Pending Shop Invoice Payment Requests (Cheques, bank transfers, online)
        $paymentRequests = ShopInvoicePaymentRequest::query()
            ->with(['shop'])
            ->where('status', '!=', 'rejected')
            ->where(function ($query) use ($startDate, $endDate): void {
                $query->where(function ($q) use ($startDate, $endDate): void {
                    $q->whereNotNull('payment_date')
                        ->whereDate('payment_date', '>=', $startDate)
                        ->whereDate('payment_date', '<=', $endDate);
                })->orWhere(function ($q) use ($startDate, $endDate): void {
                    $q->whereNull('payment_date')
                        ->whereDate('created_at', '>=', $startDate)
                        ->whereDate('created_at', '<=', $endDate);
                });
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

            $detailsUrl = $req->shop_id ? route('admin.cashbook.shop.show', $req->shop_id) : null;
            $deleteUrl = route('admin.cashbook.account-balance.payment-requests.delete', $req->id);

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
                'details_url' => $detailsUrl,
                'delete_url' => $deleteUrl,
            ];
        }

        // 2. Unfinalized Inbound Statement Entries
        $unfinalizedStatements = CompanyAccountStatementEntry::query()
            ->with(['companyAccount', 'sourceRecord'])
            ->where('is_finalized', 0)
            ->where('direction', 'in')
            ->whereNotIn('status', ['superseded', 'duplicate_flagged', 'rejected'])
            ->where(function ($query) use ($startDate, $endDate): void {
                $query->where(function ($q) use ($startDate, $endDate): void {
                    $q->whereNotNull('transaction_date')
                        ->whereDate('transaction_date', '>=', $startDate)
                        ->whereDate('transaction_date', '<=', $endDate);
                })->orWhere(function ($q) use ($startDate, $endDate): void {
                    $q->whereNull('transaction_date')
                        ->whereDate('created_at', '>=', $startDate)
                        ->whereDate('created_at', '<=', $endDate);
                });
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

            $detailsUrl = $stmt->public_uuid ? route('admin.cashbook.finance.reconciliation', ['statementRef' => $stmt->public_uuid]) : null;
            $deleteUrl = route('admin.cashbook.account-balance.statements.delete', $stmt->public_uuid ?: $stmt->id);

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
                'details_url' => $detailsUrl,
                'delete_url' => $deleteUrl,
            ];
        }

        // Sort items by date descending (latest first)
        usort($items, fn ($a, $b) => strcmp((string) $b['date'], (string) $a['date']));

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
    private function calculateFloatingOut(string $startDate, string $endDate): array
    {
        $items = [];
        $today = Carbon::today();

        // Unfinalized Outbound Statement Entries & Pending Payments
        $unfinalizedStatements = CompanyAccountStatementEntry::query()
            ->with([
                'companyAccount',
                'counterpart',
                'sourceRecord' => function (MorphTo $morphTo): void {
                    $morphTo->morphWith([
                        PurchaserCredit::class => ['purchaser'],
                        VendorSettlement::class => ['supplier'],
                        PurchaseInvoice::class => ['supplier'],
                        ShopLedgerTransaction::class => ['shop'],
                    ]);
                },
            ])
            ->where('is_finalized', 0)
            ->where('direction', 'out')
            ->whereNotIn('status', ['superseded', 'duplicate_flagged', 'rejected'])
            ->where(function ($query) use ($startDate, $endDate): void {
                $query->where(function ($q) use ($startDate, $endDate): void {
                    $q->whereNotNull('transaction_date')
                        ->whereDate('transaction_date', '>=', $startDate)
                        ->whereDate('transaction_date', '<=', $endDate);
                })->orWhere(function ($q) use ($startDate, $endDate): void {
                    $q->whereNull('transaction_date')
                        ->whereDate('created_at', '>=', $startDate)
                        ->whereDate('created_at', '<=', $endDate);
                });
            })
            ->get();

        foreach ($unfinalizedStatements as $stmt) {
            $amount = (float) $stmt->amount;
            if ($amount <= 0) {
                continue;
            }

            $date = Carbon::parse($stmt->transaction_date ?: $stmt->created_at);
            $ageDays = max(0, (int) $date->diffInDays($today));

            $toParty = $this->resolveFloatingOutPartyName($stmt);

            $detailsUrl = $stmt->public_uuid ? route('admin.cashbook.finance.reconciliation', ['statementRef' => $stmt->public_uuid]) : null;
            $deleteUrl = route('admin.cashbook.account-balance.statements.delete', $stmt->public_uuid ?: $stmt->id);

            $items[] = [
                'id' => 'stmt_out_'.$stmt->id,
                'type' => 'Outbound Statement Entry',
                'date' => $date->format('Y-m-d'),
                'from_account' => $stmt->companyAccount?->name ?: 'Company Account',
                'to' => $toParty,
                'source' => $stmt->source_label ?: 'Outbound Bank Transfer / Payment',
                'amount' => round($amount, 2),
                'status' => strtoupper((string) ($stmt->status ?: 'FLOATING OUT')),
                'reference' => $stmt->reference ?: 'STMT-OUT-'.$stmt->id,
                'age' => $ageDays,
                'details_url' => $detailsUrl,
                'delete_url' => $deleteUrl,
            ];
        }

        // Sort items by date descending (latest first)
        usort($items, fn ($a, $b) => strcmp((string) $b['date'], (string) $a['date']));

        $totalFloatingOut = array_sum(array_column($items, 'amount'));

        return [
            'total' => round((float) $totalFloatingOut, 2),
            'items' => $items,
        ];
    }

    /**
     * Resolve target party name for Floating Out money items.
     */
    private function resolveFloatingOutPartyName(CompanyAccountStatementEntry $stmt): string
    {
        // 1. Check counterpart relation
        if ($stmt->counterpart) {
            $counterpart = $stmt->counterpart;

            if ($counterpart instanceof User) {
                return $counterpart->name.' (Purchaser)';
            }

            if ($counterpart instanceof Supplier) {
                return $counterpart->name.' (Vendor)';
            }

            if ($counterpart instanceof Shop) {
                return $counterpart->name.' (Shop)';
            }
        }

        // 2. Check sourceRecord relation
        if ($stmt->sourceRecord) {
            $source = $stmt->sourceRecord;

            if ($source instanceof PurchaserCredit) {
                $purchaserName = $source->purchaser?->name;

                return $purchaserName ? $purchaserName.' (Purchaser)' : 'Purchaser';
            }

            if ($source instanceof VendorSettlement) {
                $vendorName = $source->supplier?->name;

                return $vendorName ? $vendorName.' (Vendor)' : 'Vendor';
            }

            if ($source instanceof PurchaseInvoice) {
                $vendorName = $source->supplier?->name;

                return $vendorName ? $vendorName.' (Vendor)' : 'Vendor';
            }

            if ($source instanceof ShopLedgerTransaction) {
                $shopName = $source->shop?->name;

                return $shopName ? $shopName.' (Shop Petty)' : 'Shop Petty';
            }

            if (isset($source->employee_name) && ! empty($source->employee_name)) {
                return (string) $source->employee_name.' (Payroll)';
            }
        }

        // 3. Fallback check by counterpart_id if type string is available
        if ($stmt->counterpart_type && $stmt->counterpart_id) {
            $cType = class_basename($stmt->counterpart_type);
            if ($cType === 'User') {
                $user = User::find($stmt->counterpart_id);
                if ($user) {
                    return $user->name.' (Purchaser)';
                }
            } elseif ($cType === 'Supplier') {
                $supplier = Supplier::find($stmt->counterpart_id);
                if ($supplier) {
                    return $supplier->name.' (Vendor)';
                }
            } elseif ($cType === 'Shop') {
                $shop = Shop::find($stmt->counterpart_id);
                if ($shop) {
                    return $shop->name.' (Shop)';
                }
            }
        }

        // 4. Source string or narration fallback
        $sourceType = (string) $stmt->source_type;
        $sourceStr = (string) $stmt->source;

        if (str_contains($sourceType, 'PurchaserCredit') || $sourceStr === 'purchaser_funding') {
            return 'Purchaser Funding';
        }

        if (str_contains($sourceType, 'VendorSettlement') || $sourceStr === 'vendor_settlement') {
            return 'Vendor Settlement';
        }

        if (str_contains($sourceType, 'ShopLedgerTransaction') || $sourceStr === 'shop_petty_funding') {
            return 'Shop Petty Funding';
        }

        if (! empty($stmt->narration)) {
            return (string) $stmt->narration;
        }

        return 'Vendor / Party';
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
                'details_url' => route('admin.cashbook.shop.show', $shop->id),
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
                    'details_url' => route('admin.cashbook.finance.vendor-credit'),
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
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
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
                'details_url' => $s->public_uuid ? route('admin.cashbook.finance.reconciliation', ['statementRef' => $s->public_uuid]) : null,
                'delete_url' => $s->is_finalized ? null : route('admin.cashbook.account-balance.statements.delete', $s->public_uuid ?: $s->id),
            ]);

        return collect($statements->all());
    }
}
