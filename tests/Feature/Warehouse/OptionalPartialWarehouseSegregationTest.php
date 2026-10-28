<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Actions\Inventory\ProcessBatchSortingAction;
use App\Actions\Inventory\ProcessPartialSegregationAction;
use App\DTOs\Inventory\SortingData;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\AdvanceReceiveMatch;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Inventory\StockLedgerService;
use App\Services\Purchasing\AdvanceReceiveReconciliationService;
use App\Services\Purchasing\GoodsReceivedService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Warehouse\AutoLoadAllService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OptionalPartialWarehouseSegregationTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private User $admin;

    private Warehouse $warehouse;

    private Warehouse $warehouse2;

    private Supplier $supplier;

    private Category $category;

    private Product $product;

    private ShopPriceGroup $priceGroup;

    private Shop $shop;

    private string $businessDate;

    private StockLedgerService $stockLedgerService;

    private StockMovementRepository $stockMovementRepository;

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

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'MWH-01',
            'is_active' => true,
        ]);
        $this->receiver->warehouses()->attach($this->warehouse);

        $this->warehouse2 = Warehouse::create([
            'name' => 'Secondary Warehouse',
            'code' => 'SWH-02',
            'is_active' => true,
        ]);
        $this->receiver->warehouses()->attach($this->warehouse2);

        $this->supplier = Supplier::factory()->create([
            'name' => 'Fresh Farm Supplier',
        ]);

        $this->category = Category::create([
            'name' => 'Vegetables',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Tomato Hybrid',
            'sku' => 'TOM-HYB',
            'unit' => 'KG',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'is_active' => true,
        ]);

        $this->priceGroup = ShopPriceGroup::create([
            'name' => 'A',
            'is_active' => true,
        ]);

        $this->shop = Shop::create([
            'name' => 'Green Grocery',
            'code' => 'GG-01',
            'shop_price_group_id' => $this->priceGroup->id,
            'is_active' => true,
            'allowed_product_grades' => ['A', 'B', 'C'],
        ]);

        $this->businessDate = app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();

        BusinessSetting::updateOrCreate(
            ['key' => 'allow_historical_invoice_repricing'],
            ['value' => '1']
        );

        DailyPriceApproval::create([
            'product_id' => $this->product->id,
            'business_date' => $this->businessDate,
            'grade' => 'A',
            'purchase_price' => 20.0,
            'price_unit' => 'KG',
            'price_a' => 50.0,
            'price_b' => 40.0,
            'price_c' => 30.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        DailyPriceApproval::create([
            'product_id' => $this->product->id,
            'business_date' => $this->businessDate,
            'grade' => 'B',
            'purchase_price' => 15.0,
            'price_unit' => 'KG',
            'price_a' => 40.0,
            'price_b' => 30.0,
            'price_c' => 20.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        DailyPriceApproval::create([
            'product_id' => $this->product->id,
            'business_date' => $this->businessDate,
            'grade' => 'C',
            'purchase_price' => 10.0,
            'price_unit' => 'KG',
            'price_a' => 30.0,
            'price_b' => 20.0,
            'price_c' => 10.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->stockLedgerService = app(StockLedgerService::class);
        $this->stockMovementRepository = app(StockMovementRepository::class);
        $this->segregationAction = app(ProcessPartialSegregationAction::class);
    }

    /**
     * Requirement 1-5: Receive 100 kg Mixed -> Segregate 30 kg A -> Segregate 20 kg B -> Mixed remains 50 kg -> Total stock 100 kg.
     */
    public function test_01_partial_segregation_example_sequence(): void
    {
        $batch = StockBatch::create([
            'reference' => 'BATCH-TOM-100',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 25.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        // Initial inventory: 100 kg Mixed, 0 sorted
        $this->assertSame(100.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));
        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));
        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        $this->assertSame(100.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id));

        // First segregation: 30 kg -> Grade A
        $res1 = $this->segregationAction->execute($batch, ProductGrade::GradeA, 30.0, $this->receiver->id);
        $childA = $res1['child'];
        $parent1 = $res1['parent'];

        $this->assertSame(70.0, (float) $parent1->total_kg);
        $this->assertSame(30.0, (float) $childA->total_kg);
        $this->assertSame(BatchStatus::Sorted, $childA->status);
        $this->assertSame('A', $childA->purchase_grade);
        $this->assertSame($batch->id, $childA->parent_batch_id);

        // Verification after first segregation
        $this->assertSame(30.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));
        $this->assertSame(70.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));
        $this->assertSame(100.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id));

        // Second segregation: 20 kg -> Grade B
        $res2 = $this->segregationAction->execute($parent1, ProductGrade::GradeB, 20.0, $this->receiver->id);
        $childB = $res2['child'];
        $parent2 = $res2['parent'];

        $this->assertSame(50.0, (float) $parent2->total_kg);
        $this->assertSame(20.0, (float) $childB->total_kg);
        $this->assertSame(BatchStatus::Sorted, $childB->status);
        $this->assertSame('B', $childB->purchase_grade);
        $this->assertSame($batch->id, $childB->parent_batch_id);

        // Verification after second segregation
        $this->assertSame(30.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));
        $this->assertSame(20.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        $this->assertSame(50.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));
        $this->assertSame(100.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id));

        // Verify stock movement repository matches
        $this->assertSame(100.0, $this->stockMovementRepository->currentStockForProduct($this->product->id, $this->warehouse->id));
        $this->assertSame(30.0, $this->stockMovementRepository->stockForProductAndGrade($this->product->id, ProductGrade::GradeA, $this->warehouse->id));
        $this->assertSame(20.0, $this->stockMovementRepository->stockForProductAndGrade($this->product->id, ProductGrade::GradeB, $this->warehouse->id));
        $this->assertSame(50.0, $this->stockMovementRepository->stockForProductAndGrade($this->product->id, ProductGrade::Unsorted, $this->warehouse->id));
    }

    /**
     * Requirement 6 & 7: Repeated segregation of same batch until zero.
     */
    public function test_02_repeated_segregation_until_zero_closes_parent_batch(): void
    {
        $batch = StockBatch::create([
            'reference' => 'BATCH-REPEAT-100',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 25.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        $this->segregationAction->execute($batch, ProductGrade::GradeA, 40.0, $this->receiver->id);
        $batch->refresh();
        $this->assertSame(60.0, (float) $batch->total_kg);

        $this->segregationAction->execute($batch, ProductGrade::GradeB, 30.0, $this->receiver->id);
        $batch->refresh();
        $this->assertSame(30.0, (float) $batch->total_kg);

        $this->segregationAction->execute($batch, ProductGrade::GradeC, 30.0, $this->receiver->id);
        $batch->refresh();

        $this->assertSame(0.0, (float) $batch->total_kg);
        $this->assertSame(BatchStatus::Closed, $batch->status);
        $this->assertFalse($batch->canBeSorted());

        $this->assertSame(40.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));
        $this->assertSame(30.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        $this->assertSame(30.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeC));
        $this->assertSame(0.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));
        $this->assertSame(100.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id));
    }

    /**
     * Requirement 8: Existing full sorting after partial segregation.
     */
    public function test_03_existing_full_sorting_after_partial_segregation(): void
    {
        $batch = StockBatch::create([
            'reference' => 'BATCH-PARTIAL-THEN-FULL',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 25.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        // Segregate 30 kg to Grade A
        $this->segregationAction->execute($batch, ProductGrade::GradeA, 30.0, $this->receiver->id);
        $batch->refresh();
        $this->assertSame(70.0, (float) $batch->total_kg);

        // Operator performs full sort on remaining 70 kg: 40 kg B + 30 kg C = 70 kg
        $sortingData = new SortingData(
            batchId: $batch->id,
            grades: [
                ['grade' => ProductGrade::GradeB, 'quantity' => 40.0],
                ['grade' => ProductGrade::GradeC, 'quantity' => 30.0],
            ],
            notes: 'Full sort remaining 70 kg',
        );

        app(ProcessBatchSortingAction::class)->execute($batch, $sortingData, $this->receiver->id);
        $batch->refresh();

        $this->assertSame(BatchStatus::Sorted, $batch->status);
        $this->assertSame(70.0, (float) $batch->total_kg);

        // Stock balances: 30 Grade A, 40 Grade B, 30 Grade C, 0 Mixed
        $this->assertSame(30.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));
        $this->assertSame(40.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        $this->assertSame(30.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeC));
        $this->assertSame(0.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));
        $this->assertSame(100.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id));
    }

    /**
     * Requirement 9: Attempt to segregate more than available throws ValidationException.
     */
    public function test_04_attempt_to_segregate_more_than_available_fails(): void
    {
        $batch = StockBatch::create([
            'reference' => 'BATCH-OVERFLOW',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 50.0,
            'cost_per_kg' => 25.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        $this->expectException(ValidationException::class);
        $this->segregationAction->execute($batch, ProductGrade::GradeA, 55.0, $this->receiver->id);
    }

    /**
     * Requirement 10: Segregation after partial loadout.
     */
    public function test_05_segregation_after_partial_loadout(): void
    {
        $batch = StockBatch::create([
            'reference' => 'BATCH-LOADOUT-THEN-SEG',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 25.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        // Loadout consumes 15 kg from Mixed pending batch
        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => 'U',
            'type' => StockMovementType::Out->value,
            'quantity' => 15.0,
            'cost_per_unit' => 25.0,
            'notes' => 'Early loadout of 15 kg Mixed',
        ]);

        $batch->refresh();
        $this->assertSame(85.0, $batch->available_mixed_qty);

        // Operator attempts to segregate 90 kg -> should fail because only 85 kg available
        try {
            $this->segregationAction->execute($batch, ProductGrade::GradeA, 90.0, $this->receiver->id);
            $this->fail('Expected ValidationException when segregating more than available after loadout.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }

        // Segregating 35 kg succeeds
        $res = $this->segregationAction->execute($batch, ProductGrade::GradeA, 35.0, $this->receiver->id);
        $this->assertSame(35.0, (float) $res['child']->total_kg);
        $this->assertSame(65.0, (float) $res['parent']->total_kg);
        $this->assertSame(50.0, $res['parent']->available_mixed_qty); // 65 - 15 = 50 kg available Mixed

        // Total inventory: 35 Grade A + 50 Mixed = 85 kg (15 kg was loaded out)
        $this->assertSame(85.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id));
    }

    /**
     * Requirement 11 & 13: Automatic Load All after segregation & Grade-first / Mixed-second deduction.
     */
    public function test_06_auto_load_all_consumes_segregated_grade_first_then_mixed(): void
    {
        $batch = StockBatch::create([
            'reference' => 'BATCH-AUTOLOAD-SEG',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 25.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        // Segregate 30 kg to Grade A (leaving 70 kg Mixed)
        $this->segregationAction->execute($batch, ProductGrade::GradeA, 30.0, $this->receiver->id);

        // Shop order requires 45 kg of Grade A
        $order = ShopOrder::create([
            'order_number' => 'ORD-SEG-01',
            'shop_id' => $this->shop->id,
            'created_by' => $this->receiver->id,
            'business_date' => $this->businessDate,
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
            'total_amount' => 2250.0,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 45.0,
            'approved_qty' => 45.0,
            'product_grade' => 'A',
            'sorting_status' => 'allocated',
            'unit_price' => 50.0,
            'unit' => 'KG',
            'requested_unit' => 'KG',
            'requested_unit_conversion_to_base' => 1.0,
        ]);

        // Run Auto Load All
        $autoLoadService = app(AutoLoadAllService::class);
        $result = $autoLoadService->loadOrder($order, $this->receiver, $this->warehouse->id);

        $this->assertTrue($result['success']);

        // Check movements: 30 kg from segregated Grade A, 15 kg from Mixed parent batch
        $outMovements = StockMovement::where('shop_order_item_id', $item->id)->get();
        $this->assertCount(2, $outMovements);

        $gradeAMovement = $outMovements->firstWhere('grade', 'A');
        $this->assertNotNull($gradeAMovement);
        $this->assertSame(30.0, (float) $gradeAMovement->quantity);

        // Both deductions for the order item are Grade A per Fix 1 (30 kg from existing A, 15 kg from newly segregated A)
        $this->assertSame(45.0, (float) $outMovements->where('grade', 'A')->sum('quantity'));

        // Remaining inventory: 0 Grade A, 55 kg Mixed. Total = 55 kg. (100 - 45 = 55 kg)
        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));
        $this->assertSame(55.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));
        $this->assertSame(55.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id));
    }

    /**
     * Requirement 14: Full and partial segregation reversal.
     */
    public function test_07_segregation_reversal_restores_mixed_stock(): void
    {
        $batch = StockBatch::create([
            'reference' => 'BATCH-REVERSAL',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 25.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        // Segregate 40 kg to Grade A
        $res = $this->segregationAction->execute($batch, ProductGrade::GradeA, 40.0, $this->receiver->id);
        $child = $res['child'];

        $this->assertSame(60.0, (float) $res['parent']->total_kg);
        $this->assertSame(40.0, (float) $child->total_kg);

        // Partial reversal: reverse 15 kg back to Mixed
        $revRes1 = $this->segregationAction->reverse($child, 15.0, $this->receiver->id);
        $this->assertSame(75.0, (float) $revRes1['parent']->total_kg);
        $this->assertSame(25.0, (float) $revRes1['child']->total_kg);

        $this->assertSame(25.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));
        $this->assertSame(75.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));
        $this->assertSame(100.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id));

        // Complete reversal: reverse remaining 25 kg back to Mixed
        $revRes2 = $this->segregationAction->reverse($child->fresh(), 25.0, $this->receiver->id);
        $this->assertSame(100.0, (float) $revRes2['parent']->total_kg);

        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));
        $this->assertSame(100.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));
        $this->assertSame(100.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id));
    }

    /**
     * Requirement 14b: Cannot reverse segregated stock that has already been dispatched.
     */
    public function test_08_cannot_reverse_dispatched_segregated_stock(): void
    {
        $batch = StockBatch::create([
            'reference' => 'BATCH-DISPATCH-REV',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 25.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        $res = $this->segregationAction->execute($batch, ProductGrade::GradeA, 30.0, $this->receiver->id);
        $child = $res['child'];

        // Dispatch 20 kg of Grade A from child batch
        StockMovement::create([
            'batch_id' => $child->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 20.0,
            'cost_per_unit' => 25.0,
            'notes' => 'Customer dispatch of 20 kg Grade A',
        ]);

        // Attempting to reverse 25 kg should fail (only 10 kg unconsumed remains)
        $this->expectException(ValidationException::class);
        $this->segregationAction->reverse($child, 25.0, $this->receiver->id);
    }

    /**
     * Requirement 16: Multiple warehouses segregation isolation.
     */
    public function test_09_multiple_warehouses_segregation_isolation(): void
    {
        // Batch 1 in Warehouse 1: 100 kg Mixed
        $batchWh1 = StockBatch::create([
            'reference' => 'BATCH-WH1-100',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 25.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        // Batch 2 in Warehouse 2: 50 kg Mixed
        $batchWh2 = StockBatch::create([
            'reference' => 'BATCH-WH2-50',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse2->id,
            'total_kg' => 50.0,
            'cost_per_kg' => 25.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        // Segregate 40 kg Grade A in Warehouse 1
        $resWh1 = $this->segregationAction->execute($batchWh1, ProductGrade::GradeA, 40.0, $this->receiver->id);
        $this->assertSame($this->warehouse->id, $resWh1['child']->warehouse_id);

        // Verification: Warehouse 1 has 40 Grade A and 60 Mixed
        $this->assertSame(40.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));
        $this->assertSame(60.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));

        // Warehouse 2 has 0 Grade A and 50 Mixed
        $this->assertSame(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse2->id, ProductGrade::GradeA));
        $this->assertSame(50.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse2->id, ProductGrade::Unsorted));

        // Total across all warehouses: 40 Grade A + 110 Mixed = 150 kg
        $this->assertSame(150.0, $this->stockLedgerService->availableStockForProduct($this->product->id));
    }

    /**
     * Requirement 17: Advance receipt and bill matching with partial segregation.
     */
    public function test_10_advance_receipt_and_bill_matching_with_segregation(): void
    {
        // 1. Create advance GRN for 100 kg Mixed
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-ADV-SEG-01',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->receiver->id,
            'received_at' => now(),
            'status' => 'approved',
            'is_advance_receive' => true,
        ]);

        $item = $grn->items()->create([
            'product_id' => $this->product->id,
            'received_qty' => 100.0,
            'unit_id' => null,
            'cost_per_unit' => 20.0,
            'grade' => 'U',
            'grading_mode' => 'sort_required',
            'variance' => 0.0,
        ]);

        $batch = StockBatch::create([
            'reference' => 'BATCH-ADV-SEG',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        // 2. Partially segregate 30 kg to Grade A
        $res = $this->segregationAction->execute($batch, ProductGrade::GradeA, 30.0, $this->receiver->id);

        $this->assertSame(70.0, (float) $res['parent']->total_kg);
        $this->assertSame(30.0, (float) $res['child']->total_kg);
        $this->assertSame($grn->id, $res['child']->goods_received_id);
        $this->assertSame($item->id, $res['child']->goods_received_item_id);

        // 3. Stock totals stay conserved
        $this->assertSame(100.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id));
        $this->assertSame(30.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));
        $this->assertSame(70.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));
    }

    /**
     * Requirement 18 & 19: Web routes for segregation and reverse.
     */
    public function test_11_web_routes_for_segregation_and_reverse(): void
    {
        $batch = StockBatch::create([
            'reference' => 'BATCH-WEB-SEG',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 25.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        // 1. Post to web segregate route
        $response = $this->actingAs($this->receiver)
            ->post(route('inventory.batches.segregate', $batch), [
                'target_grade' => 'A',
                'quantity' => 25.0,
                'notes' => 'Web segregation test',
            ]);

        $response->assertRedirect(route('inventory.batches.sort', $batch));
        $response->assertSessionHas('success');

        $batch->refresh();
        $this->assertSame(75.0, (float) $batch->total_kg);

        $segChild = StockBatch::where('parent_batch_id', $batch->id)->first();
        $this->assertNotNull($segChild);
        $this->assertSame(25.0, (float) $segChild->total_kg);
        $this->assertSame('A', $segChild->purchase_grade);

        // 2. Post to web reverse route
        $revResponse = $this->actingAs($this->receiver)
            ->post(route('inventory.batches.segregate.reverse', $segChild), [
                'quantity' => 25.0,
                'notes' => 'Web reverse test',
            ]);

        $revResponse->assertRedirect(route('inventory.batches.show', $batch));
        $revResponse->assertSessionHas('success');

        $batch->refresh();
        $this->assertSame(100.0, (float) $batch->total_kg);
    }

    /**
     * Requirement 20: API routes for segregation and reverse.
     */
    public function test_12_api_routes_for_segregation_and_reverse(): void
    {
        $batch = StockBatch::create([
            'reference' => 'BATCH-API-SEG',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 80.0,
            'cost_per_kg' => 25.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        // 1. API segregate
        $response = $this->actingAs($this->receiver, 'sanctum')
            ->postJson(route('api.v1.inventory.batches.segregate', $batch), [
                'target_grade' => 'B',
                'quantity' => 30.0,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.child.purchase_grade', 'B');
        $this->assertEquals(30.0, (float) $response->json('data.child.total_kg'));
        $this->assertEquals(50.0, (float) $response->json('data.parent.total_kg'));

        $childId = $response->json('data.child.id');
        $childBatch = StockBatch::findOrFail($childId);

        // 2. API reverse
        $revResponse = $this->actingAs($this->receiver, 'sanctum')
            ->postJson(route('api.v1.inventory.batches.segregate.reverse', $childBatch), [
                'quantity' => 30.0,
            ]);

        $revResponse->assertOk();
        $this->assertEquals(80.0, (float) $revResponse->json('data.parent.total_kg'));
    }

    /**
     * Audit Section 3 & 4 Scenario:
     * Receive 100 kg Tomato Mixed -> Segregate 30 A, 20 B -> Mixed 50 kg, A 30 kg, B 20 kg (Total 100 kg).
     * Dispatch: Grade B 10 kg, Mixed 15 kg -> Mixed 35 kg, A 30 kg, B 10 kg (Total 75 kg).
     * Then full sorting operates ONLY on actual remaining Mixed balance (35 kg).
     */
    public function test_13_audit_math_scenario_partial_segregation_and_partial_dispatch_and_subsequent_full_sort(): void
    {
        // 1. Receive 100 kg Tomato Mixed
        $batch = StockBatch::create([
            'reference' => 'BATCH-MATH-AUDIT',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        // 2. Segregate: 30 kg Grade A, 20 kg Grade B
        $resA = $this->segregationAction->execute($batch, ProductGrade::GradeA, 30.0, $this->receiver->id);
        $resB = $this->segregationAction->execute($batch->fresh(), ProductGrade::GradeB, 20.0, $this->receiver->id);

        $childA = $resA['child'];
        $childB = $resB['child'];
        $parent = $resB['parent'];

        // Expected: Mixed 50 kg, Grade A 30 kg, Grade B 20 kg, Total 100 kg
        $this->assertEquals(50.0, (float) $parent->total_kg);
        $this->assertEquals(30.0, (float) $childA->total_kg);
        $this->assertEquals(20.0, (float) $childB->total_kg);

        $this->assertEquals(50.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));
        $this->assertEquals(30.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));
        $this->assertEquals(20.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        $this->assertEquals(100.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id));

        // 3. Dispatch: Grade B 10 kg, Mixed 15 kg
        // Dispatch 10 kg Grade B
        StockMovement::create([
            'batch_id' => $childB->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => 'B',
            'type' => StockMovementType::Out->value,
            'quantity' => 10.0,
            'cost_per_unit' => 20.0,
            'notes' => 'Customer dispatch of 10 kg Grade B',
        ]);

        // Dispatch 15 kg Mixed
        StockMovement::create([
            'batch_id' => $parent->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => 'U',
            'type' => StockMovementType::Out->value,
            'quantity' => 15.0,
            'cost_per_unit' => 20.0,
            'notes' => 'Customer dispatch of 15 kg Mixed',
        ]);

        // Expected: Mixed 35 kg, Grade A 30 kg, Grade B 10 kg, Total 75 kg
        $this->assertEquals(35.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));
        $this->assertEquals(30.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));
        $this->assertEquals(10.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        $this->assertEquals(75.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id));

        // Check available Mixed on parent batch directly
        $parent->refresh();
        $this->assertEquals(35.0, $parent->available_mixed_qty);

        // 4. Validate Full Sorting After Partial Segregation and Partial Loadout:
        // Attempting to full sort 50 kg or 100 kg must fail because only 35 kg Mixed is physically available
        try {
            $invalidSort = new SortingData(
                batchId: $parent->id,
                grades: [
                    ['grade' => ProductGrade::GradeA, 'quantity' => 50.0],
                ],
                notes: null,
            );
            app(ProcessBatchSortingAction::class)->execute($parent, $invalidSort, $this->receiver->id);
            $this->fail('Expected exception when attempting to sort previously dispatched or segregated quantity.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('35 kg', $e->getMessage());
        }

        // Full sort operates ONLY on actual remaining 35 kg: 20 kg B + 15 kg C = 35 kg
        $validSort = new SortingData(
            batchId: $parent->id,
            grades: [
                ['grade' => ProductGrade::GradeB, 'quantity' => 20.0],
                ['grade' => ProductGrade::GradeC, 'quantity' => 15.0],
            ],
            notes: 'Sorting remaining 35 kg Mixed',
        );

        app(ProcessBatchSortingAction::class)->execute($parent, $validSort, $this->receiver->id);

        $parent->refresh();
        $this->assertSame(BatchStatus::Sorted, $parent->status);

        // Verify final inventory:
        // Grade A: 30 kg (from child A)
        // Grade B: 10 kg (remaining child B) + 20 kg (from parent sort) = 30 kg
        // Grade C: 15 kg (from parent sort)
        // Mixed: 0 kg
        // Total physical stock: 30 + 30 + 15 = 75 kg (exactly conserved!)
        $this->assertEquals(30.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));
        $this->assertEquals(30.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeB));
        $this->assertEquals(15.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeC));
        $this->assertEquals(0.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));
        $this->assertEquals(75.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id));
    }

    /**
     * Audit Section 5: Real isolated lifecycle
     * Advance Warehouse Receive -> Mixed Batch -> Partial Segregation -> Loadout -> Vendor Bill Matching.
     */
    public function test_14_audit_advance_receipt_lifecycle_segregation_loadout_and_bill_matching(): void
    {
        // 1. Advance Warehouse Receive 100 kg Mixed
        $advGrn = GoodsReceived::create([
            'grn_number' => 'GRN-ADV-LIFECYCLE',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->receiver->id,
            'received_at' => now(),
            'status' => 'approved',
            'is_advance_receive' => true,
            'receipt_type' => 'warehouse_advance',
            'bill_status' => 'bill_pending',
        ]);

        $advItem = $advGrn->items()->create([
            'product_id' => $this->product->id,
            'received_qty' => 100.0,
            'received_unit' => 'KG',
            'unit_id' => null,
            'cost_per_unit' => 20.0,
            'grade' => 'U',
            'grading_mode' => 'sort_required',
            'variance' => 0.0,
        ]);

        $batch = StockBatch::create([
            'reference' => 'BATCH-ADV-LIFE',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $advGrn->id,
            'goods_received_item_id' => $advItem->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        // 2. Partial Segregation: 30 kg Grade A
        $res = $this->segregationAction->execute($batch, ProductGrade::GradeA, 30.0, $this->receiver->id);
        $childA = $res['child'];
        $parent = $res['parent'];

        $this->assertEquals(70.0, (float) $parent->total_kg);
        $this->assertEquals(30.0, (float) $childA->total_kg);

        // 3. Loadout: 10 kg Grade A to customer order
        $order = ShopOrder::create([
            'order_number' => 'ORD-ADV-LOAD',
            'shop_id' => $this->shop->id,
            'created_by' => $this->receiver->id,
            'business_date' => $this->businessDate,
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
            'total_amount' => 500.0,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'product_grade' => 'A',
            'sorting_status' => 'allocated',
            'unit_price' => 50.0,
            'unit' => 'KG',
            'requested_unit' => 'KG',
            'requested_unit_conversion_to_base' => 1.0,
        ]);

        $autoLoadService = app(AutoLoadAllService::class);
        $autoLoadResult = $autoLoadService->loadOrder($order, $this->receiver, $this->warehouse->id);
        $this->assertTrue($autoLoadResult['success']);

        // Physical inventory check:
        // Grade A: 20 kg (30 - 10)
        // Mixed: 70 kg
        // Total physical: 90 kg
        $this->assertEquals(20.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));
        $this->assertEquals(70.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));
        $this->assertEquals(90.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id));

        // 4. Vendor Bill Matching:
        // Vendor sends PO and Bill for 100 kg
        $po = PurchaseOrder::create([
            'po_number' => 'PO-ADV-100',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->receiver->id,
            'order_date' => $this->businessDate,
            'status' => 'approved',
            'total_amount' => 2000.0,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 100.0,
            'unit_price' => 20.0,
            'unit' => 'KG',
            'purchase_unit' => 'KG',
            'base_unit' => 'KG',
            'line_total' => 2000.0,
        ]);

        $billData = GoodsReceivedData::fromRequest(new Request([
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => $this->businessDate,
            'received_by' => $this->receiver->id,
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->product->id,
                    'received_qty' => 100.0,
                    'unit' => 'KG',
                    'unit_id' => null,
                    'cost_per_unit' => 20.0,
                    'grade' => 'U',
                    'grading_mode' => 'sort_required',
                ],
            ],
            'advance_matches' => [
                [
                    'advance_goods_received_id' => $advGrn->id,
                    'advance_goods_received_item_id' => $advItem->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->product->id,
                    'matched_qty' => 100.0,
                    'unit' => 'KG',
                ],
            ],
        ]));

        $reconService = app(AdvanceReceiveReconciliationService::class);
        $reconciledGrn = $reconService->reconcileAndExecute($billData, $this->receiver->id);

        $this->assertNotNull($reconciledGrn);

        // Verification:
        // - Advance matches correctly created for 100 kg
        $matches = AdvanceReceiveMatch::where('advance_goods_received_id', $advGrn->id)->get();
        $this->assertCount(1, $matches);
        $this->assertEquals(100.0, (float) $matches->first()->matched_qty);

        // - Advance GRN bill_status updated to bill_available
        $advGrn->refresh();
        $this->assertSame('bill_available', $advGrn->bill_status);

        // - 0 duplicate physical stock created: NO new pending warehouse batches because unmatched was 0!
        $newBatches = StockBatch::where('goods_received_id', $reconciledGrn->id)->get();
        $this->assertCount(0, $newBatches, 'Fully matched bill must not create duplicate physical stock batches.');

        // - Physical stock remains exactly 90 kg (20 kg Grade A + 70 kg Mixed)
        $this->assertEquals(90.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id));
        $this->assertEquals(20.0, $this->stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));
        $this->assertEquals(70.0, $this->stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));
    }

    /**
     * Audit Section 6: Deletion and Cancellation Guards
     */
    public function test_15_audit_deletion_and_cancellation_guards(): void
    {
        // 1. Cannot delete parent batch if it has active segregated child batches
        $parent = StockBatch::create([
            'reference' => 'BATCH-GUARD-TEST',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        $this->segregationAction->execute($parent, ProductGrade::GradeA, 25.0, $this->receiver->id);

        $response = $this->actingAs($this->receiver)
            ->delete(route('inventory.batches.destroy', $parent));

        $response->assertSessionHas('error');
        $this->assertNull($parent->fresh()->deleted_at, 'Parent batch must not be deleted while child batches exist.');

        // 2. Cannot delete advance receipt if stock from it was already dispatched to orders
        $advGrn = GoodsReceived::create([
            'grn_number' => 'GRN-ADV-DISPATCH-GUARD',
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->receiver->id,
            'received_at' => now(),
            'status' => 'approved',
            'is_advance_receive' => true,
        ]);

        $advItem = $advGrn->items()->create([
            'product_id' => $this->product->id,
            'received_qty' => 50.0,
            'cost_per_unit' => 20.0,
            'grade' => 'U',
            'grading_mode' => 'sort_required',
            'variance' => 0.0,
        ]);

        $batch = StockBatch::create([
            'reference' => 'BATCH-ADV-DISPATCH-GUARD',
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $advGrn->id,
            'goods_received_item_id' => $advItem->id,
            'total_kg' => 50.0,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'created_by' => $this->receiver->id,
        ]);

        // Record a dispatch movement
        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->receiver->id,
            'grade' => 'U',
            'type' => StockMovementType::Out->value,
            'quantity' => 10.0,
            'cost_per_unit' => 20.0,
            'notes' => 'Dispatched 10 kg',
        ]);

        $grnService = app(GoodsReceivedService::class);

        try {
            $grnService->deleteAdvanceReceive($advGrn, $this->receiver->id);
            $this->fail('Expected ValidationException when attempting to delete advance receipt with dispatched stock.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('advance', $e->errors());
        }

        $this->assertNull($advGrn->fresh()->deleted_at, 'Advance GRN must remain active when dispatches have occurred.');
    }
}
