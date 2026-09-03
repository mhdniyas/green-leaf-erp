<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Employee;
use App\Models\EmployeeCategory;
use App\Models\Shop;
use App\Models\ShopOwnerAssignment;
use App\Models\User;
use App\Services\HR\AttendanceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EndToEndHRWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $shopOwner;

    private User $admin;

    private Shop $casioShop;

    private EmployeeCategory $shopCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-09-03 09:15:00', 'Asia/Kolkata'));

        $this->admin = User::factory()->create(['email' => 'admin_hr@example.com']);
        $this->admin->assignRole('admin');
        $this->admin->givePermissionTo('hr.employee.view');
        $this->admin->givePermissionTo('hr.employee.update');

        $this->shopOwner = User::factory()->create(['email' => 'casio_owner@example.com']);
        $this->shopOwner->assignRole('shop');
        $this->shopOwner->givePermissionTo('hr.attendance.mark-owned-shop');

        $this->casioShop = Shop::factory()->create([
            'name' => 'Casio Shop',
            'code' => 'CASIO',
        ]);

        ShopOwnerAssignment::create([
            'user_id' => $this->shopOwner->id,
            'shop_id' => $this->casioShop->id,
            'status' => 'active',
        ]);

        $this->shopCategory = EmployeeCategory::create([
            'code' => 'SHOP_SALES',
            'name' => 'Shop Sales Staff',
            'staff_area' => 'shop',
            'is_active' => true,
        ]);
    }

    public function test_complete_end_to_end_shop_to_hr_approval_and_attendance_and_payroll_flow(): void
    {
        // 1. SHOP OWNER CREATES EMPLOYEE
        $payload = [
            'shop_id' => $this->casioShop->id,
            'name' => 'Final HR Test Employee',
            'phone' => '9876543210',
            'joined_on' => '2026-09-01',
            'id_type' => 'aadhaar',
            'id_number' => '9999-8888-7777',
            'address' => 'Casio Street, Kochi',
            'photo_data_url' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'id_front_data_url' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        ];

        $storeResponse = $this->actingAs($this->shopOwner)
            ->post(route('shop-owner.staff.employees.store'), $payload);

        $storeResponse->assertRedirect(route('shop-owner.staff.index', ['shop' => $this->casioShop->code]));

        $employee = Employee::where('name', 'Final HR Test Employee')->firstOrFail();

        // Verify DB values before HR review
        $this->assertNull($employee->user_id);
        $this->assertEquals('shop', $employee->staff_area);
        $this->assertEquals($this->casioShop->id, $employee->default_shop_id);
        $this->assertEquals('pending', $employee->verification_status);
        $this->assertNull($employee->employee_category_id);
        $this->assertNull($employee->salary_type);
        $this->assertNull($employee->monthly_salary);
        $this->assertNull($employee->daily_wage);

        // 2. SHOP PENDING STATE
        $shopIndexResponse = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', ['shop' => $this->casioShop->code]));

        $shopIndexResponse->assertOk();
        $shopIndexResponse->assertSee('Final HR Test Employee');
        $shopIndexResponse->assertSee('Pending HR Approval');

        // Verify pending employee CANNOT be marked for attendance
        $attendanceService = app(AttendanceService::class);
        $canMarkPending = $attendanceService->canOwnerMarkAttendance(
            $this->shopOwner,
            $employee,
            now(),
            $this->casioShop->id
        );
        $this->assertFalse($canMarkPending);

        // 3. HR APPROVAL
        $approvalsResponse = $this->actingAs($this->admin)
            ->get(route('admin.staff.approvals.index'));
        $approvalsResponse->assertOk();
        $approvalsResponse->assertSee('Final HR Test Employee');

        $reviewResponse = $this->actingAs($this->admin)
            ->get(route('admin.staff.approvals.show', $employee));
        $reviewResponse->assertOk();

        $approveResponse = $this->actingAs($this->admin)
            ->post(route('admin.staff.approve', $employee), [
                'employee_category_id' => $this->shopCategory->id,
                'salary_type' => 'monthly',
                'monthly_salary' => 25000,
            ]);

        $approveResponse->assertRedirect(route('admin.staff.approvals.index'));

        // 4. VERIFY APPROVED DB STATE
        $employee->refresh();
        $this->assertEquals('approved', $employee->verification_status);
        $this->assertEquals('active', $employee->employment_status);
        $this->assertEquals($this->shopCategory->id, $employee->employee_category_id);
        $this->assertEquals('monthly', $employee->salary_type);
        $this->assertEquals(25000.00, (float) $employee->monthly_salary);
        $this->assertNull($employee->daily_wage);
        $this->assertEquals($this->admin->id, $employee->reviewed_by);
        $this->assertNotNull($employee->reviewed_at);
        $this->assertNull($employee->user_id);

        // 5. SHOP AFTER APPROVAL & ATTENDANCE CHECK-IN
        $shopIndexApprovedResponse = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', ['shop' => $this->casioShop->code]));
        $shopIndexApprovedResponse->assertOk();
        $shopIndexApprovedResponse->assertSee('Final HR Test Employee');

        $canMarkApproved = $attendanceService->canOwnerMarkAttendance(
            $this->shopOwner,
            $employee,
            now(),
            $this->casioShop->id
        );
        $this->assertTrue($canMarkApproved);

        // Mark Present
        $attendanceRecord = $attendanceService->upsert(
            $employee,
            now(),
            'present',
            $this->shopOwner,
            'shop_owner',
            $this->casioShop
        );

        $this->assertEquals($employee->id, $attendanceRecord->employee_id);
        $this->assertEquals($this->shopOwner->id, $attendanceRecord->marked_by);
        $this->assertEquals($this->casioShop->id, $attendanceRecord->shop_id);

        // 6. PAYROLL CANDIDATE SELECTION
        $payrollCandidates = Employee::query()
            ->with('category')
            ->approved()
            ->where('employment_status', 'active')
            ->get();

        $this->assertTrue($payrollCandidates->contains('id', $employee->id));

        // 7. REJECTION FLOW
        $rejectedPayload = [
            'shop_id' => $this->casioShop->id,
            'name' => 'Rejected Test Employee',
            'phone' => '9876543211',
            'joined_on' => '2026-09-01',
            'id_type' => 'aadhaar',
            'id_number' => '9999-8888-6666',
            'address' => 'Casio Street, Kochi',
            'photo_data_url' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'id_front_data_url' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        ];

        $this->actingAs($this->shopOwner)
            ->post(route('shop-owner.staff.employees.store'), $rejectedPayload);

        $rejectedEmployee = Employee::where('name', 'Rejected Test Employee')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.staff.reject', $rejectedEmployee), [
                'rejection_reason' => 'ID proof unclear',
            ]);

        $rejectedEmployee->refresh();
        $this->assertEquals('rejected', $rejectedEmployee->verification_status);
        $this->assertEquals('ID proof unclear', $rejectedEmployee->rejection_reason);

        // Verify rejected employee is excluded from payroll candidates
        $payrollCandidatesAfterReject = Employee::query()
            ->with('category')
            ->approved()
            ->where('employment_status', 'active')
            ->get();

        $this->assertFalse($payrollCandidatesAfterReject->contains('id', $rejectedEmployee->id));

        // Verify Shop Owner sees Rejected & Reason
        $shopIndexRejectResponse = $this->actingAs($this->shopOwner)
            ->get(route('shop-owner.staff.index', ['shop' => $this->casioShop->code]));
        $shopIndexRejectResponse->assertOk();
        $shopIndexRejectResponse->assertSee('Rejected');
        $shopIndexRejectResponse->assertSee('ID proof unclear');
    }
}
