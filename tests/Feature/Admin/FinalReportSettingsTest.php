<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Client;
use App\Models\CompanyAccountingCategory;
use App\Models\PurchaseProductFilter;
use App\Models\Shop;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinalReportSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorizedUser;

    private Shop $clientShopA;

    private Shop $clientShopB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, LedgerEntryTypeSeeder::class, ShopConfigPresetSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->unauthorizedUser = User::factory()->create();

        $client = Client::create([
            'name' => 'Test Client Corp',
            'code' => 'TCC-01',
            'status' => 'active',
        ]);
        $this->clientShopA = Shop::factory()->create([
            'client_id' => $client->id,
            'accounting_enabled' => true,
            'status' => 'active',
        ]);
        $this->clientShopB = Shop::factory()->create([
            'client_id' => $client->id,
            'accounting_enabled' => true,
            'status' => 'active',
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();
    }

    public function test_settings_index_accessible_by_admin(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.settings.final-report.index'));

        $response->assertOk();
        $response->assertViewIs('admin.cashbook.settings.final-report.index');
        $response->assertViewHas(['readiness', 'filters', 'clientShops', 'headings', 'expenseMappings']);
    }

    public function test_settings_forbidden_for_unauthorized_user(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->getJson(route('admin.cashbook.settings.final-report.index'));

        $response->assertForbidden();
    }

    public function test_can_update_purchase_product_groups(): void
    {
        $filter1 = PurchaseProductFilter::create([
            'name' => 'Apple / Banana Group',
            'product_criteria' => ['Apple', 'Banana'],
        ]);

        $filter2 = PurchaseProductFilter::create([
            'name' => 'Carrot / Onion Group',
            'product_criteria' => ['Carrot', 'Onion'],
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.settings.final-report.product-groups'), [
            'assignments' => [
                [
                    'filter_id' => $filter1->id,
                    'monthly_report_group' => 'fruits',
                ],
                [
                    'filter_id' => $filter2->id,
                    'monthly_report_group' => 'vegetables',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('purchase_product_filters', [
            'id' => $filter1->id,
            'monthly_report_group' => 'fruits',
        ]);
        $this->assertDatabaseHas('purchase_product_filters', [
            'id' => $filter2->id,
            'monthly_report_group' => 'vegetables',
        ]);
    }

    public function test_can_update_shop_headings_and_copy_between_shops(): void
    {
        $settingA = ShopLedgerEntrySetting::where('shop_id', $this->clientShopA->id)->firstOrFail();
        $entryType1 = $settingA->entryType;

        // 1. Update shop A
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.settings.final-report.shop-headings'), [
            'shop_id' => $this->clientShopA->id,
            'mappings' => [
                [
                    'setting_id' => $settingA->id,
                    'monthly_report_bucket' => 'fruits_sale',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shop_ledger_entry_settings', [
            'id' => $settingA->id,
            'shop_id' => $this->clientShopA->id,
            'monthly_report_bucket' => 'fruits_sale',
        ]);

        // 2. Copy from Shop A to Shop B
        $responseCopy = $this->actingAs($this->admin)->post(route('admin.cashbook.settings.final-report.shop-headings'), [
            'shop_id' => $this->clientShopB->id,
            'copy_from_shop_id' => $this->clientShopA->id,
        ]);

        $responseCopy->assertRedirect();
        $this->assertDatabaseHas('shop_ledger_entry_settings', [
            'shop_id' => $this->clientShopB->id,
            'entry_type_id' => $entryType1->id,
            'monthly_report_bucket' => 'fruits_sale',
        ]);
    }

    public function test_can_update_expense_category_mappings(): void
    {
        $account = Account::create([
            'name' => 'Rent Expense Account',
            'code' => 'EXP-RENT-01',
            'type' => 'expense',
            'is_active' => true,
        ]);

        $companyCat = CompanyAccountingCategory::create([
            'name' => 'Shop Rent Category',
            'type' => 'expense',
            'account_id' => $account->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.settings.final-report.expense-mappings'), [
            'mappings' => [
                [
                    'source_type' => 'procurement_expense_category',
                    'source_key' => 'vehicle',
                    'report_bucket' => 'vehicle_fuel',
                ],
                [
                    'source_type' => 'company_accounting_category',
                    'source_key' => (string) $companyCat->id,
                    'report_bucket' => 'rent',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cashbook_monthly_report_expense_mappings', [
            'source_type' => 'procurement_expense_category',
            'source_key' => 'vehicle',
            'report_bucket' => 'vehicle_fuel',
        ]);
        $this->assertDatabaseHas('cashbook_monthly_report_expense_mappings', [
            'source_type' => 'company_accounting_category',
            'source_key' => (string) $companyCat->id,
            'report_bucket' => 'rent',
        ]);
    }

    public function test_readiness_json_endpoint(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.settings.final-report.readiness'));

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'unmapped_product_filters',
            'unconfigured_shops',
            'unmapped_expense_categories',
            'configured_shops',
        ]);
    }

    public function test_settings_forms_accept_aliases_and_normalize_payloads(): void
    {
        // 1. Product groups alias: veg -> vegetables, other -> null
        $filter = PurchaseProductFilter::create([
            'name' => 'Vegetable Group',
            'product_criteria' => ['Tomato'],
        ]);

        $res1 = $this->actingAs($this->admin)->post(route('admin.cashbook.settings.final-report.product-groups'), [
            'assignments' => [
                ['filter_id' => $filter->id, 'monthly_report_group' => 'veg'],
            ],
        ]);
        $res1->assertRedirect();
        $this->assertEquals('vegetables', $filter->fresh()->monthly_report_group);

        // 2. Shop headings using entry_type_id instead of setting_id
        $settingA = ShopLedgerEntrySetting::where('shop_id', $this->clientShopA->id)->firstOrFail();
        $res2 = $this->actingAs($this->admin)->post(route('admin.cashbook.settings.final-report.shop-headings'), [
            'shop_id' => $this->clientShopA->id,
            'mappings' => [
                [
                    'entry_type_id' => $settingA->entry_type_id,
                    'monthly_report_bucket' => 'expenses',
                ],
            ],
        ]);
        $res2->assertRedirect();
        $this->assertEquals('other_expense', $settingA->fresh()->monthly_report_bucket);

        // 3. Expense mapping using procurement_expense and monthly_report_category aliases
        $res3 = $this->actingAs($this->admin)->post(route('admin.cashbook.settings.final-report.expense-mappings'), [
            'mappings' => [
                [
                    'source_type' => 'procurement_expense',
                    'source_key' => 'fuel',
                    'monthly_report_category' => 'vehicle_fuel',
                ],
            ],
        ]);
        $res3->assertRedirect();
        $this->assertDatabaseHas('cashbook_monthly_report_expense_mappings', [
            'source_type' => 'procurement_expense_category',
            'source_key' => 'fuel',
            'report_bucket' => 'vehicle_fuel',
        ]);
    }
}
