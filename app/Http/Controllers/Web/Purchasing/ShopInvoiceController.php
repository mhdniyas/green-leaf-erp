<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\RepriceShopInvoiceRequest;
use App\Http\Requests\Web\Purchasing\ApproveShopInvoicePaymentRequest;
use App\Models\ShopInvoice;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopInvoiceController extends Controller
{
    public function __construct(
        private readonly ShopInvoiceService $shopInvoiceService,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasRole('purchase') || $request->user()?->hasRole('admin'), 403);

        $invoices = ShopInvoice::query()
            ->with(['shop', 'order'])
            ->latest('business_date')
            ->latest('id')
            ->paginate(20);

        return view('purchasing.shop-invoices.index', compact('invoices'));
    }

    public function show(Request $request, ShopInvoice $invoice): View
    {
        abort_unless($request->user()?->hasRole('purchase') || $request->user()?->hasRole('admin'), 403);

        $invoice->load(['shop', 'order', 'items.product', 'paymentApprovedBy', 'priceUpdatedBy']);

        return view('purchasing.shop-invoices.show', compact('invoice'));
    }

    public function pdf(Request $request, ShopInvoice $invoice): View
    {
        abort_unless($request->user()?->hasRole('purchase') || $request->user()?->hasRole('admin'), 403);

        $invoice->load(['shop', 'order', 'items.product', 'paymentApprovedBy', 'priceUpdatedBy']);

        return view('purchasing.shop-invoices.pdf', compact('invoice'));
    }

    public function approvePayment(ApproveShopInvoicePaymentRequest $request, ShopInvoice $invoice): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('purchase') || $request->user()?->hasRole('admin'), 403);

        $this->shopInvoiceService->approvePayment(
            $invoice,
            $request->validated(),
            (int) $request->user()->id,
        );

        return redirect()->route('purchasing.shop-invoices.show', $invoice)
            ->with('success', 'Daily invoice payment approval updated.');
    }

    public function reprice(RepriceShopInvoiceRequest $request, ShopInvoice $invoice): RedirectResponse
    {
        $this->shopInvoiceService->repriceInvoice(
            $invoice,
            (int) $request->user()->id,
            $request->validated('reason'),
        );

        return redirect()->route('purchasing.shop-invoices.show', $invoice)
            ->with('success', 'Daily invoice prices refreshed.');
    }
}
