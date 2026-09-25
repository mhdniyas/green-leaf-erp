<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Shop;
use App\Models\User;
use App\Support\CashbookAccess;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CashbookUserAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $normalUser;

    protected Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $adminEmail = 'admin@greenleaf.test';
        config(['admin.user_access.main_admin_email' => $adminEmail]);

        $this->admin = User::factory()->create([
            'email' => $adminEmail,
        ]);
        $this->admin->assignRole('admin');

        $this->normalUser = User::factory()->create([
            'email' => 'staff@greenleaf.test',
        ]);

        $this->shop = Shop::factory()->create([
            'name' => 'Kozhikode Main',
            'code' => 'CLT01',
            'status' => 'active',
        ]);
    }

    public function test_main_admin_has_full_unrestricted_access_to_cashbook_and_user_access(): void
    {
        $this->actingAs($this->admin);

        // Landing page
        $this->get(route('admin.cashbook.index'))->assertOk();

        // User Access Management
        $this->get(route('admin.cashbook.user-access.index'))->assertOk();

        // Specific section view
        $this->get(route('admin.cashbook.all-shops'))->assertOk();
        $this->get(route('admin.cashbook.money-flow'))->assertOk();
        $this->get(route('admin.cashbook.account-balance'))->assertOk();
        $this->get(route('admin.cashbook.finance'))->assertOk();
        $this->get(route('admin.cashbook.inventory'))->assertOk();
        $this->get(route('admin.cashbook.reports'))->assertOk();
        $this->get(route('admin.cashbook.settings'))->assertOk();
    }

    public function test_user_without_cashbook_access_is_forbidden_from_all_cashbook_routes(): void
    {
        $this->actingAs($this->normalUser);

        // Web GET routes redirect to dashboard with unauthorized error
        $this->get(route('admin.cashbook.index'))->assertRedirect(route('dashboard'));
        $this->get(route('admin.cashbook.all-shops'))->assertRedirect(route('dashboard'));
        $this->get(route('admin.cashbook.money-flow'))->assertRedirect(route('dashboard'));
        $this->get(route('admin.cashbook.account-balance'))->assertRedirect(route('dashboard'));
        $this->get(route('admin.cashbook.finance'))->assertRedirect(route('dashboard'));
        $this->get(route('admin.cashbook.inventory'))->assertRedirect(route('dashboard'));
        $this->get(route('admin.cashbook.reports'))->assertRedirect(route('dashboard'));
        $this->get(route('admin.cashbook.settings'))->assertRedirect(route('dashboard'));
        $this->get(route('admin.cashbook.user-access.index'))->assertRedirect(route('dashboard'));

        // JSON requests return 403 Forbidden
        $this->getJson(route('admin.cashbook.index'))->assertForbidden();
        $this->getJson(route('admin.cashbook.user-access.index'))->assertForbidden();
        $this->getJson(route('admin.cashbook.api.shop-data'))->assertForbidden();
        $this->postJson(route('admin.cashbook.api.record-entry'), [])->assertForbidden();
    }

    public function test_read_only_user_can_view_permitted_sections_but_cannot_mutate_data(): void
    {
        // Grant Read-Only for Dashboard, Shops, and Reports
        CashbookAccess::updateUserPermissions($this->normalUser, [
            CashbookAccess::Access,
            CashbookAccess::DashboardView,
            CashbookAccess::ShopsView,
            CashbookAccess::ReportsView,
        ]);

        $this->actingAs($this->normalUser);

        // Permitted GET routes succeed
        $this->get(route('admin.cashbook.index'))->assertOk();
        $this->get(route('admin.cashbook.all-shops'))->assertOk();
        $this->get(route('admin.cashbook.reports'))->assertOk();

        // Unpermitted GET routes are blocked and redirected
        $this->get(route('admin.cashbook.money-flow'))->assertRedirect(route('dashboard'));
        $this->get(route('admin.cashbook.finance'))->assertRedirect(route('dashboard'));
        $this->get(route('admin.cashbook.settings'))->assertRedirect(route('dashboard'));
        $this->getJson(route('admin.cashbook.money-flow'))->assertForbidden();

        // Write routes in permitted sections are blocked (because user lacks .manage)
        $this->post(route('admin.cashbook.shop.financial-ledger.update', ['shop' => $this->shop->id]), [])->assertForbidden();
        $this->postJson(route('admin.cashbook.api.record-entry'), [])->assertForbidden();
        $this->postJson(route('admin.cashbook.api.update-rule'), [])->assertForbidden();
    }

    public function test_full_access_preset_grants_all_modules_but_excludes_user_access_manage(): void
    {
        $allPermissions = CashbookAccess::fullAccessPermissions();
        CashbookAccess::updateUserPermissions($this->normalUser, $allPermissions);

        $this->actingAs($this->normalUser);

        $this->get(route('admin.cashbook.index'))->assertOk();
        $this->get(route('admin.cashbook.all-shops'))->assertOk();
        $this->get(route('admin.cashbook.money-flow'))->assertOk();
        $this->get(route('admin.cashbook.account-balance'))->assertOk();
        $this->get(route('admin.cashbook.finance'))->assertOk();
        $this->get(route('admin.cashbook.inventory'))->assertOk();
        $this->get(route('admin.cashbook.reports'))->assertOk();
        $this->get(route('admin.cashbook.settings'))->assertOk();

        // User Access Management remains restricted
        $this->get(route('admin.cashbook.user-access.index'))->assertRedirect(route('dashboard'));
        $this->getJson(route('admin.cashbook.user-access.index'))->assertForbidden();
    }

    public function test_custom_access_mode_respects_independent_view_and_manage_permissions(): void
    {
        // Grant Shops (View + Manage) and Money Flow (View only)
        CashbookAccess::updateUserPermissions($this->normalUser, [
            CashbookAccess::Access,
            CashbookAccess::ShopsView,
            CashbookAccess::ShopsManage,
            CashbookAccess::MoneyFlowView,
        ]);

        $this->actingAs($this->normalUser);

        // Permitted views
        $this->get(route('admin.cashbook.all-shops'))->assertOk();
        $this->get(route('admin.cashbook.money-flow'))->assertOk();

        // Money Flow write is forbidden
        $this->postJson(route('admin.cashbook.api.update-rule'), [])->assertForbidden();

        // Unassigned sections are forbidden / redirected
        $this->get(route('admin.cashbook.account-balance'))->assertRedirect(route('dashboard'));
        $this->get(route('admin.cashbook.finance'))->assertRedirect(route('dashboard'));
        $this->get(route('admin.cashbook.inventory'))->assertRedirect(route('dashboard'));
        $this->getJson(route('admin.cashbook.account-balance'))->assertForbidden();
    }

    public function test_manage_permission_implies_view_permission(): void
    {
        // Initially false
        $this->assertFalse(CashbookAccess::allows($this->normalUser, CashbookAccess::ShopsView));

        // Assign only ShopsManage
        CashbookAccess::updateUserPermissions($this->normalUser, [
            CashbookAccess::Access,
            CashbookAccess::ShopsManage,
        ]);

        // Manage implies View
        $this->assertTrue(CashbookAccess::allows($this->normalUser, CashbookAccess::ShopsView));
        $this->assertTrue(CashbookAccess::allows($this->normalUser, CashbookAccess::ShopsManage));
    }

    public function test_updating_cashbook_permissions_preserves_unrelated_erp_permissions(): void
    {
        // Create an unrelated permission and assign it to the user
        $unrelatedPermission = Permission::findOrCreate('hr.employees.view', 'web');
        $this->normalUser->givePermissionTo($unrelatedPermission);

        $this->assertTrue($this->normalUser->hasPermissionTo('hr.employees.view'));

        // Update Cashbook permissions via controller or support service
        CashbookAccess::updateUserPermissions($this->normalUser, [
            CashbookAccess::Access,
            CashbookAccess::ShopsView,
        ]);

        // Unrelated permission is untouched
        $this->normalUser->refresh();
        $this->assertTrue($this->normalUser->hasPermissionTo('hr.employees.view'));
        $this->assertTrue($this->normalUser->hasPermissionTo(CashbookAccess::ShopsView));
        $this->assertFalse($this->normalUser->hasPermissionTo(CashbookAccess::ReportsView));

        // Remove Cashbook permissions (No Access mode)
        CashbookAccess::updateUserPermissions($this->normalUser, []);

        $this->normalUser->refresh();
        $this->assertTrue($this->normalUser->hasPermissionTo('hr.employees.view'));
        $this->assertFalse($this->normalUser->hasPermissionTo(CashbookAccess::Access));
    }

    public function test_user_access_controller_saves_presets_correctly(): void
    {
        $this->actingAs($this->admin);

        // 1. Save Read Only preset with selected sections
        $response = $this->post(route('admin.cashbook.user-access.update'), [
            'user_id' => $this->normalUser->id,
            'mode' => 'read_only',
            'read_only_sections' => ['dashboard', 'shops', 'reports'],
        ]);

        $response->assertRedirect();
        $this->normalUser->refresh();
        $this->assertTrue($this->normalUser->hasPermissionTo(CashbookAccess::DashboardView));
        $this->assertTrue($this->normalUser->hasPermissionTo(CashbookAccess::ShopsView));
        $this->assertTrue($this->normalUser->hasPermissionTo(CashbookAccess::ReportsView));
        $this->assertFalse($this->normalUser->hasPermissionTo(CashbookAccess::ShopsManage));
        $this->assertFalse($this->normalUser->hasPermissionTo(CashbookAccess::MoneyFlowView));

        // 2. Save Full Access preset
        $response = $this->post(route('admin.cashbook.user-access.update'), [
            'user_id' => $this->normalUser->id,
            'mode' => 'full_access',
        ]);

        $response->assertRedirect();
        $this->normalUser->refresh();
        $this->assertTrue($this->normalUser->hasPermissionTo(CashbookAccess::ShopsManage));
        $this->assertTrue($this->normalUser->hasPermissionTo(CashbookAccess::ReconciliationManage));
        $this->assertFalse($this->normalUser->hasPermissionTo(CashbookAccess::UserAccessManage));

        // 3. Save No Access mode
        $response = $this->post(route('admin.cashbook.user-access.update'), [
            'user_id' => $this->normalUser->id,
            'mode' => 'no_access',
        ]);

        $response->assertRedirect();
        $this->normalUser->refresh();
        $this->assertFalse($this->normalUser->hasPermissionTo(CashbookAccess::Access));
        $this->assertFalse($this->normalUser->hasPermissionTo(CashbookAccess::ShopsView));
    }

    public function test_sidebar_and_landing_page_render_only_permitted_modules(): void
    {
        // Grant only Reports to user
        CashbookAccess::updateUserPermissions($this->normalUser, [
            CashbookAccess::Access,
            CashbookAccess::ReportsView,
        ]);

        $this->actingAs($this->normalUser);

        $response = $this->get(route('admin.cashbook.reports'));
        $response->assertOk();

        // Reports is visible
        $response->assertSee('Reports');
    }
}
