<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\Shop;
use App\Models\ShopOwnerAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShopAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-09-03 09:15:00', 'Asia/Kolkata'));
    }

    public function test_shop_user_can_mark_attendance_for_assigned_employee_without_user_account(): void
    {
        $shop = Shop::factory()->create();

        /** @var User $shopUser */
        $shopUser = User::factory()->create();
        $shopUser->assignRole('shop');

        ShopOwnerAssignment::create([
            'user_id' => $shopUser->id,
            'shop_id' => $shop->id,
        ]);

        $category = EmployeeCategory::factory()->create([
            'code' => 'other-shop',
            'staff_area' => 'shop',
        ]);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'name' => 'Shop Worker NonUser',
            'user_id' => null,
            'default_shop_id' => $shop->id,
            'employee_category_id' => $category->id,
            'staff_area' => 'shop',
            'employment_status' => 'active',
        ]);

        $response = $this->actingAs($shopUser)
            ->post(route('shop-owner.staff.attendance.store'), [
                'employee_id' => $employee->id,
                'shop_id' => $shop->id,
                'attendance_date' => today()->toDateString(),
                'status' => 'present',
                'notes' => 'Checked in on time',
            ]);

        $response->assertRedirect();

        $attendance = EmployeeAttendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', today()->toDateString())
            ->first();

        $this->assertNotNull($attendance);
        $this->assertEquals($shop->id, $attendance->shop_id);
        $this->assertEquals($shopUser->id, $attendance->marked_by);
        $this->assertEquals('present', $attendance->status);

        $this->assertNull($employee->fresh()->user_id);
    }

    public function test_shop_user_cannot_mark_attendance_for_employee_assigned_to_another_shop(): void
    {
        $casioShop = Shop::factory()->create(['name' => 'Casio']);
        $otherShop = Shop::factory()->create(['name' => 'Ashirwad']);

        /** @var User $casioUser */
        $casioUser = User::factory()->create();
        $casioUser->assignRole('shop');

        ShopOwnerAssignment::create([
            'user_id' => $casioUser->id,
            'shop_id' => $casioShop->id,
        ]);

        $category = EmployeeCategory::factory()->create([
            'code' => 'other-shop',
            'staff_area' => 'shop',
        ]);

        /** @var Employee $otherShopEmployee */
        $otherShopEmployee = Employee::factory()->create([
            'name' => 'Ashirwad Worker',
            'user_id' => null,
            'default_shop_id' => $otherShop->id,
            'employee_category_id' => $category->id,
            'staff_area' => 'shop',
            'employment_status' => 'active',
        ]);

        $response = $this->actingAs($casioUser)
            ->post(route('shop-owner.staff.attendance.store'), [
                'employee_id' => $otherShopEmployee->id,
                'shop_id' => $casioShop->id,
                'attendance_date' => today()->toDateString(),
                'status' => 'present',
            ]);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('employee_attendances', [
            'employee_id' => $otherShopEmployee->id,
            'shop_id' => $casioShop->id,
        ]);
    }

    public function test_shop_user_itself_does_not_need_an_employee_record(): void
    {
        $shop = Shop::factory()->create();

        /** @var User $shopUser */
        $shopUser = User::factory()->create();
        $shopUser->assignRole('shop');

        ShopOwnerAssignment::create([
            'user_id' => $shopUser->id,
            'shop_id' => $shop->id,
        ]);

        $this->assertNull(Employee::where('user_id', $shopUser->id)->first());

        $response = $this->actingAs($shopUser)
            ->get(route('shop-owner.staff.index', ['shop' => $shop->code]));

        $response->assertStatus(200);
    }

    public function test_admin_attendance_view_displays_marked_employee_attendance(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $shop = Shop::factory()->create();
        $category = EmployeeCategory::factory()->create(['staff_area' => 'shop']);

        $employee = Employee::factory()->create([
            'user_id' => null,
            'default_shop_id' => $shop->id,
            'employee_category_id' => $category->id,
            'staff_area' => 'shop',
        ]);

        EmployeeAttendance::create([
            'employee_id' => $employee->id,
            'shop_id' => $shop->id,
            'attendance_date' => today()->toDateString(),
            'status' => 'present',
            'marked_by' => $admin->id,
            'source' => 'admin',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.staff.index', ['tab' => 'attendance', 'date' => today()->toDateString()]));

        $response->assertStatus(200);
        $response->assertSee($employee->name);
    }
}
