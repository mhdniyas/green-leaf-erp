<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShopSettingsCategoryHeaderPlacementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->shop = Shop::query()->create([
            'name' => 'Downtown Shop',
            'code' => 'DWT_01',
            'warehouse_tag' => 'DWT',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();
    }

    public function test_expense_category_assigned_to_income_header_is_visible_on_settings_page(): void
    {
        // 1. Create Expense category.
        $expenseEntryType = LedgerEntryType::query()->create([
            'name' => 'Bank Charge Custom',
            'code' => 'bank_charge_custom',
            'category' => 'expense',
            'display_order' => 50,
            'active' => true,
        ]);

        $incomeHeader = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Daily Sales Header',
            'type' => 'income',
            'display_order' => 1,
            'enabled' => true,
        ]);

        // 2. Assign it to an Income header.
        $setting = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $expenseEntryType->id,
            'header_group_id' => $incomeHeader->id,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'include_in_expense' => true,
            'header_display_order' => 1,
        ]);

        // 3. Open settings page.
        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.settings.shop', ['shop' => $this->shop->id])
        );

        // 4. Category name is visible.
        $response->assertOk();
        $response->assertSee('Bank Charge Custom');
        $response->assertSee('Daily Sales Header');

        // 5. Existing header_group_id remains unchanged.
        $this->assertSame($incomeHeader->id, $setting->fresh()->header_group_id);

        // 6. No financial data is modified.
        $this->assertSame('expense', $expenseEntryType->fresh()->category);
    }
}
