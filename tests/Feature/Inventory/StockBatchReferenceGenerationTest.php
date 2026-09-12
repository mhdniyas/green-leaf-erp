<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\BatchStatus;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockBatchRepository;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockBatchReferenceGenerationTest extends TestCase
{
    use RefreshDatabase;

    private StockBatchRepository $repository;

    private User $user;

    private Product $product;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->repository = app(StockBatchRepository::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('admin');

        $this->product = Product::factory()->create([
            'is_active' => true,
        ]);
        $this->warehouse = Warehouse::factory()->create();
    }

    public function test_generates_sequence_001_when_no_existing_batch(): void
    {
        $today = now()->format('Ymd');
        $reference = $this->repository->generateReference();

        $this->assertSame("BATCH-{$today}-001", $reference);
    }

    public function test_generates_next_sequence_when_latest_is_active(): void
    {
        $today = now()->format('Ymd');
        StockBatch::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'reference' => "BATCH-{$today}-170",
        ]);

        $reference = $this->repository->generateReference();

        $this->assertSame("BATCH-{$today}-171", $reference);
    }

    public function test_includes_soft_deleted_batch_when_calculating_next_sequence(): void
    {
        $today = now()->format('Ymd');
        $batch = StockBatch::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'reference' => "BATCH-{$today}-171",
        ]);
        $batch->delete(); // Soft delete

        $this->assertSoftDeleted('stock_batches', ['id' => $batch->id]);

        $reference = $this->repository->generateReference();

        $this->assertSame("BATCH-{$today}-172", $reference);
    }

    public function test_handles_mixed_active_and_deleted_batches_correctly(): void
    {
        $today = now()->format('Ymd');

        StockBatch::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'reference' => "BATCH-{$today}-170",
        ]);

        $deleted171 = StockBatch::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'reference' => "BATCH-{$today}-171",
        ]);
        $deleted171->delete();

        StockBatch::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'reference' => "BATCH-{$today}-172",
        ]);

        $reference = $this->repository->generateReference();

        $this->assertSame("BATCH-{$today}-173", $reference);
    }

    public function test_deleted_highest_sequence_is_never_reused(): void
    {
        $today = now()->format('Ymd');

        $deletedBatch = StockBatch::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'reference' => "BATCH-{$today}-250",
        ]);
        $deletedBatch->delete();

        $reference1 = $this->repository->generateReference();
        $this->assertSame("BATCH-{$today}-251", $reference1);

        $newBatch = $this->repository->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'reference' => $reference1,
            'received_at' => now(),
            'total_kg' => 10.0,
            'cost_per_kg' => 5.0,
            'status' => BatchStatus::Pending,
        ]);

        $this->assertSame("BATCH-{$today}-251", $newBatch->reference);

        $reference2 = $this->repository->generateReference();
        $this->assertSame("BATCH-{$today}-252", $reference2);
    }

    public function test_simulated_unique_reference_collision_retries_and_generates_next_available(): void
    {
        $today = now()->format('Ymd');

        // Create an existing batch
        StockBatch::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'reference' => "BATCH-{$today}-001",
        ]);

        // If a caller passes reference 'BATCH-<today>-001' (colliding), create() must catch and retry with 002
        $createdBatch = $this->repository->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'reference' => "BATCH-{$today}-001",
            'received_at' => now(),
            'total_kg' => 15.0,
            'cost_per_kg' => 8.0,
            'status' => BatchStatus::Pending,
        ]);

        $this->assertInstanceOf(StockBatch::class, $createdBatch);
        $this->assertSame("BATCH-{$today}-002", $createdBatch->reference);
    }

    public function test_other_database_exceptions_are_rethrown_without_retry(): void
    {
        $this->expectException(QueryException::class);

        // Omitting required non-nullable fields (e.g. invalid type or missing required column in raw query)
        $this->repository->create([
            'product_id' => 999999, // Invalid foreign key or missing required fields
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'total_kg' => null, // non-nullable column violation
            'cost_per_kg' => 5.0,
            'status' => BatchStatus::Pending,
        ]);
    }

    public function test_grn_submission_creates_inventory_and_stock_batch_with_proper_reference(): void
    {
        Sanctum::actingAs($this->user);

        $today = now()->format('Ymd');
        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'received_at' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'received_qty' => 50.0,
                    'received_unit' => 'kg',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/purchasing/grns', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('stock_batches', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'reference' => "BATCH-{$today}-001",
            'total_kg' => 50.0,
        ]);

        $batchCount = StockBatch::query()->where('product_id', $this->product->id)->count();
        $this->assertSame(1, $batchCount);
    }
}
