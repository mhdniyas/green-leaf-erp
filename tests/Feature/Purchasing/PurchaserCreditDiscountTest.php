<?php

namespace Tests\Feature\Purchasing;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\PurchaserCredit;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserCreditDiscountTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Supplier $supplier;

    private Product $product;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'purchaser']);
        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $this->supplier = Supplier::factory()->create([
            'credit_approved' => true,
        ]);

        $category = Category::factory()->create();
        $this->warehouse = Warehouse::factory()->create();

        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'unit' => 'KG',
        ]);
    }

    public function test_purchaser_can_submit_cart_with_credit_and_discount(): void
    {
        $today = now()->format('Y-m-d');

        $cart = PurchaserCart::query()->create([
            'cart_number' => 'CART-TEST-001',
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $today,
            'status' => 'draft',
        ]);

        $cartItem = PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 100,
            'line_total' => 1000,
        ]);

        $initialCreditsCount = PurchaserCredit::query()->count();

        $response = $this->actingAs($this->purchaser)
            ->post(route('purchaser.carts.submit'), [
                'cart_id' => $cart->id,
                'supplier_id' => $this->supplier->id,
                'business_date' => $today,
                'bill_number' => 'BILL-CR-100',
                'payment_method' => 'Credit',
                'discount_amount' => 150.00,
                'payment_note' => 'Special festival credit discount agreed with vendor',
                'items' => [
                    $cartItem->id => [
                        'unit_price' => 100,
                    ],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('purchaser.vendors', ['date' => $today, 'tab' => 'pending']));

        $cart->refresh();
        $invoice = PurchaseInvoice::query()->where('purchaser_cart_id', $cart->id)->first();

        $this->assertNotNull($invoice);
        $this->assertEquals(1000.00, (float) $invoice->amount);
        $this->assertEquals(150.00, (float) $invoice->discount_amount);
        $this->assertEquals('Credit', $invoice->payment_method);
        $this->assertEquals(0.00, (float) $invoice->paid_amount);
        $this->assertEquals('Special festival credit discount agreed with vendor', $invoice->payment_note);
        $this->assertEquals('credit_pending_approval', $invoice->payment_status);

        // Verify net payable / outstanding balance is 850.00 (1000 - 150)
        $outstanding = (float) $invoice->amount - (float) $invoice->discount_amount - (float) $invoice->paid_amount;
        $this->assertEquals(850.00, $outstanding);

        // Verify no purchaser credits/cash movements were generated
        $this->assertEquals($initialCreditsCount, PurchaserCredit::query()->count());
    }

    public function test_purchaser_can_update_invoice_payment_to_credit_with_discount(): void
    {
        $today = now()->format('Y-m-d');

        $cart = PurchaserCart::query()->create([
            'cart_number' => 'CART-TEST-002',
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => $today,
            'status' => 'submitted',
        ]);

        $invoice = PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'amount' => 5000.00,
            'discount_amount' => 0.00,
            'paid_amount' => 0.00,
            'payment_method' => 'Cash',
            'payment_status' => 'unpaid',
        ]);

        $initialCreditsCount = PurchaserCredit::query()->count();

        $response = $this->actingAs($this->purchaser)
            ->patch(route('purchaser.invoices.payment', $invoice), [
                'payment_method' => 'Credit',
                'discount_amount' => 500.00,
                'payment_note' => 'Bulk volume settlement discount',
                'return_to' => 'history',
                'date' => $today,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('purchaser.history', ['date' => $today, 'tab' => 'today']));

        $invoice->refresh();
        $this->assertEquals(5000.00, (float) $invoice->amount);
        $this->assertEquals(500.00, (float) $invoice->discount_amount);
        $this->assertEquals('Credit', $invoice->payment_method);
        $this->assertEquals(0.00, (float) $invoice->paid_amount);
        $this->assertEquals('Bulk volume settlement discount', $invoice->payment_note);
        $this->assertEquals('credit_pending_approval', $invoice->payment_status);

        // Verify net payable / outstanding balance is 4500.00 (5000 - 500)
        $outstanding = (float) $invoice->amount - (float) $invoice->discount_amount - (float) $invoice->paid_amount;
        $this->assertEquals(4500.00, $outstanding);

        // Verify no purchaser credits/cash movements were generated
        $this->assertEquals($initialCreditsCount, PurchaserCredit::query()->count());
    }
}
