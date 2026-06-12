<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Actions\Purchasing\ApproveGoodsReceiptAction;
use App\Actions\Purchasing\RecordGoodsReceiptAction;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\PurchaseOrderItem;
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

    public function approve(GoodsReceived $grn, int $userId): GoodsReceived
    {
        return $this->approveGoodsReceiptAction->execute($grn, $userId);
    }

    public function reject(GoodsReceived $grn, string $remarks, int $userId): GoodsReceived
    {
        return DB::transaction(function () use ($grn, $remarks, $userId): GoodsReceived {
            $grn->update([
                'status' => 'rejected',
                'rejection_remarks' => $remarks,
            ]);

            // Note: PO status remains open/SentToSupplier/Received as it was, but GRN is rejected

            activity()
                ->performedOn($grn)
                ->causedBy($userId)
                ->log('goods_received.rejected');

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
                'status' => 'pending_approval',
                'rejection_remarks' => null, // Reset rejection remarks on resubmit
            ]);

            // Re-create items by deleting existing ones and creating new ones
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

            // Ensure purchase order is in Received status
            $po = $grn->purchaseOrder;
            if ($po) {
                $po->update([
                    'status' => POStatus::Received,
                ]);
            }

            activity()
                ->performedOn($grn)
                ->causedBy($userId)
                ->log('goods_received.updated');

            return $grn->fresh(['items.product', 'purchaseOrder']);
        });
    }
}
