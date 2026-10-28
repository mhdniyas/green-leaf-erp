<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Models\Category;
use App\Models\DailyPriceApproval;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopPriceGroup;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WarehouseDispatchValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $receiver;

    private Shop $shop;

    private Product $product1;

    private Product $product2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('warehouse_receiver', 'web');
        Role::findOrCreate('warehouse', 'web');

        Permission::findOrCreate('warehouse.receive.view', 'web');
        Permission::findOrCreate('warehouse.receive.confirm', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->receiver = User::factory()->create();
        $this->receiver->assignRole('warehouse_receiver');
        $this->receiver->givePermissionTo('warehouse.receive.view');
        $this->receiver->givePermissionTo('warehouse.receive.confirm');

        $priceGroup = ShopPriceGroup::query()->create([
            'name' => 'A',
            'code' => 'A',
            'default_margin_percent' => 15.00,
            'is_active' => true,
        ]);

        $this->shop = Shop::factory()->create([
            'name' => 'Grand Hotel',
            'shop_price_group_id' => $priceGroup->id,
            'status' => 'active',
        ]);

        $category = Category::factory()->create([
            'name' => 'Vegetables',
            'is_active' => true,
        ]);

        $this->product1 = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Tomato Local',
            'sku' => 'TL-01',
            'unit' => 'kg',
            'base_price' => 30.00,
            'is_active' => true,
        ]);

        $this->product2 = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Onion Red',
            'sku' => 'OR-01',
            'unit' => 'kg',
            'base_price' => 40.00,
            'is_active' => true,
        ]);
    }

    /**
     * Requirement 1: Full dispatch must reject unresolved short-loaded items.
     */
    public function test_full_dispatch_rejects_unresolved_short_loaded_items(): void
    {
        $date = '2026-10-28';
        $this->approvePrice($this->product1, $date, 'A', 35.00);
        $this->approvePrice($this->product2, $date, 'A', 45.00);

        $order = $this->createOrder($date, 'ready_for_dispatch');
        $item1 = $this->createOrderItem($order, $this->product1, 'A', 10.0, 10.0, 'loaded');
        $item2 = $this->createOrderItem($order, $this->product2, 'A', 10.0, 0.0, null); // Unloaded!

        // 1. Web Loadout move to delivery without partial flag
        $response = $this->actingAs($this->admin)->post("/warehouse/loadout/{$order->id}/move-to-delivery", [
            'partial' => false,
        ]);
        $response->assertSessionHasErrors();
        $this->assertEquals('ready_for_dispatch', $order->fresh()->delivery_status);

        // 2. API move to delivery without partial flag
        $apiResponse = $this->actingAs($this->admin)->postJson("/api/v1/warehouse/loadout/{$order->id}/move-to-delivery", [
            'partial' => false,
        ], ['X-Client-Supports-Multi-Grade' => 'true']);
        $apiResponse->assertStatus(422);
        $apiResponse->assertJsonFragment(['message' => 'Order has short-loaded items. Use partial delivery.']);
        $this->assertEquals('ready_for_dispatch', $order->fresh()->delivery_status);

        // 3. Receiver route ship without partial flag
        $receiverResponse = $this->actingAs($this->receiver)->post(route('warehouse.receiver.loadout.order.ship', $order->id), [
            'partial' => false,
        ]);
        $receiverResponse->assertSessionHasErrors();
        $this->assertEquals('ready_for_dispatch', $order->fresh()->delivery_status);
    }

    /**
     * Requirement 2 & 3: Partial dispatch must be explicitly requested and only prices billable loaded items.
     */
    public function test_partial_dispatch_succeeds_and_invoices_only_billable_loaded_items(): void
    {
        $date = '2026-10-28';
        // Product 1 (loaded) has valid price
        $this->approvePrice($this->product1, $date, 'B', 32.00);
        // Product 2 (unloaded) has ZERO price (unpriced!)
        $this->approvePrice($this->product2, $date, 'B', 0.00);

        $order = $this->createOrder($date, 'ready_for_dispatch');
        $this->createOrderItem($order, $this->product1, 'B', 6.0, 6.0, 'loaded');
        $this->createOrderItem($order, $this->product2, 'B', 15.0, 0.0, 'allocated'); // Unloaded & unpriced!

        // Partial dispatch via API
        $response = $this->actingAs($this->admin)->postJson("/api/v1/warehouse/loadout/{$order->id}/move-to-delivery", [
            'partial' => true,
        ], ['X-Client-Supports-Multi-Grade' => 'true']);

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Moved to partial delivery.']);

        $freshOrder = $order->fresh();
        $this->assertEquals('in_transit', $freshOrder->delivery_status);
        $this->assertTrue($freshOrder->is_allocation_completed);
        $this->assertNotNull($freshOrder->invoice);
        $this->assertEquals('generated', $freshOrder->invoice->status);

        // Invoice only contains the loaded billable item
        $invoiceItems = $freshOrder->invoice->items;
        $this->assertCount(1, $invoiceItems);
        $this->assertEquals($this->product1->id, $invoiceItems->first()->product_id);
        $this->assertEquals(6.0, (float) $invoiceItems->first()->approved_qty);
        $this->assertEquals(32.0, (float) $invoiceItems->first()->unit_price);
        $this->assertEquals(192.00, (float) $freshOrder->invoice->final_total);
    }

    /**
     * Requirement 4 & 5: Every loaded billable item must have approved positive pricing.
     */
    public function test_dispatch_rejects_if_any_loaded_item_is_missing_approved_positive_price(): void
    {
        $date = '2026-10-28';
        // Product 1 has ZERO price
        $this->approvePrice($this->product1, $date, 'A', 0.00);

        $order = $this->createOrder($date, 'ready_for_dispatch');
        $this->createOrderItem($order, $this->product1, 'A', 10.0, 10.0, 'loaded');

        // All items loaded, but product 1 is zero-priced
        $response = $this->actingAs($this->admin)->postJson("/api/v1/warehouse/loadout/{$order->id}/move-to-delivery", [
            'partial' => false,
        ], ['X-Client-Supports-Multi-Grade' => 'true']);

        $response->assertStatus(422);
        // Delivery status must remain untouched
        $this->assertEquals('ready_for_dispatch', $order->fresh()->delivery_status);
    }

    /**
     * Requirement 8: Repeated dispatch requests are idempotent and do not duplicate invoices.
     */
    public function test_repeated_dispatch_requests_are_idempotent_and_do_not_duplicate_invoices(): void
    {
        $date = '2026-10-28';
        $this->approvePrice($this->product1, $date, 'A', 35.00);

        $order = $this->createOrder($date, 'ready_for_dispatch');
        $this->createOrderItem($order, $this->product1, 'A', 10.0, 10.0, 'loaded');

        // First dispatch
        $res1 = $this->actingAs($this->admin)->postJson("/api/v1/warehouse/loadout/{$order->id}/move-to-delivery", [
            'partial' => false,
        ], ['X-Client-Supports-Multi-Grade' => 'true']);
        $res1->assertOk();
        $this->assertEquals('in_transit', $order->fresh()->delivery_status);
        $invoiceCount = ShopInvoice::where('shop_order_id', $order->id)->count();
        $this->assertEquals(1, $invoiceCount);

        // Repeated dispatch request is rejected because order is already in transit
        $res2 = $this->actingAs($this->admin)->postJson("/api/v1/warehouse/loadout/{$order->id}/move-to-delivery", [
            'partial' => false,
        ], ['X-Client-Supports-Multi-Grade' => 'true']);
        $res2->assertStatus(422);
        $res2->assertJsonFragment(['message' => 'Order is already in transit.']);
        $this->assertEquals(1, ShopInvoice::where('shop_order_id', $order->id)->count());
    }

    /**
     * Requirement 10: Receiver route applies identical safeguards and cannot bypass rules.
     */
    public function test_receiver_route_cannot_bypass_short_loaded_or_pricing_safeguards(): void
    {
        $date = '2026-10-28';
        // Product 1 has ZERO price
        $this->approvePrice($this->product1, $date, 'B', 0.00);

        $order = $this->createOrder($date, 'ready_for_dispatch');
        $this->createOrderItem($order, $this->product1, 'B', 5.0, 5.0, 'loaded');

        // Receiver attempts to ship via receiver ship route
        $response = $this->actingAs($this->receiver)->post(route('warehouse.receiver.loadout.order.ship', $order->id), [
            'partial' => false,
        ]);

        $response->assertSessionHasErrors();
        // Order remains in ready_for_dispatch; cannot bypass to in_transit
        $this->assertEquals('ready_for_dispatch', $order->fresh()->delivery_status);
    }

    private function createOrder(string $date, string $deliveryStatus): ShopOrder
    {
        return ShopOrder::factory()->create([
            'shop_id' => $this->shop->id,
            'business_date' => $date,
            'state' => 'approved',
            'delivery_status' => $deliveryStatus,
        ]);
    }

    private function createOrderItem(ShopOrder $order, Product $product, string $grade, float $approved, float $loaded, ?string $sortingStatus): ShopOrderItem
    {
        $status = $sortingStatus ?? ($loaded > 0 ? 'loaded' : 'allocated');

        return ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'product_grade' => $grade,
            'unit' => 'kg',
            'requested_qty' => $approved,
            'approved_qty' => $approved,
            'loaded_qty' => $loaded,
            'sorting_status' => $status,
            'is_sorted' => $status === 'loaded',
        ]);
    }

    private function approvePrice(Product $product, string $date, string $grade, float $price): DailyPriceApproval
    {
        return DailyPriceApproval::create([
            'product_id' => $product->id,
            'business_date' => $date,
            'grade' => $grade,
            'purchase_price' => 20.00,
            'price_unit' => 'kg',
            'price_a' => $price,
            'price_b' => $price,
            'price_c' => $price,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);
    }
}
