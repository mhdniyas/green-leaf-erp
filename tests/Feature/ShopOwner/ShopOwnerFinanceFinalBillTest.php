<?php

declare(strict_types=1);

namespace Tests\Feature\ShopOwner;

use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopOwnerAssignment;
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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopOwnerFinanceFinalBillTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopOwner;

    private Shop $shop;

    private Product $productA;

    private Product $productB;

    private ShopCreditNoteService $creditNoteService;

    private ShopInvoiceService $invoiceService;

    private static int $orderCounter = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        config(['admin.user_access.main_admin_email' => 'admin@example.com']);

        $this->admin = User::factory()->create(['email' => 'admin@example.com']);
        $this->admin->assignRole('admin');

        Permission::findOrCreate('sales.order.create');
        Role::findOrCreate('shop');

        $this->shopOwner = User::factory()->create(['email' => 'owner@example.com']);
        $this->shopOwner->givePermissionTo('sales.order.create');
        $this->shopOwner->assignRole('shop');

        $this->shop = Shop::query()->create([
            'name' => 'Green Leaf Test Shop',
            'code' => 'GLS_TEST',
            'warehouse_tag' => 'GLS',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        ShopOwnerAssignment::query()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->shopOwner->id,
            'status' => 'active',
        ]);

        ShopLedgerProfile::query()->create([
            'shop_id' => $this->shop->id,
            'enabled' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $this->productA = Product::factory()->create(['name' => 'Fresh Tomato', 'sku' => 'TOM-001', 'unit' => 'kg']);
        $this->productB = Product::factory()->create(['name' => 'Green Chili', 'sku' => 'CHI-001', 'unit' => 'kg']);

        $this->creditNoteService = app(ShopCreditNoteService::class);
        $this->invoiceService = app(ShopInvoiceService::class);
    }

    private function createSampleInvoice(
        float $finalTotal = 10000.00,
        float $paidAmount = 0.00,
        ?Shop $targetShop = null,
        ?string $businessDate = null
    ): ShopInvoice {
        $counter = self::$orderCounter++;
        $targetShop = $targetShop ?? $this->shop;
        $date = $businessDate ?? Carbon::parse('2026-11-05')->addDays($counter)->format('Y-m-d');

        $order = ShopOrder::factory()->create([
            'shop_id' => $targetShop->id,
            'order_number' => "ORD-{$date}-{$counter}",
            'business_date' => $date,
            'shop_daily_order_key' => "shop:{$targetShop->id}:{$date}:{$counter}",
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
            'shop_id' => $targetShop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => "SINV-{$date}-{$counter}",
            'business_date' => $date,
            'status' => 'finalized',
            'delivery_status' => 'received_full',
            'payment_status' => $balanceAmount <= 0.0001 ? 'paid' : ($paidAmount > 0 ? 'partially_paid' : 'unpaid'),
            'subtotal' => $finalTotal,
            'discount_total' => 0.00,
            'final_total' => $finalTotal,
            'paid_amount' => $paidAmount,
            'balance_amount' => $balanceAmount,
            'finalized_at' => Carbon::parse("{$date} 18:00:00"),
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
            'customer_note' => 'Adjustment for shop owner finance test',
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

        $this->creditNoteService->approve($draft, $this->admin->id, 'Approved for test');
    }

    /**
     * 1. Invoice without adjustments: original equals final bill.
     */
    public function test_01_invoice_without_adjustments_shows_original_equals_final_bill(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00);

        $response = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $this->shop->code])
            ->get(route('shop-owner.finance.index'));

        $response->assertStatus(200);
        $response->assertSee('Rs. 10,000.00');
        $response->assertSee($invoice->invoice_number);

        // Details page
        $showResponse = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $this->shop->code])
            ->get(route('shop-owner.finance.show', $invoice));

        $showResponse->assertStatus(200);
        $showResponse->assertSee('Rs. 10,000.00');
        $showResponse->assertDontSee('Credit Adjustments');
    }

    /**
     * 2. One approved credit note: reduces total billed, net payable, and balance.
     */
    public function test_02_one_approved_credit_note_reduces_payable_and_balance(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 2000.00);

        // Original invoice untouched in DB
        $invoice->refresh();
        $this->assertEquals(10000.00, (float) $invoice->final_total);

        // Payments tab check
        $response = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $this->shop->code])
            ->get(route('shop-owner.finance.index', ['tab' => 'payments']));

        $response->assertStatus(200);
        // Original 10k, adjustment -2k, final bill 8k
        $response->assertSee('Rs. 10,000.00');
        $response->assertSee('-Rs. 2,000.00');
        $response->assertSee('Rs. 8,000.00');

        // Show page check
        $showResponse = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $this->shop->code])
            ->get(route('shop-owner.finance.show', $invoice));

        $showResponse->assertStatus(200);
        $showResponse->assertSee('Original Invoice Total');
        $showResponse->assertSee('Credit Adjustments');
        $showResponse->assertSee('- Rs. 2,000.00');
        $showResponse->assertSee('Final Net Bill');
        $showResponse->assertSee('Rs. 8,000.00');
    }

    /**
     * 3. Multiple credits and debits: correct net final bill and balance.
     */
    public function test_03_multiple_credits_and_debits_accumulate_correctly(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 3000.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Debit, 1000.00, ShopCreditNoteReason::PriceCorrection);

        $bill = $invoice->finalBill();
        $this->assertEquals(10000.00, $bill->originalInvoiceTotal);
        $this->assertEquals(3000.00, $bill->finalizedCreditTotal);
        $this->assertEquals(1000.00, $bill->finalizedDebitTotal);
        $this->assertEquals(-2000.00, $bill->netAdjustment);
        $this->assertEquals(8000.00, $bill->finalNetPayable);
        $this->assertEquals(8000.00, $bill->remainingBalance);

        $response = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $this->shop->code])
            ->get(route('shop-owner.finance.index', ['tab' => 'payments']));

        $response->assertStatus(200);
        $response->assertSee('Rs. 8,000.00');
    }

    /**
     * 4. Partially paid invoice.
     */
    public function test_04_partially_paid_invoice_shows_correct_remaining_balance(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 3000.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 2000.00);

        // Final bill: 8000, Paid: 3000, Remaining: 5000
        $bill = $invoice->finalBill();
        $this->assertEquals(8000.00, $bill->finalNetPayable);
        $this->assertEquals(3000.00, $bill->amountPaid);
        $this->assertEquals(5000.00, $bill->remainingBalance);
        $this->assertEquals('partially_paid', $bill->effectivePaymentStatus);

        $response = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $this->shop->code])
            ->get(route('shop-owner.finance.show', $invoice));

        $response->assertStatus(200);
        $response->assertSee('Rs. 5,000.00');
        $response->assertSee('Partially Paid');
    }

    /**
     * 5. Fully settled invoice: excluded from payable invoices dropdown.
     */
    public function test_05_fully_settled_invoice_excluded_from_payable_options(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 8000.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 2000.00);

        // Final bill: 8000, Paid: 8000 => Remaining: 0
        $bill = $invoice->finalBill();
        $this->assertEquals(0.00, $bill->remainingBalance);
        $this->assertEquals('paid', $bill->effectivePaymentStatus);

        $response = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $this->shop->code])
            ->get(route('shop-owner.finance.index', ['tab' => 'payments']));

        $response->assertStatus(200);
        $response->assertSee('No payable bills pending.');

        $showResponse = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $this->shop->code])
            ->get(route('shop-owner.finance.show', $invoice));

        $showResponse->assertStatus(200);
        $showResponse->assertSee('This invoice is already fully settled.');
    }

    /**
     * 6. Overpayment credit display: when credit notes exceed remaining balance.
     */
    public function test_06_overpayment_credit_displayed_safely(): void
    {
        // Invoice 10,000, paid 9,000, then credit 2,000 => Final bill 8,000, Paid 9,000 => Overpayment 1,000
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 9000.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 2000.00);

        $bill = $invoice->finalBill();
        $this->assertEquals(1000.00, $bill->overpaymentCredit);
        $this->assertEquals(0.00, $bill->remainingBalance);

        $response = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $this->shop->code])
            ->get(route('shop-owner.finance.index', ['tab' => 'payments']));

        $response->assertStatus(200);
        $response->assertSee('Overpayment Credit');
        $response->assertSee('Rs. 1,000.00');
    }

    /**
     * 7. Payment request amounts: defaults match effective remaining balance.
     */
    public function test_07_payment_request_amounts_default_to_effective_remaining_balance(): void
    {
        $independentShop = Shop::query()->create([
            'name' => 'Independent Branch',
            'code' => 'IND_BRANCH',
            'warehouse_tag' => 'IND',
            'status' => 'active',
            'accounting_enabled' => false,
            'accounting_mode' => 'independent',
            'is_active' => true,
        ]);

        ShopOwnerAssignment::query()->create([
            'shop_id' => $independentShop->id,
            'user_id' => $this->shopOwner->id,
            'status' => 'active',
        ]);

        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 2000.00, targetShop: $independentShop);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 3000.00);

        // Final bill: 7000, Paid: 2000 => Remaining: 5000
        $bill = $invoice->finalBill();
        $this->assertEquals(5000.00, $bill->remainingBalance);

        $showResponse = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $independentShop->code])
            ->get(route('shop-owner.finance.show', $invoice));

        $showResponse->assertStatus(200);
        $showResponse->assertSee('value="5000.00"', false);
    }

    /**
     * 8. Shop ownership and authorization boundaries.
     */
    public function test_08_shop_ownership_and_authorization_boundaries_enforced(): void
    {
        $otherShop = Shop::query()->create([
            'name' => 'Other Branch',
            'code' => 'OTHER_SHOP',
            'warehouse_tag' => 'OTH',
            'status' => 'active',
            'is_active' => true,
        ]);

        $otherInvoice = $this->createSampleInvoice(finalTotal: 5000.00, targetShop: $otherShop);

        // Shop owner cannot access other shop's invoice
        $response = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $this->shop->code])
            ->get(route('shop-owner.finance.show', $otherInvoice));

        $response->assertStatus(403);
    }

    /**
     * 9. Correct totals across summary cards, KPI blocks, and tables.
     */
    public function test_09_correct_totals_across_summary_cards_and_tables(): void
    {
        $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00);
        $inv2 = $this->createSampleInvoice(finalTotal: 5000.00, paidAmount: 1000.00);
        $this->createApprovedAdjustment($inv2, ShopCreditNoteDirection::Credit, 1000.00);

        // Inv 1: Final 10000, Paid 0, Bal 10000
        // Inv 2: Orig 5000, Credit 1000, Final 4000, Paid 1000, Bal 3000
        // Total Final Billed: 14000, Total Paid: 1000, Total Outstanding: 13000

        $response = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $this->shop->code])
            ->get(route('shop-owner.finance.index', ['tab' => 'payments']));

        $response->assertStatus(200);
        $response->assertSee('Rs. 14,000.00');
        $response->assertSee('Rs. 13,000.00');
    }

    /**
     * 10. Existing Shop Owner finance functionality remains intact (e.g. PDF generation).
     */
    public function test_10_existing_finance_functionality_and_pdf_remain_intact(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 2000.00);
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 1500.00);

        $pdfResponse = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $this->shop->code])
            ->get(route('shop-owner.finance.pdf', $invoice));

        $pdfResponse->assertStatus(200);
        $pdfResponse->assertSee($invoice->invoice_number);
    }
}
