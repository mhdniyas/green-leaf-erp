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
use Illuminate\Support\Carbon;
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

        $date = Carbon::parse($request->input('date', now()->format('Y-m-d')));

        $invoices = PurchaseInvoice::query()
            ->with(['goodsReceived', 'supplier', 'purchaserCart'])
            ->where(function ($query) use ($date): void {
                $query
                    ->whereNull('purchaser_cart_id')
                    ->orWhereHas('purchaserCart', function ($purchaserCartQuery) use ($date): void {
                        $purchaserCartQuery->whereDate('business_date', $date);
                    });
            })
            ->orderByDesc('id')
            ->paginate(20);

        return view('purchasing.invoices.index', [
            'date' => $date->format('Y-m-d'),
            'invoices' => $invoices,
            'financeAudience' => 'manager',
            'canManageSuppliers' => $request->user()->hasRole('admin') || $request->user()->hasRole('purchase') || $request->user()->can('purchasing.supplier.update'),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        Gate::authorize('create', PurchaseInvoice::class);

        $goodsReceivedReference = trim((string) $request->string('goods_received'));

        if ($goodsReceivedReference === '' && $request->filled('goods_received_id')) {
            $legacyGoodsReceivedId = $request->integer('goods_received_id');

            if ($legacyGoodsReceivedId > 0) {
                /** @var GoodsReceived $legacyGrn */
                $legacyGrn = GoodsReceived::findOrFail($legacyGoodsReceivedId);

                return redirect()->route('purchasing.invoices.create', [
                    'goods_received' => $legacyGrn,
                ]);
            }
        }

        if ($goodsReceivedReference === '') {
            return redirect()->route('purchasing.grns.index')
                ->with('error', 'Please select a Goods Received Note to create an invoice.');
        }

        /** @var GoodsReceived $grn */
        $grn = GoodsReceived::query()
            ->where((new GoodsReceived)->getRouteKeyName(), $goodsReceivedReference)
            ->when(
                GoodsReceived::hasPublicUuidColumn(),
                fn ($query) => $query->orWhere('grn_number', $goodsReceivedReference)
            )
            ->firstOrFail();
        $grn->load(['purchaseOrder.supplier', 'items.product']);

        // Check if there is an existing invoice for this GRN
        $existingInvoice = PurchaseInvoice::where('goods_received_id', $grn->id)->first();
        if ($existingInvoice) {
            return redirect()->route('purchasing.invoices.show', $existingInvoice)
                ->with('warning', 'An invoice has already been created for this Goods Received Note.');
        }

        $suggestedInvoiceNumber = 'PINV-'.now()->format('Ymd-Hisv');

        return view('purchase-manager.invoices.create', compact('grn', 'suggestedInvoiceNumber'));
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

        $invoice->load([
            'supplier',
            'purchaserCart.items.product',
            'goodsReceived.items.product',
            'goodsReceived.purchaseOrder',
            'purchaserCart',
        ]);

        return view('purchasing.invoices.show', [
            'invoice' => $invoice,
            'paymentUpdateRouteName' => 'purchasing.invoices.update-payment',
            'billPdfRouteName' => 'purchasing.invoices.pdf',
            'backRouteName' => 'purchasing.invoices.index',
            'backRouteParameters' => ['date' => $invoice->purchaserCart?->business_date?->format('Y-m-d')],
            'financeAudience' => 'manager',
        ]);
    }

    public function pdf(PurchaseInvoice $invoice): View
    {
        Gate::authorize('view', $invoice);

        $invoice->load([
            'supplier',
            'purchaserCart.items.product',
            'goodsReceived.items.product',
            'goodsReceived.purchaseOrder',
        ]);

        return view('purchasing.invoices.pdf', compact('invoice'));
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

    public function updatePayment(Request $request, PurchaseInvoice $invoice): RedirectResponse
    {
        Gate::authorize('update', $invoice);

        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'in:Cash,Online,Credit'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_note' => ['nullable', 'string', 'max:1000'],
            'payment_details' => ['nullable', 'string', 'max:1000'],
        ]);

        $updatedInvoice = $this->service->updatePayment($invoice, [
            'payment_method' => $validated['payment_method'],
            'paid_amount' => (float) $validated['paid_amount'],
            'payment_note' => $validated['payment_note'] ?? null,
            'payment_details' => $validated['payment_details'] ?? null,
        ]);

        $remainingBalance = max(0, round((float) $updatedInvoice->amount - (float) $updatedInvoice->paid_amount, 2));
        $message = $remainingBalance > 0 || $updatedInvoice->payment_method === 'Credit'
            ? 'Payment updated. Invoice is not complete yet.'
            : 'Payment completed successfully.';

        return redirect()->back()->with('success', $message);
    }
}
