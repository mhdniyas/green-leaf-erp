<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use App\Models\CompanyAccountingEntry;
use App\Models\PayrollPayment;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCredit;
use App\Models\ShopCredit;
use App\Models\ShopInvoice;
use App\Models\ShopLoanEntry;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdminFinancePillarService
{
    /**
     * @var list<string>
     */
    private const CASH_FLOW_ACCOUNT_CODES = ['1010', '1020'];

    /**
     * @return array{
     *     start_date:string,
     *     end_date:string,
     *     vendor:array<string, mixed>,
     *     sales:array<string, mixed>,
     *     pending_credit_requests:Collection<int, Supplier>
     * }
     */
    public function forPeriod(Carbon $startDate, Carbon $endDate): array
    {
        $vendorInvoices = $this->vendorInvoicesForPeriod($startDate, $endDate);
        $shopInvoices = $this->shopInvoicesForPeriod($startDate, $endDate);

        return [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'vendor' => $this->buildVendorPillar($vendorInvoices),
            'sales' => $this->buildSalesPillar($shopInvoices),
            'pending_credit_requests' => Supplier::query()
                ->with(['creditApprovalRequestedBy'])
                ->whereNotNull('credit_approval_requested_at')
                ->where('credit_approved', false)
                ->latest('credit_approval_requested_at')
                ->limit(8)
                ->get(),
        ];
    }

    /**
     * @return array{
     *     date:string,
     *     summary:array<string, mixed>,
     *     vendor_rows:Collection<int, array<string, mixed>>,
     *     invoices:Collection<int, PurchaseInvoice>
     * }
     */
    public function vendorDailyDetail(Carbon $date): array
    {
        $invoices = $this->vendorInvoicesForPeriod($date, $date);
        $vendorRows = $this->buildVendorRows($invoices);

        return [
            'date' => $date->toDateString(),
            'summary' => $this->vendorSummary($invoices, $vendorRows),
            'vendor_rows' => $vendorRows,
            'invoices' => $invoices->sortByDesc('created_at')->values(),
        ];
    }

    /**
     * @return array{
     *     date:string,
     *     summary:array<string, mixed>,
     *     shop_rows:Collection<int, array<string, mixed>>,
     *     invoices:Collection<int, ShopInvoice>
     * }
     */
    /**
     * @param  list<int>|null  $shopIds
     */
    public function salesDailyDetail(Carbon $date, string $statusFilter = 'all', ?array $shopIds = null): array
    {
        $invoices = $this->shopInvoicesForPeriod($date, $date, $shopIds);
        $filteredInvoices = $this->filterSalesDailyInvoices($invoices, $statusFilter);
        $shopRows = $this->buildSalesRows($filteredInvoices);

        return [
            'date' => $date->toDateString(),
            'summary' => $this->salesSummary($filteredInvoices, $shopRows),
            'shop_rows' => $shopRows,
            'invoices' => $filteredInvoices->sortByDesc('id')->values(),
            'status_filter' => $statusFilter,
        ];
    }

    /**
     * @return array{
     *     month_label:string,
     *     start_date:string,
     *     end_date:string,
     *     summary:array<string, float>,
     *     journal_rows:Collection<int, array<string, mixed>>,
     *     selected_date:string,
     *     selected_day_rows:Collection<int, array<string, mixed>>,
     *     daily_rows:Collection<int, array<string, mixed>>,
     *     purchaser_columns:Collection<int, array{id:int,label:string}>,
     *     paid_rows:Collection<int, array<string, mixed>>,
     *     received_rows:Collection<int, array<string, mixed>>
     * }
     */
    public function cashFlowReport(Carbon $date): array
    {
        $startDate = $date->copy()->startOfMonth();
        $endDate = $date->copy();

        $purchaserCredits = PurchaserCredit::query()
            ->with(['purchaser', 'purchaseInvoice'])
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->orderBy('business_date')
            ->orderBy('id')
            ->get();

        $journalEntries = $this->cashFlowJournalEntriesForPeriod($startDate, $endDate);
        $pettyCashCredits = $this->pettyCashCreditsForPeriod($startDate, $endDate);
        $shopLoanEntries = $this->shopLoanEntriesForPeriod($startDate, $endDate);
        $journalRows = $this->buildCashFlowJournalRows($journalEntries)
            ->merge($this->buildPettyCashCreditCashFlowRows($pettyCashCredits))
            ->merge($this->buildShopLoanEntryCashFlowRows($shopLoanEntries))
            ->sortBy([
                ['date', 'asc'],
                ['sort_at', 'asc'],
                ['journal', 'asc'],
            ])
            ->values();
        $openingBalance = $this->cashFlowOpeningBalance($startDate);
        $monthlyIn = round((float) $journalRows->where('direction', 'IN')->sum('amount'), 2);
        $monthlyOut = round((float) $journalRows->where('direction', 'OUT')->sum('amount'), 2);
        $closingBalance = round($openingBalance + $monthlyIn - $monthlyOut, 2);
        $dailyRows = $this->buildCashFlowDailyRows($journalRows, $startDate, $endDate, $openingBalance);
        $selectedDate = $date->toDateString();
        $purchaserColumns = $purchaserCredits
            ->groupBy('purchaser_id')
            ->map(fn (Collection $credits, int|string $purchaserId): array => [
                'id' => (int) $purchaserId,
                'label' => (string) ($credits->first()?->purchaser?->name ?? 'Unknown'),
            ])
            ->sortBy('label')
            ->values();

        return [
            'month_label' => $startDate->format('F Y'),
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'summary' => [
                'opening_balance' => $openingBalance,
                'total_in' => $monthlyIn,
                'total_out' => $monthlyOut,
                'closing_balance' => $closingBalance,
                'purchaser_in' => round((float) $purchaserCredits->where('type', 'in')->sum('amount'), 2),
                'purchaser_out' => round((float) $purchaserCredits->where('type', 'out')->sum('amount'), 2),
                'client_invoice_in' => round((float) $journalRows->where('source', 'client_invoice')->where('direction', 'IN')->sum('amount'), 2),
                'client_loan_out' => round((float) $journalRows->where('source', 'client_shop_loan')->where('direction', 'OUT')->sum('amount'), 2),
                'client_loan_in' => round((float) $journalRows->where('source', 'client_shop_loan')->where('direction', 'IN')->sum('amount'), 2),
                'petty_cash_in' => round((float) $journalRows->where('source', 'client_shop_loan')->where('direction', 'IN')->sum('amount'), 2),
                'petty_cash_out' => round((float) $journalRows->where('source', 'client_shop_loan')->where('direction', 'OUT')->sum('amount'), 2),
            ],
            'journal_rows' => $journalRows,
            'selected_date' => $selectedDate,
            'selected_day_rows' => $journalRows->where('date', $selectedDate)->values(),
            'daily_rows' => $dailyRows,
            'purchaser_columns' => $purchaserColumns,
            'paid_rows' => $this->buildPurchaserCashPivotRows($purchaserCredits, $purchaserColumns, $startDate, $endDate, 'out'),
            'received_rows' => $this->buildPurchaserCashPivotRows($purchaserCredits, $purchaserColumns, $startDate, $endDate, 'in'),
        ];
    }

    /**
     * @param  Collection<int, ShopInvoice>  $invoices
     * @return Collection<int, ShopInvoice>
     */
    private function filterSalesDailyInvoices(Collection $invoices, string $statusFilter): Collection
    {
        return match ($statusFilter) {
            'pending' => $invoices->filter(fn (ShopInvoice $invoice): bool => (float) $invoice->balance_amount > 0)->values(),
            'settled' => $invoices->filter(fn (ShopInvoice $invoice): bool => (float) $invoice->balance_amount <= 0)->values(),
            default => $invoices->values(),
        };
    }

    /**
     * @return Collection<int, PurchaseInvoice>
     */
    private function vendorInvoicesForPeriod(Carbon $startDate, Carbon $endDate): Collection
    {
        return PurchaseInvoice::query()
            ->with(['supplier.creditApprovalRequestedBy', 'supplier.creditApprovedBy', 'purchaserCart', 'goodsReceived'])
            ->where(function (Builder $query) use ($startDate, $endDate): void {
                $query
                    ->whereHas('purchaserCart', function (Builder $cartQuery) use ($startDate, $endDate): void {
                        $cartQuery
                            ->whereDate('business_date', '>=', $startDate)
                            ->whereDate('business_date', '<=', $endDate);
                    })
                    ->orWhere(function (Builder $manualInvoiceQuery) use ($startDate, $endDate): void {
                        $manualInvoiceQuery
                            ->whereNull('purchaser_cart_id')
                            ->whereDate('created_at', '>=', $startDate)
                            ->whereDate('created_at', '<=', $endDate);
                    });
            })
            ->latest('created_at')
            ->get();
    }

    /**
     * @return Collection<int, ShopInvoice>
     */
    /**
     * @param  list<int>|null  $shopIds
     */
    private function shopInvoicesForPeriod(Carbon $startDate, Carbon $endDate, ?array $shopIds = null): Collection
    {
        return ShopInvoice::query()
            ->with(['shop', 'order'])
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->when($shopIds !== null && $shopIds !== [], fn (Builder $query) => $query->whereIn('shop_id', $shopIds))
            ->latest('business_date')
            ->latest('id')
            ->get();
    }

    /**
     * @param  Collection<int, PurchaseInvoice>  $invoices
     * @return array<string, mixed>
     */
    private function buildVendorPillar(Collection $invoices): array
    {
        $vendorRows = $this->buildVendorRows($invoices);

        return [
            'summary' => $this->vendorSummary($invoices, $vendorRows),
            'rows' => $vendorRows,
            'chart' => $this->chartRows($vendorRows, 'vendor'),
            'daily_rows' => $this->buildVendorDailyRows($invoices),
        ];
    }

    /**
     * @param  Collection<int, ShopInvoice>  $invoices
     * @return array<string, mixed>
     */
    private function buildSalesPillar(Collection $invoices): array
    {
        $salesRows = $this->buildSalesRows($invoices);

        return [
            'summary' => $this->salesSummary($invoices, $salesRows),
            'rows' => $salesRows,
            'chart' => $this->chartRows($salesRows, 'shop'),
            'daily_rows' => $this->buildSalesDailyRows($invoices),
        ];
    }

    /**
     * @param  Collection<int, PurchaseInvoice>  $invoices
     * @return Collection<int, array<string, mixed>>
     */
    private function buildVendorRows(Collection $invoices): Collection
    {
        return $invoices
            ->groupBy(fn (PurchaseInvoice $invoice): string => (string) ($invoice->supplier_id ?? 'pending'))
            ->map(function (Collection $vendorInvoices): array {
                /** @var PurchaseInvoice $latestInvoice */
                $latestInvoice = $vendorInvoices->sortByDesc('created_at')->first();
                $vendorTotal = round((float) $vendorInvoices->sum('amount'), 2);
                $vendorPaid = round((float) $vendorInvoices->sum('paid_amount'), 2);
                $vendorOutstanding = round(max(0, $vendorTotal - $vendorPaid), 2);

                return [
                    'vendor' => $latestInvoice->supplier,
                    'invoice_count' => $vendorInvoices->count(),
                    'total_amount' => $vendorTotal,
                    'paid_amount' => $vendorPaid,
                    'outstanding_amount' => $vendorOutstanding,
                    'latest_invoice' => $latestInvoice,
                    'status' => $vendorOutstanding > 0 ? 'Due' : 'Settled',
                ];
            })
            ->sortByDesc('outstanding_amount')
            ->values();
    }

    /**
     * @param  Collection<int, ShopInvoice>  $invoices
     * @return Collection<int, array<string, mixed>>
     */
    private function buildSalesRows(Collection $invoices): Collection
    {
        return $invoices
            ->groupBy(fn (ShopInvoice $invoice): string => (string) ($invoice->shop_id ?? 'pending'))
            ->map(function (Collection $shopInvoices): array {
                /** @var ShopInvoice $latestInvoice */
                $latestInvoice = $shopInvoices->sortByDesc('id')->first();
                $shopTotal = round((float) $shopInvoices->sum('final_total'), 2);
                $shopPaid = round((float) $shopInvoices->sum('paid_amount'), 2);
                $shopOutstanding = round((float) $shopInvoices->sum('balance_amount'), 2);

                return [
                    'shop' => $latestInvoice->shop,
                    'invoice_count' => $shopInvoices->count(),
                    'total_amount' => $shopTotal,
                    'paid_amount' => $shopPaid,
                    'outstanding_amount' => $shopOutstanding,
                    'latest_invoice' => $latestInvoice,
                    'invoices' => $shopInvoices->sortByDesc('id')->values(),
                    'status' => $shopOutstanding > 0 ? 'Due' : 'Settled',
                ];
            })
            ->sortByDesc('outstanding_amount')
            ->values();
    }

    /**
     * @param  Collection<int, PurchaseInvoice>  $invoices
     * @param  Collection<int, array<string, mixed>>  $vendorRows
     * @return array<string, mixed>
     */
    private function vendorSummary(Collection $invoices, Collection $vendorRows): array
    {
        $totalAmount = round((float) $invoices->sum('amount'), 2);
        $paidAmount = round((float) $invoices->sum('paid_amount'), 2);
        $outstandingAmount = round(max(0, $totalAmount - $paidAmount), 2);

        return [
            'label' => 'Vendor Reports',
            'count' => $vendorRows->count(),
            'invoice_count' => $invoices->count(),
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'outstanding_amount' => $outstandingAmount,
            'settlement_rate' => $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100, 1) : 0.0,
        ];
    }

    /**
     * @param  Collection<int, ShopInvoice>  $invoices
     * @param  Collection<int, array<string, mixed>>  $salesRows
     * @return array<string, mixed>
     */
    private function salesSummary(Collection $invoices, Collection $salesRows): array
    {
        $totalAmount = round((float) $invoices->sum('final_total'), 2);
        $paidAmount = round((float) $invoices->sum('paid_amount'), 2);
        $outstandingAmount = round((float) $invoices->sum('balance_amount'), 2);

        return [
            'label' => 'Sales Reports',
            'count' => $salesRows->count(),
            'invoice_count' => $invoices->count(),
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'outstanding_amount' => $outstandingAmount,
            'settlement_rate' => $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100, 1) : 0.0,
        ];
    }

    /**
     * @param  Collection<int, PurchaseInvoice>  $invoices
     * @return Collection<int, array<string, mixed>>
     */
    private function buildVendorDailyRows(Collection $invoices): Collection
    {
        return $invoices
            ->groupBy(fn (PurchaseInvoice $invoice): string => $this->vendorBusinessDate($invoice))
            ->map(function (Collection $dayInvoices, string $date): array {
                $creditAmount = round((float) $dayInvoices->sum('amount'), 2);
                $debitAmount = round((float) $dayInvoices->sum('paid_amount'), 2);
                $balanceAmount = round(max(0, $creditAmount - $debitAmount), 2);
                $topVendor = $dayInvoices
                    ->groupBy('supplier_id')
                    ->sortByDesc(fn (Collection $vendorInvoices): float => (float) $vendorInvoices->sum('amount'))
                    ->first()?->first()?->supplier;

                return [
                    'date' => $date,
                    'invoice_count' => $dayInvoices->count(),
                    'credit_amount' => $creditAmount,
                    'debit_amount' => $debitAmount,
                    'balance_amount' => $balanceAmount,
                    'status' => $balanceAmount > 0 ? 'Due' : 'Settled',
                    'lead_label' => $topVendor?->name ?? 'Mixed vendors',
                ];
            })
            ->sortKeysDesc()
            ->values();
    }

    /**
     * @param  Collection<int, ShopInvoice>  $invoices
     * @return Collection<int, array<string, mixed>>
     */
    private function buildSalesDailyRows(Collection $invoices): Collection
    {
        return $invoices
            ->groupBy(fn (ShopInvoice $invoice): string => Carbon::parse((string) $invoice->business_date)->toDateString())
            ->map(function (Collection $dayInvoices, string $date): array {
                $creditAmount = round((float) $dayInvoices->sum('final_total'), 2);
                $debitAmount = round((float) $dayInvoices->sum('paid_amount'), 2);
                $balanceAmount = round((float) $dayInvoices->sum('balance_amount'), 2);
                $topShop = $dayInvoices
                    ->groupBy('shop_id')
                    ->sortByDesc(fn (Collection $shopInvoices): float => (float) $shopInvoices->sum('final_total'))
                    ->first()?->first()?->shop;

                return [
                    'date' => $date,
                    'invoice_count' => $dayInvoices->count(),
                    'credit_amount' => $creditAmount,
                    'debit_amount' => $debitAmount,
                    'balance_amount' => $balanceAmount,
                    'status' => $balanceAmount > 0 ? 'Pending Collection' : 'Settled',
                    'lead_label' => $topShop?->name ?? 'Mixed shops',
                ];
            })
            ->sortKeysDesc()
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array{label:string, amount:float, width:float}>
     */
    private function chartRows(Collection $rows, string $entityKey): Collection
    {
        $topRows = $rows->sortByDesc('total_amount')->take(6)->values();
        $maxAmount = max(1.0, (float) $topRows->max('total_amount'));

        return $topRows->map(function (array $row) use ($entityKey, $maxAmount): array {
            $entity = $row[$entityKey] ?? null;

            return [
                'label' => (string) ($entity?->name ?? 'Pending'),
                'amount' => (float) $row['total_amount'],
                'width' => max(6.0, round(((float) $row['total_amount'] / $maxAmount) * 100, 1)),
            ];
        });
    }

    private function vendorBusinessDate(PurchaseInvoice $invoice): string
    {
        if ($invoice->purchaserCart?->business_date) {
            return Carbon::parse((string) $invoice->purchaserCart->business_date)->toDateString();
        }

        return $invoice->created_at->toDateString();
    }

    /**
     * @return Collection<int, JournalEntry>
     */
    private function cashFlowJournalEntriesForPeriod(Carbon $startDate, Carbon $endDate): Collection
    {
        return JournalEntry::query()
            ->with(['transactions.account'])
            ->whereDate('entry_date', '>=', $startDate)
            ->whereDate('entry_date', '<=', $endDate)
            ->where(function (Builder $query): void {
                $query->whereNull('source_type')
                    ->orWhere(function (Builder $subQuery): void {
                        $subQuery->where('source_type', '!=', PurchaseInvoice::class)
                            ->where('source_type', '!=', PurchaserCredit::class);
                    })
                    ->orWhere(function (Builder $query): void {
                        $query->where('source_type', PurchaseInvoice::class)
                            ->where(function (Builder $eventQuery): void {
                                $eventQuery
                                    ->where('source_event', 'like', 'green_leaf_direct_purchase_payment:%')
                                    ->orWhere('source_event', 'like', 'purchaser_daily_purchase_payment:%')
                                    ->orWhere('source_event', 'like', 'company_vendor_credit_payment:%');
                            });
                    });
            })
            ->whereHas('transactions.account', fn (Builder $query): Builder => $query->whereIn('code', self::CASH_FLOW_ACCOUNT_CODES))
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, ShopCredit>
     */
    private function pettyCashCreditsForPeriod(Carbon $startDate, Carbon $endDate): Collection
    {
        return ShopCredit::query()
            ->approved()
            ->with(['shop', 'creator', 'cashMovementCategory'])
            ->where('is_petty_cash', true)
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->orderBy('business_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, ShopLoanEntry>
     */
    private function shopLoanEntriesForPeriod(Carbon $startDate, Carbon $endDate): Collection
    {
        return ShopLoanEntry::query()
            ->approved()
            ->with(['shop', 'creator'])
            ->whereIn('type', [ShopLoanEntry::TypeCashGiven, ShopLoanEntry::TypeRepayment])
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->orderBy('business_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, JournalEntry>  $journalEntries
     * @return Collection<int, array<string, mixed>>
     */
    private function buildCashFlowJournalRows(Collection $journalEntries): Collection
    {
        return $journalEntries
            ->flatMap(function (JournalEntry $entry): Collection {
                $cashTransactions = $entry->transactions
                    ->filter(fn (JournalTransaction $transaction): bool => in_array((string) $transaction->account?->code, self::CASH_FLOW_ACCOUNT_CODES, true));
                $counterpartyAccounts = $entry->transactions
                    ->reject(fn (JournalTransaction $transaction): bool => in_array((string) $transaction->account?->code, self::CASH_FLOW_ACCOUNT_CODES, true))
                    ->map(fn (JournalTransaction $transaction): string => (string) ($transaction->account?->name ?? 'Journal Entry'))
                    ->unique()
                    ->values();

                return $cashTransactions->map(function (JournalTransaction $transaction) use ($entry, $counterpartyAccounts): array {
                    return [
                        'date' => $entry->entry_date->toDateString(),
                        'amount' => round((float) $transaction->amount, 2),
                        'direction' => $transaction->type === 'debit' ? 'IN' : 'OUT',
                        'journal' => $counterpartyAccounts->isNotEmpty() ? $counterpartyAccounts->implode(', ') : ((string) ($entry->reference ?: 'Journal Entry')),
                        'remarks' => $entry->description ?: $entry->reference,
                        'category' => $this->cashFlowCategory($entry, $counterpartyAccounts),
                        'source' => $this->cashFlowSource($entry),
                        'sort_at' => $entry->created_at?->timestamp ?? 0,
                    ];
                });
            })
            ->sortBy([
                ['date', 'asc'],
                ['sort_at', 'asc'],
                ['journal', 'asc'],
            ])
            ->values();
    }

    /**
     * @param  Collection<int, ShopCredit>  $credits
     * @return Collection<int, array<string, mixed>>
     */
    private function buildPettyCashCreditCashFlowRows(Collection $credits): Collection
    {
        return $credits->map(function (ShopCredit $credit): array {
            $isCreditToShop = $credit->type === 'in';
            $shopName = (string) ($credit->shop?->name ?? 'Unknown shop');
            $category = $credit->cashMovementCategory?->name
                ?? ($isCreditToShop ? 'Loan Given' : 'Loan Repayment');

            return [
                'date' => $credit->business_date?->toDateString() ?? $credit->created_at?->toDateString(),
                'amount' => round((float) $credit->amount, 2),
                'direction' => $isCreditToShop ? 'OUT' : 'IN',
                'journal' => $category.' - '.$shopName,
                'remarks' => $credit->description ?: $category.' for '.$shopName,
                'category' => $category,
                'source' => 'client_shop_loan',
                'sort_at' => $credit->created_at?->timestamp ?? 0,
            ];
        })->values();
    }

    /**
     * @param  Collection<int, ShopLoanEntry>  $entries
     * @return Collection<int, array<string, mixed>>
     */
    private function buildShopLoanEntryCashFlowRows(Collection $entries): Collection
    {
        return $entries->map(function (ShopLoanEntry $entry): array {
            $shopName = (string) ($entry->shop?->name ?? 'Unknown shop');

            return [
                'date' => $entry->business_date?->toDateString() ?? $entry->created_at?->toDateString(),
                'amount' => round((float) $entry->amount, 2),
                'direction' => $entry->cashJournalDirection(),
                'journal' => $entry->typeLabel().' - '.$shopName,
                'remarks' => $entry->description ?: $entry->title,
                'category' => 'Shop Loan',
                'source' => 'client_shop_loan',
                'sort_at' => $entry->created_at?->timestamp ?? 0,
            ];
        })->values();
    }

    private function cashFlowOpeningBalance(Carbon $startDate): float
    {
        $transactions = JournalTransaction::query()
            ->with('account')
            ->whereHas('account', fn (Builder $query): Builder => $query->whereIn('code', self::CASH_FLOW_ACCOUNT_CODES))
            ->whereHas('journalEntry', fn (Builder $query): Builder => $query->whereDate('entry_date', '<', $startDate))
            ->get();

        $journalOpeningBalance = round((float) $transactions->sum(
            fn (JournalTransaction $transaction): float => $transaction->type === 'debit'
                ? (float) $transaction->amount
                : -1 * (float) $transaction->amount
        ), 2);

        $pettyCashOpeningBalance = round((float) ShopCredit::query()
            ->approved()
            ->where('is_petty_cash', true)
            ->whereDate('business_date', '<', $startDate)
            ->get()
            ->sum(fn (ShopCredit $credit): float => $credit->type === 'in' ? -1 * (float) $credit->amount : (float) $credit->amount), 2);

        $shopLoanOpeningBalance = round((float) ShopLoanEntry::query()
            ->approved()
            ->whereDate('business_date', '<', $startDate)
            ->get()
            ->sum(fn (ShopLoanEntry $entry): float => $entry->type === ShopLoanEntry::TypeCashGiven ? -1 * (float) $entry->amount : (float) $entry->amount), 2);

        return round($journalOpeningBalance + $pettyCashOpeningBalance + $shopLoanOpeningBalance, 2);
    }

    /**
     * @param  Collection<int, string>  $counterpartyAccounts
     */
    private function cashFlowCategory(JournalEntry $entry, Collection $counterpartyAccounts): string
    {
        if ($entry->source_type === ShopInvoice::class) {
            $isClientInvoice = ShopInvoice::query()
                ->whereKey($entry->source_id)
                ->whereHas('shop', fn (Builder $query): Builder => $query->whereNotNull('client_id'))
                ->exists();

            return $isClientInvoice ? 'Client Invoice Payment' : 'Direct Sales Income';
        }

        if ($entry->source_type === PurchaserCredit::class) {
            return 'Purchaser Cash Advance';
        }

        if ($entry->source_type === PurchaseInvoice::class && str_starts_with((string) $entry->source_event, 'green_leaf_direct_purchase_payment:')) {
            return 'Green Leaf Direct Purchase';
        }

        if ($entry->source_type === PurchaseInvoice::class && str_starts_with((string) $entry->source_event, 'purchaser_daily_purchase_payment:')) {
            return 'Purchaser Daily Purchase';
        }

        if ($entry->source_type === PurchaseInvoice::class && str_starts_with((string) $entry->source_event, 'company_vendor_credit_payment:')) {
            return 'Company Vendor Credit Payment';
        }

        if ($entry->source_event === 'payroll_payment') {
            return 'Staff Salary';
        }

        if ($entry->source_type === CompanyAccountingEntry::class) {
            return str_starts_with((string) $entry->source_event, 'reversal')
                ? 'Main Account Reversal'
                : 'Main Account';
        }

        return $counterpartyAccounts->first() ?: 'Journal Entry';
    }

    private function cashFlowSource(JournalEntry $entry): string
    {
        if ($entry->source_type === ShopInvoice::class) {
            return ShopInvoice::query()
                ->whereKey($entry->source_id)
                ->whereHas('shop', fn (Builder $query): Builder => $query->whereNotNull('client_id'))
                ->exists()
                    ? 'client_invoice'
                    : 'daily_sales';
        }

        return match ($entry->source_type) {
            PayrollPayment::class => 'payroll',
            PurchaserCredit::class => 'purchaser',
            PurchaseInvoice::class => match (true) {
                str_starts_with((string) $entry->source_event, 'green_leaf_direct_purchase_payment:') => 'green_leaf_direct_purchase',
                str_starts_with((string) $entry->source_event, 'purchaser_daily_purchase_payment:') => 'purchaser_daily_purchase',
                str_starts_with((string) $entry->source_event, 'company_vendor_credit_payment:') => 'vendor_credit_company_payment',
                default => 'journal',
            },
            CompanyAccountingEntry::class => 'main_account',
            default => 'journal',
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $journalRows
     * @return Collection<int, array<string, mixed>>
     */
    private function buildCashFlowDailyRows(Collection $journalRows, Carbon $startDate, Carbon $endDate, float $openingBalance): Collection
    {
        $rowsByDate = $journalRows->groupBy('date');
        $runningBalance = $openingBalance;
        $dailyRows = collect();

        for ($cursor = $startDate->copy(); $cursor->lte($endDate); $cursor->addDay()) {
            $dateKey = $cursor->toDateString();
            $dayRows = $rowsByDate->get($dateKey, collect());
            $inAmount = round((float) $dayRows->where('direction', 'IN')->sum('amount'), 2);
            $outAmount = round((float) $dayRows->where('direction', 'OUT')->sum('amount'), 2);
            $runningBalance = round($runningBalance + $inAmount - $outAmount, 2);

            $dailyRows->push([
                'date' => $dateKey,
                'in_amount' => $inAmount,
                'out_amount' => $outAmount,
                'balance' => $runningBalance,
            ]);
        }

        return $dailyRows;
    }

    /**
     * @param  Collection<int, PurchaserCredit>  $purchaserCredits
     * @param  Collection<int, array{id:int,label:string}>  $purchaserColumns
     * @return Collection<int, array<string, mixed>>
     */
    private function buildPurchaserCashPivotRows(
        Collection $purchaserCredits,
        Collection $purchaserColumns,
        Carbon $startDate,
        Carbon $endDate,
        string $type,
    ): Collection {
        $creditsByDate = $purchaserCredits
            ->where('type', $type)
            ->groupBy(fn (PurchaserCredit $credit): string => $credit->business_date?->toDateString() ?? today()->toDateString());
        $rows = collect();

        for ($cursor = $startDate->copy(); $cursor->lte($endDate); $cursor->addDay()) {
            $dateKey = $cursor->toDateString();
            $dayCredits = $creditsByDate->get($dateKey, collect());
            $amounts = [];
            $dayTotal = 0.0;

            foreach ($purchaserColumns as $column) {
                $amount = round((float) $dayCredits->where('purchaser_id', $column['id'])->sum('amount'), 2);
                $amounts[$column['id']] = $amount;
                $dayTotal += $amount;
            }

            $rows->push([
                'date' => $dateKey,
                'amounts' => $amounts,
                'total' => round($dayTotal, 2),
            ]);
        }

        return $rows;
    }
}
