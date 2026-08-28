<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Models\Category;
use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DirectPurchaseReceiveTest extends TestCase
{
    use RefreshDatabase;

    private User $receiver;

    private User $unauthorizedUser;

    private Warehouse $warehouse;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('warehouse.receive.view', 'web');
        Permission::findOrCreate('warehouse.receive.confirm', 'web');

        $role = Role::findOrCreate('warehouse_receiver', 'web');
        $role->givePermissionTo(['warehouse.receive.view', 'warehouse.receive.confirm']);

        $this->receiver = User::factory()->create([
            'email' => 'receiver@greenleaf.test',
        ]);
        $this->receiver->assignRole('warehouse_receiver');

        $this->unauthorizedUser = User::factory()->create([
            'email' => 'user@greenleaf.test',
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Central Warehouse',
            'code' => 'CWH',
            'is_active' => true,
        ]);
        $this->receiver->warehouses()->attach($this->warehouse);

        $category = Category::create([
            'name' => 'Vegetables',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Fresh Tomato',
            'sku' => 'TOM-01',
            'unit' => 'KG',
            'category_id' => $category->id,
            'default_warehouse_id' => $this->warehouse->id,
            'is_active' => true,
        ]);
    }

    public function test_get_direct_purchase_receive_page_resolves_by_numeric_id(): void
    {
        $directOrder = ShopOrder::create([
            'order_number' => 'RQ-20260827-DP01',
            'order_source' => 'admin_direct_purchase',
            'business_date' => now()->toDateString(),
            'created_by' => $this->receiver->id,
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
            'is_allocation_completed' => false,
            'manager_note' => 'Green Leaf Direct Purchase',
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $directOrder->id,
            'product_id' => $this->product->id,
            'requested_qty' => 50.00,
            'approved_qty' => 50.00,
            'unit' => 'KG',
        ]);

        $response = $this->actingAs($this->receiver)
            ->get("/warehouse-receiver/direct-purchase/{$directOrder->id}/receive");

        $response->assertOk()
            ->assertSee('Direct Purchase Receive')
            ->assertSee('RQ-20260827-DP01')
            ->assertSee('Fresh Tomato')
            ->assertSee('Central Warehouse');

        // Confirm GET caused zero stock movements/batches
        $this->assertEquals(0, StockBatch::count());
    }

    public function test_get_direct_purchase_receive_page_resolves_by_order_number_string(): void
    {
        $directOrder = ShopOrder::create([
            'order_number' => 'RQ-20260827-DP02',
            'order_source' => 'admin_direct_purchase',
            'business_date' => now()->toDateString(),
            'created_by' => $this->receiver->id,
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
            'is_allocation_completed' => false,
            'manager_note' => 'Green Leaf Direct Purchase',
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $directOrder->id,
            'product_id' => $this->product->id,
            'requested_qty' => 75.00,
            'approved_qty' => 75.00,
            'unit' => 'KG',
        ]);

        $response = $this->actingAs($this->receiver)
            ->get("/warehouse-receiver/direct-purchase/{$directOrder->order_number}/receive");

        $response->assertOk()
            ->assertSee('Direct Purchase Receive')
            ->assertSee('RQ-20260827-DP02')
            ->assertSee('Fresh Tomato');
    }

    public function test_post_direct_purchase_receive_creates_stock_batch_and_updates_order(): void
    {
        $directOrder = ShopOrder::create([
            'order_number' => 'RQ-20260827-DP03',
            'order_source' => 'admin_direct_purchase',
            'business_date' => now()->toDateString(),
            'created_by' => $this->receiver->id,
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
            'is_allocation_completed' => false,
            'manager_note' => 'Green Leaf Direct Purchase',
        ]);

        $item = ShopOrderItem::create([
            'shop_order_id' => $directOrder->id,
            'product_id' => $this->product->id,
            'requested_qty' => 120.00,
            'approved_qty' => 120.00,
            'unit' => 'KG',
        ]);

        $response = $this->actingAs($this->receiver)
            ->post("/warehouse-receiver/direct-purchase/{$directOrder->id}/receive", [
                'items' => [
                    $item->id => [
                        'warehouse_id' => $this->warehouse->id,
                    ],
                ],
            ]);

        $response->assertRedirect()
            ->assertSessionHas('success');

        // Verify StockBatch created
        $this->assertDatabaseHas('stock_batches', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'total_kg' => 120.00,
            'warehouse_receive_pending' => false,
        ]);

        // Verify ShopOrder status updated
        $directOrder->refresh();
        $this->assertEquals('ready_for_dispatch', $directOrder->delivery_status);
    }

    public function test_unauthorized_user_is_forbidden_from_viewing_and_receiving(): void
    {
        $directOrder = ShopOrder::create([
            'order_number' => 'RQ-20260827-DP04',
            'order_source' => 'admin_direct_purchase',
            'business_date' => now()->toDateString(),
            'created_by' => $this->receiver->id,
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
        ]);

        $this->unauthorizedUser->givePermissionTo('warehouse.receive.view');

        $responseGet = $this->actingAs($this->unauthorizedUser)
            ->get("/warehouse-receiver/direct-purchase/{$directOrder->id}/receive");

        $responseGet->assertRedirect(route('dashboard'));

        $responseGetJson = $this->actingAs($this->unauthorizedUser)
            ->getJson("/warehouse-receiver/direct-purchase/{$directOrder->id}/receive");

        $responseGetJson->assertForbidden();

        $responsePost = $this->actingAs($this->unauthorizedUser)
            ->post("/warehouse-receiver/direct-purchase/{$directOrder->id}/receive", [
                'items' => [],
            ]);

        $responsePost->assertForbidden();
    }

    public function test_non_direct_purchase_order_returns_404(): void
    {
        $regularOrder = ShopOrder::create([
            'order_number' => 'RQ-20260827-REGULAR',
            'order_source' => 'shop_daily',
            'business_date' => now()->toDateString(),
            'created_by' => $this->receiver->id,
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
        ]);

        $response = $this->actingAs($this->receiver)
            ->get("/warehouse-receiver/direct-purchase/{$regularOrder->id}/receive");

        $response->assertNotFound();
    }

    public function test_non_existent_order_id_returns_404(): void
    {
        $response = $this->actingAs($this->receiver)
            ->get('/warehouse-receiver/direct-purchase/999999/receive');

        $response->assertNotFound();
    }

    public function test_checklist_pending_tab_includes_direct_purchase_and_loadout_tab_excludes_it(): void
    {
        $today = now()->toDateString();

        $directOrder = ShopOrder::create([
            'order_number' => 'RQ-20260827-DP-PENDING',
            'order_source' => 'admin_direct_purchase',
            'business_date' => $today,
            'created_by' => $this->receiver->id,
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
            'is_allocation_completed' => false,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $directOrder->id,
            'product_id' => $this->product->id,
            'requested_qty' => 30.00,
            'approved_qty' => 30.00,
            'unit' => 'KG',
        ]);

        $shopOrder = ShopOrder::create([
            'order_number' => 'RQ-20260827-SHOP-LOADOUT',
            'order_source' => 'shop_owner',
            'business_date' => $today,
            'created_by' => $this->receiver->id,
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
            'is_allocation_completed' => false,
        ]);

        ShopOrderItem::create([
            'shop_order_id' => $shopOrder->id,
            'product_id' => $this->product->id,
            'requested_qty' => 40.00,
            'approved_qty' => 40.00,
            'unit' => 'KG',
        ]);

        // Pending receive tab must include direct purchase order
        $responsePending = $this->actingAs($this->receiver)
            ->getJson("/warehouse-receiver/tab/pending?date={$today}");

        $responsePending->assertOk();
        $pendingData = $responsePending->json();
        $directOrderNumbers = collect($pendingData['pending_direct_orders'])->pluck('order_number')->all();
        $this->assertContains('RQ-20260827-DP-PENDING', $directOrderNumbers);

        // Loadout tab must contain shop order but NEVER direct purchase
        $responseLoadout = $this->actingAs($this->receiver)
            ->getJson("/warehouse-receiver/tab/loadout?date={$today}");

        $responseLoadout->assertOk();
        $loadoutData = $responseLoadout->json();
        $loadoutOrderNumbers = collect($loadoutData['orders'])->pluck('order_number')->all();
        $this->assertContains('RQ-20260827-SHOP-LOADOUT', $loadoutOrderNumbers);
        $this->assertNotContains('RQ-20260827-DP-PENDING', $loadoutOrderNumbers);

        // Deliveries tab must also exclude direct purchase
        $responseDeliveries = $this->actingAs($this->receiver)
            ->getJson("/warehouse-receiver/tab/deliveries?date={$today}");

        $responseDeliveries->assertOk();
        $deliveriesData = $responseDeliveries->json();
        $deliveriesOrderNumbers = collect($deliveriesData['orders'])->pluck('order_number')->all();
        $this->assertContains('RQ-20260827-SHOP-LOADOUT', $deliveriesOrderNumbers);
        $this->assertNotContains('RQ-20260827-DP-PENDING', $deliveriesOrderNumbers);
    }

    public function test_loadout_details_page_rejects_direct_purchase_with_404(): void
    {
        $today = now()->toDateString();

        $directOrder = ShopOrder::create([
            'order_number' => 'RQ-20260827-DP-BLOCK',
            'order_source' => 'admin_direct_purchase',
            'business_date' => $today,
            'created_by' => $this->receiver->id,
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
        ]);

        $shopOrder = ShopOrder::create([
            'order_number' => 'RQ-20260827-SHOP-ALLOW',
            'order_source' => 'shop_owner',
            'business_date' => $today,
            'created_by' => $this->receiver->id,
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
        ]);

        // Direct purchase must 404 on loadout details
        $this->actingAs($this->receiver)
            ->get("/warehouse-receiver/loadout/{$directOrder->id}")
            ->assertNotFound();

        // Shop order must 200 on loadout details
        $this->actingAs($this->receiver)
            ->get("/warehouse-receiver/loadout/{$shopOrder->id}")
            ->assertOk();
    }

    public function test_partial_loadout_appears_in_loadout_tab(): void
    {
        $today = now()->toDateString();

        $product2 = Product::create([
            'name' => 'Fresh Onion',
            'sku' => 'ONI-01',
            'unit' => 'KG',
            'category_id' => $this->product->category_id,
            'default_warehouse_id' => $this->warehouse->id,
            'is_active' => true,
        ]);

        $partialOrder = ShopOrder::create([
            'order_number' => 'RQ-20260827-PARTIAL',
            'order_source' => 'shop_owner',
            'business_date' => $today,
            'created_by' => $this->receiver->id,
            'state' => 'approved',
            'delivery_status' => 'pending_delivery',
        ]);

        // Item 1 loaded
        ShopOrderItem::create([
            'shop_order_id' => $partialOrder->id,
            'product_id' => $this->product->id,
            'requested_qty' => 10.00,
            'approved_qty' => 10.00,
            'loaded_qty' => 10.00,
            'sorting_status' => 'loaded',
            'unit' => 'KG',
        ]);

        // Item 2 not loaded
        ShopOrderItem::create([
            'shop_order_id' => $partialOrder->id,
            'product_id' => $product2->id,
            'requested_qty' => 20.00,
            'approved_qty' => 20.00,
            'loaded_qty' => 0.00,
            'sorting_status' => 'pending',
            'unit' => 'KG',
        ]);

        $response = $this->actingAs($this->receiver)
            ->getJson("/warehouse-receiver/tab/loadout?date={$today}");

        $response->assertOk();
        $orderData = collect($response->json('orders'))->firstWhere('order_number', 'RQ-20260827-PARTIAL');
        $this->assertNotNull($orderData);
        $this->assertEquals('Partially Loaded', $orderData['loading_status']);
        $this->assertEquals(2, $orderData['total_items_count']);
        $this->assertEquals(1, $orderData['loaded_items_count']);
    }
}
