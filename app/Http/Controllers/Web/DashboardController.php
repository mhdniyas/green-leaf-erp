<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SalesInvoice;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\WastageEntry;
use App\Repositories\Inventory\StockMovementRepository;
use App\Support\StaffAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly StockMovementRepository $stockMovements,
    ) {}

    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasRole('shop')) {
            return redirect()->route('shop.dashboard');
        }

        if ($user->hasRole('admin')) {
            return redirect()->route('admin.overview');
        }

        if ($staffLandingUrl = StaffAccess::landingUrl($user, $request->input('date', today()->toDateString()))) {
            return redirect()->to($staffLandingUrl);
        }

        if ($user->hasRole('purchaser')) {
            return redirect()->route('purchaser.dashboard');
        }

        if ($user->hasRole('purchase')) {
            return redirect()->route('purchasing.dashboard');
        }

        if ($user->hasRole('warehouse_receiver')) {
            return redirect()->route('warehouse.receiver.checklist');
        }

        if ($user->hasAnyPermission(['inventory.product.view', 'inventory.stock.view', 'inventory.sorting.view', 'inventory.wastage.view'])) {
            return redirect()->route('inventory.dashboard');
        }

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
        if ($user->can('sales.customer.view') || $user->can('sales.invoice.view')) {
            $salesStats = [
                'active_shops' => Shop::query()->active()->count(),
                'monthly_sales' => (float) SalesInvoice::whereYear('created_at', today()->year)
                    ->whereMonth('created_at', today()->month)
                    ->sum('amount'),
            ];
        }

        $allShopOrders = collect();
        $dailyOrderStatuses = collect();
        $purchaseDashboard = null;
        if ($user->hasRole('purchase') || $user->can('purchasing.order.approve')) {
            $allShopOrders = ShopOrder::with(['shop', 'creator', 'items.product'])
                ->orderBy('business_date', 'desc')
                ->get();

            $today = today()->toDateString();
            $tomorrow = today()->addDay()->toDateString();

            $pendingReviewOrders = $allShopOrders->whereIn('state', ['submitted', 'update_requested']);
            $pendingTomorrowReviewOrders = $pendingReviewOrders
                ->filter(fn (ShopOrder $order) => $order->business_date->format('Y-m-d') === $tomorrow);
            $approvedTomorrowOrders = $allShopOrders
                ->where('state', 'approved')
                ->filter(fn (ShopOrder $order) => $order->business_date->format('Y-m-d') === $tomorrow);

            $openPurchaseOrders = PurchaseOrder::query()
                ->whereIn('status', [
                    POStatus::Draft,
                    POStatus::Approved,
                    POStatus::SentToSupplier,
                    POStatus::PartiallyReceived,
                    POStatus::Received,
                ])
                ->with(['supplier', 'goodsReceiveds'])
                ->orderByDesc('order_date')
                ->get();

            $recheckGrns = GoodsReceived::query()
                ->where('status', 'recheck_required')
                ->with(['purchaseOrder.supplier', 'receivedBy', 'updatedBy'])
                ->latest('updated_at')
                ->latest('id')
                ->get();

            $approvedGrns = GoodsReceived::query()
                ->whereDate('received_at', $today)
                ->where('status', 'approved')
                ->with(['purchaseOrder.supplier', 'receivedBy', 'approvedBy'])
                ->latest('received_at')
                ->latest('id')
                ->get();

            $invoiceExceptions = ShopInvoice::query()
                ->where(function ($query): void {
                    $query->whereIn('status', ['delivery_review', 'payment_pending'])
                        ->orWhere('shortage_total', '>', 0)
                        ->orWhere('discount_total', '>', 0)
                        ->orWhere('balance_amount', '>', 0);
                })
                ->with(['shop', 'order'])
                ->latest('business_date')
                ->latest('id')
                ->get();

            $greenLeafDirectInvoices = PurchaseInvoice::query()
                ->whereIn('purchase_source', ['green_leaf_direct_purchase', 'mixed'])
                ->with(['supplier', 'purchaserCart.user'])
                ->latest('updated_at')
                ->latest('id')
                ->limit(6)
                ->get();

            $purchaseDashboard = [
                'headline' => [
                    'pending_review' => $pendingReviewOrders->count(),
                    'pending_review_tomorrow' => $pendingTomorrowReviewOrders->count(),
                    'approved_tomorrow' => $approvedTomorrowOrders->count(),
                    'open_purchase_orders' => $openPurchaseOrders->count(),
                    'grns_awaiting_approval' => $recheckGrns->count(),
                    'pending_invoices' => $invoiceExceptions->count(),
                    'green_leaf_direct_invoices' => $greenLeafDirectInvoices->count(),
                ],
                'focus_cards' => [
                    [
                        'title' => 'Approve Shop Orders',
                        'count' => $pendingTomorrowReviewOrders->count(),
                        'detail' => 'Shop requests waiting for first review for tomorrow',
                        'href' => route('requisitions.board', ['date' => $tomorrow]),
                        'action' => 'Review Requests',
                        'tone' => 'amber',
                    ],
                    [
                        'title' => 'Approved Board',
                        'count' => $approvedTomorrowOrders->count(),
                        'detail' => 'Approved requisitions ready for supplier split and PO handoff',
                        'href' => route('requisitions.approved_board', ['date' => $tomorrow]),
                        'action' => 'Open Approved Board',
                        'tone' => 'violet',
                    ],
                    [
                        'title' => 'Purchase Orders',
                        'count' => $openPurchaseOrders->count(),
                        'detail' => 'Draft, approved, sent, and receiving-stage purchase orders',
                        'href' => route('purchasing.orders.index'),
                        'action' => 'Manage Orders',
                        'tone' => 'blue',
                    ],
                    [
                        'title' => 'GRN Recheck',
                        'count' => $recheckGrns->count(),
                        'detail' => 'Receipts flagged by admin for warehouse recheck and resubmission',
                        'href' => route('purchasing.grns.index'),
                        'action' => 'Open Receipts',
                        'tone' => 'emerald',
                    ],
                    [
                        'title' => 'Shop Daily Invoices',
                        'count' => $invoiceExceptions->count(),
                        'detail' => 'Delivery discrepancies, discounts, and balances waiting for review',
                        'href' => route('purchasing.shop-invoices.index'),
                        'action' => 'Open Invoices',
                        'tone' => 'slate',
                    ],
                ],
                'today_tasks' => [
                    [
                        'title' => 'Handle shop updates first',
                        'description' => $pendingReviewOrders->where('state', 'update_requested')->count().' updated requisitions need quantity review before PO finalization.',
                        'href' => route('requisitions.board', ['date' => $tomorrow]),
                    ],
                    [
                        'title' => 'Convert approved demand into supplier orders',
                        'description' => $approvedTomorrowOrders->count().' approved requisitions are ready for supplier allocation and PO creation.',
                        'href' => route('requisitions.approved_board', ['date' => $tomorrow]),
                    ],
                    [
                        'title' => 'Track warehouse receipts and rechecks',
                        'description' => $recheckGrns->count().' GRNs need recheck while '.$approvedGrns->count().' receipts are already approved today.',
                        'href' => route('purchasing.grns.index'),
                    ],
                    [
                        'title' => 'Follow invoice matching and payment handoff',
                        'description' => $invoiceExceptions->count().' shop invoices still need discrepancy, discount, or payment follow-up.',
                        'href' => route('purchasing.shop-invoices.index'),
                    ],
                ],
                'today' => $today,
                'tomorrow' => $tomorrow,
                'recent_grns' => $approvedGrns->take(3),
                'recent_purchase_orders' => $openPurchaseOrders->take(4),
                'recent_invoices' => $invoiceExceptions->take(3),
                'green_leaf_direct_invoices' => $greenLeafDirectInvoices,
                'notifications' => $user->unreadNotifications()->latest()->limit(8)->get(),
            ];

            $trackedDates = collect([
                $tomorrow,
                $today,
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
        if ($user->hasRole(['admin', 'purchase', 'warehouse'])) {
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
        if ($user->hasRole(['warehouse', 'admin'])) {
            $pendingPOsForReceipt = PurchaseOrder::where('status', POStatus::Approved)
                ->with(['supplier', 'items.product'])
                ->get();
        }

        return view('dashboard', compact(
            'inventoryStats',
            'purchasingStats',
            'salesStats',
            'allShopOrders',
            'purchaseDashboard',
            'dailyOrderStatuses',
            'sortingProgress',
            'pendingPOsForReceipt'
        ));
    }
}
