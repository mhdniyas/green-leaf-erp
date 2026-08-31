<?php

declare(strict_types=1);

namespace App\Domains\ShopOrder\Actions;

use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\ShopInvoices\ShopInvoiceIntegrityValidator;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BulkFinalizeShopInvoicesAction
{
    public function __construct(
        private readonly ResolveDeliveryReviewAction $resolveDeliveryReviewAction,
        private readonly ShopInvoiceService $shopInvoiceService,
        private readonly ShopInvoiceIntegrityValidator $shopInvoiceIntegrityValidator,
    ) {}

    /**
     * Check whether an invoice is eligible for delivery review finalization.
     *
     * @return array{eligible: bool, reason: string}
     */
    public function checkEligibility(ShopInvoice $invoice, User $user): array
    {
        if ($invoice->isFinalized()) {
            return [
                'eligible' => false,
                'reason' => 'Already finalized.',
            ];
        }

        $order = $invoice->order;
        if (! $order instanceof ShopOrder) {
            return [
                'eligible' => false,
                'reason' => 'Invoice has no linked shop order.',
            ];
        }

        if ($order->state !== 'approved') {
            return [
                'eligible' => false,
                'reason' => 'Shop order is not approved.',
            ];
        }

        $isPendingReview = $order->delivery_status === 'pending_approval' && $order->delivery_review_status === 'pending';
        $isInTransit = $order->delivery_status === 'in_transit' && in_array($order->delivery_review_status, ['not_started', 'correction_requested'], true);

        if (! $isPendingReview && ! $isInTransit) {
            return [
                'eligible' => false,
                'reason' => "Order is in {$order->delivery_status} state and not ready for delivery finalization.",
            ];
        }

        try {
            $this->shopInvoiceIntegrityValidator->assertMatchesApprovedDailyPrices($invoice);
        } catch (ValidationException $exception) {
            $error = collect($exception->errors())->flatten()->first() ?? 'Daily prices are not approved or do not match invoice.';

            return [
                'eligible' => false,
                'reason' => $error,
            ];
        }

        return [
            'eligible' => true,
            'reason' => 'Ready for finalization.',
        ];
    }

    /**
     * Finalize an individual shop invoice within an isolated transaction.
     *
     * @return array{
     *     status: 'finalized'|'skipped'|'failed',
     *     invoice: ShopInvoice,
     *     order?: ShopOrder,
     *     reason?: string,
     * }
     */
    public function finalizeSingleInvoice(ShopInvoice $invoice, User $user, ?string $note = null): array
    {
        $invoice->loadMissing(['shop.priceGroup', 'items.product', 'order.items.product']);

        if ($invoice->isFinalized()) {
            return [
                'status' => 'skipped',
                'invoice' => $invoice,
                'reason' => 'Already finalized.',
            ];
        }

        $eligibility = $this->checkEligibility($invoice, $user);
        if (! $eligibility['eligible']) {
            return [
                'status' => 'failed',
                'invoice' => $invoice,
                'reason' => $eligibility['reason'],
            ];
        }

        $order = $invoice->order;

        try {
            return DB::transaction(function () use ($invoice, $order, $user, $note): array {
                /** @var ShopInvoice $lockedInvoice */
                $lockedInvoice = ShopInvoice::query()->with('items')->lockForUpdate()->findOrFail($invoice->id);
                if ($lockedInvoice->isFinalized()) {
                    return [
                        'status' => 'skipped',
                        'invoice' => $lockedInvoice,
                        'reason' => 'Already finalized.',
                    ];
                }

                /** @var ShopOrder $lockedOrder */
                $lockedOrder = ShopOrder::query()->with(['items.product', 'invoice.items'])->lockForUpdate()->findOrFail($order->id);

                if ($lockedOrder->delivery_status === 'in_transit' && in_array($lockedOrder->delivery_review_status, ['not_started', 'correction_requested'], true)) {
                    $this->prepareUnitCosts($lockedOrder);

                    $reportedQuantities = $lockedOrder->items
                        ->mapWithKeys(fn (ShopOrderItem $item): array => [
                            $item->id => round((float) ($item->shop_reported_received_qty ?? $item->loaded_qty ?? $item->approved_qty ?? 0), 2),
                        ])
                        ->all();

                    $this->resolveDeliveryReviewAction->submit(
                        $lockedOrder,
                        $reportedQuantities,
                        (int) $user->id,
                        $note,
                    );

                    $lockedOrder = $lockedOrder->fresh(['items.product', 'invoice.items']);
                }

                $approvedOrder = $this->resolveDeliveryReviewAction->approve(
                    $lockedOrder,
                    [],
                    [],
                    [],
                    [],
                    [],
                    (int) $user->id,
                    $note,
                );

                return [
                    'status' => 'finalized',
                    'invoice' => $lockedInvoice->fresh('items'),
                    'order' => $approvedOrder,
                ];
            });
        } catch (ValidationException $exception) {
            $errorMessage = collect($exception->errors())->flatten()->first() ?? $exception->getMessage();

            return [
                'status' => 'failed',
                'invoice' => $invoice,
                'reason' => $errorMessage,
            ];
        } catch (\Throwable $throwable) {
            return [
                'status' => 'failed',
                'invoice' => $invoice,
                'reason' => $throwable->getMessage(),
            ];
        }
    }

    /**
     * Finalize all eligible shop invoices strictly for the specified business date.
     *
     * @return array{
     *     date: string,
     *     total: int,
     *     finalized: int,
     *     skipped: int,
     *     failed: int,
     *     finalized_invoices: list<string>,
     *     skipped_invoices: list<string>,
     *     failures: list<array{invoice_number: string, shop_name: string, reason: string}>,
     * }
     */
    public function finalizeAllForDate(string $businessDate, User $user, ?string $note = null): array
    {
        $invoices = ShopInvoice::query()
            ->with(['shop.priceGroup', 'items.product', 'order.items.product'])
            ->whereDate('business_date', $businessDate)
            ->orderBy('id')
            ->get();

        $result = [
            'date' => $businessDate,
            'total' => $invoices->count(),
            'finalized' => 0,
            'skipped' => 0,
            'failed' => 0,
            'finalized_invoices' => [],
            'skipped_invoices' => [],
            'failures' => [],
        ];

        if ($invoices->isEmpty()) {
            return $result;
        }

        foreach ($invoices as $invoice) {
            $singleResult = $this->finalizeSingleInvoice($invoice, $user, $note);

            if ($singleResult['status'] === 'finalized') {
                $result['finalized']++;
                $result['finalized_invoices'][] = $invoice->invoice_number;
            } elseif ($singleResult['status'] === 'skipped') {
                $result['skipped']++;
                $result['skipped_invoices'][] = $invoice->invoice_number;
            } else {
                $result['failed']++;
                $result['failures'][] = [
                    'invoice_number' => $invoice->invoice_number,
                    'shop_name' => $invoice->shop?->name ?? 'Unknown Shop',
                    'reason' => $singleResult['reason'] ?? 'Unknown error occurred.',
                ];
            }
        }

        return $result;
    }

    private function prepareUnitCosts(ShopOrder $order): void
    {
        $businessDate = $order->business_date?->toDateString() ?? today()->toDateString();

        foreach ($order->items as $item) {
            $item->update([
                'unit_cost' => $this->resolveProductUnitCost((int) $item->product_id, $businessDate),
            ]);
        }
    }

    private function resolveProductUnitCost(int $productId, string $businessDate): float
    {
        $cost = DB::table('stock_batches')
            ->where('product_id', $productId)
            ->whereDate('received_at', $businessDate)
            ->whereNull('deleted_at')
            ->latest('id')
            ->value('cost_per_kg');

        if ($cost !== null) {
            return (float) $cost;
        }

        $cost = DB::table('stock_batches')
            ->where('product_id', $productId)
            ->whereNull('deleted_at')
            ->latest('received_at')
            ->latest('id')
            ->value('cost_per_kg');

        return $cost !== null ? (float) $cost : 0.00;
    }
}
