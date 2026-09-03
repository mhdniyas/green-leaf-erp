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

    public function test_staff_tab_preserves_staff_list_with_primary_and_emergency_phones(): void
    {
        $response = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', ['shop' => $this->shop->code, 'tab' => 'staff']));

        $response->assertOk();
        $response->assertSee('Active Shop Staff');
        $response->assertSee('Ahmed Ali');
        $response->assertSee('Primary: 9876543210');
        $response->assertSee('Emergency: 9123456789');
    }
}
