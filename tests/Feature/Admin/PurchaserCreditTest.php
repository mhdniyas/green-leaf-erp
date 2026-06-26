<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Purchasing\InvoiceStatus;
use App\Enums\Purchasing\POStatus;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\PurchaserCredit;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaseInvoiceService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaserCreditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');
    }

    public function test_admin_can_view_purchasers_ledger_index_and_details(): void
    {
        // Add some credits
        PurchaserCredit::create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 1000.00,
            'description' => 'Advance cash',
            'created_by' => $this->admin->id,
            'business_date' => today(),
        ]);

        PurchaserCredit::create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'out',
            'amount' => 300.00,
            'description' => 'Buying items',
            'created_by' => $this->purchaser->id,
            'business_date' => today(),
        ]);

        // Access index as non-admin
        $this->actingAs($this->purchaser)
            ->get(route('admin.accounting.purchasers.index'))
            ->assertForbidden();

        // Access index as admin
        $this->actingAs($this->admin)
            ->get(route('admin.accounting.purchasers.index'))
            ->assertOk()
            ->assertSee($this->purchaser->name)
            ->assertSee('₹1,000.00')
            ->assertSee('₹300.00')
            ->assertSee('₹700.00');

        // Access show as admin
        $this->actingAs($this->admin)
            ->get(route('admin.accounting.purchasers.show', $this->purchaser))
            ->assertOk()
            ->assertSee('Advance cash')
            ->assertSee('Buying items')
            ->assertSee('₹700.00');
    }

    public function test_admin_can_store_purchaser_credit(): void
    {
        $payload = [
            'amount' => 1500.50,
            'description' => 'Fuel & buying advance',
            'business_date' => '2026-06-26',
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.accounting.purchasers.credits.store', $this->purchaser), $payload)
            ->assertRedirect(route('admin.accounting.purchasers.show', $this->purchaser))
            ->assertSessionHas('success', 'Credit added successfully.');

        $this->assertDatabaseHas('purchaser_credits', [
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 1500.50,
            'description' => 'Fuel & buying advance',
            'created_by' => $this->admin->id,
        ]);

        $credit = PurchaserCredit::where('purchaser_id', $this->purchaser->id)->where('type', 'in')->firstOrFail();
        $this->assertSame('2026-06-26', $credit->business_date->format('Y-m-d'));
    }

    public function test_purchaser_can_view_own_credits_ledger(): void
    {
        PurchaserCredit::create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'in',
            'amount' => 500.00,
            'description' => 'Company cash',
            'created_by' => $this->admin->id,
            'business_date' => today(),
        ]);

        $anotherPurchaser = User::factory()->create();
        $anotherPurchaser->assignRole('purchaser');

        // Access own credits page
        $this->actingAs($this->purchaser)
            ->get(route('purchaser.credits'))
            ->assertOk()
            ->assertSee('Company cash')
            ->assertSee('₹500.00');

        // Non-purchaser forbidden
        $shopUser = User::factory()->create();
        $shopUser->assignRole('shop');
        $this->actingAs($shopUser)
            ->get(route('purchaser.credits'))
            ->assertForbidden();
    }

    public function test_cart_submission_automatically_creates_purchaser_credit_outflow(): void
    {
        $supplier = Supplier::create([
            'name' => 'Supplier A',
            'code' => 'SUP-A',
            'type' => 'Vendor',
            'category' => 'market',
            'status' => 'active',
            'credit_approved' => true,
        ]);

        $product = Product::factory()->create();

        $cart = PurchaserCart::create([
            'user_id' => $this->purchaser->id,
            'cart_number' => PurchaserCart::generateCartNumber(today()),
            'status' => 'draft',
            'business_date' => today(),
        ]);

        PurchaserCartItem::create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 50.00,
            'line_total' => 500.00,
        ]);

        $payload = [
            'cart_id' => $cart->id,
            'business_date' => today()->toDateString(),
            'supplier_id' => $supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'discount_amount' => 50.00,
            'items' => [
                $cart->items[0]->id => [
                    'unit_price' => 50.00,
                ],
            ],
        ];

        // Submit the cart
        $this->actingAs($this->purchaser)
            ->post(route('purchaser.carts.submit'), $payload)
            ->assertRedirect();

        // Total payable is 500.00 - 50.00 = 450.00
        $this->assertDatabaseHas('purchaser_credits', [
            'purchaser_id' => $this->purchaser->id,
            'type' => 'out',
            'amount' => 450.00,
        ]);

        $credit = PurchaserCredit::where('purchaser_id', $this->purchaser->id)->where('type', 'out')->firstOrFail();
        $this->assertSame(today()->toDateString(), $credit->business_date->format('Y-m-d'));
    }

    public function test_updating_purchase_invoice_payment_updates_purchaser_credit(): void
    {
        $supplier = Supplier::create([
            'name' => 'Supplier B',
            'code' => 'SUP-B',
            'type' => 'Vendor',
            'category' => 'market',
            'status' => 'active',
            'credit_approved' => true,
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => POStatus::Approved,
        ]);
        $grn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
        ]);

        $invoice = PurchaseInvoice::create([
            'invoice_number' => 'INV-001',
            'goods_received_id' => $grn->id,
            'supplier_id' => $supplier->id,
            'amount' => 1000.00,
            'status' => InvoiceStatus::Pending,
            'payment_method' => 'Cash',
            'payment_status' => 'unpaid',
            'paid_amount' => 0.00,
            'purchaser_submitted_by' => $this->purchaser->id,
            'purchaser_submitted_at' => now(),
        ]);

        // Create initial credit out
        PurchaserCredit::create([
            'purchaser_id' => $this->purchaser->id,
            'type' => 'out',
            'amount' => 1000.00,
            'description' => 'Debit for invoice: INV-001',
            'purchase_invoice_id' => $invoice->id,
            'created_by' => $this->purchaser->id,
            'business_date' => today(),
        ]);

        // Update payment via service (which is what both controllers call)
        $service = app(PurchaseInvoiceService::class);
        $service->updatePayment($invoice, [
            'payment_method' => 'Cash',
            'discount_amount' => 150.00,
            'paid_amount' => 850.00,
            'payment_note' => 'Paid with discount',
            'payment_details' => 'Ref-123',
            'bill_number' => 'INV-001-UPDATED',
        ]);

        // Total should be net 1000.00 - 150.00 = 850.00
        $this->assertDatabaseHas('purchaser_credits', [
            'purchase_invoice_id' => $invoice->id,
            'type' => 'out',
            'amount' => 850.00,
            'description' => 'Debit for invoice: INV-001-UPDATED',
        ]);
    }
}
