<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Domains\ShopOrder\Actions\ResolveDeliveryReviewAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\RepriceShopInvoiceRequest;
use App\Http\Requests\Web\Purchasing\ReviewDeliveryDiscrepancyRequest;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Services\ShopInvoices\ShopInvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
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
        $finalizedInvoiceScope = fn ($query) => $query
            ->whereNotNull('finalized_at')
            ->orWhereIn('status', ['finalized', 'payment_pending', 'paid']);
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
        $finalizedBillsCount = $baseInvoiceQuery()
            ->where($finalizedInvoiceScope)
            ->count();
        $totalFinalizedAmount = round((float) $baseInvoiceQuery()
            ->where($finalizedInvoiceScope)
            ->sum('final_total'), 2);

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
            'finalizedBillsCount' => $finalizedBillsCount,
            'pendingReviewBillsCount' => max(0, $baseInvoiceQuery()->count() - $finalizedBillsCount),
            'totalFinalizedAmount' => $totalFinalizedAmount,
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

        $isAdmin = (bool) $request->user()?->hasRole('admin');
        $canApprove = $isAdmin || (bool) $request->user()?->hasRole('purchase') || (bool) $request->user()?->can('purchasing.order.approve');
        $isFinalized = $invoice->isFinalized();
        $shopSubmitted = $invoice->order?->shop_checked_at !== null;
        $deliveryReviewState = $invoice->order?->delivery_review_status;
        $canOverride = $isAdmin && ! $isFinalized && $invoice->order !== null;
        $canEdit = $canOverride || (
            $canApprove
            && ! $isFinalized
            && ($invoice->delivery_status === 'awaiting_review' || in_array((string) $invoice->order?->delivery_status, ['pending_approval', 'delivered'], true))
        );
        $canFinalize = $canApprove && ! $isFinalized && $invoice->order !== null;
        $shareUrl = URL::signedRoute('shop-invoices.shared', ['invoice' => $invoice->invoice_number]);
        $pdfUrl = route('purchasing.shop-invoices.pdf', $invoice);

        return view('purchasing.shop-invoices.show', compact(
            'invoice',
            'activities',
            'canApprove',
            'canFinalize',
            'canOverride',
            'canEdit',
            'isFinalized',
            'shopSubmitted',
            'deliveryReviewState',
            'shareUrl',
            'pdfUrl',
        ));
    }

    public function finalizeOnBehalf(ReviewDeliveryDiscrepancyRequest $request, ShopInvoice $invoice): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $invoice->loadMissing(['order.items.product', 'order.invoice.items', 'items']);
        $order = $invoice->order;

        if (! $order instanceof ShopOrder) {
            return back()->withErrors(['invoice' => 'Cannot finalize because this invoice has no linked order.'])->withInput();
        }

        if ($invoice->isFinalized()) {
            return back()->withErrors(['invoice' => 'This shop invoice is already finalized.'])->withInput();
        }

        $validated = $request->validated();
        $approvedDeliveredQuantities = $validated['approved_delivered_qty'] ?? [];
        $reviewNote = $validated['review_note'] ?? null;

        try {
            if ($order->delivery_status === 'in_transit' && in_array($order->delivery_review_status, ['not_started', 'correction_requested'], true)) {
                $this->prepareUnitCosts($order);

                $this->resolveDeliveryReviewAction->submit(
                    $order,
                    $this->reportedQuantitiesForAdminOverride($order, $approvedDeliveredQuantities),
                    (int) $request->user()->id,
                    $reviewNote,
                );

                $order = $order->fresh(['items.product', 'invoice.items']);
            }

            $order = $this->resolveDeliveryReviewAction->approve(
                $order,
                $approvedDeliveredQuantities,
                $validated['item_review_notes'] ?? [],
                $validated['item_inventory_actions'] ?? [],
                $validated['delivery_discrepancy_types'] ?? [],
                $validated['delivery_discrepancy_notes'] ?? [],
                (int) $request->user()->id,
                $reviewNote,
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('purchasing.shop-invoices.show', $order->invoice?->invoice_number ?? $invoice->invoice_number)
            ->with('success', 'Shop invoice finalized by admin review.');
    }

    public function pdf(Request $request, ShopInvoice $invoice): View|Response
    {
        abort_unless($request->user()?->hasRole('purchase') || $request->user()?->hasRole('admin'), 403);

        $invoice->load(['shop', 'order', 'items.product.category', 'items.orderItem', 'paymentApprovedBy', 'discountApprovedBy', 'priceUpdatedBy', 'finalizedBy']);

        $data = $this->resolveFilteredInvoiceItemsAndTotals($invoice, $request);
        $items = $data['items'];
        $subtotal = $data['subtotal'];
        $discountTotal = $data['discountTotal'];
        $finalTotal = $data['finalTotal'];

        if ($request->boolean('download') || $request->query('format') === 'pdf') {
            return Pdf::loadView('purchasing.shop-invoices.dompdf', compact('invoice', 'items', 'subtotal', 'discountTotal', 'finalTotal'))
                ->download("Shop-Invoice-{$invoice->invoice_number}.pdf");
        }

        $shareUrl = URL::signedRoute('shop-invoices.shared', ['invoice' => $invoice->invoice_number]);
        $pdfUrl = route('purchasing.shop-invoices.pdf', array_merge(['invoice' => $invoice], $request->query()));

        return view('purchasing.shop-invoices.pdf', compact('invoice', 'items', 'subtotal', 'discountTotal', 'finalTotal', 'shareUrl', 'pdfUrl'));
    }

    public function shared(Request $request, ShopInvoice $invoice): View
    {
        abort_unless($request->hasValidSignature(), 403, 'Invalid or expired bill link.');

        $invoice->load(['shop', 'order', 'items.product.category', 'items.orderItem', 'paymentApprovedBy', 'discountApprovedBy', 'priceUpdatedBy', 'finalizedBy']);

        $shareUrl = $request->fullUrl();
        $pdfUrl = URL::signedRoute('shop-invoices.shared.pdf', ['invoice' => $invoice->invoice_number]);

        return view('purchasing.shop-invoices.shared', compact('invoice', 'shareUrl', 'pdfUrl'));
    }

    public function sharedPdf(Request $request, ShopInvoice $invoice): View|Response
    {
        abort_unless($request->hasValidSignature(), 403, 'Invalid or expired bill link.');

        $invoice->load(['shop', 'order', 'items.product.category', 'items.orderItem', 'paymentApprovedBy', 'discountApprovedBy', 'priceUpdatedBy', 'finalizedBy']);

        $data = $this->resolveFilteredInvoiceItemsAndTotals($invoice, $request);
        $items = $data['items'];
        $subtotal = $data['subtotal'];
        $discountTotal = $data['discountTotal'];
        $finalTotal = $data['finalTotal'];

        if ($request->boolean('download') || $request->query('format') === 'pdf') {
            return Pdf::loadView('purchasing.shop-invoices.dompdf', compact('invoice', 'items', 'subtotal', 'discountTotal', 'finalTotal'))
                ->download("Shop-Invoice-{$invoice->invoice_number}.pdf");
        }

        $shareUrl = URL::signedRoute('shop-invoices.shared', ['invoice' => $invoice->invoice_number]);
        $pdfUrl = $request->fullUrl();

        return view('purchasing.shop-invoices.pdf', compact('invoice', 'items', 'subtotal', 'discountTotal', 'finalTotal', 'shareUrl', 'pdfUrl'));
    }

    /**
     * @return array{items: Collection<int, ShopInvoiceItem>, subtotal: float, discountTotal: float, finalTotal: float}
     */
    private function resolveFilteredInvoiceItemsAndTotals(ShopInvoice $invoice, Request $request): array
    {
        $categoryIds = null;
        if ($request->has('category_ids')) {
            $raw = $request->get('category_ids');
            if (is_array($raw)) {
                $categoryIds = array_map('intval', $raw);
            } elseif (is_string($raw) && strlen($raw) > 0) {
                $categoryIds = array_map('intval', explode(',', $raw));
            }
        } elseif ($request->filled('category_id')) {
            $categoryIds = [(int) $request->input('category_id')];
        }

        $categories = null;
        if ($request->has('categories')) {
            $rawCats = $request->get('categories');
            if (is_array($rawCats)) {
                $categories = array_map('strval', $rawCats);
            } elseif (is_string($rawCats) && strlen($rawCats) > 0) {
                $categories = array_map('trim', explode(',', $rawCats));
            }
        }

        $items = $invoice->items;
        if (! empty($categoryIds) || ! empty($categories)) {
            $items = $items->filter(function (ShopInvoiceItem $item) use ($categoryIds, $categories): bool {
                if (! empty($categoryIds) && in_array((int) $item->product?->category_id, $categoryIds, true)) {
                    return true;
                }
                if (! empty($categories)) {
                    $catName = strtolower(trim((string) ($item->product?->category?->name ?? '')));
                    foreach ($categories as $cat) {
                        if (strtolower(trim((string) $cat)) === $catName) {
                            return true;
                        }
                    }
                }

                return false;
            })->values();

            $subtotal = round((float) $items->sum(function (ShopInvoiceItem $item): float {
                return (float) ($item->final_line_total ?: $item->line_subtotal);
            }), 2);
            $discountTotal = 0.0;
            $finalTotal = $subtotal;
        } else {
            $subtotal = round((float) $invoice->subtotal, 2);
            $discountTotal = round((float) $invoice->discount_total, 2);
            $finalTotal = round((float) $invoice->final_total, 2);
        }

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'discountTotal' => $discountTotal,
            'finalTotal' => $finalTotal,
        ];
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

    public function updateItem(Request $request, ShopInvoice $invoice, ShopInvoiceItem $item): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->hasRole('purchase') || $request->user()?->hasRole('admin'), 403);

        if ((int) $item->shop_invoice_id !== (int) $invoice->id) {
            abort(404);
        }

        $validated = $request->validate([
            'final_qty' => ['required', 'numeric', 'min:0'],
            'final_price' => ['required', 'numeric', 'min:0.01'],
            'note' => [$invoice->isFinalized() ? 'required' : 'nullable', 'string', 'max:1000'],
        ]);

        $updatedInvoice = $this->shopInvoiceService->updateItemFinalQuantityAndPrice(
            $invoice,
            $item,
            (float) $validated['final_qty'],
            (float) $validated['final_price'],
            (int) $request->user()->id,
            $validated['note'] ?? null,
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'subtotal' => (float) $updatedInvoice->subtotal,
                'discount_total' => (float) $updatedInvoice->discount_total,
                'final_total' => (float) $updatedInvoice->final_total,
            ]);
        }

        return redirect()->route('purchasing.shop-invoices.show', $invoice)
            ->with('success', 'Invoice item updated and bill total recalculated.');
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

    public function reopenForEdit(Request $request, ShopInvoice $invoice): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        try {
            $this->shopInvoiceService->reopenFinalizedInvoice(
                $invoice,
                (int) $request->user()->id,
            );
        } catch (ValidationException $exception) {
            return redirect()->route('purchasing.shop-invoices.show', $invoice)
                ->withErrors($exception->errors());
        }

        return redirect()->route('purchasing.shop-invoices.show', $invoice)
            ->with('success', 'Invoice reopened for editing.');
    }

    /**
     * @param  array<int|string, mixed>  $approvedDeliveredQuantities
     * @return array<int, float>
     */
    private function reportedQuantitiesForAdminOverride(ShopOrder $order, array $approvedDeliveredQuantities): array
    {
        return $order->items
            ->mapWithKeys(function (ShopOrderItem $item) use ($approvedDeliveredQuantities): array {
                $quantity = $approvedDeliveredQuantities[$item->id]
                    ?? $item->shop_reported_received_qty
                    ?? $item->loaded_qty
                    ?? $item->approved_qty
                    ?? 0;

                return [$item->id => round((float) $quantity, 2)];
            })
            ->all();
    }

    private function prepareUnitCosts(ShopOrder $order): void
    {
        $businessDate = $order->business_date?->toDateString() ?? today()->toDateString();

        foreach ($order->items as $item) {
            $item->update([
                'unit_cost' => $this->resolveProductUnitCost((int) $item->product_id, $businessDate),
            ]);
        }
    }

    private function resolveProductUnitCost(int $productId, string $businessDate): float
    {
        $cost = DB::table('stock_batches')
            ->where('product_id', $productId)
            ->whereDate('received_at', $businessDate)
            ->whereNull('deleted_at')
            ->latest('id')
            ->value('cost_per_kg');

        if ($cost !== null) {
            return (float) $cost;
        }

        $cost = DB::table('stock_batches')
            ->where('product_id', $productId)
            ->whereNull('deleted_at')
            ->latest('received_at')
            ->latest('id')
            ->value('cost_per_kg');

        return $cost !== null ? (float) $cost : 0.00;
    }
}
