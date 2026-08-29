<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\ShopInvoicePaymentRequest;
use Illuminate\Support\Collection;

class CompanyMoneyPositionService
{
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
            fn (ShopInvoicePaymentRequest $req): float => (float) ($req->floating_amount ?: $req->approved_amount ?: $req->requested_amount)
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
            ->where('status', '!=', 'void')
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

                $isCash = str_contains(strtolower($code), 'cash')
                    || $setting?->companyAccount?->account_type === 'cash'
                    || $statement?->companyAccount?->account_type === 'cash';

                $status = 'POSTED';
                $actionType = 'approve';

                if ($tx->status !== 'approved') {
                    $status = 'POSTED';
                    $actionType = 'approve';
                } elseif ($statement && $statement->is_finalized && $statement->status === 'reconciled') {
                    $status = 'VERIFIED';
                    $actionType = null;
                    $verifiedReceived += (float) $tx->amount;
                } elseif ($isCash) {
                    $status = 'CASH WITH SHOP';
                    $actionType = 'verify_cash_received';
                    $cashStillWithShop += (float) $tx->amount;
                } else {
                    $status = 'NEEDS VERIFICATION';
                    $actionType = 'verify_received';
                    $pendingVerification += (float) $tx->amount;
                }

                $collections[] = [
                    'id' => $tx->id,
                    'category_name' => $tx->entryType?->name ?: $code,
                    'code' => $code,
                    'amount' => (float) $tx->amount,
                    'status' => $status,
                    'is_cash' => $isCash,
                    'destination_account' => $destinationName,
                    'statement_ref' => $statement?->reference,
                    'statement_uuid' => $statement?->public_uuid,
                    'action_type' => $actionType,
                    'entered_by' => $tx->enteredBy?->name,
                    'approved_by' => $tx->approvedBy?->name,
                    'verified_by' => $statement?->reconciledBy?->name,
                    'verified_at' => $statement?->reconciled_at?->format('d M Y H:i'),
                ];
            } elseif ($isExpense && ! $isSettlement) {
                $effectOnPayable = 0.0;
                $fundingSource = (string) ($tx->funding_source ?: 'none');

                if ($fundingSource === 'sales' || $tx->settlement_delta < 0) {
                    $effectOnPayable = -(float) $tx->amount;
                    $settlementDeductions += (float) $tx->amount;
                }

                $adjustments[] = [
                    'id' => $tx->id,
                    'name' => $tx->entryType?->name ?: $code,
                    'amount' => (float) $tx->amount,
                    'funding_source' => $fundingSource,
                    'effect_on_payable' => $effectOnPayable,
                    'status' => $fundingSource === 'sales' ? 'FROM SALES' : 'APPLIED',
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
}
