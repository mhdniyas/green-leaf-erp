@php
    $isPendingApproval = $order->delivery_status === 'pending_approval';
    $deliveryEligibility = $deliveryEligibility ?? ['allowed' => true, 'message' => null];
    $deliveryPriceReadiness = $deliveryPriceReadiness ?? ['published' => [], 'unpublished' => []];
    $isEditable = $order->is_allocation_completed && ! $order->is_delivered && ! $isPendingApproval && $deliveryEligibility['allowed'];
    $sortedItems = $order->items->sortBy(
        fn ($item) => \App\Models\Product::sortableSku((string) ($item->product?->sku ?? ''))
    );
    $invoice = $order->invoice;
    $invoiceItemsByProductId = $invoice?->items?->keyBy('product_id') ?? collect();
    $priceRowsByProductId = collect($deliveryPriceReadiness['published'] ?? [])
        ->merge($deliveryPriceReadiness['unpublished'] ?? [])
        ->keyBy('product_id');
    $verifiableItems = $sortedItems->filter(fn ($item) => (float) ($item->approved_qty ?? 0) > 0);
    $verifiedCount = $verifiableItems->whereNotNull('shop_verified_at')->count();
    $totalVerifiableCount = $verifiableItems->count();
    $progressLabel = $totalVerifiableCount > 0
        ? "{$verifiedCount} / {$totalVerifiableCount} products submitted"
        : 'No products to verify';
    $bottomTitle = match (true) {
        $isPendingApproval => 'Submitted For Admin Review',
        ! $deliveryEligibility['allowed'] => 'Delivery Pending',
        default => 'Submit Each Product',
    };
    $bottomMessage = match (true) {
        $isPendingApproval => 'Your received quantities are submitted. Admin recheck is required before final invoice totals are confirmed.',
        ! $deliveryEligibility['allowed'] => $deliveryEligibility['message'],
        default => 'Edit a received quantity, then use the tick button on that product row. Admin review starts after every product is submitted.',
    };
@endphp

<section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
    <div
        id="shop-delivery-item-verification"
        data-csrf-token="{{ csrf_token() }}"
        data-complete-title="Submitted For Admin Review"
        data-complete-message="All products are submitted. Admin recheck is required before final invoice totals are confirmed."
    >
        <div class="border-b border-slate-100 bg-emerald-50 px-4 py-4 sm:px-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Approved Invoice Pricing</p>
                    <h3 class="mt-1 text-base font-black text-slate-950 sm:text-lg">
                        {{ $invoice?->invoice_number ?? $order->order_number }} · {{ $order->business_date?->format('d/m/Y') }}
                    </h3>
                </div>
                <div class="rounded-2xl bg-white px-4 py-2 sm:text-right">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Net Total</p>
                    <p class="mt-1 text-lg font-black tabular-nums text-slate-950">Rs. {{ number_format((float) ($invoice?->final_total ?? 0), 2) }}</p>
                </div>
            </div>
        </div>

        <div class="hidden border-b border-slate-100 bg-white px-4 py-3 text-[10px] font-black uppercase tracking-[0.14em] text-slate-500 md:grid md:grid-cols-[minmax(0,1fr)_6rem_6rem_7rem_8rem_8rem] md:gap-3">
            <span>Item</span>
            <span class="text-right">Qty</span>
            <span class="text-right">Rate</span>
            <span class="text-right">Total</span>
            <span class="text-right">Received</span>
            <span class="text-right">Submit</span>
        </div>

        <div class="divide-y divide-slate-100">
            @foreach ($sortedItems as $item)
                @php
                    $invoiceItem = $invoiceItemsByProductId->get($item->product_id);
                    $priceRow = $priceRowsByProductId->get($item->product_id);
                    $approvedQty = (float) ($item->loaded_qty ?? $item->approved_qty ?? $invoiceItem?->approved_qty ?? 0);
                    $unitRate = (float) ($invoiceItem?->unit_price ?? $priceRow['unit_price'] ?? 0);
                    $lineTotal = round($approvedQty * $unitRate, 2);
                    $receivedQty = $isPendingApproval
                        ? (float) ($item->shop_reported_received_qty ?? 0)
                        : (float) ($item->delivered_qty ?? $approvedQty);
                    $isItemVerified = $item->shop_verified_at !== null;
                    $itemShortQty = (float) ($item->shop_reported_missing_qty ?? 0);
                    $itemExcessQty = (float) ($item->shop_reported_excess_qty ?? 0);
                    $statusLabel = match (true) {
                        ! $deliveryEligibility['allowed'] => $priceRow['status_label'] ?? ($invoiceItem ? 'Pending' : 'Not Updated'),
                        $isItemVerified && $itemExcessQty > 0 => 'Excess Submitted',
                        $isItemVerified && $itemShortQty > 0 => 'Short Submitted',
                        $isItemVerified => 'Submitted',
                        default => 'Pending',
                    };
                    $statusTone = match (true) {
                        ! $deliveryEligibility['allowed'] => $priceRow['status_tone'] ?? ($invoiceItem ? 'info' : 'warning'),
                        $isItemVerified && $itemExcessQty > 0 => 'info',
                        $isItemVerified && $itemShortQty > 0 => 'warning',
                        $isItemVerified => 'success',
                        default => 'neutral',
                    };
                @endphp

                <article
                    class="shop-item-row px-4 py-3 transition sm:px-6"
                    data-item-id="{{ $item->id }}"
                    data-verify-url="{{ route('shop-owner.deliveries.items.verify', [$order->order_number, $item]) }}"
                    data-approved-qty="{{ $approvedQty }}"
                    data-unit="{{ $item->unit }}"
                >
                    <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_6rem_6rem_7rem_8rem_8rem] md:items-center">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-black text-slate-950">{{ $item->product->name }}</p>
                            <p class="mt-0.5 truncate text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">
                                Code {{ $item->product->sku }} · {{ strtoupper($item->unit) }}
                            </p>
                        </div>

                        <div class="grid grid-cols-3 gap-2 md:contents">
                            <div class="rounded-2xl bg-slate-50 px-3 py-2 md:bg-transparent md:p-0 md:text-right">
                                <p class="text-[9px] font-black uppercase tracking-[0.12em] text-slate-500 md:hidden">Qty</p>
                                <p class="mt-1 text-sm font-black tabular-nums text-slate-800 md:mt-0">{{ number_format($approvedQty, 2) }}</p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 px-3 py-2 md:bg-transparent md:p-0 md:text-right">
                                <p class="text-[9px] font-black uppercase tracking-[0.12em] text-slate-500 md:hidden">Rate</p>
                                <p class="mt-1 text-sm font-black tabular-nums text-slate-950 md:mt-0">Rs. {{ number_format($unitRate, 2) }}</p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 px-3 py-2 md:bg-transparent md:p-0 md:text-right">
                                <p class="text-[9px] font-black uppercase tracking-[0.12em] text-slate-500 md:hidden">Total</p>
                                <p class="mt-1 text-sm font-black tabular-nums text-slate-950 md:mt-0">Rs. {{ number_format($lineTotal, 2) }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 md:block md:text-right">
                            <div class="rounded-2xl border border-slate-200 bg-white px-3 py-2">
                                <label class="block text-[9px] font-black uppercase tracking-[0.12em] text-slate-500 md:hidden">Received</label>
                                <div class="flex items-center">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="delivered_qty[{{ $item->id }}]"
                                        value="{{ number_format($receivedQty, 2, '.', '') }}"
                                        @disabled(! $isEditable || $isItemVerified)
                                        class="shop-delivered-qty-input w-full min-w-0 border-0 bg-transparent py-0.5 text-right text-base font-black tabular-nums text-slate-950 outline-none focus:ring-0 disabled:text-slate-500"
                                    >
                                    <span class="ml-1 text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">{{ $item->unit }}</span>
                                </div>
                            </div>
                            <p class="shop-difference-value text-right text-[11px] font-black uppercase tracking-[0.12em] text-slate-500 md:mt-1"></p>
                        </div>

                        <div class="flex items-center justify-between gap-2 md:justify-end">
                            <span class="md:hidden text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Submit</span>
                            <button
                                type="button"
                                class="shop-item-submit inline-flex h-10 min-w-[6.75rem] items-center justify-center gap-1.5 rounded-2xl border px-3 text-[10px] font-black uppercase tracking-[0.14em] transition disabled:cursor-not-allowed {{ $statusTone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($statusTone === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-700' : ($statusTone === 'info' ? 'border-cyan-200 bg-cyan-50 text-cyan-700' : 'border-slate-200 bg-white text-slate-600 hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700')) }}"
                                @disabled(! $isEditable || $isItemVerified)
                                data-default-label="{{ $statusLabel }}"
                            >
                                <svg class="shop-item-submit-icon h-3.5 w-3.5 {{ $isItemVerified ? '' : 'hidden' }}" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="shop-item-submit-label">{{ $isItemVerified ? $statusLabel : 'Submit' }}</span>
                            </button>
                        </div>
                    </div>
                    <p class="shop-item-error mt-2 hidden rounded-2xl bg-red-50 px-3 py-2 text-xs font-bold text-red-700"></p>
                </article>
            @endforeach
        </div>

        <div class="border-t border-slate-100 bg-slate-50 px-4 py-4 sm:px-6">
            <div id="shop-delivery-progress-panel" class="rounded-[1.5rem] {{ $isEditable ? 'bg-slate-950 text-white' : 'border border-amber-200 bg-amber-50 text-amber-950' }} p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p id="shop-delivery-progress-title" class="text-[10px] font-black uppercase tracking-[0.16em] {{ $isEditable ? 'text-slate-400' : 'text-amber-700' }}">{{ $bottomTitle }}</p>
                        <p id="shop-delivery-progress-message" class="mt-1 text-sm font-semibold leading-6 {{ $isEditable ? 'text-slate-200' : 'text-amber-900' }}">{{ $bottomMessage }}</p>
                    </div>
                    <span id="shop-delivery-progress-count" class="shrink-0 rounded-2xl {{ $isEditable ? 'bg-white/10 text-white' : 'bg-white text-amber-800' }} px-4 py-2 text-xs font-black uppercase tracking-[0.14em]">
                        {{ $progressLabel }}
                    </span>
                </div>
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

        function normalizeValue(input) {
            let val = parseFloat(input.value);

            if (Number.isNaN(val) || val < 0) {
                val = 0;
            }

            return val;
        }

        function updateRow(row) {
            const approvedQty = parseFloat(row.dataset.approvedQty) || 0;
            const unit = row.dataset.unit || '';
            const input = row.querySelector('.shop-delivered-qty-input');
            const differenceLabel = row.querySelector('.shop-difference-value');
            const receivedQty = normalizeValue(input);
            const shortage = Math.max(0, approvedQty - receivedQty);
            const excess = Math.max(0, receivedQty - approvedQty);

            differenceLabel.textContent = shortage > 0.001
                ? `Short ${shortage.toFixed(2)} ${unit}`.trim()
                : (excess > 0.001 ? `Excess ${excess.toFixed(2)} ${unit}`.trim() : `Matched`);
            differenceLabel.classList.toggle('text-amber-700', shortage > 0.001);
            differenceLabel.classList.toggle('text-cyan-700', excess > 0.001);
            differenceLabel.classList.toggle('text-emerald-700', shortage <= 0.001 && excess <= 0.001);
        }

        function setRowError(row, message) {
            const error = row.querySelector('.shop-item-error');
            error.textContent = message || '';
            error.classList.toggle('hidden', !message);
        }

        function setSubmittedState(row, result) {
            const input = row.querySelector('.shop-delivered-qty-input');
            const button = row.querySelector('.shop-item-submit');
            const icon = row.querySelector('.shop-item-submit-icon');
            const label = row.querySelector('.shop-item-submit-label');

            input.value = result.item.received_qty;
            input.disabled = true;
            button.disabled = true;
            button.classList.remove('border-slate-200', 'bg-white', 'text-slate-600', 'hover:border-emerald-200', 'hover:bg-emerald-50', 'hover:text-emerald-700');
            button.classList.add(
                result.item.status === 'short' ? 'border-amber-200' : (result.item.status === 'excess' ? 'border-cyan-200' : 'border-emerald-200'),
                result.item.status === 'short' ? 'bg-amber-50' : (result.item.status === 'excess' ? 'bg-cyan-50' : 'bg-emerald-50'),
                result.item.status === 'short' ? 'text-amber-700' : (result.item.status === 'excess' ? 'text-cyan-700' : 'text-emerald-700')
            );
            icon.classList.remove('hidden');
            label.textContent = result.item.status_label;
            setRowError(row, null);
            updateRow(row);
        }

        function lockAllRows() {
            rows.forEach((row) => {
                row.querySelector('.shop-delivered-qty-input').disabled = true;
                row.querySelector('.shop-item-submit').disabled = true;
            });
        }

        rows.forEach((row) => {
            const input = row.querySelector('.shop-delivered-qty-input');
            const approvedQty = parseFloat(row.dataset.approvedQty) || 0;
            const button = row.querySelector('.shop-item-submit');
            const label = row.querySelector('.shop-item-submit-label');

            input.addEventListener('input', function () {
                updateRow(row);
            });

            input.addEventListener('change', function () {
                this.value = normalizeValue(this).toFixed(2);
                updateRow(row);
            });

            updateRow(row);

            button.addEventListener('click', async function () {
                if (button.disabled || !csrfToken) {
                    return;
                }

                input.value = normalizeValue(input).toFixed(2);
                button.disabled = true;
                label.textContent = 'Saving';
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
                            received_qty: input.value,
                        }),
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        throw new Error(result.message || 'Unable to submit this product.');
                    }

                    setSubmittedState(row, result);
                    progressCount.textContent = result.progress.label;
                    progressTitle.textContent = result.order_status_label;
                    progressMessage.textContent = result.message;

                    if (result.order_submitted) {
                        lockAllRows();
                        progressTitle.textContent = wrapper.dataset.completeTitle || result.order_status_label;
                        progressMessage.textContent = wrapper.dataset.completeMessage || result.message;
                    }
                } catch (error) {
                    button.disabled = false;
                    label.textContent = 'Submit';
                    setRowError(row, error.message || 'Unable to submit this product.');
                }
            });
        });
    });
</script>
