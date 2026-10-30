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

final class SegregationGridV1FeatureTest extends TestCase
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
        $response = $this->get(route('print.segregation-grid'));
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_user_is_forbidden(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('print.segregation-grid'));
        $this->assertTrue(in_array($response->status(), [302, 403], true));
    }

    public function test_authorized_user_can_access_segregation_grid_v1(): void
    {
        $response = $this->actingAs($this->authorizedUser)->get(route('print.segregation-grid'));

        $response->assertOk();
        $response->assertViewIs('print-v1.segregation-grid.index');
        $response->assertSee('Segregation Grid V1');
        $response->assertSee('Daily Custom Presets:');
    }

    public function test_default_filter_selects_grade_a_only(): void
    {
        $response = $this->actingAs($this->authorizedUser)->get(route('print.segregation-grid'));

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

        $response = $this->actingAs($this->authorizedUser)->get(route('print.segregation-grid', [
            'date' => $this->testDate,
            'category_ids' => [$this->categoryFruit->id],
        ]));

        $response->assertOk();
        $matrix = $response->viewData('matrix');
        $this->assertArrayHasKey("{$this->apple->id}:A", $matrix);
        $this->assertArrayNotHasKey("{$this->tomato->id}:A", $matrix);
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

        $response = $this->actingAs($this->authorizedUser)->get(route('print.segregation-grid', [
            'date' => $this->testDate,
            'product_ids' => [$this->tomato->id],
        ]));

        $response->assertOk();
        $matrix = $response->viewData('matrix');
        $this->assertArrayHasKey("{$this->tomato->id}:A", $matrix);
        $this->assertArrayNotHasKey("{$this->cucumber->id}:A", $matrix);
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

        $response = $this->actingAs($this->authorizedUser)->get(route('print.segregation-grid', [
            'date' => $this->testDate,
            'shop_ids' => [$this->shop1->id],
        ]));

        $response->assertOk();
        $filteredShops = $response->viewData('filteredShops');
        $this->assertTrue($filteredShops->contains('id', $this->shop1->id));
        $this->assertFalse($filteredShops->contains('id', $this->shop2->id));
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

        $response = $this->actingAs($this->authorizedUser)->get(route('print.segregation-grid', [
            'date' => $this->testDate,
            'grades' => ['A', 'B'],
        ]));

        $response->assertOk();
        $matrix = $response->viewData('matrix');
        $this->assertArrayHasKey("{$this->tomato->id}:A", $matrix);
        $this->assertArrayHasKey("{$this->tomato->id}:B", $matrix);
        $this->assertEquals(10, $matrix["{$this->tomato->id}:A"][$this->shop1->id]);
        $this->assertEquals(7, $matrix["{$this->tomato->id}:B"][$this->shop1->id]);
        $this->assertEquals('GRADES A, B', $response->viewData('gradeHeading'));
    }

    public function test_segregation_grid_print_pagination_chunks_9_rows(): void
    {
        $order = ShopOrder::factory()->approved()->for($this->shop1)->create(['business_date' => $this->testDate]);

        for ($i = 1; $i <= 20; $i++) {
            $prod = Product::create([
                'name' => "Grid Item {$i}",
                'sku' => (string) ($i + 100),
                'category_id' => $this->categoryVeg->id,
                'is_active' => true,
                'unit' => 'kg',
            ]);
            ShopOrderItem::create([
                'shop_order_id' => $order->id,
                'product_id' => $prod->id,
                'product_grade' => 'A',
                'requested_qty' => 2,
                'approved_qty' => 2,
                'unit' => 'kg',
            ]);
        }

        $response = $this->actingAs($this->authorizedUser)->get(route('print.segregation-grid.print', ['date' => $this->testDate]));

        $response->assertOk();
        $gridPages = $response->viewData('gridPages');
        // 20 items at 9 rows per page = 3 pages (9, 9, 2)
        $this->assertCount(3, $gridPages);
        $this->assertCount(9, $gridPages[0]);
        $this->assertCount(9, $gridPages[1]);
        $this->assertCount(2, $gridPages[2]);
    }

    public function test_segregation_grid_presets_and_batches_displayed_in_view(): void
    {
        $preset = SortSheetPreset::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->authorizedUser->id,
            'name' => 'Grid Veggies Preset',
            'surface' => 'grid',
            'sort_order' => 1,
            'category_ids' => [$this->categoryVeg->id],
        ]);

        $batch = SortSheetPresetBatch::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->authorizedUser->id,
            'name' => 'Grid Batch',
            'preset_ids' => [$preset->uuid],
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.segregation-grid', ['date' => $this->testDate]));

        $response->assertOk();
        $this->assertTrue($response->viewData('presets')->contains('id', $preset->id));
        $this->assertTrue($response->viewData('presetBatches')->contains('id', $batch->id));
        $response->assertSee('Grid Veggies Preset');
        $response->assertSee('Grid Batch');
        $response->assertSee(route('sort-sheet.presets.index'));
    }

    public function test_segregation_grid_preset_application_restores_stored_filters(): void
    {
        $preset = SortSheetPreset::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->authorizedUser->id,
            'name' => 'Grid Fruits Preset',
            'surface' => 'grid',
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
            ->get(route('print.segregation-grid', [
                'date' => $this->testDate,
                'preset_id' => $preset->uuid,
            ]));

        $response->assertOk();
        $matrix = $response->viewData('matrix');
        $this->assertArrayHasKey("{$this->apple->id}:A", $matrix);
        $this->assertArrayNotHasKey("{$this->tomato->id}:A", $matrix);

        $filters = $response->viewData('filters');
        $this->assertEquals($preset->uuid, $filters['selected_preset']);
    }

    public function test_unauthorized_user_cannot_apply_other_users_private_preset(): void
    {
        $otherUser = User::factory()->create();
        $privatePreset = SortSheetPreset::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $otherUser->id,
            'name' => 'Private Grid Preset',
            'surface' => 'grid',
            'category_ids' => [$this->categoryFruit->id],
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.segregation-grid', [
                'date' => $this->testDate,
                'preset_id' => $privatePreset->uuid,
            ]));

        $response->assertOk();
        $filters = $response->viewData('filters');
        $this->assertNull($filters['selected_preset'] ?? null);
    }

    public function test_preset_application_is_strictly_read_only(): void
    {
        $preset = SortSheetPreset::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->authorizedUser->id,
            'name' => 'Read Only Grid Preset',
            'surface' => 'grid',
            'category_ids' => [$this->categoryVeg->id],
        ]);

        $countBefore = SortSheetPreset::count();
        $this->actingAs($this->authorizedUser)
            ->get(route('print.segregation-grid', [
                'date' => $this->testDate,
                'preset_id' => $preset->uuid,
            ]))
            ->assertOk();

        $this->assertSame($countBefore, SortSheetPreset::count());
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
            'requested_qty' => 55.0,
            'approved_qty' => 55.0,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.segregation-grid', ['date' => $historicalDate]));

        $response->assertOk();
        $response->assertSee('Apply Filters');
        $response->assertSee($historicalDate);
        $matrix = $response->viewData('matrix');
        $this->assertArrayHasKey("{$this->tomato->id}:A", $matrix);
        $this->assertEquals(55.0, $matrix["{$this->tomato->id}:A"][$this->shop1->id]);

        $printResponse = $this->actingAs($this->authorizedUser)
            ->get(route('print.segregation-grid.print', ['date' => $historicalDate]));

        $printResponse->assertOk();
        $printResponse->assertSee('Tomato');
    }

    public function test_filter_form_has_no_nested_forms_and_includes_submit_button(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('print.segregation-grid', ['date' => $this->testDate]));

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
