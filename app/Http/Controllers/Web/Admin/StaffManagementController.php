<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Exports\PayrollMonthExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\FinalizePayrollRunRequest;
use App\Http\Requests\Web\Admin\ReviewEmployeeLeaveRequest;
use App\Http\Requests\Web\Admin\StoreAdminEmployeeLeaveRequest;
use App\Http\Requests\Web\Admin\StoreEmployeeCategoryRequest;
use App\Http\Requests\Web\Admin\StoreEmployeeRequest;
use App\Http\Requests\Web\Admin\StorePayrollRunRequest;
use App\Http\Requests\Web\Admin\UpdateEmployeeCategoryRequest;
use App\Http\Requests\Web\Admin\UpdateEmployeeRequest;
use App\Http\Requests\Web\Admin\UpdateEmployeeStatusRequest;
use App\Http\Requests\Web\Admin\UpdatePayrollRunItemRequest;
use App\Http\Requests\Web\Admin\UpsertEmployeeAttendanceRequest;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\EmployeeLeaveRequest as LeaveRequestModel;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Shop;
use App\Models\User;
use App\Services\HR\AttendanceService;
use App\Services\HR\EmployeeSyncService;
use App\Services\HR\PayrollService;
use App\Services\HR\StaffDirectoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StaffManagementController extends Controller
{
    private const PAGE_SIZE = 20;

    public function __construct(
        private readonly StaffDirectoryService $staffDirectoryService,
        private readonly AttendanceService $attendanceService,
        private readonly PayrollService $payrollService,
        private readonly EmployeeSyncService $employeeSyncService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Employee::class);

        $selectedDate = Carbon::parse($request->input('date', today()->toDateString()));
        $attendanceRecords = EmployeeAttendance::query()
            ->with(['employee.category', 'shop', 'markedBy'])
            ->whereDate('attendance_date', $selectedDate)
            ->orderBy('attendance_date')
            ->orderBy('id')
            ->get();
        $ownedShops = Shop::query()->ownedForStaff()->orderBy('name')->get();
        $shopCards = $ownedShops->map(function (Shop $shop) use ($attendanceRecords): array {
            $records = $attendanceRecords
                ->where('shop_id', $shop->id)
                ->sortBy(fn (EmployeeAttendance $attendance): string => $attendance->employee->name)
                ->values();

            return [
                'shop' => $shop,
                'records' => $records,
                'present_count' => $records->where('status', 'present')->count(),
                'half_day_count' => $records->where('status', 'half_day')->count(),
                'leave_count' => $records->where('status', 'leave')->count(),
            ];
        });
        $officeRecords = $attendanceRecords
            ->filter(fn (EmployeeAttendance $attendance): bool => $attendance->shop_id === null && $attendance->employee->staff_area === 'office')
            ->sortBy(fn (EmployeeAttendance $attendance): string => $attendance->employee->name)
            ->values();

        return view('admin.staff.index', [
            'selectedDate' => $selectedDate,
            'stats' => $this->staffDirectoryService->statsForDate($selectedDate),
            'attendanceRecords' => $attendanceRecords,
            'shopCards' => $shopCards,
            'officeRecords' => $officeRecords,
        ]);
    }

    public function employeesIndex(Request $request): View
    {
        Gate::authorize('viewAny', Employee::class);

        $selectedDate = Carbon::parse($request->input('date', today()->toDateString()));
        $staffArea = $request->string('staff_area')->toString();
        $categoryCode = trim($request->string('category')->toString());
        $search = trim($request->string('search')->toString());
        $categories = EmployeeCategory::query()->where('is_active', true)->orderBy('name')->get();
        $selectedCategory = $categoryCode !== ''
            ? $categories->firstWhere('code', $categoryCode)
            : null;
        $ownedShops = Shop::query()->ownedForStaff()->orderBy('name')->get();

        return view('admin.staff.employees', [
            'selectedDate' => $selectedDate,
            'search' => $search,
            'employees' => $this->staffDirectoryService->paginateEmployees(
                $staffArea !== '' ? $staffArea : null,
                $selectedCategory?->code,
                $search !== '' ? $search : null,
            ),
            'categories' => $categories,
            'categoryTabs' => $categories->map(fn (EmployeeCategory $category): array => [
                'code' => $category->code,
                'name' => $category->name,
                'count' => $category->employees()->count(),
            ]),
            'selectedCategory' => $selectedCategory,
            'shops' => $ownedShops,
            'users' => User::query()->with('roles')->orderBy('name')->get(),
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

        $employee->load(['category', 'defaultShop', 'user.roles', 'user.ownedShopAssignments.shop', 'assignedShops']);

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
        $ownedShops = Shop::query()->ownedForStaff()->orderBy('name')->get();

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
            'workedShops' => Shop::query()
                ->whereIn('id', EmployeeAttendance::query()->where('employee_id', $employee->id)->whereNotNull('shop_id')->distinct()->pluck('shop_id'))
                ->ownedForStaff()
                ->orderBy('name')
                ->get(),
            'shops' => $ownedShops,
            'categories' => EmployeeCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'payrollHistory' => $employee->payrollItems()->with('payrollRun')->latest('id')->limit(6)->get(),
            'leaveRequests' => $employee->leaveRequests()->with(['submittedBy.roles', 'submittedForShop', 'reviewedBy'])->latest('id')->limit(8)->get(),
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

    public function updateEmploymentStatus(UpdateEmployeeStatusRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update([
            'employment_status' => $request->string('employment_status')->toString(),
        ]);

        return redirect()->route('admin.staff.show', $employee)
            ->with('success', 'Employee status updated successfully.');
    }

    public function syncLinkedUsers(Request $request): RedirectResponse
    {
        Gate::authorize('create', Employee::class);

        $syncedEmployees = $this->employeeSyncService->syncExistingUsers();

        return redirect()->route('admin.staff.employees.index')
            ->with('success', sprintf('%d linked user staff records were re-synced.', $syncedEmployees->count()));
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
        $validated = $request->validated();

        if ($employeeCategory->isCoreCategory()) {
            unset($validated['name'], $validated['code'], $validated['staff_area']);
        }

        $employeeCategory->update([
            ...$validated,
            'is_active' => $employeeCategory->isCoreCategory() ? true : $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.staff.categories.index')->with('success', 'Payroll category updated successfully.');
    }

    public function attendanceIndex(Request $request): View
    {
        Gate::authorize('viewAny', EmployeeAttendance::class);

        $selectedDate = Carbon::parse($request->input('date', today()->toDateString()));
        $staffArea = trim($request->string('staff_area')->toString());
        $categoryCode = trim($request->string('category')->toString());
        $search = trim($request->string('search')->toString());
        $selectedStatus = trim($request->string('status')->toString());
        $shopId = $request->integer('shop_id');
        $categories = EmployeeCategory::query()->where('is_active', true)->orderBy('name')->get();
        $selectedCategory = $categoryCode !== ''
            ? $categories->firstWhere('code', $categoryCode)
            : null;
        $ownedShops = Shop::query()->ownedForStaff()->orderBy('name')->get();

        $employeeBaseQuery = Employee::query()
            ->with(['category', 'defaultShop'])
            ->when($staffArea !== '', fn ($query) => $query->where('staff_area', $staffArea))
            ->when($selectedCategory !== null, fn ($query) => $query->where('employee_category_id', $selectedCategory->id))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($employeeQuery) use ($search): void {
                    $employeeQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('employee_code', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($shopId > 0, function ($query) use ($shopId): void {
                $query->where(function ($employeeQuery) use ($shopId): void {
                    $employeeQuery
                        ->where('default_shop_id', $shopId)
                        ->orWhereHas('attendances', function ($attendanceQuery) use ($shopId): void {
                            $attendanceQuery->where('shop_id', $shopId);
                        });
                });
            })
            ->orderBy('name');

        $statusAttendanceRecords = EmployeeAttendance::query()
            ->with(['employee.category', 'shop', 'markedBy'])
            ->whereDate('attendance_date', $selectedDate)
            ->when($shopId > 0, fn ($query) => $query->where('shop_id', $shopId))
            ->when($selectedCategory !== null, function ($query) use ($selectedCategory): void {
                $query->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('employee_category_id', $selectedCategory->id));
            })
            ->when($staffArea !== '', function ($query) use ($staffArea): void {
                $query->whereHas('employee', fn ($employeeQuery) => $employeeQuery->where('staff_area', $staffArea));
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('employee', function ($employeeQuery) use ($search): void {
                    $employeeQuery->where(function ($searchQuery) use ($search): void {
                        $searchQuery
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('employee_code', 'like', '%'.$search.'%')
                            ->orWhere('phone', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
                });
            })
            ->get();

        $statusCounts = [
            'present' => $statusAttendanceRecords->where('status', 'present')->count(),
            'half_day' => $statusAttendanceRecords->where('status', 'half_day')->count(),
            'leave' => $statusAttendanceRecords->where('status', 'leave')->count(),
            'absent' => max(0, (clone $employeeBaseQuery)->count() - $statusAttendanceRecords->whereIn('status', ['present', 'half_day', 'leave'])->count()),
        ];

        $employeesQuery = clone $employeeBaseQuery;

        if (in_array($selectedStatus, ['present', 'half_day', 'leave'], true)) {
            $employeesQuery->whereHas('attendances', function (Builder $query) use ($selectedDate, $shopId, $selectedStatus): void {
                $this->applyAttendanceDateScope($query, $selectedDate, $shopId);
                $query->where('status', $selectedStatus);
            });
        }

        if ($selectedStatus === 'absent') {
            $employeesQuery->where(function (Builder $query) use ($selectedDate, $shopId): void {
                $query
                    ->whereHas('attendances', function (Builder $attendanceQuery) use ($selectedDate, $shopId): void {
                        $this->applyAttendanceDateScope($attendanceQuery, $selectedDate, $shopId);
                        $attendanceQuery->where('status', 'absent');
                    })
                    ->orWhereDoesntHave('attendances', function (Builder $attendanceQuery) use ($selectedDate, $shopId): void {
                        $this->applyAttendanceDateScope($attendanceQuery, $selectedDate, $shopId);
                    });
            });
        }

        $employees = $employeesQuery
            ->paginate(self::PAGE_SIZE)
            ->withQueryString();

        $attendanceRecords = EmployeeAttendance::query()
            ->with(['employee.category', 'shop', 'markedBy'])
            ->whereDate('attendance_date', $selectedDate)
            ->whereIn('employee_id', $employees->getCollection()->pluck('id'))
            ->when($shopId > 0, fn ($query) => $query->where('shop_id', $shopId))
            ->get()
            ->keyBy('employee_id');

        return view('admin.staff.attendance', [
            'selectedDate' => $selectedDate,
            'search' => $search,
            'selectedStatus' => $selectedStatus,
            'statusCounts' => $statusCounts,
            'selectedShopId' => $shopId > 0 ? $shopId : null,
            'selectedStaffArea' => $staffArea,
            'selectedCategory' => $selectedCategory,
            'categories' => $categories,
            'employees' => $employees,
            'attendanceRecords' => $attendanceRecords,
            'shops' => $ownedShops,
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
            'employees' => Employee::query()
                ->with(['category', 'defaultShop'])
                ->orderBy('name')
                ->get(),
            'shops' => Shop::query()->ownedForStaff()->orderBy('name')->get(),
            'leaveRequests' => LeaveRequestModel::query()
                ->with(['employee.category', 'submittedBy', 'submittedForShop', 'reviewedBy'])
                ->latest('id')
                ->paginate(self::PAGE_SIZE)
                ->withQueryString(),
        ]);
    }

    public function storeLeave(StoreAdminEmployeeLeaveRequest $request): RedirectResponse
    {
        $employee = Employee::query()->with('defaultShop')->findOrFail($request->integer('employee_id'));
        $shopId = $employee->staff_area === 'shop'
            ? ($request->filled('shop_id') ? $request->integer('shop_id') : $employee->default_shop_id)
            : null;

        LeaveRequestModel::query()->create([
            'employee_id' => $employee->id,
            'submitted_by' => $request->user()->id,
            'submitted_for_shop_id' => $shopId,
            'start_date' => $request->date('start_date'),
            'end_date' => $request->date('end_date'),
            'status' => 'pending',
            'submission_type' => 'admin',
            'reason' => $request->string('reason')->toString(),
        ]);

        return redirect()->route('admin.staff.leaves.index')->with('success', 'Leave request submitted for HR review.');
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

    public function payrollIndex(Request $request): View
    {
        Gate::authorize('viewAny', PayrollRun::class);

        $selectedPayrollMonth = $this->resolvePayrollMonth($request->string('payroll_month')->toString());
        $monthStart = $selectedPayrollMonth->copy()->startOfMonth();
        $monthEnd = $selectedPayrollMonth->copy()->endOfMonth();

        return view('admin.staff.payroll', [
            'selectedPayrollMonth' => $selectedPayrollMonth,
            'payrollRuns' => PayrollRun::query()
                ->with(['items.employee', 'items.category', 'generatedBy', 'finalizedBy', 'journalEntry'])
                ->whereBetween('period_start', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->latest('period_start')
                ->paginate(self::PAGE_SIZE)
                ->withQueryString(),
        ]);
    }

    public function storePayroll(StorePayrollRunRequest $request): RedirectResponse
    {
        $payrollRun = $this->payrollService->generate(
            Carbon::parse((string) $request->validated('period_start')),
            Carbon::parse((string) $request->validated('period_end')),
            $request->user()->id,
        );

        return redirect()->route('admin.staff.payroll.index', [
            'payroll_month' => $payrollRun->period_start->format('Y-m'),
        ])
            ->with('success', 'Payroll draft generated for '.$payrollRun->period_start->format('F Y').'. Review and finalize when ready.');
    }

    public function updatePayrollItem(UpdatePayrollRunItemRequest $request, PayrollRun $payrollRun, PayrollRunItem $payrollRunItem): RedirectResponse
    {
        abort_unless($payrollRunItem->payroll_run_id === $payrollRun->id, 404);

        $this->payrollService->updateOverride(
            $payrollRunItem,
            $request->filled('override_amount') ? (float) $request->input('override_amount') : null,
        );

        return redirect()->route('admin.staff.payroll.index', [
            'payroll_month' => $payrollRun->period_start->format('Y-m'),
        ])
            ->with('success', 'Payroll override updated.');
    }

    public function finalizePayroll(FinalizePayrollRunRequest $request, PayrollRun $payrollRun): RedirectResponse
    {
        $finalizedRun = $this->payrollService->finalize($payrollRun, $request->user()->id);

        return redirect()->route('admin.staff.payroll.index', [
            'payroll_month' => $finalizedRun->period_start->format('Y-m'),
        ])
            ->with('success', 'Payroll finalized for '.$finalizedRun->period_start->format('F Y').'.');
    }

    public function exportPayrollExcel(Request $request): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('viewAny', PayrollRun::class);

        $payrollRun = $this->findPayrollRunForMonth($request);

        if ($payrollRun === null) {
            return redirect()->route('admin.staff.payroll.index', [
                'payroll_month' => $this->resolvePayrollMonth($request->string('payroll_month')->toString())->format('Y-m'),
            ])->with('error', 'No payroll run exists for the selected month yet.');
        }

        return Excel::download(
            new PayrollMonthExport($payrollRun),
            'staff-payroll-'.$payrollRun->period_start->format('Y-m').'.xlsx',
        );
    }

    public function exportPayrollPdf(Request $request): View|RedirectResponse
    {
        Gate::authorize('viewAny', PayrollRun::class);

        $payrollRun = $this->findPayrollRunForMonth($request);

        if ($payrollRun === null) {
            return redirect()->route('admin.staff.payroll.index', [
                'payroll_month' => $this->resolvePayrollMonth($request->string('payroll_month')->toString())->format('Y-m'),
            ])->with('error', 'No payroll run exists for the selected month yet.');
        }

        return view('admin.staff.payroll-pdf', [
            'payrollRun' => $payrollRun,
            'selectedPayrollMonth' => $payrollRun->period_start->copy()->startOfMonth(),
        ]);
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

    private function applyAttendanceDateScope(Builder $query, Carbon $selectedDate, int $shopId): void
    {
        $query->whereDate('attendance_date', $selectedDate->toDateString());

        if ($shopId > 0) {
            $query->where('shop_id', $shopId);
        }
    }

    private function resolvePayrollMonth(string $payrollMonth): Carbon
    {
        if ($payrollMonth !== '') {
            return Carbon::createFromFormat('Y-m', $payrollMonth)->startOfMonth();
        }

        return today()->startOfMonth();
    }

    private function findPayrollRunForMonth(Request $request): ?PayrollRun
    {
        $selectedPayrollMonth = $this->resolvePayrollMonth($request->string('payroll_month')->toString());

        return PayrollRun::query()
            ->with(['items.employee', 'items.category', 'generatedBy', 'finalizedBy', 'journalEntry'])
            ->whereDate('period_start', $selectedPayrollMonth->toDateString())
            ->latest('id')
            ->first();
    }
}
