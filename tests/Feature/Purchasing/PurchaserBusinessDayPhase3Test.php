<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseBusinessDay;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\DailyInventoryComparisonService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaserBusinessDayPhase3Test extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $purchaserUser;

    private Warehouse $vegWarehouse;

    private Supplier $supplierA;

    private Supplier $supplierB;

    private Product $tomato;

    private Product $cucumber;

    private Product $capsicum;

    private PurchaserBusinessDayService $businessDayService;

    private DailyInventoryComparisonService $comparisonService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->purchaserUser = User::factory()->create();
        $this->purchaserUser->assignRole('purchase');
        $this->purchaserUser->assignRole('purchaser');

        $this->vegWarehouse = Warehouse::create([
            'name' => 'Vegetable Warehouse',
            'code' => 'WH-VEG-01',
            'is_active' => true,
        ]);

        $this->supplierA = Supplier::create([
            'name' => 'Vendor A',
            'type' => 'farmer',
            'status' => 'active',
        ]);

        $this->supplierB = Supplier::create([
            'name' => 'Vendor B',
            'type' => 'trader',
            'status' => 'active',
        ]);

        $category = Category::create(['name' => 'Vegetables', 'is_active' => true]);

        $this->tomato = Product::create([
            'category_id' => $category->id,
            'name' => 'Tomato',
            'sku' => 'TOM-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->vegWarehouse->id,
            'is_active' => true,
        ]);

        $this->cucumber = Product::create([
            'category_id' => $category->id,
            'name' => 'Cucumber',
            'sku' => 'CUC-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->vegWarehouse->id,
            'is_active' => true,
        ]);

        $this->capsicum = Product::create([
            'category_id' => $category->id,
            'name' => 'Capsicum',
            'sku' => 'CAP-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->vegWarehouse->id,
            'is_active' => true,
        ]);

        $this->businessDayService = app(PurchaserBusinessDayService::class);
        $this->comparisonService = app(DailyInventoryComparisonService::class);

        // Enable business day module
        $this->businessDayService->updateWarehouseSettings($this->vegWarehouse->id, [
            'enabled' => true,
            'purchasers_can_close' => true,
            'purchasers_can_reopen' => true,
            'reopen_requires_reason' => true,
            'allow_close_with_pending' => true,
            'require_digital_verification' => true,
            'admin_override_reopen' => true,
        ]);
    }

    /**
     * Test A: Advance 100 kg, Bill 60 kg -> pending 40 kg.
     */
    public function test_a_advance_100kg_and_bill_60kg_results_in_40kg_pending(): void
    {
        $day = $this->businessDayService->open($this->vegWarehouse->id, '2026-09-15', $this->adminUser->id);

        // 1. Advance Receive: 100 kg Tomato
        $this->createAdvanceGrn($day, $this->tomato, 100.0, '2026-09-15 08:00:00');

        // 2. Purchaser creates Bill for 60 kg Tomato via pending-first form
        $response = $this->actingAs($this->purchaserUser)->post(route('purchasing.business-days.bills.store', $day->uuid), [
            'business_day_id' => $day->id,
            'supplier_id' => $this->supplierA->id,
            'bill_number' => 'BILL-A-001',
            'received_at' => '2026-09-15 10:30:00',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 60.0,
                    'received_unit' => 'kg',
                    'unit_price' => 30.0,
                ],
            ],
        ]);

        $response->assertRedirect(route('purchasing.business-days.show', $day->uuid));

        // Check comparison rows
        $rows = $this->comparisonService->buildComparisonRows('2026-09-15', $this->vegWarehouse->id);
        $tomatoRow = $rows->firstWhere('product_id', $this->tomato->id);

        $this->assertNotNull($tomatoRow);
        $this->assertEquals(100.0, (float) $tomatoRow['advance_qty']);
        $this->assertEquals(60.0, (float) $tomatoRow['bill_qty']);
        $this->assertEquals(60.0, (float) $tomatoRow['matched_bill_qty']);
        $this->assertEquals(40.0, max(0.0, (float) $tomatoRow['advance_qty'] - (float) $tomatoRow['matched_bill_qty']));

        // Detail page shows pending 40 kg
        $detailResponse = $this->actingAs($this->purchaserUser)->get(route('purchasing.business-days.show', $day->uuid));
        $detailResponse->assertOk();
        $detailResponse->assertSee('40 kg');
        $detailResponse->assertSee('Pending Bill');
    }

    /**
     * Test B: Second Bill 40 kg -> auto-match -> pending 0 (removed from pending list automatically).
     */
    public function test_b_second_bill_40kg_auto_matches_and_clears_pending_to_zero(): void
    {
        $day = $this->businessDayService->open($this->vegWarehouse->id, '2026-09-15', $this->adminUser->id);

        // 1. Advance 100 kg
        $this->createAdvanceGrn($day, $this->tomato, 100.0, '2026-09-15 08:00:00');

        // 2. First Bill: 60 kg
        $this->actingAs($this->purchaserUser)->post(route('purchasing.business-days.bills.store', $day->uuid), [
            'business_day_id' => $day->id,
            'supplier_id' => $this->supplierA->id,
            'bill_number' => 'BILL-B-001',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 60.0,
                    'received_unit' => 'kg',
                    'unit_price' => 30.0,
                ],
            ],
        ]);

        // 3. Second Bill: 40 kg
        $response = $this->actingAs($this->purchaserUser)->post(route('purchasing.business-days.bills.store', $day->uuid), [
            'business_day_id' => $day->id,
            'supplier_id' => $this->supplierB->id,
            'bill_number' => 'BILL-B-002',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 40.0,
                    'received_unit' => 'kg',
                    'unit_price' => 32.0,
                ],
            ],
        ]);

        $response->assertRedirect(route('purchasing.business-days.show', $day->uuid));

        // Comparison check
        $rows = $this->comparisonService->buildComparisonRows('2026-09-15', $this->vegWarehouse->id);
        $tomatoRow = $rows->firstWhere('product_id', $this->tomato->id);

        $this->assertNotNull($tomatoRow);
        $this->assertEquals(100.0, (float) $tomatoRow['advance_qty']);
        $this->assertEquals(100.0, (float) $tomatoRow['bill_qty']);
        $this->assertEquals(100.0, (float) $tomatoRow['matched_bill_qty']);
        $pending = max(0.0, (float) $tomatoRow['advance_qty'] - (float) $tomatoRow['matched_bill_qty']);
        $this->assertEquals(0.0, $pending);

        // Verification: Pending list is clean
        $detailResponse = $this->actingAs($this->purchaserUser)->get(route('purchasing.business-days.show', $day->uuid));
        $detailResponse->assertOk();
        $detailResponse->assertSee('🎉 Clean state! All advance receipts for this business day have matching supplier bills.');
    }

    /**
     * Test C: Bill next calendar morning assigned to previous OPEN Business Day -> matches correctly.
     */
    public function test_c_bill_next_calendar_morning_assigned_to_previous_open_business_day_matches(): void
    {
        // 15 Sep Business Day
        $day15 = $this->businessDayService->open($this->vegWarehouse->id, '2026-09-15', $this->adminUser->id);

        // Advance received on 15 Sep
        $this->createAdvanceGrn($day15, $this->tomato, 150.0, '2026-09-15 18:00:00');

        // Bill created physically on 16 Sep morning at 07:30 AM, but explicitly assigned to 15 Sep business day
        $response = $this->actingAs($this->purchaserUser)->post(route('purchasing.business-days.bills.store', $day15->uuid), [
            'business_day_id' => $day15->id,
            'supplier_id' => $this->supplierA->id,
            'bill_number' => 'BILL-C-MORNING',
            'received_at' => '2026-09-16 07:30:00', // Real physical timestamp preserved
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 150.0,
                    'received_unit' => 'kg',
                    'unit_price' => 28.0,
                ],
            ],
        ]);

        $response->assertRedirect(route('purchasing.business-days.show', $day15->uuid));

        // Verify bill preserved physical timestamp and has business_day_id
        $billGrn = GoodsReceived::where('bill_number', 'BILL-C-MORNING')->first();
        $this->assertNotNull($billGrn);
        $this->assertEquals($day15->id, $billGrn->business_day_id);
        $this->assertEquals('2026-09-16 07:30:00', $billGrn->received_at->format('Y-m-d H:i:s'));

        // Verify matched against 15 Sep business day
        $rows = $this->comparisonService->buildComparisonRows('2026-09-15', $this->vegWarehouse->id);
        $tomatoRow = $rows->firstWhere('product_id', $this->tomato->id);
        $this->assertEquals(150.0, (float) $tomatoRow['matched_bill_qty']);
        $this->assertEquals(0.0, max(0.0, (float) $tomatoRow['advance_qty'] - (float) $tomatoRow['matched_bill_qty']));
    }

    /**
     * Test D: Multi-product bill -> all selected products update inventory/match correctly.
     */
    public function test_d_multi_product_bill_creates_one_purchase_bill_and_matches_all_items(): void
    {
        $day = $this->businessDayService->open($this->vegWarehouse->id, '2026-09-15', $this->adminUser->id);

        // Advance receipts for 3 different products: Tomato 150kg, Cucumber 30kg, Capsicum 20kg
        $this->createAdvanceGrn($day, $this->tomato, 150.0, '2026-09-15 09:00:00');
        $this->createAdvanceGrn($day, $this->cucumber, 30.0, '2026-09-15 09:10:00');
        $this->createAdvanceGrn($day, $this->capsicum, 20.0, '2026-09-15 09:20:00');

        // Create one single multi-product bill from Vendor A
        $response = $this->actingAs($this->purchaserUser)->post(route('purchasing.business-days.bills.store', $day->uuid), [
            'business_day_id' => $day->id,
            'supplier_id' => $this->supplierA->id,
            'bill_number' => 'BILL-MULTI-001',
            'received_at' => '2026-09-15 11:00:00',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 150.0,
                    'received_unit' => 'kg',
                    'unit_price' => 30.0,
                ],
                [
                    'product_id' => $this->cucumber->id,
                    'received_qty' => 30.0,
                    'received_unit' => 'kg',
                    'unit_price' => 40.0,
                ],
                [
                    'product_id' => $this->capsicum->id,
                    'received_qty' => 20.0,
                    'received_unit' => 'kg',
                    'unit_price' => 50.0,
                ],
            ],
        ]);

        $response->assertRedirect(route('purchasing.business-days.show', $day->uuid));

        // Check that exactly 1 Purchase Bill GRN was created with 3 items
        $bills = GoodsReceived::where('bill_number', 'BILL-MULTI-001')->get();
        $this->assertCount(1, $bills);
        $bill = $bills->first();
        $this->assertCount(3, $bill->items);

        // Check stock batches created
        $batches = StockBatch::where('goods_received_id', $bill->id)->get();
        $this->assertCount(3, $batches);

        // Check all 3 products matched completely
        $rows = $this->comparisonService->buildComparisonRows('2026-09-15', $this->vegWarehouse->id);
        $tomatoRow = $rows->firstWhere('product_id', $this->tomato->id);
        $cucumberRow = $rows->firstWhere('product_id', $this->cucumber->id);
        $capsicumRow = $rows->firstWhere('product_id', $this->capsicum->id);

        $this->assertEquals(150.0, (float) $tomatoRow['matched_bill_qty']);
        $this->assertEquals(30.0, (float) $cucumberRow['matched_bill_qty']);
        $this->assertEquals(20.0, (float) $capsicumRow['matched_bill_qty']);

        $this->assertEquals(0.0, max(0.0, (float) $tomatoRow['advance_qty'] - (float) $tomatoRow['matched_bill_qty']));
        $this->assertEquals(0.0, max(0.0, (float) $cucumberRow['advance_qty'] - (float) $cucumberRow['matched_bill_qty']));
        $this->assertEquals(0.0, max(0.0, (float) $capsicumRow['advance_qty'] - (float) $capsicumRow['matched_bill_qty']));
    }

    /**
     * Test E: Bill edit -> inventory/reconciliation/pending update correctly.
     */
    public function test_e_bill_edit_recalculates_inventory_and_refreshes_matching(): void
    {
        $day = $this->businessDayService->open($this->vegWarehouse->id, '2026-09-15', $this->adminUser->id);

        // Advance 100 kg
        $this->createAdvanceGrn($day, $this->tomato, 100.0, '2026-09-15 08:00:00');

        // Initially entered 100 kg bill
        $this->actingAs($this->purchaserUser)->post(route('purchasing.business-days.bills.store', $day->uuid), [
            'business_day_id' => $day->id,
            'supplier_id' => $this->supplierA->id,
            'bill_number' => 'BILL-EDIT-TEST',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'unit_price' => 30.0,
                ],
            ],
        ]);

        $billGrn = GoodsReceived::where('bill_number', 'BILL-EDIT-TEST')->firstOrFail();
        $billItem = $billGrn->items->first();

        // Edit bill to 80 kg (e.g. purchaser corrected typo)
        $response = $this->actingAs($this->purchaserUser)->put(route('purchasing.business-days.bills.update', ['uuid' => $day->uuid, 'grn' => $billGrn->getRouteKey()]), [
            'bill_number' => 'BILL-EDIT-TEST-CORRECTED',
            'items' => [
                [
                    'id' => $billItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 80.0,
                    'received_unit' => 'kg',
                    'unit_price' => 30.0,
                ],
            ],
        ]);

        $response->assertRedirect(route('purchasing.business-days.show', $day->uuid));

        // Check bill item updated to 80 kg
        $freshItem = $billGrn->fresh()->items->first();
        $this->assertEquals(80.0, (float) $freshItem->received_qty);

        // Check comparison reflects 80 kg bill and 20 kg pending
        $rows = $this->comparisonService->buildComparisonRows('2026-09-15', $this->vegWarehouse->id);
        $tomatoRow = $rows->firstWhere('product_id', $this->tomato->id);

        $this->assertEquals(100.0, (float) $tomatoRow['advance_qty']);
        $this->assertEquals(80.0, (float) $tomatoRow['bill_qty']);
        $this->assertEquals(80.0, (float) $tomatoRow['matched_bill_qty']);
        $this->assertEquals(20.0, max(0.0, (float) $tomatoRow['advance_qty'] - (float) $tomatoRow['matched_bill_qty']));
    }

    /**
     * Test F: Closed day -> bill add/edit blocked.
     */
    public function test_f_closed_day_blocks_bill_add_and_edit(): void
    {
        $day = $this->businessDayService->open($this->vegWarehouse->id, '2026-09-15', $this->adminUser->id);

        // Create a bill first while open
        $this->actingAs($this->purchaserUser)->post(route('purchasing.business-days.bills.store', $day->uuid), [
            'business_day_id' => $day->id,
            'supplier_id' => $this->supplierA->id,
            'bill_number' => 'BILL-CLOSE-TEST',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 50.0,
                    'received_unit' => 'kg',
                    'unit_price' => 30.0,
                ],
            ],
        ]);

        $billGrn = GoodsReceived::where('bill_number', 'BILL-CLOSE-TEST')->firstOrFail();

        // Close day
        $this->businessDayService->close($day, $this->adminUser->id);

        // 1. Attempt Add Bill on closed day -> blocked
        $addResponse = $this->actingAs($this->purchaserUser)->get(route('purchasing.business-days.bills.create', $day->uuid));
        $addResponse->assertRedirect(route('purchasing.business-days.show', $day->uuid));
        $addResponse->assertSessionHas('error');

        // 2. Attempt Edit Bill on closed day -> blocked
        $editResponse = $this->actingAs($this->purchaserUser)->get(route('purchasing.business-days.bills.edit', ['uuid' => $day->uuid, 'grn' => $billGrn->getRouteKey()]));
        $editResponse->assertRedirect(route('purchasing.business-days.show', $day->uuid));
        $editResponse->assertSessionHas('error');

        // 3. Attempt Update Bill on closed day -> blocked
        $updateResponse = $this->actingAs($this->purchaserUser)->put(route('purchasing.business-days.bills.update', ['uuid' => $day->uuid, 'grn' => $billGrn->getRouteKey()]), [
            'bill_number' => 'BILL-ATTEMPT-UPDATE',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 60.0,
                    'received_unit' => 'kg',
                    'unit_price' => 30.0,
                ],
            ],
        ]);
        $updateResponse->assertRedirect(route('purchasing.business-days.show', $day->uuid));
        $updateResponse->assertSessionHas('error');
    }

    /**
     * Test G: Reopen -> bill edit allowed -> auto-match reruns.
     */
    public function test_g_reopened_day_allows_bill_edit_and_reruns_auto_match(): void
    {
        $day = $this->businessDayService->open($this->vegWarehouse->id, '2026-09-15', $this->adminUser->id);
        $this->createAdvanceGrn($day, $this->tomato, 100.0, '2026-09-15 08:00:00');

        $this->actingAs($this->purchaserUser)->post(route('purchasing.business-days.bills.store', $day->uuid), [
            'business_day_id' => $day->id,
            'supplier_id' => $this->supplierA->id,
            'bill_number' => 'BILL-REOPEN-EDIT',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 50.0,
                    'received_unit' => 'kg',
                    'unit_price' => 30.0,
                ],
            ],
        ]);

        $billGrn = GoodsReceived::where('bill_number', 'BILL-REOPEN-EDIT')->firstOrFail();
        $billItem = $billGrn->items->first();

        // Close day with note
        $this->businessDayService->close($day, $this->adminUser->id, 'End of shift');

        // Reopen day with reason
        $this->actingAs($this->purchaserUser)->post(route('purchasing.business-days.reopen', $day->uuid), [
            'reopen_reason' => 'Vendor sent revised invoice quantity.',
        ]);

        $this->assertTrue($day->fresh()->isReopened());

        // Now edit bill to 100 kg
        $response = $this->actingAs($this->purchaserUser)->put(route('purchasing.business-days.bills.update', ['uuid' => $day->uuid, 'grn' => $billGrn->getRouteKey()]), [
            'bill_number' => 'BILL-REOPEN-EDIT',
            'items' => [
                [
                    'id' => $billItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'unit_price' => 30.0,
                ],
            ],
        ]);

        $response->assertRedirect(route('purchasing.business-days.show', $day->uuid));

        // Check comparison shows 100 matched and 0 pending
        $rows = $this->comparisonService->buildComparisonRows('2026-09-15', $this->vegWarehouse->id);
        $tomatoRow = $rows->firstWhere('product_id', $this->tomato->id);

        $this->assertEquals(100.0, (float) $tomatoRow['advance_qty']);
        $this->assertEquals(100.0, (float) $tomatoRow['bill_qty']);
        $this->assertEquals(100.0, (float) $tomatoRow['matched_bill_qty']);
        $this->assertEquals(0.0, max(0.0, (float) $tomatoRow['advance_qty'] - (float) $tomatoRow['matched_bill_qty']));
    }

    /**
     * Test H: Retry same receive/update -> no duplicate inventory, no duplicate match.
     */
    public function test_h_retry_same_match_execution_does_not_create_duplicate_inventory_or_matches(): void
    {
        $day = $this->businessDayService->open($this->vegWarehouse->id, '2026-09-15', $this->adminUser->id);
        $this->createAdvanceGrn($day, $this->tomato, 100.0, '2026-09-15 08:00:00');

        $this->actingAs($this->purchaserUser)->post(route('purchasing.business-days.bills.store', $day->uuid), [
            'business_day_id' => $day->id,
            'supplier_id' => $this->supplierA->id,
            'bill_number' => 'BILL-IDEMPOTENT-001',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'unit_price' => 30.0,
                ],
            ],
        ]);

        $billGrn = GoodsReceived::where('bill_number', 'BILL-IDEMPOTENT-001')->firstOrFail();

        // Count initial batches and matches
        $initialBatchCount = StockBatch::where('goods_received_id', $billGrn->id)->count();
        $this->assertEquals(1, $initialBatchCount);

        // Manually rerun autoMatch multiple times
        $this->comparisonService->autoMatchForGrn($billGrn, $this->adminUser->id);
        $this->comparisonService->autoMatchForGrn($billGrn, $this->adminUser->id);

        // Batch count must remain 1
        $finalBatchCount = StockBatch::where('goods_received_id', $billGrn->id)->count();
        $this->assertEquals(1, $finalBatchCount);

        // Total matched qty on comparison row must remain strictly 100.0
        $rows = $this->comparisonService->buildComparisonRows('2026-09-15', $this->vegWarehouse->id);
        $tomatoRow = $rows->firstWhere('product_id', $this->tomato->id);

        $this->assertEquals(100.0, (float) $tomatoRow['advance_qty']);
        $this->assertEquals(100.0, (float) $tomatoRow['bill_qty']);
        $this->assertEquals(100.0, (float) $tomatoRow['matched_bill_qty']);
    }

    private function createAdvanceGrn(PurchaseBusinessDay $day, Product $product, float $qty, string $receivedAt): GoodsReceived
    {
        $grn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $day->warehouse_id,
            'business_day_id' => $day->id,
            'grn_number' => 'GRN-ADV-'.Str::upper(Str::random(6)),
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->adminUser->id,
            'approved_by' => $this->adminUser->id,
            'received_at' => $receivedAt,
            'approved_at' => $receivedAt,
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $product->unit ?? 'kg',
            'unit_price' => 0.0,
            'total_amount' => 0.0,
            'billed_qty' => 0.0,
            'variance' => 0.0,
            'is_matched' => false,
        ]);

        $ref = 'BAT-'.Str::upper(Str::random(8));
        StockBatch::create([
            'goods_received_id' => $grn->id,
            'warehouse_id' => $day->warehouse_id,
            'product_id' => $product->id,
            'reference' => $ref,
            'batch_number' => $ref,
            'quantity_received' => $qty,
            'quantity_remaining' => $qty,
            'cost_per_kg' => 0.0,
            'received_at' => $receivedAt,
            'status' => BatchStatus::Pending,
            'created_by' => $this->adminUser->id,
            'total_kg' => $qty,
            'remaining_kg' => $qty,
        ]);

        return $grn;
    }
}
