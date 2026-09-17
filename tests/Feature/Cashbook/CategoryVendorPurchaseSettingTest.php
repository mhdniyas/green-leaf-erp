<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopLedgerHeaderGroup;
use App\Models\Shop;
use App\Models\ShopSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryVendorPurchaseSettingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop1;

    private Shop $shop2;

    private ShopLedgerEntrySetting $settingShop1;

    private ShopLedgerEntrySetting $settingShop2;

    private ShopCashbookRelation $settlementShop1;

    private ShopCashbookRelation $settlementShop2;

    private ShopSupplier $shopSupplier1;

    private ShopSupplier $shopSupplier2;

    private ShopSupplier $shopSupplierOtherShop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->shop1 = Shop::query()->create([
            'name' => 'Downtown Shop',
            'code' => 'DWT_01',
            'warehouse_tag' => 'DWT',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'shop_purchasing_enabled' => true,
        ]);

        $this->shop2 = Shop::query()->create([
            'name' => 'Uptown Shop',
            'code' => 'UPT_01',
            'warehouse_tag' => 'UPT',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'shop_purchasing_enabled' => true,
        ]);

        app(CashbookShopSyncService::class)->syncAndGetProfiles();

        $entryType = LedgerEntryType::query()->where('category', 'expense')->firstOrFail();

        $header1 = ShopLedgerHeaderGroup::query()->firstOrCreate([
            'shop_id' => $this->shop1->id,
            'name' => 'Purchases',
            'type' => 'expense',
        ]);

        $this->settingShop1 = ShopLedgerEntrySetting::query()->where('shop_id', $this->shop1->id)->where('entry_type_id', $entryType->id)->firstOrFail();
        $this->settingShop1->update(['header_group_id' => $header1->id]);

        $this->settingShop2 = ShopLedgerEntrySetting::query()->where('shop_id', $this->shop2->id)->where('entry_type_id', $entryType->id)->firstOrFail();

        $this->settlementShop1 = ShopCashbookRelation::query()->create([
            'shop_id' => $this->shop1->id,
            'name' => 'Shop 1 Bank Settlement',
            'settlement_type' => 'bank_settlement',
            'enabled' => true,
        ]);

        $this->settlementShop2 = ShopCashbookRelation::query()->create([
            'shop_id' => $this->shop2->id,
            'name' => 'Shop 2 Bank Settlement',
            'settlement_type' => 'bank_settlement',
            'enabled' => true,
        ]);

        $supplierA = Supplier::query()->create([
            'name' => 'Vegetable Supplier Alpha',
            'mobile_number' => '9876543210',
            'type' => 'local',
        ]);
        $supplierB = Supplier::query()->create([
            'name' => 'Vegetable Supplier Beta',
            'mobile_number' => '9876543211',
            'type' => 'local',
        ]);
        $supplierC = Supplier::query()->create([
            'name' => 'Other Shop Supplier',
            'mobile_number' => '9876543212',
            'type' => 'local',
        ]);

        $this->shopSupplier1 = ShopSupplier::query()->create([
            'shop_id' => $this->shop1->id,
            'supplier_id' => $supplierA->id,
            'is_active' => true,
            'credit_approved' => true,
        ]);

        $this->shopSupplier2 = ShopSupplier::query()->create([
            'shop_id' => $this->shop1->id,
            'supplier_id' => $supplierB->id,
            'is_active' => true,
            'credit_approved' => false,
        ]);

        $this->shopSupplierOtherShop = ShopSupplier::query()->create([
            'shop_id' => $this->shop2->id,
            'supplier_id' => $supplierC->id,
            'is_active' => true,
            'credit_approved' => true,
        ]);
    }

    public function test_normal_category_defaults_to_vendor_purchase_off(): void
    {
        $this->assertFalse((bool) $this->settingShop1->is_vendor_purchase);
        $this->assertNull($this->settingShop1->vendor_access_mode);
        $this->assertNull($this->settingShop1->vendor_settlement_relation_id);
    }

    public function test_category_settings_page_renders_vendor_purchase_options(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.settings.shop', ['shop' => $this->shop1->id]));

        $response->assertOk();
        $response->assertSee('Vendor Purchase Capability');
        $response->assertSee('Linked Vendors + Create');
        $response->assertSee('Defined Vendors Only');
        $response->assertSee('Vegetable Supplier Alpha');
    }

    public function test_vendor_purchase_on_with_linked_create_saves_successfully(): void
    {
        $payload = [
            'setting_id' => $this->settingShop1->id,
            'enabled' => 1,
            'default_funding_source' => 'sales',
            'include_in_sales' => 0,
            'include_in_income' => 0,
            'include_in_expense' => 1,
            'include_in_pl' => 1,
            'include_in_payable' => 0,
            'generates_secondary_entry' => 0,
            'secondary_amount_mode' => 'same_amount',
            'is_vendor_purchase' => 1,
            'vendor_access_mode' => 'linked_create',
            'vendor_settlement_relation_id' => $this->settlementShop1->id,
        ];

        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.shop-settings.update'), $payload);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->settingShop1->refresh();
        $this->assertTrue($this->settingShop1->is_vendor_purchase);
        $this->assertSame('linked_create', $this->settingShop1->vendor_access_mode);
        $this->assertSame($this->settlementShop1->id, $this->settingShop1->vendor_settlement_relation_id);
        $this->assertCount(0, $this->settingShop1->definedShopSuppliers);
    }

    public function test_vendor_purchase_on_with_linked_only_saves_successfully(): void
    {
        $payload = [
            'setting_id' => $this->settingShop1->id,
            'enabled' => 1,
            'default_funding_source' => 'sales',
            'include_in_sales' => 0,
            'include_in_income' => 0,
            'include_in_expense' => 1,
            'include_in_pl' => 1,
            'include_in_payable' => 0,
            'generates_secondary_entry' => 0,
            'secondary_amount_mode' => 'same_amount',
            'is_vendor_purchase' => 1,
            'vendor_access_mode' => 'linked_only',
            'vendor_settlement_relation_id' => $this->settlementShop1->id,
        ];

        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.shop-settings.update'), $payload);

        $response->assertOk();
        $this->settingShop1->refresh();
        $this->assertTrue($this->settingShop1->is_vendor_purchase);
        $this->assertSame('linked_only', $this->settingShop1->vendor_access_mode);
    }

    public function test_vendor_purchase_on_with_defined_only_saves_and_syncs_shop_suppliers(): void
    {
        $payload = [
            'setting_id' => $this->settingShop1->id,
            'enabled' => 1,
            'default_funding_source' => 'sales',
            'include_in_sales' => 0,
            'include_in_income' => 0,
            'include_in_expense' => 1,
            'include_in_pl' => 1,
            'include_in_payable' => 0,
            'generates_secondary_entry' => 0,
            'secondary_amount_mode' => 'same_amount',
            'is_vendor_purchase' => 1,
            'vendor_access_mode' => 'defined_only',
            'vendor_settlement_relation_id' => $this->settlementShop1->id,
            'defined_shop_supplier_ids' => [$this->shopSupplier1->id, $this->shopSupplier2->id],
        ];

        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.shop-settings.update'), $payload);

        $response->assertOk();
        $this->settingShop1->refresh();
        $this->assertTrue($this->settingShop1->is_vendor_purchase);
        $this->assertSame('defined_only', $this->settingShop1->vendor_access_mode);
        $this->assertCount(2, $this->settingShop1->definedShopSuppliers);
        $this->assertTrue($this->settingShop1->definedShopSuppliers->contains('id', $this->shopSupplier1->id));
        $this->assertTrue($this->settingShop1->definedShopSuppliers->contains('id', $this->shopSupplier2->id));
    }

    public function test_cross_shop_settlement_is_rejected(): void
    {
        $payload = [
            'setting_id' => $this->settingShop1->id,
            'enabled' => 1,
            'default_funding_source' => 'sales',
            'include_in_sales' => 0,
            'include_in_income' => 0,
            'include_in_expense' => 1,
            'include_in_pl' => 1,
            'include_in_payable' => 0,
            'generates_secondary_entry' => 0,
            'secondary_amount_mode' => 'same_amount',
            'is_vendor_purchase' => 1,
            'vendor_access_mode' => 'linked_create',
            'vendor_settlement_relation_id' => $this->settlementShop2->id, // Shop 2 settlement
        ];

        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.shop-settings.update'), $payload);

        $response->assertStatus(422);
        $response->assertJson(['success' => false, 'message' => 'The selected settlement does not belong to this shop.']);
    }

    public function test_cross_shop_vendor_is_rejected(): void
    {
        $payload = [
            'setting_id' => $this->settingShop1->id,
            'enabled' => 1,
            'default_funding_source' => 'sales',
            'include_in_sales' => 0,
            'include_in_income' => 0,
            'include_in_expense' => 1,
            'include_in_pl' => 1,
            'include_in_payable' => 0,
            'generates_secondary_entry' => 0,
            'secondary_amount_mode' => 'same_amount',
            'is_vendor_purchase' => 1,
            'vendor_access_mode' => 'defined_only',
            'defined_shop_supplier_ids' => [$this->shopSupplierOtherShop->id], // Shop 2 vendor
        ];

        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.shop-settings.update'), $payload);

        $response->assertStatus(422);
        $response->assertJson(['success' => false, 'message' => 'One or more selected vendors do not belong to this shop.']);
    }

    public function test_turning_off_vendor_purchase_preserves_dormant_configuration(): void
    {
        // First turn ON
        $this->settingShop1->update([
            'is_vendor_purchase' => true,
            'vendor_access_mode' => 'defined_only',
            'vendor_settlement_relation_id' => $this->settlementShop1->id,
        ]);
        $this->settingShop1->definedShopSuppliers()->sync([$this->shopSupplier1->id]);
        $this->assertCount(1, $this->settingShop1->fresh()->definedShopSuppliers);

        // Turn OFF
        $payload = [
            'setting_id' => $this->settingShop1->id,
            'enabled' => 1,
            'default_funding_source' => 'sales',
            'include_in_sales' => 0,
            'include_in_income' => 0,
            'include_in_expense' => 1,
            'include_in_pl' => 1,
            'include_in_payable' => 0,
            'generates_secondary_entry' => 0,
            'secondary_amount_mode' => 'same_amount',
            'is_vendor_purchase' => 0,
        ];

        $response = $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.shop-settings.update'), $payload);

        $response->assertOk();
        $this->settingShop1->refresh();
        $this->assertFalse($this->settingShop1->is_vendor_purchase);
        // Dormant configurations preserved
        $this->assertSame('defined_only', $this->settingShop1->vendor_access_mode);
        $this->assertSame($this->settlementShop1->id, $this->settingShop1->vendor_settlement_relation_id);
        $this->assertCount(1, $this->settingShop1->definedShopSuppliers);
    }

    public function test_vendor_can_belong_to_multiple_vendor_categories(): void
    {
        $secondSetting = ShopLedgerEntrySetting::query()
            ->where('shop_id', $this->shop1->id)
            ->where('id', '!=', $this->settingShop1->id)
            ->firstOrFail();

        // Assign shopSupplier1 to first setting
        $this->settingShop1->update([
            'is_vendor_purchase' => true,
            'vendor_access_mode' => 'defined_only',
        ]);
        $this->settingShop1->definedShopSuppliers()->sync([$this->shopSupplier1->id]);

        // Assign same shopSupplier1 to second setting
        $this->actingAs($this->admin)->postJson(route('admin.cashbook.api.shop-settings.update'), [
            'setting_id' => $secondSetting->id,
            'enabled' => 1,
            'default_funding_source' => 'sales',
            'include_in_sales' => 0,
            'include_in_income' => 0,
            'include_in_expense' => 1,
            'include_in_pl' => 1,
            'include_in_payable' => 0,
            'generates_secondary_entry' => 0,
            'secondary_amount_mode' => 'same_amount',
            'is_vendor_purchase' => 1,
            'vendor_access_mode' => 'defined_only',
            'defined_shop_supplier_ids' => [$this->shopSupplier1->id],
        ])->assertOk();

        $this->assertDatabaseHas('category_vendor_mappings', [
            'shop_ledger_entry_setting_id' => $this->settingShop1->id,
            'shop_supplier_id' => $this->shopSupplier1->id,
        ]);

        $this->assertDatabaseHas('category_vendor_mappings', [
            'shop_ledger_entry_setting_id' => $secondSetting->id,
            'shop_supplier_id' => $this->shopSupplier1->id,
        ]);
    }

    public function test_existing_vendor_settings_page_continues_to_work(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.settings.shop.vendors.index', ['shop' => $this->shop1->id]));

        $response->assertOk();
        $response->assertSee('Vegetable Supplier Alpha');
    }
}
