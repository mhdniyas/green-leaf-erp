<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Exports\PayrollMonthExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\FinalizePayrollRunRequest;
use App\Http\Requests\Web\Admin\ReviewEmployeeAdvanceRequest;
use App\Http\Requests\Web\Admin\ReviewEmployeeLeaveRequest;
use App\Http\Requests\Web\Admin\StoreAdminEmployeeLeaveRequest;
use App\Http\Requests\Web\Admin\StoreContractWorkerPaymentRequest;
use App\Http\Requests\Web\Admin\StoreEmployeeCategoryRequest;
use App\Http\Requests\Web\Admin\StoreEmployeeRequest;
use App\Http\Requests\Web\Admin\StorePayrollPaymentRequest;
use App\Http\Requests\Web\Admin\StorePayrollRunRequest;
use App\Http\Requests\Web\Admin\StoreShopEmployeeAssignmentRequest;
use App\Http\Requests\Web\Admin\StoreShopStaffPaymentRequest;
use App\Http\Requests\Web\Admin\UpdateEmployeeCategoryLeaveRulesRequest;
use App\Http\Requests\Web\Admin\UpdateEmployeeCategoryRequest;
use App\Http\Requests\Web\Admin\UpdateEmployeeRequest;
use App\Http\Requests\Web\Admin\UpdateEmployeeStatusRequest;
use App\Http\Requests\Web\Admin\UpdatePayrollRunItemRequest;
use App\Http\Requests\Web\Admin\UpdateStaffCheckInTimeRequest;
use App\Http\Requests\Web\Admin\UpsertEmployeeAttendanceRequest;
use App\Models\BusinessSetting;
use App\Models\Cashbook\CompanyAccount;
use App\Models\ContractWorkerPayment;
use App\Models\Employee;
use App\Models\EmployeeAdvanceRequest;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCategory;
use App\Models\EmployeeCategoryLeaveRule;
use App\Models\EmployeeLeaveRequest as LeaveRequestModel;
use App\Models\LeaveType;
use App\Models\PayrollPayment;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Shop;
use App\Models\ShopEmployeeAssignment;
use App\Models\ShopStaffPayment;
use App\Models\User;
use App\Services\HR\AttendanceService;
use App\Services\HR\ContractWorkerPaymentService;
use App\Services\HR\EmployeeAdvanceService;
use App\Services\HR\HrOverrideService;
use App\Services\HR\ImageUploadService;
use App\Services\HR\LeaveLedgerService;
use App\Services\HR\PayrollPaymentService;
use App\Services\HR\PayrollService;
use App\Services\HR\ShopEmployeeAssignmentService;
use App\Services\HR\StaffDirectoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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
        private readonly PayrollPaymentService $payrollPaymentService,
        private readonly LeaveLedgerService $leaveLedgerService,
        private readonly HrOverrideService $hrOverrideService,
        private readonly ShopEmployeeAssignmentService $shopEmployeeAssignmentService,
        private readonly EmployeeAdvanceService $employeeAdvanceService,
        private readonly ContractWorkerPaymentService $contractWorkerPaymentService,
        private readonly ImageUploadService $imageUploadService,
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

        $pendingCount = Employee::query()->pending()->count();
        $pendingEmployees = Employee::query()
            ->pending()
            ->with(['category', 'defaultShop', 'submittedBy'])
            ->orderByDesc('id')
            ->get();

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
            'pendingCount' => $pendingCount,
            'pendingEmployees' => $pendingEmployees,
            'selectedTab' => $request->string('tab', 'all')->toString(),
        ]);
    }

    public function assignmentsIndex(Request $request): View
    {
        Gate::authorize('viewAny', Employee::class);

        $selectedDate = Carbon::parse($request->input('date', today()->toDateString()));
        $search = trim((string) $request->input('search', ''));
        $categoryCode = trim((string) $request->input('category', 'all'));
        $allocationFilter = trim((string) $request->input('allocation', 'all'));
        $filterShopId = $request->integer('shop_id');

        $categories = EmployeeCategory::query()->where('is_active', true)->orderBy('name')->get();
        $selectedCategory = $categoryCode !== 'all' && $categoryCode !== ''
            ? $categories->firstWhere('code', $categoryCode)
            : null;

        // Base query for counts: approved active employees
        $approvedActiveBaseQuery = Employee::query()->approved()->where('employment_status', 'active');

        $totalStaffCount = (clone $approvedActiveBaseQuery)->count();
        $allocatedCount = (clone $approvedActiveBaseQuery)->whereNotNull('default_shop_id')->count();
        $unallocatedCount = max(0, $totalStaffCount - $allocatedCount);
        $pendingCount = Employee::query()->pending()->count();

        $categoryTabs = $categories->map(function (EmployeeCategory $category) use ($approvedActiveBaseQuery): array {
            return [
                'code' => $category->code,
                'name' => $category->name,
                'count' => (clone $approvedActiveBaseQuery)->where('employee_category_id', $category->id)->count(),
            ];
        });

        // Employee directory list
        $employeesQuery = Employee::query()
            ->approved()
            ->where('employment_status', 'active')
            ->with(['category', 'defaultShop', 'shopAssignments.shop'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', '%'.$search.'%')
                        ->orWhere('employee_code', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                });
            })
            ->when($selectedCategory !== null, fn ($query) => $query->where('employee_category_id', $selectedCategory->id))
            ->when($allocationFilter === 'allocated', fn ($query) => $query->whereNotNull('default_shop_id'))
            ->when($allocationFilter === 'unallocated', fn ($query) => $query->whereNull('default_shop_id'))
            ->orderBy('name');

        $employees = $employeesQuery->paginate(25)->withQueryString();

        $ownedShops = Shop::query()->ownedForStaff()->orderBy('name')->get();
        $selectedFilterShop = $filterShopId > 0 ? $ownedShops->firstWhere('id', $filterShopId) : null;

        $dateShopStaff = collect();
        if ($selectedFilterShop !== null) {
            $dateShopStaff = EmployeeAttendance::query()
                ->with(['employee.category', 'markedBy'])
                ->where('shop_id', $selectedFilterShop->id)
                ->whereDate('attendance_date', $selectedDate)
                ->orderBy('marked_at')
                ->get();
        }

        return view('admin.staff.assignments', [
            'selectedDate' => $selectedDate,
            'search' => $search,
            'selectedCategory' => $selectedCategory,
            'categoryCode' => $categoryCode,
            'allocationFilter' => $allocationFilter,
            'categories' => $categories,
            'categoryTabs' => $categoryTabs,
            'totalStaffCount' => $totalStaffCount,
            'allocatedCount' => $allocatedCount,
            'unallocatedCount' => $unallocatedCount,
            'pendingCount' => $pendingCount,
            'employees' => $employees,
            'employeesForAssignment' => Employee::query()
                ->approved()
                ->where('employment_status', 'active')
                ->with(['category', 'defaultShop'])
                ->orderBy('name')
                ->get(),
            'shops' => $ownedShops,
            'selectedFilterShop' => $selectedFilterShop,
            'dateShopStaff' => $dateShopStaff,
        ]);
    }

    public function assignmentShow(Employee $employee, Request $request): View
    {
        Gate::authorize('view', $employee);

        $employee->load(['category', 'defaultShop', 'assignedShops']);

        $selectedMonth = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->string('month')->toString())->startOfMonth()
            : today()->startOfMonth();

        $monthEnd = $selectedMonth->copy()->endOfMonth();

        $attendanceRecords = EmployeeAttendance::query()
            ->with(['shop', 'markedBy'])
            ->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$selectedMonth->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->keyBy(fn (EmployeeAttendance $att): string => $att->attendance_date->toDateString());

        $assignmentHistory = ShopEmployeeAssignment::query()
            ->with('shop')
            ->where('employee_id', $employee->id)
            ->orderByDesc('effective_from')
            ->get();

        $calendarDays = [];
        $currentDay = $selectedMonth->copy();
        while ($currentDay->lte($monthEnd)) {
            $dateStr = $currentDay->toDateString();
            $att = $attendanceRecords->get($dateStr);

            $assignedShop = $assignmentHistory->first(function (ShopEmployeeAssignment $asgn) use ($currentDay): bool {
                $from = $asgn->effective_from?->toDateString();
                $to = $asgn->effective_to?->toDateString();
                $d = $currentDay->toDateString();

                return ($from === null || $from <= $d) && ($to === null || $to >= $d);
            })?->shop ?? $att?->shop ?? $employee->defaultShop;

            $calendarDays[] = [
                'date' => $currentDay->copy(),
                'date_string' => $dateStr,
                'day_number' => $currentDay->day,
                'attendance' => $att,
                'assigned_shop' => $assignedShop,
            ];

            $currentDay->addDay();
        }

        $ownedShops = Shop::query()->ownedForStaff()->orderBy('name')->get();

        return view('admin.staff.assignment-detail', [
            'employee' => $employee,
            'selectedMonth' => $selectedMonth,
            'prevMonth' => $selectedMonth->copy()->subMonth(),
            'nextMonth' => $selectedMonth->copy()->addMonth(),
            'calendarDays' => $calendarDays,
            'attendanceRecords' => $attendanceRecords,
            'shops' => $ownedShops,
        ]);
    }

    public function categoriesIndex(): View
    {
        Gate::authorize('viewAny', Employee::class);

        $checkInTime = BusinessSetting::query()
            ->where('key', 'shop_attendance_cutoff_time')
            ->value('value') ?: '10:00';

        return view('admin.staff.payroll-settings', [
            'allCategories' => EmployeeCategory::query()->with('leaveRules.leaveType')->orderBy('name')->get(),
            'checkInTime' => $checkInTime,
        ]);
    }

    public function updateCheckInTime(UpdateStaffCheckInTimeRequest $request): RedirectResponse
    {
        BusinessSetting::query()->updateOrCreate(
            ['key' => 'shop_attendance_cutoff_time'],
            ['value' => $request->validated('shop_attendance_cutoff_time')],
        );

        return redirect()
            ->route('admin.staff.categories.index')
            ->with('success', 'Staff check-in time updated successfully.');
    }

    public function show(Employee $employee, Request $request): View
    {
        Gate::authorize('view', $employee);

        $employee->load(['category.leaveRules.leaveType', 'defaultShop', 'user.roles', 'user.ownedShopAssignments.shop', 'assignedShops']);

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
        $leaveBalances = LeaveType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (LeaveType $leaveType) use ($employee): array {
                $rule = $employee->category?->leaveRules
                    ->filter(fn (EmployeeCategoryLeaveRule $categoryRule): bool => (int) $categoryRule->leave_type_id === (int) $leaveType->id)
                    ->sortByDesc(fn (EmployeeCategoryLeaveRule $categoryRule): string => $categoryRule->effective_from?->toDateString() ?? '0000-00-00')
                    ->first(fn (EmployeeCategoryLeaveRule $categoryRule): bool => $categoryRule->isActiveOn(today()));

                return [
                    'leave_type' => $leaveType,
                    'available' => $this->leaveLedgerService->balanceFor($employee, $leaveType, today()),
                    'carry_forward_allowed' => (bool) ($rule?->carry_forward_allowed ?? false),
                    'carry_forward_limit' => (float) ($rule?->maximum_carry_forward_days ?? 0),
                    'carry_forward_expiry_months' => $rule?->carry_forward_expiry_months,
                ];
            });
        $monthlyPayrollItem = $employee->payrollItems()
            ->with(['payrollRun', 'payments.journalEntry', 'shopStaffPayments.shop', 'shopStaffPayments.cashbookLine.entry'])
            ->whereHas('payrollRun', fn (Builder $query) => $query->whereDate('period_start', $selectedMonth->toDateString()))
            ->first();
        $recentPayrollPayments = $employee->payrollPayments()
            ->with(['payrollRun', 'journalEntry', 'paidBy'])
            ->latest('paid_on')
            ->latest('id')
            ->limit(8)
            ->get();
        $recentShopStaffPayments = $employee->shopStaffPayments()
            ->with(['shop', 'paidBy', 'advanceRequest', 'cashbookLine.entry'])
            ->latest('paid_on')
            ->latest('id')
            ->limit(8)
            ->get();
        $employeeAdvanceRequests = $employee->advanceRequests()
            ->with(['shop', 'requestedBy', 'reviewedBy', 'shopStaffPayment.cashbookLine.entry'])
            ->latest('id')
            ->limit(8)
            ->get();

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
            'leaveBalances' => $leaveBalances,
            'attendanceRecords' => $attendanceRecords->sortByDesc(fn (EmployeeAttendance $attendance): string => $attendance->attendance_date->toDateString()),
            'workedShops' => Shop::query()
                ->whereIn('id', EmployeeAttendance::query()->where('employee_id', $employee->id)->whereNotNull('shop_id')->distinct()->pluck('shop_id'))
                ->ownedForStaff()
                ->orderBy('name')
                ->get(),
            'shops' => $ownedShops,
            'categories' => EmployeeCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'payrollHistory' => $employee->payrollItems()->with(['payrollRun', 'payments'])->latest('id')->limit(6)->get(),
            'monthlyPayrollItem' => $monthlyPayrollItem,
            'recentPayrollPayments' => $recentPayrollPayments,
            'recentShopStaffPayments' => $recentShopStaffPayments,
            'employeeAdvanceRequests' => $employeeAdvanceRequests,
            'leaveRequests' => $employee->leaveRequests()->with(['submittedBy.roles', 'submittedForShop', 'reviewedBy'])->latest('id')->limit(8)->get(),
        ]);
    }

    public function approvalsIndex(Request $request): View
    {
        Gate::authorize('viewAny', Employee::class);

        $pendingEmployees = Employee::query()
            ->with(['defaultShop', 'submittedBy'])
            ->pending()
            ->latest('id')
            ->paginate(self::PAGE_SIZE);

        return view('admin.staff.approvals', [
            'pendingEmployees' => $pendingEmployees,
        ]);
    }

    public function approvalShow(Request $request, Employee $employee): View
    {
        Gate::authorize('viewAny', Employee::class);
        abort_unless($employee->verification_status === 'pending', 404, 'Employee registration is not pending approval.');

        $categories = EmployeeCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.staff.approval-review', [
            'employee' => $employee,
            'categories' => $categories,
        ]);
    }

    public function approveEmployee(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('update', $employee);

        $validated = $request->validate([
            'employee_category_id' => ['required', 'exists:employee_categories,id'],
            'salary_type' => ['required', Rule::in(['monthly', 'daily_wage'])],
            'monthly_salary' => ['nullable', 'required_if:salary_type,monthly', 'numeric', 'min:0'],
            'daily_wage' => ['nullable', 'required_if:salary_type,daily_wage', 'numeric', 'min:0'],
        ]);

        $employee->update([
            'employee_category_id' => (int) $validated['employee_category_id'],
            'salary_type' => $validated['salary_type'],
            'monthly_salary' => $validated['salary_type'] === 'monthly' ? (float) $validated['monthly_salary'] : null,
            'daily_wage' => $validated['salary_type'] === 'daily_wage' ? (float) $validated['daily_wage'] : null,
            'verification_status' => 'approved',
            'employment_status' => 'active',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        return redirect()->route('admin.staff.approvals.index')
            ->with('success', 'Employee approved successfully.');
    }

    public function rejectEmployee(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('update', $employee);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $employee->update([
            'verification_status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return redirect()->back()->with('warning', "Employee {$employee->name} ({$employee->employee_code}) registration rejected.");
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['is_user_linked'] = filled($validated['user_id'] ?? null);
        $validated['daily_wage'] = round((float) ($validated['daily_wage'] ?? 0), 2);

        if ($request->filled('photo_data_url')) {
            $validated['photo_path'] = $this->imageUploadService->processAndStore($request->string('photo_data_url')->toString(), 'employees/photos', 800);
        } elseif ($request->hasFile('photo')) {
            $validated['photo_path'] = $this->imageUploadService->processAndStore($request->file('photo'), 'employees/photos', 800);
        }

        if ($request->filled('id_front_data_url')) {
            $validated['id_front_path'] = $this->imageUploadService->processAndStore($request->string('id_front_data_url')->toString(), 'employees/id_docs', 1600);
        } elseif ($request->hasFile('id_front')) {
            $validated['id_front_path'] = $this->imageUploadService->processAndStore($request->file('id_front'), 'employees/id_docs', 1600);
        }

        if ($request->filled('id_back_data_url')) {
            $validated['id_back_path'] = $this->imageUploadService->processAndStore($request->string('id_back_data_url')->toString(), 'employees/id_docs', 1600);
        } elseif ($request->hasFile('id_back')) {
            $validated['id_back_path'] = $this->imageUploadService->processAndStore($request->file('id_back'), 'employees/id_docs', 1600);
        }

        Employee::query()->create($validated);

        return redirect()->route('admin.staff.employees.index')->with('success', 'Employee created successfully.');
    }

    public function storeShopEmployeeAssignment(StoreShopEmployeeAssignmentRequest $request): RedirectResponse
    {
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));
        $shop = Shop::query()->findOrFail($request->integer('shop_id'));

        $this->shopEmployeeAssignmentService->assign(
            $employee,
            $shop,
            Carbon::parse((string) $request->validated('effective_from')),
            $request->user(),
            $request->validated('notes'),
        );

        return redirect()->route('admin.staff.assignments.index')
            ->with('success', 'Employee assigned to '.$shop->name.'.');
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validated();
        $validated['is_user_linked'] = filled($validated['user_id'] ?? null);
        $validated['daily_wage'] = round((float) ($validated['daily_wage'] ?? 0), 2);

        if ($request->filled('photo_data_url')) {
            $validated['photo_path'] = $this->imageUploadService->processAndStore($request->string('photo_data_url')->toString(), 'employees/photos', 800);
        } elseif ($request->hasFile('photo')) {
            $validated['photo_path'] = $this->imageUploadService->processAndStore($request->file('photo'), 'employees/photos', 800);
        }

        if ($request->filled('id_front_data_url')) {
            $validated['id_front_path'] = $this->imageUploadService->processAndStore($request->string('id_front_data_url')->toString(), 'employees/id_docs', 1600);
        } elseif ($request->hasFile('id_front')) {
            $validated['id_front_path'] = $this->imageUploadService->processAndStore($request->file('id_front'), 'employees/id_docs', 1600);
        }

        if ($request->filled('id_back_data_url')) {
            $validated['id_back_path'] = $this->imageUploadService->processAndStore($request->string('id_back_data_url')->toString(), 'employees/id_docs', 1600);
        } elseif ($request->hasFile('id_back')) {
            $validated['id_back_path'] = $this->imageUploadService->processAndStore($request->file('id_back'), 'employees/id_docs', 1600);
        }

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

    public function storeCategory(StoreEmployeeCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated): void {
            $employeeCategory = EmployeeCategory::query()->create([
                ...$validated,
                'is_active' => $request->boolean('is_active', true),
            ]);

            $this->syncPaidLeaveCarryOverRule($employeeCategory, $validated);
        });

        return redirect()->route('admin.staff.categories.index')->with('success', 'Payroll category created successfully.');
    }

    public function updateCategory(UpdateEmployeeCategoryRequest $request, EmployeeCategory $employeeCategory): RedirectResponse
    {
        $validated = $request->validated();

        if ($employeeCategory->isCoreCategory()) {
            unset($validated['name'], $validated['code'], $validated['staff_area']);
        }

        DB::transaction(function () use ($employeeCategory, $request, $validated): void {
            $employeeCategory->update([
                ...$validated,
                'is_active' => $employeeCategory->isCoreCategory() ? true : $request->boolean('is_active'),
            ]);

            $this->syncPaidLeaveCarryOverRule($employeeCategory, $validated);
        });

        return redirect()->route('admin.staff.categories.index')->with('success', 'Payroll category updated successfully.');
    }

    /**
     * Save the simple paid leave carry-over rule used by the category form.
     *
     * @param  array<string, mixed>  $validated
     */
    private function syncPaidLeaveCarryOverRule(EmployeeCategory $employeeCategory, array $validated): void
    {
        $paidLeaveType = LeaveType::query()->where('code', LeaveType::CODE_PAID)->first();

        if (! $paidLeaveType) {
            return;
        }

        $monthlyPaidLeaveLimit = (float) $employeeCategory->monthly_paid_leave_limit;

        EmployeeCategoryLeaveRule::query()->updateOrCreate(
            [
                'employee_category_id' => $employeeCategory->id,
                'leave_type_id' => $paidLeaveType->id,
            ],
            [
                'annual_entitlement' => $monthlyPaidLeaveLimit * 12,
                'monthly_accrual_amount' => $monthlyPaidLeaveLimit,
                'allocation_frequency' => 'monthly',
                'carry_forward_allowed' => (bool) ($validated['paid_leave_carry_forward_allowed'] ?? false),
                'maximum_carry_forward_days' => (float) ($validated['paid_leave_maximum_carry_forward_days'] ?? 0),
                'carry_forward_expiry_months' => $validated['paid_leave_carry_forward_expiry_months'] ?? null,
                'payroll_weight' => 1,
                'negative_balance_allowed' => false,
            ],
        );
    }

    public function updateCategoryLeaveRules(UpdateEmployeeCategoryLeaveRulesRequest $request, EmployeeCategory $employeeCategory): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.employee.update'), 403);

        collect($request->validated('rules'))
            ->each(function (array $rulePayload) use ($employeeCategory): void {
                EmployeeCategoryLeaveRule::query()->updateOrCreate(
                    [
                        'employee_category_id' => $employeeCategory->id,
                        'leave_type_id' => (int) $rulePayload['leave_type_id'],
                    ],
                    [
                        'annual_entitlement' => round((float) $rulePayload['annual_entitlement'], 2),
                        'monthly_accrual_amount' => filled($rulePayload['monthly_accrual_amount'] ?? null)
                            ? round((float) $rulePayload['monthly_accrual_amount'], 2)
                            : null,
                        'allocation_frequency' => (string) $rulePayload['allocation_frequency'],
                        'carry_forward_allowed' => (bool) ($rulePayload['carry_forward_allowed'] ?? false),
                        'maximum_carry_forward_days' => round((float) ($rulePayload['maximum_carry_forward_days'] ?? 0), 2),
                        'carry_forward_expiry_months' => $rulePayload['carry_forward_expiry_months'] ?? null,
                        'carry_forward_expiry_date' => $rulePayload['carry_forward_expiry_date'] ?? null,
                        'payroll_weight' => round((float) $rulePayload['payroll_weight'], 2),
                        'negative_balance_allowed' => (bool) ($rulePayload['negative_balance_allowed'] ?? false),
                        'effective_from' => $rulePayload['effective_from'] ?? null,
                        'effective_to' => $rulePayload['effective_to'] ?? null,
                        'notes' => filled($rulePayload['notes'] ?? null) ? trim((string) $rulePayload['notes']) : null,
                    ],
                );
            });

        return redirect()->route('admin.staff.categories.index')
            ->with('success', 'Leave and payroll settings updated successfully.');
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
        $selectedCategory = $categoryCode !== '' && $categoryCode !== 'all'
            ? $categories->firstWhere('code', $categoryCode)
            : null;
        $ownedShops = Shop::query()->ownedForStaff()->orderBy('name')->get();

        $employeeBaseQuery = Employee::query()
            ->approved()
            ->where('employment_status', 'active')
            ->with(['category', 'defaultShop', 'shopAssignments.shop'])
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

        $totalStaff = (clone $employeeBaseQuery)->count();
        $presentCount = $statusAttendanceRecords->where('status', 'present')->count();
        $halfDayCount = $statusAttendanceRecords->where('status', 'half_day')->count();
        $leaveCount = $statusAttendanceRecords->where('status', 'leave')->count();
        $absentCount = $statusAttendanceRecords->where('status', 'absent')->count();
        $markedTotal = $statusAttendanceRecords->whereIn('status', ['present', 'half_day', 'leave', 'absent'])->count();
        $notMarkedCount = max(0, $totalStaff - $markedTotal);

        $statusCounts = [
            'total' => $totalStaff,
            'present' => $presentCount,
            'half_day' => $halfDayCount,
            'leave' => $leaveCount,
            'absent' => $absentCount,
            'not_marked' => $notMarkedCount,
        ];

        $employeesQuery = clone $employeeBaseQuery;

        if (in_array($selectedStatus, ['present', 'half_day', 'leave', 'absent'], true)) {
            $employeesQuery->whereHas('attendances', function (Builder $query) use ($selectedDate, $shopId, $selectedStatus): void {
                $this->applyAttendanceDateScope($query, $selectedDate, $shopId);
                $query->where('status', $selectedStatus);
            });
        } elseif ($selectedStatus === 'not_marked') {
            $employeesQuery->whereDoesntHave('attendances', function (Builder $query) use ($selectedDate, $shopId): void {
                $this->applyAttendanceDateScope($query, $selectedDate, $shopId);
            });
        }

        $employees = $employeesQuery->get();

        $attendanceRecords = EmployeeAttendance::query()
            ->with(['employee.category', 'shop', 'markedBy'])
            ->whereDate('attendance_date', $selectedDate)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->when($shopId > 0, fn ($query) => $query->where('shop_id', $shopId))
            ->get()
            ->keyBy('employee_id');

        $assignmentsMap = ShopEmployeeAssignment::query()
            ->with('shop')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where(function ($query) use ($selectedDate): void {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $selectedDate->toDateString());
            })
            ->where(function ($query) use ($selectedDate): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $selectedDate->toDateString());
            })
            ->orderByDesc('effective_from')
            ->get()
            ->groupBy('employee_id');

        $shopGroupsRaw = [];

        foreach ($employees as $employee) {
            $att = $attendanceRecords->get($employee->id);
            $assignmentShop = $assignmentsMap->get($employee->id)?->first()?->shop;
            $resolvedShop = $att?->shop ?? $assignmentShop ?? $employee->defaultShop;

            $shopKey = $resolvedShop !== null ? 'shop_'.$resolvedShop->id : 'unallocated';
            $shopName = $resolvedShop !== null ? $resolvedShop->name : 'UNALLOCATED STAFF';

            if (! isset($shopGroupsRaw[$shopKey])) {
                $shopGroupsRaw[$shopKey] = [
                    'key' => $shopKey,
                    'shop_id' => $resolvedShop?->id,
                    'shop_name' => $shopName,
                    'shop' => $resolvedShop,
                    'is_unallocated' => $resolvedShop === null,
                    'employees' => [],
                    'total' => 0,
                    'present' => 0,
                    'half_day' => 0,
                    'leave' => 0,
                    'absent' => 0,
                    'not_marked' => 0,
                ];
            }

            $status = $att?->status;
            if ($status === 'present') {
                $shopGroupsRaw[$shopKey]['present']++;
            } elseif ($status === 'half_day') {
                $shopGroupsRaw[$shopKey]['half_day']++;
            } elseif ($status === 'leave') {
                $shopGroupsRaw[$shopKey]['leave']++;
            } elseif ($status === 'absent') {
                $shopGroupsRaw[$shopKey]['absent']++;
            } else {
                $shopGroupsRaw[$shopKey]['not_marked']++;
            }
            $shopGroupsRaw[$shopKey]['total']++;

            $shopGroupsRaw[$shopKey]['employees'][] = [
                'employee' => $employee,
                'attendance' => $att,
                'status' => $status,
                'assigned_shop' => $resolvedShop,
            ];
        }

        $unallocatedGroup = $shopGroupsRaw['unallocated'] ?? null;
        unset($shopGroupsRaw['unallocated']);

        uasort($shopGroupsRaw, fn ($a, $b) => strcasecmp((string) $a['shop_name'], (string) $b['shop_name']));

        if ($unallocatedGroup !== null) {
            $shopGroupsRaw['unallocated'] = $unallocatedGroup;
        }

        $shopGroups = collect($shopGroupsRaw);

        $allActiveEmployees = Employee::query()
            ->approved()
            ->where('employment_status', 'active')
            ->with(['category', 'defaultShop'])
            ->orderBy('name')
            ->get();

        return view('admin.staff.attendance', [
            'selectedDate' => $selectedDate,
            'prevDate' => $selectedDate->copy()->subDay(),
            'nextDate' => $selectedDate->copy()->addDay(),
            'search' => $search,
            'selectedStatus' => $selectedStatus,
            'statusCounts' => $statusCounts,
            'selectedShopId' => $shopId > 0 ? $shopId : null,
            'selectedStaffArea' => $staffArea,
            'selectedCategory' => $selectedCategory,
            'categoryCode' => $categoryCode,
            'categories' => $categories,
            'employees' => $employees,
            'shopGroups' => $shopGroups,
            'attendanceRecords' => $attendanceRecords,
            'allActiveEmployees' => $allActiveEmployees,
            'shops' => $ownedShops,
        ]);
    }

    public function storeAttendance(UpsertEmployeeAttendanceRequest $request): RedirectResponse
    {
        $employee = Employee::query()->with('category')->findOrFail($request->integer('employee_id'));
        Gate::authorize('create', EmployeeAttendance::class);

        $shop = $request->filled('shop_id') ? Shop::query()->findOrFail($request->integer('shop_id')) : null;
        $attendanceDate = Carbon::parse($request->string('attendance_date')->toString());
        $existingAttendance = EmployeeAttendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $attendanceDate->toDateString())
            ->first();

        $attendance = $this->attendanceService->upsert(
            $employee,
            $attendanceDate,
            $request->string('status')->toString(),
            $request->user(),
            'admin',
            $shop,
            $request->input('notes'),
        );

        $this->hrOverrideService->record(
            'attendance',
            $employee,
            $attendance,
            [
                'status' => $existingAttendance?->status,
                'shop_id' => $existingAttendance?->shop_id,
                'notes' => $existingAttendance?->notes,
            ],
            [
                'status' => $attendance->status,
                'shop_id' => $attendance->shop_id,
                'notes' => $attendance->notes,
            ],
            $request->string('notes')->toString(),
            $request->user(),
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
            'leave_type_id' => $request->integer('leave_type_id'),
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
        $oldStatus = $leaveRequest->status;
        $leaveRequest->forceFill([
            'status' => $request->string('status')->toString(),
            'review_note' => $request->input('review_note'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        $this->hrOverrideService->record(
            'leave_approval',
            $leaveRequest->employee,
            $leaveRequest,
            ['status' => $oldStatus, 'review_note' => null],
            ['status' => $leaveRequest->status, 'review_note' => $leaveRequest->review_note],
            $request->string('review_note')->toString(),
            $request->user(),
        );

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

            $this->leaveLedgerService->recordApprovedLeave($leaveRequest, $request->user());
        }

        if ($leaveRequest->status === 'rejected') {
            EmployeeAttendance::query()
                ->where('employee_id', $leaveRequest->employee_id)
                ->whereBetween('attendance_date', [$leaveRequest->start_date->toDateString(), $leaveRequest->end_date->toDateString()])
                ->where('status', 'leave')
                ->where(function (Builder $query) use ($leaveRequest): void {
                    $query->where(function (Builder $ownerQuery) use ($leaveRequest): void {
                        $ownerQuery
                            ->where('source', 'owner')
                            ->where('shop_id', $leaveRequest->submitted_for_shop_id);
                    })->orWhere(function (Builder $approvedQuery): void {
                        $approvedQuery
                            ->where('source', 'admin')
                            ->where('notes', 'Auto-marked from approved leave request.');
                    });
                })
                ->delete();

            if ($oldStatus === 'approved') {
                $this->leaveLedgerService->reverseApprovedLeave(
                    $leaveRequest,
                    $request->user(),
                    $request->string('review_note')->toString(),
                );
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
                ->with(['items.employee', 'items.category', 'items.payments', 'items.shopStaffPayments.shop', 'generatedBy', 'finalizedBy', 'journalEntry'])
                ->whereBetween('period_start', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->latest('period_start')
                ->paginate(self::PAGE_SIZE)
                ->withQueryString(),
        ]);
    }

    public function paymentsIndex(Request $request): View
    {
        Gate::authorize('viewAny', PayrollRun::class);

        $payrollMonth = $request->string('payroll_month')->toString();

        if ($payrollMonth === '' && $request->filled('date')) {
            $payrollMonth = Carbon::parse($request->string('date')->toString())->format('Y-m');
        }

        $selectedPayrollMonth = $this->resolvePayrollMonth($payrollMonth);

        $payrollRun = PayrollRun::query()
            ->with(['items.employee', 'items.category', 'items.payments.journalEntry', 'items.payments.paidBy', 'items.shopStaffPayments.shop'])
            ->whereDate('period_start', $selectedPayrollMonth->toDateString())
            ->first();

        $payments = PayrollPayment::query()
            ->with(['employee', 'payrollRun', 'journalEntry', 'paidBy'])
            ->whereDate('paid_on', '>=', $selectedPayrollMonth->toDateString())
            ->whereDate('paid_on', '<=', $selectedPayrollMonth->copy()->endOfMonth()->toDateString())
            ->latest('paid_on')
            ->latest('id')
            ->get();
        $shopStaffPayments = ShopStaffPayment::query()
            ->with(['employee', 'shop', 'paidBy', 'advanceRequest', 'cashbookLine.entry'])
            ->whereDate('paid_on', '>=', $selectedPayrollMonth->toDateString())
            ->whereDate('paid_on', '<=', $selectedPayrollMonth->copy()->endOfMonth()->toDateString())
            ->latest('paid_on')
            ->latest('id')
            ->get();

        return view('admin.staff.payments', [
            'selectedPayrollMonth' => $selectedPayrollMonth,
            'payrollRun' => $payrollRun,
            'payments' => $payments,
            'shopStaffPayments' => $shopStaffPayments,
            'contractPayments' => ContractWorkerPayment::query()
                ->with(['shop', 'paidBy'])
                ->whereDate('paid_on', '>=', $selectedPayrollMonth->toDateString())
                ->whereDate('paid_on', '<=', $selectedPayrollMonth->copy()->endOfMonth()->toDateString())
                ->latest('paid_on')
                ->latest('id')
                ->get(),
            'shops' => Shop::query()->ownedForStaff()->orderBy('name')->get(),
            'companyAccounts' => CompanyAccount::query()
                ->where('enabled', true)
                ->whereIn('account_type', ['cash', 'bank'])
                ->orderBy('account_type')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function advancePaymentsIndex(Request $request): View
    {
        Gate::authorize('viewAny', PayrollRun::class);

        $payrollMonth = $request->string('payroll_month')->toString();

        if ($payrollMonth === '' && $request->filled('date')) {
            $payrollMonth = Carbon::parse($request->string('date')->toString())->format('Y-m');
        }

        $selectedPayrollMonth = $this->resolvePayrollMonth($payrollMonth);
        $status = $request->string('status', 'all')->toString();
        $shopId = $request->integer('shop_id');
        $employeeId = $request->integer('employee_id');

        $advanceRequests = EmployeeAdvanceRequest::query()
            ->with(['employee.category', 'shop', 'requestedBy', 'reviewedBy', 'shopStaffPayment.paidBy', 'shopStaffPayment.cashbookLine.entry'])
            ->whereDate('payroll_month', $selectedPayrollMonth->toDateString())
            ->when(in_array($status, ['pending', 'approved', 'rejected'], true), fn (Builder $query) => $query->where('status', $status))
            ->when($shopId > 0, fn (Builder $query) => $query->where('shop_id', $shopId))
            ->when($employeeId > 0, fn (Builder $query) => $query->where('employee_id', $employeeId))
            ->latest('id')
            ->get();

        return view('admin.staff.advance-payments', [
            'selectedPayrollMonth' => $selectedPayrollMonth,
            'advanceRequests' => $advanceRequests,
            'summary' => [
                'pending_count' => $advanceRequests->where('status', 'pending')->count(),
                'approved_amount' => round((float) $advanceRequests->where('status', 'approved')->sum('approved_amount'), 2),
                'requested_amount' => round((float) $advanceRequests->sum('requested_amount'), 2),
                'paid_amount' => round((float) $advanceRequests->where('status', 'approved')->sum(fn (EmployeeAdvanceRequest $advanceRequest): float => (float) ($advanceRequest->shopStaffPayment?->amount ?? 0)), 2),
            ],
            'status' => in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : 'all',
            'selectedShopId' => $shopId,
            'selectedEmployeeId' => $employeeId,
            'shops' => Shop::query()->ownedForStaff()->orderBy('name')->get(),
            'employees' => Employee::query()
                ->where('staff_area', 'shop')
                ->where('employment_status', 'active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storePayrollPayment(StorePayrollPaymentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $payrollRunItem = PayrollRunItem::query()->with(['payrollRun', 'employee', 'payments'])->findOrFail((int) $validated['payroll_run_item_id']);
        $companyAccount = CompanyAccount::query()
            ->where('public_uuid', (string) $validated['company_account_uuid'])
            ->where('enabled', true)
            ->whereIn('account_type', ['cash', 'bank'])
            ->firstOrFail();

        $payment = $this->payrollPaymentService->record(
            $payrollRunItem,
            (float) $validated['amount'],
            (string) $validated['payment_method'],
            (string) $validated['payment_type'],
            Carbon::parse((string) $validated['paid_on']),
            $request->user(),
            $validated['notes'] ?? null,
            companyAccountId: (int) $companyAccount->id,
            reference: $validated['reference'] ?? null,
            requestUuid: (string) $validated['request_uuid'],
        );

        return redirect()->route('admin.staff.payments.index', [
            'payroll_month' => $payment->payrollRun->period_start->format('Y-m'),
        ])->with('success', 'Salary payment recorded and posted to journals.');
    }

    public function storeShopStaffPayment(StoreShopStaffPaymentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $payrollRunItem = PayrollRunItem::query()
            ->with(['payrollRun', 'employee', 'shopStaffPayments', 'payments'])
            ->findOrFail((int) $validated['payroll_run_item_id']);
        $shop = Shop::query()->findOrFail((int) $validated['shop_id']);
        $amount = round((float) $validated['amount'], 2);

        $payment = $this->employeeAdvanceService->recordManualShopStaffPayment(
            $payrollRunItem,
            $shop,
            $amount,
            (string) $validated['payment_type'],
            (string) $validated['fund_source'],
            Carbon::parse((string) $validated['paid_on']),
            $request->user(),
            $validated['notes'] ?? null,
        );

        return redirect()->route('admin.staff.payments.index', [
            'payroll_month' => $payment->paid_on->format('Y-m'),
        ])->with('success', 'Shop staff payment recorded and posted to shop cashbook.');
    }

    public function reviewEmployeeAdvance(ReviewEmployeeAdvanceRequest $request, EmployeeAdvanceRequest $advanceRequest): RedirectResponse
    {
        $reviewedAdvance = $this->employeeAdvanceService->review(
            $advanceRequest,
            (string) $request->validated('decision'),
            (float) ($request->validated('approved_amount') ?? $advanceRequest->requested_amount),
            $request->user(),
            $request->validated('review_note'),
        );

        return redirect()->route('admin.staff.advance-payments.index', [
            'payroll_month' => $reviewedAdvance->payroll_month->format('Y-m'),
        ])->with(
            $reviewedAdvance->status === 'approved' ? 'success' : 'warning',
            $reviewedAdvance->status === 'approved' ? 'Advance approved and paid.' : 'Advance request rejected.',
        );
    }

    public function storeContractWorkerPayment(StoreContractWorkerPaymentRequest $request): RedirectResponse
    {
        $shop = $request->filled('shop_id')
            ? Shop::query()->findOrFail($request->integer('shop_id'))
            : null;

        $payment = $this->contractWorkerPaymentService->record(
            $request->validated(),
            $request->user(),
            $shop,
        );

        return redirect()->route('admin.staff.payments.index', [
            'payroll_month' => $payment->paid_on->format('Y-m'),
        ])->with('success', 'Contract worker payment recorded.');
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

    public function updatePayrollItem(UpdatePayrollRunItemRequest $request, PayrollRun $payrollRun, PayrollRunItem $payrollRunItem): JsonResponse|RedirectResponse
    {
        abort_unless($payrollRunItem->payroll_run_id === $payrollRun->id, 404);

        $validated = $request->validated();
        $updatedItem = $this->payrollService->updateOverride(
            $payrollRunItem,
            $request->filled('override_amount') ? (float) $validated['override_amount'] : null,
            $request->user(),
            (string) $validated['override_reason'],
        );

        if ($request->expectsJson()) {
            $updatedRun = $payrollRun->fresh(['items.category']);

            return response()->json([
                'message' => 'Payroll override updated.',
                'item' => [
                    'id' => $updatedItem->id,
                    'final_amount' => (float) $updatedItem->final_amount,
                    'final_amount_formatted' => 'Rs. '.number_format((float) $updatedItem->final_amount, 2),
                ],
                'run' => [
                    'id' => $updatedRun?->id,
                    'net_amount' => (float) ($updatedRun?->net_amount ?? 0),
                    'net_amount_formatted' => 'Rs. '.number_format((float) ($updatedRun?->net_amount ?? 0), 2),
                ],
                'categories' => $updatedRun?->items
                    ->groupBy(fn (PayrollRunItem $item): string => $item->category?->name ?? 'Uncategorized')
                    ->map(fn (Collection $items, string $categoryName): array => [
                        'name' => $categoryName,
                        'total' => (float) $items->sum('final_amount'),
                        'total_formatted' => 'Rs. '.number_format((float) $items->sum('final_amount'), 2),
                    ])
                    ->values()
                    ->all() ?? [],
            ]);
        }

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
