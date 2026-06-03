<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\Purchasing\POStatus;
use App\Enums\Sales\SOStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_user_is_redirected_to_login(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_dashboard_renders_sales_kpis_when_authorized(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('sales.order.view');

        // Create some sample data for sales stats
        $customer = Customer::factory()->create(['is_active' => true]);
        SalesOrder::factory()->create(['status' => SOStatus::Confirmed]);
        SalesInvoice::factory()->create(['amount' => 12500.50]);

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Active Customers');
        $response->assertSee('Pending Sales Orders');
        $response->assertSee('Monthly Sales');
        $response->assertSee('INR 12,500.50');
    }

    public function test_dashboard_does_not_render_sales_kpis_when_unauthorized(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Active Customers');
        $response->assertDontSee('Pending Sales Orders');
    }

    public function test_dashboard_renders_modules_based_on_permissions(): void
    {
        $user = User::factory()->create();

        // Give customer and users management permission
        $user->givePermissionTo(['sales.customer.view', 'admin.user.view']);

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Customers');
        $response->assertSee('Users & Roles');

        // Should not see sales orders or invoices as they are not permitted
        $response->assertDontSee('Sales Orders');
        $response->assertDontSee('Sales Invoices');
    }

    public function test_dashboard_renders_yesterday_order_quantities_for_shop_owner(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(ProductSeeder::class);

        $shop = Shop::create([
            'code' => 'SHOP_DASHBOARD_TEST',
            'name' => 'Dashboard Shop Test',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop-owner');

        $product1 = Product::first();
        $product2 = Product::skip(1)->first();

        // Create a yesterday's order for the shop
        $yesterdayOrder = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'submitted',
            'business_date' => today()->subDay()->toDateString(),
            'created_by' => $shopOwner->id,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $yesterdayOrder->id,
            'product_id' => $product1->id,
            'requested_qty' => 15.50,
            'unit' => $product1->unit,
        ]);

        // When accessing the dashboard
        $response = $this->actingAs($shopOwner)
            ->get(route('dashboard'));

        $response->assertOk();

        // Check that the output HTML contains the flatProducts array with correct yesterday and suggested quantities
        $html = $response->getContent();

        // Product 1 was in yesterday's order, so it should have 15.5 for yesterday and suggested
        $this->assertStringContainsString('"sku":"'.$product1->sku.'"', $html);
        $this->assertStringContainsString('"yesterday":15.5', $html);
        $this->assertStringContainsString('"suggested":15.5', $html);

        // Product 2 was NOT in yesterday's order, so it should have 0 for yesterday and suggested
        $this->assertStringContainsString('"sku":"'.$product2->sku.'"', $html);
        $this->assertStringContainsString('"yesterday":0', $html);
        $this->assertStringContainsString('"suggested":0', $html);
    }

    public function test_purchasing_manager_dashboard_shows_daily_order_progress_with_purchase_order_continue(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchasing-manager');

        $orderDate = Carbon::tomorrow()->format('Y-m-d');
        $supplier = Supplier::factory()->create();

        PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'order_date' => $orderDate,
            'status' => POStatus::Approved,
            'fulfillment_type' => 'warehouse',
            'created_by' => $manager->id,
        ]);

        $response = $this->actingAs($manager)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Daily Order Progress');
        $response->assertSee('Continue in Purchase Orders');
        $response->assertSee('Purchase Order');
    }

    public function test_warehouse_manager_dashboard_shows_receive_goods_gateway(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('warehouse-operations-manager');

        $supplier = Supplier::factory()->create();
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'order_date' => today()->toDateString(),
            'status' => POStatus::Approved,
            'created_by' => $manager->id,
        ]);

        $response = $this->actingAs($manager)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Daily Operational Gateway: Receive Goods to Start Process');
        $response->assertSee($po->po_number);
    }
}
