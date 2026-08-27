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
}
