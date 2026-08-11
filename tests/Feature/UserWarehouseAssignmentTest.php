<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\Admin\UserData;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Admin\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserWarehouseAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_assigned_multiple_warehouses_with_one_default(): void
    {
        $fruit = $this->warehouse('Fruit', 'FRUIT');
        $vegetable = $this->warehouse('Vegetable', 'VEG');

        $user = app(UserService::class)->create(new UserData(
            name: 'Warehouse User',
            email: 'warehouse@example.com',
            password: 'password123',
            shopId: null,
            roles: [],
            warehouseIds: [$fruit->id, $vegetable->id, $fruit->id],
            defaultWarehouseId: $vegetable->id,
        ));

        $this->assertCount(2, $user->warehouses()->get());
        $this->assertDatabaseHas('user_warehouse', [
            'user_id' => $user->id,
            'warehouse_id' => $vegetable->id,
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('user_warehouse', [
            'user_id' => $user->id,
            'warehouse_id' => $fruit->id,
            'is_default' => false,
        ]);
    }

    public function test_assigned_user_cannot_access_an_unassigned_warehouse(): void
    {
        $fruit = $this->warehouse('Fruit', 'FRUIT');
        $vegetable = $this->warehouse('Vegetable', 'VEG');
        $user = User::factory()->create();
        $user->warehouses()->attach($fruit, ['is_default' => true]);

        $this->assertTrue($user->canAccessWarehouse($fruit));
        $this->assertFalse($user->canAccessWarehouse($vegetable));
    }

    public function test_authorized_general_user_can_access_all_warehouses(): void
    {
        $warehouse = $this->warehouse('Fruit', 'FRUIT');
        $permission = Permission::create([
            'name' => 'warehouse.loadout.all',
            'guard_name' => 'web',
        ]);
        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        $this->assertTrue($user->hasAllWarehouseAccess());
        $this->assertTrue($user->canAccessWarehouse($warehouse));
    }

    public function test_authenticated_user_response_includes_assignments_and_default(): void
    {
        $fruit = $this->warehouse('Fruit', 'FRUIT');
        $vegetable = $this->warehouse('Vegetable', 'VEG');
        $user = User::factory()->create();
        $user->warehouses()->attach([
            $fruit->id => ['is_default' => true],
            $vegetable->id => ['is_default' => false],
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.has_all_warehouse_access', false)
            ->assertJsonCount(2, 'user.assigned_warehouses')
            ->assertJsonCount(2, 'user.available_warehouses')
            ->assertJsonFragment([
                'id' => $fruit->id,
                'name' => 'Fruit',
                'code' => 'FRUIT',
                'is_default' => true,
            ]);
    }

    public function test_login_response_includes_assigned_warehouses(): void
    {
        $fruit = $this->warehouse('Fruit', 'FRUIT');
        $user = User::factory()->create([
            'email' => 'fruit@example.com',
            'password' => 'password123',
        ]);
        $user->warehouses()->attach($fruit, ['is_default' => true]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'fruit@example.com',
            'password' => 'password123',
            'device_name' => 'test',
        ])->assertOk()
            ->assertJsonPath('user.assigned_warehouses.0.id', $fruit->id)
            ->assertJsonPath('user.assigned_warehouses.0.is_default', true);
    }

    private function warehouse(string $name, string $code): Warehouse
    {
        return Warehouse::query()->create([
            'name' => $name,
            'code' => $code,
            'is_active' => true,
        ]);
    }
}
