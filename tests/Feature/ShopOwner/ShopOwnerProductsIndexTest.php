<?php

declare(strict_types=1);

namespace Tests\Feature\ShopOwner;

use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOwnerAssignment;
use App\Models\ShopPriceGroup;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_shop_owner_products_page_returns_200_and_calculates_minimum_mrp_at_125_percent(): void
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
        $response->assertSee('₹100.00');
        $response->assertSee('₹125.00');
    }

    public function test_shop_owner_products_calculates_minimum_mrp_for_various_prices(): void
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

        // 80 selling price => 100 minimum MRP
        $product80 = Product::factory()->create([
            'name' => 'Potato Premium',
            'sku' => 'POT-001',
            'category_id' => $category->id,
            'base_price' => 0.00,
            'is_active' => true,
        ]);
        DailyPriceApproval::query()->create([
            'product_id' => $product80->id,
            'business_date' => '2026-09-22',
            'price_a' => 80.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        // 200 selling price => 250 minimum MRP
        $product200 = Product::factory()->create([
            'name' => 'Exotic Broccoli',
            'sku' => 'BRO-001',
            'category_id' => $category->id,
            'base_price' => 0.00,
            'is_active' => true,
        ]);
        DailyPriceApproval::query()->create([
            'product_id' => $product200->id,
            'business_date' => '2026-09-22',
            'price_a' => 200.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));

        $response->assertStatus(200);
        $response->assertSee('Potato Premium');
        $response->assertSee('₹80.00');
        $response->assertSee('₹100.00');

        $response->assertSee('Exotic Broccoli');
        $response->assertSee('₹200.00');
        $response->assertSee('₹250.00');
    }

    public function test_shop_owner_products_handles_zero_selling_price_safely(): void
    {
        $groupA = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 0, 'is_active' => true]
        );

        /** @var Shop $shop */
        $shop = Shop::factory()->create([
            'code' => 'SH03',
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
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));

        $response->assertStatus(200);
        $response->assertSee('Unpriced Product');
        $response->assertSee('-');
    }
}
