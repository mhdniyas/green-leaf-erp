<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\Inventory\StockMovementType;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WarehouseReceiverTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->receiver = User::factory()->create();
        $this->receiver->assignRole('warehouse_receiver');

        $this->warehouse = Warehouse::create([
            'name' => 'Vegetable Warehouse',
            'code' => 'VEG-WH',
            'is_active' => true,
        ]);
    }

    public function test_warehouse_receiver_can_view_checklist(): void
    {
        $date = Carbon::today()->format('Y-m-d');

        $response = $this->actingAs($this->receiver)
            ->get(route('warehouse.receiver.checklist', ['date' => $date]));

        $response->assertOk();
        $response->assertViewIs('warehouse-receiver.checklist');
        $response->assertSee('app-dialog-root');
        $response->assertSee('warehouse-confirm-form');
        $response->assertDontSee('onsubmit="return confirm(', false);
    }

    public function test_warehouse_receiver_is_redirected_from_dashboard(): void
    {
        $response = $this->actingAs($this->receiver)
            ->get(route('dashboard'));

        $response->assertRedirect(route('warehouse.receiver.checklist'));
    }

    public function test_checklist_is_forbidden_for_unauthorized_user(): void
    {
        $unauthorized = User::factory()->create();
        $unauthorized->assignRole('purchaser');

        $response = $this->actingAs($unauthorized)
            ->get(route('warehouse.receiver.checklist'));

        $response->assertForbidden();
    }

    public function test_warehouse_receiver_can_confirm_stock_batch(): void
    {
        $date = Carbon::today();
        $product = Product::factory()->create();
        $batch = StockBatch::factory()->create([
            'product_id' => $product->id,
            'received_at' => $date,
            'warehouse_receive_pending' => true,
        ]);

        $response = $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.confirm', $batch), [
                'warehouse_id' => $this->warehouse->id,
            ]);

        $response->assertRedirect(route('warehouse.receiver.checklist', ['date' => $date->format('Y-m-d')]));
        $response->assertSessionHas('success');

        $batch->refresh();
        $this->assertFalse($batch->warehouse_receive_pending);
        $this->assertNotNull($batch->warehouse_confirmed_at);
        $this->assertEquals($this->receiver->id, $batch->warehouse_confirmed_by);
        $this->assertEquals($this->warehouse->id, $batch->warehouse_id);
    }

    public function test_warehouse_receiver_cannot_confirm_already_confirmed_batch(): void
    {
        $date = Carbon::today();
        $product = Product::factory()->create();
        $batch = StockBatch::factory()->create([
            'product_id' => $product->id,
            'received_at' => $date,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->receiver->id,
        ]);

        $response = $this->actingAs($this->receiver)
            ->from(route('warehouse.receiver.checklist', ['date' => $date->format('Y-m-d')]))
            ->post(route('warehouse.receiver.confirm', $batch), [
                'warehouse_id' => $this->warehouse->id,
            ]);

        $response->assertRedirect(route('warehouse.receiver.checklist', ['date' => $date->format('Y-m-d')]));
        $response->assertSessionHasErrors();
    }

    public function test_warehouse_receiver_can_confirm_all_pending_batches_for_date(): void
    {
        $date = Carbon::today();
        $product = Product::factory()->create();

        $batch1 = StockBatch::factory()->create([
            'product_id' => $product->id,
            'received_at' => $date,
            'warehouse_receive_pending' => true,
        ]);
        $batch2 = StockBatch::factory()->create([
            'product_id' => $product->id,
            'received_at' => $date,
            'warehouse_receive_pending' => true,
        ]);

        $response = $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.confirm-all', ['date' => $date->format('Y-m-d')]));

        $response->assertRedirect(route('warehouse.receiver.checklist', ['date' => $date->format('Y-m-d')]));
        $response->assertSessionHas('success');

        $batch1->refresh();
        $batch2->refresh();

        $this->assertFalse($batch1->warehouse_receive_pending);
        $this->assertFalse($batch2->warehouse_receive_pending);
    }

    public function test_confirm_all_returns_error_if_no_pending_batches(): void
    {
        $date = Carbon::today();

        $response = $this->actingAs($this->receiver)
            ->from(route('warehouse.receiver.checklist', ['date' => $date->format('Y-m-d')]))
            ->post(route('warehouse.receiver.confirm-all', ['date' => $date->format('Y-m-d')]));

        $response->assertRedirect(route('warehouse.receiver.checklist', ['date' => $date->format('Y-m-d')]));
        $response->assertSessionHasErrors();
    }

    public function test_admin_can_access_warehouse_receiver_checklist(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('warehouse.receiver.checklist'))
            ->assertOk();
    }

    public function test_warehouse_receiver_can_view_loadout_details_page(): void
    {
        $shop = Shop::create([
            'code' => 'TEST_SHOP_1',
            'name' => 'Test Shop 1',
            'status' => 'active',
        ]);
        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'business_date' => Carbon::today()->format('Y-m-d'),
            'state' => 'approved',
            'created_by' => $this->receiver->id,
        ]);

        $response = $this->actingAs($this->receiver)
            ->get(route('warehouse.receiver.loadout.show', $order));

        $response->assertOk();
        $response->assertViewIs('warehouse-receiver.loadout_details');
        $response->assertSee('app-dialog-root');
        $response->assertSee('warehouse-confirm-form');
        $response->assertDontSee('onsubmit="return confirm(', false);
    }

    public function test_warehouse_receiver_can_load_item_and_reduce_inventory(): void
    {
        $shop = Shop::create([
            'code' => 'TEST_SHOP_2',
            'name' => 'Test Shop 2',
            'status' => 'active',
        ]);
        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'business_date' => Carbon::today()->format('Y-m-d'),
            'state' => 'approved',
            'created_by' => $this->receiver->id,
        ]);
        $product = Product::factory()->create();
        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 15.0,
            'approved_qty' => 15.0,
            'unit' => 'kg',
            'sorting_status' => 'allocated',
        ]);

        // Create a valid StockBatch first
        $batch = StockBatch::factory()->create([
            'product_id' => $product->id,
            'received_at' => Carbon::today(),
        ]);

        // Seed an In movement for the product so we can trace it
        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'created_by' => $this->receiver->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 100.0,
            'cost_per_unit' => 5.0,
        ]);

        $response = $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.loadout.item', $item));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $item->refresh();
        $this->assertEquals('loaded', $item->sorting_status);

        // Verify stock movement out exists
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 15.000,
        ]);
    }

    public function test_warehouse_receiver_can_load_all_items_in_order_and_reduce_inventory(): void
    {
        $shop = Shop::create([
            'code' => 'TEST_SHOP_3',
            'name' => 'Test Shop 3',
            'status' => 'active',
        ]);
        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'business_date' => Carbon::today()->format('Y-m-d'),
            'state' => 'approved',
            'created_by' => $this->receiver->id,
        ]);
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        $item1 = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product1->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'kg',
            'sorting_status' => 'allocated',
        ]);
        $item2 = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product2->id,
            'product_grade' => 'B',
            'requested_qty' => 20.0,
            'approved_qty' => 20.0,
            'unit' => 'kg',
            'sorting_status' => 'allocated',
        ]);

        // Seed stock batches and movements for product1 and product2
        $batch1 = StockBatch::factory()->create([
            'product_id' => $product1->id,
            'received_at' => Carbon::today(),
        ]);
        $batch2 = StockBatch::factory()->create([
            'product_id' => $product2->id,
            'received_at' => Carbon::today(),
        ]);

        StockMovement::create([
            'batch_id' => $batch1->id,
            'product_id' => $product1->id,
            'created_by' => $this->receiver->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 100.0,
            'cost_per_unit' => 5.0,
        ]);
        StockMovement::create([
            'batch_id' => $batch2->id,
            'product_id' => $product2->id,
            'created_by' => $this->receiver->id,
            'grade' => 'B',
            'type' => StockMovementType::In->value,
            'quantity' => 100.0,
            'cost_per_unit' => 5.0,
        ]);

        $response = $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.loadout.order-all', $order));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $item1->refresh();
        $item2->refresh();

        $this->assertEquals('loaded', $item1->sorting_status);
        $this->assertEquals('loaded', $item2->sorting_status);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product1->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 10.000,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product2->id,
            'grade' => 'B',
            'type' => StockMovementType::Out->value,
            'quantity' => 20.000,
        ]);
    }

    public function test_load_item_already_loaded_returns_error(): void
    {
        $shop = Shop::create([
            'code' => 'TEST_SHOP_4',
            'name' => 'Test Shop 4',
            'status' => 'active',
        ]);
        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'business_date' => Carbon::today()->format('Y-m-d'),
            'state' => 'approved',
            'created_by' => $this->receiver->id,
        ]);
        $product = Product::factory()->create();
        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 15.0,
            'approved_qty' => 15.0,
            'unit' => 'kg',
            'sorting_status' => 'loaded',
        ]);

        $response = $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.loadout.item', $item));

        $response->assertRedirect();
        $response->assertSessionHasErrors();
    }
}
