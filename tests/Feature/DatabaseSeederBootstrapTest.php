<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DatabaseSeederBootstrapTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_database_seeder_bootstraps_categories_warehouses_and_july_fourteen_shop_orders(): void
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
        $this->assertDatabaseCount('shop_orders', 4);
        $this->assertSame(
            4,
            ShopOrder::query()
                ->whereDate('business_date', '2026-07-14')
                ->where('order_number', 'like', 'RQ-SHOP-20260714-%')
                ->where('state', 'approved')
                ->count(),
        );

        $this->assertSame(2, GoodsReceived::query()
            ->whereDate('received_at', '2026-07-14')
            ->where('status', 'pending_approval')
            ->where('grn_number', 'like', 'GRN-20260714-WH%')
            ->count());

        $receiver = User::query()->where('email', 'receiver@greenleaf.com')->firstOrFail();

        $this
            ->actingAs($receiver)
            ->get(route('warehouse.receiver.checklist', ['date' => '2026-07-14']))
            ->assertOk()
            ->assertSee('GRN-20260714-WH01')
            ->assertSee('GRN-20260714-WH02');

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
