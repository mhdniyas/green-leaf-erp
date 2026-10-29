<?php

declare(strict_types=1);

namespace Tests\Unit\Printing;

use App\Models\Category;
use App\Models\Printing\PrintV1Setting;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Services\Printing\PrintV1MatrixBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintV1MatrixBuilderServiceTest extends TestCase
{
    use RefreshDatabase;

    private PrintV1MatrixBuilderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PrintV1MatrixBuilderService::class);
    }

    public function test_grade_d_is_strictly_disallowed_and_sanitized(): void
    {
        $ref = new \ReflectionMethod($this->service, 'sanitizeGrades');
        $ref->setAccessible(true);

        $sanitized = $ref->invoke($this->service, ['A', 'B', 'D', 'GRADE D', 'C', 'U', 'DMG']);

        $this->assertNotContains('D', $sanitized);
        $this->assertNotContains('GRADE D', $sanitized);
        $this->assertEquals(['A', 'B', 'C', 'U', 'DMG'], $sanitized);
    }

    public function test_empty_shops_are_pruned_when_skip_empty_shops_is_true(): void
    {
        $date = '2026-10-29';

        $activeShopWithOrder = Shop::factory()->create([
            'name' => 'Active Shop Ordered',
            'status' => 'active',
            'warehouse_tag' => 'WH-1',
        ]);

        $activeShopEmpty = Shop::factory()->create([
            'name' => 'Active Shop Empty',
            'status' => 'active',
            'warehouse_tag' => 'WH-2',
        ]);

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $product = Product::factory()->create([
            'name' => 'Tomato',
            'sku' => 'TOM-001',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $activeShopWithOrder->id,
            'business_date' => $date,
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 25.0,
            'approved_qty' => 25.0,
            'product_grade' => 'A',
            'unit' => 'KG',
        ]);

        // When skip_empty_shops is true
        $result = $this->service->build('sort-sheet', $date, ['skip_empty_shops' => true]);

        $this->assertTrue($result->filteredShops->contains('id', $activeShopWithOrder->id));
        $this->assertFalse($result->filteredShops->contains('id', $activeShopEmpty->id));
        $this->assertTrue($result->skippedEmptyShops->contains('id', $activeShopEmpty->id));
        $this->assertEquals(1, $result->shopCount());
        $this->assertEquals(1, $result->skippedShopCount());
    }

    public function test_empty_shops_are_retained_when_skip_empty_shops_is_false(): void
    {
        $date = '2026-10-29';

        $activeShopWithOrder = Shop::factory()->create(['status' => 'active', 'warehouse_tag' => 'WH-1']);
        $activeShopEmpty = Shop::factory()->create(['status' => 'active', 'warehouse_tag' => 'WH-2']);

        $category = Category::factory()->create(['name' => 'Vegetables']);
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $activeShopWithOrder->id,
            'business_date' => $date,
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'product_grade' => 'A',
            'unit' => 'KG',
        ]);

        $result = $this->service->build('sort-sheet', $date, ['skip_empty_shops' => false]);

        $this->assertTrue($result->filteredShops->contains('id', $activeShopWithOrder->id));
        $this->assertTrue($result->filteredShops->contains('id', $activeShopEmpty->id));
        $this->assertEquals(0, $result->skippedShopCount());
    }

    public function test_deliberately_whitelisted_inactive_shops_are_included(): void
    {
        $date = '2026-10-29';

        $inactiveShop = Shop::factory()->create([
            'name' => 'Seasonal Stall',
            'status' => 'inactive',
            'warehouse_tag' => 'WH-9',
        ]);

        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $inactiveShop->id,
            'business_date' => $date,
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 15.0,
            'approved_qty' => 15.0,
            'product_grade' => 'A',
            'unit' => 'KG',
        ]);

        // Whitelist the inactive shop in V1 shop settings
        PrintV1Setting::setForSection('shops', [
            'included_inactive_shop_ids' => [$inactiveShop->id],
        ]);

        $result = $this->service->build('sort-sheet', $date);

        $this->assertTrue($result->filteredShops->contains('id', $inactiveShop->id));
    }

    public function test_grades_a_b_c_and_u_are_segregated_with_correct_suffixes(): void
    {
        $date = '2026-10-29';
        $shop = Shop::factory()->create(['status' => 'active']);
        $category = Category::factory()->create();
        $product = Product::factory()->create(['name' => 'Tomato', 'category_id' => $category->id, 'is_active' => true]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $date,
            'state' => 'approved',
        ]);

        // Create items for Grade A, Grade B, Grade C, Grade U (Mixed)
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 20.0,
            'approved_qty' => 20.0,
            'product_grade' => 'A',
            'unit' => 'KG',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 15.0,
            'approved_qty' => 15.0,
            'product_grade' => 'B',
            'unit' => 'KG',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'product_grade' => 'C',
            'unit' => 'KG',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'product_grade' => 'U',
            'unit' => 'KG',
        ]);

        $result = $this->service->build('sort-sheet', $date, [
            'grades' => ['A', 'B', 'C', 'U'],
        ]);

        $this->assertEquals(4, $result->productCount());

        $this->assertArrayHasKey("{$product->id}:A", $result->matrix);
        $this->assertArrayHasKey("{$product->id}:B", $result->matrix);
        $this->assertArrayHasKey("{$product->id}:C", $result->matrix);
        $this->assertArrayHasKey("{$product->id}:U", $result->matrix);

        $this->assertEquals(20.0, $result->matrix["{$product->id}:A"][$shop->id]);
        $this->assertEquals(15.0, $result->matrix["{$product->id}:B"][$shop->id]);
        $this->assertEquals(10.0, $result->matrix["{$product->id}:C"][$shop->id]);
        $this->assertEquals(5.0, $result->matrix["{$product->id}:U"][$shop->id]);

        // Check suffix formatting
        $this->assertEquals('Tomato (A)', $result->productMeta["{$product->id}:A"]['display_name']);
        $this->assertEquals('Tomato (B)', $result->productMeta["{$product->id}:B"]['display_name']);
        $this->assertEquals('Tomato (C)', $result->productMeta["{$product->id}:C"]['display_name']);
        $this->assertEquals('Tomato (Mixed)', $result->productMeta["{$product->id}:U"]['display_name']);
        $this->assertEquals('GRADE A + B + C + MIXED', $result->gradeHeading());
        $this->assertFalse($result->isSingleGrade());
    }

    public function test_single_grade_a_omits_suffix_and_sets_grade_a_heading(): void
    {
        $date = '2026-10-29';
        $shop = Shop::factory()->create(['status' => 'active']);
        $category = Category::factory()->create();
        $product = Product::factory()->create(['name' => 'Banana Yelakki Green', 'category_id' => $category->id, 'is_active' => true]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $date,
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'product_grade' => 'A',
            'unit' => 'KG',
        ]);

        $result = $this->service->build('sort-sheet', $date, [
            'grades' => ['A'],
        ]);

        $this->assertTrue($result->isSingleGrade());
        $this->assertEquals('GRADE A', $result->gradeHeading());
        $this->assertEquals('Banana Yelakki Green', $result->productMeta["{$product->id}:A"]['display_name']);
    }

    public function test_single_grade_b_c_and_u_omit_suffixes(): void
    {
        $date = '2026-10-29';
        $shop = Shop::factory()->create(['status' => 'active']);
        $category = Category::factory()->create();
        $product = Product::factory()->create(['name' => 'Coconut', 'category_id' => $category->id, 'is_active' => true]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $date,
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 5.0,
            'approved_qty' => 5.0,
            'product_grade' => 'B',
            'unit' => 'KG',
        ]);

        $resultB = $this->service->build('sort-sheet', $date, ['grades' => ['B']]);
        $this->assertEquals('GRADE B', $resultB->gradeHeading());
        $this->assertEquals('Coconut', $resultB->productMeta["{$product->id}:B"]['display_name']);

        // Grade C
        $orderItemC = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 8.0,
            'approved_qty' => 8.0,
            'product_grade' => 'C',
            'unit' => 'KG',
        ]);
        $resultC = $this->service->build('sort-sheet', $date, ['grades' => ['C']]);
        $this->assertEquals('GRADE C', $resultC->gradeHeading());
        $this->assertEquals('Coconut', $resultC->productMeta["{$product->id}:C"]['display_name']);

        // Grade U (Mixed)
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 12.0,
            'approved_qty' => 12.0,
            'product_grade' => 'U',
            'unit' => 'KG',
        ]);
        $resultU = $this->service->build('sort-sheet', $date, ['grades' => ['U']]);
        $this->assertEquals('GRADE MIXED', $resultU->gradeHeading());
        $this->assertEquals('Coconut', $resultU->productMeta["{$product->id}:U"]['display_name']);
    }

    public function test_multiple_grades_heading_and_suffixes(): void
    {
        $date = '2026-10-29';
        $shop = Shop::factory()->create(['status' => 'active']);
        $category = Category::factory()->create();
        $product = Product::factory()->create(['name' => 'Peeled Garlic', 'category_id' => $category->id, 'is_active' => true]);

        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => $date,
            'state' => 'approved',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 10.0,
            'approved_qty' => 10.0,
            'product_grade' => 'A',
            'unit' => 'KG',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 6.0,
            'approved_qty' => 6.0,
            'product_grade' => 'B',
            'unit' => 'KG',
        ]);

        $resultAB = $this->service->build('sort-sheet', $date, ['grades' => ['A', 'B']]);
        $this->assertEquals('GRADE A + B', $resultAB->gradeHeading());
        $this->assertEquals('Peeled Garlic (A)', $resultAB->productMeta["{$product->id}:A"]['display_name']);
        $this->assertEquals('Peeled Garlic (B)', $resultAB->productMeta["{$product->id}:B"]['display_name']);

        $resultAU = $this->service->build('sort-sheet', $date, ['grades' => ['A', 'U']]);
        $this->assertEquals('GRADE A + MIXED', $resultAU->gradeHeading());
    }
}
