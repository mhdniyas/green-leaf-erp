<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Purchasing\InvoiceStatus;
use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLeaveRequest;
use App\Models\GoodsReceived;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\WastageEntry;
use App\Services\DashboardNotificationService;
use App\Services\Finance\AdminFinancePillarService;
use App\Support\AccountingAccess;
use App\Support\StaffAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminOverviewController extends Controller
{
    public function __construct(
        private readonly AdminFinancePillarService $financePillars,
        private readonly DashboardNotificationService $dashboardNotifications,
    ) {}

    public function __invoke(Request $request): View|RedirectResponse
    {
        $canAccessAdminOverview =
            $request->user()->hasRole('admin') ||
            $request->user()->can('admin.user.view') ||
            $request->user()->can('admin.daily-progress.view') ||
            $request->user()->can('admin.activity-log.view');

        if (! $canAccessAdminOverview && ($staffLandingUrl = StaffAccess::landingUrl($request->user(), $request->input('date', today()->toDateString())))) {
            return redirect()->to($staffLandingUrl);
        }

        abort_unless(
            $canAccessAdminOverview,
            403,
            'Unauthorized access to admin overview.'
        );

        $dateInput = $request->input('date');
        $date = $dateInput ? Carbon::parse($dateInput) : today();

        $ordersToday = ShopOrder::query()
            ->whereDate('business_date', $date)
            ->with(['shop', 'items'])
            ->get();

        $stockBatchesToday = StockBatch::query()
            ->whereDate('received_at', $date)
            ->get();

        $grnsToday = GoodsReceived::query()
            ->whereDate('received_at', $date)
            ->with(['purchaseOrder.supplier'])
            ->get();

        $purchaseOrdersToday = PurchaseOrder::query()
            ->whereDate('order_date', $date)
            ->with(['supplier'])
            ->get();

        $onlineUsers = User::query()
            ->with(['roles', 'shop'])
            ->where('last_seen_at', '>=', now()->subMinutes(5))
            ->orderByDesc('last_seen_at')
            ->get();

        $roleProgress = [
            [
                'name' => 'Shop Owners',
                'count' => User::role('shop')->count(),
                'online' => $onlineUsers->filter(fn (User $user): bool => $user->hasRole('shop'))->count(),
                'pending' => ShopOrder::whereDate('business_date', $date)->whereIn('state', ['submitted', 'update_requested'])->count(),
                'label' => 'today orders waiting for approval',
                'tone' => 'amber',
            ],
            [
                'name' => 'Purchase Team',
                'count' => User::role('purchase')->count(),
                'online' => $onlineUsers->filter(fn (User $user): bool => $user->hasRole('purchase'))->count(),
                'pending' => GoodsReceived::where('status', 'recheck_required')->count() + PurchaseInvoice::where('status', InvoiceStatus::Pending)->count(),
                'label' => 'receipt rechecks and invoices waiting',
                'tone' => 'violet',
            ],
            [
                'name' => 'Warehouse Team',
                'count' => User::role('warehouse_receiver')->count(),
                'online' => $onlineUsers->filter(fn (User $user): bool => $user->hasRole('warehouse_receiver'))->count(),
                'pending' => ShopOrder::whereDate('business_date', $date)->where('state', 'approved')->where('is_allocation_completed', false)->count(),
                'label' => 'approved orders still in warehouse flow',
                'tone' => 'cyan',
            ],
            [
                'name' => 'Administrators',
                'count' => User::role('admin')->count(),
                'online' => $onlineUsers->filter(fn (User $user): bool => $user->hasRole('admin'))->count(),
                'pending' => User::query()->where('registration_status', 'pending')->count(),
                'label' => 'new registrations waiting for approval',
                'tone' => 'slate',
            ],
        ];

        $suspiciousActivities = collect();

        $suspiciousActivities = $suspiciousActivities
            ->concat($ordersToday->filter(fn (ShopOrder $order): bool => (float) $order->total_shortage_value > 0.0)
                ->map(fn (ShopOrder $order): array => [
                    'title' => 'Delivery shortage recorded',
                    'detail' => ($order->shop?->name ?? 'Unknown shop').' reported shortage value of Rs. '.number_format((float) $order->total_shortage_value, 2),
                    'severity' => 'warning',
                ]))
            ->concat($ordersToday->filter(fn (ShopOrder $order): bool => abs((float) $order->cash_discrepancy) > 0.01)
                ->map(fn (ShopOrder $order): array => [
                    'title' => 'Cash discrepancy detected',
                    'detail' => ($order->shop?->name ?? 'Unknown shop').' has cash variance of Rs. '.number_format(abs((float) $order->cash_discrepancy), 2),
                    'severity' => 'danger',
                ]))
            ->concat($grnsToday->where('status', 'recheck_required')
                ->map(fn (GoodsReceived $grn): array => [
                    'title' => 'GRN flagged for recheck',
                    'detail' => $grn->grn_number.' was sent back for warehouse review.',
                    'severity' => 'warning',
                ]))
            ->take(8)
            ->values();

        $overview = [
            'today_orders' => $ordersToday->count(),
            'submitted_orders' => $ordersToday->whereIn('state', ['submitted', 'update_requested'])->count(),
            'approved_orders' => $ordersToday->where('state', 'approved')->count(),
            'delivered_orders' => $ordersToday->where('is_delivered', true)->count(),
            'received_kg_today' => (float) $stockBatchesToday->sum('total_kg'),
            'pending_batches' => $stockBatchesToday->where('status', BatchStatus::Pending)->count(),
            'wastage_kg_today' => (float) WastageEntry::whereDate('wastage_date', $date)->sum('quantity'),
            'open_purchase_orders' => PurchaseOrder::whereIn('status', [POStatus::Draft, POStatus::Approved, POStatus::SentToSupplier, POStatus::PartiallyReceived, POStatus::Received])->count(),
            'pending_grn_approval' => GoodsReceived::where('status', 'recheck_required')->count(),
            'pending_invoices' => PurchaseInvoice::where('status', InvoiceStatus::Pending)->count(),
            'online_users' => $onlineUsers->count(),
            'total_employees' => Employee::query()->count(),
            'present_staff' => EmployeeAttendance::query()->whereDate('attendance_date', $date)->where('status', 'present')->count(),
            'staff_on_leave' => EmployeeAttendance::query()->whereDate('attendance_date', $date)->where('status', 'leave')->count(),
            'pending_leave_requests' => EmployeeLeaveRequest::query()->where('status', 'pending')->count(),
        ];
        $finance = $this->financePillars->forPeriod($date, $date);
        $salesChannelContext = $this->salesChannelContext($date, $overview, $onlineUsers->count());
        $actionItems = $this->dashboardNotifications->adminActionItems($date);

        $quickLinks = [];

        if (AccountingAccess::canViewDashboard($request->user())) {
            $quickLinks[] = ['label' => 'Accounting Dashboard', 'href' => route('admin.accounting.index', ['date' => $date->format('Y-m-d')])];
        }

        $quickLinks = array_merge($quickLinks, [
            ['label' => 'Purchasing Dashboard', 'href' => route('purchasing.dashboard')],
            ['label' => 'Inventory Dashboard', 'href' => route('inventory.dashboard', ['date' => $date->format('Y-m-d')])],
            ['label' => 'Users & Roles', 'href' => route('admin.users.index')],
            ['label' => 'Daily Progress', 'href' => route('admin.daily-progress', ['date' => $date->format('Y-m-d')])],
            ['label' => 'Activity Log', 'href' => route('admin.activity-logs.index')],
            ['label' => 'Website Enquiries', 'href' => route('admin.enquiries.index')],
            ['label' => 'Staff Management', 'href' => route('admin.staff.index')],
        ]);

        if ($request->user()->can('accounting.ledger.view')) {
            $quickLinks[] = ['label' => 'Finance Overview', 'href' => route('finance.index')];
        }

        return view('admin.overview.index', compact(
            'date',
            'overview',
            'finance',
            'roleProgress',
            'onlineUsers',
            'suspiciousActivities',
            'quickLinks',
            'actionItems',
            'salesChannelContext',
        ));
    }

    /**
     * @param  array<string, int|float>  $overview
     * @return array{
     *     summary:array<string, int|float>,
     *     channels:list<array<string, mixed>>
     * }
     */
    private function salesChannelContext(Carbon $date, array $overview, int $onlineUsersCount): array
    {
        $aishwaryaClient = Client::query()
            ->where('code', 'AISHWARYA_VEG')
            ->first();
        $directShopIds = Shop::query()
            ->whereNull('client_id')
            ->pluck('id')
            ->map(fn (int|string $shopId): int => (int) $shopId)
            ->all();
        $aishwaryaShopIds = $aishwaryaClient
            ? Shop::query()
                ->where('client_id', $aishwaryaClient->id)
                ->pluck('id')
                ->map(fn (int|string $shopId): int => (int) $shopId)
                ->all()
            : [];

        $directSales = $directShopIds === []
            ? $this->emptySalesDetail($date)
            : $this->financePillars->salesDailyDetail($date, 'all', $directShopIds);
        $aishwaryaSales = $aishwaryaShopIds === []
            ? $this->emptySalesDetail($date)
            : $this->financePillars->salesDailyDetail($date, 'all', $aishwaryaShopIds);
        $directSalesSummary = $directSales['summary'];
        $aishwaryaSalesSummary = $aishwaryaSales['summary'];

        return [
            'summary' => [
                'sales_total' => round((float) $directSalesSummary['total_amount'] + (float) $aishwaryaSalesSummary['total_amount'], 2),
                'collections_total' => round((float) $directSalesSummary['paid_amount'] + (float) $aishwaryaSalesSummary['paid_amount'], 2),
                'outstanding_total' => round((float) $directSalesSummary['outstanding_amount'] + (float) $aishwaryaSalesSummary['outstanding_amount'], 2),
                'invoice_count' => (int) $directSalesSummary['invoice_count'] + (int) $aishwaryaSalesSummary['invoice_count'],
                'orders_today' => (int) $overview['today_orders'],
                'delivered_orders' => (int) $overview['delivered_orders'],
                'received_kg_today' => (float) $overview['received_kg_today'],
                'present_staff' => (int) $overview['present_staff'],
                'online_users' => $onlineUsersCount,
            ],
            'channels' => [
                [
                    'label' => 'Direct Sales',
                    'description' => 'Green Leaf direct customer and shop invoices.',
                    'summary' => $directSalesSummary,
                    'shop_count' => count($directShopIds),
                    'href' => route('admin.accounting.daily-sales', [
                        'date' => $date->toDateString(),
                        'sales_scope' => 'direct',
                    ]),
                    'secondary_href' => route('admin.accounting.daily-sales', [
                        'date' => $date->toDateString(),
                        'sales_scope' => 'direct',
                        'status' => 'pending',
                    ]),
                    'secondary_label' => 'Pending direct invoices',
                    'tone' => 'emerald',
                ],
                [
                    'label' => 'Aishwarya Sales',
                    'description' => 'Client sales for Aishwarya Veg shops.',
                    'summary' => $aishwaryaSalesSummary,
                    'shop_count' => count($aishwaryaShopIds),
                    'href' => $aishwaryaClient
                        ? route('admin.accounting.clients.show', [
                            'client' => $aishwaryaClient,
                            'start_date' => $date->toDateString(),
                            'end_date' => $date->toDateString(),
                        ])
                        : route('admin.accounting.daily-sales', [
                            'date' => $date->toDateString(),
                            'sales_scope' => 'client',
                        ]),
                    'secondary_href' => route('admin.accounting.daily-sales', array_filter([
                        'date' => $date->toDateString(),
                        'sales_scope' => 'client',
                        'client_id' => $aishwaryaClient?->id,
                    ], fn ($value): bool => $value !== null)),
                    'secondary_label' => 'Aishwarya sales report',
                    'tone' => 'cyan',
                ],
            ],
        ];
    }

    /**
     * @return array{
     *     date:string,
     *     summary:array<string, int|float|string>,
     *     shop_rows:Collection<int, array<string, mixed>>,
     *     invoices:Collection<int, mixed>,
     *     status_filter:string
     * }
     */
    private function emptySalesDetail(Carbon $date): array
    {
        return [
            'date' => $date->toDateString(),
            'summary' => [
                'label' => 'Sales Reports',
                'count' => 0,
                'invoice_count' => 0,
                'total_amount' => 0.0,
                'paid_amount' => 0.0,
                'outstanding_amount' => 0.0,
                'settlement_rate' => 0.0,
            ],
            'shop_rows' => collect(),
            'invoices' => collect(),
            'status_filter' => 'all',
        ];
    }
}
