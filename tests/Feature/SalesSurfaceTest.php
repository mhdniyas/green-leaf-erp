<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
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

    public function test_admin_can_create_update_and_delete_external_customers(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this
            ->actingAs($admin)
            ->get(route('sales.customers.index'))
            ->assertOk()
            ->assertSee('Add External Customer');

        $this
            ->post(route('sales.customers.store'), [
                'name' => 'Hotel Fresh',
                'type' => 'Restaurant',
                'contact' => 'Manager 9876543210',
                'email' => 'hotel@example.com',
                'address' => 'Main Road',
                'payment_terms' => 'Net 7',
                'credit_limit' => 25000,
                'is_active' => 1,
            ])
            ->assertRedirect(route('sales.customers.index'));

        $this->assertDatabaseHas('customers', [
            'name' => 'Hotel Fresh',
            'type' => 'Restaurant',
            'payment_terms' => 'Net 7',
        ]);

        $customer = Customer::query()->where('name', 'Hotel Fresh')->firstOrFail();

        $this
            ->get(route('sales.customers.edit', $customer))
            ->assertOk()
            ->assertSee('Edit Customer');

        $this
            ->put(route('sales.customers.update', $customer), [
                'name' => 'Hotel Fresh Updated',
                'type' => 'Supermarket',
                'contact' => 'Owner 9876543211',
                'email' => 'updated@example.com',
                'address' => 'Market Road',
                'payment_terms' => 'Net 15',
                'credit_limit' => 50000,
                'is_active' => 1,
            ])
            ->assertRedirect(route('sales.customers.index'));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Hotel Fresh Updated',
            'type' => 'Supermarket',
        ]);

        $this
            ->delete(route('sales.customers.destroy', $customer))
            ->assertRedirect(route('sales.customers.index'));

        $this->assertSoftDeleted('customers', [
            'id' => $customer->id,
        ]);
    }

    private function salesAdmin(): User
    {
        foreach ([
            'sales.customer.view',
            'sales.customer.create',
            'sales.customer.update',
            'sales.customer.delete',
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
            'sales.customer.delete',
            'sales.invoice.view',
            'sales.invoice.create',
        ]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
