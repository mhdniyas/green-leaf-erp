<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Cashbook\ShopSalaryBridgeSetting;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\ShopSalaryBridgeService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class CashbookSalarySettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $hrManager;

    private User $regularUser;

    private Shop $shop;

    private ShopLedgerProfile $profile;

    private ShopLedgerEntrySetting $salaryEntrySetting;

    private ShopLedgerEntrySetting $advanceEntrySetting;

    private ShopCashbookRelation $companyPayableSettlement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);

        $this->admin = User::factory()->create(['email' => 'admin@greenleaf.test']);
        $this->admin->assignRole('admin');

        $this->hrManager = User::factory()->create(['email' => 'hr@greenleaf.test']);
        $this->hrManager->assignRole('hr_manager');

        $this->regularUser = User::factory()->create(['email' => 'user@greenleaf.test']);

        $this->shop = Shop::query()->create([
            'name' => 'Casio Test Shop',
            'code' => 'CASIO_TEST',
            'warehouse_tag' => 'CS',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $this->profile = ShopLedgerProfile::query()->create([
            'shop_id' => $this->shop->id,
            'name' => $this->shop->name,
            'code' => $this->shop->code,
            'slug' => 'casio-test',
        ]);

        $salaryType = LedgerEntryType::query()->where('code', 'salary')->firstOrFail();
        $advanceType = LedgerEntryType::query()->where('code', 'staff_advance')->firstOrFail();

        $this->salaryEntrySetting = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $salaryType->id,
            'display_name' => 'Staff Salary Payable',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_expense' => true,
            'effective_from' => '2026-01-01',
            'version' => 1,
        ]);

        $this->advanceEntrySetting = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $advanceType->id,
            'display_name' => 'Staff Advance Payout',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_expense' => true,
            'effective_from' => '2026-01-01',
            'version' => 1,
        ]);

        $this->companyPayableSettlement = ShopCashbookRelation::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Salary Settlement',
            'enabled' => true,
            'is_company_payable' => true,
            'is_net_balance' => false,
        ]);
    }

    public function test_get_salary_settings_is_pure_and_does_not_mutate_database(): void
    {
        $initialBridgeRows = ShopSalaryBridgeSetting::count();
        $initialLedgerTransactions = ShopLedgerTransaction::count();

        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.settings.shop.salary.index', $this->profile->slug)
        );

        $response->assertOk();
        $response->assertSeeText('Salary Settings:');
        $response->assertSeeText('Casio Test Shop');
        $response->assertSeeText('Salary Advance');

        // Verify ZERO rows were created in shop_salary_bridge_settings or transactions on GET
        $this->assertSame($initialBridgeRows, ShopSalaryBridgeSetting::count());
        $this->assertSame($initialLedgerTransactions, ShopLedgerTransaction::count());
        $this->assertSame(0, ShopSalaryBridgeSetting::count());
    }

    public function test_hr_manager_can_view_salary_settings_in_read_only_mode(): void
    {
        $response = $this->actingAs($this->hrManager)->get(
            route('admin.cashbook.settings.shop.salary.index', $this->profile->slug)
        );

        $response->assertOk();
        $response->assertSeeText('Viewing in Read-Only mode');
        $response->assertDontSeeText('Save Salary Settings');
    }

    public function test_unauthorized_user_cannot_view_or_update_salary_settings(): void
    {
        $viewResponse = $this->actingAs($this->regularUser)->get(
            route('admin.cashbook.settings.shop.salary.index', $this->profile->slug)
        );
        $viewResponse->assertRedirect(route('dashboard'));
        $viewResponse->assertSessionHas('error', 'You do not have access to that page.');

        $updateResponse = $this->actingAs($this->regularUser)->post(
            route('admin.cashbook.settings.shop.salary.update', $this->profile->slug),
            ['types' => []]
        );
        $updateResponse->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.cashbook.settings.shop.salary.index', $this->profile->slug));
        $response->assertRedirect('/login');
    }

    public function test_root_salary_settings_redirects_to_first_shop(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.settings.salary'));
        $response->assertRedirect(route('admin.cashbook.settings.shop.salary.index', $this->profile->slug));
    }

    public function test_admin_can_save_salary_bridge_settings(): void
    {
        $payload = [
            'types' => [
                'salary' => [
                    'shop_ledger_entry_setting_id' => $this->salaryEntrySetting->id,
                    'allowed_payment_modes' => ['sales_cash', 'petty', 'company_payable'],
                    'default_payment_mode' => 'sales_cash',
                    'company_payable_settlement_id' => $this->companyPayableSettlement->id,
                    'is_enabled' => 1,
                ],
                'salary_advance' => [
                    'shop_ledger_entry_setting_id' => $this->advanceEntrySetting->id,
                    'allowed_payment_modes' => ['sales_cash', 'petty'],
                    'default_payment_mode' => 'sales_cash',
                    'company_payable_settlement_id' => null,
                    'is_enabled' => 1,
                ],
                'salary_adjustment' => [
                    'shop_ledger_entry_setting_id' => null,
                    'allowed_payment_modes' => ['sales_cash'],
                    'default_payment_mode' => 'sales_cash',
                    'company_payable_settlement_id' => null,
                    'is_enabled' => 1,
                ],
                'advance_recovery' => [
                    'shop_ledger_entry_setting_id' => $this->advanceEntrySetting->id,
                    'allowed_payment_modes' => ['sales_cash', 'company_payable'],
                    'default_payment_mode' => 'company_payable',
                    'company_payable_settlement_id' => $this->companyPayableSettlement->id,
                    'is_enabled' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.settings.shop.salary.update', $this->profile->slug),
            $payload
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check that rows exist in the DB
        $this->assertDatabaseHas('shop_salary_bridge_settings', [
            'shop_id' => $this->shop->id,
            'transaction_type' => 'salary',
            'shop_ledger_entry_setting_id' => $this->salaryEntrySetting->id,
            'default_payment_mode' => 'sales_cash',
            'company_payable_settlement_id' => $this->companyPayableSettlement->id,
        ]);

        $this->assertDatabaseHas('shop_salary_bridge_settings', [
            'shop_id' => $this->shop->id,
            'transaction_type' => 'salary_advance',
            'shop_ledger_entry_setting_id' => $this->advanceEntrySetting->id,
            'default_payment_mode' => 'sales_cash',
            'company_payable_settlement_id' => null,
        ]);

        // Asserts zero financial/ledger transactions created
        $this->assertSame(0, ShopLedgerTransaction::count());

        // Asserts audit log was created
        $this->assertTrue(
            Activity::query()
                ->where('log_name', 'cashbook_salary_settings')
                ->where('properties->shop_id', $this->shop->id)
                ->exists()
        );
    }

    public function test_default_mode_auto_falls_back_when_not_in_allowed_modes(): void
    {
        $payload = [
            'types' => [
                'salary' => [
                    'shop_ledger_entry_setting_id' => $this->salaryEntrySetting->id,
                    'allowed_payment_modes' => ['petty'],
                    'default_payment_mode' => 'sales_cash', // not in allowed
                    'company_payable_settlement_id' => null,
                    'is_enabled' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.settings.shop.salary.update', $this->profile->slug),
            $payload
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('shop_salary_bridge_settings', [
            'shop_id' => $this->shop->id,
            'transaction_type' => 'salary',
            'default_payment_mode' => 'petty', // fell back to only allowed mode
        ]);
    }

    public function test_cannot_link_settlement_belonging_to_another_shop(): void
    {
        $otherShop = Shop::query()->create([
            'name' => 'Other Shop',
            'code' => 'OTHER',
            'status' => 'active',
            'accounting_enabled' => true,
            'is_active' => true,
        ]);

        $otherSettlement = ShopCashbookRelation::query()->create([
            'shop_id' => $otherShop->id,
            'name' => 'Other Shop Settlement',
            'enabled' => true,
            'is_company_payable' => true,
        ]);

        $payload = [
            'types' => [
                'salary' => [
                    'shop_ledger_entry_setting_id' => $this->salaryEntrySetting->id,
                    'allowed_payment_modes' => ['sales_cash', 'company_payable'],
                    'default_payment_mode' => 'sales_cash',
                    'company_payable_settlement_id' => $otherSettlement->id, // belongs to other shop
                    'is_enabled' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.settings.shop.salary.update', $this->profile->slug),
            $payload
        );

        $response->assertRedirect();
        // foreign settlement from another shop must be nullified
        $this->assertDatabaseHas('shop_salary_bridge_settings', [
            'shop_id' => $this->shop->id,
            'transaction_type' => 'salary',
            'company_payable_settlement_id' => null,
        ]);
    }

    // -----------------------------------------------------------------
    // Step 5: Default Configuration + Reset to Default
    // -----------------------------------------------------------------

    public function test_default_configuration_returns_canonical_values(): void
    {
        $defaults = ShopSalaryBridgeService::defaultConfiguration();

        $this->assertSame(['sales_cash', 'company_payable', 'petty'], $defaults['allowed_payment_modes']);
        $this->assertSame('sales_cash', $defaults['default_payment_mode']);
    }

    public function test_initialize_defaults_creates_rows_for_all_types(): void
    {
        $this->assertSame(0, ShopSalaryBridgeSetting::where('shop_id', $this->shop->id)->count());

        $service = app(ShopSalaryBridgeService::class);
        $service->initializeDefaultsForShop($this->shop);

        $this->assertSame(4, ShopSalaryBridgeSetting::where('shop_id', $this->shop->id)->count());

        $defaults = ShopSalaryBridgeService::defaultConfiguration();

        foreach (['salary', 'salary_advance', 'salary_adjustment', 'advance_recovery'] as $type) {
            $row = ShopSalaryBridgeSetting::query()
                ->where('shop_id', $this->shop->id)
                ->where('transaction_type', $type)
                ->first();

            $this->assertNotNull($row);
            $this->assertNull($row->shop_ledger_entry_setting_id);
            $this->assertSame($defaults['default_payment_mode'], $row->default_payment_mode);
            $this->assertSame($defaults['allowed_payment_modes'], $row->allowed_payment_modes);
        }
    }

    public function test_initialize_defaults_is_idempotent_and_does_not_overwrite_existing_rows(): void
    {
        // Create a customised salary row first
        ShopSalaryBridgeSetting::query()->create([
            'shop_id' => $this->shop->id,
            'transaction_type' => 'salary',
            'shop_ledger_entry_setting_id' => $this->salaryEntrySetting->id,
            'allowed_payment_modes' => ['petty'],
            'default_payment_mode' => 'petty',
            'is_enabled' => true,
        ]);

        $service = app(ShopSalaryBridgeService::class);
        $service->initializeDefaultsForShop($this->shop);

        // salary row must be unchanged
        $this->assertDatabaseHas('shop_salary_bridge_settings', [
            'shop_id' => $this->shop->id,
            'transaction_type' => 'salary',
            'shop_ledger_entry_setting_id' => $this->salaryEntrySetting->id,
            'default_payment_mode' => 'petty',
        ]);

        // Other 3 types must have been created
        $this->assertSame(4, ShopSalaryBridgeSetting::where('shop_id', $this->shop->id)->count());
    }

    public function test_reset_to_default_resets_modes_but_preserves_category_and_settlement(): void
    {
        // Custom configuration: petty-only, category mapped, settlement linked
        ShopSalaryBridgeSetting::query()->create([
            'shop_id' => $this->shop->id,
            'transaction_type' => 'salary',
            'shop_ledger_entry_setting_id' => $this->salaryEntrySetting->id,
            'allowed_payment_modes' => ['petty'],
            'default_payment_mode' => 'petty',
            'company_payable_settlement_id' => $this->companyPayableSettlement->id,
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.settings.shop.salary.reset', $this->profile->slug)
        );

        $response->assertRedirect(
            route('admin.cashbook.settings.shop.salary.index', $this->profile->slug)
        );
        $response->assertSessionHas('success');

        $defaults = ShopSalaryBridgeService::defaultConfiguration();

        $row = ShopSalaryBridgeSetting::query()
            ->where('shop_id', $this->shop->id)
            ->where('transaction_type', 'salary')
            ->first();

        $this->assertNotNull($row);

        // Payment modes must be reset to canonical defaults
        $this->assertSame($defaults['allowed_payment_modes'], $row->allowed_payment_modes);
        $this->assertSame($defaults['default_payment_mode'], $row->default_payment_mode);

        // Category and settlement must be preserved
        $this->assertSame($this->salaryEntrySetting->id, $row->shop_ledger_entry_setting_id);
        $this->assertSame($this->companyPayableSettlement->id, $row->company_payable_settlement_id);
    }

    public function test_reset_to_default_creates_missing_rows_for_all_types(): void
    {
        // No rows exist
        $this->assertSame(0, ShopSalaryBridgeSetting::where('shop_id', $this->shop->id)->count());

        $this->actingAs($this->admin)->post(
            route('admin.cashbook.settings.shop.salary.reset', $this->profile->slug)
        )->assertRedirect();

        // All 4 types must exist after reset
        $this->assertSame(4, ShopSalaryBridgeSetting::where('shop_id', $this->shop->id)->count());
    }

    public function test_reset_to_default_records_audit_log_with_action_flag(): void
    {
        $this->actingAs($this->admin)->post(
            route('admin.cashbook.settings.shop.salary.reset', $this->profile->slug)
        )->assertRedirect();

        $audit = Activity::query()
            ->where('log_name', 'cashbook_salary_settings')
            ->where('properties->shop_id', $this->shop->id)
            ->where('properties->action', 'reset_to_default')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($this->admin->id, $audit->causer_id);
        $this->assertStringContainsString('Reset salary settings to default', $audit->description);
    }

    public function test_unauthorized_user_cannot_reset_salary_settings(): void
    {
        $response = $this->actingAs($this->hrManager)->post(
            route('admin.cashbook.settings.shop.salary.reset', $this->profile->slug)
        );

        $response->assertForbidden();
        $this->assertSame(0, ShopSalaryBridgeSetting::where('shop_id', $this->shop->id)->count());
    }

    public function test_reset_does_not_create_ledger_transactions(): void
    {
        $this->actingAs($this->admin)->post(
            route('admin.cashbook.settings.shop.salary.reset', $this->profile->slug)
        )->assertRedirect();

        $this->assertSame(0, ShopLedgerTransaction::count());
    }
}
