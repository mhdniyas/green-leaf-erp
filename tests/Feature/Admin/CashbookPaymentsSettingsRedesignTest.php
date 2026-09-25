<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopCashbookRelationItem;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\ShopPettyCashExpense;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\PaymentsSettings\ShopPaymentsReportConfigService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbookPaymentsSettingsRedesignTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private ShopLedgerProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LedgerEntryTypeSeeder::class, ShopConfigPresetSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->shop = Shop::factory()->create(['accounting_enabled' => true, 'accounting_mode' => 'owned']);
        app(CashbookShopSyncService::class)->syncAndGetProfiles();
        $this->profile = ShopLedgerProfile::where('shop_id', $this->shop->id)->firstOrFail();
    }

    /**
     * Test 1: Payments index renders all 9 sections via dedicated services.
     */
    public function test_payments_index_loads_all_nine_sections_cleanly(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.settings.shop.payments.index', [
            'shop' => $this->shop->id,
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $response->assertViewIs('admin.cashbook.settings.payments.index');
        $response->assertViewHas([
            'overviewData',
            'collectionsData',
            'shopToCompanyData',
            'companyToShopData',
            'pettyData',
            'settlementData',
            'allocationData',
            'reportHeadingsData',
            'advancedData',
        ]);
        $response->assertSee('PAYMENTS SETTINGS');
        $response->assertSee('COMPANY COLLECTIONS');
        $response->assertSee('SHOP → COMPANY PAYMENTS');
        $response->assertSee('COMPANY → SHOP');
        $response->assertSee('PETTY CASH SETTINGS');
        $response->assertSee('SETTLEMENT SETTINGS');
        $response->assertSee('PAYMENT ALLOCATION SETTINGS');
        $response->assertSee('SHOP SALES REPORT');
        $response->assertSee('ADVANCED SETTINGS');
        $response->assertSee('data-payment-modal="petty"', false);
        $response->assertSee('Current Settings', false);
        $response->assertSee('document.body.appendChild(modal)', false);
    }

    /**
     * Test 2: Crucial Test - Changing September report mapping leaves August report unchanged.
     */
    public function test_change_september_report_mapping_leaves_august_report_unchanged(): void
    {
        $reportService = app(ShopPaymentsReportConfigService::class);

        // 1. Establish an August mapping and transaction
        $salesType = LedgerEntryType::where('category', 'sales')->first() ?? LedgerEntryType::create([
            'name' => 'Retail Sales',
            'code' => 'sales_retail',
            'category' => 'sales',
            'affects_cash' => true,
            'direction' => 'income',
        ]);
        $augustSetting = ShopLedgerEntrySetting::where('shop_id', $this->shop->id)->where('entry_type_id', $salesType->id)->first();
        if (! $augustSetting) {
            $augustSetting = ShopLedgerEntrySetting::create([
                'shop_id' => $this->shop->id,
                'entry_type_id' => $salesType->id,
                'effective_from' => '2026-01-01',
                'enabled' => true,
                'include_in_sales' => true,
            ]);
        }

        // Post an August transaction of 1500
        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
            'entry_type_code' => 'sales_retail',
            'business_date' => '2026-08-15',
            'amount' => 1500.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        // Post a September transaction of 3000
        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salesType->id,
            'entry_type_code' => 'sales_retail',
            'business_date' => '2026-09-10',
            'amount' => 3000.00,
            'direction' => 'income',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        // Save August configuration explicitly with total_sales pointing to augustSetting
        $augustHeadings = $reportService->getDefaultHeadings((int) $this->shop->id);
        $augustHeadings[ShopPaymentsReportConfigService::HEADING_TOTAL_SALES]['sources'] = [
            ['type' => 'category', 'id' => (int) $augustSetting->id, 'name' => $augustSetting->displayName()],
        ];
        $reportService->saveConfigurationForMonth((int) $this->shop->id, '2026-08', $augustHeadings, (int) $this->admin->id);

        // Verify August report output is 1500
        $augustReport = $reportService->calculateReport($this->shop, '2026-08-01', '2026-08-31', '2026-08');
        $this->assertEquals(1500.00, $augustReport['summary']['total_sales']);

        // 2. Now change September report mapping via endpoint to remove that category or point elsewhere
        $septemberHeadings = $augustHeadings;
        // Point September sales sources to empty (or an unrelated category)
        $septemberHeadings[ShopPaymentsReportConfigService::HEADING_TOTAL_SALES]['sources'] = [];

        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.settings.shop.payments.report-headings.save', $this->shop->id), [
            'month' => '2026-09',
            'headings' => $septemberHeadings,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // 3. Verify September sales calculation reflects the new empty mapping
        $septemberReport = $reportService->calculateReport($this->shop, '2026-09-01', '2026-09-30', '2026-09');
        $this->assertEquals(0.00, $septemberReport['summary']['total_sales']);

        // 4. CRUCIAL ASSERTION: August report remains unchanged at 1500!
        $augustReportAfter = $reportService->calculateReport($this->shop, '2026-08-01', '2026-08-31', '2026-08');
        $this->assertEquals(1500.00, $augustReportAfter['summary']['total_sales'], 'Historical August sales report was modified by September mapping changes!');
    }

    /**
     * Test 3: Report calculation engine guarantees Monthly Sum === SUM(Daily Rows).
     */
    public function test_report_calculation_guarantees_monthly_sum_equals_sum_of_daily_rows(): void
    {
        $reportService = app(ShopPaymentsReportConfigService::class);
        $setting = ShopLedgerEntrySetting::where('shop_id', $this->shop->id)->firstOrFail();

        // Save September configuration explicitly with total_sales pointing to $setting
        $septHeadings = $reportService->getDefaultHeadings((int) $this->shop->id);
        $septHeadings[ShopPaymentsReportConfigService::HEADING_TOTAL_SALES]['sources'] = [
            ['type' => 'category', 'id' => (int) $setting->id, 'name' => $setting->displayName()],
        ];
        $reportService->saveConfigurationForMonth((int) $this->shop->id, '2026-09', $septHeadings, (int) $this->admin->id);

        // Post transactions on multiple days
        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $setting->entry_type_id,
            'business_date' => '2026-09-01',
            'amount' => 1200.50,
            'direction' => 'income',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);
        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $setting->entry_type_id,
            'business_date' => '2026-09-05',
            'amount' => 850.25,
            'direction' => 'income',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);
        ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $setting->entry_type_id,
            'business_date' => '2026-09-12',
            'amount' => 430.25,
            'direction' => 'income',
            'funding_source' => 'sales',
            'status' => 'posted',
        ]);

        $report = $reportService->calculateReport($this->shop, '2026-09-01', '2026-09-30', '2026-09');

        $dailySum = round(collect($report['daily_rows'])->sum('sales'), 2);
        $monthlyTotal = $report['summary']['total_sales'];

        $this->assertEquals($dailySum, $monthlyTotal);
        $this->assertEquals(2481.00, $monthlyTotal);
    }

    /**
     * Test 4: Company Collections settings save with shop isolation.
     */
    public function test_company_collections_save_with_shop_isolation(): void
    {
        $account = CompanyAccount::create([
            'name' => 'Main Company Account',
            'account_type' => 'bank',
            'is_default' => true,
            'enabled' => true,
        ]);
        $setting = ShopLedgerEntrySetting::where('shop_id', $this->shop->id)->firstOrFail();

        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.settings.shop.payments.company-collections.save', $this->shop->id), [
            'mappings' => [
                $setting->id => [
                    'is_direct' => 1,
                    'company_account_id' => $account->id,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $setting->refresh();
        $this->assertEquals($account->id, $setting->company_account_id);

        // Verify other shop is untouched
        $otherShop = Shop::factory()->create(['accounting_enabled' => true]);
        app(CashbookShopSyncService::class)->syncAndGetProfiles();
        $otherSetting = ShopLedgerEntrySetting::where('shop_id', $otherShop->id)->where('entry_type_id', $setting->entry_type_id)->first();
        if ($otherSetting) {
            $this->assertNull($otherSetting->company_account_id);
        }
    }

    /**
     * Test 5: Petty Cash settings save correctly.
     */
    public function test_petty_cash_settings_save(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.settings.shop.payments.petty.save', $this->shop->id), [
            'enabled' => 1,
            'allow_company_to_petty' => 1,
            'shop_owner_view_petty' => 1,
            'allow_expenses_from_petty' => 0,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->profile->refresh();
        $pettyConfig = $this->profile->payment_configuration['petty'] ?? [];
        $this->assertTrue($pettyConfig['enabled']);
        $this->assertTrue($pettyConfig['allow_company_to_petty']);
        $this->assertFalse($pettyConfig['allow_expenses_from_petty']);
    }

    /**
     * Test 6: Settlement settings save relation items via shop_cashbook_relation_items.
     */
    public function test_settlement_settings_save_relation_items(): void
    {
        $relation = ShopCashbookRelation::where('shop_id', $this->shop->id)->firstOrFail();
        $setting = ShopLedgerEntrySetting::where('shop_id', $this->shop->id)->firstOrFail();

        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.settings.shop.payments.settlement.save', $this->shop->id), [
            'relation_id' => $relation->id,
            'items' => [
                [
                    'type' => 'category',
                    'id' => $setting->id,
                    'role' => 'add',
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $items = ShopCashbookRelationItem::where('relation_id', $relation->id)->get();
        $this->assertCount(1, $items);
        $this->assertEquals($setting->id, $items->first()->shop_ledger_entry_setting_id);
        $this->assertEquals('add', $items->first()->role);
    }

    /**
     * Test 7: Advanced settings update non-legacy fields and protect legacy settlement_behavior.
     */
    public function test_advanced_settings_protects_legacy_settlement_behavior(): void
    {
        $setting = ShopLedgerEntrySetting::where('shop_id', $this->shop->id)->firstOrFail();
        $setting->update(['settlement_behavior' => 'add']);

        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.settings.shop.payments.advanced.save', $this->shop->id), [
            'settings' => [
                $setting->id => [
                    'default_funding_source' => 'petty',
                    'company_pending_behavior' => 'deduct_from_balance',
                    'include_in_payable' => 1,
                    // Attempt to change legacy field should be ignored by saveSettings
                    'settlement_behavior' => 'subtract',
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $setting->refresh();
        $this->assertEquals('petty', $setting->default_funding_source);
        $this->assertEquals('deduct_from_balance', $setting->company_pending_behavior);
        $this->assertTrue((bool) $setting->include_in_payable);
        // Legacy settlement_behavior must remain 'add', not corrupted to 'subtract'
        $this->assertEquals('add', $setting->settlement_behavior);
    }

    /**
     * Test 8: Company to Shop has no save route (purely operational setup + output).
     */
    public function test_company_to_shop_has_no_artificial_save_route(): void
    {
        // Assert that calling a fictitious save route returns 404
        $response = $this->actingAs($this->admin)->postJson('/admin/cashbook/settings/shops/'.$this->shop->id.'/payments/company-to-shop/save', []);
        $response->assertStatus(404);
    }

    /**
     * Test 9: Regression test - Company to Shop query resolves via entryType relationship (not entry_type_code column).
     */
    public function test_company_to_shop_query_resolves_via_entry_type_relationship_with_transaction(): void
    {
        // 1. Creates LedgerEntryType code company_paid_shop
        $entryType = LedgerEntryType::where('code', 'company_paid_shop')->first() ?? LedgerEntryType::create([
            'name' => 'Company Paid to Shop',
            'code' => 'company_paid_shop',
            'category' => 'company_funding',
            'affects_cash' => true,
            'direction' => 'income',
        ]);

        // 2. Creates ShopLedgerTransaction using entry_type_id
        $transaction = ShopLedgerTransaction::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $entryType->id,
            'business_date' => '2026-09-15',
            'amount' => 4500.00,
            'direction' => 'income',
            'funding_source' => 'company_bank',
            'status' => 'posted',
        ]);

        // 3. Opens /admin/cashbook/settings/shops/{shop}/payments
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.settings.shop.payments.index', [
            'shop' => $this->shop->id,
            'month' => '2026-09',
        ]));

        // 4. Asserts HTTP 200
        $response->assertOk();

        // 5. Confirms Company → Shop output includes the transaction
        $response->assertViewHas('companyToShopData');
        $companyToShopData = $response->viewData('companyToShopData');
        $this->assertEquals(4500.00, $companyToShopData['output']['total_company_paid_shop']);
        $this->assertTrue($companyToShopData['recent_transactions']->contains(fn ($tx) => (int) $tx->id === (int) $transaction->id));
        $response->assertSee('4,500.00');
    }

    /**
     * Test 10: Regression test - Empty shop with no company_paid_shop transactions loads cleanly.
     */
    public function test_company_to_shop_query_with_empty_shop_no_transactions(): void
    {
        $emptyShop = Shop::factory()->create(['accounting_enabled' => true, 'accounting_mode' => 'owned']);
        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.settings.shop.payments.index', [
            'shop' => $emptyShop->id,
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $response->assertViewHas('companyToShopData');
        $companyToShopData = $response->viewData('companyToShopData');
        $this->assertEquals(0.00, $companyToShopData['output']['total_company_paid_shop']);
        $this->assertTrue($companyToShopData['recent_transactions']->isEmpty());
    }

    /**
     * Test 11: Regression test - Petty section queries ShopPettyCashExpense using business_date.
     */
    public function test_petty_section_queries_shop_petty_cash_expenses_using_business_date(): void
    {
        // 1. Create ShopPettyCashExpense for the shop using business_date in September
        $septemberExpense = ShopPettyCashExpense::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-09-10',
            'amount' => 750.00,
            'source' => 'manual',
        ]);

        // 2. Create another record for August (should be excluded by month filter)
        ShopPettyCashExpense::create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-20',
            'amount' => 300.00,
            'source' => 'manual',
        ]);

        // 3. Create another record for a different shop (should be excluded by shop_id)
        $foreignShop = Shop::factory()->create(['accounting_enabled' => true]);
        ShopPettyCashExpense::create([
            'shop_id' => $foreignShop->id,
            'business_date' => '2026-09-10',
            'amount' => 999.00,
            'source' => 'manual',
        ]);

        // 4. Open payments settings for September
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.settings.shop.payments.index', [
            'shop' => $this->shop->id,
            'month' => '2026-09',
        ]));

        // 5. Asserts HTTP 200 & verify petty output
        $response->assertOk();
        $response->assertViewHas('pettyData');
        $pettyData = $response->viewData('pettyData');

        // Verify discrepancy detection compares against the 750.00 September record (and excludes August & foreign shop)
        $this->assertTrue($pettyData['output']['has_discrepancy']);
        $this->assertStringContainsString('750', (string) $pettyData['output']['discrepancy_message']);
        $this->assertStringNotContainsString('999', (string) $pettyData['output']['discrepancy_message']);
    }

    /**
     * Test 12: Regression test - Petty section with empty shop and no petty expenses loads cleanly.
     */
    public function test_petty_section_with_empty_shop_no_petty_expenses(): void
    {
        $emptyShop = Shop::factory()->create(['accounting_enabled' => true, 'accounting_mode' => 'owned']);
        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.settings.shop.payments.index', [
            'shop' => $emptyShop->id,
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $response->assertViewHas('pettyData');
        $pettyData = $response->viewData('pettyData');
        $this->assertEquals(0.00, $pettyData['output']['closing_petty']);
        $this->assertFalse($pettyData['output']['has_discrepancy']);
    }
}
