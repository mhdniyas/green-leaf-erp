<?php

declare(strict_types=1);

namespace App\Services\Printing;

use App\Models\SortSheetPreset;
use App\Models\SortSheetPresetBatch;
use App\Models\User;
use Illuminate\Support\Collection;

class PrintV1ShopOrderService
{
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
     * Load existing Shop Order (portrait) user presets.
     * READ-ONLY: Never modifies preset records.
     *
     * @return Collection<int, SortSheetPreset>
     */
    public function userPresets(?User $user): Collection
    {
        return $this->sortSheetService->userPresets($user, 'shop-order');
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
     * Build Shop Order data grouped by shop with 1 shop per section/page.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildShopOrderData(array $filters): array
    {
        $matrixResult = $this->sortSheetService->buildMatrix($filters);

        $matrix = $matrixResult['matrix'];
        $productMeta = $matrixResult['productMeta'];
        $filteredShops = $matrixResult['filteredShops'];
        $candidateShops = $matrixResult['candidateShops'];
        $skippedEmptyShops = $matrixResult['skippedEmptyShops'];
        $skipEmptyShops = (bool) ($matrixResult['filters']['skip_empty_shops'] ?? true);

        $formatQty = fn (float $qty): string => rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');

        $shopSections = [];
        $totalItemsAllShops = 0;
        $totalQtyAllShops = 0.0;

        foreach ($filteredShops as $shop) {
            $rows = [];
            $shopTotalQty = 0.0;

            foreach ($matrix as $rowKey => $shopQtys) {
                $qty = (float) ($shopQtys[$shop->id] ?? 0.0);
                if ($qty <= 0.0) {
                    continue;
                }

                $meta = $productMeta[$rowKey];
                $rows[] = [
                    'product_id' => $meta['product_id'],
                    'code' => $meta['sku'] ?: (string) $meta['product_id'],
                    'name' => $meta['display_name'],
                    'display_name' => $meta['display_name'],
                    'qty' => $qty,
                    'formatted_qty' => $formatQty($qty),
                    'unit' => $meta['unit'],
                    'category_id' => $meta['category_id'],
                    'category_name' => $meta['category_name'],
                    'grade' => $meta['grade'],
                ];

                $shopTotalQty += $qty;
            }

            if (empty($rows) && $skipEmptyShops) {
                continue;
            }

            $totalItemsAllShops += count($rows);
            $totalQtyAllShops += $shopTotalQty;

            $shopSections[] = [
                'shop' => $shop,
                'rows' => $rows,
                'total_qty' => $shopTotalQty,
                'formatted_total_qty' => $formatQty($shopTotalQty),
                'total_items' => count($rows),
            ];
        }

        return [
            'matrixResult' => $matrixResult,
            'shopSections' => $shopSections,
            'totalShopsWithOrders' => count($shopSections),
            'totalItemsAllShops' => $totalItemsAllShops,
            'totalQtyAllShops' => $totalQtyAllShops,
            'formattedTotalQtyAllShops' => $formatQty($totalQtyAllShops),
            'filteredShops' => $filteredShops,
            'candidateShops' => $candidateShops,
            'skippedEmptyShops' => $skippedEmptyShops,
            'selectedGrades' => $matrixResult['selectedGrades'],
            'gradeHeading' => $matrixResult['gradeHeading'],
            'date' => $matrixResult['date'],
            'selectedWarehouse' => $matrixResult['selectedWarehouse'],
            'filters' => $matrixResult['filters'],
            'hasData' => ! empty($shopSections),
        ];
    }
}
