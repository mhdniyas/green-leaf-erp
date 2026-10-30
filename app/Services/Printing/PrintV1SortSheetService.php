<?php

declare(strict_types=1);

namespace App\Services\Printing;

use App\Enums\Inventory\ProductGrade;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopPriceGroup;
use App\Models\SortSheetPreset;
use App\Models\SortSheetPresetBatch;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class PrintV1SortSheetService
{
    /**
     * Allowed printable grades for Sort Sheet V1.
     *
     * @var array<int, string>
     */
    public const ALLOWED_GRADES = ['A', 'B', 'C'];

    public function __construct(
        private readonly PurchaserBusinessDayService $businessDayService,
    ) {}

    /**
     * Resolve default operational business date.
     */
    public function defaultOperationalDate(): string
    {
        return $this->businessDayService->operationalDate()->toDateString();
    }

    /**
     * Load existing user presets scoped to authorized user and surface.
     * READ-ONLY: Never modifies preset records.
     *
     * @return Collection<int, SortSheetPreset>
     */
    public function userPresets(?User $user, string $surface = 'sort-sheet'): Collection
    {
        $query = SortSheetPreset::query()
            ->where(function ($q) use ($user) {
                if ($user) {
                    $q->where('user_id', $user->id)->orWhereNull('user_id');
                } else {
                    $q->whereNull('user_id');
                }
            });

        if ($surface === 'sort-sheet') {
            $query->where(function ($q) {
                $q->where('surface', 'sort-sheet')
                    ->orWhereNull('surface');
            });
        } elseif (in_array($surface, ['selection', 'segregation'], true)) {
            $hasSelectionSpecific = (clone $query)->whereIn('surface', ['segregation', 'selection'])->exists();
            if ($hasSelectionSpecific) {
                $query->whereIn('surface', ['segregation', 'selection']);
            } else {
                $query->where(function ($q) {
                    $q->whereIn('surface', ['segregation', 'selection', 'sort-sheet'])
                        ->orWhereNull('surface');
                });
            }
        } elseif ($surface === 'grid') {
            $hasGridSpecific = (clone $query)->where('surface', 'grid')->exists();
            if ($hasGridSpecific) {
                $query->where('surface', 'grid');
            } else {
                $query->where(function ($q) {
                    $q->whereIn('surface', ['grid', 'sort-sheet'])
                        ->orWhereNull('surface');
                });
            }
        } elseif (in_array($surface, ['portrait', 'shop-order'], true)) {
            $hasPortraitSpecific = (clone $query)->whereIn('surface', ['portrait', 'shop-order'])->exists();
            if ($hasPortraitSpecific) {
                $query->whereIn('surface', ['portrait', 'shop-order']);
            } else {
                $query->where(function ($q) {
                    $q->whereIn('surface', ['portrait', 'shop-order', 'sort-sheet'])
                        ->orWhereNull('surface');
                });
            }
        }

        return $query->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Load existing user preset batches scoped to authorized user.
     * READ-ONLY: Never modifies preset records.
     *
     * @return Collection<int, SortSheetPresetBatch>
     */
    public function userPresetBatches(?User $user): Collection
    {
        return SortSheetPresetBatch::query()
            ->where(function ($query) use ($user) {
                if ($user) {
                    $query->where('user_id', $user->id)->orWhereNull('user_id');
                } else {
                    $query->whereNull('user_id');
                }
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Build the filter options for the Sort Sheet V1 page.
     *
     * @return array{
     *     shops: EloquentCollection<int, Shop>,
     *     categories: EloquentCollection<int, Category>,
     *     products: EloquentCollection<int, Product>,
     *     warehouses: EloquentCollection<int, Warehouse>,
     *     priceGroups: EloquentCollection<int, ShopPriceGroup>
     * }
     */
    public function filterOptions(?int $warehouseId = null): array
    {
        $shops = Shop::where('status', 'active')
            ->orderByRaw("COALESCE(NULLIF(warehouse_tag, ''), 'ZZZ') ASC")
            ->orderBy('name')
            ->get();

        $priceGroups = ShopPriceGroup::orderBy('name')->get();

        $warehouses = Warehouse::active()
            ->with('categories:id')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $categoryQuery = Category::where('is_active', true)->with('warehouses:id');
        if ($warehouseId !== null) {
            $categoryQuery->whereHas('products', fn ($query) => $query
                ->where('is_active', true)
                ->where('default_warehouse_id', $warehouseId));
        }
        $categories = $categoryQuery->orderBy('name')->get();

        $productQuery = Product::active()
            ->with(['category:id,name'])
            ->ordered();
        if ($warehouseId !== null) {
            $productQuery->where('default_warehouse_id', $warehouseId);
        }
        $products = $productQuery->get(['id', 'name', 'sku', 'category_id', 'default_warehouse_id', 'unit']);

        return [
            'shops' => $shops,
            'categories' => $categories,
            'products' => $products,
            'warehouses' => $warehouses,
            'priceGroups' => $priceGroups,
        ];
    }

    /**
     * Sanitize selected grades against allowed grades ['A', 'B', 'C'].
     * Defaults to ['A'] if not provided.
     *
     * @param  array<int, string>|null  $grades
     * @return array<int, string>
     */
    public function sanitizeGrades(?array $grades, bool $hasGradesFilter = false): array
    {
        if ($grades !== null) {
            $sanitized = array_values(array_intersect(
                array_map('strtoupper', array_map('trim', $grades)),
                self::ALLOWED_GRADES
            ));

            return $sanitized;
        }

        if ($hasGradesFilter) {
            return [];
        }

        return ['A'];
    }

    /**
     * Build the Sort Sheet matrix result.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     matrix: array<string, array<int, float>>,
     *     productMeta: array<string, array<string, mixed>>,
     *     filteredShops: Collection<int, Shop>,
     *     candidateShops: Collection<int, Shop>,
     *     skippedEmptyShops: Collection<int, Shop>,
     *     selectedGrades: array<int, string>,
     *     gradeHeading: string,
     *     date: string,
     *     selectedWarehouse: Warehouse|null,
     *     filters: array<string, mixed>,
     *     grandTotal: float,
     *     shopTotals: array<int, float>
     * }
     */
    public function buildMatrix(array $filters): array
    {
        $date = (string) ($filters['date'] ?? $this->defaultOperationalDate());

        $hasGradesFilter = array_key_exists('grades_filter', $filters);
        $selectedGrades = $this->sanitizeGrades($filters['grades'] ?? null, $hasGradesFilter);

        $categoryIds = collect((array) ($filters['category_ids'] ?? []))
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $productIds = collect((array) ($filters['product_ids'] ?? []))
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $shopIds = collect((array) ($filters['shop_ids'] ?? []))
            ->when(! empty($filters['shop_id']), fn ($col) => $col->push($filters['shop_id']))
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $warehouseId = ! empty($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;
        $priceGroupId = ! empty($filters['price_group_id']) ? (int) $filters['price_group_id'] : null;

        $codeSort = true;
        if (isset($filters['code_sort']) && $filters['code_sort'] !== '') {
            $codeSort = (bool) filter_var($filters['code_sort'], FILTER_VALIDATE_BOOLEAN);
        } elseif (isset($filters['sku_sort']) && $filters['sku_sort'] !== '') {
            $codeSort = (bool) filter_var($filters['sku_sort'], FILTER_VALIDATE_BOOLEAN);
        }

        $showCode = true;
        if (isset($filters['show_code']) && $filters['show_code'] !== '') {
            $showCode = (bool) filter_var($filters['show_code'], FILTER_VALIDATE_BOOLEAN);
        }

        $showCategoryTitles = true;
        if (isset($filters['show_category_titles']) && $filters['show_category_titles'] !== '') {
            $showCategoryTitles = (bool) filter_var($filters['show_category_titles'], FILTER_VALIDATE_BOOLEAN);
        } elseif (isset($filters['show_category_headers']) && $filters['show_category_headers'] !== '') {
            $showCategoryTitles = (bool) filter_var($filters['show_category_headers'], FILTER_VALIDATE_BOOLEAN);
        }

        $skipEmptyShops = true;
        if (isset($filters['skip_empty_shops']) && $filters['skip_empty_shops'] !== '') {
            $skipEmptyShops = (bool) filter_var($filters['skip_empty_shops'], FILTER_VALIDATE_BOOLEAN);
        }

        // 1. Resolve candidate shops
        $shopQuery = Shop::where('status', 'active');
        if (! empty($shopIds)) {
            $shopQuery->whereIn('id', $shopIds);
        }
        if ($priceGroupId !== null) {
            $shopQuery->where('shop_price_group_id', $priceGroupId);
        }

        $candidateShops = $shopQuery
            ->orderByRaw("COALESCE(NULLIF(warehouse_tag, ''), 'ZZZ') ASC")
            ->orderBy('name')
            ->get();

        // 2. Query approved shop orders
        $orders = ShopOrder::whereDate('business_date', $date)
            ->whereIn('state', ['approved', 'update_requested'])
            ->whereIn('shop_id', $candidateShops->pluck('id'))
            ->with(['items.product.category'])
            ->get();

        // 3. Populate matrix & metadata
        $matrix = [];
        $productMeta = [];
        $shopTotals = [];

        foreach ($candidateShops as $shop) {
            $shopTotals[$shop->id] = 0.0;
        }

        if (! empty($selectedGrades)) {
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    $product = $item->product;
                    if (! $product) {
                        continue;
                    }

                    $approvedQty = (float) ($item->approved_qty ?? $item->requested_qty);
                    if ($approvedQty <= 0) {
                        continue;
                    }

                    // Strict grade resolution
                    $resolvedGradeEnum = ProductGrade::tryResolve($item->product_grade) ?? ProductGrade::GradeA;
                    $itemGrade = $resolvedGradeEnum->value;

                    if (! in_array($itemGrade, $selectedGrades, true)) {
                        continue;
                    }

                    if (! empty($categoryIds) && ! in_array((int) $product->category_id, $categoryIds, true)) {
                        continue;
                    }

                    if ($warehouseId !== null && (int) $product->default_warehouse_id !== $warehouseId) {
                        continue;
                    }

                    if (! empty($productIds) && ! in_array((int) $product->id, $productIds, true)) {
                        continue;
                    }

                    // Row key is scoped to product AND grade to guarantee separation across grades
                    $rowKey = "{$product->id}:{$itemGrade}";
                    $shopId = (int) $order->shop_id;

                    if (! isset($matrix[$rowKey])) {
                        $matrix[$rowKey] = [];
                        $productMeta[$rowKey] = [
                            'product_id' => $product->id,
                            'name' => $product->name,
                            'sku' => (string) ($product->sku ?? ''),
                            'unit' => (string) ($product->unit ?? 'kg'),
                            'category_id' => (int) ($product->category_id ?? 0),
                            'category_name' => $product->category?->name ?? 'Uncategorized',
                            'grade' => $itemGrade,
                            'grade_label' => "Grade {$itemGrade}",
                            'display_name' => $product->name,
                        ];
                    }

                    $matrix[$rowKey][$shopId] = ($matrix[$rowKey][$shopId] ?? 0.0) + $approvedQty;
                    $shopTotals[$shopId] = ($shopTotals[$shopId] ?? 0.0) + $approvedQty;
                }
            }
        }

        // 4. Sort matrix
        $this->sortMatrix($matrix, $productMeta, $codeSort);

        // 5. Empty shop pruning
        $filteredShops = new Collection;
        $skippedEmptyShops = new Collection;

        foreach ($candidateShops as $shop) {
            $total = $shopTotals[$shop->id] ?? 0.0;
            if ($skipEmptyShops && $total <= 0.0) {
                $skippedEmptyShops->push($shop);
            } else {
                $filteredShops->push($shop);
            }
        }

        // 6. Format Grade Heading
        $gradeHeading = $this->formatGradeHeading($selectedGrades);

        $selectedWarehouse = $warehouseId ? Warehouse::find($warehouseId) : null;
        $grandTotal = (float) array_sum($shopTotals);

        return [
            'matrix' => $matrix,
            'productMeta' => $productMeta,
            'filteredShops' => $filteredShops,
            'candidateShops' => $candidateShops,
            'skippedEmptyShops' => $skippedEmptyShops,
            'selectedGrades' => $selectedGrades,
            'gradeHeading' => $gradeHeading,
            'date' => $date,
            'selectedWarehouse' => $selectedWarehouse,
            'filters' => [
                'date' => $date,
                'grades' => $selectedGrades,
                'category_ids' => $categoryIds,
                'product_ids' => $productIds,
                'shop_ids' => $shopIds,
                'warehouse_id' => $warehouseId,
                'price_group_id' => $priceGroupId,
                'code_sort' => $codeSort,
                'sku_sort' => $codeSort,
                'show_code' => $showCode,
                'show_category_titles' => $showCategoryTitles,
                'show_category_headers' => $showCategoryTitles,
                'skip_empty_shops' => $skipEmptyShops,
                'preset_id' => $filters['preset_id'] ?? null,
                'selected_preset' => $filters['selected_preset'] ?? ($filters['preset_id'] ?? null),
            ],
            'grandTotal' => $grandTotal,
            'shopTotals' => $shopTotals,
        ];
    }

    /**
     * Format grade heading string according to requirements:
     * - Single grade: "GRADE A", "GRADE B", or "GRADE C"
     * - Multiple grades: "GRADES A, B", etc.
     * - None: "NO GRADES SELECTED"
     *
     * @param  array<int, string>  $grades
     */
    public function formatGradeHeading(array $grades): string
    {
        if (empty($grades)) {
            return 'NO GRADES SELECTED';
        }

        if (count($grades) === 1) {
            return 'GRADE '.$grades[0];
        }

        return 'GRADES '.implode(', ', $grades);
    }

    /**
     * Sort the matrix by SKU code (natural alphanumeric/numeric order), name, and grade.
     *
     * @param  array<string, array<int, float>>  $matrix
     * @param  array<string, array<string, mixed>>  $productMeta
     */
    private function sortMatrix(array &$matrix, array $productMeta, bool $codeSort): void
    {
        uksort($matrix, function (string $a, string $b) use ($productMeta, $codeSort): int {
            if ($codeSort) {
                $skuA = (string) ($productMeta[$a]['sku'] ?? '');
                $skuB = (string) ($productMeta[$b]['sku'] ?? '');
                $skuCmp = self::compareSkus($skuA, $skuB);
                if ($skuCmp !== 0) {
                    return $skuCmp;
                }
            }

            $nameA = (string) ($productMeta[$a]['name'] ?? '');
            $nameB = (string) ($productMeta[$b]['name'] ?? '');
            $nameCmp = strcmp($nameA, $nameB);
            if ($nameCmp !== 0) {
                return $nameCmp;
            }

            if (! $codeSort) {
                $skuA = (string) ($productMeta[$a]['sku'] ?? '');
                $skuB = (string) ($productMeta[$b]['sku'] ?? '');
                $skuCmp = self::compareSkus($skuA, $skuB);
                if ($skuCmp !== 0) {
                    return $skuCmp;
                }
            }

            return strcmp((string) ($productMeta[$a]['grade'] ?? 'A'), (string) ($productMeta[$b]['grade'] ?? 'A'));
        });
    }

    /**
     * Natural comparison for SKU codes supporting numeric and alphanumeric formats.
     */
    public static function compareSkus(string $skuA, string $skuB): int
    {
        $cleanA = trim($skuA);
        $cleanB = trim($skuB);

        if ($cleanA === $cleanB) {
            return 0;
        }

        if ($cleanA === '') {
            return 1;
        }

        if ($cleanB === '') {
            return -1;
        }

        $isNumA = preg_match('/^\d+$/', $cleanA) === 1;
        $isNumB = preg_match('/^\d+$/', $cleanB) === 1;

        if ($isNumA && $isNumB) {
            return (int) $cleanA <=> (int) $cleanB;
        }

        if ($isNumA !== $isNumB) {
            return $isNumA ? -1 : 1;
        }

        return strnatcasecmp($cleanA, $cleanB);
    }
}
