<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ShopOwner\StoreEmployeeLeaveRequest;
use App\Http\Requests\Web\ShopOwner\UpsertOwnedShopAttendanceRequest;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLeaveRequest;
use App\Models\Shop;
use App\Services\HR\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ShopOwnerStaffController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureOwnerAccess($request);

        $selectedDate = Carbon::parse($request->input('date', today()->toDateString()));
        $ownedShopIds = $request->user()->ownedShopAssignments()->pluck('shop_id');

        return view('shop-owner.staff.index', [
            'selectedDate' => $selectedDate,
            'shops' => Shop::query()->whereIn('id', $ownedShopIds)->orderBy('name')->get(),
            'employees' => Employee::query()
                ->with(['category', 'defaultShop'])
                ->where('staff_area', 'shop')
                ->where('employment_status', 'active')
                ->orderBy('name')
                ->get(),
            'attendanceRecords' => EmployeeAttendance::query()
                ->whereDate('attendance_date', $selectedDate)
                ->get()
                ->keyBy('employee_id'),
            'leaveRequests' => EmployeeLeaveRequest::query()
                ->with(['employee', 'submittedForShop'])
                ->whereIn('submitted_for_shop_id', $ownedShopIds)
                ->latest('id')
                ->limit(8)
                ->get(),
        ]);
    }

    public function storeAttendance(UpsertOwnedShopAttendanceRequest $request): RedirectResponse
    {
        $this->ensureOwnerAccess($request);

        $employee = Employee::query()->findOrFail($request->integer('employee_id'));
        $shop = Shop::query()->findOrFail($request->integer('shop_id'));
        $attendanceDate = Carbon::parse($request->string('attendance_date')->toString());

        abort_unless(
            $this->attendanceService->canOwnerMarkAttendance($request->user(), $employee, $attendanceDate, $shop->id),
            403,
            'You can only mark today attendance for shop staff assigned to your owned shops.',
        );

        $this->attendanceService->upsert(
            $employee,
            $attendanceDate,
            $request->string('status')->toString(),
            $request->user(),
            'owner',
            $shop,
            $request->input('notes'),
        );

        return redirect()->route('shop-owner.staff.index')->with('success', 'Attendance updated for today.');
    }

    public function storeLeave(StoreEmployeeLeaveRequest $request): RedirectResponse
    {
        $this->ensureOwnerAccess($request);

        $employee = Employee::query()->findOrFail($request->integer('employee_id'));
        $shopId = $request->integer('shop_id');

        abort_unless($employee->staff_area === 'shop', 403, 'Only shop staff leave requests can be submitted here.');
        abort_unless($request->user()->ownedShopAssignments()->where('shop_id', $shopId)->exists(), 403, 'This shop is outside your scope.');

        EmployeeLeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'submitted_by' => $request->user()->id,
            'submitted_for_shop_id' => $shopId,
            'start_date' => $request->date('start_date'),
            'end_date' => $request->date('end_date'),
            'status' => 'pending',
            'submission_type' => 'owner',
            'reason' => $request->string('reason')->toString(),
        ]);

        return redirect()->route('shop-owner.staff.index')->with('success', 'Leave request submitted for admin review.');
    }

    private function ensureOwnerAccess(Request $request): void
    {
        abort_unless(
            $request->user()->hasRole('shop') && $request->user()->can('hr.attendance.mark-owned-shop'),
            403,
            'Unauthorized staff access.',
        );
    }
}
