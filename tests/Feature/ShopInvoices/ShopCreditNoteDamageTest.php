<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Enums\ShopInvoice\ShopCreditNoteAccountingMode;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
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
use App\Models\WastageEntry;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\InvoiceCashbookProjectionService;
use App\Services\Inventory\StockLedgerService;
use App\Services\Purchasing\DamageLossReportService;
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

class ShopCreditNoteDamageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $warehouseStaff;

    private User $unauthorizedUser;

    private Shop $shopA;

    private Warehouse $warehouseCentral;

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

    private DamageLossReportService $damageLossReportService;

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
        $this->damageLossReportService = app(DamageLossReportService::class);

        $this->productOnion = Product::factory()->create([
            'name' => 'Big Onion',
            'default_warehouse_id' => $this->warehouseCentral->id,
        ]);

        // Create initial stock batch and purchase movement (200 kg @ ₹40/kg)
        $this->initialDispatchBatch = StockBatch::factory()->create([
            'product_id' => $this->productOnion->id,
            'warehouse_id' => $this->warehouseCentral->id,
            'purchase_grade' => 'A',
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'cost_per_kg' => 40.00,
            'total_kg' => 200.0,
        ]);

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
            'SINV-DMG-101',
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
     * Test 1: Damage at shop creates correct financial credit.
     */
    public function test_01_damage_at_shop_creates_correct_financial_credit(): void
    {
        // Damaged quantity: 20 kg @ ₹25/kg = ₹500 credit
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Shop damaged onion claim',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 20.0,
                    'credit_rate' => 25.00,
                    'line_credit_amount' => 500.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::WriteOffWastage,
                    'reason_type' => ShopCreditNoteReason::DamageWastage,
                    'damage_reason' => 'transit_damage',
                ],
            ],
        ], $this->admin);

        $creditNote = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        $this->assertTrue($creditNote->isApproved());
        $this->assertEquals(500.00, (float) $creditNote->total_credit_amount);

        // Exactly one Cashbook credit entry of ₹500
        $tx = ShopLedgerTransaction::where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $creditNote->id)
            ->first();
        $this->assertNotNull($tx);
        $this->assertEquals(500.00, (float) $tx->amount);
        $this->assertEquals('income', $tx->direction);

        // Item receipt status is NotApplicable (damage stays at shop)
        $item = $creditNote->items->first();
        $this->assertEquals(ShopCreditNoteReceiptStatus::NotApplicable->value, $item->receipt_status->value);
    }

    /**
     * Test 2: Shop-side damage creates zero warehouse stock movement.
     */
    public function test_02_shop_side_damage_creates_zero_warehouse_stock_movement(): void
    {
        $movementCountBefore = StockMovement::count();

        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Shop damaged goods write off',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 20.0,
                    'credit_rate' => 25.00,
                    'line_credit_amount' => 500.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::WriteOffWastage,
                    'reason_type' => ShopCreditNoteReason::DamageWastage,
                ],
            ],
        ], $this->admin);

        $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        // Zero stock movements created
        $movementCountAfter = StockMovement::count();
        $this->assertEquals($movementCountBefore, $movementCountAfter);
    }

    /**
     * Test 3: Existing warehouse stock is not deducted twice.
     */
    public function test_03_existing_warehouse_stock_is_not_deducted_twice(): void
    {
        // Initial purchase was 200 kg, original sale dispatched 100 kg -> 100 kg remains
        $stockBefore = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeA
        );
        $this->assertEquals(100.0, $stockBefore);

        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Shop damage',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 20.0,
                    'credit_rate' => 25.00,
                    'line_credit_amount' => 500.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::WriteOffWastage,
                    'reason_type' => ShopCreditNoteReason::DamageWastage,
                ],
            ],
        ], $this->admin);

        $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        // Stock remains exactly 100 kg (no double deduction)
        $stockAfter = $this->stockLedgerService->availableSortedStockForProduct(
            $this->productOnion->id,
            $this->warehouseCentral->id,
            ProductGrade::GradeA
        );
        $this->assertEquals(100.0, $stockAfter);
    }

    /**
     * Test 4: Warehouse-returned damaged goods never become saleable Grade A/B/C stock.
     */
    public function test_04_warehouse_returned_damaged_goods_never_become_saleable_grade_a_b_c_stock(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Return of goods claim',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 30.0,
                    'credit_rate' => 35.00,
                    'line_credit_amount' => 1050.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                    'reason_type' => ShopCreditNoteReason::ReturnToWarehouse,
                ],
            ],
        ], $this->admin);

        $creditNote = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);
        $item = $creditNote->items->first();

        $stockABefore = $this->stockLedgerService->availableSortedStockForProduct($this->productOnion->id, $this->warehouseCentral->id, ProductGrade::GradeA);
        $stockBBefore = $this->stockLedgerService->availableSortedStockForProduct($this->productOnion->id, $this->warehouseCentral->id, ProductGrade::GradeB);
        $stockCBefore = $this->stockLedgerService->availableSortedStockForProduct($this->productOnion->id, $this->warehouseCentral->id, ProductGrade::GradeC);

        // Warehouse receives 30 kg DAMAGED (rotten)
        $receipt = $this->receiptService->receiveDamagedReturn(
            item: $item,
            quantity: 30.0,
            damageReason: WastageReason::Rotten,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff,
            remarks: 'Completely spoiled in transit'
        );

        $this->assertTrue($receipt->isDamaged());
        $this->assertNull($receipt->stock_movement_id); // No stock movement created
        $this->assertNotNull($receipt->wastage_entry_id);

        // Grade A, B, and C stock are all unchanged
        $stockAAfter = $this->stockLedgerService->availableSortedStockForProduct($this->productOnion->id, $this->warehouseCentral->id, ProductGrade::GradeA);
        $stockBAfter = $this->stockLedgerService->availableSortedStockForProduct($this->productOnion->id, $this->warehouseCentral->id, ProductGrade::GradeB);
        $stockCAfter = $this->stockLedgerService->availableSortedStockForProduct($this->productOnion->id, $this->warehouseCentral->id, ProductGrade::GradeC);

        $this->assertEquals($stockABefore, $stockAAfter);
        $this->assertEquals($stockBBefore, $stockBAfter);
        $this->assertEquals($stockCBefore, $stockCAfter);
    }

    /**
     * Test 5: Valid damage reason recorded.
     */
    public function test_05_valid_damage_reason_recorded(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Rotten damage claim',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 15.0,
                    'credit_rate' => 30.00,
                    'line_credit_amount' => 450.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::WriteOffWastage,
                    'reason_type' => ShopCreditNoteReason::DamageWastage,
                    'damage_reason' => 'rotten',
                ],
            ],
        ], $this->admin);

        $creditNote = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);
        $item = $creditNote->items->first();

        $wastage = WastageEntry::where('notes', 'like', "%NoteItem: {$item->id};%")->first();
        $this->assertNotNull($wastage);
        $this->assertEquals(WastageReason::Rotten, $wastage->reason);
        $this->assertEquals(15.0, (float) $wastage->quantity);
    }

    /**
     * Test 6: Damage associated with correct shop, invoice item and Credit Note.
     */
    public function test_06_damage_associated_with_correct_shop_invoice_item_and_credit_note(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Attribution check',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 20.0,
                    'credit_rate' => 25.00,
                    'line_credit_amount' => 500.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::WriteOffWastage,
                    'reason_type' => ShopCreditNoteReason::DamageWastage,
                ],
            ],
        ], $this->admin);

        $creditNote = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);
        $item = $creditNote->items->first();

        $wastage = WastageEntry::find($item->fresh()->wastage_entry_id);
        $this->assertNotNull($wastage);

        $this->assertStringContainsString("SCN: {$creditNote->credit_note_number}", $wastage->notes);
        $this->assertStringContainsString("NoteItem: {$item->id}", $wastage->notes);
        $this->assertStringContainsString("Invoice: {$this->invoiceA1->invoice_number}", $wastage->notes);
        $this->assertStringContainsString("Shop: {$this->shopA->name}", $wastage->notes);
    }

    /**
     * Test 7: Mixed return and damage totals calculate correctly.
     */
    public function test_07_mixed_return_and_damage_totals_calculate_correctly(): void
    {
        // 40 kg ReturnToStock @ ₹35 = ₹1,400 + 20 kg Damaged at shop @ ₹25 = ₹500 -> ₹1,900
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Mixed return and damage',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 40.0,
                    'credit_rate' => 35.00,
                    'line_credit_amount' => 1400.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                    'reason_type' => ShopCreditNoteReason::ReturnToWarehouse,
                ],
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 20.0,
                    'credit_rate' => 25.00,
                    'line_credit_amount' => 500.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::WriteOffWastage,
                    'reason_type' => ShopCreditNoteReason::DamageWastage,
                ],
            ],
        ], $this->admin);

        $creditNote = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        // Total credit = ₹1,900
        $this->assertEquals(1900.00, (float) $creditNote->total_credit_amount);

        // Shop damage WastageEntry is created immediately for 20 kg
        $itemDamage = $creditNote->items->firstWhere('inventory_action', ShopCreditNoteInventoryAction::WriteOffWastage);
        $this->assertNotNull($itemDamage->wastage_entry_id);
        $this->assertEquals(20.0, (float) $itemDamage->wastageEntry->quantity);

        // Warehouse receives the 40 kg return as Grade B
        $itemReturn = $creditNote->items->firstWhere('inventory_action', ShopCreditNoteInventoryAction::ReturnToStock);
        $receipt = $this->receiptService->receivePhysicalReturn(
            item: $itemReturn,
            quantity: 40.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        $this->assertEquals(40.0, $this->stockLedgerService->availableSortedStockForProduct($this->productOnion->id, $this->warehouseCentral->id, ProductGrade::GradeB));
    }

    /**
     * Test 8: Grade A → B physical return remains correct alongside damage.
     */
    public function test_08_grade_a_to_b_physical_return_remains_correct_alongside_damage(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Grade B return with damage',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 30.0,
                    'credit_rate' => 35.00,
                    'line_credit_amount' => 1050.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                    'reason_type' => ShopCreditNoteReason::ReturnToWarehouse,
                ],
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 10.0,
                    'credit_rate' => 25.00,
                    'line_credit_amount' => 250.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::WriteOffWastage,
                    'reason_type' => ShopCreditNoteReason::DamageWastage,
                ],
            ],
        ], $this->admin);

        $creditNote = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);
        $itemReturn = $creditNote->items->firstWhere('inventory_action', ShopCreditNoteInventoryAction::ReturnToStock);

        $this->receiptService->receivePhysicalReturn(
            item: $itemReturn,
            quantity: 30.0,
            grade: ProductGrade::GradeB,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        $this->assertEquals(30.0, $this->stockLedgerService->availableSortedStockForProduct($this->productOnion->id, $this->warehouseCentral->id, ProductGrade::GradeB));
        $this->assertEquals(100.0, $this->stockLedgerService->availableSortedStockForProduct($this->productOnion->id, $this->warehouseCentral->id, ProductGrade::GradeA));
    }

    /**
     * Test 9: Same source quantity cannot be credited twice.
     */
    public function test_09_same_source_quantity_cannot_be_credited_twice(): void
    {
        // 100 kg billed. Claim 70 kg return + 40 kg damage = 110 kg (> 100 kg)
        $this->expectException(ValidationException::class);

        $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Excess credit claim',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 70.0,
                    'credit_rate' => 35.00,
                    'line_credit_amount' => 2450.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                ],
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 40.0,
                    'credit_rate' => 25.00,
                    'line_credit_amount' => 1000.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::WriteOffWastage,
                ],
            ],
        ], $this->admin);
    }

    /**
     * Test 10: Damage verification retry does not create duplicate records.
     */
    public function test_10_damage_verification_retry_does_not_create_duplicate_records(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Idempotent damage receipt',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 25.0,
                    'credit_rate' => 35.00,
                    'line_credit_amount' => 875.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                ],
            ],
        ], $this->admin);

        $creditNote = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);
        $item = $creditNote->items->first();

        // First submission with idempotency key
        $receipt1 = $this->receiptService->receiveDamagedReturn(
            item: $item,
            quantity: 25.0,
            damageReason: WastageReason::Rotten,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff,
            idempotencyKey: 'dmg_key_1001'
        );

        $wastageCount = WastageEntry::count();

        // Retry same submission with same idempotency key
        $receipt2 = $this->receiptService->receiveDamagedReturn(
            item: $item,
            quantity: 25.0,
            damageReason: WastageReason::Rotten,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff,
            idempotencyKey: 'dmg_key_1001'
        );

        $this->assertEquals($receipt1->id, $receipt2->id);
        $this->assertEquals($wastageCount, WastageEntry::count());
    }

    /**
     * Test 11: Concurrent damage verification is safe.
     */
    public function test_11_concurrent_damage_verification_is_safe(): void
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
        $pdo1->exec('TRUNCATE TABLE wastage_entries');
        $pdo1->exec('TRUNCATE TABLE stock_batches');
        $pdo1->exec('TRUNCATE TABLE warehouses');
        $pdo1->exec('TRUNCATE TABLE products');
        $pdo1->exec('TRUNCATE TABLE shops');

        $pdo1->exec("INSERT INTO shops (id, name, code, status, created_at, updated_at) VALUES (777, 'Conc Dmg Shop', 'CONC_DMG', 'active', NOW(), NOW())");
        $pdo1->exec("INSERT INTO warehouses (id, code, name, is_active, created_at, updated_at) VALUES (777, 'WH-DMG', 'Dmg Warehouse', 1, NOW(), NOW())");
        $pdo1->exec("INSERT INTO products (id, category_id, name, sku, created_at, updated_at) VALUES (777, 1, 'Dmg Onion', 'SKU-DMG-777', NOW(), NOW())");

        $pdo1->exec("INSERT INTO shop_credit_notes (id, credit_note_number, shop_id, credit_note_date, direction, accounting_mode, status, total_credit_amount, lock_version, created_at, updated_at) VALUES (777, 'SCN-CONC-DMG-1', 777, CURDATE(), 'credit', 'shop_level', 'approved', 1400.00, 1, NOW(), NOW())");
        $pdo1->exec("INSERT INTO shop_credit_note_items (id, shop_credit_note_id, line_type, product_id, product_name, product_grade, adjusted_quantity, received_quantity, credit_rate, line_credit_amount, direction, inventory_action, receipt_status, created_at, updated_at) VALUES (777, 777, 'product_adjustment', 777, 'Dmg Onion', 'A', 30.0000, 0.0000, 35.00, 1050.00, 'credit', 'return_to_stock', 'pending_receipt', NOW(), NOW())");

        // Transaction 1 locks item row #777
        $pdo1->beginTransaction();
        $stmt1 = $pdo1->prepare('SELECT id, adjusted_quantity, received_quantity FROM shop_credit_note_items WHERE id = 777 FOR UPDATE');
        $stmt1->execute();
        $row1 = $stmt1->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(30.0, (float) $row1['adjusted_quantity']);

        // Transaction 2 attempts lock with 1-second timeout
        $pdo2->exec('SET innodb_lock_wait_timeout = 1');
        $blocked = false;

        try {
            $pdo2->beginTransaction();
            $stmt2 = $pdo2->prepare('SELECT id, adjusted_quantity, received_quantity FROM shop_credit_note_items WHERE id = 777 FOR UPDATE');
            $stmt2->execute();
        } catch (\PDOException $e) {
            $blocked = true;
            $pdo2->rollBack();
        }

        $this->assertTrue($blocked, 'Pessimistic lock on item prevented concurrent over-receipt of damage.');

        // Transaction 1 records 20 kg damaged receipt and commits
        $pdo1->exec("INSERT INTO shop_credit_note_receipts (shop_credit_note_id, shop_credit_note_item_id, product_id, warehouse_id, received_grade, received_quantity, received_by, received_at, status, created_at, updated_at) VALUES (777, 777, 777, 777, 'A', 20.0000, 1, NOW(), 'damaged', NOW(), NOW())");
        $pdo1->exec("UPDATE shop_credit_note_items SET received_quantity = 20.0000, receipt_status = 'partially_received' WHERE id = 777");
        $pdo1->commit();

        // Transaction 2 reads remaining = 30 - 20 = 10 kg
        $pdo2->beginTransaction();
        $stmt2 = $pdo2->prepare('SELECT id, adjusted_quantity, received_quantity FROM shop_credit_note_items WHERE id = 777 FOR UPDATE');
        $stmt2->execute();
        $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
        $remaining = (float) $row2['adjusted_quantity'] - (float) $row2['received_quantity'];
        $this->assertEquals(10.0, $remaining);
        $pdo2->rollBack();
    }

    /**
     * Test 12: Credit Note finalization does not duplicate Cashbook credit.
     */
    public function test_12_credit_note_finalization_does_not_duplicate_cashbook_credit(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Cashbook check',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 20.0,
                    'credit_rate' => 25.00,
                    'line_credit_amount' => 500.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::WriteOffWastage,
                ],
            ],
        ], $this->admin);

        $creditNote = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        $txs = ShopLedgerTransaction::where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $creditNote->id)
            ->get();

        $this->assertCount(1, $txs);
        $this->assertEquals(500.00, (float) $txs->first()->amount);
    }

    /**
     * Test 13: Damage records appear through existing Damage & Loss data sources.
     */
    public function test_13_damage_records_appear_through_existing_damage_loss_data_sources(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Reporting check',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 20.0,
                    'credit_rate' => 25.00,
                    'line_credit_amount' => 500.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::WriteOffWastage,
                ],
            ],
        ], $this->admin);

        $creditNote = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        // Authoritative query used by Damage & Loss reporting
        $reportData = $this->damageLossReportService->getReportData([
            'product_id' => $this->productOnion->id,
            'start_date' => today()->subDays(1)->toDateString(),
            'end_date' => today()->addDays(1)->toDateString(),
            'shop_id' => $this->shopA->id,
        ]);

        $this->assertGreaterThanOrEqual(20.0, (float) $reportData['summary']->total_damaged_kg);
    }

    /**
     * Test 14: Cancellation before damage processing blocks later processing.
     */
    public function test_14_cancellation_before_damage_processing_blocks_later_processing(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Claim to be cancelled',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 20.0,
                    'credit_rate' => 35.00,
                    'line_credit_amount' => 700.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                ],
            ],
        ], $this->admin);

        $creditNote = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);
        $item = $creditNote->items->first();

        // Office cancels credit note before warehouse receives anything
        $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'Cancelled before receipt');

        // Warehouse cannot receive damaged goods on voided credit note
        $this->expectException(ValidationException::class);
        $this->receiptService->receiveDamagedReturn(
            item: $item,
            quantity: 20.0,
            damageReason: WastageReason::Rotten,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );
    }

    /**
     * Test 15: Cancellation preserves damage audit history.
     */
    public function test_15_cancellation_preserves_damage_audit_history(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Shop damage audit preservation',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 20.0,
                    'credit_rate' => 25.00,
                    'line_credit_amount' => 500.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::WriteOffWastage,
                ],
            ],
        ], $this->admin);

        $creditNote = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);
        $item = $creditNote->items->first();
        $wastageId = $item->fresh()->wastage_entry_id;
        $this->assertNotNull($wastageId);

        // Cancel credit note
        $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'Reversal of damage claim');

        // WastageEntry is soft-deleted, not hard-deleted!
        $this->assertNull(WastageEntry::find($wastageId));
        $historical = WastageEntry::withTrashed()->find($wastageId);
        $this->assertNotNull($historical);
        $this->assertNotNull($historical->deleted_at);
        $this->assertStringContainsString('SCN Voided', (string) $historical->notes);
    }

    /**
     * Test 16: Cancellation with unreconciled physical damage is blocked.
     */
    public function test_16_cancellation_with_unreconciled_physical_damage_is_blocked(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Unreconciled physical damage check',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 20.0,
                    'credit_rate' => 35.00,
                    'line_credit_amount' => 700.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                ],
            ],
        ], $this->admin);

        $creditNote = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);
        $item = $creditNote->items->first();

        // Warehouse receives 20 kg damaged
        $receipt = $this->receiptService->receiveDamagedReturn(
            item: $item,
            quantity: 20.0,
            damageReason: WastageReason::Rotten,
            warehouse: $this->warehouseCentral,
            actor: $this->warehouseStaff
        );

        // Office cancellation is blocked while receipt is unreconciled
        try {
            $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'Premature cancel');
            $this->fail('Expected cancellation to be blocked.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stock_movements', $e->errors());
        }

        // Warehouse reverses damaged receipt
        $this->receiptService->reversePhysicalReturn($receipt, $this->warehouseStaff, 'Error in damage receipt');

        // Now cancellation succeeds
        $cancelledCN = $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'Now permitted cancel');
        $this->assertTrue($cancelledCN->isVoided());
    }

    /**
     * Test 17: Original invoices and dispatch movements remain immutable.
     */
    public function test_17_original_invoices_and_dispatch_movements_remain_immutable(): void
    {
        $origInvTotal = (float) $this->invoiceA1->final_total;
        $origLineTotal = (float) $this->invoiceItemA1->final_line_total;
        $origMovementQty = (float) $this->initialDispatchMovement->quantity;

        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Immutability test',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 20.0,
                    'credit_rate' => 25.00,
                    'line_credit_amount' => 500.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::WriteOffWastage,
                ],
            ],
        ], $this->admin);

        $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        $this->assertEquals($origInvTotal, (float) $this->invoiceA1->fresh()->final_total);
        $this->assertEquals($origLineTotal, (float) $this->invoiceItemA1->fresh()->final_line_total);
        $this->assertEquals($origMovementQty, (float) $this->initialDispatchMovement->fresh()->quantity);
    }

    /**
     * Test 18: Existing legacy Credit Notes continue working.
     */
    public function test_18_existing_legacy_credit_notes_continue_working(): void
    {
        $legacyCN = ShopCreditNote::factory()->create([
            'shop_id' => $this->shopA->id,
            'shop_invoice_id' => $this->invoiceA1->id,
            'credit_note_number' => 'SCN-LEGACY-DMG',
            'credit_note_date' => today()->toDateString(),
            'accounting_mode' => ShopCreditNoteAccountingMode::LegacyInvoiceLinked->value,
            'status' => 'draft',
            'total_credit_amount' => 400.00,
        ]);

        $this->assertFalse($legacyCN->isShopLevelMode());
    }

    /**
     * Test 19: Unauthorized warehouse/office actions rejected.
     */
    public function test_19_unauthorized_warehouse_office_actions_rejected(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Auth test',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 20.0,
                    'credit_rate' => 35.00,
                    'line_credit_amount' => 700.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                ],
            ],
        ], $this->admin);

        $creditNote = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);
        $item = $creditNote->items->first();

        // Unauthorized user cannot verify damaged return
        $this->expectException(ValidationException::class);
        $this->receiptService->receiveDamagedReturn(
            item: $item,
            quantity: 20.0,
            damageReason: WastageReason::Rotten,
            warehouse: $this->warehouseCentral,
            actor: $this->unauthorizedUser
        );
    }

    /**
     * Test 20: Inventory and financial balances reconcile.
     */
    public function test_20_inventory_and_financial_balances_reconcile(): void
    {
        // 100 kg billed @ ₹55 = ₹5,500
        // Credit note: 20 kg shop damage @ ₹25 = ₹500 credit
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Reconciliation test',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->invoiceItemA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 20.0,
                    'credit_rate' => 25.00,
                    'line_credit_amount' => 500.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::WriteOffWastage,
                ],
            ],
        ], $this->admin);

        $creditNote = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        // Apply credit note to invoice
        $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 500.00, $this->admin);

        // Effective invoice balance: 5,500 - 500 = 5,000
        $bill = $this->finalBillService->calculate($this->invoiceA1);
        $this->assertEquals(5000.00, $bill->remainingBalance);

        // Credit note unapplied balance: 0.00
        $this->assertEquals(0.00, $creditNote->fresh()->unappliedCreditTotal());

        // Warehouse inventory remains 100 kg Grade A
        $stockA = $this->stockLedgerService->availableSortedStockForProduct($this->productOnion->id, $this->warehouseCentral->id, ProductGrade::GradeA);
        $this->assertEquals(100.0, $stockA);
    }
}
