<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Pricing\PriceBoardService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

class TransferGradeStockAction
{
    public function __construct(
        private readonly ProcessPartialSegregationAction $segregationAction,
        private readonly PriceBoardService $priceBoardService,
        private readonly StockMovementRepository $stockMovementRepository,
    ) {}

    /**
     * Execute a physical stock transfer between any two grades.
     * Supports all 12 combinations:
     * Mixed (U) <-> Grade A <-> Grade B <-> Grade C
     *
     * @return array{
     *     source_batch: StockBatch,
     *     destination_batch: StockBatch,
     *     source_grade: string,
     *     target_grade: string,
     *     quantity: float,
     *     is_idempotent_replay: bool
     * }
     *
     * @throws ValidationException
     */
    public function execute(
        Product $product,
        Warehouse $warehouse,
        ProductGrade $sourceGrade,
        ProductGrade $targetGrade,
        float $quantity,
        int $userId,
        ?StockBatch $sourceBatch = null,
        ?string $notes = null,
        ?string $clientSubmissionId = null
    ): array {
        if ($quantity <= 0.0001) {
            throw ValidationException::withMessages([
                'quantity' => 'Transfer quantity must be greater than zero.',
            ]);
        }

        if ($sourceGrade === $targetGrade) {
            throw ValidationException::withMessages([
                'target_grade' => "Source and destination grades cannot be identical (both are Grade {$sourceGrade->value}).",
            ]);
        }

        $validGrades = [
            ProductGrade::Unsorted,
            ProductGrade::GradeA,
            ProductGrade::GradeB,
            ProductGrade::GradeC,
        ];

        if (! in_array($sourceGrade, $validGrades, true) || ! in_array($targetGrade, $validGrades, true)) {
            throw ValidationException::withMessages([
                'grade' => 'Transfers are only supported between Mixed (U), Grade A, Grade B, and Grade C.',
            ]);
        }

        return DB::transaction(function () use (
            $product,
            $warehouse,
            $sourceGrade,
            $targetGrade,
            $quantity,
            $userId,
            $sourceBatch,
            $notes,
            $clientSubmissionId
        ): array {
            // 1. Check idempotency if clientSubmissionId provided
            if (! empty($clientSubmissionId)) {
                $existingLog = Activity::query()
                    ->where(function ($q): void {
                        $q->where('log_name', 'batch.grade_transferred')
                            ->orWhere('description', 'batch.grade_transferred');
                    })
                    ->get()
                    ->first(function ($act) use ($clientSubmissionId): bool {
                        $props = $act->properties;
                        if (is_array($props)) {
                            return ($props['client_submission_id'] ?? null) === $clientSubmissionId;
                        }
                        if ($props instanceof Collection) {
                            return $props->get('client_submission_id') === $clientSubmissionId;
                        }

                        return false;
                    });

                if ($existingLog !== null) {
                    $props = is_array($existingLog->properties) ? $existingLog->properties : $existingLog->properties->toArray();
                    $loggedSourceGrade = $props['source_grade'] ?? null;
                    $loggedTargetGrade = $props['target_grade'] ?? null;
                    $loggedQty = (float) ($props['transferred_quantity'] ?? 0.0);
                    $loggedProductId = (int) ($props['product_id'] ?? 0);
                    $loggedWarehouseId = (int) ($props['warehouse_id'] ?? 0);

                    if ($loggedSourceGrade !== $sourceGrade->value ||
                        $loggedTargetGrade !== $targetGrade->value ||
                        abs($loggedQty - round($quantity, 3)) > 0.001 ||
                        $loggedProductId !== (int) $product->id ||
                        $loggedWarehouseId !== (int) $warehouse->id) {
                        throw ValidationException::withMessages([
                            'client_submission_id' => 'The submission payload does not match the original transfer operation.',
                        ]);
                    }

                    $src = StockBatch::find($props['source_batch_id'] ?? 0);
                    $dst = StockBatch::find($props['destination_batch_id'] ?? 0);

                    return [
                        'source_batch' => $src ?? new StockBatch,
                        'destination_batch' => $dst ?? new StockBatch,
                        'source_grade' => $sourceGrade->value,
                        'target_grade' => $targetGrade->value,
                        'quantity' => $quantity,
                        'is_idempotent_replay' => true,
                    ];
                }
            }

            // 2. Resolve and lock source batch
            $lockedSource = $this->resolveAndLockSourceBatch(
                $product->id,
                $warehouse->id,
                $sourceGrade,
                $quantity,
                $sourceBatch
            );

            $transferNotes = $notes ?: "Grade transfer {$quantity} kg from Grade {$sourceGrade->value} to Grade {$targetGrade->value}";
            if (! empty($clientSubmissionId)) {
                $transferNotes .= " [submission_id:{$clientSubmissionId}]";
            }

            // 3. Dispatch to appropriate transfer strategy
            if ($sourceGrade === ProductGrade::Unsorted) {
                // Group 1: Mixed -> Graded (A, B, C)
                $segregationResult = $this->segregationAction->execute(
                    $lockedSource,
                    $targetGrade,
                    $quantity,
                    $userId,
                    $transferNotes,
                    $clientSubmissionId
                );

                $destinationBatch = $segregationResult['child'];
                $sourceBatchResult = $segregationResult['parent'];
            } elseif ($targetGrade === ProductGrade::Unsorted) {
                // Group 3: Graded (A, B, C) -> Mixed
                [$sourceBatchResult, $destinationBatch] = $this->transferGradedToMixed(
                    $lockedSource,
                    $sourceGrade,
                    $quantity,
                    $userId,
                    $transferNotes,
                    $clientSubmissionId
                );
            } else {
                // Group 2: Graded -> Graded (A->B, A->C, B->A, B->C, C->A, C->B)
                [$sourceBatchResult, $destinationBatch] = $this->transferGradedToGraded(
                    $lockedSource,
                    $sourceGrade,
                    $targetGrade,
                    $quantity,
                    $userId,
                    $transferNotes,
                    $clientSubmissionId
                );
            }

            // 4. Refresh price board wholesale prices
            $this->priceBoardService->refreshWholesalePricesForProducts(
                [$product->id],
                'grade_transfer',
                $lockedSource->reference
            );

            // 5. Audit Logging
            activity('batch.grade_transferred')
                ->performedOn($lockedSource)
                ->causedBy(User::find($userId))
                ->withProperties([
                    'client_submission_id' => $clientSubmissionId,
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'source_grade' => $sourceGrade->value,
                    'target_grade' => $targetGrade->value,
                    'transferred_quantity' => round($quantity, 3),
                    'source_batch_id' => $sourceBatchResult->id,
                    'source_reference' => $sourceBatchResult->reference,
                    'destination_batch_id' => $destinationBatch->id,
                    'destination_reference' => $destinationBatch->reference,
                    'notes' => $notes,
                ])
                ->log('batch.grade_transferred');

            return [
                'source_batch' => $sourceBatchResult->fresh(),
                'destination_batch' => $destinationBatch->fresh(),
                'source_grade' => $sourceGrade->value,
                'target_grade' => $targetGrade->value,
                'quantity' => round($quantity, 3),
                'is_idempotent_replay' => false,
            ];
        });
    }

    /**
     * Resolve and lock an eligible source batch.
     */
    private function resolveAndLockSourceBatch(
        int $productId,
        int $warehouseId,
        ProductGrade $sourceGrade,
        float $quantity,
        ?StockBatch $preferredBatch = null
    ): StockBatch {
        if ($preferredBatch !== null) {
            /** @var StockBatch $locked */
            $locked = StockBatch::query()->whereKey($preferredBatch->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->product_id !== $productId) {
                throw ValidationException::withMessages([
                    'batch' => "Source batch {$locked->reference} does not match product ID {$productId}.",
                ]);
            }

            if ((int) $locked->warehouse_id !== $warehouseId) {
                throw ValidationException::withMessages([
                    'batch' => "Source batch {$locked->reference} is not located in the selected warehouse.",
                ]);
            }

            if ($locked->warehouse_receive_pending) {
                throw ValidationException::withMessages([
                    'batch' => "Source batch {$locked->reference} cannot be transferred: warehouse receiving confirmation is pending.",
                ]);
            }

            if ($locked->status === BatchStatus::Closed) {
                throw ValidationException::withMessages([
                    'batch' => "Source batch {$locked->reference} is closed.",
                ]);
            }

            $available = $this->availableQuantityOnBatch($locked, $sourceGrade);
            if ($quantity > round($available, 3) + 0.001) {
                throw ValidationException::withMessages([
                    'quantity' => "Quantity to transfer ({$quantity} kg) exceeds available Grade {$sourceGrade->value} stock ({$available} kg) on batch {$locked->reference}.",
                ]);
            }

            return $locked;
        }

        // Automatic deterministic FIFO selection if no preferred batch specified
        $eligibleBatches = $this->findEligibleBatches($productId, $warehouseId, $sourceGrade);
        foreach ($eligibleBatches as $candidate) {
            /** @var StockBatch $locked */
            $locked = StockBatch::query()->whereKey($candidate->id)->lockForUpdate()->first();
            if (! $locked) {
                continue;
            }

            $available = $this->availableQuantityOnBatch($locked, $sourceGrade);
            if ($available >= $quantity - 0.001) {
                return $locked;
            }
        }

        $totalAvailable = $eligibleBatches->sum(fn ($b) => $this->availableQuantityOnBatch($b, $sourceGrade));
        throw ValidationException::withMessages([
            'quantity' => "Insufficient Grade {$sourceGrade->value} physical stock. Requested {$quantity} kg, but only {$totalAvailable} kg is available across eligible batches.",
        ]);
    }

    /**
     * Transfer from a Graded batch (A, B, or C) to another Graded batch (A, B, or C).
     *
     * @return array{0: StockBatch, 1: StockBatch} [sourceBatch, destinationBatch]
     */
    private function transferGradedToGraded(
        StockBatch $lockedSource,
        ProductGrade $sourceGrade,
        ProductGrade $targetGrade,
        float $quantity,
        int $userId,
        string $transferNotes,
        ?string $clientSubmissionId
    ): array {
        // Generate unique destination batch reference
        $existingTransfersCount = StockBatch::query()->where('parent_batch_id', $lockedSource->id)->count();
        $childRef = "{$lockedSource->reference}-T".($existingTransfersCount + 1);
        if (StockBatch::query()->where('reference', $childRef)->exists()) {
            $childRef = "{$lockedSource->reference}-T".($existingTransfersCount + 1).'-'.substr(uniqid(), -4);
        }

        $outNotes = "{$transferNotes} [dest:{$childRef}]";

        // Record OUT movement on source batch
        StockMovement::create([
            'batch_id' => $lockedSource->id,
            'product_id' => $lockedSource->product_id,
            'warehouse_id' => $lockedSource->warehouse_id,
            'created_by' => $userId,
            'grade' => $sourceGrade->value,
            'type' => StockMovementType::Out->value,
            'quantity' => round($quantity, 3),
            'cost_per_unit' => (float) $lockedSource->cost_per_kg,
            'notes' => $outNotes,
        ]);

        // Decrement total_kg on source batch
        $newSourceTotal = round(max(0.0, (float) $lockedSource->total_kg - $quantity), 3);
        $lockedSource->update([
            'total_kg' => $newSourceTotal,
            'status' => $newSourceTotal <= 0.0001 ? BatchStatus::Closed : $lockedSource->status,
        ]);

        // Create child batch in Sorted status for the destination grade
        $destinationBatch = StockBatch::create([
            'product_id' => $lockedSource->product_id,
            'warehouse_id' => $lockedSource->warehouse_id,
            'goods_received_id' => $lockedSource->goods_received_id,
            'goods_received_item_id' => $lockedSource->goods_received_item_id,
            'parent_batch_id' => $lockedSource->id,
            'created_by' => $userId,
            'reference' => $childRef,
            'received_at' => $lockedSource->received_at,
            'total_kg' => round($quantity, 3),
            'cost_per_kg' => $lockedSource->cost_per_kg,
            'transport_cost' => 0.0,
            'labour_cost' => 0.0,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => $lockedSource->warehouse_confirmed_at ?? now(),
            'warehouse_confirmed_by' => $userId,
            'purchase_grade' => $targetGrade->value,
            'grading_mode' => 'fixed_purchase_grade',
            'sorted_at' => now(),
            'notes' => $transferNotes,
        ]);

        // Record IN movement on destination batch
        StockMovement::create([
            'batch_id' => $destinationBatch->id,
            'product_id' => $destinationBatch->product_id,
            'warehouse_id' => $destinationBatch->warehouse_id,
            'created_by' => $userId,
            'grade' => $targetGrade->value,
            'type' => StockMovementType::In->value,
            'quantity' => round($quantity, 3),
            'cost_per_unit' => (float) $destinationBatch->cost_per_kg,
            'notes' => "Transferred from batch {$lockedSource->reference} Grade {$sourceGrade->value}",
        ]);

        return [$lockedSource, $destinationBatch];
    }

    /**
     * Transfer from a Graded batch (A, B, or C) to Mixed (U).
     *
     * @return array{0: StockBatch, 1: StockBatch} [sourceBatch, destinationBatch]
     */
    private function transferGradedToMixed(
        StockBatch $lockedSource,
        ProductGrade $sourceGrade,
        float $quantity,
        int $userId,
        string $transferNotes,
        ?string $clientSubmissionId
    ): array {
        // Generate unique mixed child reference
        $existingTransfersCount = StockBatch::query()->where('parent_batch_id', $lockedSource->id)->count();
        $mixedRef = "{$lockedSource->reference}-MX".($existingTransfersCount + 1);
        if (StockBatch::query()->where('reference', $mixedRef)->exists()) {
            $mixedRef = "{$lockedSource->reference}-MX".($existingTransfersCount + 1).'-'.substr(uniqid(), -4);
        }

        $outNotes = "{$transferNotes} [dest:{$mixedRef}]";

        // Record OUT movement on source batch
        StockMovement::create([
            'batch_id' => $lockedSource->id,
            'product_id' => $lockedSource->product_id,
            'warehouse_id' => $lockedSource->warehouse_id,
            'created_by' => $userId,
            'grade' => $sourceGrade->value,
            'type' => StockMovementType::Out->value,
            'quantity' => round($quantity, 3),
            'cost_per_unit' => (float) $lockedSource->cost_per_kg,
            'notes' => $outNotes,
        ]);

        // Decrement source batch total_kg
        $newSourceTotal = round(max(0.0, (float) $lockedSource->total_kg - $quantity), 3);
        $lockedSource->update([
            'total_kg' => $newSourceTotal,
            'status' => $newSourceTotal <= 0.0001 ? BatchStatus::Closed : $lockedSource->status,
        ]);

        // Create child batch in Pending status representing Mixed stock
        $destinationBatch = StockBatch::create([
            'product_id' => $lockedSource->product_id,
            'warehouse_id' => $lockedSource->warehouse_id,
            'goods_received_id' => $lockedSource->goods_received_id,
            'goods_received_item_id' => $lockedSource->goods_received_item_id,
            'parent_batch_id' => $lockedSource->id,
            'created_by' => $userId,
            'reference' => $mixedRef,
            'received_at' => $lockedSource->received_at,
            'total_kg' => round($quantity, 3),
            'cost_per_kg' => $lockedSource->cost_per_kg,
            'transport_cost' => 0.0,
            'labour_cost' => 0.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => $lockedSource->warehouse_confirmed_at ?? now(),
            'warehouse_confirmed_by' => $userId,
            'purchase_grade' => ProductGrade::Unsorted->value,
            'grading_mode' => 'sort_required',
            'notes' => $transferNotes,
        ]);

        return [$lockedSource, $destinationBatch];
    }

    /**
     * Reverse a prior grade transfer.
     *
     * @return array{source_batch: StockBatch, destination_batch: StockBatch, reversed_quantity: float}
     *
     * @throws ValidationException
     */
    public function reverse(
        StockBatch $destinationBatch,
        float $quantityToReverse,
        int $userId,
        ?string $notes = null,
        ?string $clientSubmissionId = null
    ): array {
        if ($quantityToReverse <= 0.0001) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity to reverse must be greater than zero.',
            ]);
        }

        if ($destinationBatch->parent_batch_id === null) {
            throw ValidationException::withMessages([
                'batch' => "Batch {$destinationBatch->reference} is not a transferred or segregated child batch.",
            ]);
        }

        return DB::transaction(function () use ($destinationBatch, $quantityToReverse, $userId, $notes, $clientSubmissionId): array {
            /** @var StockBatch $lockedDest */
            $lockedDest = StockBatch::withTrashed()->whereKey($destinationBatch->id)->lockForUpdate()->firstOrFail();

            /** @var StockBatch $lockedParent */
            $lockedParent = StockBatch::query()->whereKey($lockedDest->parent_batch_id)->lockForUpdate()->firstOrFail();

            // Check idempotency for retried reversal
            if (! empty($clientSubmissionId)) {
                $existingLog = Activity::query()
                    ->where(function ($q): void {
                        $q->where('log_name', 'batch.grade_transfer_reversed')
                            ->orWhere('description', 'batch.grade_transfer_reversed');
                    })
                    ->get()
                    ->first(function ($act) use ($clientSubmissionId): bool {
                        $props = $act->properties;
                        if (is_array($props)) {
                            return ($props['client_submission_id'] ?? null) === $clientSubmissionId;
                        }
                        if ($props instanceof Collection) {
                            return $props->get('client_submission_id') === $clientSubmissionId;
                        }

                        return false;
                    });

                if ($existingLog !== null) {
                    return [
                        'source_batch' => $lockedParent->fresh(),
                        'destination_batch' => $lockedDest->fresh(),
                        'reversed_quantity' => $quantityToReverse,
                    ];
                }
            }

            // Verify available unconsumed quantity on destination batch
            $destGrade = ProductGrade::tryResolve($lockedDest->purchase_grade) ?? ProductGrade::Unsorted;
            $availableOnDest = $this->availableQuantityOnBatch($lockedDest, $destGrade);

            if ($quantityToReverse > round($availableOnDest, 3) + 0.001) {
                throw ValidationException::withMessages([
                    'quantity' => "Cannot reverse {$quantityToReverse} kg: only {$availableOnDest} kg remains unconsumed on destination batch {$lockedDest->reference}.",
                ]);
            }

            // Deduct from destination batch
            if ($lockedDest->status === BatchStatus::Sorted) {
                $inMovement = StockMovement::query()
                    ->where('batch_id', $lockedDest->id)
                    ->where('type', StockMovementType::In->value)
                    ->first();

                if ($inMovement) {
                    $newInQty = round(max(0.0, (float) $inMovement->quantity - $quantityToReverse), 3);
                    if ($newInQty <= 0.0001) {
                        $inMovement->delete();
                    } else {
                        $inMovement->update(['quantity' => $newInQty]);
                    }
                }
            }

            $newDestTotal = round(max(0.0, (float) $lockedDest->total_kg - $quantityToReverse), 3);
            if ($newDestTotal <= 0.0001) {
                $lockedDest->update([
                    'total_kg' => 0.0,
                    'status' => BatchStatus::Closed,
                ]);
                $lockedDest->delete();
            } else {
                $lockedDest->update([
                    'total_kg' => $newDestTotal,
                ]);
            }

            // Restore source/parent batch
            $parentOutMovement = StockMovement::query()
                ->where('batch_id', $lockedParent->id)
                ->where('type', StockMovementType::Out->value)
                ->where('notes', 'like', "%{$lockedDest->reference}%")
                ->first();

            if ($parentOutMovement) {
                $newOutQty = round(max(0.0, (float) $parentOutMovement->quantity - $quantityToReverse), 3);
                if ($newOutQty <= 0.0001) {
                    $parentOutMovement->delete();
                } else {
                    $parentOutMovement->update(['quantity' => $newOutQty]);
                }
            }

            $newParentTotal = round((float) $lockedParent->total_kg + $quantityToReverse, 3);
            $parentUpdates = ['total_kg' => $newParentTotal];
            if ($lockedParent->status === BatchStatus::Closed) {
                $parentUpdates['status'] = $lockedParent->grading_mode === 'fixed_purchase_grade'
                    ? BatchStatus::Sorted
                    : BatchStatus::Pending;
                $parentUpdates['sorted_at'] = null;
            }
            $lockedParent->update($parentUpdates);

            activity('batch.grade_transfer_reversed')
                ->performedOn($lockedParent)
                ->causedBy(User::find($userId))
                ->withProperties([
                    'client_submission_id' => $clientSubmissionId,
                    'destination_batch_id' => $lockedDest->id,
                    'reversed_quantity' => $quantityToReverse,
                    'new_parent_total_kg' => $newParentTotal,
                    'notes' => $notes,
                ])
                ->log('batch.grade_transfer_reversed');

            return [
                'source_batch' => $lockedParent->fresh(),
                'destination_batch' => $lockedDest->fresh(),
                'reversed_quantity' => $quantityToReverse,
            ];
        });
    }

    /**
     * Compute available stock on a specific batch for a given grade.
     */
    public function availableQuantityOnBatch(StockBatch $batch, ProductGrade $grade): float
    {
        if ($batch->warehouse_receive_pending || $batch->status === BatchStatus::Closed) {
            return 0.0;
        }

        if ($grade === ProductGrade::Unsorted) {
            if ($batch->status !== BatchStatus::Pending) {
                return 0.0;
            }

            return (float) $batch->available_mixed_qty;
        }

        // Graded batch (A, B, or C)
        $batchGrade = ProductGrade::tryResolve($batch->purchase_grade);
        if ($batchGrade !== $grade) {
            return 0.0;
        }

        $inQty = (float) StockMovement::query()
            ->where('batch_id', $batch->id)
            ->where('grade', $grade->value)
            ->whereIn('type', [StockMovementType::In->value, StockMovementType::SaleReversal->value])
            ->sum('quantity');

        if ($inQty <= 0.0001) {
            $inQty = (float) $batch->total_kg;
        }

        $deductions = (float) StockMovement::query()
            ->where('batch_id', $batch->id)
            ->whereIn('type', [
                StockMovementType::Out->value,
                StockMovementType::Sale->value,
                StockMovementType::Wastage->value,
                StockMovementType::Adjustment->value,
            ])
            ->sum('quantity');

        return round(max(0.0, $inQty - $deductions), 3);
    }

    /**
     * Find all eligible physical source batches for a product, warehouse, and grade.
     *
     * @return Collection<int, StockBatch>
     */
    public function findEligibleBatches(int $productId, int $warehouseId, ProductGrade $grade): Collection
    {
        if ($grade === ProductGrade::Unsorted) {
            return StockBatch::query()
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('warehouse_receive_pending', false)
                ->where('status', BatchStatus::Pending)
                ->where(function ($q): void {
                    $q->whereIn('purchase_grade', ['U', 'MIXED', 'Unsorted'])
                        ->orWhereNull('purchase_grade');
                })
                ->orderBy('received_at')
                ->orderBy('id')
                ->get()
                ->filter(fn (StockBatch $b): bool => $this->availableQuantityOnBatch($b, ProductGrade::Unsorted) > 0.001)
                ->values();
        }

        return StockBatch::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('warehouse_receive_pending', false)
            ->where('purchase_grade', $grade->value)
            ->where('status', '!=', BatchStatus::Closed)
            ->orderBy('received_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (StockBatch $b): bool => $this->availableQuantityOnBatch($b, $grade) > 0.001)
            ->values();
    }
}
