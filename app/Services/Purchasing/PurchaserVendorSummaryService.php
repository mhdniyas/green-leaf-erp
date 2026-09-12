<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PurchaserVendorSummaryService
{
    /**
     * Top summary cards for purchaser vendor spend.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     total_purchase: float,
     *     cash_purchase: float,
     *     credit_purchase: float,
     *     other_modes_purchase: float,
     *     credit_paid_by_company: float,
     *     credit_outstanding: float,
     *     bills_count: int,
     *     vendor_count: int
     * }
     */
    public function getSummary(User|int $purchaser, array $filters): array
    {
        $purchaserId = $purchaser instanceof User ? (int) $purchaser->id : (int) $purchaser;
        $baseQuery = $this->baseInvoicesQuery($purchaserId, $filters);

        $row = (clone $baseQuery)->selectRaw("
            COUNT(DISTINCT purchase_invoices.id) as bills_count,
            COUNT(DISTINCT purchase_invoices.supplier_id) as vendor_count,
            COALESCE(SUM({$this->netAmountExpression()}), 0) as total_purchase,
            COALESCE(SUM(CASE WHEN {$this->isCreditExpression()} THEN 0 WHEN {$this->isCashExpression()} THEN {$this->netAmountExpression()} ELSE 0 END), 0) as cash_purchase,
            COALESCE(SUM(CASE WHEN {$this->isCreditExpression()} THEN {$this->netAmountExpression()} ELSE 0 END), 0) as credit_purchase,
            COALESCE(SUM(CASE WHEN {$this->isCreditExpression()} THEN {$this->paidByCompanyExpression()} ELSE 0 END), 0) as credit_paid_by_company,
            COALESCE(SUM(CASE WHEN {$this->isCreditExpression()} THEN {$this->creditOutstandingExpression()} ELSE 0 END), 0) as credit_outstanding,
            COALESCE(SUM(CASE WHEN {$this->isCreditExpression()} OR {$this->isCashExpression()} THEN 0 ELSE {$this->netAmountExpression()} END), 0) as other_modes_purchase
        ")->first();

        $totalPurchase = round((float) ($row->total_purchase ?? 0), 2);
        $cashPurchase = round((float) ($row->cash_purchase ?? 0), 2);
        $creditPurchase = round((float) ($row->credit_purchase ?? 0), 2);
        $otherModesPurchase = round((float) ($row->other_modes_purchase ?? 0), 2);
        $creditPaid = round((float) ($row->credit_paid_by_company ?? 0), 2);
        $creditOutstanding = round((float) ($row->credit_outstanding ?? 0), 2);

        return [
            'total_purchase' => $totalPurchase,
            'cash_purchase' => $cashPurchase,
            'credit_purchase' => $creditPurchase,
            'other_modes_purchase' => $otherModesPurchase,
            'credit_paid_by_company' => $creditPaid,
            'credit_outstanding' => $creditOutstanding,
            'bills_count' => (int) ($row->bills_count ?? 0),
            'vendor_count' => (int) ($row->vendor_count ?? 0),
        ];
    }

    /**
     * Compute KPI summary directly from aggregated vendor rows to avoid extra queries.
     *
     * @param  Collection<int, object>  $vendorRows
     * @return array{
     *     total_purchase: float,
     *     cash_purchase: float,
     *     credit_purchase: float,
     *     other_modes_purchase: float,
     *     credit_paid_by_company: float,
     *     credit_outstanding: float,
     *     bills_count: int,
     *     vendor_count: int
     * }
     */
    public function getSummaryFromRows(Collection $vendorRows): array
    {
        return [
            'total_purchase' => round((float) $vendorRows->sum('total_purchase'), 2),
            'cash_purchase' => round((float) $vendorRows->sum('cash_purchase'), 2),
            'credit_purchase' => round((float) $vendorRows->sum('credit_purchase'), 2),
            'other_modes_purchase' => round((float) $vendorRows->sum('other_modes_purchase'), 2),
            'credit_paid_by_company' => round((float) $vendorRows->sum('paid_by_company'), 2),
            'credit_outstanding' => round((float) $vendorRows->sum('credit_outstanding'), 2),
            'bills_count' => (int) $vendorRows->sum('bills_count'),
            'vendor_count' => $vendorRows->count(),
        ];
    }

    /**
     * @return array{
     *     total_purchase: float,
     *     cash_purchase: float,
     *     credit_purchase: float,
     *     other_modes_purchase: float,
     *     credit_paid_by_company: float,
     *     credit_outstanding: float,
     *     bills_count: int,
     *     vendor_count: int
     * }
     */
    public function emptySummary(): array
    {
        return [
            'total_purchase' => 0.0,
            'cash_purchase' => 0.0,
            'credit_purchase' => 0.0,
            'other_modes_purchase' => 0.0,
            'credit_paid_by_company' => 0.0,
            'credit_outstanding' => 0.0,
            'bills_count' => 0,
            'vendor_count' => 0,
        ];
    }

    /**
     * Aggregated Vendor Summary table rows for the purchaser.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    public function getVendorRows(User|int $purchaser, array $filters): Collection
    {
        $purchaserId = $purchaser instanceof User ? (int) $purchaser->id : (int) $purchaser;
        $baseQuery = $this->baseInvoicesQuery($purchaserId, $filters);

        return (clone $baseQuery)
            ->selectRaw("
                suppliers.id as supplier_id,
                suppliers.public_uuid as supplier_public_uuid,
                suppliers.name as supplier_name,
                COUNT(DISTINCT purchase_invoices.id) as bills_count,
                COALESCE(SUM({$this->netAmountExpression()}), 0) as total_purchase,
                COALESCE(SUM(CASE WHEN {$this->isCreditExpression()} THEN 0 WHEN {$this->isCashExpression()} THEN {$this->netAmountExpression()} ELSE 0 END), 0) as cash_purchase,
                COALESCE(SUM(CASE WHEN {$this->isCreditExpression()} THEN {$this->netAmountExpression()} ELSE 0 END), 0) as credit_purchase,
                COALESCE(SUM(CASE WHEN {$this->isCreditExpression()} THEN {$this->paidByCompanyExpression()} ELSE 0 END), 0) as paid_by_company,
                COALESCE(SUM(CASE WHEN {$this->isCreditExpression()} THEN {$this->creditOutstandingExpression()} ELSE 0 END), 0) as credit_outstanding,
                COALESCE(SUM(CASE WHEN {$this->isCreditExpression()} OR {$this->isCashExpression()} THEN 0 ELSE {$this->netAmountExpression()} END), 0) as other_modes_purchase
            ")
            ->groupBy('suppliers.id', 'suppliers.public_uuid', 'suppliers.name')
            ->orderByDesc('total_purchase')
            ->get()
            ->map(function (object $row): object {
                $total = round((float) $row->total_purchase, 2);
                $cash = round((float) $row->cash_purchase, 2);
                $credit = round((float) $row->credit_purchase, 2);
                $other = round((float) $row->other_modes_purchase, 2);
                $paid = round((float) $row->paid_by_company, 2);
                $outstanding = round((float) $row->credit_outstanding, 2);

                return (object) [
                    'supplier_id' => (int) $row->supplier_id,
                    'supplier_public_uuid' => (string) $row->supplier_public_uuid,
                    'supplier_name' => (string) $row->supplier_name,
                    'bills_count' => (int) $row->bills_count,
                    'cash_purchase' => $cash,
                    'credit_purchase' => $credit,
                    'paid_by_company' => $paid,
                    'credit_outstanding' => $outstanding,
                    'other_modes_purchase' => $other,
                    'total_purchase' => $total,
                ];
            });
    }

    /**
     * Summary cards for a single vendor under the purchaser.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     supplier_id: int,
     *     supplier_public_uuid: string,
     *     supplier_name: string,
     *     total_purchase: float,
     *     cash_purchase: float,
     *     credit_purchase: float,
     *     other_modes_purchase: float,
     *     credit_paid_by_company: float,
     *     credit_outstanding: float,
     *     bills_count: int
     * }
     */
    public function getVendorDetail(User|int $purchaser, Supplier|int $vendor, array $filters): array
    {
        $purchaserId = $purchaser instanceof User ? (int) $purchaser->id : (int) $purchaser;
        $vendorId = $vendor instanceof Supplier ? (int) $vendor->id : (int) $vendor;

        $filtersWithVendor = array_merge($filters, ['vendor_id' => $vendorId]);
        $baseQuery = $this->baseInvoicesQuery($purchaserId, $filtersWithVendor);

        $row = (clone $baseQuery)->selectRaw("
            suppliers.id as supplier_id,
            suppliers.public_uuid as supplier_public_uuid,
            suppliers.name as supplier_name,
            COUNT(DISTINCT purchase_invoices.id) as bills_count,
            COALESCE(SUM({$this->netAmountExpression()}), 0) as total_purchase,
            COALESCE(SUM(CASE WHEN {$this->isCreditExpression()} THEN 0 WHEN {$this->isCashExpression()} THEN {$this->netAmountExpression()} ELSE 0 END), 0) as cash_purchase,
            COALESCE(SUM(CASE WHEN {$this->isCreditExpression()} THEN {$this->netAmountExpression()} ELSE 0 END), 0) as credit_purchase,
            COALESCE(SUM(CASE WHEN {$this->isCreditExpression()} THEN {$this->paidByCompanyExpression()} ELSE 0 END), 0) as paid_by_company,
            COALESCE(SUM(CASE WHEN {$this->isCreditExpression()} THEN {$this->creditOutstandingExpression()} ELSE 0 END), 0) as credit_outstanding,
            COALESCE(SUM(CASE WHEN {$this->isCreditExpression()} OR {$this->isCashExpression()} THEN 0 ELSE {$this->netAmountExpression()} END), 0) as other_modes_purchase
        ")->groupBy('suppliers.id', 'suppliers.public_uuid', 'suppliers.name')->first();

        $supplierModel = $vendor instanceof Supplier ? $vendor : Supplier::query()->find($vendorId);

        return [
            'supplier_id' => $vendorId,
            'supplier_public_uuid' => (string) ($row->supplier_public_uuid ?? $supplierModel?->public_uuid ?? ''),
            'supplier_name' => (string) ($row->supplier_name ?? $supplierModel?->name ?? 'Vendor'),
            'total_purchase' => round((float) ($row->total_purchase ?? 0), 2),
            'cash_purchase' => round((float) ($row->cash_purchase ?? 0), 2),
            'credit_purchase' => round((float) ($row->credit_purchase ?? 0), 2),
            'other_modes_purchase' => round((float) ($row->other_modes_purchase ?? 0), 2),
            'credit_paid_by_company' => round((float) ($row->paid_by_company ?? 0), 2),
            'credit_outstanding' => round((float) ($row->credit_outstanding ?? 0), 2),
            'bills_count' => (int) ($row->bills_count ?? 0),
        ];
    }

    /**
     * Granular invoice transactions for Purchaser → Vendor.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getVendorTransactions(User|int $purchaser, Supplier|int $vendor, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $purchaserId = $purchaser instanceof User ? (int) $purchaser->id : (int) $purchaser;
        $vendorId = $vendor instanceof Supplier ? (int) $vendor->id : (int) $vendor;

        $allocSub = DB::table('vendor_settlement_allocations')
            ->join('vendor_settlements', 'vendor_settlements.id', '=', 'vendor_settlement_allocations.vendor_settlement_id')
            ->where('vendor_settlements.status', '!=', 'cancelled')
            ->selectRaw('vendor_settlement_allocations.purchase_invoice_id, SUM(vendor_settlement_allocations.total_settled) as total_company_settled')
            ->groupBy('vendor_settlement_allocations.purchase_invoice_id');

        $query = PurchaseInvoice::query()
            ->leftJoin('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
            ->leftJoinSub($allocSub, 'alloc', 'alloc.purchase_invoice_id', '=', 'purchase_invoices.id')
            ->with([
                'vendorSettlementAllocations' => function ($allocQuery): void {
                    $allocQuery->with(['settlement.companyAccount']);
                },
                'purchaserCart',
                'supplier',
            ])
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->whereRaw('(purchaser_carts.user_id = ? OR purchase_invoices.purchaser_submitted_by = ?)', [$purchaserId, $purchaserId])
            ->where('purchase_invoices.supplier_id', $vendorId);

        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        if ($startDate) {
            $query->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) >= ?', [$startDate]);
        }
        if ($endDate) {
            $query->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) <= ?', [$endDate]);
        }

        $query->select([
            'purchase_invoices.*',
            DB::raw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) as resolved_business_date'),
            DB::raw('COALESCE(purchaser_carts.bill_number, purchase_invoices.invoice_number) as resolved_bill_number'),
            DB::raw('COALESCE(alloc.total_company_settled, 0) as total_company_settled_raw'),
        ])
            ->orderByDesc(DB::raw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at))'))
            ->orderByDesc('purchase_invoices.id');

        $paginator = $query->paginate($perPage)->withQueryString();

        $paginator->getCollection()->transform(function (PurchaseInvoice $invoice): object {
            $net = max(0.0, (float) $invoice->amount - (float) $invoice->discount_amount);
            $cartMethod = (string) ($invoice->purchaserCart?->payment_method ?? '');
            $invMethod = (string) ($invoice->payment_method ?? '');
            $paidBy = (string) ($invoice->payment_paid_by ?? '');
            $paymentStatus = (string) ($invoice->payment_status ?? '');
            $hasAllocations = $invoice->vendorSettlementAllocations->isNotEmpty();

            $isCredit = strcasecmp($cartMethod, 'credit') === 0
                || strcasecmp($invMethod, 'credit') === 0
                || $paidBy === 'vendor_credit'
                || $paymentStatus === 'credit_pending_approval'
                || $hasAllocations;

            $isCash = ! $isCredit && (strcasecmp($cartMethod, 'cash') === 0 || strcasecmp($invMethod, 'cash') === 0);

            $modeCategory = $isCredit ? 'credit' : ($isCash ? 'cash' : 'other');
            $displayMode = $isCredit ? 'Credit' : ($isCash ? 'Cash' : ($invMethod ?: $cartMethod ?: 'Other'));

            $allocSum = (float) $invoice->vendorSettlementAllocations
                ->filter(fn ($a) => ($a->settlement?->status ?? 'approved') !== 'cancelled')
                ->sum('total_settled');

            if ($isCredit) {
                $cashAmount = 0.0;
                $creditAmount = $net;
                $otherAmount = 0.0;
                $companyPaid = min($net, $allocSum);
                $outstanding = max(0.0, $creditAmount - $companyPaid);
            } elseif ($isCash) {
                $cashAmount = $net;
                $creditAmount = 0.0;
                $otherAmount = 0.0;
                $companyPaid = 0.0;
                $outstanding = 0.0;
            } else {
                $cashAmount = 0.0;
                $creditAmount = 0.0;
                $otherAmount = $net;
                $companyPaid = 0.0;
                $outstanding = 0.0;
            }

            $statusLabel = match (true) {
                $isCredit && $outstanding <= 0.009 => 'Paid',
                $isCredit && $companyPaid > 0.009 => 'Partial',
                $isCredit => 'Unpaid',
                default => 'Paid',
            };

            $settlementDetails = $invoice->vendorSettlementAllocations
                ->filter(fn ($alloc) => $alloc->settlement !== null)
                ->map(fn ($alloc): array => [
                    'settlement_id' => $alloc->settlement->id,
                    'settlement_public_uuid' => $alloc->settlement->public_uuid,
                    'payment_date' => $alloc->settlement->payment_date?->toDateString(),
                    'reference' => $alloc->settlement->reference ?: '#'.$alloc->settlement->id,
                    'payment_method' => $alloc->settlement->payment_method ?: 'Bank',
                    'company_account' => $alloc->settlement->companyAccount?->name,
                    'settlement_total_paid' => (float) $alloc->settlement->actual_payment_amount,
                    'vendor_name' => $invoice->supplier?->name ?? 'Vendor',
                    'original_bill' => $invoice->invoice_number ?: (string) $invoice->id,
                    'cash_allocated' => (float) $alloc->cash_allocated,
                    'advance_allocated' => (float) $alloc->advance_allocated,
                    'discount_allocated' => (float) $alloc->discount_allocated,
                    'allocated_amount' => (float) $alloc->total_settled,
                    'is_reversed' => $alloc->settlement->status === 'cancelled',
                    'status' => $alloc->settlement->status === 'cancelled' ? 'Reversed' : 'Active',
                ])
                ->values()
                ->all();

            return (object) [
                'id' => $invoice->id,
                'public_uuid' => (string) $invoice->public_uuid,
                'invoice_number' => (string) $invoice->invoice_number,
                'business_date' => (string) ($invoice->resolved_business_date ?? $invoice->created_at?->toDateString()),
                'bill_number' => (string) ($invoice->resolved_bill_number ?? $invoice->invoice_number),
                'purchase_amount' => $net,
                'mode_category' => $modeCategory,
                'display_mode' => $displayMode,
                'cash_amount' => $cashAmount,
                'credit_amount' => $creditAmount,
                'other_amount' => $otherAmount,
                'company_paid' => $companyPaid,
                'outstanding' => $outstanding,
                'status' => $statusLabel,
                'settlement_allocations' => $settlementDetails,
            ];
        });

        return $paginator;
    }

    /**
     * Common base invoice query joining purchaser carts, suppliers, and active allocations.
     *
     * @param  array<string, mixed>  $filters
     */
    private function baseInvoicesQuery(int $purchaserId, array $filters): Builder
    {
        $allocSub = DB::table('vendor_settlement_allocations')
            ->join('vendor_settlements', 'vendor_settlements.id', '=', 'vendor_settlement_allocations.vendor_settlement_id')
            ->where('vendor_settlements.status', '!=', 'cancelled')
            ->selectRaw('vendor_settlement_allocations.purchase_invoice_id, SUM(vendor_settlement_allocations.total_settled) as total_company_settled')
            ->groupBy('vendor_settlement_allocations.purchase_invoice_id');

        $query = DB::table('purchase_invoices')
            ->leftJoin('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
            ->leftJoinSub($allocSub, 'alloc', 'alloc.purchase_invoice_id', '=', 'purchase_invoices.id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->whereRaw('(purchaser_carts.user_id = ? OR purchase_invoices.purchaser_submitted_by = ?)', [$purchaserId, $purchaserId]);

        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        if ($startDate) {
            $query->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) >= ?', [$startDate]);
        }
        if ($endDate) {
            $query->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) <= ?', [$endDate]);
        }

        if (! empty($filters['vendor_id'])) {
            $query->where('purchase_invoices.supplier_id', (int) $filters['vendor_id']);
        }

        if (! empty($filters['payment']) && $filters['payment'] !== 'all') {
            if ($filters['payment'] === 'credit') {
                $query->whereRaw($this->isCreditExpression());
            } elseif ($filters['payment'] === 'cash') {
                $query->whereRaw($this->isCashExpression());
            }
        }

        return $query;
    }

    private function netAmountExpression(): string
    {
        return 'CASE WHEN (purchase_invoices.amount - purchase_invoices.discount_amount) > 0 THEN (purchase_invoices.amount - purchase_invoices.discount_amount) ELSE 0 END';
    }

    private function isCreditExpression(): string
    {
        return "(
            LOWER(COALESCE(purchaser_carts.payment_method, '')) = 'credit'
            OR LOWER(COALESCE(purchase_invoices.payment_method, '')) = 'credit'
            OR purchase_invoices.payment_paid_by = 'vendor_credit'
            OR purchase_invoices.payment_status = 'credit_pending_approval'
            OR alloc.total_company_settled IS NOT NULL
        )";
    }

    private function isCashExpression(): string
    {
        return "(NOT {$this->isCreditExpression()} AND LOWER(COALESCE(purchaser_carts.payment_method, purchase_invoices.payment_method, '')) = 'cash')";
    }

    private function paidByCompanyExpression(): string
    {
        $net = $this->netAmountExpression();

        return "CASE WHEN COALESCE(alloc.total_company_settled, 0) > {$net} THEN {$net} ELSE COALESCE(alloc.total_company_settled, 0) END";
    }

    private function creditOutstandingExpression(): string
    {
        $net = $this->netAmountExpression();
        $paid = $this->paidByCompanyExpression();

        return "CASE WHEN ({$net} - {$paid}) > 0 THEN ({$net} - {$paid}) ELSE 0 END";
    }
}
