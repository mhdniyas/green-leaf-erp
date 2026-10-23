<?php

declare(strict_types=1);

namespace Tests\Feature\Requisition;

use App\Enums\Inventory\ProductGrade;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DailyProductPrice;
use App\Models\Product;
use App\Models\PurchaseGradePrice;
use App\Models\PurchaserCart;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderRevision;
use App\Models\ShopPriceGroup;
use App\Models\User;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Purchasing\PurchaserDailyInitialQuery;
use App\Services\Requisition\MultiGradeOrderGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class ShopOrderMultiGradePhase2Test extends TestCase
{
    use RefreshDatabase;

    private ShopPriceGroup $defaultPriceGroup;

    private Category $category;

    private User $managerUser;

    private User $purchaserUser;

    private string $businessDate;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('shop', 'web');
        Role::findOrCreate('purchase', 'web');
        Role::findOrCreate('purchaser', 'web');

        Permission::findOrCreate('purchasing.order.approve', 'web');
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

        $this->managerUser = User::factory()->create();
        $this->managerUser->assignRole('purchase');

        $this->purchaserUser = User::factory()->create();
        $this->purchaserUser->assignRole('purchaser');

        $this->businessDate = app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();
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

    private function createProduct(string $name = 'Tomato H', string $sku = 'TOM-H'): Product
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

        return $product;
    }

    private function createShopOrder(array $attributes): ShopOrder
    {
        $attributes['created_by'] ??= $this->managerUser->id;

        return ShopOrder::create($attributes);
    }

    /**
     * 1. Current Grade A-only requisition behavior.
     */
    public function test_grade_a_only_requisition_board_presentation_is_standard(): void
    {
        [$shop, $user] = $this->createShopWithUser('Shop A');
        $product = $this->createProduct('Cucumber', 'CUC-1');

        $order = $this->createShopOrder([
            'shop_id' => $shop->id,
            'business_date' => $this->businessDate,
            'state' => 'submitted',
            'submitted_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 50,
            'unit' => 'kg',
            'fulfillment_type' => 'warehouse',
        ]);

        $response = $this->actingAs($this->managerUser)
            ->get(route('requisitions.board', ['date' => $this->businessDate]));

        $response->assertOk();
        $response->assertSee('Cucumber');
        $response->assertDontSee('Multi-grade');
    }

    /**
     * 2. Shop order with Tomato Grade A and Grade B.
     */
    public function test_shop_order_with_tomato_grade_a_and_b_displays_distinct_grade_lines_on_board(): void
    {
        [$shop, $user] = $this->createShopWithUser('Shop Multi', ['A', 'B']);
        $product = $this->createProduct('Tomato H', 'TOM-H');

        $order = $this->createShopOrder([
            'shop_id' => $shop->id,
            'business_date' => $this->businessDate,
            'state' => 'submitted',
            'submitted_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 100,
            'unit' => 'kg',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_grade' => 'B',
            'requested_qty' => 20,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($this->managerUser)
            ->get(route('requisitions.board', ['date' => $this->businessDate]));

        $response->assertOk();
        $response->assertSee('Tomato H');
        $response->assertSee('Grade B');
    }

    /**
     * 3. Separate grade-wise manager approval.
     */
    public function test_separate_grade_wise_manager_approval_on_review(): void
    {
        [$shop, $user] = $this->createShopWithUser('Shop Multi', ['A', 'B']);
        $product = $this->createProduct('Tomato H', 'TOM-H');

        $order = $this->createShopOrder([
            'shop_id' => $shop->id,
            'order_number' => 'ORD-REV-001',
            'business_date' => $this->businessDate,
            'state' => 'submitted',
            'submitted_at' => now(),
        ]);

        $itemA = $order->items()->create([
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 100,
            'unit' => 'kg',
        ]);

        $itemB = $order->items()->create([
            'product_id' => $product->id,
            'product_grade' => 'B',
            'requested_qty' => 20,
            'unit' => 'kg',
        ]);

        // Manager approves 90 kg for A, and 15 kg for B
        $response = $this->actingAs($this->managerUser)
            ->post(route('requisitions.review', $order->order_number), [
                'action' => 'approve',
                'approved_qty' => [
                    $itemA->id => 90,
                    $itemB->id => 15,
                ],
            ]);

        $response->assertRedirect();

        $itemA->refresh();
        $itemB->refresh();

        $this->assertEquals(90.0, (float) $itemA->approved_qty);
        $this->assertEquals(15.0, (float) $itemB->approved_qty);
        $this->assertEquals(100.0, (float) $itemA->requested_qty);
        $this->assertEquals(20.0, (float) $itemB->requested_qty);
    }

    /**
     * 4. Quantity update to B without modifying A.
     */
    public function test_approved_board_save_updates_b_without_modifying_a(): void
    {
        [$shop, $user] = $this->createShopWithUser('Shop Multi', ['A', 'B']);
        $product = $this->createProduct('Tomato H', 'TOM-H');

        $order = $this->createShopOrder([
            'shop_id' => $shop->id,
            'order_number' => 'ORD-APP-001',
            'business_date' => $this->businessDate,
            'state' => 'approved',
            'submitted_at' => now(),
        ]);

        $itemA = $order->items()->create([
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 100,
            'approved_qty' => 100,
            'unit' => 'kg',
        ]);

        $itemB = $order->items()->create([
            'product_id' => $product->id,
            'product_grade' => 'B',
            'requested_qty' => 20,
            'approved_qty' => 20,
            'unit' => 'kg',
        ]);

        // Manager saves approved board changing B to 25, without touching A
        $response = $this->actingAs($this->managerUser)
            ->post(route('requisitions.approved_board.save'), [
                'date' => $this->businessDate,
                'quantities' => [
                    $product->id.'|A' => [$shop->id => 100],
                    $product->id.'|B' => [$shop->id => 25],
                ],
            ]);

        $response->assertRedirect();

        $itemA->refresh();
        $itemB->refresh();

        $this->assertEquals(100.0, (float) $itemA->approved_qty);
        $this->assertEquals(25.0, (float) $itemB->approved_qty);
    }

    /**
     * 5. Late revision preserving both grades.
     */
    public function test_late_revision_preserves_both_grades(): void
    {
        [$shop, $user] = $this->createShopWithUser('Shop Multi', ['A', 'B']);
        $product = $this->createProduct('Tomato H', 'TOM-H');

        $order = $this->createShopOrder([
            'shop_id' => $shop->id,
            'order_number' => 'ORD-REV-002',
            'business_date' => $this->businessDate,
            'state' => 'approved',
            'has_pending_revision' => true,
            'submitted_at' => now(),
        ]);

        $itemA = $order->items()->create([
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 100,
            'approved_qty' => 100,
            'unit' => 'kg',
        ]);

        $itemB = $order->items()->create([
            'product_id' => $product->id,
            'product_grade' => 'B',
            'requested_qty' => 20,
            'approved_qty' => 20,
            'unit' => 'kg',
        ]);

        $revision = ShopOrderRevision::create([
            'shop_order_id' => $order->id,
            'revision_no' => 2,
            'status' => 'pending',
            'reason' => 'Need more B-grade',
            'requested_by' => $user->id,
        ]);

        $revision->items()->create([
            'product_id' => $product->id,
            'product_grade' => 'A',
            'old_requested_qty' => 100,
            'new_requested_qty' => 100,
            'delta_qty' => 0,
        ]);

        $revision->items()->create([
            'product_id' => $product->id,
            'product_grade' => 'B',
            'old_requested_qty' => 20,
            'new_requested_qty' => 30,
            'delta_qty' => 10,
        ]);

        // Manager approves revision
        $response = $this->actingAs($this->managerUser)
            ->post(route('requisitions.approve-update', $order->order_number), [
                'approved_qty' => [
                    $product->id.'|A' => 100,
                    $product->id.'|B' => 30,
                ],
            ]);

        $response->assertRedirect();

        $itemA->refresh();
        $itemB->refresh();

        $this->assertEquals(100.0, (float) $itemA->approved_qty);
        $this->assertEquals(30.0, (float) $itemB->approved_qty);
    }

    /**
     * 6. Purchaser product-level totals with grade split.
     */
    public function test_purchaser_product_level_totals_with_grade_split(): void
    {
        [$shop, $user] = $this->createShopWithUser('Shop Multi', ['A', 'B', 'C']);
        $product = $this->createProduct('Tomato H', 'TOM-H');

        $order = $this->createShopOrder([
            'shop_id' => $shop->id,
            'business_date' => $this->businessDate,
            'state' => 'approved',
            'submitted_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 100,
            'approved_qty' => 100,
            'unit' => 'kg',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_grade' => 'B',
            'requested_qty' => 20,
            'approved_qty' => 20,
            'unit' => 'kg',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_grade' => 'C',
            'requested_qty' => 80,
            'approved_qty' => 80,
            'unit' => 'kg',
        ]);

        $query = app(PurchaserDailyInitialQuery::class);
        $items = $query->getPendingDemand(Carbon::parse($this->businessDate));

        $this->assertCount(1, $items);
        $demand = $items->first();

        $this->assertSame($product->id, $demand['product_id']);
        $this->assertEquals(200.0, $demand['total_approved_qty']);
        $this->assertTrue($demand['has_non_a_grades']);
        $this->assertCount(3, $demand['grade_breakdown']);

        $grades = collect($demand['grade_breakdown'])->keyBy('grade');
        $this->assertEquals(100.0, $grades['A']['approved_qty']);
        $this->assertEquals(20.0, $grades['B']['approved_qty']);
        $this->assertEquals(80.0, $grades['C']['approved_qty']);
    }

    /**
     * 7. Purchased and remaining quantities per grade calculated independently.
     */
    public function test_purchased_and_remaining_quantities_per_grade_calculated_independently(): void
    {
        [$shop, $user] = $this->createShopWithUser('Shop Multi', ['A', 'B', 'C']);
        $product = $this->createProduct('Tomato H', 'TOM-H');

        $order = $this->createShopOrder([
            'shop_id' => $shop->id,
            'business_date' => $this->businessDate,
            'state' => 'approved',
            'submitted_at' => now(),
        ]);

        $order->items()->createMany([
            ['product_id' => $product->id, 'product_grade' => 'A', 'requested_qty' => 100, 'approved_qty' => 100, 'unit' => 'kg'],
            ['product_id' => $product->id, 'product_grade' => 'B', 'requested_qty' => 20, 'approved_qty' => 20, 'unit' => 'kg'],
            ['product_id' => $product->id, 'product_grade' => 'C', 'requested_qty' => 80, 'approved_qty' => 80, 'unit' => 'kg'],
        ]);

        // Cart with Grade A (40 kg) submitted
        $cartA = PurchaserCart::create([
            'user_id' => $this->purchaserUser->id,
            'business_date' => $this->businessDate,
            'cart_number' => 'CART-A-01',
            'status' => 'submitted',
            'purchase_grade' => 'A',
        ]);
        $cartA->items()->create([
            'product_id' => $product->id,
            'grade' => 'A',
            'quantity' => 40,
            'unit_price' => 50,
            'line_total' => 2000,
        ]);

        // Cart with Grade B (5 kg) submitted
        $cartB = PurchaserCart::create([
            'user_id' => $this->purchaserUser->id,
            'business_date' => $this->businessDate,
            'cart_number' => 'CART-B-01',
            'status' => 'submitted',
            'purchase_grade' => 'B',
        ]);
        $cartB->items()->create([
            'product_id' => $product->id,
            'grade' => 'B',
            'quantity' => 5,
            'unit_price' => 40,
            'line_total' => 200,
        ]);

        $query = app(PurchaserDailyInitialQuery::class);
        $items = $query->getPendingDemand(Carbon::parse($this->businessDate));

        $demand = $items->first();
        $this->assertEquals(200.0, $demand['total_approved_qty']);
        $this->assertEquals(45.0, $demand['bought_qty']);
        $this->assertEquals(155.0, $demand['remaining_qty']);

        $grades = collect($demand['grade_breakdown'])->keyBy('grade');

        // Grade A: 100 approved - 40 bought = 60 remaining
        $this->assertEquals(100.0, $grades['A']['approved_qty']);
        $this->assertEquals(40.0, $grades['A']['bought_qty']);
        $this->assertEquals(60.0, $grades['A']['remaining_qty']);

        // Grade B: 20 approved - 5 bought = 15 remaining
        $this->assertEquals(20.0, $grades['B']['approved_qty']);
        $this->assertEquals(5.0, $grades['B']['bought_qty']);
        $this->assertEquals(15.0, $grades['B']['remaining_qty']);

        // Grade C: 80 approved - 0 bought = 80 remaining
        $this->assertEquals(80.0, $grades['C']['approved_qty']);
        $this->assertEquals(0.0, $grades['C']['bought_qty']);
        $this->assertEquals(80.0, $grades['C']['remaining_qty']);
    }

    /**
     * 8. Grade-specific cart creation.
     */
    public function test_grade_specific_cart_creation_from_daily_dashboard(): void
    {
        [$shop, $user] = $this->createShopWithUser('Shop Multi', ['A', 'B']);
        $product = $this->createProduct('Tomato H', 'TOM-H');

        $order = $this->createShopOrder([
            'shop_id' => $shop->id,
            'business_date' => $this->businessDate,
            'state' => 'approved',
            'submitted_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_grade' => 'B',
            'requested_qty' => 20,
            'approved_qty' => 20,
            'unit' => 'kg',
        ]);

        PurchaseGradePrice::create([
            'product_id' => $product->id,
            'business_date' => $this->businessDate,
            'grade' => ProductGrade::GradeB,
            'purchase_price' => 35.0,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->purchaserUser)
            ->post(route('purchaser.cart-items.store'), [
                'business_date' => $this->businessDate,
                'product_id' => $product->id,
                'purchase_grade' => 'B',
                'quantity' => 10,
                'unit_price' => 35.0,
                'return_to' => 'daily',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $cart = PurchaserCart::where('user_id', $this->purchaserUser->id)
            ->whereDate('business_date', $this->businessDate)
            ->where('purchase_grade', 'B')
            ->first();

        $this->assertNotNull($cart);
        $this->assertCount(1, $cart->items);

        $cartItem = $cart->items->first();
        $this->assertSame($product->id, $cartItem->product_id);
        $this->assertSame('B', $cartItem->grade);
        $this->assertEquals(10.0, (float) $cartItem->quantity);
    }

    /**
     * 9. Repeated, partial, and cancelled purchases.
     */
    public function test_repeated_partial_and_cancelled_purchases(): void
    {
        [$shop, $user] = $this->createShopWithUser('Shop Multi', ['A', 'B']);
        $product = $this->createProduct('Tomato H', 'TOM-H');

        $order = $this->createShopOrder([
            'shop_id' => $shop->id,
            'business_date' => $this->businessDate,
            'state' => 'approved',
            'submitted_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_grade' => 'B',
            'requested_qty' => 50,
            'approved_qty' => 50,
            'unit' => 'kg',
        ]);

        // 1. Partial purchase 1 (submitted)
        $cart1 = PurchaserCart::create([
            'user_id' => $this->purchaserUser->id,
            'business_date' => $this->businessDate,
            'cart_number' => 'CART-PARTIAL-1',
            'status' => 'submitted',
            'purchase_grade' => 'B',
        ]);
        $cart1->items()->create([
            'product_id' => $product->id,
            'grade' => 'B',
            'quantity' => 20,
            'unit_price' => 40,
            'line_total' => 800,
        ]);

        // 2. Draft cart (not yet submitted, should not count as bought)
        $cartDraft = PurchaserCart::create([
            'user_id' => $this->purchaserUser->id,
            'business_date' => $this->businessDate,
            'cart_number' => 'CART-DRAFT-1',
            'status' => 'draft',
            'purchase_grade' => 'B',
        ]);
        $cartDraft->items()->create([
            'product_id' => $product->id,
            'grade' => 'B',
            'quantity' => 10,
            'unit_price' => 40,
            'line_total' => 400,
        ]);

        // 3. Cancelled cart (should not count towards bought)
        $cartCancelled = PurchaserCart::create([
            'user_id' => $this->purchaserUser->id,
            'business_date' => $this->businessDate,
            'cart_number' => 'CART-CANCELLED-1',
            'status' => 'cancelled',
            'purchase_grade' => 'B',
        ]);
        $cartCancelled->items()->create([
            'product_id' => $product->id,
            'grade' => 'B',
            'quantity' => 15,
            'unit_price' => 40,
            'line_total' => 600,
        ]);

        $query = app(PurchaserDailyInitialQuery::class);
        $items = $query->getPendingDemand(Carbon::parse($this->businessDate));

        $demand = $items->first();
        $this->assertEquals(50.0, $demand['total_approved_qty']);
        $this->assertEquals(20.0, $demand['bought_qty']); // Only cart1!
        $this->assertEquals(10.0, $demand['draft_qty']);  // cartDraft
        $this->assertEquals(30.0, $demand['remaining_qty']); // 50 - 20
    }

    /**
     * 10. Correct shop and business-date isolation.
     */
    public function test_correct_shop_and_business_date_isolation(): void
    {
        [$shop1] = $this->createShopWithUser('Shop 1', ['A', 'B']);
        [$shop2] = $this->createShopWithUser('Shop 2', ['A', 'B']);
        $product = $this->createProduct('Tomato H', 'TOM-H');

        $today = $this->businessDate;
        $tomorrow = Carbon::parse($today)->addDay()->toDateString();

        // Shop 1 order for Today
        $orderToday = $this->createShopOrder([
            'shop_id' => $shop1->id,
            'business_date' => $today,
            'state' => 'approved',
            'submitted_at' => now(),
        ]);
        $orderToday->items()->create([
            'product_id' => $product->id,
            'product_grade' => 'B',
            'requested_qty' => 20,
            'approved_qty' => 20,
            'unit' => 'kg',
        ]);

        // Shop 2 order for Tomorrow
        $orderTomorrow = $this->createShopOrder([
            'shop_id' => $shop2->id,
            'business_date' => $tomorrow,
            'state' => 'approved',
            'submitted_at' => now(),
        ]);
        $orderTomorrow->items()->create([
            'product_id' => $product->id,
            'product_grade' => 'B',
            'requested_qty' => 70,
            'approved_qty' => 70,
            'unit' => 'kg',
        ]);

        $query = app(PurchaserDailyInitialQuery::class);

        // Query today's demand
        $todayDemand = $query->getPendingDemand(Carbon::parse($today));
        $this->assertEquals(20.0, $todayDemand->first()['total_approved_qty']);

        // Query tomorrow's demand
        $tomorrowDemand = $query->getPendingDemand(Carbon::parse($tomorrow));
        $this->assertEquals(70.0, $tomorrowDemand->first()['total_approved_qty']);
    }

    /**
     * 11. Existing Grade A purchaser presentation.
     */
    public function test_existing_grade_a_purchaser_presentation(): void
    {
        [$shop] = $this->createShopWithUser('Shop A');
        $product = $this->createProduct('Onion', 'ON-1');

        $order = $this->createShopOrder([
            'shop_id' => $shop->id,
            'business_date' => $this->businessDate,
            'state' => 'approved',
            'submitted_at' => now(),
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_grade' => 'A',
            'requested_qty' => 50,
            'approved_qty' => 50,
            'unit' => 'kg',
        ]);

        $query = app(PurchaserDailyInitialQuery::class);
        $items = $query->getPendingDemand(Carbon::parse($this->businessDate));

        $demand = $items->first();
        $this->assertFalse($demand['has_non_a_grades']);
        $this->assertNull($demand['grade_breakdown']);
        $this->assertEquals(50.0, $demand['total_approved_qty']);
    }

    /**
     * 12. Legacy B-grade routes remaining functional.
     */
    public function test_legacy_b_grade_routes_remain_functional(): void
    {
        [$shop] = $this->createShopWithUser('Shop Multi', ['A', 'B']);
        $product = $this->createProduct('Potato', 'POT-1');

        $response = $this->actingAs($this->purchaserUser)
            ->get(route('purchaser.daily', [
                'date' => $this->businessDate,
                'purchase_grade' => 'B',
            ]));

        $response->assertOk();
    }

    /**
     * 13. Rejected non-A order creation while add-on is disabled.
     */
    public function test_rejected_non_a_order_creation_while_addon_is_disabled(): void
    {
        // Ensure flag is 0
        BusinessSetting::updateOrCreate(
            ['key' => 'enable_multi_grade_ordering'],
            ['value' => '0', 'type' => 'boolean']
        );

        [$shop, $user] = $this->createShopWithUser('Shop Casio', ['A', 'B']);
        $product = $this->createProduct('Tomato H', 'TOM-H');

        $gate = app(MultiGradeOrderGate::class);
        $this->assertFalse($gate->isFeatureEnabled());

        $response = $this->actingAs($user)->post(route('requisitions.store'), [
            'items' => [
                $product->sku.'|B' => 20,
            ],
            'item_units' => [
                $product->sku.'|B' => 'kg',
            ],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertDatabaseMissing('shop_orders', ['shop_id' => $shop->id]);
    }

    /**
     * 14. Existing accepted non-A orders remaining visible for processing after disabling new submissions.
     */
    public function test_existing_accepted_non_a_orders_remain_visible_for_processing_after_disabling_flag(): void
    {
        // Global flag is disabled
        BusinessSetting::updateOrCreate(
            ['key' => 'enable_multi_grade_ordering'],
            ['value' => '0', 'type' => 'boolean']
        );

        [$shop] = $this->createShopWithUser('Shop Multi', ['A', 'B']);
        $product = $this->createProduct('Tomato H', 'TOM-H');

        // 1. Existing submitted order with Grade B remains visible on Requisition Board
        $submittedOrder = $this->createShopOrder([
            'shop_id' => $shop->id,
            'business_date' => $this->businessDate,
            'state' => 'submitted',
            'submitted_at' => now(),
        ]);
        $submittedOrder->items()->create([
            'product_id' => $product->id,
            'product_grade' => 'B',
            'requested_qty' => 15,
            'unit' => 'kg',
        ]);

        $boardResponse = $this->actingAs($this->managerUser)
            ->get(route('requisitions.board', ['date' => $this->businessDate]));
        $boardResponse->assertOk();
        $boardResponse->assertSee('Tomato H');
        $boardResponse->assertSee('Grade B');

        // 2. Existing approved order with Grade B remains visible on Approved Board & Purchaser Daily
        [$shop2] = $this->createShopWithUser('Shop Multi 2', ['A', 'B']);
        $approvedOrder = $this->createShopOrder([
            'shop_id' => $shop2->id,
            'business_date' => $this->businessDate,
            'state' => 'approved',
            'submitted_at' => now(),
        ]);
        $approvedOrder->items()->create([
            'product_id' => $product->id,
            'product_grade' => 'B',
            'requested_qty' => 20,
            'approved_qty' => 20,
            'unit' => 'kg',
        ]);

        $approvedBoardResponse = $this->actingAs($this->managerUser)
            ->get(route('requisitions.approved_board', ['date' => $this->businessDate]));
        $approvedBoardResponse->assertOk();
        $approvedBoardResponse->assertSee('Tomato H');
        $approvedBoardResponse->assertSee('Grade B');

        // 3. Visible on Purchaser Daily Dashboard
        $purchaserResponse = $this->actingAs($this->purchaserUser)
            ->get(route('purchaser.daily', ['date' => $this->businessDate]));
        $purchaserResponse->assertOk();
        $purchaserResponse->assertSee('Tomato H');
        $purchaserResponse->assertSee('Grade B');
    }
}
