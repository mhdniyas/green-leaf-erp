<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopCashbookRelationItem;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Client;
use App\Models\Shop;
use App\Models\User;
use App\Support\CashbookAccess;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HeaderCategoryCashbookArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopUserA;

    private User $shopUserB;

    private User $unauthorizedUser;

    private Shop $shopA;

    private Shop $shopB;

    private ShopLedgerHeaderGroup $dailyExpenseHeaderA;

    private ShopLedgerHeaderGroup $pettyExpenseHeaderA;

    protected function setUp(): void
    {
        parent::setUp();

        $adminEmail = 'admin@greenleaf.com';
        config(['admin.user_access.main_admin_email' => $adminEmail]);

        Role::findOrCreate('admin');
        Role::findOrCreate('shop');
        Permission::findOrCreate(CashbookAccess::SettingsView, 'web');
        Permission::findOrCreate(CashbookAccess::SettingsManage, 'web');
        Permission::findOrCreate(CashbookAccess::Access, 'web');
        Permission::findOrCreate('sales.order.create', 'web');

        $client = Client::create([
            'name' => 'Test Client',
            'code' => 'TEST_CLIENT',
            'status' => 'active',
        ]);

        $this->shopA = Shop::factory()->create([
            'name' => 'Kochi Store',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'client_id' => $client->id,
            'status' => 'active',
        ]);
        $this->shopB = Shop::factory()->create([
            'name' => 'Calicut Store',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'client_id' => $client->id,
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create(['email' => $adminEmail]);
        $this->admin->assignRole('admin');

        $this->shopUserA = User::factory()->create(['shop_id' => $this->shopA->id]);
        $this->shopUserA->assignRole('shop');
        $this->shopUserA->givePermissionTo('sales.order.create');
        CashbookAccess::updateUserPermissions($this->shopUserA, [
            CashbookAccess::Access,
            CashbookAccess::SettingsView,
            CashbookAccess::SettingsManage,
        ]);

        $this->shopUserB = User::factory()->create(['shop_id' => $this->shopB->id]);
        $this->shopUserB->assignRole('shop');
        $this->shopUserB->givePermissionTo('sales.order.create');
        CashbookAccess::updateUserPermissions($this->shopUserB, [
            CashbookAccess::Access,
            CashbookAccess::SettingsView,
            CashbookAccess::SettingsManage,
        ]);

        $this->unauthorizedUser = User::factory()->create(['shop_id' => $this->shopA->id]);
        // unauthorizedUser has no cashbook permissions

        $this->dailyExpenseHeaderA = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shopA->id,
            'name' => 'Daily Cash Expenditure',
            'type' => 'expense',
            'cash_flow_mode' => 'shop_cash',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $this->pettyExpenseHeaderA = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shopA->id,
            'name' => 'Petty Cash Expenditure',
            'type' => 'expense',
            'cash_flow_mode' => 'petty',
            'display_order' => 2,
            'enabled' => true,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. Category relationships: Multi-Header support
    // ──────────────────────────────────────────────────────────────────────────

    public function test_can_create_vehicle_under_daily_cash_expenditure(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.categories.shop.headers.attach', [$this->shopA->id, $this->dailyExpenseHeaderA->id]),
            [
                'mode' => 'new',
                'name' => 'Vehicle',
                'display_name' => 'Daily Vehicle',
            ]
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $vehicleType = LedgerEntryType::where('name', 'Vehicle')->first();
        $this->assertNotNull($vehicleType);
        $this->assertEquals('expense', $vehicleType->category);

        $setting = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)
            ->where('header_group_id', $this->dailyExpenseHeaderA->id)
            ->where('entry_type_id', $vehicleType->id)
            ->first();

        $this->assertNotNull($setting);
        $this->assertEquals('Daily Vehicle', $setting->display_name);
        $this->assertEquals('sales', $setting->default_funding_source);
        $this->assertTrue((bool) $setting->enabled);
    }

    public function test_can_add_same_vehicle_category_to_petty_cash_expenditure_and_coexist(): void
    {
        // 1. Create Vehicle
        $vehicleType = LedgerEntryType::create([
            'code' => 'vehicle',
            'name' => 'Vehicle',
            'category' => 'expense',
            'system_type' => 'custom',
            'active' => true,
        ]);

        // 2. Attach to Daily Cash Expenditure
        $res1 = $this->actingAs($this->admin)->post(
            route('admin.cashbook.categories.shop.headers.attach', [$this->shopA->id, $this->dailyExpenseHeaderA->id]),
            [
                'mode' => 'existing',
                'category_id' => $vehicleType->id,
                'display_name' => 'Vehicle (Daily)',
            ]
        );
        $res1->assertSessionHasNoErrors();

        // 3. Attach SAME Vehicle to Petty Cash Expenditure
        $res2 = $this->actingAs($this->admin)->post(
            route('admin.cashbook.categories.shop.headers.attach', [$this->shopA->id, $this->pettyExpenseHeaderA->id]),
            [
                'mode' => 'existing',
                'category_id' => $vehicleType->id,
                'display_name' => 'Vehicle (Petty)',
            ]
        );
        $res2->assertSessionHasNoErrors();

        // 4. Verify both coexist under Shop A
        $settings = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)
            ->where('entry_type_id', $vehicleType->id)
            ->get();

        $this->assertCount(2, $settings);

        $dailySetting = $settings->firstWhere('header_group_id', $this->dailyExpenseHeaderA->id);
        $pettySetting = $settings->firstWhere('header_group_id', $this->pettyExpenseHeaderA->id);

        $this->assertNotNull($dailySetting);
        $this->assertEquals('Vehicle (Daily)', $dailySetting->display_name);
        $this->assertEquals('sales', $dailySetting->default_funding_source);

        $this->assertNotNull($pettySetting);
        $this->assertEquals('Vehicle (Petty)', $pettySetting->display_name);
        $this->assertEquals('petty', $pettySetting->default_funding_source);

        // Global LedgerEntryType has NOT been duplicated!
        $this->assertEquals(1, LedgerEntryType::where('code', 'vehicle')->count());
    }

    public function test_duplicate_category_under_the_same_header_is_rejected(): void
    {
        $vehicleType = LedgerEntryType::create([
            'code' => 'vehicle',
            'name' => 'Vehicle',
            'category' => 'expense',
            'system_type' => 'custom',
            'active' => true,
        ]);

        // Attach once
        $this->actingAs($this->admin)->post(
            route('admin.cashbook.categories.shop.headers.attach', [$this->shopA->id, $this->dailyExpenseHeaderA->id]),
            [
                'mode' => 'existing',
                'category_id' => $vehicleType->id,
            ]
        );

        // Attempt duplicate under SAME header
        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.categories.shop.headers.attach', [$this->shopA->id, $this->dailyExpenseHeaderA->id]),
            [
                'mode' => 'existing',
                'category_id' => $vehicleType->id,
            ]
        );

        $response->assertSessionHasErrors('category_id');

        $count = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)
            ->where('header_group_id', $this->dailyExpenseHeaderA->id)
            ->where('entry_type_id', $vehicleType->id)
            ->count();

        $this->assertEquals(1, $count);
    }

    public function test_categories_remain_correctly_scoped_to_the_shop(): void
    {
        $vehicleType = LedgerEntryType::create([
            'code' => 'vehicle',
            'name' => 'Vehicle',
            'category' => 'expense',
            'system_type' => 'custom',
            'active' => true,
        ]);

        // Attached in Shop A
        $this->actingAs($this->admin)->post(
            route('admin.cashbook.categories.shop.headers.attach', [$this->shopA->id, $this->dailyExpenseHeaderA->id]),
            [
                'mode' => 'existing',
                'category_id' => $vehicleType->id,
            ]
        );

        // Verify Shop B has NO settings for Vehicle
        $shopBSettings = ShopLedgerEntrySetting::where('shop_id', $this->shopB->id)
            ->where('entry_type_id', $vehicleType->id)
            ->count();

        $this->assertEquals(0, $shopBSettings);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. Authorization & Tenant Isolation
    // ──────────────────────────────────────────────────────────────────────────

    public function test_authorized_shop_user_can_manage_their_own_categories(): void
    {
        $teaType = LedgerEntryType::create([
            'code' => 'tea_snacks',
            'name' => 'Tea & Snacks',
            'category' => 'expense',
            'system_type' => 'custom',
            'active' => true,
        ]);

        $response = $this->actingAs($this->shopUserA)->post(
            route('admin.cashbook.categories.shop.headers.attach', [$this->shopA->id, $this->pettyExpenseHeaderA->id]),
            [
                'mode' => 'existing',
                'category_id' => $teaType->id,
            ]
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertTrue(
            ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)
                ->where('header_group_id', $this->pettyExpenseHeaderA->id)
                ->where('entry_type_id', $teaType->id)
                ->exists()
        );
    }

    public function test_unauthorized_user_cannot_modify_configuration(): void
    {
        $teaType = LedgerEntryType::create([
            'code' => 'tea_snacks',
            'name' => 'Tea & Snacks',
            'category' => 'expense',
            'system_type' => 'custom',
            'active' => true,
        ]);

        $response = $this->actingAs($this->unauthorizedUser)->post(
            route('admin.cashbook.categories.shop.headers.attach', [$this->shopA->id, $this->pettyExpenseHeaderA->id]),
            [
                'mode' => 'existing',
                'category_id' => $teaType->id,
            ]
        );

        $response->assertStatus(403);
    }

    public function test_shop_a_user_cannot_modify_shop_b_configuration(): void
    {
        $headerB = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shopB->id,
            'name' => 'Shop B Expense',
            'type' => 'expense',
            'cash_flow_mode' => 'shop_cash',
            'display_order' => 1,
            'enabled' => true,
        ]);

        $teaType = LedgerEntryType::create([
            'code' => 'tea_snacks',
            'name' => 'Tea & Snacks',
            'category' => 'expense',
            'system_type' => 'custom',
            'active' => true,
        ]);

        // User from Shop A attempts to attach category to Shop B
        $response = $this->actingAs($this->shopUserA)->post(
            route('admin.cashbook.categories.shop.headers.attach', [$this->shopB->id, $headerB->id]),
            [
                'mode' => 'existing',
                'category_id' => $teaType->id,
            ]
        );

        $response->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. System Category Protection
    // ──────────────────────────────────────────────────────────────────────────

    public function test_protected_system_categories_cannot_be_renamed(): void
    {
        $salesType = LedgerEntryType::create([
            'code' => 'cash_sales',
            'name' => 'Cash Sales',
            'category' => 'income',
            'system_type' => 'system',
            'active' => true,
        ]);

        $setting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $salesType->id,
            'display_name' => 'Cash Sales',
            'is_readonly' => true,
            'enabled' => true,
            'effective_from' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.categories.settings.rename', $setting->id),
            ['name' => 'Arbitrary Sales Name']
        );

        $response->assertSessionHasErrors('name');
        $this->assertEquals('Cash Sales', $setting->fresh()->displayName());
    }

    public function test_protected_system_categories_cannot_be_detached_or_deleted(): void
    {
        $salesType = LedgerEntryType::create([
            'code' => 'cash_sales',
            'name' => 'Cash Sales',
            'category' => 'income',
            'system_type' => 'system',
            'active' => true,
        ]);

        $setting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'header_group_id' => $this->dailyExpenseHeaderA->id,
            'entry_type_id' => $salesType->id,
            'display_name' => 'Cash Sales',
            'is_readonly' => true,
            'enabled' => true,
            'effective_from' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->delete(
            route('admin.cashbook.categories.settings.detach', $setting->id)
        );

        $response->assertSessionHasErrors('setting');
        $this->assertDatabaseHas('shop_ledger_entry_settings', ['id' => $setting->id]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. Historical Data & Settlement Safety
    // ──────────────────────────────────────────────────────────────────────────

    public function test_detaching_category_with_historical_transactions_disables_without_deleting_row(): void
    {
        $customType = LedgerEntryType::create([
            'code' => 'maintenance',
            'name' => 'Maintenance',
            'category' => 'expense',
            'system_type' => 'custom',
            'active' => true,
        ]);

        $setting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'header_group_id' => $this->dailyExpenseHeaderA->id,
            'entry_type_id' => $customType->id,
            'display_name' => 'Maintenance',
            'enabled' => true,
            'effective_from' => now()->toDateString(),
        ]);

        // Create historical ledger transaction
        $tx = ShopLedgerTransaction::create([
            'shop_id' => $this->shopA->id,
            'business_date' => Carbon::now()->subDays(10)->toDateString(),
            'entry_type_id' => $customType->id,
            'amount' => 500.00,
            'direction' => 'out',
            'funding_source' => 'sales',
        ]);

        // Attempt detach
        $response = $this->actingAs($this->admin)->delete(
            route('admin.cashbook.categories.settings.detach', $setting->id)
        );

        $response->assertSessionHasNoErrors();

        // Must still exist in database for audit trail, but disabled and unlinked from header
        $refreshed = $setting->fresh();
        $this->assertNotNull($refreshed);
        $this->assertFalse((bool) $refreshed->enabled);
        $this->assertNull($refreshed->header_group_id);

        // Historical transaction remains linked to entry_type_id intact
        $this->assertDatabaseHas('shop_ledger_transactions', [
            'id' => $tx->id,
            'entry_type_id' => $customType->id,
            'amount' => 500.00,
        ]);
    }

    public function test_settlement_settings_and_relation_items_remain_valid(): void
    {
        $relation = ShopCashbookRelation::create([
            'shop_id' => $this->shopA->id,
            'name' => 'Main Supplier Settlement',
            'type' => 'vendor_settlement',
            'is_active' => true,
        ]);

        $category = LedgerEntryType::create([
            'code' => 'milk_purchase',
            'name' => 'Milk Purchase',
            'category' => 'expense',
            'system_type' => 'custom',
            'active' => true,
        ]);

        $setting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'header_group_id' => $this->dailyExpenseHeaderA->id,
            'entry_type_id' => $category->id,
            'display_name' => 'Milk Purchase',
            'enabled' => true,
            'effective_from' => now()->toDateString(),
        ]);

        $item = ShopCashbookRelationItem::create([
            'relation_id' => $relation->id,
            'shop_ledger_entry_setting_id' => $setting->id,
            'header_group_id' => $this->dailyExpenseHeaderA->id,
            'role' => 'add',
            'header_mode' => 'all_categories',
        ]);

        $this->assertNotNull($item);
        $this->assertEquals($setting->id, $item->shop_ledger_entry_setting_id);
        $this->assertEquals($this->dailyExpenseHeaderA->id, $item->header_group_id);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. UI & Cashbook Multi-Header Transaction Behavior
    // ──────────────────────────────────────────────────────────────────────────

    public function test_category_settings_view_displays_headers_and_multi_header_badges(): void
    {
        $vehicleType = LedgerEntryType::create([
            'code' => 'vehicle',
            'name' => 'Vehicle',
            'category' => 'expense',
            'system_type' => 'custom',
            'active' => true,
        ]);

        // Attach under both headers
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'header_group_id' => $this->dailyExpenseHeaderA->id,
            'entry_type_id' => $vehicleType->id,
            'display_name' => 'Vehicle (Daily)',
            'enabled' => true,
            'effective_from' => now()->toDateString(),
            'default_funding_source' => 'sales',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'header_group_id' => $this->pettyExpenseHeaderA->id,
            'entry_type_id' => $vehicleType->id,
            'display_name' => 'Vehicle (Petty)',
            'enabled' => true,
            'effective_from' => now()->toDateString(),
            'default_funding_source' => 'petty',
        ]);

        $response = $this->actingAs($this->admin)->get(
            route('admin.cashbook.categories.index', ['shop' => $this->shopA->id, 'view' => 'headers'])
        );

        $response->assertStatus(200);
        $response->assertSee('Daily Cash Expenditure');
        $response->assertSee('Petty Cash Expenditure');
        $response->assertSee('Vehicle (Daily)');
        $response->assertSee('Vehicle (Petty)');
        $response->assertSee('In 2 Headers');
    }

    public function test_reordering_categories_under_header(): void
    {
        $cat1 = LedgerEntryType::create(['code' => 'c1', 'name' => 'Cat 1', 'category' => 'expense', 'active' => true]);
        $cat2 = LedgerEntryType::create(['code' => 'c2', 'name' => 'Cat 2', 'category' => 'expense', 'active' => true]);

        $s1 = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'header_group_id' => $this->dailyExpenseHeaderA->id,
            'entry_type_id' => $cat1->id,
            'header_display_order' => 1,
            'enabled' => true,
            'effective_from' => now()->toDateString(),
        ]);

        $s2 = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'header_group_id' => $this->dailyExpenseHeaderA->id,
            'entry_type_id' => $cat2->id,
            'header_display_order' => 2,
            'enabled' => true,
            'effective_from' => now()->toDateString(),
        ]);

        // Reverse order
        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.categories.shop.headers.reorder', [$this->shopA->id, $this->dailyExpenseHeaderA->id]),
            ['order' => [$s2->id, $s1->id]]
        );

        $response->assertSessionHasNoErrors();
        $this->assertEquals(1, $s2->fresh()->header_display_order);
        $this->assertEquals(2, $s1->fresh()->header_display_order);
    }

    public function test_bulk_recording_entries_for_multi_header_category_preserves_both_transactions(): void
    {
        $vehicleType = LedgerEntryType::create([
            'code' => 'vehicle',
            'name' => 'Vehicle',
            'category' => 'expense',
            'system_type' => 'custom',
            'active' => true,
        ]);

        // Daily Cash setting (funding source: sales)
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'header_group_id' => $this->dailyExpenseHeaderA->id,
            'entry_type_id' => $vehicleType->id,
            'display_name' => 'Vehicle (Daily)',
            'enabled' => true,
            'effective_from' => now()->toDateString(),
            'default_funding_source' => 'sales',
        ]);

        // Petty Cash setting (funding source: petty)
        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'header_group_id' => $this->pettyExpenseHeaderA->id,
            'entry_type_id' => $vehicleType->id,
            'display_name' => 'Vehicle (Petty)',
            'enabled' => true,
            'effective_from' => now()->toDateString(),
            'default_funding_source' => 'petty',
        ]);

        $date = now()->toDateString();

        // 1. Record Daily Cash Vehicle entry ($150)
        $res1 = $this->actingAs($this->shopUserA)->postJson(
            route('shop-owner.cashbook.api.bulk-record-entries'),
            [
                'business_date' => $date,
                'header_group_id' => $this->dailyExpenseHeaderA->id,
                'entries' => [
                    [
                        'entry_type_code' => 'vehicle',
                        'amount' => 150.00,
                        'funding_source' => 'sales',
                    ],
                ],
            ]
        );
        $res1->assertStatus(200);

        // 2. Record Petty Cash Vehicle entry ($75)
        $res2 = $this->actingAs($this->shopUserA)->postJson(
            route('shop-owner.cashbook.api.bulk-record-entries'),
            [
                'business_date' => $date,
                'header_group_id' => $this->pettyExpenseHeaderA->id,
                'entries' => [
                    [
                        'entry_type_code' => 'vehicle',
                        'amount' => 75.00,
                        'funding_source' => 'petty',
                    ],
                ],
            ]
        );
        $res2->assertStatus(200);

        // 3. Verify both transactions exist independently
        $txs = ShopLedgerTransaction::where('shop_id', $this->shopA->id)
            ->where('business_date', $date)
            ->where('entry_type_id', $vehicleType->id)
            ->get();

        $this->assertCount(2, $txs);

        $dailyTx = $txs->firstWhere('funding_source', 'sales');
        $pettyTx = $txs->firstWhere('funding_source', 'petty');

        $this->assertNotNull($dailyTx);
        $this->assertEquals(150.00, (float) $dailyTx->amount);

        $this->assertNotNull($pettyTx);
        $this->assertEquals(75.00, (float) $pettyTx->amount);
    }

    public function test_cashbook_data_returns_categories_under_headers(): void
    {
        $vehicleType = LedgerEntryType::create([
            'code' => 'vehicle',
            'name' => 'Vehicle',
            'category' => 'expense',
            'system_type' => 'custom',
            'active' => true,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'header_group_id' => $this->dailyExpenseHeaderA->id,
            'entry_type_id' => $vehicleType->id,
            'display_name' => 'Vehicle (Daily)',
            'enabled' => true,
            'effective_from' => now()->toDateString(),
            'default_funding_source' => 'sales',
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'header_group_id' => $this->pettyExpenseHeaderA->id,
            'entry_type_id' => $vehicleType->id,
            'display_name' => 'Vehicle (Petty)',
            'enabled' => true,
            'effective_from' => now()->toDateString(),
            'default_funding_source' => 'petty',
        ]);

        $response = $this->actingAs($this->shopUserA)->getJson(
            route('shop-owner.cashbook.api.shop-data', ['date' => now()->toDateString()])
        );

        $response->assertStatus(200);
        $settings = collect($response->json('settings'));

        $dailySetting = $settings->firstWhere('header_group_id', $this->dailyExpenseHeaderA->id);
        $pettySetting = $settings->firstWhere('header_group_id', $this->pettyExpenseHeaderA->id);

        $this->assertNotNull($dailySetting);
        $this->assertNotNull($pettySetting);
        $this->assertEquals('Vehicle (Daily)', $dailySetting['display_name']);
        $this->assertEquals('Vehicle (Petty)', $pettySetting['display_name']);
    }

    public function test_can_attach_category_using_clean_header_route_without_shop_id_in_url(): void
    {
        $category = LedgerEntryType::create([
            'code' => 'stationery',
            'name' => 'Stationery',
            'category' => 'expense',
            'system_type' => 'custom',
            'active' => true,
        ]);

        // POST /admin/cashbook/categories/headers/{header}/categories (no shop ID in URL)
        $response = $this->actingAs($this->admin)->post(
            route('admin.cashbook.categories.headers.attach-category', $this->pettyExpenseHeaderA->id),
            [
                'mode' => 'existing',
                'category_id' => $category->id,
            ]
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.cashbook.categories.index', ['shop' => $this->shopA->id, 'view' => 'headers']));

        $this->assertTrue(
            ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)
                ->where('header_group_id', $this->pettyExpenseHeaderA->id)
                ->where('entry_type_id', $category->id)
                ->exists()
        );
    }
}
