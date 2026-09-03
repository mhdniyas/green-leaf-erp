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
use Tests\TestCase;

class ShopEmployeeApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $shopOwner;

    private User $admin;

    private Shop $shop;

    private EmployeeCategory $shopCategory;

    private EmployeeCategory $officeCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
        ]);
        $this->admin->assignRole(['admin', 'purchaser']);
        $this->admin->givePermissionTo('hr.employee.view');
        $this->admin->givePermissionTo('hr.employee.update');

        $this->shopOwner = User::factory()->create([
            'email' => 'owner@example.com',
        ]);
        $this->shopOwner->assignRole('shop');
        $this->shopOwner->givePermissionTo('hr.attendance.mark-owned-shop');

        $this->shop = Shop::factory()->create([
            'name' => 'Ashirwad Shop',
            'code' => 'ASHIRWAD',
        ]);

        ShopOwnerAssignment::create([
            'user_id' => $this->shopOwner->id,
            'shop_id' => $this->shop->id,
            'status' => 'active',
        ]);

        $this->shopCategory = EmployeeCategory::create([
            'code' => 'SHOP_WORKER',
            'name' => 'Shop Sales Staff',
            'staff_area' => 'shop',
            'is_active' => true,
        ]);

        $this->officeCategory = EmployeeCategory::create([
            'code' => 'OFFICE_MGR',
            'name' => 'Office Manager',
            'staff_area' => 'office',
            'is_active' => true,
        ]);
    }

    public function test_shop_owner_can_access_create_staff_form(): void
    {
        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.create', ['shop' => $this->shop->code]));

        $response->assertOk();
        $response->assertSee('Add New Shop Staff');
        $response->assertDontSee('Role / Designation');
        $response->assertDontSee('Salary Type');
    }

    public function test_shop_owner_submitting_staff_creates_pending_employee_without_category_or_salary(): void
    {
        $payload = [
            'shop_id' => $this->shop->id,
            'name' => 'Rahul Sharma',
            'phone' => '9876543210',
            'alternate_phone' => '9876543211',
            'email' => 'rahul@example.com',
            'joined_on' => '2026-09-01',
            'id_type' => 'aadhaar',
            'id_number' => '1234-5678-9012',
            'address' => '123 MG Road, Kochi',
            'photo_data_url' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'id_front_data_url' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        ];

        $response = $this->actingAs($this->shopOwner)
            ->post(route('shop-owner.staff.employees.store'), $payload);

        $response->assertRedirect(route('shop-owner.staff.index', ['shop' => $this->shop->code]));
        $response->assertSessionHas('success', 'Employee submitted for HR approval.');

        $this->assertDatabaseHas('employees', [
            'name' => 'Rahul Sharma',
            'default_shop_id' => $this->shop->id,
            'employee_category_id' => null,
            'salary_type' => null,
            'monthly_salary' => null,
            'daily_wage' => null,
            'staff_area' => 'shop',
            'verification_status' => 'pending',
            'submitted_by' => $this->shopOwner->id,
        ]);
    }

    public function test_pending_approvals_index_shows_only_pending_employees(): void
    {
        $pendingEmployee = Employee::create([
            'employee_code' => 'EMP-00101',
            'name' => 'Pending Staff Member',
            'phone' => '9998887770',
            'default_shop_id' => $this->shop->id,
            'employee_category_id' => null,
            'staff_area' => 'shop',
            'employment_status' => 'active',
            'verification_status' => 'pending',
            'submitted_by' => $this->shopOwner->id,
            'joined_on' => '2026-09-01',
            'id_type' => 'aadhaar',
            'id_number' => '1111-2222-3333',
            'address' => 'Kochi',
        ]);

        $approvedEmployee = Employee::create([
            'employee_code' => 'EMP-00100',
            'name' => 'Already Approved Staff',
            'phone' => '9998887779',
            'default_shop_id' => $this->shop->id,
            'employee_category_id' => $this->shopCategory->id,
            'salary_type' => 'monthly',
            'monthly_salary' => 18000,
            'staff_area' => 'shop',
            'employment_status' => 'active',
            'verification_status' => 'approved',
            'joined_on' => '2026-09-01',
            'id_type' => 'aadhaar',
            'id_number' => '1111-2222-9999',
            'address' => 'Kochi',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.staff.approvals.index'));

        $response->assertOk();
        $response->assertSee('Pending Staff Member');
        $response->assertSee('Ashirwad Shop');
        $response->assertDontSee('Already Approved Staff');
    }

    public function test_review_page_displays_employee_and_shop_details(): void
    {
        $pendingEmployee = Employee::create([
            'employee_code' => 'EMP-00101',
            'name' => 'Pending Staff Member',
            'phone' => '9998887770',
            'default_shop_id' => $this->shop->id,
            'employee_category_id' => null,
            'staff_area' => 'shop',
            'employment_status' => 'active',
            'verification_status' => 'pending',
            'submitted_by' => $this->shopOwner->id,
            'joined_on' => '2026-09-01',
            'id_type' => 'aadhaar',
            'id_number' => '1111-2222-3333',
            'address' => 'Kochi',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.staff.approvals.show', $pendingEmployee));

        $response->assertOk();
        $response->assertSee('Review Employee Registration');
        $response->assertSee('Pending Staff Member');
        $response->assertSee('Ashirwad Shop');
        $response->assertSee('Category / Designation *');
        $response->assertSee('Salary Type *');
        $response->assertSee('id="approval-form"', false);
        $response->assertSee('id="reject-form"', false);
        $response->assertSee('form="approval-form"', false);
        $response->assertSee('form="reject-form"', false);
        $response->assertSee('openEmployeeImagePreview', false);
        $response->assertSee('id="employee-image-preview-modal"', false);
        $response->assertSee('id="lightbox-close-btn"', false);
    }

    public function test_shop_owner_cannot_access_hr_approvals(): void
    {
        $response = $this->actingAs($this->shopOwner)
            ->get(route('admin.staff.approvals.index'));

        $this->assertTrue(in_array($response->getStatusCode(), [302, 403], true));
    }

    public function test_admin_must_select_category_and_salary_to_approve_pending_employee(): void
    {
        $pendingEmployee = Employee::create([
            'employee_code' => 'EMP-00102',
            'name' => 'Anand Kumar',
            'phone' => '9998887771',
            'default_shop_id' => $this->shop->id,
            'employee_category_id' => null,
            'staff_area' => 'shop',
            'employment_status' => 'active',
            'verification_status' => 'pending',
            'submitted_by' => $this->shopOwner->id,
            'joined_on' => '2026-09-01',
            'id_type' => 'aadhaar',
            'id_number' => '1111-2222-4444',
            'address' => 'Ernakulam',
        ]);

        // Failing approval attempt without category and salary
        $failedResponse = $this->actingAs($this->admin)
            ->post(route('admin.staff.approve', $pendingEmployee), []);

        $failedResponse->assertSessionHasErrors(['employee_category_id', 'salary_type']);

        // Successful approval attempt with category and monthly salary
        $successResponse = $this->actingAs($this->admin)
            ->post(route('admin.staff.approve', $pendingEmployee), [
                'employee_category_id' => $this->shopCategory->id,
                'salary_type' => 'monthly',
                'monthly_salary' => 18000,
            ]);

        $successResponse->assertRedirect(route('admin.staff.approvals.index'));
        $successResponse->assertSessionHas('success', 'Employee approved successfully.');

        $this->assertDatabaseHas('employees', [
            'id' => $pendingEmployee->id,
            'employee_category_id' => $this->shopCategory->id,
            'salary_type' => 'monthly',
            'monthly_salary' => 18000.00,
            'daily_wage' => null,
            'verification_status' => 'approved',
            'employment_status' => 'active',
            'reviewed_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_approve_with_daily_wage(): void
    {
        $pendingEmployee = Employee::create([
            'employee_code' => 'EMP-00105',
            'name' => 'Manoj Kumar',
            'phone' => '9998887774',
            'default_shop_id' => $this->shop->id,
            'employee_category_id' => null,
            'staff_area' => 'shop',
            'employment_status' => 'active',
            'verification_status' => 'pending',
            'submitted_by' => $this->shopOwner->id,
            'joined_on' => '2026-09-01',
            'id_type' => 'aadhaar',
            'id_number' => '1111-2222-7777',
            'address' => 'Kottayam',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.staff.approve', $pendingEmployee), [
                'employee_category_id' => $this->shopCategory->id,
                'salary_type' => 'daily_wage',
                'daily_wage' => 750,
            ]);

        $response->assertRedirect(route('admin.staff.approvals.index'));

        $this->assertDatabaseHas('employees', [
            'id' => $pendingEmployee->id,
            'employee_category_id' => $this->shopCategory->id,
            'salary_type' => 'daily_wage',
            'daily_wage' => 750.00,
            'monthly_salary' => null,
            'verification_status' => 'approved',
            'reviewed_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_reject_pending_employee_with_reason(): void
    {
        $pendingEmployee = Employee::create([
            'employee_code' => 'EMP-00103',
            'name' => 'Suresh Babu',
            'phone' => '9998887772',
            'default_shop_id' => $this->shop->id,
            'employee_category_id' => null,
            'staff_area' => 'shop',
            'employment_status' => 'active',
            'verification_status' => 'pending',
            'submitted_by' => $this->shopOwner->id,
            'joined_on' => '2026-09-01',
            'id_type' => 'aadhaar',
            'id_number' => '1111-2222-5555',
            'address' => 'Thrissur',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.staff.reject', $pendingEmployee), [
                'rejection_reason' => 'Government ID front photo is blurry.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('warning');

        $this->assertDatabaseHas('employees', [
            'id' => $pendingEmployee->id,
            'verification_status' => 'rejected',
            'reviewed_by' => $this->admin->id,
            'rejection_reason' => 'Government ID front photo is blurry.',
        ]);
    }

    public function test_rejected_employee_can_be_edited_and_resubmitted_by_shop_owner(): void
    {
        $rejectedEmployee = Employee::create([
            'employee_code' => 'EMP-00104',
            'name' => 'Vikas Nair',
            'phone' => '9998887773',
            'default_shop_id' => $this->shop->id,
            'employee_category_id' => null,
            'staff_area' => 'shop',
            'employment_status' => 'active',
            'verification_status' => 'rejected',
            'rejection_reason' => 'Fix ID image',
            'submitted_by' => $this->shopOwner->id,
            'joined_on' => '2026-09-01',
            'id_type' => 'aadhaar',
            'id_number' => '1111-2222-6666',
            'address' => 'Kozhikode',
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.employees.edit-submission', $rejectedEmployee));

        $response->assertOk();
        $response->assertSee('Edit Staff Submission');
        $response->assertSee('Fix ID image');

        $payload = [
            'name' => 'Vikas Nair Corrected',
            'phone' => '9998887773',
            'alternate_phone' => '9876543211',
            'joined_on' => '2026-09-01',
            'id_type' => 'aadhaar',
            'id_number' => '1111-2222-6666',
            'address' => 'Kozhikode',
            'photo_data_url' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'id_front_data_url' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        ];

        $resubmitResponse = $this->actingAs($this->shopOwner)
            ->put(route('shop-owner.staff.employees.resubmit', $rejectedEmployee), $payload);

        $resubmitResponse->assertRedirect(route('shop-owner.staff.index', ['shop' => $this->shop->code]));

        $this->assertDatabaseHas('employees', [
            'id' => $rejectedEmployee->id,
            'name' => 'Vikas Nair Corrected',
            'employee_category_id' => null,
            'salary_type' => null,
            'verification_status' => 'pending',
            'rejection_reason' => null,
        ]);
    }
}
