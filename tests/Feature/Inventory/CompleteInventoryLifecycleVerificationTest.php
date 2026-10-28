<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Actions\Inventory\ProcessPartialSegregationAction;
use App\Actions\Inventory\TransferGradeStockAction;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\GoodsReceived;
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
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Inventory\StockAdjustmentService;
use App\Services\Inventory\StockLedgerService;
use App\Services\Purchasing\AdvanceReceiveReconciliationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CompleteInventoryLifecycleVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouseVeg;

    private Warehouse $warehouseFruit;

    private Category $categoryVeg;

    private Category $categoryFruit;

    private Product $tomato;

    private Product $apple;

    private Shop $shop;

    private Supplier $supplier;

    private ShopPriceGroup $priceGroup;

    private StockMovementRepository $stockRepo;

    private StockLedgerService $stockLedgerService;

    private TransferGradeStockAction $transferAction;

    private ProcessPartialSegregationAction $segregationAction;

    private StockAdjustmentService $stockAdjustmentService;

    private AdvanceReceiveReconciliationService $advanceReconciliationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->admin->givePermissionTo(['warehouse.loadout.all']);

        $this->warehouseVeg = Warehouse::factory()->create(['name' => 'Vegetable Central Warehouse', 'code' => 'W1-VEG', 'is_active' => true]);
        $this->warehouseFruit = Warehouse::factory()->create(['name' => 'Fruit Central Warehouse', 'code' => 'W2-FRT', 'is_active' => true]);

        $this->admin->warehouses()->attach([$this->warehouseVeg->id, $this->warehouseFruit->id]);
        $this->actingAs($this->admin);

        $this->categoryVeg = Category::factory()->create(['name' => 'Vegetables', 'is_active' => true]);
        $this->categoryFruit = Category::factory()->create(['name' => 'Fruits', 'is_active' => true]);

        $this->tomato = Product::factory()->create([
            'name' => 'Fresh Tomato',
            'sku' => 'VEG-TOM-001',
            'unit' => 'kg',
            'category_id' => $this->categoryVeg->id,
            'default_warehouse_id' => $this->warehouseVeg->id,
            'carryover_enabled' => true,
        ]);

        $this->apple = Product::factory()->create([
            'name' => 'Royal Gala Apple',
            'sku' => 'FRT-APL-001',
            'unit' => 'kg',
            'category_id' => $this->categoryFruit->id,
            'default_warehouse_id' => $this->warehouseFruit->id,
            'carryover_enabled' => true,
        ]);

        $this->supplier = Supplier::factory()->create(['name' => 'Prime Agritech Farms']);
        $this->priceGroup = ShopPriceGroup::factory()->create(['name' => 'A', 'is_active' => true]);
        $this->shop = Shop::factory()->create([
            'name' => 'Emerald Hotel',
            'shop_price_group_id' => $this->priceGroup->id,
            'status' => 'active',
            'allowed_product_grades' => ['A', 'B', 'C'],
        ]);

        $this->stockRepo = app(StockMovementRepository::class);
        $this->stockLedgerService = app(StockLedgerService::class);
        $this->transferAction = app(TransferGradeStockAction::class);
        $this->segregationAction = app(ProcessPartialSegregationAction::class);
        $this->stockAdjustmentService = app(StockAdjustmentService::class);
        $this->advanceReconciliationService = app(AdvanceReceiveReconciliationService::class);

        $this->withHeader('X-Client-Supports-Multi-Grade', 'true');
    }

    /**
     * Operation 1: Normal purchase receiving.
     */
    public function test_01_normal_purchase_receiving(): void
    {
        $date = '2026-10-28';
        $batch = StockBatch::factory()->create([
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 25.0,
            'purchase_grade' => 'A',
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'received_at' => $date,
        ]);
        StockMovement::create([
            'product_id' => $this->tomato->id,
            'batch_id' => $batch->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 100.0,
            'cost_per_unit' => 25.0,
            'notes' => 'Normal purchase receiving',
        ]);

        $this->assertEquals(100.0, (float) $batch->fresh()->total_kg);
        $this->assertEquals(100.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouseVeg->id, $date));
        $this->assertEquals(100.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $date));
        $this->assertEquals(0.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseFruit->id, $date));
    }

    /**
     * Operation 2: Pending bill receiving.
     */
    public function test_02_pending_bill_receiving(): void
    {
        $date = '2026-10-28';
        $grn = GoodsReceived::create([
            'warehouse_id' => $this->warehouseVeg->id,
            'grn_number' => 'GRN-PENDING-002',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'regular',
            'received_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'received_at' => $date,
            'approved_at' => now(),
        ]);
        $grnItem = $grn->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 60.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'grade' => 'A',
        ]);
        $batch = StockBatch::factory()->create([
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $grnItem->id,
            'total_kg' => 60.0,
            'cost_per_kg' => 26.0,
            'purchase_grade' => 'A',
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'received_at' => $date,
        ]);
        StockMovement::create([
            'product_id' => $this->tomato->id,
            'batch_id' => $batch->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 60.0,
            'cost_per_unit' => 26.0,
            'notes' => 'Pending bill physical intake',
        ]);

        $this->assertEquals(60.0, (float) $batch->fresh()->total_kg);
        $this->assertEquals(60.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouseVeg->id, $date));
        $this->assertEquals(60.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $date));
    }

    /**
     * Operation 3: Advance receiving and bill matching without duplicate stock.
     */
    public function test_03_advance_receiving_and_bill_matching_without_duplicate_stock(): void
    {
        $date = '2026-10-28';
        $grnAdvance = GoodsReceived::create([
            'warehouse_id' => $this->warehouseVeg->id,
            'grn_number' => 'GRN-ADV-003',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'received_at' => $date,
            'approved_at' => now(),
        ]);
        $grnItem = $grnAdvance->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'grade' => 'U',
        ]);
        StockBatch::factory()->create([
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'goods_received_id' => $grnAdvance->id,
            'goods_received_item_id' => $grnItem->id,
            'total_kg' => 50.0,
            'cost_per_kg' => 20.0,
            'purchase_grade' => 'U',
            'status' => BatchStatus::Pending->value,
            'warehouse_receive_pending' => false,
            'received_at' => $date,
        ]);

        $this->assertEquals(50.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $date));

        // Reconcile with bill
        $billPayload = [
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'bill_received',
            'bill_number' => 'BILL-MATCH-003',
            'received_at' => $date,
            'items' => [
                ['product_id' => $this->tomato->id, 'received_qty' => 50.0, 'received_unit' => 'kg', 'grade' => 'U'],
            ],
            'advance_matches' => [
                ['advance_goods_received_id' => $grnAdvance->id, 'advance_goods_received_item_id' => $grnItem->id, 'product_id' => $this->tomato->id, 'matched_qty' => 50.0, 'unit' => 'kg'],
            ],
        ];
        $goodsReceivedData = GoodsReceivedData::fromRequest(new Request($billPayload));
        $billGrn = $this->advanceReconciliationService->reconcileAndExecute($goodsReceivedData, $this->admin->id);
        $this->assertSame('bill_available', $billGrn->bill_status);

        // Assert stock remains exactly 50 kg without duplicate injection
        $this->assertEquals(50.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $date));
    }

    /**
     * Operation 4: Mixed/U segregation into Grade A/B/C.
     */
    public function test_04_mixed_u_segregation_into_grades(): void
    {
        $date = '2026-10-28';
        $batch = StockBatch::factory()->create([
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 20.0,
            'purchase_grade' => 'U',
            'status' => BatchStatus::Pending->value,
            'warehouse_receive_pending' => false,
            'received_at' => $date,
        ]);

        $result = $this->segregationAction->execute(
            $batch,
            ProductGrade::GradeA,
            40.0,
            (int) $this->admin->id,
            'Segregate 40 kg to Grade A'
        );

        $this->assertEquals(60.0, (float) $batch->fresh()->total_kg);
        $this->assertEquals(40.0, (float) $result['child']->total_kg);
        $this->assertEquals(40.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouseVeg->id, $date));
        $this->assertEquals(60.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::Unsorted, $this->warehouseVeg->id, $date));
        $this->assertEquals(100.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $date));
    }

    /**
     * Operation 5: Multiple and partial grading operations.
     */
    public function test_05_multiple_and_partial_grading_operations(): void
    {
        $date = '2026-10-28';
        $batch = StockBatch::factory()->create([
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'total_kg' => 90.0,
            'cost_per_kg' => 22.0,
            'purchase_grade' => 'U',
            'status' => BatchStatus::Pending->value,
            'warehouse_receive_pending' => false,
            'received_at' => $date,
        ]);

        // Segregate 30 kg to A, 40 kg to B, 20 kg to C
        $this->segregationAction->execute($batch, ProductGrade::GradeA, 30.0, (int) $this->admin->id);
        $this->segregationAction->execute($batch->fresh(), ProductGrade::GradeB, 40.0, (int) $this->admin->id);
        $this->segregationAction->execute($batch->fresh(), ProductGrade::GradeC, 20.0, (int) $this->admin->id);

        $this->assertEquals(0.0, (float) $batch->fresh()->total_kg);
        $this->assertSame(BatchStatus::Closed, $batch->fresh()->status);
        $this->assertEquals(30.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouseVeg->id, $date));
        $this->assertEquals(40.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeB, $this->warehouseVeg->id, $date));
        $this->assertEquals(20.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeC, $this->warehouseVeg->id, $date));
        $this->assertEquals(90.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $date));
    }

    /**
     * Operation 6: Grade-wise shop loadout and quantity updates.
     */
    public function test_06_grade_wise_shop_loadout_and_quantity_updates(): void
    {
        $date = '2026-10-28';
        $batch = StockBatch::factory()->create([
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 25.0,
            'purchase_grade' => 'A',
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'received_at' => $date,
        ]);
        StockMovement::create([
            'product_id' => $this->tomato->id,
            'batch_id' => $batch->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 100.0,
            'cost_per_unit' => 25.0,
            'notes' => 'Stock creation',
        ]);

        DailyPriceApproval::create([
            'product_id' => $this->tomato->id,
            'business_date' => $date,
            'grade' => 'A',
            'purchase_price' => 25.0,
            'price_unit' => 'kg',
            'price_a' => 45.0,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'delivery_status' => 'pending_delivery',
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 40.0,
            'approved_qty' => 40.0,
            'loaded_qty' => 0.0,
            'unit' => 'kg',
            'sorting_status' => 'allocated',
        ]);

        // Load 40 kg
        $this->postJson("/api/v1/warehouse/loadout/{$order->id}/save", ['items' => ["{$this->tomato->id}|A" => 40.0]])->assertOk();
        $this->assertEquals(60.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $date));

        // Update loadout to 25 kg (restores 15 kg)
        $this->postJson("/api/v1/warehouse/loadout/{$order->id}/save", ['items' => ["{$this->tomato->id}|A" => 25.0]])->assertOk();
        $this->assertEquals(75.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $date));
    }

    /**
     * Operation 7: Loadout cancellation and stock restoration.
     */
    public function test_07_loadout_cancellation_and_stock_restoration(): void
    {
        $date = '2026-10-28';
        $batch = StockBatch::factory()->create([
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'total_kg' => 50.0,
            'cost_per_kg' => 25.0,
            'purchase_grade' => 'A',
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'received_at' => $date,
        ]);
        StockMovement::create([
            'product_id' => $this->tomato->id,
            'batch_id' => $batch->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 50.0,
            'cost_per_unit' => 25.0,
            'notes' => 'Stock creation',
        ]);

        $order = ShopOrder::factory()->create(['shop_id' => $this->shop->id, 'business_date' => $date, 'delivery_status' => 'pending_delivery']);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 30.0,
            'approved_qty' => 30.0,
            'loaded_qty' => 0.0,
            'unit' => 'kg',
            'sorting_status' => 'allocated',
        ]);

        // Load 30 kg
        $this->postJson("/api/v1/warehouse/loadout/{$order->id}/save", ['items' => ["{$this->tomato->id}|A" => 30.0]])->assertOk();
        $this->assertEquals(20.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $date));

        // Cancel loadout by setting loaded_qty to 0
        $this->postJson("/api/v1/warehouse/loadout/{$order->id}/save", ['items' => ["{$this->tomato->id}|A" => 0.0]])->assertOk();
        $this->assertEquals(50.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $date));
    }

    /**
     * Operation 8: Move usable stock into DMG.
     */
    public function test_08_move_usable_stock_into_dmg(): void
    {
        $date = '2026-10-28';
        $batch = StockBatch::factory()->create([
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'total_kg' => 70.0,
            'cost_per_kg' => 25.0,
            'purchase_grade' => 'A',
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'received_at' => $date,
        ]);
        StockMovement::create([
            'product_id' => $this->tomato->id,
            'batch_id' => $batch->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 70.0,
            'cost_per_unit' => 25.0,
            'notes' => 'Stock creation',
        ]);

        $res = $this->transferAction->execute(
            product: $this->tomato,
            warehouse: $this->warehouseVeg,
            sourceGrade: ProductGrade::GradeA,
            targetGrade: ProductGrade::Damage,
            quantity: 20.0,
            userId: (int) $this->admin->id
        );

        $this->assertSame('DMG', $res['destination_batch']->purchase_grade);
        $this->assertEquals(50.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouseVeg->id, $date));
        $this->assertEquals(20.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::Damage, $this->warehouseVeg->id, $date));
        $this->assertEquals(70.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $date));
    }

    /**
     * Operation 9: Dispose damaged stock & verify idempotency.
     */
    public function test_09_dispose_damaged_stock_and_idempotency(): void
    {
        $date = '2026-10-28';
        $batchDmg = StockBatch::factory()->create([
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'total_kg' => 25.0,
            'cost_per_kg' => 25.0,
            'purchase_grade' => 'DMG',
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'received_at' => $date,
        ]);
        StockMovement::create([
            'product_id' => $this->tomato->id,
            'batch_id' => $batchDmg->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'created_by' => $this->admin->id,
            'grade' => 'DMG',
            'type' => StockMovementType::In->value,
            'quantity' => 25.0,
            'cost_per_unit' => 25.0,
            'notes' => 'Damaged stock intake',
        ]);

        // Dispose 10 kg from DMG
        $this->post(route('inventory.daily-close.store'), [
            'date' => $date,
            'lines' => [
                ['product_id' => $this->tomato->id, 'warehouse_id' => $this->warehouseVeg->id, 'grade' => 'DMG', 'closing_qty' => 25.0, 'wastage_qty' => 10.0, 'carryover_qty' => 15.0, 'carryover_enabled' => 1],
            ],
        ])->assertRedirect();

        $this->assertEquals(15.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::Damage, $this->warehouseVeg->id, $date));
        $this->assertEquals(15.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $date));

        // Resubmit identical values (idempotency)
        $movementsCount = StockMovement::count();
        $this->post(route('inventory.daily-close.store'), [
            'date' => $date,
            'lines' => [
                ['product_id' => $this->tomato->id, 'warehouse_id' => $this->warehouseVeg->id, 'grade' => 'DMG', 'closing_qty' => 25.0, 'wastage_qty' => 10.0, 'carryover_qty' => 15.0, 'carryover_enabled' => 1],
            ],
        ])->assertRedirect();

        $this->assertEquals(15.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $date));
        $this->assertSame($movementsCount, StockMovement::count());
    }

    /**
     * Operation 10: Daily inventory checking and authorized stock adjustments.
     */
    public function test_10_daily_inventory_checking_and_authorized_stock_adjustments(): void
    {
        $date = '2026-10-28';
        $batch = StockBatch::factory()->create([
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'total_kg' => 80.0,
            'cost_per_kg' => 25.0,
            'purchase_grade' => 'A',
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'received_at' => $date,
        ]);
        StockMovement::create([
            'product_id' => $this->tomato->id,
            'batch_id' => $batch->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 80.0,
            'cost_per_unit' => 25.0,
            'notes' => 'Stock creation',
        ]);

        // Physical count: 76 kg counted (variance: -4 kg)
        $adj = $this->stockAdjustmentService->reconcile(
            product: $this->tomato,
            submittedSystemQty: 80.0,
            countedQty: 76.0,
            businessDate: $date,
            notes: 'Physical count audit: -4 kg shrinkage',
            userId: (int) $this->admin->id,
            warehouseId: $this->warehouseVeg->id,
            grade: 'A'
        );

        $this->assertNotNull($adj);
        $this->assertEquals(-4.0, (float) $adj->variance_qty);
        $this->assertEquals(76.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouseVeg->id, $date));
        $this->assertEquals(76.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $date));
    }

    /**
     * Operation 11: Correct stock carryover to the next day.
     */
    public function test_11_correct_stock_carryover_to_next_day(): void
    {
        $day1 = '2026-10-28';
        $day2 = '2026-10-29';
        Carbon::setTestNow($day1.' 18:00:00');

        $batch = StockBatch::factory()->create([
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'total_kg' => 65.0,
            'cost_per_kg' => 25.0,
            'purchase_grade' => 'A',
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'received_at' => $day1,
        ]);
        StockMovement::create([
            'product_id' => $this->tomato->id,
            'batch_id' => $batch->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 65.0,
            'cost_per_unit' => 25.0,
            'notes' => 'Stock creation Day 1',
        ]);

        $this->assertEquals(65.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $day1));

        // Advance to Day 2
        Carbon::setTestNow($day2.' 08:00:00');

        // Day 2 opening stock is automatically carried over
        $this->assertEquals(65.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouseVeg->id, $day2));
        $this->assertEquals(65.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $day2));

        Carbon::setTestNow();
    }

    /**
     * Complete Sequential End-to-End Workflow Verification across Operations 1-11 with multi-warehouse isolation.
     */
    public function test_12_complete_sequential_end_to_end_lifecycle_integration(): void
    {
        $day1 = '2026-10-28';
        $day2 = '2026-10-29';
        Carbon::setTestNow($day1.' 08:00:00');

        // Step 1: Normal purchase receiving
        $batchVegNormal = StockBatch::factory()->create([
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 25.0,
            'purchase_grade' => 'A',
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'received_at' => $day1,
        ]);
        StockMovement::create([
            'product_id' => $this->tomato->id,
            'batch_id' => $batchVegNormal->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 100.0,
            'cost_per_unit' => 25.0,
            'notes' => 'Step 1: Normal receiving (Tomato A)',
            'created_at' => $day1.' 08:15:00',
        ]);

        $batchFruitNormal = StockBatch::factory()->create([
            'product_id' => $this->apple->id,
            'warehouse_id' => $this->warehouseFruit->id,
            'total_kg' => 80.0,
            'cost_per_kg' => 60.0,
            'purchase_grade' => 'B',
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'received_at' => $day1,
        ]);
        StockMovement::create([
            'product_id' => $this->apple->id,
            'batch_id' => $batchFruitNormal->id,
            'warehouse_id' => $this->warehouseFruit->id,
            'created_by' => $this->admin->id,
            'grade' => 'B',
            'type' => StockMovementType::In->value,
            'quantity' => 80.0,
            'cost_per_unit' => 60.0,
            'notes' => 'Step 1: Normal receiving (Apple B)',
            'created_at' => $day1.' 08:20:00',
        ]);

        $this->assertEquals(100.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $day1));
        $this->assertEquals(80.0, $this->stockRepo->currentStockForProduct($this->apple->id, $this->warehouseFruit->id, $day1));
        $this->assertEquals(0.0, $this->stockRepo->currentStockForProduct($this->apple->id, $this->warehouseVeg->id, $day1));
        $this->assertEquals(0.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseFruit->id, $day1));

        // Step 2: Pending bill receiving
        Carbon::setTestNow($day1.' 09:00:00');
        $grnPendingBill = GoodsReceived::create([
            'warehouse_id' => $this->warehouseVeg->id,
            'grn_number' => 'GRN-PENDING-E2E-01',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'regular',
            'received_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'received_at' => $day1,
            'approved_at' => now(),
        ]);
        $grnPendingBillItem = $grnPendingBill->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'grade' => 'A',
        ]);
        $batchPendingBill = StockBatch::factory()->create([
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'goods_received_id' => $grnPendingBill->id,
            'goods_received_item_id' => $grnPendingBillItem->id,
            'total_kg' => 50.0,
            'cost_per_kg' => 26.0,
            'purchase_grade' => 'A',
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'received_at' => $day1,
        ]);
        StockMovement::create([
            'product_id' => $this->tomato->id,
            'batch_id' => $batchPendingBill->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'created_by' => $this->admin->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 50.0,
            'cost_per_unit' => 26.0,
            'notes' => 'Step 2: Pending bill receiving',
            'created_at' => $day1.' 09:10:00',
        ]);
        $this->assertEquals(150.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $day1));

        // Step 3: Advance receiving and bill matching
        Carbon::setTestNow($day1.' 10:00:00');
        $grnAdv = GoodsReceived::create([
            'warehouse_id' => $this->warehouseVeg->id,
            'grn_number' => 'GRN-ADV-E2E-01',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'received_at' => $day1,
            'approved_at' => now(),
        ]);
        $grnAdvItem = $grnAdv->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 40.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'grade' => 'U',
        ]);
        $batchAdv = StockBatch::factory()->create([
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'goods_received_id' => $grnAdv->id,
            'goods_received_item_id' => $grnAdvItem->id,
            'total_kg' => 40.0,
            'cost_per_kg' => 20.0,
            'purchase_grade' => 'U',
            'status' => BatchStatus::Pending->value,
            'warehouse_receive_pending' => false,
            'received_at' => $day1,
        ]);
        $this->assertEquals(190.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $day1));

        $billPayload = [
            'warehouse_id' => $this->warehouseVeg->id,
            'receipt_type' => 'bill_received',
            'bill_number' => 'BILL-E2E-MATCH-01',
            'received_at' => $day1,
            'items' => [['product_id' => $this->tomato->id, 'received_qty' => 40.0, 'received_unit' => 'kg', 'grade' => 'U']],
            'advance_matches' => [['advance_goods_received_id' => $grnAdv->id, 'advance_goods_received_item_id' => $grnAdvItem->id, 'product_id' => $this->tomato->id, 'matched_qty' => 40.0, 'unit' => 'kg']],
        ];
        $this->advanceReconciliationService->reconcileAndExecute(GoodsReceivedData::fromRequest(new Request($billPayload)), $this->admin->id);
        $this->assertEquals(190.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $day1));

        // Step 4: Mixed/U segregation into Grade A
        Carbon::setTestNow($day1.' 11:00:00');
        $this->segregationAction->execute($batchAdv, ProductGrade::GradeA, 20.0, (int) $this->admin->id);
        $this->assertEquals(170.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouseVeg->id, $day1));
        $this->assertEquals(20.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::Unsorted, $this->warehouseVeg->id, $day1));
        $this->assertEquals(190.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $day1));

        // Step 5: Multiple and partial grading
        Carbon::setTestNow($day1.' 11:30:00');
        $this->segregationAction->execute($batchAdv->fresh(), ProductGrade::GradeB, 12.0, (int) $this->admin->id);
        $this->segregationAction->execute($batchAdv->fresh(), ProductGrade::GradeC, 8.0, (int) $this->admin->id);
        $this->assertEquals(170.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouseVeg->id, $day1));
        $this->assertEquals(12.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeB, $this->warehouseVeg->id, $day1));
        $this->assertEquals(8.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeC, $this->warehouseVeg->id, $day1));
        $this->assertEquals(190.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $day1));

        // Step 6: Grade-wise shop loadout and quantity updates
        Carbon::setTestNow($day1.' 13:00:00');
        DailyPriceApproval::create([
            'product_id' => $this->tomato->id,
            'business_date' => $day1,
            'grade' => 'A',
            'purchase_price' => 25.0,
            'price_unit' => 'kg',
            'price_a' => 45.0,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);
        DailyPriceApproval::create([
            'product_id' => $this->tomato->id,
            'business_date' => $day1,
            'grade' => 'B',
            'purchase_price' => 20.0,
            'price_unit' => 'kg',
            'price_a' => 38.0,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);

        $order = ShopOrder::factory()->create(['shop_id' => $this->shop->id, 'business_date' => $day1, 'delivery_status' => 'pending_delivery']);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 30.0,
            'approved_qty' => 30.0,
            'loaded_qty' => 0.0,
            'unit' => 'kg',
            'sorting_status' => 'allocated',
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 0.0,
            'unit' => 'kg',
            'sorting_status' => 'allocated',
        ]);

        $this->postJson("/api/v1/warehouse/loadout/{$order->id}/save", ['items' => ["{$this->tomato->id}|A" => 30.0, "{$this->tomato->id}|B" => 10.0]])->assertOk();
        $this->assertEquals(150.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $day1));

        $this->postJson("/api/v1/warehouse/loadout/{$order->id}/save", ['items' => ["{$this->tomato->id}|A" => 22.0, "{$this->tomato->id}|B" => 12.0]])->assertOk();
        $this->assertEquals(156.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $day1));

        // Step 7: Loadout cancellation and stock restoration
        Carbon::setTestNow($day1.' 14:00:00');
        $this->postJson("/api/v1/warehouse/loadout/{$order->id}/save", ['items' => ["{$this->tomato->id}|A" => 0.0, "{$this->tomato->id}|B" => 0.0]])->assertOk();
        $this->assertEquals(190.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $day1));

        // Step 8: Move usable stock into DMG
        Carbon::setTestNow($day1.' 15:00:00');
        $this->transferAction->execute(
            product: $this->tomato,
            warehouse: $this->warehouseVeg,
            sourceGrade: ProductGrade::GradeA,
            targetGrade: ProductGrade::Damage,
            quantity: 15.0,
            userId: (int) $this->admin->id
        );
        $this->assertEquals(155.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouseVeg->id, $day1));
        $this->assertEquals(15.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::Damage, $this->warehouseVeg->id, $day1));
        $this->assertEquals(190.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $day1));

        // Step 9: Dispose damaged stock
        Carbon::setTestNow($day1.' 17:00:00');
        $this->post(route('inventory.daily-close.store'), [
            'date' => $day1,
            'lines' => [
                ['product_id' => $this->tomato->id, 'warehouse_id' => $this->warehouseVeg->id, 'grade' => 'DMG', 'closing_qty' => 15.0, 'wastage_qty' => 5.0, 'carryover_qty' => 10.0, 'carryover_enabled' => 1],
            ],
        ])->assertRedirect();
        $this->assertEquals(10.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::Damage, $this->warehouseVeg->id, $day1));
        $this->assertEquals(185.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $day1));

        // Step 10: Daily inventory checking and authorized stock adjustments
        Carbon::setTestNow($day1.' 18:00:00');
        $this->stockAdjustmentService->reconcile(
            product: $this->tomato,
            submittedSystemQty: 155.0,
            countedQty: 150.0,
            businessDate: $day1,
            notes: 'Physical count adjustment: -5 kg',
            userId: (int) $this->admin->id,
            warehouseId: $this->warehouseVeg->id,
            grade: 'A'
        );
        $this->assertEquals(150.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouseVeg->id, $day1));
        $this->assertEquals(180.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $day1));

        // Step 11: Correct stock carryover to next day
        Carbon::setTestNow($day2.' 08:00:00');
        $this->assertEquals(150.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouseVeg->id, $day2));
        $this->assertEquals(12.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeB, $this->warehouseVeg->id, $day2));
        $this->assertEquals(8.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeC, $this->warehouseVeg->id, $day2));
        $this->assertEquals(10.0, $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::Damage, $this->warehouseVeg->id, $day2));
        $this->assertEquals(180.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseVeg->id, $day2));

        // Apple in W2 remained untouched across all vegetable operations and carries over
        $this->assertEquals(80.0, $this->stockRepo->stockForProductAndGrade($this->apple->id, ProductGrade::GradeB, $this->warehouseFruit->id, $day2));
        $this->assertEquals(80.0, $this->stockRepo->currentStockForProduct($this->apple->id, $this->warehouseFruit->id, $day2));
        $this->assertEquals(0.0, $this->stockRepo->currentStockForProduct($this->apple->id, $this->warehouseVeg->id, $day2));
        $this->assertEquals(0.0, $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouseFruit->id, $day2));

        Carbon::setTestNow();
    }
}
