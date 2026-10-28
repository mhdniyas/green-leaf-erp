<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Actions\Inventory\TransferGradeStockAction;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WastageEntry;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Inventory\StockAdjustmentService;
use App\Services\Inventory\StockLedgerService;
use App\Services\Purchasing\DamageLossReportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DailyClearAndDamageLossWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    private Category $category;

    private StockMovementRepository $stockMovementRepository;

    private StockLedgerService $stockLedgerService;

    private TransferGradeStockAction $transferAction;

    private DamageLossReportService $damageLossReportService;

    private StockAdjustmentService $stockAdjustmentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');

        $this->warehouse = Warehouse::factory()->create(['name' => 'Main Vegetable Warehouse', 'code' => 'MVW']);
        $this->user->warehouses()->attach($this->warehouse->id);
        $this->actingAs($this->user);

        $this->category = Category::factory()->create(['name' => 'Vegetables']);

        $this->stockMovementRepository = app(StockMovementRepository::class);
        $this->stockLedgerService = app(StockLedgerService::class);
        $this->transferAction = app(TransferGradeStockAction::class);
        $this->damageLossReportService = app(DamageLossReportService::class);
        $this->stockAdjustmentService = app(StockAdjustmentService::class);
    }

    /**
     * Scenario 1: Grade-wise Daily Clear: correct stock and DMG quantities.
     * - Moving stock to DMG must not change total physical inventory.
     * - Daily Clear on Grade B never deducts Grade A.
     * - Daily Clear on Grade DMG clears DMG stock.
     */
    public function test_scenario_1_gradewise_daily_clear_correct_stock_and_dmg_quantities(): void
    {
        $tomato = Product::factory()->create([
            'name' => 'Tomato Hybrid',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'carryover_enabled' => true,
        ]);

        $date = '2026-10-28';

        // 1. Establish stock in Grade A (30 kg), Grade B (20 kg), and Grade U (40 kg)
        $batchA = $this->createBatchWithStock($tomato, 30.0, ProductGrade::GradeA, 25.0, $date);
        $batchB = $this->createBatchWithStock($tomato, 20.0, ProductGrade::GradeB, 20.0, $date);
        $batchU = $this->createPendingBatch($tomato, 40.0, 18.0, $date);

        $this->assertSame(30.0, $this->stockMovementRepository->stockForProductAndGrade($tomato->id, ProductGrade::GradeA, $this->warehouse->id, $date));
        $this->assertSame(20.0, $this->stockMovementRepository->stockForProductAndGrade($tomato->id, ProductGrade::GradeB, $this->warehouse->id, $date));
        $this->assertSame(40.0, $this->stockMovementRepository->stockForProductAndGrade($tomato->id, ProductGrade::Unsorted, $this->warehouse->id, $date));
        $this->assertSame(90.0, $this->stockMovementRepository->currentStockForProduct($tomato->id, $this->warehouse->id, $date));

        // 2. Transfer 5 kg from Grade A into Grade DMG (quarantine damage stock)
        $transferResult = $this->transferAction->execute(
            product: $tomato,
            warehouse: $this->warehouse,
            sourceGrade: ProductGrade::GradeA,
            targetGrade: ProductGrade::Damage,
            quantity: 5.0,
            userId: $this->user->id,
            sourceBatch: $batchA,
            notes: 'Quarantining 5 kg bruised tomatoes to Grade DMG'
        );

        $this->assertInstanceOf(StockBatch::class, $transferResult['destination_batch']);
        $this->assertSame('DMG', $transferResult['destination_batch']->purchase_grade);

        // Grade A decreased from 30 to 25 kg
        $this->assertSame(25.0, $this->stockMovementRepository->stockForProductAndGrade($tomato->id, ProductGrade::GradeA, $this->warehouse->id, $date));
        // Grade DMG now has 5 kg
        $this->assertSame(5.0, $this->stockMovementRepository->stockForProductAndGrade($tomato->id, ProductGrade::Damage, $this->warehouse->id, $date));
        // Grade B and U remain completely untouched
        $this->assertSame(20.0, $this->stockMovementRepository->stockForProductAndGrade($tomato->id, ProductGrade::GradeB, $this->warehouse->id, $date));
        $this->assertSame(40.0, $this->stockMovementRepository->stockForProductAndGrade($tomato->id, ProductGrade::Unsorted, $this->warehouse->id, $date));

        // Total physical inventory has NOT changed (25 + 20 + 40 + 5 = 90 kg)!
        $this->assertSame(90.0, $this->stockMovementRepository->currentStockForProduct($tomato->id, $this->warehouse->id, $date));

        // 3. Execute Daily Clear on Grade B: 20 kg closing -> 5 kg wastage, 15 kg carryover
        $response = $this->post(route('inventory.daily-close.store'), [
            'date' => $date,
            'lines' => [
                [
                    'product_id' => $tomato->id,
                    'warehouse_id' => $this->warehouse->id,
                    'grade' => 'B',
                    'closing_qty' => 20.0,
                    'wastage_qty' => 5.0,
                    'carryover_qty' => 15.0,
                    'carryover_enabled' => 1,
                ],
            ],
        ]);

        $response->assertRedirect();

        // Grade A remains untouched at 25 kg! Grade B is deducted down to 15 kg!
        $this->assertSame(25.0, $this->stockMovementRepository->stockForProductAndGrade($tomato->id, ProductGrade::GradeA, $this->warehouse->id, $date));
        $this->assertSame(15.0, $this->stockMovementRepository->stockForProductAndGrade($tomato->id, ProductGrade::GradeB, $this->warehouse->id, $date));
        $this->assertSame(5.0, $this->stockMovementRepository->stockForProductAndGrade($tomato->id, ProductGrade::Damage, $this->warehouse->id, $date));

        // 4. Execute Daily Clear on Grade DMG: 5 kg closing -> 5 kg wastage, 0 kg carryover
        $responseDmg = $this->post(route('inventory.daily-close.store'), [
            'date' => $date,
            'lines' => [
                [
                    'product_id' => $tomato->id,
                    'warehouse_id' => $this->warehouse->id,
                    'grade' => 'DMG',
                    'closing_qty' => 5.0,
                    'wastage_qty' => 5.0,
                    'carryover_qty' => 0.0,
                    'carryover_enabled' => 1,
                ],
            ],
        ]);

        $responseDmg->assertRedirect();

        // Grade DMG is now 0 kg!
        $this->assertSame(0.0, $this->stockMovementRepository->stockForProductAndGrade($tomato->id, ProductGrade::Damage, $this->warehouse->id, $date));
        // Total physical inventory is now 25 + 15 + 40 + 0 = 80 kg!
        $this->assertSame(80.0, $this->stockMovementRepository->currentStockForProduct($tomato->id, $this->warehouse->id, $date));
    }

    /**
     * Scenario 2: Damage disposal and financial loss report: quantity deducted and reported once.
     * - WastageEntry created with accurate unit cost and original grade.
     * - DamageLossReportService aggregates wastage and reports correct valuation.
     * - No double deductions between WastageEntry and StockMovement.
     */
    public function test_scenario_2_damage_disposal_and_financial_loss_report_once(): void
    {
        $onion = Product::factory()->create([
            'name' => 'Onion Red Large',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'carryover_enabled' => true,
        ]);

        $date = '2026-10-28';
        $costPerKg = 24.50;

        // 50 kg of Grade B at Rs 24.50/kg
        $this->createBatchWithStock($onion, 50.0, ProductGrade::GradeB, $costPerKg, $date);

        // Perform Daily Clear: 50 kg closing -> 10 kg wastage, 40 kg carryover
        $response = $this->post(route('inventory.daily-close.store'), [
            'date' => $date,
            'lines' => [
                [
                    'product_id' => $onion->id,
                    'warehouse_id' => $this->warehouse->id,
                    'grade' => 'B',
                    'closing_qty' => 50.0,
                    'wastage_qty' => 10.0,
                    'carryover_qty' => 40.0,
                    'carryover_enabled' => 1,
                ],
            ],
        ]);

        $response->assertRedirect();

        // 1. Verify WastageEntry was created for the Damage & Loss Report
        $wastageEntry = WastageEntry::query()->where('product_id', $onion->id)->latest('id')->first();
        $this->assertNotNull($wastageEntry);
        $this->assertEquals(10.0, (float) $wastageEntry->quantity);
        $this->assertEquals($costPerKg, (float) $wastageEntry->cost_per_kg);
        $this->assertEquals($date, $wastageEntry->wastage_date->toDateString());
        $this->assertEquals(ProductGrade::GradeB, $wastageEntry->grade);

        // 2. Verify DamageLossReportService accurately calculates and reports this loss
        $reportData = $this->damageLossReportService->getReportData([
            'product_id' => $onion->id,
            'warehouse_id' => $this->warehouse->id,
            'start_date' => $date,
            'end_date' => $date,
        ]);

        $this->assertSame(10.0, $reportData['summary']->total_damaged_kg);
        $this->assertSame(245.0, $reportData['summary']->total_damaged_cost); // 10 kg * Rs 24.50
        $this->assertSame(245.0, $reportData['summary']->total_loss);

        // 3. Verify physical inventory is deducted exactly once (50 - 10 = 40 kg, not double-deducted to 30)
        $remainingStock = $this->stockMovementRepository->stockForProductAndGrade($onion->id, ProductGrade::GradeB, $this->warehouse->id, $date);
        $this->assertSame(40.0, $remainingStock);

        $totalStock = $this->stockMovementRepository->currentStockForProduct($onion->id, $this->warehouse->id, $date);
        $this->assertSame(40.0, $totalStock);
    }

    /**
     * Scenario 3: Repeated Daily Clear, next-day stock and future reset-baseline isolation.
     * - Resubmitting Daily Clear does not deduct stock again.
     * - Next-day carryover is preserved accurately.
     * - Future warehouse reset safely zeroes inventory without deleting historical records
     *   and cleanly isolates negative historical movements.
     */
    public function test_scenario_3_repeated_daily_clear_next_day_stock_and_future_reset_baseline_isolation(): void
    {
        $potato = Product::factory()->create([
            'name' => 'Potato Local',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'carryover_enabled' => true,
        ]);

        $date = '2026-10-28';
        $nextDay = '2026-10-29';

        // 100 kg Grade A received
        $batch = $this->createBatchWithStock($potato, 100.0, ProductGrade::GradeA, 15.0, $date);

        // First Daily Close submission: 100 kg -> 20 kg wastage, 80 kg carryover
        $this->post(route('inventory.daily-close.store'), [
            'date' => $date,
            'lines' => [
                [
                    'product_id' => $potato->id,
                    'warehouse_id' => $this->warehouse->id,
                    'grade' => 'A',
                    'closing_qty' => 100.0,
                    'wastage_qty' => 20.0,
                    'carryover_qty' => 80.0,
                    'carryover_enabled' => 1,
                ],
            ],
        ]);

        $this->assertSame(80.0, $this->stockMovementRepository->stockForProductAndGrade($potato->id, ProductGrade::GradeA, $this->warehouse->id, $date));

        // Repeat submission for the exact same date and values
        $this->post(route('inventory.daily-close.store'), [
            'date' => $date,
            'lines' => [
                [
                    'product_id' => $potato->id,
                    'warehouse_id' => $this->warehouse->id,
                    'grade' => 'A',
                    'closing_qty' => 100.0,
                    'wastage_qty' => 20.0,
                    'carryover_qty' => 80.0,
                    'carryover_enabled' => 1,
                ],
            ],
        ]);

        // Stock must still be 80 kg (no duplicate deduction!)
        $this->assertSame(80.0, $this->stockMovementRepository->stockForProductAndGrade($potato->id, ProductGrade::GradeA, $this->warehouse->id, $date));
        // Next day carryover is accurately 80 kg!
        $this->assertSame(80.0, $this->stockMovementRepository->stockForProductAndGrade($potato->id, ProductGrade::GradeA, $this->warehouse->id, $nextDay));

        // Wastage movements count is exactly 1 (not duplicated)
        $wastageMovements = StockMovement::query()
            ->where('product_id', $potato->id)
            ->where('type', StockMovementType::Wastage->value)
            ->count();
        $this->assertSame(1, $wastageMovements);

        // 2. Future Warehouse Reset verification:
        // Create an un-repaired legacy product with negative historical balance (-12.0 kg)
        $legacyProduct = Product::factory()->create([
            'name' => 'Legacy Discrepancy Carrot',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $legacyBatch = StockBatch::factory()->create([
            'product_id' => $legacyProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 0.0,
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'received_at' => '2026-10-20',
        ]);

        StockMovement::create([
            'product_id' => $legacyProduct->id,
            'batch_id' => $legacyBatch->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 12.0,
            'cost_per_unit' => 10.0,
            'created_at' => '2026-10-20 10:00:00',
            'notes' => 'Legacy negative discrepancy movement',
        ]);

        $this->assertSame(-12.0, $this->stockMovementRepository->currentStockForProduct($legacyProduct->id, $this->warehouse->id, $date));

        // Execute future warehouse empty/reset mechanism for date 2026-10-30
        $resetDate = '2026-10-30';
        Carbon::setTestNow('2026-10-30 18:00:00');
        $adjustmentsCreated = $this->stockAdjustmentService->emptyWarehouse($this->warehouse, $resetDate, $this->user->id);
        $this->assertGreaterThanOrEqual(2, $adjustmentsCreated);

        // Verify: Historical movements in database were NOT deleted (full auditability preserved)
        $this->assertTrue(StockMovement::where('product_id', $legacyProduct->id)->exists());
        $this->assertTrue(StockMovement::where('product_id', $potato->id)->exists());

        // Verify: New stock query on reset date returns exactly 0.0 for all products in this warehouse
        $this->assertSame(0.0, $this->stockMovementRepository->currentStockForProduct($legacyProduct->id, $this->warehouse->id, $resetDate));
        $this->assertSame(0.0, $this->stockMovementRepository->currentStockForProduct($potato->id, $this->warehouse->id, $resetDate));

        // Verify: Receiving new stock after reset cleanly adds to the 0 baseline
        Carbon::setTestNow('2026-10-31 09:00:00');
        $newBatch = $this->createBatchWithStock($potato, 35.0, ProductGrade::GradeA, 16.0, '2026-10-31');
        $this->assertSame(35.0, $this->stockMovementRepository->currentStockForProduct($potato->id, $this->warehouse->id, '2026-10-31'));

        Carbon::setTestNow(); // Clear test clock
    }

    private function createBatchWithStock(Product $product, float $quantity, ProductGrade $grade, float $costPerKg, string $date): StockBatch
    {
        $batch = StockBatch::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => $quantity,
            'cost_per_kg' => $costPerKg,
            'purchase_grade' => $grade->value,
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'received_at' => $date,
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'grade' => $grade->value,
            'type' => StockMovementType::In->value,
            'quantity' => $quantity,
            'cost_per_unit' => $costPerKg,
            'notes' => 'Initial test stock',
            'created_at' => $date.' 08:00:00',
        ]);

        return $batch;
    }

    private function createPendingBatch(Product $product, float $quantity, float $costPerKg, string $date): StockBatch
    {
        return StockBatch::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => $quantity,
            'cost_per_kg' => $costPerKg,
            'purchase_grade' => 'U',
            'status' => BatchStatus::Pending->value,
            'warehouse_receive_pending' => false,
            'received_at' => $date,
        ]);
    }
}
