<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseLoadoutDeliveryStateGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private ShopPriceGroup $priceGroup;

    private Shop $shop;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->operator = User::factory()->create(['name' => 'Warehouse Operator']);
        $this->operator->assignRole('warehouse_receiver');

        $this->priceGroup = ShopPriceGroup::query()->firstOrCreate(
            ['name' => 'A'],
            ['default_margin_percent' => 10, 'is_active' => true],
        );

        $this->shop = Shop::factory()->create([
            'code' => 'SHP-GUARD-01',
            'shop_price_group_id' => $this->priceGroup->id,
        ]);

        $this->product = Product::factory()->create(['unit' => 'kg', 'base_price' => 20]);
    }

    public function test_api_cannot_move_order_to_loadout_if_shop_checkin_already_submitted(): void
    {
        Sanctum::actingAs($this->operator);

        $order = $this->createOrder(
            deliveryStatus: 'pending_approval',
            reviewStatus: 'pending',
            shopChecked: true,
        );

        $response = $this->postJson(route('api.v1.warehouse.loadout.move-to-loadout', $order));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Orders with submitted or completed delivery check-in cannot be moved back to loadout.');

        $this->assertSame('pending_approval', $order->fresh()->delivery_status);
        $this->assertSame('pending', $order->fresh()->delivery_review_status);
    }

    public function test_api_cannot_move_order_to_delivery_if_shop_checkin_already_submitted(): void
    {
        Sanctum::actingAs($this->operator);

        $order = $this->createOrder(
            deliveryStatus: 'ready_for_dispatch',
            reviewStatus: 'pending',
            shopChecked: true,
        );

        $response = $this->postJson(route('api.v1.warehouse.loadout.move-to-delivery', $order));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Orders with submitted or completed delivery check-in cannot be moved to delivery.');

        $this->assertSame('ready_for_dispatch', $order->fresh()->delivery_status);
    }

    public function test_web_cannot_move_order_to_loadout_if_shop_checkin_already_submitted(): void
    {
        $order = $this->createOrder(
            deliveryStatus: 'pending_approval',
            reviewStatus: 'pending',
            shopChecked: true,
        );

        $response = $this->actingAs($this->operator)
            ->post(route('warehouse.loadout.move-to-loadout', $order));

        $response->assertRedirect();
        $response->assertSessionHasErrors();
        $this->assertSame('pending_approval', $order->fresh()->delivery_status);
    }

    public function test_web_cannot_move_order_to_delivery_if_shop_checkin_already_submitted(): void
    {
        $order = $this->createOrder(
            deliveryStatus: 'ready_for_dispatch',
            reviewStatus: 'pending',
            shopChecked: true,
        );

        $response = $this->actingAs($this->operator)
            ->post(route('warehouse.loadout.move-to-delivery', $order));

        $response->assertRedirect();
        $response->assertSessionHasErrors();
        $this->assertSame('ready_for_dispatch', $order->fresh()->delivery_status);
    }

    public function test_normal_unsubmitted_order_can_move_to_loadout(): void
    {
        Sanctum::actingAs($this->operator);

        $order = $this->createOrder(
            deliveryStatus: 'ready_for_dispatch',
            reviewStatus: 'not_started',
            shopChecked: false,
        );

        $response = $this->postJson(route('api.v1.warehouse.loadout.move-to-loadout', $order));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('ready_for_dispatch', $order->fresh()->delivery_status);
        $this->assertSame('not_started', $order->fresh()->delivery_review_status);
    }

    private function createOrder(
        string $deliveryStatus,
        string $reviewStatus,
        bool $shopChecked = false,
    ): ShopOrder {
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => '2026-08-08',
            'delivery_status' => $deliveryStatus,
            'delivery_review_status' => $reviewStatus,
            'shop_checked_at' => $shopChecked ? now() : null,
            'shop_checked_by' => $shopChecked ? $this->operator->id : null,
        ]);

        ShopOrderItem::query()->create([
            'shop_order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_grade' => 'A',
            'requested_qty' => 10,
            'approved_qty' => 10,
            'loaded_qty' => 10,
            'unit' => 'kg',
            'requested_unit' => 'kg',
            'requested_unit_label' => 'KG',
            'requested_unit_quantity' => 10,
            'requested_unit_conversion_to_base' => 1,
            'locked_price_group_id' => $this->priceGroup->id,
            'locked_selling_price' => 20,
            'locked_price_source' => 'manual',
            'unit_cost' => 15,
            'unit_price' => 20,
            'line_total' => 200,
            'fulfillment_type' => 'warehouse',
            'sorting_status' => 'loaded',
        ]);

        return $order;
    }
}
