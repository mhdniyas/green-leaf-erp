<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Actions\Inventory\ProcessPartialSegregationAction;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\AdvanceReceiveMatch;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Inventory\StockBatchService;
use App\Services\Purchasing\GoodsReceivedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SegregationIdempotencyAndDeletionSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private User $admin;

    private Warehouse $warehouse;

    private Supplier $supplier;

    private Category $category;

    private Product $product;

    private StockMovementRepository $stockRepo;

    private GoodsReceivedService $grnService;

    private StockBatchService $batchService;

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
        Permission::firstOrCreate(['name' => 'purchasing.grn.delete']);

        $receiverRole = Role::findByName('warehouse_receiver');
        $receiverRole->givePermissionTo([
            'warehouse.receive.view',
            'warehouse.receive.confirm',
            'warehouse.receive.sort',
            'inventory.sorting.process',
            'inventory.product.view',
        ]);

        $adminRole = Role::findByName('admin');
        $adminRole->givePermissionTo(Permission::all());

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
        $this->admin->warehouses()->attach($this->warehouse);

        $this->supplier = Supplier::factory()->create([
            'name' => 'Fresh Farm Supplier',
        ]);

        $this->category = Category::factory()->create(['name' => 'Vegetables']);
        $this->product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Fresh Onion',
            'sku' => 'VEG-ONI-001',
            'unit' => 'KG',
            'is_active' => true,
        ]);

        $this->stockRepo = app(StockMovementRepository::class);
        $this->grnService = app(GoodsReceivedService::class);
        $this->batchService = app(StockBatchService::class);
        $this->segregationAction = app(ProcessPartialSegregationAction::class);
    }

    private function createParentMixedBatch(float $quantity = 100.0, ?GoodsReceived $grn = null, ?GoodsReceivedItem $item = null): StockBatch
    {
        $batch = StockBatch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $grn?->id,
            'goods_received_item_id' => $item?->id,
            'created_by' => $this->receiver->id,
            'reference' => 'BAT-MIX-'.uniqid(),
            'received_at' => now(),
            'total_kg' => $quantity,
            'cost_per_kg' => 25.0,
            'transport_cost' => 0.0,
            'labour_cost' => 0.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->receiver->id,
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
            'notes' => 'Parent mixed batch for segregation test',
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $batch->product_id,
            'warehouse_id' => $batch->warehouse_id,
            'created_by' => $this->receiver->id,
            'grade' => 'U',
            'type' => StockMovementType::In->value,
            'quantity' => $quantity,
            'cost_per_unit' => 25.0,
            'notes' => 'Initial mixed receipt',
        ]);

        return $batch;
    }

    private function createAdvanceReceipt(float $quantity = 100.0, string $grade = 'U', ?string $gradingMode = 'sort_required'): GoodsReceived
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-ADV-'.uniqid(),
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => null,
            'received_by' => $this->receiver->id,
            'received_at' => now()->toDateString(),
            'status' => 'received',
            'is_advance_receive' => true,
            'receipt_type' => 'warehouse_advance',
            'advance_cleared' => false,
            'advance_balance_kg' => $quantity,
        ]);

        $item = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'purchase_order_item_id' => null,
            'received_qty' => $quantity,
            'purchased_qty' => $quantity,
            'variance' => 0.0,
            'grade' => $grade,
            'grading_mode' => $gradingMode,
            'received_unit' => 'KG',
        ]);

        $this->createParentMixedBatch($quantity, $grn, $item);

        return $grn;
    }

    public function test_identical_retried_segregation_request_does_not_create_duplicate_child_batch_or_deduct_parent_stock_twice(): void
    {
        $batch = $this->createParentMixedBatch(100.0);
        $clientSubmissionId = 'sub-uuid-test-001';

        $payload = [
            'target_grade' => 'B',
            'quantity' => 20.0,
            'notes' => 'Segregating 20kg to Grade B',
            'client_submission_id' => $clientSubmissionId,
        ];

        // First segregation request
        $response1 = $this->actingAs($this->receiver)
            ->postJson(route('api.v1.inventory.batches.segregate', $batch), $payload);

        $response1->assertOk();
        $childId1 = $response1->json('data.child.id');

        $batch->refresh();
        $this->assertEquals(80.0, (float) $batch->total_kg, 'Parent batch must be decremented to 80 kg');
        $this->assertEquals(1, $batch->segregatedBatches()->count(), 'Exactly one segregated child batch must exist');

        // Retried identical request with same client_submission_id (e.g. network timeout)
        $response2 = $this->actingAs($this->receiver)
            ->postJson(route('api.v1.inventory.batches.segregate', $batch), $payload);

        $response2->assertOk();
        $childId2 = $response2->json('data.child.id');

        $this->assertEquals($childId1, $childId2, 'Retried request must return the existing child batch ID');

        $batch->refresh();
        $this->assertEquals(80.0, (float) $batch->total_kg, 'Retried request must NOT deduct stock twice');
        $this->assertEquals(1, $batch->segregatedBatches()->count(), 'No second child batch should be created on retry');
    }

    public function test_separate_intentional_new_segregation_is_permitted_with_distinct_submission_id(): void
    {
        $batch = $this->createParentMixedBatch(100.0);

        // First intentional segregation: 20 kg into Grade B
        $response1 = $this->actingAs($this->receiver)
            ->postJson(route('api.v1.inventory.batches.segregate', $batch), [
                'target_grade' => 'B',
                'quantity' => 20.0,
                'client_submission_id' => 'sub-uuid-test-001',
            ]);
        $response1->assertOk();

        // Second intentional segregation: 20 kg into Grade B with NEW submission ID
        $response2 = $this->actingAs($this->receiver)
            ->postJson(route('api.v1.inventory.batches.segregate', $batch), [
                'target_grade' => 'B',
                'quantity' => 20.0,
                'client_submission_id' => 'sub-uuid-test-002',
            ]);
        $response2->assertOk();

        $batch->refresh();
        $this->assertEquals(60.0, (float) $batch->total_kg, 'Parent batch must be decremented to 60 kg after two intentional segregations');
        $this->assertEquals(2, $batch->segregatedBatches()->count(), 'Exactly two child batches must exist');
    }

    public function test_retried_segregation_with_conflicting_payload_is_rejected(): void
    {
        $batch = $this->createParentMixedBatch(100.0);
        $clientSubmissionId = 'sub-uuid-test-conflict';

        $this->actingAs($this->receiver)
            ->postJson(route('api.v1.inventory.batches.segregate', $batch), [
                'target_grade' => 'B',
                'quantity' => 20.0,
                'client_submission_id' => $clientSubmissionId,
            ])
            ->assertOk();

        // Retry with same submission ID but conflicting quantity
        $responseConflictQty = $this->actingAs($this->receiver)
            ->postJson(route('api.v1.inventory.batches.segregate', $batch), [
                'target_grade' => 'B',
                'quantity' => 30.0,
                'client_submission_id' => $clientSubmissionId,
            ]);

        $responseConflictQty->assertStatus(422);
        $responseConflictQty->assertJsonValidationErrors(['client_submission_id']);

        // Retry with same submission ID but conflicting target grade
        $responseConflictGrade = $this->actingAs($this->receiver)
            ->postJson(route('api.v1.inventory.batches.segregate', $batch), [
                'target_grade' => 'A',
                'quantity' => 20.0,
                'client_submission_id' => $clientSubmissionId,
            ]);

        $responseConflictGrade->assertStatus(422);
        $responseConflictGrade->assertJsonValidationErrors(['client_submission_id']);
    }

    public function test_insufficient_stock_segregation_is_rejected(): void
    {
        $batch = $this->createParentMixedBatch(50.0);

        $response = $this->actingAs($this->receiver)
            ->postJson(route('api.v1.inventory.batches.segregate', $batch), [
                'target_grade' => 'A',
                'quantity' => 60.0,
                'client_submission_id' => 'sub-uuid-insufficient',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['quantity']);

        $batch->refresh();
        $this->assertEquals(50.0, (float) $batch->total_kg, 'Parent stock must remain unchanged after rejected segregation');
    }

    public function test_retried_reversal_is_idempotent(): void
    {
        $batch = $this->createParentMixedBatch(100.0);

        $segResult = $this->segregationAction->execute(
            $batch,
            ProductGrade::GradeB,
            20.0,
            $this->receiver->id,
            'Initial segregation',
            'sub-uuid-for-rev'
        );

        $childBatch = $segResult['child'];
        $batch->refresh();
        $this->assertEquals(80.0, (float) $batch->total_kg);

        $revSubmissionId = 'rev-uuid-test-001';

        // First reversal
        $rev1 = $this->actingAs($this->receiver)
            ->postJson(route('api.v1.inventory.batches.segregate.reverse', $childBatch), [
                'quantity' => 20.0,
                'client_submission_id' => $revSubmissionId,
            ]);
        $rev1->assertOk();

        $batch->refresh();
        $this->assertEquals(100.0, (float) $batch->total_kg, 'Parent stock must be restored to 100 kg');

        // Retried reversal with same submission ID
        $rev2 = $this->actingAs($this->receiver)
            ->postJson(route('api.v1.inventory.batches.segregate.reverse', $childBatch), [
                'quantity' => 20.0,
                'client_submission_id' => $revSubmissionId,
            ]);
        $rev2->assertOk();

        $batch->refresh();
        $this->assertEquals(100.0, (float) $batch->total_kg, 'Retried reversal must NOT credit parent stock again');
    }

    public function test_advance_receipt_not_segregated_can_be_deleted_reversing_stock(): void
    {
        $grn = $this->createAdvanceReceipt(100.0);

        $stockBefore = $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id);
        $this->assertEquals(100.0, $stockBefore);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/purchasing/grns/{$grn->id}");

        $response->assertOk();

        $stockAfter = $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id);
        $this->assertEquals(0.0, $stockAfter, 'Stock must return to 0 kg after deleting advance receipt');

        $grn->refresh();
        $this->assertEquals('cancelled', $grn->status);
        $this->assertNotNull($grn->deleted_at);
    }

    public function test_advance_receipt_partially_segregated_without_dispatch_can_be_deleted_cleaning_all_batches(): void
    {
        $grn = $this->createAdvanceReceipt(100.0);
        $parentBatch = StockBatch::query()->where('goods_received_id', $grn->id)->firstOrFail();

        // Segregate 30 kg to Grade A
        $this->segregationAction->execute(
            $parentBatch,
            ProductGrade::GradeA,
            30.0,
            $this->receiver->id,
            'Segregation before GRN deletion',
            'sub-uuid-seg-adv'
        );

        $parentBatch->refresh();
        $this->assertEquals(70.0, (float) $parentBatch->total_kg);
        $this->assertEquals(1, $parentBatch->segregatedBatches()->count());
        $childBatch = $parentBatch->segregatedBatches()->firstOrFail();

        $stockMixed = $this->stockRepo->stockForProductAndGrade($this->product->id, ProductGrade::Unsorted, $this->warehouse->id);
        $stockGradeA = $this->stockRepo->stockForProductAndGrade($this->product->id, ProductGrade::GradeA, $this->warehouse->id);
        $this->assertEquals(70.0, $stockMixed);
        $this->assertEquals(30.0, $stockGradeA);

        // Delete advance receipt
        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/purchasing/grns/{$grn->id}");

        $response->assertOk();

        // Verify physical stock is completely zeroed
        $stockAfterTotal = $this->stockRepo->currentStockForProduct($this->product->id, $this->warehouse->id);
        $stockAfterMixed = $this->stockRepo->stockForProductAndGrade($this->product->id, ProductGrade::Unsorted, $this->warehouse->id);
        $stockAfterGradeA = $this->stockRepo->stockForProductAndGrade($this->product->id, ProductGrade::GradeA, $this->warehouse->id);

        $this->assertEquals(0.0, $stockAfterTotal, 'Total physical stock must be 0 kg after deleting advance receipt');
        $this->assertEquals(0.0, $stockAfterMixed, 'Mixed physical stock must be 0 kg');
        $this->assertEquals(0.0, $stockAfterGradeA, 'Grade A physical stock must be 0 kg');

        // Verify child batch is closed and soft-deleted (NOT orphaned)
        $childBatch->refresh();
        $this->assertEquals(BatchStatus::Closed, $childBatch->status);
        $this->assertEquals(0.0, (float) $childBatch->total_kg);
        $this->assertNotNull($childBatch->deleted_at, 'Child batch must be soft-deleted and not left orphaned');
    }

    public function test_advance_receipt_with_partially_dispatched_segregated_stock_cannot_be_deleted(): void
    {
        $grn = $this->createAdvanceReceipt(100.0);
        $parentBatch = StockBatch::query()->where('goods_received_id', $grn->id)->firstOrFail();

        // Segregate 30 kg to Grade A
        $result = $this->segregationAction->execute(
            $parentBatch,
            ProductGrade::GradeA,
            30.0,
            $this->receiver->id,
            'Segregating before dispatch'
        );
        $childBatch = $result['child'];

        // Simulate dispatch: record an Out movement of 10 kg from the child batch
        StockMovement::create([
            'batch_id' => $childBatch->id,
            'product_id' => $childBatch->product_id,
            'warehouse_id' => $childBatch->warehouse_id,
            'created_by' => $this->receiver->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 10.0,
            'cost_per_unit' => 25.0,
            'notes' => 'Dispatched to shop order',
        ]);

        // Attempt to delete advance receipt
        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/purchasing/grns/{$grn->id}");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['advance']);
        $this->assertStringContainsString('dispatched to orders', $response->json('message'));

        $grn->refresh();
        $this->assertEquals('received', $grn->status);
        $this->assertNull($grn->deleted_at);
    }

    public function test_advance_receipt_matched_against_vendor_bill_cannot_be_deleted(): void
    {
        $grn = $this->createAdvanceReceipt(100.0);
        $advItem = $grn->items()->firstOrFail();

        // Create a bill GRN and match 50 kg against this advance
        $billGrn = GoodsReceived::create([
            'grn_number' => 'GRN-BILL-'.uniqid(),
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'received_by' => $this->receiver->id,
            'received_at' => now()->toDateString(),
            'status' => 'received',
            'is_advance_receive' => false,
            'receipt_type' => 'normal_purchase',
        ]);

        $billItem = GoodsReceivedItem::create([
            'goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'received_qty' => 50.0,
            'purchased_qty' => 50.0,
            'variance' => 0.0,
            'grade' => 'U',
            'received_unit' => 'KG',
        ]);

        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $grn->id,
            'advance_goods_received_item_id' => $advItem->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billItem->id,
            'product_id' => $this->product->id,
            'matched_qty' => 50.0,
            'base_qty' => 50.0,
            'confirmed_by' => $this->receiver->id,
            'confirmed_at' => now(),
        ]);

        // Attempt to delete advance receipt
        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/purchasing/grns/{$grn->id}");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['advance']);
        $this->assertStringContainsString('already been matched to a bill', $response->json('message'));
    }

    public function test_stock_batch_service_delete_blocks_deletion_of_batch_with_active_segregated_children_or_dispatches(): void
    {
        $parentBatch = $this->createParentMixedBatch(100.0);

        // Segregate 20 kg
        $this->segregationAction->execute(
            $parentBatch,
            ProductGrade::GradeB,
            20.0,
            $this->receiver->id
        );

        // Direct service deletion on parent batch with active children must be blocked
        $this->expectException(ValidationException::class);
        $this->batchService->delete($parentBatch);
    }
}
