<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Repositories\Inventory\StockMovementRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(
        private readonly StockMovementRepository $stockMovements,
    ) {}

    public function index(Request $request): View
    {
        // Default to tomorrow if current time is past 9:30 PM, otherwise today
        $date = $request->input('date');

        if (! $date) {
            $tomorrow = Carbon::tomorrow()->format('Y-m-d');
            $today = Carbon::today()->format('Y-m-d');

            $hasTodayOrders = ShopOrder::whereDate('business_date', $today)->where('state', 'approved')->exists();
            $hasTomorrowOrders = ShopOrder::whereDate('business_date', $tomorrow)->where('state', 'approved')->exists();

            if ($hasTomorrowOrders && ! $hasTodayOrders) {
                $date = $tomorrow;
            } else {
                $cutoffTime = today()->setTime(21, 30);
                $date = now()->greaterThan($cutoffTime) ? $tomorrow : $today;
            }
        }

        $stock = $this->stockMovements->currentStockByProductAndGrade($date);

        // Fetch dispatches/allocations for the date
        $allocations = ShopOrderItem::whereHas('order', function ($query) use ($date) {
            $query->whereDate('business_date', $date)
                ->where('state', 'approved');
        })
            ->with(['order.shop'])
            ->get()
            ->groupBy('product_id');

        return view('inventory.stock.index', compact('stock', 'date', 'allocations'));
    }
}
