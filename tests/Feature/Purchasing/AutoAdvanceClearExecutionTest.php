<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\AdvanceAutoClearRun;
use App\Models\AdvanceAutoClearRunItem;
use App\Models\AdvanceReceiveMatch;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\AdvanceAvailableBalanceCalculator;
use App\Services\Purchasing\AdvanceReceiveReconciliationService;
use App\Services\Purchasing\AutoAdvanceClearExecutionService;
use App\Services\Purchasing\AutoAdvanceClearPlanningService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AutoAdvanceClearExecutionTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $warehouseUser;

    private User $restrictedUser;

    private Warehouse $warehouseA;

    private Warehouse $warehouseB;

    private Product $apple;

    private Product $banana;

    private Supplier $supplier;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->warehouseUser = User::factory()->create();
        $this->warehouseUser->assignRole('warehouse_receiver');
        $this->warehouseUser->givePermissionTo([
            'inventory.stock.view',
            'inventory.product.view',
            'purchasing.order.view',
            'purchasing.grn.view',
            'purchasing.grn.create',
            'purchasing.grn.approve',
            'warehouse.receive.view',
            'warehouse.receive.confirm',
        ]);

        $this->warehouseA = Warehouse::create([
            'name' => 'Alpha Main WH',
            'code' => 'WH-ALPHA',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'name' => 'Beta Secondary WH',
            'code' => 'WH-BETA',
            'is_active' => true,
        ]);

        $this->warehouseUser->warehouses()->attach([$this->warehouseA->id, $this->warehouseB->id]);

        $this->restrictedUser = User::factory()->create();
        $this->restrictedUser->assignRole('warehouse_receiver');
        $this->restrictedUser->warehouses()->attach([$this->warehouseB->id]);

        $this->apple = Product::factory()->create([
            'name' => 'Fresh Apple',
            'sku' => 'APP-001',
            'default_warehouse_id' => $this->warehouseA->id,
            'unit' => 'kg',
            'base_price' => 50.0,
            'is_active' => true,
        ]);

        ProductUnit::create([
            'product_id' => $this->apple->id,
            'unit' => 'box',
            'label' => 'Box (10kg)',
            'conversion_to_base' => 10.0,
            'is_base' => false,
            'is_orderable' => true,
        ]);

        $this->banana = Product::factory()->create([
            'name' => 'Robusta Banana',
            'sku' => 'BAN-001',
            'default_warehouse_id' => $this->warehouseA->id,
            'unit' => 'kg',
            'base_price' => 30.0,
            'is_active' => true,
        ]);

        $this->supplier = Supplier::factory()->create([
            'name' => 'Reliable Agro Farms',
        ]);

        $this->shop = Shop::create([
            'name' => 'Retail Central Shop',
            'code' => 'SHOP-CTR',
            'is_active' => true,
        ]);
    }

    private function createAdvance(
        Warehouse $warehouse,
        Product $product,
        float $receivedQty,
        string $receivedAt,
        string $unit = 'kg',
        string $batchStatus = 'pending',
        bool $confirmedStock = true
    ): GoodsReceived {
        $grn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $warehouse->id,
            'destination_shop_id' => null,
            'purchase_order_id' => null,
            'grn_number' => 'ADV-'.uniqid(),
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->warehouseUser->id,
            'received_at' => $receivedAt,
            'approved_at' => now(),
            'approved_by' => $this->warehouseUser->id,
        ]);

        $item = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $product->id,
            'received_qty' => $receivedQty,
            'received_unit' => $unit,
            'variance' => 0.0,
        ]);

        StockBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->warehouseUser->id,
            'reference' => 'BATCH-'.uniqid(),
            'received_at' => $receivedAt,
            'total_kg' => $batchStatus === 'closed' ? 0.0 : $receivedQty,
            'cost_per_kg' => 50.0,
            'status' => $batchStatus === 'closed' ? BatchStatus::Closed : BatchStatus::Pending,
            'warehouse_receive_pending' => ! $confirmedStock,
            'warehouse_confirmed_at' => $confirmedStock ? now() : null,
            'warehouse_confirmed_by' => $confirmedStock ? $this->warehouseUser->id : null,
        ]);

        return $grn->fresh(['items', 'stockBatches']);
    }

    private function createPendingBill(
        Warehouse $warehouse,
        array $lines,
        string $orderDate,
        string $poNumberPrefix = 'PO-TEST'
    ): PurchaseOrder {
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'po_number' => $poNumberPrefix.'-'.uniqid(),
            'status' => POStatus::Approved,
            'order_date' => $orderDate,
            'destination_shop_id' => $warehouse->id,
            'created_by' => $this->warehouseUser->id,
        ]);

        foreach ($lines as $line) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'product_id' => $line['product_id'],
                'quantity' => $line['quantity'],
                'purchase_unit' => $line['unit'] ?? $line['purchase_unit'] ?? 'kg',
                'unit_price' => 50.0,
            ]);
        }

        return $po->fresh(['items.product']);
    }

    public function test_execution_requires_authentication(): void
    {
        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => str_repeat('a', 64),
            'client_submission_id' => (string) Str::uuid(),
        ]);
        $res->assertUnauthorized();
    }

    public function test_execution_requires_warehouse_authorization(): void
    {
        Sanctum::actingAs($this->restrictedUser);

        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id, // User only has warehouseB
            'plan_hash' => str_repeat('a', 64),
            'client_submission_id' => (string) Str::uuid(),
        ]);
        $res->assertForbidden();
    }

    public function test_execution_validates_required_fields(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', []);
        $res->assertUnprocessable();
        $res->assertJsonValidationErrors(['warehouse_id', 'plan_hash', 'client_submission_id']);
    }

    public function test_external_submission_id_must_be_a_valid_uuid(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => str_repeat('a', 64),
            'client_submission_id' => 'invalid-non-uuid-string',
        ]);
        $res->assertUnprocessable();
        $res->assertJsonValidationErrors(['client_submission_id']);
    }

    public function test_stale_hash_returns_409_with_zero_database_mutations(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 50.0]], '2026-08-25');

        $staleHash = str_repeat('f', 64);
        $submissionId = (string) Str::uuid();

        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $staleHash,
            'client_submission_id' => $submissionId,
        ]);

        $res->assertStatus(409);
        $res->assertJsonPath('code', 'preview_changed');
        $this->assertEquals(0, AdvanceAutoClearRun::count());
        $this->assertEquals(0, AdvanceReceiveMatch::count());
    }

    public function test_advance_100_bill_60_partial_clearing(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 60.0]], '2026-08-25');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);

        $res->assertOk();
        $data = $res->json('data');

        $this->assertEquals('completed', $data['status']);
        $this->assertEquals(1, $data['summary']['processed']);
        $this->assertEquals(60.0, $data['summary']['matched_base_qty']);
        $this->assertEquals(0, $data['summary']['advances_fully_cleared']);
        $this->assertEquals(1, $data['summary']['advances_partially_cleared']);

        // Advance remains bill_pending with 40kg open balance
        $adv->refresh();
        $this->assertEquals('bill_pending', $adv->bill_status);
        $this->assertEquals(1, GoodsReceived::openWarehouseAdvance($this->warehouseA->id)->count());
    }

    public function test_advances_60_and_40_fully_clear_100_bill(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv1 = $this->createAdvance($this->warehouseA, $this->apple, 60.0, '2026-08-20');
        $adv2 = $this->createAdvance($this->warehouseA, $this->apple, 40.0, '2026-08-21');
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 100.0]], '2026-08-25');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);

        $res->assertOk();
        $data = $res->json('data');

        $this->assertEquals(100.0, $data['summary']['matched_base_qty']);
        $this->assertEquals(2, $data['summary']['advances_fully_cleared']);
        $this->assertEquals(0, $data['summary']['advances_partially_cleared']);

        // Both advances transition to bill_available and disappear from open scope
        $this->assertEquals('bill_available', $adv1->fresh()->bill_status);
        $this->assertEquals('bill_available', $adv2->fresh()->bill_status);
        $this->assertEquals(0, GoodsReceived::openWarehouseAdvance($this->warehouseA->id)->count());
    }

    public function test_insufficient_advance_bill_is_not_executed(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->createAdvance($this->warehouseA, $this->apple, 40.0, '2026-08-20');
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 50.0]], '2026-08-25');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);

        $res->assertOk();
        $this->assertEquals(0, $res->json('data.summary.processed'));
        $this->assertEquals(0, AdvanceReceiveMatch::count());
    }

    public function test_multi_product_full_coverage_execution(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->createAdvance($this->warehouseA, $this->apple, 50.0, '2026-08-20');
        $this->createAdvance($this->warehouseA, $this->banana, 30.0, '2026-08-20');

        $po = $this->createPendingBill($this->warehouseA, [
            ['product_id' => $this->apple->id, 'quantity' => 50.0],
            ['product_id' => $this->banana->id, 'quantity' => 30.0],
        ], '2026-08-25');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);

        $res->assertOk();
        $this->assertEquals(80.0, $res->json('data.summary.matched_base_qty'));
        $this->assertEquals(1, $res->json('data.summary.processed'));
    }

    public function test_reconcile_existing_grn_mode_execution(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 60.0]], '2026-08-25');

        $pendingGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-INTAKE-EXISTING',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->warehouseUser->id,
            'received_at' => '2026-08-26',
        ]);
        $gItem = GoodsReceivedItem::create([
            'goods_received_id' => $pendingGrn->id,
            'purchase_order_item_id' => $po->items->first()->id,
            'product_id' => $this->apple->id,
            'received_qty' => 60.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'product_id' => $this->apple->id,
            'warehouse_id' => $this->warehouseA->id,
            'goods_received_id' => $pendingGrn->id,
            'goods_received_item_id' => $gItem->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->warehouseUser->id,
            'reference' => 'BATCH-EXISTING',
            'received_at' => '2026-08-26',
            'total_kg' => 60.0,
            'cost_per_kg' => 50.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => true,
        ]);

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $this->assertEquals('reconcile_existing_grn', $preview['ready_bills'][0]['execution_mode']);

        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $preview['plan_hash'],
            'client_submission_id' => (string) Str::uuid(),
        ]);

        $res->assertOk();
        $this->assertEquals($pendingGrn->id, $res->json('data.processed.0.result_goods_received_id'));
        $this->assertEquals('bill_available', $pendingGrn->fresh()->bill_status);
    }

    public function test_create_bill_grn_mode_execution(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvance($this->warehouseA, $this->apple, 60.0, '2026-08-20');
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 60.0]], '2026-08-25');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $this->assertEquals('create_bill_grn', $preview['ready_bills'][0]['execution_mode']);

        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $preview['plan_hash'],
            'client_submission_id' => (string) Str::uuid(),
        ]);

        $res->assertOk();
        $this->assertNotNull($res->json('data.processed.0.result_goods_received_id'));
    }

    public function test_create_bill_grn_uses_outstanding_po_quantity_only(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvance($this->warehouseA, $this->apple, 50.0, '2026-08-20');
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 100.0]], '2026-08-25');

        // Previous completed receipt of 50kg on this 100kg PO
        $completedGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-PAST',
            'status' => 'approved',
            'bill_status' => 'bill_available',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->warehouseUser->id,
            'received_at' => '2026-08-26',
        ]);
        $cItem = GoodsReceivedItem::create([
            'goods_received_id' => $completedGrn->id,
            'purchase_order_item_id' => $po->items->first()->id,
            'product_id' => $this->apple->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'product_id' => $this->apple->id,
            'warehouse_id' => $this->warehouseA->id,
            'goods_received_id' => $completedGrn->id,
            'goods_received_item_id' => $cItem->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->warehouseUser->id,
            'reference' => 'BATCH-PAST',
            'received_at' => '2026-08-26',
            'total_kg' => 50.0,
            'cost_per_kg' => 50.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
        ]);

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $this->assertEquals(50.0, $preview['ready_bills'][0]['matched_base_qty']);

        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $preview['plan_hash'],
            'client_submission_id' => (string) Str::uuid(),
        ]);

        $res->assertOk();
        $this->assertEquals(50.0, $res->json('data.summary.matched_base_qty'));
    }

    public function test_mixed_unit_execution_using_strict_conversion(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        // Advance: 5 boxes = 50 kg
        $this->createAdvance($this->warehouseA, $this->apple, 5.0, '2026-08-20', 'box');
        // PO: 50 kg
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 50.0, 'unit' => 'kg']], '2026-08-25');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $this->assertEquals(50.0, $preview['ready_bills'][0]['matched_base_qty']);

        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $preview['plan_hash'],
            'client_submission_id' => (string) Str::uuid(),
        ]);

        $res->assertOk();
        $this->assertEquals(50.0, $res->json('data.summary.matched_base_qty'));
    }

    public function test_closed_or_depleted_stock_batch_remains_unchanged(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvance($this->warehouseA, $this->apple, 50.0, '2026-08-20', 'kg', 'closed');
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 50.0]], '2026-08-25');

        $batch = $adv->stockBatches->first();
        $this->assertEquals(0.0, (float) $batch->total_kg);
        $this->assertEquals(BatchStatus::Closed, $batch->status);

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $preview['plan_hash'],
            'client_submission_id' => (string) Str::uuid(),
        ]);

        $res->assertOk();
        $batch->refresh();
        $this->assertEquals(0.0, (float) $batch->total_kg);
        $this->assertEquals(BatchStatus::Closed, $batch->status);
    }

    public function test_cross_warehouse_advances_are_never_consumed(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        // Advance in Warehouse B
        $this->createAdvance($this->warehouseB, $this->apple, 100.0, '2026-08-20');
        // Bill in Warehouse A
        $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 50.0]], '2026-08-25');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $this->assertEquals(0, $preview['summary']['ready_bills']);
    }

    public function test_same_submission_retry_returns_stored_result(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->createAdvance($this->warehouseA, $this->apple, 50.0, '2026-08-20');
        $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 50.0]], '2026-08-25');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        $res1 = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);
        $res1->assertOk();

        $res2 = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);
        $res2->assertOk();

        $this->assertEquals($res1->json('data.run_id'), $res2->json('data.run_id'));
        $this->assertEquals(1, AdvanceAutoClearRun::count());
    }

    public function test_different_payload_idempotency_conflict_returns_422(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->createAdvance($this->warehouseA, $this->apple, 50.0, '2026-08-20');
        $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 50.0]], '2026-08-25');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        $res1 = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);
        $res1->assertOk();

        // Submit same UUID with different warehouse
        $res2 = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseB->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);
        $res2->assertUnprocessable();
    }

    public function test_failure_isolation_first_item_fails_second_item_completes(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $po1 = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 50.0]], '2026-08-22', 'PO-FAIL');
        $po2 = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 40.0]], '2026-08-23', 'PO-OK');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        // Create run in DB with planned items
        $run = AdvanceAutoClearRun::create([
            'public_uuid' => (string) Str::uuid(),
            'client_submission_id' => $submissionId,
            'warehouse_id' => $this->warehouseA->id,
            'requested_by' => $this->warehouseUser->id,
            'requested_plan_hash' => $planHash,
            'status' => 'pending',
            'plan_snapshot' => $preview,
        ]);

        $item1 = $run->items()->create([
            'position' => 1,
            'execution_mode' => 'create_bill_grn',
            'purchase_order_id' => $po1->id,
            'planned_base_qty' => 50.0,
            'status' => 'pending',
        ]);

        $item2 = $run->items()->create([
            'position' => 2,
            'execution_mode' => 'create_bill_grn',
            'purchase_order_id' => $po2->id,
            'planned_base_qty' => 40.0,
            'status' => 'pending',
        ]);

        $realService = app(AdvanceReceiveReconciliationService::class);
        $this->partialMock(AdvanceReceiveReconciliationService::class, function ($mock) use ($po1, $realService) {
            $mock->shouldReceive('reconcileAndExecute')
                ->andReturnUsing(function ($grnData, $userId) use ($po1, $realService) {
                    if ($grnData->purchaseOrderId === $po1->id) {
                        throw new \RuntimeException('Simulated unexpected domain failure during reconciliation');
                    }

                    return $realService->reconcileAndExecute($grnData, $userId);
                });
        });

        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);

        $res->assertOk();
        $data = $res->json('data');

        $this->assertEquals('partial', $data['status']);
        $this->assertEquals(1, $data['summary']['processed']);
        $this->assertEquals(1, $data['summary']['failed']);
        $this->assertEquals(40.0, $data['summary']['matched_base_qty']);
        $this->assertEquals('reconciliation_failed', $data['failed'][0]['reason_code']);
        $this->assertEquals($po2->id, $data['processed'][0]['purchase_order_id']);
    }

    public function test_failed_transaction_leaves_no_partial_reconciliation_records(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 50.0]], '2026-08-25');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        $run = AdvanceAutoClearRun::create([
            'public_uuid' => (string) Str::uuid(),
            'client_submission_id' => $submissionId,
            'warehouse_id' => $this->warehouseA->id,
            'requested_by' => $this->warehouseUser->id,
            'requested_plan_hash' => $planHash,
            'status' => 'pending',
            'plan_snapshot' => $preview,
            'initialized_at' => now(),
        ]);
        $run->items()->create([
            'position' => 1,
            'execution_mode' => 'create_bill_grn',
            'purchase_order_id' => $po->id,
            'planned_base_qty' => 50.0,
            'status' => 'pending',
        ]);

        $this->partialMock(AdvanceReceiveReconciliationService::class, function ($mock) {
            $mock->shouldReceive('reconcileAndExecute')
                ->andThrow(new \RuntimeException('Database deadlock simulation'));
        });

        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);

        $res->assertOk();
        $this->assertEquals(0, AdvanceReceiveMatch::count());
        $this->assertEquals(0, $res->json('data.summary.processed'));
        $this->assertEquals(1, $res->json('data.summary.failed'));
        $this->assertStringNotContainsString('Database deadlock simulation', (string) $res->getContent());
    }

    public function test_failed_item_resumes_safely_on_the_same_submission(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 50.0]], '2026-08-25');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        // 1. Create a run with 1 failed item
        $run = AdvanceAutoClearRun::create([
            'public_uuid' => (string) Str::uuid(),
            'client_submission_id' => $submissionId,
            'warehouse_id' => $this->warehouseA->id,
            'requested_by' => $this->warehouseUser->id,
            'requested_plan_hash' => $planHash,
            'status' => 'failed',
            'plan_snapshot' => $preview,
            'initialized_at' => now(),
        ]);
        $item = $run->items()->create([
            'position' => 1,
            'execution_mode' => 'create_bill_grn',
            'purchase_order_id' => $po->id,
            'planned_base_qty' => 50.0,
            'status' => 'failed',
            'attempt_count' => 1,
        ]);

        // 2. Retry the run -> should process the previously failed item successfully!
        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);

        $res->assertOk();
        $data = $res->json('data');

        $this->assertEquals('completed', $data['status']);
        $this->assertEquals(1, $data['summary']['processed']);
        $this->assertEquals(0, $data['summary']['failed']);
        $this->assertEquals(2, $item->fresh()->attempt_count);
    }

    public function test_completed_item_is_not_repeated_during_resume(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $po1 = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 30.0]], '2026-08-22', 'PO-DONE');
        $po2 = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 40.0]], '2026-08-23', 'PO-RETRY');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        // 1. First run executes PO1 and completes it
        $res1 = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);
        $res1->assertOk();
        $this->assertEquals(2, $res1->json('data.summary.processed'));

        $matchCountAfterFirstRun = AdvanceReceiveMatch::count();

        // 2. Second call with exact same submissionId returns stored result without creating more matches
        $res2 = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);
        $res2->assertOk();

        $this->assertEquals($matchCountAfterFirstRun, AdvanceReceiveMatch::count());
    }

    public function test_interrupted_processing_recovery_resumes_safely(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 50.0]], '2026-08-25');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        // Simulate an interrupted run left in "processing"
        $run = AdvanceAutoClearRun::create([
            'public_uuid' => (string) Str::uuid(),
            'client_submission_id' => $submissionId,
            'warehouse_id' => $this->warehouseA->id,
            'requested_by' => $this->warehouseUser->id,
            'requested_plan_hash' => $planHash,
            'status' => 'processing',
            'plan_snapshot' => $preview,
            'initialized_at' => now(),
        ]);
        $run->items()->create([
            'position' => 1,
            'execution_mode' => 'create_bill_grn',
            'purchase_order_id' => $po->id,
            'planned_base_qty' => 50.0,
            'status' => 'processing',
        ]);

        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);

        $res->assertOk();
        $this->assertEquals('completed', $res->json('data.status'));
        $this->assertEquals(1, $res->json('data.summary.processed'));
    }

    public function test_allocation_changed_after_run_creation_is_detected_in_lock(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvance($this->warehouseA, $this->apple, 60.0, '2026-08-20');
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 60.0]], '2026-08-25');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        // 1. Create run snapshot
        $run = AdvanceAutoClearRun::create([
            'public_uuid' => (string) Str::uuid(),
            'client_submission_id' => $submissionId,
            'warehouse_id' => $this->warehouseA->id,
            'requested_by' => $this->warehouseUser->id,
            'requested_plan_hash' => $planHash,
            'status' => 'pending',
            'plan_snapshot' => $preview,
        ]);

        $run->items()->create([
            'position' => 1,
            'execution_mode' => 'create_bill_grn',
            'purchase_order_id' => $po->id,
            'planned_base_qty' => 60.0,
            'status' => 'pending',
        ]);

        // 2. Competing transaction changes data AFTER run creation (consumes 20 kg of advance)
        $competingGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'grn_number' => 'GRN-OTHER',
            'status' => 'approved',
            'bill_status' => 'bill_available',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->warehouseUser->id,
            'received_at' => now(),
        ]);
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $adv->id,
            'advance_goods_received_item_id' => $adv->items->first()->id,
            'bill_goods_received_id' => $competingGrn->id,
            'product_id' => $this->apple->id,
            'matched_qty' => 20.0,
            'matched_unit' => 'kg',
            'base_qty' => 20.0,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->warehouseUser->id,
            'confirmed_at' => now(),
        ]);

        // 3. Resume run -> In-lock validation detects available balance is only 40 kg (< planned 60 kg)
        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);

        $res->assertOk();
        $data = $res->json('data');

        $this->assertEquals(0, $data['summary']['processed']);
        $this->assertEquals(1, $data['summary']['skipped']);
        $this->assertEquals('allocation_changed', $data['skipped'][0]['reason_code']);
    }

    public function test_target_completed_after_run_creation_is_detected_in_lock(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 60.0]], '2026-08-25');

        $pendingGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-INTAKE-TEST',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->warehouseUser->id,
            'received_at' => '2026-08-26',
        ]);
        $gItem = GoodsReceivedItem::create([
            'goods_received_id' => $pendingGrn->id,
            'purchase_order_item_id' => $po->items->first()->id,
            'product_id' => $this->apple->id,
            'received_qty' => 60.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);
        $batch = StockBatch::create([
            'product_id' => $this->apple->id,
            'warehouse_id' => $this->warehouseA->id,
            'goods_received_id' => $pendingGrn->id,
            'goods_received_item_id' => $gItem->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->warehouseUser->id,
            'reference' => 'BATCH-T',
            'received_at' => '2026-08-26',
            'total_kg' => 60.0,
            'cost_per_kg' => 50.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => true,
        ]);

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        // 1. Create run snapshot for reconcile_existing_grn
        $run = AdvanceAutoClearRun::create([
            'public_uuid' => (string) Str::uuid(),
            'client_submission_id' => $submissionId,
            'warehouse_id' => $this->warehouseA->id,
            'requested_by' => $this->warehouseUser->id,
            'requested_plan_hash' => $planHash,
            'status' => 'pending',
            'plan_snapshot' => $preview,
        ]);

        $run->items()->create([
            'position' => 1,
            'execution_mode' => 'reconcile_existing_grn',
            'purchase_order_id' => $po->id,
            'source_goods_received_id' => $pendingGrn->id,
            'planned_base_qty' => 60.0,
            'status' => 'pending',
        ]);

        // 2. Competing process completes the GRN physically in warehouse
        $batch->update([
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->warehouseUser->id,
        ]);

        // 3. Resume run -> In-lock validation detects GRN was already received!
        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);

        $res->assertOk();
        $data = $res->json('data');

        $this->assertEquals(0, $data['summary']['processed']);
        $this->assertEquals(1, $data['summary']['skipped']);
        $this->assertEquals('already_processed', $data['skipped'][0]['reason_code']);
    }

    public function test_new_pending_grn_after_run_creation_is_detected_in_lock(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 60.0]], '2026-08-25');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        $run = AdvanceAutoClearRun::create([
            'public_uuid' => (string) Str::uuid(),
            'client_submission_id' => $submissionId,
            'warehouse_id' => $this->warehouseA->id,
            'requested_by' => $this->warehouseUser->id,
            'requested_plan_hash' => $planHash,
            'status' => 'pending',
            'plan_snapshot' => $preview,
            'initialized_at' => now(),
        ]);
        $run->items()->create([
            'position' => 1,
            'execution_mode' => 'create_bill_grn',
            'purchase_order_id' => $po->id,
            'planned_base_qty' => 60.0,
            'status' => 'pending',
        ]);

        $pendingGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-NEW-PENDING',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->warehouseUser->id,
            'received_at' => now(),
        ]);
        StockBatch::create([
            'product_id' => $this->apple->id,
            'warehouse_id' => $this->warehouseA->id,
            'goods_received_id' => $pendingGrn->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->warehouseUser->id,
            'reference' => 'BATCH-N',
            'received_at' => now(),
            'total_kg' => 60.0,
            'cost_per_kg' => 50.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => true,
        ]);

        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);

        $res->assertOk();
        $this->assertEquals(0, $res->json('data.summary.processed'));
        $this->assertEquals(1, $res->json('data.summary.skipped'));
        $this->assertEquals('target_state_changed', $res->json('data.skipped.0.reason_code'));
    }

    public function test_activity_log_contains_only_bounded_audit_records_for_both_events(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 60.0]], '2026-08-25');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);

        $res->assertOk();

        $activities = Activity::query()->where('log_name', 'purchasing')->orderBy('id')->get();
        $this->assertGreaterThanOrEqual(2, $activities->count());

        $started = $activities->firstWhere('description', 'Auto-clear run started');
        $completed = $activities->firstWhere('description', 'Auto-clear run completed');

        $this->assertNotNull($started);
        $this->assertNotNull($completed);

        // Bounded properties check
        foreach ([$started, $completed] as $act) {
            $this->assertEquals($this->warehouseUser->id, $act->causer_id);
            $props = $act->properties->toArray();
            $this->assertArrayHasKey('run_public_uuid', $props);
            $this->assertArrayHasKey('warehouse_id', $props);
            $this->assertArrayHasKey('requested_plan_hash', $props);

            // Must NOT contain unbounded/sensitive data
            $this->assertArrayNotHasKey('plan_snapshot', $props);
            $this->assertArrayNotHasKey('lines', $props);
            $this->assertArrayNotHasKey('matches', $props);
            $this->assertArrayNotHasKey('stack_trace', $props);
        }
    }

    public function test_raw_exception_messages_are_never_exposed_in_api_response(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 50.0]], '2026-08-25');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        $this->partialMock(AdvanceReceiveReconciliationService::class, function ($mock) {
            $mock->shouldReceive('reconcileAndExecute')
                ->andThrow(new \RuntimeException('CRITICAL_DATABASE_SECRET_PASSWORD_EXCEPTION'));
        });

        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);

        $res->assertOk();
        $this->assertStringNotContainsString('CRITICAL_DATABASE_SECRET_PASSWORD_EXCEPTION', (string) $res->getContent());
        $this->assertEquals('reconciliation_failed', $res->json('data.failed.0.reason_code'));
    }

    public function test_zero_duplicate_physical_inventory_created(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->createAdvance($this->warehouseA, $this->apple, 60.0, '2026-08-20');
        $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 60.0]], '2026-08-25');

        $batchesBefore = StockBatch::count();
        $movementsBefore = StockMovement::count();

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $preview['plan_hash'],
            'client_submission_id' => (string) Str::uuid(),
        ]);

        $res->assertOk();

        // No new stock batches or movements created!
        $this->assertEquals($batchesBefore, StockBatch::count());
        $this->assertEquals($movementsBefore, StockMovement::count());
    }

    public function test_legacy_match_for_product_a_does_not_reduce_product_b_availability(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        // One advance GRN with both Apple (50kg) and Banana (50kg)
        $grn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'grn_number' => 'ADV-MULTI',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->warehouseUser->id,
            'received_at' => '2026-08-20',
            'approved_at' => now(),
            'approved_by' => $this->warehouseUser->id,
        ]);
        $itemApple = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->apple->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);
        $itemBanana = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->banana->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);
        foreach ([$itemApple, $itemBanana] as $it) {
            StockBatch::create([
                'product_id' => $it->product_id,
                'warehouse_id' => $this->warehouseA->id,
                'goods_received_id' => $grn->id,
                'goods_received_item_id' => $it->id,
                'purchase_grade' => 'A',
                'grading_mode' => 'sort_required',
                'created_by' => $this->warehouseUser->id,
                'reference' => 'BATCH-'.uniqid(),
                'received_at' => '2026-08-20',
                'total_kg' => 50.0,
                'cost_per_kg' => 50.0,
                'status' => BatchStatus::Pending,
                'warehouse_receive_pending' => false,
            ]);
        }

        $dummyBillGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'grn_number' => 'GRN-LEG',
            'status' => 'approved',
            'bill_status' => 'bill_available',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->warehouseUser->id,
            'received_at' => now(),
        ]);

        // Legacy match for Apple of 40kg (null advance_goods_received_item_id)
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $grn->id,
            'advance_goods_received_item_id' => null,
            'bill_goods_received_id' => $dummyBillGrn->id,
            'product_id' => $this->apple->id,
            'matched_qty' => 40.0,
            'matched_unit' => 'kg',
            'base_qty' => 40.0,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->warehouseUser->id,
            'confirmed_at' => now(),
        ]);

        $calc = app(AdvanceAvailableBalanceCalculator::class);
        $balances = $calc->calculateItemAvailableBase($grn->fresh());

        // Apple is reduced to 10kg, but Banana is UNTOUCHED at 50kg!
        $this->assertEquals(10.0, $balances[$itemApple->id]);
        $this->assertEquals(50.0, $balances[$itemBanana->id]);
    }

    public function test_two_same_product_items_deduct_legacy_match_once(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        // One advance GRN with two items of Apple: Item 1 (50kg), Item 2 (30kg)
        $grn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'grn_number' => 'ADV-TWO-ITEMS',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->warehouseUser->id,
            'received_at' => '2026-08-20',
            'approved_at' => now(),
            'approved_by' => $this->warehouseUser->id,
        ]);
        $item1 = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->apple->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);
        $item2 = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->apple->id,
            'received_qty' => 30.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $dummyBillGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'grn_number' => 'GRN-LEG2',
            'status' => 'approved',
            'bill_status' => 'bill_available',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->warehouseUser->id,
            'received_at' => now(),
        ]);

        // One 40kg legacy match for Apple
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $grn->id,
            'advance_goods_received_item_id' => null,
            'bill_goods_received_id' => $dummyBillGrn->id,
            'product_id' => $this->apple->id,
            'matched_qty' => 40.0,
            'matched_unit' => 'kg',
            'base_qty' => 40.0,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->warehouseUser->id,
            'confirmed_at' => now(),
        ]);

        $calc = app(AdvanceAvailableBalanceCalculator::class);
        $balances = $calc->calculateItemAvailableBase($grn->fresh());

        // Item 1 (50kg) absorbs 40kg -> remaining 10kg
        // Item 2 (30kg) absorbs 0kg -> remaining 30kg
        // Total remaining is 40kg (80 - 40), NOT 10 + (-10) = 0!
        $this->assertEquals(10.0, $balances[$item1->id]);
        $this->assertEquals(30.0, $balances[$item2->id]);
    }

    public function test_combination_of_item_specific_and_legacy_matches(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $advItem = $adv->items->first();

        $dummyBillGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'grn_number' => 'GRN-COMBO',
            'status' => 'approved',
            'bill_status' => 'bill_available',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->warehouseUser->id,
            'received_at' => now(),
        ]);

        // Item-specific match: 30kg
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $adv->id,
            'advance_goods_received_item_id' => $advItem->id,
            'bill_goods_received_id' => $dummyBillGrn->id,
            'product_id' => $this->apple->id,
            'matched_qty' => 30.0,
            'matched_unit' => 'kg',
            'base_qty' => 30.0,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->warehouseUser->id,
            'confirmed_at' => now(),
        ]);

        // Legacy match: 20kg
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $adv->id,
            'advance_goods_received_item_id' => null,
            'bill_goods_received_id' => $dummyBillGrn->id,
            'product_id' => $this->apple->id,
            'matched_qty' => 20.0,
            'matched_unit' => 'kg',
            'base_qty' => 20.0,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->warehouseUser->id,
            'confirmed_at' => now(),
        ]);

        $calc = app(AdvanceAvailableBalanceCalculator::class);
        $balances = $calc->calculateItemAvailableBase($adv->fresh());

        // 100 - 30 - 20 = 50kg remaining
        $this->assertEquals(50.0, $balances[$advItem->id]);
    }

    public function test_legacy_quantity_spanning_two_same_product_items(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $grn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'grn_number' => 'ADV-SPAN',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->warehouseUser->id,
            'received_at' => '2026-08-20',
            'approved_at' => now(),
            'approved_by' => $this->warehouseUser->id,
        ]);
        $item1 = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->apple->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);
        $item2 = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->apple->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $dummyBillGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'grn_number' => 'GRN-SPAN-LEG',
            'status' => 'approved',
            'bill_status' => 'bill_available',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->warehouseUser->id,
            'received_at' => now(),
        ]);

        // 70kg legacy match spans across item 1 (50kg) and into item 2 (20kg)
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $grn->id,
            'advance_goods_received_item_id' => null,
            'bill_goods_received_id' => $dummyBillGrn->id,
            'product_id' => $this->apple->id,
            'matched_qty' => 70.0,
            'matched_unit' => 'kg',
            'base_qty' => 70.0,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->warehouseUser->id,
            'confirmed_at' => now(),
        ]);

        $calc = app(AdvanceAvailableBalanceCalculator::class);
        $balances = $calc->calculateItemAvailableBase($grn->fresh());

        // Item 1: 50 - 50 = 0.0
        // Item 2: 50 - 20 = 30.0
        $this->assertEquals(0.0, $balances[$item1->id]);
        $this->assertEquals(30.0, $balances[$item2->id]);
    }

    public function test_planner_and_executor_return_identical_availability(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 45.0]], '2026-08-25');

        $planner = app(AutoAdvanceClearPlanningService::class);
        $plan = $planner->buildAutoClearPlan($this->warehouseA->id, $this->warehouseUser->id);

        $calc = app(AdvanceAvailableBalanceCalculator::class);
        $balances = $calc->calculateItemAvailableBase($adv->fresh());

        $this->assertEquals(100.0, $balances[$adv->items->first()->id]);
        $this->assertEquals(45.0, $plan['summary']['matched_base_qty']);
    }

    public function test_atomic_run_creation_handles_first_or_create_idempotency(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 60.0]], '2026-08-25');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        // 1st call creates the run via firstOrCreate
        $res1 = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);
        $res1->assertOk();

        // 2nd call loads existing run via firstOrCreate without duplicating items or throwing duplicate key error
        $res2 = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);
        $res2->assertOk();

        $this->assertEquals($res1->json('data.run_id'), $res2->json('data.run_id'));
        $this->assertEquals(1, AdvanceAutoClearRun::count());
        $this->assertEquals(1, AdvanceAutoClearRunItem::count());
    }

    public function test_exception_during_run_item_creation_rolls_back_run_row_and_all_items(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 60.0]], '2026-08-25');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        // Listen for AdvanceAutoClearRunItem creating and throw to simulate crash during child item creation
        AdvanceAutoClearRunItem::creating(function () {
            throw new \RuntimeException('Simulated crash during run items insertion');
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Simulated crash during run items insertion');

        try {
            app(AutoAdvanceClearExecutionService::class)->execute(
                $this->warehouseA->id,
                $planHash,
                $submissionId,
                $this->warehouseUser->id
            );
        } finally {
            // Verify that both the run row and all items were completely rolled back
            $this->assertEquals(0, AdvanceAutoClearRun::count());
            $this->assertEquals(0, AdvanceAutoClearRunItem::count());
        }
    }

    public function test_uninitialized_interrupted_run_completes_initialization_before_processing(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 60.0]], '2026-08-25');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        // Create an uninitialized run (initialized_at = null, items = 0)
        $run = AdvanceAutoClearRun::create([
            'public_uuid' => (string) Str::uuid(),
            'client_submission_id' => $submissionId,
            'warehouse_id' => $this->warehouseA->id,
            'requested_by' => $this->warehouseUser->id,
            'requested_plan_hash' => $planHash,
            'status' => 'pending',
            'plan_snapshot' => $preview,
            'initialized_at' => null,
        ]);
        $this->assertEquals(0, $run->items()->count());

        // Resuming must populate items, set initialized_at, and process the items
        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);

        $res->assertOk();
        $this->assertNotNull($run->fresh()->initialized_at);
        $this->assertEquals(1, $run->fresh()->items()->count());
        $this->assertEquals(1, $res->json('data.summary.processed'));
    }

    public function test_zero_ready_bill_plan_completes_legitimately_with_zero_items(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        // No advances or bills in warehouse
        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $this->assertEquals(0, $preview['summary']['ready_bills']);

        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);

        $res->assertOk();
        $run = AdvanceAutoClearRun::where('client_submission_id', $submissionId)->first();
        $this->assertNotNull($run);
        $this->assertNotNull($run->initialized_at);
        $this->assertEquals('completed', $run->status);
        $this->assertEquals(0, $run->items()->count());
        $this->assertEquals(0, $res->json('data.summary.processed'));
    }

    public function test_retry_does_not_create_duplicate_run_items(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 60.0]], '2026-08-25');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        $res1 = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);
        $res1->assertOk();

        $itemCountFirst = AdvanceAutoClearRunItem::count();
        $this->assertEquals(1, $itemCountFirst);

        $res2 = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);
        $res2->assertOk();

        $this->assertEquals($itemCountFirst, AdvanceAutoClearRunItem::count());
    }

    public function test_eligible_po_with_received_status_clears_successfully_without_state_changed_error(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-TEST-RCV-ONLY',
            'status' => POStatus::Approved,
            'order_date' => '2026-08-25',
            'destination_shop_id' => $this->warehouseA->id,
            'created_by' => $this->warehouseUser->id,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->apple->id,
            'quantity' => 100.0,
            'purchase_unit' => 'kg',
            'unit_price' => 50.0,
        ]);
        $po->update(['status' => POStatus::Received]);

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $this->assertEquals(1, $preview['summary']['ready_bills']);

        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);

        $res->assertOk();
        $this->assertEquals(1, $res->json('data.summary.processed'));
        $this->assertEquals(0, $res->json('data.summary.skipped'));
        $this->assertEquals('bill_available', $adv->fresh()->bill_status);
    }

    public function test_concurrency_business_state_mutation_causes_stale_state_skip(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 60.0]], '2026-08-25');

        $preview = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $planHash = $preview['plan_hash'];
        $submissionId = (string) Str::uuid();

        // Mutate real business state field (change PO status to cancelled)
        $po->update(['status' => POStatus::Cancelled]);

        $res = $this->postJson('/api/v1/purchasing/grns/auto-clear', [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $planHash,
            'client_submission_id' => $submissionId,
        ]);

        // Stale state detected either by plan hash conflict (409) or item-level state validation
        if ($res->status() === 409) {
            $res->assertStatus(409);
        } else {
            $res->assertOk();
            $this->assertEquals(0, $res->json('data.summary.processed'));
            $this->assertEquals(1, $res->json('data.summary.skipped'));
        }
    }

    public function test_deterministic_plan_hash_same_state_loaded_twice(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 60.0]], '2026-08-25');

        $p1 = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');
        $p2 = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->json('data');

        $this->assertSame($p1['plan_hash'], $p2['plan_hash']);
    }
}
