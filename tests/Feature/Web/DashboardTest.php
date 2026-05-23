<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\Sales\SOStatus;
use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_user_is_redirected_to_login(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_dashboard_renders_sales_kpis_when_authorized(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('sales.order.view');

        // Create some sample data for sales stats
        $customer = Customer::factory()->create(['is_active' => true]);
        SalesOrder::factory()->create(['status' => SOStatus::Confirmed]);
        SalesInvoice::factory()->create(['amount' => 12500.50]);

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Active Customers');
        $response->assertSee('Pending Sales Orders');
        $response->assertSee('Monthly Sales');
        $response->assertSee('INR 12,500.50');
    }

    public function test_dashboard_does_not_render_sales_kpis_when_unauthorized(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Active Customers');
        $response->assertDontSee('Pending Sales Orders');
    }

    public function test_dashboard_renders_modules_based_on_permissions(): void
    {
        $user = User::factory()->create();

        // Give customer and users management permission
        $user->givePermissionTo(['sales.customer.view', 'admin.user.view']);

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Customers');
        $response->assertSee('Users & Roles');

        // Should not see sales orders or invoices as they are not permitted
        $response->assertDontSee('Sales Orders');
        $response->assertDontSee('Sales Invoices');
    }
}
