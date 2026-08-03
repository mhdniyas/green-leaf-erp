<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseInvoiceCalculationErrorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('purchase', 'web');
    }

    public function test_invoice_detects_calculation_error_and_restricts_updates_to_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchase');

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $cart = PurchaserCart::factory()->create([
            'user_id' => $purchaser->id,
            'supplier_id' => $supplier->id,
            'status' => 'completed',
        ]);

        // 100 kg * 60 = 6000 gross item sum
        PurchaserCartItem::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit_price' => 60,
            'line_total' => 6000,
        ]);

        // Stored invoice with erroneous pre-discounted amount (5600 instead of 6000)
        $invoice = PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'supplier_id' => $supplier->id,
            'amount' => 5600.00,
            'discount_amount' => 400.00,
            'paid_amount' => 5600.00,
            'payment_status' => 'paid',
        ]);

        // 1. Verify calculation error detection
        $this->assertTrue($invoice->hasCalculationError());
        $this->assertEquals(6000.00, $invoice->itemsGrossTotal());

        // 2. Verify non-admin is blocked with 403 Forbidden
        $response = $this->actingAs($purchaser)->patch(route('purchasing.invoices.update-payment', $invoice), [
            'payment_method' => 'Cash',
            'paid_amount' => 5600.00,
        ]);

        $response->assertStatus(403);

        // 3. Verify admin can fix calculation error
        $fixResponse = $this->actingAs($admin)->post(route('purchasing.invoices.fix-calculation', $invoice));

        $fixResponse->assertRedirect();
        $invoice->refresh();

        $this->assertFalse($invoice->hasCalculationError());
        $this->assertEquals(6000.00, (float) $invoice->amount);
        $this->assertEquals(400.00, (float) $invoice->discount_amount);
        $this->assertEquals(5600.00, (float) $invoice->paid_amount);
        $this->assertEquals('paid', $invoice->payment_status);
    }
}
