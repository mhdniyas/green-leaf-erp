<?php

namespace App\Services\ShopInvoices;

use App\Enums\ShopInvoice\ShopCreditNoteAccountingMode;
use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\ShopInvoice;
use Illuminate\Support\Collection;

/**
 * Centralized, read-only calculation service for Shop Invoices and their
 * finalized credit/debit adjustments.
 *
 * Rules:
 * 1. Final Net Payable = Original Invoice Total - Finalized Credits + Finalized Debits.
 * 2. When no adjustment exists, Final Net Payable = Original Invoice Total.
 * 3. Count finalized (approved) adjustments only; draft or voided adjustments are excluded.
 * 4. Remaining Balance = max(0.00, Final Net Payable - Amount Paid).
 * 5. Overpayment Credit = max(0.00, Amount Paid - Final Net Payable).
 * 6. Never modify original invoice records or rewrite ledger/cashbook entries.
 */
class ShopFinalBillService
{
    /**
     * Calculate the canonical final bill representation for a shop invoice.
     */
    public function calculate(ShopInvoice $invoice): ShopFinalBill
    {
        $originalTotal = round((float) $invoice->final_total, 2);

        $legacyCreditTotal = $this->resolveAdjustmentTotal(
            $invoice,
            ShopCreditNoteDirection::Credit
        );

        $appliedCreditTotal = $this->resolveAppliedAdjustmentTotal(
            $invoice,
            ShopCreditNoteDirection::Credit
        );

        $finalizedCreditTotal = round($legacyCreditTotal + $appliedCreditTotal, 2);

        $legacyDebitTotal = $this->resolveAdjustmentTotal(
            $invoice,
            ShopCreditNoteDirection::Debit
        );

        $appliedDebitTotal = $this->resolveAppliedAdjustmentTotal(
            $invoice,
            ShopCreditNoteDirection::Debit
        );

        $finalizedDebitTotal = round($legacyDebitTotal + $appliedDebitTotal, 2);

        $netAdjustment = round($finalizedDebitTotal - $finalizedCreditTotal, 2);
        $finalNetPayable = round(max(0.00, $originalTotal - $finalizedCreditTotal + $finalizedDebitTotal), 2);
        $amountPaid = round((float) $invoice->paid_amount, 2);
        $remainingBalance = round(max(0.00, $finalNetPayable - $amountPaid), 2);
        $overpaymentCredit = round(max(0.00, $amountPaid - $finalNetPayable), 2);

        $effectivePaymentStatus = match (true) {
            $overpaymentCredit > 0.0001 => 'overpaid',
            $remainingBalance <= 0.0001 && ($amountPaid > 0.0001 || ($finalNetPayable <= 0.0001 && $originalTotal > 0.0001)) => 'paid',
            $amountPaid > 0.0001 && $remainingBalance > 0.0001 => 'partially_paid',
            default => 'unpaid',
        };

        $hasAdjustments = ($finalizedCreditTotal > 0.0001 || $finalizedDebitTotal > 0.0001);

        return new ShopFinalBill(
            invoiceId: (int) $invoice->id,
            invoiceNumber: (string) ($invoice->invoice_number ?? ''),
            originalInvoiceTotal: $originalTotal,
            finalizedCreditTotal: $finalizedCreditTotal,
            finalizedDebitTotal: $finalizedDebitTotal,
            netAdjustment: $netAdjustment,
            finalNetPayable: $finalNetPayable,
            amountPaid: $amountPaid,
            remainingBalance: $remainingBalance,
            overpaymentCredit: $overpaymentCredit,
            effectivePaymentStatus: $effectivePaymentStatus,
            hasAdjustments: $hasAdjustments,
            appliedCreditTotal: $appliedCreditTotal,
            appliedDebitTotal: $appliedDebitTotal,
        );
    }

    /**
     * Calculate final bills for a collection or iterable of invoices.
     *
     * @param  iterable<int, ShopInvoice>  $invoices
     * @return Collection<int, ShopFinalBill>
     */
    public function calculateMany(iterable $invoices): Collection
    {
        $collection = $invoices instanceof Collection ? $invoices : collect($invoices);

        $collection->loadMissing(['creditNotes', 'activeCreditApplications.creditNote']);

        return $collection->mapWithKeys(function (ShopInvoice $invoice): array {
            return [(int) $invoice->id => $this->calculate($invoice)];
        });
    }

    /**
     * Resolve finalized adjustment total for a specific direction (credit or debit).
     */
    private function resolveAdjustmentTotal(ShopInvoice $invoice, ShopCreditNoteDirection $direction): float
    {
        if ($invoice->relationLoaded('creditNotes')) {
            return round((float) $invoice->creditNotes
                ->filter(fn ($cn): bool => ($cn->status === ShopCreditNoteStatus::Approved || $cn->status === ShopCreditNoteStatus::Approved->value || $cn->status === 'approved')
                    && ($cn->direction === $direction || $cn->direction === $direction->value)
                    && ($cn->accounting_mode === null
                        || $cn->accounting_mode === ShopCreditNoteAccountingMode::LegacyInvoiceLinked
                        || $cn->accounting_mode === ShopCreditNoteAccountingMode::LegacyInvoiceLinked->value
                        || $cn->accounting_mode === 'legacy_invoice_linked'))
                ->sum('total_credit_amount'), 2);
        }

        return round((float) $invoice->creditNotes()
            ->where('status', ShopCreditNoteStatus::Approved->value)
            ->where('direction', $direction->value)
            ->where(function ($q): void {
                $q->whereNull('accounting_mode')
                    ->orWhere('accounting_mode', ShopCreditNoteAccountingMode::LegacyInvoiceLinked->value);
            })
            ->sum('total_credit_amount'), 2);
    }

    /**
     * Resolve total active adjustment applied from shop-level credit/debit notes.
     */
    private function resolveAppliedAdjustmentTotal(ShopInvoice $invoice, ShopCreditNoteDirection $direction): float
    {
        $directionValue = $direction->value;

        if ($invoice->relationLoaded('activeCreditApplications') || $invoice->relationLoaded('creditApplications')) {
            $apps = $invoice->relationLoaded('activeCreditApplications')
                ? $invoice->activeCreditApplications
                : $invoice->creditApplications->filter(fn ($app): bool => $app->reversed_at === null);

            $apps->loadMissing('creditNote');

            return round((float) $apps
                ->filter(function ($app) use ($direction, $directionValue): bool {
                    $note = $app->creditNote;
                    if (! $note) {
                        return $direction === ShopCreditNoteDirection::Credit;
                    }

                    return $note->direction === $direction || $note->direction === $directionValue;
                })
                ->sum('amount'), 2);
        }

        return round((float) $invoice->activeCreditApplications()
            ->whereHas('creditNote', function ($q) use ($direction, $directionValue): void {
                if ($direction === ShopCreditNoteDirection::Credit) {
                    $q->where(function ($sub) use ($directionValue): void {
                        $sub->whereNull('direction')->orWhere('direction', $directionValue);
                    });
                } else {
                    $q->where('direction', $directionValue);
                }
            })
            ->sum('amount'), 2);
    }
}
