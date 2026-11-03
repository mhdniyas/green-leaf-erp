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
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Purchasing\DamageLossReportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeStockDamageAndSegregationReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private Warehouse $warehouse;

    private Warehouse $otherWarehouse;

    private Category $category;

    private Product $product;

    private StockMovementRepository $repository;

    private TransferGradeStockAction $transferAction;

    private DamageLossReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->staff = User::factory()->create();
        $this->staff->assignRole('warehouse_receiver');

        $this->warehouse = Warehouse::create([
            'name' => 'Main Vegetable Warehouse',
            'code' => 'MVW-01',
            'is_active' => true,
        ]);

        $this->otherWarehouse = Warehouse::create([
            'name' => 'Secondary Fruits Warehouse',
            'code' => 'SFW-02',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Vegetables',
            'code' => 'VEG',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Tomato Hybrid',
            'sku' => 'TOM-HYB-001',
            'category_id' => $this->category->id,
            'unit' => 'kg',
            'status' => 'active',
        ]);

        $this->repository = app(StockMovementRepository::class);
        $this->transferAction = app(TransferGradeStockAction::class);
        $this->reportService = app(DamageLossReportService::class);
    }

    private function createGradedBatch(string $grade, float $qty, ?Warehouse $wh = null, ?User $creator = null): StockBatch
    {
        $wh = $wh ?? $this->warehouse;
        $creator = $creator ?? $this->admin;

        $batch = StockBatch::create([
            'batch_number' => 'BATCH-'.strtoupper($grade).'-'.uniqid(),
            'reference' => 'BAT-'.strtoupper($grade).'-'.uniqid(),
            'product_id' => $this->product->id,
            'warehouse_id' => $wh->id,
            'received_at' => now(),
            'purchase_grade' => $grade,
            'total_kg' => $qty,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Sorted,
            'warehouse_receive_pending' => false,
            'created_by' => $creator->id,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $wh->id,
            'created_by' => $creator->id,
            'grade' => $grade,
            'type' => StockMovementType::In->value,
            'quantity' => $qty,
            'cost_per_unit' => 20.0,
            'notes' => "Initial test stock for Grade {$grade}",
        ]);

        return $batch;
    }

    private function createPendingMixedBatch(float $qty, ?Warehouse $wh = null, ?User $creator = null): StockBatch
    {
        $wh = $wh ?? $this->warehouse;
        $creator = $creator ?? $this->admin;

        return StockBatch::create([
            'batch_number' => 'BATCH-U-'.uniqid(),
            'reference' => 'BAT-U-'.uniqid(),
            'product_id' => $this->product->id,
            'warehouse_id' => $wh->id,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'total_kg' => $qty,
            'cost_per_kg' => 15.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'created_by' => $creator->id,
        ]);
    }

    /**
     * Scenario 1: Grade A -> DMG movement.
     * Subtracts from Grade A, adds to DMG quarantine exactly once, conserves total physical quantity.
     */
    public function test_grade_a_to_damage_movement_conserves_total_physical_stock(): void
    {
        $this->actingAs($this->admin);
        $this->createGradedBatch('A', 50.0);

        // Before: A = 50, DMG = 0, Total = 50
        $response = $this->post(route('inventory.grade-stock.move-to-damage'), [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'source_grade' => 'A',
            'quantity' => 5.0,
            'notes' => 'Bruised tomatoes from transport',
        ]);

        $response->assertRedirect(route('inventory.grade-stock.index', [
            'warehouse_id' => $this->warehouse->id,
            'tab' => 'inventory',
        ]));
        $response->assertSessionHas('success');

        // Verify grade balances
        $stockA = $this->repository->stockForProductAndGrade($this->product->id, ProductGrade::GradeA, $this->warehouse->id);
        $stockDmg = $this->repository->stockForProductAndGrade($this->product->id, ProductGrade::Damage, $this->warehouse->id);
        $totalPhysical = $this->repository->currentStockForProduct($this->product->id, $this->warehouse->id);

        $this->assertSame(45.0, $stockA);
        $this->assertSame(5.0, $stockDmg);
        $this->assertSame(50.0, $totalPhysical); // Physical stock invariant preserved!
    }

    /**
     * Scenario 2 & 3: Grade B and Grade C to DMG.
     */
    public function test_grade_b_and_grade_c_to_damage_movement(): void
    {
        $this->actingAs($this->admin);
        $this->createGradedBatch('B', 30.0);
        $this->createGradedBatch('C', 20.0);

        // Move 10 kg from B to DMG
        $this->post(route('inventory.grade-stock.move-to-damage'), [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'source_grade' => 'B',
            'quantity' => 10.0,
            'notes' => 'Overripe stock',
        ])->assertSessionHas('success');

        // Move 8 kg from C to DMG
        $this->post(route('inventory.grade-stock.move-to-damage'), [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'source_grade' => 'C',
            'quantity' => 8.0,
            'notes' => 'Decay during sorting',
        ])->assertSessionHas('success');

        $stockB = $this->repository->stockForProductAndGrade($this->product->id, ProductGrade::GradeB, $this->warehouse->id);
        $stockC = $this->repository->stockForProductAndGrade($this->product->id, ProductGrade::GradeC, $this->warehouse->id);
        $stockDmg = $this->repository->stockForProductAndGrade($this->product->id, ProductGrade::Damage, $this->warehouse->id);

        $this->assertSame(20.0, $stockB);
        $this->assertSame(12.0, $stockC);
        $this->assertSame(18.0, $stockDmg);
    }

    /**
     * Scenario 4: Mixed U -> DMG movement.
     */
    public function test_mixed_u_to_damage_movement(): void
    {
        $this->actingAs($this->admin);
        $this->createPendingMixedBatch(40.0);

        // Move 6.5 kg from Mixed U to DMG
        $this->post(route('inventory.grade-stock.move-to-damage'), [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'source_grade' => 'U',
            'quantity' => 6.5,
            'notes' => 'Crushed mixed produce',
        ])->assertSessionHas('success');

        $stockU = $this->repository->stockForProductAndGrade($this->product->id, ProductGrade::Unsorted, $this->warehouse->id);
        $stockDmg = $this->repository->stockForProductAndGrade($this->product->id, ProductGrade::Damage, $this->warehouse->id);

        $this->assertSame(33.5, $stockU);
        $this->assertSame(6.5, $stockDmg);
        $this->assertSame(40.0, $this->repository->currentStockForProduct($this->product->id, $this->warehouse->id));
    }

    /**
     * Scenario 5 & 6: Partial and full available quantity damage.
     */
    public function test_full_available_quantity_damage_movement(): void
    {
        $this->actingAs($this->admin);
        $this->createGradedBatch('A', 15.0);

        // Move all 15.0 kg to DMG
        $this->post(route('inventory.grade-stock.move-to-damage'), [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'source_grade' => 'A',
            'quantity' => 15.0,
            'notes' => 'Total lot damage',
        ])->assertSessionHas('success');

        $stockA = $this->repository->stockForProductAndGrade($this->product->id, ProductGrade::GradeA, $this->warehouse->id);
        $stockDmg = $this->repository->stockForProductAndGrade($this->product->id, ProductGrade::Damage, $this->warehouse->id);

        $this->assertSame(0.0, $stockA);
        $this->assertSame(15.0, $stockDmg);
    }

    /**
     * Scenario 7: Damage quantity exceeding available stock fails validation.
     */
    public function test_damage_quantity_exceeding_available_stock_fails(): void
    {
        $this->actingAs($this->admin);
        $this->createGradedBatch('A', 10.0);

        // Try to move 15 kg when only 10 kg is available
        $response = $this->post(route('inventory.grade-stock.move-to-damage'), [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'source_grade' => 'A',
            'quantity' => 15.0,
            'notes' => 'Excessive damage request',
        ]);

        $response->assertSessionHasErrors('quantity');

        // Balance unchanged
        $this->assertSame(10.0, $this->repository->stockForProductAndGrade($this->product->id, ProductGrade::GradeA, $this->warehouse->id));
        $this->assertSame(0.0, $this->repository->stockForProductAndGrade($this->product->id, ProductGrade::Damage, $this->warehouse->id));
    }

    /**
     * Scenario 8: Repeated submission and idempotency safeguard.
     */
    public function test_repeated_submission_with_same_client_submission_id_is_idempotent(): void
    {
        $this->actingAs($this->admin);
        $this->createGradedBatch('A', 25.0);

        $submissionId = 'dmg_test_unique_sub_12345';

        // First submission
        $this->post(route('inventory.grade-stock.move-to-damage'), [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'source_grade' => 'A',
            'quantity' => 5.0,
            'client_submission_id' => $submissionId,
            'notes' => 'First attempt',
        ])->assertSessionHas('success');

        $this->assertSame(20.0, $this->repository->stockForProductAndGrade($this->product->id, ProductGrade::GradeA, $this->warehouse->id));
        $this->assertSame(5.0, $this->repository->stockForProductAndGrade($this->product->id, ProductGrade::Damage, $this->warehouse->id));

        // Duplicate retried submission with same client_submission_id
        $this->post(route('inventory.grade-stock.move-to-damage'), [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'source_grade' => 'A',
            'quantity' => 5.0,
            'client_submission_id' => $submissionId,
            'notes' => 'First attempt',
        ])->assertSessionHas('success');

        // Stock MUST NOT be deducted twice!
        $this->assertSame(20.0, $this->repository->stockForProductAndGrade($this->product->id, ProductGrade::GradeA, $this->warehouse->id));
        $this->assertSame(5.0, $this->repository->stockForProductAndGrade($this->product->id, ProductGrade::Damage, $this->warehouse->id));
    }

    /**
     * Scenario 9 & 10: Segregation U -> A/B/C and moving segregated stock to DMG.
     */
    public function test_segregation_u_to_commercial_grade_and_subsequent_damage(): void
    {
        $this->actingAs($this->admin);
        $this->createPendingMixedBatch(100.0);

        // Segregate 40 kg to Grade A
        $this->post(route('inventory.grade-stock.transfer'), [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'source_grade' => 'U',
            'target_grade' => 'A',
            'quantity' => 40.0,
            'notes' => 'Segregating premium grade A',
        ])->assertSessionHas('success');

        $this->assertSame(60.0, $this->repository->stockForProductAndGrade($this->product->id, ProductGrade::Unsorted, $this->warehouse->id));
        $this->assertSame(40.0, $this->repository->stockForProductAndGrade($this->product->id, ProductGrade::GradeA, $this->warehouse->id));

        // Now move 5 kg of that segregated Grade A stock to DMG
        $this->post(route('inventory.grade-stock.move-to-damage'), [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'source_grade' => 'A',
            'quantity' => 5.0,
            'notes' => '5 kg bruised after sorting',
        ])->assertSessionHas('success');

        $this->assertSame(35.0, $this->repository->stockForProductAndGrade($this->product->id, ProductGrade::GradeA, $this->warehouse->id));
        $this->assertSame(5.0, $this->repository->stockForProductAndGrade($this->product->id, ProductGrade::Damage, $this->warehouse->id));
        $this->assertSame(100.0, $this->repository->currentStockForProduct($this->product->id, $this->warehouse->id));
    }

    /**
     * Scenario 11: Performed By and Updated By attribution.
     */
    public function test_performed_by_and_updated_by_attribution_on_damage_and_reversal(): void
    {
        // 1. Staff executes Move to Damage
        $this->actingAs($this->staff);
        $this->createGradedBatch('A', 30.0, null, $this->admin);

        $res = $this->post(route('inventory.grade-stock.move-to-damage'), [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'source_grade' => 'A',
            'quantity' => 7.0,
            'notes' => 'Pest damage noticed by staff',
        ]);
        $this->assertSame(302, $res->getStatusCode(), 'Status: '.$res->getStatusCode().' Content: '.$res->getContent());
        $res->assertSessionHas('success');

        // Locate created damage batch
        $dmgBatch = StockBatch::query()
            ->where('product_id', $this->product->id)
            ->where('purchase_grade', 'DMG')
            ->firstOrFail();

        // Performed By is the staff user who executed the action
        $this->assertSame($this->staff->id, (int) $dmgBatch->created_by);
        $this->assertNull($dmgBatch->updated_by);

        // 2. Admin reverses the transfer
        $this->actingAs($this->admin);
        $this->post(route('inventory.grade-stock.reverse', $dmgBatch->id), [
            'quantity' => 7.0,
            'notes' => 'Reversing mistaken damage report',
        ])->assertSessionHas('success');

        // Reload soft-deleted damage batch
        $reversedDmgBatch = StockBatch::withTrashed()->findOrFail($dmgBatch->id);
        $this->assertTrue($reversedDmgBatch->trashed());
        // Performed By remains the original staff performer
        $this->assertSame($this->staff->id, (int) $reversedDmgBatch->created_by);
        // Updated By is the admin who reversed it
        $this->assertSame($this->admin->id, (int) $reversedDmgBatch->updated_by);
    }

    /**
     * Scenario 12 & 13: Damage Movements & Segregations appear in Damage & Loss Report without double counting.
     */
    public function test_damage_movements_and_segregations_integrate_with_damage_loss_report(): void
    {
        $this->actingAs($this->admin);
        $this->createGradedBatch('A', 50.0);
        $this->createPendingMixedBatch(80.0);

        // 1. Perform Segregation: U -> B 30 kg
        $this->post(route('inventory.grade-stock.transfer'), [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'source_grade' => 'U',
            'target_grade' => 'B',
            'quantity' => 30.0,
            'notes' => 'Sorting batch to B',
        ])->assertSessionHas('success');

        // 2. Perform Move to Damage: A -> DMG 6 kg
        $this->post(route('inventory.grade-stock.move-to-damage'), [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'source_grade' => 'A',
            'quantity' => 6.0,
            'notes' => 'Transit damage quarantine',
        ])->assertSessionHas('success');

        // Query Damage & Loss report
        $reportData = $this->reportService->getReportData([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
        ]);

        // Verify Damage Movements section
        $this->assertCount(1, $reportData['damageMovements']);
        $movement = $reportData['damageMovements']->first();
        $this->assertSame($this->product->id, $movement->product_id);
        $this->assertSame('A', $movement->from_grade);
        $this->assertSame(6.0, $movement->damage_qty);
        $this->assertSame('Transit damage quarantine', $movement->reason);
        $this->assertSame($this->admin->name, $movement->performed_by);

        // Verify Segregations section
        $this->assertCount(1, $reportData['segregations']);
        $segregation = $reportData['segregations']->first();
        $this->assertSame($this->product->id, $segregation->product_id);
        $this->assertSame('U', $segregation->from_grade);
        $this->assertSame('B', $segregation->to_grade);
        $this->assertSame(30.0, $segregation->quantity);
        $this->assertSame($this->admin->name, $segregation->performed_by);

        // Crucial: Normal segregation is NOT a financial loss and MUST NOT inflate total_loss!
        // Unsellable damage write-offs summary remains 0 (because physical write-off has not yet occurred).
        $this->assertSame(0.0, (float) $reportData['summary']->total_damaged_cost);
        $this->assertSame(0.0, (float) $reportData['summary']->total_loss);
    }

    /**
     * Scenario 14: Date and warehouse filters in Damage & Loss Report.
     */
    public function test_date_and_warehouse_filters_in_damage_loss_report(): void
    {
        $this->actingAs($this->admin);

        // Warehouse 1 stock & damage
        $this->createGradedBatch('A', 40.0, $this->warehouse);
        $this->post(route('inventory.grade-stock.move-to-damage'), [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'source_grade' => 'A',
            'quantity' => 4.0,
            'notes' => 'WH 1 Damage',
        ]);

        // Warehouse 2 stock & damage
        $this->createGradedBatch('B', 50.0, $this->otherWarehouse);
        $this->post(route('inventory.grade-stock.move-to-damage'), [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->otherWarehouse->id,
            'source_grade' => 'B',
            'quantity' => 9.0,
            'notes' => 'WH 2 Damage',
        ]);

        // Filter by Warehouse 1
        $wh1Report = $this->reportService->getReportData([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
        ]);
        $this->assertCount(1, $wh1Report['damageMovements']);
        $this->assertSame(4.0, $wh1Report['damageMovements']->first()->damage_qty);

        // Filter by Warehouse 2
        $wh2Report = $this->reportService->getReportData([
            'warehouse_id' => $this->otherWarehouse->id,
            'product_id' => $this->product->id,
        ]);
        $this->assertCount(1, $wh2Report['damageMovements']);
        $this->assertSame(9.0, $wh2Report['damageMovements']->first()->damage_qty);
    }

    /**
     * Scenario 15: Reversal of damage movement is reflected in Damage & Loss report as Reversed.
     */
    public function test_reversed_damage_movement_is_flagged_in_report(): void
    {
        $this->actingAs($this->admin);
        $this->createGradedBatch('A', 30.0);

        $this->post(route('inventory.grade-stock.move-to-damage'), [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'source_grade' => 'A',
            'quantity' => 5.0,
            'notes' => 'Testing reversal',
        ]);

        $dmgBatch = StockBatch::where('purchase_grade', 'DMG')->firstOrFail();

        // Reverse the movement
        $this->post(route('inventory.grade-stock.reverse', $dmgBatch->id), [
            'quantity' => 5.0,
        ]);

        $reportData = $this->reportService->getReportData([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
        ]);

        $this->assertCount(1, $reportData['damageMovements']);
        $movement = $reportData['damageMovements']->first();
        $this->assertTrue($movement->is_reversed);
        $this->assertSame('Reversed', $movement->status);
    }
}
