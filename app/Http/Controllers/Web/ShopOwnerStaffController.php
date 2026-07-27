<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ShopOwner\StoreEmployeeAdvanceRequest;
use App\Http\Requests\Web\ShopOwner\StoreEmployeeLeaveRequest;
use App\Http\Requests\Web\ShopOwner\StoreShopEmployeeAssignmentRequest;
use App\Http\Requests\Web\ShopOwner\StoreShopStaffSalaryPaymentRequest;
use App\Http\Requests\Web\ShopOwner\UpsertOwnedShopAttendanceRequest;
use App\Models\Employee;
use App\Models\EmployeeAdvanceRequest;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollRunItem;
use App\Models\Shop;
use App\Models\ShopEmployeeAssignment;
use App\Models\ShopStaffPayment;
use App\Services\HR\AttendanceService;
use App\Services\HR\EmployeeAdvanceService;
use App\Services\HR\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ShopOwnerStaffController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly EmployeeAdvanceService $employeeAdvanceService,
        private readonly PayrollService $payrollService,
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
        $selectedTab = in_array($request->string('tab', 'attendance')->toString(), ['attendance', 'advance', 'salary', 'leave', 'history'], true)
            ? $request->string('tab', 'attendance')->toString()
            : 'attendance';
        [$filterStartDate, $filterEndDate] = $this->nullableDateRangeFromRequest($request);
        $employeeSearch = trim($request->string('employee_search')->toString());
        $ownerEmployee = $request->user()->employee()->with('category')->first();
        $attendanceRecords = EmployeeAttendance::query()
            ->with(['shop', 'markedBy'])
            ->whereDate('attendance_date', $selectedDate)
            ->when($selectedShop !== null, fn ($query) => $query->where('shop_id', $selectedShop->id))
            ->get()
            ->keyBy('employee_id');

        $quickEmployees = $this->quickEmployeesForShop($selectedShop?->id, $selectedDate);
        $advanceEmployees = $this->advanceEmployeesForShop($selectedShop?->id, $selectedDate);
        $advanceOptions = $advanceEmployees
            ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $this->advanceOptionForEmployee($employee, $selectedDate, $selectedShop?->id)])
            ->all();
        $salaryOptions = $quickEmployees
            ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $this->salaryOptionForEmployee($employee, $selectedDate, $selectedShop?->id)])
            ->all();

        return view('shop-owner.staff.index', [
            'selectedDate' => $selectedDate,
            'selectedTab' => $selectedTab,
            'shops' => $ownedShops,
            'selectedShop' => $selectedShop,
            'ownerEmployee' => $ownerEmployee,
            'ownerAttendance' => $ownerEmployee !== null ? $attendanceRecords->get($ownerEmployee->id) : null,
            'employeeSearch' => $employeeSearch,
            'employees' => $quickEmployees,
            'advanceEmployees' => $advanceEmployees,
            'advanceOptions' => $advanceOptions,
            'salaryOptions' => $salaryOptions,
            'attendanceRecords' => $attendanceRecords,
            'recentPayrollPayments' => ShopStaffPayment::query()
                ->with(['employee', 'advanceRequest', 'cashbookLine.entry'])
                ->when($selectedShop !== null, fn ($query) => $query->where('shop_id', $selectedShop->id))
                ->when($filterStartDate, fn ($query) => $query->whereDate('paid_on', '>=', $filterStartDate))
                ->when($filterEndDate, fn ($query) => $query->whereDate('paid_on', '<=', $filterEndDate))
                ->latest('paid_on')
                ->latest('id')
                ->paginate(8, ['*'], 'staff_payments_page')
                ->withQueryString(),
            'advanceRequests' => EmployeeAdvanceRequest::query()
                ->with(['employee', 'reviewedBy', 'shopStaffPayment.cashbookLine.entry'])
                ->when($selectedShop !== null, fn ($query) => $query->where('shop_id', $selectedShop->id))
                ->when($filterStartDate, fn ($query) => $query->whereDate('requested_on', '>=', $filterStartDate))
                ->when($filterEndDate, fn ($query) => $query->whereDate('requested_on', '<=', $filterEndDate))
                ->latest('id')
                ->paginate(8, ['*'], 'staff_advance_page')
                ->withQueryString(),
            'searchResults' => $this->employeeSearchResults($employeeSearch, $selectedShop?->id),
            'pendingLeaveCount' => EmployeeLeaveRequest::query()
                ->when($selectedShop !== null, fn ($query) => $query->where('submitted_for_shop_id', $selectedShop->id))
                ->where('status', 'pending')
                ->count(),
            'leaveRequests' => EmployeeLeaveRequest::query()
                ->with(['employee.category', 'submittedForShop', 'reviewedBy'])
                ->when($selectedShop !== null, fn ($query) => $query->where('submitted_for_shop_id', $selectedShop->id))
                ->when($filterStartDate, fn ($query) => $query->whereDate('start_date', '>=', $filterStartDate))
                ->when($filterEndDate, fn ($query) => $query->whereDate('start_date', '<=', $filterEndDate))
                ->latest('id')
                ->paginate(8, ['*'], 'staff_leave_page')
                ->withQueryString(),
            'filterStartDate' => $filterStartDate,
            'filterEndDate' => $filterEndDate,
        ]);
    }

    public function storeEmployeeAssignment(StoreShopEmployeeAssignmentRequest $request): RedirectResponse
    {
        abort(404);
    }

    public function storeSalaryPayment(StoreShopStaffSalaryPaymentRequest $request): RedirectResponse
    {
        $this->ensureOwnerAccess($request);

        $shop = Shop::query()->findOrFail($request->integer('shop_id'));
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));

        abort_unless($request->user()->ownedShopAssignments()->where('shop_id', $shop->id)->exists(), 403, 'This shop is outside your scope.');

        $this->employeeAdvanceService->recordShopSalaryPayment(
            $employee,
            $shop,
            round((float) $request->validated('amount'), 2),
            (string) $request->validated('fund_source'),
            Carbon::parse((string) $request->validated('paid_on')),
            $request->user(),
            $request->validated('notes'),
        );

        return redirect()->route('shop-owner.staff.index', ['shop' => $shop->code, 'tab' => 'salary'])
            ->with('success', 'Staff salary payment recorded.');
    }

    public function storeAdvanceRequest(StoreEmployeeAdvanceRequest $request): RedirectResponse
    {
        $this->ensureOwnerAccess($request);

        $shop = Shop::query()->findOrFail($request->integer('shop_id'));
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));

        abort_unless($request->user()->ownedShopAssignments()->where('shop_id', $shop->id)->exists(), 403, 'This shop is outside your scope.');

        $advanceRequest = $this->employeeAdvanceService->requestOrPayAdvance(
            $employee,
            $shop,
            round((float) $request->validated('amount'), 2),
            (string) $request->validated('fund_source'),
            Carbon::parse((string) $request->validated('requested_on')),
            $request->user(),
            $request->validated('request_note'),
        );

        return redirect()->route('shop-owner.staff.index', ['shop' => $shop->code, 'tab' => 'advance'])
            ->with(
                $advanceRequest->status === 'approved' ? 'success' : 'warning',
                $advanceRequest->status === 'approved'
                    ? 'Employee advance paid within HR rule.'
                    : 'Employee advance request sent to HR/admin for approval.',
            );
    }

    public function storeAttendance(UpsertOwnedShopAttendanceRequest $request): RedirectResponse|JsonResponse
    {
        $this->ensureOwnerAccess($request);

        $employee = Employee::query()->findOrFail($request->integer('employee_id'));
        $shop = Shop::query()->findOrFail($request->integer('shop_id'));
        $attendanceDate = Carbon::parse($request->string('attendance_date')->toString());

        abort_unless(
            $this->attendanceService->canOwnerMarkAttendance($request->user(), $employee, $attendanceDate, $shop->id),
            403,
            'You can only mark today attendance for shop staff assigned to your client shops.',
        );

        $notes = $request->string('status')->toString() === 'leave'
            ? $request->string('leave_reason')->toString()
            : $request->input('notes');

        $attendance = $this->attendanceService->upsert(
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
                    'leave_type_id' => $this->defaultPaidLeaveTypeId(),
                    'submitted_by' => $request->user()->id,
                    'status' => 'pending',
                    'reason' => $request->string('leave_reason')->toString(),
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_note' => null,
                ],
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Attendance updated for today.',
                'attendance' => [
                    'employee_id' => $employee->id,
                    'status' => $attendance->status,
                    'status_label' => $attendance->status === 'present' ? 'checked in' : str_replace('_', ' ', $attendance->status),
                    'checked_in_at' => ($attendance->created_at ?? $attendance->marked_at)?->format('h:i A'),
                    'latest_mark_at' => $attendance->marked_at?->format('h:i A'),
                    'changed_at' => $attendance->updated_at?->gt(($attendance->created_at ?? $attendance->updated_at)->copy()->addSecond())
                        ? $attendance->updated_at->format('h:i A')
                        : null,
                    'button_label' => 'Update Check-In',
                ],
            ]);
        }

        return redirect()->route('shop-owner.staff.index', ['shop' => $shop->code, 'tab' => 'attendance'])
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
            'leave_type_id' => $request->integer('leave_type_id'),
            'submitted_by' => $request->user()->id,
            'submitted_for_shop_id' => $shopId,
            'start_date' => $request->date('start_date'),
            'end_date' => $request->date('end_date'),
            'status' => 'pending',
            'submission_type' => 'owner',
            'reason' => $request->string('reason')->toString(),
        ]);

        return redirect()->route('shop-owner.staff.index', ['shop' => $shop->code, 'tab' => 'leave'])->with('success', 'Leave request submitted for admin review.');
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
            'This staff module is available only for client shop assignments.',
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
            ->where('status', 'active')
            ->where(function ($query) use ($selectedDate): void {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $selectedDate->toDateString());
            })
            ->where(function ($query) use ($selectedDate): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $selectedDate->toDateString());
            })
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
            ->where('status', 'active')
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

    /**
     * @return Collection<int, Employee>
     */
    private function advanceEmployeesForShop(?int $shopId, Carbon $selectedDate): Collection
    {
        if ($shopId === null) {
            return collect();
        }

        $employeeIds = ShopEmployeeAssignment::query()
            ->where('shop_id', $shopId)
            ->where(function ($query) use ($selectedDate): void {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $selectedDate->toDateString());
            })
            ->pluck('employee_id')
            ->unique()
            ->values();

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
     * @return array{present_days:float, earned_amount:float, eligible_amount:float, already_advanced_amount:float, available_amount:float, rule_label:string}
     */
    private function advanceOptionForEmployee(Employee $employee, Carbon $selectedDate, ?int $shopId): array
    {
        $eligibility = $this->employeeAdvanceService->eligibility($employee, $selectedDate->copy()->startOfMonth(), $shopId);

        return [
            'present_days' => (float) $eligibility['present_days'],
            'earned_amount' => (float) $eligibility['earned_amount'],
            'eligible_amount' => (float) $eligibility['eligible_amount'],
            'already_advanced_amount' => (float) $eligibility['already_advanced_amount'],
            'available_amount' => (float) $eligibility['available_amount'],
            'rule_label' => (float) $eligibility['rule']->advance_percent.'% after '.(int) $eligibility['rule']->minimum_present_days.' present days',
        ];
    }

    /**
     * @return array{salary_amount:float, paid_amount:float, remaining_amount:float|null}
     */
    private function salaryOptionForEmployee(Employee $employee, Carbon $selectedDate, ?int $shopId): array
    {
        $payrollRunItem = PayrollRunItem::query()
            ->with(['payments', 'shopStaffPayments'])
            ->where('employee_id', $employee->id)
            ->whereHas('payrollRun', fn ($query) => $query->whereDate('period_start', $selectedDate->copy()->startOfMonth()->toDateString()))
            ->first();

        $shopPayable = $shopId !== null
            ? $this->payrollService->payableForAttendance(
                $employee,
                $selectedDate->copy()->startOfMonth(),
                $selectedDate->copy()->endOfMonth(),
                $shopId,
            )
            : ['amount' => 0.0];
        $paidAmount = round((float) ($payrollRunItem?->shopStaffPayments
            ->when($shopId !== null, fn ($payments) => $payments->where('shop_id', $shopId))
            ->sum('amount') ?? 0), 2);

        return [
            'salary_amount' => round((float) $shopPayable['amount'], 2),
            'paid_amount' => $paidAmount,
            'remaining_amount' => $payrollRunItem ? round(max(0, (float) $shopPayable['amount'] - $paidAmount), 2) : null,
        ];
    }

    private function defaultPaidLeaveTypeId(): int
    {
        return (int) LeaveType::query()->firstOrCreate(
            ['code' => LeaveType::CODE_PAID],
            [
                'name' => 'Paid Leave',
                'is_paid' => true,
                'is_active' => true,
                'carry_forward_allowed' => true,
            ],
        )->id;
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function nullableDateRangeFromRequest(Request $request): array
    {
        $startDate = $request->filled('start_date')
            ? Carbon::parse((string) $request->input('start_date'))->startOfDay()
            : null;
        $endDate = $request->filled('end_date')
            ? Carbon::parse((string) $request->input('end_date'))->endOfDay()
            : null;

        if ($startDate && $endDate && $startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        return [$startDate, $endDate];
    }
}
