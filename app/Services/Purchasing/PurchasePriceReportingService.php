<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\Inventory\ProductGrade;
use App\Models\Product;
use App\Models\ShopPriceGroup;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PurchasePriceReportingService
{
    private const PRODUCT_ORDER_EXPRESSION = "CASE WHEN products.sku IS NULL OR products.sku = '' THEN 1 ELSE 0 END ASC, products.sku ASC, products.name ASC";

    private const CATEGORY_ORDER_EXPRESSION = "CASE WHEN categories.name IS NULL OR categories.name = '' THEN 1 ELSE 0 END ASC, categories.name ASC, CASE WHEN products.sku IS NULL OR products.sku = '' THEN 1 ELSE 0 END ASC, products.sku ASC, products.name ASC";

    private const NORMALIZED_CART_GRADE = "CASE WHEN purchaser_cart_items.grade IS NULL OR TRIM(purchaser_cart_items.grade) = '' THEN 'U' WHEN UPPER(TRIM(purchaser_cart_items.grade)) IN ('MIXED', 'UNSORTED', 'MIXED / UNSEGREGATED', 'UNSEGREGATED') THEN 'U' ELSE UPPER(TRIM(purchaser_cart_items.grade)) END";

    private const NORMALIZED_APPROVAL_GRADE = "UPPER(COALESCE(NULLIF(TRIM(daily_price_approvals.grade), ''), 'A'))";

    private const NORMALIZED_PGP_GRADE = "UPPER(COALESCE(NULLIF(TRIM(purchase_grade_prices.grade), ''), 'A'))";

    private const CACHE_TTL_SECONDS = 120;

    private const GRADE_SORT_ORDER = [
        'A' => 1,
        'B' => 2,
        'C' => 3,
        'U' => 4,
        'DMG' => 5,
    ];

    /** @return array<int, ProductGrade> */
    public function activeGrades(): array
    {
        return ProductGrade::cases();
    }

    /** @return Collection<int, ShopPriceGroup> */
    public function activePriceGroups(): Collection
    {
        return ShopPriceGroup::query()
            ->active()
            ->whereIn('name', ['A', 'B', 'C'])
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** @param array<string, mixed> $filters */
    public function priceReport(array $filters, bool $paginate = true): LengthAwarePaginator|Collection
    {
        $page = $paginate ? Paginator::resolveCurrentPage() : 0;
        $cacheService = app(PurchaserReadCacheService::class);
        $cacheFilters = [
            'start_date' => (string) ($filters['start_date'] ?? $filters['date']),
            'end_date' => (string) ($filters['end_date'] ?? $filters['date']),
            'warehouse_code' => $filters['warehouse_code'] ?? null,
            'category_id' => $filters['category_id'] ?? null,
            'product_id' => $filters['product_id'] ?? null,
            'purchaser_id' => $filters['purchaser_id'] ?? null,
            'vendor_id' => $filters['vendor_id'] ?? null,
            'grade' => $filters['grade'] ?? null,
            'search' => $filters['search'] ?? null,
            'sort' => (string) ($filters['sort'] ?? 'code'),
            'paginate' => $paginate ? 1 : 0,
            'page' => $page,
        ];

        if (app()->runningUnitTests()) {
            $cachedRows = $this->buildPriceReportRows($filters);
        } else {
            $cachedRows = $cacheService->remember(
                scopes: ['carts', 'prices', 'products'],
                dataset: 'purchase_price_report_rows',
                ttlSeconds: self::CACHE_TTL_SECONDS,
                callback: fn (): array => $this->buildPriceReportRows($filters),
                tenantScope: PurchaserReadCacheService::DEFAULT_TENANT_SCOPE,
                businessDate: (string) ($filters['end_date'] ?? $filters['date']),
                grade: $filters['grade'] ?? null,
                filters: array_diff_key($cacheFilters, ['paginate' => true, 'page' => true]),
            );
        }

        $allRows = collect($cachedRows)->map(static fn (array $row): object => (object) $row);

        if (! $paginate) {
            return $allRows;
        }

        $perPage = 50;
        $sliced = $allRows->slice(($page - 1) * $perPage, $perPage)->values();

        return (new LengthAwarePaginator($sliced, $allRows->count(), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
        ]))->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function buildPriceReportRows(array $filters): array
    {
        $startDate = (string) ($filters['start_date'] ?? $filters['date']);
        $endDate = (string) ($filters['end_date'] ?? $filters['date']);
        $sort = (string) ($filters['sort'] ?? 'code');
        $orderExpression = $sort === 'category' ? self::CATEGORY_ORDER_EXPRESSION : self::PRODUCT_ORDER_EXPRESSION;

        $productsQuery = DB::table('products')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'products.default_warehouse_id')
            ->selectRaw('products.id as product_numeric_id, products.public_uuid as product_id, products.sku as product_sku, products.name as product_name, products.unit as product_unit, categories.name as category_name, warehouses.code as warehouse_code');

        if (empty($filters['product_id'])) {
            $productsQuery->where('products.is_active', true);
        }

        $this->applyProductFilters($productsQuery, $filters);
        $products = $productsQuery->orderByRaw($orderExpression)->get();

        if ($products->isEmpty()) {
            return [];
        }

        $productIds = $products->pluck('product_numeric_id')->map(static fn ($id): int => (int) $id)->all();
        $gradeFilter = ! empty($filters['grade']) ? strtoupper(trim((string) $filters['grade'])) : null;

        $currentActuals = $this->currentPurchaseAggregates($productIds, $startDate, $endDate, $filters);

        // When a single grade is filtered, skip historical lookups for products that already have a current price in that grade
        $historicalProductIds = $gradeFilter !== null
            ? array_values(array_filter($productIds, static fn (int $pid): bool => ! isset($currentActuals[$pid][$gradeFilter])))
            : $productIds;

        $historicalLast = $historicalProductIds !== []
            ? $this->historicalLastPurchasePrices($historicalProductIds, $startDate, $filters)
            : [];

        [$currentApprovals, $currentGradePrices] = $this->currentApprovedPrices($productIds, $startDate, $endDate, $filters);

        $hasVendorOrPurchaserFilter = ! empty($filters['purchaser_id']) || ! empty($filters['vendor_id']);
        $periodEndCarbon = Carbon::parse($endDate)->startOfDay();

        $rows = [];

        foreach ($products as $product) {
            $pid = (int) $product->product_numeric_id;
            $grades = $this->collectGradesForProduct(
                $pid,
                $gradeFilter,
                $hasVendorOrPurchaserFilter,
                $currentActuals,
                $historicalLast,
                $currentApprovals,
                $currentGradePrices
            );

            foreach ($grades as $grade) {
                $actual = $currentActuals[$pid][$grade] ?? null;
                $historical = $historicalLast[$pid][$grade] ?? null;
                $approval = $currentApprovals[$pid][$grade] ?? null;
                $explicitGradePrice = $currentGradePrices[$pid][$grade] ?? null;

                $currentPrice = $actual !== null ? (float) $actual->actual_purchase_price : null;
                $purchaseQty = $actual !== null ? (float) $actual->purchase_qty : null;
                $purchaseCount = $actual !== null ? (int) $actual->purchase_count : 0;

                // Last Price is only used when no valid purchase exists in the selected period,
                // guaranteeing identical row values between All Grades and single-grade filters.
                $lastPrice = ($currentPrice === null && $historical !== null) ? (float) $historical->last_purchase_price : null;
                $lastPurchaseDate = ($currentPrice === null && $historical !== null)
                    ? Carbon::parse((string) $historical->last_purchase_date)->toDateString()
                    : null;
                $lastPurchaseAgeDays = $lastPurchaseDate !== null
                    ? (int) Carbon::parse($lastPurchaseDate)->startOfDay()->diffInDays($periodEndCarbon)
                    : null;

                $approvedPurchasePrice = $explicitGradePrice?->purchase_price !== null
                    ? (float) $explicitGradePrice->purchase_price
                    : ($approval?->purchase_price !== null ? (float) $approval->purchase_price : null);

                $sellingPrice = $approval?->price_a !== null ? (float) $approval->price_a : null;
                $priceUnit = (string) ($approval?->price_unit ?? $explicitGradePrice?->price_unit ?? $product->product_unit ?? 'kg');

                if ($currentPrice !== null) {
                    $priceStatus = 'current';
                    $priceStatusLabel = 'Current Price';
                } elseif ($lastPrice !== null) {
                    $priceStatus = 'historical';
                    $priceStatusLabel = 'Historical Price';
                } else {
                    $priceStatus = 'unavailable';
                    $priceStatusLabel = 'Not Available';
                }

                $rows[] = [
                    'product_numeric_id' => $pid,
                    'product_id' => $product->product_id,
                    'product_sku' => $product->product_sku,
                    'product_name' => $product->product_name,
                    'product_unit' => $product->product_unit,
                    'category_name' => $product->category_name,
                    'warehouse_code' => $product->warehouse_code,
                    'grade' => $grade,
                    'grade_label' => ProductGrade::tryResolve($grade)?->label() ?? "Grade {$grade}",
                    'price_unit' => $priceUnit,
                    'purchase_qty' => $purchaseQty,
                    'purchase_count' => $purchaseCount,
                    'approved_purchase_price' => $approvedPurchasePrice,
                    'actual_purchase_price' => $currentPrice,
                    'current_price' => $currentPrice,
                    'last_purchase_price' => $lastPrice,
                    'last_purchase_date' => $lastPurchaseDate,
                    'last_purchase_age_days' => $lastPurchaseAgeDays,
                    'selling_price' => $sellingPrice,
                    'price_a' => $sellingPrice,
                    'price_status' => $priceStatus,
                    'price_status_label' => $priceStatusLabel,
                ];
            }
        }

        return $rows;
    }

    /** @param array<string, mixed> $filters */
    public function changedItems(array $filters, bool $paginate = true): LengthAwarePaginator|Collection
    {
        $priceColumn = $this->priceColumn((string) ($filters['price_group'] ?? 'A'));
        $query = $this->comparisonQuery($filters)
            ->selectRaw("previous.purchase_price as previous_purchase_price, current.purchase_price as current_purchase_price, previous.{$priceColumn} as previous_price, current.{$priceColumn} as current_price, current.price_unit")
            ->whereRaw("ABS(current.{$priceColumn} - previous.{$priceColumn}) > 0.001")
            ->orderByRaw(self::PRODUCT_ORDER_EXPRESSION);

        return $paginate
            ? $query->paginate(30)->withQueryString()
            : $query->limit(500)->get();
    }

    /** @param array<string, mixed> $filters */
    public function purchaserPriceComparison(array $filters): LengthAwarePaginator
    {
        $query = $this->comparisonQuery($filters)
            ->selectRaw('previous.purchase_price as previous_price, current.purchase_price as current_price, current.price_unit');

        if (! empty($filters['changed_only'])) {
            $query->whereRaw('ABS(current.purchase_price - previous.purchase_price) > 0.001');
        }

        return $query
            ->orderByRaw(self::PRODUCT_ORDER_EXPRESSION)
            ->paginate(30)
            ->withQueryString();
    }

    /** @param array<string, mixed> $filters */
    public function changedPurchaserPrices(array $filters): Collection
    {
        return $this->comparisonQuery($filters)
            ->selectRaw('previous.purchase_price as previous_price, current.purchase_price as current_price, current.price_unit')
            ->whereRaw('ABS(current.purchase_price - previous.purchase_price) > 0.001')
            ->orderByRaw(self::PRODUCT_ORDER_EXPRESSION)
            ->limit(500)
            ->get();
    }

    /** @param array<string, mixed> $filters */
    public function productDetail(Product $product, array $filters): array
    {
        $startDate = (string) ($filters['start_date'] ?? $filters['date']);
        $endDate = (string) ($filters['end_date'] ?? $filters['date']);

        $approvals = DB::table('daily_price_approvals')
            ->where('product_id', $product->id)
            ->where('status', 'approved');
        $this->applyDateRange($approvals, 'business_date', $startDate, $endDate);
        $approvals = $approvals->orderByDesc('business_date')
            ->limit(31)
            ->get();

        $history = DB::table('purchaser_cart_items')
            ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchaser_cart_items.purchaser_cart_id')
            ->join('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_carts.id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_invoices.supplier_id')
            ->leftJoin('users', 'users.id', '=', 'purchaser_carts.user_id')
            ->whereNull('purchase_invoices.deleted_at')
            ->whereNotIn('purchase_invoices.status', ['cancelled', 'draft'])
            ->whereNotIn('purchaser_carts.status', ['cancelled', 'draft'])
            ->where('purchaser_cart_items.product_id', $product->id);
        $this->applyDateRange($history, 'purchaser_carts.business_date', $startDate, $endDate);
        if (! empty($filters['grade'])) {
            $history->whereRaw(self::NORMALIZED_CART_GRADE.' = ?', [strtoupper(trim((string) $filters['grade']))]);
        }
        $history = $history->select('purchaser_carts.business_date', 'purchaser_cart_items.grade', 'purchaser_cart_items.quantity', 'purchaser_cart_items.unit_price', 'suppliers.name as vendor_name', 'users.name as purchaser_name', 'purchase_invoices.public_uuid as invoice_public_uuid', 'purchase_invoices.invoice_number')
            ->orderByDesc('purchaser_carts.business_date')
            ->limit(50)
            ->get();

        $specialPrices = DB::table('shop_daily_product_prices')
            ->join('shops', 'shops.id', '=', 'shop_daily_product_prices.shop_id')
            ->where('shop_daily_product_prices.product_id', $product->id)
            ->where('shop_daily_product_prices.status', 'approved');
        $this->applyDateRange($specialPrices, 'shop_daily_product_prices.business_date', $startDate, $endDate);
        $specialPrices = $specialPrices->select('shop_daily_product_prices.business_date', 'shops.name as shop_name', 'shop_daily_product_prices.selling_price', 'shop_daily_product_prices.price_unit')
            ->orderByDesc('shop_daily_product_prices.business_date')
            ->limit(30)
            ->get();

        return compact('approvals', 'history', 'specialPrices');
    }

    /** @return array<string, Collection<int, object>> */
    public function options(): array
    {
        return [
            'categories' => DB::table('categories')->orderBy('name')->get(['id', 'name']),
            'products' => DB::table('products')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'purchasers' => DB::table('users')->join('purchaser_carts', 'purchaser_carts.user_id', '=', 'users.id')->select('users.id', 'users.name')->distinct()->orderBy('users.name')->get(),
            'vendors' => DB::table('suppliers')->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * @param  array<int, int>  $productIds
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, object>>
     */
    private function currentPurchaseAggregates(array $productIds, string $startDate, string $endDate, array $filters): array
    {
        $query = $this->validPurchasesBaseQuery($productIds, $filters);
        $this->applyDateRange($query, 'purchaser_carts.business_date', $startDate, $endDate);

        $rows = $query
            ->selectRaw('purchaser_cart_items.product_id, '.self::NORMALIZED_CART_GRADE.' as normalized_grade, SUM(purchaser_cart_items.quantity) as purchase_qty, (1.0 * SUM(purchaser_cart_items.quantity * purchaser_cart_items.unit_price)) / NULLIF(SUM(purchaser_cart_items.quantity), 0) as actual_purchase_price, COUNT(DISTINCT purchase_invoices.id) as purchase_count')
            ->groupBy('purchaser_cart_items.product_id', DB::raw(self::NORMALIZED_CART_GRADE))
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->product_id][(string) $row->normalized_grade] = $row;
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $productIds
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, object>>
     */
    private function historicalLastPurchasePrices(array $productIds, string $beforeDate, array $filters): array
    {
        $dateExpr = $this->dateExpression('purchaser_carts.business_date');

        $latestDates = $this->validPurchasesBaseQuery($productIds, $filters);
        $this->applyBeforeDate($latestDates, 'purchaser_carts.business_date', $beforeDate);
        $latestDates
            ->selectRaw('purchaser_cart_items.product_id, '.self::NORMALIZED_CART_GRADE." as normalized_grade, MAX({$dateExpr}) as last_purchase_date")
            ->groupBy('purchaser_cart_items.product_id', DB::raw(self::NORMALIZED_CART_GRADE));

        $query = $this->validPurchasesBaseQuery($productIds, $filters)
            ->joinSub($latestDates, 'latest_dates', function ($join) use ($dateExpr): void {
                $join->on('latest_dates.product_id', '=', 'purchaser_cart_items.product_id')
                    ->whereRaw(self::NORMALIZED_CART_GRADE.' = latest_dates.normalized_grade')
                    ->whereRaw("{$dateExpr} = latest_dates.last_purchase_date");
            });

        $rows = $query
            ->selectRaw('purchaser_cart_items.product_id, '.self::NORMALIZED_CART_GRADE.' as normalized_grade, latest_dates.last_purchase_date, (1.0 * SUM(purchaser_cart_items.quantity * purchaser_cart_items.unit_price)) / NULLIF(SUM(purchaser_cart_items.quantity), 0) as last_purchase_price')
            ->groupBy('purchaser_cart_items.product_id', DB::raw(self::NORMALIZED_CART_GRADE), 'latest_dates.last_purchase_date')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->product_id][(string) $row->normalized_grade] = $row;
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $productIds
     * @param  array<string, mixed>  $filters
     * @return array{0: array<int, array<string, object>>, 1: array<int, array<string, object>>}
     */
    private function currentApprovedPrices(array $productIds, string $startDate, string $endDate, array $filters): array
    {
        $gradeFilter = ! empty($filters['grade']) ? strtoupper(trim((string) $filters['grade'])) : null;

        $approvalsQuery = DB::table('daily_price_approvals')
            ->whereIn('product_id', $productIds)
            ->where('status', 'approved');
        $this->applyDateRange($approvalsQuery, 'business_date', $startDate, $endDate);
        if ($gradeFilter !== null) {
            $approvalsQuery->whereRaw(self::NORMALIZED_APPROVAL_GRADE.' = ?', [$gradeFilter]);
        }
        $approvalsQuery->selectRaw("'approval' as source_type, id, product_id, ".self::NORMALIZED_APPROVAL_GRADE.' as normalized_grade, business_date, purchase_price, price_a, price_unit');

        $gradePricesQuery = DB::table('purchase_grade_prices')
            ->whereIn('product_id', $productIds)
            ->where('status', 'approved');
        $this->applyDateRange($gradePricesQuery, 'business_date', $startDate, $endDate);
        if ($gradeFilter !== null) {
            $gradePricesQuery->whereRaw(self::NORMALIZED_PGP_GRADE.' = ?', [$gradeFilter]);
        }
        $gradePricesQuery->selectRaw("'grade_price' as source_type, id, product_id, ".self::NORMALIZED_PGP_GRADE.' as normalized_grade, business_date, purchase_price, NULL as price_a, price_unit');

        $rows = $approvalsQuery
            ->unionAll($gradePricesQuery)
            ->orderBy('business_date')
            ->orderBy('id')
            ->get();

        $approvals = [];
        $gradePrices = [];

        foreach ($rows as $row) {
            $pid = (int) $row->product_id;
            $grade = (string) $row->normalized_grade;
            if ($row->source_type === 'grade_price') {
                $gradePrices[$pid][$grade] = $row;
            } else {
                $approvals[$pid][$grade] = $row;
            }
        }

        return [$approvals, $gradePrices];
    }

    /**
     * @param  array<int, int>  $productIds
     * @param  array<string, mixed>  $filters
     */
    private function validPurchasesBaseQuery(array $productIds, array $filters): Builder
    {
        $query = DB::table('purchaser_cart_items')
            ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchaser_cart_items.purchaser_cart_id')
            ->join('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_carts.id')
            ->whereIn('purchaser_cart_items.product_id', $productIds)
            ->whereNull('purchase_invoices.deleted_at')
            ->whereNotIn('purchase_invoices.status', ['cancelled', 'draft'])
            ->whereNotIn('purchaser_carts.status', ['cancelled', 'draft'])
            ->where('purchaser_cart_items.quantity', '>', 0)
            ->where('purchaser_cart_items.unit_price', '>', 0);

        $this->applyPurchaseFilters($query, $filters);

        return $query;
    }

    /**
     * @param  array<int, array<string, object>>  $currentActuals
     * @param  array<int, array<string, object>>  $historicalLast
     * @param  array<int, array<string, object>>  $currentApprovals
     * @param  array<int, array<string, object>>  $currentGradePrices
     * @return array<int, string>
     */
    private function collectGradesForProduct(
        int $productId,
        ?string $gradeFilter,
        bool $hasVendorOrPurchaserFilter,
        array $currentActuals,
        array $historicalLast,
        array $currentApprovals,
        array $currentGradePrices
    ): array {
        if ($gradeFilter !== null) {
            $hasActivity = isset($currentActuals[$productId][$gradeFilter])
                || isset($historicalLast[$productId][$gradeFilter])
                || (! $hasVendorOrPurchaserFilter && (isset($currentApprovals[$productId][$gradeFilter]) || isset($currentGradePrices[$productId][$gradeFilter])));

            return $hasActivity ? [$gradeFilter] : [];
        }

        $grades = array_unique(array_merge(
            array_keys($currentActuals[$productId] ?? []),
            array_keys($historicalLast[$productId] ?? []),
            $hasVendorOrPurchaserFilter ? [] : array_keys($currentApprovals[$productId] ?? []),
            $hasVendorOrPurchaserFilter ? [] : array_keys($currentGradePrices[$productId] ?? [])
        ));

        if ($grades === []) {
            return $hasVendorOrPurchaserFilter ? [] : ['A'];
        }

        usort($grades, static fn (string $a, string $b): int => (self::GRADE_SORT_ORDER[$a] ?? 99) <=> (self::GRADE_SORT_ORDER[$b] ?? 99) ?: strcmp($a, $b));

        return $grades;
    }

    private function applyDateRange(Builder $query, string $column, string $startDate, string $endDate): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $query->whereDate($column, '>=', $startDate)->whereDate($column, '<=', $endDate);

            return;
        }

        $query->where($column, '>=', $startDate)->where($column, '<=', $endDate);
    }

    private function applyBeforeDate(Builder $query, string $column, string $beforeDate): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $query->whereDate($column, '<', $beforeDate);

            return;
        }

        $query->where($column, '<', $beforeDate);
    }

    private function dateExpression(string $column): string
    {
        return DB::getDriverName() === 'sqlite' ? "DATE({$column})" : $column;
    }

    /** @param array<string, mixed> $filters */
    private function comparisonQuery(array $filters): Builder
    {
        $query = DB::table('daily_price_approvals as current')
            ->join('daily_price_approvals as previous', function ($join) use ($filters): void {
                $join->on('previous.product_id', '=', 'current.product_id')
                    ->whereDate('previous.business_date', $filters['date_a'])
                    ->where('previous.status', 'approved');
            })
            ->join('products', 'products.id', '=', 'current.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'products.default_warehouse_id')
            ->whereDate('current.business_date', $filters['date_b'])
            ->where('current.status', 'approved')
            ->whereRaw('previous.purchase_price < 9998.99 AND current.purchase_price < 9998.99')
            ->selectRaw('products.public_uuid as product_id, products.sku as product_sku, products.name as product_name, products.unit as product_unit, categories.name as category_name, warehouses.code as warehouse_code');

        $this->applyProductFilters($query, $filters);

        if ($this->hasPurchaseFilters($filters)) {
            $query->whereExists(function (Builder $purchaseQuery) use ($filters): void {
                $purchaseQuery->selectRaw('1')
                    ->from('purchaser_cart_items')
                    ->join('purchaser_carts', 'purchaser_carts.id', '=', 'purchaser_cart_items.purchaser_cart_id')
                    ->join('purchase_invoices', 'purchase_invoices.purchaser_cart_id', '=', 'purchaser_carts.id')
                    ->whereColumn('purchaser_cart_items.product_id', 'products.id')
                    ->whereNull('purchase_invoices.deleted_at')
                    ->where('purchase_invoices.status', '!=', 'cancelled')
                    ->whereDate('purchaser_carts.business_date', '>=', min($filters['date_a'], $filters['date_b']))
                    ->whereDate('purchaser_carts.business_date', '<=', max($filters['date_a'], $filters['date_b']));
                $this->applyPurchaseFilters($purchaseQuery, $filters);
            });
        }

        return $query;
    }

    private function approvalQuery(string $table): Builder
    {
        return DB::table($table)
            ->join('products', 'products.id', '=', $table.'.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'products.default_warehouse_id')
            ->selectRaw('products.public_uuid as product_id, products.sku as product_sku, products.name as product_name, products.unit as product_unit, categories.name as category_name, warehouses.code as warehouse_code');
    }

    /** @param array<string, mixed> $filters */
    private function applyProductFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['warehouse_code'])) {
            $query->where('warehouses.code', $filters['warehouse_code']);
        }
        if (! empty($filters['category_id'])) {
            $query->where('products.category_id', (int) $filters['category_id']);
        }
        if (! empty($filters['product_id'])) {
            $query->where('products.id', (int) $filters['product_id']);
        }
        if (! empty($filters['search'])) {
            $search = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['search']).'%';
            $query->where(function (Builder $q) use ($search): void {
                $q->where('products.name', 'like', $search)
                    ->orWhere('products.sku', 'like', $search);
            });
        }
    }

    /** @param array<string, mixed> $filters */
    private function applyPurchaseFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['purchaser_id'])) {
            $query->where('purchaser_carts.user_id', (int) $filters['purchaser_id']);
        }
        if (! empty($filters['vendor_id'])) {
            $query->where('purchase_invoices.supplier_id', (int) $filters['vendor_id']);
        }
        if (! empty($filters['grade'])) {
            $query->whereRaw(self::NORMALIZED_CART_GRADE.' = ?', [strtoupper(trim((string) $filters['grade']))]);
        }
    }

    /** @param array<string, mixed> $filters */
    private function hasPurchaseFilters(array $filters): bool
    {
        return ! empty($filters['purchaser_id']) || ! empty($filters['vendor_id']) || ! empty($filters['grade']);
    }

    private function priceColumn(string $group): string
    {
        return match (strtoupper($group)) {
            'B' => 'price_b',
            'C' => 'price_c',
            default => 'price_a',
        };
    }
}
