<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Models\ProcurementExpense;
use App\Models\PurchaserCart;
use App\Models\User;
use App\Services\Finance\PurchaserFinanceService;
use App\Services\Finance\PurchaserSettlementService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PurchaserMonthlySummaryService
{
    public function __construct(
        private readonly PurchaserFinanceService $purchaserFinanceService,
        private readonly PurchaserSettlementService $purchaserSettlementService,
    ) {}

    /**
     * Get all active eligible purchasers.
     * Excludes inactive, test, disabled, shop, and non-purchaser accounts.
     *
     * @return Collection<int, User>
     */
    public function getActivePurchasers(): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($roles) => $roles->where('name', 'purchaser'))
            ->where(function ($query): void {
                $query->where('registration_status', 'approved')
                    ->orWhereNull('registration_status');
            })
            ->whereNull('shop_id')
            ->orderBy('name')
            ->get();
    }

    /**
     * Check if a given user is an eligible active purchaser.
     */
    public function isPurchaserEligible(User|int|string $userOrId): bool
    {
        $user = null;
        if ($userOrId instanceof User) {
            $user = $userOrId;
        } elseif (is_numeric($userOrId)) {
            $user = User::query()->find((int) $userOrId);
        } elseif (is_string($userOrId)) {
            $user = User::query()->where('public_uuid', $userOrId)->first();
        }

        if (! $user instanceof User) {
            return false;
        }

        if ($user->shop_id !== null) {
            return false;
        }

        if (in_array($user->registration_status, ['pending', 'rejected', 'disabled', 'inactive'], true)) {
            return false;
        }

        return $user->hasRole('purchaser');
    }

    /**
     * Resolve start date, end date, and yearMonth for a given month input.
     *
     * @return array{yearMonth: string, startDate: string, endDate: string, nextMonthDate: string, formattedMonth: string}
     */
    public function resolveMonthRange(?string $monthInput = null): array
    {
        $now = now('Asia/Kolkata');
        $input = trim((string) $monthInput);

        if ($input !== '' && preg_match('/^\d{4}-\d{2}$/', $input) === 1) {
            try {
                $carbon = Carbon::createFromFormat('Y-m-d', $input.'-01', 'Asia/Kolkata')->startOfDay();
            } catch (\Throwable) {
                $carbon = $now->copy()->startOfMonth();
            }
        } else {
            $carbon = $now->copy()->startOfMonth();
        }

        $yearMonth = $carbon->format('Y-m');
        $startDate = $carbon->toDateString();
        $nextMonth = $carbon->copy()->addMonth();
        $nextMonthDate = $nextMonth->toDateString();
        $endDate = $carbon->copy()->endOfMonth()->toDateString();

        return [
            'yearMonth' => $yearMonth,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'nextMonthDate' => $nextMonthDate,
            'formattedMonth' => $carbon->format('F Y'),
        ];
    }

    /**
     * Consolidated All Purchasers Monthly Summary matrix for a given month.
     * Zero DB mutations.
     *
     * @return array{
     *     month: string,
     *     formatted_month: string,
     *     purchasers: array<int, array<string, mixed>>,
     *     grand_totals: array<string, mixed>
     * }
     */
    public function getAllPurchasersSummary(string $month): array
    {
        $range = $this->resolveMonthRange($month);
        $purchasers = $this->getActivePurchasers();

        $rows = [];
        $totals = [
            'opening_cash_net' => 0.0,
            'opening_credit_pending' => 0.0,
            'credit_purchases' => 0.0,
            'credit_settled' => 0.0,
            'credit_discount' => 0.0,
            'credit_pending' => 0.0,
            'company_funded' => 0.0,
            'cash_returned' => 0.0,
            'total_purchases' => 0.0,
            'cash_purchases' => 0.0,
            'cash_used' => 0.0,
            'procurement_expenses' => 0.0,
            'expenses' => 0.0,
            'closing_cash_net' => 0.0,
            'cash_in_hand_net' => 0.0,
            'vendor_credit_outstanding' => 0.0,
            'pending_items_count' => 0,
            'pending_bills_count' => 0,
            'pending_bills_amount' => 0.0,
        ];

        foreach ($purchasers as $purchaser) {
            $detail = $this->getPurchaserMonthlyDetail($purchaser, $month);

            $openingCash = (float) ($detail['opening']['cash_balance'] ?? 0.0);
            $openingDir = (string) ($detail['opening']['direction'] ?? 'settled');
            $openingNet = $openingDir === 'company_owes_purchaser' ? -$openingCash : $openingCash;

            $closingCash = (float) ($detail['closing']['cash_balance'] ?? 0.0);
            $closingDir = (string) ($detail['closing']['direction'] ?? 'settled');
            $closingNet = $closingDir === 'company_owes_purchaser' ? -$closingCash : $closingCash;

            $openingCredit = (float) ($detail['credit']['opening_credit_pending'] ?? 0.0);
            $creditPurchases = (float) ($detail['credit']['credit_purchases'] ?? 0.0);
            $creditSettled = (float) ($detail['credit']['credit_settled'] ?? 0.0);
            $creditDiscount = (float) ($detail['credit']['credit_discount'] ?? 0.0);
            $creditPending = (float) ($detail['credit']['credit_pending'] ?? 0.0);

            $pendingCount = (int) ($detail['pending']['pending_bills_count'] ?? 0)
                + (int) ($detail['pending']['pending_inventory_count'] ?? 0);

            $rows[] = [
                'purchaser_id' => (int) $purchaser->id,
                'public_uuid' => (string) $purchaser->public_uuid,
                'name' => (string) $purchaser->name,
                'email' => (string) $purchaser->email,

                // Explicit Cash Concept
                'opening_cash' => abs($openingCash),
                'opening_cash_direction' => $openingDir,
                'opening_cash_direction_label' => (string) ($detail['opening']['direction_label'] ?? 'Settled (Zero Balance)'),
                'opening_balance' => abs($openingCash),
                'opening_direction' => $openingDir,
                'opening_direction_label' => (string) ($detail['opening']['direction_label'] ?? 'Settled (Zero Balance)'),

                // Explicit Vendor Purchasing Credit Concept
                'opening_credit_pending' => $openingCredit,
                'credit_purchases' => $creditPurchases,
                'credit_settled' => $creditSettled,
                'credit_discount' => $creditDiscount,
                'credit_pending' => $creditPending,
                'credit_direction' => (string) ($detail['credit']['direction'] ?? 'settled'),
                'credit_direction_label' => (string) ($detail['credit']['direction_label'] ?? 'Settled'),
                'credit_breakdown_by_vendor' => $detail['credit']['breakdown_by_vendor'] ?? [],

                // Funding & Purchasing Activities
                'company_funded' => (float) ($detail['activity']['company_funded'] ?? 0.0),
                'cash_returned' => (float) ($detail['activity']['cash_returned'] ?? 0.0),
                'total_purchases' => (float) ($detail['activity']['total_purchases'] ?? 0.0),
                'cash_purchases' => (float) ($detail['activity']['cash_purchases'] ?? 0.0),
                'cash_used' => (float) ($detail['activity']['cash_used'] ?? 0.0),
                'expenses' => (float) ($detail['activity']['procurement_expenses'] ?? 0.0),
                'procurement_expenses' => (float) ($detail['activity']['procurement_expenses'] ?? 0.0),

                // Pending Operations
                'pending_items_count' => $pendingCount,
                'pending_bills_count' => (int) ($detail['pending']['pending_bills_count'] ?? 0),
                'pending_bills_amount' => (float) ($detail['pending']['pending_bills_amount'] ?? 0.0),

                // Cash in Hand Position
                'cash_in_hand' => abs($closingCash),
                'cash_in_hand_direction' => $closingDir,
                'cash_in_hand_direction_label' => (string) ($detail['closing']['direction_label'] ?? 'Settled (Zero Balance)'),
                'closing_cash' => abs($closingCash),

                // Status & Detailed Objects
                'status' => $detail['status'],
                'opening' => $detail['opening'],
                'credit' => $detail['credit'],
                'activity' => $detail['activity'],
                'cash_details' => $detail['cash_details'],
                'bills' => $detail['bills'],
                'closing' => $detail['closing'],
                'pending' => $detail['pending'],
            ];

            $totals['opening_cash_net'] += $openingNet;
            $totals['opening_credit_pending'] += $openingCredit;
            $totals['credit_purchases'] += $creditPurchases;
            $totals['credit_settled'] += $creditSettled;
            $totals['credit_discount'] += $creditDiscount;
            $totals['credit_pending'] += $creditPending;
            $totals['company_funded'] += (float) ($detail['activity']['company_funded'] ?? 0.0);
            $totals['cash_returned'] += (float) ($detail['activity']['cash_returned'] ?? 0.0);
            $totals['total_purchases'] += (float) ($detail['activity']['total_purchases'] ?? 0.0);
            $totals['cash_purchases'] += (float) ($detail['activity']['cash_purchases'] ?? 0.0);
            $totals['cash_used'] += (float) ($detail['activity']['cash_used'] ?? 0.0);
            $totals['procurement_expenses'] += (float) ($detail['activity']['procurement_expenses'] ?? 0.0);
            $totals['expenses'] += (float) ($detail['activity']['procurement_expenses'] ?? 0.0);
            $totals['closing_cash_net'] += $closingNet;
            $totals['cash_in_hand_net'] += $closingNet;
            $totals['vendor_credit_outstanding'] += $creditPending;
            $totals['pending_items_count'] += $pendingCount;
            $totals['pending_bills_count'] += (int) ($detail['pending']['pending_bills_count'] ?? 0);
            $totals['pending_bills_amount'] += (float) ($detail['pending']['pending_bills_amount'] ?? 0.0);
        }

        return [
            'month' => $range['yearMonth'],
            'formatted_month' => $range['formattedMonth'],
            'purchasers' => $rows,
            'grand_totals' => $totals,
        ];
    }

    /**
     * Get vendor purchasing credit summary and vendor-by-vendor breakdown for a given purchaser and month.
     * Zero DB mutations.
     *
     * @return array{
     *     opening_credit: float,
     *     credit_purchases: float,
     *     credit_settled: float,
     *     credit_discount: float,
     *     credit_pending: float,
     *     direction: string,
     *     direction_label: string,
     *     breakdown_by_vendor: array<int, array<string, mixed>>,
     *     invoices: array<int, array<string, mixed>>
     * }
     */
    public function getPurchaserVendorCreditSummary(int $purchaserId, string $startDate, string $nextMonthDate): array
    {
        $allCreditInvoices = DB::table('purchase_invoices')
            ->leftJoin('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->where(function (Builder $query) use ($purchaserId): void {
                $query->where('purchase_invoices.purchaser_submitted_by', $purchaserId)
                    ->orWhere('purchaser_carts.user_id', $purchaserId);
            })
            ->where(function (Builder $query): void {
                $query->whereRaw('LOWER(COALESCE(purchaser_carts.payment_method, purchase_invoices.payment_method, "")) = "credit"')
                    ->orWhere('purchase_invoices.payment_paid_by', 'vendor_credit')
                    ->orWhere('purchase_invoices.payment_status', 'credit_pending_approval')
                    ->orWhereExists(function (Builder $sub): void {
                        $sub->selectRaw(1)
                            ->from('vendor_settlement_allocations')
                            ->whereColumn('vendor_settlement_allocations.purchase_invoice_id', 'purchase_invoices.id');
                    });
            })
            ->selectRaw('
                purchase_invoices.id,
                purchase_invoices.public_uuid,
                purchase_invoices.invoice_number,
                purchase_invoices.supplier_id,
                suppliers.name as supplier_name,
                suppliers.public_uuid as supplier_public_uuid,
                ROUND(purchase_invoices.amount - purchase_invoices.discount_amount, 2) as net_amount,
                purchase_invoices.paid_amount,
                purchase_invoices.payment_status,
                purchase_invoices.status,
                COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.original_business_date), DATE(purchase_invoices.created_at)) as business_date
            ')
            ->orderByDesc('business_date')
            ->orderByDesc('purchase_invoices.id')
            ->get();

        $invoiceIds = $allCreditInvoices->pluck('id')->all();

        $allocations = DB::table('vendor_settlement_allocations')
            ->join('vendor_settlements', 'vendor_settlements.id', '=', 'vendor_settlement_allocations.vendor_settlement_id')
            ->whereIn('vendor_settlement_allocations.purchase_invoice_id', $invoiceIds)
            ->where('vendor_settlement_allocations.is_reversed', false)
            ->selectRaw('
                vendor_settlement_allocations.id,
                vendor_settlement_allocations.purchase_invoice_id,
                vendor_settlement_allocations.cash_allocated,
                vendor_settlement_allocations.advance_allocated,
                vendor_settlement_allocations.discount_allocated,
                vendor_settlement_allocations.total_settled,
                DATE(vendor_settlements.payment_date) as payment_date
            ')
            ->get();

        $purchaserPayments = DB::table('purchase_invoice_payments')
            ->whereIn('purchase_invoice_id', $invoiceIds)
            ->where('payment_paid_by', 'purchaser')
            ->selectRaw('purchase_invoice_id, amount, DATE(payment_date) as payment_date')
            ->get();

        $vendorBreakdowns = [];
        $totalOpeningCredit = 0.0;
        $totalCreditPurchases = 0.0;
        $totalCreditSettled = 0.0;
        $totalCreditDiscount = 0.0;
        $allInvoicesList = [];

        foreach ($allCreditInvoices->groupBy('supplier_id') as $supplierId => $supplierInvoices) {
            $first = $supplierInvoices->first();
            $supplierName = (string) ($first->supplier_name ?? 'Unassigned Vendor');
            $supplierUuid = (string) ($first->supplier_public_uuid ?? '');

            $suppInvIds = $supplierInvoices->pluck('id')->all();
            $suppAllocations = $allocations->whereIn('purchase_invoice_id', $suppInvIds);
            $suppPurchaserPayments = $purchaserPayments->whereIn('purchase_invoice_id', $suppInvIds);

            $preInvoices = $supplierInvoices->filter(fn ($i) => $i->business_date < $startDate);
            $monthInvoices = $supplierInvoices->filter(fn ($i) => $i->business_date >= $startDate && $i->business_date < $nextMonthDate);

            $preAllocations = $suppAllocations->filter(fn ($a) => $a->payment_date < $startDate);
            $monthAllocations = $suppAllocations->filter(fn ($a) => $a->payment_date >= $startDate && $a->payment_date < $nextMonthDate);

            $vendorOpeningCredit = 0.0;
            foreach ($preInvoices as $inv) {
                $invPreAlloc = (float) $preAllocations->where('purchase_invoice_id', $inv->id)->sum('total_settled');
                $invPrePurchaserPay = (float) $suppPurchaserPayments->where('purchase_invoice_id', $inv->id)->where('payment_date', '<', $startDate)->sum('amount');
                $invPreTotalPaid = $invPreAlloc + $invPrePurchaserPay;
                $vendorOpeningCredit += max(0.0, (float) $inv->net_amount - $invPreTotalPaid);
            }
            $vendorOpeningCredit = round($vendorOpeningCredit, 2);

            $vendorCreditPurchases = round((float) $monthInvoices->sum('net_amount'), 2);
            $vendorCreditSettled = round((float) $monthAllocations->sum(fn ($a) => (float) $a->cash_allocated + (float) $a->advance_allocated), 2);
            $vendorCreditDiscount = round((float) $monthAllocations->sum('discount_allocated'), 2);

            // If no allocations exist for month invoices, fallback to direct paid_amount on month invoices (for test/legacy direct records)
            if ($vendorCreditSettled <= 0.0001 && $vendorCreditDiscount <= 0.0001 && $monthInvoices->isNotEmpty()) {
                $monthDirectPaid = (float) $monthInvoices->sum('paid_amount');
                if ($monthDirectPaid > 0) {
                    $vendorCreditSettled = round($monthDirectPaid, 2);
                }
            }

            $vendorCreditPending = round(max(0.0, $vendorOpeningCredit + $vendorCreditPurchases - $vendorCreditSettled - $vendorCreditDiscount), 2);

            $totalOpeningCredit += $vendorOpeningCredit;
            $totalCreditPurchases += $vendorCreditPurchases;
            $totalCreditSettled += $vendorCreditSettled;
            $totalCreditDiscount += $vendorCreditDiscount;

            $vendorInvoicesList = [];
            foreach ($supplierInvoices as $inv) {
                $invAllocs = $suppAllocations->where('purchase_invoice_id', $inv->id);
                $invTotalSettled = (float) $invAllocs->sum('total_settled');
                $invTotalPurchaserPay = (float) $suppPurchaserPayments->where('purchase_invoice_id', $inv->id)->sum('amount');
                $invPending = max(0.0, (float) $inv->net_amount - $invTotalSettled - $invTotalPurchaserPay);

                $invItem = [
                    'id' => (int) $inv->id,
                    'public_uuid' => (string) $inv->public_uuid,
                    'invoice_number' => (string) $inv->invoice_number,
                    'supplier_id' => (int) $supplierId,
                    'supplier_name' => $supplierName,
                    'business_date' => (string) $inv->business_date,
                    'net_amount' => (float) $inv->net_amount,
                    'settled_amount' => round($invTotalSettled + $invTotalPurchaserPay, 2),
                    'pending_amount' => round($invPending, 2),
                    'status' => $invPending <= 0.001 ? 'paid' : ($invTotalSettled > 0 ? 'partial' : 'unpaid'),
                ];

                $vendorInvoicesList[] = $invItem;
                $allInvoicesList[] = $invItem;
            }

            $vendorBreakdowns[] = [
                'supplier_id' => (int) $supplierId,
                'supplier_name' => $supplierName,
                'supplier_public_uuid' => $supplierUuid,
                'opening_credit' => $vendorOpeningCredit,
                'credit_purchases' => $vendorCreditPurchases,
                'credit_settled' => $vendorCreditSettled,
                'credit_discount' => $vendorCreditDiscount,
                'credit_pending' => $vendorCreditPending,
                'invoices_count' => $supplierInvoices->count(),
                'invoices' => $vendorInvoicesList,
            ];
        }

        // Sort vendor breakdowns by pending amount descending, then name
        usort($vendorBreakdowns, function ($a, $b) {
            if (abs($b['credit_pending'] - $a['credit_pending']) > 0.001) {
                return $b['credit_pending'] <=> $a['credit_pending'];
            }

            return strcmp((string) $a['supplier_name'], (string) $b['supplier_name']);
        });

        $totalCreditPending = round(max(0.0, $totalOpeningCredit + $totalCreditPurchases - $totalCreditSettled - $totalCreditDiscount), 2);
        $direction = $totalCreditPending > 0.0001 ? 'vendor_credit_pending' : 'settled';
        $directionLabel = $totalCreditPending > 0.0001 ? 'Vendor Credit Pending' : 'Settled';

        return [
            'opening_credit' => round($totalOpeningCredit, 2),
            'credit_purchases' => round($totalCreditPurchases, 2),
            'credit_settled' => round($totalCreditSettled, 2),
            'credit_discount' => round($totalCreditDiscount, 2),
            'credit_pending' => $totalCreditPending,
            'direction' => $direction,
            'direction_label' => $directionLabel,
            'breakdown_by_vendor' => $vendorBreakdowns,
            'invoices' => $allInvoicesList,
        ];
    }

    /**
     * Get complete read-only monthly closing summary report and drilldowns for a single purchaser.
     * Zero DB mutations.
     *
     * @return array<string, mixed>
     */
    public function getPurchaserMonthlyDetail(User $purchaser, string $month): array
    {
        $range = $this->resolveMonthRange($month);
        $purchaserId = (int) $purchaser->id;
        $startDate = $range['startDate'];
        $nextMonthDate = $range['nextMonthDate'];
        $endDate = $range['endDate'];

        $monthCarbon = Carbon::createFromFormat('Y-m-d', $startDate, 'Asia/Kolkata');
        $prevMonthEnd = $monthCarbon->copy()->subDay()->toDateString();
        $nextMonthLabel = $monthCarbon->copy()->addMonth()->format('F Y');
        $nextMonthStr = $monthCarbon->copy()->addMonth()->format('Y-m');

        // ── 1. Opening Cash Position (Physical Cash Ledger starting from Accounting Opening) ──
        $opening = $this->purchaserSettlementService->openingBalanceBefore($purchaserId, $startDate);
        $openingCash = round((float) ($opening['advance'] ?? 0.0), 2);
        $openingDirection = match (true) {
            $openingCash > 0.0001 => 'purchaser_holds_company_cash',
            $openingCash < -0.0001 => 'company_owes_purchaser',
            default => 'settled',
        };
        $openingDirectionLabel = match ($openingDirection) {
            'purchaser_holds_company_cash' => 'Purchaser holds Company Cash',
            'company_owes_purchaser' => 'Company owes Purchaser',
            default => 'Settled (Zero Balance)',
        };

        // ── 2. Vendor Purchasing Credit Position (Continuous Vendor Credit Obligations) ──
        $vendorCredit = $this->getPurchaserVendorCreditSummary($purchaserId, $startDate, $nextMonthDate);

        // ── 3. Month Cash & Funding Activity (business_date in [startDate, nextMonthDate)) ──
        $creditsInPeriod = DB::table('purchaser_credits')
            ->leftJoin('cashbook_company_accounts', 'cashbook_company_accounts.id', '=', 'purchaser_credits.company_account_id')
            ->leftJoin('purchase_invoices', 'purchase_invoices.id', '=', 'purchaser_credits.purchase_invoice_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
            ->where('purchaser_credits.purchaser_id', $purchaserId)
            ->whereDate('purchaser_credits.business_date', '>=', $startDate)
            ->whereDate('purchaser_credits.business_date', '<', $nextMonthDate)
            ->selectRaw('
                purchaser_credits.id,
                purchaser_credits.type,
                purchaser_credits.amount,
                purchaser_credits.business_date,
                purchaser_credits.payment_source,
                purchaser_credits.reference,
                purchaser_credits.description,
                purchaser_credits.purchase_invoice_id,
                purchase_invoices.invoice_number,
                suppliers.name as supplier_name,
                cashbook_company_accounts.name as company_account_name
            ')
            ->orderByDesc('purchaser_credits.business_date')
            ->orderByDesc('purchaser_credits.id')
            ->get();

        $monthFundingAdded = round((float) $creditsInPeriod->where('type', 'in')->sum('amount'), 2);
        $monthCashReturned = round((float) $creditsInPeriod->where('type', 'out')->whereNull('purchase_invoice_id')->sum('amount'), 2);
        $monthAdvanceUtilized = round((float) $creditsInPeriod->where('type', 'out')->whereNotNull('purchase_invoice_id')->sum('amount'), 2);
        $monthCashUsedTotal = round((float) $creditsInPeriod->where('type', 'out')->sum('amount'), 2);

        // ── 4. Procurement Expenses in Period ──
        $expensesInPeriod = ProcurementExpense::query()
            ->with('companyAccountingEntry')
            ->where('user_id', $purchaserId)
            ->whereDate('expense_date', '>=', $startDate)
            ->whereDate('expense_date', '<', $nextMonthDate)
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        $monthExpensesSum = round((float) $expensesInPeriod->sum('amount'), 2);

        $expensesByCategory = [];
        foreach (ProcurementExpense::categories() as $catKey => $catLabel) {
            $catAmount = round((float) $expensesInPeriod->where('category', $catKey)->sum('amount'), 2);
            if ($catAmount > 0) {
                $expensesByCategory[] = [
                    'key' => $catKey,
                    'label' => $catLabel,
                    'amount' => $catAmount,
                ];
            }
        }

        // ── 5. Purchase Invoices in Period (Attributed by Business Date) ──
        $invoicesInPeriod = DB::table('purchase_invoices')
            ->leftJoin('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
            ->leftJoin('goods_received', 'goods_received.id', '=', 'purchase_invoices.goods_received_id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where(function (Builder $query) use ($purchaserId): void {
                $query->where('purchase_invoices.purchaser_submitted_by', $purchaserId)
                    ->orWhere('purchaser_carts.user_id', $purchaserId);
            })
            ->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.original_business_date), DATE(purchase_invoices.created_at)) >= ?', [$startDate])
            ->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.original_business_date), DATE(purchase_invoices.created_at)) < ?', [$nextMonthDate])
            ->selectRaw('
                purchase_invoices.id,
                purchase_invoices.public_uuid,
                purchase_invoices.invoice_number,
                purchase_invoices.amount,
                purchase_invoices.discount_amount,
                purchase_invoices.paid_amount,
                purchase_invoices.payment_method,
                purchase_invoices.payment_status,
                purchase_invoices.payment_paid_by,
                purchase_invoices.status,
                purchase_invoices.goods_received_id,
                purchase_invoices.supplier_id,
                suppliers.name as supplier_name,
                goods_received.grn_number,
                purchaser_carts.cart_number,
                COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.original_business_date), DATE(purchase_invoices.created_at)) as business_date,
                CASE
                    WHEN LOWER(COALESCE(purchaser_carts.payment_method, purchase_invoices.payment_method, "")) = "credit"
                         OR purchase_invoices.payment_paid_by = "vendor_credit"
                         OR purchase_invoices.payment_status = "credit_pending_approval"
                         OR purchase_invoices.payment_paid_by = "company"
                    THEN "credit"
                    ELSE "cash"
                END as payment_class
            ')
            ->orderByDesc('business_date')
            ->orderByDesc('purchase_invoices.id')
            ->get();

        $activeInvoices = $invoicesInPeriod->filter(fn ($inv) => $inv->status !== 'cancelled');
        $cancelledInvoices = $invoicesInPeriod->filter(fn ($inv) => $inv->status === 'cancelled');

        $totalBillCount = $activeInvoices->count();
        $totalPurchaseAmount = round((float) $activeInvoices->sum(fn ($inv) => max(0.0, (float) $inv->amount - (float) $inv->discount_amount)), 2);

        $cashInvoices = $activeInvoices->filter(fn ($inv) => $inv->payment_class === 'cash');
        $cashPurchaseAmount = round((float) $cashInvoices->sum(fn ($inv) => max(0.0, (float) $inv->amount - (float) $inv->discount_amount)), 2);

        $matchedInvoices = $activeInvoices->filter(fn ($inv) => ! empty($inv->goods_received_id));
        $pendingInventoryInvoices = $activeInvoices->filter(fn ($inv) => empty($inv->goods_received_id));
        $unlinkedVendorInvoices = $activeInvoices->filter(fn ($inv) => empty($inv->supplier_id));

        // ── 6. Pending Carts without Invoice ──
        $unbilledCarts = PurchaserCart::query()
            ->with(['supplier', 'items.product'])
            ->where('user_id', $purchaserId)
            ->whereNull('purchase_invoice_id')
            ->whereIn('status', ['submitted', 'pending', 'received'])
            ->whereDate('business_date', '>=', $startDate)
            ->whereDate('business_date', '<', $nextMonthDate)
            ->orderByDesc('business_date')
            ->get();

        $unbilledCartsCount = $unbilledCarts->count();
        $unbilledCartsAmount = round((float) $unbilledCarts->sum(function (PurchaserCart $cart): float {
            $itemsSum = (float) $cart->items->sum('line_total');

            return max(0.0, $itemsSum - (float) $cart->discount_amount);
        }), 2);

        // ── 7. Cash in Hand Position (Opening Cash + Funded - Returned - Used - Expenses) ──
        $closingAdvance = round($openingCash + $monthFundingAdded - $monthCashReturned - $monthAdvanceUtilized, 2);
        $closingDirection = match (true) {
            $closingAdvance > 0.0001 => 'purchaser_holds_company_cash',
            $closingAdvance < -0.0001 => 'company_owes_purchaser',
            default => 'settled',
        };
        $closingDirectionLabel = match ($closingDirection) {
            'purchaser_holds_company_cash' => 'Purchaser holds Company Cash',
            'company_owes_purchaser' => 'Company owes Purchaser',
            default => 'Settled (Zero Balance)',
        };

        // ── 8. Overall Month Status ──
        $hasPendingInventory = $pendingInventoryInvoices->isNotEmpty();
        $hasPendingBills = $unbilledCartsCount > 0;
        $hasCreditPending = $vendorCredit['credit_pending'] > 0.0001;
        $hasCashOutstanding = abs($closingAdvance) > 0.0001;

        $status = match (true) {
            $hasPendingInventory => 'Pending Inventory Match',
            $hasPendingBills => 'Pending Bills',
            $hasCashOutstanding && $hasCreditPending => 'Cash + Credit Pending',
            $hasCreditPending => 'Vendor Credit Pending',
            $hasCashOutstanding => 'Cash Outstanding',
            default => 'Settled',
        };

        $statusColor = match ($status) {
            'Settled' => 'emerald',
            'Pending Inventory Match' => 'amber',
            'Pending Bills' => 'sky',
            'Vendor Credit Pending' => 'indigo',
            'Cash + Credit Pending' => 'purple',
            'Cash Outstanding' => 'slate',
            default => 'slate',
        };

        return [
            'purchaser' => [
                'id' => $purchaserId,
                'public_uuid' => (string) $purchaser->public_uuid,
                'name' => (string) $purchaser->name,
                'email' => (string) $purchaser->email,
            ],
            'period' => [
                'month' => $range['yearMonth'],
                'label' => $range['formattedMonth'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'prev_month_end' => $prevMonthEnd,
                'next_month' => $nextMonthStr,
                'next_month_label' => $nextMonthLabel,
            ],
            'opening' => [
                'cash_balance' => abs($openingCash),
                'credit_pending' => $vendorCredit['opening_credit'],
                'cash_given_historical' => (float) ($opening['cash_given'] ?? 0.0),
                'cash_returned_historical' => (float) ($opening['cash_returned'] ?? 0.0),
                'advance_utilized_historical' => (float) ($opening['advance_utilized'] ?? 0.0),
                'raw_advance' => $openingCash,
                'direction' => $openingDirection,
                'direction_label' => $openingDirectionLabel,
            ],
            'credit' => [
                'opening_credit_pending' => $vendorCredit['opening_credit'],
                'credit_purchases' => $vendorCredit['credit_purchases'],
                'credit_settled' => $vendorCredit['credit_settled'],
                'credit_discount' => $vendorCredit['credit_discount'],
                'credit_pending' => $vendorCredit['credit_pending'],
                'direction' => $vendorCredit['direction'],
                'direction_label' => $vendorCredit['direction_label'],
                'breakdown_by_vendor' => $vendorCredit['breakdown_by_vendor'],
                'invoices' => $vendorCredit['invoices'],
            ],
            'activity' => [
                'company_funded' => $monthFundingAdded,
                'cash_returned' => $monthCashReturned,
                'net_funding' => round($monthFundingAdded - $monthCashReturned, 2),
                'total_purchases' => $totalPurchaseAmount,
                'cash_purchases' => $cashPurchaseAmount,
                'credit_purchases' => $vendorCredit['credit_purchases'],
                'cash_used' => $monthAdvanceUtilized,
                'procurement_expenses' => $monthExpensesSum,
                'expenses_by_category' => $expensesByCategory,
            ],
            'cash_details' => [
                'opening_cash' => $openingCash,
                'company_funded' => $monthFundingAdded,
                'cash_used_purchases' => $monthAdvanceUtilized,
                'cash_returned' => $monthCashReturned,
                'procurement_expenses' => $monthExpensesSum,
                'cash_in_hand' => $closingAdvance,
                'closing_cash' => $closingAdvance,
            ],
            'bills' => [
                'total_count' => $totalBillCount,
                'total_amount' => $totalPurchaseAmount,
                'cash_bills_count' => $cashInvoices->count(),
                'cash_bills_amount' => $cashPurchaseAmount,
                'credit_bills_count' => count($vendorCredit['invoices']),
                'credit_bills_amount' => $vendorCredit['credit_purchases'],
                'credit_paid_amount' => $vendorCredit['credit_settled'],
                'credit_outstanding' => $vendorCredit['credit_pending'],
                'inventory_matched_count' => $matchedInvoices->count(),
                'inventory_matched_amount' => round((float) $matchedInvoices->sum(fn ($i) => max(0.0, (float) $i->amount - (float) $i->discount_amount)), 2),
                'pending_inventory_count' => $pendingInventoryInvoices->count(),
                'pending_inventory_amount' => round((float) $pendingInventoryInvoices->sum(fn ($i) => max(0.0, (float) $i->amount - (float) $i->discount_amount)), 2),
                'unlinked_vendor_count' => $unlinkedVendorInvoices->count(),
                'cancelled_count' => $cancelledInvoices->count(),
                'cancelled_amount' => round((float) $cancelledInvoices->sum(fn ($i) => max(0.0, (float) $i->amount - (float) $i->discount_amount)), 2),
            ],
            'pending' => [
                'pending_bills_count' => $unbilledCartsCount,
                'pending_bills_amount' => $unbilledCartsAmount,
                'pending_inventory_count' => $pendingInventoryInvoices->count(),
                'pending_inventory_amount' => round((float) $pendingInventoryInvoices->sum(fn ($i) => max(0.0, (float) $i->amount - (float) $i->discount_amount)), 2),
                'credit_outstanding' => $vendorCredit['credit_pending'],
            ],
            'closing' => [
                'cash_balance' => abs($closingAdvance),
                'cash_in_hand' => abs($closingAdvance),
                'raw_advance' => $closingAdvance,
                'direction' => $closingDirection,
                'direction_label' => $closingDirectionLabel,
                'credit_pending' => $vendorCredit['credit_pending'],
            ],
            'carry_forward' => [
                'next_month' => $nextMonthStr,
                'next_month_label' => $nextMonthLabel,
                'opening_cash_balance' => abs($closingAdvance),
                'raw_advance' => $closingAdvance,
                'direction' => $closingDirection,
                'direction_label' => $closingDirectionLabel,
                'vendor_credit_outstanding' => $vendorCredit['credit_pending'],
                'pending_bills_count' => $unbilledCartsCount,
            ],
            'status' => [
                'label' => $status,
                'color' => $statusColor,
            ],
            'drilldowns' => [
                'funding' => $creditsInPeriod->map(fn ($c) => (array) $c)->all(),
                'invoices' => $activeInvoices->map(fn ($i) => (array) $i)->all(),
                'cancelled_invoices' => $cancelledInvoices->map(fn ($i) => (array) $i)->all(),
                'expenses' => $expensesInPeriod->map(fn (ProcurementExpense $e): array => [
                    'id' => $e->id,
                    'date' => $e->expense_date?->format('d M Y') ?? '',
                    'category' => $e->categoryLabel(),
                    'amount' => (float) $e->amount,
                    'note' => (string) ($e->note ?: '—'),
                    'reference' => $e->companyAccountingEntry?->reference ?? 'EXP-'.$e->id,
                ])->all(),
                'pending_carts' => $unbilledCarts->map(fn (PurchaserCart $c): array => [
                    'id' => $c->id,
                    'cart_number' => (string) $c->cart_number,
                    'date' => $c->business_date?->format('d M Y') ?? '',
                    'supplier_name' => $c->supplier?->name ?? '—',
                    'status' => (string) $c->status,
                    'amount' => max(0.0, (float) $c->items->sum('line_total') - (float) $c->discount_amount),
                ])->all(),
            ],
        ];
    }
}
