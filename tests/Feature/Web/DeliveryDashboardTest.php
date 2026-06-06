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

class DeliveryDashboardTest extends TestCase
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

    public function test_guest_user_cannot_access_delivery_dashboard(): void
    {
        $response = $this->get(route('inventory.deliveries.dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_access_delivery_dashboard(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.stock.view');

        $response = $this->actingAs($user)
            ->get(route('inventory.deliveries.dashboard'));

        $response->assertOk();
        $response->assertSee('Daily Delivery');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertSee('Live data refreshes every 30s');
    }

    public function test_delivery_dashboard_metrics_and_tables_render_correctly(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.stock.view');

        $shop1 = Shop::create(['code' => 'S1', 'name' => 'Casio Point']);
        $shop2 = Shop::create(['code' => 'S2', 'name' => 'Budegere Point']);

        // Create 2 orders for today
        $order1 = ShopOrder::create([
            'shop_id' => $shop1->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'is_allocation_completed' => true,
            'is_delivered' => true,
            'delivered_at' => now(),
            'delivered_by' => $user->id,
            'cash_collected' => 150.00,
            'cash_discrepancy' => 50.00, // Expected 200, discrepancy is 50 shortage
            'total_shortage_value' => 30.00,
            'created_by' => $user->id,
        ]);

        $order2 = ShopOrder::create([
            'shop_id' => $shop2->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'is_allocation_completed' => false,
            'is_delivered' => false,
            'created_by' => $user->id,
        ]);

        // Create product & items
        $product = Product::factory()->create();

        // StockBatch to resolve unit cost
        StockBatch::create([
            'product_id' => $product->id,
            'created_by' => $user->id,
            'reference' => 'B-01',
            'received_at' => today()->toDateString(),
            'total_kg' => 100,
            'cost_per_kg' => 10.00,
        ]);

        // item 1 with shortage
        ShopOrderItem::create([
            'shop_order_id' => $order1->id,
            'product_id' => $product->id,
            'requested_qty' => 23,
            'approved_qty' => 23,
            'delivered_qty' => 20,
            'shortage_qty' => 3,
            'unit_cost' => 10.00,
            'shortage_value' => 30.00,
            'unit' => 'kg',
        ]);

        // item 2 pending
        ShopOrderItem::create([
            'shop_order_id' => $order2->id,
            'product_id' => $product->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'unit' => 'kg',
        ]);

        // Fetch dashboard for today
        $response = $this->actingAs($user)
            ->get(route('inventory.deliveries.dashboard', ['date' => today()->toDateString()]));

        $response->assertOk();

        // Verify summary metrics render
        $response->assertSee('1 / 2'); // 1 delivered of 2 total
        $response->assertSee('Rs. 30.00'); // total shortages value
        $response->assertSee('Rs. 150.00'); // total cash collected

        // Verify tables render shop order info
        $response->assertSee('Casio Point');
        $response->assertSee('Budegere Point');

        // Verify item shortages section shows the shortage
        $response->assertSee('3.00 kg');

        // Verify cash discrepancy board shows Casio Point's variance
        $response->assertSee('Casio Point');
        $response->assertSee('Short');
    }
}
