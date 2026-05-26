<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\Purchasing\POStatus;
use App\Enums\Sales\SOStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\ShopOrder;
use App\Models\ShopPreset;
use App\Models\StockBatch;
use App\Models\Supplier;
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

        // Stats visible to purchasing roles
        $purchasingStats = null;
        if ($user->can('purchasing.order.view')) {
            $purchasingStats = [
                'active_suppliers' => Supplier::count(),
                'pending_pos' => PurchaseOrder::whereIn('status', [
                    POStatus::Draft,
                    POStatus::Approved,
                ])->count(),
                'monthly_purchases' => (float) PurchaseOrderItem::whereHas('purchaseOrder', function ($q) {
                    $q->whereYear('order_date', today()->year)
                        ->whereMonth('order_date', today()->month)
                        ->where('status', '!=', POStatus::Draft);
                })->selectRaw('SUM(quantity * unit_price) as total')->value('total'),
            ];
        }

        // Stats visible to sales roles
        $salesStats = null;
        if ($user->can('sales.order.view')) {
            $salesStats = [
                'active_customers' => Customer::where('is_active', true)->count(),
                'pending_sos' => SalesOrder::whereIn('status', [
                    SOStatus::Draft,
                    SOStatus::Confirmed,
                ])->count(),
                'monthly_sales' => (float) SalesInvoice::whereYear('created_at', today()->year)
                    ->whereMonth('created_at', today()->month)
                    ->sum('amount'),
            ];
        }

        $productsByCategory = null;
        $recentRequisitions = collect();
        $presets = collect();
        $yesterdayOrder = null;
        $allShopOrders = collect();
        if ($user->hasRole('shop-owner')) {
            $productsByCategory = Category::with(['products' => function ($q) {
                $q->where('is_active', true)->orderBy('name');
            }])
                ->where('is_active', true)
                ->get()
                ->filter(fn ($cat) => $cat->products->count() > 0);

            if ($user->shop_id) {
                $recentRequisitions = ShopOrder::where('shop_id', $user->shop_id)
                    ->with('items')
                    ->orderBy('business_date', 'desc')
                    ->limit(7)
                    ->get();

                $presets = ShopPreset::where('shop_id', $user->shop_id)
                    ->with('items.product')
                    ->get();

                $yesterdayOrder = ShopOrder::where('shop_id', $user->shop_id)
                    ->where('business_date', today()->subDay())
                    ->with('items')
                    ->first();

                if (! $yesterdayOrder) {
                    $yesterdayOrder = ShopOrder::where('shop_id', $user->shop_id)
                        ->where('business_date', '<', today())
                        ->with('items')
                        ->orderBy('business_date', 'desc')
                        ->first();
                }
            }
        } elseif ($user->hasRole('purchasing-manager') || $user->can('purchasing.order.approve')) {
            $allShopOrders = ShopOrder::with(['shop', 'creator', 'items.product'])
                ->orderBy('business_date', 'desc')
                ->get();
        }

        return view('dashboard', compact('inventoryStats', 'purchasingStats', 'salesStats', 'productsByCategory', 'recentRequisitions', 'presets', 'yesterdayOrder', 'allShopOrders'));
    }
}
