@php
    $isPendingApproval = $order->delivery_status === 'pending_approval';
    $isEditable = $order->is_allocation_completed && ! $order->is_delivered && ! $isPendingApproval;
    $sortedItems = $order->items->sortBy(
        fn ($item) => \App\Models\Product::sortableSku((string) ($item->product?->sku ?? ''))
    );
@endphp

<section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
    <div class="flex flex-col gap-4 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Step 1</p>
            <h3 class="mt-1 text-lg font-black text-slate-950">Check Delivered Quantities</h3>
            <p class="mt-2 text-sm text-slate-600">Enter what actually reached the shop. Any difference automatically becomes a discrepancy for manager review.</p>
        </div>
        @if ($isEditable)
            <button type="button" id="btn-receive-all" class="inline-flex items-center justify-center rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-black uppercase tracking-[0.16em] text-emerald-700 transition hover:bg-emerald-100">
                Receive Full Order
            </button>
        @endif
    </div>

    @if ($isPendingApproval)
        <div class="mt-4 rounded-3xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-amber-700">Waiting For Purchase Manager</p>
            <p class="mt-2 text-sm leading-6 text-amber-900">Your delivery check-in has a discrepancy. Products that matched are already recorded. The remaining difference must be approved or sent back by the purchase manager before finance is finalized.</p>
        </div>
    @endif

    @if ($isEditable)
        <form action="{{ route('requisitions.delivery.record', $order->order_number) }}" method="POST" id="shop-delivery-form" class="mt-5 space-y-4">
            @csrf

            <div class="space-y-3">
                @foreach ($sortedItems as $item)
                    @php
                        $approvedQty = (float) ($item->approved_qty ?? 0.00);
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

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="rounded-2xl bg-white p-3">
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Approved</p>
                                <p class="mt-1 text-lg font-black text-slate-950">{{ number_format($approvedQty, 2) }} {{ $item->unit }}</p>
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
                        <p class="mt-1 text-sm font-semibold text-slate-200">Submit delivery check-in. Matching quantities finish immediately. Short quantities go to purchase manager approval.</p>
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
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-2xl bg-white p-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Approved</p>
                            <p class="mt-1 text-sm font-black text-slate-950">{{ number_format((float) ($item->approved_qty ?? 0), 2) }} {{ $item->unit }}</p>
                        </div>
                        <div class="rounded-2xl bg-white p-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Received</p>
                            <p class="mt-1 text-sm font-black text-slate-950">{{ number_format((float) ($item->delivered_qty ?? 0), 2) }} {{ $item->unit }}</p>
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
