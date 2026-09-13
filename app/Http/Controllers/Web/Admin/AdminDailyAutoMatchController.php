<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyAdvanceMatchRun;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\DailyAdvanceMatchExecutionService;
use App\Services\Purchasing\DailyAdvanceMatchPlanningService;
use App\Services\Purchasing\WarehouseReceiptReadScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AdminDailyAutoMatchController extends Controller
{
    public function __construct(
        private readonly DailyAdvanceMatchPlanningService $planningService,
        private readonly DailyAdvanceMatchExecutionService $executionService,
        private readonly WarehouseReceiptReadScope $readScope,
    ) {}

    /**
     * Render the Daily Auto Match dashboard page.
     */
    /**
     * Render the Daily Auto Match dashboard page.
     */
    public function index(Request $request): View
    {
        $this->ensureAuthorized($request);

        /** @var User $user */
        $user = $request->user();

        // 1. Resolve available warehouses
        $warehousesQuery = Warehouse::query()->where('is_active', true)->orderBy('name');
        $authorizedWarehouseIds = $this->readScope->warehouseIds($user);
        if ($authorizedWarehouseIds !== null) {
            $warehousesQuery->whereIn('id', $authorizedWarehouseIds);
        }
        $availableWarehouses = $warehousesQuery->get();

        $selectedWarehouseId = $request->filled('warehouse_id')
            ? (int) $request->input('warehouse_id')
            : (int) ($availableWarehouses->first()?->id ?? 0);

        if ($authorizedWarehouseIds !== null && ! in_array($selectedWarehouseId, $authorizedWarehouseIds, true)) {
            $selectedWarehouseId = (int) ($availableWarehouses->first()?->id ?? 0);
        }

        [$selectedFromDate, $selectedToDate] = $this->resolveDateRange($request);
        $selectedDate = $selectedFromDate;

        $sort = (string) $request->input('sort', 'business_date');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $cursor = $request->filled('cursor') ? (int) $request->input('cursor') : null;
        $batchSize = 100;

        // 2. Build Range Plan (evaluates each business day independently)
        $plan = null;
        if ($selectedWarehouseId > 0) {
            $plan = $this->planningService->buildRangePlan(
                $selectedWarehouseId,
                $selectedFromDate,
                $selectedToDate,
                $cursor,
                $batchSize,
                $user->id,
                $sort,
                $direction
            );
        }

        // 3. Query Recent Run History across selected date period
        $recentRuns = DailyAdvanceMatchRun::query()
            ->when($selectedWarehouseId > 0, fn ($q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->whereDate('bill_date', '>=', $selectedFromDate)
            ->whereDate('bill_date', '<=', $selectedToDate)
            ->with(['requestedBy:id,name'])
            ->latest()
            ->limit(20)
            ->get();

        return view('admin.cashbook.reports.daily-auto-match', [
            'availableWarehouses' => $availableWarehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'selectedDate' => $selectedDate,
            'selectedFromDate' => $selectedFromDate,
            'selectedToDate' => $selectedToDate,
            'isDateRange' => $selectedFromDate !== $selectedToDate,
            'sort' => $sort,
            'direction' => $direction,
            'cursor' => $cursor,
            'batchSize' => $batchSize,
            'plan' => $plan,
            'recentRuns' => $recentRuns,
            'activeTab' => 'daily_auto_match',
        ]);
    }

    /**
     * Get deterministic daily auto-match plan preview (JSON).
     */
    public function preview(Request $request): JsonResponse
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'date' => ['nullable', 'date'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'string', 'in:asc,desc,ASC,DESC'],
            'cursor' => ['nullable', 'integer', 'min:0'],
            'batch_size' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $warehouseId = (int) $validated['warehouse_id'];
        $authorizedWarehouseIds = $this->readScope->warehouseIds($request->user(), $warehouseId);
        if ($authorizedWarehouseIds !== null && ! in_array($warehouseId, $authorizedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        [$fromDate, $toDate] = $this->resolveDateRange($request);
        $sort = $validated['sort'] ?? 'business_date';
        $direction = isset($validated['direction']) && strtolower($validated['direction']) === 'asc' ? 'asc' : 'desc';
        $cursor = isset($validated['cursor']) ? (int) $validated['cursor'] : null;
        $batchSize = isset($validated['batch_size']) ? (int) $validated['batch_size'] : 100;

        $plan = $this->planningService->buildRangePlan(
            $warehouseId,
            $fromDate,
            $toDate,
            $cursor,
            $batchSize,
            (int) $request->user()->id,
            $sort,
            $direction
        );

        return response()->json([
            'status' => 'success',
            'data' => $plan,
        ]);
    }

    /**
     * Execute daily auto-match plan idempotently (JSON).
     */
    public function execute(Request $request): JsonResponse
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'date' => ['nullable', 'date'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'plan_hash' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i'],
            'client_submission_id' => ['required', 'string', 'uuid'],
            'cursor' => ['nullable', 'integer', 'min:0'],
            'batch_size' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $warehouseId = (int) $validated['warehouse_id'];
        $authorizedWarehouseIds = $this->readScope->warehouseIds($request->user(), $warehouseId);
        if ($authorizedWarehouseIds !== null && ! in_array($warehouseId, $authorizedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        [$fromDate, $toDate] = $this->resolveDateRange($request);
        $cursor = isset($validated['cursor']) ? (int) $validated['cursor'] : null;
        $batchSize = isset($validated['batch_size']) ? (int) $validated['batch_size'] : 100;

        $result = $this->executionService->executeRange(
            $warehouseId,
            $fromDate,
            $toDate,
            (string) $validated['plan_hash'],
            (string) $validated['client_submission_id'],
            (int) $request->user()->id,
            $cursor,
            $batchSize
        );

        if (isset($result['status_code']) && $result['status_code'] === 409) {
            return response()->json([
                'status' => 'conflict',
                'message' => 'The daily match plan is stale or conflicted with concurrent receipts. Please review the updated preview.',
                'error' => $result['error'] ?? null,
            ], 409);
        }

        if (isset($result['status_code']) && $result['status_code'] === 422) {
            return response()->json([
                'status' => 'unprocessable',
                'message' => $result['error']['message'] ?? 'Unable to process daily match.',
                'error' => $result['error'] ?? null,
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Daily auto match completed successfully.',
            'data' => $result,
        ]);
    }

    /**
     * Resolve from_date and to_date from request supporting backwards compatibility with date=.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveDateRange(Request $request): array
    {
        if ($request->filled('from_date')) {
            $fromDate = Carbon::parse((string) $request->input('from_date'))->toDateString();
            $toDate = $request->filled('to_date')
                ? Carbon::parse((string) $request->input('to_date'))->toDateString()
                : $fromDate;
        } elseif ($request->filled('date')) {
            $fromDate = Carbon::parse((string) $request->input('date'))->toDateString();
            $toDate = $fromDate;
        } elseif ($request->filled('to_date')) {
            $toDate = Carbon::parse((string) $request->input('to_date'))->toDateString();
            $fromDate = $toDate;
        } else {
            $fromDate = today()->toDateString();
            $toDate = $fromDate;
        }

        if ($fromDate > $toDate) {
            $tmp = $fromDate;
            $fromDate = $toDate;
            $toDate = $tmp;
        }

        return [$fromDate, $toDate];
    }

    /**
     * Security guard for Admin Cashbook Reports.
     */
    private function ensureAuthorized(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User && (
                $user->isMainAdmin()
                || $user->hasRole('admin')
                || $user->hasRole('accounts')
                || $user->hasRole('accountant')
                || $user->hasRole('account')
                || $user->hasRole('warehouse_receiver')
                || $user->hasAnyPermission([
                    'accounting.report.view',
                    'accounting.dashboard.view',
                    'accounting.ledger.view',
                    'finance.dashboard.view',
                    'warehouse.receive.view',
                    'purchasing.grn.view',
                ])
            ),
            403
        );
    }
}
