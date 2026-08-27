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
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopLoanEntry;
use App\Models\ShopStaffPayment;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class FinanceV2DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(Carbon $date): array
    {
        $period = $this->period($date);
        $clients = $this->activeClients();
        $clientSummaries = $clients->map(function (Client $client) use ($period): array {
            $summary = $this->clientSummary($client, $period['month_start'], $period['month_end']);

            return [
                'client' => $client,
                'summary' => $summary,
            ];
        })->values();

        return [
            ...$period,
            'green_leaf' => $this->greenLeafSummary($period['month_start'], $period['month_end']),
            'clients' => $clients,
            'client_summaries' => $clientSummaries,
            'client' => $clients->first(),
            'client_summary' => $clientSummaries->first()['summary'] ?? $this->emptyShopSummaryTotals(),
            'direct_summary' => $this->directSalesSummary($period['month_start'], $period['month_end']),
            'pending_payments' => $this->pendingPayments(),
            'report_rows' => $this->dailyReportRows($period['month_start'], $period['date']),
            'company_position' => $this->companyPositionSummary($period['month_start'], $period['month_end']),
            'alerts' => $this->dashboardAlerts(),
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
    public function clientDashboard(Client $client, Carbon $date): array
    {
        $period = $this->period($date);

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
    public function clientSection(Client $client, string $section, Carbon $date): array
    {
        $period = $this->period($date);
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
        $clients = $this->activeClients();
        $clientShops = $clients->flatMap(fn (Client $client) => $this->clientShops($client))->values();

        return [
            ...$period,
            'daily_rows' => $this->dailyReportRows($period['month_start'], $period['date']),
            'shop_rows' => $this->shopSummaryRows($clientShops, $period['month_start'], $period['month_end']),
            'direct_rows' => $this->shopSummaryRows($this->directSalesShops(), $period['month_start'], $period['month_end']),
            'clients' => $clients,
            'company_position' => $this->companyPositionSummary($period['month_start'], $period['month_end']),
            'payable_ageing' => $this->companyPayableAgeing(),
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
                ->with('client')
                ->where(function (Builder $query): void {
                    $query->whereNotNull('client_id')
                        ->orWhere('accounting_enabled', true);
                })
                ->orderBy('name')
                ->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function shopPaymentCreateContext(Shop $shop, Carbon $date, float $amount = 0.0): array
    {
        $period = $this->period($date);
        $shop->loadMissing('client');
        $shopInvoiceService = app(ShopInvoiceService::class);
        $preview = $shopInvoiceService->allocationPreviewForShop($shop->id, $amount);
        $summary = $this->shopSummaryRows(collect([$shop]), $period['month_start'], $period['month_end'])->first() ?? [];
        $availableCredit = $shopInvoiceService->availableShopCredit($shop->id);
        $pendingInvoices = $shopInvoiceService->pendingInvoicesForShop($shop->id);
        $companyPayableRemaining = 0.0;
        $companyPayablePendingCount = 0;
        $companyPayables = [];

        if (Schema::hasColumn('shop_accounting_entry_lines', 'company_payable_status')) {
            $payableLines = app(CompanyPayableService::class)->openPayables($shop);
            $companyPayablePendingCount = $payableLines->where('company_payable_status', ShopAccountingEntryLine::PayablePending)->count();
            $companyPayableRemaining = round($payableLines->sum(
                fn (ShopAccountingEntryLine $line): float => $line->remainingCompanyPayableAmount()
            ), 2);
            $companyPayables = $payableLines->map(function (ShopAccountingEntryLine $line): array {
                $status = (string) $line->company_payable_status;

                return [
                    'id' => $line->id,
                    'category' => $line->category?->name ?? 'Expense',
                    'status' => $status,
                    'status_label' => match ($status) {
                        ShopAccountingEntryLine::PayablePending => 'Ready for review',
                        ShopAccountingEntryLine::PayableApproved => 'Approved',
                        default => str($status)->replace('_', ' ')->title()->toString(),
                    },
                    'amount' => round((float) ($line->company_approved_amount ?? $line->company_payable_amount ?? $line->amount), 2),
                    'remaining' => round($line->remainingCompanyPayableAmount(), 2),
                    'business_date' => $line->entry?->business_date?->toDateString(),
                    'url' => route('admin.finance-v2.company-payables.show', $line),
                ];
            })->values()->all();
        }

        $recentPayments = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shop->id)
            ->with(['invoice', 'requestedBy', 'reviewedBy'])
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (ShopInvoicePaymentRequest $payment): array => [
                'id' => $payment->id,
                'date' => $payment->payment_date?->toDateString() ?? $payment->created_at?->toDateString(),
                'method' => $payment->paymentMethodLabel(),
                'amount' => round((float) $payment->requested_amount, 2),
                'verified_amount' => round((float) ($payment->admin_verified_amount ?? $payment->approved_amount ?? 0), 2),
                'status' => $payment->statusLabel(),
                'status_raw' => $payment->status,
                'url' => route('admin.finance-v2.payments.show', $payment),
            ])
            ->values()
            ->all();

        $invoices = $pendingInvoices->map(fn (ShopInvoice $invoice): array => [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'business_date' => $invoice->business_date?->toDateString(),
            'balance_amount' => round((float) $invoice->balance_amount, 2),
            'final_total' => round((float) $invoice->final_total, 2),
        ])->values()->all();

        $allocationRows = collect($preview['invoices'])->map(fn (array $row): array => [
            'id' => $row['invoice']->id,
            'invoice_number' => $row['invoice']->invoice_number,
            'business_date' => $row['invoice']->business_date?->toDateString(),
            'balance_amount' => round((float) $row['invoice']->balance_amount, 2),
            'allocate_amount' => round((float) $row['amount'], 2),
        ])->values()->all();

        $applied = (float) $preview['applied_amount'];
        $credit = (float) $preview['credit_amount'];
        $message = match (true) {
            $amount <= 0 => 'Enter a payment amount to preview invoice allocation.',
            $applied > 0 && $credit > 0 => 'This payment will cover Rs. '.number_format($applied, 2).' of bills; Rs. '.number_format($credit, 2).' becomes shop credit.',
            $applied > 0 => 'This payment will cover Rs. '.number_format($applied, 2).' of pending bills.',
            default => 'No pending bills. The full amount will become shop credit (Rs. '.number_format($credit, 2).').',
        };

        return [
            'shop' => [
                'id' => $shop->id,
                'name' => $shop->name,
                'code' => $shop->code,
                'client_name' => $shop->client?->name,
            ],
            'summary' => [
                'pending_bills' => (float) $preview['total_due'],
                'available_credit' => $availableCredit,
                'closing_balance' => (float) ($summary['closing_balance'] ?? 0),
                'bills_mtd' => (float) ($summary['bills'] ?? 0),
                'received_mtd' => (float) ($summary['received'] ?? 0),
                'petty_mtd' => (float) ($summary['loan'] ?? 0),
                'company_payable_remaining' => $companyPayableRemaining,
                'company_payable_pending_count' => $companyPayablePendingCount,
            ],
            'preview' => [
                'amount' => round($amount, 2),
                'total_due' => (float) $preview['total_due'],
                'applied_amount' => $applied,
                'credit_amount' => $credit,
                'message' => $message,
                'allocations' => $allocationRows,
            ],
            'pending_invoices' => $invoices,
            'recent_payments' => $recentPayments,
            'company_payables' => $companyPayables,
            'company_payables_url' => route('admin.finance-v2.company-payables.index', ['date' => $period['date']->toDateString()]),
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
     * @return Collection<int, Client>
     */
    public function activeClients(): Collection
    {
        return Client::query()
            ->active()
            ->orderBy('name')
            ->get();
    }

    public function resolveClient(Client|string|int|null $client = null): ?Client
    {
        if ($client instanceof Client) {
            return $client;
        }

        if (is_int($client) || (is_string($client) && ctype_digit($client))) {
            return Client::query()->find((int) $client);
        }

        if (is_string($client) && $client !== '') {
            return Client::query()
                ->where('code', $client)
                ->orWhere('name', $client)
                ->first();
        }

        return null;
    }

    /**
     * @return Collection<int, Shop>
     */
    private function financeEnabledShops(?Client $client = null): Collection
    {
        if ($client instanceof Client) {
            return $this->clientShops($client);
        }

        return Shop::query()
            ->where(function (Builder $query): void {
                $query->whereNotNull('client_id')
                    ->orWhere('accounting_enabled', true);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, float|int>
     */
    private function emptyShopSummaryTotals(): array
    {
        return [
            'shop_count' => 0,
            'bills' => 0.0,
            'expense' => 0.0,
            'salary' => 0.0,
            'loan' => 0.0,
            'received' => 0.0,
            'credit' => 0.0,
            'balance' => 0.0,
            'company_payable' => 0.0,
            'petty_outstanding' => 0.0,
            'net_position' => 0.0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function companyPositionSummary(Carbon $startDate, Carbon $endDate): array
    {
        $purchase = $this->purchaseTotals($startDate, $endDate);
        $shops = $this->financeEnabledShops();
        $shopReceivable = round((float) $this->shopSummaryRows($shops, $startDate, $endDate)->sum('closing_balance'), 2);
        $companyPayable = $this->totalCompanyPayableOutstanding();
        $pettyOutstanding = $this->totalPettyOutstanding();
        $pendingCompanyRequests = $this->pendingCompanyPayableCount();
        $netPosition = round($shopReceivable - $companyPayable, 2);

        return [
            'company_cash_bank' => round((float) ($this->greenLeafSummary($startDate, $endDate)['balance'] ?? 0), 2),
            'direct_bills_payable' => $purchase['pending'],
            'total_shop_receivable' => $shopReceivable,
            'total_company_payable' => $companyPayable,
            'petty_outstanding' => $pettyOutstanding,
            'pending_company_expense_requests' => $pendingCompanyRequests,
            'net_client_position' => $netPosition,
            'net_client_position_message' => $netPosition >= 0
                ? 'Green Leaf must receive ₹'.number_format(abs($netPosition), 2)
                : 'Green Leaf must pay shops ₹'.number_format(abs($netPosition), 2),
        ];
    }

    public function totalCompanyPayableOutstanding(): float
    {
        if (! Schema::hasColumn('shop_accounting_entry_lines', 'company_payable_status')) {
            return 0.0;
        }

        return round((float) ShopAccountingEntryLine::query()
            ->where('funding_source', 'company')
            ->whereIn('company_payable_status', ['pending', 'approved'])
            ->selectRaw('COALESCE(SUM(COALESCE(company_approved_amount, company_payable_amount, amount) - COALESCE(company_settled_amount, 0)), 0) as remaining')
            ->value('remaining'), 2);
    }

    public function totalPettyOutstanding(): float
    {
        return round((float) ShopLoanEntry::query()
            ->approved()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'cash_given' THEN amount WHEN type = 'repayment' THEN -amount ELSE 0 END), 0) as balance")
            ->value('balance'), 2);
    }

    public function pendingCompanyPayableCount(): int
    {
        if (! Schema::hasColumn('shop_accounting_entry_lines', 'company_payable_status')) {
            return 0;
        }

        return ShopAccountingEntryLine::query()
            ->where('funding_source', 'company')
            ->where('company_payable_status', 'pending')
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardAlerts(): array
    {
        $pendingPayments = ShopInvoicePaymentRequest::query()->where('status', 'pending')->count();
        $unallocatedCredits = ShopInvoicePaymentRequest::query()
            ->where('status', 'approved')
            ->where('credit_amount', '>', 0)
            ->count();
        $overduePurchaseInvoices = PurchaseInvoice::query()
            ->notCancelled()
            ->whereRaw('amount > COALESCE(paid_amount, 0) + COALESCE(discount_amount, 0)')
            ->where('created_at', '<', now()->subDays(30))
            ->count();

        $pendingCompany = 0;
        $pendingOver7 = 0;
        $pendingOver14 = 0;
        $pendingOver30 = 0;

        if (Schema::hasColumn('shop_accounting_entry_lines', 'company_payable_status')) {
            $pendingCompany = $this->pendingCompanyPayableCount();
            $pendingOver7 = ShopAccountingEntryLine::query()
                ->where('funding_source', 'company')
                ->where('company_payable_status', 'pending')
                ->where('created_at', '<', now()->subDays(7))
                ->count();
            $pendingOver14 = ShopAccountingEntryLine::query()
                ->where('funding_source', 'company')
                ->where('company_payable_status', 'pending')
                ->where('created_at', '<', now()->subDays(14))
                ->count();
            $pendingOver30 = ShopAccountingEntryLine::query()
                ->where('funding_source', 'company')
                ->where('company_payable_status', 'pending')
                ->where('created_at', '<', now()->subDays(30))
                ->count();
        }

        return [
            'new_company_expense_requests' => $pendingCompany,
            'company_requests_over_7_days' => $pendingOver7,
            'company_requests_over_14_days' => $pendingOver14,
            'company_requests_over_30_days' => $pendingOver30,
            'shop_payments_awaiting_approval' => $pendingPayments,
            'unallocated_shop_payments' => $unallocatedCredits,
            'purchase_invoices_overdue' => $overduePurchaseInvoices,
            'journal_posting_failures' => 0,
        ];
    }

    /**
     * @return array<string, float>
     */
    public function companyPayableAgeing(): array
    {
        $buckets = [
            '0_7' => 0.0,
            '8_14' => 0.0,
            '15_30' => 0.0,
            '31_60' => 0.0,
            'above_60' => 0.0,
        ];

        if (! Schema::hasColumn('shop_accounting_entry_lines', 'company_payable_status')) {
            return $buckets;
        }

        $lines = ShopAccountingEntryLine::query()
            ->where('funding_source', 'company')
            ->whereIn('company_payable_status', ['pending', 'approved'])
            ->get(['amount', 'company_payable_amount', 'company_approved_amount', 'company_settled_amount', 'created_at']);

        foreach ($lines as $line) {
            $remaining = round(
                (float) ($line->company_approved_amount ?? $line->company_payable_amount ?? $line->amount)
                - (float) ($line->company_settled_amount ?? 0),
                2
            );
            if ($remaining <= 0) {
                continue;
            }

            $days = (int) ($line->created_at?->diffInDays(now()) ?? 0);
            $key = match (true) {
                $days <= 7 => '0_7',
                $days <= 14 => '8_14',
                $days <= 30 => '15_30',
                $days <= 60 => '31_60',
                default => 'above_60',
            };
            $buckets[$key] = round($buckets[$key] + $remaining, 2);
        }

        return $buckets;
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
            ->notCancelled()
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

        return collect()
            ->concat($payroll)
            ->concat($shopStaff)
            ->sortByDesc('date')
            ->values();
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

        return collect()
            ->concat($receivedRows)
            ->concat($purchaseRows)
            ->concat($expenseRows)
            ->concat($salaryRows)
            ->concat($loanRows)
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

        return collect()
            ->concat($invoices)
            ->concat($expenses)
            ->concat($loans)
            ->sortByDesc('date')
            ->values();
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
            $clientTotals = $this->activeClients()
                ->map(fn (Client $client) => $this->clientSummary($client, $dayStart, $dayEnd));

            $rows->push([
                'date' => $cursor->toDateString(),
                'label' => $cursor->format('d M'),
                'total_received' => round((float) $greenLeaf['total_received'], 2),
                'total_paid' => round((float) $greenLeaf['total_paid'], 2),
                'salary' => round((float) $greenLeaf['salary_total'] + (float) $clientTotals->sum('salary'), 2),
                'loan' => round((float) $greenLeaf['loan_total'] + (float) $clientTotals->sum('loan'), 2),
                'expense' => round((float) $greenLeaf['expense_total'] + (float) $clientTotals->sum('expense'), 2),
                'balance' => round((float) $greenLeaf['balance'] + (float) $clientTotals->sum('balance'), 2),
            ]);
        }

        return $rows->reverse()->values();
    }
}
