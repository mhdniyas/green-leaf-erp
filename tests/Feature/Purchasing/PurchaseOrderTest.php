<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Enums\Purchasing\POStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('purchasing.orders.index'));

        $response->assertOk();
        $response->assertSee($order->po_number);
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
}
