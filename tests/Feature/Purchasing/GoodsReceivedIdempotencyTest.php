<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoodsReceivedIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private Supplier $supplier;

    private Shop $warehouse;

    private Product $productA;

    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Role::firstOrCreate(['name' => 'warehouse_receiver']);
        Permission::firstOrCreate(['name' => 'purchasing.grn.create']);
        Permission::firstOrCreate(['name' => 'warehouse.receive.view']);

        $this->receiver = User::factory()->create(['name' => 'Receiver Guy']);
        $this->receiver->assignRole('warehouse_receiver');
        $this->receiver->givePermissionTo(['purchasing.grn.create', 'warehouse.receive.view']);

        $this->supplier = Supplier::factory()->create(['name' => 'Farm Fresh Direct']);
        $this->warehouse = Shop::factory()->create(['name' => 'North Depot']);

        $this->productA = Product::factory()->create(['name' => 'Tomato Hybrid', 'unit' => 'KG']);
        $this->productB = Product::factory()->create(['name' => 'Onion Red', 'unit' => 'KG']);
    }

    public function test_normal_po_receive_retry_with_same_submission_id_is_idempotent(): void
    {
        Sanctum::actingAs($this->receiver);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->warehouse->id,
            'status' => POStatus::Approved,
            'order_date' => now()->toDateString(),
        ]);

        $poItem1 = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->productA->id,
            'quantity' => 100.00,
            'unit_price' => 20.00,
        ]);

        $submissionId = 'sub-uuid-normal-001';
        $payload = [
            'client_submission_id' => $submissionId,
            'purchase_order_id' => $po->id,
            'received_at' => now()->toDateString(),
            'bill_status' => 'bill_available',
            'bill_number' => 'BILL-100',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem1->id,
                    'product_id' => $this->productA->id,
                    'received_qty' => 100.00,
                ],
            ],
        ];

        // First submission
        $start1 = microtime(true);
        $res1 = $this->postJson('/api/v1/purchasing/grns', $payload);
        $time1 = microtime(true) - $start1;

        $res1->assertCreated()
            ->assertJsonPath('success', true);

        $grnId1 = $res1->json('data.id');
        $grnNumber1 = $res1->json('data.grn_number');

        $this->assertEquals(1, GoodsReceived::count());
        $this->assertEquals(1, GoodsReceivedItem::count());
        $this->assertEquals(1, StockBatch::where('goods_received_id', $grnId1)->count());
        $this->assertEquals(100.00, (float) StockBatch::where('goods_received_id', $grnId1)->sum('total_kg'));
        $journalCount1 = JournalEntry::count();
        $this->assertGreaterThan(0, $journalCount1);
        $this->assertEquals(POStatus::Closed->value, $po->fresh()->status->value);

        // Second submission (same exact client_submission_id retry)
        $start2 = microtime(true);
        $res2 = $this->postJson('/api/v1/purchasing/grns', $payload);
        $time2 = microtime(true) - $start2;

        $res2->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $grnId1)
            ->assertJsonPath('data.grn_number', $grnNumber1);

        // Crucial assertions: NO DUPLICATION of any entity
        $this->assertEquals(1, GoodsReceived::count(), 'GoodsReceived count must remain 1');
        $this->assertEquals(1, GoodsReceivedItem::count(), 'GoodsReceivedItem count must remain 1');
        $this->assertEquals(1, StockBatch::where('goods_received_id', $grnId1)->count(), 'StockBatch count must remain 1');
        $this->assertEquals(100.00, (float) StockBatch::where('goods_received_id', $grnId1)->sum('total_kg'), 'Stock quantity must remain exactly 100');
        $this->assertEquals($journalCount1, JournalEntry::count(), 'JournalEntry count must not duplicate');
        $this->assertEquals(POStatus::Closed->value, $po->fresh()->status->value);
    }

    public function test_advance_receive_bill_pending_retry_with_same_submission_id_is_idempotent(): void
    {
        Sanctum::actingAs($this->receiver);

        $submissionId = 'sub-uuid-advance-002';
        $payload = [
            'client_submission_id' => $submissionId,
            'purchase_order_id' => null,
            'destination_shop_id' => $this->warehouse->id,
            'received_at' => now()->toDateString(),
            'bill_status' => 'bill_pending',
            'notes' => 'Direct farm delivery without PO',
            'items' => [
                [
                    'product_id' => $this->productB->id,
                    'received_qty' => 45.50,
                ],
            ],
        ];

        // First submission
        $res1 = $this->postJson('/api/v1/purchasing/grns', $payload);
        $res1->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.bill_status', 'bill_pending')
            ->assertJsonPath('data.is_bill_pending', true);

        $grnId1 = $res1->json('data.id');

        $this->assertEquals(1, GoodsReceived::where('bill_status', 'bill_pending')->count());
        $this->assertEquals(1, StockBatch::where('goods_received_id', $grnId1)->count());
        $this->assertEquals(45.50, (float) StockBatch::where('goods_received_id', $grnId1)->first()->total_kg);

        // Retry same advance receipt
        $res2 = $this->postJson('/api/v1/purchasing/grns', $payload);
        $res2->assertSuccessful()
            ->assertJsonPath('data.id', $grnId1);

        // Verify no duplicate GRN and no duplicate stock
        $this->assertEquals(1, GoodsReceived::where('bill_status', 'bill_pending')->count());
        $this->assertEquals(1, StockBatch::where('goods_received_id', $grnId1)->count());
        $this->assertEquals(45.50, (float) StockBatch::where('goods_received_id', $grnId1)->sum('total_kg'));
    }

    public function test_same_key_with_different_quantity_is_rejected(): void
    {
        Sanctum::actingAs($this->receiver);

        $submissionId = 'sub-uuid-tamper-003';
        $originalPayload = [
            'client_submission_id' => $submissionId,
            'received_at' => now()->toDateString(),
            'bill_status' => 'bill_pending',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'received_qty' => 20.00,
                ],
            ],
        ];

        $res1 = $this->postJson('/api/v1/purchasing/grns', $originalPayload);
        $res1->assertCreated();

        // Second attempt with same ID but different quantity (25 instead of 20)
        $tamperedPayload = $originalPayload;
        $tamperedPayload['items'][0]['received_qty'] = 25.00;

        $res2 = $this->postJson('/api/v1/purchasing/grns', $tamperedPayload);
        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['client_submission_id']);

        // Assert DB was NOT modified
        $this->assertEquals(20.00, (float) GoodsReceivedItem::first()->received_qty);
    }

    public function test_same_key_with_different_product_is_rejected(): void
    {
        Sanctum::actingAs($this->receiver);

        $submissionId = 'sub-uuid-tamper-004';
        $originalPayload = [
            'client_submission_id' => $submissionId,
            'received_at' => now()->toDateString(),
            'bill_status' => 'bill_pending',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'received_qty' => 20.00,
                ],
            ],
        ];

        $res1 = $this->postJson('/api/v1/purchasing/grns', $originalPayload);
        $res1->assertCreated();

        // Second attempt with same ID but different product
        $tamperedPayload = $originalPayload;
        $tamperedPayload['items'][0]['product_id'] = $this->productB->id;

        $res2 = $this->postJson('/api/v1/purchasing/grns', $tamperedPayload);
        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['client_submission_id']);
    }

    public function test_same_key_with_different_po_is_rejected(): void
    {
        Sanctum::actingAs($this->receiver);

        $po1 = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::Approved,
        ]);
        $po2 = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::Approved,
        ]);

        $submissionId = 'sub-uuid-tamper-005';
        $originalPayload = [
            'client_submission_id' => $submissionId,
            'purchase_order_id' => $po1->id,
            'received_at' => now()->toDateString(),
            'bill_status' => 'bill_pending',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'received_qty' => 10.00,
                ],
            ],
        ];

        $res1 = $this->postJson('/api/v1/purchasing/grns', $originalPayload);
        $res1->assertCreated();

        // Second attempt with same ID but different PO
        $tamperedPayload = $originalPayload;
        $tamperedPayload['purchase_order_id'] = $po2->id;

        $res2 = $this->postJson('/api/v1/purchasing/grns', $tamperedPayload);
        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['client_submission_id']);
    }

    public function test_legacy_request_without_client_submission_id_continues_to_work(): void
    {
        Sanctum::actingAs($this->receiver);

        $payload = [
            'received_at' => now()->toDateString(),
            'bill_status' => 'bill_pending',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'received_qty' => 15.00,
                ],
            ],
        ];

        $res = $this->postJson('/api/v1/purchasing/grns', $payload);
        $res->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertEquals(1, GoodsReceived::count());
    }
}
