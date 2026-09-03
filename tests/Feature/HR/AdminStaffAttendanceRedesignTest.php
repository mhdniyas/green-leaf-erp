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

class AdminStaffAttendanceRedesignTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shop $shop1;

    private EmployeeCategory $shopCategory;

    private Employee $approvedStaff;

    private Employee $pendingStaff;

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

        $this->shopCategory = EmployeeCategory::query()->create([
            'name' => 'Shop Staff Category',
            'code' => 'SHOP-CAT',
            'staff_area' => 'shop',
            'is_active' => true,
        ]);

        $this->approvedStaff = Employee::query()->create([
            'employee_code' => 'EMP-APP-001',
            'name' => 'Ahmed Approved',
            'phone' => '9876543210',
            'alternate_phone' => '9123456789',
            'employee_category_id' => $this->shopCategory->id,
            'default_shop_id' => $this->shop1->id,
            'id_type' => 'aadhaar',
            'id_number' => '111122223333',
            'address' => 'Kochi',
            'staff_area' => 'shop',
            'verification_status' => 'approved',
            'employment_status' => 'active',
            'salary_type' => 'monthly',
            'monthly_salary' => 25000,
            'joined_on' => '2026-09-01',
        ]);

        $this->pendingStaff = Employee::query()->create([
            'employee_code' => 'EMP-PEND-002',
            'name' => 'Fasil Pending',
            'phone' => '9876543211',
            'alternate_phone' => '9123456780',
            'employee_category_id' => $this->shopCategory->id,
            'default_shop_id' => $this->shop1->id,
            'id_type' => 'aadhaar',
            'id_number' => '444455556666',
            'address' => 'Kochi',
            'staff_area' => 'shop',
            'verification_status' => 'pending',
            'employment_status' => 'active',
            'salary_type' => 'monthly',
            'monthly_salary' => 25000,
            'joined_on' => '2026-09-01',
        ]);
    }

    public function test_attendance_page_lists_approved_active_employees_and_shows_unmarked(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.staff.attendance', ['date' => '2026-09-03']));

        $response->assertOk();
        $response->assertSee('Staff Attendance');
        $response->assertSee('Ahmed Approved');
        $response->assertSee('Not Marked');
        $this->assertTrue($response->viewData('employees')->pluck('id')->contains($this->approvedStaff->id));
        $this->assertFalse($response->viewData('employees')->pluck('id')->contains($this->pendingStaff->id));
    }

    public function test_hr_can_mark_present_attendance(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 3, 9, 15, 0, 'Asia/Kolkata'));

        $response = $this->actingAs($this->admin)
            ->post(route('admin.staff.attendance.store'), [
                'employee_id' => $this->approvedStaff->id,
                'attendance_date' => '2026-09-03',
                'status' => 'present',
                'shop_id' => $this->shop1->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employee_attendances', [
            'employee_id' => $this->approvedStaff->id,
            'attendance_date' => '2026-09-03 00:00:00',
            'status' => 'present',
            'shop_id' => $this->shop1->id,
            'source' => 'admin',
        ]);
    }

    public function test_hr_must_provide_reason_when_marking_half_day_leave_or_absent(): void
    {
        // Half Day without reason should fail validation
        $responseHalfNoReason = $this->actingAs($this->admin)
            ->post(route('admin.staff.attendance.store'), [
                'employee_id' => $this->approvedStaff->id,
                'attendance_date' => '2026-09-03',
                'status' => 'half_day',
            ]);

        $responseHalfNoReason->assertSessionHasErrors('notes');

        // Half Day with reason passes
        $responseHalfWithReason = $this->actingAs($this->admin)
            ->post(route('admin.staff.attendance.store'), [
                'employee_id' => $this->approvedStaff->id,
                'attendance_date' => '2026-09-03',
                'status' => 'half_day',
                'notes' => 'Personal work in morning',
            ]);

        $responseHalfWithReason->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employee_attendances', [
            'employee_id' => $this->approvedStaff->id,
            'status' => 'half_day',
            'notes' => 'Personal work in morning',
        ]);
    }

    public function test_hr_can_edit_existing_attendance(): void
    {
        EmployeeAttendance::query()->create([
            'employee_id' => $this->approvedStaff->id,
            'shop_id' => $this->shop1->id,
            'attendance_date' => '2026-09-03',
            'status' => 'present',
            'source' => 'shop_owner',
            'marked_at' => now(),
            'marked_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.staff.attendance.store'), [
                'employee_id' => $this->approvedStaff->id,
                'attendance_date' => '2026-09-03',
                'status' => 'leave',
                'notes' => 'Medical leave correction by HR',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employee_attendances', [
            'employee_id' => $this->approvedStaff->id,
            'status' => 'leave',
            'notes' => 'Medical leave correction by HR',
        ]);

        $this->assertDatabaseHas('hr_overrides', [
            'override_type' => 'attendance',
            'employee_id' => $this->approvedStaff->id,
            'overridden_by' => $this->admin->id,
        ]);
    }

    public function test_assigned_shop_and_attendance_details_modal_attributes_render(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 3, 9, 18, 0, 'Asia/Kolkata'));

        ShopEmployeeAssignment::query()->create([
            'employee_id' => $this->approvedStaff->id,
            'shop_id' => $this->shop1->id,
            'effective_from' => '2026-09-01',
            'effective_to' => null,
            'status' => 'active',
            'assigned_by' => $this->admin->id,
        ]);

        EmployeeAttendance::query()->create([
            'employee_id' => $this->approvedStaff->id,
            'shop_id' => $this->shop1->id,
            'attendance_date' => '2026-09-03',
            'status' => 'present',
            'source' => 'admin',
            'marked_at' => now(),
            'marked_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.staff.attendance', ['date' => '2026-09-03']));

        $response->assertOk();
        $response->assertSee('Ahmed Approved');
        $response->assertSee('Grandcity Shop');
        $response->assertSee('js-open-details-modal');
        $response->assertSee('js-open-attendance-modal');
    }

    public function test_shop_wise_grouped_dashboard_renders_shop_cards_and_badges(): void
    {
        $shop2 = Shop::query()->create([
            'name' => 'Casio Shop',
            'code' => 'CASIO',
            'warehouse_tag' => 'CS',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $casioStaff = Employee::query()->create([
            'employee_code' => 'EMP-CS-003',
            'name' => 'Salim Casio',
            'phone' => '9876543212',
            'employee_category_id' => $this->shopCategory->id,
            'default_shop_id' => $shop2->id,
            'id_type' => 'aadhaar',
            'id_number' => '777788889999',
            'address' => 'Kochi',
            'staff_area' => 'shop',
            'verification_status' => 'approved',
            'employment_status' => 'active',
            'salary_type' => 'monthly',
            'monthly_salary' => 25000,
            'joined_on' => '2026-09-01',
        ]);

        $unallocatedStaff = Employee::query()->create([
            'employee_code' => 'EMP-UN-004',
            'name' => 'Niyas Unallocated',
            'phone' => '9876543213',
            'employee_category_id' => $this->shopCategory->id,
            'default_shop_id' => null,
            'id_type' => 'aadhaar',
            'id_number' => '111155559999',
            'address' => 'Kochi',
            'staff_area' => 'shop',
            'verification_status' => 'approved',
            'employment_status' => 'active',
            'salary_type' => 'monthly',
            'monthly_salary' => 25000,
            'joined_on' => '2026-09-01',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.staff.attendance', ['date' => '2026-09-03']));

        $response->assertOk();
        $response->assertSee('Grandcity Shop');
        $response->assertSee('Casio Shop');
        $response->assertSee('UNALLOCATED STAFF');
        $response->assertSee('Salim Casio');
        $response->assertSee('Niyas Unallocated');

        $shopGroups = $response->viewData('shopGroups');
        $this->assertNotNull($shopGroups);
        $this->assertTrue($shopGroups->has('shop_'.$this->shop1->id));
        $this->assertTrue($shopGroups->has('shop_'.$shop2->id));
        $this->assertTrue($shopGroups->has('unallocated'));
    }
}
