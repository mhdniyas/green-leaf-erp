<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Repositories\Purchasing\GoodsReceivedRepository;
use Illuminate\Support\Facades\DB;

class RecordGoodsReceiptAction
{
    public function __construct(
        private readonly GoodsReceivedRepository $grnRepository,
        private readonly ApproveGoodsReceiptAction $approveGoodsReceiptAction,
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
                'destination_shop_id' => $data->destinationShopId,
                'warehouse_id' => $data->warehouseId,
                'grn_number' => $grnNumber,
                'status' => 'approved',
                'bill_status' => $data->billStatus,
                'bill_number' => $data->billNumber,
                'received_by' => $userId,
                'approved_by' => $userId,
                'updated_by' => $userId,
                'received_at' => $data->receivedAt,
                'approved_at' => now(),
                'transport_cost' => $data->transportCost,
                'labour_cost' => $data->labourCost,
                'notes' => $data->notes,
            ]);

            // Calculate total received quantity for proportional cost allocation
            $totalReceivedQty = array_sum(array_column($data->items, 'received_qty'));

            foreach ($data->items as $item) {
                // Fetch the matching PO item if available
                /** @var PurchaseOrderItem|null $poItem */
                $poItem = ! empty($item['purchase_order_item_id'])
                    ? PurchaseOrderItem::find($item['purchase_order_item_id'])
                    : null;

                // Calculate quantity variance (received_qty - ordered_qty)
                $variance = $poItem ? ($item['received_qty'] - (float) $poItem->quantity) : 0.0;

                // Create GRN Item
                $grn->items()->create([
                    'purchase_order_item_id' => $poItem?->id,
                    'product_id' => $item['product_id'],
                    'received_qty' => $item['received_qty'],
                    'received_unit' => $item['received_unit'] ?? 'kg',
                    'variance' => $variance,
                ]);
            }

            // Update Purchase Order status to Received if PO exists
            if ($data->purchaseOrderId) {
                /** @var PurchaseOrder|null $po */
                $po = PurchaseOrder::find($data->purchaseOrderId);
                $po?->update([
                    'status' => POStatus::Received,
                ]);
            }

            // Log activity
            activity()
                ->performedOn($grn)
                ->causedBy(User::query()->find($userId))
                ->log('goods_received.recorded');

            return $this->approveGoodsReceiptAction->execute($grn->fresh(['items.purchaseOrderItem', 'items.product', 'purchaseOrder']), $userId);
        });
    }
}
