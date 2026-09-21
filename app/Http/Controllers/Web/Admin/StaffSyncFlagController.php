<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cashbook\ShopLedgerTransaction;
use App\Models\Employee;
use App\Models\Shop;
use App\Models\ShopStaffPayment;
use App\Models\StaffSyncFlag;
use App\Services\HR\StaffSyncFlagScannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StaffSyncFlagController extends Controller
{
    public function __construct(
        private readonly StaffSyncFlagScannerService $scannerService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Employee::class);

        $selectedShopId = $request->filled('shop_id') ? $request->integer('shop_id') : null;
        $selectedEmployeeId = $request->filled('employee_id') ? $request->integer('employee_id') : null;
        $selectedMonth = $request->input('month');
        $selectedPaymentType = $request->input('payment_type');
        $selectedFlagCode = $request->input('flag_code');
        $selectedStatus = $request->input('status', StaffSyncFlag::STATUS_OPEN);
        $search = $request->input('search');

        $query = StaffSyncFlag::query()
            ->with(['shop', 'employee', 'resolvedBy'])
            ->latest('id');

        if ($selectedStatus !== 'all') {
            $query->where('status', $selectedStatus);
        }

        if ($selectedShopId) {
            $query->where('shop_id', $selectedShopId);
        }

        if ($selectedEmployeeId) {
            $query->where('employee_id', $selectedEmployeeId);
        }

        if ($selectedMonth) {
            $monthStart = Carbon::createFromFormat('Y-m', $selectedMonth)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $query->whereDate('payment_date', '>=', $monthStart->toDateString())
                ->whereDate('payment_date', '<=', $monthEnd->toDateString());
        }

        if ($selectedPaymentType) {
            $query->where('details->master->payment_type', $selectedPaymentType);
        }

        if ($selectedFlagCode) {
            $query->where('flag_code', $selectedFlagCode);
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($eq) => $eq->where('name', 'like', "%{$search}%")->orWhere('employee_code', 'like', "%{$search}%"))
                    ->orWhereHas('shop', fn ($sq) => $sq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            });
        }

        $flags = $query->paginate(20)->withQueryString();

        $openFlagsCount = StaffSyncFlag::query()->open()->count();
        $cashbookMismatchesCount = StaffSyncFlag::query()->open()->where(function ($q): void {
            $q->where('flag_code', 'like', 'CASHBOOK_%')
                ->orWhere('flag_code', StaffSyncFlag::CODE_MISSING_CASHBOOK)
                ->orWhere('flag_code', StaffSyncFlag::CODE_DUPLICATE_CASHBOOK);
        })->count();
        $orphanCashbooksCount = StaffSyncFlag::query()->open()->where('flag_code', StaffSyncFlag::CODE_ORPHAN_CASHBOOK)->count();
        $advanceSalaryMismatchesCount = StaffSyncFlag::query()->open()->whereIn('flag_code', [
            StaffSyncFlag::CODE_ADVANCE_BALANCE_MISMATCH,
            StaffSyncFlag::CODE_SALARY_NOT_UPDATED,
            StaffSyncFlag::CODE_PAYROLL_MISMATCH,
        ])->count();
        $resolvedCount = StaffSyncFlag::query()->resolved()->count();

        $shops = Shop::query()->ownedForStaff()->orderBy('name')->get();
        $employees = Employee::query()->orderBy('name')->get();

        return view('admin.staff.sync-flags.index', [
            'flags' => $flags,
            'openFlagsCount' => $openFlagsCount,
            'cashbookMismatchesCount' => $cashbookMismatchesCount,
            'orphanCashbooksCount' => $orphanCashbooksCount,
            'advanceSalaryMismatchesCount' => $advanceSalaryMismatchesCount,
            'resolvedCount' => $resolvedCount,
            'shops' => $shops,
            'employees' => $employees,
            'selectedShopId' => $selectedShopId,
            'selectedEmployeeId' => $selectedEmployeeId,
            'selectedMonth' => $selectedMonth,
            'selectedPaymentType' => $selectedPaymentType,
            'selectedFlagCode' => $selectedFlagCode,
            'selectedStatus' => $selectedStatus,
            'search' => $search,
        ]);
    }

    public function scan(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', Employee::class);

        $results = $this->scannerService->scanAll($request->only(['shop_id', 'employee_id', 'month', 'payment_type']));

        return redirect()->route('admin.staff.sync-flags.index', $request->query())
            ->with('success', "Integrity scan completed. Scanned {$results['scanned']} payments. Found {$results['open_flags']} open flags.");
    }

    public function review(Request $request, StaffSyncFlag $flag): JsonResponse
    {
        Gate::authorize('viewAny', Employee::class);

        $flag->load(['shop', 'employee']);

        // Refresh snapshot if source still exists
        if ($flag->source_type === ShopStaffPayment::class) {
            $payment = ShopStaffPayment::query()->with(['shop', 'employee'])->find($flag->source_id);
            if ($payment) {
                $this->scannerService->checkPayment($payment);
                $flag->refresh();
            }
        }

        $payload = $this->scannerService->getReviewPayload($flag);
        $payload['shops'] = Shop::query()->ownedForStaff()->orderBy('name')->get(['id', 'name', 'code']);
        $payload['employees'] = Employee::query()->orderBy('name')->get(['id', 'name', 'employee_code']);

        return response()->json($payload);
    }

    public function applyFixes(Request $request, StaffSyncFlag $flag): JsonResponse|RedirectResponse
    {
        if ($flag->employee) {
            Gate::authorize('update', $flag->employee);
        } else {
            abort_unless($request->user()?->can('hr.employee.update'), 403);
        }

        // Check if legacy selected_fixes payload is submitted
        if ($request->has('selected_fixes') && ! $request->filled('amount') && ! $request->filled('orphan_action')) {
            $selectedFixes = (array) $request->input('selected_fixes', []);

            if (empty($selectedFixes)) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please select at least one fix to apply.',
                    ], 422);
                }

                return back()->with('warning', 'Please select at least one fix to apply.');
            }

            if ($flag->source_type === ShopLedgerTransaction::class) {
                if (in_array('remove_orphan_cashbook', $selectedFixes, true)) {
                    $this->scannerService->fixOrphanCashbook((int) $flag->source_id, $request->user());

                    if ($request->expectsJson() || $request->ajax()) {
                        return response()->json([
                            'success' => true,
                            'message' => 'Orphan Cashbook transaction removed and daily balances recalculated.',
                            'applied_count' => 1,
                            'remaining_flags_count' => 0,
                        ]);
                    }

                    return redirect()->route('admin.staff.sync-flags.index')
                        ->with('success', 'Orphan Cashbook transaction removed and daily balances recalculated.');
                }

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No matching action for orphan cashbook.',
                    ], 422);
                }

                return back()->with('warning', 'No action selected.');
            }

            if ($flag->source_type === ShopStaffPayment::class) {
                $payment = ShopStaffPayment::query()->find($flag->source_id);
                if (! $payment) {
                    $flag->status = StaffSyncFlag::STATUS_RESOLVED;
                    $flag->resolved_at = now();
                    $flag->resolved_by = $request->user()?->id;
                    $flag->resolution_notes = 'Payment no longer exists.';
                    $flag->save();

                    if ($request->expectsJson() || $request->ajax()) {
                        return response()->json([
                            'success' => true,
                            'message' => 'Target payment no longer exists. Flag marked resolved.',
                            'applied_count' => 0,
                            'remaining_flags_count' => 0,
                        ]);
                    }

                    return back()->with('info', 'Target payment no longer exists. Flag resolved.');
                }

                $res = $this->scannerService->applySelectiveFixes($payment, $selectedFixes, $request->user());

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'message' => $res['message'],
                        'applied_fixes' => $res['applied_fixes'],
                        'applied_count' => $res['applied_count'],
                        'remaining_flags_count' => $res['remaining_flags_count'],
                    ]);
                }

                if ($res['remaining_flags_count'] === 0) {
                    return redirect()->route('admin.staff.sync-flags.index')
                        ->with('success', $res['message']);
                }

                return redirect()->route('admin.staff.sync-flags.index')
                    ->with('warning', $res['message']);
            }
        }

        // Admin Decision Flow
        if ($flag->source_type === ShopLedgerTransaction::class) {
            $orphanAction = $request->input('orphan_action', $request->input('action', 'remove_cashbook'));

            if ($orphanAction === 'create_payment') {
                $validated = $request->validate([
                    'employee_id' => ['required', 'exists:employees,id'],
                    'shop_id' => ['required', 'exists:shops,id'],
                    'amount' => ['required', 'numeric', 'min:0.01'],
                    'paid_on' => ['required', 'date'],
                    'payment_type' => ['required', 'in:salary,advance'],
                    'fund_source' => ['required', 'in:sales,petty_cash,company,petty'],
                    'notes' => ['nullable', 'string', 'max:500'],
                ]);
                $validated['orphan_action'] = 'create_payment';
            } else {
                $validated = ['orphan_action' => 'remove_cashbook'];
            }

            $res = $this->scannerService->applyAdminDecision($flag, $validated, $request->user());

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => $res['success'],
                    'message' => $res['message'],
                    'remaining_flags_count' => $res['remaining_flags_count'],
                ]);
            }

            return redirect()->route('admin.staff.sync-flags.index')
                ->with($res['success'] ? 'success' : 'warning', $res['message']);
        }

        if ($flag->source_type === ShopStaffPayment::class) {
            $action = $request->input('action', 'apply_final_value');

            if ($action === 'cancel_payment') {
                $validated = ['action' => 'cancel_payment'];
            } else {
                $validated = $request->validate([
                    'amount' => ['required', 'numeric', 'min:0.01'],
                    'paid_on' => ['required', 'date'],
                    'payment_type' => ['required', 'in:salary,advance'],
                    'fund_source' => ['required', 'in:sales,petty_cash,company,petty'],
                    'shop_id' => ['nullable', 'exists:shops,id'],
                    'notes' => ['nullable', 'string', 'max:500'],
                ]);
                $validated['action'] = 'apply_final_value';
            }

            $res = $this->scannerService->applyAdminDecision($flag, $validated, $request->user());

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => $res['success'],
                    'message' => $res['message'],
                    'remaining_flags_count' => $res['remaining_flags_count'],
                ]);
            }

            return redirect()->route('admin.staff.sync-flags.index')
                ->with($res['success'] ? 'success' : 'warning', $res['message']);
        }

        return back()->with('info', 'No action taken.');
    }

    public function fixSync(Request $request, StaffSyncFlag $flag): RedirectResponse
    {
        if ($flag->employee) {
            Gate::authorize('update', $flag->employee);
        } else {
            abort_unless($request->user()?->can('hr.employee.update'), 403);
        }

        if ($flag->source_type === ShopStaffPayment::class) {
            $payment = ShopStaffPayment::query()->find($flag->source_id);
            if (! $payment) {
                $flag->status = StaffSyncFlag::STATUS_RESOLVED;
                $flag->resolved_at = now();
                $flag->resolved_by = $request->user()?->id;
                $flag->resolution_notes = 'Payment no longer exists.';
                $flag->save();

                return back()->with('info', 'Target payment no longer exists. Flag resolved.');
            }

            $res = $this->scannerService->fixPayment($payment, [], $request->user());

            if ($res['success']) {
                return back()->with('success', "Payment #{$payment->id} synchronized and linked Cashbook entry repaired successfully.");
            }

            return back()->with('warning', 'Sync attempted but some issues require manual review.');
        }

        if ($flag->source_type === ShopLedgerTransaction::class) {
            $this->scannerService->fixOrphanCashbook((int) $flag->source_id, $request->user());

            return back()->with('success', 'Orphan Cashbook transaction removed and daily balances recalculated.');
        }

        return back()->with('info', 'Action completed.');
    }

    public function saveAndSyncAll(Request $request, StaffSyncFlag $flag): JsonResponse|RedirectResponse
    {
        return $this->applyFixes($request, $flag);
    }
}
