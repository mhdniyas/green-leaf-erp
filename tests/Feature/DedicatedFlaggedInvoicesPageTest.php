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

class DedicatedFlaggedInvoicesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('purchase', 'web');
    }

    public function test_flagged_bills_page_renders_and_filters_by_purchaser_and_date(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $purchaser1 = User::factory()->create(['name' => 'Purchaser One']);
        $purchaser1->assignRole('purchase');

        $purchaser2 = User::factory()->create(['name' => 'Purchaser Two']);
        $purchaser2->assignRole('purchase');

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $cart1 = PurchaserCart::factory()->create([
            'user_id' => $purchaser1->id,
            'supplier_id' => $supplier->id,
        ]);
        PurchaserCartItem::factory()->create([
            'purchaser_cart_id' => $cart1->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit_price' => 60,
        ]);
        $flaggedInvoice1 = PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart1->id,
            'supplier_id' => $supplier->id,
            'purchaser_submitted_by' => $purchaser1->id,
            'amount' => 5600.00, // Erroneous pre-discount amount
            'discount_amount' => 400.00,
            'created_at' => '2026-08-01 10:00:00',
        ]);

        $cart2 = PurchaserCart::factory()->create([
            'user_id' => $purchaser2->id,
            'supplier_id' => $supplier->id,
        ]);
        PurchaserCartItem::factory()->create([
            'purchaser_cart_id' => $cart2->id,
            'product_id' => $product->id,
            'quantity' => 50,
            'unit_price' => 40,
        ]);
        $flaggedInvoice2 = PurchaseInvoice::factory()->create([
            'purchaser_cart_id' => $cart2->id,
            'supplier_id' => $supplier->id,
            'purchaser_submitted_by' => $purchaser2->id,
            'amount' => 1800.00, // Erroneous pre-discount amount
            'discount_amount' => 200.00,
            'created_at' => '2026-08-02 10:00:00',
        ]);

        // 1. Visit flagged page without filters -> sees both flagged invoices
        $response = $this->actingAs($admin)->get(route('purchasing.invoices.flagged'));
        $response->assertStatus(200);
        $response->assertSee($flaggedInvoice1->invoice_number);
        $response->assertSee($flaggedInvoice2->invoice_number);

        // 2. Filter by purchaser_id -> sees only purchaser1's bill
        $filteredPurchaser = $this->actingAs($admin)->get(route('purchasing.invoices.flagged', [
            'purchaser_id' => $purchaser1->id,
        ]));
        $filteredPurchaser->assertStatus(200);
        $filteredPurchaser->assertSee($flaggedInvoice1->invoice_number);
        $filteredPurchaser->assertDontSee($flaggedInvoice2->invoice_number);

        // 3. Filter by date -> sees only 2026-08-02 bill
        $filteredDate = $this->actingAs($admin)->get(route('purchasing.invoices.flagged', [
            'date' => '2026-08-02',
        ]));
        $filteredDate->assertStatus(200);
        $filteredDate->assertDontSee($flaggedInvoice1->invoice_number);
        $filteredDate->assertSee($flaggedInvoice2->invoice_number);
    }
}
