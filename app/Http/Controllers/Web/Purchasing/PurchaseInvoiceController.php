<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\DTOs\Purchasing\PurchaseInvoiceData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Purchasing\StorePurchaseInvoiceRequest;
use App\Models\GoodsReceived;
use App\Models\PurchaseInvoice;
use App\Services\Purchasing\PurchaseInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PurchaseInvoiceController extends Controller
{
    public function __construct(
        private readonly PurchaseInvoiceService $service,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PurchaseInvoice::class);

        $invoices = $this->service->paginate(20);

        return view('purchasing.invoices.index', compact('invoices'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        Gate::authorize('create', PurchaseInvoice::class);

        $grnId = $request->integer('goods_received_id');
        if (! $grnId) {
            return redirect()->route('purchasing.grns.index')
                ->with('error', 'Please select a Goods Received Note to create an invoice.');
        }

        /** @var GoodsReceived $grn */
        $grn = GoodsReceived::findOrFail($grnId);
        $grn->load(['purchaseOrder.supplier', 'items.product']);

        // Check if there is an existing invoice for this GRN
        $existingInvoice = PurchaseInvoice::where('goods_received_id', $grnId)->first();
        if ($existingInvoice) {
            return redirect()->route('purchasing.invoices.show', $existingInvoice)
                ->with('warning', 'An invoice has already been created for this Goods Received Note.');
        }

        return view('purchasing.invoices.create', compact('grn'));
    }

    public function store(StorePurchaseInvoiceRequest $request): RedirectResponse
    {
        $invoice = $this->service->create(PurchaseInvoiceData::fromRequest($request));

        return redirect()->route('purchasing.invoices.show', $invoice)
            ->with('success', 'Purchase invoice created and matched successfully.');
    }

    public function show(PurchaseInvoice $invoice): View
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['goodsReceived.purchaseOrder', 'supplier']);

        return view('purchasing.invoices.show', compact('invoice'));
    }

    public function updateStatus(Request $request, PurchaseInvoice $invoice): RedirectResponse
    {
        Gate::authorize('update', $invoice);

        $request->validate([
            'status' => ['required', 'string', 'in:pending,approved,paid'],
        ]);

        $this->service->updateStatus($invoice, $request->string('status')->toString());

        return redirect()->route('purchasing.invoices.show', $invoice)
            ->with('success', 'Purchase invoice status updated successfully.');
    }
}
