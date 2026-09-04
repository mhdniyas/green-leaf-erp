<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopCashbookRelationItem;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\CashFlowResolutionService;
use App\Services\Cashbook\RelationSettlementCalculator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HeaderCashFlowSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    private CashFlowResolutionService $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@greenleaf.com',
        ]);
        $this->admin->assignRole('admin');

        $this->shop = Shop::factory()->create([
            'name' => 'Casio Veg Market',
            'code' => 'CASIO-CF-01',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();
        $this->resolver = app(CashFlowResolutionService::class);
    }

    public function test_admin_can_create_and_update_header_cash_flow_configuration(): void
    {
        $companyAccount = CompanyAccount::firstOrCreate(
            ['name' => 'HDFC Main Account'],
            [
                'account_number' => '12345678',
                'bank_name' => 'HDFC',
                'account_type' => 'current',
                'enabled' => true,
            ]
        );

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.headers.create'), [
                'shop_id' => $this->shop->id,
                'name' => 'Direct Bank Sales',
                'type' => 'income',
                'cash_flow_mode' => 'company_account',
                'company_account_id' => $companyAccount->id,
                'note_enabled' => 1,
                'show_both_sides' => 1,
                'product_tagging_enabled' => 0,
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $headerId = $response->json('header.id');
        $header = ShopLedgerHeaderGroup::findOrFail($headerId);

        $this->assertEquals('income', $header->type);
        $this->assertEquals('company_account', $header->cash_flow_mode);
        $this->assertEquals($companyAccount->id, $header->company_account_id);
        $this->assertTrue($header->isNoteEnabled());
        $this->assertTrue($header->showsBothSides());

        // Update header to Shop Cash and show_both_sides OFF
        $updateResponse = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.headers.update'), [
                'id' => $header->id,
                'name' => 'Shop Cash Sales',
                'cash_flow_mode' => 'shop_cash',
                'company_account_id' => null,
                'note_enabled' => 0,
                'show_both_sides' => 0,
            ]);

        $updateResponse->assertOk();
        $header->refresh();

        $this->assertEquals('Shop Cash Sales', $header->name);
        $this->assertEquals('shop_cash', $header->cash_flow_mode);
        $this->assertNull($header->company_account_id);
        $this->assertFalse($header->isNoteEnabled());
        $this->assertFalse($header->showsBothSides());
    }

    public function test_header_show_both_sides_defaults_to_false(): void
    {
        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'Rent Header',
            'type' => 'expense',
            'enabled' => true,
        ]);

        $this->assertFalse($header->show_both_sides);
        $this->assertFalse($header->showsBothSides());
    }

    public function test_expense_header_cash_flow_mode_petty_resolves_funding_source(): void
    {
        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'Petty Expenses',
            'type' => 'expense',
            'cash_flow_mode' => 'petty',
            'enabled' => true,
        ]);

        $entryType = LedgerEntryType::firstOrCreate(
            ['code' => 'cleaning_exp'],
            [
                'name' => 'Cleaning Material',
                'category' => 'expense',
                'active' => true,
            ]
        );

        $setting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $entryType->id,
            'header_group_id' => $header->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
        ]);

        $setting->load('headerGroup');

        $this->assertEquals('petty', $this->resolver->resolveFundingSource($setting));
        $this->assertEquals('petty', $setting->effectiveFundingSource());
    }

    public function test_expense_header_cash_flow_mode_company_resolves_funding_source(): void
    {
        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'Company Direct Expenses',
            'type' => 'expense',
            'cash_flow_mode' => 'company',
            'enabled' => true,
        ]);

        $entryType = LedgerEntryType::firstOrCreate(
            ['code' => 'gl_bill_custom'],
            [
                'name' => 'GL Bill Custom',
                'category' => 'expense',
                'active' => true,
            ]
        );

        $setting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $entryType->id,
            'header_group_id' => $header->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
        ]);

        $setting->load('headerGroup');

        $this->assertEquals('company', $this->resolver->resolveFundingSource($setting));
        $this->assertEquals('company', $setting->effectiveFundingSource());
    }

    public function test_income_header_company_account_resolves_account_id(): void
    {
        $companyAccount = CompanyAccount::firstOrCreate(
            ['name' => 'IDFC Bank Account'],
            [
                'account_number' => '87654321',
                'bank_name' => 'IDFC',
                'account_type' => 'current',
                'enabled' => true,
            ]
        );

        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'Card / Paytm Direct Inflows',
            'type' => 'income',
            'cash_flow_mode' => 'company_account',
            'company_account_id' => $companyAccount->id,
            'enabled' => true,
        ]);

        $entryType = LedgerEntryType::firstOrCreate(
            ['code' => 'card_custom'],
            [
                'name' => 'Card Machine Inflow',
                'category' => 'income',
                'active' => true,
            ]
        );

        $setting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $entryType->id,
            'header_group_id' => $header->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'company_account_id' => null,
        ]);

        $setting->load('headerGroup');

        $this->assertEquals($companyAccount->id, $this->resolver->resolveCompanyAccountId($setting));
        $this->assertEquals($companyAccount->id, $setting->effectiveCompanyAccountId());
    }

    public function test_child_setting_inherits_note_enabled_from_parent_header(): void
    {
        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'Header With Note Enabled',
            'type' => 'expense',
            'note_enabled' => true,
            'enabled' => true,
        ]);

        $entryType = LedgerEntryType::firstOrCreate(
            ['code' => 'misc_rent_exp'],
            [
                'name' => 'Misc Rent Expense',
                'category' => 'expense',
                'active' => true,
            ]
        );

        $setting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $entryType->id,
            'header_group_id' => $header->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'note_enabled' => false,
        ]);

        $setting->load('headerGroup');

        $this->assertTrue($this->resolver->resolveNoteEnabled($setting));
        $this->assertTrue($setting->isNoteEnabled());
    }

    public function test_transfer_header_resolves_movement_summary_label(): void
    {
        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'Sales to Petty',
            'type' => 'other',
            'from_balance' => 'shop_cash',
            'to_balance' => 'petty',
            'enabled' => true,
        ]);

        $summary = $this->resolver->resolveHeaderSummaryLabel($header);
        $this->assertStringContainsString('Shop Cash', $summary);
        $this->assertStringContainsString('Petty', $summary);
    }

    public function test_mixed_income_header_with_per_entry_destinations(): void
    {
        $accA = CompanyAccount::firstOrCreate(
            ['name' => 'Shaanu Account'],
            ['account_number' => '1111', 'bank_name' => 'HDFC', 'account_type' => 'current', 'enabled' => true]
        );
        $accB = CompanyAccount::firstOrCreate(
            ['name' => 'IDFC Bank'],
            ['account_number' => '2222', 'bank_name' => 'IDFC', 'account_type' => 'current', 'enabled' => true]
        );

        $salesHeader = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'SALES',
            'type' => 'income',
            'cash_flow_mode' => 'entry_decides',
            'enabled' => true,
        ]);

        $paytmType = LedgerEntryType::firstOrCreate(['code' => 'paytm_sales'], ['name' => 'Paytm', 'category' => 'income', 'active' => true]);
        $cardType = LedgerEntryType::firstOrCreate(['code' => 'card_sales'], ['name' => 'Card', 'category' => 'income', 'active' => true]);
        $cashType = LedgerEntryType::firstOrCreate(['code' => 'cash_sales'], ['name' => 'Cash', 'category' => 'income', 'active' => true]);

        $paytmSetting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $paytmType->id,
            'header_group_id' => $salesHeader->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'company',
            'company_account_id' => $accA->id,
        ]);

        $cardSetting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $cardType->id,
            'header_group_id' => $salesHeader->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'company',
            'company_account_id' => $accB->id,
        ]);

        $cashSetting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $cashType->id,
            'header_group_id' => $salesHeader->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'company_account_id' => null,
        ]);

        $salesHeader->load('entrySettings.entryType', 'entrySettings.companyAccount');

        $summary = $this->resolver->resolveHeaderSummaryLabel($salesHeader);
        $this->assertEquals('Cash Flow: Mixed destinations', $summary);

        $destLabelPaytm = $this->resolver->resolveDestinationLabel($paytmSetting);
        $destLabelCard = $this->resolver->resolveDestinationLabel($cardSetting);
        $destLabelCash = $this->resolver->resolveDestinationLabel($cashSetting);

        $this->assertEquals('Shaanu Account', $destLabelPaytm);
        $this->assertEquals('IDFC Bank', $destLabelCard);
        $this->assertEquals('Shop Cash', $destLabelCash);

        $childMappings = $this->resolver->resolveHeaderChildDestinations($salesHeader);
        $this->assertCount(3, $childMappings);
        $this->assertContains('Paytm → Shaanu Account', $childMappings);
        $this->assertContains('Card → IDFC Bank', $childMappings);
        $this->assertContains('Cash → Shop Cash', $childMappings);

        // Verify company account routing vs shop cash
        $this->assertEquals($accA->id, $this->resolver->resolveCompanyAccountId($paytmSetting));
        $this->assertEquals($accB->id, $this->resolver->resolveCompanyAccountId($cardSetting));
        $this->assertNull($this->resolver->resolveCompanyAccountId($cashSetting));
        $this->assertEquals('sales', $this->resolver->resolveFundingSource($cashSetting));
    }

    public function test_mixed_expense_header_with_per_entry_sources(): void
    {
        $expenseHeader = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'SHOP EXPENSES',
            'type' => 'expense',
            'cash_flow_mode' => 'entry_decides',
            'enabled' => true,
        ]);

        $rentType = LedgerEntryType::firstOrCreate(['code' => 'rent_mix'], ['name' => 'Rent', 'category' => 'expense', 'active' => true]);
        $messType = LedgerEntryType::firstOrCreate(['code' => 'mess_mix'], ['name' => 'Mess', 'category' => 'expense', 'active' => true]);
        $vehicleType = LedgerEntryType::firstOrCreate(['code' => 'vehicle_mix'], ['name' => 'Vehicle', 'category' => 'expense', 'active' => true]);

        $rentSetting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $rentType->id,
            'header_group_id' => $expenseHeader->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
        ]);

        $messSetting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $messType->id,
            'header_group_id' => $expenseHeader->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'petty',
        ]);

        $vehicleSetting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $vehicleType->id,
            'header_group_id' => $expenseHeader->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'company',
        ]);

        $expenseHeader->load('entrySettings.entryType', 'entrySettings.companyAccount');

        $summary = $this->resolver->resolveHeaderSummaryLabel($expenseHeader);
        $this->assertEquals('Cash Flow: Mixed sources', $summary);

        $this->assertEquals('sales', $this->resolver->resolveFundingSource($rentSetting));
        $this->assertEquals('petty', $this->resolver->resolveFundingSource($messSetting));
        $this->assertEquals('company', $this->resolver->resolveFundingSource($vehicleSetting));

        $childMappings = $this->resolver->resolveHeaderChildDestinations($expenseHeader);
        $this->assertCount(3, $childMappings);
        $this->assertContains('Rent → Shop Cash', $childMappings);
        $this->assertContains('Mess → Petty', $childMappings);
        $this->assertContains('Vehicle → Company', $childMappings);
    }

    public function test_expense_header_without_both_sides_does_not_mirror(): void
    {
        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'Rent',
            'type' => 'expense',
            'show_both_sides' => false,
            'enabled' => true,
        ]);

        $this->assertFalse($header->showsBothSides());
    }

    public function test_expense_header_with_both_sides_derives_mirror_without_real_db_transactions(): void
    {
        $initialTxCount = DB::table('shop_ledger_transactions')->count();

        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'Cash Purchase',
            'type' => 'expense',
            'show_both_sides' => true,
            'enabled' => true,
        ]);

        $tomatoType = LedgerEntryType::firstOrCreate(['code' => 'tomato_exp'], ['name' => 'Tomato', 'category' => 'expense', 'active' => true]);
        $bananaType = LedgerEntryType::firstOrCreate(['code' => 'banana_exp'], ['name' => 'Banana', 'category' => 'expense', 'active' => true]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $tomatoType->id,
            'header_group_id' => $header->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $bananaType->id,
            'header_group_id' => $header->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        $this->assertTrue($header->showsBothSides());

        // Zero additional financial transactions or P&L income records created in database
        $this->assertEquals($initialTxCount, DB::table('shop_ledger_transactions')->count());

        // Turning Both Sides OFF removes mirror flag
        $header->update(['show_both_sides' => false]);
        $this->assertFalse($header->fresh()->showsBothSides());
    }

    public function test_demo_page_loads_with_show_both_sides_serialized_header(): void
    {
        ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'Cash Purchase Both Sides',
            'type' => 'expense',
            'show_both_sides' => true,
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.settings.shop.demo', ['shop' => $this->shop->id]));

        $response->assertOk();
        $response->assertSee('show_both_sides');
    }

    public function test_mirrored_header_uses_exact_saved_name_and_single_line_on_opposite_side(): void
    {
        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shop->id,
            'name' => 'Cash Purchase',
            'type' => 'expense',
            'show_both_sides' => true,
            'enabled' => true,
        ]);

        $tomatoType = LedgerEntryType::firstOrCreate(['code' => 'tomato_exp_single'], ['name' => 'Tomato', 'category' => 'expense', 'active' => true]);
        $bananaType = LedgerEntryType::firstOrCreate(['code' => 'banana_exp_single'], ['name' => 'Banana', 'category' => 'expense', 'active' => true]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $tomatoType->id,
            'header_group_id' => $header->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $bananaType->id,
            'header_group_id' => $header->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.settings.shop.demo', ['shop' => $this->shop->id]));

        $response->assertOk();
        $response->assertSee('Cash Purchase');
        $response->assertSee('income-mirrored-headers-container');
        $response->assertSee('expense-mirrored-headers-container');
        $response->assertSee('dynamic-net-position-content');
        $response->assertDontSee('id="bill-sales"');
        $response->assertDontSee('id="bill-expenses"');
    }

    public function test_relation_settlement_from_previous_shop_balance_calculator_rules(): void
    {
        $relation = new ShopCashbookRelation([
            'name' => 'Supermarket Settlement',
            'eligibility_rule' => 'previous_day_balance',
            'enabled' => true,
        ]);

        $itemSetting = new ShopLedgerEntrySetting;
        $itemSetting->id = 101;

        $relItem = new ShopCashbookRelationItem([
            'shop_ledger_entry_setting_id' => 101,
            'role' => 'add',
        ]);
        $relation->setRelation('items', collect([$relItem]));

        $calculator = new RelationSettlementCalculator;

        // 1. Full settlement from previous balance (Opening ₹20,000, Settlement ₹4,535, Today collection ₹15,331)
        $resFull = $calculator->calculate($relation, [101 => 4535.0], 20000.0, 15331.0);
        $this->assertEquals(4535.0, $resFull['settledAmount']);
        $this->assertEquals(0.0, $resFull['remainingSettlementPayable']);
        $this->assertEquals(30796.0, $resFull['closingEligibleBalance']); // 20000 - 4535 + 15331 = 30796

        // 2. Partial settlement when opening balance is insufficient (Opening ₹3,000, Settlement ₹4,535, Today collection ₹15,331)
        $resPartial = $calculator->calculate($relation, [101 => 4535.0], 3000.0, 15331.0);
        $this->assertEquals(3000.0, $resPartial['settledAmount']);
        $this->assertEquals(1535.0, $resPartial['remainingSettlementPayable']); // ₹1,535 carries forward!
        $this->assertEquals(15331.0, $resPartial['closingEligibleBalance']); // 3000 - 3000 + 15331 = 15331

        // 3. Current-day balance mode allows today's collections to fund settlement
        $relationCurrent = new ShopCashbookRelation([
            'name' => 'Supermarket Settlement Current',
            'eligibility_rule' => 'current_available_balance',
            'enabled' => true,
        ]);
        $relationCurrent->setRelation('items', collect([$relItem]));

        $resCurrent = $calculator->calculate($relationCurrent, [101 => 4535.0], 3000.0, 15331.0);
        $this->assertEquals(4535.0, $resCurrent['settledAmount']);
        $this->assertEquals(0.0, $resCurrent['remainingSettlementPayable']);
        $this->assertEquals(13796.0, $resCurrent['closingEligibleBalance']); // (3000 + 15331) - 4535 = 13796
    }

    public function test_demo_page_renders_today_net_activity_and_balance_movements_sections(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.settings.shop.demo', ['shop' => $this->shop->id]));

        $response->assertOk();
        $response->assertSee('Today Net Activity');
        $response->assertSee('BALANCE MOVEMENTS');
        $response->assertSee('From: Previous Shop Balance');
    }

    public function test_demo_page_renders_save_and_clear_demo_browser_storage_controls(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.settings.shop.demo', ['shop' => $this->shop->id]));

        $response->assertOk();
        $response->assertSee('Save Demo');
        $response->assertSee('Clear Demo');
        $response->assertSee('demo-storage-status');
        $response->assertSee('saveDemoToLocalStorage');
        $response->assertSee('clearSavedDemoData');
    }
}
