<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\Shop;
use App\Models\ShopEmployeeAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMonthlyAttendanceSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $shopOwner;

    private Shop $shop1;

    private Shop $shop2;

    private EmployeeCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->shopOwner = User::factory()->create(['name' => 'Shop Owner User']);
        $this->shopOwner->assignRole('shop');

        $this->shop1 = Shop::create([
            'name' => 'Kochi Central Shop',
            'code' => 'KOCHI_CENTRAL',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $this->shop2 = Shop::create([
            'name' => 'Calicut Coastal Shop',
            'code' => 'CALICUT_COASTAL',
            'status' => 'active',
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'is_active' => true,
        ]);

        $this->category = EmployeeCategory::create([
            'name' => 'Shop Sales Staff',
            'code' => 'shop_sales_staff',
            'staff_area' => 'shop',
            'is_active' => true,
            'default_hourly_rate' => 100,
            'default_monthly_rate' => 20000,
            'rate_type' => 'monthly',
        ]);
    }

    public function test_admin_can_access_monthly_summary_on_staff_index(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.staff.index', ['tab' => 'monthly-summary', 'month' => '2026-10']));

        $response->assertOk();
        $response->assertSee('October 2026 Attendance Summary');
        $response->assertSee('All Shops Aggregated');
        $response->assertSee('Total Employees');
        $response->assertSee('Total Present');
        $response->assertSee('Total Absent');
        $response->assertSee('Total Leave');
        $response->assertSee('Other Attendance');
    }

    public function test_admin_can_access_monthly_summary_on_staff_attendance_page(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.staff.attendance', ['tab' => 'monthly-summary', 'month' => '2026-10']));

        $response->assertOk();
        $response->assertSee('October 2026 Attendance Summary');
        $response->assertSee('All Shops Aggregated');
    }

    public function test_all_shops_summary_displays_shop_wise_table_and_aggregates(): void
    {
        $emp1 = Employee::create([
            'employee_code' => 'EMP_KOC_01',
            'default_shop_id' => $this->shop1->id,
            'employee_category_id' => $this->category->id,
            'name' => 'Anand Kumar',
            'phone' => '9876500001',
            'employment_status' => 'active',
            'verification_status' => 'approved',
            'staff_area' => 'shop',
        ]);

        $emp2 = Employee::create([
            'employee_code' => 'EMP_CAL_01',
            'default_shop_id' => $this->shop2->id,
            'employee_category_id' => $this->category->id,
            'name' => 'Bilal Khan',
            'phone' => '9876500002',
            'employment_status' => 'active',
            'verification_status' => 'approved',
            'staff_area' => 'shop',
        ]);

        // Attendances for Anand in Shop 1 (October 2026)
        EmployeeAttendance::create([
            'employee_id' => $emp1->id,
            'shop_id' => $this->shop1->id,
            'attendance_date' => '2026-10-01',
            'status' => 'present',
            'source' => 'shop_owner',
            'marked_by_user_id' => $this->admin->id,
        ]);
        EmployeeAttendance::create([
            'employee_id' => $emp1->id,
            'shop_id' => $this->shop1->id,
            'attendance_date' => '2026-10-02',
            'status' => 'absent',
            'source' => 'shop_owner',
            'marked_by_user_id' => $this->admin->id,
        ]);
        EmployeeAttendance::create([
            'employee_id' => $emp1->id,
            'shop_id' => $this->shop1->id,
            'attendance_date' => '2026-10-03',
            'status' => 'half_day',
            'source' => 'shop_owner',
            'marked_by_user_id' => $this->admin->id,
        ]);

        // Attendances for Bilal in Shop 2 (October 2026)
        EmployeeAttendance::create([
            'employee_id' => $emp2->id,
            'shop_id' => $this->shop2->id,
            'attendance_date' => '2026-10-01',
            'status' => 'present',
            'source' => 'shop_owner',
            'marked_by_user_id' => $this->admin->id,
        ]);
        EmployeeAttendance::create([
            'employee_id' => $emp2->id,
            'shop_id' => $this->shop2->id,
            'attendance_date' => '2026-10-02',
            'status' => 'leave',
            'source' => 'shop_owner',
            'marked_by_user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.staff.index', ['tab' => 'monthly-summary', 'month' => '2026-10']));

        $response->assertOk();

        // Check Shop-wise Attendance Summary table
        $response->assertSee('Shop-wise Attendance Summary');
        $response->assertSee('Kochi Central Shop');
        $response->assertSee('Calicut Coastal Shop');
        $response->assertSee('View Shop');

        // Check Employee Attendance Table
        $response->assertSee('Anand Kumar');
        $response->assertSee('Bilal Khan');

        // Check Grand Totals
        // Present: 1 (Anand) + 1 (Bilal) = 2
        // Absent: 1 (Anand) + 0 (Bilal) = 1
        // Leave: 0 (Anand) + 1 (Bilal) = 1
        // Other: 1 (half_day)
        $summaryTotals = $response->viewData('monthlySummaryTotals');
        $this->assertEquals(2, $summaryTotals['total_employees']);
        $this->assertEquals(2, $summaryTotals['present']);
        $this->assertEquals(1, $summaryTotals['absent']);
        $this->assertEquals(1, $summaryTotals['leave']);
        $this->assertEquals(1, $summaryTotals['half_day']);
        $this->assertEquals(1, $summaryTotals['total_other_attendance']);
    }

    public function test_admin_can_filter_by_individual_shop(): void
    {
        $emp1 = Employee::create([
            'employee_code' => 'EMP_KOC_02',
            'default_shop_id' => $this->shop1->id,
            'employee_category_id' => $this->category->id,
            'name' => 'Chitra Nair',
            'phone' => '9876500003',
            'employment_status' => 'active',
            'verification_status' => 'approved',
            'staff_area' => 'shop',
        ]);

        $emp2 = Employee::create([
            'employee_code' => 'EMP_CAL_02',
            'default_shop_id' => $this->shop2->id,
            'employee_category_id' => $this->category->id,
            'name' => 'Deepak Varma',
            'phone' => '9876500004',
            'employment_status' => 'active',
            'verification_status' => 'approved',
            'staff_area' => 'shop',
        ]);

        // Attendance for Chitra in Shop 1
        EmployeeAttendance::create([
            'employee_id' => $emp1->id,
            'shop_id' => $this->shop1->id,
            'attendance_date' => '2026-10-05',
            'status' => 'present',
            'source' => 'shop_owner',
            'marked_by_user_id' => $this->admin->id,
        ]);

        // Filter by Shop 1 only
        $response = $this->actingAs($this->admin)
            ->get(route('admin.staff.index', [
                'tab' => 'monthly-summary',
                'shop_id' => $this->shop1->id,
                'month' => '2026-10',
            ]));

        $response->assertOk();
        $response->assertSee('Chitra Nair');
        $response->assertDontSee('Deepak Varma');

        $summaryTotals = $response->viewData('monthlySummaryTotals');
        $this->assertEquals(1, $summaryTotals['total_employees']);
        $this->assertEquals(1, $summaryTotals['present']);
        $this->assertEquals(0, $summaryTotals['absent']);
    }

    public function test_no_double_counting_for_transferred_employees(): void
    {
        $emp = Employee::create([
            'employee_code' => 'EMP_TR_01',
            'default_shop_id' => $this->shop1->id,
            'employee_category_id' => $this->category->id,
            'name' => 'Farooq Abdullah',
            'phone' => '9876500005',
            'employment_status' => 'active',
            'verification_status' => 'approved',
            'staff_area' => 'shop',
        ]);

        // Worked in Shop 1 on Oct 1
        EmployeeAttendance::create([
            'employee_id' => $emp->id,
            'shop_id' => $this->shop1->id,
            'attendance_date' => '2026-10-01',
            'status' => 'present',
            'source' => 'shop_owner',
            'marked_by_user_id' => $this->admin->id,
        ]);

        // Transferred and worked in Shop 2 on Oct 15
        ShopEmployeeAssignment::create([
            'employee_id' => $emp->id,
            'shop_id' => $this->shop2->id,
            'effective_from' => '2026-10-15',
            'is_primary' => true,
        ]);

        EmployeeAttendance::create([
            'employee_id' => $emp->id,
            'shop_id' => $this->shop2->id,
            'attendance_date' => '2026-10-15',
            'status' => 'present',
            'source' => 'shop_owner',
            'marked_by_user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.staff.index', ['tab' => 'monthly-summary', 'month' => '2026-10']));

        $response->assertOk();

        // Cards aggregate across shops WITHOUT double-counting the employee in Total Employees
        $summaryTotals = $response->viewData('monthlySummaryTotals');
        $this->assertEquals(1, $summaryTotals['total_employees']);
        $this->assertEquals(2, $summaryTotals['present']);
    }

    public function test_months_with_no_attendance_records(): void
    {
        Employee::create([
            'employee_code' => 'EMP_EMPTY_01',
            'default_shop_id' => $this->shop1->id,
            'employee_category_id' => $this->category->id,
            'name' => 'Geetha Menon',
            'phone' => '9876500006',
            'employment_status' => 'active',
            'verification_status' => 'approved',
            'staff_area' => 'shop',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.staff.index', ['tab' => 'monthly-summary', 'month' => '2026-01']));

        $response->assertOk();
        $response->assertSee('Geetha Menon');

        $summaryTotals = $response->viewData('monthlySummaryTotals');
        $this->assertEquals(1, $summaryTotals['total_employees']);
        $this->assertEquals(0, $summaryTotals['present']);
        $this->assertEquals(0, $summaryTotals['absent']);
        $this->assertEquals(0, $summaryTotals['leave']);
        $this->assertEquals(0, $summaryTotals['half_day']);
    }

    public function test_existing_dashboard_and_attendance_pages_unaffected(): void
    {
        $dashResponse = $this->actingAs($this->admin)->get(route('admin.staff.index'));
        $dashResponse->assertOk();
        $dashResponse->assertSee('Staff Management');
        $dashResponse->assertSee('Monthly Summary'); // In tabs

        $attResponse = $this->actingAs($this->admin)->get(route('admin.staff.attendance'));
        $attResponse->assertOk();
        $attResponse->assertSee('Staff Attendance');
        $attResponse->assertSee('Monthly Summary'); // In navigation
    }
}
