<?php

declare(strict_types=1);

namespace App\Services\Cashbook\CashFlow\Sources;

use App\DTOs\Cashbook\MoneyMovement;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\VendorSettlement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class VendorCreditCashFlowSource implements CashFlowSourceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, MoneyMovement>
     */
    public function forMonth(string $month, array $filters = []): Collection
    {
        $start = Carbon::parse($month.'-01')->startOfMonth()->toDateString();
        $end = Carbon::parse($month.'-01')->endOfMonth()->toDateString();

        $movements = collect();

        // 1. New Credit Bills recorded this month
        $invoicesQuery = PurchaseInvoice::query()
            ->with(['supplier', 'purchaserCart'])
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelled')
            ->where(function (Builder $q): void {
                $q->whereRaw("LOWER(COALESCE(payment_method, '')) = 'credit'")
                    ->orWhere('payment_paid_by', 'vendor_credit')
                    ->orWhere('payment_status', 'credit_pending_approval')
                    ->orWhereExists(function ($allocQ): void {
                        $allocQ->selectRaw('1')
                            ->from('vendor_settlement_allocations')
                            ->whereColumn('vendor_settlement_allocations.purchase_invoice_id', 'purchase_invoices.id');
                    });
            })
            ->whereRaw('COALESCE(DATE(purchase_invoices.created_at), DATE(purchase_invoices.updated_at)) BETWEEN ? AND ?', [$start, $end]);

        if (! empty($filters['vendor_id'])) {
            $invoicesQuery->where('supplier_id', (int) $filters['vendor_id']);
        }

        $creditInvoices = $invoicesQuery->get();

        foreach ($creditInvoices as $inv) {
            $supplier = $inv->supplier;
            $supplierName = $supplier?->name ?? 'Unlinked Vendor';
            $supplierId = $supplier?->id;
            $netAmount = round((float) $inv->amount - (float) $inv->discount_amount, 2);
            $date = $inv->created_at ? $inv->created_at->toDateString() : $start;

            if ($netAmount <= 0) {
                continue;
            }

            $movements->push(new MoneyMovement(
                date: $date,
                sourceType: 'vendor_credit',
                sourceId: $inv->id,
                fromEntityType: 'vendor',
                fromEntityId: $supplierId,
                fromEntityName: $supplierName,
                toEntityType: 'credit_payable',
                toEntityId: null,
                toEntityName: 'Accounts Payable',
                amount: $netAmount,
                movementType: 'vendor_credit_bill',
                category: 'credit_bill',
                referenceType: 'purchase_invoice',
                referenceId: $inv->id,
                referenceNumber: $inv->invoice_number,
                notes: 'Credit purchase bill #'.$inv->invoice_number,
                metadata: [
                    'supplier_id' => $supplierId,
                    'invoice_id' => $inv->id,
                ]
            ));
        }

        // 2. Company settlements / payments against vendor credit
        $settlementsQuery = VendorSettlement::query()
            ->with(['supplier', 'companyAccount', 'allocations'])
            ->whereBetween('payment_date', [$start, $end]);

        if (! empty($filters['vendor_id'])) {
            $settlementsQuery->where('supplier_id', (int) $filters['vendor_id']);
        }

        $settlements = $settlementsQuery->get();

        foreach ($settlements as $settlement) {
            $supplier = $settlement->supplier;
            $supplierName = $supplier?->name ?? 'Vendor #'.$settlement->supplier_id;
            $supplierId = (int) $settlement->supplier_id;
            $date = $settlement->payment_date ? $settlement->payment_date->toDateString() : $start;

            $cashPaid = (float) $settlement->actual_payment_amount;
            $discount = (float) $settlement->settlement_discount_amount;

            // Scenario A: Company Account pays Vendor
            if ($cashPaid > 0) {
                $companyAccount = $settlement->companyAccount;
                $fromEntityName = $companyAccount
                    ? ($companyAccount->name ?: ($companyAccount->bank_name ?: 'Company Account'))
                    : 'Company Cash Vault';
                $fromEntityId = $companyAccount?->id;

                $movements->push(new MoneyMovement(
                    date: $date,
                    sourceType: 'vendor_credit',
                    sourceId: $settlement->id,
                    fromEntityType: $companyAccount ? 'company_bank' : 'company_vault',
                    fromEntityId: $fromEntityId,
                    fromEntityName: $fromEntityName,
                    toEntityType: 'vendor',
                    toEntityId: $supplierId,
                    toEntityName: $supplierName,
                    amount: $cashPaid,
                    movementType: 'vendor_credit_payment',
                    category: 'settlement',
                    referenceType: 'vendor_settlement',
                    referenceId: $settlement->id,
                    referenceNumber: $settlement->reference,
                    notes: $settlement->note ?: "Vendor settlement to {$supplierName}",
                    metadata: [
                        'supplier_id' => $supplierId,
                        'company_account_id' => $fromEntityId,
                    ]
                ));
            }

            // Scenario B: Settlement discount negotiated
            if ($discount > 0) {
                $movements->push(new MoneyMovement(
                    date: $date,
                    sourceType: 'vendor_credit',
                    sourceId: $settlement->id,
                    fromEntityType: 'vendor',
                    fromEntityId: $supplierId,
                    fromEntityName: $supplierName,
                    toEntityType: 'settlement_discount',
                    toEntityId: null,
                    toEntityName: 'Settlement Discount (Non-cash)',
                    amount: $discount,
                    movementType: 'settlement_discount',
                    category: 'discount',
                    referenceType: 'vendor_settlement',
                    referenceId: $settlement->id,
                    referenceNumber: $settlement->reference,
                    notes: "Discount granted by {$supplierName}",
                    metadata: [
                        'supplier_id' => $supplierId,
                    ]
                ));
            }
        }

        return $movements;
    }

    /**
     * Compute opening credit payable outstanding prior to startDate.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function openingBalances(string $startDate, array $filters = []): array
    {
        // 1. Total credit bills before startDate
        $bills = DB::table('purchase_invoices')
            ->whereNull('purchase_invoices.deleted_at')
            ->where('purchase_invoices.status', '!=', 'cancelled')
            ->where(function ($q): void {
                $q->whereRaw("LOWER(COALESCE(purchase_invoices.payment_method, '')) = 'credit'")
                    ->orWhere('purchase_invoices.payment_paid_by', 'vendor_credit')
                    ->orWhere('purchase_invoices.payment_status', 'credit_pending_approval')
                    ->orWhereExists(function ($allocQ): void {
                        $allocQ->selectRaw('1')
                            ->from('vendor_settlement_allocations')
                            ->whereColumn('vendor_settlement_allocations.purchase_invoice_id', 'purchase_invoices.id');
                    });
            })
            ->whereRaw('COALESCE(DATE(purchase_invoices.created_at), DATE(purchase_invoices.updated_at)) < ?', [$startDate])
            ->when(! empty($filters['vendor_id']), fn ($q) => $q->where('purchase_invoices.supplier_id', (int) $filters['vendor_id']))
            ->groupBy('purchase_invoices.supplier_id')
            ->selectRaw('purchase_invoices.supplier_id, SUM(purchase_invoices.amount - purchase_invoices.discount_amount) as total_billed')
            ->get()
            ->keyBy('supplier_id');

        // 2. Total settled before startDate
        $settled = DB::table('vendor_settlements')
            ->whereDate('payment_date', '<', $startDate)
            ->when(! empty($filters['vendor_id']), fn ($q) => $q->where('supplier_id', (int) $filters['vendor_id']))
            ->groupBy('supplier_id')
            ->selectRaw('supplier_id, SUM(actual_payment_amount + settlement_discount_amount) as total_settled')
            ->get()
            ->keyBy('supplier_id');

        $suppliers = Supplier::query()
            ->when(! empty($filters['vendor_id']), fn ($q) => $q->where('id', (int) $filters['vendor_id']))
            ->get();

        $balances = [];
        foreach ($suppliers as $supplier) {
            $billedAmt = (float) ($bills->get($supplier->id)?->total_billed ?? 0);
            $settledAmt = (float) ($settled->get($supplier->id)?->total_settled ?? 0);
            $outstanding = max(0.0, round($billedAmt - $settledAmt, 2));

            if ($billedAmt > 0 || $settledAmt > 0) {
                $balances[$supplier->id] = [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'total_billed_prior' => round($billedAmt, 2),
                    'total_settled_prior' => round($settledAmt, 2),
                    'opening_payable' => $outstanding,
                ];
            }
        }

        return $balances;
    }
}
