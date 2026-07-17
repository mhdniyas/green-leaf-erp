<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_create_user_page_uses_roles_without_direct_permission_overrides(): void
    {
        $admin = $this->adminUser();

        Role::findOrCreate('shop', 'web');
        Permission::findOrCreate('sales.order.view', 'web');

        $this
            ->actingAs($admin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('Access Roles')
            ->assertSee('Shop')
            ->assertDontSee('Direct Permission Overrides')
            ->assertDontSee('permissions[]', false)
            ->assertDontSee('sales.order.view');
    }

    public function test_admin_overview_does_not_surface_direct_permission_override_language(): void
    {
        $admin = $this->adminUser();

        $this
            ->actingAs($admin)
            ->get(route('admin.overview'))
            ->assertOk()
            ->assertSee('Users & Roles')
            ->assertDontSee('Permission Overrides')
            ->assertDontSee('direct permission overrides');
    }

    public function test_store_rejects_direct_permission_payloads(): void
    {
        $admin = $this->adminUser();
        $role = Role::findOrCreate('shop', 'web');
        $permission = Permission::findOrCreate('sales.order.view', 'web');

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.users.create'))
            ->post(route('admin.users.store'), [
                'name' => 'Shop Operator',
                'email' => 'operator@example.com',
                'password' => 'password123',
                'roles' => [$role->name],
                'permissions' => [$permission->name],
            ]);

        $response->assertRedirect(route('admin.users.create'));
        $response->assertSessionHasErrors('permissions');
        $this->assertDatabaseMissing('users', ['email' => 'operator@example.com']);
    }

    public function test_updating_user_clears_legacy_direct_permissions_and_keeps_roles(): void
    {
        $admin = $this->adminUser();
        $role = Role::findOrCreate('shop', 'web');
        $permission = Permission::findOrCreate('sales.order.view', 'web');
        $user = User::factory()->create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
        ]);
        $user->givePermissionTo($permission);

        $this
            ->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => 'Existing User',
                'email' => 'existing@example.com',
                'roles' => [$role->name],
            ])
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();

        $this->assertTrue($user->hasRole($role));
        $this->assertCount(0, $user->permissions);
    }

    private function adminUser(): User
    {
        foreach (['shop', 'purchase', 'warehouse_receiver'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        foreach (['admin.user.view', 'admin.user.create', 'admin.user.update', 'admin.user.delete'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role = Role::findOrCreate('admin', 'web');
        $role->syncPermissions([
            'admin.user.view',
            'admin.user.create',
            'admin.user.update',
            'admin.user.delete',
        ]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
