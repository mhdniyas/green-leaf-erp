<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\DTOs\Purchasing\PurchaseOrderData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Purchasing\StorePurchaseOrderRequest;
use App\Http\Requests\Web\Purchasing\UpdatePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Repositories\Inventory\ProductRepository;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Purchasing\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $orders = $this->service->paginate(20);

        return view('purchasing.orders.index', compact('orders'));
    }

    public function create(): View
    {
        Gate::authorize('create', PurchaseOrder::class);

        $suppliers = $this->supplierService->all();
        $products = $this->productRepository->findAllActive();

        return view('purchasing.orders.create', compact('suppliers', 'products'));
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

        return view('purchasing.orders.show', compact('order'));
    }

    public function edit(PurchaseOrder $order): View
    {
        Gate::authorize('update', $order);

        $suppliers = $this->supplierService->all();
        $products = $this->productRepository->findAllActive();
        $order->load('items');

        return view('purchasing.orders.edit', compact('order', 'suppliers', 'products'));
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $order): RedirectResponse
    {
        $this->service->update($order, PurchaseOrderData::fromRequest($request));

        return redirect()->route('purchasing.orders.show', $order)
            ->with('success', 'Purchase Order updated successfully.');
    }

    public function approve(PurchaseOrder $order): RedirectResponse
    {
        Gate::authorize('approve', $order);

        $this->service->approve($order);

        return redirect()->route('purchasing.orders.show', $order)
            ->with('success', 'Purchase Order approved successfully.');
    }

    public function destroy(PurchaseOrder $order): RedirectResponse
    {
        Gate::authorize('delete', $order);

        $this->service->delete($order);

        return redirect()->route('purchasing.orders.index')
            ->with('success', 'Purchase Order deleted successfully.');
    }
}
