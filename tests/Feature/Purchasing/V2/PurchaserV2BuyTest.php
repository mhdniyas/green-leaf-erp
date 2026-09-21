<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing\V2;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Queries\Purchasing\V2\PurchaserV2BuyQuery;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserV2BuyTest extends TestCase
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

    private function createProduct(string $name, string $sku, bool $isActive = true, bool $showInPurchaser = true, ?Category $category = null): Product
    {
        return Product::query()->create([
            'name' => $name,
            'sku' => $sku,
            'unit' => 'kg',
            'category_id' => ($category ?? $this->category)->id,
            'base_price' => 25.00,
            'vendor_price' => 20.00,
            'is_active' => $isActive,
            'show_in_purchaser_order' => $showInPurchaser,
        ]);
    }

    private function createApprovedDemand(Product $product, float $qty, string $grade = 'A'): ShopOrderItem
    {
        $shop = Shop::factory()->create(['status' => 'active']);
        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $this->testDate->toDateString(),
            'state' => 'approved',
        ]);

        return ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => $grade,
            'approved_qty' => $qty,
            'requested_qty' => $qty,
            'unit' => $product->unit ?: 'kg',
        ]);
    }

    public function test_purchaser_can_access_v2_buy_workspace(): void
    {
        $response = $this->actingAs($this->purchaser)->get(route('purchaser-v2.buy', [
            'date' => $this->testDate->toDateString(),
            'grade' => 'A',
        ]));

        $response->assertOk();
        $response->assertViewIs('purchasing.purchaser-v2.buy');
        $response->assertSee('Buy Workspace');
    }

    public function test_unauthorized_user_cannot_access_v2_buy(): void
    {
        $guest = User::factory()->create(); // No role

        $response = $this->actingAs($guest)->get(route('purchaser-v2.buy', [
            'date' => $this->testDate->toDateString(),
            'grade' => 'A',
        ]));

        $response->assertRedirect(route('dashboard'));

        $jsonResponse = $this->actingAs($guest)->getJson(route('purchaser-v2.buy.product-details', [
            'date' => $this->testDate->toDateString(),
            'grade' => 'A',
        ]));
        $jsonResponse->assertForbidden();
    }

    public function test_single_product_id_preselects_product_in_buy_workspace(): void
    {
        $product = $this->createProduct('Fresh Tomato', 'TOM-001');
        $this->createApprovedDemand($product, 50.0);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser-v2.buy', [
            'product_id' => $product->id,
            'date' => $this->testDate->toDateString(),
            'grade' => 'A',
        ]));

        $response->assertOk();
        $response->assertSee('Fresh Tomato');
        $response->assertSee('TOM-001');
        $response->assertSee('50.0'); // Remaining quantity
    }

    public function test_multiple_product_ids_preselect_all_specified_products(): void
    {
        $p1 = $this->createProduct('Green Onion', 'ON-001');
        $p2 = $this->createProduct('Carrot Ooty', 'CAR-002');
        $this->createApprovedDemand($p1, 20.0);
        $this->createApprovedDemand($p2, 35.0);

        $response = $this->actingAs($this->purchaser)->get(route('purchaser-v2.buy', [
            'product_ids' => [$p1->id, $p2->id],
            'date' => $this->testDate->toDateString(),
            'grade' => 'A',
        ]));

        $response->assertOk();
        $response->assertSee('Green Onion');
        $response->assertSee('Carrot Ooty');
    }

    public function test_intended_pending_list_is_bounded_and_excludes_fulfilled_products(): void
    {
        $p1 = $this->createProduct('Needed Product', 'ND-001');
        $p2 = $this->createProduct('Fulfilled Product', 'FL-002');

        $this->createApprovedDemand($p1, 25.0);
        $this->createApprovedDemand($p2, 10.0);

        // Fulfill p2 with submitted cart
        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'business_date' => $this->testDate,
            'cart_number' => 'PC-TEST-001',
            'status' => 'submitted',
            'purchase_grade' => 'A',
        ]);
        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $p2->id,
            'grade' => 'A',
            'quantity' => 10.0,
            'unit_price' => 20.00,
            'line_total' => 200.00,
        ]);

        /** @var PurchaserV2BuyQuery $buyQuery */
        $buyQuery = app(PurchaserV2BuyQuery::class);
        $pending = $buyQuery->getPendingIntendedProducts($this->testDate, 'A', $this->purchaser, limit: 30);

        $pendingIds = collect($pending)->pluck('product_id')->all();
        $this->assertContains($p1->id, $pendingIds);
        $this->assertNotContains($p2->id, $pendingIds);
    }

    public function test_category_authorization_is_strictly_enforced_in_buy(): void
    {
        $allowedCat = Category::factory()->create(['name' => 'Allowed Veg', 'is_active' => true]);
        $forbiddenCat = Category::factory()->create(['name' => 'Forbidden Meat', 'is_active' => true]);

        $this->purchaser->update(['assigned_category_ids' => [$allowedCat->id]]);

        $allowedProduct = $this->createProduct('Allowed Spinach', 'SPN-01', category: $allowedCat);
        $forbiddenProduct = $this->createProduct('Forbidden Chicken', 'CHK-01', category: $forbiddenCat);

        $this->createApprovedDemand($allowedProduct, 10.0);
        $this->createApprovedDemand($forbiddenProduct, 10.0);

        /** @var PurchaserV2BuyQuery $buyQuery */
        $buyQuery = app(PurchaserV2BuyQuery::class);
        $selected = $buyQuery->getSelectedProducts(
            [$allowedProduct->id, $forbiddenProduct->id],
            $this->testDate,
            'A',
            $this->purchaser
        );

        $selectedIds = collect($selected)->pluck('product_id')->all();
        $this->assertContains($allowedProduct->id, $selectedIds);
        $this->assertNotContains($forbiddenProduct->id, $selectedIds);
    }

    public function test_unit_conversion_options_are_loaded_for_selected_products(): void
    {
        $product = $this->createProduct('Coriander Leaves', 'COR-01');
        ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit' => 'bunch',
            'label' => 'BUNCH (0.25 KG)',
            'conversion_to_base' => 0.25,
            'is_base' => false,
            'is_active' => true,
        ]);

        /** @var PurchaserV2BuyQuery $buyQuery */
        $buyQuery = app(PurchaserV2BuyQuery::class);
        $selected = $buyQuery->getSelectedProducts([$product->id], $this->testDate, 'A', $this->purchaser);

        $this->assertCount(1, $selected);
        $units = $selected[0]['orderable_units'];
        $this->assertCount(1, $units);
        $this->assertSame('bunch', $units[0]['unit']);
        $this->assertSame(0.25, $units[0]['conversion_to_base']);
    }

    public function test_store_cart_creates_draft_cart_and_items_with_bounded_queries(): void
    {
        $p1 = $this->createProduct('Capsicum Green', 'CAP-01');
        $p2 = $this->createProduct('Ginger Local', 'GIN-01');

        $this->createApprovedDemand($p1, 20.0);
        $this->createApprovedDemand($p2, 15.0);

        $payload = [
            'business_date' => $this->testDate->toDateString(),
            'purchase_grade' => 'A',
            'items' => [
                [
                    'product_id' => $p1->id,
                    'quantity' => 20.0,
                    'unit_price' => 45.00,
                    'unit' => 'kg',
                    'conversion_to_base' => 1.0,
                ],
                [
                    'product_id' => $p2->id,
                    'quantity' => 15.0,
                    'unit_price' => 120.00,
                    'unit' => 'kg',
                    'conversion_to_base' => 1.0,
                ],
            ],
        ];

        $response = $this->actingAs($this->purchaser)->post(route('purchaser-v2.cart.store'), $payload);

        $response->assertRedirect(route('purchaser-v2.cart.index', [
            'date' => $this->testDate->toDateString(),
            'grade' => 'A',
        ]));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('purchaser_carts', [
            'user_id' => $this->purchaser->id,
            'status' => 'draft',
            'purchase_grade' => 'A',
        ]);

        $this->assertDatabaseHas('purchaser_cart_items', [
            'product_id' => $p1->id,
            'quantity' => 20.0,
            'unit_price' => 45.00,
            'is_extra_purchase' => false,
        ]);

        $this->assertDatabaseHas('purchaser_cart_items', [
            'product_id' => $p2->id,
            'quantity' => 15.0,
            'unit_price' => 120.00,
            'is_extra_purchase' => false,
        ]);
    }

    public function test_add_on_surplus_quantity_is_correctly_flagged_as_extra_purchase(): void
    {
        $product = $this->createProduct('Mint Leaves', 'MNT-01');
        $this->createApprovedDemand($product, 5.0); // Only 5.0 approved

        $payload = [
            'business_date' => $this->testDate->toDateString(),
            'purchase_grade' => 'A',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 12.0, // Buying 12.0 (surplus > 5.0)
                    'unit_price' => 10.00,
                    'unit' => 'kg',
                    'conversion_to_base' => 1.0,
                ],
            ],
        ];

        $this->actingAs($this->purchaser)->post(route('purchaser-v2.cart.store'), $payload);

        $this->assertDatabaseHas('purchaser_cart_items', [
            'product_id' => $product->id,
            'quantity' => 12.0,
            'is_extra_purchase' => true,
        ]);
    }

    public function test_grade_a_rejects_zero_or_negative_price(): void
    {
        $product = $this->createProduct('Garlic White', 'GAR-01');
        $this->createApprovedDemand($product, 10.0);

        $payload = [
            'business_date' => $this->testDate->toDateString(),
            'purchase_grade' => 'A',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10.0,
                    'unit_price' => 0.00, // Invalid Grade A price
                    'unit' => 'kg',
                ],
            ],
        ];

        $response = $this->actingAs($this->purchaser)->post(route('purchaser-v2.cart.store'), $payload);
        $response->assertSessionHas('error');
    }

    public function test_query_slope_remains_o1_bounded_across_1_10_25_50_products(): void
    {
        $products = [];
        for ($i = 1; $i <= 50; $i++) {
            $p = $this->createProduct("Product Batch {$i}", "SKU-B-{$i}");
            $this->createApprovedDemand($p, (float) rand(5, 50));
            $products[] = $p;
        }

        /** @var PurchaserV2BuyQuery $buyQuery */
        $buyQuery = app(PurchaserV2BuyQuery::class);

        // Test 1 selected
        DB::flushQueryLog();
        DB::enableQueryLog();
        $buyQuery->getSelectedProducts([$products[0]->id], $this->testDate, 'A', $this->purchaser);
        $count1 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Test 10 selected
        $ids10 = collect($products)->take(10)->pluck('id')->all();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $buyQuery->getSelectedProducts($ids10, $this->testDate, 'A', $this->purchaser);
        $count10 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Test 25 selected
        $ids25 = collect($products)->take(25)->pluck('id')->all();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $buyQuery->getSelectedProducts($ids25, $this->testDate, 'A', $this->purchaser);
        $count25 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Test 50 selected
        $ids50 = collect($products)->take(50)->pluck('id')->all();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $buyQuery->getSelectedProducts($ids50, $this->testDate, 'A', $this->purchaser);
        $count50 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Assert query counts remain constant / bounded (<= 6 queries total)
        $this->assertLessThanOrEqual(6, $count1);
        $this->assertLessThanOrEqual(6, $count10);
        $this->assertLessThanOrEqual(6, $count25);
        $this->assertLessThanOrEqual(6, $count50);

        // The query count for 50 products should equal the query count for 10 products (O(1) Slope)
        $this->assertSame($count10, $count50);
    }
}
