<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Enums\Cashbook\TransactionStatus;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\CompanyExpenseLedgerAllocation;
use App\Models\Cashbook\ShopBankSettlementAdjustment;
use App\Models\Cashbook\ShopBankSettlementAdjustmentRule;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
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
            ->where(function ($query) use ($businessDate): void {
                $query->whereDate('updated_at', $businessDate)
                    ->orWhereDate('payment_date', $businessDate);
            })
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
            ->whereNotIn('status', ['void', 'voided'])
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
        $settlementAdditions = 0.0;

        $reversals = ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->where('reference_type', ShopLedgerTransaction::class)
            ->whereNotNull('reference_id')
            ->whereNotIn('status', ['void', 'voided'])
            ->get()
            ->keyBy('reference_id');

        foreach ($transactions as $tx) {
            $code = (string) ($tx->entryType?->code ?: ($tx->entry_type_code ?? ''));
            $category = (string) ($tx->entryType?->category ?: ($tx->direction ?? ''));
            $isIncome = $tx->direction === 'income' || $category === 'income';
            $isExpense = $tx->direction === 'expense' || $category === 'expense';
            $isSettlement = $code === 'shop_paid_company' || $category === 'settlement';
            $isAdjustmentIncome = $isIncome && ($code === 'other_income' || $tx->reference_type === ShopLedgerTransaction::class);

            if ($isIncome && ! $isAdjustmentIncome) {
                $setting = $settings->firstWhere('entry_type_id', $tx->entry_type_id);
                $isCpContra = $code === 'income_cp' || ($setting && ! $setting->include_in_sales && $setting->generates_secondary_entry);
                if (! $isCpContra) {
                    $grossSales += (float) $tx->amount;
                }
                $statement = $statements->get($tx->id);

                $configuredAccount = ($setting && $setting->enabled && $setting->company_account_id && $setting->companyAccount?->enabled)
                    ? $setting->companyAccount
                    : null;

                $hasAccountMapping = $configuredAccount !== null;
                $destinationAccountId = $configuredAccount?->id;
                $destinationAccountName = $configuredAccount?->name;

                $statementAccountId = $statement?->company_account_id;
                $statementAccountName = $statement?->companyAccount?->name;

                $hasAccountMismatch = false;
                $accountMappingStatus = 'unconfigured';
                $verificationBlockReason = null;

                if ($statement) {
                    if ($hasAccountMapping && (int) $statementAccountId !== (int) $configuredAccount->id) {
                        $hasAccountMismatch = true;
                        $accountMappingStatus = 'mismatched';
                        $verificationBlockReason = 'Account mismatch — review required';
                    } elseif (! $hasAccountMapping) {
                        $hasAccountMismatch = true;
                        $accountMappingStatus = 'unconfigured';
                        $verificationBlockReason = 'Destination account not configured';
                    } else {
                        $accountMappingStatus = 'configured';
                    }
                } else {
                    if ($hasAccountMapping) {
                        $accountMappingStatus = 'configured';
                    } else {
                        $accountMappingStatus = 'unconfigured';
                        $verificationBlockReason = 'Destination account not configured';
                    }
                }

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

                $isApproved = in_array($tx->status, [TransactionStatus::Approved->value, 'approved'], true);
                $isReconciled = (bool) ($statement && $statement->is_finalized && $statement->status === 'reconciled');

                if (! $isApproved) {
                    $status = 'POSTED';
                    $actionType = 'approve';
                    $locationFormatted = '📍 '.$shopName.' Shop';
                } elseif ($isReconciled) {
                    $status = 'VERIFIED';
                    $actionType = null;
                    $destinationFormatted = '→ '.($destinationAccountName ?? $statementAccountName ?? 'Company Account');
                    $verifiedReceived += (float) $tx->amount;
                } elseif ($isCash) {
                    $status = 'CASH WITH SHOP';
                    $actionType = 'verify_cash_received';
                    $locationFormatted = '📍 '.$shopName.' Shop';
                    $cashStillWithShop += (float) $tx->amount;
                } else {
                    $status = 'NEEDS VERIFICATION';
                    $actionType = 'verify_received';
                    $destinationFormatted = $destinationAccountName ? '→ '.$destinationAccountName : null;
                    $pendingVerification += (float) $tx->amount;
                }

                $canVerify = $isApproved && ! $isReconciled && $hasAccountMapping && ! $hasAccountMismatch;

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
                    'destination_account' => $destinationAccountName,
                    'destination_account_id' => $destinationAccountId,
                    'destination_account_name' => $destinationAccountName,
                    'has_account_mapping' => $hasAccountMapping,
                    'account_mapping_status' => $accountMappingStatus,
                    'statement_account_id' => $statementAccountId,
                    'statement_account_name' => $statementAccountName,
                    'has_account_mismatch' => $hasAccountMismatch,
                    'verification_block_reason' => $verificationBlockReason,
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
                    'tx_status' => (string) $tx->status,
                    'can_accept' => in_array($tx->status, [TransactionStatus::Posted->value, TransactionStatus::Submitted->value], true),
                    'can_verify' => $canVerify,
                    'is_received' => $isReconciled,
                ];
            } elseif ($isExpense && ! $isSettlement) {
                $effectOnPayable = 0.0;
                $fundingSource = (string) ($tx->funding_source ?: 'none');

                if ($fundingSource === 'sales' || $tx->settlement_delta < 0) {
                    $effectOnPayable = -(float) $tx->amount;
                    if (! in_array($tx->status, ['void', 'voided'], true)) {
                        $settlementDeductions += (float) $tx->amount;
                    }
                }

                $statusLabel = match ($fundingSource) {
                    'sales' => 'FROM SALES',
                    'company' => 'PAID BY COMPANY',
                    'petty' => 'FROM PETTY',
                    default => ($effectOnPayable < 0 ? 'FROM SALES' : 'NO EFFECT'),
                };

                $isReversal = $tx->reference_type === ShopLedgerTransaction::class && ! empty($tx->reference_id);
                $isReversed = $reversals->has($tx->id) || $tx->status === 'reversed';
                $reversalId = $reversals->get($tx->id)?->id;

                $adjustments[] = [
                    'id' => $tx->id,
                    'time' => $tx->created_at?->format('H:i') ?: '—',
                    'type' => 'Shop Expense',
                    'name' => $tx->entryType?->name ?: $code,
                    'note' => $tx->notes ?: '—',
                    'amount' => (float) $tx->amount,
                    'funding_source' => $fundingSource,
                    'effect_on_payable' => $effectOnPayable,
                    'admin' => $tx->enteredBy?->name ?: 'Admin',
                    'status' => $isReversed ? 'REVERSED' : (string) $tx->status,
                    'status_label' => $statusLabel,
                    'is_reversal' => $isReversal,
                    'original_id' => $isReversal ? (int) $tx->reference_id : null,
                    'is_reversed' => $isReversed,
                    'reversal_id' => $reversalId ? (int) $reversalId : null,
                    'can_reverse' => ! $isReversal && ! $isReversed && ! in_array($tx->status, ['void', 'voided'], true),
                ];
            } elseif ($isAdjustmentIncome) {
                $effectOnPayable = (float) $tx->amount;
                if (! in_array($tx->status, ['void', 'voided'], true)) {
                    $settlementAdditions += (float) $tx->amount;
                }

                $isReversal = $tx->reference_type === ShopLedgerTransaction::class && ! empty($tx->reference_id);
                $isReversed = $reversals->has($tx->id) || $tx->status === 'reversed';
                $reversalId = $reversals->get($tx->id)?->id;

                $adjustments[] = [
                    'id' => $tx->id,
                    'time' => $tx->created_at?->format('H:i') ?: '—',
                    'type' => 'Shop Income',
                    'name' => $tx->entryType?->name ?: $code,
                    'note' => $tx->notes ?: '—',
                    'amount' => (float) $tx->amount,
                    'funding_source' => (string) ($tx->funding_source ?: 'none'),
                    'effect_on_payable' => $effectOnPayable,
                    'admin' => $tx->enteredBy?->name ?: 'Admin',
                    'status' => $isReversed ? 'REVERSED' : (string) $tx->status,
                    'status_label' => 'SHOP INCOME',
                    'is_reversal' => $isReversal,
                    'original_id' => $isReversal ? (int) $tx->reference_id : null,
                    'is_reversed' => $isReversed,
                    'reversal_id' => $reversalId ? (int) $reversalId : null,
                    'can_reverse' => ! $isReversal && ! $isReversed && ! in_array($tx->status, ['void', 'voided'], true),
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

        // Reconciled shop payment allocations applied to this business date
        $reconciledAllocationsForDay = (float) ShopPaymentLedgerAllocation::query()
            ->where('shop_id', $shopId)
            ->whereHas('paymentRequest', fn ($q) => $q->where('reconciliation_status', 'reconciled')->whereNotIn('status', ['rejected', 'cancelled']))
            ->whereHas('ledgerTransaction', fn ($q) => $q->whereDate('business_date', $businessDate))
            ->sum('amount');

        if ($reconciledAllocationsForDay > 0) {
            $verifiedReceived = max($verifiedReceived, $reconciledAllocationsForDay);
        }

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

        $expectedPayable = max(0.0, round($grossSales + $settlementAdditions - $settlementDeductions, 2));
        $outstandingToSettle = max(0.0, round($expectedPayable - $verifiedReceived, 2));

        // Canonical Bidirectional Settlement Obligations
        $shop = Shop::find($shopId);
        $shopName = $shop?->name ?? 'Shop';

        $prevSnapshot = ShopDailyLedgerSnapshot::query()
            ->where('shop_id', $shopId)
            ->where('business_date', '<', $businessDate)
            ->orderByDesc('business_date')
            ->first();

        $openingShopOutstanding = max(0.0, (float) ($prevSnapshot?->closing_shop_position ?? 0.0));
        $openingCompanyOutstanding = max(0.0, (float) ($prevSnapshot?->closing_company_pending ?? 0.0));

        $shopObligationGross = round($grossSales + $settlementAdditions, 2);
        $shopSalesDeductions = round($settlementDeductions, 2);
        $shopToPettyTransfers = (float) $transactions
            ->filter(fn ($t) => ! in_array($t->status, ['void', 'voided'], true) && (string) $t->funding_source === 'sales' && (float) $t->petty_delta > 0)
            ->sum('amount');
        $shopPaymentsVerified = round($verifiedReceived, 2);

        $dayShopObligations = max(0.0, round($shopObligationGross - $shopSalesDeductions - $shopToPettyTransfers, 2));
        $dayShopPayments = $shopPaymentsVerified;

        $companyExpensesFundedByShop = (float) $transactions
            ->filter(fn ($t) => ! in_array($t->status, ['void', 'voided'], true) && ((string) $t->funding_source === 'company_later' || (float) $t->company_pending_delta > 0))
            ->sum('amount');
        $companyObligationGross = round($companyExpensesFundedByShop, 2);
        $dayCompanyObligations = $companyObligationGross;
        $dayCompanyPayments = (float) CompanyExpenseLedgerAllocation::query()
            ->where('shop_id', $shopId)
            ->where('status', 'active')
            ->whereDate('allocation_date', $businessDate)
            ->sum('allocated_amount');

        $totalCompanyPaymentsVerified = (float) CompanyExpenseLedgerAllocation::query()
            ->where('shop_id', $shopId)
            ->where('status', 'active')
            ->whereDate('allocation_date', '<=', $businessDate)
            ->sum('allocated_amount');

        $closingShopOutstanding = max(0.0, round($openingShopOutstanding + $dayShopObligations - $dayShopPayments, 2));
        $closingCompanyOutstanding = max(0.0, round($openingCompanyOutstanding + $dayCompanyObligations - $dayCompanyPayments, 2));

        $closingNetDiff = round($closingShopOutstanding - $closingCompanyOutstanding, 2);
        if ($closingNetDiff > 0) {
            $closingNetAmount = $closingNetDiff;
            $closingNetDirection = 'shop_owes_company';
            $creditorName = 'Green Leaf';
            $debtorName = $shopName;
            $displayStatement = "{$shopName} OWES GREEN LEAF ₹".number_format($closingNetAmount, 2);
        } elseif ($closingNetDiff < 0) {
            $closingNetAmount = abs($closingNetDiff);
            $closingNetDirection = 'company_owes_shop';
            $creditorName = $shopName;
            $debtorName = 'Green Leaf';
            $displayStatement = "GREEN LEAF OWES {$shopName} ₹".number_format($closingNetAmount, 2);
        } else {
            $closingNetAmount = 0.0;
            $closingNetDirection = 'settled';
            $creditorName = null;
            $debtorName = null;
            $displayStatement = "GREEN LEAF AND {$shopName} ARE FULLY SETTLED";
        }

        // Petty Cash Analysis
        $openingPetty = (float) ($prevSnapshot?->closing_petty ?? 0.0);
        $companyPettyReceived = (float) $transactions
            ->filter(fn ($t) => ! in_array($t->status, ['void', 'voided'], true) && (string) $t->funding_source === 'company' && (float) $t->petty_delta > 0)
            ->sum('amount');
        $salesTransferredToPetty = $shopToPettyTransfers;
        $pettyExpenses = (float) $transactions
            ->filter(fn ($t) => ! in_array($t->status, ['void', 'voided'], true) && ((string) $t->funding_source === 'petty' || (float) $t->petty_delta < 0))
            ->sum('amount');
        $closingPetty = round($openingPetty + $companyPettyReceived + $salesTransferredToPetty - $pettyExpenses, 2);
        $shopFundedPettyShortfall = $closingPetty < 0 ? abs($closingPetty) : 0.0;

        return [
            'shop_id' => $shopId,
            'business_date' => $businessDate,
            'as_of_date' => $businessDate,
            'gross_sales' => round($grossSales, 2),
            'collections' => $collections,
            'settlement_adjustments' => $adjustments,
            'total_deductions' => round($settlementDeductions, 2),
            'total_additions' => round($settlementAdditions, 2),
            'company_receipt_status' => [
                'verified_received' => round($verifiedReceived, 2),
                'pending_verification' => round($pendingVerification, 2),
                'cash_still_with_shop' => round($cashStillWithShop, 2),
                'floating_cheques' => round($floatingCheques, 2),
            ],
            'settlement_summary' => [
                'gross_sales' => round($grossSales, 2),
                'settlement_deductions' => round($settlementDeductions, 2),
                'settlement_additions' => round($settlementAdditions, 2),
                'expected_payable' => $expectedPayable,
                'verified_company_received' => round($verifiedReceived, 2),
                'outstanding_to_settle' => $outstandingToSettle,
                'opening_shop_outstanding' => $openingShopOutstanding,
                'opening_company_outstanding' => $openingCompanyOutstanding,
                'day_shop_obligations' => $dayShopObligations,
                'day_shop_payments' => $dayShopPayments,
                'day_company_obligations' => $dayCompanyObligations,
                'day_company_payments' => $dayCompanyPayments,
                'closing_shop_outstanding' => $closingShopOutstanding,
                'closing_company_outstanding' => $closingCompanyOutstanding,
                'closing_net_amount' => $closingNetAmount,
                'closing_net_direction' => $closingNetDirection,
                'shop_obligation_gross' => $shopObligationGross,
                'shop_sales_deductions' => $shopSalesDeductions,
                'shop_to_petty_transfers' => $shopToPettyTransfers,
                'shop_payments_verified' => $shopPaymentsVerified,
                'shop_outstanding' => $closingShopOutstanding,
                'company_obligation_gross' => $companyObligationGross,
                'company_payments_verified' => $totalCompanyPaymentsVerified,
                'company_outstanding' => $closingCompanyOutstanding,
                'net_amount' => $closingNetAmount,
                'net_direction' => $closingNetDirection,
                'creditor_name' => $creditorName,
                'debtor_name' => $debtorName,
                'display_statement' => $displayStatement,
            ],
            'petty_position' => [
                'opening_petty' => $openingPetty,
                'company_petty_received' => $companyPettyReceived,
                'sales_transferred_to_petty' => $salesTransferredToPetty,
                'petty_expenses' => $pettyExpenses,
                'closing_petty' => $closingPetty,
                'approved_shop_funded_petty_shortfall' => $shopFundedPettyShortfall,
                'pending_review_shortfall' => 0.0,
            ],
            // Direct top-level aliases for canonical fields
            'opening_shop_outstanding' => $openingShopOutstanding,
            'opening_company_outstanding' => $openingCompanyOutstanding,
            'day_shop_obligations' => $dayShopObligations,
            'day_shop_payments' => $dayShopPayments,
            'day_company_obligations' => $dayCompanyObligations,
            'day_company_payments' => $dayCompanyPayments,
            'closing_shop_outstanding' => $closingShopOutstanding,
            'closing_company_outstanding' => $closingCompanyOutstanding,
            'closing_net_amount' => $closingNetAmount,
            'closing_net_direction' => $closingNetDirection,
            'shop_obligation_gross' => $shopObligationGross,
            'shop_sales_deductions' => $shopSalesDeductions,
            'shop_to_petty_transfers' => $shopToPettyTransfers,
            'shop_payments_verified' => $shopPaymentsVerified,
            'shop_outstanding' => $closingShopOutstanding,
            'company_obligation_gross' => $companyObligationGross,
            'company_payments_verified' => $totalCompanyPaymentsVerified,
            'company_outstanding' => $closingCompanyOutstanding,
            'net_amount' => $closingNetAmount,
            'net_direction' => $closingNetDirection,
            'creditor_name' => $creditorName,
            'debtor_name' => $debtorName,
            'display_statement' => $displayStatement,
        ];
    }

    /**
     * Get monthly breakdown of daily settlement summaries for a specific shop.
     *
     * @return array<string, mixed>
     */
    public function getShopMonthlyDailySummaries(int $shopId, string $yearMonth): array
    {
        $monthCarbon = Carbon::parse($yearMonth.'-01');
        $monthStart = $monthCarbon->copy()->startOfMonth()->toDateString();
        $monthEnd = $monthCarbon->copy()->endOfMonth()->toDateString();
        $prevMonth = $monthCarbon->copy()->subMonth()->format('Y-m');
        $nextMonth = $monthCarbon->copy()->addMonth()->format('Y-m');
        $monthTitle = $monthCarbon->format('F Y');

        $transactions = ShopLedgerTransaction::query()
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$monthStart, $monthEnd])
            ->whereNotIn('status', ['void', 'voided', 'reversed'])
            ->orderBy('business_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $statements = $transactions->isEmpty()
            ? collect()
            : CompanyAccountStatementEntry::query()
                ->where('source_type', ShopLedgerTransaction::class)
                ->whereIn('source_id', $transactions->pluck('id'))
                ->get()
                ->keyBy('source_id');

        $reconciledAllocationsByDate = ShopPaymentLedgerAllocation::query()
            ->where('shop_id', $shopId)
            ->whereHas('paymentRequest', fn ($q) => $q->where('reconciliation_status', 'reconciled')->whereNotIn('status', ['rejected', 'cancelled']))
            ->whereHas('ledgerTransaction', fn ($q) => $q->whereBetween('business_date', [$monthStart, $monthEnd]))
            ->with('ledgerTransaction')
            ->get()
            ->groupBy(fn ($alloc) => $alloc->ledgerTransaction->business_date->toDateString())
            ->map(fn ($group) => (float) $group->sum('amount'));

        $monthReconciledPayments = (float) ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shopId)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->where('reconciliation_status', 'reconciled')
            ->where(function ($q) use ($monthStart, $monthEnd): void {
                $q->whereBetween('payment_date', [$monthStart, $monthEnd])
                    ->orWhere(function ($q2) use ($monthStart, $monthEnd): void {
                        $q2->whereNull('payment_date')
                            ->whereBetween('created_at', [$monthStart.' 00:00:00', $monthEnd.' 23:59:59']);
                    });
            })
            ->sum('requested_amount');

        $txByDate = $transactions->groupBy(fn ($tx) => $tx->business_date->toDateString());

        $entrySettings = ShopLedgerEntrySetting::query()
            ->where('shop_id', $shopId)
            ->where('enabled', true)
            ->get()
            ->keyBy('entry_type_id');

        $dailyRows = [];
        $monthTotalCollections = 0.0;
        $monthCompanyPayable = 0.0;
        $monthCompanyReceived = 0.0;
        $monthPendingAcceptance = 0.0;
        $monthPendingVerification = 0.0;
        $monthNonPayableAudit = 0.0;
        $monthPendingCount = 0;

        $dates = $txByDate->keys()->sortDesc();

        foreach ($dates as $dateStr) {
            $dayTxs = $txByDate->get($dateStr, collect());
            $carbonDate = Carbon::parse($dateStr);

            $dayCollections = 0.0;
            $dayCompanyPayable = 0.0;
            $dayNonPayableAudit = 0.0;
            $dayReceived = 0.0;
            $dayPendingAcceptance = 0.0;
            $dayPendingVerification = 0.0;
            $dayDeductions = 0.0;
            $dayPendingAcceptanceCount = 0;
            $dayPendingVerificationCount = 0;
            $hasAttention = false;

            foreach ($dayTxs as $tx) {
                $amount = (float) $tx->amount;
                $stmt = $statements->get($tx->id);
                $setting = $entrySettings->get($tx->entry_type_id);

                if ($stmt && $stmt->duplicate_status === 'possible_duplicate') {
                    $hasAttention = true;
                }

                $isIncome = $tx->direction === 'income' || $tx->affects_income || $tx->affects_sales;
                $isExpense = $tx->direction === 'expense' || $tx->affects_expense;

                if ($isIncome) {
                    $dayCollections += $amount;

                    $isPayable = $setting ? (bool) $setting->include_in_payable : true;
                    $payableDirection = $setting?->payable_direction ?? 'add';

                    if ($isPayable) {
                        if ($payableDirection === 'subtract') {
                            $dayCompanyPayable -= $amount;
                        } else {
                            $dayCompanyPayable += $amount;
                        }
                    } else {
                        $dayNonPayableAudit += $amount;
                    }

                    if ($tx->status !== 'approved') {
                        $dayPendingAcceptance += $amount;
                        $dayPendingAcceptanceCount++;
                    } elseif ($stmt && $stmt->is_finalized && $stmt->status === 'reconciled') {
                        $dayReceived += $amount;
                    } else {
                        $dayPendingVerification += $amount;
                        $dayPendingVerificationCount++;
                    }
                } elseif ($isExpense && ($tx->funding_source === 'sales' || (float) $tx->settlement_delta < 0) && $tx->reference_type !== CompanyAccountStatementEntry::class) {
                    $dayDeductions += $amount;
                }
            }

            $expectedPayable = max(0.0, round($dayCompanyPayable, 2));
            $dayOutstanding = max(0.0, round($expectedPayable - $dayReceived, 2));
            $pendingOpCount = $dayPendingAcceptanceCount + $dayPendingVerificationCount;

            if ($hasAttention) {
                $status = 'Needs Attention';
                $statusKey = 'needs_attention';
            } elseif ($dayPendingAcceptanceCount > 0) {
                $status = 'Needs Acceptance';
                $statusKey = 'needs_acceptance';
            } elseif ($dayPendingVerificationCount > 0) {
                $status = 'Pending Verification';
                $statusKey = 'pending_verification';
            } else {
                $status = 'Complete';
                $statusKey = 'complete';
            }

            $monthTotalCollections += $dayCollections;
            $monthCompanyPayable += $dayCompanyPayable;
            $monthCompanyReceived += $dayReceived;
            $monthNonPayableAudit += $dayNonPayableAudit;
            $monthPendingAcceptance += $dayPendingAcceptance;
            $monthPendingVerification += $dayPendingVerification;
            $monthPendingCount += $pendingOpCount;

            $dailyRows[] = [
                'business_date' => $dateStr,
                'formatted_date' => $carbonDate->format('d M Y'),
                'day_name' => $carbonDate->format('D'),
                'day_number' => $carbonDate->format('d'),
                'is_today' => $dateStr === today()->toDateString(),
                'total_collection' => round($dayCollections, 2),
                'company_payable' => round($dayCompanyPayable, 2),
                'non_payable_audit' => round($dayNonPayableAudit, 2),
                'company_received' => round($dayReceived, 2),
                'pending_acceptance' => round($dayPendingAcceptance, 2),
                'pending_verification' => round($dayPendingVerification, 2),
                'deductions' => round($dayDeductions, 2),
                'expected_payable' => $expectedPayable,
                'outstanding' => $dayOutstanding,
                'pending_operation_count' => $pendingOpCount,
                'status' => $status,
                'status_key' => $statusKey,
                'entries_count' => $dayTxs->count(),
            ];
        }

        $monthCompanyReceived = $monthCompanyPayable > 0
            ? min($monthCompanyPayable, max($monthCompanyReceived, $monthReconciledPayments))
            : max($monthCompanyReceived, $monthReconciledPayments);
        $monthStillToReceive = max(0.0, round($monthCompanyPayable - $monthCompanyReceived, 2));

        return [
            'year_month' => $yearMonth,
            'month_title' => $monthTitle,
            'prev_month' => $prevMonth,
            'next_month' => $nextMonth,
            'days' => $dailyRows,
            'summary' => [
                'total_collections' => round($monthTotalCollections, 2),
                'company_payable' => round($monthCompanyPayable, 2),
                'company_received' => round($monthCompanyReceived, 2),
                'still_to_receive' => round($monthStillToReceive, 2),
                'non_payable_audit' => round($monthNonPayableAudit, 2),
                'pending_acceptance' => round($monthPendingAcceptance, 2),
                'pending_verification' => round($monthPendingVerification, 2),
                'outstanding' => round($monthStillToReceive, 2),
                'pending_count' => $monthPendingCount,
                'active_days_count' => count($dailyRows),
            ],
        ];
    }

    /**
     * Get presentation-ready Shop Money Flow summary cards.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getShopMoneyFlowCards(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $shopId = null,
        ?string $statusFilter = null,
        ?string $businessDate = null,
        mixed $shops = null
    ): array {
        if ($startDate === null && $endDate === null) {
            $startDate = $businessDate ?? today()->toDateString();
            $endDate = $startDate;
        } elseif ($startDate !== null && $endDate === null) {
            $endDate = $startDate;
        } elseif ($startDate === null && $endDate !== null) {
            $startDate = $endDate;
        }

        if ($shops instanceof Collection || $shops instanceof \Illuminate\Database\Eloquent\Collection) {
            if ($shopId) {
                $shops = $shops->filter(fn ($s) => (int) ($s->shop_id ?? $s->id) === $shopId)->values();
            }
        } else {
            $shopsQuery = Shop::query()
                ->where('status', 'active')
                ->orderBy('name');

            if ($shopId) {
                $shopsQuery->where('id', $shopId);
            }

            $shops = $shopsQuery->get();
        }

        if ($shops->isEmpty()) {
            return [];
        }

        $targetShopIds = $shops->map(fn ($s) => (int) ($s->shop_id ?? $s->id))->filter()->unique()->values()->all();

        $periodTransactions = ShopLedgerTransaction::query()
            ->whereIn('shop_id', $targetShopIds)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->whereNotIn('status', ['void', 'voided', 'reversed'])
            ->where(function ($q): void {
                $q->where('direction', 'income')
                    ->orWhere('affects_income', true)
                    ->orWhere('affects_sales', true)
                    ->orWhere('funding_source', 'sales')
                    ->orWhere('settlement_delta', '<', 0);
            })
            ->get();

        $incomeTransactions = $periodTransactions->filter(fn ($tx) => $tx->direction === 'income' || $tx->affects_income || $tx->affects_sales);

        $statements = $incomeTransactions->isEmpty()
            ? collect()
            : CompanyAccountStatementEntry::query()
                ->where('source_type', ShopLedgerTransaction::class)
                ->whereIn('source_id', $incomeTransactions->pluck('id'))
                ->get()
                ->keyBy('source_id');

        $asOfDate = max($endDate, today()->toDateString());
        $runningOutstanding = ShopLedgerTransaction::query()
            ->whereIn('shop_id', $targetShopIds)
            ->whereDate('business_date', '<=', $asOfDate)
            ->whereNotIn('status', ['void', 'voided', 'reversed'])
            ->select('shop_id', DB::raw('SUM(settlement_delta) as net_outstanding'))
            ->groupBy('shop_id')
            ->pluck('net_outstanding', 'shop_id');

        $periodDeductions = $periodTransactions
            ->filter(fn ($tx) => ($tx->funding_source === 'sales' || $tx->settlement_delta < 0) && $tx->reference_type !== CompanyAccountStatementEntry::class)
            ->groupBy('shop_id')
            ->map(fn ($group) => (float) $group->sum('amount'));

        $txByShop = $incomeTransactions->groupBy('shop_id');
        $cards = [];

        foreach ($shops as $shop) {
            $shopIdVal = (int) ($shop->shop_id ?? $shop->id);
            $shopTxs = $txByShop->get($shopIdVal, collect());

            $totalCollection = 0.0;
            $companyReceived = 0.0;
            $pendingAcceptance = 0.0;
            $pendingVerification = 0.0;
            $pendingAcceptanceCount = 0;
            $pendingVerificationCount = 0;
            $hasAttentionFlag = false;

            foreach ($shopTxs as $tx) {
                $amount = (float) $tx->amount;
                $totalCollection += $amount;
                $stmt = $statements->get($tx->id);

                if ($stmt && $stmt->duplicate_status === 'possible_duplicate') {
                    $hasAttentionFlag = true;
                }

                if ($tx->status !== 'approved') {
                    $pendingAcceptance += $amount;
                    $pendingAcceptanceCount++;
                } elseif ($stmt && $stmt->is_finalized && $stmt->status === 'reconciled') {
                    $companyReceived += $amount;
                } else {
                    $pendingVerification += $amount;
                    $pendingVerificationCount++;
                }
            }

            $periodDeduction = (float) ($periodDeductions->get($shopIdVal) ?? 0.0);
            $periodExpectedPayable = max(0.0, round($totalCollection - $periodDeduction, 2));
            $periodOutstanding = max(0.0, round($periodExpectedPayable - $companyReceived, 2));

            $cumulativeOutstanding = (float) ($runningOutstanding->get($shopIdVal) ?? $periodOutstanding);
            $currentOutstanding = max(0.0, round($cumulativeOutstanding, 2));

            $pendingOpCount = $pendingAcceptanceCount + $pendingVerificationCount;

            if ($hasAttentionFlag) {
                $status = 'Needs Attention';
                $statusKey = 'needs_attention';
            } elseif ($pendingAcceptanceCount > 0) {
                $status = 'Needs Acceptance';
                $statusKey = 'needs_acceptance';
            } elseif ($pendingVerificationCount > 0) {
                $status = 'Pending Verification';
                $statusKey = 'pending_verification';
            } else {
                $status = 'Complete';
                $statusKey = 'complete';
            }

            $shopSlug = $shop->slug ?: ($shop->code ? strtolower($shop->code) : (string) $shopIdVal);

            $cards[] = [
                'shop_id' => $shopIdVal,
                'shop_name' => (string) $shop->name,
                'shop_code' => (string) ($shop->code ?? ''),
                'shop_slug' => (string) $shopSlug,
                'total_collection' => round($totalCollection, 2),
                'company_received' => round($companyReceived, 2),
                'pending_acceptance' => round($pendingAcceptance, 2),
                'pending_verification' => round($pendingVerification, 2),
                'current_outstanding' => $currentOutstanding,
                'pending_operation_count' => $pendingOpCount,
                'pending_count' => $pendingOpCount,
                'pending_acceptance_count' => $pendingAcceptanceCount,
                'pending_verification_count' => $pendingVerificationCount,
                'status' => $status,
                'status_key' => $statusKey,
                'open_shop_url' => route('admin.cashbook.shop.show', ['shop' => $shopSlug]),
            ];
        }

        if ($statusFilter && $statusFilter !== 'all') {
            if ($statusFilter === 'needs_attention') {
                $cards = array_values(array_filter($cards, fn ($c) => in_array($c['status_key'], ['needs_attention', 'needs_acceptance', 'pending_verification'], true)));
            } elseif ($statusFilter === 'pending') {
                $cards = array_values(array_filter($cards, fn ($c) => $c['status_key'] !== 'complete'));
            } elseif ($statusFilter === 'needs_acceptance') {
                $cards = array_values(array_filter($cards, fn ($c) => $c['status_key'] === 'needs_acceptance'));
            } elseif ($statusFilter === 'pending_verification') {
                $cards = array_values(array_filter($cards, fn ($c) => $c['status_key'] === 'pending_verification'));
            } elseif ($statusFilter === 'complete' || $statusFilter === 'verified') {
                $cards = array_values(array_filter($cards, fn ($c) => $c['status_key'] === 'complete'));
            }
        }

        return $cards;
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
