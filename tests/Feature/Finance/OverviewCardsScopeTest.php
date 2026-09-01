<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Client;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OverviewCardsScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $ownedShop1;

    private Shop $ownedShop2;

    private Shop $directShop1;

    private Shop $directShop2;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-25 12:00:00');
        $this->admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin']);
        $this->admin->assignRole('admin');
        config(['admin.user_access.main_admin_email' => $this->admin->email]);

        $client = Client::create([
            'code' => 'CL-01',
            'name' => 'Main Client',
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::factory()->create([
            'code' => 'MAIN-WH',
            'name' => 'Main Warehouse',
            'is_active' => true,
        ]);

        // 2 Owned / Client shops
        $this->ownedShop1 = Shop::factory()->create([
            'name' => 'Owned Downtown',
            'code' => 'OWN-01',
            'client_id' => $client->id,
            'status' => 'active',
            'accounting_enabled' => true,
        ]);

        $this->ownedShop2 = Shop::factory()->create([
            'name' => 'Owned Uptown',
            'code' => 'OWN-02',
            'client_id' => $client->id,
            'status' => 'active',
            'accounting_enabled' => true,
        ]);

        // 2 Direct Buyer shops (client_id === null)
        $this->directShop1 = Shop::factory()->create([
            'name' => 'Quick Mart Direct',
            'code' => 'DIR-01',
            'client_id' => null,
            'status' => 'active',
            'accounting_enabled' => false,
        ]);

        $this->directShop2 = Shop::factory()->create([
            'name' => 'Fortune Direct',
            'code' => 'DIR-02',
            'client_id' => null,
            'status' => 'active',
            'accounting_enabled' => false,
        ]);

        // Create Ledger Entry Types
        $salesType = LedgerEntryType::firstOrCreate(
            ['code' => 'cash_sale'],
            ['name' => 'Cash Sale', 'category' => 'income', 'is_active' => true]
        );
        $expenseType = LedgerEntryType::firstOrCreate(
            ['code' => 'tea_snacks'],
            ['name' => 'Tea & Snacks', 'category' => 'expense', 'is_active' => true]
        );

        // Seed data for August 2026:
        // Owned Shop 1: Sales ₹10,000, Expense ₹2,000, 2 Invoices = ₹3,500
        ShopLedgerTransaction::create([
            'shop_id' => $this->ownedShop1->id,
            'business_date' => '2026-08-10',
            'direction' => 'income',
            'amount' => 10000.00,
            'entry_type_id' => $salesType->id,
            'funding_source' => 'shop_cash',
            'status' => 'posted',
        ]);
        ShopLedgerTransaction::create([
            'shop_id' => $this->ownedShop1->id,
            'business_date' => '2026-08-10',
            'direction' => 'expense',
            'amount' => 2000.00,
            'entry_type_id' => $expenseType->id,
            'funding_source' => 'shop_cash',
            'status' => 'posted',
        ]);
        $this->createInvoice($this->ownedShop1, '2026-08-10', 2000.00, 'SINV-OWN1-01');
        $this->createInvoice($this->ownedShop1, '2026-08-15', 1500.00, 'SINV-OWN1-02');

        // Direct Shop 1: 3 Invoices = ₹50,000, No daily sales entries
        $this->createInvoice($this->directShop1, '2026-08-05', 20000.00, 'SINV-DIR1-01');
        $this->createInvoice($this->directShop1, '2026-08-12', 18000.00, 'SINV-DIR1-02');
        $this->createInvoice($this->directShop1, '2026-08-20', 12000.00, 'SINV-DIR1-03');

        // Direct Shop 2: 1 Invoice = ₹15,000, No daily sales entries
        $this->createInvoice($this->directShop2, '2026-08-08', 15000.00, 'SINV-DIR2-01');

        // Outside August record (September 2026) to verify date boundaries
        $this->createInvoice($this->directShop1, '2026-09-02', 99999.00, 'SINV-DIR1-SEPT');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createInvoice(Shop $shop, string $date, float $total, string $invNumber): ShopInvoice
    {
        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'business_date' => $date,
            'order_number' => 'ORD-'.$invNumber,
            'order_source' => 'system',
            'state' => 'approved',
            'delivery_status' => 'delivered',
            'payment_status' => 'pending',
            'shop_daily_order_key' => 'shop:'.$shop->id.':'.$date,
            'created_by' => $this->admin->id,
            'total_amount' => $total,
        ]);

        return ShopInvoice::create([
            'invoice_number' => $invNumber,
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
            'business_date' => $date,
            'subtotal' => $total,
            'tax_total' => 0.00,
            'round_off' => 0.00,
            'final_total' => $total,
            'paid_amount' => 0.00,
            'balance_due' => $total,
            'status' => 'issued',
            'delivery_status' => 'delivered',
            'payment_status' => 'pending',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_overview_cards_renders_and_supports_direct_scope(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.hub', [
            'timeframe' => 'custom',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'scope' => 'direct',
        ]));

        $response->assertOk()
            ->assertSee('Finance')
            ->assertSee('Direct')
            ->assertSee('Quick Mart Direct')
            ->assertSee('Fortune Direct');

        $totals = $response->viewData('totals');
        $this->assertEquals(65000.00, $totals['gl_bills']); // 50000 + 15000
        $this->assertEquals(4, $totals['gl_bills_count']); // 3 + 1
        $this->assertEquals(0.00, $totals['sales']);
        $this->assertEquals(0.00, $totals['expense']);
        $this->assertEquals(0.00, $totals['net']);

        // Check shop metrics for Direct Shop 1
        $shopMetrics = $response->viewData('shopMetrics');
        $dir1Metric = $shopMetrics->firstWhere('shop_id', $this->directShop1->id);
        $this->assertNotNull($dir1Metric);
        $this->assertEquals(50000.00, $dir1Metric['gl_bills']);
        $this->assertEquals(3, $dir1Metric['gl_bills_count']);
        $this->assertEquals(0, $dir1Metric['pending_days_count']); // Must NOT be flagged as pending!
        $this->assertFalse($dir1Metric['is_client_owned']);
    }

    public function test_overview_cards_supports_owned_scope(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.hub', [
            'timeframe' => 'custom',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'scope' => 'owned',
        ]));

        $response->assertOk();
        $totals = $response->viewData('totals');
        $this->assertEquals(3500.00, $totals['gl_bills']);
        $this->assertEquals(2, $totals['gl_bills_count']);
        $this->assertEquals(10000.00, $totals['sales']);
        $this->assertEquals(2000.00, $totals['expense']);
        $this->assertEquals(8000.00, $totals['net']);
    }

    public function test_overview_cards_supports_all_scope(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.hub', [
            'timeframe' => 'custom',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'scope' => 'all',
        ]));

        $response->assertOk();
        $totals = $response->viewData('totals');
        // Total GL: 3500 (owned) + 65000 (direct) = 68500
        $this->assertEquals(68500.00, $totals['gl_bills']);
        $this->assertEquals(6, $totals['gl_bills_count']);
        $this->assertEquals(10000.00, $totals['sales']);
        $this->assertEquals(2000.00, $totals['expense']);
        $this->assertEquals(8000.00, $totals['net']);
    }

    public function test_api_hub_data_returns_scoped_metrics(): void
    {
        // Direct scope via API
        $response = $this->actingAs($this->admin)->getJson(route('admin.cashbook.reports.api.hub', [
            'timeframe' => 'custom',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'scope' => 'direct',
        ]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'scope' => 'direct',
                'totals' => [
                    'gl_bills' => 65000.00,
                    'gl_bills_count' => 4,
                    'sales' => 0.00,
                    'expense' => 0.00,
                    'net' => 0.00,
                ],
            ]);

        // Owned scope via API
        $responseOwned = $this->actingAs($this->admin)->getJson(route('admin.cashbook.reports.api.hub', [
            'timeframe' => 'custom',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'scope' => 'owned',
        ]));

        $responseOwned->assertOk()
            ->assertJson([
                'success' => true,
                'scope' => 'owned',
                'totals' => [
                    'gl_bills' => 3500.00,
                    'gl_bills_count' => 2,
                    'sales' => 10000.00,
                    'expense' => 2000.00,
                    'net' => 8000.00,
                ],
            ]);
    }

    public function test_single_shop_detail_reports_correct_gl_bills_for_direct_shop(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.reports.shop', [
            'shop' => $this->directShop1->code,
            'timeframe' => 'custom',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
        ]));

        $response->assertOk()
            ->assertSee('Quick Mart Direct');

        $metrics = $response->viewData('metrics');
        $this->assertEquals(50000.00, $metrics['gl_bills']);
        $this->assertEquals(3, $metrics['gl_bills_count']);
        $this->assertEquals(0, $metrics['pending_days_count']);
    }

    public function test_unauthorized_user_cannot_access_overview_cards(): void
    {
        $user = User::factory()->create(); // Regular user with no admin/finance roles

        // Web request redirects to dashboard with error message per app authorization policy
        $response = $this->actingAs($user)->get(route('admin.cashbook.reports.hub'));
        $response->assertRedirect(route('dashboard'));

        // API request gets 403
        $apiResponse = $this->actingAs($user)->getJson(route('admin.cashbook.reports.api.hub'));
        $apiResponse->assertForbidden();
    }
}
