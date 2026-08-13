<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopConfigPreset;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashbookPresetManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config()->set('admin.user_access.main_admin_email', 'admin@greenleaf.com');
        Role::findOrCreate('admin', 'web');

        $this->admin = User::factory()->create(['email' => 'admin@greenleaf.com', 'registration_status' => 'approved']);
        $this->admin->assignRole('admin');

        // Create sample entry type
        LedgerEntryType::create([
            'code'          => 'DAILY_SALES',
            'name'          => 'Daily Sales',
            'category'      => 'income',
            'system_type'   => 'user',
            'active'        => true,
            'display_order' => 1,
        ]);
    }

    public function test_main_admin_can_access_presets_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.settings.presets'));
        $response->assertOk();
        $response->assertSee('Preset & Shop Configuration Engine', false);
    }

    public function test_can_create_custom_preset(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/admin/cashbook/api/presets/create', [
            'name'        => 'Custom Outlet Preset',
            'description' => 'Special preset for express shops',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('cashbook_config_presets', ['name' => 'Custom Outlet Preset']);
    }

    public function test_can_update_preset_setting_rules(): void
    {
        $preset = ShopConfigPreset::create(['name' => 'Test Preset', 'slug' => 'test-preset']);
        $entryType = LedgerEntryType::first();

        $setting = $preset->entrySettings()->create([
            'entry_type_id'        => $entryType->id,
            'version'              => 1,
            'effective_from'       => '2026-01-01',
            'enabled'              => true,
            'include_in_sales'     => true,
            'settlement_behavior' => 'increase',
        ]);

        $response = $this->actingAs($this->admin)->postJson('/admin/cashbook/api/presets/update-setting', [
            'setting_id'          => $setting->id,
            'include_in_sales'    => false,
            'settlement_behavior' => 'none',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('cashbook_preset_entry_settings', [
            'id'                  => $setting->id,
            'include_in_sales'    => false,
            'settlement_behavior' => 'none',
        ]);
    }

    public function test_can_assign_preset_to_shop(): void
    {
        $shop = Shop::factory()->create([
            'name'               => 'Test Shop',
            'accounting_enabled' => true,
            'accounting_mode'    => 'owned',
        ]);

        $profile = ShopLedgerProfile::create([
            'shop_id' => $shop->id,
            'name'    => $shop->name,
            'code'    => $shop->code,
        ]);

        $preset = ShopConfigPreset::create(['name' => 'Assigned Preset', 'slug' => 'assigned-preset']);
        $preset->entrySettings()->create([
            'entry_type_id' => LedgerEntryType::firstOrFail()->id,
            'version' => 1,
            'effective_from' => '2026-08-13',
            'enabled' => true,
            'default_funding_source' => 'none',
            'allowed_funding_sources' => ['none'],
            'include_in_sales' => true,
            'include_in_income' => true,
            'include_in_expense' => false,
            'include_in_pl' => true,
            'settlement_behavior' => 'increase',
            'petty_behavior' => 'none',
            'company_pending_behavior' => 'none',
            'generates_secondary_entry' => false,
            'secondary_amount_mode' => 'same_amount',
            'display_order' => 1,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/admin/cashbook/api/assign-preset', [
            'shop_id'   => $shop->id,
            'preset_id' => $preset->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('shop_ledger_profiles', [
            'shop_id'   => $shop->id,
            'preset_id' => $preset->id,
        ]);

        $this->assertDatabaseHas('shop_ledger_entry_settings', [
            'shop_id' => $shop->id,
            'entry_type_id' => LedgerEntryType::firstOrFail()->id,
            'effective_from' => '2026-01-01 00:00:00',
        ]);

        $setting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $shop->id)
            ->where('entry_type_id', LedgerEntryType::firstOrFail()->id)
            ->first();

        $this->assertNotNull($setting);
        $this->assertTrue($setting->enabled);
    }
}
