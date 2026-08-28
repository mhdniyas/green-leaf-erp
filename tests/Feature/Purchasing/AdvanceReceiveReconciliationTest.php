<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Inventory\BatchStatus;
use App\Models\AdvanceReceiveMatch;
use App\Models\BillReconciliation;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Inventory\StockLedgerService;
use App\Services\Purchasing\AdvanceReceiveReconciliationService;
use App\Services\Purchasing\WarehouseReceiptStateResolver;
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

    private function confirmPhysicalReceipt(GoodsReceived $grn): void
    {
        StockBatch::where('goods_received_id', $grn->id)->update([
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->receiver->id,
        ]);
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

    public function test_pending_bill_with_zero_advance_appears_in_match_with_zero_coverage(): void
    {
        $order = PurchaseOrder::factory()->create(['status' => 'approved']);
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_id' => $this->tomato->id, 'quantity' => 100.0]);

        $response = $this->getJson('/api/v1/purchasing/grns/advance-match-candidates');
        $response->assertOk()
            ->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.overall_coverage_percentage', 0)
            ->assertJsonPath('data.0.has_advance_match', false)
            ->assertJsonPath('data.0.reconciliation_status', 'unmatched')
            ->assertJsonPath('data.0.exact_matches_count', 0)
            ->assertJsonPath('data.0.partial_matches_count', 0)
            ->assertJsonPath('data.0.unmatched_count', 1)
            ->assertJsonPath('data.0.match_summary_items.0.new_receive_qty', 100);
    }

    public function test_partial_advance_match_bill_coverage_calculation(): void
    {
        [$advance, $item] = $this->createConfirmedAdvance($this->tomato, 93.0);
        $order = PurchaseOrder::factory()->create(['status' => 'approved']);
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_id' => $this->tomato->id, 'quantity' => 100.0]);

        $response = $this->getJson('/api/v1/purchasing/grns/advance-match-candidates');
        $response->assertOk()
            ->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.overall_coverage_percentage', 93)
            ->assertJsonPath('data.0.has_advance_match', true)
            ->assertJsonPath('data.0.reconciliation_status', 'partial')
            ->assertJsonPath('data.0.exact_matches_count', 0)
            ->assertJsonPath('data.0.partial_matches_count', 1)
            ->assertJsonPath('data.0.unmatched_count', 0)
            ->assertJsonPath('data.0.match_summary_items.0.total_proposed_match_qty', 93)
            ->assertJsonPath('data.0.match_summary_items.0.new_receive_qty', 7);
    }

    public function test_full_advance_match_bill_coverage_calculation_and_remains_unconfirmed_in_match(): void
    {
        [$advance, $item] = $this->createConfirmedAdvance($this->tomato, 100.0);
        $order = PurchaseOrder::factory()->create(['status' => 'approved']);
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_id' => $this->tomato->id, 'quantity' => 100.0]);

        $response = $this->getJson('/api/v1/purchasing/grns/advance-match-candidates');
        $response->assertOk()
            ->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.overall_coverage_percentage', 100)
            ->assertJsonPath('data.0.has_advance_match', true)
            ->assertJsonPath('data.0.reconciliation_status', 'ready')
            ->assertJsonPath('data.0.exact_matches_count', 1)
            ->assertJsonPath('data.0.partial_matches_count', 0)
            ->assertJsonPath('data.0.unmatched_count', 0)
            ->assertJsonPath('data.0.match_summary_items.0.total_proposed_match_qty', 100)
            ->assertJsonPath('data.0.match_summary_items.0.new_receive_qty', 0);
    }

    public function test_multiple_products_exact_partial_unmatched_summary(): void
    {
        [$advTomato] = $this->createConfirmedAdvance($this->tomato, 100.0);
        [$advOnion] = $this->createConfirmedAdvance($this->onion, 40.0);

        $order = PurchaseOrder::factory()->create(['status' => 'approved']);
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_id' => $this->tomato->id, 'quantity' => 100.0]); // Exact
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_id' => $this->onion->id, 'quantity' => 80.0]);   // Partial (40/80 = 50%)
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_id' => $this->potato->id, 'quantity' => 50.0]);  // Unmatched (0%)

        $response = $this->getJson('/api/v1/purchasing/grns/advance-match-candidates');
        $response->assertOk()
            ->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.exact_matches_count', 1)
            ->assertJsonPath('data.0.partial_matches_count', 1)
            ->assertJsonPath('data.0.unmatched_count', 1)
            ->assertJsonPath('data.0.reconciliation_status', 'partial');
    }

    public function test_candidates_endpoint_respects_warehouse_and_date_filtering(): void
    {
        $shop = Shop::factory()->create();

        $orderToday = PurchaseOrder::factory()->create([
            'status' => 'approved',
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-28',
            'destination_shop_id' => $shop->id,
        ]);
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $orderToday->id, 'product_id' => $this->tomato->id, 'quantity' => 50.0]);

        $orderYesterday = PurchaseOrder::factory()->create([
            'status' => 'approved',
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-27',
            'destination_shop_id' => $shop->id,
        ]);
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $orderYesterday->id, 'product_id' => $this->tomato->id, 'quantity' => 30.0]);

        // Filter by date
        $this->getJson('/api/v1/purchasing/grns/advance-match-candidates?date=2026-08-28')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $orderToday->id);

        $this->getJson('/api/v1/purchasing/grns/advance-match-candidates?date=2026-08-27')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $orderYesterday->id);
    }

    public function test_candidates_and_suggestions_have_zero_stock_mutations(): void
    {
        [$advance] = $this->createConfirmedAdvance($this->tomato, 100.0);
        $order = PurchaseOrder::factory()->create([
            'status' => 'approved',
            'supplier_id' => $this->supplier->id,
        ]);
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_id' => $this->tomato->id, 'quantity' => 100.0]);

        $batchCountBefore = StockBatch::count();
        $movementCountBefore = StockMovement::count();
        $matchesCountBefore = AdvanceReceiveMatch::count();

        $this->getJson('/api/v1/purchasing/grns/advance-match-candidates')->assertOk();
        $this->getJson('/api/v1/purchasing/grns/advance-match-suggestions?purchase_order_id='.$order->id)->assertOk();

        $this->assertSame($batchCountBefore, StockBatch::count());
        $this->assertSame($movementCountBefore, StockMovement::count());
        $this->assertSame($matchesCountBefore, AdvanceReceiveMatch::count());
    }

    public function test_loadout_cohort_context_calculation_is_read_only_and_included_in_response(): void
    {
        $shop = Shop::factory()->create();
        $date = '2026-08-28';
        $order = PurchaseOrder::factory()->create([
            'status' => 'approved',
            'supplier_id' => $this->supplier->id,
            'order_date' => $date,
            'destination_shop_id' => $shop->id,
        ]);
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_id' => $this->tomato->id, 'quantity' => 100.0]);

        // Create a loadout item for tomato on this date
        $shopOrder = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $date,
            'delivery_status' => 'ready_for_dispatch',
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $shopOrder->id,
            'product_id' => $this->tomato->id,
            'requested_qty' => 75.0,
            'approved_qty' => 75.0,
            'loaded_qty' => 75.0,
            'actual_weight' => 75.0,
            'sorting_status' => 'loaded',
            'unit' => 'KG',
        ]);

        $response = $this->getJson('/api/v1/purchasing/grns/advance-match-candidates?date='.$date);
        $response->assertOk()
            ->assertJsonPath('data.0.match_summary_items.0.relevant_loadout_qty', 75);
    }

    public function test_phase2_zero_percent_confirmation_creates_normal_reconciliation_and_full_stock(): void
    {
        $shop = Shop::factory()->create();
        $order = PurchaseOrder::factory()->create([
            'status' => 'approved',
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-28',
            'destination_shop_id' => $shop->id,
        ]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'quantity' => 100.0,
            'unit_price' => 10.0,
        ]);

        $batchCountBefore = StockBatch::count();

        $response = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => '2026-08-28',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'KG',
                ],
            ],
            'advance_matches' => [],
        ]);

        $response->assertCreated();
        $grnId = $response->json('data.id');

        // Verify BillReconciliation
        $recon = BillReconciliation::where('goods_received_id', $grnId)->first();
        $this->assertNotNull($recon);
        $this->assertSame('normal', $recon->source_type);
        $this->assertSame('confirmed', $recon->status);
        $this->assertEquals(100.0, (float) $recon->total_bill_base_qty);
        $this->assertEquals(0.0, (float) $recon->total_matched_base_qty);
        $this->assertEquals(100.0, (float) $recon->total_new_receive_base_qty);
        $this->assertSame($this->receiver->id, $recon->confirmed_by);

        // Verify line
        $line = $recon->lines->first();
        $this->assertNotNull($line);
        $this->assertSame($this->tomato->id, $line->product_id);
        $this->assertEquals(100.0, (float) $line->bill_base_qty);
        $this->assertEquals(0.0, (float) $line->advance_matched_base_qty);
        $this->assertEquals(100.0, (float) $line->new_receive_base_qty);
        $this->assertSame('unmatched', $line->difference_status);

        // Verify StockBatch created (+100)
        $this->assertSame($batchCountBefore + 1, StockBatch::count());
        $newBatch = StockBatch::where('goods_received_id', $grnId)->first();
        $this->assertNotNull($newBatch);
        $this->assertEquals(100.0, (float) $newBatch->total_kg);
    }

    public function test_phase2_partial_confirmation_creates_mixed_reconciliation_and_stock_for_remainder_only(): void
    {
        [$advanceGrn, $advanceItem] = $this->createConfirmedAdvance($this->tomato, 93.0);

        $shop = Shop::factory()->create();
        $order = PurchaseOrder::factory()->create([
            'status' => 'approved',
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-28',
            'destination_shop_id' => $shop->id,
        ]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'quantity' => 100.0,
            'unit_price' => 10.0,
        ]);

        $batchCountBefore = StockBatch::count();

        $response = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => '2026-08-28',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'KG',
                ],
            ],
            'advance_matches' => [
                [
                    'advance_goods_received_id' => $advanceGrn->id,
                    'advance_goods_received_item_id' => $advanceItem->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'matched_qty' => 93.0,
                    'unit' => 'KG',
                ],
            ],
        ]);

        $response->assertCreated();
        $grnId = $response->json('data.id');

        $recon = BillReconciliation::where('goods_received_id', $grnId)->first();
        $this->assertNotNull($recon);
        $this->assertSame('mixed', $recon->source_type);
        $this->assertEquals(100.0, (float) $recon->total_bill_base_qty);
        $this->assertEquals(93.0, (float) $recon->total_matched_base_qty);
        $this->assertEquals(7.0, (float) $recon->total_new_receive_base_qty);

        $line = $recon->lines->first();
        $this->assertNotNull($line);
        $this->assertSame('partial', $line->difference_status);
        $this->assertEquals(93.0, (float) $line->advance_matched_base_qty);
        $this->assertEquals(7.0, (float) $line->new_receive_base_qty);

        // AdvanceReceiveMatch linked
        $match = AdvanceReceiveMatch::where('bill_goods_received_id', $grnId)->first();
        $this->assertNotNull($match);
        $this->assertSame($recon->id, $match->bill_reconciliation_id);
        $this->assertSame($line->id, $match->bill_reconciliation_line_id);

        // Only remainder (+7 KG) created into new StockBatch
        $this->assertSame($batchCountBefore + 1, StockBatch::count());
        $newBatch = StockBatch::where('goods_received_id', $grnId)->first();
        $this->assertNotNull($newBatch);
        $this->assertEquals(7.0, (float) $newBatch->total_kg);
    }

    public function test_phase2_hundred_percent_confirmation_creates_advance_reconciliation_and_zero_new_stock(): void
    {
        [$advanceGrn, $advanceItem] = $this->createConfirmedAdvance($this->tomato, 100.0);

        $shop = Shop::factory()->create();
        $order = PurchaseOrder::factory()->create([
            'status' => 'approved',
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-28',
            'destination_shop_id' => $shop->id,
        ]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'quantity' => 100.0,
            'unit_price' => 10.0,
        ]);

        $batchCountBefore = StockBatch::count();

        $response = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => '2026-08-28',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'KG',
                ],
            ],
            'advance_matches' => [
                [
                    'advance_goods_received_id' => $advanceGrn->id,
                    'advance_goods_received_item_id' => $advanceItem->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'matched_qty' => 100.0,
                    'unit' => 'KG',
                ],
            ],
        ]);

        $response->assertCreated();
        $grnId = $response->json('data.id');

        $recon = BillReconciliation::where('goods_received_id', $grnId)->first();
        $this->assertNotNull($recon);
        $this->assertSame('advance', $recon->source_type);
        $this->assertEquals(100.0, (float) $recon->total_bill_base_qty);
        $this->assertEquals(100.0, (float) $recon->total_matched_base_qty);
        $this->assertEquals(0.0, (float) $recon->total_new_receive_base_qty);

        $line = $recon->lines->first();
        $this->assertSame('matched', $line->difference_status);

        // ZERO new stock batches created
        $this->assertSame($batchCountBefore, StockBatch::count());
    }

    public function test_phase2_history_api_returns_detailed_reconciliation_and_source_details(): void
    {
        [$advanceGrn, $advanceItem] = $this->createConfirmedAdvance($this->tomato, 60.0);

        $shop = Shop::factory()->create();
        $order = PurchaseOrder::factory()->create([
            'status' => 'approved',
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-28',
            'destination_shop_id' => $shop->id,
        ]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'quantity' => 100.0,
            'unit_price' => 10.0,
        ]);

        $res = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => '2026-08-28',
            'items' => [
                ['purchase_order_item_id' => $poItem->id, 'product_id' => $this->tomato->id, 'received_qty' => 100.0, 'received_unit' => 'KG'],
            ],
            'advance_matches' => [
                ['advance_goods_received_id' => $advanceGrn->id, 'advance_goods_received_item_id' => $advanceItem->id, 'purchase_order_item_id' => $poItem->id, 'product_id' => $this->tomato->id, 'matched_qty' => 60.0, 'unit' => 'KG'],
            ],
        ]);
        $grnId = $res->json('data.id');

        $showRes = $this->getJson("/api/v1/purchasing/grns/{$grnId}");
        $showRes->assertOk()
            ->assertJsonPath('data.source', 'MIXED')
            ->assertJsonPath('data.bill_reconciliation.source_type', 'mixed')
            ->assertJsonPath('data.bill_reconciliation.total_bill_base_qty', 100)
            ->assertJsonPath('data.bill_reconciliation.total_matched_base_qty', 60)
            ->assertJsonPath('data.bill_reconciliation.total_new_receive_base_qty', 40)
            ->assertJsonPath('data.bill_reconciliation.lines.0.product_id', $this->tomato->id)
            ->assertJsonPath('data.bill_reconciliation.lines.0.advance_matched_qty', 60)
            ->assertJsonPath('data.bill_reconciliation.lines.0.new_receive_qty', 40);
    }

    public function test_phase2b_scenario10_loadout_records_are_strictly_immutable_during_reconciliation(): void
    {
        $this->tomato->update(['default_warehouse_id' => $this->warehouse->id]);
        $shop = Shop::factory()->create();
        $date = '2026-08-28';

        // Create canonical loadout records
        $shopOrder = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $date,
            'state' => 'submitted',
        ]);
        $orderItem = ShopOrderItem::create([
            'shop_order_id' => $shopOrder->id,
            'product_id' => $this->tomato->id,
            'unit' => 'KG',
            'requested_qty' => 120.0,
            'loaded_qty' => 120.0,
            'actual_weight' => 120.0,
            'sorting_status' => 'loaded',
            'unit_price' => 10.0,
        ]);

        $movementCountBefore = StockMovement::count();

        // Execute bill reconciliation
        $order = PurchaseOrder::factory()->create([
            'status' => 'approved',
            'supplier_id' => $this->supplier->id,
            'order_date' => $date,
            'destination_shop_id' => $shop->id,
        ]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'quantity' => 100.0,
            'unit_price' => 10.0,
        ]);

        $res = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => $date,
            'items' => [
                ['purchase_order_item_id' => $poItem->id, 'product_id' => $this->tomato->id, 'received_qty' => 100.0, 'received_unit' => 'KG'],
            ],
            'advance_matches' => [],
        ])->assertCreated();

        $grnId = $res->json('data.id');
        $recon = BillReconciliation::where('goods_received_id', $grnId)->first();
        $line = $recon->lines->first();

        // Context snapshot preserved
        $this->assertEquals(120.0, (float) $line->relevant_loadout_qty);
        $this->assertEquals(20.0, (float) $line->unbilled_loadout_qty);

        // Loadout entities NOT mutated
        $this->assertSame('loaded', $orderItem->fresh()->sorting_status);
        $this->assertEquals(120.0, (float) $orderItem->fresh()->loaded_qty);
        $this->assertEquals(120.0, (float) $orderItem->fresh()->actual_weight);
        $this->assertSame('submitted', $shopOrder->fresh()->state);
        $this->assertSame($movementCountBefore, StockMovement::count());
    }

    public function test_phase2b_scenario11_unit_safety_and_conversion_drift_prevention(): void
    {
        // 1. Box with 10 KG conversion
        $this->tomato->update(['unit' => 'KG']);

        [$advanceGrn, $advanceItem] = $this->createConfirmedAdvance($this->tomato, 50.0);

        $shop = Shop::factory()->create();
        $order = PurchaseOrder::factory()->create([
            'status' => 'approved',
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-28',
            'destination_shop_id' => $shop->id,
        ]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'quantity' => 100.0,
            'unit_price' => 10.0,
        ]);

        $res = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => '2026-08-28',
            'items' => [
                ['purchase_order_item_id' => $poItem->id, 'product_id' => $this->tomato->id, 'received_qty' => 100.0, 'received_unit' => 'KG'],
            ],
            'advance_matches' => [
                ['advance_goods_received_id' => $advanceGrn->id, 'advance_goods_received_item_id' => $advanceItem->id, 'purchase_order_item_id' => $poItem->id, 'product_id' => $this->tomato->id, 'matched_qty' => 50.0, 'unit' => 'KG'],
            ],
        ])->assertCreated();

        $recon = BillReconciliation::where('goods_received_id', $res->json('data.id'))->first();
        $line = $recon->lines->first();

        $this->assertEquals(100.0, (float) $line->bill_base_qty);
        $this->assertEquals(50.0, (float) $line->advance_matched_base_qty);
        $this->assertEquals(50.0, (float) $line->new_receive_base_qty);
    }

    public function test_phase2b_scenario12_receipt_state_transitions_to_received_and_leaves_match_candidates(): void
    {
        $shop = Shop::factory()->create();
        $order = PurchaseOrder::factory()->create([
            'status' => 'approved',
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-28',
            'destination_shop_id' => $shop->id,
        ]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'quantity' => 100.0,
            'unit_price' => 10.0,
        ]);

        // Before receipt: Resolver returns Pending
        $resolver = app(WarehouseReceiptStateResolver::class);
        $orderState = $resolver->forOrder($order);
        $this->assertSame('pending', $orderState['receipt_status']);

        // Candidates list includes it
        $candResBefore = $this->getJson("/api/v1/purchasing/grns/advance-match-candidates?warehouse_id={$this->warehouse->id}&date=2026-08-28");
        $candResBefore->assertOk();
        $this->assertTrue(collect($candResBefore->json('data'))->contains('purchase_order_id', $order->id));

        // Reconcile and confirm
        $res = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => '2026-08-28',
            'items' => [
                ['purchase_order_item_id' => $poItem->id, 'product_id' => $this->tomato->id, 'received_qty' => 100.0, 'received_unit' => 'KG'],
            ],
            'advance_matches' => [],
        ])->assertCreated();

        $grn = GoodsReceived::find($res->json('data.id'));

        // After warehouse receipt confirmation:
        $this->confirmPhysicalReceipt($grn);

        $orderFreshState = $resolver->forOrder($order->fresh(['goodsReceiveds']));
        $this->assertSame('received', $orderFreshState['receipt_status']);

        $grnFreshState = $resolver->forReceipt($grn->fresh());
        $this->assertSame('received', $grnFreshState['receipt_status']);

        // Leaves active Match candidates list
        $candResAfter = $this->getJson("/api/v1/purchasing/grns/advance-match-candidates?warehouse_id={$this->warehouse->id}&date=2026-08-28");
        $candResAfter->assertOk();
        $this->assertFalse(collect($candResAfter->json('data'))->contains('purchase_order_id', $order->id));
    }

    public function test_phase2b_scenario13_advance_history_tracks_consumption_across_multiple_bills(): void
    {
        [$advanceGrn, $advanceItem] = $this->createConfirmedAdvance($this->tomato, 100.0);

        $shop = Shop::factory()->create();

        // Bill 1 consumes 60
        $order1 = PurchaseOrder::factory()->create(['status' => 'approved', 'supplier_id' => $this->supplier->id, 'order_date' => '2026-08-28', 'destination_shop_id' => $shop->id]);
        $poItem1 = PurchaseOrderItem::factory()->create(['purchase_order_id' => $order1->id, 'product_id' => $this->tomato->id, 'quantity' => 60.0]);

        $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $order1->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => '2026-08-28',
            'items' => [['purchase_order_item_id' => $poItem1->id, 'product_id' => $this->tomato->id, 'received_qty' => 60.0, 'received_unit' => 'KG']],
            'advance_matches' => [
                ['advance_goods_received_id' => $advanceGrn->id, 'advance_goods_received_item_id' => $advanceItem->id, 'purchase_order_item_id' => $poItem1->id, 'product_id' => $this->tomato->id, 'matched_qty' => 60.0, 'unit' => 'KG'],
            ],
        ])->assertCreated();

        // Remaining 40 available
        $cand1 = $this->reconcileService->getOpenAdvanceCandidatesForProduct($this->tomato->id, $this->warehouse->id);
        $this->assertCount(1, $cand1);
        $this->assertEquals(40.0, $cand1[0]['available_qty']);

        // Bill 2 consumes 40
        $order2 = PurchaseOrder::factory()->create(['status' => 'approved', 'supplier_id' => $this->supplier->id, 'order_date' => '2026-08-28', 'destination_shop_id' => $shop->id]);
        $poItem2 = PurchaseOrderItem::factory()->create(['purchase_order_id' => $order2->id, 'product_id' => $this->tomato->id, 'quantity' => 40.0]);

        $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $order2->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => '2026-08-28',
            'items' => [['purchase_order_item_id' => $poItem2->id, 'product_id' => $this->tomato->id, 'received_qty' => 40.0, 'received_unit' => 'KG']],
            'advance_matches' => [
                ['advance_goods_received_id' => $advanceGrn->id, 'advance_goods_received_item_id' => $advanceItem->id, 'purchase_order_item_id' => $poItem2->id, 'product_id' => $this->tomato->id, 'matched_qty' => 40.0, 'unit' => 'KG'],
            ],
        ])->assertCreated();

        // Remaining = 0 (Cleared)
        $cand2 = $this->reconcileService->getOpenAdvanceCandidatesForProduct($this->tomato->id, $this->warehouse->id);
        $this->assertCount(0, $cand2);

        // Advance history links both bills
        $matches = AdvanceReceiveMatch::where('advance_goods_received_id', $advanceGrn->id)->get();
        $this->assertCount(2, $matches);
        $this->assertEquals(60.0, (float) $matches[0]->base_qty);
        $this->assertEquals(40.0, (float) $matches[1]->base_qty);
    }

    public function test_phase2b_scenario14_legacy_matches_with_null_reconciliation_ids_handle_safely(): void
    {
        [$advanceGrn, $advanceItem] = $this->createConfirmedAdvance($this->tomato, 50.0);
        $billGrn = GoodsReceived::factory()->create(['status' => 'approved', 'warehouse_id' => $this->warehouse->id]);
        $billItem = GoodsReceivedItem::factory()->create(['goods_received_id' => $billGrn->id, 'product_id' => $this->tomato->id, 'received_qty' => 50.0]);

        // Legacy match row without bill_reconciliation_id
        $legacyMatch = AdvanceReceiveMatch::create([
            'advance_goods_received_id' => $advanceGrn->id,
            'advance_goods_received_item_id' => $advanceItem->id,
            'bill_goods_received_id' => $billGrn->id,
            'bill_goods_received_item_id' => $billItem->id,
            'product_id' => $this->tomato->id,
            'matched_qty' => 50.0,
            'unit' => 'KG',
            'base_qty' => 50.0,
            'confirmed_by' => $this->receiver->id,
            'confirmed_at' => now(),
            'bill_reconciliation_id' => null,
            'bill_reconciliation_line_id' => null,
        ]);

        $this->assertNotNull($legacyMatch);
        $this->assertNull($legacyMatch->billReconciliation);

        // Show endpoint doesn't fail on null bill reconciliation
        $res = $this->getJson("/api/v1/purchasing/grns/{$billGrn->id}");
        $res->assertOk();
    }

    public function test_phase2b_controlled_realistic_end_to_end_flow(): void
    {
        // Initial Stock before Advance = 0
        $initialStock = $this->movementRepo->currentStockForProduct($this->tomato->id, $this->warehouse->id);
        $this->assertSame(0.0, $initialStock);

        // 1. Advance Receive Tomato 93 KG
        [$advGrn, $advItem] = $this->createConfirmedAdvance($this->tomato, 93.0);

        // 2 & 3. Stock after Advance = +93 KG
        $stockAfterAdvance = $this->movementRepo->currentStockForProduct($this->tomato->id, $this->warehouse->id);
        $this->assertSame(93.0, $stockAfterAdvance);

        // 4. Purchaser Bill Tomato 100 KG
        $shop = Shop::factory()->create();
        $order = PurchaseOrder::factory()->create([
            'status' => 'approved',
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-28',
            'destination_shop_id' => $shop->id,
        ]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'quantity' => 100.0,
            'unit_price' => 10.0,
        ]);

        // 5 & 6. Verify Match suggestions show 93% coverage
        $suggestionsRes = $this->getJson("/api/v1/purchasing/grns/advance-match-suggestions?purchase_order_id={$order->id}");
        $suggestionsRes->assertOk()
            ->assertJsonPath('data.total_bill_base_qty', 100)
            ->assertJsonPath('data.total_matched_base_qty', 93)
            ->assertJsonPath('data.overall_coverage_percentage', 93);

        // 7 & 8. Confirm Reconciliation with client_submission_id
        $submissionId = 'ctrl-flow-sub-001';
        $payload = [
            'client_submission_id' => $submissionId,
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'received_at' => '2026-08-28',
            'items' => [
                ['purchase_order_item_id' => $poItem->id, 'product_id' => $this->tomato->id, 'received_qty' => 100.0, 'received_unit' => 'KG'],
            ],
            'advance_matches' => [
                ['advance_goods_received_id' => $advGrn->id, 'advance_goods_received_item_id' => $advItem->id, 'purchase_order_item_id' => $poItem->id, 'product_id' => $this->tomato->id, 'matched_qty' => 93.0, 'unit' => 'KG'],
            ],
        ];

        $reconcileRes = $this->postJson('/api/v1/purchasing/grns', $payload)->assertCreated();
        $billGrnId = $reconcileRes->json('data.id');
        $billGrn = GoodsReceived::find($billGrnId);

        // Confirm physical receipt for the new difference
        $this->confirmPhysicalReceipt($billGrn);

        // 9 & 10. Verify stock after reconciliation: +7 additional (Total physical = 100 KG)
        $stockAfterRecon = $this->movementRepo->currentStockForProduct($this->tomato->id, $this->warehouse->id);
        $this->assertSame(100.0, $stockAfterRecon);

        // 11. Advance remaining = 0
        $cand = $this->reconcileService->getOpenAdvanceCandidatesForProduct($this->tomato->id, $this->warehouse->id);
        $this->assertCount(0, $cand);

        // 12. Bill leaves Match
        $candRes = $this->getJson("/api/v1/purchasing/grns/advance-match-candidates?warehouse_id={$this->warehouse->id}&date=2026-08-28");
        $candRes->assertOk();
        $this->assertFalse(collect($candRes->json('data'))->contains('purchase_order_id', $order->id));

        // 13 & 14. History = MIXED with ADV 93 + NEW 7
        $showRes = $this->getJson("/api/v1/purchasing/grns/{$billGrnId}");
        $showRes->assertOk()
            ->assertJsonPath('data.source', 'MIXED')
            ->assertJsonPath('data.bill_reconciliation.source_type', 'mixed')
            ->assertJsonPath('data.bill_reconciliation.total_bill_base_qty', 100)
            ->assertJsonPath('data.bill_reconciliation.total_matched_base_qty', 93)
            ->assertJsonPath('data.bill_reconciliation.total_new_receive_base_qty', 7);

        // 15 & 16. Retry same submission -> 0 additional stock, 0 new rows
        $reconCountBefore = BillReconciliation::count();
        $batchCountBefore = StockBatch::count();
        $matchCountBefore = AdvanceReceiveMatch::count();

        $retryRes = $this->postJson('/api/v1/purchasing/grns', $payload);
        $retryRes->assertSuccessful();

        $this->assertSame($billGrnId, $retryRes->json('data.id'));
        $this->assertSame(100.0, $this->movementRepo->currentStockForProduct($this->tomato->id, $this->warehouse->id));
        $this->assertSame($reconCountBefore, BillReconciliation::count());
        $this->assertSame($batchCountBefore, StockBatch::count());
        $this->assertSame($matchCountBefore, AdvanceReceiveMatch::count());
    }
}
