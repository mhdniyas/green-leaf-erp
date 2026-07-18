<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SalesSurfaceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        view()->share('errors', new ViewErrorBag);
    }

    public function test_sales_customers_and_invoices_use_admin_layout_sidebar(): void
    {
        $admin = $this->salesAdmin();

        $this
            ->actingAs($admin)
            ->get(route('sales.customers.index'))
            ->assertOk()
            ->assertSee('Admin Panel')
            ->assertSee('Sales Destinations')
            ->assertSee('Sales Invoices')
            ->assertDontSee('Sales Orders');

        $this
            ->actingAs($admin)
            ->get(route('sales.invoices.index'))
            ->assertOk()
            ->assertSee('Admin Panel')
            ->assertSee('Sales Invoices')
            ->assertSee('Customers')
            ->assertDontSee('Sales Orders');
    }

    public function test_sales_orders_web_surface_is_removed(): void
    {
        $this
            ->actingAs($this->salesAdmin())
            ->get('/sales/orders')
            ->assertNotFound();
    }

    public function test_sales_invoice_pages_tolerate_archived_related_records(): void
    {
        $admin = $this->salesAdmin();
        $invoice = SalesInvoice::factory()->create();

        $invoice->customer->delete();
        $invoice->salesOrder->delete();

        $this
            ->actingAs($admin)
            ->get(route('sales.invoices.index'))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee($invoice->customer->name)
            ->assertSee($invoice->salesOrder->so_number);

        $this
            ->actingAs($admin)
            ->get(route('sales.invoices.show', $invoice))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee($invoice->customer->name)
            ->assertSee($invoice->salesOrder->so_number);
    }

    private function salesAdmin(): User
    {
        foreach ([
            'sales.customer.view',
            'sales.customer.create',
            'sales.customer.update',
            'sales.invoice.view',
            'sales.invoice.create',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role = Role::findOrCreate('admin', 'web');
        $role->syncPermissions([
            'sales.customer.view',
            'sales.customer.create',
            'sales.customer.update',
            'sales.invoice.view',
            'sales.invoice.create',
        ]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
