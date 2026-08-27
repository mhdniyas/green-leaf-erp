<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Warehouse;

use App\Actions\Purchasing\ApproveGoodsReceiptAction;
use App\DTOs\Inventory\WastageEntryData;
use App\Enums\Inventory\BatchStatus;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Warehouse\ReceiveIndexRequest;
use App\Models\Category;
use App\Models\GoodsReceived;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Repositories\Inventory\StockMovementRepository;
use App\Repositories\Warehouse\WarehouseReceiveRepository;
use App\Services\Inventory\StockLedgerService;
use App\Services\Inventory\WastageService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WarehouseReceiverController extends Controller
{
    private const BULK_RECEIVE_GRN_LIMIT = 100;

    public function __construct(
        private readonly StockLedgerService $stockLedgerService,
        private readonly WarehouseReceiveRepository $warehouseReceiveRepository,
        private readonly StockMovementRepository $stockMovementRepository,
    ) {}

    /**
     * Show the warehouse receive checklist shell.
     *
     * Minimal data for page frame (warehouses + categories for filters).
     * Tab contents are lazy-loaded via web endpoints:
     *   GET /warehouse-receiver/tab/pending
     *   GET /warehouse-receiver/tab/inventory
     *   GET /warehouse-receiver/tab/loadout
     *   GET /warehouse-receiver/tab/deliveries
     */
    public function index(ReceiveIndexRequest $request): View
    {
        $this->authorizeReceiverAccess($request);
        $validated = $request->validated();

        $date = $validated['date'] ?? app(PurchaserBusinessDayService::class)->operationalDate()->toDateString();
        $selectedWarehouseId = isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null;
        $receiveSearch = trim((string) ($validated['receive_search'] ?? ''));
        $receiveSource = (string) ($validated['receive_source'] ?? 'all');
        $receiveCategoryId = isset($validated['receive_category_id']) ? (int) $validated['receive_category_id'] : null;

        $warehouses = Warehouse::active()->orderBy('name')->get(['id', 'name', 'code']);
        $receiveCategories = Category::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('warehouse-receiver.checklist', compact(
            'date',
            'selectedWarehouseId',
            'receiveSearch',
            'receiveSource',
            'receiveCategoryId',
            'warehouses',
            'receiveCategories',
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tab JSON endpoints (session authenticated for web)
    // ─────────────────────────────────────────────────────────────────────────

    public function tabPending(ReceiveIndexRequest $request): JsonResponse
    {
        $this->authorizeReceiverAccess($request);
        $validated = $request->validated();

        $date = (string) ($validated['date'] ?? app(PurchaserBusinessDayService::class)->operationalDate()->toDateString());
        $source = (string) ($validated['receive_source'] ?? 'all');
        $categoryId = isset($validated['receive_category_id']) ? (int) $validated['receive_category_id'] : null;
        $search = trim((string) ($validated['receive_search'] ?? ''));

        $warehouses = Warehouse::active()->orderBy('name')->get(['id', 'name', 'code']);

        $pendingGrns = $this->warehouseReceiveRepository->pendingGrns($date, $source, $categoryId, $search)
            ->map(fn ($grn) => [
                'id' => $grn->id,
                'grn_number' => $grn->grn_number,
                'status' => $grn->status,
                'received_at' => $grn->received_at?->toDateTimeString(),
                'supplier_name' => $grn->purchaseOrder?->supplier?->name ?? 'Vendor',
                'purchaser_name' => $grn->purchaseOrder?->purchaserCart?->user?->name ?? 'Purchaser',
                'items_count' => $grn->items->count(),
                'total_kg' => (float) $grn->items->sum('received_qty'),
                'receive_url' => route('warehouse.receiver.receive-grn', $grn->id),
                'items' => $grn->items->map(fn ($item) => [
                    'product_name' => $item->product?->name,
                    'product_sku' => $item->product?->sku,
                    'category_name' => $item->product?->category?->name,
                    'received_qty' => (float) $item->received_qty,
                    'unit' => $item->product?->unit,
                ]),
            ]);

        $pendingBatches = $this->warehouseReceiveRepository->pendingBatches($date, $source, $categoryId, $search)
            ->map(fn ($batch) => [
                'id' => $batch->id,
                'reference' => $batch->reference,
                'total_kg' => (float) $batch->total_kg,
                'received_at' => $batch->received_at?->toDateTimeString(),
                'product_name' => $batch->product?->name,
                'product_sku' => $batch->product?->sku,
                'category_name' => $batch->product?->category?->name,
                'unit' => $batch->product?->unit,
                'default_warehouse_id' => $batch->product?->default_warehouse_id ?? $warehouses->first()?->id,
                'confirm_url' => route('warehouse.receiver.confirm', $batch->id),
            ]);

        $directPurchaseGrns = $this->warehouseReceiveRepository->directPurchaseGrns($date);
        $directProductIds = $directPurchaseGrns
            ->flatMap(fn ($grn) => $grn->items->pluck('product_id'))
            ->unique()->values();

        $pendingDirectOrders = $this->warehouseReceiveRepository->pendingDirectPurchaseOrders($date, $source, $categoryId, $search)
            ->filter(fn (ShopOrder $order) => $order->items->pluck('product_id')->intersect($directProductIds)->isEmpty())
            ->values()
            ->map(fn (ShopOrder $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'delivery_status' => $order->delivery_status,
                'receive_url' => route('warehouse.receiver.direct-purchase.receive', $order->id),
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product?->name,
                    'product_sku' => $item->product?->sku,
                    'category_name' => $item->product?->category?->name,
                    'approved_qty' => (float) ($item->approved_qty ?: $item->requested_qty),
                    'unit' => $item->unit,
                    'default_warehouse_id' => $item->product?->default_warehouse_id ?? $warehouses->first()?->id,
                ]),
            ]);

        return response()->json([
            'success' => true,
            'date' => $date,
            'warehouses' => $warehouses,
            'receive_all_grns_url' => route('warehouse.receiver.process-receive-grns.all'),
            'confirm_all_batches_url' => route('warehouse.receiver.confirm-all'),
            'pending_grns' => $pendingGrns->values(),
            'pending_batches' => $pendingBatches->values(),
            'pending_direct_orders' => $pendingDirectOrders->values(),
        ]);
    }

    public function tabInventory(ReceiveIndexRequest $request): JsonResponse
    {
        $this->authorizeReceiverAccess($request);
        $validated = $request->validated();

        $date = (string) ($validated['date'] ?? app(PurchaserBusinessDayService::class)->operationalDate()->toDateString());
        $warehouseId = isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null;

        $inMovements = StockMovement::query()
            ->whereIn('type', [StockMovementType::In, StockMovementType::SaleReversal])
            ->when($warehouseId, fn (Builder $q) => $q->where('warehouse_id', $warehouseId))
            ->with(['product:id,name,unit,category_id', 'product.category:id,name', 'batch:id,reference'])
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();

        $outMovements = StockMovement::query()
            ->whereIn('type', [StockMovementType::Out, StockMovementType::Wastage, StockMovementType::Sale])
            ->when($warehouseId, fn (Builder $q) => $q->where('warehouse_id', $warehouseId))
            ->with(['product:id,name,unit,category_id', 'product.category:id,name'])
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();

        $stockLevels = $this->stockMovementRepository->currentStockByProductAndGrade(null, $warehouseId);
        $latestActivity = $this->warehouseReceiveRepository->latestActivityByStockLevel($stockLevels, $warehouseId);

        $inflows = $inMovements->map(fn ($mov) => [
            'product_name' => $mov->product?->name,
            'category_name' => $mov->product?->category?->name ?? 'Other',
            'reference' => $mov->batch?->reference,
            'quantity' => (float) $mov->quantity,
            'unit' => $mov->product?->unit,
            'time_formatted' => $mov->created_at->format('H:i'),
        ]);

        $outflows = $outMovements->map(fn ($mov) => [
            'product_name' => $mov->product?->name,
            'category_name' => $mov->product?->category?->name ?? 'Other',
            'type_label' => $mov->type instanceof StockMovementType ? $mov->type->label() : (string) $mov->type,
            'quantity' => (float) $mov->quantity,
            'unit' => $mov->product?->unit,
            'time_formatted' => $mov->created_at->format('H:i'),
        ]);

        $stockRows = $stockLevels
            ->sortByDesc(function ($item) use ($latestActivity) {
                $gradeStr = ($item->grade instanceof \BackedEnum) ? $item->grade->value : (string) $item->grade;
                $key = ((int) $item->product_id).'|'.$gradeStr;
                $rawTs = $latestActivity[$key] ?? null;

                return $rawTs ? Carbon::parse($rawTs)->timestamp : 0;
            })
            ->values()
            ->map(function ($level) use ($latestActivity) {
                $gradeStr = ($level->grade instanceof \BackedEnum) ? $level->grade->value : (string) $level->grade;
                $key = ((int) $level->product_id).'|'.$gradeStr;
                $rawTs = $latestActivity[$key] ?? null;

                return [
                    'product_name' => $level->product_name ?? '',
                    'product_sku' => $level->product_sku ?? '',
                    'category_name' => $level->category_name ?? 'Other',
                    'current_stock' => round((float) $level->current_stock, 2),
                    'unit' => 'kg',
                    'latest_activity' => $rawTs ? Carbon::parse($rawTs)->format('Y-m-d H:i') : null,
                ];
            });

        return response()->json([
            'success' => true,
            'inflows' => $inflows->values(),
            'outflows' => $outflows->values(),
            'stock_levels' => $stockRows,
        ]);
    }

    public function tabLoadout(ReceiveIndexRequest $request): JsonResponse
    {
        $this->authorizeReceiverAccess($request);
        $validated = $request->validated();

        $date = (string) ($validated['date'] ?? app(PurchaserBusinessDayService::class)->operationalDate()->toDateString());

        $orders = ShopOrder::whereDate('business_date', $date)
            ->where('state', 'approved')
            ->where('order_source', '!=', 'admin_direct_purchase')
            ->with(['shop:id,name,code,warehouse_tag,contact_phone,contact_name'])
            ->withCount([
                'items as total_items_count',
                'items as loaded_items_count' => fn ($q) => $q->where('sorting_status', 'loaded'),
            ])
            ->orderBy('created_at')
            ->get(['id', 'shop_id', 'order_number', 'business_date', 'delivery_status', 'state', 'created_at'])
            ->map(function (ShopOrder $order) {
                $total = (int) $order->total_items_count;
                $loaded = (int) $order->loaded_items_count;

                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'delivery_status' => $order->delivery_status,
                    'display_name' => $order->loadoutDisplayName(),
                    'loading_status' => match (true) {
                        $total === 0 => 'Pending',
                        $loaded === $total => 'Loaded',
                        $loaded > 0 => 'Partially Loaded',
                        default => 'Pending',
                    },
                    'total_items_count' => $total,
                    'loaded_items_count' => $loaded,
                    'shop' => $order->shop ? [
                        'warehouse_tag' => $order->shop->warehouse_tag,
                        'code' => $order->shop->code,
                        'contact_phone' => $order->shop->contact_phone,
                        'contact_name' => $order->shop->contact_name,
                    ] : null,
                    'loadout_url' => route('warehouse.receiver.loadout.show', $order->id),
                ];
            });

        return response()->json([
            'success' => true,
            'date' => $date,
            'orders' => $orders,
        ]);
    }

    public function tabDeliveries(ReceiveIndexRequest $request): JsonResponse
    {
        $this->authorizeReceiverAccess($request);
        $validated = $request->validated();

        $date = (string) ($validated['date'] ?? app(PurchaserBusinessDayService::class)->operationalDate()->toDateString());
        $warehouseId = isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null;

        $stockLevels = $this->stockMovementRepository->currentStockByProductAndGrade(null, $warehouseId);
        $stockMap = [];
        foreach ($stockLevels as $level) {
            $stockMap[$level->product_id] = ($stockMap[$level->product_id] ?? 0.0) + (float) $level->current_stock;
        }

        $orders = ShopOrder::whereDate('business_date', $date)
            ->where('order_source', '!=', 'admin_direct_purchase')
            ->with([
                'shop:id,name,code,warehouse_tag',
                'items:id,shop_order_id,product_id,approved_qty,requested_qty,loaded_qty,sorting_status,product_grade,unit,loadout_discrepancy_type,loadout_discrepancy_note',
                'items.product:id,name,unit',
            ])
            ->withCount([
                'items as total_items_count',
                'items as loaded_items_count' => fn ($q) => $q->where('sorting_status', 'loaded'),
            ])
            ->orderBy('created_at')
            ->get(['id', 'shop_id', 'order_number', 'business_date', 'delivery_status', 'state', 'created_at'])
            ->map(function (ShopOrder $order) use ($stockMap) {
                $total = (int) $order->total_items_count;
                $loaded = (int) $order->loaded_items_count;

                $totalReq = 0.0;
                $totalAvail = 0.0;
                foreach ($order->items as $item) {
                    $qty = (float) ($item->approved_qty > 0 ? $item->approved_qty : $item->requested_qty);
                    $stock = $stockMap[$item->product_id] ?? 0.0;
                    $totalReq += $qty;
                    $totalAvail += min($qty, max(0.0, $stock));
                }
                $fulfillmentPct = $totalReq > 0 ? (int) round(($totalAvail / $totalReq) * 100) : 100;

                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'delivery_status' => $order->delivery_status,
                    'display_name' => $order->loadoutDisplayName(),
                    'loading_status' => match (true) {
                        $total === 0 => 'Pending',
                        $loaded === $total => 'Loaded',
                        $loaded > 0 => 'Partially Loaded',
                        default => 'Pending',
                    },
                    'total_items_count' => $total,
                    'loaded_items_count' => $loaded,
                    'fulfillment_percentage' => $fulfillmentPct,
                    'shop' => $order->shop ? [
                        'warehouse_tag' => $order->shop->warehouse_tag,
                        'code' => $order->shop->code,
                        'name' => $order->shop->name,
                    ] : null,
                    'dispatch_url' => route('warehouse.receiver.loadout.order.dispatch', $order->id),
                    'dispatch_partial_url' => route('warehouse.receiver.loadout.order.dispatch-partial', $order->id),
                    'ship_url' => route('warehouse.receiver.loadout.order.ship', $order->id),
                    'loaded_items' => $order->items
                        ->where('sorting_status', 'loaded')
                        ->map(fn ($item) => [
                            'product_name' => $item->product?->name,
                            'product_grade' => $item->product_grade ?? 'A',
                            'loaded_qty' => (float) $item->loaded_qty,
                            'unit' => $item->unit,
                            'discrepancy_type' => $item->loadout_discrepancy_type,
                            'discrepancy_note' => $item->loadout_discrepancy_note,
                        ])->values(),
                    'all_items' => $order->items->map(fn ($item) => [
                        'product_name' => $item->product?->name,
                        'approved_qty' => (float) ($item->approved_qty ?: $item->requested_qty),
                        'loaded_qty' => (float) $item->loaded_qty,
                        'unit' => $item->unit,
                        'sorting_status' => $item->sorting_status,
                    ])->values(),
                ];
            });

        return response()->json([
            'success' => true,
            'date' => $date,
            'orders' => $orders,
        ]);
    }

    public function receiveDirectPurchaseForm(ShopOrder $order, Request $request): View
    {
        $this->authorizeReceiverAccess($request);

        abort_unless($order->isAdminDirectPurchase(), 404);

        $order->loadMissing(['items.product.category']);
        $warehouses = Warehouse::active()->orderBy('name')->get();

        return view('warehouse-receiver.receive_direct_purchase', compact('order', 'warehouses'));
    }

    public function receiveDirectPurchase(ShopOrder $order, Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        abort_unless($order->isAdminDirectPurchase(), 404);

        if ($order->delivery_status !== 'pending_delivery' || $order->is_allocation_completed) {
            return redirect()->back()->withErrors(['This direct purchase has already been received for warehouse flow.']);
        }

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ]);

        $order->loadMissing('items.product');

        if ($order->items->isEmpty()) {
            return redirect()->back()->withErrors(['This direct purchase has no items to receive.']);
        }

        $userId = (int) $request->user()->id;

        DB::transaction(function () use ($order, $validated, $userId): void {
            foreach ($order->items as $item) {
                $itemData = $validated['items'][$item->id] ?? null;
                if (! is_array($itemData)) {
                    throw ValidationException::withMessages([
                        "items.{$item->id}.warehouse_id" => 'Select a warehouse for every direct purchase item.',
                    ]);
                }

                $quantity = (float) ($item->approved_qty > 0 ? $item->approved_qty : $item->requested_qty);

                if ($quantity <= 0.0) {
                    continue;
                }

                StockBatch::query()->create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => (int) $itemData['warehouse_id'],
                    'created_by' => $userId,
                    'reference' => $this->generateDirectPurchaseBatchReference(),
                    'received_at' => $order->business_date,
                    'total_kg' => $quantity,
                    'cost_per_kg' => 0,
                    'transport_cost' => 0,
                    'labour_cost' => 0,
                    'status' => BatchStatus::Pending,
                    'warehouse_receive_pending' => false,
                    'warehouse_confirmed_at' => now(),
                    'warehouse_confirmed_by' => $userId,
                    'notes' => "Direct purchase received from admin order: {$order->order_number}",
                ]);
            }

            $order->update([
                'delivery_status' => 'ready_for_dispatch',
                'sorting_notes' => trim((string) $order->sorting_notes."\nDirect purchase received by warehouse."),
            ]);
        });

        return redirect()
            ->route('warehouse.receiver.checklist', ['date' => $order->business_date->format('Y-m-d'), 'tab' => 'pending'])
            ->with('success', 'Direct Purchase received into warehouse inventory.');
    }

    /**
     * Show the receive form for a specific vendor sheet (GRN).
     */
    public function receiveGrnForm(GoodsReceived $grn, Request $request): View
    {
        $this->authorizeReceiverAccess($request);

        $grn->load(['purchaseOrder.supplier', 'purchaseOrder.purchaserCart.user', 'items.product.category', 'items.purchaseOrderItem']);
        $warehouses = Warehouse::active()->orderBy('name')->get();

        // Group items by category name
        $groupedItems = $grn->items->groupBy(fn ($item) => $item->product->category->name ?? 'Uncategorized');

        return view('warehouse-receiver.receive_grn', compact('grn', 'groupedItems', 'warehouses'));
    }

    /**
     * Process physical receipt of the vendor sheet (GRN).
     */
    public function processReceiveGrn(GoodsReceived $grn, Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'items' => ['required', 'array'],
            'items.*.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'items.*.received_qty' => ['required', 'numeric', 'min:0'],
            'items.*.discrepancy_type' => ['required', 'string', 'in:none,wastage,other'],
            'items.*.discrepancy_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $zeroPricedItems = $this->zeroPricedReceivedItems($grn, $validated['items']);

        if ($zeroPricedItems !== []) {
            return redirect()
                ->back()
                ->withInput()
                ->with('warning', 'Price is zero on: '.implode(', ', $zeroPricedItems).'. Update the purchaser bill price before receiving this vendor sheet.');
        }

        $userId = (int) $request->user()->id;

        $this->receiveGrnIntoWarehouse($grn, $validated['items'], (int) $validated['warehouse_id'], $userId);

        return redirect()
            ->route('warehouse.receiver.checklist', ['date' => $grn->received_at->format('Y-m-d')])
            ->with('success', 'Vendor sheet received and stock moved to inventory.');
    }

    /**
     * Receive the next pending vendor sheet batch for the selected date using current GRN quantities.
     */
    public function processReceiveAllGrns(Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $userId = (int) $request->user()->id;
        $fallbackWarehouseId = Warehouse::active()->orderBy('id')->value('id');

        if (! $fallbackWarehouseId) {
            return redirect()->back()->withErrors(['No active warehouse is available for receiving vendor sheets.']);
        }

        $pendingGrns = GoodsReceived::query()
            ->where('status', 'pending_approval')
            ->whereDate('received_at', $date)
            ->with(['items.product', 'items.purchaseOrderItem'])
            ->orderBy('created_at')
            ->limit(self::BULK_RECEIVE_GRN_LIMIT)
            ->get();

        if ($pendingGrns->isEmpty()) {
            return redirect()->back()->withErrors(['No pending vendor sheets to receive for this date.']);
        }

        $zeroPricedItems = [];
        $receivePayloads = [];

        foreach ($pendingGrns as $pendingGrn) {
            $items = $this->defaultReceiveItemsForGrn($pendingGrn, (int) $fallbackWarehouseId);
            $zeroPriced = $this->zeroPricedReceivedItems($pendingGrn, $items);

            if ($zeroPriced !== []) {
                $zeroPricedItems[] = "{$pendingGrn->grn_number}: ".implode(', ', $zeroPriced);
            }

            $receivePayloads[(int) $pendingGrn->id] = $items;
        }

        if ($zeroPricedItems !== []) {
            return redirect()
                ->back()
                ->with('warning', 'Price is zero on: '.implode('; ', $zeroPricedItems).'. Update purchaser bill prices before receiving this vendor sheet batch.');
        }

        foreach ($pendingGrns as $pendingGrn) {
            $this->receiveGrnIntoWarehouse(
                $pendingGrn,
                $receivePayloads[(int) $pendingGrn->id],
                (int) $fallbackWarehouseId,
                $userId
            );
        }

        $remainingCount = GoodsReceived::query()
            ->where('status', 'pending_approval')
            ->whereDate('received_at', $date)
            ->count();

        return redirect()
            ->route('warehouse.receiver.checklist', ['date' => $date])
            ->with('success', "{$pendingGrns->count()} vendor sheet(s) received and moved to inventory. {$remainingCount} still pending.");
    }

    /**
     * Confirm a single pending batch.
     */
    public function confirm(StockBatch $batch, Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        if (! $batch->warehouse_receive_pending) {
            return redirect()->back()->withErrors(['This batch has already been confirmed.']);
        }

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ]);

        $batch->update([
            'warehouse_id' => $validated['warehouse_id'],
            'warehouse_receive_pending' => false,
            'warehouse_confirmed_at' => now(),
            'warehouse_confirmed_by' => $request->user()->id,
        ]);

        activity()
            ->performedOn($batch)
            ->causedBy($request->user()->id)
            ->log('stock_batch.warehouse_confirmed');

        if ($batch->goodsReceived) {
            $this->updatePurchaserCartReceiptStatus($batch->goodsReceived);
        }

        return redirect()
            ->route('warehouse.receiver.checklist', ['date' => $batch->received_at->format('Y-m-d')])
            ->with('success', "{$batch->product->name} confirmed as received in warehouse.");
    }

    /**
     * Confirm all pending batches for a date at once.
     */
    public function confirmAll(Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        $date = $request->input('date', app(PurchaserBusinessDayService::class)->operationalDate()->toDateString());
        $userId = (int) $request->user()->id;

        $pending = StockBatch::where('warehouse_receive_pending', true)
            ->whereDate('received_at', $date)
            ->with('product')
            ->get();

        if ($pending->isEmpty()) {
            return redirect()->back()->withErrors(['No pending batches to confirm for this date.']);
        }

        $grnIds = $pending->pluck('goods_received_id')->filter()->unique();

        $firstWarehouse = Warehouse::active()->orderBy('id')->first();
        $firstWarehouseId = $firstWarehouse?->id;

        foreach ($pending as $batch) {
            $warehouseId = $batch->product->default_warehouse_id ?? $firstWarehouseId;
            $batch->update([
                'warehouse_id' => $warehouseId,
                'warehouse_receive_pending' => false,
                'warehouse_confirmed_at' => now(),
                'warehouse_confirmed_by' => $userId,
            ]);
        }

        foreach ($grnIds as $grnId) {
            $grn = GoodsReceived::find($grnId);
            if ($grn) {
                $this->updatePurchaserCartReceiptStatus($grn);
            }
        }

        return redirect()
            ->route('warehouse.receiver.checklist', ['date' => $date])
            ->with('success', "All {$pending->count()} batch(es) confirmed as received.");
    }

    private function authorizeReceiverAccess(Request $request): void
    {
        if (
            ! $request->user()->hasRole('warehouse_receiver')
            && ! $request->user()->hasRole('admin')
            && ! $request->user()->can('warehouse.receive.confirm')
        ) {
            abort(403, 'Unauthorized access.');
        }
    }

    /**
     * Show the loadout details for a single shop order.
     */
    public function loadoutDetails(ShopOrder $order, Request $request): View
    {
        $this->authorizeReceiverAccess($request);

        abort_if($order->isAdminDirectPurchase(), 404);

        $order->load(['shop', 'items.product']);

        foreach ($order->items as $item) {
            $item->inventory_stock = $this->stockLedgerService->availableSortedStockForProduct($item->product_id) + (float) $item->loaded_qty;
        }

        return view('warehouse-receiver.loadout_details', compact('order'));
    }

    /**
     * Mark a single shop order item as loaded and reduce from inventory.
     */
    public function loadoutItem(ShopOrderItem $item, Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        if ($item->sorting_status === 'loaded') {
            return redirect()->back()->withErrors(['This item is already loaded.']);
        }

        $validated = $request->validate([
            'loaded_qty' => ['nullable', 'numeric', 'min:0'],
            'discrepancy_type' => ['nullable', 'string', 'in:none,wastage,other'],
            'discrepancy_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $approvedQty = (float) ($item->approved_qty > 0 ? $item->approved_qty : $item->requested_qty);
        $loadedQty = $request->has('loaded_qty') ? (float) $validated['loaded_qty'] : $approvedQty;
        $discrepancyType = $validated['discrepancy_type'] ?? 'none';
        $discrepancyNote = $validated['discrepancy_note'] ?? null;

        if ($loadedQty > $approvedQty) {
            return redirect()->back()->withErrors(['Loaded quantity cannot exceed approved quantity.']);
        }

        try {
            DB::transaction(function () use ($item, $loadedQty, $request) {
                $userId = $request->user()->id;

                if ($loadedQty > 0) {
                    $this->stockLedgerService->consumeStockForProductAllowingNegative(
                        $item->product_id,
                        $loadedQty,
                        $userId,
                        StockMovementType::Out,
                        "Loadout: Shop Order {$item->order->order_number} to {$item->order->shop->name}"
                    );
                }

                $item->update([
                    'loaded_qty' => $loadedQty,
                    'loadout_discrepancy_type' => 'none',
                    'loadout_discrepancy_note' => null,
                    'sorting_status' => 'loaded',
                    'is_sorted' => true,
                    'sorted_at' => now(),
                    'sorted_by' => $userId,
                ]);
            });

            return redirect()->back()->with('success', "{$item->product->name} marked as loaded and reduced from inventory.");
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([$e->getMessage()]);
        }
    }

    public function loadoutOrderAll(ShopOrder $order, Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        $pendingItems = $order->items()->where('sorting_status', '!=', 'loaded')->get();

        if ($pendingItems->isEmpty()) {
            return redirect()->back()->withErrors(['All items in this order are already loaded.']);
        }

        $skipUnavailable = $request->boolean('skip_unavailable');
        $skippedNames = [];
        $loadedCount = 0;

        try {
            DB::transaction(function () use ($pendingItems, $order, $request, $skipUnavailable, &$skippedNames, &$loadedCount) {
                $userId = $request->user()->id;

                foreach ($pendingItems as $item) {
                    $approvedQty = $item->approved_qty > 0 ? (float) $item->approved_qty : (float) $item->requested_qty;
                    $availableStock = $this->stockLedgerService->availableSortedStockForProduct($item->product_id);

                    // Skip if skip_unavailable is checked and available stock is less than approved quantity
                    if ($skipUnavailable && $availableStock < $approvedQty) {
                        $skippedNames[] = $item->product->name;

                        continue;
                    }

                    $qtyToLoad = $approvedQty;

                    if ($qtyToLoad <= 0.0) {
                        $skippedNames[] = $item->product->name;

                        continue;
                    }

                    // Deduct stock immediately (allows negative stock)
                    $this->stockLedgerService->consumeStockForProductAllowingNegative(
                        $item->product_id,
                        $qtyToLoad,
                        $userId,
                        StockMovementType::Out,
                        "Loadout: Shop Order {$order->order_number} to {$order->shop->name}"
                    );

                    $item->update([
                        'loaded_qty' => $qtyToLoad,
                        'loadout_discrepancy_type' => 'none',
                        'loadout_discrepancy_note' => null,
                        'sorting_status' => 'loaded',
                        'is_sorted' => true,
                        'sorted_at' => now(),
                        'sorted_by' => $userId,
                    ]);

                    $loadedCount++;
                }
            });

            if (! empty($skippedNames)) {
                $skippedList = implode(', ', array_unique($skippedNames));

                return redirect()->route('warehouse.receiver.loadout.show', $order)
                    ->with('success', "Loaded {$loadedCount} item(s). (Skipped/Partial: {$skippedList} due to insufficient stock)");
            }

            return redirect()->route('warehouse.receiver.loadout.show', $order)
                ->with('success', "All {$loadedCount} pending item(s) processed for loadout successfully.");
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([$e->getMessage()]);
        }
    }

    /**
     * Dispatch order and mark as in transit out for delivery.
     */
    public function dispatchOrder(ShopOrder $order, Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        if ($order->delivery_status !== 'pending_delivery' && $order->delivery_status !== 'in_transit' && $order->delivery_status !== 'ready_for_dispatch') {
            return redirect()->back()->withErrors(['This order is already completed.']);
        }

        $loadedCount = $order->items()->where('sorting_status', 'loaded')->count();
        if ($loadedCount === 0) {
            return redirect()->back()->withErrors(['Cannot dispatch: No items have been loaded yet.']);
        }

        DB::transaction(function () use ($order, $request): void {
            $userId = $request->user()->id;

            // Split items where loaded_qty < approved_qty (but > 0)
            $items = $order->items()->get();
            foreach ($items as $item) {
                $approvedQty = $item->approved_qty > 0 ? (float) $item->approved_qty : (float) $item->requested_qty;
                $loadedQty = $item->loaded_qty !== null ? (float) $item->loaded_qty : 0.0;

                if ($loadedQty > 0.0 && $loadedQty < $approvedQty) {
                    $remainingQty = $approvedQty - $loadedQty;

                    // Update existing item to represent only the loaded part
                    $item->update([
                        'requested_qty' => $loadedQty,
                        'approved_qty' => $loadedQty,
                    ]);

                    // Create a new pending item for the remaining balance
                    ShopOrderItem::create([
                        'shop_order_id' => $order->id,
                        'product_id' => $item->product_id,
                        'product_grade' => $item->product_grade ?? 'A',
                        'unit' => $item->unit,
                        'requested_qty' => $remainingQty,
                        'approved_qty' => $remainingQty,
                        'loaded_qty' => null,
                        'sorting_status' => 'allocated',
                        'is_sorted' => false,
                    ]);
                }
            }

            $order->update([
                'delivery_status' => 'ready_for_dispatch',
                'is_allocation_completed' => false,
            ]);

            activity()
                ->performedOn($order)
                ->causedBy($userId)
                ->log('shop_order.moved_to_delivery');
        });

        return redirect()
            ->route('warehouse.receiver.checklist', ['date' => $order->business_date->format('Y-m-d')])
            ->with('success', "Order {$order->order_number} moved to delivery successfully.");
    }

    public function dispatchPartialOrder(ShopOrder $order, Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        if ($order->delivery_status !== 'pending_delivery' && $order->delivery_status !== 'in_transit' && $order->delivery_status !== 'ready_for_dispatch') {
            return redirect()->back()->withErrors(['This order is already completed.']);
        }

        try {
            DB::transaction(function () use ($order, $request) {
                $userId = $request->user()->id;

                // Split items where loaded_qty < approved_qty (but > 0)
                $items = $order->items()->get();
                foreach ($items as $item) {
                    $approvedQty = $item->approved_qty > 0 ? (float) $item->approved_qty : (float) $item->requested_qty;
                    $loadedQty = $item->loaded_qty !== null ? (float) $item->loaded_qty : 0.0;

                    if ($loadedQty > 0.0 && $loadedQty < $approvedQty) {
                        $remainingQty = $approvedQty - $loadedQty;

                        // Update existing item to represent only the loaded part
                        $item->update([
                            'requested_qty' => $loadedQty,
                            'approved_qty' => $loadedQty,
                        ]);

                        // Create a new pending item for the remaining balance
                        ShopOrderItem::create([
                            'shop_order_id' => $order->id,
                            'product_id' => $item->product_id,
                            'product_grade' => $item->product_grade ?? 'A',
                            'unit' => $item->unit,
                            'requested_qty' => $remainingQty,
                            'approved_qty' => $remainingQty,
                            'loaded_qty' => null,
                            'sorting_status' => 'allocated',
                            'is_sorted' => false,
                        ]);
                    }
                }

                // Transition order to ready_for_dispatch state
                $order->update([
                    'delivery_status' => 'ready_for_dispatch',
                    'is_allocation_completed' => false,
                ]);

                activity()
                    ->performedOn($order)
                    ->causedBy($userId)
                    ->log('shop_order.moved_to_delivery_partial');
            });

            return redirect()
                ->route('warehouse.receiver.checklist', ['date' => $order->business_date->format('Y-m-d')])
                ->with('success', "Order {$order->order_number} moved to delivery as a partial delivery.");
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([$e->getMessage()]);
        }
    }

    /**
     * Mark order out for delivery (dispatch from ready_for_dispatch to in_transit).
     */
    public function shipOrder(ShopOrder $order, Request $request): RedirectResponse
    {
        $this->authorizeReceiverAccess($request);

        if ($order->delivery_status !== 'ready_for_dispatch') {
            return redirect()->back()->withErrors(['This order is not ready for dispatch or already dispatched.']);
        }

        try {
            DB::transaction(function () use ($order, $request) {
                $userId = $request->user()->id;

                $order->update([
                    'delivery_status' => 'in_transit',
                    'is_allocation_completed' => true,
                ]);

                activity()
                    ->performedOn($order)
                    ->causedBy($userId)
                    ->log('shop_order.marked_out_for_delivery');
            });

            return redirect()->route('warehouse.receiver.checklist', ['date' => $order->business_date->format('Y-m-d'), 'tab' => 'confirmed'])
                ->with('success', "Order {$order->order_number} marked out for delivery successfully.");
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([$e->getMessage()]);
        }
    }

    /**
     * @param  array<int|string, array{received_qty:mixed}>  $items
     * @return array<int, string>
     */
    private function zeroPricedReceivedItems(GoodsReceived $grn, array $items): array
    {
        $grn->loadMissing(['items.product', 'items.purchaseOrderItem']);

        return $grn->items
            ->filter(function ($item) use ($items): bool {
                $receivedQty = (float) ($items[$item->id]['received_qty'] ?? 0);
                $unitPrice = (float) ($item->purchaseOrderItem?->unit_price ?? 0);

                return $receivedQty > 0.0 && $unitPrice <= 0.0;
            })
            ->map(fn ($item): string => $item->product?->name ?? "Item #{$item->id}")
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{warehouse_id:int, received_qty:float, discrepancy_type:string, discrepancy_note:null}>
     */
    private function defaultReceiveItemsForGrn(GoodsReceived $grn, int $fallbackWarehouseId): array
    {
        $grn->loadMissing(['items.product']);

        return $grn->items
            ->mapWithKeys(function ($item) use ($fallbackWarehouseId): array {
                return [
                    $item->id => [
                        'warehouse_id' => (int) ($item->product?->default_warehouse_id ?: $fallbackWarehouseId),
                        'received_qty' => (float) $item->received_qty,
                        'discrepancy_type' => 'none',
                        'discrepancy_note' => null,
                    ],
                ];
            })
            ->all();
    }

    /**
     * @param  array<int|string, array{warehouse_id?:mixed, received_qty:mixed, discrepancy_type?:string, discrepancy_note?:string|null}>  $items
     */
    private function receiveGrnIntoWarehouse(GoodsReceived $grn, array $items, int $fallbackWarehouseId, int $userId): void
    {
        DB::transaction(function () use ($grn, $items, $fallbackWarehouseId, $userId): void {
            $grn->loadMissing(['items.purchaseOrderItem', 'items.product']);

            foreach ($items as $itemId => $itemData) {
                $item = $grn->items->firstWhere('id', (int) $itemId) ?? $grn->items()->findOrFail((int) $itemId);
                $originalPurchasedQty = (float) ($item->purchaseOrderItem?->quantity ?? $item->received_qty);
                $receivedQty = (float) $itemData['received_qty'];
                $variance = $receivedQty - (float) ($item->purchaseOrderItem?->quantity ?? 0.0);

                $item->update([
                    'received_qty' => $receivedQty,
                    'variance' => $variance,
                    'purchased_qty' => $originalPurchasedQty,
                    'discrepancy_type' => $itemData['discrepancy_type'] ?? 'none',
                    'discrepancy_note' => $itemData['discrepancy_note'] ?? null,
                ]);
            }

            $approvedGrn = app(ApproveGoodsReceiptAction::class)->execute($grn, $userId);
            $approvedGrn->loadMissing('items');

            StockBatch::where('goods_received_id', $approvedGrn->id)
                ->get()
                ->each(function (StockBatch $batch) use ($approvedGrn, $items, $fallbackWarehouseId, $userId): void {
                    $grnItem = $batch->goods_received_item_id
                        ? $approvedGrn->items->firstWhere('id', $batch->goods_received_item_id)
                        : $approvedGrn->items->firstWhere('product_id', $batch->product_id);
                    $discrepancyType = $grnItem?->discrepancy_type ?? 'none';
                    $discrepancyNote = $grnItem?->discrepancy_note;
                    $itemWarehouseId = (int) ($items[$grnItem?->id]['warehouse_id'] ?? $fallbackWarehouseId);

                    $batch->update([
                        'warehouse_id' => $itemWarehouseId,
                        'warehouse_receive_pending' => false,
                        'warehouse_confirmed_at' => now(),
                        'warehouse_confirmed_by' => $userId,
                    ]);

                    if ($batch->grading_mode === 'fixed_purchase_grade') {
                        StockMovement::query()->firstOrCreate(
                            [
                                'batch_id' => $batch->id,
                                'type' => StockMovementType::In->value,
                                'grade' => $batch->purchase_grade,
                            ],
                            [
                                'product_id' => $batch->product_id,
                                'warehouse_id' => $itemWarehouseId,
                                'created_by' => $userId,
                                'quantity' => $batch->total_kg,
                                'cost_per_unit' => $batch->cost_per_kg,
                                'notes' => "Fixed Grade {$batch->purchase_grade} receipt from {$approvedGrn->grn_number}",
                            ],
                        );

                        $batch->update([
                            'status' => BatchStatus::Sorted,
                            'sorted_at' => now(),
                        ]);
                    }

                    activity()
                        ->performedOn($batch)
                        ->causedBy($userId)
                        ->log('stock_batch.warehouse_confirmed');

                    if ($discrepancyType === 'wastage') {
                        $purchasedQty = $grnItem?->purchased_qty ?? 0.0;
                        $receivedQty = $grnItem?->received_qty ?? 0.0;
                        $diff = $purchasedQty - $receivedQty;

                        if ($diff > 0.0) {
                            app(WastageService::class)->record(new WastageEntryData(
                                productId: $batch->product_id,
                                batchId: $batch->id,
                                grade: 'U',
                                quantity: $diff,
                                costPerKg: (float) $batch->cost_per_kg,
                                reason: WastageReason::TransitDamage,
                                wastageDate: now()->toDateString(),
                                notes: 'Receiving discrepancy wastage: '.($discrepancyNote ?? 'Vendor goods discrepancy'),
                            ), $userId);
                        }
                    }
                });

            $this->updatePurchaserCartReceiptStatus($approvedGrn);
        });
    }

    private function updatePurchaserCartReceiptStatus(GoodsReceived $grn): void
    {
        $hasPendingBatches = StockBatch::where('goods_received_id', $grn->id)
            ->where('warehouse_receive_pending', true)
            ->exists();

        if (! $hasPendingBatches) {
            $cart = $grn->purchaseOrder?->purchaserCart;
            if ($cart && ! $cart->goods_received_at) {
                $cart->update([
                    'goods_received_at' => now(),
                ]);
            }
        }
    }

    private function generateDirectPurchaseBatchReference(): string
    {
        do {
            $reference = 'DP-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(2)));
        } while (StockBatch::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
