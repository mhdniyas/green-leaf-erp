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

        $selectedDate = $request->filled('date')
            ? Carbon::parse((string) $request->input('date'))->toDateString()
            : today()->toDateString();

        $cursor = $request->filled('cursor') ? (int) $request->input('cursor') : null;
        $batchSize = 100;

        // 2. Build Daily Plan
        $plan = null;
        if ($selectedWarehouseId > 0) {
            $plan = $this->planningService->buildDailyPlan(
                $selectedWarehouseId,
                $selectedDate,
                $cursor,
                $batchSize,
                $user->id
            );
        }

        // 3. Query Recent Run History
        $recentRuns = DailyAdvanceMatchRun::query()
            ->when($selectedWarehouseId > 0, fn ($q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->whereDate('bill_date', $selectedDate)
            ->with(['requestedBy:id,name'])
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.cashbook.reports.daily-auto-match', [
            'availableWarehouses' => $availableWarehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'selectedDate' => $selectedDate,
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
            'date' => ['required', 'date'],
            'cursor' => ['nullable', 'integer', 'min:0'],
            'batch_size' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $warehouseId = (int) $validated['warehouse_id'];
        $authorizedWarehouseIds = $this->readScope->warehouseIds($request->user(), $warehouseId);
        if ($authorizedWarehouseIds !== null && ! in_array($warehouseId, $authorizedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        $billDate = Carbon::parse((string) $validated['date'])->toDateString();
        $cursor = isset($validated['cursor']) ? (int) $validated['cursor'] : null;
        $batchSize = isset($validated['batch_size']) ? (int) $validated['batch_size'] : 100;

        $plan = $this->planningService->buildDailyPlan(
            $warehouseId,
            $billDate,
            $cursor,
            $batchSize,
            (int) $request->user()->id
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
            'date' => ['required', 'date'],
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

        $billDate = Carbon::parse((string) $validated['date'])->toDateString();
        $cursor = isset($validated['cursor']) ? (int) $validated['cursor'] : null;
        $batchSize = isset($validated['batch_size']) ? (int) $validated['batch_size'] : 100;

        $result = $this->executionService->execute(
            $warehouseId,
            $billDate,
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
