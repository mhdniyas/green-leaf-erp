@php
    $isPendingApproval = $order->delivery_status === 'pending_approval';
    $isEditable = $order->is_allocation_completed && !$order->is_delivered && !$isPendingApproval;
@endphp

<section class="rounded-[2rem] border border-slate-200 bg-white p-4 sm:p-6 shadow-sm">
    <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-4">
        <div>
            <h3 class="text-lg font-black text-slate-950">Received Update</h3>
            <p class="mt-1 text-xs text-slate-500">Verify quantities received at the shop.</p>
        </div>
        @if ($isEditable)
            <button type="button" id="btn-receive-all" class="rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-100 px-3.5 py-2 text-xs font-black uppercase tracking-wider transition active:scale-95 duration-150">
                Receive All
            </button>
        @endif
    </div>

    @if ($isPendingApproval)
        <div class="mt-4 bg-amber-50 border border-amber-100 text-amber-800 text-xs rounded-2xl p-4 leading-normal flex items-start gap-2.5">
            <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            <div>
                <span class="font-bold block mb-0.5">Pending Discrepancy Approval</span>
                You submitted a delivery check-in with discrepancies. Items that matched the approved quantities are marked as delivered, while discrepant items are pending manager approval.
            </div>
        </div>
    @endif

    @if ($isEditable)
        <form action="{{ route('requisitions.delivery.record', $order->order_number) }}" method="POST" id="shop-delivery-form" class="mt-5 space-y-4">
            @csrf

            <div class="grid grid-cols-2 gap-2.5 sm:gap-3">
                @foreach ($order->items as $item)
                    @php
                        $approvedQty = (float) ($item->approved_qty ?? 0.00);
                        $unitCost = $item->resolved_unit_cost ?? 0.00;
                    @endphp
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 shop-item-row flex flex-col justify-between"
                         data-item-id="{{ $item->id }}"
                         data-approved-qty="{{ $approvedQty }}"
                         data-unit-cost="{{ $unitCost }}">
                        
                        <div>
                            <div class="flex items-start justify-between gap-1.5">
                                <p class="font-bold text-slate-900 text-[11px] sm:text-xs leading-tight line-clamp-2 min-h-[2rem]">{{ $item->product->name }}</p>
                                
                                <!-- Row status indicator -->
                                <div class="status-indicator-container shrink-0">
                                    <span class="indicator-ok inline-flex items-center gap-0.5 bg-emerald-50 text-emerald-700 px-1.5 py-0.5 rounded-full text-[9px] font-black border border-emerald-100">
                                        <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                        OK
                                    </span>
                                    <span class="indicator-diff hidden inline-flex items-center gap-0.5 bg-amber-50 text-amber-700 px-1.5 py-0.5 rounded-full text-[9px] font-black border border-amber-100">
                                        <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                        Short
                                    </span>
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-1">Approved: <span class="font-bold text-slate-700">{{ number_format($approvedQty, 2) }} {{ $item->unit }}</span></p>
                        </div>

                        <!-- Quantity input field -->
                        <div class="mt-2.5 pt-2.5 border-t border-slate-200/60">
                            <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-1">Delivered Qty</label>
                            <div class="relative rounded-xl border border-slate-200 bg-white">
                                <input type="number" 
                                       step="0.01" 
                                       min="0" 
                                       max="{{ $approvedQty }}"
                                       name="delivered_qty[{{ $item->id }}]" 
                                       value="{{ number_format($approvedQty, 2, '.', '') }}" 
                                       class="shop-delivered-qty-input w-full rounded-xl bg-transparent py-1.5 pl-2.5 pr-8 text-left text-xs sm:text-sm font-black focus:outline-none focus:ring-1 focus:ring-emerald-500">
                                <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-slate-400 font-semibold pointer-events-none">{{ $item->unit }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Notes & Cash collected -->
            <div class="bg-slate-50 rounded-2xl border border-slate-200 p-4 mt-5 space-y-4">
                <div>
                    <label for="cash_collected" class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5">Amount Paid (Rs.)</label>
                    <input type="number" 
                           step="0.01" 
                           min="0" 
                           id="cash_collected" 
                           name="cash_collected" 
                           value="0.00" 
                           class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-900 font-black text-sm focus:border-emerald-500 focus:outline-none transition-all">
                    <p class="text-[10px] text-slate-400 mt-1 italic" id="cash-hint">Expected value matches order quantity.</p>
                </div>

                <div>
                    <label for="delivery_notes" class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5">Delivery Comments / Reason for Shortages</label>
                    <textarea id="delivery_notes" name="delivery_notes" rows="3" class="w-full text-xs bg-white border border-slate-200 rounded-xl p-3 focus:outline-none focus:border-emerald-500 resize-none transition-colors" placeholder="Enter comments about delivery discrepancies (if any)..."></textarea>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full bg-slate-950 hover:bg-slate-800 text-white text-xs font-bold py-3.5 rounded-xl transition-all cursor-pointer focus:outline-none flex items-center justify-center gap-1.5 border-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    Confirm & Check-in Delivery
                </button>
            </div>
        </form>
    @else
        <!-- Static read-only items list -->
        <div class="mt-5 grid grid-cols-2 gap-2.5 sm:gap-3">
            @foreach ($order->items as $item)
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between gap-1.5">
                            <p class="font-bold text-slate-900 text-[11px] sm:text-xs leading-tight line-clamp-2 min-h-[2rem]">{{ $item->product->name }}</p>
                            <div class="shrink-0">
                                @include('shop-owner.components.status-badge', ['label' => $item->warehouseWorkflowLabel(), 'tone' => $item->warehouseWorkflowTone()])
                            </div>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1">Approved: <span class="font-semibold text-slate-700">{{ number_format((float) ($item->approved_qty ?? 0), 2) }} {{ $item->unit }}</span></p>
                    </div>
                    <div class="mt-2.5 pt-2 border-t border-slate-200/60 flex items-center justify-between">
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Received</span>
                        <span class="text-xs font-black text-slate-900">{{ number_format((float) ($item->delivered_qty ?? 0), 2) }} {{ $item->unit }}</span>
                    </div>
                </div>
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
            const cashInput = document.getElementById('cash_collected');
            const cashHint = document.getElementById('cash-hint');

            function recalculateTotalValue() {
                let totalExpectedValue = 0;
                rows.forEach(row => {
                    const unitCost = parseFloat(row.dataset.unitCost) || 0;
                    const input = row.querySelector('.shop-delivered-qty-input');
                    const qty = parseFloat(input.value) || 0;
                    totalExpectedValue += qty * unitCost;
                });
                cashHint.textContent = 'Expected value: Rs. ' + totalExpectedValue.toFixed(2);
                return totalExpectedValue;
            }

            function updateRowIndicator(row) {
                const approvedQty = parseFloat(row.dataset.approvedQty) || 0;
                const input = row.querySelector('.shop-delivered-qty-input');
                let val = parseFloat(input.value);
                if (isNaN(val) || val < 0) val = 0;

                const okIndicator = row.querySelector('.indicator-ok');
                const diffIndicator = row.querySelector('.indicator-diff');

                if (Math.abs(val - approvedQty) < 0.001) {
                    okIndicator.classList.remove('hidden');
                    diffIndicator.classList.add('hidden');
                } else {
                    okIndicator.classList.add('hidden');
                    diffIndicator.classList.remove('hidden');
                }
            }

            rows.forEach(row => {
                const input = row.querySelector('.shop-delivered-qty-input');
                const approvedQty = parseFloat(row.dataset.approvedQty) || 0;

                input.addEventListener('input', function() {
                    let val = parseFloat(this.value);
                    if (val > approvedQty) {
                        this.value = approvedQty.toFixed(2);
                    }
                    updateRowIndicator(row);
                    recalculateTotalValue();
                });

                input.addEventListener('change', function() {
                    let val = parseFloat(this.value);
                    if (isNaN(val) || val < 0) val = 0;
                    if (val > approvedQty) val = approvedQty;
                    this.value = val.toFixed(2);
                    updateRowIndicator(row);
                    recalculateTotalValue();
                });

                // Initial update
                updateRowIndicator(row);
            });

            // Set default cash collected value to total expected
            const initVal = recalculateTotalValue();
            cashInput.value = initVal.toFixed(2);

            // Receive All Button handler
            btnReceiveAll.addEventListener('click', function() {
                rows.forEach(row => {
                    const approvedQty = parseFloat(row.dataset.approvedQty) || 0;
                    const input = row.querySelector('.shop-delivered-qty-input');
                    input.value = approvedQty.toFixed(2);
                    updateRowIndicator(row);
                });
                const finalVal = recalculateTotalValue();
                cashInput.value = finalVal.toFixed(2);

                // Auto submit immediately
                form.submit();
            });
        });
    </script>
@endif
