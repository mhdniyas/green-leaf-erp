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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GradeStockManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Warehouse $otherWarehouse;

    private Category $category;

    private Product $product;

    private StockMovementRepository $repository;

    private TransferGradeStockAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'MAIN-WH',
            'is_active' => true,
        ]);

        $this->otherWarehouse = Warehouse::create([
            'name' => 'Secondary Warehouse',
            'code' => 'SEC-WH',
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
        $this->action = app(TransferGradeStockAction::class);
    }

    private function createPendingMixedBatch(float $qty, ?Warehouse $wh = null): StockBatch
    {
        $wh = $wh ?? $this->warehouse;

        return StockBatch::create([
            'batch_number' => 'BATCH-MIXED-'.uniqid(),
            'reference' => 'BAT-MIXED-'.uniqid(),
            'product_id' => $this->product->id,
            'warehouse_id' => $wh->id,
            'received_at' => now(),
            'purchase_grade' => 'U',
            'total_kg' => $qty,
            'cost_per_kg' => 15.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'created_by' => $this->admin->id,
        ]);
    }

    private function createGradedBatch(string $grade, float $qty, ?Warehouse $wh = null): StockBatch
    {
        $wh = $wh ?? $this->warehouse;

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
            'created_by' => $this->admin->id,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $wh->id,
            'type' => StockMovementType::In,
            'grade' => $grade,
            'quantity' => $qty,
            'cost_per_unit' => 20.0,
            'notes' => "Direct receipt {$grade}",
            'created_by' => $this->admin->id,
        ]);

        return $batch;
    }

    /**
     * Test all 12 source-to-destination transfer combinations with exact stock conservation.
     */
    public function test_all_twelve_transfer_directions_preserve_total_inventory(): void
    {
        $combinations = [
            ['U', 'A'], ['U', 'B'], ['U', 'C'],
            ['A', 'U'], ['A', 'B'], ['A', 'C'],
            ['B', 'U'], ['B', 'A'], ['B', 'C'],
            ['C', 'U'], ['C', 'A'], ['C', 'B'],
        ];

        foreach ($combinations as [$from, $to]) {
            // Seed 100 kg in $from grade
            if ($from === 'U') {
                $this->createPendingMixedBatch(100.0);
            } else {
                $this->createGradedBatch($from, 100.0);
            }

            $initialRaw = $this->repository->currentStockByProductAndGrade(warehouseId: $this->warehouse->id, productId: $this->product->id);
            $initialTotal = (float) $initialRaw->sum('current_stock');
            $this->assertEquals(100.0, $initialTotal, "Initial total for {$from}->{$to} failed");

            // Transfer 25 kg from $from to $to
            $result = $this->action->execute(
                product: $this->product,
                warehouse: $this->warehouse,
                sourceGrade: ProductGrade::tryResolve($from),
                targetGrade: ProductGrade::tryResolve($to),
                quantity: 25.0,
                userId: $this->admin->id,
                notes: "Testing {$from} to {$to}"
            );

            $this->assertEquals(25.0, $result['quantity']);
            $this->assertFalse($result['is_idempotent_replay']);

            // Verify canonical stock post-transfer
            $postRaw = $this->repository->currentStockByProductAndGrade(warehouseId: $this->warehouse->id, productId: $this->product->id);
            $postTotal = (float) $postRaw->sum('current_stock');
            $this->assertEquals(100.0, $postTotal, "Total inventory changed during {$from}->{$to}!");

            // Source stock must be 75.0, Destination stock must be 25.0
            $fromStock = (float) $postRaw->firstWhere('grade', $from)?->current_stock;
            $toStock = (float) $postRaw->firstWhere('grade', $to)?->current_stock;

            $this->assertEquals(75.0, $fromStock, "Source {$from} stock incorrect after transfer");
            $this->assertEquals(25.0, $toStock, "Destination {$to} stock incorrect after transfer");

            // Clean up batches for next combination
            StockMovement::truncate();
            StockBatch::truncate();
        }
    }

    /**
     * Test same-grade transfer is strictly rejected.
     */
    public function test_same_grade_transfer_is_rejected(): void
    {
        $this->createGradedBatch('A', 50.0);

        $this->expectException(ValidationException::class);
        $this->action->execute(
            product: $this->product,
            warehouse: $this->warehouse,
            sourceGrade: ProductGrade::GradeA,
            targetGrade: ProductGrade::GradeA,
            quantity: 10.0,
            userId: $this->admin->id
        );
    }

    /**
     * Test transferring more than available source stock is rejected.
     */
    public function test_transfer_more_than_available_is_rejected(): void
    {
        $this->createGradedBatch('B', 20.0);

        $this->expectException(ValidationException::class);
        $this->action->execute(
            product: $this->product,
            warehouse: $this->warehouse,
            sourceGrade: ProductGrade::GradeB,
            targetGrade: ProductGrade::GradeA,
            quantity: 25.0,
            userId: $this->admin->id
        );
    }

    /**
     * Test zero or negative transfer quantity is rejected.
     */
    public function test_zero_or_negative_quantity_is_rejected(): void
    {
        $this->createGradedBatch('A', 20.0);

        $this->expectException(ValidationException::class);
        $this->action->execute(
            product: $this->product,
            warehouse: $this->warehouse,
            sourceGrade: ProductGrade::GradeA,
            targetGrade: ProductGrade::GradeB,
            quantity: 0.0,
            userId: $this->admin->id
        );
    }

    /**
     * Test chained transfers: Segregated child Grade A -> Grade B.
     */
    public function test_segregated_child_batch_can_be_re_transferred(): void
    {
        // 1. Seed Mixed 50 kg
        $this->createPendingMixedBatch(50.0);

        // 2. Transfer Mixed -> A 30 kg
        $r1 = $this->action->execute(
            product: $this->product,
            warehouse: $this->warehouse,
            sourceGrade: ProductGrade::Unsorted,
            targetGrade: ProductGrade::GradeA,
            quantity: 30.0,
            userId: $this->admin->id
        );

        $childA = $r1['destination_batch'];
        $this->assertNotNull($childA);

        // 3. Transfer A -> B 10 kg using that child batch
        $r2 = $this->action->execute(
            product: $this->product,
            warehouse: $this->warehouse,
            sourceGrade: ProductGrade::GradeA,
            targetGrade: ProductGrade::GradeB,
            quantity: 10.0,
            userId: $this->admin->id,
            sourceBatch: $childA
        );

        $this->assertEquals(10.0, $r2['quantity']);

        $postRaw = $this->repository->currentStockByProductAndGrade(warehouseId: $this->warehouse->id, productId: $this->product->id);
        $this->assertEquals(50.0, (float) $postRaw->sum('current_stock'));
        $this->assertEquals(20.0, (float) $postRaw->firstWhere('grade', 'U')?->current_stock);
        $this->assertEquals(20.0, (float) $postRaw->firstWhere('grade', 'A')?->current_stock);
        $this->assertEquals(10.0, (float) $postRaw->firstWhere('grade', 'B')?->current_stock);
    }

    /**
     * Test warehouse isolation: cannot transfer stock from a different warehouse.
     */
    public function test_warehouse_isolation_prevents_cross_warehouse_deduction(): void
    {
        // Seed batch in other warehouse
        $this->createGradedBatch('A', 50.0, $this->otherWarehouse);

        $this->expectException(ValidationException::class);
        $this->action->execute(
            product: $this->product,
            warehouse: $this->warehouse, // Attempting to transfer from Main Warehouse where stock is 0
            sourceGrade: ProductGrade::GradeA,
            targetGrade: ProductGrade::GradeB,
            quantity: 10.0,
            userId: $this->admin->id
        );
    }

    /**
     * Test idempotency with client_submission_id.
     */
    public function test_idempotent_retry_returns_same_result_without_double_deduction(): void
    {
        $this->createGradedBatch('C', 50.0);
        $submissionId = 'idemp-test-uuid-12345';

        // 1st attempt
        $res1 = $this->action->execute(
            product: $this->product,
            warehouse: $this->warehouse,
            sourceGrade: ProductGrade::GradeC,
            targetGrade: ProductGrade::GradeA,
            quantity: 15.0,
            userId: $this->admin->id,
            clientSubmissionId: $submissionId
        );

        // 2nd attempt with same client_submission_id
        $res2 = $this->action->execute(
            product: $this->product,
            warehouse: $this->warehouse,
            sourceGrade: ProductGrade::GradeC,
            targetGrade: ProductGrade::GradeA,
            quantity: 15.0,
            userId: $this->admin->id,
            clientSubmissionId: $submissionId
        );

        $this->assertTrue($res2['is_idempotent_replay']);

        // Stock must have deducted ONLY 15.0, not 30.0!
        $postRaw = $this->repository->currentStockByProductAndGrade(warehouseId: $this->warehouse->id, productId: $this->product->id);
        $this->assertEquals(50.0, (float) $postRaw->sum('current_stock'));
        $this->assertEquals(35.0, (float) $postRaw->firstWhere('grade', 'C')?->current_stock);
        $this->assertEquals(15.0, (float) $postRaw->firstWhere('grade', 'A')?->current_stock);
    }

    /**
     * Test safe reversal and blocked unsafe reversal.
     */
    public function test_safe_reversal_and_unsafe_reversal_handling(): void
    {
        $batch = $this->createGradedBatch('B', 40.0);

        // Transfer B -> A 20 kg
        $res = $this->action->execute(
            product: $this->product,
            warehouse: $this->warehouse,
            sourceGrade: ProductGrade::GradeB,
            targetGrade: ProductGrade::GradeA,
            quantity: 20.0,
            userId: $this->admin->id
        );

        $destBatch = $res['destination_batch'];
        $this->assertNotNull($destBatch);

        // Safe reversal: reverse 10 kg back
        $revRes = $this->action->reverse(
            destinationBatch: $destBatch,
            quantityToReverse: 10.0,
            userId: $this->admin->id
        );

        $this->assertEquals(10.0, $revRes['reversed_quantity']);

        $postRaw = $this->repository->currentStockByProductAndGrade(warehouseId: $this->warehouse->id, productId: $this->product->id);
        $this->assertEquals(40.0, (float) $postRaw->sum('current_stock'));
        $this->assertEquals(30.0, (float) $postRaw->firstWhere('grade', 'B')?->current_stock);
        $this->assertEquals(10.0, (float) $postRaw->firstWhere('grade', 'A')?->current_stock);

        // Unsafe reversal: try to reverse 25 kg when only 10 kg remain in destination batch
        $this->expectException(ValidationException::class);
        $this->action->reverse(
            destinationBatch: $destBatch,
            quantityToReverse: 25.0,
            userId: $this->admin->id
        );
    }

    /**
     * Test Web UI Controller: Tab 1 Current Inventory lists products with zero mixed but positive graded stock.
     */
    public function test_web_index_displays_products_with_only_graded_stock(): void
    {
        // Product has 0 mixed, 25 kg Grade A
        $this->createGradedBatch('A', 25.0);

        $response = $this->actingAs($this->admin)->get(route('inventory.grade-stock.index', [
            'warehouse_id' => $this->warehouse->id,
            'tab' => 'inventory',
        ]));

        $response->assertOk();
        $response->assertSee('Grade Stock Management');
        $response->assertSee('Tomato Hybrid');
        $response->assertSee('25.00'); // Grade A
    }

    /**
     * Test Web UI Controller: Execute transfer via POST route.
     */
    public function test_web_transfer_execution_route(): void
    {
        $this->createGradedBatch('B', 30.0);

        $response = $this->actingAs($this->admin)->post(route('inventory.grade-stock.transfer'), [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'source_grade' => 'B',
            'target_grade' => 'C',
            'quantity' => 12.5,
            'notes' => 'Downgrading to C',
            'client_submission_id' => 'web-sub-1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $postRaw = $this->repository->currentStockByProductAndGrade(warehouseId: $this->warehouse->id, productId: $this->product->id);
        $this->assertEquals(17.5, (float) $postRaw->firstWhere('grade', 'B')?->current_stock);
        $this->assertEquals(12.5, (float) $postRaw->firstWhere('grade', 'C')?->current_stock);
    }

    /**
     * Test JSON API: Paginated grade-wise inventory endpoint.
     */
    public function test_api_inventory_endpoint(): void
    {
        $this->createGradedBatch('A', 40.0);

        $response = $this->actingAs($this->admin)->getJson(route('api.v1.warehouse.grade-stock.inventory', [
            'warehouse_id' => $this->warehouse->id,
        ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => [
                        'product_id',
                        'product_name',
                        'sku',
                        'mixed_qty',
                        'grade_a_qty',
                        'grade_b_qty',
                        'grade_c_qty',
                        'total_stock',
                    ],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'summary_totals' => ['total_mixed', 'total_grade_a', 'total_grade_b', 'total_grade_c', 'total_all'],
            ],
        ]);
    }

    /**
     * Test JSON API: Product transfer details endpoint.
     */
    public function test_api_product_details_endpoint(): void
    {
        $this->createGradedBatch('A', 25.0);

        $response = $this->actingAs($this->admin)->getJson(route('api.v1.warehouse.grade-stock.product-details', [
            'product' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.stock.grade_a', 25);
        $response->assertJsonPath('data.stock.total', 25);
    }

    /**
     * Test JSON API: Transfer execution via API endpoint.
     */
    public function test_api_transfer_execution_endpoint(): void
    {
        $this->createGradedBatch('A', 50.0);

        $response = $this->actingAs($this->admin)->postJson(route('api.v1.warehouse.grade-stock.transfer'), [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'source_grade' => 'A',
            'target_grade' => 'U',
            'quantity' => 15.0,
            'client_submission_id' => 'api-sub-1',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.updated_stock.grade_a', 35);
        $response->assertJsonPath('data.updated_stock.mixed', 15);
        $response->assertJsonPath('data.updated_stock.total', 50);
    }

    /**
     * Test multiple batches of the same grade deducts from selected or FIFO batch.
     */
    public function test_two_batches_of_same_grade_deduction(): void
    {
        $b1 = $this->createGradedBatch('A', 30.0);
        $b2 = $this->createGradedBatch('A', 40.0);

        // Explicitly select batch 2
        $result = $this->action->execute(
            product: $this->product,
            warehouse: $this->warehouse,
            sourceGrade: ProductGrade::GradeA,
            targetGrade: ProductGrade::GradeB,
            quantity: 25.0,
            userId: $this->admin->id,
            sourceBatch: $b2
        );

        $this->assertEquals(25.0, $result['quantity']);
        $b1->refresh();
        $b2->refresh();

        // Batch 1 must remain 30.0, Batch 2 must be 15.0
        $this->assertEquals(30.0, (float) $b1->total_kg);
        $this->assertEquals(15.0, (float) $b2->total_kg);
    }

    /**
     * Test transfer preserves financial and pricing invariant (no changes to purchase prices or selling rates).
     */
    public function test_transfer_does_not_alter_original_costs_or_pricing(): void
    {
        $batch = $this->createGradedBatch('B', 60.0);
        $originalCost = (float) $batch->cost_per_kg;

        $res = $this->action->execute(
            product: $this->product,
            warehouse: $this->warehouse,
            sourceGrade: ProductGrade::GradeB,
            targetGrade: ProductGrade::GradeA,
            quantity: 20.0,
            userId: $this->admin->id
        );

        $destBatch = $res['destination_batch'];
        $batch->refresh();

        $this->assertEquals($originalCost, (float) $batch->cost_per_kg);
        $this->assertEquals($originalCost, (float) $destBatch->cost_per_kg);
    }
}
