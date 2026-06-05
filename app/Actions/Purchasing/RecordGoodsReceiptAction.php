<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Repositories\Purchasing\GoodsReceivedRepository;
use Illuminate\Support\Facades\DB;

class RecordGoodsReceiptAction
{
    public function __construct(
        private readonly GoodsReceivedRepository $grnRepository,
    ) {}

    /**
     * Records a new Goods Received Note (GRN) and handles inventory integration.
     */
    public function execute(GoodsReceivedData $data, int $userId): GoodsReceived
    {
        return DB::transaction(function () use ($data, $userId): GoodsReceived {
            // Generate GRN number
            $grnNumber = $this->grnRepository->generateGrnNumber();

            // Create GRN
            /** @var GoodsReceived $grn */
            $grn = $this->grnRepository->create([
                'purchase_order_id' => $data->purchaseOrderId,
                'grn_number' => $grnNumber,
                'status' => 'pending_approval',
                'received_by' => $userId,
                'received_at' => $data->receivedAt,
                'transport_cost' => $data->transportCost,
                'labour_cost' => $data->labourCost,
                'notes' => $data->notes,
            ]);

            // Calculate total received quantity for proportional cost allocation
            $totalReceivedQty = array_sum(array_column($data->items, 'received_qty'));

            foreach ($data->items as $item) {
                // Fetch the matching PO item
                /** @var PurchaseOrderItem $poItem */
                $poItem = PurchaseOrderItem::findOrFail($item['purchase_order_item_id']);

                // Calculate quantity variance (received_qty - ordered_qty)
                $variance = $item['received_qty'] - (float) $poItem->quantity;

                // Create GRN Item
                $grn->items()->create([
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $item['product_id'],
                    'received_qty' => $item['received_qty'],
                    'variance' => $variance,
                ]);
            }

            // Update Purchase Order status to Received
            /** @var PurchaseOrder $po */
            $po = PurchaseOrder::findOrFail($data->purchaseOrderId);
            $po->update([
                'status' => POStatus::Received,
            ]);

            // Log activity
            activity()
                ->performedOn($grn)
                ->causedBy($userId)
                ->log('goods_received.recorded');

            return $grn->fresh(['items.product', 'purchaseOrder']);
        });
    }
}
