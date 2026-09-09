<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Purchasing\POStatus;
use App\Models\AdvanceReceiveMatch;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminCashbookInventoryAcceptPendingBillsTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $unauthorizedUser;

    private Warehouse $warehouseA;

    private Warehouse $warehouseB;

    private Category $category;

    private Product $tomato;

    private Product $apple;

    private Supplier $supplier;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->unauthorizedUser = User::factory()->create();

        $this->warehouseA = Warehouse::create([
            'name' => 'Vegetable Warehouse',
            'code' => 'WH-VEG',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'name' => 'Fruit Warehouse',
            'code' => 'WH-FRUIT',
            'is_active' => true,
        ]);

        $this->shop = Shop::factory()->create([
            'name' => 'Main Retail Shop',
            'code' => 'SH-MAIN',
        ]);

        $this->category = Category::create([
            'name' => 'Vegetables',
            'code' => 'VEG',
        ]);

        $this->tomato = Product::create([
            'name' => 'Tomato',
            'sku' => 'TOM-001',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouseA->id,
            'unit' => 'kg',
            'base_price' => 10.00,
            'status' => 'active',
        ]);

        $this->apple = Product::create([
            'name' => 'Apple',
            'sku' => 'APP-001',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouseB->id,
            'unit' => 'kg',
            'base_price' => 20.00,
            'status' => 'active',
        ]);

        $this->supplier = Supplier::factory()->create([
            'name' => 'Test Supplier',
        ]);
    }

    public function test_pending_bills_days_summary_returns_grouped_counts_for_selected_warehouse(): void
    {
        $this->createPendingBillGrn($this->warehouseA, $this->tomato, 100.0, '2026-09-08');
        $this->createPendingBillGrn($this->warehouseA, $this->tomato, 50.0, '2026-09-08');
        $this->createPendingBillGrn($this->warehouseA, $this->tomato, 200.0, '2026-09-09');

        // Warehouse B bill - should not appear in Warehouse A query
        $this->createPendingBillGrn($this->warehouseB, $this->apple, 300.0, '2026-09-09');

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('admin.cashbook.inventory.pending-bills-days', [
                'warehouse_id' => $this->warehouseA->id,
            ]));

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.warehouse_id', $this->warehouseA->id);
        $response->assertJsonPath('data.total_bills', 3);
        $this->assertEquals(350.0, $response->json('data.total_qty'));

        $days = $response->json('data.days');
        $this->assertCount(2, $days);

        $day09 = collect($days)->firstWhere('date', '2026-09-09');
        $this->assertNotNull($day09);
        $this->assertSame(1, $day09['bill_count']);
        $this->assertEquals(200.0, $day09['total_qty']);

        $day08 = collect($days)->firstWhere('date', '2026-09-08');
        $this->assertNotNull($day08);
        $this->assertSame(2, $day08['bill_count']);
        $this->assertEquals(150.0, $day08['total_qty']);
    }

    public function test_pending_bills_day_details_returns_line_items_for_expanded_date(): void
    {
        $grn = $this->createPendingBillGrn($this->warehouseA, $this->tomato, 120.0, '2026-09-09');

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('admin.cashbook.inventory.pending-bills-day-details', [
                'warehouse_id' => $this->warehouseA->id,
                'date' => '2026-09-09',
            ]));

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.date', '2026-09-09');

        $bills = $response->json('data.bills');
        $this->assertCount(1, $bills);
        $this->assertSame($grn->grn_number, $bills[0]['grn_number']);
        $this->assertSame('Test Supplier', $bills[0]['supplier_name']);
        $this->assertEquals(120.0, $bills[0]['qty']);
        $this->assertSame('Pending Approval', $bills[0]['status']);
    }

    public function test_accept_pending_bills_approves_selected_dates_and_returns_auto_match_preview(): void
    {
        // 1. Create open Advance in Warehouse A for Tomato (100 kg)
        $this->createConfirmedAdvanceGrn($this->warehouseA, $this->tomato, 100.0, '2026-09-05');

        // 2. Create pending bills: 2 on 2026-09-08 (50 kg each), 1 on 2026-09-09 (80 kg)
        $grn1 = $this->createPendingBillGrn($this->warehouseA, $this->tomato, 50.0, '2026-09-08');
        $grn2 = $this->createPendingBillGrn($this->warehouseA, $this->tomato, 50.0, '2026-09-08');
        $grn3 = $this->createPendingBillGrn($this->warehouseA, $this->tomato, 80.0, '2026-09-09');

        // Accept only 2026-09-08 bills
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.accept-pending-bills'), [
                'warehouse_id' => $this->warehouseA->id,
                'dates' => ['2026-09-08'],
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.approved', 2);
        $response->assertJsonPath('data.skipped', 0);
        $response->assertJsonPath('data.failed', 0);

        // Verify GRN 1 and GRN 2 were approved and warehouse received canonically
        $this->assertSame('approved', $grn1->fresh()->status);
        $this->assertSame('approved', $grn2->fresh()->status);

        // Verify StockBatches are confirmed and available in warehouse inventory (warehouse_receive_pending = false)
        $batches1 = StockBatch::where('goods_received_id', $grn1->id)->get();
        $this->assertTrue($batches1->isNotEmpty());
        $this->assertFalse((bool) $batches1->first()->warehouse_receive_pending);
        $this->assertNotNull($batches1->first()->warehouse_confirmed_at);

        // Verify GRN 3 on 2026-09-09 remained pending
        $this->assertSame('pending_approval', $grn3->fresh()->status);

        // Verify Auto Match Preview was computed and returned
        $preview = $response->json('data.auto_match_preview');
        $this->assertNotNull($preview);
        // The two 50kg bills on 2026-09-08 match the 100kg open advance exactly
        $this->assertSame(2, $preview['matchable_with_advances']);
        $this->assertSame(1, $preview['advances_that_can_fully_clear']);

        // Verify Auto Match did NOT execute automatically (Advance remains open)
        $this->assertSame('bill_pending', GoodsReceived::where('receipt_type', 'warehouse_advance')->first()->bill_status);
    }

    public function test_pending_bill_10kg_no_advance_approves_and_receives_adding_10kg_to_inventory(): void
    {
        $grn = $this->createPendingBillGrn($this->warehouseA, $this->tomato, 10.0, '2026-09-09');

        $initialStock = (float) StockBatch::where('warehouse_id', $this->warehouseA->id)
            ->where('product_id', $this->tomato->id)
            ->where('warehouse_receive_pending', false)
            ->sum('total_kg');
        $this->assertEquals(0.0, $initialStock);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.accept-pending-bills'), [
                'warehouse_id' => $this->warehouseA->id,
                'purchase_order_id' => $grn->purchase_order_id,
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.approved', 1);

        $this->assertSame('approved', $grn->fresh()->status);

        $batch = StockBatch::where('goods_received_id', $grn->id)->first();
        $this->assertNotNull($batch);
        $this->assertFalse((bool) $batch->warehouse_receive_pending);
        $this->assertEquals(10.0, $batch->total_kg);

        $finalStock = (float) StockBatch::where('warehouse_id', $this->warehouseA->id)
            ->where('product_id', $this->tomato->id)
            ->where('warehouse_receive_pending', false)
            ->sum('total_kg');
        $this->assertEquals(10.0, $finalStock);
    }

    public function test_advance_4kg_in_inventory_and_bill_10kg_approves_and_receives_only_adding_6kg_net_stock(): void
    {
        // 1. Advance 4 KG already in inventory
        $advGrn = $this->createConfirmedAdvanceGrn($this->warehouseA, $this->tomato, 4.0, '2026-09-05');
        $advItem = $advGrn->items->first();

        $stockAfterAdvance = (float) StockBatch::where('warehouse_id', $this->warehouseA->id)
            ->where('product_id', $this->tomato->id)
            ->where('warehouse_receive_pending', false)
            ->sum('total_kg');
        $this->assertEquals(4.0, $stockAfterAdvance);

        // 2. Pending Bill 10 KG
        $billGrn = $this->createPendingBillGrn($this->warehouseA, $this->tomato, 10.0, '2026-09-09');
        $billItem = $billGrn->items->first();

        // 3. Pre-matched Advance of 4 KG
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $advGrn->id,
            'advance_goods_received_item_id' => $advItem->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billItem->id,
            'product_id' => $this->tomato->id,
            'matched_qty' => 4.0,
            'matched_unit' => 'kg',
            'base_qty' => 4.0,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->adminUser->id,
            'confirmed_at' => now(),
            'notes' => 'Pre-match 4kg',
        ]);

        // 4. Approve & Receive Bill
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.accept-pending-bills'), [
                'warehouse_id' => $this->warehouseA->id,
                'grn_id' => $billGrn->id,
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');

        // Bill batch total_kg should be 10 - 4 = 6 KG
        $billBatch = StockBatch::where('goods_received_id', $billGrn->id)->first();
        $this->assertNotNull($billBatch);
        $this->assertEquals(6.0, $billBatch->total_kg);
        $this->assertFalse((bool) $billBatch->warehouse_receive_pending);

        // Final represented physical inventory = 4 (Advance) + 6 (Bill new) = 10 KG
        $finalStock = (float) StockBatch::where('warehouse_id', $this->warehouseA->id)
            ->where('product_id', $this->tomato->id)
            ->where('warehouse_receive_pending', false)
            ->sum('total_kg');
        $this->assertEquals(10.0, $finalStock);
    }

    public function test_fully_covered_advance_10kg_and_bill_10kg_approves_and_receives_with_zero_inventory_change(): void
    {
        // 1. Advance 10 KG already in inventory
        $advGrn = $this->createConfirmedAdvanceGrn($this->warehouseA, $this->tomato, 10.0, '2026-09-05');
        $advItem = $advGrn->items->first();

        // 2. Pending Bill 10 KG
        $billGrn = $this->createPendingBillGrn($this->warehouseA, $this->tomato, 10.0, '2026-09-09');
        $billItem = $billGrn->items->first();

        // 3. Fully matched Advance of 10 KG
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $advGrn->id,
            'advance_goods_received_item_id' => $advItem->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billItem->id,
            'product_id' => $this->tomato->id,
            'matched_qty' => 10.0,
            'matched_unit' => 'kg',
            'base_qty' => 10.0,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->adminUser->id,
            'confirmed_at' => now(),
            'notes' => 'Full match 10kg',
        ]);

        // 4. Approve & Receive Bill
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.accept-pending-bills'), [
                'warehouse_id' => $this->warehouseA->id,
                'grn_id' => $billGrn->id,
            ]);

        $response->assertOk();

        // Bill batch total_kg should be 10 - 10 = 0 KG
        $billBatch = StockBatch::where('goods_received_id', $billGrn->id)->first();
        $this->assertNotNull($billBatch);
        $this->assertEquals(0.0, $billBatch->total_kg);

        // Final inventory is still 10 KG (net change = 0)
        $finalStock = (float) StockBatch::where('warehouse_id', $this->warehouseA->id)
            ->where('product_id', $this->tomato->id)
            ->where('warehouse_receive_pending', false)
            ->sum('total_kg');
        $this->assertEquals(10.0, $finalStock);
    }

    public function test_bulk_10_pending_bills_approve_and_receive_all_updates_all_to_received_and_updates_inventory(): void
    {
        $grnIds = [];
        for ($i = 0; $i < 10; $i++) {
            $grn = $this->createPendingBillGrn($this->warehouseA, $this->tomato, 10.0, '2026-09-08');
            $grnIds[] = $grn->id;
        }

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.accept-pending-bills'), [
                'warehouse_id' => $this->warehouseA->id,
                'dates' => ['2026-09-08'],
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.approved', 10);

        // All 10 bills confirmed in warehouse
        $batches = StockBatch::whereIn('goods_received_id', $grnIds)->get();
        $this->assertCount(10, $batches);
        foreach ($batches as $b) {
            $this->assertFalse((bool) $b->warehouse_receive_pending);
        }

        // Total inventory = 100 KG
        $totalStock = (float) StockBatch::where('warehouse_id', $this->warehouseA->id)
            ->where('product_id', $this->tomato->id)
            ->where('warehouse_receive_pending', false)
            ->sum('total_kg');
        $this->assertEquals(100.0, $totalStock);
    }

    public function test_repeating_action_cannot_duplicate_inventory(): void
    {
        $grn = $this->createPendingBillGrn($this->warehouseA, $this->tomato, 15.0, '2026-09-09');

        // First call: Approve & Receive
        $response1 = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.accept-pending-bills'), [
                'warehouse_id' => $this->warehouseA->id,
                'grn_id' => $grn->id,
            ]);
        $response1->assertOk();
        $response1->assertJsonPath('data.approved', 1);

        $stockAfterFirst = (float) StockBatch::where('warehouse_id', $this->warehouseA->id)
            ->where('product_id', $this->tomato->id)
            ->where('warehouse_receive_pending', false)
            ->sum('total_kg');
        $this->assertEquals(15.0, $stockAfterFirst);

        // Second call: Replay same action
        $response2 = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.accept-pending-bills'), [
                'warehouse_id' => $this->warehouseA->id,
                'grn_id' => $grn->id,
            ]);
        $response2->assertOk();
        $response2->assertJsonPath('data.already_approved', 1);

        // Inventory must remain 15.0 KG (no duplicates)
        $stockAfterSecond = (float) StockBatch::where('warehouse_id', $this->warehouseA->id)
            ->where('product_id', $this->tomato->id)
            ->where('warehouse_receive_pending', false)
            ->sum('total_kg');
        $this->assertEquals(15.0, $stockAfterSecond);
    }

    public function test_single_pending_grn_approves_and_receives_by_grn_id_updating_status_and_inventory(): void
    {
        $grn = $this->createPendingBillGrn($this->warehouseA, $this->tomato, 25.0, '2026-09-09');

        $initialStock = (float) StockBatch::where('warehouse_id', $this->warehouseA->id)
            ->where('product_id', $this->tomato->id)
            ->where('warehouse_receive_pending', false)
            ->sum('total_kg');
        $this->assertEquals(0.0, $initialStock);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.accept-pending-bills'), [
                'warehouse_id' => $this->warehouseA->id,
                'grn_id' => $grn->id,
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('message', '1 bill approved and received.');
        $response->assertJsonPath('data.approved', 1);

        // 1. GoodsReceived status changed to approved
        $this->assertSame('approved', $grn->fresh()->status);

        // 2. StockBatch has warehouse_receive_pending = false
        $batch = StockBatch::where('goods_received_id', $grn->id)->first();
        $this->assertNotNull($batch);
        $this->assertFalse((bool) $batch->warehouse_receive_pending);
        $this->assertNotNull($batch->warehouse_confirmed_at);
        $this->assertEquals(25.0, $batch->total_kg);

        // 3. Current inventory increases
        $finalStock = (float) StockBatch::where('warehouse_id', $this->warehouseA->id)
            ->where('product_id', $this->tomato->id)
            ->where('warehouse_receive_pending', false)
            ->sum('total_kg');
        $this->assertEquals(25.0, $finalStock);
    }

    public function test_approve_all_with_3_grns_approves_all_and_returns_3_bills_message(): void
    {
        $grn1 = $this->createPendingBillGrn($this->warehouseA, $this->tomato, 10.0, '2026-09-09');
        $grn2 = $this->createPendingBillGrn($this->warehouseA, $this->tomato, 15.0, '2026-09-09');
        $grn3 = $this->createPendingBillGrn($this->warehouseA, $this->tomato, 20.0, '2026-09-09');
        $grnIds = [$grn1->id, $grn2->id, $grn3->id];

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.accept-pending-bills'), [
                'warehouse_id' => $this->warehouseA->id,
                'grn_ids' => $grnIds,
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('message', '3 bills approved and received.');
        $response->assertJsonPath('data.approved', 3);

        // Verify all 3 GRNs are approved
        $this->assertSame('approved', $grn1->fresh()->status);
        $this->assertSame('approved', $grn2->fresh()->status);
        $this->assertSame('approved', $grn3->fresh()->status);

        // Verify batches have warehouse_receive_pending = false
        $batches = StockBatch::whereIn('goods_received_id', $grnIds)->get();
        $this->assertCount(3, $batches);
        foreach ($batches as $batch) {
            $this->assertFalse((bool) $batch->warehouse_receive_pending);
            $this->assertNotNull($batch->warehouse_confirmed_at);
        }

        // Verify inventory increased by 45 kg
        $totalStock = (float) StockBatch::where('warehouse_id', $this->warehouseA->id)
            ->where('product_id', $this->tomato->id)
            ->where('warehouse_receive_pending', false)
            ->sum('total_kg');
        $this->assertEquals(45.0, $totalStock);
    }

    public function test_zero_resolved_grns_returns_422_with_explicit_error_message(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.accept-pending-bills'), [
                'warehouse_id' => $this->warehouseA->id,
                'dates' => ['2020-01-01'],
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('message', 'No pending GRNs were found for the submitted IDs.');
    }

    public function test_already_approved_grn_with_no_batches_creates_and_confirms_batches(): void
    {
        // Create a GRN that is already approved but has NO stock batches
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-'.uniqid(),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->shop->id,
            'status' => 'closed',
            'order_date' => Carbon::parse('2026-09-09'),
            'total_amount' => 300,
            'created_by' => $this->adminUser->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->tomato->id,
            'quantity' => 30.0,
            'unit_price' => 10.0,
            'unit' => $this->tomato->unit,
            'purchase_unit' => $this->tomato->unit,
            'total_amount' => 300,
        ]);

        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-TEST-'.uniqid(),
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouseA->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_by' => $this->adminUser->id,
            'approved_by' => $this->adminUser->id,
            'received_at' => Carbon::parse('2026-09-09'),
            'approved_at' => Carbon::parse('2026-09-09'),
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 30.0,
            'received_unit' => $this->tomato->unit,
            'variance' => 0.0,
            'grade' => 'A',
        ]);

        // Confirm: no batches exist
        $this->assertFalse(StockBatch::where('goods_received_id', $grn->id)->exists());

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.accept-pending-bills'), [
                'warehouse_id' => $this->warehouseA->id,
                'grn_id' => $grn->id,
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.approved', 1);

        // Batch was created and confirmed
        $batch = StockBatch::where('goods_received_id', $grn->id)->first();
        $this->assertNotNull($batch);
        $this->assertFalse((bool) $batch->warehouse_receive_pending);
        $this->assertNotNull($batch->warehouse_confirmed_at);
        $this->assertEquals(30.0, $batch->total_kg);

        // Inventory increased
        $stock = (float) StockBatch::where('warehouse_id', $this->warehouseA->id)
            ->where('product_id', $this->tomato->id)
            ->where('warehouse_receive_pending', false)
            ->sum('total_kg');
        $this->assertEquals(30.0, $stock);
    }

    public function test_unauthorized_user_cannot_access_or_accept_pending_bills(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->getJson(route('admin.cashbook.inventory.pending-bills-days', [
                'warehouse_id' => $this->warehouseA->id,
            ]))
            ->assertForbidden();

        $this->actingAs($this->unauthorizedUser)
            ->postJson(route('admin.cashbook.inventory.accept-pending-bills'), [
                'warehouse_id' => $this->warehouseA->id,
                'dates' => ['2026-09-08'],
            ])
            ->assertForbidden();
    }

    private function createPendingBillGrn(Warehouse $warehouse, Product $product, float $qty, string $date): GoodsReceived
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-'.uniqid(),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $warehouse->id,
            'destination_shop_id' => $this->shop->id,
            'status' => POStatus::SentToSupplier,
            'order_date' => Carbon::parse($date),
            'total_amount' => $qty * 10,
            'created_by' => $this->adminUser->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'unit_price' => 10.0,
            'unit' => $product->unit,
            'purchase_unit' => $product->unit,
            'total_amount' => $qty * 10,
        ]);

        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-TEST-'.uniqid(),
            'purchase_order_id' => $po->id,
            'warehouse_id' => $warehouse->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'pending_approval',
            'bill_status' => 'bill_pending',
            'received_by' => $this->adminUser->id,
            'received_at' => Carbon::parse($date),
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $product->unit,
            'variance' => 0.0,
            'grade' => 'A',
        ]);

        return $grn;
    }

    private function createConfirmedAdvanceGrn(Warehouse $warehouse, Product $product, float $qty, string $date): GoodsReceived
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-ADV-'.uniqid(),
            'warehouse_id' => $warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_by' => $this->adminUser->id,
            'approved_by' => $this->adminUser->id,
            'received_at' => Carbon::parse($date),
            'approved_at' => Carbon::parse($date),
        ]);

        $item = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $product->unit,
            'variance' => 0.0,
            'grade' => 'A',
        ]);

        StockBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->adminUser->id,
            'reference' => 'BAT-ADV-'.uniqid(),
            'received_at' => Carbon::parse($date),
            'total_kg' => $qty,
            'cost_per_kg' => 10.0,
            'status' => 'pending',
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->adminUser->id,
        ]);

        return $grn;
    }
}
