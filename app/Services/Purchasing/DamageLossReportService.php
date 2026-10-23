<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\Client;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WastageEntry;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Authoritative, read-only calculation service for Damage & Loss Report (Reports V1).
 *
 * Computes two distinct financial loss classifications:
 * 1. Selling Below Cost: realized selling rate < attributable batch purchase cost.
 * 2. Unsellable Damage: physical wastage write-offs from warehouse inventory.
 *
 * Provides summary, transaction-level selling losses, physical damage logs,
 * daily & monthly reconciliation, and client/shop attribution with strict separation
 * of unallocated warehouse damage.
 */
final class DamageLossReportService
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

        $options = $this->getOptions();

        $baseCacheFilters = collect($filters)->except(['page', 'search'])->all();

        $cacheService = app(PurchaserReadCacheService::class);
        $rawReportContent = $cacheService->remember(
            scopes: ['orders', 'products'],
            dataset: 'damage_loss_report',
            ttlSeconds: 300,
            callback: fn () => $this->compileReport($product, $filters),
            tenantScope: PurchaserReadCacheService::DEFAULT_TENANT_SCOPE,
            filters: $baseCacheFilters,
        );

        $reportContent = $this->hydrateReportData($rawReportContent, $filters);

        return array_merge([
            'product' => $product,
            'searchResults' => $searchResults,
            'options' => $options,
            'filters' => $filters,
        ], $reportContent);
    }

    /**
     * Hydrate cached raw array data into Blade-ready objects and Collections.
     *
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function hydrateReportData(array $raw, array $filters = []): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = 5;

        $matrixCollection = collect($raw['productMatrix'] ?? []);
        if ($search !== '') {
            $s = mb_strtolower($search);
            $matrixCollection = $matrixCollection->filter(function ($item) use ($s) {
                $name = mb_strtolower((string) ($item['product_name'] ?? ''));
                $grade = mb_strtolower((string) ($item['grade_label'] ?? ''));

                return str_contains($name, $s) || str_contains($grade, $s);
            });
        }

        $total = $matrixCollection->count();
        $slice = $matrixCollection->slice(($page - 1) * $perPage, $perPage)->values();

        $paginatedProductMatrix = new LengthAwarePaginator(
            $slice->map(fn ($item) => (object) $item),
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        return [
            'summary' => (object) $raw['summary'],
            'sellingLossLines' => collect($raw['sellingLossLines'] ?? [])->map(fn ($item) => (object) $item),
            'damageEntries' => collect($raw['damageEntries'] ?? [])->map(fn ($item) => (object) $item),
            'dailySummary' => collect($raw['dailySummary'] ?? [])->map(fn ($item) => (object) $item),
            'monthlySummary' => collect($raw['monthlySummary'] ?? [])->map(fn ($item) => (object) $item),
            'clientShopSummary' => collect($raw['clientShopSummary'] ?? [])->map(fn ($item) => (object) $item),
            'unallocatedWarehouseDamage' => (object) ($raw['unallocatedWarehouseDamage'] ?? []),
            'productMatrix' => $paginatedProductMatrix,
            'productMatrixTotalCount' => $total,
        ];
    }

    /**
     * Filter options for dropdowns.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $shops = Shop::query()
            ->where('status', 'active')
            ->with('client:id,name,code')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'client_id']);

        $clients = Client::query()
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
            'shops' => $shops,
            'clients' => $clients,
            'grades' => $grades,
        ];
    }

    /**
     * Compile comprehensive report data across all sections.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function compileReport(?Product $product, array $filters): array
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $warehouseId = ! empty($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;
        $gradeFilter = ! empty($filters['grade']) && $filters['grade'] !== 'all' ? strtoupper((string) $filters['grade']) : 'all';
        $shopId = ! empty($filters['shop_id']) ? (int) $filters['shop_id'] : null;
        $clientId = ! empty($filters['client_id']) ? (int) $filters['client_id'] : null;

        // -------------------------------------------------------------
        // SECTION 1 & 2: SELLING BELOW COST TRANSACTIONS
        // -------------------------------------------------------------
        $sellingLossResults = $this->extractSellingLossTransactions(
            $product?->id,
            $startDate,
            $endDate,
            $warehouseId,
            $gradeFilter,
            $shopId,
            $clientId
        );

        $sellingLossLines = $sellingLossResults['lines'];
        $totalSellingLoss = $sellingLossResults['total_selling_loss'];
        $totalSoldBelowCostKg = $sellingLossResults['total_sold_below_cost_kg'];
        $pendingCostCount = $sellingLossResults['pending_cost_count'];
        $pendingCostQty = $sellingLossResults['pending_cost_qty'];

        // -------------------------------------------------------------
        // SECTION 1 & 3: UNSELLABLE DAMAGE ENTRIES (WastageEntry)
        // -------------------------------------------------------------
        $damageResults = $this->extractDamageEntries(
            $product?->id,
            $startDate,
            $endDate,
            $warehouseId,
            $gradeFilter,
            $shopId,
            $clientId
        );

        $damageEntries = $damageResults['entries'];
        $totalDamagedCost = $damageResults['total_damaged_cost'];
        $totalDamagedKg = $damageResults['total_damaged_kg'];
        $unallocatedWarehouseDamageCost = $damageResults['unallocated_warehouse_damage_cost'];
        $unallocatedWarehouseDamageKg = $damageResults['unallocated_warehouse_damage_kg'];

        // -------------------------------------------------------------
        // MAIN SUMMARY TOTALS
        // -------------------------------------------------------------
        $totalLoss = round($totalSellingLoss + $totalDamagedCost, 2);

        $summary = (object) [
            'total_selling_loss' => $totalSellingLoss,
            'total_sold_below_cost_kg' => $totalSoldBelowCostKg,
            'total_sold_below_cost_lines' => $sellingLossLines->count(),
            'total_damaged_cost' => $totalDamagedCost,
            'total_damaged_kg' => $totalDamagedKg,
            'total_damaged_entries' => $damageEntries->count(),
            'total_loss' => $totalLoss,
            'unallocated_warehouse_damage_cost' => $unallocatedWarehouseDamageCost,
            'unallocated_warehouse_damage_kg' => $unallocatedWarehouseDamageKg,
            'pending_cost_count' => $pendingCostCount,
            'pending_cost_qty' => $pendingCostQty,
            'has_pending_cost_reconciliation' => $pendingCostCount > 0,
        ];

        // -------------------------------------------------------------
        // SECTION 4: DAILY AND MONTHLY RECONCILIATION
        // -------------------------------------------------------------
        $reconciliation = $this->compileDailyAndMonthlyReconciliation(
            $sellingLossLines,
            $damageEntries
        );

        // -------------------------------------------------------------
        // SECTION 5: CLIENT / SHOP REPORT
        // -------------------------------------------------------------
        $clientShopData = $this->compileClientShopSummary(
            $sellingLossLines,
            $damageEntries,
            $unallocatedWarehouseDamageCost,
            $unallocatedWarehouseDamageKg
        );

        // -------------------------------------------------------------
        // PRODUCT & GRADE MATRIX SUMMARY
        // -------------------------------------------------------------
        $productMatrix = $this->compileProductMatrix(
            $sellingLossLines,
            $damageEntries
        );

        return [
            'summary' => (array) $summary,
            'sellingLossLines' => $sellingLossLines->map(fn ($l) => (array) $l)->all(),
            'damageEntries' => $damageEntries->map(fn ($e) => (array) $e)->all(),
            'dailySummary' => $reconciliation['daily']->map(fn ($d) => (array) $d)->all(),
            'monthlySummary' => $reconciliation['monthly']->map(fn ($m) => (array) $m)->all(),
            'clientShopSummary' => $clientShopData['clients']->map(fn ($c) => (array) $c)->all(),
            'unallocatedWarehouseDamage' => (array) $clientShopData['unallocated_damage'],
            'productMatrix' => $productMatrix->map(fn ($p) => (array) $p)->all(),
        ];
    }

    /**
     * Extract selling-below-cost lines by joining invoice lines with batch-level movement allocations.
     *
     * @return array{
     *     lines: Collection<int, object>,
     *     total_selling_loss: float,
     *     total_sold_below_cost_kg: float,
     *     pending_cost_count: int,
     *     pending_cost_qty: float
     * }
     */
    private function extractSellingLossTransactions(
        ?int $productId,
        ?string $startDate,
        ?string $endDate,
        ?int $warehouseId,
        string $gradeFilter,
        ?int $shopId,
        ?int $clientId
    ): array {
        // Pre-fetch latest authoritative non-negative purchase batches per product in a single bulk query
        $latestBatchesByProduct = StockBatch::query()
            ->where('reference', 'not like', 'NEG-%')
            ->where('cost_per_kg', '>', 0)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->orderByDesc('received_at')
            ->get(['id', 'product_id', 'warehouse_id', 'reference', 'cost_per_kg', 'received_at'])
            ->unique('product_id')
            ->keyBy('product_id');

        // Pre-fetch physical stock movements linked to order items in a single bulk query
        $movementsByOrderItem = StockMovement::query()
            ->whereNotNull('shop_order_item_id')
            ->join('stock_batches as sb', 'sb.id', '=', 'stock_movements.batch_id')
            ->select([
                'stock_movements.id',
                'stock_movements.shop_order_item_id',
                'stock_movements.batch_id',
                'stock_movements.warehouse_id',
                'stock_movements.type',
                'stock_movements.quantity',
                'stock_movements.cost_per_unit',
                'sb.reference as batch_reference',
                'sb.cost_per_kg as batch_cost_per_kg',
            ])
            ->get()
            ->groupBy('shop_order_item_id');

        // Stream billed shop invoice items via joined scalar query to avoid 512MB+ memory exhaustion
        $invoiceItemsQuery = ShopInvoiceItem::query()
            ->join('shop_invoices as si', 'si.id', '=', 'shop_invoice_items.shop_invoice_id')
            ->join('shops as s', 's.id', '=', 'si.shop_id')
            ->leftJoin('clients as c', 'c.id', '=', 's.client_id')
            ->join('products as p', 'p.id', '=', 'shop_invoice_items.product_id')
            ->where('si.status', '!=', 'cancelled')
            ->where('shop_invoice_items.delivered_qty', '>', 0);

        if ($startDate) {
            $invoiceItemsQuery->whereDate('si.business_date', '>=', $startDate);
        }
        if ($endDate) {
            $invoiceItemsQuery->whereDate('si.business_date', '<=', $endDate);
        }
        if ($shopId) {
            $invoiceItemsQuery->where('si.shop_id', $shopId);
        }
        if ($clientId) {
            $invoiceItemsQuery->where('s.client_id', $clientId);
        }
        if ($productId) {
            $invoiceItemsQuery->where('shop_invoice_items.product_id', $productId);
        }
        if ($gradeFilter !== 'all') {
            if ($gradeFilter === 'U') {
                $invoiceItemsQuery->whereIn('shop_invoice_items.product_grade', ['U', 'MIXED', 'UNSORTED']);
            } else {
                $invoiceItemsQuery->where('shop_invoice_items.product_grade', $gradeFilter);
            }
        }

        $invoiceItemsQuery->select([
            'shop_invoice_items.id',
            'shop_invoice_items.shop_order_item_id',
            'shop_invoice_items.product_id',
            'p.name as product_name',
            'p.unit as product_unit',
            'shop_invoice_items.product_grade',
            'shop_invoice_items.delivered_qty',
            'shop_invoice_items.final_line_total',
            'si.id as invoice_id',
            'si.invoice_number',
            'si.business_date',
            'si.subtotal',
            'si.final_total',
            's.id as shop_id',
            's.name as shop_name',
            's.code as shop_code',
            'c.id as client_id',
            'c.name as client_name',
        ]);

        $lines = collect();
        $totalSellingLoss = 0.0;
        $totalSoldBelowCostKg = 0.0;
        $pendingCostCount = 0;
        $pendingCostQty = 0.0;

        foreach ($invoiceItemsQuery->cursor() as $item) {
            $deliveredQty = (float) $item->delivered_qty;
            if ($deliveredQty <= 0.0001) {
                continue;
            }

            // Calculate actual net selling rate per unit after adjustments & proportional invoice discount
            $subtotal = (float) $item->subtotal;
            $finalTotal = (float) $item->final_total;
            $discountRatio = ($subtotal > 0.0001 && $finalTotal > 0.0001 && $subtotal >= $finalTotal)
                ? ($finalTotal / $subtotal)
                : 1.0;

            $netLineTotal = round((float) $item->final_line_total * $discountRatio, 2);
            $netSellingRate = round($netLineTotal / $deliveredQty, 4);

            // Fetch physical stock movements associated with this loadout line
            $movements = $item->shop_order_item_id ? ($movementsByOrderItem[$item->shop_order_item_id] ?? null) : null;

            if ($warehouseId !== null && $movements) {
                $movements = $movements->filter(fn ($m) => (int) $m->warehouse_id === $warehouseId);
            }

            // Deduct any sale reversals for accurate net consumption per batch
            $batchAllocations = [];
            if ($movements) {
                foreach ($movements as $m) {
                    $bId = (int) $m->batch_id;
                    if (! isset($batchAllocations[$bId])) {
                        $batchAllocations[$bId] = [
                            'batch_reference' => $m->batch_reference,
                            'batch_cost_per_kg' => (float) $m->batch_cost_per_kg,
                            'qty' => 0.0,
                            'unit_cost' => (float) $m->cost_per_unit,
                        ];
                    }

                    $isReversal = ($m->type === StockMovementType::SaleReversal || $m->type === 'sale_reversal');
                    if ($isReversal) {
                        $batchAllocations[$bId]['qty'] -= (float) $m->quantity;
                    } else {
                        $batchAllocations[$bId]['qty'] += (float) $m->quantity;
                    }
                }
            }

            // Filter out fully reversed batches
            $activeAllocations = array_filter($batchAllocations, fn ($alloc) => $alloc['qty'] > 0.0001);

            $dateStr = is_string($item->business_date)
                ? substr($item->business_date, 0, 10)
                : Carbon::parse($item->business_date)->toDateString();

            if (empty($activeAllocations)) {
                // Fallback: If no direct movements exist, check preloaded latest batch for product
                $latestBatch = $latestBatchesByProduct[$item->product_id] ?? null;

                if ($latestBatch) {
                    $unitCost = (float) $latestBatch->cost_per_kg;
                    $isPending = false;
                } else {
                    $unitCost = 0.0;
                    $isPending = true;
                }

                if ($isPending) {
                    $pendingCostCount++;
                    $pendingCostQty += $deliveredQty;
                } else {
                    $lossPerKg = max(0.0, round($unitCost - $netSellingRate, 4));
                    if ($lossPerKg > 0.0001) {
                        $lossAmount = round($lossPerKg * $deliveredQty, 2);
                        $totalSellingLoss += $lossAmount;
                        $totalSoldBelowCostKg += $deliveredQty;

                        $lines->push((object) [
                            'date' => $dateStr,
                            'invoice_number' => $item->invoice_number,
                            'shop_id' => $item->shop_id,
                            'shop_name' => $item->shop_name ?? 'Unknown Shop',
                            'shop_code' => $item->shop_code ?? '',
                            'client_id' => $item->client_id,
                            'client_name' => $item->client_name ?? 'Independent Shop',
                            'product_id' => $item->product_id,
                            'product_name' => $item->product_name ?? 'Product',
                            'product_unit' => $item->product_unit ?: 'kg',
                            'commercial_grade' => $item->product_grade,
                            'sold_qty' => $deliveredQty,
                            'batch_reference' => $latestBatch ? $latestBatch->reference : 'Weighted Average',
                            'purchase_cost' => $unitCost,
                            'net_selling_rate' => $netSellingRate,
                            'loss_per_kg' => $lossPerKg,
                            'total_loss' => $lossAmount,
                            'is_pending_cost' => false,
                        ]);
                    }
                }
            } else {
                // Support multiple purchase batches contributing to a single delivered product line
                foreach ($activeAllocations as $alloc) {
                    $allocQty = min((float) $alloc['qty'], $deliveredQty);
                    $unitCost = (float) ($alloc['batch_cost_per_kg'] > 0.0001 ? $alloc['batch_cost_per_kg'] : $alloc['unit_cost']);

                    $isPending = Str::startsWith((string) ($alloc['batch_reference'] ?? ''), 'NEG-') || $unitCost <= 0.0001;

                    if ($isPending) {
                        $pendingCostCount++;
                        $pendingCostQty += $allocQty;

                        continue;
                    }

                    $lossPerKg = max(0.0, round($unitCost - $netSellingRate, 4));
                    if ($lossPerKg > 0.0001) {
                        $lossAmount = round($lossPerKg * $allocQty, 2);
                        $totalSellingLoss += $lossAmount;
                        $totalSoldBelowCostKg += $allocQty;

                        $lines->push((object) [
                            'date' => $dateStr,
                            'invoice_number' => $item->invoice_number,
                            'shop_id' => $item->shop_id,
                            'shop_name' => $item->shop_name ?? 'Unknown Shop',
                            'shop_code' => $item->shop_code ?? '',
                            'client_id' => $item->client_id,
                            'client_name' => $item->client_name ?? 'Independent Shop',
                            'product_id' => $item->product_id,
                            'product_name' => $item->product_name ?? 'Product',
                            'product_unit' => $item->product_unit ?: 'kg',
                            'commercial_grade' => $item->product_grade,
                            'sold_qty' => round($allocQty, 3),
                            'batch_reference' => $alloc['batch_reference'] ?? 'BATCH',
                            'purchase_cost' => $unitCost,
                            'net_selling_rate' => $netSellingRate,
                            'loss_per_kg' => $lossPerKg,
                            'total_loss' => $lossAmount,
                            'is_pending_cost' => false,
                        ]);
                    }
                }
            }
        }

        return [
            'lines' => $lines->sortByDesc('date')->values(),
            'total_selling_loss' => round($totalSellingLoss, 2),
            'total_sold_below_cost_kg' => round($totalSoldBelowCostKg, 3),
            'pending_cost_count' => $pendingCostCount,
            'pending_cost_qty' => round($pendingCostQty, 3),
        ];
    }

    /**
     * Extract authoritative physical damage records from WastageEntry.
     *
     * @return array{
     *     entries: Collection<int, object>,
     *     total_damaged_cost: float,
     *     total_damaged_kg: float,
     *     unallocated_warehouse_damage_cost: float,
     *     unallocated_warehouse_damage_kg: float
     * }
     */
    private function extractDamageEntries(
        ?int $productId,
        ?string $startDate,
        ?string $endDate,
        ?int $warehouseId,
        string $gradeFilter,
        ?int $shopId,
        ?int $clientId
    ): array {
        $wastageQuery = WastageEntry::query()
            ->with([
                'product',
                'batch.warehouse',
                'recordedBy',
            ]);

        if ($productId) {
            $wastageQuery->where('product_id', $productId);
        }

        if ($startDate) {
            $wastageQuery->whereDate('wastage_date', '>=', $startDate);
        }
        if ($endDate) {
            $wastageQuery->whereDate('wastage_date', '<=', $endDate);
        }

        if ($warehouseId) {
            $wastageQuery->whereHas('batch', fn ($b) => $b->where('warehouse_id', $warehouseId));
        }

        if ($gradeFilter !== 'all') {
            if ($gradeFilter === 'U') {
                $wastageQuery->whereIn('grade', ['U', 'MIXED', 'UNSORTED', 'DMG']);
            } else {
                $wastageQuery->where('grade', $gradeFilter);
            }
        }

        $rawEntries = $wastageQuery->orderByDesc('wastage_date')->orderByDesc('id')->get();

        // Batch pre-fetch referenced orders to avoid N+1 queries
        $orderNumbers = [];
        foreach ($rawEntries as $we) {
            if (! empty($we->notes) && preg_match('/Order:\s*([A-Za-z0-9\-_]+)/i', $we->notes, $matches)) {
                $orderNumbers[] = $matches[1];
            }
        }

        $ordersMap = empty($orderNumbers) ? collect() : ShopOrder::query()
            ->whereIn('order_number', array_unique($orderNumbers))
            ->with('shop.client')
            ->get()
            ->keyBy('order_number');

        $entries = collect();
        $totalDamagedCost = 0.0;
        $totalDamagedKg = 0.0;
        $unallocatedWarehouseDamageCost = 0.0;
        $unallocatedWarehouseDamageKg = 0.0;

        foreach ($rawEntries as $we) {
            $qty = (float) $we->quantity;
            $unitCost = (float) $we->cost_per_kg;
            $entryCost = round($qty * $unitCost, 2);

            // Determine if this damage was directly attributable to a shop delivery review
            $orderNumber = null;
            $shop = null;
            $client = null;

            if (! empty($we->notes) && preg_match('/Order:\s*([A-Za-z0-9\-_]+)/i', $we->notes, $matches)) {
                $orderNumber = $matches[1];
                $order = $ordersMap[$orderNumber] ?? null;
                if ($order && $order->shop) {
                    $shop = $order->shop;
                    $client = $order->shop->client;
                }
            }

            // Apply shop or client filter if requested
            if ($shopId && (! $shop || (int) $shop->id !== $shopId)) {
                continue;
            }
            if ($clientId && (! $client || (int) $client->id !== $clientId)) {
                continue;
            }

            $isAttributable = ($shop !== null);

            $totalDamagedCost += $entryCost;
            $totalDamagedKg += $qty;

            if (! $isAttributable) {
                $unallocatedWarehouseDamageCost += $entryCost;
                $unallocatedWarehouseDamageKg += $qty;
            }

            $entries->push((object) [
                'id' => $we->id,
                'date' => $we->wastage_date->toDateString(),
                'product_id' => $we->product_id,
                'product_name' => $we->product?->name ?? 'Product',
                'product_unit' => $we->product?->unit ?: 'kg',
                'source_grade' => $we->grade?->value ?? (string) $we->grade,
                'source_grade_label' => ProductGrade::tryResolve($we->grade)?->label() ?? (string) $we->grade,
                'quantity' => round($qty, 3),
                'cost_per_kg' => round($unitCost, 2),
                'total_cost' => $entryCost,
                'warehouse_id' => $we->batch?->warehouse_id,
                'warehouse_name' => $we->batch?->warehouse?->name ?? 'Main Warehouse',
                'reason' => $we->reason?->label() ?? (string) ($we->reason?->value ?? 'Damage write-off'),
                'batch_reference' => $we->batch?->reference ?? 'N/A',
                'recorded_by' => $we->recordedBy?->name ?? 'System',
                'notes' => $we->notes,
                'is_client_attributable' => $isAttributable,
                'shop_id' => $shop?->id,
                'shop_name' => $shop?->name,
                'client_id' => $client?->id,
                'client_name' => $client?->name,
                'order_number' => $orderNumber,
            ]);
        }

        return [
            'entries' => $entries,
            'total_damaged_cost' => round($totalDamagedCost, 2),
            'total_damaged_kg' => round($totalDamagedKg, 3),
            'unallocated_warehouse_damage_cost' => round($unallocatedWarehouseDamageCost, 2),
            'unallocated_warehouse_damage_kg' => round($unallocatedWarehouseDamageKg, 3),
        ];
    }

    /**
     * Compile daily and monthly reconciliation ensuring daily sums equal monthly totals.
     *
     * @param  Collection<int, object>  $sellingLossLines
     * @param  Collection<int, object>  $damageEntries
     * @return array{
     *     daily: Collection<string, object>,
     *     monthly: Collection<string, object>
     * }
     */
    private function compileDailyAndMonthlyReconciliation(
        Collection $sellingLossLines,
        Collection $damageEntries
    ): array {
        // Collect all unique dates
        $allDates = $sellingLossLines->pluck('date')
            ->merge($damageEntries->pluck('date'))
            ->unique()
            ->sortDesc()
            ->values();

        $daily = collect();
        $monthlyAggregates = [];

        foreach ($allDates as $dateStr) {
            $daySellingLoss = round((float) $sellingLossLines->where('date', $dateStr)->sum('total_loss'), 2);
            $daySoldBelowCostQty = round((float) $sellingLossLines->where('date', $dateStr)->sum('sold_qty'), 3);
            $dayDamageCost = round((float) $damageEntries->where('date', $dateStr)->sum('total_cost'), 2);
            $dayDamagedQty = round((float) $damageEntries->where('date', $dateStr)->sum('quantity'), 3);
            $dayTotalLoss = round($daySellingLoss + $dayDamageCost, 2);

            $dateCarbon = Carbon::parse($dateStr);
            $monthKey = $dateCarbon->format('Y-m');

            $daily->push((object) [
                'date' => $dateStr,
                'formatted_date' => $dateCarbon->format('d M Y'),
                'selling_loss' => $daySellingLoss,
                'sold_below_cost_qty' => $daySoldBelowCostQty,
                'damage_cost' => $dayDamageCost,
                'damaged_qty' => $dayDamagedQty,
                'total_loss' => $dayTotalLoss,
            ]);

            if (! isset($monthlyAggregates[$monthKey])) {
                $monthlyAggregates[$monthKey] = [
                    'month' => $monthKey,
                    'month_label' => $dateCarbon->format('F Y'),
                    'selling_loss' => 0.0,
                    'sold_below_cost_qty' => 0.0,
                    'damage_cost' => 0.0,
                    'damaged_qty' => 0.0,
                    'total_loss' => 0.0,
                ];
            }

            $monthlyAggregates[$monthKey]['selling_loss'] += $daySellingLoss;
            $monthlyAggregates[$monthKey]['sold_below_cost_qty'] += $daySoldBelowCostQty;
            $monthlyAggregates[$monthKey]['damage_cost'] += $dayDamageCost;
            $monthlyAggregates[$monthKey]['damaged_qty'] += $dayDamagedQty;
            $monthlyAggregates[$monthKey]['total_loss'] += $dayTotalLoss;
        }

        $monthly = collect($monthlyAggregates)->map(fn ($m) => (object) [
            'month' => $m['month'],
            'month_label' => $m['month_label'],
            'selling_loss' => round($m['selling_loss'], 2),
            'sold_below_cost_qty' => round($m['sold_below_cost_qty'], 3),
            'damage_cost' => round($m['damage_cost'], 2),
            'damaged_qty' => round($m['damaged_qty'], 3),
            'total_loss' => round($m['total_loss'], 2),
        ])->sortByDesc('month')->values();

        return [
            'daily' => $daily,
            'monthly' => $monthly,
        ];
    }

    /**
     * Group client and shop performance, keeping unallocated warehouse damage strictly segregated.
     *
     * @param  Collection<int, object>  $sellingLossLines
     * @param  Collection<int, object>  $damageEntries
     * @return array{
     *     clients: Collection<int, object>,
     *     unallocated_damage: object
     * }
     */
    private function compileClientShopSummary(
        Collection $sellingLossLines,
        Collection $damageEntries,
        float $unallocatedDamageCost,
        float $unallocatedDamageKg
    ): array {
        $clientsMap = [];

        // Aggregate selling loss by shop
        foreach ($sellingLossLines as $line) {
            $sId = (int) $line->shop_id;
            if (! isset($clientsMap[$sId])) {
                $clientsMap[$sId] = [
                    'shop_id' => $sId,
                    'shop_name' => $line->shop_name,
                    'shop_code' => $line->shop_code,
                    'client_id' => $line->client_id,
                    'client_name' => $line->client_name,
                    'sold_below_cost_qty' => 0.0,
                    'selling_loss' => 0.0,
                    'attributable_damage_qty' => 0.0,
                    'attributable_damage_cost' => 0.0,
                ];
            }

            $clientsMap[$sId]['sold_below_cost_qty'] += $line->sold_qty;
            $clientsMap[$sId]['selling_loss'] += $line->total_loss;
        }

        // Aggregate directly attributable damage (e.g. delivery review wastage)
        foreach ($damageEntries as $entry) {
            if ($entry->is_client_attributable && $entry->shop_id) {
                $sId = (int) $entry->shop_id;
                if (! isset($clientsMap[$sId])) {
                    $clientsMap[$sId] = [
                        'shop_id' => $sId,
                        'shop_name' => $entry->shop_name ?? 'Shop',
                        'shop_code' => '',
                        'client_id' => $entry->client_id,
                        'client_name' => $entry->client_name ?? 'Client',
                        'sold_below_cost_qty' => 0.0,
                        'selling_loss' => 0.0,
                        'attributable_damage_qty' => 0.0,
                        'attributable_damage_cost' => 0.0,
                    ];
                }

                $clientsMap[$sId]['attributable_damage_qty'] += $entry->quantity;
                $clientsMap[$sId]['attributable_damage_cost'] += $entry->total_cost;
            }
        }

        $clients = collect($clientsMap)->map(function ($c): object {
            $totalLoss = round($c['selling_loss'] + $c['attributable_damage_cost'], 2);

            return (object) [
                'shop_id' => $c['shop_id'],
                'shop_name' => $c['shop_name'],
                'shop_code' => $c['shop_code'],
                'client_id' => $c['client_id'],
                'client_name' => $c['client_name'],
                'sold_below_cost_qty' => round($c['sold_below_cost_qty'], 3),
                'selling_loss' => round($c['selling_loss'], 2),
                'attributable_damage_qty' => round($c['attributable_damage_qty'], 3),
                'attributable_damage_cost' => round($c['attributable_damage_cost'], 2),
                'total_client_loss' => $totalLoss,
            ];
        })->sortByDesc('total_client_loss')->values();

        $unallocated = (object) [
            'quantity' => round($unallocatedDamageKg, 3),
            'cost' => round($unallocatedDamageCost, 2),
        ];

        return [
            'clients' => $clients,
            'unallocated_damage' => $unallocated,
        ];
    }

    /**
     * Compile matrix of losses grouped by product and commercial grade.
     *
     * @param  Collection<int, object>  $sellingLossLines
     * @param  Collection<int, object>  $damageEntries
     * @return Collection<string, object>
     */
    private function compileProductMatrix(
        Collection $sellingLossLines,
        Collection $damageEntries
    ): Collection {
        $matrix = [];

        foreach ($sellingLossLines as $line) {
            $key = $line->product_id.'-'.$line->commercial_grade;
            if (! isset($matrix[$key])) {
                $matrix[$key] = [
                    'product_id' => $line->product_id,
                    'product_name' => $line->product_name,
                    'product_unit' => $line->product_unit,
                    'grade' => $line->commercial_grade,
                    'grade_label' => ProductGrade::tryResolve($line->commercial_grade)?->label() ?? $line->commercial_grade,
                    'sold_below_cost_qty' => 0.0,
                    'selling_loss' => 0.0,
                    'damaged_qty' => 0.0,
                    'damaged_cost' => 0.0,
                ];
            }

            $matrix[$key]['sold_below_cost_qty'] += $line->sold_qty;
            $matrix[$key]['selling_loss'] += $line->total_loss;
        }

        foreach ($damageEntries as $entry) {
            $key = $entry->product_id.'-'.$entry->source_grade;
            if (! isset($matrix[$key])) {
                $matrix[$key] = [
                    'product_id' => $entry->product_id,
                    'product_name' => $entry->product_name,
                    'product_unit' => $entry->product_unit,
                    'grade' => $entry->source_grade,
                    'grade_label' => $entry->source_grade_label,
                    'sold_below_cost_qty' => 0.0,
                    'selling_loss' => 0.0,
                    'damaged_qty' => 0.0,
                    'damaged_cost' => 0.0,
                ];
            }

            $matrix[$key]['damaged_qty'] += $entry->quantity;
            $matrix[$key]['damaged_cost'] += $entry->total_cost;
        }

        return collect($matrix)->map(function ($row): object {
            $totalLoss = round($row['selling_loss'] + $row['damaged_cost'], 2);

            return (object) [
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'],
                'product_unit' => $row['product_unit'],
                'grade' => $row['grade'],
                'grade_label' => $row['grade_label'],
                'sold_below_cost_qty' => round($row['sold_below_cost_qty'], 3),
                'selling_loss' => round($row['selling_loss'], 2),
                'damaged_qty' => round($row['damaged_qty'], 3),
                'damaged_cost' => round($row['damaged_cost'], 2),
                'total_loss' => $totalLoss,
            ];
        })->sortByDesc('total_loss')->values();
    }
}
