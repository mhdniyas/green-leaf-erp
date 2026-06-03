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
use App\Models\ShopOrderItem;
use App\Models\ShopPreset;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\WastageEntry;
use App\Repositories\Inventory\StockMovementRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $dailyOrderStatuses = collect();
        if ($user->hasRole('shop-owner') || $user->hasRole('shop')) {
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
        } elseif ($user->hasRole('purchasing-manager') || $user->hasRole('purchase') || $user->can('purchasing.order.approve')) {
            $allShopOrders = ShopOrder::with(['shop', 'creator', 'items.product'])
                ->orderBy('business_date', 'desc')
                ->get();

            $trackedDates = collect([
                today()->addDay()->toDateString(),
                today()->toDateString(),
            ])
                ->merge($allShopOrders->pluck('business_date')->map(fn ($date) => $date->format('Y-m-d')))
                ->merge(PurchaseOrder::query()
                    ->orderByDesc('order_date')
                    ->limit(10)
                    ->pluck('order_date')
                    ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d')))
                ->unique()
                ->sortDesc()
                ->take(7)
                ->values();

            $dailyOrderStatuses = $trackedDates->map(function (string $date) use ($allShopOrders): array {
                $shopOrdersForDate = $allShopOrders->filter(fn (ShopOrder $order) => $order->business_date->format('Y-m-d') === $date);
                $purchaseOrdersForDate = PurchaseOrder::whereDate('order_date', $date)
                    ->with(['supplier', 'goodsReceiveds'])
                    ->orderByDesc('id')
                    ->get();

                $submittedCount = $shopOrdersForDate->whereIn('state', ['submitted', 'update_requested'])->count();
                $approvedCount = $shopOrdersForDate->where('state', 'approved')->count();
                $poCount = $purchaseOrdersForDate->count();
                $receivedPoCount = $purchaseOrdersForDate
                    ->filter(fn (PurchaseOrder $order) => in_array($order->status, [POStatus::Received, POStatus::Closed], true))
                    ->count();

                if ($poCount > 0 && $receivedPoCount === $poCount) {
                    $stage = 'received';
                    $label = 'Receiving / Warehouse';
                    $description = "{$receivedPoCount} PO received";
                } elseif ($poCount > 0) {
                    $stage = 'purchase_order';
                    $label = 'Purchase Order';
                    $description = "{$poCount} PO generated";
                } elseif ($approvedCount > 0) {
                    $stage = 'approved_board';
                    $label = 'Approved Board';
                    $description = "{$approvedCount} approved requisitions";
                } elseif ($submittedCount > 0) {
                    $stage = 'requisition';
                    $label = 'Requisition Review';
                    $description = "{$submittedCount} pending review";
                } else {
                    $stage = 'not_started';
                    $label = 'Not Started';
                    $description = 'No shop requisitions yet';
                }

                return [
                    'date' => $date,
                    'label' => $label,
                    'stage' => $stage,
                    'description' => $description,
                    'shop_orders_count' => $shopOrdersForDate->count(),
                    'submitted_count' => $submittedCount,
                    'approved_count' => $approvedCount,
                    'purchase_orders' => $purchaseOrdersForDate,
                    'po_count' => $poCount,
                    'received_po_count' => $receivedPoCount,
                ];
            });
        }

        $sortingProgress = null;
        if ($user->hasRole('admin') || $user->hasRole('super-admin') || $user->hasRole('legacy-admin') || $user->hasRole('purchasing-manager') || $user->hasRole('purchase') || $user->hasRole('inventory-manager') || $user->hasRole('warehouse-operations-manager') || $user->hasRole('warehouse')) {
            $todayApprovedItems = ShopOrderItem::whereHas('order', function ($q) {
                $q->whereDate('business_date', today())->where('state', 'approved');
            })->get();
            $todayTotal = $todayApprovedItems->count();
            $todaySorted = $todayApprovedItems->where('is_sorted', true)->count();
            $todayPercentage = $todayTotal > 0 ? (int) round(($todaySorted / $todayTotal) * 100) : 0;

            $tomorrowApprovedItems = ShopOrderItem::whereHas('order', function ($q) {
                $q->whereDate('business_date', Carbon::tomorrow())->where('state', 'approved');
            })->get();
            $tomorrowTotal = $tomorrowApprovedItems->count();
            $tomorrowSorted = $tomorrowApprovedItems->where('is_sorted', true)->count();
            $tomorrowPercentage = $tomorrowTotal > 0 ? (int) round(($tomorrowSorted / $tomorrowTotal) * 100) : 0;

            $sortingProgress = [
                'today' => [
                    'total' => $todayTotal,
                    'sorted' => $todaySorted,
                    'percentage' => $todayPercentage,
                ],
                'tomorrow' => [
                    'total' => $tomorrowTotal,
                    'sorted' => $tomorrowSorted,
                    'percentage' => $tomorrowPercentage,
                ],
            ];
        }

        $pendingPOsForReceipt = collect();
        if ($user->hasRole('warehouse-operations-manager') || $user->hasRole('warehouse') || $user->hasRole('admin') || $user->hasRole('super-admin') || $user->hasRole('legacy-admin')) {
            $pendingPOsForReceipt = PurchaseOrder::where('status', POStatus::Approved)
                ->with(['supplier', 'items.product'])
                ->get();
        }

        return view('dashboard', compact(
            'inventoryStats',
            'purchasingStats',
            'salesStats',
            'productsByCategory',
            'recentRequisitions',
            'presets',
            'yesterdayOrder',
            'allShopOrders',
            'dailyOrderStatuses',
            'sortingProgress',
            'pendingPOsForReceipt'
        ));
    }
}
