<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

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
use App\Services\Purchasing\DailyAdvanceMatchPlanningService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyAdvanceMatchPlanningTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Warehouse $warehouseA;

    private Warehouse $warehouseB;

    private Product $apple;

    private Product $banana;

    private Supplier $supplier;

    private Shop $shop;

    private DailyAdvanceMatchPlanningService $planningService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

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

        $this->apple = Product::factory()->create([
            'name' => 'Fresh Apple',
            'sku' => 'APP-001',
            'default_warehouse_id' => $this->warehouseA->id,
            'unit' => 'kg',
        ]);

        $this->banana = Product::factory()->create([
            'name' => 'Fresh Banana',
            'sku' => 'BAN-001',
            'default_warehouse_id' => $this->warehouseA->id,
            'unit' => 'kg',
        ]);

        $this->supplier = Supplier::factory()->create(['name' => 'Global Farms Ltd']);
        $this->shop = Shop::factory()->create([
            'name' => 'Downtown Retail Shop',
        ]);

        $this->planningService = app(DailyAdvanceMatchPlanningService::class);
    }

    public function test_scoped_to_single_warehouse_and_selected_bill_date(): void
    {
        $selectedDate = '2026-09-11';
        $otherDate = '2026-09-10';

        // 1. Advance on selected date in warehouse A
        $advanceGrn = $this->createAdvanceGrn($this->warehouseA, $this->apple, 100.0, $selectedDate);

        // 2. Bill on selected date in warehouse A
        $billGrn1 = $this->createBillGrn($this->warehouseA, $this->apple, 40.0, $selectedDate);

        // 3. Bill on DIFFERENT date in warehouse A
        $billGrn2 = $this->createBillGrn($this->warehouseA, $this->apple, 30.0, $otherDate);

        // 4. Bill on selected date in warehouse B (other warehouse)
        $billGrn3 = $this->createBillGrn($this->warehouseB, $this->apple, 20.0, $selectedDate);

        $plan = $this->planningService->buildDailyPlan($this->warehouseA->id, $selectedDate, null, 100, $this->adminUser->id);

        $this->assertSame($this->warehouseA->id, $plan['warehouse_id']);
        $this->assertSame($selectedDate, $plan['bill_date']);
        $this->assertSame(1, $plan['summary']['total_bills_on_date']);
        $this->assertSame(1, $plan['summary']['ready_bills']);
        $this->assertSame(40.0, (float) $plan['summary']['matched_base_qty']);
        $this->assertCount(1, $plan['ready_bills']);
        $this->assertSame($billGrn1->id, $plan['ready_bills'][0]['goods_received_id']);
    }

    public function test_advances_received_before_or_on_selected_date_allowed_future_advances_blocked(): void
    {
        $selectedDate = '2026-09-11';
        $pastDate = '2026-09-08';
        $futureDate = '2026-09-12';

        // Advance 1: past date -> eligible
        $pastAdvance = $this->createAdvanceGrn($this->warehouseA, $this->apple, 50.0, $pastDate);

        // Advance 2: future date -> NOT eligible for selectedDate matching
        $futureAdvance = $this->createAdvanceGrn($this->warehouseA, $this->apple, 100.0, $futureDate);

        // Bill on selected date for 70 kg
        $billGrn = $this->createBillGrn($this->warehouseA, $this->apple, 70.0, $selectedDate);

        $plan = $this->planningService->buildDailyPlan($this->warehouseA->id, $selectedDate, null, 100, $this->adminUser->id);

        // Should match only 50kg from pastAdvance, leaving 20kg partial remainder
        $this->assertSame(1, $plan['summary']['partial_bills']);
        $this->assertSame(50.0, (float) $plan['summary']['matched_base_qty']);
        $this->assertCount(1, $plan['partial_bills']);
        $this->assertSame(20.0, (float) $plan['partial_bills'][0]['remaining_base_qty']);

        // Open advances in plan should ONLY include pastAdvance
        $this->assertCount(1, $plan['open_advances']);
        $this->assertSame($pastAdvance->id, $plan['open_advances'][0]['id']);
    }

    public function test_same_product_required_no_cross_product_matching(): void
    {
        $selectedDate = '2026-09-11';

        // Advance for Banana only
        $this->createAdvanceGrn($this->warehouseA, $this->banana, 100.0, $selectedDate);

        // Bill for Apple
        $billGrn = $this->createBillGrn($this->warehouseA, $this->apple, 50.0, $selectedDate);

        $plan = $this->planningService->buildDailyPlan($this->warehouseA->id, $selectedDate, null, 100, $this->adminUser->id);

        $this->assertSame(0, $plan['summary']['ready_bills']);
        $this->assertSame(0, $plan['summary']['partial_bills']);
        $this->assertSame(1, $plan['summary']['blocked_bills']);
        $this->assertSame('NO_ADVANCE', $plan['blocked_bills'][0]['blocked_reason']);
    }

    public function test_different_unit_without_conversion_is_blocked_with_conversion_matches(): void
    {
        $selectedDate = '2026-09-11';

        // Product with unit 'kg'
        $carrot = Product::factory()->create([
            'name' => 'Fresh Carrot',
            'sku' => 'CAR-001',
            'default_warehouse_id' => $this->warehouseA->id,
            'unit' => 'kg',
        ]);

        // Advance with unit 'box' (no ProductUnit conversion yet)
        $this->createAdvanceGrnWithUnit($this->warehouseA, $carrot, 10.0, 'box', $selectedDate);

        // Bill with unit 'kg' for 50 kg
        $billGrn = $this->createBillGrn($this->warehouseA, $carrot, 50.0, $selectedDate);

        // Plan without conversion -> blocked
        $plan1 = $this->planningService->buildDailyPlan($this->warehouseA->id, $selectedDate, null, 100, $this->adminUser->id);
        $this->assertSame(1, $plan1['summary']['blocked_bills']);
        $this->assertSame('UNIT_DIFFERENCE_REQUIRES_CONVERSION', $plan1['blocked_bills'][0]['blocked_reason']);

        // Now save unit conversion: 1 box = 10 kg
        ProductUnit::create([
            'product_id' => $carrot->id,
            'unit' => 'box',
            'label' => 'Box (10kg)',
            'conversion_to_base' => 10.0,
            'is_orderable' => true,
        ]);

        // Plan with conversion -> matches 50 kg (which uses 5 boxes)
        $plan2 = $this->planningService->buildDailyPlan($this->warehouseA->id, $selectedDate, null, 100, $this->adminUser->id);
        $this->assertSame(1, $plan2['summary']['ready_bills']);
        $this->assertSame(50.0, (float) $plan2['summary']['matched_base_qty']);
    }

    public function test_cursor_pagination_and_next_cursor(): void
    {
        $selectedDate = '2026-09-11';

        // Advance with 500kg
        $this->createAdvanceGrn($this->warehouseA, $this->apple, 500.0, $selectedDate);

        // Create 3 bills of 10kg each
        $bill1 = $this->createBillGrn($this->warehouseA, $this->apple, 10.0, $selectedDate);
        $bill2 = $this->createBillGrn($this->warehouseA, $this->apple, 10.0, $selectedDate);
        $bill3 = $this->createBillGrn($this->warehouseA, $this->apple, 10.0, $selectedDate);

        // Batch size of 2
        $planPage1 = $this->planningService->buildDailyPlan($this->warehouseA->id, $selectedDate, null, 2, $this->adminUser->id);

        $this->assertSame(3, $planPage1['summary']['total_bills_on_date']);
        $this->assertSame(2, $planPage1['summary']['batch_bills_count']);
        $this->assertSame(2, $planPage1['summary']['ready_bills']);
        $this->assertSame($bill2->id, $planPage1['next_cursor']);

        // Fetch page 2 using cursor
        $planPage2 = $this->planningService->buildDailyPlan($this->warehouseA->id, $selectedDate, $planPage1['next_cursor'], 2, $this->adminUser->id);

        $this->assertSame(3, $planPage2['summary']['total_bills_on_date']);
        $this->assertSame(1, $planPage2['summary']['batch_bills_count']);
        $this->assertSame(1, $planPage2['summary']['ready_bills']);
        $this->assertSame($bill3->id, $planPage2['ready_bills'][0]['goods_received_id']);
        $this->assertNull($planPage2['next_cursor']);
    }

    public function test_inventory_without_bills_grouping_and_age_calculation(): void
    {
        $selectedDate = '2026-09-11';
        $pastDate = '2026-09-06'; // 5 days old

        $adv = $this->createAdvanceGrn($this->warehouseA, $this->apple, 150.0, $pastDate);

        $plan = $this->planningService->buildDailyPlan($this->warehouseA->id, $selectedDate, null, 100, $this->adminUser->id);

        $this->assertCount(1, $plan['inventory_without_bills']);
        $unbilled = $plan['inventory_without_bills'][0];
        $this->assertSame($this->apple->id, $unbilled['product_id']);
        $this->assertSame(150.0, (float) $unbilled['received_qty']);
        $this->assertSame(0.0, (float) $unbilled['matched_qty']);
        $this->assertSame(150.0, (float) $unbilled['remaining_qty']);
        $this->assertSame(5, $unbilled['age_days']);
    }

    private function createAdvanceGrn(Warehouse $warehouse, Product $product, float $qty, string $date): GoodsReceived
    {
        return $this->createAdvanceGrnWithUnit($warehouse, $product, $qty, $product->unit, $date);
    }

    private function createAdvanceGrnWithUnit(Warehouse $warehouse, Product $product, float $qty, string $unit, string $date): GoodsReceived
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'ADV-'.uniqid(),
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $this->supplier->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_at' => $date,
            'received_by' => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
        ]);

        $item = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $unit,
            'unit_price' => 50.0,
            'total_price' => $qty * 50.0,
            'variance' => 0.0,
        ]);

        StockBatch::create([
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'reference' => 'BATCH-'.uniqid(),
            'total_kg' => $qty,
            'cost_per_kg' => 50.0,
            'quantity' => $qty,
            'current_quantity' => $qty,
            'received_at' => $date,
            'warehouse_receive_pending' => false,
            'status' => 'pending',
            'created_by' => $this->adminUser->id,
        ]);

        return $grn;
    }

    private function createBillGrn(Warehouse $warehouse, Product $product, float $qty, string $date): GoodsReceived
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-'.uniqid(),
            'warehouse_id' => $warehouse->id,
            'destination_shop_id' => $this->shop->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'order_date' => $date,
            'created_by' => $this->adminUser->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'unit_price' => 50.0,
            'total_price' => $qty * 50.0,
            'unit' => $product->unit,
        ]);

        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-'.uniqid(),
            'purchase_order_id' => $po->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $this->supplier->id,
            'receipt_type' => 'normal',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_at' => $date,
            'received_by' => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
        ]);

        $item = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => $product->unit,
            'unit_price' => 50.0,
            'total_price' => $qty * 50.0,
            'variance' => 0.0,
        ]);

        StockBatch::create([
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'reference' => 'BATCH-'.uniqid(),
            'total_kg' => $qty,
            'cost_per_kg' => 50.0,
            'quantity' => $qty,
            'current_quantity' => $qty,
            'received_at' => $date,
            'warehouse_receive_pending' => false,
            'status' => 'pending',
            'created_by' => $this->adminUser->id,
        ]);

        return $grn;
    }
}
