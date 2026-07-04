<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\EmployeeLeaveRequest;
use App\Models\Shop;
use App\Models\ShopEmployeeAssignment;
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

        $this->owner->employee()->firstOrFail()->update([
            'employee_category_id' => EmployeeCategory::query()->where('code', 'other-shop')->firstOrFail()->id,
            'staff_area' => 'shop',
            'employment_status' => 'active',
            'default_shop_id' => $this->ownedShop->id,
            'name' => $this->owner->name,
            'email' => $this->owner->email,
            'is_user_linked' => true,
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

        $response->assertRedirect(route('shop-owner.staff.index', ['shop' => $this->ownedShop->code]));
        $attendance = EmployeeAttendance::query()
            ->where('employee_id', $employee->id)
            ->where('shop_id', $this->ownedShop->id)
            ->first();

        $this->assertNotNull($attendance);
        $this->assertSame(today()->toDateString(), $attendance->attendance_date?->toDateString());
        $this->assertSame('owner', $attendance->source);
        $this->assertNotNull($attendance->marked_at);
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

        $response->assertRedirect(route('shop-owner.staff.index', ['shop' => $this->ownedShop->code]));
        $this->assertDatabaseHas('employee_leave_requests', [
            'employee_id' => $employee->id,
            'submitted_by' => $this->owner->id,
            'submitted_for_shop_id' => $this->ownedShop->id,
            'status' => 'pending',
        ]);
    }

    public function test_shop_owner_cannot_submit_leave_for_office_staff(): void
    {
        $employee = Employee::factory()->create([
            'staff_area' => 'office',
        ]);

        $response = $this->actingAs($this->owner)->post(route('shop-owner.staff.leave-requests.store'), [
            'employee_id' => $employee->id,
            'shop_id' => $this->ownedShop->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
            'reason' => 'Office leave should fail',
        ]);

        $response->assertForbidden();
    }

    public function test_shop_owner_can_add_shop_employee_to_quick_attendance_list(): void
    {
        $category = EmployeeCategory::query()->where('code', 'other-shop')->firstOrFail();
        $employee = Employee::factory()->create([
            'employee_category_id' => $category->id,
            'staff_area' => 'shop',
            'name' => 'Quick List Staff',
        ]);

        $response = $this->actingAs($this->owner)->post(route('shop-owner.staff.employees.store'), [
            'shop_id' => $this->ownedShop->id,
            'employee_id' => $employee->id,
        ]);

        $response->assertRedirect(route('shop-owner.staff.index', ['shop' => $this->ownedShop->code]));
        $this->assertDatabaseHas('shop_employee_assignments', [
            'shop_id' => $this->ownedShop->id,
            'employee_id' => $employee->id,
        ]);

        $page = $this->actingAs($this->owner)->get(route('shop-owner.staff.index', ['shop' => $this->ownedShop->code]));
        $page->assertOk();
        $page->assertSee('Quick List Staff');
    }

    public function test_leave_attendance_creates_pending_leave_request_with_reason(): void
    {
        $category = EmployeeCategory::query()->where('code', 'other-shop')->firstOrFail();
        $employee = Employee::factory()->create([
            'employee_category_id' => $category->id,
            'staff_area' => 'shop',
        ]);

        ShopEmployeeAssignment::query()->create([
            'shop_id' => $this->ownedShop->id,
            'employee_id' => $employee->id,
            'assigned_by' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)->post(route('shop-owner.staff.attendance.store'), [
            'employee_id' => $employee->id,
            'attendance_date' => today()->toDateString(),
            'shop_id' => $this->ownedShop->id,
            'status' => 'leave',
            'leave_reason' => 'Family emergency',
        ]);

        $response->assertRedirect(route('shop-owner.staff.index', ['shop' => $this->ownedShop->code]));

        $attendance = EmployeeAttendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', today())
            ->first();

        $this->assertNotNull($attendance);
        $this->assertSame('leave', $attendance->status);
        $this->assertSame('Family emergency', $attendance->notes);

        $leaveRequest = EmployeeLeaveRequest::query()->where('employee_id', $employee->id)->first();
        $this->assertNotNull($leaveRequest);
        $this->assertSame('pending', $leaveRequest->status);
        $this->assertSame('Family emergency', $leaveRequest->reason);
    }

    public function test_shop_owner_dashboard_lists_staff_module(): void
    {
        $response = $this->actingAs($this->owner)->get(route('shop-owner.staff.index'));

        $response->assertOk();
        $response->assertSee('Owned Shop Staff');
        $response->assertSee('Owner Check-In');
    }

    public function test_shop_user_without_owned_shop_assignment_cannot_access_staff_module(): void
    {
        $shopUser = User::factory()->create();
        $shopUser->assignRole('shop');

        $response = $this->actingAs($shopUser)->get(route('shop-owner.staff.index'));

        $response->assertForbidden();
    }

    public function test_shop_owner_can_mark_self_check_in_for_today(): void
    {
        $ownerEmployee = $this->owner->employee()->firstOrFail();

        $response = $this->actingAs($this->owner)->post(route('shop-owner.staff.attendance.store'), [
            'employee_id' => $ownerEmployee->id,
            'attendance_date' => today()->toDateString(),
            'shop_id' => $this->ownedShop->id,
            'status' => 'present',
        ]);

        $response->assertRedirect(route('shop-owner.staff.index', ['shop' => $this->ownedShop->code]));
        $this->assertDatabaseHas('employee_attendances', [
            'employee_id' => $ownerEmployee->id,
            'shop_id' => $this->ownedShop->id,
            'status' => 'present',
            'source' => 'owner',
        ]);
    }
}
