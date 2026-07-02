<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\Shop;
use App\Models\ShopOwnerAssignment;
use App\Models\User;
use Database\Seeders\EmployeeCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopOwnerStaffAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Shop $ownedShop;

    private Shop $otherShop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            EmployeeCategorySeeder::class,
        ]);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('shop');

        $this->ownedShop = Shop::factory()->create(['name' => 'Owned Shop']);
        $this->otherShop = Shop::factory()->create(['name' => 'Other Shop']);

        ShopOwnerAssignment::query()->create([
            'user_id' => $this->owner->id,
            'shop_id' => $this->ownedShop->id,
        ]);
    }

    public function test_shop_owner_can_mark_today_attendance_for_owned_shop_staff(): void
    {
        $category = EmployeeCategory::query()->where('code', 'other-shop')->firstOrFail();
        $employee = Employee::factory()->create([
            'employee_category_id' => $category->id,
            'staff_area' => 'shop',
        ]);

        $response = $this->actingAs($this->owner)->post(route('shop-owner.staff.attendance.store'), [
            'employee_id' => $employee->id,
            'attendance_date' => today()->toDateString(),
            'shop_id' => $this->ownedShop->id,
            'status' => 'present',
        ]);

        $response->assertRedirect(route('shop-owner.staff.index'));
        $attendance = EmployeeAttendance::query()
            ->where('employee_id', $employee->id)
            ->where('shop_id', $this->ownedShop->id)
            ->first();

        $this->assertNotNull($attendance);
        $this->assertSame(today()->toDateString(), $attendance->attendance_date?->toDateString());
        $this->assertSame('owner', $attendance->source);
    }

    public function test_shop_owner_cannot_mark_past_or_future_attendance(): void
    {
        $employee = Employee::factory()->create(['staff_area' => 'shop']);

        $yesterdayResponse = $this->actingAs($this->owner)->post(route('shop-owner.staff.attendance.store'), [
            'employee_id' => $employee->id,
            'attendance_date' => today()->subDay()->toDateString(),
            'shop_id' => $this->ownedShop->id,
            'status' => 'present',
        ]);

        $tomorrowResponse = $this->actingAs($this->owner)->post(route('shop-owner.staff.attendance.store'), [
            'employee_id' => $employee->id,
            'attendance_date' => today()->addDay()->toDateString(),
            'shop_id' => $this->ownedShop->id,
            'status' => 'present',
        ]);

        $yesterdayResponse->assertForbidden();
        $tomorrowResponse->assertForbidden();
    }

    public function test_shop_owner_cannot_mark_attendance_outside_owned_shop_scope(): void
    {
        $employee = Employee::factory()->create(['staff_area' => 'shop']);

        $response = $this->actingAs($this->owner)->post(route('shop-owner.staff.attendance.store'), [
            'employee_id' => $employee->id,
            'attendance_date' => today()->toDateString(),
            'shop_id' => $this->otherShop->id,
            'status' => 'present',
        ]);

        $response->assertForbidden();
    }

    public function test_shop_owner_cannot_mark_office_staff_attendance(): void
    {
        $category = EmployeeCategory::query()->where('code', 'office')->firstOrFail();
        $employee = Employee::factory()->create([
            'employee_category_id' => $category->id,
            'staff_area' => 'office',
        ]);

        $response = $this->actingAs($this->owner)->post(route('shop-owner.staff.attendance.store'), [
            'employee_id' => $employee->id,
            'attendance_date' => today()->toDateString(),
            'shop_id' => $this->ownedShop->id,
            'status' => 'present',
        ]);

        $response->assertForbidden();
    }

    public function test_shop_owner_can_submit_leave_for_non_user_staff(): void
    {
        $employee = Employee::factory()->create([
            'staff_area' => 'shop',
            'is_user_linked' => false,
        ]);

        $response = $this->actingAs($this->owner)->post(route('shop-owner.staff.leave-requests.store'), [
            'employee_id' => $employee->id,
            'shop_id' => $this->ownedShop->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'reason' => 'Medical leave',
        ]);

        $response->assertRedirect(route('shop-owner.staff.index'));
        $this->assertDatabaseHas('employee_leave_requests', [
            'employee_id' => $employee->id,
            'submitted_by' => $this->owner->id,
            'submitted_for_shop_id' => $this->ownedShop->id,
            'status' => 'pending',
        ]);
    }

    public function test_shop_owner_dashboard_lists_staff_module(): void
    {
        $response = $this->actingAs($this->owner)->get(route('shop-owner.staff.index'));

        $response->assertOk();
        $response->assertSee('Owned Shop Staff');
    }
}
