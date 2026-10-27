<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class SortSheetGradeFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Shop $shop;

    private Product $product1;

    private Product $product2;

    private string $testDate = '2026-10-27';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(
            Permission::findOrCreate('sort.sheet.view'),
            Permission::findOrCreate('sort.sheet.generate'),
            Permission::findOrCreate('sort.sheet.export'),
        );

        $category = Category::factory()->create(['name' => 'Vegetables']);

        $this->shop = Shop::factory()->create(['name' => 'Shop 1', 'status' => 'active']);

        $this->product1 = Product::factory()->create(['name' => 'Tomato', 'sku' => 'V-001', 'category_id' => $category->id]);
        $this->product2 = Product::factory()->create(['name' => 'Cucumber', 'sku' => 'V-002', 'category_id' => $category->id]);
    }

    public function test_default_sort_sheet_access_selects_grade_a_only(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('sort-sheet.index'));

        $response->assertOk();
        $response->assertViewHas('selectedGrades', ['A']);
        $response->assertSee('id="grade-filter-a"', false);
        $response->assertSee('value="A"', false);
    }

    public function test_grade_a_only_filter_includes_only_grade_a_quantities(): void
    {
        $order = ShopOrder::factory()->approved()->for($this->shop)->create(['business_date' => $this->testDate]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product1->id,
            'product_grade' => 'A',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'KG',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product1->id,
            'product_grade' => 'B',
            'requested_qty' => 20,
            'approved_qty' => 20,
            'unit' => 'KG',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('sort-sheet.generate', [
                'date' => $this->testDate,
                'grades_filter' => 1,
                'grades' => ['A'],
            ]));

        $response->assertOk();
        $response->assertViewHas('matrix', function (array $matrix): bool {
            return ($matrix[$this->product1->id][$this->shop->id] ?? null) === 10.0
                && ! isset($matrix["{$this->product1->id}_B"]);
        });
    }

    public function test_grade_b_only_filter_includes_only_grade_b_quantities(): void
    {
        $order = ShopOrder::factory()->approved()->for($this->shop)->create(['business_date' => $this->testDate]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product1->id,
            'product_grade' => 'A',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'KG',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product1->id,
            'product_grade' => 'B',
            'requested_qty' => 20,
            'approved_qty' => 20,
            'unit' => 'KG',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('sort-sheet.generate', [
                'date' => $this->testDate,
                'grades_filter' => 1,
                'grades' => ['B'],
            ]));

        $response->assertOk();
        $response->assertViewHas('matrix', function (array $matrix): bool {
            return ! isset($matrix[$this->product1->id])
                && ($matrix["{$this->product1->id}_B"][$this->shop->id] ?? null) === 20.0;
        });
    }

    public function test_grade_c_only_filter_includes_only_grade_c_quantities(): void
    {
        $order = ShopOrder::factory()->approved()->for($this->shop)->create(['business_date' => $this->testDate]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product1->id,
            'product_grade' => 'C',
            'requested_qty' => 7,
            'approved_qty' => 7,
            'unit' => 'KG',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('sort-sheet.generate', [
                'date' => $this->testDate,
                'grades_filter' => 1,
                'grades' => ['C'],
            ]));

        $response->assertOk();
        $response->assertViewHas('matrix', function (array $matrix): bool {
            return ($matrix["{$this->product1->id}_C"][$this->shop->id] ?? null) === 7.0;
        });
    }

    public function test_multi_grade_filter_does_not_merge_same_product_in_different_grades(): void
    {
        $order = ShopOrder::factory()->approved()->for($this->shop)->create(['business_date' => $this->testDate]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product1->id,
            'product_grade' => 'A',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'KG',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product1->id,
            'product_grade' => 'B',
            'requested_qty' => 20,
            'approved_qty' => 20,
            'unit' => 'KG',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('sort-sheet.generate', [
                'date' => $this->testDate,
                'grades_filter' => 1,
                'grades' => ['A', 'B'],
            ]));

        $response->assertOk();
        $response->assertViewHas('matrix', function (array $matrix): bool {
            return ($matrix[$this->product1->id][$this->shop->id] ?? null) === 10.0
                && ($matrix["{$this->product1->id}_B"][$this->shop->id] ?? null) === 20.0
                && count($matrix) === 2;
        });

        $response->assertViewHas('productMeta', function (array $meta): bool {
            return ($meta[$this->product1->id]['has_other_grades'] ?? false) === true
                && ($meta["{$this->product1->id}_B"]['has_other_grades'] ?? false) === true;
        });

        // In the HTML, grade badges should be displayed
        $response->assertSee('Grade A');
        $response->assertSee('Grade B');
    }

    public function test_historical_order_item_without_grade_defaults_to_grade_a(): void
    {
        $order = ShopOrder::factory()->approved()->for($this->shop)->create(['business_date' => $this->testDate]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product2->id,
            'requested_qty' => 14,
            'approved_qty' => 14,
            'unit' => 'KG',
        ]);

        // Default query without grades filter should include this item as Grade A
        $responseA = $this->actingAs($this->user)
            ->get(route('sort-sheet.generate', [
                'date' => $this->testDate,
            ]));

        $responseA->assertOk();
        $responseA->assertViewHas('matrix', fn (array $matrix): bool => ($matrix[$this->product2->id][$this->shop->id] ?? null) === 14.0);

        // Grade B only query should NOT include this item (matrix is empty, so noOrders is set)
        $responseB = $this->actingAs($this->user)
            ->get(route('sort-sheet.generate', [
                'date' => $this->testDate,
                'grades_filter' => 1,
                'grades' => ['B'],
            ]));

        $responseB->assertOk();
        $responseB->assertViewHas('noOrders', true);
    }

    public function test_print_view_preserves_selected_grades_and_shows_badge(): void
    {
        $order = ShopOrder::factory()->approved()->for($this->shop)->create(['business_date' => $this->testDate]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product1->id,
            'product_grade' => 'B',
            'requested_qty' => 25,
            'approved_qty' => 25,
            'unit' => 'KG',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('sort-sheet.print', [
                'date' => $this->testDate,
                'grades_filter' => 1,
                'grades' => ['B'],
            ]));

        $response->assertOk();
        $response->assertViewHas('selectedGrades', ['B']);
        $response->assertSee('Grades: B');
        $response->assertSee('Grade B');
    }

    public function test_all_selected_and_combinations_recalculate_totals_correctly(): void
    {
        $order = ShopOrder::factory()->approved()->for($this->shop)->create(['business_date' => $this->testDate]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product1->id,
            'product_grade' => 'A',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'KG',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product1->id,
            'product_grade' => 'B',
            'requested_qty' => 20,
            'approved_qty' => 20,
            'unit' => 'KG',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product1->id,
            'product_grade' => 'C',
            'requested_qty' => 30,
            'approved_qty' => 30,
            'unit' => 'KG',
        ]);

        // 1. All selected (A, B, C)
        $responseAll = $this->actingAs($this->user)
            ->get(route('sort-sheet.generate', [
                'date' => $this->testDate,
                'grades_filter' => 1,
                'grades' => ['A', 'B', 'C'],
            ]));

        $responseAll->assertOk();
        $responseAll->assertViewHas('matrix', function (array $matrix): bool {
            $grandTotal = collect($matrix)->sum(fn ($row) => array_sum($row));

            return count($matrix) === 3
                && ($matrix[$this->product1->id][$this->shop->id] ?? null) === 10.0
                && ($matrix["{$this->product1->id}_B"][$this->shop->id] ?? null) === 20.0
                && ($matrix["{$this->product1->id}_C"][$this->shop->id] ?? null) === 30.0
                && $grandTotal === 60.0;
        });

        // 2. Combination B and C
        $responseBC = $this->actingAs($this->user)
            ->get(route('sort-sheet.generate', [
                'date' => $this->testDate,
                'grades_filter' => 1,
                'grades' => ['B', 'C'],
            ]));

        $responseBC->assertOk();
        $responseBC->assertViewHas('matrix', function (array $matrix): bool {
            $grandTotal = collect($matrix)->sum(fn ($row) => array_sum($row));

            return count($matrix) === 2
                && ! isset($matrix[$this->product1->id])
                && ($matrix["{$this->product1->id}_B"][$this->shop->id] ?? null) === 20.0
                && ($matrix["{$this->product1->id}_C"][$this->shop->id] ?? null) === 30.0
                && $grandTotal === 50.0;
        });

        // 3. Combination A and C
        $responseAC = $this->actingAs($this->user)
            ->get(route('sort-sheet.generate', [
                'date' => $this->testDate,
                'grades_filter' => 1,
                'grades' => ['A', 'C'],
            ]));

        $responseAC->assertOk();
        $responseAC->assertViewHas('matrix', function (array $matrix): bool {
            $grandTotal = collect($matrix)->sum(fn ($row) => array_sum($row));

            return count($matrix) === 2
                && ($matrix[$this->product1->id][$this->shop->id] ?? null) === 10.0
                && ! isset($matrix["{$this->product1->id}_B"])
                && ($matrix["{$this->product1->id}_C"][$this->shop->id] ?? null) === 30.0
                && $grandTotal === 40.0;
        });
    }
}
