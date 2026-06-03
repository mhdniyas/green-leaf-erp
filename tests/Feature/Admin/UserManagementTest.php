<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        // Create Admin user with required permissions
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo([
            'admin.user.view',
            'admin.user.create',
            'admin.user.update',
            'admin.user.delete',
        ]);

        // Create regular user with no admin permissions
        $this->unauthorizedUser = User::factory()->create();
    }

    public function test_authorized_user_can_view_users_list(): void
    {
        $user = User::factory()->create(['name' => 'John Doe']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('John Doe');
    }

    public function test_unauthorized_user_cannot_view_users_list(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('admin.users.index'));

        $response->assertForbidden();
    }

    public function test_authorized_user_can_create_user_with_roles_and_permissions(): void
    {
        $role = Role::findByName('shop');
        $permission = Permission::findByName('sales.order.create');

        $payload = [
            'name' => 'Alice Smith',
            'email' => 'alice@example.com',
            'password' => 'secret123',
            'roles' => [$role->name],
            'permissions' => [$permission->name],
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.store'), $payload);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'name' => 'Alice Smith',
            'email' => 'alice@example.com',
        ]);

        $createdUser = User::where('email', 'alice@example.com')->firstOrFail();
        $this->assertTrue($createdUser->hasRole($role->name));
        $this->assertTrue($createdUser->hasDirectPermission($permission->name));
    }

    public function test_create_user_fails_with_invalid_data(): void
    {
        $payload = [
            'name' => '',
            'email' => 'invalid-email',
            'password' => 'short',
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.store'), $payload);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
    }

    public function test_authorized_user_can_update_user(): void
    {
        $targetUser = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $role = Role::findByName('purchase');
        $permission = Permission::findByName('sales.customer.view');

        $payload = [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'password' => null, // Leave empty to retain current password
            'roles' => [$role->name],
            'permissions' => [$permission->name],
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('admin.users.update', $targetUser), $payload);

        $response->assertRedirect(route('admin.users.index'));

        $targetUser->refresh();
        $this->assertEquals('New Name', $targetUser->name);
        $this->assertEquals('new@example.com', $targetUser->email);
        $this->assertTrue($targetUser->hasRole($role->name));
        $this->assertTrue($targetUser->hasDirectPermission($permission->name));
    }

    public function test_authorized_user_can_delete_user(): void
    {
        $targetUser = User::factory()->create();

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $targetUser));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertModelMissing($targetUser);
    }
}
