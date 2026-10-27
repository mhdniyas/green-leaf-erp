<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Actions\Inventory\ProcessBatchSortingAction;
use App\Actions\Purchasing\CancelPurchaseInvoiceAction;
use App\DTOs\Inventory\SortingData;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Purchasing\POStatus;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseBusinessDay;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaserCart;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Inventory\StockLedgerService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NormalPurchaseReceivingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $receiver;

    private Warehouse $warehouse;

    private Supplier $supplier;

    private Category $category;

    private Product $tomato;

    private PurchaseBusinessDay $businessDay;

    private StockMovementRepository $stockRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'warehouse_receiver']);
        Role::firstOrCreate(['name' => 'purchaser']);

        Permission::firstOrCreate(['name' => 'purchasing.grn.create']);
        Permission::firstOrCreate(['name' => 'purchasing.grn.edit']);
        Permission::firstOrCreate(['name' => 'warehouse.receive.view']);
        Permission::firstOrCreate(['name' => 'warehouse.receive.confirm']);
        Permission::firstOrCreate(['name' => 'warehouse.receive.sort']);

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->receiver = User::factory()->create(['name' => 'Warehouse Receiver']);
        $this->receiver->assignRole('warehouse_receiver');
        $this->receiver->givePermissionTo([
            'purchasing.grn.create',
            'purchasing.grn.edit',
            'warehouse.receive.view',
            'warehouse.receive.confirm',
            'warehouse.receive.sort',
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Central Cold Storage',
            'code' => 'CCS-01',
            'is_active' => true,
        ]);

        $this->receiver->warehouses()->attach($this->warehouse->id, ['is_default' => true]);
        $this->admin->warehouses()->attach($this->warehouse->id);

        $this->supplier = Supplier::factory()->create(['name' => 'Fresh Farms']);

        $this->category = Category::create([
            'name' => 'Vegetables',
            'is_active' => true,
        ]);

        $this->tomato = Product::create([
            'name' => 'Roma Tomato',
            'sku' => 'TOM-ROM',
            'unit' => 'KG',
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'is_active' => true,
        ]);

        $this->stockRepo = app(StockMovementRepository::class);

        $today = now()->toDateString();
        $this->businessDay = PurchaseBusinessDay::create([
            'business_date' => $today,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'open',
            'opened_by' => $this->admin->id,
            'opened_at' => now(),
        ]);

        BusinessSetting::updateOrCreate(
            ['key' => 'purchaser_business_day_verification_enabled'],
            ['value' => '1']
        );
    }

    /**
     * Scenario 1: Normal purchase, confirmation, repeated receiving and cancellation.
     */
    public function test_scenario_1_normal_purchase_confirmation_repeated_receiving_and_cancellation(): void
    {
        $today = $this->businessDay->business_date->toDateString();

        // 1. Create a normal purchase order and bill
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'business_day_id' => $this->businessDay->id,
            'po_number' => 'PO-TEST-001',
            'status' => POStatus::Approved,
            'order_date' => $today,
            'created_by' => $this->admin->id,
            'fulfillment_type' => 'warehouse',
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->tomato->id,
            'grade' => 'A',
            'purchase_unit' => 'kg',
            'quantity' => 100.0,
            'unit_price' => 20.0,
        ]);

        $cart = PurchaserCart::create([
            'business_day_id' => $this->businessDay->id,
            'business_date' => $today,
            'user_id' => $this->admin->id,
            'cart_number' => uniqid('CART-'),
            'purchase_source' => 'warehouse',
            'status' => 'billed',
            'bill_number' => 'BILL-001',
        ]);

        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-TEST-001',
            'purchase_order_id' => $po->id,
            'purchaser_cart_id' => $cart->id,
            'business_day_id' => $this->businessDay->id,
            'business_date' => $today,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'normal',
            'status' => 'pending_approval',
            'received_at' => $today,
            'created_by' => $this->admin->id,
            'received_by' => $this->admin->id,
        ]);

        $grnItem = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->tomato->id,
            'grade' => 'A',
            'received_qty' => 100.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $invoice = PurchaseInvoice::create([
            'supplier_id' => $this->supplier->id,
            'goods_received_id' => $grn->id,
            'purchaser_cart_id' => $cart->id,
            'business_day_id' => $this->businessDay->id,
            'business_date' => $today,
            'invoice_number' => 'INV-TEST-001',
            'status' => 'pending',
            'amount' => 2000.0,
            'gross_amount' => 2000.0,
            'total_amount' => 2000.0,
            'created_by' => $this->admin->id,
            'received_by' => $this->admin->id,
        ]);

        // 2. Warehouse receiver receives the GRN via processReceiveGrn
        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                $grnItem->id => [
                    'warehouse_id' => $this->warehouse->id,
                    'received_qty' => 100.0,
                    'discrepancy_type' => 'none',
                    'discrepancy_note' => null,
                ],
            ],
        ];

        $response = $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.process-receive-grn', $grn->id), $payload);

        $response->assertRedirect(route('warehouse.receiver.checklist', ['date' => $today]));
        $response->assertSessionHas('success');

        $grn->refresh();
        $this->assertSame('approved', $grn->status);

        $batch = StockBatch::where('goods_received_id', $grn->id)->firstOrFail();
        $this->assertFalse((bool) $batch->warehouse_receive_pending);
        $this->assertNotNull($batch->warehouse_confirmed_at);
        $originalConfirmedAt = $batch->warehouse_confirmed_at->toDateTimeString();

        // 3. Repeated receiving: submit the exact same receive request again
        $repeatResponse = $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.process-receive-grn', $grn->id), $payload);

        $repeatResponse->assertRedirect(route('warehouse.receiver.checklist', ['date' => $today]));
        $repeatResponse->assertSessionHas('info');

        // Confirm batch count is still 1 and confirmed_at was preserved
        $this->assertSame(1, StockBatch::where('goods_received_id', $grn->id)->count());
        $batch->refresh();
        $this->assertSame($originalConfirmedAt, $batch->warehouse_confirmed_at->toDateTimeString());

        // 4. Test invoice cancellation protection for unconfirmed vs confirmed receipts
        // 4a. Create an unconfirmed pending purchase bill
        $unconfirmedGrn = GoodsReceived::create([
            'grn_number' => 'GRN-PENDING-002',
            'business_day_id' => $this->businessDay->id,
            'business_date' => $today,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'normal',
            'status' => 'pending_approval',
            'received_at' => $today,
            'created_by' => $this->admin->id,
            'received_by' => $this->admin->id,
        ]);

        $unconfirmedBatch = StockBatch::create([
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'goods_received_id' => $unconfirmedGrn->id,
            'purchase_grade' => 'A',
            'grading_mode' => 'sort_required',
            'created_by' => $this->admin->id,
            'received_by' => $this->admin->id,
            'reference' => 'BAT-PENDING-002',
            'received_at' => $today,
            'total_kg' => 50.0,
            'cost_per_kg' => 20.0,
            'status' => BatchStatus::Pending,
            'warehouse_receive_pending' => true,
        ]);

        $unconfirmedCart = PurchaserCart::create([
            'business_day_id' => $this->businessDay->id,
            'business_date' => $today,
            'user_id' => $this->admin->id,
            'cart_number' => uniqid('CART-'),
            'purchase_source' => 'warehouse',
            'status' => 'billed',
        ]);

        $unconfirmedInvoice = PurchaseInvoice::create([
            'supplier_id' => $this->supplier->id,
            'goods_received_id' => $unconfirmedGrn->id,
            'purchaser_cart_id' => $unconfirmedCart->id,
            'business_day_id' => $this->businessDay->id,
            'business_date' => $today,
            'invoice_number' => 'INV-PENDING-002',
            'status' => 'pending',
            'amount' => 1000.0,
            'gross_amount' => 1000.0,
            'total_amount' => 1000.0,
            'created_by' => $this->admin->id,
            'received_by' => $this->admin->id,
        ]);

        // Cancel the unconfirmed invoice
        app(CancelPurchaseInvoiceAction::class)->execute($unconfirmedInvoice, $this->admin, 'Duplicate entry');

        // Verify pending batch was soft-deleted so confirmAll cannot confirm phantom stock
        $this->assertSoftDeleted('stock_batches', ['id' => $unconfirmedBatch->id]);

        // confirmAll should find 0 pending batches to confirm
        $confirmAllResponse = $this->actingAs($this->receiver)
            ->post(route('warehouse.receiver.confirm-all'), ['date' => $today]);

        $confirmAllResponse->assertSessionHasErrors();

        // 4b. Cancel the confirmed invoice ($invoice) - physical stock must remain!
        app(CancelPurchaseInvoiceAction::class)->execute($invoice, $this->admin, 'Billing correction');

        $batch->refresh();
        $this->assertNull($batch->deleted_at);
        $this->assertFalse((bool) $batch->warehouse_receive_pending);
        $grn->refresh();
        $this->assertSame('bill_pending', $grn->bill_status); // Released for replacement bill
    }

    /**
     * Scenario 2: Partial receiving, remaining quantity, and bill quantity editing.
     */
    public function test_scenario_2_partial_receiving_remaining_quantity_and_bill_editing(): void
    {
        $today = $this->businessDay->business_date->toDateString();

        // 1. PO for 200 kg
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'business_day_id' => $this->businessDay->id,
            'business_date' => $today,
            'po_number' => 'PO-PARTIAL-001',
            'status' => POStatus::Approved,
            'order_date' => $today,
            'created_by' => $this->admin->id,
            'received_by' => $this->admin->id,
            'fulfillment_type' => 'warehouse',
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->tomato->id,
            'grade' => 'B',
            'purchase_unit' => 'kg',
            'quantity' => 200.0,
            'unit_price' => 25.0,
        ]);

        // 2. First delivery: partial receipt of 120 kg
        $cart1 = PurchaserCart::create([
            'business_day_id' => $this->businessDay->id,
            'business_date' => $today,
            'user_id' => $this->admin->id,
            'cart_number' => uniqid('CART-'),
            'purchase_source' => 'warehouse',
            'status' => 'billed',
        ]);

        $grn1 = GoodsReceived::create([
            'grn_number' => 'GRN-PARTIAL-001',
            'purchase_order_id' => $po->id,
            'purchaser_cart_id' => $cart1->id,
            'business_day_id' => $this->businessDay->id,
            'business_date' => $today,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'normal',
            'status' => 'pending_approval',
            'received_at' => $today,
            'created_by' => $this->admin->id,
            'received_by' => $this->admin->id,
        ]);

        $grnItem1 = GoodsReceivedItem::create([
            'goods_received_id' => $grn1->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->tomato->id,
            'grade' => 'B',
            'received_qty' => 120.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $invoice1 = PurchaseInvoice::create([
            'supplier_id' => $this->supplier->id,
            'goods_received_id' => $grn1->id,
            'purchaser_cart_id' => $cart1->id,
            'business_day_id' => $this->businessDay->id,
            'business_date' => $today,
            'invoice_number' => 'INV-PARTIAL-001',
            'status' => 'pending',
            'amount' => 3000.0,
            'gross_amount' => 3000.0,
            'total_amount' => 3000.0,
            'created_by' => $this->admin->id,
            'received_by' => $this->admin->id,
        ]);

        // Warehouse receives the 120 kg
        $this->actingAs($this->receiver)->post(route('warehouse.receiver.process-receive-grn', $grn1->id), [
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                $grnItem1->id => [
                    'warehouse_id' => $this->warehouse->id,
                    'received_qty' => 120.0,
                    'discrepancy_type' => 'none',
                ],
            ],
        ]);

        // Assert PO status is PartiallyReceived, NOT Closed!
        $po->refresh();
        $this->assertSame(POStatus::PartiallyReceived, $po->status);

        // Grade B is direct graded, so StockMovement was created
        $batch1 = StockBatch::where('goods_received_id', $grn1->id)->firstOrFail();
        $inMovement = StockMovement::where('batch_id', $batch1->id)->where('type', StockMovementType::In)->firstOrFail();
        $this->assertEquals(120.0, (float) $batch1->total_kg);
        $this->assertEquals(120.0, (float) $inMovement->quantity);

        // 3. Bill quantity editing on unconsumed batch (120 kg -> 110 kg)
        app(PurchaserBusinessDayService::class)->updateBusinessDayBill($grn1, [
            'bill_number' => 'BILL-PARTIAL-001-REV',
            'items' => [
                [
                    'id' => $grnItem1->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 110.0,
                    'received_unit' => 'kg',
                    'variance' => 0.0,
                    'unit_price' => 25.0,
                ],
            ],
        ], $this->admin->id);

        $batch1->refresh();
        $inMovement->refresh();
        $this->assertEquals(110.0, (float) $batch1->total_kg);
        $this->assertEquals(110.0, (float) $inMovement->quantity); // Kept in sync!

        // 4. Downstream consumption: consume 40 kg from this batch
        StockMovement::create([
            'batch_id' => $batch1->id,
            'product_id' => $this->tomato->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => StockMovementType::Out,
            'grade' => 'B',
            'quantity' => 40.0,
            'cost_per_unit' => 25.0,
            'created_by' => $this->admin->id,
            'received_by' => $this->admin->id,
        ]);

        // Purchaser tries to edit bill again to 90 kg
        app(PurchaserBusinessDayService::class)->updateBusinessDayBill($grn1, [
            'bill_number' => 'BILL-PARTIAL-001-REV2',
            'items' => [
                [
                    'id' => $grnItem1->id,
                    'product_id' => $this->tomato->id,
                    'received_qty' => 90.0,
                    'received_unit' => 'kg',
                    'variance' => 0.0,
                    'unit_price' => 25.0,
                ],
            ],
        ], $this->admin->id);

        // Batch and IN movement should NOT be overwritten because stock was consumed!
        $batch1->refresh();
        $inMovement->refresh();
        $this->assertEquals(110.0, (float) $batch1->total_kg);
        $this->assertEquals(110.0, (float) $inMovement->quantity);

        // 5. Deliver remaining 80 kg on a second GRN
        $cart2 = PurchaserCart::create([
            'business_day_id' => $this->businessDay->id,
            'business_date' => $today,
            'user_id' => $this->admin->id,
            'cart_number' => uniqid('CART-'),
            'purchase_source' => 'warehouse',
            'status' => 'billed',
        ]);

        $grn2 = GoodsReceived::create([
            'grn_number' => 'GRN-PARTIAL-002',
            'purchase_order_id' => $po->id,
            'purchaser_cart_id' => $cart2->id,
            'business_day_id' => $this->businessDay->id,
            'business_date' => $today,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'normal',
            'status' => 'pending_approval',
            'received_at' => $today,
            'created_by' => $this->admin->id,
            'received_by' => $this->admin->id,
        ]);

        $grnItem2 = GoodsReceivedItem::create([
            'goods_received_id' => $grn2->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->tomato->id,
            'grade' => 'B',
            'received_qty' => 90.0, // Remaining balance fulfilled (110 + 90 = 200 kg)
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $this->actingAs($this->receiver)->post(route('warehouse.receiver.process-receive-grn', $grn2->id), [
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                $grnItem2->id => [
                    'warehouse_id' => $this->warehouse->id,
                    'received_qty' => 90.0,
                    'discrepancy_type' => 'none',
                ],
            ],
        ]);

        // Now cumulative received = 200 kg >= 200 kg, PO transitions to Closed
        $po->refresh();
        $this->assertSame(POStatus::Closed, $po->status);
    }

    /**
     * Scenario 3: Direct graded versus unsorted receiving and inventory consistency.
     */
    public function test_scenario_3_direct_graded_vs_unsorted_receiving_and_inventory_consistency(): void
    {
        $today = $this->businessDay->business_date->toDateString();

        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'business_day_id' => $this->businessDay->id,
            'business_date' => $today,
            'po_number' => 'PO-GRADE-001',
            'status' => POStatus::Approved,
            'order_date' => $today,
            'created_by' => $this->admin->id,
            'received_by' => $this->admin->id,
            'fulfillment_type' => 'warehouse',
        ]);

        // Item 1: Grade A (commercial Grade A, 100 kg)
        $poItemA = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->tomato->id,
            'grade' => 'A',
            'purchase_unit' => 'kg',
            'quantity' => 100.0,
            'unit_price' => 30.0,
        ]);

        // Item 2: Grade B (direct graded, 50 kg)
        $poItemB = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->tomato->id,
            'grade' => 'B',
            'purchase_unit' => 'kg',
            'quantity' => 50.0,
            'unit_price' => 22.0,
        ]);

        $cart = PurchaserCart::create([
            'business_day_id' => $this->businessDay->id,
            'business_date' => $today,
            'user_id' => $this->admin->id,
            'cart_number' => uniqid('CART-'),
            'purchase_source' => 'warehouse',
            'status' => 'billed',
        ]);

        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-GRADE-001',
            'purchase_order_id' => $po->id,
            'purchaser_cart_id' => $cart->id,
            'business_day_id' => $this->businessDay->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_type' => 'normal',
            'status' => 'pending_approval',
            'received_at' => $today,
            'created_by' => $this->admin->id,
            'received_by' => $this->admin->id,
        ]);

        $grnItemA = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'purchase_order_item_id' => $poItemA->id,
            'product_id' => $this->tomato->id,
            'grade' => 'A',
            'received_qty' => 100.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        $grnItemB = GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'purchase_order_item_id' => $poItemB->id,
            'product_id' => $this->tomato->id,
            'grade' => 'B',
            'received_qty' => 50.0,
            'received_unit' => 'kg',
            'variance' => 0.0,
        ]);

        // Warehouse receives the GRN
        $this->actingAs($this->receiver)->post(route('warehouse.receiver.process-receive-grn', $grn->id), [
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                $grnItemA->id => [
                    'warehouse_id' => $this->warehouse->id,
                    'received_qty' => 100.0,
                    'discrepancy_type' => 'none',
                ],
                $grnItemB->id => [
                    'warehouse_id' => $this->warehouse->id,
                    'received_qty' => 50.0,
                    'discrepancy_type' => 'none',
                ],
            ],
        ]);

        // Check batches
        $batchA = StockBatch::where('goods_received_item_id', $grnItemA->id)->firstOrFail();
        $batchB = StockBatch::where('goods_received_item_id', $grnItemB->id)->firstOrFail();

        // Grade B: Direct Graded
        $this->assertSame('fixed_purchase_grade', $batchB->grading_mode);
        $this->assertSame(BatchStatus::Sorted, $batchB->status);
        $gradeBStock = app(StockLedgerService::class)->availableSortedStockForProduct($this->tomato->id, $this->warehouse->id, ProductGrade::GradeB);
        $this->assertEquals(50.0, $gradeBStock);

        // Grade A: Normal purchase must NOT be auto-converted to fixed_purchase_grade!
        $this->assertSame('sort_required', $batchA->grading_mode);
        $this->assertSame(BatchStatus::Pending, $batchA->status);
        $gradeAStockBeforeSorting = app(StockLedgerService::class)->availableSortedStockForProduct($this->tomato->id, $this->warehouse->id, ProductGrade::GradeA);
        $this->assertEquals(0.0, $gradeAStockBeforeSorting); // No Grade A stock until sorting occurs!

        // Now physically sort Grade A: 85 kg Grade A, 10 kg Grade B, 5 kg Damage
        $sortData = new SortingData(
            batchId: $batchA->id,
            grades: [
                ['grade' => ProductGrade::GradeA, 'quantity' => 85.0],
                ['grade' => ProductGrade::GradeB, 'quantity' => 10.0],
                ['grade' => ProductGrade::Damage, 'quantity' => 5.0],
            ],
            notes: 'Physical sorting completed',
        );

        app(ProcessBatchSortingAction::class)->execute($batchA, $sortData, $this->receiver->id);

        // Verify inventory after sorting
        $gradeAStockAfterSorting = app(StockLedgerService::class)->availableSortedStockForProduct($this->tomato->id, $this->warehouse->id, ProductGrade::GradeA);
        $this->assertEquals(85.0, $gradeAStockAfterSorting);

        $gradeBStockAfterSorting = app(StockLedgerService::class)->availableSortedStockForProduct($this->tomato->id, $this->warehouse->id, ProductGrade::GradeB);
        $this->assertEquals(60.0, $gradeBStockAfterSorting); // 50 from direct graded + 10 from sorting
    }
}
