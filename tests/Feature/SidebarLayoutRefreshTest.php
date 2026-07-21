<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Purchasing\InvoiceStatus;
use App\Models\GoodsReceived;
use App\Models\PurchaseInvoice;
use App\Models\Shop;
use App\Models\ShopAccountingEntry;
use App\Models\ShopCredit;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SidebarLayoutRefreshTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        view()->share('errors', new ViewErrorBag);
    }

    public function test_core_app_sidebar_uses_current_workspace_navigation(): void
    {
        $admin = $this->adminUser();

        $this
            ->actingAsRequestUser($admin)
            ->blade('<x-layouts.app title="Layout Check"><div>Page body</div></x-layouts.app>')
            ->assertSee('Green Leaf ERP')
            ->assertSee('Workspace')
            ->assertSee('Admin Overview')
            ->assertSee('Daily Progress')
            ->assertSee('Activity Log')
            ->assertDontSee('Operations Hub')
            ->assertDontSee('bg-slate-950 text-white', false);
    }

    public function test_staff_sidebar_keeps_admin_dashboard_switching_visible(): void
    {
        $admin = $this->adminUser();
        $payrollPermission = Permission::findOrCreate('hr.payroll.view', 'web');
        $employeePermission = Permission::findOrCreate('hr.employee.view', 'web');
        Role::findOrCreate('admin', 'web')->givePermissionTo([$payrollPermission, $employeePermission]);

        $this
            ->actingAsRequestUser($admin)
            ->blade('<x-layouts.staff title="Staff Layout Check"><div>Staff body</div></x-layouts.staff>')
            ->assertSee('Staff Management')
            ->assertSee('Admin Desk')
            ->assertSee('Admin Panel')
            ->assertSee('Assign Employees')
            ->assertSee('Advance Payments')
            ->assertDontSee('Operations Hub')
            ->assertDontSee('bg-slate-950 text-white', false);
    }

    public function test_shop_owner_sidebar_uses_light_portal_navigation(): void
    {
        $shop = Shop::factory()->create(['name' => 'Central Market']);
        $shopOwner = User::factory()->create(['shop_id' => $shop->id]);
        Role::findOrCreate('shop', 'web');
        $shopOwner->assignRole('shop');

        $this
            ->actingAsRequestUser($shopOwner)
            ->blade('@include("shop-owner.partials.sidebar")')
            ->assertSee('Shop Owner Portal')
            ->assertSee('Central Market')
            ->assertSee('Dashboard')
            ->assertDontSee('Operations Hub')
            ->assertDontSee('bg-slate-950 text-white', false);
    }

    public function test_warehouse_receiver_sidebar_shows_sort_sheet(): void
    {
        $warehouseReceiver = User::factory()->create();
        Role::findOrCreate('warehouse_receiver', 'web');
        $warehouseReceiver->assignRole('warehouse_receiver');
        GoodsReceived::factory()->create([
            'received_by' => $warehouseReceiver->id,
            'received_at' => today()->toDateString(),
            'status' => 'pending_approval',
        ]);

        $this
            ->actingAsRequestUser($warehouseReceiver)
            ->blade('<x-layouts.app title="Warehouse Layout Check"><div>Warehouse body</div></x-layouts.app>')
            ->assertSee('Warehouse Desk')
            ->assertSee('Sort Sheet')
            ->assertSee(route('sort-sheet.index'), false)
            ->assertSee('bg-orange-100 text-orange-800', false)
            ->assertSee('1');
    }

    public function test_sort_sheet_uses_admin_layout_sidebar(): void
    {
        $admin = $this->adminUser();

        $this
            ->actingAs($admin)
            ->get(route('sort-sheet.index'))
            ->assertOk()
            ->assertSee('Admin Panel')
            ->assertSee('Sort Sheet')
            ->assertDontSee('Warehouse Desk');
    }

    public function test_admin_sidebar_surfaces_accounting_and_purchasing_notification_counts(): void
    {
        $admin = $this->adminUser();
        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $invoice = ShopInvoice::factory()->create();
        $invoice->order()->update(['delivery_review_status' => 'pending']);

        ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => today()->toDateString(),
            'status' => 'submitted',
            'created_by' => $admin->id,
        ]);
        ShopCredit::factory()->create([
            'shop_id' => $shop->id,
            'type' => 'out',
            'status' => 'pending',
            'business_date' => today()->toDateString(),
        ]);
        ShopInvoicePaymentRequest::factory()->count(2)->create(['status' => 'pending']);
        ShopOrder::query()->create([
            'shop_id' => $shop->id,
            'business_date' => today()->toDateString(),
            'state' => 'submitted',
            'created_by' => $admin->id,
        ]);
        GoodsReceived::factory()->create(['status' => 'pending_approval']);
        PurchaseInvoice::factory()->create(['status' => InvoiceStatus::Pending]);

        $this
            ->actingAsRequestUser($admin)
            ->blade('<x-layouts.admin title="Layout Check"><div>Page body</div></x-layouts.admin>')
            ->assertSee('Accounting Dashboard')
            ->assertSee('Purchasing Dashboard')
            ->assertSee('4')
            ->assertSee('3');
    }

    public function test_purchase_sidebar_shows_review_queue_badges(): void
    {
        $admin = $this->adminUser();
        $invoice = ShopInvoice::factory()->create();
        $invoice->order()->update(['delivery_review_status' => 'pending']);
        $shop = Shop::factory()->create();
        ShopOrder::query()->create([
            'shop_id' => $shop->id,
            'business_date' => today()->toDateString(),
            'state' => 'submitted',
            'created_by' => $admin->id,
        ]);
        GoodsReceived::factory()->create(['status' => 'pending_approval']);
        PurchaseInvoice::factory()->create(['status' => InvoiceStatus::Pending]);

        $this
            ->actingAsRequestUser($admin)
            ->blade('@include("purchase-manager.layouts.app")')
            ->assertSee('Approve Shop Orders')
            ->assertSee('Shop Daily Invoices')
            ->assertSee('Goods Receipts')
            ->assertSee('Supplier Bills')
            ->assertSee('4');
    }

    public function test_admin_overview_shows_action_required_panel(): void
    {
        $admin = $this->adminUser();
        ShopInvoicePaymentRequest::factory()->create(['status' => 'pending']);

        $this
            ->actingAs($admin)
            ->get(route('admin.overview', ['date' => today()->toDateString()]))
            ->assertOk()
            ->assertSee('Action Required')
            ->assertSee('Shop Payments')
            ->assertSee('Payments to company waiting for accounting approval.');
    }

    public function test_owned_shop_owner_accounting_defaults_to_cashbook(): void
    {
        $shopOwner = $this->shopOwnerUser([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.index'))
            ->assertOk()
            ->assertSee('Daily Shop Receipt')
            ->assertSee('Cashbook')
            ->assertSee('Create cashbook entry', false)
            ->assertSee('Online Payment')
            ->assertDontSee('id="cashbook-open-modal"', false)
            ->assertDontSee('Daily Delivery Bills');
    }

    public function test_owned_shop_owner_can_open_accounting_create_tab(): void
    {
        $shopOwner = $this->shopOwnerUser([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.index', ['tab' => 'create']))
            ->assertOk()
            ->assertSee('Create')
            ->assertSee('Add Credit / Debit')
            ->assertSee('Submit To Admin Approval')
            ->assertDontSee('Ledger Status');
    }

    public function test_regular_shop_owner_accounting_defaults_to_bills(): void
    {
        $shopOwner = $this->shopOwnerUser([
            'accounting_enabled' => false,
            'accounting_mode' => 'regular',
        ]);

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.index'))
            ->assertOk()
            ->assertSee('Daily Delivery Bills')
            ->assertDontSee('Daily Shop Receipt');
    }

    public function test_regular_shop_owner_cannot_open_owned_accounting_tabs(): void
    {
        $shopOwner = $this->shopOwnerUser([
            'accounting_enabled' => false,
            'accounting_mode' => 'regular',
        ]);

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.index', ['tab' => 'cashbook']))
            ->assertNotFound();

        $this
            ->actingAs($shopOwner)
            ->get(route('shop-owner.accounting.index', ['tab' => 'create']))
            ->assertNotFound();
    }

    private function adminUser(): User
    {
        foreach (['admin', 'shop', 'purchase', 'purchaser', 'warehouse_receiver'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        foreach ([
            'admin.user.view',
            'admin.daily-progress.view',
            'admin.activity-log.view',
            'inventory.product.view',
            'inventory.stock.view',
            'inventory.sorting.view',
            'inventory.wastage.view',
            'sort.sheet.view',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $adminRole = Role::findOrCreate('admin', 'web');
        $adminRole->syncPermissions([
            'admin.user.view',
            'admin.daily-progress.view',
            'admin.activity-log.view',
            'inventory.product.view',
            'inventory.stock.view',
            'inventory.sorting.view',
            'inventory.wastage.view',
            'sort.sheet.view',
        ]);

        $user = User::factory()->create([
            'name' => 'Administrator',
            'email' => 'admin@greenleaf.com',
        ]);
        $user->assignRole($adminRole);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $shopAttributes
     */
    private function shopOwnerUser(array $shopAttributes = []): User
    {
        $permission = Permission::findOrCreate('sales.order.create', 'web');
        $shopRole = Role::findOrCreate('shop', 'web');
        $shopRole->givePermissionTo($permission);

        $shop = Shop::factory()->create($shopAttributes);
        $user = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $user->assignRole($shopRole);

        return $user;
    }

    private function actingAsRequestUser(User $user): self
    {
        $this->actingAs($user);
        $this->app['request']->setUserResolver(fn (): User => $user);

        return $this;
    }
}
