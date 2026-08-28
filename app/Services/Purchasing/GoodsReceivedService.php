<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Actions\Purchasing\ApproveGoodsReceiptAction;
use App\Actions\Purchasing\RecordGoodsReceiptAction;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\JournalEntry;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrderItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Repositories\Purchasing\GoodsReceivedRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GoodsReceivedService
{
    public function __construct(
        private readonly GoodsReceivedRepository $repository,
        private readonly RecordGoodsReceiptAction $recordGoodsReceiptAction,
        private readonly ApproveGoodsReceiptAction $approveGoodsReceiptAction,
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->repository->query()
            ->select([
                'id',
                'purchase_order_id',
                'destination_shop_id',
                'warehouse_id',
                'grn_number',
                'public_uuid',
                'approved_at',
                'status',
                'bill_status',
                'bill_number',
                'received_by',
                'received_at',
                'created_at',
                'updated_at',
            ])
            ->where('status', '!=', 'draft')
            ->with(['purchaseOrder:id,supplier_id,destination_shop_id', 'purchaseOrder.supplier:id,name', 'purchaseOrder.destinationShop:id,name', 'destinationShop:id,name', 'receivedBy:id,name'])
            ->withCount(['items', 'purchaseInvoices'])
            ->withSum('items', 'received_qty');

        if (! empty($filters['receipt_type'])) {
            if ($filters['receipt_type'] === 'warehouse_advance') {
                $query->where(function ($q): void {
                    $q->warehouseAdvance()
                        ->orWhere(function ($legacy): void {
                            $legacy->whereNull('receipt_type')
                                ->whereNull('purchase_order_id');
                        });
                });
            } elseif ($filters['receipt_type'] === 'normal_purchase') {
                $query->where(function ($q): void {
                    $q->normalPurchase()
                        ->orWhere(function ($legacy): void {
                            $legacy->whereNull('receipt_type')
                                ->whereNotNull('purchase_order_id');
                        });
                });
            }
        }

        if (! empty($filters['bill_status'])) {
            $status = (string) $filters['bill_status'];
            if ($status === 'bill_pending') {
                $query->where('bill_status', 'bill_pending')
                    ->whereDoesntHave('purchaseInvoices')
                    ->whereNotNull('received_at');
            } elseif ($status === 'bill_available') {
                $query->where(function ($q): void {
                    $q->where('bill_status', 'bill_available')
                        ->orWhereHas('purchaseInvoices');
                });
            }
        }

        if (! empty($filters['date'])) {
            $query->whereDate('received_at', $filters['date']);
        }

        app(WarehouseReceiptStateResolver::class)->withFacts($query);
        if (! empty($filters['receipt_status'])) {
            app(WarehouseReceiptStateResolver::class)->filter($query, $filters['receipt_status']);
        }
        app(WarehouseReceiptReadScope::class)->receipts($query, $filters['authorized_warehouse_ids'] ?? (! empty($filters['warehouse_id']) ? [(int) $filters['warehouse_id']] : null));

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search): void {
                $q->where('grn_number', 'like', "%{$search}%")
                    ->orWhere('bill_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('purchaseOrder.supplier', function ($sq) use ($search): void {
                        $sq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function create(GoodsReceivedData $data, int $userId): GoodsReceived
    {
        return $this->recordGoodsReceiptAction->execute($data, $userId);
    }

    public function linkBill(GoodsReceived $grn, array $billData, int $userId): GoodsReceived
    {
        return $this->matchBill($grn, $billData, $userId);
    }

    public function matchBill(GoodsReceived $grn, array $matchData, int $userId): GoodsReceived
    {
        return DB::transaction(function () use ($grn, $matchData, $userId): GoodsReceived {
            $invoiceNumber = (string) ($matchData['invoice_number'] ?? $matchData['bill_number'] ?? 'BILL-'.$grn->grn_number);
            $amount = (float) ($matchData['amount'] ?? 0.0);
            $supplierId = (int) ($matchData['supplier_id'] ?? $grn->purchaseOrder?->supplier_id ?? 0);
            $poId = isset($matchData['purchase_order_id']) && $matchData['purchase_order_id'] !== null
                ? (int) $matchData['purchase_order_id']
                : $grn->purchase_order_id;
            $notes = $matchData['notes'] ?? null;

            if ($amount <= 0 && $grn->purchaseOrder) {
                $amount = (float) $grn->purchaseOrder->total_amount;
            }

            // Create or attach real PurchaseInvoice to existing GRN without recreating inventory
            $invoice = PurchaseInvoice::firstOrCreate(
                [
                    'goods_received_id' => $grn->id,
                    'invoice_number' => $invoiceNumber,
                ],
                [
                    'supplier_id' => $supplierId > 0 ? $supplierId : ($grn->purchaseOrder?->supplier_id ?? Supplier::first()?->id ?? 1),
                    'amount' => $amount,
                    'status' => 'approved',
                    'notes' => $notes,
                ]
            );

            $grn->update([
                'bill_status' => 'bill_available',
                'bill_number' => $invoiceNumber,
                'purchase_order_id' => $poId,
                'updated_by' => $userId,
                'matched_by' => $userId,
                'matched_at' => now(),
            ]);

            activity()
                ->performedOn($grn)
                ->causedBy(User::find($userId))
                ->event('goods_received.bill_matched')
                ->withProperties([
                    'source' => 'goods_received_matched',
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoiceNumber,
                    'amount' => $invoice->amount,
                    'matched_by' => $userId,
                    'matched_at' => now()->toDateTimeString(),
                ])
                ->log('goods_received.bill_matched');

            return $grn->fresh(['purchaseOrder.supplier', 'destinationShop', 'items.product', 'purchaseInvoices', 'matchedBy', 'updatedBy']);
        });
    }

    public function suggestPendingReceipts(array $params): Collection
    {
        $query = $this->repository->query()
            ->where(function ($q): void {
                $q->where('bill_status', 'bill_pending')
                    ->orWhereDoesntHave('purchaseInvoices');
            })
            ->with(['items.product', 'destinationShop', 'receivedBy', 'purchaseOrder.supplier']);

        if (! empty($params['destination_shop_id'])) {
            $shopId = (int) $params['destination_shop_id'];
            $query->where(function ($q) use ($shopId): void {
                $q->where('destination_shop_id', $shopId)
                    ->orWhereHas('purchaseOrder', fn ($poq) => $poq->where('destination_shop_id', $shopId));
            });
        }

        if (! empty($params['date'])) {
            $date = Carbon::parse($params['date']);
            $query->whereBetween('received_at', [
                $date->copy()->subDays(7)->toDateString(),
                $date->copy()->addDays(7)->toDateString(),
            ]);
        }

        if (! empty($params['product_ids']) && is_array($params['product_ids'])) {
            $productIds = array_map('intval', $params['product_ids']);
            $query->whereHas('items', fn ($iq) => $iq->whereIn('product_id', $productIds));
        }

        return $query->orderByDesc('received_at')->limit(20)->get();
    }

    public function updateItems(GoodsReceived $grn, array $itemsData, int $userId): GoodsReceived
    {
        return DB::transaction(function () use ($grn, $itemsData, $userId): GoodsReceived {
            $user = User::find($userId);
            $userRole = $user?->roles?->pluck('name')->first() ?? 'user';
            $auditChanges = [];

            foreach ($itemsData as $itemInput) {
                $itemId = (int) ($itemInput['id'] ?? $itemInput['goods_received_item_id'] ?? 0);
                $newQty = round((float) ($itemInput['received_qty'] ?? 0), 2);

                /** @var GoodsReceivedItem|null $grnItem */
                $grnItem = $grn->items()->where('id', $itemId)->first();
                if (! $grnItem) {
                    continue;
                }

                $oldQty = (float) $grnItem->received_qty;
                $difference = round($newQty - $oldQty, 2);

                if ($difference == 0.0) {
                    continue;
                }

                // Record audit detail
                $auditChanges[] = [
                    'item_id' => $grnItem->id,
                    'product_id' => $grnItem->product_id,
                    'product_name' => $grnItem->product?->name,
                    'before_qty' => $oldQty,
                    'after_qty' => $newQty,
                    'difference' => $difference,
                ];

                // Update item quantity and variance
                $poItem = $grnItem->purchaseOrderItem;
                $orderedQty = $poItem ? (float) $poItem->quantity : 0.0;
                $grnItem->update([
                    'received_qty' => $newQty,
                    'variance' => round($newQty - $orderedQty, 2),
                ]);

                // Adjust inventory by the DIFFERENCE only
                /** @var StockBatch|null $batch */
                $batch = StockBatch::query()
                    ->where('goods_received_id', $grn->id)
                    ->where('goods_received_item_id', $grnItem->id)
                    ->first();

                if ($batch) {
                    $oldBatchTotal = (float) $batch->total_kg;
                    $newBatchTotal = max(0.0, round($oldBatchTotal + $difference, 2));
                    $batch->update([
                        'total_kg' => $newBatchTotal,
                    ]);
                }
            }

            $grn->update([
                'updated_by' => $userId,
            ]);

            if (! empty($auditChanges)) {
                activity()
                    ->performedOn($grn)
                    ->causedBy(User::find($userId))
                    ->withProperties([
                        'changed_by' => $userId,
                        'user_name' => $user?->name,
                        'role' => $userRole,
                        'changes' => $auditChanges,
                    ])
                    ->log('goods_received.quantities_adjusted');
            }

            return $grn->fresh(['items.product', 'purchaseOrder.supplier', 'receivedBy', 'updatedBy']);
        });
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
                ->causedBy(User::find($userId))
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
