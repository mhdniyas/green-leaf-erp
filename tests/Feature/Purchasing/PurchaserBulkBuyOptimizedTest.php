<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaserCart;
use App\Models\PurchaserCartItem;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaserReadCacheService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserBulkBuyOptimizedTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaser;

    private Category $category;

    private Product $productA;

    private Product $productB;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-24 10:00:00');
        Role::findOrCreate('purchaser');
        Role::findOrCreate('admin');

        $this->purchaser = User::factory()->create(['name' => 'John Purchaser']);
        $this->purchaser->assignRole('purchaser');

        $this->category = Category::factory()->create(['name' => 'VEG', 'is_active' => true]);
        $this->supplier = Supplier::factory()->create(['name' => 'Fresh Farm', 'type' => 'Vendor']);

        $this->productA = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Tomato A',
            'sku' => 'VEG-TOM-A',
            'unit' => 'kg',
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);

        $this->productB = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Carrot B',
            'sku' => 'VEG-CAR-B',
            'unit' => 'kg',
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);

        $shop = Shop::factory()->create(['name' => 'Main Branch']);

        // Create approved shop orders for business date 2026-08-24
        $shopOrder = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-08-24',
            'state' => 'approved',
            'order_number' => 'SO-20260824-001',
        ]);
        DB::table('shop_orders')->where('id', $shopOrder->id)->update(['business_date' => '2026-08-24']);

        ShopOrderItem::query()->create([
            'shop_order_id' => $shopOrder->id,
            'product_id' => $this->productA->id,
            'product_grade' => 'A',
            'approved_qty' => 50.0,
            'requested_qty' => 50.0,
            'unit' => 'kg',
        ]);

        ShopOrderItem::query()->create([
            'shop_order_id' => $shopOrder->id,
            'product_id' => $this->productB->id,
            'product_grade' => 'A',
            'approved_qty' => 30.0,
            'requested_qty' => 30.0,
            'unit' => 'kg',
        ]);

        // Create draft cart for Tomato A (10 kg)
        $draftCart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'business_date' => '2026-08-24',
            'status' => 'draft',
            'purchase_grade' => 'A',
            'cart_number' => 'VC-DRAFT-001',
        ]);
        DB::table('purchaser_carts')->where('id', $draftCart->id)->update(['business_date' => '2026-08-24']);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $draftCart->id,
            'product_id' => $this->productA->id,
            'grade' => 'A',
            'quantity' => 10.0,
            'unit_price' => 5.0,
            'line_total' => 50.0,
        ]);

        // Create submitted cart for Tomato A (20 kg)
        $submittedCart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-08-24',
            'status' => 'submitted',
            'purchase_grade' => 'A',
            'cart_number' => 'VC-SUB-001',
        ]);
        DB::table('purchaser_carts')->where('id', $submittedCart->id)->update(['business_date' => '2026-08-24']);

        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $submittedCart->id,
            'product_id' => $this->productA->id,
            'grade' => 'A',
            'quantity' => 20.0,
            'unit_price' => 5.0,
            'line_total' => 100.0,
        ]);

        // Create historical overdue cart from 2026-08-20 with GRN and Invoice
        $overdueCart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-08-20',
            'status' => 'submitted',
            'purchase_grade' => 'A',
            'cart_number' => 'VC-OVERDUE-001',
        ]);
        DB::table('purchaser_carts')->where('id', $overdueCart->id)->update(['business_date' => '2026-08-20']);

        $grn = GoodsReceived::query()->create([
            'supplier_id' => $this->supplier->id,
            'purchaser_cart_id' => $overdueCart->id,
            'received_by' => $this->purchaser->id,
            'grn_number' => 'GRN-OD-001',
            'received_at' => '2026-08-20 12:00:00',
            'status' => 'pending',
            'bill_status' => 'pending',
        ]);
        $invoice = PurchaseInvoice::query()->create([
            'goods_received_id' => $grn->id,
            'supplier_id' => $this->supplier->id,
            'purchaser_cart_id' => $overdueCart->id,
            'invoice_number' => 'INV-OD-001',
            'invoice_date' => '2026-08-20',
            'amount' => 500.0,
            'payment_status' => 'unpaid',
            'payment_method' => 'Credit',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_bulk_buy_initial_page_loads_with_correct_quantities(): void
    {
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy', ['date' => '2026-08-24']));

        $response->assertOk();
        $response->assertViewIs('purchasing.purchaser.bulk_buy');

        // Check products rendered
        $response->assertSee('Tomato A');
        $response->assertSee('Carrot B');

        // Tomato A: Approved = 50, Bought = 20, Draft = 10, Left = 30
        $response->assertSee('Need: 50.0 kg');
        $response->assertSee('Bought: 20.0');
        $response->assertSee('In Cart: 10.0 kg');
        $response->assertSee('Left: 30.0');

        // Carrot B: Approved = 30, Bought = 0, Draft = 0, Left = 30
        $response->assertSee('Need: 30.0 kg');
        $response->assertSee('data-mobile-nav-buy', false);
        $response->assertSee('purchaser:buy-selection-change', false);
    }

    public function test_bulk_buy_initial_page_does_not_execute_l3_historical_queries(): void
    {
        $executedQueries = [];
        DB::listen(function (QueryExecuted $query) use (&$executedQueries): void {
            $executedQueries[] = $query->sql;
        });

        $response = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy', ['date' => '2026-08-24']));
        $response->assertOk();

        // Must NOT query vendor_settlement_allocations or historical overdue carts
        foreach ($executedQueries as $sql) {
            $this->assertStringNotContainsString('vendor_settlement_allocations', $sql);
            $this->assertStringNotContainsString('GRN-OD-001', $sql);
            $this->assertStringNotContainsString('VC-OVERDUE-001', $sql);
        }

        // Query count should be minimal (< 20)
        $this->assertLessThan(20, count($executedQueries));
    }

    public function test_bulk_buy_fulfilled_tab_endpoint_works(): void
    {
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy.tabs.fulfilled', [
            'date' => '2026-08-24',
            'purchase_grade' => 'A',
        ]));

        $response->assertOk();
    }

    public function test_bulk_buy_product_search_addon_endpoint_works(): void
    {
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy.product-search', [
            'q' => 'Tomato',
            'purchase_grade' => 'A',
        ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $this->productA->id,
            'name' => 'Tomato A',
        ]);
    }

    public function test_direct_purchase_addon_appears_in_pending_when_normal_demand_is_already_fulfilled(): void
    {
        // 1. Create a product whose normal demand is already fulfilled
        $cucumber = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Sambar Cucumber',
            'sku' => '52',
            'unit' => 'kg',
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);

        $normalShopOrder = ShopOrder::factory()->create([
            'business_date' => '2026-08-24',
            'order_source' => 'shop_owner',
            'state' => 'approved',
            'order_number' => 'SO-CUC-001',
            'created_at' => '2026-08-24 02:00:00',
        ]);
        DB::table('shop_orders')->where('id', $normalShopOrder->id)->update(['business_date' => '2026-08-24', 'created_at' => '2026-08-24 02:00:00']);

        ShopOrderItem::query()->create([
            'shop_order_id' => $normalShopOrder->id,
            'product_id' => $cucumber->id,
            'product_grade' => 'A',
            'approved_qty' => 18.0,
            'requested_qty' => 18.0,
            'unit' => 'kg',
        ]);

        // Cart submitted in morning: 54 kg bought (fulfilling 18 kg)
        $submittedCart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'supplier_id' => $this->supplier->id,
            'business_date' => '2026-08-24',
            'status' => 'submitted',
            'purchase_grade' => 'A',
            'cart_number' => 'VC-CUC-001',
            'submitted_at' => '2026-08-24 08:30:00',
            'created_at' => '2026-08-24 08:00:00',
        ]);
        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $submittedCart->id,
            'product_id' => $cucumber->id,
            'grade' => 'A',
            'quantity' => 54.0,
            'unit_price' => 10.0,
            'line_total' => 540.0,
        ]);

        // 2. Later purchaser adds an ADD-ON order for 10 kg
        $addonShopOrder = ShopOrder::factory()->create([
            'business_date' => '2026-08-24',
            'order_source' => 'admin_direct_purchase',
            'state' => 'approved',
            'order_number' => 'RQ-ADDON-001',
            'created_at' => '2026-08-24 11:00:00',
        ]);
        DB::table('shop_orders')->where('id', $addonShopOrder->id)->update(['business_date' => '2026-08-24', 'created_at' => '2026-08-24 11:00:00']);

        ShopOrderItem::query()->create([
            'shop_order_id' => $addonShopOrder->id,
            'product_id' => $cucumber->id,
            'product_grade' => 'A',
            'approved_qty' => 10.0,
            'requested_qty' => 10.0,
            'unit' => 'kg',
        ]);

        app(PurchaserReadCacheService::class)->invalidate(['orders', 'carts', 'products', 'settings']);

        // 3. Load Bulk Buy page
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy', ['date' => '2026-08-24']));
        $response->assertOk();

        // 4. Assert Add-on appears in Pending
        $pendingSummary = $response->viewData('pendingSummary');
        $addonPendingItem = $pendingSummary->first(fn (array $item): bool => $item['product_id'] === $cucumber->id && ! empty($item['is_addon']));

        $this->assertNotNull($addonPendingItem, 'Product 52 Add-on must appear in pendingSummary');
        $this->assertEquals(10.0, (float) $addonPendingItem['total_approved_qty']);
        $this->assertEquals(0.0, (float) $addonPendingItem['bought_qty']);
        $this->assertEquals(10.0, (float) $addonPendingItem['remaining_qty']);

        // Assert newest add-on appears at top of pending
        $firstPending = $pendingSummary->first();
        $this->assertTrue((bool) ($firstPending['is_addon'] ?? false), 'Newest add-on must appear first in Pending');

        // 5. Assert HTML contains Add-on badge and details
        $response->assertSee('Sambar Cucumber');
        $response->assertSee('Add-on');
        $response->assertSee('Need: 10.0 kg');
        $response->assertSee('Left: 10.0');

        // 6. Assert Fulfilled tab contains the fulfilled normal demand row
        $fulfilledResponse = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy.tabs.fulfilled', [
            'date' => '2026-08-24',
            'purchase_grade' => 'A',
        ]));
        $fulfilledResponse->assertOk();
        $fulfilledSummary = $fulfilledResponse->viewData('fulfilledSummary');
        $normalFulfilledItem = $fulfilledSummary->first(fn (array $item): bool => $item['product_id'] === $cucumber->id && empty($item['is_addon']));

        $this->assertNotNull($normalFulfilledItem, 'Normal fulfilled demand for Product 52 must remain in fulfilledSummary');
        $this->assertEquals(18.0, (float) $normalFulfilledItem['total_approved_qty']);
        $this->assertEquals(54.0, (float) $normalFulfilledItem['bought_qty']);
        $this->assertEquals(0.0, (float) $normalFulfilledItem['remaining_qty']);
    }

    public function test_bulk_buy_product_search_supports_pagination_and_category(): void
    {
        // Create 30 products in category
        for ($i = 1; $i <= 30; $i++) {
            Product::query()->create([
                'category_id' => $this->category->id,
                'name' => sprintf('Bulk Product %02d', $i),
                'sku' => sprintf('BP-%02d', $i),
                'unit' => 'kg',
                'is_active' => true,
                'show_in_purchaser_order' => true,
                'base_price' => 10.0,
                'vendor_price' => 10.0,
            ]);
        }

        // Page 1 with limit 24
        $response = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy.product-search', [
            'purchase_grade' => 'A',
            'category' => $this->category->name,
            'page' => 1,
            'limit' => 24,
        ]));

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'sku', 'unit', 'category_name'],
            ],
            'current_page',
            'has_more',
            'total',
            'last_page',
        ]);

        $json = $response->json();
        $this->assertCount(24, $json['data']);
        $this->assertTrue($json['has_more']);
        $this->assertEquals(1, $json['current_page']);
        $this->assertEquals('BP-01', $json['data'][0]['sku']);
        $this->assertEquals('BP-02', $json['data'][1]['sku']);

        // Page 2
        $page2Response = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy.product-search', [
            'purchase_grade' => 'A',
            'category' => $this->category->name,
            'page' => 2,
            'limit' => 24,
        ]));

        $page2Response->assertOk();
        $page2Json = $page2Response->json();
        $this->assertGreaterThan(0, count($page2Json['data']));
        $this->assertFalse($page2Json['has_more']);
        $this->assertEquals(2, $page2Json['current_page']);
    }

    public function test_bulk_buy_store_addons_creates_demand_and_appears_at_top_of_pending(): void
    {
        $cucumber = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Sambar Cucumber Extra',
            'sku' => 'SCE-01',
            'unit' => 'kg',
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);

        $beans = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Green Beans Extra',
            'sku' => 'GBE-01',
            'unit' => 'kg',
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);

        // Submit Add-ons via AJAX endpoint
        $storeResponse = $this->actingAs($this->purchaser)
            ->postJson(route('purchaser.bulk-buy.add-ons.store'), [
                'date' => '2026-08-24',
                'purchase_grade' => 'A',
                'items' => [
                    ['product_id' => $cucumber->id, 'quantity' => 10.0],
                    ['product_id' => $beans->id, 'quantity' => 5.0],
                ],
            ]);

        $storeResponse->assertOk();
        $storeResponse->assertJsonFragment(['success' => true]);

        // Verify ShopOrder created
        $this->assertDatabaseHas('shop_orders', [
            'business_date' => '2026-08-24 00:00:00',
            'order_source' => 'admin_direct_purchase',
            'state' => 'approved',
        ]);

        $this->assertDatabaseHas('shop_order_items', [
            'product_id' => $cucumber->id,
            'approved_qty' => 10.0,
            'requested_qty' => 10.0,
        ]);

        $this->assertDatabaseHas('shop_order_items', [
            'product_id' => $beans->id,
            'approved_qty' => 5.0,
            'requested_qty' => 5.0,
        ]);

        // Reload Bulk Buy page
        $bulkBuyResponse = $this->actingAs($this->purchaser)->get(route('purchaser.bulk-buy', ['date' => '2026-08-24']));
        $bulkBuyResponse->assertOk();

        $pendingSummary = $bulkBuyResponse->viewData('pendingSummary');
        $this->assertNotEmpty($pendingSummary);

        // Assert newly added add-ons appear at top of pendingSummary
        $firstItem = $pendingSummary->get(0);
        $secondItem = $pendingSummary->get(1);

        $this->assertTrue((bool) ($firstItem['is_addon'] ?? false));
        $this->assertTrue((bool) ($secondItem['is_addon'] ?? false));

        $addonProductIds = collect([$firstItem['product_id'], $secondItem['product_id']]);
        $this->assertTrue($addonProductIds->contains($cucumber->id));
        $this->assertTrue($addonProductIds->contains($beans->id));

        // HTML assertion
        $bulkBuyResponse->assertSee('Sambar Cucumber Extra');
        $bulkBuyResponse->assertSee('Green Beans Extra');
        $bulkBuyResponse->assertSee('Need: 10.0 kg');
        $bulkBuyResponse->assertSee('Need: 5.0 kg');
    }

    public function test_store_addons_to_cart_creates_demand_and_draft_cart_atomically(): void
    {
        // New product with an approved grade-A price
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Addon To Cart Product',
            'sku' => 'ATC-01',
            'unit' => 'kg',
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);

        // Seed an approved purchase grade price so resolver doesn't throw
        DB::table('purchase_grade_prices')->insert([
            'product_id' => $product->id,
            'grade' => 'A',
            'purchase_price' => 8.50,
            'business_date' => '2026-08-24',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        PurchaserCart::query()->delete();

        $countCartsBefore = PurchaserCart::query()
            ->where('user_id', $this->purchaser->id)
            ->whereNull('supplier_id')
            ->where('status', 'draft')
            ->whereDate('business_date', '2026-08-24')
            ->count();

        $response = $this->actingAs($this->purchaser)
            ->postJson(route('purchaser.bulk-buy.add-ons-to-cart.store'), [
                'date' => '2026-08-24',
                'purchase_grade' => 'A',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 12.0],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonFragment(['success' => true]);
        $response->assertJsonStructure(['redirect_url']);

        // ShopOrder with admin_direct_purchase and approved state created
        $this->assertDatabaseHas('shop_orders', [
            'business_date' => '2026-08-24 00:00:00',
            'order_source' => 'admin_direct_purchase',
            'state' => 'approved',
        ]);

        $this->assertDatabaseHas('shop_order_items', [
            'product_id' => $product->id,
            'approved_qty' => 12.0,
        ]);

        // Draft cart + cart item created (no second selection)
        $this->assertGreaterThan(
            $countCartsBefore,
            PurchaserCart::query()
                ->where('user_id', $this->purchaser->id)
                ->whereNull('supplier_id')
                ->where('status', 'draft')
                ->whereDate('business_date', '2026-08-24')
                ->count()
        );

        $this->assertDatabaseHas('purchaser_cart_items', [
            'product_id' => $product->id,
            'grade' => 'A',
            'quantity' => 12.0,
            'unit_price' => 8.50,
            'line_total' => 102.0,
        ]);

        // redirect_url should point straight to the draft cart bill
        $this->assertStringContainsString(
            '/purchaser/cart/',
            $response->json('redirect_url')
        );
    }

    public function test_store_addons_to_cart_appends_to_existing_draft_cart_item(): void
    {
        // New product with price
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Addon Append Product',
            'sku' => 'AAP-01',
            'unit' => 'kg',
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);

        DB::table('purchase_grade_prices')->insert([
            'product_id' => $product->id,
            'grade' => 'A',
            'purchase_price' => 6.00,
            'business_date' => '2026-08-24',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        PurchaserCart::query()->delete();

        // Seed an existing draft cart for this user/date/grade (no supplier)
        $existingCart = PurchaserCart::query()->create([
            'user_id' => $this->purchaser->id,
            'business_date' => '2026-08-24',
            'status' => 'draft',
            'purchase_grade' => 'A',
            'cart_number' => 'VC-EXISTING-CART',
            'supplier_id' => null,
            'purchase_source' => 'shop_order',
        ]);
        DB::table('purchaser_carts')->where('id', $existingCart->id)->update(['business_date' => '2026-08-24']);

        // Add the product to that cart already
        PurchaserCartItem::query()->create([
            'purchaser_cart_id' => $existingCart->id,
            'product_id' => $product->id,
            'grade' => 'A',
            'quantity' => 5.0,
            'unit_price' => 6.00,
            'line_total' => 30.0,
        ]);

        $response = $this->actingAs($this->purchaser)
            ->postJson(route('purchaser.bulk-buy.add-ons-to-cart.store'), [
                'date' => '2026-08-24',
                'purchase_grade' => 'A',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 8.0],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonFragment(['success' => true]);

        // No new cart created — reused the existing one
        $this->assertSame(1, PurchaserCart::query()
            ->where('user_id', $this->purchaser->id)
            ->whereNull('supplier_id')
            ->where('status', 'draft')
            ->whereDate('business_date', '2026-08-24')
            ->count()
        );

        // Existing cart item quantity should be 5 + 8 = 13
        $this->assertDatabaseHas('purchaser_cart_items', [
            'purchaser_cart_id' => $existingCart->id,
            'product_id' => $product->id,
            'quantity' => 13.0,
            'unit_price' => 6.00,
            'line_total' => 78.0,
        ]);
    }
}
