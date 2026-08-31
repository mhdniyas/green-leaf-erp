<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Domains\ShopOrder\Actions\BulkFinalizeShopInvoicesAction;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
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

    private User $purchaser;

    private ShopPriceGroup $priceGroup;

    private Shop $shop;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->operator = User::factory()->create(['name' => 'Warehouse Operator']);
        $this->operator->assignRole('warehouse_receiver');

        $this->purchaser = User::factory()->create(['name' => 'Purchaser']);
        $this->purchaser->assignRole('purchase');

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

    public function test_api_cannot_save_loadout_if_shop_checkin_already_submitted(): void
    {
        Sanctum::actingAs($this->operator);

        $order = $this->createOrder(
            deliveryStatus: 'ready_for_dispatch',
            reviewStatus: 'pending',
            shopChecked: true,
        );

        $response = $this->postJson(route('api.v1.warehouse.loadout.save', $order), [
            'items' => [
                (string) $this->product->id => 12.0,
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Orders with submitted or completed delivery check-in cannot be edited.');
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

    public function test_approved_delivery_cannot_be_moved_to_loadout(): void
    {
        Sanctum::actingAs($this->operator);

        $order = $this->createOrder(
            deliveryStatus: 'delivered',
            reviewStatus: 'approved',
            shopChecked: true,
            isDelivered: true,
        );

        $response = $this->postJson(route('api.v1.warehouse.loadout.move-to-loadout', $order));

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame('delivered', $order->fresh()->delivery_status);
    }

    public function test_normal_unsubmitted_order_can_move_to_loadout_and_delivery(): void
    {
        Sanctum::actingAs($this->operator);

        $date = now()->toDateString();
        DailyPriceApproval::query()->create([
            'product_id' => $this->product->id,
            'business_date' => $date,
            'purchase_price' => 15,
            'price_unit' => 'kg',
            'price_a' => 20,
            'price_b' => 20,
            'price_c' => 20,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $order = $this->createOrder(
            deliveryStatus: 'ready_for_dispatch',
            reviewStatus: 'not_started',
            shopChecked: false,
            businessDate: $date,
        );

        $response = $this->postJson(route('api.v1.warehouse.loadout.move-to-loadout', $order));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('ready_for_dispatch', $order->fresh()->delivery_status);
        $this->assertSame('not_started', $order->fresh()->delivery_review_status);

        $deliveryResponse = $this->postJson(route('api.v1.warehouse.loadout.move-to-delivery', $order));
        $deliveryResponse->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('in_transit', $order->fresh()->delivery_status);
    }

    public function test_pending_review_order_remains_finalizable_by_purchaser(): void
    {
        $date = '2026-08-08';
        $order = $this->createOrder(
            deliveryStatus: 'pending_approval',
            reviewStatus: 'pending',
            shopChecked: true,
        );

        $invoice = ShopInvoice::factory()->create([
            'shop_id' => $this->shop->id,
            'shop_order_id' => $order->id,
            'invoice_number' => 'SINV-20260808-TEST',
            'business_date' => $date,
            'status' => 'delivery_review',
            'delivery_status' => 'awaiting_review',
            'payment_status' => 'unpaid',
            'subtotal' => 200,
            'final_total' => 200,
            'balance_amount' => 200,
        ]);

        DailyPriceApproval::query()->create([
            'product_id' => $this->product->id,
            'business_date' => $date,
            'purchase_price' => 15,
            'price_unit' => 'kg',
            'price_a' => 20,
            'price_b' => 20,
            'price_c' => 20,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        ShopInvoiceItem::factory()->create([
            'shop_invoice_id' => $invoice->id,
            'shop_order_item_id' => $order->items->first()->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'unit' => 'kg',
            'price_unit' => 'kg',
            'approved_qty' => 10,
            'price_quantity' => 10,
            'delivered_qty' => 10,
            'delivered_price_quantity' => 10,
            'unit_price' => 20,
            'line_subtotal' => 200,
            'final_line_total' => 200,
        ]);

        $action = app(BulkFinalizeShopInvoicesAction::class);
        $eligibility = $action->checkEligibility($invoice, $this->purchaser);

        $this->assertTrue($eligibility['eligible']);
        $this->assertSame('Ready for finalization.', $eligibility['reason']);
    }

    private function createOrder(
        string $deliveryStatus,
        string $reviewStatus,
        bool $shopChecked = false,
        bool $isDelivered = false,
        ?string $businessDate = null,
    ): ShopOrder {
        $order = ShopOrder::factory()->approved()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $businessDate ?? '2026-08-08',
            'delivery_status' => $deliveryStatus,
            'delivery_review_status' => $reviewStatus,
            'shop_checked_at' => $shopChecked ? now() : null,
            'shop_checked_by' => $shopChecked ? $this->operator->id : null,
            'is_delivered' => $isDelivered,
            'delivered_at' => $isDelivered ? now() : null,
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
