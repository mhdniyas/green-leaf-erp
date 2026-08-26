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
}
