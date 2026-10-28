<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Actions\Purchasing\CancelPurchaseInvoiceAction;
use App\Actions\Purchasing\RecordGoodsReceiptAction;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Purchasing\POStatus;
use App\Models\AdvanceReceiveMatch;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseBusinessDay;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\AdvanceReceiveReconciliationService;
use App\Services\Purchasing\GoodsReceivedService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvanceReceiveAndBillMatchingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Supplier $supplier;

    private Product $tomato;

    private Product $potato;

    private Product $carrot;

    private PurchaseBusinessDay $businessDay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');
        Sanctum::actingAs($this->admin);

        $this->warehouse = Warehouse::factory()->create([
            'name' => 'Central Hub',
            'code' => 'WH-CH1',
            'is_active' => true,
        ]);

        $category = Category::factory()->create(['name' => 'Produce']);

        $this->tomato = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Tomato',
            'sku' => 'TOM-001',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $this->potato = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Potato',
            'sku' => 'POT-001',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $this->carrot = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Carrot',
            'sku' => 'CAR-001',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouse->id,
        ]);

        $this->supplier = Supplier::factory()->create(['name' => 'Direct Farm Co']);

        $this->businessDay = PurchaseBusinessDay::create([
            'business_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'status' => 'open',
            'opened_at' => now(),
            'opened_by' => $this->admin->id,
        ]);
    }

    /**
     * Test 1: Flutter advance receiving, repeated request, concurrent submission, and grade preservation.
     */
    public function test_scenario_1_flutter_advance_receiving_retries_concurrency_and_grade_preservation(): void
    {
        $submissionId = 'adv_test_'.uniqid();
        $payload = [
            'client_submission_id' => $submissionId,
            'receipt_type' => 'warehouse_advance',
            'bill_status' => 'bill_pending',
            'warehouse_id' => $this->warehouse->id,
            'received_at' => now()->toDateString(),
            'bill_number' => 'ADV-REF-101',
            'notes' => 'Direct Advance from Field',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'grade' => 'B',
                    'grading_mode' => 'fixed_purchase_grade',
                ],
                [
                    'product_id' => $this->potato->id,
                    'received_qty' => 80.0,
                    'received_unit' => 'kg',
                    'grade' => 'A',
                    'grading_mode' => 'fixed_purchase_grade',
                ],
                [
                    'product_id' => $this->carrot->id,
                    'received_qty' => 50.0,
                    'received_unit' => 'kg',
                    'grade' => 'U',
                    'grading_mode' => 'sort_required',
                ],
            ],
        ];

        // 1. Initial Flutter submission
        $response1 = $this->postJson('/api/v1/purchasing/grns', $payload);
        $response1->assertStatus(201);
        $grnId = $response1->json('data.id');

        $grn = GoodsReceived::with(['items', 'stockBatches.stockMovements'])->findOrFail($grnId);
        $this->assertSame('warehouse_advance', $grn->receipt_type);
        $this->assertSame('bill_pending', $grn->bill_status);
        $this->assertSame($submissionId, $grn->client_submission_id);
        $this->assertCount(3, $grn->items);

        // Grade preservation & ledger verification
        $tomatoBatch = $grn->stockBatches->firstWhere('product_id', $this->tomato->id);
        $this->assertNotNull($tomatoBatch);
        $this->assertSame('B', $tomatoBatch->purchase_grade);
        $this->assertSame('fixed_purchase_grade', $tomatoBatch->grading_mode);
        $this->assertSame(BatchStatus::Sorted, $tomatoBatch->status);
        $this->assertEquals(100.0, (float) $tomatoBatch->total_kg);
        $tomatoMovement = StockMovement::where('batch_id', $tomatoBatch->id)->where('type', StockMovementType::In->value)->first();
        $this->assertNotNull($tomatoMovement);
        $this->assertEquals(100.0, (float) $tomatoMovement->quantity);
        $this->assertSame('B', $tomatoMovement->grade->value);

        $potatoBatch = $grn->stockBatches->firstWhere('product_id', $this->potato->id);
        $this->assertNotNull($potatoBatch);
        $this->assertSame('A', $potatoBatch->purchase_grade);
        $this->assertSame('fixed_purchase_grade', $potatoBatch->grading_mode);
        $this->assertSame(BatchStatus::Sorted, $potatoBatch->status);
        $this->assertEquals(80.0, (float) $potatoBatch->total_kg);
        $potatoMovement = StockMovement::where('batch_id', $potatoBatch->id)->where('type', StockMovementType::In->value)->first();
        $this->assertNotNull($potatoMovement);
        $this->assertEquals(80.0, (float) $potatoMovement->quantity);
        $this->assertSame('A', $potatoMovement->grade->value);

        $carrotBatch = $grn->stockBatches->firstWhere('product_id', $this->carrot->id);
        $this->assertNotNull($carrotBatch);
        $this->assertSame('U', $carrotBatch->purchase_grade);
        $this->assertSame('sort_required', $carrotBatch->grading_mode);
        $this->assertSame(BatchStatus::Pending, $carrotBatch->status);
        $this->assertEquals(50.0, (float) $carrotBatch->total_kg);
        $carrotMovement = StockMovement::where('batch_id', $carrotBatch->id)->where('type', StockMovementType::In->value)->first();
        $this->assertNull($carrotMovement, 'Unsorted sort_required batch must not create sorted IN stock movement until segregation');

        // 2. Repeated request (Flutter retry with same client_submission_id)
        $response2 = $this->postJson('/api/v1/purchasing/grns', $payload);
        $response2->assertStatus(201);
        $this->assertSame($grnId, $response2->json('data.id'));
        $this->assertSame(1, GoodsReceived::where('client_submission_id', $submissionId)->count());
        $this->assertEquals(100.0, (float) StockMovement::where('batch_id', $tomatoBatch->id)->where('type', StockMovementType::In->value)->sum('quantity'));

        // 3. Concurrent submission race simulation: Action handles duplicate key atomically
        $goodsReceivedData = GoodsReceivedData::fromRequest(new Request($payload));
        $concurrentGrn = app(RecordGoodsReceiptAction::class)->execute($goodsReceivedData, $this->admin->id);
        $this->assertSame($grnId, $concurrentGrn->id);

        // 4. Separate legitimate receipt with identical items and quantities but different submission ID
        $distinctPayload = $payload;
        $distinctPayload['client_submission_id'] = 'adv_test_'.uniqid();
        $response3 = $this->postJson('/api/v1/purchasing/grns', $distinctPayload);
        $response3->assertStatus(201);
        $this->assertNotSame($grnId, $response3->json('data.id'));
        $this->assertSame(2, GoodsReceived::where('receipt_type', 'warehouse_advance')->count());
    }

    /**
     * Test 2: Advance quantity edit, added item, reduced quantity, and protection after stock consumption.
     */
    public function test_scenario_2_advance_edit_added_item_reduced_qty_and_consumption_protection(): void
    {
        $service = app(GoodsReceivedService::class);

        // Initial advance receipt with 100 kg Tomato (Grade B) and 50 kg Potato (Grade A)
        $grn = GoodsReceived::create([
            'warehouse_id' => $this->warehouse->id,
            'grn_number' => 'GRN-ADV-EDIT-01',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'received_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'approved_at' => now(),
            'notes' => 'Advance Before Edit',
        ]);

        $itemTomato = $grn->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 100.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'grade' => 'B',
        ]);
        $batchTomato = StockBatch::create([
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $itemTomato->id,
            'purchase_grade' => 'B',
            'grading_mode' => 'fixed_purchase_grade',
            'status' => BatchStatus::Sorted,
            'total_kg' => 100.0,
            'cost_per_kg' => 0.0,
            'created_by' => $this->admin->id,
            'reference' => 'BATCH-TOM-01',
            'received_at' => now()->toDateString(),
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
        ]);
        $inTomato = StockMovement::create([
            'batch_id' => $batchTomato->id,
            'type' => StockMovementType::In->value,
            'grade' => 'B',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100.0,
            'cost_per_unit' => 0.0,
            'created_by' => $this->admin->id,
        ]);

        $itemPotato = $grn->items()->create([
            'product_id' => $this->potato->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'grade' => 'A',
        ]);
        $batchPotato = StockBatch::create([
            'product_id' => $this->potato->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $grn->id,
            'goods_received_item_id' => $itemPotato->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'fixed_purchase_grade',
            'status' => BatchStatus::Sorted,
            'total_kg' => 50.0,
            'cost_per_kg' => 0.0,
            'created_by' => $this->admin->id,
            'reference' => 'BATCH-POT-01',
            'received_at' => now()->toDateString(),
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
        ]);
        $inPotato = StockMovement::create([
            'batch_id' => $batchPotato->id,
            'type' => StockMovementType::In->value,
            'grade' => 'A',
            'product_id' => $this->potato->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 50.0,
            'cost_per_unit' => 0.0,
            'created_by' => $this->admin->id,
        ]);

        // Action A, B, C:
        // 1. Increase Tomato from 100 kg -> 120 kg (advance edit)
        // 2. Reduce Potato from 50 kg -> 30 kg (reduced quantity)
        // 3. Add Carrot with 60 kg of Grade C (added item with dynamic grade)
        $editPayload = [
            'items' => [
                [
                    'id' => $itemTomato->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 120.0,
                    'received_unit' => 'kg',
                    'grade' => 'B',
                ],
                [
                    'id' => $itemPotato->id,
                    'product_id' => $this->potato->id,
                    'received_qty' => 30.0,
                    'received_unit' => 'kg',
                    'grade' => 'A',
                ],
                [
                    'product_id' => $this->carrot->id,
                    'received_qty' => 60.0,
                    'received_unit' => 'kg',
                    'grade' => 'C',
                    'grading_mode' => 'fixed_purchase_grade',
                ],
            ],
        ];

        $service->updateAdvanceReceive($grn, $editPayload, $this->admin->id);

        $batchTomato->refresh();
        $inTomato->refresh();
        $this->assertEquals(120.0, (float) $batchTomato->total_kg);
        $this->assertEquals(120.0, (float) $inTomato->quantity, 'StockMovement must synchronize with batch quantity on advance edit');

        $batchPotato->refresh();
        $inPotato->refresh();
        $this->assertEquals(30.0, (float) $batchPotato->total_kg);
        $this->assertEquals(30.0, (float) $inPotato->quantity, 'StockMovement must synchronize with batch reduction');

        $grn->load('items', 'stockBatches.stockMovements');
        $this->assertCount(3, $grn->items);
        $carrotItem = $grn->items->firstWhere('product_id', $this->carrot->id);
        $this->assertNotNull($carrotItem);
        $this->assertSame('C', $carrotItem->grade);
        $this->assertEquals(60.0, (float) $carrotItem->received_qty);

        $carrotBatch = $grn->stockBatches->firstWhere('product_id', $this->carrot->id);
        $this->assertNotNull($carrotBatch);
        $this->assertSame('C', $carrotBatch->purchase_grade);
        $this->assertSame('fixed_purchase_grade', $carrotBatch->grading_mode);
        $this->assertSame(BatchStatus::Sorted, $carrotBatch->status);
        $carrotMovement = StockMovement::where('batch_id', $carrotBatch->id)->where('type', StockMovementType::In->value)->first();
        $this->assertNotNull($carrotMovement);
        $this->assertEquals(60.0, (float) $carrotMovement->quantity);
        $this->assertSame('C', $carrotMovement->grade->value);

        // Action D: Downstream stock consumption and protection
        // Consume 70 kg of Tomato (e.g. loaded out to store)
        StockMovement::create([
            'batch_id' => $batchTomato->id,
            'type' => StockMovementType::Out->value,
            'grade' => 'B',
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 70.0,
            'cost_per_unit' => 0.0,
            'created_by' => $this->admin->id,
        ]);

        // Attempting to reduce Tomato below consumed quantity (70 kg) must fail
        $invalidReductionPayload = [
            'items' => [
                [
                    'id' => $itemTomato->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 60.0, // Invalid: 70 kg already consumed!
                    'received_unit' => 'kg',
                ],
                [
                    'id' => $itemPotato->id,
                    'product_id' => $this->potato->id,
                    'received_qty' => 30.0,
                    'received_unit' => 'kg',
                ],
                [
                    'id' => $carrotItem->id,
                    'product_id' => $this->carrot->id,
                    'received_qty' => 60.0,
                    'received_unit' => 'kg',
                ],
            ],
        ];

        try {
            $service->updateAdvanceReceive($grn, $invalidReductionPayload, $this->admin->id);
            $this->fail('Expected ValidationException when reducing quantity below consumed amount.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.received_qty', $e->errors());
        }

        // Attempting to remove consumed item must also fail
        $removalPayload = [
            'items' => [
                [
                    'id' => $itemPotato->id,
                    'product_id' => $this->potato->id,
                    'received_qty' => 30.0,
                    'received_unit' => 'kg',
                ],
                [
                    'id' => $carrotItem->id,
                    'product_id' => $this->carrot->id,
                    'received_qty' => 60.0,
                    'received_unit' => 'kg',
                ],
            ],
        ];

        try {
            $service->updateAdvanceReceive($grn, $removalPayload, $this->admin->id);
            $this->fail('Expected ValidationException when attempting to remove consumed item.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items', $e->errors());
        }

        // Verify batch and movement totals for Tomato were protected and unchanged
        $batchTomato->refresh();
        $inTomato->refresh();
        $this->assertEquals(120.0, (float) $batchTomato->total_kg);
        $this->assertEquals(120.0, (float) $inTomato->quantity);
    }

    /**
     * Test 3: Full/partial bill matching, unmatched remainder, repeated matching, and PO status.
     */
    public function test_scenario_3_full_partial_bill_matching_unmatched_remainder_and_po_status(): void
    {
        // Create PO with 100 kg Tomato and 80 kg Potato
        $po = PurchaseOrder::create([
            'po_number' => 'PO-MATCH-001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'business_day_id' => $this->businessDay->id,
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => now()->toDateString(),
            'status' => POStatus::Approved,
            'total_amount' => 5000.0,
            'created_by' => $this->admin->id,
        ]);

        $poItemTomato = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->tomato->id,
            'quantity' => 100.0,
            'purchase_unit' => 'kg',
            'unit_price' => 25.0,
            'line_total' => 2500.0,
        ]);

        $poItemPotato = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->potato->id,
            'quantity' => 80.0,
            'purchase_unit' => 'kg',
            'unit_price' => 31.25,
            'line_total' => 2500.0,
        ]);

        // Advance 1: 100 kg Tomato (Grade B)
        $adv1 = GoodsReceived::create([
            'warehouse_id' => $this->warehouse->id,
            'grn_number' => 'GRN-ADV-001',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'business_day_id' => $this->businessDay->id,
            'received_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'approved_at' => now(),
        ]);
        $advItem1 = $adv1->items()->create([
            'product_id' => $this->tomato->id,
            'received_qty' => 100.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'grade' => 'B',
        ]);
        $advBatch1 = StockBatch::create([
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $adv1->id,
            'goods_received_item_id' => $advItem1->id,
            'purchase_grade' => 'B',
            'grading_mode' => 'fixed_purchase_grade',
            'status' => BatchStatus::Sorted,
            'total_kg' => 100.0,
            'cost_per_kg' => 0.0,
            'created_by' => $this->admin->id,
            'reference' => 'BATCH-ADV-TOM',
            'received_at' => now()->toDateString(),
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
        ]);

        // Advance 2: 50 kg Potato (Grade A)
        $adv2 = GoodsReceived::create([
            'warehouse_id' => $this->warehouse->id,
            'grn_number' => 'GRN-ADV-002',
            'status' => 'approved',
            'bill_status' => 'bill_pending',
            'receipt_type' => 'warehouse_advance',
            'business_day_id' => $this->businessDay->id,
            'received_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'received_at' => now()->toDateString(),
            'approved_at' => now(),
        ]);
        $advItem2 = $adv2->items()->create([
            'product_id' => $this->potato->id,
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
            'grade' => 'A',
        ]);
        $advBatch2 = StockBatch::create([
            'product_id' => $this->potato->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $adv2->id,
            'goods_received_item_id' => $advItem2->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'fixed_purchase_grade',
            'status' => BatchStatus::Sorted,
            'total_kg' => 50.0,
            'cost_per_kg' => 0.0,
            'created_by' => $this->admin->id,
            'reference' => 'BATCH-ADV-POT',
            'received_at' => now()->toDateString(),
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $this->admin->id,
        ]);

        // Reconcile incoming bill for the PO:
        // Tomato: 100 kg billed, matched with Adv1 (100 kg -> full match)
        // Potato: 80 kg billed, matched with Adv2 (50 kg -> partial match, 30 kg remainder)
        $reconClientSub = 'recon_sub_'.uniqid();
        $billPayload = [
            'client_submission_id' => $reconClientSub,
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'normal_purchase',
            'bill_status' => 'bill_available',
            'received_at' => now()->toDateString(),
            'bill_number' => 'BILL-MATCH-001',
            'items' => [
                [
                    'purchase_order_item_id' => $poItemTomato->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'kg',
                    'grade' => 'B',
                ],
                [
                    'purchase_order_item_id' => $poItemPotato->id,
                    'product_id' => $this->potato->id,
                    'received_qty' => 80.0,
                    'received_unit' => 'kg',
                    'grade' => 'A',
                ],
            ],
            'advance_matches' => [
                [
                    'advance_goods_received_id' => $adv1->id,
                    'advance_goods_received_item_id' => $advItem1->id,
                    'purchase_order_item_id' => $poItemTomato->id,
                    'product_id' => $this->tomato->id,
                    'matched_qty' => 100.0,
                    'unit' => 'kg',
                ],
                [
                    'advance_goods_received_id' => $adv2->id,
                    'advance_goods_received_item_id' => $advItem2->id,
                    'purchase_order_item_id' => $poItemPotato->id,
                    'product_id' => $this->potato->id,
                    'matched_qty' => 50.0,
                    'unit' => 'kg',
                ],
            ],
        ];

        $reconService = app(AdvanceReceiveReconciliationService::class);
        $goodsReceivedData = GoodsReceivedData::fromRequest(new Request($billPayload));
        $billGrn = $reconService->reconcileAndExecute($goodsReceivedData, $this->admin->id);

        // Assertions for full and partial matches
        $this->assertSame('bill_available', $billGrn->bill_status);
        $this->assertCount(2, AdvanceReceiveMatch::where('bill_goods_received_id', $billGrn->id)->get());

        // Tomato: full match (100 kg), no remainder batch
        $tomatoBatchesOnBill = StockBatch::where('goods_received_id', $billGrn->id)->where('product_id', $this->tomato->id)->get();
        $this->assertCount(0, $tomatoBatchesOnBill);

        // Potato: partial match (50 kg matched, 30 kg remainder batch created)
        $potatoBatchesOnBill = StockBatch::where('goods_received_id', $billGrn->id)->where('product_id', $this->potato->id)->get();
        $this->assertCount(1, $potatoBatchesOnBill);
        $potatoRemainderBatch = $potatoBatchesOnBill->first();
        $this->assertEquals(30.0, (float) $potatoRemainderBatch->total_kg);
        $this->assertSame('A', $potatoRemainderBatch->purchase_grade);
        // Correct Grade A physical sorting rule on normal purchase remainder:
        $this->assertSame('sort_required', $potatoRemainderBatch->grading_mode, 'Commercial Grade A on normal purchase remainder must be sort_required until physically graded');
        $this->assertTrue((bool) $potatoRemainderBatch->warehouse_receive_pending);

        // Advances status
        $adv1->refresh();
        $adv2->refresh();
        $this->assertSame('bill_available', $adv1->bill_status);
        $this->assertSame('bill_available', $adv2->bill_status);

        // PO status transition: order was 180 kg total, 180 kg fulfilled -> Closed
        $po->refresh();
        $this->assertSame(POStatus::Closed, $po->status);

        // Repeated matching request with same client_submission_id returns idempotent GRN
        $repeatedGrn = $reconService->reconcileAndExecute($goodsReceivedData, $this->admin->id);
        $this->assertSame($billGrn->id, $repeatedGrn->id);
        $this->assertCount(2, AdvanceReceiveMatch::where('bill_goods_received_id', $billGrn->id)->get());

        // Create PurchaseInvoice linked to the bill GRN
        $invoice = PurchaseInvoice::create([
            'goods_received_id' => $billGrn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-MATCH-001',
            'amount' => 5000.0,
            'status' => 'approved',
        ]);

        // Cancel the invoice: matches must be released and advance receives restored to bill_pending for replacement billing
        $cancelAction = app(CancelPurchaseInvoiceAction::class);
        $cancelResult = $cancelAction->execute($invoice, $this->admin, 'Cancelled for replacement billing');

        $this->assertTrue($cancelResult['grn_released']);
        $this->assertSame(0, AdvanceReceiveMatch::where('bill_goods_received_id', $billGrn->id)->count());

        $adv1->refresh();
        $adv2->refresh();
        $this->assertSame('bill_pending', $adv1->bill_status, 'Advance 1 must be restored to bill_pending for replacement billing');
        $this->assertSame('bill_pending', $adv2->bill_status, 'Advance 2 must be restored to bill_pending for replacement billing');
    }
}
