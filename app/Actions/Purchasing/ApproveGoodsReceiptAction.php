<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Repositories\Inventory\StockBatchRepository;
use App\Services\Finance\JournalService;
use App\Services\Pricing\PriceBoardService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ApproveGoodsReceiptAction
{
    public function __construct(
        private readonly StockBatchRepository $stockBatchRepository,
        private readonly JournalService $journalService,
        private readonly PriceBoardService $priceBoardService,
    ) {}

    /**
     * Approves a Goods Received Note (GRN) and handles stock/inventory generation.
     */
    public function execute(GoodsReceived $grn, int $userId): GoodsReceived
    {
        return DB::transaction(function () use ($grn, $userId): GoodsReceived {
            // Update GRN status
            $grn->update([
                'status' => 'approved',
            ]);

            // Calculate total received quantity for proportional landed cost allocation
            $totalReceivedQty = (float) $grn->items->sum('received_qty');

            $date = $grn->received_at instanceof Carbon
                ? $grn->received_at->format('Y-m-d')
                : Carbon::parse($grn->received_at)->format('Y-m-d');

            foreach ($grn->items as $item) {
                $receivedQty = (float) $item->received_qty;

                // Calculate proportional landed cost allocation
                $allocatedTransport = 0.00;
                $allocatedLabour = 0.00;

                if ($totalReceivedQty > 0) {
                    $allocatedTransport = ($receivedQty / $totalReceivedQty) * (float) $grn->transport_cost;
                    $allocatedLabour = ($receivedQty / $totalReceivedQty) * (float) $grn->labour_cost;
                }

                // Weighted average unit price across ALL approved GRN items for this product on this date
                $costPerKg = $this->calculateWeightedAvgPrice($item->product_id, $date, $item);

                // Create StockBatch in inventory — flagged as warehouse_receive_pending
                $this->stockBatchRepository->create([
                    'product_id' => $item->product_id,
                    'created_by' => $userId,
                    'reference' => $this->stockBatchRepository->generateReference(),
                    'received_at' => $grn->received_at,
                    'total_kg' => $receivedQty,
                    'cost_per_kg' => $costPerKg,
                    'transport_cost' => round($allocatedTransport, 2),
                    'labour_cost' => round($allocatedLabour, 2),
                    'status' => BatchStatus::Pending,
                    'warehouse_receive_pending' => true,
                    'notes' => "Auto-created from GRN: {$grn->grn_number}",
                ]);
            }

            $this->priceBoardService->refreshWholesalePricesForProducts(
                $grn->items->pluck('product_id')->all(),
                'grn',
                $grn->grn_number
            );

            // Update Purchase Order status to Closed
            $po = $grn->purchaseOrder;
            if ($po) {
                $po->update([
                    'status' => POStatus::Closed,
                ]);
            }

            // Post journal entries
            $this->journalService->recordGoodsReceipt($grn);

            // Log activity
            activity()
                ->performedOn($grn)
                ->causedBy($userId)
                ->log('goods_received.approved');

            return $grn->fresh(['items.product', 'purchaseOrder']);
        });
    }

    /**
     * Calculate weighted average unit price for a product from all GRN items on the date.
     *
     * Weighted avg = sum(qty * price) / sum(qty) across all pending_approval + approved GRNs for that product/date.
     */
    private function calculateWeightedAvgPrice(int $productId, string $date, GoodsReceivedItem $currentItem): float
    {
        // Gather all GRN items for this product on this date (pending_approval or approved)
        $allItems = GoodsReceivedItem::where('product_id', $productId)
            ->whereHas('goodsReceived', function ($query) use ($date): void {
                $query->whereIn('status', ['pending_approval', 'approved'])
                    ->whereDate('received_at', $date);
            })
            ->with('purchaseOrderItem')
            ->get();

        $totalQty = 0.0;
        $weightedSum = 0.0;

        foreach ($allItems as $item) {
            $qty = (float) $item->received_qty;
            $price = $item->purchaseOrderItem
                ? $item->purchaseOrderItem->costPerKgForReceivedQuantity($qty)
                : 0.0;

            $totalQty += $qty;
            $weightedSum += $qty * $price;
        }

        if ($totalQty <= 0) {
            // Fall back to the current item's PO price
            $poItem = $currentItem->purchaseOrderItem;

            return $poItem ? $poItem->costPerKgForReceivedQuantity((float) $currentItem->received_qty) : 0.0;
        }

        return round($weightedSum / $totalQty, 4);
    }
}
