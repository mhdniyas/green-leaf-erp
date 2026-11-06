<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopInvoicePaymentAllocation;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\InvoiceCashbookProjectionService;
use App\Services\ShopInvoices\ShopCreditNoteService;
use App\Services\ShopInvoices\ShopInvoiceService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShopInvoicePaymentAllocationTest extends TestCase
{
    use RefreshDatabase;

    private ShopInvoiceService $invoiceService;

    private ShopCreditNoteService $creditNoteService;

    private User $admin;

    private Shop $shop;

    private Product $productA;

    private Product $productB;

    private static int $orderCounter = 1;

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

        $this->invoiceService = app(ShopInvoiceService::class);
        $this->creditNoteService = app(ShopCreditNoteService::class);

        $this->shop = Shop::query()->create([
            'name' => 'Payment Test Shop',
            'code' => 'PTS_01',
            'warehouse_tag' => 'PTS',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->productA = Product::factory()->create(['name' => 'Fresh Tomato', 'sku' => 'TOM-001', 'unit' => 'kg']);
        $this->productB = Product::factory()->create(['name' => 'Green Chili', 'sku' => 'CHI-001', 'unit' => 'kg']);
    }

    private function createSampleInvoice(float $finalTotal = 10000.00, float $paidAmount = 0.00): ShopInvoice
    {
        $counter = self::$orderCounter++;
        $businessDate = Carbon::parse('2026-11-05')->addDays($counter)->format('Y-m-d');

        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'order_number' => "ORD-{$businessDate}-{$counter}",
            'business_date' => $businessDate,
            'shop_daily_order_key' => "shop:{$this->shop->id}:{$businessDate}:{$counter}",
        ]);

        $itemCost1 = round($finalTotal * 0.6, 2);
        $itemCost2 = round($finalTotal - $itemCost1, 2);

        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_grade' => 'A',
            'requested_qty' => 100,
            'approved_qty' => 100,
            'loaded_qty' => 100,
            'delivered_qty' => 100,
            'unit' => 'kg',
            'unit_price' => round($itemCost1 / 100, 2),
            'line_total' => $itemCost1,
        ]);

        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->productB->id,
            'product_grade' => 'A',
            'requested_qty' => 50,
            'approved_qty' => 50,
            'loaded_qty' => 50,
            'delivered_qty' => 50,
            'unit' => 'kg',
            'unit_price' => round($itemCost2 / 50, 2),
            'line_total' => $itemCost2,
        ]);

        $balanceAmount = max(0.00, $finalTotal - $paidAmount);

        $invoice = ShopInvoice::query()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => "SINV-{$businessDate}-{$counter}",
            'business_date' => $businessDate,
            'status' => 'finalized',
            'delivery_status' => 'received_full',
            'payment_status' => $balanceAmount <= 0.0001 ? 'paid' : ($paidAmount > 0 ? 'partially_paid' : 'unpaid'),
            'subtotal' => $finalTotal,
            'discount_total' => 0.00,
            'final_total' => $finalTotal,
            'paid_amount' => $paidAmount,
            'balance_amount' => $balanceAmount,
            'finalized_at' => Carbon::parse("{$businessDate} 18:00:00"),
            'finalized_by' => $this->admin->id,
        ]);

        ShopInvoiceItem::query()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $this->productA->id,
            'product_name' => 'Fresh Tomato',
            'product_grade' => 'A',
            'unit' => 'kg',
            'delivered_qty' => 100,
            'approved_qty' => 100,
            'loaded_qty' => 100,
            'unit_price' => round($itemCost1 / 100, 2),
            'line_total' => $itemCost1,
            'final_line_total' => $itemCost1,
        ]);

        ShopInvoiceItem::query()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $this->productB->id,
            'product_name' => 'Green Chili',
            'product_grade' => 'A',
            'unit' => 'kg',
            'delivered_qty' => 50,
            'approved_qty' => 50,
            'loaded_qty' => 50,
            'unit_price' => round($itemCost2 / 50, 2),
            'line_total' => $itemCost2,
            'final_line_total' => $itemCost2,
        ]);

        app(InvoiceCashbookProjectionService::class)->syncInvoice($invoice);

        return $invoice;
    }

    private function createApprovedAdjustment(
        ShopInvoice $invoice,
        ShopCreditNoteDirection $direction,
        float $amount,
        ShopCreditNoteReason $reason = ShopCreditNoteReason::DamageWastage
    ): void {
        $firstItem = $invoice->items->first();

        $draft = $this->creditNoteService->createDraft($invoice, [
            'reason_type' => $reason->value,
            'direction' => $direction->value,
            'inventory_action' => ShopCreditNoteInventoryAction::None->value,
            'customer_note' => 'Adjustment for payment test',
            'items' => [
                [
                    'shop_invoice_item_id' => $firstItem->id,
                    'direction' => $direction->value,
                    'inventory_action' => ShopCreditNoteInventoryAction::None->value,
                    'quantity' => 1.0,
                    'unit_rate' => $amount,
                    'line_credit_amount' => $amount,
                ],
            ],
        ], $this->admin->id);

        $this->creditNoteService->approve($draft, $this->admin->id, 'Approved for payment test');
    }

    public function test_original_10k_credit_2k_payment_8k_fully_settles_invoice(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 2000.00);

        // 1. Verify allocation preview reflects effective total
        $preview = $this->invoiceService->allocationPreviewForShop($this->shop->id, 8000.00);
        $this->assertSame(8000.00, $preview['total_due']);
        $this->assertSame(8000.00, $preview['applied_amount']);
        $this->assertSame(0.00, $preview['credit_amount']);
        $this->assertCount(1, $preview['invoices']);
        $this->assertSame(8000.00, $preview['invoices'][0]['amount']);

        // 2. Submit payment request for 8000
        $paymentRequest = $this->invoiceService->requestPayment($invoice, [
            'amount_mode' => 'custom',
            'amount' => 8000.00,
            'payment_method' => 'cash',
        ], $this->admin->id);

        // 3. Approve payment request
        $approvedRequest = $this->invoiceService->reviewPaymentRequest($paymentRequest, 'approve', $this->admin->id, 'Paid in full');

        $this->assertSame('approved', $approvedRequest->status);
        $this->assertSame(8000.00, (float) $approvedRequest->applied_amount);
        $this->assertSame(0.00, (float) $approvedRequest->credit_amount);

        // 4. Verify invoice reflects fully paid status while keeping final_total unchanged
        $freshInvoice = $invoice->fresh();
        $this->assertSame(10000.00, (float) $freshInvoice->final_total, 'Original invoice total must remain 10,000');
        $this->assertSame(8000.00, (float) $freshInvoice->paid_amount);
        $this->assertSame(0.00, (float) $freshInvoice->balance_amount);
        $this->assertSame('paid', $freshInvoice->payment_status);
        $this->assertSame('paid', $freshInvoice->status);

        // 5. Final Bill confirms 0 remaining balance and paid status
        $finalBill = $freshInvoice->finalBill();
        $this->assertSame(10000.00, $finalBill->originalInvoiceTotal);
        $this->assertSame(2000.00, $finalBill->finalizedCreditTotal);
        $this->assertSame(8000.00, $finalBill->finalNetPayable);
        $this->assertSame(8000.00, $finalBill->amountPaid);
        $this->assertSame(0.00, $finalBill->remainingBalance);
        $this->assertSame('paid', $finalBill->effectivePaymentStatus);
        $this->assertTrue($finalBill->isFullyPaid());
    }

    public function test_no_credit_notes_preserves_existing_payment_behavior(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00);

        $preview = $this->invoiceService->allocationPreviewForShop($this->shop->id, 10000.00);
        $this->assertSame(10000.00, $preview['total_due']);
        $this->assertSame(10000.00, $preview['applied_amount']);

        $paymentRequest = $this->invoiceService->requestPayment($invoice, [
            'amount_mode' => 'custom',
            'amount' => 10000.00,
            'payment_method' => 'cash',
        ], $this->admin->id);

        $approvedRequest = $this->invoiceService->reviewPaymentRequest($paymentRequest, 'approve', $this->admin->id);

        $this->assertSame(10000.00, (float) $approvedRequest->applied_amount);

        $freshInvoice = $invoice->fresh();
        $this->assertSame(10000.00, (float) $freshInvoice->final_total);
        $this->assertSame(10000.00, (float) $freshInvoice->paid_amount);
        $this->assertSame(0.00, (float) $freshInvoice->balance_amount);
        $this->assertSame('paid', $freshInvoice->payment_status);
    }

    public function test_multiple_credit_and_debit_adjustments_payment_allocation(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 2000.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Debit, 500.00, ShopCreditNoteReason::PriceCorrection);

        // Net payable = 10,000 - 2,000 + 500 = 8,500
        $preview = $this->invoiceService->allocationPreviewForShop($this->shop->id, 8500.00);
        $this->assertSame(8500.00, $preview['total_due']);
        $this->assertSame(8500.00, $preview['applied_amount']);

        $paymentRequest = $this->invoiceService->requestPayment($invoice, [
            'amount_mode' => 'custom',
            'amount' => 8500.00,
            'payment_method' => 'cash',
        ], $this->admin->id);

        $this->invoiceService->reviewPaymentRequest($paymentRequest, 'approve', $this->admin->id);

        $freshInvoice = $invoice->fresh();
        $this->assertSame(10000.00, (float) $freshInvoice->final_total);
        $this->assertSame(8500.00, (float) $freshInvoice->paid_amount);
        $this->assertSame(0.00, (float) $freshInvoice->balance_amount);
        $this->assertSame('paid', $freshInvoice->payment_status);
        $this->assertTrue($freshInvoice->finalBill()->isFullyPaid());
    }

    public function test_partial_payment_reduces_effective_balance_correctly(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 2000.00);

        // Net payable is 8,000. Shop pays partial 3,000.
        $paymentRequest = $this->invoiceService->requestPayment($invoice, [
            'amount_mode' => 'custom',
            'amount' => 3000.00,
            'payment_method' => 'cash',
        ], $this->admin->id);

        $this->invoiceService->reviewPaymentRequest($paymentRequest, 'approve', $this->admin->id);

        $freshInvoice = $invoice->fresh();
        $this->assertSame(10000.00, (float) $freshInvoice->final_total);
        $this->assertSame(3000.00, (float) $freshInvoice->paid_amount);
        $this->assertSame(5000.00, (float) $freshInvoice->balance_amount);
        $this->assertSame('partially_paid', $freshInvoice->payment_status);

        $finalBill = $freshInvoice->finalBill();
        $this->assertSame(5000.00, $finalBill->remainingBalance);
        $this->assertSame('partially_paid', $finalBill->effectivePaymentStatus);
        $this->assertTrue($finalBill->isPartiallyPaid());
        $this->assertFalse($finalBill->isFullyPaid());
    }

    public function test_previously_paid_invoice_with_new_credit_note_is_excluded_from_pending(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00);

        // Fully pay the original invoice (10,000)
        $paymentRequest = $this->invoiceService->requestPayment($invoice, [
            'amount_mode' => 'custom',
            'amount' => 10000.00,
            'payment_method' => 'cash',
        ], $this->admin->id);
        $this->invoiceService->reviewPaymentRequest($paymentRequest, 'approve', $this->admin->id);

        // Later, admin approves a 2,000 credit note
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 2000.00);

        // Invoice is now overpaid (paid 10,000 on net payable of 8,000)
        $finalBill = $invoice->fresh()->finalBill();
        $this->assertSame(0.00, $finalBill->remainingBalance);
        $this->assertSame(2000.00, $finalBill->overpaymentCredit);
        $this->assertTrue($finalBill->isOverpaid());

        // Must be excluded from pending invoices for payment
        $pending = $this->invoiceService->pendingInvoicesForShop($this->shop->id);
        $this->assertCount(0, $pending);

        $preview = $this->invoiceService->allocationPreviewForShop($this->shop->id, 5000.00);
        $this->assertSame(0.00, $preview['total_due']);
        $this->assertSame(0.00, $preview['applied_amount']);
        $this->assertSame(5000.00, $preview['credit_amount']);
    }

    public function test_payment_exceeding_effective_balance_generates_shop_credit(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 2000.00);

        // Effective due is 8,000. Shop sends 10,000 payment.
        $preview = $this->invoiceService->allocationPreviewForShop($this->shop->id, 10000.00);
        $this->assertSame(8000.00, $preview['total_due']);
        $this->assertSame(8000.00, $preview['applied_amount']);
        $this->assertSame(2000.00, $preview['credit_amount']);

        $paymentRequest = $this->invoiceService->requestPayment($invoice, [
            'amount_mode' => 'custom',
            'amount' => 10000.00,
            'payment_method' => 'bank_transfer',
        ], $this->admin->id);

        $approvedRequest = $this->invoiceService->reviewPaymentRequest($paymentRequest, 'approve', $this->admin->id);

        $this->assertSame(8000.00, (float) $approvedRequest->applied_amount);
        $this->assertSame(2000.00, (float) $approvedRequest->credit_amount);

        // Invoice is settled at 8,000
        $freshInvoice = $invoice->fresh();
        $this->assertSame(8000.00, (float) $freshInvoice->paid_amount);
        $this->assertSame(0.00, (float) $freshInvoice->balance_amount);
        $this->assertSame('paid', $freshInvoice->payment_status);

        // Unallocated 2,000 remains as available shop credit
        $this->assertSame(2000.00, $this->invoiceService->availableShopCredit($this->shop->id));
    }

    public function test_multiple_invoices_fifo_allocation_respects_credit_adjustments(): void
    {
        // Invoice 1: 10,000 with 2,000 credit -> Net 8,000
        $inv1 = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00);
        $this->createApprovedAdjustment($inv1, ShopCreditNoteDirection::Credit, 2000.00);

        // Invoice 2: 5,000 with 0 credit -> Net 5,000
        $inv2 = $this->createSampleInvoice(finalTotal: 5000.00, paidAmount: 0.00);

        // Shop sends 10,000 payment. FIFO should give 8,000 to Inv1 (fully settling it) and 2,000 to Inv2.
        $preview = $this->invoiceService->allocationPreviewForShop($this->shop->id, 10000.00);
        $this->assertSame(13000.00, $preview['total_due']);
        $this->assertSame(10000.00, $preview['applied_amount']);
        $this->assertSame(0.00, $preview['credit_amount']);
        $this->assertCount(2, $preview['invoices']);
        $this->assertSame($inv1->id, $preview['invoices'][0]['invoice']->id);
        $this->assertSame(8000.00, $preview['invoices'][0]['amount']);
        $this->assertSame($inv2->id, $preview['invoices'][1]['invoice']->id);
        $this->assertSame(2000.00, $preview['invoices'][1]['amount']);

        $paymentRequest = $this->invoiceService->requestPayment($inv1, [
            'amount_mode' => 'custom',
            'amount' => 10000.00,
            'payment_method' => 'cash',
        ], $this->admin->id);

        $this->invoiceService->reviewPaymentRequest($paymentRequest, 'approve', $this->admin->id);

        $this->assertSame(8000.00, (float) $inv1->fresh()->paid_amount);
        $this->assertSame(0.00, (float) $inv1->fresh()->balance_amount);
        $this->assertSame('paid', $inv1->fresh()->payment_status);

        $this->assertSame(2000.00, (float) $inv2->fresh()->paid_amount);
        $this->assertSame(3000.00, (float) $inv2->fresh()->balance_amount);
        $this->assertSame('partially_paid', $inv2->fresh()->payment_status);

        // Check payment allocations table
        $allocations = ShopInvoicePaymentAllocation::query()->where('payment_request_id', $paymentRequest->id)->get();
        $this->assertCount(2, $allocations);
        $this->assertSame(8000.00, (float) $allocations->firstWhere('shop_invoice_id', $inv1->id)->amount);
        $this->assertSame(2000.00, (float) $allocations->firstWhere('shop_invoice_id', $inv2->id)->amount);
    }

    public function test_duplicate_payment_request_review_is_rejected(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00);

        $paymentRequest = $this->invoiceService->requestPayment($invoice, [
            'amount_mode' => 'custom',
            'amount' => 5000.00,
            'payment_method' => 'cash',
        ], $this->admin->id);

        // First review succeeds
        $this->invoiceService->reviewPaymentRequest($paymentRequest, 'approve', $this->admin->id);

        // Second duplicate review attempt throws ValidationException
        $this->expectException(ValidationException::class);
        $this->invoiceService->reviewPaymentRequest($paymentRequest, 'approve', $this->admin->id);
    }

    public function test_concurrent_payment_allocation_locks_invoices_and_prevents_overallocation(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 2000.00);

        // Invoice net payable is 8,000.
        // Create Request 1 (5,000)
        $req1 = $this->invoiceService->requestPayment($invoice, [
            'amount_mode' => 'custom',
            'amount' => 5000.00,
            'payment_method' => 'cash',
        ], $this->admin->id);

        // Create Request 2 (5,000)
        $req2 = ShopInvoicePaymentRequest::query()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $invoice->shop_id,
            'requested_by' => $this->admin->id,
            'request_type' => 'custom',
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
            'requested_amount' => 5000.00,
            'applied_amount' => 0,
            'credit_amount' => 0,
            'status' => 'pending',
            'reconciliation_status' => 'floating',
            'reconciled_amount' => 0,
            'floating_amount' => 5000.00,
            'shop_advance_amount' => 0,
        ]);

        // Process Req 1 (allocates 5,000)
        $this->invoiceService->reviewPaymentRequest($req1, 'approve', $this->admin->id);

        // Process Req 2 (should allocate remaining 3,000 only, leaving 2,000 as shop credit)
        $this->invoiceService->reviewPaymentRequest($req2, 'approve', $this->admin->id);

        $this->assertSame(3000.00, (float) $req2->fresh()->applied_amount);
        $this->assertSame(2000.00, (float) $req2->fresh()->credit_amount);

        // Total paid on invoice is exactly 8,000 (never overallocated)
        $freshInvoice = $invoice->fresh();
        $this->assertSame(8000.00, (float) $freshInvoice->paid_amount);
        $this->assertSame(0.00, (float) $freshInvoice->balance_amount);
        $this->assertSame('paid', $freshInvoice->payment_status);
    }

    public function test_original_invoice_and_financial_ledger_integrity(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 3000.00);

        $paymentRequest = $this->invoiceService->requestPayment($invoice, [
            'amount_mode' => 'custom',
            'amount' => 7000.00,
            'payment_method' => 'cash',
        ], $this->admin->id);

        $this->invoiceService->reviewPaymentRequest($paymentRequest, 'approve', $this->admin->id);

        $freshInvoice = $invoice->fresh();
        // Database raw columns
        $this->assertSame(10000.00, (float) $freshInvoice->final_total, 'Original loadout final_total must remain strictly 10,000');
        $this->assertSame(7000.00, (float) $freshInvoice->paid_amount);
        $this->assertSame(0.00, (float) $freshInvoice->balance_amount);
        $this->assertSame('paid', $freshInvoice->payment_status);

        // Cashbook ledger projection: ensure debit transaction remains based on original 10,000
        $ledgerTx = ShopLedgerTransaction::query()
            ->where('shop_id', $this->shop->id)
            ->where('reference_type', ShopInvoice::class)
            ->where('reference_id', $invoice->id)
            ->first();

        $this->assertNotNull($ledgerTx);
        $this->assertSame(10000.00, (float) $ledgerTx->amount, 'Original ledger debit transaction must remain based on original 10,000');
    }
}
