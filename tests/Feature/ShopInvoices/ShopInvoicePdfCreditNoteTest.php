<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\ShopInvoice\ShopCreditNoteAccountingMode;
use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCreditApplication;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\ShopInvoices\ShopFinalBillService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShopInvoicePdfCreditNoteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private Product $product;

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

        $this->shop = Shop::query()->create([
            'name' => 'Cardamom Fresh Mart',
            'code' => 'CAR_FD',
            'warehouse_tag' => 'CFM',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->product = Product::factory()->create([
            'name' => 'Carrot Ooty Premium',
            'sku' => 'CAR-OOT-01',
            'unit' => 'kg',
        ]);
    }

    private function createSampleInvoice(
        string $invoiceNumber = 'SINV-20261123-CAR_FD',
        float $subtotal = 8573.70,
        float $discount = 0.00,
        float $paidAmount = 0.00,
    ): ShopInvoice {
        $finalTotal = round($subtotal - $discount, 2);
        $balance = round(max(0.00, $finalTotal - $paidAmount), 2);

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => 'ORD-20261123-CFM01',
            'business_date' => '2026-11-23',
            'delivery_status' => 'delivered',
        ]);

        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 100,
            'approved_qty' => 100,
            'loaded_qty' => 100,
            'delivered_qty' => 100,
            'unit' => 'kg',
            'unit_price' => 85.737,
            'line_total' => $subtotal,
        ]);

        $invoice = ShopInvoice::query()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'business_date' => '2026-11-23',
            'status' => 'finalized',
            'delivery_status' => 'received_full',
            'payment_status' => $paidAmount >= $finalTotal ? 'paid' : ($paidAmount > 0 ? 'partially_paid' : 'unpaid'),
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'final_total' => $finalTotal,
            'paid_amount' => $paidAmount,
            'balance_amount' => $balance,
            'finalized_at' => Carbon::parse('2026-11-23 18:00:00'),
            'finalized_by' => $this->admin->id,
        ]);

        ShopInvoiceItem::query()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'product_name' => 'Carrot Ooty Premium',
            'product_grade' => 'A',
            'unit' => 'kg',
            'delivered_qty' => 100,
            'approved_qty' => 100,
            'unit_price' => 85.737,
            'line_subtotal' => $subtotal,
            'final_line_total' => $subtotal,
        ]);

        return $invoice;
    }

    public function test_pdf_download_for_invoice_without_credit_notes_hides_credit_notes_section_and_matches_detail_page(): void
    {
        $invoice = $this->createSampleInvoice();

        $response = $this->actingAs($this->admin)
            ->get(route('purchasing.shop-invoices.pdf', ['invoice' => $invoice, 'download' => 1]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $finalBill = app(ShopFinalBillService::class)->calculate($invoice);

        $html = view('purchasing.shop-invoices.dompdf', [
            'invoice' => $invoice,
            'items' => $invoice->items,
            'subtotal' => round((float) $invoice->subtotal, 2),
            'discountTotal' => round((float) $invoice->discount_total, 2),
            'finalTotal' => round((float) $invoice->final_total, 2),
            'finalBill' => $finalBill,
        ])->render();

        $this->assertStringNotContainsString('Applied Credit &amp; Debit Notes', $html);
        $this->assertStringNotContainsString('Credit Note Deductions', $html);
        $this->assertStringNotContainsString('Debit Note Additions', $html);
        $this->assertStringNotContainsString('Original Invoice Amount', $html);

        $this->assertStringContainsString('Subtotal', $html);
        $this->assertStringContainsString('Rs. 8,573.70', $html);
        $this->assertStringContainsString('Net Payable', $html);
        $this->assertStringContainsString('Paid Amount', $html);
        $this->assertStringContainsString('Outstanding Balance', $html);

        $this->assertSame(8573.70, $finalBill->originalInvoiceTotal);
        $this->assertSame(0.00, $finalBill->finalizedCreditTotal);
        $this->assertSame(0.00, $finalBill->finalizedDebitTotal);
        $this->assertSame(8573.70, $finalBill->finalNetPayable);
        $this->assertSame(0.00, $finalBill->amountPaid);
        $this->assertSame(8573.70, $finalBill->remainingBalance);
        $this->assertFalse($finalBill->hasAdjustments);
    }

    public function test_pdf_download_for_invoice_with_single_credit_note_includes_credit_note_details_and_updated_totals(): void
    {
        $invoice = $this->createSampleInvoice('SINV-20261123-CAR_FD', 8573.70);

        $creditNote = ShopCreditNote::query()->create([
            'credit_note_number' => 'SCN-20261124-CAR_FD-01',
            'shop_id' => $this->shop->id,
            'shop_invoice_id' => null,
            'credit_note_date' => '2026-11-24',
            'direction' => ShopCreditNoteDirection::Credit,
            'accounting_mode' => ShopCreditNoteAccountingMode::ShopLevel,
            'reason_type' => ShopCreditNoteReason::PriceCorrection,
            'status' => ShopCreditNoteStatus::Approved,
            'subtotal' => 200.00,
            'total_credit_amount' => 200.00,
            'notes' => 'Finalized from DRAFT-SCN-20261124-CAR_FD-01',
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'approved_at' => Carbon::parse('2026-11-24 10:00:00'),
        ]);

        ShopCreditApplication::query()->create([
            'shop_credit_note_id' => $creditNote->id,
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $this->shop->id,
            'amount' => 200.00,
            'applied_at' => Carbon::parse('2026-11-24 10:30:00'),
            'applied_by' => $this->admin->id,
            'notes' => 'Price correction adjustment',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('purchasing.shop-invoices.pdf', ['invoice' => $invoice, 'download' => 1]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $finalBill = app(ShopFinalBillService::class)->calculate($invoice);

        $html = view('purchasing.shop-invoices.dompdf', [
            'invoice' => $invoice,
            'items' => $invoice->items,
            'subtotal' => round((float) $invoice->subtotal, 2),
            'discountTotal' => round((float) $invoice->discount_total, 2),
            'finalTotal' => round((float) $invoice->final_total, 2),
            'finalBill' => $finalBill,
        ])->render();

        $this->assertStringContainsString('Applied Credit &amp; Debit Notes', $html);
        $this->assertStringContainsString('SCN-20261124-CAR_FD-01', $html);
        $this->assertStringContainsString('24 Nov 2026', $html);
        $this->assertStringContainsString('Credit Note', $html);
        $this->assertStringContainsString('Price Correction', $html);
        $this->assertStringContainsString('Price correction adjustment', $html);
        $this->assertStringContainsString('- Rs. 200.00', $html);

        $this->assertStringContainsString('Original Invoice Amount', $html);
        $this->assertStringContainsString('Rs. 8,573.70', $html);
        $this->assertStringContainsString('Credit Note Deductions', $html);
        $this->assertStringContainsString('- Rs. 200.00', $html);
        $this->assertStringContainsString('Net Payable', $html);
        $this->assertStringContainsString('Rs. 8,373.70', $html);
        $this->assertStringContainsString('Outstanding Balance', $html);

        $this->assertSame(8573.70, $finalBill->originalInvoiceTotal);
        $this->assertSame(200.00, $finalBill->finalizedCreditTotal);
        $this->assertSame(0.00, $finalBill->finalizedDebitTotal);
        $this->assertSame(8373.70, $finalBill->finalNetPayable);
        $this->assertSame(0.00, $finalBill->amountPaid);
        $this->assertSame(8373.70, $finalBill->remainingBalance);
        $this->assertTrue($finalBill->hasAdjustments);
    }

    public function test_pdf_download_for_invoice_with_multiple_credit_and_debit_notes_shows_all_and_prevents_duplicate_deductions(): void
    {
        $invoice = $this->createSampleInvoice('SINV-20261123-MULTI', 10000.00);

        // Credit Note 1: 500.00
        $cn1 = ShopCreditNote::query()->create([
            'credit_note_number' => 'SCN-20261124-MULTI-01',
            'shop_id' => $this->shop->id,
            'credit_note_date' => '2026-11-24',
            'direction' => ShopCreditNoteDirection::Credit,
            'accounting_mode' => ShopCreditNoteAccountingMode::ShopLevel,
            'reason_type' => ShopCreditNoteReason::Shortage,
            'status' => ShopCreditNoteStatus::Approved,
            'subtotal' => 500.00,
            'total_credit_amount' => 500.00,
            'notes' => 'Shortage during unloading',
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
        ]);
        ShopCreditApplication::query()->create([
            'shop_credit_note_id' => $cn1->id,
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $this->shop->id,
            'amount' => 500.00,
            'applied_at' => Carbon::parse('2026-11-24 11:00:00'),
            'applied_by' => $this->admin->id,
            'notes' => '5kg missing',
        ]);

        // Credit Note 2: 300.00
        $cn2 = ShopCreditNote::query()->create([
            'credit_note_number' => 'SCN-20261124-MULTI-02',
            'shop_id' => $this->shop->id,
            'credit_note_date' => '2026-11-24',
            'direction' => ShopCreditNoteDirection::Credit,
            'accounting_mode' => ShopCreditNoteAccountingMode::ShopLevel,
            'reason_type' => ShopCreditNoteReason::DamageWastage,
            'status' => ShopCreditNoteStatus::Approved,
            'subtotal' => 300.00,
            'total_credit_amount' => 300.00,
            'notes' => 'Damaged in transit',
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
        ]);
        ShopCreditApplication::query()->create([
            'shop_credit_note_id' => $cn2->id,
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $this->shop->id,
            'amount' => 300.00,
            'applied_at' => Carbon::parse('2026-11-24 12:00:00'),
            'applied_by' => $this->admin->id,
            'notes' => 'Crushed boxes',
        ]);

        // Debit Note 3: 150.00
        $dn1 = ShopCreditNote::query()->create([
            'credit_note_number' => 'SDN-20261124-MULTI-01',
            'shop_id' => $this->shop->id,
            'credit_note_date' => '2026-11-24',
            'direction' => ShopCreditNoteDirection::Debit,
            'accounting_mode' => ShopCreditNoteAccountingMode::ShopLevel,
            'reason_type' => ShopCreditNoteReason::ExcessDelivered,
            'status' => ShopCreditNoteStatus::Approved,
            'subtotal' => 150.00,
            'total_credit_amount' => 150.00,
            'notes' => 'Extra 2kg accepted by shop',
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
        ]);
        ShopCreditApplication::query()->create([
            'shop_credit_note_id' => $dn1->id,
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $this->shop->id,
            'amount' => 150.00,
            'applied_at' => Carbon::parse('2026-11-24 13:00:00'),
            'applied_by' => $this->admin->id,
            'notes' => 'Additional charge for excess',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('purchasing.shop-invoices.pdf', ['invoice' => $invoice, 'download' => 1]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $finalBill = app(ShopFinalBillService::class)->calculate($invoice);

        $html = view('purchasing.shop-invoices.dompdf', [
            'invoice' => $invoice,
            'items' => $invoice->items,
            'subtotal' => round((float) $invoice->subtotal, 2),
            'discountTotal' => round((float) $invoice->discount_total, 2),
            'finalTotal' => round((float) $invoice->final_total, 2),
            'finalBill' => $finalBill,
        ])->render();

        $this->assertStringContainsString('SCN-20261124-MULTI-01', $html);
        $this->assertStringContainsString('SCN-20261124-MULTI-02', $html);
        $this->assertStringContainsString('SDN-20261124-MULTI-01', $html);
        $this->assertStringContainsString('Debit Note', $html);
        $this->assertStringContainsString('Credit Note', $html);

        // 10,000 - 800 + 150 = 9,350
        $this->assertStringContainsString('Original Invoice Amount', $html);
        $this->assertStringContainsString('Rs. 10,000.00', $html);
        $this->assertStringContainsString('Credit Note Deductions', $html);
        $this->assertStringContainsString('- Rs. 800.00', $html);
        $this->assertStringContainsString('Debit Note Additions', $html);
        $this->assertStringContainsString('+ Rs. 150.00', $html);
        $this->assertStringContainsString('Net Payable', $html);
        $this->assertStringContainsString('Rs. 9,350.00', $html);
        $this->assertStringContainsString('Outstanding Balance', $html);

        $this->assertSame(10000.00, $finalBill->originalInvoiceTotal);
        $this->assertSame(800.00, $finalBill->finalizedCreditTotal);
        $this->assertSame(150.00, $finalBill->finalizedDebitTotal);
        $this->assertSame(9350.00, $finalBill->finalNetPayable);
        $this->assertSame(9350.00, $finalBill->remainingBalance);
    }

    public function test_newly_applied_credit_notes_appear_when_pdf_is_downloaded_again(): void
    {
        $invoice = $this->createSampleInvoice('SINV-20261123-REDOWNLOAD', 5000.00);

        // First download: no credit notes
        $firstResponse = $this->actingAs($this->admin)
            ->get(route('purchasing.shop-invoices.pdf', ['invoice' => $invoice, 'download' => 1]));
        $firstResponse->assertOk();

        $firstBill = app(ShopFinalBillService::class)->calculate($invoice);
        $this->assertFalse($firstBill->hasAdjustments);
        $this->assertSame(5000.00, $firstBill->finalNetPayable);

        // Apply a new credit note
        $cn = ShopCreditNote::query()->create([
            'credit_note_number' => 'SCN-20261124-NEW-01',
            'shop_id' => $this->shop->id,
            'credit_note_date' => '2026-11-24',
            'direction' => ShopCreditNoteDirection::Credit,
            'accounting_mode' => ShopCreditNoteAccountingMode::ShopLevel,
            'reason_type' => ShopCreditNoteReason::CommercialDiscount,
            'status' => ShopCreditNoteStatus::Approved,
            'subtotal' => 250.00,
            'total_credit_amount' => 250.00,
            'notes' => 'Commercial rebate',
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
        ]);
        ShopCreditApplication::query()->create([
            'shop_credit_note_id' => $cn->id,
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $this->shop->id,
            'amount' => 250.00,
            'applied_at' => Carbon::parse('2026-11-24 15:00:00'),
            'applied_by' => $this->admin->id,
            'notes' => 'Applied rebate',
        ]);

        // Second download: should reflect newly applied credit note
        $secondResponse = $this->actingAs($this->admin)
            ->get(route('purchasing.shop-invoices.pdf', ['invoice' => $invoice, 'download' => 1]));
        $secondResponse->assertOk();

        $secondBill = app(ShopFinalBillService::class)->calculate($invoice->fresh());
        $this->assertTrue($secondBill->hasAdjustments);
        $this->assertSame(250.00, $secondBill->finalizedCreditTotal);
        $this->assertSame(4750.00, $secondBill->finalNetPayable);

        $html = view('purchasing.shop-invoices.dompdf', [
            'invoice' => $invoice->fresh(),
            'items' => $invoice->items,
            'subtotal' => round((float) $invoice->subtotal, 2),
            'discountTotal' => round((float) $invoice->discount_total, 2),
            'finalTotal' => round((float) $invoice->final_total, 2),
            'finalBill' => $secondBill,
        ])->render();

        $this->assertStringContainsString('SCN-20261124-NEW-01', $html);
        $this->assertStringContainsString('- Rs. 250.00', $html);
        $this->assertStringContainsString('Rs. 4,750.00', $html);
    }

    public function test_reversed_credit_applications_are_excluded_from_pdf(): void
    {
        $invoice = $this->createSampleInvoice('SINV-20261123-REVERSED', 6000.00);

        $cn = ShopCreditNote::query()->create([
            'credit_note_number' => 'SCN-20261124-REV-01',
            'shop_id' => $this->shop->id,
            'credit_note_date' => '2026-11-24',
            'direction' => ShopCreditNoteDirection::Credit,
            'accounting_mode' => ShopCreditNoteAccountingMode::ShopLevel,
            'reason_type' => ShopCreditNoteReason::PriceCorrection,
            'status' => ShopCreditNoteStatus::Approved,
            'subtotal' => 400.00,
            'total_credit_amount' => 400.00,
            'notes' => 'Wrong credit applied',
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
        ]);

        ShopCreditApplication::query()->create([
            'shop_credit_note_id' => $cn->id,
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $this->shop->id,
            'amount' => 400.00,
            'applied_at' => Carbon::parse('2026-11-24 10:00:00'),
            'applied_by' => $this->admin->id,
            'notes' => 'Applied in error',
            'reversed_at' => Carbon::parse('2026-11-24 11:00:00'),
            'reversed_by' => $this->admin->id,
            'reversal_reason' => 'Customer clarified billing was correct',
        ]);

        $finalBill = app(ShopFinalBillService::class)->calculate($invoice->fresh());
        $this->assertFalse($finalBill->hasAdjustments);
        $this->assertSame(6000.00, $finalBill->finalNetPayable);

        $html = view('purchasing.shop-invoices.dompdf', [
            'invoice' => $invoice->fresh(),
            'items' => $invoice->items,
            'subtotal' => round((float) $invoice->subtotal, 2),
            'discountTotal' => round((float) $invoice->discount_total, 2),
            'finalTotal' => round((float) $invoice->final_total, 2),
            'finalBill' => $finalBill,
        ])->render();

        $this->assertStringNotContainsString('SCN-20261124-REV-01', $html);
        $this->assertStringNotContainsString('Credit Note Deductions', $html);
        $this->assertStringContainsString('Rs. 6,000.00', $html);
    }

    public function test_partially_paid_invoice_with_credit_notes_correctly_reflects_paid_amount_and_remaining_balance(): void
    {
        $invoice = $this->createSampleInvoice('SINV-20261123-PAID', 10000.00, 0.00, 3000.00);

        $cn = ShopCreditNote::query()->create([
            'credit_note_number' => 'SCN-20261124-PARTPAID-01',
            'shop_id' => $this->shop->id,
            'credit_note_date' => '2026-11-24',
            'direction' => ShopCreditNoteDirection::Credit,
            'accounting_mode' => ShopCreditNoteAccountingMode::ShopLevel,
            'reason_type' => ShopCreditNoteReason::PriceCorrection,
            'status' => ShopCreditNoteStatus::Approved,
            'subtotal' => 500.00,
            'total_credit_amount' => 500.00,
            'notes' => 'Rate adjustment',
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
        ]);

        ShopCreditApplication::query()->create([
            'shop_credit_note_id' => $cn->id,
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $this->shop->id,
            'amount' => 500.00,
            'applied_at' => Carbon::parse('2026-11-24 14:00:00'),
            'applied_by' => $this->admin->id,
            'notes' => 'Adjusted',
        ]);

        // Net Payable: 10,000 - 500 = 9,500. Paid: 3,000. Remaining: 6,500.
        $finalBill = app(ShopFinalBillService::class)->calculate($invoice->fresh());
        $this->assertSame(10000.00, $finalBill->originalInvoiceTotal);
        $this->assertSame(500.00, $finalBill->finalizedCreditTotal);
        $this->assertSame(9500.00, $finalBill->finalNetPayable);
        $this->assertSame(3000.00, $finalBill->amountPaid);
        $this->assertSame(6500.00, $finalBill->remainingBalance);

        $html = view('purchasing.shop-invoices.dompdf', [
            'invoice' => $invoice->fresh(),
            'items' => $invoice->items,
            'subtotal' => round((float) $invoice->subtotal, 2),
            'discountTotal' => round((float) $invoice->discount_total, 2),
            'finalTotal' => round((float) $invoice->final_total, 2),
            'finalBill' => $finalBill,
        ])->render();

        $this->assertStringContainsString('Paid Amount', $html);
        $this->assertStringContainsString('Rs. 3,000.00', $html);
        $this->assertStringContainsString('Outstanding Balance', $html);
        $this->assertStringContainsString('Rs. 6,500.00', $html);
        $this->assertStringContainsString('Rs. 9,500.00', $html);
    }
}
