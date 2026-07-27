<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\EmployeeAdvanceRule;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopAccountingCategory;
use App\Models\ShopEmployeeAssignment;
use App\Models\ShopOrder;
use App\Models\ShopOwnerAssignment;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\WorkflowShopOrderSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DatabaseSeederBootstrapTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_database_seeder_bootstraps_production_base_data_with_fourteen_shops_and_daily_orders(): void
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
        $this->assertDatabaseCount('shop_orders', 14);
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
        $aishwaryaVeg = Client::query()->where('code', 'AISHWARYA_VEG')->firstOrFail();

        $this->assertDatabaseHas('shops', [
            'code' => 'AV_CASIO',
            'name' => 'Casio',
            'status' => 'active',
            'client_id' => $aishwaryaVeg->id,
        ]);
        $this->assertDatabaseHas('shops', [
            'code' => 'AV_SANA_JP',
            'name' => 'Sana JP',
            'status' => 'active',
            'client_id' => $aishwaryaVeg->id,
        ]);
        $this->assertDatabaseHas('shops', [
            'code' => 'AV_JINDAL_CITY',
            'name' => 'Jindal City',
            'status' => 'active',
            'client_id' => $aishwaryaVeg->id,
        ]);
        $this->assertDatabaseHas('shops', [
            'code' => 'DS_QUICK_MART',
            'name' => 'Quick Mart',
            'status' => 'active',
            'client_id' => null,
        ]);
        $this->assertDatabaseHas('shops', [
            'code' => 'DS_FORTUNE_SM',
            'name' => 'Fortune SM',
            'status' => 'active',
            'client_id' => null,
        ]);
        $this->assertDatabaseHas('users', ['email' => 'shop-direct-quick-mart@greenleaf.com', 'registration_status' => 'approved']);
        $this->assertNotNull(User::query()->where('email', 'shop-direct-quick-mart@greenleaf.com')->firstOrFail()->shop?->shop_price_group_id);
        $this->assertSame(14, Shop::query()->where('status', 'active')->count());
        $this->assertSame(12, Shop::query()->where('client_id', $aishwaryaVeg->id)->where('status', 'active')->count());
        $this->assertSame(2, Shop::query()->whereNull('client_id')->where('status', 'active')->count());
        $this->assertSame(14, User::role('shop')->count());
        $this->assertSame(14, ShopOwnerAssignment::query()->count());
        $this->assertSame(42, ShopEmployeeAssignment::query()->where('status', 'active')->count());
        $this->assertSame(70, ShopOrder::query()->withCount('items')->get()->sum('items_count'));
        $this->assertSame(10, EmployeeAdvanceRule::activeRule()->minimum_present_days);
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

    public function test_workflow_shop_order_seeder_creates_one_five_item_submitted_order_per_shop_for_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-25 09:00:00', 'Asia/Kolkata'));

        $this->seed(DatabaseSeeder::class);
        $this->seed(WorkflowShopOrderSeeder::class);

        $shopCount = User::role('shop')->whereNotNull('shop_id')->count();

        $this->assertSame(14, $shopCount);
        $this->assertSame($shopCount, ShopOrder::query()->count());
        $this->assertSame($shopCount, ShopOrder::query()
            ->where('order_source', 'seeded_shop_workflow')
            ->where('state', 'submitted')
            ->whereDate('business_date', '2026-07-25')
            ->whereDate('submitted_at', '2026-07-24')
            ->where('is_late', false)
            ->count());
        $this->assertSame($shopCount, ShopOrder::query()
            ->select('shop_id')
            ->selectRaw('COUNT(*) as orders_count')
            ->groupBy('shop_id')
            ->having('orders_count', 1)
            ->get()
            ->count());
        $this->assertSame($shopCount, ShopOrder::query()
            ->withCount('items')
            ->get()
            ->filter(fn (ShopOrder $order): bool => $order->items_count === 5)
            ->count());
        $this->assertSame(0, ShopOrder::query()
            ->whereHas('items', fn ($query) => $query->whereNotNull('approved_qty'))
            ->count());

        Carbon::setTestNow();
    }
}
