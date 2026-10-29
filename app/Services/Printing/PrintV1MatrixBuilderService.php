<?php

declare(strict_types=1);

namespace App\Services\Printing;

use App\Enums\Inventory\ProductGrade;
use App\Models\Category;
use App\Models\Printing\PrintV1Setting;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Support\Collection;

class PrintV1MatrixBuilderService
{
    /**
     * Allowed printable grades for V1. Grade D is strictly disallowed.
     *
     * @var array<int, string>
     */
    public const ALLOWED_GRADES = ['A', 'B', 'C', 'U', 'DMG'];

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
     * Build the print matrix result for a specific section and date.
     *
     * @param  array<string, mixed>|null  $overrideSettings
     */
    public function build(
        string $section,
        ?string $date = null,
        ?array $overrideSettings = null,
        ?int $userId = null
    ): PrintV1MatrixResult {
        $effectiveDate = $date ?: $this->defaultOperationalDate();

        // 1. Resolve section settings (saved settings + overrides)
        $savedSettings = PrintV1Setting::getForSection($section, $userId);
        $settings = $overrideSettings !== null
            ? array_merge($savedSettings, $overrideSettings)
            : $savedSettings;

        // 2. Resolve global shop configuration
        $shopSettings = PrintV1Setting::getForSection('shops', $userId);
        $includedInactiveShopIds = array_map('intval', (array) ($shopSettings['included_inactive_shop_ids'] ?? []));
        $excludedActiveShopIds = array_map('intval', (array) ($shopSettings['excluded_active_shop_ids'] ?? []));

        // 3. Resolve candidate shops
        $candidateShops = $this->resolveCandidateShops(
            $settings,
            $includedInactiveShopIds,
            $excludedActiveShopIds
        );

        // 4. Resolve selected grades (sanitized against allowed list, excluding Grade D)
        $selectedGrades = $this->sanitizeGrades((array) ($settings['grades'] ?? ['A', 'B', 'C', 'U']));

        // 5. Query approved shop orders
        $warehouseId = ! empty($settings['warehouse_id']) ? (int) $settings['warehouse_id'] : null;
        $categoryIds = array_map('intval', (array) ($settings['category_ids'] ?? []));
        $productIds = array_map('intval', (array) ($settings['product_ids'] ?? []));

        $orders = ShopOrder::whereDate('business_date', $effectiveDate)
            ->whereIn('state', ['approved', 'update_requested'])
            ->whereIn('shop_id', $candidateShops->pluck('id'))
            ->with(['items.product.category'])
            ->get();

        // 6. Build matrix & product metadata
        $matrix = [];
        $productMeta = [];
        $shopTotalQuantities = [];

        foreach ($candidateShops as $shop) {
            $shopTotalQuantities[$shop->id] = 0.0;
        }

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

                // Resolve grade via ProductGrade enum
                $resolvedGradeEnum = ProductGrade::tryResolve($item->product_grade) ?? ProductGrade::GradeA;
                $itemGrade = $resolvedGradeEnum->value;

                // Grade check (must match selected grades and not be Grade D)
                if (! in_array($itemGrade, $selectedGrades, true)) {
                    continue;
                }

                // Filter by category
                if (! empty($categoryIds) && ! in_array((int) $product->category_id, $categoryIds, true)) {
                    continue;
                }

                // Filter by warehouse
                if ($warehouseId !== null && (int) $product->default_warehouse_id !== $warehouseId) {
                    continue;
                }

                // Filter by product
                if (! empty($productIds) && ! in_array((int) $product->id, $productIds, true)) {
                    continue;
                }

                // Composite row key prevents accidental collisions across grades
                $rowKey = "{$product->id}:{$itemGrade}";
                $shopId = (int) $order->shop_id;

                if (! isset($matrix[$rowKey])) {
                    $matrix[$rowKey] = [];
                    $isSingleGrade = count($selectedGrades) === 1;
                    $displayName = $isSingleGrade
                        ? trim($product->name)
                        : ProductGrade::formatNameWithGrade($product->name, $resolvedGradeEnum);

                    $productMeta[$rowKey] = [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'sku' => (string) ($product->sku ?? ''),
                        'unit' => (string) ($product->unit ?? 'kg'),
                        'category_id' => (int) ($product->category_id ?? 0),
                        'category_name' => $product->category?->name ?? '—',
                        'grade' => $itemGrade,
                        'grade_label' => $resolvedGradeEnum->label(),
                        'display_name' => $displayName,
                    ];
                }

                $matrix[$rowKey][$shopId] = ($matrix[$rowKey][$shopId] ?? 0.0) + $approvedQty;
                $shopTotalQuantities[$shopId] = ($shopTotalQuantities[$shopId] ?? 0.0) + $approvedQty;
            }
        }

        // 7. Sort matrix
        $codeSort = (bool) ($settings['code_sort'] ?? true);
        $this->sortMatrix($matrix, $productMeta, $codeSort);

        // 8. Empty Shop Pruning
        $skipEmpty = isset($settings['skip_empty_shops'])
            ? (bool) $settings['skip_empty_shops']
            : (bool) ($shopSettings['default_skip_empty_shops'] ?? true);
        $filteredShops = new Collection;
        $skippedEmptyShops = new Collection;

        foreach ($candidateShops as $shop) {
            $totalQty = $shopTotalQuantities[$shop->id] ?? 0.0;
            if ($skipEmpty && $totalQty <= 0.0) {
                $skippedEmptyShops->push($shop);
            } else {
                $filteredShops->push($shop);
            }
        }

        $selectedWarehouse = $warehouseId ? Warehouse::find($warehouseId) : null;

        return new PrintV1MatrixResult(
            filteredShops: $filteredShops,
            matrix: $matrix,
            productMeta: $productMeta,
            date: $effectiveDate,
            selectedWarehouse: $selectedWarehouse,
            selectedGrades: $selectedGrades,
            settings: $settings,
            skippedEmptyShops: $skippedEmptyShops,
            candidateShops: $candidateShops,
        );
    }

    /**
     * Resolve candidate shops using whitelist, blacklist, and section filters.
     *
     * @param  array<string, mixed>  $settings
     * @param  array<int, int>  $includedInactiveShopIds
     * @param  array<int, int>  $excludedActiveShopIds
     * @return Collection<int, Shop>
     */
    private function resolveCandidateShops(
        array $settings,
        array $includedInactiveShopIds,
        array $excludedActiveShopIds
    ): Collection {
        $query = Shop::query();

        // Include active shops, plus explicitly whitelisted inactive shops
        $query->where(function ($q) use ($includedInactiveShopIds) {
            $q->where('status', 'active');
            if (! empty($includedInactiveShopIds)) {
                $q->orWhereIn('id', $includedInactiveShopIds);
            }
        });

        // Exclude blacklisted active shops
        if (! empty($excludedActiveShopIds)) {
            $query->whereNotIn('id', $excludedActiveShopIds);
        }

        // Section-specific shop filter
        if (! empty($settings['shop_id'])) {
            $query->where('id', (int) $settings['shop_id']);
        }

        // Section-specific price group filter
        if (! empty($settings['price_group_id'])) {
            $query->where('shop_price_group_id', (int) $settings['price_group_id']);
        }

        return $query
            ->orderByRaw("COALESCE(NULLIF(warehouse_tag, ''), 'ZZZ') ASC")
            ->orderBy('name')
            ->get();
    }

    /**
     * Sanitize selected grades, ensuring Grade D is permanently disallowed.
     *
     * @param  array<mixed>  $grades
     * @return array<int, string>
     */
    private function sanitizeGrades(array $grades): array
    {
        $sanitized = [];
        foreach ($grades as $grade) {
            $upper = strtoupper(trim((string) $grade));
            if ($upper === 'D' || $upper === 'GRADE D') {
                continue; // Strictly disallow Grade D
            }

            if (in_array($upper, self::ALLOWED_GRADES, true)) {
                $sanitized[] = $upper;
            } elseif ($upper === 'MIXED') {
                $sanitized[] = 'U';
            }
        }

        $unique = array_values(array_unique($sanitized));

        return ! empty($unique) ? $unique : ['A'];
    }

    /**
     * Sort matrix rows by Category, SKU/Name, and Grade.
     *
     * @param  array<string, array<int, float>>  $matrix
     * @param  array<string, array<string, mixed>>  $productMeta
     */
    private function sortMatrix(array &$matrix, array $productMeta, bool $codeSort = true): void
    {
        uksort($matrix, function (string $a, string $b) use ($productMeta, $codeSort): int {
            $metaA = $productMeta[$a] ?? [];
            $metaB = $productMeta[$b] ?? [];

            // 1. Category name
            $catA = (string) ($metaA['category_name'] ?? '');
            $catB = (string) ($metaB['category_name'] ?? '');
            $catCmp = strcmp($catA, $catB);
            if ($catCmp !== 0) {
                return $catCmp;
            }

            // 2. SKU / Code Sort
            if ($codeSort) {
                $skuA = Product::sortableSku((string) ($metaA['sku'] ?? ''));
                $skuB = Product::sortableSku((string) ($metaB['sku'] ?? ''));
                $skuCmp = strcmp($skuA, $skuB);
                if ($skuCmp !== 0) {
                    return $skuCmp;
                }
            }

            // 3. Product Name
            $nameA = (string) ($metaA['name'] ?? '');
            $nameB = (string) ($metaB['name'] ?? '');
            $nameCmp = strcmp($nameA, $nameB);
            if ($nameCmp !== 0) {
                return $nameCmp;
            }

            // 4. Grade sort order (A, B, C, U, DMG)
            $gradeA = (string) ($metaA['grade'] ?? 'A');
            $gradeB = (string) ($metaB['grade'] ?? 'A');

            return strcmp($gradeA, $gradeB);
        });
    }
}
