<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopInvoiceShareBillTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::findOrCreate('admin');
        $adminRole->givePermissionTo([
            Permission::findOrCreate('purchasing.order.view'),
            Permission::findOrCreate('accounting.dashboard.view'),
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->shop = Shop::factory()->create([
            'name' => 'City Supermarket',
            'code' => 'CS-001',
        ]);
    }

    private function createTestInvoice(bool $isFinalized = true): ShopInvoice
    {
        $order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-10-03',
            'delivery_status' => $isFinalized ? 'delivered' : 'in_transit',
        ]);

        $apple = Product::factory()->create(['name' => 'Fuji Apple', 'unit' => 'kg']);
        $banana = Product::factory()->create(['name' => 'Robusta Banana', 'unit' => 'kg']);

        $orderItem1 = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $apple->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'loaded_qty' => 10.0,
            'delivered_qty' => 10.0,
            'shop_reported_received_qty' => 10.0,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_label' => 'KG',
            'requested_unit_quantity' => 10.0,
            'requested_unit_conversion_to_base' => 1,
            'unit_cost' => 80.0,
        ]);

        $orderItem2 = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $banana->id,
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'loaded_qty' => 5.0,
            'delivered_qty' => 5.0,
            'shop_reported_received_qty' => 5.0,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_label' => 'KG',
            'requested_unit_quantity' => 5.0,
            'requested_unit_conversion_to_base' => 1,
            'unit_cost' => 80.0,
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20261003-FDCTY',
            'business_date' => '2026-10-03',
            'subtotal' => 1500.00,
            'discount_total' => 50.00,
            'final_total' => 1450.00,
            'status' => $isFinalized ? 'finalized' : 'draft',
            'finalized_at' => $isFinalized ? now() : null,
            'finalized_by' => $isFinalized ? $this->admin->id : null,
        ]);

        ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem1->id,
            'product_id' => $apple->id,
            'product_name' => 'Fuji Apple',
            'unit' => 'kg',
            'price_unit' => 'kg',
            'unit_price' => 100.00,
            'delivered_qty' => 10.0,
            'delivered_price_quantity' => 10.0,
            'final_line_total' => 1000.00,
        ]);

        ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $orderItem2->id,
            'product_id' => $banana->id,
            'product_name' => 'Robusta Banana',
            'unit' => 'kg',
            'price_unit' => 'kg',
            'unit_price' => 100.00,
            'delivered_qty' => 5.0,
            'delivered_price_quantity' => 5.0,
            'final_line_total' => 500.00,
        ]);

        return $invoice;
    }

    public function test_shop_invoice_show_page_renders_download_pdf_button(): void
    {
        $invoice = $this->createTestInvoice();

        $response = $this->actingAs($this->admin)
            ->get(route('purchasing.shop-invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('Download PDF');
        $response->assertSee('City Supermarket');
        $response->assertSee('SINV-20261003-FDCTY');
        $response->assertSee('Fuji Apple');
        $response->assertSee('Robusta Banana');
        $response->assertSee('Rs. 1,450.00');
    }

    public function test_shop_invoice_pdf_view_renders_exact_invoice_card_and_download_action(): void
    {
        $invoice = $this->createTestInvoice();

        $response = $this->actingAs($this->admin)
            ->get(route('purchasing.shop-invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertSee('City Supermarket');
        $response->assertSee('SINV-20261003-FDCTY');
        $response->assertSee('FINALIZED BILL');
        $response->assertSee('Fuji Apple');
        $response->assertSee('Robusta Banana');
        $response->assertSee('Rs. 1,450.00');
        $response->assertSee('Download PDF');
    }

    public function test_shop_invoice_pdf_download_returns_pdf_file(): void
    {
        $invoice = $this->createTestInvoice();

        $response = $this->actingAs($this->admin)
            ->get(route('purchasing.shop-invoices.pdf', ['invoice' => $invoice, 'download' => 1]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_shop_invoice_pdf_download_with_category_filter_returns_filtered_pdf(): void
    {
        $invoice = $this->createTestInvoice();
        $bananaItem = $invoice->items->firstWhere('product_name', 'Robusta Banana');
        $this->assertNotNull($bananaItem);
        $categoryId = $bananaItem->product->category_id;

        $response = $this->actingAs($this->admin)
            ->get(route('purchasing.shop-invoices.pdf', [
                'invoice' => $invoice,
                'download' => 1,
                'category_ids' => $categoryId,
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_warehouse_loadout_order_pdf_api_endpoint_returns_pdf(): void
    {
        $invoice = $this->createTestInvoice();
        $order = $invoice->order;
        $this->assertNotNull($order);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/warehouse/loadout/{$order->order_number}/pdf");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_shared_bill_requires_valid_signed_signature(): void
    {
        $invoice = $this->createTestInvoice();

        // Direct unsigned access without signature must not allow viewing the invoice
        $unsignedUrl = url("/shared-bills/{$invoice->invoice_number}");
        $response = $this->get($unsignedUrl);
        $response->assertRedirect();
        $response->assertDontSee('Fuji Apple');

        // Tampered signature must not allow viewing the invoice
        $tamperedUrl = $unsignedUrl.'?signature=invalidsignature123';
        $responseTampered = $this->get($tamperedUrl);
        $responseTampered->assertRedirect();
        $responseTampered->assertDontSee('Fuji Apple');
    }

    public function test_shared_bill_with_valid_signature_renders_exact_invoice_without_auth(): void
    {
        $invoice = $this->createTestInvoice();

        $signedUrl = URL::signedRoute('shop-invoices.shared', ['invoice' => $invoice->invoice_number]);

        // Unauthenticated guest user accesses the signed link
        $response = $this->get($signedUrl);

        $response->assertOk();
        $response->assertSee('City Supermarket');
        $response->assertSee('SINV-20261003-FDCTY');
        $response->assertSee('FINALIZED BILL');
        $response->assertSee('Fuji Apple');
        $response->assertSee('Robusta Banana');
        $response->assertSee('Rs. 1,450.00');
        $response->assertSee('Print Bill');
        $response->assertSee('Share Bill');
        $response->assertSee('Download PDF');
        // Must NOT leak administrative edit forms or actions
        $response->assertDontSee('reopen-for-edit');
        $response->assertDontSee('data-item-edit');
    }

    public function test_shared_bill_pdf_download_with_valid_signature_returns_pdf(): void
    {
        $invoice = $this->createTestInvoice();

        $signedPdfUrl = URL::signedRoute('shop-invoices.shared.pdf', [
            'invoice' => $invoice->invoice_number,
            'download' => 1,
        ]);

        $response = $this->get($signedPdfUrl);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
