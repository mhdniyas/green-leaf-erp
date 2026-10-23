<?php

declare(strict_types=1);

namespace Tests\Feature\Requisition;

use App\Enums\Inventory\ProductGrade;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyProductPrice;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopPriceGroup;
use App\Models\User;
use App\Services\Requisition\MultiGradeOrderGate;
use App\Services\Requisition\ShopOrderItemSyncService;
use App\Services\Requisition\ShopOrderRevisionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class ShopOrderMultiGradePhase1Test extends TestCase
{
    use RefreshDatabase;

    private ShopPriceGroup $defaultPriceGroup;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-16 10:00:00'));

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('shop', 'web');
        Role::findOrCreate('purchase', 'web');

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
    }

    private function createShopWithUser(string $shopName = 'Test Shop', ?array $allowedGrades = null): array
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

    private function createProductWithPrices(string $name, string $sku, float $priceGradeA = 100.0, float $priceGradeB = 80.0): Product
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'name' => $name,
            'sku' => $sku,
            'unit' => 'kg',
            'is_active' => true,
            'show_in_purchaser_order' => true,
        ]);

        // Seed prices for tomorrow
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

        return $product;
    }

    public function test_additional_grade_ordering_is_disabled_by_default(): void
    {
        [$shop, $user] = $this->createShopWithUser();
        $product = $this->createProductWithPrices('Tomato H', 'TOM-H');

        $this->assertFalse(app(MultiGradeOrderGate::class)->isFeatureEnabled());

        // Attempting to submit Grade B line fails when feature is disabled
        $response = $this->actingAs($user)->post(route('requisitions.store'), [
            'items' => [
                $product->sku => 10,
                $product->sku.'|B' => 5,
            ],
            'item_units' => [
                $product->sku => 'kg',
                $product->sku.'|B' => 'kg',
            ],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseMissing('shop_orders', [
            'shop_id' => $shop->id,
        ]);
    }

    public function test_existing_grade_a_only_shop_orders_work_identically_to_baseline(): void
    {
        [$shop, $user] = $this->createShopWithUser();
        $product = $this->createProductWithPrices('Tomato H', 'TOM-H', priceGradeA: 95.0);

        $response = $this->actingAs($user)->post(route('requisitions.store'), [
            'items' => [
                $product->sku => 15,
            ],
            'item_units' => [
                $product->sku => 'kg',
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shop_orders', [
            'shop_id' => $shop->id,
        ]);

        $order = ShopOrder::where('shop_id', $shop->id)->firstOrFail();
        $this->assertCount(1, $order->items);
        $item = $order->items->first();

        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('A', is_string($item->product_grade) ? $item->product_grade : $item->product_grade->value);
        $this->assertEquals(15.0, (float) $item->requested_qty);
        $this->assertEquals(95.0, (float) $item->locked_selling_price);
    }

    public function test_shop_cannot_order_unauthorized_grades_even_if_global_flag_enabled(): void
    {
        BusinessSetting::updateOrCreate(
            ['key' => 'enable_multi_grade_ordering'],
            ['value' => '1', 'type' => 'boolean']
        );

        // Shop Casio has default permissions (only Grade A)
        [$shop, $user] = $this->createShopWithUser('Shop Casio', ['A']);
        $product = $this->createProductWithPrices('Tomato H', 'TOM-H');

        $this->assertTrue(app(MultiGradeOrderGate::class)->isFeatureEnabled());
        $this->assertFalse(app(MultiGradeOrderGate::class)->canShopOrderGrade($shop, 'B'));

        $response = $this->actingAs($user)->post(route('requisitions.store'), [
            'items' => [
                $product->sku => 10,
                $product->sku.'|B' => 5,
            ],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseMissing('shop_orders', ['shop_id' => $shop->id]);
    }

    public function test_rejects_damage_and_unsorted_classifications(): void
    {
        BusinessSetting::updateOrCreate(
            ['key' => 'enable_multi_grade_ordering'],
            ['value' => '1', 'type' => 'boolean']
        );

        [$shop, $user] = $this->createShopWithUser('Shop Casio', ['A', 'B']);
        $product = $this->createProductWithPrices('Tomato H', 'TOM-H');

        $response = $this->actingAs($user)->post(route('requisitions.store'), [
            'items' => [
                $product->sku.'|D' => 10,
            ],
        ]);

        $response->assertSessionHasErrors('items');
    }

    public function test_authorized_multi_grade_order_stores_distinct_lines_with_distinct_prices(): void
    {
        BusinessSetting::updateOrCreate(
            ['key' => 'enable_multi_grade_ordering'],
            ['value' => '1', 'type' => 'boolean']
        );

        [$shop, $user] = $this->createShopWithUser('Shop Multi', ['A', 'B']);
        $product = $this->createProductWithPrices('Tomato H', 'TOM-H', priceGradeA: 100.0, priceGradeB: 75.0);

        $response = $this->actingAs($user)->post(route('requisitions.store'), [
            'items' => [
                $product->sku => 100,
                $product->sku.'|B' => 20,
            ],
            'item_units' => [
                $product->sku => 'kg',
                $product->sku.'|B' => 'kg',
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $order = ShopOrder::where('shop_id', $shop->id)->firstOrFail();
        $this->assertCount(2, $order->items);

        $gradeAItem = $order->items->firstWhere('product_grade', ProductGrade::GradeA);
        $gradeBItem = $order->items->firstWhere('product_grade', ProductGrade::GradeB);

        $this->assertNotNull($gradeAItem);
        $this->assertNotNull($gradeBItem);

        $this->assertSame($product->id, $gradeAItem->product_id);
        $this->assertSame($product->id, $gradeBItem->product_id);

        $this->assertEquals(100.0, (float) $gradeAItem->requested_qty);
        $this->assertEquals(20.0, (float) $gradeBItem->requested_qty);

        // Verify prices are distinct and not silently Grade A for Grade B!
        $this->assertEquals(100.0, (float) $gradeAItem->locked_selling_price);
        $this->assertEquals(75.0, (float) $gradeBItem->locked_selling_price);
    }

    public function test_order_resubmission_does_not_accidentally_delete_different_grades(): void
    {
        BusinessSetting::updateOrCreate(
            ['key' => 'enable_multi_grade_ordering'],
            ['value' => '1', 'type' => 'boolean']
        );

        [$shop, $user] = $this->createShopWithUser('Shop Multi', ['A', 'B']);
        $product = $this->createProductWithPrices('Tomato H', 'TOM-H', priceGradeA: 100.0, priceGradeB: 75.0);

        // Initial submission with A (100) and B (20)
        $this->actingAs($user)->post(route('requisitions.store'), [
            'items' => [
                $product->sku => 100,
                $product->sku.'|B' => 20,
            ],
        ]);

        $order = ShopOrder::where('shop_id', $shop->id)->firstOrFail();
        $this->assertCount(2, $order->items);

        // Resubmit updating Grade A to 120 while keeping Grade B 20
        $this->actingAs($user)->post(route('requisitions.store'), [
            'items' => [
                $product->sku => 120,
                $product->sku.'|B' => 20,
            ],
        ]);

        $order->refresh();
        $this->assertCount(2, $order->items);

        $gradeAItem = $order->items->firstWhere('product_grade', ProductGrade::GradeA);
        $gradeBItem = $order->items->firstWhere('product_grade', ProductGrade::GradeB);

        $this->assertEquals(120.0, (float) $gradeAItem->requested_qty);
        $this->assertEquals(20.0, (float) $gradeBItem->requested_qty);
    }

    public function test_admin_can_configure_allowed_grades_and_non_admin_cannot(): void
    {
        Permission::findOrCreate('sales.customer.view', 'web');

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->givePermissionTo('sales.customer.view');
        $admin->givePermissionTo('sales.customer.create');
        $admin->givePermissionTo('sales.customer.update');

        $staff = User::factory()->create();
        $staff->givePermissionTo('sales.customer.view');
        $staff->givePermissionTo('sales.customer.create');
        $staff->givePermissionTo('sales.customer.update');

        // Non-admin attempting to configure allowed_product_grades gets validation error
        $response = $this->actingAs($staff)->post(route('sales.customers.shops.store'), [
            'name' => 'Staff Shop',
            'code' => 'STF_SHOP',
            'destination_type' => 'direct',
            'status' => 'active',
            'allowed_product_grades' => ['A', 'B'],
        ]);
        $response->assertSessionHasErrors('allowed_product_grades');

        // Admin can configure allowed_product_grades
        $response = $this->actingAs($admin)->post(route('sales.customers.shops.store'), [
            'name' => 'Admin Shop',
            'code' => 'ADM_SHOP',
            'destination_type' => 'direct',
            'status' => 'active',
            'allowed_product_grades' => ['A', 'B'],
        ]);
        $response->assertSessionHasNoErrors();

        $shop = Shop::where('code', 'ADM_SHOP')->firstOrFail();
        $this->assertSame(['A', 'B'], $shop->allowedProductGrades());
        $this->assertTrue($shop->canOrderGrade('B'));
        $this->assertFalse($shop->canOrderGrade('C'));
    }

    public function test_revision_service_preserves_distinct_grades(): void
    {
        BusinessSetting::updateOrCreate(
            ['key' => 'enable_multi_grade_ordering'],
            ['value' => '1', 'type' => 'boolean']
        );

        [$shop, $user] = $this->createShopWithUser('Shop Multi', ['A', 'B']);
        $product = $this->createProductWithPrices('Tomato H', 'TOM-H', priceGradeA: 100.0, priceGradeB: 75.0);

        // Submit initial order
        $this->actingAs($user)->post(route('requisitions.store'), [
            'items' => [
                $product->sku => 50,
                $product->sku.'|B' => 30,
            ],
        ]);

        $order = ShopOrder::where('shop_id', $shop->id)->firstOrFail();
        $order->update(['state' => 'approved']);
        $order->items()->each(fn ($i) => $i->update(['approved_qty' => $i->requested_qty]));

        // Create revision changing Grade B from 30 to 40
        $revisionService = app(ShopOrderRevisionService::class);
        $syncService = app(ShopOrderItemSyncService::class);

        $resolvedItems = $syncService->resolveRequestedProducts([
            $product->sku => 50,
            $product->sku.'|B' => 40,
        ]);

        $revision = $revisionService->createApprovedOrderRevision(
            $order,
            $resolvedItems,
            $user,
            'Increase Grade B'
        );

        $this->assertNotNull($revision);
        $this->assertCount(1, $revision->items);
        $revItem = $revision->items->first();
        $this->assertSame('B', $revItem->product_grade);
        $this->assertEquals(30.0, (float) $revItem->old_requested_qty);
        $this->assertEquals(40.0, (float) $revItem->new_requested_qty);

        // Apply pending revision
        $reviewer = User::factory()->create();
        $revisionService->applyPendingRevision($order, $reviewer);

        $order->refresh();
        $this->assertCount(2, $order->items);

        $gradeA = $order->items->firstWhere('product_grade', ProductGrade::GradeA);
        $gradeB = $order->items->firstWhere('product_grade', ProductGrade::GradeB);

        $this->assertEquals(50.0, (float) $gradeA->approved_qty);
        $this->assertEquals(40.0, (float) $gradeB->approved_qty);
    }
}
