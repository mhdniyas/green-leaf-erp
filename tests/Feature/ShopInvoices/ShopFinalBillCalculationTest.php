<?php

declare(strict_types=1);

namespace Tests\Feature\ShopInvoices;

use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\ShopInvoices\ShopCreditNoteService;
use App\Services\ShopInvoices\ShopFinalBill;
use App\Services\ShopInvoices\ShopFinalBillService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShopFinalBillCalculationTest extends TestCase
{
    use RefreshDatabase;

    private ShopFinalBillService $finalBillService;

    private ShopCreditNoteService $creditNoteService;

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

        $this->finalBillService = app(ShopFinalBillService::class);
        $this->creditNoteService = app(ShopCreditNoteService::class);

        $this->shop = Shop::query()->create([
            'name' => 'City Mart Test',
            'code' => 'CMT_01',
            'warehouse_tag' => 'CMT',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->productA = Product::factory()->create(['name' => 'Apple Royal', 'sku' => 'APP-001', 'unit' => 'kg']);
        $this->productB = Product::factory()->create(['name' => 'Banana Robusta', 'sku' => 'BAN-001', 'unit' => 'kg']);
    }

    private static int $orderCounter = 1;

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
            'product_name' => 'Apple Royal',
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
            'product_name' => 'Banana Robusta',
            'product_grade' => 'A',
            'unit' => 'kg',
            'delivered_qty' => 50,
            'approved_qty' => 50,
            'loaded_qty' => 50,
            'unit_price' => round($itemCost2 / 50, 2),
            'line_total' => $itemCost2,
            'final_line_total' => $itemCost2,
        ]);

        return $invoice;
    }

    private function createApprovedAdjustment(
        ShopInvoice $invoice,
        ShopCreditNoteDirection $direction,
        float $amount,
        ShopCreditNoteReason $reason = ShopCreditNoteReason::DamageWastage,
        ShopCreditNoteInventoryAction $inventoryAction = ShopCreditNoteInventoryAction::None
    ): ShopCreditNote {
        $firstItem = $invoice->items->first();

        $draft = $this->creditNoteService->createDraft($invoice, [
            'reason_type' => $reason->value,
            'direction' => $direction->value,
            'inventory_action' => $inventoryAction->value,
            'customer_note' => 'Adjustment for test',
            'items' => [
                [
                    'shop_invoice_item_id' => $firstItem->id,
                    'direction' => $direction->value,
                    'inventory_action' => $inventoryAction->value,
                    'quantity' => 1.0,
                    'unit_rate' => $amount,
                    'line_credit_amount' => $amount,
                ],
            ],
        ], $this->admin->id);

        return $this->creditNoteService->approve($draft, $this->admin->id, 'Approved for test');
    }

    public function test_final_bill_with_no_adjustment_equals_original_invoice(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00);

        $finalBill = $this->finalBillService->calculate($invoice);

        $this->assertInstanceOf(ShopFinalBill::class, $finalBill);
        $this->assertSame(10000.00, $finalBill->originalInvoiceTotal);
        $this->assertSame(0.00, $finalBill->finalizedCreditTotal);
        $this->assertSame(0.00, $finalBill->finalizedDebitTotal);
        $this->assertSame(0.00, $finalBill->netAdjustment);
        $this->assertSame(10000.00, $finalBill->finalNetPayable);
        $this->assertSame(0.00, $finalBill->amountPaid);
        $this->assertSame(10000.00, $finalBill->remainingBalance);
        $this->assertSame(0.00, $finalBill->overpaymentCredit);
        $this->assertSame('unpaid', $finalBill->effectivePaymentStatus);
        $this->assertFalse($finalBill->hasAdjustments);
        $this->assertTrue($finalBill->isUnpaid());
        $this->assertFalse($finalBill->isFullyPaid());
        $this->assertFalse($finalBill->isOverpaid());

        // Model helper returns identical bill
        $this->assertEquals($finalBill->toArray(), $invoice->finalBill()->toArray());
        $this->assertSame('unpaid', $invoice->effectivePaymentStatus());
    }

    public function test_final_bill_with_single_credit_reduces_payable(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 1500.00);

        $finalBill = $this->finalBillService->calculate($invoice);

        $this->assertSame(10000.00, $finalBill->originalInvoiceTotal);
        $this->assertSame(1500.00, $finalBill->finalizedCreditTotal);
        $this->assertSame(0.00, $finalBill->finalizedDebitTotal);
        $this->assertSame(-1500.00, $finalBill->netAdjustment);
        $this->assertSame(8500.00, $finalBill->finalNetPayable);
        $this->assertSame(0.00, $finalBill->amountPaid);
        $this->assertSame(8500.00, $finalBill->remainingBalance);
        $this->assertSame(0.00, $finalBill->overpaymentCredit);
        $this->assertSame('unpaid', $finalBill->effectivePaymentStatus);
        $this->assertTrue($finalBill->hasAdjustments);
    }

    public function test_final_bill_with_multiple_credits_accumulates_correctly(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 1000.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 500.00);

        $finalBill = $this->finalBillService->calculate($invoice);

        $this->assertSame(10000.00, $finalBill->originalInvoiceTotal);
        $this->assertSame(1500.00, $finalBill->finalizedCreditTotal);
        $this->assertSame(0.00, $finalBill->finalizedDebitTotal);
        $this->assertSame(8500.00, $finalBill->finalNetPayable);
        $this->assertSame(8500.00, $finalBill->remainingBalance);
    }

    public function test_final_bill_with_debit_adjustment_increases_payable(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Debit, 800.00, ShopCreditNoteReason::PriceCorrection);

        $finalBill = $this->finalBillService->calculate($invoice);

        $this->assertSame(10000.00, $finalBill->originalInvoiceTotal);
        $this->assertSame(0.00, $finalBill->finalizedCreditTotal);
        $this->assertSame(800.00, $finalBill->finalizedDebitTotal);
        $this->assertSame(800.00, $finalBill->netAdjustment);
        $this->assertSame(10800.00, $finalBill->finalNetPayable);
        $this->assertSame(10800.00, $finalBill->remainingBalance);
    }

    public function test_final_bill_with_mixed_credit_and_debit_adjustments(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 2000.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Debit, 500.00, ShopCreditNoteReason::PriceCorrection);

        $finalBill = $this->finalBillService->calculate($invoice);

        $this->assertSame(10000.00, $finalBill->originalInvoiceTotal);
        $this->assertSame(2000.00, $finalBill->finalizedCreditTotal);
        $this->assertSame(500.00, $finalBill->finalizedDebitTotal);
        $this->assertSame(-1500.00, $finalBill->netAdjustment);
        $this->assertSame(8500.00, $finalBill->finalNetPayable);
        $this->assertSame(8500.00, $finalBill->remainingBalance);
        $this->assertTrue($finalBill->hasAdjustments);
    }

    public function test_final_bill_partially_paid(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 3000.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 2000.00);

        $finalBill = $this->finalBillService->calculate($invoice);

        $this->assertSame(10000.00, $finalBill->originalInvoiceTotal);
        $this->assertSame(2000.00, $finalBill->finalizedCreditTotal);
        $this->assertSame(8000.00, $finalBill->finalNetPayable);
        $this->assertSame(3000.00, $finalBill->amountPaid);
        $this->assertSame(5000.00, $finalBill->remainingBalance);
        $this->assertSame(0.00, $finalBill->overpaymentCredit);
        $this->assertSame('partially_paid', $finalBill->effectivePaymentStatus);
        $this->assertTrue($finalBill->isPartiallyPaid());
        $this->assertFalse($finalBill->isFullyPaid());
    }

    public function test_final_bill_fully_paid(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 8000.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 2000.00);

        $finalBill = $this->finalBillService->calculate($invoice);

        $this->assertSame(8000.00, $finalBill->finalNetPayable);
        $this->assertSame(8000.00, $finalBill->amountPaid);
        $this->assertSame(0.00, $finalBill->remainingBalance);
        $this->assertSame(0.00, $finalBill->overpaymentCredit);
        $this->assertSame('paid', $finalBill->effectivePaymentStatus);
        $this->assertTrue($finalBill->isFullyPaid());
        $this->assertFalse($finalBill->isOverpaid());
    }

    public function test_final_bill_fully_settled_via_full_credit_note(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 5000.00, paidAmount: 0.00);
        $items = $invoice->items;

        $adjustmentItems = [];
        foreach ($items as $item) {
            $adjustmentItems[] = [
                'shop_invoice_item_id' => $item->id,
                'direction' => ShopCreditNoteDirection::Credit->value,
                'inventory_action' => ShopCreditNoteInventoryAction::None->value,
                'quantity' => 1.0,
                'unit_rate' => (float) $item->final_line_total,
                'line_credit_amount' => (float) $item->final_line_total,
            ];
        }

        $draft = $this->creditNoteService->createDraft($invoice, [
            'reason_type' => ShopCreditNoteReason::DamageWastage->value,
            'direction' => ShopCreditNoteDirection::Credit->value,
            'inventory_action' => ShopCreditNoteInventoryAction::None->value,
            'items' => $adjustmentItems,
        ], $this->admin->id);

        $this->creditNoteService->approve($draft, $this->admin->id, 'Approved full settlement');

        $finalBill = $this->finalBillService->calculate($invoice);

        $this->assertSame(0.00, $finalBill->finalNetPayable);
        $this->assertSame(0.00, $finalBill->remainingBalance);
        $this->assertSame('paid', $finalBill->effectivePaymentStatus);
        $this->assertTrue($finalBill->isFullyPaid());
    }

    public function test_final_bill_overpayment(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 9000.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 2000.00);

        $finalBill = $this->finalBillService->calculate($invoice);

        $this->assertSame(8000.00, $finalBill->finalNetPayable);
        $this->assertSame(9000.00, $finalBill->amountPaid);
        $this->assertSame(0.00, $finalBill->remainingBalance);
        $this->assertSame(1000.00, $finalBill->overpaymentCredit);
        $this->assertSame('overpaid', $finalBill->effectivePaymentStatus);
        $this->assertTrue($finalBill->isOverpaid());
        $this->assertTrue($finalBill->isFullyPaid());
    }

    public function test_draft_and_voided_adjustments_are_strictly_excluded(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00);
        $firstItem = $invoice->items->first();

        // 1. Create a draft credit note (should be excluded)
        $this->creditNoteService->createDraft($invoice, [
            'reason_type' => ShopCreditNoteReason::DamageWastage->value,
            'direction' => ShopCreditNoteDirection::Credit->value,
            'inventory_action' => ShopCreditNoteInventoryAction::None->value,
            'items' => [
                [
                    'shop_invoice_item_id' => $firstItem->id,
                    'direction' => ShopCreditNoteDirection::Credit->value,
                    'inventory_action' => ShopCreditNoteInventoryAction::None->value,
                    'quantity' => 1.0,
                    'unit_rate' => 1000.00,
                    'line_credit_amount' => 1000.00,
                ],
            ],
        ], $this->admin->id);

        // 2. Create a voided credit note (should be excluded)
        ShopCreditNote::query()->create([
            'credit_note_number' => 'CN-VOID-TEST-01',
            'credit_note_date' => now()->toDateString(),
            'shop_invoice_id' => $invoice->id,
            'shop_id' => $invoice->shop_id,
            'reason_type' => ShopCreditNoteReason::PriceCorrection->value,
            'status' => ShopCreditNoteStatus::Voided->value,
            'direction' => ShopCreditNoteDirection::Credit->value,
            'inventory_action' => ShopCreditNoteInventoryAction::None->value,
            'total_credit_amount' => 2000.00,
            'created_by' => $this->admin->id,
        ]);

        // 3. Create an approved credit note (should be included)
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 500.00);

        $finalBill = $this->finalBillService->calculate($invoice);

        $this->assertSame(10000.00, $finalBill->originalInvoiceTotal);
        $this->assertSame(500.00, $finalBill->finalizedCreditTotal);
        $this->assertSame(0.00, $finalBill->finalizedDebitTotal);
        $this->assertSame(9500.00, $finalBill->finalNetPayable);
        $this->assertSame(9500.00, $finalBill->remainingBalance);
    }

    public function test_original_invoice_record_remains_strictly_unchanged(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 2000.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 1500.00);

        // Calculate final bill multiple times
        $this->finalBillService->calculate($invoice);
        $invoice->finalBill();

        // Refresh invoice from DB and assert raw database columns were not modified
        $freshInvoice = $invoice->fresh();
        $this->assertSame(10000.00, (float) $freshInvoice->final_total);
        $this->assertSame(2000.00, (float) $freshInvoice->paid_amount);
        $this->assertSame(8000.00, (float) $freshInvoice->balance_amount);
    }

    public function test_final_bill_works_with_eager_loaded_credit_notes_without_extra_queries(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 1000.00);

        $invoiceWithRelation = ShopInvoice::query()
            ->with(['creditNotes'])
            ->findOrFail($invoice->id);

        $finalBill = $this->finalBillService->calculate($invoiceWithRelation);

        $this->assertSame(10000.00, $finalBill->originalInvoiceTotal);
        $this->assertSame(1000.00, $finalBill->finalizedCreditTotal);
        $this->assertSame(9000.00, $finalBill->finalNetPayable);
    }

    public function test_calculate_many_invoices_preserves_invoice_keys(): void
    {
        $inv1 = $this->createSampleInvoice(finalTotal: 5000.00, paidAmount: 1000.00);
        $inv2 = $this->createSampleInvoice(finalTotal: 8000.00, paidAmount: 0.00);

        $this->createApprovedAdjustment($inv1, ShopCreditNoteDirection::Credit, 500.00);

        $results = $this->finalBillService->calculateMany([$inv1, $inv2]);

        $this->assertCount(2, $results);
        $this->assertTrue($results->has($inv1->id));
        $this->assertTrue($results->has($inv2->id));

        $this->assertSame(4500.00, $results->get($inv1->id)->finalNetPayable);
        $this->assertSame(3500.00, $results->get($inv1->id)->remainingBalance);

        $this->assertSame(8000.00, $results->get($inv2->id)->finalNetPayable);
        $this->assertSame(8000.00, $results->get($inv2->id)->remainingBalance);
    }
}
