<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\Purchasing\POStatus;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopOrderRevision;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RequisitionTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $shopOwner;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(ProductSeeder::class);

        $this->shop = Shop::create([
            'code' => 'SHOP_TEST',
            'name' => 'Test Hypermarket',
        ]);

        $this->shopOwner = User::factory()->create([
            'shop_id' => $this->shop->id,
        ]);
        $this->shopOwner->assignRole('shop');

        $this->product = Product::first();
    }

    public function test_shop_owner_can_submit_requisition_and_redirect_to_show_page(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(18, 0, 0)); // 6:00 PM, before 9:30 PM cutoff

        $response = $this->actingAs($this->shopOwner)
            ->postJson(route('requisitions.store'), [
                'items' => [
                    $this->product->sku => 15.50,
                ],
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['success', 'order_number', 'redirect_url']);

        $orderNumber = $response->json('order_number');
        $this->assertDatabaseHas('shop_orders', [
            'order_number' => $orderNumber,
            'shop_id' => $this->shop->id,
            'state' => 'submitted',
        ]);

        $this->assertDatabaseHas('shop_order_items', [
            'product_id' => $this->product->id,
            'requested_qty' => 15.50,
        ]);

        // Verify format: RQ-YYYYMMDD-XXXX
        $dateStr = Carbon::tomorrow()->format('Ymd');
        $this->assertMatchesRegularExpression("/^RQ-{$dateStr}-[A-Z0-9]{4}$/", $orderNumber);
        $this->assertSame(route('shop-owner.orders.show', $orderNumber), $response->json('redirect_url'));
    }

    public function test_shop_owner_can_submit_requisition_from_dashboard_form_post(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(18, 0, 0));

        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $response = $this->actingAs($this->shopOwner)
            ->post(route('requisitions.store'), [
                'items' => [
                    $this->product->sku => 11.25,
                ],
            ]);

        $order = ShopOrder::where('shop_id', $this->shop->id)
            ->whereDate('business_date', Carbon::tomorrow())
            ->first();

        $this->assertNotNull($order);

        $response->assertRedirect(route('shop-owner.orders.show', $order->order_number));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('shop_order_items', [
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 11.25,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $manager->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_shop_owner_empty_order_submission_redirects_back_to_shop_owner_create_page(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(18, 0, 0));

        $response = $this->actingAs($this->shopOwner)
            ->from(route('shop-owner.orders.create'))
            ->post(route('requisitions.store'), [
                'items' => [],
            ]);

        $response->assertRedirect(route('shop-owner.orders.create'));
        $response->assertSessionHasErrors('items');
    }

    public function test_shop_owner_can_view_own_requisition_details(): void
    {
        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'submitted',
            'created_by' => $this->shopOwner->id,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 12.00,
            'unit' => $this->product->unit,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->get(route('requisitions.show', $order->order_number));

        $response->assertRedirect(route('shop-owner.orders.show', $order->order_number));
    }

    public function test_shop_owner_cannot_view_other_shops_requisition(): void
    {
        $otherShop = Shop::create([
            'code' => 'SHOP_OTHER',
            'name' => 'Other Shop',
        ]);
        $otherOwner = User::factory()->create([
            'shop_id' => $otherShop->id,
        ]);

        $order = ShopOrder::create([
            'shop_id' => $otherShop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'submitted',
            'created_by' => $otherOwner->id,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->get(route('requisitions.show', $order->order_number));

        $response->assertStatus(403);
    }

    public function test_shop_owner_can_edit_requisition_before_cutoff(): void
    {
        // Target delivery tomorrow. Cutoff is today 9:30 PM.
        // Set time to today 8:00 PM (before cutoff)
        Carbon::setTestNow(Carbon::today()->setTime(20, 0, 0));

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'submitted',
            'created_by' => $this->shopOwner->id,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.00,
            'unit' => $this->product->unit,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->get(route('requisitions.edit', $order->order_number));
        $response->assertRedirect(route('shop-owner.orders.create'));

        // Submit edit
        $response = $this->actingAs($this->shopOwner)
            ->post(route('requisitions.update', $order->order_number), [
                'items' => [
                    $item->id => 20.00,
                ],
            ]);

        $response->assertRedirect(route('shop-owner.orders.show', $order->order_number));
        $this->assertDatabaseHas('shop_order_items', [
            'id' => $item->id,
            'requested_qty' => 20.00,
        ]);
    }

    public function test_shop_owner_cannot_edit_requisition_after_cutoff(): void
    {
        // Target delivery tomorrow. Cutoff is today 9:30 PM.
        // Set time to today 10:00 PM (after cutoff)
        Carbon::setTestNow(Carbon::today()->setTime(22, 0, 0));

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'submitted',
            'created_by' => $this->shopOwner->id,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.00,
            'unit' => $this->product->unit,
        ]);

        // Try getting edit view
        $response = $this->actingAs($this->shopOwner)
            ->get(route('requisitions.edit', $order->order_number));
        $response->assertRedirect(route('shop-owner.orders.show', $order->order_number));

        // Try updating quantities
        $response = $this->actingAs($this->shopOwner)
            ->post(route('requisitions.update', $order->order_number), [
                'items' => [
                    $item->id => 20.00,
                ],
            ]);

        $response->assertRedirect(route('shop-owner.orders.show', $order->order_number));
        // Verify requested_qty is unchanged
        $this->assertDatabaseHas('shop_order_items', [
            'id' => $item->id,
            'requested_qty' => 10.00,
        ]);
    }

    public function test_shop_owner_can_request_update_after_cutoff(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(22, 0, 0)); // after cutoff

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'submitted',
            'created_by' => $this->shopOwner->id,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->post(route('requisitions.update-request', $order->order_number), [
                'reason' => 'Need to add 5 kg of Tomatoes.',
                'items' => [
                    $this->product->sku => 5.00,
                ],
            ]);

        $response->assertRedirect(route('shop-owner.orders.show', $order->order_number));
        $this->assertDatabaseHas('shop_orders', [
            'id' => $order->id,
            'state' => 'update_requested',
            'update_reason' => 'Need to add 5 kg of Tomatoes.',
        ]);
    }

    public function test_shop_owner_can_modify_quantities_and_add_items_in_update_request_after_cutoff(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(22, 0, 0));

        $secondProduct = Product::skip(1)->firstOrFail();

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'submitted',
            'created_by' => $this->shopOwner->id,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.00,
            'unit' => $this->product->unit,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->post(route('requisitions.update-request', $order->order_number), [
                'reason' => 'Need to rebalance tomorrow demand.',
                'items' => [
                    $this->product->sku => 16.50,
                    $secondProduct->sku => 7.00,
                ],
            ]);

        $response->assertRedirect(route('shop-owner.orders.show', $order->order_number));
        $response->assertSessionHas('success', 'Your updated order request has been submitted to the Purchase Manager.');

        $this->assertDatabaseHas('shop_orders', [
            'id' => $order->id,
            'state' => 'update_requested',
            'update_reason' => 'Need to rebalance tomorrow demand.',
        ]);

        $this->assertDatabaseHas('shop_order_items', [
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 16.50,
        ]);

        $this->assertDatabaseHas('shop_order_items', [
            'shop_order_id' => $order->id,
            'product_id' => $secondProduct->id,
            'requested_qty' => 7.00,
        ]);
    }

    public function test_shop_owner_can_request_update_after_approval_before_purchase_orders_are_generated(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(22, 0, 0));

        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'approved',
            'created_by' => $this->shopOwner->id,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.00,
            'approved_qty' => 8.00,
            'unit' => $this->product->unit,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->post(route('requisitions.update-request', $order->order_number), [
                'reason' => 'Need 2 more kg after sales spike.',
                'items' => [
                    $this->product->sku => 12.00,
                ],
            ]);

        $response->assertRedirect(route('shop-owner.orders.show', $order->order_number));

        $this->assertDatabaseHas('shop_orders', [
            'id' => $order->id,
            'state' => 'update_requested',
            'update_reason' => 'Need 2 more kg after sales spike.',
            'has_pending_revision' => true,
            'latest_revision_no' => 2,
        ]);

        $this->assertDatabaseHas('shop_order_items', [
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.00,
            'approved_qty' => 8.00,
        ]);

        $this->assertDatabaseHas('shop_order_revisions', [
            'shop_order_id' => $order->id,
            'revision_no' => 2,
            'status' => 'pending',
            'reason' => 'Need 2 more kg after sales spike.',
        ]);

        $revision = ShopOrderRevision::where('shop_order_id', $order->id)->first();
        $this->assertNotNull($revision);

        $this->assertDatabaseHas('shop_order_revision_items', [
            'shop_order_revision_id' => $revision->id,
            'product_id' => $this->product->id,
            'old_requested_qty' => 8.00,
            'new_requested_qty' => 12.00,
            'delta_qty' => 4.00,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $manager->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_shop_owner_cannot_request_update_after_goods_receipt_starts_for_linked_purchase_order(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(22, 0, 0));

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'approved',
            'created_by' => $this->shopOwner->id,
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.00,
            'approved_qty' => 8.00,
            'unit' => $this->product->unit,
        ]);

        $supplier = Supplier::create([
            'name' => 'PO Lock Supplier',
            'type' => 'Farmer',
            'category' => 'own_purchase',
        ]);

        PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-LOCK-REQ',
            'status' => POStatus::Approved,
            'order_date' => Carbon::tomorrow()->format('Y-m-d'),
            'created_by' => $this->shopOwner->id,
            'fulfillment_type' => 'warehouse',
            'notes' => 'Auto-generated from Requisitions System',
        ]);
        $purchaseOrder = PurchaseOrder::where('po_number', 'PO-LOCK-REQ')->firstOrFail();
        $purchaseOrder->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 8.00,
            'unit_price' => 1.00,
        ]);
        GoodsReceived::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'received_by' => $this->shopOwner->id,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->post(route('requisitions.update-request', $order->order_number), [
                'reason' => 'Late update after purchase order.',
                'items' => [
                    $this->product->sku => 12.00,
                ],
            ]);

        $response->assertRedirect(route('shop-owner.orders.show', $order->order_number));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('shop_orders', [
            'id' => $order->id,
            'state' => 'approved',
        ]);
    }

    public function test_shop_owner_cannot_request_approved_update_when_nothing_changed(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(22, 0, 0));

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'approved',
            'created_by' => $this->shopOwner->id,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.00,
            'approved_qty' => 8.00,
            'unit' => $this->product->unit,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->post(route('requisitions.update-request', $order->order_number), [
                'reason' => 'No actual change.',
                'items' => [
                    $this->product->sku => 8.00,
                ],
            ]);

        $response->assertRedirect(route('shop-owner.orders.show', $order->order_number));
        $response->assertSessionHas('error');

        $this->assertDatabaseMissing('shop_order_revisions', [
            'shop_order_id' => $order->id,
        ]);
    }

    public function test_shop_owner_submits_late_requisition_request_after_cutoff_in_business_timezone(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(22, 0, 0));

        $response = $this->actingAs($this->shopOwner)
            ->from(route('shop-owner.orders.create'))
            ->post(route('requisitions.store'), [
                'items' => [
                    $this->product->sku => 8.50,
                ],
            ]);

        $order = ShopOrder::where('shop_id', $this->shop->id)
            ->whereDate('business_date', Carbon::tomorrow()->format('Y-m-d'))
            ->firstOrFail();

        $response->assertRedirect(route('shop-owner.orders.show', $order->order_number));
        $response->assertSessionHas('success');

        $this->assertTrue($order->is_late);
        $this->assertSame('submitted', $order->state);

        $this->assertDatabaseHas('shop_order_items', [
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 8.50,
        ]);
    }

    public function test_shop_owner_cannot_edit_approved_requisition_even_before_cutoff(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(20, 0, 0));

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'approved',
            'created_by' => $this->shopOwner->id,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.00,
            'approved_qty' => 10.00,
            'unit' => $this->product->unit,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->get(route('requisitions.edit', $order->order_number));

        $response->assertRedirect(route('shop-owner.orders.show', $order->order_number));

        $updateResponse = $this->actingAs($this->shopOwner)
            ->post(route('requisitions.update', $order->order_number), [
                'items' => [
                    $item->id => 20.00,
                ],
            ]);

        $updateResponse->assertRedirect(route('shop-owner.orders.show', $order->order_number));
        $this->assertDatabaseHas('shop_order_items', [
            'id' => $item->id,
            'requested_qty' => 10.00,
        ]);
    }

    public function test_export_routes_return_correct_responses(): void
    {
        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'submitted',
            'created_by' => $this->shopOwner->id,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.00,
            'unit' => $this->product->unit,
        ]);

        // CSV Export
        $response = $this->actingAs($this->shopOwner)
            ->get(route('requisitions.export.csv', $order->order_number));
        $response->assertOk();
        $this->assertEquals('text/csv; charset=utf-8', $response->headers->get('Content-Type'));

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString($order->order_number, $content);
        $this->assertStringContainsString($this->product->sku, $content);

        // PDF / Print View
        $response = $this->actingAs($this->shopOwner)
            ->get(route('requisitions.export.pdf', $order->order_number));
        $response->assertOk();
        $response->assertSee('window.print()');
    }

    public function test_purchase_manager_can_approve_requisition_with_adjusted_quantities(): void
    {
        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'submitted',
            'created_by' => $this->shopOwner->id,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.00,
            'unit' => $this->product->unit,
        ]);

        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $response = $this->actingAs($manager)
            ->post(route('requisitions.review', $order->order_number), [
                'action' => 'approve',
                'approved_qty' => [
                    $item->id => 8.50,
                ],
            ]);

        $response->assertRedirect(route('requisitions.show', $order->order_number));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('shop_orders', [
            'id' => $order->id,
            'state' => 'approved',
        ]);

        $this->assertDatabaseHas('shop_order_items', [
            'id' => $item->id,
            'approved_qty' => 8.50,
        ]);
    }

    public function test_purchase_manager_can_reject_requisition(): void
    {
        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'submitted',
            'created_by' => $this->shopOwner->id,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.00,
            'unit' => $this->product->unit,
        ]);

        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $response = $this->actingAs($manager)
            ->post(route('requisitions.review', $order->order_number), [
                'action' => 'reject',
            ]);

        $response->assertRedirect(route('requisitions.show', $order->order_number));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('shop_orders', [
            'id' => $order->id,
            'state' => 'rejected',
        ]);

        $this->assertDatabaseHas('shop_order_items', [
            'id' => $item->id,
            'approved_qty' => 0.00,
        ]);
    }

    public function test_unauthorized_user_cannot_submit_review(): void
    {
        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'submitted',
            'created_by' => $this->shopOwner->id,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->post(route('requisitions.review', $order->order_number), [
                'action' => 'approve',
            ]);

        $response->assertStatus(403);
    }

    public function test_requisitions_board_page_is_accessible_to_purchase_manager(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $response = $this->actingAs($manager)
            ->get(route('requisitions.board'));

        $response->assertOk();
        $response->assertSee('Consolidated Requisitions Board');
        $response->assertSee('Purchasing');
        $response->assertSee('Approved Board');
    }

    public function test_requisition_boards_highlight_shop_updates_before_purchase_orders(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'update_requested',
            'update_reason' => 'Need extra tomatoes before purchase.',
            'created_by' => $this->shopOwner->id,
            'has_pending_revision' => true,
            'latest_revision_no' => 2,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 15.00,
            'approved_qty' => 10.00,
            'unit' => $this->product->unit,
        ]);
        $revision = ShopOrderRevision::create([
            'shop_order_id' => $order->id,
            'revision_no' => 2,
            'status' => 'pending',
            'reason' => 'Need extra tomatoes before purchase.',
            'requested_by' => $this->shopOwner->id,
        ]);
        $revision->items()->create([
            'product_id' => $this->product->id,
            'old_requested_qty' => 10.00,
            'new_requested_qty' => 15.00,
            'delta_qty' => 5.00,
        ]);

        $boardResponse = $this->actingAs($manager)
            ->get(route('requisitions.board', ['date' => $order->business_date->format('Y-m-d')]));

        $boardResponse->assertOk();
        $boardResponse->assertSee('Shop Owner Updates Waiting');
        $boardResponse->assertSee('Update #2');
        $boardResponse->assertSee('Need extra tomatoes before purchase.');

        $approvedBoardResponse = $this->actingAs($manager)
            ->get(route('requisitions.approved_board', ['date' => $order->business_date->format('Y-m-d')]));

        $approvedBoardResponse->assertOk();
        $approvedBoardResponse->assertSee('Updated Shop Requests Pending');
        $approvedBoardResponse->assertSee('Update #2');
        $approvedBoardResponse->assertSee('Need extra tomatoes before purchase.');
    }

    public function test_requisitions_board_shows_handoff_actions_once_all_orders_are_approved(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'approved',
            'created_by' => $this->shopOwner->id,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 12.00,
            'approved_qty' => 12.00,
            'unit' => $this->product->unit,
        ]);

        $response = $this->actingAs($manager)
            ->get(route('requisitions.board', ['date' => $order->business_date->format('Y-m-d')]));

        $response->assertOk();
        $response->assertSee('Continue to Approved Board');
        $response->assertSee('Save Board Changes');
        $response->assertDontSee('Save &amp; Approve All', false);
    }

    public function test_requisitions_board_page_is_forbidden_to_shop_owner(): void
    {
        $response = $this->actingAs($this->shopOwner)
            ->get(route('requisitions.board'));

        $response->assertStatus(403);
    }

    public function test_purchase_manager_can_save_requisition_board_quantities(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $date = Carbon::tomorrow()->format('Y-m-d');

        $response = $this->actingAs($manager)
            ->post(route('requisitions.board.save'), [
                'date' => $date,
                'quantities' => [
                    $this->product->id => [
                        $this->shop->id => 25.00,
                    ],
                ],
            ]);

        $response->assertRedirect(route('requisitions.board', ['date' => $date]));
        $response->assertSessionHas('success');

        // Order and item should be created in approved state
        $this->assertDatabaseHas('shop_orders', [
            'shop_id' => $this->shop->id,
            'state' => 'approved',
        ]);

        $order = ShopOrder::where('shop_id', $this->shop->id)->first();
        $this->assertNotNull($order);

        $this->assertDatabaseHas('shop_order_items', [
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 25.00,
            'approved_qty' => 25.00,
        ]);
    }

    public function test_purchase_manager_can_save_requisition_board_fulfillment_types(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $date = Carbon::tomorrow()->format('Y-m-d');

        $response = $this->actingAs($manager)
            ->post(route('requisitions.board.save'), [
                'date' => $date,
                'quantities' => [
                    $this->product->id => [
                        $this->shop->id => 15.00,
                    ],
                ],
                'fulfillment_types' => [
                    $this->product->id => 'selection',
                ],
            ]);

        $response->assertRedirect(route('requisitions.board', ['date' => $date]));

        $order = ShopOrder::where('shop_id', $this->shop->id)->first();
        $this->assertNotNull($order);

        $this->assertDatabaseHas('shop_order_items', [
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'fulfillment_type' => 'selection',
        ]);
    }

    public function test_purchase_manager_can_review_and_update_fulfillment_type(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'submitted',
            'created_by' => $this->shopOwner->id,
            'order_number' => 'ORD-TEST-FULFILL',
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.00,
            'unit' => 'kg',
            'fulfillment_type' => 'warehouse',
        ]);

        $response = $this->actingAs($manager)
            ->post(route('requisitions.review', $order->order_number), [
                'action' => 'approve',
                'approved_qty' => [
                    $item->id => 12.00,
                ],
                'fulfillment_types' => [
                    $item->id => 'selection',
                ],
            ]);

        $response->assertRedirect(route('requisitions.show', $order->order_number));

        $this->assertDatabaseHas('shop_order_items', [
            'id' => $item->id,
            'approved_qty' => 12.00,
            'fulfillment_type' => 'selection',
        ]);
    }

    public function test_approved_requisitions_board_page_is_accessible_to_purchase_manager(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $response = $this->actingAs($manager)
            ->get(route('requisitions.approved_board'));

        $response->assertOk();
        $response->assertSee('Approved Requisitions Board');
        $response->assertSee('Purchasing');
        $response->assertSee('Requisition Board');
    }

    public function test_purchase_manager_can_save_approved_requisition_board_quantities(): void
    {
        $this->withoutExceptionHandling();
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $date = Carbon::tomorrow()->format('Y-m-d');
        $supplier = Supplier::create([
            'name' => 'Test Supplier',
            'type' => 'Farmer',
            'category' => 'own_purchase',
            'contact' => '1234567890',
            'payment_terms' => 'COD',
            'quality_score' => 100.00,
        ]);

        $response = $this->actingAs($manager)
            ->post(route('requisitions.approved_board.save'), [
                'date' => $date,
                'quantities' => [
                    $this->product->id => [
                        $this->shop->id => 30.00,
                    ],
                ],
                'fulfillment_types' => [
                    $this->product->id => 'selection',
                ],
                'suppliers' => [
                    $this->product->id => $supplier->id,
                ],
            ]);

        $response->assertRedirect(route('requisitions.approved_board', ['date' => $date]));
        $response->assertSessionHas('success');

        $order = ShopOrder::where('shop_id', $this->shop->id)
            ->whereDate('business_date', $date)
            ->where('state', 'approved')
            ->first();
        $this->assertNotNull($order);

        $this->assertDatabaseHas('shop_order_items', [
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'approved_qty' => 30.00,
            'fulfillment_type' => 'selection',
        ]);

        // Assert that Purchase Order was generated on Approved Board Save
        $po = PurchaseOrder::whereDate('order_date', $date)->first();
        $this->assertNotNull($po);
    }

    public function test_purchase_manager_can_apply_pending_revision_to_existing_purchase_order_before_grn(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $supplier = Supplier::create([
            'name' => 'Revision Supplier',
            'type' => 'Farmer',
            'category' => 'own_purchase',
        ]);

        $date = Carbon::tomorrow()->format('Y-m-d');
        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'state' => 'update_requested',
            'update_reason' => 'Increase approved quantity.',
            'created_by' => $this->shopOwner->id,
            'has_pending_revision' => true,
            'latest_revision_no' => 2,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.00,
            'approved_qty' => 8.00,
            'unit' => $this->product->unit,
            'fulfillment_type' => 'warehouse',
        ]);

        $revision = ShopOrderRevision::create([
            'shop_order_id' => $order->id,
            'revision_no' => 2,
            'status' => 'pending',
            'reason' => 'Increase approved quantity.',
            'requested_by' => $this->shopOwner->id,
        ]);
        $revision->items()->create([
            'product_id' => $this->product->id,
            'old_requested_qty' => 8.00,
            'new_requested_qty' => 12.00,
            'delta_qty' => 4.00,
        ]);

        $purchaseOrder = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-REV-0001',
            'status' => POStatus::Approved,
            'order_date' => $date,
            'created_by' => $manager->id,
            'fulfillment_type' => 'warehouse',
            'notes' => 'Auto-generated from Requisitions System',
        ]);
        $purchaseOrder->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 8.00,
            'unit_price' => 1.00,
        ]);

        $response = $this->actingAs($manager)
            ->post(route('requisitions.approved_board.save'), [
                'date' => $date,
                'quantities' => [
                    $this->product->id => [
                        $this->shop->id => 12.00,
                    ],
                ],
                'fulfillment_types' => [
                    $this->product->id => 'warehouse',
                ],
                'suppliers' => [
                    $this->product->id => $supplier->id,
                ],
            ]);

        $response->assertRedirect(route('requisitions.approved_board', ['date' => $date]));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('shop_orders', [
            'id' => $order->id,
            'state' => 'approved',
            'has_pending_revision' => false,
        ]);
        $this->assertDatabaseHas('shop_order_items', [
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'approved_qty' => 12.00,
        ]);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 12.00,
        ]);
        $this->assertDatabaseHas('shop_order_revisions', [
            'id' => $revision->id,
            'status' => 'applied',
        ]);
    }

    public function test_purchase_manager_cannot_apply_pending_revision_after_grn_starts(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $supplier = Supplier::create([
            'name' => 'Locked Revision Supplier',
            'type' => 'Farmer',
            'category' => 'own_purchase',
        ]);

        $date = Carbon::tomorrow()->format('Y-m-d');
        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'state' => 'update_requested',
            'update_reason' => 'Increase approved quantity.',
            'created_by' => $this->shopOwner->id,
            'has_pending_revision' => true,
            'latest_revision_no' => 2,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.00,
            'approved_qty' => 8.00,
            'unit' => $this->product->unit,
            'fulfillment_type' => 'warehouse',
        ]);

        $revision = ShopOrderRevision::create([
            'shop_order_id' => $order->id,
            'revision_no' => 2,
            'status' => 'pending',
            'reason' => 'Increase approved quantity.',
            'requested_by' => $this->shopOwner->id,
        ]);
        $revision->items()->create([
            'product_id' => $this->product->id,
            'old_requested_qty' => 8.00,
            'new_requested_qty' => 12.00,
            'delta_qty' => 4.00,
        ]);

        $purchaseOrder = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-REV-LOCK',
            'status' => POStatus::Approved,
            'order_date' => $date,
            'created_by' => $manager->id,
            'fulfillment_type' => 'warehouse',
            'notes' => 'Auto-generated from Requisitions System',
        ]);
        $purchaseOrder->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 8.00,
            'unit_price' => 1.00,
        ]);
        GoodsReceived::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'received_by' => $manager->id,
        ]);

        $response = $this->actingAs($manager)
            ->post(route('requisitions.approved_board.save'), [
                'date' => $date,
                'quantities' => [
                    $this->product->id => [
                        $this->shop->id => 12.00,
                    ],
                ],
                'fulfillment_types' => [
                    $this->product->id => 'warehouse',
                ],
                'suppliers' => [
                    $this->product->id => $supplier->id,
                ],
            ]);

        $response->assertRedirect(route('requisitions.approved_board', ['date' => $date]));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('shop_order_revisions', [
            'id' => $revision->id,
            'status' => 'blocked',
        ]);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 8.00,
        ]);
    }

    public function test_approved_board_does_not_fall_back_to_requested_qty_for_approved_items_without_an_approved_qty(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'state' => 'approved',
            'business_date' => Carbon::tomorrow()->toDateString(),
            'created_by' => $this->shopOwner->id,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 20.00,
            'approved_qty' => null,
            'unit' => 'kg',
            'fulfillment_type' => 'warehouse',
        ]);

        $response = $this->actingAs($manager)
            ->get(route('requisitions.approved_board', ['date' => Carbon::tomorrow()->format('Y-m-d')]));

        $response->assertOk();
        $response->assertSee($this->product->name);

        $content = $response->getContent();

        $this->assertIsString($content);
        $this->assertMatchesRegularExpression(
            '/name="quantities\['.$this->product->id.'\]\['.$this->shop->id.'\]"\s+value=""/',
            $content
        );
        $this->assertDoesNotMatchRegularExpression(
            '/name="quantities\['.$this->product->id.'\]\['.$this->shop->id.'\]"\s+value="20"/',
            $content
        );
    }

    public function test_approved_board_shows_purchase_order_handoff_after_generation(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $date = Carbon::tomorrow()->format('Y-m-d');
        $supplier = Supplier::create([
            'name' => 'Board Handoff Supplier',
            'type' => 'Farmer',
            'category' => 'own_purchase',
        ]);

        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-BOARD-HANDOFF',
            'status' => POStatus::Approved,
            'order_date' => $date,
            'created_by' => $manager->id,
            'notes' => 'Auto-generated from Requisitions System',
        ]);
        $po->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 10.00,
            'unit_price' => 1.00,
        ]);

        $response = $this->actingAs($manager)
            ->get(route('requisitions.approved_board', ['date' => $date]));

        $response->assertOk();
        $response->assertSee('Purchase Orders generated');
        $response->assertSee('PO-BOARD-HANDOFF');
        $response->assertDontSee('Generate Purchase Orders');
    }

    public function test_purchase_manager_cannot_resubmit_approved_board_after_purchase_orders_are_generated(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $date = Carbon::tomorrow()->format('Y-m-d');
        $supplier = Supplier::create([
            'name' => 'Test Supplier 2',
            'type' => 'Farmer',
            'category' => 'own_purchase',
        ]);

        // First save: 30.00 kg
        $this->actingAs($manager)
            ->post(route('requisitions.approved_board.save'), [
                'date' => $date,
                'quantities' => [
                    $this->product->id => [
                        $this->shop->id => 30.00,
                    ],
                ],
                'fulfillment_types' => [
                    $this->product->id => 'warehouse',
                ],
            ]);

        // Manually create a PurchaseOrder to block subsequent updates
        PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-TEST-RE-123',
            'status' => POStatus::Approved,
            'order_date' => $date,
            'created_by' => $manager->id,
        ]);

        // Second save should be blocked because the board has moved to Purchase Orders.
        $response = $this->actingAs($manager)
            ->post(route('requisitions.approved_board.save'), [
                'date' => $date,
                'quantities' => [
                    $this->product->id => [
                        $this->shop->id => 45.00,
                    ],
                ],
                'fulfillment_types' => [
                    $this->product->id => 'warehouse',
                ],
            ]);

        $response->assertRedirect(route('requisitions.approved_board', ['date' => $date]));
        $response->assertSessionHas('error');
    }

    public function test_purchase_manager_cannot_clear_approved_board_after_purchase_orders_are_generated(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $date = Carbon::tomorrow()->format('Y-m-d');
        $supplier = Supplier::create([
            'name' => 'Test Supplier 3',
            'type' => 'Farmer',
            'category' => 'own_purchase',
        ]);

        // Save initial quantity
        $this->actingAs($manager)
            ->post(route('requisitions.approved_board.save'), [
                'date' => $date,
                'quantities' => [
                    $this->product->id => [
                        $this->shop->id => 30.00,
                    ],
                ],
                'fulfillment_types' => [
                    $this->product->id => 'warehouse',
                ],
            ]);

        // Manually create a PurchaseOrder to block subsequent updates
        PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-TEST-CL-123',
            'status' => POStatus::Approved,
            'order_date' => $date,
            'created_by' => $manager->id,
        ]);

        // Clear quantity should be blocked after PO generation.
        $response = $this->actingAs($manager)
            ->post(route('requisitions.approved_board.save'), [
                'date' => $date,
                'quantities' => [
                    $this->product->id => [
                        $this->shop->id => '',
                    ],
                ],
                'fulfillment_types' => [
                    $this->product->id => 'warehouse',
                ],
            ]);

        $response->assertRedirect(route('requisitions.approved_board', ['date' => $date]));
        $response->assertSessionHas('error');

        $poFresh = PurchaseOrder::where('supplier_id', $supplier->id)->first();
        $this->assertNotNull($poFresh);
    }

    public function test_purchase_manager_can_export_approved_board_csv(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $response = $this->actingAs($manager)
            ->get(route('requisitions.approved_board.export.csv', ['date' => Carbon::tomorrow()->format('Y-m-d')]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_purchase_manager_can_export_requisitions_board_csv(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $response = $this->actingAs($manager)
            ->get(route('requisitions.board.export.csv', ['date' => Carbon::tomorrow()->format('Y-m-d')]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_purchase_manager_can_filter_board_exports_by_produce_group(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $vegCategory = Category::query()->where('name', 'VEG')->firstOrFail();
        $fruitCategory = Category::query()->where('name', 'Frut')->firstOrFail();

        $vegProduct = Product::create([
            'category_id' => $vegCategory->id,
            'name' => 'Board Veg Product',
            'sku' => 'BOARD-VEG-001',
            'unit' => 'kg',
            'base_price' => 10,
            'is_active' => true,
        ]);

        $fruitProduct = Product::create([
            'category_id' => $fruitCategory->id,
            'name' => 'Board Fruit Product',
            'sku' => 'BOARD-FRUIT-001',
            'unit' => 'kg',
            'base_price' => 12,
            'is_active' => true,
        ]);

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'approved',
            'created_by' => $this->shopOwner->id,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $vegProduct->id,
            'requested_qty' => 5,
            'approved_qty' => 5,
            'unit' => $vegProduct->unit,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $fruitProduct->id,
            'requested_qty' => 7,
            'approved_qty' => 7,
            'unit' => $fruitProduct->unit,
        ]);

        $fruitBoardExport = $this->actingAs($manager)
            ->get(route('requisitions.board.export.csv', [
                'date' => Carbon::tomorrow()->format('Y-m-d'),
                'produce' => 'fruit',
            ]));

        $fruitBoardExport->assertOk();
        $this->assertStringContainsString('Board Fruit Product', $fruitBoardExport->streamedContent());
        $this->assertStringNotContainsString('Board Veg Product', $fruitBoardExport->streamedContent());

        $vegApprovedExport = $this->actingAs($manager)
            ->get(route('requisitions.approved_board.export.csv', [
                'date' => Carbon::tomorrow()->format('Y-m-d'),
                'produce' => 'veg',
            ]));

        $vegApprovedExport->assertOk();
        $this->assertStringContainsString('Board Veg Product', $vegApprovedExport->streamedContent());
        $this->assertStringNotContainsString('Board Fruit Product', $vegApprovedExport->streamedContent());
    }

    public function test_purchase_manager_can_export_approved_board_pdf(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $response = $this->actingAs($manager)
            ->get(route('requisitions.approved_board.export.pdf', ['date' => Carbon::tomorrow()->format('Y-m-d')]));

        $response->assertOk();
        $response->assertSee('GREEN LEAF ERP');
        $response->assertSee('Approved Consolidated Requisitions Board');
    }

    public function test_purchase_manager_can_export_requisitions_board_pdf(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $response = $this->actingAs($manager)
            ->get(route('requisitions.board.export.pdf', ['date' => Carbon::tomorrow()->format('Y-m-d')]));

        $response->assertOk();
        $response->assertSee('GREEN LEAF ERP');
        $response->assertSee('Consolidated Requisitions Board');
    }

    public function test_purchase_manager_generates_purchase_orders_for_every_approved_product(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $date = Carbon::tomorrow()->format('Y-m-d');
        $supplier = Supplier::create([
            'name' => 'Test Supplier 3',
            'type' => 'Farmer',
            'category' => 'own_purchase',
        ]);

        $product1 = $this->product;
        $product2 = Product::factory()->create();

        $response = $this->actingAs($manager)
            ->post(route('requisitions.approved_board.save'), [
                'date' => $date,
                'selected_products' => [
                    $product1->id,
                ],
                'quantities' => [
                    $product1->id => [
                        $this->shop->id => 10.00,
                    ],
                    $product2->id => [
                        $this->shop->id => 20.00,
                    ],
                ],
                'fulfillment_types' => [
                    $product1->id => 'warehouse',
                    $product2->id => 'warehouse',
                ],
            ]);

        $response->assertRedirect();

        // Shop order items should be saved for BOTH products
        $this->assertDatabaseHas('shop_order_items', [
            'product_id' => $product1->id,
            'approved_qty' => 10.00,
        ]);
        $this->assertDatabaseHas('shop_order_items', [
            'product_id' => $product2->id,
            'approved_qty' => 20.00,
        ]);

        // PO generated automatically on save
        $po = PurchaseOrder::whereDate('order_date', $date)->first();
        $this->assertNotNull($po);

        // Manually create PO to block further updates
        PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-TEST-PM-123',
            'status' => POStatus::Approved,
            'order_date' => $date,
            'created_by' => $manager->id,
        ]);

        // Once POs exist, saving again should fail.
        $response = $this->actingAs($manager)
            ->post(route('requisitions.approved_board.save'), [
                'date' => $date,
                'selected_products' => [], // Empty selection
                'quantities' => [
                    $product1->id => [
                        $this->shop->id => 10.00,
                    ],
                    $product2->id => [
                        $this->shop->id => 20.00,
                    ],
                ],
                'fulfillment_types' => [
                    $product1->id => 'warehouse',
                    $product2->id => 'warehouse',
                ],
            ]);

        $response->assertRedirect(route('requisitions.approved_board', ['date' => $date]));
        $response->assertSessionHas('error');
    }

    public function test_approved_board_generates_purchase_order_line_for_each_approved_product_at_board_scale(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $date = Carbon::tomorrow()->format('Y-m-d');
        $supplier = Supplier::create([
            'name' => 'Board Scale Supplier',
            'type' => 'Farmer',
            'category' => 'own_purchase',
        ]);
        $shops = collect([
            $this->shop,
            Shop::create(['code' => 'SHOP_SCALE_2', 'name' => 'Scale Shop 2']),
            Shop::create(['code' => 'SHOP_SCALE_3', 'name' => 'Scale Shop 3']),
        ]);
        $category = Category::first();
        $products = collect([$this->product]);

        for ($index = 2; $index <= 10; $index++) {
            $products->push(Product::create([
                'category_id' => $category?->id,
                'name' => 'Board Scale Product '.$index,
                'sku' => 'BOARD-SCALE-'.$index,
                'unit' => 'kg',
                'base_price' => 10.00,
                'is_active' => true,
            ]));
        }

        $quantities = [];
        $fulfillmentTypes = [];
        $suppliers = [];

        foreach ($products as $productIndex => $product) {
            foreach ($shops as $shopIndex => $shop) {
                $quantities[$product->id][$shop->id] = ($productIndex + 1) * ($shopIndex + 1);
            }

            $fulfillmentTypes[$product->id] = 'warehouse';
            $suppliers[$product->id] = $supplier->id;
        }

        $response = $this->actingAs($manager)
            ->post(route('requisitions.approved_board.save'), [
                'date' => $date,
                'quantities' => $quantities,
                'fulfillment_types' => $fulfillmentTypes,
            ]);

        $response->assertRedirect(route('requisitions.approved_board', ['date' => $date]));
        $response->assertSessionHas('success');

        // Verify that PO was generated
        $po = PurchaseOrder::whereDate('order_date', $date)->first();
        $this->assertNotNull($po);

        foreach ($products as $product) {
            $this->assertDatabaseHas('shop_order_items', [
                'product_id' => $product->id,
            ]);
        }
    }

    public function test_single_requisition_approval_sets_requisition_approved(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $date = Carbon::tomorrow()->format('Y-m-d');
        $supplier = Supplier::create([
            'name' => 'Test Supplier 4',
            'type' => 'Farmer',
            'category' => 'own_purchase',
        ]);

        // Create a submitted shop order
        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'state' => 'submitted',
            'submitted_at' => now(),
            'created_by' => $this->shopOwner->id,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 15.00,
            'unit' => $this->product->unit,
        ]);

        $response = $this->actingAs($manager)
            ->post(route('requisitions.review', $order->order_number), [
                'action' => 'approve',
                'approved_qty' => [
                    $item->id => 12.00,
                ],
                'fulfillment_types' => [
                    $item->id => 'warehouse',
                ],
            ]);

        $response->assertRedirect(route('requisitions.show', $order->order_number));

        // Requisition state must be approved
        $order->refresh();
        $this->assertEquals('approved', $order->state);

        // Requisition item approved qty must be updated
        $item->refresh();
        $this->assertEquals(12.00, $item->approved_qty);

        // Verify that NO PO was generated
        $po = PurchaseOrder::whereDate('order_date', $date)->first();
        $this->assertNull($po);
    }

    public function test_purchase_manager_can_save_approved_board_without_suppliers(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $date = Carbon::tomorrow()->format('Y-m-d');
        $product2 = Product::factory()->create(['name' => 'Second Product']);

        $response = $this->actingAs($manager)
            ->post(route('requisitions.approved_board.save'), [
                'date' => $date,
                'quantities' => [
                    $this->product->id => [
                        $this->shop->id => 10.00,
                    ],
                    $product2->id => [
                        $this->shop->id => 15.00,
                    ],
                ],
                'fulfillment_types' => [
                    $this->product->id => 'warehouse',
                    $product2->id => 'warehouse',
                ],
            ]);

        $response->assertRedirect(route('requisitions.approved_board', ['date' => $date]));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('shop_order_items', [
            'product_id' => $this->product->id,
            'approved_qty' => 10.00,
        ]);
        $this->assertDatabaseHas('shop_order_items', [
            'product_id' => $product2->id,
            'approved_qty' => 15.00,
        ]);
    }

    public function test_approved_board_renders_successfully(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $response = $this->actingAs($manager)
            ->get(route('requisitions.approved_board'));

        $response->assertOk();
        $response->assertSee('Approved Requisitions Board');
    }

    public function test_purchase_manager_can_accept_late_requisition(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'submitted',
            'is_late' => true,
            'created_by' => $this->shopOwner->id,
        ]);

        $response = $this->actingAs($manager)
            ->post(route('requisitions.accept-late', $order->order_number));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertFalse($order->is_late);
    }

    public function test_purchase_manager_can_reject_late_requisition(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'submitted',
            'is_late' => true,
            'created_by' => $this->shopOwner->id,
        ]);

        $response = $this->actingAs($manager)
            ->post(route('requisitions.reject-late', $order->order_number));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('rejected', $order->state);
    }

    public function test_purchase_manager_can_approve_update_request(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $date = Carbon::tomorrow()->format('Y-m-d');
        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'state' => 'update_requested',
            'created_by' => $this->shopOwner->id,
            'has_pending_revision' => true,
            'latest_revision_no' => 2,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.00,
            'approved_qty' => 8.00,
            'unit' => $this->product->unit,
            'fulfillment_type' => 'warehouse',
        ]);

        $revision = ShopOrderRevision::create([
            'shop_order_id' => $order->id,
            'revision_no' => 2,
            'status' => 'pending',
            'reason' => 'Need more tomatoes.',
            'requested_by' => $this->shopOwner->id,
        ]);
        $revision->items()->create([
            'product_id' => $this->product->id,
            'old_requested_qty' => 8.00,
            'new_requested_qty' => 12.00,
            'delta_qty' => 4.00,
        ]);

        $response = $this->actingAs($manager)
            ->post(route('requisitions.approve-update', $order->order_number), [
                'approved_qty' => [
                    $this->product->id => 12.00,
                ],
                'fulfillment_types' => [
                    $this->product->id => 'warehouse',
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('approved', $order->state);
        $this->assertFalse($order->has_pending_revision);

        $item->refresh();
        $this->assertEquals(12.00, $item->approved_qty);

        $revision->refresh();
        $this->assertSame('applied', $revision->status);
    }

    public function test_purchase_manager_can_reject_update_request(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $date = Carbon::tomorrow()->format('Y-m-d');
        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'state' => 'update_requested',
            'created_by' => $this->shopOwner->id,
            'has_pending_revision' => true,
            'latest_revision_no' => 2,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.00,
            'approved_qty' => 8.00,
            'unit' => $this->product->unit,
            'fulfillment_type' => 'warehouse',
        ]);

        $revision = ShopOrderRevision::create([
            'shop_order_id' => $order->id,
            'revision_no' => 2,
            'status' => 'pending',
            'reason' => 'Need more tomatoes.',
            'requested_by' => $this->shopOwner->id,
        ]);
        $revision->items()->create([
            'product_id' => $this->product->id,
            'old_requested_qty' => 8.00,
            'new_requested_qty' => 12.00,
            'delta_qty' => 4.00,
        ]);

        $response = $this->actingAs($manager)
            ->post(route('requisitions.reject-update', $order->order_number));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('approved', $order->state);
        $this->assertFalse($order->has_pending_revision);

        $item->refresh();
        $this->assertEquals(8.00, $item->approved_qty); // Keeps old quantity

        $revision->refresh();
        $this->assertSame('rejected', $revision->status);
    }

    public function test_late_order_rejection_sets_is_late_to_false(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'submitted',
            'is_late' => true,
            'created_by' => $this->shopOwner->id,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.00,
            'unit' => $this->product->unit,
        ]);

        $response = $this->actingAs($manager)
            ->post(route('requisitions.review', $order->order_number), [
                'action' => 'reject',
            ]);

        $response->assertRedirect(route('requisitions.show', $order->order_number));

        $order->refresh();
        $this->assertSame('rejected', $order->state);
        $this->assertFalse($order->is_late);
    }

    public function test_reject_late_requisition_route_sets_is_late_to_false(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'submitted',
            'is_late' => true,
            'created_by' => $this->shopOwner->id,
        ]);

        $response = $this->actingAs($manager)
            ->post(route('requisitions.reject-late', $order->order_number));

        $response->assertRedirect();

        $order->refresh();
        $this->assertSame('rejected', $order->state);
        $this->assertFalse($order->is_late);
    }

    public function test_board_displays_pre_approval_update_requested_orders_in_daily_requisitions(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('purchase');

        $order = ShopOrder::create([
            'shop_id' => $this->shop->id,
            'business_date' => Carbon::tomorrow()->format('Y-m-d'),
            'state' => 'update_requested',
            'update_reason' => 'Direct update request',
            'is_late' => false,
            'created_by' => $this->shopOwner->id,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'requested_qty' => 12.00,
            'unit' => $this->product->unit,
        ]);

        $response = $this->actingAs($manager)
            ->get(route('requisitions.board', ['date' => $order->business_date->format('Y-m-d')]));

        $response->assertOk();
        $response->assertSee('Update Requested');
        $response->assertSee('Direct update request');
        $response->assertSee('Daily Requisitions');
    }
}
