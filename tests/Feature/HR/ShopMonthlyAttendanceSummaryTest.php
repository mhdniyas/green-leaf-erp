<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\BusinessSetting;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\Shop;
use App\Models\ShopEmployeeAssignment;
use App\Models\ShopOwnerAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ShopMonthlyAttendanceSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $shopOwner;

    private Shop $shop;

    private Shop $otherShop;

    private Employee $employeeA;

    private Employee $employeeB;

    private EmployeeCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->shopOwner = User::factory()->create();
        $this->shopOwner->assignRole('shop');
        $this->shopOwner->givePermissionTo('hr.attendance.mark-owned-shop');

        $this->shop = Shop::query()->create([
            'name' => 'Grandcity Shop',
            'code' => 'AV_GRANDCITY',
            'warehouse_tag' => 'GC',
            'is_active' => true,
        ]);

        $this->otherShop = Shop::query()->create([
            'name' => 'Kochi Central Shop',
            'code' => 'AV_KOCHI',
            'warehouse_tag' => 'KC',
            'is_active' => true,
        ]);

        ShopOwnerAssignment::query()->create([
            'user_id' => $this->shopOwner->id,
            'shop_id' => $this->shop->id,
            'status' => 'active',
        ]);

        $this->category = EmployeeCategory::query()->create([
            'name' => 'Sales Staff',
            'code' => 'sales_staff',
            'staff_area' => 'shop',
            'is_active' => true,
        ]);

        $this->employeeA = Employee::query()->create([
            'employee_code' => 'EMP-00101',
            'name' => 'Amina Rashid',
            'phone' => '9876543210',
            'id_type' => 'aadhaar',
            'id_number' => '999988887701',
            'address' => 'Grandcity',
            'default_shop_id' => $this->shop->id,
            'employee_category_id' => $this->category->id,
            'staff_area' => 'shop',
            'verification_status' => 'approved',
            'employment_status' => 'active',
            'salary_type' => 'monthly',
            'monthly_salary' => 25000,
            'joined_on' => '2026-01-01',
        ]);

        $this->employeeB = Employee::query()->create([
            'employee_code' => 'EMP-00102',
            'name' => 'Bilal Hassan',
            'phone' => '9876543211',
            'id_type' => 'aadhaar',
            'id_number' => '999988887702',
            'address' => 'Grandcity',
            'default_shop_id' => $this->shop->id,
            'employee_category_id' => $this->category->id,
            'staff_area' => 'shop',
            'verification_status' => 'approved',
            'employment_status' => 'active',
            'salary_type' => 'monthly',
            'monthly_salary' => 22000,
            'joined_on' => '2026-01-01',
        ]);

        BusinessSetting::query()->updateOrCreate(
            ['key' => 'shop_attendance_cutoff_time'],
            ['value' => '10:00 AM']
        );
    }

    public function test_monthly_summary_tab_renders_with_authorized_shop_owner(): void
    {
        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', [
                'shop' => $this->shop->code,
                'tab' => 'monthly-summary',
                'month' => '2026-10',
            ]));

        $response->assertOk();
        $response->assertSee('October 2026 Attendance Summary');
        $response->assertSee('Total Employees');
        $response->assertSee('Total Present');
        $response->assertSee('Total Absent');
        $response->assertSee('Total Leave');
        $response->assertSee('Other Attendance');
        $response->assertSee('Employee Name');
        $response->assertSee('Amina Rashid');
        $response->assertSee('Bilal Hassan');
    }

    public function test_single_employee_with_different_attendance_statuses(): void
    {
        // 3 Present, 2 Absent, 1 Leave, 2 Half Day, 1 Holiday (Other)
        $dates = [
            ['2026-10-01', 'present'],
            ['2026-10-02', 'present'],
            ['2026-10-03', 'present'],
            ['2026-10-05', 'absent'],
            ['2026-10-06', 'absent'],
            ['2026-10-07', 'leave'],
            ['2026-10-08', 'half_day'],
            ['2026-10-09', 'half_day'],
            ['2026-10-10', 'holiday'],
        ];

        foreach ($dates as [$date, $status]) {
            EmployeeAttendance::query()->create([
                'employee_id' => $this->employeeA->id,
                'shop_id' => $this->shop->id,
                'attendance_date' => $date,
                'status' => $status,
                'source' => 'shop_owner',
                'marked_by' => $this->shopOwner->id,
                'marked_at' => Carbon::parse($date.' 09:00:00'),
            ]);
        }

        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', [
                'shop' => $this->shop->code,
                'tab' => 'monthly-summary',
                'month' => '2026-10',
                'search' => 'Amina',
            ]));

        $response->assertOk();
        $response->assertSee('Amina Rashid');

        /** @var Collection $rows */
        $rows = $response->viewData('monthlySummaryRows');
        $this->assertCount(1, $rows);

        $rowA = $rows->firstWhere('employee_id', $this->employeeA->id);
        $this->assertNotNull($rowA);
        $this->assertSame(3, $rowA['present']);
        $this->assertSame(2, $rowA['absent']);
        $this->assertSame(1, $rowA['leave']);
        $this->assertSame(2, $rowA['half_day']);
        $this->assertSame(1, $rowA['other']);
        $this->assertSame(9, $rowA['total_marked']);

        $totals = $response->viewData('monthlySummaryTotals');
        $this->assertSame(1, $totals['total_employees']);
        $this->assertSame(3, $totals['present']);
        $this->assertSame(2, $totals['absent']);
        $this->assertSame(1, $totals['leave']);
        $this->assertSame(2, $totals['half_day']);
        $this->assertSame(1, $totals['other']);
        $this->assertSame(3, $totals['total_other_attendance']); // 2 half_day + 1 other
    }

    public function test_multiple_employees_in_one_shop_and_overall_totals(): void
    {
        // Employee A: 4 Present, 1 Absent
        EmployeeAttendance::query()->create([
            'employee_id' => $this->employeeA->id,
            'shop_id' => $this->shop->id,
            'attendance_date' => '2026-10-01',
            'status' => 'present',
            'source' => 'shop_owner',
        ]);
        EmployeeAttendance::query()->create([
            'employee_id' => $this->employeeA->id,
            'shop_id' => $this->shop->id,
            'attendance_date' => '2026-10-02',
            'status' => 'present',
            'source' => 'shop_owner',
        ]);
        EmployeeAttendance::query()->create([
            'employee_id' => $this->employeeA->id,
            'shop_id' => $this->shop->id,
            'attendance_date' => '2026-10-03',
            'status' => 'present',
            'source' => 'shop_owner',
        ]);
        EmployeeAttendance::query()->create([
            'employee_id' => $this->employeeA->id,
            'shop_id' => $this->shop->id,
            'attendance_date' => '2026-10-04',
            'status' => 'present',
            'source' => 'shop_owner',
        ]);
        EmployeeAttendance::query()->create([
            'employee_id' => $this->employeeA->id,
            'shop_id' => $this->shop->id,
            'attendance_date' => '2026-10-05',
            'status' => 'absent',
            'source' => 'shop_owner',
        ]);

        // Employee B: 2 Present, 1 Leave, 1 Half Day
        EmployeeAttendance::query()->create([
            'employee_id' => $this->employeeB->id,
            'shop_id' => $this->shop->id,
            'attendance_date' => '2026-10-01',
            'status' => 'present',
            'source' => 'shop_owner',
        ]);
        EmployeeAttendance::query()->create([
            'employee_id' => $this->employeeB->id,
            'shop_id' => $this->shop->id,
            'attendance_date' => '2026-10-02',
            'status' => 'present',
            'source' => 'shop_owner',
        ]);
        EmployeeAttendance::query()->create([
            'employee_id' => $this->employeeB->id,
            'shop_id' => $this->shop->id,
            'attendance_date' => '2026-10-03',
            'status' => 'leave',
            'source' => 'shop_owner',
        ]);
        EmployeeAttendance::query()->create([
            'employee_id' => $this->employeeB->id,
            'shop_id' => $this->shop->id,
            'attendance_date' => '2026-10-04',
            'status' => 'half_day',
            'source' => 'shop_owner',
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', [
                'shop' => $this->shop->code,
                'tab' => 'monthly-summary',
                'month' => '2026-10',
            ]));

        $response->assertOk();

        $totals = $response->viewData('monthlySummaryTotals');
        $this->assertSame(2, $totals['total_employees']);
        $this->assertSame(6, $totals['present']); // 4 + 2
        $this->assertSame(1, $totals['absent']);  // 1 + 0
        $this->assertSame(1, $totals['leave']);   // 0 + 1
        $this->assertSame(1, $totals['half_day']); // 0 + 1
        $this->assertSame(0, $totals['other']);
        $this->assertSame(1, $totals['total_other_attendance']);
    }

    public function test_employees_across_different_shops_and_shop_authorization(): void
    {
        $otherShopEmployee = Employee::query()->create([
            'employee_code' => 'EMP-00999',
            'name' => 'Foreign Shop Worker',
            'phone' => '9876549999',
            'id_type' => 'aadhaar',
            'id_number' => '999988889999',
            'address' => 'Kochi',
            'default_shop_id' => $this->otherShop->id,
            'staff_area' => 'shop',
            'verification_status' => 'approved',
            'employment_status' => 'active',
            'salary_type' => 'monthly',
            'monthly_salary' => 20000,
        ]);

        EmployeeAttendance::query()->create([
            'employee_id' => $otherShopEmployee->id,
            'shop_id' => $this->otherShop->id,
            'attendance_date' => '2026-10-05',
            'status' => 'present',
            'source' => 'shop_owner',
        ]);

        // Accessing authorized shop
        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', [
                'shop' => $this->shop->code,
                'tab' => 'monthly-summary',
                'month' => '2026-10',
            ]));

        $response->assertOk();
        $response->assertSee('Amina Rashid');
        $response->assertDontSee('Foreign Shop Worker');
        $response->assertDontSee('EMP-00999');

        // Trying to access unauthorized shop is rejected by ActiveShopResolver with 403 Forbidden
        $unauthorizedResponse = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', [
                'shop' => $this->otherShop->code,
                'tab' => 'monthly-summary',
                'month' => '2026-10',
            ]));

        $unauthorizedResponse->assertForbidden();
    }

    public function test_current_month_and_previous_month_filtering(): void
    {
        // September record
        EmployeeAttendance::query()->create([
            'employee_id' => $this->employeeA->id,
            'shop_id' => $this->shop->id,
            'attendance_date' => '2026-09-15',
            'status' => 'present',
            'source' => 'shop_owner',
        ]);

        // October record
        EmployeeAttendance::query()->create([
            'employee_id' => $this->employeeA->id,
            'shop_id' => $this->shop->id,
            'attendance_date' => '2026-10-15',
            'status' => 'present',
            'source' => 'shop_owner',
        ]);

        // Query September with month format YYYY-MM
        $responseSept = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', [
                'shop' => $this->shop->code,
                'tab' => 'monthly-summary',
                'month' => '2026-09',
                'search' => 'Amina',
            ]));

        $responseSept->assertOk();
        $responseSept->assertSee('September 2026 Attendance Summary');
        $rowSept = $responseSept->viewData('monthlySummaryRows')->firstWhere('employee_id', $this->employeeA->id);
        $this->assertSame(1, $rowSept['present']);

        // Query October with separate month and year dropdown parameters
        $responseOct = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', [
                'shop' => $this->shop->code,
                'tab' => 'monthly-summary',
                'month' => '10',
                'year' => '2026',
                'search' => 'Amina',
            ]));

        $responseOct->assertOk();
        $responseOct->assertSee('October 2026 Attendance Summary');
        $rowOct = $responseOct->viewData('monthlySummaryRows')->firstWhere('employee_id', $this->employeeA->id);
        $this->assertSame(1, $rowOct['present']);
    }

    public function test_months_with_no_attendance_shows_zeros_not_empty(): void
    {
        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', [
                'shop' => $this->shop->code,
                'tab' => 'monthly-summary',
                'month' => '2025-05',
                'search' => 'Amina',
            ]));

        $response->assertOk();
        $response->assertSee('May 2025 Attendance Summary');

        $row = $response->viewData('monthlySummaryRows')->firstWhere('employee_id', $this->employeeA->id);
        $this->assertSame(0, $row['present']);
        $this->assertSame(0, $row['absent']);
        $this->assertSame(0, $row['leave']);
        $this->assertSame(0, $row['half_day']);
        $this->assertSame(0, $row['other']);
        $this->assertSame(0, $row['total_marked']);

        $totals = $response->viewData('monthlySummaryTotals');
        $this->assertSame(0, $totals['present']);
        $this->assertSame(0, $totals['absent']);
    }

    public function test_missing_dates_are_not_automatically_classified_as_absent(): void
    {
        // Employee has only 2 present records in October 2026 (31 days in month)
        EmployeeAttendance::query()->create([
            'employee_id' => $this->employeeA->id,
            'shop_id' => $this->shop->id,
            'attendance_date' => '2026-10-01',
            'status' => 'present',
            'source' => 'shop_owner',
        ]);
        EmployeeAttendance::query()->create([
            'employee_id' => $this->employeeA->id,
            'shop_id' => $this->shop->id,
            'attendance_date' => '2026-10-02',
            'status' => 'present',
            'source' => 'shop_owner',
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', [
                'shop' => $this->shop->code,
                'tab' => 'monthly-summary',
                'month' => '2026-10',
                'search' => 'Amina',
            ]));

        $row = $response->viewData('monthlySummaryRows')->firstWhere('employee_id', $this->employeeA->id);
        $this->assertSame(2, $row['present']);
        // Crucial test: missing days (29 days) MUST NOT be classified as absent!
        $this->assertSame(0, $row['absent']);
    }

    public function test_duplicate_attendance_prevention(): void
    {
        // First record succeeds
        EmployeeAttendance::query()->create([
            'employee_id' => $this->employeeA->id,
            'shop_id' => $this->shop->id,
            'attendance_date' => '2026-10-01',
            'status' => 'present',
            'source' => 'shop_owner',
        ]);

        // Attempting to create duplicate record on the same date throws DB UNIQUE constraint exception
        $this->expectException(QueryException::class);

        EmployeeAttendance::query()->create([
            'employee_id' => $this->employeeA->id,
            'shop_id' => $this->shop->id,
            'attendance_date' => '2026-10-01',
            'status' => 'present',
            'source' => 'shop_owner',
        ]);
    }

    public function test_employee_details_modal_contains_json_records_and_labels(): void
    {
        EmployeeAttendance::query()->create([
            'employee_id' => $this->employeeA->id,
            'shop_id' => $this->shop->id,
            'attendance_date' => '2026-10-12',
            'status' => 'half_day',
            'source' => 'shop_owner',
            'marked_by' => $this->shopOwner->id,
            'marked_at' => Carbon::parse('2026-10-12 10:15:00', 'Asia/Kolkata'),
            'notes' => 'Left early for doctor appointment',
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', [
                'shop' => $this->shop->code,
                'tab' => 'monthly-summary',
                'month' => '2026-10',
                'search' => 'Amina',
            ]));

        $response->assertOk();
        $response->assertSee('Left early for doctor appointment');
        $response->assertSee('Half Day');
        $response->assertSee('10:15 AM');
    }

    public function test_transferred_or_assigned_employee_during_month_is_included(): void
    {
        $assignedEmployee = Employee::query()->create([
            'employee_code' => 'EMP-00201',
            'name' => 'Transfer Worker',
            'phone' => '9876540001',
            'id_type' => 'aadhaar',
            'id_number' => '999988880001',
            'address' => 'Kochi',
            'default_shop_id' => $this->otherShop->id, // Default is other shop
            'staff_area' => 'shop',
            'verification_status' => 'approved',
            'employment_status' => 'active',
            'salary_type' => 'monthly',
            'monthly_salary' => 20000,
        ]);

        // Historical assignment to Grandcity shop during October 2026
        ShopEmployeeAssignment::query()->create([
            'employee_id' => $assignedEmployee->id,
            'shop_id' => $this->shop->id,
            'status' => 'active',
            'effective_from' => '2026-10-01',
            'effective_to' => '2026-10-15',
        ]);

        EmployeeAttendance::query()->create([
            'employee_id' => $assignedEmployee->id,
            'shop_id' => $this->shop->id,
            'attendance_date' => '2026-10-05',
            'status' => 'present',
            'source' => 'shop_owner',
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', [
                'shop' => $this->shop->code,
                'tab' => 'monthly-summary',
                'month' => '2026-10',
            ]));

        $response->assertOk();
        $response->assertSee('Transfer Worker');
        $row = $response->viewData('monthlySummaryRows')->firstWhere('employee_id', $assignedEmployee->id);
        $this->assertNotNull($row);
        $this->assertSame(1, $row['present']);
    }

    public function test_existing_staff_and_history_tabs_continue_working(): void
    {
        $tabs = ['staff', 'attendance', 'salary', 'history', 'leave'];

        foreach ($tabs as $tab) {
            $response = $this->actingAs($this->shopOwner)
                ->get(route('shop-owner.staff.index', [
                    'shop' => $this->shop->code,
                    'tab' => $tab,
                    'month' => '2026-10',
                ]));

            $response->assertOk();
        }
    }
}
