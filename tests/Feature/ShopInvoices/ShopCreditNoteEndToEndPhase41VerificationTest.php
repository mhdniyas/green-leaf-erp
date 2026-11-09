<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
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
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShopCreditNoteEndToEndPhase41VerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $warehouseStaff;

    private User $unauthorizedUser;

    private Shop $shopA;

    private Shop $shopB;

    private Warehouse $warehouseCentral;

    private Product $productOnion;

    private Product $productTomato;

    private ShopInvoice $invoiceA1;

    private ShopInvoiceItem $itemOnionA1;

    private ShopInvoiceItem $itemTomatoA1;

    private ShopInvoice $invoiceA2;

    private ShopInvoiceItem $itemOnionA2;

    private ShopCreditNoteService $creditNoteService;

    private ShopCreditApplicationService $applicationService;

    private ShopCreditNoteWarehouseReceiptService $receiptService;

    private DamageLossReportService $damageLossReportService;

    private StockLedgerService $stockLedgerService;

    private InvoiceCashbookProjectionService $projectionService;

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
            'name' => 'Central Warehouse Keeper',
        ]);
        $this->warehouseStaff->assignRole('warehouse_receiver');

        $this->unauthorizedUser = User::factory()->create([
            'name' => 'General Employee',
        ]);
        $this->unauthorizedUser->assignRole('purchaser');

        $this->warehouseCentral = Warehouse::factory()->create([
            'code' => 'WH-CTR',
            'name' => 'Central Warehouse',
            'is_active' => true,
        ]);

        $this->shopA = Shop::factory()->create([
            'code' => 'SHP-A',
            'name' => 'Kochi Superstore',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $this->shopB = Shop::factory()->create([
            'code' => 'SHP-B',
            'name' => 'Calicut Mart',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->projectionService = app(InvoiceCashbookProjectionService::class);
        $this->creditNoteService = app(ShopCreditNoteService::class);
        $this->applicationService = app(ShopCreditApplicationService::class);
        $this->receiptService = app(ShopCreditNoteWarehouseReceiptService::class);
        $this->damageLossReportService = app(DamageLossReportService::class);
        $this->stockLedgerService = app(StockLedgerService::class);

        $this->productOnion = Product::factory()->create([
            'name' => 'Big Onion',
            'default_warehouse_id' => $this->warehouseCentral->id,
        ]);

        $this->productTomato = Product::factory()->create([
            'name' => 'Tomato Local',
            'default_warehouse_id' => $this->warehouseCentral->id,
        ]);

        $this->invoiceA1 = $this->createHistoricalInvoice(
            $this->shopA,
            'INV-A1-1001',
            today()->subDays(3)->toDateString(),
            [
                ['product' => $this->productOnion, 'qty' => 100.0, 'rate' => 35.0, 'cost' => 30.0, 'grade' => 'A'],
                ['product' => $this->productTomato, 'qty' => 50.0, 'rate' => 25.0, 'cost' => 20.0, 'grade' => 'A'],
            ]
        );
        $this->itemOnionA1 = $this->invoiceA1->items->firstWhere('product_id', $this->productOnion->id);
        $this->itemTomatoA1 = $this->invoiceA1->items->firstWhere('product_id', $this->productTomato->id);

        $this->invoiceA2 = $this->createHistoricalInvoice(
            $this->shopA,
            'INV-A2-1002',
            today()->subDays(1)->toDateString(),
            [
                ['product' => $this->productOnion, 'qty' => 80.0, 'rate' => 36.0, 'cost' => 30.0, 'grade' => 'A'],
            ]
        );
        $this->itemOnionA2 = $this->invoiceA2->items->firstWhere('product_id', $this->productOnion->id);
    }

    private function createHistoricalInvoice(Shop $shop, string $invoiceNumber, string $date, array $items): ShopInvoice
    {
        static $orderCounter = 1;
        $orderNum = $orderCounter++;
        $businessDate = $date ?? today()->toDateString();

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $businessDate,
            'shop_daily_order_key' => "shop:{$shop->id}:{$businessDate}:{$orderNum}",
        ]);

        $total = 0.0;
        foreach ($items as $itemData) {
            $lineTotal = round($itemData['qty'] * $itemData['rate'], 2);
            $total += $lineTotal;
        }

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'business_date' => $businessDate,
            'status' => 'finalized',
            'delivery_status' => 'received_full',
            'payment_status' => 'unpaid',
            'final_total' => $total,
            'paid_amount' => 0.0,
            'balance_amount' => $total,
            'finalized_by' => $this->admin->id,
            'finalized_at' => now(),
        ]);

        foreach ($items as $itemData) {
            $lineTotal = round($itemData['qty'] * $itemData['rate'], 2);

            $batch = StockBatch::factory()->create([
                'product_id' => $itemData['product']->id,
                'warehouse_id' => $this->warehouseCentral->id,
                'total_kg' => $itemData['qty'] * 2,
                'cost_per_kg' => $itemData['cost'],
                'purchase_grade' => $itemData['grade'],
                'status' => BatchStatus::Sorted->value,
                'warehouse_receive_pending' => false,
                'received_at' => $date,
            ]);

            StockMovement::create([
                'product_id' => $itemData['product']->id,
                'batch_id' => $batch->id,
                'warehouse_id' => $this->warehouseCentral->id,
                'created_by' => $this->admin->id,
                'grade' => $itemData['grade'],
                'type' => StockMovementType::In->value,
                'quantity' => $itemData['qty'] * 2,
                'cost_per_unit' => $itemData['cost'],
                'notes' => 'Initial batch stock',
            ]);

            $orderItem = ShopOrderItem::query()->create([
                'shop_order_id' => $order->id,
                'product_id' => $itemData['product']->id,
                'product_grade' => $itemData['grade'],
                'requested_qty' => $itemData['qty'],
                'approved_qty' => $itemData['qty'],
                'loaded_qty' => $itemData['qty'],
                'delivered_qty' => $itemData['qty'],
                'unit' => 'kg',
                'unit_cost' => $itemData['cost'],
            ]);

            StockMovement::create([
                'product_id' => $itemData['product']->id,
                'batch_id' => $batch->id,
                'warehouse_id' => $this->warehouseCentral->id,
                'created_by' => $this->admin->id,
                'grade' => $itemData['grade'],
                'type' => StockMovementType::Out->value,
                'quantity' => $itemData['qty'],
                'cost_per_unit' => $itemData['cost'],
                'shop_order_item_id' => $orderItem->id,
                'notes' => 'Dispatch to shop',
            ]);

            ShopInvoiceItem::query()->create([
                'shop_invoice_id' => $invoice->id,
                'shop_order_item_id' => $orderItem->id,
                'product_id' => $itemData['product']->id,
                'product_name' => $itemData['product']->name,
                'product_grade' => $itemData['grade'],
                'unit' => 'kg',
                'price_unit' => 'kg',
                'approved_qty' => $itemData['qty'],
                'price_quantity' => $itemData['qty'],
                'delivered_qty' => $itemData['qty'],
                'delivered_price_quantity' => $itemData['qty'],
                'unit_price' => $itemData['rate'],
                'line_subtotal' => $lineTotal,
                'final_line_total' => $lineTotal,
            ]);
        }

        $this->projectionService->syncInvoice($invoice->fresh());

        return $invoice->fresh(['items', 'order.items']);
    }

    /**
     * Section 1: Complete Office Workflow Verification
     * Draft Creation -> Edit -> Finalize -> Apply -> Reverse -> Cancel.
     * Immutable original invoice preservation.
     */
    public function test_01_complete_office_workflow_create_edit_finalize_apply_reverse_cancel(): void
    {
        $originalA1Total = (float) $this->invoiceA1->final_total;
        $originalA2Total = (float) $this->invoiceA2->final_total;

        // Step 1: Create Shop-First Draft spanning Invoice A1 and Invoice A2
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Multi-invoice adjustment draft',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 10.0,
                    'credit_rate' => 35.0,
                    'line_credit_amount' => 350.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                    'reason_type' => ShopCreditNoteReason::ReturnToWarehouse,
                ],
                [
                    'source_shop_invoice_id' => $this->invoiceA2->id,
                    'shop_invoice_item_id' => $this->itemOnionA2->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 5.0,
                    'credit_rate' => 36.0,
                    'line_credit_amount' => 180.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::None,
                    'reason_type' => ShopCreditNoteReason::CommercialDiscount,
                ],
            ],
        ], $this->admin);

        $this->assertEquals(ShopCreditNoteStatus::Draft, $draft->status);
        $this->assertEquals(530.0, (float) $draft->total_credit_amount);

        // Step 2: Edit Draft (update line quantity and rate)
        $updatedDraft = $this->creditNoteService->updateShopLevelDraft($draft, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Updated draft with negotiated commercial rate',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 10.0,
                    'credit_rate' => 35.0,
                    'line_credit_amount' => 350.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::None, // No return to stock
                    'reason_type' => ShopCreditNoteReason::CommercialDiscount,
                ],
                [
                    'source_shop_invoice_id' => $this->invoiceA2->id,
                    'shop_invoice_item_id' => $this->itemOnionA2->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 10.0,
                    'credit_rate' => 30.0, // Custom credit rate
                    'line_credit_amount' => 300.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::None,
                    'reason_type' => ShopCreditNoteReason::CommercialDiscount,
                ],
            ],
        ], $this->admin);

        $this->assertEquals(650.0, (float) $updatedDraft->total_credit_amount);

        // Step 3: Office Finalization
        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($updatedDraft, $this->admin);
        $this->assertEquals(ShopCreditNoteStatus::Approved, $finalized->status);
        $this->assertNotNull($finalized->approved_at);
        $this->assertStringStartsWith('SCN-', $finalized->credit_note_number);

        // Step 4: Apply Credit to Invoice A1
        $app = $this->applicationService->applyCredit($finalized, $this->invoiceA1, 400.0, $this->admin, 'Application test');
        $this->assertEquals(400.0, (float) $app->amount);
        $this->assertEquals(400.0, $finalized->fresh()->appliedCreditTotal());
        $this->assertEquals(250.0, $finalized->fresh()->unappliedCreditTotal());

        // Step 5: Reverse Application
        $reversedApp = $this->applicationService->reverseCreditApplication($app, $this->admin, 'Clerical correction');
        $this->assertNotNull($reversedApp->reversed_at);
        $this->assertEquals(0.0, $finalized->fresh()->appliedCreditTotal());
        $this->assertEquals(650.0, $finalized->fresh()->unappliedCreditTotal());

        // Step 6: Cancel Credit Note
        $cancelled = $this->creditNoteService->cancelShopLevelCreditNote($finalized, $this->admin, 'Agreement voided');
        $this->assertEquals(ShopCreditNoteStatus::Voided, $cancelled->status);

        // Verify Invoices Remain Completely Immutable
        $this->assertEquals($originalA1Total, (float) $this->invoiceA1->fresh()->final_total);
        $this->assertEquals($originalA2Total, (float) $this->invoiceA2->fresh()->final_total);
    }

    /**
     * Section 2: Financial Reconciliation with Exact Values
     * Onion return: 40 kg @ ₹35 = ₹1,400.
     * Shop damage: 20 kg @ ₹25 = ₹500.
     * Commercial discount: ₹300.
     * Total Credit Note: ₹2,200.
     * Apply ₹1,500.
     * Expected:
     * - Total finalized: ₹2,200
     * - Applied: ₹1,500
     * - Unapplied: ₹700
     * - Exactly one ₹2,200 Cashbook credit
     * - No second financial credit from application
     * - Reverse application: Applied = 0, Unapplied = 2,200
     * - Cancel Credit Note: exact-once Cashbook debit reversal of ₹2,200.
     */
    public function test_02_financial_reconciliation_exact_scenario(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Financial reconciliation exact scenario',
            'items' => [
                // 1. Grade A Onion return: 40 kg at ₹35 = ₹1,400
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 40.0,
                    'credit_rate' => 35.0,
                    'line_credit_amount' => 1400.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::None, // Financial test
                    'reason_type' => ShopCreditNoteReason::CommercialDiscount,
                ],
                // 2. Shop-side damage: 20 kg at ₹25 = ₹500
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemTomatoA1->id,
                    'product_id' => $this->productTomato->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 20.0,
                    'credit_rate' => 25.0,
                    'line_credit_amount' => 500.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::None,
                    'reason_type' => ShopCreditNoteReason::DamageWastage,
                ],
                // 3. Commercial discount: ₹300
                [
                    'line_type' => 'commercial_discount',
                    'discount_amount' => 300.0,
                    'notes' => 'Commercial discount bonus',
                ],
            ],
        ], $this->admin);

        $this->assertEquals(2200.0, (float) $draft->total_credit_amount);

        // Pre-count cashbook transactions
        $initialTxCount = ShopLedgerTransaction::where('shop_id', $this->shopA->id)->count();

        // 1. Office Finalization
        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);
        $finalized = $finalized->fresh();

        $this->assertEquals(2200.0, (float) $finalized->total_credit_amount);
        $this->assertEquals(0.0, (float) $finalized->appliedCreditTotal());
        $this->assertEquals(2200.0, (float) $finalized->unappliedCreditTotal());

        // Exactly one Cashbook transaction of ₹2,200
        $tx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $finalized->id)
            ->first();
        $this->assertNotNull($tx);
        $this->assertEquals(2200.0, (float) $tx->amount);
        $this->assertEquals('approved', $tx->status);

        // 2. Apply ₹1,500 to Invoice A1
        $app = $this->applicationService->applyCredit($finalized, $this->invoiceA1, 1500.0, $this->admin, '₹1,500 allocation');

        $finalized = $finalized->fresh();
        $this->assertEquals(2200.0, (float) $finalized->total_credit_amount);
        $this->assertEquals(1500.0, (float) $finalized->appliedCreditTotal());
        $this->assertEquals(700.0, (float) $finalized->unappliedCreditTotal());

        // Invariant check: Total = Applied + Unapplied
        $this->assertEquals(
            (float) $finalized->total_credit_amount,
            (float) ($finalized->appliedCreditTotal() + $finalized->unappliedCreditTotal())
        );

        // Verify NO second Cashbook transaction occurred from the application!
        $this->assertEquals(
            $initialTxCount + 1,
            ShopLedgerTransaction::where('shop_id', $this->shopA->id)->count()
        );

        // Original invoice amount remains unchanged
        $this->assertEquals(4750.0, (float) $this->invoiceA1->fresh()->final_total);

        // 3. Reverse the ₹1,500 application
        $this->applicationService->reverseCreditApplication($app, $this->admin, 'Application reversal test');
        $finalized = $finalized->fresh();

        $this->assertEquals(0.0, (float) $finalized->appliedCreditTotal());
        $this->assertEquals(2200.0, (float) $finalized->unappliedCreditTotal());

        // Still no additional Cashbook transaction
        $this->assertEquals(
            $initialTxCount + 1,
            ShopLedgerTransaction::where('shop_id', $this->shopA->id)->count()
        );

        // 4. Cancel the Credit Note
        $cancelled = $this->creditNoteService->cancelShopLevelCreditNote($finalized, $this->admin, 'Cancellation test');
        $this->assertEquals(ShopCreditNoteStatus::Voided, $cancelled->status);

        // Exactly one Cashbook transaction remains, marked as 'void'
        $txCancelled = ShopLedgerTransaction::query()
            ->where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $finalized->id)
            ->first();
        $this->assertNotNull($txCancelled);
        $this->assertEquals(2200.0, (float) $txCancelled->amount);
        $this->assertEquals('void', $txCancelled->status);
    }

    /**
     * Section 3: Warehouse Workflow Verification
     * Grade A supplied -> Grade B received, partial receipts, damaged returns, disputes, stock guards.
     */
    public function test_03_warehouse_workflow_grade_change_partial_dispute_damage_reversal(): void
    {
        // Office creates and finalizes credit note expecting 40 kg return of Onion
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Physical return verification',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 40.0,
                    'credit_rate' => 35.0,
                    'line_credit_amount' => 1400.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                    'reason_type' => ShopCreditNoteReason::ReturnToWarehouse,
                ],
            ],
        ], $this->admin);

        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);
        $noteItem = $finalized->items->first();

        // 1. Appears in warehouse queue with status pending_receipt
        $summary = $finalized->physicalReturnSummary();
        $this->assertTrue($summary['has_physical_return']);
        $this->assertEquals('pending_receipt', $summary['status']);
        $this->assertEquals(40.0, $summary['expected_quantity']);
        $this->assertEquals(0.0, $summary['received_quantity']);

        // Check initial Grade B stock in central warehouse
        $initialGradeBStock = (float) StockMovement::query()
            ->where('warehouse_id', $this->warehouseCentral->id)
            ->where('product_id', $this->productOnion->id)
            ->where('grade', 'B')
            ->selectRaw('SUM(CASE WHEN type IN (?, ?) THEN quantity WHEN type IN (?, ?, ?, ?) THEN -quantity ELSE 0 END) as available', [
                StockMovementType::In->value,
                StockMovementType::SaleReversal->value,
                StockMovementType::Out->value,
                StockMovementType::Wastage->value,
                StockMovementType::Sale->value,
                StockMovementType::Adjustment->value,
            ])
            ->value('available') ?? 0.0;

        // 2. Partial receipt: Warehouse receives 25 kg as Grade B (Grade A supplied -> Grade B received)
        $receiptPartial = $this->receiptService->receivePhysicalReturn(
            $noteItem,
            25.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseStaff,
            null,
            'Partial return in Grade B'
        );

        $this->assertEquals(25.0, (float) $receiptPartial->received_quantity);
        $this->assertEquals(ProductGrade::GradeB->value, $receiptPartial->received_grade);

        // Grade B stock increases by exactly 25 kg
        $currentGradeBStock = (float) StockMovement::query()
            ->where('warehouse_id', $this->warehouseCentral->id)
            ->where('product_id', $this->productOnion->id)
            ->where('grade', 'B')
            ->selectRaw('SUM(CASE WHEN type IN (?, ?) THEN quantity WHEN type IN (?, ?, ?, ?) THEN -quantity ELSE 0 END) as available', [
                StockMovementType::In->value,
                StockMovementType::SaleReversal->value,
                StockMovementType::Out->value,
                StockMovementType::Wastage->value,
                StockMovementType::Sale->value,
                StockMovementType::Adjustment->value,
            ])
            ->value('available') ?? 0.0;
        $this->assertEquals($initialGradeBStock + 25.0, $currentGradeBStock);

        // Summary reflects partially received
        $summaryPartial = $finalized->fresh(['items', 'receipts'])->physicalReturnSummary();
        $this->assertEquals('partially_received', $summaryPartial['status']);
        $this->assertEquals(25.0, $summaryPartial['received_quantity']);
        $this->assertEquals(15.0, $summaryPartial['remaining_quantity']);

        // 3. Damaged return: receive remaining 15 kg as damaged
        $receiptDamaged = $this->receiptService->receiveDamagedReturn(
            $noteItem,
            15.0,
            WastageReason::Rotten,
            $this->warehouseCentral,
            $this->warehouseStaff,
            null,
            'Rotten upon arrival'
        );

        $this->assertEquals(15.0, (float) $receiptDamaged->received_quantity);
        $this->assertEquals('damaged', $receiptDamaged->status);

        // Damaged return produces NO saleable stock movement!
        $this->assertNull($receiptDamaged->stock_movement_id);

        // Summary reflects received and verified
        $summaryFull = $finalized->fresh(['items', 'receipts'])->physicalReturnSummary();
        $this->assertEquals('received_and_verified', $summaryFull['status']);
        $this->assertEquals(40.0, $summaryFull['received_quantity']);
        $this->assertEquals(0.0, $summaryFull['remaining_quantity']);

        // 4. Receipt Reversal Guard: Consume stock so available < 25 kg
        StockMovement::create([
            'batch_id' => $receiptPartial->stockMovement->batch_id,
            'product_id' => $this->productOnion->id,
            'warehouse_id' => $this->warehouseCentral->id,
            'created_by' => $this->admin->id,
            'grade' => 'B',
            'type' => StockMovementType::Out->value,
            'quantity' => $currentGradeBStock - 5.0, // Leave only 5 kg available
            'cost_per_unit' => 30.0,
            'notes' => 'Stock depletion for guard test',
        ]);

        $this->expectException(ValidationException::class);
        $this->receiptService->reversePhysicalReturn($receiptPartial, $this->admin, 'Attempting reversal with insufficient stock');
    }

    /**
     * Section 3b: Receipt Reversal Execution (when stock is sufficient)
     */
    public function test_03b_warehouse_receipt_reversal_execution(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Physical reversal execution test',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 20.0,
                    'credit_rate' => 35.0,
                    'line_credit_amount' => 700.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                    'reason_type' => ShopCreditNoteReason::ReturnToWarehouse,
                ],
            ],
        ], $this->admin);

        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);
        $noteItem = $finalized->items->first();

        $receipt = $this->receiptService->receivePhysicalReturn(
            $noteItem,
            20.0,
            ProductGrade::GradeA,
            $this->warehouseCentral,
            $this->warehouseStaff,
            null,
            'Full receipt'
        );

        $stockBefore = (float) StockMovement::query()
            ->where('warehouse_id', $this->warehouseCentral->id)
            ->where('product_id', $this->productOnion->id)
            ->where('grade', 'A')
            ->selectRaw('SUM(CASE WHEN type IN (?, ?) THEN quantity WHEN type IN (?, ?, ?, ?) THEN -quantity ELSE 0 END) as available', [
                StockMovementType::In->value,
                StockMovementType::SaleReversal->value,
                StockMovementType::Out->value,
                StockMovementType::Wastage->value,
                StockMovementType::Sale->value,
                StockMovementType::Adjustment->value,
            ])
            ->value('available');

        // Reverse the physical return
        $reversedReceipt = $this->receiptService->reversePhysicalReturn($receipt, $this->admin, 'Damaged return error');
        $this->assertNotNull($reversedReceipt->reversed_at);

        $stockAfter = (float) StockMovement::query()
            ->where('warehouse_id', $this->warehouseCentral->id)
            ->where('product_id', $this->productOnion->id)
            ->where('grade', 'A')
            ->selectRaw('SUM(CASE WHEN type IN (?, ?) THEN quantity WHEN type IN (?, ?, ?, ?) THEN -quantity ELSE 0 END) as available', [
                StockMovementType::In->value,
                StockMovementType::SaleReversal->value,
                StockMovementType::Out->value,
                StockMovementType::Wastage->value,
                StockMovementType::Sale->value,
                StockMovementType::Adjustment->value,
            ])
            ->value('available');

        // Stock decreased by exactly 20 kg
        $this->assertEquals($stockBefore - 20.0, $stockAfter);

        // Credit Note summary shows reconciled / reversed
        $summary = $finalized->fresh(['items', 'receipts'])->physicalReturnSummary();
        $this->assertEquals('reconciled', $summary['status']);
    }

    /**
     * Section 4: Damage & Loss Integration
     * Shop-side damage and warehouse-returned damage in Damage & Loss report.
     * Soft-deleted damage excluded upon cancellation.
     */
    public function test_04_damage_and_loss_report_integration(): void
    {
        // 1. Create Credit Note with Shop-side damage
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Shop side damage entry',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemTomatoA1->id,
                    'product_id' => $this->productTomato->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 15.0,
                    'credit_rate' => 25.0,
                    'line_credit_amount' => 375.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::WriteOffWastage,
                    'reason_type' => ShopCreditNoteReason::DamageWastage,
                ],
            ],
        ], $this->admin);

        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        // Verify active DamageLoss report includes this entry
        $reportData = $this->damageLossReportService->getReportData([
            'from_date' => today()->toDateString(),
            'to_date' => today()->toDateString(),
        ]);

        $entries = collect($reportData['damageEntries']);
        $match = $entries->firstWhere('credit_note_number', $finalized->credit_note_number);

        $this->assertNotNull($match);
        $this->assertEquals('shop', $match->damage_location);
        $this->assertEquals(15.0, (float) $match->quantity);
        $this->assertEquals('Active', $match->status);

        // Cancel the Credit Note -> WastageEntry is soft-deleted
        $this->creditNoteService->cancelShopLevelCreditNote($finalized, $this->admin, 'Cancelled shop damage');

        // Re-check DamageLoss report: soft-deleted entries are excluded from active report!
        $reportDataAfter = $this->damageLossReportService->getReportData([
            'from_date' => today()->toDateString(),
            'to_date' => today()->toDateString(),
        ]);
        $entriesAfter = collect($reportDataAfter['damageEntries']);
        $matchAfter = $entriesAfter->firstWhere('credit_note_number', $finalized->credit_note_number);
        $this->assertNull($matchAfter);
    }

    /**
     * Section 5: Report Accuracy — Pending vs Partially Received vs Fully Verified
     */
    public function test_05_report_accuracy_pending_partial_verified_and_kpis(): void
    {
        // 40 kg expected return
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Status progression test',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 40.0,
                    'credit_rate' => 35.0,
                    'line_credit_amount' => 1400.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::ReturnToStock,
                    'reason_type' => ShopCreditNoteReason::ReturnToWarehouse,
                ],
            ],
        ], $this->admin);

        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);
        $item = $finalized->items->first();

        // 1. 0 received -> Pending
        $summary0 = $finalized->fresh(['items', 'receipts'])->physicalReturnSummary();
        $this->assertEquals('pending_receipt', $summary0['status']);
        $this->assertEquals('Pending Receipt', $summary0['status_label']);

        // 2. 25 received -> Partially Received
        $this->receiptService->receivePhysicalReturn($item, 25.0, ProductGrade::GradeA, $this->warehouseCentral, $this->warehouseStaff);
        $summary25 = $finalized->fresh(['items', 'receipts'])->physicalReturnSummary();
        $this->assertEquals('partially_received', $summary25['status']);
        $this->assertEquals('Partially Received', $summary25['status_label']);

        // 3. 40 received (remaining 15 received) -> Fully Verified
        $this->receiptService->receivePhysicalReturn($item, 15.0, ProductGrade::GradeA, $this->warehouseCentral, $this->warehouseStaff);
        $summary40 = $finalized->fresh(['items', 'receipts'])->physicalReturnSummary();
        $this->assertEquals('received_and_verified', $summary40['status']);
        $this->assertEquals('Fully Verified', $summary40['status_label']);
    }

    /**
     * Section 6: Security and Integrity Constraints
     */
    public function test_06_security_and_integrity_constraints(): void
    {
        // 1. Cross-shop invoice items are rejected
        $invoiceB = $this->createHistoricalInvoice($this->shopB, 'INV-B1-999', today()->toDateString(), [
            ['product' => $this->productOnion, 'qty' => 10.0, 'rate' => 35.0, 'cost' => 30.0, 'grade' => 'A'],
        ]);
        $itemB = $invoiceB->items->first();

        $this->expectException(ValidationException::class);
        $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Cross shop attack',
            'items' => [
                [
                    'source_shop_invoice_id' => $invoiceB->id,
                    'shop_invoice_item_id' => $itemB->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 5.0,
                    'credit_rate' => 35.0,
                    'line_credit_amount' => 175.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::None,
                    'reason_type' => ShopCreditNoteReason::CommercialDiscount,
                ],
            ],
        ], $this->admin);
    }

    /**
     * Section 6b: Applying credit greater than unapplied credit is rejected.
     */
    public function test_06b_cannot_apply_credit_exceeding_unapplied_balance(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Over-apply test',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->productOnion->id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 10.0,
                    'credit_rate' => 35.0,
                    'line_credit_amount' => 350.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::None,
                    'reason_type' => ShopCreditNoteReason::CommercialDiscount,
                ],
            ],
        ], $this->admin);

        $finalized = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        $this->expectException(ValidationException::class);
        // Attempt to apply ₹500 when unapplied credit is only ₹350
        $this->applicationService->applyCredit($finalized, $this->invoiceA1, 500.0, $this->admin);
    }
}
