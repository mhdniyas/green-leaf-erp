<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Inventory\ProductGrade;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyProductPrice;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopPriceGroup;
use App\Models\User;
use App\Services\ShopInvoices\ShopInvoiceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class ShopOwnerOrderGradePersistenceTest extends TestCase
{
    use RefreshDatabase;

    private ShopPriceGroup $defaultPriceGroup;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-24 10:00:00'));

        Role::findOrCreate('admin', 'web');
        $shopRole = Role::findOrCreate('shop', 'web');
        Role::findOrCreate('purchase', 'web');

        $salesOrderPermission = Permission::findOrCreate('sales.order.create', 'web');
        $shopRole->givePermissionTo($salesOrderPermission);

        Permission::findOrCreate('sales.customer.create', 'web');
        Permission::findOrCreate('sales.customer.update', 'web');

        $this->defaultPriceGroup = ShopPriceGroup::factory()->create([
            'name' => 'A',
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

    private function createShopWithUser(string $shopName = 'Ashirwad Test Shop', ?array $allowedGrades = ['A', 'B', 'C']): array
    {
        $shop = Shop::factory()->create([
            'name' => $shopName,
            'shop_price_group_id' => $this->defaultPriceGroup->id,
            'allowed_product_grades' => $allowedGrades,
        ]);
        $user = User::factory()->create(['shop_id' => $shop->id]);
        $user->assignRole('shop');

        return [$shop, $user];
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
            'shop_price_group_id' => $this->defaultPriceGroup->id,
            'grade' => ProductGrade::GradeA,
            'selling_price' => $priceGradeA,
        ]);

        DailyProductPrice::create([
            'product_id' => $product->id,
            'shop_price_group_id' => $this->defaultPriceGroup->id,
            'grade' => ProductGrade::GradeB,
            'selling_price' => $priceGradeB,
        ]);

        DailyProductPrice::create([
            'product_id' => $product->id,
            'shop_price_group_id' => $this->defaultPriceGroup->id,
            'grade' => ProductGrade::GradeC,
            'selling_price' => $priceGradeC,
        ]);

        return $product;
    }

    public function test_grade_a_only_order_submission_redirects_to_orders_history(): void
    {
        [$shop, $user] = $this->createShopWithUser('Casio Shop', ['A']);
        $product = $this->createProductWithPrices('Tomato H', 'TOM-H', priceGradeA: 95.0);

        $response = $this->actingAs($user)->post(route('requisitions.store'), [
            '_form_submission_start' => '1',
            '_form_submission_complete' => '1',
            'items' => [
                $product->sku => 15,
            ],
            'item_units' => [
                $product->sku => 'kg',
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('shop-owner.orders.history'));

        $this->assertDatabaseHas('shop_orders', [
            'shop_id' => $shop->id,
            'state' => 'submitted',
        ]);

        $order = ShopOrder::where('shop_id', $shop->id)->firstOrFail();
        $this->assertCount(1, $order->items);
        $this->assertEquals(15.0, (float) $order->items->first()->requested_qty);
        $this->assertSame('A', $order->items->first()->product_grade->value ?? (string) $order->items->first()->product_grade);
    }

    public function test_multi_grade_order_submission_persists_separate_grade_rows_and_redirects_to_history(): void
    {
        [$shop, $user] = $this->createShopWithUser('Ashirwad', ['A', 'B', 'C']);
        $tomato = $this->createProductWithPrices('Tomato H', 'TOM-H', priceGradeA: 100.0, priceGradeB: 80.0, priceGradeC: 60.0);

        $response = $this->actingAs($user)->post(route('requisitions.store'), [
            '_form_submission_start' => '1',
            '_form_submission_complete' => '1',
            'items' => [
                $tomato->sku => 11,
                $tomato->sku.'|B' => 2,
                $tomato->sku.'|C' => 2,
            ],
            'item_units' => [
                $tomato->sku => 'kg',
                $tomato->sku.'|B' => 'kg',
                $tomato->sku.'|C' => 'kg',
            ],
            'item_grades' => [
                $tomato->sku.'|B' => 'B',
                $tomato->sku.'|C' => 'C',
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('shop-owner.orders.history'));

        $order = ShopOrder::where('shop_id', $shop->id)->with('items.product')->firstOrFail();
        $this->assertSame('submitted', $order->state);
        $this->assertSame('Submitted', $order->displayStateLabel());
        $this->assertCount(3, $order->items);

        $itemsByGrade = $order->items->keyBy(fn ($i) => $i->product_grade->value ?? (string) $i->product_grade);

        $this->assertTrue($itemsByGrade->has('A'));
        $this->assertEquals(11.0, (float) $itemsByGrade->get('A')->requested_qty);

        $this->assertTrue($itemsByGrade->has('B'));
        $this->assertEquals(2.0, (float) $itemsByGrade->get('B')->requested_qty);

        $this->assertTrue($itemsByGrade->has('C'));
        $this->assertEquals(2.0, (float) $itemsByGrade->get('C')->requested_qty);

        // Verify total selection matches 15 KG across 3 lines
        $this->assertEquals(15.0, (float) $order->items->sum('requested_qty'));
    }

    public function test_json_ajax_submission_returns_redirect_url_to_orders_history(): void
    {
        [$shop, $user] = $this->createShopWithUser('Ashirwad', ['A', 'B', 'C']);
        $tomato = $this->createProductWithPrices('Tomato H', 'TOM-H');

        $response = $this->actingAs($user)->postJson(route('requisitions.store'), [
            '_form_submission_start' => '1',
            '_form_submission_complete' => '1',
            'items' => [
                $tomato->sku => 11,
                $tomato->sku.'|B' => 2,
                $tomato->sku.'|C' => 2,
            ],
            'item_units' => [
                $tomato->sku => 'kg',
                $tomato->sku.'|B' => 'kg',
                $tomato->sku.'|C' => 'kg',
            ],
            'item_grades' => [
                $tomato->sku.'|B' => 'B',
                $tomato->sku.'|C' => 'C',
            ],
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'redirect_url' => route('shop-owner.orders.history'),
        ]);

        $this->assertDatabaseHas('shop_orders', [
            'shop_id' => $shop->id,
            'state' => 'submitted',
        ]);
    }

    public function test_empty_submission_fails_cleanly_without_saving_order(): void
    {
        [$shop, $user] = $this->createShopWithUser('Ashirwad', ['A', 'B', 'C']);

        $response = $this->actingAs($user)->postJson(route('requisitions.store'), [
            '_form_submission_start' => '1',
            '_form_submission_complete' => '1',
            'items' => [],
        ]);

        $response->assertStatus(400);
        $this->assertDatabaseMissing('shop_orders', ['shop_id' => $shop->id]);
    }

    public function test_truncated_submission_missing_complete_token_is_rejected(): void
    {
        [$shop, $user] = $this->createShopWithUser('Ashirwad', ['A', 'B', 'C']);
        $tomato = $this->createProductWithPrices('Tomato H', 'TOM-H');

        // Sending _form_submission_start WITHOUT _form_submission_complete
        $response = $this->actingAs($user)->post(route('requisitions.store'), [
            '_form_submission_start' => '1',
            'items' => [
                $tomato->sku => 10,
            ],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseMissing('shop_orders', ['shop_id' => $shop->id]);
    }

    public function test_create_order_view_renders_no_nested_form_inside_order_form(): void
    {
        [$shop, $user] = $this->createShopWithUser('Ashirwad', ['A', 'B', 'C']);
        $this->createProductWithPrices('Tomato H', 'TOM-H');

        $response = $this->actingAs($user)->get(route('shop-owner.orders.create'));
        $response->assertOk();

        $content = $response->getContent();

        // Ensure order form contains the completion token directly before closing </form>
        $this->assertStringContainsString('id="shop-owner-order-form"', $content);
        $this->assertStringContainsString('name="_form_submission_complete"', $content);
        $this->assertStringContainsString('id="cart-review-drawer"', $content);
        $this->assertStringContainsString('id="place-order-submit-btn"', $content);

        // Verify that <form data-save-preset-form> is not nested inside shop-owner-order-form
        $orderFormPos = strpos($content, 'id="shop-owner-order-form"');
        $this->assertNotFalse($orderFormPos);
        $orderFormClosePos = strpos($content, '</form>', $orderFormPos);
        $this->assertNotFalse($orderFormClosePos);

        // Before our fix, preset form was inside the order form.
        // Now, data-save-preset-form must be AFTER the order form closing tag:
        $presetFormPos = strpos($content, 'data-save-preset-form');
        $this->assertNotFalse($presetFormPos);
        $this->assertGreaterThan($orderFormClosePos, $presetFormPos, 'Preset form must not be nested inside shop-owner-order-form');
    }

    public function test_submitted_order_appears_in_history_page_under_pending_request_status(): void
    {
        [$shop, $user] = $this->createShopWithUser('Ashirwad', ['A', 'B', 'C']);
        $tomato = $this->createProductWithPrices('Tomato H', 'TOM-H');

        // Submit order
        $this->actingAs($user)->post(route('requisitions.store'), [
            '_form_submission_start' => '1',
            '_form_submission_complete' => '1',
            'items' => [
                $tomato->sku => 11,
                $tomato->sku.'|B' => 2,
                $tomato->sku.'|C' => 2,
            ],
            'item_units' => [
                $tomato->sku => 'kg',
                $tomato->sku.'|B' => 'kg',
                $tomato->sku.'|C' => 'kg',
            ],
            'item_grades' => [
                $tomato->sku.'|B' => 'B',
                $tomato->sku.'|C' => 'C',
            ],
        ])->assertRedirect(route('shop-owner.orders.history'));

        $order = ShopOrder::where('shop_id', $shop->id)->firstOrFail();

        // Visit history page
        $historyResponse = $this->actingAs($user)->get(route('shop-owner.orders.history'));
        $historyResponse->assertOk();
        $historyResponse->assertSee($order->order_number);
        $historyResponse->assertSee($order->displayStateLabel()); // 'Submitted'
    }

    public function test_submitting_again_updates_existing_order_without_duplicate(): void
    {
        [$shop, $user] = $this->createShopWithUser('Ashirwad', ['A', 'B', 'C']);
        $tomato = $this->createProductWithPrices('Tomato H', 'TOM-H');

        // First submission: 5 KG Grade A
        $this->actingAs($user)->post(route('requisitions.store'), [
            '_form_submission_start' => '1',
            '_form_submission_complete' => '1',
            'items' => [$tomato->sku => 5],
            'item_units' => [$tomato->sku => 'kg'],
        ])->assertRedirect(route('shop-owner.orders.history'));

        $this->assertEquals(1, ShopOrder::where('shop_id', $shop->id)->count());

        // Second submission: update to 11 KG Grade A + 2 KG Grade B + 2 KG Grade C
        $this->actingAs($user)->post(route('requisitions.store'), [
            '_form_submission_start' => '1',
            '_form_submission_complete' => '1',
            'items' => [
                $tomato->sku => 11,
                $tomato->sku.'|B' => 2,
                $tomato->sku.'|C' => 2,
            ],
            'item_units' => [
                $tomato->sku => 'kg',
                $tomato->sku.'|B' => 'kg',
                $tomato->sku.'|C' => 'kg',
            ],
            'item_grades' => [
                $tomato->sku.'|B' => 'B',
                $tomato->sku.'|C' => 'C',
            ],
        ])->assertRedirect(route('shop-owner.orders.history'));

        // Still only 1 order in the database for tomorrow
        $this->assertEquals(1, ShopOrder::where('shop_id', $shop->id)->count());
        $order = ShopOrder::where('shop_id', $shop->id)->first();
        $this->assertCount(3, $order->items);
        $this->assertEquals(15.0, (float) $order->items->sum('requested_qty'));
    }

    public function test_pending_order_submission_with_unresolved_grade_b_price_succeeds_as_pending_request(): void
    {
        [$shop, $user] = $this->createShopWithUser('Ashirwad', ['A', 'B', 'C']);
        // Create Potato Local where Grade B has 0.00 selling price
        $potato = $this->createProductWithPrices('Potato Local', 'POT-LOC', priceGradeA: 30.0, priceGradeB: 0.0, priceGradeC: 24.0);

        $response = $this->actingAs($user)->post(route('requisitions.store'), [
            '_form_submission_start' => '1',
            '_form_submission_complete' => '1',
            'items' => [
                $potato->sku => 10,
                $potato->sku.'|B' => 33,
            ],
            'item_units' => [
                $potato->sku => 'kg',
                $potato->sku.'|B' => 'kg',
            ],
            'item_grades' => [
                $potato->sku.'|B' => 'B',
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('shop-owner.orders.history'));

        $order = ShopOrder::where('shop_id', $shop->id)->with('items.product')->firstOrFail();
        $this->assertSame('submitted', $order->state);
        $this->assertSame('Submitted', $order->displayStateLabel());
        $this->assertCount(2, $order->items);

        $gradeBItem = $order->items->firstWhere('product_grade', ProductGrade::GradeB);
        $this->assertNotNull($gradeBItem);
        $this->assertEquals(33.0, (float) $gradeBItem->requested_qty);
        $this->assertEquals(0.0, (float) $gradeBItem->locked_selling_price);
        $this->assertSame('unresolved', $gradeBItem->locked_price_source);
        $this->assertEquals(0.0, (float) $gradeBItem->line_total);

        // Financial Safety invariant: invoice generation must skip unpriced orders
        $order->update(['state' => 'approved']);
        $gradeBItem->update([
            'approved_qty' => 33,
            'loaded_qty' => 33,
            'sorting_status' => 'loaded',
        ]);

        $summary = app(ShopInvoiceService::class)->generateForBusinessDate(
            $order->business_date->toDateString(),
            $user->id
        );

        $this->assertEquals(0, $summary['generated']);
        $this->assertCount(1, $summary['skipped']);
        $this->assertSame('Potato Local (Grade B)', $summary['skipped'][0]['products'][0]);
        $this->assertDatabaseMissing('shop_invoices', ['shop_order_id' => $order->id]);
    }
}
