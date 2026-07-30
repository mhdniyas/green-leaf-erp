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
        default => 'Confirm Delivery',
    };
    $bottomMessage = match (true) {
        $isPendingApproval => 'Your received quantities are submitted. Admin recheck is required before final invoice totals are confirmed.',
        ! $deliveryEligibility['allowed'] => $deliveryEligibility['message'],
        default => 'Check received quantities against the invoice, then submit delivery verification once.',
    };
@endphp

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div
        id="shop-delivery-item-verification"
        data-csrf-token="{{ csrf_token() }}"
        data-complete-title="Submitted For Admin Review"
        data-complete-message="All products are submitted. Admin recheck is required before final invoice totals are confirmed."
    >
        <div class="relative mx-auto min-h-[36rem] max-w-[38rem] bg-white px-3 py-5 text-slate-950 sm:px-6 sm:py-7">
            <header class="border-b border-dashed border-slate-400 pb-3 text-center">
                <h3 class="text-xl font-black uppercase tracking-wide text-slate-950">Delivery Verification</h3>
                <p class="mt-2 text-base font-black uppercase leading-tight text-slate-950">{{ $order->shop?->name }}</p>
                <p class="mt-0.5 text-[11px] font-semibold leading-tight text-slate-700">{{ $invoice?->invoice_number ?? $order->order_number }} · {{ $order->business_date?->format('d M Y') }}</p>
            </header>

            <div class="grid grid-cols-1 gap-3 border-b border-dashed border-slate-400 py-3 text-[11px] font-bold text-slate-800 sm:grid-cols-2">
                <div class="min-w-0">
                    <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-500">Delivery Ref</p>
                    <p class="mt-1 font-black text-slate-950">{{ $order->order_number }}</p>
                    <p class="mt-1">Items: {{ $totalVerifiableCount }}</p>
                </div>
                <div class="sm:text-right">
                    <p>Invoice Total</p>
                    <p class="mt-1 text-sm font-black text-slate-950">Rs. {{ number_format((float) ($invoice?->final_total ?? 0), 2) }}</p>
                </div>
            </div>

            <div class="overflow-x-auto border-b border-dashed border-slate-400 py-3">
                <table class="w-full table-fixed text-left text-[11px]">
                    <thead class="border-b border-dashed border-slate-400 text-[10px] font-black uppercase text-slate-950">
                        <tr>
                            <th class="w-7 py-1 pr-1">SN</th>
                            <th class="py-1 pr-2">Item</th>
                            <th class="w-12 py-1 pr-1 text-right">Qty</th>
                            <th class="w-16 py-1 pr-1 text-right">Rate</th>
                            <th class="w-20 py-1 pr-1 text-right">Amt</th>
                            <th class="w-20 py-1 text-right">Received</th>
                        </tr>
                    </thead>
                    <tbody>
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
                            @endphp
                            <tr
                                class="shop-item-row align-top"
                                data-item-id="{{ $item->id }}"
                                data-verify-url="{{ route('shop-owner.deliveries.items.verify', [$order->order_number, $item]) }}"
                                data-approved-qty="{{ $approvedQty }}"
                                data-unit="{{ $item->unit }}"
                                data-verified="{{ $isItemVerified ? 'true' : 'false' }}"
                            >
                                <td class="py-2 pr-1 font-bold">{{ $loop->iteration }}</td>
                                <td class="py-2 pr-2">
                                    <p class="font-black text-slate-950">{{ $item->product->name }}</p>
                                    <p class="mt-0.5 text-[10px] font-semibold text-slate-500">{{ $item->product->sku }} · {{ $item->requestedMeasureBreakdownLabel() }}</p>
                                    <p class="shop-difference-value mt-0.5 text-[10px] font-black uppercase tracking-[0.08em] text-slate-500"></p>
                                    <p class="shop-item-status mt-0.5 text-[10px] font-black uppercase tracking-[0.08em] {{ $isItemVerified ? 'text-emerald-700' : 'text-slate-400' }}">{{ $statusLabel }}</p>
                                    <p class="shop-item-error mt-1 hidden rounded-md bg-red-50 px-2 py-1 text-[10px] font-bold text-red-700"></p>
                                </td>
                                <td class="py-2 pr-1 text-right font-bold">{{ number_format($approvedQty, 2) }}</td>
                                <td class="py-2 pr-1 text-right font-bold">Rs. {{ number_format($unitRate, 2) }}</td>
                                <td class="py-2 pr-1 text-right font-black text-slate-950">Rs. {{ number_format($lineTotal, 2) }}</td>
                                <td class="py-2 text-right">
                                    <div class="flex items-center justify-end rounded-md border border-slate-200 bg-slate-50 px-1">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            name="delivered_qty[{{ $item->id }}]"
                                            value="{{ number_format($receivedQty, 2, '.', '') }}"
                                            @disabled(! $isEditable || $isItemVerified)
                                            class="shop-delivered-qty-input h-7 w-12 border-0 bg-transparent px-0 text-right text-[11px] font-black tabular-nums text-slate-950 outline-none focus:ring-0 disabled:text-slate-500"
                                        >
                                        <span class="ml-0.5 text-[9px] font-black uppercase text-slate-400">{{ $item->unit }}</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="ml-auto w-full max-w-full border-b border-dashed border-slate-400 py-3 text-[11px] font-bold text-slate-800 sm:max-w-[20rem]">
                <div class="flex items-center justify-between">
                    <span>Invoice Total</span>
                    <span>Rs. {{ number_format((float) ($invoice?->final_total ?? 0), 2) }}</span>
                </div>
                <div class="mt-1.5 flex items-center justify-between">
                    <span>Verification</span>
                    <span id="shop-delivery-progress-count">{{ $progressLabel }}</span>
                </div>
            </div>

            <footer class="pt-3 text-center">
                <p class="text-xs font-black text-slate-800">Please confirm delivered quantity</p>
            </footer>
        </div>

        <div class="border-t border-slate-100 bg-slate-50 px-4 py-4 sm:px-6">
            <div id="shop-delivery-progress-panel" class="rounded-[1.5rem] {{ $isEditable ? 'bg-slate-950 text-white' : 'border border-amber-200 bg-amber-50 text-amber-950' }} p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p id="shop-delivery-progress-title" class="text-[10px] font-black uppercase tracking-[0.16em] {{ $isEditable ? 'text-slate-400' : 'text-amber-700' }}">{{ $bottomTitle }}</p>
                        <p id="shop-delivery-progress-message" class="mt-1 text-sm font-semibold leading-6 {{ $isEditable ? 'text-slate-200' : 'text-amber-900' }}">{{ $bottomMessage }}</p>
                    </div>
                    <button type="button" id="shop-delivery-submit-all" class="shrink-0 rounded-2xl {{ $isEditable ? 'bg-emerald-500 text-slate-950 hover:bg-emerald-400' : 'bg-white text-amber-800' }} px-4 py-3 text-xs font-black uppercase tracking-[0.14em] transition disabled:cursor-not-allowed disabled:opacity-60" @disabled(! $isEditable || $verifiedCount === $totalVerifiableCount)>
                        Submit Delivery Verification
                    </button>
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
        const submitAllButton = document.getElementById('shop-delivery-submit-all');

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
            const status = row.querySelector('.shop-item-status');

            input.value = result.item.received_qty;
            input.disabled = true;
            row.dataset.verified = 'true';

            if (status) {
                status.textContent = result.item.status_label;
                status.classList.remove('text-slate-400', 'text-emerald-700', 'text-amber-700', 'text-cyan-700');
                status.classList.add(result.item.status === 'short' ? 'text-amber-700' : (result.item.status === 'excess' ? 'text-cyan-700' : 'text-emerald-700'));
            }

            setRowError(row, null);
            updateRow(row);
        }

        function lockAllRows() {
            rows.forEach((row) => {
                row.querySelector('.shop-delivered-qty-input').disabled = true;
            });

            if (submitAllButton) {
                submitAllButton.disabled = true;
            }
        }

        rows.forEach((row) => {
            const input = row.querySelector('.shop-delivered-qty-input');

            input.addEventListener('input', function () {
                updateRow(row);
            });

            input.addEventListener('change', function () {
                this.value = normalizeValue(this).toFixed(2);
                updateRow(row);
            });

            updateRow(row);
        });

        submitAllButton?.addEventListener('click', async function () {
            if (submitAllButton.disabled || !csrfToken) {
                return;
            }

            const pendingRows = Array.from(rows).filter((row) => row.dataset.verified !== 'true');
            if (pendingRows.length === 0) {
                return;
            }

            submitAllButton.disabled = true;
            submitAllButton.textContent = 'Submitting';

            for (const row of pendingRows) {
                const input = row.querySelector('.shop-delivered-qty-input');
                input.value = normalizeValue(input).toFixed(2);
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
