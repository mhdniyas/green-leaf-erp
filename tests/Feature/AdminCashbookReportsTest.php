<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\User;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminCashbookReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        config()->set('admin.user_access.main_admin_email', 'admin@greenleaf.com');
        Role::findOrCreate('admin', 'web');

        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);
    }

    public function test_main_admin_can_access_cashbook_reports_hub_cards(): void
    {
        $admin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $admin->assignRole('admin');

        $shop = Shop::factory()->create([
            'name' => 'Downtown Superstore',
            'code' => 'SHP-DT',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        ShopLedgerProfile::create([
            'shop_id' => $shop->id,
            'name' => $shop->name,
            'code' => $shop->code,
            'slug' => 'shp-dt',
            'enabled' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.cashbook.reports.hub'))
            ->assertOk()
            ->assertSee('Own')
            ->assertSee('Downtown Superstore')
            ->assertSee('Total Gross Sales')
            ->assertSee('Total Expenses');
    }

    public function test_cashbook_reports_hub_api_returns_accurate_metrics(): void
    {
        $admin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $admin->assignRole('admin');

        $shop = Shop::factory()->create([
            'name' => 'City Fresh',
            'code' => 'SHP-CF',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        ShopLedgerProfile::create([
            'shop_id' => $shop->id,
            'name' => $shop->name,
            'code' => $shop->code,
            'slug' => 'shp-cf',
            'enabled' => true,
        ]);

        $incomeType = LedgerEntryType::where('code', 'cash_sales')->first();
        $expenseType = LedgerEntryType::where('code', 'other_expense')->first();

        ShopLedgerTransaction::create([
            'shop_id' => $shop->id,
            'business_date' => today()->toDateString(),
            'entry_type_id' => $incomeType?->id,
            'entry_type_code' => 'cash_sales',
            'direction' => 'income',
            'amount' => 8000.00,
            'funding_source' => 'none',
            'entered_by_user_id' => $admin->id,
            'status' => 'posted',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $shop->id,
            'business_date' => today()->toDateString(),
            'entry_type_id' => $expenseType?->id,
            'entry_type_code' => 'other_expense',
            'direction' => 'expense',
            'amount' => 2000.00,
            'funding_source' => 'petty',
            'entered_by_user_id' => $admin->id,
            'status' => 'posted',
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.cashbook.reports.api.hub', ['timeframe' => 'today']))
            ->assertOk();

        $response->assertJsonPath('success', true);
        $response->assertJsonPath('totals.sales', 8000);
        $response->assertJsonPath('totals.expense', 2000);
        $response->assertJsonPath('totals.net', 6000);
    }

    public function test_single_shop_drilldown_renders_in_admin_cashbook(): void
    {
        $admin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $admin->assignRole('admin');

        $shop = Shop::factory()->create([
            'name' => 'Metro Supercenter',
            'code' => 'SHP-METRO',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        ShopLedgerProfile::create([
            'shop_id' => $shop->id,
            'name' => $shop->name,
            'code' => $shop->code,
            'slug' => 'shp-metro',
            'enabled' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.cashbook.reports.shop', 'shp-metro'))
            ->assertOk()
            ->assertSee('Metro Supercenter')
            ->assertSee('Category Breakdown')
            ->assertSee('Daily Ledger Entries');
    }

    public function test_category_charts_and_analytics_render_in_admin_cashbook(): void
    {
        $admin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $admin->assignRole('admin');

        $shop = Shop::factory()->create([
            'name' => 'Apex Outlet',
            'code' => 'SHP-APEX',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        ShopLedgerProfile::create([
            'shop_id' => $shop->id,
            'name' => $shop->name,
            'code' => $shop->code,
            'slug' => 'shp-apex',
            'enabled' => true,
        ]);

        // Charts
        $this->actingAs($admin)
            ->get(route('admin.cashbook.reports.charts'))
            ->assertOk()
            ->assertSee('Category Distribution');

        // Analytics
        $res = $this->actingAs($admin)
            ->get(route('admin.cashbook.reports.analytics'))
            ->assertOk();
        $this->assertTrue(str_contains($res->getContent(), 'Profit Intelligence') || str_contains($res->getContent(), 'No Confirmed Transactions'));
    }

    public function test_mobile_ledger_renders_correctly(): void
    {
        $admin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $admin->assignRole('admin');

        $shop = Shop::factory()->create([
            'name' => 'Mobile Outlet',
            'code' => 'SHP-MOB',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        ShopLedgerProfile::create([
            'shop_id' => $shop->id,
            'name' => $shop->name,
            'code' => $shop->code,
            'slug' => 'shp-mob',
            'enabled' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.cashbook.reports.mobile-ledger', 'shp-mob'))
            ->assertOk()
            ->assertSee('Mobile Shop Ledger')
            ->assertSee('Mobile Outlet')
            ->assertSee('Total Sales')
            ->assertSee('Shop Expense')
            ->assertSee('Upto Y')
            ->assertSee('GL Bill');
    }

    public function test_upto_yesterday_preset_and_gl_bills_count_return_correctly(): void
    {
        $admin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $admin->assignRole('admin');

        $shop = Shop::factory()->create([
            'name' => 'Yesterday Outlet',
            'code' => 'SHP-YEST',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        ShopLedgerProfile::create([
            'shop_id' => $shop->id,
            'name' => $shop->name,
            'code' => $shop->code,
            'slug' => 'shp-yest',
            'enabled' => true,
        ]);

        $glType = LedgerEntryType::where('code', 'purchase_bill')->first();

        // Transaction yesterday
        ShopLedgerTransaction::create([
            'shop_id' => $shop->id,
            'business_date' => today()->subDay()->toDateString(),
            'entry_type_id' => $glType?->id,
            'entry_type_code' => 'purchase_bill',
            'direction' => 'expense',
            'amount' => 1500.00,
            'funding_source' => 'none',
            'entered_by_user_id' => $admin->id,
            'status' => 'posted',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.cashbook.reports.mobile-ledger', ['shop' => 'shp-yest', 'timeframe' => 'upto_yesterday']))
            ->assertOk()
            ->assertSee('Yesterday Outlet')
            ->assertSee('1500.00')
            ->assertSee('Info');
    }

    public function test_accounts_role_user_redirects_to_mobile_cashbook_and_can_view_all_data(): void
    {
        Role::findOrCreate('accounts', 'web');

        $accountUser = User::factory()->create(['email' => 'accounts@greenleaf.com']);
        $accountUser->assignRole('accounts');

        $shop = Shop::factory()->create([
            'name' => 'Mega Mart',
            'code' => 'SHP-MEGA',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        ShopLedgerProfile::create([
            'shop_id' => $shop->id,
            'name' => $shop->name,
            'code' => $shop->code,
            'slug' => 'shp-mega',
            'enabled' => true,
        ]);

        // 1. Dashboard redirects accounts role directly to the mobile cashbook hub
        $this->actingAs($accountUser)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.cashbook.reports.hub'));

        // 2. Can access Hub
        $this->actingAs($accountUser)
            ->get(route('admin.cashbook.reports.hub'))
            ->assertOk()
            ->assertSee('Own')
            ->assertSee('Mega Mart');

        // 3. Can access Charts
        $this->actingAs($accountUser)
            ->get(route('admin.cashbook.reports.charts'))
            ->assertOk()
            ->assertSee('Category Distribution');

        // 4. Can access Analytics
        $res = $this->actingAs($accountUser)
            ->get(route('admin.cashbook.reports.analytics'))
            ->assertOk();
        $this->assertTrue(str_contains($res->getContent(), 'Profit Intelligence') || str_contains($res->getContent(), 'No Confirmed Transactions'));

        // 5. Can access GL Bills
        $this->actingAs($accountUser)
            ->get(route('admin.cashbook.reports.gl-bills'))
            ->assertOk()
            ->assertSee('GL Bills');

        // 6. Can access Mobile Ledger
        $this->actingAs($accountUser)
            ->get(route('admin.cashbook.reports.mobile-ledger', 'shp-mega'))
            ->assertOk()
            ->assertSee('Mobile Shop Ledger')
            ->assertSee('GL Bill')
            ->assertSee('View Bills')
            ->assertSee('Category Summaries');
    }

    public function test_gl_bills_report_page_renders_correctly(): void
    {
        $admin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $admin->assignRole('admin');

        $shop = Shop::factory()->create([
            'name' => 'Bills Outlet',
            'code' => 'SHP-BLL',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        ShopLedgerProfile::create([
            'shop_id' => $shop->id,
            'name' => $shop->name,
            'code' => $shop->code,
            'slug' => 'shp-bll',
            'enabled' => true,
        ]);

        $order = \App\Models\ShopOrder::factory()->create([
            'shop_id' => $shop->id,
        ]);

        ShopInvoice::create([
            'shop_id' => $shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'INV-TEST-001',
            'business_date' => today()->toDateString(),
            'status' => 'finalized',
            'subtotal' => 5000.00,
            'final_total' => 5000.00,
            'paid_amount' => 2000.00,
            'balance_amount' => 3000.00,
            'delivery_note' => 'Daily produce delivery',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.cashbook.reports.gl-bills', ['shop_id' => $shop->id]))
            ->assertOk()
            ->assertSee('GL Bills')
            ->assertSee('INV-TEST-001')
            ->assertSee('Bills Outlet');
    }

    public function test_overview_cards_excludes_gl_bill_only_days_from_shop_loss_calculations(): void
    {
        $admin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $admin->assignRole('admin');

        $shop = Shop::factory()->create([
            'name' => 'Pending Entry Shop',
            'code' => 'SHP-PND',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        ShopLedgerProfile::create([
            'shop_id' => $shop->id,
            'name' => $shop->name,
            'code' => $shop->code,
            'slug' => 'shp-pnd',
            'enabled' => true,
        ]);

        $glEntryType = LedgerEntryType::where('code', 'gl_bill')->first()
            ?? LedgerEntryType::create(['code' => 'gl_bill', 'name' => 'GL Bill', 'category' => 'expense']);

        // Create ONLY a GL Bill transaction for today (no sales recorded)
        ShopLedgerTransaction::create([
            'shop_id' => $shop->id,
            'business_date' => today()->toDateString(),
            'entry_type_id' => $glEntryType->id,
            'entry_type_code' => 'gl_bill',
            'direction' => 'expense',
            'amount' => 45000.00,
            'funding_source' => 'company',
            'status' => 'approved',
            'reference_type' => 'App\Models\ShopInvoice',
            'reference_id' => 999,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.cashbook.reports.hub', ['timeframe' => 'today']));

        $response->assertOk()
            ->assertSee('Pending Entry Shop')
            ->assertSee('Pending Entry');
    }

    public function test_overview_cards_renders_dynamic_sparkline_svg(): void
    {
        $admin = User::factory()->create(['email' => 'admin@greenleaf.com']);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)
            ->get(route('admin.cashbook.reports.hub'));

        $response->assertOk()
            ->assertSee('getSparklinePath()')
            ->assertSee('getSparklineAreaPath()')
            ->assertSee('selectedShopForGraph');
    }
}
