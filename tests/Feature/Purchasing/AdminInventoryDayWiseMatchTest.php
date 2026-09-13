<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\AdvanceReceiveMatch;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInventoryDayWiseMatchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Supplier $supplier;

    private Product $anar;

    private Product $cucumber;

    private Product $bananaLeaf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->warehouse = Warehouse::factory()->create([
            'name' => 'Main Warehouse',
            'code' => 'MAIN-WH',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::factory()->create([
            'name' => 'Fruit Supplier',
        ]);

        $this->anar = Product::factory()->create([
            'name' => 'Anar / Pomegranate',
            'sku' => 'ANAR-01',
            'unit' => 'box',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $this->cucumber = Product::factory()->create([
            'name' => 'English Cucumber',
            'sku' => 'CUC-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $this->bananaLeaf = Product::factory()->create([
            'name' => 'Banana Leaf',
            'sku' => 'LEAF-01',
            'unit' => 'piece',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
    }

    /**
     * A. Full: Advance 10 / Bill 10 -> Match All -> matched 10 -> pending 0
     */
    public function test_match_all_scenario_a_full_match(): void
    {
        $advGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_by' => $this->admin->id,
            'received_at' => '2026-09-10 08:00:00',
        ]);

        $advItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->anar->id,
            'received_qty' => 10.0,
            'received_unit' => 'box',
        ]);

        $advBatch = StockBatch::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->anar->id,
            'goods_received_id' => $advGrn->id,
            'goods_received_item_id' => $advItem->id,
            'total_kg' => 10.0,
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-09-10',
            'status' => 'approved',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->anar->id,
            'quantity' => 10.0,
            'purchase_unit' => 'box',
        ]);

        $billGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => $po->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'approved',
            'received_by' => $this->admin->id,
            'received_at' => '2026-09-10 10:00:00',
        ]);

        $billItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->anar->id,
            'received_qty' => 10.0,
            'received_unit' => 'box',
        ]);

        $billBatch = StockBatch::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->anar->id,
            'goods_received_id' => $billGrn->id,
            'goods_received_item_id' => $billItem->id,
            'total_kg' => 10.0,
        ]);

        $initialMovementCount = StockMovement::count();

        // Match All
        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.inventory.match-all-day'), [
            'date' => '2026-09-10',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'matched_products_count' => 1,
                    'total_matched_qty' => 10.0,
                    'skipped_unit_mismatches' => 0,
                    'still_pending_rows' => 0,
                ],
            ]);

        // Stock batch assertion: Bill batch reduced to 0, Advance batch untouched at 10
        $this->assertEquals(0.0, (float) $billBatch->fresh()->total_kg);
        $this->assertEquals(10.0, (float) $advBatch->fresh()->total_kg);
        $this->assertEquals($initialMovementCount, StockMovement::count());

        // Report page check
        $viewResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.inventory', [
            'date' => '2026-09-10',
            'warehouse_id' => $this->warehouse->id,
        ]));
        $viewResponse->assertOk();
        $viewResponse->assertSee('100%');
        $viewResponse->assertSee('Matched');
    }

    /**
     * B. Partial: Advance 4 / Bill 10 -> Match All -> matched 4 -> Bill Pending 6
     */
    public function test_match_all_scenario_b_partial_match(): void
    {
        $advGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'received_by' => $this->admin->id,
            'received_at' => '2026-09-10 08:00:00',
        ]);

        $advItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->cucumber->id,
            'received_qty' => 4.0,
            'received_unit' => 'kg',
        ]);

        $advBatch = StockBatch::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->cucumber->id,
            'goods_received_id' => $advGrn->id,
            'goods_received_item_id' => $advItem->id,
            'total_kg' => 4.0,
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-09-10',
            'status' => 'approved',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->cucumber->id,
            'quantity' => 10.0,
            'purchase_unit' => 'kg',
        ]);

        $billGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => $po->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'approved',
            'received_by' => $this->admin->id,
            'received_at' => '2026-09-10 10:00:00',
        ]);

        $billItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->cucumber->id,
            'received_qty' => 10.0,
            'received_unit' => 'kg',
        ]);

        $billBatch = StockBatch::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->cucumber->id,
            'goods_received_id' => $billGrn->id,
            'goods_received_item_id' => $billItem->id,
            'total_kg' => 10.0,
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.inventory.match-all-day'), [
            'date' => '2026-09-10',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'matched_products_count' => 1,
                    'total_matched_qty' => 4.0,
                    'still_pending_rows' => 1,
                ],
            ]);

        // Bill batch reduced: 10 - 4 = 6. Advance batch untouched: 4.
        $this->assertEquals(6.0, (float) $billBatch->fresh()->total_kg);
        $this->assertEquals(4.0, (float) $advBatch->fresh()->total_kg);

        $viewResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.inventory', [
            'date' => '2026-09-10',
            'warehouse_id' => $this->warehouse->id,
        ]));
        $viewResponse->assertOk();
        $viewResponse->assertSee('40%');

        // Now if another advance of 3 kg arrives on the same date for the same product
        $advGrn2 = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'received_by' => $this->admin->id,
            'received_at' => '2026-09-10 11:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn2->id,
            'product_id' => $this->cucumber->id,
            'received_qty' => 3.0,
            'received_unit' => 'kg',
        ]);

        StockBatch::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->cucumber->id,
            'goods_received_id' => $advGrn2->id,
            'total_kg' => 3.0,
        ]);

        $viewResponse2 = $this->actingAs($this->admin)->get(route('admin.cashbook.inventory', [
            'date' => '2026-09-10',
            'warehouse_id' => $this->warehouse->id,
        ]));
        $viewResponse2->assertOk();
        $viewResponse2->assertSee('Match Remaining');
    }

    /**
     * C. Advance larger: Advance 10 / Bill 4 -> Match All -> matched 4 -> Bill Pending 0
     */
    public function test_match_all_scenario_c_advance_larger(): void
    {
        $advGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'received_by' => $this->admin->id,
            'received_at' => '2026-09-10 08:00:00',
        ]);

        $advItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->anar->id,
            'received_qty' => 10.0,
            'received_unit' => 'box',
        ]);

        $advBatch = StockBatch::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->anar->id,
            'goods_received_id' => $advGrn->id,
            'goods_received_item_id' => $advItem->id,
            'total_kg' => 10.0,
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-09-10',
            'status' => 'approved',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->anar->id,
            'quantity' => 4.0,
            'purchase_unit' => 'box',
        ]);

        $billGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => $po->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'approved',
            'received_by' => $this->admin->id,
            'received_at' => '2026-09-10 10:00:00',
        ]);

        $billItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->anar->id,
            'received_qty' => 4.0,
            'received_unit' => 'box',
        ]);

        $billBatch = StockBatch::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->anar->id,
            'goods_received_id' => $billGrn->id,
            'goods_received_item_id' => $billItem->id,
            'total_kg' => 4.0,
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.inventory.match-all-day'), [
            'date' => '2026-09-10',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'matched_products_count' => 1,
                    'total_matched_qty' => 4.0,
                    'still_pending_rows' => 0,
                ],
            ]);

        // Bill batch reduced: 4 - 4 = 0. Advance batch untouched: 10.
        $this->assertEquals(0.0, (float) $billBatch->fresh()->total_kg);
        $this->assertEquals(10.0, (float) $advBatch->fresh()->total_kg);

        $viewResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.inventory', [
            'date' => '2026-09-10',
            'warehouse_id' => $this->warehouse->id,
        ]));
        $viewResponse->assertOk();
        $viewResponse->assertSee('100%');
        $viewResponse->assertSee('Matched');
    }

    /**
     * D. Bill only: Advance 0 / Bill 10 -> Bill Pending 10
     */
    public function test_match_all_scenario_d_bill_only(): void
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-09-10',
            'status' => 'approved',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->cucumber->id,
            'quantity' => 10.0,
            'purchase_unit' => 'kg',
        ]);

        $billGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => $po->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'approved',
            'received_by' => $this->admin->id,
            'received_at' => '2026-09-10 10:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->cucumber->id,
            'received_qty' => 10.0,
            'received_unit' => 'kg',
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.inventory.match-all-day'), [
            'date' => '2026-09-10',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'matched_products_count' => 0,
                    'total_matched_qty' => 0.0,
                    'still_pending_rows' => 1,
                ],
            ]);

        $this->assertEquals(0, AdvanceReceiveMatch::count());
    }

    /**
     * E. Unit mismatch: Advance 10 box / Bill 10 kg -> skipped by Match All -> Fix Unit shown -> Fix Unit to box -> Match All -> matched 10
     */
    public function test_match_all_scenario_e_unit_mismatch_and_unit_fix(): void
    {
        // Advance: 10 box
        $advGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'received_by' => $this->admin->id,
            'received_at' => '2026-09-10 08:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->anar->id,
            'received_qty' => 10.0,
            'received_unit' => 'box',
        ]);

        $advBatch = StockBatch::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->anar->id,
            'goods_received_id' => $advGrn->id,
            'total_kg' => 10.0,
        ]);

        // Bill: 10 kg (unit mismatch)
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-09-10',
            'status' => 'approved',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->anar->id,
            'quantity' => 10.0,
            'purchase_unit' => 'kg',
        ]);

        $billGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => $po->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'approved',
            'received_by' => $this->admin->id,
            'received_at' => '2026-09-10 10:00:00',
        ]);

        $billItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->anar->id,
            'received_qty' => 10.0,
            'received_unit' => 'kg',
        ]);

        $billBatch = StockBatch::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->anar->id,
            'goods_received_id' => $billGrn->id,
            'goods_received_item_id' => $billItem->id,
            'total_kg' => 10.0,
        ]);

        // Run Match All: must skip unit mismatch safely
        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.inventory.match-all-day'), [
            'date' => '2026-09-10',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'matched_products_count' => 0,
                    'total_matched_qty' => 0.0,
                    'skipped_unit_mismatches' => 1,
                    'still_pending_rows' => 1,
                ],
            ]);

        $this->assertEquals(0, AdvanceReceiveMatch::count());

        // Fix Unit: change bill item unit from kg to box
        $fixResponse = $this->actingAs($this->admin)->postJson(route('admin.cashbook.inventory.update-item-unit'), [
            'goods_received_item_id' => $billItem->id,
            'new_unit' => 'box',
        ]);

        $fixResponse->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $billItem->id,
                    'old_unit' => 'kg',
                    'new_unit' => 'box',
                ],
            ]);

        $this->assertEquals('box', $billItem->fresh()->received_unit);
        // Ensure unit fix does not modify inventory or batch quantities
        $this->assertEquals(10.0, (float) $billBatch->fresh()->total_kg);
        $this->assertEquals(10.0, (float) $advBatch->fresh()->total_kg);

        // Run Match All after unit fix: now matches perfectly!
        $secondMatch = $this->actingAs($this->admin)->postJson(route('admin.cashbook.inventory.match-all-day'), [
            'date' => '2026-09-10',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $secondMatch->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'matched_products_count' => 1,
                    'total_matched_qty' => 10.0,
                    'skipped_unit_mismatches' => 0,
                    'still_pending_rows' => 0,
                ],
            ]);

        $this->assertEquals(0.0, (float) $billBatch->fresh()->total_kg);
        $this->assertEquals(10.0, (float) $advBatch->fresh()->total_kg);
    }

    /**
     * F. Run Match All twice: second run must create zero duplicate matches, inventory delta = 0
     */
    public function test_match_all_scenario_f_idempotent_retry(): void
    {
        $advGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'received_by' => $this->admin->id,
            'received_at' => '2026-09-10 08:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->anar->id,
            'received_qty' => 10.0,
            'received_unit' => 'box',
        ]);

        StockBatch::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->anar->id,
            'goods_received_id' => $advGrn->id,
            'total_kg' => 10.0,
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-09-10',
            'status' => 'approved',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->anar->id,
            'quantity' => 10.0,
            'purchase_unit' => 'box',
        ]);

        $billGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => $po->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'approved',
            'received_by' => $this->admin->id,
            'received_at' => '2026-09-10 10:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->anar->id,
            'received_qty' => 10.0,
            'received_unit' => 'box',
        ]);

        $billBatch = StockBatch::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->anar->id,
            'goods_received_id' => $billGrn->id,
            'total_kg' => 10.0,
        ]);

        // Run 1
        $res1 = $this->actingAs($this->admin)->postJson(route('admin.cashbook.inventory.match-all-day'), [
            'date' => '2026-09-10',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $res1->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'matched_products_count' => 1,
                    'total_matched_qty' => 10.0,
                    'still_pending_rows' => 0,
                ],
            ]);

        $matchCountAfterRun1 = AdvanceReceiveMatch::count();
        $this->assertEquals(1, $matchCountAfterRun1);
        $this->assertEquals(0.0, (float) $billBatch->fresh()->total_kg);

        // Run 2 (Retry)
        $res2 = $this->actingAs($this->admin)->postJson(route('admin.cashbook.inventory.match-all-day'), [
            'date' => '2026-09-10',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $res2->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'matched_products_count' => 0,
                    'total_matched_qty' => 0.0,
                    'still_pending_rows' => 0,
                ],
            ]);

        // Zero duplicate matches and stock unchanged
        $this->assertEquals($matchCountAfterRun1, AdvanceReceiveMatch::count());
        $this->assertEquals(0.0, (float) $billBatch->fresh()->total_kg);
    }

    public function test_match_all_is_strictly_scoped_to_selected_warehouse(): void
    {
        $otherWarehouse = Warehouse::factory()->create([
            'name' => 'Secondary Hub',
            'code' => 'SEC-HUB',
            'is_active' => true,
        ]);

        // Advance in Other Warehouse
        $advGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $otherWarehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'received_by' => $this->admin->id,
            'received_at' => '2026-09-10 08:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->anar->id,
            'received_qty' => 10.0,
            'received_unit' => 'box',
        ]);

        // Bill in Other Warehouse
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-09-10',
            'status' => 'approved',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->anar->id,
            'quantity' => 10.0,
            'purchase_unit' => 'box',
        ]);

        $billGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $otherWarehouse->id,
            'purchase_order_id' => $po->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'approved',
            'received_by' => $this->admin->id,
            'received_at' => '2026-09-10 10:00:00',
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->anar->id,
            'received_qty' => 10.0,
            'received_unit' => 'box',
        ]);

        // Running match-all for $this->warehouse must NOT match items in $otherWarehouse
        $res = $this->actingAs($this->admin)->postJson(route('admin.cashbook.inventory.match-all-day'), [
            'date' => '2026-09-10',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $res->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'matched_products_count' => 0,
                    'total_matched_qty' => 0.0,
                ],
            ]);

        $this->assertEquals(0, AdvanceReceiveMatch::count());
    }

    /**
     * Test Receive All Pending Bills:
     * 10 pending POs + 2 already received POs on selected date.
     * Before: Pending = 10.
     * Click Receive All -> 10 received, Pending = 0, already received 2 unchanged.
     * Retry -> 0 received, 0 pending, inventory delta = 0, no duplicate GRN/batches.
     */
    public function test_receive_all_pending_bills_receives_10_pending_and_idempotent_on_retry(): void
    {
        $selectedDate = '2026-09-13';

        // 1. Create 10 pending POs on selected date for the selected warehouse
        $pendingPos = [];
        for ($i = 1; $i <= 10; $i++) {
            $po = PurchaseOrder::factory()->create([
                'supplier_id' => $this->supplier->id,
                'order_date' => $selectedDate,
                'status' => 'approved',
                'po_number' => "PO-TEST-PENDING-{$i}",
            ]);

            PurchaseOrderItem::factory()->create([
                'purchase_order_id' => $po->id,
                'product_id' => $this->anar->id,
                'quantity' => 5.0,
                'purchase_unit' => 'box',
            ]);

            $pendingPos[] = $po;
        }

        // 2. Create 2 already received POs & GRNs on selected date
        for ($j = 1; $j <= 2; $j++) {
            $receivedPo = PurchaseOrder::factory()->create([
                'supplier_id' => $this->supplier->id,
                'order_date' => $selectedDate,
                'status' => 'closed',
                'po_number' => "PO-TEST-RCVD-{$j}",
            ]);

            $rcvdPoItem = PurchaseOrderItem::factory()->create([
                'purchase_order_id' => $receivedPo->id,
                'product_id' => $this->anar->id,
                'quantity' => 10.0,
                'purchase_unit' => 'box',
            ]);

            $rcvdGrn = GoodsReceived::factory()->create([
                'warehouse_id' => $this->warehouse->id,
                'purchase_order_id' => $receivedPo->id,
                'receipt_type' => 'normal_purchase',
                'status' => 'approved',
                'bill_status' => 'bill_pending',
                'received_by' => $this->admin->id,
                'approved_by' => $this->admin->id,
                'received_at' => "{$selectedDate} 09:00:00",
                'approved_at' => "{$selectedDate} 09:30:00",
            ]);

            $rcvdGrnItem = GoodsReceivedItem::factory()->create([
                'goods_received_id' => $rcvdGrn->id,
                'purchase_order_item_id' => $rcvdPoItem->id,
                'product_id' => $this->anar->id,
                'received_qty' => 10.0,
                'received_unit' => 'box',
            ]);

            StockBatch::factory()->create([
                'goods_received_id' => $rcvdGrn->id,
                'goods_received_item_id' => $rcvdGrnItem->id,
                'product_id' => $this->anar->id,
                'warehouse_id' => $this->warehouse->id,
                'total_kg' => 10.0,
                'warehouse_receive_pending' => false,
                'warehouse_confirmed_at' => "{$selectedDate} 09:30:00",
                'warehouse_confirmed_by' => $this->admin->id,
            ]);
        }

        // Verify initial page view reports 10 pending bills
        $pageRes = $this->actingAs($this->admin)->get(route('admin.cashbook.inventory', [
            'date' => $selectedDate,
            'warehouse_id' => $this->warehouse->id,
        ]));
        $pageRes->assertOk();
        $pageRes->assertViewHas('pendingBillsCount', 10);

        // Execute Receive All Pending Bills
        $res = $this->actingAs($this->admin)->postJson(route('admin.cashbook.inventory.receive-all-pending-bills'), [
            'date' => $selectedDate,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $res->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'received' => 10,
                    'skipped' => 0,
                    'failed' => 0,
                    'pending_remaining' => 0,
                ],
            ]);

        // Verify all 10 pending POs now have approved GRNs and confirmed StockBatches
        foreach ($pendingPos as $po) {
            $grn = GoodsReceived::query()->where('purchase_order_id', $po->id)->first();
            $this->assertNotNull($grn, "GRN was not created for PO #{$po->id}");
            $this->assertEquals('approved', $grn->status);

            $batches = StockBatch::query()->where('goods_received_id', $grn->id)->get();
            $this->assertNotEmpty($batches);
            foreach ($batches as $b) {
                $this->assertFalse((bool) $b->warehouse_receive_pending);
                $this->assertNotNull($b->warehouse_confirmed_at);
            }
        }

        // Page reload: pendingBillsCount must be 0
        $pageReloadRes = $this->actingAs($this->admin)->get(route('admin.cashbook.inventory', [
            'date' => $selectedDate,
            'warehouse_id' => $this->warehouse->id,
        ]));
        $pageReloadRes->assertOk();
        $pageReloadRes->assertViewHas('pendingBillsCount', 0);

        $initialTotalBatches = StockBatch::count();
        $initialTotalGrns = GoodsReceived::count();

        // Run Receive All again (retry): must be completely idempotent
        $retryRes = $this->actingAs($this->admin)->postJson(route('admin.cashbook.inventory.receive-all-pending-bills'), [
            'date' => $selectedDate,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $retryRes->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'received' => 0,
                    'pending_remaining' => 0,
                ],
            ]);

        $this->assertEquals($initialTotalBatches, StockBatch::count(), 'No duplicate StockBatches should be created on retry');
        $this->assertEquals($initialTotalGrns, GoodsReceived::count(), 'No duplicate GRNs should be created on retry');
    }

    /**
     * Test that Receive All strictly operates on selected date only.
     * Previous day and next day bills must remain untouched.
     */
    public function test_receive_all_pending_bills_strictly_scopes_to_selected_date_only(): void
    {
        $selectedDate = '2026-09-10';
        $prevDate = '2026-09-09';
        $nextDate = '2026-09-11';

        // 3 POs on selected date
        $selectedPos = [];
        for ($i = 1; $i <= 3; $i++) {
            $po = PurchaseOrder::factory()->create([
                'supplier_id' => $this->supplier->id,
                'order_date' => $selectedDate,
                'status' => 'approved',
            ]);
            PurchaseOrderItem::factory()->create([
                'purchase_order_id' => $po->id,
                'product_id' => $this->anar->id,
                'quantity' => 4.0,
                'purchase_unit' => 'box',
            ]);
            $selectedPos[] = $po;
        }

        // 2 POs on previous date
        $prevPos = [];
        for ($i = 1; $i <= 2; $i++) {
            $po = PurchaseOrder::factory()->create([
                'supplier_id' => $this->supplier->id,
                'order_date' => $prevDate,
                'status' => 'approved',
            ]);
            PurchaseOrderItem::factory()->create([
                'purchase_order_id' => $po->id,
                'product_id' => $this->anar->id,
                'quantity' => 4.0,
                'purchase_unit' => 'box',
            ]);
            $prevPos[] = $po;
        }

        // 2 POs on next date
        $nextPos = [];
        for ($i = 1; $i <= 2; $i++) {
            $po = PurchaseOrder::factory()->create([
                'supplier_id' => $this->supplier->id,
                'order_date' => $nextDate,
                'status' => 'approved',
            ]);
            PurchaseOrderItem::factory()->create([
                'purchase_order_id' => $po->id,
                'product_id' => $this->anar->id,
                'quantity' => 4.0,
                'purchase_unit' => 'box',
            ]);
            $nextPos[] = $po;
        }

        // Execute Receive All for selected date only
        $res = $this->actingAs($this->admin)->postJson(route('admin.cashbook.inventory.receive-all-pending-bills'), [
            'date' => $selectedDate,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $res->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'received' => 3,
                    'pending_remaining' => 0,
                ],
            ]);

        // Verify selected POs received
        foreach ($selectedPos as $po) {
            $this->assertTrue(GoodsReceived::query()->where('purchase_order_id', $po->id)->where('status', 'approved')->exists());
        }

        // Verify previous date POs are untouched
        foreach ($prevPos as $po) {
            $this->assertFalse(GoodsReceived::query()->where('purchase_order_id', $po->id)->exists());
        }

        // Verify next date POs are untouched
        foreach ($nextPos as $po) {
            $this->assertFalse(GoodsReceived::query()->where('purchase_order_id', $po->id)->exists());
        }
    }

    /**
     * Test that Receive All reuses existing pending GRN without creating duplicate GRN.
     */
    public function test_receive_all_reuses_existing_pending_grn_without_duplicate(): void
    {
        $date = '2026-09-10';

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => $date,
            'status' => 'approved',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->anar->id,
            'quantity' => 5.0,
            'purchase_unit' => 'box',
        ]);

        $existingPendingGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => $po->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'pending_approval',
            'bill_status' => 'bill_pending',
            'received_by' => $this->admin->id,
            'received_at' => "{$date} 08:00:00",
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $existingPendingGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->anar->id,
            'received_qty' => 5.0,
            'received_unit' => 'box',
        ]);

        $this->assertEquals(1, GoodsReceived::where('purchase_order_id', $po->id)->count());

        $res = $this->actingAs($this->admin)->postJson(route('admin.cashbook.inventory.receive-all-pending-bills'), [
            'date' => $date,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $res->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'received' => 1,
                    'pending_remaining' => 0,
                ],
            ]);

        // Total GRNs for this PO must still be 1 (reused, not duplicated)
        $this->assertEquals(1, GoodsReceived::where('purchase_order_id', $po->id)->count());
        $existingPendingGrn->refresh();
        $this->assertEquals('approved', $existingPendingGrn->status);

        $batches = StockBatch::where('goods_received_id', $existingPendingGrn->id)->get();
        $this->assertNotEmpty($batches);
        foreach ($batches as $b) {
            $this->assertFalse((bool) $b->warehouse_receive_pending);
        }
    }

    /**
     * Test Receive Single Bill:
     * Receives one specific PO/bill, confirming warehouse receipt.
     */
    public function test_receive_single_bill_receives_one_po_and_updates_status(): void
    {
        $date = '2026-09-13';

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => $date,
            'status' => 'approved',
            'po_number' => 'PO-SINGLE-01',
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->anar->id,
            'quantity' => 7.0,
            'purchase_unit' => 'box',
        ]);

        $res = $this->actingAs($this->admin)->postJson(route('admin.cashbook.inventory.receive-single-bill'), [
            'type' => 'po',
            'id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'date' => $date,
        ]);

        $res->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $grn = GoodsReceived::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($grn);
        $this->assertEquals('approved', $grn->status);

        $batches = StockBatch::where('goods_received_id', $grn->id)->get();
        $this->assertNotEmpty($batches);
        foreach ($batches as $b) {
            $this->assertFalse((bool) $b->warehouse_receive_pending);
            $this->assertEquals(7.0, (float) $b->total_kg);
        }
    }

    /**
     * Test Pending Bills Day Details API returns accurate day and warehouse scoped pending bills.
     */
    public function test_pending_bills_day_details_api(): void
    {
        $date = '2026-09-13';

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => $date,
            'status' => 'approved',
            'po_number' => 'PO-DETAILS-01',
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->anar->id,
            'quantity' => 5.0,
            'purchase_unit' => 'box',
        ]);

        $res = $this->actingAs($this->admin)->getJson(route('admin.cashbook.inventory.pending-bills-day-details', [
            'date' => $date,
            'warehouse_id' => $this->warehouse->id,
        ]));

        $res->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'date' => $date,
                    'total_pending' => 1,
                ],
            ]);

        $this->assertEquals('PO-DETAILS-01', $res->json('data.bills.0.bill_number'));
        $this->assertEquals($this->supplier->name, $res->json('data.bills.0.supplier_name'));
    }

    /**
     * Test Pending Bills Days Summary API returns grouped dates with pending counts.
     */
    public function test_pending_bills_days_summary_api(): void
    {
        $po1 = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-09-13',
            'status' => 'approved',
        ]);
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po1->id,
            'product_id' => $this->anar->id,
            'quantity' => 5.0,
            'purchase_unit' => 'box',
        ]);

        $po2 = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-09-12',
            'status' => 'approved',
        ]);
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po2->id,
            'product_id' => $this->anar->id,
            'quantity' => 3.0,
            'purchase_unit' => 'box',
        ]);

        $res = $this->actingAs($this->admin)->getJson(route('admin.cashbook.inventory.pending-bills-days', [
            'warehouse_id' => $this->warehouse->id,
        ]));

        $res->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $days = collect($res->json('data.days'));
        $this->assertEquals(1, $days->firstWhere('date', '2026-09-13')['pending_count']);
        $this->assertEquals(1, $days->firstWhere('date', '2026-09-12')['pending_count']);
    }

    /**
     * Test full-day summary metrics in inventory view data.
     */
    public function test_inventory_view_provides_full_day_summary_and_pending_list(): void
    {
        $date = '2026-09-13';

        // Advance 10 box
        $advGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_by' => $this->admin->id,
            'received_at' => "{$date} 08:00:00",
        ]);

        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->anar->id,
            'received_qty' => 10.0,
            'received_unit' => 'box',
        ]);

        // Pending PO 10 box
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => $date,
            'status' => 'approved',
            'po_number' => 'PO-SUMMARY-01',
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->anar->id,
            'quantity' => 10.0,
            'purchase_unit' => 'box',
        ]);

        $res = $this->actingAs($this->admin)->get(route('admin.cashbook.inventory', [
            'date' => $date,
            'warehouse_id' => $this->warehouse->id,
        ]));

        $res->assertOk();
        $res->assertViewHas('pendingBillsCount', 1);
        $res->assertViewHas('pendingBillsList');
        $res->assertViewHas('summary');

        $summary = $res->viewData('summary');
        $this->assertEquals(10.0, $summary['total_advance_qty']);
        $this->assertEquals(0.0, $summary['total_bill_qty']);
        $this->assertEquals(0, $summary['unit_fix_count']);

        $pendingList = $res->viewData('pendingBillsList');
        $this->assertCount(1, $pendingList);
        $this->assertEquals('PO-SUMMARY-01', $pendingList[0]['bill_number']);
    }
}
