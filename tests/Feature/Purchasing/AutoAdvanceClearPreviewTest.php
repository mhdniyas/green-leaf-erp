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

    public function test_advance_60_bill_100_is_skipped_as_insufficient_advance(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->createAdvance($this->warehouseA, $this->apple, 60.0, '2026-08-25');
        $bill = $this->createPendingBill($this->warehouseA, [['product_id' => $this->apple->id, 'quantity' => 100.0]], '2026-08-28');

        $res = $this->getJson("/api/v1/purchasing/grns/auto-clear-preview?warehouse_id={$this->warehouseA->id}");
        $res->assertOk();

        $data = $res->json('data');
        $this->assertEquals(0, $data['summary']['ready_bills']);
        $this->assertEquals(1, $data['summary']['skipped_bills']);
        $this->assertEquals(0.0, $data['summary']['matched_base_qty']);

        $this->assertEquals($bill->id, $data['skipped_bills'][0]['purchase_order_id']);
        $this->assertEquals('insufficient_advance', $data['skipped_bills'][0]['reason']);
        $this->assertEquals(100.0, $data['skipped_bills'][0]['shortages'][0]['required_base_qty']);
        $this->assertEquals(60.0, $data['skipped_bills'][0]['shortages'][0]['available_base_qty']);
        $this->assertEquals(40.0, $data['skipped_bills'][0]['shortages'][0]['shortage_base_qty']);

        $this->assertEquals(60.0, $data['advance_allocations'][0]['remaining_base_qty']);
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

        $this->assertLessThanOrEqual(15, $queryCount, "Query count {$queryCount} exceeded bounded threshold");
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
}
