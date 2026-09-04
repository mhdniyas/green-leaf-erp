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

class ShopAttendanceUIRedesignTest extends TestCase
{
    use RefreshDatabase;

    private User $shopOwner;

    private Shop $shop;

    private Employee $employee;

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
            'joined_on' => '2026-09-01',
        ]);

        BusinessSetting::query()->updateOrCreate(
            ['key' => 'shop_attendance_cutoff_time'],
            ['value' => '10:00 AM']
        );
    }

    public function test_present_one_tap_store_returns_marked_time_and_status_label(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 09:18:00', 'Asia/Kolkata'));

        $response = $this->actingAs($this->shopOwner)
            ->postJson(route('shop-owner.staff.attendance.store'), [
                'employee_id' => $this->employee->id,
                'shop_id' => $this->shop->id,
                'attendance_date' => '2026-09-03',
                'status' => 'present',
                'notes' => '',
            ]);

        $response->assertOk();
        $response->assertJson([
            'message' => 'Attendance updated for today.',
            'attendance' => [
                'employee_id' => $this->employee->id,
                'status' => 'present',
                'status_label' => '✓ Present',
                'marked_at' => '9:18 AM',
                'notes' => null,
            ],
        ]);
    }

    public function test_half_day_store_with_reason_returns_marked_time_and_notes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 09:25:00', 'Asia/Kolkata'));

        $response = $this->actingAs($this->shopOwner)
            ->postJson(route('shop-owner.staff.attendance.store'), [
                'employee_id' => $this->employee->id,
                'shop_id' => $this->shop->id,
                'attendance_date' => '2026-09-03',
                'status' => 'half_day',
                'notes' => 'Medical appointment',
            ]);

        $response->assertOk();
        $response->assertJson([
            'message' => 'Attendance updated for today.',
            'attendance' => [
                'employee_id' => $this->employee->id,
                'status' => 'half_day',
                'status_label' => 'Half Day',
                'marked_at' => '9:25 AM',
                'notes' => 'Medical appointment',
            ],
        ]);
    }

    public function test_attendance_tab_displays_marked_status_time_change_button_and_action_buttons(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 3, 9, 18, 0, 'Asia/Kolkata'));

        EmployeeAttendance::query()->create([
            'employee_id' => $this->employee->id,
            'shop_id' => $this->shop->id,
            'attendance_date' => '2026-09-03',
            'status' => 'present',
            'source' => 'shop_owner',
            'marked_at' => Carbon::create(2026, 9, 3, 9, 18, 0, 'Asia/Kolkata'),
            'marked_by' => $this->shopOwner->id,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', ['shop' => $this->shop->code, 'tab' => 'attendance']));

        $att = EmployeeAttendance::query()->firstOrFail();
        $expectedTime = $att->marked_at->timezone('Asia/Kolkata')->format('g:i A');

        $response->assertOk();
        $response->assertSee('Ahmed Ali');
        $response->assertSee('EMP-00031');
        $response->assertSee('✓ Present');
        $response->assertSee($expectedTime);
        $response->assertSee('[Change]');
        $response->assertSee('✓ Present');
        $response->assertSee('◐ Half');
        $response->assertSee('L Leave');
        $response->assertSee('× Absent');
    }

    public function test_after_cutoff_attendance_is_read_only_and_shows_locked_message(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 10:15:00', 'Asia/Kolkata'));

        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', ['shop' => $this->shop->code, 'tab' => 'attendance']));

        $response->assertOk();
        $response->assertSee('Attendance closed · 10:00 AM');
        $response->assertSee('Not marked · Marking closed at 10:00 AM. Contact HR for corrections.');
    }

    public function test_quick_check_in_updates_existing_attendance_without_creating_duplicate(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 09:15:00', 'Asia/Kolkata'));

        // First mark as present
        $firstResponse = $this->actingAs($this->shopOwner)
            ->postJson(route('shop-owner.staff.attendance.store'), [
                'employee_id' => $this->employee->id,
                'shop_id' => $this->shop->id,
                'attendance_date' => '2026-09-03',
                'status' => 'present',
                'notes' => '',
            ]);

        $firstResponse->assertOk();
        $this->assertEquals(1, EmployeeAttendance::query()->where('employee_id', $this->employee->id)->count());

        // Change status to absent with reason
        $secondResponse = $this->actingAs($this->shopOwner)
            ->postJson(route('shop-owner.staff.attendance.store'), [
                'employee_id' => $this->employee->id,
                'shop_id' => $this->shop->id,
                'attendance_date' => '2026-09-03',
                'status' => 'absent',
                'notes' => 'Unwell today',
            ]);

        $secondResponse->assertOk();
        $secondResponse->assertJson([
            'message' => 'Attendance updated for today.',
            'attendance' => [
                'employee_id' => $this->employee->id,
                'status' => 'absent',
                'status_label' => 'Absent',
                'notes' => 'Unwell today',
            ],
        ]);

        // Still exactly 1 record, updated in place
        $this->assertEquals(1, EmployeeAttendance::query()->where('employee_id', $this->employee->id)->count());
        $fresh = EmployeeAttendance::query()->where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertEquals('absent', $fresh->status);
        $this->assertEquals('Unwell today', $fresh->notes);

        // Reload page: attendance tab shows updated Absent badge and reason
        $pageResponse = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', ['shop' => $this->shop->code, 'tab' => 'attendance']));

        $pageResponse->assertOk();
        $pageResponse->assertSee('Absent');
        $pageResponse->assertSee('Unwell today');
    }

    public function test_quick_check_in_validation_rejects_missing_reason_for_half_day_and_leave(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 09:15:00', 'Asia/Kolkata'));

        $halfDayResponse = $this->actingAs($this->shopOwner)
            ->postJson(route('shop-owner.staff.attendance.store'), [
                'employee_id' => $this->employee->id,
                'shop_id' => $this->shop->id,
                'attendance_date' => '2026-09-03',
                'status' => 'half_day',
                'notes' => '',
            ]);

        $halfDayResponse->assertStatus(422);
        $halfDayResponse->assertJsonValidationErrors(['notes']);

        $leaveResponse = $this->actingAs($this->shopOwner)
            ->postJson(route('shop-owner.staff.attendance.store'), [
                'employee_id' => $this->employee->id,
                'shop_id' => $this->shop->id,
                'attendance_date' => '2026-09-03',
                'status' => 'leave',
                'notes' => '',
            ]);

        $leaveResponse->assertStatus(422);
        $leaveResponse->assertJsonValidationErrors(['leave_reason']);
    }

    public function test_quick_check_in_cannot_mark_employee_from_unauthorized_shop(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 09:15:00', 'Asia/Kolkata'));

        $otherShop = Shop::query()->create([
            'name' => 'Other Shop',
            'code' => 'AV_OTHER',
            'warehouse_tag' => 'OT',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->postJson(route('shop-owner.staff.attendance.store'), [
                'employee_id' => $this->employee->id,
                'shop_id' => $otherShop->id,
                'attendance_date' => '2026-09-03',
                'status' => 'present',
                'notes' => '',
            ]);

        $response->assertStatus(403);
    }
}
