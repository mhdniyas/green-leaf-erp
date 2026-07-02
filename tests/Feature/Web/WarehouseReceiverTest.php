<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\StockMovementType;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
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

    public function test_inventory_tab_can_be_filtered_by_warehouse(): void
    {
        $date = Carbon::today()->format('Y-m-d');
        $otherWarehouse = Warehouse::create([
            'name' => 'Fruit Warehouse',
            'code' => 'FRT-WH',
            'is_active' => true,
        ]);

        $productInSelectedWarehouse = Product::factory()->create(['name' => 'Tomato']);
        $productInOtherWarehouse = Product::factory()->create(['name' => 'Potato']);

        $selectedBatch = StockBatch::factory()->create([
            'product_id' => $productInSelectedWarehouse->id,
            'warehouse_id' => $this->warehouse->id,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted,
            'received_at' => $date,
        ]);

        $otherBatch = StockBatch::factory()->create([
            'product_id' => $productInOtherWarehouse->id,
            'warehouse_id' => $otherWarehouse->id,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted,
            'received_at' => $date,
        ]);

        StockMovement::create([
            'batch_id' => $selectedBatch->id,
            'product_id' => $productInSelectedWarehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 20.0,
            'cost_per_unit' => 5.0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        StockMovement::create([
            'batch_id' => $otherBatch->id,
            'product_id' => $productInOtherWarehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 15.0,
            'cost_per_unit' => 4.0,
            'warehouse_id' => $otherWarehouse->id,
        ]);

        $response = $this->actingAs($this->receiver)
            ->get(route('warehouse.receiver.checklist', [
                'date' => $date,
                'tab' => 'inventory',
                'warehouse_id' => $this->warehouse->id,
            ]));

        $response->assertOk();
        $response->assertSee('Warehouse');
        $response->assertSee('Tomato');
        $response->assertDontSee('Potato');
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
            'warehouse_id' => $this->warehouse->id,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted,
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
            'warehouse_id' => $this->warehouse->id,
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
            'warehouse_id' => $this->warehouse->id,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted,
        ]);
        $batch2 = StockBatch::factory()->create([
            'product_id' => $product2->id,
            'received_at' => Carbon::today(),
            'warehouse_id' => $this->warehouse->id,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted,
        ]);

        StockMovement::create([
            'batch_id' => $batch1->id,
            'product_id' => $product1->id,
            'created_by' => $this->receiver->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 100.0,
            'cost_per_unit' => 5.0,
            'warehouse_id' => $this->warehouse->id,
        ]);
        StockMovement::create([
            'batch_id' => $batch2->id,
            'product_id' => $product2->id,
            'created_by' => $this->receiver->id,
            'grade' => 'B',
            'type' => StockMovementType::In->value,
            'quantity' => 100.0,
            'cost_per_unit' => 5.0,
            'warehouse_id' => $this->warehouse->id,
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

    public function test_load_all_with_skip_unavailable_skips_out_of_stock_items(): void
    {
        $shop = Shop::create([
            'code' => 'TEST_SHOP_SKIP',
            'name' => 'Test Shop Skip',
            'status' => 'active',
        ]);
        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'business_date' => Carbon::today()->format('Y-m-d'),
            'state' => 'approved',
            'created_by' => $this->receiver->id,
        ]);

        $product1 = Product::factory()->create(['unit' => 'kg']);
        $product2 = Product::factory()->create(['unit' => 'kg']);

        // Item 1: approved qty = 10, stock = 100 (available)
        $item1 = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product1->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'kg',
            'sorting_status' => 'allocated',
            'product_grade' => 'A',
        ]);

        // Item 2: approved qty = 20, stock = 0 (unavailable)
        $item2 = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product2->id,
            'requested_qty' => 20.0,
            'approved_qty' => 20.0,
            'unit' => 'kg',
            'sorting_status' => 'allocated',
            'product_grade' => 'B',
        ]);

        // Add batch and stock for product 1
        $batch = StockBatch::factory()->create([
            'product_id' => $product1->id,
            'received_at' => Carbon::today()->format('Y-m-d'),
            'warehouse_id' => $this->warehouse->id,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted,
            'total_kg' => 100.0,
        ]);
        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product1->id,
            'created_by' => $this->receiver->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 100.0,
            'cost_per_unit' => 5.0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // Post with skip_unavailable = 1
        $response = $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.loadout.order-all', $order), [
                'skip_unavailable' => '1',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $item1->refresh();
        $item2->refresh();

        // Item 1 should be loaded, Item 2 should remain pending
        $this->assertEquals('loaded', $item1->sorting_status);
        $this->assertEquals('allocated', $item2->sorting_status);

        // Check only product 1 has Out movement
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product1->id,
            'type' => StockMovementType::Out->value,
            'quantity' => 10.0,
        ]);
        $this->assertDatabaseMissing('stock_movements', [
            'product_id' => $product2->id,
            'type' => StockMovementType::Out->value,
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

    public function test_loadout_item_with_wastage_discrepancy(): void
    {
        $shop = Shop::create([
            'code' => 'TEST_SHOP_WD',
            'name' => 'Test Shop WD',
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

        $batch = StockBatch::factory()->create([
            'product_id' => $product->id,
            'received_at' => Carbon::today(),
            'warehouse_id' => $this->warehouse->id,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'created_by' => $this->receiver->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 100.0,
            'cost_per_unit' => 5.0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.loadout.item', $item), [
                'loaded_qty' => 10.0,
                'discrepancy_type' => 'wastage',
                'discrepancy_note' => 'damaged during loading',
            ]);

        $response->assertRedirect();
        $item->refresh();

        $this->assertEquals(10.0, (float) $item->loaded_qty);
        $this->assertEquals('none', $item->loadout_discrepancy_type);
        $this->assertNull($item->loadout_discrepancy_note);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 10.0,
        ]);

        $this->assertDatabaseMissing('stock_movements', [
            'product_id' => $product->id,
            'grade' => 'A',
            'type' => StockMovementType::Wastage->value,
        ]);

        $this->assertDatabaseMissing('wastage_entries', [
            'product_id' => $product->id,
            'grade' => 'A',
        ]);
    }

    public function test_process_receive_grn_with_wastage_discrepancy(): void
    {
        $product = Product::factory()->create(['unit' => 'kg']);
        $supplier = Supplier::factory()->create();
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'order_date' => Carbon::today(),
            'created_by' => $this->receiver->id,
        ]);
        $poItem = $po->items()->create([
            'product_id' => $product->id,
            'purchase_unit' => 'kg',
            'quantity' => 10.0,
            'unit_price' => 5.00,
            'price_basis' => 'per_kg',
        ]);
        $grn = GoodsReceived::create([
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-TEST-DISC-1',
            'status' => 'pending_approval',
            'received_by' => $this->receiver->id,
            'received_at' => Carbon::today(),
        ]);
        $grnItem = $grn->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'received_qty' => 10.0,
            'variance' => 0.0,
        ]);

        $response = $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.process-receive-grn', $grn), [
                'warehouse_id' => $this->warehouse->id,
                'items' => [
                    $grnItem->id => [
                        'warehouse_id' => $this->warehouse->id,
                        'received_qty' => 8.0,
                        'discrepancy_type' => 'wastage',
                        'discrepancy_note' => 'Short received due to damage',
                    ],
                ],
            ]);

        $response->assertRedirect();
        $grn->refresh();
        $grnItem->refresh();

        $this->assertEquals('approved', $grn->status);
        $this->assertEquals(8.0, (float) $grnItem->received_qty);
        $this->assertEquals(-2.0, (float) $grnItem->variance);
        $this->assertEquals('wastage', $grnItem->discrepancy_type);
        $this->assertEquals('Short received due to damage', $grnItem->discrepancy_note);

        $this->assertDatabaseHas('wastage_entries', [
            'product_id' => $product->id,
            'grade' => 'U',
            'quantity' => 2.0,
            'notes' => 'Receiving discrepancy wastage: Short received due to damage',
        ]);
    }

    public function test_inflows_displays_both_movements_and_confirmed_batches(): void
    {
        $date = Carbon::today();
        $product = Product::factory()->create(['name' => 'Test Tomato']);

        // Create a confirmed batch (warehouse_receive_pending = false)
        $batch = StockBatch::factory()->create([
            'product_id' => $product->id,
            'received_at' => $date,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'total_kg' => 123.45,
            'reference' => 'BATCH-REF-123',
        ]);

        // Create an IN StockMovement
        $movement = StockMovement::factory()->create([
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'type' => StockMovementType::In,
            'quantity' => 50.00,
            'grade' => 'A',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->receiver)
            ->get(route('warehouse.receiver.checklist', ['date' => $date->format('Y-m-d')]));

        $response->assertOk();
        $inflows = $response->original->getData()['inflows'];

        $this->assertNotEmpty($inflows);

        // Assert that the batch is represented in inflows
        $batchInflow = $inflows->where('source', 'batch')->firstWhere('reference', 'BATCH-REF-123');
        $this->assertNotNull($batchInflow);
        $this->assertEquals(123.45, $batchInflow->quantity);
        $this->assertEquals('Unsorted', $batchInflow->grade_label);

        // Assert that the movement is represented in inflows
        $movementInflow = $inflows->where('source', 'movement')->firstWhere('quantity', 50.00);
        $this->assertNotNull($movementInflow);
        $this->assertEquals('Grade A — Premium', $movementInflow->grade_label);

        $response->assertSee('Test Tomato');
        $response->assertSee('+123.45');
        $response->assertSee('+50.00');
    }

    public function test_process_receive_grn_with_custom_per_item_warehouse(): void
    {
        $product1 = Product::factory()->create(['unit' => 'kg']);
        $product2 = Product::factory()->create(['unit' => 'kg']);
        $supplier = Supplier::factory()->create();
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'order_date' => Carbon::today(),
            'created_by' => $this->receiver->id,
        ]);
        $poItem1 = $po->items()->create([
            'product_id' => $product1->id,
            'purchase_unit' => 'kg',
            'quantity' => 10.0,
            'unit_price' => 5.00,
            'price_basis' => 'per_kg',
        ]);
        $poItem2 = $po->items()->create([
            'product_id' => $product2->id,
            'purchase_unit' => 'kg',
            'quantity' => 20.0,
            'unit_price' => 6.00,
            'price_basis' => 'per_kg',
        ]);
        $grn = GoodsReceived::create([
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-TEST-CUSTOM-WH',
            'status' => 'pending_approval',
            'received_by' => $this->receiver->id,
            'received_at' => Carbon::today(),
        ]);
        $grnItem1 = $grn->items()->create([
            'purchase_order_item_id' => $poItem1->id,
            'product_id' => $product1->id,
            'received_qty' => 10.0,
            'variance' => 0.0,
        ]);
        $grnItem2 = $grn->items()->create([
            'purchase_order_item_id' => $poItem2->id,
            'product_id' => $product2->id,
            'received_qty' => 20.0,
            'variance' => 0.0,
        ]);

        $customWarehouse = Warehouse::create([
            'name' => 'Custom Fruits Warehouse',
            'code' => 'FRU-WH',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.process-receive-grn', $grn), [
                'warehouse_id' => $this->warehouse->id, // Default target
                'items' => [
                    $grnItem1->id => [
                        'warehouse_id' => $this->warehouse->id, // item 1 goes to default
                        'received_qty' => 10.0,
                        'discrepancy_type' => 'none',
                        'discrepancy_note' => '',
                    ],
                    $grnItem2->id => [
                        'warehouse_id' => $customWarehouse->id, // item 2 goes to custom
                        'received_qty' => 20.0,
                        'discrepancy_type' => 'none',
                        'discrepancy_note' => '',
                    ],
                ],
            ]);

        $response->assertRedirect();

        // Assert that the batch for product1 is in the default warehouse
        $batch1 = StockBatch::where('goods_received_id', $grn->id)
            ->where('product_id', $product1->id)
            ->first();
        $this->assertNotNull($batch1);
        $this->assertEquals($this->warehouse->id, $batch1->warehouse_id);

        // Assert that the batch for product2 is in the custom warehouse
        $batch2 = StockBatch::where('goods_received_id', $grn->id)
            ->where('product_id', $product2->id)
            ->first();
        $this->assertNotNull($batch2);
        $this->assertEquals($customWarehouse->id, $batch2->warehouse_id);
    }

    public function test_warehouse_receiver_load_all_insufficient_stock_only_loads_available(): void
    {
        $shop = Shop::create([
            'code' => 'TEST_SHOP_SHORTAGE',
            'name' => 'Test Shop Shortage',
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
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'kg',
            'sorting_status' => 'allocated',
        ]);

        $batch = StockBatch::factory()->create([
            'product_id' => $product->id,
            'received_at' => Carbon::today(),
            'warehouse_id' => $this->warehouse->id,
            'warehouse_receive_pending' => false,
            'status' => BatchStatus::Sorted,
        ]);

        // Seed only 4.0 kg available in stock (less than approved 10.0)
        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'created_by' => $this->receiver->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 4.0,
            'cost_per_unit' => 5.0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.loadout.order-all', $order));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $item->refresh();

        $this->assertEquals('loaded', $item->sorting_status);
        $this->assertEquals(4.0, (float) $item->loaded_qty);
        $this->assertEquals('none', $item->loadout_discrepancy_type);
        $this->assertNull($item->loadout_discrepancy_note);

        // Deducted Out quantity
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 4.000,
        ]);

        // No adjustment quantity
        $this->assertDatabaseMissing('stock_movements', [
            'product_id' => $product->id,
            'grade' => 'A',
            'type' => StockMovementType::Adjustment->value,
        ]);
    }

    public function test_warehouse_receiver_can_dispatch_partial_order(): void
    {
        $shop = Shop::create([
            'code' => 'TEST_SHOP_PARTIAL',
            'name' => 'Test Shop Partial',
            'status' => 'active',
        ]);
        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'business_date' => Carbon::today()->format('Y-m-d'),
            'state' => 'approved',
            'created_by' => $this->receiver->id,
            'delivery_status' => 'pending_delivery',
        ]);
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        // item1 is already loaded
        $item1 = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product1->id,
            'product_grade' => 'A',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'loaded_qty' => 5.0,
            'unit' => 'kg',
            'sorting_status' => 'loaded',
        ]);

        // item2 is pending
        $item2 = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product2->id,
            'product_grade' => 'A',
            'requested_qty' => 8.0,
            'approved_qty' => 8.0,
            'unit' => 'kg',
            'sorting_status' => 'allocated',
        ]);

        $batch2 = StockBatch::factory()->create([
            'product_id' => $product2->id,
            'received_at' => Carbon::today(),
        ]);

        // Seed stock for item2
        StockMovement::create([
            'batch_id' => $batch2->id,
            'product_id' => $product2->id,
            'created_by' => $this->receiver->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 10.0,
            'cost_per_unit' => 5.0,
        ]);

        $response = $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.loadout.order.dispatch-partial', $order));

        $response->assertRedirect();

        $order->refresh();
        $item2->refresh();

        $this->assertEquals('ready_for_dispatch', $order->delivery_status);
        $this->assertEquals('allocated', $item2->sorting_status);
        $this->assertNull($item2->loaded_qty);

        // Assert database does NOT have adjustment stock movement for item2
        $this->assertDatabaseMissing('stock_movements', [
            'product_id' => $product2->id,
            'grade' => 'A',
            'type' => StockMovementType::Adjustment->value,
        ]);
    }

    public function test_warehouse_receiver_can_dispatch_split_delivery(): void
    {
        $shop = Shop::create([
            'code' => 'TEST_SHOP_SPLIT',
            'name' => 'Test Shop Split',
            'status' => 'active',
        ]);
        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'business_date' => Carbon::today()->format('Y-m-d'),
            'state' => 'approved',
            'created_by' => $this->receiver->id,
            'delivery_status' => 'pending_delivery',
        ]);
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        // item1 is loaded
        $item1 = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product1->id,
            'product_grade' => 'A',
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'loaded_qty' => 5.0,
            'unit' => 'kg',
            'sorting_status' => 'loaded',
        ]);

        // item2 is pending
        $item2 = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product2->id,
            'product_grade' => 'A',
            'requested_qty' => 8.0,
            'approved_qty' => 8.0,
            'unit' => 'kg',
            'sorting_status' => 'allocated',
        ]);

        $response = $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.loadout.order.dispatch', $order));

        $response->assertRedirect();

        $order->refresh();
        $item2->refresh();

        // The order is moved to delivery (ready_for_dispatch), but item2 is STILL allocated/pending (not loaded!)
        $this->assertEquals('ready_for_dispatch', $order->delivery_status);
        $this->assertEquals('allocated', $item2->sorting_status);
    }

    public function test_warehouse_receiver_can_ship_order(): void
    {
        $shop = Shop::create([
            'code' => 'TEST_SHOP_SHIP',
            'name' => 'Test Shop Ship',
            'status' => 'active',
        ]);
        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'business_date' => Carbon::today()->format('Y-m-d'),
            'state' => 'approved',
            'created_by' => $this->receiver->id,
            'delivery_status' => 'ready_for_dispatch',
        ]);

        $response = $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.loadout.order.ship', $order));

        $response->assertRedirect();

        $order->refresh();
        $this->assertEquals('in_transit', $order->delivery_status);
        $this->assertTrue($order->is_allocation_completed);
    }
}
