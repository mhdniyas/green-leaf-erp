<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Repositories\Inventory\StockMovementRepository;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DeliveryDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_delivery_dashboard_renders_successfully_for_given_date(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $shop = Shop::factory()->create(['name' => 'Main Outlet']);
        $category = Category::factory()->create(['name' => 'Vegetables']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Fresh Carrots',
            'sku' => '1001',
            'unit' => 'kg',
        ]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-09-21',
            'order_number' => 'ORD-20260921-001',
            'delivery_status' => 'pending_delivery',
            'total_shortage_value' => 150.00,
            'cash_discrepancy' => 0.00,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'unit' => 'kg',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'loaded_qty' => 10,
            'sorting_status' => 'loaded',
        ]);

        ShopInvoice::factory()->create([
            'shop_order_id' => $order->id,
            'shop_id' => $shop->id,
            'invoice_number' => 'INV-20260921-001',
            'final_total' => 500.00,
            'paid_amount' => 500.00,
            'balance_amount' => 0.00,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('inventory.deliveries.dashboard', ['date' => '2026-09-21']));

        $response->assertOk();
        $response->assertSee('Delivery Operations - 21 September 2026');
        $response->assertSee('Main Outlet');
        $response->assertSee('ORD-20260921-001');
        $response->assertSee('INV-20260921-001');
        $response->assertSee('Rs. 150.00');
    }

    public function test_delivery_dashboard_handles_date_switching(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $shop = Shop::factory()->create(['name' => 'Yesterday Outlet']);
        ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-09-20',
            'order_number' => 'ORD-20260920-001',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('inventory.deliveries.dashboard', ['date' => '2026-09-20']));

        $response->assertOk();
        $response->assertSee('Delivery Operations - 20 September 2026');
        $response->assertSee('Yesterday Outlet');
        $response->assertSee('ORD-20260920-001');
    }

    public function test_delivery_dashboard_does_not_execute_current_stock_by_product_and_grade(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $stockRepoMock = Mockery::mock(StockMovementRepository::class);
        $stockRepoMock->shouldNotReceive('currentStockByProductAndGrade');
        $this->app->instance(StockMovementRepository::class, $stockRepoMock);

        $response = $this->actingAs($admin)
            ->get(route('inventory.deliveries.dashboard', ['date' => '2026-09-21']));

        $response->assertOk();
    }

    public function test_delivery_dashboard_paginates_shop_cards(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit' => 'kg',
        ]);

        // Create 25 orders with distinct shops to trigger pagination (perPage is 20)
        for ($i = 1; $i <= 25; $i++) {
            $shop = Shop::factory()->create(['name' => sprintf('Outlet %02d', $i)]);
            $order = ShopOrder::factory()->create([
                'shop_id' => $shop->id,
                'business_date' => '2026-09-21',
                'order_number' => sprintf('ORD-20260921-%03d', $i),
                'delivery_status' => 'pending_delivery',
            ]);

            ShopOrderItem::create([
                'shop_order_id' => $order->id,
                'product_id' => $product->id,
                'unit' => 'kg',
                'requested_qty' => 5,
                'approved_qty' => 5,
                'sorting_status' => 'allocated',
            ]);
        }

        $page1Response = $this->actingAs($admin)
            ->get(route('inventory.deliveries.dashboard', ['date' => '2026-09-21', 'orders_page' => 1]));

        $page1Response->assertOk();
        $page1Response->assertSee('orders_page=2');

        $page2Response = $this->actingAs($admin)
            ->get(route('inventory.deliveries.dashboard', ['date' => '2026-09-21', 'orders_page' => 2]));

        $page2Response->assertOk();
    }

    public function test_delivery_dashboard_filters_by_category_and_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $shopA = Shop::factory()->create(['name' => 'Shop Alpha']);
        $shopB = Shop::factory()->create(['name' => 'Shop Beta']);

        $catVeg = Category::factory()->create(['name' => 'Vegetables']);
        $catFruit = Category::factory()->create(['name' => 'Fruits']);

        $prodVeg = Product::factory()->create(['category_id' => $catVeg->id, 'unit' => 'kg']);
        $prodFruit = Product::factory()->create(['category_id' => $catFruit->id, 'unit' => 'kg']);

        $orderA = ShopOrder::factory()->create([
            'shop_id' => $shopA->id,
            'business_date' => '2026-09-21',
            'order_number' => 'ORD-ALPHA-01',
            'delivery_status' => 'in_transit',
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $orderA->id,
            'product_id' => $prodVeg->id,
            'unit' => 'kg',
            'requested_qty' => 5,
            'approved_qty' => 5,
            'sorting_status' => 'loaded',
        ]);

        $orderB = ShopOrder::factory()->create([
            'shop_id' => $shopB->id,
            'business_date' => '2026-09-21',
            'order_number' => 'ORD-BETA-02',
            'delivery_status' => 'delivered',
            'is_delivered' => true,
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $orderB->id,
            'product_id' => $prodFruit->id,
            'unit' => 'kg',
            'requested_qty' => 8,
            'approved_qty' => 8,
            'sorting_status' => 'loaded',
        ]);

        // Filter by Vegetables category
        $responseVeg = $this->actingAs($admin)
            ->get(route('inventory.deliveries.dashboard', ['date' => '2026-09-21', 'category_id' => $catVeg->id]));
        $responseVeg->assertOk();
        $responseVeg->assertSee('Shop Alpha');
        $responseVeg->assertDontSee('Shop Beta');

        // Filter by in_transit status
        $responseTransit = $this->actingAs($admin)
            ->get(route('inventory.deliveries.dashboard', ['date' => '2026-09-21', 'status' => 'in_transit']));
        $responseTransit->assertOk();
        $responseTransit->assertSee('Shop Alpha');
        $responseTransit->assertDontSee('Shop Beta');
    }

    public function test_delivery_dashboard_requires_authentication(): void
    {
        $response = $this->get(route('inventory.deliveries.dashboard', ['date' => '2026-09-21']));

        $response->assertRedirect(route('login'));
    }

    public function test_delivery_dashboard_requires_product_view_permission(): void
    {
        $userWithoutPermission = User::factory()->create();

        $response = $this->actingAs($userWithoutPermission)
            ->get(route('inventory.deliveries.dashboard', ['date' => '2026-09-21']));

        $this->assertTrue(in_array($response->getStatusCode(), [302, 403], true));
    }
}
