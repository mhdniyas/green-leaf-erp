<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
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
use App\Services\Purchasing\DamageLossReportService;
use App\Services\ShopInvoices\ShopCreditApplicationService;
use App\Services\ShopInvoices\ShopCreditNoteService;
use App\Services\ShopInvoices\ShopCreditNoteWarehouseReceiptService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCreditNoteRegisterReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $accountsUser;

    private User $warehouseUser;

    private User $unauthorizedUser;

    private Shop $shopA;

    private Shop $shopB;

    private Warehouse $warehouseCentral;

    private Product $productOnion;

    private Product $productTomato;

    private ShopInvoice $invoiceA1;

    private ShopInvoiceItem $itemOnionA1;

    private ShopCreditNoteService $creditNoteService;

    private ShopCreditApplicationService $applicationService;

    private ShopCreditNoteWarehouseReceiptService $receiptService;

    private StockLedgerService $stockLedgerService;

    private InvoiceCashbookProjectionService $projectionService;

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

        $this->accountsUser = User::factory()->create([
            'name' => 'Kochi Accounts Officer',
        ]);
        $this->accountsUser->assignRole('accounts');

        $this->warehouseUser = User::factory()->create([
            'name' => 'Kochi Warehouse Operator',
        ]);
        $this->warehouseUser->assignRole('warehouse_receiver');

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
        $this->stockLedgerService = app(StockLedgerService::class);
        $this->damageLossReportService = app(DamageLossReportService::class);

        $this->productOnion = Product::factory()->create([
            'name' => 'Big Onion',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouseCentral->id,
        ]);

        $this->productTomato = Product::factory()->create([
            'name' => 'Tomato Local',
            'unit' => 'kg',
            'default_warehouse_id' => $this->warehouseCentral->id,
        ]);

        // Initial inventory batch: 300 kg Grade A
        $batch = StockBatch::factory()->create([
            'product_id' => $this->productOnion->id,
            'warehouse_id' => $this->warehouseCentral->id,
            'purchase_grade' => 'A',
            'status' => BatchStatus::Sorted->value,
            'warehouse_receive_pending' => false,
            'cost_per_kg' => 40.00,
            'total_kg' => 300.0,
        ]);

        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $this->productOnion->id,
            'warehouse_id' => $this->warehouseCentral->id,
            'grade' => 'A',
            'type' => StockMovementType::In->value,
            'quantity' => 300.0,
            'cost_per_unit' => 40.00,
            'created_by' => $this->admin->id,
            'notes' => 'Initial batch stock in',
        ]);

        // Invoice A1: 100 kg @ Rs 55 = Rs 5500
        $this->invoiceA1 = $this->createFinalizedInvoice(
            $this->shopA,
            'SINV-REG-101',
            today()->toDateString(),
            5500.00,
            0.00
        );

        $this->itemOnionA1 = $this->createInvoiceItem(
            $this->invoiceA1,
            $this->productOnion,
            'A',
            100.0,
            55.00
        );
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

        return ShopInvoiceItem::query()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_grade' => $grade,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => $qty,
            'price_quantity' => $qty,
            'delivered_qty' => $qty,
            'delivered_price_quantity' => $qty,
            'unit_price' => $price,
            'line_subtotal' => round($qty * $price, 2),
            'final_line_total' => round($qty * $price, 2),
        ]);
    }

    private function createFinalizedCreditNote(
        ShopInvoiceItem $sourceItem,
        float $creditQty = 20.0,
        float $creditRate = 50.0,
        ?string $date = null,
        bool $returnToStock = false
    ): ShopCreditNote {
        $draft = $this->creditNoteService->createShopLevelDraft($sourceItem->invoice->shop, [
            'credit_note_date' => $date ?? today()->toDateString(),
            'notes' => 'Testing register notes',
            'items' => [
                [
                    'source_shop_invoice_id' => $sourceItem->shop_invoice_id,
                    'shop_invoice_item_id' => $sourceItem->id,
                    'product_id' => $sourceItem->product_id,
                    'product_grade' => $sourceItem->product_grade,
                    'unit' => 'kg',
                    'credit_quantity' => $creditQty,
                    'credit_rate' => $creditRate,
                    'line_credit_amount' => round($creditQty * $creditRate, 2),
                    'inventory_action' => $returnToStock ? ShopCreditNoteInventoryAction::ReturnToStock : ShopCreditNoteInventoryAction::None,
                    'reason_type' => $returnToStock ? ShopCreditNoteReason::ReturnToWarehouse : ShopCreditNoteReason::CommercialDiscount,
                ],
            ],
        ], $this->admin);

        return $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);
    }

    /**
     * Test 1: Sidebar links resolve correctly in both Cashbook and Main ERP layouts.
     */
    public function test_01_sidebar_links_resolve_correctly(): void
    {
        $this->actingAs($this->admin);

        // Cashbook sidebar renders Credit Notes link
        $cashbookResponse = $this->get(route('admin.cashbook.reports.shops.credit-notes'));
        $cashbookResponse->assertOk();
        $cashbookResponse->assertSee(route('admin.cashbook.reports.shops.credit-notes'));

        // Main ERP layout renders Sales navigation with Shop Credit Notes
        $mainResponse = $this->get(route('sales.invoices.index'));
        $mainResponse->assertOk();
        $mainResponse->assertSee(route('admin.cashbook.reports.shops.credit-notes'));
        $mainResponse->assertSee('Shop Credit Notes');
    }

    /**
     * Test 2: Unauthorized navigation and report access rejected.
     */
    public function test_02_unauthorized_navigation_and_report_access_rejected(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->getJson(route('admin.cashbook.reports.shops.credit-notes'))
            ->assertForbidden();
    }

    /**
     * Test 3: Register displays shop-level Credit Notes with correct metadata.
     */
    public function test_03_register_displays_shop_level_credit_notes(): void
    {
        $note = $this->createFinalizedCreditNote($this->itemOnionA1, 20.0, 50.0); // 1,000.00

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes', ['timeframe' => 'today']));

        $response->assertOk();
        $response->assertSee($note->credit_note_number);
        $response->assertSee('Kochi Central Shop');
        $response->assertSee('SINV-REG-101');
        $response->assertSee('1,000.00');
        $response->assertSee('Shop-Level');
        $response->assertSee('approved');
    }

    /**
     * Test 4: Legacy Credit Notes remain accessible in the register.
     */
    public function test_04_legacy_credit_notes_remain_accessible(): void
    {
        $draft = $this->creditNoteService->createDraft(
            $this->invoiceA1,
            [
                'credit_note_date' => today()->toDateString(),
                'reason_type' => ShopCreditNoteReason::Shortage,
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->itemOnionA1->id,
                        'revised_quantity' => 95.0,
                        'revised_unit_price' => 55.0,
                        'inventory_action' => ShopCreditNoteInventoryAction::None,
                    ],
                ],
                'notes' => 'Legacy invoice note',
            ],
            $this->admin->id
        );

        $legacyNote = $this->creditNoteService->approve($draft, $this->admin);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes', ['timeframe' => 'today']));

        $response->assertOk();
        $response->assertSee($legacyNote->credit_note_number);
        $response->assertSee('Legacy Linked');
        $response->assertSee('275.00');
    }

    /**
     * Test 5: Draft/Edit/View actions work properly.
     */
    public function test_05_draft_edit_view_actions_work(): void
    {
        // 1. Create a draft
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'notes' => 'Pending review draft',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->itemOnionA1->product_id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 10.0,
                    'credit_rate' => 50.0,
                    'line_credit_amount' => 500.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::None,
                    'reason_type' => ShopCreditNoteReason::CommercialDiscount,
                ],
            ],
        ], $this->admin);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes', ['status' => 'draft', 'timeframe' => 'today']));
        $response->assertOk();
        $response->assertSee('Edit Draft');
        $response->assertSee(route('admin.cashbook.reports.shops.credit-notes.drafts.edit', $draft));

        // 2. Finalized note shows View & Apply
        $finalized = $this->createFinalizedCreditNote($this->itemOnionA1, 10.0, 50.0);
        $responseFin = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes', ['status' => 'approved', 'timeframe' => 'today']));
        $responseFin->assertOk();
        $responseFin->assertSee('View & Apply');
        $responseFin->assertSee(route('admin.cashbook.reports.shops.credit-notes.show', $finalized));
    }

    /**
     * Test 6: Today filter works and isolates today's notes.
     */
    public function test_06_today_filter_works(): void
    {
        $todayNote = $this->createFinalizedCreditNote($this->itemOnionA1, 10.0, 50.0, today()->toDateString());
        $yesterdayNote = $this->createFinalizedCreditNote($this->itemOnionA1, 10.0, 50.0, today()->subDay()->toDateString());

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes', ['timeframe' => 'today']));

        $response->assertOk();
        $response->assertSee($todayNote->credit_note_number);
        $response->assertDontSee($yesterdayNote->credit_note_number);
    }

    /**
     * Test 7: Previous month filter works.
     */
    public function test_07_previous_month_filter_works(): void
    {
        $prevMonthDate = today()->subMonth()->startOfMonth()->addDays(5)->toDateString();
        $prevMonthNote = $this->createFinalizedCreditNote($this->itemOnionA1, 10.0, 50.0, $prevMonthDate);
        $thisMonthNote = $this->createFinalizedCreditNote($this->itemOnionA1, 10.0, 50.0, today()->toDateString());

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes', ['timeframe' => 'prev_month']));

        $response->assertOk();
        $response->assertSee($prevMonthNote->credit_note_number);
        $response->assertDontSee($thisMonthNote->credit_note_number);
    }

    /**
     * Test 8: Custom date range works.
     */
    public function test_08_custom_date_range_works(): void
    {
        $rangeStart = today()->subDays(10)->toDateString();
        $rangeEnd = today()->subDays(5)->toDateString();

        $inRangeNote = $this->createFinalizedCreditNote($this->itemOnionA1, 10.0, 50.0, today()->subDays(7)->toDateString());
        $outRangeNote = $this->createFinalizedCreditNote($this->itemOnionA1, 10.0, 50.0, today()->subDays(2)->toDateString());

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes', [
            'timeframe' => 'custom',
            'start_date' => $rangeStart,
            'end_date' => $rangeEnd,
        ]));

        $response->assertOk();
        $response->assertSee($inRangeNote->credit_note_number);
        $response->assertDontSee($outRangeNote->credit_note_number);
    }

    /**
     * Test 9: Shop and invoice search work.
     */
    public function test_09_shop_and_invoice_search_work(): void
    {
        $invB = $this->createFinalizedInvoice($this->shopB, 'SINV-CAL-999', today()->toDateString(), 3000.0, 0.0);
        $itemB = $this->createInvoiceItem($invB, $this->productTomato, 'A', 50.0, 60.0);

        $noteA = $this->createFinalizedCreditNote($this->itemOnionA1, 10.0, 50.0);
        $noteB = $this->createFinalizedCreditNote($itemB, 10.0, 60.0);

        // Filter by Shop A
        $responseShop = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes', [
            'shop_id' => $this->shopA->id,
            'timeframe' => 'today',
        ]));
        $responseShop->assertOk();
        $responseShop->assertSee($noteA->credit_note_number);
        $responseShop->assertDontSee($noteB->credit_note_number);

        // Search by Invoice Number
        $responseInv = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes', [
            'search' => 'CAL-999',
            'timeframe' => 'today',
        ]));
        $responseInv->assertOk();
        $responseInv->assertSee($noteB->credit_note_number);
        $responseInv->assertDontSee($noteA->credit_note_number);
    }

    /**
     * Test 10: Drafts are excluded from active finalized credit totals.
     */
    public function test_10_drafts_excluded_from_active_credit_totals(): void
    {
        $this->createFinalizedCreditNote($this->itemOnionA1, 20.0, 50.0); // 1,000.00 finalized

        // Create 2,500.00 draft
        $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'product_id' => $this->itemOnionA1->product_id,
                    'product_grade' => 'A',
                    'unit' => 'kg',
                    'credit_quantity' => 50.0,
                    'credit_rate' => 50.0,
                    'line_credit_amount' => 2500.0,
                    'inventory_action' => ShopCreditNoteInventoryAction::None,
                    'reason_type' => ShopCreditNoteReason::CommercialDiscount,
                ],
            ],
        ], $this->admin);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes', ['timeframe' => 'today']));
        $response->assertOk();

        $summary = $response->viewData('summary');
        $this->assertEquals(1000.00, $summary['finalized_credit_amount']);
        $this->assertEquals(1, $summary['finalized_count']);
        $this->assertEquals(1, $summary['draft_count']);
        $this->assertEquals(2500.00, $summary['draft_amount']);
    }

    /**
     * Test 11: Voided Credit Notes are excluded from active credit totals and shown separately.
     */
    public function test_11_voided_credit_notes_excluded_from_active_totals(): void
    {
        $activeNote = $this->createFinalizedCreditNote($this->itemOnionA1, 20.0, 50.0); // 1,000.00
        $voidNote = $this->createFinalizedCreditNote($this->itemOnionA1, 15.0, 50.0); // 750.00

        // Cancel the second note
        $this->creditNoteService->cancelShopLevelCreditNote($voidNote, $this->admin, 'Client cancelled claim');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes', ['timeframe' => 'today']));
        $response->assertOk();

        $summary = $response->viewData('summary');
        $this->assertEquals(1000.00, $summary['finalized_credit_amount']);
        $this->assertEquals(1, $summary['finalized_count']);
        $this->assertEquals(1, $summary['voided_count']);
        $this->assertEquals(750.00, $summary['voided_credit_amount']);
    }

    /**
     * Test 12: Applied and unapplied amounts reconcile: Finalized = Applied + Unapplied.
     */
    public function test_12_applied_and_unapplied_amounts_reconcile(): void
    {
        // Credit note = 1,700.00 (34 kg @ 50)
        $note = $this->createFinalizedCreditNote($this->itemOnionA1, 34.0, 50.0);
        $this->assertEquals(1700.00, (float) $note->total_credit_amount);

        // Apply 1,500.00 to Invoice A1
        $this->applicationService->applyCredit($note, $this->invoiceA1, 1500.00, $this->admin);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes', ['timeframe' => 'today']));
        $response->assertOk();

        $summary = $response->viewData('summary');
        $this->assertEquals(1700.00, $summary['finalized_credit_amount']);
        $this->assertEquals(1500.00, $summary['applied_credit_amount']);
        $this->assertEquals(200.00, $summary['unapplied_credit_amount']);

        // Sum equality guarantee
        $this->assertEquals(
            $summary['finalized_credit_amount'],
            $summary['applied_credit_amount'] + $summary['unapplied_credit_amount']
        );
    }

    /**
     * Test 13: Application reversals reflected correctly in register balances.
     */
    public function test_13_application_reversals_reflected_correctly(): void
    {
        $note = $this->createFinalizedCreditNote($this->itemOnionA1, 20.0, 50.0); // 1,000.00
        $app = $this->applicationService->applyCredit($note, $this->invoiceA1, 600.00, $this->admin);

        // Reverse application
        $this->applicationService->reverseCreditApplication($app, $this->admin, 'Wrong bill chosen');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes', ['timeframe' => 'today']));
        $response->assertOk();

        $summary = $response->viewData('summary');
        $this->assertEquals(1000.00, $summary['finalized_credit_amount']);
        $this->assertEquals(0.00, $summary['applied_credit_amount']);
        $this->assertEquals(1000.00, $summary['unapplied_credit_amount']);
    }

    /**
     * Test 14: Warehouse receipt statuses display correctly.
     */
    public function test_14_warehouse_receipt_statuses_display_correctly(): void
    {
        // 1. Pending return
        $notePending = $this->createFinalizedCreditNote($this->itemOnionA1, 20.0, 50.0, returnToStock: true);

        $responsePending = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes', ['timeframe' => 'today']));
        $responsePending->assertOk();
        $responsePending->assertSee('Pending Receipt');
        $responsePending->assertSee('0 / 20 kg');

        // 2. Receive 20 kg -> Verified
        $this->receiptService->receivePhysicalReturn(
            $notePending->items->first(),
            20.0,
            ProductGrade::GradeB,
            $this->warehouseCentral,
            $this->warehouseUser
        );

        $responseVerified = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes', ['timeframe' => 'today']));
        $responseVerified->assertOk();
        $responseVerified->assertSee('Fully Verified');
        $responseVerified->assertSee('20 / 20 kg');
    }

    /**
     * Test 15: Damage & Loss report includes valid damage entries from shop credit notes.
     */
    public function test_15_damage_loss_report_includes_valid_damage_entries(): void
    {
        // Warehouse damaged return
        $note = $this->createFinalizedCreditNote($this->itemOnionA1, 25.0, 50.0, returnToStock: true);
        $this->receiptService->receiveDamagedReturn(
            $note->items->first(),
            25.0,
            WastageReason::TransitDamage,
            $this->warehouseCentral,
            $this->warehouseUser,
            remarks: 'Crushed in transport'
        );

        $report = $this->damageLossReportService->getReportData([
            'period' => 'today',
            'product_id' => $this->productOnion->id,
        ]);

        $this->assertNotEmpty($report['damageEntries']);
        $entry = $report['damageEntries']->first();

        $this->assertEquals(25.0, (float) $entry->quantity);
        $this->assertEquals($note->credit_note_number, $entry->credit_note_number);
        $this->assertEquals('Kochi Central Shop', $entry->shop_name);
        $this->assertEquals('SINV-REG-101', $entry->invoice_number);
        $this->assertEquals('warehouse', $entry->damage_location);
    }

    /**
     * Test 16: Cancelled / reversed damage excluded from active loss in Damage & Loss report.
     */
    public function test_16_cancelled_reversed_damage_excluded_from_active_loss(): void
    {
        $note = $this->createFinalizedCreditNote($this->itemOnionA1, 20.0, 50.0, returnToStock: true);
        $receipt = $this->receiptService->receiveDamagedReturn(
            $note->items->first(),
            20.0,
            WastageReason::Rotten,
            $this->warehouseCentral,
            $this->warehouseUser
        );

        // Before reversal: loss report includes damage
        $beforeReport = $this->damageLossReportService->getReportData([
            'period' => 'today',
            'product_id' => $this->productOnion->id,
        ]);
        $this->assertEquals(20.0, (float) $beforeReport['summary']->total_damaged_kg);

        // Reverse the damaged receipt
        $this->receiptService->reversePhysicalReturn($receipt, $this->warehouseUser, 'Operator recorded wrong item');

        // After reversal: damage excluded
        $afterReport = $this->damageLossReportService->getReportData([
            'period' => 'today',
            'product_id' => $this->productOnion->id,
        ]);
        $this->assertEquals(0.0, (float) $afterReport['summary']->total_damaged_kg);
    }

    /**
     * Test 17: Original invoice totals remain unchanged by credit notes or allocations.
     */
    public function test_17_original_invoice_totals_remain_unchanged(): void
    {
        $origTotal = (float) $this->invoiceA1->final_total;
        $this->assertEquals(5500.00, $origTotal);

        // Create and finalize credit note
        $note = $this->createFinalizedCreditNote($this->itemOnionA1, 20.0, 50.0);
        $this->applicationService->applyCredit($note, $this->invoiceA1, 1000.00, $this->admin);

        $this->invoiceA1->refresh();
        $this->assertEquals(5500.00, (float) $this->invoiceA1->final_total);
    }

    /**
     * Test 18: No duplicate Cashbook amounts: Credit Notes create exactly 1 ledger transaction.
     */
    public function test_18_no_duplicate_cashbook_amounts(): void
    {
        $txBefore = ShopLedgerTransaction::where('reference_type', ShopCreditNote::class)->count();

        $note = $this->createFinalizedCreditNote($this->itemOnionA1, 20.0, 50.0); // 1,000.00
        $this->applicationService->applyCredit($note, $this->invoiceA1, 500.00, $this->admin);
        $this->applicationService->applyCredit($note, $this->invoiceA1, 500.00, $this->admin);

        $txAfter = ShopLedgerTransaction::where('reference_type', ShopCreditNote::class)->count();

        // Exactly 1 financial transaction created at finalization; applications do not create additional transactions
        $this->assertEquals($txBefore + 1, $txAfter);
    }

    /**
     * Test 19: Pagination handles larger datasets efficiently.
     */
    public function test_19_pagination_handles_larger_datasets(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->createFinalizedCreditNote($this->itemOnionA1, 1.0, 50.0);
        }

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shops.credit-notes', ['timeframe' => 'today']));
        $response->assertOk();

        $paginator = $response->viewData('creditNotes');
        $this->assertGreaterThanOrEqual(30, $paginator->total());
        $this->assertEquals(25, $paginator->perPage());
        $this->assertEquals(2, $paginator->lastPage());
    }

    /**
     * Test 20: Existing Cashbook and Shop reports remain fully functional.
     */
    public function test_20_existing_cashbook_and_shop_reports_remain_functional(): void
    {
        $this->actingAs($this->admin);

        // Shop Invoices Report
        $this->get(route('admin.cashbook.reports.shops.invoices'))->assertOk();

        // All Cashbook Reports Hub
        $this->get(route('admin.cashbook.reports'))->assertOk();

        // Damage & Loss Report
        $this->get(route('admin.cashbook.reports-v1.damage-loss'))->assertOk();
    }
}
