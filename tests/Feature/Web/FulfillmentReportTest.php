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

class FulfillmentReportTest extends TestCase
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

    public function test_guest_user_cannot_access_fulfillment_report(): void
    {
        $response = $this->get(route('inventory.reports.fulfillment'));
        $response->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_access_fulfillment_report(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.stock.view');

        $response = $this->actingAs($user)
            ->get(route('inventory.reports.fulfillment'));

        $response->assertOk();
        $response->assertSee('inventory-sidebar-toggle', false);
        $response->assertSee('Fulfillment & Delivery Report');
    }

    public function test_shop_owner_cannot_filter_by_other_shops(): void
    {
        $shop1 = Shop::create(['code' => 'S1', 'name' => 'Casio Point']);
        $shop2 = Shop::create(['code' => 'S2', 'name' => 'Budegere Point']);

        $owner = User::factory()->create(['shop_id' => $shop1->id]);
        $owner->assignRole('shop');

        // Create orders for both shops
        ShopOrder::create([
            'shop_id' => $shop1->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $owner->id,
        ]);

        ShopOrder::create([
            'shop_id' => $shop2->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        // Accessing as Casio owner
        $response = $this->actingAs($owner)
            ->get(route('inventory.reports.fulfillment'));

        $response->assertOk();
        $response->assertSee('Casio Point');
        $response->assertDontSee('Budegere Point');
    }

    public function test_fulfillment_report_metrics_and_breakdowns_are_accurate(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.stock.view');

        $shop = Shop::create(['code' => 'S1', 'name' => 'Casio Point']);

        $order = ShopOrder::create([
            'shop_id' => $shop->id,
            'state' => 'approved',
            'business_date' => today()->toDateString(),
            'is_allocation_completed' => true,
            'is_delivered' => true,
            'delivered_at' => now(),
            'delivered_by' => $user->id,
            'cash_collected' => 80.00,
            'cash_discrepancy' => 20.00, // Expected 100, discrepancy is 20
            'total_shortage_value' => 50.00,
            'created_by' => $user->id,
        ]);

        $product = Product::factory()->create();

        // Create stock batch to resolve cost
        StockBatch::create([
            'product_id' => $product->id,
            'created_by' => $user->id,
            'reference' => 'B-01',
            'received_at' => today()->toDateString(),
            'total_kg' => 100,
            'cost_per_kg' => 10.00,
        ]);

        // item with shortage: 15 requested/approved, 10 delivered (5 shortage -> Rs 50 shortage value)
        ShopOrderItem::create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'requested_qty' => 15,
            'approved_qty' => 15,
            'delivered_qty' => 10,
            'shortage_qty' => 5,
            'unit_cost' => 10.00,
            'shortage_value' => 50.00,
            'unit' => 'kg',
        ]);

        $response = $this->actingAs($user)
            ->get(route('inventory.reports.fulfillment', [
                'start_date' => today()->subDays(1)->toDateString(),
                'end_date' => today()->toDateString(),
            ]));

        $response->assertOk();

        // Verify general stats
        $response->assertSee('66.7%'); // Approved Fulfillment Rate: 10 / 15 = 66.7%
        $response->assertSee('Rs. 50.00'); // Shortage Value
        $response->assertSee('Rs. 80.00'); // Cash Collected
        $response->assertSee('Rs. 20.00'); // Cash Variance

        // Verify product list
        $response->assertSee($product->name);
        $response->assertSee('5.00'); // shortage qty
    }
}
