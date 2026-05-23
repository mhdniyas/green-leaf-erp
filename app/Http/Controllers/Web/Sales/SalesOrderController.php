<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Sales;

use App\DTOs\Sales\SalesOrderData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Sales\StoreSalesOrderRequest;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Services\Sales\CustomerService;
use App\Services\Sales\SalesOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SalesOrderController extends Controller
{
    public function __construct(
        private readonly SalesOrderService $service,
        private readonly CustomerService $customerService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SalesOrder::class);

        $orders = $this->service->paginate(20);

        return view('sales.orders.index', compact('orders'));
    }

    public function create(): View
    {
        Gate::authorize('create', SalesOrder::class);

        $customers = $this->customerService->all();
        $products = Product::with('category')->where('is_active', true)->orderBy('name')->get();

        return view('sales.orders.create', compact('customers', 'products'));
    }

    public function store(StoreSalesOrderRequest $request): RedirectResponse
    {
        $so = $this->service->create(
            SalesOrderData::fromRequest($request),
            $request->user()->id
        );

        return redirect()->route('sales.orders.show', $so)
            ->with('success', "Sales order {$so->so_number} created.");
    }

    public function show(SalesOrder $order): View
    {
        Gate::authorize('view', $order);

        $order->load(['customer', 'items.product', 'createdBy', 'invoice.payments']);

        return view('sales.orders.show', compact('order'));
    }

    public function edit(SalesOrder $order): View
    {
        Gate::authorize('update', $order);

        abort_unless($order->status->canBeConfirmed(), 403, 'Only draft orders can be edited.');

        $customers = $this->customerService->all();
        $products = Product::with('category')->where('is_active', true)->orderBy('name')->get();

        return view('sales.orders.edit', compact('order', 'customers', 'products'));
    }

    public function update(StoreSalesOrderRequest $request, SalesOrder $order): RedirectResponse
    {
        abort_unless($order->status->canBeConfirmed(), 403, 'Only draft orders can be edited.');

        $this->service->update($order, SalesOrderData::fromRequest($request));

        return redirect()->route('sales.orders.show', $order)
            ->with('success', 'Sales order updated.');
    }

    public function confirm(SalesOrder $order): RedirectResponse
    {
        Gate::authorize('confirm', $order);

        try {
            $this->service->confirm($order, request()->user()->id);

            return redirect()->route('sales.orders.show', $order)
                ->with('success', "Order {$order->so_number} confirmed and stock deducted.");
        } catch (\RuntimeException $e) {
            return redirect()->route('sales.orders.show', $order)
                ->with('error', $e->getMessage());
        }
    }

    public function dispatch(SalesOrder $order): RedirectResponse
    {
        Gate::authorize('dispatch', $order);

        try {
            $this->service->dispatch($order);

            return redirect()->route('sales.orders.show', $order)
                ->with('success', "Order {$order->so_number} marked as dispatched.");
        } catch (\RuntimeException $e) {
            return redirect()->route('sales.orders.show', $order)
                ->with('error', $e->getMessage());
        }
    }

    public function cancel(SalesOrder $order): RedirectResponse
    {
        Gate::authorize('cancel', $order);

        try {
            $this->service->cancel($order, request()->user()->id);

            return redirect()->route('sales.orders.show', $order)
                ->with('success', "Order {$order->so_number} cancelled.");
        } catch (\RuntimeException $e) {
            return redirect()->route('sales.orders.show', $order)
                ->with('error', $e->getMessage());
        }
    }

    public function destroy(SalesOrder $order): RedirectResponse
    {
        Gate::authorize('delete', $order);

        $this->service->delete($order);

        return redirect()->route('sales.orders.index')
            ->with('success', 'Sales order deleted.');
    }
}
