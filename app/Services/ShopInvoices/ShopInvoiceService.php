<?php

declare(strict_types=1);

namespace App\Services\ShopInvoices;

use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopInvoicePaymentAllocation;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Services\Finance\JournalService;
use App\Services\Pricing\ApprovedDailyPriceResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShopInvoiceService
{
    public function __construct(
        private readonly ApprovedDailyPriceResolver $approvedDailyPriceResolver,
        private readonly JournalService $journalService,
    ) {}

    public function generateForBusinessDate(string $businessDate, int $userId): void
    {
        ShopOrder::query()
            ->with(['shop.priceGroup', 'items.product', 'invoice.items'])
            ->whereDate('business_date', $businessDate)
            ->where('state', 'approved')
            ->where('order_source', '!=', 'admin_direct_purchase')
            ->get()
            ->each(fn (ShopOrder $order) => $this->synchronizeOrderInvoice($order, $userId));
    }

    public function synchronizeOrderInvoice(ShopOrder $order, int $userId): ShopInvoice
    {
        $order->loadMissing(['shop.priceGroup', 'items.product', 'invoice.items']);

        return DB::transaction(function () use ($order, $userId): ShopInvoice {
            $invoice = ShopInvoice::query()->firstOrNew([
                'shop_order_id' => $order->id,
            ]);

            if ($invoice->exists && $invoice->isFinalLocked()) {
                return $invoice->fresh('items');
            }

            $invoice->fill([
                'shop_id' => $order->shop_id,
                'shop_order_id' => $order->id,
                'invoice_number' => $invoice->exists ? $invoice->invoice_number : $this->invoiceNumberFor($order),
                'business_date' => $order->business_date->toDateString(),
                'status' => $invoice->exists ? $invoice->status : 'generated',
                'delivery_status' => $invoice->exists ? $invoice->delivery_status : 'pending',
                'payment_status' => $invoice->exists ? $invoice->payment_status : 'unpaid',
                'generated_by' => $invoice->generated_by ?? $userId,
            ]);
            $invoice->save();

            $existingItems = $invoice->items()->get()->keyBy('shop_order_item_id');
            $activeOrderItemIds = [];

            foreach ($order->items as $orderItem) {
                if ((float) ($orderItem->approved_qty ?? 0) <= 0) {
                    continue;
                }

                $activeOrderItemIds[] = (int) $orderItem->id;
                $invoiceItem = $existingItems->get($orderItem->id) ?? new ShopInvoiceItem([
                    'shop_invoice_id' => $invoice->id,
                    'shop_order_item_id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                ]);

                $unitPrice = $this->unitPriceForOrderItem($order, $orderItem);
                $approvedQty = (float) $orderItem->approved_qty;
                $deliveredQty = $invoiceItem->exists ? (float) $invoiceItem->delivered_qty : (float) ($orderItem->delivered_qty ?? 0);
                $shortageQty = $invoiceItem->exists ? (float) $invoiceItem->shortage_qty : (float) ($orderItem->shortage_qty ?? 0);
                $lineSubtotal = round($approvedQty * $unitPrice, 2);
                $shortageAmount = round($shortageQty * $unitPrice, 2);

                $invoiceItem->fill([
                    'shop_order_item_id' => $orderItem->id,
                    'product_name' => $orderItem->product?->name ?? 'Unknown Product',
                    'unit' => $orderItem->unit,
                    'approved_qty' => $approvedQty,
                    'delivered_qty' => $deliveredQty,
                    'shortage_qty' => $shortageQty,
                    'unit_price' => $unitPrice,
                    'line_subtotal' => $lineSubtotal,
                    'shortage_amount' => $shortageAmount,
                    'final_line_total' => round($lineSubtotal - $shortageAmount, 2),
                ]);
                $invoiceItem->save();
            }

            $invoice->items()
                ->when(
                    $activeOrderItemIds !== [],
                    fn ($query) => $query->whereNotIn('shop_order_item_id', $activeOrderItemIds),
                    fn ($query) => $query,
                )
                ->delete();

            return $this->recalculate($invoice->fresh('items'));
        });
    }

    public function applyDeliveryCheckin(ShopOrder $order, array $deliveredQtys, int $userId, ?string $deliveryNote = null): ShopInvoice
    {
        $invoice = $this->synchronizeOrderInvoice($order, $userId);

        if ($invoice->isFinalLocked()) {
            throw ValidationException::withMessages([
                'invoice' => 'This shop invoice is finalized. Create an adjustment instead of changing the original delivery.',
            ]);
        }

        return DB::transaction(function () use ($order, $invoice, $deliveredQtys, $userId, $deliveryNote): ShopInvoice {
            $hasDiscrepancy = false;
            $invoiceItems = $invoice->items()->get()->keyBy('shop_order_item_id');

            foreach ($order->items as $orderItem) {
                $approvedQty = (float) ($orderItem->approved_qty ?? 0);
                $deliveredQty = (float) ($deliveredQtys[$orderItem->id] ?? 0);
                $shortageQty = max(0.00, $approvedQty - $deliveredQty);
                $hasDiscrepancy = $hasDiscrepancy || $shortageQty > 0.0001;

                $invoiceItem = $invoiceItems->get($orderItem->id);
                if (! $invoiceItem) {
                    continue;
                }

                $shortageAmount = round($shortageQty * (float) $invoiceItem->unit_price, 2);

                $invoiceItem->update([
                    'delivered_qty' => $deliveredQty,
                    'shortage_qty' => $shortageQty,
                    'shortage_amount' => $shortageAmount,
                    'final_line_total' => round((float) $invoiceItem->line_subtotal - $shortageAmount, 2),
                ]);

                $orderItem->update([
                    'delivered_qty' => $deliveredQty,
                    'shortage_qty' => $shortageQty,
                    'shortage_value' => $shortageAmount,
                ]);
            }

            $invoice->update([
                'delivery_status' => $hasDiscrepancy ? 'received_with_discrepancy' : 'received_full',
                'status' => $hasDiscrepancy ? 'delivery_review' : 'finalized',
                'delivery_note' => $deliveryNote,
                'delivery_confirmed_by' => $userId,
                'delivery_confirmed_at' => now(),
            ]);

            $invoice = $this->recalculate($invoice->fresh('items'));

            $order->update([
                'delivery_status' => $hasDiscrepancy ? 'pending_approval' : 'delivered',
                'is_delivered' => ! $hasDiscrepancy,
                'delivered_at' => $hasDiscrepancy ? null : now(),
                'delivered_by' => $userId,
                'delivery_notes' => $deliveryNote,
                'total_shortage_value' => $invoice->shortage_total,
                'balance_amount' => $invoice->balance_amount,
                'payment_status' => $invoice->payment_status,
            ]);

            return $invoice;
        });
    }

    public function finalizeDiscrepancy(ShopOrder $order, int $userId): ShopInvoice
    {
        $order->loadMissing('invoice.items');
        $invoice = $order->invoice;

        if (! $invoice) {
            $invoice = $this->synchronizeOrderInvoice($order, $userId);
        }

        $invoice->update([
            'delivery_status' => 'approved_after_discrepancy',
            'status' => 'finalized',
            'delivery_confirmed_by' => $invoice->delivery_confirmed_by ?? $userId,
            'delivery_confirmed_at' => $invoice->delivery_confirmed_at ?? now(),
        ]);

        $invoice = $this->recalculate($invoice->fresh('items'));

        $order->update([
            'is_delivered' => true,
            'delivered_at' => now(),
            'delivery_status' => (float) $invoice->shortage_total > 0 ? 'partially_delivered' : 'delivered',
            'payment_status' => $invoice->payment_status,
            'balance_amount' => $invoice->balance_amount,
            'total_shortage_value' => $invoice->shortage_total,
        ]);

        return $invoice;
    }

    /**
     * @param  array{discount_total?: float|int|string|null, paid_amount?: float|int|string|null, payment_note?: string|null}  $payload
     */
    public function approvePayment(ShopInvoice $invoice, array $payload, int $userId): ShopInvoice
    {
        return DB::transaction(function () use ($invoice, $payload, $userId): ShopInvoice {
            $previousPaidAmount = round((float) $invoice->paid_amount, 2);

            $invoice->update([
                'discount_total' => round((float) ($payload['discount_total'] ?? 0), 2),
                'paid_amount' => round((float) ($payload['paid_amount'] ?? 0), 2),
                'payment_note' => $payload['payment_note'] ?? null,
                'payment_approved_by' => $userId,
                'payment_approved_at' => now(),
            ]);

            $invoice = ShopInvoice::query()
                ->with('items')
                ->findOrFail($invoice->id);

            $invoice = $this->recalculate($invoice);
            $approvedPaymentIncrease = round((float) $invoice->paid_amount - $previousPaidAmount, 2);

            if ($approvedPaymentIncrease > 0.00) {
                $paidAmountCents = (int) round((float) $invoice->paid_amount * 100);

                $this->journalService->recordShopInvoicePayment(
                    $invoice,
                    $approvedPaymentIncrease,
                    $userId,
                    "payment:paid-{$paidAmountCents}",
                );
            }

            if ($invoice->order) {
                $invoice->order->update([
                    'cash_collected' => $invoice->paid_amount,
                    'cash_discrepancy' => round((float) $invoice->final_total - (float) $invoice->paid_amount, 2),
                    'payment_status' => $invoice->payment_status,
                    'balance_amount' => $invoice->balance_amount,
                ]);
            }

            return $invoice;
        });
    }

    /**
     * @param  array{amount_mode:string, amount?: float|int|string|null, shop_note?: string|null}  $payload
     */
    public function requestPayment(ShopInvoice $invoice, array $payload, int $userId): ShopInvoicePaymentRequest
    {
        return DB::transaction(function () use ($invoice, $payload, $userId): ShopInvoicePaymentRequest {
            $invoice->refresh();
            $pendingInvoices = $this->pendingInvoicesForShop((int) $invoice->shop_id);
            $totalShopDue = round($pendingInvoices->sum(fn (ShopInvoice $pendingInvoice): float => (float) $pendingInvoice->balance_amount), 2);

            if ($totalShopDue <= 0.0) {
                throw ValidationException::withMessages([
                    'invoice_id' => 'This shop has no pending bills to pay.',
                ]);
            }

            $existingPendingRequest = ShopInvoicePaymentRequest::query()
                ->where('shop_id', $invoice->shop_id)
                ->where('status', 'pending')
                ->first();

            if ($existingPendingRequest instanceof ShopInvoicePaymentRequest) {
                throw ValidationException::withMessages([
                    'invoice_id' => 'A payment request for this shop is already waiting for approval.',
                ]);
            }

            $requestedAmount = $payload['amount_mode'] === 'balance_due'
                ? $totalShopDue
                : round((float) ($payload['amount'] ?? 0), 2);

            if ($requestedAmount <= 0.0) {
                throw ValidationException::withMessages([
                    'amount' => 'Requested amount must be greater than zero.',
                ]);
            }

            return ShopInvoicePaymentRequest::query()->create([
                'shop_invoice_id' => $invoice->id,
                'shop_id' => $invoice->shop_id,
                'requested_by' => $userId,
                'request_type' => $payload['amount_mode'],
                'requested_amount' => $requestedAmount,
                'applied_amount' => 0,
                'credit_amount' => 0,
                'status' => 'pending',
                'shop_note' => filled($payload['shop_note'] ?? null) ? trim((string) $payload['shop_note']) : null,
            ]);
        });
    }

    public function reviewPaymentRequest(ShopInvoicePaymentRequest $paymentRequest, string $decision, int $userId, ?string $adminNote = null): ShopInvoicePaymentRequest
    {
        return DB::transaction(function () use ($paymentRequest, $decision, $userId, $adminNote): ShopInvoicePaymentRequest {
            $paymentRequest->loadMissing('invoice', 'allocations');

            if ($paymentRequest->status !== 'pending') {
                throw ValidationException::withMessages([
                    'decision' => 'This payment request has already been reviewed.',
                ]);
            }

            if ($decision === 'approve') {
                $invoice = $paymentRequest->invoice;

                if (! $invoice instanceof ShopInvoice) {
                    throw ValidationException::withMessages([
                        'decision' => 'The related invoice could not be found.',
                    ]);
                }

                $approvedAmount = round((float) $paymentRequest->requested_amount, 2);
                $appliedAmount = $this->allocateShopPaymentToPendingInvoices($paymentRequest, $approvedAmount, $userId, $adminNote);
                $creditAmount = round(max(0, $approvedAmount - $appliedAmount), 2);

                $this->journalService->recordShopInvoicePayment(
                    $invoice,
                    $approvedAmount,
                    $userId,
                    'shop-payment-request:'.$paymentRequest->id,
                );

                $paymentRequest->update([
                    'status' => 'approved',
                    'approved_amount' => $approvedAmount,
                    'applied_amount' => $appliedAmount,
                    'credit_amount' => $creditAmount,
                    'admin_note' => filled($adminNote) ? trim((string) $adminNote) : null,
                    'reviewed_by' => $userId,
                    'reviewed_at' => now(),
                ]);
            } else {
                $paymentRequest->update([
                    'status' => 'rejected',
                    'approved_amount' => null,
                    'applied_amount' => 0,
                    'credit_amount' => 0,
                    'admin_note' => filled($adminNote) ? trim((string) $adminNote) : null,
                    'reviewed_by' => $userId,
                    'reviewed_at' => now(),
                ]);
            }

            return $paymentRequest->fresh(['invoice', 'requestedBy', 'reviewedBy', 'allocations.invoice']);
        });
    }

    /**
     * @return array{total_due: float, applied_amount: float, credit_amount: float, invoices: array<int, array{invoice: ShopInvoice, amount: float}>}
     */
    public function allocationPreviewForShopPayment(ShopInvoicePaymentRequest $paymentRequest): array
    {
        $pendingInvoices = $this->pendingInvoicesForShop((int) $paymentRequest->shop_id);
        $remainingAmount = round((float) $paymentRequest->requested_amount, 2);
        $invoices = [];

        foreach ($pendingInvoices as $invoice) {
            if ($remainingAmount <= 0.0) {
                break;
            }

            $allocationAmount = round(min($remainingAmount, (float) $invoice->balance_amount), 2);

            if ($allocationAmount <= 0.0) {
                continue;
            }

            $invoices[] = [
                'invoice' => $invoice,
                'amount' => $allocationAmount,
            ];
            $remainingAmount = round($remainingAmount - $allocationAmount, 2);
        }

        $appliedAmount = round(collect($invoices)->sum(fn (array $row): float => (float) $row['amount']), 2);

        return [
            'total_due' => round($pendingInvoices->sum(fn (ShopInvoice $invoice): float => (float) $invoice->balance_amount), 2),
            'applied_amount' => $appliedAmount,
            'credit_amount' => round(max(0, (float) $paymentRequest->requested_amount - $appliedAmount), 2),
            'invoices' => $invoices,
        ];
    }

    public function availableShopCredit(int $shopId): float
    {
        $approvedPaymentRequests = ShopInvoicePaymentRequest::query()
            ->where('shop_id', $shopId)
            ->where('status', 'approved')
            ->with('allocations')
            ->get();

        return round($approvedPaymentRequests->sum(
            fn (ShopInvoicePaymentRequest $paymentRequest): float => $paymentRequest->remainingCreditAmount()
        ), 2);
    }

    /**
     * @param  array{discount_total?: float|int|string|null, paid_amount: float|int|string, payment_note?: string|null}  $payload
     */
    public function recordAdminPaymentReceived(ShopInvoice $invoice, array $payload, int $userId): ShopInvoicePaymentRequest
    {
        return DB::transaction(function () use ($invoice, $payload, $userId): ShopInvoicePaymentRequest {
            $invoice->refresh();

            if (array_key_exists('discount_total', $payload)) {
                $invoice->update([
                    'discount_total' => round((float) ($payload['discount_total'] ?? $invoice->discount_total), 2),
                ]);
                $invoice = $this->recalculate($invoice->fresh('items'));
            }

            $currentPaidAmount = round((float) $invoice->paid_amount, 2);
            $paidAmount = round((float) $payload['paid_amount'], 2);
            $approvedAmount = round($paidAmount - $currentPaidAmount, 2);
            $adminNote = filled($payload['payment_note'] ?? null) ? trim((string) $payload['payment_note']) : null;

            if ($approvedAmount <= 0.0) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'Paid amount must be greater than the current collected amount.',
                ]);
            }

            $paymentRequest = ShopInvoicePaymentRequest::query()->create([
                'shop_invoice_id' => $invoice->id,
                'shop_id' => $invoice->shop_id,
                'requested_by' => $userId,
                'request_type' => 'admin_manual',
                'requested_amount' => $approvedAmount,
                'approved_amount' => $approvedAmount,
                'applied_amount' => 0,
                'credit_amount' => 0,
                'status' => 'approved',
                'shop_note' => 'Admin recorded payment received.',
                'admin_note' => $adminNote,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ]);

            $appliedAmount = $this->allocateShopPaymentToPendingInvoices($paymentRequest, $approvedAmount, $userId, $adminNote);
            $creditAmount = round(max(0, $approvedAmount - $appliedAmount), 2);

            $this->journalService->recordShopInvoicePayment(
                $invoice,
                $approvedAmount,
                $userId,
                'admin-shop-payment:'.$paymentRequest->id,
            );

            $paymentRequest->update([
                'applied_amount' => $appliedAmount,
                'credit_amount' => $creditAmount,
            ]);

            return $paymentRequest->fresh(['invoice', 'requestedBy', 'reviewedBy', 'allocations.invoice']);
        });
    }

    public function repriceInvoice(ShopInvoice $invoice, int $userId, ?string $reason = null): ShopInvoice
    {
        $invoice->loadMissing(['shop.priceGroup', 'items.product', 'order']);

        if ($invoice->isFinalLocked()) {
            throw ValidationException::withMessages([
                'invoice' => 'This shop invoice is finalized. Create a price adjustment instead of repricing the original invoice.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $userId, $reason): ShopInvoice {
            foreach ($invoice->items as $invoiceItem) {
                $product = $invoiceItem->product;

                if (! $product) {
                    continue;
                }

                $price = $this->approvedDailyPriceResolver->resolve($product, $invoice->shop, $invoice->business_date);
                $unitPrice = round((float) $price['price'], 2);
                $lineSubtotal = round((float) $invoiceItem->approved_qty * $unitPrice, 2);
                $shortageAmount = round((float) $invoiceItem->shortage_qty * $unitPrice, 2);

                $invoiceItem->update([
                    'unit_price' => $unitPrice,
                    'line_subtotal' => $lineSubtotal,
                    'shortage_amount' => $shortageAmount,
                    'final_line_total' => round($lineSubtotal - $shortageAmount, 2),
                ]);
            }

            $invoice->update([
                'admin_price_note' => $reason,
                'price_updated_by' => $userId,
                'price_updated_at' => now(),
            ]);

            $invoice = $this->recalculate($invoice->fresh('items'));

            if ($invoice->order) {
                $invoice->order->update([
                    'total_shortage_value' => $invoice->shortage_total,
                    'balance_amount' => $invoice->balance_amount,
                ]);
            }

            return $invoice;
        });
    }

    public function repriceAllForBusinessDate(string $businessDate, int $userId, ?string $reason = null): void
    {
        ShopInvoice::query()
            ->with(['shop.priceGroup', 'items.product', 'order'])
            ->whereDate('business_date', $businessDate)
            ->get()
            ->reject(fn (ShopInvoice $invoice): bool => $invoice->isFinalLocked())
            ->each(fn (ShopInvoice $invoice) => $this->repriceInvoice($invoice, $userId, $reason));
    }

    private function allocateShopPaymentToPendingInvoices(ShopInvoicePaymentRequest $paymentRequest, float $paymentAmount, int $userId, ?string $adminNote = null): float
    {
        $remainingAmount = round($paymentAmount, 2);
        $appliedAmount = 0.0;

        foreach ($this->pendingInvoicesForShop((int) $paymentRequest->shop_id) as $invoice) {
            if ($remainingAmount <= 0.0) {
                break;
            }

            $allocationAmount = round(min($remainingAmount, (float) $invoice->balance_amount), 2);

            if ($allocationAmount <= 0.0) {
                continue;
            }

            $this->applyInvoicePaymentWithoutJournal($invoice, $allocationAmount, $userId, $adminNote);

            ShopInvoicePaymentAllocation::query()->create([
                'payment_request_id' => $paymentRequest->id,
                'shop_invoice_id' => $invoice->id,
                'shop_id' => $invoice->shop_id,
                'amount' => $allocationAmount,
                'created_by' => $userId,
            ]);

            $appliedAmount = round($appliedAmount + $allocationAmount, 2);
            $remainingAmount = round($remainingAmount - $allocationAmount, 2);
        }

        return $appliedAmount;
    }

    private function applyInvoicePaymentWithoutJournal(ShopInvoice $invoice, float $amount, int $userId, ?string $adminNote = null): ShopInvoice
    {
        $invoice->refresh();
        $appliedAmount = round(min($amount, (float) $invoice->balance_amount), 2);

        if ($appliedAmount <= 0.0) {
            return $invoice;
        }

        $noteParts = array_filter([
            (string) $invoice->payment_note,
            'Shop payment allocated: Rs. '.number_format($appliedAmount, 2),
            filled($adminNote) ? trim((string) $adminNote) : null,
        ]);

        $invoice->update([
            'paid_amount' => round((float) $invoice->paid_amount + $appliedAmount, 2),
            'payment_note' => implode("\n", $noteParts),
            'payment_approved_by' => $userId,
            'payment_approved_at' => now(),
        ]);

        $invoice = $this->recalculate($invoice->fresh('items'));

        if ($invoice->order) {
            $invoice->order->update([
                'cash_collected' => $invoice->paid_amount,
                'cash_discrepancy' => round((float) $invoice->final_total - (float) $invoice->paid_amount, 2),
                'payment_status' => $invoice->payment_status,
                'balance_amount' => $invoice->balance_amount,
            ]);
        }

        return $invoice;
    }

    /**
     * @return Collection<int, ShopInvoice>
     */
    private function pendingInvoicesForShop(int $shopId): Collection
    {
        return ShopInvoice::query()
            ->where('shop_id', $shopId)
            ->where('balance_amount', '>', 0)
            ->with(['order'])
            ->oldest('business_date')
            ->oldest('id')
            ->get();
    }

    public function recalculate(ShopInvoice $invoice): ShopInvoice
    {
        $invoice->loadMissing('items');

        $subtotal = round((float) $invoice->items->sum('line_subtotal'), 2);
        $shortageTotal = round((float) $invoice->items->sum('shortage_amount'), 2);
        $discountTotal = round((float) $invoice->discount_total, 2);
        $finalTotal = round(max(0.00, $subtotal - $shortageTotal - $discountTotal), 2);
        $paidAmount = round((float) $invoice->paid_amount, 2);
        $balanceAmount = round(max(0.00, $finalTotal - $paidAmount), 2);

        $paymentStatus = match (true) {
            $paidAmount <= 0.00 => 'unpaid',
            $balanceAmount > 0.00 => 'partially_paid',
            default => 'paid',
        };

        $status = match (true) {
            $paymentStatus === 'paid' => 'paid',
            in_array($invoice->delivery_status, ['awaiting_review', 'received_with_discrepancy'], true) => 'delivery_review',
            in_array($invoice->delivery_status, ['received_full', 'approved_after_discrepancy'], true) => 'payment_pending',
            default => $invoice->status ?: 'generated',
        };

        $invoice->update([
            'subtotal' => $subtotal,
            'shortage_total' => $shortageTotal,
            'final_total' => $finalTotal,
            'balance_amount' => $balanceAmount,
            'payment_status' => $paymentStatus,
            'status' => $status,
        ]);

        return $invoice->fresh('items');
    }

    private function invoiceNumberFor(ShopOrder $order): string
    {
        $base = sprintf(
            'SINV-%s-%s',
            $order->business_date->format('Ymd'),
            strtoupper($order->shop?->code ?? sprintf('SHOP%04d', $order->shop_id))
        );

        $invoiceNumber = $base;
        $counter = 1;

        while (
            ShopInvoice::query()
                ->where('invoice_number', $invoiceNumber)
                ->where('shop_order_id', '!=', $order->id)
                ->exists()
        ) {
            $counter++;
            $invoiceNumber = $base.'-'.$counter;
        }

        return $invoiceNumber;
    }

    private function unitPriceForOrderItem(ShopOrder $order, ShopOrderItem $orderItem): float
    {
        /** @var Product|null $product */
        $product = $orderItem->product;

        if (! $product) {
            throw ValidationException::withMessages([
                'prices' => 'Invoice generation failed because an order item is not linked to a valid product.',
            ]);
        }

        if (! $order->shop) {
            throw ValidationException::withMessages([
                'prices' => 'Invoice generation failed because the order is not linked to a valid shop.',
            ]);
        }

        $price = $this->approvedDailyPriceResolver->resolve($product, $order->shop, $order->business_date);

        return round((float) $price['price'], 2);
    }
}
