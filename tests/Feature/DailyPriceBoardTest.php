<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Inventory\ProductGrade;
use App\Models\DailyProductPrice;
use App\Models\DailyProductPriceRevision;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyPriceBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(ProductSeeder::class);
    }

    public function test_purchase_manager_can_view_daily_price_board(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $response = $this->actingAs($manager)
            ->get(route('purchasing.prices.index'));

        $response->assertOk();
        $response->assertSee('Daily Price Board');
        $response->assertSee('Product Search');
        $response->assertSee('Save Daily Prices');
        $response->assertSee('Today&apos;s Order', false);
    }

    public function test_products_with_todays_orders_are_sorted_first(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $shop = Shop::create([
            'code' => 'SHOP_PRICE_SORT',
            'name' => 'Price Sort Shop',
            'status' => 'active',
        ]);

        $orderedProduct = Product::factory()->create(['name' => 'Tomato Sorted First']);
        $unorderedProduct = Product::factory()->create(['name' => 'Apple Sorted Later']);

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'submitted',
            'business_date' => now()->addDay()->toDateString(),
            'created_by' => $manager->id,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $orderedProduct->id,
            'requested_qty' => 12,
            'unit' => $orderedProduct->unit,
        ]);

        $response = $this->actingAs($manager)
            ->get(route('purchasing.prices.index'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Tomato Sorted First',
            'Apple Sorted Later',
        ]);
    }

    public function test_purchase_manager_can_search_products_on_daily_price_board(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $matchingProduct = Product::factory()->create([
            'name' => 'Searchable Tomato',
            'sku' => 'SEARCH-TOM-001',
        ]);

        $nonMatchingProduct = Product::factory()->create([
            'name' => 'Hidden Apple',
            'sku' => 'HIDDEN-APP-001',
        ]);

        $response = $this->actingAs($manager)
            ->get(route('purchasing.prices.index', ['search' => 'Tomato']));

        $response->assertOk();
        $response->assertSee($matchingProduct->name);
        $response->assertDontSee($nonMatchingProduct->name);
    }

    public function test_admin_can_update_product_prices_across_shop_groups(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $product = Product::firstOrFail();
        $groupA = ShopPriceGroup::factory()->create([
            'name' => 'A',
            'default_margin_percent' => 10,
        ]);
        $groupB = ShopPriceGroup::factory()->create([
            'name' => 'B',
            'default_margin_percent' => 10,
        ]);
        $groupC = ShopPriceGroup::factory()->create([
            'name' => 'C',
            'default_margin_percent' => 10,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('purchasing.prices.update'), [
                'reason' => 'Market correction',
                'simple_prices' => [
                    $product->id => [
                        $groupA->id => 45,
                        $groupB->id => 43,
                        $groupC->id => 40,
                    ],
                ],
            ]);

        $response->assertRedirect(route('purchasing.prices.index'));

        $this->assertDatabaseHas('daily_product_prices', [
            'product_id' => $product->id,
            'shop_price_group_id' => $groupA->id,
            'grade' => ProductGrade::GradeA->value,
            'selling_price' => 45,
            'price_source' => 'manual',
            'manual_override' => true,
        ]);

        $this->assertDatabaseHas('daily_product_prices', [
            'product_id' => $product->id,
            'shop_price_group_id' => $groupB->id,
            'grade' => ProductGrade::GradeA->value,
            'selling_price' => 43,
        ]);

        $this->assertDatabaseHas('daily_product_prices', [
            'product_id' => $product->id,
            'shop_price_group_id' => $groupC->id,
            'grade' => ProductGrade::GradeA->value,
            'selling_price' => 40,
        ]);

        $this->assertDatabaseHas('daily_product_price_revisions', [
            'product_id' => $product->id,
            'shop_price_group_id' => $groupA->id,
            'grade' => ProductGrade::GradeA->value,
            'new_price' => 45,
            'change_type' => 'manual',
        ]);
    }

    public function test_shop_owner_cannot_access_internal_daily_price_board(): void
    {
        $shopOwner = User::factory()->create();
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($shopOwner)
            ->get(route('purchasing.prices.index'));

        $response->assertForbidden();
    }

    public function test_margin_price_is_not_overwritten_when_manual_override_is_saved(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $product = Product::firstOrFail();
        $group = ShopPriceGroup::factory()->create([
            'name' => 'B',
            'default_margin_percent' => 12,
        ]);

        DailyProductPrice::factory()->create([
            'product_id' => $product->id,
            'shop_price_group_id' => $group->id,
            'grade' => ProductGrade::GradeA,
            'selling_price' => 101.25,
            'price_source' => 'manual',
            'manual_override' => true,
        ]);

        $this->actingAs($manager)
            ->post(route('purchasing.prices.update'), [
                'simple_prices' => [
                    $product->id => [
                        $group->id => 101.25,
                    ],
                ],
            ])
            ->assertRedirect(route('purchasing.prices.index'));

        $this->assertSame(101.25, (float) DailyProductPrice::query()
            ->where('product_id', $product->id)
            ->where('shop_price_group_id', $group->id)
            ->where('grade', ProductGrade::GradeA->value)
            ->value('selling_price'));

        $this->assertSame(0, DailyProductPriceRevision::query()
            ->where('product_id', $product->id)
            ->where('shop_price_group_id', $group->id)
            ->where('grade', ProductGrade::GradeA->value)
            ->count());
    }
}
