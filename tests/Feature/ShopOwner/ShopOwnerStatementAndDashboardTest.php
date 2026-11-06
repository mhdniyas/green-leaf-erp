<?php

declare(strict_types=1);

namespace Tests\Feature\ShopOwner;

use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteInventoryAction;
use App\Enums\ShopInvoice\ShopCreditNoteReason;
use App\Http\Controllers\Web\ShopOwnerController;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopInvoicePaymentRequest;
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
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopOwnerStatementAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopOwner;

    private Shop $shop;

    private Product $productA;

    private Product $productB;

    private ShopCreditNoteService $creditNoteService;

    private ShopInvoiceService $invoiceService;

    private ShopOwnerController $controller;

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
            'accounting_enabled' => false,
            'accounting_mode' => 'independent',
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
        $this->controller = app(ShopOwnerController::class);
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
        ShopCreditNoteReason $reason = ShopCreditNoteReason::DamageWastage,
        ?string $creditNoteDate = null
    ): void {
        $firstItem = $invoice->items->first();

        $draft = $this->creditNoteService->createDraft($invoice, [
            'reason_type' => $reason->value,
            'direction' => $direction->value,
            'inventory_action' => ShopCreditNoteInventoryAction::None->value,
            'customer_note' => 'Adjustment for statement test',
            'credit_note_date' => $creditNoteDate ?? now()->toDateString(),
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

        if ($creditNoteDate) {
            $draft->update(['credit_note_date' => $creditNoteDate]);
        }

        $this->creditNoteService->approve($draft, $this->admin->id, 'Approved for test');
    }

    private function createApprovedPayment(ShopInvoice $invoice, float $amount, ?string $paymentDate = null): ShopInvoicePaymentRequest
    {
        $pr = ShopInvoicePaymentRequest::query()->create([
            'shop_id' => $invoice->shop_id,
            'shop_invoice_id' => $invoice->id,
            'requested_by' => $this->shopOwner->id,
            'submission_uuid' => (string) str()->uuid(),
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'TXN-'.uniqid(),
            'payment_date' => $paymentDate ?? now()->toDateString(),
            'requested_amount' => $amount,
            'approved_amount' => $amount,
            'status' => 'approved',
            'reviewed_by' => $this->admin->id,
            'reviewed_at' => now(),
        ]);

        $invoice->paid_amount = round((float) $invoice->paid_amount + $amount, 2);
        $invoice->balance_amount = max(0.00, round((float) $invoice->final_total - (float) $invoice->paid_amount, 2));
        $invoice->save();
        $this->invoiceService->recalculate($invoice);

        return $pr;
    }

    /**
     * 1. No credit note: original equals final bill, statement running balance matches.
     */
    public function test_01_no_credit_note_statement_and_dashboard_reconcile(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00, businessDate: '2026-11-01');

        // Dashboard check
        $dashResponse = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $this->shop->code])
            ->get(route('shop.dashboard'));

        $dashResponse->assertStatus(200);
        $dashResponse->assertSee('Rs. 10,000.00');

        // Statement check
        $request = Request::create(route('shop-owner.finance.index', ['tab' => 'statement']), 'GET', ['tab' => 'statement']);
        $statement = $this->controller->statementOfAccountData($request, $this->shop);

        $this->assertEquals(0.00, $statement['openingBalance']);
        $this->assertEquals(10000.00, $statement['totalInvoiced']);
        $this->assertEquals(0.00, $statement['totalAdjustments']);
        $this->assertEquals(10000.00, $statement['closingBalance']);
        $this->assertCount(1, $statement['entries']);
        $this->assertEquals(10000.00, $statement['entries'][0]['balance']);

        // Web view assertion
        $response = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $this->shop->code])
            ->get(route('shop-owner.finance.index', ['tab' => 'statement']));

        if ($response->status() === 302) {
            dump($response->headers->get('Location'));
        }

        $response->assertStatus(200);
        $response->assertSee('Statement of Account');
        $response->assertSee('Rs. 10,000.00');
    }

    /**
     * 2. One credit adjustment: Original ₹10,000, Credit ₹1,500, Final ₹8,500.
     */
    public function test_02_one_credit_adjustment_reconciles_dashboard_and_statement(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00, businessDate: '2026-11-01');
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 1500.00, creditNoteDate: '2026-11-03');

        // Check billingSummary
        $summary = $this->controller->billingSummary(collect([$invoice]));
        $this->assertEquals(10000.00, $summary['gross_billed']);
        $this->assertEquals(8500.00, $summary['total_final_billed']);
        $this->assertEquals(8500.00, $summary['total_balance']);
        $this->assertEquals(1500.00, $summary['total_credits']);

        // Check pendingBalance helper
        $this->assertEquals(8500.00, $this->controller->pendingBalance($this->shop));

        // Check Statement
        $request = Request::create('/finance?tab=statement', 'GET', ['tab' => 'statement']);
        $statement = $this->controller->statementOfAccountData($request, $this->shop);

        $this->assertEquals(10000.00, $statement['totalInvoiced']);
        $this->assertEquals(-1500.00, $statement['totalAdjustments']);
        $this->assertEquals(8500.00, $statement['closingBalance']);

        // Entry 1: Invoice on Nov 1 -> bal 10000
        $this->assertEquals('invoice', $statement['entries'][0]['type']);
        $this->assertEquals(10000.00, $statement['entries'][0]['debit']);
        $this->assertEquals(10000.00, $statement['entries'][0]['balance']);

        // Entry 2: Credit note on Nov 3 -> bal 8500
        $this->assertEquals('credit_note', $statement['entries'][1]['type']);
        $this->assertEquals(1500.00, $statement['entries'][1]['credit']);
        $this->assertEquals(8500.00, $statement['entries'][1]['balance']);
    }

    /**
     * 3. Multiple credit and debit adjustments.
     */
    public function test_03_multiple_credits_and_debits_accumulate_correctly(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00, businessDate: '2026-11-01');
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 3000.00, creditNoteDate: '2026-11-02');
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Debit, 1000.00, ShopCreditNoteReason::PriceCorrection, creditNoteDate: '2026-11-03');

        $statement = $this->controller->statementOfAccountData(Request::create('/finance?tab=statement'), $this->shop);

        $this->assertEquals(10000.00, $statement['totalInvoiced']);
        $this->assertEquals(-2000.00, $statement['totalAdjustments']);
        $this->assertEquals(8000.00, $statement['closingBalance']);
        $this->assertCount(3, $statement['entries']);
    }

    /**
     * 4. Partial payment: Original ₹10,000, Credit ₹1,500, Paid ₹5,000 => Outstanding ₹3,500.
     */
    public function test_04_partial_payment_and_credit_note_leaves_exact_outstanding(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00, businessDate: '2026-11-01');
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 1500.00, creditNoteDate: '2026-11-02');
        $this->createApprovedPayment($invoice, 5000.00, '2026-11-03');

        $this->assertEquals(3500.00, $this->controller->pendingBalance($this->shop));

        $statement = $this->controller->statementOfAccountData(Request::create('/finance?tab=statement'), $this->shop);
        $this->assertEquals(10000.00, $statement['totalInvoiced']);
        $this->assertEquals(-1500.00, $statement['totalAdjustments']);
        $this->assertEquals(5000.00, $statement['totalPayments']);
        $this->assertEquals(3500.00, $statement['closingBalance']);

        $dashResponse = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $this->shop->code])
            ->get(route('shop.dashboard'));

        $dashResponse->assertStatus(200);
        $dashResponse->assertSee('Rs. 3,500.00');
    }

    /**
     * 5. Fully paid invoice: ₹8,500 final bill, ₹8,500 paid => 0 due.
     */
    public function test_05_fully_paid_invoice_shows_zero_outstanding(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00, businessDate: '2026-11-01');
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 1500.00, creditNoteDate: '2026-11-02');
        $this->createApprovedPayment($invoice, 8500.00, '2026-11-03');

        $this->assertEquals(0.00, $this->controller->pendingBalance($this->shop));

        $statement = $this->controller->statementOfAccountData(Request::create('/finance?tab=statement'), $this->shop);
        $this->assertEquals(0.00, $statement['closingBalance']);
    }

    /**
     * 6. Overpayment: Final bill ₹8,000, Paid ₹9,000 => Overpayment ₹1,000.
     */
    public function test_06_overpayment_handled_and_displayed(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00, businessDate: '2026-11-01');
        $this->createApprovedPayment($invoice, 9000.00, '2026-11-02');
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 2000.00, creditNoteDate: '2026-11-03');

        $details = $this->controller->pendingBalanceDetails($this->shop);
        $this->assertEquals(1000.00, $details['overpayment_credit']);
        $this->assertEquals(0.00, $details['outstanding_balance']);

        // Dashboard displays overpayment notice
        $dashResponse = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $this->shop->code])
            ->get(route('shop.dashboard'));

        $dashResponse->assertStatus(200);
        $dashResponse->assertSee('Available Overpayment Credit');
        $dashResponse->assertSee('Rs. 1,000.00');
    }

    /**
     * 7. Historical credit note issued in a later month:
     * Invoice in Oct (₹10,000), Credit Note in Nov (₹2,000).
     * Nov statement has opening balance ₹10,000 and closing balance ₹8,000.
     */
    public function test_07_historical_credit_note_preserves_opening_balance(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00, businessDate: '2026-10-15');
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 2000.00, creditNoteDate: '2026-11-05');

        // Request Statement scoped strictly to November: 2026-11-01 to 2026-11-30
        $request = Request::create('/finance', 'GET', [
            'tab' => 'statement',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
        ]);

        $statement = $this->controller->statementOfAccountData($request, $this->shop);

        // Prior to Nov 1, invoice was ₹10,000, so opening balance is ₹10,000
        $this->assertEquals(10000.00, $statement['openingBalance']);
        // Period contains only the credit note (-₹2,000)
        $this->assertEquals(0.00, $statement['totalInvoiced']);
        $this->assertEquals(-2000.00, $statement['totalAdjustments']);
        // Closing balance = ₹8,000
        $this->assertEquals(8000.00, $statement['closingBalance']);
        $this->assertCount(1, $statement['entries']);
        $this->assertEquals(8000.00, $statement['entries'][0]['balance']);
    }

    /**
     * 8. Correct opening and closing statement balances: closing = opening + debits - credits.
     */
    public function test_08_mathematical_reconciliation_of_statement_balances(): void
    {
        $this->createSampleInvoice(finalTotal: 5000.00, paidAmount: 1000.00, businessDate: '2026-10-10');
        $invNov = $this->createSampleInvoice(finalTotal: 7000.00, paidAmount: 0.00, businessDate: '2026-11-10');
        $this->createApprovedAdjustment($invNov, ShopCreditNoteDirection::Credit, 1000.00, creditNoteDate: '2026-11-12');
        $this->createApprovedPayment($invNov, 2000.00, '2026-11-15');

        $request = Request::create('/finance', 'GET', [
            'tab' => 'statement',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
        ]);

        $statement = $this->controller->statementOfAccountData($request, $this->shop);

        // Opening = 5000 - 1000 = 4000
        $this->assertEquals(4000.00, $statement['openingBalance']);
        // Nov Debits = 7000 (invoice)
        $this->assertEquals(7000.00, $statement['totalDebits']);
        // Nov Credits = 1000 (credit note) + 2000 (payment) = 3000
        $this->assertEquals(3000.00, $statement['totalCredits']);
        // Closing = 4000 + 7000 - 3000 = 8000
        $this->assertEquals(8000.00, $statement['closingBalance']);
        $this->assertEquals(
            round($statement['openingBalance'] + $statement['totalDebits'] - $statement['totalCredits'], 2),
            $statement['closingBalance']
        );
    }

    /**
     * 9. No duplicate adjustment entries in statement.
     */
    public function test_09_no_duplicate_adjustment_entries(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00, businessDate: '2026-11-01');
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 1500.00, creditNoteDate: '2026-11-02');

        $statement = $this->controller->statementOfAccountData(Request::create('/finance?tab=statement'), $this->shop);

        $creditNoteEntries = collect($statement['entries'])->where('type', 'credit_note');
        $this->assertCount(1, $creditNoteEntries);
    }

    /**
     * 10. Dashboard and Statement balances reconcile.
     */
    public function test_10_dashboard_and_statement_balances_reconcile(): void
    {
        $inv1 = $this->createSampleInvoice(finalTotal: 8000.00, paidAmount: 2000.00, businessDate: '2026-11-01');
        $this->createApprovedAdjustment($inv1, ShopCreditNoteDirection::Credit, 1000.00, creditNoteDate: '2026-11-02');

        $statement = $this->controller->statementOfAccountData(Request::create('/finance?tab=statement'), $this->shop);
        $pending = $this->controller->pendingBalance($this->shop);

        $this->assertEquals($pending, $statement['closingBalance']);
        $this->assertEquals(5000.00, $pending);
    }

    /**
     * 11. Shop ownership authorization boundaries.
     */
    public function test_11_shop_ownership_authorization(): void
    {
        $otherShop = Shop::query()->create([
            'name' => 'Other Branch',
            'code' => 'OTHER_01',
            'warehouse_tag' => 'OTH',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->createSampleInvoice(finalTotal: 10000.00, targetShop: $otherShop);

        // Statement of target shop has ₹0 for shop owner's shop
        $statement = $this->controller->statementOfAccountData(Request::create('/finance?tab=statement'), $this->shop);
        $this->assertEquals(0.00, $statement['closingBalance']);
    }

    /**
     * 12. Existing Shop Owner features remain functional (deliveriesIndex, deliveries pending total).
     */
    public function test_12_existing_features_and_deliveries_pending_bill_remain_functional(): void
    {
        $invoice = $this->createSampleInvoice(finalTotal: 10000.00, paidAmount: 0.00, businessDate: today()->toDateString());
        $this->createApprovedAdjustment($invoice, ShopCreditNoteDirection::Credit, 2500.00);

        // Deliveries page should show credit-adjusted pending bill (₹7,500.00)
        $response = $this->actingAs($this->shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $this->shop->code])
            ->get(route('shop-owner.deliveries.index'));

        $response->assertStatus(200);
        $response->assertSee('Rs. 7,500.00');
    }
}
