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
use App\Models\ShopOrderChangeRequest;
use App\Models\ShopOrderChangeRequestItem;
use App\Models\ShopPriceGroup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class ShopOrderMultiGradeLateSubmissionRegressionTest extends TestCase
{
    use RefreshDatabase;

    private ShopPriceGroup $defaultPriceGroup;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('shop', 'web');
        Role::findOrCreate('purchase', 'web');

        Permission::findOrCreate('purchasing.order.approve', 'web');

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

    private function createShopWithUser(string $shopName = 'Test MultiGrade Shop', ?array $allowedGrades = ['A', 'B', 'C']): array
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

    private function createPurchaseManager(): User
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        return $manager;
    }

    private function createProductWithGrades(string $name = 'Tomato H', string $sku = 'TOM-H'): Product
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
            'selling_price' => 100.0,
        ]);

        DailyProductPrice::create([
            'product_id' => $product->id,
            'shop_price_group_id' => $this->defaultPriceGroup->id,
            'grade' => ProductGrade::GradeB,
            'selling_price' => 80.0,
        ]);

        DailyProductPrice::create([
            'product_id' => $product->id,
            'shop_price_group_id' => $this->defaultPriceGroup->id,
            'grade' => ProductGrade::GradeC,
            'selling_price' => 60.0,
        ]);

        return $product;
    }

    public function test_multi_grade_late_submission_creates_distinct_change_request_items_without_500(): void
    {
        // 21:00 is after 18:00 cutoff -> isLate = true
        Carbon::setTestNow(Carbon::parse('2026-10-16 22:30:00', 'Asia/Kolkata'));

        [$shop, $user] = $this->createShopWithUser();
        $product = $this->createProductWithGrades();

        $response = $this->actingAs($user)->post(route('requisitions.store'), [
            'items' => [
                $product->sku => 10,
                $product->sku.'|B' => 11,
                $product->sku.'|C' => 5,
            ],
            'item_units' => [
                $product->sku => 'kg',
                $product->sku.'|B' => 'kg',
                $product->sku.'|C' => 'kg',
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $order = ShopOrder::where('shop_id', $shop->id)->firstOrFail();
        $this->assertTrue((bool) $order->is_late);
        $this->assertCount(3, $order->items);

        // Verify ShopOrderChangeRequest was created
        $changeRequest = ShopOrderChangeRequest::where('shop_order_id', $order->id)
            ->where('type', 'late_submission')
            ->firstOrFail();

        $this->assertSame('pending', $changeRequest->status);

        // Verify 3 distinct change request items are recorded with their grades
        $changeItems = ShopOrderChangeRequestItem::where('shop_order_change_request_id', $changeRequest->id)->get();
        $this->assertCount(3, $changeItems);

        $gradeA = $changeItems->firstWhere('product_grade', 'A');
        $gradeB = $changeItems->firstWhere('product_grade', 'B');
        $gradeC = $changeItems->firstWhere('product_grade', 'C');

        $this->assertNotNull($gradeA);
        $this->assertNotNull($gradeB);
        $this->assertNotNull($gradeC);

        $this->assertEquals(10.0, (float) $gradeA->new_qty);
        $this->assertEquals(11.0, (float) $gradeB->new_qty);
        $this->assertEquals(5.0, (float) $gradeC->new_qty);

        // Each item links to its corresponding shop_order_item_id
        $this->assertNotNull($gradeA->shop_order_item_id);
        $this->assertNotNull($gradeB->shop_order_item_id);
        $this->assertNotNull($gradeC->shop_order_item_id);
    }

    public function test_multi_grade_late_order_update_resubmission(): void
    {
        // 21:00 is after 18:00 cutoff -> isLate = true
        Carbon::setTestNow(Carbon::parse('2026-10-16 22:30:00', 'Asia/Kolkata'));

        [$shop, $user] = $this->createShopWithUser();
        $product = $this->createProductWithGrades();

        // 1. Initial submission
        $this->actingAs($user)->post(route('requisitions.store'), [
            'items' => [
                $product->sku => 10,
                $product->sku.'|B' => 5,
            ],
        ]);

        $order = ShopOrder::where('shop_id', $shop->id)->firstOrFail();

        // 2. Resubmit with updated quantities
        $response = $this->actingAs($user)->post(route('requisitions.store'), [
            'items' => [
                $product->sku => 15,
                $product->sku.'|B' => 8,
                $product->sku.'|C' => 4,
            ],
        ]);

        $response->assertSessionHasNoErrors();

        // Check latest change request of type quantity_update
        $updateRequest = ShopOrderChangeRequest::where('shop_order_id', $order->id)
            ->where('type', 'quantity_update')
            ->latest('id')
            ->firstOrFail();

        $items = $updateRequest->items()->get();
        $this->assertCount(3, $items);

        $itemA = $items->firstWhere('product_grade', 'A');
        $itemB = $items->firstWhere('product_grade', 'B');
        $itemC = $items->firstWhere('product_grade', 'C');

        $this->assertEquals(10.0, (float) $itemA->old_qty);
        $this->assertEquals(15.0, (float) $itemA->new_qty);
        $this->assertEquals(5.0, (float) $itemA->delta_qty);

        $this->assertEquals(5.0, (float) $itemB->old_qty);
        $this->assertEquals(8.0, (float) $itemB->new_qty);
        $this->assertEquals(3.0, (float) $itemB->delta_qty);

        $this->assertEquals(0.0, (float) $itemC->old_qty);
        $this->assertEquals(4.0, (float) $itemC->new_qty);
        $this->assertEquals(4.0, (float) $itemC->delta_qty);
    }

    public function test_purchase_manager_can_approve_late_multi_grade_requisition(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 22:30:00', 'Asia/Kolkata'));

        [$shop, $user] = $this->createShopWithUser();
        $manager = $this->createPurchaseManager();
        $product = $this->createProductWithGrades();

        $this->actingAs($user)->post(route('requisitions.store'), [
            'items' => [
                $product->sku => 10,
                $product->sku.'|B' => 12,
            ],
        ]);

        $order = ShopOrder::where('shop_id', $shop->id)->firstOrFail();
        $changeRequest = ShopOrderChangeRequest::where('shop_order_id', $order->id)->firstOrFail();

        $response = $this->actingAs($manager)->post(route('requisitions.accept-late', $order->order_number));
        $response->assertSessionHasNoErrors();

        $order->refresh();
        $changeRequest->refresh();

        $this->assertFalse((bool) $order->is_late);
        $this->assertSame('approved', $changeRequest->status);
        $this->assertSame($manager->id, $changeRequest->reviewed_by);
    }

    public function test_purchase_manager_can_reject_late_multi_grade_requisition(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 22:30:00', 'Asia/Kolkata'));

        [$shop, $user] = $this->createShopWithUser();
        $manager = $this->createPurchaseManager();
        $product = $this->createProductWithGrades();

        $this->actingAs($user)->post(route('requisitions.store'), [
            'items' => [
                $product->sku => 10,
                $product->sku.'|B' => 12,
            ],
        ]);

        $order = ShopOrder::where('shop_id', $shop->id)->firstOrFail();
        $changeRequest = ShopOrderChangeRequest::where('shop_order_id', $order->id)->firstOrFail();

        $response = $this->actingAs($manager)->post(route('requisitions.reject-late', $order->order_number), [
            'manager_note' => 'Supplier has no stock.',
        ]);
        $response->assertSessionHasNoErrors();

        $order->refresh();
        $changeRequest->refresh();

        $this->assertSame('rejected', $changeRequest->status);
        $this->assertSame($manager->id, $changeRequest->reviewed_by);
    }

    public function test_truncated_submission_fails_safely_without_saving_order(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 10:00:00', 'Asia/Kolkata'));

        [$shop, $user] = $this->createShopWithUser();
        $product = $this->createProductWithGrades();

        // Simulate form truncated by max_input_vars: has _form_submission_start but missing _form_submission_complete
        $response = $this->actingAs($user)->post(route('requisitions.store'), [
            '_form_submission_start' => '1',
            'items' => [
                $product->sku => 10,
            ],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseMissing('shop_orders', [
            'shop_id' => $shop->id,
        ]);
    }

    public function test_complete_form_submission_passes_sentinel_check(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 10:00:00', 'Asia/Kolkata'));

        [$shop, $user] = $this->createShopWithUser();
        $product = $this->createProductWithGrades();

        $response = $this->actingAs($user)->post(route('requisitions.store'), [
            '_form_submission_start' => '1',
            'items' => [
                $product->sku => 10,
                $product->sku.'|B' => 5,
            ],
            '_form_submission_complete' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shop_orders', [
            'shop_id' => $shop->id,
        ]);
    }
}
