<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\AdvanceReceiveMatch;
use App\Models\DailyAdvanceMatchRun;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\DailyAdvanceMatchExecutionService;
use App\Services\Purchasing\DailyAdvanceMatchPlanningService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyAdvanceMatchExecutionTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Warehouse $warehouseA;

    private Product $apple;

    private Supplier $supplier;

    private Shop $shop;

    private DailyAdvanceMatchPlanningService $planningService;

    private DailyAdvanceMatchExecutionService $executionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->warehouseA = Warehouse::create([
            'name' => 'Alpha Main WH',
            'code' => 'WH-ALPHA',
            'is_active' => true,
        ]);

        $this->apple = Product::factory()->create([
            'name' => 'Fresh Apple',
            'sku' => 'APP-001',
            'default_warehouse_id' => $this->warehouseA->id,
            'unit' => 'kg',
        ]);

        $this->supplier = Supplier::factory()->create(['name' => 'Global Farms Ltd']);
        $this->shop = Shop::factory()->create([
            'name' => 'Downtown Retail Shop',
        ]);

        $this->planningService = app(DailyAdvanceMatchPlanningService::class);
        $this->executionService = app(DailyAdvanceMatchExecutionService::class);
    }

    public function test_full_bill_and_full_advance_clearance_and_zero_inventory_impact(): void
    {
        $date = '2026-09-11';

        // 1. Advance for 50kg
        $advanceGrn = $this->createAdvanceGrn($this->warehouseA, $this->apple, 50.0, $date);
        $advanceBatch = $advanceGrn->stockBatches->first();

        // 2. Bill for 50kg
        $billGrn = $this->createBillGrn($this->warehouseA, $this->apple, 50.0, $date);
        $billBatch = $billGrn->stockBatches->first();

        // Capture state before execution
        $stockMovementsBefore = StockMovement::count();
        $advanceBatchQtyBefore = (float) $advanceBatch->current_quantity;
        $billBatchQtyBefore = (float) $billBatch->current_quantity;

        // Plan
        $plan = $this->planningService->buildDailyPlan($this->warehouseA->id, $date, null, 100, $this->adminUser->id);
        $planHash = $plan['plan_hash'];

        // Execute
        $submissionId = (string) Str::uuid();
        $result = $this->executionService->execute(
            $this->warehouseA->id,
            $date,
            $planHash,
            $submissionId,
            $this->adminUser->id
        );

        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['summary']['processed']);
        $this->assertSame(0, $result['summary']['skipped']);
        $this->assertSame(50.0, (float) $result['summary']['matched_base_qty']);
        $this->assertSame(1, $result['summary']['advances_fully_cleared']);

        // 1. Verify AdvanceReceiveMatch created
        $this->assertSame(1, AdvanceReceiveMatch::count());
        $match = AdvanceReceiveMatch::first();
        $this->assertSame($advanceGrn->id, $match->advance_goods_received_id);
        $this->assertSame($billGrn->id, $match->bill_goods_received_id);
        $this->assertSame(50.0, (float) $match->base_qty);

        // 2. Advance GRN should be bill_available since fully cleared
        $this->assertSame('bill_available', $advanceGrn->fresh()->bill_status);

        // 3. ZERO INVENTORY EFFECT
        $this->assertSame($stockMovementsBefore, StockMovement::count(), 'Stock movements should not be created');
        $this->assertSame($advanceBatchQtyBefore, (float) $advanceBatch->fresh()->current_quantity, 'Advance stock batch quantity should not change');
        $this->assertSame($billBatchQtyBefore, (float) $billBatch->fresh()->current_quantity, 'Bill stock batch quantity should not change');
    }

    public function test_partial_bill_and_partial_advance_retains_remaining(): void
    {
        $date = '2026-09-11';

        // Advance for 30kg
        $advanceGrn = $this->createAdvanceGrn($this->warehouseA, $this->apple, 30.0, $date);

        // Bill for 50kg
        $billGrn = $this->createBillGrn($this->warehouseA, $this->apple, 50.0, $date);

        $plan = $this->planningService->buildDailyPlan($this->warehouseA->id, $date, null, 100, $this->adminUser->id);

        $submissionId = (string) Str::uuid();
        $result = $this->executionService->execute(
            $this->warehouseA->id,
            $date,
            $plan['plan_hash'],
            $submissionId,
            $this->adminUser->id
        );

        $this->assertSame('completed', $result['status']);
        $this->assertSame(30.0, (float) $result['summary']['matched_base_qty']);
        $this->assertSame(1, $result['summary']['advances_fully_cleared']);

        // Check new plan for remaining 20kg bill
        $nextPlan = $this->planningService->buildDailyPlan($this->warehouseA->id, $date, null, 100, $this->adminUser->id);
        $this->assertSame(0, $nextPlan['summary']['ready_bills']);
        $this->assertSame(0, $nextPlan['summary']['partial_bills']);
        $this->assertSame(1, $nextPlan['summary']['blocked_bills']);
        $this->assertSame(20.0, (float) $nextPlan['blocked_bills'][0]['required_base_qty']);
    }

    public function test_idempotent_execution_with_same_client_submission_id(): void
    {
        $date = '2026-09-11';

        $advanceGrn = $this->createAdvanceGrn($this->warehouseA, $this->apple, 100.0, $date);
        $billGrn = $this->createBillGrn($this->warehouseA, $this->apple, 40.0, $date);

        $plan = $this->planningService->buildDailyPlan($this->warehouseA->id, $date, null, 100, $this->adminUser->id);
        $submissionId = (string) Str::uuid();

        // 1st run
        $result1 = $this->executionService->execute($this->warehouseA->id, $date, $plan['plan_hash'], $submissionId, $this->adminUser->id);
        $this->assertSame('completed', $result1['status']);
        $this->assertSame(1, AdvanceReceiveMatch::count());
        $this->assertSame(1, DailyAdvanceMatchRun::count());

        // 2nd run with same submissionId -> idempotent replay
        $result2 = $this->executionService->execute($this->warehouseA->id, $date, $plan['plan_hash'], $submissionId, $this->adminUser->id);
        $this->assertSame('completed', $result2['status']);
        $this->assertSame(1, AdvanceReceiveMatch::count(), 'Should not insert duplicate matches');
        $this->assertSame(1, DailyAdvanceMatchRun::count(), 'Should not create duplicate runs');
    }

    public function test_hash_mismatch_returns_409_conflict(): void
    {
        $date = '2026-09-11';

        $advanceGrn = $this->createAdvanceGrn($this->warehouseA, $this->apple, 100.0, $date);
        $billGrn = $this->createBillGrn($this->warehouseA, $this->apple, 40.0, $date);

        $fakeHash = str_repeat('a', 64);
        $submissionId = (string) Str::uuid();

        $result = $this->executionService->execute($this->warehouseA->id, $date, $fakeHash, $submissionId, $this->adminUser->id);

        $this->assertArrayHasKey('status_code', $result);
        $this->assertSame(409, $result['status_code']);
        $this->assertSame('preview_changed', $result['error']['code']);
    }

    private function createAdvanceGrn(Warehouse $warehouse, Product $product, float $qty, string $date): GoodsReceived
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'ADV-'.uniqid(),
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $this->supplier->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_at' => $date,
            'received_by' => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
        ]);

        $item = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $product->unit,
            'unit_price' => 50.0,
            'total_price' => $qty * 50.0,
            'variance' => 0.0,
        ]);

        StockBatch::create([
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'reference' => 'BATCH-'.uniqid(),
            'total_kg' => $qty,
            'cost_per_kg' => 50.0,
            'quantity' => $qty,
            'current_quantity' => $qty,
            'received_at' => $date,
            'warehouse_receive_pending' => false,
            'status' => 'pending',
            'created_by' => $this->adminUser->id,
        ]);

        return $grn;
    }

    private function createBillGrn(Warehouse $warehouse, Product $product, float $qty, string $date): GoodsReceived
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-'.uniqid(),
            'warehouse_id' => $warehouse->id,
            'destination_shop_id' => $this->shop->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'order_date' => $date,
            'created_by' => $this->adminUser->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'unit_price' => 50.0,
            'total_price' => $qty * 50.0,
            'unit' => $product->unit,
        ]);

        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-'.uniqid(),
            'purchase_order_id' => $po->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $this->supplier->id,
            'receipt_type' => 'normal',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_at' => $date,
            'received_by' => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
        ]);

        $item = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $product->unit,
            'unit_price' => 50.0,
            'total_price' => $qty * 50.0,
            'variance' => 0.0,
        ]);

        StockBatch::create([
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'reference' => 'BATCH-'.uniqid(),
            'total_kg' => $qty,
            'cost_per_kg' => 50.0,
            'quantity' => $qty,
            'current_quantity' => $qty,
            'received_at' => $date,
            'warehouse_receive_pending' => false,
            'status' => 'pending',
            'created_by' => $this->adminUser->id,
        ]);

        return $grn;
    }
}
