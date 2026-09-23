<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class ShopFinancialReportService
{
    public function __construct(
        private readonly ShopSettlementService $settlementService,
        private readonly ShopPaymentLedgerReconciliationService $reconciliationService,
        private readonly ShopAccountingOpeningService $openingService = new ShopAccountingOpeningService,
        private readonly ?ShopCashbookMonthConfigService $monthConfigService = null,
    ) {}

    /**
     * Generate complete read-only financial report for a shop over the specified period.
     *
     * @return array{
     *     period: array{
     *         start: string,
     *         end: string,
     *         month: string,
     *         mode: string,
     *         formatted_range: string,
     *         label: string,
     *         date: ?string,
     *         from: ?string,
     *         to: ?string
     *     },
     *     summary: array{
     *         sales: float,
     *         expenses: float,
     *         gl_bills: float,
     *         salary: float,
     *         petty_used: float,
     *         company_payable: float
     *     },
     *     settlement: array{
     *         due: float,
     *         received: float,
     *         allocated: float,
     *         unallocated: float,
     *         pending_verification: float,
     *         floating_cheques: float,
     *         pending: float
     *     },
     *     payment_modes: array<int, array{mode: string, label: string, amount: float}>,
     *     total_received: float,
     *     position: array{
     *         direction: string,
     *         amount: float,
     *         due_to_company: float,
     *         less_settled: float,
     *         company_reimbursement: float,
     *         current_balance: float
     *     },
     *     petty: array{
     *         opening: float,
     *         funded: float,
     *         used: float,
     *         current: float
     *     },
     *     expenses: array<int, array{
     *         category: string,
     *         amount: float,
     *         percentage: ?float,
     *         percentage_formatted: string,
     *         funding_split: array<int, array{source_key: string, label: string, amount: float}>
     *     }>
     * }
     */
    public function generate(
        ShopLedgerProfile|Shop|int $shopOrProfile,
        string $startDate,
        string $endDate,
        string $periodMode = 'month',
        ?string $month = null
    ): array {
        $shopId = $this->resolveShopId($shopOrProfile);
        $monthStr = $month ?: Carbon::parse($startDate)->format('Y-m');
        $monthCarbon = Carbon::createFromFormat('Y-m', $monthStr);

        // ── 1. Settings & Configured Entry Types ─────────────────────────────
        $configService = $this->monthConfigService ?? app(ShopCashbookMonthConfigService::class);
        $monthConfig = $configService->getConfigurationForMonth($shopId, $monthStr);
        $entrySettings = $monthConfig['settings'] ?? ShopLedgerEntrySetting::query()
            ->with('entryType')
            ->where('shop_id', $shopId)
            ->where('enabled', true)
            ->get();

        $salesSettingIds = $entrySettings
            ->filter(fn (ShopLedgerEntrySetting $s): bool => (bool) ($s->include_in_sales || $s->include_in_income))
            ->pluck('entry_type_id')
            ->filter()
            ->all();

        $expenseSettings = $entrySettings
            ->filter(fn (ShopLedgerEntrySetting $s): bool => (bool) $s->include_in_expense)
            ->values();

        $expenseSettingEntryTypeIds = $expenseSettings
            ->pluck('entry_type_id')
            ->filter()
            ->all();

        $salarySettingIds = $entrySettings
            ->filter(fn (ShopLedgerEntrySetting $s): bool => (bool) ($s->entryType && (
                $s->entryType->code === 'salary'
                || str_contains(strtolower($s->entryType->code ?? ''), 'salary')
                || str_contains(strtolower($s->entryType->name ?? ''), 'salary')
            )))
            ->pluck('entry_type_id')
            ->filter()
            ->all();

        // ── 2. Active Ledger Transactions in Period ──────────────────────────
        $transactions = ShopLedgerTransaction::query()
            ->with(['entryType', 'companyAccount'])
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->whereNotIn('status', ['void', 'voided', 'reversed'])
            ->whereNull('voided_at')
            ->get();

        // ── 3. Sales Calculation ─────────────────────────────────────────────
        $sales = (float) $transactions
            ->filter(function (ShopLedgerTransaction $tx) use ($salesSettingIds): bool {
                return (bool) $tx->affects_sales
                    || (bool) $tx->affects_income
                    || in_array($tx->entry_type_id, $salesSettingIds, true)
                    || $tx->direction === 'income';
            })
            ->sum('amount');
        $sales = round($sales, 2);

        // ── 4. Expenses & Funding Split Calculation ──────────────────────────
        $expenseTransactions = $transactions->filter(function (ShopLedgerTransaction $tx) use ($expenseSettingEntryTypeIds): bool {
            return (bool) $tx->affects_expense
                || in_array($tx->entry_type_id, $expenseSettingEntryTypeIds, true)
                || $tx->direction === 'expense';
        });

        $totalExpenses = round((float) $expenseTransactions->sum('amount'), 2);

        $expensesBreakdown = [];
        foreach ($expenseSettings as $setting) {
            $catTxs = $expenseTransactions->where('entry_type_id', $setting->entry_type_id);
            $catAmount = round((float) $catTxs->sum('amount'), 2);

            if ($catAmount <= 0 && $catTxs->isEmpty()) {
                continue;
            }

            $percentage = $sales > 0 ? round(($catAmount / $sales) * 100, 1) : null;
            $percentageFormatted = $sales > 0 ? $percentage.'%' : '—';

            // Split by funding source
            $fundingGroups = $catTxs->groupBy(fn (ShopLedgerTransaction $tx) => (string) ($tx->funding_source ?: 'shop_balance'));
            $fundingSplit = [];
            foreach ($fundingGroups as $sourceKey => $sourceTxs) {
                $sourceAmount = round((float) $sourceTxs->sum('amount'), 2);
                if ($sourceAmount > 0) {
                    $fundingSplit[] = [
                        'source_key' => $sourceKey,
                        'label' => $this->formatFundingSourceLabel($sourceKey),
                        'amount' => $sourceAmount,
                    ];
                }
            }

            $expensesBreakdown[] = [
                'category' => (string) ($setting->displayName() ?: $setting->entryType?->name ?: 'Expense'),
                'amount' => $catAmount,
                'percentage' => $percentage,
                'percentage_formatted' => $percentageFormatted,
                'funding_split' => $fundingSplit,
            ];
        }

        // Catch any expense transactions not matched to an enabled expense setting
        $unmatchedExpenseTxs = $expenseTransactions->reject(fn (ShopLedgerTransaction $tx) => in_array($tx->entry_type_id, $expenseSettingEntryTypeIds, true));
        if ($unmatchedExpenseTxs->isNotEmpty()) {
            foreach ($unmatchedExpenseTxs->groupBy('entry_type_id') as $eTypeId => $uTxs) {
                $uAmount = round((float) $uTxs->sum('amount'), 2);
                if ($uAmount <= 0) {
                    continue;
                }
                $eType = $uTxs->first()?->entryType;
                $percentage = $sales > 0 ? round(($uAmount / $sales) * 100, 1) : null;
                $percentageFormatted = $sales > 0 ? $percentage.'%' : '—';

                $fundingGroups = $uTxs->groupBy(fn (ShopLedgerTransaction $tx) => (string) ($tx->funding_source ?: 'shop_balance'));
                $fundingSplit = [];
                foreach ($fundingGroups as $sourceKey => $sourceTxs) {
                    $sourceAmount = round((float) $sourceTxs->sum('amount'), 2);
                    if ($sourceAmount > 0) {
                        $fundingSplit[] = [
                            'source_key' => $sourceKey,
                            'label' => $this->formatFundingSourceLabel($sourceKey),
                            'amount' => $sourceAmount,
                        ];
                    }
                }

                $expensesBreakdown[] = [
                    'category' => (string) ($eType?->name ?: 'Other Expense'),
                    'amount' => $uAmount,
                    'percentage' => $percentage,
                    'percentage_formatted' => $percentageFormatted,
                    'funding_split' => $fundingSplit,
                ];
            }
        }

        // ── 5. GL Bills ──────────────────────────────────────────────────────
        $glBills = (float) ShopInvoice::query()
            ->where('shop_id', $shopId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('business_date', [$startDate, $endDate])
            ->sum('final_total');
        $glBills = round($glBills, 2);

        // ── 6. Salary ────────────────────────────────────────────────────────
        $salary = (float) $transactions
            ->filter(fn (ShopLedgerTransaction $tx) => in_array($tx->entry_type_id, $salarySettingIds, true)
                || ($tx->entryType && $tx->entryType->code === 'salary'))
            ->sum('amount');
        $salary = round($salary, 2);

        // ── 7. Petty Calculation ─────────────────────────────────────────────
        $pettyUsed = abs((float) $transactions
            ->filter(fn (ShopLedgerTransaction $tx) => (float) $tx->petty_delta < 0)
            ->sum('petty_delta'));
        $pettyUsed = round($pettyUsed, 2);

        $companyFundedPetty = (float) $transactions
            ->filter(fn (ShopLedgerTransaction $tx) => (float) $tx->petty_delta > 0 && $tx->entryType?->code === 'company_to_petty')
            ->sum('petty_delta');
        $companyFundedPetty = round($companyFundedPetty, 2);

        $openingRecord = $this->openingService->getOpeningForDate($shopId, $startDate);
        $accountingStartDate = $this->openingService->getAccountingStartDate($shopId);
        $isPreOpening = $this->openingService->isPreOpening($shopId, $startDate);

        if (! $isPreOpening && $accountingStartDate !== null && $startDate === $accountingStartDate) {
            $openingPetty = (float) ($openingRecord?->opening_petty_balance ?? 0.0);
        } elseif (! $isPreOpening && $accountingStartDate !== null) {
            $openingPetty = (float) (ShopDailyLedgerSnapshot::query()
                ->where('shop_id', $shopId)
                ->where('business_date', '>=', $accountingStartDate)
                ->where('business_date', '<', $startDate)
                ->orderByDesc('business_date')
                ->orderByDesc('id')
                ->value('closing_petty') ?? ($openingRecord?->opening_petty_balance ?? 0.0));
        } else {
            $openingPetty = (float) (ShopDailyLedgerSnapshot::query()
                ->where('shop_id', $shopId)
                ->where('business_date', '<', $startDate)
                ->orderByDesc('business_date')
                ->orderByDesc('id')
                ->value('closing_petty') ?? 0.0);
        }
        $openingPetty = round($openingPetty, 2);

        $currentPetty = (float) (ShopDailyLedgerSnapshot::query()
            ->where('shop_id', $shopId)
            ->when(! $isPreOpening && $accountingStartDate !== null, fn (Builder $q) => $q->where('business_date', '>=', $accountingStartDate))
            ->where('business_date', '<=', $endDate)
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->value('closing_petty') ?? ($openingPetty + $companyFundedPetty - $pettyUsed));
        $currentPetty = round($currentPetty, 2);

        // ── 8. Settlement Obligations & Company Payable ──────────────────────
        $payableCalculation = $this->settlementService->calculateCompanyPayable($shopId, $startDate, $endDate);
        $settlementDue = round((float) ($payableCalculation['formula_net'] ?? 0.0), 2);
        if ($settlementDue == 0.0) {
            $txSettlementDelta = (float) $transactions->sum('settlement_delta');
            if ($txSettlementDelta != 0.0) {
                $settlementDue = round($txSettlementDelta, 2);
            }
        }
        $verifiedPaymentsTotal = round((float) ($payableCalculation['verified_payments_total'] ?? 0.0), 2);

        // ── 9. Shop Payment Requests & Allocation Metrics ────────────────────
        $paymentRequests = ShopInvoicePaymentRequest::query()
            ->with(['reconciliations.statementEntry', 'reconciliations.companyAccount', 'ledgerAllocations'])
            ->where('shop_id', $shopId)
            ->where('status', '!=', 'rejected')
            ->where(function (Builder $query) use ($startDate, $endDate): void {
                $query->whereBetween('payment_date', [$startDate, $endDate])
                    ->orWhere(function (Builder $q2) use ($startDate, $endDate): void {
                        $q2->whereNull('payment_date')
                            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);
                    });
            })
            ->get();

        $directReceipts = CompanyAccountStatementEntry::query()
            ->where('source_type', ShopLedgerTransaction::class)
            ->whereHasMorph('sourceRecord', [ShopLedgerTransaction::class], fn (Builder $q): Builder => $q->where('shop_id', $shopId))
            ->where('direction', 'in')
            ->where('status', 'reconciled')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->get();

        $totalAllocated = round((float) ShopPaymentLedgerAllocation::query()
            ->where('shop_id', $shopId)
            ->where('status', 'active')
            ->whereHas('ledgerTransaction', fn (Builder $q): Builder => $q->whereBetween('business_date', [$startDate, $endDate]))
            ->sum('amount'), 2);

        $paymentsSum = round((float) ($paymentRequests->where('status', 'approved')->sum('approved_amount') ?: $paymentRequests->sum('requested_amount')), 2);
        $totalReceived = $verifiedPaymentsTotal > 0
            ? $verifiedPaymentsTotal
            : round((float) ($paymentsSum + $directReceipts->sum('amount')), 2);

        $remainingCompanyPayable = round(max(0, $settlementDue - $totalAllocated), 2);
        $unallocated = round(max(0, $totalReceived - $totalAllocated), 2);
        $pendingVerification = round(max(0, $settlementDue - $totalReceived), 2);

        $floatingCheques = round((float) $paymentRequests
            ->filter(fn (ShopInvoicePaymentRequest $p) => $p->payment_method === 'cheque' && $p->cheque_status === 'pending')
            ->sum('requested_amount'), 2);

        // ── 10. Payment Mode Breakdown ───────────────────────────────────────
        $paymentModes = $this->resolvePaymentModes($paymentRequests, $directReceipts, $transactions);

        // ── 11. Current Position ─────────────────────────────────────────────
        $netPositionDifference = round($settlementDue - $totalReceived, 2);
        $positionDirection = match (true) {
            $netPositionDifference > 0 => 'shop_owes_company',
            $netPositionDifference < 0 => 'company_owes_shop',
            default => 'settled',
        };
        $positionAmount = abs($netPositionDifference);

        // ── 12. Period Formatting ────────────────────────────────────────────
        $formattedRange = $startDate === $endDate
            ? Carbon::parse($startDate)->format('d M Y')
            : Carbon::parse($startDate)->format('d M Y').' – '.Carbon::parse($endDate)->format('d M Y');

        $periodLabel = match ($periodMode) {
            'day' => 'Day: '.Carbon::parse($startDate)->format('d M Y'),
            'custom' => 'Custom: '.$formattedRange,
            default => $monthCarbon->format('F Y'),
        };

        $salesForPercentage = $sales > 0 ? $sales : 0.0;
        $netBalance = round($sales - $totalExpenses, 2);
        $netBalancePercentage = $salesForPercentage > 0 ? round(($netBalance / $salesForPercentage) * 100, 1) : null;
        $expensesPercentage = $salesForPercentage > 0 ? round(($totalExpenses / $salesForPercentage) * 100, 1) : null;
        $glBillsPercentage = $salesForPercentage > 0 ? round(($glBills / $salesForPercentage) * 100, 1) : null;
        $salaryPercentage = $salesForPercentage > 0 ? round(($salary / $salesForPercentage) * 100, 1) : null;
        $payablePercentage = $salesForPercentage > 0 ? round(($remainingCompanyPayable / $salesForPercentage) * 100, 1) : null;

        $pettyUsedFormatted = match (true) {
            $pettyUsed > 0.0001 => '-₹'.number_format($pettyUsed, 2),
            $pettyUsed < -0.0001 => '+₹'.number_format(abs($pettyUsed), 2),
            default => '₹0.00',
        };

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
                'month' => $monthStr,
                'mode' => $periodMode,
                'formatted_range' => $formattedRange,
                'label' => $periodLabel,
                'date' => $periodMode === 'day' ? $startDate : null,
                'from' => $periodMode === 'custom' ? $startDate : null,
                'to' => $periodMode === 'custom' ? $endDate : null,
            ],
            'summary' => [
                'sales' => $sales,
                'expenses' => $totalExpenses,
                'expenses_percentage' => $expensesPercentage,
                'net_balance' => $netBalance,
                'net_balance_percentage' => $netBalancePercentage,
                'gl_bills' => $glBills,
                'gl_bills_percentage' => $glBillsPercentage,
                'salary' => $salary,
                'salary_percentage' => $salaryPercentage,
                'petty_used' => $pettyUsed,
                'petty_used_formatted' => $pettyUsedFormatted,
                'company_payable' => $remainingCompanyPayable,
                'payable_percentage' => $payablePercentage,
            ],
            'settlement' => [
                'due' => $settlementDue,
                'received' => $totalReceived,
                'allocated' => $totalAllocated,
                'unallocated' => $unallocated,
                'pending_verification' => $pendingVerification,
                'floating_cheques' => $floatingCheques,
                'pending' => $remainingCompanyPayable,
            ],
            'payment_modes' => $paymentModes,
            'total_received' => $totalReceived,
            'position' => [
                'direction' => $positionDirection,
                'amount' => $positionAmount,
                'due_to_company' => $settlementDue,
                'less_settled' => $verifiedPaymentsTotal,
                'company_reimbursement' => 0.0,
                'current_balance' => $positionAmount,
            ],
            'petty' => [
                'opening' => $openingPetty,
                'funded' => $companyFundedPetty,
                'used' => $pettyUsed,
                'current' => $currentPetty,
            ],
            'expenses' => $expensesBreakdown,
        ];
    }

    /**
     * Group payments by dynamic mode.
     *
     * @param  Collection<int, ShopInvoicePaymentRequest>  $paymentRequests
     * @param  Collection<int, CompanyAccountStatementEntry>  $directReceipts
     * @param  Collection<int, ShopLedgerTransaction>  $transactions
     * @return array<int, array{mode: string, label: string, amount: float}>
     */
    private function resolvePaymentModes(
        Collection $paymentRequests,
        Collection $directReceipts,
        Collection $transactions
    ): array {
        $modeTotals = [];

        foreach ($paymentRequests as $payment) {
            $rawMethod = strtolower((string) ($payment->payment_method ?: 'cash'));
            $label = match ($rawMethod) {
                'cash' => 'Cash',
                'upi', 'paytm' => 'Paytm / UPI',
                'card', 'pos', 'credit_card', 'debit_card' => 'Card',
                'cheque', 'check' => 'Cheque',
                'bank', 'bank_transfer', 'neft', 'rtgs', 'imps' => 'Bank Transfer',
                default => ucfirst(str_replace('_', ' ', $rawMethod)),
            };

            $modeTotals[$label] = ($modeTotals[$label] ?? 0.0) + (float) $payment->requested_amount;
        }

        foreach ($directReceipts as $receipt) {
            $accountType = strtolower((string) ($receipt->companyAccount?->account_type ?: 'bank'));
            $label = $accountType === 'cash' ? 'Cash' : 'Bank Transfer';
            $modeTotals[$label] = ($modeTotals[$label] ?? 0.0) + (float) $receipt->amount;
        }

        // Also check transactions with code shop_paid_company if payment requests did not cover it
        if (empty($modeTotals)) {
            $paidTxs = $transactions->filter(fn (ShopLedgerTransaction $tx) => $tx->entryType?->code === 'shop_paid_company');
            foreach ($paidTxs as $tx) {
                $accountType = strtolower((string) ($tx->companyAccount?->account_type ?: 'cash'));
                $label = $accountType === 'cash' ? 'Cash' : ($tx->companyAccount?->name ?: 'Direct Payment');
                $modeTotals[$label] = ($modeTotals[$label] ?? 0.0) + (float) $tx->amount;
            }
        }

        $result = [];
        foreach ($modeTotals as $label => $amount) {
            $rounded = round((float) $amount, 2);
            if ($rounded > 0) {
                $result[] = [
                    'mode' => strtolower(str_replace([' ', '/'], '_', $label)),
                    'label' => $label,
                    'amount' => $rounded,
                ];
            }
        }

        return $result;
    }

    private function formatFundingSourceLabel(string $sourceKey): string
    {
        return match ($sourceKey) {
            'shop_balance' => 'Shop Balance',
            'shop_cash' => 'Shop Cash',
            'company' => 'Paid by Company',
            'petty' => 'Petty',
            'company_later' => 'Company Later',
            default => ucfirst(str_replace('_', ' ', $sourceKey)),
        };
    }

    /**
     * Generate complete settlement details report with component explanation,
     * obligations, payments, and allocation breakdown.
     *
     * @return array{
     *     period: array<string, mixed>,
     *     summary: array<string, mixed>,
     *     how_calculated: array{
     *         items: array<int, array<string, mixed>>,
     *         gross_additions: float,
     *         gross_deductions: float,
     *         formula_net: float,
     *         relation_name: string
     *     },
     *     obligations: array<int, array<string, mixed>>,
     *     payments: array<int, array<string, mixed>>,
     *     allocations: array<int, array<string, mixed>>
     * }
     */
    public function getSettlementDetailsReport(
        ShopLedgerProfile|Shop|int $shopOrProfile,
        string $startDate,
        string $endDate,
        string $periodMode = 'month',
        ?string $month = null
    ): array {
        $baseReport = $this->generate($shopOrProfile, $startDate, $endDate, $periodMode, $month);
        $shopId = $this->resolveShopId($shopOrProfile);
        $monthStr = $month ?: Carbon::parse($startDate)->format('Y-m');

        // ── 1. Calculate Authoritative Company Payable & Component Breakdown ──
        $payableCalculation = $this->settlementService->calculateCompanyPayable($shopId, $startDate, $endDate);
        $relationItems = $payableCalculation['items'] ?? [];
        $grossAdditions = round((float) ($payableCalculation['grossAdditions'] ?? 0.0), 2);
        $grossDeductions = round((float) ($payableCalculation['grossDeductions'] ?? 0.0), 2);
        $formulaNet = round((float) ($payableCalculation['formula_net'] ?? ($grossAdditions - $grossDeductions)), 2);

        $calculatedItems = [];
        foreach ($relationItems as $item) {
            $amt = round((float) ($item['amount'] ?? 0.0), 2);
            $role = strtolower((string) ($item['role'] ?? 'add'));
            $calculatedItems[] = [
                'name' => (string) ($item['name'] ?? 'Entry'),
                'category' => (string) ($item['category'] ?? 'general'),
                'role' => $role,
                'role_symbol' => $role === 'subtract' ? '-' : '+',
                'amount' => $amt,
                'signed_amount' => $role === 'subtract' ? -$amt : $amt,
                'source' => (string) ($payableCalculation['name'] ?? 'Configured Relation'),
            ];
        }

        $periodDue = (float) ($baseReport['settlement']['due'] ?? $formulaNet);
        if ($formulaNet == 0.0 && empty($calculatedItems) && $periodDue > 0) {
            $calculatedItems[] = [
                'name' => 'Settlement Obligation Base',
                'category' => 'sales',
                'role' => 'add',
                'role_symbol' => '+',
                'amount' => $periodDue,
                'signed_amount' => $periodDue,
                'source' => 'Daily Settlement Deltas',
            ];
            $grossAdditions = $periodDue;
            $formulaNet = $periodDue;
        }

        // ── 2. Obligations Breakdown in Period ───────────────────────────────
        $targetCategories = $this->settlementService->resolveExpenseAllocationTargets($shopId);
        $targetEntryTypeIds = $targetCategories->pluck('entry_type_id')->filter()->unique()->all();

        $obligationTransactions = ShopLedgerTransaction::query()
            ->with(['entryType', 'paymentLedgerAllocations' => fn ($q) => $q->where('status', 'active')])
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->whereNotIn('status', ['void', 'voided', 'reversed'])
            ->whereNull('voided_at')
            ->where(function (Builder $q) use ($targetEntryTypeIds): void {
                if (! empty($targetEntryTypeIds)) {
                    $q->whereIn('entry_type_id', $targetEntryTypeIds)
                        ->orWhere('settlement_delta', '!=', 0);
                } else {
                    $q->where('settlement_delta', '!=', 0)
                        ->orWhere('direction', 'income');
                }
            })
            ->orderBy('business_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $obligations = [];
        foreach ($obligationTransactions as $tx) {
            $allocatedAmt = round((float) $tx->paymentLedgerAllocations->sum('amount'), 2);
            $origAmt = round((float) ($tx->settlement_delta != 0 ? abs((float) $tx->settlement_delta) : (float) $tx->amount), 2);
            $remAmt = round(max(0, $origAmt - $allocatedAmt), 2);

            $statusLabel = match (true) {
                $remAmt <= 0.01 && $allocatedAmt > 0 => 'Settled',
                $allocatedAmt > 0 => 'Partially Settled',
                default => 'Unsettled',
            };

            $statusColor = match ($statusLabel) {
                'Settled' => 'emerald',
                'Partially Settled' => 'amber',
                default => 'slate',
            };

            $obligations[] = [
                'id' => $tx->id,
                'date' => $tx->business_date ? $tx->business_date->format('d M Y') : '—',
                'raw_date' => $tx->business_date?->toDateString(),
                'business_day' => $tx->business_date ? $tx->business_date->format('l') : '—',
                'category' => (string) ($tx->entryType?->name ?: 'Daily Settlement'),
                'description' => (string) ($tx->notes ?: ($tx->entryType?->name ?: 'Settlement Obligation')),
                'amount' => $origAmt,
                'status' => $statusLabel,
                'status_color' => $statusColor,
                'allocated' => $allocatedAmt,
                'remaining' => $remAmt,
                'reference_id' => $tx->reference_id,
            ];
        }

        // ── 3. Payments Received Breakdown in Period ─────────────────────────
        $paymentRequests = ShopInvoicePaymentRequest::query()
            ->with(['reconciliations.statementEntry', 'reconciliations.companyAccount', 'ledgerAllocations' => fn ($q) => $q->where('status', 'active')])
            ->where('shop_id', $shopId)
            ->where('status', '!=', 'rejected')
            ->where(function (Builder $query) use ($startDate, $endDate): void {
                $query->whereBetween('payment_date', [$startDate, $endDate])
                    ->orWhere(function (Builder $q2) use ($startDate, $endDate): void {
                        $q2->whereNull('payment_date')
                            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);
                    });
            })
            ->orderBy('payment_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $directReceipts = CompanyAccountStatementEntry::query()
            ->with('companyAccount')
            ->where('source_type', ShopLedgerTransaction::class)
            ->whereHasMorph('sourceRecord', [ShopLedgerTransaction::class], fn (Builder $q): Builder => $q->where('shop_id', $shopId))
            ->where('direction', 'in')
            ->where('status', 'reconciled')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date', 'asc')
            ->get();

        $payments = [];
        foreach ($paymentRequests as $p) {
            $paymentAmt = round((float) ($p->status === 'approved' && $p->approved_amount > 0 ? $p->approved_amount : $p->requested_amount), 2);
            $allocAmt = round((float) $p->ledgerAllocations->sum('amount'), 2);
            $unallocAmt = round(max(0, $paymentAmt - $allocAmt), 2);

            $rawMethod = strtolower((string) ($p->payment_method ?: 'cash'));
            $modeLabel = match ($rawMethod) {
                'cash' => 'Cash',
                'upi', 'paytm' => 'Paytm / UPI',
                'card', 'pos' => 'Card',
                'cheque', 'check' => 'Cheque',
                'bank', 'bank_transfer', 'neft', 'rtgs', 'imps' => 'Bank Transfer',
                default => ucfirst(str_replace('_', ' ', $rawMethod)),
            };

            $statusText = match (true) {
                $p->payment_method === 'cheque' && $p->cheque_status === 'pending' => 'Floating Cheque',
                $p->status === 'pending' => 'Pending Verification',
                $unallocAmt <= 0.01 && $allocAmt > 0 => 'Allocated',
                $allocAmt > 0 => 'Partially Allocated',
                default => 'Unallocated',
            };

            $statusBadgeColor = match ($statusText) {
                'Allocated' => 'emerald',
                'Partially Allocated' => 'amber',
                'Floating Cheque', 'Pending Verification' => 'sky',
                default => 'slate',
            };

            $accountName = $p->reconciliations->first()?->companyAccount?->name ?? '—';

            $paymentDateStr = $p->payment_date ? Carbon::parse($p->payment_date)->format('d M Y') : $p->created_at->format('d M Y');

            $payments[] = [
                'id' => $p->id,
                'date' => $paymentDateStr,
                'reference' => (string) ($p->payment_reference ?: ('PAY-'.$p->id)),
                'mode' => $modeLabel,
                'amount' => $paymentAmt,
                'allocated' => $allocAmt,
                'unallocated' => $unallocAmt,
                'status' => $statusText,
                'status_color' => $statusBadgeColor,
                'company_account' => $accountName,
                'cheque_bank' => $p->cheque_bank_name,
                'cheque_number' => $p->cheque_number,
            ];
        }

        foreach ($directReceipts as $dr) {
            $amt = round((float) $dr->amount, 2);
            $accountType = strtolower((string) ($dr->companyAccount?->account_type ?: 'bank'));
            $mode = $accountType === 'cash' ? 'Cash' : 'Bank Transfer';

            $payments[] = [
                'id' => $dr->id,
                'date' => $dr->transaction_date ? Carbon::parse($dr->transaction_date)->format('d M Y') : '—',
                'reference' => (string) ($dr->reference_number ?: ('STMT-'.$dr->id)),
                'mode' => $mode,
                'amount' => $amt,
                'allocated' => $amt,
                'unallocated' => 0.0,
                'status' => 'Allocated',
                'status_color' => 'emerald',
                'company_account' => $dr->companyAccount?->name ?? 'Company Account',
                'cheque_bank' => null,
                'cheque_number' => null,
            ];
        }

        // ── 4. Allocation Details Breakdown ──────────────────────────────────
        $allocationRecords = ShopPaymentLedgerAllocation::query()
            ->with(['paymentRequest', 'ledgerTransaction.entryType', 'reconciledBy'])
            ->where('shop_id', $shopId)
            ->where('status', 'active')
            ->whereHas('ledgerTransaction', fn (Builder $q): Builder => $q->whereBetween('business_date', [$startDate, $endDate]))
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $allocations = [];
        foreach ($allocationRecords as $alloc) {
            $pRef = $alloc->paymentRequest?->payment_reference ?: ($alloc->payment_request_id ? 'PAY-'.$alloc->payment_request_id : 'Direct Allocation');
            $obligationDate = $alloc->ledgerTransaction?->business_date?->format('d M Y');
            $obligationCategory = $alloc->ledgerTransaction?->entryType?->name ?? 'Daily Settlement';
            $obligationLabel = $obligationDate ? ($obligationDate.' — '.$obligationCategory) : $obligationCategory;

            $allocations[] = [
                'id' => $alloc->id,
                'allocated_at' => $alloc->created_at ? $alloc->created_at->format('d M Y h:i A') : '—',
                'payment_reference' => $pRef,
                'obligation_label' => $obligationLabel,
                'amount' => round((float) $alloc->amount, 2),
                'allocated_by' => $alloc->reconciledBy?->name ?? 'Admin / System',
                'status' => 'Active',
            ];
        }

        // Summary metrics & Status evaluation
        $periodDue = (float) ($baseReport['settlement']['due'] ?? $formulaNet);
        $totalAllocated = (float) ($baseReport['settlement']['allocated'] ?? 0.0);
        $totalReceived = (float) ($baseReport['settlement']['received'] ?? 0.0);
        $remainingDue = round(max(0, $periodDue - $totalAllocated), 2);
        $unallocated = (float) ($baseReport['settlement']['unallocated'] ?? max(0, $totalReceived - $totalAllocated));

        $statusKey = match (true) {
            abs($periodDue - $totalAllocated) < 0.01 => 'fully_settled',
            $periodDue > $totalAllocated => 'shop_owes_company',
            default => 'company_owes_shop',
        };

        $statusLabel = match ($statusKey) {
            'fully_settled' => 'FULLY SETTLED',
            'shop_owes_company' => 'SHOP OWES COMPANY',
            'company_owes_shop' => 'COMPANY OWES SHOP',
        };

        $statusColor = match ($statusKey) {
            'fully_settled' => 'emerald',
            'shop_owes_company' => 'amber',
            'company_owes_shop' => 'sky',
        };

        return [
            'period' => $baseReport['period'],
            'summary' => [
                'sales' => (float) ($baseReport['summary']['sales'] ?? 0.0),
                'period_due' => $periodDue,
                'received' => $totalReceived,
                'allocated' => $totalAllocated,
                'unallocated' => $unallocated,
                'pending_verification' => (float) ($baseReport['settlement']['pending_verification'] ?? 0.0),
                'floating_cheques' => (float) ($baseReport['settlement']['floating_cheques'] ?? 0.0),
                'remaining_due' => $remainingDue,
                'status' => $statusKey,
                'status_label' => $statusLabel,
                'status_color' => $statusColor,
            ],
            'how_calculated' => [
                'items' => $calculatedItems,
                'gross_additions' => $grossAdditions,
                'gross_deductions' => $grossDeductions,
                'formula_net' => $formulaNet,
                'relation_name' => (string) ($payableCalculation['name'] ?? 'Company Payable Relation'),
            ],
            'obligations' => $obligations,
            'payments' => $payments,
            'allocations' => $allocations,
        ];
    }

    private function resolveShopId(ShopLedgerProfile|Shop|int $shopOrProfile): int
    {
        if (is_int($shopOrProfile)) {
            return $shopOrProfile;
        }

        return (int) ($shopOrProfile->shop_id ?? $shopOrProfile->id);
    }
}
