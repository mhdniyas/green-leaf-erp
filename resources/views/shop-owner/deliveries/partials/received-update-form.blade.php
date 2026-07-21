@php
    $isPendingApproval = $order->delivery_status === 'pending_approval';
    $deliveryEligibility = $deliveryEligibility ?? ['allowed' => true, 'message' => null];
    $isEditable = $order->is_allocation_completed && ! $order->is_delivered && ! $isPendingApproval && $deliveryEligibility['allowed'];
    $sortedItems = $order->items->sortBy(
        fn ($item) => \App\Models\Product::sortableSku((string) ($item->product?->sku ?? ''))
    );
    $invoice = $order->invoice;
    $invoiceItemsByOrderItemId = $invoice?->items?->keyBy('shop_order_item_id') ?? collect();
@endphp

<section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
    <div class="flex flex-col gap-4 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Step 1</p>
            <h3 class="mt-1 text-lg font-black text-slate-950">Check Delivered Quantities</h3>
            <p class="mt-2 text-sm text-slate-600">Enter what actually reached the shop. Every submission goes to admin review before final quantities and invoice totals are confirmed.</p>
        </div>
        @if ($isEditable)
            <button type="button" id="btn-receive-all" class="inline-flex items-center justify-center rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-black uppercase tracking-[0.16em] text-emerald-700 transition hover:bg-emerald-100">
                Receive Full Order
            </button>
        @endif
    </div>

    @if ($isPendingApproval)
        <div class="mt-4 rounded-3xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-amber-700">Waiting For Admin Review</p>
            <p class="mt-2 text-sm leading-6 text-amber-900">Your reported quantities were submitted successfully. Final received quantities and invoice totals will update only after admin approval or a correction request.</p>
        </div>
    @elseif (! $deliveryEligibility['allowed'])
        <div class="mt-4 rounded-3xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-amber-700">Verification Disabled</p>
            <p class="mt-2 text-sm leading-6 text-amber-900">{{ $deliveryEligibility['message'] }}</p>
        </div>
    @endif

    @if ($invoice)
        <div class="mt-4 rounded-[1.75rem] border border-emerald-200 bg-emerald-50 p-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Approved Invoice Pricing</p>
                    <p class="mt-1 text-sm font-bold text-emerald-950">{{ $invoice->invoice_number }} · {{ $order->business_date?->format('d/m/Y') }}</p>
                </div>
                <div class="rounded-2xl bg-white px-4 py-2 text-right">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Net Total</p>
                    <p class="mt-1 text-lg font-black tabular-nums text-slate-950">Rs. {{ number_format((float) $invoice->final_total, 2) }}</p>
                </div>
            </div>

            <div class="mt-4 overflow-hidden rounded-2xl border border-emerald-100 bg-white">
                <div class="grid grid-cols-[minmax(0,1fr)_4.5rem_5rem_6rem] gap-2 border-b border-emerald-100 px-3 py-2 text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">
                    <span>Item</span>
                    <span class="text-right">Qty</span>
                    <span class="text-right">Rate</span>
                    <span class="text-right">Total</span>
                </div>
                <div class="divide-y divide-emerald-50">
                    @foreach ($sortedItems as $item)
                        @php
                            $invoiceItem = $invoiceItemsByOrderItemId->get($item->id);
                            $approvedQty = (float) ($invoiceItem?->approved_qty ?? $item->approved_qty ?? 0.00);
                            $unitRate = (float) ($invoiceItem?->unit_price ?? 0.00);
                            $lineTotal = (float) ($invoiceItem?->line_subtotal ?? ($approvedQty * $unitRate));
                        @endphp
                        <div class="grid grid-cols-[minmax(0,1fr)_4.5rem_5rem_6rem] gap-2 px-3 py-3 text-sm">
                            <div class="min-w-0">
                                <p class="truncate font-black text-slate-950">{{ $item->product->name }}</p>
                                <p class="mt-0.5 truncate text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">{{ $item->product->sku }}</p>
                            </div>
                            <p class="self-center text-right font-bold tabular-nums text-slate-700">{{ number_format($approvedQty, 2) }}</p>
                            <p class="self-center text-right font-bold tabular-nums text-slate-950">Rs. {{ number_format($unitRate, 2) }}</p>
                            <p class="self-center text-right font-black tabular-nums text-slate-950">Rs. {{ number_format($lineTotal, 2) }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="border-t border-emerald-100 bg-emerald-50/70 px-3 py-3">
                    <div class="ml-auto grid max-w-xs grid-cols-2 gap-2 text-sm">
                        <span class="font-bold text-slate-600">Total</span>
                        <span class="text-right font-black tabular-nums text-slate-950">Rs. {{ number_format((float) $invoice->subtotal, 2) }}</span>
                        @if ((float) $invoice->discount_total > 0)
                            <span class="font-bold text-slate-600">Discount</span>
                            <span class="text-right font-black tabular-nums text-slate-950">Rs. {{ number_format((float) $invoice->discount_total, 2) }}</span>
                        @endif
                        <span class="font-black text-slate-950">Net Total</span>
                        <span class="text-right font-black tabular-nums text-slate-950">Rs. {{ number_format((float) $invoice->final_total, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($isEditable)
        <form action="{{ route('requisitions.delivery.record', $order->order_number) }}" method="POST" id="shop-delivery-form" class="mt-5 space-y-4">
            @csrf

            <div class="space-y-3">
                @foreach ($sortedItems as $item)
                    @php
                        $approvedQty = (float) ($item->approved_qty ?? 0.00);
                        $invoiceItem = $invoiceItemsByOrderItemId->get($item->id);
                        $unitRate = (float) ($invoiceItem?->unit_price ?? 0.00);
                        $lineTotal = (float) ($invoiceItem?->line_subtotal ?? ($approvedQty * $unitRate));
                    @endphp
                    <article
                        class="shop-item-row rounded-[1.75rem] border border-slate-200 bg-slate-50 p-4"
                        data-item-id="{{ $item->id }}"
                        data-approved-qty="{{ $approvedQty }}"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h4 class="text-base font-black text-slate-950">{{ $item->product->name }}</h4>
                                <p class="mt-1 text-[11px] font-bold tracking-[0.16em] text-slate-500">Code {{ $item->product->sku }} · {{ strtoupper($item->unit) }}</p>
                            </div>
                            <div class="status-indicator-container shrink-0">
                                <span class="indicator-ok inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                    Match
                                </span>
                                <span class="indicator-diff hidden inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-amber-800">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    Short
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="rounded-2xl bg-white p-3">
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Approved</p>
                                <p class="mt-1 text-lg font-black text-slate-950">{{ number_format($approvedQty, 2) }} {{ $item->unit }}</p>
                            </div>
                            <div class="rounded-2xl bg-white p-3">
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Rate</p>
                                <p class="mt-1 text-lg font-black tabular-nums text-slate-950">Rs. {{ number_format($unitRate, 2) }}</p>
                            </div>
                            <div class="rounded-2xl bg-white p-3">
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Total</p>
                                <p class="mt-1 text-lg font-black tabular-nums text-slate-950">Rs. {{ number_format($lineTotal, 2) }}</p>
                            </div>
                            <div class="rounded-2xl bg-white p-3">
                                <label class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Delivered</label>
                                <div class="mt-1 flex items-center rounded-2xl border border-slate-200 bg-white px-3">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="{{ $approvedQty }}"
                                        name="delivered_qty[{{ $item->id }}]"
                                        value="{{ number_format($approvedQty, 2, '.', '') }}"
                                        class="shop-delivered-qty-input w-full border-0 bg-transparent py-3 text-lg font-black text-slate-950 outline-none focus:ring-0"
                                    >
                                    <span class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">{{ $item->unit }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 rounded-2xl bg-white px-3 py-2.5">
                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Current Difference</p>
                            <p class="shop-difference-value mt-1 text-sm font-black text-slate-700">0.00 {{ $item->unit }}</p>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-4">
                <label for="delivery_notes" class="block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Comments For Discrepancy</label>
                <textarea id="delivery_notes" name="delivery_notes" rows="4" class="mt-3 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-emerald-500" placeholder="Explain missing or damaged items if delivered quantity is short..."></textarea>
            </div>

            <div class="rounded-[1.75rem] bg-slate-950 p-4 text-white shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Step 2</p>
                        <p class="mt-1 text-sm font-semibold text-slate-200">Submit delivery check-in for admin review. The invoice stays provisional until the final approved received quantities are recorded.</p>
                    </div>
                </div>
                <button type="submit" class="mt-4 inline-flex w-full items-center justify-center rounded-2xl bg-white px-5 py-3.5 text-sm font-black text-slate-950 transition hover:bg-slate-100">
                    Confirm Delivery Check-In
                </button>
            </div>
        </form>
    @else
        <div class="mt-5 space-y-3">
            @foreach ($sortedItems as $item)
                @php
                    $approvedQty = (float) ($item->approved_qty ?? 0);
                    $invoiceItem = $invoiceItemsByOrderItemId->get($item->id);
                    $unitRate = (float) ($invoiceItem?->unit_price ?? 0.00);
                    $lineTotal = (float) ($invoiceItem?->line_subtotal ?? ($approvedQty * $unitRate));
                @endphp
                <article class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h4 class="text-base font-black text-slate-950">{{ $item->product->name }}</h4>
                            <p class="mt-1 text-[11px] font-bold tracking-[0.16em] text-slate-500">Code {{ $item->product->sku }} · {{ strtoupper($item->unit) }}</p>
                        </div>
                        <div class="shrink-0">
                            @include('shop-owner.components.status-badge', ['label' => $item->warehouseWorkflowLabel(), 'tone' => $item->warehouseWorkflowTone()])
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-2xl bg-white p-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Approved</p>
                            <p class="mt-1 text-sm font-black text-slate-950">{{ number_format($approvedQty, 2) }} {{ $item->unit }}</p>
                        </div>
                        <div class="rounded-2xl bg-white p-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Rate</p>
                            <p class="mt-1 text-sm font-black tabular-nums text-slate-950">Rs. {{ number_format($unitRate, 2) }}</p>
                        </div>
                        <div class="rounded-2xl bg-white p-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Total</p>
                            <p class="mt-1 text-sm font-black tabular-nums text-slate-950">Rs. {{ number_format($lineTotal, 2) }}</p>
                        </div>
                        <div class="rounded-2xl bg-white p-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">{{ $isPendingApproval ? 'Reported Received' : 'Received' }}</p>
                            <p class="mt-1 text-sm font-black text-slate-950">{{ number_format((float) ($isPendingApproval ? ($item->shop_reported_received_qty ?? 0) : ($item->delivered_qty ?? 0)), 2) }} {{ $item->unit }}</p>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>

@if ($isEditable)
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('shop-delivery-form');
            const rows = document.querySelectorAll('.shop-item-row');
            const btnReceiveAll = document.getElementById('btn-receive-all');

            function updateRowIndicator(row) {
                const approvedQty = parseFloat(row.dataset.approvedQty) || 0;
                const input = row.querySelector('.shop-delivered-qty-input');
                const unit = row.dataset.unit || '';
                let val = parseFloat(input.value);

                if (Number.isNaN(val) || val < 0) {
                    val = 0;
                }

                const difference = Math.max(0, approvedQty - val);
                row.querySelector('.shop-difference-value').textContent = `${difference.toFixed(2)} ${unit}`.trim();

                const okIndicator = row.querySelector('.indicator-ok');
                const diffIndicator = row.querySelector('.indicator-diff');

                if (Math.abs(val - approvedQty) < 0.001) {
                    okIndicator.classList.remove('hidden');
                    diffIndicator.classList.add('hidden');
                    row.classList.remove('border-amber-300', 'bg-amber-50/70');
                    row.classList.add('border-slate-200', 'bg-slate-50');
                } else {
                    okIndicator.classList.add('hidden');
                    diffIndicator.classList.remove('hidden');
                    row.classList.remove('border-slate-200', 'bg-slate-50');
                    row.classList.add('border-amber-300', 'bg-amber-50/70');
                }
            }

            rows.forEach((row) => {
                const input = row.querySelector('.shop-delivered-qty-input');
                const approvedQty = parseFloat(row.dataset.approvedQty) || 0;
                const unitLabel = row.querySelector('.shop-difference-value');
                const unit = input.closest('.rounded-2xl').querySelector('span').textContent;

                row.dataset.unit = unit;

                input.addEventListener('input', function () {
                    let val = parseFloat(this.value);
                    if (val > approvedQty) {
                        this.value = approvedQty.toFixed(2);
                    }

                    updateRowIndicator(row);
                    const difference = Math.max(0, approvedQty - (parseFloat(this.value) || 0));
                    unitLabel.textContent = `${difference.toFixed(2)} ${unit}`;
                });

                input.addEventListener('change', function () {
                    let val = parseFloat(this.value);
                    if (Number.isNaN(val) || val < 0) {
                        val = 0;
                    }
                    if (val > approvedQty) {
                        val = approvedQty;
                    }

                    this.value = val.toFixed(2);
                    updateRowIndicator(row);
                    const difference = Math.max(0, approvedQty - val);
                    unitLabel.textContent = `${difference.toFixed(2)} ${unit}`;
                });

                const initialDifference = Math.max(0, approvedQty - (parseFloat(input.value) || 0));
                unitLabel.textContent = `${initialDifference.toFixed(2)} ${unit}`;
                updateRowIndicator(row);
            });

            btnReceiveAll?.addEventListener('click', function () {
                rows.forEach((row) => {
                    const approvedQty = parseFloat(row.dataset.approvedQty) || 0;
                    const input = row.querySelector('.shop-delivered-qty-input');
                    input.value = approvedQty.toFixed(2);
                    updateRowIndicator(row);
                    row.querySelector('.shop-difference-value').textContent = `0.00 ${row.dataset.unit}`;
                });

                form.submit();
            });
        });
    </script>
@endif
