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
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedgerService;
use App\Services\Warehouse\AutoLoadAllService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryDashboardSegregationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Warehouse $otherWarehouse;

    private Category $category;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->warehouse = Warehouse::create([
            'name' => 'Vegetable Warehouse',
            'code' => 'VEG-WH',
            'is_active' => true,
        ]);

        $this->otherWarehouse = Warehouse::create([
            'name' => 'Fruit Warehouse',
            'code' => 'FRT-WH',
            'is_active' => true,
        ]);

        $this->category = Category::factory()->create(['name' => 'Vegetables']);
        $this->product = Product::factory()->create([
            'name' => 'Tomato',
            'sku' => 'TOM-001',
            'unit' => 'kg',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouse->id,
        ]);
    }

    private function createMixedBatch(float $quantity, ?Warehouse $wh = null, string $date = '2026-10-24', string $ref = 'BATCH-TOM-001'): StockBatch
    {
        $targetWh = $wh ?? $this->warehouse;

        return StockBatch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $targetWh->id,
            'created_by' => $this->admin->id,
            'reference' => $ref,
            'received_at' => $date,
            'total_kg' => $quantity,
            'cost_per_kg' => 45.00,
            'transport_cost' => 0.0,
            'labour_cost' => 0.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'purchase_grade' => 'U',
            'grading_mode' => 'sort_required',
        ]);
    }

    public function test_segregate_30_kg_mixed_to_grade_a(): void
    {
        $batch = $this->createMixedBatch(100.0);

        $response = $this->actingAs($this->admin)
            ->postJson(route('inventory.deliveries.dashboard.segregate'), [
                'batch_id' => $batch->id,
                'target_grade' => 'A',
                'quantity' => 30.0,
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $freshBatch = $batch->fresh();
        $this->assertEquals(70.0, (float) $freshBatch->total_kg);
        $this->assertEquals(70.0, (float) $freshBatch->available_mixed_qty);

        $childBatch = StockBatch::where('parent_batch_id', $batch->id)->first();
        $this->assertNotNull($childBatch);
        $this->assertEquals('A', $childBatch->purchase_grade);
        $this->assertEquals(30.0, (float) $childBatch->total_kg);
        $this->assertEquals(BatchStatus::Sorted, $childBatch->status);

        $this->assertDatabaseHas('stock_movements', [
            'batch_id' => $childBatch->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 30.0,
        ]);
    }

    public function test_repeated_partial_segregation_20_kg_to_grade_b_conserves_total_stock(): void
    {
        $batch = $this->createMixedBatch(100.0);

        // Step 1: Segregate 30 kg into Grade A
        $this->actingAs($this->admin)
            ->postJson(route('inventory.deliveries.dashboard.segregate'), [
                'batch_id' => $batch->id,
                'target_grade' => 'A',
                'quantity' => 30.0,
            ])
            ->assertOk();

        // Step 2: Segregate 20 kg into Grade B
        $this->actingAs($this->admin)
            ->postJson(route('inventory.deliveries.dashboard.segregate'), [
                'batch_id' => $batch->id,
                'target_grade' => 'B',
                'quantity' => 20.0,
            ])
            ->assertOk();

        $freshBatch = $batch->fresh();
        // Remaining Mixed: 50 kg
        $this->assertEquals(50.0, (float) $freshBatch->total_kg);
        $this->assertEquals(50.0, (float) $freshBatch->available_mixed_qty);

        $children = StockBatch::where('parent_batch_id', $batch->id)->get();
        $this->assertCount(2, $children);

        $childA = $children->firstWhere('purchase_grade', 'A');
        $childB = $children->firstWhere('purchase_grade', 'B');

        $this->assertNotNull($childA);
        $this->assertEquals(30.0, (float) $childA->total_kg);

        $this->assertNotNull($childB);
        $this->assertEquals(20.0, (float) $childB->total_kg);

        // Total stock conserved: 50 + 30 + 20 = 100 kg
        $totalStock = (float) $freshBatch->total_kg + (float) $childA->total_kg + (float) $childB->total_kg;
        $this->assertEquals(100.0, $totalStock);
    }

    public function test_segregation_data_endpoint_returns_multiple_eligible_source_batches(): void
    {
        $batch1 = $this->createMixedBatch(40.0, $this->warehouse, '2026-10-23', 'BATCH-001');
        $batch2 = $this->createMixedBatch(60.0, $this->warehouse, '2026-10-24', 'BATCH-002');

        $response = $this->actingAs($this->admin)
            ->getJson(route('inventory.deliveries.dashboard.segregation-data', [
                'product' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'date' => '2026-10-24',
            ]));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'stock' => [
                'mixed' => 100.0,
            ],
        ]);

        $batches = $response->json('batches');
        $this->assertCount(2, $batches);
        $this->assertEquals('BATCH-001', $batches[0]['reference']);
        $this->assertEquals(40.0, $batches[0]['available_mixed_qty']);
        $this->assertEquals('BATCH-002', $batches[1]['reference']);
        $this->assertEquals(60.0, $batches[1]['available_mixed_qty']);
    }

    public function test_segregation_records_exact_source_batch_lineage(): void
    {
        $batch1 = $this->createMixedBatch(40.0, $this->warehouse, '2026-10-23', 'BATCH-001');
        $batch2 = $this->createMixedBatch(60.0, $this->warehouse, '2026-10-24', 'BATCH-002');

        // Explicitly segregate 15 kg from batch2
        $response = $this->actingAs($this->admin)
            ->postJson(route('inventory.deliveries.dashboard.segregate'), [
                'batch_id' => $batch2->id,
                'target_grade' => 'C',
                'quantity' => 15.0,
            ]);

        $response->assertOk();

        // Batch 1 is untouched
        $this->assertEquals(40.0, (float) $batch1->fresh()->total_kg);

        // Batch 2 is decremented
        $this->assertEquals(45.0, (float) $batch2->fresh()->total_kg);

        // Child batch records batch2 as parent
        $child = StockBatch::where('parent_batch_id', $batch2->id)->first();
        $this->assertNotNull($child);
        $this->assertEquals(15.0, (float) $child->total_kg);
        $this->assertEquals('C', $child->purchase_grade);
    }

    public function test_insufficient_mixed_quantity_rejected_leaves_stock_unchanged(): void
    {
        $batch = $this->createMixedBatch(50.0);

        $response = $this->actingAs($this->admin)
            ->postJson(route('inventory.deliveries.dashboard.segregate'), [
                'batch_id' => $batch->id,
                'target_grade' => 'A',
                'quantity' => 75.0,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['quantity']);

        // Quantities remain unchanged
        $this->assertEquals(50.0, (float) $batch->fresh()->total_kg);
        $this->assertEquals(0, StockBatch::where('parent_batch_id', $batch->id)->count());
    }

    public function test_dispatched_quantity_cannot_be_segregated(): void
    {
        $batch = $this->createMixedBatch(100.0);

        // Record a dispatch/out movement of 80 kg
        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'grade' => 'U',
            'type' => StockMovementType::Out->value,
            'quantity' => 80.0,
            'cost_per_unit' => 45.00,
            'notes' => 'Dispatched loadout',
        ]);

        // Attempting to segregate 30 kg should fail because only 20 kg remains
        $response = $this->actingAs($this->admin)
            ->postJson(route('inventory.deliveries.dashboard.segregate'), [
                'batch_id' => $batch->id,
                'target_grade' => 'A',
                'quantity' => 30.0,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['quantity']);
    }

    public function test_already_segregated_quantity_cannot_be_counted_again(): void
    {
        $batch = $this->createMixedBatch(50.0);

        // Segregate 30 kg into Grade A
        $this->actingAs($this->admin)
            ->postJson(route('inventory.deliveries.dashboard.segregate'), [
                'batch_id' => $batch->id,
                'target_grade' => 'A',
                'quantity' => 30.0,
            ])
            ->assertOk();

        // Only 20 kg is left; attempting to segregate 25 kg should fail
        $response = $this->actingAs($this->admin)
            ->postJson(route('inventory.deliveries.dashboard.segregate'), [
                'batch_id' => $batch->id,
                'target_grade' => 'B',
                'quantity' => 25.0,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['quantity']);
    }

    public function test_idempotent_retry_with_same_submission_id_does_not_duplicate_movements(): void
    {
        $batch = $this->createMixedBatch(100.0);
        $submissionId = 'seg_test_idempotent_123';

        // First call
        $response1 = $this->actingAs($this->admin)
            ->postJson(route('inventory.deliveries.dashboard.segregate'), [
                'batch_id' => $batch->id,
                'target_grade' => 'A',
                'quantity' => 40.0,
                'client_submission_id' => $submissionId,
            ]);

        $response1->assertOk();

        // Second retried call with identical submission ID
        $response2 = $this->actingAs($this->admin)
            ->postJson(route('inventory.deliveries.dashboard.segregate'), [
                'batch_id' => $batch->id,
                'target_grade' => 'A',
                'quantity' => 40.0,
                'client_submission_id' => $submissionId,
            ]);

        $response2->assertOk();

        // Exactly 1 child batch and 1 movement created
        $this->assertEquals(1, StockBatch::where('parent_batch_id', $batch->id)->count());
        $this->assertEquals(1, StockMovement::where('product_id', $this->product->id)->where('grade', 'A')->count());
        $this->assertEquals(60.0, (float) $batch->fresh()->total_kg);
    }

    public function test_unauthorized_warehouse_access_is_rejected(): void
    {
        $receiver = User::factory()->create();
        $receiver->assignRole('warehouse_receiver');
        // Attach only to Fruit Warehouse
        $receiver->warehouses()->attach($this->otherWarehouse->id);

        // Batch belongs to Vegetable Warehouse
        $batch = $this->createMixedBatch(50.0, $this->warehouse);

        $response = $this->actingAs($receiver)
            ->postJson(route('inventory.deliveries.dashboard.segregate'), [
                'batch_id' => $batch->id,
                'target_grade' => 'A',
                'quantity' => 10.0,
            ]);

        $response->assertStatus(403);
    }

    public function test_dashboard_renders_segregation_table_and_action(): void
    {
        $batch = $this->createMixedBatch(80.0, $this->warehouse, '2026-10-24');

        $response = $this->actingAs($this->admin)
            ->get(route('inventory.deliveries.dashboard', [
                'date' => '2026-10-24',
                'warehouse_id' => $this->warehouse->id,
            ]));

        $response->assertOk();
        $response->assertSee('Produce Grade Segregation');
        $response->assertSee('Tomato');
        $response->assertSee('80.00 kg');
        $response->assertSee('Segregate');
    }

    public function test_automatic_load_all_grade_first_mixed_second(): void
    {
        // 1. Create a 100 kg mixed batch
        $batch = $this->createMixedBatch(100.0, $this->warehouse, '2026-10-24');

        // 2. Segregate 30 kg into Grade A
        $this->actingAs($this->admin)
            ->postJson(route('inventory.deliveries.dashboard.segregate'), [
                'batch_id' => $batch->id,
                'target_grade' => 'A',
                'quantity' => 30.0,
            ])
            ->assertOk();

        // 3. Create DailyPriceApproval for Tomato so loadout can price it
        DailyPriceApproval::create([
            'product_id' => $this->product->id,
            'business_date' => '2026-10-24',
            'grade' => 'A',
            'purchase_price' => 30.0,
            'price_unit' => 'KG',
            'price_a' => 60.0,
            'price_b' => 50.0,
            'price_c' => 40.0,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $priceGroup = ShopPriceGroup::create([
            'name' => 'A',
            'is_active' => true,
        ]);

        $shop = Shop::create([
            'name' => 'Test Shop',
            'code' => 'SHOP-TEST-01',
            'shop_price_group_id' => $priceGroup->id,
            'status' => 'active',
            'allowed_product_grades' => ['A', 'B', 'C'],
        ]);
        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-10-24',
            'order_number' => 'ORD-TEST-LOAD-01',
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
            'order_source' => 'web',
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 40.0,
            'approved_qty' => 40.0,
            'unit' => 'KG',
            'requested_unit' => 'KG',
            'requested_unit_conversion_to_base' => 1.0,
            'sorting_status' => 'allocated',
        ]);

        // 4. Run AutoLoadAllService
        $autoLoadService = app(AutoLoadAllService::class);
        $result = $autoLoadService->loadOrder($order, $this->admin, $this->warehouse->id);
        $this->assertTrue($result['success']);
        $this->assertEquals(40.0, (float) $item->fresh()->loaded_qty);

        $stockLedgerService = app(StockLedgerService::class);
        // Grade A sorted stock should be completely consumed (0 remaining)
        $this->assertEquals(0.0, $stockLedgerService->availableSortedStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::GradeA));

        // Mixed stock should have 60 kg remaining (100 - 30 Grade A - 10 fallback)
        $this->assertEquals(60.0, $stockLedgerService->availableStockForProduct($this->product->id, $this->warehouse->id, ProductGrade::Unsorted));

        // Total OUT movements on shop_order_item should be 30 Grade A and 10 Mixed
        $outMovements = StockMovement::where('shop_order_item_id', $item->id)->get();
        $this->assertCount(2, $outMovements);

        $gradeAMovement = $outMovements->firstWhere('grade', 'A');
        $this->assertNotNull($gradeAMovement);
        $this->assertEquals(30.0, (float) $gradeAMovement->quantity);

        $mixedMovement = $outMovements->firstWhere('grade', 'U');
        $this->assertNotNull($mixedMovement);
        $this->assertEquals(10.0, (float) $mixedMovement->quantity);
    }

    public function test_target_grade_must_be_commercial_a_b_c_only(): void
    {
        $batch = $this->createMixedBatch(50.0);

        // Reject invalid grades like DMG or D or Z from dashboard segregation
        $response = $this->actingAs($this->admin)
            ->postJson(route('inventory.deliveries.dashboard.segregate'), [
                'batch_id' => $batch->id,
                'target_grade' => 'DMG',
                'quantity' => 10.0,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['target_grade']);
    }

    public function test_non_ajax_form_submission_redirects_with_success(): void
    {
        $batch = $this->createMixedBatch(50.0);

        $response = $this->actingAs($this->admin)
            ->from(route('inventory.deliveries.dashboard', ['date' => '2026-10-24']))
            ->post(route('inventory.deliveries.dashboard.segregate'), [
                'batch_id' => $batch->id,
                'target_grade' => 'A',
                'quantity' => 15.0,
            ]);

        $response->assertRedirect(route('inventory.deliveries.dashboard', ['date' => '2026-10-24']));
        $response->assertSessionHas('success');
        $this->assertEquals(35.0, (float) $batch->fresh()->total_kg);
    }

    public function test_l1_dashboard_load_is_fast_and_ignores_irrelevant_grade_a_batches(): void
    {
        // Simulate high volume of historical Grade A batches
        for ($i = 0; $i < 30; $i++) {
            StockBatch::create([
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'received_at' => '2026-10-20',
                'total_kg' => 100.0,
                'cost_per_kg' => 20.0,
                'purchase_grade' => 'A',
                'status' => BatchStatus::Pending,
                'warehouse_receive_pending' => false,
                'created_by' => $this->admin->id,
                'reference' => 'GRN-HIST-'.($i + 1),
            ]);
        }

        // Create one mixed batch that genuinely requires segregation
        $mixedBatch = $this->createMixedBatch(40.0);

        $response = $this->actingAs($this->admin)
            ->get(route('inventory.deliveries.dashboard', ['date' => '2026-10-24']));

        $response->assertOk();
        $response->assertSee('Produce Grade Segregation');
        $response->assertSee('40.00 kg');
        // Must show exactly 1 eligible produce item, not 31
        $response->assertSee('1 Eligible Produce Item');
    }

    public function test_warehouse_resolution_respects_l1_l2_l3_priorities(): void
    {
        // Create mixed batch in Fruit Warehouse (otherWarehouse)
        $batchFruit = StockBatch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->otherWarehouse->id,
            'received_at' => '2026-10-24',
            'total_kg' => 25.0,
            'cost_per_kg' => 15.0,
            'purchase_grade' => 'U',
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'created_by' => $this->admin->id,
            'reference' => 'GRN-FRT-001',
        ]);

        // Priority 1 (L1): Explicit warehouse filter
        $responseVeg = $this->actingAs($this->admin)
            ->get(route('inventory.deliveries.dashboard', [
                'date' => '2026-10-24',
                'warehouse_id' => $this->warehouse->id,
            ]));
        $responseVeg->assertOk();
        $responseVeg->assertDontSee('GRN-FRT-001');

        $responseFruit = $this->actingAs($this->admin)
            ->get(route('inventory.deliveries.dashboard', [
                'date' => '2026-10-24',
                'warehouse_id' => $this->otherWarehouse->id,
            ]));
        $responseFruit->assertOk();
        $responseFruit->assertSee('Fruit Warehouse');
        $responseFruit->assertSee('25.00 kg');
    }

    public function test_stock_consumption_follows_l1_approved_l2_mixed_l3_shortfall_priority(): void
    {
        // 1. Create a mixed batch with 50 kg
        $mixedBatch = $this->createMixedBatch(50.0);

        // 2. Segregate 30 kg into Grade A (Moves 30 kg from L2 Mixed pool into L1 Grade A pool)
        $this->actingAs($this->admin)->postJson(route('inventory.deliveries.dashboard.segregate'), [
            'batch_id' => $mixedBatch->id,
            'target_grade' => 'A',
            'quantity' => 30.0,
        ])->assertOk();

        // 3. Shop order requires 60 kg of Grade A (Exceeds L1 30kg, consumes remaining L2 20kg, and L3 10kg shortfall)
        $shop = Shop::factory()->create();
        $priceGroup = ShopPriceGroup::factory()->create();
        $shop->update(['price_group_id' => $priceGroup->id]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-10-24',
            'delivery_status' => 'pending_delivery',
        ]);

        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 60.0,
            'approved_qty' => 60.0,
            'loaded_qty' => 60.0,
            'unit' => 'KG',
            'requested_unit' => 'KG',
            'requested_unit_conversion_to_base' => 1.0,
            'product_grade' => 'A',
            'sorting_status' => 'loaded',
        ]);

        /** @var StockLedgerService $stockLedgerService */
        $stockLedgerService = app(StockLedgerService::class);
        $stockLedgerService->consumeStockForProductAllowingNegative(
            productId: $this->product->id,
            quantity: 60.0,
            userId: $this->admin->id,
            movementType: StockMovementType::Out,
            notes: 'Test L1 L2 L3 priority consumption',
            shopOrderItemId: $orderItem->id,
            warehouseId: $this->warehouse->id,
            grade: ProductGrade::GradeA,
        );

        $outMovements = StockMovement::query()
            ->where('shop_order_item_id', $orderItem->id)
            ->where('type', StockMovementType::Out->value)
            ->get();

        // Must have:
        // L1 Priority: 30 kg Grade A
        // L2 Priority: 20 kg Mixed (Grade U)
        // L3 Priority: 10 kg Shortfall / Negative stock under Grade A
        $gradeAMovements = $outMovements->where('grade', 'A');
        $mixedMovements = $outMovements->where('grade', 'U');

        $this->assertEquals(40.0, (float) $gradeAMovements->sum('quantity')); // 30kg segregated + 10kg shortfall
        $this->assertEquals(20.0, (float) $mixedMovements->sum('quantity')); // 20kg mixed pool
        $this->assertEquals(60.0, (float) $outMovements->sum('quantity'));
    }
}
