<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Models\PurchaseProductFilter;
use App\Models\PurchaseProductFilterItem;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashbookGlBillsReportTest extends TestCase
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

    public function test_gl_bills_page_renders_with_sidebar_link_under_finance(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.gl-bills'));

        $response->assertOk()
            ->assertSee('GL Bills')
            ->assertSee('Daily Shop Invoices')
            ->assertSee('All Products');
    }

    public function test_gl_bills_filters_by_shop_and_date_range(): void
    {
        $product = $this->createProduct('Tomato', 'TOM-01');

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-25',
            'order_number' => 'RQ-DT-001',
            'order_source' => 'shop_owner',
            'state' => 'approved',
            'delivery_status' => 'delivered',
            'payment_status' => 'paid',
            'shop_daily_order_key' => 'shop:'.$this->shop->id.':2026-08-25',
            'created_by' => $this->admin->id,
            'submitted_at' => '2026-08-25 08:00:00',
            'deadline_at' => '2026-08-25 10:00:00',
        ]);

        $invoice = ShopInvoice::create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'INV-DT-20260825-001',
            'business_date' => '2026-08-25',
            'final_total' => 500.00,
            'paid_amount' => 500.00,
            'balance_amount' => 0.00,
            'generated_by' => $this->admin->id,
        ]);

        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 10,
            'price_quantity' => 10,
            'delivered_qty' => 10,
            'delivered_price_quantity' => 10,
            'unit_price' => 50.00,
            'line_subtotal' => 500.00,
            'final_line_total' => 500.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.gl-bills', [
            'shop_id' => $this->shop->id,
            'timeframe' => 'custom',
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
        ]));

        $response->assertOk()
            ->assertSee('INV-DT-20260825-001')
            ->assertSee('500.00')
            ->assertSee('Downtown Fresh');
    }

    public function test_gl_bills_filters_by_product_filter(): void
    {
        $tomato = $this->createProduct('Tomato', 'TOM-01');
        $potato = $this->createProduct('Potato', 'POT-01');

        // Invoice 1 has Tomato
        $order1 = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-24',
            'order_number' => 'RQ-DT-002',
            'order_source' => 'shop_owner',
            'state' => 'approved',
            'delivery_status' => 'delivered',
            'payment_status' => 'paid',
            'shop_daily_order_key' => 'shop:'.$this->shop->id.':2026-08-24',
            'created_by' => $this->admin->id,
            'submitted_at' => '2026-08-24 08:00:00',
            'deadline_at' => '2026-08-24 10:00:00',
        ]);
        $invoice1 = ShopInvoice::create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order1->id,
            'invoice_number' => 'INV-TOM-001',
            'business_date' => '2026-08-24',
            'final_total' => 300.00,
            'generated_by' => $this->admin->id,
        ]);
        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice1->id,
            'product_id' => $tomato->id,
            'product_name' => $tomato->name,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 6,
            'price_quantity' => 6,
            'delivered_qty' => 6,
            'delivered_price_quantity' => 6,
            'unit_price' => 50.00,
            'line_subtotal' => 300.00,
            'final_line_total' => 300.00,
        ]);

        // Invoice 2 has Potato only
        $order2 = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-25',
            'order_number' => 'RQ-DT-003',
            'order_source' => 'shop_owner',
            'state' => 'approved',
            'delivery_status' => 'delivered',
            'payment_status' => 'paid',
            'shop_daily_order_key' => 'shop:'.$this->shop->id.':2026-08-25',
            'created_by' => $this->admin->id,
            'submitted_at' => '2026-08-25 08:00:00',
            'deadline_at' => '2026-08-25 10:00:00',
        ]);
        $invoice2 = ShopInvoice::create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order2->id,
            'invoice_number' => 'INV-POT-002',
            'business_date' => '2026-08-25',
            'final_total' => 400.00,
            'generated_by' => $this->admin->id,
        ]);
        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice2->id,
            'product_id' => $potato->id,
            'product_name' => $potato->name,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 8,
            'price_quantity' => 8,
            'delivered_qty' => 8,
            'delivered_price_quantity' => 8,
            'unit_price' => 50.00,
            'line_subtotal' => 400.00,
            'final_line_total' => 400.00,
        ]);

        // Create a product filter containing Tomato only
        $filter = PurchaseProductFilter::create([
            'name' => 'Red Veggies',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
        PurchaseProductFilterItem::create([
            'filter_id' => $filter->id,
            'product_id' => $tomato->id,
        ]);

        // When filter is applied, only Invoice 1 (Tomato) should appear
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.gl-bills', [
            'shop_id' => $this->shop->id,
            'timeframe' => 'custom',
            'start_date' => '2026-08-24',
            'end_date' => '2026-08-25',
            'product_filter' => $filter->uuid,
        ]));

        $response->assertOk()
            ->assertSee('INV-TOM-001')
            ->assertDontSee('INV-POT-002')
            ->assertSee('Red Veggies');
    }

    public function test_gl_bills_empty_state_when_product_filter_matches_no_invoices(): void
    {
        $potato = $this->createProduct('Potato', 'POT-01');

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-25',
            'order_number' => 'RQ-DT-004',
            'order_source' => 'shop_owner',
            'state' => 'approved',
            'delivery_status' => 'delivered',
            'payment_status' => 'paid',
            'shop_daily_order_key' => 'shop:'.$this->shop->id.':2026-08-25',
            'created_by' => $this->admin->id,
            'submitted_at' => '2026-08-25 08:00:00',
            'deadline_at' => '2026-08-25 10:00:00',
        ]);
        $invoice = ShopInvoice::create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'INV-POT-001',
            'business_date' => '2026-08-25',
            'final_total' => 200.00,
            'generated_by' => $this->admin->id,
        ]);
        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $potato->id,
            'product_name' => $potato->name,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 4,
            'price_quantity' => 4,
            'delivered_qty' => 4,
            'delivered_price_quantity' => 4,
            'unit_price' => 50.00,
            'line_subtotal' => 200.00,
            'final_line_total' => 200.00,
        ]);

        $filter = PurchaseProductFilter::create([
            'name' => 'Exotics Only',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.finance.gl-bills', [
            'shop_id' => $this->shop->id,
            'timeframe' => 'custom',
            'start_date' => '2026-08-25',
            'end_date' => '2026-08-25',
            'product_filter' => $filter->uuid,
        ]));

        $response->assertOk()
            ->assertSee('No GL invoices matching product filter')
            ->assertSee('Exotics Only')
            ->assertDontSee('INV-POT-001');
    }

    public function test_gl_bills_csv_export_respects_shop_date_and_product_filter(): void
    {
        $tomato = $this->createProduct('Tomato', 'TOM-01');
        $potato = $this->createProduct('Potato', 'POT-01');

        $matchingInvoice = $this->createInvoiceWithItem('INV-TOM-CSV', '2026-08-24', $tomato, 300.00, 500.00);
        $this->createInvoiceWithItem('INV-POT-CSV', '2026-08-25', $potato, 400.00, 400.00);

        $filter = PurchaseProductFilter::create([
            'name' => 'Tomato Filter',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
        PurchaseProductFilterItem::create([
            'filter_id' => $filter->id,
            'product_id' => $tomato->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills.export.csv', [
            'shop_id' => $this->shop->id,
            'timeframe' => 'custom',
            'start_date' => '2026-08-24',
            'end_date' => '2026-08-25',
            'product_filter' => $filter->uuid,
        ]));
        ob_start();
        $response->baseResponse->sendContent();
        $content = (string) ob_get_clean();

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('Tomato Filter', $content);
        $this->assertStringContainsString($matchingInvoice->invoice_number, $content);
        $this->assertStringContainsString('300', $content);
        $this->assertStringContainsString('500', $content);
        $this->assertStringNotContainsString('INV-POT-CSV', $content);
    }

    public function test_gl_bills_pdf_export_respects_filters(): void
    {
        $tomato = $this->createProduct('Tomato', 'TOM-01');
        $this->createInvoiceWithItem('INV-TOM-PDF', '2026-08-24', $tomato, 300.00, 300.00);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills.export.pdf', [
            'shop_id' => $this->shop->id,
            'timeframe' => 'custom',
            'start_date' => '2026-08-24',
            'end_date' => '2026-08-24',
            'download' => 1,
        ]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename=gl-bills-DT-01-2026-08-24-to-2026-08-24.pdf');
    }

    public function test_gl_bills_csv_export_without_shop_exports_all_owned_shops(): void
    {
        $tomato = $this->createProduct('Tomato', 'TOM-01');
        $secondShop = Shop::factory()->create([
            'name' => 'Uptown Fresh',
            'code' => 'UT-01',
            'client_id' => Client::query()->create([
                'code' => 'CL-02',
                'name' => 'Second Client',
                'status' => 'active',
            ])->id,
            'status' => 'active',
            'accounting_enabled' => true,
        ]);

        $this->createInvoiceWithItem('INV-DT-ALL', '2026-08-24', $tomato, 300.00, 300.00);
        $this->createInvoiceWithItem('INV-UT-ALL', '2026-08-25', $tomato, 450.00, 450.00, $secondShop);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills.export.csv', [
            'timeframe' => 'custom',
            'start_date' => '2026-08-24',
            'end_date' => '2026-08-25',
        ]));
        ob_start();
        $response->baseResponse->sendContent();
        $content = (string) ob_get_clean();

        $response->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=gl-bills-all-shops-2026-08-24-to-2026-08-25.csv');

        $this->assertStringContainsString('All Outlets', $content);
        $this->assertStringContainsString('INV-DT-ALL', $content);
        $this->assertStringContainsString('INV-UT-ALL', $content);
        $this->assertStringContainsString('750', $content);
    }

    public function test_gl_bills_pdf_print_view_shows_filters_and_shop_sections(): void
    {
        $tomato = $this->createProduct('Tomato', 'TOM-01');
        $secondShop = Shop::factory()->create([
            'name' => 'Uptown Fresh',
            'code' => 'UT-01',
            'client_id' => Client::query()->create([
                'code' => 'CL-03',
                'name' => 'Third Client',
                'status' => 'active',
            ])->id,
            'status' => 'active',
            'accounting_enabled' => true,
        ]);
        $filter = PurchaseProductFilter::create([
            'name' => 'Professional Tomato Filter',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
        PurchaseProductFilterItem::create([
            'filter_id' => $filter->id,
            'product_id' => $tomato->id,
        ]);

        $this->createInvoiceWithItem('INV-DT-PRINT', '2026-08-24', $tomato, 300.00, 300.00);
        $this->createInvoiceWithItem('INV-UT-PRINT', '2026-08-25', $tomato, 450.00, 450.00, $secondShop);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills.export.pdf', [
            'timeframe' => 'custom',
            'start_date' => '2026-08-24',
            'end_date' => '2026-08-25',
            'product_filter' => $filter->uuid,
        ]));

        $response->assertOk()
            ->assertSee('Outlet scope')
            ->assertSee('Product filter')
            ->assertSee('Professional Tomato Filter')
            ->assertSee('Downtown Fresh')
            ->assertSee('Uptown Fresh')
            ->assertSee('INV-DT-PRINT')
            ->assertSee('INV-UT-PRINT');
    }

    public function test_gl_bills_shows_all_shops_in_dropdown_including_non_client_shops(): void
    {
        $nonClientShop = Shop::factory()->create([
            'name' => 'Independent Retailer',
            'code' => 'IND-01',
            'client_id' => null,
            'accounting_mode' => 'direct_buyer',
            'accounting_enabled' => false,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills'));

        $response->assertOk()
            ->assertSee('Downtown Fresh')
            ->assertSee('Independent Retailer')
            ->assertSee('All Outlets');
    }

    public function test_gl_bills_applies_date_and_product_filters_without_shop_id_selection(): void
    {
        $tomato = $this->createProduct('Tomato', 'TOM-01');
        $potato = $this->createProduct('Potato', 'POT-01');

        $otherShop = Shop::factory()->create([
            'name' => 'Suburban Market',
            'code' => 'SUB-01',
            'client_id' => null,
            'status' => 'active',
        ]);

        // Invoice 1: Shop 1 with Tomato (matches filter)
        $inv1 = $this->createInvoiceWithItem('INV-ALL-TOM-1', '2026-08-15', $tomato, 350.00, 350.00, $this->shop);
        // Invoice 2: Shop 2 with Tomato (matches filter)
        $inv2 = $this->createInvoiceWithItem('INV-ALL-TOM-2', '2026-08-20', $tomato, 450.00, 450.00, $otherShop);
        // Invoice 3: Shop 1 with Potato (does not match product filter)
        $this->createInvoiceWithItem('INV-ALL-POT-3', '2026-08-18', $potato, 200.00, 200.00, $this->shop);
        // Invoice 4: Outside date range
        $this->createInvoiceWithItem('INV-ALL-OUT-4', '2026-07-20', $tomato, 500.00, 500.00, $this->shop);

        $filter = PurchaseProductFilter::create([
            'name' => 'Tomato Filter',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
        PurchaseProductFilterItem::create([
            'filter_id' => $filter->id,
            'product_id' => $tomato->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills', [
            'timeframe' => 'custom',
            'shop_id' => '',
            'product_filter' => $filter->uuid,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]));

        $response->assertOk()
            ->assertSee('INV-ALL-TOM-1')
            ->assertSee('INV-ALL-TOM-2')
            ->assertDontSee('INV-ALL-POT-3')
            ->assertDontSee('INV-ALL-OUT-4')
            ->assertSee('Downtown Fresh')
            ->assertSee('Suburban Market')
            ->assertSee('800.00'); // 350 + 450 total billed
    }

    public function test_gl_bills_scope_filter_own_direct_all(): void
    {
        $product = $this->createProduct('Cucumber', 'CUC-01');

        $directShop = Shop::factory()->create([
            'name' => 'Direct Quick Mart',
            'code' => 'DIR-QM',
            'client_id' => null,
            'status' => 'active',
            'accounting_enabled' => false,
        ]);

        $this->createInvoiceWithItem('INV-OWN-01', '2026-08-20', $product, 500.00, 500.00, $this->shop);
        $this->createInvoiceWithItem('INV-DIR-01', '2026-08-21', $product, 1200.00, 1200.00, $directShop);

        // Scope: All
        $resAll = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills', [
            'scope' => 'all',
            'timeframe' => 'custom',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]));
        $resAll->assertOk()
            ->assertSee('INV-OWN-01')
            ->assertSee('INV-DIR-01');
        $totalsAll = $resAll->viewData('totals');
        $this->assertEquals(1700.00, $totalsAll['total_billed']);
        $this->assertEquals(2, $totalsAll['count']);

        // Scope: Owned (Own)
        $resOwn = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills', [
            'scope' => 'owned',
            'timeframe' => 'custom',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]));
        $resOwn->assertOk()
            ->assertSee('INV-OWN-01')
            ->assertDontSee('INV-DIR-01');
        $totalsOwn = $resOwn->viewData('totals');
        $this->assertEquals(500.00, $totalsOwn['total_billed']);
        $this->assertEquals(1, $totalsOwn['count']);

        // Scope: Direct
        $resDir = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills', [
            'scope' => 'direct',
            'timeframe' => 'custom',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]));
        $resDir->assertOk()
            ->assertSee('INV-DIR-01')
            ->assertDontSee('INV-OWN-01');
        $totalsDir = $resDir->viewData('totals');
        $this->assertEquals(1200.00, $totalsDir['total_billed']);
        $this->assertEquals(1, $totalsDir['count']);
    }

    public function test_gl_bills_scope_narrows_shop_dropdown_and_resets_incompatible_selection(): void
    {
        $directShop = Shop::factory()->create([
            'name' => 'Direct Quick Mart',
            'code' => 'DIR-QM',
            'client_id' => null,
            'status' => 'active',
            'accounting_enabled' => false,
        ]);

        // When scope=direct, $shops in view should only contain direct shops
        $resDir = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills', [
            'scope' => 'direct',
        ]));
        $resDir->assertOk();
        $scopedShops = $resDir->viewData('shops');
        $this->assertTrue($scopedShops->contains('id', $directShop->id));
        $this->assertFalse($scopedShops->contains('id', $this->shop->id));

        // When scope=direct but an owned shop_id is passed, it must reset selectedShopId visibly
        $resIncompatible = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills', [
            'scope' => 'direct',
            'shop_id' => $this->shop->id,
        ]));
        $resIncompatible->assertOk();
        $this->assertNull($resIncompatible->viewData('selectedShopId'));
        $this->assertNull($resIncompatible->viewData('selectedShop'));
    }

    public function test_gl_bills_exports_honor_scope_filter(): void
    {
        $product = $this->createProduct('Capsicum', 'CAP-01');

        $directShop = Shop::factory()->create([
            'name' => 'Direct Quick Mart',
            'code' => 'DIR-QM',
            'client_id' => null,
            'status' => 'active',
            'accounting_enabled' => false,
        ]);

        $this->createInvoiceWithItem('INV-EXP-OWN', '2026-08-10', $product, 750.00, 750.00, $this->shop);
        $this->createInvoiceWithItem('INV-EXP-DIR', '2026-08-11', $product, 1500.00, 1500.00, $directShop);

        // CSV Export with Direct scope
        $csvResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills.export.csv', [
            'scope' => 'direct',
            'timeframe' => 'custom',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]));
        $csvResponse->assertOk();
        $csvContent = $csvResponse->streamedContent();
        $this->assertStringContainsString('INV-EXP-DIR', $csvContent);
        $this->assertStringNotContainsString('INV-EXP-OWN', $csvContent);

        // PDF Export with Direct scope
        $pdfResponse = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.gl-bills.export.pdf', [
            'scope' => 'direct',
            'timeframe' => 'custom',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]));
        $pdfResponse->assertOk();
    }

    private function createProduct(string $name, string $sku): Product
    {
        return Product::factory()->create([
            'name' => $name,
            'sku' => $sku,
            'category_id' => $this->category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'unit' => 'kg',
        ]);
    }

    private function createInvoiceWithItem(
        string $invoiceNumber,
        string $businessDate,
        Product $product,
        float $lineTotal,
        float $invoiceTotal,
        ?Shop $shop = null
    ): ShopInvoice {
        $shop ??= $this->shop;

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'business_date' => $businessDate,
            'order_number' => 'RQ-'.$invoiceNumber,
            'order_source' => 'shop_owner',
            'state' => 'approved',
            'delivery_status' => 'delivered',
            'payment_status' => 'paid',
            'shop_daily_order_key' => 'shop:'.$shop->id.':'.$businessDate.':'.$invoiceNumber,
            'created_by' => $this->admin->id,
            'submitted_at' => $businessDate.' 08:00:00',
            'deadline_at' => $businessDate.' 10:00:00',
        ]);

        $invoice = ShopInvoice::create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'business_date' => $businessDate,
            'final_total' => $invoiceTotal,
            'paid_amount' => $invoiceTotal,
            'balance_amount' => 0.00,
            'generated_by' => $this->admin->id,
        ]);

        ShopInvoiceItem::create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 6,
            'price_quantity' => 6,
            'delivered_qty' => 6,
            'delivered_price_quantity' => 6,
            'unit_price' => $lineTotal / 6,
            'line_subtotal' => $lineTotal,
            'final_line_total' => $lineTotal,
        ]);

        return $invoice;
    }
}
