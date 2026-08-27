<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\PurchaserCredit;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class PurchaserFinanceService
{
    public function cashMovementSumSubquery(string $type, string $startDate = '', string $endDate = ''): Builder
    {
        return DB::table('purchaser_credits')
            ->selectRaw('COALESCE(SUM(amount), 0)')
            ->whereColumn('purchaser_credits.purchaser_id', 'users.id')
            ->where('purchaser_credits.type', $type)
            ->when($startDate !== '', fn (Builder $query) => $query->whereDate('purchaser_credits.business_date', '>=', $startDate))
            ->when($endDate !== '', fn (Builder $query) => $query->whereDate('purchaser_credits.business_date', '<=', $endDate));
    }

    public function creditPurchasesSumSubquery(string $startDate = '', string $endDate = ''): Builder
    {
        return DB::table('purchase_invoices')
            ->leftJoin('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
            ->selectRaw('COALESCE(SUM(purchase_invoices.amount - purchase_invoices.discount_amount), 0)')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->whereRaw('(purchase_invoices.purchaser_submitted_by = users.id OR purchaser_carts.user_id = users.id)')
            ->where(function (Builder $query): void {
                $query->where('purchase_invoices.payment_method', 'Credit')
                    ->orWhere('purchase_invoices.payment_status', 'credit_pending_approval')
                    ->orWhere('purchase_invoices.payment_paid_by', 'vendor_credit');
            })
            ->when($startDate !== '', fn (Builder $query) => $query->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) >= ?', [$startDate]))
            ->when($endDate !== '', fn (Builder $query) => $query->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) <= ?', [$endDate]));
    }

    public function balanceRows(string $startDate = '', string $endDate = ''): Builder
    {
        return DB::table('purchaser_credits')
            ->selectRaw("purchaser_id, SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) as cash_given, SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END) as cash_used, SUM(CASE WHEN type = 'in' THEN amount ELSE -amount END) as remaining_advance")
            ->when($startDate !== '', fn (Builder $query) => $query->whereDate('business_date', '>=', $startDate))
            ->when($endDate !== '', fn (Builder $query) => $query->whereDate('business_date', '<=', $endDate))
            ->groupBy('purchaser_id');
    }

    /** @return array{cash_given:float,cash_used:float,remaining_advance:float,credit_purchases:float} */
    public function summaryFor(int $purchaserId): array
    {
        $balance = $this->balanceRows()->where('purchaser_id', $purchaserId)->first();
        $creditPurchases = DB::query()
            ->fromSub($this->creditPurchaseRowsQuery($purchaserId), 'credit_rows')
            ->sum('amount');

        return [
            'cash_given' => round((float) ($balance->cash_given ?? 0), 2),
            'cash_used' => round((float) ($balance->cash_used ?? 0), 2),
            'remaining_advance' => round((float) ($balance->remaining_advance ?? 0), 2),
            'credit_purchases' => round((float) $creditPurchases, 2),
        ];
    }

    /** @return array{cash_given:float,cash_used:float,remaining_advance:float,credit_purchases:float} */
    public function activityFor(int $purchaserId, string $startDate, string $endDate): array
    {
        $balance = $this->balanceRows($startDate, $endDate)->where('purchaser_id', $purchaserId)->first();
        $creditPurchases = DB::query()
            ->fromSub($this->creditPurchaseRowsQuery($purchaserId, $startDate, $endDate), 'credit_rows')
            ->sum('amount');

        return [
            'cash_given' => round((float) ($balance->cash_given ?? 0), 2),
            'cash_used' => round((float) ($balance->cash_used ?? 0), 2),
            'remaining_advance' => round((float) ($balance->remaining_advance ?? 0), 2),
            'credit_purchases' => round((float) $creditPurchases, 2),
        ];
    }

    public function splitsFor(int $purchaserId, string $startDate = '', string $endDate = '', string $search = '', string $payment = 'all'): LengthAwarePaginator
    {
        $cashSplits = DB::table('purchaser_credits')
            ->leftJoin('purchase_invoices', 'purchase_invoices.id', '=', 'purchaser_credits.purchase_invoice_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
            ->where('purchaser_credits.purchaser_id', $purchaserId)
            ->where('purchaser_credits.type', 'out')
            ->when($startDate !== '', fn (Builder $query) => $query->whereDate('purchaser_credits.business_date', '>=', $startDate))
            ->when($endDate !== '', fn (Builder $query) => $query->whereDate('purchaser_credits.business_date', '<=', $endDate))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $sub) use ($search): void {
                    $sub->where('purchaser_credits.description', 'like', '%'.$search.'%')
                        ->orWhere('purchase_invoices.invoice_number', 'like', '%'.$search.'%')
                        ->orWhere('suppliers.name', 'like', '%'.$search.'%');
                });
            })
            ->selectRaw('purchaser_credits.business_date as row_date, suppliers.public_uuid as supplier_public_uuid, COALESCE(suppliers.name, "—") as supplier_name, COALESCE(purchase_invoices.invoice_number, purchaser_credits.description, "Advance Utilization") as invoice_number, "Cash" as payment_type, purchaser_credits.amount as amount, purchaser_credits.description as movement_reference, COALESCE(purchase_invoices.payment_status, "advance_utilized") as status, purchase_invoices.id as invoice_id, purchaser_credits.id as purchaser_credit_id');

        $creditSplits = DB::table('purchase_invoices')
            ->leftJoin('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->where(function (Builder $query) use ($purchaserId): void {
                $query->where('purchase_invoices.purchaser_submitted_by', $purchaserId)
                    ->orWhere('purchaser_carts.user_id', $purchaserId);
            })
            ->where(function (Builder $query): void {
                $query->where('purchase_invoices.payment_method', 'Credit')
                    ->orWhere('purchase_invoices.payment_status', 'credit_pending_approval')
                    ->orWhere('purchase_invoices.payment_paid_by', 'vendor_credit');
            })
            ->when($startDate !== '', fn (Builder $query) => $query->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) >= ?', [$startDate]))
            ->when($endDate !== '', fn (Builder $query) => $query->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) <= ?', [$endDate]))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $sub) use ($search): void {
                    $sub->where('purchase_invoices.invoice_number', 'like', '%'.$search.'%')
                        ->orWhere('purchase_invoices.payment_note', 'like', '%'.$search.'%')
                        ->orWhere('purchase_invoices.payment_details', 'like', '%'.$search.'%')
                        ->orWhere('suppliers.name', 'like', '%'.$search.'%');
                });
            })
            ->selectRaw('COALESCE(purchaser_carts.business_date, DATE(purchase_invoices.created_at)) as row_date, suppliers.public_uuid as supplier_public_uuid, COALESCE(suppliers.name, "—") as supplier_name, purchase_invoices.invoice_number as invoice_number, "Credit" as payment_type, (purchase_invoices.amount - purchase_invoices.discount_amount) as amount, COALESCE(purchase_invoices.payment_note, purchase_invoices.payment_details, "Vendor Credit") as movement_reference, purchase_invoices.payment_status as status, purchase_invoices.id as invoice_id, NULL as purchaser_credit_id');

        return DB::query()
            ->fromSub($cashSplits->unionAll($creditSplits), 'purchaser_splits')
            ->when($payment !== 'all', fn (Builder $query) => $query->where('payment_type', ucfirst($payment)))
            ->orderByDesc('row_date')
            ->orderByDesc('invoice_id')
            ->paginate(25)
            ->withQueryString();
    }

    /** @return array{reconciled_amount:float,pending_reconciliation:float} */
    public function reconciliationFor(int $purchaserId): array
    {
        $row = DB::table('purchaser_credits as credits')
            ->leftJoin('cashbook_company_account_statement_entries as statements', function ($join): void {
                $join->on('statements.source_id', '=', 'credits.id')
                    ->where('statements.source_type', PurchaserCredit::class);
            })
            ->where('credits.purchaser_id', $purchaserId)
            ->where('credits.type', 'in')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(statements.matched_amount, 0) > credits.amount THEN credits.amount ELSE COALESCE(statements.matched_amount, 0) END), 0) as reconciled_amount, COALESCE(SUM(credits.amount), 0) as funding_amount')
            ->first();

        $reconciled = round((float) ($row->reconciled_amount ?? 0), 2);

        return [
            'reconciled_amount' => $reconciled,
            'pending_reconciliation' => round(max(0, (float) ($row->funding_amount ?? 0) - $reconciled), 2),
        ];
    }

    public function transactionsFor(int $purchaserId, string $startDate, string $endDate): LengthAwarePaginator
    {
        return DB::table('purchaser_credits as credits')
            ->leftJoin('cashbook_company_accounts as accounts', 'accounts.id', '=', 'credits.company_account_id')
            ->leftJoin('purchase_invoices as invoices', 'invoices.id', '=', 'credits.purchase_invoice_id')
            ->leftJoin('cashbook_company_account_statement_entries as statements', function ($join): void {
                $join->on('statements.source_id', '=', 'credits.id')
                    ->where('statements.source_type', PurchaserCredit::class);
            })
            ->where('credits.purchaser_id', $purchaserId)
            ->whereDate('credits.business_date', '>=', $startDate)
            ->whereDate('credits.business_date', '<=', $endDate)
            ->selectRaw("credits.id, credits.business_date, CASE WHEN credits.type = 'in' THEN 'Purchaser Funding' ELSE 'Cash Used' END as movement_type, credits.type, credits.amount, accounts.name as company_account, credits.payment_source, COALESCE(credits.reference, invoices.invoice_number, credits.description) as movement_reference, CASE WHEN credits.type = 'out' THEN 'advance_utilized' WHEN statements.id IS NULL THEN 'pending_reconciliation' ELSE statements.status END as status")
            ->orderByDesc('credits.business_date')
            ->orderByDesc('credits.id')
            ->paginate(20, ['*'], 'finance_page')
            ->withQueryString();
    }

    private function creditPurchaseRowsQuery(int $purchaserId, string $startDate = '', string $endDate = ''): Builder
    {
        return DB::table('purchase_invoices')
            ->leftJoin('purchaser_carts', 'purchaser_carts.id', '=', 'purchase_invoices.purchaser_cart_id')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->where(function (Builder $query) use ($purchaserId): void {
                $query->where('purchase_invoices.purchaser_submitted_by', $purchaserId)
                    ->orWhere('purchaser_carts.user_id', $purchaserId);
            })
            ->where(function (Builder $query): void {
                $query->where('purchase_invoices.payment_method', 'Credit')
                    ->orWhere('purchase_invoices.payment_status', 'credit_pending_approval')
                    ->orWhere('purchase_invoices.payment_paid_by', 'vendor_credit');
            })
            ->when($startDate !== '', fn (Builder $query) => $query->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) >= ?', [$startDate]))
            ->when($endDate !== '', fn (Builder $query) => $query->whereRaw('COALESCE(DATE(purchaser_carts.business_date), DATE(purchase_invoices.created_at)) <= ?', [$endDate]))
            ->selectRaw('(purchase_invoices.amount - purchase_invoices.discount_amount) as amount');
    }
}
