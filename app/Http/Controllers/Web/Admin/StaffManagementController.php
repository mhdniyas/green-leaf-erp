<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\ReviewEmployeeLeaveRequest;
use App\Http\Requests\Web\Admin\StoreEmployeeCategoryRequest;
use App\Http\Requests\Web\Admin\StoreEmployeeRequest;
use App\Http\Requests\Web\Admin\StorePayrollRunRequest;
use App\Http\Requests\Web\Admin\UpdateEmployeeCategoryRequest;
use App\Http\Requests\Web\Admin\UpdateEmployeeRequest;
use App\Http\Requests\Web\Admin\UpsertEmployeeAttendanceRequest;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\EmployeeLeaveRequest as LeaveRequestModel;
use App\Models\PayrollRun;
use App\Models\Shop;
use App\Models\User;
use App\Services\HR\AttendanceService;
use App\Services\HR\PayrollService;
use App\Services\HR\StaffDirectoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StaffManagementController extends Controller
{
    public function __construct(
        private readonly StaffDirectoryService $staffDirectoryService,
        private readonly AttendanceService $attendanceService,
        private readonly PayrollService $payrollService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Employee::class);

        $selectedDate = Carbon::parse($request->input('date', today()->toDateString()));

        return view('admin.staff.index', [
            'selectedDate' => $selectedDate,
            'stats' => $this->staffDirectoryService->statsForDate($selectedDate),
            'attendanceRecords' => EmployeeAttendance::query()
                ->with(['employee.category', 'shop', 'markedBy'])
                ->whereDate('attendance_date', $selectedDate)
                ->orderBy('attendance_date')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function employeesIndex(Request $request): View
    {
        Gate::authorize('viewAny', Employee::class);

        $selectedDate = Carbon::parse($request->input('date', today()->toDateString()));
        $staffArea = $request->string('staff_area')->toString();
        $categoryId = $request->filled('employee_category_id') ? $request->integer('employee_category_id') : null;
        $search = trim($request->string('search')->toString());
        $categories = EmployeeCategory::query()->where('is_active', true)->orderBy('name')->get();

        return view('admin.staff.employees', [
            'selectedDate' => $selectedDate,
            'search' => $search,
            'employees' => $this->staffDirectoryService->paginateEmployees(
                $staffArea !== '' ? $staffArea : null,
                $categoryId,
                $search !== '' ? $search : null,
            ),
            'categories' => $categories,
            'categoryTabs' => $categories->map(fn (EmployeeCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'count' => $category->employees()->count(),
            ]),
            'shops' => Shop::query()->orderBy('name')->get(),
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function categoriesIndex(): View
    {
        Gate::authorize('viewAny', Employee::class);

        return view('admin.staff.categories', [
            'allCategories' => EmployeeCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function show(Employee $employee, Request $request): View
    {
        Gate::authorize('view', $employee);

        $employee->load(['category', 'defaultShop', 'user']);

        $selectedMonth = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->string('month')->toString())->startOfMonth()
            : today()->startOfMonth();

        $monthEnd = $selectedMonth->copy()->endOfMonth();
        $attendanceRecords = EmployeeAttendance::query()
            ->with(['shop', 'markedBy'])
            ->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$selectedMonth->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->keyBy(fn (EmployeeAttendance $attendance): string => $attendance->attendance_date->toDateString());

        return view('admin.staff.show', [
            'employee' => $employee,
            'selectedMonth' => $selectedMonth,
            'calendarDays' => $this->buildCalendarDays($selectedMonth, $attendanceRecords),
            'monthlySummary' => [
                'present' => $attendanceRecords->where('status', 'present')->count(),
                'half_day' => $attendanceRecords->where('status', 'half_day')->count(),
                'leave' => $attendanceRecords->where('status', 'leave')->count(),
                'absent' => $attendanceRecords->where('status', 'absent')->count(),
            ],
            'attendanceRecords' => $attendanceRecords->sortByDesc(fn (EmployeeAttendance $attendance): string => $attendance->attendance_date->toDateString()),
            'shops' => Shop::query()->orderBy('name')->get(),
            'categories' => EmployeeCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'payrollHistory' => $employee->payrollItems()->with('payrollRun')->latest('id')->limit(6)->get(),
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['is_user_linked'] = filled($validated['user_id'] ?? null);

        Employee::query()->create($validated);

        return redirect()->route('admin.staff.employees.index')->with('success', 'Employee created successfully.');
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validated();
        $validated['is_user_linked'] = filled($validated['user_id'] ?? null);

        $employee->update($validated);

        return redirect()->route('admin.staff.show', $employee)->with('success', 'Employee updated successfully.');
    }

    public function storeCategory(StoreEmployeeCategoryRequest $request): RedirectResponse
    {
        EmployeeCategory::query()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.staff.categories.index')->with('success', 'Payroll category created successfully.');
    }

    public function updateCategory(UpdateEmployeeCategoryRequest $request, EmployeeCategory $employeeCategory): RedirectResponse
    {
        $employeeCategory->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.staff.categories.index')->with('success', 'Payroll category updated successfully.');
    }

    public function attendanceIndex(Request $request): View
    {
        Gate::authorize('viewAny', EmployeeAttendance::class);

        $selectedDate = Carbon::parse($request->input('date', today()->toDateString()));

        return view('admin.staff.attendance', [
            'selectedDate' => $selectedDate,
            'employees' => Employee::query()->with(['category', 'defaultShop'])->orderBy('name')->get(),
            'attendanceRecords' => EmployeeAttendance::query()
                ->with(['employee.category', 'shop', 'markedBy'])
                ->whereDate('attendance_date', $selectedDate)
                ->get()
                ->keyBy('employee_id'),
            'shops' => Shop::query()->orderBy('name')->get(),
        ]);
    }

    public function storeAttendance(UpsertEmployeeAttendanceRequest $request): RedirectResponse
    {
        $employee = Employee::query()->with('category')->findOrFail($request->integer('employee_id'));
        Gate::authorize('create', EmployeeAttendance::class);

        $shop = $request->filled('shop_id') ? Shop::query()->findOrFail($request->integer('shop_id')) : null;

        $this->attendanceService->upsert(
            $employee,
            Carbon::parse($request->string('attendance_date')->toString()),
            $request->string('status')->toString(),
            $request->user(),
            'admin',
            $shop,
            $request->input('notes'),
        );

        if ($request->input('redirect_to') === 'profile') {
            return redirect()->route('admin.staff.show', [
                'employee' => $employee,
                'month' => Carbon::parse($request->string('attendance_date')->toString())->format('Y-m'),
            ])->with('success', 'Attendance saved successfully.');
        }

        return redirect()->route('admin.staff.attendance', ['date' => $request->string('attendance_date')->toString()])
            ->with('success', 'Attendance saved successfully.');
    }

    public function leavesIndex(): View
    {
        Gate::authorize('viewAny', LeaveRequestModel::class);

        return view('admin.staff.leaves', [
            'leaveRequests' => LeaveRequestModel::query()
                ->with(['employee.category', 'submittedBy', 'submittedForShop', 'reviewedBy'])
                ->latest('id')
                ->paginate(12),
        ]);
    }

    public function reviewLeave(ReviewEmployeeLeaveRequest $request, LeaveRequestModel $leaveRequest): RedirectResponse
    {
        $leaveRequest->forceFill([
            'status' => $request->string('status')->toString(),
            'review_note' => $request->input('review_note'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        if ($leaveRequest->status === 'approved') {
            $currentDate = $leaveRequest->start_date->copy();

            while ($currentDate->lte($leaveRequest->end_date)) {
                $this->attendanceService->upsert(
                    $leaveRequest->employee,
                    $currentDate,
                    'leave',
                    $request->user(),
                    'admin',
                    $leaveRequest->submittedForShop,
                    'Auto-marked from approved leave request.',
                );

                $currentDate->addDay();
            }
        }

        return redirect()->route('admin.staff.leaves.index')->with('success', 'Leave request reviewed successfully.');
    }

    public function payrollIndex(): View
    {
        Gate::authorize('viewAny', PayrollRun::class);

        return view('admin.staff.payroll', [
            'payrollRuns' => PayrollRun::query()->with(['items.employee', 'generatedBy', 'journalEntry'])->latest('period_start')->paginate(12),
        ]);
    }

    public function storePayroll(StorePayrollRunRequest $request): RedirectResponse
    {
        $payrollRun = $this->payrollService->generate(
            Carbon::parse($request->string('period_start')->toString()),
            Carbon::parse($request->string('period_end')->toString()),
            $request->user()->id,
        );

        return redirect()->route('admin.staff.payroll.index')
            ->with('success', 'Payroll finalized for '.$payrollRun->period_start->format('F Y').'.');
    }

    /**
     * @param  Collection<string, EmployeeAttendance>  $attendanceRecords
     * @return array<int, array{date: Carbon, is_current_month: bool, attendance: ?EmployeeAttendance}>
     */
    private function buildCalendarDays(Carbon $selectedMonth, Collection $attendanceRecords): array
    {
        $calendarStart = $selectedMonth->copy()->startOfMonth()->startOfWeek();
        $calendarEnd = $selectedMonth->copy()->endOfMonth()->endOfWeek();
        $days = [];
        $cursor = $calendarStart->copy();

        while ($cursor->lte($calendarEnd)) {
            $days[] = [
                'date' => $cursor->copy(),
                'is_current_month' => $cursor->month === $selectedMonth->month,
                'attendance' => $attendanceRecords->get($cursor->toDateString()),
            ];

            $cursor->addDay();
        }

        return $days;
    }
}
