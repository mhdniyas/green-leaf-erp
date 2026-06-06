<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\Purchasing\POStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\ShopOrder;
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
        $response->assertSee('Add To Tomorrow Order');
        $response->assertSee('Search Products');
        $response->assertSee('Sort By');
        $response->assertSee('Frequently Ordered');
        $response->assertSee('price-board-add-modal');
        $response->assertSee('Add To Draft Order');
    }

    /**
     * Test Purchase Orders index tabs and approve/reject actions.
     */
    public function test_purchase_orders_redesign_tabs_and_actions(): void
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

        // Get index page
        $response = $this->actingAs($shopOwner)->get(route('purchasing.orders.index'));
        $response->assertOk();
        $response->assertSee('All Orders');
        $response->assertSee('Pending Approval');
        $response->assertSee('Approval History');
        $response->assertSee('Analytics');
        $response->assertSee('PO-TEST-DRAFT');

        // Approve order
        $approveResponse = $this->actingAs($shopOwner)
            ->post(route('purchasing.orders.approve', $draftPO), [
                'remarks' => 'Stock is needed urgently',
            ]);

        $approveResponse->assertRedirect(route('purchasing.orders.show', $draftPO));
        $this->assertEquals(POStatus::Approved, $draftPO->fresh()->status);

        // Refresh model from DB to get the 'approved' status, then update back to draft
        $draftPO->refresh();
        $draftPO->status = POStatus::Draft;
        $draftPO->save();

        // Reject order
        $rejectResponse = $this->actingAs($shopOwner)
            ->post(route('purchasing.orders.reject', $draftPO), [
                'remarks' => 'Duplicate item order',
            ]);

        $rejectResponse->assertRedirect(route('purchasing.orders.index'));
        $this->assertEquals(POStatus::Rejected, $draftPO->fresh()->status);
    }

    /**
     * Test the consolidated Finance dashboard and exports.
     */
    public function test_finance_dashboard_consolidated_view_and_exports(): void
    {
        $shop = Shop::create([
            'code' => 'SHOP_TEST_3',
            'name' => 'Shop Test Three',
        ]);

        $shopOwner = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        // Get index page
        $response = $this->actingAs($shopOwner)->get(route('finance.index'));
        $response->assertOk();
        $response->assertSee('Available Balance');
        $response->assertSee('Outstanding Amount');
        $response->assertSee('This Month Purchases');
        $response->assertSee('Expected Credit');
        $response->assertSee('Payment History');
        $response->assertSee('Ledger Statement');
        $response->assertSee('Download Statements');

        // Test CSV Statement Export
        $csvResponse = $this->actingAs($shopOwner)->get(route('finance.statement.export.csv'));
        $csvResponse->assertOk();
        $csvResponse->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csvResponse->assertHeader('Content-Disposition', 'attachment; filename="ledger_statement_'.today()->startOfMonth()->format('Y-m-d').'_to_'.today()->endOfMonth()->format('Y-m-d').'.csv"');

        // Test PDF Statement Export
        $pdfResponse = $this->actingAs($shopOwner)->get(route('finance.statement.export.pdf'));
        $pdfResponse->assertOk();
        $pdfResponse->assertViewIs('finance.statement_pdf');
        $pdfResponse->assertSee('Ledger Account Statement');
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
