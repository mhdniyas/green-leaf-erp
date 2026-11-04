<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOwnerAssignment;
use App\Models\ShopPriceGroup;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SellingPriceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Super Admin']);
        $this->admin->assignRole('admin');

        $this->staff = User::factory()->create(['name' => 'Regular Staff']);
    }

    public function test_admin_can_view_selling_price_general_page_with_default_25_percent(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.selling-price.index'));

        $response->assertOk();
        $response->assertViewIs('admin.selling-price.index');
        $this->assertSame(25.0, $response->viewData('sellingPriceDefaultPercentage'));

        // Navigation elements
        $response->assertSee('Selling Price Management');
        $response->assertSee('General Pricing');
        $response->assertSee('Price Categories');
        $response->assertSee('Product Matrix');
        $response->assertSee('Shop Pricing');

        // Form elements
        $response->assertSee('Shop Owner Selling Price');
        $response->assertSee('Default Retail Markup (%)');
        $response->assertSee('Live Calculation Preview');
        $response->assertSee('Illustrative Example');
        $response->assertSee('Recommended Min Retail Price');
        $response->assertSee('Pricing Notice');
        $response->assertSee('Save Selling Price Settings');
    }

    public function test_admin_view_loads_configured_selling_price_percentage(): void
    {
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'selling_price_default_percentage'],
            ['value' => '33.5']
        );

        $response = $this->actingAs($this->admin)
            ->get(route('admin.selling-price.index'));

        $response->assertOk();
        $this->assertSame(33.5, $response->viewData('sellingPriceDefaultPercentage'));
        $response->assertSee('value="33.5"', false);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.selling-price.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_selling_price_management(): void
    {
        $response = $this->actingAs($this->staff)
            ->get(route('admin.selling-price.index'));

        $response->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'You do not have access to that page.');
    }

    public function test_admin_can_update_general_selling_price_percentage(): void
    {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.selling-price.update-general'), [
                'selling_price_default_percentage' => '30.0',
            ]);

        $response->assertRedirect(route('admin.selling-price.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('business_settings', [
            'key' => 'selling_price_default_percentage',
            'value' => '30.0',
        ]);
    }

    public function test_selling_price_percentage_supports_decimal_and_zero(): void
    {
        $responseZero = $this->actingAs($this->admin)
            ->patch(route('admin.selling-price.update-general'), [
                'selling_price_default_percentage' => '0',
            ]);

        $responseZero->assertRedirect(route('admin.selling-price.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('business_settings', [
            'key' => 'selling_price_default_percentage',
            'value' => '0',
        ]);

        $responseDecimal = $this->actingAs($this->admin)
            ->patch(route('admin.selling-price.update-general'), [
                'selling_price_default_percentage' => '17.75',
            ]);

        $responseDecimal->assertRedirect(route('admin.selling-price.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('business_settings', [
            'key' => 'selling_price_default_percentage',
            'value' => '17.75',
        ]);
    }

    public function test_validation_rejects_negative_value(): void
    {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.selling-price.update-general'), [
                'selling_price_default_percentage' => '-5',
            ]);

        $response->assertSessionHasErrors(['selling_price_default_percentage']);
    }

    public function test_validation_rejects_value_above_500(): void
    {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.selling-price.update-general'), [
                'selling_price_default_percentage' => '500.01',
            ]);

        $response->assertSessionHasErrors(['selling_price_default_percentage']);
    }

    public function test_validation_rejects_non_numeric_and_empty_value(): void
    {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.selling-price.update-general'), [
                'selling_price_default_percentage' => 'abc',
            ]);

        $response->assertSessionHasErrors(['selling_price_default_percentage']);
    }

    public function test_non_admin_cannot_update_selling_price_percentage(): void
    {
        $response = $this->actingAs($this->staff)
            ->patch(route('admin.selling-price.update-general'), [
                'selling_price_default_percentage' => '20',
            ]);

        $response->assertForbidden();
    }

    public function test_legacy_company_settings_tab_redirects_to_selling_price_module(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.company-settings.edit', ['tab' => 'selling-price']));

        $response->assertRedirect(route('admin.selling-price.index'));
    }

    public function test_company_settings_edit_no_longer_renders_selling_price_panel_or_tab(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.company-settings.edit'));

        $response->assertOk();
        $response->assertDontSee('data-settings-tab="selling-price"', false);
        $response->assertDontSee('data-settings-panel="selling-price"', false);
    }

    public function test_shop_owner_min_retail_price_reflects_updated_percentage_from_selling_price_module(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-22 10:00:00', 'Asia/Kolkata'));

        $priceGroup = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 0, 'is_active' => true]
        );

        $shop = Shop::factory()->create([
            'code' => 'SH01',
            'name' => 'Downtown Shop',
            'shop_price_group_id' => $priceGroup->id,
        ]);

        $shopOwner = User::factory()->create();
        $shopOwner->assignRole('shop');
        $shopOwner->givePermissionTo('sales.order.create');

        ShopOwnerAssignment::query()->create([
            'shop_id' => $shop->id,
            'user_id' => $shopOwner->id,
            'status' => 'active',
        ]);

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Carrot Ooty',
            'base_price' => 0.00,
            'vendor_price' => 0.00,
            'is_active' => true,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $product->id,
            'business_date' => '2026-09-22',
            'price_a' => 100.00,
            'price_b' => 100.00,
            'price_c' => 100.00,
            'status' => 'approved',
            'price_unit' => 'kg',
        ]);

        // 1. Update to 40% via Selling Price Module
        $this->actingAs($this->admin)
            ->patch(route('admin.selling-price.update-general'), [
                'selling_price_default_percentage' => '40',
            ])
            ->assertRedirect(route('admin.selling-price.index'));

        // 2. Verify Shop Owner products page calculates 100 * 1.40 = 140.00
        $shopResponse = $this->actingAs($shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));

        $shopResponse->assertOk();
        $shopResponse->assertSee('Carrot Ooty');
        $shopResponse->assertSee('₹100');
        $shopResponse->assertSee('₹140');

        $products = $shopResponse->viewData('products');
        $this->assertNotEmpty($products);

        $firstProduct = $products->first();
        $this->assertEquals(140.00, $firstProduct['minimum_mrp']);

        Carbon::setTestNow();
    }
}
