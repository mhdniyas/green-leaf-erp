<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Purchasing\POStatus;
use App\Models\AdvanceReceiveMatch;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\JournalEntry;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Repositories\Inventory\StockBatchRepository;
use App\Services\Finance\JournalService;
use App\Services\Pricing\PriceBoardService;
use App\Services\Purchasing\VendorPriceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ApproveGoodsReceiptAction
{
    public function __construct(
        private readonly StockBatchRepository $stockBatchRepository,
        private readonly JournalService $journalService,
        private readonly PriceBoardService $priceBoardService,
        private readonly VendorPriceService $vendorPriceService,
    ) {}

    /**
     * Approves a Goods Received Note (GRN) and completes the warehouse receive confirmation in one step.
     */
    public function executeAndConfirmReceive(GoodsReceived $grn, int $userId, ?int $targetWarehouseId = null): GoodsReceived
    {
        return DB::transaction(function () use ($grn, $userId, $targetWarehouseId): GoodsReceived {
            $approvedGrn = $grn->status === 'approved' ? $grn : $this->execute($grn, $userId);
            $approvedGrn->loadMissing('items');

            $targetWarehouse = $targetWarehouseId ?? $approvedGrn->warehouse_id;

            // If the GRN is approved but has no StockBatches at all, create them now
            $hasBatches = StockBatch::query()
                ->where('goods_received_id', $approvedGrn->id)
                ->exists();

            if (! $hasBatches && $approvedGrn->items->isNotEmpty()) {
                $this->createMissingBatches($approvedGrn, $userId, $targetWarehouse);
            }

            StockBatch::query()
                ->where('goods_received_id', $approvedGrn->id)
                ->where('warehouse_receive_pending', true)
                ->get()
                ->each(function (StockBatch $batch) use ($approvedGrn, $targetWarehouse, $userId): void {
                    $grnItem = $batch->goods_received_item_id
                        ? $approvedGrn->items->firstWhere('id', $batch->goods_received_item_id)
                        : $approvedGrn->items->firstWhere('product_id', $batch->product_id);

                    $itemWarehouseId = (int) ($targetWarehouse ?? $batch->warehouse_id ?? $approvedGrn->warehouse_id ?? 1);

                    // Check if Advance matches already cover part or all of this item
                    $alreadyMatchedBase = $grnItem ? (float) AdvanceReceiveMatch::query()
                        ->where('bill_goods_received_item_id', $grnItem->id)
                        ->sum(DB::raw('COALESCE(base_qty, matched_qty, 0)')) : 0.0;

                    $newTotalKg = $alreadyMatchedBase > 0
                        ? max(0.0, round((float) $batch->total_kg - $alreadyMatchedBase, 3))
                        : (float) $batch->total_kg;

                    $batch->update([
                        'warehouse_id' => $itemWarehouseId,
                        'total_kg' => $newTotalKg,
                        'warehouse_receive_pending' => false,
                        'warehouse_confirmed_at' => now(),
                        'warehouse_confirmed_by' => $userId,
                    ]);

                    if ($batch->grading_mode === 'fixed_purchase_grade' && $newTotalKg > 0) {
                        StockMovement::query()->firstOrCreate(
                            [
                                'batch_id' => $batch->id,
                                'type' => StockMovementType::In->value,
                                'grade' => $batch->purchase_grade,
                            ],
                            [
                                'product_id' => $batch->product_id,
                                'warehouse_id' => $itemWarehouseId,
                                'created_by' => $userId,
                                'quantity' => $newTotalKg,
                                'cost_per_unit' => $batch->cost_per_kg,
                                'notes' => "Fixed Grade {$batch->purchase_grade} receipt from {$approvedGrn->grn_number}",
                            ],
                        );

                        $batch->update([
                            'status' => BatchStatus::Sorted,
                            'sorted_at' => now(),
                        ]);
                    }

                    activity()
                        ->performedOn($batch)
                        ->causedBy(User::query()->find($userId))
                        ->log('stock_batch.warehouse_confirmed');
                });

            return $approvedGrn->fresh(['items.product', 'purchaseOrder']);
        });
    }

    /**
     * Approves a Goods Received Note (GRN) and handles stock/inventory generation.
     */
    public function execute(GoodsReceived $grn, int $userId): GoodsReceived
    {
        return DB::transaction(function () use ($grn, $userId): GoodsReceived {
            $this->clearExistingArtifacts($grn);

            // Update GRN status
            $grn->update([
                'status' => 'approved',
                'approved_by' => $userId,
                'updated_by' => $userId,
                'approved_at' => now(),
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

                if (($item->grade ?? 'A') === 'A' && $costPerKg > 0) {
                    $this->vendorPriceService->syncPrice($item->product_id, $costPerKg);
                }

                $isWarehouseAdvance = $grn->receipt_type === 'warehouse_advance';

                // Create StockBatch in inventory
                $this->stockBatchRepository->create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $grn->warehouse_id,
                    'goods_received_id' => $grn->id,
                    'goods_received_item_id' => $item->id,
                    'purchase_grade' => $item->grade ?? $grn->purchase_grade ?? 'A',
                    'grading_mode' => ($item->grade ?? $grn->purchase_grade ?? 'A') === 'B' ? 'fixed_purchase_grade' : 'sort_required',
                    'created_by' => $userId,
                    'reference' => $this->stockBatchRepository->generateReference(),
                    'received_at' => $grn->received_at,
                    'total_kg' => $receivedQty,
                    'cost_per_kg' => $costPerKg,
                    'transport_cost' => round($allocatedTransport, 2),
                    'labour_cost' => round($allocatedLabour, 2),
                    'status' => BatchStatus::Pending,
                    'warehouse_receive_pending' => ! $isWarehouseAdvance,
                    'warehouse_confirmed_at' => $isWarehouseAdvance ? now() : null,
                    'warehouse_confirmed_by' => $isWarehouseAdvance ? $userId : null,
                    'notes' => "Auto-created from GRN: {$grn->grn_number}",
                ]);
            }

            $gradeAProductIds = $grn->items
                ->filter(fn (GoodsReceivedItem $item): bool => ($item->grade ?? 'A') === 'A')
                ->pluck('product_id')
                ->all();

            if ($gradeAProductIds !== []) {
                $this->priceBoardService->refreshWholesalePricesForProducts($gradeAProductIds, 'grn', $grn->grn_number);
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
                ->causedBy(User::query()->find($userId))
                ->log('goods_received.approved');

            return $grn->fresh(['items.product', 'purchaseOrder']);
        });
    }

    /**
     * Calculate weighted average unit price for a product from all GRN items on the date.
     *
     * Weighted avg = sum(qty * price) / sum(qty) across all approved GRNs for that product/date.
     */
    private function calculateWeightedAvgPrice(int $productId, string $date, GoodsReceivedItem $currentItem): float
    {
        // Gather all approved GRN items for this product on this date.
        $allItems = GoodsReceivedItem::where('product_id', $productId)
            ->where('grade', $currentItem->grade ?? 'A')
            ->whereHas('goodsReceived', function ($query) use ($date): void {
                $query->where('status', 'approved')
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

    private function clearExistingArtifacts(GoodsReceived $grn): void
    {
        $batches = StockBatch::query()
            ->where('notes', "Auto-created from GRN: {$grn->grn_number}")
            ->get();

        foreach ($batches as $batch) {
            if ($batch->stockMovements()->exists() || $batch->wastageEntries()->exists()) {
                throw new \RuntimeException('This GRN cannot be resubmitted after stock activity has started.');
            }

            $batch->forceDelete();
        }

        JournalEntry::query()
            ->where('reference', $grn->grn_number)
            ->delete();
    }

    /**
     * Create missing StockBatch records for an already-approved GRN that has items but no batches.
     */
    private function createMissingBatches(GoodsReceived $grn, int $userId, ?int $targetWarehouseId = null): void
    {
        $totalReceivedQty = (float) $grn->items->sum('received_qty');

        $date = $grn->received_at instanceof Carbon
            ? $grn->received_at->format('Y-m-d')
            : Carbon::parse($grn->received_at)->format('Y-m-d');

        $warehouseId = (int) ($targetWarehouseId ?? $grn->warehouse_id ?? 1);

        foreach ($grn->items as $item) {
            $receivedQty = (float) $item->received_qty;

            $allocatedTransport = 0.00;
            $allocatedLabour = 0.00;
            if ($totalReceivedQty > 0) {
                $allocatedTransport = ($receivedQty / $totalReceivedQty) * (float) $grn->transport_cost;
                $allocatedLabour = ($receivedQty / $totalReceivedQty) * (float) $grn->labour_cost;
            }

            $costPerKg = $this->calculateWeightedAvgPrice($item->product_id, $date, $item);

            $this->stockBatchRepository->create([
                'product_id' => $item->product_id,
                'warehouse_id' => $warehouseId,
                'goods_received_id' => $grn->id,
                'goods_received_item_id' => $item->id,
                'purchase_grade' => $item->grade ?? $grn->purchase_grade ?? 'A',
                'grading_mode' => ($item->grade ?? $grn->purchase_grade ?? 'A') === 'B' ? 'fixed_purchase_grade' : 'sort_required',
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
    }
}
