<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Warehouse\WarehouseCreditInventoryAction;
use App\Enums\Warehouse\WarehouseCreditNoteStatus;
use App\Enums\Warehouse\WarehouseCreditReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\WarehouseCreditNotes\StoreWarehouseCreditNoteRequest;
use App\Http\Requests\Web\Admin\WarehouseCreditNotes\UpdateWarehouseCreditNoteRequest;
use App\Http\Requests\Web\Admin\WarehouseCreditNotes\VoidWarehouseCreditNoteRequest;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseCreditNote;
use App\Models\WarehouseCreditNoteItem;
use App\Services\Inventory\StockLedgerService;
use App\Services\Warehouse\WarehouseCreditNoteService;
use App\Support\CashbookAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WarehouseCreditNoteController extends Controller
{
    public function __construct(
        private readonly WarehouseCreditNoteService $creditNoteService,
        private readonly StockLedgerService $stockLedgerService,
    ) {}

    public function index(Request $request): View
    {
        $user = $this->requireAuthorizedUser($request, CashbookAccess::WarehouseCreditNotesView);
        $allowedWarehouseIds = $this->allowedWarehouseIds($user);

        $filters = [
            'warehouse_id' => $request->filled('warehouse_id') ? (int) $request->input('warehouse_id') : null,
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'status' => $request->input('status'),
            'inventory_action' => $request->input('inventory_action'),
            'reason' => $request->input('reason'),
            'q' => trim((string) $request->input('q', '')),
        ];

        if ($filters['warehouse_id'] !== null && $allowedWarehouseIds !== null && ! in_array($filters['warehouse_id'], $allowedWarehouseIds, true)) {
            abort(403, 'Unauthorized warehouse access.');
        }

        $baseQuery = WarehouseCreditNote::query()
            ->when($allowedWarehouseIds !== null, fn (Builder $q) => $q->whereIn('warehouse_id', $allowedWarehouseIds))
            ->when($filters['warehouse_id'] !== null, fn (Builder $q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->when($filters['start_date'], fn (Builder $q) => $q->whereDate('business_date', '>=', (string) $filters['start_date']))
            ->when($filters['end_date'], fn (Builder $q) => $q->whereDate('business_date', '<=', (string) $filters['end_date']))
            ->when($filters['reason'], fn (Builder $q) => $q->where('reason', (string) $filters['reason']))
            ->when($filters['inventory_action'], function (Builder $q) use ($filters): void {
                $q->whereHas('items', fn (Builder $iq) => $iq->where('inventory_action', (string) $filters['inventory_action']));
            })
            ->when($filters['q'] !== '', function (Builder $q) use ($filters): void {
                $search = $filters['q'];
                $q->where(function (Builder $sub) use ($search): void {
                    $sub->where('credit_note_number', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%");
                });
            });

        // Bounded aggregate summary across active (finalized) notes matching filters
        $activeNoteIdsQuery = (clone $baseQuery)
            ->where('status', WarehouseCreditNoteStatus::Active->value)
            ->select('id');

        $summaryRow = WarehouseCreditNoteItem::query()
            ->whereIn('warehouse_credit_note_id', $activeNoteIdsQuery)
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN inventory_action = 'in' THEN line_total ELSE 0 END), 0) as stock_in_total, ".
                "COALESCE(SUM(CASE WHEN inventory_action = 'out' THEN fifo_cost_total ELSE 0 END), 0) as stock_out_total, ".
                "COALESCE(SUM(CASE WHEN inventory_action = 'damage' THEN fifo_cost_total ELSE 0 END), 0) as damage_total"
            )
            ->toBase()
            ->first();

        $stockInTotal = round((float) ($summaryRow->stock_in_total ?? 0), 2);
        $stockOutTotal = round((float) ($summaryRow->stock_out_total ?? 0), 2);
        $damageTotal = round((float) ($summaryRow->damage_total ?? 0), 2);

        $summary = [
            'stock_in_total' => $stockInTotal,
            'stock_out_total' => $stockOutTotal,
            'damage_total' => $damageTotal,
            'net_change' => round($stockInTotal - $stockOutTotal - $damageTotal, 2),
        ];

        $creditNotes = (clone $baseQuery)
            ->when($filters['status'], fn (Builder $q) => $q->where('status', (string) $filters['status']))
            ->with(['warehouse:id,name,code', 'createdBy:id,name'])
            ->withCount('items')
            ->addSelect([
                'stock_in_value' => WarehouseCreditNoteItem::query()
                    ->whereColumn('warehouse_credit_note_id', 'warehouse_credit_notes.id')
                    ->where('inventory_action', WarehouseCreditInventoryAction::In->value)
                    ->selectRaw('COALESCE(SUM(line_total), 0)'),
                'stock_out_value' => WarehouseCreditNoteItem::query()
                    ->whereColumn('warehouse_credit_note_id', 'warehouse_credit_notes.id')
                    ->where('inventory_action', WarehouseCreditInventoryAction::Out->value)
                    ->selectRaw('COALESCE(SUM(fifo_cost_total), 0)'),
                'damage_value' => WarehouseCreditNoteItem::query()
                    ->whereColumn('warehouse_credit_note_id', 'warehouse_credit_notes.id')
                    ->where('inventory_action', WarehouseCreditInventoryAction::Damage->value)
                    ->selectRaw('COALESCE(SUM(fifo_cost_total), 0)'),
            ])
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $warehouses = $this->authorizedWarehouses($user);
        $statuses = WarehouseCreditNoteStatus::cases();
        $actions = WarehouseCreditInventoryAction::cases();
        $reasons = WarehouseCreditReason::cases();

        return view('admin.cashbook.warehouse-credit-notes.index', compact(
            'creditNotes',
            'summary',
            'filters',
            'warehouses',
            'statuses',
            'actions',
            'reasons',
        ));
    }

    public function create(Request $request): View
    {
        $user = $this->requireAuthorizedUser($request, CashbookAccess::WarehouseCreditNotesManage);
        $warehouses = $this->authorizedWarehouses($user);

        return view('admin.cashbook.warehouse-credit-notes.create', [
            'isEdit' => false,
            'creditNote' => null,
            'warehouses' => $warehouses,
            'reasons' => WarehouseCreditReason::cases(),
            'actions' => WarehouseCreditInventoryAction::cases(),
            'grades' => $this->supportedGrades(),
            'defaultDate' => today()->toDateString(),
        ]);
    }

    public function store(StoreWarehouseCreditNoteRequest $request): RedirectResponse
    {
        $user = $this->requireAuthorizedUser($request, CashbookAccess::WarehouseCreditNotesManage);
        $validated = $this->applyPrimaryDirectionToPayload($request->validated());
        $this->assertWarehouseScope($user, (int) $validated['warehouse_id']);

        $saveAction = (string) ($validated['save_action'] ?? 'draft');

        $creditNote = DB::transaction(function () use ($validated, $user, $saveAction): WarehouseCreditNote {
            $note = $this->creditNoteService->createDraft($validated, $user);

            if ($saveAction === 'finalize') {
                return $this->creditNoteService->finalize($note, $user);
            }

            return $note;
        });

        $message = $creditNote->isActive()
            ? "Warehouse Credit Note {$creditNote->credit_note_number} finalized successfully."
            : 'Warehouse Credit Note draft saved successfully.';

        return redirect()
            ->route('admin.cashbook.warehouse-credit-notes.show', ['creditNote' => $creditNote->public_uuid])
            ->with('status', $message);
    }

    public function show(Request $request, WarehouseCreditNote $creditNote): View
    {
        $user = $this->requireAuthorizedUser($request, CashbookAccess::WarehouseCreditNotesView);
        $this->assertWarehouseScope($user, (int) $creditNote->warehouse_id);

        $creditNote->load([
            'warehouse:id,name,code',
            'items.product:id,name,sku,unit',
            'items.wastageEntry',
            'items.movements.stockBatch:id,reference,received_at,cost_per_kg,purchase_grade',
            'items.movements.stockMovement:id,type,quantity,cost_per_unit,created_at',
            'items.movements.reversalStockMovement:id,type,quantity,cost_per_unit,created_at',
            'journalEntry.transactions.account',
            'createdBy:id,name',
            'finalizedBy:id,name',
            'voidedBy:id,name',
        ]);

        $stockInTotal = round((float) $creditNote->items
            ->where('inventory_action', WarehouseCreditInventoryAction::In)
            ->sum('line_total'), 2);
        $stockOutTotal = round((float) $creditNote->items
            ->where('inventory_action', WarehouseCreditInventoryAction::Out)
            ->sum('fifo_cost_total'), 2);
        $damageTotal = round((float) $creditNote->items
            ->where('inventory_action', WarehouseCreditInventoryAction::Damage)
            ->sum('fifo_cost_total'), 2);

        $canManage = $this->hasPermission($user, CashbookAccess::WarehouseCreditNotesManage);
        $canVoid = $this->hasPermission($user, CashbookAccess::WarehouseCreditNotesVoid);
        $canViewJournal = $user->isMainAdmin()
            || $user->hasRole('admin')
            || $user->can(CashbookAccess::AccountBalanceView)
            || $user->can('accounting.ledger.view')
            || $user->can('finance.journals.view');

        return view('admin.cashbook.warehouse-credit-notes.show', compact(
            'creditNote',
            'stockInTotal',
            'stockOutTotal',
            'damageTotal',
            'canManage',
            'canVoid',
            'canViewJournal',
        ));
    }

    public function edit(Request $request, WarehouseCreditNote $creditNote): View|RedirectResponse
    {
        $user = $this->requireAuthorizedUser($request, CashbookAccess::WarehouseCreditNotesManage);
        $this->assertWarehouseScope($user, (int) $creditNote->warehouse_id);

        if (! $creditNote->isDraft()) {
            return redirect()
                ->route('admin.cashbook.warehouse-credit-notes.show', ['creditNote' => $creditNote->public_uuid])
                ->with('error', 'Finalized or voided warehouse credit notes cannot be edited.');
        }

        $creditNote->load(['items.product.orderUnits']);
        $warehouses = $this->authorizedWarehouses($user);

        return view('admin.cashbook.warehouse-credit-notes.create', [
            'isEdit' => true,
            'creditNote' => $creditNote,
            'warehouses' => $warehouses,
            'reasons' => WarehouseCreditReason::cases(),
            'actions' => WarehouseCreditInventoryAction::cases(),
            'grades' => $this->supportedGrades(),
            'defaultDate' => $creditNote->business_date?->toDateString() ?? today()->toDateString(),
        ]);
    }

    public function update(UpdateWarehouseCreditNoteRequest $request, WarehouseCreditNote $creditNote): RedirectResponse
    {
        $user = $this->requireAuthorizedUser($request, CashbookAccess::WarehouseCreditNotesManage);
        $this->assertWarehouseScope($user, (int) $creditNote->warehouse_id);

        $validated = $this->applyPrimaryDirectionToPayload($request->validated());
        $this->assertWarehouseScope($user, (int) $validated['warehouse_id']);

        $saveAction = (string) ($validated['save_action'] ?? 'draft');

        $updated = DB::transaction(function () use ($creditNote, $validated, $user, $saveAction): WarehouseCreditNote {
            $note = $this->creditNoteService->updateDraft($creditNote, $validated, $user);

            if ($saveAction === 'finalize') {
                return $this->creditNoteService->finalize($note, $user);
            }

            return $note;
        });

        $message = $updated->isActive()
            ? "Warehouse Credit Note {$updated->credit_note_number} finalized successfully."
            : 'Warehouse Credit Note draft updated successfully.';

        return redirect()
            ->route('admin.cashbook.warehouse-credit-notes.show', ['creditNote' => $updated->public_uuid])
            ->with('status', $message);
    }

    public function destroy(Request $request, WarehouseCreditNote $creditNote): RedirectResponse
    {
        $user = $this->requireAuthorizedUser($request, CashbookAccess::WarehouseCreditNotesManage);
        $this->assertWarehouseScope($user, (int) $creditNote->warehouse_id);

        $this->creditNoteService->deleteDraft($creditNote, $user);

        return redirect()
            ->route('admin.cashbook.warehouse-credit-notes.index')
            ->with('status', 'Draft warehouse credit note deleted.');
    }

    public function finalize(Request $request, WarehouseCreditNote $creditNote): RedirectResponse
    {
        $user = $this->requireAuthorizedUser($request, CashbookAccess::WarehouseCreditNotesManage);
        $this->assertWarehouseScope($user, (int) $creditNote->warehouse_id);

        $finalized = $this->creditNoteService->finalize($creditNote, $user);

        return redirect()
            ->route('admin.cashbook.warehouse-credit-notes.show', ['creditNote' => $finalized->public_uuid])
            ->with('status', "Warehouse Credit Note {$finalized->credit_note_number} finalized successfully.");
    }

    public function void(VoidWarehouseCreditNoteRequest $request, WarehouseCreditNote $creditNote): RedirectResponse
    {
        $user = $this->requireAuthorizedUser($request, CashbookAccess::WarehouseCreditNotesVoid);
        $this->assertWarehouseScope($user, (int) $creditNote->warehouse_id);

        $voided = $this->creditNoteService->void($creditNote, (string) $request->validated('void_reason'), $user);

        return redirect()
            ->route('admin.cashbook.warehouse-credit-notes.show', ['creditNote' => $voided->public_uuid])
            ->with('status', "Warehouse Credit Note {$voided->credit_note_number} has been voided.");
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $this->requireAuthorizedUser($request, CashbookAccess::WarehouseCreditNotesManage);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $limit = (int) ($validated['limit'] ?? 25);

        $products = Product::query()
            ->active()
            ->with('orderUnits:id,product_id,unit,label,conversion_to_base')
            ->when($search !== '', function (Builder $q) use ($search): void {
                $q->where(function (Builder $sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->ordered()
            ->limit($limit)
            ->get(['id', 'name', 'sku', 'unit']);

        $items = $products->map(function (Product $product): array {
            $baseUnit = $product->unit ?: 'kg';
            $units = [[
                'unit' => $baseUnit,
                'label' => $baseUnit,
                'conversion_to_base' => 1.0,
            ]];

            foreach ($product->orderUnits as $pu) {
                if (strcasecmp((string) $pu->unit, $baseUnit) === 0) {
                    continue;
                }
                if ($pu->conversion_to_base === null || (float) $pu->conversion_to_base <= 0.0) {
                    continue;
                }
                $units[] = [
                    'unit' => $pu->unit,
                    'label' => $pu->label ?: $pu->unit,
                    'conversion_to_base' => round((float) $pu->conversion_to_base, 4),
                ];
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'base_unit' => $baseUnit,
                'units' => $units,
            ];
        })->values();

        return response()->json(['items' => $items]);
    }

    public function stockAvailability(Request $request): JsonResponse
    {
        $user = $this->requireAuthorizedUser($request, CashbookAccess::WarehouseCreditNotesManage);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'grade' => ['required', 'string'],
        ]);

        $warehouseId = (int) $validated['warehouse_id'];
        $this->assertWarehouseScope($user, $warehouseId);

        $grade = ProductGrade::tryResolve((string) $validated['grade']);
        if (! $grade instanceof ProductGrade || $grade === ProductGrade::Damage) {
            return response()->json([
                'available_qty' => 0.0,
                'estimated_fifo_rate' => 0.0,
            ]);
        }

        $productId = (int) $validated['product_id'];
        $availableQty = round($this->stockLedgerService->availableStockForProduct($productId, $warehouseId, $grade), 3);
        $estimatedRate = $this->estimateFifoRate($productId, $warehouseId, $grade);

        return response()->json([
            'available_qty' => $availableQty,
            'estimated_fifo_rate' => $estimatedRate,
        ]);
    }

    private function estimateFifoRate(int $productId, int $warehouseId, ProductGrade $grade): float
    {
        $rows = StockMovement::query()
            ->join('stock_batches', 'stock_batches.id', '=', 'stock_movements.batch_id')
            ->where('stock_movements.product_id', $productId)
            ->whereNull('stock_batches.deleted_at')
            ->where('stock_batches.warehouse_id', $warehouseId)
            ->where('stock_batches.status', BatchStatus::Sorted->value)
            ->where('stock_batches.warehouse_receive_pending', false)
            ->when($grade === ProductGrade::Unsorted, function (Builder $q): void {
                $q->whereIn('stock_movements.grade', ['U', 'MIXED', 'UNSORTED']);
            }, function (Builder $q) use ($grade): void {
                $q->where('stock_movements.grade', $grade->value);
            })
            ->selectRaw(
                'stock_movements.batch_id, MAX(stock_movements.cost_per_unit) as cost_per_unit, '.
                'SUM(CASE WHEN stock_movements.type IN (?, ?) THEN stock_movements.quantity '.
                'WHEN stock_movements.type IN (?, ?, ?, ?) THEN -stock_movements.quantity ELSE 0 END) as available_qty',
                [
                    StockMovementType::In->value,
                    StockMovementType::SaleReversal->value,
                    StockMovementType::Out->value,
                    StockMovementType::Wastage->value,
                    StockMovementType::Sale->value,
                    StockMovementType::Adjustment->value,
                ]
            )
            ->groupBy('stock_movements.batch_id', 'stock_batches.received_at', 'stock_batches.id')
            ->having('available_qty', '>', 0)
            ->orderBy('stock_batches.received_at')
            ->orderBy('stock_batches.id')
            ->toBase()
            ->get();

        $totalQty = 0.0;
        $totalVal = 0.0;
        foreach ($rows as $row) {
            $qty = (float) $row->available_qty;
            $cost = (float) $row->cost_per_unit;
            $totalQty += $qty;
            $totalVal += ($qty * $cost);
        }

        if ($totalQty > 0.0001) {
            return round($totalVal / $totalQty, 4);
        }

        $latestCost = StockBatch::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('purchase_grade', $grade->value)
            ->latest('id')
            ->value('cost_per_kg');

        return round((float) ($latestCost ?? 0.0), 4);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applyPrimaryDirectionToPayload(array $validated): array
    {
        $direction = $validated['movement_direction'] ?? null;
        if (! in_array($direction, ['in', 'out'], true)) {
            return $validated;
        }

        $reason = (string) ($validated['reason'] ?? '');
        $resolvedAction = match ($direction) {
            'in' => WarehouseCreditInventoryAction::In->value,
            'out' => in_array($reason, ['damage_wastage', 'spoilage'], true)
                ? WarehouseCreditInventoryAction::Damage->value
                : WarehouseCreditInventoryAction::Out->value,
        };

        $validated['items'] = array_map(function (array $item) use ($resolvedAction): array {
            $item['inventory_action'] = $resolvedAction;

            return $item;
        }, (array) ($validated['items'] ?? []));

        return $validated;
    }

    private function requireAuthorizedUser(Request $request, string $permission): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->hasPermission($user, $permission), 403, 'Unauthorized.');

        return $user;
    }

    private function hasPermission(User $user, string $permission): bool
    {
        if ($user->isMainAdmin() || $user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('accounts') && $user->can($permission);
    }

    /**
     * @return list<int>|null
     */
    private function allowedWarehouseIds(User $user): ?array
    {
        if ($user->isMainAdmin() || $user->hasRole('admin') || $user->hasAllWarehouseAccess()) {
            return null;
        }

        return $user->warehouses()->pluck('warehouses.id')->map(fn ($id): int => (int) $id)->all();
    }

    private function assertWarehouseScope(User $user, int $warehouseId): void
    {
        if ($user->isMainAdmin() || $user->hasRole('admin') || $user->canAccessWarehouse($warehouseId)) {
            return;
        }

        abort(403, 'Unauthorized warehouse access.');
    }

    private function authorizedWarehouses(User $user)
    {
        $allowedIds = $this->allowedWarehouseIds($user);

        return Warehouse::query()
            ->where('is_active', true)
            ->when($allowedIds !== null, fn (Builder $q) => $q->whereIn('id', $allowedIds))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    /**
     * @return list<ProductGrade>
     */
    private function supportedGrades(): array
    {
        return [
            ProductGrade::GradeA,
            ProductGrade::GradeB,
            ProductGrade::GradeC,
            ProductGrade::Unsorted,
        ];
    }
}
