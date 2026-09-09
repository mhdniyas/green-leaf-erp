<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

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
use App\Services\Purchasing\AutoAdvanceClearExecutionService;
use App\Services\Purchasing\AutoAdvanceClearPlanningService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AutoAdvanceClearPreviewRefreshTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Warehouse $warehouse;

    private Product $apple;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->supplier = Supplier::factory()->create();

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);

        $this->apple = Product::factory()->create([
            'name' => 'Red Apple',
            'sku' => 'APP-001',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
    }

    public function test_auto_match_execution_reduces_preview_candidate_counts(): void
    {
        // 1. Create open advance
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse->id,
            'grn_number' => 'GRN-ADV-100',
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_by' => $this->adminUser->id,
            'received_at' => '2026-08-01',
        ]);
        $advItem = GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->apple->id,
            'received_qty' => 50.0,
            'variance' => 0.0,
            'received_unit' => 'kg',
        ]);
        StockBatch::create([
            'product_id' => $this->apple->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $advGrn->id,
            'goods_received_item_id' => $advItem->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->adminUser->id,
            'reference' => 'BATCH-ADV-100',
            'received_by' => $this->adminUser->id,
            'received_at' => '2026-08-01',
            'total_kg' => 50.0,
            'cost_per_kg' => 10.0,
            'warehouse_receive_pending' => false,
        ]);

        // 2. Create approved pending bill GRN
        $shop = Shop::factory()->create();
        $po = PurchaseOrder::create([
            'po_number' => 'PO-BILL-100',
            'warehouse_id' => $this->warehouse->id,
            'destination_shop_id' => $shop->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'created_by' => $this->adminUser->id,
            'order_date' => '2026-08-05',
        ]);
        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->apple->id,
            'quantity' => 50.0,
            'unit' => 'kg',
            'unit_price' => 12.0,
        ]);
        $billGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-BILL-100',
            'receipt_type' => 'normal_purchase',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_by' => $this->adminUser->id,
            'received_at' => '2026-08-05',
        ]);
        $billItem = GoodsReceivedItem::create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->apple->id,
            'received_qty' => 50.0,
            'variance' => 0.0,
            'received_unit' => 'kg',
        ]);
        StockBatch::create([
            'product_id' => $this->apple->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $billGrn->id,
            'goods_received_item_id' => $billItem->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->adminUser->id,
            'reference' => 'BATCH-BILL-100',
            'received_at' => '2026-08-05',
            'total_kg' => 50.0,
            'cost_per_kg' => 12.0,
            'warehouse_receive_pending' => false,
        ]);

        $planning = app(AutoAdvanceClearPlanningService::class);
        $exec = app(AutoAdvanceClearExecutionService::class);

        // 3. First Preview -> 1 ready bill
        $preview1 = $planning->buildAutoClearPlan($this->warehouse->id, $this->adminUser->id);
        $this->assertCount(1, $preview1['ready_bills']);
        $this->assertEquals(50.0, $preview1['summary']['matched_base_qty']);

        // 4. Execute Auto Match
        $result = $exec->execute($this->warehouse->id, $preview1['plan_hash'], 'sub-test-100', $this->adminUser->id);
        $this->assertEquals('completed', $result['status']);

        // 5. Second Preview -> 0 ready bills (matched bill disappears!)
        $preview2 = $planning->buildAutoClearPlan($this->warehouse->id, $this->adminUser->id);
        $this->assertCount(0, $preview2['ready_bills']);
        $this->assertEquals(0.0, $preview2['summary']['matched_base_qty']);

        // 6. Running Auto Match a second time produces 0 new matches (no duplicate matches)
        $preview2Hash = $preview2['plan_hash'];
        $result2 = $exec->execute($this->warehouse->id, $preview2Hash, 'sub-test-200', $this->adminUser->id);
        $this->assertEquals(0, $result2['summary']['processed']);
    }

    public function test_manual_clear_prevents_auto_match(): void
    {
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse->id,
            'grn_number' => 'GRN-ADV-200',
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_by' => $this->adminUser->id,
            'received_at' => '2026-08-01',
        ]);
        $advItem = GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->apple->id,
            'received_qty' => 30.0,
            'variance' => 0.0,
            'received_unit' => 'kg',
        ]);
        StockBatch::create([
            'product_id' => $this->apple->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $advGrn->id,
            'goods_received_item_id' => $advItem->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->adminUser->id,
            'reference' => 'BATCH-ADV-200',
            'received_at' => '2026-08-01',
            'total_kg' => 30.0,
            'cost_per_kg' => 10.0,
            'warehouse_receive_pending' => false,
        ]);

        $planning = app(AutoAdvanceClearPlanningService::class);
        $preview1 = $planning->buildAutoClearPlan($this->warehouse->id, $this->adminUser->id);

        // Perform Manual Clear on Advance
        $response = $this->actingAs($this->adminUser)->post('/admin/cashbook/inventory/clear-advances', [
            'advance_ids' => [$advGrn->id],
            'reason' => 'Administrative manual clear for historical advance',
            'warehouse_id' => $this->warehouse->id,
        ]);
        $response->assertRedirect();

        // Advance is no longer bill_pending
        $this->assertEquals('bill_available', $advGrn->fresh()->bill_status);

        // Second preview returns 0 ready bills for this advance
        $preview2 = $planning->buildAutoClearPlan($this->warehouse->id, $this->adminUser->id);
        $this->assertCount(0, $preview2['ready_bills']);
    }
}
