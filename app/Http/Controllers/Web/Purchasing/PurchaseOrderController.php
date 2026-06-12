<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\DTOs\Purchasing\PurchaseOrderData;
use App\Enums\Purchasing\POStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Purchasing\StorePurchaseOrderRequest;
use App\Http\Requests\Web\Purchasing\UpdatePurchaseOrderRequest;
use App\Models\DailyPriceApproval;
use App\Models\GoodsReceived;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Repositories\Inventory\ProductRepository;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Purchasing\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

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

        $selectedDate = (string) $request->input('date', today()->addDay()->toDateString());

        $pendingShopOrders = ShopOrder::query()
            ->whereDate('business_date', $selectedDate)
            ->where(function ($query): void {
                $query->whereIn('state', ['submitted', 'update_requested'])
                    ->orWhere('has_pending_revision', true);
            })
            ->with(['shop', 'items'])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get();

        $approvedShopOrdersCount = ShopOrder::query()
            ->whereDate('business_date', $selectedDate)
            ->where('state', 'approved')
            ->count();

        $generatedPurchaseOrdersCount = PurchaseOrder::query()
            ->whereDate('order_date', $selectedDate)
            ->count();

        $reviewedPurchases = GoodsReceived::query()
            ->whereDate('received_at', $selectedDate)
            ->where('status', 'approved')
            ->with(['purchaseOrder.supplier', 'items.product', 'receivedBy', 'approvedBy'])
            ->latest('id')
            ->get();

        $pendingRegularPurchases = $reviewedPurchases->where('is_extra', false)->values();
        $pendingAddonPurchases = $reviewedPurchases->where('is_extra', true)->values();

        $grnCorrections = GoodsReceived::query()
            ->whereDate('received_at', $selectedDate)
            ->where('status', 'recheck_required')
            ->with(['purchaseOrder.supplier', 'updatedBy'])
            ->latest('updated_at')
            ->get();

        $pendingPriceApprovalsCount = DailyPriceApproval::query()
            ->whereDate('business_date', $selectedDate)
            ->where('status', 'pending')
            ->count();

        $invoiceExceptions = ShopInvoice::query()
            ->whereDate('business_date', $selectedDate)
            ->where(function ($query): void {
                $query->whereIn('status', ['delivery_review', 'payment_pending'])
                    ->orWhere('shortage_total', '>', 0)
                    ->orWhere('discount_total', '>', 0);
            })
            ->with(['shop', 'order'])
            ->latest('id')
            ->get();

        $todaySummary = [
            'orders_approved' => $approvedShopOrdersCount,
            'purchase_batches_approved' => GoodsReceived::query()
                ->whereDate('received_at', $selectedDate)
                ->where('status', 'approved')
                ->count(),
            'grn_corrections' => $grnCorrections->count(),
            'prices_pending_admin' => $pendingPriceApprovalsCount,
            'invoices_finalized' => ShopInvoice::query()
                ->whereDate('business_date', $selectedDate)
                ->whereIn('status', ['finalized', 'paid'])
                ->count(),
        ];

        $recentPurchaseOrders = PurchaseOrder::query()
            ->with(['supplier', 'createdBy', 'goodsReceiveds'])
            ->whereDate('order_date', $selectedDate)
            ->latest('id')
            ->take(6)
            ->get();

        return view('purchase-manager.orders.index', compact(
            'selectedDate',
            'pendingShopOrders',
            'approvedShopOrdersCount',
            'generatedPurchaseOrdersCount',
            'pendingRegularPurchases',
            'pendingAddonPurchases',
            'grnCorrections',
            'pendingPriceApprovalsCount',
            'invoiceExceptions',
            'todaySummary',
            'recentPurchaseOrders',
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
