<?php

declare(strict_types=1);

namespace Tests\Feature\Cashbook;

use App\Models\PurchaseInvoice;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Cashbook\CashbookShopSyncService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCashbookVendorSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopUser1;

    private User $shopUser2;

    private Shop $shop1;

    private Shop $shop2;

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
        ]);

        $this->shop2 = Shop::query()->create([
            'name' => 'Uptown Shop',
            'code' => 'UPT_01',
            'warehouse_tag' => 'UPT',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $this->shopUser1 = User::factory()->create(['shop_id' => $this->shop1->id]);
        $this->shopUser1->assignRole('shop');

        $this->shopUser2 = User::factory()->create(['shop_id' => $this->shop2->id]);
        $this->shopUser2->assignRole('shop');
    }

    public function test_vendors_tab_is_visible_in_shop_cashbook_settings(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.settings.shop.vendors.index', ['shop' => $this->shop1->id]));

        $response->assertOk();
        $response->assertViewIs('admin.cashbook.settings.vendors.index');
        $response->assertSee('SHOP VENDORS');
        $response->assertSee('Vendors');
        $response->assertSee('+ Link Vendor');
        $response->assertSee('+ Create Vendor');
    }

    public function test_vendors_settings_resolves_shop_by_slug_from_ledger_profile(): void
    {
        $profile = app(CashbookShopSyncService::class)->syncAndGetProfiles()
            ->firstWhere('shop_id', $this->shop1->id);

        $this->assertNotNull($profile);
        $this->assertNotEmpty($profile->slug);

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.settings.shop.vendors.index', ['shop' => $profile->slug]));

        $response->assertOk();
        $response->assertViewIs('admin.cashbook.settings.vendors.index');
        $response->assertSee($this->shop1->name);
    }

    public function test_existing_supplier_can_be_linked_to_shop_without_duplicating(): void
    {
        $supplier = Supplier::query()->create([
            'name' => 'Fresh Dairy Ltd',
            'mobile_number' => '9876543210',
            'credit_approved' => true,
            'type' => 'local',
            'category' => 'shop_vendor',
        ]);

        $this->assertCount(1, Supplier::query()->where('name', 'Fresh Dairy Ltd')->get());
        $this->assertFalse($this->shop1->suppliers()->where('suppliers.id', $supplier->id)->exists());

        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.settings.shop.vendors.link', ['shop' => $this->shop1->id]), [
            'supplier_id' => $supplier->id,
        ]);

        $response->assertRedirect();
        $this->assertTrue($this->shop1->suppliers()->where('suppliers.id', $supplier->id)->exists());
        $this->assertTrue((bool) $this->shop1->suppliers()->where('suppliers.id', $supplier->id)->first()->pivot->is_active);

        // Ensure global supplier count is still exactly 1 (no duplicate supplier entity created)
        $this->assertCount(1, Supplier::query()->where('name', 'Fresh Dairy Ltd')->get());
    }

    public function test_new_supplier_can_be_created_and_automatically_linked(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.settings.shop.vendors.create', ['shop' => $this->shop1->id]), [
            'name' => 'Green Valley Farms',
            'mobile_number' => '9123456780',
            'contact' => 'Vikram',
            'credit_approved' => '1',
        ]);

        $response->assertRedirect();

        $supplier = Supplier::query()->where('name', 'Green Valley Farms')->first();
        $this->assertNotNull($supplier);
        $this->assertSame('9123456780', $supplier->mobile_number);
        $this->assertSame('Vikram', $supplier->contact);
        $this->assertTrue((bool) $supplier->credit_approved);

        // Check linked to shop1
        $this->assertTrue($this->shop1->suppliers()->where('suppliers.id', $supplier->id)->exists());
    }

    public function test_creating_supplier_with_existing_name_reuses_global_supplier_and_links_it(): void
    {
        $existing = Supplier::query()->create([
            'name' => 'City Bakery',
            'type' => 'local',
            'mobile_number' => '8888888888',
            'credit_approved' => true,
        ]);

        $this->actingAs($this->admin)->post(route('admin.cashbook.settings.shop.vendors.create', ['shop' => $this->shop1->id]), [
            'name' => 'City Bakery',
            'mobile_number' => '8888888888',
        ]);

        // Should not create duplicate supplier
        $this->assertSame(1, Supplier::query()->where('name', 'City Bakery')->count());
        $this->assertTrue($this->shop1->suppliers()->where('suppliers.id', $existing->id)->exists());
    }

    public function test_vendor_can_be_disabled_for_one_shop_only_while_remaining_active_for_another(): void
    {
        $supplier = Supplier::query()->create([
            'name' => 'Shared Spice Vendor',
            'type' => 'local',
            'credit_approved' => true,
        ]);

        // Link to both shops
        $this->shop1->suppliers()->attach($supplier->id, ['is_active' => true]);
        $this->shop2->suppliers()->attach($supplier->id, ['is_active' => true]);

        // Disable for shop1
        $response = $this->actingAs($this->admin)->post(route('admin.cashbook.settings.shop.vendors.toggle-status', [
            'shop' => $this->shop1->id,
            'supplier' => $supplier,
        ]));
        $response->assertRedirect();

        // Check shop1 pivot is disabled (false)
        $this->assertFalse((bool) $this->shop1->suppliers()->where('suppliers.id', $supplier->id)->first()->pivot->is_active);

        // Check shop2 pivot remains active (true)
        $this->assertTrue((bool) $this->shop2->suppliers()->where('suppliers.id', $supplier->id)->first()->pivot->is_active);

        // Check global supplier entity still exists
        $this->assertNotNull(Supplier::query()->find($supplier->id));
    }

    public function test_vendor_can_be_unlinked_safely_preserving_historical_purchases_and_global_record(): void
    {
        $supplier = Supplier::query()->create([
            'name' => 'High Volume Supplier',
            'type' => 'local',
            'credit_approved' => true,
        ]);

        $this->shop1->suppliers()->attach($supplier->id, ['is_active' => true]);

        // Create a historical purchase invoice
        $invoice = PurchaseInvoice::factory()->for($supplier)->create([
            'shop_id' => $this->shop1->id,
            'amount' => 500.00,
        ]);

        // Unlink from shop1
        $response = $this->actingAs($this->admin)->delete(route('admin.cashbook.settings.shop.vendors.unlink', [
            'shop' => $this->shop1->id,
            'supplier' => $supplier,
        ]));
        $response->assertRedirect();

        // Verify unlinked from shop1
        $this->assertFalse($this->shop1->suppliers()->where('suppliers.id', $supplier->id)->exists());

        // Verify global supplier record is preserved
        $this->assertNotNull(Supplier::query()->find($supplier->id));

        // Verify historical purchase invoice record is intact
        $invoice->refresh();
        $this->assertSame($supplier->id, $invoice->supplier_id);
        $this->assertSame($this->shop1->id, $invoice->shop_id);
    }

    public function test_unauthorized_user_cannot_access_or_modify_another_shops_vendors(): void
    {
        $supplier = Supplier::query()->create([
            'name' => 'Private Vendor',
            'type' => 'local',
            'credit_approved' => true,
        ]);

        // Shop user 2 tries to access Shop 1's vendor settings via JSON -> 403 Forbidden
        $response = $this->actingAs($this->shopUser2)->getJson(route('admin.cashbook.settings.shop.vendors.index', ['shop' => $this->shop1->id]));
        $response->assertForbidden();

        // Shop user 2 tries to access Shop 1's vendor settings via GET web -> redirects to dashboard with error
        $response = $this->actingAs($this->shopUser2)->get(route('admin.cashbook.settings.shop.vendors.index', ['shop' => $this->shop1->id]));
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');

        // Shop user 2 tries to link vendor to Shop 1 -> 403 Forbidden
        $response = $this->actingAs($this->shopUser2)->post(route('admin.cashbook.settings.shop.vendors.link', ['shop' => $this->shop1->id]), [
            'supplier_id' => $supplier->id,
        ]);
        $response->assertForbidden();

        // Shop user 1 CAN access their own shop vendors
        $response = $this->actingAs($this->shopUser1)->get(route('admin.cashbook.settings.shop.vendors.index', ['shop' => $this->shop1->id]));
        $response->assertOk();
    }

    public function test_global_supplier_search_api(): void
    {
        $s1 = Supplier::query()->create(['name' => 'Alpha Suppliers', 'type' => 'local', 'mobile_number' => '1111111111']);
        $s2 = Supplier::query()->create(['name' => 'Beta Wholesalers', 'type' => 'local', 'mobile_number' => '2222222222']);

        $this->shop1->suppliers()->attach($s1->id, ['is_active' => true]);

        $response = $this->actingAs($this->admin)->getJson(route('admin.cashbook.settings.shop.vendors.search-global', [
            'shop' => $this->shop1->id,
            'q' => 'Alpha',
        ]));

        $response->assertOk();
        $response->assertJsonStructure(['suppliers']);
        $data = $response->json('suppliers');
        $this->assertCount(1, $data);
        $this->assertSame('Alpha Suppliers', $data[0]['name']);
        $this->assertTrue($data[0]['is_already_linked']);
    }

    public function test_shop_owner_cashbook_vendors_redirects_to_active_shop_vendors_settings(): void
    {
        $profile = app(CashbookShopSyncService::class)->syncAndGetProfiles()
            ->firstWhere('shop_id', $this->shop1->id);

        $response = $this->actingAs($this->shopUser1)->get(route('shop-owner.cashbook.vendors'));
        $response->assertRedirect(route('admin.cashbook.settings.shop.vendors.index', ['shop' => $profile->slug]));
    }

    public function test_vendor_settings_page_shows_disabled_warning_and_enable_button_when_purchasing_is_disabled(): void
    {
        $this->shop1->update(['shop_purchasing_enabled' => false]);
        $this->assertFalse($this->shop1->fresh()->isPurchasingEnabled());

        $response = $this->actingAs($this->admin)->get(route('admin.cashbook.settings.shop.vendors.index', ['shop' => $this->shop1->id]));

        $response->assertOk();
        $response->assertSee('Shop Purchasing is disabled');
        $response->assertSee('Vendors are configured, but Shop Owner cannot create purchases.');
        $response->assertSee('Enable Shop Purchasing');
        $response->assertSee('Shop Purchasing: Disabled');
    }

    public function test_admin_can_enable_purchasing_from_vendor_settings_page_and_warning_disappears(): void
    {
        $this->shop1->update(['shop_purchasing_enabled' => false]);
        $this->assertFalse($this->shop1->fresh()->isPurchasingEnabled());

        $vendorUrl = route('admin.cashbook.settings.shop.vendors.index', ['shop' => $this->shop1->id]);

        $response = $this->actingAs($this->admin)
            ->from($vendorUrl)
            ->post(route('admin.cashbook.settings.shop.toggle-purchasing', ['shop' => $this->shop1->id]));

        $response->assertRedirect($vendorUrl);
        $this->assertTrue($this->shop1->fresh()->isPurchasingEnabled());

        $afterResponse = $this->actingAs($this->admin)->get($vendorUrl);
        $afterResponse->assertOk();
        $afterResponse->assertDontSee('Shop Purchasing is disabled');
        $afterResponse->assertSee('Shop Purchasing: Enabled');
    }

    public function test_admin_can_toggle_shop_owner_vendor_creation_permission(): void
    {
        $this->assertFalse($this->shop1->fresh()->isVendorCreationAllowed());

        $vendorUrl = route('admin.cashbook.settings.shop.vendors.index', ['shop' => $this->shop1->id]);

        // Toggle ON
        $response = $this->actingAs($this->admin)
            ->from($vendorUrl)
            ->post(route('admin.cashbook.settings.shop.vendors.toggle-creation-permission', ['shop' => $this->shop1->id]));

        $response->assertRedirect($vendorUrl);
        $this->assertTrue($this->shop1->fresh()->isVendorCreationAllowed());

        $pageResponse = $this->actingAs($this->admin)->get($vendorUrl);
        $pageResponse->assertOk();
        $pageResponse->assertSee('SHOP OWNER PERMISSIONS');
        $pageResponse->assertSee('Allow Shop Owner to Create Vendors');
        $pageResponse->assertSee('ON');

        // Toggle OFF
        $responseOff = $this->actingAs($this->admin)
            ->from($vendorUrl)
            ->post(route('admin.cashbook.settings.shop.vendors.toggle-creation-permission', ['shop' => $this->shop1->id]));

        $responseOff->assertRedirect($vendorUrl);
        $this->assertFalse($this->shop1->fresh()->isVendorCreationAllowed());
    }

    public function test_shop_owner_cannot_create_vendor_when_permission_disabled(): void
    {
        $this->shop1->update(['allow_vendor_creation' => false]);

        $response = $this->actingAs($this->shopUser1)->postJson(
            route('admin.cashbook.settings.shop.vendors.create', ['shop' => $this->shop1->id]),
            ['name' => 'Forbidden Vendor', 'mobile_number' => '9998887770']
        );

        $response->assertStatus(403);
    }

    public function test_shop_owner_can_create_vendor_when_permission_enabled_with_credit_disabled_by_default(): void
    {
        $this->shop1->update(['allow_vendor_creation' => true]);

        $response = $this->actingAs($this->shopUser1)->postJson(
            route('admin.cashbook.settings.shop.vendors.create', ['shop' => $this->shop1->id]),
            [
                'name' => 'Direct Shop Vendor',
                'mobile_number' => '9998887771',
                'contact' => 'GSTIN12345',
                'credit_approved' => true, // Shop owner tries to pass credit_approved = true
            ]
        );

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'supplier' => [
                'name' => 'Direct Shop Vendor',
                'credit_approved' => false, // Must be forced to false
            ],
        ]);

        $supplier = Supplier::query()->where('name', 'Direct Shop Vendor')->firstOrFail();
        $this->assertFalse((bool) $supplier->credit_approved);

        $pivot = $this->shop1->suppliers()->where('supplier_id', $supplier->id)->firstOrFail()->pivot;
        $this->assertTrue((bool) $pivot->is_active);
        $this->assertFalse((bool) $pivot->credit_approved);
    }

    public function test_admin_can_later_approve_credit_for_shop_created_vendor(): void
    {
        $this->shop1->update(['allow_vendor_creation' => true]);

        $supplier = Supplier::query()->create([
            'name' => 'Pending Credit Vendor',
            'mobile_number' => '9888777666',
            'type' => 'local',
            'category' => 'shop_vendor',
            'credit_approved' => false,
        ]);
        $this->shop1->suppliers()->attach($supplier->id, [
            'is_active' => true,
            'credit_approved' => false,
        ]);

        // Admin updates vendor to enable credit
        $response = $this->actingAs($this->admin)->putJson(
            route('admin.cashbook.settings.shop.vendors.update', ['shop' => $this->shop1->id, 'supplier' => $supplier->id]),
            [
                'name' => 'Pending Credit Vendor',
                'mobile_number' => '9888777666',
                'credit_approved' => true,
            ]
        );

        $response->assertOk();
        $this->assertTrue((bool) $supplier->fresh()->credit_approved);
        $pivot = $this->shop1->suppliers()->where('supplier_id', $supplier->id)->firstOrFail()->pivot;
        $this->assertTrue((bool) $pivot->credit_approved);
    }
}
