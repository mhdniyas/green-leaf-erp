<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\StockAdjustment;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Inventory\StockAdjustmentService;
use App\Services\Inventory\StockLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WarehouseBaselineSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse1;

    private Warehouse $warehouse2;

    private Category $category;

    private StockMovementRepository $stockMovementRepo;

    private StockAdjustmentService $adjustmentService;

    private StockLedgerService $stockLedgerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Role::findOrCreate('admin', 'web');

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');

        $this->warehouse1 = Warehouse::factory()->create(['name' => 'Main Central Warehouse', 'is_active' => true]);
        $this->warehouse2 = Warehouse::factory()->create(['name' => 'Secondary Hub Warehouse', 'is_active' => true]);
        $this->category = Category::factory()->create(['name' => 'Fresh Farm Vegetables']);

        $this->stockMovementRepo = app(StockMovementRepository::class);
        $this->adjustmentService = app(StockAdjustmentService::class);
        $this->stockLedgerService = app(StockLedgerService::class);
    }

    /**
     * Requirement 1: Dry-run preview method accurately detects positive lots, pending batches,
     * unconfirmed receive-pending batches, and negative balances without modifying the database.
     */
    public function test_dry_run_preview_is_accurate_and_performs_no_database_mutations(): void
    {
        $date = '2026-10-28';
        $p1 = $this->createProduct('Carrot');
        $p2 = $this->createProduct('Tomato');
        $p3 = $this->createProduct('Apple');
        $p4 = $this->createProduct('Potato');

        // 1. Positive sorted stock in W1: 50 kg Grade A Carrot
        $this->createSortedBatchWithMovement($p1, $this->warehouse1, 50.0, ProductGrade::GradeA, 30.0, $date);

        // 2. Confirmed pending batch in W1: 30 kg Unsorted Tomato
        StockBatch::factory()->create([
            'product_id' => $p2->id,
            'warehouse_id' => $this->warehouse1->id,
            'total_kg' => 30.0,
            'cost_per_kg' => 20.0,
            'purchase_grade' => 'U',
            'status' => BatchStatus::Pending->value,
            'warehouse_receive_pending' => false,
            'received_at' => $date,
        ]);

        // 3. Unconfirmed receive-pending batch in W1 (like the 77 fruit batches): 100 kg Apple
        StockBatch::factory()->create([
            'product_id' => $p3->id,
            'warehouse_id' => $this->warehouse1->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 60.0,
            'purchase_grade' => 'U',
            'status' => BatchStatus::Pending->value,
            'warehouse_receive_pending' => true,
            'received_at' => $date,
        ]);

        // 4. Negative legacy balance in W1: -10 kg Potato
        $negBatch = StockBatch::factory()->create([
            'product_id' => $p4->id,
            'warehouse_id' => $this->warehouse1->id,
            'total_kg' => 0.0,
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'received_at' => $date,
        ]);
        StockMovement::create([
            'product_id' => $p4->id,
            'batch_id' => $negBatch->id,
            'warehouse_id' => $this->warehouse1->id,
            'created_by' => $this->user->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 10.0,
            'cost_per_unit' => 15.0,
            'notes' => 'Legacy negative drift movement',
        ]);

        // Capture DB snapshot counts before preview
        $batchesBefore = StockBatch::count();
        $movementsBefore = StockMovement::count();
        $adjustmentsBefore = StockAdjustment::count();

        // Run preview
        $preview = $this->adjustmentService->previewEmptyWarehouse($this->warehouse1, $date);

        // Verify preview results
        $this->assertSame($this->warehouse1->id, $preview['warehouse_id']);
        $this->assertSame('Main Central Warehouse', $preview['warehouse_name']);
        $this->assertSame(2, $preview['positive_lots_count']); // Carrot (50) + Tomato pending (30)
        $this->assertEquals(80.0, $preview['positive_stock_kg']);
        $this->assertSame(1, $preview['pending_batches_count']);
        $this->assertEquals(30.0, $preview['pending_batches_kg']);
        $this->assertSame(1, $preview['unconfirmed_receive_pending_batches_count']);
        $this->assertEquals(100.0, $preview['unconfirmed_receive_pending_batches_kg']);
        $this->assertSame(1, $preview['negative_balance_lines_count']);
        $this->assertEquals(10.0, $preview['negative_balance_kg']);

        // Assert strictly zero DB changes took place during preview
        $this->assertSame($batchesBefore, StockBatch::count());
        $this->assertSame($movementsBefore, StockMovement::count());
        $this->assertSame($adjustmentsBefore, StockAdjustment::count());
    }

    /**
     * Requirement 2: Warehouse reset closes unconfirmed receive-pending batches and pre-reset pending batches,
     * neutralizing legacy negative balances and establishing an exact zero baseline.
     */
    public function test_empty_warehouse_closes_unconfirmed_and_pending_batches_and_zeroes_all_stock(): void
    {
        $date = '2026-10-28';
        $p1 = $this->createProduct('Carrot');
        $p2 = $this->createProduct('Tomato');
        $p3 = $this->createProduct('Apple');
        $p4 = $this->createProduct('Potato');

        // Positive stock
        $b1 = $this->createSortedBatchWithMovement($p1, $this->warehouse1, 50.0, ProductGrade::GradeA, 30.0, $date);

        // Confirmed pending batch
        $b2 = StockBatch::factory()->create([
            'product_id' => $p2->id,
            'warehouse_id' => $this->warehouse1->id,
            'total_kg' => 30.0,
            'cost_per_kg' => 20.0,
            'purchase_grade' => 'U',
            'status' => BatchStatus::Pending->value,
            'warehouse_receive_pending' => false,
            'received_at' => $date,
        ]);

        // Unconfirmed receive-pending batch (e.g. 77 fruit batches)
        $b3 = StockBatch::factory()->create([
            'product_id' => $p3->id,
            'warehouse_id' => $this->warehouse1->id,
            'total_kg' => 100.0,
            'cost_per_kg' => 60.0,
            'purchase_grade' => 'U',
            'status' => BatchStatus::Pending->value,
            'warehouse_receive_pending' => true,
            'received_at' => $date,
        ]);

        // Negative legacy balance
        $negBatch = StockBatch::factory()->create([
            'product_id' => $p4->id,
            'warehouse_id' => $this->warehouse1->id,
            'total_kg' => 0.0,
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'received_at' => $date,
        ]);
        StockMovement::create([
            'product_id' => $p4->id,
            'batch_id' => $negBatch->id,
            'warehouse_id' => $this->warehouse1->id,
            'created_by' => $this->user->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 10.0,
            'cost_per_unit' => 15.0,
            'notes' => 'Legacy negative drift movement',
        ]);

        // Execute emptyWarehouse
        $adjCount = $this->adjustmentService->emptyWarehouse($this->warehouse1, $date, $this->user->id);
        $this->assertGreaterThan(0, $adjCount);

        // 1. Verify confirmed pending batch b2 is closed
        $this->assertSame(BatchStatus::Closed, $b2->fresh()->status);
        $this->assertFalse($b2->fresh()->status->canBeSorted());

        // 2. Verify unconfirmed receive-pending batch b3 is closed
        $this->assertSame(BatchStatus::Closed, $b3->fresh()->status);
        $this->assertFalse($b3->fresh()->status->canBeSorted());

        // 3. Verify all available stock is exactly 0.0 for every product in warehouse 1
        $this->assertEquals(0.0, $this->stockMovementRepo->currentStockForProduct($p1->id, $this->warehouse1->id, $date));
        $this->assertEquals(0.0, $this->stockMovementRepo->currentStockForProduct($p2->id, $this->warehouse1->id, $date));
        $this->assertEquals(0.0, $this->stockMovementRepo->currentStockForProduct($p3->id, $this->warehouse1->id, $date));
        $this->assertEquals(0.0, $this->stockMovementRepo->currentStockForProduct($p4->id, $this->warehouse1->id, $date));

        // 4. Verify available sorted stock via StockLedgerService is also 0.0
        $this->assertEquals(0.0, $this->stockLedgerService->availableSortedStockForProduct($p1->id, $this->warehouse1->id, ProductGrade::GradeA));
        $this->assertEquals(0.0, $this->stockLedgerService->availableSortedStockForProduct($p2->id, $this->warehouse1->id, ProductGrade::Unsorted));
    }

    /**
     * Requirement 3: Warehouse isolation — emptying Warehouse 1 leaves Warehouse 2 untouched.
     */
    public function test_empty_warehouse_preserves_other_warehouses(): void
    {
        $date = '2026-10-28';
        $p1 = $this->createProduct('Carrot');

        // W1 stock: 40 kg
        $this->createSortedBatchWithMovement($p1, $this->warehouse1, 40.0, ProductGrade::GradeA, 25.0, $date);

        // W2 stock: 80 kg
        $this->createSortedBatchWithMovement($p1, $this->warehouse2, 80.0, ProductGrade::GradeA, 25.0, $date);

        // Empty W1 only
        $this->adjustmentService->emptyWarehouse($this->warehouse1, $date, $this->user->id);

        // W1 is zeroed
        $this->assertEquals(0.0, $this->stockMovementRepo->currentStockForProduct($p1->id, $this->warehouse1->id, $date));

        // W2 remains fully intact at 80 kg
        $this->assertEquals(80.0, $this->stockMovementRepo->currentStockForProduct($p1->id, $this->warehouse2->id, $date));
        $this->assertEquals(80.0, $this->stockLedgerService->availableSortedStockForProduct($p1->id, $this->warehouse2->id, ProductGrade::GradeA));
    }

    /**
     * Requirement 4: Idempotency — executing emptyWarehouse repeatedly produces 0 adjustments.
     */
    public function test_empty_warehouse_is_strictly_idempotent(): void
    {
        $date = '2026-10-28';
        $p1 = $this->createProduct('Carrot');
        $this->createSortedBatchWithMovement($p1, $this->warehouse1, 50.0, ProductGrade::GradeA, 30.0, $date);

        // First run creates adjustments
        $adj1 = $this->adjustmentService->emptyWarehouse($this->warehouse1, $date, $this->user->id);
        $this->assertGreaterThan(0, $adj1);

        // Second run on already zeroed warehouse produces 0 adjustments
        $adj2 = $this->adjustmentService->emptyWarehouse($this->warehouse1, $date, $this->user->id);
        $this->assertSame(0, $adj2);

        // Third run preview also reports 0 positive lots and 0 pending kg
        $preview = $this->adjustmentService->previewEmptyWarehouse($this->warehouse1, $date);
        $this->assertSame(0, $preview['positive_lots_count']);
        $this->assertEquals(0.0, $preview['positive_stock_kg']);
        $this->assertSame(0, $preview['pending_batches_count']);
        $this->assertSame(0, $preview['negative_balance_lines_count']);
    }

    /**
     * Requirement 5: Post-reset stock operations — new stock received after reset can be allocated,
     * loaded out, and dispatched cleanly without interference from pre-reset closed batches.
     */
    public function test_post_reset_stock_intake_and_loadout_work_cleanly(): void
    {
        $oldDate = '2026-10-28';
        $newDate = '2026-10-29';
        $p1 = $this->createProduct('Fresh Onion');

        Carbon::setTestNow($oldDate.' 18:00:00');

        // Old pre-reset stock
        $this->createSortedBatchWithMovement($p1, $this->warehouse1, 60.0, ProductGrade::GradeB, 20.0, $oldDate);

        // Empty warehouse on oldDate
        $this->adjustmentService->emptyWarehouse($this->warehouse1, $oldDate, $this->user->id);
        $this->assertEquals(0.0, $this->stockMovementRepo->currentStockForProduct($p1->id, $this->warehouse1->id, $oldDate));

        // Advance to new day for fresh stock intake
        Carbon::setTestNow($newDate.' 08:00:00');

        // Receive fresh stock on newDate: 100 kg Grade B at Rs 25/kg
        $newBatch = $this->createSortedBatchWithMovement($p1, $this->warehouse1, 100.0, ProductGrade::GradeB, 25.0, $newDate);
        $this->assertEquals(100.0, $this->stockMovementRepo->currentStockForProduct($p1->id, $this->warehouse1->id, $newDate));

        // Setup price approval for shop order
        DailyPriceApproval::create([
            'product_id' => $p1->id,
            'business_date' => $newDate,
            'grade' => 'B',
            'purchase_price' => 25.0,
            'price_unit' => 'kg',
            'price_a' => 38.0,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->user->id,
        ]);

        $priceGroup = ShopPriceGroup::factory()->create(['name' => 'A', 'is_active' => true]);
        $shop = Shop::factory()->create(['shop_price_group_id' => $priceGroup->id, 'status' => 'active']);

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $newDate,
            'delivery_status' => 'ready_for_dispatch',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $p1->id,
            'product_grade' => 'B',
            'requested_qty' => 25.0,
            'approved_qty' => 25.0,
            'loaded_qty' => 25.0,
            'unit' => 'kg',
            'sorting_status' => 'loaded',
        ]);

        // Dispatch via API
        $res = $this->actingAs($this->user)->postJson("/api/v1/warehouse/loadout/{$order->id}/move-to-delivery", [
            'partial' => false,
        ], ['X-Client-Supports-Multi-Grade' => 'true']);

        $res->assertOk();
        $this->assertSame('in_transit', $order->fresh()->delivery_status);

        // Verify invoice was created with correct pricing
        $this->assertNotNull($order->fresh()->invoice);
        $this->assertEquals(950.0, (float) $order->fresh()->invoice->final_total); // 25 kg * Rs 38.00

        Carbon::setTestNow();
    }

    private function createProduct(string $name): Product
    {
        return Product::factory()->create([
            'name' => $name,
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouse1->id,
            'unit' => 'kg',
            'carryover_enabled' => true,
        ]);
    }

    private function createSortedBatchWithMovement(Product $product, Warehouse $warehouse, float $qty, ProductGrade $grade, float $cost, string $date): StockBatch
    {
        $batch = StockBatch::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'total_kg' => $qty,
            'cost_per_kg' => $cost,
            'purchase_grade' => $grade->value,
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'received_at' => $date,
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $this->user->id,
            'grade' => $grade->value,
            'type' => StockMovementType::In->value,
            'quantity' => $qty,
            'cost_per_unit' => $cost,
            'notes' => 'Stock creation for '.$product->name,
        ]);

        return $batch;
    }
}
