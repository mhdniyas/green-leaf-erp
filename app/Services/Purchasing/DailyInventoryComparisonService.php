<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\WarehouseReceiptReadScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\AdvanceReceiveMatch;

class DailyInventoryComparisonService
{
    /**
     * Build day-wise comparison rows between Advance receipts and Purchase Bills for a given date.
     *
     * Strict Day-Wise Rule:
     * - Advance date = Bill date = selected date
     * - Advance items filtered by goods_received.warehouse_id
     * - Bill items filtered by product.default_warehouse_id
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function buildComparisonRows(string $date, ?int $selectedWarehouseId, ?array $authorizedWarehouseIds = null): Collection
    {
        // 1. Query all Advance received items for the selected date (filtered by goods_received.warehouse_id)
        $advanceItems = GoodsReceivedItem::query()
            ->whereHas('goodsReceived', function (Builder $q) use ($date, $selectedWarehouseId, $authorizedWarehouseIds): void {
                $q->where('receipt_type', 'warehouse_advance')
                    ->where('status', '!=', 'cancelled')
                    ->whereDate('received_at', $date)
                    ->when($selectedWarehouseId !== null, fn (Builder $wq) => $wq->where('warehouse_id', $selectedWarehouseId))
                    ->when($selectedWarehouseId === null && $authorizedWarehouseIds !== null, fn (Builder $wq) => $wq->whereIn('warehouse_id', $authorizedWarehouseIds));
            })
            ->with(['product', 'goodsReceived'])
            ->get();

        // 2. Query all normal Purchase Bill received items for the selected date (filtered by product.default_warehouse_id)
        $billItems = GoodsReceivedItem::query()
            ->whereHas('goodsReceived', function (Builder $q) use ($date): void {
                $q->where(function (Builder $sub): void {
                    $sub->where('receipt_type', '!=', 'warehouse_advance')
                        ->orWhereNull('receipt_type');
                })
                    ->where('status', '!=', 'cancelled')
                    ->whereDate('received_at', $date);
            })
            ->when($selectedWarehouseId !== null, function (Builder $q) use ($selectedWarehouseId): void {
                $q->whereHas('product', fn (Builder $pq) => $pq->where('default_warehouse_id', $selectedWarehouseId));
            })
            ->when($selectedWarehouseId === null && $authorizedWarehouseIds !== null, function (Builder $q) use ($authorizedWarehouseIds): void {
                $q->whereHas('product', fn (Builder $pq) => $pq->whereIn('default_warehouse_id', $authorizedWarehouseIds));
            })
            ->with(['product', 'goodsReceived'])
            ->get();

        $allProductIds = $advanceItems->pluck('product_id')->merge($billItems->pluck('product_id'))->unique();
        $products = Product::query()->whereIn('id', $allProductIds)->get()->keyBy('id');

        // Preload matches for all advance and bill items on this date
        $advItemIds = $advanceItems->pluck('id')->all();
        $billItemIds = $billItems->pluck('id')->all();

        $existingMatches = AdvanceReceiveMatch::query()
            ->where(function (Builder $mq) use ($advItemIds, $billItemIds): void {
                $mq->whereIn('advance_goods_received_item_id', $advItemIds)
                    ->orWhereIn('bill_goods_received_item_id', $billItemIds);
            })
            ->get();

        $stockCollection = app(StockMovementRepository::class)->currentStockByProductAndGrade(null, $selectedWarehouseId);
        $stockByProductId = $stockCollection->groupBy('product_id')->map(fn ($items): float => (float) collect($items)->sum('current_stock'));

        $rows = [];

        $formatNumber = static function (float $val): string {
            return (abs($val - (int) $val) < 0.0001)
                ? (string) (int) $val
                : rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.');
        };

        foreach ($allProductIds as $productId) {
            /** @var Product|null $product */
            $product = $products->get($productId);
            $prodAdvItems = $advanceItems->where('product_id', $productId);
            $prodBillItems = $billItems->where('product_id', $productId);

            $stockBalance = round((float) ($stockByProductId->get($productId) ?? 0.0), 2);
            $stockUnit = $product?->unit ?? 'kg';
            $formattedStockBalance = $formatNumber($stockBalance).' '.$stockUnit;

            // Group advance items by unit
            $advByUnit = [];
            foreach ($prodAdvItems as $item) {
                $unit = trim((string) ($item->received_unit ?: ($product?->unit ?? 'kg')));
                $uKey = mb_strtolower($unit);
                if (! isset($advByUnit[$uKey])) {
                    $advByUnit[$uKey] = ['unit' => $unit, 'qty' => 0.0, 'items' => []];
                }
                $advByUnit[$uKey]['qty'] += (float) $item->received_qty;
                $advByUnit[$uKey]['items'][] = [
                    'id' => $item->id,
                    'grn_number' => $item->goodsReceived?->grn_number ?? 'GRN-'.$item->goods_received_id,
                    'type' => 'Advance',
                    'qty' => (float) $item->received_qty,
                    'unit' => $unit,
                ];
            }

            // Group bill items by unit
            $billByUnit = [];
            foreach ($prodBillItems as $item) {
                $unit = trim((string) ($item->received_unit ?: ($product?->unit ?? 'kg')));
                $uKey = mb_strtolower($unit);
                if (! isset($billByUnit[$uKey])) {
                    $billByUnit[$uKey] = ['unit' => $unit, 'qty' => 0.0, 'items' => []];
                }
                $billByUnit[$uKey]['qty'] += (float) $item->received_qty;
                $billByUnit[$uKey]['items'][] = [
                    'id' => $item->id,
                    'grn_number' => $item->goodsReceived?->grn_number ?? 'GRN-'.$item->goods_received_id,
                    'type' => 'Bill',
                    'qty' => (float) $item->received_qty,
                    'unit' => $unit,
                ];
            }

            $advUnitKeys = array_keys($advByUnit);
            $billUnitKeys = array_keys($billByUnit);

            $matchingUnitKeys = array_intersect($advUnitKeys, $billUnitKeys);
            $unmatchedAdvKeys = array_diff($advUnitKeys, $matchingUnitKeys);
            $unmatchedBillKeys = array_diff($billUnitKeys, $matchingUnitKeys);

            // 1. Process exact matching units
            foreach ($matchingUnitKeys as $uKey) {
                $advData = $advByUnit[$uKey];
                $billData = $billByUnit[$uKey];
                $unit = $advData['unit'];

                $advQty = (float) $advData['qty'];
                $billQty = (float) $billData['qty'];
                $diff = $advQty - $billQty;

                $advItemIdsInGroup = collect($advData['items'])->pluck('id')->all();
                $billItemIdsInGroup = collect($billData['items'])->pluck('id')->all();

                $matchedAdvQty = (float) $existingMatches->whereIn('advance_goods_received_item_id', $advItemIdsInGroup)->sum('matched_qty');
                $matchedBillQty = (float) $existingMatches->whereIn('bill_goods_received_item_id', $billItemIdsInGroup)->sum('matched_qty');

                $unmatchedAdvQty = max(0.0, round($advQty - $matchedAdvQty, 3));
                $unmatchedBillQty = max(0.0, round($billQty - $matchedBillQty, 3));

                $matchPct = $billQty > 0 ? ($matchedBillQty / $billQty) * 100 : 0.0;

                if (abs($diff) < 0.0001) {
                    $formattedDiff = '0';
                } else {
                    $prefix = $diff > 0 ? '+' : '';
                    $formattedDiff = $prefix.$formatNumber($diff).' '.$unit;
                }

                $actionType = 'none';
                if ($billQty > 0 && $unmatchedBillQty <= 0.0001) {
                    $actionType = 'matched';
                } elseif ($unmatchedAdvQty > 0.0001 && $unmatchedBillQty > 0.0001) {
                    $actionType = $matchedBillQty <= 0.0001 ? 'match' : 'match_remaining';
                }

                $rows[] = [
                    'product_id' => $productId,
                    'product_name' => $product?->name ?? 'Unknown Product',
                    'sku' => $product?->sku ?? '',
                    'product_code' => $product?->sku ?? '',
                    'unit' => $unit,
                    'base_unit' => $unit,
                    'advance_qty' => $advQty,
                    'advance_base_qty' => $advQty,
                    'physical_base_qty' => $advQty,
                    'bill_qty' => $billQty,
                    'billed_base_qty' => $billQty,
                    'formatted_advance' => $formatNumber($advQty).' '.$unit,
                    'formatted_bill' => $formatNumber($billQty).' '.$unit,
                    'diff' => $diff,
                    'difference_base_qty' => $diff,
                    'difference_type' => $diff > 0.0001 ? 'excess' : ($diff < -0.0001 ? 'short' : 'balanced'),
                    'formatted_diff' => $formattedDiff,
                    'matched_bill_qty' => $matchedBillQty,
                    'unmatched_bill_qty' => $unmatchedBillQty,
                    'bill_pending' => $unmatchedBillQty,
                    'unmatched_adv_qty' => $unmatchedAdvQty,
                    'match_pct' => $matchPct,
                    'formatted_match_pct' => round($matchPct).'%',
                    'stock_balance' => $stockBalance,
                    'stock_unit' => $stockUnit,
                    'formatted_stock_balance' => $formattedStockBalance,
                    'inventory_balance' => $stockBalance,
                    'inventory_unit' => $stockUnit,
                    'formatted_inventory_balance' => $formattedStockBalance,
                    'unit_mismatch' => false,
                    'action_type' => $actionType,
                    'editable_items' => array_merge($advData['items'], $billData['items']),
                ];
            }

            // 2. Process unmatched: If both unmatched advance and unmatched bill exist, it's a UNIT MISMATCH
            if (! empty($unmatchedAdvKeys) && ! empty($unmatchedBillKeys)) {
                $advQtyTotal = 0.0;
                $advUnitNames = [];
                $advItemsList = [];
                foreach ($unmatchedAdvKeys as $uKey) {
                    $advQtyTotal += $advByUnit[$uKey]['qty'];
                    $advUnitNames[] = $advByUnit[$uKey]['unit'];
                    $advItemsList = array_merge($advItemsList, $advByUnit[$uKey]['items']);
                }

                $billQtyTotal = 0.0;
                $billUnitNames = [];
                $billItemsList = [];
                foreach ($unmatchedBillKeys as $uKey) {
                    $billQtyTotal += $billByUnit[$uKey]['qty'];
                    $billUnitNames[] = $billByUnit[$uKey]['unit'];
                    $billItemsList = array_merge($billItemsList, $billByUnit[$uKey]['items']);
                }

                $advUnitStr = implode('/', array_unique($advUnitNames));
                $billUnitStr = implode('/', array_unique($billUnitNames));

                $rows[] = [
                    'product_id' => $productId,
                    'product_name' => $product?->name ?? 'Unknown Product',
                    'sku' => $product?->sku ?? '',
                    'product_code' => $product?->sku ?? '',
                    'unit' => $advUnitStr.' vs '.$billUnitStr,
                    'base_unit' => $advUnitStr.' vs '.$billUnitStr,
                    'advance_qty' => $advQtyTotal,
                    'advance_base_qty' => $advQtyTotal,
                    'physical_base_qty' => $advQtyTotal,
                    'bill_qty' => $billQtyTotal,
                    'billed_base_qty' => $billQtyTotal,
                    'formatted_advance' => $formatNumber($advQtyTotal).' '.$advUnitStr,
                    'formatted_bill' => $formatNumber($billQtyTotal).' '.$billUnitStr,
                    'diff' => null,
                    'difference_base_qty' => 0.0,
                    'difference_type' => 'mismatch',
                    'formatted_diff' => 'Unit Mismatch',
                    'matched_bill_qty' => 0.0,
                    'unmatched_bill_qty' => $billQtyTotal,
                    'bill_pending' => $billQtyTotal,
                    'unmatched_adv_qty' => $advQtyTotal,
                    'match_pct' => null,
                    'formatted_match_pct' => '--',
                    'stock_balance' => $stockBalance,
                    'stock_unit' => $stockUnit,
                    'formatted_stock_balance' => $formattedStockBalance,
                    'inventory_balance' => $stockBalance,
                    'inventory_unit' => $stockUnit,
                    'formatted_inventory_balance' => $formattedStockBalance,
                    'unit_mismatch' => true,
                    'action_type' => 'fix_unit',
                    'editable_items' => array_merge($advItemsList, $billItemsList),
                ];
            } else {
                // Unmatched Advance only (No bill with this unit)
                foreach ($unmatchedAdvKeys as $uKey) {
                    $advData = $advByUnit[$uKey];
                    $unit = $advData['unit'];
                    $advQty = (float) $advData['qty'];
                    $diff = $advQty;

                    $rows[] = [
                        'product_id' => $productId,
                        'product_name' => $product?->name ?? 'Unknown Product',
                        'sku' => $product?->sku ?? '',
                        'product_code' => $product?->sku ?? '',
                        'unit' => $unit,
                        'base_unit' => $unit,
                        'advance_qty' => $advQty,
                        'advance_base_qty' => $advQty,
                        'physical_base_qty' => $advQty,
                        'bill_qty' => 0.0,
                        'billed_base_qty' => 0.0,
                        'formatted_advance' => $formatNumber($advQty).' '.$unit,
                        'formatted_bill' => '0 '.$unit,
                        'diff' => $diff,
                        'formatted_diff' => '+'.$formatNumber($diff).' '.$unit,
                        'matched_bill_qty' => 0.0,
                        'unmatched_bill_qty' => 0.0,
                        'bill_pending' => 0.0,
                        'unmatched_adv_qty' => $advQty,
                        'match_pct' => 0.0,
                        'formatted_match_pct' => '0%',
                        'stock_balance' => $stockBalance,
                        'stock_unit' => $stockUnit,
                        'formatted_stock_balance' => $formattedStockBalance,
                        'inventory_balance' => $stockBalance,
                        'inventory_unit' => $stockUnit,
                        'formatted_inventory_balance' => $formattedStockBalance,
                        'unit_mismatch' => false,
                        'action_type' => 'none',
                        'editable_items' => $advData['items'],
                    ];
                }

                // Unmatched Bill only (No advance with this unit)
                foreach ($unmatchedBillKeys as $uKey) {
                    $billData = $billByUnit[$uKey];
                    $unit = $billData['unit'];
                    $billQty = (float) $billData['qty'];
                    $diff = -$billQty;

                    $rows[] = [
                        'product_id' => $productId,
                        'product_name' => $product?->name ?? 'Unknown Product',
                        'sku' => $product?->sku ?? '',
                        'product_code' => $product?->sku ?? '',
                        'unit' => $unit,
                        'base_unit' => $unit,
                        'advance_qty' => 0.0,
                        'advance_base_qty' => 0.0,
                        'physical_base_qty' => 0.0,
                        'bill_qty' => $billQty,
                        'billed_base_qty' => $billQty,
                        'formatted_advance' => '0 '.$unit,
                        'formatted_bill' => $formatNumber($billQty).' '.$unit,
                        'diff' => $diff,
                        'formatted_diff' => '-'.$formatNumber($billQty).' '.$unit,
                        'matched_bill_qty' => 0.0,
                        'unmatched_bill_qty' => $billQty,
                        'bill_pending' => $billQty,
                        'unmatched_adv_qty' => 0.0,
                        'match_pct' => 0.0,
                        'formatted_match_pct' => '0%',
                        'stock_balance' => $stockBalance,
                        'stock_unit' => $stockUnit,
                        'formatted_stock_balance' => $formattedStockBalance,
                        'inventory_balance' => $stockBalance,
                        'inventory_unit' => $stockUnit,
                        'formatted_inventory_balance' => $formattedStockBalance,
                        'unit_mismatch' => false,
                        'action_type' => 'none',
                        'editable_items' => $billData['items'],
                    ];
                }
            }
        }

        return collect($rows)->sortBy(function (array $row): string {
            $sku = (string) ($row['product_code'] ?: ($row['sku'] ?? ''));

            return Product::sortableSku($sku);
        })->values();
    }

    /**
     * Compute full summary metrics for the comparison rows.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function calculateSummary(Collection $rows): array
    {
        $totalAdvance = (float) $rows->sum('advance_qty');
        $totalBill = (float) $rows->sum('bill_qty');
        $totalMatched = (float) $rows->sum('matched_bill_qty');
        $totalUnmatchedBill = (float) $rows->sum('unmatched_bill_qty');
        $unitFixCount = $rows->where('unit_mismatch', true)->count();
        $overallMatchPct = $totalBill > 0 ? round(($totalMatched / $totalBill) * 100, 1) : 0.0;

        // Build unit-wise breakdown for Advance, Bill, Matched, and Pending
        $advanceUnitMap = [];
        $billUnitMap = [];
        $matchedUnitMap = [];
        $pendingUnitMap = [];

        foreach ($rows as $row) {
            $unit = trim((string) ($row['unit'] ?? 'kg'));
            if ($row['unit_mismatch']) {
                continue;
            }
            if (($row['advance_qty'] ?? 0) > 0) {
                $advanceUnitMap[$unit] = ($advanceUnitMap[$unit] ?? 0.0) + (float) $row['advance_qty'];
            }
            if (($row['bill_qty'] ?? 0) > 0) {
                $billUnitMap[$unit] = ($billUnitMap[$unit] ?? 0.0) + (float) $row['bill_qty'];
            }
            if (($row['matched_bill_qty'] ?? 0) > 0) {
                $matchedUnitMap[$unit] = ($matchedUnitMap[$unit] ?? 0.0) + (float) $row['matched_bill_qty'];
            }
            if (($row['unmatched_bill_qty'] ?? 0) > 0) {
                $pendingUnitMap[$unit] = ($pendingUnitMap[$unit] ?? 0.0) + (float) $row['unmatched_bill_qty'];
            }
        }

        return [
            'total_advance_qty' => round($totalAdvance, 2),
            'total_bill_qty' => round($totalBill, 2),
            'total_matched_qty' => round($totalMatched, 2),
            'total_unmatched_bill_qty' => round($totalUnmatchedBill, 2),
            'total_pending_bill_qty' => round($totalUnmatchedBill, 2),
            'unit_fix_count' => $unitFixCount,
            'overall_match_pct' => $overallMatchPct,
            'advance_units' => $advanceUnitMap,
            'bill_units' => $billUnitMap,
            'matched_units' => $matchedUnitMap,
            'pending_units' => $pendingUnitMap,
        ];
    }

    /**
     * Sort comparison rows by a specified column and direction.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function sortComparisonRows(Collection $rows, string $sortBy, string $sortDir = 'asc'): Collection
    {
        $isDesc = strtolower($sortDir) === 'desc';

        return $rows->sort(function (array $a, array $b) use ($sortBy, $isDesc): int {
            $res = 0;

            if (in_array($sortBy, ['product_code', 'code', 'sku'], true)) {
                $codeA = trim((string) ($a['product_code'] ?: ($a['sku'] ?? '')));
                $codeB = trim((string) ($b['product_code'] ?: ($b['sku'] ?? '')));

                $sortA = Product::sortableSku($codeA);
                $sortB = Product::sortableSku($codeB);

                $res = strcmp($sortA, $sortB);
                if ($res === 0) {
                    $res = strcasecmp((string) ($a['product_name'] ?? ''), (string) ($b['product_name'] ?? ''));
                }
            } elseif (in_array($sortBy, ['product_name', 'name', 'product'], true)) {
                $res = strcasecmp((string) ($a['product_name'] ?? ''), (string) ($b['product_name'] ?? ''));
                if ($res === 0) {
                    $sortA = Product::sortableSku(trim((string) ($a['product_code'] ?: ($a['sku'] ?? ''))));
                    $sortB = Product::sortableSku(trim((string) ($b['product_code'] ?: ($b['sku'] ?? ''))));
                    $res = strcmp($sortA, $sortB);
                }
            } elseif (in_array($sortBy, ['advance_qty', 'advance'], true)) {
                $valA = (float) ($a['advance_qty'] ?? 0);
                $valB = (float) ($b['advance_qty'] ?? 0);
                $res = $valA <=> $valB;
            } elseif (in_array($sortBy, ['bill_qty', 'bill'], true)) {
                $valA = (float) ($a['bill_qty'] ?? 0);
                $valB = (float) ($b['bill_qty'] ?? 0);
                $res = $valA <=> $valB;
            } elseif ($sortBy === 'diff') {
                $valA = (float) ($a['diff'] ?? 0);
                $valB = (float) ($b['diff'] ?? 0);
                $res = $valA <=> $valB;
            } elseif (in_array($sortBy, ['matched_bill_qty', 'matched_qty', 'matched'], true)) {
                $valA = (float) ($a['matched_bill_qty'] ?? 0);
                $valB = (float) ($b['matched_bill_qty'] ?? 0);
                $res = $valA <=> $valB;
            } elseif (in_array($sortBy, ['unmatched_bill_qty', 'pending_qty', 'pending'], true)) {
                $valA = (float) ($a['unmatched_bill_qty'] ?? 0);
                $valB = (float) ($b['unmatched_bill_qty'] ?? 0);
                $res = $valA <=> $valB;
            } elseif (in_array($sortBy, ['match_pct', 'match'], true)) {
                $valA = isset($a['match_pct']) && $a['match_pct'] !== null ? (float) $a['match_pct'] : -1.0;
                $valB = isset($b['match_pct']) && $b['match_pct'] !== null ? (float) $b['match_pct'] : -1.0;
                $res = $valA <=> $valB;
            } elseif (in_array($sortBy, ['stock_balance', 'inv_stock', 'stock', 'inventory_balance'], true)) {
                $valA = (float) ($a['stock_balance'] ?? 0);
                $valB = (float) ($b['stock_balance'] ?? 0);
                $res = $valA <=> $valB;
            } else {
                $sortA = Product::sortableSku(trim((string) ($a['product_code'] ?: ($a['sku'] ?? ''))));
                $sortB = Product::sortableSku(trim((string) ($b['product_code'] ?: ($b['sku'] ?? ''))));
                $res = strcmp($sortA, $sortB);
            }

            return $isDesc ? -$res : $res;
        })->values();
    }

    /**
     * Filter and Paginate comparison rows.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     */
    public function paginateComparisonRows(Collection $rows, array $filters = [], int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $matchFilter = (string) ($filters['match_filter'] ?? 'all');
        $sortBy = (string) ($filters['sort_by'] ?? $filters['sort'] ?? 'product_code');
        $sortDir = (string) ($filters['sort_dir'] ?? $filters['direction'] ?? 'asc');

        $filtered = $rows;

        if ($search !== '') {
            $lowerSearch = mb_strtolower($search);
            $filtered = $filtered->filter(function (array $r) use ($lowerSearch): bool {
                $name = mb_strtolower((string) ($r['product_name'] ?? ''));
                $code = mb_strtolower((string) ($r['product_code'] ?: ($r['sku'] ?? '')));

                return str_contains($name, $lowerSearch) || str_contains($code, $lowerSearch);
            })->values();
        }

        if ($matchFilter === 'unmatched') {
            $filtered = $filtered->filter(function (array $r): bool {
                return $r['unit_mismatch'] || ($r['match_pct'] ?? 0) < 99.99 || ($r['unmatched_bill_qty'] ?? 0) > 0.0001 || ($r['unmatched_adv_qty'] ?? 0) > 0.0001 || abs((float) ($r['diff'] ?? 0)) > 0.0001;
            })->values();
        } elseif ($matchFilter === 'matched') {
            $filtered = $filtered->filter(function (array $r): bool {
                return ! $r['unit_mismatch'] && ($r['match_pct'] ?? 0) >= 99.99 && ($r['unmatched_bill_qty'] ?? 0) <= 0.0001 && ($r['unmatched_adv_qty'] ?? 0) <= 0.0001 && abs((float) ($r['diff'] ?? 0)) <= 0.0001;
            })->values();
        }

        $sorted = $this->sortComparisonRows($filtered, $sortBy, $sortDir);

        if ($perPage <= 0) {
            $perPage = max(1, $sorted->count());
        }

        $sliced = $sorted->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $sliced,
            $sorted->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
    }

    /**
     * Perform FIFO inventory matching for a specific product and unit within the selected date.
     *
     * @return array<string, mixed>
     */
    public function executeDayInventoryMatch(
        string $date,
        int $productId,
        string $unit,
        ?int $warehouseId,
        ?array $authorizedWarehouseIds,
        int $userId
    ): array {
        /** @var Product $product */
        $product = Product::findOrFail($productId);
        $normalizedUnit = ProductUnit::normalizeUnit($unit);

        // 1. Fetch same-day Advance items
        $advanceItems = GoodsReceivedItem::query()
            ->where('product_id', $productId)
            ->whereHas('goodsReceived', function (Builder $q) use ($date, $warehouseId, $authorizedWarehouseIds): void {
                $q->where('receipt_type', 'warehouse_advance')
                    ->where('status', '!=', 'cancelled')
                    ->whereDate('received_at', $date)
                    ->when($warehouseId !== null, fn (Builder $wq) => $wq->where('warehouse_id', $warehouseId))
                    ->when($warehouseId === null && $authorizedWarehouseIds !== null, fn (Builder $wq) => $wq->whereIn('warehouse_id', $authorizedWarehouseIds));
            })
            ->with(['goodsReceived.stockBatches'])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->filter(fn (GoodsReceivedItem $item): bool => ProductUnit::normalizeUnit((string) $item->received_unit) === $normalizedUnit);

        // 2. Fetch same-day RECEIVED Bill items (must be approved / received)
        $billItems = GoodsReceivedItem::query()
            ->where('product_id', $productId)
            ->whereHas('goodsReceived', function (Builder $q) use ($date): void {
                $q->where(function (Builder $sub): void {
                    $sub->where('receipt_type', '!=', 'warehouse_advance')
                        ->orWhereNull('receipt_type');
                })
                    ->where('status', '!=', 'cancelled')
                    ->whereDate('received_at', $date);
            })
            ->when($warehouseId !== null, function (Builder $q) use ($warehouseId): void {
                $q->whereHas('product', fn (Builder $pq) => $pq->where('default_warehouse_id', $warehouseId));
            })
            ->when($warehouseId === null && $authorizedWarehouseIds !== null, function (Builder $q) use ($authorizedWarehouseIds): void {
                $q->whereHas('product', fn (Builder $pq) => $pq->whereIn('default_warehouse_id', $authorizedWarehouseIds));
            })
            ->with(['goodsReceived.stockBatches', 'purchaseOrderItem'])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->filter(fn (GoodsReceivedItem $item): bool => ProductUnit::normalizeUnit((string) $item->received_unit) === $normalizedUnit);

        if ($advanceItems->isEmpty() || $billItems->isEmpty()) {
            return [
                'matched_qty' => 0.0,
                'matches_count' => 0,
                'message' => 'No matching same-day Advance and Bill items found for this product and unit.',
            ];
        }

        // Calculate remaining available qty on each item
        $advItemAvail = [];
        foreach ($advanceItems as $advItem) {
            $alreadyMatched = (float) AdvanceReceiveMatch::query()
                ->where('advance_goods_received_item_id', $advItem->id)
                ->sum('matched_qty');
            $advItemAvail[$advItem->id] = max(0.0, round((float) $advItem->received_qty - $alreadyMatched, 3));
        }

        $billItemAvail = [];
        foreach ($billItems as $billItem) {
            $alreadyMatched = (float) AdvanceReceiveMatch::query()
                ->where('bill_goods_received_item_id', $billItem->id)
                ->sum('matched_qty');
            $billItemAvail[$billItem->id] = max(0.0, round((float) $billItem->received_qty - $alreadyMatched, 3));
        }

        $totalMatchedInRun = 0.0;
        $matchesCreated = 0;

        // FIFO matching loop within selected date only
        foreach ($billItems as $billItem) {
            if ($billItemAvail[$billItem->id] <= 0.0001) {
                continue;
            }

            foreach ($advanceItems as $advItem) {
                if ($advItemAvail[$advItem->id] <= 0.0001) {
                    continue;
                }

                $matchQty = min($billItemAvail[$billItem->id], $advItemAvail[$advItem->id]);
                if ($matchQty <= 0.0001) {
                    continue;
                }

                $advGrn = $advItem->goodsReceived;
                $billGrn = $billItem->goodsReceived;

                $advBatch = $advGrn?->stockBatches->firstWhere('goods_received_item_id', $advItem->id)
                    ?? $advGrn?->stockBatches->firstWhere('product_id', $productId);

                $billBatch = $billGrn?->stockBatches->firstWhere('goods_received_item_id', $billItem->id)
                    ?? $billGrn?->stockBatches->firstWhere('product_id', $productId);

                // Create AdvanceReceiveMatch
                AdvanceReceiveMatch::create([
                    'advance_goods_received_id' => $advItem->goods_received_id,
                    'advance_goods_received_item_id' => $advItem->id,
                    'advance_stock_batch_id' => $advBatch?->id,
                    'bill_goods_received_id' => $billItem->goods_received_id,
                    'bill_goods_received_item_id' => $billItem->id,
                    'purchase_order_id' => $billGrn?->purchase_order_id ?? $billItem->purchaseOrderItem?->purchase_order_id,
                    'purchase_order_item_id' => $billItem->purchase_order_item_id,
                    'product_id' => $productId,
                    'matched_qty' => $matchQty,
                    'matched_unit' => $unit,
                    'base_qty' => $matchQty,
                    'conversion_to_base' => 1.0,
                    'confirmed_by' => $userId,
                    'confirmed_at' => now(),
                    'notes' => "Day-wise inventory match for {$product->name} on {$date}",
                ]);

                // Reduce BILL-side StockBatch only (Advance StockBatch remains intact!)
                if ($billBatch) {
                    $newBillBatchQty = max(0.0, round((float) $billBatch->total_kg - $matchQty, 3));
                    $billBatch->update([
                        'total_kg' => $newBillBatchQty,
                        'notes' => trim(($billBatch->notes ?? '')." | Matched {$matchQty} {$unit} with Advance GRN #{$advGrn?->grn_number}"),
                    ]);
                }

                // Update local available quantities
                $billItemAvail[$billItem->id] = round($billItemAvail[$billItem->id] - $matchQty, 3);
                $advItemAvail[$advItem->id] = round($advItemAvail[$advItem->id] - $matchQty, 3);

                $totalMatchedInRun = round($totalMatchedInRun + $matchQty, 3);
                $matchesCreated++;

                if ($billItemAvail[$billItem->id] <= 0.0001) {
                    break;
                }
            }
        }

        // Check if any Advance GRNs or Bill GRNs are now fully matched
        foreach ($advanceItems as $advItem) {
            $advGrn = $advItem->goodsReceived;
            if ($advGrn) {
                $freshMatches = AdvanceReceiveMatch::where('advance_goods_received_id', $advGrn->id)->get();
                $allAdvItemsMatched = $advGrn->items->every(function (GoodsReceivedItem $it) use ($freshMatches): bool {
                    $matched = (float) $freshMatches->where('advance_goods_received_item_id', $it->id)->sum('matched_qty');

                    return $matched >= (float) $it->received_qty - 0.0001;
                });
                if ($allAdvItemsMatched && $advGrn->items->isNotEmpty()) {
                    $advGrn->update(['bill_status' => 'bill_available']);
                }
            }
        }

        foreach ($billItems as $billItem) {
            $billGrn = $billItem->goodsReceived;
            if ($billGrn) {
                $freshMatches = AdvanceReceiveMatch::where('bill_goods_received_id', $billGrn->id)->get();
                $allBillItemsMatched = $billGrn->items->every(function (GoodsReceivedItem $it) use ($freshMatches): bool {
                    $matched = (float) $freshMatches->where('bill_goods_received_item_id', $it->id)->sum('matched_qty');

                    return $matched >= (float) $it->received_qty - 0.0001;
                });
                if ($allBillItemsMatched && $billGrn->items->isNotEmpty()) {
                    $billGrn->update(['bill_status' => 'bill_available']);
                }
            }
        }

        if ($totalMatchedInRun > 0) {
            activity()
                ->performedOn($product)
                ->causedBy(User::find($userId))
                ->withProperties([
                    'action' => 'day_wise_inventory_match',
                    'date' => $date,
                    'product_id' => $productId,
                    'product_name' => $product->name,
                    'unit' => $unit,
                    'matched_qty' => $totalMatchedInRun,
                    'matches_count' => $matchesCreated,
                ])
                ->log("Matched {$totalMatchedInRun} {$unit} for {$product->name} on {$date}");
        }

        return [
            'matched_qty' => $totalMatchedInRun,
            'matches_count' => $matchesCreated,
            'message' => $totalMatchedInRun > 0
                ? "Successfully matched {$totalMatchedInRun} {$unit} for {$product->name}."
                : 'No additional quantities could be matched.',
        ];
    }

    /**
     * Match all eligible rows for the selected date and warehouse.
     *
     * @return array<string, mixed>
     */
    public function matchAllDayInventory(
        string $date,
        ?int $warehouseId,
        ?array $authorizedWarehouseIds,
        int $userId
    ): array {
        $rows = $this->buildComparisonRows($date, $warehouseId, $authorizedWarehouseIds);

        $matchedProductsCount = 0;
        $totalMatchedQty = 0.0;
        $skippedUnitMismatches = 0;

        DB::transaction(function () use ($rows, $date, $warehouseId, $authorizedWarehouseIds, $userId, &$matchedProductsCount, &$totalMatchedQty, &$skippedUnitMismatches): void {
            foreach ($rows as $row) {
                if ($row['unit_mismatch']) {
                    $skippedUnitMismatches++;

                    continue;
                }

                if (in_array($row['action_type'], ['match', 'match_remaining'], true)) {
                    $matchResult = $this->executeDayInventoryMatch(
                        $date,
                        (int) $row['product_id'],
                        (string) $row['unit'],
                        $warehouseId,
                        $authorizedWarehouseIds,
                        $userId
                    );

                    if ($matchResult['matched_qty'] > 0.0001) {
                        $matchedProductsCount++;
                        $totalMatchedQty = round($totalMatchedQty + $matchResult['matched_qty'], 3);
                    }
                }
            }
        });

        // Recompute rows to get current pending count
        $updatedRows = $this->buildComparisonRows($date, $warehouseId, $authorizedWarehouseIds);
        $stillPendingRows = collect($updatedRows)->filter(function (array $r): bool {
            return ($r['bill_qty'] > 0 && $r['unmatched_bill_qty'] > 0.0001) || $r['unit_mismatch'];
        })->count();

        return [
            'matched_products_count' => $matchedProductsCount,
            'total_matched_qty' => $totalMatchedQty,
            'skipped_unit_mismatches' => $skippedUnitMismatches,
            'still_pending_rows' => $stillPendingRows,
            'message' => "Matched {$matchedProductsCount} products. Skipped {$skippedUnitMismatches} unit mismatches. {$stillPendingRows} rows still pending.",
        ];
    }

    /**
     * Update received_unit metadata on a specific goods received item safely.
     *
     * @return array<string, mixed>
     */
    public function updateItemUnit(
        int $goodsReceivedItemId,
        string $newUnit,
        int $userId,
        ?array $authorizedWarehouseIds = null
    ): array {
        $item = GoodsReceivedItem::with(['goodsReceived', 'product'])->findOrFail($goodsReceivedItemId);

        $warehouseId = $item->goodsReceived?->warehouse_id;
        if ($authorizedWarehouseIds !== null && $warehouseId !== null && ! in_array($warehouseId, $authorizedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        $oldUnit = $item->received_unit;
        $cleanUnit = trim($newUnit);

        // Update ONLY received_unit metadata on the item - do not alter quantities or stock batches
        $item->received_unit = $cleanUnit;
        $item->save();

        activity()
            ->performedOn($item)
            ->causedBy(User::find($userId))
            ->withProperties([
                'action' => 'update_received_item_unit',
                'goods_received_id' => $item->goods_received_id,
                'goods_received_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'old_unit' => $oldUnit,
                'new_unit' => $cleanUnit,
            ])
            ->log("Updated received unit for item #{$item->id} from {$oldUnit} to {$cleanUnit}");

        return [
            'id' => $item->id,
            'old_unit' => $oldUnit,
            'new_unit' => $cleanUnit,
            'message' => "Unit updated from {$oldUnit} to {$cleanUnit}.",
        ];
    }

    /**
     * Get pending purchase bills for the date.
     *
     * @return Collection<int, mixed>
     */
    public function getPendingBillsForDate(string $date, ?int $selectedWarehouseId, ?array $authorizedWarehouseIds): Collection
    {
        return PurchaseOrder::query()
            ->whereDate('order_date', $date)
            ->whereIn('status', ['draft', 'sent', 'partial_received'])
            ->when($selectedWarehouseId !== null, fn (Builder $q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->when($selectedWarehouseId === null && $authorizedWarehouseIds !== null, fn (Builder $q) => $q->whereIn('warehouse_id', $authorizedWarehouseIds))
            ->with(['supplier', 'warehouse', 'items.product'])
            ->get();
    }

    /**
     * Format pending bills list for JSON/Blade.
     *
     * @param  Collection<int, mixed>  $pendingBillsCollection
     * @return array<int, array<string, mixed>>
     */
    public function formatPendingBillsList(Collection $pendingBillsCollection): array
    {
        return $pendingBillsCollection->map(function ($po): array {
            return [
                'id' => $po->id,
                'type' => 'PO',
                'number' => $po->po_number,
                'supplier_name' => $po->supplier?->name ?? 'Unknown Supplier',
                'warehouse_name' => $po->warehouse?->name ?? 'Unknown Warehouse',
                'status' => $po->status,
                'items_count' => $po->items->count(),
                'total_qty' => round((float) $po->items->sum('quantity'), 2),
                'items' => $po->items->map(fn ($it) => [
                    'product_name' => $it->product?->name ?? 'Unknown',
                    'quantity' => (float) $it->quantity,
                    'unit' => $it->unit ?? 'kg',
                ])->all(),
            ];
        })->all();
    }
}
