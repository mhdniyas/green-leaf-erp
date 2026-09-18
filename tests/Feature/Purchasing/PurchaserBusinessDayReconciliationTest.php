<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\AdvanceReceiveMatch;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseBusinessDay;
use App\Models\PurchaserCart;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaserAllotmentService;
use App\Services\Purchasing\PurchaserBusinessDayReconciliationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaserBusinessDayReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private PurchaserBusinessDayReconciliationService $reconciliationService;

    private PurchaserAllotmentService $allotmentService;

    private User $purchaser1;

    private User $purchaser2;

    private Warehouse $warehouse;

    private Product $productPotato;

    private Product $productTomato;

    private PurchaseBusinessDay $businessDaySep17;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->reconciliationService = app(PurchaserBusinessDayReconciliationService::class);
        $this->allotmentService = app(PurchaserAllotmentService::class);

        $this->purchaser1 = User::factory()->create(['name' => 'Faisal']);
        $this->purchaser1->assignRole('purchaser');

        $this->purchaser2 = User::factory()->create(['name' => 'Rasheed']);
        $this->purchaser2->assignRole('purchaser');

        $this->warehouse = Warehouse::create([
            'name' => 'Central Warehouse',
            'code' => 'WH-CENTRAL',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Vegetables',
            'is_active' => true,
        ]);

        $this->productPotato = Product::create([
            'name' => 'Potato',
            'sku' => 'POT-01',
            'unit' => 'kg',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $this->productTomato = Product::create([
            'name' => 'Tomato',
            'sku' => 'TOM-01',
            'unit' => 'kg',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $this->businessDaySep17 = PurchaseBusinessDay::create([
            'business_date' => '2026-09-17',
            'warehouse_id' => $this->warehouse->id,
            'status' => PurchaseBusinessDay::STATUS_OPEN,
            'opened_by' => $this->purchaser1->id,
            'opened_at' => '2026-09-17 08:00:00',
        ]);
    }

    public function test_direct_ownership_wins_over_product_allotment(): void
    {
        // Product allotment on 17 Sep assigned to Purchaser 2 (Rasheed)
        $this->allotmentService->assignPurchaser($this->productPotato->id, $this->purchaser2->id, '2026-09-17');

        // Direct cart created by Purchaser 1 (Faisal)
        $cart = PurchaserCart::create([
            'cart_number' => 'CART-001',
            'user_id' => $this->purchaser1->id,
            'business_date' => '2026-09-17',
            'status' => 'submitted',
        ]);

        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-001',
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $this->businessDaySep17->id,
            'purchaser_cart_id' => $cart->id,
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->purchaser1->id,
            'received_at' => '2026-09-17 10:00:00',
        ]);

        $grnItem = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->productPotato->id,
            'received_qty' => 100.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $resolvedPurchaserId = $this->reconciliationService->resolvePurchaserForReceiptItem($grnItem, '2026-09-17');

        // Direct cart user (Purchaser 1) MUST win over Product Allotment (Purchaser 2)
        $this->assertEquals($this->purchaser1->id, $resolvedPurchaserId);
    }

    public function test_allotment_fallback_used_when_direct_ownership_is_missing(): void
    {
        // Standalone advance GRN without cart/PO link
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-002',
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $this->businessDaySep17->id,
            'purchaser_cart_id' => null,
            'purchase_order_id' => null,
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->purchaser1->id,
            'received_at' => '2026-09-17 10:00:00',
        ]);

        $grnItem = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->productPotato->id,
            'received_qty' => 80.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        // Product allotment on 17 Sep assigned to Purchaser 1 (Faisal)
        $this->allotmentService->assignPurchaser($this->productPotato->id, $this->purchaser1->id, '2026-09-17');

        $resolvedPurchaserId = $this->reconciliationService->resolvePurchaserForReceiptItem($grnItem, '2026-09-17');

        $this->assertEquals($this->purchaser1->id, $resolvedPurchaserId);
    }

    public function test_unassigned_receipt_when_neither_direct_ownership_nor_allotment_exists(): void
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-003',
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $this->businessDaySep17->id,
            'purchaser_cart_id' => null,
            'purchase_order_id' => null,
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->purchaser1->id,
            'received_at' => '2026-09-17 10:00:00',
        ]);

        $grnItem = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->productTomato->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $resolvedPurchaserId = $this->reconciliationService->resolvePurchaserForReceiptItem($grnItem, '2026-09-17');

        $this->assertNull($resolvedPurchaserId);
    }

    public function test_partial_and_full_coverage_reconciliation_calculation(): void
    {
        $this->allotmentService->assignPurchaser($this->productPotato->id, $this->purchaser1->id, '2026-09-17');
        $this->allotmentService->assignPurchaser($this->productTomato->id, $this->purchaser1->id, '2026-09-17');

        // Standalone advance GRN for 80 kg Potato + 20 kg Tomato
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-004',
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $this->businessDaySep17->id,
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->purchaser1->id,
            'received_at' => '2026-09-17 10:00:00',
        ]);

        $grnBill = GoodsReceived::create([
            'grn_number' => 'GRN-BILL-004',
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $this->businessDaySep17->id,
            'receipt_type' => 'direct_bill',
            'received_by' => $this->purchaser1->id,
            'received_at' => '2026-09-17 11:00:00',
        ]);

        $itemPotato = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->productPotato->id,
            'received_qty' => 80.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $itemTomato = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->productTomato->id,
            'received_qty' => 20.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        // Create AdvanceMatch: 50 kg billed for Potato, 20 kg billed for Tomato
        AdvanceReceiveMatch::create([
            'business_day_id' => $this->businessDaySep17->id,
            'advance_goods_received_id' => $grn->id,
            'advance_goods_received_item_id' => $itemPotato->id,
            'bill_goods_received_id' => $grnBill->id,
            'product_id' => $this->productPotato->id,
            'matched_qty' => 50.0,
            'matched_unit' => 'kg',
            'base_qty' => 50.0,
            'confirmed_at' => now(),
        ]);

        AdvanceReceiveMatch::create([
            'business_day_id' => $this->businessDaySep17->id,
            'advance_goods_received_id' => $grn->id,
            'advance_goods_received_item_id' => $itemTomato->id,
            'bill_goods_received_id' => $grnBill->id,
            'product_id' => $this->productTomato->id,
            'matched_qty' => 20.0,
            'matched_unit' => 'kg',
            'base_qty' => 20.0,
            'confirmed_at' => now(),
        ]);

        $res = $this->reconciliationService->calculateReconciliation($this->purchaser1->id, '2026-09-17', $this->warehouse->id);

        $this->assertEquals(100.0, $res['total_received_qty']);
        $this->assertEquals(70.0, $res['total_billed_qty']);
        $this->assertEquals(30.0, $res['total_pending_qty']);
        $this->assertEquals(70.0, $res['coverage_percentage']);
        $this->assertFalse($res['is_fully_covered']);

        $productsKeyed = collect($res['products'])->keyBy('product_id');

        // Potato: 50 / 80 = 62.5%
        $potatoRow = $productsKeyed->get($this->productPotato->id);
        $this->assertNotNull($potatoRow);
        $this->assertEquals(80.0, $potatoRow['received_qty']);
        $this->assertEquals(50.0, $potatoRow['billed_qty']);
        $this->assertEquals(30.0, $potatoRow['pending_qty']);
        $this->assertEquals(62.5, $potatoRow['coverage_percentage']);
        $this->assertFalse($potatoRow['is_fully_covered']);

        // Tomato: 20 / 20 = 100%
        $tomatoRow = $productsKeyed->get($this->productTomato->id);
        $this->assertNotNull($tomatoRow);
        $this->assertEquals(20.0, $tomatoRow['received_qty']);
        $this->assertEquals(20.0, $tomatoRow['billed_qty']);
        $this->assertEquals(0.0, $tomatoRow['pending_qty']);
        $this->assertEquals(100.0, $tomatoRow['coverage_percentage']);
        $this->assertTrue($tomatoRow['is_fully_covered']);
    }

    public function test_current_coverage_updates_dynamically_when_late_bill_is_submitted_for_past_receipt_date(): void
    {
        $this->allotmentService->assignPurchaser($this->productPotato->id, $this->purchaser1->id, '2026-09-17');

        // 17 Sep: 80 kg Potato received as advance
        $grnSep17 = GoodsReceived::create([
            'grn_number' => 'GRN-005',
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $this->businessDaySep17->id,
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->purchaser1->id,
            'received_at' => '2026-09-17 10:00:00',
        ]);

        $grnBillSep17 = GoodsReceived::create([
            'grn_number' => 'GRN-BILL-005',
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $this->businessDaySep17->id,
            'receipt_type' => 'direct_bill',
            'received_by' => $this->purchaser1->id,
            'received_at' => '2026-09-17 11:00:00',
        ]);

        $itemPotato = GoodsReceivedItem::create([
            'goods_received_id' => $grnSep17->id,
            'product_id' => $this->productPotato->id,
            'received_qty' => 80.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        // 17 Sep: Initial bill of 50 kg entered
        AdvanceReceiveMatch::create([
            'business_day_id' => $this->businessDaySep17->id,
            'advance_goods_received_id' => $grnSep17->id,
            'advance_goods_received_item_id' => $itemPotato->id,
            'bill_goods_received_id' => $grnBillSep17->id,
            'product_id' => $this->productPotato->id,
            'matched_qty' => 50.0,
            'matched_unit' => 'kg',
            'base_qty' => 50.0,
            'confirmed_at' => '2026-09-17 14:00:00',
        ]);

        // Initial reconciliation check on 17 Sep -> 62.5% coverage
        $resInitial = $this->reconciliationService->calculateReconciliation($this->purchaser1->id, '2026-09-17', $this->warehouse->id);
        $this->assertEquals(62.5, $resInitial['coverage_percentage']);
        $this->assertEquals(30.0, $resInitial['total_pending_qty']);

        // 19 Sep: Purchaser enters remaining 30 kg bill matched to 17 Sep advance receipt
        $businessDaySep19 = PurchaseBusinessDay::create([
            'business_date' => '2026-09-19',
            'warehouse_id' => $this->warehouse->id,
            'status' => PurchaseBusinessDay::STATUS_OPEN,
            'opened_by' => $this->purchaser1->id,
            'opened_at' => '2026-09-19 08:00:00',
        ]);

        $grnBillSep19 = GoodsReceived::create([
            'grn_number' => 'GRN-BILL-006',
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $businessDaySep19->id,
            'receipt_type' => 'direct_bill',
            'received_by' => $this->purchaser1->id,
            'received_at' => '2026-09-19 10:00:00',
        ]);

        AdvanceReceiveMatch::create([
            'business_day_id' => $businessDaySep19->id,
            'advance_goods_received_id' => $grnSep17->id,
            'advance_goods_received_item_id' => $itemPotato->id,
            'bill_goods_received_id' => $grnBillSep19->id,
            'product_id' => $this->productPotato->id,
            'matched_qty' => 30.0,
            'matched_unit' => 'kg',
            'base_qty' => 30.0,
            'confirmed_at' => '2026-09-19 11:00:00',
        ]);

        // Re-querying 17 Sep reconciliation NOW: Received date remains 17 Sep, but Current Coverage dynamically updates to 100%!
        $resUpdated = $this->reconciliationService->calculateReconciliation($this->purchaser1->id, '2026-09-17', $this->warehouse->id);
        $this->assertEquals(80.0, $resUpdated['total_received_qty']);
        $this->assertEquals(80.0, $resUpdated['total_billed_qty']);
        $this->assertEquals(0.0, $resUpdated['total_pending_qty']);
        $this->assertEquals(100.0, $resUpdated['coverage_percentage']);
        $this->assertTrue($resUpdated['is_fully_covered']);
    }

    public function test_reconciliation_calculation_causes_zero_database_writes(): void
    {
        $this->allotmentService->assignPurchaser($this->productPotato->id, $this->purchaser1->id, '2026-09-17');

        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-006',
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $this->businessDaySep17->id,
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->purchaser1->id,
            'received_at' => '2026-09-17 10:00:00',
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->productPotato->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $grnCountBefore = GoodsReceived::count();
        $grnItemCountBefore = GoodsReceivedItem::count();
        $matchCountBefore = AdvanceReceiveMatch::count();

        $this->reconciliationService->calculateReconciliation($this->purchaser1->id, '2026-09-17', $this->warehouse->id);

        $this->assertEquals($grnCountBefore, GoodsReceived::count());
        $this->assertEquals($grnItemCountBefore, GoodsReceivedItem::count());
        $this->assertEquals($matchCountBefore, AdvanceReceiveMatch::count());
    }

    public function test_cancelled_receipt_is_excluded_from_reconciliation(): void
    {
        $this->allotmentService->assignPurchaser($this->productPotato->id, $this->purchaser1->id, '2026-09-17');

        // Cancelled GRN
        $grnCancelled = GoodsReceived::create([
            'grn_number' => 'GRN-007-CANCELLED',
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $this->businessDaySep17->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'cancelled',
            'received_by' => $this->purchaser1->id,
            'received_at' => '2026-09-17 10:00:00',
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grnCancelled->id,
            'product_id' => $this->productPotato->id,
            'received_qty' => 100.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $res = $this->reconciliationService->calculateReconciliation($this->purchaser1->id, '2026-09-17', $this->warehouse->id);

        $this->assertEquals(0.0, $res['total_received_qty']);
        $this->assertEmpty($res['products']);
    }

    public function test_excess_bill_quantity_exposes_variance(): void
    {
        $this->allotmentService->assignPurchaser($this->productPotato->id, $this->purchaser1->id, '2026-09-17');

        $grnAdv = GoodsReceived::create([
            'grn_number' => 'GRN-008',
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $this->businessDaySep17->id,
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->purchaser1->id,
            'received_at' => '2026-09-17 10:00:00',
        ]);

        $grnBill = GoodsReceived::create([
            'grn_number' => 'GRN-BILL-008',
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $this->businessDaySep17->id,
            'receipt_type' => 'direct_bill',
            'received_by' => $this->purchaser1->id,
            'received_at' => '2026-09-17 11:00:00',
        ]);

        $itemPotato = GoodsReceivedItem::create([
            'goods_received_id' => $grnAdv->id,
            'product_id' => $this->productPotato->id,
            'received_qty' => 80.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        // Excess match: Billed 90 kg for 80 kg received
        AdvanceReceiveMatch::create([
            'business_day_id' => $this->businessDaySep17->id,
            'advance_goods_received_id' => $grnAdv->id,
            'advance_goods_received_item_id' => $itemPotato->id,
            'bill_goods_received_id' => $grnBill->id,
            'product_id' => $this->productPotato->id,
            'matched_qty' => 90.0,
            'matched_unit' => 'kg',
            'base_qty' => 90.0,
            'confirmed_at' => now(),
        ]);

        $res = $this->reconciliationService->calculateReconciliation($this->purchaser1->id, '2026-09-17', $this->warehouse->id);

        $this->assertEquals(80.0, $res['total_received_qty']);
        $this->assertEquals(90.0, $res['total_billed_qty']);
        $this->assertEquals(0.0, $res['total_pending_qty']);
        $this->assertEquals(10.0, $res['total_excess_qty']);
        $this->assertEquals(100.0, $res['coverage_percentage']);

        $potatoRow = collect($res['products'])->first();
        $this->assertEquals(10.0, $potatoRow['excess_qty']);
    }

    public function test_unit_mismatch_flagged_when_units_are_incompatible(): void
    {
        $this->allotmentService->assignPurchaser($this->productPotato->id, $this->purchaser1->id, '2026-09-17');

        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-009',
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $this->businessDaySep17->id,
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->purchaser1->id,
            'received_at' => '2026-09-17 10:00:00',
        ]);

        // Item received in 'box' when base product unit is 'kg'
        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->productPotato->id,
            'received_qty' => 5.0,
            'received_unit' => 'box',
            'variance' => 0.0,
        ]);

        $res = $this->reconciliationService->calculateReconciliation($this->purchaser1->id, '2026-09-17', $this->warehouse->id);

        $potatoRow = collect($res['products'])->first();
        $this->assertTrue($potatoRow['unit_mismatch']);
    }

    public function test_warehouse_filter_scopes_reconciliation_to_specific_warehouse(): void
    {
        $warehouse2 = Warehouse::create([
            'name' => 'Branch Warehouse 2',
            'code' => 'WH-BRANCH2',
            'is_active' => true,
        ]);

        $this->allotmentService->assignPurchaser($this->productPotato->id, $this->purchaser1->id, '2026-09-17');

        // GRN at Warehouse 1
        $grn1 = GoodsReceived::create([
            'grn_number' => 'GRN-WH1',
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $this->businessDaySep17->id,
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->purchaser1->id,
            'received_at' => '2026-09-17 10:00:00',
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grn1->id,
            'product_id' => $this->productPotato->id,
            'received_qty' => 60.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        // GRN at Warehouse 2
        $grn2 = GoodsReceived::create([
            'grn_number' => 'GRN-WH2',
            'warehouse_id' => $warehouse2->id,
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->purchaser1->id,
            'received_at' => '2026-09-17 11:00:00',
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grn2->id,
            'product_id' => $this->productPotato->id,
            'received_qty' => 40.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $resAll = $this->reconciliationService->calculateReconciliation($this->purchaser1->id, '2026-09-17', null);
        $resWh1 = $this->reconciliationService->calculateReconciliation($this->purchaser1->id, '2026-09-17', $this->warehouse->id);
        $resWh2 = $this->reconciliationService->calculateReconciliation($this->purchaser1->id, '2026-09-17', $warehouse2->id);

        $this->assertEquals(100.0, $resAll['total_received_qty']);
        $this->assertEquals(60.0, $resWh1['total_received_qty']);
        $this->assertEquals(40.0, $resWh2['total_received_qty']);
    }
}
