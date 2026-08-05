<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Domains\ShopOrder\Actions\ResolveDeliveryReviewAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\RepriceShopInvoiceRequest;
use App\Models\ShopInvoice;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShopInvoiceController extends Controller
{
    public function __construct(
        private readonly ShopInvoiceService $shopInvoiceService,
        private readonly ResolveDeliveryReviewAction $resolveDeliveryReviewAction,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasRole('purchase') || $request->user()?->hasRole('admin'), 403);

        $tab = (string) $request->input('tab', 'all');
        $selectedDate = $request->filled('date')
            ? Carbon::parse((string) $request->input('date'))->toDateString()
            : null;

        $invoiceQuery = ShopInvoice::query()
            ->with(['shop', 'order'])
            ->latest('business_date')
            ->latest('id');

        if ($selectedDate !== null) {
            $invoiceQuery->whereDate('business_date', $selectedDate);
        }

        if ($tab === 'delivery-review') {
            $invoiceQuery->whereHas('order', fn ($query) => $query
                ->where('delivery_status', 'pending_approval')
                ->where('delivery_review_status', 'pending'));
        }

        $invoices = $invoiceQuery->paginate(20);

        return view('purchasing.shop-invoices.index', [
            'invoices' => $invoices,
            'tab' => $tab,
            'selectedDate' => $selectedDate,
            'todayDate' => today()->toDateString(),
            'allInvoicesCount' => ShopInvoice::query()->count(),
            'deliveryReviewCount' => ShopInvoice::query()
                ->whereHas('order', fn ($query) => $query
                    ->where('delivery_status', 'pending_approval')
                    ->where('delivery_review_status', 'pending'))
                ->count(),
        ]);
    }

    public function show(Request $request, ShopInvoice $invoice): View
    {
        abort_unless($request->user()?->hasRole('purchase') || $request->user()?->hasRole('admin'), 403);

        $invoice->load(['shop', 'order', 'items.product', 'items.orderItem', 'paymentApprovedBy', 'discountApprovedBy', 'priceUpdatedBy']);
        $invoice->load(['paymentRequests.requestedBy', 'paymentRequests.reviewedBy']);

        return view('purchasing.shop-invoices.show', compact('invoice'));
    }

    public function pdf(Request $request, ShopInvoice $invoice): View
    {
        abort_unless($request->user()?->hasRole('purchase') || $request->user()?->hasRole('admin'), 403);

        $invoice->load(['shop', 'order', 'items.product', 'items.orderItem', 'paymentApprovedBy', 'discountApprovedBy', 'priceUpdatedBy']);

        return view('purchasing.shop-invoices.pdf', compact('invoice'));
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

    public function revertApproval(Request $request, ShopInvoice $invoice): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $invoice->loadMissing('order');

        if (! $invoice->order) {
            return redirect()->route('purchasing.shop-invoices.show', $invoice)
                ->withErrors(['invoice' => 'Cannot revert approval because this invoice has no linked order.']);
        }

        try {
            $this->resolveDeliveryReviewAction->revertApprovalForAdminEdit(
                $invoice->order,
                (int) $request->user()->id,
                $validated['review_note'] ?? null,
            );
        } catch (ValidationException $exception) {
            return redirect()->route('purchasing.shop-invoices.show', $invoice)
                ->withErrors($exception->errors());
        }

        return redirect()->route('purchasing.shop-invoices.show', $invoice)
            ->with('success', 'Delivery approval was reverted. You can now edit and approve the review again.');
    }
}
