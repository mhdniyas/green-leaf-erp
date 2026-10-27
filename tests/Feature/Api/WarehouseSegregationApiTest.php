<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseSegregationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $warehouseUser;

    private User $unauthorizedUser;

    private Warehouse $warehouseVeg;

    private Warehouse $warehouseFrt;

    private Category $category;

    private Product $productA;

    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->warehouseVeg = Warehouse::create([
            'name' => 'Vegetable Warehouse',
            'code' => 'VEG-WH',
            'is_active' => true,
        ]);

        $this->warehouseFrt = Warehouse::create([
            'name' => 'Fruit Warehouse',
            'code' => 'FRT-WH',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->warehouseUser = User::factory()->create();
        $this->warehouseUser->assignRole('warehouse_receiver');
        $this->warehouseUser->warehouses()->attach($this->warehouseVeg->id);

        $this->unauthorizedUser = User::factory()->create();

        $this->category = Category::create([
            'name' => 'Vegetables',
            'is_active' => true,
        ]);

        $this->productA = Product::create([
            'name' => 'Tomato H',
            'sku' => '101',
            'unit' => 'kg',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouseVeg->id,
            'is_active' => true,
        ]);

        $this->productB = Product::create([
            'name' => 'Carrot O',
            'sku' => '102',
            'unit' => 'kg',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouseVeg->id,
            'is_active' => true,
        ]);
    }

    private function createBatch(Product $product, Warehouse $warehouse, float $qty, string $grade = 'U', string $status = 'pending', ?int $parentId = null): StockBatch
    {
        return StockBatch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'parent_batch_id' => $parentId,
            'received_at' => '2026-10-24',
            'total_kg' => $qty,
            'cost_per_kg' => 20.0,
            'purchase_grade' => $grade,
            'status' => $status === 'sorted' ? BatchStatus::Sorted : BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'created_by' => $this->admin->id,
            'reference' => 'GRN-'.uniqid(),
        ]);
    }

    public function test_01_authenticated_product_summary(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $this->createBatch($this->productA, $this->warehouseVeg, 60.0);

        $response = $this->getJson(route('api.v1.warehouse.segregation.summary', [
            'warehouse_id' => $this->warehouseVeg->id,
        ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals(101, $data[0]['product_sku']);
        $this->assertEquals('Tomato H', $data[0]['product_name']);
        $this->assertEquals(60.0, (float) $data[0]['available_mixed']);
    }

    public function test_02_unauthorized_access_rejected(): void
    {
        // Guest
        $response = $this->getJson(route('api.v1.warehouse.segregation.summary'));
        $response->assertStatus(401);

        // User without roles/permissions
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->getJson(route('api.v1.warehouse.segregation.summary'));
        $response->assertStatus(403);
    }

    public function test_03_warehouse_isolation(): void
    {
        Sanctum::actingAs($this->warehouseUser); // only assigned to warehouseVeg

        // Attempting to query warehouseFrt should be forbidden for this scoped user
        $response = $this->getJson(route('api.v1.warehouse.segregation.summary', [
            'warehouse_id' => $this->warehouseFrt->id,
        ]));
        $response->assertStatus(403);
    }

    public function test_04_product_search_and_pagination(): void
    {
        Sanctum::actingAs($this->admin);

        $this->createBatch($this->productA, $this->warehouseVeg, 50.0);
        $this->createBatch($this->productB, $this->warehouseVeg, 30.0);

        // Search for Carrot
        $response = $this->getJson(route('api.v1.warehouse.segregation.summary', [
            'warehouse_id' => $this->warehouseVeg->id,
            'search' => 'Carrot',
        ]));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Carrot O', $data[0]['product_name']);

        // Pagination per_page = 1
        $responsePaged = $this->getJson(route('api.v1.warehouse.segregation.summary', [
            'warehouse_id' => $this->warehouseVeg->id,
            'per_page' => 1,
            'page' => 1,
        ]));
        $responsePaged->assertOk();
        $this->assertCount(1, $responsePaged->json('data'));
        $this->assertEquals(2, $responsePaged->json('meta.total'));
    }

    public function test_05_eligible_source_batches(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $batch1 = $this->createBatch($this->productA, $this->warehouseVeg, 40.0);
        $batch2 = $this->createBatch($this->productA, $this->warehouseVeg, 20.0);

        $response = $this->getJson(route('api.v1.warehouse.segregation.batches', [
            'product_id' => $this->productA->id,
            'warehouse_id' => $this->warehouseVeg->id,
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.product.name', 'Tomato H');
        $batches = $response->json('data.batches');
        $this->assertCount(2, $batches);
        $this->assertEquals(60.0, (float) $response->json('data.stock.mixed'));
    }

    public function test_06_incorrect_source_batch_rejected(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        // Closed batch cannot be segregated
        $closedBatch = StockBatch::create([
            'product_id' => $this->productA->id,
            'warehouse_id' => $this->warehouseVeg->id,
            'received_at' => '2026-10-24',
            'total_kg' => 0.0,
            'cost_per_kg' => 20.0,
            'purchase_grade' => 'U',
            'status' => BatchStatus::Closed,
            'warehouse_receive_pending' => false,
            'created_by' => $this->admin->id,
            'reference' => 'GRN-CLOSED-01',
        ]);

        $response = $this->postJson(route('api.v1.inventory.batches.segregate', $closedBatch), [
            'target_grade' => 'A',
            'quantity' => 10.0,
        ]);

        $response->assertStatus(422);
    }

    public function test_07_grade_a_segregation(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $batch = $this->createBatch($this->productA, $this->warehouseVeg, 100.0);

        $response = $this->postJson(route('api.v1.inventory.batches.segregate', $batch), [
            'target_grade' => 'A',
            'quantity' => 30.0,
            'notes' => 'Segregated to Grade A',
        ]);

        $response->assertOk();
        $this->assertEquals(70.0, (float) $batch->fresh()->total_kg);
        $this->assertEquals('A', $response->json('data.child.purchase_grade'));
        $this->assertEquals(30.0, (float) $response->json('data.child.total_kg'));

        // Check stock movement IN created on child
        $childId = $response->json('data.child.id');
        $movement = StockMovement::where('batch_id', $childId)->where('type', StockMovementType::In->value)->first();
        $this->assertNotNull($movement);
        $gradeVal = $movement->grade instanceof ProductGrade ? $movement->grade->value : (string) $movement->grade;
        $this->assertEquals('A', $gradeVal);
        $this->assertEquals(30.0, (float) $movement->quantity);
    }

    public function test_08_grade_b_segregation(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $batch = $this->createBatch($this->productA, $this->warehouseVeg, 100.0);

        $response = $this->postJson(route('api.v1.inventory.batches.segregate', $batch), [
            'target_grade' => 'B',
            'quantity' => 25.0,
        ]);

        $response->assertOk();
        $this->assertEquals('B', $response->json('data.child.purchase_grade'));
        $this->assertEquals(25.0, (float) $response->json('data.child.total_kg'));
    }

    public function test_09_grade_c_segregation(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $batch = $this->createBatch($this->productA, $this->warehouseVeg, 100.0);

        $response = $this->postJson(route('api.v1.inventory.batches.segregate', $batch), [
            'target_grade' => 'C',
            'quantity' => 15.0,
        ]);

        $response->assertOk();
        $this->assertEquals('C', $response->json('data.child.purchase_grade'));
        $this->assertEquals(15.0, (float) $response->json('data.child.total_kg'));
    }

    public function test_10_insufficient_mixed_quantity_rejected(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $batch = $this->createBatch($this->productA, $this->warehouseVeg, 10.0);

        $response = $this->postJson(route('api.v1.inventory.batches.segregate', $batch), [
            'target_grade' => 'A',
            'quantity' => 15.0, // Exceeds 10kg
        ]);

        $response->assertStatus(422);
    }

    public function test_11_repeated_request_idempotency(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $batch = $this->createBatch($this->productA, $this->warehouseVeg, 80.0);
        $subId = 'sub-test-unique-123';

        $res1 = $this->postJson(route('api.v1.inventory.batches.segregate', $batch), [
            'target_grade' => 'A',
            'quantity' => 20.0,
            'client_submission_id' => $subId,
        ]);
        $res1->assertOk();
        $childId = $res1->json('data.child.id');

        // Second identical request
        $res2 = $this->postJson(route('api.v1.inventory.batches.segregate', $batch), [
            'target_grade' => 'A',
            'quantity' => 20.0,
            'client_submission_id' => $subId,
        ]);
        $res2->assertOk();
        $this->assertEquals($childId, $res2->json('data.child.id'));
        $this->assertEquals(60.0, (float) $batch->fresh()->total_kg);
    }

    public function test_12_conflicting_payload_with_same_request_id_rejected(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $batch = $this->createBatch($this->productA, $this->warehouseVeg, 80.0);
        $subId = 'sub-test-conflict-456';

        $res1 = $this->postJson(route('api.v1.inventory.batches.segregate', $batch), [
            'target_grade' => 'A',
            'quantity' => 20.0,
            'client_submission_id' => $subId,
        ]);
        $res1->assertOk();

        // Second request with DIFFERENT payload but SAME client_submission_id
        $res2 = $this->postJson(route('api.v1.inventory.batches.segregate', $batch), [
            'target_grade' => 'B',
            'quantity' => 30.0,
            'client_submission_id' => $subId,
        ]);
        $res2->assertStatus(422);
    }

    public function test_13_reversal_safety(): void
    {
        Sanctum::actingAs($this->warehouseUser);

        $batch = $this->createBatch($this->productA, $this->warehouseVeg, 50.0);

        // 1. Segregate 20kg
        $segRes = $this->postJson(route('api.v1.inventory.batches.segregate', $batch), [
            'target_grade' => 'A',
            'quantity' => 20.0,
        ]);
        $segRes->assertOk();
        $childId = $segRes->json('data.child.id');
        $childBatch = StockBatch::findOrFail($childId);

        // 2. Reverse 10kg
        $revRes = $this->postJson(route('api.v1.inventory.batches.segregate.reverse', $childBatch), [
            'quantity' => 10.0,
            'notes' => 'Reversed 10kg back to mixed',
        ]);
        $revRes->assertOk();

        $this->assertEquals(40.0, (float) $batch->fresh()->total_kg); // 30 + 10 = 40
        $this->assertEquals(10.0, (float) $childBatch->fresh()->total_kg); // 20 - 10 = 10
    }
}
