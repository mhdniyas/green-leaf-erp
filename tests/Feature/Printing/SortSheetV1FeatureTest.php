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

final class SortSheetV1FeatureTest extends TestCase
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
            'sku' => 'V-100',
            'category_id' => $this->categoryVeg->id,
            'is_active' => true,
        ]);

        $this->cucumber = Product::factory()->create([
            'name' => 'Cucumber Green',
            'sku' => 'V-050',
            'category_id' => $this->categoryVeg->id,
            'is_active' => true,
        ]);

        $this->apple = Product::factory()->create([
            'name' => 'Apple Red',
            'sku' => 'F-010',
            'category_id' => $this->categoryFruit->id,
            'is_active' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('print.sort-sheet'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_is_forbidden(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('print.sort-sheet'));

        // If app redirects unauthorized users to home or dashboard with an error, or returns 403
        $this->assertTrue(in_array($response->status(), [302, 403], true));
    }

    public function test_dashboard_redirects_to_sort_sheet_v1(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.dashboard'));

        $response->assertRedirect(route('print.sort-sheet'));
    }

    public function test_authorized_user_can_access_sort_sheet_v1(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet'));

        $response->assertOk();
        $response->assertSee('Sort Sheet V1');
        $response->assertSee('Quality Grade:');
        $response->assertSee('id="grade-filter-a"', false);
        $response->assertSee('id="grade-filter-b"', false);
        $response->assertSee('id="grade-filter-c"', false);
    }

    public function test_default_filter_selects_grade_a_only(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet'));

        $response->assertOk();
        $response->assertViewHas('selectedGrades', ['A']);
        $response->assertViewHas('gradeHeading', 'GRADE A');
    }

    public function test_filter_1_category_isolation(): void
    {
        $order = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 15,
            'approved_qty' => 15,
            'unit' => 'kg',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->apple->id,
            'product_grade' => 'A',
            'requested_qty' => 20,
            'approved_qty' => 20,
            'unit' => 'kg',
        ]);

        // Filter only Vegetables category
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', [
                'date' => $this->testDate,
                'category_ids' => [$this->categoryVeg->id],
            ]));

        $response->assertOk();
        $matrix = $response->viewData('matrix');
        $this->assertArrayHasKey("{$this->tomato->id}:A", $matrix);
        $this->assertArrayNotHasKey("{$this->apple->id}:A", $matrix);
        $this->assertEquals(15.0, $response->viewData('grandTotal'));
    }

    public function test_filter_2_product_isolation(): void
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

        // Filter only cucumber
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', [
                'date' => $this->testDate,
                'product_ids' => [$this->cucumber->id],
            ]));

        $response->assertOk();
        $matrix = $response->viewData('matrix');
        $this->assertArrayNotHasKey("{$this->tomato->id}:A", $matrix);
        $this->assertArrayHasKey("{$this->cucumber->id}:A", $matrix);
        $this->assertEquals(8.0, $response->viewData('grandTotal'));
    }

    public function test_filter_3_shop_multi_selection(): void
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
            'requested_qty' => 25,
            'approved_qty' => 25,
            'unit' => 'kg',
        ]);

        // Select only Shop 1
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', [
                'date' => $this->testDate,
                'shop_ids' => [$this->shop1->id],
            ]));

        $response->assertOk();
        $filteredShops = $response->viewData('filteredShops');
        $this->assertCount(1, $filteredShops);
        $this->assertEquals($this->shop1->id, $filteredShops->first()->id);
        $this->assertEquals(12.0, $response->viewData('grandTotal'));
    }

    public function test_filter_4_grade_combinations_and_separation(): void
    {
        $order = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);

        // Create Tomato in Grade A, B, and C
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
            'requested_qty' => 6,
            'approved_qty' => 6,
            'unit' => 'kg',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'C',
            'requested_qty' => 4,
            'approved_qty' => 4,
            'unit' => 'kg',
        ]);

        // Scenario 1: Grade A only
        $resA = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', ['date' => $this->testDate, 'grades' => ['A']]));
        $this->assertEquals(10.0, $resA->viewData('grandTotal'));
        $this->assertEquals('GRADE A', $resA->viewData('gradeHeading'));
        $this->assertCount(1, $resA->viewData('matrix'));

        // Scenario 2: Grade B only
        $resB = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', ['date' => $this->testDate, 'grades' => ['B']]));
        $this->assertEquals(6.0, $resB->viewData('grandTotal'));
        $this->assertEquals('GRADE B', $resB->viewData('gradeHeading'));
        $this->assertCount(1, $resB->viewData('matrix'));

        // Scenario 3: Grade C only
        $resC = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', ['date' => $this->testDate, 'grades' => ['C']]));
        $this->assertEquals(4.0, $resC->viewData('grandTotal'));
        $this->assertEquals('GRADE C', $resC->viewData('gradeHeading'));
        $this->assertCount(1, $resC->viewData('matrix'));

        // Scenario 4: A + B
        $resAB = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', ['date' => $this->testDate, 'grades' => ['A', 'B']]));
        $this->assertEquals(16.0, $resAB->viewData('grandTotal'));
        $this->assertEquals('GRADES A, B', $resAB->viewData('gradeHeading'));
        $this->assertCount(2, $resAB->viewData('matrix'));
        $this->assertArrayHasKey("{$this->tomato->id}:A", $resAB->viewData('matrix'));
        $this->assertArrayHasKey("{$this->tomato->id}:B", $resAB->viewData('matrix'));

        // Scenario 5: A + C
        $resAC = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', ['date' => $this->testDate, 'grades' => ['A', 'C']]));
        $this->assertEquals(14.0, $resAC->viewData('grandTotal'));
        $this->assertEquals('GRADES A, C', $resAC->viewData('gradeHeading'));
        $this->assertCount(2, $resAC->viewData('matrix'));

        // Scenario 6: B + C
        $resBC = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', ['date' => $this->testDate, 'grades' => ['B', 'C']]));
        $this->assertEquals(10.0, $resBC->viewData('grandTotal'));
        $this->assertEquals('GRADES B, C', $resBC->viewData('gradeHeading'));
        $this->assertCount(2, $resBC->viewData('matrix'));

        // Scenario 7: A + B + C
        $resABC = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', ['date' => $this->testDate, 'grades' => ['A', 'B', 'C']]));
        $this->assertEquals(20.0, $resABC->viewData('grandTotal'));
        $this->assertEquals('GRADES A, B, C', $resABC->viewData('gradeHeading'));
        $this->assertCount(3, $resABC->viewData('matrix'));
    }

    public function test_filter_5_sku_sort_and_skip_empty_shops(): void
    {
        $order = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id, // SKU V-100
            'product_grade' => 'A',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'kg',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->cucumber->id, // SKU V-050
            'product_grade' => 'A',
            'requested_qty' => 5,
            'approved_qty' => 5,
            'unit' => 'kg',
        ]);

        // Code sort enabled: V-050 (cucumber) must precede V-100 (tomato)
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', [
                'date' => $this->testDate,
                'code_sort' => 1,
                'skip_empty_shops' => 1,
            ]));

        $response->assertOk();
        $keys = array_keys($response->viewData('matrix'));
        $this->assertEquals("{$this->cucumber->id}:A", $keys[0]);
        $this->assertEquals("{$this->tomato->id}:A", $keys[1]);

        // Shop 2 has 0 ordered quantities, so it should be skipped with skip_empty_shops=1
        $filteredShops = $response->viewData('filteredShops');
        $this->assertCount(1, $filteredShops);
        $this->assertEquals($this->shop1->id, $filteredShops->first()->id);

        // With skip_empty_shops=0, Shop 2 should be included
        $responseNoSkip = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', [
                'date' => $this->testDate,
                'skip_empty_shops' => 0,
            ]));
        $this->assertCount(2, $responseNoSkip->viewData('filteredShops'));
    }

    public function test_print_view_data_consistency(): void
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

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet.print', [
                'date' => $this->testDate,
                'grades' => ['A'],
            ]));

        $response->assertOk();
        $response->assertSee('Sort Sheet V1');
        $response->assertSee('GRADE A');
        $response->assertSee('Tomato Local');
        $response->assertSee('Shop Alpha');
        $this->assertEquals(10.0, $response->viewData('grandTotal'));
    }

    public function test_category_headers_visible_when_headers_on(): void
    {
        $order = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id, // Vegetables
            'product_grade' => 'A',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'kg',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->apple->id, // Fruits
            'product_grade' => 'A',
            'requested_qty' => 5,
            'approved_qty' => 5,
            'unit' => 'kg',
        ]);

        // Default or explicit show_category_titles=1
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', [
                'date' => $this->testDate,
                'show_category_titles' => 1,
            ]));

        $response->assertOk();
        $this->assertTrue($response->viewData('filters')['show_category_titles']);
        $response->assertSee('Category: Vegetables');
        $response->assertSee('Category: Fruits');
    }

    public function test_category_headers_absent_when_headers_off(): void
    {
        $order = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id, // Vegetables
            'product_grade' => 'A',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'kg',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->apple->id, // Fruits
            'product_grade' => 'A',
            'requested_qty' => 5,
            'approved_qty' => 5,
            'unit' => 'kg',
        ]);

        // Explicit show_category_titles=0
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', [
                'date' => $this->testDate,
                'show_category_titles' => 0,
            ]));

        $response->assertOk();
        $this->assertFalse($response->viewData('filters')['show_category_titles']);
        $response->assertDontSee('Category: Vegetables');
        $response->assertDontSee('Category: Fruits');

        // Products, quantities, and totals remain present and correct
        $response->assertSee('Tomato Local');
        $response->assertSee('Apple Red');
        $this->assertEquals(15.0, $response->viewData('grandTotal'));
    }

    public function test_category_headers_off_via_alias_and_form_omission(): void
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

        // Alias show_category_headers=0
        $resAlias = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', [
                'date' => $this->testDate,
                'show_category_headers' => 0,
            ]));
        $this->assertFalse($resAlias->viewData('filters')['show_category_titles']);
        $resAlias->assertDontSee('Category: Vegetables');

        // Form submission where unchecked checkbox was omitted by browser
        $resOmitted = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', [
                'date' => $this->testDate,
                'grades_filter' => 1,
                // show_category_titles omitted
            ]));
        $this->assertFalse($resOmitted->viewData('filters')['show_category_titles']);
        $resOmitted->assertDontSee('Category: Vegetables');
    }

    public function test_category_headers_off_with_category_filter_grade_b_and_sku_sort(): void
    {
        $order = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);

        // Tomato (V-100) Grade B
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'B',
            'requested_qty' => 12,
            'approved_qty' => 12,
            'unit' => 'kg',
        ]);

        // Cucumber (V-050) Grade B
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->cucumber->id,
            'product_grade' => 'B',
            'requested_qty' => 7,
            'approved_qty' => 7,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', [
                'date' => $this->testDate,
                'grades' => ['B'],
                'category_ids' => [$this->categoryVeg->id],
                'code_sort' => 1,
                'show_category_titles' => 0,
            ]));

        $response->assertOk();
        $this->assertFalse($response->viewData('filters')['show_category_titles']);
        $response->assertDontSee('Category: Vegetables');

        // SKU sort order verified without category headings
        $keys = array_keys($response->viewData('matrix'));
        $this->assertEquals("{$this->cucumber->id}:B", $keys[0]);
        $this->assertEquals("{$this->tomato->id}:B", $keys[1]);
        $this->assertEquals(19.0, $response->viewData('grandTotal'));
    }

    public function test_print_view_reflects_category_headers_option(): void
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

        // 1. Print with headers ON
        $resOn = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet.print', [
                'date' => $this->testDate,
                'show_category_titles' => 1,
            ]));
        $resOn->assertOk();
        $resOn->assertSee('CATEGORY: Vegetables');

        // 2. Print with headers OFF
        $resOff = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet.print', [
                'date' => $this->testDate,
                'show_category_titles' => 0,
            ]));
        $resOff->assertOk();
        $resOff->assertDontSee('CATEGORY: Vegetables');
        $resOff->assertSee('Tomato Local');
        $this->assertEquals(10.0, $resOff->viewData('grandTotal'));
    }

    public function test_checkbox_state_matches_selected_value_after_refresh(): void
    {
        // When OFF (show_category_titles=0), checkbox input should not be checked
        $resOff = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', ['show_category_titles' => 0]));
        $resOff->assertOk();
        $this->assertStringNotContainsString('id="show-category-headers-checkbox" value="1" checked', $resOff->getContent());

        // When ON (show_category_titles=1), checkbox input should be checked
        $resOn = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', ['show_category_titles' => 1]));
        $resOn->assertOk();
        $this->assertStringContainsString('id="show-category-headers-checkbox" value="1" checked', $resOn->getContent());
    }

    public function test_product_count_quantities_and_totals_remain_identical_when_toggling_headers(): void
    {
        $order = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->tomato->id,
            'product_grade' => 'A',
            'requested_qty' => 14.5,
            'approved_qty' => 14.5,
            'unit' => 'kg',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->apple->id,
            'product_grade' => 'A',
            'requested_qty' => 22.0,
            'approved_qty' => 22.0,
            'unit' => 'kg',
        ]);

        $resOn = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', ['date' => $this->testDate, 'show_category_titles' => 1]));

        $resOff = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', ['date' => $this->testDate, 'show_category_titles' => 0]));

        $this->assertEquals(count($resOn->viewData('matrix')), count($resOff->viewData('matrix')));
        $this->assertEquals($resOn->viewData('grandTotal'), $resOff->viewData('grandTotal'));
        $this->assertEquals($resOn->viewData('shopTotals'), $resOff->viewData('shopTotals'));
    }

    public function test_sort_sheet_presets_and_batches_are_displayed_in_view(): void
    {
        $preset = SortSheetPreset::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->authorizedUser->id,
            'name' => 'Daily Veggies Preset',
            'surface' => 'sort-sheet',
            'sort_order' => 1,
            'category_ids' => [$this->categoryVeg->id],
        ]);

        $batch = SortSheetPresetBatch::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->authorizedUser->id,
            'name' => 'Morning Routine Batch',
            'preset_ids' => [$preset->uuid],
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', ['date' => $this->testDate]));

        $response->assertOk();
        $this->assertTrue($response->viewData('presets')->contains('id', $preset->id));
        $this->assertTrue($response->viewData('presetBatches')->contains('id', $batch->id));
        $response->assertSee('Daily Veggies Preset');
        $response->assertSee('Morning Routine Batch');
        $response->assertSee(route('sort-sheet.presets.index'));
    }

    public function test_sort_sheet_preset_application_restores_stored_filters_and_refreshes_preview(): void
    {
        $preset = SortSheetPreset::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->authorizedUser->id,
            'name' => 'Fruits Only Preset',
            'surface' => 'sort-sheet',
            'category_ids' => [$this->categoryFruit->id],
            'product_ids' => [$this->apple->id],
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
            'product_id' => $this->apple->id,
            'product_grade' => 'A',
            'requested_qty' => 15.0,
            'approved_qty' => 15.0,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', [
                'date' => $this->testDate,
                'preset_id' => $preset->uuid,
            ]));

        $response->assertOk();
        $matrix = $response->viewData('matrix');
        $this->assertArrayHasKey("{$this->apple->id}:A", $matrix);
        $this->assertArrayNotHasKey("{$this->tomato->id}:A", $matrix);

        $filters = $response->viewData('filters');
        $this->assertEquals($preset->uuid, $filters['selected_preset']);
        $this->assertEquals([$this->categoryFruit->id], $filters['category_ids']);
        $this->assertEquals([$this->apple->id], $filters['product_ids']);
    }

    public function test_unauthorized_user_cannot_apply_other_users_private_preset(): void
    {
        $otherUser = User::factory()->create();
        $privatePreset = SortSheetPreset::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $otherUser->id,
            'name' => 'Private Preset',
            'surface' => 'sort-sheet',
            'category_ids' => [$this->categoryFruit->id],
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', [
                'date' => $this->testDate,
                'preset_id' => $privatePreset->uuid,
            ]));

        $response->assertOk();
        $filters = $response->viewData('filters');
        $this->assertNull($filters['selected_preset'] ?? null);
        $this->assertEmpty($filters['category_ids'] ?? []);
    }

    public function test_preset_application_is_strictly_read_only_and_does_not_mutate_database(): void
    {
        $preset = SortSheetPreset::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->authorizedUser->id,
            'name' => 'Read Only Preset',
            'surface' => 'sort-sheet',
            'category_ids' => [$this->categoryVeg->id],
        ]);

        $presetCountBefore = SortSheetPreset::count();
        $presetBatchCountBefore = SortSheetPresetBatch::count();
        $presetUpdatedBefore = $preset->fresh()->updated_at;

        $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', [
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
            'requested_qty' => 42.0,
            'approved_qty' => 42.0,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', ['date' => $historicalDate]));

        $response->assertOk();
        $response->assertSee('Apply Filters & Preview');
        $response->assertSee($historicalDate);
        $matrix = $response->viewData('matrix');
        $this->assertArrayHasKey("{$this->tomato->id}:A", $matrix);
        $this->assertEquals(42.0, $matrix["{$this->tomato->id}:A"][$this->shop1->id]);

        $printResponse = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet.print', ['date' => $historicalDate]));

        $printResponse->assertOk();
        $printResponse->assertSee('Tomato');
    }

    public function test_filter_form_has_no_nested_forms_and_includes_submit_button(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.sort-sheet', ['date' => $this->testDate]));

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
