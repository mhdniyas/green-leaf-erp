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
            'company_funded' => 0.0,
            'cash_returned' => 0.0,
            'total_purchases' => 0.0,
            'cash_purchases' => 0.0,
            'credit_purchases' => 0.0,
            'cash_used' => 0.0,
            'procurement_expenses' => 0.0,
            'closing_cash_net' => 0.0,
            'vendor_credit_outstanding' => 0.0,
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

            $rows[] = [
                'purchaser_id' => (int) $purchaser->id,
                'public_uuid' => (string) $purchaser->public_uuid,
                'name' => (string) $purchaser->name,
                'email' => (string) $purchaser->email,
                'opening' => $detail['opening'],
                'activity' => $detail['activity'],
                'cash_details' => $detail['cash_details'],
                'bills' => $detail['bills'],
                'closing' => $detail['closing'],
                'pending' => $detail['pending'],
                'status' => $detail['status'],
            ];

            $totals['opening_cash_net'] += $openingNet;
            $totals['company_funded'] += (float) ($detail['activity']['company_funded'] ?? 0.0);
            $totals['cash_returned'] += (float) ($detail['activity']['cash_returned'] ?? 0.0);
            $totals['total_purchases'] += (float) ($detail['activity']['total_purchases'] ?? 0.0);
            $totals['cash_purchases'] += (float) ($detail['activity']['cash_purchases'] ?? 0.0);
            $totals['credit_purchases'] += (float) ($detail['activity']['credit_purchases'] ?? 0.0);
            $totals['cash_used'] += (float) ($detail['activity']['cash_used'] ?? 0.0);
            $totals['procurement_expenses'] += (float) ($detail['activity']['procurement_expenses'] ?? 0.0);
            $totals['closing_cash_net'] += $closingNet;
            $totals['vendor_credit_outstanding'] += (float) ($detail['bills']['credit_outstanding'] ?? 0.0);
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

        // ── 1. Opening Position prior to startDate (Historical Cumulative Ledger) ──
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

        // ── 2. Month Cash & Funding Activity (business_date in [startDate, nextMonthDate)) ──
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

        // ── 3. Procurement Expenses in Period ──
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

        // ── 4. Purchase Invoices in Period (Attributed by Business Date) ──
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
                    WHEN LOWER(COALESCE(purchase_invoices.payment_method, purchaser_carts.payment_method, "")) = "credit"
                         OR purchase_invoices.payment_paid_by = "vendor_credit"
                         OR purchase_invoices.payment_status = "credit_pending_approval"
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
        $creditInvoices = $activeInvoices->filter(fn ($inv) => $inv->payment_class === 'credit');

        $cashPurchaseAmount = round((float) $cashInvoices->sum(fn ($inv) => max(0.0, (float) $inv->amount - (float) $inv->discount_amount)), 2);
        $creditPurchaseAmount = round((float) $creditInvoices->sum(fn ($inv) => max(0.0, (float) $inv->amount - (float) $inv->discount_amount)), 2);
        $creditPaidAmount = round((float) $creditInvoices->sum('paid_amount'), 2);
        $creditOutstanding = round(max(0.0, $creditPurchaseAmount - $creditPaidAmount), 2);

        $matchedInvoices = $activeInvoices->filter(fn ($inv) => ! empty($inv->goods_received_id));
        $pendingInventoryInvoices = $activeInvoices->filter(fn ($inv) => empty($inv->goods_received_id));
        $unlinkedVendorInvoices = $activeInvoices->filter(fn ($inv) => empty($inv->supplier_id));

        // ── 5. Pending Carts without Invoice ──
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

        // ── 6. Closing Advance / Cash Position ──
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

        // ── 7. Overall Month Status ──
        $status = match (true) {
            $pendingInventoryInvoices->isNotEmpty() => 'Pending Inventory Match',
            $unbilledCartsCount > 0 => 'Pending Bills',
            $creditOutstanding > 0.0001 => 'Vendor Credit Pending',
            abs($closingAdvance) > 0.0001 => 'Cash Outstanding',
            default => 'Complete',
        };

        $statusColor = match ($status) {
            'Complete' => 'emerald',
            'Pending Inventory Match' => 'amber',
            'Pending Bills' => 'sky',
            'Vendor Credit Pending' => 'indigo',
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
                'cash_given_historical' => (float) ($opening['cash_given'] ?? 0.0),
                'cash_returned_historical' => (float) ($opening['cash_returned'] ?? 0.0),
                'advance_utilized_historical' => (float) ($opening['advance_utilized'] ?? 0.0),
                'raw_advance' => $openingCash,
                'direction' => $openingDirection,
                'direction_label' => $openingDirectionLabel,
            ],
            'activity' => [
                'company_funded' => $monthFundingAdded,
                'cash_returned' => $monthCashReturned,
                'net_funding' => round($monthFundingAdded - $monthCashReturned, 2),
                'total_purchases' => $totalPurchaseAmount,
                'cash_purchases' => $cashPurchaseAmount,
                'credit_purchases' => $creditPurchaseAmount,
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
                'closing_cash' => $closingAdvance,
            ],
            'bills' => [
                'total_count' => $totalBillCount,
                'total_amount' => $totalPurchaseAmount,
                'cash_bills_count' => $cashInvoices->count(),
                'cash_bills_amount' => $cashPurchaseAmount,
                'credit_bills_count' => $creditInvoices->count(),
                'credit_bills_amount' => $creditPurchaseAmount,
                'credit_paid_amount' => $creditPaidAmount,
                'credit_outstanding' => $creditOutstanding,
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
                'credit_outstanding' => $creditOutstanding,
            ],
            'closing' => [
                'cash_balance' => abs($closingAdvance),
                'raw_advance' => $closingAdvance,
                'direction' => $closingDirection,
                'direction_label' => $closingDirectionLabel,
            ],
            'carry_forward' => [
                'next_month' => $nextMonthStr,
                'next_month_label' => $nextMonthLabel,
                'opening_cash_balance' => abs($closingAdvance),
                'raw_advance' => $closingAdvance,
                'direction' => $closingDirection,
                'direction_label' => $closingDirectionLabel,
                'vendor_credit_outstanding' => $creditOutstanding,
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
