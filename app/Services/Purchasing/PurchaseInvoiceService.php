<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\DTOs\Purchasing\PurchaseInvoiceData;
use App\Enums\Purchasing\InvoiceStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoicePayment;
use App\Models\PurchaserCredit;
use App\Repositories\Purchasing\PurchaseInvoiceRepository;
use App\Services\Finance\JournalService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceService
{
    public function __construct(
        private readonly PurchaseInvoiceRepository $repository,
        private readonly JournalService $journalService,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->query()
            ->with(['goodsReceived', 'supplier'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function create(PurchaseInvoiceData $data): PurchaseInvoice
    {
        return DB::transaction(function () use ($data): PurchaseInvoice {
            /** @var GoodsReceived $grn */
            $grn = GoodsReceived::query()
                ->with('purchaseOrder.purchaserCart')
                ->findOrFail($data->goodsReceivedId);

            $invoiceData = $data->toArray();

            if ($grn->purchaseOrder?->purchaserCart?->isGreenLeafDirectPurchase()) {
                $invoiceData['purchaser_cart_id'] = $grn->purchaseOrder->purchaserCart->id;
                $invoiceData['purchase_source'] = $grn->purchaseOrder->purchaserCart->purchase_source;
                $invoiceData['purchaser_submitted_by'] = $grn->purchaseOrder->purchaserCart->user_id;
                $invoiceData['purchaser_submitted_at'] = $grn->purchaseOrder->purchaserCart->submitted_at;
            }

            /** @var PurchaseInvoice $invoice */
            $invoice = $this->repository->create($invoiceData);

            // Transition associated Purchase Order status to Closed upon matching invoice
            $po = $grn->purchaseOrder;
            if ($po) {
                $po->update([
                    'status' => POStatus::Closed,
                ]);
            }

            // Log activity
            activity()
                ->performedOn($invoice)
                ->log('invoice.created');

            return $invoice->fresh(['goodsReceived', 'supplier']);
        });
    }

    public function updateStatus(PurchaseInvoice $invoice, string $status): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoice, $status): PurchaseInvoice {
            $invoice->update(['status' => $status]);

            return $invoice->fresh();
        });
    }

    /**
     * @param  array{payment_method:string, discount_amount?:float, paid_amount:float, payment_note:?string, payment_details:?string, payment_paid_by?:string, bill_number?:string}  $payload
     */
    public function updatePayment(PurchaseInvoice $invoice, array $payload): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoice, $payload): PurchaseInvoice {
            $invoice->loadMissing(['supplier', 'purchaserCart']);
            $previousPaidAmount = round((float) ($invoice->paid_amount ?? 0), 2);
            $previousDiscountAmount = round((float) ($invoice->discount_amount ?? 0), 2);

            $invoiceAmount = round((float) $invoice->amount, 2);
            $discountAmount = min($invoiceAmount, max(0, round((float) ($payload['discount_amount'] ?? $invoice->discount_amount ?? 0), 2)));
            $netInvoiceAmount = max(0, round($invoiceAmount - $discountAmount, 2));
            $paidAmount = min($netInvoiceAmount, max(0, round((float) $payload['paid_amount'], 2)));
            $paymentMethod = $payload['payment_method'];
            $paymentPaidBy = $this->resolvePaymentPaidBy(
                paymentMethod: $paymentMethod,
                paidAmount: $paidAmount,
                requestedPaidBy: $payload['payment_paid_by'] ?? null,
            );
            $paymentStatus = $this->resolvePaymentStatus(
                paymentMethod: $paymentMethod,
                invoiceAmount: $netInvoiceAmount,
                paidAmount: $paidAmount,
            );
            $invoiceStatus = $paymentStatus === 'paid'
                ? InvoiceStatus::Paid->value
                : InvoiceStatus::Pending->value;
            $invoiceUpdateData = [
                'payment_method' => $paymentMethod,
                'payment_paid_by' => $paymentPaidBy,
                'discount_amount' => $discountAmount,
                'payment_status' => $paymentStatus,
                'paid_amount' => $paidAmount,
                'payment_note' => $payload['payment_note'],
                'payment_details' => $payload['payment_details'],
                'status' => $invoiceStatus,
            ];

            if (! empty($payload['bill_number'])) {
                $invoiceUpdateData['invoice_number'] = $payload['bill_number'];
            }

            $invoice->update($invoiceUpdateData);

            if ($invoice->purchaserCart) {
                $cartUpdateData = [
                    'payment_method' => $paymentMethod,
                    'discount_amount' => $discountAmount,
                    'payment_status' => $paymentStatus,
                    'paid_amount' => $paidAmount,
                    'payment_note' => $payload['payment_note'],
                    'payment_details' => $payload['payment_details'],
                    'payment_made_at' => $paymentStatus === 'paid' ? now() : null,
                    'goods_received_at' => $paymentStatus === 'paid' ? ($invoice->purchaserCart->goods_received_at ?: now()) : $invoice->purchaserCart->goods_received_at,
                ];

                if (! empty($payload['bill_number'])) {
                    $cartUpdateData['bill_number'] = $payload['bill_number'];
                }

                $invoice->purchaserCart->update($cartUpdateData);
            }

            $effectivePurchaserId = $invoice->purchaser_submitted_by
                ?? $payload['payment_purchaser_id']
                ?? null;

            if ($paymentPaidBy === 'purchaser' && $effectivePurchaserId) {
                PurchaserCredit::updateOrCreate(
                    ['purchase_invoice_id' => $invoice->id, 'type' => 'out'],
                    [
                        'purchaser_id' => $effectivePurchaserId,
                        'amount' => $netInvoiceAmount,
                        'description' => 'Debit for invoice: '.($payload['bill_number'] ?? $invoice->invoice_number),
                        'created_by' => auth()->id() ?: $effectivePurchaserId,
                        'business_date' => $invoice->purchaserCart?->business_date ?: today(),
                    ]
                );
            } else {
                PurchaserCredit::query()
                    ->where('purchase_invoice_id', $invoice->id)
                    ->where('type', 'out')
                    ->delete();
            }

            $updatedInvoice = $invoice->fresh(['supplier', 'purchaserCart']);
            $paidIncrease = round((float) $updatedInvoice->paid_amount - $previousPaidAmount, 2);
            $discountIncrease = round((float) $updatedInvoice->discount_amount - $previousDiscountAmount, 2);

            if ($paidIncrease > 0.0 || $discountIncrease > 0.0) {
                PurchaseInvoicePayment::query()->create([
                    'purchase_invoice_id' => $updatedInvoice->id,
                    'supplier_id' => $updatedInvoice->supplier_id,
                    'payment_date' => today()->toDateString(),
                    'amount' => max(0, $paidIncrease),
                    'discount_amount' => max(0, $discountIncrease),
                    'payment_method' => $paymentMethod,
                    'payment_paid_by' => $paymentPaidBy,
                    'note' => $payload['payment_note'],
                    'created_by' => auth()->id() ?: $updatedInvoice->purchaser_submitted_by,
                ]);
            }

            if ($paymentPaidBy === 'company' && $paidIncrease > 0) {
                $this->journalService->recordCompanyVendorCreditPayment(
                    invoice: $updatedInvoice,
                    amount: $paidIncrease,
                    userId: (int) (auth()->id() ?: $updatedInvoice->purchaser_submitted_by ?: 1),
                    paymentMode: $paymentMethod,
                );
            } elseif ($updatedInvoice->isGreenLeafDirectPurchase() && $paidIncrease > 0) {
                $this->journalService->recordGreenLeafDirectPurchasePayment(
                    invoice: $updatedInvoice,
                    amount: $paidIncrease,
                    userId: (int) (auth()->id() ?: $updatedInvoice->purchaser_submitted_by ?: 1),
                    paymentMode: $paymentMethod,
                );
            } elseif ($paymentPaidBy === 'purchaser' && $paidIncrease > 0) {
                $this->journalService->recordPurchaserDailyPurchasePayment(
                    invoice: $updatedInvoice,
                    amount: $paidIncrease,
                    userId: (int) (auth()->id() ?: $updatedInvoice->purchaser_submitted_by ?: 1),
                    paymentMode: $paymentMethod,
                );
            }

            return $updatedInvoice;
        });
    }

    private function resolvePaymentStatus(string $paymentMethod, float $invoiceAmount, float $paidAmount): string
    {
        if (strcasecmp($paymentMethod, 'Credit') === 0 && $paidAmount <= 0) {
            return 'credit_pending_approval';
        }

        if ($paidAmount <= 0) {
            return 'unpaid';
        }

        if ($paidAmount < $invoiceAmount) {
            return 'partial';
        }

        return 'paid';
    }

    private function resolvePaymentPaidBy(string $paymentMethod, float $paidAmount, ?string $requestedPaidBy): string
    {
        if (in_array($requestedPaidBy, ['purchaser', 'company', 'vendor_credit'], true)) {
            return $requestedPaidBy;
        }

        if (strcasecmp($paymentMethod, 'Credit') === 0 && $paidAmount <= 0.00) {
            return 'vendor_credit';
        }

        return 'purchaser';
    }

    /**
     * Comprehensive 6-step fix for a bill with a calculation discrepancy.
     *
     * 1. Fix every cart item line_total = round(quantity × unit_price, 2)
     * 2. Recalculate gross subtotal from items
     * 3. Update invoice (amount = gross, validate discount/paid/status)
     * 4. Sync purchaser cart (discount, paid, payment_status, payment_made_at)
     * 5. Audit purchaser credit (update amount if purchaser-funded, delete if not)
     * 6. Do NOT create new payment history or journal entries (avoids duplicates)
     *    Log before/after audit trail via Spatie ActivityLog
     *
     * @return array{invoice: PurchaseInvoice, before: array, after: array}
     */
    public function fixCalculationError(PurchaseInvoice $invoice): array
    {
        return DB::transaction(function () use ($invoice): array {
            $invoice->loadMissing(['supplier', 'purchaserCart.items', 'goodsReceived.items']);

            // ── Capture BEFORE state for audit trail ─────────────────────
            $before = [
                'gross' => round((float) $invoice->amount, 2),
                'discount' => round((float) $invoice->discount_amount, 2),
                'paid' => round((float) $invoice->paid_amount, 2),
                'balance' => round(max(0, (float) $invoice->amount - (float) $invoice->discount_amount - (float) $invoice->paid_amount), 2),
                'status' => (string) ($invoice->payment_status ?? 'unpaid'),
            ];

            $cart = $invoice->purchaserCart;

            // ── Step 1: Fix every cart item line_total ────────────────────
            if ($cart?->items?->isNotEmpty()) {
                foreach ($cart->items as $item) {
                    $lineTotal = round((float) $item->quantity * (float) $item->unit_price, 2);
                    $item->update(['line_total' => $lineTotal]);
                }
            }

            // ── Step 2: Recalculate gross subtotal from items ────────────
            $grossSubtotal = $invoice->itemsGrossTotal();

            // ── Step 3: Validate and update invoice ──────────────────────
            $discount = round(min($grossSubtotal, max(0, (float) $invoice->discount_amount)), 2);
            $netAmount = round(max(0, $grossSubtotal - $discount), 2);
            $paidAmount = round(min($netAmount, max(0, (float) $invoice->paid_amount)), 2);
            $paymentStatus = $this->resolvePaymentStatus(
                $invoice->payment_method ?: 'Cash',
                $netAmount,
                $paidAmount,
            );
            $invoiceStatus = $paymentStatus === 'paid'
                ? InvoiceStatus::Paid->value
                : InvoiceStatus::Pending->value;

            $invoice->update([
                'amount' => $grossSubtotal,
                'discount_amount' => $discount,
                'paid_amount' => $paidAmount,
                'payment_status' => $paymentStatus,
                'status' => $invoiceStatus,
            ]);

            // ── Step 4: Sync purchaser cart ──────────────────────────────
            if ($cart) {
                $cart->update([
                    'discount_amount' => $discount,
                    'paid_amount' => $paidAmount,
                    'payment_status' => $paymentStatus,
                    'payment_made_at' => $paymentStatus === 'paid'
                        ? ($cart->payment_made_at ?: now())
                        : null,
                ]);
            }

            // ── Step 5: Audit purchaser credit ───────────────────────────
            $creditQuery = PurchaserCredit::query()
                ->where('purchase_invoice_id', $invoice->id)
                ->where('type', 'out');

            if ($invoice->payment_paid_by === 'purchaser') {
                // Update the debit amount to match the corrected net total
                $creditQuery->update(['amount' => $netAmount]);
            } else {
                // Not purchaser-funded — remove stale credit entry
                $creditQuery->delete();
            }

            // ── Step 6: Audit trail (no new payments/journals) ───────────
            $after = [
                'gross' => $grossSubtotal,
                'discount' => $discount,
                'net' => $netAmount,
                'paid' => $paidAmount,
                'balance' => round(max(0, $netAmount - $paidAmount), 2),
                'status' => $paymentStatus,
            ];

            activity()
                ->performedOn($invoice)
                ->withProperties([
                    'action' => 'admin_fix_calculation',
                    'before' => $before,
                    'after' => $after,
                    'fixed_by' => auth()->id(),
                    'fixed_at' => now()->toIso8601String(),
                ])
                ->log('invoice.calculation_fixed');

            return [
                'invoice' => $invoice->fresh(),
                'before' => $before,
                'after' => $after,
            ];
        });
    }

    /**
     * Scan all purchase invoices for calculation errors and fix them in bulk.
     *
     * @return array{fixed_count: int, audit_log: list<array{invoice_id: int|string, before: array, after: array}>}
     */
    public function fixAllCalculationErrors(): array
    {
        $invoices = PurchaseInvoice::query()
            ->with(['supplier', 'purchaserCart.items', 'goodsReceived.items'])
            ->notCancelled()
            ->get();

        $auditLog = [];
        $fixedCount = 0;

        foreach ($invoices as $invoice) {
            if (! $invoice->hasCalculationError()) {
                continue;
            }

            $result = $this->fixCalculationError($invoice);
            $auditLog[] = [
                'invoice_id' => $invoice->getKey(),
                'invoice_number' => $invoice->invoice_number,
                'before' => $result['before'],
                'after' => $result['after'],
            ];
            $fixedCount++;
        }

        return [
            'fixed_count' => $fixedCount,
            'audit_log' => $auditLog,
        ];
    }
}
