<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Purchasing\POStatus;
use App\Models\AdvanceReceiveMatch;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\AdvanceReceiveReconciliationService;
use App\Services\Purchasing\AutoAdvanceClearPlanningService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminCashbookUnitDifferencesTest extends TestCase
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

        $this->apple = Product::factory()->create([
            'name' => 'Apple Royal Gala',
            'sku' => 'APL-001',
            'unit' => 'piece',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouseA->id,
            'base_price' => 15.0,
            'is_active' => true,
        ]);

        $this->supplier = Supplier::factory()->create(['name' => 'Prime Farm Supplier']);
    }

    private function createStockBatch(GoodsReceived $grn, $item, Product $product, float $qty): StockBatch
    {
        return StockBatch::create([
            'warehouse_id' => $this->warehouseA->id,
            'product_id' => $product->id,
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'reference' => 'BATCH-TEST-'.Str::upper(Str::random(6)),
            'total_kg' => $qty,
            'available_kg' => $qty,
            'cost_per_kg' => 20.0,
            'received_at' => now(),
            'created_at' => now(),
            'created_by' => $this->adminUser->id,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->adminUser->id,
        ]);
    }

    public function test_clean_kg_kg_and_piece_piece_auto_matches_normally(): void
    {
        // 1. Advance GRN for Tomato (kg) and Apple (piece)
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->shop->id,
            'grn_number' => 'GRN-ADV-001',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->adminUser->id,
            'received_at' => now()->subDay(),
            'approved_at' => now()->subDay(),
            'approved_by' => $this->adminUser->id,
        ]);

        $advItem1 = $advGrn->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 100.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 20.0,
            'total_price' => 2000.0,
        ]);

        $this->createStockBatch($advGrn, $advItem1, $this->tomato, 100.0);

        $advItem2 = $advGrn->items()->create([
            'product_id' => $this->apple->id,
            'received_qty' => 50.0,
            'received_unit' => 'piece',
            'variance' => 0.0,
            'unit_price' => 15.0,
            'total_price' => 750.0,
        ]);

        $this->createStockBatch($advGrn, $advItem2, $this->apple, 50.0);

        // 2. PO with matching units: Tomato in kg, Apple in piece
        $po = PurchaseOrder::create([
            'po_number' => 'PO-CLEAN-001',
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->shop->id,
            'status' => 'approved',
            'order_date' => now()->toDateString(),
            'total_amount' => 3500.0,
            'created_by' => $this->adminUser->id,
        ]);

        $po->items()->create([
            'product_id' => $this->tomato->id,
            'quantity' => 50.0,
            'unit_price' => 20.0,
            'purchase_unit' => 'kg',
            'total_price' => 1000.0,
        ]);

        $po->items()->create([
            'product_id' => $this->apple->id,
            'quantity' => 25.0,
            'unit_price' => 15.0,
            'purchase_unit' => 'piece',
            'total_price' => 375.0,
        ]);

        $planService = app(AutoAdvanceClearPlanningService::class);
        $plan = $planService->buildAutoClearPlan($this->warehouseA->id, $this->adminUser->id);

        $this->assertEquals(1, $plan['summary']['ready_bills']);
        $this->assertEquals(0, $plan['summary']['skipped_bills']);
    }

    public function test_unsupported_unit_difference_is_skipped_by_auto_match_and_appears_in_unit_differences_tab(): void
    {
        // 1. Advance GRN for Tomato in KG
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->shop->id,
            'grn_number' => 'GRN-ADV-TOM-KG',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->adminUser->id,
            'received_at' => now()->subDay(),
            'approved_at' => now()->subDay(),
            'approved_by' => $this->adminUser->id,
        ]);

        $advItem = $advGrn->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 100.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 20.0,
            'total_price' => 2000.0,
        ]);

        $this->createStockBatch($advGrn, $advItem, $this->tomato, 100.0);

        // 2. PO with unconfigured unit: Tomato in 'box' (without configured conversion)
        $po = PurchaseOrder::create([
            'po_number' => 'PO-DIFF-001',
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->shop->id,
            'status' => 'approved',
            'order_date' => now()->toDateString(),
            'total_amount' => 1000.0,
            'created_by' => $this->adminUser->id,
        ]);

        $poItem = $po->items()->create([
            'product_id' => $this->tomato->id,
            'quantity' => 10.0,
            'unit_price' => 100.0,
            'purchase_unit' => 'box',
            'total_price' => 1000.0,
        ]);

        // 3. Auto clear should skip with reason unit_difference_requires_manual_review
        $planService = app(AutoAdvanceClearPlanningService::class);
        $plan = $planService->buildAutoClearPlan($this->warehouseA->id, $this->adminUser->id);

        $this->assertEquals(0, $plan['summary']['ready_bills']);
        $this->assertEquals(1, $plan['summary']['skipped_bills']);
        $this->assertEquals('unit_difference_requires_manual_review', $plan['skipped_bills'][0]['reason']);

        // 4. Check Web page Unit Differences Tab
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.cashbook.inventory', [
                'tab' => 'unit_differences',
                'warehouse_id' => $this->warehouseA->id,
            ]));

        $response->assertOk();
        $response->assertViewHas('summary', fn ($summary) => ($summary['unit_differences_count'] ?? 0) >= 1);
        $response->assertSee('PO-DIFF-001');
        $response->assertSee('Tomato Hybrid');
        $response->assertSee('box');
        $response->assertSee('kg');
        $response->assertSee('Resolve');
    }

    public function test_manual_resolution_can_match_partial_quantity_and_recalculates_remaining(): void
    {
        // 1. Advance GRN for Tomato in KG (50 KG)
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->shop->id,
            'grn_number' => 'GRN-ADV-PARTIAL',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->adminUser->id,
            'received_at' => now()->subDay(),
            'approved_at' => now()->subDay(),
            'approved_by' => $this->adminUser->id,
        ]);

        $advItem = $advGrn->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 20.0,
            'total_price' => 1000.0,
        ]);

        $this->createStockBatch($advGrn, $advItem, $this->tomato, 50.0);

        // 2. PO for 10 boxes (1 box = 5 kg)
        $po = PurchaseOrder::create([
            'po_number' => 'PO-DIFF-PARTIAL',
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->shop->id,
            'status' => 'approved',
            'order_date' => now()->toDateString(),
            'total_amount' => 1000.0,
            'created_by' => $this->adminUser->id,
        ]);

        $poItem = $po->items()->create([
            'product_id' => $this->tomato->id,
            'quantity' => 10.0,
            'unit_price' => 100.0,
            'purchase_unit' => 'box',
            'total_price' => 1000.0,
        ]);

        // 3. Resolve partial: 4 boxes with conversion 5.0 (4 * 5 = 20 kg)
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.resolve-unit-difference'), [
                'purchase_order_id' => $po->id,
                'purchase_order_item_id' => $poItem->id,
                'warehouse_id' => $this->warehouseA->id,
                'advance_goods_received_id' => $advGrn->id,
                'advance_goods_received_item_id' => $advItem->id,
                'matched_qty' => 4.0,
                'conversion_factor' => 5.0,
                'notes' => 'Partial manual match 4 boxes',
            ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'remaining_bill_qty' => 6.0,
                'po_fully_cleared' => false,
                'advance_fully_cleared' => false,
            ],
        ]);

        $this->assertDatabaseHas('advance_receive_matches', [
            'purchase_order_id' => $po->id,
            'purchase_order_item_id' => $poItem->id,
            'matched_qty' => 4.0,
            'matched_unit' => 'box',
            'base_qty' => 20.0,
            'conversion_to_base' => 5.0,
        ]);

        // PO remains pending
        $this->assertEquals(POStatus::Approved->value, $po->fresh()->status->value);

        // Advance GRN still has 30 KG available
        $this->assertEquals('bill_pending', $advGrn->fresh()->bill_status);
    }

    public function test_full_manual_resolution_clears_bill_and_closes_advance(): void
    {
        // 1. Advance GRN for Tomato 50 KG
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->shop->id,
            'grn_number' => 'GRN-ADV-FULL',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->adminUser->id,
            'received_at' => now()->subDay(),
            'approved_at' => now()->subDay(),
            'approved_by' => $this->adminUser->id,
        ]);

        $advItem = $advGrn->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 20.0,
            'total_price' => 1000.0,
        ]);

        $this->createStockBatch($advGrn, $advItem, $this->tomato, 50.0);

        // 2. PO for 10 boxes (10 boxes * 5 kg = 50 kg)
        $po = PurchaseOrder::create([
            'po_number' => 'PO-DIFF-FULL',
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->shop->id,
            'status' => 'approved',
            'order_date' => now()->toDateString(),
            'total_amount' => 1000.0,
            'created_by' => $this->adminUser->id,
        ]);

        $poItem = $po->items()->create([
            'product_id' => $this->tomato->id,
            'quantity' => 10.0,
            'unit_price' => 100.0,
            'purchase_unit' => 'box',
            'total_price' => 1000.0,
        ]);

        // 3. Resolve full 10 boxes
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.resolve-unit-difference'), [
                'purchase_order_id' => $po->id,
                'purchase_order_item_id' => $poItem->id,
                'warehouse_id' => $this->warehouseA->id,
                'advance_goods_received_id' => $advGrn->id,
                'advance_goods_received_item_id' => $advItem->id,
                'matched_qty' => 10.0,
                'conversion_factor' => 5.0,
                'notes' => 'Full match 10 boxes',
            ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'remaining_bill_qty' => 0.0,
                'po_fully_cleared' => true,
                'advance_fully_cleared' => true,
            ],
        ]);

        // PO updated to received
        $this->assertEquals(POStatus::Received->value, $po->fresh()->status->value);

        // Advance updated to bill_available (closed)
        $this->assertEquals('bill_available', $advGrn->fresh()->bill_status);

        // Check activity log
        $this->assertDatabaseHas('activity_log', [
            'description' => 'purchasing.unit_difference_resolved',
            'subject_id' => $po->id,
            'causer_id' => $this->adminUser->id,
        ]);
    }

    public function test_retry_with_client_submission_id_is_idempotent(): void
    {
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->shop->id,
            'grn_number' => 'GRN-ADV-IDEM',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->adminUser->id,
            'received_at' => now()->subDay(),
            'approved_at' => now()->subDay(),
            'approved_by' => $this->adminUser->id,
        ]);

        $advItem = $advGrn->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 20.0,
            'total_price' => 1000.0,
        ]);

        $this->createStockBatch($advGrn, $advItem, $this->tomato, 50.0);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-DIFF-IDEM',
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->shop->id,
            'status' => 'approved',
            'order_date' => now()->toDateString(),
            'total_amount' => 500.0,
            'created_by' => $this->adminUser->id,
        ]);

        $poItem = $po->items()->create([
            'product_id' => $this->tomato->id,
            'quantity' => 5.0,
            'unit_price' => 100.0,
            'purchase_unit' => 'box',
            'total_price' => 500.0,
        ]);

        $submissionId = (string) Str::uuid();

        // 1st submission
        $res1 = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.resolve-unit-difference'), [
                'purchase_order_id' => $po->id,
                'purchase_order_item_id' => $poItem->id,
                'warehouse_id' => $this->warehouseA->id,
                'advance_goods_received_id' => $advGrn->id,
                'advance_goods_received_item_id' => $advItem->id,
                'matched_qty' => 5.0,
                'conversion_factor' => 5.0,
                'client_submission_id' => $submissionId,
            ]);

        $res1->assertOk();

        // 2nd duplicate submission with same client_submission_id
        $res2 = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.resolve-unit-difference'), [
                'purchase_order_id' => $po->id,
                'purchase_order_item_id' => $poItem->id,
                'warehouse_id' => $this->warehouseA->id,
                'advance_goods_received_id' => $advGrn->id,
                'advance_goods_received_item_id' => $advItem->id,
                'matched_qty' => 5.0,
                'conversion_factor' => 5.0,
                'client_submission_id' => $submissionId,
            ]);

        $res2->assertOk();
        $this->assertEquals(1, AdvanceReceiveMatch::where('client_submission_id', $submissionId)->count());
    }

    public function test_unauthorized_users_cannot_resolve_unit_differences(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->postJson(route('admin.cashbook.inventory.resolve-unit-difference'), [
                'purchase_order_id' => 1,
                'purchase_order_item_id' => 1,
                'warehouse_id' => $this->warehouseA->id,
                'advance_goods_received_id' => 1,
                'matched_qty' => 5.0,
                'conversion_factor' => 1.0,
            ]);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_kg_vs_piece_line_appears_in_unit_differences(): void
    {
        // 1. Advance GRN in PIECE for Apple (Apple base unit is piece -> enters pool as 100 pieces)
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->shop->id,
            'grn_number' => 'GRN-ADV-KG-APL',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->adminUser->id,
            'received_at' => now()->subDay(),
            'approved_at' => now()->subDay(),
            'approved_by' => $this->adminUser->id,
        ]);

        $advItem = $advGrn->items()->create([
            'product_id' => $this->apple->id,
            'received_qty' => 100.0,
            'received_unit' => 'piece',
            'variance' => 0.0,
        ]);

        $this->createStockBatch($advGrn, $advItem, $this->apple, 100.0);

        // 2. Pending Bill (PO) in KG for Apple (no conversion from KG to PIECE in orderUnits)
        $po = PurchaseOrder::create([
            'po_number' => 'PO-APL-PC',
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->shop->id,
            'status' => 'approved',
            'order_date' => now()->toDateString(),
            'total_amount' => 500.0,
            'created_by' => $this->adminUser->id,
        ]);

        $poItem = $po->items()->create([
            'product_id' => $this->apple->id,
            'quantity' => 20.0,
            'unit_price' => 25.0,
            'purchase_unit' => 'kg',
            'total_price' => 500.0,
        ]);

        // Auto Match planning should exclude this due to unit difference
        $plan = app(AutoAdvanceClearPlanningService::class)->buildAutoClearPlan($this->warehouseA->id, $this->adminUser->id);

        $this->assertEquals(0, $plan['summary']['ready_bills']);
        $this->assertEquals(1, $plan['summary']['skipped_bills']);
        $this->assertEquals('unit_difference_requires_manual_review', $plan['skipped_bills'][0]['reason']);

        // Check unit differences endpoint / tab returns this line
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.cashbook.inventory', [
                'tab' => 'unit_differences',
                'warehouse_id' => $this->warehouseA->id,
            ]));

        $response->assertOk();
        $response->assertSee('PO-APL-PC');
        $response->assertSee('Apple Royal Gala');
    }

    public function test_manual_clear_step_by_step_partial_remainder_and_second_clear_completes(): void
    {
        // Advance GRN 100 KG
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->shop->id,
            'grn_number' => 'GRN-ADV-STEP',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->adminUser->id,
            'received_at' => now()->subDay(),
            'approved_at' => now()->subDay(),
            'approved_by' => $this->adminUser->id,
        ]);

        $advItem = $advGrn->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 100.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $this->createStockBatch($advGrn, $advItem, $this->tomato, 100.0);

        // PO for 20 boxes (1 box = 5 kg)
        $po = PurchaseOrder::create([
            'po_number' => 'PO-DIFF-STEP',
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->shop->id,
            'status' => 'approved',
            'order_date' => now()->toDateString(),
            'total_amount' => 2000.0,
            'created_by' => $this->adminUser->id,
        ]);

        $poItem = $po->items()->create([
            'product_id' => $this->tomato->id,
            'quantity' => 20.0,
            'unit_price' => 100.0,
            'purchase_unit' => 'box',
            'total_price' => 2000.0,
        ]);

        // Step 1: Clear 10 boxes (10 * 5 = 50 kg)
        $res1 = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.resolve-unit-difference'), [
                'purchase_order_id' => $po->id,
                'purchase_order_item_id' => $poItem->id,
                'warehouse_id' => $this->warehouseA->id,
                'advance_goods_received_id' => $advGrn->id,
                'advance_goods_received_item_id' => $advItem->id,
                'matched_qty' => 10.0,
                'conversion_factor' => 5.0,
                'notes' => 'Step 1 clear 10 boxes',
            ]);

        $res1->assertOk();
        $res1->assertJson([
            'status' => 'success',
            'data' => [
                'remaining_bill_qty' => 10.0,
                'po_fully_cleared' => false,
                'advance_fully_cleared' => false,
            ],
        ]);

        // Remainder stays visible in pending state
        $this->assertEquals(POStatus::Approved->value, $po->fresh()->status->value);
        $this->assertEquals('bill_pending', $advGrn->fresh()->bill_status);

        // Step 2: Second clear for remaining 10 boxes (10 * 5 = 50 kg)
        $res2 = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.resolve-unit-difference'), [
                'purchase_order_id' => $po->id,
                'purchase_order_item_id' => $poItem->id,
                'warehouse_id' => $this->warehouseA->id,
                'advance_goods_received_id' => $advGrn->id,
                'advance_goods_received_item_id' => $advItem->id,
                'matched_qty' => 10.0,
                'conversion_factor' => 5.0,
                'notes' => 'Step 2 clear remaining 10 boxes',
            ]);

        $res2->assertOk();
        $res2->assertJson([
            'status' => 'success',
            'data' => [
                'remaining_bill_qty' => 0.0,
                'po_fully_cleared' => true,
                'advance_fully_cleared' => true,
            ],
        ]);

        $this->assertEquals(POStatus::Received->value, $po->fresh()->status->value);
        $this->assertEquals('bill_available', $advGrn->fresh()->bill_status);
    }

    public function test_manual_clear_does_not_create_duplicate_physical_stock(): void
    {
        $initialBatchCount = StockBatch::count();

        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->shop->id,
            'grn_number' => 'GRN-ADV-NO-STOCK-DUP',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->adminUser->id,
            'received_at' => now()->subDay(),
            'approved_at' => now()->subDay(),
            'approved_by' => $this->adminUser->id,
        ]);

        $advItem = $advGrn->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $batch = $this->createStockBatch($advGrn, $advItem, $this->tomato, 50.0);
        $batchCountAfterAdvance = StockBatch::count();

        $po = PurchaseOrder::create([
            'po_number' => 'PO-NO-STOCK-DUP',
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->shop->id,
            'status' => 'approved',
            'order_date' => now()->toDateString(),
            'total_amount' => 1000.0,
            'created_by' => $this->adminUser->id,
        ]);

        $poItem = $po->items()->create([
            'product_id' => $this->tomato->id,
            'quantity' => 10.0,
            'unit_price' => 100.0,
            'purchase_unit' => 'box',
            'total_price' => 1000.0,
        ]);

        $res = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.resolve-unit-difference'), [
                'purchase_order_id' => $po->id,
                'purchase_order_item_id' => $poItem->id,
                'warehouse_id' => $this->warehouseA->id,
                'advance_goods_received_id' => $advGrn->id,
                'advance_goods_received_item_id' => $advItem->id,
                'matched_qty' => 10.0,
                'conversion_factor' => 5.0,
            ]);

        $res->assertOk();

        // NO new stock batches should have been created during manual unit clear
        $this->assertEquals($batchCountAfterAdvance, StockBatch::count());
    }

    public function test_validation_rejects_wrong_product_wrong_warehouse_over_clear_and_stale_advance(): void
    {
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->shop->id,
            'grn_number' => 'GRN-ADV-VAL-TEST',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->adminUser->id,
            'received_at' => now()->subDay(),
            'approved_at' => now()->subDay(),
            'approved_by' => $this->adminUser->id,
        ]);

        $advItem = $advGrn->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 20.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $this->createStockBatch($advGrn, $advItem, $this->tomato, 20.0);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-VAL-TEST',
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->shop->id,
            'status' => 'approved',
            'order_date' => now()->toDateString(),
            'total_amount' => 500.0,
            'created_by' => $this->adminUser->id,
        ]);

        $poItem = $po->items()->create([
            'product_id' => $this->apple->id, // Apple on PO, Tomato on Advance
            'quantity' => 10.0,
            'unit_price' => 50.0,
            'purchase_unit' => 'piece',
            'total_price' => 500.0,
        ]);

        // 1. Wrong product (Apple vs Tomato) -> 422
        $resProduct = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.resolve-unit-difference'), [
                'purchase_order_id' => $po->id,
                'purchase_order_item_id' => $poItem->id,
                'warehouse_id' => $this->warehouseA->id,
                'advance_goods_received_id' => $advGrn->id,
                'advance_goods_received_item_id' => $advItem->id,
                'matched_qty' => 5.0,
                'conversion_factor' => 1.0,
            ]);
        $resProduct->assertStatus(422);

        // Update PO item to Tomato for remaining tests
        $poItem->update(['product_id' => $this->tomato->id, 'purchase_unit' => 'kg']);

        // 2. Unauthorized warehouse -> 403
        $resWarehouse = $this->actingAs($this->unauthorizedUser)
            ->postJson(route('admin.cashbook.inventory.resolve-unit-difference'), [
                'purchase_order_id' => $po->id,
                'purchase_order_item_id' => $poItem->id,
                'warehouse_id' => $this->warehouseB->id,
                'advance_goods_received_id' => $advGrn->id,
                'advance_goods_received_item_id' => $advItem->id,
                'matched_qty' => 5.0,
                'conversion_factor' => 1.0,
            ]);
        $resWarehouse->assertStatus(403);

        // 3. Over-clear bill (requested 50 kg when bill is 10 kg) -> 422
        $resOverBill = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.resolve-unit-difference'), [
                'purchase_order_id' => $po->id,
                'purchase_order_item_id' => $poItem->id,
                'warehouse_id' => $this->warehouseA->id,
                'advance_goods_received_id' => $advGrn->id,
                'advance_goods_received_item_id' => $advItem->id,
                'matched_qty' => 50.0,
                'conversion_factor' => 1.0,
            ]);
        $resOverBill->assertStatus(422);

        // 4. Stale advance balance (advance only has 20 kg available, try matching 30 kg base) -> 422
        $poItem->update(['quantity' => 100.0]); // enlarge bill to test advance balance limit
        $resStaleAdv = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.resolve-unit-difference'), [
                'purchase_order_id' => $po->id,
                'purchase_order_item_id' => $poItem->id,
                'warehouse_id' => $this->warehouseA->id,
                'advance_goods_received_id' => $advGrn->id,
                'advance_goods_received_item_id' => $advItem->id,
                'matched_qty' => 30.0,
                'conversion_factor' => 1.0,
            ]);
        $resStaleAdv->assertStatus(422);
    }

    public function test_fix_advance_units_updates_advance_unit_to_product_default_unit_without_changing_qty(): void
    {
        // Product Gala with base unit 'box'
        $gala = Product::factory()->create([
            'name' => 'Gala Apple',
            'sku' => 'GAL-001',
            'unit' => 'box',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouseA->id,
            'base_price' => 50.0,
            'is_active' => true,
        ]);

        // Product Cauliflower with base unit 'piece'
        $cauliflower = Product::factory()->create([
            'name' => 'Cauliflower Fresh',
            'sku' => 'CAU-001',
            'unit' => 'piece',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouseA->id,
            'base_price' => 30.0,
            'is_active' => true,
        ]);

        // 1. Advance GRN with incorrect unit 'kg' for Gala and Cauliflower
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->shop->id,
            'grn_number' => 'GRN-ADV-FIX-001',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->adminUser->id,
            'received_at' => now()->toDateString(),
            'approved_at' => now(),
            'approved_by' => $this->adminUser->id,
        ]);

        $advItemGala = $advGrn->items()->create([
            'product_id' => $gala->id,
            'received_qty' => 0.75,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 50.0,
            'total_price' => 37.50,
        ]);
        $this->createStockBatch($advGrn, $advItemGala, $gala, 0.75);

        $advItemCauli = $advGrn->items()->create([
            'product_id' => $cauliflower->id,
            'received_qty' => 10.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'unit_price' => 30.0,
            'total_price' => 300.0,
        ]);
        $this->createStockBatch($advGrn, $advItemCauli, $cauliflower, 10.0);

        // 2. Open Purchase Orders with matching product base units (box, piece)
        $poGala = PurchaseOrder::create([
            'po_number' => 'PO-GALA-001',
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->warehouseA->id,
            'status' => 'approved',
            'order_date' => now()->toDateString(),
            'total_amount' => 50.0,
            'created_by' => $this->adminUser->id,
        ]);
        $poGala->items()->create([
            'product_id' => $gala->id,
            'quantity' => 0.75,
            'unit_price' => 50.0,
            'purchase_unit' => 'box',
            'total_price' => 37.50,
        ]);

        $poCauli = PurchaseOrder::create([
            'po_number' => 'PO-CAULI-001',
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->warehouseA->id,
            'status' => 'approved',
            'order_date' => now()->toDateString(),
            'total_amount' => 300.0,
            'created_by' => $this->adminUser->id,
        ]);
        $poCauli->items()->create([
            'product_id' => $cauliflower->id,
            'quantity' => 10.0,
            'unit_price' => 30.0,
            'purchase_unit' => 'piece',
            'total_price' => 300.0,
        ]);

        // Verify Unit Differences tab shows unit mismatch before fix
        $diffBefore = app(AdvanceReceiveReconciliationService::class)->paginateUnitDifferences([
            'warehouse_id' => $this->warehouseA->id,
        ]);
        $this->assertGreaterThanOrEqual(2, $diffBefore->total());

        // 3. Call fixAdvanceUnits endpoint
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.fix-advance-units'), [
                'date' => now()->toDateString(),
                'warehouse_id' => $this->warehouseA->id,
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'fixed_count' => 2,
                    'already_correct_count' => 0,
                    'skipped_count' => 0,
                ],
            ]);

        // 4. Verify Advance item units updated to product default unit, but quantities remained unchanged!
        $advItemGala->refresh();
        $this->assertEquals('box', $advItemGala->received_unit);
        $this->assertEquals(0.75, (float) $advItemGala->received_qty);

        $advItemCauli->refresh();
        $this->assertEquals('piece', $advItemCauli->received_unit);
        $this->assertEquals(10.0, (float) $advItemCauli->received_qty);

        // 5. Verify Activity logs recorded
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => get_class($advItemGala),
            'subject_id' => $advItemGala->id,
            'causer_id' => $this->adminUser->id,
        ]);

        // 6. Verify Unit Differences tab after fix no longer flags unit mismatch for resolved rows
        $diffAfter = app(AdvanceReceiveReconciliationService::class)->paginateUnitDifferences([
            'warehouse_id' => $this->warehouseA->id,
        ]);
        $poIds = collect($diffAfter->items())->pluck('purchase_order_id')->all();
        $this->assertNotContains($poGala->id, $poIds);
        $this->assertNotContains($poCauli->id, $poIds);
    }

    public function test_fix_advance_units_handles_already_correct_and_unauthorized_users(): void
    {
        // 1. Unauthorized user attempt -> 403
        $resUnauthorized = $this->actingAs($this->unauthorizedUser)
            ->postJson(route('admin.cashbook.inventory.fix-advance-units'), [
                'date' => now()->toDateString(),
                'warehouse_id' => $this->warehouseA->id,
            ]);
        $resUnauthorized->assertStatus(403);

        // 2. Advance GRN already having correct base unit ('kg' for Tomato)
        $advGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouseA->id,
            'destination_shop_id' => $this->shop->id,
            'grn_number' => 'GRN-ADV-CORRECT-001',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->adminUser->id,
            'received_at' => now()->toDateString(),
        ]);
        $advItem = $advGrn->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 15.0,
            'received_unit' => 'kg', // Already matches tomato's base unit ('kg')
            'variance' => 0.0,
        ]);
        $this->createStockBatch($advGrn, $advItem, $this->tomato, 15.0);

        // Create PO in 'piece' to force a unit difference row
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TOM-PIECE-001',
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->warehouseA->id,
            'status' => 'approved',
            'order_date' => now()->toDateString(),
            'total_amount' => 100.0,
            'created_by' => $this->adminUser->id,
        ]);
        $po->items()->create([
            'product_id' => $this->tomato->id,
            'quantity' => 15.0,
            'unit_price' => 10.0,
            'purchase_unit' => 'piece',
            'total_price' => 150.0,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.cashbook.inventory.fix-advance-units'), [
                'date' => now()->toDateString(),
                'warehouse_id' => $this->warehouseA->id,
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'fixed_count' => 0,
                    'already_correct_count' => 1,
                    'skipped_count' => 0,
                ],
            ]);
    }
}
