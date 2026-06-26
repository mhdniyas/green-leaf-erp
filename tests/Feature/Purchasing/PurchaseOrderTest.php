<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Purchasing\POStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaserCart;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $authorizedUser;

    private User $unauthorizedUser;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        // User with PO permissions
        $this->authorizedUser = User::factory()->create();
        $this->authorizedUser->givePermissionTo([
            'purchasing.order.view',
            'purchasing.order.create',
            'purchasing.order.approve',
        ]);

        $this->unauthorizedUser = User::factory()->create();

        $this->supplier = Supplier::factory()->create();
        $category = Category::factory()->create();
        $this->product = Product::factory()->create(['category_id' => $category->id]);
    }

    public function test_authorized_user_can_view_purchase_orders_list(): void
    {
        $order = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->authorizedUser->id,
            'order_date' => today()->addDay()->toDateString(),
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('purchasing.orders.index'));

        $response->assertOk();
        $response->assertSee('Purchase Manager Dashboard');
        $response->assertSee('Total shop orders');
    }

    public function test_purchase_orders_dashboard_shows_tomorrow_orders_and_today_deliveries(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(12, 0));

        $shop = Shop::create([
            'code' => 'SHOP_PM_DASHBOARD',
            'name' => 'Purchase Dashboard Shop',
        ]);

        ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'submitted',
            'business_date' => today()->addDay()->toDateString(),
            'created_by' => $this->authorizedUser->id,
        ]);

        ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $this->authorizedUser->id,
            'is_delivered' => true,
            'delivered_at' => now(),
            'delivered_by' => $this->authorizedUser->id,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('purchasing.orders.index'));

        $response->assertOk();
        $response->assertSee('Purchase Manager Dashboard');
        $response->assertSee('Total shop orders');
        $response->assertSee('Delivery done');
        $response->assertSee('Purchase Dashboard Shop');
        $response->assertSee('Open Approve Shop Orders');
    }

    public function test_unauthorized_user_cannot_view_purchase_orders_list(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('purchasing.orders.index'));

        $response->assertForbidden();
    }

    public function test_authorized_user_can_create_purchase_order(): void
    {
        $orderData = [
            'supplier_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'fulfillment_type' => 'warehouse',
            'notes' => 'Some test notes for PO',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 150.50,
                    'unit_price' => 2.50,
                ],
            ],
        ];

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('purchasing.orders.store'), $orderData);

        // Should redirect to the show page
        $order = PurchaseOrder::latest('id')->first();
        $response->assertRedirect(route('purchasing.orders.show', $order));

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $order->id,
            'supplier_id' => $this->supplier->id,
            'status' => POStatus::Draft->value,
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 150.50,
            'unit_price' => 2.50,
        ]);

        $this->assertEquals(376.25, $order->total_amount);
    }

    public function test_authorized_user_can_update_draft_purchase_order(): void
    {
        $order = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->authorizedUser->id,
            'status' => POStatus::Draft,
        ]);
        $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 10.00,
            'unit_price' => 10.00,
        ]);

        $updateData = [
            'supplier_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'fulfillment_type' => 'warehouse',
            'notes' => 'Updated notes',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 20.00,
                    'unit_price' => 5.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->authorizedUser)
            ->put(route('purchasing.orders.update', $order), $updateData);

        $response->assertRedirect(route('purchasing.orders.show', $order));

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $order->id,
            'notes' => 'Updated notes',
        ]);

        $order->refresh();
        $this->assertEquals(100.00, $order->total_amount);
    }

    public function test_authorized_user_can_approve_purchase_order(): void
    {
        $order = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->authorizedUser->id,
            'status' => POStatus::Draft,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('purchasing.orders.approve', $order));

        $response->assertRedirect(route('purchasing.orders.show', $order));
        $this->assertDatabaseHas('purchase_orders', [
            'id' => $order->id,
            'status' => POStatus::Approved->value,
        ]);
    }

    public function test_authorized_user_can_delete_draft_purchase_order(): void
    {
        $order = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->authorizedUser->id,
            'status' => POStatus::Draft,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->delete(route('purchasing.orders.destroy', $order));

        $response->assertRedirect(route('purchasing.orders.index'));
        $this->assertSoftDeleted('purchase_orders', [
            'id' => $order->id,
        ]);
    }

    public function test_routes_use_po_number_instead_of_id(): void
    {
        $order = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->authorizedUser->id,
            'po_number' => 'PO-2026-TEST-99',
        ]);

        $showUrl = route('purchasing.orders.show', $order);
        $this->assertStringContainsString('PO-2026-TEST-99', $showUrl);
        $this->assertStringNotContainsString("/{$order->id}", $showUrl);

        $response = $this->actingAs($this->authorizedUser)->get($showUrl);
        $response->assertOk();
    }

    public function test_can_update_purchase_order_items(): void
    {
        $order = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->authorizedUser->id,
            'status' => POStatus::Approved,
        ]);
        $item = $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 10.00,
            'unit_price' => 5.00,
            'purchase_unit' => 'kg',
        ]);

        $updateData = [
            'items' => [
                [
                    'id' => $item->id,
                    'product_id' => $this->product->id,
                    'purchase_unit' => 'packet',
                    'packet_qty' => 5.00,
                    'weight_per_packet' => 2.50,
                    'actual_weight' => 12.00,
                    'unit_price' => 6.00,
                    'price_basis' => 'per_kg',
                    'quantity' => 12.50, // expected quantity
                ],
            ],
        ];

        $response = $this->actingAs($this->authorizedUser)
            ->put(route('purchasing.orders.items.update', $order), $updateData);

        $response->assertRedirect(route('purchasing.orders.show', $order));

        $item->refresh();
        $this->assertEquals('packet', $item->purchase_unit);
        $this->assertEquals(5.00, (float) $item->packet_qty);
        $this->assertEquals(2.50, (float) $item->weight_per_packet);
        $this->assertEquals(12.00, (float) $item->actual_weight);
        $this->assertEquals(12.50, (float) $item->quantity);
        $this->assertEquals(6.00, (float) $item->unit_price);
        $this->assertEquals(72.00, $item->subtotal);

        $order->refresh();
        $this->assertEquals(72.00, $order->total_amount);
    }

    public function test_purchase_order_item_can_be_priced_per_packet_or_bag(): void
    {
        $order = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->authorizedUser->id,
            'status' => POStatus::Approved,
        ]);
        $item = $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 100.00,
            'unit_price' => 750.00,
            'purchase_unit' => 'bag',
            'price_basis' => 'per_kg',
        ]);

        $updateData = [
            'items' => [
                [
                    'id' => $item->id,
                    'product_id' => $this->product->id,
                    'purchase_unit' => 'bag',
                    'packet_qty' => 4.00,
                    'weight_per_packet' => 25.00,
                    'actual_weight' => 96.00,
                    'unit_price' => 750.00,
                    'price_basis' => 'per_unit',
                    'quantity' => 100.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->authorizedUser)
            ->put(route('purchasing.orders.items.update', $order), $updateData);

        $response->assertRedirect(route('purchasing.orders.show', $order));

        $item->refresh();
        $this->assertEquals('per_unit', $item->price_basis);
        $this->assertEquals(3000.00, $item->subtotal);

        $order->refresh();
        $this->assertEquals(3000.00, $order->total_amount);
    }

    public function test_purchase_order_item_product_can_be_changed_from_dynamic_table(): void
    {
        $replacementProduct = Product::factory()->create([
            'category_id' => $this->product->category_id,
            'name' => 'Replacement Leaf',
        ]);

        $order = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->authorizedUser->id,
            'status' => POStatus::Approved,
        ]);
        $item = $order->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 10.00,
            'unit_price' => 5.00,
            'purchase_unit' => 'kg',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->put(route('purchasing.orders.items.update', $order), [
                'items' => [
                    [
                        'id' => $item->id,
                        'product_id' => $replacementProduct->id,
                        'purchase_unit' => 'kg',
                        'packet_qty' => null,
                        'weight_per_packet' => null,
                        'actual_weight' => null,
                        'unit_price' => 8.00,
                        'price_basis' => 'per_kg',
                        'quantity' => 12.00,
                    ],
                ],
            ]);

        $response->assertRedirect(route('purchasing.orders.show', $order));

        $item->refresh();
        $this->assertEquals($replacementProduct->id, $item->product_id);
        $this->assertEquals(12.00, (float) $item->quantity);
        $this->assertEquals(96.00, $item->subtotal);
    }

    public function test_previous_day_price_is_displayed_correctly(): void
    {
        $order1 = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->authorizedUser->id,
            'status' => POStatus::Approved,
            'order_date' => now()->subDays(2)->toDateString(),
        ]);
        $order1->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 10.00,
            'unit_price' => 4.50,
        ]);

        $order2 = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->authorizedUser->id,
            'status' => POStatus::Draft,
            'order_date' => now()->toDateString(),
        ]);
        $order2->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 10.00,
            'unit_price' => 5.00,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('purchasing.orders.show', $order2));

        $response->assertOk();
        $response->assertSee('Prev. Price: INR 4.5000');
        $response->assertSee('min-w-[1100px]', false);
        $response->assertSee('[-webkit-overflow-scrolling:touch]', false);
    }

    public function test_purchaser_carts_and_unfulfilled_orders_are_automatically_cancelled_on_dashboard_load(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25 12:00:00'));

        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');

        // Past draft cart
        $pastCart = PurchaserCart::create([
            'user_id' => $purchaser->id,
            'business_date' => '2026-06-24',
            'cart_number' => 'VC-20260624-XXXX',
            'status' => 'draft',
        ]);
        $pastCart->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 5.0,
            'unit_price' => 10.0,
            'line_total' => 50.0,
        ]);

        // Past draft PO
        $pastPO = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-2026-PAST',
            'status' => POStatus::Draft,
            'order_date' => '2026-06-24',
            'created_by' => $this->authorizedUser->id,
        ]);
        $pastPO->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 10.0,
            'unit_price' => 12.0,
            'purchase_unit' => 'kg',
        ]);

        // Verify they are draft initially
        $this->assertEquals('draft', $pastCart->fresh()->status);
        $this->assertEquals(POStatus::Draft, $pastPO->fresh()->status);

        // Access manager dashboard to trigger cancellation
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('purchasing.orders.index'));

        $response->assertOk();

        // Check if status is cancelled now
        $this->assertEquals('cancelled', $pastCart->fresh()->status);
        $this->assertEquals(POStatus::Cancelled, $pastPO->fresh()->status);

        // Check if dashboard displays them in the Cancelled section
        $response->assertSee('Cancelled purchases');
        $response->assertSee('VC-20260624-XXXX');
        $response->assertSee('PO-2026-PAST');

        Carbon::setTestNow(); // Reset time
    }

    public function test_purchaser_dashboard_redirects_past_date_access(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25 12:00:00'));

        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');

        // Accessing past date daily dashboard should redirect to today (2026-06-25)
        $response = $this->actingAs($purchaser)
            ->get(route('purchaser.daily', ['date' => '2026-06-24']));

        $response->assertRedirect(route('purchaser.daily', ['date' => '2026-06-25']));
        $response->assertSessionHas('error', 'Only the active business day order can be viewed/processed.');

        Carbon::setTestNow(); // Reset time
    }
}
