<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Models\Cashbook\ShopDailyLedgerSnapshot;
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
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\InvoiceCashbookProjectionService;
use App\Services\Finance\OwnedShopAccountingService;
use App\Services\ShopInvoices\ShopCreditNoteService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShopCreditNoteReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private ShopCreditNoteService $creditNoteService;

    private InvoiceCashbookProjectionService $projectionService;

    private OwnedShopAccountingService $ownedShopAccountingService;

    private User $admin;

    private Shop $shop;

    private Product $productA;

    private Product $productB;

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

        $this->creditNoteService = app(ShopCreditNoteService::class);
        $this->projectionService = app(InvoiceCashbookProjectionService::class);
        $this->ownedShopAccountingService = app(OwnedShopAccountingService::class);

        $this->shop = Shop::query()->create([
            'name' => 'Metro Fresh Store',
            'code' => 'MFS_01',
            'warehouse_tag' => 'MFS',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->productA = Product::factory()->create(['name' => 'Carrot Ooty', 'sku' => 'CAR-001', 'unit' => 'kg']);
        $this->productB = Product::factory()->create(['name' => 'Potato Agra', 'sku' => 'POT-001', 'unit' => 'kg']);
    }

    private function createSampleInvoice(string $businessDate = '2026-08-15', float $paidAmount = 0.0): ShopInvoice
    {
        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-'.str_replace('-', '', $businessDate).'-001',
            'business_date' => $businessDate,
        ]);

        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->productA->id,
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
            'product_id' => $this->productB->id,
            'product_grade' => 'A',
            'requested_qty' => 80,
            'approved_qty' => 80,
            'loaded_qty' => 80,
            'delivered_qty' => 80,
            'unit' => 'kg',
            'unit_price' => 50.00,
            'line_total' => 4000.00,
        ]);

        $balanceAmount = max(0.00, 10000.00 - $paidAmount);
        $paymentStatus = match (true) {
            $paidAmount <= 0.00 => 'unpaid',
            $balanceAmount <= 0.00 => 'paid',
            default => 'partially_paid',
        };

        $invoice = ShopInvoice::query()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-'.str_replace('-', '', $businessDate).'-MFS01',
            'business_date' => $businessDate,
            'status' => 'finalized',
            'delivery_status' => 'received_full',
            'payment_status' => $paymentStatus,
            'subtotal' => 10000.00,
            'discount_total' => 0.00,
            'final_total' => 10000.00,
            'paid_amount' => $paidAmount,
            'balance_amount' => $balanceAmount,
            'finalized_at' => Carbon::parse($businessDate)->setTime(18, 0, 0),
            'finalized_by' => $this->admin->id,
        ]);

        ShopInvoiceItem::query()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $this->productA->id,
            'product_name' => 'Carrot Ooty',
            'product_grade' => 'A',
            'unit' => 'kg',
            'delivered_qty' => 100,
            'approved_qty' => 100,
            'unit_price' => 60.00,
            'line_subtotal' => 6000.00,
            'final_line_total' => 6000.00,
        ]);

        ShopInvoiceItem::query()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $this->productB->id,
            'product_name' => 'Potato Agra',
            'product_grade' => 'A',
            'unit' => 'kg',
            'delivered_qty' => 80,
            'approved_qty' => 80,
            'unit_price' => 50.00,
            'line_subtotal' => 4000.00,
            'final_line_total' => 4000.00,
        ]);

        $this->projectionService->syncInvoice($invoice, $this->admin->id);

        return $invoice;
    }

    public function test_1_invoice_without_credit_notes_has_exact_baseline_figures(): void
    {
        $invoice = $this->createSampleInvoice('2026-08-15', 0.0);

        $summary = $invoice->financialReconciliationSummary();

        $this->assertSame(10000.00, $summary['original_invoice_total']);
        $this->assertSame(0.00, $summary['approved_credit_total']);
        $this->assertSame(0.00, $summary['approved_debit_total']);
        $this->assertSame(10000.00, $summary['net_payable']);
        $this->assertSame(0.00, $summary['amount_paid']);
        $this->assertSame(10000.00, $summary['remaining_balance']);
        $this->assertSame(0.00, $summary['overpayment_credit']);
        $this->assertFalse($summary['has_adjustments']);

        // Individual helpers
        $this->assertSame(10000.00, $invoice->originalInvoiceTotal());
        $this->assertSame(10000.00, $invoice->netPayable());
        $this->assertSame(10000.00, $invoice->remainingBalance());
        $this->assertSame(0.00, $invoice->amountPaid());
        $this->assertSame(0.00, $invoice->overpaymentCredit());
        $this->assertFalse($invoice->isOverpaid());
    }

    public function test_2_one_approved_credit_note_reconciles_cleanly(): void
    {
        $invoice = $this->createSampleInvoice('2026-08-15', 0.0);
        $item = $invoice->items->first();

        $draft = $this->creditNoteService->createDraft($invoice, [
            'credit_note_date' => '2026-08-15',
            'direction' => ShopCreditNoteDirection::Credit,
            'reason_type' => ShopCreditNoteReason::PriceCorrection,
            'items' => [
                ['shop_invoice_item_id' => $item->id, 'line_credit_amount' => 500.00, 'adjusted_quantity' => 0],
            ],
        ], $this->admin->id);

        $this->creditNoteService->approve($draft, $this->admin);

        $fresh = $invoice->fresh();
        $summary = $fresh->financialReconciliationSummary();

        $this->assertSame(10000.00, $summary['original_invoice_total']);
        $this->assertSame(500.00, $summary['approved_credit_total']);
        $this->assertSame(0.00, $summary['approved_debit_total']);
        $this->assertSame(9500.00, $summary['net_payable']);
        $this->assertSame(0.00, $summary['amount_paid']);
        $this->assertSame(9500.00, $summary['remaining_balance']);
        $this->assertSame(0.00, $summary['overpayment_credit']);
        $this->assertTrue($summary['has_adjustments']);
    }

    public function test_3_multiple_credit_and_debit_notes_accumulate_without_double_counting(): void
    {
        $invoice = $this->createSampleInvoice('2026-08-15', 0.0);
        $itemA = $invoice->items->first();
        $itemB = $invoice->items->last();

        // Note 1: Credit 500
        $n1 = $this->creditNoteService->createDraft($invoice, [
            'credit_note_date' => '2026-08-15',
            'direction' => ShopCreditNoteDirection::Credit,
            'items' => [['shop_invoice_item_id' => $itemA->id, 'line_credit_amount' => 500.00, 'adjusted_quantity' => 0]],
        ], $this->admin->id);
        $this->creditNoteService->approve($n1, $this->admin);

        // Note 2: Credit 300
        $n2 = $this->creditNoteService->createDraft($invoice, [
            'credit_note_date' => '2026-08-15',
            'direction' => ShopCreditNoteDirection::Credit,
            'items' => [['shop_invoice_item_id' => $itemB->id, 'line_credit_amount' => 300.00, 'adjusted_quantity' => 0]],
        ], $this->admin->id);
        $this->creditNoteService->approve($n2, $this->admin);

        // Note 3: Debit 150
        $n3 = $this->creditNoteService->createDraft($invoice, [
            'credit_note_date' => '2026-08-15',
            'direction' => ShopCreditNoteDirection::Debit,
            'items' => [['shop_invoice_item_id' => $itemA->id, 'line_credit_amount' => 150.00, 'adjusted_quantity' => 0, 'direction' => ShopCreditNoteDirection::Debit]],
        ], $this->admin->id);
        $this->creditNoteService->approve($n3, $this->admin);

        $fresh = $invoice->fresh();
        $summary = $fresh->financialReconciliationSummary();

        // 10,000 - 500 - 300 + 150 = 9,350
        $this->assertSame(10000.00, $summary['original_invoice_total']);
        $this->assertSame(800.00, $summary['approved_credit_total']);
        $this->assertSame(150.00, $summary['approved_debit_total']);
        $this->assertSame(9350.00, $summary['net_payable']);
        $this->assertSame(9350.00, $summary['remaining_balance']);
        $this->assertSame(0.00, $summary['overpayment_credit']);

        // Check ledger transactions: exactly 3 credit note transactions + 1 invoice transaction
        $cnTxCount = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', ShopCreditNote::class)
            ->where('status', 'approved')
            ->count();
        $this->assertSame(3, $cnTxCount);
    }

    public function test_4_partially_and_fully_paid_invoices_reconcile_with_explicit_overpayment(): void
    {
        // Scenario A: Partially paid (4,000 paid on 10,000 bill)
        $invPart = $this->createSampleInvoice('2026-08-16', 4000.00);
        $item = $invPart->items->first();

        $cn1 = $this->creditNoteService->createDraft($invPart, [
            'direction' => ShopCreditNoteDirection::Credit,
            'items' => [['shop_invoice_item_id' => $item->id, 'line_credit_amount' => 500.00, 'adjusted_quantity' => 0]],
        ], $this->admin->id);
        $this->creditNoteService->approve($cn1, $this->admin);

        $sumPart = $invPart->fresh()->financialReconciliationSummary();
        $this->assertSame(10000.00, $sumPart['original_invoice_total']);
        $this->assertSame(9500.00, $sumPart['net_payable']);
        $this->assertSame(4000.00, $sumPart['amount_paid']);
        $this->assertSame(5500.00, $sumPart['remaining_balance']);
        $this->assertSame(0.00, $sumPart['overpayment_credit']);

        // Scenario B: Fully paid (10,000 paid)
        $invFull = $this->createSampleInvoice('2026-08-17', 10000.00);
        $itemFull = $invFull->items->first();

        $cn2 = $this->creditNoteService->createDraft($invFull, [
            'direction' => ShopCreditNoteDirection::Credit,
            'items' => [['shop_invoice_item_id' => $itemFull->id, 'line_credit_amount' => 500.00, 'adjusted_quantity' => 0]],
        ], $this->admin->id);
        $this->creditNoteService->approve($cn2, $this->admin);

        $sumFull = $invFull->fresh()->financialReconciliationSummary();
        $this->assertSame(10000.00, $sumFull['original_invoice_total']);
        $this->assertSame(9500.00, $sumFull['net_payable']);
        $this->assertSame(10000.00, $sumFull['amount_paid']);
        $this->assertSame(0.00, $sumFull['remaining_balance']);
        $this->assertSame(500.00, $sumFull['overpayment_credit']);
        $this->assertTrue($invFull->fresh()->isOverpaid());
    }

    public function test_5_credit_note_approved_months_later_does_not_alter_historical_daily_delivery_bills(): void
    {
        // Invoice was finalized on 2026-07-01
        $historicalDate = '2026-07-01';
        $invoice = $this->createSampleInvoice($historicalDate, 0.0);

        // Before any credit note, check historical daily delivery bill on 2026-07-01
        $initialHistoricalBill = $this->ownedShopAccountingService->approvedDeliveryBillTotalForDate(
            $this->shop,
            Carbon::parse($historicalDate)
        );
        $this->assertSame(10000.00, $initialHistoricalBill);

        // Credit note is created and approved 3 months later on 2026-10-01
        $creditNoteDate = '2026-10-01';
        $item = $invoice->items->first();

        $draft = $this->creditNoteService->createDraft($invoice, [
            'credit_note_date' => $creditNoteDate,
            'direction' => ShopCreditNoteDirection::Credit,
            'items' => [['shop_invoice_item_id' => $item->id, 'line_credit_amount' => 600.00, 'adjusted_quantity' => 0]],
        ], $this->admin->id);

        $this->creditNoteService->approve($draft, $this->admin);

        // 1. Verify original historical invoice record is COMPLETELY unchanged
        $freshInvoice = $invoice->fresh();
        $this->assertSame(10000.00, (float) $freshInvoice->final_total);
        $this->assertSame(10000.00, (float) $freshInvoice->subtotal);
        $this->assertSame($historicalDate, $freshInvoice->business_date?->toDateString());

        // 2. Verify historical daily delivery bill on 2026-07-01 remains 10,000 (NOT reduced to 9,400)
        $afterHistoricalBill = $this->ownedShopAccountingService->approvedDeliveryBillTotalForDate(
            $this->shop,
            Carbon::parse($historicalDate)
        );
        $this->assertSame(10000.00, $afterHistoricalBill);

        // 3. Verify the credit note ledger entry is posted on the credit note date (2026-10-01), NOT the invoice date
        $cnTx = ShopLedgerTransaction::query()
            ->where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $draft->id)
            ->first();
        $this->assertNotNull($cnTx);
        $this->assertSame($creditNoteDate, $cnTx->business_date?->toDateString());
        $this->assertSame(600.00, (float) $cnTx->amount);
        $this->assertSame('income', $cnTx->direction);

        // 4. Verify the 2026-07-01 snapshot net P/L remains -10,000.00
        $snapshotJuly = ShopDailyLedgerSnapshot::query()
            ->where('shop_id', $this->shop->id)
            ->where('business_date', $historicalDate)
            ->first();
        $this->assertSame(-10000.00, (float) $snapshotJuly->net_pl);

        // 5. Verify the 2026-10-01 snapshot has net P/L of +600.00 (from the credit note on its actual effective date)
        $snapshotOct = ShopDailyLedgerSnapshot::query()
            ->where('shop_id', $this->shop->id)
            ->where('business_date', $creditNoteDate)
            ->first();
        $this->assertSame(600.00, (float) $snapshotOct->net_pl);

        // 6. Effective invoice total correctly reflects 9,400 for settlement
        $this->assertSame(9400.00, $freshInvoice->effectiveFinalTotal());
        $this->assertSame(9400.00, $freshInvoice->remainingBalance());
    }

    public function test_6_cashbook_projection_is_fully_idempotent(): void
    {
        $invoice = $this->createSampleInvoice('2026-08-15', 0.0);
        $item = $invoice->items->first();

        $draft = $this->creditNoteService->createDraft($invoice, [
            'direction' => ShopCreditNoteDirection::Credit,
            'items' => [['shop_invoice_item_id' => $item->id, 'line_credit_amount' => 500.00, 'adjusted_quantity' => 0]],
        ], $this->admin->id);

        $approved = $this->creditNoteService->approve($draft, $this->admin);

        // Repeated syncs
        for ($i = 0; $i < 5; $i++) {
            $this->projectionService->syncCreditNote($approved, $this->admin->id);
        }

        $txCount = ShopLedgerTransaction::query()
            ->where('reference_type', ShopCreditNote::class)
            ->where('reference_id', $approved->id)
            ->count();

        $this->assertSame(1, $txCount);
    }

    public function test_7_cannot_create_or_approve_credit_note_in_closed_accounting_period(): void
    {
        $invoice = $this->createSampleInvoice('2026-06-15', 0.0);
        $item = $invoice->items->first();

        // Close June 2026 accounting period
        ShopAccountingPeriodClosure::query()->create([
            'shop_id' => $this->shop->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'closed_by' => $this->admin->id,
            'closed_at' => now(),
        ]);

        // Attempting to backdate draft into closed June period must throw ValidationException
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot create credit note in a closed accounting period.');

        $this->creditNoteService->createDraft($invoice, [
            'credit_note_date' => '2026-06-20',
            'items' => [['shop_invoice_item_id' => $item->id, 'line_credit_amount' => 500.00, 'adjusted_quantity' => 0]],
        ], $this->admin->id);
    }
}
