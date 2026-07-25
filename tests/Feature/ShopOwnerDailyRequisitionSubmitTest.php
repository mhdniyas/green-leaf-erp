<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Database\Seeders\AdminOwnPurchasePurchaserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopOwnerDailyRequisitionSubmitTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_same_shop_daily_submission_updates_existing_order_instead_of_creating_duplicates(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00', 'Asia/Kolkata'));
        $this->seed(RolePermissionSeeder::class);

        $shop = Shop::factory()->create();
        $shopOwner = $this->shopUser($shop);
        $product = Product::factory()->create([
            'name' => 'Daily Tomato',
            'sku' => 'DAILY-TOMATO',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $this->actingAs($shopOwner)
            ->post(route('requisitions.store'), [
                'items' => [$product->sku => 1],
            ])
            ->assertRedirect();

        $this->actingAs($shopOwner)
            ->post(route('requisitions.store'), [
                'items' => [$product->sku => 2],
            ])
            ->assertRedirect();

        $this->actingAs($shopOwner)
            ->post(route('requisitions.store'), [
                'items' => [$product->sku => 3],
            ])
            ->assertRedirect();

        $order = ShopOrder::query()->with('items')->sole();

        $this->assertSame('submitted', $order->state);
        $this->assertFalse($order->is_late);
        $this->assertSame('shop_owner', $order->order_source);
        $this->assertSame(ShopOrder::dailyOrderKey($shop->id, '2026-07-24'), $order->shop_daily_order_key);
        $this->assertSame(3.0, (float) $order->items->sole()->requested_qty);
    }

    public function test_repeated_submit_after_cutoff_updates_same_order_as_late_update_request(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-23 22:00:00', 'Asia/Kolkata'));
        $this->seed(RolePermissionSeeder::class);

        $shop = Shop::factory()->create();
        $shopOwner = $this->shopUser($shop);
        $product = Product::factory()->create([
            'name' => 'Late Beans',
            'sku' => 'LATE-BEANS',
            'unit' => 'kg',
            'is_active' => true,
        ]);
        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-24',
            'created_by' => $shopOwner->id,
            'state' => 'submitted',
            'is_late' => false,
        ]);
        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 4,
            'unit' => 'kg',
        ]);

        $this->actingAs($shopOwner)
            ->post(route('requisitions.store'), [
                'items' => [$product->sku => 7],
            ])
            ->assertRedirect(route('shop-owner.orders.show', $order->fresh()->order_number));

        $order->refresh();

        $this->assertSame(1, ShopOrder::query()->count());
        $this->assertSame('update_requested', $order->state);
        $this->assertTrue($order->is_late);
        $this->assertSame(7.0, (float) $order->items()->where('product_id', $product->id)->value('requested_qty'));
        $this->assertSame('quantity_update', $order->changeRequests()->sole()->type);
    }

    public function test_repeated_submit_for_approved_order_creates_revision_on_same_order(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00', 'Asia/Kolkata'));
        $this->seed(RolePermissionSeeder::class);

        $shop = Shop::factory()->create();
        $shopOwner = $this->shopUser($shop);
        $product = Product::factory()->create([
            'name' => 'Approved Chilli',
            'sku' => 'APPROVED-CHILLI',
            'unit' => 'kg',
            'is_active' => true,
        ]);
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-24',
            'created_by' => $shopOwner->id,
        ]);
        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 2,
            'approved_qty' => 2,
            'unit' => 'kg',
        ]);

        $this->actingAs($shopOwner)
            ->post(route('requisitions.store'), [
                'items' => [$product->sku => 5],
            ])
            ->assertRedirect(route('shop-owner.orders.show', $order->fresh()->order_number));

        $order->refresh();

        $this->assertSame(1, ShopOrder::query()->count());
        $this->assertSame('update_requested', $order->state);
        $this->assertTrue($order->has_pending_revision);
        $this->assertSame(1, $order->revisions()->count());
        $this->assertSame('approved_order_update', $order->changeRequests()->sole()->type);
    }

    public function test_repeated_submit_for_locked_order_is_blocked_without_new_order(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00', 'Asia/Kolkata'));
        $this->seed(RolePermissionSeeder::class);

        $shop = Shop::factory()->create();
        $shopOwner = $this->shopUser($shop);
        $product = Product::factory()->create([
            'name' => 'Locked Paper',
            'sku' => 'LOCKED-PAPER',
            'unit' => 'kg',
            'is_active' => true,
        ]);
        $order = ShopOrder::factory()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-24',
            'created_by' => $shopOwner->id,
            'is_delivered' => true,
        ]);
        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 2,
            'unit' => 'kg',
        ]);

        $this->actingAs($shopOwner)
            ->post(route('requisitions.store'), [
                'items' => [$product->sku => 5],
            ])
            ->assertRedirect(route('shop-owner.orders.show', $order->order_number))
            ->assertSessionHas('error');

        $this->assertSame(1, ShopOrder::query()->count());
        $this->assertSame(2.0, (float) $order->items()->where('product_id', $product->id)->value('requested_qty'));
    }

    public function test_admin_direct_purchase_can_still_create_multiple_orders_for_same_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00', 'Asia/Kolkata'));
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('admin'));
        $this->seed(AdminOwnPurchasePurchaserSeeder::class);
        $admin = $admin->fresh();
        $product = Product::factory()->create([
            'name' => 'Direct Tomato',
            'sku' => 'DIRECT-TOMATO',
            'unit' => 'kg',
            'is_active' => true,
        ]);

        $payload = [
            'business_date' => '2026-07-24',
            'items' => [$product->sku => 5],
        ];

        $this->actingAs($admin)
            ->post(route('admin.accounting.purchasers.direct-purchase.store'), $payload)
            ->assertRedirect(route('purchaser.vendors', ['date' => '2026-07-24']));

        $this->actingAs($admin)
            ->post(route('admin.accounting.purchasers.direct-purchase.store'), $payload)
            ->assertRedirect(route('purchaser.vendors', ['date' => '2026-07-24']));

        $orders = ShopOrder::query()
            ->where('order_source', 'admin_direct_purchase')
            ->get();

        $this->assertCount(2, $orders);
        $this->assertTrue($orders->every(fn (ShopOrder $order): bool => $order->shop_daily_order_key === null));
    }

    private function shopUser(Shop $shop): User
    {
        $user = User::factory()->create([
            'shop_id' => $shop->id,
        ]);
        $user->assignRole(Role::findByName('shop'));

        return $user;
    }
}
