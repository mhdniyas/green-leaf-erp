<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WastageEntry;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Authoritative, read-only reporting service for Grades Report (Reports V1).
 *
 * Integrates warehouse segregation, stock ledger, procurement lineage,
 * physical loadout vs commercial grading, damage tracking, realized
 * invoice selling prices, and negative-stock pending cost reconciliation.
 */
final class GradesReportService
{
    /**
     * Search products by name or SKU.
     *
     * @return Collection<int, Product>
     */
    public function searchProducts(?string $query = null, int $limit = 50): Collection
    {
        return Product::query()
            ->when(! empty($query), function ($builder) use ($query): void {
                $trimmed = trim($query);
                $builder->where(function ($sub) use ($trimmed): void {
                    $sub->where('name', 'like', '%'.$trimmed.'%')
                        ->orWhere('sku', 'like', '%'.$trimmed.'%');
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'public_uuid', 'name', 'unit', 'sku', 'category_id']);
    }

    /**
     * Get report data based on validated filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getReportData(array $filters): array
    {
        $productIdentifier = $filters['product'] ?? $filters['product_uuid'] ?? $filters['product_id'] ?? null;
        $searchProduct = trim((string) ($filters['search_product'] ?? ''));

        $product = null;
        $searchResults = collect();

        if ($productIdentifier) {
            $identifierStr = (string) $productIdentifier;
            if (Str::isUuid($identifierStr)) {
                $product = Product::query()->with('category')->where('public_uuid', $identifierStr)->first();
            } elseif (is_numeric($identifierStr)) {
                $product = Product::query()->with('category')->find((int) $identifierStr);
            } else {
                $product = Product::query()->with('category')
                    ->where('sku', $identifierStr)
                    ->orWhere('public_uuid', $identifierStr)
                    ->first();
            }
        } elseif ($searchProduct !== '') {
            $matches = $this->searchProducts($searchProduct, 15);
            if ($matches->count() === 1) {
                $product = $matches->first();
            } else {
                $searchResults = $matches;
            }
        }

        $options = $this->getOptions($product?->id, $filters);

        // Calculate summary cards and product/grade rows
        $reportContent = $this->compileReportContent($product, $filters);
        $dailySummary = $this->compileDailyGradeSummary($product, $filters);

        return array_merge([
            'product' => $product,
            'searchResults' => $searchResults,
            'options' => $options,
        ], $reportContent, $dailySummary);
    }

    /**
     * Filter options for dropdowns.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getOptions(?int $productId = null, array $filters = []): array
    {
        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $purchasers = User::query()
            ->where(function ($query): void {
                $query->whereHas('roles', fn ($roles) => $roles->where('name', 'purchaser'))
                    ->orWhereIn('id', DB::table('purchaser_carts')->select('user_id'));
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $shops = Shop::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $grades = [
            'all' => 'All Grades',
            'A' => 'Grade A — Premium',
            'B' => 'Grade B — Standard',
            'C' => 'Grade C — Economy',
            'U' => 'Mixed / Unsegregated',
        ];

        return [
            'warehouses' => $warehouses,
            'purchasers' => $purchasers,
            'shops' => $shops,
            'grades' => $grades,
        ];
    }

    /**
     * Compile summary KPIs and product rows.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function compileReportContent(?Product $selectedProduct, array $filters): array
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $warehouseId = ! empty($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;
        $gradeFilter = ! empty($filters['grade']) && $filters['grade'] !== 'all' ? strtoupper((string) $filters['grade']) : 'all';
        $purchaserId = ! empty($filters['purchaser_id']) ? (int) $filters['purchaser_id'] : null;
        $shopId = ! empty($filters['shop_id']) ? (int) $filters['shop_id'] : null;
        $search = trim((string) ($filters['search'] ?? $filters['search_product'] ?? ''));

        // 1. Identify products to include in the report
        $productsQuery = Product::query()->with('category');

        if ($selectedProduct !== null) {
            $productsQuery->where('id', $selectedProduct->id);
        } else {
            if ($search !== '') {
                $productsQuery->where(function ($sq) use ($search): void {
                    $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            }

            // Efficiently scope active products using indexed subqueries
            $activeBatchPids = StockBatch::query()->select('product_id')->distinct();
            if ($startDate) {
                $activeBatchPids->whereDate('received_at', '>=', $startDate);
            }
            if ($endDate) {
                $activeBatchPids->whereDate('received_at', '<=', $endDate);
            }
            if ($warehouseId) {
                $activeBatchPids->where('warehouse_id', $warehouseId);
            }

            $activeMovementPids = StockMovement::query()->select('product_id')->distinct();
            if ($startDate) {
                $activeMovementPids->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $activeMovementPids->whereDate('created_at', '<=', $endDate);
            }
            if ($warehouseId) {
                $activeMovementPids->where('warehouse_id', $warehouseId);
            }

            $productsQuery->where(function ($sub) use ($activeBatchPids, $activeMovementPids): void {
                $sub->whereIn('id', $activeBatchPids)
                    ->orWhereIn('id', $activeMovementPids);
            });
        }

        // True server-side 5-product pagination
        $products = $productsQuery->orderBy('name')->paginate(5)->withQueryString();

        // 2. Commercial grades only (A, B, C) and Mixed (U)
        $gradesList = match ($gradeFilter) {
            'A' => ['A'],
            'B' => ['B'],
            'C' => ['C'],
            'U' => ['U'],
            default => ['A', 'B', 'C', 'U'],
        };

        $productSummaries = [];
        $pageTotalPurchasedKg = 0.0;
        $pageTotalPurchasedCost = 0.0;
        $pageTotalSegregatedKg = 0.0;
        $pageTotalRegradedKg = 0.0;
        $pageTotalPhysicalLoadedKg = 0.0;
        $pageTotalDamagedKg = 0.0;
        $pageTotalDamagedCost = 0.0;
        $pageTotalAvailableKg = 0.0;
        $pageTotalAvailableValuation = 0.0;
        $pageTotalInvoicedSales = 0.0;
        $pageTotalInvoicedQty = 0.0;
        $pagePendingCount = 0;
        $pagePendingQty = 0.0;

        $pageProductIds = collect($products->items())->pluck('id')->all();
        $precomputedMixedStock = $this->calculateAvailableMixedStockForProducts($pageProductIds, $warehouseId);

        foreach ($products->items() as $prod) {
            $prodGradesData = [];
            $prodTotalPurchasedKg = 0.0;
            $prodTotalPurchasedCost = 0.0;
            $prodTotalSegregatedKg = 0.0;
            $prodTotalRegradedKg = 0.0;
            $prodTotalPhysicalLoadedKg = 0.0;
            $prodTotalCommercialLoadedKg = 0.0;
            $prodTotalDamagedKg = 0.0;
            $prodTotalDamagedCost = 0.0;
            $prodTotalAvailableKg = 0.0;
            $prodTotalAvailableValuation = 0.0;
            $prodTotalInvoicedSales = 0.0;
            $prodTotalInvoicedQty = 0.0;
            $prodHasPendingCost = false;
            $prodPendingCount = 0;

            foreach ($gradesList as $g) {
                $metrics = $this->calculateGradeMetrics(
                    $prod->id,
                    $g,
                    $startDate,
                    $endDate,
                    $warehouseId,
                    $purchaserId,
                    $shopId,
                    $precomputedMixedStock
                );

                $prodGradesData[$g] = $metrics;

                $prodTotalPurchasedKg += $metrics['purchased_kg'];
                $prodTotalPurchasedCost += $metrics['purchased_total_cost'];
                $prodTotalSegregatedKg += $metrics['segregated_kg'];
                $prodTotalRegradedKg += $metrics['regraded_kg'];
                $prodTotalPhysicalLoadedKg += $metrics['physical_loaded_kg'];
                $prodTotalCommercialLoadedKg += $metrics['commercial_loaded_kg'];
                $prodTotalDamagedKg += $metrics['damaged_kg'];
                $prodTotalDamagedCost += $metrics['damaged_total_cost'];
                $prodTotalAvailableKg += $metrics['available_kg'];
                $prodTotalAvailableValuation += $metrics['available_valuation'];
                $prodTotalInvoicedSales += $metrics['invoiced_sales_amount'];
                $prodTotalInvoicedQty += $metrics['invoiced_delivered_kg'];

                if ($metrics['has_pending_cost_reconciliation']) {
                    $prodHasPendingCost = true;
                    $prodPendingCount += $metrics['pending_reconciliation_count'];
                    $pagePendingQty += $metrics['pending_reconciliation_qty'];
                }
            }

            $prodWeightedPurchaseCost = $prodTotalPurchasedKg > 0.001
                ? ($prodTotalPurchasedCost / $prodTotalPurchasedKg)
                : 0.0;

            $prodWeightedSellingRate = $prodTotalInvoicedQty > 0.001
                ? ($prodTotalInvoicedSales / $prodTotalInvoicedQty)
                : 0.0;

            $prodMarginPercent = null;
            if (! $prodHasPendingCost && $prodWeightedSellingRate > 0.001 && $prodWeightedPurchaseCost > 0.0) {
                $prodMarginPercent = round((($prodWeightedSellingRate - $prodWeightedPurchaseCost) / $prodWeightedSellingRate) * 100, 1);
            }

            $productSummaries[$prod->id] = [
                'product' => $prod,
                'grades' => $prodGradesData,
                'total_purchased_kg' => round($prodTotalPurchasedKg, 3),
                'total_purchased_cost' => round($prodTotalPurchasedCost, 2),
                'weighted_purchase_cost' => round($prodWeightedPurchaseCost, 2),
                'total_segregated_kg' => round($prodTotalSegregatedKg, 3),
                'total_regraded_kg' => round($prodTotalRegradedKg, 3),
                'total_physical_loaded_kg' => round($prodTotalPhysicalLoadedKg, 3),
                'total_commercial_loaded_kg' => round($prodTotalCommercialLoadedKg, 3),
                'total_damaged_kg' => round($prodTotalDamagedKg, 3),
                'total_damaged_cost' => round($prodTotalDamagedCost, 2),
                'total_available_kg' => round($prodTotalAvailableKg, 3),
                'total_available_valuation' => round($prodTotalAvailableValuation, 2),
                'total_invoiced_sales' => round($prodTotalInvoicedSales, 2),
                'total_invoiced_qty' => round($prodTotalInvoicedQty, 3),
                'weighted_selling_rate' => round($prodWeightedSellingRate, 2),
                'margin_percent' => $prodMarginPercent,
                'has_pending_cost' => $prodHasPendingCost,
                'pending_count' => $prodPendingCount,
            ];

            $pageTotalPurchasedKg += $prodTotalPurchasedKg;
            $pageTotalPurchasedCost += $prodTotalPurchasedCost;
            $pageTotalSegregatedKg += $prodTotalSegregatedKg;
            $pageTotalRegradedKg += $prodTotalRegradedKg;
            $pageTotalPhysicalLoadedKg += $prodTotalPhysicalLoadedKg;
            $pageTotalDamagedKg += $prodTotalDamagedKg;
            $pageTotalDamagedCost += $prodTotalDamagedCost;
            $pageTotalAvailableKg += $prodTotalAvailableKg;
            $pageTotalAvailableValuation += $prodTotalAvailableValuation;
            $pageTotalInvoicedSales += $prodTotalInvoicedSales;
            $pageTotalInvoicedQty += $prodTotalInvoicedQty;
            $pagePendingCount += $prodPendingCount;
        }

        // Summary KPI calculation:
        // If single product filtered, summary is that product.
        // Otherwise, overall summary is computed across all matching products using set-based SQL aggregations.
        if ($selectedProduct !== null) {
            $overallWeightedPurchaseCost = $pageTotalPurchasedKg > 0.001
                ? ($pageTotalPurchasedCost / $pageTotalPurchasedKg)
                : 0.0;
            $overallWeightedSellingRate = $pageTotalInvoicedQty > 0.001
                ? ($pageTotalInvoicedSales / $pageTotalInvoicedQty)
                : 0.0;
            $overallMargin = null;
            if ($pagePendingCount === 0 && $overallWeightedSellingRate > 0.001 && $overallWeightedPurchaseCost > 0.0) {
                $overallMargin = round((($overallWeightedSellingRate - $overallWeightedPurchaseCost) / $overallWeightedSellingRate) * 100, 1);
            }

            $summary = (object) [
                'total_purchased_kg' => round($pageTotalPurchasedKg, 3),
                'total_purchased_cost' => round($pageTotalPurchasedCost, 2),
                'weighted_purchase_cost' => round($overallWeightedPurchaseCost, 2),
                'total_segregated_kg' => round($pageTotalSegregatedKg, 3),
                'total_regraded_kg' => round($pageTotalRegradedKg, 3),
                'total_physical_loaded_kg' => round($pageTotalPhysicalLoadedKg, 3),
                'total_damaged_kg' => round($pageTotalDamagedKg, 3),
                'total_damaged_cost' => round($pageTotalDamagedCost, 2),
                'total_available_kg' => round($pageTotalAvailableKg, 3),
                'total_available_valuation' => round($pageTotalAvailableValuation, 2),
                'total_invoiced_sales' => round($pageTotalInvoicedSales, 2),
                'total_invoiced_qty' => round($pageTotalInvoicedQty, 3),
                'weighted_selling_rate' => round($overallWeightedSellingRate, 2),
                'margin_percent' => $overallMargin,
                'pending_reconciliation_count' => $pagePendingCount,
                'pending_reconciliation_qty' => round($pagePendingQty, 3),
                'has_pending_cost_reconciliation' => $pagePendingCount > 0,
            ];
        } else {
            $summary = $this->calculateOverallSummary($filters, $search);
        }

        return [
            'products' => $products,
            'productSummaries' => $productSummaries,
            'summary' => $summary,
            'gradesList' => $gradesList,
        ];
    }

    /**
     * Set-based SQL aggregation across all matching records for the report period.
     */
    private function calculateOverallSummary(array $filters, string $search = ''): object
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $warehouseId = ! empty($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;
        $gradeFilter = ! empty($filters['grade']) && $filters['grade'] !== 'all' ? strtoupper((string) $filters['grade']) : 'all';
        $purchaserId = ! empty($filters['purchaser_id']) ? (int) $filters['purchaser_id'] : null;
        $shopId = ! empty($filters['shop_id']) ? (int) $filters['shop_id'] : null;

        $matchingProductIds = null;
        if ($search !== '') {
            $matchingProductIds = Product::query()
                ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))
                ->pluck('id');
        }

        // 1. Root purchases
        $purchasesQuery = StockBatch::query()
            ->whereNull('parent_batch_id')
            ->where('warehouse_receive_pending', false)
            ->where('reference', 'not like', 'NEG-%');
        if ($matchingProductIds !== null) {
            $purchasesQuery->whereIn('product_id', $matchingProductIds);
        }
        if ($startDate) {
            $purchasesQuery->whereDate('received_at', '>=', $startDate);
        }
        if ($endDate) {
            $purchasesQuery->whereDate('received_at', '<=', $endDate);
        }
        if ($warehouseId) {
            $purchasesQuery->where('warehouse_id', $warehouseId);
        }
        if ($purchaserId) {
            $purchasesQuery->where(function ($sub) use ($purchaserId): void {
                $sub->where('created_by', $purchaserId)
                    ->orWhereHas('goodsReceived.purchaserCart', fn ($pc) => $pc->where('user_id', $purchaserId));
            });
        }
        if ($gradeFilter !== 'all') {
            if ($gradeFilter === 'U') {
                $purchasesQuery->where(fn ($sub) => $sub->whereIn('purchase_grade', ['U', 'MIXED', 'UNSORTED'])->orWhereNull('purchase_grade'));
            } else {
                $purchasesQuery->where('purchase_grade', $gradeFilter);
            }
        }
        $pStats = $purchasesQuery->selectRaw('COALESCE(SUM(total_kg), 0) as total_kg, COALESCE(SUM(total_kg * cost_per_kg), 0) as total_cost')->first();
        $totalOriginalPurchasedKg = (float) ($pStats->total_kg ?? 0);
        $totalOriginalPurchasedCost = (float) ($pStats->total_cost ?? 0);

        // 2. Child batches (segregated)
        $childQuery = StockBatch::query()->whereNotNull('parent_batch_id');
        if ($matchingProductIds !== null) {
            $childQuery->whereIn('product_id', $matchingProductIds);
        }
        if ($startDate) {
            $childQuery->whereDate('received_at', '>=', $startDate);
        }
        if ($endDate) {
            $childQuery->whereDate('received_at', '<=', $endDate);
        }
        if ($warehouseId) {
            $childQuery->where('warehouse_id', $warehouseId);
        }
        if ($gradeFilter !== 'all' && $gradeFilter !== 'U') {
            $childQuery->where('purchase_grade', $gradeFilter);
        }
        $totalSegregatedKg = (float) ($childQuery->sum('total_kg') ?? 0);

        // 3. Physical loadout
        $loadoutQuery = StockMovement::query()
            ->whereIn('type', [StockMovementType::Out->value, StockMovementType::Sale->value])
            ->where(fn ($sub) => $sub->whereNotNull('shop_order_item_id')->orWhereNotNull('warehouse_sale_item_id')->orWhere('notes', 'like', '%loadout%'));
        if ($matchingProductIds !== null) {
            $loadoutQuery->whereIn('product_id', $matchingProductIds);
        }
        if ($startDate) {
            $loadoutQuery->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $loadoutQuery->whereDate('created_at', '<=', $endDate);
        }
        if ($warehouseId) {
            $loadoutQuery->where('warehouse_id', $warehouseId);
        }
        if ($shopId) {
            $loadoutQuery->whereHas('shopOrderItem.order', fn ($so) => $so->where('shop_id', $shopId));
        }
        if ($gradeFilter !== 'all') {
            $loadoutQuery->where('grade', $gradeFilter);
        }
        $totalPhysicalLoadedKg = (float) ($loadoutQuery->sum('quantity') ?? 0);

        // 4. Wastage / Damage
        $damageQuery = WastageEntry::query()->whereNull('deleted_at');
        if ($matchingProductIds !== null) {
            $damageQuery->whereIn('product_id', $matchingProductIds);
        }
        if ($startDate) {
            $damageQuery->whereDate('wastage_date', '>=', $startDate);
        }
        if ($endDate) {
            $damageQuery->whereDate('wastage_date', '<=', $endDate);
        }
        if ($gradeFilter !== 'all') {
            $damageQuery->where('grade', $gradeFilter);
        }
        $dStats = $damageQuery->selectRaw('COALESCE(SUM(quantity), 0) as total_kg, COALESCE(SUM(quantity * cost_per_kg), 0) as total_cost')->first();
        $totalDamagedKg = (float) ($dStats->total_kg ?? 0);
        $totalDamagedCost = (float) ($dStats->total_cost ?? 0);

        // 5. Available stock
        $availQuery = StockBatch::query()->where('status', '!=', BatchStatus::Closed)->where('total_kg', '>', 0);
        if ($matchingProductIds !== null) {
            $availQuery->whereIn('product_id', $matchingProductIds);
        }
        if ($warehouseId) {
            $availQuery->where('warehouse_id', $warehouseId);
        }
        if ($gradeFilter !== 'all') {
            if ($gradeFilter === 'U') {
                $availQuery->where(fn ($sub) => $sub->whereIn('purchase_grade', ['U', 'MIXED', 'UNSORTED'])->orWhereNull('purchase_grade'));
            } else {
                $availQuery->where('purchase_grade', $gradeFilter);
            }
        }
        $aStats = $availQuery->selectRaw('COALESCE(SUM(total_kg), 0) as total_kg, COALESCE(SUM(total_kg * cost_per_kg), 0) as total_cost')->first();
        $totalAvailableKg = (float) ($aStats->total_kg ?? 0);
        $totalAvailableValuation = (float) ($aStats->total_cost ?? 0);

        // 6. Invoiced sales
        $salesQuery = ShopInvoiceItem::query()
            ->whereHas('invoice', function ($iq) use ($startDate, $endDate, $shopId): void {
                $iq->where('status', '!=', 'cancelled');
                if ($startDate) {
                    $iq->whereDate('business_date', '>=', $startDate);
                }
                if ($endDate) {
                    $iq->whereDate('business_date', '<=', $endDate);
                }
                if ($shopId) {
                    $iq->where('shop_id', $shopId);
                }
            });
        if ($matchingProductIds !== null) {
            $salesQuery->whereIn('product_id', $matchingProductIds);
        }
        if ($gradeFilter !== 'all') {
            $salesQuery->where('product_grade', $gradeFilter);
        }
        $sStats = $salesQuery->selectRaw('COALESCE(SUM(delivered_qty), 0) as total_qty, COALESCE(SUM(final_line_total), 0) as total_amount')->first();
        $totalInvoicedDeliveredKg = (float) ($sStats->total_qty ?? 0);
        $totalInvoicedSalesAmount = (float) ($sStats->total_amount ?? 0);

        // 7. Pending negative stock
        $pendingQuery = StockMovement::query()
            ->where(fn ($sub) => $sub->where('cost_per_unit', '<=', 0.0001)->orWhereHas('batch', fn ($bq) => $bq->where('reference', 'like', 'NEG-%')));
        if ($matchingProductIds !== null) {
            $pendingQuery->whereIn('product_id', $matchingProductIds);
        }
        if ($startDate) {
            $pendingQuery->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $pendingQuery->whereDate('created_at', '<=', $endDate);
        }
        if ($warehouseId) {
            $pendingQuery->where('warehouse_id', $warehouseId);
        }
        $penStats = $pendingQuery->selectRaw('COUNT(*) as total_count, COALESCE(SUM(quantity), 0) as total_qty')->first();
        $totalPendingCostReconciliationCount = (int) ($penStats->total_count ?? 0);
        $totalPendingCostReconciliationQty = (float) ($penStats->total_qty ?? 0);

        $overallWeightedPurchaseCost = $totalOriginalPurchasedKg > 0.001
            ? ($totalOriginalPurchasedCost / $totalOriginalPurchasedKg)
            : 0.0;

        $overallWeightedSellingRate = $totalInvoicedDeliveredKg > 0.001
            ? ($totalInvoicedSalesAmount / $totalInvoicedDeliveredKg)
            : 0.0;

        $overallMarginPercent = null;
        if ($totalPendingCostReconciliationCount === 0 && $overallWeightedSellingRate > 0.001 && $overallWeightedPurchaseCost > 0.0) {
            $overallMarginPercent = round((($overallWeightedSellingRate - $overallWeightedPurchaseCost) / $overallWeightedSellingRate) * 100, 1);
        }

        return (object) [
            'total_purchased_kg' => round($totalOriginalPurchasedKg, 3),
            'total_purchased_cost' => round($totalOriginalPurchasedCost, 2),
            'weighted_purchase_cost' => round($overallWeightedPurchaseCost, 2),
            'total_segregated_kg' => round($totalSegregatedKg, 3),
            'total_regraded_kg' => 0.0,
            'total_physical_loaded_kg' => round($totalPhysicalLoadedKg, 3),
            'total_damaged_kg' => round($totalDamagedKg, 3),
            'total_damaged_cost' => round($totalDamagedCost, 2),
            'total_available_kg' => round($totalAvailableKg, 3),
            'total_available_valuation' => round($totalAvailableValuation, 2),
            'total_invoiced_sales' => round($totalInvoicedSalesAmount, 2),
            'total_invoiced_qty' => round($totalInvoicedDeliveredKg, 3),
            'weighted_selling_rate' => round($overallWeightedSellingRate, 2),
            'margin_percent' => $overallMarginPercent,
            'pending_reconciliation_count' => $totalPendingCostReconciliationCount,
            'pending_reconciliation_qty' => round($totalPendingCostReconciliationQty, 3),
            'has_pending_cost_reconciliation' => $totalPendingCostReconciliationCount > 0,
        ];
    }

    /**
     * Compute available mixed stock for given products in a set-based manner.
     *
     * @param  array<int>  $productIds
     * @return array<int, float>
     */
    public function calculateAvailableMixedStockForProducts(array $productIds, ?int $warehouseId = null): array
    {
        if (empty($productIds)) {
            return [];
        }

        $pendingBatchesQuery = StockBatch::query()
            ->whereIn('product_id', $productIds)
            ->where('status', BatchStatus::Pending)
            ->where('warehouse_receive_pending', false)
            ->where('reference', 'not like', 'NEG-%');

        if ($warehouseId) {
            $pendingBatchesQuery->where('warehouse_id', $warehouseId);
        }

        $pendingBatches = $pendingBatchesQuery->get(['id', 'product_id', 'total_kg', 'received_at']);

        if ($pendingBatches->isEmpty()) {
            $emptyResult = [];
            foreach ($productIds as $pId) {
                $emptyResult[$pId] = 0.0;
            }

            return $emptyResult;
        }

        // Collect all distinct (product_id, date) pairs needed
        $productDates = [];
        foreach ($pendingBatches as $pb) {
            $date = Carbon::parse($pb->received_at)->toDateString();
            $productDates[$pb->product_id][$date] = true;
        }

        // Fetch all confirmed batches on those dates (needed for accurate FIFO allocation)
        $allBatchesQuery = StockBatch::query()
            ->whereIn('product_id', array_keys($productDates))
            ->where('warehouse_receive_pending', false)
            ->where('reference', 'not like', 'NEG-%')
            ->orderBy('id', 'asc');

        $allBatches = $allBatchesQuery->get(['id', 'product_id', 'total_kg', 'received_at']);

        // Filter in memory to only the relevant dates
        $relevantBatches = $allBatches->filter(function ($b) use ($productDates) {
            $d = Carbon::parse($b->received_at)->toDateString();

            return isset($productDates[$b->product_id][$d]);
        });

        $allBatchIds = $relevantBatches->pluck('id')->unique();

        // 1. Wastage entries
        $allWastages = WastageEntry::query()
            ->whereIn('batch_id', $allBatchIds)
            ->groupBy('batch_id')
            ->selectRaw('batch_id, sum(quantity) as total_qty')
            ->pluck('total_qty', 'batch_id');

        // 2. Stock movements (write-off and reversal)
        $movements = StockMovement::query()
            ->whereIn('batch_id', $pendingBatches->pluck('id'))
            ->groupBy('batch_id')
            ->selectRaw('
                batch_id,
                SUM(CASE WHEN type IN (?, ?, ?, ?) THEN quantity ELSE 0 END) as write_off,
                SUM(CASE WHEN type = ? THEN quantity ELSE 0 END) as reversal
            ', [
                StockMovementType::Out->value,
                StockMovementType::Sale->value,
                StockMovementType::Wastage->value,
                StockMovementType::Adjustment->value,
                StockMovementType::SaleReversal->value,
            ])
            ->get()
            ->keyBy('batch_id');

        // 3. Allocations by (product_id, date)
        $allDates = [];
        foreach ($productDates as $dates) {
            foreach (array_keys($dates) as $d) {
                $allDates[$d] = true;
            }
        }

        $allAllocations = ShopOrderItem::query()
            ->whereIn('shop_order_items.product_id', array_keys($productDates))
            ->where('shop_order_items.sorting_status', 'allocated')
            ->join('shop_orders', 'shop_order_items.shop_order_id', '=', 'shop_orders.id')
            ->where('shop_orders.state', 'approved')
            ->whereIn(DB::raw('DATE(shop_orders.business_date)'), array_keys($allDates))
            ->groupBy('shop_order_items.product_id', DB::raw('DATE(shop_orders.business_date)'))
            ->selectRaw('shop_order_items.product_id, DATE(shop_orders.business_date) as b_date, sum(shop_order_items.approved_qty) as total_qty')
            ->get()
            ->groupBy('product_id');

        // 4. Calculate FIFO batch allocations
        $batchesByProductAndDate = [];
        foreach ($relevantBatches as $b) {
            $d = Carbon::parse($b->received_at)->toDateString();
            $batchesByProductAndDate[$b->product_id][$d][] = $b;
        }

        $batchAllocations = [];
        foreach ($batchesByProductAndDate as $pId => $dateGroups) {
            $prodAllocs = $allAllocations->get($pId, collect())->pluck('total_qty', 'b_date');

            foreach ($dateGroups as $d => $batchesOnDate) {
                $totalAllocated = (float) ($prodAllocs[$d] ?? 0);
                $remainingAllocated = $totalAllocated;

                foreach ($batchesOnDate as $b) {
                    $wasted = (float) ($allWastages[$b->id] ?? 0);
                    $maxAllocatable = max(0.0, (float) $b->total_kg - $wasted);
                    $allocated = min($maxAllocatable, $remainingAllocated);

                    $batchAllocations[$b->id] = $allocated;

                    $remainingAllocated -= $allocated;
                    if ($remainingAllocated <= 0) {
                        continue;
                    }
                }
            }
        }

        // 5. Aggregate available mixed qty for pending batches
        $results = [];
        foreach ($productIds as $pId) {
            $results[$pId] = 0.0;
        }

        foreach ($pendingBatches as $pb) {
            $wasted = (float) ($allWastages[$pb->id] ?? 0);
            $allocated = (float) ($batchAllocations[$pb->id] ?? 0);
            $rem = max(0.0, (float) $pb->total_kg - $wasted - $allocated);

            $m = $movements->get($pb->id);
            $writeOff = (float) ($m->write_off ?? 0);
            $reversal = (float) ($m->reversal ?? 0);
            $netWriteOff = max(0.0, $writeOff - $reversal);

            $avail = round(max(0.0, $rem - $netWriteOff), 3);
            $results[$pb->product_id] = round($results[$pb->product_id] + $avail, 3);
        }

        return $results;
    }

    /**
     * Compute authoritative metrics for a specific product and grade.
     *
     * @param  array<int, float>|null  $precomputedMixedStock
     * @return array<string, mixed>
     */
    public function calculateGradeMetrics(
        int $productId,
        string $grade,
        ?string $startDate,
        ?string $endDate,
        ?int $warehouseId,
        ?int $purchaserId,
        ?int $shopId,
        ?array $precomputedMixedStock = null
    ): array {
        $isMixed = in_array($grade, ['U', 'MIXED', 'UNSORTED'], true);

        // 1. ORIGINAL PURCHASES (Root batches only: parent_batch_id IS NULL)
        $rootBatchesQuery = StockBatch::query()
            ->where('product_id', $productId)
            ->whereNull('parent_batch_id')
            ->where('warehouse_receive_pending', false)
            ->where('reference', 'not like', 'NEG-%');

        if ($startDate) {
            $rootBatchesQuery->whereDate('received_at', '>=', $startDate);
        }
        if ($endDate) {
            $rootBatchesQuery->whereDate('received_at', '<=', $endDate);
        }
        if ($warehouseId) {
            $rootBatchesQuery->where('warehouse_id', $warehouseId);
        }
        if ($purchaserId) {
            $rootBatchesQuery->where(function ($sub) use ($purchaserId): void {
                $sub->where('created_by', $purchaserId)
                    ->orWhereHas('goodsReceived.purchaserCart', fn ($pc) => $pc->where('user_id', $purchaserId));
            });
        }

        if ($isMixed) {
            $rootBatchesQuery->where(function ($sub): void {
                $sub->whereIn('purchase_grade', ['U', 'MIXED', 'UNSORTED'])
                    ->orWhereNull('purchase_grade');
            });
        } else {
            $rootBatchesQuery->where('purchase_grade', $grade);
        }

        $rootBatches = $rootBatchesQuery->with('segregatedBatches')->get();

        $purchasedKg = 0.0;
        $purchasedCost = 0.0;
        foreach ($rootBatches as $rb) {
            $origTotal = (float) $rb->original_total_kg;
            $purchasedKg += $origTotal;
            $purchasedCost += ($origTotal * (float) $rb->cost_per_kg);
        }

        $weightedPurchaseCost = $purchasedKg > 0.001
            ? ($purchasedCost / $purchasedKg)
            : 0.0;

        // 2. SEGREGATION & REGRADING
        // A child batch is created when segregating or regrading
        $childBatchesQuery = StockBatch::query()
            ->where('product_id', $productId)
            ->whereNotNull('parent_batch_id');

        if ($startDate) {
            $childBatchesQuery->whereDate('received_at', '>=', $startDate);
        }
        if ($endDate) {
            $childBatchesQuery->whereDate('received_at', '<=', $endDate);
        }
        if ($warehouseId) {
            $childBatchesQuery->where('warehouse_id', $warehouseId);
        }

        $segregatedInKg = 0.0;
        $regradedInKg = 0.0;
        $regradedOutKg = 0.0;

        if (! $isMixed) {
            // Inward segregation into this grade
            $inwardBatches = (clone $childBatchesQuery)
                ->where('purchase_grade', $grade)
                ->with('parentBatch')
                ->get();

            foreach ($inwardBatches as $cb) {
                $parentGrade = $cb->parentBatch?->purchase_grade ?? 'U';
                $parentIsMixed = in_array(strtoupper($parentGrade), ['U', 'MIXED', 'UNSORTED'], true);

                if ($parentIsMixed) {
                    // Segregated from Mixed into this grade
                    $segregatedInKg += (float) $cb->total_kg;
                } else {
                    // Regraded from another sellable grade into this grade
                    $regradedInKg += (float) $cb->total_kg;
                }
            }

            // Regraded OUT of this grade to another grade
            $outwardRegradedBatches = (clone $childBatchesQuery)
                ->where('purchase_grade', '!=', $grade)
                ->whereHas('parentBatch', fn ($pb) => $pb->where('purchase_grade', $grade))
                ->get();

            $regradedOutKg = (float) $outwardRegradedBatches->sum('total_kg');
        } else {
            // For Mixed: segregation out of Mixed to other grades
            $outwardFromMixedBatches = (clone $childBatchesQuery)
                ->whereHas('parentBatch', function ($pb): void {
                    $pb->whereIn('purchase_grade', ['U', 'MIXED', 'UNSORTED'])
                        ->orWhereNull('purchase_grade');
                })
                ->get();

            // Total segregated out of Mixed
            $segregatedInKg = (float) $outwardFromMixedBatches->sum('total_kg');
        }

        // 3. LOADOUT MOVEMENTS (Dispatches)
        // Check physical loadout movements
        $movementsQuery = StockMovement::query()
            ->where('product_id', $productId)
            ->whereIn('type', [
                StockMovementType::Out->value,
                StockMovementType::Sale->value,
            ])
            ->where(function ($sub): void {
                $sub->whereNotNull('shop_order_item_id')
                    ->orWhereNotNull('warehouse_sale_item_id')
                    ->orWhere('notes', 'like', '%loadout%');
            });

        if ($startDate) {
            $movementsQuery->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $movementsQuery->whereDate('created_at', '<=', $endDate);
        }
        if ($warehouseId) {
            $movementsQuery->where('warehouse_id', $warehouseId);
        }
        if ($shopId) {
            $movementsQuery->whereHas('shopOrderItem.order', fn ($so) => $so->where('shop_id', $shopId));
        }

        $physicalMovements = (clone $movementsQuery)
            ->where('grade', $isMixed ? 'U' : $grade)
            ->with(['shopOrderItem', 'batch'])
            ->get();

        $physicalLoadedKg = 0.0;
        $mixedLoadedAsOtherGrades = [];
        $pendingCostReconciliationCount = 0;
        $pendingCostReconciliationQty = 0.0;

        foreach ($physicalMovements as $pm) {
            $qty = (float) $pm->quantity;
            $physicalLoadedKg += $qty;

            // Check if this movement came from a negative-stock batch or has unresolved cost
            if (($pm->batch && Str::startsWith((string) $pm->batch->reference, 'NEG-')) || (float) $pm->cost_per_unit <= 0.0001) {
                $pendingCostReconciliationCount++;
                $pendingCostReconciliationQty += $qty;
            }

            if ($isMixed && $pm->shopOrderItem) {
                $commercialGrade = strtoupper((string) ($pm->shopOrderItem->product_grade ?: 'B'));
                if (! isset($mixedLoadedAsOtherGrades[$commercialGrade])) {
                    $mixedLoadedAsOtherGrades[$commercialGrade] = 0.0;
                }
                $mixedLoadedAsOtherGrades[$commercialGrade] += $qty;
            }
        }

        // Commercial loadout:
        // For Grade B, this includes items ordered/loaded as Grade B (even if sourced from Mixed!)
        $commercialLoadedKg = $physicalLoadedKg;
        if (! $isMixed) {
            // Find loadouts where commercial grade in shop_order_item was $grade, but physical source was 'U' (Mixed)
            $mixedConsumedForThisGrade = (clone $movementsQuery)
                ->where('grade', 'U')
                ->whereHas('shopOrderItem', fn ($soi) => $soi->where('product_grade', $grade))
                ->sum('quantity');

            $commercialLoadedKg += (float) $mixedConsumedForThisGrade;
        }

        // 4. DAMAGE (WastageEntry)
        $damageQuery = WastageEntry::query()
            ->where('product_id', $productId);

        if ($startDate) {
            $damageQuery->whereDate('wastage_date', '>=', $startDate);
        }
        if ($endDate) {
            $damageQuery->whereDate('wastage_date', '<=', $endDate);
        }
        if ($warehouseId) {
            $damageQuery->whereHas('batch', fn ($b) => $b->where('warehouse_id', $warehouseId));
        }

        if ($isMixed) {
            $damageQuery->where(function ($sub): void {
                $sub->whereIn('grade', ['U', 'MIXED', 'UNSORTED', 'DMG'])
                    ->orWhereNull('grade');
            });
        } else {
            $damageQuery->where('grade', $grade);
        }

        $damageEntries = $damageQuery->get();
        $damagedKg = (float) $damageEntries->sum('quantity');
        $damagedCost = 0.0;
        foreach ($damageEntries as $de) {
            $damagedCost += ((float) $de->quantity * (float) $de->cost_per_kg);
        }

        // 5. CURRENT AVAILABLE STOCK (Warehouse Ledger)
        $availableKg = 0.0;
        if ($isMixed) {
            if ($precomputedMixedStock !== null && array_key_exists($productId, $precomputedMixedStock)) {
                $availableKg = (float) $precomputedMixedStock[$productId];
            } else {
                $res = $this->calculateAvailableMixedStockForProducts([$productId], $warehouseId);
                $availableKg = (float) ($res[$productId] ?? 0.0);
            }
        } else {
            // For sorted sellable grades (A, B, C, D):
            $sortedBatchesQuery = StockBatch::query()
                ->where('product_id', $productId)
                ->where('purchase_grade', $grade)
                ->where('warehouse_receive_pending', false)
                ->where('reference', 'not like', 'NEG-%');

            if ($warehouseId) {
                $sortedBatchesQuery->where('warehouse_id', $warehouseId);
            }

            $batchIds = $sortedBatchesQuery->pluck('id');

            if ($batchIds->isNotEmpty()) {
                $movements = StockMovement::query()
                    ->whereIn('batch_id', $batchIds)
                    ->selectRaw('
                        SUM(CASE WHEN type IN (?, ?) THEN quantity ELSE 0 END) as in_qty,
                        SUM(CASE WHEN type IN (?, ?, ?, ?) THEN quantity ELSE 0 END) as out_qty
                    ', [
                        StockMovementType::In->value,
                        StockMovementType::SaleReversal->value,
                        StockMovementType::Out->value,
                        StockMovementType::Wastage->value,
                        StockMovementType::Sale->value,
                        StockMovementType::Adjustment->value,
                    ])
                    ->first();

                $inQ = (float) ($movements->in_qty ?? 0);
                $outQ = (float) ($movements->out_qty ?? 0);

                if ($inQ <= 0.0001) {
                    $inQ = (float) $sortedBatchesQuery->sum('total_kg');
                }

                $availableKg = max(0.0, $inQ - $outQ);
            }
        }

        $availableKg = round($availableKg, 3);
        $availableValuation = round($availableKg * $weightedPurchaseCost, 2);

        // 6. ACTUAL REALIZED INVOICE SELLING PRICE
        $invoiceItemsQuery = DB::table('shop_invoice_items')
            ->join('shop_invoices', 'shop_invoices.id', '=', 'shop_invoice_items.shop_invoice_id')
            ->where('shop_invoices.status', '!=', 'cancelled')
            ->where('shop_invoice_items.product_id', $productId)
            ->where('shop_invoice_items.delivered_qty', '>', 0);

        if ($startDate) {
            $invoiceItemsQuery->whereDate('shop_invoices.business_date', '>=', $startDate);
        }
        if ($endDate) {
            $invoiceItemsQuery->whereDate('shop_invoices.business_date', '<=', $endDate);
        }
        if ($shopId) {
            $invoiceItemsQuery->where('shop_invoices.shop_id', $shopId);
        }

        if ($isMixed) {
            $invoiceItemsQuery->whereIn('shop_invoice_items.product_grade', ['U', 'MIXED', 'UNSORTED']);
        } else {
            $invoiceItemsQuery->where('shop_invoice_items.product_grade', $grade);
        }

        $invoiceStats = $invoiceItemsQuery->selectRaw('
            COALESCE(SUM(shop_invoice_items.delivered_qty), 0) as total_delivered,
            COALESCE(SUM(shop_invoice_items.final_line_total), 0) as total_amount
        ')->first();

        $invoicedDeliveredKg = (float) ($invoiceStats->total_delivered ?? 0);
        $invoicedSalesAmount = (float) ($invoiceStats->total_amount ?? 0);

        $weightedSellingRate = $invoicedDeliveredKg > 0.001
            ? ($invoicedSalesAmount / $invoicedDeliveredKg)
            : 0.0;

        $hasPendingCostReconciliation = $pendingCostReconciliationCount > 0;
        $marginPercent = null;
        if (! $hasPendingCostReconciliation && $weightedSellingRate > 0.001 && $weightedPurchaseCost > 0.0) {
            $marginPercent = round((($weightedSellingRate - $weightedPurchaseCost) / $weightedSellingRate) * 100, 1);
        }

        return [
            'grade' => $grade,
            'grade_label' => ProductGrade::tryResolve($grade)?->label() ?? ($isMixed ? 'Mixed / Unsegregated' : "Grade {$grade}"),
            'purchased_kg' => round($purchasedKg, 3),
            'purchased_total_cost' => round($purchasedCost, 2),
            'weighted_purchase_cost' => round($weightedPurchaseCost, 2),
            'segregated_kg' => round($segregatedInKg, 3),
            'regraded_kg' => round($regradedInKg > 0 ? $regradedInKg : $regradedOutKg, 3),
            'regraded_in_kg' => round($regradedInKg, 3),
            'regraded_out_kg' => round($regradedOutKg, 3),
            'physical_loaded_kg' => round($physicalLoadedKg, 3),
            'commercial_loaded_kg' => round($commercialLoadedKg, 3),
            'mixed_loaded_as_other_grades' => $mixedLoadedAsOtherGrades,
            'damaged_kg' => round($damagedKg, 3),
            'damaged_total_cost' => round($damagedCost, 2),
            'available_kg' => $availableKg,
            'available_valuation' => $availableValuation,
            'invoiced_delivered_kg' => round($invoicedDeliveredKg, 3),
            'invoiced_sales_amount' => round($invoicedSalesAmount, 2),
            'weighted_selling_rate' => round($weightedSellingRate, 2),
            'margin_percent' => $marginPercent,
            'has_pending_cost_reconciliation' => $hasPendingCostReconciliation,
            'pending_reconciliation_count' => $pendingCostReconciliationCount,
            'pending_reconciliation_qty' => round($pendingCostReconciliationQty, 3),
        ];
    }

    /**
     * Get deep audit detail for a specific batch.
     *
     * @return array<string, mixed>
     */
    public function getBatchAuditDetail(int|string $batchIdentifier): array
    {
        $batch = StockBatch::query()
            ->with([
                'product.category',
                'warehouse',
                'createdBy',
                'parentBatch.warehouse',
                'segregatedBatches',
                'goodsReceived.purchaseOrder.supplier',
                'goodsReceived.purchaserCart.user',
                'wastageEntries.recordedBy',
                'stockMovements' => function ($m): void {
                    $m->with([
                        'createdBy',
                        'shopOrderItem.order.shop',
                        'warehouseSaleItem',
                    ])->orderBy('id');
                },
            ])
            ->where(function ($sub) use ($batchIdentifier): void {
                if (is_numeric($batchIdentifier)) {
                    $sub->where('id', (int) $batchIdentifier)
                        ->orWhere('reference', (string) $batchIdentifier);
                } else {
                    $sub->where('reference', (string) $batchIdentifier);
                }
            })
            ->firstOrFail();

        $isPendingCost = Str::startsWith((string) $batch->reference, 'NEG-') || (float) $batch->cost_per_kg <= 0.0001;

        // Procurement details
        $grn = $batch->goodsReceived;
        $cart = $grn?->purchaserCart;
        $po = $grn?->purchaseOrder;

        $procurement = [
            'grn_number' => $grn?->grn_number,
            'bill_number' => $grn?->bill_number,
            'bill_status' => $grn?->bill_status,
            'purchaser_name' => $cart?->user?->name ?? $batch->createdBy?->name ?? 'Unknown',
            'supplier_name' => $po?->supplier?->name ?? 'N/A',
            'po_number' => $po?->po_number,
            'received_at' => $batch->received_at?->toDateString(),
            'total_kg' => (float) $batch->total_kg,
            'original_total_kg' => (float) $batch->original_total_kg,
            'cost_per_kg' => (float) $batch->cost_per_kg,
        ];

        // Segregations
        $children = $batch->segregatedBatches->map(fn (StockBatch $c): array => [
            'id' => $c->id,
            'reference' => $c->reference,
            'grade' => $c->purchase_grade,
            'grade_label' => ProductGrade::tryResolve($c->purchase_grade)?->label() ?? $c->purchase_grade,
            'total_kg' => (float) $c->total_kg,
            'created_at' => $c->created_at?->toDateTimeString(),
            'notes' => $c->notes,
        ])->values()->all();

        // Movements detail
        $movements = $batch->stockMovements->map(function (StockMovement $m): array {
            $shopOrder = $m->shopOrderItem?->order;
            $shop = $shopOrder?->shop;
            $commercialGrade = $m->shopOrderItem?->product_grade;
            $physicalGrade = $m->grade?->value ?? (string) $m->grade;
            $isMixedLoadedAsGrade = $physicalGrade === 'U' && ! empty($commercialGrade) && $commercialGrade !== 'U';

            return [
                'id' => $m->id,
                'created_at' => $m->created_at?->toDateTimeString(),
                'type' => $m->type->value,
                'type_label' => $m->type->label(),
                'quantity' => (float) $m->quantity,
                'cost_per_unit' => (float) $m->cost_per_unit,
                'physical_grade' => $physicalGrade,
                'commercial_grade' => $commercialGrade,
                'is_mixed_loaded_as_grade' => $isMixedLoadedAsGrade,
                'notes' => $m->notes,
                'created_by' => $m->createdBy?->name ?? 'System',
                'shop' => $shop ? [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'code' => $shop->code,
                ] : null,
                'order' => $shopOrder ? [
                    'id' => $shopOrder->id,
                    'order_number' => $shopOrder->order_number,
                    'business_date' => $shopOrder->business_date?->toDateString(),
                ] : null,
            ];
        })->values()->all();

        // Wastage Entries
        $wastages = $batch->wastageEntries->map(fn (WastageEntry $w): array => [
            'id' => $w->id,
            'date' => $w->wastage_date?->toDateString(),
            'grade' => $w->grade?->value ?? (string) $w->grade,
            'quantity' => (float) $w->quantity,
            'cost_per_kg' => (float) $w->cost_per_kg,
            'total_cost' => (float) $w->total_cost,
            'reason' => $w->reason?->value ?? (string) $w->reason,
            'notes' => $w->notes,
            'recorded_by' => $w->recordedBy?->name ?? 'Unknown',
        ])->values()->all();

        // Available balance
        $availableBalance = $batch->status === BatchStatus::Pending
            ? (float) $batch->available_mixed_qty
            : (float) $batch->remaining_qty;

        return [
            'batch' => [
                'id' => $batch->id,
                'reference' => $batch->reference,
                'product_id' => $batch->product_id,
                'product_name' => $batch->product?->name,
                'product_unit' => $batch->product?->unit ?: 'kg',
                'warehouse_name' => $batch->warehouse?->name,
                'grade' => $batch->purchase_grade,
                'grade_label' => ProductGrade::tryResolve($batch->purchase_grade)?->label() ?? $batch->purchase_grade,
                'status' => $batch->status->value,
                'total_kg' => (float) $batch->total_kg,
                'cost_per_kg' => (float) $batch->cost_per_kg,
                'available_balance' => round($availableBalance, 3),
                'is_pending_cost_reconciliation' => $isPendingCost,
                'notes' => $batch->notes,
            ],
            'procurement' => $procurement,
            'parent_batch' => $batch->parentBatch ? [
                'id' => $batch->parentBatch->id,
                'reference' => $batch->parentBatch->reference,
                'grade' => $batch->parentBatch->purchase_grade,
                'total_kg' => (float) $batch->parentBatch->total_kg,
                'cost_per_kg' => (float) $batch->parentBatch->cost_per_kg,
            ] : null,
            'segregated_children' => $children,
            'movements' => $movements,
            'wastages' => $wastages,
        ];
    }

    /**
     * Compile authoritative Daily Grade & Stock Summary for V2 report.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function compileDailyGradeSummary(?Product $selectedProduct, array $filters): array
    {
        $startDate = (string) ($filters['start_date'] ?? ($filters['date'] ?? now('Asia/Kolkata')->toDateString()));
        $endDate = (string) ($filters['end_date'] ?? ($filters['date'] ?? now('Asia/Kolkata')->toDateString()));
        $reportDate = $startDate;
        $warehouseId = ! empty($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;
        $rawGrade = strtoupper(trim((string) ($filters['grade'] ?? 'A')));
        $selectedGrade = in_array($rawGrade, ['A', 'B', 'C', 'DAMAGE', 'DMG'], true) ? ($rawGrade === 'DMG' ? 'DAMAGE' : $rawGrade) : 'A';
        $search = trim((string) ($filters['search'] ?? $filters['search_product'] ?? ''));
        $todayDate = now('Asia/Kolkata')->toDateString();
        $isToday = ($reportDate === $todayDate && $startDate === $endDate);
        $productId = $selectedProduct?->id;

        // 1. Tab Quick-Indicators (Grade A, B, C, Damage)
        $tabMetrics = [
            'A' => $this->getTabSummaryMetric('A', $startDate, $endDate, $warehouseId, $productId),
            'B' => $this->getTabSummaryMetric('B', $startDate, $endDate, $warehouseId, $productId),
            'C' => $this->getTabSummaryMetric('C', $startDate, $endDate, $warehouseId, $productId),
            'DAMAGE' => $this->getTabDamageMetric($startDate, $endDate, $warehouseId, $productId),
        ];

        // 2. Active Grade Summary (Section 3) for A, B, C
        $gradeSummary = null;
        $dailyProducts = null;
        $productRows = [];

        if (in_array($selectedGrade, ['A', 'B', 'C'], true)) {
            $availData = $this->calculateGradeAvailableStock($selectedGrade, $warehouseId, $productId, $startDate, $endDate);
            $dispData = $this->calculateGradeDispatched($selectedGrade, $startDate, $endDate, $warehouseId, $productId);
            $billData = $this->calculateGradeBilled($selectedGrade, $startDate, $endDate, $warehouseId, $productId);
            $dmgData = $this->calculateGradeDamage($selectedGrade, $startDate, $endDate, $warehouseId, $productId);

            $gradeSummary = [
                'grade' => $selectedGrade,
                'available_total' => $availData['total'],
                'available_formatted' => self::formatUnitSummary($availData['units']),
                'available_units' => $availData['units'],
                'dispatched_total' => $dispData['total'],
                'dispatched_formatted' => self::formatUnitSummary($dispData['units']),
                'dispatched_units' => $dispData['units'],
                'billed_total' => $billData['total'],
                'billed_val' => $billData['total_val'],
                'billed_formatted' => self::formatUnitSummary($billData['units']),
                'billed_units' => $billData['units'],
                'damage_total' => $dmgData['total'],
                'damage_formatted' => self::formatUnitSummary($dmgData['units']),
                'damage_units' => $dmgData['units'],
                'is_today' => $isToday,
            ];

            // Section 4 Product Table
            // Scope candidates: products having available stock OR activity on report date
            $candidatePids = collect();
            if ($productId !== null) {
                $candidatePids->push($productId);
            } else {
                $batchPids = StockBatch::query()
                    ->where('purchase_grade', $selectedGrade)
                    ->whereDate('received_at', '<=', $endDate)
                    ->whereDate('received_at', '>=', $startDate)
                    ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
                    ->distinct()
                    ->pluck('product_id');

                $candidatePids = $candidatePids
                    ->merge(array_keys($availData['by_product']))
                    ->merge(array_keys($dispData['by_product']))
                    ->merge(array_keys($billData['by_product']))
                    ->merge(array_keys($dmgData['by_product']))
                    ->merge($batchPids)
                    ->unique();
            }

            $prodQuery = Product::query()->with('category');
            if ($productId !== null) {
                $prodQuery->where('id', $productId);
            } else {
                $prodQuery->whereIn('id', $candidatePids);
                if ($search !== '') {
                    $prodQuery->where(function ($sq) use ($search): void {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
                }
            }

            $dailyProducts = $prodQuery->orderBy('name')->paginate(5)->withQueryString();

            foreach ($dailyProducts->items() as $p) {
                $pId = (int) $p->id;
                $pUnit = strtoupper((string) ($p->unit ?: 'KG'));

                $rowAvailable = $availData['by_product'][$pId] ?? null;
                if ($rowAvailable === null) {
                    $batchSum = (float) StockBatch::query()
                        ->where('product_id', $pId)
                        ->where('purchase_grade', $selectedGrade)
                        ->where('warehouse_receive_pending', false)
                        ->where('reference', 'not like', 'NEG-%')
                        ->where('status', '!=', BatchStatus::Closed)
                        ->whereDate('received_at', '<=', $endDate)
                        ->whereDate('received_at', '>=', $startDate)
                        ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
                        ->sum('total_kg');
                    $rowAvailable = $batchSum;
                }

                $productRows[] = [
                    'id' => $pId,
                    'product' => $p,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'unit' => $pUnit,
                    'available' => round((float) $rowAvailable, 3),
                    'dispatched' => round($dispData['by_product'][$pId] ?? 0.0, 3),
                    'billed' => round($billData['by_product'][$pId] ?? 0.0, 3),
                    'billed_rate' => $billData['by_product_rate'][$pId] ?? 0.0,
                    'damage' => round($dmgData['by_product'][$pId] ?? 0.0, 3),
                ];
            }
        }

        // 3. Section 5 Damage Tab Data
        $damageData = null;
        if ($selectedGrade === 'DAMAGE') {
            $damageData = $this->compileDailyDamageReport($startDate, $endDate, $warehouseId, $productId, $search);
        }

        // 4. Section 6 Shop Returns Data (Independent of active tab)
        $shopReturns = $this->compileDailyShopReturns($startDate, $endDate, $warehouseId, $search);

        return [
            'selectedGrade' => $selectedGrade,
            'reportDate' => $reportDate,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'isToday' => $isToday,
            'tabMetrics' => $tabMetrics,
            'gradeSummary' => $gradeSummary,
            'dailyProducts' => $dailyProducts,
            'productRows' => $productRows,
            'damageData' => $damageData,
            'shopReturns' => $shopReturns,
        ];
    }

    /**
     * Quick summary metrics for Grade A, B, C tabs.
     *
     * @return array<string, mixed>
     */
    private function getTabSummaryMetric(string $grade, string $startDate, string $endDate, ?int $warehouseId, ?int $productId): array
    {
        $avail = $this->calculateGradeAvailableStock($grade, $warehouseId, $productId, $startDate, $endDate);
        $disp = $this->calculateGradeDispatched($grade, $startDate, $endDate, $warehouseId, $productId);

        return [
            'grade' => $grade,
            'available' => $avail['total'],
            'available_formatted' => self::formatUnitSummary($avail['units']),
            'dispatched' => $disp['total'],
            'dispatched_formatted' => self::formatUnitSummary($disp['units']),
            'from_mixed' => $disp['from_mixed'] ?? 0.0,
        ];
    }

    /**
     * Quick summary metrics for Damage tab.
     *
     * @return array<string, mixed>
     */
    private function getTabDamageMetric(string $startDate, string $endDate, ?int $warehouseId, ?int $productId): array
    {
        $wQuery = WastageEntry::query()->whereNull('deleted_at');
        if ($startDate === $endDate) {
            $wQuery->whereDate('wastage_date', $startDate);
        } else {
            $wQuery->whereDate('wastage_date', '>=', $startDate)->whereDate('wastage_date', '<=', $endDate);
        }
        if ($warehouseId) {
            $wQuery->whereHas('batch', fn ($b) => $b->where('warehouse_id', $warehouseId));
        }
        if ($productId) {
            $wQuery->where('product_id', $productId);
        }
        $wTotal = (float) $wQuery->sum('quantity');

        $bQuery = StockBatch::query()
            ->where('purchase_grade', ProductGrade::Damage->value)
            ->whereNotNull('parent_batch_id')
            ->whereNull('deleted_at');
        if ($startDate === $endDate) {
            $bQuery->whereDate('created_at', $startDate);
        } else {
            $bQuery->whereDate('created_at', '>=', $startDate)->whereDate('created_at', '<=', $endDate);
        }
        if ($warehouseId) {
            $bQuery->where('warehouse_id', $warehouseId);
        }
        if ($productId) {
            $bQuery->where('product_id', $productId);
        }
        $bTotal = (float) $bQuery->sum('total_kg');

        $quarantine = (float) StockBatch::query()
            ->where('purchase_grade', ProductGrade::Damage->value)
            ->where('status', '!=', BatchStatus::Closed)
            ->where('total_kg', '>', 0)
            ->whereNull('deleted_at')
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->sum('total_kg');

        return [
            'moved_today' => round($wTotal + $bTotal, 3),
            'quarantine' => round($quarantine, 3),
        ];
    }

    /**
     * Calculate current available stock for given grade.
     *
     * @return array{total: float, units: array<string, float>, by_product: array<int, float>}
     */
    private function calculateGradeAvailableStock(string $grade, ?int $warehouseId, ?int $productId, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = DB::table('stock_batches as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->where('b.purchase_grade', $grade)
            ->where('b.warehouse_receive_pending', false)
            ->where('b.reference', 'not like', 'NEG-%')
            ->where('b.status', '!=', BatchStatus::Closed->value)
            ->where('b.total_kg', '>', 0)
            ->whereNull('b.deleted_at');

        if ($warehouseId) {
            $query->where('b.warehouse_id', $warehouseId);
        }
        if ($productId) {
            $query->where('b.product_id', $productId);
        }
        if ($endDate) {
            $query->whereDate('b.received_at', '<=', $endDate);
        }
        if ($startDate) {
            $query->whereDate('b.received_at', '>=', $startDate);
        }

        $results = $query->selectRaw('b.product_id, p.unit, SUM(b.total_kg) as total_kg')
            ->groupBy('b.product_id', 'p.unit')
            ->get();

        $byProduct = [];
        $units = [];
        $total = 0.0;

        foreach ($results as $r) {
            $pId = (int) $r->product_id;
            $qty = (float) $r->total_kg;
            $u = strtolower((string) ($r->unit ?: 'kg'));

            $byProduct[$pId] = ($byProduct[$pId] ?? 0.0) + $qty;
            $units[$u] = ($units[$u] ?? 0.0) + $qty;
            $total += $qty;
        }

        return [
            'total' => round($total, 3),
            'units' => $units,
            'by_product' => $byProduct,
        ];
    }

    /**
     * Calculate physically dispatched quantity on given date for grade.
     *
     * @return array{total: float, units: array<string, float>, by_product: array<int, float>, from_mixed: float}
     */
    private function calculateGradeDispatched(string $grade, string $startDate, string $endDate, ?int $warehouseId, ?int $productId): array
    {
        $query = StockMovement::query()
            ->join('products as p', 'p.id', '=', 'stock_movements.product_id')
            ->whereIn('stock_movements.type', [StockMovementType::Out->value, StockMovementType::Sale->value]);

        if ($startDate === $endDate) {
            $query->whereDate('stock_movements.created_at', $startDate);
        } else {
            $query->whereDate('stock_movements.created_at', '>=', $startDate)
                ->whereDate('stock_movements.created_at', '<=', $endDate);
        }

        if ($warehouseId) {
            $query->where('stock_movements.warehouse_id', $warehouseId);
        }
        if ($productId) {
            $query->where('stock_movements.product_id', $productId);
        }

        $query->where(function ($sq) use ($grade): void {
            $sq->where('stock_movements.grade', $grade)
                ->orWhere(function ($mixedSub) use ($grade): void {
                    $mixedSub->where('stock_movements.grade', 'U')
                        ->whereHas('shopOrderItem', fn ($soi) => $soi->where('product_grade', $grade));
                });
        });

        $results = $query->selectRaw('stock_movements.product_id, stock_movements.grade, p.unit, SUM(stock_movements.quantity) as total_qty')
            ->groupBy('stock_movements.product_id', 'stock_movements.grade', 'p.unit')
            ->get();

        $byProduct = [];
        $units = [];
        $total = 0.0;
        $fromMixed = 0.0;

        foreach ($results as $r) {
            $pId = (int) $r->product_id;
            $qty = (float) $r->total_qty;
            $u = strtolower((string) ($r->unit ?: 'kg'));

            $byProduct[$pId] = ($byProduct[$pId] ?? 0.0) + $qty;
            $units[$u] = ($units[$u] ?? 0.0) + $qty;
            $total += $qty;

            $rGrade = $r->grade instanceof \BackedEnum ? $r->grade->value : (string) $r->grade;
            if ($rGrade === 'U') {
                $fromMixed += $qty;
            }
        }

        return [
            'total' => round($total, 3),
            'units' => $units,
            'by_product' => $byProduct,
            'from_mixed' => round($fromMixed, 3),
        ];
    }

    /**
     * Calculate billed quantity from finalized shop invoices on given date for grade.
     *
     * @return array{total: float, units: array<string, float>, by_product: array<int, float>}
     */
    private function calculateGradeBilled(string $grade, string $startDate, string $endDate, ?int $warehouseId, ?int $productId): array
    {
        $query = DB::table('shop_invoice_items')
            ->join('shop_invoices', 'shop_invoices.id', '=', 'shop_invoice_items.shop_invoice_id')
            ->leftJoin('products', 'products.id', '=', 'shop_invoice_items.product_id')
            ->where('shop_invoices.status', '!=', 'cancelled')
            ->where(function ($sub): void {
                $sub->whereNotNull('shop_invoices.finalized_at')
                    ->orWhereIn('shop_invoices.status', ['finalized', 'payment_pending', 'paid'])
                    ->orWhereIn('shop_invoices.delivery_status', ['received_full', 'approved_after_discrepancy']);
            })
            ->where('shop_invoice_items.product_grade', $grade)
            ->where('shop_invoice_items.delivered_qty', '>', 0);

        if ($startDate === $endDate) {
            $query->whereDate('shop_invoices.business_date', $startDate);
        } else {
            $query->whereDate('shop_invoices.business_date', '>=', $startDate)
                ->whereDate('shop_invoices.business_date', '<=', $endDate);
        }

        if ($productId) {
            $query->where('shop_invoice_items.product_id', $productId);
        }

        $results = $query->selectRaw('shop_invoice_items.product_id, COALESCE(shop_invoice_items.unit, products.unit) as unit, SUM(shop_invoice_items.delivered_qty) as total_qty, SUM(shop_invoice_items.final_line_total) as total_val')
            ->groupBy('shop_invoice_items.product_id', DB::raw('COALESCE(shop_invoice_items.unit, products.unit)'))
            ->get();

        $byProduct = [];
        $byProductRate = [];
        $byProductVal = [];
        $units = [];
        $total = 0.0;
        $totalVal = 0.0;

        foreach ($results as $r) {
            $pId = (int) $r->product_id;
            $qty = (float) $r->total_qty;
            $val = (float) ($r->total_val ?? 0.0);
            $u = strtolower((string) ($r->unit ?: 'kg'));

            $byProduct[$pId] = ($byProduct[$pId] ?? 0.0) + $qty;
            $byProductVal[$pId] = ($byProductVal[$pId] ?? 0.0) + $val;
            $byProductRate[$pId] = $qty > 0 ? round($val / $qty, 2) : 0.0;
            $units[$u] = ($units[$u] ?? 0.0) + $qty;
            $total += $qty;
            $totalVal += $val;
        }

        return [
            'total' => round($total, 3),
            'total_val' => round($totalVal, 2),
            'units' => $units,
            'by_product' => $byProduct,
            'by_product_rate' => $byProductRate,
            'by_product_val' => $byProductVal,
        ];
    }

    /**
     * Calculate quantity moved to damage on given date for grade.
     *
     * @return array{total: float, units: array<string, float>, by_product: array<int, float>}
     */
    private function calculateGradeDamage(string $grade, string $startDate, string $endDate, ?int $warehouseId, ?int $productId): array
    {
        // 1. WastageEntry
        $wQuery = WastageEntry::query()
            ->join('products as p', 'p.id', '=', 'wastage_entries.product_id')
            ->where('wastage_entries.grade', $grade)
            ->whereNull('wastage_entries.deleted_at');

        if ($startDate === $endDate) {
            $wQuery->whereDate('wastage_entries.wastage_date', $startDate);
        } else {
            $wQuery->whereDate('wastage_entries.wastage_date', '>=', $startDate)
                ->whereDate('wastage_entries.wastage_date', '<=', $endDate);
        }

        if ($productId) {
            $wQuery->where('wastage_entries.product_id', $productId);
        }
        if ($warehouseId) {
            $wQuery->whereHas('batch', fn ($b) => $b->where('warehouse_id', $warehouseId));
        }

        $wResults = $wQuery->selectRaw('wastage_entries.product_id, p.unit, SUM(wastage_entries.quantity) as total_qty')
            ->groupBy('wastage_entries.product_id', 'p.unit')
            ->get();

        // 2. Child batches created with purchase_grade = 'DMG' from this grade
        $dQuery = StockBatch::query()
            ->join('products as p', 'p.id', '=', 'stock_batches.product_id')
            ->where('stock_batches.purchase_grade', ProductGrade::Damage->value)
            ->whereNotNull('stock_batches.parent_batch_id')
            ->whereNull('stock_batches.deleted_at')
            ->whereHas('parentBatch', fn ($pb) => $pb->where('purchase_grade', $grade));

        if ($startDate === $endDate) {
            $dQuery->whereDate('stock_batches.created_at', $startDate);
        } else {
            $dQuery->whereDate('stock_batches.created_at', '>=', $startDate)
                ->whereDate('stock_batches.created_at', '<=', $endDate);
        }

        if ($productId) {
            $dQuery->where('stock_batches.product_id', $productId);
        }
        if ($warehouseId) {
            $dQuery->where('stock_batches.warehouse_id', $warehouseId);
        }

        $dResults = $dQuery->selectRaw('stock_batches.product_id, p.unit, SUM(stock_batches.total_kg) as total_qty')
            ->groupBy('stock_batches.product_id', 'p.unit')
            ->get();

        $byProduct = [];
        $units = [];
        $total = 0.0;

        foreach ($wResults as $r) {
            $pId = (int) $r->product_id;
            $qty = (float) $r->total_qty;
            $u = strtolower((string) ($r->unit ?: 'kg'));

            $byProduct[$pId] = ($byProduct[$pId] ?? 0.0) + $qty;
            $units[$u] = ($units[$u] ?? 0.0) + $qty;
            $total += $qty;
        }

        foreach ($dResults as $r) {
            $pId = (int) $r->product_id;
            $qty = (float) $r->total_qty;
            $u = strtolower((string) ($r->unit ?: 'kg'));

            $byProduct[$pId] = ($byProduct[$pId] ?? 0.0) + $qty;
            $units[$u] = ($units[$u] ?? 0.0) + $qty;
            $total += $qty;
        }

        return [
            'total' => round($total, 3),
            'units' => $units,
            'by_product' => $byProduct,
        ];
    }

    /**
     * Compile Section 5 Damage tab detailed report.
     *
     * @return array<string, mixed>
     */
    private function compileDailyDamageReport(string $startDate, string $endDate, ?int $warehouseId, ?int $productId, string $search): array
    {
        $wQuery = WastageEntry::query()
            ->with(['product', 'batch.warehouse', 'recordedBy'])
            ->whereNull('deleted_at');

        if ($startDate === $endDate) {
            $wQuery->whereDate('wastage_date', $startDate);
        } else {
            $wQuery->whereDate('wastage_date', '>=', $startDate)->whereDate('wastage_date', '<=', $endDate);
        }

        if ($productId) {
            $wQuery->where('product_id', $productId);
        }
        if ($warehouseId) {
            $wQuery->whereHas('batch', fn ($b) => $b->where('warehouse_id', $warehouseId));
        }
        if ($search !== '') {
            $wQuery->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
        }

        $wEntries = $wQuery->orderByDesc('id')->get();

        $bQuery = StockBatch::query()
            ->with(['product', 'parentBatch', 'warehouse', 'createdBy', 'updatedBy'])
            ->where('purchase_grade', ProductGrade::Damage->value)
            ->whereNotNull('parent_batch_id')
            ->whereNull('deleted_at');

        if ($startDate === $endDate) {
            $bQuery->whereDate('created_at', $startDate);
        } else {
            $bQuery->whereDate('created_at', '>=', $startDate)->whereDate('created_at', '<=', $endDate);
        }

        if ($productId) {
            $bQuery->where('product_id', $productId);
        }
        if ($warehouseId) {
            $bQuery->where('warehouse_id', $warehouseId);
        }
        if ($search !== '') {
            $bQuery->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
        }

        $bEntries = $bQuery->orderByDesc('id')->get();

        $totalMoved = 0.0;
        $units = [];
        $items = collect();

        foreach ($wEntries as $we) {
            $qty = (float) $we->quantity;
            $unit = strtolower((string) ($we->product?->unit ?: 'kg'));
            $totalMoved += $qty;
            $units[$unit] = ($units[$unit] ?? 0.0) + $qty;

            $origGrade = $we->grade?->value ?? (string) $we->grade ?: 'U';
            $reasonStr = $we->reason?->value ?? (string) $we->reason;
            if (! empty($we->notes)) {
                $cleanNote = preg_replace('/\[(dest|submission_id):[^\]]+\]/i', '', (string) $we->notes);
                $reasonStr .= ' • '.trim((string) $cleanNote);
            }

            $items->push((object) [
                'id' => 'w-'.$we->id,
                'product_name' => $we->product?->name ?? 'Unknown',
                'product_sku' => $we->product?->sku ?? '',
                'original_grade' => $origGrade,
                'quantity' => round($qty, 3),
                'unit' => strtoupper($unit),
                'reason' => trim($reasonStr, ' •'),
                'updated_by' => $we->recordedBy?->name ?? 'Staff',
                'status' => 'Written Off',
                'reference' => $we->batch?->reference ?? 'Wastage #'.$we->id,
                'time' => $we->created_at?->format('H:i') ?? '—',
            ]);
        }

        foreach ($bEntries as $be) {
            $qty = (float) $be->total_kg;
            $unit = strtolower((string) ($be->product?->unit ?: 'kg'));
            $totalMoved += $qty;
            $units[$unit] = ($units[$unit] ?? 0.0) + $qty;

            $origGrade = $be->parentBatch?->purchase_grade ?: 'U';
            $cleanReason = preg_replace('/\[(dest|submission_id):[^\]]+\]/i', '', (string) $be->notes);
            $cleanReason = trim((string) $cleanReason) ?: 'Moved to Damage quarantine';

            $status = $be->total_kg <= 0.0001 ? 'Written Off' : 'In Quarantine';

            $items->push((object) [
                'id' => 'b-'.$be->id,
                'product_name' => $be->product?->name ?? 'Unknown',
                'product_sku' => $be->product?->sku ?? '',
                'original_grade' => $origGrade,
                'quantity' => round($qty, 3),
                'unit' => strtoupper($unit),
                'reason' => $cleanReason,
                'updated_by' => $be->updatedBy?->name ?? ($be->createdBy?->name ?? 'Staff'),
                'status' => $status,
                'reference' => $be->reference,
                'time' => $be->created_at?->format('H:i') ?? '—',
            ]);
        }

        $quarantineRemaining = (float) StockBatch::query()
            ->where('purchase_grade', ProductGrade::Damage->value)
            ->where('status', '!=', BatchStatus::Closed)
            ->where('total_kg', '>', 0)
            ->whereNull('deleted_at')
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->sum('total_kg');

        $page = (int) request()->get('page', 1);
        $perPage = 5;
        $paginatedItems = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return [
            'moved_today' => round($totalMoved, 3),
            'moved_formatted' => self::formatUnitSummary($units),
            'quarantine_remaining' => round($quarantineRemaining, 3),
            'items' => $paginatedItems,
        ];
    }

    /**
     * Compile Section 6 Shop Returns report data.
     *
     * @return array<string, mixed>
     */
    private function compileDailyShopReturns(string $startDate, string $endDate, ?int $warehouseId, string $search): array
    {
        $query = ShopOrderItem::query()
            ->whereHas('order', function ($oq) use ($startDate, $endDate): void {
                if ($startDate === $endDate) {
                    $oq->whereDate('business_date', $startDate);
                } else {
                    $oq->whereDate('business_date', '>=', $startDate)->whereDate('business_date', '<=', $endDate);
                }
            })
            ->where(function ($q): void {
                $q->where('shortage_qty', '>', 0)
                    ->orWhere('shop_reported_returned_qty', '>', 0)
                    ->orWhere('shop_reported_damaged_qty', '>', 0);
            })
            ->with([
                'order.shop',
                'order.shopCheckedBy',
                'order.adminReviewedBy',
                'product',
                'stockMovements.warehouse',
            ]);

        if ($warehouseId) {
            $query->whereHas('stockMovements', fn ($sm) => $sm->where('warehouse_id', $warehouseId));
        }

        if ($search !== '') {
            $query->where(function ($sub) use ($search): void {
                $sub->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))
                    ->orWhereHas('order.shop', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        $items = $query->orderByDesc('id')->get();

        $itemIds = $items->pluck('id')->all();
        $saleReversalItemIds = StockMovement::query()
            ->whereIn('shop_order_item_id', $itemIds)
            ->where('type', StockMovementType::SaleReversal->value)
            ->pluck('shop_order_item_id')
            ->flip();

        $wastageItemIds = collect();
        if (! empty($itemIds)) {
            $wastages = WastageEntry::query()
                ->where(function ($wq) use ($itemIds): void {
                    foreach (array_slice($itemIds, 0, 100) as $itemId) {
                        $wq->orWhere('notes', 'like', "%Item: {$itemId}%");
                    }
                })
                ->get(['id', 'notes']);

            foreach ($wastages as $w) {
                if (preg_match('/Item:\s*(\d+)/i', (string) $w->notes, $m)) {
                    $wastageItemIds->put((int) $m[1], true);
                }
            }
        }

        $returnRows = collect();
        $totalQty = 0.0;
        $units = [];

        foreach ($items as $item) {
            $order = $item->order;
            $shop = $order?->shop;
            $product = $item->product;

            $qty = (float) ($item->shortage_qty > 0 ? $item->shortage_qty : ($item->shop_reported_returned_qty > 0 ? $item->shop_reported_returned_qty : $item->shop_reported_damaged_qty));
            $unit = strtolower((string) ($item->unit ?: ($product?->unit ?: 'kg')));

            $totalQty += $qty;
            $units[$unit] = ($units[$unit] ?? 0.0) + $qty;

            if ($order?->delivery_review_status === 'pending' || $order?->delivery_status === 'awaiting_review') {
                $status = 'Pending';
                $statusColor = 'amber';
            } elseif (isset($saleReversalItemIds[$item->id])) {
                $status = 'Restocked';
                $statusColor = 'emerald';
            } elseif ($wastageItemIds->has($item->id)) {
                $status = 'Moved to Damage';
                $statusColor = 'rose';
            } else {
                $status = 'Received';
                $statusColor = 'blue';
            }

            $reason = $item->delivery_discrepancy_type ?: ($item->delivery_discrepancy_note ?: ($item->loadout_discrepancy_type ?: 'Shortage reported on delivery'));
            if ($item->delivery_discrepancy_note && $item->delivery_discrepancy_note !== $reason) {
                $reason .= ' — '.$item->delivery_discrepancy_note;
            }

            $movementWh = $item->stockMovements->first()?->warehouse?->name;
            $user = $order?->adminReviewedBy?->name ?? ($order?->shopCheckedBy?->name ?? 'Staff');

            $returnRows->push((object) [
                'id' => $item->id,
                'shop_name' => $shop?->name ?? 'Shop #'.$order?->shop_id,
                'shop_code' => $shop?->code ?? '',
                'product_name' => $product?->name ?? 'Product #'.$item->product_id,
                'product_sku' => $product?->sku ?? '',
                'grade' => $item->product_grade ? (is_object($item->product_grade) ? $item->product_grade->value : (string) $item->product_grade) : 'A',
                'quantity' => round($qty, 3),
                'unit' => strtoupper($unit),
                'status' => $status,
                'status_color' => $statusColor,
                'reason' => $reason,
                'returned_date' => $order?->business_date?->format('d M Y') ?? $startDate,
                'warehouse_name' => $movementWh ?: 'Main Warehouse',
                'responsible_user' => $user,
                'order_number' => $order?->order_number ?? '',
            ]);
        }

        return [
            'total_count' => $returnRows->count(),
            'total_qty' => round($totalQty, 3),
            'unit_summary' => self::formatUnitSummary($units),
            'items' => $returnRows,
        ];
    }

    /**
     * Format a map of units => quantity into a clean human readable summary.
     *
     * @param  array<string, float>  $unitTotals
     */
    public static function formatUnitSummary(array $unitTotals): string
    {
        $parts = [];
        foreach ($unitTotals as $unit => $qty) {
            if ($qty > 0.0001) {
                $parts[] = number_format($qty, 1).' '.strtoupper((string) $unit);
            }
        }

        return ! empty($parts) ? implode(' · ', $parts) : '0 KG';
    }
}
