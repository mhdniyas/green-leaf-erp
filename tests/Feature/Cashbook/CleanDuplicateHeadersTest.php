<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Services\Cashbook\ShopCashbookUiLayoutService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanDuplicateHeadersTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Shop $shop;

    private ShopCashbookUiLayoutService $layoutService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->adminUser = User::factory()->create([
            'email' => 'admin_test@greenleaf.com',
        ]);
        $this->adminUser->assignRole('admin');

        $this->shop = Shop::factory()->create([
            'name' => 'Midland Fresh Market',
            'code' => 'av-gm-midland-gm-midland',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();
        $this->layoutService = app(ShopCashbookUiLayoutService::class);
    }

    public function test_layout_service_does_not_duplicate_other_expenses_when_unassigned_expenses_exist(): void
    {
        // 1. Create a DB Header Group named "Other Expenses"
        $dbHeader = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Other Expenses',
            'type' => 'expense',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $expType1 = LedgerEntryType::firstOrCreate(
            ['code' => 'tea_snacks_test'],
            ['name' => 'Tea & Snacks', 'category' => 'expense', 'active' => true, 'display_order' => 1]
        );
        $expType2 = LedgerEntryType::firstOrCreate(
            ['code' => 'cleaning_test'],
            ['name' => 'Cleaning', 'category' => 'expense', 'active' => true, 'display_order' => 2]
        );

        // Setting 1 is assigned to header
        ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $expType1->id,
            'header_group_id' => $dbHeader->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'include_in_expense' => true,
            'display_order' => 1,
        ]);

        // Setting 2 is UNASSIGNED (header_group_id is null)
        ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $expType2->id,
            'header_group_id' => null,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'include_in_expense' => true,
            'display_order' => 2,
        ]);

        $headerGroups = ShopLedgerHeaderGroup::where('shop_id', $this->shop->id)->get();
        $settings = ShopLedgerEntrySetting::where('shop_id', $this->shop->id)->get();

        $resolved = $this->layoutService->getResolvedLayout($this->shop->id, $headerGroups, $settings);

        // Filter all sections whose name/display is "Other Expenses" or "OTHER EXPENSES"
        $otherExpenseSections = collect($resolved['headers'])->filter(function ($h): bool {
            $norm = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $h['display_name']));

            return in_array($norm, ['otherexpenses', 'otherexpense'], true);
        });

        // Must ONLY have 1 single "Other Expenses" header
        $this->assertCount(1, $otherExpenseSections, 'There should be exactly one Other Expenses section.');
        $headerSection = $otherExpenseSections->first();

        // Both assigned setting and orphan setting must be present inside that single header
        $entryTypeIds = $headerSection['settings']->pluck('entry_type_id')->all();
        $this->assertContains($expType1->id, $entryTypeIds);
        $this->assertContains($expType2->id, $entryTypeIds);
    }

    public function test_admin_cannot_create_duplicate_header_with_same_name_and_type(): void
    {
        ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Other Expenses',
            'type' => 'expense',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->postJson(route('admin.cashbook.api.shop-settings.headers.create'), [
            'shop_id' => $this->shop->id,
            'name' => 'other expenses', // case-insensitive duplicate
            'type' => 'expense',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
        $this->assertStringContainsString('already exists', (string) $response->json('message'));
    }

    public function test_clean_duplicate_header_groups_artisan_command(): void
    {
        // Create duplicate header groups
        $primary = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Other Expenses',
            'type' => 'expense',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $duplicate = ShopLedgerHeaderGroup::query()->create([
            'shop_id' => $this->shop->id,
            'name' => 'OTHER EXPENSES',
            'type' => 'expense',
            'display_order' => 2,
            'enabled' => true,
        ]);

        $type1 = LedgerEntryType::firstOrCreate(
            ['code' => 'cmd_test_1'],
            ['name' => 'Exp 1', 'category' => 'expense', 'active' => true]
        );
        $type2 = LedgerEntryType::firstOrCreate(
            ['code' => 'cmd_test_2'],
            ['name' => 'Exp 2', 'category' => 'expense', 'active' => true]
        );

        $settingOnDup = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $type1->id,
            'header_group_id' => $duplicate->id,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'include_in_expense' => true,
        ]);

        $orphanSetting = ShopLedgerEntrySetting::query()->create([
            'shop_id' => $this->shop->id,
            'entry_type_id' => $type2->id,
            'header_group_id' => null,
            'version' => 1,
            'effective_from' => '2026-01-01',
            'enabled' => true,
            'include_in_expense' => true,
        ]);

        // Run artisan cleanup command
        $this->artisan('cashbook:clean-duplicate-headers', [
            'shop' => $this->shop->code,
            '--reset-ui-layout' => true,
        ])->assertSuccessful();

        // Check that duplicate was deleted
        $this->assertDatabaseMissing('shop_ledger_header_groups', ['id' => $duplicate->id]);
        $this->assertDatabaseHas('shop_ledger_header_groups', ['id' => $primary->id]);

        // Check that setting originally on duplicate was re-pointed to primary
        $settingOnDup->refresh();
        $this->assertEquals($primary->id, $settingOnDup->header_group_id);

        // Check that orphan setting was assigned to primary
        $orphanSetting->refresh();
        $this->assertEquals($primary->id, $orphanSetting->header_group_id);
    }
}
