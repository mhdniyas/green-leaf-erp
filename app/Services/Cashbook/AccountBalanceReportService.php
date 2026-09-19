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
use App\Models\ShopPettyCashExpense;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorSettlement;
use App\Services\Cashbook\DTO\AccountBalanceReportData;
use App\Services\Finance\PurchaserSettlementService;
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
        $purchaserPayablesData = $this->calculatePurchaserPayables($startDate, $endDate);
        $purchaserPaymentsData = $this->calculatePurchaserPayments($startDate, $endDate);
        $vendorPayablesData = $this->calculateVendorPayables($startDate, $endDate);
        $vendorPaymentsData = $this->calculateVendorPayments($startDate, $endDate);
        $companyOwesShopsData = $this->calculateCompanyOwesShops($startDate, $endDate);
        $pettyPayablesData = $this->calculatePettyPayables($startDate, $endDate);
        $otherPayablesData = $this->calculateOtherPayables($startDate, $endDate);

        $totalPurchaserOutstanding = (float) $purchaserPayablesData['total_closing'];
        $totalVendorOutstanding = (float) $vendorPayablesData['total_closing'];
        $totalCompanyOwesShops = (float) $companyOwesShopsData['total_closing'];
        $totalPettyPayables = (float) $pettyPayablesData['total_closing'];
        $totalOtherPayables = (float) $otherPayablesData['total_closing'];

        $totalPayables = round($totalPurchaserOutstanding + $totalVendorOutstanding + $totalCompanyOwesShops + $totalPettyPayables + $totalOtherPayables, 2);

        $payablesStructured = [
            'total_closing' => $totalPayables,
            'summary' => [
                'purchaser_outstanding' => $totalPurchaserOutstanding,
                'vendor_outstanding' => $totalVendorOutstanding,
                'company_owes_shops' => $totalCompanyOwesShops,
                'petty_outstanding' => $totalPettyPayables,
                'other_payables' => $totalOtherPayables,
                'total_payables' => $totalPayables,
            ],
            'purchaser_payables' => $purchaserPayablesData['items'],
            'purchaser_payments' => $purchaserPaymentsData,
            'vendor_payables' => $vendorPayablesData['items'],
            'vendor_payments' => $vendorPaymentsData,
            'company_owes_shops' => $companyOwesShopsData['items'],
            'petty_payables' => $pettyPayablesData['items'],
            'other_payables' => $otherPayablesData['items'],
            'items' => array_merge(
                $purchaserPayablesData['items'],
                $vendorPayablesData['items'],
                $companyOwesShopsData['items'],
                $pettyPayablesData['items'],
                $otherPayablesData['items']
            ),
        ];

        $movementsData = $this->calculatePeriodMovements(
            $startDate,
            $endDate,
            $accountsData,
            $floatingInData,
            $floatingOutData,
            $receivablesData,
            $payablesStructured
        );
        $transactionsCollection = $this->collectPeriodTransactions($startDate, $endDate);

        $totalActualBalance = round((float) array_sum(array_column($accountsData, 'closing_actual')), 2);
        $totalFloatingIn = round((float) $floatingInData['total'], 2);
        $totalFloatingOut = round((float) $floatingOutData['total'], 2);
        $netFloating = round($totalFloatingIn - $totalFloatingOut, 2);
        $totalReceivables = round((float) $receivablesData['total_closing'], 2);
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
            receivables: $receivablesData,
            payables: $payablesStructured,
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

        switch ($preset) {
            case 'today':
                $startDate = $today->toDateString();
                $endDate = $today->toDateString();
                $mode = 'day';
                $label = 'Today ('.$today->format('d M Y').')';
                break;

            case 'yesterday':
                $yesterday = $today->copy()->subDay();
                $startDate = $yesterday->toDateString();
                $endDate = $yesterday->toDateString();
                $mode = 'day';
                $label = 'Yesterday ('.$yesterday->format('d M Y').')';
                break;

            case 'day':
                $targetDate = $date ?: ($fromDate ?: $today->toDateString());
                $cDate = Carbon::parse($targetDate);
                $startDate = $cDate->toDateString();
                $endDate = $cDate->toDateString();
                $mode = 'day';
                $label = $cDate->format('d M Y');
                break;

            case 'this_week':
                $startDate = $today->copy()->startOfWeek()->toDateString();
                $endDate = $today->copy()->endOfWeek()->toDateString();
                $mode = 'custom';
                $label = 'This Week ('.Carbon::parse($startDate)->format('d M').' – '.Carbon::parse($endDate)->format('d M Y').')';
                break;

            case 'last_week':
                $lastWeekStart = $today->copy()->subWeek()->startOfWeek();
                $lastWeekEnd = $today->copy()->subWeek()->endOfWeek();
                $startDate = $lastWeekStart->toDateString();
                $endDate = $lastWeekEnd->toDateString();
                $mode = 'custom';
                $label = 'Last Week ('.$lastWeekStart->format('d M').' – '.$lastWeekEnd->format('d M Y').')';
                break;

            case 'last_month':
                $lastMonth = $today->copy()->subMonth();
                $startDate = $lastMonth->copy()->startOfMonth()->toDateString();
                $endDate = $lastMonth->copy()->endOfMonth()->toDateString();
                $mode = 'month';
                $label = $lastMonth->format('F Y');
                break;

            case 'quarter':
                $startDate = $today->copy()->startOfQuarter()->toDateString();
                $endDate = $today->copy()->endOfQuarter()->toDateString();
                $mode = 'custom';
                $label = 'Q'.ceil($today->month / 3).' '.$today->year.' ('.Carbon::parse($startDate)->format('d M').' – '.Carbon::parse($endDate)->format('d M Y').')';
                break;

            case 'year':
                $startDate = $today->copy()->startOfYear()->toDateString();
                $endDate = $today->copy()->endOfYear()->toDateString();
                $mode = 'custom';
                $label = 'Year '.$today->year;
                break;

            case 'all_time':
                $startDate = '2020-01-01';
                $endDate = $today->toDateString();
                $mode = 'custom';
                $label = 'All Time (up to '.$today->format('d M Y').')';
                break;

            case 'custom':
                $startDate = $fromDate ?: $today->copy()->startOfMonth()->toDateString();
                $endDate = $toDate ?: $today->toDateString();
                $mode = 'custom';
                $label = Carbon::parse($startDate)->format('d M Y').' – '.Carbon::parse($endDate)->format('d M Y');
                break;

            case 'month':
            case 'this_month':
            default:
                $mode = $periodMode ?: 'month';
                if ($mode === 'day') {
                    $targetDate = $date ?: ($fromDate ?: $today->toDateString());
                    $cDate = Carbon::parse($targetDate);
                    $startDate = $cDate->toDateString();
                    $endDate = $cDate->toDateString();
                    $label = $cDate->format('d M Y');
                } elseif ($mode === 'custom') {
                    $startDate = $fromDate ?: $today->copy()->startOfMonth()->toDateString();
                    $endDate = $toDate ?: $today->toDateString();
                    $label = Carbon::parse($startDate)->format('d M Y').' – '.Carbon::parse($endDate)->format('d M Y');
                } else {
                    $monthStrInput = $month ?: $today->format('Y-m');
                    try {
                        $cMonth = Carbon::createFromFormat('Y-m', $monthStrInput);
                    } catch (\Throwable) {
                        $cMonth = $today->copy();
                    }
                    $startDate = $cMonth->copy()->startOfMonth()->toDateString();
                    $endDate = $cMonth->copy()->endOfMonth()->toDateString();
                    $label = $cMonth->format('F Y');
                }
                break;
        }

        $monthStr = Carbon::parse($startDate)->format('Y-m');
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
     * Calculate Whole-Company Receivables (Shop Receivables + Other Receivables).
     *
     * @param  array<int, array<string, mixed>>  $floatingInItems
     * @return array{total_closing: float, items: array<int, array<string, mixed>>, shop_receivables: array<int, array<string, mixed>>, other_receivables: array<int, array<string, mixed>>}
     */
    private function calculateReceivables(string $startDate, string $endDate, array $floatingInItems): array
    {
        $shops = Shop::query()->where('status', 'active')->orderBy('name')->get();

        $floatingByShop = [];
        foreach ($floatingInItems as $item) {
            $sId = $item['shop_id'] ?? null;
            if ($sId) {
                $floatingByShop[$sId] = ($floatingByShop[$sId] ?? 0.0) + (float) $item['amount'];
            }
        }

        $shopItems = [];
        $totalShopClosing = 0.0;

        foreach ($shops as $shop) {
            $closingSnapshot = ShopDailyLedgerSnapshot::query()
                ->where('shop_id', $shop->id)
                ->whereDate('business_date', '<=', $endDate)
                ->orderBy('business_date', 'desc')
                ->first();

            $openingSnapshot = ShopDailyLedgerSnapshot::query()
                ->where('shop_id', $shop->id)
                ->whereDate('business_date', '<', $startDate)
                ->orderBy('business_date', 'desc')
                ->first();

            $openingPosition = round((float) ($openingSnapshot?->closing_shop_position ?? 0.0), 2);
            $grossClosingPosition = round((float) ($closingSnapshot?->closing_shop_position ?? 0.0), 2);

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

            $floatingInOffset = round((float) ($floatingByShop[$shop->id] ?? 0.0), 2);

            $openingRec = max(0.0, $openingPosition);
            $grossClosingRec = max(0.0, $grossClosingPosition);
            $netClosingRec = max(0.0, round($grossClosingRec - $floatingInOffset, 2));

            $shopItems[] = [
                'party' => $shop->name.' ('.$shop->code.')',
                'shop_id' => $shop->id,
                'type' => 'Shop Receivable',
                'opening_outstanding' => $openingRec,
                'new_receivable' => round($newReceivable, 2),
                'received' => round($received, 2),
                'reversed' => round($reversed, 2),
                'floating_in_offset' => $floatingInOffset,
                'gross_closing' => $grossClosingRec,
                'closing_outstanding' => $netClosingRec,
                'status' => $netClosingRec > 0 ? 'OUTSTANDING' : 'SETTLED',
                'reference' => 'SHOP-'.$shop->code,
                'details_url' => route('admin.cashbook.shop.show', $shop->id),
            ];

            $totalShopClosing += $netClosingRec;
        }

        $otherReceivables = [];
        $totalOtherClosing = 0.0;

        $totalClosing = round($totalShopClosing + $totalOtherClosing, 2);

        return [
            'total_closing' => $totalClosing,
            'shop_receivables' => $shopItems,
            'other_receivables' => $otherReceivables,
            'items' => array_merge($shopItems, $otherReceivables),
        ];
    }

    /**
     * Calculate Purchaser Payables (Company owes Purchasers or Purchaser Outstanding).
     *
     * @return array{total_closing: float, items: array<int, array<string, mixed>>}
     */
    private function calculatePurchaserPayables(string $startDate, string $endDate): array
    {
        $purchasers = User::query()
            ->where(function ($q): void {
                $q->whereHas('roles', fn ($r) => $r->where('name', 'purchaser'))
                    ->orWhereIn('id', function ($sub): void {
                        $sub->select('purchaser_id')->from('purchaser_credits')->distinct();
                    })
                    ->orWhereIn('id', function ($sub): void {
                        $sub->select('purchaser_submitted_by')->from('purchase_invoices')->whereNotNull('purchaser_submitted_by')->distinct();
                    });
            })
            ->orderBy('name')
            ->get();

        $items = [];
        $totalClosing = 0.0;

        /** @var PurchaserSettlementService $settlementService */
        $settlementService = app(PurchaserSettlementService::class);

        foreach ($purchasers as $purchaser) {
            $purchaserId = (int) $purchaser->id;

            $openingData = $settlementService->openingBalanceBefore($purchaserId, $startDate);
            $openingAdvance = (float) $openingData['advance'];

            $oldCreditInvoicesSum = (float) PurchaseInvoice::query()
                ->leftJoin('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
                ->whereNull('purchase_invoices.deleted_at')
                ->where('purchase_invoices.status', '!=', 'cancelled')
                ->whereRaw('(purchase_invoices.purchaser_submitted_by = ? OR purchaser_carts.user_id = ?)', [$purchaserId, $purchaserId])
                ->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) < ?', [$startDate])
                ->selectRaw('COALESCE(SUM(purchase_invoices.amount - purchase_invoices.discount_amount - purchase_invoices.paid_amount), 0) as total')
                ->value('total');

            $openingOutstanding = round($oldCreditInvoicesSum - $openingAdvance, 2);

            $periodCredits = PurchaserCredit::query()
                ->where('purchaser_id', $purchaserId)
                ->whereBetween('business_date', [$startDate, $endDate])
                ->get();

            $paid = (float) $periodCredits->filter(fn ($c) => $c->type === 'in')->sum('amount');
            $returned = (float) $periodCredits->filter(fn ($c) => $c->type === 'out' && $c->purchase_invoice_id === null)->sum('amount');

            $periodInvoices = PurchaseInvoice::query()
                ->leftJoin('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
                ->whereNull('purchase_invoices.deleted_at')
                ->where('purchase_invoices.status', '!=', 'cancelled')
                ->whereRaw('(purchase_invoices.purchaser_submitted_by = ? OR purchaser_carts.user_id = ?)', [$purchaserId, $purchaserId])
                ->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) >= ?', [$startDate])
                ->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) <= ?', [$endDate])
                ->selectRaw('
                    COALESCE(SUM(purchase_invoices.amount), 0) as gross_amount,
                    COALESCE(SUM(purchase_invoices.discount_amount), 0) as discount_amount,
                    COALESCE(SUM(purchase_invoices.paid_amount), 0) as paid_amount
                ')
                ->first();

            $newBills = round((float) ($periodInvoices?->gross_amount ?? 0), 2);
            $discounts = round((float) ($periodInvoices?->discount_amount ?? 0), 2);

            $closingOutstanding = round($openingOutstanding + $newBills - $paid - $discounts + $returned, 2);

            if (abs($openingOutstanding) > 0.01 || $newBills > 0 || $paid > 0 || abs($closingOutstanding) > 0.01) {
                $items[] = [
                    'party' => $purchaser->name,
                    'purchaser_id' => $purchaserId,
                    'type' => 'Purchaser Payable',
                    'opening_outstanding' => $openingOutstanding,
                    'new_liability' => $newBills,
                    'paid' => round($paid, 2),
                    'discount' => $discounts,
                    'adjustment' => 0.0,
                    'closing_outstanding' => $closingOutstanding,
                    'status' => $closingOutstanding > 0 ? 'OUTSTANDING PAYABLE' : ($closingOutstanding < 0 ? 'ADVANCE HELD' : 'SETTLED'),
                    'reference' => 'PURCHASER-'.$purchaserId,
                    'details_url' => route('admin.cashbook.finance.purchasers'),
                ];

                $totalClosing += max(0.0, $closingOutstanding);
            }
        }

        return [
            'total_closing' => round($totalClosing, 2),
            'items' => $items,
        ];
    }

    /**
     * Calculate Purchaser Payments during selected period.
     *
     * @return array{total_paid: float, total_cleared: float, total_floating: float, total_unallocated: float, items: array<int, array<string, mixed>>}
     */
    private function calculatePurchaserPayments(string $startDate, string $endDate): array
    {
        $credits = PurchaserCredit::query()
            ->with(['purchaser', 'companyAccount'])
            ->where('type', 'in')
            ->whereBetween('business_date', [$startDate, $endDate])
            ->orderBy('business_date', 'desc')
            ->get();

        $items = [];
        $totalPaid = 0.0;
        $totalCleared = 0.0;
        $totalFloating = 0.0;
        $totalUnallocated = 0.0;

        foreach ($credits as $credit) {
            $amount = (float) $credit->amount;
            $allocated = (float) PurchaserCredit::query()
                ->where('purchaser_id', $credit->purchaser_id)
                ->where('type', 'out')
                ->whereNotNull('purchase_invoice_id')
                ->where('created_at', '>=', $credit->created_at)
                ->sum('amount');

            $allocatedAmount = min($amount, $allocated);
            $unallocatedAmount = max(0.0, round($amount - $allocatedAmount, 2));

            $isCleared = (bool) ($credit->company_account_id !== null);
            $statusStr = $isCleared ? 'CLEARED' : 'PENDING';

            $items[] = [
                'id' => 'purchaser_pay_'.$credit->id,
                'date' => $credit->business_date->format('Y-m-d'),
                'purchaser' => $credit->purchaser?->name ?: 'Purchaser #'.$credit->purchaser_id,
                'from_account' => $credit->companyAccount?->name ?: ($credit->payment_source ?: 'Cash'),
                'amount' => round($amount, 2),
                'allocated_amount' => round($allocatedAmount, 2),
                'unallocated_amount' => $unallocatedAmount,
                'status' => $statusStr,
                'clearance_status' => $isCleared ? 'CLEARED' : 'FLOATING',
                'reference' => $credit->reference ?: 'CREDIT-'.$credit->id,
                'notes' => $credit->description,
                'details_url' => route('admin.cashbook.finance.purchasers'),
            ];

            $totalPaid += $amount;
            if ($isCleared) {
                $totalCleared += $amount;
            } else {
                $totalFloating += $amount;
            }
            $totalUnallocated += $unallocatedAmount;
        }

        return [
            'total_paid' => round($totalPaid, 2),
            'total_cleared' => round($totalCleared, 2),
            'total_floating' => round($totalFloating, 2),
            'total_unallocated' => round($totalUnallocated, 2),
            'items' => $items,
        ];
    }

    /**
     * Calculate Vendor Payables (Company owes Vendors).
     *
     * @return array{total_closing: float, items: array<int, array<string, mixed>>}
     */
    private function calculateVendorPayables(string $startDate, string $endDate): array
    {
        $suppliers = Supplier::query()->orderBy('name')->get();

        $items = [];
        $totalClosing = 0.0;

        foreach ($suppliers as $supplier) {
            $invoicesBeforeStart = PurchaseInvoice::query()
                ->where('supplier_id', $supplier->id)
                ->whereDate('created_at', '<', $startDate)
                ->where('status', '!=', 'cancelled')
                ->get();

            $openingPayable = round((float) $invoicesBeforeStart->sum(fn ($i) => (float) ($i->total_amount ?: $i->amount) - (float) $i->paid_amount - (float) $i->discount_amount), 2);

            $periodInvoices = PurchaseInvoice::query()
                ->where('supplier_id', $supplier->id)
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->where('status', '!=', 'cancelled')
                ->get();

            $newCreditPurchases = round((float) $periodInvoices->sum(fn ($i) => (float) ($i->total_amount ?: $i->amount)), 2);
            $paid = round((float) $periodInvoices->sum('paid_amount'), 2);
            $discounts = round((float) $periodInvoices->sum('discount_amount'), 2);
            $reversed = round((float) PurchaseInvoice::query()
                ->where('supplier_id', $supplier->id)
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->where('status', 'cancelled')
                ->sum('amount'), 2);

            $closingPayable = max(0.0, round($openingPayable + $newCreditPurchases - $paid - $discounts - $reversed, 2));

            if ($openingPayable > 0 || $newCreditPurchases > 0 || $closingPayable > 0) {
                $items[] = [
                    'party' => $supplier->name,
                    'vendor_id' => $supplier->id,
                    'type' => 'Vendor Payable',
                    'opening_outstanding' => $openingPayable,
                    'new_credit_purchases' => $newCreditPurchases,
                    'paid' => $paid,
                    'settlement_discount' => $discounts,
                    'adjustment' => $reversed,
                    'closing_outstanding' => $closingPayable,
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
     * Calculate Vendor Payments during selected period.
     *
     * @return array{total_paid: float, items: array<int, array<string, mixed>>}
     */
    private function calculateVendorPayments(string $startDate, string $endDate): array
    {
        $settlements = VendorSettlement::query()
            ->with(['supplier', 'companyAccount', 'allocations.purchaseInvoice'])
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->orderBy('payment_date', 'desc')
            ->get();

        $items = [];
        $totalPaid = 0.0;

        foreach ($settlements as $settlement) {
            $amount = (float) $settlement->actual_payment_amount;
            $allocated = (float) $settlement->allocations->sum('total_settled');

            $invoiceRefs = $settlement->allocations
                ->map(fn ($a) => $a->purchaseInvoice?->invoice_number)
                ->filter()
                ->implode(', ');

            $items[] = [
                'id' => 'vendor_settle_'.$settlement->id,
                'date' => $settlement->payment_date ? $settlement->payment_date->format('Y-m-d') : '',
                'vendor' => $settlement->supplier?->name ?: 'Vendor #'.$settlement->supplier_id,
                'from_account' => $settlement->companyAccount?->name ?: 'Company Account',
                'amount' => round($amount, 2),
                'allocated' => round($allocated, 2),
                'status' => strtoupper((string) ($settlement->reconciliation_status ?: $settlement->status)),
                'settlement_reference' => $settlement->reference ?: 'SETTLE-'.$settlement->id,
                'related_bills' => $invoiceRefs ?: 'General Settlement',
                'details_url' => route('admin.cashbook.finance.vendor-credit'),
            ];

            $totalPaid += $amount;
        }

        return [
            'total_paid' => round($totalPaid, 2),
            'items' => $items,
        ];
    }

    /**
     * Calculate Company Owes Shops (Negative shop closing positions).
     *
     * @return array{total_closing: float, items: array<int, array<string, mixed>>}
     */
    private function calculateCompanyOwesShops(string $startDate, string $endDate): array
    {
        $shops = Shop::query()->where('status', 'active')->orderBy('name')->get();

        $items = [];
        $totalClosing = 0.0;

        foreach ($shops as $shop) {
            $closingSnapshot = ShopDailyLedgerSnapshot::query()
                ->where('shop_id', $shop->id)
                ->whereDate('business_date', '<=', $endDate)
                ->orderBy('business_date', 'desc')
                ->first();

            $openingSnapshot = ShopDailyLedgerSnapshot::query()
                ->where('shop_id', $shop->id)
                ->whereDate('business_date', '<', $startDate)
                ->orderBy('business_date', 'desc')
                ->first();

            $openingPos = (float) ($openingSnapshot?->closing_shop_position ?? 0.0);
            $closingPos = (float) ($closingSnapshot?->closing_shop_position ?? 0.0);

            $openingPayable = $openingPos < 0 ? abs($openingPos) : 0.0;
            $closingPayable = $closingPos < 0 ? abs($closingPos) : 0.0;

            if ($openingPayable > 0 || $closingPayable > 0) {
                $periodTrans = ShopLedgerTransaction::query()
                    ->where('shop_id', $shop->id)
                    ->whereBetween('business_date', [$startDate, $endDate])
                    ->whereNotIn('status', ['void', 'voided', 'reversed'])
                    ->get();

                $newOwed = (float) $periodTrans->filter(fn ($t) => $t->direction === 'income' || $t->payable_direction === 'minus')->sum('amount');
                $paidToShop = (float) $periodTrans->filter(fn ($t) => $t->direction === 'expense' || $t->payable_direction === 'plus')->sum('amount');

                $items[] = [
                    'shop' => $shop->name.' ('.$shop->code.')',
                    'shop_id' => $shop->id,
                    'opening_payable' => round($openingPayable, 2),
                    'new_amount_owed' => round($newOwed, 2),
                    'paid_to_shop' => round($paidToShop, 2),
                    'adjustment' => 0.0,
                    'closing_payable' => round($closingPayable, 2),
                    'view' => route('admin.cashbook.shop.show', $shop->id),
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
     * Calculate Petty / Reimbursement Payables.
     *
     * @return array{total_closing: float, items: array<int, array<string, mixed>>}
     */
    private function calculatePettyPayables(string $startDate, string $endDate): array
    {
        $pettyExpenses = ShopPettyCashExpense::query()
            ->with(['shop'])
            ->whereBetween('business_date', [$startDate, $endDate])
            ->get();

        $items = [];
        $totalClosing = 0.0;

        foreach ($pettyExpenses as $expense) {
            $amount = (float) $expense->amount;
            if ($amount <= 0) {
                continue;
            }

            $items[] = [
                'party' => $expense->shop?->name ?: 'Shop #'.$expense->shop_id,
                'source' => 'Shop Petty Expense ('.ucfirst((string) $expense->source).')',
                'opening' => 0.0,
                'created' => round($amount, 2),
                'paid' => 0.0,
                'adjustment' => 0.0,
                'closing' => round($amount, 2),
                'status' => 'PETTY EXPENSE RECORDED',
                'reference' => 'PETTY-'.$expense->id,
                'view' => $expense->shop_id ? route('admin.cashbook.shop.show', $expense->shop_id) : null,
            ];

            $totalClosing += $amount;
        }

        return [
            'total_closing' => round($totalClosing, 2),
            'items' => $items,
        ];
    }

    /**
     * Calculate Other Payables (Any unmapped liabilities).
     *
     * @return array{total_closing: float, items: array<int, array<string, mixed>>}
     */
    private function calculateOtherPayables(string $startDate, string $endDate): array
    {
        return [
            'total_closing' => 0.0,
            'items' => [],
        ];
    }

    /**
     * Calculate Period Movement matrix (Opening -> Activity -> Closing).
     *
     * @param  array<int, array<string, mixed>>  $accountsData
     * @param  array{total: float, items: array<int, array<string, mixed>>}  $floatingInData
     * @param  array{total: float, items: array<int, array<string, mixed>>}  $floatingOutData
     * @param  array{total_closing: float, items: array<int, array<string, mixed>>}  $receivablesData
     * @param  array<string, mixed>  $payablesData
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

        $purchaserItems = $payablesData['purchaser_payables'] ?? [];
        $purchaserOpening = round((float) array_sum(array_column($purchaserItems, 'opening_outstanding')), 2);
        $purchaserClosing = round((float) array_sum(array_column($purchaserItems, 'closing_outstanding')), 2);
        $purchaserActivity = round($purchaserClosing - $purchaserOpening, 2);

        $vendorItems = $payablesData['vendor_payables'] ?? [];
        $vendorOpening = round((float) array_sum(array_column($vendorItems, 'opening_outstanding')), 2);
        $vendorClosing = round((float) array_sum(array_column($vendorItems, 'closing_outstanding')), 2);
        $vendorActivity = round($vendorClosing - $vendorOpening, 2);

        $shopPayItems = $payablesData['company_owes_shops'] ?? [];
        $shopPayOpening = round((float) array_sum(array_column($shopPayItems, 'opening_payable')), 2);
        $shopPayClosing = round((float) array_sum(array_column($shopPayItems, 'closing_payable')), 2);
        $shopPayActivity = round($shopPayClosing - $shopPayOpening, 2);

        $pettyItems = $payablesData['petty_payables'] ?? [];
        $pettyOpening = round((float) array_sum(array_column($pettyItems, 'opening')), 2);
        $pettyClosing = round((float) array_sum(array_column($pettyItems, 'closing')), 2);
        $pettyActivity = round($pettyClosing - $pettyOpening, 2);

        $payOpening = round($purchaserOpening + $vendorOpening + $shopPayOpening + $pettyOpening, 2);
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
            'total_receivables' => [
                'opening' => $recOpening,
                'activity' => $recActivity,
                'closing' => $recClosing,
            ],
            'shop_receivables' => [
                'opening' => $recOpening,
                'activity' => $recActivity,
                'closing' => $recClosing,
            ],
            'total_payables' => [
                'opening' => $payOpening,
                'activity' => $payActivity,
                'closing' => $payClosing,
            ],
            'purchaser_payables' => [
                'opening' => $purchaserOpening,
                'activity' => $purchaserActivity,
                'closing' => $purchaserClosing,
            ],
            'vendor_payables' => [
                'opening' => $vendorOpening,
                'activity' => $vendorActivity,
                'closing' => $vendorClosing,
            ],
            'company_owes_shops' => [
                'opening' => $shopPayOpening,
                'activity' => $shopPayActivity,
                'closing' => $shopPayClosing,
            ],
            'petty_payables' => [
                'opening' => $pettyOpening,
                'activity' => $pettyActivity,
                'closing' => $pettyClosing,
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
