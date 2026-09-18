<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceived;
use App\Models\Product;
use App\Models\PurchaseBusinessDay;
use App\Models\PurchaseBusinessDayCarryForward;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Purchasing\DailyInventoryComparisonService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchasingBusinessDayController extends Controller
{
    public function __construct(
        private readonly PurchaserBusinessDayService $businessDayService,
        private readonly DailyInventoryComparisonService $comparisonService,
    ) {}

    /**
     * Main Monthly Control Board / Active Day Router.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $this->authorizeAccess();

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedWarehouseId = $request->filled('warehouse_id')
            ? $request->integer('warehouse_id')
            : (int) ($warehouses->first()?->id ?? 1);

        $wantsHistory = $request->boolean('history') || $request->input('view') === 'history' || $request->filled('month') || $request->filled('status') || $request->filled('pending_only');
        if (! $wantsHistory) {
            $activeDay = $this->businessDayService->getActiveForWarehouse($selectedWarehouseId);
            if ($activeDay) {
                return redirect()->route('purchasing.business-days.show', $activeDay->uuid);
            }

            $todayStr = $this->businessDayService->operationalDate()->toDateString();
            $todayDay = PurchaseBusinessDay::query()
                ->where('warehouse_id', $selectedWarehouseId)
                ->whereDate('business_date', $todayStr)
                ->latest('id')
                ->first();
            if ($todayDay) {
                return redirect()->route('purchasing.business-days.show', $todayDay->uuid);
            }
        }

        $monthInput = $request->input('month', now()->format('Y-m'));
        $monthCarbon = Carbon::createFromFormat('Y-m', $monthInput) ?: now();
        $startOfMonth = $monthCarbon->copy()->startOfMonth()->toDateString();
        $endOfMonth = $monthCarbon->copy()->endOfMonth()->toDateString();

        $statusFilter = (string) $request->input('status', 'all');
        $pendingOnly = $request->boolean('pending_only', false);

        // 1. Active Day Header for selected warehouse
        $activeDay = $this->businessDayService->getActiveForWarehouse($selectedWarehouseId);
        $selectedWarehouse = $warehouses->firstWhere('id', $selectedWarehouseId) ?? $warehouses->first();
        $warehouseSettings = $this->businessDayService->getWarehouseSettings($selectedWarehouseId);

        // 2. Query all business days for this month and warehouse
        $daysQuery = PurchaseBusinessDay::query()
            ->with(['openedBy', 'closedBy', 'reopenedBy', 'warehouse'])
            ->whereBetween('business_date', [$startOfMonth, $endOfMonth])
            ->when($selectedWarehouseId > 0, fn ($q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->when(in_array($statusFilter, [PurchaseBusinessDay::STATUS_OPEN, PurchaseBusinessDay::STATUS_CLOSED, PurchaseBusinessDay::STATUS_REOPENED], true), fn ($q) => $q->where('status', $statusFilter))
            ->orderByDesc('business_date')
            ->orderByDesc('id');

        $businessDays = $daysQuery->get();

        // 3. Compute canonical comparison summaries for each business day in the month
        $daysData = $businessDays->map(function (PurchaseBusinessDay $day): array {
            $dateStr = $day->business_date->toDateString();
            $warehouseId = (int) $day->warehouse_id;

            // Compute comparison rows from canonical service
            $rows = $this->comparisonService->buildComparisonRows($dateStr, $warehouseId);
            $summary = $this->comparisonService->calculateSummary($rows);

            // Bills and Advance counts
            $billsCount = GoodsReceived::query()
                ->where(function ($sub): void {
                    $sub->where('receipt_type', '!=', 'warehouse_advance')->orWhereNull('receipt_type');
                })
                ->where('status', '!=', 'cancelled')
                ->where(function ($q) use ($day, $dateStr, $warehouseId): void {
                    $q->where('business_day_id', $day->id)
                        ->orWhere(function ($sub) use ($dateStr, $warehouseId): void {
                            $sub->whereNull('business_day_id')
                                ->whereDate('received_at', $dateStr)
                                ->whereHas('items.product', fn ($pq) => $pq->where('default_warehouse_id', $warehouseId));
                        });
                })
                ->count();

            $advanceCount = GoodsReceived::query()
                ->where('receipt_type', 'warehouse_advance')
                ->where('status', '!=', 'cancelled')
                ->where(function ($q) use ($day, $dateStr, $warehouseId): void {
                    $q->where('business_day_id', $day->id)
                        ->orWhere(function ($sub) use ($dateStr, $warehouseId): void {
                            $sub->whereNull('business_day_id')
                                ->whereDate('received_at', $dateStr)
                                ->where('warehouse_id', $warehouseId);
                        });
                })
                ->count();

            $productsCount = $rows->count();

            // Fully Billed = Products with advance where pending is 0
            $fullyBilledCount = $rows->filter(function (array $r): bool {
                $adv = (float) ($r['advance_qty'] ?? 0);
                $matched = (float) ($r['matched_bill_qty'] ?? 0);

                return $adv > 0 && max(0.0, $adv - $matched) <= 0.0001 && ! ($r['unit_mismatch'] ?? false);
            })->count();

            // Pending = Products with pending advance to be billed (or unmatched bill)
            $pendingCount = $rows->filter(function (array $r): bool {
                $adv = (float) ($r['advance_qty'] ?? 0);
                $matched = (float) ($r['matched_bill_qty'] ?? 0);
                $pendingAdv = max(0.0, $adv - $matched);
                $unmatchedBill = (float) ($r['unmatched_bill_qty'] ?? 0);

                return $pendingAdv > 0.0001 || $unmatchedBill > 0.0001 || ($r['unit_mismatch'] ?? false);
            })->count();

            $unitIssuesCount = (int) ($summary['unit_fix_count'] ?? 0);

            $carryForwards = PurchaseBusinessDayCarryForward::where('origin_business_day_id', $day->id)->get();
            $totalCarryCount = $carryForwards->count();
            $openCarryCount = $carryForwards->where('status', PurchaseBusinessDayCarryForward::STATUS_OPEN)->count();

            return [
                'day' => $day,
                'business_date' => $day->business_date,
                'status' => $day->status,
                'close_mode' => $day->close_mode,
                'bills_count' => $billsCount,
                'advance_count' => $advanceCount,
                'products_count' => $productsCount,
                'fully_billed_count' => $fullyBilledCount,
                'pending_count' => $pendingCount,
                'unit_issues_count' => $unitIssuesCount,
                'total_carry_count' => $totalCarryCount,
                'open_carry_count' => $openCarryCount,
                'summary' => $summary,
            ];
        });

        if ($pendingOnly) {
            $daysData = $daysData->filter(fn (array $d): bool => $d['pending_count'] > 0 || $d['unit_issues_count'] > 0)->values();
        }

        return view('purchaser.business-days.index', [
            'warehouses' => $warehouses,
            'selectedWarehouse' => $selectedWarehouse,
            'selectedWarehouseId' => $selectedWarehouseId,
            'month' => $monthInput,
            'statusFilter' => $statusFilter,
            'pendingOnly' => $pendingOnly,
            'activeDay' => $activeDay,
            'warehouseSettings' => $warehouseSettings,
            'daysData' => $daysData,
        ]);
    }

    /**
     * Open a new Business Day.
     */
    public function open(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'business_date' => 'required|date_format:Y-m-d',
        ]);

        try {
            $day = $this->businessDayService->open(
                (int) $validated['warehouse_id'],
                $validated['business_date'],
                (int) $request->user()->id
            );

            return redirect()->route('purchasing.business-days.show', $day->uuid)
                ->with('success', "Business Day for {$day->business_date->format('d M Y')} opened successfully.");
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        }
    }

    /**
     * Business Day Detail Page.
     */
    public function show(Request $request, string $uuid): View
    {
        $this->authorizeAccess();

        /** @var PurchaseBusinessDay $day */
        $day = PurchaseBusinessDay::query()
            ->with(['warehouse', 'openedBy', 'closedBy', 'reopenedBy'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $dateStr = $day->business_date->toDateString();
        $warehouseId = (int) $day->warehouse_id;

        // Canonical comparison data
        $comparisonRows = $this->comparisonService->buildComparisonRows($dateStr, $warehouseId);
        $summary = $this->comparisonService->calculateSummary($comparisonRows);
        $managerSummary = $this->comparisonService->getDailyManagerSummary($dateStr, $warehouseId);
        $warehouseSettings = $this->businessDayService->getWarehouseSettings($warehouseId);

        // Build Pending worklist
        $pendingList = $this->buildPendingWorklist($comparisonRows);

        // Query Bills for this business day
        $bills = GoodsReceived::query()
            ->with(['items.product', 'purchaseOrder.supplier', 'receivedBy'])
            ->where(function ($sub): void {
                $sub->where('receipt_type', '!=', 'warehouse_advance')->orWhereNull('receipt_type');
            })
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($day, $dateStr, $warehouseId): void {
                $q->where('business_day_id', $day->id)
                    ->orWhere(function ($sub) use ($dateStr, $warehouseId): void {
                        $sub->whereNull('business_day_id')
                            ->whereDate('received_at', $dateStr)
                            ->whereHas('items.product', fn ($pq) => $pq->where('default_warehouse_id', $warehouseId));
                    });
            })
            ->orderByDesc('id')
            ->get();

        // Query Advance Receives for this business day
        $advanceReceives = GoodsReceived::query()
            ->with(['items.product', 'receivedBy', 'warehouse'])
            ->where('receipt_type', 'warehouse_advance')
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($day, $dateStr, $warehouseId): void {
                $q->where('business_day_id', $day->id)
                    ->orWhere(function ($sub) use ($dateStr, $warehouseId): void {
                        $sub->whereNull('business_day_id')
                            ->whereDate('received_at', $dateStr)
                            ->where('warehouse_id', $warehouseId);
                    });
            })
            ->orderByDesc('id')
            ->get();

        // Query Cancelled Purchases for this business day
        $cancelledPurchases = GoodsReceived::query()
            ->with(['items.product', 'purchaseOrder.supplier', 'receivedBy'])
            ->where('status', 'cancelled')
            ->where(function ($q) use ($day, $dateStr, $warehouseId): void {
                $q->where('business_day_id', $day->id)
                    ->orWhere(function ($sub) use ($dateStr, $warehouseId): void {
                        $sub->whereNull('business_day_id')
                            ->whereDate('received_at', $dateStr)
                            ->where('warehouse_id', $warehouseId);
                    });
            })
            ->orderByDesc('id')
            ->get();

        // Query open carry-forward tasks from older Business Days for the same warehouse
        $openCarryRecords = PurchaseBusinessDayCarryForward::query()
            ->with(['originBusinessDay', 'product'])
            ->where('warehouse_id', $day->warehouse_id)
            ->where('origin_business_day_id', '!=', $day->id)
            ->where('status', PurchaseBusinessDayCarryForward::STATUS_OPEN)
            ->get();

        $uniqueOriginDays = $openCarryRecords->pluck('originBusinessDay')->filter()->unique('id');
        $originRowsByDayId = [];
        foreach ($uniqueOriginDays as $oDay) {
            $originRowsByDayId[$oDay->id] = $this->comparisonService->buildComparisonRows(
                $oDay->business_date->toDateString(),
                (int) $day->warehouse_id
            );
        }

        $openCarryForwards = $openCarryRecords
            ->map(function (PurchaseBusinessDayCarryForward $carry) use ($originRowsByDayId): array {
                $originDay = $carry->originBusinessDay;
                $canonicalRows = $originRowsByDayId[$carry->origin_business_day_id] ?? collect();
                $row = $canonicalRows->firstWhere('product_id', $carry->product_id);
                $adv = $row ? (float) ($row['advance_qty'] ?? 0) : 0.0;
                $matched = $row ? (float) ($row['matched_bill_qty'] ?? 0) : 0.0;
                $pending = max(0.0, $adv - $matched);
                $unit = $row ? (string) ($row['unit'] ?? $carry->unit) : $carry->unit;

                return [
                    'id' => $carry->id,
                    'uuid' => $carry->uuid,
                    'origin_business_day_id' => $carry->origin_business_day_id,
                    'origin_day_uuid' => $originDay?->uuid ?? '',
                    'origin_date_formatted' => $originDay?->business_date?->format('d M') ?? '',
                    'product_id' => $carry->product_id,
                    'product_name' => $carry->product?->name ?? 'Product',
                    'sku' => $carry->product?->sku ?? '',
                    'pending_qty_at_close' => $carry->pending_qty_at_close,
                    'current_pending' => $pending,
                    'unit' => $unit,
                    'formatted_pending' => $this->formatNumber($pending).' '.$unit,
                    'is_resolved' => $pending <= 0.0001,
                ];
            })->filter(fn (array $item): bool => ! $item['is_resolved'])->values();

        // Query carry-forward records originating from this business day (if closed/reopened)
        $dayCarryForwards = PurchaseBusinessDayCarryForward::query()
            ->with('product')
            ->where('origin_business_day_id', $day->id)
            ->get()
            ->map(function (PurchaseBusinessDayCarryForward $carry) use ($comparisonRows): array {
                $row = $comparisonRows->firstWhere('product_id', $carry->product_id);
                $adv = $row ? (float) ($row['advance_qty'] ?? 0) : 0.0;
                $matched = $row ? (float) ($row['matched_bill_qty'] ?? 0) : 0.0;
                $pending = max(0.0, $adv - $matched);
                $unit = $row ? (string) ($row['unit'] ?? $carry->unit) : $carry->unit;

                return [
                    'id' => $carry->id,
                    'product_id' => $carry->product_id,
                    'product_name' => $carry->product?->name ?? 'Product',
                    'sku' => $carry->product?->sku ?? '',
                    'pending_qty_at_close' => $carry->pending_qty_at_close,
                    'current_pending' => $pending,
                    'unit' => $unit,
                    'formatted_pending_at_close' => $this->formatNumber($carry->pending_qty_at_close).' '.$unit,
                    'formatted_current_pending' => $this->formatNumber($pending).' '.$unit,
                    'status' => $carry->status,
                    'is_resolved' => $carry->isResolved() || $pending <= 0.0001,
                ];
            });

        return view('purchaser.business-days.show', [
            'day' => $day,
            'warehouse' => $day->warehouse,
            'comparisonRows' => $comparisonRows,
            'summary' => $summary,
            'managerSummary' => $managerSummary,
            'pendingList' => $pendingList,
            'bills' => $bills,
            'advanceReceives' => $advanceReceives,
            'cancelledPurchases' => $cancelledPurchases,
            'warehouseSettings' => $warehouseSettings,
            'openCarryForwards' => $openCarryForwards,
            'dayCarryForwards' => $dayCarryForwards,
        ]);
    }

    /**
     * Verify and Close Business Day.
     */
    public function verifyAndClose(Request $request, string $uuid): RedirectResponse
    {
        $this->authorizeAccess();

        /** @var PurchaseBusinessDay $day */
        $day = PurchaseBusinessDay::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        if ($day->isClosed()) {
            return redirect()->route('purchasing.business-days.show', $day->uuid)
                ->with('error', 'This Business Day is already closed.');
        }

        $warehouseSettings = $this->businessDayService->getWarehouseSettings((int) $day->warehouse_id);

        // Check if user is allowed to close
        $isPurchaser = $request->user()->hasRole('purchase');
        $isAdmin = $request->user()->hasRole('admin') || $request->user()->isMainAdmin();
        if ($isPurchaser && ! $isAdmin && ! ($warehouseSettings['purchasers_can_close'] ?? true)) {
            return redirect()->back()->with('error', 'Purchaser closing is disabled for this warehouse in Company Settings.');
        }

        // Run verification check
        $comparisonRows = $this->comparisonService->buildComparisonRows($day->business_date->toDateString(), (int) $day->warehouse_id);
        $pendingList = $this->buildPendingWorklist($comparisonRows);
        $hasPending = $pendingList->isNotEmpty();

        if ($hasPending && ! ($warehouseSettings['allow_close_with_pending'] ?? true) && ! $isAdmin) {
            return redirect()->back()->with('error', 'Closing with pending items is disabled for this warehouse. Resolve all pending advance bills before closing.');
        }

        if ($hasPending) {
            $request->validate([
                'close_note' => 'required|string|min:3|max:1000',
            ], [
                'close_note.required' => 'A reason/note is required when closing a business day with pending items.',
            ]);
        }

        $closeNote = $request->input('close_note');
        $closeMode = $request->input('close_mode', 'carry_forward');

        try {
            $this->businessDayService->close($day, (int) $request->user()->id, $closeNote, $closeMode);

            $msg = $hasPending && $closeMode === 'carry_forward'
                ? "Business Day for {$day->business_date->format('d M Y')} closed and pending tasks carried forward."
                : "Business Day for {$day->business_date->format('d M Y')} has been verified and closed.";

            return redirect()->route('purchasing.business-days.show', $day->uuid)
                ->with('success', $msg);
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        }
    }

    /**
     * Reopen a closed Business Day.
     */
    public function reopen(Request $request, string $uuid): RedirectResponse
    {
        $this->authorizeAccess();

        /** @var PurchaseBusinessDay $day */
        $day = PurchaseBusinessDay::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        if (! $day->isClosed()) {
            return redirect()->route('purchasing.business-days.show', $day->uuid)
                ->with('error', 'Only closed Business Days can be reopened.');
        }

        $warehouseSettings = $this->businessDayService->getWarehouseSettings((int) $day->warehouse_id);

        $isPurchaser = $request->user()->hasRole('purchase');
        $isAdmin = $request->user()->hasRole('admin') || $request->user()->isMainAdmin();

        if ($isPurchaser && ! $isAdmin && ! ($warehouseSettings['purchasers_can_reopen'] ?? true)) {
            return redirect()->back()->with('error', 'Purchasers cannot reopen closed Business Days for this warehouse.');
        }

        $request->validate([
            'reopen_reason' => 'required|string|min:3|max:1000',
        ], [
            'reopen_reason.required' => 'A mandatory reason is required to reopen a closed Business Day.',
        ]);

        try {
            $this->businessDayService->reopen($day, (int) $request->user()->id, (string) $request->input('reopen_reason'));

            return redirect()->route('purchasing.business-days.show', $day->uuid)
                ->with('success', "Business Day for {$day->business_date->format('d M Y')} has been reopened.");
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        }
    }

    /**
     * Show the Pending-First Bill Entry Form.
     */
    public function createBill(Request $request, string $uuid): View|RedirectResponse
    {
        $this->authorizeAccess();

        /** @var PurchaseBusinessDay $day */
        $day = PurchaseBusinessDay::query()
            ->with(['warehouse'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $carryUuid = (string) ($request->query('carry_forward') ?: $request->input('carry_forward_uuid', ''));
        $carryRecord = null;

        if ($carryUuid !== '') {
            $carryRecord = PurchaseBusinessDayCarryForward::query()->where('uuid', $carryUuid)->first();
        }

        if ($day->isClosed()) {
            if (! $carryRecord) {
                return redirect()->route('purchasing.business-days.show', $day->uuid)
                    ->with('error', 'Business Day is closed. An explicit carry_forward_uuid parameter is required to add a bill on a closed day.');
            }

            if ((int) $carryRecord->origin_business_day_id !== (int) $day->id
                || (int) $carryRecord->warehouse_id !== (int) $day->warehouse_id
                || ! $carryRecord->isOpen()
            ) {
                return redirect()->route('purchasing.business-days.show', $day->uuid)
                    ->with('error', 'Invalid or resolved carry-forward task for this closed business day.');
            }
        }

        $suppliers = $this->getPurchaserScopedSuppliers();
        $allProducts = Product::query()->where('is_active', true)->orderBy('name')->get();

        // Calculate pending worklist to pre-populate items
        $comparisonRows = $this->comparisonService->buildComparisonRows($day->business_date->toDateString(), (int) $day->warehouse_id);
        $pendingWorklist = $this->buildPendingWorklist($comparisonRows);

        $prefilledItems = [];

        if ($carryRecord) {
            $row = $comparisonRows->firstWhere('product_id', $carryRecord->product_id);
            $adv = $row ? (float) ($row['advance_qty'] ?? 0) : 0.0;
            $matched = $row ? (float) ($row['matched_bill_qty'] ?? 0) : 0.0;
            $currentPending = max(0.0, $adv - $matched);

            if ($currentPending <= 0.0001 && $day->isClosed()) {
                return redirect()->route('purchasing.business-days.show', $day->uuid)
                    ->with('error', 'Carry-forward task is already resolved.');
            }

            $product = $allProducts->firstWhere('id', $carryRecord->product_id);
            if ($product) {
                $unit = $row ? (string) ($row['unit'] ?? $carryRecord->unit) : $carryRecord->unit;
                $prefilledItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'pending_qty' => $currentPending,
                    'received_qty' => $currentPending > 0 ? $currentPending : 1.0,
                    'received_unit' => $unit,
                    'unit_price' => 0.0,
                ];
            }
        } else {
            // Requested pre-filled product IDs
            $requestedProductIds = $request->input('product_ids', []);
            if (is_numeric($request->input('product_id'))) {
                $requestedProductIds[] = (int) $request->input('product_id');
            }
            $requestedProductIds = array_values(array_unique(array_map('intval', (array) $requestedProductIds)));

            if (! empty($requestedProductIds)) {
                foreach ($requestedProductIds as $pId) {
                    $pendingItem = $pendingWorklist->firstWhere('product_id', $pId);
                    $product = $allProducts->firstWhere('id', $pId);
                    if ($product) {
                        $pendingQty = $pendingItem ? (float) $pendingItem['pending_qty'] : 0.0;
                        $unit = $pendingItem ? (string) $pendingItem['unit'] : ($product->unit ?? 'kg');
                        $prefilledItems[] = [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'sku' => $product->sku,
                            'pending_qty' => $pendingQty,
                            'received_qty' => $pendingQty > 0 ? $pendingQty : 1.0,
                            'received_unit' => $unit,
                            'unit_price' => 0.0,
                        ];
                    }
                }
            } elseif ($pendingWorklist->isNotEmpty()) {
                foreach ($pendingWorklist as $pItem) {
                    $prefilledItems[] = [
                        'product_id' => $pItem['product_id'],
                        'product_name' => $pItem['product_name'],
                        'sku' => $pItem['sku'],
                        'pending_qty' => (float) $pItem['pending_qty'],
                        'received_qty' => (float) $pItem['pending_qty'],
                        'received_unit' => (string) $pItem['unit'],
                        'unit_price' => 0.0,
                    ];
                }
            }
        }

        $openBusinessDays = PurchaseBusinessDay::query()
            ->with('warehouse')
            ->whereIn('status', [PurchaseBusinessDay::STATUS_OPEN, PurchaseBusinessDay::STATUS_REOPENED])
            ->orderByDesc('business_date')
            ->get();

        return view('purchaser.business-days.bills.create', [
            'day' => $day,
            'warehouse' => $day->warehouse,
            'suppliers' => $suppliers,
            'allProducts' => $allProducts,
            'pendingWorklist' => $pendingWorklist,
            'prefilledItems' => $prefilledItems,
            'openBusinessDays' => $openBusinessDays,
            'carryRecord' => $carryRecord,
        ]);
    }

    /**
     * Store a Purchase Bill against a Business Day with automatic inventory and matching.
     */
    public function storeBill(Request $request, string $uuid): RedirectResponse
    {
        $this->authorizeAccess();

        /** @var PurchaseBusinessDay $day */
        $day = PurchaseBusinessDay::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $validated = $request->validate([
            'business_day_id' => 'required|integer|exists:purchase_business_days,id',
            'carry_forward_uuid' => 'nullable|string|max:100',
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'bill_number' => 'nullable|string|max:100',
            'received_at' => 'nullable|date',
            'transport_cost' => 'nullable|numeric|min:0',
            'labour_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.received_qty' => 'required|numeric|min:0.001',
            'items.*.received_unit' => 'nullable|string|max:50',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        // Target business day (can be same or another selected open/reopened day)
        $targetBusinessDayId = (int) $validated['business_day_id'];
        $targetDay = $targetBusinessDayId === (int) $day->id
            ? $day
            : PurchaseBusinessDay::findOrFail($targetBusinessDayId);

        try {
            $grn = $this->businessDayService->createBusinessDayBill(
                $targetDay,
                $validated,
                (int) $request->user()->id
            );

            return redirect()->route('purchasing.business-days.show', $targetDay->uuid)
                ->with('success', "Purchase Bill {$grn->bill_number} recorded, inventory updated, and auto-matched for Business Day {$targetDay->business_date->format('d M Y')}.");
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        }
    }

    /**
     * Edit a Purchase Bill.
     */
    public function editBill(Request $request, string $uuid, GoodsReceived $grn): View|RedirectResponse
    {
        $this->authorizeAccess();

        /** @var PurchaseBusinessDay $day */
        $day = PurchaseBusinessDay::query()
            ->with(['warehouse'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        if ($day->isClosed()) {
            return redirect()->route('purchasing.business-days.show', $day->uuid)
                ->with('error', 'Business Day is closed. Reopen the business day before modifying bills.');
        }

        $grn->load(['items.product', 'purchaseOrder.supplier']);
        $suppliers = $this->getPurchaserScopedSuppliers();
        if ($grn->supplier_id && ! $suppliers->contains('id', $grn->supplier_id)) {
            $existingSupplier = Supplier::find($grn->supplier_id);
            if ($existingSupplier) {
                $suppliers->push($existingSupplier);
                $suppliers = $suppliers->sortBy('name')->values();
            }
        }
        $allProducts = Product::query()->where('is_active', true)->orderBy('name')->get();

        return view('purchaser.business-days.bills.edit', [
            'day' => $day,
            'warehouse' => $day->warehouse,
            'grn' => $grn,
            'suppliers' => $suppliers,
            'allProducts' => $allProducts,
        ]);
    }

    /**
     * Update a Purchase Bill.
     */
    public function updateBill(Request $request, string $uuid, GoodsReceived $grn): RedirectResponse
    {
        $this->authorizeAccess();

        /** @var PurchaseBusinessDay $day */
        $day = PurchaseBusinessDay::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        if ($day->isClosed()) {
            return redirect()->route('purchasing.business-days.show', $day->uuid)
                ->with('error', 'Business Day is closed. Reopen the business day before modifying bills.');
        }

        $validated = $request->validate([
            'bill_number' => 'nullable|string|max:100',
            'transport_cost' => 'nullable|numeric|min:0',
            'labour_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|integer',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.received_qty' => 'required|numeric|min:0.001',
            'items.*.received_unit' => 'nullable|string|max:50',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        try {
            $updatedGrn = $this->businessDayService->updateBusinessDayBill(
                $grn,
                $validated,
                (int) $request->user()->id
            );

            return redirect()->route('purchasing.business-days.show', $day->uuid)
                ->with('success', "Purchase Bill {$updatedGrn->bill_number} updated, stock adjusted, and matching refreshed for Business Day {$day->business_date->format('d M Y')}.");
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        }
    }

    /**
     * Build the canonical pending worklist.
     * Pending = Advance - same-business-day matched qty (min 0).
     *
     * @param  Collection<int, array<string, mixed>>  $comparisonRows
     * @return Collection<int, array<string, mixed>>
     */
    private function buildPendingWorklist(Collection $comparisonRows): Collection
    {
        return $comparisonRows->filter(function (array $r): bool {
            $advQty = (float) ($r['advance_qty'] ?? 0);
            $matchedQty = (float) ($r['matched_bill_qty'] ?? 0);
            $pending = max(0.0, $advQty - $matchedQty);

            return $pending > 0.0001 || ($r['unit_mismatch'] ?? false);
        })->map(function (array $r): array {
            $advQty = (float) ($r['advance_qty'] ?? 0);
            $matchedQty = (float) ($r['matched_bill_qty'] ?? 0);
            $pending = max(0.0, $advQty - $matchedQty);
            $unit = (string) ($r['unit'] ?? 'kg');

            return [
                'product_id' => $r['product_id'],
                'product_name' => $r['product_name'],
                'sku' => $r['sku'] ?? '',
                'unit' => $unit,
                'advance_qty' => $advQty,
                'matched_qty' => $matchedQty,
                'pending_qty' => $pending,
                'formatted_pending' => $this->formatNumber($pending).' '.$unit,
                'unit_mismatch' => (bool) ($r['unit_mismatch'] ?? false),
            ];
        })->values();
    }

    private function formatNumber(float $val): string
    {
        return (abs($val - (int) $val) < 0.0001)
            ? (string) (int) $val
            : rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.');
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(401);
        }

        if ($user->hasRole('admin') || $user->hasRole('purchase') || $user->hasRole('purchaser') || $user->can('purchasing.order.view') || $user->isMainAdmin()) {
            return;
        }

        abort(403, 'Unauthorized access to Purchaser Business Days.');
    }

    /**
     * Helper to retrieve purchaser-scoped suppliers excluding shop-only vendors.
     *
     * @return Collection<int, Supplier>
     */
    private function getPurchaserScopedSuppliers(): Collection
    {
        $user = auth()->user();

        $suppliers = Supplier::query()
            ->where(function ($q): void {
                $q->whereNull('category')
                    ->orWhere('category', '!=', 'shop_vendor');
            })
            ->orderBy('name')
            ->get();

        if ($user && method_exists($user, 'scopedSuppliersQuery')) {
            $scoped = $user->scopedSuppliersQuery()
                ->where(function ($q): void {
                    $q->whereNull('category')
                        ->orWhere('category', '!=', 'shop_vendor');
                })
                ->orderBy('name')
                ->get();

            if ($scoped->isNotEmpty()) {
                $suppliers = $scoped;
            }
        }

        return $suppliers;
    }
}
