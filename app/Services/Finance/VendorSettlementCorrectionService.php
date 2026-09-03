<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Cashbook\CompanyAccountStatementEntry;
use App\Models\JournalEntry;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoicePayment;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorAdvance;
use App\Models\VendorSettlement;
use App\Models\VendorSettlementAllocation;
use App\Services\Cashbook\CompanyPaymentReconciliationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class VendorSettlementCorrectionService
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly CompanyPaymentReconciliationService $companyPaymentReconciliationService,
        private readonly VendorSettlementService $vendorSettlementService
    ) {}

    /**
     * Preview reversal impact before an admin edits or deletes a settlement entry.
     *
     * @return array{affected_bills_count: int, total_settled_amount: float, cash_amount: float, discount_amount: float, advance_amount: float}
     */
    public function reversalPreview(VendorSettlement $settlement): array
    {
        $allocations = $settlement->allocations;

        return [
            'affected_bills_count' => $allocations->count(),
            'total_settled_amount' => (float) $allocations->sum('total_settled'),
            'cash_amount' => (float) $settlement->actual_payment_amount,
            'discount_amount' => (float) $settlement->settlement_discount_amount,
            'advance_amount' => (float) $settlement->vendor_advance_used_amount,
        ];
    }

    /**
     * Atomically delete/void a vendor settlement entry, reversing allocations, vendor advances, statement reconciliations, and journal entries.
     */
    public function deleteSettlementWithReversal(
        VendorSettlement $settlement,
        User $actor,
        string $reason,
        ?string $notes = null
    ): void {
        abort_unless($actor->isMainAdmin() || $actor->hasRole('admin'), 403, 'Unauthorized admin action.');

        DB::transaction(function () use ($settlement, $actor, $reason, $notes): void {
            $settlement = VendorSettlement::query()->whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            $supplier = $settlement->supplier;

            // 1. Unmatch or delete statement entries linked to this settlement
            if ($settlement->journal_entry_id || $settlement->id) {
                $statementEntries = CompanyAccountStatementEntry::query()
                    ->where(function ($q) use ($settlement): void {
                        if ($settlement->journal_entry_id) {
                            $q->where('journal_entry_id', $settlement->journal_entry_id);
                        }
                        $q->orWhere(function ($sub) use ($settlement): void {
                            $sub->where('source_type', VendorSettlement::class)
                                ->where('source_id', $settlement->id);
                        });
                    })
                    ->lockForUpdate()
                    ->get();

                foreach ($statementEntries as $stmt) {
                    if ($stmt->import_file_name === null && $stmt->import_fingerprint === null) {
                        // Auto-created statement entry created during settlement
                        $stmt->delete();
                    } else {
                        // Linked existing statement entry
                        $stmt->update([
                            'journal_entry_id' => null,
                            'source_type' => null,
                            'source_id' => null,
                            'source' => 'imported',
                            'status' => 'unmatched',
                            'matched_amount' => 0.0,
                            'is_finalized' => false,
                            'finalized_at' => null,
                            'reconciled_by' => null,
                            'reconciled_at' => null,
                        ]);
                    }
                }

                if ($settlement->journal_entry_id) {
                    $journalEntry = JournalEntry::query()->whereKey($settlement->journal_entry_id)->lockForUpdate()->first();
                    if ($journalEntry instanceof JournalEntry) {
                        $journalEntry->transactions()->delete();
                        $journalEntry->delete();
                    }
                }
            }

            // 2. Restore Vendor Advances created or used by this settlement
            $advancesCreated = VendorAdvance::query()->where('source_settlement_id', $settlement->id)->lockForUpdate()->get();
            foreach ($advancesCreated as $adv) {
                $adv->delete();
            }

            if ((float) $settlement->vendor_advance_used_amount > 0) {
                // Restore used advance back to open vendor advances for supplier
                $openAdvances = VendorAdvance::query()
                    ->where('supplier_id', $supplier->id)
                    ->where('status', 'used')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->get();

                $restoreRemaining = (float) $settlement->vendor_advance_used_amount;
                foreach ($openAdvances as $adv) {
                    $used = min($restoreRemaining, (float) $adv->amount_original - (float) $adv->amount_remaining);
                    if ($used > 0) {
                        $adv->update([
                            'amount_remaining' => round((float) $adv->amount_remaining + $used, 2),
                            'status' => 'open',
                        ]);
                        $restoreRemaining -= $used;
                    }
                    if ($restoreRemaining <= 0.009) {
                        break;
                    }
                }
            }

            // 3. Reverse Purchase Invoice Allocations and restore Invoice Balances
            $allocations = VendorSettlementAllocation::query()->where('vendor_settlement_id', $settlement->id)->lockForUpdate()->get();
            foreach ($allocations as $alloc) {
                $invoice = PurchaseInvoice::query()->whereKey($alloc->purchase_invoice_id)->lockForUpdate()->first();
                if ($invoice instanceof PurchaseInvoice) {
                    $cashAllocated = (float) $alloc->cash_allocated;
                    if ($cashAllocated > 0) {
                        $invoice->update([
                            'paid_amount' => round(max(0, (float) $invoice->paid_amount - $cashAllocated), 2),
                        ]);
                        // Delete matching PurchaseInvoicePayment row
                        PurchaseInvoicePayment::query()
                            ->where('purchase_invoice_id', $invoice->id)
                            ->where('amount', $cashAllocated)
                            ->where('payment_paid_by', 'company')
                            ->delete();
                    }

                    // Recalculate remaining settled balance after deleting this allocation
                    $alloc->delete();

                    $remainingSettled = (float) VendorSettlementAllocation::query()
                        ->where('purchase_invoice_id', $invoice->id)
                        ->sum('total_settled');
                    $net = round((float) $invoice->amount - (float) $invoice->discount_amount, 2);

                    $invoice->update([
                        'payment_status' => $remainingSettled >= $net - 0.01 ? 'paid' : ($remainingSettled > 0.01 ? 'partial' : 'unpaid'),
                    ]);
                }
            }

            // 4. Delete settlement record
            $settlement->delete();

            Log::info('Vendor settlement deleted with reversal by admin', [
                'settlement_id' => $settlement->id,
                'supplier_id' => $supplier->id,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'notes' => $notes,
            ]);

            if (function_exists('activity')) {
                activity('vendor_settlement')
                    ->causedBy($actor)
                    ->performedOn($supplier)
                    ->withProperties([
                        'action' => 'delete_vendor_settlement',
                        'settlement_id' => $settlement->id,
                        'reason' => $reason,
                        'notes' => $notes,
                    ])
                    ->log("Admin deleted vendor settlement #{$settlement->id} for {$supplier->name}. Reason: {$reason}");
            }
        });
    }

    /**
     * Atomically update a vendor settlement entry with reversal and re-application.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSettlementWithReversal(
        VendorSettlement $settlement,
        array $data,
        User $actor,
        string $reason,
        ?string $notes = null
    ): void {
        abort_unless($actor->isMainAdmin() || $actor->hasRole('admin'), 403, 'Unauthorized admin action.');

        DB::transaction(function () use ($settlement, $data, $actor, $reason, $notes): void {
            $settlement = VendorSettlement::query()->whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            $supplier = $settlement->supplier;

            // Check metadata vs financial changes
            $isFinancialChange = isset($data['actual_payment_amount']) || isset($data['settlement_discount_amount']) || isset($data['payment_date']) || isset($data['company_account_id']);

            if (! $isFinancialChange) {
                // Update metadata fields directly
                $settlement->update([
                    'reference' => $data['reference'] ?? $settlement->reference,
                    'note' => $data['note'] ?? $settlement->note,
                ]);

                return;
            }

            // Perform full reversal of old settlement
            $this->deleteSettlementWithReversal($settlement, $actor, 'Pre-update Reversal: '.$reason, $notes);

            // Re-create new settlement using VendorSettlementService
            $this->vendorSettlementService->createAutomatic($supplier, [
                'invoice_ids' => $data['invoice_ids'],
                'actual_payment_amount' => (float) ($data['actual_payment_amount'] ?? 0),
                'settlement_discount_amount' => (float) ($data['settlement_discount_amount'] ?? 0),
                'use_vendor_advance' => (bool) ($data['use_vendor_advance'] ?? false),
                'difference_treatment' => $data['difference_treatment'] ?? 'outstanding',
                'allocation_order' => $data['allocation_order'] ?? 'oldest',
                'payment_date' => (string) ($data['payment_date'] ?? now()->toDateString()),
                'payment_method' => $data['payment_method'] ?? 'Bank',
                'company_account_id' => isset($data['company_account_id']) ? (int) $data['company_account_id'] : null,
                'statement_entry_id' => isset($data['statement_entry_id']) ? (int) $data['statement_entry_id'] : null,
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
            ], (int) $actor->id);

            if (function_exists('activity')) {
                activity('vendor_settlement')
                    ->causedBy($actor)
                    ->performedOn($supplier)
                    ->withProperties([
                        'action' => 'update_vendor_settlement',
                        'settlement_id' => $settlement->id,
                        'reason' => $reason,
                        'notes' => $notes,
                    ])
                    ->log("Admin updated vendor settlement #{$settlement->id} for {$supplier->name}. Reason: {$reason}");
            }
        });
    }
}
