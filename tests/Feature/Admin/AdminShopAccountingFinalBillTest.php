<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopInvoicePaymentRequest;
use App\Models\ShopOrder;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminShopAccountingFinalBillTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private Client $client;

    private Warehouse $warehouse;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-25 12:00:00');
        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'accounts']);
        Role::firstOrCreate(['name' => 'shop']);
        Role::firstOrCreate(['name' => 'shop_owner']);

        $accessPerm = Permission::firstOrCreate(['name' => 'cashbook.access']);
        $reportsPerm = Permission::firstOrCreate(['name' => 'cashbook.reports.view']);
        $accountingPerm = Permission::firstOrCreate(['name' => 'accounting.dashboard.view']);
        $ownedShopPerm = Permission::firstOrCreate(['name' => 'accounting.owned-shop.manage']);
        $shopsManagePerm = Permission::firstOrCreate(['name' => 'cashbook.shops.manage']);
        Permission::firstOrCreate(['name' => 'sales.order.create']);

        $this->admin->givePermissionTo([$accessPerm, $reportsPerm, $accountingPerm, $ownedShopPerm, $shopsManagePerm]);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $this->client = Client::query()->create([
            'code' => 'CL-01',
            'name' => 'Main Client',
            'status' => 'active',
        ]);

        $this->shop = Shop::factory()->create([
            'name' => 'Downtown Fresh',
            'code' => 'DT-01',
            'client_id' => $this->client->id,
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $this->warehouse = Warehouse::factory()->create([
            'code' => 'MAIN-WH',
            'name' => 'Main Warehouse',
            'is_active' => true,
        ]);

        $this->category = Category::factory()->create([
            'name' => 'Vegetables',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createProduct(string $name, string $sku): Product
    {
        return Product::create([
            'name' => $name,
            'sku' => $sku,
            'category_id' => $this->category->id,
            'warehouse_id' => $this->warehouse->id,
            'unit' => 'kg',
            'pricing_unit' => 'kg',
            'price_unit' => 'kg',
            'expected_yield_percent' => 100,
            'is_active' => true,
        ]);
    }

    private function createInvoice(
        string $invoiceNumber,
        string $businessDate,
        float $finalTotal,
        float $paidAmount = 0.00,
        ?Shop $shop = null
    ): ShopInvoice {
        $targetShop = $shop ?? $this->shop;

        $order = ShopOrder::create([
            'shop_id' => $targetShop->id,
            'business_date' => $businessDate,
            'order_number' => 'ORD-'.str_replace('-', '', $invoiceNumber),
            'order_source' => 'shop_owner',
            'state' => 'approved',
            'delivery_status' => 'delivered',
            'payment_status' => $paidAmount >= $finalTotal ? 'paid' : ($paidAmount > 0 ? 'partially_paid' : 'unpaid'),
            'shop_daily_order_key' => 'shop:'.$targetShop->id.':'.$businessDate.':'.$invoiceNumber,
            'created_by' => $this->admin->id,
            'submitted_at' => $businessDate.' 08:00:00',
            'deadline_at' => $businessDate.' 10:00:00',
        ]);

        $balance = max(0.00, $finalTotal - $paidAmount);

        $invoice = ShopInvoice::create([
            'shop_id' => $targetShop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'business_date' => $businessDate,
            'final_total' => $finalTotal,
            'paid_amount' => $paidAmount,
            'balance_amount' => $balance,
            'payment_status' => $balance <= 0.0001 ? 'paid' : ($paidAmount > 0 ? 'partially_paid' : 'unpaid'),
            'generated_by' => $this->admin->id,
        ]);

        $product = $this->createProduct('Item-'.$invoiceNumber, 'SKU-'.$invoiceNumber);

        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 1,
            'price_quantity' => 1,
            'delivered_qty' => 1,
            'delivered_price_quantity' => 1,
            'unit_price' => $finalTotal,
            'line_subtotal' => $finalTotal,
            'final_line_total' => $finalTotal,
        ]);

        return $invoice;
    }

    private function createAdjustment(
        ShopInvoice $invoice,
        ShopCreditNoteDirection $direction,
        float $amount,
        ShopCreditNoteStatus $status = ShopCreditNoteStatus::Approved,
        string $date = '2026-08-25'
    ): ShopCreditNote {
        static $counter = 200;
        $counter++;

        return ShopCreditNote::create([
            'credit_note_number' => 'ADJ-'.$counter,
            'shop_id' => $invoice->shop_id,
            'shop_invoice_id' => $invoice->id,
            'credit_note_date' => $date,
            'direction' => $direction->value,
            'status' => $status->value,
            'inventory_action' => 'none',
            'subtotal' => $amount,
            'total_credit_amount' => $amount,
            'notes' => 'Adjustment test',
            'created_by' => $this->admin->id,
            'approved_by' => $status === ShopCreditNoteStatus::Approved ? $this->admin->id : null,
            'approved_at' => $status === ShopCreditNoteStatus::Approved ? now() : null,
        ]);
    }

    public function test_01_unadjusted_invoice_uses_original_totals_in_admin_accounting(): void
    {
        $this->createInvoice('INV-01-001', '2026-08-25', 10000.00, 0.00);

        // 1. Owned Shops Index
        $response = $this->actingAs($this->admin)->get(route('admin.accounting.owned-shops.index'));
        $response->assertOk();
        $shops = $response->viewData('shops');
        $shopRow = $shops->firstWhere('id', $this->shop->id);
        $this->assertNotNull($shopRow);
        $this->assertEquals(10000.00, $shopRow->pending_balance_amount);
        $this->assertEquals(0.00, $shopRow->total_overpaid_amount);

        // 2. Client Dashboard
        $clientResponse = $this->actingAs($this->admin)->get(route('admin.accounting.clients.show', [
            'client' => $this->client->id,
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]));
        $clientResponse->assertOk();
        $summary = $clientResponse->viewData('summary');
        $this->assertEquals(10000.00, $summary['invoice_original_billed']);
        $this->assertEquals(10000.00, $summary['invoice_final_billed']);
        $this->assertEquals(10000.00, $summary['invoice_pending']);
        $this->assertEquals(0.00, $summary['invoice_collected']);
        $this->assertEquals(0.00, $summary['credit_adjustments']);
        $this->assertEquals(0.00, $summary['debit_adjustments']);

        // 3. API Report Bills
        $apiResponse = $this->actingAs($this->admin)->getJson(route('admin.cashbook.api.report-bills', [
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]));
        $apiResponse->assertOk();
        $data = $apiResponse->json();
        $this->assertEquals(10000.00, $data['totals']['total_billed']);
        $this->assertEquals(10000.00, $data['totals']['total_balance']);
        $this->assertEquals(0.00, $data['totals']['total_paid']);
    }

    public function test_02_single_credit_note_reduces_final_bill_and_outstanding_balance(): void
    {
        $invoice = $this->createInvoice('INV-02-001', '2026-08-25', 10000.00, 0.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Credit, 1500.00);

        // Owned Shops Index
        $response = $this->actingAs($this->admin)->get(route('admin.accounting.owned-shops.index'));
        $response->assertOk();
        $shops = $response->viewData('shops');
        $shopRow = $shops->firstWhere('id', $this->shop->id);
        $this->assertEquals(8500.00, $shopRow->pending_balance_amount);

        // Client Dashboard
        $clientResponse = $this->actingAs($this->admin)->get(route('admin.accounting.clients.show', [
            'client' => $this->client->id,
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]));
        $clientResponse->assertOk();
        $summary = $clientResponse->viewData('summary');
        $this->assertEquals(10000.00, $summary['invoice_original_billed']);
        $this->assertEquals(1500.00, $summary['credit_adjustments']);
        $this->assertEquals(8500.00, $summary['invoice_final_billed']);
        $this->assertEquals(8500.00, $summary['invoice_pending']);

        // API Report Bills
        $apiResponse = $this->actingAs($this->admin)->getJson(route('admin.cashbook.api.report-bills', [
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]));
        $apiResponse->assertOk();
        $row = $apiResponse->json('rows.0');
        $this->assertEquals(10000.00, $row['original_final_total']);
        $this->assertEquals(8500.00, $row['final_total']);
        $this->assertEquals(8500.00, $row['balance_amount']);
        $this->assertEquals(1500.00, $row['finalized_credit_total']);
    }

    public function test_03_multiple_credit_and_debit_adjustments_compute_correct_net_bill(): void
    {
        $invoice = $this->createInvoice('INV-03-001', '2026-08-25', 10000.00, 0.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Credit, 1500.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Debit, 200.00);

        // Final Bill: 10000 - 1500 + 200 = 8700
        $clientResponse = $this->actingAs($this->admin)->get(route('admin.accounting.clients.show', [
            'client' => $this->client->id,
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]));
        $clientResponse->assertOk();
        $summary = $clientResponse->viewData('summary');
        $this->assertEquals(10000.00, $summary['invoice_original_billed']);
        $this->assertEquals(1500.00, $summary['credit_adjustments']);
        $this->assertEquals(200.00, $summary['debit_adjustments']);
        $this->assertEquals(8700.00, $summary['invoice_final_billed']);
        $this->assertEquals(8700.00, $summary['invoice_pending']);

        // Owned Shop Show delivery bills view
        $showResponse = $this->actingAs($this->admin)->get(route('admin.accounting.owned-shops.show', [
            'shop' => $this->shop,
            'period' => '2026-08',
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]));
        $showResponse->assertOk();
        $showResponse->assertSee('8,700.00');
        $showResponse->assertSee('-Rs. 1,500.00');
        $showResponse->assertSee('+Rs. 200.00');
    }

    public function test_04_partial_payment_calculates_correct_remaining_balance_and_status(): void
    {
        // Original: 10000, Credit: 1500, Debit: 200 -> Final: 8700. Paid: 5000 -> Outstanding: 3700
        $invoice = $this->createInvoice('INV-04-001', '2026-08-25', 10000.00, 5000.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Credit, 1500.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Debit, 200.00);

        $clientResponse = $this->actingAs($this->admin)->get(route('admin.accounting.clients.show', [
            'client' => $this->client->id,
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]));
        $clientResponse->assertOk();
        $summary = $clientResponse->viewData('summary');
        $this->assertEquals(5000.00, $summary['invoice_collected']);
        $this->assertEquals(3700.00, $summary['invoice_pending']);

        // API Report Bills
        $apiResponse = $this->actingAs($this->admin)->getJson(route('admin.cashbook.api.report-bills', [
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]));
        $apiResponse->assertOk();
        $row = $apiResponse->json('rows.0');
        $this->assertEquals(8700.00, $row['final_total']);
        $this->assertEquals(5000.00, $row['paid_amount']);
        $this->assertEquals(3700.00, $row['balance_amount']);
        $this->assertEquals('partially_paid', $row['payment_status']);
    }

    public function test_05_fully_settled_invoice_shows_settled_badge_and_prevents_overcollection(): void
    {
        // Original: 10000, Credit: 1500, Debit: 200 -> Final: 8700. Paid: 8700 -> Outstanding: 0
        $invoice = $this->createInvoice('INV-05-001', '2026-08-25', 10000.00, 8700.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Credit, 1500.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Debit, 200.00);

        // Effective balance is strictly 0
        $this->assertEquals(0.00, $invoice->effectiveBalanceAmount());

        // Owned Shop Show view displays Settled badge and disables payment action with N/A
        $showResponse = $this->actingAs($this->admin)->get(route('admin.accounting.owned-shops.show', [
            'shop' => $this->shop,
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]));
        $showResponse->assertOk();
        $showResponse->assertSee('Settled');
        $showResponse->assertSee('N/A');

        // Attempting to submit a payment exceeding effective bill (e.g. 8701) must fail validation
        $invalidPayment = $this->actingAs($this->admin)->patch(
            route('admin.accounting.owned-shops.daily-bills.payment', [
                'shop' => $this->shop,
                'invoice' => $invoice,
            ]),
            [
                'paid_amount' => 8701.00,
                'payment_method' => 'cash',
                'payment_date' => '2026-08-25',
            ]
        );
        $invalidPayment->assertSessionHasErrors(['paid_amount']);
    }

    public function test_06_overpayment_tracks_overpayment_credit_without_negative_due(): void
    {
        // Original: 10000, Credit: 1500, Debit: 200 -> Final: 8700. Paid: 9000 -> Overpaid: 300, Due: 0
        $invoice = $this->createInvoice('INV-06-001', '2026-08-25', 10000.00, 9000.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Credit, 1500.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Debit, 200.00);

        // Owned Shops Index
        $response = $this->actingAs($this->admin)->get(route('admin.accounting.owned-shops.index'));
        $response->assertOk();
        $shops = $response->viewData('shops');
        $shopRow = $shops->firstWhere('id', $this->shop->id);
        $this->assertEquals(0.00, $shopRow->pending_balance_amount);
        $this->assertEquals(300.00, $shopRow->total_overpaid_amount);

        // Client Dashboard
        $clientResponse = $this->actingAs($this->admin)->get(route('admin.accounting.clients.show', [
            'client' => $this->client->id,
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]));
        $clientResponse->assertOk();
        $summary = $clientResponse->viewData('summary');
        $this->assertEquals(0.00, $summary['invoice_pending']);
        $this->assertEquals(300.00, $summary['overpayment_credit']);

        // Owned Shop Show displays overpayment banner
        $showResponse = $this->actingAs($this->admin)->get(route('admin.accounting.owned-shops.show', [
            'shop' => $this->shop,
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]));
        $showResponse->assertOk();
        $showResponse->assertSee('Overpayment Credit');
        $showResponse->assertSee('300.00');
    }

    public function test_07_credit_note_issued_after_payment_reduces_remaining_due(): void
    {
        // Initially: Original 10000, Paid 5000 -> Unadjusted remaining is 5000
        $invoice = $this->createInvoice('INV-07-001', '2026-08-25', 10000.00, 5000.00);

        // Later: Admin approves credit note of 2000 -> Final is 8000 -> Remaining balance drops to 3000
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Credit, 2000.00);

        $response = $this->actingAs($this->admin)->get(route('admin.accounting.owned-shops.index'));
        $response->assertOk();
        $shops = $response->viewData('shops');
        $shopRow = $shops->firstWhere('id', $this->shop->id);
        $this->assertEquals(3000.00, $shopRow->pending_balance_amount);

        // Valid payment update (paying total effective amount of 8000) redirects with capped requested_amount = 3000
        $paymentRedirect = $this->actingAs($this->admin)->patch(
            route('admin.accounting.owned-shops.daily-bills.payment', [
                'shop' => $this->shop,
                'invoice' => $invoice,
            ]),
            [
                'paid_amount' => 8000.00,
                'payment_method' => 'cash',
                'payment_date' => '2026-08-25',
            ]
        );
        $paymentRedirect->assertRedirect();
        $this->assertStringContainsString('requested_amount=3000', $paymentRedirect->headers->get('Location'));
    }

    public function test_08_historical_credit_notes_in_later_period_preserve_immutability(): void
    {
        // Invoice generated in July 2026
        $invoice = $this->createInvoice('INV-08-001', '2026-07-15', 10000.00, 0.00);

        // Credit note approved in August 2026
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Credit, 1500.00, ShopCreditNoteStatus::Approved, '2026-08-10');

        // Verify raw database record remains strictly 10,000.00
        $rawInvoice = ShopInvoice::findOrFail($invoice->id);
        $this->assertEquals(10000.00, (float) $rawInvoice->final_total);

        // Current overall receivables reflect the adjustment
        $response = $this->actingAs($this->admin)->get(route('admin.accounting.owned-shops.index'));
        $response->assertOk();
        $shopRow = $response->viewData('shops')->firstWhere('id', $this->shop->id);
        $this->assertEquals(8500.00, $shopRow->pending_balance_amount);
    }

    public function test_09_reporting_and_settlement_queries_are_read_only_and_idempotent(): void
    {
        $invoice = $this->createInvoice('INV-09-001', '2026-08-25', 10000.00, 5000.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Credit, 1500.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Debit, 200.00);

        $initialTransactionCount = ShopLedgerTransaction::count();

        // Perform multiple report and analytics calls
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->admin)->get(route('admin.accounting.owned-shops.index'))->assertOk();
            $this->actingAs($this->admin)->get(route('admin.accounting.clients.show', [
                'client' => $this->client->id,
                'start_date' => '2026-08-25',
                'end_date' => '2026-08-25',
            ]))->assertOk();
            $this->actingAs($this->admin)->get(route('admin.accounting.owned-shops.show', [
                'shop' => $this->shop,
                'start_date' => '2026-08-25',
                'end_date' => '2026-08-25',
            ]))->assertOk();
            $this->actingAs($this->admin)->getJson(route('admin.cashbook.api.report-bills', [
                'start_date' => '2026-08-25',
                'end_date' => '2026-08-25',
            ]))->assertOk();
        }

        // Ledger transaction count must not change (zero side-effects)
        $this->assertEquals($initialTransactionCount, ShopLedgerTransaction::count());
    }

    public function test_10_admin_receivables_match_final_bill_outstanding_amounts(): void
    {
        $inv1 = $this->createInvoice('INV-10-001', '2026-08-24', 10000.00, 5000.00);
        $this->createAdjustment($inv1, ShopCreditNoteDirection::Credit, 1500.00);
        $this->createAdjustment($inv1, ShopCreditNoteDirection::Debit, 200.00);
        // Inv 1: Final = 8700, Paid = 5000, Pending = 3700

        $inv2 = $this->createInvoice('INV-10-002', '2026-08-25', 5000.00, 1000.00);
        $this->createAdjustment($inv2, ShopCreditNoteDirection::Credit, 1000.00);
        // Inv 2: Final = 4000, Paid = 1000, Pending = 3000

        // Total expected pending = 3700 + 3000 = 6700
        $response = $this->actingAs($this->admin)->get(route('admin.accounting.owned-shops.index'));
        $shopRow = $response->viewData('shops')->firstWhere('id', $this->shop->id);
        $this->assertEquals(6700.00, $shopRow->pending_balance_amount);

        $clientResponse = $this->actingAs($this->admin)->get(route('admin.accounting.clients.show', [
            'client' => $this->client->id,
            'start_date' => '2026-08-24',
            'end_date' => '2026-08-25',
        ]));
        $summary = $clientResponse->viewData('summary');
        $this->assertEquals(6700.00, $summary['invoice_pending']);

        $apiResponse = $this->actingAs($this->admin)->getJson(route('admin.cashbook.api.report-bills', [
            'timeframe' => 'custom',
            'start_date' => '2026-08-24',
            'end_date' => '2026-08-25',
        ]));
        $this->assertEquals(6700.00, $apiResponse->json('totals.total_balance'));
    }

    public function test_11_admin_accounts_and_shop_owner_balances_reconcile_to_the_cent(): void
    {
        $owner = User::factory()->create([
            'shop_id' => $this->shop->id,
        ]);
        $owner->assignRole('shop');
        $owner->givePermissionTo('sales.order.create');
        $this->shop->update(['shop_owner_id' => $owner->id]);

        $invoice = $this->createInvoice('INV-11-001', '2026-08-25', 10000.00, 5000.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Credit, 1500.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Debit, 200.00);

        // Admin side pending balance
        $adminResponse = $this->actingAs($this->admin)->get(route('admin.accounting.owned-shops.index'));
        $adminPending = $adminResponse->viewData('shops')->firstWhere('id', $this->shop->id)->pending_balance_amount;

        // Shop Owner side finance view data
        $shopOwnerResponse = $this->actingAs($owner)->get(route('shop-owner.finance.index'));
        $shopOwnerResponse->assertOk();
        $shopOwnerDue = $shopOwnerResponse->viewData('outstandingBalance');

        $this->assertEquals(3700.00, $adminPending);
        $this->assertEquals(3700.00, $shopOwnerDue);
        $this->assertSame($adminPending, $shopOwnerDue);
    }

    public function test_12_record_shop_payment_picks_oldest_effective_unsettled_invoice(): void
    {
        // Invoice 1: Older, 1000 with 1000 credit note -> effective balance is 0
        $inv1 = $this->createInvoice('INV-12-001', '2026-08-24', 1000.00, 0.00);
        $this->createAdjustment($inv1, ShopCreditNoteDirection::Credit, 1000.00);

        // Invoice 2: Newer, 2000 unadjusted -> effective balance is 2000
        $inv2 = $this->createInvoice('INV-12-002', '2026-08-25', 2000.00, 0.00);

        $requestUuid = (string) Str::uuid();

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.shop.accept-payment.store', ['shop' => $this->shop->public_uuid]),
            [
                'request_uuid' => $requestUuid,
                'amount' => 500.00,
                'payment_method' => 'cash',
                'payment_date' => '2026-08-25',
            ]
        );

        $response->assertRedirect();

        // The recorded payment request must be linked to Invoice 2, not Invoice 1!
        $paymentRequest = ShopInvoicePaymentRequest::where('submission_uuid', $requestUuid)->first();
        $this->assertNotNull($paymentRequest);
        $this->assertEquals($inv2->id, $paymentRequest->shop_invoice_id);
    }
}
