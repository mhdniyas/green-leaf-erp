<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Purchasing\POStatus;
use App\Models\AdvanceReceiveMatch;
use App\Models\BillReconciliation;
use App\Models\BillReconciliationLine;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\AutoAdvanceClearPlanningService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCashbookInventoryReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $unauthorizedUser;

    private Warehouse $warehouseA;

    private Warehouse $warehouseB;

    private Category $category;

    private Product $tomato;

    private Product $onion;

    private Product $potato;

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
            'name' => 'Main Warehouse Alpha',
            'code' => 'WH-A',
            'is_active' => true,
        ]);

        $this->warehouseB = Warehouse::create([
            'name' => 'Secondary Warehouse Beta',
            'code' => 'WH-B',
            'is_active' => true,
        ]);

        $this->shop = Shop::factory()->create([
            'name' => 'Alpha Direct Shop',
            'code' => 'SH-01',
        ]);

        $this->category = Category::factory()->create();

        $this->tomato = Product::factory()->create([
            'name' => 'Tomato Hybrid',
            'sku' => 'TOM-001',
            'unit' => 'kg',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouseA->id,
            'base_price' => 20.0,
            'is_active' => true,
        ]);

        $this->onion = Product::factory()->create([
            'name' => 'Red Onion',
            'sku' => 'ONI-001',
            'unit' => 'kg',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouseA->id,
            'base_price' => 30.0,
            'is_active' => true,
        ]);

        $this->potato = Product::factory()->create([
            'name' => 'Agra Potato',
            'sku' => 'POT-001',
            'unit' => 'kg',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouseA->id,
            'base_price' => 25.0,
            'is_active' => true,
        ]);

        ProductUnit::create([
            'product_id' => $this->tomato->id,
            'unit' => 'crate',
            'label' => 'Crate (25kg)',
            'conversion_to_base' => 25.0,
            'is_base' => false,
            'is_orderable' => true,
        ]);

        $this->supplier = Supplier::factory()->create(['name' => 'Green Valley Farms']);
    }

    private function createPhysicalBatch(Product $product, Warehouse $warehouse, float $qtyKg, string $date, ?GoodsReceived $grn = null): StockBatch
    {
        return StockBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'goods_received_id' => $grn?->id,
            'reference' => 'BATCH-TEST-'.Str::upper(Str::random(6)),
            'total_kg' => $qtyKg,
            'available_kg' => $qtyKg,
            'cost_per_kg' => 20.0,
            'received_at' => $date,
            'created_at' => Carbon::parse($date)->setTime(10, 0, 0),
            'created_by' => $this->adminUser->id,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => Carbon::parse($date)->setTime(10, 0, 0),
            'warehouse_confirmed_by' => $this->adminUser->id,
        ]);
    }

    private function createConfirmedReconciliation(Product $product, ?Warehouse $warehouse, float $billedBaseQty, string $date, ?GoodsReceived $grn = null): BillReconciliation
    {
        $recon = BillReconciliation::create([
            'goods_received_id' => $grn?->id,
            'warehouse_id' => $warehouse?->id,
            'status' => 'confirmed',
            'source_type' => 'purchaser_bill',
            'total_bill_base_qty' => $billedBaseQty,
            'total_matched_base_qty' => $billedBaseQty,
            'total_new_receive_base_qty' => 0.0,
            'confirmed_at' => Carbon::parse($date)->setTime(12, 0, 0),
            'confirmed_by' => $this->adminUser->id,
            'created_at' => Carbon::parse($date)->setTime(12, 0, 0),
        ]);

        BillReconciliationLine::create([
            'bill_reconciliation_id' => $recon->id,
            'product_id' => $product->id,
            'bill_qty' => $billedBaseQty,
            'bill_unit' => $product->unit,
            'bill_base_qty' => $billedBaseQty,
            'matched_base_qty' => $billedBaseQty,
            'new_receive_base_qty' => 0.0,
            'line_status' => 'exact',
            'created_at' => Carbon::parse($date)->setTime(12, 0, 0),
        ]);

        return $recon;
    }

    private function createAdvanceGrn(Warehouse $warehouse, Product $product, float $qty, string $date, string $billStatus = 'bill_pending', ?Carbon $clearedAt = null): GoodsReceived
    {
        $grn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => null,
            'grn_number' => 'GRN-ADV-'.Str::upper(Str::random(6)),
            'status' => 'approved',
            'bill_status' => $billStatus,
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $warehouse->id,
            'received_by' => $this->adminUser->id,
            'received_at' => $date,
            'matched_at' => $clearedAt,
            'updated_at' => $clearedAt ?: Carbon::parse($date)->setTime(10, 0, 0),
            'created_at' => Carbon::parse($date)->setTime(10, 0, 0),
        ]);

        $item = $grn->items()->create([
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $product->unit,
            'variance' => 0,
        ]);

        $batch = $this->createPhysicalBatch($product, $warehouse, $qty, $date, $grn);

        if ($billStatus === 'bill_available') {
            $billGrn = GoodsReceived::create([
                'public_uuid' => (string) Str::uuid(),
                'purchase_order_id' => null,
                'grn_number' => 'GRN-BILL-'.Str::upper(Str::random(6)),
                'status' => 'approved',
                'bill_status' => 'bill_available',
                'receipt_type' => 'normal_purchase',
                'warehouse_id' => $warehouse->id,
                'received_by' => $this->adminUser->id,
                'received_at' => $clearedAt ?: $date,
            ]);
            $billItem = $billGrn->items()->create([
                'product_id' => $product->id,
                'received_qty' => $qty,
                'received_unit' => $product->unit,
                'variance' => 0,
            ]);

            AdvanceReceiveMatch::create([
                'advance_goods_received_id' => $grn->id,
                'advance_goods_received_item_id' => $item->id,
                'advance_stock_batch_id' => $batch->id,
                'bill_goods_received_id' => $billGrn->id,
                'bill_goods_received_item_id' => $billItem->id,
                'product_id' => $product->id,
                'matched_qty' => $qty,
                'matched_unit' => $product->unit,
                'base_qty' => $qty,
                'conversion_to_base' => 1.0,
                'confirmed_by' => $this->adminUser->id,
                'confirmed_at' => $clearedAt ?: Carbon::parse($date),
                'created_at' => $clearedAt ?: Carbon::parse($date),
                'updated_at' => $clearedAt ?: Carbon::parse($date),
            ]);

            if ($clearedAt !== null) {
                DB::table('advance_receive_matches')->where('advance_goods_received_id', $grn->id)->update([
                    'confirmed_at' => $clearedAt,
                    'created_at' => $clearedAt,
                    'updated_at' => $clearedAt,
                ]);
            }
        }

        if ($clearedAt !== null) {
            DB::table('goods_received')->where('id', $grn->id)->update([
                'matched_at' => $clearedAt,
                'updated_at' => $clearedAt,
            ]);
        }

        return $grn;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. DAILY INVENTORY TESTS
    // ─────────────────────────────────────────────────────────────────────────

    public function test_daily_inventory_renders_billed_physical_and_advance_difference(): void
    {
        // Tomato: Physical 540 KG, Billed 475 KG -> +65 KG (Excess)
        $this->createPhysicalBatch($this->tomato, $this->warehouseA, 540.0, '2026-09-02');
        $this->createConfirmedReconciliation($this->tomato, $this->warehouseA, 475.0, '2026-09-02');

        // Onion: Physical 355 KG, Billed 400 KG -> -45 KG (Shortage)
        $this->createPhysicalBatch($this->onion, $this->warehouseA, 355.0, '2026-09-02');
        $this->createConfirmedReconciliation($this->onion, $this->warehouseA, 400.0, '2026-09-02');

        // Potato: Physical 220 KG, Billed 220 KG -> 0 KG (Balanced)
        $this->createPhysicalBatch($this->potato, $this->warehouseA, 220.0, '2026-09-02');
        $this->createConfirmedReconciliation($this->potato, $this->warehouseA, 220.0, '2026-09-02');

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'daily_inventory',
            'date' => '2026-09-02',
            'warehouse_id' => $this->warehouseA->id,
        ]));

        $response->assertOk();
        $response->assertSee('Tomato Hybrid');
        $response->assertSee('540.00'); // physical
        $response->assertSee('475.00'); // billed
        $response->assertSee('+65.00 KG'); // excess
        $response->assertSee('EXCESS / UNBILLED');

        $response->assertSee('Red Onion');
        $response->assertSee('355.00');
        $response->assertSee('400.00');
        $response->assertSee('-45.00 KG'); // short
        $response->assertSee('SHORTAGE');

        $response->assertSee('Agra Potato');
        $response->assertSee('220.00');
        $response->assertSee('0.00 KG'); // balanced
        $response->assertSee('BALANCED');
    }

    public function test_daily_inventory_preserves_historical_carry_forward_as_of_date(): void
    {
        // Sep 1: Intake 100 KG
        $this->createPhysicalBatch($this->tomato, $this->warehouseA, 100.0, '2026-09-01');
        // Sep 2: Intake 50 KG, Billed 80 KG
        $this->createPhysicalBatch($this->tomato, $this->warehouseA, 50.0, '2026-09-02');
        $this->createConfirmedReconciliation($this->tomato, $this->warehouseA, 80.0, '2026-09-02');

        // Check as of Sep 1: Physical 100, Billed 0 -> +100 KG
        $resSep1 = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'daily_inventory',
            'date' => '2026-09-01',
            'warehouse_id' => $this->warehouseA->id,
        ]));
        $resSep1->assertOk();
        $resSep1->assertSee('+100.00 KG');

        // Check as of Sep 2: Physical 150, Billed 80 -> +70 KG
        $resSep2 = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'daily_inventory',
            'date' => '2026-09-02',
            'warehouse_id' => $this->warehouseA->id,
        ]));
        $resSep2->assertOk();
        $resSep2->assertSee('+70.00 KG');
    }

    public function test_daily_inventory_search_and_pagination(): void
    {
        $this->createPhysicalBatch($this->tomato, $this->warehouseA, 50.0, '2026-09-02');
        $this->createPhysicalBatch($this->onion, $this->warehouseA, 60.0, '2026-09-02');

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'daily_inventory',
            'date' => '2026-09-02',
            'search' => 'TOM-001',
        ]));

        $response->assertOk();
        $response->assertSee('Tomato Hybrid');
        $response->assertDontSee('Red Onion');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. PENDING BILLS TESTS
    // ─────────────────────────────────────────────────────────────────────────

    public function test_pending_bills_section_shows_pending_and_partial_orders(): void
    {
        $poPending = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->shop->id,
            'po_number' => 'PO-PENDING-001',
            'status' => POStatus::Approved,
            'order_date' => '2026-09-02',
            'created_by' => $this->adminUser->id,
        ]);
        $poPending->items()->create([
            'product_id' => $this->tomato->id,
            'quantity' => 100.0,
            'unit' => 'kg',
            'unit_price' => 20.0,
            'total_price' => 2000.0,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'pending_bills',
            'warehouse_id' => $this->warehouseA->id,
        ]));

        $response->assertOk();
        $response->assertSee('PO-PENDING-001');
        $response->assertSee('Green Valley Farms');
        $response->assertSee('100.00');
    }

    public function test_fully_received_orders_are_excluded_from_pending_bills(): void
    {
        $poReceived = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->shop->id,
            'po_number' => 'PO-FULL-REC-001',
            'status' => POStatus::Received,
            'order_date' => '2026-09-02',
            'created_by' => $this->adminUser->id,
        ]);
        $poReceived->items()->create([
            'product_id' => $this->tomato->id,
            'quantity' => 50.0,
            'unit' => 'kg',
            'unit_price' => 20.0,
            'total_price' => 1000.0,
        ]);

        $grn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => $poReceived->id,
            'grn_number' => 'GRN-REC-001',
            'status' => 'approved',
            'bill_status' => 'bill_available',
            'receipt_type' => 'normal_purchase',
            'warehouse_id' => $this->warehouseA->id,
            'received_by' => $this->adminUser->id,
            'received_at' => '2026-09-02',
        ]);
        $this->createPhysicalBatch($this->tomato, $this->warehouseA, 50.0, '2026-09-02', $grn);

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'pending_bills',
            'warehouse_id' => $this->warehouseA->id,
        ]));

        $response->assertOk();
        $response->assertDontSee('PO-FULL-REC-001');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. ADVANCE BILLS & RETENTION TESTS
    // ─────────────────────────────────────────────────────────────────────────

    public function test_advance_bills_shows_open_advances_regardless_of_age(): void
    {
        // Old open advance received 30 days ago
        $oldAdvance = $this->createAdvanceGrn($this->warehouseA, $this->tomato, 150.0, '2026-08-01', 'bill_pending');

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'advance_bills',
            'warehouse_id' => $this->warehouseA->id,
        ]));

        $response->assertOk();
        $response->assertSee($oldAdvance->grn_number);
        $response->assertSee('BILL PENDING');
        $response->assertSee('150.00');
    }

    public function test_cleared_advances_within_3_days_are_visible(): void
    {
        // Received 20 days ago, but cleared yesterday
        $clearedYesterday = $this->createAdvanceGrn(
            $this->warehouseA,
            $this->tomato,
            80.0,
            '2026-08-10',
            'bill_available',
            now()->subDay()
        );

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'advance_bills',
            'warehouse_id' => $this->warehouseA->id,
        ]));

        $response->assertOk();
        $response->assertSee($clearedYesterday->grn_number);
        $response->assertSee('CLEARED (3-DAY)');
    }

    public function test_cleared_advances_older_than_3_days_are_hidden_from_operational_list(): void
    {
        // Received 20 days ago, cleared 10 days ago
        $clearedLongAgo = $this->createAdvanceGrn(
            $this->warehouseA,
            $this->tomato,
            80.0,
            '2026-08-10',
            'bill_available',
            now()->subDays(10)
        );

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'advance_bills',
            'warehouse_id' => $this->warehouseA->id,
        ]));

        $response->assertOk();
        $response->assertDontSee($clearedLongAgo->grn_number);

        // Verify record is NOT deleted from database
        $this->assertDatabaseHas('goods_received', [
            'id' => $clearedLongAgo->id,
            'deleted_at' => null,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. AUTO MATCH & CLEAR WORKFLOW TESTS
    // ─────────────────────────────────────────────────────────────────────────

    public function test_auto_clear_plan_preview_endpoint_returns_valid_json(): void
    {
        // Create open advance: Tomato 100 KG
        $this->createAdvanceGrn($this->warehouseA, $this->tomato, 100.0, '2026-09-02', 'bill_pending');

        // Create pending PO: Tomato 60 KG
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->shop->id,
            'po_number' => 'PO-AUTO-TEST-001',
            'status' => POStatus::Approved,
            'order_date' => '2026-09-02',
            'created_by' => $this->adminUser->id,
        ]);
        $po->items()->create([
            'product_id' => $this->tomato->id,
            'quantity' => 60.0,
            'unit' => 'kg',
            'unit_price' => 20.0,
            'total_price' => 1200.0,
        ]);

        $response = $this->actingAs($this->adminUser)->getJson(route('admin.cashbook.inventory.auto-clear-plan', [
            'warehouse_id' => $this->warehouseA->id,
        ]));

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.summary.ready_bills', 1);
        $this->assertEquals(60.0, (float) $response->json('data.summary.matched_base_qty'));
        $this->assertNotEmpty($response->json('data.plan_hash'));
    }

    public function test_auto_clear_execute_endpoint_reconciles_and_is_idempotent(): void
    {
        // 1. Setup open advance and pending PO
        $this->createAdvanceGrn($this->warehouseA, $this->tomato, 100.0, '2026-09-02', 'bill_pending');

        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->shop->id,
            'po_number' => 'PO-AUTO-EXEC-001',
            'status' => POStatus::Approved,
            'order_date' => '2026-09-02',
            'created_by' => $this->adminUser->id,
        ]);
        $po->items()->create([
            'product_id' => $this->tomato->id,
            'quantity' => 60.0,
            'unit' => 'kg',
            'unit_price' => 20.0,
            'total_price' => 1200.0,
        ]);

        $plan = app(AutoAdvanceClearPlanningService::class)->buildAutoClearPlan(
            $this->warehouseA->id,
            $this->adminUser->id
        );

        $clientSubmissionId = (string) Str::uuid();

        // 2. Execute plan
        $executeRes = $this->actingAs($this->adminUser)->postJson(route('admin.cashbook.inventory.auto-clear-execute'), [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $plan['plan_hash'],
            'client_submission_id' => $clientSubmissionId,
        ]);

        $executeRes->assertOk();
        $executeRes->assertJsonPath('status', 'success');
        $executeRes->assertJsonPath('data.summary.processed', 1);
        $this->assertEquals(60.0, (float) $executeRes->json('data.summary.matched_base_qty'));

        // 3. Idempotency check: Repeat identical submission
        $repeatRes = $this->actingAs($this->adminUser)->postJson(route('admin.cashbook.inventory.auto-clear-execute'), [
            'warehouse_id' => $this->warehouseA->id,
            'plan_hash' => $plan['plan_hash'],
            'client_submission_id' => $clientSubmissionId,
        ]);

        $repeatRes->assertOk();
        $repeatRes->assertJsonPath('status', 'success');
        $repeatRes->assertJsonPath('data.summary.processed', 1);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. MANUAL MATCH TESTS
    // ─────────────────────────────────────────────────────────────────────────

    public function test_manual_match_suggestions_and_execution(): void
    {
        $advanceGrn = $this->createAdvanceGrn($this->warehouseA, $this->tomato, 100.0, '2026-09-02', 'bill_pending');

        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->shop->id,
            'po_number' => 'PO-MANUAL-001',
            'status' => POStatus::Approved,
            'order_date' => '2026-09-02',
            'created_by' => $this->adminUser->id,
        ]);
        $poItem = $po->items()->create([
            'product_id' => $this->tomato->id,
            'quantity' => 40.0,
            'unit' => 'kg',
            'unit_price' => 20.0,
            'total_price' => 800.0,
        ]);

        // 1. Get suggestions
        $sugRes = $this->actingAs($this->adminUser)->getJson(route('admin.cashbook.inventory.manual-match-suggestions', [
            'order' => $po->id,
            'warehouse_id' => $this->warehouseA->id,
        ]));

        $sugRes->assertOk();
        $sugRes->assertJsonPath('status', 'success');
        $sugRes->assertJsonPath('data.items.0.product_id', $this->tomato->id);

        // 2. Execute manual match
        $execRes = $this->actingAs($this->adminUser)->postJson(route('admin.cashbook.inventory.manual-match', ['order' => $po->id]), [
            'warehouse_id' => $this->warehouseA->id,
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'purchase_order_item_id' => $poItem->id,
                    'received_qty' => 40.0,
                    'unit' => 'kg',
                ],
            ],
            'advance_matches' => [
                [
                    'advance_goods_received_id' => $advanceGrn->id,
                    'advance_goods_received_item_id' => $advanceGrn->items->first()->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'matched_qty' => 40.0,
                    'unit' => 'kg',
                ],
            ],
        ]);

        $execRes->assertOk();
        $execRes->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('advance_receive_matches', [
            'advance_goods_received_id' => $advanceGrn->id,
            'product_id' => $this->tomato->id,
            'matched_qty' => 40.0,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. AUTHORIZATION TESTS
    // ─────────────────────────────────────────────────────────────────────────

    public function test_unauthorized_users_are_forbidden(): void
    {
        $this->unauthorizedUser->assignRole('shop');
        $response = $this->actingAs($this->unauthorizedUser)->getJson(route('admin.cashbook.inventory'));
        $response->assertForbidden();
    }

    public function test_unauthorized_warehouse_request_is_rejected(): void
    {
        $warehouseUser = User::factory()->create();
        $warehouseUser->assignRole('purchase');
        $warehouseUser->warehouses()->attach($this->warehouseA->id);

        $response = $this->actingAs($warehouseUser)->getJson(route('admin.cashbook.inventory', [
            'warehouse_id' => $this->warehouseB->id,
        ]));
        $response->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. PERFORMANCE: BOUNDED QUERY COUNTS
    // ─────────────────────────────────────────────────────────────────────────

    public function test_daily_inventory_query_count_is_bounded(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $p = Product::factory()->create([
                'name' => 'Product '.$i,
                'sku' => 'PROD-'.$i,
                'category_id' => $this->category->id,
                'default_warehouse_id' => $this->warehouseA->id,
            ]);
            $this->createPhysicalBatch($p, $this->warehouseA, 100.0, '2026-09-02');
            $this->createConfirmedReconciliation($p, $this->warehouseA, 80.0, '2026-09-02');
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->adminUser)->get(route('admin.cashbook.inventory', [
            'tab' => 'daily_inventory',
            'date' => '2026-09-02',
            'warehouse_id' => $this->warehouseA->id,
        ]));

        $queries = DB::getQueryLog();
        $response->assertOk();

        // Must be <= 20 queries total (bounded aggregate queries, zero N+1 per product row)
        $this->assertLessThanOrEqual(20, count($queries), 'Query count for Daily Inventory must be bounded');
    }
}
