<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Purchasing\InvoiceStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private User $unauthorizedUser;

    private Supplier $supplier;

    private Product $product;

    private GoodsReceived $grn;

    private PurchaseOrder $po;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        // User with accountant / accounting permissions
        $this->accountant = User::factory()->create();
        $this->accountant->givePermissionTo([
            'accounting.ledger.view',
            'accounting.entry.create',
            'purchasing.order.view',
            'purchasing.grn.view',
        ]);

        $this->unauthorizedUser = User::factory()->create();

        $this->supplier = Supplier::factory()->create();
        $category = Category::factory()->create();
        $this->product = Product::factory()->create(['category_id' => $category->id]);

        $this->po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::Approved,
            'created_by' => $this->accountant->id,
        ]);
        $this->po->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 50.000,
            'unit_price' => 4.0000,
        ]);

        $this->grn = GoodsReceived::factory()->create([
            'purchase_order_id' => $this->po->id,
            'received_by' => $this->accountant->id,
        ]);
        $this->grn->items()->create([
            'purchase_order_item_id' => $this->po->items->first()->id,
            'product_id' => $this->product->id,
            'received_qty' => 50.000,
            'variance' => 0.000,
        ]);
    }

    public function test_accountant_can_view_purchase_invoices_list(): void
    {
        $invoice = PurchaseInvoice::factory()->create([
            'goods_received_id' => $this->grn->id,
            'supplier_id' => $this->supplier->id,
        ]);

        $response = $this->actingAs($this->accountant)
            ->get(route('purchasing.invoices.index'));

        $response->assertOk();
        $response->assertSee($invoice->invoice_number);
    }

    public function test_purchase_invoice_report_groups_results_by_vendor_and_filters_payment_type(): void
    {
        $matchingVendor = Supplier::factory()->create([
            'name' => 'City Vendor',
            'mobile_number' => '9876543210',
        ]);
        $otherVendor = Supplier::factory()->create([
            'name' => 'Credit Vendor',
            'mobile_number' => '9999999999',
        ]);

        $matchingInvoice = PurchaseInvoice::factory()->create([
            'goods_received_id' => $this->grn->id,
            'supplier_id' => $matchingVendor->id,
            'invoice_number' => 'PINV-GPAY-1001',
            'payment_method' => 'GPay',
            'paid_amount' => 200.00,
            'payment_status' => 'paid',
            'created_at' => now(),
        ]);

        PurchaseInvoice::factory()->create([
            'goods_received_id' => $this->grn->id,
            'supplier_id' => $otherVendor->id,
            'invoice_number' => 'PINV-CREDIT-1001',
            'payment_method' => 'Credit',
            'payment_status' => 'credit_pending_approval',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->accountant)->get(route('purchasing.invoices.index', [
            'date' => now()->format('Y-m-d'),
            'search' => 'City Vendor',
            'payment_type' => 'gpay',
        ]));

        $response->assertOk();
        $response->assertSee('Vendor Finance Report');
        $response->assertSee('City Vendor');
        $response->assertSee($matchingInvoice->invoice_number);
        $response->assertDontSee('Credit Vendor');
        $response->assertDontSee('PINV-CREDIT-1001');
    }

    public function test_unauthorized_user_cannot_view_purchase_invoices_list(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('purchasing.invoices.index'));

        $response->assertForbidden();
    }

    public function test_accountant_can_see_create_invoice_page_with_grn(): void
    {
        $response = $this->actingAs($this->accountant)
            ->get(route('purchasing.invoices.create', ['goods_received' => $this->grn]));

        $response->assertOk();
        $response->assertSee($this->grn->grn_number);
        $response->assertSee('Generated with timestamp');
        $this->assertStringContainsString('goods_received='.$this->grn->public_uuid, route('purchasing.invoices.create', ['goods_received' => $this->grn]));
    }

    public function test_accountant_can_store_purchase_invoice_and_closes_purchase_order(): void
    {
        // 50kg * INR 4/kg = INR 200.00 expected amount
        $invoiceData = [
            'goods_received_id' => $this->grn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-SUPP-999',
            'amount' => 200.00,
            'status' => 'pending',
            'notes' => 'Matching invoice for testing',
        ];

        $response = $this->actingAs($this->accountant)
            ->post(route('purchasing.invoices.store'), $invoiceData);

        $invoice = PurchaseInvoice::latest('id')->first();
        $response->assertRedirect(route('purchasing.invoices.show', $invoice));

        $this->assertDatabaseHas('purchase_invoices', [
            'id' => $invoice->id,
            'invoice_number' => 'INV-SUPP-999',
            'amount' => 200.00,
            'status' => 'pending',
        ]);

        // Purchase order should now be closed
        $this->po->refresh();
        $this->assertEquals(POStatus::Closed, $this->po->status);
    }

    public function test_accountant_cannot_store_duplicate_invoice_for_same_grn(): void
    {
        // First invoice
        PurchaseInvoice::factory()->create([
            'goods_received_id' => $this->grn->id,
            'supplier_id' => $this->supplier->id,
        ]);

        // Try creating a second one
        $response = $this->actingAs($this->accountant)
            ->get(route('purchasing.invoices.create', ['goods_received' => $this->grn]));

        $response->assertRedirect(route('purchasing.invoices.show', PurchaseInvoice::first()));
    }

    public function test_legacy_invoice_create_url_redirects_to_canonical_grn_reference(): void
    {
        $response = $this->actingAs($this->accountant)
            ->get(route('purchasing.invoices.create', ['goods_received_id' => $this->grn->id]));

        $response->assertRedirect(route('purchasing.invoices.create', ['goods_received' => $this->grn]));
    }

    public function test_invoice_show_route_uses_invoice_number_reference(): void
    {
        $invoice = PurchaseInvoice::factory()->create([
            'goods_received_id' => $this->grn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'PINV-20260606-095606123',
        ]);

        $this->assertStringContainsString('/purchasing/invoices/'.$invoice->public_uuid, route('purchasing.invoices.show', $invoice));
    }

    public function test_accountant_can_update_invoice_status(): void
    {
        $invoice = PurchaseInvoice::factory()->create([
            'goods_received_id' => $this->grn->id,
            'supplier_id' => $this->supplier->id,
            'status' => InvoiceStatus::Pending,
        ]);

        // Transition to approved
        $response = $this->actingAs($this->accountant)
            ->post(route('purchasing.invoices.update-status', $invoice), [
                'status' => 'approved',
            ]);

        $response->assertRedirect(route('purchasing.invoices.show', $invoice));
        $this->assertDatabaseHas('purchase_invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Approved->value,
        ]);

        // Transition to paid
        $response = $this->actingAs($this->accountant)
            ->post(route('purchasing.invoices.update-status', $invoice), [
                'status' => 'paid',
            ]);

        $response->assertRedirect(route('purchasing.invoices.show', $invoice));
        $this->assertDatabaseHas('purchase_invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Paid->value,
        ]);
    }

    public function test_accountant_can_open_purchase_invoice_pdf_view(): void
    {
        $invoice = PurchaseInvoice::factory()->create([
            'goods_received_id' => $this->grn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'PINV-PDF-1001',
            'amount' => 330.00,
            'discount_amount' => 12.00,
            'paid_amount' => 318.00,
            'payment_method' => 'Cash',
            'payment_status' => 'paid',
        ]);

        $this->actingAs($this->accountant)
            ->get(route('purchasing.invoices.pdf', $invoice))
            ->assertOk()
            ->assertSee('PINV-PDF-1001')
            ->assertSee('Rs. 330.00')
            ->assertSee('Rs. 318.00')
            ->assertSee('Rs. 12.00')
            ->assertSee('Rs. 0.00');
    }

    public function test_purchase_invoice_report_uses_discounted_balance(): void
    {
        $invoice = PurchaseInvoice::factory()->create([
            'goods_received_id' => $this->grn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'PINV-DISCOUNT-BALANCE-1001',
            'amount' => 330.00,
            'discount_amount' => 12.00,
            'paid_amount' => 300.00,
            'payment_method' => 'Cash',
            'payment_status' => 'partial',
            'created_at' => now(),
        ]);

        $this->actingAs($this->accountant)
            ->get(route('purchasing.invoices.index', [
                'date' => now()->format('Y-m-d'),
                'search' => $this->supplier->name,
            ]))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('₹18.00');
    }

    public function test_accountant_can_view_vendor_finance_report_page(): void
    {
        $invoiceA = PurchaseInvoice::factory()->create([
            'goods_received_id' => $this->grn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'PINV-VENDOR-1001',
            'amount' => 250.00,
            'paid_amount' => 100.00,
            'payment_method' => 'Cash',
            'payment_status' => 'partial',
            'created_at' => now()->subDay(),
        ]);

        $invoiceB = PurchaseInvoice::factory()->create([
            'goods_received_id' => $this->grn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'PINV-VENDOR-1002',
            'amount' => 300.00,
            'paid_amount' => 300.00,
            'payment_method' => 'GPay',
            'payment_status' => 'paid',
            'created_at' => now(),
        ]);

        $this->actingAs($this->accountant)
            ->get(route('purchasing.invoices.vendor-report', $this->supplier))
            ->assertOk()
            ->assertSee($this->supplier->name)
            ->assertSee('Finance History')
            ->assertSee($invoiceA->invoice_number)
            ->assertSee($invoiceB->invoice_number);
    }

    public function test_accountant_can_view_purchase_invoice_details_page(): void
    {
        $invoice = PurchaseInvoice::factory()->create([
            'goods_received_id' => $this->grn->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'PINV-SHOW-1001',
        ]);

        $this->actingAs($this->accountant)
            ->get(route('purchasing.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('PINV-SHOW-1001')
            ->assertSee('Line Items')
            ->assertSee('Open Bill');
    }

    public function test_accountant_can_update_invoice_payment_with_gpay(): void
    {
        $invoice = PurchaseInvoice::factory()->create([
            'goods_received_id' => $this->grn->id,
            'supplier_id' => $this->supplier->id,
            'amount' => 200.00,
            'paid_amount' => 0,
            'payment_method' => null,
            'payment_status' => 'unpaid',
        ]);

        $response = $this->actingAs($this->accountant)
            ->patch(route('purchasing.invoices.update-payment', $invoice), [
                'payment_method' => 'GPay',
                'paid_amount' => 200,
                'payment_note' => 'Settled by GPay',
                'payment_details' => 'Ref: GP-9001',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('purchase_invoices', [
            'id' => $invoice->id,
            'payment_method' => 'GPay',
            'payment_status' => 'paid',
            'paid_amount' => 200.00,
        ]);
    }
}
