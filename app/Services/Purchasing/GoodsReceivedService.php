<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Actions\Purchasing\ApproveGoodsReceiptAction;
use App\Actions\Purchasing\RecordGoodsReceiptAction;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\AdvanceReceiveMatch;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrderItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Repositories\Inventory\StockBatchRepository;
use App\Repositories\Purchasing\GoodsReceivedRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodsReceivedService
{
    public function __construct(
        private readonly GoodsReceivedRepository $repository,
        private readonly RecordGoodsReceiptAction $recordGoodsReceiptAction,
        private readonly ApproveGoodsReceiptAction $approveGoodsReceiptAction,
        private readonly StockBatchRepository $stockBatchRepository,
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
            ->with(['purchaseOrder:id,supplier_id,destination_shop_id', 'purchaseOrder.supplier:id,name', 'purchaseOrder.destinationShop:id,name', 'purchaseOrder.purchaserCart:id,purchase_order_id,purchase_source', 'destinationShop:id,name', 'receivedBy:id,name'])
            ->withCount(['items', 'purchaseInvoices'])
            ->withSum('items', 'received_qty')
            ->selectSub(function ($sub): void {
                $sub->from('goods_received_items as gri')
                    ->join('products as p', 'p.id', '=', 'gri.product_id')
                    ->whereColumn('gri.goods_received_id', 'goods_received.id')
                    ->whereNull('gri.deleted_at')
                    ->selectRaw('COALESCE(SUM(gri.received_qty * (
                        CASE
                            WHEN LOWER(TRIM(gri.received_unit)) = LOWER(TRIM(p.unit)) THEN 1.0
                            ELSE COALESCE((
                                SELECT pu.conversion_to_base
                                FROM product_units pu
                                WHERE pu.product_id = gri.product_id
                                  AND LOWER(TRIM(pu.unit)) = LOWER(TRIM(gri.received_unit))
                                LIMIT 1
                            ), 1.0)
                        END
                    )), 0.0)');
            }, 'received_base_qty')
            ->selectSub(function ($sub): void {
                $sub->from('advance_receive_matches as arm')
                    ->whereColumn('arm.advance_goods_received_id', 'goods_received.id')
                    ->selectRaw('COALESCE(SUM(arm.base_qty), 0.0)');
            }, 'bill_matched_base_qty');

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
                if (($filters['receipt_status'] ?? null) === 'pending') {
                    $query->where('goods_received.bill_status', 'bill_pending')
                        ->whereDoesntHave('purchaseInvoices')
                        ->whereNotNull('goods_received.received_at');
                } else {
                    $query->openWarehouseAdvance();
                }
            } elseif ($status === 'bill_available') {
                $query->where(function ($q): void {
                    $q->where('bill_status', 'bill_available')
                        ->orWhereHas('purchaseInvoices');
                });
            }
        }

        $isOperationalAdvance = ! empty($filters['operational_advance']) || (! empty($filters['receipt_type']) && $filters['receipt_type'] === 'warehouse_advance' && empty($filters['bill_status']) && empty($filters['audit_mode']));
        if ($isOperationalAdvance) {
            $cutoffDate = Carbon::parse($filters['date'] ?? now()->toDateString())->subDays(3)->toDateString();
            $query->where(function ($advScope) use ($cutoffDate): void {
                $advScope->where(function ($open): void {
                    $open->openWarehouseAdvance();
                })->orWhere(function ($recent) use ($cutoffDate): void {
                    $recent->whereDate('goods_received.received_at', '>=', $cutoffDate);
                });
            });
        } elseif (! empty($filters['date'])) {
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

    public function updateAdvanceReceive(GoodsReceived $grn, array $data, int $userId): GoodsReceived
    {
        abort_unless(
            $grn->isWarehouseAdvance() || $grn->purchase_order_id === null,
            422,
            'Only advance warehouse receipts can be edited through this action.'
        );

        if ($grn->status === 'cancelled') {
            throw ValidationException::withMessages([
                'advance' => 'Cancelled advance receipts cannot be edited.',
            ]);
        }

        // Check if fully matched
        $totalMatchedBase = (float) $grn->advanceMatchesAsAdvance()->sum('base_qty');
        $currentReceivedBase = (float) $grn->received_base_qty;
        if ($totalMatchedBase > 0 && $totalMatchedBase >= $currentReceivedBase - 0.0001) {
            throw ValidationException::withMessages([
                'advance' => 'This advance receipt is fully matched to a bill and cannot be edited.',
            ]);
        }

        return DB::transaction(function () use ($grn, $data, $userId): GoodsReceived {
            $user = User::find($userId);
            $auditChanges = [];

            $existingItems = $grn->items()->get()->keyBy('id');
            $processedItemIds = [];

            $itemsInput = $data['items'] ?? [];
            if (empty($itemsInput)) {
                throw ValidationException::withMessages([
                    'items' => 'At least one item is required.',
                ]);
            }

            foreach ($itemsInput as $index => $itemInput) {
                $itemId = isset($itemInput['id']) ? (int) $itemInput['id'] : null;
                $productId = (int) ($itemInput['product_id'] ?? 0);
                $product = Product::findOrFail($productId);
                $newQty = round((float) ($itemInput['received_qty'] ?? 0), 3);
                if ($newQty <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.received_qty" => 'Received quantity must be greater than zero.',
                    ]);
                }

                $rawUnit = (string) ($itemInput['received_unit'] ?? $itemInput['unit'] ?? $product->unit);
                $newUnit = ProductUnit::normalizeUnit($rawUnit);
                $conv = (float) ($product->conversionToBaseForUnit($newUnit) ?? 1.0);
                $newBaseQty = round($newQty * $conv, 3);

                /** @var GoodsReceivedItem|null $grnItem */
                $grnItem = $itemId && $existingItems->has($itemId) ? $existingItems->get($itemId) : null;

                if ($grnItem) {
                    $processedItemIds[] = $grnItem->id;
                    $oldProduct = $grnItem->product ?? Product::find($grnItem->product_id);
                    $oldUnit = (string) $grnItem->received_unit;
                    $oldQty = (float) $grnItem->received_qty;
                    $oldConv = (float) ($oldProduct?->conversionToBaseForUnit($oldUnit) ?? 1.0);
                    $oldBaseQty = round($oldQty * $oldConv, 3);

                    // Check matches for this item or product
                    $itemMatchedBase = (float) AdvanceReceiveMatch::query()
                        ->where('advance_goods_received_id', $grn->id)
                        ->where(function ($mq) use ($grnItem): void {
                            $mq->where('advance_goods_received_item_id', $grnItem->id)
                                ->orWhere(function ($mqq) use ($grnItem): void {
                                    $mqq->whereNull('advance_goods_received_item_id')
                                        ->where('product_id', $grnItem->product_id);
                                });
                        })
                        ->sum('base_qty');

                    // If product changed and there was already a match, prevent change
                    if ($grnItem->product_id !== $productId && $itemMatchedBase > 0.0001) {
                        throw ValidationException::withMessages([
                            "items.{$index}.product_id" => "Product {$oldProduct?->name} has already been matched to a bill and cannot be changed.",
                        ]);
                    }

                    // Partial match rule: New base quantity cannot be less than already matched quantity
                    if ($newBaseQty < $itemMatchedBase - 0.0001) {
                        throw ValidationException::withMessages([
                            "items.{$index}.received_qty" => "Quantity cannot be reduced below the already matched quantity of {$itemMatchedBase} KG for {$product->name}.",
                        ]);
                    }

                    $difference = round($newBaseQty - $oldBaseQty, 3);

                    /** @var StockBatch|null $batch */
                    $batch = StockBatch::query()
                        ->where('goods_received_id', $grn->id)
                        ->where(function ($bq) use ($grnItem): void {
                            $bq->where('goods_received_item_id', $grnItem->id)
                                ->orWhere('product_id', $grnItem->product_id);
                        })
                        ->first();

                    if ($batch) {
                        $oldBatchTotal = (float) $batch->total_kg;
                        $newBatchTotal = max(0.0, round($oldBatchTotal + $difference, 3));
                        $batch->update([
                            'product_id' => $productId,
                            'total_kg' => $newBatchTotal,
                        ]);
                    }

                    // Update item
                    $grnItem->update([
                        'product_id' => $productId,
                        'received_qty' => $newQty,
                        'received_unit' => $newUnit,
                        'variance' => 0.0,
                    ]);

                    $auditChanges[] = [
                        'item_id' => $grnItem->id,
                        'product_id' => $productId,
                        'product_name' => $product->name,
                        'before_qty' => $oldQty,
                        'before_unit' => $oldUnit,
                        'before_base_qty' => $oldBaseQty,
                        'after_qty' => $newQty,
                        'after_unit' => $newUnit,
                        'after_base_qty' => $newBaseQty,
                        'difference_base_kg' => $difference,
                    ];
                } else {
                    // New item added to advance
                    $newItem = $grn->items()->create([
                        'product_id' => $productId,
                        'received_qty' => $newQty,
                        'received_unit' => $newUnit,
                        'variance' => 0.0,
                    ]);
                    $processedItemIds[] = $newItem->id;

                    $this->stockBatchRepository->create([
                        'product_id' => $productId,
                        'warehouse_id' => $grn->warehouse_id,
                        'goods_received_id' => $grn->id,
                        'goods_received_item_id' => $newItem->id,
                        'purchase_grade' => 'A',
                        'grading_mode' => 'sort_required',
                        'created_by' => $userId,
                        'reference' => $this->stockBatchRepository->generateReference(),
                        'received_at' => $data['received_at'] ?? $grn->received_at,
                        'total_kg' => $newBaseQty,
                        'cost_per_kg' => 0.0,
                        'transport_cost' => 0.0,
                        'labour_cost' => 0.0,
                        'status' => BatchStatus::Pending,
                        'warehouse_receive_pending' => false,
                        'warehouse_confirmed_at' => now(),
                        'warehouse_confirmed_by' => $userId,
                        'notes' => "Auto-created from GRN: {$grn->grn_number}",
                    ]);

                    $auditChanges[] = [
                        'item_id' => $newItem->id,
                        'product_id' => $productId,
                        'product_name' => $product->name,
                        'action' => 'item_added',
                        'after_qty' => $newQty,
                        'after_unit' => $newUnit,
                        'after_base_qty' => $newBaseQty,
                    ];
                }
            }

            // Remove any items that were omitted
            foreach ($existingItems as $oldItem) {
                if (! in_array($oldItem->id, $processedItemIds, true)) {
                    $matched = (float) AdvanceReceiveMatch::query()
                        ->where('advance_goods_received_id', $grn->id)
                        ->where('product_id', $oldItem->product_id)
                        ->sum('base_qty');

                    if ($matched > 0.0001) {
                        throw ValidationException::withMessages([
                            'items' => "Cannot remove item {$oldItem->product?->name} because {$matched} KG has already been matched.",
                        ]);
                    }

                    StockBatch::query()
                        ->where('goods_received_id', $grn->id)
                        ->where('goods_received_item_id', $oldItem->id)
                        ->update(['total_kg' => 0.0, 'status' => BatchStatus::Closed]);
                    StockBatch::query()
                        ->where('goods_received_id', $grn->id)
                        ->where('goods_received_item_id', $oldItem->id)
                        ->delete();

                    $oldItem->delete();

                    $auditChanges[] = [
                        'item_id' => $oldItem->id,
                        'product_id' => $oldItem->product_id,
                        'action' => 'item_removed',
                    ];
                }
            }

            // Update GRN attributes
            $grnUpdates = ['updated_by' => $userId];
            if (array_key_exists('bill_number', $data)) {
                $grnUpdates['bill_number'] = $data['bill_number'] !== null ? trim((string) $data['bill_number']) : null;
            }
            if (array_key_exists('notes', $data)) {
                $grnUpdates['notes'] = $data['notes'] !== null ? trim((string) $data['notes']) : null;
            }
            if (! empty($data['received_at'])) {
                $grnUpdates['received_at'] = $data['received_at'];
            }
            $grn->update($grnUpdates);

            if (! empty($auditChanges)) {
                activity()
                    ->performedOn($grn)
                    ->causedBy(User::find($userId))
                    ->withProperties([
                        'changed_by' => $userId,
                        'user_name' => $user?->name,
                        'action' => 'advance_receive.edited',
                        'changes' => $auditChanges,
                    ])
                    ->log('advance_receive.edited');
            }

            return $grn->fresh(['items.product', 'receivedBy', 'updatedBy']);
        });
    }

    public function deleteAdvanceReceive(GoodsReceived $grn, int $userId): array
    {
        abort_unless(
            $grn->isWarehouseAdvance() || $grn->purchase_order_id === null,
            422,
            'Only advance warehouse receipts can be deleted through this action.'
        );

        if ($grn->status === 'cancelled') {
            throw ValidationException::withMessages([
                'advance' => 'This advance receipt is already cancelled.',
            ]);
        }

        // Check if any quantity is matched
        $totalMatchedBase = (float) $grn->advanceMatchesAsAdvance()->sum('base_qty');
        if ($totalMatchedBase > 0.0001) {
            throw ValidationException::withMessages([
                'advance' => "Cannot delete advance receipt because {$totalMatchedBase} KG has already been matched to a bill.",
            ]);
        }

        return DB::transaction(function () use ($grn, $userId): array {
            $user = User::find($userId);
            $reversedDetails = [];

            // Reverse linked stock batches
            $batches = StockBatch::query()
                ->where('goods_received_id', $grn->id)
                ->get();

            foreach ($batches as $batch) {
                $revKg = (float) $batch->total_kg;
                $reversedDetails[] = [
                    'batch_id' => $batch->id,
                    'product_id' => $batch->product_id,
                    'reversed_total_kg' => $revKg,
                ];

                $batch->update([
                    'total_kg' => 0.0,
                    'status' => BatchStatus::Closed,
                ]);
                $batch->delete();
            }

            // Soft delete items
            foreach ($grn->items as $item) {
                $item->delete();
            }

            // Update GRN status to cancelled and soft delete
            $grn->update([
                'status' => 'cancelled',
                'updated_by' => $userId,
            ]);
            $grn->delete();

            activity()
                ->performedOn($grn)
                ->causedBy(User::find($userId))
                ->withProperties([
                    'cancelled_by' => $userId,
                    'user_name' => $user?->name,
                    'action' => 'advance_receive.cancelled',
                    'reversed_batches' => $reversedDetails,
                ])
                ->log('advance_receive.cancelled');

            return [
                'id' => $grn->id,
                'grn_number' => $grn->grn_number,
                'status' => 'cancelled',
                'reversed_batches' => $reversedDetails,
            ];
        });
    }
}
