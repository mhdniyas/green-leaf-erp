<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\SellingPriceCategory;
use App\Models\Shop;
use App\Models\ShopOwnerAssignment;
use App\Models\ShopPriceGroup;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SellingPriceCategoryTest extends TestCase
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

    public function test_admin_can_view_price_categories_index(): void
    {
        SellingPriceCategory::factory()->create([
            'name' => 'Standard Veg',
            'markup_percentage' => 25.0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.selling-price.categories.index'));

        $response->assertOk();
        $response->assertViewIs('admin.selling-price.categories');
        $response->assertSee('Selling Price Categories');
        $response->assertSee('Standard Veg');
        $response->assertSee('25%');
        $response->assertSee('Add Category');
        $response->assertSee('Manage Products');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.selling-price.categories.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_price_categories(): void
    {
        $response = $this->actingAs($this->staff)
            ->get(route('admin.selling-price.categories.index'));

        $response->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'You do not have access to that page.');
    }

    public function test_admin_can_create_selling_price_category(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.selling-price.categories.store'), [
                'name' => 'Premium Exotic',
                'markup_percentage' => '35.5',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('admin.selling-price.categories.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('selling_price_categories', [
            'name' => 'Premium Exotic',
            'markup_percentage' => 35.5,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    public function test_category_creation_validation_rules(): void
    {
        // Negative percentage
        $negResponse = $this->actingAs($this->admin)
            ->post(route('admin.selling-price.categories.store'), [
                'name' => 'Invalid Negative',
                'markup_percentage' => '-5',
            ]);
        $negResponse->assertSessionHasErrors(['markup_percentage']);

        // Above 500%
        $highResponse = $this->actingAs($this->admin)
            ->post(route('admin.selling-price.categories.store'), [
                'name' => 'Invalid High',
                'markup_percentage' => '500.1',
            ]);
        $highResponse->assertSessionHasErrors(['markup_percentage']);

        // Non numeric
        $strResponse = $this->actingAs($this->admin)
            ->post(route('admin.selling-price.categories.store'), [
                'name' => 'Invalid Str',
                'markup_percentage' => 'abc',
            ]);
        $strResponse->assertSessionHasErrors(['markup_percentage']);

        // Missing name
        $emptyResponse = $this->actingAs($this->admin)
            ->post(route('admin.selling-price.categories.store'), [
                'name' => '',
                'markup_percentage' => '20',
            ]);
        $emptyResponse->assertSessionHasErrors(['name']);
    }

    public function test_category_supports_zero_and_decimal_percentages(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.selling-price.categories.store'), [
                'name' => 'Zero Margin',
                'markup_percentage' => '0',
            ])
            ->assertRedirect(route('admin.selling-price.categories.index'));

        $this->assertDatabaseHas('selling_price_categories', [
            'name' => 'Zero Margin',
            'markup_percentage' => 0.0,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.selling-price.categories.store'), [
                'name' => 'Fine Fraction',
                'markup_percentage' => '12.34',
            ])
            ->assertRedirect(route('admin.selling-price.categories.index'));

        $this->assertDatabaseHas('selling_price_categories', [
            'name' => 'Fine Fraction',
            'markup_percentage' => 12.34,
        ]);
    }

    public function test_duplicate_category_name_is_rejected(): void
    {
        SellingPriceCategory::factory()->create(['name' => 'Organic Range']);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.selling-price.categories.store'), [
                'name' => 'Organic Range',
                'markup_percentage' => '20',
            ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_admin_can_update_category(): void
    {
        $category = SellingPriceCategory::factory()->create([
            'name' => 'Old Category Name',
            'markup_percentage' => 20.0,
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.selling-price.categories.update', $category), [
                'name' => 'Renamed Category',
                'markup_percentage' => '22.5',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('admin.selling-price.categories.index'))
            ->assertSessionHas('success');

        $category->refresh();
        $this->assertSame('Renamed Category', $category->name);
        $this->assertSame(22.5, $category->markup_percentage);
        $this->assertSame($this->admin->id, $category->updated_by);
    }

    public function test_admin_can_toggle_category_status(): void
    {
        $category = SellingPriceCategory::factory()->create([
            'name' => 'Status Toggle Test',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.selling-price.categories.toggle-status', $category));

        $response->assertRedirect(route('admin.selling-price.categories.index'));
        $this->assertFalse($category->fresh()->is_active);

        // Toggle back to active
        $this->actingAs($this->admin)
            ->patch(route('admin.selling-price.categories.toggle-status', $category));
        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_cannot_delete_category_with_assigned_products(): void
    {
        $category = SellingPriceCategory::factory()->create(['name' => 'Has Products']);
        $botanical = Category::factory()->create(['name' => 'Vegetables']);

        $product = Product::factory()->create([
            'category_id' => $botanical->id,
            'name' => 'Tomato Local',
            'selling_price_category_id' => $category->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.selling-price.categories.destroy', $category));

        $response->assertRedirect(route('admin.selling-price.categories.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('selling_price_categories', ['id' => $category->id]);
    }

    public function test_can_delete_empty_category(): void
    {
        $category = SellingPriceCategory::factory()->create(['name' => 'Empty Category']);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.selling-price.categories.destroy', $category));

        $response->assertRedirect(route('admin.selling-price.categories.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('selling_price_categories', ['id' => $category->id]);
    }

    public function test_admin_can_assign_single_product_to_category(): void
    {
        $category = SellingPriceCategory::factory()->create(['name' => 'Single Assignment']);
        $botanical = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $botanical->id, 'selling_price_category_id' => null]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.selling-price.categories.assign-products', $category), [
                'product_ids' => [$product->id],
            ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'assigned_count' => 1,
        ]);

        $this->assertSame($category->id, $product->fresh()->selling_price_category_id);
    }

    public function test_admin_can_assign_multiple_products_to_category(): void
    {
        $category = SellingPriceCategory::factory()->create(['name' => 'Batch Assignment']);
        $botanical = Category::factory()->create();

        $p1 = Product::factory()->create(['category_id' => $botanical->id]);
        $p2 = Product::factory()->create(['category_id' => $botanical->id]);
        $p3 = Product::factory()->create(['category_id' => $botanical->id]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.selling-price.categories.assign-products', $category), [
                'product_ids' => [$p1->id, $p2->id, $p3->id],
            ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'assigned_count' => 3,
        ]);

        $this->assertSame($category->id, $p1->fresh()->selling_price_category_id);
        $this->assertSame($category->id, $p2->fresh()->selling_price_category_id);
        $this->assertSame($category->id, $p3->fresh()->selling_price_category_id);
    }

    public function test_reassigning_product_requires_confirmation_when_already_in_another_category(): void
    {
        $catA = SellingPriceCategory::factory()->create(['name' => 'Category Alpha']);
        $catB = SellingPriceCategory::factory()->create(['name' => 'Category Beta']);
        $botanical = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $botanical->id,
            'name' => 'Assigned Product',
            'selling_price_category_id' => $catA->id,
        ]);

        // Attempt reassignment without confirmation -> should fail with 422 requires_confirmation
        $failResponse = $this->actingAs($this->admin)
            ->postJson(route('admin.selling-price.categories.assign-products', $catB), [
                'product_ids' => [$product->id],
                'confirm_reassign' => false,
            ]);

        $failResponse->assertStatus(422);
        $failResponse->assertJson([
            'status' => 'requires_confirmation',
        ]);
        $this->assertSame($catA->id, $product->fresh()->selling_price_category_id);

        // Attempt reassignment WITH confirmation -> succeeds
        $successResponse = $this->actingAs($this->admin)
            ->postJson(route('admin.selling-price.categories.assign-products', $catB), [
                'product_ids' => [$product->id],
                'confirm_reassign' => true,
            ]);

        $successResponse->assertOk();
        $this->assertSame($catB->id, $product->fresh()->selling_price_category_id);
    }

    public function test_admin_can_remove_product_assignment(): void
    {
        $category = SellingPriceCategory::factory()->create(['name' => 'Removal Test']);
        $botanical = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $botanical->id,
            'selling_price_category_id' => $category->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.selling-price.categories.remove-product', $category), [
                'product_id' => $product->id,
            ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'removed' => true,
        ]);

        $this->assertNull($product->fresh()->selling_price_category_id);
    }

    public function test_duplicate_assignment_prevention_at_database_level(): void
    {
        $categoryA = SellingPriceCategory::factory()->create(['name' => 'Cat 1']);
        $categoryB = SellingPriceCategory::factory()->create(['name' => 'Cat 2']);
        $botanical = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $botanical->id,
            'selling_price_category_id' => $categoryA->id,
        ]);

        // Product has exactly one column for selling_price_category_id
        $this->assertSame($categoryA->id, $product->selling_price_category_id);

        $product->update(['selling_price_category_id' => $categoryB->id]);
        $this->assertSame($categoryB->id, $product->fresh()->selling_price_category_id);
    }

    public function test_unassigned_product_effective_markup_falls_back_to_global_default(): void
    {
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'selling_price_default_percentage'],
            ['value' => '25.0']
        );

        $botanical = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $botanical->id,
            'selling_price_category_id' => null,
        ]);

        $this->assertNull($product->selling_price_category_id);
        $this->assertNull($product->sellingPriceCategory);

        // When a category is inactive, its effectiveMarkupPercentage falls back to global default
        $inactiveCategory = SellingPriceCategory::factory()->create([
            'markup_percentage' => 45.0,
            'is_active' => false,
        ]);

        $this->assertSame(25.0, $inactiveCategory->effectiveMarkupPercentage());
    }

    public function test_active_category_effective_markup_returns_its_configured_percentage(): void
    {
        $activeCategory = SellingPriceCategory::factory()->create([
            'markup_percentage' => 38.5,
            'is_active' => true,
        ]);

        $this->assertSame(38.5, $activeCategory->effectiveMarkupPercentage());
    }

    public function test_category_search_products_returns_correct_assignment_metadata(): void
    {
        $category = SellingPriceCategory::factory()->create(['name' => 'Target Category']);
        $otherCategory = SellingPriceCategory::factory()->create(['name' => 'Other Category']);
        $botanical = Category::factory()->create(['name' => 'Vegetables']);

        $p1 = Product::factory()->create([
            'name' => 'Cabbage Local',
            'sku' => 'CAB-001',
            'category_id' => $botanical->id,
            'selling_price_category_id' => $category->id,
        ]);

        $p2 = Product::factory()->create([
            'name' => 'Cabbage Chinese',
            'sku' => 'CAB-002',
            'category_id' => $botanical->id,
            'selling_price_category_id' => $otherCategory->id,
        ]);

        $p3 = Product::factory()->create([
            'name' => 'Cabbage Red',
            'sku' => 'CAB-003',
            'category_id' => $botanical->id,
            'selling_price_category_id' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.selling-price.categories.search-products', [
                'category' => $category->id,
                'q' => 'Cabbage',
            ]));

        $response->assertOk();
        $data = collect($response->json('data'));

        $item1 = $data->firstWhere('id', $p1->id);
        $this->assertTrue($item1['is_assigned_to_current']);
        $this->assertFalse($item1['is_assigned_to_other']);

        $item2 = $data->firstWhere('id', $p2->id);
        $this->assertFalse($item2['is_assigned_to_current']);
        $this->assertTrue($item2['is_assigned_to_other']);
        $this->assertSame('Other Category', $item2['current_category_name']);

        $item3 = $data->firstWhere('id', $p3->id);
        $this->assertFalse($item3['is_assigned_to_current']);
        $this->assertFalse($item3['is_assigned_to_other']);
        $this->assertNull($item3['current_category_name']);
    }

    public function test_shop_owner_pricing_reflects_category_percentage_in_capsule_5(): void
    {
        // Capsule 5: Shop Owner computes based on active category markup
        Carbon::setTestNow(Carbon::parse('2026-09-22 10:00:00', 'Asia/Kolkata'));

        BusinessSetting::query()->updateOrCreate(
            ['key' => 'selling_price_default_percentage'],
            ['value' => '25.0']
        );

        $priceGroup = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 0, 'is_active' => true]
        );

        $shop = Shop::factory()->create([
            'code' => 'SH02',
            'name' => 'Midtown Shop',
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

        $botanical = Category::factory()->create(['name' => 'Vegetables']);
        $highMarkupCat = SellingPriceCategory::factory()->create([
            'name' => 'Super High 80%',
            'markup_percentage' => 80.0,
            'is_active' => true,
        ]);

        // Product assigned to 80% category, in Capsule 5 Shop Owner computes based on category (80%)
        $product = Product::factory()->create([
            'category_id' => $botanical->id,
            'name' => 'Fresh Beetroot',
            'base_price' => 0.00,
            'vendor_price' => 0.00,
            'selling_price_category_id' => $highMarkupCat->id,
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

        $shopResponse = $this->actingAs($shopOwner)
            ->withSession(['shop_owner_active_shop_code' => $shop->code])
            ->get(route('shop-owner.products.index'));

        $shopResponse->assertOk();
        $shopResponse->assertSee('Fresh Beetroot');
        $shopResponse->assertSee('₹100');
        // ₹100 * 1.80 = ₹180 (category markup reflected)
        $shopResponse->assertSee('₹180');

        $products = $shopResponse->viewData('products');
        $firstProduct = $products->first();
        $this->assertEquals(180.00, $firstProduct['minimum_mrp']);

        Carbon::setTestNow();
    }
}
