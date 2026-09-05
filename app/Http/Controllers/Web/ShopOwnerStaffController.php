<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ShopOwner\StoreEmployeeAdvanceRequest;
use App\Http\Requests\Web\ShopOwner\StoreEmployeeLeaveRequest;
use App\Http\Requests\Web\ShopOwner\StoreShopEmployeeAssignmentRequest;
use App\Http\Requests\Web\ShopOwner\StoreShopOwnerEmployeeRequest;
use App\Http\Requests\Web\ShopOwner\StoreShopStaffSalaryPaymentRequest;
use App\Http\Requests\Web\ShopOwner\UpsertOwnedShopAttendanceRequest;
use App\Models\Employee;
use App\Models\EmployeeAdvanceRequest;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\EmployeeLeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollRunItem;
use App\Models\Shop;
use App\Models\ShopEmployeeAssignment;
use App\Models\ShopStaffPayment;
use App\Services\HR\AttendanceService;
use App\Services\HR\EmployeeAdvanceService;
use App\Services\HR\ImageUploadService;
use App\Services\HR\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ShopOwnerStaffController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly EmployeeAdvanceService $employeeAdvanceService,
        private readonly PayrollService $payrollService,
        private readonly ImageUploadService $imageUploadService,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureOwnerAccess($request);

        $rawMonth = trim($request->string('month')->toString());
        $calendarMonth = null;
        if ($rawMonth !== '' && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $rawMonth)) {
            try {
                $calendarMonth = Carbon::createFromFormat('Y-m', $rawMonth)->startOfMonth();
            } catch (\Throwable) {
                $calendarMonth = null;
            }
        }

        $rawDate = trim($request->string('date')->toString());
        $selectedDate = null;
        if ($rawDate !== '' && preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $rawDate)) {
            try {
                $selectedDate = Carbon::createFromFormat('Y-m-d', $rawDate)->startOfDay();
            } catch (\Throwable) {
                $selectedDate = null;
            }
        }

        if ($selectedDate === null) {
            $selectedDate = today()->startOfDay();
        }

        if ($calendarMonth === null) {
            $calendarMonth = $selectedDate->copy()->startOfMonth();
        }

        if ($selectedDate->format('Y-m') !== $calendarMonth->format('Y-m')) {
            if (today()->format('Y-m') === $calendarMonth->format('Y-m')) {
                $selectedDate = today()->startOfDay();
            } else {
                $selectedDate = $calendarMonth->copy()->startOfMonth();
            }
        }

        $ownedShops = Shop::query()
            ->whereIn('id', $request->user()->ownedShopAssignments()->pluck('shop_id'))
            ->orderBy('name')
            ->get();
        $selectedShop = $this->selectedShop($ownedShops, $request->string('shop')->toString());
        $selectedTab = in_array($request->string('tab', 'attendance')->toString(), ['attendance', 'staff', 'advance', 'salary', 'leave', 'history'], true)
            ? $request->string('tab', 'attendance')->toString()
            : 'attendance';
        [$filterStartDate, $filterEndDate] = $this->nullableDateRangeFromRequest($request);
        $employeeSearch = trim($request->string('employee_search')->toString());
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

        $salaryOptions = [];
        $recentPayrollPayments = new LengthAwarePaginator([], 0, 8);

        if ($selectedTab === 'salary') {
            $salaryOptions = $quickEmployees
                ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $this->salaryOptionForEmployee($employee, $selectedDate, $selectedShop?->id)])
                ->all();

            $recentPayrollPayments = ShopStaffPayment::query()
                ->with(['employee', 'advanceRequest', 'cashbookLine.entry'])
                ->when($selectedShop !== null, fn ($query) => $query->where('shop_id', $selectedShop->id))
                ->when($filterStartDate, fn ($query) => $query->whereDate('paid_on', '>=', $filterStartDate))
                ->when($filterEndDate, fn ($query) => $query->whereDate('paid_on', '<=', $filterEndDate))
                ->latest('paid_on')
                ->latest('id')
                ->paginate(8, ['*'], 'staff_payments_page')
                ->withQueryString();
        }

        $historyDatesWithAttendance = collect();
        $historyDayAttendance = collect();

        if ($selectedTab === 'history' && $selectedShop !== null) {
            $startOfMonth = $calendarMonth->copy()->startOfMonth()->toDateString();
            $endOfMonth = $calendarMonth->copy()->endOfMonth()->toDateString();

            $historyDatesWithAttendance = EmployeeAttendance::query()
                ->where('shop_id', $selectedShop->id)
                ->whereBetween('attendance_date', [$startOfMonth, $endOfMonth])
                ->pluck('attendance_date')
                ->map(fn ($d): string => $d instanceof Carbon ? $d->format('Y-m-d') : substr((string) $d, 0, 10))
                ->unique()
                ->values();

            $historyDayAttendance = EmployeeAttendance::query()
                ->with(['employee.category', 'markedBy'])
                ->where('shop_id', $selectedShop->id)
                ->whereDate('attendance_date', $selectedDate->toDateString())
                ->orderBy('id')
                ->get();
        }

        return view('shop-owner.staff.index', [
            'selectedDate' => $selectedDate,
            'calendarMonth' => $calendarMonth,
            'selectedTab' => $selectedTab,
            'shops' => $ownedShops,
            'selectedShop' => $selectedShop,
            'employeeSearch' => $employeeSearch,
            'employees' => $quickEmployees,
            'advanceEmployees' => $advanceEmployees,
            'advanceOptions' => $advanceOptions,
            'salaryOptions' => $salaryOptions,
            'attendanceRecords' => $attendanceRecords,
            'historyDatesWithAttendance' => $historyDatesWithAttendance,
            'historyDayAttendance' => $historyDayAttendance,
            'recentPayrollPayments' => $recentPayrollPayments,
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
            'pendingEmployees' => $this->pendingEmployeesForShop($selectedShop?->id),
            'isAttendanceOpen' => $this->attendanceService->isShopAttendanceOpen(attendanceDate: $selectedDate),
            'cutoffFormatted' => $this->attendanceService->formattedShopAttendanceCutoffTime(),
        ]);
    }

    public function createEmployee(Request $request): View
    {
        $this->ensureOwnerAccess($request);

        $ownedShops = Shop::query()
            ->whereIn('id', $request->user()->ownedShopAssignments()->pluck('shop_id'))
            ->orderBy('name')
            ->get();
        $selectedShop = $this->selectedShop($ownedShops, $request->string('shop')->toString());

        $categories = EmployeeCategory::query()
            ->where('staff_area', 'shop')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('shop-owner.staff.create', [
            'selectedShop' => $selectedShop,
            'shops' => $ownedShops,
            'categories' => $categories,
            'employee' => null,
        ]);
    }

    public function storeEmployee(StoreShopOwnerEmployeeRequest $request): RedirectResponse
    {
        $this->ensureOwnerAccess($request);

        $validated = $request->validated();
        $ownedShops = $request->user()->ownedShopAssignments()->pluck('shop_id');
        $shopId = $request->integer('shop_id');

        if (! $ownedShops->contains($shopId)) {
            $shopId = (int) $ownedShops->first();
        }

        $shop = Shop::query()->findOrFail($shopId);

        $submissionLock = hash('sha256', implode('|', [
            $request->user()->id,
            $shop->id,
            $validated['id_type'],
            mb_strtoupper(trim($validated['id_number'])),
        ]));

        return Cache::lock("shop-owner-staff-submission:{$submissionLock}", 10)->block(5, function () use ($request, $validated, $shop): RedirectResponse {
            $alreadySubmitted = Employee::query()
                ->where('default_shop_id', $shop->id)
                ->where('submitted_by', $request->user()->id)
                ->where('verification_status', 'pending')
                ->where('id_type', $validated['id_type'])
                ->where('id_number', $validated['id_number'])
                ->exists();

            if ($alreadySubmitted) {
                return redirect()->route('shop-owner.staff.index', ['shop' => $shop->code])
                    ->with('warning', 'This employee is already waiting for HR approval.');
            }

            $photoPath = $request->filled('photo_data_url')
                ? $this->imageUploadService->processAndStore((string) $request->input('photo_data_url'), 'employees/photos', 600)
                : null;

            $idFrontPath = $request->filled('id_front_data_url')
                ? $this->imageUploadService->processAndStore((string) $request->input('id_front_data_url'), 'employees/ids', 1200)
                : null;

            $idBackPath = $request->filled('id_back_data_url')
                ? $this->imageUploadService->processAndStore((string) $request->input('id_back_data_url'), 'employees/ids', 1200)
                : null;

            Employee::create([
                'employee_code' => Employee::generateNextCode(),
                'user_id' => null,
                'default_shop_id' => $shop->id,
                'employee_category_id' => null,
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'alternate_phone' => $validated['alternate_phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'photo_path' => $photoPath,
                'id_type' => $validated['id_type'],
                'other_id_type' => $validated['other_id_type'] ?? null,
                'id_number' => $validated['id_number'],
                'id_front_path' => $idFrontPath,
                'id_back_path' => $idBackPath,
                'address' => $validated['address'],
                'staff_area' => 'shop',
                'employment_status' => 'active',
                'verification_status' => 'pending',
                'submitted_by' => $request->user()->id,
                'joined_on' => $validated['joined_on'],
                'salary_type' => $validated['salary_type'],
                'monthly_salary' => $validated['salary_type'] === 'monthly' ? (float) $validated['monthly_salary'] : null,
                'daily_wage' => $validated['salary_type'] === 'daily_wage' ? (float) $validated['daily_wage'] : null,
                'notes' => $validated['notes'] ?? null,
            ]);

            return redirect()->route('shop-owner.staff.index', ['shop' => $shop->code])
                ->with('success', 'Employee submitted for HR approval.');
        });
    }

    public function editEmployeeSubmission(Request $request, Employee $employee): View
    {
        $this->ensureOwnerAccess($request);

        $ownedShops = $request->user()->ownedShopAssignments()->pluck('shop_id');
        abort_unless($ownedShops->contains($employee->default_shop_id), 403, 'Unauthorized submission edit.');
        abort_unless(in_array($employee->verification_status, ['pending', 'rejected'], true), 403, 'Only pending or rejected submissions can be edited.');

        $ownedShopsModels = Shop::query()->whereIn('id', $ownedShops)->orderBy('name')->get();

        return view('shop-owner.staff.create', [
            'selectedShop' => $employee->defaultShop,
            'shops' => $ownedShopsModels,
            'employee' => $employee,
        ]);
    }

    public function resubmitEmployee(StoreShopOwnerEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->ensureOwnerAccess($request);

        $ownedShops = $request->user()->ownedShopAssignments()->pluck('shop_id');
        abort_unless($ownedShops->contains($employee->default_shop_id), 403, 'Unauthorized submission edit.');
        abort_unless(in_array($employee->verification_status, ['pending', 'rejected'], true), 403, 'Only pending or rejected submissions can be edited.');

        $validated = $request->validated();

        $photoPath = $request->filled('photo_data_url')
            ? $this->imageUploadService->processAndStore((string) $request->input('photo_data_url'), 'employees/photos', 600)
            : $employee->photo_path;

        $idFrontPath = $request->filled('id_front_data_url')
            ? $this->imageUploadService->processAndStore((string) $request->input('id_front_data_url'), 'employees/ids', 1200)
            : $employee->id_front_path;

        $idBackPath = $request->filled('id_back_data_url')
            ? $this->imageUploadService->processAndStore((string) $request->input('id_back_data_url'), 'employees/ids', 1200)
            : $employee->id_back_path;

        $employee->update([
            'employee_category_id' => null,
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'alternate_phone' => $validated['alternate_phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'photo_path' => $photoPath,
            'id_type' => $validated['id_type'],
            'other_id_type' => $validated['other_id_type'] ?? null,
            'id_number' => $validated['id_number'],
            'id_front_path' => $idFrontPath,
            'id_back_path' => $idBackPath,
            'address' => $validated['address'],
            'verification_status' => 'pending',
            'rejection_reason' => null,
            'submitted_by' => $request->user()->id,
            'joined_on' => $validated['joined_on'],
            'salary_type' => $validated['salary_type'],
            'monthly_salary' => $validated['salary_type'] === 'monthly' ? (float) $validated['monthly_salary'] : null,
            'daily_wage' => $validated['salary_type'] === 'daily_wage' ? (float) $validated['daily_wage'] : null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('shop-owner.staff.index', ['shop' => $employee->defaultShop?->code])
            ->with('success', 'Employee resubmitted for HR approval.');
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

        if (! $this->attendanceService->isShopAttendanceOpen(attendanceDate: $attendanceDate)) {
            $cutoffFormatted = $this->attendanceService->formattedShopAttendanceCutoffTime();
            $message = "Attendance marking closed at {$cutoffFormatted}. Contact HR for corrections.";
            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->back()->with('error', $message);
        }

        abort_unless(
            $this->attendanceService->canOwnerMarkAttendance($request->user(), $employee, $attendanceDate, $shop->id),
            403,
            'You can only mark today attendance for shop staff assigned to your client shops.',
        );

        $status = $request->string('status')->toString();
        $notes = $status === 'leave'
            ? ($request->input('leave_reason') ?: $request->input('notes'))
            : $request->input('notes');

        if ($status === 'present') {
            $notes = null;
        }

        $attendance = $this->attendanceService->upsert(
            $employee,
            $attendanceDate,
            $status,
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

        if ($request->expectsJson() || $request->wantsJson()) {
            $markedAtStr = $attendance->marked_at?->timezone('Asia/Kolkata')->format('g:i A') ?? now('Asia/Kolkata')->format('g:i A');
            $statusLabel = match ($attendance->status) {
                'present' => '✓ Present',
                'half_day' => 'Half Day',
                'leave' => 'Leave',
                'absent' => 'Absent',
                default => str_replace('_', ' ', ucfirst($attendance->status)),
            };

            return response()->json([
                'message' => 'Attendance updated for today.',
                'attendance' => [
                    'employee_id' => $employee->id,
                    'status' => $attendance->status,
                    'status_label' => $statusLabel,
                    'marked_at' => $markedAtStr,
                    'notes' => $attendance->notes,
                    'checked_in_at' => $markedAtStr,
                    'latest_mark_at' => $markedAtStr,
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

        $defaultShopEmployeeIds = Employee::query()
            ->approved()
            ->where('default_shop_id', $shopId)
            ->where('staff_area', 'shop')
            ->where('employment_status', 'active')
            ->pluck('id');

        $employeeIds = $assignedEmployeeIds
            ->merge($defaultShopEmployeeIds)
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

        $assignedEmployeeIds = ShopEmployeeAssignment::query()
            ->where('shop_id', $shopId)
            ->where(function ($query) use ($selectedDate): void {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $selectedDate->toDateString());
            })
            ->pluck('employee_id');

        $defaultShopEmployeeIds = Employee::query()
            ->where('default_shop_id', $shopId)
            ->where('staff_area', 'shop')
            ->where('employment_status', 'active')
            ->pluck('id');

        $employeeIds = $assignedEmployeeIds
            ->merge($defaultShopEmployeeIds)
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

    /**
     * @return Collection<int, Employee>
     */
    private function pendingEmployeesForShop(?int $shopId): Collection
    {
        if ($shopId === null) {
            return collect();
        }

        return Employee::query()
            ->with(['category', 'submittedBy', 'reviewedBy'])
            ->where('default_shop_id', $shopId)
            ->whereIn('verification_status', ['pending', 'rejected'])
            ->orderByDesc('id')
            ->get();
    }
}
