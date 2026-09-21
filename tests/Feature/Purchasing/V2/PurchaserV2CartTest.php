<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing\V2;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Queries\Purchasing\V2\PurchaserV2CartQuery;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserV2CartTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Carbon $testDate;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-24 10:00:00');

        $this->seed(RolePermissionSeeder::class);
        Role::findOrCreate('purchaser');

        $this->purchaser = User::factory()->create();
        $this->purchaser->assignRole('purchaser');

        $this->testDate = app(PurchaserBusinessDayService::class)->operationalDate();
        $this->category = Category::factory()->create(['name' => 'Vegetables', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createProduct(string $name, string $sku): Product
    {
        return Product::query()->create([
            'category_id' => $this->category->id,
            'name' => $name,
            'sku' => $sku,
            'unit' => 'kg',
            'base_price' => 30.00,
            'vendor_price' => 25.00,
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);
    }

    private function createDraftCart(?User $user = null, ?Supplier $supplier = null, string $grade = 'A'): PurchaserCart
    {
        return PurchaserCart::query()->create([
            'user_id' => ($user ?? $this->purchaser)->id,
            'supplier_id' => $supplier?->id,
            'business_date' => $this->testDate,
            'cart_number' => 'PC-TEST-'.uniqid(),
            'status' => 'draft',
            'purchase_grade' => $grade,
        ]);
    }

    private function addCartItem(PurchaserCart $cart, Product $product, float $qty = 10.0, float $price = 25.00): PurchaserCartItem
    {
        return PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product->id,
            'grade' => $cart->purchase_grade ?? 'A',
            'quantity' => $qty,
            'unit_price' => $price,
            'line_total' => round($qty * $price, 2),
            'is_extra_purchase' => false,
        ]);
    }

    public function test_purchaser_can_access_v2_cart_hub(): void
    {
        $cart = $this->createDraftCart();
        $product = $this->createProduct('Broccoli Fresh', 'BROC-01');
        $this->addCartItem($cart, $product, 15.0, 50.00);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser-v2.cart.index', [
            'date' => $this->testDate->toDateString(),
            'grade' => 'A',
        ]));

        $response->assertOk();
        $response->assertViewIs('purchasing.purchaser-v2.cart');
        $response->assertSee('Draft Carts');
        $response->assertSee($cart->cart_number);
    }

    public function test_unauthorized_user_cannot_access_v2_cart(): void
    {
        $guest = User::factory()->create();

        $response = $this->actingAs($guest)->get(route('purchaser-v2.cart.index', [
            'date' => $this->testDate->toDateString(),
            'grade' => 'A',
        ]));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_only_current_date_and_grade_draft_carts_are_initially_loaded(): void
    {
        $draftA = $this->createDraftCart(grade: 'A');
        $draftB = $this->createDraftCart(grade: 'B');

        // Submitted cart on same date
        $submittedCart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'business_date' => $this->testDate,
            'cart_number' => 'PC-SUBMITTED-01',
            'status' => 'submitted',
            'purchase_grade' => 'A',
        ]);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser-v2.cart.index', [
            'date' => $this->testDate->toDateString(),
            'grade' => 'A',
        ]));

        $response->assertOk();
        $response->assertSee($draftA->cart_number);
        $response->assertDontSee($draftB->cart_number);
        $response->assertDontSee($submittedCart->cart_number);
    }

    public function test_other_users_draft_carts_are_hidden(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('purchaser');

        $myCart = $this->createDraftCart($this->purchaser);
        $otherCart = $this->createDraftCart($otherUser);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser-v2.cart.index', [
            'date' => $this->testDate->toDateString(),
            'grade' => 'A',
        ]));

        $response->assertOk();
        $response->assertSee($myCart->cart_number);
        $response->assertDontSee($otherCart->cart_number);
    }

    public function test_cart_items_are_fetched_on_demand_via_json(): void
    {
        $cart = $this->createDraftCart();
        $p1 = $this->createProduct('Green Apple', 'APP-01');
        $p2 = $this->createProduct('Banana Robusta', 'BAN-01');
        $this->addCartItem($cart, $p1, 20.0, 80.00);
        $this->addCartItem($cart, $p2, 50.0, 35.00);

        $response = $this->actingAs($this->purchaser)->getJson(route('purchaser-v2.cart.items', ['cart' => $cart]));

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'cart_id' => $cart->id,
        ]);
        $response->assertJsonCount(2, 'items');
        $response->assertJsonFragment(['name' => 'Green Apple', 'sku' => 'APP-01', 'quantity' => 20]);
        $response->assertJsonFragment(['name' => 'Banana Robusta', 'sku' => 'BAN-01', 'quantity' => 50]);
    }

    public function test_supplier_search_is_bounded_and_scoped(): void
    {
        $s1 = Supplier::factory()->create(['name' => 'Mahadev Fruits', 'type' => 'Vendor']);
        $s2 = Supplier::factory()->create(['name' => 'Kaveri Produce', 'type' => 'Vendor']);

        $response = $this->actingAs($this->purchaser)->getJson(route('purchaser-v2.suppliers.search', ['q' => 'Mahadev']));

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
        ]);
        $response->assertJsonFragment(['name' => 'Mahadev Fruits']);
        $response->assertJsonMissing(['name' => 'Kaveri Produce']);
    }

    public function test_purchaser_can_create_new_supplier_and_optionally_assign_to_cart(): void
    {
        $cart = $this->createDraftCart();

        // 1. Create supplier without cart assignment
        $response = $this->actingAs($this->purchaser)->postJson(route('purchaser-v2.suppliers.store'), [
            'name' => 'New Agro Tech',
            'location' => 'Bangalore Market',
            'mobile_number' => '9876543210',
            'type' => 'Vendor',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'supplier' => [
                'name' => 'New Agro Tech',
                'location' => 'Bangalore Market',
                'mobile_number' => '9876543210',
                'type' => 'Vendor',
            ],
        ]);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'New Agro Tech',
            'location' => 'Bangalore Market',
            'mobile_number' => '9876543210',
        ]);

        // 2. Create supplier with direct cart assignment
        $responseWithCart = $this->actingAs($this->purchaser)->postJson(route('purchaser-v2.suppliers.store'), [
            'name' => 'Direct Cart Supplier',
            'city' => 'City Market',
            'mobile_number' => '9123456780',
            'type' => 'Vendor',
            'cart_id' => $cart->id,
        ]);

        $responseWithCart->assertOk();
        $responseWithCart->assertJson([
            'status' => 'success',
            'assigned' => true,
            'cart_id' => $cart->id,
        ]);

        $this->assertDatabaseHas('purchaser_carts', [
            'id' => $cart->id,
            'supplier_id' => $responseWithCart->json('supplier.id'),
        ]);
    }

    public function test_supplier_assignment_updates_draft_cart(): void
    {
        $cart = $this->createDraftCart();
        $supplier = Supplier::factory()->create(['name' => 'Green Fields Trading']);

        // Test with cart model / cart_number
        $response = $this->actingAs($this->purchaser)->patchJson(route('purchaser-v2.cart.update-supplier', ['cart' => $cart]), [
            'supplier_id' => $supplier->id,
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'supplier_id' => $supplier->id,
            'supplier_name' => 'Green Fields Trading',
        ]);

        $this->assertDatabaseHas('purchaser_carts', [
            'id' => $cart->id,
            'supplier_id' => $supplier->id,
        ]);

        // Test with numeric cart ID explicitly (e.g. /purchaser-v2/cart/2175/supplier)
        $supplier2 = Supplier::factory()->create(['name' => 'Second Supplier']);
        $response2 = $this->actingAs($this->purchaser)->patchJson("/purchaser-v2/cart/{$cart->id}/supplier", [
            'supplier_id' => $supplier2->id,
        ]);

        $response2->assertOk();
        $response2->assertJson([
            'status' => 'success',
            'supplier_id' => $supplier2->id,
            'supplier_name' => 'Second Supplier',
        ]);

        $this->assertDatabaseHas('purchaser_carts', [
            'id' => $cart->id,
            'supplier_id' => $supplier2->id,
        ]);
    }

    public function test_update_cart_items_with_grouped_quantity_validation(): void
    {
        $cart = $this->createDraftCart();
        $p = $this->createProduct('Capsicum Red', 'CAP-R');
        $item = $this->addCartItem($cart, $p, 10.0, 60.00);

        // Shop demand approved = 15.0
        $shop = Shop::factory()->create();
        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $this->testDate->toDateString(),
            'state' => 'approved',
        ]);
        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $p->id,
            'product_grade' => 'A',
            'approved_qty' => 15.0,
            'requested_qty' => 15.0,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->purchaser)->patchJson(route('purchaser-v2.cart.items.update', ['cart' => $cart]), [
            'items' => [
                [
                    'id' => $item->id,
                    'quantity' => 12.0, // Within 15.0 approved
                    'unit_price' => 65.00,
                    'notes' => 'Updated price from vendor',
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('purchaser_cart_items', [
            'id' => $item->id,
            'quantity' => 12.0,
            'unit_price' => 65.00,
            'line_total' => 780.00,
            'is_extra_purchase' => false,
            'notes' => 'Updated price from vendor',
        ]);
    }

    public function test_destroy_item_and_auto_delete_empty_cart(): void
    {
        $cart = $this->createDraftCart();
        $p1 = $this->createProduct('Item 1', 'IT-01');
        $p2 = $this->createProduct('Item 2', 'IT-02');
        $item1 = $this->addCartItem($cart, $p1, 5.0, 10.00);
        $item2 = $this->addCartItem($cart, $p2, 5.0, 10.00);

        // Delete item 1 -> cart remains
        $res1 = $this->actingAs($this->purchaser)->deleteJson(route('purchaser-v2.cart.items.destroy', [
            'cart' => $cart,
            'item' => $item1,
        ]));
        $res1->assertOk();
        $res1->assertJson(['cart_deleted' => false]);
        $this->assertDatabaseMissing('purchaser_cart_items', ['id' => $item1->id]);
        $this->assertDatabaseHas('purchaser_carts', ['id' => $cart->id]);

        // Delete item 2 -> cart becomes empty and is deleted
        $res2 = $this->actingAs($this->purchaser)->deleteJson(route('purchaser-v2.cart.items.destroy', [
            'cart' => $cart,
            'item' => $item2,
        ]));
        $res2->assertOk();
        $res2->assertJson(['cart_deleted' => true]);
        $this->assertDatabaseMissing('purchaser_cart_items', ['id' => $item2->id]);
        $this->assertDatabaseMissing('purchaser_carts', ['id' => $cart->id]);
    }

    public function test_merge_compatible_drafts_into_target_cart(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Same Supplier']);
        $targetCart = $this->createDraftCart(supplier: $supplier);
        $sourceCart = $this->createDraftCart(supplier: $supplier);

        $p1 = $this->createProduct('Product A', 'PA-01');
        $p2 = $this->createProduct('Product B', 'PB-01');

        $this->addCartItem($targetCart, $p1, 10.0, 20.00);
        $this->addCartItem($sourceCart, $p2, 15.0, 30.00);

        $response = $this->actingAs($this->purchaser)->postJson(route('purchaser-v2.cart.merge-drafts', ['cart' => $targetCart]));

        $response->assertOk();
        $response->assertJson(['status' => 'success']);

        // Source cart deleted
        $this->assertDatabaseMissing('purchaser_carts', ['id' => $sourceCart->id]);
        // Target cart now has 2 items
        $this->assertDatabaseHas('purchaser_cart_items', [
            'purchaser_cart_id' => $targetCart->id,
            'product_id' => $p1->id,
            'quantity' => 10.0,
        ]);
        $this->assertDatabaseHas('purchaser_cart_items', [
            'purchaser_cart_id' => $targetCart->id,
            'product_id' => $p2->id,
            'quantity' => 15.0,
        ]);
    }

    public function test_cart_hub_query_slope_remains_constant_across_1_5_10_25_carts(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $cart = $this->createDraftCart();
            $p = $this->createProduct("Product {$i}", "SKU-{$i}");
            $this->addCartItem($cart, $p, 5.0, 20.00);
        }

        /** @var PurchaserV2CartQuery $cartQuery */
        $cartQuery = app(PurchaserV2CartQuery::class);

        // Test 1 cart
        DB::flushQueryLog();
        DB::enableQueryLog();
        $cartQuery->getDraftCarts($this->purchaser, $this->testDate, 'A', limit: 1);
        $count1 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Test 5 carts
        DB::flushQueryLog();
        DB::enableQueryLog();
        $cartQuery->getDraftCarts($this->purchaser, $this->testDate, 'A', limit: 5);
        $count5 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Test 10 carts
        DB::flushQueryLog();
        DB::enableQueryLog();
        $cartQuery->getDraftCarts($this->purchaser, $this->testDate, 'A', limit: 10);
        $count10 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Test 25 carts
        DB::flushQueryLog();
        DB::enableQueryLog();
        $cartQuery->getDraftCarts($this->purchaser, $this->testDate, 'A', limit: 25);
        $count25 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Query count for getDraftCarts should strictly be 1 bounded query with eager loading
        $this->assertLessThanOrEqual(3, $count1);
        $this->assertLessThanOrEqual(3, $count5);
        $this->assertLessThanOrEqual(3, $count10);
        $this->assertLessThanOrEqual(3, $count25);
        $this->assertSame($count1, $count25);
    }

    public function test_cart_items_query_slope_remains_constant_across_item_counts(): void
    {
        $cart = $this->createDraftCart();
        $products = [];
        for ($i = 1; $i <= 50; $i++) {
            $p = $this->createProduct("Item Batch {$i}", "SKU-IB-{$i}");
            $this->addCartItem($cart, $p, 10.0, 20.00);
            $products[] = $p;
        }

        /** @var PurchaserV2CartQuery $cartQuery */
        $cartQuery = app(PurchaserV2CartQuery::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $cartQuery->getCartItems($cart, $this->purchaser);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Should execute bounded grouped queries (< 6 queries) regardless of 50 items
        $this->assertLessThanOrEqual(6, count($queries));
    }

    public function test_v2_cart_hub_continue_to_bill_links_directly_to_legacy_bill_route(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Legacy Link Vendor']);
        $cart = $this->createDraftCart(supplier: $supplier, grade: 'A');
        $product = $this->createProduct('Organic Carrot', 'CAR-01');
        $this->addCartItem($cart, $product, 25.0, 40.00);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser-v2.cart.index', [
            'date' => $this->testDate->toDateString(),
            'grade' => 'A',
        ]));

        $response->assertOk();
        $expectedLegacyBillUrl = route('purchaser.bill', ['cart' => $cart]);
        $response->assertSee($expectedLegacyBillUrl, false);
    }

    public function test_v2_draft_cart_is_accepted_seamlessly_by_legacy_bill_page_without_data_loss(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Royal Fresh Agro']);
        $cart = $this->createDraftCart(supplier: $supplier, grade: 'A');
        $product1 = $this->createProduct('Premium Mango', 'MAN-01');
        $product2 = $this->createProduct('Crisp Apple', 'APP-02');
        $this->addCartItem($cart, $product1, 30.0, 120.00);
        $this->addCartItem($cart, $product2, 15.0, 80.00);

        // Access legacy bill page using the exact same PurchaserCart
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.bill', ['cart' => $cart]));

        $response->assertOk();
        $response->assertSee('Royal Fresh Agro');
        $response->assertSee('Premium Mango');
        $response->assertSee('Crisp Apple');
        $response->assertSee('120');
        $response->assertSee('80');

        // Modal shell and open trigger removed
        $response->assertDontSee('id="payment-update-modal"', false);
        $response->assertDontSee('openBillPaymentModal()', false);

        // Payment section is directly inline in the page DOM
        $response->assertSee('id="payment-modal-total"', false);
        $response->assertSee('id="additional_paid_amount"', false);
        $response->assertSee('id="pm-btn-Cash"', false);
        $response->assertSee('id="pm-btn-Credit"', false);
        $response->assertSee('Submit Payment');
        $response->assertSee('Thank You');
    }

    public function test_legacy_bill_enforces_purchaser_authorization_on_v2_created_cart(): void
    {
        $otherPurchaser = User::factory()->create();
        $otherPurchaser->assignRole('purchaser');

        $cart = $this->createDraftCart($this->purchaser);

        // Other purchaser cannot open this cart on legacy bill
        $response = $this->actingAs($otherPurchaser)->get(route('purchaser.bill', ['cart' => $cart]));
        $this->assertTrue(in_array($response->getStatusCode(), [403, 404], true));
    }
}
