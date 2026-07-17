<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Purchasing\InvoiceStatus;
use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLeaveRequest;
use App\Models\GoodsReceived;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\ShopOrder;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\WastageEntry;
use App\Services\Finance\AdminFinancePillarService;
use App\Support\AccountingAccess;
use App\Support\StaffAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class AdminOverviewController extends Controller
{
    public function __construct(
        private readonly AdminFinancePillarService $financePillars,
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

        $recentActivities = Activity::query()
            ->with(['causer'])
            ->latest('created_at')
            ->limit(12)
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

        $finance = $this->financePillars->forPeriod($date, $date);

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
            'recentActivities',
            'suspiciousActivities',
            'quickLinks',
        ));
    }
}
