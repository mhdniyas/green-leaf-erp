<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Employee;
use App\Models\EmployeeCategory;
use App\Models\Shop;
use App\Models\ShopOwnerAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmergencyContactValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopOwner;

    private Shop $shop;

    private EmployeeCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(['admin', 'purchaser']);

        $this->shopOwner = User::factory()->create();
        $this->shopOwner->assignRole('shop');
        $this->shopOwner->givePermissionTo('hr.attendance.mark-owned-shop');

        $this->shop = Shop::query()->create([
            'name' => 'Casio Shop',
            'code' => 'CASIO',
            'warehouse_tag' => 'CS',
            'is_active' => true,
        ]);

        ShopOwnerAssignment::query()->create([
            'user_id' => $this->shopOwner->id,
            'shop_id' => $this->shop->id,
            'status' => 'active',
        ]);

        $this->category = EmployeeCategory::query()->create([
            'name' => 'Sales Staff',
            'code' => 'SALES-STAFF',
            'staff_area' => 'shop',
            'is_active' => true,
        ]);
    }

    public function test_admin_create_requires_primary_phone_and_emergency_contact(): void
    {
        Storage::fake('public');

        // Missing emergency contact
        $response1 = $this->actingAs($this->admin)
            ->post(route('admin.staff.store'), [
                'name' => 'Admin Staff One',
                'employee_code' => 'EMP-ADM-001',
                'employee_category_id' => $this->category->id,
                'staff_area' => 'shop',
                'default_shop_id' => $this->shop->id,
                'phone' => '9876543210',
                'alternate_phone' => '',
                'id_type' => 'aadhaar',
                'id_number' => '999988887777',
                'salary_type' => 'monthly',
                'monthly_salary' => 20000,
                'employment_status' => 'active',
            ]);

        $response1->assertSessionHasErrors(['alternate_phone' => 'Emergency contact number is required.']);

        // Missing primary phone
        $response2 = $this->actingAs($this->admin)
            ->post(route('admin.staff.store'), [
                'name' => 'Admin Staff Two',
                'employee_code' => 'EMP-ADM-002',
                'employee_category_id' => $this->category->id,
                'staff_area' => 'shop',
                'default_shop_id' => $this->shop->id,
                'phone' => '',
                'alternate_phone' => '9123456789',
                'id_type' => 'aadhaar',
                'id_number' => '999988887777',
                'salary_type' => 'monthly',
                'monthly_salary' => 20000,
                'employment_status' => 'active',
            ]);

        $response2->assertSessionHasErrors(['phone' => 'Primary phone number is required.']);

        // Both provided
        $response3 = $this->actingAs($this->admin)
            ->post(route('admin.staff.store'), [
                'name' => 'Admin Staff Three',
                'employee_code' => 'EMP-ADM-003',
                'employee_category_id' => $this->category->id,
                'staff_area' => 'shop',
                'default_shop_id' => $this->shop->id,
                'phone' => '9876543210',
                'alternate_phone' => '9123456789',
                'id_type' => 'aadhaar',
                'id_number' => '999988887777',
                'salary_type' => 'monthly',
                'monthly_salary' => 20000,
                'employment_status' => 'active',
            ]);

        $response3->assertRedirect(route('admin.staff.employees.index'));
        $this->assertDatabaseHas('employees', [
            'employee_code' => 'EMP-ADM-003',
            'phone' => '9876543210',
            'alternate_phone' => '9123456789',
        ]);
    }

    public function test_shop_create_requires_primary_phone_and_emergency_contact(): void
    {
        // Missing emergency contact
        $response1 = $this->actingAs($this->shopOwner)
            ->post(route('shop-owner.staff.employees.store'), [
                'shop_id' => $this->shop->id,
                'name' => 'Shop Staff One',
                'phone' => '9876543210',
                'alternate_phone' => '',
                'joined_on' => '2026-09-01',
                'id_type' => 'aadhaar',
                'id_number' => '999988887777',
                'address' => 'Kochi',
                'photo_data_url' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
                'id_front_data_url' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            ]);

        $response1->assertSessionHasErrors(['alternate_phone' => 'Emergency contact number is required.']);

        // Both provided
        $response2 = $this->actingAs($this->shopOwner)
            ->post(route('shop-owner.staff.employees.store'), [
                'shop_id' => $this->shop->id,
                'name' => 'Shop Staff Two',
                'phone' => '9876543210',
                'alternate_phone' => '9123456789',
                'salary_type' => 'monthly',
                'monthly_salary' => 20000,
                'joined_on' => '2026-09-01',
                'id_type' => 'aadhaar',
                'id_number' => '999988887777',
                'address' => 'Kochi',
                'photo_data_url' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
                'id_front_data_url' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            ]);

        $response2->assertRedirect();
        $this->assertDatabaseHas('employees', [
            'name' => 'Shop Staff Two',
            'phone' => '9876543210',
            'alternate_phone' => '9123456789',
            'verification_status' => 'pending',
        ]);
    }

    public function test_submitted_emergency_contact_appears_on_approval_review_page(): void
    {
        $employee = Employee::query()->create([
            'employee_code' => 'EMP-REV-001',
            'name' => 'Review Target',
            'phone' => '9876543210',
            'alternate_phone' => '9988776655',
            'id_type' => 'aadhaar',
            'id_number' => '123456789012',
            'address' => 'Test Address',
            'default_shop_id' => $this->shop->id,
            'staff_area' => 'shop',
            'verification_status' => 'pending',
            'employment_status' => 'active',
            'joined_on' => '2026-09-01',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.staff.approvals.show', $employee));

        $response->assertOk();
        $response->assertSee('Emergency Contact');
        $response->assertSee('9988776655');
    }

    public function test_employee_edit_requires_emergency_contact_and_displays_label(): void
    {
        $employee = Employee::query()->create([
            'employee_code' => 'EMP-EDIT-001',
            'name' => 'Edit Target',
            'phone' => '9876543210',
            'alternate_phone' => '9988776655',
            'employee_category_id' => $this->category->id,
            'id_type' => 'aadhaar',
            'id_number' => '123456789012',
            'address' => 'Test Address',
            'default_shop_id' => $this->shop->id,
            'staff_area' => 'shop',
            'verification_status' => 'approved',
            'employment_status' => 'active',
            'salary_type' => 'monthly',
            'monthly_salary' => 25000,
            'joined_on' => '2026-09-01',
        ]);

        $showResponse = $this->actingAs($this->admin)
            ->get(route('admin.staff.show', $employee));

        $showResponse->assertOk();
        $showResponse->assertSee('Emergency: 9988776655');
        $showResponse->assertSee('Emergency Contact Number *');

        // Cannot update without emergency contact
        $updateResponse1 = $this->actingAs($this->admin)
            ->put(route('admin.staff.update', $employee), [
                'name' => 'Edit Target Updated',
                'employee_code' => 'EMP-EDIT-001',
                'employee_category_id' => $this->category->id,
                'staff_area' => 'shop',
                'phone' => '9876543210',
                'alternate_phone' => '',
                'id_type' => 'aadhaar',
                'id_number' => '123456789012',
                'salary_type' => 'monthly',
                'monthly_salary' => 25000,
                'employment_status' => 'active',
            ]);

        $updateResponse1->assertSessionHasErrors(['alternate_phone' => 'Emergency contact number is required.']);
    }
}
