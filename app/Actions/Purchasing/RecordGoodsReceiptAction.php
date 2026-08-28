<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Purchasing\POStatus;
use App\Models\BillReconciliation;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Repositories\Purchasing\GoodsReceivedRepository;
use App\Services\Purchasing\AdvanceReceiveReconciliationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordGoodsReceiptAction
{
    public function __construct(
        private readonly GoodsReceivedRepository $grnRepository,
        private readonly ApproveGoodsReceiptAction $approveGoodsReceiptAction,
        private readonly AdvanceReceiveReconciliationService $advanceReconciliationService,
    ) {}

    /**
     * Records a new Goods Received Note (GRN) and handles inventory integration.
     * Supports idempotent retries when clientSubmissionId is provided.
     */
    public function execute(GoodsReceivedData $data, int $userId): GoodsReceived
    {
        if (! empty($data->advanceMatches)) {
            return $this->advanceReconciliationService->reconcileAndExecute($data, $userId);
        }

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

            $receiptType = $data->receiptType;
            if ($receiptType === null) {
                $receiptType = $data->purchaseOrderId === null ? 'warehouse_advance' : 'normal_purchase';
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
                'receipt_type' => $receiptType,
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

            // Record BillReconciliation if purchase_order_id is present
            if ($data->purchaseOrderId) {
                $totalBillBase = 0.0;
                foreach ($grn->items as $it) {
                    $prod = Product::find($it->product_id);
                    $conv = (float) ($prod?->conversionToBaseForUnit($it->received_unit) ?? 1.0);
                    $totalBillBase += round((float) $it->received_qty * $conv, 3);
                }

                /** @var BillReconciliation $recon */
                $recon = BillReconciliation::create([
                    'purchase_order_id' => $data->purchaseOrderId,
                    'goods_received_id' => $grn->id,
                    'warehouse_id' => $grn->warehouse_id,
                    'source_type' => 'normal',
                    'status' => 'confirmed',
                    'total_bill_base_qty' => $totalBillBase,
                    'total_matched_base_qty' => 0.0,
                    'total_new_receive_base_qty' => $totalBillBase,
                    'confirmed_by' => $userId,
                    'confirmed_at' => now(),
                    'client_submission_id' => $data->clientSubmissionId,
                    'submission_payload_hash' => $payloadHash,
                    'notes' => $data->notes,
                ]);

                foreach ($grn->items as $it) {
                    $prod = Product::find($it->product_id);
                    $conv = (float) ($prod?->conversionToBaseForUnit($it->received_unit) ?? 1.0);
                    $baseQty = round((float) $it->received_qty * $conv, 3);

                    $relevantLoadoutQty = $this->advanceReconciliationService->getLoadedQtyForCohort(
                        $it->product_id,
                        (int) $grn->warehouse_id,
                        $data->receivedAt
                    );
                    $unbilledLoadoutQty = max(0.0, round($relevantLoadoutQty - $baseQty, 3));

                    $recon->lines()->create([
                        'purchase_order_item_id' => $it->purchase_order_item_id,
                        'product_id' => $it->product_id,
                        'bill_qty' => $it->received_qty,
                        'bill_unit' => $it->received_unit ?? 'kg',
                        'bill_base_qty' => $baseQty,
                        'advance_matched_qty' => 0.0,
                        'advance_matched_unit' => $it->received_unit ?? 'kg',
                        'advance_matched_base_qty' => 0.0,
                        'new_receive_qty' => $it->received_qty,
                        'new_receive_unit' => $it->received_unit ?? 'kg',
                        'new_receive_base_qty' => $baseQty,
                        'relevant_loadout_qty' => $relevantLoadoutQty,
                        'unbilled_loadout_qty' => $unbilledLoadoutQty,
                        'reconciled_qty' => $it->received_qty,
                        'reconciled_base_qty' => $baseQty,
                        'difference_status' => 'unmatched',
                    ]);
                }
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
