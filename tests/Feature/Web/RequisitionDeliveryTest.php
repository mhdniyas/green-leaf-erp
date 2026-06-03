<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequisitionDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        $this->user = User::factory()->create();
    }

    public function test_guest_user_cannot_access_delivery_checkin(): void
    {
        $shop = Shop::create(['code' => 'S1', 'name' => 'Shop 1']);
        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'is_allocation_completed' => true,
            'created_by' => $this->user->id,
        ]);

        $response = $this->get(route('requisitions.delivery.show', $order->order_number));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_shop_owner_cannot_access_other_shop_delivery_checkin(): void
    {
        $shop1 = Shop::create(['code' => 'S1', 'name' => 'Shop 1']);
        $shop2 = Shop::create(['code' => 'S2', 'name' => 'Shop 2']);

        $owner = User::factory()->create(['shop_id' => $shop1->id]);
        $owner->assignRole('shop-owner');

        $order = ShopOrder::create([
            'shop_id' => $shop2->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'is_allocation_completed' => true,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($owner)
            ->get(route('requisitions.delivery.show', $order->order_number));

        $response->assertStatus(403);
    }

    public function test_authorized_shop_owner_can_access_own_shop_delivery_checkin(): void
    {
        $shop = Shop::create(['code' => 'S1', 'name' => 'Shop 1']);
        $owner = User::factory()->create(['shop_id' => $shop->id]);
        $owner->assignRole('shop-owner');

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'is_allocation_completed' => true,
            'created_by' => $owner->id,
        ]);

        $product = Product::factory()->create();
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($owner)
            ->get(route('requisitions.delivery.show', $order->order_number));

        $response->assertOk();
        $response->assertSee($order->order_number);
        $response->assertSee('Verify Delivery');
    }

    public function test_cannot_access_delivery_checkin_if_allocation_not_completed(): void
    {
        $shop = Shop::create(['code' => 'S1', 'name' => 'Shop 1']);
        $owner = User::factory()->create(['shop_id' => $shop->id]);
        $owner->assignRole('shop-owner');

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'is_allocation_completed' => false,
            'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)
            ->get(route('requisitions.delivery.show', $order->order_number));

        $response->assertRedirect(route('requisitions.show', $order->order_number));
        $response->assertSessionHas('error', 'This order has not been dispatched/allocated from the warehouse yet.');
    }

    public function test_cannot_access_delivery_checkin_if_already_delivered(): void
    {
        $shop = Shop::create(['code' => 'S1', 'name' => 'Shop 1']);
        $owner = User::factory()->create(['shop_id' => $shop->id]);
        $owner->assignRole('shop-owner');

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'is_allocation_completed' => true,
            'is_delivered' => true,
            'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)
            ->get(route('requisitions.delivery.show', $order->order_number));

        $response->assertRedirect(route('requisitions.show', $order->order_number));
        $response->assertSessionHas('error', 'This order has already been checked-in and marked as delivered.');
    }

    public function test_authorized_shop_owner_can_record_delivery_with_shortages_and_cash_discrepancy(): void
    {
        $shop = Shop::create(['code' => 'S1', 'name' => 'Shop 1']);
        $owner = User::factory()->create(['shop_id' => $shop->id]);
        $owner->assignRole('shop-owner');

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'is_allocation_completed' => true,
            'created_by' => $owner->id,
        ]);

        $product = Product::factory()->create();

        // Create stock batch to resolve cost
        StockBatch::create([
            'product_id' => $product->id,
            'created_by' => $owner->id,
            'reference' => 'B-01',
            'received_at' => today()->toDateString(),
            'total_kg' => 100,
            'cost_per_kg' => 10.00,
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 15,
            'approved_qty' => 15,
            'unit' => 'kg',
        ]);

        $payload = [
            'delivered_qty' => [
                $item->id => 12, // shortage of 3
            ],
            'cash_collected' => 100.00, // Expected Delivered Value: 12 * 10 = 120.00. Discrepancy: 120 - 100 = 20.00 shortage.
            'delivery_notes' => 'Some minor shortages, cash collected is Rs. 100.',
        ];

        $response = $this->actingAs($owner)
            ->post(route('requisitions.delivery.record', $order->order_number), $payload);

        $response->assertRedirect(route('requisitions.show', $order->order_number));
        $response->assertSessionHas('success');

        $order->refresh();
        $item->refresh();

        // Verify Order updates
        $this->assertTrue($order->is_delivered);
        $this->assertNotNull($order->delivered_at);
        $this->assertEquals($owner->id, $order->delivered_by);
        $this->assertEquals(100.00, (float) $order->cash_collected);
        $this->assertEquals(20.00, (float) $order->cash_discrepancy);
        $this->assertEquals(30.00, (float) $order->total_shortage_value); // 3 kg shortage * Rs 10 = 30.00
        $this->assertEquals('Some minor shortages, cash collected is Rs. 100.', $order->delivery_notes);

        // Verify Item updates
        $this->assertEquals(12.00, (float) $item->delivered_qty);
        $this->assertEquals(3.00, (float) $item->shortage_qty);
        $this->assertEquals(10.00, (float) $item->unit_cost);
        $this->assertEquals(30.00, (float) $item->shortage_value);
    }
}
