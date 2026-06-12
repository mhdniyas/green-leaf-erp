<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\DTOs\Inventory\SortingData;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Exceptions\Inventory\BatchAlreadySortedException;
use App\Exceptions\Inventory\SortingQuantityMismatchException;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\WastageEntry;
use App\Services\Pricing\PriceBoardService;
use Illuminate\Support\Facades\DB;

class ProcessBatchSortingAction
{
    public function __construct(
        private readonly PriceBoardService $priceBoardService,
    ) {}

    /**
     * Process the sorting of a batch into grades.
     *
     * Business rules:
     * - Batch must be in Pending status
     * - Sum of all grade quantities must equal batch total_kg (±0.01 tolerance)
     * - Creates one StockMovement per sellable grade (A, B, C)
     * - Creates one WastageEntry for Damage grade
     * - Marks batch as Sorted
     *
     * @throws BatchAlreadySortedException
     * @throws SortingQuantityMismatchException
     */
    public function execute(StockBatch $batch, SortingData $data, int $userId): StockBatch
    {
        if (! $batch->canBeSorted()) {
            throw new BatchAlreadySortedException(
                "Batch {$batch->reference} cannot be sorted. Current status: {$batch->status->label()}"
            );
        }

        $totalSorted = $data->totalSortedKg();
        $tolerance = 0.01;

        if (abs($totalSorted - (float) $batch->total_kg) > $tolerance) {
            throw new SortingQuantityMismatchException(
                "Sorted quantity ({$totalSorted} kg) does not match batch total ({$batch->total_kg} kg)"
            );
        }

        return DB::transaction(function () use ($batch, $data, $userId): StockBatch {

            foreach ($data->grades as $gradeEntry) {
                /** @var ProductGrade $grade */
                $grade = $gradeEntry['grade'];
                $quantity = $gradeEntry['quantity'];

                if ($grade === ProductGrade::Damage) {
                    // Damage → Wastage entry
                    WastageEntry::create([
                        'product_id' => $batch->product_id,
                        'batch_id' => $batch->id,
                        'recorded_by' => $userId,
                        'grade' => ProductGrade::Damage->value,
                        'quantity' => $quantity,
                        'cost_per_kg' => $batch->cost_per_kg,
                        'reason' => WastageReason::SortingDamage->value,
                        'wastage_date' => now()->toDateString(),
                        'notes' => "Auto-recorded from sorting batch {$batch->reference}",
                    ]);
                } else {
                    // Sellable grade → Stock movement IN
                    StockMovement::create([
                        'batch_id' => $batch->id,
                        'product_id' => $batch->product_id,
                        'created_by' => $userId,
                        'grade' => $grade->value,
                        'type' => StockMovementType::In->value,
                        'quantity' => $quantity,
                        'cost_per_unit' => $batch->cost_per_kg,
                        'warehouse_id' => $batch->warehouse_id,
                        'notes' => "Sorted from batch {$batch->reference}",
                    ]);
                }
            }

            // Mark batch as sorted
            $batch->update([
                'status' => BatchStatus::Sorted,
                'sorted_at' => now(),
            ]);

            $this->priceBoardService->refreshWholesalePricesForProducts(
                [$batch->product_id],
                'sorting',
                $batch->reference
            );

            activity()
                ->performedOn($batch)
                ->causedBy($userId)
                ->withProperties(['grades' => $data->grades])
                ->log('batch.sorted');

            return $batch->fresh(['product', 'stockMovements', 'wastageEntries']);
        });
    }
}
