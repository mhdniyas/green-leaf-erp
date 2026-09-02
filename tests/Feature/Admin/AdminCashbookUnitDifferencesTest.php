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
}
