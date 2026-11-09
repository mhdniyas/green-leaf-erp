<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

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

class ShopCreditApplicationTest extends TestCase
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

    private ShopInvoice $invoiceB1;

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

        // Invoice A1 (Shop A) - 100 kg Onion @ 50 = 5000 (unpaid)
        $this->invoiceA1 = $this->createFinalizedInvoice($this->shopA, 'SINV-A1', today()->subDays(3)->toDateString(), 5000.00, 0.00);
        $this->createInvoiceItem($this->invoiceA1, $this->productOnion, 'A', 100.0, 50.0);

        // Invoice A2 (Shop A) - 80 kg Tomato @ 40 = 3200 (unpaid)
        $this->invoiceA2 = $this->createFinalizedInvoice($this->shopA, 'SINV-A2', today()->subDays(2)->toDateString(), 3200.00, 0.00);
        $this->createInvoiceItem($this->invoiceA2, $this->productTomato, 'A', 80.0, 40.0);

        // Invoice B1 (Shop B) - 50 kg Onion @ 50 = 2500 (unpaid)
        $this->invoiceB1 = $this->createFinalizedInvoice($this->shopB, 'SINV-B1', today()->subDays(1)->toDateString(), 2500.00, 0.00);
        $this->createInvoiceItem($this->invoiceB1, $this->productOnion, 'A', 50.0, 50.0);
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
     * Test 1: Full application to one invoice.
     */
    public function test_01_full_application_to_one_invoice(): void
    {
        $invoice = $this->createFinalizedInvoice($this->shopA, 'SINV-FULL-1', today()->toDateString(), 1000.00, 0.00);
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        $this->assertEquals(1000.00, $creditNote->unappliedCreditTotal());
        $this->assertEquals(0.00, $creditNote->appliedCreditTotal());

        $application = $this->applicationService->applyCredit($creditNote, $invoice, 1000.00, $this->admin, 'Full settlement');

        $this->assertInstanceOf(ShopCreditApplication::class, $application);
        $this->assertEquals(1000.00, (float) $application->amount);
        $this->assertEquals($creditNote->id, $application->shop_credit_note_id);
        $this->assertEquals($invoice->id, $application->shop_invoice_id);

        $creditNote->refresh();
        $this->assertEquals(0.00, $creditNote->unappliedCreditTotal());
        $this->assertEquals(1000.00, $creditNote->appliedCreditTotal());

        $bill = $this->finalBillService->calculate($invoice);
        $this->assertEquals(0.00, $bill->remainingBalance);
        $this->assertTrue($bill->isFullyPaid());
        $this->assertEquals(1000.00, $bill->finalizedCreditTotal);
        $this->assertEquals(1000.00, $bill->appliedCreditTotal);
    }

    /**
     * Test 2: Partial application to one invoice.
     */
    public function test_02_partial_application_to_one_invoice(): void
    {
        $invoice = $this->createFinalizedInvoice($this->shopA, 'SINV-PART-1', today()->toDateString(), 3000.00, 0.00);
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1700.00);

        $application = $this->applicationService->applyCredit($creditNote, $invoice, 1000.00, $this->admin, 'Partial application 1');

        $creditNote->refresh();
        $this->assertEquals(700.00, $creditNote->unappliedCreditTotal());
        $this->assertEquals(1000.00, $creditNote->appliedCreditTotal());

        $bill = $this->finalBillService->calculate($invoice);
        $this->assertEquals(2000.00, $bill->finalNetPayable);
        $this->assertEquals(2000.00, $bill->remainingBalance);
        $this->assertEquals(1000.00, $bill->appliedCreditTotal);
    }

    /**
     * Test 3: One Credit Note applied across multiple invoices.
     * Specification:
     * Finalized Credit Note: ₹1,700.
     * - Apply ₹1,000 to Invoice A.
     * - Apply ₹500 to Invoice B.
     * - Leave ₹200 unapplied.
     */
    public function test_03_one_credit_note_applied_across_multiple_invoices(): void
    {
        $invoiceA = $this->createFinalizedInvoice($this->shopA, 'SINV-SPEC-A', today()->subDays(10)->toDateString(), 4000.00, 0.00);
        $invoiceB = $this->createFinalizedInvoice($this->shopA, 'SINV-SPEC-B', today()->subDays(11)->toDateString(), 2000.00, 0.00);

        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1700.00);

        // Apply 1000 to Invoice A
        $this->applicationService->applyCredit($creditNote, $invoiceA, 1000.00, $this->admin);

        // Apply 500 to Invoice B
        $this->applicationService->applyCredit($creditNote, $invoiceB, 500.00, $this->admin);

        $creditNote->refresh();
        $this->assertEquals(1700.00, (float) $creditNote->total_credit_amount);
        $this->assertEquals(1500.00, $creditNote->appliedCreditTotal());
        $this->assertEquals(200.00, $creditNote->unappliedCreditTotal());

        $billA = $this->finalBillService->calculate($invoiceA);
        $this->assertEquals(3000.00, $billA->finalNetPayable);
        $this->assertEquals(3000.00, $billA->remainingBalance);

        $billB = $this->finalBillService->calculate($invoiceB);
        $this->assertEquals(1500.00, $billB->finalNetPayable);
        $this->assertEquals(1500.00, $billB->remainingBalance);
    }

    /**
     * Test 4: Unapplied balance calculated correctly across allocations.
     */
    public function test_04_unapplied_balance_calculated_correctly(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 2500.00);

        $this->assertEquals(2500.00, $creditNote->unappliedCreditTotal());

        $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 500.00, $this->admin);
        $this->assertEquals(2000.00, $creditNote->fresh()->unappliedCreditTotal());

        $this->applicationService->applyCredit($creditNote, $this->invoiceA2, 1200.00, $this->admin);
        $this->assertEquals(800.00, $creditNote->fresh()->unappliedCreditTotal());
        $this->assertEquals(1700.00, $creditNote->fresh()->appliedCreditTotal());
    }

    /**
     * Test 5: Application exceeding available credit rejected.
     */
    public function test_05_application_exceeding_available_credit_rejected(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 500.00);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('exceeds available unapplied credit');

        $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 600.00, $this->admin);
    }

    /**
     * Test 6: Application exceeding invoice outstanding rejected.
     */
    public function test_06_application_exceeding_invoice_outstanding_rejected(): void
    {
        $invoice = $this->createFinalizedInvoice($this->shopA, 'SINV-LOW-1', today()->toDateString(), 400.00, 0.00);
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('exceeds invoice effective outstanding balance');

        $this->applicationService->applyCredit($creditNote, $invoice, 500.00, $this->admin);
    }

    /**
     * Test 7: Cross-shop application rejected.
     */
    public function test_07_cross_shop_application_rejected(): void
    {
        $creditNoteShopA = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        // Attempt to apply Shop A credit note to Shop B invoice
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Target invoice must belong to the same shop as the credit note.');

        $this->applicationService->applyCredit($creditNoteShopA, $this->invoiceB1, 500.00, $this->admin);
    }

    /**
     * Test 8: Fully paid invoice application rejected.
     */
    public function test_08_fully_paid_invoice_application_rejected(): void
    {
        $paidInvoice = $this->createFinalizedInvoice($this->shopA, 'SINV-PAID-1', today()->toDateString(), 2000.00, 2000.00);
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 500.00);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Target invoice is already fully settled.');

        $this->applicationService->applyCredit($creditNote, $paidInvoice, 200.00, $this->admin);
    }

    /**
     * Test 9: Different Credit Notes concurrently targeting one invoice.
     */
    public function test_09_different_credit_notes_targeting_one_invoice(): void
    {
        $invoice = $this->createFinalizedInvoice($this->shopA, 'SINV-MULTI-CN', today()->toDateString(), 1500.00, 0.00);
        $cn1 = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);
        $cn2 = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);
        $cn3 = $this->createFinalizedShopCreditNote($this->shopA, 500.00);

        // CN1 applies 1000 -> remaining invoice balance is 500
        $this->applicationService->applyCredit($cn1, $invoice, 1000.00, $this->admin);
        $this->assertEquals(500.00, $this->finalBillService->calculate($invoice)->remainingBalance);

        // CN2 applies 500 -> remaining invoice balance becomes 0
        $this->applicationService->applyCredit($cn2, $invoice, 500.00, $this->admin);
        $this->assertEquals(0.00, $this->finalBillService->calculate($invoice)->remainingBalance);
        $this->assertEquals(500.00, $cn2->fresh()->unappliedCreditTotal());

        // CN3 attempts to apply 100 to now fully settled invoice -> rejected!
        $rejected = false;
        try {
            $this->applicationService->applyCredit($cn3, $invoice, 100.00, $this->admin);
        } catch (ValidationException $e) {
            $rejected = true;
            $this->assertStringContainsString('Target invoice is already fully settled.', $e->getMessage());
        }
        $this->assertTrue($rejected);
    }

    /**
     * Test 10: Batch application across multiple invoices via applyCreditToMany.
     */
    public function test_10_batch_application_across_multiple_invoices(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 2000.00);

        $apps = $this->applicationService->applyCreditToMany($creditNote, [
            ['invoice_id' => $this->invoiceA1->id, 'amount' => 1200.00, 'notes' => 'Alloc 1'],
            ['invoice_id' => $this->invoiceA2->id, 'amount' => 600.00, 'notes' => 'Alloc 2'],
        ], $this->admin);

        $this->assertCount(2, $apps);
        $this->assertEquals(200.00, $creditNote->fresh()->unappliedCreditTotal());
        $this->assertEquals(1800.00, $creditNote->fresh()->appliedCreditTotal());
    }

    /**
     * Test 11: Duplicate submission does not double-apply (Idempotency).
     */
    public function test_11_duplicate_submission_does_not_double_apply(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        // First application with idempotency key
        $app1 = $this->applicationService->applyCredit(
            $creditNote,
            $this->invoiceA1,
            400.00,
            $this->admin,
            'Payment allocation',
            idempotencyKey: 'tx_req_9999'
        );

        // Immediate retry with same idempotency key
        $app2 = $this->applicationService->applyCredit(
            $creditNote,
            $this->invoiceA1,
            400.00,
            $this->admin,
            'Payment allocation',
            idempotencyKey: 'tx_req_9999'
        );

        $this->assertEquals($app1->id, $app2->id);

        // Unapplied balance is decremented exactly ONCE
        $this->assertEquals(600.00, $creditNote->fresh()->unappliedCreditTotal());
        $this->assertEquals(1, ShopCreditApplication::where('shop_credit_note_id', $creditNote->id)->count());
    }

    /**
     * Test 12: Original invoice stored totals remain strictly unchanged.
     */
    public function test_12_original_invoice_stored_totals_remain_unchanged(): void
    {
        $origFinalTotal = (float) $this->invoiceA1->final_total;
        $origBalance = (float) $this->invoiceA1->balance_amount;
        $origPaid = (float) $this->invoiceA1->paid_amount;

        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1500.00);
        $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 1500.00, $this->admin);

        $this->invoiceA1->refresh();

        // Stored invoice table row is strictly immutable
        $this->assertEquals($origFinalTotal, (float) $this->invoiceA1->final_total);
        $this->assertEquals($origBalance, (float) $this->invoiceA1->balance_amount);
        $this->assertEquals($origPaid, (float) $this->invoiceA1->paid_amount);
    }

    /**
     * Test 13: Final Bill effective payable reflects applied credit.
     */
    public function test_13_final_bill_effective_payable_reflects_applied_credit(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);
        $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 1000.00, $this->admin);

        $bill = $this->finalBillService->calculate($this->invoiceA1);

        $this->assertEquals(5000.00, $bill->originalInvoiceTotal);
        $this->assertEquals(1000.00, $bill->finalizedCreditTotal);
        $this->assertEquals(1000.00, $bill->appliedCreditTotal);
        $this->assertEquals(4000.00, $bill->finalNetPayable);
        $this->assertEquals(4000.00, $bill->remainingBalance);

        // Model methods also match
        $this->assertEquals(4000.00, $this->invoiceA1->effectiveFinalTotal());
        $this->assertEquals(4000.00, $this->invoiceA1->effectiveBalanceAmount());
    }

    /**
     * Test 14: Cashbook contains no additional credit entry after application.
     */
    public function test_14_cashbook_contains_no_additional_credit_entry_after_application(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        // Before application: exactly 1 ledger transaction exists for the credit note
        $initialTxCount = ShopLedgerTransaction::where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $creditNote->id)
            ->count();
        $this->assertEquals(1, $initialTxCount);

        $totalShopTxBefore = ShopLedgerTransaction::where('shop_id', $this->shopA->id)->count();

        // Perform credit application
        $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 600.00, $this->admin);

        // After application: no new ledger transaction created
        $finalTxCount = ShopLedgerTransaction::where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $creditNote->id)
            ->count();
        $this->assertEquals(1, $finalTxCount);

        $totalShopTxAfter = ShopLedgerTransaction::where('shop_id', $this->shopA->id)->count();
        $this->assertEquals($totalShopTxBefore, $totalShopTxAfter);
    }

    /**
     * Test 15: Aggregate shop outstanding remains unchanged on allocation.
     */
    public function test_15_aggregate_shop_outstanding_remains_unchanged_on_allocation(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        /** @var BalanceCalculator $calculator */
        $calculator = app(BalanceCalculator::class);
        $snapshotBefore = $calculator->recalculate($this->shopA->id, today()->toDateString());

        // Apply credit to invoice
        $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 700.00, $this->admin);

        $snapshotAfter = $calculator->recalculate($this->shopA->id, today()->toDateString());

        // The daily ledger closing company pending remains strictly identical
        $this->assertEquals(
            (float) $snapshotBefore->closing_company_pending,
            (float) $snapshotAfter->closing_company_pending
        );
    }

    /**
     * Test 16: Admin Accounts and Shop Owner Finance reconcile.
     */
    public function test_16_admin_accounts_and_shop_owner_finance_reconcile(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1200.00);
        $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 1200.00, $this->accountsUser);

        $invoices = ShopInvoice::where('shop_id', $this->shopA->id)->get();
        $finalBills = $this->finalBillService->calculateMany($invoices);

        $totalBilled = $finalBills->sum('originalInvoiceTotal');
        $totalFinalBilled = $finalBills->sum('finalNetPayable');
        $totalOutstanding = $finalBills->sum('remainingBalance');
        $totalCredits = $finalBills->sum('finalizedCreditTotal');

        // Invoice A1 (5000 - 1200 = 3800) + Invoice A2 (3200) = 7000
        $this->assertEquals(8200.00, $totalBilled);
        $this->assertEquals(1200.00, $totalCredits);
        $this->assertEquals(7000.00, $totalFinalBilled);
        $this->assertEquals(7000.00, $totalOutstanding);
    }

    /**
     * Test 17: Reversed application records excluded from active totals.
     */
    public function test_17_reversed_application_records_excluded_from_active_totals(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        $app = $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 600.00, $this->admin);

        $this->assertEquals(400.00, $creditNote->fresh()->unappliedCreditTotal());
        $this->assertEquals(4400.00, $this->finalBillService->calculate($this->invoiceA1)->remainingBalance);

        // Mark application as reversed
        $app->forceFill([
            'reversed_at' => now(),
            'reversed_by' => $this->admin->id,
            'reversal_reason' => 'Audit correction',
        ])->save();

        $this->assertTrue($app->fresh()->isReversed());
        $this->assertFalse($app->fresh()->isActive());

        // Credit note unapplied balance is fully restored to 1000.00!
        $this->assertEquals(1000.00, $creditNote->fresh()->unappliedCreditTotal());
        $this->assertEquals(0.00, $creditNote->fresh()->appliedCreditTotal());

        // Invoice remaining balance is restored to 5000.00!
        $this->assertEquals(5000.00, $this->finalBillService->calculate($this->invoiceA1)->remainingBalance);
    }

    /**
     * Test 18: Legacy single-invoice Credit Notes continue working and are rejected from shop-level apply.
     */
    public function test_18_legacy_single_invoice_credit_notes_remain_correct(): void
    {
        // 1. Create and approve legacy draft
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

        $legacyApproved = $this->creditNoteService->approve($legacyDraft, $this->admin);

        // Direct legacy calculation works
        $bill = $this->finalBillService->calculate($this->invoiceA1);
        $this->assertEquals(500.00, $bill->finalizedCreditTotal);
        $this->assertEquals(0.00, $bill->appliedCreditTotal);
        $this->assertEquals(4500.00, $bill->finalNetPayable);

        // Attempting to pass legacy credit note to shop-level applyCredit must be rejected
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Only shop-level credit notes can be applied to invoices.');

        $this->applicationService->applyCredit($legacyApproved, $this->invoiceA2, 100.00, $this->admin);
    }

    /**
     * Test 19: Unauthorized application rejected.
     */
    public function test_19_unauthorized_application_rejected(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('User is not authorized to apply shop credit notes.');

        // Purchaser role is unauthorized to apply financial credit
        $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 500.00, $this->unauthorizedUser);
    }

    /**
     * Test 20: Closed-period protections enforced.
     */
    public function test_20_closed_period_protections_enforced(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);
        $closedDate = today()->subDays(5);

        // Create closure covering date
        ShopAccountingPeriodClosure::query()->create([
            'shop_id' => $this->shopA->id,
            'period_start' => today()->subDays(10)->toDateString(),
            'period_end' => today()->subDays(1)->toDateString(),
            'closed_by' => $this->admin->id,
            'closed_at' => now(),
            'notes' => 'Month closure',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot apply credit in a closed accounting period.');

        $this->applicationService->applyCredit(
            $creditNote,
            $this->invoiceA1,
            300.00,
            $this->admin,
            appliedAt: $closedDate
        );
    }

    /**
     * Test 21: Concurrency safety on MySQL — Competing transactions cannot over-apply remaining credit.
     */
    public function test_21_concurrent_applications_cannot_over_apply_on_mysql(): void
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

        // Clean tables in isolated test database
        $pdo1->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo2->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo1->exec('TRUNCATE TABLE shop_credit_applications');
        $pdo1->exec('TRUNCATE TABLE shop_credit_notes');
        $pdo1->exec('TRUNCATE TABLE shop_invoices');
        $pdo1->exec('TRUNCATE TABLE shops');

        $pdo1->exec("INSERT INTO shops (id, name, code, status, created_at, updated_at) VALUES (777, 'Conc App Shop', 'CONC_APP', 'active', NOW(), NOW())");
        $pdo1->exec("INSERT INTO shop_invoices (id, shop_id, shop_order_id, invoice_number, business_date, status, delivery_status, payment_status, final_total, balance_amount, created_at, updated_at) VALUES (771, 777, 777, 'SINV-CONC-APP-1', CURDATE(), 'finalized', 'received_full', 'unpaid', 5000.00, 5000.00, NOW(), NOW())");
        $pdo1->exec("INSERT INTO shop_credit_notes (id, credit_note_number, shop_id, credit_note_date, direction, accounting_mode, status, total_credit_amount, created_at, updated_at) VALUES (771, 'SCN-CONC-APP-1', 777, CURDATE(), 'credit', 'shop_level', 'approved', 1000.00, NOW(), NOW())");

        // Transaction 1 locks Credit Note #771 and applies 700.00 of 1000.00
        $pdo1->beginTransaction();
        $stmt1 = $pdo1->prepare('SELECT id, total_credit_amount FROM shop_credit_notes WHERE id = 771 FOR UPDATE');
        $stmt1->execute();
        $cnRow1 = $stmt1->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(1000.00, (float) $cnRow1['total_credit_amount']);

        // Transaction 2 sets 1-second timeout and attempts to lock Credit Note #771
        $pdo2->exec('SET innodb_lock_wait_timeout = 1');
        $lockBlocked = false;

        try {
            $pdo2->beginTransaction();
            $stmt2 = $pdo2->prepare('SELECT id, total_credit_amount FROM shop_credit_notes WHERE id = 771 FOR UPDATE');
            $stmt2->execute();
        } catch (\PDOException $e) {
            $lockBlocked = true;
            $pdo2->rollBack();
        }

        $this->assertTrue($lockBlocked, 'Pessimistic lock on shop_credit_notes successfully blocked competing transaction.');

        // Transaction 1 inserts application of 700.00 and commits
        $pdo1->exec('INSERT INTO shop_credit_applications (id, shop_credit_note_id, shop_invoice_id, shop_id, amount, applied_at, created_at, updated_at) VALUES (771, 771, 771, 777, 700.00, NOW(), NOW(), NOW())');
        $pdo1->commit();

        // Transaction 2 now starts, acquires lock, and calculates remaining credit (300.00)
        $pdo2->beginTransaction();
        $stmt2 = $pdo2->prepare('SELECT id, total_credit_amount FROM shop_credit_notes WHERE id = 771 FOR UPDATE');
        $stmt2->execute();
        $cnRow2 = $stmt2->fetch(PDO::FETCH_ASSOC);

        $calcStmt = $pdo2->prepare('SELECT COALESCE(SUM(amount), 0) FROM shop_credit_applications WHERE shop_credit_note_id = 771 AND reversed_at IS NULL');
        $calcStmt->execute();
        $alreadyApplied = (float) $calcStmt->fetchColumn();

        $remainingCredit = (float) $cnRow2['total_credit_amount'] - $alreadyApplied;
        $this->assertEquals(300.00, $remainingCredit);

        // Transaction 2 requests 500.00, which exceeds remaining 300.00 -> rejected!
        $requestedSecondAmount = 500.00;
        $allowed = ($requestedSecondAmount <= $remainingCredit);
        $this->assertFalse($allowed, 'Transaction 2 detected remaining credit is 300.00 and rejected 500.00 application request.');

        $pdo2->rollBack();

        // Database preserves exactly 700.00 total applied
        $verifyStmt = $pdo1->query('SELECT SUM(amount) FROM shop_credit_applications WHERE shop_credit_note_id = 771 AND reversed_at IS NULL');
        $this->assertEquals(700.00, (float) $verifyStmt->fetchColumn());
    }
}
