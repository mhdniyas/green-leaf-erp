<?php

namespace App\Services\ShopInvoices;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Immutable value object representing the canonical final bill calculation
 * for a shop invoice and its finalized credit/debit adjustments.
 */
final class ShopFinalBill implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly int $invoiceId,
        public readonly string $invoiceNumber,
        public readonly float $originalInvoiceTotal,
        public readonly float $finalizedCreditTotal,
        public readonly float $finalizedDebitTotal,
        public readonly float $netAdjustment,
        public readonly float $finalNetPayable,
        public readonly float $amountPaid,
        public readonly float $remainingBalance,
        public readonly float $overpaymentCredit,
        public readonly string $effectivePaymentStatus,
        public readonly bool $hasAdjustments,
    ) {}

    public function isFullyPaid(): bool
    {
        return $this->remainingBalance <= 0.0001;
    }

    public function isPartiallyPaid(): bool
    {
        return $this->amountPaid > 0.0001 && $this->remainingBalance > 0.0001;
    }

    public function isUnpaid(): bool
    {
        return $this->amountPaid <= 0.0001 && $this->remainingBalance > 0.0001;
    }

    public function isOverpaid(): bool
    {
        return $this->overpaymentCredit > 0.0001;
    }

    /**
     * @return array{
     *     invoice_id: int,
     *     invoice_number: string,
     *     original_invoice_total: float,
     *     finalized_credit_total: float,
     *     finalized_debit_total: float,
     *     net_adjustment: float,
     *     final_net_payable: float,
     *     amount_paid: float,
     *     remaining_balance: float,
     *     overpayment_credit: float,
     *     effective_payment_status: string,
     *     has_adjustments: bool,
     *     is_fully_paid: bool,
     *     is_partially_paid: bool,
     *     is_unpaid: bool,
     *     is_overpaid: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'invoice_number' => $this->invoiceNumber,
            'original_invoice_total' => $this->originalInvoiceTotal,
            'finalized_credit_total' => $this->finalizedCreditTotal,
            'finalized_debit_total' => $this->finalizedDebitTotal,
            'net_adjustment' => $this->netAdjustment,
            'final_net_payable' => $this->finalNetPayable,
            'amount_paid' => $this->amountPaid,
            'remaining_balance' => $this->remainingBalance,
            'overpayment_credit' => $this->overpaymentCredit,
            'effective_payment_status' => $this->effectivePaymentStatus,
            'has_adjustments' => $this->hasAdjustments,
            'is_fully_paid' => $this->isFullyPaid(),
            'is_partially_paid' => $this->isPartiallyPaid(),
            'is_unpaid' => $this->isUnpaid(),
            'is_overpaid' => $this->isOverpaid(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
