<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\Inventory\ProductGrade;
use App\Models\Category;
use App\Models\DailyProductPrice;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class AdminShopOrderGradeConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ShopPriceGroup $priceGroup;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-27 10:00:00'));

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $shopRole = Role::firstOrCreate(['name' => 'shop', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'purchase', 'guard_name' => 'web']);

        $salesOrderPermission = Permission::findOrCreate('sales.order.create', 'web');
        $shopRole->givePermissionTo($salesOrderPermission);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->priceGroup = ShopPriceGroup::factory()->create([
            'name' => 'Retail Standard',
            'is_active' => true,
        ]);

        $this->category = Category::factory()->create([
            'name' => 'Vegetables',
            'is_active' => true,
        ]);
    }

    private function createProductWithPrices(string $name, string $sku, float $priceGradeA = 100.0, float $priceGradeB = 80.0, float $priceGradeC = 60.0): Product
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
            'selling_price' => $priceGradeA,
        ]);

        DailyProductPrice::create([
            'product_id' => $product->id,
            'shop_price_group_id' => $this->priceGroup->id,
            'grade' => ProductGrade::GradeB,
            'selling_price' => $priceGradeB,
        ]);

        DailyProductPrice::create([
            'product_id' => $product->id,
            'shop_price_group_id' => $this->priceGroup->id,
            'grade' => ProductGrade::GradeC,
            'selling_price' => $priceGradeC,
        ]);

        return $product;
    }

    private function createShop(string $name, string $code, ?array $allowedGrades): Shop
    {
        return Shop::factory()->create([
            'name' => $name,
            'code' => $code,
            'shop_price_group_id' => $this->priceGroup->id,
            'allowed_product_grades' => $allowedGrades,
            'status' => 'active',
        ]);
    }

    public function test_a_only_shop_displays_only_grade_a(): void
    {
        $shop = $this->createShop('Casio', 'AV_CASIO', ['A']);
        $product = $this->createProductWithPrices('Tomato H', 'TOM-H');

        $response = $this->actingAs($this->admin)
            ->get(route('purchasing.shop-orders.edit', ['shop' => $shop->code, 'date' => '2026-10-27']));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('name="items['.$product->sku.']"', $content);
        $this->assertStringNotContainsString('name="items['.$product->sku.'|B]"', $content);
        $this->assertStringNotContainsString('name="items['.$product->sku.'|C]"', $content);
        $this->assertStringNotContainsString('item_grades[', $content);
    }

    public function test_b_only_shop_displays_only_grade_b(): void
    {
        $shop = $this->createShop('B Only Shop', 'B_ONLY', ['B']);
        $product = $this->createProductWithPrices('Tomato H', 'TOM-H');

        $response = $this->actingAs($this->admin)
            ->get(route('purchasing.shop-orders.edit', ['shop' => $shop->code, 'date' => '2026-10-27']));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('name="items['.$product->sku.']"', $content);
        $this->assertStringContainsString('name="item_grades['.$product->sku.']" value="B"', $content);
        $this->assertStringContainsString('Grade B', $content);
        $this->assertStringNotContainsString('name="items['.$product->sku.'|A]"', $content);
        $this->assertStringNotContainsString('name="items['.$product->sku.'|C]"', $content);
    }

    public function test_c_only_shop_displays_only_grade_c(): void
    {
        $shop = $this->createShop('C Only Shop', 'C_ONLY', ['C']);
        $product = $this->createProductWithPrices('Tomato H', 'TOM-H');

        $response = $this->actingAs($this->admin)
            ->get(route('purchasing.shop-orders.edit', ['shop' => $shop->code, 'date' => '2026-10-27']));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('name="items['.$product->sku.']"', $content);
        $this->assertStringContainsString('name="item_grades['.$product->sku.']" value="C"', $content);
        $this->assertStringContainsString('Grade C', $content);
        $this->assertStringNotContainsString('name="items['.$product->sku.'|A]"', $content);
        $this->assertStringNotContainsString('name="items['.$product->sku.'|B]"', $content);
    }

    public function test_a_plus_b_default_b_shows_b_first_then_a(): void
    {
        $shop = $this->createShop('AB Default B', 'AB_DEFB', ['B', 'A']);
        $product = $this->createProductWithPrices('Tomato H', 'TOM-H');

        $response = $this->actingAs($this->admin)
            ->get(route('purchasing.shop-orders.edit', ['shop' => $shop->code, 'date' => '2026-10-27']));

        $response->assertOk();
        $content = $response->getContent();

        // Primary is B
        $this->assertStringContainsString('name="items['.$product->sku.']"', $content);
        $this->assertStringContainsString('name="item_grades['.$product->sku.']" value="B"', $content);
        // Subgrade is A
        $this->assertStringContainsString('name="items['.$product->sku.'|A]"', $content);
        $this->assertStringContainsString('name="item_grades['.$product->sku.'|A]" value="A"', $content);
        // Grade C is absent
        $this->assertStringNotContainsString('name="items['.$product->sku.'|C]"', $content);
    }

    public function test_a_plus_b_plus_c_default_b_shows_all_three(): void
    {
        $shop = $this->createShop('ABC Default B', 'ABC_DEFB', ['B', 'A', 'C']);
        $product = $this->createProductWithPrices('Tomato H', 'TOM-H');

        $response = $this->actingAs($this->admin)
            ->get(route('purchasing.shop-orders.edit', ['shop' => $shop->code, 'date' => '2026-10-27']));

        $response->assertOk();
        $content = $response->getContent();

        // Primary is B
        $this->assertStringContainsString('name="items['.$product->sku.']"', $content);
        $this->assertStringContainsString('name="item_grades['.$product->sku.']" value="B"', $content);
        // Subgrades A and C
        $this->assertStringContainsString('name="items['.$product->sku.'|A]"', $content);
        $this->assertStringContainsString('name="item_grades['.$product->sku.'|A]" value="A"', $content);
        $this->assertStringContainsString('name="items['.$product->sku.'|C]"', $content);
        $this->assertStringContainsString('name="item_grades['.$product->sku.'|C]" value="C"', $content);
    }

    public function test_b_plus_c_default_c_shows_c_first_then_b(): void
    {
        $shop = $this->createShop('BC Default C', 'BC_DEFC', ['C', 'B']);
        $product = $this->createProductWithPrices('Tomato H', 'TOM-H');

        $response = $this->actingAs($this->admin)
            ->get(route('purchasing.shop-orders.edit', ['shop' => $shop->code, 'date' => '2026-10-27']));

        $response->assertOk();
        $content = $response->getContent();

        // Primary is C
        $this->assertStringContainsString('name="items['.$product->sku.']"', $content);
        $this->assertStringContainsString('name="item_grades['.$product->sku.']" value="C"', $content);
        // Subgrade is B
        $this->assertStringContainsString('name="items['.$product->sku.'|B]"', $content);
        $this->assertStringContainsString('name="item_grades['.$product->sku.'|B]" value="B"', $content);
        // Grade A is not present
        $this->assertStringNotContainsString('name="items['.$product->sku.'|A]"', $content);
    }

    public function test_admin_creates_b_only_order_persists_grade_b(): void
    {
        $shop = $this->createShop('Ashirwad B Only', 'AV_ASHIRWAD_B', ['B']);
        $product = $this->createProductWithPrices('Tomato H', 'TOM-H', 100.0, 80.0, 60.0);

        $response = $this->actingAs($this->admin)->post(route('purchasing.shop-orders.store', $shop), [
            'business_date' => '2026-10-27',
            'items' => [
                $product->sku => '15.0',
            ],
            'item_grades' => [
                $product->sku => 'B',
            ],
        ]);

        $response->assertRedirect(route('purchasing.shop-orders.edit', ['shop' => $shop->code, 'date' => '2026-10-27']));

        $order = ShopOrder::where('shop_id', $shop->id)->whereDate('business_date', '2026-10-27')->firstOrFail();
        $this->assertCount(1, $order->items);
        $item = $order->items->first();
        $this->assertSame('B', $item->product_grade instanceof ProductGrade ? $item->product_grade->value : (string) $item->product_grade);
        $this->assertEquals(15.0, (float) $item->requested_qty);
        $this->assertEquals(80.0, (float) $item->locked_selling_price);
    }

    public function test_admin_creates_multi_grade_order_persists_separate_lines(): void
    {
        $shop = $this->createShop('Ashirwad Multi', 'AV_ASHIRWAD_MULTI', ['B', 'C']);
        $product = $this->createProductWithPrices('Tomato H', 'TOM-H', 100.0, 80.0, 60.0);

        $response = $this->actingAs($this->admin)->post(route('purchasing.shop-orders.store', $shop), [
            'business_date' => '2026-10-27',
            'items' => [
                $product->sku => '10.0',
                $product->sku.'|C' => '5.0',
            ],
            'item_grades' => [
                $product->sku => 'B',
                $product->sku.'|C' => 'C',
            ],
        ]);

        $response->assertRedirect(route('purchasing.shop-orders.edit', ['shop' => $shop->code, 'date' => '2026-10-27']));

        $order = ShopOrder::where('shop_id', $shop->id)->whereDate('business_date', '2026-10-27')->firstOrFail();
        $this->assertCount(2, $order->items);

        $itemB = $order->items->first(fn ($i) => ($i->product_grade instanceof ProductGrade ? $i->product_grade->value : (string) $i->product_grade) === 'B');
        $itemC = $order->items->first(fn ($i) => ($i->product_grade instanceof ProductGrade ? $i->product_grade->value : (string) $i->product_grade) === 'C');

        $this->assertNotNull($itemB);
        $this->assertNotNull($itemC);
        $this->assertEquals(10.0, (float) $itemB->requested_qty);
        $this->assertEquals(80.0, (float) $itemB->locked_selling_price);
        $this->assertEquals(5.0, (float) $itemC->requested_qty);
        $this->assertEquals(60.0, (float) $itemC->locked_selling_price);
    }

    public function test_admin_order_rejects_unauthorized_grades(): void
    {
        $shop = $this->createShop('Ashirwad B Only', 'AV_ASHIRWAD_B2', ['B']);
        $product = $this->createProductWithPrices('Tomato H', 'TOM-H');

        $response = $this->actingAs($this->admin)->post(route('purchasing.shop-orders.store', $shop), [
            'business_date' => '2026-10-27',
            'items' => [
                $product->sku => '10.0',
            ],
            'item_grades' => [
                $product->sku => 'A', // Unauthorized!
            ],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseMissing('shop_orders', ['shop_id' => $shop->id]);
    }

    public function test_historical_disallowed_grade_items_are_preserved_on_update(): void
    {
        $shop = $this->createShop('Formerly A Now B', 'FORMER_A', ['B']);
        $product = $this->createProductWithPrices('Tomato H', 'TOM-H');

        // Create an existing historical order with Grade A
        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-10-27',
            'order_source' => 'shop_owner',
            'state' => 'submitted',
        ]);

        $historicalItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => ProductGrade::GradeA->value,
            'requested_qty' => 20.0,
            'unit' => 'kg',
            'locked_selling_price' => 100.0,
            'line_total' => 2000.0,
        ]);

        // Admin updates the order adding Grade B
        $response = $this->actingAs($this->admin)->post(route('purchasing.shop-orders.store', $shop), [
            'business_date' => '2026-10-27',
            'items' => [
                $product->sku => '15.0',
            ],
            'item_grades' => [
                $product->sku => 'B',
            ],
        ]);

        $response->assertRedirect(route('purchasing.shop-orders.edit', ['shop' => $shop->code, 'date' => '2026-10-27']));

        $order->refresh();
        $this->assertCount(2, $order->items);
        $this->assertDatabaseHas('shop_order_items', [
            'id' => $historicalItem->id,
            'product_grade' => 'A',
            'requested_qty' => 20.0,
        ]);
        $this->assertDatabaseHas('shop_order_items', [
            'shop_order_id' => $order->id,
            'product_grade' => 'B',
            'requested_qty' => 15.0,
        ]);
    }
}
