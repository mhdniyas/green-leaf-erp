<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\Cashbook\LedgerDirection;
use App\Enums\ShopInvoice\ShopCreditNoteDirection;
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
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\InvoiceCashbookProjectionService;
use App\Services\ShopInvoices\ShopCreditNoteService;
use App\Services\ShopInvoices\ShopFinalBillService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Characterization tests capturing baseline legacy behavior of single-invoice
 * Shop Credit Notes before Phase 2.1 schema modifications.
 */
class LegacyShopCreditNoteCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private ShopOrder $order;

    private ShopInvoice $invoice;

    private ShopInvoiceItem $invoiceItem;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->shop = Shop::factory()->create([
            'code' => 'KTYM01',
            'name' => 'Kottayam Central Shop',
            'status' => 'active',
            'accounting_enabled' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->product = Product::factory()->create([
            'name' => 'Onion Grade A',
            'sku' => 'ONI-GRA-001',
            'unit' => 'kg',
        ]);

        $this->order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-20261105-001',
            'business_date' => '2026-11-05',
        ]);

        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 100,
            'approved_qty' => 100,
            'loaded_qty' => 100,
            'delivered_qty' => 100,
            'unit' => 'kg',
            'unit_cost' => 45.00,
        ]);

        $this->invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $this->order->id,
            'invoice_number' => 'SINV-20261105-KTYM01',
            'business_date' => '2026-11-05',
            'status' => 'finalized',
            'delivery_status' => 'received_full',
            'payment_status' => 'unpaid',
            'subtotal' => 5500.00,
            'shortage_total' => 0.00,
            'discount_total' => 0.00,
            'final_total' => 5500.00,
            'paid_amount' => 0.00,
            'balance_amount' => 5500.00,
            'finalized_by' => $this->admin->id,
            'finalized_at' => now(),
        ]);

        $this->invoiceItem = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $this->invoice->id,
            'shop_order_item_id' => $orderItem->id,
            'product_id' => $this->product->id,
            'product_name' => 'Onion Grade A',
            'product_grade' => 'A',
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 100.00,
            'price_quantity' => 100.0000,
            'delivered_qty' => 100.00,
            'delivered_price_quantity' => 100.0000,
            'unit_price' => 55.00,
            'line_subtotal' => 5500.00,
            'final_line_total' => 5500.00,
        ]);
    }

    /**
     * Characterization: Existing single-invoice Credit Note links to ShopInvoice and preserves immutable invoice totals.
     */
    public function test_existing_finalized_single_invoice_credit_note_preserves_immutable_invoice(): void
    {
        $creditNoteService = app(ShopCreditNoteService::class);

        $draft = $creditNoteService->createDraft(
            $this->invoice,
            [
                'credit_note_date' => '2026-11-06',
                'reason_type' => ShopCreditNoteReason::PriceCorrection,
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->invoiceItem->id,
                        'revised_quantity' => 100,
                        'revised_unit_price' => 45, // ₹10/kg credit = ₹1,000 credit
                        'inventory_action' => ShopCreditNoteInventoryAction::None,
                    ],
                ],
                'notes' => 'Legacy price adjustment characterization test',
            ],
            $this->admin->id
        );

        $this->assertSame(ShopCreditNoteStatus::Draft, $draft->status);
        $this->assertEquals(1000.00, (float) $draft->total_credit_amount);
        $this->assertSame($this->invoice->id, $draft->shop_invoice_id);
        $this->assertSame($this->shop->id, $draft->shop_id);

        $approved = $creditNoteService->approve($draft, $this->admin);

        $this->assertSame(ShopCreditNoteStatus::Approved, $approved->status);
        $this->assertNotNull($approved->approved_at);
        $this->assertSame($this->admin->id, $approved->approved_by);

        // Verification 1: Relationships
        $this->assertTrue($approved->invoice->is($this->invoice));
        $this->assertCount(1, $approved->items);
        $this->assertTrue($approved->items->first()->invoiceItem->is($this->invoiceItem));
        $this->assertTrue($approved->items->first()->product->is($this->product));
        $this->assertCount(1, $this->invoice->fresh()->creditNotes);

        // Verification 2: Original invoice in DB remains completely unchanged
        $freshInvoice = $this->invoice->fresh();
        $this->assertEquals(5500.00, (float) $freshInvoice->final_total);
        $this->assertEquals(0.00, (float) $freshInvoice->paid_amount);
        $this->assertEquals(5500.00, (float) $freshInvoice->balance_amount);
        $freshInvoiceItem = $this->invoiceItem->fresh();
        $this->assertEquals(100.00, (float) $freshInvoiceItem->delivered_qty);
        $this->assertEquals(55.00, (float) $freshInvoiceItem->unit_price);
        $this->assertEquals(5500.00, (float) $freshInvoiceItem->final_line_total);
    }

    /**
     * Characterization: Final Bill calculation deducts approved credit notes without mutating invoice.
     */
    public function test_final_bill_calculation_deducts_credit_notes_correctly(): void
    {
        $creditNoteService = app(ShopCreditNoteService::class);
        $finalBillService = app(ShopFinalBillService::class);

        // Create and approve a ₹1,500 credit note (e.g. 30 kg shortage @ ₹50)
        $draft = $creditNoteService->createDraft(
            $this->invoice,
            [
                'credit_note_date' => '2026-11-06',
                'reason_type' => ShopCreditNoteReason::Shortage,
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->invoiceItem->id,
                        'revised_quantity' => 70, // 30 kg reduction @ ₹55 = ₹1,650
                        'revised_unit_price' => 55,
                        'inventory_action' => ShopCreditNoteInventoryAction::None,
                    ],
                ],
            ],
            $this->admin->id
        );
        $creditNoteService->approve($draft, $this->admin);

        $finalBill = $finalBillService->calculate($this->invoice->fresh());

        $this->assertEquals(5500.00, $finalBill->originalInvoiceTotal);
        $this->assertEquals(1650.00, $finalBill->finalizedCreditTotal);
        $this->assertEquals(0.00, $finalBill->finalizedDebitTotal);
        $this->assertEquals(-1650.00, $finalBill->netAdjustment);
        $this->assertEquals(3850.00, $finalBill->finalNetPayable);
        $this->assertEquals(0.00, $finalBill->amountPaid);
        $this->assertEquals(3850.00, $finalBill->remainingBalance);
        $this->assertEquals(0.00, $finalBill->overpaymentCredit);
        $this->assertTrue($finalBill->hasAdjustments);
        $this->assertSame('unpaid', $finalBill->effectivePaymentStatus);

        // Model helper methods match FinalBill exactly
        $freshInvoice = $this->invoice->fresh();
        $this->assertEquals(1650.00, $freshInvoice->approvedCreditTotal());
        $this->assertEquals(3850.00, $freshInvoice->effectiveFinalTotal());
        $this->assertEquals(3850.00, $freshInvoice->effectiveBalanceAmount());
        $this->assertEquals(0.00, $freshInvoice->overpaidAmount());
    }

    /**
     * Characterization: Cashbook ledger transaction projection syncs upon approval.
     */
    public function test_cashbook_projection_creates_shop_ledger_transaction(): void
    {
        $creditNote = ShopCreditNote::query()->create([
            'credit_note_number' => 'SCN-20261105-KTYM01-01',
            'shop_id' => $this->shop->id,
            'shop_invoice_id' => $this->invoice->id,
            'credit_note_date' => '2026-11-06',
            'direction' => ShopCreditNoteDirection::Credit,
            'reason_type' => ShopCreditNoteReason::PriceCorrection,
            'status' => ShopCreditNoteStatus::Approved,
            'inventory_action' => ShopCreditNoteInventoryAction::None,
            'subtotal' => 1000.00,
            'total_credit_amount' => 1000.00,
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        $cashbookService = app(InvoiceCashbookProjectionService::class);
        $transaction = $cashbookService->syncCreditNote($creditNote, $this->admin->id);

        $this->assertInstanceOf(ShopLedgerTransaction::class, $transaction);
        $this->assertSame($this->shop->id, $transaction->shop_id);
        $this->assertSame(ShopCreditNote::class, $transaction->reference_type);
        $this->assertSame($creditNote->id, $transaction->reference_id);
        $this->assertEquals(1000.00, (float) $transaction->amount);
        $this->assertSame(LedgerDirection::Income->value, $transaction->direction);
        $this->assertFalse($transaction->affects_expense); // credit note reverses expense -> Income
    }

    /**
     * Characterization: Draft credit notes do not affect Final Bill or Cashbook until approved.
     */
    public function test_draft_credit_notes_do_not_affect_final_bill_or_cashbook(): void
    {
        $creditNoteService = app(ShopCreditNoteService::class);
        $finalBillService = app(ShopFinalBillService::class);

        $draft = $creditNoteService->createDraft(
            $this->invoice,
            [
                'credit_note_date' => '2026-11-06',
                'reason_type' => ShopCreditNoteReason::PriceCorrection,
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->invoiceItem->id,
                        'revised_quantity' => 100,
                        'revised_unit_price' => 50, // ₹500 credit in draft
                        'inventory_action' => ShopCreditNoteInventoryAction::None,
                    ],
                ],
            ],
            $this->admin->id
        );

        $this->assertTrue($draft->isDraft());

        // Draft must NOT reduce final bill
        $finalBill = $finalBillService->calculate($this->invoice->fresh());
        $this->assertEquals(0.00, $finalBill->finalizedCreditTotal);
        $this->assertEquals(5500.00, $finalBill->finalNetPayable);
        $this->assertEquals(5500.00, $finalBill->remainingBalance);
        $this->assertFalse($finalBill->hasAdjustments);

        // Draft must NOT project to cashbook transactions
        $this->assertNull(ShopLedgerTransaction::where('reference_id', $draft->id)->first());

        // Upon approval, both update
        $approved = $creditNoteService->approve($draft, $this->admin);
        $this->assertTrue($approved->isApproved());

        $finalBillAfter = $finalBillService->calculate($this->invoice->fresh());
        $this->assertEquals(500.00, $finalBillAfter->finalizedCreditTotal);
        $this->assertEquals(5000.00, $finalBillAfter->finalNetPayable);
        $this->assertEquals(5000.00, $finalBillAfter->remainingBalance);
        $this->assertTrue($finalBillAfter->hasAdjustments);

        $this->assertNotNull(ShopLedgerTransaction::where('reference_id', $approved->id)->first());
    }

    /**
     * Characterization: Overpayment credit when approved adjustments exceed outstanding balance.
     */
    public function test_overpayment_credit_calculated_when_paid_exceeds_adjusted_total(): void
    {
        $creditNoteService = app(ShopCreditNoteService::class);
        $finalBillService = app(ShopFinalBillService::class);

        // Record partial payment of ₹5,000 on original ₹5,500 bill
        $this->invoice->update([
            'paid_amount' => 5000.00,
            'balance_amount' => 500.00,
            'payment_status' => 'partially_paid',
        ]);

        // Issue credit note of ₹1,000 (net payable becomes ₹4,500, so paid ₹5,000 creates ₹500 overpayment)
        $draft = $creditNoteService->createDraft(
            $this->invoice,
            [
                'credit_note_date' => '2026-11-06',
                'reason_type' => ShopCreditNoteReason::PriceCorrection,
                'items' => [
                    [
                        'shop_invoice_item_id' => $this->invoiceItem->id,
                        'revised_quantity' => 100,
                        'revised_unit_price' => 45, // ₹1,000 credit
                        'inventory_action' => ShopCreditNoteInventoryAction::None,
                    ],
                ],
            ],
            $this->admin->id
        );
        $creditNoteService->approve($draft, $this->admin);

        $finalBill = $finalBillService->calculate($this->invoice->fresh());

        $this->assertEquals(5500.00, $finalBill->originalInvoiceTotal);
        $this->assertEquals(1000.00, $finalBill->finalizedCreditTotal);
        $this->assertEquals(4500.00, $finalBill->finalNetPayable);
        $this->assertEquals(5000.00, $finalBill->amountPaid);
        $this->assertEquals(0.00, $finalBill->remainingBalance);
        $this->assertEquals(500.00, $finalBill->overpaymentCredit);
        $this->assertSame('overpaid', $finalBill->effectivePaymentStatus);
    }

    /**
     * Characterization: Legacy credit note numbering format SCN-YYYYMMDD-SHOP-XX.
     */
    public function test_legacy_credit_note_number_generation_sequence(): void
    {
        $num1 = ShopCreditNote::generateCreditNoteNumber($this->invoice);
        $this->assertSame('SCN-20261105-KTYM01-01', $num1);

        ShopCreditNote::query()->create([
            'credit_note_number' => $num1,
            'shop_id' => $this->shop->id,
            'shop_invoice_id' => $this->invoice->id,
            'credit_note_date' => '2026-11-05',
            'direction' => ShopCreditNoteDirection::Credit,
            'reason_type' => ShopCreditNoteReason::PriceCorrection,
            'status' => ShopCreditNoteStatus::Draft,
            'subtotal' => 100.00,
            'total_credit_amount' => 100.00,
        ]);

        $num2 = ShopCreditNote::generateCreditNoteNumber($this->invoice);
        $this->assertSame('SCN-20261105-KTYM01-02', $num2);
    }
}
