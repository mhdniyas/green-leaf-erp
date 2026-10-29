<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\GoodsReceived;
use App\Models\PurchaseInvoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class PurchaserBillsNotReceivedService
{
    /**
     * Retrieve all purchaser-created purchase bills awaiting warehouse receiving,
     * counted from all historical dates through today.
     *
     * @return array{
     *     count: int,
     *     total_outstanding_qty: float,
     *     bills: array<int, array{
     *         id: int,
     *         date: string,
     *         raw_date: string,
     *         bill_number: string,
     *         purchaser: string,
     *         supplier: string,
     *         status: string,
     *         bill_qty: float,
     *         outstanding_qty: float
     *     }>
     * }
     */
    public function getPendingBillsData(bool $ignoreCache = false): array
    {
        if ($ignoreCache) {
            return $this->computePendingBillsData();
        }

        return Cache::remember('purchaser_bills_not_received_summary', 60, function (): array {
            return $this->computePendingBillsData();
        });
    }

    /**
     * Execute the authoritative query and calculation for purchaser bills awaiting receiving.
     *
     * @return array{
     *     count: int,
     *     total_outstanding_qty: float,
     *     bills: array<int, array{
     *         id: int,
     *         date: string,
     *         raw_date: string,
     *         bill_number: string,
     *         purchaser: string,
     *         supplier: string,
     *         status: string,
     *         bill_qty: float,
     *         outstanding_qty: float
     *     }>
     * }
     */
    public function computePendingBillsData(): array
    {
        $todayEndOfDay = Carbon::today()->endOfDay();

        // 1. Efficient pre-filtering of candidate purchaser invoices
        // Exclude direct shop purchases (they are fulfilled to store counters and do not enter central warehouse receiving)
        $candidateInvoices = PurchaseInvoice::query()
            ->whereNull('cancelled_at')
            ->whereNotIn('status', ['cancelled'])
            ->where('created_at', '<=', $todayEndOfDay)
            ->where(function ($q): void {
                $q->whereNotNull('purchaser_cart_id')
                    ->orWhereNotNull('purchaser_submitted_by');
            })
            ->where(function ($sourceQ): void {
                $sourceQ->whereNull('purchase_source')
                    ->orWhere('purchase_source', '!=', 'shop');
            })
            ->whereNull('shop_id')
            ->whereDoesntHave('purchaserCart', fn ($cartQ) => $cartQ->where('purchase_source', 'shop'))
            ->whereDoesntHave('goodsReceived.purchaseOrder', fn ($poQ) => $poQ->where('fulfillment_type', 'shop'))
            ->whereHas('goodsReceived', function ($grnQ): void {
                $grnQ->where('status', '!=', 'cancelled')
                    ->where('receipt_type', '!=', 'warehouse_advance')
                    ->where(function ($pendingQ): void {
                        $pendingQ->whereHas('stockBatches', function ($bQ): void {
                            $bQ->where('warehouse_receive_pending', true)
                                ->where('total_kg', '>', 0.001);
                        })
                            ->orWhereDoesntHave('stockBatches');
                    });
            })
            ->with([
                'supplier:id,name',
                'purchaserCart.user:id,name',
                'purchaserSubmittedBy:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        if ($candidateInvoices->isEmpty()) {
            return [
                'count' => 0,
                'total_outstanding_qty' => 0.0,
                'bills' => [],
            ];
        }

        // 2. Pre-fetch all related GoodsReceived records (primary + any cart siblings) to avoid N+1 queries
        $cartIds = $candidateInvoices->pluck('purchaser_cart_id')->filter()->unique()->all();
        $primaryGrnIds = $candidateInvoices->pluck('goods_received_id')->filter()->unique()->all();

        $allGrns = GoodsReceived::query()
            ->where(function ($q) use ($cartIds, $primaryGrnIds): void {
                if (! empty($cartIds)) {
                    $q->whereIn('purchaser_cart_id', $cartIds);
                }
                if (! empty($primaryGrnIds)) {
                    $q->orWhereIn('id', $primaryGrnIds);
                }
            })
            ->whereNotIn('status', ['cancelled'])
            ->where('receipt_type', '!=', 'warehouse_advance')
            ->with([
                'purchaseOrder:id,fulfillment_type,purchaser_cart_id',
                'purchaserCart:id,purchase_source',
                'items:id,goods_received_id,received_qty',
                'stockBatches:id,goods_received_id,total_kg,warehouse_receive_pending',
                'advanceMatchesAsBill:id,bill_goods_received_id,base_qty,matched_qty',
            ])
            ->get();

        $grnsByCartId = $allGrns->whereNotNull('purchaser_cart_id')->groupBy('purchaser_cart_id');
        $grnsById = $allGrns->keyBy('id');

        $bills = [];
        $totalOutstandingQty = 0.0;

        foreach ($candidateInvoices as $inv) {
            if ($inv->purchase_source === 'shop' || $inv->shop_id !== null) {
                continue;
            }

            if ($inv->purchaserCart?->purchase_source === 'shop') {
                continue;
            }

            if ($inv->purchaser_cart_id && $grnsByCartId->has($inv->purchaser_cart_id)) {
                $relatedGrns = $grnsByCartId->get($inv->purchaser_cart_id);
            } else {
                $relatedGrns = collect([$grnsById->get($inv->goods_received_id)])->filter();
            }

            if ($relatedGrns->isEmpty()) {
                continue;
            }

            if ($relatedGrns->contains(fn ($grn): bool => $grn->isDirectShopPurchase())) {
                continue;
            }

            $billQty = 0.0;
            $matchedQty = 0.0;
            $confirmedQty = 0.0;

            foreach ($relatedGrns as $grn) {
                $gItemsQty = (float) $grn->items->sum('received_qty');
                $gMatchedQty = (float) $grn->advanceMatchesAsBill->sum(
                    fn ($m): float => (float) ($m->base_qty ?? $m->matched_qty ?? 0.0)
                );

                $hasBatches = $grn->stockBatches->isNotEmpty();
                $hasPendingBatches = $grn->stockBatches->contains(
                    fn ($b): bool => (bool) $b->warehouse_receive_pending && (float) $b->total_kg > 0.001
                );

                if ($hasBatches && ! $hasPendingBatches) {
                    $gConfirmedQty = max(0.0, round($gItemsQty - $gMatchedQty, 3));
                } else {
                    $gConfirmedQty = (float) $grn->stockBatches
                        ->where('warehouse_receive_pending', false)
                        ->sum('total_kg');
                }

                $billQty += $gItemsQty;
                $matchedQty += $gMatchedQty;
                $confirmedQty += $gConfirmedQty;
            }

            $outstandingQty = max(0.0, round($billQty - $matchedQty - $confirmedQty, 3));

            if ($outstandingQty <= 0.001) {
                // Fully received, fully advance-matched, or cleared
                continue;
            }

            $purchaserName = $inv->purchaserCart?->user?->name
                ?? $inv->purchaserSubmittedBy?->name
                ?? 'Purchaser';

            $status = ($matchedQty > 0.001 || $confirmedQty > 0.001)
                ? 'Partially Received'
                : 'Not Received';

            $roundedOutstanding = round($outstandingQty, 2);
            $totalOutstandingQty += $roundedOutstanding;

            $bills[] = [
                'id' => (int) $inv->id,
                'date' => $inv->created_at ? $inv->created_at->format('d-m-Y') : now()->format('d-m-Y'),
                'raw_date' => $inv->created_at ? $inv->created_at->toDateTimeString() : '',
                'bill_number' => (string) ($inv->invoice_number ?: 'BILL-#'.$inv->id),
                'purchaser' => (string) $purchaserName,
                'supplier' => (string) ($inv->supplier?->name ?? 'Unknown'),
                'status' => $status,
                'bill_qty' => round($billQty, 2),
                'outstanding_qty' => $roundedOutstanding,
            ];
        }

        return [
            'count' => count($bills),
            'total_outstanding_qty' => round($totalOutstandingQty, 2),
            'bills' => $bills,
        ];
    }
}
