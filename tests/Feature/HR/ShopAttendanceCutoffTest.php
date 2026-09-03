<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\BusinessSetting;
use App\Models\Employee;
use App\Models\EmployeeCategory;
use App\Models\Shop;
use App\Models\ShopOwnerAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShopAttendanceCutoffTest extends TestCase
{
    use RefreshDatabase;

    private User $shopOwner;

    private User $admin;

    private Shop $shop;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
        ]);
        $this->admin->assignRole(['admin', 'purchaser']);

        $this->shopOwner = User::factory()->create([
            'email' => 'owner@example.com',
        ]);
        $this->shopOwner->assignRole('shop');
        $this->shopOwner->givePermissionTo('hr.attendance.mark-owned-shop');

        $this->shop = Shop::query()->create([
            'name' => 'Casio Shop',
            'code' => 'CASIO',
            'warehouse_tag' => 'CS',
            'is_active' => true,
        ]);

        ShopOwnerAssignment::query()->create([
            'user_id' => $this->shopOwner->id,
            'shop_id' => $this->shop->id,
            'status' => 'active',
        ]);

        $category = EmployeeCategory::query()->create([
            'name' => 'Sales Representative',
            'code' => 'SALES-REP',
            'staff_area' => 'shop',
            'is_active' => true,
        ]);

        $this->employee = Employee::query()->create([
            'employee_code' => 'EMP-1001',
            'name' => 'Rahul Sharma',
            'phone' => '9876543210',
            'default_shop_id' => $this->shop->id,
            'employee_category_id' => $category->id,
            'salary_type' => 'monthly',
            'monthly_salary' => 15000,
            'staff_area' => 'shop',
            'employment_status' => 'active',
            'verification_status' => 'approved',
            'joined_on' => '2026-09-01',
            'id_type' => 'aadhaar',
            'id_number' => '1234-5678-9012',
            'address' => 'Kochi',
        ]);
    }

    public function test_default_cutoff_setting_is_10_00_am(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.company-settings.edit'));

        $response->assertOk();
        $response->assertSee('Shop Attendance Cutoff Time');
        $response->assertSee('value="10:00"', false);
    }

    public function test_admin_can_update_cutoff_time_setting(): void
    {
        $response = $this->actingAs($this->admin)
            ->patch(route('admin.company-settings.update'), [
                'company_name' => 'Green Leaf Traders',
                'default_purchaser_user_id' => $this->admin->id,
                'shop_attendance_cutoff_time' => '09:30',
            ]);

        $response->assertRedirect(route('admin.company-settings.edit'));
        $this->assertDatabaseHas('business_settings', [
            'key' => 'shop_attendance_cutoff_time',
            'value' => '09:30',
        ]);
    }

    public function test_shop_owner_can_mark_present_without_note_before_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 09:30:00', 'Asia/Kolkata'));

        $response = $this->actingAs($this->shopOwner)
            ->post(route('shop-owner.staff.attendance.store'), [
                'employee_id' => $this->employee->id,
                'attendance_date' => '2026-09-03',
                'shop_id' => $this->shop->id,
                'status' => 'present',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employee_attendances', [
            'employee_id' => $this->employee->id,
            'status' => 'present',
            'source' => 'owner',
            'notes' => null,
        ]);
    }

    public function test_shop_owner_cannot_mark_half_day_without_reason(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 09:30:00', 'Asia/Kolkata'));

        $response = $this->actingAs($this->shopOwner)
            ->post(route('shop-owner.staff.attendance.store'), [
                'employee_id' => $this->employee->id,
                'attendance_date' => '2026-09-03',
                'shop_id' => $this->shop->id,
                'status' => 'half_day',
            ]);

        $response->assertSessionHasErrors(['notes']);
    }

    public function test_shop_owner_can_mark_half_day_with_valid_reason(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 09:30:00', 'Asia/Kolkata'));

        $response = $this->actingAs($this->shopOwner)
            ->post(route('shop-owner.staff.attendance.store'), [
                'employee_id' => $this->employee->id,
                'attendance_date' => '2026-09-03',
                'shop_id' => $this->shop->id,
                'status' => 'half_day',
                'notes' => 'Doctor appointment in afternoon',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employee_attendances', [
            'employee_id' => $this->employee->id,
            'status' => 'half_day',
            'notes' => 'Doctor appointment in afternoon',
        ]);
    }

    public function test_shop_owner_blocked_after_cutoff_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 10:01:00', 'Asia/Kolkata'));

        $response = $this->actingAs($this->shopOwner)
            ->post(route('shop-owner.staff.attendance.store'), [
                'employee_id' => $this->employee->id,
                'attendance_date' => '2026-09-03',
                'shop_id' => $this->shop->id,
                'status' => 'present',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Attendance marking closed at 10:00 AM. Contact HR for corrections.');

        $this->assertDatabaseMissing('employee_attendances', [
            'employee_id' => $this->employee->id,
            'attendance_date' => '2026-09-03',
        ]);
    }

    public function test_hr_can_mark_or_correct_attendance_after_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 11:30:00', 'Asia/Kolkata'));

        $response = $this->actingAs($this->admin)
            ->post(route('admin.staff.attendance.store'), [
                'employee_id' => $this->employee->id,
                'attendance_date' => '2026-09-03',
                'shop_id' => $this->shop->id,
                'status' => 'absent',
                'notes' => 'Late check-in without notice',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employee_attendances', [
            'employee_id' => $this->employee->id,
            'status' => 'absent',
            'source' => 'admin',
            'notes' => 'Late check-in without notice',
        ]);
    }

    public function test_updated_cutoff_time_is_immediately_respected(): void
    {
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'shop_attendance_cutoff_time'],
            ['value' => '09:30'],
        );

        Carbon::setTestNow(Carbon::parse('2026-09-03 09:35:00', 'Asia/Kolkata'));

        $response = $this->actingAs($this->shopOwner)
            ->post(route('shop-owner.staff.attendance.store'), [
                'employee_id' => $this->employee->id,
                'attendance_date' => '2026-09-03',
                'shop_id' => $this->shop->id,
                'status' => 'present',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Attendance marking closed at 9:30 AM. Contact HR for corrections.');
    }
}
