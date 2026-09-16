<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\Purchasing\ApproveGoodsReceiptAction;
use App\Models\AdvanceReceiveMatch;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\DailyInventoryComparisonService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerDailyPurchaseMatchSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouseVeg;

    private Warehouse $warehouseFruit;

    private Supplier $supplier;

    private Product $tomato;

    private Product $bananaLeaf;

    private Product $anar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->warehouseVeg = Warehouse::factory()->create([
            'name' => 'Vegetable Warehouse',
            'code' => 'WH-VEG',
            'is_active' => true,
        ]);

        $this->warehouseFruit = Warehouse::factory()->create([
            'name' => 'Fruit Warehouse',
            'code' => 'WH-FRUIT',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::factory()->create([
            'name' => 'Fresh Farm Supplies',
        ]);

        $this->tomato = Product::factory()->create([
            'name' => 'Tomato',
            'sku' => 'TOM-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouseVeg->id,
        ]);

        $this->bananaLeaf = Product::factory()->create([
            'name' => 'Banana Leaf',
            'sku' => 'BL-01',
            'unit' => 'piece',
            'default_warehouse_id' => $this->warehouseVeg->id,
        ]);

        $this->anar = Product::factory()->create([
            'name' => 'Anar',
            'sku' => 'ANAR-01',
            'unit' => 'box',
            'default_warehouse_id' => $this->warehouseFruit->id,
        ]);
    }

    /**
     * Test 1: Auto Match After Every Receive (Web & API / Advance & Bill).
     * When Advance GRN is created first, and then Bill GRN is received,
     * same-day auto matching executes automatically without manual intervention.
     */
    public function test_auto_match_runs_automatically_after_bill_receive(): void
    {
        $date = '2026-09-15';

        // 1. Advance Receive: 10 kg Tomato
        $advGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_by' => $this->admin->id,
            'received_at' => $date.' 08:00:00',
        ]);

        $advItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 10.0,
            'received_unit' => 'kg',
        ]);

        $advBatch = StockBatch::factory()->create([
            'goods_received_id' => $advGrn->id,
            'goods_received_item_id' => $advItem->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'total_kg' => 10.0,
            'received_at' => $date,
        ]);

        $this->assertDatabaseCount('advance_receive_matches', 0);

        // 2. Normal Purchase Bill GRN arrives: 6 kg Tomato
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => $date,
            'status' => 'sent_to_supplier',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->tomato->id,
            'quantity' => 6.0,
            'purchase_unit' => 'kg',
        ]);

        $billGrn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'pending_approval',
            'bill_status' => 'bill_pending',
            'received_by' => $this->admin->id,
            'received_at' => $date.' 10:00:00',
        ]);

        $billItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 6.0,
            'received_unit' => 'kg',
        ]);

        // Approve and confirm receive on the Bill GRN (simulating receive flow)
        app(ApproveGoodsReceiptAction::class)->executeAndConfirmReceive($billGrn, $this->admin->id, $this->warehouseVeg->id);

        // 3. Verify auto-match ran automatically!
        $this->assertDatabaseCount('advance_receive_matches', 1);
        $match = AdvanceReceiveMatch::first();
        $this->assertSame($advItem->id, $match->advance_goods_received_item_id);
        $this->assertSame($billItem->id, $match->bill_goods_received_item_id);
        $this->assertEquals(6.0, (float) $match->matched_qty);
        $this->assertSame('kg', $match->matched_unit);
    }

    /**
     * Test 2: Auto Match runs when Advance GRN is received AFTER Bill GRN.
     */
    public function test_auto_match_runs_automatically_after_advance_receive(): void
    {
        $date = '2026-09-15';

        // 1. Bill GRN received first: 8 piece Banana Leaf
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => $date,
            'status' => 'sent_to_supplier',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->bananaLeaf->id,
            'quantity' => 8.0,
            'purchase_unit' => 'piece',
        ]);

        $billGrn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'pending_approval',
            'bill_status' => 'bill_pending',
            'received_by' => $this->admin->id,
            'received_at' => $date.' 07:00:00',
        ]);

        $billItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->bananaLeaf->id,
            'received_qty' => 8.0,
            'received_unit' => 'piece',
        ]);

        app(ApproveGoodsReceiptAction::class)->executeAndConfirmReceive($billGrn, $this->admin->id, $this->warehouseVeg->id);

        $this->assertDatabaseCount('advance_receive_matches', 0);

        // 2. Now Advance GRN is received: 10 piece Banana Leaf
        $advGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'pending_approval',
            'bill_status' => 'bill_pending',
            'received_by' => $this->admin->id,
            'received_at' => $date.' 09:00:00',
        ]);

        $advItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->bananaLeaf->id,
            'received_qty' => 10.0,
            'received_unit' => 'piece',
        ]);

        // Approve Advance GRN
        app(ApproveGoodsReceiptAction::class)->execute($advGrn, $this->admin->id);

        // Verify auto-match ran: 8 piece matched
        $this->assertDatabaseCount('advance_receive_matches', 1);
        $match = AdvanceReceiveMatch::first();
        $this->assertEquals(8.0, (float) $match->matched_qty);
        $this->assertSame('piece', $match->matched_unit);
    }

    /**
     * Test 3: Strict Rule - No cross-date matching.
     */
    public function test_no_cross_date_auto_matching(): void
    {
        // Advance on Sept 14
        $advGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_by' => $this->admin->id,
            'received_at' => '2026-09-14 08:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 10.0,
            'received_unit' => 'kg',
        ]);

        // Bill on Sept 15
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-09-15',
            'status' => 'sent_to_supplier',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->tomato->id,
            'quantity' => 10.0,
            'purchase_unit' => 'kg',
        ]);

        $billGrn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'pending_approval',
            'bill_status' => 'bill_pending',
            'received_by' => $this->admin->id,
            'received_at' => '2026-09-15 10:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 10.0,
            'received_unit' => 'kg',
        ]);

        app(ApproveGoodsReceiptAction::class)->executeAndConfirmReceive($billGrn, $this->admin->id, $this->warehouseVeg->id);

        // Must NOT match across different dates
        $this->assertDatabaseCount('advance_receive_matches', 0);
    }

    /**
     * Test 4: Manager Summary Calculations (Pending Bills After Match & Advance Cleared %).
     */
    public function test_manager_summary_calculations(): void
    {
        $date = '2026-09-15';
        $service = app(DailyInventoryComparisonService::class);

        // Scenario 1: Advance = 0 -> Cleared = N/A
        $summaryEmpty = $service->getDailyManagerSummary($date, $this->warehouseVeg->id);
        $this->assertSame('N/A', $summaryEmpty['pending_bills_after_match']['formatted_advance_cleared_pct']);
        $this->assertSame(0, $summaryEmpty['pending_bills_after_match']['pending_products_count']);

        // Scenario 2: Advance = 10, Matched = 6 -> Pending = 4, Cleared = 60%
        $advGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_by' => $this->admin->id,
            'received_at' => $date.' 08:00:00',
        ]);

        $advItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 10.0,
            'received_unit' => 'kg',
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => $date,
            'status' => 'sent_to_supplier',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->tomato->id,
            'quantity' => 6.0,
            'purchase_unit' => 'kg',
        ]);

        $billGrn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'pending_approval',
            'bill_status' => 'bill_pending',
            'received_by' => $this->admin->id,
            'received_at' => $date.' 10:00:00',
        ]);

        $billItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 6.0,
            'received_unit' => 'kg',
        ]);

        app(ApproveGoodsReceiptAction::class)->executeAndConfirmReceive($billGrn, $this->admin->id, $this->warehouseVeg->id);

        $summary = $service->getDailyManagerSummary($date, $this->warehouseVeg->id);

        $this->assertEquals(1, $summary['purchase_bills']['count']);
        $this->assertEquals(1, $summary['advance_receives']['count']);
        $this->assertEquals(1, $summary['pending_bills_after_match']['pending_products_count']);
        $this->assertSame('4 kg', $summary['pending_bills_after_match']['formatted_totals']);
        $this->assertSame('60%', $summary['pending_bills_after_match']['formatted_advance_cleared_pct']);

        // Check 2-column pending list structure
        $pendingProducts = $summary['pending_bills_after_match']['products'];
        $this->assertCount(1, $pendingProducts);
        $this->assertSame('Tomato', $pendingProducts[0]['product_name']);
        $this->assertSame('4 kg', $pendingProducts[0]['pending_qty']);
    }

    /**
     * Test 5: Web UI and API Parity.
     * Both Web and Flutter API return identical manager summary.
     */
    public function test_web_and_api_use_same_manager_summary_service(): void
    {
        $date = '2026-09-15';

        // Create Advance & Bill for Tomato and Banana Leaf
        $advGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_by' => $this->admin->id,
            'received_at' => $date.' 08:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 450.0,
            'received_unit' => 'kg',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->bananaLeaf->id,
            'received_qty' => 85.0,
            'received_unit' => 'piece',
        ]);

        // 1. Web request to /admin/cashbook/inventory
        $webResponse = $this->actingAs($this->admin)->get('/admin/cashbook/inventory?date='.$date.'&warehouse_id='.$this->warehouseVeg->id);
        $webResponse->assertOk();
        $webResponse->assertViewHas('managerSummary');

        $webManagerSummary = $webResponse->viewData('managerSummary');
        $this->assertSame('450 kg · 85 piece', $webManagerSummary['pending_bills_after_match']['formatted_totals']);
        $this->assertSame(2, $webManagerSummary['pending_bills_after_match']['pending_products_count']);

        // 2. API request to /api/v1/purchasing/advance-inventory
        $apiResponse = $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/purchasing/advance-inventory?date='.$date.'&warehouse_id='.$this->warehouseVeg->id);
        $apiResponse->assertOk();
        $apiResponse->assertJsonPath('status', 'success');
        $apiResponse->assertJsonPath('manager_summary.pending_bills_after_match.formatted_totals', '450 kg · 85 piece');
        $apiResponse->assertJsonPath('manager_summary.pending_bills_after_match.pending_products_count', 2);

        // 3. Print Pending Bills route: /admin/cashbook/inventory/print-pending
        $printResponse = $this->actingAs($this->admin)->get('/admin/cashbook/inventory/print-pending?date='.$date.'&warehouse_id='.$this->warehouseVeg->id);
        $printResponse->assertOk();
        $printResponse->assertSee('Green Leaf - Pending Bills');
        $printResponse->assertSee('Tomato');
        $printResponse->assertSee('450 kg');
        $printResponse->assertSee('Banana Leaf');
        $printResponse->assertSee('85 piece');
    }

    /**
     * Test 6: Receive All Pending Bills automatically auto-matches.
     */
    public function test_receive_all_pending_bills_executes_auto_match(): void
    {
        $date = '2026-09-15';

        // Advance: 50 kg Tomato
        $advGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_by' => $this->admin->id,
            'received_at' => $date.' 08:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
        ]);

        // Pending PO: 50 kg Tomato
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => $date,
            'status' => 'sent_to_supplier',
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->tomato->id,
            'quantity' => 50.0,
            'purchase_unit' => 'kg',
        ]);

        $this->assertDatabaseCount('advance_receive_matches', 0);

        // POST /admin/cashbook/inventory/receive-all-pending-bills
        $response = $this->actingAs($this->admin)->postJson('/admin/cashbook/inventory/receive-all-pending-bills', [
            'date' => $date,
            'warehouse_id' => $this->warehouseVeg->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');

        // Verify auto-match occurred and 50 kg is matched!
        $this->assertDatabaseCount('advance_receive_matches', 1);
        $match = AdvanceReceiveMatch::first();
        $this->assertEquals(50.0, (float) $match->matched_qty);
    }

    /**
     * Test 7: Verify Top Summary Cards use the SAME selected date + warehouse dataset as the table.
     * Even if bill GRN has warehouse_id = null (routed via product.default_warehouse_id),
     * card bill count > 0 and unit totals match table rows.
     */
    public function test_top_cards_match_table_dataset_when_table_has_bill_values(): void
    {
        $date = '2026-09-15';
        $service = app(DailyInventoryComparisonService::class);

        // Advance GRN: 15 kg Tomato
        $advGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_by' => $this->admin->id,
            'received_at' => $date.' 08:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 15.0,
            'received_unit' => 'kg',
        ]);

        // Purchase Bill GRN with warehouse_id = null (normal purchase where warehouse comes from product default)
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => $date,
            'status' => 'sent_to_supplier',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->tomato->id,
            'quantity' => 10.0,
            'purchase_unit' => 'kg',
        ]);

        $billGrn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'warehouse_id' => null, // Explicitly NULL on GRN header
            'receipt_type' => 'normal_purchase',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_by' => $this->admin->id,
            'received_at' => $date.' 09:30:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->tomato->id,
            'received_qty' => 10.0,
            'received_unit' => 'kg',
        ]);

        // Get comparison table rows
        $comparisonRows = $service->buildComparisonRows($date, $this->warehouseVeg->id);
        $this->assertNotEmpty($comparisonRows);
        $row = $comparisonRows->firstWhere('product_id', $this->tomato->id);
        $this->assertNotNull($row);
        $this->assertEquals(15.0, (float) $row['advance_qty']);
        $this->assertEquals(10.0, (float) $row['bill_qty']);

        // Get manager summary cards
        $managerSummary = $service->getDailyManagerSummary($date, $this->warehouseVeg->id);

        // 1. Purchase Bills Card must have count > 0 and match table bill qty!
        $this->assertEquals(1, $managerSummary['purchase_bills']['count']);
        $this->assertEquals(1, $managerSummary['purchase_bills']['products_count']);
        $this->assertEquals(['kg' => 10.0], $managerSummary['purchase_bills']['unit_totals']);
        $this->assertSame('10 kg', $managerSummary['purchase_bills']['formatted_totals']);

        // 2. Advance Receives Card must match table advance qty!
        $this->assertEquals(1, $managerSummary['advance_receives']['count']);
        $this->assertEquals(1, $managerSummary['advance_receives']['products_count']);
        $this->assertEquals(['kg' => 15.0], $managerSummary['advance_receives']['unit_totals']);
        $this->assertSame('15 kg', $managerSummary['advance_receives']['formatted_totals']);

        // 3. Pending Bills Card
        $this->assertEquals(1, $managerSummary['pending_bills_after_match']['pending_products_count']);
        $this->assertEquals(['kg' => 15.0], $managerSummary['pending_bills_after_match']['unit_totals']);
        $this->assertSame('15 kg', $managerSummary['pending_bills_after_match']['formatted_totals']);
    }
}
