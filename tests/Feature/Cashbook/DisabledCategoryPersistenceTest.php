<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\DailyLedgerService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisabledCategoryPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopOwner;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->shopOwner = User::factory()->create();
        $this->shopOwner->assignRole('shop');

        $this->shop = Shop::query()->create([
            'name' => 'Ashirwad Veg Shop',
            'code' => 'AV_ASHIRWAD',
            'warehouse_tag' => 'AV',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $this->shopOwner->ownedShopAssignments()->create(['shop_id' => $this->shop->id]);
        app(CashbookShopSyncService::class)->syncAndGetProfiles();
    }

    public function test_disabled_category_stays_disabled_when_recalculating_historical_date_with_transactions(): void
    {
        $cashPurchaseType = LedgerEntryType::where('code', 'cash_purchase')->firstOrFail();

        // 1. Create a historical transaction in August 2026
        $historicalTx = ShopLedgerTransaction::query()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-15',
            'entry_type_id' => $cashPurchaseType->id,
            'amount' => 500.0,
            'direction' => 'expense',
            'funding_source' => 'petty',
            'affects_sales' => false,
            'affects_income' => false,
            'affects_expense' => true,
            'affects_pl' => true,
            'pl_delta' => -500.0,
            'settlement_delta' => 0.0,
            'settlement_direction' => 'none',
            'petty_delta' => -500.0,
            'petty_direction' => 'decrease',
            'company_pending_delta' => 0.0,
            'company_pending_direction' => 'none',
            'status' => 'posted',
        ]);

        // 2. Disable cash_purchase setting for the shop
        $setting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $this->shop->id)
            ->where('entry_type_id', $cashPurchaseType->id)
            ->firstOrFail();

        $setting->update(['enabled' => false]);
        $this->assertFalse((bool) $setting->fresh()->enabled);

        // 3. Recalculate historical date / run daily summary
        $dailyLedgerService = app(DailyLedgerService::class);
        $snapshot = $dailyLedgerService->dailySummary((int) $this->shop->id, '2026-08-15');

        // 4. Verify setting remains disabled in database
        $this->assertFalse((bool) $setting->fresh()->enabled);

        // 5. Verify historical calculation still includes the transaction
        $this->assertEquals(500.0, (float) $snapshot->total_expense);
        $this->assertEquals(-500.0, (float) $snapshot->net_pl);
    }

    public function test_salary_setting_preserves_disabled_state_across_profile_sync(): void
    {
        $salaryType = LedgerEntryType::where('code', 'salary')->firstOrFail();

        $salarySetting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $this->shop->id)
            ->where('entry_type_id', $salaryType->id)
            ->firstOrFail();

        // Disable Salary
        $salarySetting->update(['enabled' => false]);
        $this->assertFalse((bool) $salarySetting->fresh()->enabled);

        // Run Cashbook profile sync
        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        // Verify Salary is STILL disabled
        $this->assertFalse((bool) $salarySetting->fresh()->enabled);
    }

    public function test_explicit_admin_toggle_enables_and_disables_normally(): void
    {
        $cashPurchaseType = LedgerEntryType::where('code', 'cash_purchase')->firstOrFail();
        $setting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $this->shop->id)
            ->where('entry_type_id', $cashPurchaseType->id)
            ->firstOrFail();

        // Turn OFF
        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.shop-settings.toggle-status'), [
            'setting_id' => $setting->id,
            'enabled' => false,
        ]);
        $response->assertOk();
        $this->assertFalse((bool) $setting->fresh()->enabled);

        // Turn ON
        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.shop-settings.toggle-status'), [
            'setting_id' => $setting->id,
            'enabled' => true,
        ]);
        $response->assertOk();
        $this->assertTrue((bool) $setting->fresh()->enabled);
    }
}
