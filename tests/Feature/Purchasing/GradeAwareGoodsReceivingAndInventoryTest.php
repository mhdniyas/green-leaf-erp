<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Actions\Inventory\ProcessBatchSortingAction;
use App\Actions\Purchasing\ApproveGoodsReceiptAction;
use App\DTOs\Inventory\SortingData;
use App\DTOs\Purchasing\GoodsReceivedData;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Purchasing\GoodsReceivedService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GradeAwareGoodsReceivingAndInventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $receiver;

    private Warehouse $warehouse1;

    private Warehouse $warehouse2;

    private Supplier $supplier;

    private Shop $shop;

    private Product $tomato;

    private Product $potato;

    private StockMovementRepository $stockRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'warehouse_receiver']);
        Permission::firstOrCreate(['name' => 'purchasing.grn.create']);
        Permission::firstOrCreate(['name' => 'warehouse.receive.view']);

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->receiver = User::factory()->create(['name' => 'Receiver User']);
        $this->receiver->assignRole('warehouse_receiver');
        $this->receiver->givePermissionTo(['purchasing.grn.create', 'warehouse.receive.view']);

        $this->warehouse1 = Warehouse::factory()->create(['name' => 'Central Warehouse 1', 'code' => 'CW1', 'is_active' => true]);
        $this->warehouse2 = Warehouse::factory()->create(['name' => 'Central Warehouse 2', 'code' => 'CW2', 'is_active' => true]);

        $this->receiver->warehouses()->attach($this->warehouse1->id, ['is_default' => true]);
        $this->admin->warehouses()->attach([$this->warehouse1->id, $this->warehouse2->id]);

        $this->shop = Shop::factory()->create(['name' => 'Retail Outlet 1']);
        $this->supplier = Supplier::factory()->create(['name' => 'Farm Fresh Produce']);

        $this->tomato = Product::factory()->create([
            'name' => 'Tomato Country',
            'sku' => 'TOM-CTY-01',
            'unit' => 'KG',
            'default_warehouse_id' => $this->warehouse1->id,
        ]);

        $this->potato = Product::factory()->create([
            'name' => 'Potato Agra',
            'sku' => 'POT-AGR-01',
            'unit' => 'KG',
            'default_warehouse_id' => $this->warehouse1->id,
        ]);

        $this->stockRepo = app(StockMovementRepository::class);
    }

    public function test_1_receive_100kg_mixed_increases_mixed_pool_and_no_graded_stock(): void
    {
        Sanctum::actingAs($this->receiver);

        $response = $this->postJson('/api/v1/purchasing/grns', [
            'warehouse_id' => $this->warehouse1->id,
            'received_at' => now()->toDateString(),
            'receipt_type' => 'warehouse_advance',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'KG',
                    'grade' => 'MIXED',
                ],
            ],
        ]);

        $response->assertCreated();

        $mixedStock = $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::Unsorted, $this->warehouse1->id);
        $gradeAStock = $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouse1->id);
        $gradeBStock = $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeB, $this->warehouse1->id);
        $totalStock = $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouse1->id);

        $this->assertEquals(100.0, $mixedStock, 'Mixed stock must equal 100 kg');
        $this->assertEquals(0.0, $gradeAStock, 'Grade A stock must be 0 kg');
        $this->assertEquals(0.0, $gradeBStock, 'Grade B stock must be 0 kg');
        $this->assertEquals(100.0, $totalStock, 'Total physical stock must equal 100 kg');
    }

    public function test_2_receive_100kg_grade_a_directly_increases_grade_a_stock(): void
    {
        Sanctum::actingAs($this->receiver);

        $response = $this->postJson('/api/v1/purchasing/grns', [
            'warehouse_id' => $this->warehouse1->id,
            'received_at' => now()->toDateString(),
            'receipt_type' => 'warehouse_advance',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'KG',
                    'grade' => 'A',
                ],
            ],
        ]);

        $response->assertCreated();

        $gradeAStock = $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouse1->id);
        $mixedStock = $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::Unsorted, $this->warehouse1->id);
        $totalStock = $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouse1->id);

        $this->assertEquals(100.0, $gradeAStock, 'Grade A stock must equal 100 kg');
        $this->assertEquals(0.0, $mixedStock, 'Mixed stock must be 0 kg');
        $this->assertEquals(100.0, $totalStock, 'Total physical stock must equal 100 kg');
    }

    public function test_3_receive_100kg_grade_b_increases_grade_b_stock(): void
    {
        Sanctum::actingAs($this->receiver);

        $response = $this->postJson('/api/v1/purchasing/grns', [
            'warehouse_id' => $this->warehouse1->id,
            'received_at' => now()->toDateString(),
            'receipt_type' => 'warehouse_advance',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'KG',
                    'grade' => 'B',
                ],
            ],
        ]);

        $response->assertCreated();

        $gradeBStock = $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeB, $this->warehouse1->id);
        $mixedStock = $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::Unsorted, $this->warehouse1->id);
        $totalStock = $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouse1->id);

        $this->assertEquals(100.0, $gradeBStock, 'Grade B stock must equal 100 kg');
        $this->assertEquals(0.0, $mixedStock, 'Mixed stock must be 0 kg');
        $this->assertEquals(100.0, $totalStock, 'Total physical stock must equal 100 kg');
    }

    public function test_4_receive_100kg_grade_c_increases_grade_c_stock(): void
    {
        Sanctum::actingAs($this->receiver);

        $response = $this->postJson('/api/v1/purchasing/grns', [
            'warehouse_id' => $this->warehouse1->id,
            'received_at' => now()->toDateString(),
            'receipt_type' => 'warehouse_advance',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'KG',
                    'grade' => 'C',
                ],
            ],
        ]);

        $response->assertCreated();

        $gradeCStock = $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeC, $this->warehouse1->id);
        $mixedStock = $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::Unsorted, $this->warehouse1->id);
        $totalStock = $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouse1->id);

        $this->assertEquals(100.0, $gradeCStock, 'Grade C stock must equal 100 kg');
        $this->assertEquals(0.0, $mixedStock, 'Mixed stock must be 0 kg');
        $this->assertEquals(100.0, $totalStock, 'Total physical stock must equal 100 kg');
    }

    public function test_5_receive_one_product_with_grade_a_b_and_mixed_lines_in_single_grn(): void
    {
        Sanctum::actingAs($this->receiver);

        $response = $this->postJson('/api/v1/purchasing/grns', [
            'warehouse_id' => $this->warehouse1->id,
            'received_at' => now()->toDateString(),
            'receipt_type' => 'warehouse_advance',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 30.0,
                    'received_unit' => 'KG',
                    'grade' => 'A',
                ],
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 20.0,
                    'received_unit' => 'KG',
                    'grade' => 'B',
                ],
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 50.0,
                    'received_unit' => 'KG',
                    'grade' => 'U',
                ],
            ],
        ]);

        $response->assertCreated();

        $gradeA = $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouse1->id);
        $gradeB = $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeB, $this->warehouse1->id);
        $gradeC = $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeC, $this->warehouse1->id);
        $mixed = $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::Unsorted, $this->warehouse1->id);
        $total = $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouse1->id);

        $this->assertEquals(30.0, $gradeA, 'Grade A must be 30 kg');
        $this->assertEquals(20.0, $gradeB, 'Grade B must be 20 kg');
        $this->assertEquals(0.0, $gradeC, 'Grade C must be 0 kg');
        $this->assertEquals(50.0, $mixed, 'Mixed must be 50 kg');
        $this->assertEquals(100.0, $total, 'Total stock must equal 100 kg');
    }

    public function test_6_standard_purchase_bill_pending_adds_no_stock_until_confirmed(): void
    {
        Sanctum::actingAs($this->receiver);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->shop->id,
            'status' => POStatus::Approved,
            'order_date' => now()->toDateString(),
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->tomato->id,
            'quantity' => 50.00,
            'purchase_unit' => 'KG',
        ]);

        // 1. Create standard bill receipt (receipt_type = normal_purchase)
        $data = new GoodsReceivedData(
            purchaseOrderId: $po->id,
            receivedAt: now()->toDateString(),
            transportCost: 0.0,
            labourCost: 0.0,
            notes: 'Test PO receipt',
            items: [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 50.0,
                    'received_unit' => 'KG',
                    'grade' => 'B',
                ],
            ],
            billStatus: 'bill_available',
            destinationShopId: $this->shop->id,
            warehouseId: $this->warehouse1->id,
            receiptType: 'normal_purchase',
        );

        $grn = app(GoodsReceivedService::class)->create($data, $this->receiver->id);

        // Before warehouse receive confirmation: stock must be 0
        $stockBefore = $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouse1->id);
        $this->assertEquals(0.0, $stockBefore, 'Pending purchase receipt must add 0 physical stock');

        // Check stock batch state
        $batch = StockBatch::where('goods_received_id', $grn->id)->firstOrFail();
        $this->assertTrue((bool) $batch->warehouse_receive_pending, 'Batch must have warehouse_receive_pending = true');
        $this->assertEquals(BatchStatus::Pending, $batch->status);

        // 2. Warehouse receiver confirms physical receipt
        app(ApproveGoodsReceiptAction::class)->executeAndConfirmReceive($grn, $this->receiver->id, $this->warehouse1->id);

        $stockAfter = $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouse1->id);
        $gradeBStock = $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeB, $this->warehouse1->id);

        $this->assertEquals(50.0, $stockAfter, 'Confirmed receipt must add 50 kg physical stock once');
        $this->assertEquals(50.0, $gradeBStock, 'Confirmed Grade B receipt must be in Grade B pool');

        $batch->refresh();
        $this->assertFalse((bool) $batch->warehouse_receive_pending);
        $this->assertEquals(BatchStatus::Sorted, $batch->status);
    }

    public function test_7_advance_receipt_matched_with_bill_does_not_duplicate_stock(): void
    {
        Sanctum::actingAs($this->receiver);

        // Step 1: Advance warehouse receipt of 80 kg Tomato Grade B
        $advResponse = $this->postJson('/api/v1/purchasing/grns', [
            'warehouse_id' => $this->warehouse1->id,
            'received_at' => now()->toDateString(),
            'receipt_type' => 'warehouse_advance',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 80.0,
                    'received_unit' => 'KG',
                    'grade' => 'B',
                ],
            ],
        ]);

        $advResponse->assertCreated();
        $advGrnId = $advResponse->json('data.id');
        $advGrn = GoodsReceived::with('items')->findOrFail($advGrnId);
        $advItem = $advGrn->items->firstOrFail();

        $stockAfterAdvance = $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouse1->id);
        $this->assertEquals(80.0, $stockAfterAdvance, 'Advance receipt must add 80 kg physical stock');

        // Step 2: Final supplier bill arrives for 80 kg and is matched with the advance receipt
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->shop->id,
            'status' => POStatus::Approved,
            'order_date' => now()->toDateString(),
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->tomato->id,
            'quantity' => 80.00,
            'purchase_unit' => 'KG',
            'grade' => 'B',
        ]);

        $billResponse = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse1->id,
            'received_at' => now()->toDateString(),
            'bill_status' => 'bill_available',
            'bill_number' => 'FINAL-BILL-001',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 80.0,
                    'received_unit' => 'KG',
                    'grade' => 'B',
                ],
            ],
            'advance_matches' => [
                [
                    'advance_goods_received_id' => $advGrn->id,
                    'advance_goods_received_item_id' => $advItem->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'matched_qty' => 80.0,
                    'unit' => 'KG',
                ],
            ],
        ]);

        $billResponse->assertCreated();

        // Verify stock has NOT duplicated!
        $stockAfterBill = $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouse1->id);
        $gradeBStock = $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeB, $this->warehouse1->id);

        $this->assertEquals(80.0, $stockAfterBill, 'Matching advance receipt with bill must NOT duplicate stock (remains 80 kg)');
        $this->assertEquals(80.0, $gradeBStock, 'Grade B stock must remain exactly 80 kg');
    }

    public function test_8_excess_quantity_after_advance_matching_adds_only_excess_batch(): void
    {
        Sanctum::actingAs($this->receiver);

        // Step 1: Advance warehouse receipt of 60 kg Tomato Grade A
        $advResponse = $this->postJson('/api/v1/purchasing/grns', [
            'warehouse_id' => $this->warehouse1->id,
            'received_at' => now()->toDateString(),
            'receipt_type' => 'warehouse_advance',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 60.0,
                    'received_unit' => 'KG',
                    'grade' => 'A',
                ],
            ],
        ]);

        $advResponse->assertCreated();
        $advGrn = GoodsReceived::with('items')->findOrFail($advResponse->json('data.id'));
        $advItem = $advGrn->items->firstOrFail();

        // Step 2: Bill arrives for 85 kg (25 kg excess over the 60 kg advance receipt)
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->shop->id,
            'status' => POStatus::Approved,
            'order_date' => now()->toDateString(),
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->tomato->id,
            'quantity' => 85.00,
            'purchase_unit' => 'KG',
            'grade' => 'A',
        ]);

        $billResponse = $this->postJson('/api/v1/purchasing/grns', [
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse1->id,
            'received_at' => now()->toDateString(),
            'bill_status' => 'bill_available',
            'bill_number' => 'EXCESS-BILL-001',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 85.0,
                    'received_unit' => 'KG',
                    'grade' => 'A',
                ],
            ],
            'advance_matches' => [
                [
                    'advance_goods_received_id' => $advGrn->id,
                    'advance_goods_received_item_id' => $advItem->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->tomato->id,
                    'matched_qty' => 60.0,
                    'unit' => 'KG',
                ],
            ],
        ]);

        $billResponse->assertCreated();
        $billGrn = GoodsReceived::findOrFail($billResponse->json('data.id'));

        // The bill creates an excess batch for 25 kg with warehouse_receive_pending = true
        $excessBatch = StockBatch::where('goods_received_id', $billGrn->id)->firstOrFail();
        $this->assertEquals(25.0, (float) $excessBatch->total_kg);
        $this->assertTrue((bool) $excessBatch->warehouse_receive_pending);

        // Current physical stock is still the original 60 kg because excess batch is pending warehouse confirmation
        $stockBeforeConfirm = $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouse1->id);
        $this->assertEquals(60.0, $stockBeforeConfirm);

        // When warehouse confirms the excess receipt
        app(ApproveGoodsReceiptAction::class)->executeAndConfirmReceive($billGrn, $this->receiver->id, $this->warehouse1->id);

        $stockAfterConfirm = $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouse1->id);
        $this->assertEquals(85.0, $stockAfterConfirm, 'Total stock must equal exactly 60 + 25 = 85 kg');
    }

    public function test_9_advance_receipt_cancellation_reverses_stock_cleanly(): void
    {
        Sanctum::actingAs($this->admin);

        $advResponse = $this->postJson('/api/v1/purchasing/grns', [
            'warehouse_id' => $this->warehouse1->id,
            'received_at' => now()->toDateString(),
            'receipt_type' => 'warehouse_advance',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 45.0,
                    'received_unit' => 'KG',
                    'grade' => 'A',
                ],
            ],
        ]);

        $advResponse->assertCreated();
        $grnId = $advResponse->json('data.id');
        $grn = GoodsReceived::findOrFail($grnId);

        $stockBeforeDelete = $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouse1->id);
        $this->assertEquals(45.0, $stockBeforeDelete);

        // Delete / cancel advance receipt
        $delResponse = $this->deleteJson("/api/v1/purchasing/grns/{$grn->id}");
        $delResponse->assertOk();

        $stockAfterDelete = $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouse1->id);
        $gradeAAfterDelete = $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouse1->id);

        $this->assertEquals(0.0, $stockAfterDelete, 'Reversed advance receipt must return physical stock to 0 kg');
        $this->assertEquals(0.0, $gradeAAfterDelete, 'Grade A stock must return to 0 kg');
    }

    public function test_10_warehouse_isolation_keeps_stock_separate(): void
    {
        Sanctum::actingAs($this->admin);

        // Receive 40 kg Grade A in Warehouse 1
        $this->postJson('/api/v1/purchasing/grns', [
            'warehouse_id' => $this->warehouse1->id,
            'received_at' => now()->toDateString(),
            'receipt_type' => 'warehouse_advance',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 40.0,
                    'received_unit' => 'KG',
                    'grade' => 'A',
                ],
            ],
        ])->assertCreated();

        // Receive 60 kg Grade B in Warehouse 2
        $this->postJson('/api/v1/purchasing/grns', [
            'warehouse_id' => $this->warehouse2->id,
            'received_at' => now()->toDateString(),
            'receipt_type' => 'warehouse_advance',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 60.0,
                    'received_unit' => 'KG',
                    'grade' => 'B',
                ],
            ],
        ])->assertCreated();

        $wh1Stock = $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouse1->id);
        $wh2Stock = $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouse2->id);

        $this->assertEquals(40.0, $wh1Stock, 'Warehouse 1 stock must equal 40 kg');
        $this->assertEquals(60.0, $wh2Stock, 'Warehouse 2 stock must equal 60 kg');
    }

    public function test_11_unit_conversion_to_base_kg_is_accurate(): void
    {
        Sanctum::actingAs($this->receiver);

        // Box unit with 20 kg conversion
        ProductUnit::create([
            'product_id' => $this->potato->id,
            'unit' => 'BOX',
            'label' => 'Box of 20kg',
            'conversion_to_base' => 20.0,
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'destination_shop_id' => $this->shop->id,
            'status' => POStatus::Approved,
            'order_date' => now()->toDateString(),
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->potato->id,
            'quantity' => 2.0,
            'purchase_unit' => 'BOX',
        ]);

        $data = new GoodsReceivedData(
            purchaseOrderId: $po->id,
            receivedAt: now()->toDateString(),
            transportCost: 0.0,
            labourCost: 0.0,
            notes: '2 Boxes of Potatoes',
            items: [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->potato->id,
                    'received_qty' => 2.0,
                    'received_unit' => 'BOX',
                    'grade' => 'B',
                ],
            ],
            billStatus: 'bill_available',
            destinationShopId: $this->shop->id,
            warehouseId: $this->warehouse1->id,
            receiptType: 'normal_purchase',
        );

        $grn = app(GoodsReceivedService::class)->create($data, $this->receiver->id);
        app(ApproveGoodsReceiptAction::class)->executeAndConfirmReceive($grn, $this->receiver->id, $this->warehouse1->id);

        $stock = $this->stockRepo->currentStockForProduct($this->potato->id, $this->warehouse1->id);
        $this->assertEquals(2.0, $stock);
    }

    public function test_12_existing_batch_sorting_compatibility_converts_mixed_to_graded_stock(): void
    {
        Sanctum::actingAs($this->receiver);

        // Receive 100 kg Mixed in Warehouse 1
        $response = $this->postJson('/api/v1/purchasing/grns', [
            'warehouse_id' => $this->warehouse1->id,
            'received_at' => now()->toDateString(),
            'receipt_type' => 'warehouse_advance',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 100.0,
                    'received_unit' => 'KG',
                    'grade' => 'U',
                ],
            ],
        ]);

        $response->assertCreated();
        $grn = GoodsReceived::findOrFail($response->json('data.id'));
        $batch = StockBatch::where('goods_received_id', $grn->id)->firstOrFail();

        $this->assertTrue($batch->canBeSorted(), 'Mixed batch must be sortable');

        // Sorters sort 100 kg into: 50 kg Grade A, 30 kg Grade B, 15 kg Grade C, 5 kg Damage
        $sortingData = new SortingData(
            batchId: $batch->id,
            grades: [
                ['grade' => ProductGrade::GradeA, 'quantity' => 50.0],
                ['grade' => ProductGrade::GradeB, 'quantity' => 30.0],
                ['grade' => ProductGrade::GradeC, 'quantity' => 15.0],
                ['grade' => ProductGrade::Damage, 'quantity' => 5.0],
            ],
            notes: 'Batch sorting test'
        );

        app(ProcessBatchSortingAction::class)->execute($batch, $sortingData, $this->receiver->id);

        $batch->refresh();
        $this->assertEquals(BatchStatus::Sorted, $batch->status);
        $this->assertFalse($batch->canBeSorted(), 'Sorted batch cannot be sorted again');

        $gradeA = $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeA, $this->warehouse1->id);
        $gradeB = $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeB, $this->warehouse1->id);
        $gradeC = $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::GradeC, $this->warehouse1->id);
        $mixed = $this->stockRepo->stockForProductAndGrade($this->tomato->id, ProductGrade::Unsorted, $this->warehouse1->id);
        $total = $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouse1->id);

        $this->assertEquals(50.0, $gradeA, 'Grade A must be 50 kg');
        $this->assertEquals(30.0, $gradeB, 'Grade B must be 30 kg');
        $this->assertEquals(15.0, $gradeC, 'Grade C must be 15 kg');
        $this->assertEquals(0.0, $mixed, 'Mixed must be 0 kg after 100% sorting');
        $this->assertEquals(95.0, $total, 'Total physical stock must be 95 kg (5 kg was damaged/wasted)');
    }

    public function test_13_idempotent_grn_retry_does_not_duplicate_stock(): void
    {
        Sanctum::actingAs($this->receiver);

        $clientSubmissionId = 'SUBMISSION-UUID-12345';

        $payload = [
            'client_submission_id' => $clientSubmissionId,
            'warehouse_id' => $this->warehouse1->id,
            'received_at' => now()->toDateString(),
            'receipt_type' => 'warehouse_advance',
            'items' => [
                [
                    'product_id' => $this->tomato->id,
                    'received_qty' => 50.0,
                    'received_unit' => 'KG',
                    'grade' => 'A',
                ],
            ],
        ];

        // First attempt
        $res1 = $this->postJson('/api/v1/purchasing/grns', $payload);
        $res1->assertCreated();

        // Retry with same clientSubmissionId
        $res2 = $this->postJson('/api/v1/purchasing/grns', $payload);
        $res2->assertSuccessful();

        $stock = $this->stockRepo->currentStockForProduct($this->tomato->id, $this->warehouse1->id);
        $this->assertEquals(50.0, $stock, 'Idempotent retry must NOT duplicate stock');
    }
}
