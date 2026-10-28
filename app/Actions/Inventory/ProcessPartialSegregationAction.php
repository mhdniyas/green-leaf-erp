<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WastageEntry;
use App\Services\Pricing\PriceBoardService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

class ProcessPartialSegregationAction
{
    public function __construct(
        private readonly PriceBoardService $priceBoardService,
    ) {}

    /**
     * Partially segregate or regrade a quantity from an available batch into a specific sellable grade or unsellable damage.
     *
     * @return array{parent: StockBatch, child: ?StockBatch, wastage: ?WastageEntry}
     *
     * @throws ValidationException
     */
    public function execute(
        StockBatch $batch,
        ProductGrade $targetGrade,
        float $quantity,
        int $userId,
        ?string $notes = null,
        ?string $clientSubmissionId = null,
        ?WastageReason $damageReason = null,
        bool $immediateWriteOff = true
    ): array {
        if ($quantity <= 0.001) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity to segregate must be greater than zero.',
            ]);
        }

        if (! in_array($targetGrade, [
            ProductGrade::GradeA,
            ProductGrade::GradeB,
            ProductGrade::GradeC,
            ProductGrade::Damage,
        ], true)) {
            throw ValidationException::withMessages([
                'grade' => 'Partial segregation target grade must be Grade A, Grade B, Grade C, or Damage.',
            ]);
        }

        return DB::transaction(function () use ($batch, $targetGrade, $quantity, $userId, $notes, $clientSubmissionId, $damageReason, $immediateWriteOff): array {
            /** @var StockBatch $lockedBatch */
            $lockedBatch = StockBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();

            // Atomic idempotency check: verify if an identical retried segregation operation already completed
            if (! empty($clientSubmissionId)) {
                /** @var StockBatch|null $existingChild */
                $existingChild = StockBatch::query()
                    ->where('parent_batch_id', $lockedBatch->id)
                    ->where('notes', 'like', "%[submission_id:{$clientSubmissionId}]%")
                    ->first();

                if ($existingChild !== null) {
                    // Check payload consistency
                    if ($existingChild->purchase_grade !== $targetGrade->value ||
                        abs((float) $existingChild->total_kg - round($quantity, 3)) > 0.001) {
                        throw ValidationException::withMessages([
                            'client_submission_id' => 'The submission payload does not match the original segregation operation.',
                        ]);
                    }

                    return [
                        'parent' => $lockedBatch->fresh(['product', 'warehouse', 'segregatedBatches']),
                        'child' => $existingChild->fresh(['product', 'warehouse', 'stockMovements']),
                        'wastage' => null,
                    ];
                }

                // Check if damage entry was recorded with this submission ID
                $existingWastage = WastageEntry::query()
                    ->where('batch_id', $lockedBatch->id)
                    ->where('notes', 'like', "%[submission_id:{$clientSubmissionId}]%")
                    ->first();

                if ($existingWastage !== null) {
                    return [
                        'parent' => $lockedBatch->fresh(['product', 'warehouse', 'segregatedBatches']),
                        'child' => null,
                        'wastage' => $existingWastage,
                    ];
                }
            }

            if ($lockedBatch->warehouse_receive_pending) {
                throw ValidationException::withMessages([
                    'batch' => "Batch {$lockedBatch->reference} cannot be segregated: warehouse receiving confirmation is pending.",
                ]);
            }

            if ($lockedBatch->status === BatchStatus::Closed) {
                throw ValidationException::withMessages([
                    'batch' => "Batch {$lockedBatch->reference} cannot be segregated: batch is closed.",
                ]);
            }

            $currentGrade = ProductGrade::tryResolve($lockedBatch->purchase_grade);
            if ($targetGrade !== ProductGrade::Damage && $currentGrade !== null && $targetGrade === $currentGrade) {
                throw ValidationException::withMessages([
                    'grade' => "Batch {$lockedBatch->reference} is already Grade {$currentGrade->value}. Choose a different target grade.",
                ]);
            }

            // Compute available stock on this batch
            if ($lockedBatch->status === BatchStatus::Pending) {
                $availableQty = $lockedBatch->available_mixed_qty;
            } else {
                $inQty = (float) $lockedBatch->stockMovements()
                    ->whereIn('type', [StockMovementType::In->value, StockMovementType::SaleReversal->value])
                    ->sum('quantity');

                if ($inQty <= 0.0001) {
                    $inQty = (float) $lockedBatch->total_kg;
                }

                $deductions = (float) $lockedBatch->stockMovements()
                    ->whereIn('type', [
                        StockMovementType::Out->value,
                        StockMovementType::Sale->value,
                        StockMovementType::Wastage->value,
                        StockMovementType::Adjustment->value,
                    ])
                    ->sum('quantity');

                $availableQty = round(max(0.0, $inQty - $deductions), 3);
            }

            if ($quantity > round($availableQty, 3) + 0.001) {
                throw ValidationException::withMessages([
                    'quantity' => "Quantity to segregate ({$quantity} kg) exceeds available quantity ({$availableQty} kg) on batch {$lockedBatch->reference}.",
                ]);
            }

            $sourceGradeVal = $lockedBatch->purchase_grade ?: 'U';

            // Case A: Segregation to Unsellable Damage (Write-off)
            if ($targetGrade === ProductGrade::Damage && $immediateWriteOff) {
                $damageNotes = $notes ?: "Damaged / unsellable write-off from batch {$lockedBatch->reference}";
                if (! empty($clientSubmissionId)) {
                    $damageNotes .= " [submission_id:{$clientSubmissionId}]";
                }

                // Create WastageEntry preserving product, original grade, warehouse, batch, cost, and reason
                $wastage = WastageEntry::create([
                    'product_id' => $lockedBatch->product_id,
                    'batch_id' => $lockedBatch->id,
                    'recorded_by' => $userId,
                    'grade' => $sourceGradeVal,
                    'quantity' => round($quantity, 3),
                    'cost_per_kg' => (float) $lockedBatch->cost_per_kg,
                    'reason' => $damageReason ?? WastageReason::SortingDamage,
                    'wastage_date' => now()->toDateString(),
                    'notes' => $damageNotes,
                ]);

                // For sorted batches, decrement total_kg and record wastage movement
                if ($lockedBatch->status === BatchStatus::Sorted) {
                    StockMovement::create([
                        'batch_id' => $lockedBatch->id,
                        'product_id' => $lockedBatch->product_id,
                        'warehouse_id' => $lockedBatch->warehouse_id,
                        'created_by' => $userId,
                        'grade' => $sourceGradeVal,
                        'type' => StockMovementType::Wastage->value,
                        'quantity' => round($quantity, 3),
                        'cost_per_unit' => (float) $lockedBatch->cost_per_kg,
                        'notes' => $damageNotes,
                    ]);

                    $newParentTotal = round(max(0.0, (float) $lockedBatch->total_kg - $quantity), 3);
                    $lockedBatch->update([
                        'total_kg' => $newParentTotal,
                        'status' => $newParentTotal <= 0.0001 ? BatchStatus::Closed : $lockedBatch->status,
                    ]);
                } else {
                    // For pending batches, WastageEntry already decrements remaining_qty.
                    // If available mixed is now 0, close the batch.
                    if ($lockedBatch->fresh()->available_mixed_qty <= 0.0001) {
                        $lockedBatch->update([
                            'status' => BatchStatus::Closed,
                            'sorted_at' => now(),
                        ]);
                    }
                }

                $this->priceBoardService->refreshWholesalePricesForProducts(
                    [$lockedBatch->product_id],
                    'segregation_damage',
                    $lockedBatch->reference
                );

                $remainingParentKg = (float) $lockedBatch->fresh()->remaining_qty;

                activity()
                    ->performedOn($lockedBatch)
                    ->causedBy(User::query()->find($userId))
                    ->withProperties([
                        'client_submission_id' => $clientSubmissionId,
                        'wastage_entry_id' => $wastage->id,
                        'source_grade' => $sourceGradeVal,
                        'damage_quantity' => $quantity,
                        'remaining_parent_total_kg' => $remainingParentKg,
                    ])
                    ->log('batch.damage_write_off');

                return [
                    'parent' => $lockedBatch->fresh(['product', 'warehouse', 'segregatedBatches']),
                    'child' => null,
                    'wastage' => $wastage,
                ];
            }

            // Case B: Segregation into a sellable grade (Grade A, B, C, D)
            // Generate unique child batch reference
            $existingCount = StockBatch::query()->where('parent_batch_id', $lockedBatch->id)->count();
            $childReference = "{$lockedBatch->reference}-S".($existingCount + 1);
            if (StockBatch::query()->where('reference', $childReference)->exists()) {
                $childReference = "{$lockedBatch->reference}-S".($existingCount + 1).'-'.substr(uniqid(), -4);
            }

            $childNotes = $notes ?: "Partially segregated {$quantity} kg to Grade {$targetGrade->value} from batch {$lockedBatch->reference}";
            if (! empty($clientSubmissionId)) {
                $childNotes .= " [submission_id:{$clientSubmissionId}]";
            }

            // If source batch was already sorted, record OUT movement on parent batch
            if ($lockedBatch->status === BatchStatus::Sorted) {
                StockMovement::create([
                    'batch_id' => $lockedBatch->id,
                    'product_id' => $lockedBatch->product_id,
                    'warehouse_id' => $lockedBatch->warehouse_id,
                    'created_by' => $userId,
                    'grade' => $sourceGradeVal,
                    'type' => StockMovementType::Out->value,
                    'quantity' => round($quantity, 3),
                    'cost_per_unit' => (float) $lockedBatch->cost_per_kg,
                    'notes' => "Regraded {$quantity} kg to Grade {$targetGrade->value} (Batch {$childReference})",
                ]);
            }

            // Create segregated child batch in Sorted status
            $childBatch = StockBatch::create([
                'product_id' => $lockedBatch->product_id,
                'warehouse_id' => $lockedBatch->warehouse_id,
                'goods_received_id' => $lockedBatch->goods_received_id,
                'goods_received_item_id' => $lockedBatch->goods_received_item_id,
                'parent_batch_id' => $lockedBatch->id,
                'created_by' => $userId,
                'reference' => $childReference,
                'received_at' => $lockedBatch->received_at,
                'total_kg' => round($quantity, 3),
                'cost_per_kg' => $lockedBatch->cost_per_kg,
                'transport_cost' => 0.0,
                'labour_cost' => 0.0,
                'status' => BatchStatus::Sorted,
                'warehouse_receive_pending' => false,
                'warehouse_confirmed_at' => $lockedBatch->warehouse_confirmed_at ?? now(),
                'warehouse_confirmed_by' => $userId,
                'purchase_grade' => $targetGrade->value,
                'grading_mode' => 'fixed_purchase_grade',
                'sorted_at' => now(),
                'notes' => $childNotes,
            ]);

            // Create StockMovement IN on the segregated batch
            StockMovement::create([
                'batch_id' => $childBatch->id,
                'product_id' => $childBatch->product_id,
                'warehouse_id' => $childBatch->warehouse_id,
                'created_by' => $userId,
                'grade' => $targetGrade->value,
                'type' => StockMovementType::In->value,
                'quantity' => round($quantity, 3),
                'cost_per_unit' => (float) $childBatch->cost_per_kg,
                'notes' => "Partially segregated from batch {$lockedBatch->reference}",
            ]);

            // Decrement remaining total_kg on parent batch
            $newParentTotal = round(max(0.0, (float) $lockedBatch->total_kg - $quantity), 3);
            if ($newParentTotal <= 0.0001) {
                $lockedBatch->update([
                    'total_kg' => 0.0,
                    'status' => BatchStatus::Closed,
                    'sorted_at' => now(),
                ]);
            } else {
                $lockedBatch->update([
                    'total_kg' => $newParentTotal,
                ]);
            }

            $this->priceBoardService->refreshWholesalePricesForProducts(
                [$lockedBatch->product_id],
                'segregation',
                $lockedBatch->reference
            );

            activity()
                ->performedOn($lockedBatch)
                ->causedBy(User::query()->find($userId))
                ->withProperties([
                    'client_submission_id' => $clientSubmissionId,
                    'child_batch_id' => $childBatch->id,
                    'child_reference' => $childBatch->reference,
                    'target_grade' => $targetGrade->value,
                    'segregated_quantity' => $quantity,
                    'remaining_parent_total_kg' => $newParentTotal,
                ])
                ->log('batch.partial_segregation');

            return [
                'parent' => $lockedBatch->fresh(['product', 'warehouse', 'segregatedBatches']),
                'child' => $childBatch->fresh(['product', 'warehouse', 'stockMovements']),
                'wastage' => null,
            ];
        });
    }

    /**
     * Reverse a partial segregation back to the parent batch.
     *
     * @return array{parent: StockBatch, child: StockBatch}
     *
     * @throws ValidationException
     */
    public function reverse(
        StockBatch $childBatch,
        float $quantityToReverse,
        int $userId,
        ?string $notes = null,
        ?string $clientSubmissionId = null
    ): array {
        if ($quantityToReverse <= 0.001) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity to reverse must be greater than zero.',
            ]);
        }

        if ($childBatch->parent_batch_id === null) {
            throw ValidationException::withMessages([
                'batch' => "Batch {$childBatch->reference} is not a segregated child batch.",
            ]);
        }

        return DB::transaction(function () use ($childBatch, $quantityToReverse, $userId, $notes, $clientSubmissionId): array {
            /** @var StockBatch $lockedChild */
            $lockedChild = StockBatch::withTrashed()->whereKey($childBatch->id)->lockForUpdate()->firstOrFail();

            /** @var StockBatch $parentBatch */
            $parentBatch = StockBatch::query()->whereKey($lockedChild->parent_batch_id)->lockForUpdate()->firstOrFail();

            // Atomic idempotency check for retried reversal
            if (! empty($clientSubmissionId)) {
                $existingLog = Activity::query()
                    ->where(function ($q) {
                        $q->where('log_name', 'batch.segregation_reversed')
                            ->orWhere('description', 'batch.segregation_reversed');
                    })
                    ->get()
                    ->first(fn ($act): bool => (
                        (is_array($act->properties) ? ($act->properties['client_submission_id'] ?? null) : null) === $clientSubmissionId
                        || ($act->properties instanceof Collection && $act->properties->get('client_submission_id') === $clientSubmissionId)
                    ));

                if ($existingLog !== null) {
                    return [
                        'parent' => $parentBatch->fresh(['product', 'warehouse', 'segregatedBatches']),
                        'child' => $lockedChild->fresh(['product', 'warehouse']),
                    ];
                }
            }

            // Calculate unconsumed available quantity on the segregated child batch
            $deductions = (float) StockMovement::query()
                ->where('batch_id', $lockedChild->id)
                ->whereIn('type', [
                    StockMovementType::Out->value,
                    StockMovementType::Sale->value,
                    StockMovementType::Wastage->value,
                    StockMovementType::Adjustment->value,
                ])
                ->sum('quantity');

            $availableOnChild = round(max(0.0, (float) $lockedChild->total_kg - $deductions), 3);

            if ($quantityToReverse > $availableOnChild + 0.001) {
                throw ValidationException::withMessages([
                    'quantity' => "Cannot reverse {$quantityToReverse} kg: only {$availableOnChild} kg remains unconsumed in segregated batch {$lockedChild->reference}.",
                ]);
            }

            // Adjust or reduce the StockMovement IN on the child batch
            $inMovement = StockMovement::query()
                ->where('batch_id', $lockedChild->id)
                ->where('type', StockMovementType::In->value)
                ->first();

            $newChildTotal = round(max(0.0, (float) $lockedChild->total_kg - $quantityToReverse), 3);

            if ($inMovement) {
                $newInQty = round(max(0.0, (float) $inMovement->quantity - $quantityToReverse), 3);
                if ($newInQty <= 0.0001) {
                    $inMovement->delete();
                } else {
                    $inMovement->update(['quantity' => $newInQty]);
                }
            }

            if ($newChildTotal <= 0.0001) {
                $lockedChild->update([
                    'total_kg' => 0.0,
                    'status' => BatchStatus::Closed,
                ]);
                $lockedChild->delete();
            } else {
                $lockedChild->update([
                    'total_kg' => $newChildTotal,
                ]);
            }

            // If parent batch had an OUT movement from regrading, reverse or adjust it
            $parentOutMovement = StockMovement::query()
                ->where('batch_id', $parentBatch->id)
                ->where('type', StockMovementType::Out->value)
                ->where('notes', 'like', "%{$lockedChild->reference}%")
                ->first();

            if ($parentOutMovement) {
                $newOutQty = round(max(0.0, (float) $parentOutMovement->quantity - $quantityToReverse), 3);
                if ($newOutQty <= 0.0001) {
                    $parentOutMovement->delete();
                } else {
                    $parentOutMovement->update(['quantity' => $newOutQty]);
                }
            }

            // Restore parent batch total_kg and status
            $newParentTotal = round((float) $parentBatch->total_kg + $quantityToReverse, 3);
            $parentUpdates = ['total_kg' => $newParentTotal];
            if ($parentBatch->status === BatchStatus::Closed) {
                $parentUpdates['status'] = $parentBatch->grading_mode === 'fixed_purchase_grade'
                    ? BatchStatus::Sorted
                    : BatchStatus::Pending;
                $parentUpdates['sorted_at'] = null;
            }
            $parentBatch->update($parentUpdates);

            activity('batch.segregation_reversed')
                ->performedOn($parentBatch)
                ->causedBy(User::query()->find($userId))
                ->withProperties([
                    'client_submission_id' => $clientSubmissionId,
                    'reversed_from_child_id' => $lockedChild->id,
                    'reversed_quantity' => $quantityToReverse,
                    'new_parent_total_kg' => $newParentTotal,
                    'notes' => $notes,
                ])
                ->log('batch.segregation_reversed');

            return [
                'parent' => $parentBatch->fresh(['product', 'warehouse', 'segregatedBatches']),
                'child' => $lockedChild->fresh(['product', 'warehouse']),
            ];
        });
    }

    /**
     * Reverse a recorded damage write-off back to the parent batch.
     *
     * @return array{parent: StockBatch, wastage: ?WastageEntry}
     *
     * @throws ValidationException
     */
    public function reverseDamage(
        WastageEntry $wastage,
        float $quantityToReverse,
        int $userId,
        ?string $notes = null,
        ?string $clientSubmissionId = null
    ): array {
        if ($quantityToReverse <= 0.001) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity to reverse must be greater than zero.',
            ]);
        }

        if ($quantityToReverse > (float) $wastage->quantity + 0.001) {
            throw ValidationException::withMessages([
                'quantity' => "Cannot reverse {$quantityToReverse} kg: exceeds recorded damage quantity ({$wastage->quantity} kg).",
            ]);
        }

        return DB::transaction(function () use ($wastage, $quantityToReverse, $userId, $notes, $clientSubmissionId): array {
            /** @var WastageEntry $lockedWastage */
            $lockedWastage = WastageEntry::query()->whereKey($wastage->id)->lockForUpdate()->firstOrFail();

            /** @var StockBatch $parentBatch */
            $parentBatch = StockBatch::query()->whereKey($lockedWastage->batch_id)->lockForUpdate()->firstOrFail();

            // Reverse wastage stock movement if present on sorted batch
            $wastageMovement = StockMovement::query()
                ->where('batch_id', $parentBatch->id)
                ->where('type', StockMovementType::Wastage->value)
                ->latest('id')
                ->first();

            if ($wastageMovement) {
                $newWastageQty = round(max(0.0, (float) $wastageMovement->quantity - $quantityToReverse), 3);
                if ($newWastageQty <= 0.0001) {
                    $wastageMovement->delete();
                } else {
                    $wastageMovement->update(['quantity' => $newWastageQty]);
                }
            }

            $newWastageTotal = round(max(0.0, (float) $lockedWastage->quantity - $quantityToReverse), 3);
            if ($newWastageTotal <= 0.0001) {
                $lockedWastage->delete();
            } else {
                $lockedWastage->update(['quantity' => $newWastageTotal]);
            }

            // Restore parent batch total_kg and status
            $parentUpdates = [];
            if ($parentBatch->status !== BatchStatus::Pending) {
                $newParentTotal = round((float) $parentBatch->total_kg + $quantityToReverse, 3);
                $parentUpdates['total_kg'] = $newParentTotal;
            } else {
                $newParentTotal = (float) $parentBatch->total_kg;
            }

            if ($parentBatch->status === BatchStatus::Closed) {
                $parentUpdates['status'] = $parentBatch->grading_mode === 'fixed_purchase_grade'
                    ? BatchStatus::Sorted
                    : BatchStatus::Pending;
                $parentUpdates['sorted_at'] = null;
            }

            if ($parentUpdates !== []) {
                $parentBatch->update($parentUpdates);
            }

            activity('batch.damage_reversed')
                ->performedOn($parentBatch)
                ->causedBy(User::query()->find($userId))
                ->withProperties([
                    'client_submission_id' => $clientSubmissionId,
                    'reversed_wastage_id' => $lockedWastage->id,
                    'reversed_quantity' => $quantityToReverse,
                    'new_parent_total_kg' => $newParentTotal,
                    'notes' => $notes,
                ])
                ->log('batch.damage_reversed');

            return [
                'parent' => $parentBatch->fresh(['product', 'warehouse', 'segregatedBatches']),
                'wastage' => $lockedWastage->trashed() ? null : $lockedWastage->fresh(),
            ];
        });
    }
}
