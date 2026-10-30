<?php

declare(strict_types=1);

namespace App\Services\Printing;

use App\Models\SortSheetPreset;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class PrintV1SelectionService
{
    public const ROWS_PER_PAGE = 36;

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
     * Load existing Selection user presets.
     * READ-ONLY: Never modifies preset records.
     *
     * @return Collection<int, SortSheetPreset>
     */
    public function userPresets(?User $user): Collection
    {
        return $this->sortSheetService->userPresets($user, 'selection');
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
     * Build Selection report data and paginated print pages from resolved filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildSelectionData(array $filters): array
    {
        $matrixResult = $this->sortSheetService->buildMatrix($filters);

        $matrix = $matrixResult['matrix'];
        $productMeta = $matrixResult['productMeta'];
        $filteredShops = $matrixResult['filteredShops'];
        $candidateShops = $matrixResult['candidateShops'];
        $skippedEmptyShops = $matrixResult['skippedEmptyShops'];
        $skipEmptyShops = (bool) ($matrixResult['filters']['skip_empty_shops'] ?? true);
        $showCode = (bool) ($matrixResult['filters']['show_code'] ?? true);
        $showCategoryTitles = (bool) ($matrixResult['filters']['show_category_titles'] ?? true);
        $codeSort = (bool) ($matrixResult['filters']['code_sort'] ?? true);

        $formatQty = fn (float $qty): string => rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');

        $shopSections = [];
        $totalItemsAllShops = 0;
        $totalQtyAllShops = 0.0;

        foreach ($filteredShops as $shop) {
            $items = [];
            $shopTotalQty = 0.0;

            foreach ($matrix as $rowKey => $shopQtys) {
                $qty = (float) ($shopQtys[$shop->id] ?? 0.0);
                if ($qty <= 0.0) {
                    continue;
                }

                $meta = $productMeta[$rowKey];
                $shopTotalQty += $qty;
                $items[] = [
                    'row_key' => $rowKey,
                    'product_id' => $meta['product_id'],
                    'code' => $meta['sku'] ?: (string) $meta['product_id'],
                    'name' => $meta['display_name'] ?? $meta['name'],
                    'grade' => $meta['grade'] ?? 'A',
                    'category_id' => (int) ($meta['category_id'] ?? 0),
                    'category_name' => $meta['category_name'] ?? 'General',
                    'unit' => $meta['unit'] ?? 'kg',
                    'qty' => $qty,
                    'formatted_qty' => $formatQty($qty),
                ];
            }

            if ($skipEmptyShops && empty($items)) {
                continue;
            }

            $shopSections[] = [
                'shop' => $shop,
                'items' => $items,
                'total_items' => count($items),
                'total_qty' => $shopTotalQty,
                'formatted_total_qty' => $formatQty($shopTotalQty),
            ];

            $totalItemsAllShops += count($items);
            $totalQtyAllShops += $shopTotalQty;
        }

        // Build paginated shop print pages (36 rows per page standard)
        $shopPages = [];
        foreach ($shopSections as $section) {
            if (empty($section['items'])) {
                continue;
            }

            $chunks = array_chunk($section['items'], self::ROWS_PER_PAGE);
            $totalPages = count($chunks);
            foreach ($chunks as $pageIndex => $chunk) {
                $shopPages[] = [
                    'shop' => $section['shop'],
                    'rows' => $chunk,
                    'page_number' => $pageIndex + 1,
                    'total_pages' => $totalPages,
                    'shop_total_qty' => $section['total_qty'],
                    'formatted_shop_total_qty' => $section['formatted_total_qty'],
                    'total_shop_items' => $section['total_items'],
                ];
            }
        }

        return [
            'matrixResult' => $matrixResult,
            'shopSections' => $shopSections,
            'shopPages' => $shopPages,
            'totalShopsWithOrders' => count($shopSections),
            'totalItemsAllShops' => $totalItemsAllShops,
            'totalQtyAllShops' => $totalQtyAllShops,
            'formattedTotalQtyAllShops' => $formatQty($totalQtyAllShops),
            'filteredShops' => $filteredShops,
            'candidateShops' => $candidateShops,
            'skippedEmptyShops' => $skippedEmptyShops,
            'filters' => $matrixResult['filters'],
            'date' => $matrixResult['date'],
            'selectedWarehouse' => $matrixResult['selectedWarehouse'],
            'gradeHeading' => $matrixResult['gradeHeading'],
            'selectedGrades' => $matrixResult['selectedGrades'],
            'hasData' => ! empty($shopSections),
        ];
    }
}
