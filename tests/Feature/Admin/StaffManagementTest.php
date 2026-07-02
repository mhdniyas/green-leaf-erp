<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\EmployeeLeaveRequest;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\EmployeeCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            EmployeeCategorySeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_admin_can_view_staff_dashboard_with_stats(): void
    {
        $category = EmployeeCategory::query()->where('code', 'office')->firstOrFail();
        Employee::factory()->count(2)->create([
            'employee_category_id' => $category->id,
            'staff_area' => 'office',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.staff.index'));

        $response->assertOk();
        $response->assertSee('Staff Management');
        $response->assertSee('Total Employees');
    }

    public function test_admin_can_search_employees_from_staff_dashboard(): void
    {
        Employee::factory()->create(['name' => 'Niyas Staff']);
        Employee::factory()->create(['name' => 'Another Person']);

        $response = $this->actingAs($this->admin)->get(route('admin.staff.employees.index', ['search' => 'Niyas']));

        $response->assertOk();
        $response->assertSee('Niyas Staff');
        $response->assertDontSee('Another Person');
    }

    public function test_admin_can_view_employee_crud_page_with_category_tabs(): void
    {
        $officeCategory = EmployeeCategory::query()->where('code', 'office')->firstOrFail();

        $response = $this->actingAs($this->admin)->get(route('admin.staff.employees.index', [
            'employee_category_id' => $officeCategory->id,
        ]));

        $response->assertOk();
        $response->assertSee('Staff CRUD');
        $response->assertSee('All Categories');
        $response->assertSee($officeCategory->name);
    }

    public function test_attendance_board_shows_visible_check_in_time_panel(): void
    {
        $employee = Employee::factory()->create();
        EmployeeAttendance::factory()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-07-02',
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.staff.attendance', [
            'date' => '2026-07-02',
        ]));

        $response->assertOk();
        $response->assertSee('Check-In Time');
        $response->assertSee('Marked By');
        $response->assertSee('Visible Time');
    }

    public function test_admin_can_view_categories_page_from_staff_section(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.staff.categories.index'));

        $response->assertOk();
        $response->assertSee('Category Rules');
        $response->assertSee('Add Payroll Category');
    }

    public function test_admin_can_view_staff_profile_with_attendance_calendar(): void
    {
        $employee = Employee::factory()->create(['name' => 'Calendar Staff']);
        EmployeeAttendance::factory()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-07-02',
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.staff.show', [
            'employee' => $employee,
            'month' => '2026-07',
        ]));

        $response->assertOk();
        $response->assertSee('Calendar Staff');
        $response->assertSee('Attendance Calendar');
        $response->assertSee('Update Attendance');
    }

    public function test_hr_manager_is_redirected_to_staff_dashboard_from_admin_entry(): void
    {
        $hrManager = User::factory()->create();
        $hrManager->assignRole('hr_manager');

        $staffResponse = $this->actingAs($hrManager)->get(route('admin.staff.index'));
        $staffResponse->assertOk();
        $staffResponse->assertSee('Staff Management');

        $adminOverviewResponse = $this->actingAs($hrManager)->get(route('admin.overview'));
        $adminOverviewResponse->assertRedirect(route('admin.staff.index'));
    }

    public function test_admin_can_create_employee_record(): void
    {
        $category = EmployeeCategory::query()->where('code', 'office')->firstOrFail();
        $shop = Shop::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('admin.staff.store'), [
            'name' => 'Office Clerk',
            'employee_code' => 'EMP-100',
            'employee_category_id' => $category->id,
            'staff_area' => 'office',
            'employment_status' => 'active',
            'default_shop_id' => $shop->id,
            'monthly_salary' => 25000,
        ]);

        $response->assertRedirect(route('admin.staff.employees.index'));
        $this->assertDatabaseHas('employees', [
            'name' => 'Office Clerk',
            'employee_code' => 'EMP-100',
            'staff_area' => 'office',
            'default_shop_id' => $shop->id,
        ]);
    }

    public function test_admin_can_create_payroll_category_rule(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.staff.categories.store'), [
            'name' => 'Contract Crew',
            'code' => 'contract-crew',
            'staff_area' => 'shop',
            'default_monthly_salary' => 15000,
            'monthly_paid_leave_limit' => 2,
            'present_day_weight' => 1,
            'half_day_weight' => 0.5,
            'paid_leave_weight' => 1,
            'excess_leave_weight' => 0,
            'absent_day_weight' => 0,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.staff.categories.index'));
        $this->assertDatabaseHas('employee_categories', [
            'code' => 'contract-crew',
            'monthly_paid_leave_limit' => 2,
        ]);
    }

    public function test_admin_can_backfill_attendance_for_any_date(): void
    {
        $employee = Employee::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('admin.staff.attendance.store'), [
            'employee_id' => $employee->id,
            'attendance_date' => '2026-06-01',
            'status' => 'present',
        ]);

        $response->assertRedirect(route('admin.staff.attendance', ['date' => '2026-06-01']));

        $attendance = EmployeeAttendance::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'present')
            ->first();

        $this->assertNotNull($attendance);
        $this->assertSame('2026-06-01', $attendance->attendance_date?->toDateString());
        $this->assertSame('admin', $attendance->source);
    }

    public function test_admin_can_review_leave_and_approved_leave_marks_attendance(): void
    {
        $employee = Employee::factory()->create();
        $leaveRequest = EmployeeLeaveRequest::factory()->create([
            'employee_id' => $employee->id,
            'start_date' => '2026-07-03',
            'end_date' => '2026-07-04',
        ]);

        $response = $this->actingAs($this->admin)->patch(route('admin.staff.leaves.review', $leaveRequest), [
            'status' => 'approved',
            'review_note' => 'Approved',
        ]);

        $response->assertRedirect(route('admin.staff.leaves.index'));
        $this->assertDatabaseHas('employee_leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'approved',
        ]);
        $this->assertTrue(EmployeeAttendance::query()->where('employee_id', $employee->id)->whereDate('attendance_date', '2026-07-03')->where('status', 'leave')->exists());
        $this->assertTrue(EmployeeAttendance::query()->where('employee_id', $employee->id)->whereDate('attendance_date', '2026-07-04')->where('status', 'leave')->exists());
    }

    public function test_payroll_generation_uses_leave_limits_and_posts_salary_expense(): void
    {
        $category = EmployeeCategory::query()->where('code', 'office')->firstOrFail();
        $category->update([
            'monthly_paid_leave_limit' => 1,
        ]);

        $employee = Employee::factory()->create([
            'employee_category_id' => $category->id,
            'staff_area' => 'office',
            'monthly_salary' => 30000,
        ]);

        EmployeeAttendance::factory()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-07-01',
            'status' => 'present',
        ]);
        EmployeeAttendance::factory()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-07-02',
            'status' => 'half_day',
        ]);
        EmployeeAttendance::factory()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-07-03',
            'status' => 'leave',
        ]);
        EmployeeAttendance::factory()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-07-04',
            'status' => 'leave',
        ]);
        EmployeeAttendance::factory()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-07-05',
            'status' => 'absent',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.staff.payroll.store'), [
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-05',
        ]);

        $response->assertRedirect(route('admin.staff.payroll.index'));

        $payrollRun = PayrollRun::query()->firstOrFail();
        $item = $payrollRun->items()->where('employee_id', $employee->id)->firstOrFail();
        $journalEntry = JournalEntry::query()->find($payrollRun->journal_entry_id);

        $this->assertSame('finalized', $payrollRun->status);
        $this->assertEquals(1.0, (float) $item->present_days);
        $this->assertEquals(1.0, (float) $item->half_days);
        $this->assertEquals(1.0, (float) $item->paid_leave_days);
        $this->assertEquals(1.0, (float) $item->unpaid_leave_days);
        $this->assertEquals(1.0, (float) $item->absent_days);
        $this->assertNotNull($journalEntry);
        $this->assertSame('PAYROLL-20260701-20260705', $journalEntry->reference);
        $this->assertSame(1, $item->rule_snapshot['monthly_paid_leave_limit']);
        $this->assertSame(1.0, (float) $item->rule_snapshot['present_day_weight']);
    }
}
