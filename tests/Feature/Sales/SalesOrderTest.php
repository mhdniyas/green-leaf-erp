<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Sales\SOStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $salesManager;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->salesManager = User::factory()->create();
        $this->salesManager->givePermissionTo([
            'sales.order.view',
            'sales.order.create',
            'sales.order.confirm',
            'sales.order.cancel',
        ]);

        $this->viewer = User::factory()->create();
        $this->viewer->givePermissionTo('sales.order.view');
    }

    private function createOrderPayload(Customer $customer, Product $product, string $grade = 'A'): array
    {
        return [
            'customer_id' => $customer->id,
            'order_date' => now()->format('Y-m-d'),
            'notes' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'grade' => $grade,
                    'quantity' => '10.000',
                    'unit_price' => '50.0000',
                ],
            ],
        ];
    }

    public function test_authorized_user_can_view_orders_index(): void
    {
        $order = SalesOrder::factory()->create();

        $response = $this->actingAs($this->salesManager)
            ->get(route('sales.orders.index'));

        $response->assertOk();
        $response->assertSee($order->so_number);
    }

    public function test_unauthorized_user_cannot_view_orders(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('sales.orders.index'));

        $response->assertForbidden();
    }

    public function test_authorized_user_can_create_sales_order(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $payload = $this->createOrderPayload($customer, $product);

        $response = $this->actingAs($this->salesManager)
            ->post(route('sales.orders.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('sales_orders', [
            'customer_id' => $customer->id,
            'status' => SOStatus::Draft->value,
        ]);
        $this->assertDatabaseHas('sales_order_items', [
            'product_id' => $product->id,
            'grade' => 'A',
        ]);
    }

    public function test_store_order_fails_with_no_items(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->salesManager)
            ->post(route('sales.orders.store'), [
                'customer_id' => $customer->id,
                'order_date' => now()->format('Y-m-d'),
                'items' => [],
            ]);

        $response->assertSessionHasErrors(['items']);
    }

    public function test_confirming_order_deducts_stock(): void
    {
        $product = Product::factory()->create();
        $customer = Customer::factory()->create();

        // Create stock
        StockMovement::factory()->create([
            'product_id' => $product->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::In,
            'quantity' => 50,
            'cost_per_unit' => 30,
            'created_by' => $this->salesManager->id,
        ]);

        // Create draft order
        $order = SalesOrder::factory()
            ->for($customer)
            ->create(['created_by' => $this->salesManager->id]);

        $order->items()->create([
            'product_id' => $product->id,
            'grade' => ProductGrade::GradeA,
            'quantity' => 10,
            'unit_price' => 50,
        ]);

        $response = $this->actingAs($this->salesManager)
            ->post(route('sales.orders.confirm', $order));

        $response->assertRedirect(route('sales.orders.show', $order));

        $order->refresh();
        $this->assertEquals(SOStatus::Confirmed, $order->status);

        // Verify a sale movement was created
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovementType::Sale->value,
            'quantity' => 10,
        ]);
    }

    public function test_confirming_order_fails_when_insufficient_stock(): void
    {
        $product = Product::factory()->create();
        $customer = Customer::factory()->create();

        // Only 5 kg in stock
        StockMovement::factory()->create([
            'product_id' => $product->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::In,
            'quantity' => 5,
            'cost_per_unit' => 30,
            'created_by' => $this->salesManager->id,
        ]);

        $order = SalesOrder::factory()
            ->for($customer)
            ->create(['created_by' => $this->salesManager->id]);

        // Trying to order 20 kg
        $order->items()->create([
            'product_id' => $product->id,
            'grade' => ProductGrade::GradeA,
            'quantity' => 20,
            'unit_price' => 50,
        ]);

        $response = $this->actingAs($this->salesManager)
            ->post(route('sales.orders.confirm', $order));

        $response->assertRedirect(route('sales.orders.show', $order));
        $response->assertSessionHas('error');

        $order->refresh();
        $this->assertEquals(SOStatus::Draft, $order->status);
    }

    public function test_cancelling_confirmed_order_reverses_stock(): void
    {
        $product = Product::factory()->create();
        $customer = Customer::factory()->create();

        StockMovement::factory()->create([
            'product_id' => $product->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::In,
            'quantity' => 100,
            'cost_per_unit' => 30,
            'created_by' => $this->salesManager->id,
        ]);

        $order = SalesOrder::factory()->confirmed()
            ->for($customer)
            ->create(['created_by' => $this->salesManager->id]);

        $order->items()->create([
            'product_id' => $product->id,
            'grade' => ProductGrade::GradeA,
            'quantity' => 10,
            'unit_price' => 50,
        ]);

        // Simulate the sale deduction
        StockMovement::factory()->create([
            'product_id' => $product->id,
            'grade' => ProductGrade::GradeA,
            'type' => StockMovementType::Sale,
            'quantity' => 10,
            'cost_per_unit' => 50,
            'created_by' => $this->salesManager->id,
            'notes' => "Sale: {$order->so_number}",
        ]);

        $response = $this->actingAs($this->salesManager)
            ->post(route('sales.orders.cancel', $order));

        $response->assertRedirect(route('sales.orders.show', $order));

        $order->refresh();
        $this->assertEquals(SOStatus::Cancelled, $order->status);

        // Reversal movement should have been created
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovementType::SaleReversal->value,
            'quantity' => 10,
        ]);
    }

    public function test_dispatch_confirmed_order(): void
    {
        $order = SalesOrder::factory()->confirmed()->create([
            'created_by' => $this->salesManager->id,
        ]);

        $response = $this->actingAs($this->salesManager)
            ->post(route('sales.orders.dispatch', $order));

        $response->assertRedirect(route('sales.orders.show', $order));

        $order->refresh();
        $this->assertEquals(SOStatus::Dispatched, $order->status);
    }

    public function test_viewer_cannot_confirm_order(): void
    {
        $order = SalesOrder::factory()->create(['created_by' => $this->viewer->id]);

        $response = $this->actingAs($this->viewer)
            ->post(route('sales.orders.confirm', $order));

        $response->assertForbidden();
    }
}
