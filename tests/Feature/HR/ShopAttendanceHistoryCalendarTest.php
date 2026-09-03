<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\BusinessSetting;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\Shop;
use App\Models\ShopOwnerAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShopAttendanceHistoryCalendarTest extends TestCase
{
    use RefreshDatabase;

    private User $shopOwner;

    private Shop $shop;

    private Shop $otherShop;

    private Employee $employee;

    private Employee $otherEmployee;

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

        $this->employee = Employee::query()->create([
            'employee_code' => 'EMP-00031',
            'name' => 'Ahmed Ali',
            'phone' => '9876543210',
            'alternate_phone' => '9123456789',
            'id_type' => 'aadhaar',
            'id_number' => '999988887777',
            'address' => 'Kochi',
            'default_shop_id' => $this->shop->id,
            'staff_area' => 'shop',
            'verification_status' => 'approved',
            'employment_status' => 'active',
            'salary_type' => 'monthly',
            'monthly_salary' => 25000,
            'joined_on' => '2026-08-01',
        ]);

        $this->otherEmployee = Employee::query()->create([
            'employee_code' => 'EMP-00032',
            'name' => 'Suresh Kumar',
            'phone' => '9876543211',
            'alternate_phone' => '9123456780',
            'id_type' => 'aadhaar',
            'id_number' => '999988887778',
            'address' => 'Kochi',
            'default_shop_id' => $this->otherShop->id,
            'staff_area' => 'shop',
            'verification_status' => 'approved',
            'employment_status' => 'active',
            'salary_type' => 'monthly',
            'monthly_salary' => 20000,
            'joined_on' => '2026-08-01',
        ]);

        BusinessSetting::query()->updateOrCreate(
            ['key' => 'shop_attendance_cutoff_time'],
            ['value' => '10:00 AM']
        );
    }

    public function test_default_attendance_page_does_not_show_salary_content(): void
    {
        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', ['shop' => $this->shop->code]));

        $response->assertOk();
        $response->assertSee('Quick Check-In');
        $response->assertDontSee('Pay Staff Salary');
        $response->assertDontSee('Recent Salary Payments');
    }

    public function test_non_salary_requests_do_not_load_salary_options(): void
    {
        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', ['shop' => $this->shop->code, 'tab' => 'attendance']));

        $response->assertOk();
        $this->assertSame([], $response->viewData('salaryOptions'));
    }

    public function test_salary_content_appears_on_salary_tab(): void
    {
        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', ['shop' => $this->shop->code, 'tab' => 'salary']));

        $response->assertOk();
        $response->assertSee('Pay Staff Salary');
        $response->assertSee('Recent Salary Payments');
        $this->assertArrayHasKey($this->employee->id, $response->viewData('salaryOptions'));
    }

    public function test_history_tab_renders_calendar_and_defaults_to_correct_month(): void
    {
        Carbon::setTestNow('2026-09-03');

        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', ['shop' => $this->shop->code, 'tab' => 'history']));

        $response->assertOk();
        $response->assertSee('September 2026');
        $response->assertSee('Attendance History');
        $response->assertSee('Selected Date');
        $response->assertSee('03 Sep 2026');
        $response->assertSee('Mon');
        $response->assertSee('Sun');
    }

    public function test_history_month_navigation_links_work(): void
    {
        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', [
                'shop' => $this->shop->code,
                'tab' => 'history',
                'month' => '2026-08',
                'date' => '2026-08-15',
            ]));

        $response->assertOk();
        $response->assertSee('August 2026');
        $response->assertSee('15 Aug 2026');
        $response->assertSee('month=2026-07');
        $response->assertSee('month=2026-09');
    }

    public function test_history_shows_attendance_for_selected_date_only(): void
    {
        EmployeeAttendance::query()->create([
            'employee_id' => $this->employee->id,
            'shop_id' => $this->shop->id,
            'attendance_date' => '2026-09-03',
            'status' => 'present',
            'source' => 'shop_owner',
            'marked_at' => Carbon::parse('2026-09-03 09:15:00', 'Asia/Kolkata'),
            'marked_by' => $this->shopOwner->id,
            'notes' => 'On time check-in',
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', [
                'shop' => $this->shop->code,
                'tab' => 'history',
                'month' => '2026-09',
                'date' => '2026-09-03',
            ]));

        $response->assertOk();
        $response->assertSee('Ahmed Ali');
        $response->assertSee('EMP-00031');
        $response->assertSee('✓ Present');
        $response->assertSee('9:15 AM');
        $response->assertSee('On time check-in');
        $response->assertSee($this->shopOwner->name);
    }

    public function test_history_does_not_show_attendance_from_another_date(): void
    {
        EmployeeAttendance::query()->create([
            'employee_id' => $this->employee->id,
            'shop_id' => $this->shop->id,
            'attendance_date' => '2026-09-02',
            'status' => 'present',
            'source' => 'shop_owner',
            'marked_at' => Carbon::parse('2026-09-02 09:10:00', 'Asia/Kolkata'),
            'marked_by' => $this->shopOwner->id,
            'notes' => 'Yesterday note',
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', [
                'shop' => $this->shop->code,
                'tab' => 'history',
                'month' => '2026-09',
                'date' => '2026-09-03',
            ]));

        $response->assertOk();
        $response->assertDontSee('Yesterday note');
        $response->assertSee('No attendance records for this date.');
    }

    public function test_history_does_not_show_attendance_from_another_shop(): void
    {
        EmployeeAttendance::query()->create([
            'employee_id' => $this->otherEmployee->id,
            'shop_id' => $this->otherShop->id,
            'attendance_date' => '2026-09-03',
            'status' => 'present',
            'source' => 'shop_owner',
            'marked_at' => Carbon::parse('2026-09-03 09:12:00', 'Asia/Kolkata'),
            'marked_by' => $this->shopOwner->id,
            'notes' => 'Other shop secret note',
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', [
                'shop' => $this->shop->code,
                'tab' => 'history',
                'month' => '2026-09',
                'date' => '2026-09-03',
            ]));

        $response->assertOk();
        $response->assertDontSee('Other shop secret note');
        $response->assertDontSee('Suresh Kumar');
    }

    public function test_history_date_with_no_records_shows_empty_state(): void
    {
        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', [
                'shop' => $this->shop->code,
                'tab' => 'history',
                'month' => '2026-09',
                'date' => '2026-09-20',
            ]));

        $response->assertOk();
        $response->assertSee('No attendance records for this date.');
    }

    public function test_invalid_month_and_date_parameters_fall_back_safely(): void
    {
        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', [
                'shop' => $this->shop->code,
                'tab' => 'history',
                'month' => 'invalid-month',
                'date' => 'invalid-date',
            ]));

        $response->assertOk();
        $response->assertSee('Attendance History');
    }

    public function test_date_outside_requested_month_is_resolved_safely(): void
    {
        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', [
                'shop' => $this->shop->code,
                'tab' => 'history',
                'month' => '2026-10',
                'date' => '2026-09-03',
            ]));

        $response->assertOk();
        $response->assertSee('October 2026');
        $this->assertSame('2026-10', $response->viewData('calendarMonth')->format('Y-m'));
        $this->assertSame('2026-10', $response->viewData('selectedDate')->format('Y-m'));
    }

    public function test_existing_quick_check_in_and_attendance_workflows_still_render(): void
    {
        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', [
                'shop' => $this->shop->code,
                'tab' => 'attendance',
            ]));

        $response->assertOk();
        $response->assertSee('Quick Check-In');
        $response->assertSee('Ahmed Ali');
        $response->assertSee('EMP-00031');
    }

    public function test_advance_and_leave_tabs_still_work(): void
    {
        $advanceResponse = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', [
                'shop' => $this->shop->code,
                'tab' => 'advance',
            ]));

        $advanceResponse->assertOk();
        $advanceResponse->assertSee('Request Advance');

        $leaveResponse = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', [
                'shop' => $this->shop->code,
                'tab' => 'leave',
            ]));

        $leaveResponse->assertOk();
        $leaveResponse->assertSee('Request Leave');
    }
}
