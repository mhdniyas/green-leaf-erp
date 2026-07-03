<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ShopOwner\StoreEmployeeLeaveRequest;
use App\Http\Requests\Web\ShopOwner\StoreShopEmployeeAssignmentRequest;
use App\Http\Requests\Web\ShopOwner\UpsertOwnedShopAttendanceRequest;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLeaveRequest;
use App\Models\Shop;
use App\Models\ShopEmployeeAssignment;
use App\Services\HR\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
        $ownedShops = Shop::query()
            ->whereIn('id', $request->user()->ownedShopAssignments()->pluck('shop_id'))
            ->orderBy('name')
            ->get();
        $selectedShop = $this->selectedShop($ownedShops, $request->string('shop')->toString());
        $employeeSearch = trim($request->string('employee_search')->toString());
        $ownerEmployee = $request->user()->employee()->with('category')->first();
        $attendanceRecords = EmployeeAttendance::query()
            ->with(['shop', 'markedBy'])
            ->whereDate('attendance_date', $selectedDate)
            ->when($selectedShop !== null, fn ($query) => $query->where('shop_id', $selectedShop->id))
            ->get()
            ->keyBy('employee_id');

        $quickEmployees = $this->quickEmployeesForShop($selectedShop?->id, $selectedDate);

        return view('shop-owner.staff.index', [
            'selectedDate' => $selectedDate,
            'shops' => $ownedShops,
            'selectedShop' => $selectedShop,
            'ownerEmployee' => $ownerEmployee,
            'ownerAttendance' => $ownerEmployee !== null ? $attendanceRecords->get($ownerEmployee->id) : null,
            'employeeSearch' => $employeeSearch,
            'employees' => $quickEmployees,
            'attendanceRecords' => $attendanceRecords,
            'searchResults' => $this->employeeSearchResults($employeeSearch, $selectedShop?->id),
            'leaveRequests' => EmployeeLeaveRequest::query()
                ->with(['employee.category', 'submittedForShop', 'reviewedBy'])
                ->when($selectedShop !== null, fn ($query) => $query->where('submitted_for_shop_id', $selectedShop->id))
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function storeEmployeeAssignment(StoreShopEmployeeAssignmentRequest $request): RedirectResponse
    {
        $this->ensureOwnerAccess($request);

        $shop = Shop::query()->findOrFail($request->integer('shop_id'));
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));

        abort_unless($request->user()->ownedShopAssignments()->where('shop_id', $shop->id)->exists(), 403, 'This shop is outside your scope.');
        abort_unless($employee->staff_area === 'shop', 403, 'Only shop staff can be added to owned shop attendance lists.');

        ShopEmployeeAssignment::query()->firstOrCreate(
            [
                'shop_id' => $shop->id,
                'employee_id' => $employee->id,
            ],
            [
                'assigned_by' => $request->user()->id,
            ],
        );

        return redirect()->route('shop-owner.staff.index', ['shop' => $shop->code])
            ->with('success', 'Employee added to this shop attendance list.');
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

        $notes = $request->string('status')->toString() === 'leave'
            ? $request->string('leave_reason')->toString()
            : $request->input('notes');

        $this->attendanceService->upsert(
            $employee,
            $attendanceDate,
            $request->string('status')->toString(),
            $request->user(),
            'owner',
            $shop,
            $notes,
        );

        if ($request->string('status')->toString() === 'leave') {
            EmployeeLeaveRequest::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'submitted_for_shop_id' => $shop->id,
                    'start_date' => $attendanceDate->toDateString(),
                    'end_date' => $attendanceDate->toDateString(),
                    'submission_type' => 'owner',
                ],
                [
                    'submitted_by' => $request->user()->id,
                    'status' => 'pending',
                    'reason' => $request->string('leave_reason')->toString(),
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_note' => null,
                ],
            );
        }

        return redirect()->route('shop-owner.staff.index', ['shop' => $shop->code])
            ->with('success', 'Attendance updated for today.');
    }

    public function storeLeave(StoreEmployeeLeaveRequest $request): RedirectResponse
    {
        $this->ensureOwnerAccess($request);

        $employee = Employee::query()->findOrFail($request->integer('employee_id'));
        $shopId = $request->integer('shop_id');
        $shop = Shop::query()->findOrFail($shopId);

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

        return redirect()->route('shop-owner.staff.index', ['shop' => $shop->code])->with('success', 'Leave request submitted for admin review.');
    }

    private function ensureOwnerAccess(Request $request): void
    {
        abort_unless(
            $request->user()->hasRole('shop') && $request->user()->can('hr.attendance.mark-owned-shop'),
            403,
            'Unauthorized staff access.',
        );

        abort_unless(
            $request->user()->ownedShopAssignments()->exists(),
            403,
            'This staff module is available only for owned shop assignments.',
        );
    }

    /**
     * @param  Collection<int, Shop>  $ownedShops
     */
    private function selectedShop(Collection $ownedShops, string $shopCode): ?Shop
    {
        if ($ownedShops->isEmpty()) {
            return null;
        }

        return $ownedShops->firstWhere('code', $shopCode) ?? $ownedShops->first();
    }

    /**
     * @return Collection<int, Employee>
     */
    private function quickEmployeesForShop(?int $shopId, Carbon $selectedDate): Collection
    {
        if ($shopId === null) {
            return collect();
        }

        $assignedEmployeeIds = ShopEmployeeAssignment::query()
            ->where('shop_id', $shopId)
            ->pluck('employee_id');

        $todayEmployeeIds = EmployeeAttendance::query()
            ->where('shop_id', $shopId)
            ->whereDate('attendance_date', $selectedDate)
            ->pluck('employee_id');

        $employeeIds = $assignedEmployeeIds->merge($todayEmployeeIds)->unique()->values();

        if ($employeeIds->isEmpty()) {
            return collect();
        }

        return Employee::query()
            ->with(['category'])
            ->whereIn('id', $employeeIds)
            ->where('staff_area', 'shop')
            ->where('employment_status', 'active')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Employee>
     */
    private function employeeSearchResults(string $search, ?int $selectedShopId): Collection
    {
        if ($search === '' || $selectedShopId === null) {
            return collect();
        }

        $assignedEmployeeIds = ShopEmployeeAssignment::query()
            ->where('shop_id', $selectedShopId)
            ->pluck('employee_id');

        return Employee::query()
            ->with('category')
            ->where('staff_area', 'shop')
            ->where('employment_status', 'active')
            ->whereNotIn('id', $assignedEmployeeIds)
            ->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('employee_code', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            })
            ->orderBy('name')
            ->limit(10)
            ->get();
    }
}
