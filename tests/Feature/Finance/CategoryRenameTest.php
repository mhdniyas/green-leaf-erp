<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CategoryRenameTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop1;

    private Shop $shop2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        Permission::findOrCreate('sales.order.create');

        $this->admin = User::factory()->create([
            'email' => 'admin@greenleaf.com',
        ]);
        $this->admin->assignRole('admin');

        $this->shop1 = Shop::factory()->create([
            'name' => 'Casio Market',
            'code' => 'CASIO-RENAME-01',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $this->shop2 = Shop::factory()->create([
            'name' => 'Green Leaf Downtown',
            'code' => 'GL-DOWNTOWN-02',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();
    }

    public function test_admin_can_rename_a_category_for_one_shop(): void
    {
        $setting1 = ShopLedgerEntrySetting::where('shop_id', $this->shop1->id)
            ->whereHas('entryType', fn ($q) => $q->where('code', 'cash_sales')->orWhere('code', 'CASH_SALES'))
            ->firstOrFail();

        $originalEntryTypeId = $setting1->entry_type_id;
        $canonicalName = $setting1->entryType->name;

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.update'), [
                'setting_id' => $setting1->id,
                'display_name' => 'Cash Remaining',
                'enabled' => 1,
                'note_enabled' => 0,
                'default_funding_source' => 'sales',
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
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $setting1->refresh();
        $this->assertSame('Cash Remaining', $setting1->display_name);
        $this->assertSame('Cash Remaining', $setting1->displayName());
        $this->assertSame($originalEntryTypeId, $setting1->entry_type_id);

        // Global LedgerEntryType must NOT be renamed
        $entryType = LedgerEntryType::findOrFail($originalEntryTypeId);
        $this->assertSame($canonicalName, $entryType->name);
    }

    public function test_renaming_category_in_one_shop_does_not_affect_another_shop(): void
    {
        $setting1 = ShopLedgerEntrySetting::where('shop_id', $this->shop1->id)
            ->whereHas('entryType', fn ($q) => $q->where('code', 'cash_sales')->orWhere('code', 'CASH_SALES'))
            ->firstOrFail();

        $setting2 = ShopLedgerEntrySetting::where('shop_id', $this->shop2->id)
            ->where('entry_type_id', $setting1->entry_type_id)
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.update'), [
                'setting_id' => $setting1->id,
                'display_name' => 'Cash',
                'enabled' => 1,
                'note_enabled' => 0,
                'default_funding_source' => 'sales',
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
            ])
            ->assertOk();

        $setting1->refresh();
        $setting2->refresh();

        $this->assertSame('Cash', $setting1->displayName());
        $this->assertSame($setting2->entryType->name, $setting2->displayName());
        $this->assertNull($setting2->display_name);
    }

    public function test_renamed_category_appears_in_admin_settings_view(): void
    {
        $setting = ShopLedgerEntrySetting::where('shop_id', $this->shop1->id)
            ->whereHas('entryType', fn ($q) => $q->where('code', 'cash_sales')->orWhere('code', 'CASH_SALES'))
            ->firstOrFail();

        $setting->update(['display_name' => 'Cash in Drawer']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.settings.shop', ['shop' => $this->shop1->id]));

        $response->assertOk();
        $response->assertSee('Cash in Drawer');
    }

    public function test_renamed_category_appears_in_demo_view(): void
    {
        $setting = ShopLedgerEntrySetting::where('shop_id', $this->shop1->id)
            ->whereHas('entryType', fn ($q) => $q->where('code', 'cash_sales')->orWhere('code', 'CASH_SALES'))
            ->firstOrFail();

        $setting->update(['display_name' => 'Physical Cash']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cashbook.settings.shop.demo', ['shop' => $this->shop1->id]));

        $response->assertOk();
        $response->assertSee('Physical Cash');
    }

    public function test_renamed_category_appears_in_shop_owner_cashbook_view(): void
    {
        $owner = User::factory()->create([
            'email' => 'owner@greenleaf.com',
            'shop_id' => $this->shop1->id,
        ]);
        $owner->assignRole('shop');
        $owner->givePermissionTo('sales.order.create');

        $setting = ShopLedgerEntrySetting::where('shop_id', $this->shop1->id)
            ->whereHas('entryType', fn ($q) => $q->where('code', 'cash_sales')->orWhere('code', 'CASH_SALES'))
            ->firstOrFail();

        $setting->update(['display_name' => 'Cash Box']);

        $response = $this->actingAs($owner)
            ->get(route('shop-owner.cashbook.show'));

        $response->assertOk();
        $response->assertSee('Cash Box');
    }

    public function test_reset_to_default_restores_canonical_name(): void
    {
        $setting = ShopLedgerEntrySetting::where('shop_id', $this->shop1->id)
            ->whereHas('entryType', fn ($q) => $q->where('code', 'cash_sales')->orWhere('code', 'CASH_SALES'))
            ->firstOrFail();

        $canonicalName = $setting->entryType->name;

        // First set custom name
        $setting->update(['display_name' => 'Custom Cash']);
        $this->assertSame('Custom Cash', $setting->displayName());

        // Now save with empty display_name to reset
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.update'), [
                'setting_id' => $setting->id,
                'display_name' => '',
                'enabled' => 1,
                'note_enabled' => 0,
                'default_funding_source' => 'sales',
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
            ]);

        $response->assertOk();

        $setting->refresh();
        $this->assertNull($setting->display_name);
        $this->assertSame($canonicalName, $setting->displayName());
    }

    public function test_whitespace_is_trimmed_and_blank_strings_are_nulled(): void
    {
        $setting = ShopLedgerEntrySetting::where('shop_id', $this->shop1->id)->firstOrFail();

        // Whitespace trimmed
        $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.update'), [
                'setting_id' => $setting->id,
                'display_name' => '   Trimmed Label   ',
                'enabled' => 1,
                'default_funding_source' => in_array($setting->default_funding_source, ['none', 'sales', 'petty', 'company', 'company_later', 'bank'], true) ? $setting->default_funding_source : 'sales',
                'include_in_sales' => $setting->include_in_sales,
                'include_in_income' => $setting->include_in_income,
                'include_in_expense' => $setting->include_in_expense,
                'include_in_pl' => $setting->include_in_pl,
                'include_in_payable' => $setting->include_in_payable,
                'generates_secondary_entry' => false,
                'secondary_amount_mode' => 'same_amount',
            ])
            ->assertOk();

        $setting->refresh();
        $this->assertSame('Trimmed Label', $setting->display_name);

        // Only spaces becomes null
        $this->actingAs($this->admin)
            ->postJson(route('admin.cashbook.api.shop-settings.update'), [
                'setting_id' => $setting->id,
                'display_name' => '     ',
                'enabled' => 1,
                'default_funding_source' => in_array($setting->default_funding_source, ['none', 'sales', 'petty', 'company', 'company_later', 'bank'], true) ? $setting->default_funding_source : 'sales',
                'include_in_sales' => $setting->include_in_sales,
                'include_in_income' => $setting->include_in_income,
                'include_in_expense' => $setting->include_in_expense,
                'include_in_pl' => $setting->include_in_pl,
                'include_in_payable' => $setting->include_in_payable,
                'generates_secondary_entry' => false,
                'secondary_amount_mode' => 'same_amount',
            ])
            ->assertOk();

        $setting->refresh();
        $this->assertNull($setting->display_name);
    }
}
