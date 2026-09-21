<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\CategoryVendorMapping;
use App\Models\Cashbook\CompanyAccount;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopCashbookRelationItem;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Shop;
use App\Models\ShopSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\CashFlowResolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminCashbookCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shopA;

    private Shop $shopB;

    protected function setUp(): void
    {
        parent::setUp();

        $adminEmail = 'admin@greenleaf.com';
        config(['admin.user_access.main_admin_email' => $adminEmail]);

        Role::findOrCreate('admin');

        $this->admin = User::factory()->create([
            'email' => $adminEmail,
        ]);
        $this->admin->assignRole('admin');

        $this->shopA = Shop::factory()->create(['name' => 'Shop Alpha']);
        $this->shopB = Shop::factory()->create(['name' => 'Shop Beta']);
    }

    public function test_category_list_displays_all_categories_and_shop_summaries(): void
    {
        $category = LedgerEntryType::create([
            'code' => 'utility_bill',
            'name' => 'Utility Bill',
            'category' => 'expense',
            'active' => true,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $category->id,
            'display_name' => 'Utility Bill',
            'enabled' => true,
            'effective_from' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.categories.index'));

        $response->assertStatus(200);
        $response->assertSee('Utility Bill');
        $response->assertSee('utility_bill');
    }

    public function test_admin_can_create_new_category(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.categories.store'), [
            'name' => 'Internet Bill',
            'category' => 'expense',
            'active' => 1,
            'shop_ids' => [$this->shopA->id, $this->shopB->id],
        ]);

        $category = LedgerEntryType::where('name', 'Internet Bill')->first();
        $this->assertNotNull($category);
        $this->assertEquals('expense', $category->category);
        $this->assertTrue($category->active);

        $response->assertRedirect(route('admin.cashbook.categories.show', $category->code));

        $settingA = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)->where('entry_type_id', $category->id)->first();
        $settingB = ShopLedgerEntrySetting::where('shop_id', $this->shopB->id)->where('entry_type_id', $category->id)->first();

        $this->assertNotNull($settingA);
        $this->assertTrue((bool) $settingA->enabled);
        $this->assertNotNull($settingB);
        $this->assertTrue((bool) $settingB->enabled);
    }

    public function test_category_accessible_by_code_or_id(): void
    {
        $category = LedgerEntryType::create([
            'code' => 'safe_url_category',
            'name' => 'Safe URL Category',
            'category' => 'income',
            'active' => true,
        ]);

        ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $category->id,
            'enabled' => true,
            'effective_from' => now()->toDateString(),
            'header_display_order' => 0,
            'display_order' => 0,
        ]);

        // Accessible via URL-safe string code
        $responseCode = $this->actingAs($this->admin)->get(route('admin.cashbook.categories.show', $category->code));
        $responseCode->assertStatus(200);
        $responseCode->assertSee('Safe URL Category');

        // Accessible via numeric ID for backwards compatibility
        $responseId = $this->actingAs($this->admin)->get(route('admin.cashbook.categories.show', $category->id));
        $responseId->assertStatus(200);
        $responseId->assertSee('Safe URL Category');
    }

    public function test_assign_category_to_one_shop(): void
    {
        $category = LedgerEntryType::create([
            'code' => 'test_cat',
            'name' => 'Test Category',
            'category' => 'income',
            'active' => true,
        ]);

        $this->actingAs($this->admin)->post(route('admin.cashbook.categories.assign-shops', $category->id), [
            'shop_ids' => [$this->shopA->id],
        ]);

        $settingA = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)->where('entry_type_id', $category->id)->first();
        $settingB = ShopLedgerEntrySetting::where('shop_id', $this->shopB->id)->where('entry_type_id', $category->id)->first();

        $this->assertNotNull($settingA);
        $this->assertTrue((bool) $settingA->enabled);
        $this->assertNull($settingB);
    }

    public function test_assign_category_to_multiple_shops(): void
    {
        $category = LedgerEntryType::create([
            'code' => 'test_multi',
            'name' => 'Test Multi',
            'category' => 'expense',
            'active' => true,
        ]);

        $this->actingAs($this->admin)->post(route('admin.cashbook.categories.assign-shops', $category->id), [
            'shop_ids' => [$this->shopA->id, $this->shopB->id],
        ]);

        $this->assertTrue((bool) ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)->where('entry_type_id', $category->id)->value('enabled'));
        $this->assertTrue((bool) ShopLedgerEntrySetting::where('shop_id', $this->shopB->id)->where('entry_type_id', $category->id)->value('enabled'));
    }

    public function test_edit_shop_specific_header(): void
    {
        $category = LedgerEntryType::create([
            'code' => 'header_test',
            'name' => 'Header Test',
            'category' => 'expense',
            'active' => true,
        ]);

        $headerA = ShopLedgerHeaderGroup::create([
            'shop_id' => $this->shopA->id,
            'name' => 'Shop A Expenses',
            'type' => 'expense',
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.categories.shop.basic-header', [$category->id, $this->shopA->id]), [
            'enabled' => 1,
            'display_name' => 'Custom Header Test',
            'header_group_id' => $headerA->id,
        ]);
        $response->assertSessionHasNoErrors();

        $settingA = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)->where('entry_type_id', $category->id)->first();
        $this->assertNotNull($settingA);
        $this->assertEquals('Custom Header Test', $settingA->display_name);
        $this->assertEquals($headerA->id, $settingA->header_group_id);
    }

    public function test_edit_settlement_relation_using_existing_structure(): void
    {
        $category = LedgerEntryType::create([
            'code' => 'settlement_test',
            'name' => 'Settlement Test',
            'category' => 'income',
            'active' => true,
        ]);

        $settingA = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $category->id,
            'enabled' => true,
            'effective_from' => now()->toDateString(),
        ]);

        $relationA = ShopCashbookRelation::create([
            'shop_id' => $this->shopA->id,
            'name' => 'Income Settlement',
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.categories.shop.settlement', [$category->id, $this->shopA->id]), [
            'relation_id' => $relationA->id,
            'role' => 'add',
            'settlement_behavior' => 'increase',
        ]);
        $response->assertSessionHasNoErrors();

        $settingA->refresh();
        $this->assertEquals('increase', $settingA->settlement_behavior);
        $this->assertEquals($relationA->id, $settingA->vendor_settlement_relation_id);

        $item = ShopCashbookRelationItem::where('relation_id', $relationA->id)->where('shop_ledger_entry_setting_id', $settingA->id)->first();
        $this->assertNotNull($item);
        $this->assertEquals('add', $item->role);
    }

    public function test_edit_company_account_relation(): void
    {
        $category = LedgerEntryType::create([
            'code' => 'bank_test',
            'name' => 'Bank Test',
            'category' => 'income',
            'active' => true,
        ]);

        $account = CompanyAccount::create([
            'name' => 'HDFC Bank Account',
            'account_type' => 'bank',
            'bank_name' => 'HDFC',
            'account_number' => '1234567890',
            'active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.categories.shop.company-relation', [$category->id, $this->shopA->id]), [
            'company_account_id' => $account->id,
            'default_funding_source' => 'company',
        ]);
        $response->assertSessionHasNoErrors();

        $settingA = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)->where('entry_type_id', $category->id)->first();
        $this->assertNotNull($settingA);
        $this->assertEquals($account->id, $settingA->company_account_id);
        $this->assertEquals('company', $settingA->default_funding_source);
    }

    public function test_edit_vendor_configuration(): void
    {
        $category = LedgerEntryType::create([
            'code' => 'vendor_test',
            'name' => 'Vendor Test',
            'category' => 'expense',
            'active' => true,
        ]);

        $globalSupplier = Supplier::create([
            'name' => 'Test Global Supplier',
            'type' => 'vendor',
        ]);

        $shopSupplier = ShopSupplier::create([
            'shop_id' => $this->shopA->id,
            'supplier_id' => $globalSupplier->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.categories.shop.vendor-relation', [$category->id, $this->shopA->id]), [
            'is_vendor_purchase' => 1,
            'vendor_purchase_payment_type' => 'credit',
            'vendor_access_mode' => 'pinned',
            'pinned_supplier_ids' => [$shopSupplier->id],
        ]);
        $response->assertSessionHasNoErrors();

        $settingA = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)->where('entry_type_id', $category->id)->first();
        $this->assertNotNull($settingA);
        $this->assertTrue((bool) $settingA->is_vendor_purchase);
        $this->assertEquals('credit', $settingA->vendor_purchase_payment_type);
        $this->assertEquals('pinned', $settingA->vendor_access_mode);

        $mapping = CategoryVendorMapping::where('shop_ledger_entry_setting_id', $settingA->id)->first();
        $this->assertNotNull($mapping);
        $this->assertEquals($shopSupplier->id, $mapping->shop_supplier_id);
    }

    public function test_edit_report_flags(): void
    {
        $category = LedgerEntryType::create([
            'code' => 'report_test',
            'name' => 'Report Test',
            'category' => 'income',
            'active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.categories.shop.reports', [$category->id, $this->shopA->id]), [
            'include_in_sales' => 1,
            'include_in_income' => 1,
            'include_in_pl' => 1,
            'sales_report_bucket' => 'sales',
        ]);
        $response->assertSessionHasNoErrors();

        $settingA = ShopLedgerEntrySetting::where('shop_id', $this->shopA->id)->where('entry_type_id', $category->id)->first();
        $this->assertTrue((bool) $settingA->include_in_sales);
        $this->assertTrue((bool) $settingA->include_in_income);
        $this->assertTrue((bool) $settingA->include_in_pl);
        $this->assertEquals('sales', $settingA->sales_report_bucket);
    }

    public function test_disable_category_for_one_shop_without_affecting_another(): void
    {
        $category = LedgerEntryType::create([
            'code' => 'isolation_test',
            'name' => 'Isolation Test',
            'category' => 'expense',
            'active' => true,
        ]);

        $settingA = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $category->id,
            'enabled' => true,
            'effective_from' => now()->toDateString(),
        ]);

        $settingB = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopB->id,
            'entry_type_id' => $category->id,
            'enabled' => true,
            'effective_from' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.categories.shop.basic-header', [$category->id, $this->shopA->id]), [
            'enabled' => 0,
        ]);
        $response->assertSessionHasNoErrors();

        $settingA->refresh();
        $settingB->refresh();

        $this->assertFalse((bool) $settingA->enabled);
        $this->assertTrue((bool) $settingB->enabled);
    }

    public function test_disabling_category_in_one_shop_does_not_disable_global_ledger_entry_type(): void
    {
        $category = LedgerEntryType::create([
            'code' => 'global_test',
            'name' => 'Global Test',
            'category' => 'expense',
            'active' => true,
        ]);

        $settingA = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $category->id,
            'enabled' => true,
            'effective_from' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.categories.shop.basic-header', [$category->id, $this->shopA->id]), [
            'enabled' => 0,
        ]);
        $response->assertSessionHasNoErrors();

        $category->refresh();
        $this->assertTrue($category->active);
    }

    public function test_vendor_configuration_change_in_shop_a_cannot_alter_shop_b(): void
    {
        $category = LedgerEntryType::create([
            'code' => 'vendor_iso_test',
            'name' => 'Vendor Isolation Test',
            'category' => 'expense',
            'active' => true,
        ]);

        $settingA = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $category->id,
            'enabled' => true,
            'is_vendor_purchase' => false,
            'effective_from' => now()->toDateString(),
        ]);

        $settingB = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopB->id,
            'entry_type_id' => $category->id,
            'enabled' => true,
            'is_vendor_purchase' => true,
            'vendor_purchase_payment_type' => 'cash',
            'effective_from' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.categories.shop.vendor-relation', [$category->id, $this->shopA->id]), [
            'is_vendor_purchase' => 1,
            'vendor_purchase_payment_type' => 'credit',
        ]);
        $response->assertSessionHasNoErrors();

        $settingA->refresh();
        $settingB->refresh();

        $this->assertEquals('credit', $settingA->vendor_purchase_payment_type);
        $this->assertEquals('cash', $settingB->vendor_purchase_payment_type);
    }

    public function test_settlement_configuration_change_in_shop_a_cannot_alter_shop_b(): void
    {
        $category = LedgerEntryType::create([
            'code' => 'settle_iso_test',
            'name' => 'Settle Isolation Test',
            'category' => 'income',
            'active' => true,
        ]);

        $settingA = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $category->id,
            'enabled' => true,
            'settlement_behavior' => 'increase',
            'effective_from' => now()->toDateString(),
        ]);

        $settingB = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopB->id,
            'entry_type_id' => $category->id,
            'enabled' => true,
            'settlement_behavior' => 'increase',
            'effective_from' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.categories.shop.settlement', [$category->id, $this->shopA->id]), [
            'settlement_behavior' => 'decrease',
        ]);
        $response->assertSessionHasNoErrors();

        $settingA->refresh();
        $settingB->refresh();

        $this->assertEquals('decrease', $settingA->settlement_behavior);
        $this->assertEquals('increase', $settingB->settlement_behavior);
    }

    public function test_historical_transactions_remain_untouched_after_category_edit(): void
    {
        $category = LedgerEntryType::create([
            'code' => 'tx_history_test',
            'name' => 'Tx History Test',
            'category' => 'expense',
            'active' => true,
        ]);

        $settingA = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $category->id,
            'enabled' => true,
            'effective_from' => now()->toDateString(),
        ]);

        $tx = ShopLedgerTransaction::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $category->id,
            'direction' => 'out',
            'funding_source' => 'sales',
            'business_date' => now()->toDateString(),
            'entry_date' => now()->toDateString(),
            'amount' => 500.00,
            'transaction_type' => 'out',
            'status' => 'verified',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.categories.shop.basic-header', [$category->id, $this->shopA->id]), [
            'display_name' => 'Updated Tx Category Name',
        ]);
        $response->assertSessionHasNoErrors();

        $tx->refresh();
        $this->assertEquals(500.00, (float) $tx->amount);
        $this->assertEquals($category->id, $tx->entry_type_id);
    }

    public function test_existing_old_settings_pages_still_work(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.settings'));
        $response->assertStatus(200);

        $responseShop = $this->actingAs($this->admin)->get(route('admin.cashbook.settings.shop', $this->shopA->id));
        $responseShop->assertStatus(200);
    }

    public function test_existing_settlement_calculation_engine_code_path_remains_unchanged_and_consumes_updated_configuration(): void
    {
        $category = LedgerEntryType::create([
            'code' => 'calc_engine_test',
            'name' => 'Calc Engine Test',
            'category' => 'income',
            'active' => true,
        ]);

        $setting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $category->id,
            'enabled' => true,
            'settlement_behavior' => 'increase',
            'effective_from' => now()->toDateString(),
        ]);

        $relation = ShopCashbookRelation::create([
            'shop_id' => $this->shopA->id,
            'name' => 'Settlement Relation Test',
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.categories.shop.settlement', [$category->id, $this->shopA->id]), [
            'relation_id' => $relation->id,
            'role' => 'add',
            'settlement_behavior' => 'increase',
        ]);
        $response->assertSessionHasNoErrors();

        $item = ShopCashbookRelationItem::where('relation_id', $relation->id)->where('shop_ledger_entry_setting_id', $setting->id)->first();
        $this->assertNotNull($item);
        $this->assertEquals('add', $item->role);
    }

    public function test_existing_cash_flow_resolution_service_results_remain_unchanged_and_consumes_updated_configuration(): void
    {
        $category = LedgerEntryType::create([
            'code' => 'cash_flow_res_test',
            'name' => 'Cash Flow Res Test',
            'category' => 'expense',
            'active' => true,
        ]);

        $setting = ShopLedgerEntrySetting::create([
            'shop_id' => $this->shopA->id,
            'entry_type_id' => $category->id,
            'enabled' => true,
            'default_funding_source' => 'sales',
            'effective_from' => now()->toDateString(),
        ]);

        $service = app(CashFlowResolutionService::class);
        $this->assertEquals('sales', $service->resolveFundingSource($setting));

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.categories.shop.company-relation', [$category->id, $this->shopA->id]), [
            'default_funding_source' => 'company',
        ]);
        $response->assertSessionHasNoErrors();

        $setting->refresh();
        $this->assertEquals('company', $service->resolveFundingSource($setting));
    }
}
