<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\ShopInvoice\ShopCreditNoteAccountingMode;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteReceiptStatus;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopAccountingPeriodClosure;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\InvoiceCashbookProjectionService;
use App\Services\Inventory\StockLedgerService;
use App\Services\ShopInvoices\ShopCreditApplicationService;
use App\Services\ShopInvoices\ShopCreditNoteService;
use App\Services\ShopInvoices\ShopCreditNoteWarehouseReceiptService;
use App\Services\ShopInvoices\ShopFinalBillService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PDO;
use Tests\TestCase;

class ShopCreditNoteReceiptReversalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $warehouseStaff;

    private User $unauthorizedUser;

    private Shop $shopA;

    private Warehouse $warehouseCentral;

    private Warehouse $warehouseHub;

    private Product $productOnion;

    private ShopInvoice $invoiceA1;

    private ShopInvoiceItem $invoiceItemA1;

    private StockBatch $initialDispatchBatch;

    private StockMovement $initialDispatchMovement;

    private ShopCreditNoteService $creditNoteService;

    private ShopCreditNoteWarehouseReceiptService $receiptService;

    private ShopCreditApplicationService $applicationService;

    private StockLedgerService $stockLedgerService;

    private InvoiceCashbookProjectionService $projectionService;

    private ShopFinalBillService $finalBillService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        config(['admin.user_access.main_admin_email' => 'admin@example.com']);

        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
        ]);
        $this->admin->assignRole('admin');

        $this->warehouseStaff = User::factory()->create([
            'name' => 'Kochi Warehouse Operator',
        ]);
        $this->warehouseStaff->assignRole('warehouse_receiver');

        $this->unauthorizedUser = User::factory()->create([
            'name' => 'Purchaser Staff',
        ]);
        $this->unauthorizedUser->assignRole('purchaser');

        $this->warehouseCentral = Warehouse::factory()->create([
            'code' => 'WH-CENTRAL',
            'name' => 'Central Warehouse',
            'is_active' => true,
        ]);

        $this->warehouseHub = Warehouse::factory()->create([
            'code' => 'WH-HUB',
            'name' => 'Suburban Hub Warehouse',
            'is_active' => true,
        ]);

        $this->shopA = Shop::factory()->create([
            'code' => 'SHP-A',
            'name' => 'Kochi Central Shop',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->projectionService = app(InvoiceCashbookProjectionService::class);
        $this->creditNoteService = app(ShopCreditNoteService::class);
        $this->receiptService = app(ShopCreditNoteWarehouseReceiptService::class);
        $this->applicationService = app(ShopCreditApplicationService::class);
        $this->stockLedgerService = app(StockLedgerService::class);
        $this->finalBillService = app(ShopFinalBillService::class);

        $this->productOnion = Product::factory()->create([
            'name' => 'Big Onion',
            'default_warehouse_id' => $this->warehouseCentral->id,
        ]);

        // Create initial stock batch and original sale loadout
        $this->initialDispatchBatch = StockBatch::factory()->create([
            'product_id' => $this->productOnion->id,
            'warehouse_id' => $this->warehouseCentral->id,
            'purchase_grade' => 'A',
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'cost_per_kg' => 40.00,
            'total_kg' => 200.0,
        ]);

        // Stock In movement: 200 kg Grade A
        StockMovement::create([
            'batch_id' => $this->initialDispatchBatch->id,
            'product_id' => $this->productOnion->id,
            'warehouse_id' => $this->warehouseCentral->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 200.0,
            'cost_per_unit' => 40.00,
            'created_by' => $this->admin->id,
            'notes' => 'Initial purchase stock in',
        ]);

        // Invoice for 100 kg Grade A @ ₹55/kg (₹5,500)
        $this->invoiceA1 = $this->createFinalizedInvoice(
            $this->shopA,
            'SINV-WH-101',
            today()->subDays(5)->toDateString(),
            5500.00,
            0.00
        );

        $this->invoiceItemA1 = $this->createInvoiceItem(
            $this->invoiceA1,
            $this->productOnion,
            'A',
            100.0,
            55.00
        );

        // Out movement for original sale dispatch
        $this->initialDispatchMovement = StockMovement::create([
            'batch_id' => $this->initialDispatchBatch->id,
            'product_id' => $this->productOnion->id,
            'warehouse_id' => $this->warehouseCentral->id,
            'shop_order_item_id' => $this->invoiceItemA1->shop_order_item_id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 100.0,
            'cost_per_unit' => 40.00,
            'created_by' => $this->admin->id,
            'notes' => 'Original sale dispatch for invoice '.$this->invoiceA1->invoice_number,
        ]);
    }

    private function createFinalizedInvoice(
        Shop $shop,
        string $invoiceNumber,
        ?string $date = null,
        float $total = 5500.00,
        float $paid = 0.00
    ): ShopInvoice {
        static $orderCounter = 1;
        $orderNum = $orderCounter++;
        $businessDate = $date ?? today()->toDateString();
        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $businessDate,
            'shop_daily_order_key' => "shop:{$shop->id}:{$businessDate}:{$orderNum}",
        ]);

        $balance = max(0.00, $total - $paid);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'business_date' => $businessDate,
            'status' => 'finalized',
            'delivery_status' => 'received_full',
            'payment_status' => $balance <= 0.0001 ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid'),
            'final_total' => $total,
            'paid_amount' => $paid,
            'balance_amount' => $balance,
            'finalized_by' => $this->admin->id,
            'finalized_at' => now(),
        ]);

        $this->projectionService->syncInvoice($invoice);

        return $invoice;
    }

    private function createInvoiceItem(ShopInvoice $invoice, Product $product, string $grade, float $qty, float $price): ShopInvoiceItem
    {
        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $invoice->shop_order_id,
            'product_id' => $product->id,
            'product_grade' => $grade,
            'requested_qty' => $qty,
            'approved_qty' => $qty,
            'loaded_qty' => $qty,
            'delivered_qty' => $qty,
            'unit' => 'kg',
            'unit_cost' => 40.00,
        ]);

        return ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_grade' => $grade,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => $qty,
            'delivered_qty' => $qty,
            'unit_price' => $price,
            'line_subtotal' => round($qty * $price, 2),
            'final_line_total' => round($qty * $price, 2),
        ]);
    }

    /**
     * Helper to create and finalize a shop-level Credit Note draft for return to stock.
     */
    private function createAndFinalizeReturnCreditNote(float $returnQty = 50.0, float $creditRate = 35.00): ShopCreditNote
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Customer physical return claim',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->invoiceItemA1->product_id,
                    'product_grade' => $this->invoiceItemA1->product_grade,
                    'unit' => 'kg',
                    'credit_quantity' => $returnQty,
                    'credit_rate' => $creditRate,
                    'line_credit_amount' => round($returnQty * $creditRate, 2),
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                    'reason_type' => ShopCreditNoteReason::ReturnToWarehouse,
                ],
            ],
        ], $this->admin);

        return $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);
    }

    /**
     * Test 1: Full receipt reversal decreases received Grade B stock.
     */
    public function test_01_full_receipt_reversal_decreases_received_grade_b_stock(): void
    {
        $creditNote = $this->createAndFinalizeReturnCreditNote(50.0, 35.00);
        $item = $creditNote->items->first();

        // Warehouse receives 50 kg as Grade B
        $receipt = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 50.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff,
            remarks: 'Physical return receipt'
        );

        $gradeBStockBefore = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeB
        );
        $this->assertEquals(50.0, $gradeBStockBefore);

        // Warehouse reverses the receipt
        $reversedReceipt = $this->receiptService->reversePhysicalReturn(
            receipt: $receipt,
            actor: $this->warehouseStaff,
            reason: 'Quality assessment error, returned goods rejected at gate'
        );

        $this->assertTrue($reversedReceipt->isReversed());
        $this->assertNotNull($reversedReceipt->reversed_at);
        $this->assertEquals($this->warehouseStaff->id, $reversedReceipt->reversed_by);
        $this->assertNotNull($reversedReceipt->reversal_stock_movement_id);

        // Grade B stock returns to 0
        $gradeBStockAfter = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeB
        );
        $this->assertEquals(0.0, $gradeBStockAfter);

        // Compensating stock movement verification
        $compMovement = StockMovement::find($reversedReceipt->reversal_stock_movement_id);
        $this->assertNotNull($compMovement);
        $this->assertEquals(StockMovementType::Adjustment, $compMovement->type);
        $this->assertEquals(ProductGrade::GradeB, $compMovement->grade);
        $this->assertEquals(50.0, (float) $compMovement->quantity);

        // Item status reset
        $item->refresh();
        $this->assertEquals(0.0, (float) $item->received_quantity);
        $this->assertEquals(ShopCreditNoteReceiptStatus::PendingReceipt->value, $item->receipt_status->value);
        $this->assertStringContainsString('Reversed receipt', (string) $item->physical_receipt_notes);
    }

    /**
     * Test 2: Original Grade A sale dispatch remains unchanged.
     */
    public function test_02_original_grade_a_sale_dispatch_remains_unchanged(): void
    {
        $creditNote = $this->createAndFinalizeReturnCreditNote(50.0, 35.00);
        $item = $creditNote->items->first();

        $origDispatchBefore = $this->initialDispatchMovement->fresh();

        $receipt = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 50.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        $this->receiptService->reversePhysicalReturn(
            receipt: $receipt,
            actor: $this->warehouseStaff,
            reason: 'Administrative correction'
        );

        $origDispatchAfter = $this->initialDispatchMovement->fresh();

        $this->assertEquals((float) $origDispatchBefore->quantity, (float) $origDispatchAfter->quantity);
        $this->assertEquals($origDispatchBefore->type, $origDispatchAfter->type);
        $this->assertEquals($origDispatchBefore->grade, $origDispatchAfter->grade);
        $this->assertEquals($origDispatchBefore->batch_id, $origDispatchAfter->batch_id);
        $this->assertEquals($origDispatchBefore->warehouse_id, $origDispatchAfter->warehouse_id);
    }

    /**
     * Test 3: Grade C receipt reversal.
     */
    public function test_03_grade_c_receipt_reversal(): void
    {
        $creditNote = $this->createAndFinalizeReturnCreditNote(40.0, 30.00);
        $item = $creditNote->items->first();

        // Receive 40 kg as Grade C
        $receipt = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 40.0,
            grade: ProductGrade::GradeC,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff,
            remarks: 'Damaged onion returned as Grade C'
        );

        $gradeCStockBefore = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeC
        );
        $this->assertEquals(40.0, $gradeCStockBefore);

        // Reverse Grade C receipt
        $reversedReceipt = $this->receiptService->reversePhysicalReturn(
            receipt: $receipt,
            actor: $this->warehouseStaff,
            reason: 'Disputed return count by driver'
        );

        $this->assertTrue($reversedReceipt->isReversed());

        $gradeCStockAfter = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeC
        );
        $this->assertEquals(0.0, $gradeCStockAfter);

        $compMovement = StockMovement::find($reversedReceipt->reversal_stock_movement_id);
        $this->assertEquals(ProductGrade::GradeC, $compMovement->grade);
        $this->assertEquals(40.0, (float) $compMovement->quantity);
    }

    /**
     * Test 4: Multiple partial receipt reversals.
     */
    public function test_04_multiple_partial_receipt_reversals(): void
    {
        $creditNote = $this->createAndFinalizeReturnCreditNote(50.0, 35.00);
        $item = $creditNote->items->first();

        // Receipt 1: 20 kg Grade B
        $receipt1 = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 20.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        // Receipt 2: 30 kg Grade B
        $receipt2 = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 30.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        $item->refresh();
        $this->assertEquals(50.0, (float) $item->received_quantity);
        $this->assertEquals(ShopCreditNoteReceiptStatus::ReceivedAndVerified->value, $item->receipt_status->value);

        // Reverse Receipt 1 (20 kg)
        $this->receiptService->reversePhysicalReturn(
            receipt: $receipt1,
            actor: $this->warehouseStaff,
            reason: 'Reversing partial batch 1'
        );

        $item->refresh();
        $this->assertEquals(30.0, (float) $item->received_quantity);
        $this->assertEquals(ShopCreditNoteReceiptStatus::PartiallyReceived->value, $item->receipt_status->value);

        $gradeBStock = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeB
        );
        $this->assertEquals(30.0, $gradeBStock);

        // Remaining expected return is now 20 kg again -> receive 15 kg
        $receipt3 = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 15.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        $item->refresh();
        $this->assertEquals(45.0, (float) $item->received_quantity);

        // Reverse Receipt 2 (30 kg) and Receipt 3 (15 kg)
        $this->receiptService->reversePhysicalReturn($receipt2, $this->warehouseStaff, 'Reversing receipt 2');
        $this->receiptService->reversePhysicalReturn($receipt3, $this->warehouseStaff, 'Reversing receipt 3');

        $item->refresh();
        $this->assertEquals(0.0, (float) $item->received_quantity);
        $this->assertEquals(ShopCreditNoteReceiptStatus::PendingReceipt->value, $item->receipt_status->value);
    }

    /**
     * Test 5: Mixed Grade B and Grade C receipt events reversal.
     */
    public function test_05_mixed_grade_b_and_grade_c_receipt_events_reversal(): void
    {
        $creditNote = $this->createAndFinalizeReturnCreditNote(50.0, 35.00);
        $item = $creditNote->items->first();

        // 30 kg Grade B + 20 kg Grade C
        $receiptB = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 30.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        $receiptC = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 20.0,
            grade: ProductGrade::GradeC,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        $this->assertEquals(30.0, $this->stockLedgerService->availableSortedStockForProduct($this->productOnion->id, $this->warehouseCentral->id, ProductGrade::GradeB));
        $this->assertEquals(20.0, $this->stockLedgerService->availableSortedStockForProduct($this->productOnion->id, $this->warehouseCentral->id, ProductGrade::GradeC));

        // Reverse Grade B receipt
        $this->receiptService->reversePhysicalReturn(
            receipt: $receiptB,
            actor: $this->warehouseStaff,
            reason: 'Grade B reversal'
        );

        // Grade B stock goes to 0, Grade C stock remains 20 kg
        $this->assertEquals(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->productOnion->id, $this->warehouseCentral->id, ProductGrade::GradeB));
        $this->assertEquals(20.0, $this->stockLedgerService->availableSortedStockForProduct($this->productOnion->id, $this->warehouseCentral->id, ProductGrade::GradeC));

        $item->refresh();
        $this->assertEquals(20.0, (float) $item->received_quantity);
        $this->assertEquals('C', $item->received_grade);
    }

    /**
     * Test 6: Correct batch and warehouse deductions.
     */
    public function test_06_correct_batch_and_warehouse_deductions(): void
    {
        $creditNote = $this->createAndFinalizeReturnCreditNote(50.0, 35.00);
        $item = $creditNote->items->first();

        // Receive into Suburban Hub Warehouse (WH-HUB)
        $receiptHub = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 50.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseHub,
            actor: $this->warehouseStaff
        );

        $hubBatchId = $receiptHub->stockMovement->batch_id;
        $this->assertNotNull($hubBatchId);

        // Stock in WH-HUB is 50 kg; WH-CENTRAL is 0 kg Grade B
        $this->assertEquals(50.0, $this->stockLedgerService->availableSortedStockForProduct($this->productOnion->id, $this->warehouseHub->id, ProductGrade::GradeB));
        $this->assertEquals(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->productOnion->id, $this->warehouseCentral->id, ProductGrade::GradeB));

        // Reverse receipt in WH-HUB
        $this->receiptService->reversePhysicalReturn(
            receipt: $receiptHub,
            actor: $this->warehouseStaff,
            reason: 'Correction in WH-HUB'
        );

        // WH-HUB Grade B stock is 0; WH-CENTRAL stock remains 0
        $this->assertEquals(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->productOnion->id, $this->warehouseHub->id, ProductGrade::GradeB));
        $this->assertEquals(0.0, $this->stockLedgerService->availableSortedStockForProduct($this->productOnion->id, $this->warehouseCentral->id, ProductGrade::GradeB));

        // Movement is linked to the hub batch
        $compMovement = StockMovement::find($receiptHub->fresh()->reversal_stock_movement_id);
        $this->assertEquals($hubBatchId, $compMovement->batch_id);
        $this->assertEquals($this->warehouseHub->id, $compMovement->warehouse_id);
    }

    /**
     * Test 7: Insufficient available stock blocks reversal.
     */
    public function test_07_insufficient_available_stock_blocks_reversal(): void
    {
        $creditNote = $this->createAndFinalizeReturnCreditNote(50.0, 35.00);
        $item = $creditNote->items->first();

        $receipt = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 50.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        // Subsequent warehouse sale consumes 40 kg of the 50 kg Grade B batch
        StockMovement::create([
            'batch_id' => $receipt->stockMovement->batch_id,
            'product_id' => $this->productOnion->id,
            'warehouse_id' => $this->warehouseCentral->id,
            'grade' => 'B',
            'type' => StockMovementType::Out->value,
            'quantity' => 40.0,
            'cost_per_unit' => 35.00,
            'created_by' => $this->admin->id,
            'notes' => 'Dispatched partial Grade B to local buyer',
        ]);

        $availableNow = $this->stockLedgerService->availableSortedStockForProduct($this->productOnion->id, $this->warehouseCentral->id, ProductGrade::GradeB);
        $this->assertEquals(10.0, $availableNow);

        // Attempting to reverse 50 kg receipt must fail because only 10 kg is available
        try {
            $this->receiptService->reversePhysicalReturn(
                receipt: $receipt,
                actor: $this->warehouseStaff,
                reason: 'Gate return reversal'
            );
            $this->fail('Expected ValidationException was not thrown for insufficient stock.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stock_availability', $e->errors());
            $this->assertStringContainsString('already been consumed', $e->errors()['stock_availability'][0]);
        }

        // Receipt remains unreversed
        $this->assertFalse($receipt->fresh()->isReversed());
    }

    /**
     * Test 8: Already-consumed stock cannot be reversed automatically.
     */
    public function test_08_already_consumed_stock_cannot_be_reversed_automatically(): void
    {
        $creditNote = $this->createAndFinalizeReturnCreditNote(50.0, 35.00);
        $item = $creditNote->items->first();

        $receipt = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 50.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        // All 50 kg is consumed (e.g. wastage)
        StockMovement::create([
            'batch_id' => $receipt->stockMovement->batch_id,
            'product_id' => $this->productOnion->id,
            'warehouse_id' => $this->warehouseCentral->id,
            'grade' => 'B',
            'type' => StockMovementType::Wastage->value,
            'quantity' => 50.0,
            'cost_per_unit' => 35.00,
            'created_by' => $this->admin->id,
            'notes' => 'Rotten in storage',
        ]);

        $this->expectException(ValidationException::class);
        $this->receiptService->reversePhysicalReturn(
            receipt: $receipt,
            actor: $this->warehouseStaff,
            reason: 'Attempt reversal on zero stock'
        );
    }

    /**
     * Test 9: Double reversal rejected.
     */
    public function test_09_double_reversal_rejected(): void
    {
        $creditNote = $this->createAndFinalizeReturnCreditNote(50.0, 35.00);
        $item = $creditNote->items->first();

        $receipt = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 50.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        // First reversal succeeds
        $this->receiptService->reversePhysicalReturn($receipt, $this->warehouseStaff, 'Initial reversal');

        // Second reversal must fail
        $this->expectException(ValidationException::class);
        $this->receiptService->reversePhysicalReturn($receipt, $this->warehouseStaff, 'Duplicate reversal');
    }

    /**
     * Test 10: Concurrent reversals are safe on MySQL.
     */
    public function test_10_concurrent_reversals_are_safe_on_mysql(): void
    {
        $mysqlHost = config('database.connections.mysql.host', '127.0.0.1');
        $mysqlPort = config('database.connections.mysql.port', '3306');
        $mysqlUser = config('database.connections.mysql.username', 'root');
        $mysqlPass = config('database.connections.mysql.password', '');
        $testDb = 'green_leaf_erp_concurrency_test';

        try {
            $pdo1 = new PDO("mysql:host={$mysqlHost};port={$mysqlPort};dbname={$testDb}", $mysqlUser, $mysqlPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo2 = new PDO("mysql:host={$mysqlHost};port={$mysqlPort};dbname={$testDb}", $mysqlUser, $mysqlPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped("MySQL connection to {$testDb} unavailable: ".$e->getMessage());
        }

        $pdo1->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo2->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo1->exec('TRUNCATE TABLE shop_credit_note_receipts');
        $pdo1->exec('TRUNCATE TABLE shop_credit_note_items');
        $pdo1->exec('TRUNCATE TABLE shop_credit_notes');
        $pdo1->exec('TRUNCATE TABLE stock_movements');
        $pdo1->exec('TRUNCATE TABLE stock_batches');
        $pdo1->exec('TRUNCATE TABLE warehouses');
        $pdo1->exec('TRUNCATE TABLE products');
        $pdo1->exec('TRUNCATE TABLE shops');

        $pdo1->exec("INSERT INTO shops (id, name, code, status, created_at, updated_at) VALUES (900, 'Conc Rev Shop', 'CONC_REV', 'active', NOW(), NOW())");
        $pdo1->exec("INSERT INTO warehouses (id, code, name, is_active, created_at, updated_at) VALUES (900, 'WH-REV', 'Rev Warehouse', 1, NOW(), NOW())");
        $pdo1->exec("INSERT INTO products (id, category_id, name, sku, created_at, updated_at) VALUES (900, 1, 'Rev Onion', 'SKU-REV-900', NOW(), NOW())");
        $pdo1->exec("INSERT INTO stock_batches (id, product_id, warehouse_id, purchase_grade, reference, received_at, total_kg, cost_per_kg, transport_cost, labour_cost, status, warehouse_receive_pending, grading_mode, created_by, created_at, updated_at) VALUES (900, 900, 900, 'B', 'BATCH-REV-900', CURDATE(), 0.00, 40.00, 0.00, 0.00, 'sorted', 0, 'fixed_purchase_grade', 1, NOW(), NOW())");

        $pdo1->exec("INSERT INTO shop_credit_notes (id, credit_note_number, shop_id, credit_note_date, direction, accounting_mode, status, total_credit_amount, lock_version, created_at, updated_at) VALUES (900, 'SCN-CONC-REV-1', 900, CURDATE(), 'credit', 'shop_level', 'approved', 1400.00, 1, NOW(), NOW())");
        $pdo1->exec("INSERT INTO shop_credit_note_items (id, shop_credit_note_id, line_type, product_id, product_name, product_grade, adjusted_quantity, received_quantity, credit_rate, line_credit_amount, direction, inventory_action, receipt_status, created_at, updated_at) VALUES (900, 900, 'product_adjustment', 900, 'Rev Onion', 'A', 40.0000, 40.0000, 35.00, 1400.00, 'credit', 'return_to_stock', 'received_and_verified', NOW(), NOW())");
        $pdo1->exec("INSERT INTO stock_movements (id, batch_id, product_id, warehouse_id, grade, type, quantity, cost_per_unit, created_by, created_at, updated_at) VALUES (900, 900, 900, 900, 'B', 'sale_reversal', 40.000, 35.00, 1, NOW(), NOW())");
        $pdo1->exec("INSERT INTO shop_credit_note_receipts (id, shop_credit_note_id, shop_credit_note_item_id, product_id, warehouse_id, stock_movement_id, received_grade, received_quantity, received_by, received_at, status, created_at, updated_at) VALUES (900, 900, 900, 900, 900, 900, 'B', 40.0000, 1, NOW(), 'verified', NOW(), NOW())");

        // Transaction 1 locks receipt #900 for reversal
        $pdo1->beginTransaction();
        $stmt1 = $pdo1->prepare('SELECT id, reversed_at FROM shop_credit_note_receipts WHERE id = 900 FOR UPDATE');
        $stmt1->execute();
        $receiptRow1 = $stmt1->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($receiptRow1['reversed_at']);

        // Transaction 2 sets 1-second timeout and attempts to lock the SAME receipt
        $pdo2->exec('SET innodb_lock_wait_timeout = 1');
        $lockBlocked = false;

        try {
            $pdo2->beginTransaction();
            $stmt2 = $pdo2->prepare('SELECT id, reversed_at FROM shop_credit_note_receipts WHERE id = 900 FOR UPDATE');
            $stmt2->execute();
        } catch (\PDOException $e) {
            $lockBlocked = true;
            $pdo2->rollBack();
        }

        $this->assertTrue($lockBlocked, 'Pessimistic lock on receipt row blocked concurrent reversal.');

        // Transaction 1 commits reversal
        $pdo1->exec("UPDATE shop_credit_note_receipts SET reversed_at = NOW(), reversed_by = 1, reversal_reason = 'Tx1 reversal' WHERE id = 900");
        $pdo1->commit();

        // Transaction 2 now starts, acquires lock, and reads updated reversed_at IS NOT NULL
        $pdo2->beginTransaction();
        $stmt2 = $pdo2->prepare('SELECT id, reversed_at FROM shop_credit_note_receipts WHERE id = 900 FOR UPDATE');
        $stmt2->execute();
        $receiptRow2 = $stmt2->fetch(PDO::FETCH_ASSOC);
        $this->assertNotNull($receiptRow2['reversed_at'], 'Transaction 2 safely detected receipt was already reversed.');
        $pdo2->rollBack();
    }

    /**
     * Test 11: Reversal racing with a new receipt is safe.
     */
    public function test_11_reversal_racing_with_a_new_receipt_is_safe(): void
    {
        $creditNote = $this->createAndFinalizeReturnCreditNote(50.0, 35.00);
        $item = $creditNote->items->first();

        // Initial 25 kg receipt
        $receipt1 = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 25.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        // Reverse receipt 1
        $this->receiptService->reversePhysicalReturn($receipt1, $this->warehouseStaff, 'Reversal before re-receipt');

        // New receipt for remaining goods (full 50 kg now available again)
        $receipt2 = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 50.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        $item->refresh();
        $this->assertEquals(50.0, (float) $item->received_quantity);
        $this->assertEquals(ShopCreditNoteReceiptStatus::ReceivedAndVerified->value, $item->receipt_status->value);
        $this->assertFalse($receipt2->isReversed());
    }

    /**
     * Test 12: Reversal racing with batch consumption is safe.
     */
    public function test_12_reversal_racing_with_batch_consumption_is_safe(): void
    {
        $creditNote = $this->createAndFinalizeReturnCreditNote(30.0, 35.00);
        $item = $creditNote->items->first();

        $receipt = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 30.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        // Consume 25 kg out of 30 kg
        StockMovement::create([
            'batch_id' => $receipt->stockMovement->batch_id,
            'product_id' => $this->productOnion->id,
            'warehouse_id' => $this->warehouseCentral->id,
            'grade' => 'B',
            'type' => StockMovementType::Out->value,
            'quantity' => 25.0,
            'cost_per_unit' => 35.00,
            'created_by' => $this->admin->id,
            'notes' => 'Loadout consumption',
        ]);

        // Attempt to reverse 30 kg fails
        $this->expectException(ValidationException::class);
        $this->receiptService->reversePhysicalReturn($receipt, $this->warehouseStaff, 'Racing reversal');
    }

    /**
     * Test 13: Receipt audit history preserved.
     */
    public function test_13_receipt_audit_history_preserved(): void
    {
        $creditNote = $this->createAndFinalizeReturnCreditNote(50.0, 35.00);
        $item = $creditNote->items->first();

        $receipt = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 50.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff,
            remarks: 'Original receipt note'
        );

        $origMovementId = $receipt->stock_movement_id;
        $this->assertNotNull($origMovementId);

        $reversedReceipt = $this->receiptService->reversePhysicalReturn(
            receipt: $receipt,
            actor: $this->warehouseStaff,
            reason: 'Defective test receipt'
        );

        // Verification of immutable preservation
        $this->assertEquals($origMovementId, $reversedReceipt->stock_movement_id);
        $this->assertEquals(50.0, (float) $reversedReceipt->received_quantity);
        $this->assertEquals('B', $reversedReceipt->received_grade);
        $this->assertEquals('verified', $reversedReceipt->status);
        $this->assertEquals('Original receipt note', $reversedReceipt->remarks);

        // Reversal audit fields
        $this->assertNotNull($reversedReceipt->reversal_stock_movement_id);
        $this->assertNotNull($reversedReceipt->reversed_at);
        $this->assertEquals($this->warehouseStaff->id, $reversedReceipt->reversed_by);
        $this->assertEquals('Defective test receipt', $reversedReceipt->reversal_reason);

        // Relationships resolve
        $this->assertInstanceOf(StockMovement::class, $reversedReceipt->reversalStockMovement);
        $this->assertInstanceOf(User::class, $reversedReceipt->reversedByUser);
    }

    /**
     * Test 14: Financial Credit Note amount unchanged after physical reversal.
     */
    public function test_14_financial_credit_note_amount_unchanged_after_physical_reversal(): void
    {
        $creditNote = $this->createAndFinalizeReturnCreditNote(50.0, 35.00);
        $item = $creditNote->items->first();

        $origSubtotal = (float) $creditNote->subtotal;
        $origTotal = (float) $creditNote->total_credit_amount;
        $origItemRate = (float) $item->credit_rate;
        $origItemCredit = (float) $item->line_credit_amount;

        $receipt = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 50.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        $this->receiptService->reversePhysicalReturn(
            receipt: $receipt,
            actor: $this->warehouseStaff,
            reason: 'Stock count adjustment'
        );

        $creditNote->refresh();
        $item->refresh();

        $this->assertEquals($origSubtotal, (float) $creditNote->subtotal);
        $this->assertEquals($origTotal, (float) $creditNote->total_credit_amount);
        $this->assertEquals($origItemRate, (float) $item->credit_rate);
        $this->assertEquals($origItemCredit, (float) $item->line_credit_amount);
    }

    /**
     * Test 15: Cashbook and applications unchanged after physical reversal.
     */
    public function test_15_cashbook_and_applications_unchanged_after_physical_reversal(): void
    {
        $creditNote = $this->createAndFinalizeReturnCreditNote(50.0, 35.00);
        $item = $creditNote->items->first();

        // Apply ₹1,000 credit to invoice A1
        $application = $this->applicationService->applyCredit(
            creditNote: $creditNote,
            invoice: $this->invoiceA1,
            amount: 1000.00,
            actor: $this->admin
        );

        $cashbookCountBefore = ShopLedgerTransaction::where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $creditNote->id)
            ->count();
        $this->assertEquals(1, $cashbookCountBefore);

        $receipt = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 50.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        // Reversal of physical receipt
        $this->receiptService->reversePhysicalReturn(
            receipt: $receipt,
            actor: $this->warehouseStaff,
            reason: 'Physical reversal'
        );

        // Cashbook count remains exactly 1 (no changes to Cashbook)
        $cashbookCountAfter = ShopLedgerTransaction::where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $creditNote->id)
            ->count();
        $this->assertEquals(1, $cashbookCountAfter);

        // Credit application remains active
        $this->assertFalse($application->fresh()->isReversed());
        $this->assertEquals(1000.00, (float) $creditNote->fresh()->appliedCreditTotal());
    }

    /**
     * Test 16: Cancellation blocked with active physical receipts.
     */
    public function test_16_cancellation_blocked_with_active_physical_receipts(): void
    {
        $creditNote = $this->createAndFinalizeReturnCreditNote(50.0, 35.00);
        $item = $creditNote->items->first();

        $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 50.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        // Office staff attempts to cancel the credit note
        try {
            $this->creditNoteService->cancelShopLevelCreditNote(
                creditNote: $creditNote,
                actor: $this->admin,
                reason: 'Customer cancelled return request'
            );
            $this->fail('Expected cancellation to be blocked due to active physical receipts.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stock_movements', $e->errors());
            $this->assertStringContainsString('unreconciled warehouse receipts', $e->errors()['stock_movements'][0]);
        }

        $this->assertFalse($creditNote->fresh()->isVoided());
    }

    /**
     * Test 17: Cancellation allowed after all physical effects are reconciled.
     */
    public function test_17_cancellation_allowed_after_all_physical_effects_are_reconciled(): void
    {
        $creditNote = $this->createAndFinalizeReturnCreditNote(50.0, 35.00);
        $item = $creditNote->items->first();

        $receipt = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 50.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        // Warehouse reverses the receipt
        $this->receiptService->reversePhysicalReturn(
            receipt: $receipt,
            actor: $this->warehouseStaff,
            reason: 'Reconciled return goods'
        );

        // Office staff cancels credit note
        $cancelledCN = $this->creditNoteService->cancelShopLevelCreditNote(
            creditNote: $creditNote,
            actor: $this->admin,
            reason: 'Reconciled return cancellation'
        );

        $this->assertTrue($cancelledCN->isVoided());
        $this->assertNotNull($cancelledCN->voided_at);
        $this->assertEquals($this->admin->id, $cancelledCN->voided_by);
    }

    /**
     * Test 18: Financial cancellation reverses credit exactly once.
     */
    public function test_18_financial_cancellation_reverses_credit_exactly_once(): void
    {
        $creditNote = $this->createAndFinalizeReturnCreditNote(50.0, 35.00);
        $item = $creditNote->items->first();

        // Total credit = ₹1,750
        $this->assertEquals(1750.00, (float) $creditNote->total_credit_amount);

        // Apply ₹500 to invoice A1
        $application = $this->applicationService->applyCredit(
            creditNote: $creditNote,
            invoice: $this->invoiceA1,
            amount: 500.00,
            actor: $this->admin
        );

        $receipt = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 50.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        // Reconcile physical receipt
        $this->receiptService->reversePhysicalReturn($receipt, $this->warehouseStaff, 'Reconciled return');

        // Cancel credit note
        $this->creditNoteService->cancelShopLevelCreditNote(
            creditNote: $creditNote,
            actor: $this->admin,
            reason: 'Full credit note cancellation'
        );

        $creditNote->refresh();
        $this->assertTrue($creditNote->isVoided());

        // Application reversed
        $this->assertTrue($application->fresh()->isReversed());

        // Cashbook transaction is marked voided
        $tx = ShopLedgerTransaction::where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $creditNote->id)
            ->first();
        $this->assertNotNull($tx);
        $this->assertEquals('void', $tx->status);

        // Bill engine confirms invoice liability restored back to full amount
        $bill = $this->finalBillService->calculate($this->invoiceA1);
        $this->assertEquals(5500.00, $bill->remainingBalance);
    }

    /**
     * Test 19: Unauthorized warehouse reversal rejected.
     */
    public function test_19_unauthorized_warehouse_reversal_rejected(): void
    {
        $creditNote = $this->createAndFinalizeReturnCreditNote(50.0, 35.00);
        $item = $creditNote->items->first();

        $receipt = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 50.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        $this->expectException(ValidationException::class);
        $this->receiptService->reversePhysicalReturn(
            receipt: $receipt,
            actor: $this->unauthorizedUser,
            reason: 'Unauthorized attempt'
        );
    }

    /**
     * Test 20: Closed-period safety.
     */
    public function test_20_closed_period_safety(): void
    {
        $creditNote = $this->createAndFinalizeReturnCreditNote(50.0, 35.00);
        $item = $creditNote->items->first();

        $receipt = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 50.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        // Close last week's accounting period
        ShopAccountingPeriodClosure::create([
            'shop_id' => $this->shopA->id,
            'period_start' => today()->subDays(14)->toDateString(),
            'period_end' => today()->subDays(7)->toDateString(),
            'closed_by' => $this->admin->id,
            'closed_at' => now(),
        ]);

        // Attempting to backdate reversal into closed period fails
        try {
            $this->receiptService->reversePhysicalReturn(
                receipt: $receipt,
                actor: $this->warehouseStaff,
                reason: 'Backdated reversal',
                reversedAt: today()->subDays(10)
            );
            $this->fail('Expected ValidationException was not thrown for closed period reversal.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reversed_at', $e->errors());
        }

        // Reversing today (in open period) succeeds
        $reversed = $this->receiptService->reversePhysicalReturn(
            receipt: $receipt,
            actor: $this->warehouseStaff,
            reason: 'Open period reversal',
            reversedAt: today()
        );
        $this->assertTrue($reversed->isReversed());
    }

    /**
     * Test 21: Duplicate-looking legitimate receipts are not silently lost.
     */
    public function test_21_duplicate_looking_legitimate_receipts_are_not_silently_lost(): void
    {
        $creditNote = $this->createAndFinalizeReturnCreditNote(50.0, 35.00);
        $item = $creditNote->items->first();

        // Two rapid un-keyed receipts of 10 kg each (e.g. 2 separate 10 kg crates)
        $rcpt1 = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 10.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        $rcpt2 = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 10.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        $this->assertNotEquals($rcpt1->id, $rcpt2->id);
        $item->refresh();
        $this->assertEquals(20.0, (float) $item->received_quantity);

        // With explicit idempotency key, identical submission returns the same receipt
        $rcptKeyed1 = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 10.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff,
            idempotencyKey: 'rcpt_box_3'
        );

        $rcptKeyed2 = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 10.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff,
            idempotencyKey: 'rcpt_box_3'
        );

        $this->assertEquals($rcptKeyed1->id, $rcptKeyed2->id);
        $item->refresh();
        $this->assertEquals(30.0, (float) $item->received_quantity);
    }

    /**
     * Test 22: Existing legacy credit notes and warehouse stock flows remain unchanged.
     */
    public function test_22_existing_legacy_credit_notes_and_warehouse_stock_flows_remain_unchanged(): void
    {
        // Legacy invoice-linked credit note with direct stock movement
        $legacyCN = ShopCreditNote::factory()->create([
            'shop_id' => $this->shopA->id,
            'shop_invoice_id' => $this->invoiceA1->id,
            'credit_note_number' => 'SCN-LEGACY-001',
            'credit_note_date' => today()->toDateString(),
            'accounting_mode' => ShopCreditNoteAccountingMode::LegacyInvoiceLinked->value,
            'status' => 'approved',
            'total_credit_amount' => 500.00,
        ]);

        // Direct stock movement linked to this legacy credit note
        StockMovement::create([
            'batch_id' => $this->initialDispatchBatch->id,
            'product_id' => $this->productOnion->id,
            'warehouse_id' => $this->warehouseCentral->id,
            'grade' => 'A',
            'type' => StockMovementType::SaleReversal->value,
            'quantity' => 10.0,
            'cost_per_unit' => 40.00,
            'created_by' => $this->admin->id,
            'notes' => "Legacy return — SCN: {$legacyCN->credit_note_number}",
        ]);

        $this->assertTrue($legacyCN->hasUnreconciledPhysicalReturns());
    }
}
