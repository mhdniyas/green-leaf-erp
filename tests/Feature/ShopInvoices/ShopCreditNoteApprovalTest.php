<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCreditNote;
use App\Models\ShopCreditNoteItem;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\InvoiceCashbookProjectionService;
use App\Services\ShopInvoices\ShopCreditNoteService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShopCreditNoteApprovalTest extends TestCase
{
    use RefreshDatabase;

    private ShopCreditNoteService $service;

    private InvoiceCashbookProjectionService $projectionService;

    private User $admin;

    private User $regularUser;

    private Shop $shop;

    private ShopInvoice $invoice;

    private ShopInvoiceItem $tomatoItem;

    private ShopInvoiceItem $onionItem;

    private Product $tomato;

    private Product $onion;

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

        $this->regularUser = User::factory()->create();
        $this->regularUser->assignRole('shop');

        $this->service = app(ShopCreditNoteService::class);
        $this->projectionService = app(InvoiceCashbookProjectionService::class);

        $this->shop = Shop::query()->create([
            'name' => 'City Fresh Mart',
            'code' => 'CFM01',
            'warehouse_tag' => 'CFM',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->tomato = Product::factory()->create([
            'name' => 'Tomato Local',
            'sku' => 'TOM-001',
            'unit' => 'kg',
        ]);

        $this->onion = Product::factory()->create([
            'name' => 'Onion Red',
            'sku' => 'ONI-001',
            'unit' => 'kg',
        ]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-20261105-001',
            'business_date' => '2026-11-05',
        ]);

        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 100,
            'approved_qty' => 100,
            'loaded_qty' => 100,
            'delivered_qty' => 100,
            'unit' => 'kg',
            'unit_price' => 60.00,
            'line_total' => 6000.00,
        ]);

        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->onion->id,
            'product_grade' => 'A',
            'requested_qty' => 80,
            'approved_qty' => 80,
            'loaded_qty' => 80,
            'delivered_qty' => 80,
            'unit' => 'kg',
            'unit_price' => 50.00,
            'line_total' => 4000.00,
        ]);

        $this->invoice = ShopInvoice::query()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20261105-CFM01',
            'business_date' => '2026-11-05',
            'status' => 'finalized',
            'delivery_status' => 'received_full',
            'payment_status' => 'unpaid',
            'subtotal' => 10000.00,
            'discount_total' => 0.00,
            'final_total' => 10000.00,
            'paid_amount' => 0.00,
            'balance_amount' => 10000.00,
            'finalized_at' => now(),
            'finalized_by' => $this->admin->id,
        ]);

        $this->tomatoItem = ShopInvoiceItem::query()->create([
            'shop_invoice_id' => $this->invoice->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'product_name' => 'Tomato Local',
            'unit' => 'kg',
            'delivered_qty' => 100,
            'approved_qty' => 100,
            'unit_price' => 60.00,
            'line_subtotal' => 6000.00,
            'final_line_total' => 6000.00,
        ]);

        $this->onionItem = ShopInvoiceItem::query()->create([
            'shop_invoice_id' => $this->invoice->id,
            'product_id' => $this->onion->id,
            'product_grade' => 'A',
            'product_name' => 'Onion Red',
            'unit' => 'kg',
            'delivered_qty' => 80,
            'approved_qty' => 80,
            'unit_price' => 50.00,
            'line_subtotal' => 4000.00,
            'final_line_total' => 4000.00,
        ]);

        // Sync initial invoice into cashbook projection
        $this->projectionService->syncInvoice($this->invoice, $this->admin->id);
    }

    public function test_case_1_admin_approval_of_credit_note_reduces_net_payable(): void
    {
        // Original invoice: 10,000, Credit note: 500 -> Net shop payable: 9,500
        $draft = $this->service->createDraft($this->invoice, [
            'reason_type' => ShopCreditNoteReason::PriceCorrection,
            'direction' => ShopCreditNoteDirection::Credit,
            'inventory_action' => ShopCreditNoteInventoryAction::None,
            'items' => [
                [
                    'shop_invoice_item_id' => $this->tomatoItem->id,
                    'adjusted_quantity' => 0,
                    'adjusted_unit_price' => 0,
                    'line_credit_amount' => 500.00,
                    'direction' => ShopCreditNoteDirection::Credit,
                ],
            ],
        ], $this->admin->id);

        $this->assertTrue($draft->isDraft());

        $approved = $this->service->approve($draft, $this->admin, 'Approved billing correction');

        $this->assertTrue($approved->isApproved());
        $this->assertEquals($this->admin->id, $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
        $this->assertStringContainsString('Approved billing correction', (string) $approved->notes);

        // Original finalized invoice remains completely unchanged at 10,000
        $freshInvoice = $this->invoice->fresh();
        $this->assertEquals(10000.00, (float) $freshInvoice->final_total);
        $this->assertEquals(10000.00, (float) $freshInvoice->balance_amount);

        // Effective helpers reflect the adjustment
        $this->assertEquals(500.00, $freshInvoice->approvedCreditTotal());
        $this->assertEquals(0.00, $freshInvoice->approvedDebitTotal());
        $this->assertEquals(9500.00, $freshInvoice->effectiveFinalTotal());
        $this->assertEquals(9500.00, $freshInvoice->effectiveBalanceAmount());
        $this->assertEquals(0.00, $freshInvoice->overpaidAmount());
        $this->assertFalse($freshInvoice->isOverpaid());

        // Check ShopLedgerTransaction for the Credit Note
        $cnTx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $approved->id)
            ->first();

        $this->assertNotNull($cnTx);
        $this->assertEquals(500.00, (float) $cnTx->amount);
        $this->assertEquals('income', $cnTx->direction);
        $this->assertEquals(500.00, (float) $cnTx->pl_delta);
        $this->assertEquals('approved', $cnTx->status);

        // Original invoice purchase_bill ledger entry is fully preserved
        $invTx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopInvoice::class)
            ->where('reference_id', $this->invoice->id)
            ->first();

        $this->assertNotNull($invTx);
        $this->assertEquals(10000.00, (float) $invTx->amount);
        $this->assertEquals('expense', $invTx->direction);
        $this->assertEquals(-10000.00, (float) $invTx->pl_delta);

        // Check daily snapshot net P/L (-10000 + 500 = -9500)
        $snapshot = ShopDailyLedgerSnapshot::query()
            ->where('shop_id', $this->shop->id)
            ->where('business_date', '2026-11-05')
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertEquals(-9500.00, (float) $snapshot->net_pl);
    }

    public function test_case_2_admin_approval_of_debit_note_increases_net_payable(): void
    {
        // Original invoice: 10,000, Debit note: 200 -> Net shop payable: 10,200
        $draft = $this->service->createDraft($this->invoice, [
            'reason_type' => ShopCreditNoteReason::PriceCorrection,
            'direction' => ShopCreditNoteDirection::Debit,
            'inventory_action' => ShopCreditNoteInventoryAction::None,
            'items' => [
                [
                    'shop_invoice_item_id' => $this->onionItem->id,
                    'adjusted_quantity' => 0,
                    'adjusted_unit_price' => 0,
                    'line_credit_amount' => 200.00,
                    'direction' => ShopCreditNoteDirection::Debit,
                ],
            ],
        ], $this->admin->id);

        $approved = $this->service->approve($draft, $this->admin);

        $this->assertTrue($approved->isApproved());
        $this->assertTrue($approved->isDebit());

        // Original invoice in DB stays 10,000
        $freshInvoice = $this->invoice->fresh();
        $this->assertEquals(10000.00, (float) $freshInvoice->final_total);

        // Effective payable is 10,200
        $this->assertEquals(0.00, $freshInvoice->approvedCreditTotal());
        $this->assertEquals(200.00, $freshInvoice->approvedDebitTotal());
        $this->assertEquals(10200.00, $freshInvoice->effectiveFinalTotal());
        $this->assertEquals(10200.00, $freshInvoice->effectiveBalanceAmount());

        // Check ShopLedgerTransaction for the Debit Note (Expense direction)
        $cnTx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $approved->id)
            ->first();

        $this->assertNotNull($cnTx);
        $this->assertEquals(200.00, (float) $cnTx->amount);
        $this->assertEquals('expense', $cnTx->direction);
        $this->assertEquals(-200.00, (float) $cnTx->pl_delta);

        // Snapshot net PL: -10000 - 200 = -10200
        $snapshot = ShopDailyLedgerSnapshot::query()
            ->where('shop_id', $this->shop->id)
            ->where('business_date', '2026-11-05')
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertEquals(-10200.00, (float) $snapshot->net_pl);
    }

    public function test_case_3_multiple_approved_adjustments_accumulate_correctly(): void
    {
        // Note 1: Credit 500
        $note1 = $this->service->createDraft($this->invoice, [
            'reason_type' => ShopCreditNoteReason::DamageWastage,
            'direction' => ShopCreditNoteDirection::Credit,
            'items' => [
                ['shop_invoice_item_id' => $this->tomatoItem->id, 'line_credit_amount' => 500.00, 'adjusted_quantity' => 0],
            ],
        ], $this->admin->id);
        $this->service->approve($note1, $this->admin);

        // Note 2: Credit 300
        $note2 = $this->service->createDraft($this->invoice, [
            'reason_type' => ShopCreditNoteReason::PriceCorrection,
            'direction' => ShopCreditNoteDirection::Credit,
            'items' => [
                ['shop_invoice_item_id' => $this->onionItem->id, 'line_credit_amount' => 300.00, 'adjusted_quantity' => 0],
            ],
        ], $this->admin->id);
        $this->service->approve($note2, $this->admin);

        // Note 3: Debit 100
        $note3 = $this->service->createDraft($this->invoice, [
            'reason_type' => ShopCreditNoteReason::PriceCorrection,
            'direction' => ShopCreditNoteDirection::Debit,
            'items' => [
                ['shop_invoice_item_id' => $this->tomatoItem->id, 'line_credit_amount' => 100.00, 'adjusted_quantity' => 0, 'direction' => ShopCreditNoteDirection::Debit],
            ],
        ], $this->admin->id);
        $this->service->approve($note3, $this->admin);

        $freshInvoice = $this->invoice->fresh();

        // Original 10,000 stays unmutated
        $this->assertEquals(10000.00, (float) $freshInvoice->final_total);

        // 10000 - 500 - 300 + 100 = 9300
        $this->assertEquals(800.00, $freshInvoice->approvedCreditTotal());
        $this->assertEquals(100.00, $freshInvoice->approvedDebitTotal());
        $this->assertEquals(9300.00, $freshInvoice->effectiveFinalTotal());
        $this->assertEquals(9300.00, $freshInvoice->effectiveBalanceAmount());

        // Snapshot net PL: -10000 + 500 + 300 - 100 = -9300
        $snapshot = ShopDailyLedgerSnapshot::query()
            ->where('shop_id', $this->shop->id)
            ->where('business_date', '2026-11-05')
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertEquals(-9300.00, (float) $snapshot->net_pl);
    }

    public function test_case_4_draft_and_voided_notes_have_zero_financial_effect(): void
    {
        // Create draft (not approved)
        $draft = $this->service->createDraft($this->invoice, [
            'direction' => ShopCreditNoteDirection::Credit,
            'items' => [
                ['shop_invoice_item_id' => $this->tomatoItem->id, 'line_credit_amount' => 400.00, 'adjusted_quantity' => 0],
            ],
        ], $this->admin->id);

        // Create voided note
        $voided = ShopCreditNote::factory()->create([
            'shop_id' => $this->shop->id,
            'shop_invoice_id' => $this->invoice->id,
            'direction' => ShopCreditNoteDirection::Credit,
            'status' => ShopCreditNoteStatus::Voided,
            'total_credit_amount' => 600.00,
        ]);

        $freshInvoice = $this->invoice->fresh();

        $this->assertEquals(0.00, $freshInvoice->approvedCreditTotal());
        $this->assertEquals(0.00, $freshInvoice->approvedDebitTotal());
        $this->assertEquals(10000.00, $freshInvoice->effectiveFinalTotal());
        $this->assertEquals(10000.00, $freshInvoice->effectiveBalanceAmount());

        // No ledger transaction exists for draft
        $draftTx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $draft->id)
            ->first();
        $this->assertNull($draftTx);

        // Snapshot net PL remains -10000.00
        $snapshot = ShopDailyLedgerSnapshot::query()
            ->where('shop_id', $this->shop->id)
            ->where('business_date', '2026-11-05')
            ->first();

        $this->assertEquals(-10000.00, (float) $snapshot->net_pl);
    }

    public function test_case_5_re_approval_of_already_approved_note_is_rejected(): void
    {
        $draft = $this->service->createDraft($this->invoice, [
            'direction' => ShopCreditNoteDirection::Credit,
            'items' => [
                ['shop_invoice_item_id' => $this->tomatoItem->id, 'line_credit_amount' => 500.00, 'adjusted_quantity' => 0],
            ],
        ], $this->admin->id);

        $approved = $this->service->approve($draft, $this->admin);
        $this->assertTrue($approved->isApproved());

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Credit note is already approved.');

        $this->service->approve($approved, $this->admin);
    }

    public function test_case_6_idempotent_ledger_sync_does_not_duplicate_transactions(): void
    {
        $draft = $this->service->createDraft($this->invoice, [
            'direction' => ShopCreditNoteDirection::Credit,
            'items' => [
                ['shop_invoice_item_id' => $this->tomatoItem->id, 'line_credit_amount' => 500.00, 'adjusted_quantity' => 0],
            ],
        ], $this->admin->id);

        $approved = $this->service->approve($draft, $this->admin);

        // Explicitly trigger syncCreditNote repeatedly
        $this->projectionService->syncCreditNote($approved, $this->admin->id);
        $this->projectionService->syncCreditNote($approved, $this->admin->id);

        // Exactly one ledger transaction row exists for this credit note
        $count = ShopLedgerTransaction::query()
            ->where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $approved->id)
            ->count();

        $this->assertEquals(1, $count);
    }

    public function test_case_7_non_admin_approval_attempts_are_rejected(): void
    {
        $draft = $this->service->createDraft($this->invoice, [
            'direction' => ShopCreditNoteDirection::Credit,
            'items' => [
                ['shop_invoice_item_id' => $this->tomatoItem->id, 'line_credit_amount' => 500.00, 'adjusted_quantity' => 0],
            ],
        ], $this->admin->id);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Only authorized administrators can approve credit notes.');

        $this->service->approve($draft, $this->regularUser);

        $freshDraft = $draft->fresh();
        $this->assertTrue($freshDraft->isDraft());
    }

    public function test_case_8_historical_invoice_adjustments_apply_without_altering_original_invoice(): void
    {
        // Simulate an invoice finalized 45 days ago
        $historicalDate = '2026-09-20';
        $historicalOrder = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-20260920-001',
            'business_date' => $historicalDate,
        ]);

        $historicalInvoice = ShopInvoice::query()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $historicalOrder->id,
            'invoice_number' => 'SINV-20260920-CFM01',
            'business_date' => $historicalDate,
            'status' => 'finalized',
            'delivery_status' => 'received_full',
            'payment_status' => 'unpaid',
            'subtotal' => 5000.00,
            'discount_total' => 0.00,
            'final_total' => 5000.00,
            'paid_amount' => 0.00,
            'balance_amount' => 5000.00,
            'finalized_at' => '2026-09-20 18:00:00',
            'finalized_by' => $this->admin->id,
        ]);

        $item = ShopInvoiceItem::query()->create([
            'shop_invoice_id' => $historicalInvoice->id,
            'product_id' => $this->tomato->id,
            'product_name' => 'Tomato Local',
            'product_grade' => 'A',
            'unit' => 'kg',
            'delivered_qty' => 50,
            'approved_qty' => 50,
            'unit_price' => 100.00,
            'line_subtotal' => 5000.00,
            'final_line_total' => 5000.00,
        ]);

        $this->projectionService->syncInvoice($historicalInvoice, $this->admin->id);

        // Create and approve credit note against historical invoice
        $draft = $this->service->createDraft($historicalInvoice, [
            'credit_note_date' => $historicalDate,
            'direction' => ShopCreditNoteDirection::Credit,
            'items' => [
                ['shop_invoice_item_id' => $item->id, 'line_credit_amount' => 450.00, 'adjusted_quantity' => 0],
            ],
        ], $this->admin->id);

        $approved = $this->service->approve($draft, $this->admin);

        $this->assertTrue($approved->isApproved());

        $fresh = $historicalInvoice->fresh();
        $this->assertEquals(5000.00, (float) $fresh->final_total);
        $this->assertEquals('2026-09-20 18:00:00', $fresh->finalized_at?->toDateTimeString());
        $this->assertEquals(4550.00, $fresh->effectiveFinalTotal());
        $this->assertEquals(4550.00, $fresh->effectiveBalanceAmount());
    }

    public function test_case_9_fully_paid_invoice_adjustment_creates_explicit_overpayment_liability(): void
    {
        // Invoice: 10,000, fully paid (paid_amount = 10,000, balance_amount = 0)
        $this->invoice->update([
            'paid_amount' => 10000.00,
            'balance_amount' => 0.00,
            'payment_status' => 'paid',
        ]);

        $draft = $this->service->createDraft($this->invoice, [
            'direction' => ShopCreditNoteDirection::Credit,
            'items' => [
                ['shop_invoice_item_id' => $this->tomatoItem->id, 'line_credit_amount' => 500.00, 'adjusted_quantity' => 0],
            ],
        ], $this->admin->id);

        $this->service->approve($draft, $this->admin);

        $fresh = $this->invoice->fresh();

        $this->assertEquals(10000.00, (float) $fresh->final_total);
        $this->assertEquals(10000.00, (float) $fresh->paid_amount);
        $this->assertEquals(9500.00, $fresh->effectiveFinalTotal());
        $this->assertEquals(0.00, $fresh->effectiveBalanceAmount());
        $this->assertEquals(500.00, $fresh->overpaidAmount());
        $this->assertTrue($fresh->isOverpaid());

        // Shop level overpayment recognition
        $this->assertEquals(500.00, $this->shop->totalOverpaidAmount());
        $this->assertEquals(0.00, $this->shop->totalEffectivePendingBalance());
    }

    public function test_case_10_partially_paid_invoice_reflects_remaining_balance_minus_credit(): void
    {
        // Invoice: 10,000, partially paid (paid_amount = 4,000, balance_amount = 6,000)
        $this->invoice->update([
            'paid_amount' => 4000.00,
            'balance_amount' => 6000.00,
            'payment_status' => 'partially_paid',
        ]);

        $draft = $this->service->createDraft($this->invoice, [
            'direction' => ShopCreditNoteDirection::Credit,
            'items' => [
                ['shop_invoice_item_id' => $this->tomatoItem->id, 'line_credit_amount' => 500.00, 'adjusted_quantity' => 0],
            ],
        ], $this->admin->id);

        $this->service->approve($draft, $this->admin);

        $fresh = $this->invoice->fresh();

        $this->assertEquals(10000.00, (float) $fresh->final_total);
        $this->assertEquals(4000.00, (float) $fresh->paid_amount);
        $this->assertEquals(9500.00, $fresh->effectiveFinalTotal());
        // 9,500 - 4,000 = 5,500
        $this->assertEquals(5500.00, $fresh->effectiveBalanceAmount());
        $this->assertEquals(0.00, $fresh->overpaidAmount());
        $this->assertFalse($fresh->isOverpaid());

        $this->assertEquals(5500.00, $this->shop->totalEffectivePendingBalance());
        $this->assertEquals(0.00, $this->shop->totalOverpaidAmount());
    }

    public function test_case_11_credit_exceeding_remaining_balance_correctly_recognizes_overpayment(): void
    {
        // Invoice: 10,000, paid: 8,000, remaining balance was 2,000
        $this->invoice->update([
            'paid_amount' => 8000.00,
            'balance_amount' => 2000.00,
            'payment_status' => 'partially_paid',
        ]);

        // Credit note for 3,000 (exceeds the 2,000 remaining balance)
        $draft = $this->service->createDraft($this->invoice, [
            'direction' => ShopCreditNoteDirection::Credit,
            'items' => [
                ['shop_invoice_item_id' => $this->tomatoItem->id, 'line_credit_amount' => 3000.00, 'adjusted_quantity' => 0],
            ],
        ], $this->admin->id);

        $this->service->approve($draft, $this->admin);

        $fresh = $this->invoice->fresh();

        $this->assertEquals(7000.00, $fresh->effectiveFinalTotal());
        $this->assertEquals(0.00, $fresh->effectiveBalanceAmount());
        // 8000 paid - 7000 effective total = 1000 overpayment liability
        $this->assertEquals(1000.00, $fresh->overpaidAmount());
        $this->assertTrue($fresh->isOverpaid());
        $this->assertEquals(1000.00, $this->shop->totalOverpaidAmount());
    }

    public function test_case_12_unsupported_stock_action_credit_note_approval_is_safely_rejected(): void
    {
        $draft = $this->service->createDraft($this->invoice, [
            'inventory_action' => ShopCreditNoteInventoryAction::DeductFromStock,
            'items' => [
                [
                    'shop_invoice_item_id' => $this->tomatoItem->id,
                    'adjusted_quantity' => 10,
                    'line_credit_amount' => 600.00,
                    'inventory_action' => ShopCreditNoteInventoryAction::DeductFromStock,
                ],
            ],
        ], $this->admin->id);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Inventory action 'Deduct Extra from Warehouse' is not supported in this step.");

        $this->service->approve($draft, $this->admin);

        $freshDraft = $draft->fresh();
        $this->assertTrue($freshDraft->isDraft());
    }

    public function test_case_13_concurrent_or_excessive_cumulative_approval_is_prevented(): void
    {
        // Tomato line total is 6,000
        // Draft 1 requests 4,000 credit
        $draft1 = $this->service->createDraft($this->invoice, [
            'items' => [
                ['shop_invoice_item_id' => $this->tomatoItem->id, 'line_credit_amount' => 4000.00, 'adjusted_quantity' => 0],
            ],
        ], $this->admin->id);

        // Draft 2 requests 3,000 credit (while draft 1 is still draft, cumulative active total was 4000, so remaining was 2000)
        // Wait, draft creation allowed up to remaining creditable amount:
        // Tomato has 6,000. Draft 1 takes 4,000 -> remaining active is 2,000.
        // Let's create Draft 2 with 2,000 credit:
        $draft2 = $this->service->createDraft($this->invoice, [
            'items' => [
                ['shop_invoice_item_id' => $this->tomatoItem->id, 'line_credit_amount' => 2000.00, 'adjusted_quantity' => 0],
            ],
        ], $this->admin->id);

        // Approve draft 1 (now 4,000 is approved)
        $this->service->approve($draft1, $this->admin);

        // Now suppose another note was approved in between that took the remaining 2,000
        $interimDraft = ShopCreditNote::factory()->create([
            'shop_id' => $this->shop->id,
            'shop_invoice_id' => $this->invoice->id,
            'status' => ShopCreditNoteStatus::Approved,
            'direction' => ShopCreditNoteDirection::Credit,
            'total_credit_amount' => 1500.00,
        ]);
        ShopCreditNoteItem::factory()->create([
            'shop_credit_note_id' => $interimDraft->id,
            'shop_invoice_item_id' => $this->tomatoItem->id,
            'direction' => ShopCreditNoteDirection::Credit,
            'line_credit_amount' => 1500.00,
            'adjusted_quantity' => 0,
        ]);

        // Now total approved is 4,000 + 1,500 = 5,500 out of 6,000. Remaining is only 500.
        // Attempting to approve draft 2 (which asks for 2,000) must fail revalidation!
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('exceeds current remaining creditable amount');

        $this->service->approve($draft2, $this->admin);
    }
}
