<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Client;
use App\Models\CompanyAccountingEntry;
use App\Models\PayrollPayment;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoicePayment;
use App\Models\Shop;
use App\Models\ShopAccountingEntryLine;
use App\Models\ShopCredit;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopLoanEntry;
use App\Models\ShopStaffPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FinanceV2DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(Carbon $date): array
    {
        $period = $this->period($date);
        $client = $this->aishwaryaVegClient();

        return [
            ...$period,
            'green_leaf' => $this->greenLeafSummary($period['month_start'], $period['month_end']),
            'client' => $client,
            'client_summary' => $this->clientSummary($client, $period['month_start'], $period['month_end']),
            'direct_summary' => $this->directSalesSummary($period['month_start'], $period['month_end']),
            'pending_payments' => $this->pendingPayments(),
            'report_rows' => $this->dailyReportRows($period['month_start'], $period['date']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function greenLeafSection(string $section, Carbon $date): array
    {
        $period = $this->period($date);

        return [
            ...$period,
            'section' => $section,
            'summary' => $this->greenLeafSummary($period['month_start'], $period['month_end']),
            'rows' => match ($section) {
                'purchase' => $this->purchaseRows($period['month_start'], $period['month_end']),
                'expense' => $this->greenLeafExpenseRows($period['month_start'], $period['month_end']),
                'salary' => $this->salaryRows($period['month_start'], $period['month_end']),
                'credit-loan' => $this->greenLeafCreditLoanRows($period['month_start'], $period['month_end']),
                'balance' => $this->greenLeafBalanceRows($period['month_start'], $period['month_end']),
                default => collect(),
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function clientDashboard(Carbon $date): array
    {
        $period = $this->period($date);
        $client = $this->aishwaryaVegClient();

        return [
            ...$period,
            'client' => $client,
            'summary' => $this->clientSummary($client, $period['month_start'], $period['month_end']),
            'shops' => $this->shopSummaryRows($this->clientShops($client), $period['month_start'], $period['month_end']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function clientSection(string $section, Carbon $date): array
    {
        $period = $this->period($date);
        $client = $this->aishwaryaVegClient();
        $shops = $this->clientShops($client);

        return [
            ...$period,
            'client' => $client,
            'section' => $section,
            'summary' => $this->clientSummary($client, $period['month_start'], $period['month_end']),
            'rows' => $this->shopDetailRows($shops, $section, $period['month_start'], $period['month_end']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function shopDashboard(Shop $shop, Carbon $date): array
    {
        $period = $this->period($date);
        $shops = collect([$shop]);

        return [
            ...$period,
            'shop' => $shop->loadMissing('client'),
            'summary' => $this->shopSummaryRows($shops, $period['month_start'], $period['month_end'])->first(),
            'ledger_rows' => $this->shopLedgerRows($shop, $period['month_start'], $period['month_end']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reports(Carbon $date): array
    {
        $period = $this->period($date);
        $client = $this->aishwaryaVegClient();

        return [
            ...$period,
            'daily_rows' => $this->dailyReportRows($period['month_start'], $period['date']),
            'shop_rows' => $this->shopSummaryRows($this->clientShops($client), $period['month_start'], $period['month_end']),
            'direct_rows' => $this->shopSummaryRows($this->directSalesShops(), $period['month_start'], $period['month_end']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payments(Carbon $date): array
    {
        $period = $this->period($date);

        return [
            ...$period,
            'pending_payments' => $this->paymentRows('pending'),
            'processing_cheques' => $this->paymentRows('pending', chequeOnly: true),
            'approved_payments' => $this->paymentRows('approved'),
            'rejected_payments' => $this->paymentRows('rejected'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createPayment(Carbon $date): array
    {
        $period = $this->period($date);

        return [
            ...$period,
            'shops' => Shop::query()
                ->where(function (Builder $query): void {
                    $query->whereNotNull('client_id')
                        ->orWhere('accounting_enabled', true);
                })
                ->orderBy('name')
                ->get(),
        ];
    }

    /**
     * @param  array{total_due: float, applied_amount: float, credit_amount: float, invoices: array<int, array{invoice: ShopInvoice, amount: float}>}  $invoicePreview
     * @return array<string, mixed>
     */
    public function paymentShow(ShopInvoicePaymentRequest $paymentRequest, Carbon $date, array $invoicePreview): array
    {
        $period = $this->period($date);
        $paymentRequest->loadMissing(['shop.client', 'invoice', 'requestedBy', 'reviewedBy', 'allocations.invoice']);
        $shop = $paymentRequest->shop;
        $manualRows = $shop instanceof Shop
            ? $this->shopLedgerRows($shop, $period['month_start'], $period['month_end'])
                ->filter(fn (array $row): bool => $row['section'] !== 'purchase' && (float) $row['pending'] > 0)
                ->values()
            : collect();

        return [
            ...$period,
            'paymentRequest' => $paymentRequest,
            'invoicePreview' => $invoicePreview,
            'manualRows' => $manualRows,
        ];
    }

    /**
     * @return array{date:Carbon,month_start:Carbon,month_end:Carbon}
     */
    private function period(Carbon $date): array
    {
        $date = $date->copy()->startOfDay();

        return [
            'date' => $date,
            'month_start' => $date->copy()->startOfMonth(),
            'month_end' => $date->copy()->endOfMonth(),
        ];
    }

    private function aishwaryaVegClient(): ?Client
    {
        return Client::query()
            ->where('code', 'AISHWARYA_VEG')
            ->orWhere('name', 'Aishwarya Veg')
            ->first();
    }

    /**
     * @return Collection<int, Shop>
     */
    private function clientShops(?Client $client): Collection
    {
        if (! $client instanceof Client) {
            return collect();
        }

        return Shop::query()
            ->where('client_id', $client->id)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Shop>
     */
    private function directSalesShops(): Collection
    {
        return Shop::query()
            ->whereNull('client_id')
            ->where('accounting_enabled', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, float|int>
     */
    private function greenLeafSummary(Carbon $startDate, Carbon $endDate): array
    {
        $purchase = $this->purchaseTotals($startDate, $endDate);
        $expense = $this->companyExpenseTotal($startDate, $endDate);
        $salary = $this->salaryTotal($startDate, $endDate);
        $loan = $this->greenLeafLoanTotal($startDate, $endDate);
        $received = $this->shopReceivedTotal($startDate, $endDate);
        $paid = round((float) $purchase['paid'] + $expense + $salary + $loan, 2);

        return [
            'purchase_total' => $purchase['total'],
            'purchase_paid' => $purchase['paid'],
            'purchase_pending' => $purchase['pending'],
            'expense_total' => $expense,
            'salary_total' => $salary,
            'loan_total' => $loan,
            'total_received' => $received,
            'total_paid' => $paid,
            'balance' => round($received - $paid, 2),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function clientSummary(?Client $client, Carbon $startDate, Carbon $endDate): array
    {
        return $this->shopSummaryTotals($this->clientShops($client), $startDate, $endDate);
    }

    /**
     * @return array<string, float|int>
     */
    private function directSalesSummary(Carbon $startDate, Carbon $endDate): array
    {
        return $this->shopSummaryTotals($this->directSalesShops(), $startDate, $endDate);
    }

    /**
     * @param  Collection<int, Shop>  $shops
     * @return array<string, float|int>
     */
    private function shopSummaryTotals(Collection $shops, Carbon $startDate, Carbon $endDate): array
    {
        $rows = $this->shopSummaryRows($shops, $startDate, $endDate);

        return [
            'shop_count' => $shops->count(),
            'bills' => round((float) $rows->sum('bills'), 2),
            'expense' => round((float) $rows->sum('expense'), 2),
            'salary' => round((float) $rows->sum('salary'), 2),
            'loan' => round((float) $rows->sum('loan'), 2),
            'received' => round((float) $rows->sum('received'), 2),
            'credit' => round((float) $rows->sum('credit'), 2),
            'balance' => round((float) $rows->sum('closing_balance'), 2),
        ];
    }

    /**
     * @param  Collection<int, Shop>  $shops
     * @return Collection<int, array<string, mixed>>
     */
    private function shopSummaryRows(Collection $shops, Carbon $startDate, Carbon $endDate): Collection
    {
        return $shops->map(function (Shop $shop) use ($startDate, $endDate): array {
            $bills = $this->shopInvoiceTotal($shop, $startDate, $endDate);
            $received = $this->shopReceivedTotal($startDate, $endDate, $shop);
            $expense = $this->shopExpenseTotal($shop, $startDate, $endDate);
            $salary = $this->shopSalaryTotal($shop, $startDate, $endDate);
            $loan = $this->shopLoanTotal($shop, $startDate, $endDate);
            $credit = $this->shopCreditAvailable($shop);
            $opening = $this->shopOpeningBalance($shop, $startDate);

            return [
                'shop' => $shop,
                'opening_balance' => $opening,
                'bills' => $bills,
                'expense' => $expense,
                'salary' => $salary,
                'loan' => $loan,
                'received' => $received,
                'credit' => $credit,
                'closing_balance' => round($opening + $bills + $expense + $salary + $loan - $received - $credit, 2),
            ];
        })->values();
    }

    /**
     * @return array{total:float,paid:float,pending:float,count:int}
     */
    private function purchaseTotals(Carbon $startDate, Carbon $endDate): array
    {
        $invoices = PurchaseInvoice::query()
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->get();
        $payments = PurchaseInvoicePayment::query()
            ->whereDate('payment_date', '>=', $startDate)
            ->whereDate('payment_date', '<=', $endDate)
            ->get();
        $total = round((float) $invoices->sum('amount'), 2);
        $paid = round((float) $payments->sum('amount'), 2);

        if ($paid <= 0.0) {
            $paid = round((float) $invoices->sum('paid_amount'), 2);
        }

        return [
            'total' => $total,
            'paid' => $paid,
            'pending' => round(max(0, $total - $paid - (float) $payments->sum('discount_amount')), 2),
            'count' => $invoices->count(),
        ];
    }

    private function companyExpenseTotal(Carbon $startDate, Carbon $endDate): float
    {
        return round((float) CompanyAccountingEntry::query()
            ->where('type', 'expense')
            ->where('status', '!=', CompanyAccountingEntry::StatusReversed)
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->sum('amount'), 2);
    }

    private function salaryTotal(Carbon $startDate, Carbon $endDate): float
    {
        $payroll = PayrollPayment::query()
            ->whereDate('paid_on', '>=', $startDate)
            ->whereDate('paid_on', '<=', $endDate)
            ->sum('amount');
        $shopStaff = ShopStaffPayment::query()
            ->whereDate('paid_on', '>=', $startDate)
            ->whereDate('paid_on', '<=', $endDate)
            ->sum('amount');

        return round((float) $payroll + (float) $shopStaff, 2);
    }

    private function greenLeafLoanTotal(Carbon $startDate, Carbon $endDate): float
    {
        return round((float) ShopLoanEntry::query()
            ->approved()
            ->where('type', ShopLoanEntry::TypeCashGiven)
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->sum('amount'), 2);
    }

    private function shopInvoiceTotal(Shop $shop, Carbon $startDate, Carbon $endDate): float
    {
        return round((float) ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->sum('final_total'), 2);
    }

    private function shopReceivedTotal(Carbon $startDate, Carbon $endDate, ?Shop $shop = null): float
    {
        return round((float) ShopInvoicePaymentRequest::query()
            ->where('status', 'approved')
            ->when($shop instanceof Shop, fn (Builder $query) => $query->where('shop_id', $shop->id))
            ->whereDate('reviewed_at', '>=', $startDate)
            ->whereDate('reviewed_at', '<=', $endDate)
            ->sum('approved_amount'), 2);
    }

    private function shopExpenseTotal(Shop $shop, Carbon $startDate, Carbon $endDate): float
    {
        return round((float) ShopAccountingEntryLine::query()
            ->where('type', 'expense')
            ->whereHas('entry', function (Builder $query) use ($shop, $startDate, $endDate): void {
                $query
                    ->where('shop_id', $shop->id)
                    ->whereIn('status', ['submitted', 'approved', 'finalized'])
                    ->whereDate('business_date', '>=', $startDate)
                    ->whereDate('business_date', '<=', $endDate);
            })
            ->whereHas('category', fn (Builder $query) => $query->whereNotIn('purpose', ['staff_salary', 'staff_advance']))
            ->sum('amount'), 2);
    }

    private function shopSalaryTotal(Shop $shop, Carbon $startDate, Carbon $endDate): float
    {
        return round((float) ShopStaffPayment::query()
            ->where('shop_id', $shop->id)
            ->whereDate('paid_on', '>=', $startDate)
            ->whereDate('paid_on', '<=', $endDate)
            ->sum('amount'), 2);
    }

    private function shopLoanTotal(Shop $shop, Carbon $startDate, Carbon $endDate): float
    {
        return round((float) ShopLoanEntry::query()
            ->approved()
            ->where('shop_id', $shop->id)
            ->where('type', ShopLoanEntry::TypeCashGiven)
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->sum('amount'), 2);
    }

    private function shopCreditAvailable(Shop $shop): float
    {
        $credit = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shop->id)
            ->where('status', 'approved')
            ->with('allocations')
            ->get()
            ->sum(fn (ShopInvoicePaymentRequest $paymentRequest): float => $paymentRequest->remainingCreditAmount());

        return round((float) $credit, 2);
    }

    private function shopOpeningBalance(Shop $shop, Carbon $startDate): float
    {
        $bills = (float) ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', '<', $startDate)
            ->sum('balance_amount');
        $credit = (float) ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shop->id)
            ->where('status', 'approved')
            ->whereDate('reviewed_at', '<', $startDate)
            ->sum('credit_amount');

        return round($bills - $credit, 2);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function pendingPayments(): Collection
    {
        return ShopInvoicePaymentRequest::query()
            ->where('status', 'pending')
            ->with(['shop.client', 'invoice', 'requestedBy'])
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(fn (ShopInvoicePaymentRequest $payment): array => [
                'payment' => $payment,
                'shop' => $payment->shop,
                'amount' => round((float) $payment->requested_amount, 2),
                'method' => $payment->paymentMethodLabel(),
                'date' => $payment->payment_date?->toDateString() ?? $payment->created_at?->toDateString(),
            ]);
    }

    /**
     * @return Collection<int, ShopInvoicePaymentRequest>
     */
    private function paymentRows(string $status, bool $chequeOnly = false): Collection
    {
        return ShopInvoicePaymentRequest::query()
            ->where('status', $status)
            ->when($chequeOnly, fn (Builder $query) => $query->where('payment_method', 'cheque')->whereIn('cheque_status', ['pending', 'deposited']))
            ->when(! $chequeOnly && $status === 'pending', fn (Builder $query) => $query->where(function (Builder $query): void {
                $query->where('payment_method', '!=', 'cheque')
                    ->orWhereNull('payment_method')
                    ->orWhere('cheque_status', 'cleared');
            }))
            ->with(['shop.client', 'invoice', 'requestedBy', 'reviewedBy'])
            ->latest('id')
            ->limit(40)
            ->get();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function purchaseRows(Carbon $startDate, Carbon $endDate): Collection
    {
        return PurchaseInvoice::query()
            ->with(['supplier', 'payments'])
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->latest('created_at')
            ->limit(80)
            ->get()
            ->map(function (PurchaseInvoice $invoice): array {
                $paid = round((float) ($invoice->payments->sum('amount') ?: $invoice->paid_amount), 2);
                $discount = round((float) ($invoice->payments->sum('discount_amount') ?: $invoice->discount_amount), 2);
                $pending = round(max(0, (float) $invoice->amount - $paid - $discount), 2);

                return [
                    'date' => $invoice->created_at?->toDateString(),
                    'party' => $invoice->supplier?->name ?? 'Supplier',
                    'reference' => $invoice->invoice_number,
                    'description' => $invoice->purchaseSourceLabel(),
                    'amount' => round((float) $invoice->amount, 2),
                    'paid' => $paid,
                    'pending' => $pending,
                    'status' => $pending > 0 ? 'Pending' : 'Paid',
                    'view_url' => route('purchasing.invoices.show', $invoice),
                    'supplier_url' => $invoice->supplier_id ? route('purchaser.suppliers.show', ['supplier' => $invoice->supplier_id, 'date' => $invoice->created_at?->format('Y-m-d') ?: now()->format('Y-m-d')]) : null,
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function greenLeafExpenseRows(Carbon $startDate, Carbon $endDate): Collection
    {
        return CompanyAccountingEntry::query()
            ->with('category')
            ->where('type', 'expense')
            ->where('status', '!=', CompanyAccountingEntry::StatusReversed)
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->latest('business_date')
            ->latest('id')
            ->limit(80)
            ->get()
            ->map(fn (CompanyAccountingEntry $entry): array => [
                'date' => $entry->business_date?->toDateString(),
                'party' => 'Green Leaf',
                'reference' => $entry->reference ?? $entry->payment_reference,
                'description' => $entry->category?->name ?? $entry->description ?? 'Expense',
                'amount' => round((float) $entry->amount, 2),
                'paid' => round((float) $entry->amount, 2),
                'pending' => 0.0,
                'status' => $entry->paymentModeLabel(),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function salaryRows(Carbon $startDate, Carbon $endDate): Collection
    {
        $payroll = PayrollPayment::query()
            ->with(['employee', 'shop'])
            ->whereDate('paid_on', '>=', $startDate)
            ->whereDate('paid_on', '<=', $endDate)
            ->latest('paid_on')
            ->get()
            ->map(fn (PayrollPayment $payment): array => [
                'date' => $payment->paid_on?->toDateString(),
                'party' => $payment->employee?->name ?? 'Employee',
                'reference' => $payment->shop?->name ?? 'Green Leaf',
                'description' => str((string) $payment->payment_type)->replace('_', ' ')->title()->toString(),
                'amount' => round((float) $payment->amount, 2),
                'paid' => round((float) $payment->amount, 2),
                'pending' => 0.0,
                'status' => $payment->fund_source,
            ]);
        $shopStaff = ShopStaffPayment::query()
            ->with(['employee', 'shop'])
            ->whereDate('paid_on', '>=', $startDate)
            ->whereDate('paid_on', '<=', $endDate)
            ->latest('paid_on')
            ->get()
            ->map(fn (ShopStaffPayment $payment): array => [
                'date' => $payment->paid_on?->toDateString(),
                'party' => $payment->employee?->name ?? 'Shop staff',
                'reference' => $payment->shop?->name ?? 'Shop',
                'description' => str((string) $payment->payment_type)->replace('_', ' ')->title()->toString(),
                'amount' => round((float) $payment->amount, 2),
                'paid' => round((float) $payment->amount, 2),
                'pending' => 0.0,
                'status' => $payment->fund_source,
            ]);

        return $payroll->merge($shopStaff)->sortByDesc('date')->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function greenLeafCreditLoanRows(Carbon $startDate, Carbon $endDate): Collection
    {
        return ShopLoanEntry::query()
            ->approved()
            ->with('shop')
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->latest('business_date')
            ->limit(80)
            ->get()
            ->map(fn (ShopLoanEntry $entry): array => [
                'date' => $entry->business_date?->toDateString(),
                'party' => $entry->shop?->name ?? 'Shop',
                'reference' => $entry->title,
                'description' => $entry->description ?? $entry->typeLabel(),
                'amount' => round((float) $entry->amount, 2),
                'paid' => $entry->type === ShopLoanEntry::TypeRepayment ? round((float) $entry->amount, 2) : 0.0,
                'pending' => $entry->type === ShopLoanEntry::TypeCashGiven ? round((float) $entry->amount, 2) : 0.0,
                'status' => $entry->typeLabel(),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function greenLeafBalanceRows(Carbon $startDate, Carbon $endDate): Collection
    {
        $receivedRows = ShopInvoicePaymentRequest::query()
            ->where('status', 'approved')
            ->with(['shop', 'reviewedBy'])
            ->whereDate('reviewed_at', '>=', $startDate)
            ->whereDate('reviewed_at', '<=', $endDate)
            ->latest('reviewed_at')
            ->get()
            ->map(fn (ShopInvoicePaymentRequest $payment): array => [
                'date' => $payment->reviewed_at?->toDateString() ?? $payment->created_at?->toDateString(),
                'party' => $payment->shop?->name ?? 'Shop Collection',
                'reference' => $payment->payment_reference ?: 'REC-'.$payment->id,
                'description' => 'Collection received ('.$payment->paymentMethodLabel().')',
                'amount' => round((float) $payment->approved_amount, 2),
                'paid' => round((float) $payment->approved_amount, 2),
                'pending' => 0.0,
                'status' => 'Received',
                'view_url' => route('admin.finance-v2.payments.show', $payment),
                'supplier_url' => null,
            ]);

        $purchaseRows = $this->purchaseRows($startDate, $endDate);
        $expenseRows = $this->greenLeafExpenseRows($startDate, $endDate);
        $salaryRows = $this->salaryRows($startDate, $endDate);
        $loanRows = $this->greenLeafCreditLoanRows($startDate, $endDate);

        return $receivedRows
            ->toBase()
            ->merge($purchaseRows)
            ->merge($expenseRows)
            ->merge($salaryRows)
            ->merge($loanRows)
            ->sortByDesc('date')
            ->values();
    }

    /**
     * @param  Collection<int, Shop>  $shops
     * @return Collection<int, array<string, mixed>>
     */
    private function shopDetailRows(Collection $shops, string $section, Carbon $startDate, Carbon $endDate): Collection
    {
        return $shops
            ->flatMap(fn (Shop $shop): Collection => $this->shopLedgerRows($shop, $startDate, $endDate)
                ->filter(fn (array $row): bool => $section === 'balance' || $row['section'] === $section))
            ->sortByDesc('date')
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function shopLedgerRows(Shop $shop, Carbon $startDate, Carbon $endDate): Collection
    {
        $invoices = ShopInvoice::query()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->latest('business_date')
            ->get()
            ->map(fn (ShopInvoice $invoice): array => [
                'section' => 'purchase',
                'date' => $invoice->business_date?->toDateString(),
                'shop' => $shop,
                'party' => $shop->name,
                'reference' => $invoice->invoice_number,
                'description' => 'Product bill',
                'amount' => round((float) $invoice->final_total, 2),
                'paid' => round((float) $invoice->paid_amount, 2),
                'pending' => round((float) $invoice->balance_amount, 2),
                'status' => $invoice->payment_status,
            ]);
        $expenses = ShopAccountingEntryLine::query()
            ->with(['entry', 'category'])
            ->whereHas('entry', function (Builder $query) use ($shop, $startDate, $endDate): void {
                $query
                    ->where('shop_id', $shop->id)
                    ->whereIn('status', ['submitted', 'approved', 'finalized'])
                    ->whereDate('business_date', '>=', $startDate)
                    ->whereDate('business_date', '<=', $endDate);
            })
            ->where('type', 'expense')
            ->latest('id')
            ->get()
            ->map(function (ShopAccountingEntryLine $line) use ($shop): array {
                $purpose = (string) ($line->category?->purpose ?? 'expense');
                $section = in_array($purpose, ['staff_salary', 'staff_advance'], true) ? 'salary' : 'expense';

                return [
                    'section' => $section,
                    'date' => $line->entry?->business_date?->toDateString(),
                    'shop' => $shop,
                    'party' => $shop->name,
                    'reference' => $line->category?->name ?? 'Expense',
                    'description' => $line->description ?: 'Shop expense',
                    'amount' => round((float) $line->amount, 2),
                    'paid' => 0.0,
                    'pending' => round((float) $line->amount, 2),
                    'status' => $line->entry?->statusLabel() ?? 'Pending',
                ];
            });
        $loans = ShopLoanEntry::query()
            ->approved()
            ->where('shop_id', $shop->id)
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<=', $endDate)
            ->latest('business_date')
            ->get()
            ->map(fn (ShopLoanEntry $entry): array => [
                'section' => 'credit-loan',
                'date' => $entry->business_date?->toDateString(),
                'shop' => $shop,
                'party' => $shop->name,
                'reference' => $entry->title,
                'description' => $entry->description ?? $entry->typeLabel(),
                'amount' => round((float) $entry->amount, 2),
                'paid' => $entry->type === ShopLoanEntry::TypeRepayment ? round((float) $entry->amount, 2) : 0.0,
                'pending' => $entry->type === ShopLoanEntry::TypeCashGiven ? round((float) $entry->amount, 2) : 0.0,
                'status' => $entry->typeLabel(),
            ]);

        return $invoices->merge($expenses)->merge($loans)->sortByDesc('date')->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function dailyReportRows(Carbon $startDate, Carbon $endDate): Collection
    {
        $rows = collect();

        for ($cursor = $startDate->copy(); $cursor->lte($endDate); $cursor->addDay()) {
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();
            $greenLeaf = $this->greenLeafSummary($dayStart, $dayEnd);
            $client = $this->clientSummary($this->aishwaryaVegClient(), $dayStart, $dayEnd);

            $rows->push([
                'date' => $cursor->toDateString(),
                'label' => $cursor->format('d M'),
                'total_received' => round((float) $greenLeaf['total_received'], 2),
                'total_paid' => round((float) $greenLeaf['total_paid'], 2),
                'salary' => round((float) $greenLeaf['salary_total'] + (float) $client['salary'], 2),
                'loan' => round((float) $greenLeaf['loan_total'] + (float) $client['loan'], 2),
                'expense' => round((float) $greenLeaf['expense_total'] + (float) $client['expense'], 2),
                'balance' => round((float) $greenLeaf['balance'] + (float) $client['balance'], 2),
            ]);
        }

        return $rows->reverse()->values();
    }
}
