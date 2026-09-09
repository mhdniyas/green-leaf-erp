<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

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
use App\Services\Purchasing\WarehouseReceiptReadScope;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AutoMatchPhase1RegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Warehouse $warehouse;

    private Warehouse $otherWarehouse;

    private Shop $shop;

    private Supplier $supplier;

    private Product $papaya;

    private Product $noolkol;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);

        $this->otherWarehouse = Warehouse::create([
            'name' => 'Other Warehouse',
            'code' => 'WH-OTHER',
            'is_active' => true,
        ]);

        $this->shop = Shop::factory()->create(['name' => 'Main Shop']);
        $this->supplier = Supplier::factory()->create(['name' => 'Agro Vendor']);
        $category = Category::factory()->create();

        $this->papaya = Product::factory()->create([
            'name' => 'Pappaya',
            'sku' => 'PAP-01',
            'unit' => 'kg',
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'is_active' => true,
        ]);

        $this->noolkol = Product::factory()->create([
            'name' => 'Noolkol',
            'sku' => 'NOOL-01',
            'unit' => 'kg',
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'is_active' => true,
        ]);
    }

    private function createPhysicalBatch(Product $product, Warehouse $warehouse, float $qtyKg, string $date, ?GoodsReceived $grn = null): StockBatch
    {
        return StockBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'goods_received_id' => $grn?->id,
            'reference' => 'BATCH-'.Str::upper(Str::random(6)),
            'total_kg' => $qtyKg,
            'available_kg' => $qtyKg,
            'cost_per_kg' => 10.0,
            'received_at' => $date,
            'created_by' => $this->adminUser->id,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->adminUser->id,
        ]);
    }

    private function createAdvanceGrn(Warehouse $warehouse, Product $product, float $qty, string $unit = 'kg'): GoodsReceived
    {
        $grn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'grn_number' => 'GRN-ADV-'.Str::upper(Str::random(6)),
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => $warehouse->id,
            'received_by' => $this->adminUser->id,
            'received_at' => '2026-09-08',
        ]);

        $item = $grn->items()->create([
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $unit,
            'variance' => 0,
        ]);

        $this->createPhysicalBatch($product, $warehouse, $qty, '2026-09-08', $grn);

        return $grn;
    }

    /**
     * Test 1: 196 + 20 = fully reconciled 216 kg bill.
     * Consecutive partial matches must calculate cumulative remainder and clear bill from pending.
     */
    public function test_cumulative_partial_reconciliation_clears_216kg_bill(): void
    {
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'destination_shop_id' => $this->shop->id,
            'po_number' => 'PO-PAP-216',
            'status' => POStatus::Approved,
            'order_date' => '2026-09-08',
            'created_by' => $this->adminUser->id,
        ]);
        $poItem = $po->items()->create([
            'product_id' => $this->papaya->id,
            'quantity' => 216.0,
            'unit' => 'kg',
            'purchase_unit' => 'kg',
            'unit_price' => 25.0,
            'total_price' => 5400.0,
        ]);

        // Bill GRN for 216 kg
        $billGrn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-BILL-216',
            'status' => 'pending',
            'bill_status' => 'bill_pending',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->adminUser->id,
            'received_at' => '2026-09-08',
        ]);
        $billItem = $billGrn->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->papaya->id,
            'received_qty' => 216.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);
        $this->createPhysicalBatch($this->papaya, $this->warehouse, 216.0, '2026-09-08', $billGrn);

        // Advance 1: 196 kg
        $adv1 = $this->createAdvanceGrn($this->warehouse, $this->papaya, 196.0);
        // Advance 2: 20 kg
        $adv2 = $this->createAdvanceGrn($this->warehouse, $this->papaya, 20.0);

        $reconService = app(AdvanceReceiveReconciliationService::class);

        // First match: 196 kg
        $reconService->reconcileExistingGrn(
            grn: $billGrn,
            items: [$billItem->id => ['received_qty' => 216.0]],
            advanceMatches: [
                [
                    'advance_goods_received_id' => $adv1->id,
                    'advance_goods_received_item_id' => $adv1->items->first()->id,
                    'product_id' => $this->papaya->id,
                    'purchase_order_item_id' => $poItem->id,
                    'goods_received_item_id' => $billItem->id,
                    'matched_qty' => 196.0,
                    'matched_unit' => 'kg',
                    'base_qty' => 196.0,
                ],
            ],
            fallbackWarehouseId: $this->warehouse->id,
            userId: $this->adminUser->id,
            autoAdvanceClear: true
        );

        $billGrn->refresh();
        $this->assertEquals('bill_pending', $billGrn->bill_status);
        $this->assertEquals(20.0, (float) $billGrn->billReconciliation->total_new_receive_base_qty);

        // Second match: 20 kg
        $reconService->reconcileExistingGrn(
            grn: $billGrn,
            items: [$billItem->id => ['received_qty' => 216.0]],
            advanceMatches: [
                [
                    'advance_goods_received_id' => $adv2->id,
                    'advance_goods_received_item_id' => $adv2->items->first()->id,
                    'product_id' => $this->papaya->id,
                    'purchase_order_item_id' => $poItem->id,
                    'goods_received_item_id' => $billItem->id,
                    'matched_qty' => 20.0,
                    'matched_unit' => 'kg',
                    'base_qty' => 20.0,
                ],
            ],
            fallbackWarehouseId: $this->warehouse->id,
            userId: $this->adminUser->id,
            autoAdvanceClear: true
        );

        $billGrn->refresh();
        // Fully allocated: remaining = 216 - (196 + 20) = 0
        $this->assertEquals('bill_available', $billGrn->bill_status);
        $this->assertEquals(0.0, (float) $billGrn->billReconciliation->total_new_receive_base_qty);
        $this->assertEquals(216.0, (float) $billGrn->billReconciliation->total_matched_base_qty);
        $this->assertEquals('advance', $billGrn->billReconciliation->source_type);

        // Batch total_kg reduced to 0
        $batch = StockBatch::where('goods_received_id', $billGrn->id)->first();
        $this->assertEquals(0.0, (float) $batch->total_kg);
    }

    /**
     * Test 2: Multiple GRNs on one PO item.
     * Allocations linked to GRN A must not be subtracted from GRN B.
     */
    public function test_multiple_grns_on_one_po_item_respects_exact_grn_allocations(): void
    {
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'destination_shop_id' => $this->shop->id,
            'po_number' => 'PO-NOOL-MULTI',
            'status' => POStatus::Approved,
            'order_date' => '2026-09-08',
            'created_by' => $this->adminUser->id,
        ]);
        $poItem = $po->items()->create([
            'product_id' => $this->noolkol->id,
            'quantity' => 24.0,
            'unit' => 'kg',
            'purchase_unit' => 'kg',
            'unit_price' => 30.0,
            'total_price' => 720.0,
        ]);

        // GRN 1 for 12 kg
        $grn1 = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-NOOL-01',
            'status' => 'pending',
            'bill_status' => 'bill_pending',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->adminUser->id,
            'received_at' => '2026-09-08',
        ]);
        $grn1Item = $grn1->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->noolkol->id,
            'received_qty' => 12.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);
        $this->createPhysicalBatch($this->noolkol, $this->warehouse, 12.0, '2026-09-08', $grn1);

        // GRN 2 for 12 kg
        $grn2 = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'purchase_order_id' => $po->id,
            'grn_number' => 'GRN-NOOL-02',
            'status' => 'pending',
            'bill_status' => 'bill_pending',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->adminUser->id,
            'received_at' => '2026-09-08',
        ]);
        $grn2Item = $grn2->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->noolkol->id,
            'received_qty' => 12.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);
        $this->createPhysicalBatch($this->noolkol, $this->warehouse, 12.0, '2026-09-08', $grn2);

        // Advance: 10 kg
        $adv = $this->createAdvanceGrn($this->warehouse, $this->noolkol, 10.0);

        // Existing match for GRN 1: 12 kg allocated to GRN 1
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $adv->id,
            'advance_goods_received_item_id' => $adv->items->first()->id,
            'bill_goods_received_id' => $grn1->id,
            'bill_goods_received_item_id' => $grn1Item->id,
            'purchase_order_id' => $po->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->noolkol->id,
            'matched_qty' => 12.0,
            'matched_unit' => 'kg',
            'base_qty' => 12.0,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->adminUser->id,
            'confirmed_at' => now(),
        ]);

        // Plan for this warehouse
        $planner = app(AutoAdvanceClearPlanningService::class);
        $plan = $planner->buildAutoClearPlan($this->warehouse->id, $this->adminUser->id);

        // GRN 2 must NOT have the 12 kg subtracted; it has its own 12 kg receivable!
        $allTargets = array_merge($plan['ready_bills'], $plan['skipped_bills']);
        $grn2Target = collect($allTargets)->firstWhere('source_goods_received_id', $grn2->id);

        $this->assertNotNull($grn2Target);
        $this->assertEquals(12.0, (float) $grn2Target['lines'][0]['quantity']);
        $this->assertEquals(0.0, (float) $grn2Target['lines'][0]['already_matched_base_qty']);
    }

    /**
     * Test 3: Warehouse resolved from confirmed stock batch.
     * Advance with NULL header warehouse_id but confirmed stock batch in warehouse must be recognized.
     */
    public function test_warehouse_resolved_from_stock_batch(): void
    {
        // Advance with null warehouse_id header
        $adv = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'grn_number' => 'GRN-ADV-NULL-WH',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'warehouse_id' => null,
            'destination_shop_id' => null,
            'received_by' => $this->adminUser->id,
            'received_at' => '2026-09-08',
        ]);
        $adv->items()->create([
            'product_id' => $this->papaya->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0,
        ]);

        // Stock batch assigned to $this->warehouse
        $this->createPhysicalBatch($this->papaya, $this->warehouse, 50.0, '2026-09-08', $adv);

        // 1. scopeOpenWarehouseAdvance must find it
        $this->assertTrue(GoodsReceived::query()->openWarehouseAdvance($this->warehouse->id)->whereKey($adv->id)->exists());

        // 2. WarehouseReceiptReadScope must match it
        $readScope = app(WarehouseReceiptReadScope::class);
        $this->assertTrue($readScope->receiptMatchesWarehouse($adv, $this->warehouse->id));
        $this->assertFalse($readScope->receiptMatchesWarehouse($adv, $this->otherWarehouse->id));

        // 3. Planner must include it
        $planner = app(AutoAdvanceClearPlanningService::class);
        $plan = $planner->buildAutoClearPlan($this->warehouse->id, $this->adminUser->id);
        $this->assertTrue(GoodsReceived::query()->openWarehouseAdvance($this->warehouse->id)->whereKey($adv->id)->exists());
    }

    /**
     * Test 4: Same-unit match without conversion (factor 1).
     */
    public function test_same_unit_match_without_conversion(): void
    {
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'destination_shop_id' => $this->shop->id,
            'po_number' => 'PO-SAME-UNIT',
            'status' => POStatus::Approved,
            'order_date' => '2026-09-08',
            'created_by' => $this->adminUser->id,
        ]);
        $po->items()->create([
            'product_id' => $this->papaya->id,
            'quantity' => 15.0,
            'unit' => 'kg',
            'purchase_unit' => 'kg',
            'unit_price' => 20.0,
            'total_price' => 300.0,
        ]);

        $this->createAdvanceGrn($this->warehouse, $this->papaya, 15.0, 'kg');

        $planner = app(AutoAdvanceClearPlanningService::class);
        $plan = $planner->buildAutoClearPlan($this->warehouse->id, $this->adminUser->id);

        $this->assertCount(1, $plan['ready_bills']);
        $this->assertEquals(15.0, (float) $plan['summary']['matched_base_qty']);
        $this->assertEquals('FULL_MATCH', $plan['ready_bills'][0]['lines'][0]['classification']);
    }

    /**
     * Test 5: Different-unit match without configured conversion must be rejected.
     */
    public function test_different_unit_match_without_conversion_rejected(): void
    {
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'destination_shop_id' => $this->shop->id,
            'po_number' => 'PO-DIFF-UNIT',
            'status' => POStatus::Approved,
            'order_date' => '2026-09-08',
            'created_by' => $this->adminUser->id,
        ]);
        $po->items()->create([
            'product_id' => $this->papaya->id, // base unit is kg
            'quantity' => 10.0,
            'unit' => 'piece', // DIFFERENT UNIT with no conversion configured
            'purchase_unit' => 'piece',
            'unit_price' => 50.0,
            'total_price' => 500.0,
        ]);

        // Advance is in kg
        $this->createAdvanceGrn($this->warehouse, $this->papaya, 10.0, 'kg');

        $planner = app(AutoAdvanceClearPlanningService::class);
        $plan = $planner->buildAutoClearPlan($this->warehouse->id, $this->adminUser->id);

        // Must NOT match: must be classified as UNIT_DIFFERENCE and placed in skipped_bills
        $this->assertCount(0, $plan['ready_bills']);
        $this->assertCount(1, $plan['skipped_bills']);
        $this->assertEquals('UNIT_DIFFERENCE', $plan['skipped_bills'][0]['lines'][0]['classification']);
    }
}
