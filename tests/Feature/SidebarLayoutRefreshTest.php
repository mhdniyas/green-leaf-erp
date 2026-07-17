<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Shop;
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

        $this
            ->actingAsRequestUser($admin)
            ->blade('<x-layouts.staff title="Staff Layout Check"><div>Staff body</div></x-layouts.staff>')
            ->assertSee('Staff Management')
            ->assertSee('Admin Desk')
            ->assertSee('Admin Panel')
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

        $this
            ->actingAsRequestUser($warehouseReceiver)
            ->blade('<x-layouts.app title="Warehouse Layout Check"><div>Warehouse body</div></x-layouts.app>')
            ->assertSee('Warehouse Desk')
            ->assertSee('Sort Sheet')
            ->assertSee(route('sort-sheet.index'), false);
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
        ]);

        $user = User::factory()->create([
            'name' => 'Administrator',
            'email' => 'admin@greenleaf.com',
        ]);
        $user->assignRole($adminRole);

        return $user;
    }

    private function actingAsRequestUser(User $user): self
    {
        $this->actingAs($user);
        $this->app['request']->setUserResolver(fn (): User => $user);

        return $this;
    }
}
