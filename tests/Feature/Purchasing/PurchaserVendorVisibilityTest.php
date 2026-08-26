<?php

namespace Tests\Feature\Purchasing;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserVendorVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Supplier $vendorA;

    private Supplier $vendorB;

    private Supplier $vendorDeleted;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'purchaser']);
        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        // Vendor A: has previous cart from another user/no cart
        $this->vendorA = Supplier::factory()->create([
            'name' => 'Alpha Agro Vendor',
            'mobile_number' => '9876543210',
            'credit_approved' => true,
        ]);

        // Vendor B: brand new vendor, never used by any purchaser
        $this->vendorB = Supplier::factory()->create([
            'name' => 'Beta Fresh Supplies',
            'mobile_number' => '9876543211',
            'credit_approved' => false,
        ]);

        // Deleted vendor
        $this->vendorDeleted = Supplier::factory()->create([
            'name' => 'Gamma Inactive Vendor',
            'deleted_at' => now(),
        ]);
    }

    public function test_purchaser_sees_all_active_vendors_in_vendors_page(): void
    {
        $today = app(PurchaserBusinessDayService::class)->operationalDate()->format('Y-m-d');

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.vendors', ['date' => $today]));

        $response->assertOk();
        $response->assertSee('Alpha Agro Vendor');
        $response->assertSee('Beta Fresh Supplies');
        $response->assertDontSee('Gamma Inactive Vendor');
    }

    public function test_purchaser_sees_all_active_vendors_in_bill_payment_page(): void
    {
        $today = now()->format('Y-m-d');
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $cart = PurchaserCart::query()->create([
            'cart_number' => 'CART-VIS-001',
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->vendorA->id,
            'business_date' => $today,
            'status' => 'draft',
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 50,
            'line_total' => 250,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.bill', ['cart' => $cart]));

        $response->assertOk();
        $response->assertViewHas('suppliers', function ($suppliers) {
            $names = $suppliers->pluck('name')->all();

            return in_array('Alpha Agro Vendor', $names, true)
                && in_array('Beta Fresh Supplies', $names, true)
                && ! in_array('Gamma Inactive Vendor', $names, true);
        });
    }

    public function test_purchaser_can_search_and_see_all_active_vendors_in_supplier_hub(): void
    {
        $today = now()->format('Y-m-d');

        $response = $this->actingAs($this->purchaser)
            ->get(route('purchaser.suppliers', ['date' => $today, 'search' => 'Beta']));

        $response->assertOk();
        $response->assertSee('Beta Fresh Supplies');
        $response->assertDontSee('Gamma Inactive Vendor');
    }

    public function test_purchaser_can_assign_never_used_vendor_to_cart_and_submit(): void
    {
        $today = now()->format('Y-m-d');
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $cart = PurchaserCart::query()->create([
            'cart_number' => 'CART-VIS-002',
            'user_id' => $this->purchaser->id,
            'supplier_id' => null,
            'business_date' => $today,
            'status' => 'draft',
        ]);

        $cartItem = PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 20,
            'line_total' => 200,
        ]);

        // Assign Vendor B to cart
        $updateResponse = $this->actingAs($this->purchaser)
            ->patch(route('purchaser.carts.update-supplier', $cart), [
                'supplier_id' => $this->vendorB->id,
                'return_to' => 'vendors',
            ]);

        $updateResponse->assertSessionHasNoErrors();
        $cart->refresh();
        $this->assertEquals($this->vendorB->id, $cart->supplier_id);

        // Submit cart with Vendor B
        $submitResponse = $this->actingAs($this->purchaser)
            ->post(route('purchaser.carts.submit'), [
                'cart_id' => $cart->id,
                'supplier_id' => $this->vendorB->id,
                'business_date' => $today,
                'payment_method' => 'Cash',
                'paid_amount' => 200.00,
                'items' => [
                    $cartItem->id => [
                        'unit_price' => 20,
                    ],
                ],
            ]);

        $submitResponse->assertSessionHasNoErrors();
        $submitResponse->assertRedirect(route('purchaser.vendors', ['date' => $today, 'tab' => 'pending']));
    }
}
