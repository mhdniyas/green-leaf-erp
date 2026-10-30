<?php

declare(strict_types=1);

namespace Tests\Feature\Printing;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\SortSheetPreset;
use App\Models\SortSheetPresetBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class ShopOrderV1FeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $authorizedUser;

    private User $unauthorizedUser;

    private Shop $shop1;

    private Shop $shop2;

    private Category $categoryVeg;

    private Category $categoryFruit;

    private Product $tomato;

    private Product $cucumber;

    private Product $apple;

    private string $testDate = '2026-10-30';

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorizedUser = User::factory()->create();
        $this->authorizedUser->givePermissionTo(
            Permission::findOrCreate('sort.sheet.view'),
            Permission::findOrCreate('sort.sheet.generate'),
            Permission::findOrCreate('sort.sheet.export'),
        );

        $this->unauthorizedUser = User::factory()->create();

        $this->categoryVeg = Category::factory()->create(['name' => 'Vegetables', 'is_active' => true]);
        $this->categoryFruit = Category::factory()->create(['name' => 'Fruits', 'is_active' => true]);

        $this->shop1 = Shop::factory()->create(['name' => 'Shop Alpha', 'status' => 'active', 'warehouse_tag' => 'TAG-A']);
        $this->shop2 = Shop::factory()->create(['name' => 'Shop Beta', 'status' => 'active', 'warehouse_tag' => 'TAG-B']);

        $this->tomato = Product::factory()->create([
            'name' => 'Tomato Local',
            'sku' => '10',
            'category_id' => $this->categoryVeg->id,
            'is_active' => true,
        ]);

        $this->cucumber = Product::factory()->create([
            'name' => 'Cucumber Green',
            'sku' => '2',
            'category_id' => $this->categoryVeg->id,
            'is_active' => true,
        ]);

        $this->apple = Product::factory()->create([
            'name' => 'Apple Royal',
            'sku' => '1',
            'category_id' => $this->categoryFruit->id,
            'is_active' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('print.shop-order'));
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_user_is_forbidden(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('print.shop-order'));
        $this->assertTrue(in_array($response->status(), [302, 403], true));
    }

    public function test_authorized_user_can_access_shop_order_v1(): void
    {
        $response = $this->actingAs($this->authorizedUser)->get(route('print.shop-order'));

        $response->assertOk();
        $response->assertViewIs('print-v1.shop-order.index');
        $response->assertSee('Shop Order V1');
        $response->assertSee('Daily Custom Presets:');
    }

    public function test_legacy_shop_wise_portrait_redirects_to_shop_order(): void
    {
        $response = $this->actingAs($this->authorizedUser)->get(route('print.shop-wise-portrait', [
            'date' => $this->testDate,
            'warehouse_id' => 1,
        ]));

        $response->assertRedirect(route('print.shop-order', [
            'date' => $this->testDate,
            'warehouse_id' => 1,
        ]));
        $this->assertEquals(301, $response->getStatusCode());
    }

    public function test_default_filter_selects_grade_a_only(): void
    {
        $response = $this->actingAs($this->authorizedUser)->get(route('print.shop-order'));

        $response->assertOk();
        $this->assertEquals(['A'], $response->viewData('selectedGrades'));
        $this->assertEquals('GRADE A', $response->viewData('gradeHeading'));
    }

    public function test_filter_category_isolation(): void
    {
        $order = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'kg',
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->apple->id,
            'product_grade' => 'A',
            'requested_qty' => 5,
            'approved_qty' => 5,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->authorizedUser)->get(route('print.shop-order', [
            'date' => $this->testDate,
            'category_ids' => [$this->categoryFruit->id],
        ]));

        $response->assertOk();
        $sections = $response->viewData('shopSections');
        $this->assertCount(1, $sections);
        $itemProductIds = collect($sections[0]['rows'])->pluck('product_id')->all();
        $this->assertContains($this->apple->id, $itemProductIds);
        $this->assertNotContains($this->tomato->id, $itemProductIds);
    }

    public function test_filter_product_isolation(): void
    {
        $order = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'kg',
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->cucumber->id,
            'product_grade' => 'A',
            'requested_qty' => 8,
            'approved_qty' => 8,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->authorizedUser)->get(route('print.shop-order', [
            'date' => $this->testDate,
            'product_ids' => [$this->tomato->id],
        ]));

        $response->assertOk();
        $sections = $response->viewData('shopSections');
        $this->assertCount(1, $sections);
        $itemProductIds = collect($sections[0]['rows'])->pluck('product_id')->all();
        $this->assertContains($this->tomato->id, $itemProductIds);
        $this->assertNotContains($this->cucumber->id, $itemProductIds);
    }

    public function test_filter_shop_multi_selection(): void
    {
        $order1 = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);
        $order2 = ShopOrder::factory()->approved()->for($this->shop2)->create(['business_date' => $this->testDate]);

        ShopOrderItem::create([
            'shop_order_id' => $order1->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 12,
            'approved_qty' => 12,
            'unit' => 'kg',
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $order2->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 8,
            'approved_qty' => 8,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->authorizedUser)->get(route('print.shop-order', [
            'date' => $this->testDate,
            'shop_ids' => [$this->shop1->id],
        ]));

        $response->assertOk();
        $sections = $response->viewData('shopSections');
        $this->assertCount(1, $sections);
        $this->assertEquals($this->shop1->id, $sections[0]['shop']->id);
    }

    public function test_grade_combinations_and_separation(): void
    {
        $order = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'kg',
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'requested_qty' => 7,
            'approved_qty' => 7,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->authorizedUser)->get(route('print.shop-order', [
            'date' => $this->testDate,
            'grades' => ['A', 'B'],
        ]));

        $response->assertOk();
        $sections = $response->viewData('shopSections');
        $this->assertCount(1, $sections);
        $rows = $sections[0]['rows'];
        $this->assertCount(2, $rows);
        $this->assertEquals(10, $rows[0]['qty']);
        $this->assertEquals('A', $rows[0]['grade']);
        $this->assertEquals(7, $rows[1]['qty']);
        $this->assertEquals('B', $rows[1]['grade']);
        $this->assertEquals('GRADES A, B', $response->viewData('gradeHeading'));
    }

    public function test_shop_order_print_view_renders_portrait_layout(): void
    {
        $order = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->authorizedUser)->get(route('print.shop-order.print', ['date' => $this->testDate]));

        $response->assertOk();
        $response->assertViewIs('print-v1.shop-order.print');
        $response->assertSee('Shop Alpha');
        $response->assertSee('TAG-A');
        $response->assertSee('Tomato Local');
        $response->assertSee('10');
    }

    public function test_shop_order_presets_and_batches_displayed_in_view(): void
    {
        $preset = SortSheetPreset::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->authorizedUser->id,
            'name' => 'Shop Order Preset',
            'surface' => 'portrait',
            'sort_order' => 1,
            'category_ids' => [$this->categoryVeg->id],
        ]);

        $batch = SortSheetPresetBatch::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->authorizedUser->id,
            'name' => 'Shop Order Batch',
            'preset_ids' => [$preset->uuid],
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.shop-order', ['date' => $this->testDate]));

        $response->assertOk();
        $this->assertTrue($response->viewData('presets')->contains('id', $preset->id));
        $this->assertTrue($response->viewData('presetBatches')->contains('id', $batch->id));
        $response->assertSee('Shop Order Preset');
        $response->assertSee('Shop Order Batch');
        $response->assertSee(route('sort-sheet.presets.index'));
    }

    public function test_shop_order_preset_application_restores_stored_filters(): void
    {
        $preset = SortSheetPreset::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->authorizedUser->id,
            'name' => 'Apple Preset',
            'surface' => 'portrait',
            'category_ids' => [$this->categoryFruit->id],
            'product_ids' => [$this->apple->id],
        ]);

        $order = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'kg',
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->apple->id,
            'product_grade' => 'A',
            'requested_qty' => 5,
            'approved_qty' => 5,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.shop-order', [
                'date' => $this->testDate,
                'preset_id' => $preset->uuid,
            ]));

        $response->assertOk();
        $sections = $response->viewData('shopSections');
        $this->assertCount(1, $sections);
        $this->assertEquals($this->apple->id, $sections[0]['rows'][0]['product_id']);

        $filters = $response->viewData('filters');
        $this->assertEquals($preset->uuid, $filters['selected_preset']);
    }

    public function test_sidebar_includes_all_four_v1_links(): void
    {
        $response = $this->actingAs($this->authorizedUser)->get(route('print.shop-order', ['date' => $this->testDate]));

        $response->assertOk();
        $response->assertSee(route('print.sort-sheet'));
        $response->assertSee(route('print.selection'));
        $response->assertSee(route('print.segregation-grid'));
        $response->assertSee(route('print.shop-order'));
    }

    public function test_can_filter_and_preview_historical_past_date(): void
    {
        $historicalDate = '2026-10-25';

        $historicalOrder = ShopOrder::factory()->approved()->for($this->shop1)->create([
            'business_date' => $historicalDate,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $historicalOrder->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 60.0,
            'approved_qty' => 60.0,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.shop-order', ['date' => $historicalDate]));

        $response->assertOk();
        $response->assertSee('Apply Filters');
        $response->assertSee($historicalDate);
        $sections = $response->viewData('shopSections');
        $this->assertNotEmpty($sections);
        $this->assertEquals(60.0, $sections[0]['total_qty']);

        $printResponse = $this->actingAs($this->authorizedUser)
            ->get(route('print.shop-order.print', ['date' => $historicalDate]));

        $printResponse->assertOk();
        $printResponse->assertSee('Tomato');
    }

    public function test_filter_form_has_no_nested_forms_and_includes_submit_button(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.shop-order', ['date' => $this->testDate]));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('id="v1-filter-form"', $content);
        $this->assertStringContainsString('Apply Filters', $content);

        $formStart = strpos($content, 'id="v1-filter-form"');
        $formEnd = strpos($content, '</form>', $formStart);
        $formBlock = substr($content, $formStart, $formEnd - $formStart);

        $this->assertStringNotContainsString('<form', $formBlock, 'There should be no nested <form> tags inside #v1-filter-form');
        $this->assertStringContainsString('type="submit"', $formBlock);
        $this->assertStringContainsString('Apply Filters', $formBlock);
    }
}
