<?php

declare(strict_types=1);

namespace App\Services\ShopInvoices;

use App\Enums\Inventory\ProductGrade;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Services\Pricing\PriceBoardService;
use Illuminate\Support\Facades\DB;

class ShopInvoiceService
{
    public function __construct(
        private readonly PriceBoardService $priceBoardService,
    ) {}

    public function generateForBusinessDate(string $businessDate, int $userId): void
    {
        ShopOrder::query()
            ->with(['shop.priceGroup', 'items.product', 'invoice.items'])
            ->whereDate('business_date', $businessDate)
            ->where('state', 'approved')
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

            $existingItems = $invoice->items()->get()->keyBy('product_id');
            $activeProductIds = [];

            foreach ($order->items as $orderItem) {
                if ((float) ($orderItem->approved_qty ?? 0) <= 0) {
                    continue;
                }

                $activeProductIds[] = (int) $orderItem->product_id;
                $invoiceItem = $existingItems->get($orderItem->product_id) ?? new ShopInvoiceItem([
                    'shop_invoice_id' => $invoice->id,
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
                    $activeProductIds !== [],
                    fn ($query) => $query->whereNotIn('product_id', $activeProductIds),
                    fn ($query) => $query,
                )
                ->delete();

            return $this->recalculate($invoice->fresh('items'));
        });
    }

    public function applyDeliveryCheckin(ShopOrder $order, array $deliveredQtys, int $userId, ?string $deliveryNote = null): ShopInvoice
    {
        $invoice = $this->synchronizeOrderInvoice($order, $userId);

        return DB::transaction(function () use ($order, $invoice, $deliveredQtys, $userId, $deliveryNote): ShopInvoice {
            $hasDiscrepancy = false;
            $invoiceItems = $invoice->items()->get()->keyBy('product_id');

            foreach ($order->items as $orderItem) {
                $approvedQty = (float) ($orderItem->approved_qty ?? 0);
                $deliveredQty = (float) ($deliveredQtys[$orderItem->id] ?? 0);
                $shortageQty = max(0.00, $approvedQty - $deliveredQty);
                $hasDiscrepancy = $hasDiscrepancy || $shortageQty > 0.0001;

                $invoiceItem = $invoiceItems->get($orderItem->product_id);
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
            $invoice->update([
                'discount_total' => round((float) ($payload['discount_total'] ?? 0), 2),
                'paid_amount' => round((float) ($payload['paid_amount'] ?? 0), 2),
                'payment_note' => $payload['payment_note'] ?? null,
                'payment_approved_by' => $userId,
                'payment_approved_at' => now(),
            ]);

            $invoice = $this->recalculate($invoice->fresh('items'));

            if ($invoice->order) {
                $invoice->order->update([
                    'payment_status' => $invoice->payment_status,
                    'balance_amount' => $invoice->balance_amount,
                ]);
            }

            return $invoice;
        });
    }

    public function repriceInvoice(ShopInvoice $invoice, int $userId, ?string $reason = null): ShopInvoice
    {
        $invoice->loadMissing(['shop.priceGroup', 'items.product', 'order']);

        return DB::transaction(function () use ($invoice, $userId, $reason): ShopInvoice {
            foreach ($invoice->items as $invoiceItem) {
                $product = $invoiceItem->product;

                if (! $product) {
                    continue;
                }

                $price = $this->priceBoardService->sellingPriceFor($product, $invoice->shop, ProductGrade::GradeA);
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
            ->each(fn (ShopInvoice $invoice) => $this->repriceInvoice($invoice, $userId, $reason));
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
            $invoice->delivery_status === 'received_with_discrepancy' => 'delivery_review',
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
        if ((float) $orderItem->locked_selling_price > 0) {
            return round((float) $orderItem->locked_selling_price, 2);
        }

        /** @var Product|null $product */
        $product = $orderItem->product;

        if (! $product) {
            return 0.00;
        }

        $price = $this->priceBoardService->sellingPriceFor($product, $order->shop, ProductGrade::GradeA);

        return round((float) $price['price'], 2);
    }
}
