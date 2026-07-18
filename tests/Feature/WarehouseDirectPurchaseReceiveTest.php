<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WarehouseDirectPurchaseReceiveTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_warehouse_receiver_can_receive_admin_direct_purchase_from_pending_tab(): void
    {
        $warehouseReceiver = User::factory()->create();
        $warehouseReceiver->assignRole('warehouse_receiver');

        $warehouse = Warehouse::query()->create([
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'name' => 'Tomato',
            'unit' => 'kg',
        ]);

        $order = ShopOrder::query()->create([
            'shop_id' => null,
            'order_number' => 'ADP-20260717-002',
            'order_source' => 'admin_direct_purchase',
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
            'business_date' => '2026-07-17',
            'created_by' => $warehouseReceiver->id,
            'reviewed_by' => $warehouseReceiver->id,
            'reviewed_at' => now(),
        ]);

        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 15,
            'approved_qty' => 15,
            'unit' => 'kg',
            'sorting_status' => 'pending',
            'notes' => 'Green Leaf Direct Purchase',
        ]);

        $this
            ->actingAs($warehouseReceiver)
            ->get(route('warehouse.receiver.checklist', ['date' => '2026-07-17', 'tab' => 'pending']))
            ->assertOk()
            ->assertSee('Pending Direct Purchases')
            ->assertSee('ADP-20260717-002')
            ->assertSee('Receive');

        $this
            ->actingAs($warehouseReceiver)
            ->post(route('warehouse.receiver.direct-purchase.receive', $order), [
                'warehouse_id' => $warehouse->id,
            ])
            ->assertRedirect(route('warehouse.receiver.checklist', ['date' => '2026-07-17', 'tab' => 'pending']));

        $this->assertDatabaseHas('stock_batches', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'total_kg' => 15,
            'warehouse_receive_pending' => false,
        ]);

        $this->assertSame('ready_for_dispatch', $order->fresh()->delivery_status);
        $this->assertSame(1, StockBatch::query()->where('product_id', $product->id)->count());
    }
}
