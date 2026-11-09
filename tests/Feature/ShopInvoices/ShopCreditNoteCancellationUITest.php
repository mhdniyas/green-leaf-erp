<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\Inventory\ProductGrade;
use App\Enums\ShopInvoice\ShopCreditNoteAccountingMode;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopAccountingPeriodClosure;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\ShopInvoices\ShopCreditApplicationService;
use App\Services\ShopInvoices\ShopCreditNoteService;
use App\Services\ShopInvoices\ShopCreditNoteWarehouseReceiptService;
use App\Services\ShopInvoices\ShopFinalBillService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCreditNoteCancellationUITest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $accountsUser;

    private User $warehouseUser;

    private User $purchaserUser;

    private Shop $shopA;

    private Shop $shopB;

    private Warehouse $warehouseCentral;

    private Product $productOnion;

    private Product $productTomato;

    private ShopInvoice $invoiceA1;

    private ShopInvoice $invoiceA2;

    private ShopInvoiceItem $itemOnionA1;

    private ShopInvoiceItem $itemTomatoA2;

    private ShopCreditNoteService $creditNoteService;

    private ShopCreditApplicationService $applicationService;

    private ShopCreditNoteWarehouseReceiptService $receiptService;

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

        $this->accountsUser = User::factory()->create();
        $this->accountsUser->assignRole('accounts');
        $this->accountsUser->givePermissionTo('cashbook.reports.view');

        $this->warehouseUser = User::factory()->create();
        $this->warehouseUser->assignRole('warehouse_receiver');
        $this->warehouseUser->givePermissionTo('cashbook.reports.view');

        $this->purchaserUser = User::factory()->create();
        $this->purchaserUser->assignRole('purchaser');
        $this->purchaserUser->givePermissionTo('cashbook.reports.view');

        $this->shopA = Shop::factory()->create([
            'code' => 'SHP-A',
            'name' => 'Kochi Central',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $this->shopB = Shop::factory()->create([
            'code' => 'SHP-B',
            'name' => 'Calicut Mart',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $this->warehouseCentral = Warehouse::factory()->create([
            'name' => 'Central Cold Storage',
            'code' => 'WH-CENTRAL',
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->productOnion = Product::factory()->create(['name' => 'Onion Grade A', 'unit' => 'kg']);
        $this->productTomato = Product::factory()->create(['name' => 'Tomato Grade A', 'unit' => 'kg']);

        // Invoices for Shop A
        $this->invoiceA1 = $this->createFinalizedInvoice($this->shopA, 'SINV-A1', today()->subDays(2)->toDateString(), 5000.00);
        $this->itemOnionA1 = $this->createInvoiceItem($this->invoiceA1, $this->productOnion, 'A', 100.0, 50.0);

        $this->invoiceA2 = $this->createFinalizedInvoice($this->shopA, 'SINV-A2', today()->subDays(1)->toDateString(), 3200.00);
        $this->itemTomatoA2 = $this->createInvoiceItem($this->invoiceA2, $this->productTomato, 'A', 80.0, 40.0);

        $this->creditNoteService = app(ShopCreditNoteService::class);
        $this->applicationService = app(ShopCreditApplicationService::class);
        $this->receiptService = app(ShopCreditNoteWarehouseReceiptService::class);
        $this->finalBillService = app(ShopFinalBillService::class);
    }

    private function createFinalizedInvoice(Shop $shop, string $invoiceNumber, ?string $date = null, float $total = 5000.00): ShopInvoice
    {
        $businessDate = $date ?? today()->toDateString();
        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $businessDate,
        ]);

        return ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'business_date' => $businessDate,
            'status' => 'finalized',
            'delivery_status' => 'received_full',
            'payment_status' => 'unpaid',
            'subtotal' => $total,
            'final_total' => $total,
            'balance_amount' => $total,
            'finalized_by' => $this->admin->id,
            'finalized_at' => now(),
        ]);
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
            'unit_cost' => $price * 0.8,
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

    private function createAndFinalizeStandardNote(float $creditTotal = 500.00, bool $returnToStock = false): ShopCreditNote
    {
        $qty = $creditTotal / 50.0;
        $invAction = $returnToStock ? ShopCreditNoteInventoryAction::ReturnToStock : ShopCreditNoteInventoryAction::None;
        $reason = $returnToStock ? ShopCreditNoteReason::ReturnToWarehouse : ShopCreditNoteReason::PriceCorrection;

        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'notes' => 'Standard finalized note for cancellation testing',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => $qty,
                    'credit_rate' => 50.0,
                    'reason_type' => $reason->value,
                    'inventory_action' => $invAction->value,
                ],
            ],
        ], $this->admin);

        return $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);
    }

    /**
     * 1. Authorized application reversal restores unapplied balance and marks application reversed.
     */
    public function test_01_authorized_application_reversal(): void
    {
        $note = $this->createAndFinalizeStandardNote(500.00);
        $app = $this->applicationService->applyCredit($note, $this->invoiceA1, 200.00, $this->admin);

        $this->assertEquals(200.00, $note->fresh()->appliedCreditTotal());
        $this->assertEquals(300.00, $note->fresh()->unappliedCreditTotal());

        $response = $this->actingAs($this->accountsUser)->post(
            route('admin.cashbook.reports.shops.credit-notes.applications.reverse', [
                'creditNote' => $note,
                'application' => $app,
            ]),
            ['reason' => 'Allocated to incorrect invoice by error']
        );

        $response->assertRedirect(route('admin.cashbook.reports.shops.credit-notes.show', $note));

        $freshApp = $app->fresh();
        $this->assertTrue($freshApp->isReversed());
        $this->assertEquals($this->accountsUser->id, $freshApp->reversed_by);
        $this->assertEquals('Allocated to incorrect invoice by error', $freshApp->reversal_reason);
        $this->assertNotNull($freshApp->reversed_at);

        $freshNote = $note->fresh();
        $this->assertEquals(0.00, $freshNote->appliedCreditTotal());
        $this->assertEquals(500.00, $freshNote->unappliedCreditTotal());

        // Target invoice remaining balance restored
        $bill = $this->finalBillService->calculate($this->invoiceA1->fresh());
        $this->assertEquals(5000.00, round($bill->remainingBalance, 2));
    }

    /**
     * 2. Unauthorized reversal rejected (403 for warehouse/purchaser).
     */
    public function test_02_unauthorized_reversal_rejected(): void
    {
        $note = $this->createAndFinalizeStandardNote(500.00);
        $app = $this->applicationService->applyCredit($note, $this->invoiceA1, 200.00, $this->admin);

        $response = $this->actingAs($this->warehouseUser)->post(
            route('admin.cashbook.reports.shops.credit-notes.applications.reverse', [
                'creditNote' => $note,
                'application' => $app,
            ]),
            ['reason' => 'Unauthorized attempt']
        );

        $response->assertForbidden();
        $this->assertFalse($app->fresh()->isReversed());

        $responsePurchaser = $this->actingAs($this->purchaserUser)->post(
            route('admin.cashbook.reports.shops.credit-notes.applications.reverse', [
                'creditNote' => $note,
                'application' => $app,
            ]),
            ['reason' => 'Unauthorized purchaser attempt']
        );

        $responsePurchaser->assertForbidden();
    }

    /**
     * 3. Mandatory reversal reason required.
     */
    public function test_03_mandatory_reversal_reason(): void
    {
        $note = $this->createAndFinalizeStandardNote(500.00);
        $app = $this->applicationService->applyCredit($note, $this->invoiceA1, 200.00, $this->admin);

        $response = $this->actingAs($this->accountsUser)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.applications.reverse', [
                'creditNote' => $note,
                'application' => $app,
            ]),
            ['reason' => '']
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reason']);
        $this->assertFalse($app->fresh()->isReversed());
    }

    /**
     * 4. Application history preserved after reversal.
     */
    public function test_04_application_history_preserved(): void
    {
        $note = $this->createAndFinalizeStandardNote(500.00);
        $app = $this->applicationService->applyCredit($note, $this->invoiceA1, 200.00, $this->admin);

        $this->actingAs($this->accountsUser)->post(
            route('admin.cashbook.reports.shops.credit-notes.applications.reverse', [
                'creditNote' => $note,
                'application' => $app,
            ]),
            ['reason' => 'Reversal audit test']
        );

        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.reports.shops.credit-notes.show', $note)
        );

        $response->assertOk();
        $response->assertSee('SINV-A1');
        $response->assertSee('Reversed');
        $response->assertSee('Reversal audit test');
    }

    /**
     * 5. Applied and unapplied balance updates correctly when one of multiple applications is reversed.
     */
    public function test_05_applied_unapplied_balance_updates(): void
    {
        // Credit note total: 1700
        $note = $this->createAndFinalizeStandardNote(1700.00);

        // Apply 1000 to Inv A1, 500 to Inv A2
        $app1 = $this->applicationService->applyCredit($note, $this->invoiceA1, 1000.00, $this->admin);
        $app2 = $this->applicationService->applyCredit($note, $this->invoiceA2, 500.00, $this->admin);

        $this->assertEquals(1500.00, $note->fresh()->appliedCreditTotal());
        $this->assertEquals(200.00, $note->fresh()->unappliedCreditTotal());

        // Office reverses App 1 (1000.00)
        $this->actingAs($this->accountsUser)->post(
            route('admin.cashbook.reports.shops.credit-notes.applications.reverse', [
                'creditNote' => $note,
                'application' => $app1,
            ]),
            ['reason' => 'Office reallocation required']
        );

        $freshNote = $note->fresh();
        $this->assertTrue($app1->fresh()->isReversed());
        $this->assertTrue($app2->fresh()->isActive());

        // Active applied is 500, unapplied is 1200, total remains 1700
        $this->assertEquals(500.00, $freshNote->appliedCreditTotal());
        $this->assertEquals(1200.00, $freshNote->unappliedCreditTotal());
        $this->assertEquals(1700.00, (float) $freshNote->total_credit_amount);
    }

    /**
     * 6. Double reversal blocked.
     */
    public function test_06_double_reversal_blocked(): void
    {
        $note = $this->createAndFinalizeStandardNote(500.00);
        $app = $this->applicationService->applyCredit($note, $this->invoiceA1, 200.00, $this->admin);

        // First reversal
        $res1 = $this->actingAs($this->accountsUser)->post(
            route('admin.cashbook.reports.shops.credit-notes.applications.reverse', [
                'creditNote' => $note,
                'application' => $app,
            ]),
            ['reason' => 'First reversal']
        );
        $res1->assertRedirect();

        // Second reversal call
        $res2 = $this->actingAs($this->accountsUser)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.applications.reverse', [
                'creditNote' => $note,
                'application' => $app,
            ]),
            ['reason' => 'Second attempt']
        );
        $res2->assertStatus(422);
    }

    /**
     * 7. Reversed application action button hidden or disabled on view.
     */
    public function test_07_reversed_application_action_hidden_or_disabled(): void
    {
        $note = $this->createAndFinalizeStandardNote(500.00);
        $app = $this->applicationService->applyCredit($note, $this->invoiceA1, 200.00, $this->admin);

        // Active application has reverse action button
        $showBefore = $this->actingAs($this->admin)->get(
            route('admin.cashbook.reports.shops.credit-notes.show', $note)
        );
        $showBefore->assertOk();
        $showBefore->assertSee('openReverseModal('.$app->id, false);

        // Reverse it
        $this->applicationService->reverseCreditApplication($app, $this->admin, 'Audit test');

        // Reversed application no longer has the reverse button
        $showAfter = $this->actingAs($this->admin)->get(
            route('admin.cashbook.reports.shops.credit-notes.show', $note)
        );
        $showAfter->assertOk();
        $showAfter->assertDontSee('openReverseModal('.$app->id, false);
    }

    /**
     * 8. Authorized credit note cancellation marks note as voided and records audit fields.
     */
    public function test_08_authorized_credit_note_cancellation(): void
    {
        $note = $this->createAndFinalizeStandardNote(500.00);

        $response = $this->actingAs($this->accountsUser)->post(
            route('admin.cashbook.reports.shops.credit-notes.cancel', $note),
            ['reason' => 'Issued to wrong shop by mistake']
        );

        $response->assertRedirect(route('admin.cashbook.reports.shops.credit-notes.show', $note->fresh()));

        $fresh = $note->fresh();
        $this->assertEquals(ShopCreditNoteStatus::Voided, $fresh->status);
        $this->assertEquals($this->accountsUser->id, $fresh->voided_by);
        $this->assertEquals('Issued to wrong shop by mistake', $fresh->void_reason);
        $this->assertNotNull($fresh->voided_at);
    }

    /**
     * 9. Unauthorized cancellation rejected (403 for warehouse/purchaser).
     */
    public function test_09_unauthorized_cancellation_rejected(): void
    {
        $note = $this->createAndFinalizeStandardNote(500.00);

        $response = $this->actingAs($this->warehouseUser)->post(
            route('admin.cashbook.reports.shops.credit-notes.cancel', $note),
            ['reason' => 'Unauthorized warehouse cancel']
        );

        $response->assertForbidden();
        $this->assertEquals(ShopCreditNoteStatus::Approved, $note->fresh()->status);

        $responsePurchaser = $this->actingAs($this->purchaserUser)->post(
            route('admin.cashbook.reports.shops.credit-notes.cancel', $note),
            ['reason' => 'Unauthorized purchaser cancel']
        );

        $responsePurchaser->assertForbidden();
    }

    /**
     * 10. Mandatory cancellation reason required.
     */
    public function test_10_mandatory_cancellation_reason(): void
    {
        $note = $this->createAndFinalizeStandardNote(500.00);

        $response = $this->actingAs($this->accountsUser)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.cancel', $note),
            ['reason' => '']
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reason']);
        $this->assertEquals(ShopCreditNoteStatus::Approved, $note->fresh()->status);
    }

    /**
     * 11. Cancellation automatically reverses all active applications and sets balances to 0.
     */
    public function test_11_cancellation_reverses_all_active_applications(): void
    {
        $note = $this->createAndFinalizeStandardNote(1000.00);

        $app1 = $this->applicationService->applyCredit($note, $this->invoiceA1, 400.00, $this->admin);
        $app2 = $this->applicationService->applyCredit($note, $this->invoiceA2, 300.00, $this->admin);

        $this->actingAs($this->accountsUser)->post(
            route('admin.cashbook.reports.shops.credit-notes.cancel', $note),
            ['reason' => 'Credit note cancelled on dispute']
        );

        $this->assertTrue($app1->fresh()->isReversed());
        $this->assertTrue($app2->fresh()->isReversed());
        $this->assertEquals($this->accountsUser->id, $app1->fresh()->reversed_by);
        $this->assertEquals($this->accountsUser->id, $app2->fresh()->reversed_by);

        $freshNote = $note->fresh();
        $this->assertEquals(0.00, $freshNote->appliedCreditTotal());
        $this->assertEquals(0.00, $freshNote->unappliedCreditTotal());
    }

    /**
     * 12. Cashbook credit reversed exactly once.
     */
    public function test_12_cashbook_credit_reversed_exactly_once(): void
    {
        $note = $this->createAndFinalizeStandardNote(500.00);

        // Active ledger transaction before cancellation
        $txBefore = ShopLedgerTransaction::where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $note->id)
            ->first();
        $this->assertNotNull($txBefore);
        $this->assertEquals('approved', $txBefore->status);

        $this->actingAs($this->accountsUser)->post(
            route('admin.cashbook.reports.shops.credit-notes.cancel', $note),
            ['reason' => 'Reversal check']
        );

        // Transaction is now voided
        $txAfter = ShopLedgerTransaction::where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $note->id)
            ->first();
        $this->assertEquals('void', $txAfter->status);

        // Exactly one ledger transaction exists for this credit note
        $totalTxCount = ShopLedgerTransaction::where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $note->id)
            ->count();
        $this->assertEquals(1, $totalTxCount);
    }

    /**
     * 13. Cancellation blocked when Credit Note has unreconciled warehouse receipts.
     */
    public function test_13_cancellation_blocked_with_unreconciled_receipts(): void
    {
        $note = $this->createAndFinalizeStandardNote(500.00, returnToStock: true);
        $item = $note->items->first();

        // Warehouse receives return
        $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 10.0,
            grade: ProductGrade::GradeA,
            warehouse: $this->warehouseCentral,
            actor: $this->admin,
            remarks: 'Physical return received at central'
        );

        $this->assertTrue($note->fresh()->hasUnreconciledPhysicalReturns());

        $response = $this->actingAs($this->accountsUser)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.cancel', $note),
            ['reason' => 'Attempt to cancel with active receipts']
        );

        $response->assertStatus(422);
        $this->assertEquals(ShopCreditNoteStatus::Approved, $note->fresh()->status);
    }

    /**
     * 14. Cancellation allowed after physical reconciliation (receipt reversal).
     */
    public function test_14_cancellation_allowed_after_physical_reconciliation(): void
    {
        $note = $this->createAndFinalizeStandardNote(500.00, returnToStock: true);
        $item = $note->items->first();

        $receipt = $this->receiptService->receivePhysicalReturn(
            item: $item,
            quantity: 10.0,
            grade: ProductGrade::GradeA,
            warehouse: $this->warehouseCentral,
            actor: $this->admin,
            remarks: 'Physical return'
        );

        // Warehouse reverses the receipt
        $this->receiptService->reversePhysicalReturn($receipt, $this->admin, 'Wrong warehouse receipt');

        $this->assertFalse($note->fresh()->hasUnreconciledPhysicalReturns());

        $response = $this->actingAs($this->accountsUser)->post(
            route('admin.cashbook.reports.shops.credit-notes.cancel', $note),
            ['reason' => 'Cancelled after physical return was reversed']
        );

        $response->assertRedirect();
        $this->assertEquals(ShopCreditNoteStatus::Voided, $note->fresh()->status);
    }

    /**
     * 15. Cancelled note becomes read-only and action buttons are disabled.
     */
    public function test_15_cancelled_note_becomes_read_only(): void
    {
        $note = $this->createAndFinalizeStandardNote(500.00);

        $this->actingAs($this->accountsUser)->post(
            route('admin.cashbook.reports.shops.credit-notes.cancel', $note),
            ['reason' => 'Void note test']
        );

        $fresh = $note->fresh();

        $showResponse = $this->actingAs($this->admin)->get(
            route('admin.cashbook.reports.shops.credit-notes.show', $fresh)
        );

        $showResponse->assertOk();
        $showResponse->assertSee('Credit Note Cancelled / Voided');
        $showResponse->assertSee('Void note test');
        $showResponse->assertDontSee('Apply Credit to Invoices');
        $showResponse->assertDontSee('Cancel Credit Note');

        // Cannot apply credit to voided note
        $applyResponse = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.apply', $fresh),
            [
                'allocations' => [
                    [
                        'invoice_id' => $this->invoiceA1->id,
                        'amount' => 100.00,
                    ],
                ],
            ]
        );
        $applyResponse->assertStatus(422);

        // Cannot update draft of voided note
        $updateResponse = $this->actingAs($this->admin)->putJson(
            route('admin.cashbook.reports.shops.credit-notes.drafts.update', $fresh),
            [
                'shop_id' => $this->shopA->id,
                'credit_note_date' => today()->toDateString(),
                'expected_lock_version' => 1,
                'items' => [],
            ]
        );
        $updateResponse->assertStatus(422);
    }

    /**
     * 16. Repeated cancellation safely rejected.
     */
    public function test_16_repeated_cancellation_safely_rejected(): void
    {
        $note = $this->createAndFinalizeStandardNote(500.00);

        // First cancellation
        $this->actingAs($this->accountsUser)->post(
            route('admin.cashbook.reports.shops.credit-notes.cancel', $note),
            ['reason' => 'First cancel']
        );

        // Second cancellation attempt
        $res2 = $this->actingAs($this->accountsUser)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.cancel', $note->fresh()),
            ['reason' => 'Second cancel']
        );

        $res2->assertStatus(422);
    }

    /**
     * 17. Closed accounting periods protected against cancellation.
     */
    public function test_17_closed_accounting_periods_protected(): void
    {
        $note = $this->createAndFinalizeStandardNote(500.00);

        // Create accounting period closure covering today
        ShopAccountingPeriodClosure::query()->create([
            'shop_id' => $this->shopA->id,
            'period_start' => today()->subDays(5)->toDateString(),
            'period_end' => today()->addDays(5)->toDateString(),
            'closed_by' => $this->admin->id,
            'closed_at' => now(),
            'notes' => 'Month-end closed',
        ]);

        $response = $this->actingAs($this->accountsUser)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.cancel', $note),
            ['reason' => 'Attempt to cancel in closed period']
        );

        $response->assertStatus(422);
        $this->assertEquals(ShopCreditNoteStatus::Approved, $note->fresh()->status);
    }

    /**
     * 18. Original invoices and payments remain unchanged.
     */
    public function test_18_original_invoices_and_payments_unchanged(): void
    {
        $note = $this->createAndFinalizeStandardNote(500.00);
        $this->applicationService->applyCredit($note, $this->invoiceA1, 200.00, $this->admin);

        $invBefore = $this->invoiceA1->fresh();
        $origSubtotal = (float) $invBefore->subtotal;
        $origFinalTotal = (float) $invBefore->final_total;

        $this->actingAs($this->accountsUser)->post(
            route('admin.cashbook.reports.shops.credit-notes.cancel', $note),
            ['reason' => 'Audit check']
        );

        $invAfter = $this->invoiceA1->fresh();
        $this->assertEquals($origSubtotal, (float) $invAfter->subtotal);
        $this->assertEquals($origFinalTotal, (float) $invAfter->final_total);
    }

    /**
     * 19. Legacy credit note UI remains functional and protected against shop-level cancellation.
     */
    public function test_19_legacy_credit_note_ui_remains_functional(): void
    {
        $legacyNote = ShopCreditNote::factory()->create([
            'shop_id' => $this->shopA->id,
            'shop_invoice_id' => $this->invoiceA1->id,
            'accounting_mode' => ShopCreditNoteAccountingMode::LegacyInvoiceLinked,
            'status' => ShopCreditNoteStatus::Approved,
            'total_credit_amount' => 100.00,
        ]);

        $response = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.cancel', $legacyNote),
            ['reason' => 'Attempt to cancel legacy note']
        );

        $response->assertStatus(422);

        // List route still loads fine
        $listResponse = $this->actingAs($this->admin)->get(
            route('admin.cashbook.reports.shops.credit-notes')
        );
        $listResponse->assertOk();
    }

    /**
     * 20. Existing finalization and allocation UI unaffected by Phase 3.3 additions.
     */
    public function test_20_existing_finalization_allocation_ui_unaffected(): void
    {
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'notes' => 'Phase 3.3 regression test',
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $this->itemOnionA1->id,
                    'credit_quantity' => 5.0,
                    'credit_rate' => 50.0,
                ],
            ],
        ], $this->admin);

        // Finalize
        $finalRes = $this->actingAs($this->admin)->post(
            route('admin.cashbook.reports.shops.credit-notes.drafts.finalize', $draft)
        );
        $finalRes->assertRedirect();
        $this->assertEquals(ShopCreditNoteStatus::Approved, $draft->fresh()->status);

        // Allocate
        $allocRes = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports.shops.credit-notes.apply', $draft->fresh()),
            [
                'allocations' => [
                    [
                        'invoice_id' => $this->invoiceA1->id,
                        'amount' => 150.00,
                    ],
                ],
            ]
        );
        $allocRes->assertOk();
        $this->assertEquals(150.00, $draft->fresh()->appliedCreditTotal());
    }
}
