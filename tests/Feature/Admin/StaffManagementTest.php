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
        $response->assertSee('data-admin-dashboard-switcher', false);
        $response->assertSee('Accounting');
        $response->assertSee('Purchasing');
        $response->assertSee('Inventory');
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

    public function test_employee_directory_paginates_with_twenty_records_per_page(): void
    {
        foreach (range(1, 21) as $index) {
            Employee::factory()->create([
                'name' => sprintf('Paged Employee %02d', $index),
            ]);
        }

        $firstPage = $this->actingAs($this->admin)->get(route('admin.staff.employees.index'));
        $secondPage = $this->actingAs($this->admin)->get(route('admin.staff.employees.index', ['page' => 2]));

        $firstPage->assertOk();
        $firstPage->assertSee('Paged Employee 01');
        $firstPage->assertSee('Paged Employee 19');
        $firstPage->assertDontSee('Paged Employee 20');

        $secondPage->assertOk();
        $secondPage->assertSee('Paged Employee 20');
        $secondPage->assertSee('Paged Employee 21');
        $secondPage->assertDontSee('Paged Employee 01');
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
        $response->assertSee('SL No');
        $response->assertSee('Toggle Sidebar');
        $response->assertSee($officeCategory->name);
        $response->assertSee('What Re-Sync Linked Users does');
        $response->assertSee('Checks all login users and makes sure each one has a linked staff record.');
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
        $response->assertSee('Quick Board');
        $response->assertSee('Updated By');
        $response->assertSee('SL No');
        $response->assertSee('Only the key attendance fields are shown here');
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
        $staffResponse->assertDontSee('Admin Panel');

        $adminOverviewResponse = $this->actingAs($hrManager)->get(route('admin.overview'));
        $adminOverviewResponse->assertRedirect(route('admin.staff.index', ['date' => today()->toDateString()]));
    }

    public function test_attendance_only_staff_user_is_redirected_to_attendance_and_sees_only_allowed_links(): void
    {
        $attendanceUser = User::factory()->create();
        $attendanceUser->givePermissionTo('hr.attendance.view');

        $dashboardResponse = $this->actingAs($attendanceUser)->get(route('dashboard'));
        $dashboardResponse->assertRedirect(route('admin.staff.attendance', ['date' => today()->toDateString()]));

        $staffIndexResponse = $this->actingAs($attendanceUser)->get(route('admin.staff.index'));
        $staffIndexResponse->assertRedirect(route('admin.staff.attendance', ['date' => today()->toDateString()]));

        $attendanceResponse = $this->actingAs($attendanceUser)->get(route('admin.staff.attendance', ['date' => today()->toDateString()]));
        $attendanceResponse->assertOk();
        $attendanceResponse->assertSee(route('admin.staff.attendance', ['date' => today()->toDateString()]), false);
        $attendanceResponse->assertDontSee(route('admin.staff.employees.index', ['date' => today()->toDateString()]), false);
        $attendanceResponse->assertDontSee(route('admin.staff.leaves.index'), false);
        $attendanceResponse->assertDontSee(route('admin.staff.payroll.index'), false);
        $attendanceResponse->assertDontSee('Admin Panel');
    }

    public function test_admin_root_stays_on_admin_overview_for_admin_user(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.overview'));

        $response->assertOk();
        $response->assertSee('Admin Control Center');
        $response->assertSee('Control operations, cash flow, and user access from one place.');
    }

    public function test_deactivate_action_is_hidden_on_employee_list_and_only_visible_to_admin_on_profile(): void
    {
        $employee = Employee::factory()->create([
            'name' => 'Status Controlled Staff',
        ]);

        $listResponse = $this->actingAs($this->admin)->get(route('admin.staff.employees.index'));
        $listResponse->assertOk();
        $listResponse->assertDontSee('Deactivate');
        $listResponse->assertDontSee('Reactivate');

        $adminProfileResponse = $this->actingAs($this->admin)->get(route('admin.staff.show', $employee));
        $adminProfileResponse->assertOk();
        $adminProfileResponse->assertSee('Deactivate Staff');

        $hrManager = User::factory()->create();
        $hrManager->assignRole('hr_manager');

        $hrProfileResponse = $this->actingAs($hrManager)->get(route('admin.staff.show', $employee));
        $hrProfileResponse->assertOk();
        $hrProfileResponse->assertDontSee('Deactivate Staff');
        $hrProfileResponse->assertDontSee('Reactivate Staff');
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

        $response->assertRedirect(route('admin.staff.payroll.index', ['payroll_month' => '2026-07']));

        $payrollRun = PayrollRun::query()->firstOrFail();
        $item = $payrollRun->items()->where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame('draft', $payrollRun->status);
        $this->assertEquals(1.0, (float) $item->present_days);
        $this->assertEquals(1.0, (float) $item->half_days);
        $this->assertEquals(1.0, (float) $item->paid_leave_days);
        $this->assertEquals(1.0, (float) $item->unpaid_leave_days);
        $this->assertEquals(27.0, (float) $item->absent_days);
        $this->assertNull($payrollRun->journal_entry_id);
        $this->assertSame(1, $item->rule_snapshot['monthly_paid_leave_limit']);
        $this->assertSame(1.0, (float) $item->rule_snapshot['present_day_weight']);
    }

    public function test_payroll_finalization_posts_salary_expense_after_draft_review(): void
    {
        $employee = Employee::factory()->create([
            'staff_area' => 'office',
            'monthly_salary' => 31000,
        ]);

        EmployeeAttendance::factory()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-07-01',
            'status' => 'present',
        ]);

        $this->actingAs($this->admin)->post(route('admin.staff.payroll.store'), [
            'payroll_month' => '2026-07',
        ]);

        $payrollRun = PayrollRun::query()->firstOrFail();

        $response = $this->actingAs($this->admin)->post(route('admin.staff.payroll.finalize', $payrollRun));

        $response->assertRedirect(route('admin.staff.payroll.index', ['payroll_month' => '2026-07']));

        $payrollRun->refresh();
        $journalEntry = JournalEntry::query()->find($payrollRun->journal_entry_id);

        $this->assertSame('finalized', $payrollRun->status);
        $this->assertNotNull($journalEntry);
        $this->assertSame('PAYROLL-20260701-20260731', $journalEntry->reference);
    }

    public function test_payroll_override_changes_final_amount_without_touching_computed_amount(): void
    {
        $employee = Employee::factory()->create([
            'staff_area' => 'office',
            'monthly_salary' => 30000,
        ]);

        EmployeeAttendance::factory()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-07-01',
            'status' => 'present',
        ]);

        $this->actingAs($this->admin)->post(route('admin.staff.payroll.store'), [
            'payroll_month' => '2026-07',
        ]);

        $payrollRun = PayrollRun::query()->firstOrFail();
        $item = $payrollRun->items()->where('employee_id', $employee->id)->firstOrFail();
        $originalComputedAmount = (float) $item->computed_amount;

        $response = $this->actingAs($this->admin)->patch(route('admin.staff.payroll.items.update', [$payrollRun, $item]), [
            'override_amount' => 9999.99,
        ]);

        $response->assertRedirect(route('admin.staff.payroll.index', ['payroll_month' => '2026-07']));

        $item->refresh();
        $payrollRun->refresh();

        $this->assertSame($originalComputedAmount, (float) $item->computed_amount);
        $this->assertEquals(9999.99, (float) $item->override_amount);
        $this->assertEquals(9999.99, (float) $item->final_amount);
        $this->assertEquals(9999.99, (float) $payrollRun->net_amount);
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

        $response->assertRedirect(route('admin.staff.payroll.index', ['payroll_month' => '2026-07']));

        $payrollRun = PayrollRun::query()->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.staff.payroll.finalize', $payrollRun));

        $this->assertDatabaseHas('accounts', ['code' => '1020', 'name' => 'Bank Account']);
        $this->assertDatabaseHas('accounts', ['code' => '5700', 'name' => 'Salaries Expense']);
        $this->assertNotNull(PayrollRun::query()->first()?->fresh()?->journal_entry_id);
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

        $response->assertRedirect(route('admin.staff.payroll.index', ['payroll_month' => '2026-06']));

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

    public function test_hr_can_submit_leave_request_for_office_staff(): void
    {
        $employee = Employee::factory()->create([
            'staff_area' => 'office',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.staff.leaves.store'), [
            'employee_id' => $employee->id,
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-11',
            'reason' => 'Office leave request',
        ]);

        $response->assertRedirect(route('admin.staff.leaves.index'));
        $this->assertDatabaseHas('employee_leave_requests', [
            'employee_id' => $employee->id,
            'submission_type' => 'admin',
            'status' => 'pending',
        ]);
    }

    public function test_leave_page_uses_tabs_and_employee_driven_context(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.staff.leaves.index', [
            'tab' => 'submit',
        ]));

        $response->assertOk();
        $response->assertSee('Submit Leave');
        $response->assertSee('Leave Queue');
        $response->assertSee('Select employee to load leave context.');
        $response->assertSee('Leave context updates automatically from the selected staff profile.');
        $response->assertSee('data-enhanced-select', false);
        $response->assertDontSee('Office / no shop');
    }

    public function test_sync_linked_users_maps_hr_manager_to_office_category_and_preserves_custom_salary(): void
    {
        $user = User::factory()->create();
        $user->assignRole('hr_manager');

        $employee = $user->employee()->firstOrFail();
        $employee->update([
            'monthly_salary' => 54321,
            'employee_category_id' => EmployeeCategory::query()->where('code', 'other-shop')->firstOrFail()->id,
            'staff_area' => 'shop',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.staff.sync-users'));

        $response->assertRedirect(route('admin.staff.employees.index'));

        $employee->refresh();

        $this->assertSame('office', $employee->staff_area);
        $this->assertSame('office', $employee->category->code);
        $this->assertEquals(54321.0, (float) $employee->monthly_salary);
    }

    public function test_inactive_staff_are_excluded_from_payroll_generation(): void
    {
        $inactiveEmployee = Employee::factory()->create([
            'employment_status' => 'inactive',
        ]);

        $response = $this->actingAs($this->admin)->patch(route('admin.staff.employment-status.update', $inactiveEmployee), [
            'employment_status' => 'inactive',
        ]);

        $response->assertRedirect(route('admin.staff.show', $inactiveEmployee));

        $this->actingAs($this->admin)->post(route('admin.staff.payroll.store'), [
            'payroll_month' => '2026-07',
        ]);

        $payrollRun = PayrollRun::query()->firstOrFail();

        $this->assertFalse($payrollRun->items()->where('employee_id', $inactiveEmployee->id)->exists());
    }
}
