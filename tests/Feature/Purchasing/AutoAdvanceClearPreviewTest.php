<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\AdvanceReceiveMatch;
use App\Models\BillReconciliation;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\AutoAdvanceClearPlanningService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AutoAdvanceClearPreviewTest extends TestCase
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

    public function test_preview_endpoint_requires_authentication(): void
    {
        $res = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $res->assertUnauthorized();
    }

    public function test_warehouse_permission_isolation_is_enforced(): void
    {
        Sanctum::actingAs($this->restrictedUser);

        // Restricted user cannot access Warehouse A
        $res = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $res->assertForbidden();

        // But can access Warehouse B
        $resB = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseB->id}");
        $resB->assertOk();
    }

    public function test_preview_is_completely_read_only_and_does_not_mutate_state(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-25');
        $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 60.0]], '2026-08-28');

        $countsBefore = [
            GoodsReceived::count(),
            GoodsReceivedItem::count(),
            AdvanceReceiveMatch::count(),
            BillReconciliation::count(),
            PurchaseOrder::count(),
            PurchaseInvoice::count(),
            StockBatch::count(),
            StockMovement::count(),
            JournalEntry::count(),
            Activity::count(),
        ];

        $res = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $res->assertOk();

        $countsAfter = [
            GoodsReceived::count(),
            GoodsReceivedItem::count(),
            AdvanceReceiveMatch::count(),
            BillReconciliation::count(),
            PurchaseOrder::count(),
            PurchaseInvoice::count(),
            StockBatch::count(),
            StockMovement::count(),
            JournalEntry::count(),
            Activity::count(),
        ];

        $this->assertSame($countsBefore, $countsAfter, 'Database record counts mutated during preview!');
    }

    public function test_po_without_grn_returns_create_bill_grn_mode(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 60.0]], '2026-08-25');

        $res = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $res->assertOk();

        $data = $res->json('data');
        $this->assertEquals(1, $data['summary']['ready_bills']);
        $ready = $data['ready_bills'][0];

        $this->assertEquals('create_bill_grn', $ready['execution_mode']);
        $this->assertEquals($po->id, $ready['purchase_order_id']);
        $this->assertNull($ready['source_goods_received_id']);
        $this->assertEquals(60.0, $ready['matched_base_qty']);
    }

    public function test_existing_pending_grn_returns_reconcile_existing_grn_mode_and_source_id(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        // Advance 100 kg
        $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');

        // PO ordered 100 kg
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 100.0]], '2026-08-25');
        $poItem = $po->items->first();

        // Existing pending intake GRN for 60 kg
        $pendingGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->warehouseA->id,
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-INTAKE-60',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->warehouseUser->id,
            'received_at' => '2026-08-26',
        ]);
        $grnItem = GoodsReceivedItem::create([
            'goods_received_id' => $pendingGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->apple->id,
            'received_qty' => 60.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'product_id' => $this->apple->id,
            'warehouse_id' => $this->warehouseA->id,
            'goods_received_id' => $pendingGrn->id,
            'goods_received_item_id' => $grnItem->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->warehouseUser->id,
            'reference' => 'BATCH-'.uniqid(),
            'received_at' => '2026-08-26',
            'total_kg' => 60.0,
            'cost_per_kg' => 50.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => true, // Pending intake
        ]);

        $res = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $res->assertOk();

        $data = $res->json('data');
        $this->assertEquals(1, $data['summary']['ready_bills']);
        $ready = $data['ready_bills'][0];

        $this->assertEquals('reconcile_existing_grn', $ready['execution_mode']);
        $this->assertEquals($po->id, $ready['purchase_order_id']);
        $this->assertEquals($pendingGrn->id, $ready['source_goods_received_id']);
        $this->assertEquals(60.0, $ready['matched_base_qty']); // Previews pending GRN 60 kg, NOT PO 100 kg!
    }

    public function test_partially_received_po_with_completed_grn_previews_only_remaining_outstanding_qty(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        // Advance 100 kg
        $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');

        // PO ordered 100 kg, marked partially_received
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-PARTIAL-100',
            'status' => POStatus::PartiallyReceived,
            'order_date' => '2026-08-25',
            'destination_shop_id' => $this->warehouseA->id,
            'created_by' => $this->warehouseUser->id,
        ]);
        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->apple->id,
            'quantity' => 100.0,
            'purchase_unit' => 'kg',
            'unit_price' => 50.0,
        ]);

        // Prior COMPLETED GRN of 40 kg
        $completedGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->warehouseA->id,
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-COMPLETED-40',
            'status' => 'approved',
            'bill_status' => 'bill_available',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->warehouseUser->id,
            'received_at' => '2026-08-26',
        ]);
        $compItem = GoodsReceivedItem::create([
            'goods_received_id' => $completedGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->apple->id,
            'received_qty' => 40.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'product_id' => $this->apple->id,
            'warehouse_id' => $this->warehouseA->id,
            'goods_received_id' => $completedGrn->id,
            'goods_received_item_id' => $compItem->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->warehouseUser->id,
            'reference' => 'BATCH-COMP-40',
            'received_at' => '2026-08-26',
            'total_kg' => 40.0,
            'cost_per_kg' => 50.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false, // Fully completed
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->warehouseUser->id,
        ]);

        $res = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $res->assertOk();

        $data = $res->json('data');
        $this->assertEquals(1, $data['summary']['ready_bills']);
        $ready = $data['ready_bills'][0];

        // Only outstanding 60 kg (100 - 40) is previewed!
        $this->assertEquals(60.0, $ready['matched_base_qty']);
        $this->assertEquals(60.0, $ready['lines'][0]['required_base_qty']);
        $this->assertEquals('create_bill_grn', $ready['execution_mode']);
    }

    public function test_multiple_pending_grns_on_single_po_create_independent_ready_entries(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        // Advance 100 kg
        $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');

        // PO ordered 100 kg
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 100.0]], '2026-08-25');
        $poItem = $po->items->first();

        // Pending GRN 1: 30 kg
        $pendingGrn1 = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->warehouseA->id,
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-INTAKE-30',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->warehouseUser->id,
            'received_at' => '2026-08-26',
        ]);
        $gItem1 = GoodsReceivedItem::create([
            'goods_received_id' => $pendingGrn1->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->apple->id,
            'received_qty' => 30.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'product_id' => $this->apple->id,
            'warehouse_id' => $this->warehouseA->id,
            'goods_received_id' => $pendingGrn1->id,
            'goods_received_item_id' => $gItem1->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->warehouseUser->id,
            'reference' => 'BATCH-1',
            'received_at' => '2026-08-26',
            'total_kg' => 30.0,
            'cost_per_kg' => 50.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => true,
        ]);

        // Pending GRN 2: 40 kg
        $pendingGrn2 = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->warehouseA->id,
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-INTAKE-40',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->warehouseUser->id,
            'received_at' => '2026-08-27',
        ]);
        $gItem2 = GoodsReceivedItem::create([
            'goods_received_id' => $pendingGrn2->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->apple->id,
            'received_qty' => 40.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);
        StockBatch::create([
            'product_id' => $this->apple->id,
            'warehouse_id' => $this->warehouseA->id,
            'goods_received_id' => $pendingGrn2->id,
            'goods_received_item_id' => $gItem2->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->warehouseUser->id,
            'reference' => 'BATCH-2',
            'received_at' => '2026-08-27',
            'total_kg' => 40.0,
            'cost_per_kg' => 50.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => true,
        ]);

        $res = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $res->assertOk();

        $data = $res->json('data');
        $this->assertEquals(2, $data['summary']['ready_bills']);
        $this->assertEquals(70.0, $data['summary']['matched_base_qty']);

        // Entry 1
        $this->assertEquals($pendingGrn1->id, $data['ready_bills'][0]['source_goods_received_id']);
        $this->assertEquals(30.0, $data['ready_bills'][0]['matched_base_qty']);

        // Entry 2
        $this->assertEquals($pendingGrn2->id, $data['ready_bills'][1]['source_goods_received_id']);
        $this->assertEquals(40.0, $data['ready_bills'][1]['matched_base_qty']);
    }

    public function test_empty_bill_or_zero_quantity_is_skipped_with_stable_reason(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');

        // PO with 0 items
        $poEmpty = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-EMPTY',
            'status' => POStatus::Approved,
            'order_date' => '2026-08-25',
            'destination_shop_id' => $this->warehouseA->id,
            'created_by' => $this->warehouseUser->id,
        ]);

        $res = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $res->assertOk();

        $data = $res->json('data');
        $this->assertEquals(1, $data['summary']['skipped_bills']);
        $this->assertEquals('no_reconcilable_items', $data['skipped_bills'][0]['reason']);
    }

    public function test_zero_or_negative_quantity_is_skipped_as_invalid_quantity(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');

        $po = $this->createPendingBill($this->warehouseA, [
            ['product_id' => $this->apple->id, 'quantity' => 0.0],
        ], '2026-08-25');

        $res = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $res->assertOk();

        $data = $res->json('data');
        $this->assertEquals(1, $data['summary']['skipped_bills']);
        $this->assertEquals('invalid_quantity', $data['skipped_bills'][0]['reason']);
    }

    public function test_unknown_advance_unit_does_not_enter_allocation_pool(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        // Advance with unknown unit (has no conversion factor configured)
        $this->createAdvance($this->warehouseA, $this->apple, 10.0, '2026-08-20', 'alien_unit');

        // Bill of Apples
        $bill = $this->createPendingBill($this->warehouseA, [
            ['product_id' => $this->apple->id, 'quantity' => 10.0],
        ], '2026-08-25');

        $res = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $res->assertOk();

        $data = $res->json('data');
        // Advance was excluded from pool -> Bill is skipped as no_advance!
        $this->assertEquals(0, $data['summary']['ready_bills']);
        $this->assertEquals(1, $data['summary']['skipped_bills']);
        $this->assertEquals('no_advance', $data['skipped_bills'][0]['reason']);

        // Warning was recorded
        $this->assertNotEmpty($data['warnings']);
        $this->assertEquals('invalid_advance_unit_conversion', $data['warnings'][0]['warning']);
    }

    public function test_plan_hash_changes_when_advance_allocation_or_quantity_changes(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv1 = $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $bill = $this->createPendingBill($this->warehouseA, [
            ['product_id' => $this->apple->id, 'quantity' => 50.0],
        ], '2026-08-25');

        $res1 = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $hash1 = $res1->json('data.plan_hash');

        // Same unchanged plan produces exact same hash
        $res1Repeat = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $this->assertSame($hash1, $res1Repeat->json('data.plan_hash'));

        // Change bill quantity from 50 to 60 -> hash must change!
        $bill->items->first()->update(['quantity' => 60.0]);

        $res2 = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $hash2 = $res2->json('data.plan_hash');

        $this->assertNotSame($hash1, $hash2);
    }

    public function test_advance_100_bill_60_results_in_ready_bill_and_40_remaining_advance(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv = $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-25');
        $bill = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 60.0]], '2026-08-28');

        $res = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $res->assertOk();

        $data = $res->json('data');
        $this->assertEquals(1, $data['summary']['ready_bills']);
        $this->assertEquals(0, $data['summary']['skipped_bills']);
        $this->assertEquals(60.0, $data['summary']['matched_base_qty']);
        $this->assertEquals(0, $data['summary']['advances_fully_cleared']);
        $this->assertEquals(1, $data['summary']['advances_partially_cleared']);

        $this->assertEquals($bill->id, $data['ready_bills'][0]['purchase_order_id']);
        $this->assertEquals(60.0, $data['ready_bills'][0]['matched_base_qty']);
        $this->assertEquals($adv->id, $data['ready_bills'][0]['lines'][0]['matches'][0]['advance_goods_received_id']);
        $this->assertEquals(60.0, $data['ready_bills'][0]['lines'][0]['matches'][0]['base_qty']);

        $this->assertEquals(40.0, $data['advance_allocations'][0]['remaining_base_qty']);
        $this->assertEquals(60.0, $data['advance_allocations'][0]['preview_matched_base_qty']);
    }

    public function test_advances_60_plus_40_bill_100_fully_covers_bill_using_fifo(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $adv1 = $this->createAdvance($this->warehouseA, $this->apple, 60.0, '2026-08-20');
        $adv2 = $this->createAdvance($this->warehouseA, $this->apple, 40.0, '2026-08-22');
        $bill = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 100.0]], '2026-08-28');

        $res = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $res->assertOk();

        $data = $res->json('data');
        $this->assertEquals(1, $data['summary']['ready_bills']);
        $this->assertEquals(2, $data['summary']['advances_fully_cleared']);
        $this->assertEquals(0, $data['summary']['advances_partially_cleared']);
        $this->assertEquals(100.0, $data['summary']['matched_base_qty']);

        $matches = $data['ready_bills'][0]['lines'][0]['matches'];
        $this->assertCount(2, $matches);
        $this->assertEquals($adv1->id, $matches[0]['advance_goods_received_id']);
        $this->assertEquals(60.0, $matches[0]['base_qty']);
        $this->assertEquals($adv2->id, $matches[1]['advance_goods_received_id']);
        $this->assertEquals(40.0, $matches[1]['base_qty']);
    }

    public function test_advance_60_bill_100_is_partial_match(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->createAdvance($this->warehouseA, $this->apple, 60.0, '2026-08-25');
        $bill = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 100.0]], '2026-08-28');

        $res = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $res->assertOk();

        $data = $res->json('data');
        // Partial match: bill 100 KG vs advance 60 KG → partial ready bill
        $this->assertEquals(1, $data['summary']['ready_bills']);
        $this->assertEquals(0, $data['summary']['skipped_bills']);
        $this->assertEquals(60.0, $data['summary']['matched_base_qty']);

        $readyBill = $data['ready_bills'][0];
        $this->assertEquals($bill->id, $readyBill['purchase_order_id']);
        $this->assertEquals('partial_match', $readyBill['match_type']);
        $this->assertEquals(60.0, $readyBill['matched_base_qty']);
        $this->assertEquals(40.0, $readyBill['remaining_unmatched_base_qty']);

        // Advance pool should be fully consumed
        $this->assertEquals(0.0, $data['advance_allocations'][0]['remaining_base_qty']);
    }

    public function test_query_count_remains_bounded(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        for ($i = 0; $i < 10; $i++) {
            $this->createAdvance($this->warehouseA, $this->apple, 100.0, "2026-08-0{$i}");
        }

        for ($i = 0; $i < 20; $i++) {
            $this->createPendingBill($this->warehouseA, [
                ['product_id' => $this->apple->id, 'quantity' => 50.0],
            ], '2026-08-10');
        }

        // Warm up
        $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}")->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $res = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $res->assertOk();

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        $this->assertLessThanOrEqual(18, $queryCount, "Query count {$queryCount} exceeded bounded threshold");
    }

    public function test_fully_completed_po_is_skipped_as_no_reconcilable_items(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');

        // PO ordered 100 kg
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 100.0]], '2026-08-25');
        $poItem = $po->items->first();

        // Completed GRN of full 100 kg
        $completedGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->warehouseA->id,
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-FULL-100',
            'status' => 'approved',
            'bill_status' => 'bill_available',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->warehouseUser->id,
            'received_at' => '2026-08-26',
        ]);
        $cItem = GoodsReceivedItem::create([
            'goods_received_id' => $completedGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->apple->id,
            'received_qty' => 100.0,
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
            'reference' => 'BATCH-FULL-100',
            'received_at' => '2026-08-26',
            'total_kg' => 100.0,
            'cost_per_kg' => 50.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->warehouseUser->id,
        ]);

        $res = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $res->assertOk();

        $data = $res->json('data');
        $this->assertEquals(0, $data['summary']['ready_bills']);
        $this->assertEquals(1, $data['summary']['skipped_bills']);
        $this->assertEquals('no_reconcilable_items', $data['skipped_bills'][0]['reason']);
    }

    public function test_negative_quantity_is_skipped_as_invalid_quantity(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');

        $this->createPendingBill($this->warehouseA, [
            ['product_id' => $this->apple->id, 'quantity' => -10.0],
        ], '2026-08-25');

        $res = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $res->assertOk();

        $data = $res->json('data');
        $this->assertEquals(1, $data['summary']['skipped_bills']);
        $this->assertEquals('invalid_quantity', $data['skipped_bills'][0]['reason']);
    }

    public function test_closed_or_depleted_stock_batch_remains_available_for_commercial_preview(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        // Advance with closed/depleted batch
        $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20', 'kg', 'closed');
        $this->createPendingBill($this->warehouseA, [
            ['product_id' => $this->apple->id, 'quantity' => 60.0],
        ], '2026-08-25');

        $res = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $res->assertOk();

        $data = $res->json('data');
        $this->assertEquals(1, $data['summary']['ready_bills']);
        $this->assertEquals(60.0, $data['summary']['matched_base_qty']);
    }

    public function test_hash_changes_when_source_pending_grn_quantity_changes(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');
        $po = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 100.0]], '2026-08-25');

        $pendingGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->warehouseA->id,
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-INTAKE-HASH',
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
            'received_qty' => 50.0,
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
            'reference' => 'BATCH-HASH',
            'received_at' => '2026-08-26',
            'total_kg' => 50.0,
            'cost_per_kg' => 50.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => true,
        ]);

        $res1 = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $hash1 = $res1->json('data.plan_hash');

        // Update pending GRN item quantity from 50 to 65
        $gItem->update(['received_qty' => 65.0]);

        $res2 = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $hash2 = $res2->json('data.plan_hash');

        $this->assertNotSame($hash1, $hash2);
    }

    public function test_auto_match_warehouse_scope_parity_where_destination_shop_id_differs(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-20');

        // PO where destination_shop_id is set to shop->id (not warehouseA->id directly),
        // but product default_warehouse_id is warehouseA->id (matched via WarehouseReceiptReadScope)
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-SCOPE-PARITY-001',
            'status' => POStatus::Approved,
            'order_date' => '2026-08-25',
            'destination_shop_id' => $this->shop->id,
            'created_by' => $this->warehouseUser->id,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->apple->id,
            'quantity' => 50.0,
            'purchase_unit' => 'kg',
            'unit_price' => 50.0,
        ]);

        // 1. Manual Match candidates sees the PO via WarehouseReceiptReadScope
        $manualRes = $this->getJson("/api/v1/purchasing/grns/advance-match-candidates?warehouse_id={$this->warehouseA->id}");
        $manualRes->assertOk();
        $manualPoIds = array_column($manualRes->json('data'), 'id');
        $this->assertContains($po->id, $manualPoIds, 'Manual Match candidates did not return scope-matched PO!');

        // 2. Auto Clear preview ALSO sees and matches the PO via WarehouseReceiptReadScope
        $autoRes = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $autoRes->assertOk();
        $autoData = $autoRes->json('data');
        $this->assertEquals(1, $autoData['summary']['ready_bills']);
        $this->assertEquals($po->id, $autoData['ready_bills'][0]['purchase_order_id']);
        $this->assertEquals(50.0, $autoData['ready_bills'][0]['matched_base_qty']);
    }

    public function test_candidate_orders_query_count_is_bounded_and_does_not_scale_per_item(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        for ($i = 0; $i < 5; $i++) {
            $this->createAdvance($this->warehouseA, $this->apple, 100.0, "2026-08-0{$i}");
        }

        for ($i = 0; $i < 25; $i++) {
            $this->createPendingBill($this->warehouseA, [
                ['product_id' => $this->apple->id, 'quantity' => 20.0],
                ['product_id' => $this->banana->id, 'quantity' => 15.0],
            ], '2026-08-10', "PO-PERF-{$i}");
        }

        // Warm up
        $this->getJson("/api/v1/purchasing/grns/advance-match-candidates?warehouse_id={$this->warehouseA->id}&per_page=25")->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $res = $this->getJson("/api/v1/purchasing/grns/advance-match-candidates?warehouse_id={$this->warehouseA->id}&per_page=25");
        $res->assertOk();

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        $this->assertLessThanOrEqual(17, $queryCount, "Candidate orders query count {$queryCount} exceeded bounded threshold <= 17");
    }

    public function test_goods_received_serialization_does_not_trigger_n_plus_one_queries_for_purchaser_cart(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        for ($i = 0; $i < 20; $i++) {
            $po = $this->createPendingBill($this->warehouseA, [
                ['product_id' => $this->apple->id, 'quantity' => 10.0],
            ], '2026-08-10', "PO-SERIAL-{$i}");

            GoodsReceived::create([
                'public_uuid' => (string) Str::uuid(),
                'warehouse_id' => $this->warehouseA->id,
                'destination_shop_id' => $this->warehouseA->id,
                'purchase_order_id' => $po->id,
                'grn_number' => "GRN-SERIAL-{$i}",
                'status' => 'approved',
                'bill_status' => 'bill_available',
                'receipt_type' => 'normal_purchase',
                'received_by' => $this->warehouseUser->id,
                'received_at' => '2026-08-10',
            ]);
        }

        // Warm up auth
        $this->getJson("/api/v1/purchasing/grns?warehouse_id={$this->warehouseA->id}&per_page=1")->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $res5 = $this->getJson("/api/v1/purchasing/grns?warehouse_id={$this->warehouseA->id}&per_page=5");
        $res5->assertOk();
        $q5 = count(DB::getQueryLog());

        DB::flushQueryLog();

        $res20 = $this->getJson("/api/v1/purchasing/grns?warehouse_id={$this->warehouseA->id}&per_page=20");
        $res20->assertOk();
        $q20 = count(DB::getQueryLog());

        $this->assertEquals($q5, $q20, "Query count for 20 GRNs ({$q20}) differed from 5 GRNs ({$q5}), indicating N+1 query leak in serialization.");
        $this->assertLessThanOrEqual(15, $q20);
    }

    public function test_planner_includes_open_advance_when_warehouse_id_is_null_but_resolved_via_stock_batch_or_product_default(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        // 1. Create an advance GRN with warehouse_id = null and destination_shop_id = null
        //    whose product default warehouse is warehouseA
        $advance = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => null,
            'destination_shop_id' => null,
            'purchase_order_id' => null,
            'grn_number' => 'ADV-NULL-WH-'.uniqid(),
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->warehouseUser->id,
            'received_at' => '2026-08-01',
            'approved_at' => '2026-08-01',
            'approved_by' => $this->warehouseUser->id,
        ]);

        $advItem = GoodsReceivedItem::create([
            'goods_received_id' => $advance->id,
            'product_id' => $this->apple->id, // default_warehouse_id is warehouseA
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        StockBatch::create([
            'goods_received_id' => $advance->id,
            'goods_received_item_id' => $advItem->id,
            'warehouse_id' => $this->warehouseA->id,
            'product_id' => $this->apple->id,
            'created_by' => $this->warehouseUser->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'reference' => 'BATCH-'.uniqid(),
            'total_kg' => 50.0,
            'cost_per_kg' => 50.0,
            'received_at' => '2026-08-01',
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => '2026-08-01',
            'warehouse_confirmed_by' => $this->warehouseUser->id,
        ]);

        // 2. Create a pending bill in warehouseA for 50kg of apple
        $this->createPendingBill($this->warehouseA, [
            ['product_id' => $this->apple->id, 'quantity' => 50.0],
        ], '2026-08-02', 'PO-NULL-WH-A');

        // 3. Planner for warehouseA must include the advance and mark the bill ready
        $service = app(AutoAdvanceClearPlanningService::class);
        $planA = $service->buildAutoClearPlan($this->warehouseA->id, $this->warehouseUser->id);

        $this->assertCount(1, $planA['ready_bills']);
        $this->assertEquals(50.0, $planA['summary']['matched_base_qty']);
        $this->assertEquals($advance->id, $planA['ready_bills'][0]['lines'][0]['matches'][0]['advance_goods_received_id']);

        // 4. Planner for warehouseB must exclude this advance
        $planB = $service->buildAutoClearPlan($this->warehouseB->id, $this->warehouseUser->id);
        $this->assertCount(0, $planB['ready_bills']);
        $this->assertEquals(0.0, $planB['summary']['matched_base_qty']);
    }

    public function test_same_bill_item_with_3_advance_grns_results_in_one_ready_line_and_3_allocations(): void
    {
        $this->createAdvance($this->warehouseA, $this->apple, 10.0, '2026-08-01');
        $this->createAdvance($this->warehouseA, $this->apple, 10.0, '2026-08-02');
        $this->createAdvance($this->warehouseA, $this->apple, 10.0, '2026-08-03');

        $this->createPendingBill($this->warehouseA, [
            ['product_id' => $this->apple->id, 'quantity' => 30.0],
        ], '2026-08-04');

        $service = app(AutoAdvanceClearPlanningService::class);
        $plan = $service->buildAutoClearPlan($this->warehouseA->id, $this->warehouseUser->id);

        $this->assertCount(1, $plan['ready_bills']);
        $lines = $plan['ready_bills'][0]['lines'];
        $this->assertCount(1, $lines);
        $this->assertEquals(30.0, $lines[0]['planned_matched_base_qty']);
        $this->assertCount(3, $lines[0]['allocations']);
    }

    public function test_partial_bill_item_has_correct_matched_and_remaining_quantities(): void
    {
        $this->createAdvance($this->warehouseA, $this->apple, 20.0, '2026-08-01');

        $this->createPendingBill($this->warehouseA, [
            ['product_id' => $this->apple->id, 'quantity' => 50.0],
        ], '2026-08-02');

        $service = app(AutoAdvanceClearPlanningService::class);
        $plan = $service->buildAutoClearPlan($this->warehouseA->id, $this->warehouseUser->id);

        $this->assertCount(1, $plan['ready_bills']);
        $line = $plan['ready_bills'][0]['lines'][0];
        $this->assertEquals('PARTIAL_MATCH', $line['classification']);
        $this->assertEquals(20.0, $line['planned_matched_base_qty']);
        $this->assertEquals(30.0, $line['remaining_unmatched_base_qty']);
    }

    public function test_same_product_on_two_genuine_bill_items_remains_separate(): void
    {
        $this->createAdvance($this->warehouseA, $this->apple, 100.0, '2026-08-01');

        $bill = $this->createPendingBill($this->warehouseA, [
            ['product_id' => $this->apple->id, 'quantity' => 10.0],
            ['product_id' => $this->apple->id, 'quantity' => 20.0],
        ], '2026-08-02');

        $service = app(AutoAdvanceClearPlanningService::class);
        $plan = $service->buildAutoClearPlan($this->warehouseA->id, $this->warehouseUser->id);

        $this->assertCount(1, $plan['ready_bills']);
        $lines = $plan['ready_bills'][0]['lines'];
        $this->assertCount(2, $lines);
        $this->assertNotEquals($lines[0]['source_item_id'], $lines[1]['source_item_id']);
    }

    public function test_unit_difference_item_classified_as_unit_difference(): void
    {
        $boxProd = Product::factory()->create(['unit' => 'box']);
        $this->createAdvance($this->warehouseA, $boxProd, 50.0, '2026-08-01');

        $this->createPendingBill($this->warehouseA, [
            ['product_id' => $boxProd->id, 'quantity' => 10.0, 'purchase_unit' => 'box'],
        ], '2026-08-02');

        $service = app(AutoAdvanceClearPlanningService::class);
        $plan = $service->buildAutoClearPlan($this->warehouseA->id, $this->warehouseUser->id);

        $this->assertCount(1, $plan['skipped_bills']);
        $line = $plan['skipped_bills'][0]['lines'][0];
        $this->assertEquals('UNIT_DIFFERENCE', $line['classification']);
        $this->assertEquals('UNIT_DIFFERENCE', $line['unmatched_reason']);
    }

    public function test_no_advance_item_classified_as_no_advance(): void
    {
        $this->createPendingBill($this->warehouseA, [
            ['product_id' => $this->apple->id, 'quantity' => 15.0],
        ], '2026-08-02');

        $service = app(AutoAdvanceClearPlanningService::class);
        $plan = $service->buildAutoClearPlan($this->warehouseA->id, $this->warehouseUser->id);

        $this->assertCount(1, $plan['skipped_bills']);
        $line = $plan['skipped_bills'][0]['lines'][0];
        $this->assertEquals('NO_ADVANCE', $line['classification']);
        $this->assertEquals('NO_ADVANCE', $line['unmatched_reason']);
    }

    public function test_advance_exhausted_item_classified_as_advance_exhausted(): void
    {
        $this->createAdvance($this->warehouseA, $this->apple, 10.0, '2026-08-01');

        $this->createPendingBill($this->warehouseA, [
            ['product_id' => $this->apple->id, 'quantity' => 10.0],
        ], '2026-08-02');

        $this->createPendingBill($this->warehouseA, [
            ['product_id' => $this->apple->id, 'quantity' => 15.0],
        ], '2026-08-03');

        $service = app(AutoAdvanceClearPlanningService::class);
        $plan = $service->buildAutoClearPlan($this->warehouseA->id, $this->warehouseUser->id);

        $this->assertCount(1, $plan['ready_bills']);
        $this->assertCount(1, $plan['skipped_bills']);
        $exhaustedLine = $plan['skipped_bills'][0]['lines'][0];
        $this->assertEquals('ADVANCE_EXHAUSTED', $exhaustedLine['classification']);
        $this->assertEquals('ADVANCE_EXHAUSTED', $exhaustedLine['unmatched_reason']);
    }

    public function test_unconfirmed_advance_item_classified_as_unconfirmed_advance(): void
    {
        $adv = $this->createAdvance($this->warehouseA, $this->apple, 25.0, '2026-08-01');
        StockBatch::query()->where('goods_received_id', $adv->id)->update(['warehouse_receive_pending' => true]);

        $this->createPendingBill($this->warehouseA, [
            ['product_id' => $this->apple->id, 'quantity' => 10.0],
        ], '2026-08-02');

        $service = app(AutoAdvanceClearPlanningService::class);
        $plan = $service->buildAutoClearPlan($this->warehouseA->id, $this->warehouseUser->id);

        $this->assertCount(1, $plan['skipped_bills']);
        $line = $plan['skipped_bills'][0]['lines'][0];
        $this->assertEquals('UNCONFIRMED_ADVANCE', $line['classification']);
        $this->assertEquals('UNCONFIRMED_ADVANCE', $line['unmatched_reason']);
    }

    public function test_warehouse_advance_accepts_kg_even_if_product_base_is_piece(): void
    {
        $pieceProduct = Product::factory()->create(['name' => 'Banana Stem Piece', 'unit' => 'piece']);

        $response = $this->actingAs($this->warehouseUser, 'sanctum')
            ->postJson('/api/v1/purchasing/grns', [
                'receipt_type' => 'warehouse_advance',
                'bill_status' => 'bill_pending',
                'received_at' => now()->toDateString(),
                'warehouse_id' => $this->warehouseA->id,
                'items' => [
                    [
                        'product_id' => $pieceProduct->id,
                        'received_qty' => 142.0,
                        'received_unit' => 'kg',
                    ],
                ],
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('goods_received_items', [
            'product_id' => $pieceProduct->id,
            'received_qty' => 142.0,
            'received_unit' => 'kg',
        ]);
    }

    public function test_warehouse_advance_rejects_arbitrary_unsupported_unit(): void
    {
        $pieceProduct = Product::factory()->create(['name' => 'Banana Stem Piece 2', 'unit' => 'piece']);

        $response = $this->actingAs($this->warehouseUser, 'sanctum')
            ->postJson('/api/v1/purchasing/grns', [
                'receipt_type' => 'warehouse_advance',
                'bill_status' => 'bill_pending',
                'received_at' => now()->toDateString(),
                'warehouse_id' => $this->warehouseA->id,
                'items' => [
                    [
                        'product_id' => $pieceProduct->id,
                        'received_qty' => 50.0,
                        'received_unit' => 'litre',
                    ],
                ],
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['items.0.received_unit']);
    }

    public function test_same_unit_matching_for_piece_base_product_with_bill_kg_and_advance_kg(): void
    {
        $pieceProduct = Product::factory()->create(['name' => 'Banana Stem Test', 'unit' => 'piece']);

        // Advance 142 kg
        $adv = $this->createAdvance($this->warehouseA, $pieceProduct, 142.0, '2026-08-01', 'kg');

        // Bill 6 kg
        $this->createPendingBill($this->warehouseA, [
            ['product_id' => $pieceProduct->id, 'quantity' => 6.0, 'purchase_unit' => 'kg'],
        ], '2026-08-02');

        $service = app(AutoAdvanceClearPlanningService::class);
        $plan = $service->buildAutoClearPlan($this->warehouseA->id, $this->warehouseUser->id);

        $this->assertCount(1, $plan['ready_bills']);
        $this->assertEquals('full_match', $plan['ready_bills'][0]['match_type']);
        $this->assertEquals(6.0, $plan['summary']['matched_base_qty']);
    }

    public function test_different_units_bill_piece_vs_advance_kg_yields_unit_difference(): void
    {
        $pieceProduct = Product::factory()->create(['name' => 'Raw Banana Test', 'unit' => 'piece']);

        // Advance 50 kg
        $adv = $this->createAdvance($this->warehouseA, $pieceProduct, 50.0, '2026-08-01', 'kg');

        // Bill 10 piece (different unit from advance kg)
        $this->createPendingBill($this->warehouseA, [
            ['product_id' => $pieceProduct->id, 'quantity' => 10.0, 'purchase_unit' => 'piece'],
        ], '2026-08-02');

        $service = app(AutoAdvanceClearPlanningService::class);
        $plan = $service->buildAutoClearPlan($this->warehouseA->id, $this->warehouseUser->id);

        $this->assertCount(1, $plan['skipped_bills']);
        $line = $plan['skipped_bills'][0]['lines'][0];
        $this->assertEquals('UNIT_DIFFERENCE', $line['classification']);
    }

    public function test_phase1db_1_same_product_base_piece_bill_6kg_advance_142kg_full_match(): void
    {
        $product = Product::factory()->create(['name' => 'Banana Stem P1', 'unit' => 'piece']);
        $this->createAdvance($this->warehouseA, $product, 142.0, '2026-08-01', 'kg');
        $this->createPendingBill($this->warehouseA, [
            ['product_id' => $product->id, 'quantity' => 6.0, 'purchase_unit' => 'kg'],
        ], '2026-08-02');

        $plan = app(AutoAdvanceClearPlanningService::class)->buildAutoClearPlan($this->warehouseA->id, $this->warehouseUser->id);
        $this->assertCount(1, $plan['ready_bills']);
        $this->assertEquals(6.0, $plan['summary']['matched_base_qty']);
    }

    public function test_phase1db_2_same_product_base_box_bill_2kg_advance_19kg_full_match(): void
    {
        $product = Product::factory()->create(['name' => 'Gala P2', 'unit' => 'box']);
        $this->createAdvance($this->warehouseA, $product, 19.0, '2026-08-01', 'kg');
        $this->createPendingBill($this->warehouseA, [
            ['product_id' => $product->id, 'quantity' => 2.0, 'purchase_unit' => 'kg'],
        ], '2026-08-02');

        $plan = app(AutoAdvanceClearPlanningService::class)->buildAutoClearPlan($this->warehouseA->id, $this->warehouseUser->id);
        $this->assertCount(1, $plan['ready_bills']);
        $this->assertEquals(2.0, $plan['summary']['matched_base_qty']);
    }

    public function test_phase1db_3_same_product_bill_20piece_advance_142kg_unit_difference_not_no_advance(): void
    {
        $product = Product::factory()->create(['name' => 'Banana Stem P3', 'unit' => 'piece']);
        $this->createAdvance($this->warehouseA, $product, 142.0, '2026-08-01', 'kg');
        $this->createPendingBill($this->warehouseA, [
            ['product_id' => $product->id, 'quantity' => 20.0, 'purchase_unit' => 'piece'],
        ], '2026-08-02');

        $plan = app(AutoAdvanceClearPlanningService::class)->buildAutoClearPlan($this->warehouseA->id, $this->warehouseUser->id);
        $this->assertCount(1, $plan['skipped_bills']);
        $line = $plan['skipped_bills'][0]['lines'][0];
        $this->assertEquals('UNIT_DIFFERENCE', $line['classification']);
        $this->assertNotEquals('NO_ADVANCE', $line['classification']);
    }

    public function test_phase1db_4_different_product_id_same_unit_must_not_match(): void
    {
        $prodA = Product::factory()->create(['name' => 'Apple A', 'unit' => 'kg']);
        $prodB = Product::factory()->create(['name' => 'Banana B', 'unit' => 'kg']);

        $this->createAdvance($this->warehouseA, $prodA, 100.0, '2026-08-01', 'kg');
        $this->createPendingBill($this->warehouseA, [
            ['product_id' => $prodB->id, 'quantity' => 10.0, 'purchase_unit' => 'kg'],
        ], '2026-08-02');

        $plan = app(AutoAdvanceClearPlanningService::class)->buildAutoClearPlan($this->warehouseA->id, $this->warehouseUser->id);
        $this->assertCount(0, $plan['ready_bills']);
        $this->assertCount(1, $plan['skipped_bills']);
        $this->assertEquals('NO_ADVANCE', $plan['skipped_bills'][0]['lines'][0]['classification']);
    }

    public function test_phase1db_5_same_product_multiple_advance_grns_fifo(): void
    {
        $product = Product::factory()->create(['name' => 'FIFO Prod', 'unit' => 'kg']);
        $advA = $this->createAdvance($this->warehouseA, $product, 50.0, '2026-08-01', 'kg');
        $advB = $this->createAdvance($this->warehouseA, $product, 40.0, '2026-08-02', 'kg');

        $this->createPendingBill($this->warehouseA, [
            ['product_id' => $product->id, 'quantity' => 70.0, 'purchase_unit' => 'kg'],
        ], '2026-08-03');

        $plan = app(AutoAdvanceClearPlanningService::class)->buildAutoClearPlan($this->warehouseA->id, $this->warehouseUser->id);
        $this->assertCount(1, $plan['ready_bills']);
        $matches = $plan['ready_bills'][0]['lines'][0]['matches'];
        $this->assertCount(2, $matches);
        $this->assertEquals($advA->id, $matches[0]['advance_goods_received_id']);
        $this->assertEquals(50.0, $matches[0]['matched_base_qty']);
        $this->assertEquals($advB->id, $matches[1]['advance_goods_received_id']);
        $this->assertEquals(20.0, $matches[1]['matched_base_qty']);
    }

    public function test_phase1db_6_same_product_insufficient_quantity_partial_match(): void
    {
        $product = Product::factory()->create(['name' => 'Partial Prod', 'unit' => 'kg']);
        $this->createAdvance($this->warehouseA, $product, 60.0, '2026-08-01', 'kg');
        $this->createPendingBill($this->warehouseA, [
            ['product_id' => $product->id, 'quantity' => 100.0, 'purchase_unit' => 'kg'],
        ], '2026-08-02');

        $plan = app(AutoAdvanceClearPlanningService::class)->buildAutoClearPlan($this->warehouseA->id, $this->warehouseUser->id);
        $this->assertCount(1, $plan['ready_bills']);
        $this->assertEquals('partial_match', $plan['ready_bills'][0]['match_type']);
        $this->assertEquals(60.0, $plan['summary']['matched_base_qty']);
    }

    public function test_phase1db_7_same_product_previous_advance_allocation_deducted(): void
    {
        $product = Product::factory()->create(['name' => 'Prev Alloc Prod', 'unit' => 'kg']);
        $adv = $this->createAdvance($this->warehouseA, $product, 100.0, '2026-08-01', 'kg');

        // Existing match of 40 kg
        \App\Models\AdvanceReceiveMatch::create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => 99999,
            'goods_received_id' => 99999,
            'advance_goods_received_id' => $adv->id,
            'advance_goods_received_item_id' => $adv->items->first()->id,
            'product_id' => $product->id,
            'base_qty' => 40.0,
            'matched_unit' => 'kg',
            'matched_qty' => 40.0,
            'created_by' => $this->warehouseUser->id,
        ]);

        $this->createPendingBill($this->warehouseA, [
            ['product_id' => $product->id, 'quantity' => 80.0, 'purchase_unit' => 'kg'],
        ], '2026-08-02');

        $plan = app(AutoAdvanceClearPlanningService::class)->buildAutoClearPlan($this->warehouseA->id, $this->warehouseUser->id);
        $this->assertCount(1, $plan['ready_bills']);
        $this->assertEquals('partial_match', $plan['ready_bills'][0]['match_type']);
        $this->assertEquals(60.0, $plan['summary']['matched_base_qty']);
    }

    public function test_phase1db_8_no_same_product_advance_yields_no_advance(): void
    {
        $product = Product::factory()->create(['name' => 'No Adv Prod', 'unit' => 'kg']);
        $this->createPendingBill($this->warehouseA, [
            ['product_id' => $product->id, 'quantity' => 10.0, 'purchase_unit' => 'kg'],
        ], '2026-08-02');

        $plan = app(AutoAdvanceClearPlanningService::class)->buildAutoClearPlan($this->warehouseA->id, $this->warehouseUser->id);
        $this->assertCount(0, $plan['ready_bills']);
        $this->assertCount(1, $plan['skipped_bills']);
        $this->assertEquals('NO_ADVANCE', $plan['skipped_bills'][0]['lines'][0]['classification']);
    }
}
