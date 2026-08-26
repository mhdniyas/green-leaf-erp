<?php

declare(strict_types=1);

namespace App\Services\ShopInvoices;

use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Shop;
use App\Models\ShopCredit;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopInvoicePaymentAllocation;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Services\Cashbook\InvoiceCashbookProjectionService;
use App\Services\Finance\JournalService;
use App\Services\Finance\OwnedShopAccountingService;
use App\Services\Pricing\ApprovedDailyPriceResolver;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ShopInvoiceService
{
    private const HISTORICAL_REPRICING_SETTING_KEY = 'allow_historical_invoice_repricing';

    public function __construct(
        private readonly ApprovedDailyPriceResolver $approvedDailyPriceResolver,
        private readonly JournalService $journalService,
        private readonly OwnedShopAccountingService $ownedShopAccountingService,
        private readonly PurchaserBusinessDayService $businessDayService,
        private readonly InvoiceCashbookProjectionService $invoiceCashbookProjectionService,
    ) {}

    /**
     * @return array{generated: int, skipped: array<int, array{order_number: string|null, shop_name: string|null, products: array<int, string>}>}
     */
    public function generateForBusinessDate(string $businessDate, int $userId): array
    {
        $summary = [
            'generated' => 0,
            'skipped' => [],
        ];

        ShopOrder::query()
            ->with(['shop.priceGroup', 'items.product', 'invoice.items'])
            ->whereDate('business_date', $businessDate)
            ->where('state', 'approved')
            ->where('order_source', '!=', 'admin_direct_purchase')
            ->get()
            ->each(function (ShopOrder $order) use ($userId, &$summary): void {
                $missingProducts = $this->missingDailyPriceProductNamesForOrder($order);

                if ($missingProducts !== []) {
                    $summary['skipped'][] = [
                        'order_number' => $order->order_number,
                        'shop_name' => $order->shop?->name,
                        'products' => $missingProducts,
                    ];

                    return;
                }

                $this->synchronizeOrderInvoice($order, $userId);
                $summary['generated']++;
            });

        return $summary;
    }

    public function synchronizeOrderInvoice(ShopOrder $order, int $userId): ShopInvoice
    {
        $order->loadMissing(['shop.priceGroup', 'items.product', 'invoice.items']);

        if ($order->invoice instanceof ShopInvoice && $order->invoice->isFinalLocked()) {
            return $order->invoice->fresh('items');
        }

        $missingProducts = $this->missingDailyPriceProductNamesForOrder($order);
        if ($missingProducts !== []) {
            throw ValidationException::withMessages([
                'prices' => 'Daily price matrix is missing for: '.implode(', ', $missingProducts).'. Update daily matrix and reload.',
            ]);
        }

        return DB::transaction(function () use ($order, $userId): ShopInvoice {
            $invoice = ShopInvoice::query()->firstOrNew([
                'shop_order_id' => $order->id,
            ]);

            if ($invoice->exists && $invoice->isFinalLocked()) {
                return $invoice->fresh('items');
            }

            if ($invoice->exists && $this->isHistoricalInvoice($invoice->business_date) && ! $this->allowHistoricalRepricing()) {
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

            $this->normalizeDuplicateInvoiceItems($invoice);

            $existingItems = $invoice->items()->get()->keyBy('product_id');
            $billableProductIds = [];

            $order->items
                ->filter(fn (ShopOrderItem $orderItem): bool => $this->shouldIncludeOrderItemInInvoice($orderItem))
                ->groupBy('product_id')
                ->each(function (Collection $orderItems, int|string $productId) use ($order, $invoice, $existingItems, &$billableProductIds): void {
                    /** @var ShopOrderItem $firstOrderItem */
                    $firstOrderItem = $orderItems->first();

                    $invoiceItem = $existingItems->get($productId) ?? new ShopInvoiceItem([
                        'shop_invoice_id' => $invoice->id,
                        'shop_order_item_id' => $firstOrderItem->id,
                        'product_id' => (int) $productId,
                    ]);

                    $dailyPrice = $this->dailyPriceForOrderItem($order, $firstOrderItem);
                    $billingPrice = $this->billingPriceForOrderItems($orderItems, $dailyPrice);
                    $unitPrice = $billingPrice['price'];
                    $priceUnit = $billingPrice['price_unit'];
                    $product = $firstOrderItem->product;
                    $approvedQty = (float) $orderItems->sum(fn (ShopOrderItem $item): float => (float) $item->approved_qty);
                    $computedDeliveredQty = (float) $orderItems->sum(function (ShopOrderItem $item): float {
                        if ($item->sorting_status === 'not_available' || $item->loadout_discrepancy_type === 'not_available') {
                            return 0.0;
                        }

                        // For products with actual_weight (e.g. FULL_BUNCH weighed at warehouse), use actual_weight.
                        return (float) ($item->actual_weight ?? $item->loaded_qty ?? $item->approved_qty ?? 0);
                    });

                    $isInvoiceDeliveryFinalized = in_array((string) $invoice->delivery_status, [
                        'received_full',
                        'received_with_discrepancy',
                        'approved_after_discrepancy',
                    ], true);

                    $deliveredQty = ($isInvoiceDeliveryFinalized && $invoiceItem->exists && $invoiceItem->delivered_qty !== null)
                        ? (float) $invoiceItem->delivered_qty
                        : $computedDeliveredQty;

                    $shortageQty = max(0.0, round($approvedQty - $deliveredQty, 3));
                    $excessQty = max(0.0, round($deliveredQty - $approvedQty, 3));

                    if ($deliveredQty <= $approvedQty) {
                        $excessQty = 0.0;
                    }
                    if ($deliveredQty >= $approvedQty) {
                        $shortageQty = 0.0;
                    }

                    $priceQuantity = $this->priceQuantityFor($product, $approvedQty, $priceUnit, $orderItems);
                    $deliveredPriceQuantity = $this->priceQuantityFor($product, $deliveredQty, $priceUnit, $orderItems);
                    $shortagePriceQuantity = $this->priceQuantityFor($product, $shortageQty, $priceUnit, $orderItems);
                    $excessPriceQuantity = $this->priceQuantityFor($product, $excessQty, $priceUnit, $orderItems);
                    $lineSubtotal = round($priceQuantity * $unitPrice, 2);
                    $shortageAmount = round($shortagePriceQuantity * $unitPrice, 2);
                    $excessAmount = round($excessPriceQuantity * $unitPrice, 2);

                    $invoiceItem->fill([
                        'shop_order_item_id' => $firstOrderItem->id,
                        'product_name' => $firstOrderItem->product?->name ?? 'Unknown Product',
                        'unit' => $firstOrderItem->unit,
                        'price_unit' => $priceUnit,
                        'approved_qty' => $approvedQty,
                        'price_quantity' => $priceQuantity,
                        'delivered_qty' => $deliveredQty,
                        'delivered_price_quantity' => $deliveredPriceQuantity,
                        'shortage_qty' => $shortageQty,
                        'shortage_price_quantity' => $shortagePriceQuantity,
                        'excess_qty' => $excessQty,
                        'excess_price_quantity' => $excessPriceQuantity,
                        'unit_price' => $unitPrice,
                        'line_subtotal' => $lineSubtotal,
                        'shortage_amount' => $shortageAmount,
                        'excess_amount' => $excessAmount,
                        'final_line_total' => round($lineSubtotal - $shortageAmount + $excessAmount, 2),
                    ]);
                    $invoiceItem->save();
                    $billableProductIds[] = (int) $productId;
                });

            $invoice->items()
                ->when(
                    $billableProductIds !== [],
                    fn ($query) => $query->whereNotIn('product_id', $billableProductIds),
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
            $invoiceItems = $invoice->items()->get()->keyBy('product_id');

            $order->items
                ->filter(fn (ShopOrderItem $orderItem): bool => $this->shouldIncludeOrderItemInInvoice($orderItem))
                ->groupBy('product_id')
                ->each(function (Collection $orderItems, int|string $productId) use ($invoiceItems, $deliveredQtys, &$hasDiscrepancy): void {
                    /** @var ShopOrderItem $firstOrderItem */
                    $firstOrderItem = $orderItems->first();
                    $approvedQty = (float) $orderItems->sum(fn (ShopOrderItem $item): float => (float) $item->approved_qty);
                    $deliveredQty = (float) $orderItems->sum(
                        fn (ShopOrderItem $item): float => (float) ($deliveredQtys[$item->id] ?? 0)
                    );
                    $shortageQty = max(0.00, $approvedQty - $deliveredQty);
                    $excessQty = max(0.00, $deliveredQty - $approvedQty);
                    $hasDiscrepancy = $hasDiscrepancy || $shortageQty > 0.0001 || $excessQty > 0.0001;

                    $invoiceItem = $invoiceItems->get($productId);
                    if (! $invoiceItem) {
                        return;
                    }

                    $product = $firstOrderItem->product;
                    $priceUnit = (string) ($invoiceItem->price_unit ?: $product?->unit ?: $invoiceItem->unit);
                    $deliveredPriceQuantity = $this->priceQuantityFor($product, $deliveredQty, $priceUnit, $orderItems);
                    $shortagePriceQuantity = $this->priceQuantityFor($product, $shortageQty, $priceUnit, $orderItems);
                    $excessPriceQuantity = $this->priceQuantityFor($product, $excessQty, $priceUnit, $orderItems);
                    $shortageAmount = round($shortagePriceQuantity * (float) $invoiceItem->unit_price, 2);
                    $excessAmount = round($excessPriceQuantity * (float) $invoiceItem->unit_price, 2);

                    $invoiceItem->update([
                        'delivered_qty' => $deliveredQty,
                        'delivered_price_quantity' => $deliveredPriceQuantity,
                        'shortage_qty' => $shortageQty,
                        'shortage_price_quantity' => $shortagePriceQuantity,
                        'excess_qty' => $excessQty,
                        'excess_price_quantity' => $excessPriceQuantity,
                        'shortage_amount' => $shortageAmount,
                        'excess_amount' => $excessAmount,
                        'final_line_total' => round((float) $invoiceItem->line_subtotal - $shortageAmount + $excessAmount, 2),
                    ]);

                    foreach ($orderItems as $orderItem) {
                        $itemApprovedQty = (float) ($orderItem->approved_qty ?? 0);
                        $itemDeliveredQty = (float) ($deliveredQtys[$orderItem->id] ?? 0);
                        $itemShortageQty = max(0.00, $itemApprovedQty - $itemDeliveredQty);
                        $itemExcessQty = max(0.00, $itemDeliveredQty - $itemApprovedQty);
                        $singleItemContext = collect([$orderItem]);
                        $itemShortageAmount = round($this->priceQuantityFor($orderItem->product, $itemShortageQty, $priceUnit, $singleItemContext) * (float) $invoiceItem->unit_price, 2);
                        $itemExcessAmount = round($this->priceQuantityFor($orderItem->product, $itemExcessQty, $priceUnit, $singleItemContext) * (float) $invoiceItem->unit_price, 2);

                        $orderItem->update([
                            'delivered_qty' => $itemDeliveredQty,
                            'shortage_qty' => $itemShortageQty,
                            'excess_qty' => $itemExcessQty,
                            'shortage_value' => $itemShortageAmount,
                            'excess_value' => $itemExcessAmount,
                        ]);
                    }
                });

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
                'total_excess_value' => $invoice->excess_total,
                'balance_amount' => $invoice->balance_amount,
                'payment_status' => $invoice->payment_status,
            ]);

            if (! $hasDiscrepancy) {
                $invoice = $this->markFinalized($invoice, $userId, 'shop_delivery_finalized', 'Shop delivery submitted without discrepancy.');
                $this->syncOwnedShopBalanceForInvoice($invoice, $userId);
            }

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

        if ($invoice->isFinalized()) {
            return $invoice->fresh('items');
        }

        $invoice->update([
            'delivery_status' => 'approved_after_discrepancy',
            'delivery_confirmed_by' => $invoice->delivery_confirmed_by ?? $userId,
            'delivery_confirmed_at' => $invoice->delivery_confirmed_at ?? now(),
        ]);

        $invoice = $this->recalculate($invoice->fresh('items'));
        $invoice = $this->markFinalized($invoice, $userId, 'admin_delivery_review_finalized', 'Admin finalized delivery discrepancy.');

        $order->update([
            'is_delivered' => true,
            'delivered_at' => now(),
            'delivery_status' => (float) $invoice->shortage_total > 0 ? 'partially_delivered' : 'delivered',
            'payment_status' => $invoice->payment_status,
            'balance_amount' => $invoice->balance_amount,
            'total_shortage_value' => $invoice->shortage_total,
            'total_excess_value' => $invoice->excess_total,
        ]);

        $this->syncOwnedShopBalanceForInvoice($invoice, $userId);

        return $invoice;
    }

    /**
     * @param  array{discount_total?: float|int|string|null, paid_amount?: float|int|string|null, payment_note?: string|null}  $payload
     */
    public function approvePayment(ShopInvoice $invoice, array $payload, int $userId): ShopInvoice
    {
        return DB::transaction(function () use ($invoice, $payload, $userId): ShopInvoice {
            $this->assertInvoiceFinalizedForMoneyMutation($invoice);

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
                'payment_method' => $payload['payment_method'] ?? 'cash',
                'payment_reference' => filled($payload['payment_reference'] ?? null) ? trim((string) $payload['payment_reference']) : null,
                'payment_date' => $payload['payment_date'] ?? now()->toDateString(),
                'requested_amount' => $requestedAmount,
                'applied_amount' => 0,
                'credit_amount' => 0,
                'status' => 'pending',
                'reconciliation_status' => 'floating',
                'reconciled_amount' => 0,
                'floating_amount' => $requestedAmount,
                'shop_advance_amount' => 0,
                'shop_note' => filled($payload['shop_note'] ?? null) ? trim((string) $payload['shop_note']) : null,
            ]);
        });
    }

    /**
     * @param  array{amount_mode:string, amount?: float|int|string|null, shop_note?: string|null}  $payload
     */
    public function requestShopBalancePayment(Shop $shop, Carbon $balanceDate, float $closingBalance, array $payload, int $userId): ShopInvoicePaymentRequest
    {
        return DB::transaction(function () use ($shop, $balanceDate, $closingBalance, $payload, $userId): ShopInvoicePaymentRequest {
            if (! $shop->isOwnedAccountingEnabled()) {
                throw ValidationException::withMessages([
                    'amount_mode' => 'Closing balance payments are only available for owned accounting shops.',
                ]);
            }

            $requestedAmount = round((float) ($payload['amount'] ?? 0), 2);
            $payableBalance = round(max(0, $closingBalance, $requestedAmount), 2);

            if ($payableBalance <= 0.0) {
                throw ValidationException::withMessages([
                    'amount' => 'Enter an amount greater than zero to submit a payment for reconciliation.',
                ]);
            }

            $existingPendingRequest = ShopInvoicePaymentRequest::query()
                ->where('shop_id', $shop->id)
                ->where('status', 'pending')
                ->first();

            if ($existingPendingRequest instanceof ShopInvoicePaymentRequest) {
                throw ValidationException::withMessages([
                    'amount' => 'A payment request for this shop is already waiting for approval.',
                ]);
            }

            $requestedAmount = $requestedAmount > 0.0 ? $requestedAmount : $payableBalance;

            if ($requestedAmount <= 0.0) {
                throw ValidationException::withMessages([
                    'amount' => 'Requested amount must be greater than zero.',
                ]);
            }

            return ShopInvoicePaymentRequest::query()->create([
                'shop_invoice_id' => null,
                'shop_id' => $shop->id,
                'requested_by' => $userId,
                'request_type' => 'shop_balance',
                'payment_method' => $payload['payment_method'] ?? 'cash',
                'payment_reference' => filled($payload['payment_reference'] ?? null) ? trim((string) $payload['payment_reference']) : null,
                'payment_date' => $payload['payment_date'] ?? $balanceDate->toDateString(),
                'requested_amount' => $requestedAmount,
                'approved_amount' => null,
                'applied_amount' => 0,
                'credit_amount' => 0,
                'status' => 'pending',
                'reconciliation_status' => 'floating',
                'reconciled_amount' => 0,
                'floating_amount' => $requestedAmount,
                'shop_advance_amount' => 0,
                'shop_note' => filled($payload['shop_note'] ?? null)
                    ? trim((string) $payload['shop_note'])
                    : 'Closing balance payment for '.$balanceDate->toDateString(),
            ]);
        });
    }

    public function reviewPaymentRequest(ShopInvoicePaymentRequest $paymentRequest, string $decision, int $userId, ?string $adminNote = null): ShopInvoicePaymentRequest
    {
        return $this->reviewPaymentRequestWithAmount($paymentRequest, $decision, $userId, $adminNote);
    }

    public function reviewPaymentRequestWithAmount(ShopInvoicePaymentRequest $paymentRequest, string $decision, int $userId, ?string $adminNote = null, float|int|string|null $approvedAmountOverride = null): ShopInvoicePaymentRequest
    {
        return DB::transaction(function () use ($paymentRequest, $decision, $userId, $adminNote, $approvedAmountOverride): ShopInvoicePaymentRequest {
            $paymentRequest = ShopInvoicePaymentRequest::query()
                ->whereKey($paymentRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $paymentRequest->loadMissing('invoice', 'allocations');

            if ($paymentRequest->status !== 'pending') {
                throw ValidationException::withMessages([
                    'decision' => 'This payment request has already been reviewed.',
                ]);
            }

            if ($decision === 'approve') {
                $invoice = $paymentRequest->invoice;
                $approvedAmount = $approvedAmountOverride === null
                    ? round((float) $paymentRequest->requested_amount, 2)
                    : round((float) $approvedAmountOverride, 2);

                if ($approvedAmount <= 0.0) {
                    throw ValidationException::withMessages([
                        'admin_verified_amount' => 'Verified amount must be greater than zero.',
                    ]);
                }

                if ($paymentRequest->payment_method === 'cheque' && $paymentRequest->cheque_status !== 'cleared') {
                    throw ValidationException::withMessages([
                        'cheque_status' => 'Cheque payments can only be approved after the cheque is cleared.',
                    ]);
                }

                if ($paymentRequest->request_type === 'shop_balance') {
                    $paymentRequest->update([
                        'status' => 'approved',
                        'admin_verified_amount' => $approvedAmount,
                        'approved_amount' => $approvedAmount,
                        'applied_amount' => 0,
                        'credit_amount' => $approvedAmount,
                        'admin_note' => filled($adminNote) ? trim((string) $adminNote) : null,
                        'reviewed_by' => $userId,
                        'reviewed_at' => now(),
                    ]);

                    $paymentRequest = $paymentRequest->fresh(['shop', 'invoice', 'requestedBy', 'reviewedBy', 'allocations.invoice']);
                    $this->recordApprovedShopBalanceCashMovement($paymentRequest, $userId);

                    return $paymentRequest;
                }

                if (! $invoice instanceof ShopInvoice) {
                    throw ValidationException::withMessages([
                        'decision' => 'The related invoice could not be found.',
                    ]);
                }

                $appliedAmount = $this->allocateShopPaymentToPendingInvoices($paymentRequest, $approvedAmount, $userId, $adminNote);
                $creditAmount = round(max(0, $approvedAmount - $appliedAmount), 2);

                $paymentRequest->update([
                    'status' => 'approved',
                    'admin_verified_amount' => $approvedAmount,
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
                    'admin_verified_amount' => null,
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
        if ($paymentRequest->request_type === 'shop_balance') {
            return [
                'total_due' => 0.0,
                'applied_amount' => 0.0,
                'credit_amount' => round((float) $paymentRequest->requested_amount, 2),
                'invoices' => [],
            ];
        }

        return $this->allocationPreviewForShop(
            (int) $paymentRequest->shop_id,
            (float) $paymentRequest->requested_amount,
        );
    }

    /**
     * @return array{total_due: float, applied_amount: float, credit_amount: float, invoices: array<int, array{invoice: ShopInvoice, amount: float}>}
     */
    public function allocationPreviewForShop(int $shopId, float $amount): array
    {
        $pendingInvoices = $this->pendingInvoicesForShop($shopId);
        $remainingAmount = round(max(0, $amount), 2);
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
            'credit_amount' => round(max(0, round($amount, 2) - $appliedAmount), 2),
            'invoices' => $invoices,
        ];
    }

    /**
     * @return Collection<int, ShopInvoice>
     */
    public function pendingInvoicesForShop(int $shopId): Collection
    {
        return ShopInvoice::query()
            ->where('shop_id', $shopId)
            ->where('balance_amount', '>', 0)
            ->with(['order'])
            ->oldest('business_date')
            ->oldest('id')
            ->get();
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
            $this->assertInvoiceFinalizedForMoneyMutation($invoice);

            $paymentApplication = (string) ($payload['payment_application'] ?? 'invoice_pending');
            $currentPaidAmount = round((float) $invoice->paid_amount, 2);
            $paidAmount = round((float) $payload['paid_amount'], 2);
            $approvedAmount = $paymentApplication === 'client_balance'
                ? $paidAmount
                : round($paidAmount - $currentPaidAmount, 2);
            $adminNote = filled($payload['payment_note'] ?? null) ? trim((string) $payload['payment_note']) : null;

            if ($approvedAmount <= 0.0) {
                throw ValidationException::withMessages([
                    'paid_amount' => $paymentApplication === 'client_balance'
                        ? 'Received amount must be greater than zero.'
                        : 'Paid amount must be greater than the current collected amount.',
                ]);
            }

            $paymentRequest = ShopInvoicePaymentRequest::query()->create([
                'shop_invoice_id' => $invoice->id,
                'shop_id' => $invoice->shop_id,
                'requested_by' => $userId,
                'request_type' => $paymentApplication === 'client_balance' ? 'admin_client_balance' : 'admin_manual',
                'payment_method' => $payload['payment_method'] ?? 'cash',
                'payment_reference' => filled($payload['payment_reference'] ?? null) ? trim((string) $payload['payment_reference']) : null,
                'payment_date' => $payload['payment_date'] ?? $invoice->business_date?->toDateString() ?? now()->toDateString(),
                'requested_amount' => $approvedAmount,
                'approved_amount' => $approvedAmount,
                'applied_amount' => 0,
                'credit_amount' => $paymentApplication === 'client_balance' ? $approvedAmount : 0,
                'status' => 'approved',
                'shop_note' => $paymentApplication === 'client_balance'
                    ? 'Admin recorded client balance payment.'
                    : 'Admin recorded payment received.',
                'admin_note' => $adminNote,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ]);

            if ($paymentApplication === 'client_balance') {
                $paymentRequest->load('shop');
                $this->recordApprovedShopBalanceCashMovement($paymentRequest, $userId);

                return $paymentRequest->fresh(['invoice', 'requestedBy', 'reviewedBy', 'allocations.invoice']);
            }

            $appliedAmount = $this->allocateShopPaymentToPendingInvoices($paymentRequest, $approvedAmount, $userId, $adminNote);
            $creditAmount = round(max(0, $approvedAmount - $appliedAmount), 2);

            $paymentRequest->update([
                'applied_amount' => $appliedAmount,
                'credit_amount' => $creditAmount,
            ]);

            return $paymentRequest->fresh(['invoice', 'requestedBy', 'reviewedBy', 'allocations.invoice']);
        });
    }

    /**
     * @param  array{discount_total: float|int|string, discount_note: string}  $payload
     */
    public function applyAdminDiscount(ShopInvoice $invoice, array $payload, int $userId): ShopInvoice
    {
        return DB::transaction(function () use ($invoice, $payload, $userId): ShopInvoice {
            $invoice->refresh();

            if ($invoice->isFinalized()) {
                throw ValidationException::withMessages([
                    'discount_total' => 'This shop invoice is finalized. Reopen/correction flow is required before changing discounts.',
                ]);
            }

            $before = [
                'discount_total' => round((float) $invoice->discount_total, 2),
                'discount_note' => $invoice->discount_note,
                'final_total' => round((float) $invoice->final_total, 2),
            ];

            $invoice->update([
                'discount_total' => round((float) $payload['discount_total'], 2),
                'discount_note' => trim((string) $payload['discount_note']),
                'discount_approved_by' => $userId,
                'discount_approved_at' => now(),
            ]);

            $invoice = $this->recalculate($invoice->fresh('items'));
            $this->recordInvoiceActivity($invoice, 'discount_updated', $userId, $before, [
                'discount_total' => round((float) $invoice->discount_total, 2),
                'discount_note' => $invoice->discount_note,
                'final_total' => round((float) $invoice->final_total, 2),
            ], trim((string) $payload['discount_note']), 'admin_discount');

            if ($invoice->order) {
                $invoice->order->update([
                    'cash_discrepancy' => round((float) $invoice->final_total - (float) $invoice->paid_amount, 2),
                    'payment_status' => $invoice->payment_status,
                    'balance_amount' => $invoice->balance_amount,
                ]);
            }

            $this->syncOwnedShopBalanceForInvoice($invoice, $userId);

            return $invoice;
        });
    }

    public function updateItemFinalQuantityAndPrice(
        ShopInvoice $invoice,
        ShopInvoiceItem $invoiceItem,
        float $finalQty,
        float $finalPrice,
        int $userId,
        ?string $note = null,
    ): ShopInvoice {
        return DB::transaction(function () use ($invoice, $invoiceItem, $finalQty, $finalPrice, $userId, $note): ShopInvoice {
            $invoice = ShopInvoice::query()
                ->with(['items.product', 'order'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $invoiceItem = ShopInvoiceItem::query()
                ->with('product')
                ->where('shop_invoice_id', $invoice->id)
                ->lockForUpdate()
                ->findOrFail($invoiceItem->id);

            $priceUnit = (string) ($invoiceItem->price_unit ?: $invoiceItem->unit);
            $approvedQty = (float) $invoiceItem->approved_qty;
            $beforeQty = round((float) ($invoiceItem->delivered_price_quantity ?: $invoiceItem->price_quantity ?: $invoiceItem->delivered_qty ?: $approvedQty), 4);
            $beforePrice = round((float) $invoiceItem->unit_price, 2);
            $beforeAmount = round((float) ($invoiceItem->final_line_total ?: $invoiceItem->line_subtotal), 2);
            $newQty = round($finalQty, 4);
            $newPrice = round($finalPrice, 2);
            $deliveredQty = round($finalQty, 2);
            $shortageQty = round(max(0, $approvedQty - $deliveredQty), 2);
            $excessQty = round(max(0, $deliveredQty - $approvedQty), 2);
            $deliveredPriceQuantity = $this->priceQuantityFor($invoiceItem->product, $newQty, $priceUnit);
            $shortagePriceQuantity = $this->priceQuantityFor($invoiceItem->product, $shortageQty, $priceUnit);
            $excessPriceQuantity = $this->priceQuantityFor($invoiceItem->product, $excessQty, $priceUnit);
            $lineSubtotal = round((float) $invoiceItem->price_quantity * $newPrice, 2);
            $shortageAmount = round($shortagePriceQuantity * $newPrice, 2);
            $excessAmount = round($excessPriceQuantity * $newPrice, 2);
            $finalLineTotal = round($deliveredPriceQuantity * $newPrice, 2);

            $invoiceItem->update([
                'delivered_qty' => $deliveredQty,
                'delivered_price_quantity' => $deliveredPriceQuantity,
                'shortage_qty' => $shortageQty,
                'shortage_price_quantity' => $shortagePriceQuantity,
                'excess_qty' => $excessQty,
                'excess_price_quantity' => $excessPriceQuantity,
                'unit_price' => $newPrice,
                'line_subtotal' => $lineSubtotal,
                'shortage_amount' => $shortageAmount,
                'excess_amount' => $excessAmount,
                'final_line_total' => $finalLineTotal,
            ]);

            $invoice->update([
                'admin_price_note' => $note,
                'price_updated_by' => $userId,
                'price_updated_at' => now(),
            ]);

            $invoice = $this->recalculate($invoice->fresh('items'));
            $this->recordInvoiceActivity($invoice, 'item_adjusted', $userId, [
                'item_id' => $invoiceItem->id,
                'product_id' => $invoiceItem->product_id,
                'product_name' => $invoiceItem->product?->name ?? $invoiceItem->product_name,
                'qty' => $beforeQty,
                'price' => $beforePrice,
                'amount' => $beforeAmount,
            ], [
                'item_id' => $invoiceItem->id,
                'product_id' => $invoiceItem->product_id,
                'product_name' => $invoiceItem->product?->name ?? $invoiceItem->product_name,
                'qty' => $newQty,
                'price' => $newPrice,
                'amount' => $finalLineTotal,
            ], $note, 'admin_item_adjustment');

            if ($invoice->order) {
                $invoice->order->update([
                    'cash_discrepancy' => round((float) $invoice->final_total - (float) $invoice->paid_amount, 2),
                    'payment_status' => $invoice->payment_status,
                    'balance_amount' => $invoice->balance_amount,
                ]);
            }

            $this->syncOwnedShopBalanceForInvoice($invoice, $userId);

            return $invoice;
        });
    }

    public function repriceInvoice(ShopInvoice $invoice, int $userId, ?string $reason = null): ShopInvoice
    {
        $invoice->loadMissing(['shop.priceGroup', 'items.product', 'order.items.product']);

        if ($invoice->isFinalLocked()) {
            throw ValidationException::withMessages([
                'invoice' => 'This shop invoice is finalized. Create a price adjustment instead of repricing the original invoice.',
            ]);
        }

        if ($this->isHistoricalInvoice($invoice->business_date) && ! $this->allowHistoricalRepricing()) {
            throw ValidationException::withMessages([
                'invoice' => 'Historical invoice repricing is disabled. Ask an admin to enable the setting before updating past invoices.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $userId, $reason): ShopInvoice {
            $orderItemsByProduct = $invoice->order
                ? $invoice->order->items
                    ->filter(fn (ShopOrderItem $item): bool => $this->shouldIncludeOrderItemInInvoice($item))
                    ->filter(fn (ShopOrderItem $item): bool => (bool) ($item->product?->is_active ?? true))
                    ->groupBy('product_id')
                : collect();

            foreach ($invoice->items as $invoiceItem) {
                $product = $invoiceItem->product;

                if (! $product || ! $product->is_active) {
                    continue;
                }

                $approvedQty = (float) $invoiceItem->approved_qty;
                $deliveredQty = (float) $invoiceItem->delivered_qty;
                $shortageQty = (float) $invoiceItem->shortage_qty;
                $excessQty = (float) $invoiceItem->excess_qty;

                if ($deliveredQty <= $approvedQty) {
                    $excessQty = 0.0;
                }
                if ($deliveredQty >= $approvedQty) {
                    $shortageQty = 0.0;
                }

                try {
                    $price = $this->approvedDailyPriceResolver->resolve($product, $invoice->shop, $invoice->business_date);
                    $unitPrice = round((float) $price['price'], 2);
                    $priceUnit = (string) $price['price_unit'];
                    $productOrderItems = $orderItemsByProduct->get((int) $product->id);
                    $orderItemContext = $productOrderItems instanceof Collection ? $productOrderItems : null;
                    $priceQuantity = $this->priceQuantityFor($product, $approvedQty, $priceUnit, $orderItemContext);
                    $deliveredPriceQuantity = $this->priceQuantityFor($product, $deliveredQty, $priceUnit, $orderItemContext);
                    $shortagePriceQuantity = $this->priceQuantityFor($product, $shortageQty, $priceUnit, $orderItemContext);
                    $excessPriceQuantity = $this->priceQuantityFor($product, $excessQty, $priceUnit, $orderItemContext);
                    $lineSubtotal = round($priceQuantity * $unitPrice, 2);
                    $shortageAmount = round($shortagePriceQuantity * $unitPrice, 2);
                    $excessAmount = round($excessPriceQuantity * $unitPrice, 2);

                    $invoiceItem->update([
                        'price_unit' => $priceUnit,
                        'price_quantity' => $priceQuantity,
                        'delivered_price_quantity' => $deliveredPriceQuantity,
                        'shortage_qty' => $shortageQty,
                        'shortage_price_quantity' => $shortagePriceQuantity,
                        'excess_qty' => $excessQty,
                        'excess_price_quantity' => $excessPriceQuantity,
                        'unit_price' => $unitPrice,
                        'line_subtotal' => $lineSubtotal,
                        'shortage_amount' => $shortageAmount,
                        'excess_amount' => $excessAmount,
                        'final_line_total' => round($lineSubtotal - $shortageAmount + $excessAmount, 2),
                    ]);
                } catch (Throwable $e) {
                    // Skip this product if pricing data is missing or invalid.
                    // The loadout save must not be blocked by a single product's price issue.
                    // The unpriced_product_names list shown in the UI will surface these products.
                    Log::warning('repriceInvoice: skipped product due to pricing error', [
                        'invoice_id' => $invoice->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'business_date' => $invoice->business_date,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }
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
                    'total_excess_value' => $invoice->excess_total,
                    'balance_amount' => $invoice->balance_amount,
                ]);
            }

            return $invoice;
        });
    }

    /**
     * @return array{repriced: int, skipped: array<int, array{order_number: string|null, shop_name: string|null, products: array<int, string>}>}
     */
    public function repriceAllForBusinessDate(string $businessDate, int $userId, ?string $reason = null): array
    {
        $summary = [
            'repriced' => 0,
            'skipped' => [],
        ];

        if ($this->isHistoricalInvoiceDate($businessDate) && ! $this->allowHistoricalRepricing()) {
            $summary['skipped'][] = [
                'order_number' => null,
                'shop_name' => null,
                'products' => ['Historical invoice repricing is disabled.'],
            ];

            return $summary;
        }

        ShopInvoice::query()
            ->with(['shop.priceGroup', 'items.product', 'order'])
            ->whereDate('business_date', $businessDate)
            ->get()
            ->reject(fn (ShopInvoice $invoice): bool => $invoice->isFinalLocked())
            ->each(function (ShopInvoice $invoice) use ($userId, $reason, &$summary): void {
                $missingProducts = $this->missingDailyPriceProductNamesForInvoice($invoice);

                if ($missingProducts !== []) {
                    $summary['skipped'][] = [
                        'order_number' => $invoice->order?->order_number,
                        'shop_name' => $invoice->shop?->name,
                        'products' => $missingProducts,
                    ];

                    return;
                }

                try {
                    $this->repriceInvoice($invoice, $userId, $reason);
                    $summary['repriced']++;
                } catch (ValidationException $exception) {
                    $message = $exception->validator?->errors()->first('prices')
                        ?? $exception->getMessage()
                        ?? 'Invoice repricing failed.';

                    $summary['skipped'][] = [
                        'order_number' => $invoice->order?->order_number,
                        'shop_name' => $invoice->shop?->name,
                        'products' => [$message],
                    ];
                }
            });

        return $summary;
    }

    private function allowHistoricalRepricing(): bool
    {
        return filter_var(
            BusinessSetting::query()
                ->where('key', self::HISTORICAL_REPRICING_SETTING_KEY)
                ->value('value') ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
    }

    private function isHistoricalInvoice(Carbon|string $businessDate): bool
    {
        return Carbon::parse($businessDate)->toDateString() < $this->businessDayService->operationalDate()->toDateString();
    }

    private function isHistoricalInvoiceDate(string $businessDate): bool
    {
        return Carbon::parse($businessDate)->toDateString() < $this->businessDayService->operationalDate()->toDateString();
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

    private function recordApprovedShopBalanceCashMovement(ShopInvoicePaymentRequest $paymentRequest, int $userId): void
    {
        if (! in_array($paymentRequest->request_type, ['shop_balance', 'admin_client_balance'], true)) {
            return;
        }

        $paymentRequest->loadMissing('shop');

        if (! $paymentRequest->shop instanceof Shop || ! $paymentRequest->shop->isOwnedAccountingEnabled()) {
            return;
        }

        $amount = round((float) $paymentRequest->approved_amount, 2);

        if ($amount <= 0.0) {
            return;
        }

        $businessDate = ($paymentRequest->payment_date ?? $paymentRequest->reviewed_at ?? now())->format('Y-m-d');
        $description = 'Approved shop balance payment #'.$paymentRequest->id;

        $exists = ShopCredit::query()
            ->where('shop_id', $paymentRequest->shop_id)
            ->where('type', 'out')
            ->where('amount', $amount)
            ->where('description', $description)
            ->exists();

        if ($exists) {
            return;
        }

        ShopCredit::query()->create([
            'shop_id' => $paymentRequest->shop_id,
            'type' => 'out',
            'amount' => $amount,
            'description' => $description,
            'admin_note' => $paymentRequest->admin_note,
            'created_by' => $paymentRequest->requested_by,
            'business_date' => $businessDate,
            'status' => 'approved',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ]);

        $this->ownedShopAccountingService->syncStoredClosingBalancesFromDate(
            $paymentRequest->shop,
            Carbon::parse($businessDate),
            $userId,
        );
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
    public function recalculate(ShopInvoice $invoice): ShopInvoice
    {
        $invoice->loadMissing('items');

        // Store subtotal exactly as listed line amounts (1 + 2 + 3 ...).
        $subtotal = round((float) $invoice->items->sum('final_line_total'), 2);
        $shortageTotal = round((float) $invoice->items->sum('shortage_amount'), 2);
        $excessTotal = round((float) $invoice->items->sum('excess_amount'), 2);
        $discountTotal = round((float) $invoice->discount_total, 2);
        $finalTotal = round(max(0.00, $subtotal - $discountTotal), 2);
        $paidAmount = round((float) $invoice->paid_amount, 2);
        $balanceAmount = round(max(0.00, $finalTotal - $paidAmount), 2);

        $paymentStatus = match (true) {
            $finalTotal <= 0.00 && $balanceAmount <= 0.00 => 'paid',
            $paidAmount <= 0.00 => 'unpaid',
            $balanceAmount > 0.00 => 'partially_paid',
            default => 'paid',
        };

        $status = match (true) {
            in_array($invoice->delivery_status, ['awaiting_review', 'received_with_discrepancy'], true) => 'delivery_review',
            $paymentStatus === 'paid' => 'paid',
            in_array($invoice->delivery_status, ['received_full', 'approved_after_discrepancy'], true) => 'payment_pending',
            default => 'generated',
        };

        $invoice->update([
            'subtotal' => $subtotal,
            'shortage_total' => $shortageTotal,
            'excess_total' => $excessTotal,
            'final_total' => $finalTotal,
            'balance_amount' => $balanceAmount,
            'payment_status' => $paymentStatus,
            'status' => $status,
        ]);

        $invoice = $invoice->fresh('items');

        if ($invoice->isFinalized()) {
            $this->invoiceCashbookProjectionService->syncInvoice($invoice);
        }

        return $invoice;
    }

    public function markFinalized(ShopInvoice $invoice, int $userId, string $event, string $reason): ShopInvoice
    {
        $invoice = ShopInvoice::query()->with('items')->lockForUpdate()->findOrFail($invoice->id);

        if ($invoice->finalized_at !== null) {
            return $invoice->fresh('items');
        }

        $before = [
            'status' => $invoice->status,
            'delivery_status' => $invoice->delivery_status,
            'final_total' => round((float) $invoice->final_total, 2),
            'balance_amount' => round((float) $invoice->balance_amount, 2),
        ];

        $paymentStatus = (float) $invoice->balance_amount <= 0.0001 ? 'paid' : $invoice->payment_status;
        $invoice->forceFill([
            'status' => $paymentStatus === 'paid' ? 'paid' : 'payment_pending',
            'payment_status' => $paymentStatus,
            'finalized_by' => $userId,
            'finalized_at' => now(),
        ])->save();

        $invoice = $invoice->fresh('items');
        $this->recordInvoiceActivity($invoice, $event, $userId, $before, [
            'status' => $invoice->status,
            'delivery_status' => $invoice->delivery_status,
            'final_total' => round((float) $invoice->final_total, 2),
            'balance_amount' => round((float) $invoice->balance_amount, 2),
            'finalized_at' => $invoice->finalized_at?->toDateTimeString(),
        ], $reason, 'finalization');
        $this->invoiceCashbookProjectionService->syncInvoice($invoice, $userId);

        return $invoice;
    }

    private function assertInvoiceFinalizedForMoneyMutation(ShopInvoice $invoice): void
    {
        if (! $invoice->isFinalized()) {
            throw ValidationException::withMessages([
                'invoice' => 'Money changes can only be recorded against a finalized shop invoice.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function recordInvoiceActivity(ShopInvoice $invoice, string $event, int $userId, array $before, array $after, ?string $reason, string $source): void
    {
        activity('shop_invoice')
            ->performedOn($invoice)
            ->causedBy($userId)
            ->event($event)
            ->withProperties([
                'before' => $before,
                'after' => $after,
                'reason' => $reason,
                'source' => $source,
            ])
            ->log($event);
    }

    /**
     * @param  Collection<int, ShopOrderItem>|null  $orderItems
     * @return array{
     *     delivered_qty: float,
     *     delivered_price_quantity: float,
     *     shortage_qty: float,
     *     shortage_price_quantity: float,
     *     excess_qty: float,
     *     excess_price_quantity: float,
     *     shortage_amount: float,
     *     excess_amount: float,
     *     final_line_total: float
     * }
     */
    public function calculateDeliveryAdjustmentForInvoiceItem(
        ShopInvoiceItem $invoiceItem,
        float $deliveredQty,
        float $shortageQty,
        float $excessQty,
        ?Collection $orderItems = null,
    ): array {
        $invoiceItem->loadMissing('product');

        $product = $invoiceItem->product;
        $priceUnit = (string) ($invoiceItem->price_unit ?: $product?->unit ?: $invoiceItem->unit);
        $unitPrice = (float) $invoiceItem->unit_price;

        $deliveredPriceQuantity = $this->priceQuantityFor($product, $deliveredQty, $priceUnit, $orderItems);
        $shortagePriceQuantity = $this->priceQuantityFor($product, $shortageQty, $priceUnit, $orderItems);
        $excessPriceQuantity = $this->priceQuantityFor($product, $excessQty, $priceUnit, $orderItems);
        $shortageAmount = round($shortagePriceQuantity * $unitPrice, 2);
        $excessAmount = round($excessPriceQuantity * $unitPrice, 2);
        $finalLineTotal = round($deliveredPriceQuantity * $unitPrice, 2);

        return [
            'delivered_qty' => round($deliveredQty, 2),
            'delivered_price_quantity' => $deliveredPriceQuantity,
            'shortage_qty' => round($shortageQty, 2),
            'shortage_price_quantity' => $shortagePriceQuantity,
            'excess_qty' => round($excessQty, 2),
            'excess_price_quantity' => $excessPriceQuantity,
            'shortage_amount' => $shortageAmount,
            'excess_amount' => $excessAmount,
            'final_line_total' => $finalLineTotal,
        ];
    }

    private function shouldPostPaymentToJournal(ShopInvoice $invoice): bool
    {
        $invoice->loadMissing('shop');

        return ! $invoice->shop?->isOwnedAccountingEnabled();
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

    /**
     * @return array{price: float, price_unit: string}
     */
    private function dailyPriceForOrderItem(ShopOrder $order, ShopOrderItem $orderItem): array
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

        return [
            'price' => round((float) $price['price'], 2),
            'price_unit' => (string) $price['price_unit'],
        ];
    }

    /**
     * @param  Collection<int, ShopOrderItem>|null  $orderItems
     */
    private function priceQuantityFor(?Product $product, float $baseQuantity, string $priceUnit, ?Collection $orderItems = null): float
    {
        if (! $product) {
            throw ValidationException::withMessages([
                'prices' => 'Invoice calculation failed because an invoice item is not linked to a valid product.',
            ]);
        }

        $normalizedPriceUnit = ProductUnit::normalizeUnit($priceUnit ?: $product->unit);
        $normalizedBaseUnit = ProductUnit::normalizeUnit((string) $product->unit);

        if ($normalizedPriceUnit === $normalizedBaseUnit) {
            return round($baseQuantity, 4);
        }

        // Live product_units is the source of truth so admin corrections apply
        // retroactively to every order without needing manual data patches.
        $product->loadMissing('orderUnits');
        $conversionToBase = $product->orderUnits
            ->first(fn (ProductUnit $unit): bool => ProductUnit::normalizeUnit($unit->unit) === $normalizedPriceUnit)
            ?->conversion_to_base;

        if (($conversionToBase === null || (float) $conversionToBase <= 0) && $orderItems instanceof Collection && $orderItems->isNotEmpty()) {
            $conversionToBase = $orderItems
                ->first(function (ShopOrderItem $item) use ($normalizedPriceUnit, $product): bool {
                    return (int) $item->product_id === (int) $product->id
                        && ProductUnit::normalizeUnit((string) ($item->requested_unit ?: $item->unit)) === $normalizedPriceUnit
                        && (float) ($item->requested_unit_conversion_to_base ?? 0) > 0;
                })
                ?->requested_unit_conversion_to_base;
        }

        if ($conversionToBase === null || (float) $conversionToBase <= 0) {
            throw ValidationException::withMessages([
                'prices' => "{$product->name} cannot be invoiced in {$priceUnit}. Add a valid conversion in inventory units.",
            ]);
        }

        return round($baseQuantity / (float) $conversionToBase, 4);
    }

    /**
     * @param  Collection<int, ShopOrderItem>  $orderItems
     * @param  array{price: float, price_unit: string}  $dailyPrice
     * @return array{price: float, price_unit: string}
     */
    private function billingPriceForOrderItems(Collection $orderItems, array $dailyPrice): array
    {
        return [
            'price' => round((float) $dailyPrice['price'], 2),
            'price_unit' => ProductUnit::normalizeUnit((string) $dailyPrice['price_unit']),
        ];
    }

    /**
     * Return product names from the order that have no approved daily price.
     * Reuses the same filtering logic that blocks invoice generation.
     *
     * @return array<int, string>
     */
    public function getUnpricedOrderItemNames(ShopOrder $order): array
    {
        return $this->missingDailyPriceProductNamesForOrder($order);
    }

    /**
     * @return array<int, string>
     */
    private function missingDailyPriceProductNamesForOrder(ShopOrder $order): array
    {
        $order->loadMissing(['shop.priceGroup', 'items.product']);

        return $order->items
            ->filter(fn (ShopOrderItem $item): bool => $this->shouldIncludeOrderItemInInvoice($item))
            ->filter(function (ShopOrderItem $item) use ($order): bool {
                if (! $item->product || ! $order->shop) {
                    return true;
                }

                try {
                    $this->approvedDailyPriceResolver->resolve($item->product, $order->shop, $order->business_date);

                    return false;
                } catch (ValidationException) {
                    return true;
                }
            })
            ->map(fn (ShopOrderItem $item): string => $item->product?->name ?? 'Unknown Product')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function missingDailyPriceProductNamesForInvoice(ShopInvoice $invoice): array
    {
        $invoice->loadMissing(['shop.priceGroup', 'items.product']);

        return $invoice->items
            ->filter(fn (ShopInvoiceItem $item): bool => (float) ($item->approved_qty ?? 0) > 0 || (float) ($item->delivered_qty ?? 0) > 0)
            ->filter(function (ShopInvoiceItem $item) use ($invoice): bool {
                if (! $item->product || ! $invoice->shop) {
                    return true;
                }

                try {
                    $this->approvedDailyPriceResolver->resolve($item->product, $invoice->shop, $invoice->business_date);

                    return false;
                } catch (ValidationException) {
                    return true;
                }
            })
            ->map(fn (ShopInvoiceItem $item): string => $item->product?->name ?? 'Unknown Product')
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeDuplicateInvoiceItems(ShopInvoice $invoice): void
    {
        $invoice->loadMissing('items');

        $invoice->items
            ->groupBy('product_id')
            ->filter(fn (Collection $rows): bool => $rows->count() > 1)
            ->each(function (Collection $rows): void {
                /** @var ShopInvoiceItem $primary */
                $primary = $rows->sortBy('id')->first();
                $secondaryRows = $rows->slice(1)->values();

                $approvedQty = (float) $rows->sum(fn (ShopInvoiceItem $row): float => (float) $row->approved_qty);
                $priceQuantity = (float) $rows->sum(fn (ShopInvoiceItem $row): float => (float) ($row->price_quantity ?? 0));
                $deliveredQty = (float) $rows->sum(fn (ShopInvoiceItem $row): float => (float) $row->delivered_qty);
                $deliveredPriceQuantity = (float) $rows->sum(fn (ShopInvoiceItem $row): float => (float) ($row->delivered_price_quantity ?? 0));
                $shortageQty = (float) $rows->sum(fn (ShopInvoiceItem $row): float => (float) $row->shortage_qty);
                $shortagePriceQuantity = (float) $rows->sum(fn (ShopInvoiceItem $row): float => (float) ($row->shortage_price_quantity ?? 0));
                $excessQty = (float) $rows->sum(fn (ShopInvoiceItem $row): float => (float) ($row->excess_qty ?? 0));
                $excessPriceQuantity = (float) $rows->sum(fn (ShopInvoiceItem $row): float => (float) ($row->excess_price_quantity ?? 0));
                $lineSubtotal = (float) $rows->sum(fn (ShopInvoiceItem $row): float => (float) $row->line_subtotal);
                $shortageAmount = (float) $rows->sum(fn (ShopInvoiceItem $row): float => (float) $row->shortage_amount);
                $excessAmount = (float) $rows->sum(fn (ShopInvoiceItem $row): float => (float) ($row->excess_amount ?? 0));
                $finalTotal = (float) $rows->sum(fn (ShopInvoiceItem $row): float => (float) $row->final_line_total);

                $primary->update([
                    'approved_qty' => round($approvedQty, 2),
                    'price_quantity' => round($priceQuantity, 4),
                    'delivered_qty' => round($deliveredQty, 2),
                    'delivered_price_quantity' => round($deliveredPriceQuantity, 4),
                    'shortage_qty' => round($shortageQty, 2),
                    'shortage_price_quantity' => round($shortagePriceQuantity, 4),
                    'excess_qty' => round($excessQty, 2),
                    'excess_price_quantity' => round($excessPriceQuantity, 4),
                    'line_subtotal' => round($lineSubtotal, 2),
                    'shortage_amount' => round($shortageAmount, 2),
                    'excess_amount' => round($excessAmount, 2),
                    'final_line_total' => round($finalTotal, 2),
                ]);

                $secondaryRows->each(fn (ShopInvoiceItem $row) => $row->delete());
            });
    }

    private function shouldIncludeOrderItemInInvoice(ShopOrderItem $orderItem): bool
    {
        if ((float) ($orderItem->approved_qty ?? 0) <= 0) {
            return false;
        }

        if ($orderItem->sorting_status === 'not_available' || $orderItem->loadout_discrepancy_type === 'not_available') {
            return true;
        }

        return $orderItem->sorting_status === 'loaded' && (float) ($orderItem->loaded_qty ?? 0) > 0;
    }

    private function syncOwnedShopBalanceForInvoice(ShopInvoice $invoice, int $userId): void
    {
        $invoice->loadMissing('shop');

        if (! $invoice->shop?->isOwnedAccountingEnabled() || ! $invoice->business_date) {
            return;
        }

        $this->ownedShopAccountingService->syncStoredClosingBalancesFromDate(
            $invoice->shop,
            $invoice->business_date,
            $userId,
        );
    }
}
