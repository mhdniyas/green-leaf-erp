<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ShopInvoice\ShopCreditNoteDirection;
use App\Enums\ShopInvoice\ShopCreditNoteStatus;
use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Models\PurchaseProductFilter;
use App\Models\PurchaseProductFilterItem;
use App\Models\Shop;
use App\Models\ShopCreditNote;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminCashbookGlBillsFinalBillTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private Warehouse $warehouse;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-25 12:00:00');
        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        $accountsRole = Role::firstOrCreate(['name' => 'accounts']);
        $accessPerm = Permission::firstOrCreate(['name' => 'cashbook.access']);
        $reportsPerm = Permission::firstOrCreate(['name' => 'cashbook.reports.view']);
        $accountsRole->givePermissionTo([$accessPerm, $reportsPerm]);

        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $client = Client::query()->create([
            'code' => 'CL-01',
            'name' => 'Main Client',
            'status' => 'active',
        ]);

        $this->shop = Shop::factory()->create([
            'name' => 'Downtown Fresh',
            'code' => 'DT-01',
            'client_id' => $client->id,
            'status' => 'active',
            'accounting_enabled' => true,
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
        static $counter = 100;
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

    public function test_01_gl_bills_unadjusted_invoice_displays_original_total_as_final_bill(): void
    {
        $this->createInvoice('INV-UNADJ-001', '2026-08-25', 10000.00, 0.00);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills', [
            'shop_id' => $this->shop->id,
            'timeframe' => 'custom',
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]));

        $response->assertOk();
        $totals = $response->viewData('totals');

        $this->assertEquals(10000.00, $totals['original_invoice_total']);
        $this->assertEquals(0.00, $totals['finalized_credit_total']);
        $this->assertEquals(0.00, $totals['finalized_debit_total']);
        $this->assertEquals(10000.00, $totals['final_net_billed']);
        $this->assertEquals(10000.00, $totals['total_billed']);
        $this->assertEquals(0.00, $totals['total_paid']);
        $this->assertEquals(10000.00, $totals['total_balance']);
        $this->assertEquals(1, $totals['count']);

        $response->assertSee('INV-UNADJ-001');
    }

    public function test_02_gl_bills_single_approved_credit_note_reduces_final_bill(): void
    {
        $invoice = $this->createInvoice('INV-CR-001', '2026-08-25', 10000.00, 0.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Credit, 1500.00);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills', [
            'shop_id' => $this->shop->id,
            'timeframe' => 'custom',
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]));

        $response->assertOk();
        $totals = $response->viewData('totals');

        $this->assertEquals(10000.00, $totals['original_invoice_total']);
        $this->assertEquals(1500.00, $totals['finalized_credit_total']);
        $this->assertEquals(0.00, $totals['finalized_debit_total']);
        $this->assertEquals(8500.00, $totals['final_net_billed']);
        $this->assertEquals(8500.00, $totals['total_billed']);
        $this->assertEquals(8500.00, $totals['total_balance']);

        // Original database invoice remains strictly immutable
        $this->assertDatabaseHas('shop_invoices', [
            'id' => $invoice->id,
            'final_total' => 10000.00,
        ]);
    }

    public function test_03_gl_bills_multiple_credits_and_debits_calculate_correct_net_payable(): void
    {
        $invoice = $this->createInvoice('INV-MIX-001', '2026-08-25', 10000.00, 0.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Credit, 1500.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Debit, 200.00);
        // Draft adjustment should be ignored
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Credit, 500.00, ShopCreditNoteStatus::Draft);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills', [
            'shop_id' => $this->shop->id,
            'timeframe' => 'custom',
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]));

        $response->assertOk();
        $totals = $response->viewData('totals');

        // Net Bill = 10,000 - 1,500 + 200 = 8,700
        $this->assertEquals(10000.00, $totals['original_invoice_total']);
        $this->assertEquals(1500.00, $totals['finalized_credit_total']);
        $this->assertEquals(200.00, $totals['finalized_debit_total']);
        $this->assertEquals(8700.00, $totals['final_net_billed']);
        $this->assertEquals(8700.00, $totals['total_billed']);
        $this->assertEquals(8700.00, $totals['total_balance']);
    }

    public function test_04_gl_bills_partially_paid_invoice_reflects_remaining_balance_and_status(): void
    {
        // Original: 10,000, Credit: 1,500, Debit: 200 -> Net Bill: 8,700
        // Paid: 5,000 -> Outstanding: 3,700
        $invoice = $this->createInvoice('INV-PARTIAL-001', '2026-08-25', 10000.00, 5000.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Credit, 1500.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Debit, 200.00);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills', [
            'shop_id' => $this->shop->id,
            'timeframe' => 'custom',
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]));

        $response->assertOk();
        $totals = $response->viewData('totals');

        $this->assertEquals(10000.00, $totals['original_invoice_total']);
        $this->assertEquals(8700.00, $totals['final_net_billed']);
        $this->assertEquals(5000.00, $totals['total_paid']);
        $this->assertEquals(3700.00, $totals['total_balance']);

        /** @var LengthAwarePaginator $invoices */
        $invoices = $response->viewData('invoices');
        $annotated = $invoices->first();
        $this->assertEquals('partially_paid', $annotated->effective_payment_status);
        $this->assertEquals(3700.00, $annotated->effective_balance_amount);
    }

    public function test_05_gl_bills_fully_paid_invoice_reflects_zero_balance_and_paid_status(): void
    {
        // Original: 10,000, Credit: 1,500, Debit: 200 -> Net Bill: 8,700
        // Paid: 8,700 -> Outstanding: 0.00
        $invoice = $this->createInvoice('INV-FULL-001', '2026-08-25', 10000.00, 8700.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Credit, 1500.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Debit, 200.00);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills', [
            'shop_id' => $this->shop->id,
            'timeframe' => 'custom',
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]));

        $response->assertOk();
        $totals = $response->viewData('totals');

        $this->assertEquals(8700.00, $totals['final_net_billed']);
        $this->assertEquals(8700.00, $totals['total_paid']);
        $this->assertEquals(0.00, $totals['total_balance']);

        /** @var LengthAwarePaginator $invoices */
        $invoices = $response->viewData('invoices');
        $annotated = $invoices->first();
        $this->assertEquals('paid', $annotated->effective_payment_status);
        $this->assertEquals(0.00, $annotated->effective_balance_amount);
    }

    public function test_06_gl_bills_overpaid_invoice_shows_overpayment_credit(): void
    {
        // Original: 10,000, Credit: 2,000 -> Net Bill: 8,000
        // Paid: 9,000 -> Overpayment: 1,000
        $invoice = $this->createInvoice('INV-OVER-001', '2026-08-25', 10000.00, 9000.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Credit, 2000.00);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills', [
            'shop_id' => $this->shop->id,
            'timeframe' => 'custom',
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]));

        $response->assertOk();
        $totals = $response->viewData('totals');

        $this->assertEquals(8000.00, $totals['final_net_billed']);
        $this->assertEquals(9000.00, $totals['total_paid']);
        $this->assertEquals(0.00, $totals['total_balance']);
        $this->assertEquals(1000.00, $totals['overpayment_credit']);

        $response->assertSee('Overpayment Credit Available')
            ->assertSee('1,000.00');

        /** @var LengthAwarePaginator $invoices */
        $invoices = $response->viewData('invoices');
        $annotated = $invoices->first();
        $this->assertEquals('overpaid', $annotated->effective_payment_status);
    }

    public function test_07_gl_bills_historical_credit_note_in_later_month_associates_correctly_with_invoice(): void
    {
        // Invoice on 2026-08-15
        $invoice = $this->createInvoice('INV-HIST-001', '2026-08-15', 10000.00, 0.00);
        // Credit Note issued in September (2026-09-05)
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Credit, 2000.00, ShopCreditNoteStatus::Approved, '2026-09-05');

        // Viewing August bills
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills', [
            'shop_id' => $this->shop->id,
            'timeframe' => 'custom',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]));

        $response->assertOk();
        $totals = $response->viewData('totals');

        // Canonical final bill reflects finalized adjustment for this invoice
        $this->assertEquals(10000.00, $totals['original_invoice_total']);
        $this->assertEquals(2000.00, $totals['finalized_credit_total']);
        $this->assertEquals(8000.00, $totals['final_net_billed']);

        // Original invoice final_total remains untouched
        $this->assertDatabaseHas('shop_invoices', [
            'id' => $invoice->id,
            'final_total' => 10000.00,
        ]);
    }

    public function test_08_gl_bills_no_duplicate_ledger_deductions_on_reporting(): void
    {
        $invoice = $this->createInvoice('INV-AUDIT-001', '2026-08-25', 10000.00, 2000.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Credit, 1500.00);

        $initialInvoices = ShopInvoice::count();
        $initialAdjustments = ShopCreditNote::count();

        // Render Report Page
        $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills', [
            'shop_id' => $this->shop->id,
            'timeframe' => 'custom',
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]))->assertOk();

        // Export CSV
        $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills.export.csv', [
            'shop_id' => $this->shop->id,
            'timeframe' => 'custom',
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]))->assertOk();

        // Export PDF
        $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills.export.pdf', [
            'shop_id' => $this->shop->id,
            'timeframe' => 'custom',
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]))->assertOk();

        // Ensure reporting operations are completely side-effect free
        $this->assertSame($initialInvoices, ShopInvoice::count());
        $this->assertSame($initialAdjustments, ShopCreditNote::count());
        $this->assertDatabaseHas('shop_invoices', [
            'id' => $invoice->id,
            'final_total' => 10000.00,
            'paid_amount' => 2000.00,
        ]);
    }

    public function test_09_gl_bills_summary_cards_reconcile_with_table_invoices_and_filters(): void
    {
        $inv1 = $this->createInvoice('INV-REC-001', '2026-08-24', 10000.00, 3000.00);
        $this->createAdjustment($inv1, ShopCreditNoteDirection::Credit, 1000.00, ShopCreditNoteStatus::Approved, '2026-08-24'); // Net: 9,000, Paid: 3,000, Bal: 6,000

        $inv2 = $this->createInvoice('INV-REC-002', '2026-08-25', 5000.00, 5000.00);
        $this->createAdjustment($inv2, ShopCreditNoteDirection::Debit, 500.00, ShopCreditNoteStatus::Approved, '2026-08-25'); // Net: 5,500, Paid: 5,000, Bal: 500

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills', [
            'shop_id' => $this->shop->id,
            'timeframe' => 'custom',
            'start_date' => '2026-08-24',
            'end_date' => '2026-08-25',
        ]));

        $response->assertOk();
        $totals = $response->viewData('totals');

        // Totals reconciliation
        $this->assertEquals(15000.00, $totals['original_invoice_total']);
        $this->assertEquals(1000.00, $totals['finalized_credit_total']);
        $this->assertEquals(500.00, $totals['finalized_debit_total']);
        $this->assertEquals(14500.00, $totals['final_net_billed']);
        $this->assertEquals(8000.00, $totals['total_paid']);
        $this->assertEquals(6500.00, $totals['total_balance']);
        $this->assertEquals(2, $totals['count']);

        // Assert rendered view shows the cards
        $response->assertSee('15,000.00')
            ->assertSee('1,000.00')
            ->assertSee('500.00')
            ->assertSee('14,500.00')
            ->assertSee('8,000.00')
            ->assertSee('6,500.00');
    }

    public function test_10_gl_bills_csv_and_pdf_exports_match_displayed_amounts(): void
    {
        $invoice = $this->createInvoice('INV-EXP-001', '2026-08-25', 10000.00, 5000.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Credit, 1500.00);
        $this->createAdjustment($invoice, ShopCreditNoteDirection::Debit, 200.00);

        // CSV Export
        $csvResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills.export.csv', [
            'shop_id' => $this->shop->id,
            'timeframe' => 'custom',
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]));

        $csvResponse->assertOk();
        ob_start();
        $csvResponse->baseResponse->sendContent();
        $csvContent = (string) ob_get_clean();

        $this->assertStringContainsString('INV-EXP-001', $csvContent);
        $this->assertStringContainsString('10000', $csvContent);
        $this->assertStringContainsString('1500', $csvContent);
        $this->assertStringContainsString('200', $csvContent);
        $this->assertStringContainsString('8700', $csvContent);
        $this->assertStringContainsString('5000', $csvContent);
        $this->assertStringContainsString('3700', $csvContent);

        // PDF Print View
        $pdfResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills.export.pdf', [
            'shop_id' => $this->shop->id,
            'timeframe' => 'custom',
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]));

        $pdfResponse->assertOk()
            ->assertSee('10,000.00')
            ->assertSee('1,500.00')
            ->assertSee('200.00')
            ->assertSee('8,700.00')
            ->assertSee('5,000.00')
            ->assertSee('3,700.00');
    }

    public function test_11_gl_bills_authorization_allows_authorized_users_and_blocks_unauthorized(): void
    {
        $accountsUser = User::factory()->create();
        $accountsUser->assignRole('accounts');

        $unauthorizedUser = User::factory()->create();

        // Admin -> 200 OK
        $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills'))->assertOk();

        // Accounts -> 200 OK
        $this->actingAs($accountsUser)->get(route('admin.cashbook.reports.gl-bills'))->assertOk();

        // Unauthorized user -> blocked (redirected or forbidden)
        $this->actingAs($unauthorizedUser)->get(route('admin.cashbook.reports.gl-bills'))->assertRedirect();
        $this->actingAs($unauthorizedUser)->getJson(route('admin.cashbook.reports.gl-bills'))->assertForbidden();
    }

    public function test_12_gl_bills_product_filter_displays_filtered_items_with_canonical_final_bill(): void
    {
        $product1 = $this->createProduct('Filter Item A', 'FIA-01');
        $product2 = $this->createProduct('Filter Item B', 'FIB-01');

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-25',
            'order_number' => 'ORD-FLT-001',
            'order_source' => 'shop_owner',
            'state' => 'approved',
            'delivery_status' => 'delivered',
            'payment_status' => 'unpaid',
            'shop_daily_order_key' => 'shop:'.$this->shop->id.':2026-08-25',
            'created_by' => $this->admin->id,
            'submitted_at' => '2026-08-25 08:00:00',
            'deadline_at' => '2026-08-25 10:00:00',
        ]);

        $invoice = ShopInvoice::create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'INV-FLT-001',
            'business_date' => '2026-08-25',
            'final_total' => 1000.00,
            'paid_amount' => 0.00,
            'balance_amount' => 1000.00,
            'payment_status' => 'unpaid',
            'generated_by' => $this->admin->id,
        ]);

        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $product1->id,
            'product_name' => $product1->name,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 4,
            'price_quantity' => 4,
            'delivered_qty' => 4,
            'delivered_price_quantity' => 4,
            'unit_price' => 100.00,
            'line_subtotal' => 400.00,
            'final_line_total' => 400.00,
        ]);

        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $product2->id,
            'product_name' => $product2->name,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 6,
            'price_quantity' => 6,
            'delivered_qty' => 6,
            'delivered_price_quantity' => 6,
            'unit_price' => 100.00,
            'line_subtotal' => 600.00,
            'final_line_total' => 600.00,
        ]);

        $filter = PurchaseProductFilter::create([
            'name' => 'Item A Only',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
        PurchaseProductFilterItem::create([
            'filter_id' => $filter->id,
            'product_id' => $product1->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills', [
            'shop_id' => $this->shop->id,
            'timeframe' => 'custom',
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
            'product_filter' => $filter->uuid,
        ]));

        $response->assertOk();
        $response->assertSee('INV-FLT-001')
            ->assertSee('400.00')
            ->assertSee('Item A Only');
    }
}
