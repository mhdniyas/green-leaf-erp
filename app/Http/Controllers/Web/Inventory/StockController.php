<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use App\Repositories\Inventory\CategoryRepository;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(
        private readonly StockMovementRepository $stockMovements,
        private readonly PurchaserBusinessDayService $businessDayService,
        private readonly CategoryRepository $categories,
    ) {}

    public function index(Request $request): View
    {
        $date = $request->input('date');

        if (! $date) {
            $tomorrow = Carbon::tomorrow()->format('Y-m-d');
            $today = Carbon::today()->format('Y-m-d');

            $hasTodayOrders = ShopOrder::whereDate('business_date', $today)->where('state', 'approved')->exists();
            $hasTomorrowOrders = ShopOrder::whereDate('business_date', $tomorrow)->where('state', 'approved')->exists();

            if ($hasTomorrowOrders && ! $hasTodayOrders) {
                $date = $tomorrow;
            } else {
                $date = $this->businessDayService->operationalDate()->toDateString();
            }
        }

        $warehouses = Warehouse::query()->active()->orderBy('name')->get(['id', 'name', 'code']);
        $selectedWarehouseId = $request->integer('warehouse_id');
        $selectedWarehouseId = $warehouses->contains('id', $selectedWarehouseId) ? $selectedWarehouseId : null;
        $assignedCategoryIds = $request->user()?->hasAssignedCategoryFilter()
            ? $request->user()->assignedCategoryIds()
            : null;
        $categories = $this->categories->findAllActive()
            ->when($assignedCategoryIds !== null, fn (Collection $items) => $items->whereIn('id', $assignedCategoryIds))
            ->values();
        $selectedCategoryId = $request->integer('category_id');
        $selectedCategoryId = $categories->contains('id', $selectedCategoryId) ? $selectedCategoryId : null;
        $categoryIds = $selectedCategoryId ? [$selectedCategoryId] : $assignedCategoryIds;

        $stockRows = $this->stockMovements->currentStockByProductAndGrade($date, $selectedWarehouseId, $categoryIds);
        $stockByProduct = $stockRows->groupBy('product_id');
        $negativeProductCount = $stockByProduct
            ->filter(fn ($rows) => (float) $rows->sum('current_stock') < -0.001)
            ->count();
        $belowBufferProductCount = $stockByProduct
            ->filter(function ($rows): bool {
                $totalStock = (float) $rows->sum('current_stock');
                $bufferQty = (float) ($rows->first()->buffer_qty ?? 0);

                return $bufferQty > 0 && $totalStock < $bufferQty;
            })
            ->count();
        $carryoverProductCount = $stockByProduct
            ->filter(fn ($rows): bool => (bool) ($rows->first()->carryover_enabled ?? false))
            ->count();
        $adjustmentTotals = StockAdjustment::query()
            ->whereDate('business_date', $date)
            ->when($categoryIds !== null, fn ($query) => $query->whereHas('product', fn ($productQuery) => $productQuery->whereIn('category_id', $categoryIds)))
            ->selectRaw("COALESCE(SUM(CASE WHEN category = 'wastage' THEN ABS(variance_qty) ELSE 0 END), 0) as wastage_qty")
            ->selectRaw("COALESCE(SUM(CASE WHEN category = 'old_stock' THEN variance_qty ELSE 0 END), 0) as old_stock_qty")
            ->first();
        $showAdjustmentTotals = ! $selectedWarehouseId;

        $search = trim($request->string('search')->toString());
        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, ['sku_asc', 'name_asc', 'name_desc', 'stock_high', 'stock_low', 'below_buffer'], true)
            ? $sort
            : 'sku_asc';
        $perPage = in_array($request->integer('per_page'), [12, 24, 48], true)
            ? $request->integer('per_page')
            : 24;

        $productGroups = $stockByProduct
            ->filter(function (Collection $grades) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                $entry = $grades->first();
                $haystack = mb_strtolower(implode(' ', [
                    (string) $entry->product_name,
                    (string) ($entry->product_sku ?? ''),
                    (string) ($entry->category_name ?? ''),
                ]));

                return str_contains($haystack, mb_strtolower($search));
            });

        $productGroups = match ($sort) {
            'name_desc' => $productGroups->sortByDesc(fn (Collection $grades): string => (string) $grades->first()->product_name),
            'stock_high' => $productGroups->sortByDesc(fn (Collection $grades): float => (float) $grades->sum('current_stock')),
            'stock_low' => $productGroups->sortBy(fn (Collection $grades): float => (float) $grades->sum('current_stock')),
            'below_buffer' => $productGroups->sortByDesc(function (Collection $grades): int {
                $first = $grades->first();

                return (float) $first->buffer_qty > 0 && (float) $grades->sum('current_stock') < (float) $first->buffer_qty ? 1 : 0;
            }),
            default => $productGroups,
        };

        $page = Paginator::resolveCurrentPage('page');
        $stock = new LengthAwarePaginator(
            $productGroups->forPage($page, $perPage),
            $productGroups->count(),
            $perPage,
            $page,
            ['path' => route('inventory.stock.index'), 'pageName' => 'page'],
        );

        // Fetch dispatches/allocations for the date
        $allocations = ShopOrderItem::whereHas('order', function ($query) use ($date) {
            $query->whereDate('business_date', $date)
                ->where('state', 'approved');
        })
            ->with(['order.shop'])
            ->get()
            ->groupBy('product_id');

        return view('inventory.stock.index', compact(
            'stock',
            'date',
            'allocations',
            'negativeProductCount',
            'belowBufferProductCount',
            'carryoverProductCount',
            'adjustmentTotals',
            'search',
            'sort',
            'perPage',
            'warehouses',
            'selectedWarehouseId',
            'categories',
            'selectedCategoryId',
            'showAdjustmentTotals',
        ));
    }
}
