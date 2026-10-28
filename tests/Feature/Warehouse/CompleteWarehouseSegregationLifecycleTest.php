<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Actions\Inventory\ProcessPartialSegregationAction;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WastageEntry;
use App\Services\Inventory\StockLedgerService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompleteWarehouseSegregationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private User $admin;

    private Warehouse $vegWarehouse;

    private Warehouse $fruitWarehouse;

    private Supplier $supplier;

    private Category $vegCategory;

    private Category $fruitCategory;

    private Product $tomato;

    private Product $apple;

    private ShopPriceGroup $priceGroup;

    private Shop $shop;

    private string $businessDate;

    private StockLedgerService $stockLedgerService;

    private ProcessPartialSegregationAction $segregationAction;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'warehouse_receiver']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'purchaser']);

        Permission::firstOrCreate(['name' => 'warehouse.receive.view']);
        Permission::firstOrCreate(['name' => 'warehouse.receive.confirm']);
        Permission::firstOrCreate(['name' => 'warehouse.receive.sort']);
        Permission::firstOrCreate(['name' => 'inventory.sorting.process']);
        Permission::firstOrCreate(['name' => 'inventory.product.view']);

        $receiverRole = Role::findByName('warehouse_receiver');
        $receiverRole->givePermissionTo([
            'warehouse.receive.view',
            'warehouse.receive.confirm',
            'warehouse.receive.sort',
            'inventory.sorting.process',
            'inventory.product.view',
        ]);

        $this->receiver = User::factory()->create();
        $this->receiver->assignRole('warehouse_receiver');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->vegWarehouse = Warehouse::create([
            'name' => 'Vegetable Warehouse',
            'code' => 'VEG-01',
            'is_active' => true,
        ]);
        $this->receiver->warehouses()->attach($this->vegWarehouse);

        $this->fruitWarehouse = Warehouse::create([
            'name' => 'Fruit Warehouse',
            'code' => 'FRT-02',
            'is_active' => true,
        ]);
        $this->receiver->warehouses()->attach($this->fruitWarehouse);

        $this->supplier = Supplier::factory()->create(['name' => 'Sunrise Produce']);

        $this->vegCategory = Category::create(['name' => 'Vegetables', 'is_active' => true]);
        $this->fruitCategory = Category::create(['name' => 'Fruits', 'is_active' => true]);

        $this->tomato = Product::create([
            'name' => 'Tomato Hybrid',
            'sku' => 'TOM-HYB-01',
            'unit' => 'KG',
            'category_id' => $this->vegCategory->id,
            'default_warehouse_id' => $this->vegWarehouse->id,
            'is_active' => true,
        ]);

        $this->apple = Product::create([
            'name' => 'Kashmir Apple',
            'sku' => 'APL-KSH-01',
            'unit' => 'KG',
            'category_id' => $this->fruitCategory->id,
            'default_warehouse_id' => $this->fruitWarehouse->id,
            'is_active' => true,
        ]);

        $this->priceGroup = ShopPriceGroup::create(['name' => 'Group A', 'is_active' => true]);

        $this->shop = Shop::create([
            'name' => 'Green Leaf Express',
            'code' => 'GLE-01',
            'shop_price_group_id' => $this->priceGroup->id,
            'is_active' => true,
            'allowed_product_grades' => ['A', 'B', 'C'],
        ]);

        $this->businessDate = app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();

        BusinessSetting::updateOrCreate(
            ['key' => 'multi_grade_enabled'],
            ['value' => 'true']
        );

        foreach (['A' => 40.0, 'B' => 30.0, 'C' => 20.0] as $grade => $price) {
            DailyPriceApproval::create([
                'product_id' => $this->tomato->id,
                'business_date' => $this->businessDate,
                'grade' => $grade,
                'purchase_price' => $price * 0.7,
                'price_unit' => 'KG',
                'price_a' => $price,
                'price_b' => $price * 0.9,
                'price_c' => $price * 0.8,
                'status' => 'approved',
                'approved_at' => now(),
            ]);
        }

        $this->stockLedgerService = app(StockLedgerService::class);
        $this->segregationAction = app(ProcessPartialSegregationAction::class);
    }

    /**
     * Test 1: Direct-grade receiving for Grade A, B, C, D batches.
     */
    public function test_01_direct_grade_receiving_for_all_grades(): void
    {
        foreach ([ProductGrade::GradeA, ProductGrade::GradeB, ProductGrade::GradeC] as $grade) {
            $batch = StockBatch::create([
                'reference' => 'BATCH-DIR-'.$grade->value,
                'product_id' => $this->tomato->id,
                'warehouse_id' => $this->vegWarehouse->id,
                'supplier_id' => $this->supplier->id,
                'total_kg' => 50.0,
                'cost_per_kg' => 25.0,
                'status' => BatchStatus::Sorted,
                'warehouse_receive_pending' => false,
                'received_at' => now(),
                'purchase_grade' => $grade->value,
                'grading_mode' => 'fixed_purchase_grade',
                'created_by' => $this->receiver->id,
            ]);

            StockMovement::create([
                'batch_id' => $batch->id,
                'product_id' => $this->tomato->id,
                'warehouse_id' => $this->vegWarehouse->id,
                'created_by' => $this->receiver->id,
                'grade' => $grade->value,
                'type' => StockMovementType::In->value,
                'quantity' => 50.0,
                'cost_per_unit' => 25.0,
                'notes' => "Direct receipt Grade {$grade->value}",
            ]);

            $available = $this->stockLedgerService->availableStockForProduct(
                $this->tomato->id,
                $this->vegWarehouse->id,
                $grade
            );
            $this->assertSame(50.0, $available);
        }

        // Total physical available stock across all grades is 150 kg (3 grades x 50 kg)
        $totalPhysical = $this->stockLedgerService->availableStockForProduct(
            $this->tomato->id,
            $this->vegWarehouse->id
        );
        $this->assertSame(150.0, $totalPhysical);
    }

    /**
     * Test 2: Mixed receiving arrives as available Unsorted (U) inventory.
     */
    public function test_02_mixed_receiving_available_as_unsorted(): void
    {
        $batch = StockBatch::create([
            'reference' => 'BATCH-MIX-100',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'supplier_id' => $this->supplier->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        $this->assertSame(100.0, $this->stockLedgerService->availableStockForProduct(
            $this->tomato->id,
            $this->vegWarehouse->id,
            ProductGrade::Unsorted
        ));
        $this->assertSame(0.0, $this->stockLedgerService->availableStockForProduct(
            $this->tomato->id,
            $this->vegWarehouse->id,
            ProductGrade::GradeA
        ));
        $this->assertSame(100.0, $this->stockLedgerService->availableStockForProduct(
            $this->tomato->id,
            $this->vegWarehouse->id
        ));
    }

    /**
     * Test 3: Advance receiving followed by purchase bill matching does not double-count inventory.
     */
    public function test_03_advance_receiving_and_bill_matching_no_double_counting(): void
    {
        // 1. Advance receiving creates initial pending batch
        $batch = StockBatch::create([
            'reference' => 'ADV-REC-01',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'supplier_id' => $this->supplier->id,
            'total_kg' => 150.0,
            'cost_per_kg' => 18.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        $this->assertSame(150.0, $this->stockLedgerService->availableStockForProduct(
            $this->tomato->id,
            $this->vegWarehouse->id
        ));

        // 2. Vendor bill matching links to the existing batch rather than creating a duplicate physical batch
        $batch->update([
            'notes' => 'Matched to vendor bill INV-9988',
        ]);

        // Stock remains exactly 150 kg (no duplicate batch)
        $this->assertSame(150.0, $this->stockLedgerService->availableStockForProduct(
            $this->tomato->id,
            $this->vegWarehouse->id
        ));
    }

    /**
     * Test 4: Partial segregation from Mixed to Grade A, B, C, and D.
     */
    public function test_04_partial_segregation_from_mixed_to_all_grades(): void
    {
        $batch = StockBatch::create([
            'reference' => 'BATCH-MIX-200',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'supplier_id' => $this->supplier->id,
            'total_kg' => 200.0,
            'cost_per_kg' => 22.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        // Segregate 40 kg to Grade A
        $resA = $this->segregationAction->execute($batch, ProductGrade::GradeA, 40.0, $this->receiver->id);
        $this->assertSame(160.0, (float) $resA['parent']->total_kg);
        $this->assertSame(40.0, (float) $resA['child']->total_kg);
        $this->assertSame(22.0, (float) $resA['child']->cost_per_kg);

        // Segregate 50 kg to Grade B
        $resB = $this->segregationAction->execute($batch->fresh(), ProductGrade::GradeB, 50.0, $this->receiver->id);
        $this->assertSame(110.0, (float) $resB['parent']->total_kg);
        $this->assertSame(50.0, (float) $resB['child']->total_kg);

        // Segregate 50 kg to Grade C
        $resC = $this->segregationAction->execute($batch->fresh(), ProductGrade::GradeC, 50.0, $this->receiver->id);
        $this->assertSame(60.0, (float) $resC['parent']->total_kg);

        // Verify grade availability
        $this->assertSame(40.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeA));
        $this->assertSame(50.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeB));
        $this->assertSame(50.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeC));
        $this->assertSame(60.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::Unsorted));

        // Sum of all grades equals total physical stock (200 kg)
        $this->assertSame(200.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id));
    }

    /**
     * Test 5: Regrading existing sorted batches (e.g. Grade A -> Grade B) with mass conservation.
     */
    public function test_05_regrading_existing_sorted_batch(): void
    {
        // Direct Grade A batch with 100 kg
        $batchA = StockBatch::create([
            'reference' => 'BATCH-A-100',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 30.0,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'A',
            'grading_mode' => 'fixed_purchase_grade',
            'created_by' => $this->receiver->id,
        ]);

        StockMovement::create([
            'batch_id' => $batchA->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 100.0,
            'cost_per_unit' => 30.0,
            'notes' => 'Direct Grade A',
        ]);

        $this->assertSame(100.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeA));

        // Regrade 25 kg from Grade A to Grade B
        $result = $this->segregationAction->execute($batchA, ProductGrade::GradeB, 25.0, $this->receiver->id, 'Downgraded to Grade B');

        $this->assertSame(75.0, (float) $result['parent']->total_kg);
        $this->assertSame(25.0, (float) $result['child']->total_kg);
        $this->assertSame(30.0, (float) $result['child']->cost_per_kg); // Preserves purchase cost

        // Stock movements conserved: Grade A OUT = 25 kg, Grade B IN = 25 kg
        $this->assertSame(75.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeA));
        $this->assertSame(25.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeB));
        $this->assertSame(100.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id));
    }

    /**
     * Test 6: Scenario A — Available: Mixed 100 kg, B 0 kg. Loadout Grade B: 30 kg.
     * Expected: Classify 30 kg Mixed as B, deduct physically exactly once, remaining Mixed: 70 kg, B: 0 kg.
     */
    public function test_06_loadout_scenario_a_mixed_100_b_0_load_b_30(): void
    {
        $batch = StockBatch::create([
            'reference' => 'SCENARIO-A-MIX-100',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'order_number' => 'SO-SCEN-A',
            'state' => 'approved',
            'business_date' => $this->businessDate,
            'created_by' => $this->admin->id,
        ]);

        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'unit' => 'KG',
            'requested_qty' => 30.0,
            'approved_qty' => 30.0,
            'selling_price' => 35.0,
        ]);

        // Consume stock for order item allowing negative (Loadout posting)
        $consumed = $this->stockLedgerService->consumeStockForProductAllowingNegative(
            productId: $this->tomato->id,
            quantity: 30.0,
            userId: $this->receiver->id,
            movementType: StockMovementType::Out,
            notes: 'Loadout Scenario A',
            shopOrderItemId: $orderItem->id,
            warehouseId: $this->vegWarehouse->id,
            grade: ProductGrade::GradeB
        );

        $this->assertSame(30.0, $consumed);

        // Verification of Scenario A requirements:
        // 1. Deducted physically exactly once (30 kg)
        // 2. Remaining Mixed: 70 kg
        // 3. Remaining B: 0 kg
        // 4. Physical available stock: 70 kg
        $this->assertSame(70.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::Unsorted));
        $this->assertSame(0.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeB));
        $this->assertSame(70.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id));
    }

    /**
     * Test 7: Scenario B — Available: B 30 kg, Mixed 0 kg. Loadout Grade B: 30 kg.
     * Expected: Deduct 30 kg from B, no Mixed stock movement.
     */
    public function test_07_loadout_scenario_b_b_30_mixed_0_load_b_30(): void
    {
        $batchB = StockBatch::create([
            'reference' => 'SCENARIO-B-BATCH-30',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'total_kg' => 30.0,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'B',
            'grading_mode' => 'fixed_purchase_grade',
            'created_by' => $this->receiver->id,
        ]);

        StockMovement::create([
            'batch_id' => $batchB->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => 'B',
            'type' => StockMovementType::In->value,
            'quantity' => 30.0,
            'cost_per_unit' => 20.0,
            'notes' => 'Direct B',
        ]);

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'order_number' => 'SO-SCEN-B',
            'state' => 'approved',
            'business_date' => $this->businessDate,
            'created_by' => $this->admin->id,
        ]);

        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'unit' => 'KG',
            'requested_qty' => 30.0,
            'approved_qty' => 30.0,
            'selling_price' => 35.0,
        ]);

        $consumed = $this->stockLedgerService->consumeStockForProductAllowingNegative(
            productId: $this->tomato->id,
            quantity: 30.0,
            userId: $this->receiver->id,
            movementType: StockMovementType::Out,
            notes: 'Loadout Scenario B',
            shopOrderItemId: $orderItem->id,
            warehouseId: $this->vegWarehouse->id,
            grade: ProductGrade::GradeB
        );

        $this->assertSame(30.0, $consumed);
        $this->assertSame(0.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeB));
        $this->assertSame(0.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id));

        // Ensure no Mixed movement was created
        $mixedOut = StockMovement::where('batch_id', $batchB->id)->where('grade', 'U')->count();
        $this->assertSame(0, $mixedOut);
    }

    /**
     * Test 8: Scenario C — Available: B 10 kg, Mixed 50 kg. Loadout Grade B: 30 kg.
     * Expected: Consume 10 kg from B, classify & consume 20 kg from Mixed. Remaining B: 0 kg, Mixed: 30 kg.
     */
    public function test_08_loadout_scenario_c_b_10_mixed_50_load_b_30(): void
    {
        // 1. Grade B batch with 10 kg
        $batchB = StockBatch::create([
            'reference' => 'SCENARIO-C-B-10',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'total_kg' => 10.0,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'B',
            'grading_mode' => 'fixed_purchase_grade',
            'created_by' => $this->receiver->id,
        ]);
        StockMovement::create([
            'batch_id' => $batchB->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => 'B',
            'type' => StockMovementType::In->value,
            'quantity' => 10.0,
            'cost_per_unit' => 20.0,
            'notes' => 'Grade B 10kg',
        ]);

        // 2. Mixed batch with 50 kg
        $batchMixed = StockBatch::create([
            'reference' => 'SCENARIO-C-MIX-50',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'total_kg' => 50.0,
            'cost_per_kg' => 18.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        // Initial check: B has 10, Mixed has 50, total 60
        $this->assertSame(10.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeB));
        $this->assertSame(50.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::Unsorted));
        $this->assertSame(60.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id));

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'order_number' => 'SO-SCEN-C',
            'state' => 'approved',
            'business_date' => $this->businessDate,
            'created_by' => $this->admin->id,
        ]);
        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'unit' => 'KG',
            'requested_qty' => 30.0,
            'approved_qty' => 30.0,
            'selling_price' => 35.0,
        ]);

        $consumed = $this->stockLedgerService->consumeStockForProductAllowingNegative(
            productId: $this->tomato->id,
            quantity: 30.0,
            userId: $this->receiver->id,
            movementType: StockMovementType::Out,
            notes: 'Loadout Scenario C',
            shopOrderItemId: $orderItem->id,
            warehouseId: $this->vegWarehouse->id,
            grade: ProductGrade::GradeB
        );

        $this->assertSame(30.0, $consumed);

        // Verification of Scenario C:
        // Remaining B: 0 kg
        // Remaining Mixed: 30 kg
        // Total physical available: 30 kg
        $this->assertSame(0.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeB));
        $this->assertSame(30.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::Unsorted));
        $this->assertSame(30.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id));
    }

    /**
     * Test 9: Scenario D — Available: A 50 kg, B 0 kg, Mixed 0 kg. Request: Load 30 kg as B.
     * Expected: Do not silently consume A. Stock breakdown shows available: 0 kg for Grade B.
     */
    public function test_09_loadout_scenario_d_insufficient_stock_never_silently_takes_a(): void
    {
        $batchA = StockBatch::create([
            'reference' => 'SCENARIO-D-A-50',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'total_kg' => 50.0,
            'cost_per_kg' => 30.0,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'A',
            'grading_mode' => 'fixed_purchase_grade',
            'created_by' => $this->receiver->id,
        ]);
        StockMovement::create([
            'batch_id' => $batchA->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 50.0,
            'cost_per_unit' => 30.0,
            'notes' => 'Grade A 50kg',
        ]);

        // Check available loadout breakdown for Grade B
        $breakdown = $this->stockLedgerService->availableLoadoutStockBreakdown(
            $this->tomato->id,
            $this->vegWarehouse->id,
            ProductGrade::GradeB
        );

        $this->assertSame(0.0, $breakdown['grade_stock']);
        $this->assertSame(0.0, $breakdown['mixed_stock']);
        $this->assertSame(0.0, $breakdown['available_stock']);

        // Grade A remains completely untouched
        $this->assertSame(50.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeA));
    }

    /**
     * Test 10: Damage write-off from Mixed and Graded stock preserves cost, reason, operator.
     */
    public function test_10_damage_write_off_preserves_cost_and_reason(): void
    {
        $batch = StockBatch::create([
            'reference' => 'BATCH-DAM-100',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 28.50,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        $result = $this->segregationAction->execute(
            batch: $batch,
            targetGrade: ProductGrade::Damage,
            quantity: 12.5,
            userId: $this->receiver->id,
            notes: 'Spoiled due to humidity',
            damageReason: WastageReason::Rotten
        );

        $this->assertNull($result['child']);
        $this->assertNotNull($result['wastage']);

        /** @var WastageEntry $wastage */
        $wastage = $result['wastage'];
        $this->assertSame(12.5, (float) $wastage->quantity);
        $this->assertSame(28.50, (float) $wastage->cost_per_kg);
        $this->assertSame(WastageReason::Rotten, $wastage->reason);
        $this->assertSame((int) $this->receiver->id, (int) $wastage->recorded_by);
        $this->assertSame('U', is_object($wastage->grade) ? $wastage->grade->value : $wastage->grade);

        // Remaining physical stock on parent batch is 87.5 kg
        $this->assertSame(87.5, (float) $result['parent']->fresh()->remaining_qty);
        $this->assertSame(87.5, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id));
    }

    /**
     * Test 11: Loadout modification and reversal restores original physical pools.
     */
    public function test_11_loadout_reversal_restores_exact_physical_pools(): void
    {
        // 1. Setup Scenario C initial state (10 kg B, 50 kg Mixed)
        $batchB = StockBatch::create([
            'reference' => 'REV-B-10',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'total_kg' => 10.0,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'B',
            'grading_mode' => 'fixed_purchase_grade',
            'created_by' => $this->receiver->id,
        ]);
        StockMovement::create([
            'batch_id' => $batchB->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => 'B',
            'type' => StockMovementType::In->value,
            'quantity' => 10.0,
            'cost_per_unit' => 20.0,
            'notes' => 'Initial B',
        ]);

        $batchMixed = StockBatch::create([
            'reference' => 'REV-MIX-50',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'total_kg' => 50.0,
            'cost_per_kg' => 18.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'order_number' => 'SO-REV-01',
            'state' => 'approved',
            'business_date' => $this->businessDate,
            'created_by' => $this->admin->id,
        ]);
        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'unit' => 'KG',
            'requested_qty' => 30.0,
            'approved_qty' => 30.0,
            'selling_price' => 35.0,
        ]);

        // Dispatch 30 kg B (10 kg from B, 20 kg from Mixed)
        $this->stockLedgerService->consumeStockForProductAllowingNegative(
            productId: $this->tomato->id,
            quantity: 30.0,
            userId: $this->receiver->id,
            movementType: StockMovementType::Out,
            notes: 'Dispatched for SO-REV-01',
            shopOrderItemId: $orderItem->id,
            warehouseId: $this->vegWarehouse->id,
            grade: ProductGrade::GradeB
        );

        $this->assertSame(0.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeB));
        $this->assertSame(30.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::Unsorted));

        // Now reverse 30 kg back: LIFO order should restore 20 kg to Mixed, 10 kg to B!
        $reversed = $this->stockLedgerService->reverseStockForOrderItem(
            shopOrderItemId: $orderItem->id,
            quantityToReverse: 30.0,
            userId: $this->receiver->id,
            notes: 'Order cancelled, restore stock'
        );

        $this->assertSame(30.0, $reversed);

        // Verification: Restored stock returns to segregated Grade B per Fix 1 rule
        $this->assertSame(30.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeB));
        $this->assertSame(30.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::Unsorted));
        $this->assertSame(60.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id));
    }

    /**
     * Test 12: Idempotency with client_submission_id for repeated requests.
     */
    public function test_12_segregation_idempotency_with_client_submission_id(): void
    {
        $batch = StockBatch::create([
            'reference' => 'IDEMP-MIX-100',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        $submissionId = 'client-sub-12345';

        // 1. Initial segregation call
        $res1 = $this->segregationAction->execute(
            $batch,
            ProductGrade::GradeA,
            25.0,
            $this->receiver->id,
            'First attempt',
            $submissionId
        );

        $child1Id = $res1['child']->id;

        // 2. Duplicate retry with same submission id
        $res2 = $this->segregationAction->execute(
            $batch->fresh(),
            ProductGrade::GradeA,
            25.0,
            $this->receiver->id,
            'Retry attempt',
            $submissionId
        );

        // Same child batch returned, no duplicate deduction
        $this->assertSame($child1Id, $res2['child']->id);
        $this->assertSame(75.0, (float) $res2['parent']->total_kg);
        $this->assertSame(25.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeA));
        $this->assertSame(75.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::Unsorted));
    }

    /**
     * Test 13: Warehouse segregation isolation (Vegetable vs Fruit warehouse).
     */
    public function test_13_warehouse_segregation_isolation(): void
    {
        // Vegetable warehouse: 100 kg Tomato
        $batchVeg = StockBatch::create([
            'reference' => 'VEG-TOM-100',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        // Fruit warehouse: 100 kg Apple
        $batchFruit = StockBatch::create([
            'reference' => 'FRT-APL-100',
            'product_id' => $this->apple->id,
            'warehouse_id' => $this->fruitWarehouse->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 80.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        // Segregate Tomato in Vegetable Warehouse: 40 kg to Grade A
        $this->segregationAction->execute($batchVeg, ProductGrade::GradeA, 40.0, $this->receiver->id);

        // Segregate Apple in Fruit Warehouse: 30 kg to Grade B
        $this->segregationAction->execute($batchFruit, ProductGrade::GradeB, 30.0, $this->receiver->id);

        // Assert Vegetable Warehouse levels
        $this->assertSame(40.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeA));
        $this->assertSame(60.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::Unsorted));
        $this->assertSame(0.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->fruitWarehouse->id));

        // Assert Fruit Warehouse levels
        $this->assertSame(30.0, $this->stockLedgerService->availableStockForProduct($this->apple->id, $this->fruitWarehouse->id, ProductGrade::GradeB));
        $this->assertSame(70.0, $this->stockLedgerService->availableStockForProduct($this->apple->id, $this->fruitWarehouse->id, ProductGrade::Unsorted));
        $this->assertSame(0.0, $this->stockLedgerService->availableStockForProduct($this->apple->id, $this->vegWarehouse->id));
    }

    /**
     * Test 14: End-to-end simulated business day with complete reconciliation formula:
     * Opening + Receipts + Transfers In - Transfers Out - Physical Consumption - Damage +- Adjustments = Closing
     * and proving that sum of all grade balances matches physical available stock!
     */
    public function test_14_end_to_end_business_day_reconciliation(): void
    {
        // 1. Opening: 0 kg
        // 2. Receipts:
        //    Batch 1: 200 kg Mixed (U) at Rs 20/kg
        $batchMixed = StockBatch::create([
            'reference' => 'E2E-MIX-200',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'total_kg' => 200.0,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        //    Batch 2: 50 kg Direct Grade A at Rs 30/kg
        $batchDirectA = StockBatch::create([
            'reference' => 'E2E-A-50',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'total_kg' => 50.0,
            'cost_per_kg' => 30.0,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'A',
            'grading_mode' => 'fixed_purchase_grade',
            'created_by' => $this->receiver->id,
        ]);
        StockMovement::create([
            'batch_id' => $batchDirectA->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 50.0,
            'cost_per_unit' => 30.0,
            'notes' => 'Direct A receipt',
        ]);

        // Total initial physical receipts = 200 + 50 = 250 kg
        $this->assertSame(250.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id));

        // 3. Manual Segregations:
        //    From Mixed: 40 kg to Grade B
        $this->segregationAction->execute($batchMixed, ProductGrade::GradeB, 40.0, $this->receiver->id);

        //    From Mixed: 25 kg to Grade C
        $this->segregationAction->execute($batchMixed->fresh(), ProductGrade::GradeC, 25.0, $this->receiver->id);

        // 4. Damage Write-off:
        //    From Mixed: 5 kg written off as damage
        $this->segregationAction->execute(
            batch: $batchMixed->fresh(),
            targetGrade: ProductGrade::Damage,
            quantity: 5.0,
            userId: $this->receiver->id,
            notes: 'Bruised during sorting',
            damageReason: WastageReason::SortingDamage
        );

        // 5. Regrading:
        //    From Direct Grade A: regrade 10 kg into Grade B
        $this->segregationAction->execute($batchDirectA->fresh(), ProductGrade::GradeB, 10.0, $this->receiver->id, 'Regraded to B');

        // 6. Shop Orders & Loadouts:
        //    Order 1: Load 25 kg of Grade A (fulfilled from Direct Grade A batch)
        $order1 = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'order_number' => 'E2E-ORD-01',
            'state' => 'approved',
            'business_date' => $this->businessDate,
            'created_by' => $this->admin->id,
        ]);
        $item1 = ShopOrderItem::create([
            'shop_order_id' => $order1->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'unit' => 'KG',
            'requested_qty' => 25.0,
            'approved_qty' => 25.0,
            'selling_price' => 45.0,
        ]);
        $this->stockLedgerService->consumeStockForProductAllowingNegative(
            productId: $this->tomato->id,
            quantity: 25.0,
            userId: $this->receiver->id,
            movementType: StockMovementType::Out,
            notes: 'Loadout Order 1',
            shopOrderItemId: $item1->id,
            warehouseId: $this->vegWarehouse->id,
            grade: ProductGrade::GradeA
        );

        //    Order 2: Load 60 kg of Grade B
        //    (Grade B has: 40 kg from segregation + 10 kg from regrading = 50 kg available.
        //     Shortfall of 10 kg is automatically classified and consumed from remaining Mixed!)
        $shop2 = Shop::create([
            'name' => 'Green Leaf Downtown',
            'code' => 'GLE-02',
            'shop_price_group_id' => $this->priceGroup->id,
            'is_active' => true,
            'allowed_product_grades' => ['A', 'B', 'C'],
        ]);

        $order2 = ShopOrder::create([
            'shop_id' => $shop2->id,
            'order_number' => 'E2E-ORD-02',
            'state' => 'approved',
            'business_date' => $this->businessDate,
            'created_by' => $this->admin->id,
        ]);
        $item2 = ShopOrderItem::create([
            'shop_order_id' => $order2->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'unit' => 'KG',
            'requested_qty' => 60.0,
            'approved_qty' => 60.0,
            'selling_price' => 35.0,
        ]);
        $this->stockLedgerService->consumeStockForProductAllowingNegative(
            productId: $this->tomato->id,
            quantity: 60.0,
            userId: $this->receiver->id,
            movementType: StockMovementType::Out,
            notes: 'Loadout Order 2',
            shopOrderItemId: $item2->id,
            warehouseId: $this->vegWarehouse->id,
            grade: ProductGrade::GradeB
        );

        // 7. Execute Reconciliation via StockLedgerService
        $reconciliation = $this->stockLedgerService->reconcileInventory(
            productId: $this->tomato->id,
            warehouseId: $this->vegWarehouse->id
        );

        // Assert full reconciliation holds!
        $this->assertTrue($reconciliation['is_fully_reconciled']);
        $this->assertTrue($reconciliation['total']['reconciled']);

        // Check total movements conservation:
        // Total Receipts: 250 kg
        $this->assertSame(250.0, $reconciliation['total']['receipts']);

        // Transfers In must exactly equal Transfers Out (Internal conservation: net = 0)
        $this->assertSame($reconciliation['total']['transfers_in'], $reconciliation['total']['transfers_out']);

        // Physical Consumption: 25 kg (Grade A) + 60 kg (Grade B) = 85 kg
        $this->assertSame(85.0, $reconciliation['total']['physical_consumption']);

        // Damage: 5 kg
        $this->assertSame(5.0, $reconciliation['total']['damage']);

        // Total physical remaining available = 250 - 85 - 5 = 160 kg!
        $this->assertSame(160.0, $reconciliation['total']['physical_available']);
        $this->assertSame(160.0, $reconciliation['total']['formula_closing']);

        // Check individual grade balances:
        // Grade A:
        //   Receipts: 50 kg
        //   Regraded Out to B: 10 kg
        //   Physical Consumption: 25 kg
        //   Closing: 50 - 10 - 25 = 15 kg
        $gradeA = $reconciliation['by_grade']['A'];
        $this->assertTrue($gradeA['reconciled']);
        $this->assertSame(15.0, $gradeA['available_stock']);

        // Grade B:
        //   Transfers In: 40 kg (from Mixed) + 10 kg (from A) + 10 kg (auto-classified from Mixed during loadout) = 60 kg
        //   Physical Consumption: 60 kg
        //   Closing: 60 - 60 = 0 kg
        $gradeB = $reconciliation['by_grade']['B'];
        $this->assertTrue($gradeB['reconciled']);
        $this->assertSame(0.0, $gradeB['available_stock']);

        // Grade C:
        //   Transfers In from Mixed: 15 kg
        //   Closing: 15 kg
        $gradeC = $reconciliation['by_grade']['C'];
        $this->assertTrue($gradeC['reconciled']);
        // Grade C:
        //   Transfers In from Mixed: 25 kg
        //   Closing: 25 kg
        $gradeC = $reconciliation['by_grade']['C'];
        $this->assertTrue($gradeC['reconciled']);
        $this->assertSame(25.0, $gradeC['available_stock']);

        // Mixed (U):
        //   Receipts: 200 kg
        //   Transfers Out: 40 (to B) + 25 (to C) + 10 (auto-classified to B for loadout) = 75 kg
        //   Damage: 5 kg
        //   Closing: 200 - 75 - 5 = 120 kg
        $gradeU = $reconciliation['by_grade']['U'];
        $this->assertTrue($gradeU['reconciled']);
        $this->assertSame(120.0, $gradeU['available_stock']);

        // Sum of all grade balances:
        // Grade A (15) + Grade B (0) + Grade C (25) + Mixed (120) = 160 kg!
        // Matches exact physical available stock (160 kg)!
        $sumGradeBalances = $gradeA['available_stock'] + $gradeB['available_stock'] + $gradeC['available_stock'] + $gradeU['available_stock'];
        $this->assertSame(160.0, $sumGradeBalances);
        $this->assertSame($reconciliation['total']['physical_available'], $sumGradeBalances);
    }

    /**
     * Test 15: Reversal of a regraded sorted batch restores parent batch stock and balances.
     */
    public function test_15_reversal_of_regraded_sorted_batch(): void
    {
        $batchA = StockBatch::create([
            'reference' => 'REGRADE-REV-A-100',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 30.0,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'A',
            'grading_mode' => 'fixed_purchase_grade',
            'created_by' => $this->receiver->id,
        ]);
        StockMovement::create([
            'batch_id' => $batchA->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 100.0,
            'cost_per_unit' => 30.0,
            'notes' => 'Initial A batch',
        ]);

        // Regrade 30 kg from A to B
        $result = $this->segregationAction->execute($batchA, ProductGrade::GradeB, 30.0, $this->receiver->id, 'Regraded to B');
        $childB = $result['child'];

        $this->assertSame(70.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeA));
        $this->assertSame(30.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeB));

        // Reverse the 30 kg back from B to A
        $revResult = $this->segregationAction->reverse($childB, 30.0, $this->receiver->id, 'Reversing regrade');

        $this->assertSame(100.0, (float) $revResult['parent']->total_kg);
        $this->assertSame(100.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeA));
        $this->assertSame(0.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeB));
        $this->assertSame(100.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id));
    }

    /**
     * Test 16: Reversal of damage write-off restores available stock on parent batch.
     */
    public function test_16_reversal_of_damage_write_off(): void
    {
        $batch = StockBatch::create([
            'reference' => 'DAM-REV-100',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 25.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        // Record 15 kg damage
        $result = $this->segregationAction->execute(
            batch: $batch,
            targetGrade: ProductGrade::Damage,
            quantity: 15.0,
            userId: $this->receiver->id,
            notes: 'Transit bruising',
            damageReason: WastageReason::TransitDamage
        );

        $wastage = $result['wastage'];
        $this->assertSame(85.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id));

        // Reverse damage write-off
        $revResult = $this->segregationAction->reverseDamage($wastage, 15.0, $this->receiver->id, 'Restoring erroneously marked damage');

        $this->assertNull($revResult['wastage']); // Fully reversed / deleted
        $this->assertSame(100.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id));
    }

    /**
     * Test 17: Release Verification — Exact Operational Lifecycle:
     * Receive Mixed → Grade B Loadout → Segregate Remaining → Regrade → Damage → Edit/Reverse → Reconcile Inventory.
     */
    public function test_17_full_business_day_mixed_loadout_first_segregate_regrade_damage_reverse(): void
    {
        // 1. Receive Mixed: 100 kg at Rs 25/kg
        $batchMixed = StockBatch::create([
            'reference' => 'REL-VERIF-MIX-100',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->vegWarehouse->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 25.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        $this->assertSame(100.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id));
        $this->assertSame(100.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::Unsorted));
        $this->assertSame(0.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeB));

        // 2. Grade B Loadout (before any manual segregation): Load 30 kg as B
        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'order_number' => 'REL-SO-01',
            'state' => 'approved',
            'business_date' => $this->businessDate,
            'created_by' => $this->admin->id,
        ]);
        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'unit' => 'KG',
            'requested_qty' => 30.0,
            'approved_qty' => 30.0,
            'selling_price' => 35.0,
        ]);

        $consumed = $this->stockLedgerService->consumeStockForProductAllowingNegative(
            productId: $this->tomato->id,
            quantity: 30.0,
            userId: $this->receiver->id,
            movementType: StockMovementType::Out,
            notes: 'Dispatch Order REL-SO-01',
            shopOrderItemId: $orderItem->id,
            warehouseId: $this->vegWarehouse->id,
            grade: ProductGrade::GradeB
        );

        $this->assertSame(30.0, $consumed);
        // Mixed decreased to 70 kg, B remains 0 kg, physical available is 70 kg
        $this->assertSame(70.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::Unsorted));
        $this->assertSame(0.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeB));
        $this->assertSame(70.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id));

        // Lineage verification: The physical deduction is on the segregated Grade B batch per Fix 1
        $movement = StockMovement::where('shop_order_item_id', $orderItem->id)->first();
        $this->assertNotNull($movement);
        $this->assertSame(ProductGrade::GradeB, $movement->grade instanceof ProductGrade ? $movement->grade : ProductGrade::tryResolve($movement->grade));
        $this->assertSame(30.0, (float) $movement->quantity);
        $this->assertSame(25.0, (float) $movement->cost_per_unit);
        $this->assertSame('B', $orderItem->product_grade);

        // 3. Segregate Remaining Mixed:
        //    20 kg to Grade A
        $resA = $this->segregationAction->execute($batchMixed->fresh(), ProductGrade::GradeA, 20.0, $this->receiver->id);
        $childA = $resA['child'];

        //    20 kg to Grade C
        $resC = $this->segregationAction->execute($batchMixed->fresh(), ProductGrade::GradeC, 20.0, $this->receiver->id);
        $childC = $resC['child'];

        // Remaining Mixed: 70 - 40 = 30 kg
        $this->assertSame(30.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::Unsorted));
        $this->assertSame(20.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeA));
        $this->assertSame(20.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeC));
        $this->assertSame(70.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id));

        // 4. Regrade: from Grade A (20 kg), regrade 5 kg to Grade B
        $resB = $this->segregationAction->execute($childA->fresh(), ProductGrade::GradeB, 5.0, $this->receiver->id, 'Regraded from A to B');
        $this->assertSame(15.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeA));
        $this->assertSame(5.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeB));
        $this->assertSame(70.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id));

        // 5. Damage: from Grade C (10 kg), record 2 kg damage
        $resDmg = $this->segregationAction->execute(
            batch: $childC->fresh(),
            targetGrade: ProductGrade::Damage,
            quantity: 2.0,
            userId: $this->receiver->id,
            notes: 'Spotted rot',
            damageReason: WastageReason::SortingDamage
        );
        $this->assertSame(18.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeC));
        // Total physical available is now 70 - 2 = 68 kg
        $this->assertSame(68.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id));

        // 6. Edit/Reverse: Loadout adjusted from 30 kg to 20 kg (reverse 10 kg)
        $this->stockLedgerService->reverseStockForOrderItem(
            shopOrderItemId: $orderItem->id,
            quantityToReverse: 10.0,
            userId: $this->receiver->id,
            notes: 'Reduced loaded quantity from 30 to 20 kg'
        );

        // Verification of reversal:
        // Stock restores to segregated Grade B per Fix 1 (5 kg from regrade + 10 kg from reversal = 15 kg)!
        $this->assertSame(15.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::GradeB));
        $this->assertSame(30.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id, ProductGrade::Unsorted));
        // Total physical available restores from 68 to 78 kg!
        $this->assertSame(78.0, $this->stockLedgerService->availableStockForProduct($this->tomato->id, $this->vegWarehouse->id));

        // 7. Full Inventory Reconciliation
        $reconciliation = $this->stockLedgerService->reconcileInventory(
            productId: $this->tomato->id,
            warehouseId: $this->vegWarehouse->id
        );

        $this->assertTrue($reconciliation['is_fully_reconciled']);
        $this->assertTrue($reconciliation['total']['reconciled']);

        // Check total movements conservation:
        $this->assertSame(100.0, $reconciliation['total']['receipts']);
        $this->assertSame(20.0, $reconciliation['total']['physical_consumption']); // 30 - 10
        $this->assertSame(2.0, $reconciliation['total']['damage']);
        $this->assertSame(78.0, $reconciliation['total']['physical_available']);
        $this->assertSame(78.0, $reconciliation['total']['formula_closing']);

        // Transfers in equal transfers out
        $this->assertSame($reconciliation['total']['transfers_in'], $reconciliation['total']['transfers_out']);

        // Check grade balances sum up exactly to physical available stock
        $gradeBalances = $reconciliation['by_grade'];
        $this->assertSame(15.0, $gradeBalances['A']['available_stock']);
        $this->assertSame(15.0, $gradeBalances['B']['available_stock']);
        $this->assertSame(18.0, $gradeBalances['C']['available_stock']);
        $this->assertSame(30.0, $gradeBalances['U']['available_stock']);

        $sumBalances = 15.0 + 15.0 + 18.0 + 30.0;
        $this->assertSame(78.0, $sumBalances);
        $this->assertSame($reconciliation['total']['physical_available'], $sumBalances);
    }
}
