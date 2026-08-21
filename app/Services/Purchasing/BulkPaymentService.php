<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BulkPaymentService
{
    public function __construct(
        private readonly PurchaseInvoiceService $invoiceService,
    ) {}

    /**
     * Process bulk payment across multiple invoices for a supplier.
     *
     * @param  array{bill_ids: array<int>, amount_paid: float, payment_method: string, payment_paid_by?: string, discount_allocations?: array<int, float>, payment_note?: string}  $payload
     * @return array{success: bool, processed: int, total_paid: float, total_discount: float, invoices: Collection}
     */
    public function processBulkPayment(Supplier $supplier, array $payload): array
    {
        return DB::transaction(function () use ($supplier, $payload): array {
            $billIds = $payload['bill_ids'] ?? [];
            $totalAmountPaid = max(0, (float) ($payload['amount_paid'] ?? 0));
            $paymentMethod = $payload['payment_method'] ?? 'Cash';
            $paymentPaidBy = $payload['payment_paid_by'] ?? 'purchaser';
            $discountAllocations = $payload['discount_allocations'] ?? [];
            $paymentNote = $payload['payment_note'] ?? null;

            // Load all pending/partial invoices for this supplier
            $invoices = PurchaseInvoice::query()
                ->whereIn('id', $billIds)
                ->where('supplier_id', $supplier->id)
                ->where('payment_status', '!=', 'paid')
                ->with(['supplier', 'purchaserCart'])
                ->orderBy('created_at', 'asc') // FIFO: oldest first
                ->get();

            if ($invoices->isEmpty()) {
                return [
                    'success' => false,
                    'processed' => 0,
                    'total_paid' => 0.0,
                    'total_discount' => 0.0,
                    'invoices' => collect(),
                    'message' => 'No eligible bills found for payment.',
                ];
            }

            // Calculate remaining balance for each invoice
            $invoicesWithBalance = $invoices->map(function (PurchaseInvoice $invoice) use ($discountAllocations): array {
                $grossAmount = round((float) $invoice->amount, 2);
                $currentDiscount = round((float) ($invoice->discount_amount ?? 0), 2);
                $currentPaid = round((float) ($invoice->paid_amount ?? 0), 2);
                $additionalDiscount = round((float) ($discountAllocations[$invoice->id] ?? 0), 2);
                $totalDiscount = min($grossAmount, round($currentDiscount + $additionalDiscount, 2));
                $netAmount = max(0, round($grossAmount - $totalDiscount, 2));
                $remainingBalance = max(0, round($netAmount - $currentPaid, 2));

                return [
                    'invoice' => $invoice,
                    'gross_amount' => $grossAmount,
                    'current_discount' => $currentDiscount,
                    'additional_discount' => $additionalDiscount,
                    'total_discount' => $totalDiscount,
                    'current_paid' => $currentPaid,
                    'remaining_balance' => $remainingBalance,
                ];
            })->filter(fn (array $item): bool => $item['remaining_balance'] > 0);

            if ($invoicesWithBalance->isEmpty()) {
                return [
                    'success' => false,
                    'processed' => 0,
                    'total_paid' => 0.0,
                    'total_discount' => 0.0,
                    'invoices' => collect(),
                    'message' => 'All selected bills are already fully paid.',
                ];
            }

            // Distribute payment across invoices (FIFO)
            $remainingPayment = $totalAmountPaid;
            $processedInvoices = collect();
            $totalDiscountApplied = 0.0;
            $totalPaidApplied = 0.0;

            foreach ($invoicesWithBalance as $item) {
                if ($remainingPayment <= 0) {
                    break;
                }

                /** @var PurchaseInvoice $invoice */
                $invoice = $item['invoice'];
                $remainingBalance = $item['remaining_balance'];
                $additionalDiscount = $item['additional_discount'];
                $totalDiscount = $item['total_discount'];

                // Calculate payment for this invoice
                $paymentForThisInvoice = min($remainingBalance, $remainingPayment);
                $newPaidAmount = round($item['current_paid'] + $paymentForThisInvoice, 2);

                // Update the invoice
                $updatedInvoice = $this->invoiceService->updatePayment($invoice, [
                    'payment_method' => $paymentMethod,
                    'payment_paid_by' => $paymentPaidBy,
                    'discount_amount' => $totalDiscount,
                    'paid_amount' => $newPaidAmount,
                    'payment_note' => $paymentNote,
                    'payment_details' => 'Bulk payment with '.count($billIds).' bills',
                ]);

                $processedInvoices->push($updatedInvoice);
                $remainingPayment = round($remainingPayment - $paymentForThisInvoice, 2);
                $totalDiscountApplied = round($totalDiscountApplied + $additionalDiscount, 2);
                $totalPaidApplied = round($totalPaidApplied + $paymentForThisInvoice, 2);
            }

            return [
                'success' => true,
                'processed' => $processedInvoices->count(),
                'total_paid' => $totalPaidApplied,
                'total_discount' => $totalDiscountApplied,
                'invoices' => $processedInvoices,
                'remaining_payment' => max(0, $remainingPayment),
                'message' => "Successfully processed payment for {$processedInvoices->count()} bill(s).",
            ];
        });
    }

    /**
     * Get pending bills for a supplier with calculated balances.
     *
     * @return Collection<int, array{invoice: PurchaseInvoice, balance: float}>
     */
    public function getPendingBills(Supplier $supplier): Collection
    {
        return PurchaseInvoice::query()
            ->where('supplier_id', $supplier->id)
            ->where('payment_status', '!=', 'paid')
            ->with(['supplier', 'purchaserCart'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function (PurchaseInvoice $invoice): array {
                $grossAmount = round((float) $invoice->amount, 2);
                $discount = round((float) ($invoice->discount_amount ?? 0), 2);
                $paid = round((float) ($invoice->paid_amount ?? 0), 2);
                $netAmount = max(0, round($grossAmount - $discount, 2));
                $balance = max(0, round($netAmount - $paid, 2));

                return [
                    'invoice' => $invoice,
                    'gross_amount' => $grossAmount,
                    'discount' => $discount,
                    'paid' => $paid,
                    'balance' => $balance,
                ];
            })
            ->filter(fn (array $item): bool => $item['balance'] > 0);
    }
}
