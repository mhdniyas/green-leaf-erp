<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\Purchasing\POStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderRevision;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopOwnerModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    /**
     * Test that the shop owner gets a simplified sidebar.
     */
    public function test_shop_owner_gets_simplified_sidebar(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_TEST_1',
            'name' => 'Shop Test One',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($shopOwner)->get(route('shop-owner.dashboard'));

        $response->assertOk();
        // Should see dashboard, cart, deliveries, finance, and approval history
        $response->assertSee('Dashboard');
        $response->assertSee('Cart');
        $response->assertDontSee('Daily Price Board');
        $response->assertSee('Deliveries');
        $response->assertSee('Finance');
        $response->assertSee('Approval History');
        $response->assertSee('app-dialog-root');
        $response->assertSee('window.showAppAlert');

        // Should not see inventory/purchasing group items which are reserved for other roles
        $response->assertDontSee('Sorting Checklist');
        $response->assertDontSee('Requisition Board');
        $response->assertDontSee('Suppliers');
    }

    public function test_shop_owner_daily_price_board_is_removed(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_PRICE_TEST',
            'name' => 'Price Board Shop',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($shopOwner)
            ->get('/shop-owner/daily-prices');

        $response->assertNotFound();
    }

    public function test_shop_owner_cannot_access_internal_purchase_orders(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_TEST_2',
            'name' => 'Shop Test Two',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $supplier = Supplier::factory()->create();
        $draftPO = PurchaseOrder::create([
            'po_number' => 'PO-TEST-DRAFT',
            'supplier_id' => $supplier->id,
            'status' => POStatus::Draft,
            'order_date' => now()->format('Y-m-d'),
            'created_by' => $shopOwner->id,
            'fulfillment_type' => 'warehouse',
        ]);

        $response = $this->actingAs($shopOwner)->get(route('purchasing.orders.index'));
        $response->assertForbidden();
    }

    public function test_shop_owner_dashboard_shows_accepted_revision_status_label(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_REV_STATUS',
            'name' => 'Revision Status Shop',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->addDay()->toDateString(),
            'created_by' => $shopOwner->id,
            'latest_revision_no' => 2,
        ]);
        $product = Product::factory()->create();

        ShopOrderRevision::create([
            'shop_order_id' => $order->id,
            'revision_no' => 2,
            'status' => 'applied',
            'reason' => 'Need extra quantity',
            'requested_by' => $shopOwner->id,
            'reviewed_by' => $shopOwner->id,
            'reviewed_at' => now(),
        ])->items()->create([
            'product_id' => $product->id,
            'old_requested_qty' => 5.00,
            'new_requested_qty' => 8.00,
            'delta_qty' => 3.00,
            'final_approved_qty' => 8.00,
        ]);

        $response = $this->actingAs($shopOwner)->get(route('shop-owner.dashboard'));

        $response->assertOk();
        $response->assertSee('Update #2 Accepted');
    }

    public function test_shop_owner_dashboard_uses_purchaser_style_mobile_bottom_nav(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_MOBILE_NAV',
            'name' => 'Mobile Nav Shop',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($shopOwner)->get(route('shop-owner.dashboard'));

        $response->assertOk();
        $response->assertSee('bottom-5 z-50 px-5 lg:hidden', false);
        $response->assertSee('h-[60px] max-w-md items-center gap-1 rounded-[2rem]', false);
        $response->assertSee('h-11 flex-1 items-center justify-center gap-1.5 rounded-[1.25rem]', false);
    }

    /**
     * Test the consolidated Finance dashboard and retired internal finance URLs.
     */
    public function test_shop_owner_uses_shop_finance_and_cannot_access_internal_finance(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_TEST_3',
            'name' => 'Shop Test Three',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($shopOwner)->get(route('shop-owner.finance.index'));
        $response->assertOk();
        $response->assertSee('Finance');

        $this->actingAs($shopOwner)->get(route('finance.index'))->assertForbidden();
        $this->actingAs($shopOwner)->get(route('finance.statement.export.csv'))->assertForbidden();
        $this->actingAs($shopOwner)->get(route('finance.statement.export.pdf'))->assertForbidden();
        $this->actingAs($shopOwner)->get(route('finance.accounts.index'))->assertForbidden();
        $this->actingAs($shopOwner)->get(route('finance.reports.pnl'))->assertForbidden();
    }

    /**
     * Test Deliveries Dashboard is scoped to the user's shop.
     */
    public function test_deliveries_dashboard_scoped_to_shop_owner_shop(): void
    {
        $shopCasio = Shop::create(['code' => 'SHOP_CASIO_T', 'name' => 'Casio Test Outlet']);
        $shopBudegere = Shop::create(['code' => 'SHOP_BUDEGERE_T', 'name' => 'Budegere Test Outlet']);

        $casioOwner = User::factory()->create([
            'shop_id' => $shopCasio->id,
        ]);
        $casioOwner->assignRole('shop');

        $budegereOwner = User::factory()->create([
            'shop_id' => $shopBudegere->id,
        ]);
        $budegereOwner->assignRole('shop');

        // Create an order for Casio Test Outlet
        ShopOrder::create([
            'shop_id' => $shopCasio->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $casioOwner->id,
        ]);

        // Create an order for Budegere Test Outlet
        ShopOrder::create([
            'shop_id' => $shopBudegere->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $budegereOwner->id,
        ]);

        // Casio owner accesses dashboard
        $casioResponse = $this->actingAs($casioOwner)
            ->get(route('inventory.deliveries.dashboard'));

        $casioResponse->assertOk();
        $casioResponse->assertSee('Casio Test Outlet');
        $casioResponse->assertDontSee('Budegere Test Outlet');

        // Budegere owner accesses dashboard
        $budegereResponse = $this->actingAs($budegereOwner)
            ->get(route('inventory.deliveries.dashboard'));

        $budegereResponse->assertOk();
        $budegereResponse->assertSee('Budegere Test Outlet');
        $budegereResponse->assertDontSee('Casio Test Outlet');
    }

    public function test_shop_owner_delivery_details_uses_mobile_friendly_checkin_flow(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_DELIVERY_UI',
            'name' => 'Delivery Ui Shop',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $shopOwner->id,
            'is_allocation_completed' => true,
        ]);

        $response = $this->actingAs($shopOwner)
            ->get(route('shop-owner.deliveries.show', $order->order_number));

        $response->assertOk();
        $response->assertSee('Step 1');
        $response->assertSee('Receive Full Order');
        $response->assertSee('Confirm Delivery Check-In');
        $response->assertSee('Step 3');
        $response->assertSee('Shortage Summary');
    }

    public function test_shop_owner_marketplace_flow_uses_cart_language_and_hides_totals(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_MARKETPLACE_UI',
            'name' => 'Marketplace Ui Shop',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($shopOwner)->get(route('shop-owner.orders.create'));

        $response->assertOk();
        $response->assertSee('Marketplace');
        $response->assertSee('Add to Cart');
        $response->assertSee('Open Cart');
        $response->assertSee('Submit Daily Order');
        $response->assertDontSee('Estimated Total');
        $response->assertDontSee('Review Requisition');
    }

    public function test_shop_owner_cart_screen_shows_approval_history_copy(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_CART_UI',
            'name' => 'Cart Ui Shop',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($shopOwner)->get(route('shop-owner.orders.index'));

        $response->assertOk();
        $response->assertSee('Cart');
        $response->assertSee('Tomorrow Cart Snapshot');
        $response->assertSee('Approval History');
        $response->assertDontSee('Recent Orders');
    }

    public function test_shop_owner_marketplace_marks_empty_cart_validation_banner_for_js_dismissal(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_CART_ERROR_UI',
            'name' => 'Cart Error Ui Shop',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($shopOwner)
            ->from(route('shop-owner.orders.create'))
            ->followingRedirects()
            ->post(route('requisitions.store'), [
                'items' => [],
            ]);

        $response->assertOk();
        $response->assertSee('Requisition cannot be empty.');
        $response->assertSee('data-items-error-banner', false);
    }
}
