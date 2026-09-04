<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OptionalNoteSettingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@greenleaf.com',
        ]);
        $this->admin->assignRole('admin');

        $this->shop = Shop::factory()->create([
            'name' => 'Casio Market',
            'code' => 'CASIO-NOTE-01',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();
    }

    public function test_note_enabled_defaults_to_false_for_all_categories(): void
    {
        $settings = ShopLedgerEntrySetting::where('shop_id', $this->shop->id)->get();

        $this->assertGreaterThan(0, $settings->count());

        foreach ($settings as $setting) {
            $this->assertFalse(
                $setting->note_enabled,
                "Category {$setting->entryType?->code} should default note_enabled to false."
            );
        }
    }

    public function test_admin_can_toggle_note_enabled_on_and_off(): void
    {
        $setting = ShopLedgerEntrySetting::where('shop_id', $this->shop->id)->firstOrFail();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.update'), [
                'setting_id' => $setting->id,
                'enabled' => 1,
                'note_enabled' => 1,
                'default_funding_source' => in_array($setting->default_funding_source, ['none', 'sales', 'petty', 'company', 'company_later', 'bank'], true) ? $setting->default_funding_source : 'sales',
                'include_in_sales' => $setting->include_in_sales,
                'include_in_income' => $setting->include_in_income,
                'include_in_expense' => $setting->include_in_expense,
                'include_in_pl' => $setting->include_in_pl,
                'include_in_payable' => $setting->include_in_payable,
                'generates_secondary_entry' => 0,
                'secondary_amount_mode' => 'same_amount',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $setting->refresh();
        $this->assertTrue($setting->note_enabled);
        $this->assertTrue($setting->isNoteEnabled());

        // Toggle back OFF
        $responseOff = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.update'), [
                'setting_id' => $setting->id,
                'enabled' => 1,
                'note_enabled' => 0,
                'default_funding_source' => in_array($setting->default_funding_source, ['none', 'sales', 'petty', 'company', 'company_later', 'bank'], true) ? $setting->default_funding_source : 'sales',
                'include_in_sales' => $setting->include_in_sales,
                'include_in_income' => $setting->include_in_income,
                'include_in_expense' => $setting->include_in_expense,
                'include_in_pl' => $setting->include_in_pl,
                'include_in_payable' => $setting->include_in_payable,
                'generates_secondary_entry' => 0,
                'secondary_amount_mode' => 'same_amount',
            ]);

        $responseOff->assertOk();
        $setting->refresh();
        $this->assertFalse($setting->note_enabled);
    }

    public function test_enabling_note_on_one_shop_does_not_affect_other_shops(): void
    {
        $otherShop = Shop::factory()->create([
            'name' => 'Kozhikode Market',
            'code' => 'KOZHIKODE-01',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $shop1Setting = ShopLedgerEntrySetting::where('shop_id', $this->shop->id)->firstOrFail();
        $shop2Setting = ShopLedgerEntrySetting::where('shop_id', $otherShop->id)
            ->where('entry_type_id', $shop1Setting->entry_type_id)
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.update'), [
                'setting_id' => $shop1Setting->id,
                'enabled' => 1,
                'note_enabled' => 1,
                'default_funding_source' => in_array($shop1Setting->default_funding_source, ['none', 'sales', 'petty', 'company', 'company_later', 'bank'], true) ? $shop1Setting->default_funding_source : 'sales',
                'include_in_sales' => $shop1Setting->include_in_sales,
                'include_in_income' => $shop1Setting->include_in_income,
                'include_in_expense' => $shop1Setting->include_in_expense,
                'include_in_pl' => $shop1Setting->include_in_pl,
                'include_in_payable' => $shop1Setting->include_in_payable,
                'generates_secondary_entry' => 0,
                'secondary_amount_mode' => 'same_amount',
            ]);

        $shop1Setting->refresh();
        $shop2Setting->refresh();

        $this->assertTrue($shop1Setting->note_enabled);
        $this->assertFalse($shop2Setting->note_enabled);
    }

    public function test_other_income_and_other_expense_require_note_regardless_of_note_enabled_flag(): void
    {
        $otherIncomeType = LedgerEntryType::where('code', 'other_income')->firstOrFail();
        $otherExpenseType = LedgerEntryType::where('code', 'other_expense')->firstOrFail();

        $incomeSetting = ShopLedgerEntrySetting::where('shop_id', $this->shop->id)
            ->where('entry_type_id', $otherIncomeType->id)
            ->firstOrFail();

        $expenseSetting = ShopLedgerEntrySetting::where('shop_id', $this->shop->id)
            ->where('entry_type_id', $otherExpenseType->id)
            ->firstOrFail();

        $this->assertTrue($incomeSetting->requiresNote());
        $this->assertTrue($expenseSetting->requiresNote());
    }

    public function test_demo_page_loads_with_note_setting_metadata(): void
    {
        $demoResponse = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.settings.shop.demo', ['shop' => $this->shop->id]));

        $demoResponse->assertOk();
        $demoResponse->assertSee('Note');

        $settingsResponse = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.settings.shop', ['shop' => $this->shop->id]));

        $settingsResponse->assertOk();
        $settingsResponse->assertSee('Note Field');
    }
}
