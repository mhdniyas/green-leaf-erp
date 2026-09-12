<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Models\AdvanceReceiveMatch;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvanceReceiveEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->warehouse = Warehouse::factory()->create(['name' => 'Central Hub', 'code' => 'CH1', 'is_active' => true]);
        $category = Category::factory()->create(['name' => 'Vegetables']);
        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Tomato',
            'sku' => 'TOM-01',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');
    }

    private function createAdvanceGrn(float $qty = 10.0): GoodsReceived
    {
        $grn = GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse->id,
            'grn_number' => 'GRN-ADV-'.uniqid(),
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'approved_at' => now(),
            'notes' => 'Initial Advance Receipt',
        ]);

        $item = $grn->items()->create([
            'product_id' => $this->product->id,
            'received_qty' => $qty,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        StockBatch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->admin->id,
            'reference' => 'BATCH-'.uniqid(),
            'received_at' => $grn->received_at,
            'total_kg' => $qty,
            'cost_per_kg' => 0.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
            'notes' => "Auto-created from GRN: {$grn->grn_number}",
        ]);

        return $grn->fresh(['items', 'stockBatches']);
    }

    private function createBillGrn(): GoodsReceived
    {
        return GoodsReceived::create([
            'public_uuid' => (string) Str::uuid(),
            'warehouse_id' => $this->warehouse->id,
            'grn_number' => 'GRN-BILL-'.uniqid(),
            'status' => 'approved',
            'bill_status' => 'bill_available',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'approved_at' => now(),
        ]);
    }

    public function test_can_edit_advance_receive_and_adjust_inventory_difference_only(): void
    {
        Sanctum::actingAs($this->admin);

        $grn = $this->createAdvanceGrn(10.0);
        $item = $grn->items->first();
        $batch = $grn->stockBatches->first();
        $this->assertEquals(10.0, (float) $batch->total_kg);

        // Edit: 10 KG -> 7 KG (difference -3 KG)
        $response1 = $this->putJson("/api/v1/purchasing/grns/{$grn->id}", [
            'items' => [
                [
                    'id' => $item->id,
                    'product_id' => $this->product->id,
                    'received_qty' => 7.0,
                    'received_unit' => 'kg',
                ],
            ],
            'notes' => 'Reduced from 10 to 7',
        ]);

        $response1->assertOk();
        $this->assertEquals(7.0, (float) $batch->fresh()->total_kg);
        $this->assertEquals(7.0, (float) $item->fresh()->received_qty);

        // Edit: 7 KG -> 14 KG (difference +7 KG)
        $response2 = $this->putJson("/api/v1/purchasing/grns/{$grn->id}", [
            'items' => [
                [
                    'id' => $item->id,
                    'product_id' => $this->product->id,
                    'received_qty' => 14.0,
                    'received_unit' => 'kg',
                ],
            ],
            'notes' => 'Increased from 7 to 14',
        ]);

        $response2->assertOk();
        $this->assertEquals(14.0, (float) $batch->fresh()->total_kg);
        $this->assertEquals(14.0, (float) $item->fresh()->received_qty);
    }

    public function test_can_delete_unmatched_advance_receive_and_reverse_inventory(): void
    {
        Sanctum::actingAs($this->admin);

        $grn = $this->createAdvanceGrn(10.0);
        $batch = $grn->stockBatches->first();
        $this->assertEquals(10.0, (float) $batch->total_kg);

        $response = $this->deleteJson("/api/v1/purchasing/grns/{$grn->id}");
        $response->assertOk();

        // StockBatch reversed to 0 and soft deleted
        $this->assertEquals(0.0, (float) $batch->fresh()->total_kg);
        $this->assertTrue($batch->fresh()->trashed());

        // GRN status cancelled and soft deleted
        $this->assertEquals('cancelled', $grn->fresh()->status);
        $this->assertTrue($grn->fresh()->trashed());
    }

    public function test_cannot_delete_partially_matched_advance_receive(): void
    {
        Sanctum::actingAs($this->admin);

        $grn = $this->createAdvanceGrn(10.0);
        $item = $grn->items->first();
        $billGrn = $this->createBillGrn();

        // Create partial match of 4 KG
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $grn->id,
            'advance_goods_received_item_id' => $item->id,
            'bill_goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'matched_qty' => 4.0,
            'matched_unit' => 'kg',
            'base_qty' => 4.0,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->admin->id,
            'confirmed_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v1/purchasing/grns/{$grn->id}");
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['advance']);
        $this->assertStringContainsString('4 KG has already been matched to a bill', $response->json('message'));

        // Batch should NOT be reversed
        $batch = $grn->stockBatches->first();
        $this->assertEquals(10.0, (float) $batch->fresh()->total_kg);
        $this->assertFalse($batch->fresh()->trashed());
    }

    public function test_cannot_edit_partially_matched_advance_below_matched_quantity(): void
    {
        Sanctum::actingAs($this->admin);

        $grn = $this->createAdvanceGrn(10.0);
        $item = $grn->items->first();
        $billGrn = $this->createBillGrn();

        // Create partial match of 4 KG
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $grn->id,
            'advance_goods_received_item_id' => $item->id,
            'bill_goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'matched_qty' => 4.0,
            'matched_unit' => 'kg',
            'base_qty' => 4.0,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->admin->id,
            'confirmed_at' => now(),
        ]);

        // Attempting to reduce below 4 KG (e.g. to 3 KG) must fail
        $responseFail = $this->putJson("/api/v1/purchasing/grns/{$grn->id}", [
            'items' => [
                [
                    'id' => $item->id,
                    'product_id' => $this->product->id,
                    'received_qty' => 3.0,
                    'received_unit' => 'kg',
                ],
            ],
        ]);

        $responseFail->assertStatus(422);
        $responseFail->assertJsonValidationErrors(['items.0.received_qty']);

        // Reducing to 5 KG (>= 4 KG) must succeed
        $responseSuccess = $this->putJson("/api/v1/purchasing/grns/{$grn->id}", [
            'items' => [
                [
                    'id' => $item->id,
                    'product_id' => $this->product->id,
                    'received_qty' => 5.0,
                    'received_unit' => 'kg',
                ],
            ],
        ]);

        $responseSuccess->assertOk();
        $batch = $grn->stockBatches->first();
        $this->assertEquals(5.0, (float) $batch->fresh()->total_kg);
    }

    public function test_cannot_edit_or_delete_fully_matched_advance_receive(): void
    {
        Sanctum::actingAs($this->admin);

        $grn = $this->createAdvanceGrn(10.0);
        $item = $grn->items->first();
        $billGrn = $this->createBillGrn();

        // Fully match 10 KG
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $grn->id,
            'advance_goods_received_item_id' => $item->id,
            'bill_goods_received_id' => $billGrn->id,
            'product_id' => $this->product->id,
            'matched_qty' => 10.0,
            'matched_unit' => 'kg',
            'base_qty' => 10.0,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->admin->id,
            'confirmed_at' => now(),
        ]);

        // Edit blocked
        $responseEdit = $this->putJson("/api/v1/purchasing/grns/{$grn->id}", [
            'items' => [
                [
                    'id' => $item->id,
                    'product_id' => $this->product->id,
                    'received_qty' => 12.0,
                    'received_unit' => 'kg',
                ],
            ],
        ]);
        $responseEdit->assertStatus(422);
        $responseEdit->assertJsonValidationErrors(['advance']);

        // Delete blocked
        $responseDelete = $this->deleteJson("/api/v1/purchasing/grns/{$grn->id}");
        $responseDelete->assertStatus(422);
        $responseDelete->assertJsonValidationErrors(['advance']);
    }
}
