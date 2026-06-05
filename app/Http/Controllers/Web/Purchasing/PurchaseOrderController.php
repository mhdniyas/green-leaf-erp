<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\DTOs\Purchasing\PurchaseOrderData;
use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Purchasing\StorePurchaseOrderRequest;
use App\Http\Requests\Web\Purchasing\UpdatePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Repositories\Inventory\ProductRepository;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Purchasing\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $service,
        private readonly SupplierService $supplierService,
        private readonly ProductRepository $productRepository,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        $activeTab = $request->input('tab', 'all');

        // 1. All Orders tab query
        $query = PurchaseOrder::query()->with(['supplier', 'createdBy', 'goodsReceiveds']);

        // Filters for All Orders
        $supplierId = $request->input('supplier_id');
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $dateFilter = $request->input('date_filter');
        if ($dateFilter === 'this_month') {
            $query->whereYear('order_date', today()->year)
                ->whereMonth('order_date', today()->month);
        } elseif ($dateFilter === 'last_month') {
            $lastMonth = today()->subMonth();
            $query->whereYear('order_date', $lastMonth->year)
                ->whereMonth('order_date', $lastMonth->month);
        } elseif ($dateFilter === 'custom' && $request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('order_date', [$request->input('start_date'), $request->input('end_date')]);
        }

        $allOrders = $query->orderByDesc('order_date')->orderByDesc('id')->paginate(15)->withQueryString();

        // 2. Pending Approval tab query (Draft POs)
        $pendingOrders = PurchaseOrder::where('status', POStatus::Draft)
            ->with(['supplier', 'createdBy'])
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get();

        // 3. Approval History tab query (retrieved from Spatie Activity log)
        $approvalHistory = Activity::where('subject_type', PurchaseOrder::class)
            ->whereIn('description', ['Approved', 'Rejected'])
            ->with(['causer', 'subject'])
            ->orderByDesc('created_at')
            ->get();

        // 4. Order Analytics tab calculations
        $thisMonthSpend = (float) PurchaseOrderItem::whereHas('purchaseOrder', function ($q) {
            $q->whereYear('order_date', today()->year)
                ->whereMonth('order_date', today()->month)
                ->where('status', '!=', POStatus::Draft);
        })->selectRaw('SUM(quantity * unit_price) as total')->value('total');

        $thisMonthOrdersCount = PurchaseOrder::whereYear('order_date', today()->year)
            ->whereMonth('order_date', today()->month)
            ->where('status', '!=', POStatus::Draft)
            ->count();

        $avgOrderValue = $thisMonthOrdersCount > 0 ? $thisMonthSpend / $thisMonthOrdersCount : 0.0;

        // Top Supplier
        $topSupplierData = PurchaseOrder::join('purchase_order_items', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_orders.status', '!=', POStatus::Draft)
            ->select('purchase_orders.supplier_id', DB::raw('SUM(purchase_order_items.quantity * purchase_order_items.unit_price) as total_spend'))
            ->groupBy('purchase_orders.supplier_id')
            ->orderByDesc('total_spend')
            ->first();
        $topSupplier = $topSupplierData ? (Supplier::find($topSupplierData->supplier_id)?->name ?? 'N/A') : 'N/A';

        // Monthly Purchase Trend (Jan - Dec)
        $purchaseOrderItems = PurchaseOrderItem::whereHas('purchaseOrder', function ($q) {
            $q->where('status', '!=', POStatus::Draft)
                ->whereYear('order_date', today()->year);
        })
            ->with('purchaseOrder')
            ->get();

        $monthlyTrendRaw = [];
        foreach ($purchaseOrderItems as $item) {
            if ($item->purchaseOrder && $item->purchaseOrder->order_date) {
                $monthName = $item->purchaseOrder->order_date->format('M');
                $monthlyTrendRaw[$monthName] = ($monthlyTrendRaw[$monthName] ?? 0.0) + ((float) $item->quantity * (float) $item->unit_price);
            }
        }

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $monthlyTrend = [];
        foreach ($months as $m) {
            $monthlyTrend[$m] = $monthlyTrendRaw[$m] ?? 0.0;
        }

        $suppliers = Supplier::orderBy('name')->get();

        return view('purchase-manager.orders.index', compact(
            'allOrders',
            'pendingOrders',
            'approvalHistory',
            'thisMonthSpend',
            'thisMonthOrdersCount',
            'avgOrderValue',
            'topSupplier',
            'monthlyTrend',
            'activeTab',
            'suppliers'
        ));
    }

    public function create(): View
    {
        Gate::authorize('create', PurchaseOrder::class);

        $suppliers = $this->supplierService->all();
        $products = $this->productRepository->findAllActive();

        return view('purchase-manager.orders.create', compact('suppliers', 'products'));
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $order = $this->service->create(
            PurchaseOrderData::fromRequest($request),
            (int) $request->user()->id
        );

        return redirect()->route('purchasing.orders.show', $order)
            ->with('success', 'Purchase Order created successfully.');
    }

    public function show(PurchaseOrder $order): View
    {
        Gate::authorize('view', $order);

        $order->load(['supplier', 'items.product', 'createdBy']);

        // Query the most recent prior PO unit price for each product in the order
        $previousPrices = PurchaseOrderItem::join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->whereIn('purchase_order_items.product_id', $order->items->pluck('product_id'))
            ->where('purchase_orders.id', '<', $order->id)
            ->whereIn('purchase_order_items.id', function ($query) use ($order) {
                $query->selectRaw('MAX(poi.id)')
                    ->from('purchase_order_items as poi')
                    ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
                    ->where('po.id', '<', $order->id)
                    ->groupBy('poi.product_id');
            })
            ->pluck('unit_price', 'product_id');

        $products = $this->productRepository->findAllActive();

        return view('purchase-manager.orders.show', compact('order', 'previousPrices', 'products'));
    }

    public function updateItems(Request $request, PurchaseOrder $order): RedirectResponse
    {
        Gate::authorize('updateItems', $order);

        if (in_array($order->status->value, ['received', 'closed'])) {
            return redirect()->back()->with('error', 'Cannot update items on a received or closed purchase order.');
        }

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'exists:purchase_order_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.purchase_unit' => ['required', 'string', 'in:kg,packet,bag,box'],
            'items.*.packet_qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.weight_per_packet' => ['nullable', 'numeric', 'min:0'],
            'items.*.actual_weight' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.price_basis' => ['required', 'string', 'in:per_kg,per_unit'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
        ]);

        foreach ($data['items'] as $itemData) {
            $item = $order->items()->findOrFail($itemData['id']);

            $updateFields = [
                'product_id' => (int) $itemData['product_id'],
                'purchase_unit' => $itemData['purchase_unit'],
                'unit_price' => $itemData['unit_price'],
                'price_basis' => $itemData['price_basis'],
                'actual_weight' => ($itemData['actual_weight'] !== null && $itemData['actual_weight'] !== '') ? (float) $itemData['actual_weight'] : null,
            ];

            if ($itemData['purchase_unit'] === 'kg') {
                $updateFields['packet_qty'] = null;
                $updateFields['weight_per_packet'] = null;
                $updateFields['quantity'] = (float) $itemData['quantity'];
            } else {
                $updateFields['packet_qty'] = ($itemData['packet_qty'] !== null && $itemData['packet_qty'] !== '') ? (float) $itemData['packet_qty'] : null;
                $updateFields['weight_per_packet'] = ($itemData['weight_per_packet'] !== null && $itemData['weight_per_packet'] !== '') ? (float) $itemData['weight_per_packet'] : null;
                $updateFields['quantity'] = ((float) ($itemData['packet_qty'] ?? 0)) * ((float) ($itemData['weight_per_packet'] ?? 0));
            }

            $item->update($updateFields);
        }

        return redirect()->route('purchasing.orders.show', $order)
            ->with('success', 'Purchase order items updated successfully.');
    }

    public function edit(PurchaseOrder $order): View
    {
        Gate::authorize('update', $order);

        $suppliers = $this->supplierService->all();
        $products = $this->productRepository->findAllActive();
        $order->load('items');

        return view('purchase-manager.orders.edit', compact('order', 'suppliers', 'products'));
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $order): RedirectResponse
    {
        $this->service->update($order, PurchaseOrderData::fromRequest($request));

        return redirect()->route('purchasing.orders.show', $order)
            ->with('success', 'Purchase Order updated successfully.');
    }

    public function approve(PurchaseOrder $order, Request $request): RedirectResponse
    {
        Gate::authorize('approve', $order);

        $remarks = $request->input('remarks', 'Stock Required');

        DB::transaction(function () use ($order, $remarks) {
            $order->update(['status' => POStatus::Approved]);

            activity()
                ->performedOn($order)
                ->causedBy(auth()->user())
                ->withProperties([
                    'status' => 'approved',
                    'remarks' => $remarks,
                ])
                ->log('Approved');
        });

        return redirect()->route('purchasing.orders.show', $order)
            ->with('success', 'Purchase Order Approved successfully.');
    }

    public function reject(PurchaseOrder $order, Request $request): RedirectResponse
    {
        Gate::authorize('reject', $order);

        $remarks = $request->input('remarks', 'Duplicate Order');

        DB::transaction(function () use ($order, $remarks) {
            $order->update(['status' => POStatus::Rejected]);

            activity()
                ->performedOn($order)
                ->causedBy(auth()->user())
                ->withProperties([
                    'status' => 'rejected',
                    'remarks' => $remarks,
                ])
                ->log('Rejected');
        });

        return redirect()->route('purchasing.orders.index')
            ->with('success', 'Purchase Order Rejected successfully.');
    }

    public function send(PurchaseOrder $order, Request $request): RedirectResponse
    {
        Gate::authorize('send', $order);

        DB::transaction(function () use ($order) {
            $order->update(['status' => POStatus::SentToSupplier]);

            activity()
                ->performedOn($order)
                ->causedBy(auth()->user())
                ->withProperties([
                    'status' => 'sent_to_supplier',
                ])
                ->log('Sent to Supplier');
        });

        return redirect()->route('purchasing.orders.show', $order)
            ->with('success', 'Purchase Order sent to supplier successfully.');
    }

    public function destroy(PurchaseOrder $order): RedirectResponse
    {
        Gate::authorize('delete', $order);

        $this->service->delete($order);

        return redirect()->route('purchasing.orders.index')
            ->with('success', 'Purchase Order deleted successfully.');
    }
}
