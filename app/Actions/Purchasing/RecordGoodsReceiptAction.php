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
use Illuminate\Validation\ValidationException;

class RecordGoodsReceiptAction
{
    public function __construct(
        private readonly GoodsReceivedRepository $grnRepository,
        private readonly ApproveGoodsReceiptAction $approveGoodsReceiptAction,
    ) {}

    /**
     * Records a new Goods Received Note (GRN) and handles inventory integration.
     * Supports idempotent retries when clientSubmissionId is provided.
     */
    public function execute(GoodsReceivedData $data, int $userId): GoodsReceived
    {
        return DB::transaction(function () use ($data, $userId): GoodsReceived {
            $payloadHash = $data->calculatePayloadHash();

            if (! empty($data->clientSubmissionId)) {
                /** @var GoodsReceived|null $existing */
                $existing = GoodsReceived::query()
                    ->where('client_submission_id', $data->clientSubmissionId)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $existingHash = $existing->submission_payload_hash;
                    if ($existingHash !== null && $existingHash !== $payloadHash) {
                        throw ValidationException::withMessages([
                            'client_submission_id' => 'The submission payload does not match the original receipt for this submission ID.',
                        ]);
                    }

                    if (! $this->isPayloadConsistent($existing, $data)) {
                        throw ValidationException::withMessages([
                            'client_submission_id' => 'The submission payload does not match the original receipt.',
                        ]);
                    }

                    return $existing->fresh(['items.product', 'items.purchaseOrderItem', 'purchaseOrder']);
                }
            }

            // Generate GRN number
            $grnNumber = $this->grnRepository->generateGrnNumber();

            // Create GRN
            /** @var GoodsReceived $grn */
            $grn = $this->grnRepository->create([
                'purchase_order_id' => $data->purchaseOrderId,
                'destination_shop_id' => $data->destinationShopId,
                'warehouse_id' => $data->warehouseId,
                'client_submission_id' => $data->clientSubmissionId,
                'submission_payload_hash' => $payloadHash,
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

    private function isPayloadConsistent(GoodsReceived $existing, GoodsReceivedData $data): bool
    {
        if ((int) $existing->purchase_order_id !== (int) $data->purchaseOrderId) {
            return false;
        }

        if ((int) $existing->destination_shop_id !== (int) $data->destinationShopId) {
            return false;
        }

        if ((int) $existing->warehouse_id !== (int) $data->warehouseId) {
            return false;
        }

        $existingItems = $existing->items()->get(['product_id', 'purchase_order_item_id', 'received_qty'])->toArray();
        if (count($existingItems) !== count($data->items)) {
            return false;
        }

        $normalize = function (array $items): array {
            $sorted = $items;
            usort($sorted, function (array $a, array $b): int {
                $cmp = ($a['product_id'] ?? 0) <=> ($b['product_id'] ?? 0);
                if ($cmp !== 0) {
                    return $cmp;
                }

                return ($a['purchase_order_item_id'] ?? 0) <=> ($b['purchase_order_item_id'] ?? 0);
            });

            return array_map(fn (array $i): string => (int) $i['product_id'].':'.(int) ($i['purchase_order_item_id'] ?? 0).':'.number_format((float) $i['received_qty'], 3, '.', ''), $sorted);
        };

        return $normalize($existingItems) === $normalize($data->items);
    }
}
