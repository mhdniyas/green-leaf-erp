<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopOwnerProductCodeDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_shop_owner_order_screen_shows_and_sorts_products_by_code(): void
    {
        $category = Category::query()->create([
            'name' => 'VEG',
            'description' => 'Vegetables',
            'is_active' => true,
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Zebra Onion',
            'sku' => '2',
            'unit' => 'kg',
            'base_price' => 10,
            'vendor_price' => 10,
            'is_active' => true,
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Alpha Tomato',
            'sku' => '10',
            'unit' => 'kg',
            'base_price' => 10,
            'vendor_price' => 10,
            'is_active' => true,
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Mint Leaf',
            'sku' => '101',
            'unit' => 'kg',
            'base_price' => 10,
            'vendor_price' => 10,
            'is_active' => true,
        ]);

        $shop = Shop::query()->create([
            'code' => 'SHOP_CODE_SORT',
            'name' => 'Code Sorted Shop',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($shopOwner)->get(route('shop-owner.orders.create'));

        $response->assertOk();
        $response->assertSee('Search products by name, code or category...');
        $response->assertSee('Code 2');
        $response->assertSeeInOrder(['data-sku="2"', 'data-sku="10"', 'data-sku="101"'], false);
    }
}
