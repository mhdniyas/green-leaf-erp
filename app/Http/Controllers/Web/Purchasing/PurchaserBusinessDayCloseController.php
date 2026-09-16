<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceived;
use App\Models\PurchaseBusinessDay;
use App\Services\Purchasing\DailyInventoryComparisonService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaserBusinessDayCloseController extends Controller
{
    public function __construct(
        private readonly PurchaserBusinessDayService $businessDayService,
        private readonly DailyInventoryComparisonService $comparisonService,
    ) {}

    /**
     * Close Day main review & verification dashboard.
     */
    public function index(Request $request, string $uuid): View|RedirectResponse
    {
        $this->authorizeAccess();

        $day = $this->resolveBusinessDay($uuid);
        $data = $this->buildCloseDayData($day);

        return view('purchaser.business-days.close.index', array_merge(['day' => $day], $data));
    }

    /**
     * Detail: Purchase Bills for this business day.
     */
    public function bills(Request $request, string $uuid): View
    {
        $this->authorizeAccess();

        $day = $this->resolveBusinessDay($uuid);
        $bills = $this->queryBillsForDay($day);

        return view('purchaser.business-days.close.bills', [
            'day' => $day,
            'warehouse' => $day->warehouse,
            'bills' => $bills,
        ]);
    }

    /**
     * Detail: Advance Receives for this business day.
     */
    public function advances(Request $request, string $uuid): View
    {
        $this->authorizeAccess();

        $day = $this->resolveBusinessDay($uuid);
        $advanceReceives = $this->queryAdvancesForDay($day);

        return view('purchaser.business-days.close.advances', [
            'day' => $day,
            'warehouse' => $day->warehouse,
            'advanceReceives' => $advanceReceives,
        ]);
    }

    /**
     * Detail: Pending Products for this business day.
     */
    public function pending(Request $request, string $uuid): View
    {
        $this->authorizeAccess();

        $day = $this->resolveBusinessDay($uuid);
        $comparisonRows = $this->comparisonService->buildComparisonRows($day->business_date->toDateString(), (int) $day->warehouse_id);
        $pendingList = $this->buildPendingWorklist($comparisonRows);

        return view('purchaser.business-days.close.pending', [
            'day' => $day,
            'warehouse' => $day->warehouse,
            'pendingList' => $pendingList,
        ]);
    }

    /**
     * Detail: Unit Issues for this business day.
     */
    public function issues(Request $request, string $uuid): View
    {
        $this->authorizeAccess();

        $day = $this->resolveBusinessDay($uuid);
        $comparisonRows = $this->comparisonService->buildComparisonRows($day->business_date->toDateString(), (int) $day->warehouse_id);
        $unitIssues = $comparisonRows->where('unit_mismatch', true)->values();

        return view('purchaser.business-days.close.issues', [
            'day' => $day,
            'warehouse' => $day->warehouse,
            'unitIssues' => $unitIssues,
        ]);
    }

    /**
     * Detail: Inventory Reconciliation for this business day.
     */
    public function inventory(Request $request, string $uuid): View
    {
        $this->authorizeAccess();

        $day = $this->resolveBusinessDay($uuid);
        $comparisonRows = $this->comparisonService->buildComparisonRows($day->business_date->toDateString(), (int) $day->warehouse_id);
        $summary = $this->comparisonService->calculateSummary($comparisonRows);

        return view('purchaser.business-days.close.inventory', [
            'day' => $day,
            'warehouse' => $day->warehouse,
            'comparisonRows' => $comparisonRows,
            'summary' => $summary,
        ]);
    }

    /**
     * Detail: Cancelled Purchases for this business day.
     */
    public function cancelled(Request $request, string $uuid): View
    {
        $this->authorizeAccess();

        $day = $this->resolveBusinessDay($uuid);
        $cancelledPurchases = $this->queryCancelledForDay($day);

        return view('purchaser.business-days.close.cancelled', [
            'day' => $day,
            'warehouse' => $day->warehouse,
            'cancelledPurchases' => $cancelledPurchases,
        ]);
    }

    /**
     * Perform Close Day action.
     */
    public function close(Request $request, string $uuid): RedirectResponse
    {
        $this->authorizeAccess();

        $day = $this->resolveBusinessDay($uuid);

        if ($day->isClosed()) {
            return redirect()->route('purchaser.business-days.close.index', $day->uuid)
                ->with('error', 'This Business Day is already closed.');
        }

        $warehouseSettings = $this->businessDayService->getWarehouseSettings((int) $day->warehouse_id);

        $isPurchaser = $request->user()->hasRole('purchase') || $request->user()->hasRole('purchaser');
        $isAdmin = $request->user()->hasRole('admin') || $request->user()->isMainAdmin();
        if ($isPurchaser && ! $isAdmin && ! ($warehouseSettings['purchasers_can_close'] ?? true)) {
            return redirect()->back()->with('error', 'Purchaser closing is disabled for this warehouse in Company Settings.');
        }

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

        try {
            $this->businessDayService->close($day, (int) $request->user()->id, $closeNote);

            return redirect()->route('purchasing.business-days.show', $day->uuid)
                ->with('success', "Business Day for {$day->business_date->format('d M Y')} has been verified and closed.");
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        }
    }

    private function resolveBusinessDay(string $uuid): PurchaseBusinessDay
    {
        return PurchaseBusinessDay::query()
            ->with(['warehouse', 'openedBy', 'closedBy', 'reopenedBy'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * Build aggregated data for the Close Day main screen.
     *
     * @return array<string, mixed>
     */
    private function buildCloseDayData(PurchaseBusinessDay $day): array
    {
        $dateStr = $day->business_date->toDateString();
        $warehouseId = (int) $day->warehouse_id;

        $comparisonRows = $this->comparisonService->buildComparisonRows($dateStr, $warehouseId);
        $summary = $this->comparisonService->calculateSummary($comparisonRows);
        $pendingList = $this->buildPendingWorklist($comparisonRows);
        $unitIssues = $comparisonRows->where('unit_mismatch', true)->values();

        $bills = $this->queryBillsForDay($day);
        $advanceReceives = $this->queryAdvancesForDay($day);
        $cancelledPurchases = $this->queryCancelledForDay($day);
        $warehouseSettings = $this->businessDayService->getWarehouseSettings($warehouseId);

        return [
            'warehouse' => $day->warehouse,
            'comparisonRows' => $comparisonRows,
            'summary' => $summary,
            'pendingList' => $pendingList,
            'unitIssues' => $unitIssues,
            'bills' => $bills,
            'advanceReceives' => $advanceReceives,
            'cancelledPurchases' => $cancelledPurchases,
            'warehouseSettings' => $warehouseSettings,
            'isClean' => $pendingList->isEmpty() && $unitIssues->isEmpty(),
        ];
    }

    /**
     * @return Collection<int, GoodsReceived>
     */
    private function queryBillsForDay(PurchaseBusinessDay $day): Collection
    {
        $dateStr = $day->business_date->toDateString();
        $warehouseId = (int) $day->warehouse_id;

        return GoodsReceived::query()
            ->with(['items.product', 'items.purchaseOrderItem', 'purchaseOrder.supplier', 'purchaseInvoices', 'receivedBy'])
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
    }

    /**
     * @return Collection<int, GoodsReceived>
     */
    private function queryAdvancesForDay(PurchaseBusinessDay $day): Collection
    {
        $dateStr = $day->business_date->toDateString();
        $warehouseId = (int) $day->warehouse_id;

        return GoodsReceived::query()
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
    }

    /**
     * @return Collection<int, GoodsReceived>
     */
    private function queryCancelledForDay(PurchaseBusinessDay $day): Collection
    {
        $dateStr = $day->business_date->toDateString();
        $warehouseId = (int) $day->warehouse_id;

        return GoodsReceived::query()
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
    }

    /**
     * Build the canonical pending worklist.
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
}
