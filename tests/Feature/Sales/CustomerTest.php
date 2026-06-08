<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    private User $authorizedUser;

    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->authorizedUser = User::factory()->create();
        $this->authorizedUser->givePermissionTo([
            'sales.customer.view',
            'sales.customer.create',
            'sales.customer.update',
        ]);

        $this->unauthorizedUser = User::factory()->create();
    }

    public function test_authorized_user_can_view_customers_list(): void
    {
        $customer = Customer::factory()->create();
        $shop = Shop::create([
            'code' => 'SHOP-SALES-1',
            'name' => 'Casio Shop',
        ]);
        $shopOwner = User::factory()->create([
            'name' => 'Shop Owner One',
            'shop_id' => $shop->id,
        ]);
        $shopOwner->assignRole('shop');

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('sales.customers.index'));

        $response->assertOk();
        $response->assertSee($customer->name);
        $response->assertSee('Shop Deliveries');
        $response->assertSee('Casio Shop');
        $response->assertSee('Shop Owner One');
    }

    public function test_unauthorized_user_cannot_view_customers_list(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('sales.customers.index'));

        $response->assertForbidden();
    }

    public function test_authorized_user_can_see_create_customer_page(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('sales.customers.create'));

        $response->assertOk();
    }

    public function test_authorized_user_can_store_customer(): void
    {
        $data = [
            'name' => 'Test Customer LLC',
            'type' => 'Retailer',
            'contact' => 'Ahmed (+971 50 123 4567)',
            'payment_terms' => 'COD',
            'credit_limit' => 0,
        ];

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('sales.customers.store'), $data);

        $response->assertRedirect(route('sales.customers.index'));
        $this->assertDatabaseHas('customers', [
            'name' => 'Test Customer LLC',
            'type' => 'Retailer',
        ]);
    }

    public function test_store_customer_fails_with_invalid_data(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->post(route('sales.customers.store'), [
                'name' => '',
                'type' => 'InvalidType',
                'contact' => '',
                'payment_terms' => 'InvalidTerms',
                'credit_limit' => -1,
            ]);

        $response->assertSessionHasErrors(['name', 'type', 'contact', 'payment_terms', 'credit_limit']);
    }

    public function test_authorized_user_can_update_customer(): void
    {
        $customer = Customer::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->authorizedUser)
            ->put(route('sales.customers.update', $customer), [
                'name' => 'New Name',
                'type' => 'Wholesaler',
                'contact' => 'New Contact',
                'payment_terms' => 'Net 30',
                'credit_limit' => 25000,
                'is_active' => 1,
            ]);

        $response->assertRedirect(route('sales.customers.index'));
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'New Name',
            'type' => 'Wholesaler',
        ]);
    }

    public function test_authorized_user_can_delete_customer(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->authorizedUser)
            ->delete(route('sales.customers.destroy', $customer));

        $response->assertRedirect(route('sales.customers.index'));
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_duplicate_customer_name_is_rejected(): void
    {
        Customer::factory()->create(['name' => 'Duplicate Name']);

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('sales.customers.store'), [
                'name' => 'Duplicate Name',
                'type' => 'Retailer',
                'contact' => 'Someone',
                'payment_terms' => 'COD',
                'credit_limit' => 0,
            ]);

        $response->assertSessionHasErrors(['name']);
    }
}
