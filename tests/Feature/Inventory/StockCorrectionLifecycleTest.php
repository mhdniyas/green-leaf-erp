<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Purchasing\POStatus;
use App\Models\AdvanceReceiveMatch;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Inventory\StockLedgerService;
use App\Services\Purchasing\DailyAdvanceMatchExecutionService;
use App\Services\Purchasing\DailyAdvanceMatchPlanningService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockCorrectionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    private Warehouse $warehouse2;

    private Product $product;

    private Supplier $supplier;

    private Shop $shop;

    private StockMovementRepository $stockRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo([
            'inventory.product.view',
            'inventory.stock.view',
            'inventory.stock.adjust',
            'purchasing.grn.create',
            'purchasing.grn.approve',
            'warehouse.receive.view',
            'warehouse.receive.confirm',
            'warehouse.loadout.all',
        ]);

        $this->warehouse = Warehouse::factory()->create(['name' => 'Vegetable Warehouse', 'code' => 'VEG-WH']);
        $this->warehouse2 = Warehouse::factory()->create(['name' => 'Fruit Warehouse', 'code' => 'FRT-WH']);
        $this->user->warehouses()->attach([$this->warehouse->id, $this->warehouse2->id]);
        $this->actingAs($this->user);

        $this->supplier = Supplier::factory()->create();
        $priceGroup = ShopPriceGroup::factory()->create(['name' => 'A', 'is_active' => true]);
        $this->shop = Shop::factory()->create(['shop_price_group_id' => $priceGroup->id]);

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $this->product = Product::factory()->create([
            'name' => 'Tomato H',
            'sku' => 'VEG-TOM-H',
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'unit' => 'kg',
            'is_active' => true,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $this->product->id,
            'business_date' => '2026-10-15',
            'purchase_price' => 30.0,
            'price_unit' => 'kg',
            'price_a' => 50.0,
            'price_b' => 45.0,
            'price_c' => 40.0,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->user->id,
        ]);

        $this->stockRepo = app(StockMovementRepository::class);
    }

    public function test_mandatory_ten_step_lifecycle(): void
    {
        $businessDate = '2026-10-15';
        Carbon::setTestNow('2026-10-15 08:00:00');

        // Historical unclosed pending batches from August 2026 (500 kg)
        $historicalBatch = StockBatch::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'total_kg' => 500.0,
            'received_at' => '2026-08-15',
            'created_at' => '2026-08-15 10:00:00',
        ]);

        // Historical negative loadout movement (-50 kg)
        StockMovement::create([
            'product_id' => $this->product->id,
            'batch_id' => $historicalBatch->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'grade' => ProductGrade::GradeA->value,
            'type' => StockMovementType::Out->value,
            'quantity' => 50.0,
            'cost_per_unit' => 30.0,
            'created_at' => '2026-08-16 11:00:00',
            'notes' => 'Old historical loadout',
        ]);

        // Prior to correction: stock reflects historical records (450 kg)
        $initialStock = $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $businessDate);
        $this->assertEquals(450.0, $initialStock);

        // Step 1: Manually set current stock to 100 kg
        Carbon::setTestNow('2026-10-15 10:00:00');
        $response = $this->post(route('inventory.stock.adjustments.store', $this->product), [
            'system_qty' => $initialStock,
            'counted_qty' => 100.0,
            'business_date' => $businessDate,
            'warehouse_id' => $this->warehouse->id,
            'notes' => 'Morning physical audit',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('stock_adjustments', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'counted_qty' => 100.0,
        ]);

        $stockStep1 = $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $businessDate);
        $this->assertEquals(100.0, $stockStep1, 'Step 1: Stock must equal entered count of 100 kg');

        // Step 2: Refresh page -> 100 kg (historical pending batches must NOT reappear)
        Carbon::setTestNow('2026-10-15 10:05:00');
        $pageResponse = $this->get(route('inventory.stock.index', ['date' => $businessDate, 'warehouse_id' => $this->warehouse->id]));
        $pageResponse->assertOk();
        $stockStep2 = $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $businessDate);
        $this->assertEquals(100.0, $stockStep2, 'Step 2: Refresh must maintain corrected 100 kg baseline');

        // Step 3: Receive advance 50 kg -> 150 kg
        Carbon::setTestNow('2026-10-15 10:10:00');
        $advanceGrn = $this->createAndConfirmAdvanceGrn(50.0, $businessDate);
        $stockStep3 = $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $businessDate);
        $this->assertEquals(150.0, $stockStep3, 'Step 3: Advance receipt must increase stock to 150 kg');

        // Step 4: Loadout 30 kg -> 120 kg
        Carbon::setTestNow('2026-10-15 10:15:00');
        $this->performLoadout(30.0, $businessDate);
        $stockStep4 = $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $businessDate);
        $this->assertEquals(120.0, $stockStep4, 'Step 4: Loadout of 30 kg must deduct stock to 120 kg');

        // Step 5: Create and match purchase bill for the 50 kg advance -> 120 kg (no duplicate stock)
        Carbon::setTestNow('2026-10-15 10:20:00');
        $this->createAndMatchPurchaseBill($advanceGrn, 50.0, $businessDate);
        $stockStep5 = $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $businessDate);
        $this->assertEquals(120.0, $stockStep5, 'Step 5: Advance matching must create no additional stock (remains 120 kg)');

        // Step 6: Receive another genuine 20 kg -> 140 kg
        Carbon::setTestNow('2026-10-15 10:25:00');
        $this->createAndConfirmDirectGrn(20.0, $businessDate);
        $stockStep6 = $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $businessDate);
        $this->assertEquals(140.0, $stockStep6, 'Step 6: Genuine new receipt must increase stock to 140 kg');

        // Step 7: Manually correct stock to 90 kg -> 90 kg
        Carbon::setTestNow('2026-10-15 10:30:00');
        $this->post(route('inventory.stock.adjustments.store', $this->product), [
            'system_qty' => $stockStep6,
            'counted_qty' => 90.0,
            'business_date' => $businessDate,
            'warehouse_id' => $this->warehouse->id,
            'notes' => 'Second manual adjustment after recount',
        ]);
        $stockStep7 = $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $businessDate);
        $this->assertEquals(90.0, $stockStep7, 'Step 7: Manual correction sets stock to 90 kg');

        // Step 8: Refresh -> 90 kg
        Carbon::setTestNow('2026-10-15 10:35:00');
        $this->get(route('inventory.stock.index', ['date' => $businessDate, 'warehouse_id' => $this->warehouse->id]))->assertOk();
        $stockStep8 = $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $businessDate);
        $this->assertEquals(90.0, $stockStep8, 'Step 8: Refresh maintains 90 kg baseline');

        // Step 9: Loadout 10 kg -> 80 kg
        Carbon::setTestNow('2026-10-15 10:40:00');
        $this->performLoadout(10.0, $businessDate);
        $stockStep9 = $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $businessDate);
        $this->assertEquals(80.0, $stockStep9, 'Step 9: Loadout of 10 kg deducts stock to 80 kg');

        // Step 10: Re-run matching -> 80 kg
        Carbon::setTestNow('2026-10-15 10:45:00');
        $plan = app(DailyAdvanceMatchPlanningService::class)->buildDailyPlan($this->warehouse->id, $businessDate);
        app(DailyAdvanceMatchExecutionService::class)->execute(
            $this->warehouse->id,
            $businessDate,
            $plan['plan_hash'],
            'SUB-'.Str::random(10),
            $this->user->id
        );
        $stockStep10 = $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $businessDate);
        $this->assertEquals(80.0, $stockStep10, 'Step 10: Re-running matching leaves stock at 80 kg');
    }

    public function test_multiple_warehouses_independence(): void
    {
        $businessDate = '2026-10-15';
        Carbon::setTestNow('2026-10-15 09:00:00');

        // Warehouse 1 correction to 100 kg
        $this->post(route('inventory.stock.adjustments.store', $this->product), [
            'system_qty' => 0.0,
            'counted_qty' => 100.0,
            'business_date' => $businessDate,
            'warehouse_id' => $this->warehouse->id,
            'notes' => 'WH1 count',
        ]);

        // Warehouse 2 correction to 50 kg
        $this->post(route('inventory.stock.adjustments.store', $this->product), [
            'system_qty' => 0.0,
            'counted_qty' => 50.0,
            'business_date' => $businessDate,
            'warehouse_id' => $this->warehouse2->id,
            'notes' => 'WH2 count',
        ]);

        $wh1Stock = $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $businessDate);
        $wh2Stock = $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse2->id, $businessDate);
        $allStock = $this->stockRepo->currentStockForProduct($this->product->id, null, $businessDate);

        $this->assertEquals(100.0, $wh1Stock, 'Warehouse 1 stock must be 100 kg');
        $this->assertEquals(50.0, $wh2Stock, 'Warehouse 2 stock must be 50 kg');
        $this->assertEquals(150.0, $allStock, 'All warehouses stock must equal 150 kg');

        // Loadout from Warehouse 1 of 20 kg
        Carbon::setTestNow('2026-10-15 10:00:00');
        app(StockLedgerService::class)->consumeStockForProductAllowingNegative(
            $this->product->id,
            20.0,
            $this->user->id,
            StockMovementType::Out,
            'WH1 loadout',
            null,
            $this->warehouse->id,
            ProductGrade::GradeA,
        );

        $this->assertEquals(80.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $businessDate));
        $this->assertEquals(50.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse2->id, $businessDate));
        $this->assertEquals(130.0, $this->stockRepo->currentStockForProduct($this->product->id, null, $businessDate));
    }

    public function test_grade_specific_corrections_and_movements(): void
    {
        $businessDate = '2026-10-15';
        Carbon::setTestNow('2026-10-15 09:00:00');
        $this->withoutExceptionHandling();

        // Adjust Grade A to 60 kg
        $this->post(route('inventory.stock.adjustments.store', $this->product), [
            'system_qty' => 0.0,
            'counted_qty' => 60.0,
            'business_date' => $businessDate,
            'warehouse_id' => $this->warehouse->id,
            'grade' => 'A',
            'notes' => 'Grade A count',
        ]);

        // Adjust Grade B to 40 kg
        $this->post(route('inventory.stock.adjustments.store', $this->product), [
            'system_qty' => 0.0,
            'counted_qty' => 40.0,
            'business_date' => $businessDate,
            'warehouse_id' => $this->warehouse->id,
            'grade' => 'B',
            'notes' => 'Grade B count',
        ]);

        $this->assertEquals(60.0, $this->stockRepo->stockForProductAndGrade($this->product->id, ProductGrade::GradeA));
        $this->assertEquals(40.0, $this->stockRepo->stockForProductAndGrade($this->product->id, ProductGrade::GradeB));
        $this->assertEquals(100.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $businessDate));

        // Receive 50 kg Unsorted advance batch
        Carbon::setTestNow('2026-10-15 10:00:00');
        $this->createAndConfirmAdvanceGrn(50.0, $businessDate);

        $this->assertEquals(60.0, $this->stockRepo->stockForProductAndGrade($this->product->id, ProductGrade::GradeA));
        $this->assertEquals(40.0, $this->stockRepo->stockForProductAndGrade($this->product->id, ProductGrade::GradeB));
        $this->assertEquals(50.0, $this->stockRepo->stockForProductAndGrade($this->product->id, ProductGrade::Unsorted));
        $this->assertEquals(150.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $businessDate));

        // Loadout 20 kg from Grade A
        Carbon::setTestNow('2026-10-15 11:00:00');
        app(StockLedgerService::class)->consumeStockForProductAllowingNegative(
            $this->product->id,
            20.0,
            $this->user->id,
            StockMovementType::Out,
            'Grade A loadout',
            null,
            $this->warehouse->id,
            ProductGrade::GradeA,
        );

        $this->assertEquals(40.0, $this->stockRepo->stockForProductAndGrade($this->product->id, ProductGrade::GradeA));
        $this->assertEquals(40.0, $this->stockRepo->stockForProductAndGrade($this->product->id, ProductGrade::GradeB));
        $this->assertEquals(50.0, $this->stockRepo->stockForProductAndGrade($this->product->id, ProductGrade::Unsorted));
        $this->assertEquals(130.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $businessDate));
    }

    public function test_repeated_submission_is_idempotent(): void
    {
        $businessDate = '2026-10-15';
        Carbon::setTestNow('2026-10-15 09:00:00');

        $this->post(route('inventory.stock.adjustments.store', $this->product), [
            'system_qty' => 0.0,
            'counted_qty' => 100.0,
            'business_date' => $businessDate,
            'warehouse_id' => $this->warehouse->id,
            'notes' => 'Initial adjustment',
        ]);
        $this->assertEquals(1, StockAdjustment::where('product_id', $this->product->id)->count());

        // Submitting same count when system stock is now 100.0 creates no duplicate adjustment
        $this->post(route('inventory.stock.adjustments.store', $this->product), [
            'system_qty' => 100.0,
            'counted_qty' => 100.0,
            'business_date' => $businessDate,
            'warehouse_id' => $this->warehouse->id,
            'notes' => 'Duplicate attempt',
        ]);
        $this->assertEquals(1, StockAdjustment::where('product_id', $this->product->id)->count(), 'No duplicate adjustment record created');
        $this->assertEquals(100.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $businessDate));
    }

    public function test_date_boundary_isolation(): void
    {
        Carbon::setTestNow('2026-10-15 12:00:00');

        // On Oct 14: historical stock was 40 kg
        StockBatch::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'total_kg' => 40.0,
            'received_at' => '2026-10-14',
            'created_at' => '2026-10-14 10:00:00',
        ]);

        $this->assertEquals(40.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, '2026-10-14'));

        // On Oct 15: stock adjusted to 100 kg
        $response = $this->post(route('inventory.stock.adjustments.store', $this->product), [
            'system_qty' => 40.0,
            'counted_qty' => 100.0,
            'business_date' => '2026-10-15',
            'warehouse_id' => $this->warehouse->id,
            'notes' => 'Oct 15 baseline',
        ]);
        $response->assertRedirect();

        // Querying Oct 14 still shows 40 kg (Oct 15 adjustment does not leak backwards)
        $this->assertEquals(40.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, '2026-10-14'));
        // Querying Oct 15 shows 100 kg
        $this->assertEquals(100.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, '2026-10-15'));
        // Querying Oct 16 shows 100 kg (forward baseline preserved)
        $this->assertEquals(100.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, '2026-10-16'));
    }

    public function test_unsorted_and_legacy_unsorted_grade_adjustments(): void
    {
        $businessDate = '2026-10-15';
        Carbon::setTestNow('2026-10-15 09:00:00');

        // Initial unsorted stock: 100 kg
        $this->createAndConfirmAdvanceGrn(100.0, $businessDate);
        $this->assertEquals(100.0, $this->stockRepo->stockForProductAndGrade($this->product->id, ProductGrade::Unsorted, $this->warehouse->id, $businessDate));

        // 1. Submit adjustment using legacy 'Unsorted' string
        $response = $this->post(route('inventory.stock.adjustments.store', $this->product), [
            'system_qty' => 100.0,
            'counted_qty' => 75.0,
            'business_date' => $businessDate,
            'warehouse_id' => $this->warehouse->id,
            'grade' => 'Unsorted',
            'notes' => 'Recount of unsorted crates',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('stock_adjustments', [
            'product_id' => $this->product->id,
            'grade' => 'U',
            'counted_qty' => 75.0,
        ]);
        $this->assertEquals(75.0, $this->stockRepo->stockForProductAndGrade($this->product->id, ProductGrade::Unsorted, $this->warehouse->id, $businessDate));

        // 2. Submit adjustment using canonical 'U' representation
        $response2 = $this->post(route('inventory.stock.adjustments.store', $this->product), [
            'system_qty' => 75.0,
            'counted_qty' => 80.0,
            'business_date' => $businessDate,
            'warehouse_id' => $this->warehouse->id,
            'grade' => 'U',
            'notes' => 'Found 5 kg more unsorted',
        ]);
        $response2->assertRedirect();
        $this->assertEquals(80.0, $this->stockRepo->stockForProductAndGrade($this->product->id, ProductGrade::Unsorted, $this->warehouse->id, $businessDate));

        // 3. Reject invalid grade
        $responseInvalid = $this->from(route('inventory.stock.index'))->post(route('inventory.stock.adjustments.store', $this->product), [
            'system_qty' => 80.0,
            'counted_qty' => 85.0,
            'business_date' => $businessDate,
            'warehouse_id' => $this->warehouse->id,
            'grade' => 'INVALID_GRADE_XYZ',
        ]);
        $responseInvalid->assertSessionHasErrors('grade');
    }

    public function test_multi_warehouse_negative_stock_and_concurrency_validation(): void
    {
        $businessDate = '2026-10-15';
        Carbon::setTestNow('2026-10-15 08:00:00');

        $wh1Batch = StockBatch::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'total_kg' => 0.0,
            'received_at' => '2026-10-15',
        ]);

        $wh2Batch = StockBatch::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse2->id,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'total_kg' => 200.0,
            'received_at' => '2026-10-15',
        ]);

        // Setup scenario:
        // Warehouse 1 has -500 kg Grade A
        StockMovement::create([
            'product_id' => $this->product->id,
            'batch_id' => $wh1Batch->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'grade' => ProductGrade::GradeA->value,
            'type' => StockMovementType::Out->value,
            'quantity' => 500.0,
            'cost_per_unit' => 30.0,
            'created_at' => '2026-10-15 07:00:00',
            'notes' => 'Negative out movement in WH1',
        ]);

        // Warehouse 2 has +200 kg Grade A
        StockMovement::create([
            'product_id' => $this->product->id,
            'batch_id' => $wh2Batch->id,
            'warehouse_id' => $this->warehouse2->id,
            'created_by' => $this->user->id,
            'grade' => ProductGrade::GradeA->value,
            'type' => StockMovementType::In->value,
            'quantity' => 200.0,
            'cost_per_unit' => 30.0,
            'created_at' => '2026-10-15 07:30:00',
            'notes' => 'Positive in movement in WH2',
        ]);

        $this->assertEquals(-500.0, $this->stockRepo->stockForProductAndGrade($this->product->id, ProductGrade::GradeA, $this->warehouse->id, $businessDate));
        $this->assertEquals(200.0, $this->stockRepo->stockForProductAndGrade($this->product->id, ProductGrade::GradeA, $this->warehouse2->id, $businessDate));
        $this->assertEquals(-300.0, $this->stockRepo->stockForProductAndGrade($this->product->id, ProductGrade::GradeA, null, $businessDate));

        // Concurrency protection: If user submits -300.0 (cross-warehouse sum) while adjusting WH1 (-500.0), validation must fail!
        Carbon::setTestNow('2026-10-15 08:30:00');
        $failResponse = $this->from(route('inventory.stock.index'))->post(route('inventory.stock.adjustments.store', $this->product), [
            'system_qty' => -300.0, // Incorrect cross-warehouse qty submitted
            'counted_qty' => 50.0,
            'business_date' => $businessDate,
            'warehouse_id' => $this->warehouse->id,
            'grade' => 'A',
            'notes' => 'Count adjustment with stale qty',
        ]);
        $failResponse->assertSessionHasErrors('counted_qty');

        // Admin submits correct WH1 Grade A count of 50 kg with system_qty = -500.0
        Carbon::setTestNow('2026-10-15 09:00:00');
        $successResponse = $this->post(route('inventory.stock.adjustments.store', $this->product), [
            'system_qty' => -500.0,
            'counted_qty' => 50.0,
            'business_date' => $businessDate,
            'warehouse_id' => $this->warehouse->id,
            'grade' => 'A',
            'notes' => 'Establish WH1 Grade A baseline',
        ]);
        $successResponse->assertRedirect();

        // Verify: WH1 Grade A is 50 kg, WH2 stock remains completely untouched at 200 kg!
        $this->assertEquals(50.0, $this->stockRepo->stockForProductAndGrade($this->product->id, ProductGrade::GradeA, $this->warehouse->id, $businessDate));
        $this->assertEquals(200.0, $this->stockRepo->stockForProductAndGrade($this->product->id, ProductGrade::GradeA, $this->warehouse2->id, $businessDate));
        $this->assertEquals(250.0, $this->stockRepo->stockForProductAndGrade($this->product->id, ProductGrade::GradeA, null, $businessDate));

        // Next: Advance receipt +100 kg in WH1 -> 150 kg
        Carbon::setTestNow('2026-10-15 10:00:00');
        $advanceGrn = $this->createAndConfirmAdvanceGrn(100.0, $businessDate);
        $this->assertEquals(150.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $businessDate));
        $this->assertEquals(200.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse2->id, $businessDate));

        // Loadout -30 kg from WH1 -> 120 kg
        Carbon::setTestNow('2026-10-15 10:30:00');
        $this->performLoadout(30.0, $businessDate);
        $this->assertEquals(120.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $businessDate));
        $this->assertEquals(200.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse2->id, $businessDate));

        // Purchase Bill matching for the 100 kg advance -> physical stock remains 120 kg
        Carbon::setTestNow('2026-10-15 11:00:00');
        $this->createAndMatchPurchaseBill($advanceGrn, 100.0, $businessDate);
        $this->assertEquals(120.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $businessDate));
        $this->assertEquals(200.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse2->id, $businessDate));

        // Page refresh retains 120 kg
        $pageResponse = $this->get(route('inventory.stock.index', ['date' => $businessDate, 'warehouse_id' => $this->warehouse->id]));
        $pageResponse->assertOk();
        $this->assertEquals(120.0, $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id, $businessDate));
    }

    private function createAndConfirmAdvanceGrn(float $quantity, string $date): GoodsReceived
    {
        $grn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->user->id,
            'status' => 'approved',
            'receipt_type' => 'warehouse_advance',
            'bill_status' => 'bill_pending',
            'received_at' => Carbon::parse($date),
        ]);

        $item = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'received_qty' => $quantity,
            'received_unit' => 'kg',
            'grade' => 'A',
        ]);

        $batch = StockBatch::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'total_kg' => $quantity,
            'received_at' => Carbon::parse($date),
        ]);

        $item->update(['stock_batch_id' => $batch->id]);

        return $grn;
    }

    private function createAndConfirmDirectGrn(float $quantity, string $date): GoodsReceived
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id,
            'order_date' => Carbon::parse($date),
            'status' => POStatus::Approved,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'unit_price' => 30.0,
        ]);

        $grn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'received_by' => $this->user->id,
            'status' => 'approved',
            'receipt_type' => 'normal',
            'bill_status' => 'billed',
            'received_at' => Carbon::parse($date),
        ]);

        $grnItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'received_qty' => $quantity,
            'received_unit' => 'kg',
            'grade' => 'A',
        ]);

        $batch = StockBatch::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $grnItem->id,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'total_kg' => $quantity,
            'received_at' => Carbon::parse($date),
        ]);

        return $grn;
    }

    private function performLoadout(float $quantity, string $date): void
    {
        $shop = Shop::factory()->create(['shop_price_group_id' => $this->shop->shop_price_group_id]);

        $shopOrder = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => Carbon::parse($date),
            'state' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $shopOrder->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => $quantity,
            'approved_qty' => $quantity,
            'loaded_qty' => $quantity,
            'unit' => 'kg',
            'sorting_status' => 'loaded',
        ]);

        app(StockLedgerService::class)->consumeStockForProductAllowingNegative(
            $this->product->id,
            $quantity,
            $this->user->id,
            StockMovementType::Out,
            "Warehouse loadout completion — Order: {$shopOrder->order_number}; Warehouse: {$this->warehouse->code}",
            $item->id,
            $this->warehouse->id,
            ProductGrade::GradeA,
        );
    }

    private function createAndMatchPurchaseBill(GoodsReceived $advanceGrn, float $quantity, string $date): void
    {
        $billGrn = $this->createAndConfirmDirectGrn($quantity, $date);

        $advanceGrnItem = $advanceGrn->items()->first();
        $billGrnItem = $billGrn->items()->first();

        // Matching: bill matches existing advance receipt, creating no new physical stock batch
        StockBatch::where('goods_received_id', $billGrn->id)->delete();

        AdvanceReceiveMatch::create([
            'product_id' => $this->product->id,
            'advance_goods_received_id' => $advanceGrn->id,
            'advance_goods_received_item_id' => $advanceGrnItem->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billGrnItem->id,
            'matched_qty' => $quantity,
            'base_qty' => $quantity,
            'matched_unit' => 'kg',
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->user->id,
            'confirmed_at' => now(),
            'notes' => 'Test match',
        ]);
    }
}
