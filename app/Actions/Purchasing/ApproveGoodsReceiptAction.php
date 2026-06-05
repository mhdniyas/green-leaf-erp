<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Repositories\Inventory\StockBatchRepository;
use App\Services\Finance\JournalService;
use Illuminate\Support\Facades\DB;

class ApproveGoodsReceiptAction
{
    public function __construct(
        private readonly StockBatchRepository $stockBatchRepository,
        private readonly JournalService $journalService,
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

            foreach ($grn->items as $item) {
                $receivedQty = (float) $item->received_qty;

                // Calculate proportional landed cost allocation
                $allocatedTransport = 0.00;
                $allocatedLabour = 0.00;

                if ($totalReceivedQty > 0) {
                    $allocatedTransport = ($receivedQty / $totalReceivedQty) * (float) $grn->transport_cost;
                    $allocatedLabour = ($receivedQty / $totalReceivedQty) * (float) $grn->labour_cost;
                }

                $poItem = $item->purchaseOrderItem;
                $costPerKg = $poItem
                    ? $poItem->costPerKgForReceivedQuantity($receivedQty)
                    : 0.00;

                // Create StockBatch in inventory for each item received
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
                    'notes' => "Auto-created from GRN: {$grn->grn_number}",
                ]);
            }

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
}
