<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Purchasing\InvoiceStatus;
use App\Models\GoodsReceived;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoicePayment;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\PurchaserCredit;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Comprehensive test verifying:
 * 1. Admin fix updates all cart items, invoice, cart, and purchaser credit
 * 2. No new payment history rows are created
 * 3. Bulk fix-all endpoint works
 * 4. Non-admin is rejected with 403
 * 5. All purchaser report values tally after fix
 */
class ComprehensiveFixAndTallyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $purchaser;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'purchaser']);

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->purchaser = User::factory()->create(['name' => 'Test Purchaser']);
        $this->purchaser->assignRole('purchaser');

        $this->supplier = Supplier::factory()->create(['name' => 'Test Vendor']);
    }

    /**
     * Helper: create a bill with a pre-discounted amount (simulating the bug)
     * where invoice.amount = 5600 but items gross total = 6000.
     *
     * Acceptance scenario:
     *   Quantity: 100, Unit price: ₹60
     *   Gross subtotal: ₹6,000
     *   Discount: ₹400
     *   Net bill: ₹5,600
     *   Paid: ₹5,200
     *   Balance: ₹400
     */
    private function createBuggyBill(): PurchaseInvoice
    {
        $cart = PurchaserCart::factory()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'submitted',
            'discount_amount' => 400.00,
            'paid_amount' => 5200.00,
            'payment_status' => 'partial',
            'payment_method' => 'Cash',
            'business_date' => today(),
        ]);

        // Item: 100 × ₹60 = ₹6,000 gross
        // But line_total stored wrong as 5600 (pre-discounted)
        PurchaserCartItem::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'quantity' => 100,
            'unit_price' => 60.00,
            'line_total' => 5600.00, // BUG: should be 6000
        ]);

        $grn = GoodsReceived::factory()->create([
            'supplier_id' => $this->supplier->id,
        ]);

        // Invoice with wrong amount (pre-discounted 5600 instead of gross 6000)
        return PurchaseInvoice::factory()->create([
            'goods_received_id' => $grn->id,
            'supplier_id' => $this->supplier->id,
            'purchaser_cart_id' => $cart->id,
            'purchaser_submitted_by' => $this->purchaser->id,
            'amount' => 5600.00,          // BUG: should be 6000 (gross)
            'discount_amount' => 400.00,
            'paid_amount' => 5200.00,
            'payment_method' => 'Cash',
            'payment_paid_by' => 'purchaser',
            'payment_status' => 'partial',
            'status' => InvoiceStatus::Pending->value,
        ]);
    }

    public function test_admin_fix_updates_cart_items_invoice_cart_and_credit(): void
    {
        $invoice = $this->createBuggyBill();

        // Create a purchaser credit that should be updated
        PurchaserCredit::create([
            'purchaser_id' => $this->purchaser->id,
            'purchase_invoice_id' => $invoice->id,
            'type' => 'out',
            'amount' => 5200.00, // old incorrect net amount
            'description' => 'Debit for invoice',
            'created_by' => $this->purchaser->id,
            'business_date' => today(),
        ]);

        // Verify the bill has a calculation error before fix
        $invoice->load('purchaserCart.items');
        $this->assertTrue($invoice->hasCalculationError());

        // Admin fixes the bill
        $response = $this->actingAs($this->admin)->post(
            route('purchasing.invoices.fix-calculation', $invoice)
        );
        $response->assertRedirect();

        // Verify invoice updated correctly
        $invoice->refresh();
        $this->assertEquals(6000.00, (float) $invoice->amount, 'Invoice gross amount should be 6000');
        $this->assertEquals(400.00, (float) $invoice->discount_amount, 'Discount should remain 400');
        $this->assertEquals(5200.00, (float) $invoice->paid_amount, 'Paid should remain 5200');
        $this->assertEquals('partial', $invoice->payment_status, 'Status should be partial (5200 < 5600 net)');

        // Verify net and balance
        $net = (float) $invoice->amount - (float) $invoice->discount_amount;
        $balance = $net - (float) $invoice->paid_amount;
        $this->assertEquals(5600.00, $net, 'Net should be 5600');
        $this->assertEquals(400.00, $balance, 'Balance should be 400');

        // Verify cart item line_total fixed
        $cartItem = $invoice->purchaserCart->items->first()->fresh();
        $this->assertEquals(6000.00, (float) $cartItem->line_total, 'Cart item line_total should be 6000');

        // Verify purchaser cart synced
        $cart = $invoice->purchaserCart->fresh();
        $this->assertEquals(400.00, (float) $cart->discount_amount);
        $this->assertEquals(5200.00, (float) $cart->paid_amount);
        $this->assertEquals('partial', $cart->payment_status);

        // Verify purchaser credit updated to net amount
        $credit = PurchaserCredit::query()
            ->where('purchase_invoice_id', $invoice->id)
            ->where('type', 'out')
            ->first();
        $this->assertNotNull($credit);
        $this->assertEquals(5600.00, (float) $credit->amount, 'Purchaser credit should be updated to net 5600');

        // Verify no calculation error remains
        $this->assertFalse($invoice->hasCalculationError());
    }

    public function test_fix_does_not_create_new_payment_history(): void
    {
        $invoice = $this->createBuggyBill();

        $existingPaymentCount = PurchaseInvoicePayment::query()
            ->where('purchase_invoice_id', $invoice->id)
            ->count();

        $this->actingAs($this->admin)->post(
            route('purchasing.invoices.fix-calculation', $invoice)
        );

        $afterPaymentCount = PurchaseInvoicePayment::query()
            ->where('purchase_invoice_id', $invoice->id)
            ->count();

        $this->assertEquals($existingPaymentCount, $afterPaymentCount,
            'No new payment history rows should be created during fix');
    }

    public function test_non_admin_cannot_fix_calculation(): void
    {
        $invoice = $this->createBuggyBill();

        $response = $this->actingAs($this->purchaser)->post(
            route('purchasing.invoices.fix-calculation', $invoice)
        );

        $response->assertForbidden();
    }

    public function test_bulk_fix_all_calculations(): void
    {
        // Create two buggy bills
        $invoice1 = $this->createBuggyBill();
        $invoice2 = $this->createBuggyBill();

        // Verify both are flagged
        $invoice1->load('purchaserCart.items');
        $invoice2->load('purchaserCart.items');
        $this->assertTrue($invoice1->hasCalculationError());
        $this->assertTrue($invoice2->hasCalculationError());

        // Admin fixes all
        $response = $this->actingAs($this->admin)->post(
            route('purchasing.invoices.fix-all-calculations')
        );
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify both are fixed
        $invoice1->refresh();
        $invoice2->refresh();
        $this->assertEquals(6000.00, (float) $invoice1->amount);
        $this->assertEquals(6000.00, (float) $invoice2->amount);
        $this->assertFalse($invoice1->hasCalculationError());
        $this->assertFalse($invoice2->hasCalculationError());
    }

    public function test_non_admin_cannot_bulk_fix(): void
    {
        $response = $this->actingAs($this->purchaser)->post(
            route('purchasing.invoices.fix-all-calculations')
        );

        $response->assertForbidden();
    }

    public function test_fix_removes_stale_credit_when_not_purchaser_funded(): void
    {
        $invoice = $this->createBuggyBill();
        $invoice->update(['payment_paid_by' => 'company']);

        PurchaserCredit::create([
            'purchaser_id' => $this->purchaser->id,
            'purchase_invoice_id' => $invoice->id,
            'type' => 'out',
            'amount' => 5200.00,
            'description' => 'Stale debit entry',
            'created_by' => $this->purchaser->id,
            'business_date' => today(),
        ]);

        $this->actingAs($this->admin)->post(
            route('purchasing.invoices.fix-calculation', $invoice)
        );

        // Stale credit should be deleted since payment_paid_by is 'company'
        $creditExists = PurchaserCredit::query()
            ->where('purchase_invoice_id', $invoice->id)
            ->where('type', 'out')
            ->exists();
        $this->assertFalse($creditExists, 'Stale purchaser credit should be deleted for company-funded bills');
    }
}
