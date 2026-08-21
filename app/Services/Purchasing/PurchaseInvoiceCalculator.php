<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\PurchaseInvoice;

/**
 * Single source of truth for all purchase invoice financial calculations.
 *
 * Used by both purchaser and admin controllers to guarantee 100% tally
 * across every report page. Never duplicate these formulas inline.
 */
final class PurchaseInvoiceCalculator
{
    /**
     * @return array{gross: float, discount: float, net: float, paid: float, balance: float}
     */
    public function calculate(PurchaseInvoice $invoice): array
    {
        $gross = round(max(0, (float) $invoice->amount), 2);
        $discount = round(min($gross, max(0, (float) $invoice->discount_amount)), 2);
        $net = round(max(0, $gross - $discount), 2);
        $paid = round(min($net, max(0, (float) $invoice->paid_amount)), 2);
        $balance = round(max(0, $net - $paid), 2);

        return [
            'gross' => $gross,
            'discount' => $discount,
            'net' => $net,
            'paid' => $paid,
            'balance' => $balance,
        ];
    }

    /**
     * Calculate net amount (gross - discount).
     */
    public function calculateNet(PurchaseInvoice $invoice): float
    {
        $gross = round(max(0, (float) $invoice->amount), 2);
        $discount = round(min($gross, max(0, (float) $invoice->discount_amount)), 2);

        return round(max(0, $gross - $discount), 2);
    }

    /**
     * Calculate remaining balance (net - paid, capped at net).
     */
    public function calculateBalance(PurchaseInvoice $invoice): float
    {
        $net = $this->calculateNet($invoice);
        $paid = round(min($net, max(0, (float) $invoice->paid_amount)), 2);

        return round(max(0, $net - $paid), 2);
    }

    /**
     * Calculate capped paid amount (cannot exceed net).
     */
    public function calculatePaidCapped(PurchaseInvoice $invoice): float
    {
        $net = $this->calculateNet($invoice);
        $paid = round(max(0, (float) $invoice->paid_amount), 2);

        return round(min($net, $paid), 2);
    }
}
