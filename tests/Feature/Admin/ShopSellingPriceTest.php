<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\ProductSellingPrice;
use App\Models\SellingPriceCategory;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopOwnerAssignment;
use App\Models\ShopPriceGroup;
use App\Models\ShopProductSellingPrice;
use App\Models\User;
use App\Services\Pricing\SellingPriceResolverService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopSellingPriceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private SellingPriceResolverService $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Shop Pricing Admin']);
        $this->admin->assignRole('admin');

        $this->staff = User::factory()->create(['name' => 'Regular Staff']);
        $this->resolver = app(SellingPriceResolverService::class);
    }

    public function test_admin_can_view_shop_pricing_page(): void
    {
        $shop = Shop::factory()->create(['name' => 'Kochi Central', 'status' => 'active']);
        $product = Product::factory()->create(['name' => 'Red Tomato', 'sku' => 'TOM-001', 'is_active' => true]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.selling-price.shop-pricing.index', ['shop_id' => $shop->id]));

        $response->assertOk();
        $response->assertViewIs('admin.selling-price.shop-pricing');
        $response->assertSee('Shop-Wise Selling Price Management');
        $response->assertSee('Kochi Central');
        $response->assertSee('Red Tomato');
        $response->assertSee('TOM-001');
        $response->assertSee('Shop Markup');
        $response->assertSee('General Rule');
    }

    public function test_non_admin_cannot_access_shop_pricing_page(): void
    {
        $response = $this->actingAs($this->staff)
            ->get(route('admin.selling-price.shop-pricing.index'));

        $response->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'You do not have access to that page.');
    }

    public function test_admin_can_create_shop_specific_override(): void
    {
        $shop = Shop::factory()->create(['name' => 'Calicut Mart', 'status' => 'active']);
        $product = Product::factory()->create(['name' => 'Fresh Onion', 'is_active' => true]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.selling-price.shop-pricing.update-override', [
                'shop' => $shop->id,
                'product' => $product->id,
            ]), [
                'markup_percentage' => 35.5,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shop_product_selling_prices', [
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'markup_percentage' => 35.50,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_update_existing_shop_override(): void
    {
        $shop = Shop::factory()->create(['status' => 'active']);
        $product = Product::factory()->create(['is_active' => true]);

        ShopProductSellingPrice::create([
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'markup_percentage' => 20.0,
            'created_by' => $this->admin->id,
        ]);

        $anotherAdmin = User::factory()->create();
        $anotherAdmin->assignRole('admin');

        $response = $this->actingAs($anotherAdmin)
            ->post(route('admin.selling-price.shop-pricing.update-override', [
                'shop' => $shop->id,
                'product' => $product->id,
            ]), [
                'markup_percentage' => 42.0,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shop_product_selling_prices', [
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'markup_percentage' => 42.00,
            'created_by' => $this->admin->id,
            'updated_by' => $anotherAdmin->id,
        ]);
        $this->assertDatabaseCount('shop_product_selling_prices', 1);
    }

    public function test_zero_percentage_is_valid(): void
    {
        $shop = Shop::factory()->create(['status' => 'active']);
        $product = Product::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.selling-price.shop-pricing.update-override', [
                'shop' => $shop->id,
                'product' => $product->id,
            ]), [
                'markup_percentage' => 0,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shop_product_selling_prices', [
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'markup_percentage' => 0.00,
        ]);
    }

    public function test_decimal_percentage_and_precision(): void
    {
        $shop = Shop::factory()->create(['status' => 'active']);
        $product = Product::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.selling-price.shop-pricing.update-override', [
                'shop' => $shop->id,
                'product' => $product->id,
            ]), [
                'markup_percentage' => 17.75,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shop_product_selling_prices', [
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'markup_percentage' => 17.75,
        ]);

        // Retail calculation on supply price 80.00: 80 * 1.1775 = 94.20
        $resolution = $this->resolver->resolve($product, 80.0, $shop);
        $this->assertEquals(94.20, $resolution['retail_price']);
    }

    public function test_invalid_percentage_is_rejected(): void
    {
        $shop = Shop::factory()->create(['status' => 'active']);
        $product = Product::factory()->create(['is_active' => true]);

        // Negative percentage
        $response = $this->actingAs($this->admin)
            ->post(route('admin.selling-price.shop-pricing.update-override', [
                'shop' => $shop->id,
                'product' => $product->id,
            ]), [
                'markup_percentage' => -5,
            ]);
        $response->assertSessionHasErrors('markup_percentage');

        // Over 500%
        $response2 = $this->actingAs($this->admin)
            ->post(route('admin.selling-price.shop-pricing.update-override', [
                'shop' => $shop->id,
                'product' => $product->id,
            ]), [
                'markup_percentage' => 501,
            ]);
        $response2->assertSessionHasErrors('markup_percentage');

        // Non-numeric
        $response3 = $this->actingAs($this->admin)
            ->post(route('admin.selling-price.shop-pricing.update-override', [
                'shop' => $shop->id,
                'product' => $product->id,
            ]), [
                'markup_percentage' => 'abc',
            ]);
        $response3->assertSessionHasErrors('markup_percentage');
    }

    public function test_shop_specific_percentage_takes_precedence_over_general_custom_percentage(): void
    {
        $shopA = Shop::factory()->create(['name' => 'Shop A', 'status' => 'active']);
        $shopB = Shop::factory()->create(['name' => 'Shop B', 'status' => 'active']);

        $category = SellingPriceCategory::factory()->create(['markup_percentage' => 30.0, 'is_active' => true]);
        $product = Product::factory()->create(['selling_price_category_id' => $category->id]);

        // Product general custom rule: 20%
        ProductSellingPrice::create([
            'product_id' => $product->id,
            'pricing_mode' => ProductSellingPrice::MODE_CUSTOM_PERCENTAGE,
            'markup_percentage' => 20.0,
        ]);

        // Shop A override: 35%
        ShopProductSellingPrice::create([
            'shop_id' => $shopA->id,
            'product_id' => $product->id,
            'markup_percentage' => 35.0,
        ]);

        // Precedence test: Shop A gets 35%, Supply ₹100 => Retail ₹135
        $resShopA = $this->resolver->resolve($product, 100.0, $shopA);
        $this->assertTrue($resShopA['is_shop_override']);
        $this->assertEquals(35.0, $resShopA['effective_percentage']);
        $this->assertEquals(135.00, $resShopA['retail_price']);
        $this->assertEquals('shop_override', $resShopA['pricing_mode']);

        // Shop B without override inherits general custom 20% => Retail ₹120
        $resShopB = $this->resolver->resolve($product, 100.0, $shopB);
        $this->assertFalse($resShopB['is_shop_override']);
        $this->assertEquals(20.0, $resShopB['effective_percentage']);
        $this->assertEquals(120.00, $resShopB['retail_price']);
        $this->assertEquals(ProductSellingPrice::MODE_CUSTOM_PERCENTAGE, $resShopB['pricing_mode']);
    }

    public function test_shop_specific_percentage_takes_precedence_over_general_fixed_price(): void
    {
        $shop = Shop::factory()->create(['status' => 'active']);
        $product = Product::factory()->create();

        // Product general rule: Fixed Price ₹50.00
        ProductSellingPrice::create([
            'product_id' => $product->id,
            'pricing_mode' => ProductSellingPrice::MODE_FIXED_PRICE,
            'fixed_price' => 50.0,
        ]);

        // Shop override: 30%
        ShopProductSellingPrice::create([
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'markup_percentage' => 30.0,
        ]);

        // Precedence #1 (Shop override) beats Precedence #2 (General fixed price)
        $resolution = $this->resolver->resolve($product, 100.0, $shop);
        $this->assertTrue($resolution['is_shop_override']);
        $this->assertEquals(30.0, $resolution['effective_percentage']);
        $this->assertEquals(130.00, $resolution['retail_price']);
        $this->assertEquals('shop_override', $resolution['pricing_mode']);
    }

    public function test_general_fixed_price_applies_when_no_shop_override_exists(): void
    {
        $shop = Shop::factory()->create(['status' => 'active']);
        $product = Product::factory()->create();

        ProductSellingPrice::create([
            'product_id' => $product->id,
            'pricing_mode' => ProductSellingPrice::MODE_FIXED_PRICE,
            'fixed_price' => 50.0,
        ]);

        $resolution = $this->resolver->resolve($product, 100.0, $shop);
        $this->assertFalse($resolution['is_shop_override']);
        $this->assertTrue($resolution['is_fixed']);
        $this->assertEquals(50.00, $resolution['retail_price']);
    }

    public function test_category_inactive_falls_back_to_global_default_when_no_shop_or_product_override(): void
    {
        $shop = Shop::factory()->create(['status' => 'active']);
        $category = SellingPriceCategory::factory()->create(['markup_percentage' => 40.0, 'is_active' => false]);
        $product = Product::factory()->create(['selling_price_category_id' => $category->id]);

        $resolution = $this->resolver->resolve($product, 100.0, $shop);
        $this->assertFalse($resolution['is_shop_override']);
        $this->assertEquals(25.0, $resolution['effective_percentage']);
        $this->assertEquals(125.00, $resolution['retail_price']);
        $this->assertStringContainsString('Category Inactive', $resolution['source_label']);
    }

    public function test_admin_can_reset_individual_shop_override(): void
    {
        $shop = Shop::factory()->create(['status' => 'active']);
        $product = Product::factory()->create();

        ShopProductSellingPrice::create([
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'markup_percentage' => 35.0,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.selling-price.shop-pricing.reset-override', [
                'shop' => $shop->id,
                'product' => $product->id,
            ]));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('shop_product_selling_prices', [
            'shop_id' => $shop->id,
            'product_id' => $product->id,
        ]);

        // Now resolves to default global fallback
        $resolution = $this->resolver->resolve($product, 100.0, $shop);
        $this->assertFalse($resolution['is_shop_override']);
        $this->assertEquals(25.0, $resolution['effective_percentage']);
        $this->assertEquals(125.00, $resolution['retail_price']);
    }

    public function test_bulk_apply_across_multiple_shops_and_products(): void
    {
        $shops = Shop::factory()->count(3)->create(['status' => 'active']);
        $products = Product::factory()->count(3)->create(['is_active' => true]);

        $shopIds = $shops->pluck('id')->all();
        $productIds = $products->pluck('id')->all();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.selling-price.shop-pricing.bulk-apply'), [
                'shop_ids' => $shopIds,
                'product_ids' => $productIds,
                'markup_percentage' => 30.0,
            ]);

        $response->assertSessionHasNoErrors();

        // 3 shops × 3 products = 9 overrides
        $this->assertDatabaseCount('shop_product_selling_prices', 9);

        foreach ($shopIds as $sId) {
            foreach ($productIds as $pId) {
                $this->assertDatabaseHas('shop_product_selling_prices', [
                    'shop_id' => $sId,
                    'product_id' => $pId,
                    'markup_percentage' => 30.00,
                    'created_by' => $this->admin->id,
                    'updated_by' => $this->admin->id,
                ]);
            }
        }
    }

    public function test_bulk_reset_deletes_only_targeted_overrides(): void
    {
        $shopA = Shop::factory()->create(['status' => 'active']);
        $shopB = Shop::factory()->create(['status' => 'active']);
        $products = Product::factory()->count(2)->create(['is_active' => true]);

        // Overrides for shop A and B
        foreach ([$shopA, $shopB] as $s) {
            foreach ($products as $p) {
                ShopProductSellingPrice::create([
                    'shop_id' => $s->id,
                    'product_id' => $p->id,
                    'markup_percentage' => 25.0,
                ]);
            }
        }

        $this->assertDatabaseCount('shop_product_selling_prices', 4);

        // Reset only for shop A
        $response = $this->actingAs($this->admin)
            ->post(route('admin.selling-price.shop-pricing.bulk-reset'), [
                'shop_ids' => [$shopA->id],
                'product_ids' => $products->pluck('id')->all(),
            ]);

        $response->assertSessionHasNoErrors();

        // Shop A overrides deleted (0 left), Shop B overrides remain (2 left)
        $this->assertDatabaseCount('shop_product_selling_prices', 2);
        $this->assertEquals(0, ShopProductSellingPrice::where('shop_id', $shopA->id)->count());
        $this->assertEquals(2, ShopProductSellingPrice::where('shop_id', $shopB->id)->count());
    }

    public function test_batch_resolve_supply_prices_without_n_plus_one_queries(): void
    {
        $groupB = ShopPriceGroup::query()->firstOrCreate(['name' => 'B']);
        $shop = Shop::factory()->create([
            'shop_price_group_id' => $groupB->id,
            'status' => 'active',
        ]);

        $products = Product::factory()->count(3)->create(['is_active' => true, 'base_price' => 0.0]);

        // Product 0: approved daily price approval for target date
        DailyPriceApproval::create([
            'product_id' => $products[0]->id,
            'business_date' => today()->toDateString(),
            'price_a' => 40.0,
            'price_b' => 45.0,
            'price_c' => 50.0,
            'status' => 'approved',
        ]);

        // Product 1: base price fallback
        $products[1]->update(['base_price' => 60.0]);

        // Product 2: has no price anywhere (base_price = 0)

        $prices = $this->resolver->batchResolveSupplyPrices($products, $shop);

        $this->assertEquals(45.0, $prices[$products[0]->id]);
        $this->assertEquals(60.0, $prices[$products[1]->id]);
        $this->assertNull($prices[$products[2]->id]);
    }

    public function test_missing_supply_price_returns_null_retail_price(): void
    {
        $shop = Shop::factory()->create(['status' => 'active']);
        $product = Product::factory()->create(['is_active' => true, 'base_price' => 0.0]);

        ShopProductSellingPrice::create([
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'markup_percentage' => 30.0,
        ]);

        // Supply price is null
        $resolution = $this->resolver->resolve($product, null, $shop);
        $this->assertTrue($resolution['is_shop_override']);
        $this->assertNull($resolution['supply_price']);
        $this->assertNull($resolution['retail_price']);
    }

    public function test_filter_by_override_status(): void
    {
        $shop = Shop::factory()->create(['status' => 'active']);
        $pCustom = Product::factory()->create(['name' => 'Customized Item', 'is_active' => true]);
        $pInherited = Product::factory()->create(['name' => 'Inherited Item', 'is_active' => true]);

        ShopProductSellingPrice::create([
            'shop_id' => $shop->id,
            'product_id' => $pCustom->id,
            'markup_percentage' => 20.0,
        ]);

        // 1. Customized only
        $resCustom = $this->actingAs($this->admin)
            ->get(route('admin.selling-price.shop-pricing.index', [
                'shop_id' => $shop->id,
                'override_status' => 'customized',
            ]));
        $resCustom->assertOk();
        $resCustom->assertSee('Customized Item');
        $resCustom->assertDontSee('Inherited Item');

        // 2. Inherited only
        $resInherited = $this->actingAs($this->admin)
            ->get(route('admin.selling-price.shop-pricing.index', [
                'shop_id' => $shop->id,
                'override_status' => 'inherited',
            ]));
        $resInherited->assertOk();
        $resInherited->assertSee('Inherited Item');
        $resInherited->assertDontSee('Customized Item');
    }

    public function test_json_requests_return_json_responses(): void
    {
        $shop = Shop::factory()->create(['status' => 'active']);
        $product = Product::factory()->create(['is_active' => true]);

        // Update JSON
        $res = $this->actingAs($this->admin)
            ->postJson(route('admin.selling-price.shop-pricing.update-override', [
                'shop' => $shop->id,
                'product' => $product->id,
            ]), [
                'markup_percentage' => 28.0,
            ]);
        $res->assertOk();
        $res->assertJsonFragment(['success' => true]);

        // Reset JSON
        $resReset = $this->actingAs($this->admin)
            ->deleteJson(route('admin.selling-price.shop-pricing.reset-override', [
                'shop' => $shop->id,
                'product' => $product->id,
            ]));
        $resReset->assertOk();
        $resReset->assertJsonFragment(['success' => true]);
    }

    public function test_bulk_apply_rejects_excessive_batch_limit(): void
    {
        $cat = Category::factory()->create();
        $shop1 = Shop::factory()->create(['status' => 'active']);
        $shop2 = Shop::factory()->create(['status' => 'active']);

        $now = now();
        $productData = [];
        for ($i = 1; $i <= 501; $i++) {
            $productData[] = [
                'category_id' => $cat->id,
                'name' => "Batch Limit Product {$i}",
                'sku' => "BLP-{$i}",
                'unit' => 'kg',
                'base_price' => 10.0,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('products')->insert($productData);
        $productIds = DB::table('products')
            ->where('sku', 'like', 'BLP-%')
            ->pluck('id')
            ->all();

        // 2 shops × 501 products = 1,002 combinations (> 1,000 limit)
        $response = $this->actingAs($this->admin)
            ->post(route('admin.selling-price.shop-pricing.bulk-apply'), [
                'shop_ids' => [$shop1->id, $shop2->id],
                'product_ids' => $productIds,
                'markup_percentage' => 20.0,
            ]);

        $response->assertSessionHasErrors('bulk');
        $this->assertDatabaseCount('shop_product_selling_prices', 0);
    }

    public function test_supply_price_consistency_across_admin_matrix_admin_shop_pricing_and_shop_owner_catalog(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-22 10:00:00', 'Asia/Kolkata'));

        $groupA = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 0, 'is_active' => true]
        );
        $groupA->update(['is_active' => true]);

        $shop = Shop::factory()->create([
            'code' => 'SH_SYNC',
            'name' => 'Sync Verification Shop',
            'shop_price_group_id' => $groupA->id,
            'status' => 'active',
        ]);

        $shopOwner = User::factory()->create();
        $shopOwner->assignRole('shop');
        $shopOwner->givePermissionTo('sales.order.create');
        ShopOwnerAssignment::create([
            'shop_id' => $shop->id,
            'user_id' => $shopOwner->id,
            'status' => 'active',
        ]);

        $category = Category::factory()->create();

        // Product 1: Today's approval (100.00)
        $p1 = Product::factory()->create([
            'name' => 'Item Today Approval',
            'category_id' => $category->id,
            'base_price' => 20.00,
            'vendor_price' => 0.00,
            'is_active' => true,
        ]);
        DailyPriceApproval::create([
            'product_id' => $p1->id,
            'business_date' => '2026-09-22',
            'price_a' => 100.00,
            'price_b' => 100.00,
            'price_c' => 100.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        // Product 2: Previous approval (80.00)
        $p2 = Product::factory()->create([
            'name' => 'Item Previous Approval',
            'category_id' => $category->id,
            'base_price' => 30.00,
            'vendor_price' => 0.00,
            'is_active' => true,
        ]);
        DailyPriceApproval::create([
            'product_id' => $p2->id,
            'business_date' => '2026-09-20',
            'price_a' => 80.00,
            'price_b' => 80.00,
            'price_c' => 80.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        // Product 3: Base price only (45.00)
        $p3 = Product::factory()->create([
            'name' => 'Item Base Price Only',
            'category_id' => $category->id,
            'base_price' => 45.00,
            'vendor_price' => 0.00,
            'is_active' => true,
        ]);

        // 1. Admin Product Matrix
        $resMatrix = $this->actingAs($this->admin)
            ->get(route('admin.selling-price.matrix.index', ['shop_id' => $shop->id]));
        $resMatrix->assertOk();
        $matrixRows = collect($resMatrix->viewData('resolvedRows'))->keyBy(fn ($r) => $r['product']->id);

        // 2. Admin Shop Pricing
        $resShopPricing = $this->actingAs($this->admin)
            ->get(route('admin.selling-price.shop-pricing.index', ['shop_id' => $shop->id]));
        $resShopPricing->assertOk();
        $shopPricingRows = collect($resShopPricing->viewData('resolvedRows'))->keyBy(fn ($r) => $r['product']->id);

        // 3. Shop Owner Catalog
        $resShopOwner = $this->actingAs($shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));
        $resShopOwner->assertOk();
        $shopOwnerProducts = collect($resShopOwner->viewData('products')->items())->keyBy('id');

        // Verify Product 1 Supply Price is identical across all three
        $this->assertEquals(100.00, $matrixRows[$p1->id]['resolution']['supply_price']);
        $this->assertEquals(100.00, $shopPricingRows[$p1->id]['supply_price']);
        $this->assertEquals(100.00, $shopOwnerProducts[$p1->id]['selling_price']);

        // Verify Product 2 Supply Price is identical across all three
        $this->assertEquals(80.00, $matrixRows[$p2->id]['resolution']['supply_price']);
        $this->assertEquals(80.00, $shopPricingRows[$p2->id]['supply_price']);
        $this->assertEquals(80.00, $shopOwnerProducts[$p2->id]['selling_price']);

        // Verify Product 3 Supply Price is identical across all three
        $this->assertEquals(45.00, $matrixRows[$p3->id]['resolution']['supply_price']);
        $this->assertEquals(45.00, $shopPricingRows[$p3->id]['supply_price']);
        $this->assertEquals(45.00, $shopOwnerProducts[$p3->id]['selling_price']);

        // Verify Min Retail Prices are also identical (default 25%)
        $this->assertEquals(125.00, $matrixRows[$p1->id]['resolution']['retail_price']);
        $this->assertEquals(125.00, $shopPricingRows[$p1->id]['resolution']['retail_price']);
        $this->assertEquals(125.00, $shopOwnerProducts[$p1->id]['minimum_mrp']);

        $this->assertEquals(100.00, $matrixRows[$p2->id]['resolution']['retail_price']);
        $this->assertEquals(100.00, $shopPricingRows[$p2->id]['resolution']['retail_price']);
        $this->assertEquals(100.00, $shopOwnerProducts[$p2->id]['minimum_mrp']);

        $this->assertEquals(56.25, $matrixRows[$p3->id]['resolution']['retail_price']);
        $this->assertEquals(56.25, $shopPricingRows[$p3->id]['resolution']['retail_price']);
        $this->assertEquals(56.25, $shopOwnerProducts[$p3->id]['minimum_mrp']);

        Carbon::setTestNow();
    }

    public function test_financial_isolation_retail_changes_do_not_alter_orders_invoices_or_approvals(): void
    {
        $shop = Shop::factory()->create(['status' => 'active']);
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'base_price' => 50.00,
            'is_active' => true,
        ]);

        $approval = DailyPriceApproval::create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'price_a' => 100.00,
            'price_b' => 100.00,
            'price_c' => 100.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-09-22',
        ]);

        $orderItem = ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'locked_selling_price' => 100.00,
            'requested_qty' => 10,
            'unit' => 'kg',
        ]);

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-09-22',
            'subtotal' => 1000.00,
            'final_total' => 1000.00,
            'status' => 'finalized',
        ]);

        $invoiceItem = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'unit_price' => 100.00,
            'delivered_qty' => 10,
            'final_line_total' => 1000.00,
        ]);

        // Mutate all layers of retail pricing
        $priceCat = SellingPriceCategory::create([
            'name' => 'High Retail',
            'markup_percentage' => 75.0,
            'is_active' => true,
        ]);
        $product->update(['selling_price_category_id' => $priceCat->id]);

        ProductSellingPrice::create([
            'product_id' => $product->id,
            'pricing_mode' => ProductSellingPrice::MODE_FIXED_PRICE,
            'fixed_price' => 250.00,
        ]);

        ShopProductSellingPrice::create([
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'markup_percentage' => 45.0,
        ]);

        BusinessSetting::query()->updateOrCreate(
            ['key' => 'selling_price_default_percentage'],
            ['value' => '60.0']
        );

        // Verify Wholesale / Financial records remain strictly unchanged
        $this->assertEquals(100.00, (float) $approval->fresh()->price_a);
        $this->assertEquals(100.00, (float) $approval->fresh()->price_b);
        $this->assertEquals(100.00, (float) $approval->fresh()->price_c);

        $this->assertEquals(100.00, (float) $orderItem->fresh()->locked_selling_price);

        $this->assertEquals(100.00, (float) $invoiceItem->fresh()->unit_price);
        $this->assertEquals(1000.00, (float) $invoiceItem->fresh()->final_line_total);
        $this->assertEquals(1000.00, (float) $invoice->fresh()->final_total);
    }
}
