<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserCartRevertAndInvoiceCancellationTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Role::firstOrCreate(['name' => 'purchaser']);

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $this->supplier = Supplier::factory()->create();
        $this->product = Product::factory()->create();
    }

    public function test_purchaser_can_destroy_invoice_and_revert_cart_to_submitted_pending(): void
    {
        $today = today();

        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'submitted',
            'business_date' => $today,
            'cart_number' => 'VC-TEST-'.uniqid(),
            'bill_number' => 'INV-TEST-001',
        ]);

        $cartItem = PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 50,
            'line_total' => 500,
        ]);

        $goodsReceived = GoodsReceived::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'status' => 'approved',
            'bill_status' => 'matched',
            'bill_number' => 'INV-TEST-001',
        ]);

        $invoice = PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'goods_received_id' => $goodsReceived->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-TEST-001',
            'status' => 'approved',
            'amount' => 500,
            'paid_amount' => 0,
        ]);

        $cart->update(['purchase_invoice_id' => $invoice->id]);

        $response = $this->actingAs($this->purchaser)
            ->delete(route('purchaser.invoices.destroy', $invoice), [
                'cancellation_note' => 'Reverting cart for edit',
            ]);

        $response->assertRedirect(route('purchaser.vendors', [
            'date' => $today->format('Y-m-d'),
            'tab' => 'pending',
        ]));
        $response->assertSessionHas('success', 'Bill cancelled. Cart reverted to pending — you can now re-process it.');

        $cart->refresh();
        $goodsReceived->refresh();

        $this->assertSame('submitted', $cart->status);
        $this->assertNull($cart->bill_number);
        $this->assertSame('cancelled', $goodsReceived->status);
        $this->assertSame('cancelled', $goodsReceived->bill_status);
        $this->assertNull($goodsReceived->bill_number);

        // Verify the cart now appears under pendingCarts on the purchaser.vendors page
        $vendorsResponse = $this->actingAs($this->purchaser)
            ->get(route('purchaser.vendors', ['date' => $today->format('Y-m-d'), 'tab' => 'pending']));

        $vendorsResponse->assertOk();
        $vendorsResponse->assertViewHas('pendingCarts', function ($pendingCarts) use ($cart) {
            return $pendingCarts->contains('id', $cart->id);
        });
    }

    public function test_purchaser_cannot_destroy_another_purchasers_invoice(): void
    {
        $otherPurchaser = User::factory()->create();
        $otherPurchaser->assignRole('purchaser');

        $cart = PurchaserCart::query()->create([
            'user_id' => $otherPurchaser->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'submitted',
            'business_date' => today(),
            'cart_number' => 'VC-TEST-'.uniqid(),
        ]);

        $invoice = PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-OTHER-001',
        ]);

        $response = $this->actingAs($this->purchaser)
            ->delete(route('purchaser.invoices.destroy', $invoice));

        $response->assertNotFound();
    }
}
