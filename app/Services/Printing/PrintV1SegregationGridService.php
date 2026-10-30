<?php

declare(strict_types=1);

namespace App\Services\Printing;

use App\Models\SortSheetPreset;
use App\Models\SortSheetPresetBatch;
use App\Models\User;
use Illuminate\Support\Collection;

class PrintV1SegregationGridService
{
    /**
     * Number of product rows per page in Segregation Grid print.
     */
    public const ROWS_PER_PAGE = 9;

    public function __construct(
        private readonly PrintV1SortSheetService $sortSheetService,
    ) {}

    /**
     * Retrieve filter options scoped to warehouse if provided.
     *
     * @return array<string, mixed>
     */
    public function filterOptions(?int $warehouseId = null): array
    {
        return $this->sortSheetService->filterOptions($warehouseId);
    }

    /**
     * Load existing Segregation Grid user presets.
     * READ-ONLY: Never modifies preset records.
     *
     * @return Collection<int, SortSheetPreset>
     */
    public function userPresets(?User $user): Collection
    {
        return $this->sortSheetService->userPresets($user, 'grid');
    }

    /**
     * Load existing user preset batches.
     * READ-ONLY: Never modifies preset records.
     *
     * @return Collection<int, SortSheetPresetBatch>
     */
    public function userPresetBatches(?User $user): Collection
    {
        return $this->sortSheetService->userPresetBatches($user);
    }

    /**
     * Get operational business date string.
     */
    public function defaultOperationalDate(): string
    {
        return $this->sortSheetService->defaultOperationalDate();
    }

    /**
     * Build Segregation Grid matrix data and paginated print chunks.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildGridData(array $filters): array
    {
        $matrixResult = $this->sortSheetService->buildMatrix($filters);

        $matrix = $matrixResult['matrix'];
        $productMeta = $matrixResult['productMeta'];
        $filteredShops = $matrixResult['filteredShops'];
        $candidateShops = $matrixResult['candidateShops'];
        $skippedEmptyShops = $matrixResult['skippedEmptyShops'];

        $shopCount = max(1, $filteredShops->count());
        $shopWidth = (100 - 4 - 15 - 6) / $shopCount;

        $gridPages = array_chunk($matrix, self::ROWS_PER_PAGE, true);

        return [
            'matrixResult' => $matrixResult,
            'gridPages' => $gridPages,
            'matrix' => $matrix,
            'productMeta' => $productMeta,
            'filteredShops' => $filteredShops,
            'candidateShops' => $candidateShops,
            'skippedEmptyShops' => $skippedEmptyShops,
            'selectedGrades' => $matrixResult['selectedGrades'],
            'gradeHeading' => $matrixResult['gradeHeading'],
            'date' => $matrixResult['date'],
            'selectedWarehouse' => $matrixResult['selectedWarehouse'],
            'filters' => $matrixResult['filters'],
            'grandTotal' => $matrixResult['grandTotal'],
            'shopTotals' => $matrixResult['shopTotals'],
            'shopWidth' => $shopWidth,
            'hasData' => ! empty($matrix),
        ];
    }
}
