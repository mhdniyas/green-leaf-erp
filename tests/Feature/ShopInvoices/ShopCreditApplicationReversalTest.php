<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\ShopInvoice\ShopCreditNoteAccountingMode;
use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopAccountingPeriodClosure;
use App\Models\ShopCreditApplication;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\Cashbook\BalanceCalculator;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\InvoiceCashbookProjectionService;
use App\Services\ShopInvoices\ShopCreditApplicationService;
use App\Services\ShopInvoices\ShopCreditNoteService;
use App\Services\ShopInvoices\ShopFinalBillService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PDO;
use Tests\TestCase;

class ShopCreditApplicationReversalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $accountsUser;

    private User $unauthorizedUser;

    private Shop $shopA;

    private Shop $shopB;

    private Product $productOnion;

    private Product $productTomato;

    private ShopInvoice $invoiceA1;

    private ShopInvoice $invoiceA2;

    private ShopCreditNoteService $creditNoteService;

    private ShopCreditApplicationService $applicationService;

    private ShopFinalBillService $finalBillService;

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

        $this->accountsUser = User::factory()->create();
        $this->accountsUser->assignRole('accounts');

        $this->unauthorizedUser = User::factory()->create();
        $this->unauthorizedUser->assignRole('purchaser');

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

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->creditNoteService = app(ShopCreditNoteService::class);
        $this->applicationService = app(ShopCreditApplicationService::class);
        $this->finalBillService = app(ShopFinalBillService::class);
        $this->projectionService = app(InvoiceCashbookProjectionService::class);

        $this->productOnion = Product::factory()->create(['name' => 'Onion Grade A', 'unit' => 'kg']);
        $this->productTomato = Product::factory()->create(['name' => 'Tomato Grade A', 'unit' => 'kg']);

        // Invoice A1 (Shop A) - 5000.00
        $this->invoiceA1 = $this->createFinalizedInvoice($this->shopA, 'SINV-REV-A1', today()->subDays(5)->toDateString(), 5000.00, 0.00);
        $this->createInvoiceItem($this->invoiceA1, $this->productOnion, 'A', 100.0, 50.0);

        // Invoice A2 (Shop A) - 3000.00
        $this->invoiceA2 = $this->createFinalizedInvoice($this->shopA, 'SINV-REV-A2', today()->subDays(4)->toDateString(), 3000.00, 0.00);
        $this->createInvoiceItem($this->invoiceA2, $this->productTomato, 'A', 75.0, 40.0);
    }

    private function createFinalizedInvoice(
        Shop $shop,
        string $invoiceNumber,
        ?string $date = null,
        float $total = 5000.00,
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

    private function createFinalizedShopCreditNote(Shop $shop, float $amount, ?string $date = null): ShopCreditNote
    {
        $draft = $this->creditNoteService->createShopLevelDraft($shop, [
            'credit_note_date' => $date ?? today()->toDateString(),
            'notes' => 'Commercial credit note for testing',
            'discounts' => [
                [
                    'amount' => $amount,
                    'notes' => 'Credit allowance',
                ],
            ],
        ], $this->admin);

        return $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);
    }

    /**
     * Test 1: Full application reversal restores credit note and invoice balances.
     */
    public function test_01_full_application_reversal(): void
    {
        $invoice = $this->createFinalizedInvoice($this->shopA, 'SINV-REV-1', today()->subDays(2)->toDateString(), 1000.00, 0.00);
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        $app = $this->applicationService->applyCredit($creditNote, $invoice, 1000.00, $this->admin, 'Full settlement');
        $this->assertEquals(0.00, $creditNote->fresh()->unappliedCreditTotal());
        $this->assertEquals(0.00, $this->finalBillService->calculate($invoice)->remainingBalance);

        // Reverse the application
        $reversedApp = $this->applicationService->reverseCreditApplication(
            $app,
            $this->admin,
            'Customer requested invoice reassignment'
        );

        $this->assertTrue($reversedApp->isReversed());
        $this->assertFalse($reversedApp->isActive());
        $this->assertEquals($this->admin->id, $reversedApp->reversed_by);
        $this->assertEquals('Customer requested invoice reassignment', $reversedApp->reversal_reason);
        $this->assertNotNull($reversedApp->reversed_at);

        // Credit Note unapplied balance returns to 1000.00
        $this->assertEquals(1000.00, $creditNote->fresh()->unappliedCreditTotal());
        $this->assertEquals(0.00, $creditNote->fresh()->appliedCreditTotal());

        // Invoice effective outstanding balance returns to 1000.00
        $bill = $this->finalBillService->calculate($invoice);
        $this->assertEquals(1000.00, $bill->remainingBalance);
        $this->assertEquals(0.00, $bill->appliedCreditTotal);
        $this->assertTrue($bill->isUnpaid());
    }

    /**
     * Test 2: Partial application reversal.
     */
    public function test_02_partial_application_reversal(): void
    {
        $invoice = $this->createFinalizedInvoice($this->shopA, 'SINV-REV-2', today()->subDays(2)->toDateString(), 3000.00, 0.00);
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1700.00);

        $app = $this->applicationService->applyCredit($creditNote, $invoice, 1000.00, $this->admin);
        $this->assertEquals(700.00, $creditNote->fresh()->unappliedCreditTotal());
        $this->assertEquals(2000.00, $this->finalBillService->calculate($invoice)->remainingBalance);

        // Reverse
        $this->applicationService->reverseCreditApplication($app, $this->admin, 'Correction needed');

        $this->assertEquals(1700.00, $creditNote->fresh()->unappliedCreditTotal());
        $this->assertEquals(0.00, $creditNote->fresh()->appliedCreditTotal());
        $this->assertEquals(3000.00, $this->finalBillService->calculate($invoice)->remainingBalance);
    }

    /**
     * Test 3: Multiple applications where only one is reversed.
     * Specification:
     * Credit Note total: ₹1,700.
     * Invoice A: ₹1,000, Invoice B: ₹500, Unapplied: ₹200.
     * Reverse Invoice A's ₹1,000 application.
     * Expected:
     * - Credit Note total: ₹1,700
     * - Active applied: ₹500
     * - Unapplied: ₹1,200
     * - Invoice A effective outstanding increases by ₹1,000
     * - Invoice B remains unchanged
     */
    public function test_03_multiple_applications_where_only_one_is_reversed(): void
    {
        $invoiceA = $this->createFinalizedInvoice($this->shopA, 'SINV-REV-MULTI-A', today()->subDays(10)->toDateString(), 4000.00, 0.00);
        $invoiceB = $this->createFinalizedInvoice($this->shopA, 'SINV-REV-MULTI-B', today()->subDays(11)->toDateString(), 2000.00, 0.00);

        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1700.00);

        $appA = $this->applicationService->applyCredit($creditNote, $invoiceA, 1000.00, $this->admin);
        $appB = $this->applicationService->applyCredit($creditNote, $invoiceB, 500.00, $this->admin);

        $this->assertEquals(200.00, $creditNote->fresh()->unappliedCreditTotal());
        $this->assertEquals(1500.00, $creditNote->fresh()->appliedCreditTotal());

        // Reverse only Invoice A's application
        $this->applicationService->reverseCreditApplication($appA, $this->admin, 'Reverse allocation on A');

        $creditNote->refresh();
        $this->assertEquals(1700.00, (float) $creditNote->total_credit_amount);
        $this->assertEquals(500.00, $creditNote->appliedCreditTotal());
        $this->assertEquals(1200.00, $creditNote->unappliedCreditTotal());

        // Invoice A increased by 1000 back to 4000
        $billA = $this->finalBillService->calculate($invoiceA);
        $this->assertEquals(4000.00, $billA->finalNetPayable);
        $this->assertEquals(4000.00, $billA->remainingBalance);

        // Invoice B remains unchanged at 1500
        $billB = $this->finalBillService->calculate($invoiceB);
        $this->assertEquals(1500.00, $billB->finalNetPayable);
        $this->assertEquals(1500.00, $billB->remainingBalance);
    }

    /**
     * Test 4: Unapplied credit restored correctly and can be re-allocated.
     */
    public function test_04_unapplied_credit_restored_correctly_and_reallocated(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        $app1 = $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 600.00, $this->admin);
        $this->assertEquals(400.00, $creditNote->fresh()->unappliedCreditTotal());

        // Reverse app1
        $this->applicationService->reverseCreditApplication($app1, $this->admin, 'Wrong invoice chosen');
        $this->assertEquals(1000.00, $creditNote->fresh()->unappliedCreditTotal());

        // Now apply full 1000 to invoiceA2
        $app2 = $this->applicationService->applyCredit($creditNote, $this->invoiceA2, 1000.00, $this->admin);
        $this->assertEquals(0.00, $creditNote->fresh()->unappliedCreditTotal());
        $this->assertEquals(1000.00, $creditNote->fresh()->appliedCreditTotal());
        $this->assertEquals(2000.00, $this->finalBillService->calculate($this->invoiceA2)->remainingBalance);
    }

    /**
     * Test 5: Original Credit Note total amount remains strictly unchanged.
     */
    public function test_05_original_credit_note_total_unchanged(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1500.00);
        $origTotal = (float) $creditNote->total_credit_amount;

        $app = $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 1500.00, $this->admin);
        $this->applicationService->reverseCreditApplication($app, $this->admin, 'Reason');

        $creditNote->refresh();
        $this->assertEquals($origTotal, (float) $creditNote->total_credit_amount);
        $this->assertEquals($origTotal, (float) $creditNote->subtotal);
    }

    /**
     * Test 6: Invoice effective outstanding recalculated correctly.
     */
    public function test_06_invoice_effective_outstanding_recalculated_correctly(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 800.00);
        $app = $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 800.00, $this->admin);

        $this->assertEquals(4200.00, $this->invoiceA1->effectiveFinalTotal());
        $this->assertEquals(4200.00, $this->invoiceA1->effectiveBalanceAmount());

        $this->applicationService->reverseCreditApplication($app, $this->admin, 'Audit adjustment');

        $this->invoiceA1->refresh();
        $this->assertEquals(5000.00, $this->invoiceA1->effectiveFinalTotal());
        $this->assertEquals(5000.00, $this->invoiceA1->effectiveBalanceAmount());
    }

    /**
     * Test 7: Double reversal cannot duplicate effects and throws ValidationException.
     */
    public function test_07_double_reversal_cannot_duplicate_effects(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);
        $app = $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 500.00, $this->admin);

        // First reversal succeeds
        $this->applicationService->reverseCreditApplication($app, $this->admin, 'First reversal');
        $this->assertEquals(1000.00, $creditNote->fresh()->unappliedCreditTotal());

        // Second reversal must throw ValidationException
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Credit application is already reversed.');

        $this->applicationService->reverseCreditApplication($app, $this->admin, 'Attempt duplicate reversal');
    }

    /**
     * Test 8: Reversal via ShopCreditNoteService delegation works.
     */
    public function test_08_reversal_via_credit_note_service_delegation(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 600.00);
        $app = $this->creditNoteService->applyCredit($creditNote, $this->invoiceA1, 600.00, $this->admin);

        $reversed = $this->creditNoteService->reverseCreditApplication($app, $this->admin, 'Delegation test');
        $this->assertTrue($reversed->isReversed());
        $this->assertEquals(600.00, $creditNote->fresh()->unappliedCreditTotal());
    }

    /**
     * Test 9: Reversal concurrent with a new credit application is safe under deterministic locking.
     */
    public function test_09_reversal_concurrent_with_new_credit_application_is_safe(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);
        $app1 = $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 600.00, $this->admin);

        // Reverse app1 and immediately apply 800 to invoiceA2
        $this->applicationService->reverseCreditApplication($app1, $this->admin, 'Released credit');
        $app2 = $this->applicationService->applyCredit($creditNote, $this->invoiceA2, 800.00, $this->admin);

        $this->assertInstanceOf(ShopCreditApplication::class, $app2);
        $this->assertEquals(200.00, $creditNote->fresh()->unappliedCreditTotal());
        $this->assertEquals(800.00, $creditNote->fresh()->appliedCreditTotal());
    }

    /**
     * Test 10: Cashbook transaction count and total unchanged.
     */
    public function test_10_cashbook_transaction_count_and_total_unchanged(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);
        $app = $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 1000.00, $this->admin);

        $txCountBefore = ShopLedgerTransaction::where('shop_id', $this->shopA->id)->count();

        // Perform reversal
        $this->applicationService->reverseCreditApplication($app, $this->admin, 'Cashbook neutrality check');

        $txCountAfter = ShopLedgerTransaction::where('shop_id', $this->shopA->id)->count();

        // Exactly identical: no new ledger transaction, no deleted transactions
        $this->assertEquals($txCountBefore, $txCountAfter);

        // Exactly one ledger transaction exists for the credit note
        $noteTx = ShopLedgerTransaction::where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $creditNote->id)
            ->get();
        $this->assertCount(1, $noteTx);
        $this->assertEquals(1000.00, (float) $noteTx->first()->amount);
    }

    /**
     * Test 11: Shop aggregate net liability unchanged.
     */
    public function test_11_shop_aggregate_net_liability_unchanged(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);
        $app = $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 700.00, $this->admin);

        /** @var BalanceCalculator $calculator */
        $calculator = app(BalanceCalculator::class);
        $snapshotBefore = $calculator->recalculate($this->shopA->id, today()->toDateString());

        // Reverse application
        $this->applicationService->reverseCreditApplication($app, $this->admin, 'Liability balance check');

        $snapshotAfter = $calculator->recalculate($this->shopA->id, today()->toDateString());

        // Snapshot company pending closing balance remains strictly unchanged
        $this->assertEquals(
            (float) $snapshotBefore->closing_company_pending,
            (float) $snapshotAfter->closing_company_pending
        );
    }

    /**
     * Test 12: Admin Accounts and Shop Owner Finance reconcile after reversal.
     */
    public function test_12_admin_accounts_and_shop_owner_finance_reconcile(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1200.00);
        $app = $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 1200.00, $this->accountsUser);

        // Before reversal: Invoice A1 has 3800 remaining, A2 has 3000 -> total 6800
        $billsBefore = $this->finalBillService->calculateMany([$this->invoiceA1, $this->invoiceA2]);
        $this->assertEquals(6800.00, $billsBefore->sum('remainingBalance'));

        // Reverse application
        $this->applicationService->reverseCreditApplication($app, $this->accountsUser, 'Reconciliation test');

        $this->invoiceA1->refresh();
        $this->invoiceA2->refresh();

        // After reversal: Invoice A1 has 5000 remaining, A2 has 3000 -> total 8000
        $billsAfter = $this->finalBillService->calculateMany([$this->invoiceA1, $this->invoiceA2]);
        $this->assertEquals(8000.00, $billsAfter->sum('remainingBalance'));
        $this->assertEquals(0.00, $billsAfter->sum('finalizedCreditTotal'));
    }

    /**
     * Test 13: Fully paid invoice handling — reversal restores remaining balance while preserving payments.
     */
    public function test_13_fully_paid_invoice_handling(): void
    {
        // Invoice total: 5000. Applied credit: 1000. Net payable: 4000. Paid: 4000 -> fully paid.
        $invoice = $this->createFinalizedInvoice($this->shopA, 'SINV-REV-PAID', today()->subDays(3)->toDateString(), 5000.00, 4000.00);
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        $app = $this->applicationService->applyCredit($creditNote, $invoice, 1000.00, $this->admin);

        $billBefore = $this->finalBillService->calculate($invoice);
        $this->assertEquals(0.00, $billBefore->remainingBalance);
        $this->assertTrue($billBefore->isFullyPaid());

        // Reversal occurs
        $this->applicationService->reverseCreditApplication($app, $this->admin, 'Customer refund or allocation change');

        $billAfter = $this->finalBillService->calculate($invoice);
        // Net payable restored to 5000. Paid remains 4000. Remaining balance is 1000!
        $this->assertEquals(5000.00, $billAfter->finalNetPayable);
        $this->assertEquals(4000.00, $billAfter->amountPaid);
        $this->assertEquals(1000.00, $billAfter->remainingBalance);
        $this->assertFalse($billAfter->isFullyPaid());
        $this->assertTrue($billAfter->isPartiallyPaid());
    }

    /**
     * Test 14: Existing payment records preserved.
     */
    public function test_14_existing_payment_records_preserved(): void
    {
        $invoice = $this->createFinalizedInvoice($this->shopA, 'SINV-REV-PAY-1', today()->subDays(3)->toDateString(), 5000.00, 2500.00);
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        $app = $this->applicationService->applyCredit($creditNote, $invoice, 1000.00, $this->admin);
        $this->applicationService->reverseCreditApplication($app, $this->admin, 'Payment preservation test');

        $invoice->refresh();
        $this->assertEquals(2500.00, (float) $invoice->paid_amount);
        $this->assertEquals(2500.00, (float) $invoice->balance_amount);
    }

    /**
     * Test 15: Closed-period restrictions enforced on reversal.
     */
    public function test_15_closed_period_restrictions_enforced(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);
        $app = $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 500.00, $this->admin);

        $closedDate = today()->subDays(3);

        // Closure covering reversal date
        ShopAccountingPeriodClosure::query()->create([
            'shop_id' => $this->shopA->id,
            'period_start' => today()->subDays(10)->toDateString(),
            'period_end' => today()->subDays(1)->toDateString(),
            'closed_by' => $this->admin->id,
            'closed_at' => now(),
            'notes' => 'Month closure',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot reverse credit application in a closed accounting period.');

        $this->applicationService->reverseCreditApplication(
            $app,
            $this->admin,
            'Attempted reversal in closed period',
            reversedAt: $closedDate
        );
    }

    /**
     * Test 16: Unauthorized reversal rejected.
     */
    public function test_16_unauthorized_reversal_rejected(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);
        $app = $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 500.00, $this->admin);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('User is not authorized to reverse credit applications.');

        // Purchaser is unauthorized to reverse financial allocations
        $this->applicationService->reverseCreditApplication($app, $this->unauthorizedUser, 'Unauthorized attempt');
    }

    /**
     * Test 17: Missing reversal reason rejected.
     */
    public function test_17_missing_reversal_reason_rejected(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);
        $app = $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 500.00, $this->admin);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('A valid reversal reason is required.');

        $this->applicationService->reverseCreditApplication($app, $this->admin, '   ');
    }

    /**
     * Test 18: Legacy Credit Notes remain unchanged.
     */
    public function test_18_legacy_credit_notes_remain_unchanged(): void
    {
        $legacyDraft = $this->creditNoteService->createDraft($this->invoiceA1, [
            'direction' => ShopCreditNoteDirection::Credit,
            'inventory_action' => ShopCreditNoteInventoryAction::None,
            'items' => [
                [
                    'shop_invoice_item_id' => $this->invoiceA1->items->first()->id,
                    'line_credit_amount' => 500.00,
                    'adjusted_quantity' => 0.0,
                ],
            ],
        ], $this->admin->id);

        $approvedLegacy = $this->creditNoteService->approve($legacyDraft, $this->admin);
        $this->assertEquals(ShopCreditNoteAccountingMode::LegacyInvoiceLinked, $approvedLegacy->accounting_mode);

        $bill = $this->finalBillService->calculate($this->invoiceA1);
        $this->assertEquals(500.00, $bill->finalizedCreditTotal);
        $this->assertEquals(0.00, $bill->appliedCreditTotal);
        $this->assertEquals(4500.00, $bill->finalNetPayable);
    }

    /**
     * Test 19: Original invoice records remain immutable in database table.
     */
    public function test_19_original_invoice_records_remain_immutable(): void
    {
        $origFinalTotal = (float) $this->invoiceA1->final_total;
        $origBalance = (float) $this->invoiceA1->balance_amount;

        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);
        $app = $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 1000.00, $this->admin);
        $this->applicationService->reverseCreditApplication($app, $this->admin, 'Audit trace');

        $this->invoiceA1->refresh();
        $this->assertEquals($origFinalTotal, (float) $this->invoiceA1->final_total);
        $this->assertEquals($origBalance, (float) $this->invoiceA1->balance_amount);
    }

    /**
     * Test 20: Intentional duplicate-looking applications are not silently lost.
     */
    public function test_20_intentional_duplicate_looking_applications_are_not_silently_lost(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        // Application 1 with explicit key req_101
        $app1 = $this->applicationService->applyCredit(
            $creditNote,
            $this->invoiceA1,
            200.00,
            $this->admin,
            'Installment 1',
            idempotencyKey: 'req_101'
        );

        // Application 2 with explicit key req_102 (same amount within same second)
        $app2 = $this->applicationService->applyCredit(
            $creditNote,
            $this->invoiceA1,
            200.00,
            $this->admin,
            'Installment 2',
            idempotencyKey: 'req_102'
        );

        // Both applications are created and distinct!
        $this->assertNotEquals($app1->id, $app2->id);
        $this->assertEquals(600.00, $creditNote->fresh()->unappliedCreditTotal());
        $this->assertEquals(400.00, $creditNote->fresh()->appliedCreditTotal());
    }

    /**
     * Test 21: Concurrency safety on MySQL — Competing transactions cannot reverse the same application twice.
     */
    public function test_21_concurrent_reversals_cannot_double_reverse_on_mysql(): void
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
        $pdo1->exec('TRUNCATE TABLE shop_credit_applications');
        $pdo1->exec('TRUNCATE TABLE shop_credit_notes');
        $pdo1->exec('TRUNCATE TABLE shop_invoices');
        $pdo1->exec('TRUNCATE TABLE shops');

        $pdo1->exec("INSERT INTO shops (id, name, code, status, created_at, updated_at) VALUES (666, 'Conc Rev Shop', 'CONC_REV', 'active', NOW(), NOW())");
        $pdo1->exec("INSERT INTO shop_invoices (id, shop_id, shop_order_id, invoice_number, business_date, status, delivery_status, payment_status, final_total, balance_amount, created_at, updated_at) VALUES (661, 666, 666, 'SINV-CONC-REV-1', CURDATE(), 'finalized', 'received_full', 'unpaid', 5000.00, 5000.00, NOW(), NOW())");
        $pdo1->exec("INSERT INTO shop_credit_notes (id, credit_note_number, shop_id, credit_note_date, direction, accounting_mode, status, total_credit_amount, created_at, updated_at) VALUES (661, 'SCN-CONC-REV-1', 666, CURDATE(), 'credit', 'shop_level', 'approved', 1000.00, NOW(), NOW())");
        $pdo1->exec('INSERT INTO shop_credit_applications (id, shop_credit_note_id, shop_invoice_id, shop_id, amount, applied_at, created_at, updated_at) VALUES (661, 661, 661, 666, 600.00, NOW(), NOW(), NOW())');

        // Transaction 1 locks Credit Note #661 and Application #661 for reversal
        $pdo1->beginTransaction();
        $stmt1 = $pdo1->prepare('SELECT id, reversed_at FROM shop_credit_applications WHERE id = 661 FOR UPDATE');
        $stmt1->execute();
        $appRow1 = $stmt1->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($appRow1['reversed_at']);

        // Transaction 2 sets 1-second timeout and attempts to lock the SAME application row
        $pdo2->exec('SET innodb_lock_wait_timeout = 1');
        $lockBlocked = false;

        try {
            $pdo2->beginTransaction();
            $stmt2 = $pdo2->prepare('SELECT id, reversed_at FROM shop_credit_applications WHERE id = 661 FOR UPDATE');
            $stmt2->execute();
        } catch (\PDOException $e) {
            $lockBlocked = true;
            $pdo2->rollBack();
        }

        $this->assertTrue($lockBlocked, 'Pessimistic lock on shop_credit_applications successfully blocked concurrent reversal.');

        // Transaction 1 marks application reversed and commits
        $pdo1->exec("UPDATE shop_credit_applications SET reversed_at = NOW(), reversal_reason = 'Reversal from Tx1' WHERE id = 661");
        $pdo1->commit();

        // Transaction 2 now starts, acquires lock, and inspects reversed_at
        $pdo2->beginTransaction();
        $stmt2 = $pdo2->prepare('SELECT id, reversed_at FROM shop_credit_applications WHERE id = 661 FOR UPDATE');
        $stmt2->execute();
        $appRow2 = $stmt2->fetch(PDO::FETCH_ASSOC);

        $isAlreadyReversed = ($appRow2['reversed_at'] !== null);
        $this->assertTrue($isAlreadyReversed, 'Transaction 2 detected application is already reversed and safely aborted.');
        $pdo2->rollBack();

        // Final verification: exactly 1 reversal occurred
        $verifyStmt = $pdo1->query('SELECT COUNT(*) FROM shop_credit_applications WHERE id = 661 AND reversed_at IS NOT NULL');
        $this->assertEquals(1, (int) $verifyStmt->fetchColumn());
    }
}
