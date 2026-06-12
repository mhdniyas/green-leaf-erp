<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Actions\Purchasing\ApproveGoodsReceiptAction;
use App\Actions\Purchasing\RecordGoodsReceiptAction;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\JournalEntry;
use App\Models\PurchaseOrderItem;
use App\Models\StockBatch;
use App\Repositories\Purchasing\GoodsReceivedRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class GoodsReceivedService
{
    public function __construct(
        private readonly GoodsReceivedRepository $repository,
        private readonly RecordGoodsReceiptAction $recordGoodsReceiptAction,
        private readonly ApproveGoodsReceiptAction $approveGoodsReceiptAction,
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->query()
            ->where('status', '!=', 'draft')
            ->with(['purchaseOrder.supplier', 'receivedBy', 'items.product'])
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function create(GoodsReceivedData $data, int $userId): GoodsReceived
    {
        return $this->recordGoodsReceiptAction->execute($data, $userId);
    }

    public function markForRecheck(GoodsReceived $grn, string $remarks, int $userId): GoodsReceived
    {
        return DB::transaction(function () use ($grn, $remarks, $userId): GoodsReceived {
            $grn->update([
                'status' => 'recheck_required',
                'rejection_remarks' => $remarks,
                'approved_by' => null,
                'approved_at' => null,
                'updated_by' => $userId,
            ]);

            $this->deleteApprovalArtifacts($grn);

            $po = $grn->purchaseOrder;
            if ($po) {
                $po->update([
                    'status' => POStatus::Received,
                ]);
            }

            activity()
                ->performedOn($grn)
                ->causedBy($userId)
                ->log('goods_received.recheck_requested');

            return $grn->fresh();
        });
    }

    public function update(GoodsReceived $grn, GoodsReceivedData $data, int $userId): GoodsReceived
    {
        return DB::transaction(function () use ($grn, $data, $userId): GoodsReceived {
            $grn->update([
                'received_at' => $data->receivedAt,
                'transport_cost' => $data->transportCost,
                'labour_cost' => $data->labourCost,
                'notes' => $data->notes,
                'status' => 'approved',
                'rejection_remarks' => null,
                'received_by' => $userId,
                'approved_by' => $userId,
                'updated_by' => $userId,
                'approved_at' => now(),
            ]);

            $grn->items()->delete();

            foreach ($data->items as $item) {
                /** @var PurchaseOrderItem $poItem */
                $poItem = PurchaseOrderItem::findOrFail($item['purchase_order_item_id']);
                $variance = $item['received_qty'] - (float) $poItem->quantity;

                $grn->items()->create([
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $item['product_id'],
                    'received_qty' => $item['received_qty'],
                    'variance' => $variance,
                ]);
            }

            $po = $grn->purchaseOrder;
            if ($po) {
                $po->update([
                    'status' => POStatus::Received,
                ]);
            }

            $grn = $this->approveGoodsReceiptAction->execute(
                $grn->fresh(['items.purchaseOrderItem', 'items.product', 'purchaseOrder']),
                $userId
            );

            activity()
                ->performedOn($grn)
                ->causedBy($userId)
                ->log('goods_received.resubmitted');

            return $grn->fresh(['items.product', 'purchaseOrder']);
        });
    }

    private function deleteApprovalArtifacts(GoodsReceived $grn): void
    {
        $batches = StockBatch::query()
            ->where('notes', "Auto-created from GRN: {$grn->grn_number}")
            ->get();

        foreach ($batches as $batch) {
            if ($batch->stockMovements()->exists() || $batch->wastageEntries()->exists()) {
                throw new \RuntimeException('This GRN cannot be sent for recheck after stock activity has started.');
            }

            $batch->forceDelete();
        }

        JournalEntry::query()
            ->where('reference', $grn->grn_number)
            ->delete();
    }
}
