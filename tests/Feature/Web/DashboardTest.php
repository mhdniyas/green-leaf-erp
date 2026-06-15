<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\Purchasing\POStatus;
use App\Enums\Sales\SOStatus;
use App\Models\Customer;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Pricing\PriceBoardService;
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

    public function test_shop_owner_order_create_page_renders_yesterday_order_quantities(): void
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
        $shopOwner->assignRole('shop');

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
            ->get(route('shop-owner.orders.create'));

        $response->assertOk();
        $response->assertSee('Create Tomorrow Order');
        $response->assertSee(number_format(15.50, 2));
        $response->assertSee($product1->unit);
        $response->assertSee($product2->sku);
        $response->assertDontSee('Price');
        $response->assertDontSee('Estimated value');
        $expectedPrice = app(PriceBoardService::class)->sellingPriceFor($product1, $shop)['price'];
        $response->assertDontSee('INR '.number_format($expectedPrice, 2));
    }

    public function test_shop_owner_order_show_page_renders_successfully(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(ProductSeeder::class);

        $shop = Shop::create([
            'code' => 'SHOP_SHOW_TEST',
            'name' => 'Show Shop Test',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'submitted',
            'business_date' => today()->addDay()->toDateString(),
            'created_by' => $shopOwner->id,
        ]);

        $response = $this->actingAs($shopOwner)
            ->get(route('shop-owner.orders.show', $order->order_number));

        $response->assertOk();
    }

    public function test_shop_owner_order_create_page_shows_update_request_form_after_cutoff_for_submitted_order(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(22, 0));

        $shop = Shop::create([
            'code' => 'SHOP_DASHBOARD_LOCKED',
            'name' => 'Locked Shop',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'submitted',
            'business_date' => Carbon::tomorrow()->toDateString(),
            'created_by' => $shopOwner->id,
        ]);

        $response = $this->actingAs($shopOwner)->get(route('shop-owner.orders.create'));

        $response->assertOk();
        $response->assertSee('Order Locked After Cutoff');
        $response->assertSee('Submit Update Request');
    }

    public function test_shop_owner_dashboard_shows_today_delivery_check_action(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_DASHBOARD_DELIVERY',
            'name' => 'Delivery Shop',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'is_allocation_completed' => true,
            'created_by' => $shopOwner->id,
        ]);

        $response = $this->actingAs($shopOwner)->get(route('shop-owner.dashboard'));

        $response->assertOk();
        $response->assertSee('Pending Deliveries');
        $response->assertSee($order->order_number);
        $response->assertSee(route('shop-owner.deliveries.show', $order->order_number), false);
    }

    public function test_shop_user_is_redirected_from_main_dashboard_to_shop_owner_dashboard(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_DASHBOARD_REDIRECT',
            'name' => 'Redirect Shop',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($shopOwner)->get(route('dashboard'));

        $response->assertRedirect(route('shop-owner.dashboard'));
    }

    public function test_purchasing_manager_sees_daily_operations_dashboard(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $shop = Shop::create([
            'code' => 'SHOP_PURCHASE_DASH',
            'name' => 'Purchase Ops Shop',
        ]);
        $supplier = Supplier::factory()->create();
        $orderDate = Carbon::tomorrow()->format('Y-m-d');

        ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'submitted',
            'business_date' => $orderDate,
            'created_by' => $manager->id,
        ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'order_date' => $orderDate,
            'status' => POStatus::Approved,
            'fulfillment_type' => 'warehouse',
            'created_by' => $manager->id,
        ]);

        $grn = GoodsReceived::factory()->create([
            'purchase_order_id' => $po->id,
            'received_by' => $manager->id,
            'approved_by' => $manager->id,
            'updated_by' => $manager->id,
            'status' => 'recheck_required',
        ]);

        $approvedOrder = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => $orderDate,
            'created_by' => $manager->id,
        ]);

        ShopInvoice::factory()->create([
            'shop_id' => $shop->id,
            'shop_order_id' => $approvedOrder->id,
            'status' => 'delivery_review',
        ]);

        $response = $this->actingAs($manager)
            ->get(route('dashboard'));

        $response->assertRedirect(route('purchasing.orders.index'));

        $dashboardResponse = $this->actingAs($manager)
            ->get(route('purchasing.orders.index'));

        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Purchase Manager Dashboard');
        $dashboardResponse->assertSee('Total shop orders');
        $dashboardResponse->assertSee('Delivery done');
        $dashboardResponse->assertSee('Open Approve Shop Orders');
        $dashboardResponse->assertDontSee('Purchaser Desk');
        $dashboardResponse->assertSee('bottom-5 z-50 px-5 lg:hidden', false);
        $dashboardResponse->assertSee('h-[60px] max-w-md items-center gap-1 rounded-[2rem]', false);
        $dashboardResponse->assertSee('h-11 flex-1 items-center justify-center gap-1.5 rounded-[1.25rem]', false);
    }

    public function test_dashboard_renders_requisition_and_approved_board_modules_for_purchase_approver(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['purchasing.order.approve', 'purchasing.order.view']);

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Approve Shop Orders');
        $response->assertSee('Approved Board');
        $response->assertSee('Purchase Orders');
    }

    public function test_purchase_orders_workspace_navigation_prioritizes_requisition_boards(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $response = $this->actingAs($manager)
            ->get(route('purchasing.orders.index'));

        $response->assertOk();
        $response->assertSee('Purchase Manager Dashboard');
        $response->assertSee('Total shop orders');
        $response->assertSee('Delivery done');
        $response->assertSee('Open Approve Shop Orders');
    }

    public function test_shop_owner_selected_products_are_rendered_before_unselected_items(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(ProductSeeder::class);
        Carbon::setTestNow(Carbon::today()->setTime(12, 0));

        $shop = Shop::create([
            'code' => 'SHOP_SELECTED_FIRST',
            'name' => 'Selected First Shop',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $products = Product::query()->orderBy('id')->take(2)->get();

        $tomorrowOrder = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'draft',
            'business_date' => today()->addDay()->toDateString(),
            'created_by' => $shopOwner->id,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $tomorrowOrder->id,
            'product_id' => $products[1]->id,
            'requested_qty' => 5.00,
            'unit' => $products[1]->unit,
        ]);

        $response = $this->actingAs($shopOwner)->get(route('shop-owner.orders.create'));

        $response->assertOk();

        $content = $response->getContent();
        $selectedPosition = strpos($content, 'data-product-id="'.$products[1]->id.'"');
        $unselectedPosition = strpos($content, 'data-product-id="'.$products[0]->id.'"');

        $this->assertNotFalse($selectedPosition);
        $this->assertNotFalse($unselectedPosition);
        $this->assertLessThan($unselectedPosition, $selectedPosition);
    }

    public function test_shop_owner_create_order_page_renders_cart_total_hook_for_product_catalog_script(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(ProductSeeder::class);
        Carbon::setTestNow(Carbon::today()->setTime(12, 0));

        $shop = Shop::create([
            'code' => 'SHOP_ORDER_WINDOW',
            'name' => 'Order Window Shop',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($shopOwner)->get(route('shop-owner.orders.create'));

        $response->assertOk();
        $response->assertSee('id="shop-owner-product-catalog"', false);
        $response->assertSee('id="review-total-value"', false);
        $response->assertSee('data-category-pill="all"', false);
    }

    public function test_purchase_manager_cannot_access_removed_supplier_and_admin_only_sections(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $supplier = Supplier::factory()->create();

        $this->actingAs($manager)
            ->get(route('purchasing.suppliers.index'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('purchasing.invoices.index'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('purchasing.price-groups.index'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('purchasing.suppliers.edit', $supplier))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('purchasing.orders.create'))
            ->assertForbidden();
    }

    public function test_admin_overview_shows_vendor_and_shop_owner_finance_pillars(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $supplier = Supplier::factory()->create([
            'name' => 'North Market Vendor',
            'credit_approval_requested_at' => now(),
            'credit_approval_requested_by' => $admin->id,
            'credit_approved' => false,
        ]);

        PurchaseInvoice::factory()->create([
            'supplier_id' => $supplier->id,
            'amount' => 500.00,
            'paid_amount' => 125.00,
            'created_at' => today()->setTime(10, 0),
        ]);

        ShopInvoice::factory()->create([
            'invoice_number' => 'SINV-ADMIN-PILLAR',
            'business_date' => today()->toDateString(),
            'final_total' => 700.00,
            'paid_amount' => 400.00,
            'balance_amount' => 300.00,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.overview'))
            ->assertOk()
            ->assertSee('Vendor Reports')
            ->assertSee('Sales Reports')
            ->assertSee('Daily credit and debit table')
            ->assertSee('North Market Vendor')
            ->assertSee('Approve Credit')
            ->assertDontSee('Cash Collected')
            ->assertDontSee('Expense Outflow');
    }

    public function test_warehouse_manager_dashboard_shows_receive_goods_gateway(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('warehouse');

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
