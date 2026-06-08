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
        // Should see dashboard, PO, Deliveries, and Finance
        $response->assertSee('Dashboard');
        $response->assertSee('Daily Orders');
        $response->assertSee('Daily Price Board');
        $response->assertSee('Deliveries');
        $response->assertSee('Finance');

        // Should not see inventory/purchasing group items which are reserved for other roles
        $response->assertDontSee('Sorting Checklist');
        $response->assertDontSee('Requisition Board');
        $response->assertDontSee('Suppliers');
    }

    public function test_shop_owner_can_view_daily_price_board_and_product_shortcuts(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_PRICE_TEST',
            'name' => 'Price Board Shop',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $product = Product::factory()->create([
            'name' => 'Daily Price Tomato',
        ]);

        $response = $this->actingAs($shopOwner)
            ->get(route('shop-owner.prices.index'));

        $response->assertOk();
        $response->assertSee('Daily Price Board');
        $response->assertSee('Daily Price Tomato');
        $response->assertSee('Add To Draft');
        $response->assertSee('Search Products');
        $response->assertSee('Sort By');
        $response->assertSee('Frequently Ordered');
        $response->assertSee('price-board-add-modal');
        $response->assertSee('Add To Draft Order');
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

    public function test_shop_owner_dashboard_shows_approved_revision_status_label(): void
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

        ShopOrderRevision::create([
            'shop_order_id' => $order->id,
            'revision_no' => 2,
            'status' => 'applied',
            'reason' => 'Need extra quantity',
            'requested_by' => $shopOwner->id,
            'reviewed_by' => $shopOwner->id,
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($shopOwner)->get(route('shop-owner.dashboard'));

        $response->assertOk();
        $response->assertSee('Update #2 Approved');
    }

    /**
     * Test the consolidated Finance dashboard and exports.
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
}
