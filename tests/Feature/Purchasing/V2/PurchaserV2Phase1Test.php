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
use App\Queries\Purchasing\V2\PurchaserV2DailyQuery;
use App\Queries\Purchasing\V2\PurchaserV2DashboardQuery;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserV2Phase1Test extends TestCase
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

    private function createProduct(string $name, string $sku, bool $isActive = true, bool $showInPurchaser = true): Product
    {
        return Product::query()->create([
            'category_id' => $this->category->id,
            'name' => $name,
            'sku' => $sku,
            'unit' => 'kg',
            'base_price' => 20.0,
            'vendor_price' => 18.0,
            'is_active' => $isActive,
            'show_in_purchaser_order' => $showInPurchaser,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_v2(): void
    {
        $response = $this->get(route('purchaser-v2.dashboard'));
        $response->assertRedirect(route('login'));

        $response = $this->get(route('purchaser-v2.daily'));
        $response->assertRedirect(route('login'));

        $response = $this->getJson(route('purchaser-v2.products.search'));
        $response->assertUnauthorized();
    }

    public function test_non_purchaser_user_is_forbidden(): void
    {
        $regularUser = User::factory()->create();

        // Web requests redirect to dashboard via app exception handler
        $response = $this->actingAs($regularUser)->get(route('purchaser-v2.dashboard'));
        $response->assertRedirect(route('dashboard'));

        $response = $this->actingAs($regularUser)->get(route('purchaser-v2.daily'));
        $response->assertRedirect(route('dashboard'));

        // JSON requests return 403
        $jsonResponse = $this->actingAs($regularUser)->getJson(route('purchaser-v2.daily.products'));
        $jsonResponse->assertForbidden();
    }

    public function test_v2_dashboard_returns_lightweight_aggregates_with_controlled_query_count(): void
    {
        $product = $this->createProduct('Carrot Fresh', 'CAR-001');

        $shop = Shop::factory()->create();
        $shopOrder = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $this->testDate->toDateString(),
            'state' => 'approved',
        ]);

        ShopOrderItem::query()->create([
            'shop_order_id' => $shopOrder->id,
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 50.0,
            'approved_qty' => 50.0,
            'unit' => 'kg',
        ]);

        $supplier = Supplier::factory()->create(['type' => 'Vendor']);
        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $supplier->id,
            'business_date' => $this->testDate->toDateString(),
            'purchase_grade' => 'A',
            'status' => 'submitted',
            'cart_number' => 'VC-TEST-001',
            'paid_amount' => 1250.00,
        ]);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $product->id,
            'grade' => 'A',
            'quantity' => 50.0,
            'unit_price' => 25.0,
            'line_total' => 1250.00,
        ]);

        // Measure V2 Query Execution directly
        /** @var PurchaserV2DashboardQuery $queryService */
        $queryService = app(PurchaserV2DashboardQuery::class);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $summary = $queryService->executeQueries($this->testDate, 'A', $this->purchaser);
        $v2DataQueries = count(DB::getQueryLog());

        // Assert strictly <= 3 V2 aggregate queries on cache miss
        $this->assertLessThanOrEqual(3, $v2DataQueries, "Dashboard V2 data queries exceeded 3 (was {$v2DataQueries})");

        $this->assertSame(1, $summary['intended_count']);
        $this->assertSame(1, $summary['fulfilled_count']);
        $this->assertSame(0, $summary['pending_count']);
        $this->assertEquals(1250.00, $summary['today_purchased_amount']);
        $this->assertEquals(50.0, $summary['total_approved_qty']);
        $this->assertEquals(50.0, $summary['total_purchased_qty']);

        // Check Web response
        $response = $this->actingAs($this->purchaser)->get(route('purchaser-v2.dashboard', [
            'date' => $this->testDate->toDateString(),
            'grade' => 'A',
        ]));

        $response->assertOk();
        $response->assertViewHas('summary');
        $response->assertSee('Procurement Overview');
    }

    public function test_v2_daily_loads_bounded_products_first_page(): void
    {
        $shop = Shop::factory()->create();
        $shopOrder = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $this->testDate->toDateString(),
            'state' => 'approved',
        ]);

        // Create 25 products with demand directly
        for ($i = 1; $i <= 25; $i++) {
            $prod = $this->createProduct(sprintf('Produce Item %02d', $i), sprintf('PROD-%02d', $i));

            ShopOrderItem::query()->create([
                'shop_order_id' => $shopOrder->id,
                'product_id' => $prod->id,
                'product_grade' => 'A',
                'requested_qty' => 10.0 + $i,
                'approved_qty' => 10.0 + $i,
                'unit' => 'kg',
            ]);
        }

        $response = $this->actingAs($this->purchaser)->get(route('purchaser-v2.daily', [
            'date' => $this->testDate->toDateString(),
            'grade' => 'A',
        ]));

        $response->assertOk();
        $response->assertViewHas('initialItems', function (array $items): bool {
            // Must strictly load bounded 20 items on page 1
            return count($items) === 20;
        });
        $response->assertViewHas('pagination', function (array $pagination): bool {
            return $pagination['total_count'] === 25 && $pagination['has_more'] === true;
        });
    }

    public function test_v2_daily_products_search_endpoint_with_pagination(): void
    {
        $shop = Shop::factory()->create();
        $shopOrder = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $this->testDate->toDateString(),
            'state' => 'approved',
        ]);

        $prodA = $this->createProduct('Fresh Tomato Red', 'TOM-001');
        ShopOrderItem::query()->create([
            'shop_order_id' => $shopOrder->id,
            'product_id' => $prodA->id,
            'product_grade' => 'A',
            'requested_qty' => 40.0,
            'approved_qty' => 40.0,
            'unit' => 'kg',
        ]);

        $prodB = $this->createProduct('Green Apple Crunchy', 'APP-001');
        ShopOrderItem::query()->create([
            'shop_order_id' => $shopOrder->id,
            'product_id' => $prodB->id,
            'product_grade' => 'A',
            'requested_qty' => 15.0,
            'approved_qty' => 15.0,
            'unit' => 'kg',
        ]);

        // Search for 'Tomato'
        $response = $this->actingAs($this->purchaser)->getJson(route('purchaser-v2.daily.products', [
            'date' => $this->testDate->toDateString(),
            'grade' => 'A',
            'q' => 'Tomato',
        ]));

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'pagination' => [
                'total_count' => 1,
            ],
        ]);
        $response->assertJsonFragment(['name' => 'Fresh Tomato Red']);
        $response->assertJsonMissing(['name' => 'Green Apple Crunchy']);
    }

    public function test_v2_daily_product_detail_endpoint_returns_isolated_demand(): void
    {
        $shopA = Shop::factory()->create(['name' => 'Shop Downtown']);
        $shopB = Shop::factory()->create(['name' => 'Shop Uptown']);

        $orderA = ShopOrder::factory()->create([
            'shop_id' => $shopA->id,
            'order_number' => 'ORD-1001',
            'business_date' => $this->testDate->toDateString(),
            'state' => 'approved',
        ]);
        $orderB = ShopOrder::factory()->create([
            'shop_id' => $shopB->id,
            'order_number' => 'ORD-1002',
            'business_date' => $this->testDate->toDateString(),
            'state' => 'approved',
        ]);

        $targetProduct = $this->createProduct('Potato Standard', 'POT-001');

        ShopOrderItem::query()->create([
            'shop_order_id' => $orderA->id,
            'product_id' => $targetProduct->id,
            'product_grade' => 'A',
            'requested_qty' => 20.0,
            'approved_qty' => 20.0,
            'unit' => 'kg',
        ]);

        ShopOrderItem::query()->create([
            'shop_order_id' => $orderB->id,
            'product_id' => $targetProduct->id,
            'product_grade' => 'A',
            'requested_qty' => 30.0,
            'approved_qty' => 30.0,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->purchaser)->getJson(route('purchaser-v2.daily.product-detail', [
            'product' => $targetProduct->id,
            'date' => $this->testDate->toDateString(),
            'grade' => 'A',
        ]));

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'product' => [
                    'id' => $targetProduct->id,
                    'name' => 'Potato Standard',
                ],
                'summary' => [
                    'intended_qty' => 50.0,
                    'remaining_qty' => 50.0,
                ],
            ],
        ]);

        $data = $response->json('data');
        $this->assertCount(2, $data['shops']);
        $this->assertSame('Shop Downtown', $data['shops'][0]['shop_name']);
        $this->assertSame('Shop Uptown', $data['shops'][1]['shop_name']);
    }

    public function test_reusable_product_search_respects_permissions_and_bounds(): void
    {
        // 1. Active & visible in purchaser
        $visibleProduct = $this->createProduct('Visible Onion', 'ONI-001', true, true);

        // 2. Inactive product
        $this->createProduct('Inactive Onion', 'ONI-002', false, true);

        // 3. Hidden from purchaser
        $this->createProduct('Hidden Onion', 'ONI-003', true, false);

        $response = $this->actingAs($this->purchaser)->getJson(route('purchaser-v2.products.search', [
            'q' => 'Onion',
        ]));

        $response->assertOk();
        $items = $response->json('data');

        $this->assertCount(1, $items);
        $this->assertSame($visibleProduct->id, $items[0]['id']);
        $this->assertSame('Visible Onion', $items[0]['name']);
    }

    public function test_query_slope_remains_o1_constant_as_catalog_scales(): void
    {
        $shop = Shop::factory()->create();
        $shopOrder = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $this->testDate->toDateString(),
            'state' => 'approved',
        ]);

        /** @var PurchaserV2DailyQuery $dailyQuery */
        $dailyQuery = app(PurchaserV2DailyQuery::class);

        // Scenario 1: 5 products
        for ($i = 1; $i <= 5; $i++) {
            $p = $this->createProduct("Batch1 Product {$i}", "B1-{$i}");
            ShopOrderItem::query()->create([
                'shop_order_id' => $shopOrder->id,
                'product_id' => $p->id,
                'product_grade' => 'A',
                'requested_qty' => 5.0,
                'approved_qty' => 5.0,
                'unit' => 'kg',
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $dailyQuery->getProducts($this->testDate, 'A', $this->purchaser, null, 'all', 20, 1);
        $queriesWith5 = count(DB::getQueryLog());

        // Scenario 2: Add 35 more products (total 40 products)
        for ($i = 6; $i <= 40; $i++) {
            $p = $this->createProduct("Batch2 Product {$i}", "B2-{$i}");
            ShopOrderItem::query()->create([
                'shop_order_id' => $shopOrder->id,
                'product_id' => $p->id,
                'product_grade' => 'A',
                'requested_qty' => 5.0,
                'approved_qty' => 5.0,
                'unit' => 'kg',
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $dailyQuery->getProducts($this->testDate, 'A', $this->purchaser, null, 'all', 20, 1);
        $queriesWith40 = count(DB::getQueryLog());

        // Assert query slope is flat O(1)
        $this->assertSame(
            $queriesWith5,
            $queriesWith40,
            "Query count grew from {$queriesWith5} to {$queriesWith40} as catalog scaled! Must be O(1) constant."
        );
    }

    public function test_legacy_purchaser_flow_remains_completely_unaffected(): void
    {
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.daily'));
        $response->assertOk();

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.dashboard'));
        $response->assertRedirect(route('purchaser.daily'));
    }
}
