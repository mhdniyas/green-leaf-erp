<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Models\Shop;
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
            ->assertSee('Shop Deliveries')
            ->assertSee('Sales Invoices')
            ->assertDontSee('Sales Orders')
            ->assertSee('External Customers')
            ->assertSee('Add External Customer');

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

    public function test_external_customer_crud_web_surface_works(): void
    {
        $admin = $this->salesAdmin();

        $this
            ->actingAs($admin)
            ->get('/sales/customers/create')
            ->assertOk()
            ->assertSee('Add Customer')
            ->assertSee('Create Customer');

        $this
            ->actingAs($admin)
            ->post('/sales/customers', [
                'name' => 'External Customer',
                'type' => 'Retailer',
                'contact' => 'External Contact 999',
                'email' => 'external@example.com',
                'address' => 'External Road',
                'payment_terms' => 'Net 15',
                'credit_limit' => '1500',
                'is_active' => '1',
            ])
            ->assertRedirect(route('sales.customers.index'));

        $customer = Customer::query()->where('name', 'External Customer')->firstOrFail();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'type' => 'Retailer',
            'contact' => 'External Contact 999',
            'email' => 'external@example.com',
            'payment_terms' => 'Net 15',
            'is_active' => true,
        ]);

        $this
            ->actingAs($admin)
            ->get(route('sales.customers.edit', $customer))
            ->assertOk()
            ->assertSee('Edit Customer')
            ->assertSee('External Customer');

        $this
            ->actingAs($admin)
            ->put(route('sales.customers.update', $customer), [
                'name' => 'Updated External Customer',
                'type' => 'Wholesaler',
                'contact' => 'Updated Contact',
                'email' => 'updated-external@example.com',
                'address' => 'Updated Road',
                'payment_terms' => 'Net 30',
                'credit_limit' => '2500',
                'is_active' => '0',
            ])
            ->assertRedirect(route('sales.customers.index'));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Updated External Customer',
            'type' => 'Wholesaler',
            'is_active' => false,
        ]);

        $this
            ->actingAs($admin)
            ->delete(route('sales.customers.destroy', $customer))
            ->assertRedirect(route('sales.customers.index'));

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
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

    public function test_sales_customers_page_shows_external_customer_and_shop_delivery_rows(): void
    {
        $admin = $this->salesAdmin();
        $shopRole = Role::findOrCreate('shop', 'web');
        $customer = Customer::factory()->create([
            'name' => 'Blue Valley Market',
            'type' => 'Supermarket',
            'contact' => 'Blue Valley Contact',
            'email' => 'blue-valley@example.com',
            'payment_terms' => 'Net 7',
            'credit_limit' => 7500,
        ]);

        $shop = Shop::factory()->create([
            'name' => 'Ashirwad',
            'code' => 'SHOP_ASHIRWAD',
            'warehouse_tag' => 'TAG D',
            'status' => 'active',
            'accounting_mode' => 'shop_sale',
            'accounting_enabled' => true,
            'contact_name' => 'Ashirwad Demo',
            'contact_phone' => '9876543210',
            'address' => 'Main Road',
        ]);

        $owner = User::factory()->create([
            'name' => 'Ashirwad Owner',
            'email' => 'shop-ashirwad@example.com',
            'shop_id' => $shop->id,
        ]);
        $owner->assignRole($shopRole);

        $this
            ->actingAs($admin)
            ->get(route('sales.customers.index'))
            ->assertOk()
            ->assertSee('External Customers')
            ->assertSee('Add External Customer')
            ->assertSee($customer->name)
            ->assertSee('Blue Valley Contact')
            ->assertSee('blue-valley@example.com')
            ->assertSee('Net 7')
            ->assertSee('Shop Deliveries')
            ->assertSee('Ashirwad')
            ->assertSee('SHOP_ASHIRWAD')
            ->assertSee('TAG D')
            ->assertSee('Ashirwad Demo')
            ->assertSee('9876543210')
            ->assertSee('Main Road')
            ->assertSee('Ashirwad Owner')
            ->assertSee('shop-ashirwad@example.com')
            ->assertSee('Shop Sale')
            ->assertSee('Enabled');
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
