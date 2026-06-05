<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\WastageReason;
use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseSortingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);
    }

    public function test_guest_user_cannot_access_sorting_checklist(): void
    {
        $response = $this->get(route('inventory.sorting.checklist'));

        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_cannot_access_sorting_checklist(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('inventory.sorting.checklist'));

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_access_sorting_checklist(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('warehouse.checklist.view');
        $user->givePermissionTo('sales.order.view');

        $response = $this->actingAs($user)
            ->get(route('inventory.sorting.checklist'));

        $response->assertOk();
        $response->assertSee('Dispatch Sorting Progress');
    }

    public function test_guest_user_cannot_access_shop_orders(): void
    {
        $response = $this->get(route('inventory.sorting.shop-orders'));

        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_cannot_access_shop_orders(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('inventory.sorting.shop-orders'));

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_access_shop_orders(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('warehouse.checklist.view');

        $response = $this->actingAs($user)
            ->get(route('inventory.sorting.shop-orders'));

        $response->assertOk();
        $response->assertSee('Shop Orders Allocation');
    }

    public function test_unauthorized_user_cannot_toggle_sorting_item(): void
    {
        $user = User::factory()->create();

        $shop = Shop::create([
            'code' => 'TEST_SHOP',
            'name' => 'Test Shop',
        ]);

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $user->id,
        ]);

        $product = Product::factory()->create();
        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'kg',
            'fulfillment_type' => 'warehouse',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('inventory.sorting.checklist.toggle', $item));

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_toggle_sorting_item(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['warehouse.checklist.view', 'warehouse.checklist.toggle', 'inventory.sorting.process']);

        $shop = Shop::create([
            'code' => 'TEST_SHOP',
            'name' => 'Test Shop',
        ]);

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $user->id,
        ]);

        $product = Product::factory()->create();
        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'kg',
            'fulfillment_type' => 'warehouse',
            'is_sorted' => false,
        ]);

        // Toggle to true
        $response = $this->actingAs($user)
            ->postJson(route('inventory.sorting.checklist.toggle', $item));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('item.is_sorted', true);
        $response->assertJsonPath('item.sorted_by_name', $user->name);
        $response->assertJsonPath('shop_progress.percentage', 100);

        $this->assertDatabaseHas('shop_order_items', [
            'id' => $item->id,
            'is_sorted' => true,
            'sorted_by' => $user->id,
        ]);

        // Toggle back to false
        $response = $this->actingAs($user)
            ->postJson(route('inventory.sorting.checklist.toggle', $item));

        $response->assertOk();
        $response->assertJsonPath('item.is_sorted', false);
        $response->assertJsonPath('item.sorted_by_name', null);
        $response->assertJsonPath('shop_progress.percentage', 0);

        $this->assertDatabaseHas('shop_order_items', [
            'id' => $item->id,
            'is_sorted' => false,
            'sorted_by' => null,
            'sorted_at' => null,
        ]);
    }

    public function test_warehouse_manager_can_access_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('warehouse');

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_warehouse_manager_can_receive_goods_and_create_grn(): void
    {
        $user = User::factory()->create();
        $user->assignRole('warehouse');

        $supplier = Supplier::factory()->create();
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-'.time(),
            'status' => POStatus::SentToSupplier,
            'order_date' => today()->toDateString(),
            'created_by' => $user->id,
        ]);

        $product = Product::factory()->create();
        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'purchase_unit' => 'kg',
            'quantity' => 50.0,
            'unit_price' => 2.50,
            'price_basis' => 'per_kg',
        ]);

        $payload = [
            'purchase_order_id' => $po->id,
            'received_at' => today()->toDateString(),
            'notes' => 'Received with some shortage',
            'transport_cost' => 10.00,
            'labour_cost' => 5.00,
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $product->id,
                    'received_qty' => 45.0,
                ],
            ],
        ];

        $response = $this->actingAs($user)
            ->postJson(route('inventory.sorting.checklist.grn'), $payload);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => POStatus::Received->value,
        ]);

        $this->assertDatabaseHas('goods_received', [
            'purchase_order_id' => $po->id,
            'received_by' => $user->id,
            'notes' => 'Received with some shortage',
            'status' => 'pending_approval',
        ]);

        // Assert that Stock Batch was NOT created yet
        $this->assertDatabaseMissing('stock_batches', [
            'product_id' => $product->id,
        ]);

        // Create a manager and approve the GRN
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $grn = GoodsReceived::latest('id')->first();
        $approveResponse = $this->actingAs($manager)
            ->post(route('purchasing.grns.approve', $grn));

        $approveResponse->assertRedirect(route('purchasing.grns.show', $grn));
        $this->assertEquals('approved', $grn->fresh()->status);

        // Check Stock Batch was created now
        $this->assertDatabaseHas('stock_batches', [
            'product_id' => $product->id,
            'total_kg' => 45.0,
        ]);
    }

    public function test_warehouse_manager_can_carry_over_stock_batch(): void
    {
        $user = User::factory()->create();
        $user->assignRole('warehouse');

        $product = Product::factory()->create();
        $batch = StockBatch::create([
            'product_id' => $product->id,
            'created_by' => $user->id,
            'reference' => 'BATCH-'.time(),
            'received_at' => today()->toDateString(),
            'total_kg' => 100.0,
            'cost_per_kg' => 1.50,
            'status' => BatchStatus::Pending,
        ]);

        $tomorrow = today()->addDay()->format('Y-m-d');

        $response = $this->actingAs($user)
            ->postJson(route('inventory.sorting.checklist.carry-over', $batch), [
                'target_date' => $tomorrow,
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('received_at', $tomorrow);

        $batch->refresh();
        $this->assertEquals($tomorrow, $batch->received_at->format('Y-m-d'));
    }

    public function test_warehouse_manager_can_record_wastage_for_stock_batch(): void
    {
        $user = User::factory()->create();
        $user->assignRole('warehouse');
        // Give the role explicit permission to write wastage if not assigned via RolePermissionSeeder
        $user->givePermissionTo('inventory.wastage.record');

        $product = Product::factory()->create();
        $batch = StockBatch::create([
            'product_id' => $product->id,
            'created_by' => $user->id,
            'reference' => 'BATCH-'.time(),
            'received_at' => today()->toDateString(),
            'total_kg' => 100.0,
            'cost_per_kg' => 1.50,
            'status' => BatchStatus::Pending,
        ]);

        $payload = [
            'quantity' => 10.0,
            'reason' => WastageReason::Rotten->value,
            'notes' => 'Some tomatoes were spoiled',
        ];

        $response = $this->actingAs($user)
            ->postJson(route('inventory.sorting.checklist.wastage', $batch), $payload);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('wastage_entries', [
            'batch_id' => $batch->id,
            'product_id' => $product->id,
            'quantity' => 10.0,
            'reason' => WastageReason::Rotten->value,
            'recorded_by' => $user->id,
        ]);
    }

    public function test_warehouse_manager_can_complete_shop_order_allocation(): void
    {
        $user = User::factory()->create();
        $user->assignRole('warehouse');

        $shop = Shop::create([
            'code' => 'TEST_SHOP_FINAL',
            'name' => 'Final Test Shop',
        ]);

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $user->id,
            'is_allocation_completed' => false,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('inventory.sorting.checklist.complete-order', $order), [
                'sorting_notes' => 'Allocated all items successfully with zero discrepancies.',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('shop_orders', [
            'id' => $order->id,
            'is_allocation_completed' => true,
            'sorting_notes' => 'Allocated all items successfully with zero discrepancies.',
        ]);
    }

    public function test_batch_allocation_reduces_remaining_qty(): void
    {
        $user = User::factory()->create();

        $product = Product::factory()->create();
        $batch = StockBatch::create([
            'product_id' => $product->id,
            'created_by' => $user->id,
            'reference' => 'BATCH-TEST-ALLOC',
            'received_at' => today()->toDateString(),
            'total_kg' => 100.0,
            'cost_per_kg' => 1.50,
            'status' => BatchStatus::Pending,
        ]);

        $shop = Shop::create([
            'code' => 'TEST_SHOP_ALLOC',
            'name' => 'Alloc Test Shop',
        ]);

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $user->id,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 40.0,
            'approved_qty' => 40.0,
            'unit' => 'kg',
            'fulfillment_type' => 'warehouse',
            'is_sorted' => true,
            'sorting_status' => 'allocated',
        ]);

        $batch->refresh();
        $this->assertEquals(40.0, $batch->allocated_qty);
        $this->assertEquals(60.0, $batch->remaining_qty);
    }
}
