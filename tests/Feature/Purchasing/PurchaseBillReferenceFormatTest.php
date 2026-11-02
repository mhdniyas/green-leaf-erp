<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Purchasing\InvoiceStatus;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\GoodsReceivedItem;
use App\Models\Product;
use App\Models\PurchaseBusinessDay;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseBillReferenceFormatTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private Supplier $supplier;

    private Product $product;

    private Warehouse $warehouse;

    private PurchaseBusinessDay $businessDay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'purchaser']);

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->purchaser = User::factory()->create(['name' => 'Purchaser User']);
        $this->purchaser->assignRole('purchaser');

        $this->warehouse = Warehouse::create([
            'name' => 'Central Warehouse',
            'code' => 'WH-01',
            'is_active' => true,
        ]);

        $this->admin->warehouses()->attach($this->warehouse->id);
        $this->purchaser->warehouses()->attach($this->warehouse->id);

        $this->supplier = Supplier::factory()->create([
            'name' => 'Green Valley Supplier',
            'credit_approved' => true,
        ]);

        $category = Category::create([
            'name' => 'Fresh Produce',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Fresh Carrots',
            'sku' => 'CARROT-01',
            'unit' => 'kg',
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'is_active' => true,
        ]);

        $this->businessDay = PurchaseBusinessDay::create([
            'business_date' => '2026-10-31',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'open',
            'opened_by' => $this->admin->id,
            'opened_at' => now(),
        ]);

        BusinessSetting::updateOrCreate(
            ['key' => 'purchaser_business_day_verification_enabled'],
            ['value' => '0']
        );
    }

    public function test_generate_purchase_bill_number_uses_glpb_format_with_cart_suffix(): void
    {
        $date = Carbon::parse('2026-10-31');
        $cartNumber = 'VC-20261031-FC02';

        $generated = PurchaseInvoice::generatePurchaseBillNumber($date, $cartNumber);

        $this->assertSame('GLPB26-1031-FC02', $generated);
    }

    public function test_generate_purchase_bill_number_handles_collision_by_generating_unique_suffix(): void
    {
        $date = Carbon::parse('2026-10-31');
        $cartNumber = 'VC-20261031-FC02';

        // Pre-create invoice with GLPB26-1031-FC02
        PurchaseInvoice::factory()->create([
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'GLPB26-1031-FC02',
        ]);

        $generated = PurchaseInvoice::generatePurchaseBillNumber($date, $cartNumber);

        $this->assertNotSame('GLPB26-1031-FC02', $generated);
        $this->assertMatchesRegularExpression('/^GLPB26-1031-[A-Z0-9]{4}$/', $generated);
        $this->assertDatabaseMissing('purchase_invoices', ['invoice_number' => $generated]);
    }

    public function test_generate_purchase_bill_number_without_cart_generates_valid_glpb(): void
    {
        $date = Carbon::parse('2026-11-02');

        $generated = PurchaseInvoice::generatePurchaseBillNumber($date);

        $this->assertMatchesRegularExpression('/^GLPB26-1102-[A-Z0-9]{4}$/', $generated);
    }

    public function test_submitting_new_cart_without_bill_number_creates_glpb_format(): void
    {
        $cart = PurchaserCart::query()->create([
            'cart_number' => 'VC-20261031-FC02',
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-10-31',
            'status' => 'draft',
            'purchase_grade' => 'A',
            'purchase_source' => 'shop_order',
        ]);

        $cartItem = PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 10.0,
            'unit_price' => 50.00,
            'line_total' => 500.00,
            'grade' => 'A',
        ]);

        $response = $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'cart_id' => $cart->id,
            'business_date' => '2026-10-31',
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'bill_number' => '',
            'return_to' => 'vendors',
            'items' => [
                $cartItem->id => ['unit_price' => 50.00],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $invoice = PurchaseInvoice::query()->where('purchaser_cart_id', $cart->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('GLPB26-1031-FC02', $invoice->invoice_number);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
    }

    public function test_submitting_credit_cart_without_bill_number_creates_glpb_format(): void
    {
        $cart = PurchaserCart::query()->create([
            'cart_number' => 'VC-20261031-AB12',
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-10-31',
            'status' => 'draft',
            'purchase_grade' => 'A',
            'purchase_source' => 'shop_order',
        ]);

        $cartItem = PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 20.0,
            'unit_price' => 40.00,
            'line_total' => 800.00,
            'grade' => 'A',
        ]);

        $response = $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'cart_id' => $cart->id,
            'business_date' => '2026-10-31',
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Credit',
            'paid_amount' => 0.00,
            'bill_number' => '',
            'return_to' => 'vendors',
            'items' => [
                $cartItem->id => ['unit_price' => 40.00],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $invoice = PurchaseInvoice::query()->where('purchaser_cart_id', $cart->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('GLPB26-1031-AB12', $invoice->invoice_number);
        $this->assertSame(InvoiceStatus::Pending, $invoice->status);
        $this->assertSame('Credit', $invoice->payment_method);
    }

    public function test_user_provided_vendor_bill_number_is_preserved_when_specified(): void
    {
        $cart = PurchaserCart::query()->create([
            'cart_number' => 'VC-20261031-CD34',
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-10-31',
            'status' => 'draft',
            'purchase_grade' => 'A',
            'purchase_source' => 'shop_order',
        ]);

        $cartItem = PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 10.0,
            'unit_price' => 50.00,
            'line_total' => 500.00,
            'grade' => 'A',
        ]);

        $response = $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'cart_id' => $cart->id,
            'business_date' => '2026-10-31',
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'bill_number' => 'VENDOR-BILL-999',
            'return_to' => 'vendors',
            'items' => [
                $cartItem->id => ['unit_price' => 50.00],
            ],
        ]);

        $response->assertRedirect();
        $invoice = PurchaseInvoice::query()->where('purchaser_cart_id', $cart->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('VENDOR-BILL-999', $invoice->invoice_number);
    }

    public function test_historical_bill_number_remains_untouched_and_viewable(): void
    {
        $cart = PurchaserCart::query()->create([
            'cart_number' => 'VC-20261031-FC02',
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-10-31',
            'status' => 'submitted',
            'purchase_grade' => 'A',
            'purchase_source' => 'shop_order',
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 10.0,
            'unit_price' => 50.00,
            'line_total' => 500.00,
            'grade' => 'A',
        ]);

        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-HIST-001',
            'status' => 'pending_approval',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->purchaser->id,
            'received_at' => now(),
        ]);

        $historicalInvoice = PurchaseInvoice::create([
            'goods_received_id' => $grn->id,
            'supplier_id' => $this->supplier->id,
            'purchaser_cart_id' => $cart->id,
            'purchase_source' => 'shop_order',
            'invoice_number' => 'PENDING-BILL-VC-20261031-FC02',
            'amount' => 500.00,
            'discount_amount' => 0.00,
            'status' => InvoiceStatus::Paid,
            'payment_method' => 'Cash',
            'payment_paid_by' => 'purchaser',
            'payment_status' => 'paid',
            'paid_amount' => 500.00,
            'purchaser_submitted_by' => $this->purchaser->id,
            'purchaser_submitted_at' => now(),
        ]);

        $cart->update(['purchase_invoice_id' => $historicalInvoice->id]);

        // Historical record in DB must not have been modified
        $this->assertSame('PENDING-BILL-VC-20261031-FC02', $historicalInvoice->fresh()->invoice_number);

        // Details modal endpoint
        $detailsResponse = $this->actingAs($this->purchaser)->getJson("/purchaser/history/{$cart->id}/details");
        $detailsResponse->assertOk();
        $detailsResponse->assertJsonPath('modalPayload.invoiceNumber', 'PENDING-BILL-VC-20261031-FC02');

        // Show page
        $showResponse = $this->actingAs($this->purchaser)->get(route('purchaser.invoices.show', $historicalInvoice));
        $showResponse->assertOk();
        $showResponse->assertSee('PENDING-BILL-VC-20261031-FC02');

        // PDF page
        $pdfResponse = $this->actingAs($this->purchaser)->get(route('purchaser.invoices.pdf', $historicalInvoice));
        $pdfResponse->assertOk();
        $pdfResponse->assertSee('PENDING-BILL-VC-20261031-FC02');
    }

    public function test_new_glpb_bill_displays_correctly_in_views_and_pdf(): void
    {
        $cart = PurchaserCart::query()->create([
            'cart_number' => 'VC-20261031-FC02',
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-10-31',
            'status' => 'submitted',
            'purchase_grade' => 'A',
            'purchase_source' => 'shop_order',
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 10.0,
            'unit_price' => 50.00,
            'line_total' => 500.00,
            'grade' => 'A',
        ]);

        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-NEW-001',
            'status' => 'pending_approval',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->purchaser->id,
            'received_at' => now(),
        ]);

        $newInvoice = PurchaseInvoice::create([
            'goods_received_id' => $grn->id,
            'supplier_id' => $this->supplier->id,
            'purchaser_cart_id' => $cart->id,
            'purchase_source' => 'shop_order',
            'invoice_number' => 'GLPB26-1031-FC02',
            'amount' => 500.00,
            'discount_amount' => 0.00,
            'status' => InvoiceStatus::Paid,
            'payment_method' => 'Cash',
            'payment_paid_by' => 'purchaser',
            'payment_status' => 'paid',
            'paid_amount' => 500.00,
            'purchaser_submitted_by' => $this->purchaser->id,
            'purchaser_submitted_at' => now(),
        ]);

        $cart->update(['purchase_invoice_id' => $newInvoice->id]);

        // Details modal endpoint
        $detailsResponse = $this->actingAs($this->purchaser)->getJson("/purchaser/history/{$cart->id}/details");
        $detailsResponse->assertOk();
        $detailsResponse->assertJsonPath('modalPayload.invoiceNumber', 'GLPB26-1031-FC02');

        // Show page
        $showResponse = $this->actingAs($this->purchaser)->get(route('purchaser.invoices.show', $newInvoice));
        $showResponse->assertOk();
        $showResponse->assertSee('GLPB26-1031-FC02');

        // PDF page
        $pdfResponse = $this->actingAs($this->purchaser)->get(route('purchaser.invoices.pdf', $newInvoice));
        $pdfResponse->assertOk();
        $pdfResponse->assertSee('GLPB26-1031-FC02');
    }

    public function test_receiving_workflow_and_search_operate_with_glpb_references(): void
    {
        $grn = GoodsReceived::create([
            'grn_number' => 'GRN-TEST-001',
            'status' => 'pending_approval',
            'receipt_type' => 'normal_purchase',
            'received_by' => $this->admin->id,
            'received_at' => now(),
        ]);

        GoodsReceivedItem::create([
            'goods_received_id' => $grn->id,
            'product_id' => $this->product->id,
            'received_qty' => 50.0,
            'variance' => 0.0,
            'grade' => 'A',
        ]);

        $invoice = PurchaseInvoice::create([
            'goods_received_id' => $grn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'GLPB26-1031-FC02',
            'amount' => 2500.0,
            'status' => 'pending',
            'payment_method' => 'Credit',
            'payment_paid_by' => 'vendor_credit',
            'purchaser_submitted_by' => $this->purchaser->id,
        ]);

        // Verify search by new format
        $searchResult = PurchaseInvoice::query()
            ->where('invoice_number', 'like', '%GLPB26-1031%')
            ->first();

        $this->assertNotNull($searchResult);
        $this->assertSame($invoice->id, $searchResult->id);

        // Receive goods through warehouse pending bills receiving endpoint
        $receiveResponse = $this->actingAs($this->admin)->postJson(
            route('admin.cashbook.reports-v1.pending-bills.receive-goods', $invoice),
            ['warehouse_id' => $this->warehouse->id]
        );

        $receiveResponse->assertOk();
        $receiveResponse->assertJsonPath('success', true);
        $this->assertStringContainsString('GLPB26-1031-FC02', (string) $receiveResponse->json('message'));
    }
}
