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
use Spatie\Activitylog\Models\Activity;

class ShopInvoiceController extends Controller
{
    public function __construct(
        private readonly ShopInvoiceService $shopInvoiceService,
        private readonly ResolveDeliveryReviewAction $resolveDeliveryReviewAction,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasRole('purchase') || $request->user()?->hasRole('admin'), 403);

        $selectedDate = $request->filled('date')
            ? Carbon::parse((string) $request->input('date'))->toDateString()
            : today()->toDateString();

        $baseInvoiceQuery = fn () => ShopInvoice::query()
            ->whereDate('business_date', $selectedDate);

        $invoiceQuery = ShopInvoice::query()
            ->with(['shop', 'order.shopCheckedBy', 'order.adminReviewedBy', 'items.orderItem', 'discountApprovedBy', 'finalizedBy'])
            ->latest('business_date')
            ->latest('id')
            ->whereDate('business_date', $selectedDate);

        $invoices = $invoiceQuery->paginate(20);
        $invoicesByShop = $invoices->getCollection()->groupBy(fn (ShopInvoice $invoice): string => (string) ($invoice->shop?->name ?? 'Unknown Shop'));
        $pendingApprovalCount = $baseInvoiceQuery()
            ->whereHas('order', fn ($query) => $query
                ->where('delivery_status', 'pending_approval')
                ->where('delivery_review_status', 'pending'))
            ->count();
        $shopNotesCount = $baseInvoiceQuery()
            ->whereHas('items.orderItem', fn ($query) => $query
                ->whereNotNull('shop_verification_note')
                ->where('shop_verification_note', '!=', ''))
            ->count();
        $varianceCount = $baseInvoiceQuery()
            ->where(function ($query): void {
                $query->where('shortage_total', '>', 0)
                    ->orWhere('excess_total', '>', 0)
                    ->orWhereHas('items.orderItem', fn ($itemQuery) => $itemQuery
                        ->where(function ($varianceQuery): void {
                            $varianceQuery->where('shop_reported_missing_qty', '>', 0)
                                ->orWhere('shop_reported_excess_qty', '>', 0);
                        }));
            })
            ->count();
        $paymentPendingCount = $baseInvoiceQuery()
            ->where('balance_amount', '>', 0)
            ->count();

        return view('purchasing.shop-invoices.index', [
            'invoices' => $invoices,
            'invoicesByShop' => $invoicesByShop,
            'selectedDate' => $selectedDate,
            'todayDate' => today()->toDateString(),
            'allInvoicesCount' => $baseInvoiceQuery()->count(),
            'pendingApprovalCount' => $pendingApprovalCount,
            'shopNotesCount' => $shopNotesCount,
            'varianceCount' => $varianceCount,
            'paymentPendingCount' => $paymentPendingCount,
            'deliveryReviewCount' => $pendingApprovalCount,
        ]);
    }

    public function show(Request $request, ShopInvoice $invoice): View
    {
        abort_unless($request->user()?->hasRole('purchase') || $request->user()?->hasRole('admin'), 403);

        $invoice->load(['shop', 'order.shopCheckedBy', 'order.adminReviewedBy', 'items.product', 'items.orderItem', 'paymentApprovedBy', 'discountApprovedBy', 'priceUpdatedBy', 'finalizedBy']);
        $invoice->load(['paymentRequests.requestedBy', 'paymentRequests.reviewedBy']);
        $activities = Activity::query()
            ->with('causer')
            ->where('subject_type', ShopInvoice::class)
            ->where('subject_id', $invoice->id)
            ->latest('created_at')
            ->latest('id')
            ->get();

        return view('purchasing.shop-invoices.show', compact('invoice', 'activities'));
    }

    public function pdf(Request $request, ShopInvoice $invoice): View
    {
        abort_unless($request->user()?->hasRole('purchase') || $request->user()?->hasRole('admin'), 403);

        $invoice->load(['shop', 'order', 'items.product', 'items.orderItem', 'paymentApprovedBy', 'discountApprovedBy', 'priceUpdatedBy', 'finalizedBy']);

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
