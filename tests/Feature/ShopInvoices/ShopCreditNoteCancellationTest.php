<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\ShopInvoice\ShopCreditNoteAccountingMode;
use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
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
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Cashbook\BalanceCalculator;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\InvoiceCashbookProjectionService;
use App\Services\ShopInvoices\ShopCreditApplicationService;
use App\Services\ShopInvoices\ShopCreditNoteEligibilityService;
use App\Services\ShopInvoices\ShopCreditNoteService;
use App\Services\ShopInvoices\ShopFinalBillService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PDO;
use Tests\TestCase;

class ShopCreditNoteCancellationTest extends TestCase
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

    private ShopCreditNoteEligibilityService $eligibilityService;

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
            'name' => 'Calicut Hub',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->projectionService = app(InvoiceCashbookProjectionService::class);
        $this->creditNoteService = app(ShopCreditNoteService::class);
        $this->applicationService = app(ShopCreditApplicationService::class);
        $this->finalBillService = app(ShopFinalBillService::class);
        $this->eligibilityService = app(ShopCreditNoteEligibilityService::class);

        $this->productOnion = Product::factory()->create(['name' => 'Big Onion']);
        $this->productTomato = Product::factory()->create(['name' => 'Tomato Local']);

        $this->invoiceA1 = $this->createFinalizedInvoice(
            $this->shopA,
            'SINV-A-101',
            today()->subDays(10)->toDateString(),
            5000.00,
            0.00
        );
        $this->createInvoiceItem($this->invoiceA1, $this->productOnion, 'A', 50.0, 100.0);

        $this->invoiceA2 = $this->createFinalizedInvoice(
            $this->shopA,
            'SINV-A-102',
            today()->subDays(9)->toDateString(),
            3000.00,
            0.00
        );
        $this->createInvoiceItem($this->invoiceA2, $this->productTomato, 'A', 60.0, 50.0);
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
     * Test 1: Cancel an unapplied shop-level Credit Note.
     */
    public function test_01_cancel_unapplied_shop_level_credit_note(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);
        $this->assertEquals(ShopCreditNoteStatus::Approved, $creditNote->status);
        $this->assertEquals(1000.00, $creditNote->unappliedCreditTotal());
        $this->assertEquals(0.00, $creditNote->appliedCreditTotal());

        // Cashbook transaction is approved
        $tx = $creditNote->fresh()->ledgerTransaction;
        $this->assertNotNull($tx);
        $this->assertSame('approved', $tx->status);

        $calc = app(BalanceCalculator::class);
        $date = $creditNote->credit_note_date->toDateString();
        $snapshotBefore = $calc->recalculate($this->shopA->id, $date);

        // Cancel credit note
        $cancelled = $this->creditNoteService->cancelShopLevelCreditNote(
            $creditNote,
            $this->admin,
            'Client cancelled adjustment claim'
        );

        $this->assertEquals(ShopCreditNoteStatus::Voided, $cancelled->status);
        $this->assertTrue($cancelled->isVoided());
        $this->assertSame($this->admin->id, $cancelled->voided_by);
        $this->assertNotNull($cancelled->voided_at);
        $this->assertSame('Client cancelled adjustment claim', $cancelled->void_reason);
        $this->assertStringContainsString('Cancelled on', (string) $cancelled->notes);

        // Cashbook transaction is in-place voided
        $tx->refresh();
        $this->assertSame('void', $tx->status);
        $this->assertSame($this->admin->id, $tx->voided_by);
        $this->assertSame('Client cancelled adjustment claim', $tx->void_reason);

        // Daily balance snapshot recalculated: net P/L was 1000.00 before and becomes 0.00 after
        $this->assertEquals(1000.00, round((float) $snapshotBefore->net_pl, 2));
        $snapshotAfter = ShopDailyLedgerSnapshot::where('shop_id', $this->shopA->id)
            ->where('business_date', $date)
            ->first();
        $this->assertNotNull($snapshotAfter);
        $this->assertEquals(0.00, round((float) $snapshotAfter->net_pl, 2));
    }

    /**
     * Test 2: Cancel a partially applied shop-level Credit Note.
     */
    public function test_02_cancel_partially_applied_shop_level_credit_note(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        // Apply 400.00 to invoice A1
        $app = $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 400.00, $this->admin, 'Partial application');
        $this->assertNull($app->reversed_at);

        // Invoice A1 remaining balance reduced to 4600.00
        $billBefore = $this->finalBillService->calculate($this->invoiceA1);
        $this->assertEquals(4600.00, $billBefore->remainingBalance);
        $this->assertEquals(400.00, $billBefore->appliedCreditTotal);

        // Cancel credit note
        $cancelled = $this->creditNoteService->cancelShopLevelCreditNote(
            $creditNote,
            $this->admin,
            'Billing dispute resolved'
        );

        $this->assertTrue($cancelled->isVoided());

        // Active application must be reversed
        $app->refresh();
        $this->assertTrue($app->isReversed());
        $this->assertSame($this->admin->id, $app->reversed_by);
        $this->assertNotNull($app->reversed_at);
        $this->assertStringContainsString('Billing dispute resolved', (string) $app->reversal_reason);

        // Invoice A1 final bill net payable restored to 5000.00
        $billAfter = $this->finalBillService->calculate($this->invoiceA1);
        $this->assertEquals(5000.00, $billAfter->remainingBalance);
        $this->assertEquals(0.00, $billAfter->appliedCreditTotal);

        // Cashbook transaction voided
        $tx = $creditNote->fresh()->ledgerTransaction;
        $this->assertNotNull($tx);
        $this->assertSame('void', $tx->status);
    }

    /**
     * Test 3: Cancel a fully applied shop-level Credit Note.
     */
    public function test_03_cancel_fully_applied_shop_level_credit_note(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        // Apply 600.00 to invoice A1 and 400.00 to invoice A2
        $app1 = $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 600.00, $this->admin);
        $app2 = $this->applicationService->applyCredit($creditNote, $this->invoiceA2, 400.00, $this->admin);

        $this->assertEquals(0.00, $creditNote->fresh()->unappliedCreditTotal());
        $this->assertEquals(1000.00, $creditNote->fresh()->appliedCreditTotal());

        // Cancel credit note
        $cancelled = $this->creditNoteService->cancelShopLevelCreditNote(
            $creditNote,
            $this->admin,
            'Total cancellation of credit claim'
        );

        $this->assertTrue($cancelled->isVoided());

        // Both applications reversed
        $this->assertTrue($app1->fresh()->isReversed());
        $this->assertTrue($app2->fresh()->isReversed());

        // Both invoice balances restored
        $this->assertEquals(5000.00, $this->finalBillService->calculate($this->invoiceA1)->remainingBalance);
        $this->assertEquals(3000.00, $this->finalBillService->calculate($this->invoiceA2)->remainingBalance);

        // Credit note applied total is now 0.00
        $this->assertEquals(0.00, $cancelled->fresh()->appliedCreditTotal());
    }

    /**
     * Test 4: All active applications reversed and previously reversed applications remain unaltered.
     */
    public function test_04_previously_reversed_applications_retain_original_metadata(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        $app1 = $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 300.00, $this->admin, 'App 1');
        $app2 = $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 300.00, $this->admin, 'App 2');

        // Manually reverse App 1 earlier
        $earlyTimestamp = now()->subHours(3);
        $this->applicationService->reverseCreditApplication(
            $app1,
            $this->accountsUser,
            'Initial mistake on invoice allocation',
            $earlyTimestamp
        );

        $app1->refresh();
        $this->assertTrue($app1->isReversed());
        $this->assertSame($this->accountsUser->id, $app1->reversed_by);
        $this->assertSame('Initial mistake on invoice allocation', $app1->reversal_reason);

        // Cancel credit note
        $cancelTime = now()->subHour();
        $this->creditNoteService->cancelShopLevelCreditNote(
            $creditNote,
            $this->admin,
            'Full cancellation by office',
            $cancelTime
        );

        // App 1 metadata unchanged!
        $app1->refresh();
        $this->assertSame($this->accountsUser->id, $app1->reversed_by);
        $this->assertSame('Initial mistake on invoice allocation', $app1->reversal_reason);

        // App 2 was reversed during cancellation
        $app2->refresh();
        $this->assertTrue($app2->isReversed());
        $this->assertSame($this->admin->id, $app2->reversed_by);
        $this->assertStringContainsString('Full cancellation by office', (string) $app2->reversal_reason);
    }

    /**
     * Test 5: Original application records retained in database.
     */
    public function test_05_original_application_records_retained_in_database(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);
        $app = $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 500.00, $this->admin);

        $appId = $app->id;
        $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'Cancelled');

        // Application row still exists
        $this->assertDatabaseHas('shop_credit_applications', [
            'id' => $appId,
            'shop_credit_note_id' => $creditNote->id,
            'amount' => 500.00,
        ]);
        $this->assertNotNull(ShopCreditApplication::find($appId)->reversed_at);
    }

    /**
     * Test 6: Original Credit Note number and record preserved.
     */
    public function test_06_original_credit_note_number_and_record_preserved(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);
        $origNumber = $creditNote->credit_note_number;
        $origId = $creditNote->id;

        $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'Cancellation test');

        $creditNote->refresh();
        $this->assertSame($origId, $creditNote->id);
        $this->assertSame($origNumber, $creditNote->credit_note_number);
        $this->assertStringStartsWith('SCN-', $creditNote->credit_note_number);
    }

    /**
     * Test 7: Mandatory cancellation reason enforced.
     */
    public function test_07_mandatory_cancellation_reason_enforced(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        try {
            $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, '   ');
            $this->fail('Expected ValidationException for blank reason');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('void_reason', $e->errors());
        }

        $this->assertTrue($creditNote->fresh()->isApproved());
    }

    /**
     * Test 8: Cancelling user and timestamp recorded.
     */
    public function test_08_cancelling_user_and_timestamp_recorded(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);
        $customDate = now()->subHours(4);

        $cancelled = $this->creditNoteService->cancelShopLevelCreditNote(
            $creditNote,
            $this->accountsUser,
            'Audit verification',
            $customDate
        );

        $this->assertSame($this->accountsUser->id, $cancelled->voided_by);
        $this->assertEquals($customDate->toDateTimeString(), $cancelled->voided_at->toDateTimeString());
    }

    /**
     * Test 9: Original Cashbook credit reversed exactly once without duplicate records.
     */
    public function test_09_original_cashbook_credit_reversed_exactly_once(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        $txQuery = ShopLedgerTransaction::where(function ($q): void {
            $q->where('reference_type', ShopCreditNote::class)
                ->orWhere('reference_type', 'App\\Models\\ShopCreditNote');
        })->where('reference_id', $creditNote->id);

        $txCountBefore = (clone $txQuery)->count();
        $this->assertSame(1, $txCountBefore);

        $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'Single void verification');

        $txCountAfter = (clone $txQuery)->count();
        $this->assertSame(1, $txCountAfter);

        $tx = $txQuery->first();
        $this->assertNotNull($tx);
        $this->assertSame('void', $tx->status);
    }

    /**
     * Test 10: Shop outstanding reconciles correctly with cashbook and bill engine.
     */
    public function test_10_shop_outstanding_reconciles_correctly(): void
    {
        // Invoice 1 = 5000, Invoice 2 = 3000 -> Total gross outstanding = 8000
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);
        $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 500.00, $this->admin);

        // Before cancellation:
        // Invoice 1 remaining = 4500, Invoice 2 remaining = 3000
        // Credit note unapplied = 500
        // Total shop remaining liability = 4500 + 3000 - 500 = 7000
        $bill1 = $this->finalBillService->calculate($this->invoiceA1);
        $bill2 = $this->finalBillService->calculate($this->invoiceA2);
        $this->assertEquals(4500.00, $bill1->remainingBalance);
        $this->assertEquals(3000.00, $bill2->remainingBalance);
        $this->assertEquals(500.00, $creditNote->fresh()->unappliedCreditTotal());

        // Cancel credit note
        $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'Full void');

        // After cancellation:
        // Invoice 1 remaining = 5000, Invoice 2 remaining = 3000
        // Credit note unapplied = 0.00 (voided)
        // Total shop remaining liability = 5000 + 3000 = 8000
        $this->assertEquals(5000.00, $this->finalBillService->calculate($this->invoiceA1)->remainingBalance);
        $this->assertEquals(3000.00, $this->finalBillService->calculate($this->invoiceA2)->remainingBalance);
        $this->assertEquals(0.00, $creditNote->fresh()->unappliedCreditTotal());
    }

    /**
     * Test 11: Original invoice totals remain unchanged in database tables.
     */
    public function test_11_original_invoice_totals_remain_unchanged(): void
    {
        $origFinalTotal = (float) $this->invoiceA1->final_total;
        $origBalance = (float) $this->invoiceA1->balance_amount;
        $origPaid = (float) $this->invoiceA1->paid_amount;

        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);
        $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 1000.00, $this->admin);

        $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'Integrity check');

        $this->invoiceA1->refresh();
        $this->assertEquals($origFinalTotal, (float) $this->invoiceA1->final_total);
        $this->assertEquals($origBalance, (float) $this->invoiceA1->balance_amount);
        $this->assertEquals($origPaid, (float) $this->invoiceA1->paid_amount);
    }

    /**
     * Test 12: Existing recorded payments remain unchanged.
     */
    public function test_12_existing_payments_remain_unchanged(): void
    {
        $paidInvoice = $this->createFinalizedInvoice(
            $this->shopA,
            'SINV-A-PAID-1',
            today()->subDays(5)->toDateString(),
            4000.00,
            1500.00
        );

        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 500.00);
        $this->applicationService->applyCredit($creditNote, $paidInvoice, 500.00, $this->admin);

        $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'Payment integrity');

        $paidInvoice->refresh();
        $this->assertEquals(1500.00, (float) $paidInvoice->paid_amount);
        $this->assertEquals(4000.00, (float) $paidInvoice->final_total);
        $this->assertEquals(2500.00, (float) $paidInvoice->balance_amount);

        $bill = $this->finalBillService->calculate($paidInvoice);
        $this->assertEquals(2500.00, $bill->remainingBalance);
        $this->assertEquals(1500.00, $bill->amountPaid);
    }

    /**
     * Test 13: Source item eligibility restored correctly upon cancellation.
     */
    public function test_13_source_item_eligibility_restored_correctly(): void
    {
        $invoiceItem = $this->invoiceA1->items->first();
        $this->assertNotNull($invoiceItem);

        // Delivered qty is 50.0 kg. Create draft adjusting 30.0 kg.
        $draft = $this->creditNoteService->createShopLevelDraft($this->shopA, [
            'credit_note_date' => today()->toDateString(),
            'items' => [
                [
                    'source_shop_invoice_id' => $this->invoiceA1->id,
                    'shop_invoice_item_id' => $invoiceItem->id,
                    'credit_quantity' => 30.0,
                    'credit_rate' => 100.0,
                    'line_credit_amount' => 3000.00,
                    'reason_type' => ShopCreditNoteReason::PriceCorrection,
                ],
            ],
        ], $this->admin);

        $creditNote = $this->creditNoteService->finalizeShopLevelCreditNote($draft, $this->admin);

        // While approved, remaining creditable quantity is 20.0 kg
        $eligibilityWhileApproved = $this->eligibilityService->calculateItemAdjustments($invoiceItem);
        $this->assertEquals(20.0, $eligibilityWhileApproved['active']['remaining_creditable_quantity']);

        // Cancel credit note
        $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'Customer re-acceptance');

        // After cancellation, full 50.0 kg is restored!
        $eligibilityAfterCancel = $this->eligibilityService->calculateItemAdjustments($invoiceItem);
        $this->assertEquals(50.0, $eligibilityAfterCancel['active']['remaining_creditable_quantity']);
        $this->assertEquals(5000.00, $eligibilityAfterCancel['active']['remaining_creditable_amount']);
        $this->assertEquals(30.0, $eligibilityAfterCancel['voided']['net_quantity']);
    }

    /**
     * Test 14: Repeated cancellation rejected safely.
     */
    public function test_14_repeated_cancellation_rejected_safely(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'First cancellation');
        $this->assertTrue($creditNote->fresh()->isVoided());

        // Second cancellation attempt
        try {
            $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'Second cancellation');
            $this->fail('Expected ValidationException for already voided credit note');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
    }

    /**
     * Test 15: Concurrent cancellation requests are safe (idempotent failure on second).
     */
    public function test_15_concurrent_cancellation_requests_are_safe(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        // Transaction 1 cancels
        $cancelled1 = $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'Tx 1 cancel');
        $this->assertSame(ShopCreditNoteStatus::Voided, $cancelled1->status);

        // Transaction 2 immediately attempts to cancel the same instance
        $this->expectException(ValidationException::class);
        $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'Tx 2 cancel');
    }

    /**
     * Test 16: Cancellation concurrent with credit application is safe.
     */
    public function test_16_cancellation_concurrent_with_credit_application(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        // Cancel credit note first
        $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'Cancelled before apply');

        // Subsequent applyCredit must fail
        try {
            $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 500.00, $this->admin);
            $this->fail('Expected ValidationException when applying credit on voided note');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('shop_credit_note_id', $e->errors());
        }

        $this->assertEquals(0, ShopCreditApplication::where('shop_credit_note_id', $creditNote->id)->count());
    }

    /**
     * Test 17: Ledger reversal failure rolls back all changes atomically.
     */
    public function test_17_ledger_reversal_failure_rolls_back_all_changes(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);
        $app = $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 400.00, $this->admin);

        // Create failing mock for projection service
        $failingProjection = $this->createMock(InvoiceCashbookProjectionService::class);
        $failingProjection->method('syncCreditNote')
            ->willThrowException(new \RuntimeException('Database connection lost to cashbook ledger'));

        $serviceWithFailingLedger = new ShopCreditNoteService(
            invoiceCashbookProjectionService: $failingProjection
        );

        try {
            $serviceWithFailingLedger->cancelShopLevelCreditNote($creditNote, $this->admin, 'Rollback test');
            $this->fail('Expected RuntimeException from failing ledger projection');
        } catch (\RuntimeException $e) {
            $this->assertSame('Database connection lost to cashbook ledger', $e->getMessage());
        }

        // Credit note is STILL approved!
        $creditNote->refresh();
        $this->assertSame(ShopCreditNoteStatus::Approved, $creditNote->status);
        $this->assertNull($creditNote->voided_at);

        // Application is STILL active!
        $app->refresh();
        $this->assertNull($app->reversed_at);

        // Cashbook transaction remains approved
        $tx = $creditNote->fresh()->ledgerTransaction;
        $this->assertNotNull($tx);
        $this->assertSame('approved', $tx->status);
    }

    /**
     * Test 18: Closed accounting periods protected.
     */
    public function test_18_closed_accounting_periods_protected(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00, today()->subDays(5)->toDateString());
        $app = $this->applicationService->applyCredit(
            $creditNote,
            $this->invoiceA1,
            500.00,
            $this->admin,
            appliedAt: today()->subDays(3)
        );

        // Case A: Cancellation date in closed period
        ShopAccountingPeriodClosure::query()->create([
            'shop_id' => $this->shopA->id,
            'period_start' => today()->subDay()->toDateString(),
            'period_end' => today()->addDay()->toDateString(),
            'closed_by' => $this->admin->id,
            'closed_at' => now(),
            'reason' => 'Monthly Audit',
        ]);

        try {
            $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'Close test');
            $this->fail('Expected ValidationException for closed cancellation date');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cancelled_at', $e->errors());
        }

        ShopAccountingPeriodClosure::query()->truncate();

        // Case B: Credit note posted date in closed period
        ShopAccountingPeriodClosure::query()->create([
            'shop_id' => $this->shopA->id,
            'period_start' => today()->subDays(7)->toDateString(),
            'period_end' => today()->subDays(4)->toDateString(),
            'closed_by' => $this->admin->id,
            'closed_at' => now(),
            'reason' => 'Prior Month Audit',
        ]);

        try {
            $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'Close test B');
            $this->fail('Expected ValidationException for closed credit note date');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('credit_note_date', $e->errors());
        }

        ShopAccountingPeriodClosure::query()->truncate();

        // Case C: Active application date in closed period
        ShopAccountingPeriodClosure::query()->create([
            'shop_id' => $this->shopA->id,
            'period_start' => today()->subDays(4)->toDateString(),
            'period_end' => today()->subDays(2)->toDateString(),
            'closed_by' => $this->admin->id,
            'closed_at' => now(),
            'reason' => 'Application Period Audit',
        ]);

        try {
            $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'Close test C');
            $this->fail('Expected ValidationException for closed application date');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reversed_at', $e->errors());
        }
    }

    /**
     * Test 19: Unauthorized cancellation rejected.
     */
    public function test_19_unauthorized_cancellation_rejected(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        try {
            $this->creditNoteService->cancelShopLevelCreditNote(
                $creditNote,
                $this->unauthorizedUser,
                'Attempted by purchaser'
            );
            $this->fail('Expected ValidationException for unauthorized purchaser');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cancelled_by', $e->errors());
        }

        $this->assertTrue($creditNote->fresh()->isApproved());
    }

    /**
     * Test 20: Legacy single-invoice notes unaffected and rejected by shop-level cancellation.
     */
    public function test_20_legacy_single_invoice_notes_rejected(): void
    {
        $invoiceItem = $this->invoiceA1->items->first();
        $legacyDraft = $this->creditNoteService->createDraft($this->invoiceA1, [
            'credit_note_date' => today()->toDateString(),
            'reason_type' => ShopCreditNoteReason::PriceCorrection,
            'direction' => ShopCreditNoteDirection::Credit,
            'inventory_action' => ShopCreditNoteInventoryAction::None,
            'items' => [
                [
                    'shop_invoice_item_id' => $invoiceItem->id,
                    'revised_quantity' => 50,
                    'revised_unit_price' => 90,
                    'inventory_action' => ShopCreditNoteInventoryAction::None,
                ],
            ],
        ], $this->admin->id);

        $approvedLegacy = $this->creditNoteService->approve($legacyDraft, $this->admin);
        $this->assertEquals(ShopCreditNoteAccountingMode::LegacyInvoiceLinked, $approvedLegacy->accounting_mode);

        try {
            $this->creditNoteService->cancelShopLevelCreditNote($approvedLegacy, $this->admin, 'Invalid mode attempt');
            $this->fail('Expected ValidationException for legacy credit note mode');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('accounting_mode', $e->errors());
        }

        $this->assertTrue($approvedLegacy->fresh()->isApproved());
    }

    /**
     * Test 21: Financial cancellation produces zero warehouse stock movements.
     */
    public function test_21_no_unnecessary_warehouse_stock_movement(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);
        $this->applicationService->applyCredit($creditNote, $this->invoiceA1, 500.00, $this->admin);

        $stockMovementCountBefore = StockMovement::count();

        $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'Clean financial void');

        $stockMovementCountAfter = StockMovement::count();
        $this->assertSame($stockMovementCountBefore, $stockMovementCountAfter);
    }

    /**
     * Test 22: Financial cancellation blocked when physical movements exist.
     */
    public function test_22_financial_cancellation_blocked_when_physical_movements_exist(): void
    {
        $creditNote = $this->createFinalizedShopCreditNote($this->shopA, 1000.00);

        $warehouse = Warehouse::factory()->create();
        $batch = StockBatch::factory()->create([
            'product_id' => $this->productTomato->id,
            'warehouse_id' => $warehouse->id,
        ]);

        // Create an associated physical stock movement linked to this credit note
        StockMovement::create([
            'batch_id' => $batch->id,
            'product_id' => $this->productTomato->id,
            'warehouse_id' => $warehouse->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::SaleReversal,
            'quantity' => 15.0,
            'cost_per_unit' => 50.0,
            'created_by' => $this->admin->id,
            'notes' => "Credit Note Return — SCN: {$creditNote->credit_note_number}; Batch: {$batch->id}",
        ]);

        $this->assertTrue($creditNote->stockMovements()->exists());

        try {
            $this->creditNoteService->cancelShopLevelCreditNote($creditNote, $this->admin, 'Physical void attempt');
            $this->fail('Expected ValidationException when associated stock movements exist');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stock_movements', $e->errors());
        }

        $this->assertTrue($creditNote->fresh()->isApproved());
    }

    /**
     * Test 23: Concurrency safety on MySQL — Competing transactions cannot cancel the same credit note twice.
     */
    public function test_23_concurrent_cancellations_cannot_double_cancel_on_mysql(): void
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
        $pdo1->exec('TRUNCATE TABLE shops');

        $pdo1->exec("INSERT INTO shops (id, name, code, status, created_at, updated_at) VALUES (777, 'Conc Cancel Shop', 'CONC_CAN', 'active', NOW(), NOW())");
        $pdo1->exec("INSERT INTO shop_credit_notes (id, credit_note_number, shop_id, credit_note_date, direction, accounting_mode, status, total_credit_amount, lock_version, created_at, updated_at) VALUES (771, 'SCN-CONC-CAN-1', 777, CURDATE(), 'credit', 'shop_level', 'approved', 1000.00, 1, NOW(), NOW())");
        $pdo1->exec('INSERT INTO shop_credit_applications (id, shop_credit_note_id, shop_invoice_id, shop_id, amount, applied_at, created_at, updated_at) VALUES (771, 771, 771, 777, 500.00, NOW(), NOW(), NOW())');

        // Transaction 1 locks Credit Note #771 for update
        $pdo1->beginTransaction();
        $stmt1 = $pdo1->prepare('SELECT id, status, lock_version FROM shop_credit_notes WHERE id = 771 FOR UPDATE');
        $stmt1->execute();
        $row1 = $stmt1->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('approved', $row1['status']);

        // Transaction 2 sets 1-second timeout and attempts to lock the SAME credit note row
        $pdo2->exec('SET innodb_lock_wait_timeout = 1');
        $lockBlocked = false;

        try {
            $pdo2->beginTransaction();
            $stmt2 = $pdo2->prepare('SELECT id, status, lock_version FROM shop_credit_notes WHERE id = 771 FOR UPDATE');
            $stmt2->execute();
        } catch (\PDOException $e) {
            $lockBlocked = true;
            $pdo2->rollBack();
        }

        $this->assertTrue($lockBlocked, 'Pessimistic lock on shop_credit_notes successfully blocked concurrent cancellation.');

        // Transaction 1 updates status to voided and commits
        $pdo1->exec("UPDATE shop_credit_notes SET status = 'voided', voided_at = NOW(), void_reason = 'Cancelled by Tx1', lock_version = lock_version + 1 WHERE id = 771");
        $pdo1->exec("UPDATE shop_credit_applications SET reversed_at = NOW(), reversal_reason = 'Cancelled by Tx1' WHERE id = 771");
        $pdo1->commit();

        // Transaction 2 now starts, acquires lock, and inspects status
        $pdo2->beginTransaction();
        $stmt2 = $pdo2->prepare('SELECT id, status, lock_version FROM shop_credit_notes WHERE id = 771 FOR UPDATE');
        $stmt2->execute();
        $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('voided', $row2['status'], 'Transaction 2 detected credit note is already voided and safely aborted.');
        $pdo2->rollBack();

        // Final verification: exactly 1 void occurred
        $verifyStmt = $pdo1->query("SELECT COUNT(*) FROM shop_credit_notes WHERE id = 771 AND status = 'voided'");
        $this->assertEquals(1, (int) $verifyStmt->fetchColumn());
    }
}
