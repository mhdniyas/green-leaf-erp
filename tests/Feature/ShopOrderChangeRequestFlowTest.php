<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderChangeRequest;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\Requisition\ShopOrderChangeRequestRecorder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ShopOrderChangeRequestFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_submitted_order_update_records_request_history_without_creating_another_order(): void
    {
        Notification::fake();
        $this->seed(RolePermissionSeeder::class);

        $shop = Shop::factory()->create();
        $shopOwner = $this->shopUser($shop);
        $product = Product::factory()->create([
            'name' => 'Demo Tomato',
            'sku' => 'DEMO-TOMATO-UPDATE',
            'unit' => 'KG',
        ]);
        $order = $this->shopOrder($shop, $shopOwner, 'submitted');
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 5,
            'unit' => 'KG',
        ]);

        $response = $this->actingAs($shopOwner)
            ->withSession(['_token' => 'test-token'])
            ->post(route('requisitions.update-request', $order->order_number), [
                '_token' => 'test-token',
                'items' => [
                    $product->sku => 8,
                ],
                'reason' => 'Need more tomato.',
            ]);

        $response->assertRedirect(route('shop-owner.orders.show', $order->order_number));
        $this->assertSame(1, ShopOrder::query()->count());

        $order->refresh();
        $this->assertSame('update_requested', $order->state);
        $this->assertSame(8.0, (float) $order->items()->where('product_id', $product->id)->value('requested_qty'));

        $changeRequest = $order->changeRequests()->with('items')->sole();
        $this->assertSame('quantity_update', $changeRequest->type);
        $this->assertSame('pending', $changeRequest->status);
        $this->assertSame($shopOwner->id, $changeRequest->requested_by);

        $requestItem = $changeRequest->items->sole();
        $this->assertSame(5.0, (float) $requestItem->old_qty);
        $this->assertSame(8.0, (float) $requestItem->new_qty);
        $this->assertSame(3.0, (float) $requestItem->delta_qty);
    }

    public function test_approved_order_update_creates_trace_request_and_applies_to_same_order(): void
    {
        Notification::fake();
        $this->seed(RolePermissionSeeder::class);

        $shop = Shop::factory()->create();
        $shopOwner = $this->shopUser($shop);
        $purchaseManager = $this->purchaseUser();
        $product = Product::factory()->create([
            'name' => 'Demo Onion',
            'sku' => 'DEMO-ONION-APPROVED',
            'unit' => 'KG',
        ]);
        $order = $this->shopOrder($shop, $shopOwner, 'approved');
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 5,
            'approved_qty' => 5,
            'unit' => 'KG',
        ]);

        $this->actingAs($shopOwner)
            ->withSession(['_token' => 'test-token'])
            ->post(route('requisitions.update-request', $order->order_number), [
                '_token' => 'test-token',
                'items' => [
                    $product->sku => 8,
                ],
                'reason' => 'Increase after customer request.',
            ])->assertRedirect(route('shop-owner.orders.show', $order->order_number));

        $order->refresh();
        $this->assertSame('update_requested', $order->state);
        $this->assertSame(1, ShopOrder::query()->count());

        $revision = $order->revisions()->with('items')->sole();
        $changeRequest = $order->changeRequests()->with('items')->sole();
        $this->assertSame('approved_order_update', $changeRequest->type);
        $this->assertSame('pending', $changeRequest->status);
        $this->assertSame($revision->id, $changeRequest->shop_order_revision_id);
        $this->assertSame(5.0, (float) $changeRequest->items->sole()->old_qty);
        $this->assertSame(8.0, (float) $changeRequest->items->sole()->new_qty);

        $this->actingAs($purchaseManager)
            ->withSession(['_token' => 'test-token'])
            ->post(route('requisitions.approve-update', $order->order_number), [
                '_token' => 'test-token',
                'approved_qty' => [
                    $product->id => 7,
                ],
                'manager_note' => 'Approved at 7 KG.',
            ])->assertRedirect();

        $order->refresh();
        $this->assertSame('approved', $order->state);
        $this->assertFalse($order->has_pending_revision);
        $this->assertSame(7.0, (float) $order->items()->where('product_id', $product->id)->value('requested_qty'));
        $this->assertSame(7.0, (float) $order->items()->where('product_id', $product->id)->value('approved_qty'));

        $revision->refresh();
        $changeRequest->refresh();
        $this->assertSame('applied', $revision->status);
        $this->assertSame('approved', $changeRequest->status);
        $this->assertSame($purchaseManager->id, $changeRequest->reviewed_by);
    }

    public function test_late_request_review_updates_history_on_the_same_order(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $shop = Shop::factory()->create();
        $shopOwner = $this->shopUser($shop);
        $purchaseManager = $this->purchaseUser();
        $product = Product::factory()->create([
            'name' => 'Demo Carrot',
            'sku' => 'DEMO-CARROT-LATE',
            'unit' => 'KG',
        ]);
        $order = $this->shopOrder($shop, $shopOwner, 'submitted', [
            'is_late' => true,
        ]);
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 6,
            'unit' => 'KG',
        ]);

        app(ShopOrderChangeRequestRecorder::class)->recordLateSubmission(
            $order,
            $shopOwner,
            'Late request for same order.'
        );

        $this->actingAs($purchaseManager)
            ->withSession(['_token' => 'test-token'])
            ->post(route('requisitions.accept-late', $order->order_number), [
                '_token' => 'test-token',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertFalse($order->is_late);
        $this->assertSame(1, ShopOrder::query()->count());

        $changeRequest = ShopOrderChangeRequest::query()->with('items')->sole();
        $this->assertSame($order->id, $changeRequest->shop_order_id);
        $this->assertSame('late_submission', $changeRequest->type);
        $this->assertSame('approved', $changeRequest->status);
        $this->assertSame($purchaseManager->id, $changeRequest->reviewed_by);
        $this->assertSame(0.0, (float) $changeRequest->items->sole()->old_qty);
        $this->assertSame(6.0, (float) $changeRequest->items->sole()->new_qty);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function shopOrder(Shop $shop, User $shopOwner, string $state, array $attributes = []): ShopOrder
    {
        return ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'state' => $state,
            'business_date' => now()->addDay()->toDateString(),
            'created_by' => $shopOwner->id,
            ...$attributes,
        ]);
    }

    private function shopUser(Shop $shop): User
    {
        $user = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $user->assignRole('shop');

        return $user;
    }

    private function purchaseUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('purchase');

        return $user;
    }
}
