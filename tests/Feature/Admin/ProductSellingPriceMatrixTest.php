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
use App\Models\ShopOwnerAssignment;
use App\Models\ShopPriceGroup;
use App\Models\User;
use App\Services\Pricing\SellingPriceResolverService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductSellingPriceMatrixTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private SellingPriceResolverService $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Matrix Admin']);
        $this->admin->assignRole('admin');

        $this->staff = User::factory()->create(['name' => 'Regular Staff']);
        $this->resolver = app(SellingPriceResolverService::class);
    }

    public function test_admin_can_view_product_matrix_page(): void
    {
        $botanical = Category::factory()->create(['name' => 'Vegetables']);
        $product = Product::factory()->create([
            'name' => 'Carrot Fresh',
            'sku' => 'CAR-001',
            'category_id' => $botanical->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.selling-price.matrix.index'));

        $response->assertOk();
        $response->assertViewIs('admin.selling-price.matrix');
        $response->assertSee('Selling Price Management');
        $response->assertSee('Product Matrix');
        $response->assertSee('Carrot Fresh');
        $response->assertSee('CAR-001');
        $response->assertSee('SL');
        $response->assertSee('Effective Rule');
        $response->assertSee('Supply Price');
        $response->assertSee('Retail Price');
        $response->assertSee('Edit Rule');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.selling-price.matrix.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_product_matrix(): void
    {
        $response = $this->actingAs($this->staff)
            ->get(route('admin.selling-price.matrix.index'));

        $response->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'You do not have access to that page.');
    }

    public function test_product_search_and_filtering_by_category_and_mode(): void
    {
        $botanical = Category::factory()->create();
        $catStandard = SellingPriceCategory::factory()->create(['name' => 'Standard Margins', 'markup_percentage' => 20]);

        $p1 = Product::factory()->create(['name' => 'Apple Red', 'sku' => 'APP-001', 'category_id' => $botanical->id, 'selling_price_category_id' => $catStandard->id]);
        $p2 = Product::factory()->create(['name' => 'Banana Yellow', 'sku' => 'BAN-001', 'category_id' => $botanical->id, 'selling_price_category_id' => null]);

        ProductSellingPrice::create([
            'product_id' => $p1->id,
            'pricing_mode' => ProductSellingPrice::MODE_CUSTOM_PERCENTAGE,
            'markup_percentage' => 35.0,
        ]);

        // Search by keyword
        $searchRes = $this->actingAs($this->admin)
            ->get(route('admin.selling-price.matrix.index', ['search' => 'Apple']));
        $searchRes->assertOk();
        $searchRes->assertSee('Apple Red');
        $searchRes->assertDontSee('Banana Yellow');

        // Filter by category
        $catRes = $this->actingAs($this->admin)
            ->get(route('admin.selling-price.matrix.index', ['category_id' => $catStandard->id]));
        $catRes->assertOk();
        $catRes->assertSee('Apple Red');
        $catRes->assertDontSee('Banana Yellow');

        // Filter by unassigned
        $unassignedRes = $this->actingAs($this->admin)
            ->get(route('admin.selling-price.matrix.index', ['category_id' => 'unassigned']));
        $unassignedRes->assertOk();
        $unassignedRes->assertSee('Banana Yellow');
        $unassignedRes->assertDontSee('Apple Red');

        // Filter by custom_percentage mode
        $customRes = $this->actingAs($this->admin)
            ->get(route('admin.selling-price.matrix.index', ['pricing_mode' => 'custom_percentage']));
        $customRes->assertOk();
        $customRes->assertSee('Apple Red');
        $customRes->assertDontSee('Banana Yellow');
    }

    public function test_resolver_precedence_mode_a_global_default(): void
    {
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'selling_price_default_percentage'],
            ['value' => '25.0']
        );

        $botanical = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $botanical->id,
            'selling_price_category_id' => null,
        ]);

        $resolution = $this->resolver->resolve($product, 100.00);

        $this->assertSame(ProductSellingPrice::MODE_INHERITED, $resolution['pricing_mode']);
        $this->assertSame('global_default', $resolution['source']);
        $this->assertSame(25.0, $resolution['effective_percentage']);
        $this->assertEquals(125.00, $resolution['retail_price']);
        $this->assertFalse($resolution['is_override']);
        $this->assertFalse($resolution['is_fixed']);
    }

    public function test_resolver_precedence_mode_a_active_category(): void
    {
        $cat = SellingPriceCategory::factory()->create([
            'name' => 'High Value Veg',
            'markup_percentage' => 30.0,
            'is_active' => true,
        ]);

        $botanical = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $botanical->id,
            'selling_price_category_id' => $cat->id,
        ]);

        $resolution = $this->resolver->resolve($product, 100.00);

        $this->assertSame(ProductSellingPrice::MODE_INHERITED, $resolution['pricing_mode']);
        $this->assertSame('category', $resolution['source']);
        $this->assertSame(30.0, $resolution['effective_percentage']);
        $this->assertEquals(130.00, $resolution['retail_price']);
    }

    public function test_resolver_precedence_mode_a_inactive_category_falls_back_to_global_default(): void
    {
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'selling_price_default_percentage'],
            ['value' => '25.0']
        );

        $cat = SellingPriceCategory::factory()->create([
            'name' => 'Inactive Category',
            'markup_percentage' => 40.0,
            'is_active' => false,
        ]);

        $botanical = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $botanical->id,
            'selling_price_category_id' => $cat->id,
        ]);

        $resolution = $this->resolver->resolve($product, 100.00);

        $this->assertSame(ProductSellingPrice::MODE_INHERITED, $resolution['pricing_mode']);
        $this->assertSame('global_default', $resolution['source']);
        $this->assertSame(25.0, $resolution['effective_percentage']);
        $this->assertEquals(125.00, $resolution['retail_price']);
    }

    public function test_resolver_precedence_mode_b_custom_percentage_overrides_category(): void
    {
        $cat = SellingPriceCategory::factory()->create(['markup_percentage' => 20.0, 'is_active' => true]);
        $botanical = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $botanical->id,
            'selling_price_category_id' => $cat->id,
        ]);

        ProductSellingPrice::create([
            'product_id' => $product->id,
            'pricing_mode' => ProductSellingPrice::MODE_CUSTOM_PERCENTAGE,
            'markup_percentage' => 35.0,
        ]);

        $resolution = $this->resolver->resolve($product, 100.00);

        $this->assertSame(ProductSellingPrice::MODE_CUSTOM_PERCENTAGE, $resolution['pricing_mode']);
        $this->assertSame('product_custom', $resolution['source']);
        $this->assertSame(35.0, $resolution['effective_percentage']);
        $this->assertEquals(135.00, $resolution['retail_price']);
        $this->assertTrue($resolution['is_override']);
    }

    public function test_resolver_precedence_mode_c_manual_fixed_retail_price(): void
    {
        $cat = SellingPriceCategory::factory()->create(['markup_percentage' => 25.0, 'is_active' => true]);
        $botanical = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $botanical->id,
            'selling_price_category_id' => $cat->id,
        ]);

        ProductSellingPrice::create([
            'product_id' => $product->id,
            'pricing_mode' => ProductSellingPrice::MODE_FIXED_PRICE,
            'fixed_price' => 45.00,
        ]);

        // When Supply Price is 30.00
        $res30 = $this->resolver->resolve($product, 30.00);
        $this->assertSame(ProductSellingPrice::MODE_FIXED_PRICE, $res30['pricing_mode']);
        $this->assertSame('product_fixed', $res30['source']);
        $this->assertEquals(45.00, $res30['retail_price']);
        $this->assertNull($res30['warning']);

        // When Supply Price shifts to 50.00 (Fixed price 45 < 50 => warning generated)
        $res50 = $this->resolver->resolve($product, 50.00);
        $this->assertEquals(45.00, $res50['retail_price']);
        $this->assertNotNull($res50['warning']);
        $this->assertStringContainsString('is below the supply price', $res50['warning']);
    }

    public function test_admin_can_update_product_pricing_mode_and_category_from_modal(): void
    {
        $botanical = Category::factory()->create();
        $category = SellingPriceCategory::factory()->create(['name' => 'Premium']);
        $product = Product::factory()->create(['category_id' => $botanical->id, 'selling_price_category_id' => null]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.selling-price.matrix.update-product', $product), [
                'pricing_mode' => ProductSellingPrice::MODE_CUSTOM_PERCENTAGE,
                'markup_percentage' => '28.5',
                'selling_price_category_id' => $category->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame($category->id, $product->fresh()->selling_price_category_id);

        $config = $product->fresh()->sellingPriceConfig;
        $this->assertNotNull($config);
        $this->assertSame(ProductSellingPrice::MODE_CUSTOM_PERCENTAGE, $config->pricing_mode);
        $this->assertSame(28.5, $config->markup_percentage);
        $this->assertNull($config->fixed_price);
    }

    public function test_admin_can_set_fixed_retail_price(): void
    {
        $botanical = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $botanical->id]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.selling-price.matrix.update-product', $product), [
                'pricing_mode' => ProductSellingPrice::MODE_FIXED_PRICE,
                'fixed_price' => '65.50',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $config = $product->fresh()->sellingPriceConfig;
        $this->assertSame(ProductSellingPrice::MODE_FIXED_PRICE, $config->pricing_mode);
        $this->assertEquals(65.50, $config->fixed_price);
        $this->assertNull($config->markup_percentage);
    }

    public function test_switching_modes_clears_conflicting_values(): void
    {
        $botanical = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $botanical->id]);

        // 1. Set fixed price
        $this->actingAs($this->admin)
            ->patch(route('admin.selling-price.matrix.update-product', $product), [
                'pricing_mode' => ProductSellingPrice::MODE_FIXED_PRICE,
                'fixed_price' => '50.00',
            ]);

        // 2. Switch to custom percentage
        $this->actingAs($this->admin)
            ->patch(route('admin.selling-price.matrix.update-product', $product), [
                'pricing_mode' => ProductSellingPrice::MODE_CUSTOM_PERCENTAGE,
                'markup_percentage' => '18.00',
            ]);

        $config = $product->fresh()->sellingPriceConfig;
        $this->assertSame(ProductSellingPrice::MODE_CUSTOM_PERCENTAGE, $config->pricing_mode);
        $this->assertSame(18.0, $config->markup_percentage);
        $this->assertNull($config->fixed_price);
    }

    public function test_reset_product_rule_resets_to_inherited(): void
    {
        $botanical = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $botanical->id]);

        ProductSellingPrice::create([
            'product_id' => $product->id,
            'pricing_mode' => ProductSellingPrice::MODE_FIXED_PRICE,
            'fixed_price' => 99.00,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.selling-price.matrix.reset-product', $product));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $config = $product->fresh()->sellingPriceConfig;
        $this->assertSame(ProductSellingPrice::MODE_INHERITED, $config->pricing_mode);
        $this->assertNull($config->fixed_price);
        $this->assertNull($config->markup_percentage);
    }

    public function test_validation_rejects_invalid_values(): void
    {
        $botanical = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $botanical->id]);

        // Negative percentage
        $this->actingAs($this->admin)
            ->patch(route('admin.selling-price.matrix.update-product', $product), [
                'pricing_mode' => ProductSellingPrice::MODE_CUSTOM_PERCENTAGE,
                'markup_percentage' => '-10',
            ])
            ->assertSessionHasErrors(['markup_percentage']);

        // Percentage > 500
        $this->actingAs($this->admin)
            ->patch(route('admin.selling-price.matrix.update-product', $product), [
                'pricing_mode' => ProductSellingPrice::MODE_CUSTOM_PERCENTAGE,
                'markup_percentage' => '501',
            ])
            ->assertSessionHasErrors(['markup_percentage']);

        // Negative fixed price
        $this->actingAs($this->admin)
            ->patch(route('admin.selling-price.matrix.update-product', $product), [
                'pricing_mode' => ProductSellingPrice::MODE_FIXED_PRICE,
                'fixed_price' => '-5',
            ])
            ->assertSessionHasErrors(['fixed_price']);
    }

    public function test_bulk_assign_category(): void
    {
        $botanical = Category::factory()->create();
        $cat = SellingPriceCategory::factory()->create(['name' => 'Bulk Category']);

        $p1 = Product::factory()->create(['category_id' => $botanical->id]);
        $p2 = Product::factory()->create(['category_id' => $botanical->id]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.selling-price.matrix.bulk-category'), [
                'product_ids' => [$p1->id, $p2->id],
                'selling_price_category_id' => $cat->id,
            ]);

        $response->assertOk();
        $response->assertJson(['status' => 'success', 'count' => 2]);

        $this->assertSame($cat->id, $p1->fresh()->selling_price_category_id);
        $this->assertSame($cat->id, $p2->fresh()->selling_price_category_id);
    }

    public function test_bulk_set_custom_percentage(): void
    {
        $botanical = Category::factory()->create();
        $p1 = Product::factory()->create(['category_id' => $botanical->id]);
        $p2 = Product::factory()->create(['category_id' => $botanical->id]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.selling-price.matrix.bulk-percentage'), [
                'product_ids' => [$p1->id, $p2->id],
                'markup_percentage' => '32.5',
            ]);

        $response->assertOk();
        $response->assertJson(['status' => 'success', 'count' => 2]);

        $this->assertSame(32.5, $p1->fresh()->sellingPriceConfig->markup_percentage);
        $this->assertSame(32.5, $p2->fresh()->sellingPriceConfig->markup_percentage);
    }

    public function test_bulk_reset_to_inherited(): void
    {
        $botanical = Category::factory()->create();
        $p1 = Product::factory()->create(['category_id' => $botanical->id]);
        $p2 = Product::factory()->create(['category_id' => $botanical->id]);

        ProductSellingPrice::create(['product_id' => $p1->id, 'pricing_mode' => 'fixed_price', 'fixed_price' => 50]);
        ProductSellingPrice::create(['product_id' => $p2->id, 'pricing_mode' => 'custom_percentage', 'markup_percentage' => 30]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.selling-price.matrix.bulk-reset'), [
                'product_ids' => [$p1->id, $p2->id],
            ]);

        $response->assertOk();
        $response->assertJson(['status' => 'success', 'count' => 2]);

        $this->assertSame(ProductSellingPrice::MODE_INHERITED, $p1->fresh()->sellingPriceConfig->pricing_mode);
        $this->assertSame(ProductSellingPrice::MODE_INHERITED, $p2->fresh()->sellingPriceConfig->pricing_mode);
    }

    public function test_shop_context_preview_resolves_supply_price(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-22 10:00:00', 'Asia/Kolkata'));

        $group = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 0, 'is_active' => true]
        );

        $shop = Shop::factory()->create([
            'code' => 'SH03',
            'name' => 'Harbor Shop',
            'shop_price_group_id' => $group->id,
        ]);

        $botanical = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $botanical->id,
            'name' => 'Mint Leaves',
            'base_price' => 0.00,
            'vendor_price' => 0.00,
            'is_active' => true,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'price_a' => 80.00,
            'price_b' => 80.00,
            'price_c' => 80.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        // When shop is selected in matrix query
        $response = $this->actingAs($this->admin)
            ->get(route('admin.selling-price.matrix.index', [
                'shop_id' => $shop->id,
                'search' => 'Mint Leaves',
            ]));

        $response->assertOk();
        $response->assertSee('₹80.00'); // Supply price resolved
        $response->assertSee('₹100.00'); // 80 * 1.25 (global 25%)

        Carbon::setTestNow();
    }

    public function test_shop_owner_pricing_reflects_product_matrix_fixed_price_in_capsule_5(): void
    {
        // Capsule 5: Resolver is connected to live Shop Owner displays
        Carbon::setTestNow(Carbon::parse('2026-09-22 10:00:00', 'Asia/Kolkata'));

        BusinessSetting::query()->updateOrCreate(
            ['key' => 'selling_price_default_percentage'],
            ['value' => '25.0']
        );

        $group = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 0, 'is_active' => true]
        );

        $shop = Shop::factory()->create([
            'code' => 'SH04',
            'name' => 'Coast Shop',
            'shop_price_group_id' => $group->id,
        ]);

        $shopOwner = User::factory()->create();
        $shopOwner->assignRole('shop');
        $shopOwner->givePermissionTo('sales.order.create');

        ShopOwnerAssignment::query()->create([
            'shop_id' => $shop->id,
            'user_id' => $shopOwner->id,
            'status' => 'active',
        ]);

        $botanical = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $botanical->id,
            'name' => 'Capsule5 Protected Potato',
            'base_price' => 0.00,
            'vendor_price' => 0.00,
            'is_active' => true,
        ]);

        // Configure Mode C fixed price ₹200 in product matrix
        ProductSellingPrice::create([
            'product_id' => $product->id,
            'pricing_mode' => ProductSellingPrice::MODE_FIXED_PRICE,
            'fixed_price' => 200.00,
        ]);

        // Wholesale supply price is 100.00
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'price_a' => 100.00,
            'price_b' => 100.00,
            'price_c' => 100.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        // Shop Owner products screen shows Mode C fixed price ₹200 in Capsule 5
        $shopResponse = $this->actingAs($shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));

        $shopResponse->assertOk();
        $shopResponse->assertSee('₹200');

        $products = $shopResponse->viewData('products');
        $first = $products->first();
        $this->assertEquals(200.00, $first['minimum_mrp']);

        Carbon::setTestNow();
    }
}
