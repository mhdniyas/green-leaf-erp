<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Sales;

use App\Http\Controllers\Controller;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Services\Sales\SalesInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SalesInvoiceController extends Controller
{
    public function __construct(
        private readonly SalesInvoiceService $service,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SalesInvoice::class);

        $invoices = $this->service->paginate(20);

        return view('sales.invoices.index', compact('invoices'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', SalesInvoice::class);

        $soId = $request->input('so_id');
        $order = $soId ? SalesOrder::with(['customer', 'items.product'])->findOrFail($soId) : null;

        return view('sales.invoices.create', compact('order'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', SalesInvoice::class);

        $request->validate([
            'sales_order_id' => ['required', 'integer', 'exists:sales_orders,id'],
        ]);

        $so = SalesOrder::findOrFail($request->input('sales_order_id'));

        try {
            $invoice = $this->service->createFromOrder($so, $request->user()->id);

            return redirect()->route('sales.invoices.show', $invoice)
                ->with('success', "Invoice {$invoice->invoice_number} created.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function show(SalesInvoice $invoice): View
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['customer', 'salesOrder.items.product', 'createdBy', 'payments.createdBy']);

        return view('sales.invoices.show', compact('invoice'));
    }
}
