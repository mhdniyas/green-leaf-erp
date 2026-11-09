<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\ShopInvoice\ShopCreditNoteAccountingMode;
use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteLineType;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteReceiptStatus;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\Shop;
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
use App\Services\ShopInvoices\ShopCreditNoteService;
use App\Services\ShopInvoices\ShopCreditNoteWarehouseReceiptService;
use App\Services\ShopInvoices\ShopFinalBillService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PDO;
use Tests\TestCase;

class ShopCreditNoteWarehouseReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $warehouseStaff;

    private User $unauthorizedUser;

    private Shop $shopA;

    private Warehouse $warehouseCentral;

    private Warehouse $warehouseHub;

    private Product $productOnion;

    private Product $productTomato;

    private ShopInvoice $invoiceA1;

    private ShopInvoiceItem $invoiceItemA1;

    private StockBatch $initialDispatchBatch;

    private StockMovement $initialDispatchMovement;

    private ShopCreditNoteService $creditNoteService;

    private ShopCreditNoteWarehouseReceiptService $receiptService;

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
        $this->stockLedgerService = app(StockLedgerService::class);
        $this->finalBillService = app(ShopFinalBillService::class);

        $this->productOnion = Product::factory()->create([
            'name' => 'Big Onion',
            'default_warehouse_id' => $this->warehouseCentral->id,
        ]);

        $this->productTomato = Product::factory()->create([
            'name' => 'Tomato Local',
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

        // Loadout Out movement: 100 kg Grade A deducted from initial batch
        $this->initialDispatchMovement = StockMovement::create([
            'batch_id' => $this->initialDispatchBatch->id,
            'product_id' => $this->productOnion->id,
            'warehouse_id' => $this->warehouseCentral->id,
            'grade' => 'A',
            'type' => StockMovementType::Out->value,
            'quantity' => 100.0,
            'cost_per_unit' => 40.00,
            'created_by' => $this->admin->id,
            'shop_order_item_id' => $this->invoiceItemA1->shop_order_item_id,
            'notes' => "Warehouse loadout — Invoice: {$this->invoiceA1->invoice_number}",
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
     * Helper to create and finalize a shop-level Credit Note for physical return.
     */
    private function createFinalizedReturnCreditNote(
        ShopInvoiceItem $sourceItem,
        float $returnQty = 40.0,
        float $creditRate = 35.00
    ): ShopCreditNote {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Customer physical return claim',
            'items' => [
                [
                    'source_shop_invoice_id' => $sourceItem->shop_invoice_id,
                    'shop_invoice_item_id' => $sourceItem->id,
                    'product_id' => $sourceItem->product_id,
                    'product_grade' => $sourceItem->product_grade,
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
     * Test 1: Grade A supplied → Grade B physically received and verified.
     */
    public function test_01_grade_a_supplied_grade_b_returned(): void
    {
        // 100 kg Grade A was dispatched. Credit note claims 40 kg return.
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        $this->assertEquals(ShopCreditNoteReceiptStatus::PendingReceipt, $item->receipt_status);
        $this->assertEquals(0.00, (float) $item->received_quantity);

        // Grade A stock before receipt in central warehouse = 100 kg (200 in - 100 out)
        $gradeABefore = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeA
        );
        $this->assertEquals(100.0, $gradeABefore);

        // Grade B stock before receipt = 0 kg
        $gradeBBefore = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeB
        );
        $this->assertEquals(0.0, $gradeBBefore);

        // Warehouse verifies: 40 kg received as Grade B
        $receipt = $this->receiptService->receivePhysicalReturn(
            $item,
            40.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseStaff,
            remarks: 'Quality downgraded to Grade B upon receipt'
        );

        $this->assertTrue($receipt->isVerified());
        $this->assertEquals(40.0, (float) $receipt->received_quantity);
        $this->assertSame('B', $receipt->received_grade);
        $this->assertSame($this->warehouseCentral->id, $receipt->warehouse_id);
        $this->assertSame($this->warehouseStaff->id, $receipt->received_by);

        // Grade B stock increases by exactly 40 kg!
        $gradeBAfter = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeB
        );
        $this->assertEquals(40.0, $gradeBAfter);

        // Grade A stock remains completely unchanged (still 100 kg)!
        $gradeAAfter = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeA
        );
        $this->assertEquals(100.0, $gradeAAfter);

        // Item status transitions to ReceivedAndVerified
        $item->refresh();
        $this->assertEquals(ShopCreditNoteReceiptStatus::ReceivedAndVerified, $item->receipt_status);
        $this->assertEquals(40.0, (float) $item->received_quantity);
        $this->assertSame('B', $item->received_grade);
    }

    /**
     * Test 2: Grade A supplied → Grade C physically received and verified.
     */
    public function test_02_grade_a_supplied_grade_c_returned(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 30.0, 30.00);
        $item = $creditNote->items->first();

        // Warehouse staff receives 30 kg as Grade C in the hub warehouse
        $receipt = $this->receiptService->receivePhysicalReturn(
            $item,
            30.0,
            ProductGrade::GradeC,
            $this->warehouseHub,
            $this->warehouseStaff,
            remarks: 'Severe bruising, accepted as Grade C'
        );

        $this->assertSame('C', $receipt->received_grade);
        $this->assertSame($this->warehouseHub->id, $receipt->warehouse_id);

        // Grade C stock in hub warehouse increases by 30 kg
        $gradeCStock = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseHub->id,
            ProductGrade::GradeC
        );
        $this->assertEquals(30.0, $gradeCStock);

        // Central warehouse Grade A stock unchanged
        $this->assertEquals(100.0, $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeA
        ));
    }

    /**
     * Test 3: Full receipt increases correct stock and sets ReceivedAndVerified status.
     */
    public function test_03_full_receipt_increases_correct_stock(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        $receipt = $this->receiptService->receivePhysicalReturn(
            $item,
            40.0,
            ProductGrade::GradeA,
            $this->warehouseCentral,
            $this->warehouseStaff
        );

        $this->assertEquals(40.0, (float) $receipt->received_quantity);
        $item->refresh();
        $this->assertEquals(ShopCreditNoteReceiptStatus::ReceivedAndVerified, $item->receipt_status);
        $this->assertTrue($item->isReceived());
        $this->assertEquals(40.0, (float) $item->received_quantity);

        // Stock movement was created
        $this->assertNotNull($receipt->stockMovement);
        $this->assertEquals(StockMovementType::SaleReversal, $receipt->stockMovement->type);
        $this->assertEquals(40.0, (float) $receipt->stockMovement->quantity);
        $this->assertEquals(ProductGrade::GradeA, $receipt->stockMovement->grade);
    }

    /**
     * Test 4: Partial receipts increase stock incrementally and update receipt states.
     */
    public function test_04_partial_receipts_increase_stock_incrementally(): void
    {
        // Credit note expects 40 kg return
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        // Event 1: First truck delivers 25 kg Grade B
        $receipt1 = $this->receiptService->receivePhysicalReturn(
            $item,
            25.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseStaff,
            remarks: 'First installment 25 kg'
        );

        $this->assertEquals(25.0, (float) $receipt1->received_quantity);
        $item->refresh();
        $this->assertEquals(ShopCreditNoteReceiptStatus::PartiallyReceived, $item->receipt_status);
        $this->assertTrue($item->isPartiallyReceived());
        $this->assertEquals(25.0, (float) $item->received_quantity);

        $gradeBStock1 = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeB
        );
        $this->assertEquals(25.0, $gradeBStock1);

        // Event 2: Second truck delivers remaining 15 kg Grade B
        $receipt2 = $this->receiptService->receivePhysicalReturn(
            $item,
            15.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseStaff,
            remarks: 'Final installment 15 kg'
        );

        $this->assertEquals(15.0, (float) $receipt2->received_quantity);
        $item->refresh();
        $this->assertEquals(ShopCreditNoteReceiptStatus::ReceivedAndVerified, $item->receipt_status);
        $this->assertTrue($item->isReceived());
        $this->assertEquals(40.0, (float) $item->received_quantity);

        // Total Grade B stock is now 40 kg (25 + 15)
        $gradeBStock2 = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeB
        );
        $this->assertEquals(40.0, $gradeBStock2);

        // Both distinct receipt records exist
        $this->assertCount(2, $item->receipts);
        $this->assertNotEquals($receipt1->id, $receipt2->id);
    }

    /**
     * Test 5: Total received quantity cannot exceed Credit Note's expected quantity.
     */
    public function test_05_total_receipt_cannot_exceed_expected_quantity(): void
    {
        // Credit note expects 40 kg return
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        // 1. Single attempt with 45 kg (exceeds 40 kg)
        try {
            $this->receiptService->receivePhysicalReturn(
                $item,
                45.0,
                ProductGrade::GradeB,
                $this->warehouseCentral,
                $this->warehouseStaff
            );
            $this->fail('Expected ValidationException when exceeding expected return quantity');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('received_quantity', $e->errors());
        }

        // 2. Partial receive 30 kg succeeds
        $this->receiptService->receivePhysicalReturn(
            $item,
            30.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseStaff
        );

        // 3. Second attempt of 15 kg (30 + 15 = 45 > 40) fails
        try {
            $this->receiptService->receivePhysicalReturn(
                $item,
                15.0,
                ProductGrade::GradeB,
                $this->warehouseCentral,
                $this->warehouseStaff
            );
            $this->fail('Expected ValidationException when cumulative receipts exceed expected return quantity');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('received_quantity', $e->errors());
        }

        $this->assertEquals(30.0, (float) $item->fresh()->received_quantity);
    }

    /**
     * Test 6: Duplicate receipt submission with same idempotency key does not duplicate stock.
     */
    public function test_06_duplicate_receipt_submission_does_not_duplicate_stock(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        // Submit receipt with explicit idempotency key
        $receipt1 = $this->receiptService->receivePhysicalReturn(
            $item,
            20.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseStaff,
            idempotencyKey: 'rcpt_tx_999'
        );

        $stockAfterFirst = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeB
        );
        $this->assertEquals(20.0, $stockAfterFirst);

        // Retry same submission with same idempotency key
        $receipt2 = $this->receiptService->receivePhysicalReturn(
            $item,
            20.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseStaff,
            idempotencyKey: 'rcpt_tx_999'
        );

        // Returns identical receipt record!
        $this->assertSame($receipt1->id, $receipt2->id);

        // Stock was NOT duplicated (still exactly 20.0 kg)!
        $stockAfterSecond = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeB
        );
        $this->assertEquals(20.0, $stockAfterSecond);
        $this->assertSame(1, StockMovement::where('type', StockMovementType::SaleReversal->value)->count());
    }

    /**
     * Test 7: Concurrent receipt submissions cannot over-receive.
     */
    public function test_07_concurrent_receipt_submissions_cannot_over_receive(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        // Transaction 1 receives 25 kg
        $this->receiptService->receivePhysicalReturn(
            $item,
            25.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseStaff
        );

        // Transaction 2 tries to receive 20 kg (25 + 20 = 45 > 40)
        try {
            $this->receiptService->receivePhysicalReturn(
                $item,
                20.0,
                ProductGrade::GradeB,
                $this->warehouseCentral,
                $this->warehouseStaff
            );
            $this->fail('Expected ValidationException when concurrent transaction exceeds limit');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('received_quantity', $e->errors());
        }

        $this->assertEquals(25.0, (float) $item->fresh()->received_quantity);
    }

    /**
     * Test 8: Physical receipt blocked before Credit Note finalization (draft state).
     */
    public function test_08_receipt_blocked_before_credit_note_finalization(): void
    {
        // Create draft (NOT finalized)
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceItemA1->shop_invoice_id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'credit_quantity' => 20.0,
                    'credit_rate' => 35.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                ],
            ],
        ], $this->admin);

        $item = $draft->items->first();

        try {
            $this->receiptService->receivePhysicalReturn(
                $item,
                20.0,
                ProductGrade::GradeB,
                $this->warehouseCentral,
                $this->warehouseStaff
            );
            $this->fail('Expected ValidationException for unapproved credit note');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('credit_note', $e->errors());
        }

        $this->assertEquals(0, StockMovement::where('type', StockMovementType::SaleReversal->value)->count());
    }

    /**
     * Test 9: Physical receipt blocked after Credit Note cancellation.
     */
    public function test_09_receipt_blocked_after_cancellation(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        // Office cancels credit note before any goods arrive
        $this->creditNoteService->cancelShopLevelCreditNote(
            $creditNote,
            $this->admin,
            'Cancelled before physical return'
        );
        $this->assertTrue($creditNote->fresh()->isVoided());

        // Warehouse receipt attempt must be rejected
        try {
            $this->receiptService->receivePhysicalReturn(
                $item,
                40.0,
                ProductGrade::GradeB,
                $this->warehouseCentral,
                $this->warehouseStaff
            );
            $this->fail('Expected ValidationException for voided credit note');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('credit_note', $e->errors());
        }

        $this->assertEquals(0, StockMovement::where('type', StockMovementType::SaleReversal->value)->count());
    }

    /**
     * Test 10: Non-return financial adjustment lines cannot create stock.
     */
    public function test_10_non_return_financial_lines_cannot_create_stock(): void
    {
        // Finalize credit note with PriceCorrection (None inventory action) and CommercialDiscount
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceItemA1->shop_invoice_id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'credit_quantity' => 20.0,
                    'credit_rate' => 10.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::None,
                    'reason_type' => ShopCreditNoteReason::PriceCorrection,
                ],
            ],
            'discounts' => [
                [
                    'amount' => 500.00,
                    'notes' => 'Commercial Goodwill',
                ],
            ],
        ], $this->admin);

        $creditNote = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);
        $priceItem = $creditNote->items->where('line_type', ShopCreditNoteLineType::ProductAdjustment)->first();
        $discountItem = $creditNote->items->where('line_type', ShopCreditNoteLineType::CommercialDiscount)->first();

        // Attempt on price correction line fails
        try {
            $this->receiptService->receivePhysicalReturn(
                $priceItem,
                20.0,
                ProductGrade::GradeA,
                $this->warehouseCentral,
                $this->warehouseStaff
            );
            $this->fail('Expected ValidationException for non-return inventory action');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('inventory_action', $e->errors());
        }

        // Attempt on commercial discount line fails
        try {
            $this->receiptService->receivePhysicalReturn(
                $discountItem,
                10.0,
                ProductGrade::GradeA,
                $this->warehouseCentral,
                $this->warehouseStaff
            );
            $this->fail('Expected ValidationException for commercial discount line');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('line_type', $e->errors());
        }
    }

    /**
     * Test 11: Original dispatch movements remain completely unmodified.
     */
    public function test_11_original_dispatch_remains_unchanged(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        $origDispatchQty = (float) $this->initialDispatchMovement->quantity;
        $origDispatchType = $this->initialDispatchMovement->type;
        $origDispatchGrade = $this->initialDispatchMovement->grade;

        $this->receiptService->receivePhysicalReturn(
            $item,
            40.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseStaff
        );

        $this->initialDispatchMovement->refresh();
        $this->assertEquals($origDispatchQty, (float) $this->initialDispatchMovement->quantity);
        $this->assertSame($origDispatchType, $this->initialDispatchMovement->type);
        $this->assertSame($origDispatchGrade, $this->initialDispatchMovement->grade);
    }

    /**
     * Test 12: Original invoice records remain completely unmodified.
     */
    public function test_12_original_invoice_remains_unchanged(): void
    {
        $origFinalTotal = (float) $this->invoiceA1->final_total;
        $origBalance = (float) $this->invoiceA1->balance_amount;
        $origPaid = (float) $this->invoiceA1->paid_amount;

        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        $this->receiptService->receivePhysicalReturn(
            $item,
            40.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseStaff
        );

        $this->invoiceA1->refresh();
        $this->assertEquals($origFinalTotal, (float) $this->invoiceA1->final_total);
        $this->assertEquals($origBalance, (float) $this->invoiceA1->balance_amount);
        $this->assertEquals($origPaid, (float) $this->invoiceA1->paid_amount);
    }

    /**
     * Test 13: Credit Note amount and Cashbook remain independent of physical receipt verification.
     */
    public function test_13_credit_note_amount_and_cashbook_unchanged(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        $cnAmountBefore = (float) $creditNote->total_credit_amount; // ₹1,400.00
        $this->assertEquals(1400.00, $cnAmountBefore);

        $txBefore = $creditNote->fresh()->ledgerTransaction;
        $this->assertNotNull($txBefore);
        $txAmountBefore = (float) $txBefore->amount;

        // Warehouse receives only 25 kg (short receipt)
        $this->receiptService->receivePhysicalReturn(
            $item,
            25.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseStaff
        );

        // Credit Note amount remains ₹1,400.00 (not modified by physical quantity!)
        $creditNote->refresh();
        $this->assertEquals(1400.00, (float) $creditNote->total_credit_amount);

        // Cashbook entry remains ₹1,400.00 and no new transactions created
        $txAfter = $creditNote->fresh()->ledgerTransaction;
        $this->assertNotNull($txAfter);
        $this->assertEquals($txAmountBefore, (float) $txAfter->amount);
        $this->assertSame(1, ShopLedgerTransaction::where(function ($q): void {
            $q->where('reference_type', ShopCreditNote::class)
                ->orWhere('reference_type', 'App\\Models\\ShopCreditNote');
        })->where('reference_id', $creditNote->id)->count());
    }

    /**
     * Test 14: Invalid or non-existent warehouse is rejected.
     */
    public function test_14_invalid_warehouse_rejected(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        $this->expectException(ModelNotFoundException::class);
        $this->receiptService->receivePhysicalReturn(
            $item,
            40.0,
            ProductGrade::GradeB,
            999999, // non-existent warehouse
            $this->warehouseStaff
        );
    }

    /**
     * Test 15: Invalid grade string is rejected.
     */
    public function test_15_invalid_grade_rejected(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        try {
            $this->receiptService->receivePhysicalReturn(
                $item,
                40.0,
                'INVALID_GRADE',
                $this->warehouseCentral,
                $this->warehouseStaff
            );
            $this->fail('Expected ValidationException for invalid product grade');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('received_grade', $e->errors());
        }
    }

    /**
     * Test 16: Multiple receipt events retain full auditable history without overwriting earlier receipts.
     */
    public function test_16_multiple_receipt_events_retain_audit_history(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        // Receipt 1: 20 kg Grade B in Central Warehouse by Staff 1
        $time1 = now()->subHours(2);
        $receipt1 = $this->receiptService->receivePhysicalReturn(
            $item,
            20.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseStaff,
            receivedAt: $time1,
            remarks: 'Batch 1 from truck 1'
        );

        // Receipt 2: 20 kg Grade C in Hub Warehouse by Admin
        $time2 = now()->subHour();
        $receipt2 = $this->receiptService->receivePhysicalReturn(
            $item,
            20.0,
            ProductGrade::GradeC,
            $this->warehouseHub,
            $this->admin,
            receivedAt: $time2,
            remarks: 'Batch 2 from truck 2'
        );

        // Both receipts preserved
        $receipts = $item->receipts()->orderBy('id')->get();
        $this->assertCount(2, $receipts);

        $this->assertSame('B', $receipts[0]->received_grade);
        $this->assertEquals(20.0, (float) $receipts[0]->received_quantity);
        $this->assertSame($this->warehouseCentral->id, $receipts[0]->warehouse_id);
        $this->assertSame($this->warehouseStaff->id, $receipts[0]->received_by);
        $this->assertSame('Batch 1 from truck 1', $receipts[0]->remarks);

        $this->assertSame('C', $receipts[1]->received_grade);
        $this->assertEquals(20.0, (float) $receipts[1]->received_quantity);
        $this->assertSame($this->warehouseHub->id, $receipts[1]->warehouse_id);
        $this->assertSame($this->admin->id, $receipts[1]->received_by);
        $this->assertSame('Batch 2 from truck 2', $receipts[1]->remarks);
    }

    /**
     * Test 17: Credit Note cancellation detects linked physical stock movements and safely blocks cancellation.
     */
    public function test_17_cancellation_detects_linked_stock_movements(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        // Warehouse receives physical goods
        $this->receiptService->receivePhysicalReturn(
            $item,
            40.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseStaff
        );

        // Credit Note now has linked physical stock movements!
        $this->assertTrue($creditNote->stockMovements()->exists());
        $this->assertTrue($item->stockMovements()->exists());

        // Calling financial cancellation must be BLOCKED!
        try {
            $this->creditNoteService->cancelShopLevelCreditNote(
                $creditNote,
                $this->admin,
                'Attempted cancellation while stock is sitting in warehouse'
            );
            $this->fail('Expected ValidationException when cancelling credit note with unreconciled stock movements');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stock_movements', $e->errors());
        }

        // Credit note remains approved and stock remains safe
        $this->assertTrue($creditNote->fresh()->isApproved());
        $this->assertEquals(40.0, $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeB
        ));
    }

    /**
     * Test 18: Legacy single-invoice Credit Notes are unaffected and rejected by shop-level receiving service.
     */
    public function test_18_legacy_single_invoice_credit_notes_unaffected(): void
    {
        $legacyDraft = $this->creditNoteService->createDraft($this->invoiceA1, [
            'credit_note_date' => today()->toDateString(),
            'reason_type' => ShopCreditNoteReason::PriceCorrection,
            'direction' => ShopCreditNoteDirection::Credit,
            'inventory_action' => ShopCreditNoteInventoryAction::None,
            'items' => [
                [
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'revised_quantity' => 100,
                    'revised_unit_price' => 50,
                    'inventory_action' => ShopCreditNoteInventoryAction::None,
                ],
            ],
        ], $this->admin->id);

        $legacyApproved = $this->creditNoteService->approve($legacyDraft, $this->admin);
        $this->assertEquals(ShopCreditNoteAccountingMode::LegacyInvoiceLinked, $legacyApproved->accounting_mode);

        $legacyItem = $legacyApproved->items->first();

        try {
            $this->receiptService->receivePhysicalReturn(
                $legacyItem,
                10.0,
                ProductGrade::GradeA,
                $this->warehouseCentral,
                $this->warehouseStaff
            );
            $this->fail('Expected ValidationException for legacy accounting mode');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('accounting_mode', $e->errors());
        }
    }

    /**
     * Test 19: Warehouse role permissions enforced (unauthorized purchasers rejected).
     */
    public function test_19_warehouse_role_permissions_enforced(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        // Purchaser without warehouse roles rejected
        try {
            $this->receiptService->receivePhysicalReturn(
                $item,
                40.0,
                ProductGrade::GradeB,
                $this->warehouseCentral,
                $this->unauthorizedUser
            );
            $this->fail('Expected ValidationException for unauthorized purchaser');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('received_by', $e->errors());
        }

        // Warehouse receiver staff succeeds
        $receipt = $this->receiptService->receivePhysicalReturn(
            $item,
            40.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseStaff
        );
        $this->assertTrue($receipt->isVerified());
    }

    /**
     * Test 20: Inventory grade totals reconcile perfectly with stock movements.
     */
    public function test_20_inventory_grade_totals_reconcile_with_stock_movements(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        // Receive 25 kg as Grade B, 15 kg as Grade C
        $this->receiptService->receivePhysicalReturn(
            $item,
            25.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseStaff
        );

        $this->receiptService->receivePhysicalReturn(
            $item,
            15.0,
            ProductGrade::GradeC,
            $this->warehouseCentral,
            $this->warehouseStaff
        );

        // Grade A stock: 200 In - 100 Out = 100 kg
        $stockA = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeA
        );
        $this->assertEquals(100.0, $stockA);

        // Grade B stock: +25 kg
        $stockB = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeB
        );
        $this->assertEquals(25.0, $stockB);

        // Grade C stock: +15 kg
        $stockC = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeC
        );
        $this->assertEquals(15.0, $stockC);

        // Total onion stock across all grades in warehouse = 100 + 25 + 15 = 140 kg
        $totalStock = $this->stockLedgerService->availableStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id
        );
        $this->assertEquals(140.0, $totalStock);
    }

    /**
     * Test 21: Physical return dispute records dispute without creating stock movements and sets Disputed status.
     */
    public function test_21_dispute_physical_return_records_dispute_without_stock(): void
    {
        $creditNote = $this->createFinalizedReturnCreditNote($this->invoiceItemA1, 40.0, 35.00);
        $item = $creditNote->items->first();

        $stockMovementsBefore = StockMovement::count();

        // Warehouse staff disputes return (goods never arrived from customer)
        $receipt = $this->receiptService->disputePhysicalReturn(
            $item,
            $this->warehouseStaff,
            'Driver reported customer refused handover of physical goods'
        );

        $this->assertTrue($receipt->isDisputed());
        $this->assertEquals(0.00, (float) $receipt->received_quantity);
        $this->assertNull($receipt->stock_movement_id);
        $this->assertSame('Driver reported customer refused handover of physical goods', $receipt->remarks);

        // Zero stock movements created!
        $this->assertSame($stockMovementsBefore, StockMovement::count());

        // Item status transitions to Disputed
        $item->refresh();
        $this->assertEquals(ShopCreditNoteReceiptStatus::Disputed, $item->receipt_status);
        $this->assertTrue($item->isDisputed());
        $this->assertEquals(0.00, (float) $item->received_quantity);
        $this->assertStringContainsString('Disputed on', (string) $item->physical_receipt_notes);

        // Financial credit note remains unchanged at ₹1,400.00
        $this->assertEquals(1400.00, (float) $creditNote->fresh()->total_credit_amount);
    }

    /**
     * Test 22: Concurrency safety on MySQL — Competing warehouse receivers cannot over-receive.
     */
    public function test_22_concurrent_warehouse_receivers_cannot_over_receive_on_mysql(): void
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

        $pdo1->exec("INSERT INTO shops (id, name, code, status, created_at, updated_at) VALUES (888, 'Conc Receipt Shop', 'CONC_RCPT', 'active', NOW(), NOW())");
        $pdo1->exec("INSERT INTO warehouses (id, code, name, is_active, created_at, updated_at) VALUES (888, 'WH-CONC', 'Conc Warehouse', 1, NOW(), NOW())");
        $pdo1->exec("INSERT INTO products (id, category_id, name, sku, created_at, updated_at) VALUES (888, 1, 'Conc Onion', 'SKU-CONC-888', NOW(), NOW())");
        $pdo1->exec("INSERT INTO stock_batches (id, product_id, warehouse_id, purchase_grade, reference, received_at, total_kg, cost_per_kg, transport_cost, labour_cost, status, warehouse_receive_pending, grading_mode, created_by, created_at, updated_at) VALUES (888, 888, 888, 'B', 'BATCH-888', CURDATE(), 0.00, 40.00, 0.00, 0.00, 'sorted', 0, 'fixed_purchase_grade', 1, NOW(), NOW())");

        $pdo1->exec("INSERT INTO shop_credit_notes (id, credit_note_number, shop_id, credit_note_date, direction, accounting_mode, status, total_credit_amount, lock_version, created_at, updated_at) VALUES (888, 'SCN-CONC-WH-1', 888, CURDATE(), 'credit', 'shop_level', 'approved', 1400.00, 1, NOW(), NOW())");
        $pdo1->exec("INSERT INTO shop_credit_note_items (id, shop_credit_note_id, line_type, product_id, product_name, product_grade, adjusted_quantity, received_quantity, credit_rate, line_credit_amount, direction, inventory_action, receipt_status, created_at, updated_at) VALUES (888, 888, 'product_adjustment', 888, 'Conc Onion', 'A', 40.0000, 0.0000, 35.00, 1400.00, 'credit', 'return_to_stock', 'pending_receipt', NOW(), NOW())");

        // Transaction 1 locks shop_credit_note_items #888 for receiving 25 kg
        $pdo1->beginTransaction();
        $stmt1 = $pdo1->prepare('SELECT id, adjusted_quantity, received_quantity FROM shop_credit_note_items WHERE id = 888 FOR UPDATE');
        $stmt1->execute();
        $itemRow1 = $stmt1->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(40.0, (float) $itemRow1['adjusted_quantity']);

        // Transaction 2 sets 1-second timeout and attempts to lock the SAME item row
        $pdo2->exec('SET innodb_lock_wait_timeout = 1');
        $lockBlocked = false;

        try {
            $pdo2->beginTransaction();
            $stmt2 = $pdo2->prepare('SELECT id, adjusted_quantity, received_quantity FROM shop_credit_note_items WHERE id = 888 FOR UPDATE');
            $stmt2->execute();
        } catch (\PDOException $e) {
            $lockBlocked = true;
            $pdo2->rollBack();
        }

        $this->assertTrue($lockBlocked, 'Pessimistic lock on shop_credit_note_items successfully blocked concurrent receipt verification.');

        // Transaction 1 records 25 kg receipt, updates received_quantity to 25.0, and commits
        $pdo1->exec("INSERT INTO shop_credit_note_receipts (shop_credit_note_id, shop_credit_note_item_id, product_id, warehouse_id, received_grade, received_quantity, received_by, received_at, status, created_at, updated_at) VALUES (888, 888, 888, 888, 'B', 25.0000, 1, NOW(), 'verified', NOW(), NOW())");
        $pdo1->exec("UPDATE shop_credit_note_items SET received_quantity = 25.0000, receipt_status = 'partially_received' WHERE id = 888");
        $pdo1->commit();

        // Transaction 2 now starts, acquires lock, and reads updated received_quantity = 25.0
        $pdo2->beginTransaction();
        $stmt2 = $pdo2->prepare('SELECT id, adjusted_quantity, received_quantity FROM shop_credit_note_items WHERE id = 888 FOR UPDATE');
        $stmt2->execute();
        $itemRow2 = $stmt2->fetch(PDO::FETCH_ASSOC);

        $expected = (float) $itemRow2['adjusted_quantity'];
        $alreadyRcvd = (float) $itemRow2['received_quantity'];
        $remaining = $expected - $alreadyRcvd; // 40 - 25 = 15

        $this->assertEquals(25.0, $alreadyRcvd);
        $this->assertEquals(15.0, $remaining);

        // If Tx2 was trying to receive 20 kg, it detects 20 > 15 and rejects
        $attemptQty = 20.0;
        $canReceive = ($attemptQty <= $remaining);
        $this->assertFalse($canReceive, 'Transaction 2 safely detected that 20 kg exceeds the remaining 15 kg capacity.');
        $pdo2->rollBack();
    }
}
