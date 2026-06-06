<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    private User $authorizedUser;

    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        // User with supplier permissions
        $this->authorizedUser = User::factory()->create();
        $this->authorizedUser->givePermissionTo([
            'purchasing.supplier.view',
            'purchasing.supplier.create',
            'purchasing.supplier.update',
        ]);

        // User without supplier permissions
        $this->unauthorizedUser = User::factory()->create();
    }

    public function test_authorized_user_can_view_suppliers_list(): void
    {
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('purchasing.suppliers.index'));

        $response->assertOk();
        $response->assertSee($supplier->name);
    }

    public function test_unauthorized_user_cannot_view_suppliers_list(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('purchasing.suppliers.index'));

        $response->assertForbidden();
    }

    public function test_authorized_user_can_see_create_supplier_page(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('purchasing.suppliers.create'));

        $response->assertOk();
    }

    public function test_authorized_user_can_store_supplier(): void
    {
        $supplierData = [
            'name' => 'New Supplier Corp',
            'type' => 'Farmer',
            'category' => 'own_purchase',
            'is_default_purchase' => '1',
            'contact' => 'John Doe (012-3456789)',
            'payment_terms' => 'Net 15',
        ];

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('purchasing.suppliers.store'), $supplierData);

        $response->assertRedirect(route('purchasing.suppliers.index'));
        $this->assertDatabaseHas('suppliers', [
            'name' => 'New Supplier Corp',
            'type' => 'Farmer',
            'category' => 'own_purchase',
            'is_default_purchase' => true,
        ]);
    }

    public function test_store_supplier_fails_with_invalid_data(): void
    {
        $supplierData = [
            'name' => '', // Required
            'type' => 'InvalidType', // Must be one of Farmer, Market Agent, Importer, Co-operative
            'category' => 'invalid',
            'contact' => '', // Required
            'payment_terms' => 'InvalidTerms', // Must be COD, Net 7, Net 15, Net 30
        ];

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('purchasing.suppliers.store'), $supplierData);

        $response->assertSessionHasErrors(['name', 'type', 'category', 'contact', 'payment_terms']);
    }

    public function test_authorized_user_can_see_edit_supplier_page(): void
    {
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('purchasing.suppliers.edit', $supplier));

        $response->assertOk();
        $response->assertSee($supplier->name);
    }

    public function test_authorized_user_can_update_supplier(): void
    {
        $supplier = Supplier::factory()->create([
            'name' => 'Old Supplier Name',
        ]);

        $updateData = [
            'name' => 'Updated Supplier Name',
            'type' => 'Importer',
            'category' => 'b2b',
            'contact' => 'Jane Smith (012-9876543)',
            'payment_terms' => 'COD',
        ];

        $response = $this->actingAs($this->authorizedUser)
            ->put(route('purchasing.suppliers.update', $supplier), $updateData);

        $response->assertRedirect(route('purchasing.suppliers.index'));
        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Updated Supplier Name',
            'type' => 'Importer',
            'category' => 'b2b',
            'is_default_purchase' => false,
        ]);
    }

    public function test_new_default_purchase_supplier_replaces_previous_default(): void
    {
        $existingDefaultSupplier = Supplier::factory()->create([
            'category' => 'own_purchase',
            'is_default_purchase' => true,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('purchasing.suppliers.store'), [
                'name' => 'Primary Farm Supplier',
                'type' => 'Farmer',
                'category' => 'own_purchase',
                'is_default_purchase' => '1',
                'contact' => 'Primary Contact',
                'payment_terms' => 'COD',
            ]);

        $response->assertRedirect(route('purchasing.suppliers.index'));

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Primary Farm Supplier',
            'is_default_purchase' => true,
        ]);

        $this->assertDatabaseHas('suppliers', [
            'id' => $existingDefaultSupplier->id,
            'is_default_purchase' => false,
        ]);
    }

    public function test_authorized_user_can_delete_supplier(): void
    {
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($this->authorizedUser)
            ->delete(route('purchasing.suppliers.destroy', $supplier));

        $response->assertRedirect(route('purchasing.suppliers.index'));
        $this->assertSoftDeleted('suppliers', [
            'id' => $supplier->id,
        ]);
    }
}
