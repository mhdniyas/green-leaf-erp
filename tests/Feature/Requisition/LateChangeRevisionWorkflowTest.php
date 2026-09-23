<?php

declare(strict_types=1);

namespace Tests\Feature\Requisition;

use App\Models\Category;
use App\Models\DailyProductPrice;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\User;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Requisition\ShopOrderRevisionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class LateChangeRevisionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private ShopPriceGroup $defaultPriceGroup;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('shop', 'web');
        Role::findOrCreate('purchase', 'web');

        // Create default price group
        $this->defaultPriceGroup = ShopPriceGroup::factory()->create([
            'name' => 'A',
            'is_active' => true,
        ]);
    }

    private function createShopWithUser(string $shopName = 'Test Shop'): array
    {
        $shop = Shop::factory()->create([
            'name' => $shopName,
        ]);
        $user = User::factory()->create(['shop_id' => $shop->id]);
        $user->assignRole('shop');

        return [$shop, $user];
    }

    private function createPurchasingUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('purchase');
        $user->givePermissionTo(Permission::findOrCreate('purchasing.order.approve', 'web'));

        return $user;
    }

    private function setupProductPrice(Product $product, float $price = 10.00): void
    {
        DailyProductPrice::updateOrCreate(
            [
                'product_id' => $product->id,
                'shop_price_group_id' => $this->defaultPriceGroup->id,
                'grade' => 'A',
            ],
            [
                'selling_price' => $price,
                'cost_price' => $price * 0.8,
                'price_source' => 'manual',
            ]
        );
    }

    /**
     * Requirement 1 & G1: Omitted products are NOT converted to zero.
     * Palak=10, Mint=5, Tomato=20 -> Late request: Tomato=25
     * Expected: only Tomato revision created (old=20, new=25). No Palak or Mint revision.
     * Palak approved_qty remains 10, Mint approved_qty remains 5, Tomato approved_qty remains 20 before approval.
     */
    public function test_omitted_products_in_late_change_are_not_converted_to_zero_g1(): void
    {
        [$shop, $user] = $this->createShopWithUser();
        $palak = Product::factory()->create(['name' => 'Palak', 'sku' => 'PALAK-01']);
        $mint = Product::factory()->create(['name' => 'Mint', 'sku' => 'MINT-01']);
        $tomato = Product::factory()->create(['name' => 'Tomato', 'sku' => 'TOMATO-01']);

        $order = ShopOrder::factory()->approved()->for($shop)->create([
            'business_date' => '2026-08-25',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $palak->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => $palak->unit,
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $mint->id,
            'requested_qty' => 5,
            'approved_qty' => 5,
            'unit' => $mint->unit,
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $tomato->id,
            'requested_qty' => 20,
            'approved_qty' => 20,
            'unit' => $tomato->unit,
        ]);

        /** @var ShopOrderRevisionService $revisionService */
        $revisionService = app(ShopOrderRevisionService::class);

        // Shop submits only Tomato = 25 (Palak and Mint are omitted)
        $revision = $revisionService->createApprovedOrderRevision(
            $order,
            [['product' => $tomato, 'quantity' => 25.0]],
            $user,
            'Need 5 more tomatoes'
        );

        $this->assertNotNull($revision);
        $this->assertSame('pending', $revision->status);
        $this->assertCount(1, $revision->items);

        $revisionItem = $revision->items->first();
        $this->assertSame($tomato->id, $revisionItem->product_id);
        $this->assertEquals(20.0, (float) $revisionItem->old_requested_qty);
        $this->assertEquals(25.0, (float) $revisionItem->new_requested_qty);
        $this->assertEquals(5.0, (float) $revisionItem->delta_qty);

        // Confirm Palak and Mint have NO revision items
        $this->assertFalse($revision->items->contains('product_id', $palak->id));
        $this->assertFalse($revision->items->contains('product_id', $mint->id));

        // Confirm database approved quantities are UNCHANGED before approval
        $this->assertEquals(10.0, (float) $order->items()->where('product_id', $palak->id)->value('approved_qty'));
        $this->assertEquals(5.0, (float) $order->items()->where('product_id', $mint->id)->value('approved_qty'));
        $this->assertEquals(20.0, (float) $order->items()->where('product_id', $tomato->id)->value('approved_qty'));
    }

    /**
     * Requirement 2: Whole Leaf section omission regression test (Casio Leaf incident).
     * Approved: Palak=10, Mint=5, Coriander=4, Tomato=20
     * Late request payload: only Tomato=25 (Leaf completely absent).
     */
    public function test_omitting_entire_leaf_section_preserves_all_leaf_items_and_live_demand(): void
    {
        [$shop, $user] = $this->createShopWithUser();
        $leafCat = Category::factory()->create(['name' => 'Leaf']);
        $vegCat = Category::factory()->create(['name' => 'Vegetable']);

        $palak = Product::factory()->create(['name' => 'Palak', 'sku' => 'PALAK-02', 'category_id' => $leafCat->id]);
        $mint = Product::factory()->create(['name' => 'Mint', 'sku' => 'MINT-02', 'category_id' => $leafCat->id]);
        $coriander = Product::factory()->create(['name' => 'Coriander', 'sku' => 'CORI-02', 'category_id' => $leafCat->id]);
        $tomato = Product::factory()->create(['name' => 'Tomato', 'sku' => 'TOMATO-02', 'category_id' => $vegCat->id]);

        $order = ShopOrder::factory()->approved()->for($shop)->create([
            'business_date' => '2026-08-25',
        ]);

        $palakItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $palak->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => $palak->unit,
        ]);
        $mintItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $mint->id,
            'requested_qty' => 5,
            'approved_qty' => 5,
            'unit' => $mint->unit,
        ]);
        $corianderItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $coriander->id,
            'requested_qty' => 4,
            'approved_qty' => 4,
            'unit' => $coriander->unit,
        ]);
        $tomatoItem = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $tomato->id,
            'requested_qty' => 20,
            'approved_qty' => 20,
            'unit' => $tomato->unit,
        ]);

        // Submit late request containing ONLY Tomato
        $response = $this->actingAs($user)->post(route('requisitions.update-request', $order->order_number), [
            'items' => [
                'TOMATO-02' => 25,
            ],
            'reason' => 'Need extra tomatoes for soup',
        ]);

        $response->assertRedirect();

        // 1. No Leaf revision items created
        $order->refresh();
        $this->assertTrue($order->has_pending_revision);
        $revision = $order->latestPendingRevision()->with('items')->first();
        $this->assertNotNull($revision);
        $this->assertCount(1, $revision->items);
        $this->assertSame($tomato->id, $revision->items->first()->product_id);

        // 2. No Leaf ShopOrderItem deleted
        $this->assertDatabaseHas('shop_order_items', ['id' => $palakItem->id]);
        $this->assertDatabaseHas('shop_order_items', ['id' => $mintItem->id]);
        $this->assertDatabaseHas('shop_order_items', ['id' => $corianderItem->id]);

        // 3. No Leaf approved_qty changed
        $this->assertEquals(10.0, (float) $palakItem->fresh()->approved_qty);
        $this->assertEquals(5.0, (float) $mintItem->fresh()->approved_qty);
        $this->assertEquals(4.0, (float) $corianderItem->fresh()->approved_qty);
        $this->assertEquals(20.0, (float) $tomatoItem->fresh()->approved_qty);

        // 4. Sort sheet / live demand still sees full Leaf quantities
        $purchasingUser = $this->createPurchasingUser();
        $purchasingUser->givePermissionTo(Permission::findOrCreate('sort.sheet.view'));

        $this->actingAs($purchasingUser)
            ->get(route('sort-sheet.generate', ['date' => '2026-08-25']))
            ->assertOk()
            ->assertViewHas('matrix', function (array $matrix) use ($palak, $mint, $coriander, $tomato, $shop): bool {
                return ($matrix[$palak->id][$shop->id] ?? null) === 10.0
                    && ($matrix[$mint->id][$shop->id] ?? null) === 5.0
                    && ($matrix[$coriander->id][$shop->id] ?? null) === 4.0
                    && ($matrix[$tomato->id][$shop->id] ?? null) === 20.0;
            });
    }

    /**
     * Requirement 3 & G2: Manager approval never deletes existing approved rows.
     * Pre-existing row ID must be unchanged, deleted_at must remain null.
     */
    public function test_manager_approval_updates_approved_qty_without_deleting_shop_order_item_row_g2(): void
    {
        [$shop, $user] = $this->createShopWithUser();
        $manager = $this->createPurchasingUser();
        $palak = Product::factory()->create(['name' => 'Palak', 'sku' => 'PALAK-03']);
        $this->setupProductPrice($palak, 15.00);

        $order = ShopOrder::factory()->approved()->for($shop)->create([
            'business_date' => '2026-08-25',
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $palak->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => $palak->unit,
        ]);
        $originalItemId = $item->id;

        /** @var ShopOrderRevisionService $revisionService */
        $revisionService = app(ShopOrderRevisionService::class);

        $revision = $revisionService->createApprovedOrderRevision(
            $order,
            [['product' => $palak, 'quantity' => 15.0]],
            $user,
            'Increase palak'
        );

        // Manager approves revision
        $revisionService->applyPendingRevision(
            $order,
            $manager,
            [$palak->id => 15.0]
        );

        $item->refresh();
        $this->assertSame($originalItemId, $item->id);
        $this->assertEquals(15.0, (float) $item->approved_qty);
        $this->assertEquals(10.0, (float) $item->requested_qty); // original requested_qty preserved!
        $this->assertNull($item->deleted_at ?? null);
    }

    /**
     * Requirement 4: Explicit Removal via real HTTP request.
     * Palak = 10 -> Shop sends Palak = 0 -> pending revision (old=10, new=0)
     * Manager approves -> Palak approved_qty = 0, row remains, deleted_at = null.
     */
    public function test_explicit_removal_with_zero_qty_via_http_creates_pending_revision_and_manager_approval_sets_approved_qty_zero_without_deleting_row(): void
    {
        [$shop, $user] = $this->createShopWithUser();
        $manager = $this->createPurchasingUser();
        $palak = Product::factory()->create(['name' => 'Palak', 'sku' => 'PALAK-04']);
        $this->setupProductPrice($palak, 20.00);

        $order = ShopOrder::factory()->approved()->for($shop)->create([
            'business_date' => '2026-08-25',
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $palak->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => $palak->unit,
        ]);
        $originalItemId = $item->id;

        // Shop submits explicit removal: Palak = 0 via HTTP route
        $response = $this->actingAs($user)->post(route('requisitions.update-request', $order->order_number), [
            'items' => [
                'PALAK-04' => 0,
            ],
            'reason' => 'Cancel palak entirely',
        ]);

        $response->assertRedirect();

        // 1. Immediately: revision is pending, old=10, new=0, delta=-10
        $order->refresh();
        $this->assertTrue($order->has_pending_revision);
        $revision = $order->latestPendingRevision()->with('items')->first();
        $this->assertNotNull($revision);
        $this->assertSame('pending', $revision->status);
        $this->assertCount(1, $revision->items);

        $revItem = $revision->items->first();
        $this->assertSame($palak->id, $revItem->product_id);
        $this->assertEquals(10.0, (float) $revItem->old_requested_qty);
        $this->assertEquals(0.0, (float) $revItem->new_requested_qty);
        $this->assertEquals(-10.0, (float) $revItem->delta_qty);

        // ShopOrderItem approved_qty is STILL 10 before manager approval
        $this->assertEquals(10.0, (float) $item->fresh()->approved_qty);

        // 2. Manager approves revision via HTTP route
        $approveResponse = $this->actingAs($manager)->post(route('requisitions.approve-update', $order->order_number), [
            'approved_qty' => [
                $palak->id => 0,
            ],
            'manager_note' => 'Approved removal',
        ]);

        $approveResponse->assertRedirect();

        // 3. After approval: approved_qty = 0, row preserved, revision = applied/approved
        $item->refresh();
        $this->assertSame($originalItemId, $item->id);
        $this->assertEquals(0.0, (float) $item->approved_qty);
        $this->assertNull($item->deleted_at ?? null);

        $revision->refresh();
        $this->assertSame('applied', $revision->status);
        $this->assertSame($manager->id, $revision->reviewed_by);
        $this->assertNotNull($revision->reviewed_at);
    }

    /**
     * Requirement 5: Pending late requests never affect live demand.
     */
    public function test_pending_revision_does_not_leak_into_live_purchaser_demand_or_sort_sheet(): void
    {
        [$shop, $user] = $this->createShopWithUser();
        $manager = $this->createPurchasingUser();
        $manager->givePermissionTo(Permission::findOrCreate('sort.sheet.view'));

        $tomato = Product::factory()->create(['name' => 'Tomato', 'sku' => 'TOMATO-05']);
        $this->setupProductPrice($tomato, 25.00);

        $order = ShopOrder::factory()->approved()->for($shop)->create([
            'business_date' => '2026-08-25',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $tomato->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => $tomato->unit,
        ]);

        // Shop requests 100 tomatoes
        $this->actingAs($user)->post(route('requisitions.update-request', $order->order_number), [
            'items' => [
                'TOMATO-05' => 100,
            ],
            'reason' => 'Need 100kg',
        ]);

        // Sort sheet must still see 10, not 100
        $this->actingAs($manager)
            ->get(route('sort-sheet.generate', ['date' => '2026-08-25']))
            ->assertOk()
            ->assertViewHas('matrix', fn (array $matrix): bool => ($matrix[$tomato->id][$shop->id] ?? null) === 10.0);
    }

    /**
     * Requirement 6: Manager Approval — Increase (10 -> 15).
     */
    public function test_manager_approval_increase_updates_approved_qty_and_preserves_row_id(): void
    {
        [$shop, $user] = $this->createShopWithUser();
        $manager = $this->createPurchasingUser();
        $tomato = Product::factory()->create(['name' => 'Tomato', 'sku' => 'TOMATO-06']);
        $this->setupProductPrice($tomato, 30.00);

        $order = ShopOrder::factory()->approved()->for($shop)->create([
            'business_date' => '2026-08-25',
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $tomato->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => $tomato->unit,
        ]);
        $originalId = $item->id;

        $this->actingAs($user)->post(route('requisitions.update-request', $order->order_number), [
            'items' => ['TOMATO-06' => 15],
            'reason' => 'Increase',
        ]);

        $this->assertEquals(10.0, (float) $item->fresh()->approved_qty);

        $this->actingAs($manager)->post(route('requisitions.approve-update', $order->order_number), [
            'approved_qty' => [$tomato->id => 15],
        ]);

        $item->refresh();
        $this->assertSame($originalId, $item->id);
        $this->assertEquals(15.0, (float) $item->approved_qty);
    }

    /**
     * Requirement 7: Manager Approval — Decrease (10 -> 7).
     */
    public function test_manager_approval_decrease_updates_approved_qty_and_preserves_row_id(): void
    {
        [$shop, $user] = $this->createShopWithUser();
        $manager = $this->createPurchasingUser();
        $tomato = Product::factory()->create(['name' => 'Tomato', 'sku' => 'TOMATO-07']);
        $this->setupProductPrice($tomato, 30.00);

        $order = ShopOrder::factory()->approved()->for($shop)->create([
            'business_date' => '2026-08-25',
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $tomato->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => $tomato->unit,
        ]);
        $originalId = $item->id;

        $this->actingAs($user)->post(route('requisitions.update-request', $order->order_number), [
            'items' => ['TOMATO-07' => 7],
            'reason' => 'Decrease',
        ]);

        $this->actingAs($manager)->post(route('requisitions.approve-update', $order->order_number), [
            'approved_qty' => [$tomato->id => 7],
        ]);

        $item->refresh();
        $this->assertSame($originalId, $item->id);
        $this->assertEquals(7.0, (float) $item->approved_qty);
    }

    /**
     * Requirement 8: Manager Rejection leaves order and approved quantities unchanged.
     */
    public function test_manager_rejection_leaves_order_and_items_unchanged(): void
    {
        [$shop, $user] = $this->createShopWithUser();
        $manager = $this->createPurchasingUser();
        $tomato = Product::factory()->create(['name' => 'Tomato', 'sku' => 'TOMATO-08']);
        $this->setupProductPrice($tomato, 30.00);

        $order = ShopOrder::factory()->approved()->for($shop)->create([
            'business_date' => '2026-08-25',
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $tomato->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => $tomato->unit,
        ]);

        $this->actingAs($user)->post(route('requisitions.update-request', $order->order_number), [
            'items' => ['TOMATO-08' => 5],
            'reason' => 'Lower demand',
        ]);

        $revision = $order->latestPendingRevision()->first();
        $this->assertNotNull($revision);

        $this->actingAs($manager)->post(route('requisitions.reject-update', $order->order_number), [
            'manager_note' => 'Cannot reduce supplier order already placed',
        ]);

        $revision->refresh();
        $this->assertSame('rejected', $revision->status);
        $this->assertSame($manager->id, $revision->reviewed_by);

        $item->refresh();
        $this->assertEquals(10.0, (float) $item->approved_qty);
    }

    /**
     * Requirement 9: New product added via late request remains pending until manager approves.
     */
    public function test_new_product_added_via_late_request_remains_pending_until_manager_approves(): void
    {
        [$shop, $user] = $this->createShopWithUser();
        $manager = $this->createPurchasingUser();
        $tomato = Product::factory()->create(['name' => 'Tomato', 'sku' => 'TOMATO-09']);
        $palak = Product::factory()->create(['name' => 'Palak', 'sku' => 'PALAK-09']);
        $this->setupProductPrice($tomato, 30.00);
        $this->setupProductPrice($palak, 20.00);

        $order = ShopOrder::factory()->approved()->for($shop)->create([
            'business_date' => '2026-08-25',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $tomato->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => $tomato->unit,
        ]);

        // Shop requests adding Palak = 5 (in addition to existing Tomato = 10)
        $this->actingAs($user)->post(route('requisitions.update-request', $order->order_number), [
            'items' => [
                'TOMATO-09' => 10,
                'PALAK-09' => 5,
            ],
            'reason' => 'Forgot palak',
        ]);

        // Palak row does not exist yet before manager approval
        $this->assertNull($order->items()->where('product_id', $palak->id)->first());

        // Manager approves
        $this->actingAs($manager)->post(route('requisitions.approve-update', $order->order_number), [
            'approved_qty' => [
                $palak->id => 5,
            ],
        ]);

        $newItem = $order->items()->where('product_id', $palak->id)->first();
        $this->assertNotNull($newItem);
        $this->assertEquals(5.0, (float) $newItem->approved_qty);
    }

    /**
     * Requirement 10: Existing baseline uses approved_qty over requested_qty.
     * requested_qty = 20, approved_qty = 15 -> Shop requests 10 -> old_requested_qty must be 15, not 20.
     */
    public function test_existing_baseline_uses_approved_qty_over_requested_qty(): void
    {
        [$shop, $user] = $this->createShopWithUser();
        $tomato = Product::factory()->create(['name' => 'Tomato', 'sku' => 'TOMATO-10']);

        $order = ShopOrder::factory()->approved()->for($shop)->create([
            'business_date' => '2026-08-25',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $tomato->id,
            'requested_qty' => 20,
            'approved_qty' => 15,
            'unit' => $tomato->unit,
        ]);

        /** @var ShopOrderRevisionService $revisionService */
        $revisionService = app(ShopOrderRevisionService::class);

        $revision = $revisionService->createApprovedOrderRevision(
            $order,
            [['product' => $tomato, 'quantity' => 10.0]],
            $user,
            'Change to 10'
        );

        $this->assertNotNull($revision);
        $revItem = $revision->items->first();
        $this->assertEquals(15.0, (float) $revItem->old_requested_qty);
        $this->assertEquals(10.0, (float) $revItem->new_requested_qty);
        $this->assertEquals(-5.0, (float) $revItem->delta_qty);
    }

    /**
     * Requirement 11 & 12: Normal order auto-approval still works, but late changes NEVER auto-approve.
     */
    public function test_late_requests_never_auto_approve_even_when_original_order_was_auto_approved(): void
    {
        [$shop, $user] = $this->createShopWithUser();
        $tomato = Product::factory()->create(['name' => 'Tomato', 'sku' => 'TOMATO-11']);
        $this->setupProductPrice($tomato, 25.00);

        // Simulate auto-approved original order
        $order = ShopOrder::factory()->approved()->for($shop)->create([
            'business_date' => Carbon::now('Asia/Kolkata')->addDay()->toDateString(),
            'state' => 'approved',
            'manager_note' => PurchaserBusinessDayService::AUTO_APPROVE_MANAGER_NOTE,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $tomato->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => $tomato->unit,
        ]);

        // Shop resubmits order after auto-approval
        $response = $this->actingAs($user)->post(route('requisitions.store'), [
            'items' => ['TOMATO-11' => 15],
            'reason' => 'Changed mind',
        ]);

        $response->assertRedirect();

        $order->refresh();
        $this->assertSame('update_requested', $order->state);
        $this->assertTrue($order->has_pending_revision);

        $revision = $order->latestPendingRevision()->first();
        $this->assertNotNull($revision);
        $this->assertSame('pending', $revision->status); // MUST NOT BE AUTO-APPROVED!
    }

    /**
     * Requirement 13: Authorization rules for shop and purchasing users.
     */
    public function test_authorization_rules_for_shop_and_purchasing_users(): void
    {
        [$shop1, $user1] = $this->createShopWithUser('Shop 1');
        [$shop2, $user2] = $this->createShopWithUser('Shop 2');
        $manager = $this->createPurchasingUser();
        $unauthorizedUser = User::factory()->create();

        $tomato = Product::factory()->create(['name' => 'Tomato', 'sku' => 'TOMATO-13']);

        $order1 = ShopOrder::factory()->approved()->for($shop1)->create(['business_date' => '2026-08-25']);
        ShopOrderItem::create([
            'shop_order_id' => $order1->id,
            'product_id' => $tomato->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => $tomato->unit,
        ]);

        // User2 cannot edit Shop 1's order
        $this->actingAs($user2)->post(route('requisitions.update-request', $order1->order_number), [
            'items' => ['TOMATO-13' => 20],
        ])->assertForbidden();

        // User1 can submit late change for Shop 1
        $this->actingAs($user1)->post(route('requisitions.update-request', $order1->order_number), [
            'items' => ['TOMATO-13' => 20],
        ])->assertRedirect();

        // User1 (shop role) cannot approve revision
        $this->actingAs($user1)->post(route('requisitions.approve-update', $order1->order_number), [
            'approved_qty' => [$tomato->id => 20],
        ])->assertForbidden();

        // Unauthorized user cannot approve revision
        $this->actingAs($unauthorizedUser)->post(route('requisitions.approve-update', $order1->order_number), [
            'approved_qty' => [$tomato->id => 20],
        ])->assertForbidden();

        // Manager can approve revision
        $this->actingAs($manager)->post(route('requisitions.approve-update', $order1->order_number), [
            'approved_qty' => [$tomato->id => 20],
        ])->assertRedirect();
    }

    /**
     * Requirement 14: Multiple sequential revisions chain approved baselines correctly.
     * 10 -> Rev 1: 10 -> 7 approved.
     * Rev 2: 7 -> 12 pending. Baseline is 7, approved_qty is 7.
     * Manager approves Rev 2 -> approved_qty becomes 12.
     */
    public function test_multiple_sequential_revisions_chain_approved_baselines_correctly(): void
    {
        [$shop, $user] = $this->createShopWithUser();
        $manager = $this->createPurchasingUser();
        $tomato = Product::factory()->create(['name' => 'Tomato', 'sku' => 'TOMATO-14']);
        $this->setupProductPrice($tomato, 25.00);

        $order = ShopOrder::factory()->approved()->for($shop)->create([
            'business_date' => '2026-08-25',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $tomato->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => $tomato->unit,
        ]);

        /** @var ShopOrderRevisionService $revisionService */
        $revisionService = app(ShopOrderRevisionService::class);

        // Revision 1: 10 -> 7
        $rev1 = $revisionService->createApprovedOrderRevision(
            $order,
            [['product' => $tomato, 'quantity' => 7.0]],
            $user,
            'First change'
        );
        $revisionService->applyPendingRevision($order, $manager, [$tomato->id => 7.0]);

        $this->assertEquals(7.0, (float) $order->items()->where('product_id', $tomato->id)->value('approved_qty'));

        // Revision 2: 7 -> 12
        $rev2 = $revisionService->createApprovedOrderRevision(
            $order,
            [['product' => $tomato, 'quantity' => 12.0]],
            $user,
            'Second change'
        );

        $this->assertNotNull($rev2);
        $rev2Item = $rev2->items->first();
        $this->assertEquals(7.0, (float) $rev2Item->old_requested_qty); // Baseline is 7!
        $this->assertEquals(12.0, (float) $rev2Item->new_requested_qty);

        // While pending, approved_qty is still 7
        $this->assertEquals(7.0, (float) $order->items()->where('product_id', $tomato->id)->value('approved_qty'));

        // Manager approves Rev 2
        $revisionService->applyPendingRevision($order, $manager, [$tomato->id => 12.0]);
        $this->assertEquals(12.0, (float) $order->items()->where('product_id', $tomato->id)->value('approved_qty'));
    }

    /**
     * Requirement 15: Entire Category is safe across Leaf, Vegetable, Fruit.
     */
    public function test_entire_categories_leaf_vegetable_fruit_are_safe_when_omitted(): void
    {
        [$shop, $user] = $this->createShopWithUser();
        $leafCat = Category::factory()->create(['name' => 'Leaf']);
        $vegCat = Category::factory()->create(['name' => 'Vegetable']);
        $fruitCat = Category::factory()->create(['name' => 'Fruit']);

        $palak = Product::factory()->create(['name' => 'Palak', 'sku' => 'LEAF-PALAK', 'category_id' => $leafCat->id]);
        $carrot = Product::factory()->create(['name' => 'Carrot', 'sku' => 'VEG-CARROT', 'category_id' => $vegCat->id]);
        $apple = Product::factory()->create(['name' => 'Apple', 'sku' => 'FRUIT-APPLE', 'category_id' => $fruitCat->id]);

        $order = ShopOrder::factory()->approved()->for($shop)->create(['business_date' => '2026-08-25']);

        ShopOrderItem::create(['shop_order_id' => $order->id, 'product_id' => $palak->id, 'requested_qty' => 10, 'approved_qty' => 10, 'unit' => $palak->unit]);
        ShopOrderItem::create(['shop_order_id' => $order->id, 'product_id' => $carrot->id, 'requested_qty' => 15, 'approved_qty' => 15, 'unit' => $carrot->unit]);
        ShopOrderItem::create(['shop_order_id' => $order->id, 'product_id' => $apple->id, 'requested_qty' => 20, 'approved_qty' => 20, 'unit' => $apple->unit]);

        /** @var ShopOrderRevisionService $revisionService */
        $revisionService = app(ShopOrderRevisionService::class);

        // Late payload omits Leaf and Vegetable entirely, only changes Apple: 20 -> 25
        $revision = $revisionService->createApprovedOrderRevision(
            $order,
            [['product' => $apple, 'quantity' => 25.0]],
            $user,
            'More apples'
        );

        $this->assertNotNull($revision);
        $this->assertCount(1, $revision->items);
        $this->assertSame($apple->id, $revision->items->first()->product_id);

        // Palak and Carrot remain 10 and 15
        $this->assertEquals(10.0, (float) $order->items()->where('product_id', $palak->id)->value('approved_qty'));
        $this->assertEquals(15.0, (float) $order->items()->where('product_id', $carrot->id)->value('approved_qty'));
    }
}
