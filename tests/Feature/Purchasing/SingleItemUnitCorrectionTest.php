<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\AdvanceReceiveMatch;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SingleItemUnitCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Supplier $supplier;

    private Product $cauliflower;

    private Product $potato;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->warehouse = Warehouse::factory()->create([
            'name' => 'Main Vegetable Hub',
            'code' => 'MAIN-HUB',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::factory()->create([
            'name' => 'Test Farmer',
        ]);

        $this->cauliflower = Product::factory()->create([
            'name' => 'Cauliflower',
            'sku' => 'CAULI-01',
            'unit' => 'KG',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $this->potato = Product::factory()->create([
            'name' => 'Potato Agra',
            'sku' => 'POT-AGRA',
            'unit' => 'KG',
            'default_warehouse_id' => $this->warehouse->id,
        ]);
    }

    public function test_single_grn_item_unit_correction_leaves_sibling_items_and_inventory_untouched(): void
    {
        // 1. Create Advance GRN with 2 items: Cauliflower 103 KG and Potato Agra 200 KG
        $grn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_by' => $this->admin->id,
            'received_at' => '2026-09-13 08:00:00',
        ]);

        $billGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'approved',
            'received_by' => $this->admin->id,
            'received_at' => '2026-09-13 09:00:00',
        ]);

        $cauliItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->cauliflower->id,
            'received_qty' => 103.0,
            'received_unit' => 'PIECE', // Mismatched unit
        ]);

        $potatoItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->potato->id,
            'received_qty' => 200.0,
            'received_unit' => 'KG',
        ]);

        $batchPotato = StockBatch::factory()->create([
            'product_id' => $this->potato->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $potatoItem->id,
            'warehouse_receive_pending' => false,
            'total_kg' => 200.0,
        ]);

        // Potato Agra has already been matched
        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $grn->id,
            'advance_goods_received_item_id' => $potatoItem->id,
            'advance_stock_batch_id' => $batchPotato->id,
            'bill_goods_received_id' => $billGrn->id,
            'product_id' => $this->potato->id,
            'matched_qty' => 200.0,
            'matched_unit' => 'KG',
            'base_qty' => 200.0,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->admin->id,
            'confirmed_at' => now(),
        ]);

        $initialMovementCount = StockMovement::count();
        $initialBatchCount = StockBatch::count();

        // 2. Perform Single Item Unit Correction on Cauliflower ONLY
        $response = $this->actingAs($this->admin, 'sanctum')->patchJson(
            "/api/v1/purchasing/grn-items/{$cauliItem->id}/unit",
            ['unit' => 'KG']
        );

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.received_unit', 'KG');

        // 3. Verify database state
        $cauliItem->refresh();
        $potatoItem->refresh();

        // Cauliflower unit updated, quantity unchanged
        $this->assertSame('KG', $cauliItem->received_unit);
        $this->assertEquals(103.0, (float) $cauliItem->received_qty);

        // Potato Agra completely untouched, not deleted!
        $this->assertFalse($potatoItem->trashed());
        $this->assertSame('KG', $potatoItem->received_unit);
        $this->assertEquals(200.0, (float) $potatoItem->received_qty);

        // Verify total items count on GRN is still 2
        $this->assertEquals(2, GoodsReceivedItem::where('goods_received_id', $grn->id)->count());

        // Verify NO stock movements or new batches created
        $this->assertEquals($initialMovementCount, StockMovement::count());
        $this->assertEquals($initialBatchCount, StockBatch::count());
    }

    public function test_single_purchase_order_item_unit_correction(): void
    {
        $order = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'created_by' => $this->admin->id,
            'order_date' => '2026-09-13',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'product_id' => $this->cauliflower->id,
            'quantity' => 50.0,
            'purchase_unit' => 'PIECE',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')->patchJson(
            "/api/v1/purchasing/order-items/{$poItem->id}/unit",
            ['unit' => 'KG']
        );

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.purchase_unit', 'KG');

        $poItem->refresh();
        $this->assertSame('KG', $poItem->purchase_unit);
        $this->assertEquals(50.0, (float) $poItem->quantity);
    }

    public function test_unit_correction_rejects_when_target_item_itself_is_already_matched(): void
    {
        $grn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_by' => $this->admin->id,
            'received_at' => '2026-09-13 08:00:00',
        ]);

        $billGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'normal_purchase',
            'status' => 'approved',
            'received_by' => $this->admin->id,
            'received_at' => '2026-09-13 09:00:00',
        ]);

        $potatoItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->potato->id,
            'received_qty' => 200.0,
            'received_unit' => 'KG',
        ]);

        AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $grn->id,
            'advance_goods_received_item_id' => $potatoItem->id,
            'bill_goods_received_id' => $billGrn->id,
            'product_id' => $this->potato->id,
            'matched_qty' => 200.0,
            'matched_unit' => 'KG',
            'base_qty' => 200.0,
            'conversion_to_base' => 1.0,
            'confirmed_by' => $this->admin->id,
            'confirmed_at' => now(),
        ]);

        // Attempting to change unit on already matched item must return 422
        $response = $this->actingAs($this->admin, 'sanctum')->patchJson(
            "/api/v1/purchasing/grn-items/{$potatoItem->id}/unit",
            ['unit' => 'PIECE']
        );

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertStringContainsString('already has confirmed matches', $response->json('message'));
    }

    public function test_complete_match_payload_validation_passes_with_contract(): void
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'created_by' => $this->admin->id,
            'order_date' => '2026-09-13',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->cauliflower->id,
            'quantity' => 10.0,
            'purchase_unit' => 'KG',
        ]);

        $advGrn = GoodsReceived::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'warehouse_advance',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'received_by' => $this->admin->id,
            'received_at' => '2026-09-13 08:00:00',
        ]);

        $advGrnItem = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $advGrn->id,
            'product_id' => $this->cauliflower->id,
            'received_qty' => 10.0,
            'received_unit' => 'KG',
        ]);

        StockBatch::factory()->create([
            'product_id' => $this->cauliflower->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $advGrn->id,
            'goods_received_item_id' => $advGrnItem->id,
            'warehouse_receive_pending' => false,
            'total_kg' => 10.0,
        ]);

        $payload = [
            'purchase_order_id' => $po->id,
            'client_submission_id' => (string) Str::uuid(),
            'receipt_type' => 'normal_purchase',
            'warehouse_id' => $this->warehouse->id,
            'received_at' => '2026-09-13 10:00:00',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->cauliflower->id,
                    'received_qty' => 10.0,
                    'received_unit' => 'KG',
                ],
            ],
            'advance_matches' => [
                [
                    'advance_goods_received_id' => $advGrn->id,
                    'advance_goods_received_item_id' => $advGrnItem->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->cauliflower->id,
                    'matched_qty' => 10.0,
                    'unit' => 'KG',
                ],
            ],
        ];

        $response = $this->actingAs($this->admin, 'sanctum')->postJson(
            '/api/v1/purchasing/grns',
            $payload
        );

        $response->assertCreated();
        $response->assertJsonPath('success', true);
    }
}
