<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DailyPricePublication;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\ShopOrders\DeliveryPriceReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyPricePublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_manager_can_toggle_publish_price_status(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        $date = '2026-08-16';

        $this->assertFalse(DailyPricePublication::isPublishedForDate($date));

        // Toggle ON
        $response = $this->actingAs($admin)->post(route('purchasing.prices.toggle-publish'), [
            'date' => $date,
            'is_published' => '1',
        ]);

        $response->assertRedirect();
        $this->assertTrue(DailyPricePublication::isPublishedForDate($date));

        // Toggle OFF
        $responseOff = $this->actingAs($admin)->post(route('purchasing.prices.toggle-publish'), [
            'date' => $date,
            'is_published' => '0',
        ]);

        $responseOff->assertRedirect();
        $this->assertFalse(DailyPricePublication::isPublishedForDate($date));
    }

    public function test_shop_owner_can_view_products_page_with_published_and_draft_status(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->syncRoles(['shop']);
        $user->givePermissionTo('sales.order.create');

        $shop = Shop::create([
            'name' => 'Green Leaf Test Shop',
            'code' => 'SHP-TEST',
            'slug' => 'shp-test',
            'is_active' => true,
            'owner_user_id' => $user->id,
        ]);
        $user->update(['shop_id' => $shop->id]);

        $category = Category::create(['name' => 'Vegetables', 'slug' => 'vegetables']);
        $product = Product::create([
            'name' => 'Fresh Tomatoes',
            'sku' => 'TOM-001',
            'unit' => 'kg',
            'base_price' => 25.00,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $date = '2026-08-16';

        // 1. When draft / unpublished
        DailyPricePublication::setPublishStatus($date, false);

        $responseDraft = $this->actingAs($user)->get(route('shop-owner.products.index', ['date' => $date]));
        $responseDraft->assertStatus(200);
        $responseDraft->assertSee('Daily Prices Updating');
        $responseDraft->assertSee('Fresh Tomatoes');

        // 2. When published
        DailyPricePublication::setPublishStatus($date, true);

        $responsePub = $this->actingAs($user)->get(route('shop-owner.products.index', ['date' => $date]));
        $responsePub->assertStatus(200);
        $responsePub->assertSee('Prices Published');
        $responsePub->assertSee('Fresh Tomatoes');
    }

    public function test_delivery_price_readiness_checks_date_published_status(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $user = User::factory()->create();
        $shop = Shop::create([
            'name' => 'Green Leaf Test Shop 2',
            'code' => 'SHP-TEST2',
            'slug' => 'shp-test2',
            'is_active' => true,
            'owner_user_id' => $user->id,
        ]);

        $date = '2026-08-16';

        $order = ShopOrder::create([
            'order_number' => 'ORD-TEST-001',
            'shop_id' => $shop->id,
            'business_date' => $date,
            'state' => 'approved',
            'created_by' => $user->id,
        ]);

        $service = app(DeliveryPriceReadinessService::class);

        // When not published
        DailyPricePublication::setPublishStatus($date, false);
        $readinessDraft = $service->forOrder($order);
        $this->assertFalse($readinessDraft['is_date_published']);

        // When published
        DailyPricePublication::setPublishStatus($date, true);
        $readinessPub = $service->forOrder($order);
        $this->assertTrue($readinessPub['is_date_published']);
    }

    public function test_shop_owner_has_no_access_to_purchase_prices_or_other_category_prices(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->syncRoles(['shop']);
        $user->givePermissionTo('sales.order.create');

        $shop = Shop::create([
            'name' => 'Green Leaf Test Shop Privacy',
            'code' => 'SHP-PRIV',
            'slug' => 'shp-priv',
            'is_active' => true,
            'owner_user_id' => $user->id,
        ]);
        $user->update(['shop_id' => $shop->id]);

        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits']);
        $product = Product::create([
            'name' => 'Premium Apples',
            'sku' => 'APP-001',
            'unit' => 'kg',
            'base_price' => 50.00,
            'vendor_price' => 35.00,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $date = '2026-08-16';
        DailyPricePublication::setPublishStatus($date, true);

        $response = $this->actingAs($user)->get(route('shop-owner.products.index', ['date' => $date]));

        $response->assertStatus(200);

        // Ensure purchasing prices/fields are NOT present in view data or output
        $productsViewData = $response->viewData('products');
        $firstItem = $productsViewData->first();

        $this->assertArrayNotHasKey('purchase_price', $firstItem);
        $this->assertArrayNotHasKey('vendor_price', $firstItem);
        $this->assertArrayNotHasKey('vendor_name', $firstItem);
        $this->assertArrayNotHasKey('margin_percent', $firstItem);
        $this->assertArrayHasKey('selling_price', $firstItem);
    }

    public function test_shop_owner_products_resolves_max_price_among_special_and_group_prices(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->syncRoles(['shop']);
        $user->givePermissionTo('sales.order.create');

        $shop = Shop::create([
            'name' => 'Max Price Test Shop',
            'code' => 'SHP-MAX',
            'slug' => 'shp-max',
            'is_active' => true,
            'owner_user_id' => $user->id,
        ]);
        $user->update(['shop_id' => $shop->id]);

        $category = Category::create(['name' => 'Spices', 'slug' => 'spices']);
        $product = Product::create([
            'name' => 'Cardamom',
            'sku' => 'SP-001',
            'unit' => 'kg',
            'base_price' => 100.00,
            'is_active' => true,
            'category_id' => $category->id,
        ]);

        $date = '2026-08-16';
        DailyPricePublication::setPublishStatus($date, true);

        // Create a special shop price higher than base price
        \App\Models\ShopDailyProductPrice::create([
            'business_date' => $date,
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'selling_price' => 150.00,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user)->get(route('shop-owner.products.index', ['date' => $date]));
        $response->assertStatus(200);

        $productsViewData = $response->viewData('products');
        $item = $productsViewData->first();

        // Max price (150.00) must be picked instead of base price (100.00)
        $this->assertEquals(150.00, $item['selling_price']);
    }
}
