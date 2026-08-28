<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Models\AdvanceReceiveMatch;
use App\Models\BillReconciliation;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\AdvanceReceiveReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WarehousePendingReceiveAdvanceReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $warehouseUser;

    private Warehouse $warehouse;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'warehouse_receiver']);
        Permission::firstOrCreate(['name' => 'warehouse.receive.view']);
        Permission::firstOrCreate(['name' => 'warehouse.receive.confirm']);
        $role->givePermissionTo(['warehouse.receive.view', 'warehouse.receive.confirm']);

        $this->warehouseUser = User::factory()->create();
        $this->warehouseUser->assignRole('warehouse_receiver');

        $this->warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse', 'code' => 'MWH']);
        $this->supplier = Supplier::factory()->create(['name' => 'Test Farmer']);

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Tomato',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
    }

    public function test_pending_bill_with_no_advance_receives_normally(): void
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
        ]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'unit_price' => 20,
        ]);

        $grn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'pending_approval',
            'bill_status' => 'bill_pending',
            'received_at' => now(),
        ]);
        $grnItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'received_qty' => 50,
            'received_unit' => 'kg',
        ]);
        $batch = StockBatch::factory()->create([
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $grnItem->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 50,
            'warehouse_receive_pending' => true,
        ]);

        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                $grnItem->id => [
                    'warehouse_id' => $this->warehouse->id,
                    'received_qty' => 50,
                    'discrepancy_type' => 'none',
                ],
            ],
        ];

        $response = $this->actingAs($this->warehouseUser)
            ->post(route('warehouse.receiver.process-receive-grn', $grn), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('goods_received', [
            'id' => $grn->id,
            'status' => 'approved',
        ]);

        $batch->refresh();
        $this->assertFalse((bool) $batch->warehouse_receive_pending);
        $this->assertEquals(50.0, (float) $batch->total_kg);
    }

    public function test_pending_bill_with_advance_suggestions_blocks_unconfirmed_receive(): void
    {
        // 1. Create open Advance receipt of 100 kg
        $advanceGrn = GoodsReceived::factory()->create([
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_at' => now(),
        ]);
        $advanceItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advanceGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 100,
            'received_unit' => 'kg',
        ]);
        StockBatch::factory()->create([
            'goods_received_id' => $advanceGrn->id,
            'goods_received_item_id' => $advanceItem->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 100,
            'warehouse_receive_pending' => false,
        ]);

        // 2. Create pending bill GRN of 75 kg
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
        ]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 75,
            'unit_price' => 20,
        ]);
        $grn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'pending_approval',
            'bill_status' => 'bill_pending',
            'received_at' => now(),
        ]);
        $grnItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'received_qty' => 75,
            'received_unit' => 'kg',
        ]);

        // Attempt normal receive without advance_matches and without bypass
        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                $grnItem->id => [
                    'warehouse_id' => $this->warehouse->id,
                    'received_qty' => 75,
                    'discrepancy_type' => 'none',
                ],
            ],
        ];

        $response = $this->actingAs($this->warehouseUser)
            ->post(route('warehouse.receiver.process-receive-grn', $grn), $payload);

        $response->assertSessionHasErrors('advance_matches');
        $grn->refresh();
        $this->assertEquals('pending_approval', $grn->status);
    }

    public function test_pending_bill_fully_covered_by_advance_adds_zero_stock_and_marks_received(): void
    {
        // 1. Create open Advance receipt of 100 kg
        $advanceGrn = GoodsReceived::factory()->create([
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_at' => now(),
        ]);
        $advanceItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advanceGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 100,
            'received_unit' => 'kg',
        ]);
        $advanceBatch = StockBatch::factory()->create([
            'goods_received_id' => $advanceGrn->id,
            'goods_received_item_id' => $advanceItem->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 100,
            'warehouse_receive_pending' => false,
        ]);

        // 2. Create pending bill GRN of 75 kg
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
        ]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 75,
            'unit_price' => 20,
        ]);
        $billGrn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'pending_approval',
            'bill_status' => 'bill_pending',
            'received_at' => now(),
        ]);
        $billGrnItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'received_qty' => 75,
            'received_unit' => 'kg',
        ]);
        $billBatch = StockBatch::factory()->create([
            'goods_received_id' => $billGrn->id,
            'goods_received_item_id' => $billGrnItem->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 75,
            'warehouse_receive_pending' => true,
        ]);

        // 3. Confirm Advance Match of 75 kg
        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                $billGrnItem->id => [
                    'warehouse_id' => $this->warehouse->id,
                    'received_qty' => 75,
                    'discrepancy_type' => 'none',
                ],
            ],
            'advance_matches' => [
                [
                    'advance_goods_received_id' => $advanceGrn->id,
                    'advance_goods_received_item_id' => $advanceItem->id,
                    'purchase_order_item_id' => $poItem->id,
                    'goods_received_item_id' => $billGrnItem->id,
                    'product_id' => $this->product->id,
                    'matched_qty' => 75,
                    'unit' => 'kg',
                ],
            ],
        ];

        $response = $this->actingAs($this->warehouseUser)
            ->post(route('warehouse.receiver.process-receive-grn', $billGrn), $payload);

        $response->assertRedirect();

        // Verify Bill GRN is approved and marked received
        $billGrn->refresh();
        $this->assertEquals('approved', $billGrn->status);
        $this->assertEquals('bill_available', $billGrn->bill_status);

        // Verify Stock effect is 0
        $billBatch->refresh();
        $this->assertEquals(0.0, (float) $billBatch->total_kg);
        $this->assertFalse((bool) $billBatch->warehouse_receive_pending);

        // Verify AdvanceReceiveMatch and BillReconciliation
        $this->assertDatabaseHas('advance_receive_matches', [
            'advance_goods_received_id' => $advanceGrn->id,
            'bill_goods_received_id' => $billGrn->id,
            'matched_qty' => 75,
        ]);
        $this->assertDatabaseHas('bill_reconciliations', [
            'goods_received_id' => $billGrn->id,
            'total_bill_base_qty' => 75,
            'total_matched_base_qty' => 75,
            'total_new_receive_base_qty' => 0,
            'source_type' => 'advance',
        ]);

        // Advance still has 25 kg remaining, so bill_status remains bill_pending
        $advanceGrn->refresh();
        $this->assertEquals('bill_pending', $advanceGrn->bill_status);
    }

    public function test_pending_bill_partially_covered_by_advance_adds_only_remainder_stock(): void
    {
        // 1. Advance of 40 kg
        $advanceGrn = GoodsReceived::factory()->create([
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_at' => now(),
        ]);
        $advanceItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advanceGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 40,
            'received_unit' => 'kg',
        ]);
        StockBatch::factory()->create([
            'goods_received_id' => $advanceGrn->id,
            'goods_received_item_id' => $advanceItem->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 40,
            'warehouse_receive_pending' => false,
        ]);

        // 2. Bill of 100 kg
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
        ]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
            'unit_price' => 20,
        ]);
        $billGrn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'pending_approval',
            'bill_status' => 'bill_pending',
            'received_at' => now(),
        ]);
        $billGrnItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'received_qty' => 100,
            'received_unit' => 'kg',
        ]);
        $billBatch = StockBatch::factory()->create([
            'goods_received_id' => $billGrn->id,
            'goods_received_item_id' => $billGrnItem->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 100,
            'warehouse_receive_pending' => true,
        ]);

        // 3. Confirm 40 kg match
        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                $billGrnItem->id => [
                    'warehouse_id' => $this->warehouse->id,
                    'received_qty' => 100,
                    'discrepancy_type' => 'none',
                ],
            ],
            'advance_matches' => [
                [
                    'advance_goods_received_id' => $advanceGrn->id,
                    'advance_goods_received_item_id' => $advanceItem->id,
                    'purchase_order_item_id' => $poItem->id,
                    'goods_received_item_id' => $billGrnItem->id,
                    'product_id' => $this->product->id,
                    'matched_qty' => 40,
                    'unit' => 'kg',
                ],
            ],
        ];

        $response = $this->actingAs($this->warehouseUser)
            ->post(route('warehouse.receiver.process-receive-grn', $billGrn), $payload);

        $response->assertRedirect();

        // Verify Bill GRN StockBatch has ONLY 60 kg remainder
        $billBatch->refresh();
        $this->assertEquals(60.0, (float) $billBatch->total_kg);
        $this->assertFalse((bool) $billBatch->warehouse_receive_pending);

        // Advance was 100% consumed (40 / 40), so bill_status becomes bill_available
        $advanceGrn->refresh();
        $this->assertEquals('bill_available', $advanceGrn->bill_status);

        // BillReconciliation
        $this->assertDatabaseHas('bill_reconciliations', [
            'goods_received_id' => $billGrn->id,
            'total_bill_base_qty' => 100,
            'total_matched_base_qty' => 40,
            'total_new_receive_base_qty' => 60,
            'source_type' => 'mixed',
        ]);
    }

    public function test_match_tab_not_required_to_complete_pending_receive(): void
    {
        // Receive form loads advance suggestions and preview directly
        $advanceGrn = GoodsReceived::factory()->create([
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_at' => now(),
        ]);
        $advanceItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advanceGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 50,
            'received_unit' => 'kg',
        ]);
        StockBatch::factory()->create([
            'goods_received_id' => $advanceGrn->id,
            'goods_received_item_id' => $advanceItem->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 50,
            'warehouse_receive_pending' => false,
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
        ]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'unit_price' => 20,
        ]);
        $billGrn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'pending_approval',
            'bill_status' => 'bill_pending',
            'received_at' => now(),
        ]);
        GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'received_qty' => 50,
            'received_unit' => 'kg',
        ]);

        $response = $this->actingAs($this->warehouseUser)
            ->get(route('warehouse.receiver.receive-grn', $billGrn));

        $response->assertOk();
        $response->assertSee('Warehouse Advance Found');
        $response->assertSee('Confirm Match &amp; Receive', false);
    }

    public function test_advance_greater_than_bill_leaves_remaining_advance_open(): void
    {
        // 1. Advance of 100 kg
        $advanceGrn = GoodsReceived::factory()->create([
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_at' => now(),
        ]);
        $advanceItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advanceGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 100,
            'received_unit' => 'kg',
        ]);
        StockBatch::factory()->create([
            'goods_received_id' => $advanceGrn->id,
            'goods_received_item_id' => $advanceItem->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 100,
            'warehouse_receive_pending' => false,
        ]);

        // 2. Bill of 30 kg
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
        ]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 30,
            'unit_price' => 20,
        ]);
        $billGrn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'pending_approval',
            'bill_status' => 'bill_pending',
            'received_at' => now(),
        ]);
        $billGrnItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'received_qty' => 30,
            'received_unit' => 'kg',
        ]);
        $billBatch = StockBatch::factory()->create([
            'goods_received_id' => $billGrn->id,
            'goods_received_item_id' => $billGrnItem->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 30,
            'warehouse_receive_pending' => true,
        ]);

        // 3. Confirm 30 kg match
        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                $billGrnItem->id => [
                    'warehouse_id' => $this->warehouse->id,
                    'received_qty' => 30,
                    'discrepancy_type' => 'none',
                ],
            ],
            'advance_matches' => [
                [
                    'advance_goods_received_id' => $advanceGrn->id,
                    'advance_goods_received_item_id' => $advanceItem->id,
                    'purchase_order_item_id' => $poItem->id,
                    'goods_received_item_id' => $billGrnItem->id,
                    'product_id' => $this->product->id,
                    'matched_qty' => 30,
                    'unit' => 'kg',
                ],
            ],
        ];

        $this->actingAs($this->warehouseUser)
            ->post(route('warehouse.receiver.process-receive-grn', $billGrn), $payload)
            ->assertRedirect();

        // Bill GRN is completed
        $billGrn->refresh();
        $this->assertEquals('approved', $billGrn->status);
        $this->assertEquals('bill_available', $billGrn->bill_status);

        // Bill batch has 0 kg (covered by advance)
        $billBatch->refresh();
        $this->assertEquals(0.0, (float) $billBatch->total_kg);

        // Advance still has 70 kg unconsumed, so bill_status remains bill_pending
        $advanceGrn->refresh();
        $this->assertEquals('bill_pending', $advanceGrn->bill_status);

        // Candidates check shows 70 kg remaining available
        $candidates = app(AdvanceReceiveReconciliationService::class)->getOpenAdvanceCandidatesForProduct(
            $this->product->id,
            $this->warehouse->id
        );
        $this->assertCount(1, $candidates);
        $this->assertEquals(70.0, (float) $candidates[0]['available_qty']);
    }
}
