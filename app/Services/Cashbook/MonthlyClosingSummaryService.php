<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\Cashbook\CompanyPaymentReconciliation;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopPaymentLedgerAllocation;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class MonthlyClosingSummaryService
{
    public function __construct(
        private readonly ShopFinancialReportService $financialReportService,
        private readonly ShopSettlementService $settlementService,
        private readonly CashbookShopSyncService $shopSyncService,
        private readonly ShopPaymentLedgerReconciliationService $reconciliationService,
        private readonly ShopAccountingOpeningService $openingService = new ShopAccountingOpeningService,
    ) {}

    /**
     * Get all active client shop ledger profiles only.
     * Excludes internal, warehouse, test, direct-buyer, and inactive shops.
     *
     * @return Collection<int, ShopLedgerProfile>
     */
    public function getActiveClientProfiles(): Collection
    {
        $profiles = $this->shopSyncService->syncAndGetProfiles();
        $profiles->load('shop.client', 'client', 'preset');

        return $profiles->filter(fn (ShopLedgerProfile $p): bool => $this->isClientShopEligible($p))->values();
    }

    /**
     * Check if a shop or profile is an active client shop according to authoritative ERP classification.
     */
    public function isClientShopEligible(ShopLedgerProfile|Shop|int $shopOrProfile): bool
    {
        $shop = null;
        $profile = null;

        if ($shopOrProfile instanceof ShopLedgerProfile) {
            $profile = $shopOrProfile;
            $shop = $profile->shop;
        } elseif ($shopOrProfile instanceof Shop) {
            $shop = $shopOrProfile;
            $profile = ShopLedgerProfile::query()->where('shop_id', $shop->id)->first();
        } elseif (is_numeric($shopOrProfile)) {
            $shop = Shop::query()->find((int) $shopOrProfile);
            $profile = ShopLedgerProfile::query()->where('shop_id', (int) $shopOrProfile)->first();
        }

        if (! $shop instanceof Shop) {
            return false;
        }

        // Must be active
        if ($shop->status !== 'active') {
            return false;
        }

        // Must have accounting enabled and be a client shop (client_id is not null)
        if (! $shop->accounting_enabled || ! $shop->isClientShop()) {
            return false;
        }

        // If client relation exists, client must also be active
        if ($shop->client && $shop->client->status !== 'active') {
            return false;
        }

        // If ledger profile exists, it must be enabled
        if ($profile && ! $profile->enabled) {
            return false;
        }

        return true;
    }

    /**
     * Get the consolidated All Shops Monthly Closing Summary matrix for a given month.
     * Zero DB mutations.
     *
     * @return array{
     *     month: string,
     *     formatted_month: string,
     *     shops: array<int, array<string, mixed>>,
     *     grand_totals: array<string, mixed>
     * }
     */
    public function getAllShopsSummary(string $month): array
    {
        $monthCarbon = Carbon::createFromFormat('Y-m', $month);
        $profiles = $this->getActiveClientProfiles();

        $rows = [];
        $totals = [
            'opening_physical_net' => 0.0,
            'settlement_due' => 0.0,
            'received' => 0.0,
            'allocated' => 0.0,
            'allocation_pending' => 0.0,
            'pending_verification' => 0.0,
            'projected_allocated' => 0.0,
            'closing_physical_net' => 0.0,
            'opening_available_credit' => 0.0,
            'closing_available_credit' => 0.0,
        ];

        foreach ($profiles as $profile) {
            $shopId = (int) $profile->shop_id;
            $detail = $this->getShopMonthlyDetail($profile, $month);

            $openingPhys = (float) ($detail['opening']['physical_position'] ?? 0.0);
            $openingDir = (string) ($detail['opening']['direction'] ?? 'settled');
            $openingNet = $openingDir === 'company_owes_shop' ? -$openingPhys : $openingPhys;

            $closingPhys = (float) ($detail['closing']['physical_position'] ?? 0.0);
            $closingDir = (string) ($detail['closing']['direction'] ?? 'settled');
            $closingNet = $closingDir === 'company_owes_shop' ? -$closingPhys : $closingPhys;

            $rows[] = [
                'shop_id' => $shopId,
                'slug' => $profile->slug ?: $shopId,
                'name' => $profile->name ?: ($profile->shop?->name ?: 'Shop #'.$shopId),
                'code' => $profile->code ?: ($profile->shop?->code ?: 'SHP-'.$shopId),
                'client_name' => $profile->client?->name,
                'is_direct' => $profile->client_id === null && $profile->profile_template === 'direct_buyer',
                'opening' => $detail['opening'],
                'opening_balance' => $openingPhys,
                'opening_direction' => $openingDir,
                'opening_direction_label' => (string) ($detail['opening']['direction_label'] ?? 'Settled'),
                'activity' => $detail['activity'],
                'current_position' => $detail['current_position'],
                'projected_position' => $detail['projected_position'],
                'closing' => $detail['closing'],
                'credit' => $detail['credit'],
                'status' => $detail['status'],
            ];

            $totals['opening_physical_net'] += $openingNet;
            $totals['settlement_due'] += (float) ($detail['activity']['settlement_due'] ?? 0.0);
            $totals['received'] += (float) ($detail['activity']['company_received'] ?? 0.0);
            $totals['allocated'] += (float) ($detail['current_position']['already_allocated'] ?? 0.0);
            $totals['allocation_pending'] += (float) ($detail['current_position']['allocation_pending'] ?? 0.0);
            $totals['pending_verification'] += (float) ($detail['current_position']['pending_verification'] ?? 0.0);
            $totals['projected_allocated'] += (float) ($detail['projected_position']['projected_total_allocated'] ?? 0.0);
            $totals['closing_physical_net'] += $closingNet;
            $totals['opening_available_credit'] += (float) ($detail['credit']['opening_available_credit'] ?? 0.0);
            $totals['closing_available_credit'] += (float) ($detail['credit']['closing_available_credit'] ?? 0.0);
        }

        return [
            'month' => $month,
            'formatted_month' => $monthCarbon->format('F Y'),
            'shops' => $rows,
            'grand_totals' => $totals,
        ];
    }

    /**
     * Get complete read-only monthly closing summary report and drilldowns for a single shop.
     * Zero DB mutations.
     *
     * @return array<string, mixed>
     */
    public function getShopMonthlyDetail(ShopLedgerProfile|Shop|int $shopOrProfile, string $month): array
    {
        $profile = $shopOrProfile instanceof ShopLedgerProfile
            ? $shopOrProfile
            : ($shopOrProfile instanceof Shop
                ? ShopLedgerProfile::query()->where('shop_id', (int) $shopOrProfile->id)->first()
                : ShopLedgerProfile::query()->where('shop_id', (int) $shopOrProfile)->first());

        $shopId = $profile ? (int) $profile->shop_id : (is_numeric($shopOrProfile) ? (int) $shopOrProfile : (int) $shopOrProfile->id);

        $monthCarbon = Carbon::createFromFormat('Y-m', $month);
        $startDate = $monthCarbon->copy()->startOfMonth()->toDateString();
        $endDate = $monthCarbon->copy()->endOfMonth()->toDateString();
        $prevMonthEnd = $monthCarbon->copy()->subMonth()->endOfMonth()->toDateString();
        $nextMonthStr = $monthCarbon->copy()->addMonth()->format('Y-m');
        $nextMonthLabel = $monthCarbon->copy()->addMonth()->format('F Y');

        // ── 1. Read-Only Physical Position from Snapshots (No Recalculation!) ──
        $accountingStartDate = $this->openingService->getAccountingStartDate($shopId);
        $openingRecord = $this->openingService->getOpeningForDate($shopId, $startDate);
        $isPreOpening = $this->openingService->isPreOpeningMonth($shopId, $month);

        if (! $isPreOpening && $accountingStartDate !== null && $startDate === $accountingStartDate) {
            $openingPhysicalPosRaw = $openingRecord ? $openingRecord->getSignedShopCompanyBalance() : 0.0;
        } elseif (! $isPreOpening && $accountingStartDate !== null) {
            $openingSnapshot = ShopDailyLedgerSnapshot::query()
                ->where('shop_id', $shopId)
                ->where('business_date', '>=', $accountingStartDate)
                ->where('business_date', '<=', $prevMonthEnd)
                ->orderByDesc('business_date')
                ->orderByDesc('id')
                ->first();

            $openingPhysicalPosRaw = (float) ($openingSnapshot?->closing_shop_position ?? 0.0);
        } else {
            $openingSnapshot = ShopDailyLedgerSnapshot::query()
                ->where('shop_id', $shopId)
                ->where('business_date', '<=', $prevMonthEnd)
                ->orderByDesc('business_date')
                ->orderByDesc('id')
                ->first();

            $openingPhysicalPosRaw = (float) ($openingSnapshot?->closing_shop_position ?? 0.0);
        }

        $openingPhysicalPos = round(abs($openingPhysicalPosRaw), 2);
        $openingDirection = match (true) {
            $openingPhysicalPosRaw > 0.0001 => 'shop_owes_company',
            $openingPhysicalPosRaw < -0.0001 => 'company_owes_shop',
            default => 'settled',
        };
        $openingDirectionLabel = match ($openingDirection) {
            'shop_owes_company' => 'Shop → Company',
            'company_owes_shop' => 'Company → Shop',
            default => 'Settled',
        };

        $closingSnapshot = ShopDailyLedgerSnapshot::query()
            ->where('shop_id', $shopId)
            ->when(! $isPreOpening && $accountingStartDate !== null, fn (Builder $q) => $q->where('business_date', '>=', $accountingStartDate))
            ->where('business_date', '<=', $endDate)
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->first();

        $closingPhysicalPosRaw = (float) ($closingSnapshot?->closing_shop_position ?? $openingPhysicalPosRaw);
        $closingPhysicalPos = round(abs($closingPhysicalPosRaw), 2);
        $closingDirection = match (true) {
            $closingPhysicalPosRaw > 0.0001 => 'shop_owes_company',
            $closingPhysicalPosRaw < -0.0001 => 'company_owes_shop',
            default => 'settled',
        };
        $closingDirectionLabel = match ($closingDirection) {
            'shop_owes_company' => 'Shop → Company',
            'company_owes_shop' => 'Company → Shop',
            default => 'Settled',
        };

        $glBills = round((float) ShopInvoice::query()
            ->where('shop_id', $shopId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('business_date', [$startDate, $endDate])
            ->sum('final_total'), 2);

        $payableCalculation = $this->settlementService->calculateCompanyPayable($shopId, $startDate, $endDate);
        $settlementDue = round((float) ($payableCalculation['formula_net'] ?? 0.0), 2);
        if ($settlementDue === 0.0 && $glBills > 0.0) {
            $settlementDue = $glBills;
        }

        $paymentRequests = ShopInvoicePaymentRequest::query()
            ->with(['reconciliations.companyAccount', 'reconciliations.statementEntry', 'ledgerAllocations'])
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

        $reconciledStatementIds = CompanyPaymentReconciliation::query()
            ->whereNotNull('payment_request_id')
            ->pluck('statement_entry_id')
            ->filter()
            ->all();

        $directReceipts = CompanyAccountStatementEntry::query()
            ->with('companyAccount')
            ->where('source_type', ShopLedgerTransaction::class)
            ->whereHasMorph('sourceRecord', [ShopLedgerTransaction::class], fn (Builder $q): Builder => $q->where('shop_id', $shopId))
            ->where('direction', 'in')
            ->where('status', 'reconciled')
            ->whereNotIn('id', $reconciledStatementIds)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->get();

        $approvedPayments = $paymentRequests->filter(fn (ShopInvoicePaymentRequest $p) => in_array($p->status, ['approved', 'verified'], true));
        $approvedPaymentsSum = round((float) ($approvedPayments->sum('approved_amount') ?: $approvedPayments->sum('requested_amount')), 2);
        $directReceiptsSum = round((float) $directReceipts->sum('amount'), 2);
        $companyReceivedThisMonth = round($approvedPaymentsSum + $directReceiptsSum, 2);

        $pendingVerification = round((float) $paymentRequests
            ->filter(fn (ShopInvoicePaymentRequest $p) => $p->status === 'pending' || $p->reconciliation_status === 'unreconciled')
            ->sum('requested_amount'), 2);

        $monthTransactions = ShopLedgerTransaction::query()
            ->with('entryType')
            ->where('shop_id', $shopId)
            ->whereBetween('business_date', [$startDate, $endDate])
            ->whereNotIn('status', ['void', 'voided', 'reversed'])
            ->whereNull('voided_at')
            ->get();

        $companyPaidExpenses = round((float) $monthTransactions
            ->filter(fn ($t) => $t->funding_source === 'company' && ! in_array($t->entryType?->code, ['purchase_bill', 'gl_bill'], true) && ! str_contains(strtolower($t->entryType?->name ?? ''), 'gl bill'))
            ->sum('amount'), 2);

        $shopLocalExpenses = round((float) $monthTransactions
            ->filter(fn ($t) => in_array($t->funding_source, ['sales', 'shop_cash'], true) && ($t->direction === 'expense' || $t->entryType?->category === 'expense') && $t->entryType?->code !== 'shop_paid_company')
            ->sum('amount'), 2);

        // ── 3. Allocation Classification & Credit Math ────────────────────────
        // Target Month Allocations: All active allocations against Month M obligations
        $allocationsThisMonth = ShopPaymentLedgerAllocation::query()
            ->with(['paymentRequest', 'ledgerTransaction.entryType', 'reconciledBy'])
            ->where('shop_id', $shopId)
            ->where('status', 'active')
            ->whereHas('ledgerTransaction', fn (Builder $q): Builder => $q->whereBetween('business_date', [$startDate, $endDate]))
            ->get();

        $totalAllocatedThisMonth = round((float) $allocationsThisMonth->sum('amount'), 2);

        // Classify allocations by Payment Source Date
        $currentPaymentsAllocatedThisMonth = 0.0;
        $previousCreditUtilizedThisMonth = 0.0;

        foreach ($allocationsThisMonth as $alloc) {
            $paymentDate = $alloc->paymentRequest?->payment_date
                ? Carbon::parse($alloc->paymentRequest->payment_date)->toDateString()
                : ($alloc->paymentRequest?->created_at ? $alloc->paymentRequest->created_at->toDateString() : null);

            $isPriorPayment = $paymentDate !== null && $paymentDate < $startDate;

            if ($isPriorPayment) {
                $previousCreditUtilizedThisMonth += (float) $alloc->amount;
            } else {
                $currentPaymentsAllocatedThisMonth += (float) $alloc->amount;
            }
        }

        $currentPaymentsAllocatedThisMonth = round($currentPaymentsAllocatedThisMonth, 2);
        $previousCreditUtilizedThisMonth = round($previousCreditUtilizedThisMonth, 2);

        // Prior Total Payments vs Prior Total Allocations for Opening Available Credit
        if (! $isPreOpening && $accountingStartDate !== null && $startDate === $accountingStartDate) {
            $openingAvailableCredit = $openingRecord ? (float) $openingRecord->opening_allocation_pending : 0.0;
        } elseif (! $isPreOpening && $accountingStartDate !== null) {
            $priorPayments = ShopInvoicePaymentRequest::query()
                ->where('shop_id', $shopId)
                ->whereIn('status', ['approved', 'verified'])
                ->where(function (Builder $query) use ($startDate, $accountingStartDate): void {
                    $query->whereBetween('payment_date', [$accountingStartDate, Carbon::parse($startDate)->subDay()->toDateString()])
                        ->orWhere(function (Builder $q2) use ($startDate, $accountingStartDate): void {
                            $q2->whereNull('payment_date')
                                ->whereBetween('created_at', [$accountingStartDate.' 00:00:00', Carbon::parse($startDate)->subDay()->toDateString().' 23:59:59']);
                        });
                })
                ->get();

            $priorPaymentsTotal = round((float) ($priorPayments->sum('approved_amount') ?: $priorPayments->sum('requested_amount')), 2)
                + round((float) CompanyAccountStatementEntry::query()
                    ->where('source_type', ShopLedgerTransaction::class)
                    ->whereHasMorph('sourceRecord', [ShopLedgerTransaction::class], fn (Builder $q): Builder => $q->where('shop_id', $shopId))
                    ->where('direction', 'in')
                    ->where('status', 'reconciled')
                    ->whereNotIn('id', $reconciledStatementIds)
                    ->whereBetween('transaction_date', [$accountingStartDate, Carbon::parse($startDate)->subDay()->toDateString()])
                    ->sum('amount'), 2);

            $priorAllocationsTotal = round((float) ShopPaymentLedgerAllocation::query()
                ->where('shop_id', $shopId)
                ->where('status', 'active')
                ->whereHas('ledgerTransaction', fn (Builder $q): Builder => $q->whereBetween('business_date', [$accountingStartDate, Carbon::parse($startDate)->subDay()->toDateString()]))
                ->sum('amount'), 2);

            $openingAvailableCredit = max(0.0, round($priorPaymentsTotal - $priorAllocationsTotal, 2));
        } else {
            // Historical pre-opening period calculation
            $priorPayments = ShopInvoicePaymentRequest::query()
                ->where('shop_id', $shopId)
                ->whereIn('status', ['approved', 'verified'])
                ->where(function (Builder $query) use ($startDate): void {
                    $query->where('payment_date', '<', $startDate)
                        ->orWhere(function (Builder $q2) use ($startDate): void {
                            $q2->whereNull('payment_date')->where('created_at', '<', $startDate.' 00:00:00');
                        });
                })
                ->get();

            $priorPaymentsTotal = round((float) ($priorPayments->sum('approved_amount') ?: $priorPayments->sum('requested_amount')), 2)
                + round((float) CompanyAccountStatementEntry::query()
                    ->where('source_type', ShopLedgerTransaction::class)
                    ->whereHasMorph('sourceRecord', [ShopLedgerTransaction::class], fn (Builder $q): Builder => $q->where('shop_id', $shopId))
                    ->where('direction', 'in')
                    ->where('status', 'reconciled')
                    ->whereNotIn('id', $reconciledStatementIds)
                    ->where('transaction_date', '<', $startDate)
                    ->sum('amount'), 2);

            $priorAllocationsTotal = round((float) ShopPaymentLedgerAllocation::query()
                ->where('shop_id', $shopId)
                ->where('status', 'active')
                ->whereHas('ledgerTransaction', fn (Builder $q): Builder => $q->where('business_date', '<', $startDate))
                ->sum('amount'), 2);

            $openingAvailableCredit = max(0.0, round($priorPaymentsTotal - $priorAllocationsTotal, 2));
        }

        // New Credit Created This Month from current month unallocated receipts
        $newCreditCreatedThisMonth = max(0.0, round($companyReceivedThisMonth - $currentPaymentsAllocatedThisMonth, 2));

        // Closing Available Credit
        $closingAvailableCredit = max(0.0, round($openingAvailableCredit + $newCreditCreatedThisMonth - $previousCreditUtilizedThisMonth, 2));

        // ── 4. Open Settlement Obligations & Projected Valid Allocation ────────
        $openSettlementTransactions = $this->reconciliationService->getOpenDailySettlements($shopId, $month);
        $openObligationsDue = round((float) $openSettlementTransactions->sum('remaining_due'), 2);

        // Additional valid allocation available is capped by available unallocated money and outstanding obligations
        $eligibleUnallocatedMoney = $closingAvailableCredit;
        $additionalValidAllocation = min($eligibleUnallocatedMoney, $openObligationsDue);
        $projectedTotalAllocated = round($totalAllocatedThisMonth + $additionalValidAllocation, 2);
        $remainingUnallocatedCredit = max(0.0, round($eligibleUnallocatedMoney - $additionalValidAllocation, 2));

        // ── 5. CURRENT POSITION vs PROJECTED POSITION Datasets ─────────────────
        $currentPosition = [
            'settlement_due' => $settlementDue,
            'company_received' => $companyReceivedThisMonth,
            'already_allocated' => $totalAllocatedThisMonth,
            'allocation_pending' => $newCreditCreatedThisMonth,
            'total_unallocated_credit' => $closingAvailableCredit,
            'pending_verification' => $pendingVerification,
            'closing_position' => $closingPhysicalPos,
            'direction' => $closingDirection,
            'direction_label' => $closingDirectionLabel,
            'open_obligations_due' => $openObligationsDue,
        ];

        $projectedPosition = [
            'settlement_due' => $settlementDue,
            'company_received' => $companyReceivedThisMonth,
            'already_allocated' => $totalAllocatedThisMonth,
            'additional_valid_allocation' => $additionalValidAllocation,
            'projected_total_allocated' => $projectedTotalAllocated,
            'remaining_unallocated_credit' => $remainingUnallocatedCredit,
            'pending_verification' => $pendingVerification,
            'closing_position' => $closingPhysicalPos,
            'direction' => $closingDirection,
            'direction_label' => $closingDirectionLabel,
            'open_obligations_due' => $openObligationsDue,
            'projected_remaining_obligations' => max(0.0, round($openObligationsDue - $additionalValidAllocation, 2)),
        ];

        // ── 6. Month Status ──────────────────────────────────────────────────
        $statusKey = match (true) {
            $pendingVerification > 0.01 => 'pending_verification',
            $newCreditCreatedThisMonth > 0.01 || $openingAvailableCredit > 0.01 => 'allocation_pending',
            $openObligationsDue <= 0.01 || ($openObligationsDue - $additionalValidAllocation) <= 0.01 => 'settled',
            default => 'ready_to_settle',
        };

        $statusLabel = match ($statusKey) {
            'pending_verification' => 'Pending Verification',
            'allocation_pending' => 'Allocation Pending',
            'settled' => 'Settled',
            'ready_to_settle' => 'Ready to Settle',
        };

        $statusBadgeColor = match ($statusKey) {
            'pending_verification' => 'sky',
            'allocation_pending' => 'amber',
            'settled' => 'emerald',
            'ready_to_settle' => 'indigo',
        };

        // ── 7. Drilldown Datasets (Read-Only) ────────────────────────────────
        // A. Allocation Pending Items
        $allocationPendingItems = $paymentRequests
            ->filter(function (ShopInvoicePaymentRequest $p) use ($startDate, $endDate): bool {
                if (! in_array($p->status, ['approved', 'verified'], true)) {
                    return false;
                }
                $verifiedAmt = (float) ($p->approved_amount > 0 ? $p->approved_amount : $p->requested_amount);
                $allocThisMonth = (float) $p->ledgerAllocations
                    ->filter(fn ($a) => ($a->status ?? 'active') === 'active' && $a->ledgerTransaction && $a->ledgerTransaction->business_date >= $startDate && $a->ledgerTransaction->business_date <= $endDate)
                    ->sum('amount');
                $unalloc = max(0.0, $verifiedAmt - $allocThisMonth);

                return $unalloc > 0.001;
            })
            ->map(function (ShopInvoicePaymentRequest $p) use ($startDate, $endDate, $openObligationsDue): array {
                $receivedAmt = round((float) $p->requested_amount, 2);
                $verifiedAmt = round((float) ($p->approved_amount > 0 ? $p->approved_amount : $p->requested_amount), 2);
                $alreadyAlloc = round((float) $p->ledgerAllocations
                    ->filter(fn ($a) => ($a->status ?? 'active') === 'active' && $a->ledgerTransaction && $a->ledgerTransaction->business_date >= $startDate && $a->ledgerTransaction->business_date <= $endDate)
                    ->sum('amount'), 2);
                $unalloc = round(max(0, $verifiedAmt - $alreadyAlloc), 2);
                $potentialAlloc = min($unalloc, $openObligationsDue);

                return [
                    'id' => $p->id,
                    'date' => $p->payment_date ? Carbon::parse($p->payment_date)->format('d M Y') : $p->created_at->format('d M Y'),
                    'raw_date' => $p->payment_date?->toDateString() ?? $p->created_at->toDateString(),
                    'reference' => (string) ($p->payment_reference ?: 'PAY-'.$p->id),
                    'received_amount' => $receivedAmt,
                    'verified_amount' => $verifiedAmt,
                    'already_allocated' => $alreadyAlloc,
                    'remaining_unallocated' => $unalloc,
                    'source' => ucfirst((string) ($p->payment_method ?: 'cash')),
                    'bank_or_cash' => $p->reconciliations->first()?->companyAccount?->name ?? (ucfirst((string) ($p->payment_method ?: 'cash'))),
                    'status' => ucfirst((string) $p->status),
                    'potential_allocation' => $potentialAlloc,
                ];
            })->values()->all();

        // B. Outstanding Obligations
        $remPool = $eligibleUnallocatedMoney;
        $outstandingObligationItems = $openSettlementTransactions->map(function (array $st) use (&$remPool): array {
            $remainingDue = (float) ($st['remaining_due'] ?? 0.0);
            $simAlloc = round(min($remPool, $remainingDue), 2);
            $remPool = max(0.0, round($remPool - $simAlloc, 2));

            return [
                'id' => $st['id'],
                'business_date' => $st['formatted_date'],
                'raw_date' => $st['business_date'],
                'reference' => $st['entry_name'],
                'entry_type' => $st['entry_name'],
                'original_amount' => round((float) $st['original_amount'], 2),
                'already_allocated' => round((float) $st['already_allocated'], 2),
                'remaining_due' => round($remainingDue, 2),
                'status' => (float) $st['already_allocated'] > 0.001 ? 'Partially Allocated' : 'Unallocated',
                'simulated_allocation' => $simAlloc,
                'projected_remaining' => round(max(0.0, $remainingDue - $simAlloc), 2),
            ];
        })->values()->all();

        $drilldowns = [
            'settlement_due_items' => ($payableCalculation['items'] ?? []),
            'payments' => $paymentRequests->map(function (ShopInvoicePaymentRequest $p) use ($startDate, $endDate): array {
                $verifiedAmt = round((float) ($p->status === 'approved' && $p->approved_amount > 0 ? $p->approved_amount : $p->requested_amount), 2);
                $allocatedThisMonth = round((float) $p->ledgerAllocations
                    ->filter(fn ($a) => ($a->status ?? 'active') === 'active' && $a->ledgerTransaction && $a->ledgerTransaction->business_date >= $startDate && $a->ledgerTransaction->business_date <= $endDate)
                    ->sum('amount'), 2);
                $unallocatedThisMonth = round(max(0, $verifiedAmt - $allocatedThisMonth), 2);

                return [
                    'id' => $p->id,
                    'date' => $p->payment_date ? Carbon::parse($p->payment_date)->format('d M Y') : $p->created_at->format('d M Y'),
                    'raw_date' => $p->payment_date?->toDateString() ?? $p->created_at->toDateString(),
                    'reference' => (string) ($p->payment_reference ?: 'PAY-'.$p->id),
                    'method' => ucfirst((string) ($p->payment_method ?: 'cash')),
                    'amount' => $verifiedAmt,
                    'allocated' => $allocatedThisMonth,
                    'unallocated' => $unallocatedThisMonth,
                    'status' => $p->status,
                    'account' => $p->reconciliations->first()?->companyAccount?->name ?? '—',
                ];
            })->values()->all(),
            'allocation_pending_items' => $allocationPendingItems,
            'outstanding_obligations' => $outstandingObligationItems,
            'allocations' => $allocationsThisMonth->map(fn (ShopPaymentLedgerAllocation $a): array => [
                'id' => $a->id,
                'amount' => round((float) $a->amount, 2),
                'target_date' => $a->ledgerTransaction?->business_date?->format('d M Y') ?? '—',
                'target_category' => $a->ledgerTransaction?->entryType?->name ?? 'Daily Obligation',
                'payment_ref' => $a->paymentRequest?->payment_reference ?: ($a->payment_request_id ? 'PAY-'.$a->payment_request_id : 'Direct'),
                'payment_date' => $a->paymentRequest?->payment_date ? Carbon::parse($a->paymentRequest->payment_date)->format('d M Y') : '—',
                'source_type' => ($a->paymentRequest?->payment_date && Carbon::parse($a->paymentRequest->payment_date)->toDateString() < $startDate) ? 'Previous Credit' : 'Current Month Payment',
            ])->values()->all(),
            'gl_bills' => $monthTransactions->filter(fn ($t) => in_array($t->entryType?->code, ['purchase_bill', 'gl_bill'], true) || str_contains(strtolower($t->entryType?->name ?? ''), 'gl bill'))->map(fn ($t) => [
                'id' => $t->id,
                'date' => $t->business_date?->format('d M Y'),
                'description' => $t->notes ?: $t->entryType?->name,
                'amount' => round((float) $t->amount, 2),
            ])->values()->all(),
            'company_expenses' => $monthTransactions->filter(fn ($t) => $t->funding_source === 'company' && ! in_array($t->entryType?->code, ['purchase_bill', 'gl_bill'], true) && ! str_contains(strtolower($t->entryType?->name ?? ''), 'gl bill'))->map(fn ($t) => [
                'id' => $t->id,
                'date' => $t->business_date?->format('d M Y'),
                'category' => $t->entryType?->name ?: 'Company Expense',
                'description' => $t->notes ?: 'Paid by Company',
                'amount' => round((float) $t->amount, 2),
            ])->values()->all(),
        ];

        return [
            'shop' => [
                'id' => $shopId,
                'slug' => $profile?->slug ?: $shopId,
                'name' => $profile?->name ?: ($profile?->shop?->name ?: 'Shop #'.$shopId),
                'code' => $profile?->code ?: ($profile?->shop?->code ?: 'SHP-'.$shopId),
                'client_name' => $profile?->client?->name,
            ],
            'period' => [
                'month' => $month,
                'label' => $monthCarbon->format('F Y'),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'next_month' => $nextMonthStr,
                'next_month_label' => $nextMonthLabel,
                'is_pre_opening' => $isPreOpening,
                'accounting_start_date' => $accountingStartDate,
            ],
            'opening' => [
                'physical_position' => $openingPhysicalPos,
                'direction' => $openingDirection,
                'direction_label' => $openingDirectionLabel,
                'previous_available_credit' => $openingAvailableCredit,
                'available_credit' => $openingAvailableCredit,
            ],
            'activity' => [
                'settlement_due' => $settlementDue,
                'company_received' => $companyReceivedThisMonth,
                'gl_bills' => $glBills,
                'company_paid_expenses' => $companyPaidExpenses,
                'shop_local_expenses' => $shopLocalExpenses,
                'allocated_this_month' => $totalAllocatedThisMonth,
                'allocated_to_month' => $totalAllocatedThisMonth,
                'current_payments_allocated' => $currentPaymentsAllocatedThisMonth,
                'previous_credit_utilized' => $previousCreditUtilizedThisMonth,
                'new_unallocated_credit' => $newCreditCreatedThisMonth,
                'pending_verification' => $pendingVerification,
            ],
            'current_position' => $currentPosition,
            'projected_position' => $projectedPosition,
            'closing' => [
                'physical_position' => $closingPhysicalPos,
                'direction' => $closingDirection,
                'direction_label' => $closingDirectionLabel,
                'closing_available_credit' => $closingAvailableCredit,
                'available_credit' => $closingAvailableCredit,
            ],
            'credit' => [
                'opening_available_credit' => $openingAvailableCredit,
                'used_this_month' => $previousCreditUtilizedThisMonth,
                'new_credit_created' => $newCreditCreatedThisMonth,
                'closing_available_credit' => $closingAvailableCredit,
            ],
            'carry_forward' => [
                'next_month' => $nextMonthStr,
                'next_month_label' => $nextMonthLabel,
                'opening_physical_position' => $closingPhysicalPos,
                'direction' => $closingDirection,
                'direction_label' => $closingDirectionLabel,
                'available_previous_credit' => $closingAvailableCredit,
            ],
            'status' => [
                'key' => $statusKey,
                'label' => $statusLabel,
                'badge_color' => $statusBadgeColor,
            ],
            'drilldowns' => $drilldowns,
        ];
    }
}
