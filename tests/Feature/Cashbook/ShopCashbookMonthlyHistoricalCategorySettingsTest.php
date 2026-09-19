<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\CategoryVendorMapping;
use App\Models\Cashbook\ShopCashbookMonthConfigSnapshot;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\ShopSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\ShopCashbookMonthConfigService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShopCashbookMonthlyHistoricalCategorySettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shopA;

    private Shop $shopB;

    private ShopCashbookMonthConfigService $configService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->shopA = Shop::query()->create([
            'name' => 'Shop Alpha',
            'code' => 'SHOP_ALPHA',
            'warehouse_tag' => 'ALPHA',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $this->shopB = Shop::query()->create([
            'name' => 'Shop Beta',
            'code' => 'SHOP_BETA',
            'warehouse_tag' => 'BETA',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();
        $this->configService = app(ShopCashbookMonthConfigService::class);
    }

    public function test_1_current_month_uses_live_editable_settings(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.settings.shop', [
            'shop' => $this->shopA->slug ?: $this->shopA->shop_id,
            'month' => '2026-09',
        ]));

        $response->assertOk();
        $response->assertViewHas('isReadOnly', false);
        $response->assertViewHas('isCurrentMonth', true);
        $response->assertSee('Current Configuration');
        $response->assertDontSee('Historical Configuration');
        $response->assertDontSee('READ ONLY');
    }

    public function test_2_previous_month_loads_historical_settings(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        // Create an August 2026 snapshot for Shop A
        $this->configService->captureSnapshot($this->shopA->id, '2026-08', 'manual_snapshot', false, $this->admin->id, 'Frozen August config');

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.settings.shop', [
            'shop' => $this->shopA->slug ?: $this->shopA->shop_id,
            'month' => '2026-08',
        ]));

        $response->assertOk();
        $response->assertViewHas('isReadOnly', true);
        $response->assertViewHas('isCurrentMonth', false);
        $response->assertSee('Historical Configuration');
        $response->assertSee('READ ONLY');
        $response->assertSee('August 2026');
    }

    public function test_3_all_categories_appear_even_with_no_transactions(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        // Ensure zero transactions exist
        $this->assertSame(0, ShopLedgerTransaction::where('shop_id', $this->shopA->id)->count());

        $this->configService->captureSnapshot($this->shopA->id, '2026-08', 'manual_snapshot', false, $this->admin->id);

        $historicalConfig = $this->configService->getConfigurationForMonth($this->shopA->id, '2026-08');

        // Total categories in snapshot should match all live settings
        $liveCount = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)->count();
        $this->assertGreaterThan(0, $liveCount);
        $this->assertCount($liveCount, $historicalConfig['settings']);
    }

    public function test_4_all_headers_appear_as_they_existed_that_month(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        // Create custom header in August
        $augustHeader = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shopA->id,
            'name' => 'August Special Expenses',
            'type' => 'expense',
            'cash_flow_mode' => 'entry_decides',
            'display_order' => 99,
            'enabled' => true,
        ]);

        $this->configService->captureSnapshot($this->shopA->id, '2026-08', 'manual_snapshot', false, $this->admin->id);

        // Delete header in September (live)
        $augustHeader->delete();

        // Historical August config should still have 'August Special Expenses'
        $historical = $this->configService->getConfigurationForMonth($this->shopA->id, '2026-08');
        $headerNames = $historical['headers']->pluck('name')->all();
        $this->assertContains('August Special Expenses', $headerNames);

        // Live September should NOT have it
        $live = $this->configService->getConfigurationForMonth($this->shopA->id, '2026-09');
        $liveHeaderNames = $live['headers']->pluck('name')->all();
        $this->assertNotContains('August Special Expenses', $liveHeaderNames);
    }

    public function test_5_moving_a_category_this_month_does_not_move_it_in_previous_month(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $header1 = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shopA->id,
            'name' => 'Header One',
            'type' => 'expense',
            'cash_flow_mode' => 'entry_decides',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $header2 = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shopA->id,
            'name' => 'Header Two',
            'type' => 'expense',
            'cash_flow_mode' => 'entry_decides',
            'display_order' => 2,
            'enabled' => true,
        ]);

        $setting = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)->firstOrFail();
        $setting->update(['header_group_id' => $header1->id]);

        // Capture August snapshot
        $this->configService->captureSnapshot($this->shopA->id, '2026-08', 'manual_snapshot', false, $this->admin->id);

        // In September, move setting to Header Two via controller
        $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.shop-settings.assign-header'), [
            'setting_id' => $setting->id,
            'header_group_id' => $header2->id,
        ])->assertOk();

        // Verify Live has Header Two
        $setting->refresh();
        $this->assertSame($header2->id, $setting->header_group_id);

        // Verify August snapshot still has Header One
        $augustConfig = $this->configService->getConfigurationForMonth($this->shopA->id, '2026-08');
        $augustSetting = $augustConfig['settings']->firstWhere('id', $setting->id);
        $this->assertSame($header1->id, $augustSetting->header_group_id);
    }

    public function test_6_renaming_a_header_does_not_rename_historical_snapshot(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shopA->id,
            'name' => 'Original August Name',
            'type' => 'income',
            'cash_flow_mode' => 'entry_decides',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $this->configService->captureSnapshot($this->shopA->id, '2026-08', 'manual_snapshot', false, $this->admin->id);

        // Rename header in live September
        $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.shop-settings.headers.update'), [
            'id' => $header->id,
            'name' => 'Renamed September Name',
            'type' => 'income',
            'cash_flow_mode' => 'entry_decides',
            'enabled' => 1,
        ])->assertOk();

        // Live should be renamed
        $header->refresh();
        $this->assertSame('Renamed September Name', $header->name);

        // August snapshot should maintain original name
        $augustConfig = $this->configService->getConfigurationForMonth($this->shopA->id, '2026-08');
        $augustHeader = $augustConfig['headers']->firstWhere('id', $header->id);
        $this->assertSame('Original August Name', $augustHeader->name);
    }

    public function test_7_enabling_disabling_category_does_not_alter_historical_state(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $setting = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)->firstOrFail();
        $setting->update(['enabled' => true]);

        $this->configService->captureSnapshot($this->shopA->id, '2026-08', 'manual_snapshot', false, $this->admin->id);

        // Disable category in live September
        $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.shop-settings.toggle-status'), [
            'setting_id' => $setting->id,
            'enabled' => false,
        ])->assertOk();

        $setting->refresh();
        $this->assertFalse($setting->enabled);

        // August historical snapshot remains enabled
        $augustConfig = $this->configService->getConfigurationForMonth($this->shopA->id, '2026-08');
        $augustSetting = $augustConfig['settings']->firstWhere('id', $setting->id);
        $this->assertTrue($augustSetting->enabled);
    }

    public function test_8_vendor_purchase_split_changes_do_not_rewrite_previous_month(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $setting = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)->firstOrFail();
        $setting->update([
            'is_vendor_purchase' => true,
            'vendor_purchase_payment_type' => 'all',
            'vendor_access_mode' => 'all',
        ]);

        // Snapshot August as combined 'all'
        $this->configService->captureSnapshot($this->shopA->id, '2026-08', 'manual_snapshot', false, $this->admin->id);

        // In September, change to 'cash' split
        $setting->update([
            'vendor_purchase_payment_type' => 'cash',
        ]);

        $augustConfig = $this->configService->getConfigurationForMonth($this->shopA->id, '2026-08');
        $augustSetting = $augustConfig['settings']->firstWhere('id', $setting->id);
        $this->assertSame('all', $augustSetting->vendor_purchase_payment_type);

        $septConfig = $this->configService->getConfigurationForMonth($this->shopA->id, '2026-09');
        $septSetting = $septConfig['settings']->firstWhere('id', $setting->id);
        $this->assertSame('cash', $septSetting->vendor_purchase_payment_type);
    }

    public function test_9_defined_vendor_mappings_can_be_historically_represented(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $sup1 = Supplier::create(['name' => 'Supplier One', 'type' => 'vegetables']);
        $sup2 = Supplier::create(['name' => 'Supplier Two', 'type' => 'vegetables']);
        $shopSup1 = ShopSupplier::create(['shop_id' => $this->shopA->id, 'supplier_id' => $sup1->id, 'is_active' => true]);
        $shopSup2 = ShopSupplier::create(['shop_id' => $this->shopA->id, 'supplier_id' => $sup2->id, 'is_active' => true]);

        $vpSetting = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)->firstOrFail();
        $vpSetting->update([
            'is_vendor_purchase' => true,
            'vendor_access_mode' => 'defined_only',
        ]);

        CategoryVendorMapping::create([
            'shop_ledger_entry_setting_id' => $vpSetting->id,
            'shop_supplier_id' => $shopSup1->id,
        ]);

        // Capture August snapshot with only Supplier 1
        $this->configService->captureSnapshot($this->shopA->id, '2026-08', 'manual_snapshot', false, $this->admin->id);

        // In September, add Supplier 2 mapping in live DB
        CategoryVendorMapping::create([
            'shop_ledger_entry_setting_id' => $vpSetting->id,
            'shop_supplier_id' => $shopSup2->id,
        ]);

        $augustConfig = $this->configService->getConfigurationForMonth($this->shopA->id, '2026-08');
        $augustSetting = $augustConfig['settings']->firstWhere('id', $vpSetting->id);
        $augustSupplierIds = $augustSetting->definedShopSuppliers->pluck('id')->all();
        $this->assertContains($shopSup1->id, $augustSupplierIds);
        $this->assertNotContains($shopSup2->id, $augustSupplierIds);

        $septConfig = $this->configService->getConfigurationForMonth($this->shopA->id, '2026-09');
        $septSetting = $septConfig['settings']->firstWhere('id', $vpSetting->id);
        $septSupplierIds = $septSetting->definedShopSuppliers->pluck('id')->all();
        $this->assertContains($shopSup1->id, $septSupplierIds);
        $this->assertContains($shopSup2->id, $septSupplierIds);
    }

    public function test_10_shop_a_history_is_isolated_from_shop_b(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shopA->id,
            'name' => 'Shop A Unique August Header',
            'type' => 'income',
            'cash_flow_mode' => 'entry_decides',
            'display_order' => 1,
            'enabled' => true,
        ]);

        ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shopB->id,
            'name' => 'Shop B Unique August Header',
            'type' => 'income',
            'cash_flow_mode' => 'entry_decides',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $this->configService->captureSnapshot($this->shopA->id, '2026-08', 'manual_snapshot', false, $this->admin->id);
        $this->configService->captureSnapshot($this->shopB->id, '2026-08', 'manual_snapshot', false, $this->admin->id);

        $augustA = $this->configService->getConfigurationForMonth($this->shopA->id, '2026-08');
        $augustB = $this->configService->getConfigurationForMonth($this->shopB->id, '2026-08');

        $this->assertTrue($augustA['headers']->contains('name', 'Shop A Unique August Header'));
        $this->assertFalse($augustA['headers']->contains('name', 'Shop B Unique August Header'));

        $this->assertTrue($augustB['headers']->contains('name', 'Shop B Unique August Header'));
        $this->assertFalse($augustB['headers']->contains('name', 'Shop A Unique August Header'));
    }

    public function test_11_historical_page_is_read_only(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $this->configService->captureSnapshot($this->shopA->id, '2026-08', 'manual_snapshot', false, $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.settings.shop', [
            'shop' => $this->shopA->slug ?: $this->shopA->shop_id,
            'month' => '2026-08',
        ]));

        $response->assertOk();
        $response->assertSee('Historical Configuration');
        $response->assertSee('READ ONLY');
        // Edit/add buttons should not be present in read only mode
        $response->assertDontSee('Create Income Header');
        $response->assertDontSee('Create Expense Header');
    }

    public function test_12_snapshot_creation_is_idempotent(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        // First capture
        $snap1 = $this->configService->captureSnapshot($this->shopA->id, '2026-08', 'manual_snapshot', false, $this->admin->id, 'First snapshot');
        $this->assertNotNull($snap1);

        // Second capture for same shop and month without force should return the existing snapshot
        $snap2 = $this->configService->captureSnapshot($this->shopA->id, '2026-08', 'manual_snapshot', false, $this->admin->id, 'Second snapshot');
        $this->assertSame($snap1->id, $snap2->id);

        $this->assertSame(1, ShopCashbookMonthConfigSnapshot::where('shop_id', $this->shopA->id)->where('month', '2026-08')->count());
    }

    public function test_13_current_settings_continue_working_normally(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $setting = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)->firstOrFail();

        // Update live setting via controller with valid schema
        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.shop-settings.update'), [
            'setting_id' => $setting->id,
            'display_name' => 'Custom Renamed Row',
            'enabled' => true,
            'default_funding_source' => 'sales',
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
            'include_in_payable' => false,
            'generates_secondary_entry' => false,
            'secondary_amount_mode' => 'same_amount',
        ]);

        $response->assertOk();
        $setting->refresh();
        $this->assertSame('Custom Renamed Row', $setting->display_name);
    }

    public function test_14_existing_cashbook_totals_and_accounting_do_not_change(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        // Create an existing transaction in August
        $setting = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)->firstOrFail();
        $tx = ShopLedgerTransaction::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $setting->entry_type_id,
            'business_date' => '2026-08-15',
            'funding_source' => 'shop_cash',
            'amount' => 1500.00,
            'direction' => 'outflow',
            'source' => 'manual',
        ]);

        // Capture snapshot
        $this->configService->captureSnapshot($this->shopA->id, '2026-08', 'manual_snapshot', false, $this->admin->id);

        // Mutate live September setting
        $setting->update(['display_name' => 'Brand New Name']);

        // Historical transaction values, amount, and integrity must remain untouched
        $tx->refresh();
        $this->assertSame('1500.00', number_format((float) $tx->amount, 2, '.', ''));
        $this->assertSame('2026-08-15', $tx->business_date->format('Y-m-d'));
        $this->assertSame($this->shopA->id, $tx->shop_id);
    }

    public function test_15_legacy_month_reconstructs_safely_when_no_snapshot_exists(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        // Request a past month (July 2026) with NO prior snapshot
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.settings.shop', [
            'shop' => $this->shopA->slug ?: $this->shopA->shop_id,
            'month' => '2026-07',
        ]));

        $response->assertOk();
        $response->assertViewHas('isReadOnly', true);
        $response->assertViewHas('isLegacy', true);
        $response->assertSee('Legacy / Reconstructed');
        $response->assertSee('READ ONLY');
    }
}
