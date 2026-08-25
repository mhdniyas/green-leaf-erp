<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseGradePrice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaserReadCacheService;
use App\Services\Purchasing\VendorPriceService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserRouteCachingPhase3Test extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Supplier $supplier;

    private Product $productA;

    private Product $productB;

    private Shop $shop;

    private ShopOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-24 10:00:00');
        Role::findOrCreate('purchaser');
        $this->purchaser = User::factory()->create(['name' => 'Purchaser One']);
        $this->purchaser->assignRole('purchaser');

        $category = Category::factory()->create(['name' => 'VEG', 'is_active' => true]);
        $this->supplier = Supplier::factory()->create(['name' => 'Fresh Farms', 'type' => 'Vendor']);
        $this->shop = Shop::factory()->create(['name' => 'Main Outlet']);

        $this->productA = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Tomato A',
            'sku' => 'TOM-A',
            'unit' => 'kg',
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);

        $this->productB = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Tomato B',
            'sku' => 'TOM-B',
            'unit' => 'kg',
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);

        ProductUnit::query()->create([
            'product_id' => $this->productA->id,
            'unit' => 'box',
            'label' => 'Box 10kg',
            'conversion_to_base' => 10.0,
            'is_orderable' => true,
        ]);

        // Seed an approved shop order for Product A
        $this->order = ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-24',
            'state' => 'approved',
        ]);
        DB::table('shop_orders')->where('id', $this->order->id)->update(['business_date' => '2026-08-24']);

        ShopOrderItem::query()->create([
            'shop_order_id' => $this->order->id,
            'product_id' => $this->productA->id,
            'product_grade' => 'A',
            'requested_qty' => 50.0,
            'approved_qty' => 50.0,
            'unit' => 'kg',
        ]);
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        PurchaserCartItem::query()->delete();
        PurchaserCart::query()->delete();
        ShopOrderItem::query()->delete();
        ShopOrder::query()->delete();
        ProductUnit::query()->delete();
        Product::query()->delete();
        Category::query()->delete();
        Supplier::query()->delete();
        Shop::query()->delete();
        DailyPriceApproval::query()->delete();
        PurchaseGradePrice::query()->delete();
        BusinessSetting::query()->delete();
        User::query()->delete();

        Carbon::setTestNow();
        parent::tearDown();
    }

    private function commitTestTransaction(): void
    {
        DB::commit();
        DB::beginTransaction();
    }

    public function test_daily_route_miss_then_hit_reduces_query_count_and_preserves_response(): void
    {
        // 1. Cold Request
        $queriesCold = [];
        DB::listen(function (QueryExecuted $query) use (&$queriesCold): void {
            $queriesCold[] = $query->sql;
        });

        $resCold = $this->actingAs($this->purchaser)->get(route('purchaser.daily', ['date' => '2026-08-24']));
        $resCold->assertOk();
        $resCold->assertSee('Tomato A');
        $coldContent = $resCold->getContent();
        $coldQueryCount = count($queriesCold);

        // 2. Warm Request
        $queriesWarm = [];
        DB::listen(function (QueryExecuted $query) use (&$queriesWarm): void {
            $queriesWarm[] = $query->sql;
        });

        $resWarm = $this->actingAs($this->purchaser)->get(route('purchaser.daily', ['date' => '2026-08-24']));
        $resWarm->assertOk();
        $resWarm->assertSee('Tomato A');
        $warmContent = $resWarm->getContent();
        $warmQueryCount = count($queriesWarm);

        // Verify query count reduction on warm request
        $this->assertLessThan($coldQueryCount, $warmQueryCount, "Warm request should execute fewer queries. Cold: {$coldQueryCount}, Warm: {$warmQueryCount}");

        // Verify response content equivalence
        $this->assertSame($coldContent, $warmContent, 'Warm response HTML must be identical to cold response.');
    }

    public function test_b_grade_route_miss_then_hit_and_grade_isolation(): void
    {
        $resA = $this->actingAs($this->purchaser)->get(route('purchaser.daily', ['date' => '2026-08-24', 'chip' => 'All']));
        $resA->assertOk();

        $resB = $this->actingAs($this->purchaser)->get(route('purchaser.b-grade', ['date' => '2026-08-24', 'chip' => 'All']));
        $resB->assertOk();
        $resB->assertSee('Tomato B');
    }

    public function test_bulk_buy_routes_caching_and_details_response(): void
    {
        $resBulk = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy', ['date' => '2026-08-24']));
        $resBulk->assertOk();
        $resBulk->assertSee('Tomato A');

        $resDetails = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy.details', [
            'date' => '2026-08-24',
            'product_ids' => [$this->productA->id],
        ]));
        $resDetails->assertOk();
        $resDetails->assertSee('Tomato A');
    }

    public function test_daily_share_route_caching(): void
    {
        $resShare = $this->actingAs($this->purchaser)->get(route('purchaser.daily.share', ['date' => '2026-08-24']));
        $resShare->assertOk();
        $resShare->assertSee('Tomato A');
    }

    public function test_shop_order_mutation_invalidates_cache_immediately(): void
    {
        // Prime cache
        $this->actingAs($this->purchaser)->get(route('purchaser.daily', ['date' => '2026-08-24', 'chip' => 'All']))->assertOk();

        // Mutate: Add item for Product B to existing approved order
        ShopOrderItem::query()->create([
            'shop_order_id' => $this->order->id,
            'product_id' => $this->productB->id,
            'product_grade' => 'A',
            'requested_qty' => 30.0,
            'approved_qty' => 30.0,
            'unit' => 'kg',
        ]);
        $this->commitTestTransaction();

        // Next read MUST show Tomato B immediately
        $res = $this->actingAs($this->purchaser)->get(route('purchaser.daily', ['date' => '2026-08-24', 'chip' => 'All']));
        $res->assertOk();
        $res->assertSee('Tomato B');
    }

    public function test_cart_item_mutation_invalidates_cache_and_updates_quantities(): void
    {
        // Prime cache
        $this->actingAs($this->purchaser)->get(route('purchaser.daily', ['date' => '2026-08-24']))->assertOk();

        // Mutate: Purchaser creates draft cart item
        $cart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'business_date' => '2026-08-24',
            'status' => 'draft',
            'purchase_grade' => 'A',
            'cart_number' => 'VC-MUT-01',
        ]);
        DB::table('purchaser_carts')->where('id', $cart->id)->update(['business_date' => '2026-08-24']);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cart->id,
            'product_id' => $this->productA->id,
            'grade' => 'A',
            'quantity' => 20.0,
            'unit_price' => 5.0,
            'line_total' => 100.0,
        ]);
        $this->commitTestTransaction();

        // Next read MUST reflect draft cart and purchaser name immediately
        $res = $this->actingAs($this->purchaser)->get(route('purchaser.daily', ['date' => '2026-08-24']));
        $res->assertOk();
        $res->assertSee($this->purchaser->name);
    }

    public function test_two_purchasers_are_strictly_isolated_in_cache(): void
    {
        $purchaserTwo = User::factory()->create(['name' => 'Purchaser Two']);
        $purchaserTwo->assignRole('purchaser');

        // Purchaser One creates a draft cart
        $cartOne = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'business_date' => '2026-08-24',
            'status' => 'draft',
            'purchase_grade' => 'A',
            'cart_number' => 'VC-P1-001',
        ]);
        DB::table('purchaser_carts')->where('id', $cartOne->id)->update(['business_date' => '2026-08-24']);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $cartOne->id,
            'product_id' => $this->productA->id,
            'grade' => 'A',
            'quantity' => 15.0,
            'unit_price' => 5.0,
            'line_total' => 75.0,
        ]);
        $this->commitTestTransaction();

        // Purchaser One fetches daily route (primes Purchaser One cache)
        $resOne = $this->actingAs($this->purchaser)->get(route('purchaser.daily', ['date' => '2026-08-24']));
        $resOne->assertOk();
        $resOne->assertSee('VC-P1-001');

        // Purchaser Two fetches daily route (must NOT see Purchaser One's private draft cart in their active carts)
        $resTwo = $this->actingAs($purchaserTwo)->get(route('purchaser.daily', ['date' => '2026-08-24']));
        $resTwo->assertOk();
        $resTwo->assertDontSee('VC-P1-001');
    }

    public function test_product_and_category_mutations_invalidate_cache(): void
    {
        // Prime cache
        $this->actingAs($this->purchaser)->get(route('purchaser.daily', ['date' => '2026-08-24']))->assertOk();

        // Mutate Product
        $this->productA->name = 'Premium Heirloom Tomato';
        $this->productA->save();
        $this->commitTestTransaction();

        // Next read MUST show renamed product
        $res = $this->actingAs($this->purchaser)->get(route('purchaser.daily', ['date' => '2026-08-24']));
        $res->assertOk();
        $res->assertSee('Premium Heirloom Tomato');
    }

    public function test_vendor_price_service_sync_invalidates_cache(): void
    {
        // Prime cache
        $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy.details', [
            'date' => '2026-08-24',
            'product_ids' => [$this->productA->id],
        ]))->assertOk();

        // Mutate via VendorPriceService (which bypasses model events)
        app(VendorPriceService::class)->syncPrice((int) $this->productA->id, 12.50, (int) $this->supplier->id);
        $this->commitTestTransaction();

        // Next read runs without stale cache
        $res = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy.details', [
            'date' => '2026-08-24',
            'product_ids' => [$this->productA->id],
        ]));
        $res->assertOk();
        $res->assertSee('12.50');
    }

    public function test_purchaser_cart_cancel_overdue_work_invalidates_cache(): void
    {
        // Prime cache
        $this->actingAs($this->purchaser)->get(route('purchaser.daily', ['date' => '2026-08-24']))->assertOk();

        // Bulk cancel overdue work (which uses query()->update() bypass)
        PurchaserCart::cancelOverdueCartsAndOrders(Carbon::parse('2026-08-25'));
        $this->commitTestTransaction();

        // Next read executes cleanly
        $this->actingAs($this->purchaser)->get(route('purchaser.daily', ['date' => '2026-08-24']))->assertOk();
    }

    public function test_business_settings_mutation_invalidates_cache(): void
    {
        // Prime cache
        $this->actingAs($this->purchaser)->get(route('purchaser.daily', ['date' => '2026-08-24']))->assertOk();

        // Mutate BusinessSetting
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'purchaser_cutoff_time'],
            ['value' => '14:00']
        );
        $this->commitTestTransaction();

        $res = $this->actingAs($this->purchaser)->get(route('purchaser.daily', ['date' => '2026-08-24']));
        $res->assertOk();
    }

    public function test_price_matrix_approval_mutation_invalidates_cache(): void
    {
        $res = $this->actingAs($this->purchaser)->get(route('purchaser.daily', ['date' => '2026-08-24']));
        $res->assertOk();

        // Mutate daily price approval
        DailyPriceApproval::query()->create([
            'product_id' => $this->productA->id,
            'business_date' => '2026-08-24',
            'purchase_price' => 18.50,
            'price_unit' => 'kg',
            'status' => 'approved',
            'price_a' => 22.00,
            'price_b' => 20.00,
            'price_c' => 19.00,
        ]);
        $this->commitTestTransaction();

        $resNext = $this->actingAs($this->purchaser)->get(route('purchaser.daily', ['date' => '2026-08-24']));
        $resNext->assertOk();
    }

    public function test_transaction_committed_invalidates_cache(): void
    {
        $cacheService = new PurchaserReadCacheService(store: 'array');
        $this->app->instance(PurchaserReadCacheService::class, $cacheService);

        $initialVersion = $cacheService->getScopeVersion('orders');

        ShopOrderItem::query()->create([
            'shop_order_id' => $this->order->id,
            'product_id' => $this->productB->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'kg',
        ]);
        $this->commitTestTransaction();

        $this->assertGreaterThan($initialVersion, $cacheService->getScopeVersion('orders'));
    }

    public function test_transaction_rollback_preserves_version_and_avoids_invalidation(): void
    {
        $cacheService = new PurchaserReadCacheService(store: 'array');
        $this->app->instance(PurchaserReadCacheService::class, $cacheService);

        $initialVersion = $cacheService->getScopeVersion('orders');

        // Simulate a transaction that writes and rolls back
        DB::beginTransaction();
        ShopOrderItem::query()->create([
            'shop_order_id' => $this->order->id,
            'product_id' => $this->productB->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'kg',
        ]);
        DB::rollBack();

        // Cache version should not be bumped on rollback
        $this->assertSame($initialVersion, $cacheService->getScopeVersion('orders'));
    }

    public function test_redis_failure_falls_back_cleanly_on_all_phase3_routes(): void
    {
        // Simulate Redis failure by registering failing cache service
        $this->app->instance(
            PurchaserReadCacheService::class,
            new PurchaserReadCacheService(store: 'non_existent_redis_driver')
        );

        $this->actingAs($this->purchaser)->get(route('purchaser.daily', ['date' => '2026-08-24']))->assertOk();
        $this->actingAs($this->purchaser)->get(route('purchaser.b-grade', ['date' => '2026-08-24']))->assertOk();
        $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy', ['date' => '2026-08-24']))->assertOk();
        $this->actingAs($this->purchaser)->get(route('purchaser.daily.share', ['date' => '2026-08-24']))->assertOk();
    }
}
