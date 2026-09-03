<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\Shop;
use App\Models\ShopEmployeeAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminStaffAssignmentsRedesignTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop1;

    private Shop $shop2;

    private EmployeeCategory $shopCategory;

    private EmployeeCategory $officeCategory;

    private Employee $allocatedStaff;

    private Employee $unallocatedStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(['admin', 'purchaser']);
        $this->admin->givePermissionTo('hr.employee.view');
        $this->admin->givePermissionTo('hr.employee.update');

        $this->shop1 = Shop::query()->create([
            'name' => 'Grandcity Shop',
            'code' => 'AV_GRANDCITY',
            'warehouse_tag' => 'GC',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $this->shop2 = Shop::query()->create([
            'name' => 'Casio Shop',
            'code' => 'CASIO',
            'warehouse_tag' => 'CS',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $this->shopCategory = EmployeeCategory::query()->create([
            'name' => 'Shop Sales Staff',
            'code' => 'SHOP-SALES',
            'staff_area' => 'shop',
            'is_active' => true,
        ]);

        $this->officeCategory = EmployeeCategory::query()->create([
            'name' => 'Office Staff',
            'code' => 'OFFICE-STAFF',
            'staff_area' => 'office',
            'is_active' => true,
        ]);

        $this->allocatedStaff = Employee::query()->create([
            'employee_code' => 'EMP-ALLOC-001',
            'name' => 'Ahmed Ali Allocated',
            'phone' => '9876543210',
            'alternate_phone' => '9123456789',
            'employee_category_id' => $this->shopCategory->id,
            'default_shop_id' => $this->shop1->id,
            'id_type' => 'aadhaar',
            'id_number' => '999988887777',
            'address' => 'Kochi',
            'staff_area' => 'shop',
            'verification_status' => 'approved',
            'employment_status' => 'active',
            'salary_type' => 'monthly',
            'monthly_salary' => 25000,
            'joined_on' => '2026-09-01',
        ]);

        $this->unallocatedStaff = Employee::query()->create([
            'employee_code' => 'EMP-UNALLOC-002',
            'name' => 'Fasil Unallocated',
            'phone' => '9876543211',
            'alternate_phone' => '9123456780',
            'employee_category_id' => $this->officeCategory->id,
            'default_shop_id' => null,
            'id_type' => 'aadhaar',
            'id_number' => '999988886666',
            'address' => 'Kochi',
            'staff_area' => 'office',
            'verification_status' => 'approved',
            'employment_status' => 'active',
            'salary_type' => 'monthly',
            'monthly_salary' => 30000,
            'joined_on' => '2026-09-01',
        ]);
    }

    public function test_existing_canonical_assignment_store_endpoint_works(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.staff.shop-assignments.store'), [
                'employee_id' => $this->unallocatedStaff->id,
                'shop_id' => $this->shop2->id,
                'effective_from' => '2026-09-03',
                'notes' => 'New shop placement',
            ]);

        $response->assertRedirect(route('admin.staff.assignments.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('shop_employee_assignments', [
            'employee_id' => $this->unallocatedStaff->id,
            'shop_id' => $this->shop2->id,
            'status' => 'active',
        ]);

        $this->unallocatedStaff->refresh();
        $this->assertEquals($this->shop2->id, $this->unallocatedStaff->default_shop_id);
    }

    public function test_assignments_index_renders_cashbook_ui_with_counts_tabs_and_filters(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.staff.assignments.index'));

        $response->assertOk();
        $response->assertSee('Staff Allocations');
        $response->assertSee('Total Staff');
        $response->assertSee('Allocated');
        $response->assertSee('Unallocated');
        $response->assertSee('Shop Sales Staff');
        $response->assertSee('Office Staff');
        $response->assertSee('Ahmed Ali Allocated');
        $response->assertSee('Fasil Unallocated');
        $response->assertSee('Manage Assignment');
        $response->assertSee('Assign');
    }

    public function test_assignment_modal_wiring_and_canonical_route_attributes(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.staff.assignments.index'));

        $response->assertOk();
        $response->assertSee('js-open-assignment-modal');
        $response->assertSee('data-employee-id="'.$this->unallocatedStaff->id.'"', false);
        $response->assertSee('data-employee-id="'.$this->allocatedStaff->id.'"', false);
        $response->assertSee('admin-assignment-modal');
        $response->assertSee(route('admin.staff.shop-assignments.store'));
        $response->assertSee('name="employee_id"', false);
        $response->assertSee('name="shop_id"', false);
        $response->assertSee('name="effective_from"', false);
    }

    public function test_allocation_filter_filters_allocated_and_unallocated_staff(): void
    {
        // Filter allocated
        $responseAllocated = $this->actingAs($this->admin)
            ->get(route('admin.staff.assignments.index', ['allocation' => 'allocated']));

        $responseAllocated->assertOk();
        $this->assertTrue($responseAllocated->viewData('employees')->pluck('id')->contains($this->allocatedStaff->id));
        $this->assertFalse($responseAllocated->viewData('employees')->pluck('id')->contains($this->unallocatedStaff->id));

        // Filter unallocated
        $responseUnallocated = $this->actingAs($this->admin)
            ->get(route('admin.staff.assignments.index', ['allocation' => 'unallocated']));

        $responseUnallocated->assertOk();
        $this->assertTrue($responseUnallocated->viewData('employees')->pluck('id')->contains($this->unallocatedStaff->id));
        $this->assertFalse($responseUnallocated->viewData('employees')->pluck('id')->contains($this->allocatedStaff->id));
    }

    public function test_category_tab_filters_by_employee_category(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.staff.assignments.index', ['category' => $this->officeCategory->code]));

        $response->assertOk();
        $this->assertTrue($response->viewData('employees')->pluck('id')->contains($this->unallocatedStaff->id));
        $this->assertFalse($response->viewData('employees')->pluck('id')->contains($this->allocatedStaff->id));
    }

    public function test_date_and_shop_filter_displays_staff_working_at_shop(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 3, 9, 15, 0, 'Asia/Kolkata'));

        EmployeeAttendance::query()->create([
            'employee_id' => $this->allocatedStaff->id,
            'shop_id' => $this->shop1->id,
            'attendance_date' => '2026-09-03',
            'status' => 'present',
            'source' => 'shop_owner',
            'marked_at' => now(),
            'marked_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.staff.assignments.index', [
                'shop_id' => $this->shop1->id,
                'date' => '2026-09-03',
            ]));

        $response->assertOk();
        $response->assertSee('Staff Working at Grandcity Shop on 03 Sep 2026');
        $response->assertSee('Ahmed Ali Allocated');
        $response->assertSee('✓ Present');
    }

    public function test_employee_detail_page_renders_header_calendar_and_day_details(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 3, 9, 18, 0, 'Asia/Kolkata'));

        ShopEmployeeAssignment::query()->create([
            'employee_id' => $this->allocatedStaff->id,
            'shop_id' => $this->shop1->id,
            'effective_from' => '2026-09-01',
            'effective_to' => null,
            'status' => 'active',
            'assigned_by' => $this->admin->id,
        ]);

        EmployeeAttendance::query()->create([
            'employee_id' => $this->allocatedStaff->id,
            'shop_id' => $this->shop1->id,
            'attendance_date' => '2026-09-03',
            'status' => 'present',
            'source' => 'shop_owner',
            'marked_at' => now(),
            'marked_by' => $this->admin->id,
            'notes' => 'On time check-in',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.staff.assignments.show', $this->allocatedStaff));

        $response->assertOk();
        $response->assertSee('Ahmed Ali Allocated');
        $response->assertSee('EMP-ALLOC-001');
        $response->assertSee('Grandcity Shop');
        $response->assertSee('Primary Phone');
        $response->assertSee('Emergency Contact');
        $response->assertSee('September 2026');
        $response->assertSee('Manage Assignment');
    }
}
