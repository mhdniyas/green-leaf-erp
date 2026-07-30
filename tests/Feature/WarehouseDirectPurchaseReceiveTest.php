<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\Supplier;
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

        $fruitWarehouse = Warehouse::query()->create([
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'is_active' => true,
        ]);
        $vegWarehouse = Warehouse::query()->create([
            'name' => 'Veg Warehouse',
            'code' => 'VEG',
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'name' => 'Tomato',
            'unit' => 'kg',
        ]);
        $secondProduct = Product::factory()->create([
            'name' => 'Apple',
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

        $firstItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 15,
            'approved_qty' => 15,
            'unit' => 'kg',
            'sorting_status' => 'pending',
            'notes' => 'Green Leaf Direct Purchase',
        ]);
        $secondItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $secondProduct->id,
            'requested_qty' => 8,
            'approved_qty' => 8,
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
                'items' => [
                    $firstItem->id => ['warehouse_id' => $vegWarehouse->id],
                    $secondItem->id => ['warehouse_id' => $fruitWarehouse->id],
                ],
            ])
            ->assertRedirect(route('warehouse.receiver.checklist', ['date' => '2026-07-17', 'tab' => 'pending']));

        $this->assertDatabaseHas('stock_batches', [
            'product_id' => $product->id,
            'warehouse_id' => $vegWarehouse->id,
            'total_kg' => 15,
            'warehouse_receive_pending' => false,
        ]);
        $this->assertDatabaseHas('stock_batches', [
            'product_id' => $secondProduct->id,
            'warehouse_id' => $fruitWarehouse->id,
            'total_kg' => 8,
            'warehouse_receive_pending' => false,
        ]);

        $this->assertSame('ready_for_dispatch', $order->fresh()->delivery_status);
        $this->assertSame(2, StockBatch::query()->whereIn('product_id', [$product->id, $secondProduct->id])->count());
    }

    public function test_warehouse_receiver_can_search_pending_vendor_delivery_by_product_and_category(): void
    {
        $warehouseReceiver = User::factory()->create();
        $warehouseReceiver->assignRole('warehouse_receiver');

        $vegetableCategory = Category::factory()->create(['name' => 'Vegetables']);
        $fruitCategory = Category::factory()->create(['name' => 'Fruits']);
        $tomato = Product::factory()->create([
            'name' => 'Tomato H',
            'sku' => 'TOM-H-001',
            'category_id' => $vegetableCategory->id,
        ]);
        $apple = Product::factory()->create([
            'name' => 'Apple Royal',
            'sku' => 'APL-ROYAL-001',
            'category_id' => $fruitCategory->id,
        ]);

        $matchingPo = PurchaseOrder::factory()->create([
            'supplier_id' => Supplier::factory()->create(['name' => 'Market Vendor'])->id,
        ]);
        $matchingGrn = GoodsReceived::factory()->create([
            'purchase_order_id' => $matchingPo->id,
            'grn_number' => 'GRN-TOMATO-001',
            'status' => 'pending_approval',
            'received_at' => '2026-07-17',
        ]);
        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $matchingGrn->id,
            'product_id' => $tomato->id,
            'received_qty' => 12,
        ]);

        $decoyPo = PurchaseOrder::factory()->create([
            'supplier_id' => Supplier::factory()->create(['name' => 'Fruit Vendor'])->id,
        ]);
        $decoyGrn = GoodsReceived::factory()->create([
            'purchase_order_id' => $decoyPo->id,
            'grn_number' => 'GRN-APPLE-001',
            'status' => 'pending_approval',
            'received_at' => '2026-07-17',
        ]);
        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $decoyGrn->id,
            'product_id' => $apple->id,
            'received_qty' => 8,
        ]);

        $this
            ->actingAs($warehouseReceiver)
            ->get(route('warehouse.receiver.checklist', [
                'date' => '2026-07-17',
                'tab' => 'pending',
                'receive_source' => 'vendor',
                'receive_category_id' => $vegetableCategory->id,
                'receive_search' => 'Tomato',
            ]))
            ->assertOk()
            ->assertSee('GRN-TOMATO-001')
            ->assertSee('Market Vendor')
            ->assertDontSee('GRN-APPLE-001')
            ->assertDontSee('Fruit Vendor');
    }

    public function test_warehouse_receiver_grn_receive_page_has_product_search_filters(): void
    {
        $warehouseReceiver = User::factory()->create();
        $warehouseReceiver->assignRole('warehouse_receiver');

        Warehouse::query()->create([
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'is_active' => true,
        ]);

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $product = Product::factory()->create([
            'name' => 'Tomato H',
            'sku' => 'TOM-H-GRN',
            'category_id' => $category->id,
        ]);
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => Supplier::factory()->create(['name' => 'Market Vendor'])->id,
        ]);
        $grn = GoodsReceived::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'grn_number' => 'GRN-FILTER-001',
            'status' => 'pending_approval',
            'received_at' => '2026-07-17',
        ]);
        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn->id,
            'product_id' => $product->id,
            'received_qty' => 12,
        ]);

        $this
            ->actingAs($warehouseReceiver)
            ->get(route('warehouse.receiver.receive-grn', $grn))
            ->assertOk()
            ->assertSee('Find Product')
            ->assertSee('grn-product-search')
            ->assertSee('Tomato H')
            ->assertSee('Vegetables');
    }
}
