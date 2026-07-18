<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
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
}
