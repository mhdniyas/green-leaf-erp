<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\WastageEntry;
use App\Repositories\Inventory\StockMovementRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly StockMovementRepository $stockMovements,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        // Stats visible to inventory roles
        $inventoryStats = null;
        if ($user->hasAnyPermission(['inventory.stock.view', 'inventory.product.view'])) {
            $inventoryStats = [
                'pending_batches' => StockBatch::where('status', 'pending')->count(),
                'today_wastage' => (float) WastageEntry::whereDate('wastage_date', today())->selectRaw('SUM(quantity * cost_per_kg) as total')->value('total'),
                'total_products' => Product::where('is_active', true)->count(),
                'stock_entries' => $this->stockMovements->currentStockByProductAndGrade()->count(),
            ];
        }

        return view('dashboard', compact('inventoryStats'));
    }
}
