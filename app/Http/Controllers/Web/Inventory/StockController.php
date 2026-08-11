<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockAdjustment;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(
        private readonly StockMovementRepository $stockMovements,
        private readonly PurchaserBusinessDayService $businessDayService,
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

        $stock = $this->stockMovements->currentStockByProductAndGrade($date);
        $stockByProduct = $stock->groupBy('product_id');
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
            ->selectRaw("COALESCE(SUM(CASE WHEN category = 'wastage' THEN ABS(variance_qty) ELSE 0 END), 0) as wastage_qty")
            ->selectRaw("COALESCE(SUM(CASE WHEN category = 'old_stock' THEN variance_qty ELSE 0 END), 0) as old_stock_qty")
            ->first();

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
        ));
    }
}
