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

final class SelectionV1FeatureTest extends TestCase
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
            'name' => 'Apple Red',
            'sku' => '1',
            'category_id' => $this->categoryFruit->id,
            'is_active' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('print.selection'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_is_forbidden(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('print.selection'));
        $this->assertTrue(in_array($response->status(), [302, 403], true));
    }

    public function test_authorized_user_can_view_selection_index(): void
    {
        $response = $this->actingAs($this->authorizedUser)->get(route('print.selection', ['date' => $this->testDate]));

        $response->assertOk();
        $response->assertSee('Selection V1');
        $response->assertSee('Shop Alpha');
        $response->assertSee('Shop Beta');
        $response->assertSee('Quality Grade:');
    }

    public function test_authorized_user_can_view_selection_print(): void
    {
        $response = $this->actingAs($this->authorizedUser)->get(route('print.selection.print', ['date' => $this->testDate]));

        $response->assertOk();
        $response->assertSee('Selection List');
        $response->assertSee('Print Selection');
    }

    public function test_selection_report_groups_by_shop(): void
    {
        $order1 = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);
        ShopOrderItem::create([
            'shop_order_id' => $order1->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 15,
            'approved_qty' => 15,
            'unit' => 'kg',
        ]);

        $order2 = ShopOrder::factory()->approved()->for($this->shop2)->create(['business_date' => $this->testDate]);
        ShopOrderItem::create([
            'shop_order_id' => $order2->id,
            'product_id' => $this->apple->id,
            'product_grade' => 'A',
            'requested_qty' => 8,
            'approved_qty' => 8,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->authorizedUser)->get(route('print.selection', ['date' => $this->testDate]));

        $response->assertOk();
        $shopSections = $response->viewData('shopSections');
        $this->assertCount(2, $shopSections);

        $shop1Section = collect($shopSections)->firstWhere('shop.id', $this->shop1->id);
        $this->assertNotNull($shop1Section);
        $this->assertEquals(15.0, $shop1Section['total_qty']);
        $this->assertCount(1, $shop1Section['items']);
        $this->assertEquals('Tomato Local', $shop1Section['items'][0]['name']);

        $shop2Section = collect($shopSections)->firstWhere('shop.id', $this->shop2->id);
        $this->assertNotNull($shop2Section);
        $this->assertEquals(8.0, $shop2Section['total_qty']);
        $this->assertCount(1, $shop2Section['items']);
        $this->assertEquals('Apple Red', $shop2Section['items'][0]['name']);
    }

    public function test_empty_shops_are_skipped_when_configured(): void
    {
        // Only Shop 1 has orders
        $order = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'kg',
        ]);

        // skip_empty_shops = 1 (default)
        $response = $this->actingAs($this->authorizedUser)->get(route('print.selection', [
            'date' => $this->testDate,
            'skip_empty_shops' => 1,
        ]));

        $response->assertOk();
        $shopSections = $response->viewData('shopSections');
        $this->assertCount(1, $shopSections);
        $this->assertEquals($this->shop1->id, $shopSections[0]['shop']->id);
    }

    public function test_empty_shops_are_retained_when_skip_empty_shops_is_off(): void
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

        $response = $this->actingAs($this->authorizedUser)->get(route('print.selection', [
            'date' => $this->testDate,
            'skip_empty_shops' => 0,
        ]));

        $response->assertOk();
        $shopSections = $response->viewData('shopSections');
        $this->assertCount(2, $shopSections);
    }

    public function test_category_filter_scopes_selection(): void
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

        // Filter only Vegetables category
        $response = $this->actingAs($this->authorizedUser)->get(route('print.selection', [
            'date' => $this->testDate,
            'category_ids' => [$this->categoryVeg->id],
        ]));

        $response->assertOk();
        $shopSections = $response->viewData('shopSections');
        $this->assertCount(1, $shopSections);
        $items = $shopSections[0]['items'];
        $this->assertCount(1, $items);
        $this->assertEquals('Tomato Local', $items[0]['name']);
    }

    public function test_product_filter_scopes_selection(): void
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
            'requested_qty' => 5,
            'approved_qty' => 5,
            'unit' => 'kg',
        ]);

        // Filter only cucumber
        $response = $this->actingAs($this->authorizedUser)->get(route('print.selection', [
            'date' => $this->testDate,
            'product_ids' => [$this->cucumber->id],
        ]));

        $response->assertOk();
        $shopSections = $response->viewData('shopSections');
        $items = $shopSections[0]['items'];
        $this->assertCount(1, $items);
        $this->assertEquals('Cucumber Green', $items[0]['name']);
    }

    public function test_shop_filter_scopes_selection(): void
    {
        $order1 = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);
        ShopOrderItem::create([
            'shop_order_id' => $order1->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'kg',
        ]);

        $order2 = ShopOrder::factory()->approved()->for($this->shop2)->create(['business_date' => $this->testDate]);
        ShopOrderItem::create([
            'shop_order_id' => $order2->id,
            'product_id' => $this->apple->id,
            'product_grade' => 'A',
            'requested_qty' => 5,
            'approved_qty' => 5,
            'unit' => 'kg',
        ]);

        // Filter only shop 2
        $response = $this->actingAs($this->authorizedUser)->get(route('print.selection', [
            'date' => $this->testDate,
            'shop_ids' => [$this->shop2->id],
        ]));

        $response->assertOk();
        $shopSections = $response->viewData('shopSections');
        $this->assertCount(1, $shopSections);
        $this->assertEquals($this->shop2->id, $shopSections[0]['shop']->id);
    }

    public function test_grade_filtering_grade_a(): void
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
            'requested_qty' => 20,
            'approved_qty' => 20,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->authorizedUser)->get(route('print.selection', [
            'date' => $this->testDate,
            'grades' => ['A'],
        ]));

        $response->assertOk();
        $shopSections = $response->viewData('shopSections');
        $this->assertCount(1, $shopSections);
        $this->assertEquals(10.0, $shopSections[0]['total_qty']);
        $this->assertEquals('GRADE A', $response->viewData('gradeHeading'));
    }

    public function test_grade_filtering_grade_b(): void
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
            'requested_qty' => 25,
            'approved_qty' => 25,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->authorizedUser)->get(route('print.selection', [
            'date' => $this->testDate,
            'grades' => ['B'],
        ]));

        $response->assertOk();
        $shopSections = $response->viewData('shopSections');
        $this->assertEquals(25.0, $shopSections[0]['total_qty']);
        $this->assertEquals('GRADE B', $response->viewData('gradeHeading'));
    }

    public function test_grade_filtering_grade_c(): void
    {
        $order = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'C',
            'requested_qty' => 7,
            'approved_qty' => 7,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->authorizedUser)->get(route('print.selection', [
            'date' => $this->testDate,
            'grades' => ['C'],
        ]));

        $response->assertOk();
        $shopSections = $response->viewData('shopSections');
        $this->assertEquals(7.0, $shopSections[0]['total_qty']);
        $this->assertEquals('GRADE C', $response->viewData('gradeHeading'));
    }

    public function test_grade_filtering_multiple_grades_keeps_quantities_separate(): void
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
            'requested_qty' => 15,
            'approved_qty' => 15,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->authorizedUser)->get(route('print.selection', [
            'date' => $this->testDate,
            'grades' => ['A', 'B'],
        ]));

        $response->assertOk();
        $shopSections = $response->viewData('shopSections');
        $this->assertCount(1, $shopSections);
        // Tomato has 2 separate rows: Grade A (10) and Grade B (15)
        $items = $shopSections[0]['items'];
        $this->assertCount(2, $items);
        $this->assertEquals('A', $items[0]['grade']);
        $this->assertEquals(10.0, $items[0]['qty']);
        $this->assertEquals('B', $items[1]['grade']);
        $this->assertEquals(15.0, $items[1]['qty']);
        $this->assertEquals('GRADES A, B', $response->viewData('gradeHeading'));
    }

    public function test_sku_sorting_natural_order(): void
    {
        // SKU 1 (Apple), SKU 2 (Cucumber), SKU 10 (Tomato)
        $order = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id, // SKU 10
            'product_grade' => 'A',
            'requested_qty' => 5,
            'approved_qty' => 5,
            'unit' => 'kg',
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->cucumber->id, // SKU 2
            'product_grade' => 'A',
            'requested_qty' => 5,
            'approved_qty' => 5,
            'unit' => 'kg',
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->apple->id, // SKU 1
            'product_grade' => 'A',
            'requested_qty' => 5,
            'approved_qty' => 5,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->authorizedUser)->get(route('print.selection', [
            'date' => $this->testDate,
            'code_sort' => 1,
        ]));

        $response->assertOk();
        $shopSections = $response->viewData('shopSections');
        $items = $shopSections[0]['items'];
        $this->assertCount(3, $items);
        // In natural SKU order: SKU 1 (Apple), SKU 2 (Cucumber), SKU 10 (Tomato)
        $this->assertEquals('1', $items[0]['code']);
        $this->assertEquals('2', $items[1]['code']);
        $this->assertEquals('10', $items[2]['code']);
    }

    public function test_code_sort_off_alphabetical_name_order(): void
    {
        // Names: Apple Red (SKU 1), Cucumber Green (SKU 2), Tomato Local (SKU 10)
        $order = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 5,
            'approved_qty' => 5,
            'unit' => 'kg',
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->cucumber->id,
            'product_grade' => 'A',
            'requested_qty' => 5,
            'approved_qty' => 5,
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

        $response = $this->actingAs($this->authorizedUser)->get(route('print.selection', [
            'date' => $this->testDate,
            'code_sort' => 0,
        ]));

        $response->assertOk();
        $shopSections = $response->viewData('shopSections');
        $items = $shopSections[0]['items'];
        $this->assertEquals('Apple Red', $items[0]['name']);
        $this->assertEquals('Cucumber Green', $items[1]['name']);
        $this->assertEquals('Tomato Local', $items[2]['name']);
    }

    public function test_category_headers_off_hides_category_headings_in_preview_and_print(): void
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

        // Preview with show_category_titles = 0
        $resPreview = $this->actingAs($this->authorizedUser)->get(route('print.selection', [
            'date' => $this->testDate,
            'show_category_titles' => 0,
        ]));
        $resPreview->assertOk();
        $resPreview->assertDontSee('Category: Vegetables');

        // Print with show_category_titles = 0
        $resPrint = $this->actingAs($this->authorizedUser)->get(route('print.selection.print', [
            'date' => $this->testDate,
            'show_category_titles' => 0,
        ]));
        $resPrint->assertOk();
        $resPrint->assertDontSee('Category: Vegetables');
    }

    public function test_category_headers_on_shows_category_headings(): void
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

        $resPreview = $this->actingAs($this->authorizedUser)->get(route('print.selection', [
            'date' => $this->testDate,
            'show_category_titles' => 1,
        ]));
        $resPreview->assertOk();
        $resPreview->assertSee('Category: Vegetables');

        $resPrint = $this->actingAs($this->authorizedUser)->get(route('print.selection.print', [
            'date' => $this->testDate,
            'show_category_titles' => 1,
        ]));
        $resPrint->assertOk();
        $resPrint->assertSee('Category: Vegetables');
    }

    public function test_show_code_off_hides_code_column(): void
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

        $resPrint = $this->actingAs($this->authorizedUser)->get(route('print.selection.print', [
            'date' => $this->testDate,
            'show_code' => 0,
        ]));
        $resPrint->assertOk();
        $resPrint->assertDontSee('<th>Code</th>', false);
    }

    public function test_selection_print_pagination_chunks_36_rows(): void
    {
        $order = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);

        // Create 40 products for shop 1
        for ($i = 1; $i <= 40; $i++) {
            $prod = Product::create([
                'name' => "Item Product {$i}",
                'sku' => (string) ($i + 50),
                'category_id' => $this->categoryVeg->id,
                'is_active' => true,
                'unit' => 'kg',
            ]);
            ShopOrderItem::create([
                'shop_order_id' => $order->id,
                'product_id' => $prod->id,
                'product_grade' => 'A',
                'requested_qty' => 1,
                'approved_qty' => 1,
                'unit' => 'kg',
            ]);
        }

        $response = $this->actingAs($this->authorizedUser)->get(route('print.selection.print', ['date' => $this->testDate]));

        $response->assertOk();
        $shopPages = $response->viewData('shopPages');
        // 40 items at 36 per page = 2 pages for Shop Alpha
        $this->assertCount(2, $shopPages);
        $this->assertEquals(1, $shopPages[0]['page_number']);
        $this->assertEquals(2, $shopPages[0]['total_pages']);
        $this->assertCount(36, $shopPages[0]['rows']);

        $this->assertEquals(2, $shopPages[1]['page_number']);
        $this->assertEquals(2, $shopPages[1]['total_pages']);
        $this->assertCount(4, $shopPages[1]['rows']);
    }

    public function test_sidebar_includes_selection_link(): void
    {
        $response = $this->actingAs($this->authorizedUser)->get(route('print.selection', ['date' => $this->testDate]));

        $response->assertOk();
        $response->assertSee(route('print.selection'));
        $response->assertSee(route('print.sort-sheet'));
    }

    public function test_selection_presets_and_batches_are_displayed_in_view(): void
    {
        $preset = SortSheetPreset::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->authorizedUser->id,
            'name' => 'Packing Selection 1',
            'surface' => 'selection',
            'sort_order' => 1,
            'category_ids' => [$this->categoryVeg->id],
        ]);

        $batch = SortSheetPresetBatch::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->authorizedUser->id,
            'name' => 'Selection Batch A',
            'preset_ids' => [$preset->uuid],
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.selection', ['date' => $this->testDate]));

        $response->assertOk();
        $this->assertTrue($response->viewData('presets')->contains('id', $preset->id));
        $this->assertTrue($response->viewData('presetBatches')->contains('id', $batch->id));
        $response->assertSee('Packing Selection 1');
        $response->assertSee('Selection Batch A');
        $response->assertSee(route('sort-sheet.presets.index'));
    }

    public function test_selection_preset_application_restores_stored_filters_and_filters_shop_sections(): void
    {
        $preset = SortSheetPreset::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->authorizedUser->id,
            'name' => 'Cucumbers Only Preset',
            'surface' => 'selection',
            'category_ids' => [$this->categoryVeg->id],
            'product_ids' => [$this->cucumber->id],
        ]);

        $order = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'unit' => 'kg',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->cucumber->id,
            'product_grade' => 'A',
            'requested_qty' => 15.0,
            'approved_qty' => 15.0,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.selection', [
                'date' => $this->testDate,
                'preset_id' => $preset->uuid,
            ]));

        $response->assertOk();
        $shopSections = $response->viewData('shopSections');
        $this->assertCount(1, $shopSections);
        $items = $shopSections[0]['items'];
        $this->assertCount(1, $items);
        $this->assertEquals($this->cucumber->id, $items[0]['product_id']);

        $filters = $response->viewData('filters');
        $this->assertEquals($preset->uuid, $filters['selected_preset']);
        $this->assertEquals([$this->categoryVeg->id], $filters['category_ids']);
        $this->assertEquals([$this->cucumber->id], $filters['product_ids']);
    }

    public function test_selection_unauthorized_user_cannot_apply_other_users_private_preset(): void
    {
        $otherUser = User::factory()->create();
        $privatePreset = SortSheetPreset::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $otherUser->id,
            'name' => 'Other User Private Preset',
            'surface' => 'selection',
            'category_ids' => [$this->categoryVeg->id],
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.selection', [
                'date' => $this->testDate,
                'preset_id' => $privatePreset->uuid,
            ]));

        $response->assertOk();
        $filters = $response->viewData('filters');
        $this->assertNull($filters['selected_preset'] ?? null);
        $this->assertEmpty($filters['category_ids'] ?? []);
    }

    public function test_selection_preset_application_is_strictly_read_only_and_does_not_mutate_database(): void
    {
        $preset = SortSheetPreset::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->authorizedUser->id,
            'name' => 'Read Only Selection Preset',
            'surface' => 'selection',
            'category_ids' => [$this->categoryVeg->id],
        ]);

        $presetCountBefore = SortSheetPreset::count();
        $presetBatchCountBefore = SortSheetPresetBatch::count();
        $presetUpdatedBefore = $preset->fresh()->updated_at;

        $this->actingAs($this->authorizedUser)
            ->get(route('print.selection', [
                'date' => $this->testDate,
                'preset_id' => $preset->uuid,
            ]))
            ->assertOk();

        $this->assertSame($presetCountBefore, SortSheetPreset::count());
        $this->assertSame($presetBatchCountBefore, SortSheetPresetBatch::count());
        $this->assertEquals($presetUpdatedBefore, $preset->fresh()->updated_at);
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
            'requested_qty' => 35.0,
            'approved_qty' => 35.0,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.selection', ['date' => $historicalDate]));

        $response->assertOk();
        $response->assertSee('Apply Filters');
        $response->assertSee($historicalDate);
        $shopSections = $response->viewData('shopSections');
        $this->assertNotEmpty($shopSections);
        $this->assertEquals(35.0, $shopSections[0]['total_qty']);

        $printResponse = $this->actingAs($this->authorizedUser)
            ->get(route('print.selection.print', ['date' => $historicalDate]));

        $printResponse->assertOk();
        $printResponse->assertSee('Tomato');
    }

    public function test_filter_form_has_no_nested_forms_and_includes_submit_button(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.selection', ['date' => $this->testDate]));

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
