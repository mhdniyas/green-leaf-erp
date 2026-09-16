<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoicePayment;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProcessedBillItemRemovalTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('purchaser');
        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');
        $this->supplier = Supplier::factory()->create();
    }

    public function test_purchaser_can_remove_an_unpaid_processed_bill_item_and_totals_are_recalculated(): void
    {
        [$cart, $firstItem, $secondItem, $invoice] = $this->processedCart();

        $response = $this->actingAs($this->purchaser)->delete(route('purchaser.cart-items.destroy', $firstItem), [
            'return_to' => 'vendors',
            'tab' => 'pending',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('purchaser.vendors', ['date' => '2026-09-14', 'tab' => 'pending']));
        $this->assertDatabaseMissing('purchaser_cart_items', ['id' => $firstItem->id]);
        $this->assertDatabaseHas('purchaser_cart_items', ['id' => $secondItem->id]);
        $this->assertSame(500.0, (float) $invoice->fresh()->amount);
        $this->assertDatabaseHas('activity_log', ['subject_type' => PurchaseInvoice::class, 'subject_id' => $invoice->id, 'description' => 'invoice.item_removed']);
        $this->assertSame(1, $cart->fresh()->items()->count());
    }

    public function test_processed_bill_cannot_remove_its_last_item(): void
    {
        [$cart, $firstItem] = $this->processedCart(1);

        $response = $this->actingAs($this->purchaser)->delete(route('purchaser.cart-items.destroy', $firstItem));

        $response->assertSessionHasErrors('item');
        $this->assertDatabaseHas('purchaser_cart_items', ['id' => $firstItem->id]);
        $this->assertSame(1, $cart->fresh()->items()->count());
    }

    public function test_processed_bill_removal_is_rejected_when_recorded_payment_exceeds_remaining_total(): void
    {
        [$cart, $firstItem, $secondItem, $invoice] = $this->processedCart();
        PurchaseInvoicePayment::query()->create([
            'purchase_invoice_id' => $invoice->id,
            'amount' => 1200,
            'payment_date' => today(),
            'created_by' => $this->purchaser->id,
        ]);

        $response = $this->actingAs($this->purchaser)->delete(route('purchaser.cart-items.destroy', $firstItem));

        $response->assertSessionHasErrors('item');
        $this->assertDatabaseHas('purchaser_cart_items', ['id' => $firstItem->id]);
        $this->assertDatabaseHas('purchaser_cart_items', ['id' => $secondItem->id]);
        $this->assertSame(1500.0, (float) $invoice->fresh()->amount);
    }

    /** @return array{0: PurchaserCart, 1: PurchaserCartItem, 2?: PurchaserCartItem, 3: PurchaseInvoice} */
    private function processedCart(int $itemCount = 2): array
    {
        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-09-14',
            'status' => 'submitted',
            'cart_number' => 'VC-TEST-'.uniqid(),
        ]);

        $items = [];
        foreach (range(1, $itemCount) as $index) {
            $product = Product::factory()->create();
            $items[] = PurchaserCartItem::query()->create([
                'purchaser_cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => $index === 1 ? 1000 : 500,
                'line_total' => $index === 1 ? 1000 : 500,
            ]);
        }

        $invoice = PurchaseInvoice::factory()->create([
            'supplier_id' => $this->supplier->id,
            'purchaser_cart_id' => $cart->id,
            'amount' => $itemCount === 1 ? 1000 : 1500,
            'discount_amount' => 0,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
            'payment_paid_by' => 'vendor_credit',
        ]);
        $cart->update(['purchase_invoice_id' => $invoice->id]);

        return [$cart, $items[0], $items[1] ?? null, $invoice];
    }
}
