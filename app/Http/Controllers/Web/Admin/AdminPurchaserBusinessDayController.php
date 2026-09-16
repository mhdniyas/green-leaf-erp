<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseBusinessDay;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchasing\DailyInventoryComparisonService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminPurchaserBusinessDayController extends Controller
{
    public function __construct(
        private readonly PurchaserBusinessDayService $businessDayService,
        private readonly DailyInventoryComparisonService $comparisonService,
    ) {}

    /**
     * Admin Monthly Oversight Board for Purchaser Business Days.
     */
    public function index(Request $request): View
    {
        $this->authorizeAdmin();

        $warehouses = Warehouse::query()->active()->orderBy('name')->get();
        $purchasers = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['purchase', 'purchaser', 'admin']))
            ->orderBy('name')
            ->get();

        $selectedWarehouseId = $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null;
        $selectedPurchaserId = $request->filled('purchaser_id') ? $request->integer('purchaser_id') : null;
        $statusFilter = (string) $request->input('status', 'all');
        $pendingOnly = $request->boolean('pending_only', false);
        $reopenedOnly = $request->boolean('reopened_only', false);

        $monthInput = $request->input('month', now()->format('Y-m'));
        $monthCarbon = Carbon::createFromFormat('Y-m', $monthInput) ?: now();
        $startOfMonth = $monthCarbon->copy()->startOfMonth()->toDateString();
        $endOfMonth = $monthCarbon->copy()->endOfMonth()->toDateString();

        // Query business days for selected filters
        $daysQuery = PurchaseBusinessDay::query()
            ->with(['warehouse', 'openedBy', 'closedBy', 'reopenedBy'])
            ->whereBetween('business_date', [$startOfMonth, $endOfMonth])
            ->when($selectedWarehouseId !== null && $selectedWarehouseId > 0, fn ($q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->when($selectedPurchaserId !== null && $selectedPurchaserId > 0, fn ($q) => $q->where('opened_by', $selectedPurchaserId))
            ->when(in_array($statusFilter, [PurchaseBusinessDay::STATUS_OPEN, PurchaseBusinessDay::STATUS_CLOSED, PurchaseBusinessDay::STATUS_REOPENED], true), fn ($q) => $q->where('status', $statusFilter))
            ->when($reopenedOnly, fn ($q) => $q->where('status', PurchaseBusinessDay::STATUS_REOPENED))
            ->orderByDesc('business_date')
            ->orderByDesc('id');

        $businessDays = $daysQuery->get();

        // Canonical comparison row data for each business day
        $daysData = $businessDays->map(function (PurchaseBusinessDay $day): array {
            $dateStr = $day->business_date->toDateString();
            $warehouseId = (int) $day->warehouse_id;

            $rows = $this->comparisonService->buildComparisonRows($dateStr, $warehouseId);
            $summary = $this->comparisonService->calculateSummary($rows);
            $managerSummary = $this->comparisonService->getDailyManagerSummary($dateStr, $warehouseId);

            $pendingCount = $rows->filter(function (array $r): bool {
                $adv = (float) ($r['advance_qty'] ?? 0);
                $matched = (float) ($r['matched_bill_qty'] ?? 0);

                return max(0.0, $adv - $matched) > 0.0001 || ($r['unit_mismatch'] ?? false);
            })->count();

            $unitIssuesCount = (int) ($summary['unit_fix_count'] ?? 0);

            return [
                'day' => $day,
                'business_date' => $day->business_date,
                'warehouse' => $day->warehouse,
                'status' => $day->status,
                'purchaser' => $day->openedBy?->name ?? 'System',
                'bills_count' => $managerSummary['purchase_bills']['count'] ?? 0,
                'bills_formatted_total' => $managerSummary['purchase_bills']['formatted_totals'] ?? '0 kg',
                'advance_count' => $managerSummary['advance_receives']['count'] ?? 0,
                'advance_formatted_total' => $managerSummary['advance_receives']['formatted_totals'] ?? '0 kg',
                'products_count' => $rows->count(),
                'pending_count' => $pendingCount,
                'unit_issues_count' => $unitIssuesCount,
                'summary' => $summary,
            ];
        });

        if ($pendingOnly) {
            $daysData = $daysData->filter(fn (array $d): bool => $d['pending_count'] > 0 || $d['unit_issues_count'] > 0)->values();
        }

        // Monthly oversight metrics summary
        $monthlySummary = $this->businessDayService->getMonthlyOversightSummary($monthInput, $selectedWarehouseId);

        return view('admin.cashbook.purchaser-business-days.index', [
            'warehouses' => $warehouses,
            'purchasers' => $purchasers,
            'selectedWarehouseId' => $selectedWarehouseId,
            'selectedPurchaserId' => $selectedPurchaserId,
            'month' => $monthInput,
            'statusFilter' => $statusFilter,
            'pendingOnly' => $pendingOnly,
            'reopenedOnly' => $reopenedOnly,
            'daysData' => $daysData,
            'monthlySummary' => $monthlySummary,
        ]);
    }

    /**
     * Admin Day Detail View.
     */
    public function show(Request $request, string $uuid): View
    {
        $this->authorizeAdmin();

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

        // Build current pending list
        $pendingList = $this->buildPendingWorklist($comparisonRows);

        // Build exhaustive audit timeline
        $timeline = $this->businessDayService->buildAuditTimeline($day);

        // Historical vs Current pending metrics
        $firstCloseSnapshot = $day->first_close_pending_snapshot ?? [];
        $latestCloseSnapshot = $day->close_pending_snapshot ?? [];

        return view('admin.cashbook.purchaser-business-days.show', [
            'day' => $day,
            'warehouse' => $day->warehouse,
            'comparisonRows' => $comparisonRows,
            'summary' => $summary,
            'managerSummary' => $managerSummary,
            'pendingList' => $pendingList,
            'timeline' => $timeline,
            'firstCloseSnapshot' => $firstCloseSnapshot,
            'latestCloseSnapshot' => $latestCloseSnapshot,
            'warehouseSettings' => $warehouseSettings,
        ]);
    }

    /**
     * Admin Reopen Action with Mandatory Reason.
     */
    public function reopen(Request $request, string $uuid): RedirectResponse
    {
        $this->authorizeAdmin();

        /** @var PurchaseBusinessDay $day */
        $day = PurchaseBusinessDay::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        if (! $day->isClosed()) {
            return redirect()->route('admin.cashbook.purchaser-business-days.show', $day->uuid)
                ->with('error', 'Only closed Business Days can be reopened.');
        }

        $request->validate([
            'reopen_reason' => 'required|string|min:3|max:1000',
        ], [
            'reopen_reason.required' => 'A mandatory reason is required for admin reopening.',
        ]);

        try {
            $this->businessDayService->reopen($day, (int) $request->user()->id, (string) $request->input('reopen_reason'));

            return redirect()->route('admin.cashbook.purchaser-business-days.show', $day->uuid)
                ->with('success', "Business Day for {$day->business_date->format('d M Y')} reopened by Admin. Auto-match recalculated.");
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        }
    }

    /**
     * Dedicated Business Day Reports Hub (Daily, Monthly, Pending, Reopened, Close-With-Pending, Vendor Pending).
     */
    public function reports(Request $request): View
    {
        $this->authorizeAdmin();

        $warehouses = Warehouse::query()->active()->orderBy('name')->get();
        $suppliers = Supplier::query()->orderBy('name')->get();

        $tab = (string) $request->input('tab', 'monthly');
        $month = (string) $request->input('month', now()->format('Y-m'));
        $date = (string) $request->input('date', now()->format('Y-m-d'));
        $selectedWarehouseId = $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null;
        $selectedSupplierId = $request->filled('supplier_id') ? $request->integer('supplier_id') : null;

        $monthCarbon = Carbon::createFromFormat('Y-m', $month) ?: now();
        $startOfMonth = $monthCarbon->copy()->startOfMonth()->toDateString();
        $endOfMonth = $monthCarbon->copy()->endOfMonth()->toDateString();

        // 1. Monthly Summary Report
        $monthlySummary = $this->businessDayService->getMonthlyOversightSummary($month, $selectedWarehouseId);

        // 2. Pending Purchase Bills Report Data
        $pendingQuery = PurchaseBusinessDay::query()
            ->with(['warehouse', 'openedBy'])
            ->whereIn('status', [PurchaseBusinessDay::STATUS_OPEN, PurchaseBusinessDay::STATUS_REOPENED])
            ->when($selectedWarehouseId !== null && $selectedWarehouseId > 0, fn ($q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->orderByDesc('business_date');

        $openDays = $pendingQuery->get();
        $allPendingItems = collect();

        foreach ($openDays as $od) {
            $rows = $this->comparisonService->buildComparisonRows($od->business_date->toDateString(), (int) $od->warehouse_id);
            $pList = $this->buildPendingWorklist($rows);
            foreach ($pList as $pi) {
                $allPendingItems->push([
                    'business_day_id' => $od->id,
                    'business_day_uuid' => $od->uuid,
                    'business_date' => $od->business_date,
                    'warehouse' => $od->warehouse,
                    'purchaser' => $od->openedBy?->name ?? 'Purchaser',
                    ...$pi,
                ]);
            }
        }

        // 3. Reopened Days Report
        $reopenedDays = PurchaseBusinessDay::query()
            ->with(['warehouse', 'openedBy', 'reopenedBy'])
            ->where('status', PurchaseBusinessDay::STATUS_REOPENED)
            ->whereBetween('business_date', [$startOfMonth, $endOfMonth])
            ->when($selectedWarehouseId !== null && $selectedWarehouseId > 0, fn ($q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->orderByDesc('reopened_at')
            ->get();

        // 4. Close-With-Pending Report
        $closeWithPendingDays = PurchaseBusinessDay::query()
            ->with(['warehouse', 'openedBy', 'closedBy', 'reopenedBy'])
            ->whereNotNull('first_close_pending_snapshot')
            ->where('first_close_pending_snapshot', '!=', '[]')
            ->whereBetween('business_date', [$startOfMonth, $endOfMonth])
            ->when($selectedWarehouseId !== null && $selectedWarehouseId > 0, fn ($q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->orderByDesc('closed_at')
            ->get()
            ->map(function (PurchaseBusinessDay $d): array {
                $rows = $this->comparisonService->buildComparisonRows($d->business_date->toDateString(), (int) $d->warehouse_id);
                $currentPending = $this->buildPendingWorklist($rows);

                return [
                    'day' => $d,
                    'first_close_snapshot' => (array) ($d->first_close_pending_snapshot ?? []),
                    'current_pending' => $currentPending,
                    'is_resolved' => $currentPending->isEmpty(),
                ];
            });

        // 5. Vendor Pending Aggregation Report
        $vendorPendingList = $this->buildVendorPendingReport($allPendingItems, $selectedSupplierId);

        return view('admin.cashbook.purchaser-business-days.reports', [
            'tab' => $tab,
            'month' => $month,
            'date' => $date,
            'warehouses' => $warehouses,
            'suppliers' => $suppliers,
            'selectedWarehouseId' => $selectedWarehouseId,
            'selectedSupplierId' => $selectedSupplierId,
            'monthlySummary' => $monthlySummary,
            'allPendingItems' => $allPendingItems,
            'reopenedDays' => $reopenedDays,
            'closeWithPendingDays' => $closeWithPendingDays,
            'vendorPendingList' => $vendorPendingList,
        ]);
    }

    /**
     * Export Pending Bills Report as PDF.
     */
    public function exportPendingPdf(Request $request): mixed
    {
        $this->authorizeAdmin();

        $selectedWarehouseId = $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null;
        $warehouse = $selectedWarehouseId ? Warehouse::find($selectedWarehouseId) : null;

        $openDays = PurchaseBusinessDay::query()
            ->with(['warehouse', 'openedBy'])
            ->whereIn('status', [PurchaseBusinessDay::STATUS_OPEN, PurchaseBusinessDay::STATUS_REOPENED])
            ->when($selectedWarehouseId !== null && $selectedWarehouseId > 0, fn ($q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->orderByDesc('business_date')
            ->get();

        $pendingItems = collect();
        foreach ($openDays as $od) {
            $rows = $this->comparisonService->buildComparisonRows($od->business_date->toDateString(), (int) $od->warehouse_id);
            $pList = $this->buildPendingWorklist($rows);
            foreach ($pList as $pi) {
                $pendingItems->push([
                    'business_date' => $od->business_date->format('d M Y'),
                    'warehouse_name' => $od->warehouse?->name ?? 'Warehouse',
                    ...$pi,
                ]);
            }
        }

        $pdfData = [
            'warehouse' => $warehouse,
            'pendingItems' => $pendingItems,
            'generatedAt' => now()->format('d M Y, h:i A'),
            'generatedBy' => auth()->user()?->name ?? 'Admin',
        ];

        return Pdf::loadView('admin.cashbook.purchaser-business-days.pdf.pending_report', $pdfData)
            ->setPaper('a4', 'portrait')
            ->setOption(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true])
            ->download('Pending_Purchase_Bills_'.now()->format('Ymd_His').'.pdf');
    }

    /**
     * Build the canonical pending worklist.
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

    /**
     * Build vendor pending aggregation report.
     */
    private function buildVendorPendingReport(Collection $pendingItems, ?int $supplierId = null): Collection
    {
        return $pendingItems->groupBy('product_id')->map(function (Collection $items): array {
            $first = $items->first();
            $totalPending = $items->sum('pending_qty');
            $unit = $first['unit'] ?? 'kg';

            return [
                'product_id' => $first['product_id'],
                'product_name' => $first['product_name'],
                'sku' => $first['sku'],
                'unit' => $unit,
                'total_pending_qty' => $totalPending,
                'formatted_total_pending' => $this->formatNumber($totalPending).' '.$unit,
                'occurrences_count' => $items->count(),
                'days' => $items->pluck('business_date')->map(fn ($d) => $d instanceof Carbon ? $d->format('d M') : (string) $d)->unique()->join(', '),
            ];
        })->values();
    }

    private function formatNumber(float $val): string
    {
        return (abs($val - (int) $val) < 0.0001)
            ? (string) (int) $val
            : rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.');
    }

    private function authorizeAdmin(): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(401);
        }

        if ($user->hasRole('admin') || $user->isMainAdmin() || $user->can('purchasing.order.view')) {
            return;
        }

        abort(403, 'Unauthorized access to Admin Cashbook Purchaser Business Days.');
    }
}
