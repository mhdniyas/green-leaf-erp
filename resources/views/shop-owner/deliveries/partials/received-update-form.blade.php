@php
    $eligibility = is_array($deliveryEligibility ?? null)
        ? $deliveryEligibility
        : ['allowed' => true, 'message' => 'Delivery verification is available.'];
    $eligibilityAllowed = (bool) ($eligibility['allowed'] ?? false);
    $isPendingApproval = $order->delivery_status === 'pending_approval';
    $isEditable = $eligibilityAllowed && ! $order->is_delivered && ! $isPendingApproval;
    $sortedItems = $order->items->sortBy(
        fn ($item) => \App\Models\Product::sortableSku((string) ($item->product?->sku ?? ''))
    );
    $invoice = $order->invoice;
    $invoiceItemsByProductId = $invoice?->items?->keyBy('product_id') ?? collect();
    $fulfilledItems = $sortedItems
        ->filter(fn ($item) => $item->sorting_status === 'loaded' && (float) ($item->loaded_qty ?? 0) > 0)
        ->groupBy('product_id')
        ->map(function ($group) {
            $loadedRow = $group->first(fn ($i) => $i->sorting_status === 'loaded' || (float) ($i->loaded_qty ?? 0) > 0);
            return $loadedRow ?: $group->first();
        })
        ->values();
    $notAvailableItems = $sortedItems->filter(fn ($item) => $item->sorting_status === 'not_available');
    $verifiableItems = $fulfilledItems;
    $verifiedCount = $verifiableItems->whereNotNull('shop_verified_at')->count();
    $totalVerifiableCount = $verifiableItems->count();
    $allItemsVerified = $totalVerifiableCount > 0 && $verifiedCount === $totalVerifiableCount;
    $canSubmitVerification = $isEditable && $totalVerifiableCount > 0 && ! $allItemsVerified;

    // Recalculate invoice total from delivered quantities (DB may have wrong values)
    $computedInvoiceTotal = (float) $invoiceItemsByProductId->sum(function ($invoiceItem) {
        $qty = (float) ($invoiceItem->delivered_price_quantity ?? $invoiceItem->price_quantity ?? $invoiceItem->delivered_qty ?? 0);
        $rate = (float) ($invoiceItem->unit_price ?? 0);
        return $qty * $rate;
    });
    $invoiceDiscountTotal = (float) ($invoice?->discount_total ?? 0);
    $invoiceNetTotal = max(0.0, $computedInvoiceTotal - $invoiceDiscountTotal);

    $bottomTitle = match (true) {
        $isPendingApproval => 'Submitted For Admin Review',
        $order->is_delivered => 'Delivery Completed',
        default => 'Confirm Delivery Verification',
    };
    $bottomMessage = match (true) {
        $isPendingApproval => 'Your delivery verification is submitted. Admin review is pending.',
        $order->is_delivered => 'This delivery has been verified and confirmed.',
        default => 'Confirm received quantities and add any optional notes, then submit.',
    };
@endphp

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm sm:rounded-2xl">
    <div
        id="shop-delivery-item-verification"
        data-csrf-token="{{ csrf_token() }}"
        data-complete-title="Submitted For Admin Review"
        data-complete-message="All products are submitted. Admin recheck is required before final invoice totals are confirmed."
    >
        <div class="relative mx-auto max-w-[38rem] bg-white px-2 py-3 text-slate-950 sm:min-h-[36rem] sm:px-6 sm:py-7">
            <header class="border-b border-dashed border-slate-400 pb-2 text-center sm:pb-3">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1">
                        <h3 class="text-base font-black uppercase tracking-wide text-slate-950 sm:text-xl">Delivery Verification</h3>
                        <p class="mt-1 text-sm font-black uppercase leading-tight text-slate-950 sm:mt-2 sm:text-base">{{ $order->shop?->name }}</p>
                        <p class="mt-0.5 text-[11px] font-semibold leading-tight text-slate-700">{{ $invoice?->invoice_number ?? $order->order_number }} · {{ $order->business_date?->format('d M Y') }}</p>
                    </div>
                    <a href="{{ route('shop-owner.deliveries.pdf', $order->order_number) }}" target="_blank" class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[9px] font-black uppercase tracking-[0.12em] text-slate-700 transition hover:border-emerald-200 hover:text-emerald-700 sm:px-4 sm:py-2 sm:text-xs">
                        Print / PDF
                    </a>
                </div>
            </header>

            <div class="grid grid-cols-2 gap-2 border-b border-dashed border-slate-400 py-2 text-[10px] font-bold leading-tight text-slate-800 sm:gap-3 sm:py-3 sm:text-[11px]">
                <div class="min-w-0">
                    <p class="text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[10px] sm:tracking-[0.12em]">Delivery Ref</p>
                    <p class="mt-0.5 break-all font-black text-slate-950 sm:mt-1">{{ $order->order_number }}</p>
                    <p class="mt-0.5 sm:mt-1">Items: {{ $totalVerifiableCount }}</p>
                </div>
                <div class="text-right">
                    <p>Invoice Total</p>
                    <p class="mt-0.5 text-xs font-black text-slate-950 sm:mt-1 sm:text-sm">Rs. {{ number_format($computedInvoiceTotal, 2) }}</p>
                    @if ($invoiceDiscountTotal > 0)
                        <p class="mt-0.5 text-[10px] font-bold text-rose-700 sm:text-[11px]">Discount: -Rs. {{ number_format($invoiceDiscountTotal, 2) }}</p>
                        <p class="mt-0.5 text-[10px] font-black text-emerald-700 sm:text-[11px]">After Discount: Rs. {{ number_format($invoiceNetTotal, 2) }}</p>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto border-b border-dashed border-slate-400 py-2 sm:py-3">
                <table class="w-full table-fixed text-left text-[9px] sm:text-[11px]">
                    <thead class="border-b border-dashed border-slate-400 text-[8px] font-black uppercase text-slate-950 sm:text-[10px]">
                        <tr>
                            <th class="w-5 py-0.5 pr-0.5 sm:w-7 sm:py-1 sm:pr-1">SN</th>
                            <th class="py-0.5 pr-1 sm:py-1 sm:pr-2">Item</th>
                            <th class="w-14 py-0.5 pr-0.5 text-right sm:w-16 sm:py-1 sm:pr-1">Delivered</th>
                            <th class="w-14 py-0.5 pr-0.5 text-right sm:w-16 sm:py-1 sm:pr-1">Rate</th>
                            <th class="w-16 py-0.5 text-right sm:w-20 sm:py-1">Amt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($fulfilledItems as $item)
                            @php
                                $invoiceItem = $invoiceItemsByProductId->get($item->product_id);
                                
                                // Use invoice's pricing quantity and unit for display
                                if ($invoiceItem) {
                                    $approvedQty = (float) ($invoiceItem->delivered_price_quantity ?? $invoiceItem->price_quantity ?? $invoiceItem->delivered_qty ?? 0);
                                    $displayUnitLabel = strtoupper($invoiceItem->price_unit ?: $item->product->unit);
                                    $unitRate = $invoiceItem->unit_price;
                                    // Calculate line total (DB value may be wrong)
                                    $lineTotal = $approvedQty * $unitRate;
                                } else {
                                    // Fallback if no invoice item
                                    $approvedQty = (float) ($item->loaded_qty ?? $item->approved_qty ?? 0);
                                    $displayUnitLabel = strtoupper($item->product->unit ?? 'KG');
                                    $unitRate = null;
                                    $lineTotal = null;
                                }

                                $isItemVerified = $item->shop_verified_at !== null;
                            @endphp
                            <tr
                                class="shop-item-row align-top"
                                data-item-id="{{ $item->id }}"
                                data-verify-url="{{ route('shop-owner.deliveries.items.verify', [$order->order_number, $item]) }}"
                                data-approved-qty="{{ $approvedQty }}"
                                data-unit="{{ $displayUnitLabel }}"
                                data-verified="{{ $isItemVerified ? 'true' : 'false' }}"
                            >
                                <td class="py-1.5 pr-0.5 font-bold sm:py-2.5 sm:pr-1">{{ $loop->iteration }}</td>
                                <td class="py-1.5 pr-1 sm:py-2.5 sm:pr-2">
                                    <p class="font-black leading-tight text-slate-950">
                                        <span class="inline-block rounded bg-slate-100 px-1 py-0.5 text-[9px] font-black text-slate-700 mr-1">#{{ $item->product->sku ?: $item->product_id }}</span>
                                        {{ $item->product->name }}
                                    </p>
                                    <p class="shop-item-error mt-1 hidden rounded-md bg-red-50 px-2 py-1 text-[10px] font-bold text-red-700"></p>
                                </td>
                                <td class="py-1.5 pr-0.5 text-right font-bold text-slate-900 sm:py-2.5 sm:pr-1">
                                    {{ number_format($approvedQty, 2) }} {{ $displayUnitLabel }}
                                </td>
                                <td class="py-1.5 pr-0.5 text-right font-bold text-slate-700 sm:py-2.5 sm:pr-1">
                                    @if($unitRate !== null)
                                        Rs. {{ number_format((float) $unitRate, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-1.5 text-right font-black text-slate-950 sm:py-2">
                                    @if($lineTotal !== null)
                                        Rs. {{ number_format((float) $lineTotal, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-xs font-bold text-slate-500">No delivered products to verify.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="ml-auto w-full max-w-full border-b border-dashed border-slate-400 py-2 text-[10px] font-bold text-slate-800 sm:max-w-[20rem] sm:py-3 sm:text-[11px]">
                <div class="flex items-center justify-between font-black text-slate-950 text-xs sm:text-sm">
                    <span>Invoice Total</span>
                    <span>Rs. {{ number_format($computedInvoiceTotal, 2) }}</span>
                </div>
                @if ($invoiceDiscountTotal > 0)
                    <div class="mt-1 flex items-center justify-between text-[10px] font-bold text-rose-700 sm:text-[11px]">
                        <span>Discount</span>
                        <span>- Rs. {{ number_format($invoiceDiscountTotal, 2) }}</span>
                    </div>
                    <div class="mt-0.5 flex items-center justify-between text-[11px] font-black text-emerald-700 sm:text-xs">
                        <span>Net Total</span>
                        <span>Rs. {{ number_format($invoiceNetTotal, 2) }}</span>
                    </div>
                @endif
            </div>
        </div>

        <div class="border-t border-slate-200/80 bg-slate-50/50 px-2.5 py-2.5 sm:px-6 sm:py-4">
            <div id="shop-delivery-progress-panel" class="rounded-xl border border-slate-200 bg-white p-3.5 sm:rounded-[1.5rem] sm:p-5 space-y-3.5 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p id="shop-delivery-progress-title" class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500 sm:text-[11px]">{{ $bottomTitle }}</p>
                        <p id="shop-delivery-progress-message" class="mt-0.5 text-xs font-semibold leading-5 text-slate-700 sm:text-sm">{{ $bottomMessage }}</p>
                    </div>
                    <button
                        type="button"
                        id="shop-delivery-submit-all"
                        class="shrink-0 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-3 text-xs font-black uppercase tracking-[0.12em] transition-all shadow-md active:scale-95 cursor-pointer border-none disabled:cursor-not-allowed disabled:opacity-60"
                        @disabled(! $canSubmitVerification)
                    >
                        Submit Delivery Verification
                    </button>
                </div>

                @if(! $canSubmitVerification)
                    <p class="text-[11px] font-semibold text-amber-700">
                        @if(! $eligibilityAllowed)
                            {{ (string) ($eligibility['message'] ?? 'Delivery verification is currently unavailable.') }}
                        @elseif($totalVerifiableCount === 0)
                            No delivered items are available to submit.
                        @elseif($allItemsVerified)
                            All delivered items are already submitted for verification.
                        @else
                            Delivery verification is currently unavailable.
                        @endif
                    </p>
                @endif

                @if($isEditable)
                    <div class="border-t border-slate-100 pt-3">
                        <label for="shop-delivery-note" class="block text-[9px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">
                            Delivery Note / Remarks (Optional)
                        </label>
                        <input
                            type="text"
                            id="shop-delivery-note"
                            name="delivery_note"
                            placeholder="Enter any optional delivery remarks..."
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-950 placeholder-slate-400 outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-all shadow-inner"
                        >
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const wrapper = document.getElementById('shop-delivery-item-verification');
        const rows = document.querySelectorAll('.shop-item-row');
        const csrfToken = wrapper?.dataset.csrfToken;
        const progressTitle = document.getElementById('shop-delivery-progress-title');
        const progressMessage = document.getElementById('shop-delivery-progress-message');
        const progressCount = document.getElementById('shop-delivery-progress-count');
        const submitAllButton = document.getElementById('shop-delivery-submit-all');

        function setRowError(row, message) {
            const error = row.querySelector('.shop-item-error');
            if (error) {
                error.textContent = message || '';
                error.classList.toggle('hidden', !message);
            }
        }

        submitAllButton?.addEventListener('click', async function () {
            if (submitAllButton.disabled || !csrfToken) {
                return;
            }

            const pendingRows = Array.from(rows).filter((row) => row.dataset.verified !== 'true');
            if (pendingRows.length === 0) {
                return;
            }

            const deliveryNote = document.getElementById('shop-delivery-note')?.value || '';

            submitAllButton.disabled = true;
            submitAllButton.textContent = 'Submitting...';

            for (const row of pendingRows) {
                const approvedQty = row.dataset.approvedQty || '0.00';
                setRowError(row, null);

                try {
                    const response = await fetch(row.dataset.verifyUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            received_qty: approvedQty,
                            note: deliveryNote,
                        }),
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        throw new Error(result.message || 'Unable to submit this product.');
                    }

                    row.dataset.verified = 'true';
                    if (progressCount) progressCount.textContent = result.progress.label;
                    if (progressTitle) progressTitle.textContent = result.order_status_label;
                    if (progressMessage) progressMessage.textContent = result.message;

                    if (result.order_submitted) {
                        if (progressTitle) progressTitle.textContent = wrapper.dataset.completeTitle || result.order_status_label;
                        if (progressMessage) progressMessage.textContent = wrapper.dataset.completeMessage || result.message;
                        submitAllButton.textContent = 'Submitted';
                        return;
                    }
                } catch (error) {
                    setRowError(row, error.message || 'Unable to submit this product.');
                    submitAllButton.disabled = false;
                    submitAllButton.textContent = 'Submit Delivery Verification';
                    return;
                }
            }

            submitAllButton.disabled = false;
            submitAllButton.textContent = 'Submit Delivery Verification';
        });
    });
</script>
