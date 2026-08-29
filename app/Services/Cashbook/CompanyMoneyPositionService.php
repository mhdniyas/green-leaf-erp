<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\ShopBankSettlementAdjustment;
use App\Models\Cashbook\ShopBankSettlementAdjustmentRule;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompanyMoneyPositionService
{
    public function __construct(
        private readonly BankSettlementExpectedAmountService $expectedAmountService = new BankSettlementExpectedAmountService
    ) {}

    /**
     * Get complete money position breakdown across Bank Accounts, Company Cash,
     * Cash with Shops, and Floating Cheques.
     *
     * @return array<string, mixed>
     */
    public function getMoneyPositionSummary(?string $businessDate = null): array
    {
        $businessDate = $businessDate ?? today()->toDateString();

        $accounts = CompanyAccount::query()
            ->where('enabled', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        // 1. Group statement metrics per account using a single fast query
        $statementMetrics = CompanyAccountStatementEntry::query()
            ->whereIn('company_account_id', $accounts->pluck('id'))
            ->whereNotIn('status', ['superseded', 'duplicate_flagged'])
            ->select('company_account_id')
            ->selectRaw("SUM(CASE WHEN is_finalized = 0 AND direction = 'in' THEN amount ELSE 0 END) as pending_in")
            ->selectRaw("SUM(CASE WHEN is_finalized = 0 AND direction = 'out' THEN amount ELSE 0 END) as pending_out")
            ->selectRaw('SUM(matched_amount) as matched_total')
            ->selectRaw('SUM(amount) as eligible_total')
            ->selectRaw('SUM(CASE WHEN is_finalized = 0 THEN 1 ELSE 0 END) as pending_count')
            ->groupBy('company_account_id')
            ->get()
            ->keyBy('company_account_id');

        $bankAccounts = [];
        $cashAccounts = [];

        $totalBankVerified = 0.0;
        $totalBankPending = 0.0;
        $totalBankProjected = 0.0;

        $totalCashVerified = 0.0;
        $totalCashPending = 0.0;
        $totalCashProjected = 0.0;

        foreach ($accounts as $account) {
            $metrics = $statementMetrics->get($account->id);
            $verified = round((float) $account->current_balance, 2);
            $pendingIn = round((float) ($metrics?->pending_in ?? 0), 2);
            $pendingOut = round((float) ($metrics?->pending_out ?? 0), 2);
            $netPending = round($pendingIn - $pendingOut, 2);
            $projected = round($verified + $netPending, 2);

            $matchedTotal = round((float) ($metrics?->matched_total ?? 0), 2);
            $eligibleTotal = round((float) ($metrics?->eligible_total ?? 0), 2);
            $reconciliationPercentage = $eligibleTotal > 0.0
                ? round(($matchedTotal / $eligibleTotal) * 100, 1)
                : 100.0;

            $accountData = [
                'account' => $account,
                'verified_balance' => $verified,
                'pending_in' => $pendingIn,
                'pending_out' => $pendingOut,
                'net_pending' => $netPending,
                'projected_position' => $projected,
                'reconciliation_percentage' => min(100.0, max(0.0, $reconciliationPercentage)),
                'pending_count' => (int) ($metrics?->pending_count ?? 0),
            ];

            if ($account->account_type === 'cash') {
                $cashAccounts[] = $accountData;
                $totalCashVerified += $verified;
                $totalCashPending += $netPending;
                $totalCashProjected += $projected;
            } else {
                $bankAccounts[] = $accountData;
                $totalBankVerified += $verified;
                $totalBankPending += $netPending;
                $totalBankProjected += $projected;
            }
        }

        // 2. Cash With Shops breakdown
        $cashWithShops = $this->getCashWithShopsBreakdown($businessDate);

        // 3. Floating Cheques summary
        $floatingCheques = $this->getFloatingChequesSummary($businessDate);

        // 4. Consolidated high-level classification
        $verifiedCompanyMoney = round($totalBankVerified + $totalCashVerified, 2);
        $expectedInTransitMoney = round($totalBankPending + $cashWithShops['total_cash_with_shops'] + $floatingCheques['total_floating'], 2);

        return [
            'verified_company_money' => $verifiedCompanyMoney,
            'expected_in_transit_money' => $expectedInTransitMoney,
            'bank_accounts' => [
                'accounts' => $bankAccounts,
                'total_verified' => round($totalBankVerified, 2),
                'total_pending' => round($totalBankPending, 2),
                'total_projected' => round($totalBankProjected, 2),
            ],
            'company_cash' => [
                'accounts' => $cashAccounts,
                'total_verified' => round($totalCashVerified, 2),
                'total_pending' => round($totalCashPending, 2),
                'total_projected' => round($totalCashProjected, 2),
            ],
            'cash_with_shops' => $cashWithShops,
            'floating_cheques' => $floatingCheques,
        ];
    }

    /**
     * Get single account position details.
     *
     * @return array<string, mixed>
     */
    public function getAccountPosition(CompanyAccount $account): array
    {
        $metrics = CompanyAccountStatementEntry::query()
            ->where('company_account_id', $account->id)
            ->whereNotIn('status', ['superseded', 'duplicate_flagged'])
            ->selectRaw("SUM(CASE WHEN is_finalized = 0 AND direction = 'in' THEN amount ELSE 0 END) as pending_in")
            ->selectRaw("SUM(CASE WHEN is_finalized = 0 AND direction = 'out' THEN amount ELSE 0 END) as pending_out")
            ->selectRaw('SUM(matched_amount) as matched_total')
            ->selectRaw('SUM(amount) as eligible_total')
            ->selectRaw('SUM(CASE WHEN is_finalized = 0 THEN 1 ELSE 0 END) as pending_count')
            ->first();

        $verified = round((float) $account->current_balance, 2);
        $pendingIn = round((float) ($metrics?->pending_in ?? 0), 2);
        $pendingOut = round((float) ($metrics?->pending_out ?? 0), 2);
        $netPending = round($pendingIn - $pendingOut, 2);
        $projected = round($verified + $netPending, 2);

        $matchedTotal = round((float) ($metrics?->matched_total ?? 0), 2);
        $eligibleTotal = round((float) ($metrics?->eligible_total ?? 0), 2);
        $reconciliationPercentage = $eligibleTotal > 0.0
            ? round(($matchedTotal / $eligibleTotal) * 100, 1)
            : 100.0;

        return [
            'account' => $account,
            'verified_balance' => $verified,
            'pending_in' => $pendingIn,
            'pending_out' => $pendingOut,
            'net_pending' => $netPending,
            'projected_position' => $projected,
            'reconciliation_percentage' => min(100.0, max(0.0, $reconciliationPercentage)),
            'pending_count' => (int) ($metrics?->pending_count ?? 0),
        ];
    }

    /**
     * Calculate physical cash retained at shops not yet verified/handed over to company.
     *
     * @return array{total_cash_with_shops: float, shops: array<int, array<string, mixed>>}
     */
    public function getCashWithShopsBreakdown(?string $businessDate = null): array
    {
        $businessDate = $businessDate ?? today()->toDateString();
        $shops = Shop::query()->where('status', 'active')->orderBy('name')->get();

        // 1. Get latest closing shop positions from daily snapshots
        $snapshots = ShopDailyLedgerSnapshot::query()
            ->whereIn('shop_id', $shops->pluck('id'))
            ->whereDate('business_date', '<=', $businessDate)
            ->orderBy('business_date', 'desc')
            ->get()
            ->groupBy('shop_id')
            ->map(fn (Collection $group) => $group->first());

        // 2. Query pending cash collections awaiting handover verification
        $pendingCashStatements = CompanyAccountStatementEntry::query()
            ->where('source_type', ShopLedgerTransaction::class)
            ->where('is_finalized', false)
            ->whereNotIn('status', ['superseded', 'duplicate_flagged'])
            ->with(['sourceRecord.entryType', 'companyAccount'])
            ->get()
            ->filter(fn (CompanyAccountStatementEntry $stmt) => $stmt->companyAccount?->account_type === 'cash' || str_contains(strtolower((string) $stmt->sourceRecord?->entryType?->code), 'cash'))
            ->groupBy(fn (CompanyAccountStatementEntry $stmt) => (int) ($stmt->sourceRecord?->shop_id ?? 0));

        $shopBreakdowns = [];
        $totalCashWithShops = 0.0;

        foreach ($shops as $shop) {
            $snapshot = $snapshots->get($shop->id);
            $closingPosition = round((float) ($snapshot?->closing_shop_position ?? 0), 2);

            $pendingStatementsForShop = $pendingCashStatements->get($shop->id, collect());
            $pendingHandoverAmount = round((float) $pendingStatementsForShop->sum('amount'), 2);

            // Cash with shop is the physical unremitted cash collected at the shop
            $cashAmount = max(0.0, $pendingHandoverAmount > 0.0 ? $pendingHandoverAmount : $closingPosition);

            $shopBreakdowns[] = [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'shop_code' => $shop->code,
                'closing_position' => $closingPosition,
                'pending_handover' => $pendingHandoverAmount,
                'cash_with_shop' => $cashAmount,
                'status' => $cashAmount > 0.0 ? 'WITH SHOP' : 'CLEARED',
            ];

            $totalCashWithShops += $cashAmount;
        }

        return [
            'total_cash_with_shops' => round($totalCashWithShops, 2),
            'shops' => $shopBreakdowns,
        ];
    }

    /**
     * Get active floating cheques summary and segregated status.
     *
     * @return array{total_floating: float, cleared_today: float, rejected_total: float, floating_count: int, cheques: Collection}
     */
    public function getFloatingChequesSummary(?string $businessDate = null): array
    {
        $businessDate = $businessDate ?? today()->toDateString();

        $floatingCheques = ShopInvoicePaymentRequest::query()
            ->with(['shop', 'invoice', 'requestedBy'])
            ->where(function ($query): void {
                $query->where('payment_method', 'cheque')
                    ->orWhere('payment_method', 'Cheque');
            })
            ->where('status', '!=', 'rejected')
            ->where(function ($query): void {
                $query->whereNull('cheque_status')
                    ->orWhere('cheque_status', 'pending')
                    ->orWhereIn('reconciliation_status', ['pending', 'floating']);
            })
            ->latest('id')
            ->get();

        $clearedToday = (float) ShopInvoicePaymentRequest::query()
            ->where(function ($query): void {
                $query->where('payment_method', 'cheque')
                    ->orWhere('payment_method', 'Cheque');
            })
            ->where('cheque_status', 'cleared')
            ->whereDate('updated_at', $businessDate)
            ->sum('reconciled_amount');

        $rejectedTotal = (float) ShopInvoicePaymentRequest::query()
            ->where(function ($query): void {
                $query->where('payment_method', 'cheque')
                    ->orWhere('payment_method', 'Cheque');
            })
            ->where(function ($query): void {
                $query->where('status', 'rejected')
                    ->orWhere('cheque_status', 'rejected');
            })
            ->sum('requested_amount');

        $totalFloating = round((float) $floatingCheques->sum(
            fn (ShopInvoicePaymentRequest $req): float => (float) $req->floating_amount > 0 ? (float) $req->floating_amount : (float) ($req->approved_amount ?: $req->requested_amount)
        ), 2);

        return [
            'total_floating' => $totalFloating,
            'cleared_today' => round($clearedToday, 2),
            'rejected_total' => round($rejectedTotal, 2),
            'floating_count' => $floatingCheques->count(),
            'cheques' => $floatingCheques,
        ];
    }

    /**
     * Get operational settlement and money flow breakdown for a specific shop and business date.
     *
     * @return array<string, mixed>
     */
    public function getShopDaySettlementOperationalSummary(int $shopId, string $businessDate): array
    {
        $transactions = ShopLedgerTransaction::query()
            ->with(['entryType', 'shop', 'enteredBy', 'approvedBy'])
            ->where('shop_id', $shopId)
            ->whereDate('business_date', $businessDate)
            ->whereNotIn('status', ['void', 'voided', 'reversed'])
            ->orderBy('id', 'asc')
            ->get();

        $settings = ShopLedgerEntrySetting::query()
            ->with(['companyAccount', 'entryType'])
            ->where('shop_id', $shopId)
            ->where('enabled', true)
            ->get();

        $statements = CompanyAccountStatementEntry::query()
            ->where('source_type', ShopLedgerTransaction::class)
            ->whereIn('source_id', $transactions->pluck('id'))
            ->with(['companyAccount', 'reconciledBy'])
            ->get()
            ->keyBy('source_id');

        $incomeEntryTypeIds = $transactions
            ->filter(fn ($t) => $t->direction === 'income' || ($t->entryType?->category === 'income'))
            ->pluck('entry_type_id')
            ->filter()
            ->unique()
            ->all();

        $bankRules = empty($incomeEntryTypeIds)
            ? collect()
            : ShopBankSettlementAdjustmentRule::query()
                ->where('shop_id', $shopId)
                ->whereIn('entry_type_id', $incomeEntryTypeIds)
                ->where('enabled', true)
                ->get()
                ->groupBy('entry_type_id');

        $dailyBankAdjustments = empty($incomeEntryTypeIds)
            ? collect()
            : ShopBankSettlementAdjustment::query()
                ->where('shop_id', $shopId)
                ->whereDate('business_date', $businessDate)
                ->whereIn('entry_type_id', $incomeEntryTypeIds)
                ->get()
                ->groupBy('entry_type_id');

        $grossSales = 0.0;
        $collections = [];
        $adjustments = [];
        $verifiedReceived = 0.0;
        $pendingVerification = 0.0;
        $cashStillWithShop = 0.0;
        $settlementDeductions = 0.0;

        foreach ($transactions as $tx) {
            $code = (string) ($tx->entryType?->code ?: ($tx->entry_type_code ?? ''));
            $category = (string) ($tx->entryType?->category ?: ($tx->direction ?? ''));
            $isIncome = $tx->direction === 'income' || $category === 'income';
            $isExpense = $tx->direction === 'expense' || $category === 'expense';
            $isSettlement = $code === 'shop_paid_company' || $category === 'settlement';

            if ($isIncome) {
                $grossSales += (float) $tx->amount;
                $setting = $settings->firstWhere('entry_type_id', $tx->entry_type_id);
                $statement = $statements->get($tx->id);

                $destinationName = $statement?->companyAccount?->name
                    ?? $setting?->companyAccount?->name
                    ?? 'Configured Company Account';

                $lowerCode = strtolower($code);
                $isCash = str_contains($lowerCode, 'cash')
                    || $setting?->companyAccount?->account_type === 'cash'
                    || $statement?->companyAccount?->account_type === 'cash';

                $paymentMethod = match (true) {
                    str_contains($lowerCode, 'paytm') => 'Paytm',
                    str_contains($lowerCode, 'card') => 'Card',
                    str_contains($lowerCode, 'upi') => 'UPI',
                    str_contains($lowerCode, 'cash') => 'Cash',
                    default => $tx->entryType?->name ?: ($code ?: 'Collection'),
                };

                $shopName = $tx->shop?->name ?? 'Shop';
                $destinationFormatted = null;
                $locationFormatted = null;

                if ($tx->status !== 'approved') {
                    $status = 'POSTED';
                    $actionType = 'approve';
                    $locationFormatted = '📍 '.$shopName.' Shop';
                } elseif ($statement && $statement->is_finalized && $statement->status === 'reconciled') {
                    $status = 'VERIFIED';
                    $actionType = null;
                    $destinationFormatted = '→ '.($destinationName ?: 'Company Account');
                    $verifiedReceived += (float) $tx->amount;
                } elseif ($isCash) {
                    $status = 'CASH WITH SHOP';
                    $actionType = 'verify_cash_received';
                    $locationFormatted = '📍 '.$shopName.' Shop';
                    $cashStillWithShop += (float) $tx->amount;
                } else {
                    $status = 'NEEDS VERIFICATION';
                    $actionType = 'verify_received';
                    $destinationFormatted = '→ '.($destinationName ?: 'Company Bank');
                    $pendingVerification += (float) $tx->amount;
                }

                $entryTypeId = (int) $tx->entry_type_id;
                $rulesForType = $bankRules->get($entryTypeId, collect());
                $hasRules = $rulesForType->isNotEmpty();
                $dailyAdjsForType = $dailyBankAdjustments->get($entryTypeId, collect())->keyBy('rule_id');

                $resolvedExpected = $this->expectedAmountService->resolve(
                    (int) $tx->shop_id,
                    $businessDate,
                    $entryTypeId,
                    (float) $tx->amount
                );

                $rulesConfigured = $rulesForType->map(function ($r) use ($dailyAdjsForType) {
                    $existingDaily = $dailyAdjsForType->get($r->id);

                    return [
                        'rule_id' => (int) $r->id,
                        'label' => (string) $r->label,
                        'direction' => (string) $r->direction,
                        'amount' => $existingDaily ? (float) $existingDaily->amount : 0.0,
                        'notes' => $existingDaily ? (string) $existingDaily->notes : null,
                    ];
                })->values()->all();

                $collections[] = [
                    'id' => $tx->id,
                    'entry_type_id' => $entryTypeId,
                    'category_name' => $tx->entryType?->name ?: $code,
                    'code' => $code,
                    'payment_method' => $paymentMethod,
                    'amount' => (float) $tx->amount,
                    'status' => $status,
                    'is_cash' => $isCash,
                    'destination_account' => $destinationName,
                    'destination_name' => $destinationFormatted,
                    'location_name' => $locationFormatted,
                    'statement_ref' => $statement?->reference,
                    'statement_uuid' => $statement?->public_uuid,
                    'action_type' => $actionType,
                    'entered_by' => $tx->enteredBy?->name,
                    'approved_by' => $tx->approvedBy?->name,
                    'verified_by' => $statement?->reconciledBy?->name,
                    'verified_at' => $statement?->reconciled_at?->format('d M Y H:i'),
                    'has_bank_adjustment_rules' => $hasRules,
                    'bank_adjustment_rules' => $rulesConfigured,
                    'base_collection_amount' => (float) $resolvedExpected['base_amount'],
                    'expected_bank_amount' => (float) $resolvedExpected['expected_amount'],
                    'plus_adjustments' => (float) $resolvedExpected['plus_adjustments'],
                    'minus_adjustments' => (float) $resolvedExpected['minus_adjustments'],
                    'adjustment_total' => (float) $resolvedExpected['adjustment_total'],
                    'adjustments_detail' => $resolvedExpected['adjustments'],
                ];
            } elseif ($isExpense && ! $isSettlement) {
                $effectOnPayable = 0.0;
                $fundingSource = (string) ($tx->funding_source ?: 'none');

                if ($fundingSource === 'sales' || $tx->settlement_delta < 0) {
                    $effectOnPayable = -(float) $tx->amount;
                    $settlementDeductions += (float) $tx->amount;
                }

                $statusLabel = match ($fundingSource) {
                    'sales' => 'FROM SALES',
                    'company' => 'PAID BY COMPANY',
                    'petty' => 'FROM PETTY',
                    default => ($effectOnPayable < 0 ? 'FROM SALES' : 'NO EFFECT'),
                };

                $adjustments[] = [
                    'id' => $tx->id,
                    'name' => $tx->entryType?->name ?: $code,
                    'amount' => (float) $tx->amount,
                    'funding_source' => $fundingSource,
                    'effect_on_payable' => $effectOnPayable,
                    'status' => $statusLabel,
                ];
            }
        }

        // Net physical cash remaining with shop accounts for cash spent on sales-funded deductions
        $grossCashCollected = 0.0;
        $cashVerified = 0.0;
        foreach ($collections as $c) {
            if ($c['is_cash']) {
                $grossCashCollected += (float) $c['amount'];
                if ($c['status'] === 'VERIFIED') {
                    $cashVerified += (float) $c['amount'];
                }
            }
        }
        $cashStillWithShop = max(0.0, round($grossCashCollected - $settlementDeductions - $cashVerified, 2));

        // Floating cheques for this shop
        $floatingCheques = (float) ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shopId)
            ->where(function ($query): void {
                $query->where('payment_method', 'cheque')
                    ->orWhere('payment_method', 'Cheque');
            })
            ->where('status', '!=', 'rejected')
            ->where(function ($query): void {
                $query->whereNull('cheque_status')
                    ->orWhere('cheque_status', 'pending')
                    ->orWhereIn('reconciliation_status', ['pending', 'floating']);
            })
            ->sum('requested_amount');

        $expectedPayable = max(0.0, round($grossSales - $settlementDeductions, 2));
        $outstandingToSettle = max(0.0, round($expectedPayable - $verifiedReceived, 2));

        return [
            'shop_id' => $shopId,
            'business_date' => $businessDate,
            'gross_sales' => round($grossSales, 2),
            'collections' => $collections,
            'settlement_adjustments' => $adjustments,
            'total_deductions' => round($settlementDeductions, 2),
            'company_receipt_status' => [
                'verified_received' => round($verifiedReceived, 2),
                'pending_verification' => round($pendingVerification, 2),
                'cash_still_with_shop' => round($cashStillWithShop, 2),
                'floating_cheques' => round($floatingCheques, 2),
            ],
            'settlement_summary' => [
                'gross_sales' => round($grossSales, 2),
                'settlement_deductions' => round($settlementDeductions, 2),
                'expected_payable' => $expectedPayable,
                'verified_company_received' => round($verifiedReceived, 2),
                'outstanding_to_settle' => $outstandingToSettle,
            ],
        ];
    }

    /**
     * Get a normalized, unified list of daily money flow items across all shops and channels.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUnifiedMoneyFlowList(string $businessDate, ?int $shopId = null, ?string $statusFilter = null): array
    {
        $txQuery = ShopLedgerTransaction::query()
            ->with(['entryType', 'shop', 'companyAccount'])
            ->whereDate('business_date', $businessDate)
            ->whereNotIn('status', ['void', 'voided', 'reversed'])
            ->where(function ($q): void {
                $q->where('direction', 'income')
                    ->orWhereHas('entryType', fn ($et) => $et->where('category', 'income'));
            });

        if ($shopId) {
            $txQuery->where('shop_id', $shopId);
        }

        $transactions = $txQuery->orderBy('id', 'desc')->get();

        $statements = CompanyAccountStatementEntry::query()
            ->where('source_type', ShopLedgerTransaction::class)
            ->whereIn('source_id', $transactions->pluck('id'))
            ->with('companyAccount')
            ->get()
            ->keyBy('source_id');

        $settingsQuery = ShopLedgerEntrySetting::query()
            ->with('companyAccount')
            ->where('enabled', true);

        if ($shopId) {
            $settingsQuery->where('shop_id', $shopId);
        }

        $settings = $settingsQuery->get();

        $chequeQuery = ShopInvoicePaymentRequest::query()
            ->with('shop')
            ->where(function ($q): void {
                $q->where('payment_method', 'cheque')
                    ->orWhere('payment_method', 'Cheque');
            });

        if ($shopId) {
            $chequeQuery->where('shop_id', $shopId);
        }

        $cheques = $chequeQuery->where(function ($q) use ($businessDate): void {
            $q->whereDate('payment_date', $businessDate)
                ->orWhere(function ($sub): void {
                    $sub->where('status', '!=', 'rejected')
                        ->where(function ($st): void {
                            $st->whereNull('cheque_status')
                                ->orWhere('cheque_status', 'pending')
                                ->orWhereIn('reconciliation_status', ['pending', 'floating']);
                        });
                });
        })->get();

        $items = [];

        foreach ($transactions as $tx) {
            $code = (string) ($tx->entryType?->code ?: ($tx->entry_type_code ?? ''));
            $name = (string) ($tx->entryType?->name ?: $code);
            $lowerCode = strtolower($code);

            $paymentMethod = match (true) {
                str_contains($lowerCode, 'paytm') => 'Paytm',
                str_contains($lowerCode, 'card') => 'Card',
                str_contains($lowerCode, 'upi') => 'UPI',
                str_contains($lowerCode, 'cash') => 'Cash',
                default => $name ?: 'Collection',
            };

            $statement = $statements->get($tx->id);
            $setting = $settings->where('shop_id', $tx->shop_id)->firstWhere('entry_type_id', $tx->entry_type_id);

            $destinationAccountName = $statement?->companyAccount?->name
                ?? $tx->companyAccount?->name
                ?? $setting?->companyAccount?->name;

            $isCash = str_contains($lowerCode, 'cash')
                || $setting?->companyAccount?->account_type === 'cash'
                || $statement?->companyAccount?->account_type === 'cash'
                || $tx->companyAccount?->account_type === 'cash';

            $shopName = $tx->shop?->name ?? ('Shop #'.$tx->shop_id);
            $shopSlug = $tx->shop?->slug ?: ($tx->shop?->shop_id ?: 1);

            $displayStatus = 'POSTED';
            $statusType = 'needs_attention';
            $destinationName = null;
            $locationName = null;

            if ($tx->status !== 'approved') {
                $displayStatus = 'POSTED';
                $statusType = 'needs_attention';
                $locationName = '📍 '.$shopName.' Store';
            } elseif ($statement && $statement->is_finalized && $statement->status === 'reconciled') {
                $displayStatus = 'RECEIVED';
                $statusType = 'verified';
                $destinationName = '→ '.($destinationAccountName ?: 'Company Account');
            } elseif ($isCash) {
                $displayStatus = 'CASH WITH SHOP';
                $statusType = 'cash_with_shop';
                $locationName = '📍 '.$shopName.' Store';
            } else {
                $displayStatus = 'NEEDS VERIFICATION';
                $statusType = 'needs_attention';
                $destinationName = '→ '.($destinationAccountName ?: 'Company Bank');
            }

            $items[] = [
                'id' => 'tx-'.$tx->id,
                'source_type' => 'shop_ledger_transaction',
                'source_id' => $tx->id,
                'shop_id' => $tx->shop_id,
                'shop_name' => $shopName,
                'business_date' => $tx->business_date?->toDateString() ?: $businessDate,
                'payment_method' => $paymentMethod,
                'amount' => (float) $tx->amount,
                'destination_name' => $destinationName,
                'location_name' => $locationName,
                'display_status' => $displayStatus,
                'status_type' => $statusType,
                'detail_url' => route('admin.cashbook.transaction.show', $tx->id),
            ];
        }

        foreach ($cheques as $ch) {
            $shopName = $ch->shop?->name ?? ('Shop #'.$ch->shop_id);
            $amount = (float) ($ch->floating_amount > 0 ? $ch->floating_amount : $ch->requested_amount);

            $isRejected = $ch->status === 'rejected' || $ch->cheque_status === 'rejected';
            $isCleared = $ch->cheque_status === 'cleared' || $ch->reconciliation_status === 'reconciled';

            if ($isRejected) {
                $displayStatus = 'REJECTED';
                $statusType = 'needs_attention';
            } elseif ($isCleared) {
                $displayStatus = 'RECEIVED';
                $statusType = 'verified';
            } else {
                $displayStatus = 'FLOATING';
                $statusType = 'floating';
            }

            $items[] = [
                'id' => 'ch-'.$ch->id,
                'source_type' => 'cheque',
                'source_id' => $ch->id,
                'shop_id' => $ch->shop_id,
                'shop_name' => $shopName,
                'business_date' => $ch->payment_date?->toDateString() ?: $businessDate,
                'payment_method' => 'Cheque',
                'amount' => $amount,
                'destination_name' => null,
                'location_name' => null,
                'display_status' => $displayStatus,
                'status_type' => $statusType,
                'detail_url' => route('admin.cashbook.finance.cheque-submission'),
            ];
        }

        if ($statusFilter && $statusFilter !== 'all') {
            if ($statusFilter === 'pending') {
                $items = array_values(array_filter($items, fn ($item) => $item['status_type'] !== 'verified'));
            } else {
                $items = array_values(array_filter($items, fn ($item) => $item['status_type'] === $statusFilter));
            }
        }

        return $items;
    }

    /**
     * Get monthly aggregated pending transaction counts grouped by date.
     *
     * @return array<string, int>
     */
    public function getMonthlyPendingCounts(string $yearMonth, ?int $shopId = null): array
    {
        $monthCarbon = Carbon::parse($yearMonth.'-01');
        $startDate = $monthCarbon->copy()->startOfMonth()->toDateString();
        $endDate = $monthCarbon->copy()->endOfMonth()->toDateString();

        $txCounts = ShopLedgerTransaction::query()
            ->leftJoin('cashbook_company_account_statement_entries as s', function ($join): void {
                $join->on('s.source_id', '=', 'shop_ledger_transactions.id')
                    ->where('s.source_type', '=', ShopLedgerTransaction::class);
            })
            ->whereBetween('shop_ledger_transactions.business_date', [$startDate, $endDate])
            ->whereNotIn('shop_ledger_transactions.status', ['void', 'voided', 'rejected', 'reversed'])
            ->where(function ($q): void {
                $q->where('shop_ledger_transactions.direction', 'income')
                    ->orWhereHas('entryType', fn ($et) => $et->where('category', 'income'));
            })
            ->where(function ($q): void {
                $q->where('shop_ledger_transactions.status', '!=', 'approved')
                    ->orWhereNull('s.id')
                    ->orWhere('s.is_finalized', false)
                    ->orWhere('s.status', '!=', 'reconciled')
                    ->orWhere('s.duplicate_status', 'possible_duplicate');
            })
            ->when($shopId, fn ($q) => $q->where('shop_ledger_transactions.shop_id', $shopId))
            ->selectRaw('DATE(shop_ledger_transactions.business_date) as b_date, COUNT(DISTINCT shop_ledger_transactions.id) as total')
            ->groupByRaw('DATE(shop_ledger_transactions.business_date)')
            ->pluck('total', 'b_date')
            ->all();

        $chCounts = ShopInvoicePaymentRequest::query()
            ->where(function ($q): void {
                $q->where('payment_method', 'cheque')
                    ->orWhere('payment_method', 'Cheque');
            })
            ->where('status', '!=', 'rejected')
            ->where(function ($q): void {
                $q->whereNull('cheque_status')
                    ->orWhere('cheque_status', 'pending')
                    ->orWhereIn('reconciliation_status', ['pending', 'floating']);
            })
            ->whereBetween(DB::raw('DATE(COALESCE(payment_date, cheque_date, created_at))'), [$startDate, $endDate])
            ->when($shopId, fn ($q) => $q->where('shop_id', $shopId))
            ->selectRaw('DATE(COALESCE(payment_date, cheque_date, created_at)) as b_date, COUNT(*) as total')
            ->groupByRaw('DATE(COALESCE(payment_date, cheque_date, created_at))')
            ->pluck('total', 'b_date')
            ->all();

        $combined = [];
        $period = CarbonPeriod::create($startDate, $endDate);
        foreach ($period as $dt) {
            $dStr = $dt->toDateString();
            $tCount = (int) ($txCounts[$dStr] ?? 0);
            $cCount = (int) ($chCounts[$dStr] ?? 0);
            $combined[$dStr] = $tCount + $cCount;
        }

        return $combined;
    }

    /**
     * Build structured monthly calendar data for Money Flow.
     *
     * @return array{
     *     calendar_month: string,
     *     month_title: string,
     *     prev_month: string,
     *     next_month: string,
     *     weeks: array<int, array<int, array<string, mixed>|null>>,
     *     selected_date: string,
     *     today: string
     * }
     */
    public function getMonthlyCalendarData(string $selectedBusinessDate, ?string $calendarMonth = null, ?int $shopId = null): array
    {
        $calendarMonth = $calendarMonth ?: Carbon::parse($selectedBusinessDate)->format('Y-m');
        $monthCarbon = Carbon::parse($calendarMonth.'-01');
        $prevMonth = $monthCarbon->copy()->subMonth()->format('Y-m');
        $nextMonth = $monthCarbon->copy()->addMonth()->format('Y-m');
        $todayStr = today()->toDateString();

        $pendingCounts = $this->getMonthlyPendingCounts($calendarMonth, $shopId);

        $startOfMonth = $monthCarbon->copy()->startOfMonth();
        $endOfMonth = $monthCarbon->copy()->endOfMonth();

        // Week starts on Monday: Monday is 1, Sunday is 7
        $firstDayOfWeek = (int) $startOfMonth->dayOfWeekIso;
        $leadingPadding = $firstDayOfWeek - 1;

        $days = [];
        for ($i = 0; $i < $leadingPadding; $i++) {
            $days[] = null;
        }

        for ($day = 1; $day <= $endOfMonth->day; $day++) {
            $currentDateStr = $monthCarbon->copy()->day($day)->toDateString();
            $days[] = [
                'date' => $currentDateStr,
                'day' => $day,
                'is_today' => $currentDateStr === $todayStr,
                'is_selected' => $currentDateStr === $selectedBusinessDate,
                'pending_count' => (int) ($pendingCounts[$currentDateStr] ?? 0),
            ];
        }

        while (count($days) % 7 !== 0) {
            $days[] = null;
        }

        $weeks = array_chunk($days, 7);

        return [
            'calendar_month' => $calendarMonth,
            'month_title' => $monthCarbon->format('F Y'),
            'prev_month' => $prevMonth,
            'next_month' => $nextMonth,
            'weeks' => $weeks,
            'selected_date' => $selectedBusinessDate,
            'today' => $todayStr,
        ];
    }
}
