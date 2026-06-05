<x-layouts.app title="Verify Delivery & Check-in — {{ $order->order_number }}">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <a href="{{ route('requisitions.show', $order->order_number) }}" class="text-xs font-bold text-emerald-600 hover:text-emerald-700 flex items-center gap-1.5 transition-colors mb-2">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                Back to Requisition Details
            </a>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                Verify Delivery & Check-in: <span class="text-emerald-600">{{ $order->order_number }}</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">
                Shop: <span class="font-bold text-slate-700">{{ $order->shop->name }}</span> | 
                Target Date: <span class="font-bold text-slate-700">{{ $order->business_date->format('d F Y') }}</span>
            </p>
        </div>

        <form action="{{ route('requisitions.delivery.record', $order->order_number) }}" method="POST" id="delivery-form" class="space-y-6">
            @csrf

            @if($errors->any())
                <div class="rounded-3xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800 shadow-sm">
                    <p class="font-black uppercase tracking-wider text-[11px]">Please fix the highlighted delivery issues.</p>
                    <p class="mt-1 text-xs">{{ $errors->first() }}</p>
                </div>
            @endif

            <!-- Main Checklist Card -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                    <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">Product Checklist & Discrepancies</h2>
                    <p class="text-xs text-slate-400 mt-1">Verify actual quantities received at the shop. Shortages and financial values are calculated in real-time.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse" id="items-table">
                        <thead>
                            <tr class="border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50/20">
                                <th class="py-3 px-6">Product</th>
                                <th class="py-3 px-6 text-right">Approved Qty</th>
                                <th class="py-3 px-6 text-right w-44">Delivered Qty</th>
                                <th class="py-3 px-6 text-right">Unit Cost</th>
                                <th class="py-3 px-6 text-right">Expected Deliv. Value</th>
                                <th class="py-3 px-6 text-right">Shortage Qty</th>
                                <th class="py-3 px-6 text-right">Shortage Value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            @foreach($order->items as $item)
                                @php
                                    $unitCost = $item->resolved_unit_cost;
                                    $approvedQty = (float) ($item->approved_qty ?? 0.00);
                                @endphp
                                <tr class="hover:bg-slate-50/20 item-row" 
                                    data-item-id="{{ $item->id }}" 
                                    data-approved-qty="{{ $approvedQty }}" 
                                    data-unit-cost="{{ $unitCost }}">
                                    
                                    <td class="py-4 px-6 font-semibold text-slate-900">
                                        {{ $item->product->name }}
                                        <span class="block text-[10px] text-slate-400 font-normal mt-0.5">{{ $item->product->sku }}</span>
                                    </td>
                                    
                                    <td class="py-4 px-6 text-right font-semibold text-slate-700">
                                        <span class="approved-qty-display">{{ number_format($approvedQty, 2) }}</span> {{ $item->unit }}
                                    </td>
                                    
                                    <td class="py-4 px-6 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <input type="number" 
                                                   step="0.01" 
                                                   min="0" 
                                                   name="delivered_qty[{{ $item->id }}]" 
                                                   value="{{ old("delivered_qty.{$item->id}", number_format($approvedQty, 2, '.', '')) }}" 
                                                   @class([
                                                       'delivered-qty-input w-24 rounded-lg px-2.5 py-1 text-center font-black focus:border-emerald-500 focus:outline-none transition-all',
                                                       'border border-red-300 bg-red-50 text-red-700' => $errors->has("delivered_qty.{$item->id}"),
                                                       'border border-slate-200 text-slate-900' => ! $errors->has("delivered_qty.{$item->id}"),
                                                   ])>
                                            <span class="text-slate-500 font-semibold w-8 text-left">{{ $item->unit }}</span>
                                        </div>
                                    </td>
                                    
                                    <td class="py-4 px-6 text-right font-medium text-slate-500">
                                        Rs. <span class="unit-cost-display">{{ number_format($unitCost, 4) }}</span>/{{ $item->unit }}
                                    </td>
                                    
                                    <td class="py-4 px-6 text-right font-black text-slate-900">
                                        Rs. <span class="delivered-value-display">{{ number_format($approvedQty * $unitCost, 2) }}</span>
                                    </td>
                                    
                                    <td class="py-4 px-6 text-right font-semibold text-red-600">
                                        <span class="shortage-qty-display">0.00</span> {{ $item->unit }}
                                    </td>
                                    
                                    <td class="py-4 px-6 text-right font-black text-red-600">
                                        Rs. <span class="shortage-value-display">0.00</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Bottom Section: Summary & Cash Details -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left 2 Columns: Delivery Notes -->
                <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-4">
                    <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider pb-3 border-b border-slate-100">Delivery Notes</h2>
                    <div>
                        <label for="delivery_notes" class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5">Check-in Remarks</label>
                        <textarea id="delivery_notes" name="delivery_notes" rows="4" class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl p-3 focus:outline-none focus:border-emerald-500 resize-none transition-colors" placeholder="Enter comments about the delivery condition, reasons for shortages, etc. (optional)...">{{ old('delivery_notes') }}</textarea>
                    </div>
                    <div>
                        <label for="finance_note" class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5">Finance Note</label>
                        <textarea id="finance_note" name="finance_note" rows="4" class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl p-3 focus:outline-none focus:border-emerald-500 resize-none transition-colors" placeholder="Add payment mode, balance note, deduction note, or any delivery-related finance remarks...">{{ old('finance_note') }}</textarea>
                    </div>
                </div>

                <!-- Right Column: Verification Summary -->
                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-5">
                    <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider pb-3 border-b border-slate-100">Verification Summary</h2>
                    
                    <div class="space-y-3 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400 font-bold">Approved Order Value</span>
                            <span class="font-semibold text-slate-800" id="total-approved-value-display">Rs. 0.00</span>
                        </div>

                        <div class="flex items-center justify-between text-red-600">
                            <span class="font-bold">Total Shortage Value</span>
                            <span class="font-black" id="total-shortage-value-display">Rs. 0.00</span>
                        </div>

                        <div class="flex items-center justify-between border-t border-dashed border-slate-100 pt-3 text-slate-900">
                            <span class="font-black">Expected Delivered Value</span>
                            <span class="font-black text-sm" id="expected-delivered-value-display">Rs. 0.00</span>
                        </div>
                    </div>

                    <!-- Cash Input Block -->
                    <div class="border-t border-slate-100 pt-4 space-y-3">
                        <div>
                            <label for="cash_collected" class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1.5">Amount Paid / Confirmed (Rs.)</label>
                            <input type="number" 
                                   step="0.01" 
                                   min="0" 
                                   id="cash_collected" 
                                   name="cash_collected" 
                                   value="{{ old('cash_collected', '0.00') }}" 
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-slate-900 font-black text-sm focus:border-emerald-500 focus:outline-none transition-all">
                        </div>

                        <!-- Cash Discrepancy Status -->
                        <div class="rounded-2xl p-4 flex items-center justify-between transition-colors duration-200" id="discrepancy-panel">
                            <div>
                                <span class="block text-[9px] font-black uppercase tracking-wider" id="discrepancy-label">Balance / Variance</span>
                                <span class="text-sm font-black" id="discrepancy-value-display">Rs. 0.00</span>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-black border uppercase tracking-wider" id="discrepancy-badge">
                                Balanced
                            </span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="pt-2">
                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold py-3.5 rounded-xl shadow-sm hover:shadow transition-all cursor-pointer focus:outline-none flex items-center justify-center gap-1.5 border-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0110 21.036 3.745 3.745 0 016.704 19.4a3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.745 3.745 0 013.296-1.043A3.745 3.745 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" /></svg>
                            Confirm & Check-in Delivery
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Script for Dynamic Recalculation -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const rows = document.querySelectorAll('.item-row');
            const cashCollectedInput = document.getElementById('cash_collected');
            
            const totalApprovedValueDisplay = document.getElementById('total-approved-value-display');
            const totalShortageValueDisplay = document.getElementById('total-shortage-value-display');
            const expectedDeliveredValueDisplay = document.getElementById('expected-delivered-value-display');
            const discrepancyValueDisplay = document.getElementById('discrepancy-value-display');
            
            const discrepancyPanel = document.getElementById('discrepancy-panel');
            const discrepancyLabel = document.getElementById('discrepancy-label');
            const discrepancyBadge = document.getElementById('discrepancy-badge');

            function formatCurrency(val) {
                return 'Rs. ' + parseFloat(val).toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function recalculate() {
                let totalApprovedValue = 0;
                let totalShortageValue = 0;
                let totalExpectedDeliveredValue = 0;

                rows.forEach(row => {
                    const approvedQty = parseFloat(row.dataset.approvedQty) || 0;
                    const unitCost = parseFloat(row.dataset.unitCost) || 0;
                    const deliveredInput = row.querySelector('.delivered-qty-input');
                    
                    let deliveredQty = parseFloat(deliveredInput.value);
                    if (isNaN(deliveredQty) || deliveredQty < 0) {
                        deliveredQty = 0;
                    }
                    
                    // Cap delivered_qty at approved_qty if typing exceeding amounts
                    if (deliveredQty > approvedQty) {
                        deliveredQty = approvedQty;
                        deliveredInput.value = approvedQty.toFixed(2);
                    }

                    const shortageQty = Math.max(0, approvedQty - deliveredQty);
                    const shortageValue = shortageQty * unitCost;
                    const deliveredValue = deliveredQty * unitCost;
                    const approvedValue = approvedQty * unitCost;

                    // Row-level displays
                    row.querySelector('.shortage-qty-display').textContent = shortageQty.toFixed(2);
                    row.querySelector('.shortage-value-display').textContent = shortageValue.toFixed(2);
                    row.querySelector('.delivered-value-display').textContent = deliveredValue.toFixed(2);

                    totalApprovedValue += approvedValue;
                    totalShortageValue += shortageValue;
                    totalExpectedDeliveredValue += deliveredValue;
                });

                let cashCollected = parseFloat(cashCollectedInput.value);
                if (isNaN(cashCollected) || cashCollected < 0) {
                    cashCollected = 0;
                }

                // cash_discrepancy = Expected Delivered Value - cash_collected
                const discrepancy = totalExpectedDeliveredValue - cashCollected;

                // Updates summary section
                totalApprovedValueDisplay.textContent = formatCurrency(totalApprovedValue);
                totalShortageValueDisplay.textContent = formatCurrency(totalShortageValue);
                expectedDeliveredValueDisplay.textContent = formatCurrency(totalExpectedDeliveredValue);
                discrepancyValueDisplay.textContent = formatCurrency(Math.abs(discrepancy));

                // Discrepancy styling states
                // Balanced
                if (Math.abs(discrepancy) < 0.01) {
                    discrepancyPanel.className = 'rounded-2xl p-4 flex items-center justify-between bg-emerald-50 text-emerald-800 border border-emerald-100 transition-colors duration-200';
                    discrepancyLabel.textContent = 'Cash Balanced';
                    discrepancyBadge.textContent = 'Balanced';
                    discrepancyBadge.className = 'inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200 uppercase tracking-wider';
                } 
                // Shortage (We expected more cash than collected)
                else if (discrepancy > 0) {
                    discrepancyPanel.className = 'rounded-2xl p-4 flex items-center justify-between bg-red-50 text-red-800 border border-red-100 transition-colors duration-200';
                    discrepancyLabel.textContent = 'Cash Shortage';
                    discrepancyBadge.textContent = 'Shortage';
                    discrepancyBadge.className = 'inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-black bg-red-100 text-red-800 border border-red-200 uppercase tracking-wider';
                } 
                // Surplus (We collected more cash than expected)
                else {
                    discrepancyPanel.className = 'rounded-2xl p-4 flex items-center justify-between bg-blue-50 text-blue-800 border border-blue-100 transition-colors duration-200';
                    discrepancyLabel.textContent = 'Cash Surplus';
                    discrepancyBadge.textContent = 'Surplus';
                    discrepancyBadge.className = 'inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-black bg-blue-100 text-blue-800 border border-blue-200 uppercase tracking-wider';
                }
            }

            // Bind events
            rows.forEach(row => {
                const input = row.querySelector('.delivered-qty-input');
                input.addEventListener('input', recalculate);
                input.addEventListener('change', function() {
                    // Normalize to 2 decimal places on blur/change
                    let val = parseFloat(this.value);
                    if (isNaN(val) || val < 0) val = 0;
                    this.value = val.toFixed(2);
                    recalculate();
                });
            });

            cashCollectedInput.addEventListener('input', recalculate);
            cashCollectedInput.addEventListener('change', function() {
                let val = parseFloat(this.value);
                if (isNaN(val) || val < 0) val = 0;
                this.value = val.toFixed(2);
                recalculate();
            });

            // Initial calculation
            recalculate();
        });
    </script>
</x-layouts.app>
