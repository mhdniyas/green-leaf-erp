<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoicePayment;
use App\Models\Supplier;
use App\Models\VendorAdvance;
use App\Models\VendorSettlement;
use App\Models\VendorSettlementAllocation;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorSettlementService
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly CompanyPaymentReconciliationService $reconciliationService,
    ) {}

    /** @param array{actual_payment_amount:float,settlement_discount_amount:float,vendor_advance_used_amount:float,payment_date:string,payment_method:?string,company_account_id:?int,statement_entry_id?:int,reference:?string,note:?string,allocations:array<int,array{purchase_invoice_id:int,cash_allocated:float,advance_allocated:float,discount_allocated:float}>} $payload */
    public function create(Supplier $supplier, array $payload, int $userId): VendorSettlement
    {
        return DB::transaction(function () use ($supplier, $payload, $userId): VendorSettlement {
            $supplier = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            $allocations = collect($payload['allocations'])->filter(fn (array $row): bool => round((float) $row['cash_allocated'] + (float) $row['advance_allocated'] + (float) $row['discount_allocated'], 2) > 0);
            $invoiceIds = $allocations->pluck('purchase_invoice_id')->map(fn ($id): int => (int) $id)->all();

            if ($invoiceIds === [] || count($invoiceIds) !== count(array_unique($invoiceIds))) {
                throw ValidationException::withMessages(['allocations' => 'Select each supplier invoice only once.']);
            }

            $invoices = PurchaseInvoice::query()
                ->whereIn('id', $invoiceIds)
                ->where('supplier_id', $supplier->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($invoices->count() !== count($invoiceIds)) {
                throw ValidationException::withMessages(['allocations' => 'Every allocated invoice must belong to this supplier.']);
            }

            $cash = round((float) $payload['actual_payment_amount'], 2);
            $selectedStatement = null;
            if (! empty($payload['statement_entry_id'])) {
                $selectedStatement = CompanyAccountStatementEntry::query()
                    ->whereKey((int) $payload['statement_entry_id'])
                    ->where('company_account_id', (int) $payload['company_account_id'])
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($selectedStatement->direction !== 'out' || $selectedStatement->is_finalized || $selectedStatement->journal_entry_id !== null) {
                    throw ValidationException::withMessages(['statement_entry_id' => 'Statement transaction is unavailable for vendor settlement.']);
                }
                $cash = round((float) $selectedStatement->amount, 2);
                $payload['payment_date'] = $selectedStatement->transaction_date->toDateString();
                $payload['reference'] = $selectedStatement->reference;
            }
            $discount = round((float) $payload['settlement_discount_amount'], 2);
            $advanceUsed = round((float) $payload['vendor_advance_used_amount'], 2);
            $totalCash = round((float) $allocations->sum('cash_allocated'), 2);
            $totalAdvance = round((float) $allocations->sum('advance_allocated'), 2);
            $totalDiscount = round((float) $allocations->sum('discount_allocated'), 2);
            $settled = round($totalCash + $totalAdvance + $totalDiscount, 2);
            $newAdvance = round($cash + $discount + $advanceUsed - $settled, 2);

            if ($cash < 0 || $discount < 0 || $advanceUsed < 0 || $newAdvance < -0.01 || $totalCash > $cash + 0.01 || abs($discount - $totalDiscount) > 0.01 || abs($advanceUsed - $totalAdvance) > 0.01 || abs($newAdvance - ($cash - $totalCash)) > 0.01) {
                throw ValidationException::withMessages(['allocations' => 'Payment, discount, and advance allocations must balance exactly.']);
            }
            $newAdvance = max(0, $newAdvance);

            $openAdvances = VendorAdvance::query()->where('supplier_id', $supplier->id)->where('amount_remaining', '>', 0)->orderBy('id')->lockForUpdate()->get();
            if ($advanceUsed > round((float) $openAdvances->sum('amount_remaining'), 2) + 0.01) {
                throw ValidationException::withMessages(['vendor_advance_used_amount' => 'Vendor advance is no longer available.']);
            }

            foreach ($allocations as $row) {
                $invoice = $invoices->get((int) $row['purchase_invoice_id']);
                $allocated = round((float) $row['cash_allocated'] + (float) $row['advance_allocated'] + (float) $row['discount_allocated'], 2);
                $existing = round((float) VendorSettlementAllocation::query()->where('purchase_invoice_id', $invoice->id)->sum('total_settled'), 2);
                $outstanding = round(max(0, ((float) $invoice->amount - (float) $invoice->discount_amount) - $existing), 2);
                if ($allocated > $outstanding + 0.01) {
                    throw ValidationException::withMessages(['allocations' => "Invoice {$invoice->invoice_number} has only ₹{$outstanding} outstanding."]);
                }
            }

            $companyAccount = $cash > 0 && ! empty($payload['company_account_id'])
                ? CompanyAccount::query()->whereKey((int) $payload['company_account_id'])->where('enabled', true)->lockForUpdate()->firstOrFail()
                : null;

            $settlement = VendorSettlement::query()->create([
                'supplier_id' => $supplier->id, 'actual_payment_amount' => $cash, 'settlement_discount_amount' => $discount,
                'vendor_advance_used_amount' => $advanceUsed, 'new_vendor_advance_amount' => $newAdvance,
                'company_account_id' => $companyAccount?->id, 'payment_method' => $payload['payment_method'], 'payment_date' => $payload['payment_date'],
                'reference' => $payload['reference'], 'note' => $payload['note'], 'status' => 'approved',
                'reconciliation_status' => $cash > 0 ? 'unreconciled' : 'not_required', 'is_finalized' => $cash <= 0, 'finalized_at' => $cash <= 0 ? now() : null, 'created_by' => $userId,
            ]);

            foreach ($allocations as $row) {
                $invoice = $invoices->get((int) $row['purchase_invoice_id']);
                $cashAllocation = round((float) $row['cash_allocated'], 2);
                $allocation = VendorSettlementAllocation::query()->create([
                    'vendor_settlement_id' => $settlement->id, 'purchase_invoice_id' => $invoice->id,
                    'cash_allocated' => $cashAllocation, 'advance_allocated' => round((float) $row['advance_allocated'], 2),
                    'discount_allocated' => round((float) $row['discount_allocated'], 2), 'total_settled' => round($cashAllocation + (float) $row['advance_allocated'] + (float) $row['discount_allocated'], 2),
                ]);
                if ($cashAllocation > 0) {
                    PurchaseInvoicePayment::query()->create(['purchase_invoice_id' => $invoice->id, 'supplier_id' => $supplier->id, 'payment_date' => $payload['payment_date'], 'amount' => $cashAllocation, 'discount_amount' => 0, 'payment_method' => $payload['payment_method'], 'payment_paid_by' => 'company', 'note' => $payload['note'], 'created_by' => $userId]);
                }
                $settledForInvoice = round((float) VendorSettlementAllocation::query()->where('purchase_invoice_id', $invoice->id)->sum('total_settled'), 2);
                $net = round((float) $invoice->amount - (float) $invoice->discount_amount, 2);
                $invoice->update(['paid_amount' => round((float) $invoice->paid_amount + $cashAllocation, 2), 'payment_method' => $payload['payment_method'] ?? $invoice->payment_method, 'payment_paid_by' => 'company', 'payment_status' => $settledForInvoice >= $net - 0.01 ? 'paid' : ($settledForInvoice > 0 ? 'partial' : 'unpaid')]);
            }

            $remainingAdvanceUse = $advanceUsed;
            foreach ($openAdvances as $advance) {
                $used = min($remainingAdvanceUse, round((float) $advance->amount_remaining, 2));
                if ($used <= 0) {
                    break;
                }
                $advance->update(['amount_remaining' => round((float) $advance->amount_remaining - $used, 2), 'status' => round((float) $advance->amount_remaining - $used, 2) <= 0.01 ? 'used' : 'open']);
                $remainingAdvanceUse = round($remainingAdvanceUse - $used, 2);
            }

            $journal = $this->journalService->recordVendorSettlement($settlement, $userId);
            $settlement->update(['journal_entry_id' => $journal->id]);
            if ($newAdvance > 0) {
                VendorAdvance::query()->create(['supplier_id' => $supplier->id, 'source_settlement_id' => $settlement->id, 'amount_original' => $newAdvance, 'amount_remaining' => $newAdvance, 'business_date' => $payload['payment_date'], 'status' => 'open', 'journal_entry_id' => $journal->id, 'created_by' => $userId]);
            }
            if ($cash > 0 && $companyAccount instanceof CompanyAccount) {
                $this->reconciliationService->finalizeVendorSettlementMovement($settlement, $journal, [
                    'company_account_id' => $companyAccount->id,
                    'statement_entry_id' => $selectedStatement?->id,
                    'transaction_date' => $payload['payment_date'],
                    'reference' => $payload['reference'] ?: 'VENDOR-SETTLEMENT-'.$settlement->id,
                    'narration' => 'Vendor settlement for '.$supplier->name,
                    'notes' => $payload['note'],
                ], $userId);
            }

            return $settlement->fresh(['allocations.purchaseInvoice', 'journalEntry.transactions.account', 'advances']);
        }, attempts: 3);
    }

    /**
     * @param  array{invoice_ids:array<int,int>,actual_payment_amount:float,use_vendor_advance:bool,difference_treatment:string,allocation_order:string,payment_date:string,payment_method:?string,company_account_id:?int,reference:?string,note:?string}  $payload
     */
    public function createAutomatic(Supplier $supplier, array $payload, int $userId): VendorSettlement
    {
        return DB::transaction(function () use ($supplier, $payload, $userId): VendorSettlement {
            if (! empty($payload['statement_entry_id'])) {
                $statement = CompanyAccountStatementEntry::query()
                    ->whereKey((int) $payload['statement_entry_id'])
                    ->where('company_account_id', (int) $payload['company_account_id'])
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($statement->direction !== 'out' || $statement->is_finalized || $statement->journal_entry_id !== null) {
                    throw ValidationException::withMessages(['statement_entry_id' => 'Statement transaction is unavailable for vendor settlement.']);
                }
                $payload['actual_payment_amount'] = (float) $statement->amount;
                $payload['payment_date'] = $statement->transaction_date->toDateString();
                $payload['reference'] = $statement->reference;
            }
            $invoiceIds = array_values(array_unique(array_map('intval', $payload['invoice_ids'])));
            if ($invoiceIds === []) {
                throw ValidationException::withMessages(['invoice_ids' => 'Select at least one outstanding invoice.']);
            }

            $invoices = PurchaseInvoice::query()
                ->whereIn('id', $invoiceIds)
                ->where('supplier_id', $supplier->id)
                ->lockForUpdate()
                ->get()
                ->map(function (PurchaseInvoice $invoice): array {
                    $settled = round((float) VendorSettlementAllocation::query()
                        ->where('purchase_invoice_id', $invoice->id)
                        ->sum('total_settled'), 2);

                    return [
                        'invoice' => $invoice,
                        'outstanding' => round(max(0, (float) $invoice->amount - (float) $invoice->discount_amount - $settled), 2),
                    ];
                })
                ->filter(fn (array $row): bool => $row['outstanding'] > 0.01)
                ->sortBy(fn (array $row): mixed => $payload['allocation_order'] === 'newest' ? -$row['invoice']->id : $row['invoice']->id)
                ->values();

            if ($invoices->count() !== count($invoiceIds)) {
                throw ValidationException::withMessages(['invoice_ids' => 'Selected invoices must belong to supplier and remain outstanding.']);
            }

            $cash = round((float) $payload['actual_payment_amount'], 2);
            if ($cash < 0) {
                throw ValidationException::withMessages(['actual_payment_amount' => 'Payment amount cannot be negative.']);
            }

            $advanceAvailable = round((float) VendorAdvance::query()
                ->where('supplier_id', $supplier->id)
                ->where('amount_remaining', '>', 0)
                ->lockForUpdate()
                ->sum('amount_remaining'), 2);
            $selectedTotal = round((float) $invoices->sum('outstanding'), 2);
            $advance = $payload['use_vendor_advance'] ? min($advanceAvailable, $selectedTotal) : 0.0;
            $cashForInvoices = min($cash, round($selectedTotal - $advance, 2));
            $difference = round(max(0, $selectedTotal - $advance - $cashForInvoices), 2);
            $discount = $payload['difference_treatment'] === 'discount' ? $difference : 0.0;

            $cashRemaining = $cashForInvoices;
            $advanceRemaining = $advance;
            $discountRemaining = $discount;
            $allocations = [];
            foreach ($invoices as $row) {
                $outstanding = $row['outstanding'];
                $cashAllocated = min($cashRemaining, $outstanding);
                $cashRemaining = round($cashRemaining - $cashAllocated, 2);
                $afterCash = round($outstanding - $cashAllocated, 2);
                $advanceAllocated = min($advanceRemaining, $afterCash);
                $advanceRemaining = round($advanceRemaining - $advanceAllocated, 2);
                $afterAdvance = round($afterCash - $advanceAllocated, 2);
                $discountAllocated = min($discountRemaining, $afterAdvance);
                $discountRemaining = round($discountRemaining - $discountAllocated, 2);

                $allocations[] = [
                    'purchase_invoice_id' => $row['invoice']->id,
                    'cash_allocated' => $cashAllocated,
                    'advance_allocated' => $advanceAllocated,
                    'discount_allocated' => $discountAllocated,
                ];
            }

            return $this->create($supplier, [
                'actual_payment_amount' => $cash,
                'settlement_discount_amount' => $discount,
                'vendor_advance_used_amount' => $advance,
                'payment_date' => $payload['payment_date'],
                'payment_method' => $payload['payment_method'],
                'company_account_id' => $payload['company_account_id'],
                'statement_entry_id' => $payload['statement_entry_id'] ?? null,
                'reference' => $payload['reference'],
                'note' => $payload['note'],
                'allocations' => $allocations,
            ], $userId);
        }, attempts: 3);
    }
}
