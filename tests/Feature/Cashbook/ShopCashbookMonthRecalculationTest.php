<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookMonthConfigSnapshot;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\ShopCashbookMonthConfigService;
use App\Services\Cashbook\ShopCashbookMonthRecalculationService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShopCashbookMonthRecalculationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shopA;

    private Shop $shopB;

    private ShopCashbookMonthConfigService $configService;

    private ShopCashbookMonthRecalculationService $recalcService;

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
        $this->recalcService = app(ShopCashbookMonthRecalculationService::class);
    }

    public function test_1_current_month_uses_live_category_settings(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $rentType = LedgerEntryType::where('code', 'rent_expense')->firstOrFail();
        ShopLedgerTransaction::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $rentType->id,
            'business_date' => '2026-09-10',
            'funding_source' => 'shop_cash',
            'amount' => 12000.00,
            'direction' => 'outflow',
            'status' => 'approved',
            'source' => 'manual',
        ]);

        $result = $this->recalcService->recalculateMonth($this->shopA->id, '2026-09', $this->admin->id);

        $this->assertTrue($result['success']);
        $this->assertSame(12000.0, (float) $result['summary']['expense_total']);
        $this->assertNotNull($result['recalculated_at']);
    }

    public function test_2_moving_category_this_month_and_refreshing_updates_monthly_totals_and_header_assignments(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $header1 = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shopA->id,
            'name' => 'Header Alpha',
            'type' => 'expense',
            'cash_flow_mode' => 'entry_decides',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $header2 = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shopA->id,
            'name' => 'Header Beta',
            'type' => 'expense',
            'cash_flow_mode' => 'entry_decides',
            'display_order' => 2,
            'enabled' => true,
        ]);

        $rentSetting = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)
            ->whereHas('entryType', fn ($q) => $q->where('code', 'rent_expense'))
            ->firstOrFail();

        $rentSetting->update(['header_group_id' => $header1->id]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $rentSetting->entry_type_id,
            'business_date' => '2026-09-12',
            'funding_source' => 'shop_cash',
            'amount' => 5000.00,
            'direction' => 'outflow',
            'status' => 'approved',
            'source' => 'manual',
        ]);

        // First recalculation under Header Alpha
        $res1 = $this->recalcService->recalculateMonth($this->shopA->id, '2026-09');
        $this->assertSame(5000.0, (float) $res1['summary']['headers'][$header1->id]['total']);

        // Move Rent to Header Beta in live settings
        $rentSetting->update(['header_group_id' => $header2->id]);

        // Recalculate again
        $res2 = $this->recalcService->recalculateMonth($this->shopA->id, '2026-09');
        $this->assertSame(0.0, (float) ($res2['summary']['headers'][$header1->id]['total'] ?? 0.0));
        $this->assertSame(5000.0, (float) $res2['summary']['headers'][$header2->id]['total']);
    }

    public function test_3_header_totals_recalculate(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $header = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shopA->id,
            'name' => 'Operations Header',
            'type' => 'expense',
            'cash_flow_mode' => 'entry_decides',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $rentSetting = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)
            ->whereHas('entryType', fn ($q) => $q->where('code', 'rent_expense'))
            ->firstOrFail();
        $salarySetting = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)
            ->whereHas('entryType', fn ($q) => $q->where('code', 'salary'))
            ->firstOrFail();

        $rentSetting->update(['header_group_id' => $header->id]);
        $salarySetting->update(['header_group_id' => $header->id]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $rentSetting->entry_type_id,
            'business_date' => '2026-09-05',
            'funding_source' => 'shop_cash',
            'amount' => 4000.00,
            'direction' => 'outflow',
            'source' => 'manual',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $salarySetting->entry_type_id,
            'business_date' => '2026-09-08',
            'funding_source' => 'shop_cash',
            'amount' => 3000.00,
            'direction' => 'outflow',
            'source' => 'manual',
        ]);

        $result = $this->recalcService->recalculateMonth($this->shopA->id, '2026-09');
        $this->assertSame(7000.0, (float) $result['summary']['headers'][$header->id]['total']);
        $this->assertCount(2, $result['summary']['headers'][$header->id]['categories']);
    }

    public function test_4_relation_calculations_recalculate(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $cashType = LedgerEntryType::where('code', 'cash_sales')->firstOrFail();
        $rentType = LedgerEntryType::where('code', 'rent_expense')->firstOrFail();

        $cashSetting = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)->where('entry_type_id', $cashType->id)->firstOrFail();
        $rentSetting = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)->where('entry_type_id', $rentType->id)->firstOrFail();

        $relation = ShopCashbookRelation::create([
            'shop_id' => $this->shopA->id,
            'name' => 'Custom Net Calculation',
            'type' => 'settlement',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $relation->items()->create([
            'shop_ledger_entry_setting_id' => $cashSetting->id,
            'role' => 'add',
            'display_order' => 1,
        ]);

        $relation->items()->create([
            'shop_ledger_entry_setting_id' => $rentSetting->id,
            'role' => 'subtract',
            'display_order' => 2,
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $cashType->id,
            'business_date' => '2026-09-10',
            'funding_source' => 'shop_cash',
            'amount' => 20000.00,
            'direction' => 'income',
            'source' => 'manual',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $rentType->id,
            'business_date' => '2026-09-12',
            'funding_source' => 'shop_cash',
            'amount' => 6000.00,
            'direction' => 'outflow',
            'source' => 'manual',
        ]);

        $result = $this->recalcService->recalculateMonth($this->shopA->id, '2026-09');

        $this->assertArrayHasKey($relation->id, $result['summary']['relations']);
        $relData = $result['summary']['relations'][$relation->id];
        $this->assertSame(20000.0, (float) $relData['gross_additions']);
        $this->assertSame(6000.0, (float) $relData['gross_deductions']);
        $this->assertSame(14000.0, (float) $relData['net_settlement']);
    }

    public function test_5_settlement_calculations_recalculate(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $cashType = LedgerEntryType::where('code', 'cash_sales')->firstOrFail();
        $cashSetting = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)->where('entry_type_id', $cashType->id)->firstOrFail();

        $settlementRel = ShopCashbookRelation::create([
            'shop_id' => $this->shopA->id,
            'name' => 'Payable to Company',
            'type' => 'settlement',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $settlementRel->items()->create([
            'shop_ledger_entry_setting_id' => $cashSetting->id,
            'role' => 'add',
            'display_order' => 1,
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $cashType->id,
            'business_date' => '2026-09-15',
            'funding_source' => 'shop_cash',
            'amount' => 50000.00,
            'direction' => 'income',
            'source' => 'manual',
        ]);

        $result = $this->recalcService->recalculateMonth($this->shopA->id, '2026-09');
        $this->assertSame(50000.0, (float) $result['summary']['relations'][$settlementRel->id]['gross_additions']);
        $this->assertSame(50000.0, (float) $result['summary']['relations'][$settlementRel->id]['net_settlement']);
    }

    public function test_6_vendor_cash_routes_correctly_and_affects_cash_totals(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $vpType = LedgerEntryType::firstOrCreate(['code' => 'vendor_purchase_cash'], ['name' => 'Vendor Purchase - Cash', 'category' => 'expense', 'account_type' => 'expense']);
        $vpSetting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $vpType->id,
            'effective_from' => '2026-01-01',
            'is_vendor_purchase' => true,
            'vendor_purchase_payment_type' => 'cash',
            'enabled' => true,
            'include_in_expense' => true,
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $vpType->id,
            'business_date' => '2026-09-14',
            'funding_source' => 'shop_cash',
            'amount' => 15000.00,
            'direction' => 'outflow',
            'source' => 'manual',
        ]);

        $result = $this->recalcService->recalculateMonth($this->shopA->id, '2026-09');
        $this->assertSame(15000.0, (float) $result['summary']['vendor_purchase_cash']);
        $this->assertSame(0.0, (float) $result['summary']['vendor_purchase_credit']);
    }

    public function test_7_vendor_credit_routes_correctly_without_reducing_cash(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $vpCreditType = LedgerEntryType::firstOrCreate(['code' => 'vendor_purchase_credit'], ['name' => 'Vendor Purchase - Credit', 'category' => 'expense', 'account_type' => 'expense']);
        $vpCreditSetting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $vpCreditType->id,
            'effective_from' => '2026-01-01',
            'is_vendor_purchase' => true,
            'vendor_purchase_payment_type' => 'credit',
            'enabled' => true,
            'include_in_expense' => true,
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $vpCreditType->id,
            'business_date' => '2026-09-14',
            'funding_source' => 'company_later',
            'amount' => 25000.00,
            'direction' => 'outflow',
            'petty_delta' => 0.0,
            'source' => 'manual',
        ]);

        $result = $this->recalcService->recalculateMonth($this->shopA->id, '2026-09');
        $this->assertSame(25000.0, (float) $result['summary']['vendor_purchase_credit']);
        $this->assertSame(0.0, (float) $result['summary']['vendor_purchase_cash']);
        $this->assertSame(0.0, (float) $result['summary']['petty']['used']);
    }

    public function test_8_categories_with_zero_transactions_remain_visible(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $result = $this->recalcService->recalculateMonth($this->shopA->id, '2026-09');
        $this->assertGreaterThan(0, $result['categories_processed']);
    }

    public function test_9_historical_month_uses_historical_config_snapshot(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $headerAugust = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shopA->id,
            'name' => 'August Historical Header',
            'type' => 'expense',
            'cash_flow_mode' => 'entry_decides',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $rentSetting = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)
            ->whereHas('entryType', fn ($q) => $q->where('code', 'rent_expense'))
            ->firstOrFail();
        $rentSetting->update(['header_group_id' => $headerAugust->id]);

        // Capture August snapshot
        $this->configService->captureSnapshot($this->shopA->id, '2026-08', 'manual_snapshot', false, $this->admin->id);

        // In September, delete headerAugust in live settings
        $headerAugust->delete();

        // Create transaction in August
        ShopLedgerTransaction::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $rentSetting->entry_type_id,
            'business_date' => '2026-08-10',
            'funding_source' => 'shop_cash',
            'amount' => 8000.00,
            'direction' => 'outflow',
            'source' => 'manual',
        ]);

        // Recalculate August
        $augustResult = $this->recalcService->recalculateMonth($this->shopA->id, '2026-08');

        // August result must use August Historical Header
        $this->assertTrue(collect($augustResult['summary']['headers'])->contains('name', 'August Historical Header'));
    }

    public function test_10_source_transactions_are_completely_untouched(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $rentType = LedgerEntryType::where('code', 'rent_expense')->firstOrFail();
        $tx = ShopLedgerTransaction::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $rentType->id,
            'business_date' => '2026-09-10',
            'funding_source' => 'shop_cash',
            'amount' => 4500.00,
            'direction' => 'outflow',
            'notes' => 'Important source note',
            'source' => 'manual',
        ]);

        $this->recalcService->recalculateMonth($this->shopA->id, '2026-09');

        $tx->refresh();
        $this->assertSame('4500.00', number_format((float) $tx->amount, 2, '.', ''));
        $this->assertSame('2026-09-10', $tx->business_date->format('Y-m-d'));
        $this->assertSame('Important source note', $tx->notes);
    }

    public function test_11_business_date_is_used_instead_of_created_at(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $rentType = LedgerEntryType::where('code', 'rent_expense')->firstOrFail();

        // Transaction created in September but business_date is in August
        $tx = ShopLedgerTransaction::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $rentType->id,
            'business_date' => '2026-08-25',
            'funding_source' => 'shop_cash',
            'amount' => 9999.00,
            'direction' => 'outflow',
            'source' => 'manual',
        ]);

        // Recalculating September must NOT include this transaction
        $septResult = $this->recalcService->recalculateMonth($this->shopA->id, '2026-09');
        $this->assertSame(0.0, (float) $septResult['summary']['expense_total']);

        // Recalculating August MUST include this transaction
        $augResult = $this->recalcService->recalculateMonth($this->shopA->id, '2026-08');
        $this->assertSame(9999.0, (float) $augResult['summary']['expense_total']);
    }

    public function test_12_refresh_is_idempotent_and_running_twice_gives_same_result(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $res1 = $this->recalcService->recalculateMonth($this->shopA->id, '2026-09');
        $res2 = $this->recalcService->recalculateMonth($this->shopA->id, '2026-09');

        $this->assertSame($res1['summary']['income_total'], $res2['summary']['income_total']);
        $this->assertSame($res1['summary']['expense_total'], $res2['summary']['expense_total']);
        $this->assertSame($res1['summary']['sales_total'], $res2['summary']['sales_total']);
    }

    public function test_13_controller_endpoint_triggers_recalculation_and_redirects(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $profile = ShopLedgerProfile::where('shop_id', $this->shopA->id)->first();
        $shopSlug = $profile ? ($profile->slug ?: $profile->shop_id) : $this->shopA->id;

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.shop.recalculate-month', [
            'shop' => $shopSlug,
        ]), [
            'month' => '2026-09',
        ]);

        $response->assertRedirect(route('admin.cashbook.shop.show', [
            'shop' => $shopSlug,
            'month' => '2026-09',
            'period_mode' => 'month',
        ]));
        $response->assertSessionHas('success');

        // Check snapshot has recalculated_at set
        $snapshot = ShopCashbookMonthConfigSnapshot::where('shop_id', $this->shopA->id)->where('month', '2026-09')->first();
        $this->assertNotNull($snapshot);
        $this->assertNotNull($snapshot->recalculated_at);
    }

    public function test_14_shop_a_recalculation_cannot_affect_shop_b(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');

        $rentType = LedgerEntryType::where('code', 'rent_expense')->firstOrFail();

        ShopLedgerTransaction::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $rentType->id,
            'business_date' => '2026-09-10',
            'funding_source' => 'shop_cash',
            'amount' => 1111.00,
            'direction' => 'outflow',
            'source' => 'manual',
        ]);

        ShopLedgerTransaction::create([
            'shop_id' => $this->shopB->id,
            'entry_type_id' => $rentType->id,
            'business_date' => '2026-09-10',
            'funding_source' => 'shop_cash',
            'amount' => 2222.00,
            'direction' => 'outflow',
            'source' => 'manual',
        ]);

        $resA = $this->recalcService->recalculateMonth($this->shopA->id, '2026-09');
        $resB = $this->recalcService->recalculateMonth($this->shopB->id, '2026-09');

        $this->assertSame(1111.0, (float) $resA['summary']['expense_total']);
        $this->assertSame(2222.0, (float) $resB['summary']['expense_total']);
    }
}
