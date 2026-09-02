<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdvanceInventoryService
{
    /**
     * Get paginated daily advance inventory as-of date with carry forward.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateDailyInventory(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $asOfDate = isset($filters['date']) && ! empty($filters['date'])
            ? Carbon::parse($filters['date'])->toDateString()
            : now()->toDateString();

        $search = trim((string) ($filters['search'] ?? ''));
        $warehouseIds = $filters['authorized_warehouse_ids'] ?? null;

        if ($warehouseIds === null && isset($filters['warehouse_id']) && $filters['warehouse_id'] !== null) {
            $warehouseIds = [(int) $filters['warehouse_id']];
        }

        if ($warehouseIds === null) {
            $warehouseIds = Warehouse::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if (empty($warehouseIds)) {
            return new LengthAwarePaginator([], 0, $perPage, 1);
        }

        $productQuery = Product::query()
            ->select(['products.id', 'products.name', 'products.sku', 'products.unit']);

        if ($search !== '') {
            $productQuery->where(function (Builder $q) use ($search): void {
                $q->where('products.name', 'like', "%{$search}%")
                    ->orWhere('products.sku', 'like', "%{$search}%");
            });
        } else {
            $productQuery->where(function (Builder $q) use ($warehouseIds, $asOfDate): void {
                // Products that have physical stock/advance receipts OR confirmed bill reconciliations up to asOfDate
                $q->whereExists(function ($sub) use ($warehouseIds, $asOfDate): void {
                    $sub->selectRaw('1')
                        ->from('stock_batches as sb')
                        ->whereColumn('sb.product_id', 'products.id')
                        ->whereNull('sb.deleted_at')
                        ->where('sb.warehouse_receive_pending', false)
                        ->whereIn('sb.warehouse_id', $warehouseIds)
                        ->whereRaw('DATE(COALESCE(sb.received_at, sb.created_at)) <= ?', [$asOfDate]);
                })
                    ->orWhereExists(function ($sub) use ($warehouseIds, $asOfDate): void {
                        $sub->selectRaw('1')
                            ->from('bill_reconciliation_lines as brl')
                            ->join('bill_reconciliations as br', 'br.id', '=', 'brl.bill_reconciliation_id')
                            ->whereColumn('brl.product_id', 'products.id')
                            ->where('br.status', 'confirmed')
                            ->where(function ($whQ) use ($warehouseIds): void {
                                $whQ->whereIn('br.warehouse_id', $warehouseIds)
                                    ->orWhereNull('br.warehouse_id');
                            })
                            ->whereRaw('DATE(COALESCE(br.confirmed_at, br.created_at)) <= ?', [$asOfDate]);
                    });
            });
        }

        $paginator = $productQuery
            ->orderBy('products.name')
            ->orderBy('products.id')
            ->paginate($perPage);

        $productIds = $paginator->getCollection()->pluck('id')->all();

        if (empty($productIds)) {
            return $paginator;
        }

        // 1. Bulk Aggregate Physical Warehouse Intake Base Quantities as-of date (from stock_batches, zero double-counting)
        $physicals = DB::table('stock_batches as sb')
            ->whereIn('sb.product_id', $productIds)
            ->whereNull('sb.deleted_at')
            ->where('sb.warehouse_receive_pending', false)
            ->whereIn('sb.warehouse_id', $warehouseIds)
            ->whereRaw('DATE(COALESCE(sb.received_at, sb.created_at)) <= ?', [$asOfDate])
            ->select([
                'sb.product_id',
                DB::raw('SUM(sb.total_kg) as total_physical_base'),
            ])
            ->groupBy('sb.product_id')
            ->pluck('total_physical_base', 'product_id');

        // 2. Bulk Aggregate Billed Base Quantities as-of date (from confirmed BillReconciliation lines)
        $reconBilled = DB::table('bill_reconciliation_lines as brl')
            ->join('bill_reconciliations as br', 'br.id', '=', 'brl.bill_reconciliation_id')
            ->whereIn('brl.product_id', $productIds)
            ->where('br.status', 'confirmed')
            ->where(function ($whQ) use ($warehouseIds): void {
                $whQ->whereIn('br.warehouse_id', $warehouseIds)
                    ->orWhereNull('br.warehouse_id');
            })
            ->whereRaw('DATE(COALESCE(br.confirmed_at, br.created_at)) <= ?', [$asOfDate])
            ->select([
                'brl.product_id',
                DB::raw('SUM(brl.bill_base_qty) as total_billed_base'),
            ])
            ->groupBy('brl.product_id')
            ->pluck('total_billed_base', 'product_id');

        // Legacy normal purchases that were approved as bill_available without a BillReconciliation record
        $legacyBilled = DB::table('goods_received_items as gri')
            ->join('goods_received as gr', 'gr.id', '=', 'gri.goods_received_id')
            ->join('products as p', 'p.id', '=', 'gri.product_id')
            ->leftJoin('product_units as pu', function ($join): void {
                $join->on('pu.product_id', '=', 'gri.product_id')
                    ->whereRaw('LOWER(TRIM(pu.unit)) = LOWER(TRIM(gri.received_unit))');
            })
            ->whereIn('gri.product_id', $productIds)
            ->whereNull('gri.deleted_at')
            ->whereNull('gr.deleted_at')
            ->where('gr.status', 'approved')
            ->where('gr.bill_status', 'bill_available')
            ->where('gr.receipt_type', 'normal_purchase')
            ->whereNotExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('bill_reconciliations as br')
                    ->whereColumn('br.goods_received_id', 'gr.id');
            })
            ->where(function ($whQ) use ($warehouseIds): void {
                $whQ->whereIn('gr.warehouse_id', $warehouseIds)
                    ->orWhereIn('gr.destination_shop_id', $warehouseIds);
            })
            ->whereRaw('DATE(COALESCE(gr.approved_at, gr.created_at)) <= ?', [$asOfDate])
            ->select([
                'gri.product_id',
                DB::raw('SUM(gri.received_qty * (
                    CASE
                        WHEN LOWER(TRIM(gri.received_unit)) = LOWER(TRIM(p.unit)) THEN 1.0
                        ELSE COALESCE(pu.conversion_to_base, 1.0)
                    END
                )) as total_legacy_billed'),
            ])
            ->groupBy('gri.product_id')
            ->pluck('total_legacy_billed', 'product_id');

        $paginator->getCollection()->transform(function (Product $product) use ($physicals, $reconBilled, $legacyBilled, $asOfDate): array {
            $physicalQty = round((float) ($physicals[$product->id] ?? 0.0), 3);
            $billedQty = round((float) ($reconBilled[$product->id] ?? 0.0) + (float) ($legacyBilled[$product->id] ?? 0.0), 3);
            $diffQty = round($physicalQty - $billedQty, 3);

            $diffType = 'balanced';
            if ($diffQty > 0.0001) {
                $diffType = 'excess';
            } elseif ($diffQty < -0.0001) {
                $diffType = 'short';
            } else {
                $diffQty = 0.0;
            }

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'base_unit' => strtoupper((string) ($product->unit ?: 'KG')),
                'billed_base_qty' => $billedQty,
                'physical_base_qty' => $physicalQty,
                'advance_base_qty' => $diffQty, // Advance reconciliation difference column
                'difference_base_qty' => $diffQty,
                'difference_type' => $diffType,
                'as_of_date' => $asOfDate,
            ];
        });

        return $paginator;
    }

    /**
     * Get aggregate summary counts (Excess, Shortage, Balanced) as-of date using identical accounting definitions.
     *
     * @param  array<string, mixed>  $filters
     * @return array{total_products: int, excess_count: int, short_count: int, balanced_count: int, total_physical_base: float, total_billed_base: float, total_difference_base: float}
     */
    public function getDailySummary(array $filters = []): array
    {
        $asOfDate = isset($filters['date']) && ! empty($filters['date'])
            ? Carbon::parse($filters['date'])->toDateString()
            : now()->toDateString();

        $warehouseIds = $filters['authorized_warehouse_ids'] ?? null;

        if ($warehouseIds === null && isset($filters['warehouse_id']) && $filters['warehouse_id'] !== null) {
            $warehouseIds = [(int) $filters['warehouse_id']];
        }

        if ($warehouseIds === null) {
            $warehouseIds = Warehouse::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if (empty($warehouseIds)) {
            return [
                'total_products' => 0,
                'excess_count' => 0,
                'short_count' => 0,
                'balanced_count' => 0,
                'total_physical_base' => 0.0,
                'total_billed_base' => 0.0,
                'total_difference_base' => 0.0,
            ];
        }

        $eligibleProductIds = Product::query()
            ->where(function (Builder $q) use ($warehouseIds, $asOfDate): void {
                $q->whereExists(function ($sub) use ($warehouseIds, $asOfDate): void {
                    $sub->selectRaw('1')
                        ->from('stock_batches as sb')
                        ->whereColumn('sb.product_id', 'products.id')
                        ->whereNull('sb.deleted_at')
                        ->where('sb.warehouse_receive_pending', false)
                        ->whereIn('sb.warehouse_id', $warehouseIds)
                        ->whereRaw('DATE(COALESCE(sb.received_at, sb.created_at)) <= ?', [$asOfDate]);
                })
                    ->orWhereExists(function ($sub) use ($warehouseIds, $asOfDate): void {
                        $sub->selectRaw('1')
                            ->from('bill_reconciliation_lines as brl')
                            ->join('bill_reconciliations as br', 'br.id', '=', 'brl.bill_reconciliation_id')
                            ->whereColumn('brl.product_id', 'products.id')
                            ->where('br.status', 'confirmed')
                            ->where(function ($whQ) use ($warehouseIds): void {
                                $whQ->whereIn('br.warehouse_id', $warehouseIds)
                                    ->orWhereNull('br.warehouse_id');
                            })
                            ->whereRaw('DATE(COALESCE(br.confirmed_at, br.created_at)) <= ?', [$asOfDate]);
                    });
            })
            ->pluck('id')
            ->all();

        if (empty($eligibleProductIds)) {
            return [
                'total_products' => 0,
                'excess_count' => 0,
                'short_count' => 0,
                'balanced_count' => 0,
                'total_physical_base' => 0.0,
                'total_billed_base' => 0.0,
                'total_difference_base' => 0.0,
            ];
        }

        $physicals = DB::table('stock_batches as sb')
            ->whereIn('sb.product_id', $eligibleProductIds)
            ->whereNull('sb.deleted_at')
            ->where('sb.warehouse_receive_pending', false)
            ->whereIn('sb.warehouse_id', $warehouseIds)
            ->whereRaw('DATE(COALESCE(sb.received_at, sb.created_at)) <= ?', [$asOfDate])
            ->select([
                'sb.product_id',
                DB::raw('SUM(sb.total_kg) as total_physical_base'),
            ])
            ->groupBy('sb.product_id')
            ->pluck('total_physical_base', 'product_id');

        $reconBilled = DB::table('bill_reconciliation_lines as brl')
            ->join('bill_reconciliations as br', 'br.id', '=', 'brl.bill_reconciliation_id')
            ->whereIn('brl.product_id', $eligibleProductIds)
            ->where('br.status', 'confirmed')
            ->where(function ($whQ) use ($warehouseIds): void {
                $whQ->whereIn('br.warehouse_id', $warehouseIds)
                    ->orWhereNull('br.warehouse_id');
            })
            ->whereRaw('DATE(COALESCE(br.confirmed_at, br.created_at)) <= ?', [$asOfDate])
            ->select([
                'brl.product_id',
                DB::raw('SUM(brl.bill_base_qty) as total_billed_base'),
            ])
            ->groupBy('brl.product_id')
            ->pluck('total_billed_base', 'product_id');

        $legacyBilled = DB::table('goods_received_items as gri')
            ->join('goods_received as gr', 'gr.id', '=', 'gri.goods_received_id')
            ->join('products as p', 'p.id', '=', 'gri.product_id')
            ->leftJoin('product_units as pu', function ($join): void {
                $join->on('pu.product_id', '=', 'gri.product_id')
                    ->whereRaw('LOWER(TRIM(pu.unit)) = LOWER(TRIM(gri.received_unit))');
            })
            ->whereIn('gri.product_id', $eligibleProductIds)
            ->whereNull('gri.deleted_at')
            ->whereNull('gr.deleted_at')
            ->where('gr.status', 'approved')
            ->where('gr.bill_status', 'bill_available')
            ->where('gr.receipt_type', 'normal_purchase')
            ->whereNotExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('bill_reconciliations as br')
                    ->whereColumn('br.goods_received_id', 'gr.id');
            })
            ->where(function ($whQ) use ($warehouseIds): void {
                $whQ->whereIn('gr.warehouse_id', $warehouseIds)
                    ->orWhereIn('gr.destination_shop_id', $warehouseIds);
            })
            ->whereRaw('DATE(COALESCE(gr.approved_at, gr.created_at)) <= ?', [$asOfDate])
            ->select([
                'gri.product_id',
                DB::raw('SUM(gri.received_qty * (
                    CASE
                        WHEN LOWER(TRIM(gri.received_unit)) = LOWER(TRIM(p.unit)) THEN 1.0
                        ELSE COALESCE(pu.conversion_to_base, 1.0)
                    END
                )) as total_legacy_billed'),
            ])
            ->groupBy('gri.product_id')
            ->pluck('total_legacy_billed', 'product_id');

        $excessCount = 0;
        $shortCount = 0;
        $balancedCount = 0;
        $totalPhysical = 0.0;
        $totalBilled = 0.0;

        foreach ($eligibleProductIds as $pId) {
            $pQty = round((float) ($physicals[$pId] ?? 0.0), 3);
            $bQty = round((float) ($reconBilled[$pId] ?? 0.0) + (float) ($legacyBilled[$pId] ?? 0.0), 3);
            $diff = round($pQty - $bQty, 3);

            $totalPhysical += $pQty;
            $totalBilled += $bQty;

            if ($diff > 0.0001) {
                $excessCount++;
            } elseif ($diff < -0.0001) {
                $shortCount++;
            } else {
                $balancedCount++;
            }
        }

        return [
            'total_products' => count($eligibleProductIds),
            'excess_count' => $excessCount,
            'short_count' => $shortCount,
            'balanced_count' => $balancedCount,
            'total_physical_base' => round($totalPhysical, 3),
            'total_billed_base' => round($totalBilled, 3),
            'total_difference_base' => round($totalPhysical - $totalBilled, 3),
        ];
    }
}
