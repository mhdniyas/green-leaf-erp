<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Purchasing\POStatus;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\StockBatch;
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
use Tests\TestCase;

class AdvanceBillMatchingParityTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Warehouse $warehouse;

    private Shop $shop;

    private Supplier $supplier;

    private Product $tomato;

    private AdvanceReceiveReconciliationService $reconService;

    private AutoAdvanceClearPlanningService $plannerService;

    private AutoAdvanceClearExecutionService $execService;

    private AdvanceAvailableBalanceCalculator $balanceCalculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->warehouse = Warehouse::create([
            'name' => 'Vegetable Warehouse',
            'code' => 'WH-VEG',
            'is_active' => true,
        ]);

        $this->adminUser->warehouses()->attach([$this->warehouse->id]);

        $this->shop = Shop::factory()->create(['name' => 'Central Shop']);
        $this->supplier = Supplier::factory()->create(['name' => 'Green Farmer']);
        $category = Category::factory()->create();

        $this->tomato = Product::factory()->create([
            'name' => 'Tomato',
            'sku' => 'TOM-01',
            'unit' => 'kg',
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'is_active' => true,
        ]);

        $this->reconService = app(AdvanceReceiveReconciliationService::class);
        $this->plannerService = app(AutoAdvanceClearPlanningService::class);
        $this->execService = app(AutoAdvanceClearExecutionService::class);
        $this->balanceCalculator = app(AdvanceAvailableBalanceCalculator::class);
    }

    private function createAdvanceGrn(Product $product, float $qty, string $unit = 'kg', string $date = '2026-09-08'): GoodsReceived
    {
        $advGrn = GoodsReceived::create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'grn_number' => 'ADV-'.Str::upper(Str::random(6)),
            'supplier_id' => $this->supplier->id,
            'received_at' => $date,
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'created_by' => $this->adminUser->id,
            'received_by' => $this->adminUser->id,
            'total_amount' => 0,
        ]);

        $advItem = GoodsReceivedItem::create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $unit,
            'unit_price' => 10.0,
            'subtotal' => $qty * 10.0,
            'variance' => 0.0,
        ]);

        StockBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $advGrn->id,
            'goods_received_item_id' => $advItem->id,
            'reference' => 'BATCH-ADV-'.Str::upper(Str::random(6)),
            'total_kg' => $qty,
            'available_kg' => $qty,
            'cost_per_kg' => 10.0,
            'received_at' => $date,
            'created_by' => $this->adminUser->id,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->adminUser->id,
            'status' => 'pending',
        ]);

        return $advGrn->fresh(['items', 'stockBatches']);
    }

    private function createPendingBillGrn(Product $product, float $qty, string $unit = 'kg', string $date = '2026-09-09'): GoodsReceived
    {
        $po = PurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'shop_id' => $this->shop->id,
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-'.Str::upper(Str::random(6)),
            'order_date' => $date,
            'status' => POStatus::Approved,
            'created_by' => $this->adminUser->id,
            'total_amount' => $qty * 10.0,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'unit_price' => 10.0,
            'subtotal' => $qty * 10.0,
            'unit' => $unit,
        ]);

        $billGrn = GoodsReceived::create([
            'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => $po->id,
            'receipt_type' => 'purchaser_bill',
            'grn_number' => 'BILL-'.Str::upper(Str::random(6)),
            'supplier_id' => $this->supplier->id,
            'received_at' => $date,
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'created_by' => $this->adminUser->id,
            'received_by' => $this->adminUser->id,
            'total_amount' => $qty * 10.0,
        ]);

        $billItem = GoodsReceivedItem::create([
            'goods_received_id' => $billGrn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $unit,
            'unit_price' => 10.0,
            'subtotal' => $qty * 10.0,
            'variance' => 0.0,
        ]);

        StockBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $billGrn->id,
            'goods_received_item_id' => $billItem->id,
            'reference' => 'BATCH-BILL-'.Str::upper(Str::random(6)),
            'total_kg' => $qty,
            'available_kg' => $qty,
            'cost_per_kg' => 10.0,
            'received_at' => $date,
            'created_by' => $this->adminUser->id,
            'warehouse_receive_pending' => true,
            'status' => 'pending',
        ]);

        return $billGrn->fresh(['items.purchaseOrderItem', 'purchaseOrder', 'stockBatches']);
    }

    /**
     * INVARIANT 1:
     * Advance = 4, Bill = 12
     * Advance physical stock already added = 4
     * Matched qty = 4
     * New physical receive = 8
     * Final physical stock contribution = 12 (never 16).
     */
    public function test_stock_safety_invariant_advance_4_bill_12(): void
    {
        $advGrn = $this->createAdvanceGrn($this->tomato, 4.0);
        $billGrn = $this->createPendingBillGrn($this->tomato, 12.0);

        // Pre-reconciliation physical stock from advance
        $initialPhysicalStock = StockBatch::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->tomato->id)
            ->where('warehouse_receive_pending', false)
            ->sum('total_kg');
        $this->assertEquals(4.0, $initialPhysicalStock);

        // Run auto match execution
        $plan = $this->plannerService->buildAutoClearPlan($this->warehouse->id, $this->adminUser->id);
        $this->assertEquals(1, $plan['summary']['ready_bills']);
        $this->assertEquals(4.0, $plan['summary']['matched_base_qty']);

        $executionResult = $this->execService->execute($this->warehouse->id, $plan['plan_hash'], (string) Str::uuid(), $this->adminUser->id);
        $this->assertEquals(1, $executionResult['summary']['processed']);
        $this->assertEquals(4.0, $executionResult['summary']['matched_base_qty']);

        // Post-reconciliation physical stock
        $confirmedPhysicalStock = StockBatch::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->tomato->id)
            ->where('warehouse_receive_pending', false)
            ->sum('total_kg');

        $totalPhysicalStockBatches = StockBatch::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->tomato->id)
            ->sum('total_kg');

        // New receive batch confirmed for the remainder 8 kg
        $billBatch = StockBatch::where('goods_received_id', $billGrn->id)->first();
        $this->assertNotNull($billBatch);
        $this->assertEquals(8.0, $billBatch->total_kg);
        $this->assertTrue((bool) $billBatch->warehouse_receive_pending);

        // Advance stock already confirmed is 4.0
        $this->assertEquals(4.0, $confirmedPhysicalStock);
        // Total physical contribution across all batches must be exactly 12.0 (4 from advance + 8 new), NEVER 16.0
        $this->assertEquals(12.0, $totalPhysicalStockBatches);
        $this->assertNotEquals(16.0, $totalPhysicalStockBatches);

        // Advance GRN is now fully cleared (bill_available)
        $this->assertEquals('bill_available', $advGrn->fresh()->bill_status);

        // Bill GRN has 8.0 kg pending remainder physical receipt
        $this->assertEquals('bill_pending', $billGrn->fresh()->bill_status);
    }

    /**
     * INVARIANT 2:
     * Advance = 12, Bill = 12
     * Matched qty = 12
     * New receive = 0
     * Final physical stock contribution = 12.
     */
    public function test_stock_safety_invariant_advance_12_bill_12(): void
    {
        $advGrn = $this->createAdvanceGrn($this->tomato, 12.0);
        $billGrn = $this->createPendingBillGrn($this->tomato, 12.0);

        $plan = $this->plannerService->buildAutoClearPlan($this->warehouse->id, $this->adminUser->id);
        $this->assertEquals(1, $plan['summary']['ready_bills']);
        $this->assertEquals(12.0, $plan['summary']['matched_base_qty']);

        $executionResult = $this->execService->execute($this->warehouse->id, $plan['plan_hash'], (string) Str::uuid(), $this->adminUser->id);
        $this->assertEquals(1, $executionResult['summary']['processed']);

        $finalPhysicalStock = StockBatch::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->tomato->id)
            ->where('warehouse_receive_pending', false)
            ->sum('total_kg');

        // Total physical stock remains 12.0 (no extra batch added)
        $this->assertEquals(12.0, $finalPhysicalStock);

        $billBatch = StockBatch::where('goods_received_id', $billGrn->id)->first();
        $this->assertNotNull($billBatch);
        $this->assertEquals(0.0, (float) $billBatch->total_kg);

        $this->assertEquals('bill_available', $advGrn->fresh()->bill_status);
        $this->assertEquals('bill_available', $billGrn->fresh()->bill_status);
    }

    /**
     * INVARIANT 3:
     * Advance = 20, Bill = 12
     * Match = 12
     * Advance remaining = 8
     * New receive = 0
     * Final physical stock contribution = 20.
     */
    public function test_stock_safety_invariant_advance_20_bill_12(): void
    {
        $advGrn = $this->createAdvanceGrn($this->tomato, 20.0);
        $billGrn = $this->createPendingBillGrn($this->tomato, 12.0);

        $plan = $this->plannerService->buildAutoClearPlan($this->warehouse->id, $this->adminUser->id);
        $this->assertEquals(1, $plan['summary']['ready_bills']);
        $this->assertEquals(12.0, $plan['summary']['matched_base_qty']);

        $this->execService->execute($this->warehouse->id, $plan['plan_hash'], (string) Str::uuid(), $this->adminUser->id);

        $finalPhysicalStock = StockBatch::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->tomato->id)
            ->where('warehouse_receive_pending', false)
            ->sum('total_kg');

        // Total physical stock is 20.0 (from advance)
        $this->assertEquals(20.0, $finalPhysicalStock);

        // Advance remaining calculated via canonical calculator is 8.0
        $advRemaining = $this->balanceCalculator->calculateItemAvailableBase($advGrn);
        $advItemId = $advGrn->items->first()->id;
        $this->assertEquals(8.0, (float) $advRemaining[$advItemId]);

        // Advance GRN is still partially open (bill_pending)
        $this->assertEquals('bill_pending', $advGrn->fresh()->bill_status);
        // Bill GRN is fully reconciled (bill_available)
        $this->assertEquals('bill_available', $billGrn->fresh()->bill_status);
    }

    /**
     * PARITY: Manual Match Preview vs Auto Match Preview vs Auto Match Execution
     */
    public function test_parity_full_match(): void
    {
        $advGrn = $this->createAdvanceGrn($this->tomato, 10.0);
        $billGrn = $this->createPendingBillGrn($this->tomato, 10.0);

        // 1. Manual Match Candidate Suggestions
        $manualSuggestions = $this->reconService->getSuggestionsForGrn($billGrn->fresh());
        $this->assertCount(1, $manualSuggestions['items'][0]['suggested_matches']);
        $this->assertEquals(10.0, $manualSuggestions['items'][0]['suggested_matches'][0]['matched_qty']);

        // 2. Auto Match Preview
        $plan = $this->plannerService->buildAutoClearPlan($this->warehouse->id, $this->adminUser->id);
        $this->assertEquals(1, $plan['summary']['ready_bills']);
        $this->assertEquals(10.0, $plan['summary']['matched_base_qty']);

        // 3. Auto Match Execution
        $exec = $this->execService->execute($this->warehouse->id, $plan['plan_hash'], (string) Str::uuid(), $this->adminUser->id);
        $this->assertEquals(1, $exec['summary']['processed']);
        $this->assertEquals(10.0, $exec['summary']['matched_base_qty']);

        $this->assertEquals('bill_available', $billGrn->fresh()->bill_status);
        $this->assertEquals('bill_available', $advGrn->fresh()->bill_status);
    }

    public function test_parity_partial_match_multiple_advances(): void
    {
        $adv1 = $this->createAdvanceGrn($this->tomato, 5.0, 'kg', '2026-09-07');
        $adv2 = $this->createAdvanceGrn($this->tomato, 6.0, 'kg', '2026-09-08');
        $bill = $this->createPendingBillGrn($this->tomato, 15.0, 'kg', '2026-09-09');

        // Manual candidate suggestions
        $manualSuggestions = $this->reconService->getSuggestionsForGrn($bill->fresh());
        $this->assertCount(2, $manualSuggestions['items'][0]['suggested_matches']);
        $manualMatchedTotal = array_sum(array_column($manualSuggestions['items'][0]['suggested_matches'], 'matched_qty'));
        $this->assertEquals(11.0, $manualMatchedTotal);

        // Auto Match Preview
        $plan = $this->plannerService->buildAutoClearPlan($this->warehouse->id, $this->adminUser->id);
        $this->assertEquals(1, $plan['summary']['ready_bills']);
        $this->assertEquals(11.0, $plan['summary']['matched_base_qty']);

        // Auto Match Execution
        $exec = $this->execService->execute($this->warehouse->id, $plan['plan_hash'], (string) Str::uuid(), $this->adminUser->id);
        $this->assertEquals(11.0, $exec['summary']['matched_base_qty']);

        // Bill has 4.0 remainder -> StockBatch for 4.0 kg
        $billBatch = StockBatch::where('goods_received_id', $bill->id)->first();
        $this->assertNotNull($billBatch);
        $this->assertEquals(4.0, $billBatch->total_kg);

        // Total physical stock batches: 5 + 6 + 4 = 15 kg
        $totalPhysicalStock = StockBatch::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->tomato->id)
            ->sum('total_kg');
        $this->assertEquals(15.0, $totalPhysicalStock);
    }

    public function test_parity_multiple_bill_grns_consuming_one_advance(): void
    {
        $adv = $this->createAdvanceGrn($this->tomato, 10.0, 'kg', '2026-09-07');
        $bill1 = $this->createPendingBillGrn($this->tomato, 6.0, 'kg', '2026-09-08');
        $bill2 = $this->createPendingBillGrn($this->tomato, 8.0, 'kg', '2026-09-09');

        $plan = $this->plannerService->buildAutoClearPlan($this->warehouse->id, $this->adminUser->id);
        $this->assertEquals(2, $plan['summary']['ready_bills']);
        $this->assertEquals(10.0, $plan['summary']['matched_base_qty']);

        $exec = $this->execService->execute($this->warehouse->id, $plan['plan_hash'], (string) Str::uuid(), $this->adminUser->id);
        $this->assertEquals(10.0, $exec['summary']['matched_base_qty']);

        // Bill1 is 100% matched -> 0 kg batch
        $bill1Batch = StockBatch::where('goods_received_id', $bill1->id)->first();
        $this->assertNotNull($bill1Batch);
        $this->assertEquals(0.0, (float) $bill1Batch->total_kg);

        // Bill2 is partially matched (4/8) -> new batch of 4.0 kg
        $bill2Batch = StockBatch::where('goods_received_id', $bill2->id)->first();
        $this->assertNotNull($bill2Batch);
        $this->assertEquals(4.0, $bill2Batch->total_kg);

        // Total physical stock batches: 10 (adv) + 4 (bill2 remainder) = 14 kg
        $totalPhysicalStock = StockBatch::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->tomato->id)
            ->sum('total_kg');
        $this->assertEquals(14.0, $totalPhysicalStock);
    }

    public function test_parity_unit_difference_without_conversion_fails_gracefully(): void
    {
        // Tomato in 'box' when product default unit is 'kg' and no ProductUnit configured
        $adv = $this->createAdvanceGrn($this->tomato, 5.0, 'box', '2026-09-08');
        $bill = $this->createPendingBillGrn($this->tomato, 5.0, 'kg', '2026-09-09');

        // Auto Match Preview must NOT use loose conversion box -> kg = 1
        $plan = $this->plannerService->buildAutoClearPlan($this->warehouse->id, $this->adminUser->id);
        $this->assertEquals(0, $plan['summary']['ready_bills']);

        // Manual match suggestions should also reject mismatched unit without conversion
        $manualSuggestions = $this->reconService->getSuggestionsForGrn($bill->fresh());
        $this->assertCount(0, $manualSuggestions['items'][0]['suggested_matches']);
    }

    public function test_parity_configured_product_unit_conversion(): void
    {
        // Configure 1 box = 10 kg
        ProductUnit::create([
            'product_id' => $this->tomato->id,
            'unit' => 'box',
            'label' => 'Box 10kg',
            'conversion_to_base' => 10.0,
            'is_base' => false,
            'is_orderable' => true,
        ]);

        // Advance: 1 box (= 10 kg)
        $adv = $this->createAdvanceGrn($this->tomato, 1.0, 'box', '2026-09-08');
        // Bill: 10 kg
        $bill = $this->createPendingBillGrn($this->tomato, 10.0, 'kg', '2026-09-09');

        $plan = $this->plannerService->buildAutoClearPlan($this->warehouse->id, $this->adminUser->id);
        $this->assertEquals(1, $plan['summary']['ready_bills']);
        $this->assertEquals(10.0, $plan['summary']['matched_base_qty']);

        $exec = $this->execService->execute($this->warehouse->id, $plan['plan_hash'], (string) Str::uuid(), $this->adminUser->id);
        $this->assertEquals(10.0, $exec['summary']['matched_base_qty']);

        $this->assertEquals('bill_available', $adv->fresh()->bill_status);
        $this->assertEquals('bill_available', $bill->fresh()->bill_status);
    }

    public function test_parity_already_partially_matched_bill(): void
    {
        $adv1 = $this->createAdvanceGrn($this->tomato, 5.0, 'kg', '2026-09-07');
        $bill = $this->createPendingBillGrn($this->tomato, 12.0, 'kg', '2026-09-09');

        // Reconcile 5 kg first manually
        $billItem = $bill->items->first();
        $this->reconService->reconcileExistingGrn(
            $bill->fresh(),
            [
                $billItem->id => [
                    'received_qty' => 12.0,
                    'received_unit' => 'kg',
                ],
            ],
            [
                [
                    'goods_received_item_id' => $billItem->id,
                    'advance_goods_received_id' => $adv1->id,
                    'advance_goods_received_item_id' => $adv1->items->first()->id,
                    'product_id' => $this->tomato->id,
                    'matched_qty' => 5.0,
                    'unit' => 'kg',
                    'base_qty' => 5.0,
                ],
            ],
            $this->warehouse->id,
            $this->adminUser->id,
            true
        );

        // Bill remaining is 7 kg
        $billRemaining = $this->balanceCalculator->calculateBillItemRemainingBase($billItem->fresh());
        $this->assertEquals(7.0, $billRemaining);

        // Now create another advance of 10 kg
        $adv2 = $this->createAdvanceGrn($this->tomato, 10.0, 'kg', '2026-09-08');

        // Auto Match Preview should match remaining 7.0 kg of the bill with adv2
        $plan = $this->plannerService->buildAutoClearPlan($this->warehouse->id, $this->adminUser->id);
        $this->assertEquals(1, $plan['summary']['ready_bills']);
        $this->assertEquals(7.0, $plan['summary']['matched_base_qty']);

        // Execute
        $exec = $this->execService->execute($this->warehouse->id, $plan['plan_hash'], (string) Str::uuid(), $this->adminUser->id);
        $this->assertEquals(7.0, $exec['summary']['matched_base_qty']);

        // Bill is now fully cleared
        $this->assertEquals('bill_available', $bill->fresh()->bill_status);
        // Adv2 has 3.0 kg remaining
        $adv2Balances = $this->balanceCalculator->calculateItemAvailableBase($adv2);
        $this->assertEquals(3.0, (float) $adv2Balances[$adv2->items->first()->id]);
    }
}
