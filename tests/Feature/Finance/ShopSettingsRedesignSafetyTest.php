<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopCashbookRelationItem;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\RelationSettlementCalculator;
use App\Services\Cashbook\ShopCashbookSimulationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopSettingsRedesignSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $casio;

    private ShopLedgerProfile $casioProfile;

    private CompanyAccount $hdfcBank;

    private CompanyAccount $kotakBank;

    private LedgerEntryType $cashSalesType;

    private LedgerEntryType $paytmType;

    private LedgerEntryType $cardType;

    private LedgerEntryType $rentType;

    private LedgerEntryType $unconfiguredIncomeType;

    private LedgerEntryType $unconfiguredExpenseType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@greenleaf.com',
        ]);
        $this->admin->assignRole('admin');

        $this->casio = Shop::factory()->create([
            'name' => 'Casio Shop',
            'code' => 'CASIO-01',
        ]);

        $this->casioProfile = ShopLedgerProfile::create([
            'shop_id' => $this->casio->id,
            'is_active' => true,
            'auto_approve_cashbook' => true,
            'require_verification' => true,
            'opening_mode' => 'manual',
            'closing_mode' => 'manual',
        ]);

        $this->hdfcBank = CompanyAccount::create([
            'name' => 'HDFC Bank Main',
            'public_uuid' => 'hdfc-uuid-1111-2222',
            'account_type' => 'bank',
            'bank_name' => 'HDFC Bank',
            'account_number' => '1234567890',
            'opening_balance' => 10000,
            'current_balance' => 10000,
            'is_default' => true,
            'enabled' => true,
        ]);

        $this->kotakBank = CompanyAccount::create([
            'name' => 'Kotak Bank Shop Account',
            'public_uuid' => 'kotak-uuid-3333-4444',
            'account_type' => 'bank',
            'bank_name' => 'Kotak Mahindra',
            'account_number' => '0987654321',
            'opening_balance' => 5000,
            'current_balance' => 5000,
            'is_default' => false,
            'enabled' => true,
        ]);

        $this->cashSalesType = LedgerEntryType::create([
            'code' => 'cash_sales',
            'name' => 'Cash Sales',
            'category' => 'income',
            'active' => true,
            'display_order' => 1,
        ]);

        $this->paytmType = LedgerEntryType::create([
            'code' => 'paytm',
            'name' => 'Paytm',
            'category' => 'income',
            'active' => true,
            'display_order' => 2,
        ]);

        $this->cardType = LedgerEntryType::create([
            'code' => 'card',
            'name' => 'Card',
            'category' => 'income',
            'active' => true,
            'display_order' => 3,
        ]);

        $this->rentType = LedgerEntryType::create([
            'code' => 'rent',
            'name' => 'Shop Rent',
            'category' => 'expense',
            'active' => true,
            'display_order' => 10,
        ]);

        $this->unconfiguredIncomeType = LedgerEntryType::create([
            'code' => 'commission_income',
            'name' => 'Commission Income',
            'category' => 'income',
            'active' => true,
            'display_order' => 20,
        ]);

        $this->unconfiguredExpenseType = LedgerEntryType::create([
            'code' => 'generator_diesel',
            'name' => 'Generator Diesel',
            'category' => 'expense',
            'active' => true,
            'display_order' => 21,
        ]);
    }

    public function test_shop_settings_page_renders_successfully(): void
    {
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->cashSalesType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
            'include_in_payable' => true,
            'payable_direction' => 'add',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.settings.shop', ['shop' => $this->casio->id]));

        $response->assertStatus(200);
        $response->assertSee('Casio Shop');
        $response->assertSee('Income &amp; Sales', false);
        $response->assertSee('Expenses');
        $response->assertSee('Show Disabled Entries');
        $response->assertSee('Demo Cashbook');
    }

    public function test_admin_can_access_demo_cashbook_page(): void
    {
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->cashSalesType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_pl' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.settings.shop.demo', ['shop' => $this->casio->id]));

        $response->assertStatus(200);
        $response->assertSee('3-Day Cashbook Demo');
        $response->assertSee('Auto Fill 3 Days');
    }

    public function test_demo_simulation_service_calculates_carry_forward_without_database_writes(): void
    {
        $salesSetting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->cashSalesType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_pl' => true,
        ]);

        $rentSetting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->rentType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_sales' => false,
            'include_in_expense' => true,
            'include_in_pl' => true,
        ]);

        $daysInput = [
            1 => [
                $salesSetting->id => 5000,
                $rentSetting->id => 1000,
            ],
            2 => [
                $salesSetting->id => 3000,
                $rentSetting->id => 500,
            ],
            3 => [
                $salesSetting->id => 0,
                $rentSetting->id => 0,
            ],
        ];

        $simulationService = app(ShopCashbookSimulationService::class);
        $sim = $simulationService->simulate3Days(
            10000,
            2000,
            [$salesSetting, $rentSetting],
            $daysInput
        );

        $this->assertEquals(10000, $sim['initialOpeningPayable']);

        // Day 1: Opening 10000 + Sales 5000 - Rent 1000 = Closing 14000
        $this->assertEquals(10000, $sim['days'][1]['openingPayable']);
        $this->assertEquals(14000, $sim['days'][1]['closingPayable']);

        // Day 2: Opening 14000 + Sales 3000 - Rent 500 = Closing 16500
        $this->assertEquals(14000, $sim['days'][2]['openingPayable']);
        $this->assertEquals(16500, $sim['days'][2]['closingPayable']);

        // Day 3: Opening 16500 + 0 = Closing 16500
        $this->assertEquals(16500, $sim['days'][3]['openingPayable']);
        $this->assertEquals(16500, $sim['days'][3]['closingPayable']);

        // GUARANTEE: Zero database rows written to transaction tables!
        $this->assertEquals(0, ShopLedgerTransaction::count());
    }

    public function test_default_view_shows_active_cards_and_hides_disabled_cards(): void
    {
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->cashSalesType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_pl' => true,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->paytmType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => false,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_pl' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.settings.shop', ['shop' => $this->casio->id]));

        $response->assertStatus(200);
        $response->assertSee('Active');
        $response->assertSee('data-enabled="0"', false);
        $response->assertSee('hidden', false);
    }

    public function test_search_and_add_modal_lists_disabled_settings_for_reenable(): void
    {
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->paytmType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => false,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_pl' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.settings.shop', ['shop' => $this->casio->id]));

        $response->assertStatus(200);
        $response->assertSee('Previously configured • Disabled');
        $response->assertSee('Re-enable');
    }

    public function test_updating_paytm_bank_account_preserves_accounting_flags(): void
    {
        $setting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $this->hdfcBank->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
            'include_in_payable' => false,
            'payable_direction' => null,
            'settlement_behavior' => 'none',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
            'generates_secondary_entry' => false,
            'secondary_entry_type_id' => null,
            'secondary_amount_mode' => 'same_amount',
        ]);

        $payload = [
            'setting_id' => $setting->id,
            'company_account_id' => $this->kotakBank->id,
            'enabled' => true,
            'default_funding_source' => 'none',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
            'include_in_payable' => false,
            'payable_direction' => null,
            'settlement_behavior' => 'none',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
            'generates_secondary_entry' => false,
            'secondary_entry_type_id' => null,
            'secondary_amount_mode' => 'same_amount',
            'secondary_amount_value' => null,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.update'), $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $setting->refresh();
        $this->assertEquals($this->kotakBank->id, $setting->company_account_id);
        $this->assertTrue($setting->include_in_sales);
        $this->assertTrue($setting->include_in_income);
        $this->assertTrue($setting->include_in_pl);
        $this->assertFalse($setting->include_in_payable);
    }

    public function test_updating_expense_funding_source_preserves_petty_behavior(): void
    {
        $setting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->rentType->id,
            'company_account_id' => null,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'petty',
            'include_in_sales' => false,
            'include_in_income' => false,
            'include_in_expense' => true,
            'include_in_pl' => true,
            'include_in_payable' => false,
            'payable_direction' => 'minus',
            'settlement_behavior' => 'none',
            'petty_behavior' => 'decrease',
            'company_pending_behavior' => 'none',
            'generates_secondary_entry' => false,
            'secondary_entry_type_id' => null,
            'secondary_amount_mode' => 'same_amount',
        ]);

        $payload = [
            'setting_id' => $setting->id,
            'company_account_id' => null,
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_sales' => false,
            'include_in_income' => false,
            'include_in_expense' => true,
            'include_in_pl' => true,
            'include_in_payable' => true,
            'payable_direction' => 'minus',
            'settlement_behavior' => 'decrease',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
            'generates_secondary_entry' => false,
            'secondary_entry_type_id' => null,
            'secondary_amount_mode' => 'same_amount',
            'secondary_amount_value' => null,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.update'), $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $setting->refresh();
        $this->assertEquals('sales', $setting->default_funding_source);
        $this->assertTrue($setting->include_in_payable);
        $this->assertEquals('minus', $setting->payable_direction);
        $this->assertEquals('decrease', $setting->settlement_behavior);
    }

    public function test_custom_row_creation_adds_new_entry_setting_for_shop(): void
    {
        $payload = [
            'shop_id' => (string) $this->casio->id,
            'name' => 'Tea Counter Sales',
            'category' => 'income',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.custom-row'), $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $createdType = LedgerEntryType::where('name', 'Tea Counter Sales')->first();
        $this->assertNotNull($createdType);

        $setting = ShopLedgerEntrySetting::where('shop_id', $this->casio->id)
            ->where('entry_type_id', $createdType->id)
            ->first();

        $this->assertNotNull($setting);
        $this->assertTrue($setting->enabled);
    }

    public function test_create_new_income_category_with_custom_code_funding_and_company_account(): void
    {
        $payload = [
            'shop_id' => (string) $this->casio->id,
            'name' => 'Other Delivery Income',
            'code' => 'other_delivery_income',
            'category' => 'income',
            'default_funding_source' => 'bank',
            'company_account_id' => $this->hdfcBank->id,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.custom-row'), $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $createdType = LedgerEntryType::where('code', 'other_delivery_income')->first();
        $this->assertNotNull($createdType);
        $this->assertEquals('income', $createdType->category);

        $setting = ShopLedgerEntrySetting::where('shop_id', $this->casio->id)
            ->where('entry_type_id', $createdType->id)
            ->first();

        $this->assertNotNull($setting);
        $this->assertTrue($setting->enabled);
        $this->assertTrue($setting->include_in_sales);
        $this->assertFalse($setting->include_in_expense);
        $this->assertEquals('bank', $setting->default_funding_source);
        $this->assertEquals($this->hdfcBank->id, $setting->company_account_id);
    }

    public function test_create_new_expense_category_with_petty_funding(): void
    {
        $payload = [
            'shop_id' => (string) $this->casio->id,
            'name' => 'Daily Mess',
            'code' => 'daily_mess',
            'category' => 'expense',
            'default_funding_source' => 'petty',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.custom-row'), $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $createdType = LedgerEntryType::where('code', 'daily_mess')->first();
        $this->assertNotNull($createdType);
        $this->assertEquals('expense', $createdType->category);

        $setting = ShopLedgerEntrySetting::where('shop_id', $this->casio->id)
            ->where('entry_type_id', $createdType->id)
            ->first();

        $this->assertNotNull($setting);
        $this->assertTrue($setting->enabled);
        $this->assertFalse($setting->include_in_sales);
        $this->assertTrue($setting->include_in_expense);
        $this->assertEquals('petty', $setting->default_funding_source);
    }

    public function test_unique_slug_generation_for_duplicate_names(): void
    {
        $payload = [
            'shop_id' => (string) $this->casio->id,
            'name' => 'Cash Sales',
            'category' => 'income',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.custom-row'), $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $createdType = LedgerEntryType::where('code', 'cash_sales_2')->first();
        $this->assertNotNull($createdType);
        $this->assertEquals('income', $createdType->category);
    }

    public function test_existing_categories_start_unassigned_by_default(): void
    {
        $setting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->cashSalesType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_pl' => true,
        ]);

        $this->assertNull($setting->header_group_id);
    }

    public function test_admin_can_create_header_group_and_it_starts_empty(): void
    {
        $payload = [
            'shop_id' => (string) $this->casio->id,
            'name' => 'Sales',
            'type' => 'income',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.headers.create'), $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $header = ShopLedgerHeaderGroup::where('name', 'Sales')->first();
        $this->assertNotNull($header);
        $this->assertEquals($this->casio->id, $header->shop_id);
        $this->assertEquals('income', $header->type);
        $this->assertCount(0, $header->entrySettings);
    }

    public function test_assigning_setting_to_header_does_not_change_accounting_flags(): void
    {
        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->casio->id,
            'name' => 'Sales',
            'type' => 'income',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $setting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->cashSalesType->id,
            'company_account_id' => $this->hdfcBank->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
            'include_in_payable' => true,
            'payable_direction' => 'add',
        ]);

        $payload = [
            'setting_id' => $setting->id,
            'header_group_id' => $header->id,
            'header_display_order' => 1,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.assign-header'), $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $setting->refresh();
        $this->assertEquals($header->id, $setting->header_group_id);

        // ACCOUNTING ISOLATION ASSERTIONS: All accounting fields remain untouched!
        $this->assertEquals($this->hdfcBank->id, $setting->company_account_id);
        $this->assertEquals('sales', $setting->default_funding_source);
        $this->assertTrue($setting->include_in_sales);
        $this->assertTrue($setting->include_in_income);
        $this->assertFalse($setting->include_in_expense);
        $this->assertTrue($setting->include_in_pl);
        $this->assertTrue($setting->include_in_payable);
        $this->assertEquals('add', $setting->payable_direction);
    }

    public function test_reordering_headers_persists_display_order(): void
    {
        $h1 = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->casio->id,
            'name' => 'Header A',
            'type' => 'income',
            'display_order' => 1,
        ]);

        $h2 = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->casio->id,
            'name' => 'Header B',
            'type' => 'income',
            'display_order' => 2,
        ]);

        $payload = [
            'shop_id' => (string) $this->casio->id,
            'header_ids' => [$h2->id, $h1->id],
        ];

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.headers.reorder'), $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $h1->refresh();
        $h2->refresh();

        $this->assertEquals(2, $h1->display_order);
        $this->assertEquals(1, $h2->display_order);
    }

    public function test_deleting_header_group_resets_settings_to_unassigned(): void
    {
        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->casio->id,
            'name' => 'Temporary Header',
            'type' => 'income',
            'display_order' => 1,
        ]);

        $setting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->cashSalesType->id,
            'header_group_id' => $header->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_pl' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.headers.delete'), ['id' => $header->id]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $setting->refresh();
        $this->assertNull($setting->header_group_id);
    }

    public function test_creating_custom_row_from_inside_header_assigns_to_that_header(): void
    {
        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->casio->id,
            'name' => 'Shop Operating Expenses',
            'type' => 'expense',
            'display_order' => 1,
        ]);

        $payload = [
            'shop_id' => (string) $this->casio->id,
            'name' => 'Mess Expense',
            'code' => 'mess_expense',
            'category' => 'expense',
            'default_funding_source' => 'petty',
            'header_group_id' => $header->id,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.custom-row'), $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $createdType = LedgerEntryType::where('code', 'mess_expense')->first();
        $setting = ShopLedgerEntrySetting::where('shop_id', $this->casio->id)
            ->where('entry_type_id', $createdType->id)
            ->first();

        $this->assertNotNull($setting);
        $this->assertEquals($header->id, $setting->header_group_id);
    }

    public function test_shop_can_create_relation_and_it_starts_blank(): void
    {
        $payload = [
            'shop_id' => (string) $this->casio->id,
            'name' => 'Supermarket Settlement',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.relations.create'), $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $relation = ShopCashbookRelation::where('shop_id', $this->casio->id)->where('name', 'Supermarket Settlement')->first();
        $this->assertNotNull($relation);
        $this->assertNull($relation->settlement_source);
        $this->assertNull($relation->eligibility_rule);
        $this->assertCount(0, $relation->items);
    }

    public function test_admin_can_add_entry_to_relation_with_explicit_role(): void
    {
        $relation = ShopCashbookRelation::create([
            'shop_id' => $this->casio->id,
            'name' => 'Supermarket Settlement',
        ]);

        $setting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->cashSalesType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        $payload = [
            'relation_id' => $relation->id,
            'setting_id' => $setting->id,
            'role' => 'add',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.relations.add-item'), $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertCount(1, $relation->fresh()->items);
        $this->assertEquals('add', $relation->fresh()->items->first()->role);
    }

    public function test_same_entry_cannot_be_added_twice_to_same_relation(): void
    {
        $relation = ShopCashbookRelation::create([
            'shop_id' => $this->casio->id,
            'name' => 'Supermarket Settlement',
        ]);

        $setting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->cashSalesType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        ShopCashbookRelationItem::create([
            'relation_id' => $relation->id,
            'shop_ledger_entry_setting_id' => $setting->id,
            'role' => 'add',
        ]);

        $payload = [
            'relation_id' => $relation->id,
            'setting_id' => $setting->id,
            'role' => 'subtract',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.relations.add-item'), $payload);

        // updateOrCreate updates role instead of creating duplicate row
        $response->assertStatus(200);
        $this->assertCount(1, ShopCashbookRelationItem::where('relation_id', $relation->id)->get());
    }

    public function test_cross_shop_entry_assignment_to_relation_is_rejected(): void
    {
        $otherShop = Shop::factory()->create(['name' => 'Other Shop']);

        $relation = ShopCashbookRelation::create([
            'shop_id' => $this->casio->id,
            'name' => 'Supermarket Settlement',
        ]);

        $otherShopSetting = ShopLedgerEntrySetting::create([
            'shop_id' => $otherShop->id,
            'entry_type_id' => $this->cashSalesType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        $payload = [
            'relation_id' => $relation->id,
            'setting_id' => $otherShopSetting->id,
            'role' => 'add',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.relations.add-item'), $payload);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_removing_relation_item_does_not_delete_shop_entry_setting(): void
    {
        $relation = ShopCashbookRelation::create([
            'shop_id' => $this->casio->id,
            'name' => 'Supermarket Settlement',
        ]);

        $setting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->cashSalesType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        $item = ShopCashbookRelationItem::create([
            'relation_id' => $relation->id,
            'shop_ledger_entry_setting_id' => $setting->id,
            'role' => 'add',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.relations.delete-item'), ['item_id' => $item->id]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('shop_cashbook_relation_items', ['id' => $item->id]);
        $this->assertDatabaseHas('shop_ledger_entry_settings', ['id' => $setting->id]);
    }

    public function test_formula_calculation_4000_plus_560_plus_1500_minus_1000_equals_5060(): void
    {
        $relation = ShopCashbookRelation::create([
            'shop_id' => $this->casio->id,
            'name' => 'Supermarket Settlement',
            'settlement_source' => 'shop_balance',
            'eligibility_rule' => 'previous_day_balance',
        ]);

        $rentType = LedgerEntryType::create(['code' => 'test_rent', 'name' => 'Rent', 'category' => 'expense', 'active' => true]);
        $messType = LedgerEntryType::create(['code' => 'test_mess', 'name' => 'Mess', 'category' => 'expense', 'active' => true]);
        $casioDelType = LedgerEntryType::create(['code' => 'test_casio_del', 'name' => 'Casio Delivery', 'category' => 'expense', 'active' => true]);
        $normalDelType = LedgerEntryType::create(['code' => 'test_normal_del', 'name' => 'Normal Delivery', 'category' => 'expense', 'active' => true]);

        $sRent = ShopLedgerEntrySetting::create(['shop_id' => $this->casio->id, 'entry_type_id' => $rentType->id, 'version' => 1, 'effective_from' => '2026-01-01', 'enabled' => true]);
        $sMess = ShopLedgerEntrySetting::create(['shop_id' => $this->casio->id, 'entry_type_id' => $messType->id, 'version' => 1, 'effective_from' => '2026-01-01', 'enabled' => true]);
        $sCasioDel = ShopLedgerEntrySetting::create(['shop_id' => $this->casio->id, 'entry_type_id' => $casioDelType->id, 'version' => 1, 'effective_from' => '2026-01-01', 'enabled' => true]);
        $sNormalDel = ShopLedgerEntrySetting::create(['shop_id' => $this->casio->id, 'entry_type_id' => $normalDelType->id, 'version' => 1, 'effective_from' => '2026-01-01', 'enabled' => true]);

        ShopCashbookRelationItem::create(['relation_id' => $relation->id, 'shop_ledger_entry_setting_id' => $sRent->id, 'role' => 'add']);
        ShopCashbookRelationItem::create(['relation_id' => $relation->id, 'shop_ledger_entry_setting_id' => $sMess->id, 'role' => 'add']);
        ShopCashbookRelationItem::create(['relation_id' => $relation->id, 'shop_ledger_entry_setting_id' => $sCasioDel->id, 'role' => 'add']);
        ShopCashbookRelationItem::create(['relation_id' => $relation->id, 'shop_ledger_entry_setting_id' => $sNormalDel->id, 'role' => 'subtract']);

        $calculator = app(RelationSettlementCalculator::class);

        $result = $calculator->calculate(
            $relation->load('items.setting.entryType'),
            [
                $sRent->id => 4000.0,
                $sMess->id => 560.0,
                $sCasioDel->id => 1500.0,
                $sNormalDel->id => 1000.0,
            ],
            15000.0,
            10000.0
        );

        $this->assertEquals(5060.0, $result['netSettlement']);
        $this->assertEquals(5060.0, $result['settledAmount']);
        $this->assertEquals(0.0, $result['remainingSettlementPayable']);
        $this->assertEquals(19940.0, $result['closingEligibleBalance']); // (15000 - 5060) + 10000 = 19940
    }

    public function test_previous_day_balance_rule_prevents_today_collection_funding_same_day_settlement(): void
    {
        $relation = ShopCashbookRelation::create([
            'shop_id' => $this->casio->id,
            'name' => 'Supermarket Settlement',
            'settlement_source' => 'shop_balance',
            'eligibility_rule' => 'previous_day_balance',
        ]);

        $rentType = LedgerEntryType::create(['code' => 'test_rent_2', 'name' => 'Rent 2', 'category' => 'expense', 'active' => true]);
        $sRent = ShopLedgerEntrySetting::create(['shop_id' => $this->casio->id, 'entry_type_id' => $rentType->id, 'version' => 1, 'effective_from' => '2026-01-01', 'enabled' => true]);

        ShopCashbookRelationItem::create(['relation_id' => $relation->id, 'shop_ledger_entry_setting_id' => $sRent->id, 'role' => 'add']);

        $calculator = app(RelationSettlementCalculator::class);

        // Opening eligible balance = 3000, today's relation settlement = 5060, today's new collection = 10000
        $result = $calculator->calculate(
            $relation->load('items.setting.entryType'),
            [$sRent->id => 5060.0],
            3000.0,
            10000.0
        );
        $this->assertEquals(5060.0, $result['netSettlement']);
        $this->assertEquals(3000.0, $result['settledAmount']); // Limited to opening balance 3000
        $this->assertEquals(2060.0, $result['remainingSettlementPayable']); // 5060 - 3000 = 2060
        $this->assertEquals(10000.0, $result['closingEligibleBalance']); // (3000 - 3000) + 10000 = 10000
    }

    public function test_demo_page_loads_configured_active_entries_and_relations(): void
    {
        $relation = ShopCashbookRelation::create([
            'shop_id' => $this->casio->id,
            'name' => 'Supermarket Settlement',
            'enabled' => true,
        ]);

        $disabledSetting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->unconfiguredExpenseType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.settings.shop.demo', ['shop' => $this->casio->id]));

        $response->assertStatus(200);
        $response->assertSee('3-Day Cashbook Demo');
        $response->assertSee('Supermarket Settlement');
    }

    public function test_shop_without_relation_loads_demo_normally(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.settings.shop.demo', ['shop' => $this->casio->id]));

        $response->assertStatus(200);
        $response->assertSee('3-Day Cashbook Demo');
    }

    public function test_demo_page_contains_no_configuration_mutation_form_or_save_action(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.settings.shop.demo', ['shop' => $this->casio->id]));

        $response->assertStatus(200);
        $response->assertDontSee('save-configuration');
        $response->assertDontSee('Save Configuration');
        $response->assertDontSee('Customize Cashbook');
    }

    public function test_demo_page_renders_two_side_layout_and_movement_cards(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.settings.shop.demo', ['shop' => $this->casio->id]));

        $response->assertStatus(200);
        $response->assertSee('Shop Activity (Inputs)');
        $response->assertSee('Cash Movement');
        $response->assertSee('Payable to Company');
        $response->assertSee('Direct to Company Bank Accounts');
        $response->assertSee('Company Position (Active Day)');
        $response->assertSee('Petty Movement');
        $response->assertSee('3-Day Activity');
        $response->assertSee('Final Simulation Positions (End of Day 3)');
    }

    public function test_simulation_service_multi_day_carryforward_and_isolation(): void
    {
        $cashSalesSetting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->cashSalesType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_sales' => true,
        ]);

        $paytmSetting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->paytmType->id,
            'company_account_id' => $this->hdfcBank->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_sales' => true,
        ]);

        $rentSetting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->casio->id,
            'entry_type_id' => $this->rentType->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'default_funding_source' => 'petty',
        ]);

        $service = app(ShopCashbookSimulationService::class);

        $activeSettings = [$cashSalesSetting, $paytmSetting, $rentSetting];

        $daysInput = [
            1 => [$cashSalesSetting->id => 8000.0, $paytmSetting->id => 12000.0, $rentSetting->id => 1000.0],
            2 => [$cashSalesSetting->id => 9000.0, $paytmSetting->id => 15000.0, $rentSetting->id => 500.0],
            3 => [$cashSalesSetting->id => 7000.0, $paytmSetting->id => 10000.0, $rentSetting->id => 200.0],
        ];

        $res = $service->simulate3Days(
            openingPayable: 15000.0,
            openingPetty: 5000.0,
            activeSettings: $activeSettings,
            daysInput: $daysInput,
            relations: [],
            openingShopBalance: 15000.0
        );

        $this->assertEquals(15000.0, $res['days'][1]['openingPayable']);
        $this->assertEquals(23000.0, $res['days'][1]['closingPayable']); // 15000 + 8000
        $this->assertEquals(4000.0, $res['days'][1]['closingPetty']); // 5000 - 1000

        // Day 1 closing feeds Day 2 opening
        $this->assertEquals(23000.0, $res['days'][2]['openingPayable']);
        $this->assertEquals(32000.0, $res['days'][2]['closingPayable']); // 23000 + 9000
        $this->assertEquals(3500.0, $res['days'][2]['closingPetty']); // 4000 - 500

        // Day 2 closing feeds Day 3 opening
        $this->assertEquals(32000.0, $res['days'][3]['openingPayable']);
        $this->assertEquals(39000.0, $res['days'][3]['closingPayable']); // 32000 + 7000
        $this->assertEquals(3300.0, $res['days'][3]['closingPetty']); // 3500 - 200

        // Direct company collection (Paytm) bypasses shop payable
        $this->assertEquals(12000.0, $res['days'][1]['directCompanyBankTotal']);
        $this->assertEquals(15000.0, $res['days'][2]['directCompanyBankTotal']);
        $this->assertEquals(10000.0, $res['days'][3]['directCompanyBankTotal']);
    }

    public function test_demo_page_respects_saved_header_groups_and_does_not_collapse_them(): void
    {
        $shop = Shop::factory()->create(['name' => 'Header Test Shop']);
        ShopLedgerProfile::create([
            'shop_id' => $shop->id,
            'is_active' => true,
        ]);

        $header1 = ShopLedgerHeaderGroup::create([
            'shop_id' => $shop->id,
            'name' => 'Retail Sales',
            'type' => 'income',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $header2 = ShopLedgerHeaderGroup::create([
            'shop_id' => $shop->id,
            'name' => 'Other Sales',
            'type' => 'income',
            'display_order' => 2,
            'enabled' => true,
        ]);

        $header3 = ShopLedgerHeaderGroup::create([
            'shop_id' => $shop->id,
            'name' => 'Purchase',
            'type' => 'expense',
            'display_order' => 3,
            'enabled' => true,
        ]);

        $header4 = ShopLedgerHeaderGroup::create([
            'shop_id' => $shop->id,
            'name' => 'Shop Operating Expenses',
            'type' => 'expense',
            'display_order' => 4,
            'enabled' => true,
        ]);

        $settingA = ShopLedgerEntrySetting::create([
            'shop_id' => $shop->id,
            'entry_type_id' => $this->cashSalesType->id,
            'header_group_id' => $header1->id,
            'header_display_order' => 1,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        $settingB = ShopLedgerEntrySetting::create([
            'shop_id' => $shop->id,
            'entry_type_id' => $this->paytmType->id,
            'header_group_id' => $header1->id,
            'header_display_order' => 2,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        $settingC = ShopLedgerEntrySetting::create([
            'shop_id' => $shop->id,
            'entry_type_id' => $this->unconfiguredIncomeType->id,
            'header_group_id' => $header2->id,
            'header_display_order' => 1,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        $settingD = ShopLedgerEntrySetting::create([
            'shop_id' => $shop->id,
            'entry_type_id' => $this->rentType->id,
            'header_group_id' => $header4->id,
            'header_display_order' => 1,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.settings.shop.demo', ['shop' => $shop->id]));

        $response->assertStatus(200);

        // Assert all distinct saved header names appear separately
        $response->assertSee('Retail Sales');
        $response->assertSee('Other Sales');
        $response->assertSee('Shop Operating Expenses');

        // Assert header order: Retail Sales comes before Other Sales
        $content = $response->getContent();
        $posH1 = strpos($content, 'Retail Sales');
        $posH2 = strpos($content, 'Other Sales');
        $posH4 = strpos($content, 'Shop Operating Expenses');

        $this->assertNotFalse($posH1);
        $this->assertNotFalse($posH2);
        $this->assertNotFalse($posH4);
        $this->assertTrue($posH1 < $posH2, 'Retail Sales must appear before Other Sales based on display_order.');
        $this->assertTrue($posH2 < $posH4, 'Other Sales must appear before Shop Operating Expenses based on display_order.');
    }

    public function test_demo_page_creates_zero_financial_transactions_and_zero_config_writes(): void
    {
        $this->actingAs($this->admin)->get(route('admin.cashbook.settings.shop.demo', ['shop' => $this->casio->id]));

        $txCount = ShopLedgerTransaction::count();
        $settingCount = ShopLedgerEntrySetting::count();
        $relationCount = ShopCashbookRelation::count();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.settings.shop.demo', ['shop' => $this->casio->id]));

        $response->assertStatus(200);
        $this->assertEquals($txCount, ShopLedgerTransaction::count());
        $this->assertEquals($settingCount, ShopLedgerEntrySetting::count());
        $this->assertEquals($relationCount, ShopCashbookRelation::count());
    }

    public function test_any_header_type_can_enable_and_disable_product_tagging(): void
    {
        // 1. Create Income Header with tagging OFF by default
        $incomeHeader = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->casio->id,
            'name' => 'Other Sales',
            'type' => 'income',
            'display_order' => 1,
            'enabled' => true,
            'product_tagging_enabled' => false,
        ]);

        $this->assertFalse($incomeHeader->product_tagging_enabled);

        // 2. Enable Product Tagging on Income Header via API
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.headers.update'), [
                'id' => $incomeHeader->id,
                'name' => 'Other Sales',
                'product_tagging_enabled' => true,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertTrue($incomeHeader->fresh()->product_tagging_enabled);

        // 3. Create Expense Header with tagging ON
        $expenseHeader = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->casio->id,
            'name' => 'Cash Purchase',
            'type' => 'expense',
            'display_order' => 2,
            'enabled' => true,
            'product_tagging_enabled' => true,
        ]);

        $this->assertTrue($expenseHeader->product_tagging_enabled);

        // 4. Disable Product Tagging ON -> OFF
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.headers.update'), [
                'id' => $expenseHeader->id,
                'product_tagging_enabled' => false,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertFalse($expenseHeader->fresh()->product_tagging_enabled);
    }

    public function test_product_search_uses_existing_catalog_only_without_creating_fake_products_or_entry_types(): void
    {
        $tomato = Product::factory()->create([
            'name' => 'Tomato',
            'sku' => 'TOM-001',
            'is_active' => true,
        ]);

        $banana = Product::factory()->create([
            'name' => 'Banana',
            'sku' => 'BAN-001',
            'is_active' => true,
        ]);

        $productCountBefore = Product::count();
        $entryTypeCountBefore = LedgerEntryType::count();

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.api.products.search', ['q' => 'Tom']));

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonFragment(['name' => 'Tomato']);

        $this->assertEquals($productCountBefore, Product::count(), 'Search must not create new products.');
        $this->assertEquals($entryTypeCountBefore, LedgerEntryType::count(), 'Search must not create new ledger entry types.');
    }

    public function test_turning_product_tagging_off_does_not_delete_products_or_cashbook_data(): void
    {
        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->casio->id,
            'name' => 'Shop Operating Expenses',
            'type' => 'expense',
            'display_order' => 1,
            'enabled' => true,
            'product_tagging_enabled' => true,
        ]);

        $product = Product::factory()->create([
            'name' => 'Onion',
            'is_active' => true,
        ]);

        $productCountBefore = Product::count();

        // Turn OFF tagging
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.headers.update'), [
                'id' => $header->id,
                'product_tagging_enabled' => false,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertFalse($header->fresh()->product_tagging_enabled);

        $this->assertEquals($productCountBefore, Product::count(), 'Turning tagging OFF must not delete product catalog.');
    }

    public function test_demo_page_renders_product_tagging_button_and_product_modal_for_tagged_headers(): void
    {
        ShopLedgerHeaderGroup::create([
            'shop_id' => $this->casio->id,
            'name' => 'Product Wastage',
            'type' => 'expense',
            'display_order' => 1,
            'enabled' => true,
            'product_tagging_enabled' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.settings.shop.demo', ['shop' => $this->casio->id]));

        $response->assertStatus(200);
        $response->assertSee('Product Wastage');
        $response->assertSee('+ Add Product');
        $response->assertSee('Product Tagging');
        $response->assertSee('demo-product-modal');
    }

    public function test_product_search_supports_server_side_pagination_and_sku_id_matching(): void
    {
        $product = Product::factory()->create([
            'name' => 'Alfonso Mango',
            'sku' => 'SKU-MANGO-999',
            'is_active' => true,
        ]);

        $responseBySku = $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.api.products.search', ['q' => 'MANGO-999']));

        $responseBySku->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonFragment(['name' => 'Alfonso Mango'])
            ->assertJsonStructure(['success', 'products', 'current_page', 'last_page', 'total', 'has_more']);

        $responseById = $this->actingAs($this->admin)
            ->getJson(route('admin.cashbook.api.products.search', ['q' => (string) $product->id]));

        $responseById->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonFragment(['name' => 'Alfonso Mango']);
    }
}
