<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\EmployeeLeaveRequest;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\Shop;
use App\Models\ShopEmployeeAssignment;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\EmployeeCategorySeeder;
use Database\Seeders\JuneStaffAttendanceSeeder;
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

    public function test_staff_dashboard_shows_owned_shop_cards_with_date_scoped_employee_details(): void
    {
        $shopCategory = EmployeeCategory::query()->where('code', 'other-shop')->firstOrFail();
        $ownedShop = Shop::factory()->create([
            'name' => 'Ashirwad Owned Shop',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $otherShop = Shop::factory()->create([
            'name' => 'External Shop',
            'accounting_enabled' => false,
            'accounting_mode' => 'franchise',
        ]);

        $visibleEmployee = Employee::factory()->create([
            'name' => 'Visible Shop Staff',
            'employee_category_id' => $shopCategory->id,
            'staff_area' => 'shop',
        ]);

        $hiddenEmployee = Employee::factory()->create([
            'name' => 'Hidden Different Date Staff',
            'employee_category_id' => $shopCategory->id,
            'staff_area' => 'shop',
        ]);

        EmployeeAttendance::factory()->create([
            'employee_id' => $visibleEmployee->id,
            'shop_id' => $ownedShop->id,
            'attendance_date' => '2026-07-03',
            'status' => 'present',
            'marked_by' => $this->admin->id,
        ]);

        EmployeeAttendance::factory()->create([
            'employee_id' => $hiddenEmployee->id,
            'shop_id' => $otherShop->id,
            'attendance_date' => '2026-07-02',
            'status' => 'present',
            'marked_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.staff.index', [
            'date' => '2026-07-03',
        ]));

        $response->assertOk();
        $response->assertSee('Dashboard Date');
        $response->assertSee('Owned Shop Coverage');
        $response->assertSee('Ashirwad Owned Shop');
        $response->assertSee('Visible Shop Staff');
        $response->assertDontSee('External Shop');
        $response->assertDontSee('Hidden Different Date Staff');
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
            'category' => $officeCategory->code,
        ]));

        $response->assertOk();
        $response->assertSee('Staff CRUD');
        $response->assertSee('All Categories');
        $response->assertSee($officeCategory->name);
    }

    public function test_employees_page_uses_category_code_in_filters_and_shows_linked_roles(): void
    {
        $category = EmployeeCategory::query()->where('code', 'other-shop')->firstOrFail();
        $shop = Shop::factory()->create(['name' => 'Owned Shop A']);
        $user = User::factory()->create();
        $user->assignRole('shop');
        $user->ownedShopAssignments()->create(['shop_id' => $shop->id]);

        $employee = $user->employee()->firstOrFail();
        $employee->update([
            'employee_category_id' => $category->id,
            'name' => 'Owner Staff',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.staff.employees.index', [
            'category' => $category->code,
        ]));

        $response->assertOk();
        $response->assertSee('Owner Staff');
        $response->assertSee('shop');
        $response->assertSee('Owned Shop A');
        $response->assertDontSee('employee_category_id=');
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
        $response->assertSee('Updated By');
        $response->assertSee('Visible Time');
        $response->assertSee('Attendance Filters');
        $response->assertSee(route('admin.staff.show', $employee));
        $response->assertSee('Search employee name, code, phone');
    }

    public function test_attendance_board_filters_by_shop_area_and_category(): void
    {
        $shopCategory = EmployeeCategory::query()->where('code', 'other-shop')->firstOrFail();
        $officeCategory = EmployeeCategory::query()->where('code', 'office')->firstOrFail();
        $shop = Shop::factory()->create([
            'name' => 'Filter Shop',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        $shopEmployee = Employee::factory()->create([
            'name' => 'Shop Filter Staff',
            'employee_category_id' => $shopCategory->id,
            'staff_area' => 'shop',
            'default_shop_id' => $shop->id,
        ]);

        $officeEmployee = Employee::factory()->create([
            'name' => 'Office Filter Staff',
            'employee_category_id' => $officeCategory->id,
            'staff_area' => 'office',
        ]);

        EmployeeAttendance::factory()->create([
            'employee_id' => $shopEmployee->id,
            'shop_id' => $shop->id,
            'attendance_date' => '2026-07-03',
            'status' => 'present',
        ]);

        EmployeeAttendance::factory()->create([
            'employee_id' => $officeEmployee->id,
            'attendance_date' => '2026-07-03',
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.staff.attendance', [
            'date' => '2026-07-03',
            'shop_id' => $shop->id,
            'staff_area' => 'shop',
            'category' => $shopCategory->code,
        ]));

        $response->assertOk();
        $response->assertSee('Shop Filter Staff');
        $response->assertDontSee('Office Filter Staff');
        $response->assertSee('Filter Shop');
    }

    public function test_staff_attendance_filters_only_show_owned_shops(): void
    {
        Shop::factory()->create([
            'name' => 'Owned HR Shop',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);

        Shop::factory()->create([
            'name' => 'External Distribution Shop',
            'accounting_enabled' => false,
            'accounting_mode' => 'franchise',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.staff.attendance', [
            'date' => '2026-07-03',
        ]));

        $response->assertOk();
        $response->assertSee('All Owned Shops and Office');
        $response->assertSee('Owned HR Shop');
        $response->assertDontSee('External Distribution Shop');
    }

    public function test_attendance_status_cards_work_as_click_filters(): void
    {
        $presentEmployee = Employee::factory()->create(['name' => 'Present Staff']);
        $absentEmployee = Employee::factory()->create(['name' => 'Absent Staff']);

        EmployeeAttendance::factory()->create([
            'employee_id' => $presentEmployee->id,
            'attendance_date' => '2026-07-03',
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.staff.attendance', [
            'date' => '2026-07-03',
            'status' => 'present',
        ]));

        $response->assertOk();
        $response->assertSee('Present Staff');
        $response->assertDontSee('Absent Staff');
    }

    public function test_attendance_board_filters_by_employee_search(): void
    {
        $matchingEmployee = Employee::factory()->create([
            'name' => 'Search Match Staff',
            'employee_code' => 'SEARCH-001',
        ]);

        $otherEmployee = Employee::factory()->create([
            'name' => 'Other Attendance Staff',
            'employee_code' => 'OTHER-001',
        ]);

        EmployeeAttendance::factory()->create([
            'employee_id' => $matchingEmployee->id,
            'attendance_date' => '2026-07-03',
            'status' => 'present',
        ]);

        EmployeeAttendance::factory()->create([
            'employee_id' => $otherEmployee->id,
            'attendance_date' => '2026-07-03',
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.staff.attendance', [
            'date' => '2026-07-03',
            'search' => 'SEARCH-001',
        ]));

        $response->assertOk();
        $response->assertSee('Search Match Staff');
        $response->assertDontSee('Other Attendance Staff');
    }

    public function test_admin_can_view_categories_page_from_staff_section(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.staff.categories.index'));

        $response->assertOk();
        $response->assertSee('Category Rules');
        $response->assertSee('Add Payroll Category');
        $response->assertSee('Rule Directory');
        $response->assertSee('Paid leaves allowed per month');
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
        $response->assertSee($employee->employee_code);
        $response->assertDontSee('/admin/staff/'.$employee->id);
    }

    public function test_staff_profile_shows_linked_roles_and_leave_history(): void
    {
        $shop = Shop::factory()->create(['name' => 'Scope Shop']);
        $user = User::factory()->create();
        $user->assignRole('shop');
        $user->ownedShopAssignments()->create(['shop_id' => $shop->id]);

        $employee = $user->employee()->firstOrFail();
        $employee->update([
            'name' => 'Role Linked Staff',
        ]);

        EmployeeLeaveRequest::factory()->create([
            'employee_id' => $employee->id,
            'submitted_by' => $user->id,
            'submitted_for_shop_id' => $shop->id,
            'submission_type' => 'owner',
            'reason' => 'Sick leave',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.staff.show', $employee));

        $response->assertOk();
        $response->assertSee('Linked User Access');
        $response->assertSee('Owned Shops');
        $response->assertSee('Leave Request History');
        $response->assertSee('Scope Shop');
    }

    public function test_staff_profile_shows_quick_list_shops_and_worked_shops(): void
    {
        $employee = Employee::factory()->create([
            'staff_area' => 'shop',
            'name' => 'Roaming Staff',
        ]);
        $assignedShop = Shop::factory()->create(['name' => 'Assigned Shop']);
        $workedShop = Shop::factory()->create(['name' => 'Worked Shop']);

        ShopEmployeeAssignment::query()->create([
            'shop_id' => $assignedShop->id,
            'employee_id' => $employee->id,
            'assigned_by' => $this->admin->id,
        ]);

        EmployeeAttendance::factory()->create([
            'employee_id' => $employee->id,
            'shop_id' => $workedShop->id,
            'attendance_date' => '2026-07-03',
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.staff.show', $employee));

        $response->assertOk();
        $response->assertSee('Shop Coverage');
        $response->assertSee('Assigned Shop');
        $response->assertSee('Worked Shop');
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
            'payroll_month' => '2026-07',
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
        $this->assertSame('PAYROLL-20260701-20260731', $journalEntry->reference);
        $this->assertSame(1, $item->rule_snapshot['monthly_paid_leave_limit']);
        $this->assertSame(1.0, (float) $item->rule_snapshot['present_day_weight']);
    }

    public function test_payroll_generation_recreates_required_accounts_when_chart_rows_are_missing(): void
    {
        $category = EmployeeCategory::query()->where('code', 'office')->firstOrFail();
        $employee = Employee::factory()->create([
            'employee_category_id' => $category->id,
            'staff_area' => 'office',
            'monthly_salary' => 18000,
        ]);

        EmployeeAttendance::factory()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-07-01',
            'status' => 'present',
        ]);

        Account::query()->whereIn('code', ['1020', '5700'])->delete();

        $response = $this->actingAs($this->admin)->post(route('admin.staff.payroll.store'), [
            'payroll_month' => '2026-07',
        ]);

        $response->assertRedirect(route('admin.staff.payroll.index'));
        $this->assertDatabaseHas('accounts', ['code' => '1020', 'name' => 'Bank Account']);
        $this->assertDatabaseHas('accounts', ['code' => '5700', 'name' => 'Salaries Expense']);
        $this->assertNotNull(PayrollRun::query()->first()?->journal_entry_id);
    }

    public function test_june_demo_seeder_creates_direct_board_leave_case_for_payroll_review(): void
    {
        $this->seed([
            DemoUserSeeder::class,
            JuneStaffAttendanceSeeder::class,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.staff.payroll.store'), [
            'payroll_month' => '2026-06',
        ]);

        $response->assertRedirect(route('admin.staff.payroll.index'));

        $payrollRun = PayrollRun::query()
            ->whereDate('period_start', '2026-06-01')
            ->whereDate('period_end', '2026-06-30')
            ->firstOrFail();

        $directBoardItem = $payrollRun->items()
            ->whereHas('employee', fn ($query) => $query->where('employee_code', 'DEMO-DB-001'))
            ->firstOrFail();

        $this->assertEquals(20.0, (float) $directBoardItem->present_days);
        $this->assertEquals(6.0, (float) $directBoardItem->paid_leave_days);
        $this->assertEquals(4.0, (float) $directBoardItem->unpaid_leave_days);
        $this->assertEquals(26.0, (float) $directBoardItem->payable_units);
    }
}
