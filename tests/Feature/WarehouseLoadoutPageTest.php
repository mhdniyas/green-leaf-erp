<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WarehouseLoadoutPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_warehouse_receiver_can_view_admin_direct_purchase_loadout_without_shop(): void
    {
        $admin = User::factory()->create(['name' => 'Administrator']);
        $warehouseReceiver = User::factory()->create();
        $admin->assignRole('admin');
        $warehouseReceiver->assignRole('warehouse_receiver');

        $order = ShopOrder::query()->create([
            'shop_id' => null,
            'order_number' => 'ADP-20260717-001',
            'order_source' => 'admin_direct_purchase',
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
            'business_date' => '2026-07-17',
            'created_by' => $admin->id,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => Product::factory()->create()->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'kg',
            'sorting_status' => 'pending',
        ]);

        $this
            ->actingAs($warehouseReceiver)
            ->get(route('warehouse.loadout.index'))
            ->assertOk()
            ->assertSee('Direct Purchase')
            ->assertSee('ADP-20260717-001')
            ->assertDontSee('Unknown Shop');

        $this
            ->actingAs($admin)
            ->get(route('warehouse.loadout.index'))
            ->assertOk()
            ->assertSee('Direct Purchase')
            ->assertSee('ADP-20260717-001');
    }

    public function test_warehouse_receiver_can_search_loadout_by_product_name(): void
    {
        $warehouseReceiver = User::factory()->create();
        $warehouseReceiver->assignRole('warehouse_receiver');
        $vegetableCategory = Category::factory()->create(['name' => 'Vegetables']);
        $fruitCategory = Category::factory()->create(['name' => 'Fruits']);

        $matchingShop = Shop::factory()->create([
            'name' => 'Ashirwad',
            'warehouse_tag' => 'A1',
        ]);
        $matchingOrder = ShopOrder::query()->create([
            'shop_id' => $matchingShop->id,
            'order_number' => 'RQ-LOADOUT-TOMATO',
            'order_source' => 'shop_owner',
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
            'business_date' => '2026-07-17',
            'created_by' => $warehouseReceiver->id,
            'reviewed_by' => $warehouseReceiver->id,
            'reviewed_at' => now(),
        ]);
        ShopOrderItem::query()->create([
            'shop_order_id' => $matchingOrder->id,
            'product_id' => Product::factory()->create([
                'name' => 'Tomato H',
                'sku' => 'TOM-H-LOAD',
                'category_id' => $vegetableCategory->id,
            ])->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'kg',
            'sorting_status' => 'pending',
        ]);

        $decoyShop = Shop::factory()->create(['name' => 'Different Shop']);
        $decoyOrder = ShopOrder::query()->create([
            'shop_id' => $decoyShop->id,
            'order_number' => 'RQ-LOADOUT-APPLE',
            'order_source' => 'shop_owner',
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
            'business_date' => '2026-07-17',
            'created_by' => $warehouseReceiver->id,
            'reviewed_by' => $warehouseReceiver->id,
            'reviewed_at' => now(),
        ]);
        ShopOrderItem::query()->create([
            'shop_order_id' => $decoyOrder->id,
            'product_id' => Product::factory()->create([
                'name' => 'Apple Royal',
                'sku' => 'APL-LOAD',
                'category_id' => $fruitCategory->id,
            ])->id,
            'requested_qty' => 5,
            'approved_qty' => 5,
            'unit' => 'kg',
            'sorting_status' => 'pending',
        ]);

        $this
            ->actingAs($warehouseReceiver)
            ->get(route('warehouse.loadout.index', [
                'search' => 'Tomato',
                'date' => '2026-07-17',
                'source' => 'shop',
                'category_id' => $vegetableCategory->id,
            ]))
            ->assertOk()
            ->assertSee('RQ-LOADOUT-TOMATO')
            ->assertSee('Ashirwad')
            ->assertDontSee('RQ-LOADOUT-APPLE');
    }

    public function test_warehouse_receiver_loadout_detail_page_has_product_search_filters(): void
    {
        $warehouseReceiver = User::factory()->create();
        $warehouseReceiver->assignRole('warehouse_receiver');

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $shop = Shop::factory()->create(['name' => 'Ashirwad']);
        $order = ShopOrder::query()->create([
            'shop_id' => $shop->id,
            'order_number' => 'RQ-LOADOUT-FILTER',
            'order_source' => 'shop_owner',
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
            'business_date' => '2026-07-17',
            'created_by' => $warehouseReceiver->id,
            'reviewed_by' => $warehouseReceiver->id,
            'reviewed_at' => now(),
        ]);
        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => Product::factory()->create([
                'name' => 'Tomato H',
                'sku' => 'TOM-H-DETAIL',
                'category_id' => $category->id,
            ])->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'kg',
            'sorting_status' => 'pending',
        ]);

        $this
            ->actingAs($warehouseReceiver)
            ->get(route('warehouse.loadout.show', $order))
            ->assertOk()
            ->assertSee('Find Product')
            ->assertSee('loadout-product-search')
            ->assertSee('Tomato H')
            ->assertSee('Vegetables');
    }
}
