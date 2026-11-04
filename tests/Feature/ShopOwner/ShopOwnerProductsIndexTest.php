<?php

declare(strict_types=1);

namespace Tests\Feature\ShopOwner;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\ProductSellingPrice;
use App\Models\SellingPriceCategory;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOwnerAssignment;
use App\Models\ShopPriceGroup;
use App\Models\ShopProductSellingPrice;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopOwnerProductsIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-09-22 10:00:00', 'Asia/Kolkata'));
    }

    public function test_shop_owner_products_page_returns_200_and_calculates_minimum_mrp_at_default_25_percent(): void
    {
        $groupA = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 0, 'is_active' => true]
        );

        /** @var Shop $shop */
        $shop = Shop::factory()->create([
            'code' => 'SH01',
            'name' => 'Downtown Shop',
            'shop_price_group_id' => $groupA->id,
        ]);

        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('shop');
        $user->givePermissionTo('sales.order.create');

        ShopOwnerAssignment::query()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $category = Category::factory()->create(['name' => 'Vegetables']);

        /** @var Product $product */
        $product = Product::factory()->create([
            'name' => 'Fresh Tomato',
            'sku' => 'TOM-001',
            'category_id' => $category->id,
            'base_price' => 0.00,
            'vendor_price' => 0.00,
            'is_active' => true,
        ]);

        // DailyPriceApproval for today setting Group A selling price (Supply Price) to 100.00
        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'price_a' => 100.00,
            'price_b' => 100.00,
            'price_c' => 100.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));

        $response->assertStatus(200);
        $response->assertSee('Fresh Tomato');
        $response->assertSee('Supply Price');
        $response->assertSee('Min Retail Price');
        $response->assertSee('₹100');
        $response->assertSee('₹125');
    }

    public function test_shop_owner_products_calculates_minimum_mrp_for_various_prices_with_default_percentage(): void
    {
        $groupA = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 0, 'is_active' => true]
        );

        /** @var Shop $shop */
        $shop = Shop::factory()->create([
            'code' => 'SH02',
            'name' => 'Uptown Shop',
            'shop_price_group_id' => $groupA->id,
        ]);

        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('shop');
        $user->givePermissionTo('sales.order.create');

        ShopOwnerAssignment::query()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $category = Category::factory()->create(['name' => 'Vegetables']);

        // 80 selling price => 100 minimum MRP (80 * 1.25)
        $product80 = Product::factory()->create([
            'name' => 'Potato Premium',
            'sku' => 'POT-001',
            'category_id' => $category->id,
            'base_price' => 0.00,
            'vendor_price' => 0.00,
            'is_active' => true,
        ]);
        DailyPriceApproval::query()->create([
            'product_id' => $product80->id,
            'business_date' => '2026-09-22',
            'price_a' => 80.00,
            'price_b' => 80.00,
            'price_c' => 80.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        // 200 selling price => 250 minimum MRP (200 * 1.25)
        $product200 = Product::factory()->create([
            'name' => 'Exotic Broccoli',
            'sku' => 'BRO-001',
            'category_id' => $category->id,
            'base_price' => 0.00,
            'vendor_price' => 0.00,
            'is_active' => true,
        ]);
        DailyPriceApproval::query()->create([
            'product_id' => $product200->id,
            'business_date' => '2026-09-22',
            'price_a' => 200.00,
            'price_b' => 200.00,
            'price_c' => 200.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));

        $response->assertStatus(200);
        $response->assertSee('Potato Premium');
        $response->assertSee('₹80');
        $response->assertSee('₹100');

        $response->assertSee('Exotic Broccoli');
        $response->assertSee('₹200');
        $response->assertSee('₹250');
    }

    public function test_shop_owner_products_calculates_minimum_mrp_using_dynamic_configured_percentages(): void
    {
        $groupA = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 0, 'is_active' => true]
        );

        /** @var Shop $shop */
        $shop = Shop::factory()->create([
            'code' => 'SH03',
            'name' => 'Market Square Shop',
            'shop_price_group_id' => $groupA->id,
        ]);

        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('shop');
        $user->givePermissionTo('sales.order.create');

        ShopOwnerAssignment::query()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $category = Category::factory()->create(['name' => 'Vegetables']);

        $product = Product::factory()->create([
            'name' => 'Fresh Onion',
            'sku' => 'ONI-001',
            'category_id' => $category->id,
            'base_price' => 0.00,
            'vendor_price' => 0.00,
            'is_active' => true,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'price_a' => 100.00,
            'price_b' => 100.00,
            'price_c' => 100.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        // 1. Configured 10% markup => 100 * 1.10 = 110.00
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'selling_price_default_percentage'],
            ['value' => '10.0']
        );
        $response = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));
        $response->assertOk()
            ->assertSee('₹100')
            ->assertSee('₹110');

        // 2. Configured 20% markup => 100 * 1.20 = 120.00
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'selling_price_default_percentage'],
            ['value' => '20.0']
        );
        $response = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));
        $response->assertOk()
            ->assertSee('₹100')
            ->assertSee('₹120');

        // 3. Configured 30% markup => 100 * 1.30 = 130.00
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'selling_price_default_percentage'],
            ['value' => '30.0']
        );
        $response = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));
        $response->assertOk()
            ->assertSee('₹100')
            ->assertSee('₹130');

        // 4. Configured 0% markup => 100 * 1.00 = 100.00
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'selling_price_default_percentage'],
            ['value' => '0']
        );
        $response = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));
        $response->assertOk()
            ->assertSee('₹100');

        // 5. Configured decimal percentage 15.5% => 100 * 1.155 = 115.50
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'selling_price_default_percentage'],
            ['value' => '15.5']
        );
        $response = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));
        $response->assertOk()
            ->assertSee('₹100')
            ->assertSee('₹115.5');

        // 6. Configured 12.5% markup => 100 * 1.125 = 112.50
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'selling_price_default_percentage'],
            ['value' => '12.5']
        );
        $response = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));
        $response->assertOk()
            ->assertSee('₹100')
            ->assertSee('₹112.5');
    }

    public function test_shop_owner_products_calculates_non_round_prices_with_correct_two_decimal_rounding(): void
    {
        $groupA = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 0, 'is_active' => true]
        );

        /** @var Shop $shop */
        $shop = Shop::factory()->create([
            'code' => 'SH_ROUND',
            'name' => 'Rounding Shop',
            'shop_price_group_id' => $groupA->id,
        ]);

        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('shop');
        $user->givePermissionTo('sales.order.create');

        ShopOwnerAssignment::query()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $category = Category::factory()->create(['name' => 'Vegetables']);

        // 87.35 supply price with 25% markup => round(87.35 * 1.25, 2) = 109.19
        $product1 = Product::factory()->create([
            'name' => 'Carrot Ooty',
            'sku' => 'CAR-001',
            'category_id' => $category->id,
            'base_price' => 0.00,
            'vendor_price' => 0.00,
            'is_active' => true,
        ]);
        DailyPriceApproval::query()->create([
            'product_id' => $product1->id,
            'business_date' => '2026-09-22',
            'price_a' => 87.35,
            'price_b' => 87.35,
            'price_c' => 87.35,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        // 33.33 supply price with 12.5% markup => round(33.33 * 1.125, 2) = 37.50
        $product2 = Product::factory()->create([
            'name' => 'Beans Green',
            'sku' => 'BEA-001',
            'category_id' => $category->id,
            'base_price' => 0.00,
            'vendor_price' => 0.00,
            'is_active' => true,
        ]);
        DailyPriceApproval::query()->create([
            'product_id' => $product2->id,
            'business_date' => '2026-09-22',
            'price_a' => 33.33,
            'price_b' => 33.33,
            'price_c' => 33.33,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        // 1. With default 25%: 87.35 -> 109.19
        $response = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));
        $response->assertOk()
            ->assertSee('₹87.35')
            ->assertSee('₹109.19');

        // 2. With 12.5%: 33.33 -> 37.50
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'selling_price_default_percentage'],
            ['value' => '12.5']
        );
        $response = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));
        $response->assertOk()
            ->assertSee('₹33.33')
            ->assertSee('₹37.5');
    }

    public function test_selling_price_percentage_is_queried_without_per_product_n_plus_one_queries(): void
    {
        $groupA = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 0, 'is_active' => true]
        );

        /** @var Shop $shop */
        $shop = Shop::factory()->create([
            'code' => 'SH_NPLUS',
            'name' => 'Query Test Shop',
            'shop_price_group_id' => $groupA->id,
        ]);

        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('shop');
        $user->givePermissionTo('sales.order.create');

        ShopOwnerAssignment::query()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $category = Category::factory()->create(['name' => 'Vegetables']);

        // Create 6 products
        for ($i = 1; $i <= 6; $i++) {
            $prod = Product::factory()->create([
                'name' => "Batch Product {$i}",
                'sku' => "BAT-00{$i}",
                'category_id' => $category->id,
                'base_price' => 0.00,
                'vendor_price' => 0.00,
                'is_active' => true,
            ]);
            DailyPriceApproval::query()->create([
                'product_id' => $prod->id,
                'business_date' => '2026-09-22',
                'price_a' => 50.00 + $i,
                'status' => 'approved',
                'price_unit' => 'kg',
            ]);
        }

        DB::enableQueryLog();

        $response = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));

        $response->assertOk();

        $queries = DB::getQueryLog();
        $settingQueries = array_filter($queries, function ($q) {
            return str_contains($q['query'], 'business_settings') && str_contains(json_encode($q['bindings']), 'selling_price_default_percentage');
        });

        // Must be queried exactly once before product loop, NOT once per product
        $this->assertCount(1, $settingQueries);
    }

    public function test_changing_retail_markup_does_not_affect_approved_prices_or_invoice_safety(): void
    {
        $groupA = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 0, 'is_active' => true]
        );

        $shop = Shop::factory()->create([
            'code' => 'SH_INV_SAFE',
            'name' => 'Invoice Safety Shop',
            'shop_price_group_id' => $groupA->id,
        ]);

        $category = Category::factory()->create(['name' => 'Safety Items']);

        $product = Product::factory()->create([
            'name' => 'Safety Veggie',
            'sku' => 'SAF-001',
            'category_id' => $category->id,
            'base_price' => 0.00,
            'vendor_price' => 0.00,
            'is_active' => true,
        ]);

        $approval = DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'price_a' => 100.00,
            'price_b' => 100.00,
            'price_c' => 100.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        // Create an existing shop invoice item with unit price 100.00
        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'subtotal' => 100.00,
            'final_total' => 100.00,
            'status' => 'finalized',
        ]);

        $invoiceItem = ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'unit_price' => 100.00,
            'final_line_total' => 100.00,
        ]);

        // Change markup to 50%
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'selling_price_default_percentage'],
            ['value' => '50.0']
        );

        // Verify DailyPriceApproval was NOT modified
        $approval->refresh();
        $this->assertEquals(100.00, (float) $approval->price_a);

        // Verify ShopInvoice was NOT modified
        $invoice->refresh();
        $this->assertEquals(100.00, (float) $invoice->final_total);

        // Verify ShopInvoiceItem was NOT modified
        $invoiceItem->refresh();
        $this->assertEquals(100.00, (float) $invoiceItem->unit_price);
        $this->assertEquals(100.00, (float) $invoiceItem->final_line_total);
    }

    public function test_shop_owner_products_handles_zero_selling_price_safely(): void
    {
        $groupA = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 0, 'is_active' => true]
        );

        /** @var Shop $shop */
        $shop = Shop::factory()->create([
            'code' => 'SH04',
            'name' => 'Suburban Shop',
            'shop_price_group_id' => $groupA->id,
        ]);

        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('shop');
        $user->givePermissionTo('sales.order.create');

        ShopOwnerAssignment::query()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $category = Category::factory()->create(['name' => 'Unpriced Items']);

        Product::factory()->create([
            'name' => 'Unpriced Product',
            'sku' => 'UNP-001',
            'category_id' => $category->id,
            'base_price' => 0.00,
            'vendor_price' => 0.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));

        $response->assertStatus(200);
        $response->assertSee('Unpriced Product');
        $response->assertSee('Updating');
    }

    public function test_shop_owner_sees_category_percentage_retail_price(): void
    {
        $groupA = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 0, 'is_active' => true]
        );
        $groupA->update(['is_active' => true]);

        $shop = Shop::factory()->create([
            'code' => 'SH_CAT',
            'shop_price_group_id' => $groupA->id,
        ]);
        $user = User::factory()->create();
        $user->assignRole('shop');
        $user->givePermissionTo('sales.order.create');
        ShopOwnerAssignment::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'status' => 'active']);

        $category = Category::factory()->create();
        $priceCat = SellingPriceCategory::create([
            'name' => 'Premium Vegetables',
            'markup_percentage' => 30.0,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'name' => 'Broccoli Premium',
            'sku' => 'BROC-01',
            'category_id' => $category->id,
            'selling_price_category_id' => $priceCat->id,
            'base_price' => 0.00,
            'vendor_price' => 0.00,
            'is_active' => true,
        ]);

        DailyPriceApproval::create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'price_a' => 100.00,
            'price_b' => 100.00,
            'price_c' => 100.00,
            'price_unit' => 'kg',
            'status' => 'approved',
        ]);

        // Category 30%: ₹100 supply => ₹130 Min Retail Price
        $response = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));

        $response->assertOk();
        $response->assertSee('Broccoli Premium');
        $response->assertSee('₹100');
        $response->assertSee('₹130');
    }

    public function test_shop_owner_sees_product_custom_percentage_retail_price(): void
    {
        $groupA = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 0, 'is_active' => true]
        );
        $groupA->update(['is_active' => true]);

        $shop = Shop::factory()->create([
            'code' => 'SH_PROD',
            'shop_price_group_id' => $groupA->id,
        ]);
        $user = User::factory()->create();
        $user->assignRole('shop');
        $user->givePermissionTo('sales.order.create');
        ShopOwnerAssignment::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'status' => 'active']);

        $category = Category::factory()->create();
        $priceCat = SellingPriceCategory::create([
            'name' => 'Category 30',
            'markup_percentage' => 30.0,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'name' => 'Sweet Corn',
            'sku' => 'CORN-01',
            'category_id' => $category->id,
            'selling_price_category_id' => $priceCat->id,
            'base_price' => 0.00,
            'vendor_price' => 0.00,
            'is_active' => true,
        ]);

        // Product custom percentage: 20% (beats category 30%)
        ProductSellingPrice::create([
            'product_id' => $product->id,
            'pricing_mode' => ProductSellingPrice::MODE_CUSTOM_PERCENTAGE,
            'markup_percentage' => 20.0,
        ]);

        DailyPriceApproval::create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'price_a' => 100.00,
            'price_b' => 100.00,
            'price_c' => 100.00,
            'price_unit' => 'kg',
            'status' => 'approved',
        ]);

        // Product Custom 20%: ₹100 supply => ₹120 Min Retail Price
        $response = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));

        $response->assertOk();
        $response->assertSee('Sweet Corn');
        $response->assertSee('₹100');
        $response->assertSee('₹120');
    }

    public function test_shop_owner_sees_shop_specific_percentage_override(): void
    {
        $groupA = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 0, 'is_active' => true]
        );
        $groupA->update(['is_active' => true]);

        $shopA = Shop::factory()->create(['name' => 'Shop A', 'code' => 'SHA', 'shop_price_group_id' => $groupA->id]);
        $shopB = Shop::factory()->create(['name' => 'Shop B', 'code' => 'SHB', 'shop_price_group_id' => $groupA->id]);

        $userA = User::factory()->create();
        $userA->assignRole('shop');
        $userA->givePermissionTo('sales.order.create');
        ShopOwnerAssignment::create(['shop_id' => $shopA->id, 'user_id' => $userA->id, 'status' => 'active']);

        $userB = User::factory()->create();
        $userB->assignRole('shop');
        $userB->givePermissionTo('sales.order.create');
        ShopOwnerAssignment::create(['shop_id' => $shopB->id, 'user_id' => $userB->id, 'status' => 'active']);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Special Potato',
            'sku' => 'POT-01',
            'category_id' => $category->id,
            'base_price' => 0.00,
            'vendor_price' => 0.00,
            'is_active' => true,
        ]);

        // Product custom: 20%
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

        DailyPriceApproval::create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'price_a' => 100.00,
            'price_b' => 100.00,
            'price_c' => 100.00,
            'price_unit' => 'kg',
            'status' => 'approved',
        ]);

        // Shop A sees 35% override => ₹135
        $resA = $this->actingAs($userA)
            ->withSession(['shop_owner_active_shop_code' => $shopA->code])
            ->get(route('shop-owner.products.index'));
        $resA->assertOk();
        $resA->assertSee('Special Potato');
        $resA->assertSee('₹100');
        $resA->assertSee('₹135');

        // Shop B sees product custom 20% => ₹120
        $resB = $this->actingAs($userB)
            ->withSession(['shop_owner_active_shop_code' => $shopB->code])
            ->get(route('shop-owner.products.index'));
        $resB->assertOk();
        $resB->assertSee('Special Potato');
        $resB->assertSee('₹100');
        $resB->assertSee('₹120');
    }

    public function test_shop_owner_sees_general_fixed_retail_price_across_supply_price_fluctuations(): void
    {
        $groupA = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 0, 'is_active' => true]
        );
        $groupA->update(['is_active' => true]);

        $shop = Shop::factory()->create([
            'code' => 'SH_FIX',
            'shop_price_group_id' => $groupA->id,
        ]);
        $user = User::factory()->create();
        $user->assignRole('shop');
        $user->givePermissionTo('sales.order.create');
        ShopOwnerAssignment::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'status' => 'active']);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Brand Butter',
            'sku' => 'BUT-01',
            'category_id' => $category->id,
            'base_price' => 0.00,
            'vendor_price' => 0.00,
            'is_active' => true,
        ]);

        // Fixed price ₹145.00
        ProductSellingPrice::create([
            'product_id' => $product->id,
            'pricing_mode' => ProductSellingPrice::MODE_FIXED_PRICE,
            'fixed_price' => 145.00,
        ]);

        $approval = DailyPriceApproval::create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'price_a' => 100.00,
            'price_b' => 100.00,
            'price_c' => 100.00,
            'price_unit' => 'kg',
            'status' => 'approved',
        ]);

        // 1. With supply price ₹100 => Retail remains fixed at ₹145.00
        $res1 = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));
        $res1->assertOk();
        $res1->assertSee('₹100');
        $res1->assertSee('₹145');

        // 2. Supply price increases to ₹110 => Retail still remains fixed at ₹145
        $approval->update([
            'price_a' => 110.00,
            'price_b' => 110.00,
            'price_c' => 110.00,
        ]);
        $res2 = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));
        $res2->assertOk();
        $res2->assertSee('₹110');
        $res2->assertSee('₹145');
    }

    public function test_shop_specific_percentage_overrides_general_fixed_price(): void
    {
        $groupA = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 0, 'is_active' => true]
        );
        $groupA->update(['is_active' => true]);

        $shopA = Shop::factory()->create(['name' => 'Shop A', 'code' => 'SHA2', 'shop_price_group_id' => $groupA->id]);
        $shopB = Shop::factory()->create(['name' => 'Shop B', 'code' => 'SHB2', 'shop_price_group_id' => $groupA->id]);

        $userA = User::factory()->create();
        $userA->assignRole('shop');
        $userA->givePermissionTo('sales.order.create');
        ShopOwnerAssignment::create(['shop_id' => $shopA->id, 'user_id' => $userA->id, 'status' => 'active']);

        $userB = User::factory()->create();
        $userB->assignRole('shop');
        $userB->givePermissionTo('sales.order.create');
        ShopOwnerAssignment::create(['shop_id' => $shopB->id, 'user_id' => $userB->id, 'status' => 'active']);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Premium Olive Oil',
            'sku' => 'OIL-01',
            'category_id' => $category->id,
            'base_price' => 0.00,
            'vendor_price' => 0.00,
            'is_active' => true,
        ]);

        // General rule: Fixed price ₹145
        ProductSellingPrice::create([
            'product_id' => $product->id,
            'pricing_mode' => ProductSellingPrice::MODE_FIXED_PRICE,
            'fixed_price' => 145.00,
        ]);

        // Shop A override: 40%
        ShopProductSellingPrice::create([
            'shop_id' => $shopA->id,
            'product_id' => $product->id,
            'markup_percentage' => 40.0,
        ]);

        DailyPriceApproval::create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'price_a' => 110.00,
            'price_b' => 110.00,
            'price_c' => 110.00,
            'price_unit' => 'kg',
            'status' => 'approved',
        ]);

        // Shop A: 110 * 1.40 = ₹154 (Shop percentage overrides fixed price)
        $resA = $this->actingAs($userA)
            ->withSession(['shop_owner_active_shop_code' => $shopA->code])
            ->get(route('shop-owner.products.index'));
        $resA->assertOk();
        $resA->assertSee('₹110');
        $resA->assertSee('₹154');

        // Shop B: inherits fixed price ₹145
        $resB = $this->actingAs($userB)
            ->withSession(['shop_owner_active_shop_code' => $shopB->code])
            ->get(route('shop-owner.products.index'));
        $resB->assertOk();
        $resB->assertSee('₹110');
        $resB->assertSee('₹145');
    }

    public function test_inactive_category_falls_back_to_global_default(): void
    {
        $groupA = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 0, 'is_active' => true]
        );
        $groupA->update(['is_active' => true]);

        $shop = Shop::factory()->create([
            'code' => 'SH_INACT',
            'shop_price_group_id' => $groupA->id,
        ]);
        $user = User::factory()->create();
        $user->assignRole('shop');
        $user->givePermissionTo('sales.order.create');
        ShopOwnerAssignment::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'status' => 'active']);

        $category = Category::factory()->create();
        $priceCat = SellingPriceCategory::create([
            'name' => 'Inactive Category',
            'markup_percentage' => 50.0,
            'is_active' => false,
        ]);

        $product = Product::factory()->create([
            'name' => 'Seasonal Herb',
            'sku' => 'HRB-01',
            'category_id' => $category->id,
            'selling_price_category_id' => $priceCat->id,
            'base_price' => 0.00,
            'vendor_price' => 0.00,
            'is_active' => true,
        ]);

        DailyPriceApproval::create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'price_a' => 100.00,
            'price_b' => 100.00,
            'price_c' => 100.00,
            'price_unit' => 'kg',
            'status' => 'approved',
        ]);

        // Category inactive, falls back to default 25% => ₹125
        $response = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));

        $response->assertOk();
        $response->assertSee('Seasonal Herb');
        $response->assertSee('₹100');
        $response->assertSee('₹125');
    }

    public function test_shop_owner_cannot_access_admin_selling_price_management_endpoints(): void
    {
        $user = User::factory()->create();
        $user->assignRole('shop');

        $res1 = $this->actingAs($user)->get(route('admin.selling-price.index'));
        $res1->assertRedirect(route('dashboard'));

        $res2 = $this->actingAs($user)->get(route('admin.selling-price.matrix.index'));
        $res2->assertRedirect(route('dashboard'));

        $res3 = $this->actingAs($user)->get(route('admin.selling-price.shop-pricing.index'));
        $res3->assertRedirect(route('dashboard'));
    }
}
