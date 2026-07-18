<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ShopAccountingCategory;
use App\Models\ShopOwnerAssignment;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DatabaseSeederBootstrapTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_database_seeder_bootstraps_production_base_data_without_demo_orders(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('categories', ['name' => 'VEG', 'is_active' => 1]);
        $this->assertDatabaseHas('categories', ['name' => 'Frut', 'is_active' => 1]);

        $this->assertDatabaseHas('warehouses', ['code' => 'VEG-WH', 'name' => 'Vegetable Warehouse', 'is_active' => 1]);
        $this->assertDatabaseHas('warehouses', ['code' => 'FRT-WH', 'name' => 'Fruit Warehouse', 'is_active' => 1]);

        $vegWarehouse = Warehouse::query()->where('code', 'VEG-WH')->firstOrFail();
        $fruitWarehouse = Warehouse::query()->where('code', 'FRT-WH')->firstOrFail();
        $vegCategoryIds = Category::query()->whereNotIn('name', ['Frut', 'Banana'])->pluck('id');
        $fruitCategoryIds = Category::query()->whereIn('name', ['Frut', 'Banana'])->pluck('id');

        $this->assertTrue($vegWarehouse->exists);
        $this->assertTrue($fruitWarehouse->exists);
        $this->assertDatabaseCount('shop_orders', 0);
        $this->assertDatabaseCount('goods_received', 0);
        $this->assertDatabaseHas('roles', ['name' => 'admin', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => 'shop', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => 'hr_manager', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'hr.employee.view', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'sort.sheet.view', 'guard_name' => 'web']);
        $this->assertDatabaseHas('users', ['email' => 'admin@greenleaf.com', 'registration_status' => 'approved']);
        $this->assertDatabaseHas('users', ['email' => 'hr@greenleaf.com', 'registration_status' => 'approved']);
        $this->assertDatabaseHas('users', ['email' => 'purchase@greenleaf.com', 'registration_status' => 'approved']);
        $this->assertDatabaseHas('users', ['email' => 'purchaser@greenleaf.com', 'registration_status' => 'approved']);
        $this->assertDatabaseHas('users', ['email' => 'receiver@greenleaf.com', 'registration_status' => 'approved']);
        $this->assertDatabaseHas('shops', ['code' => 'SHOP_ASHIRWAD', 'name' => 'Ashirwad', 'status' => 'active']);
        $this->assertDatabaseHas('users', ['email' => 'shop-ashirwad@greenleaf.com', 'registration_status' => 'approved']);
        $this->assertSame(14, User::role('shop')->count());
        $this->assertSame(14, ShopOwnerAssignment::query()->count());
        $this->assertGreaterThan(0, ShopAccountingCategory::query()->whereNull('shop_id')->where('is_active', true)->count());

        $this->assertGreaterThan(
            0,
            Product::query()->whereIn('category_id', $fruitCategoryIds)->where('default_warehouse_id', $fruitWarehouse->id)->count(),
        );
        $this->assertGreaterThan(
            0,
            Product::query()->whereIn('category_id', $vegCategoryIds)->where('default_warehouse_id', $vegWarehouse->id)->count(),
        );
    }
}
