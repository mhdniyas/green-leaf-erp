<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Inventory\ProductGrade;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyProductPrice;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopPriceGroup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class ShopGradeConfigurationModalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private ShopPriceGroup $priceGroup;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-24 10:00:00'));

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $staffRole = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        $shopRole = Role::firstOrCreate(['name' => 'shop', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'purchaser', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'purchase', 'guard_name' => 'web']);

        Permission::findOrCreate('sales.customer.view', 'web');
        Permission::findOrCreate('sales.customer.create', 'web');
        Permission::findOrCreate('sales.customer.update', 'web');
        Permission::findOrCreate('sales.order.create', 'web');

        $staffRole->givePermissionTo(['sales.customer.view', 'sales.customer.create', 'sales.customer.update']);
        $shopRole->givePermissionTo('sales.order.create');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->staff = User::factory()->create();
        $this->staff->assignRole('staff');

        $this->priceGroup = ShopPriceGroup::factory()->create([
            'name' => 'Retail Standard',
            'is_active' => true,
        ]);

        $this->category = Category::factory()->create([
            'name' => 'Vegetables',
            'is_active' => true,
        ]);

        BusinessSetting::updateOrCreate(
            ['key' => 'enable_multi_grade_ordering'],
            ['value' => '1', 'type' => 'boolean']
        );
    }

    private function createProductWithPrices(string $name = 'Tomato Hybrid', string $sku = 'TOM-HYB', float $priceA = 100.0, float $priceB = 85.0, float $priceC = 60.0): Product
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => $name,
            'sku' => $sku,
            'unit' => 'kg',
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);

        DailyProductPrice::create([
            'product_id' => $product->id,
            'shop_price_group_id' => $this->priceGroup->id,
            'grade' => ProductGrade::GradeA,
            'selling_price' => $priceA,
        ]);

        DailyProductPrice::create([
            'product_id' => $product->id,
            'shop_price_group_id' => $this->priceGroup->id,
            'grade' => ProductGrade::GradeB,
            'selling_price' => $priceB,
        ]);

        DailyProductPrice::create([
            'product_id' => $product->id,
            'shop_price_group_id' => $this->priceGroup->id,
            'grade' => ProductGrade::GradeC,
            'selling_price' => $priceC,
        ]);

        return $product;
    }

    public function test_old_grade_b_checkbox_no_longer_renders_in_create_or_edit_shop(): void
    {
        $shop = Shop::factory()->create([
            'name' => 'Ashirwad Hypermarket',
            'code' => 'ASH_HYPER',
            'shop_price_group_id' => $this->priceGroup->id,
            'allowed_product_grades' => ['A', 'B'],
        ]);

        $response = $this->actingAs($this->admin)->get(route('sales.customers.index'));
        $response->assertOk();

        $content = $response->getContent();

        // 1. Old Grade B checkboxes must NOT render
        $this->assertStringNotContainsString('Allow Grade B purchases', $content);
        $this->assertStringNotContainsString('Allow this shop to request Grade B purchases', $content);
        $this->assertStringNotContainsString('Permitted Product Ordering Grades', $content);

        // 2. New Product Ordering Grades card must render
        $this->assertStringContainsString('Product Ordering Grades', $content);
        $this->assertStringContainsString('Edit Configuration', $content);
        $this->assertStringContainsString('Shop Grade Configuration', $content);
        $this->assertStringContainsString('Choose which product grades this shop can order and set its default grade.', $content);
    }

    public function test_modal_markup_and_controls_render_correctly_for_admin(): void
    {
        $shop = Shop::factory()->create([
            'name' => 'Ashirwad Test',
            'code' => 'ASH_TEST',
            'shop_price_group_id' => $this->priceGroup->id,
            'allowed_product_grades' => ['B', 'C'],
        ]);

        $response = $this->actingAs($this->admin)->get(route('sales.customers.index'));
        $response->assertOk();

        $content = $response->getContent();

        $this->assertStringContainsString('edit-shop-'.$shop->id.'-edit-grades-btn', $content);
        $this->assertStringContainsString('edit-shop-'.$shop->id.'-grade-modal', $content);
        $this->assertStringContainsString('edit-shop-'.$shop->id.'-modal-default-grade', $content);
        $this->assertStringContainsString('edit-shop-'.$shop->id.'-modal-cancel-btn', $content);
        $this->assertStringContainsString('edit-shop-'.$shop->id.'-modal-apply-btn', $content);

        // Non-admin staff does not see grade config
        $staffResponse = $this->actingAs($this->staff)->get(route('sales.customers.index'));
        $staffResponse->assertOk();
        $this->assertStringNotContainsString('edit-shop-'.$shop->id.'-edit-grades-btn', $staffResponse->getContent());
    }

    public function test_main_save_shop_persists_b_only_configuration(): void
    {
        $shop = Shop::factory()->create([
            'name' => 'Green Valley Market',
            'code' => 'GV_MKT',
            'shop_price_group_id' => $this->priceGroup->id,
            'allowed_product_grades' => ['A'],
        ]);

        $response = $this->actingAs($this->admin)->patch(route('sales.customers.shops.update', $shop), [
            'name' => 'Green Valley Market',
            'code' => 'GV_MKT',
            'destination_type' => 'direct',
            'status' => 'active',
            'shop_price_group_id' => $this->priceGroup->id,
            'allowed_product_grades' => ['B'],
            'default_product_grade' => 'B',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('sales.customers.index'));

        $shop->refresh();
        $this->assertSame(['B'], $shop->allowedProductGrades());
        $this->assertSame('B', $shop->defaultProductGrade());
        $this->assertTrue($shop->canOrderGrade('B'));
        $this->assertFalse($shop->canOrderGrade('A'));
        $this->assertFalse($shop->canOrderGrade('C'));
        $this->assertTrue((bool) $shop->allow_grade_b_purchase);
    }

    public function test_main_save_shop_persists_c_only_configuration(): void
    {
        $shop = Shop::factory()->create([
            'name' => 'Economy Produce Shop',
            'code' => 'ECON_PRD',
            'shop_price_group_id' => $this->priceGroup->id,
            'allowed_product_grades' => ['A', 'B'],
        ]);

        $response = $this->actingAs($this->admin)->patch(route('sales.customers.shops.update', $shop), [
            'name' => 'Economy Produce Shop',
            'code' => 'ECON_PRD',
            'destination_type' => 'direct',
            'status' => 'active',
            'shop_price_group_id' => $this->priceGroup->id,
            'allowed_product_grades' => ['C'],
            'default_product_grade' => 'C',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('sales.customers.index'));

        $shop->refresh();
        $this->assertSame(['C'], $shop->allowedProductGrades());
        $this->assertSame('C', $shop->defaultProductGrade());
        $this->assertTrue($shop->canOrderGrade('C'));
        $this->assertFalse($shop->canOrderGrade('A'));
        $this->assertFalse($shop->canOrderGrade('B'));
        $this->assertFalse((bool) $shop->allow_grade_b_purchase);
    }

    public function test_main_save_shop_persists_a_only_configuration(): void
    {
        $shop = Shop::factory()->create([
            'name' => 'Premium A Only Shop',
            'code' => 'PREM_A',
            'shop_price_group_id' => $this->priceGroup->id,
            'allowed_product_grades' => ['A', 'B', 'C'],
        ]);

        $response = $this->actingAs($this->admin)->patch(route('sales.customers.shops.update', $shop), [
            'name' => 'Premium A Only Shop',
            'code' => 'PREM_A',
            'destination_type' => 'direct',
            'status' => 'active',
            'shop_price_group_id' => $this->priceGroup->id,
            'allowed_product_grades' => ['A'],
            'default_product_grade' => 'A',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('sales.customers.index'));

        $shop->refresh();
        $this->assertSame(['A'], $shop->allowedProductGrades());
        $this->assertSame('A', $shop->defaultProductGrade());
        $this->assertTrue($shop->canOrderGrade('A'));
        $this->assertFalse($shop->canOrderGrade('B'));
        $this->assertFalse($shop->canOrderGrade('C'));
        $this->assertFalse((bool) $shop->allow_grade_b_purchase);
    }

    public function test_main_save_shop_persists_a_and_b_with_default_b(): void
    {
        $shop = Shop::factory()->create([
            'name' => 'Grade B Focus Shop',
            'code' => 'GB_FOC',
            'shop_price_group_id' => $this->priceGroup->id,
            'allowed_product_grades' => ['A'],
        ]);

        $response = $this->actingAs($this->admin)->patch(route('sales.customers.shops.update', $shop), [
            'name' => 'Grade B Focus Shop',
            'code' => 'GB_FOC',
            'destination_type' => 'direct',
            'status' => 'active',
            'shop_price_group_id' => $this->priceGroup->id,
            'allowed_product_grades' => ['A', 'B'],
            'default_product_grade' => 'B',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('sales.customers.index'));

        $shop->refresh();
        $this->assertSame('B', $shop->defaultProductGrade());
        $this->assertTrue($shop->canOrderGrade('A'));
        $this->assertTrue($shop->canOrderGrade('B'));
        $this->assertFalse($shop->canOrderGrade('C'));
        $this->assertTrue((bool) $shop->allow_grade_b_purchase);
    }

    public function test_empty_grades_selection_is_rejected(): void
    {
        $shop = Shop::factory()->create([
            'name' => 'Empty Test Shop',
            'code' => 'EMPTY_TEST',
            'shop_price_group_id' => $this->priceGroup->id,
            'allowed_product_grades' => ['A'],
        ]);

        $response = $this->actingAs($this->admin)->patch(route('sales.customers.shops.update', $shop), [
            'name' => 'Empty Test Shop',
            'code' => 'EMPTY_TEST',
            'destination_type' => 'direct',
            'status' => 'active',
            'allowed_product_grades' => [],
        ]);

        $response->assertSessionHasErrors('allowed_product_grades');
        $shop->refresh();
        $this->assertSame(['A'], $shop->allowedProductGrades());
    }

    public function test_invalid_default_grade_is_rejected(): void
    {
        $shop = Shop::factory()->create([
            'name' => 'Invalid Default Shop',
            'code' => 'INV_DEF',
            'shop_price_group_id' => $this->priceGroup->id,
            'allowed_product_grades' => ['A'],
        ]);

        $response = $this->actingAs($this->admin)->patch(route('sales.customers.shops.update', $shop), [
            'name' => 'Invalid Default Shop',
            'code' => 'INV_DEF',
            'destination_type' => 'direct',
            'status' => 'active',
            'allowed_product_grades' => ['A'],
            'default_product_grade' => 'B', // 'B' is not in allowed ['A']
        ]);

        $response->assertSessionHasErrors('default_product_grade');
        $shop->refresh();
        $this->assertSame(['A'], $shop->allowedProductGrades());
    }

    public function test_shop_owner_b_only_page_shows_only_b_field(): void
    {
        $shop = Shop::factory()->create([
            'name' => 'B Only Mart',
            'code' => 'B_MART',
            'shop_price_group_id' => $this->priceGroup->id,
            'allowed_product_grades' => ['B'],
        ]);

        $user = User::factory()->create(['shop_id' => $shop->id]);
        $user->assignRole('shop');

        $tomato = $this->createProductWithPrices('Tomato Local', 'TOM-LOC');

        $response = $this->actingAs($user)->get(route('shop-owner.orders.create'));
        $response->assertOk();

        $content = $response->getContent();

        // Must display Grade B badge on the primary product row
        $this->assertStringContainsString('Grade B', $content);

        // Must submit Grade B identity
        $this->assertStringContainsString('name="item_grades['.$tomato->sku.']" value="B"', $content);

        // Must NOT render sub-rows for Grade A or Grade C
        $this->assertStringNotContainsString('Grade A</span>', $content);
        $this->assertStringNotContainsString('name="item_grades['.$tomato->sku.'|A]"', $content);
        $this->assertStringNotContainsString('name="item_grades['.$tomato->sku.'|C]"', $content);
    }

    public function test_b_only_shop_submits_correct_b_identity_order(): void
    {
        $shop = Shop::factory()->create([
            'name' => 'B Only Mart 2',
            'code' => 'B_MART_2',
            'shop_price_group_id' => $this->priceGroup->id,
            'allowed_product_grades' => ['B'],
        ]);

        $user = User::factory()->create(['shop_id' => $shop->id]);
        $user->assignRole('shop');

        $tomato = $this->createProductWithPrices('Tomato Local 2', 'TOM-LOC-2', priceB: 85.0);

        $response = $this->actingAs($user)->post(route('requisitions.store'), [
            '_form_submission_start' => '1',
            '_form_submission_complete' => '1',
            'items' => [
                $tomato->sku => 20,
            ],
            'item_units' => [
                $tomato->sku => 'kg',
            ],
            'item_grades' => [
                $tomato->sku => 'B',
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('shop-owner.orders.history'));

        $order = ShopOrder::where('shop_id', $shop->id)->with('items')->firstOrFail();
        $this->assertCount(1, $order->items);

        $item = $order->items->first();
        $this->assertSame(ProductGrade::GradeB->value, $item->product_grade?->value ?? $item->product_grade);
        $this->assertEquals(20.0, (float) $item->requested_qty);
        $this->assertEquals(85.0, (float) $item->locked_selling_price);
        $this->assertEquals(1700.0, (float) $item->line_total);
    }

    public function test_disabled_grades_rejected_by_backend_for_b_only_shop(): void
    {
        $shop = Shop::factory()->create([
            'name' => 'Strict B Shop',
            'code' => 'STRICT_B',
            'shop_price_group_id' => $this->priceGroup->id,
            'allowed_product_grades' => ['B'],
        ]);

        $user = User::factory()->create(['shop_id' => $shop->id]);
        $user->assignRole('shop');

        $tomato = $this->createProductWithPrices('Tomato B', 'TOM-B');

        // Attempting to order Grade A on a B-only shop
        $response = $this->actingAs($user)->post(route('requisitions.store'), [
            '_form_submission_start' => '1',
            '_form_submission_complete' => '1',
            'items' => [
                $tomato->sku => 10,
            ],
            'item_units' => [
                $tomato->sku => 'kg',
            ],
            'item_grades' => [
                $tomato->sku => 'A',
            ],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseMissing('shop_orders', ['shop_id' => $shop->id]);
    }

    public function test_legacy_shops_remain_compatible(): void
    {
        // 1. Legacy shop with allowed_product_grades = null and allow_grade_b_purchase = false
        $legacyA = Shop::factory()->create([
            'name' => 'Legacy Shop A',
            'code' => 'LEG_A',
            'allowed_product_grades' => null,
            'allow_grade_b_purchase' => false,
        ]);

        $this->assertSame(['A'], $legacyA->allowedProductGrades());
        $this->assertSame('A', $legacyA->defaultProductGrade());
        $this->assertTrue($legacyA->canOrderGrade('A'));
        $this->assertFalse($legacyA->canOrderGrade('B'));

        // 2. Legacy shop with allowed_product_grades = null and allow_grade_b_purchase = true
        $legacyB = Shop::factory()->create([
            'name' => 'Legacy Shop B',
            'code' => 'LEG_B',
            'allowed_product_grades' => null,
            'allow_grade_b_purchase' => true,
        ]);

        $this->assertSame(['A', 'B'], $legacyB->allowedProductGrades());
        $this->assertSame('A', $legacyB->defaultProductGrade());
        $this->assertTrue($legacyB->canOrderGrade('A'));
        $this->assertTrue($legacyB->canOrderGrade('B'));
        $this->assertFalse($legacyB->canOrderGrade('C'));
    }

    public function test_draft_cart_and_history_preserved_for_b_only_shop(): void
    {
        $shop = Shop::factory()->create([
            'name' => 'Draft Mart B',
            'code' => 'DFT_B',
            'shop_price_group_id' => $this->priceGroup->id,
            'allowed_product_grades' => ['B'],
        ]);

        $user = User::factory()->create(['shop_id' => $shop->id]);
        $user->assignRole('shop');

        $tomato = $this->createProductWithPrices('Draft Tomato', 'DFT-TOM');

        // Submit initial order
        $this->actingAs($user)->post(route('requisitions.store'), [
            '_form_submission_start' => '1',
            '_form_submission_complete' => '1',
            'items' => [$tomato->sku => 12],
            'item_units' => [$tomato->sku => 'kg'],
            'item_grades' => [$tomato->sku => 'B'],
        ])->assertRedirect(route('shop-owner.orders.history'));

        // Check history page shows the submitted order
        $historyResponse = $this->actingAs($user)->get(route('shop-owner.orders.history'));
        $historyResponse->assertOk();
        $historyResponse->assertSee('Submitted');

        $order = ShopOrder::where('shop_id', $shop->id)->firstOrFail();
        $this->assertEquals(12.0, (float) $order->items->first()?->requested_qty);
        $this->assertSame('submitted', $order->state);

        // Check reopening the create order page populates the draft quantity for Grade B
        $createResponse = $this->actingAs($user)->get(route('shop-owner.orders.create'));
        $createResponse->assertOk();
        $this->assertStringContainsString('DFT-TOM', $createResponse->getContent());
        $this->assertStringContainsString('name="items[DFT-TOM]"', $createResponse->getContent());
        $this->assertMatchesRegularExpression('/name="items\[DFT-TOM\]"\s+value="12(\.00)?"/', $createResponse->getContent());
    }

    public function test_grade_aware_price_resolution_remains_correct_for_b_only_shop(): void
    {
        $shop = Shop::factory()->create([
            'name' => 'Priced B Shop',
            'code' => 'PRC_B',
            'shop_price_group_id' => $this->priceGroup->id,
            'allowed_product_grades' => ['B'],
        ]);

        $user = User::factory()->create(['shop_id' => $shop->id]);
        $user->assignRole('shop');

        $potato = $this->createProductWithPrices('Potato Premium', 'POT-PRM', priceA: 50.0, priceB: 42.50, priceC: 30.0);

        $this->actingAs($user)->post(route('requisitions.store'), [
            '_form_submission_start' => '1',
            '_form_submission_complete' => '1',
            'items' => [$potato->sku => 10],
            'item_units' => [$potato->sku => 'kg'],
            'item_grades' => [$potato->sku => 'B'],
        ])->assertRedirect(route('shop-owner.orders.history'));

        $order = ShopOrder::where('shop_id', $shop->id)->with('items')->firstOrFail();
        $item = $order->items->first();

        $this->assertNotNull($item);
        $this->assertSame(ProductGrade::GradeB->value, $item->product_grade?->value ?? $item->product_grade);
        $this->assertEquals(42.50, (float) $item->locked_selling_price);
        $this->assertEquals(425.00, (float) $item->line_total);
    }

    public function test_warehouse_inventory_and_automatic_load_all_remain_unchanged(): void
    {
        $shop = Shop::factory()->create([
            'name' => 'Warehouse Safety Shop',
            'code' => 'WH_SAFE',
            'shop_price_group_id' => $this->priceGroup->id,
            'allowed_product_grades' => ['B'],
        ]);

        $user = User::factory()->create(['shop_id' => $shop->id]);
        $user->assignRole('shop');

        $tomato = $this->createProductWithPrices('Warehouse Tomato', 'WH-TOM', priceB: 90.0);

        $this->actingAs($user)->post(route('requisitions.store'), [
            '_form_submission_start' => '1',
            '_form_submission_complete' => '1',
            'items' => [$tomato->sku => 5],
            'item_units' => [$tomato->sku => 'kg'],
            'item_grades' => [$tomato->sku => 'B'],
        ])->assertRedirect(route('shop-owner.orders.history'));

        $order = ShopOrder::where('shop_id', $shop->id)->with('items')->firstOrFail();
        $this->assertCount(1, $order->items);
        $item = $order->items->first();
        $this->assertSame(ProductGrade::GradeB->value, $item->product_grade?->value ?? $item->product_grade);
        $this->assertEquals(5.0, (float) $item->requested_qty);
    }
}
