<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Models\AdvanceReceiveMatch;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Inventory\StockLedgerService;
use App\Services\Purchasing\AdvanceReceiveReconciliationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvanceReceiveReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private Warehouse $warehouse;

    private Supplier $supplier;

    private Product $tomato;

    private Product $onion;

    private Product $potato;

    private StockMovementRepository $movementRepo;

    private StockLedgerService $ledgerService;

    private AdvanceReceiveReconciliationService $reconcileService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->warehouse = Warehouse::factory()->create();
        $this->supplier = Supplier::factory()->create();

        $this->tomato = Product::factory()->create([
            'name' => 'Tomato',
            'sku' => 'TOM-001',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'base_price' => 20.0,
        ]);

        $this->onion = Product::factory()->create([
            'name' => 'Onion',
            'sku' => 'ONI-001',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'base_price' => 30.0,
        ]);

        $this->potato = Product::factory()->create([
            'name' => 'Potato',
            'sku' => 'POT-001',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
            'base_price' => 25.0,
        ]);

        $this->receiver = User::factory()->create();
        $this->receiver->assignRole('warehouse_receiver');
        $this->receiver->warehouses()->attach($this->warehouse);
        $this->receiver->givePermissionTo([
            'inventory.stock.view',
            'inventory.product.view',
            'purchasing.order.view',
            'purchasing.grn.view',
            'purchasing.grn.create',
            'purchasing.grn.approve',
            'warehouse.receive.view',
            'warehouse.receive.confirm',
        ]);

        Sanctum::actingAs($this->receiver);
        $this->movementRepo = app(StockMovementRepository::class);
        $this->ledgerService = app(StockLedgerService::class);
        $this->reconcileService = app(AdvanceReceiveReconciliationService::class);
    }

    private function createConfirmedAdvance(Product $product, float $qty, ?string $receivedAt = null): array
    {
        $date = $receivedAt ?? now()->toDateString();
        $grn = GoodsReceived::factory()->create([
            'purchase_order_id' => null,
            'warehouse_id' => $this->warehouse->id,
            'bill_status' => 'bill_pending',
            'status' => 'approved',
            'received_at' => $date,
        ]);

        $item = GoodsReceivedItem::factory()->create([
            'goods_received_id' => $grn->id,
            'product_id' => $product->id,
            'received_qty' => $qty,
            'received_unit' => 'kg',
        ]);

        $batch = StockBatch::factory()->create([
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $item->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => $qty,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->receiver->id,
            'received_at' => $date,
        ]);

        return [$grn, $item, $batch];
    }

    public function test_exact_match_advance_100_bill_100_produces_zero_stock_delta_and_clears_advance(): void
    {
        [$advanceGrn, $advanceItem, $advanceBatch] = $this->createConfirmedAdvance($this->tomato, 100.0);

        // Initial available stock is 100kg from Advance
        $this->assertSame(100.0, $this->movementRepo->currentStockForProduct($this->tomato->id, $this->warehouse->id));

        $order = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'quantity' => 100.0,
        ]);

        $response = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                ],
            ],
            'advance_matches' => [
                [
                    'advance_goods_received_id' => $advanceGrn->id,
                    'advance_goods_received_item_id' => $advanceItem->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'matched_qty' => 100.0,
                    'unit' => 'kg',
                ],
            ],
        ])->assertCreated();

        // 1. Stock effect from match is 0 -> Total stock remains 100.0, NOT 200.0
        $this->assertSame(100.0, $this->movementRepo->currentStockForProduct($this->tomato->id, $this->warehouse->id));
        $this->assertSame(100.0, $this->ledgerService->availableStockForProduct($this->tomato->id, $this->warehouse->id));

        // 2. Exactly one StockBatch exists in database (the advance batch, no duplicate batch created)
        $this->assertSame(1, StockBatch::where('product_id', $this->tomato->id)->count());

        // 3. AdvanceReceiveMatch record exists
        $this->assertDatabaseHas('advance_receive_matches', [
            'advance_goods_received_id' => $advanceGrn->id,
            'purchase_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'matched_qty' => 100.0,
        ]);

        // 4. Advance GRN is cleared
        $this->assertSame('bill_available', $advanceGrn->fresh()->bill_status);
    }

    public function test_shortage_match_advance_93_bill_100_produces_7_new_stock(): void
    {
        [$advanceGrn, $advanceItem] = $this->createConfirmedAdvance($this->tomato, 93.0);
        $this->assertSame(93.0, $this->movementRepo->currentStockForProduct($this->tomato->id, $this->warehouse->id));

        $order = PurchaseOrder::factory()->create();
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'quantity' => 100.0,
        ]);

        $response = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                ],
            ],
            'advance_matches' => [
                [
                    'advance_goods_received_id' => $advanceGrn->id,
                    'advance_goods_received_item_id' => $advanceItem->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'matched_qty' => 93.0,
                    'unit' => 'kg',
                ],
            ],
        ])->assertCreated();

        $billGrnId = $response->json('data.id');
        $newBatch = StockBatch::where('goods_received_id', $billGrnId)->first();

        // Exactly one new batch for the +7kg difference was created
        $this->assertNotNull($newBatch);
        $this->assertEquals(7.0, (float) $newBatch->total_kg);
        $this->assertTrue((bool) $newBatch->warehouse_receive_pending);

        // Before warehouse confirms the 7kg, available stock is 93kg
        $this->assertSame(93.0, $this->movementRepo->currentStockForProduct($this->tomato->id, $this->warehouse->id));

        // Warehouse confirms new 7kg portion
        $newBatch->update([
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->receiver->id,
        ]);

        // Total available stock becomes 100kg (93 + 7)
        $this->assertSame(100.0, $this->movementRepo->currentStockForProduct($this->tomato->id, $this->warehouse->id));
        $this->assertSame('bill_available', $advanceGrn->fresh()->bill_status);
    }

    public function test_excess_advance_107_bill_100_leaves_7_remaining_without_subtracting_stock(): void
    {
        [$advanceGrn, $advanceItem] = $this->createConfirmedAdvance($this->tomato, 107.0);
        $this->assertSame(107.0, $this->movementRepo->currentStockForProduct($this->tomato->id, $this->warehouse->id));

        $order = PurchaseOrder::factory()->create();
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'quantity' => 100.0,
        ]);

        $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                ],
            ],
            'advance_matches' => [
                [
                    'advance_goods_received_id' => $advanceGrn->id,
                    'advance_goods_received_item_id' => $advanceItem->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'matched_qty' => 100.0,
                    'unit' => 'kg',
                ],
            ],
        ])->assertCreated();

        // 1. Stock remains 107.0 (no automatic deduction of the 7kg excess)
        $this->assertSame(107.0, $this->movementRepo->currentStockForProduct($this->tomato->id, $this->warehouse->id));

        // 2. Remaining advance available for future bills is 7kg
        $candidates = $this->reconcileService->getOpenAdvanceCandidatesForProduct($this->tomato->id, $this->warehouse->id);
        $this->assertCount(1, $candidates);
        $this->assertEquals(7.0, $candidates[0]['available_qty']);
        $this->assertEquals('partial', $candidates[0]['status']);
    }

    public function test_no_advance_match_creates_normal_stock(): void
    {
        $order = PurchaseOrder::factory()->create();
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'quantity' => 100.0,
        ]);

        $response = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                ],
            ],
        ])->assertCreated();

        $billGrnId = $response->json('data.id');
        $batch = StockBatch::where('goods_received_id', $billGrnId)->first();
        $this->assertNotNull($batch);
        $this->assertEquals(100.0, (float) $batch->total_kg);
    }

    public function test_mixed_bill_with_exact_partial_and_no_match_in_one_atomic_transaction(): void
    {
        // Tomato: Advance 100kg
        [$tomatoGrn, $tomatoItem] = $this->createConfirmedAdvance($this->tomato, 100.0);
        // Onion: Advance 40kg
        [$onionGrn, $onionItem] = $this->createConfirmedAdvance($this->onion, 40.0);
        // Potato: 0 Advance

        $order = PurchaseOrder::factory()->create();
        $poTomato = PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_id' => $this->tomato->id, 'quantity' => 100.0]);
        $poOnion = PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_id' => $this->onion->id, 'quantity' => 50.0]);
        $poPotato = PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_id' => $this->potato->id, 'quantity' => 20.0]);

        $response = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => now()->toDateString(),
            'items' => [
                ['purchase_order_item_id' => $poTomato->id, 'product_id' => $this->tomato->id, 'received_qty' => 100.0],
                ['purchase_order_item_id' => $poOnion->id, 'product_id' => $this->onion->id, 'received_qty' => 50.0],
                ['purchase_order_item_id' => $poPotato->id, 'product_id' => $this->potato->id, 'received_qty' => 20.0],
            ],
            'advance_matches' => [
                ['advance_goods_received_id' => $tomatoGrn->id, 'advance_goods_received_item_id' => $tomatoItem->id, 'purchase_order_item_id' => $poTomato->id, 'product_id' => $this->tomato->id, 'matched_qty' => 100.0],
                ['advance_goods_received_id' => $onionGrn->id, 'advance_goods_received_item_id' => $onionItem->id, 'purchase_order_item_id' => $poOnion->id, 'product_id' => $this->onion->id, 'matched_qty' => 40.0],
            ],
        ])->assertCreated();

        $billGrnId = $response->json('data.id');

        // Tomato: 0 new batch created
        $this->assertNull(StockBatch::where('goods_received_id', $billGrnId)->where('product_id', $this->tomato->id)->first());

        // Onion: +10kg batch created
        $onionBatch = StockBatch::where('goods_received_id', $billGrnId)->where('product_id', $this->onion->id)->first();
        $this->assertNotNull($onionBatch);
        $this->assertEquals(10.0, (float) $onionBatch->total_kg);

        // Potato: +20kg batch created
        $potatoBatch = StockBatch::where('goods_received_id', $billGrnId)->where('product_id', $this->potato->id)->first();
        $this->assertNotNull($potatoBatch);
        $this->assertEquals(20.0, (float) $potatoBatch->total_kg);
    }

    public function test_multiple_advances_60_plus_30_for_bill_100_fifo_order(): void
    {
        [$advanceA, $itemA] = $this->createConfirmedAdvance($this->tomato, 60.0, '2026-08-20');
        [$advanceB, $itemB] = $this->createConfirmedAdvance($this->tomato, 30.0, '2026-08-22');

        $order = PurchaseOrder::factory()->create();
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'quantity' => 100.0,
        ]);

        // Verify suggestion order is FIFO
        $suggestions = $this->getJson("/api/v1/purchasing/grns/advance-match-suggestions?purchase_order_id={$order->id}")
            ->assertOk()
            ->json('data');

        $this->assertEquals(90.0, $suggestions['items'][0]['total_proposed_match_qty']);
        $this->assertEquals(10.0, $suggestions['items'][0]['new_receive_qty']);
        $this->assertEquals(90.0, $suggestions['items'][0]['coverage_percentage']);
        $this->assertCount(2, $suggestions['items'][0]['suggested_matches']);

        // Submit match
        $response = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => now()->toDateString(),
            'items' => [['purchase_order_item_id' => $poItem->id, 'product_id' => $this->tomato->id, 'received_qty' => 100.0]],
            'advance_matches' => [
                ['advance_goods_received_id' => $advanceA->id, 'advance_goods_received_item_id' => $itemA->id, 'purchase_order_item_id' => $poItem->id, 'product_id' => $this->tomato->id, 'matched_qty' => 60.0],
                ['advance_goods_received_id' => $advanceB->id, 'advance_goods_received_item_id' => $itemB->id, 'purchase_order_item_id' => $poItem->id, 'product_id' => $this->tomato->id, 'matched_qty' => 30.0],
            ],
        ])->assertCreated();

        $billGrnId = $response->json('data.id');
        $newBatch = StockBatch::where('goods_received_id', $billGrnId)->first();
        $this->assertEquals(10.0, (float) $newBatch->total_kg);

        $this->assertSame('bill_available', $advanceA->fresh()->bill_status);
        $this->assertSame('bill_available', $advanceB->fresh()->bill_status);
    }

    public function test_partial_advance_consumed_across_multiple_bills(): void
    {
        [$advance, $item] = $this->createConfirmedAdvance($this->tomato, 100.0);

        // Bill 1: 40kg
        $order1 = PurchaseOrder::factory()->create();
        $poItem1 = PurchaseOrderItem::factory()->create(['purchase_order_id' => $order1->id, 'product_id' => $this->tomato->id, 'quantity' => 40.0]);

        $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $order1->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => now()->toDateString(),
            'items' => [['purchase_order_item_id' => $poItem1->id, 'product_id' => $this->tomato->id, 'received_qty' => 40.0]],
            'advance_matches' => [
                ['advance_goods_received_id' => $advance->id, 'advance_goods_received_item_id' => $item->id, 'purchase_order_item_id' => $poItem1->id, 'product_id' => $this->tomato->id, 'matched_qty' => 40.0],
            ],
        ])->assertCreated();

        // Check remaining available is 60kg
        $cand1 = $this->reconcileService->getOpenAdvanceCandidatesForProduct($this->tomato->id, $this->warehouse->id);
        $this->assertEquals(60.0, $cand1[0]['available_qty']);

        // Bill 2: 60kg
        $order2 = PurchaseOrder::factory()->create();
        $poItem2 = PurchaseOrderItem::factory()->create(['purchase_order_id' => $order2->id, 'product_id' => $this->tomato->id, 'quantity' => 60.0]);

        $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $order2->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => now()->toDateString(),
            'items' => [['purchase_order_item_id' => $poItem2->id, 'product_id' => $this->tomato->id, 'received_qty' => 60.0]],
            'advance_matches' => [
                ['advance_goods_received_id' => $advance->id, 'advance_goods_received_item_id' => $item->id, 'purchase_order_item_id' => $poItem2->id, 'product_id' => $this->tomato->id, 'matched_qty' => 60.0],
            ],
        ])->assertCreated();

        // Advance is now fully cleared
        $cand2 = $this->reconcileService->getOpenAdvanceCandidatesForProduct($this->tomato->id, $this->warehouse->id);
        $this->assertEmpty($cand2);
        $this->assertSame('bill_available', $advance->fresh()->bill_status);
    }

    public function test_suggestion_endpoint_is_read_only_and_changes_nothing(): void
    {
        [$advance, $item] = $this->createConfirmedAdvance($this->tomato, 100.0);
        $order = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_id' => $this->tomato->id, 'quantity' => 100.0]);

        $this->getJson("/api/v1/purchasing/grns/advance-match-suggestions?purchase_order_id={$order->id}")->assertOk();

        // Verify no matches were created and advance remains open
        $this->assertSame(0, AdvanceReceiveMatch::count());
        $this->assertSame('bill_pending', $advance->fresh()->bill_status);
    }

    public function test_idempotent_double_submission_and_network_retry_consumes_advance_once(): void
    {
        [$advance, $item] = $this->createConfirmedAdvance($this->tomato, 50.0);
        $order = PurchaseOrder::factory()->create();
        $poItem = PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_id' => $this->tomato->id, 'quantity' => 30.0]);

        $payload = [
            'client_submission_id' => 'sub-unique-idemp-001',
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => now()->toDateString(),
            'items' => [['purchase_order_item_id' => $poItem->id, 'product_id' => $this->tomato->id, 'received_qty' => 30.0]],
            'advance_matches' => [
                ['advance_goods_received_id' => $advance->id, 'advance_goods_received_item_id' => $item->id, 'purchase_order_item_id' => $poItem->id, 'product_id' => $this->tomato->id, 'matched_qty' => 30.0],
            ],
        ];

        $res1 = $this->postJson('/api/v1/purchasing/grns', $payload)->assertCreated();
        $res2 = $this->postJson('/api/v1/purchasing/grns', $payload)->assertCreated();

        $this->assertSame($res1->json('data.id'), $res2->json('data.id'));
        $this->assertSame(1, AdvanceReceiveMatch::count());

        $cand = $this->reconcileService->getOpenAdvanceCandidatesForProduct($this->tomato->id, $this->warehouse->id);
        $this->assertEquals(20.0, $cand[0]['available_qty']);
    }

    public function test_unconfirmed_advance_cannot_match(): void
    {
        // Advance created but warehouse_receive_pending = true
        $advanceGrn = GoodsReceived::factory()->create(['purchase_order_id' => null, 'status' => 'approved', 'bill_status' => 'bill_pending']);
        $item = GoodsReceivedItem::factory()->create(['goods_received_id' => $advanceGrn->id, 'product_id' => $this->tomato->id, 'received_qty' => 50.0]);
        StockBatch::factory()->create([
            'goods_received_id' => $advanceGrn->id,
            'goods_received_item_id' => $item->id,
            'product_id' => $this->tomato->id,
            'total_kg' => 50.0,
            'warehouse_receive_pending' => true,
        ]);

        $order = PurchaseOrder::factory()->create();
        $poItem = PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_id' => $this->tomato->id, 'quantity' => 50.0]);

        $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => now()->toDateString(),
            'items' => [['purchase_order_item_id' => $poItem->id, 'product_id' => $this->tomato->id, 'received_qty' => 50.0]],
            'advance_matches' => [
                ['advance_goods_received_id' => $advanceGrn->id, 'advance_goods_received_item_id' => $item->id, 'purchase_order_item_id' => $poItem->id, 'product_id' => $this->tomato->id, 'matched_qty' => 50.0],
            ],
        ])->assertStatus(422);
    }

    public function test_incompatible_product_cannot_match(): void
    {
        [$advance, $item] = $this->createConfirmedAdvance($this->tomato, 50.0);
        $order = PurchaseOrder::factory()->create();
        $poItem = PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_id' => $this->onion->id, 'quantity' => 50.0]);

        $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => now()->toDateString(),
            'items' => [['purchase_order_item_id' => $poItem->id, 'product_id' => $this->onion->id, 'received_qty' => 50.0]],
            'advance_matches' => [
                ['advance_goods_received_id' => $advance->id, 'advance_goods_received_item_id' => $item->id, 'purchase_order_item_id' => $poItem->id, 'product_id' => $this->onion->id, 'matched_qty' => 50.0],
            ],
        ])->assertStatus(422);
    }

    public function test_attempt_to_over_consume_advance_is_rejected(): void
    {
        [$advance, $item] = $this->createConfirmedAdvance($this->tomato, 50.0);
        $order = PurchaseOrder::factory()->create();
        $poItem = PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_id' => $this->tomato->id, 'quantity' => 100.0]);

        $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => now()->toDateString(),
            'items' => [['purchase_order_item_id' => $poItem->id, 'product_id' => $this->tomato->id, 'received_qty' => 100.0]],
            'advance_matches' => [
                ['advance_goods_received_id' => $advance->id, 'advance_goods_received_item_id' => $item->id, 'purchase_order_item_id' => $poItem->id, 'product_id' => $this->tomato->id, 'matched_qty' => 80.0], // Exceeds 50kg!
            ],
        ])->assertStatus(422);
    }
}
