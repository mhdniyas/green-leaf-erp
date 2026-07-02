<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\RepriceShopInvoiceRequest;
use App\Http\Requests\Web\Purchasing\ApproveShopInvoicePaymentRequest;
use App\Http\Requests\Web\Purchasing\ReviewShopInvoicePaymentRequest;
use App\Models\ShopInvoice;
use App\Models\ShopInvoicePaymentRequest;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShopInvoiceController extends Controller
{
    public function __construct(
        private readonly ShopInvoiceService $shopInvoiceService,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasRole('purchase') || $request->user()?->hasRole('admin'), 403);

        $tab = (string) $request->input('tab', 'all');

        $invoiceQuery = ShopInvoice::query()
            ->with(['shop', 'order'])
            ->latest('business_date')
            ->latest('id');

        if ($tab === 'delivery-review') {
            $invoiceQuery->where('delivery_status', 'received_with_discrepancy');
        }

        $invoices = $invoiceQuery->paginate(20);

        return view('purchasing.shop-invoices.index', [
            'invoices' => $invoices,
            'tab' => $tab,
            'allInvoicesCount' => ShopInvoice::query()->count(),
            'deliveryReviewCount' => ShopInvoice::query()
                ->where('delivery_status', 'received_with_discrepancy')
                ->count(),
        ]);
    }

    public function show(Request $request, ShopInvoice $invoice): View
    {
        abort_unless($request->user()?->hasRole('purchase') || $request->user()?->hasRole('admin'), 403);

        $invoice->load(['shop', 'order', 'items.product', 'items.orderItem', 'paymentApprovedBy', 'priceUpdatedBy']);
        $invoice->load(['paymentRequests.requestedBy', 'paymentRequests.reviewedBy']);

        return view('purchasing.shop-invoices.show', compact('invoice'));
    }

    public function pdf(Request $request, ShopInvoice $invoice): View
    {
        abort_unless($request->user()?->hasRole('purchase') || $request->user()?->hasRole('admin'), 403);

        $invoice->load(['shop', 'order', 'items.product', 'items.orderItem', 'paymentApprovedBy', 'priceUpdatedBy']);

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

    public function reviewPaymentRequest(ReviewShopInvoicePaymentRequest $request, ShopInvoicePaymentRequest $paymentRequest): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('purchase') || $request->user()?->hasRole('admin'), 403);

        try {
            $paymentRequest = $this->shopInvoiceService->reviewPaymentRequest(
                $paymentRequest,
                $request->validated('decision'),
                (int) $request->user()->id,
                $request->validated('admin_note'),
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('purchasing.shop-invoices.show', $paymentRequest->invoice)
            ->with(
                $paymentRequest->status === 'approved' ? 'success' : 'warning',
                $paymentRequest->status === 'approved'
                    ? 'Shop payment request approved and added to sales collections.'
                    : 'Shop payment request rejected.'
            );
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
