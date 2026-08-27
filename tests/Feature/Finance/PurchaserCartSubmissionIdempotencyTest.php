<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Purchasing\InvoiceStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\PurchaserCredit;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserCartSubmissionIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private User $admin;

    private Supplier $supplier;

    private Supplier $otherSupplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-27 10:00:00');

        Role::findOrCreate('purchaser');
        Role::findOrCreate('admin');

        $this->purchaser = User::factory()->create(['name' => 'Test Purchaser']);
        $this->purchaser->assignRole('purchaser');

        $this->admin = User::factory()->create(['name' => 'System Admin']);
        $this->admin->assignRole('admin');

        $category = Category::factory()->create(['name' => 'VEG', 'is_active' => true]);
        $this->supplier = Supplier::factory()->create([
            'name' => 'Green Valley Farms',
            'type' => 'Vendor',
            'credit_approved' => true,
        ]);

        $this->otherSupplier = Supplier::factory()->create([
            'name' => 'Blue Sky Organics',
            'type' => 'Vendor',
            'credit_approved' => true,
        ]);

        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Fresh Onion',
            'unit' => 'kg',
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);

        Account::query()->firstOrCreate(['code' => '1010'], ['name' => 'Cash on Hand', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1020'], ['name' => 'Bank Account', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '1300'], ['name' => 'Purchaser Advances', 'type' => 'asset', 'is_active' => true]);
        Account::query()->firstOrCreate(['code' => '2100'], ['name' => 'Accounts Payable', 'type' => 'liability', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createDraftCart(array $attributes = []): array
    {
        $cart = PurchaserCart::query()->create(array_merge([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-08-27',
            'status' => 'draft',
            'purchase_grade' => 'A',
            'cart_number' => 'VC-20260827-'.rand(100, 999),
            'purchase_source' => 'shop_order',
            'payment_method' => 'Cash',
            'payment_status' => 'paid',
            'paid_amount' => 500.00,
        ], $attributes));

        $item = PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'grade' => 'A',
            'quantity' => 10.0,
            'unit_price' => 50.00,
            'line_total' => 500.00,
        ]);

        return [$cart, $item];
    }

    public function test_first_submit_creates_one_bill_and_returns_valid_purchaser_redirect(): void
    {
        [$cart, $item] = $this->createDraftCart();

        $response = $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => '2026-08-27',
            'cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'bill_number' => 'BILL-1001',
            'return_to' => 'vendors',
            'items' => [
                $item->id => ['unit_price' => 50.00],
            ],
        ]);

        $response->assertRedirect(route('purchaser.vendors', ['date' => '2026-08-27', 'tab' => 'pending']));
        $response->assertSessionHas('success');

        $cart->refresh();
        $this->assertEquals('submitted', $cart->status);
        $this->assertNotNull($cart->purchase_invoice_id);

        $this->assertEquals(1, PurchaseInvoice::query()->where('purchaser_cart_id', $cart->id)->count());
        $invoice = PurchaseInvoice::query()->where('purchaser_cart_id', $cart->id)->firstOrFail();

        $this->assertEquals(500.00, (float) $invoice->amount);
        $this->assertEquals('BILL-1001', $invoice->invoice_number);

        $this->assertEquals(1, JournalEntry::query()->where('source_type', PurchaseInvoice::class)->where('source_id', $invoice->id)->count());
        $this->assertEquals(1, PurchaserCredit::query()->where('purchase_invoice_id', $invoice->id)->count());
    }

    public function test_second_identical_submit_is_idempotent_and_does_not_404_or_duplicate(): void
    {
        [$cart, $item] = $this->createDraftCart();

        $payload = [
            'business_date' => '2026-08-27',
            'cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'bill_number' => 'BILL-1002',
            'return_to' => 'vendors',
            'items' => [
                $item->id => ['unit_price' => 50.00],
            ],
        ];

        // First submit
        $res1 = $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), $payload);
        $res1->assertRedirect();
        $res1->assertSessionHas('success');

        // Second submit (retry / double-submit)
        $res2 = $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), $payload);

        // Crucial requirement: No 404 exception! Redirects cleanly.
        $res2->assertStatus(302);
        $res2->assertRedirect(route('purchaser.vendors', ['date' => '2026-08-27', 'tab' => 'pending']));

        // Assert counts remain strictly 1
        $this->assertEquals(1, PurchaseInvoice::query()->where('purchaser_cart_id', $cart->id)->count());
        $this->assertEquals(1, PurchaserCredit::query()->where('purchaser_id', $this->purchaser->id)->count());
        $this->assertEquals(1, JournalEntry::query()->where('source_type', PurchaseInvoice::class)->count());
    }

    public function test_opening_submitted_bill_page_redirects_safely_without_404(): void
    {
        [$cart, $item] = $this->createDraftCart();

        $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => '2026-08-27',
            'cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'bill_number' => 'BILL-1003',
            'return_to' => 'vendors',
            'items' => [
                $item->id => ['unit_price' => 50.00],
            ],
        ]);

        $cart->refresh();
        $invoice = PurchaseInvoice::query()->where('purchaser_cart_id', $cart->id)->firstOrFail();

        // Accessing /purchaser/cart/{cart}/bill on a submitted cart must NOT 404!
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.bill', ['cart' => $cart, 'date' => '2026-08-27']));

        $response->assertStatus(302);
        $response->assertRedirect(route('purchaser.invoices.show', $invoice));
    }

    public function test_double_click_simulation_creates_one_bill(): void
    {
        [$cart, $item] = $this->createDraftCart();

        $payload = [
            'business_date' => '2026-08-27',
            'cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'bill_number' => 'BILL-FAST-DOUBLE',
            'return_to' => 'history',
            'items' => [
                $item->id => ['unit_price' => 50.00],
            ],
        ];

        // Simulate rapid sequential POSTs
        $res1 = $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), $payload);
        $res2 = $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), $payload);

        $res1->assertRedirect();
        $res2->assertRedirect();

        $this->assertEquals(1, PurchaseInvoice::query()->where('purchaser_cart_id', $cart->id)->count());
    }

    public function test_purchaser_authorization_prevents_submitting_another_users_cart(): void
    {
        [$cart, $item] = $this->createDraftCart();
        $otherPurchaser = User::factory()->create();
        $otherPurchaser->assignRole('purchaser');

        $response = $this->actingAs($otherPurchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => '2026-08-27',
            'cart_id' => $cart->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'bill_number' => 'BILL-UNAUTH',
            'items' => [
                $item->id => ['unit_price' => 50.00],
            ],
        ]);

        $response->assertStatus(404);
        $this->assertEquals(0, PurchaseInvoice::query()->where('purchaser_cart_id', $cart->id)->count());
    }

    public function test_same_vendor_and_same_invoice_number_from_different_cart_blocks_duplicate_invoice(): void
    {
        [$cart1, $item1] = $this->createDraftCart();
        [$cart2, $item2] = $this->createDraftCart();

        // Submit Cart 1 with Bill No INV-4582 for Green Valley Farms
        $res1 = $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => '2026-08-27',
            'cart_id' => $cart1->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'bill_number' => 'INV-4582',
            'return_to' => 'vendors',
            'items' => [
                $item1->id => ['unit_price' => 50.00],
            ],
        ]);
        $res1->assertRedirect();
        $this->assertEquals(1, PurchaseInvoice::query()->where('supplier_id', $this->supplier->id)->count());

        // Submit Cart 2 with SAME Bill No INV-4582 for SAME vendor Green Valley Farms
        $res2 = $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => '2026-08-27',
            'cart_id' => $cart2->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'bill_number' => 'INV-4582',
            'return_to' => 'vendors',
            'items' => [
                $item2->id => ['unit_price' => 50.00],
            ],
        ]);

        $res2->assertRedirect(route('purchaser.vendors', ['date' => '2026-08-27', 'tab' => 'pending']));
        $res2->assertSessionHas('success');

        // Total PurchaseInvoices for supplier must STILL be 1!
        $this->assertEquals(1, PurchaseInvoice::query()->where('supplier_id', $this->supplier->id)->count());
        $this->assertEquals(1, JournalEntry::query()->where('source_type', PurchaseInvoice::class)->count());
        $this->assertEquals(1, PurchaserCredit::query()->where('purchaser_id', $this->purchaser->id)->count());

        // Cart 2 is linked to the existing invoice
        $cart2->refresh();
        $this->assertEquals('submitted', $cart2->status);
        $this->assertEquals(PurchaseInvoice::first()->id, $cart2->purchase_invoice_id);
    }

    public function test_same_vendor_and_normalized_case_space_invoice_number_blocks_duplicate_invoice(): void
    {
        [$cart1, $item1] = $this->createDraftCart();
        [$cart2, $item2] = $this->createDraftCart();

        // Cart 1 submitted with uppercase "INV-4582"
        $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => '2026-08-27',
            'cart_id' => $cart1->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'bill_number' => 'INV-4582',
            'return_to' => 'vendors',
            'items' => [
                $item1->id => ['unit_price' => 50.00],
            ],
        ]);

        // Cart 2 submitted with lowercase + leading/trailing space " inv-4582 "
        $res2 = $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => '2026-08-27',
            'cart_id' => $cart2->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'bill_number' => ' inv-4582 ',
            'return_to' => 'vendors',
            'items' => [
                $item2->id => ['unit_price' => 50.00],
            ],
        ]);

        $res2->assertRedirect();
        $this->assertEquals(1, PurchaseInvoice::query()->where('supplier_id', $this->supplier->id)->count());
    }

    public function test_same_vendor_different_invoice_number_is_allowed(): void
    {
        [$cart1, $item1] = $this->createDraftCart();
        [$cart2, $item2] = $this->createDraftCart();

        // Cart 1 with INV-100
        $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => '2026-08-27',
            'cart_id' => $cart1->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'bill_number' => 'INV-100',
            'return_to' => 'vendors',
            'items' => [
                $item1->id => ['unit_price' => 50.00],
            ],
        ]);

        // Cart 2 with DIFFERENT bill number INV-101 for same vendor
        $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => '2026-08-27',
            'cart_id' => $cart2->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'bill_number' => 'INV-101',
            'return_to' => 'vendors',
            'items' => [
                $item2->id => ['unit_price' => 50.00],
            ],
        ]);

        $this->assertEquals(2, PurchaseInvoice::query()->where('supplier_id', $this->supplier->id)->count());
    }

    public function test_different_vendor_same_invoice_number_is_allowed(): void
    {
        [$cart1, $item1] = $this->createDraftCart(['supplier_id' => $this->supplier->id]);
        [$cart2, $item2] = $this->createDraftCart(['supplier_id' => $this->otherSupplier->id]);

        // Supplier 1 with INV-Common
        $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => '2026-08-27',
            'cart_id' => $cart1->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'bill_number' => 'INV-Common',
            'return_to' => 'vendors',
            'items' => [
                $item1->id => ['unit_price' => 50.00],
            ],
        ]);

        // Supplier 2 with SAME bill number INV-Common (different supplier)
        $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => '2026-08-27',
            'cart_id' => $cart2->id,
            'supplier_id' => $this->otherSupplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'bill_number' => 'INV-Common',
            'return_to' => 'vendors',
            'items' => [
                $item2->id => ['unit_price' => 50.00],
            ],
        ]);

        $this->assertEquals(1, PurchaseInvoice::query()->where('supplier_id', $this->supplier->id)->count());
        $this->assertEquals(1, PurchaseInvoice::query()->where('supplier_id', $this->otherSupplier->id)->count());
    }

    public function test_same_vendor_same_amount_different_invoice_number_is_allowed(): void
    {
        [$cart1, $item1] = $this->createDraftCart();
        [$cart2, $item2] = $this->createDraftCart();

        // Same vendor, same amount 500.00, but Bill # A-1
        $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => '2026-08-27',
            'cart_id' => $cart1->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'bill_number' => 'BILL-A-1',
            'return_to' => 'vendors',
            'items' => [
                $item1->id => ['unit_price' => 50.00],
            ],
        ]);

        // Same vendor, same amount 500.00, but Bill # A-2
        $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => '2026-08-27',
            'cart_id' => $cart2->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'bill_number' => 'BILL-A-2',
            'return_to' => 'vendors',
            'items' => [
                $item2->id => ['unit_price' => 50.00],
            ],
        ]);

        $this->assertEquals(2, PurchaseInvoice::query()->where('supplier_id', $this->supplier->id)->count());
    }

    public function test_blank_invoice_number_not_blocked_by_amount_alone(): void
    {
        [$cart1, $item1] = $this->createDraftCart();
        [$cart2, $item2] = $this->createDraftCart();

        // Cart 1 submitted without bill number
        $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => '2026-08-27',
            'cart_id' => $cart1->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'bill_number' => '',
            'return_to' => 'vendors',
            'items' => [
                $item1->id => ['unit_price' => 50.00],
            ],
        ]);

        // Cart 2 submitted without bill number for same vendor & amount
        $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => '2026-08-27',
            'cart_id' => $cart2->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'bill_number' => '',
            'return_to' => 'vendors',
            'items' => [
                $item2->id => ['unit_price' => 50.00],
            ],
        ]);

        $this->assertEquals(2, PurchaseInvoice::query()->where('supplier_id', $this->supplier->id)->count());
    }

    public function test_cancelled_invoice_allows_reusing_bill_number(): void
    {
        [$cart1, $item1] = $this->createDraftCart();
        [$cart2, $item2] = $this->createDraftCart();

        // Submit Cart 1 with Bill INV-CANCEL-1
        $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => '2026-08-27',
            'cart_id' => $cart1->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'bill_number' => 'INV-CANCEL-1',
            'return_to' => 'vendors',
            'items' => [
                $item1->id => ['unit_price' => 50.00],
            ],
        ]);

        $invoice1 = PurchaseInvoice::query()->where('purchaser_cart_id', $cart1->id)->firstOrFail();
        $invoice1->update(['status' => InvoiceStatus::Cancelled]);

        // Submit Cart 2 with SAME Bill INV-CANCEL-1
        $this->actingAs($this->purchaser)->post(route('purchaser.carts.submit'), [
            'business_date' => '2026-08-27',
            'cart_id' => $cart2->id,
            'supplier_id' => $this->supplier->id,
            'payment_method' => 'Cash',
            'paid_amount' => 500.00,
            'bill_number' => 'INV-CANCEL-1',
            'return_to' => 'vendors',
            'items' => [
                $item2->id => ['unit_price' => 50.00],
            ],
        ]);

        // Allowed because previous invoice was cancelled! Active count is 1.
        $this->assertEquals(1, PurchaseInvoice::query()->where('supplier_id', $this->supplier->id)->notCancelled()->count());
    }
}
