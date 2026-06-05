<x-layouts.app title="Correct Goods Received Note (GRN) — {{ $grn->grn_number }}">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left column - form items --}}
        <div class="lg:col-span-2 space-y-6">
            @if($grn->rejection_remarks)
                <div class="bg-red-50 border border-red-200 text-red-800 rounded-2xl p-5 space-y-1">
                    <h3 class="text-sm font-bold flex items-center gap-2 text-red-900">
                        <svg class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                        Rejection Comments / Correction Instructions
                    </h3>
                    <p class="text-sm text-red-800 whitespace-pre-line leading-relaxed font-medium">
                        {{ $grn->rejection_remarks }}
                    </p>
                </div>
            @endif

            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">Correct Received Quantities</h2>
                    <p class="text-xs text-gray-500 mt-0.5 font-medium">Update the quantities and landed costs to correct the discrepancies.</p>
                </div>

                <form id="grn-form" method="POST" action="{{ route('purchasing.grns.update', $grn) }}" class="p-6 space-y-6">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="purchase_order_id" value="{{ $grn->purchase_order_id }}">

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                        <div class="space-y-1.5">
                            <label for="received_at" class="block text-sm font-medium text-gray-700">Received Date <span class="text-red-500">*</span></label>
                            <input id="received_at" name="received_at" type="date" required
                                   value="{{ old('received_at', $grn->received_at->format('Y-m-d')) }}"
                                   max="{{ today()->toDateString() }}"
                                   class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('received_at') border-red-300 @enderror">
                            @error('received_at') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="transport_cost" class="block text-sm font-medium text-gray-700">Transport Cost</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-medium">INR</span>
                                <input id="transport_cost" name="transport_cost" type="number" step="0.01" min="0"
                                       value="{{ old('transport_cost', $grn->transport_cost) }}"
                                       class="w-full border border-gray-200 rounded-xl pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('transport_cost') border-red-300 @enderror">
                            </div>
                            @error('transport_cost') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="labour_cost" class="block text-sm font-medium text-gray-700">Labour Cost</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-medium">INR</span>
                                <input id="labour_cost" name="labour_cost" type="number" step="0.01" min="0"
                                       value="{{ old('labour_cost', $grn->labour_cost) }}"
                                       class="w-full border border-gray-200 rounded-xl pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('labour_cost') border-red-300 @enderror">
                            </div>
                            @error('labour_cost') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="notes" class="block text-sm font-medium text-gray-700">Receipt Notes (optional)</label>
                        <textarea id="notes" name="notes" rows="2"
                                  placeholder="e.g. Any damage, transport company details..."
                                  class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 resize-none">{{ old('notes', $grn->notes) }}</textarea>
                        @error('notes') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Items table --}}
                    <div class="space-y-3 pt-4 border-t border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-900">PO Items Verification</h3>
                        @error('items') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror

                        <div class="border border-gray-200 rounded-xl overflow-hidden bg-gray-50/50">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-100/50 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                        <th class="px-4 py-3 text-left w-1/3">Product</th>
                                        <th class="px-4 py-3 text-right">Ordered Qty</th>
                                        <th class="px-4 py-3 text-right w-1/4">Received Qty (kg) <span class="text-red-500">*</span></th>
                                        <th class="px-4 py-3 text-center">Qty Variance</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @foreach($grn->items as $index => $item)
                                    @php
                                        $orderedQty = (float) ($item->purchaseOrderItem?->quantity ?? 0);
                                        $oldQty = old("items.{$index}.received_qty", number_format((float) $item->received_qty, 3, '.', ''));
                                    @endphp
                                    <tr class="grn-item-row" data-ordered="{{ $orderedQty }}">
                                        <td class="p-4">
                                            <input type="hidden" name="items[{{ $index }}][purchase_order_item_id]" value="{{ $item->purchase_order_item_id }}">
                                            <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $item->product_id }}">
                                            <p class="font-medium text-gray-900">{{ $item->product->name }}</p>
                                            <code class="text-[10px] text-gray-400 font-mono">{{ $item->product->sku }}</code>
                                        </td>
                                        <td class="p-4 text-right text-gray-600">
                                            {{ number_format($orderedQty, 3) }} kg
                                        </td>
                                        <td class="p-4">
                                            <input name="items[{{ $index }}][received_qty]" type="number" step="0.001" min="0" required
                                                   value="{{ $oldQty }}"
                                                   class="received-qty-input w-full border border-gray-200 rounded-lg px-2.5 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500">
                                            @error("items.{$index}.received_qty") <p class="text-red-600 text-[10px] mt-0.5">{{ $message }}</p> @enderror
                                        </td>
                                        <td class="p-4 text-center">
                                            <span class="variance-badge inline-flex items-center text-xs font-semibold px-2.5 py-0.5 rounded-full">
                                                0.000 kg
                                            </span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-4 border-t border-gray-100">
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition-colors cursor-pointer">
                            Resubmit GRN
                        </button>
                        <a href="{{ route('purchasing.grns.show', $grn) }}"
                           class="text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Right column - PO Summary --}}
        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Linked Purchase Order</h3>
                <div class="grid grid-cols-2 gap-y-3 text-sm">
                    <span class="text-gray-500">PO Number</span>
                    <a href="{{ route('purchasing.orders.show', $grn->purchaseOrder) }}" class="text-brand-600 font-mono font-bold text-right hover:underline">
                        {{ $grn->purchaseOrder->po_number }}
                    </a>

                    <span class="text-gray-500">Order Date</span>
                    <span class="text-gray-900 font-medium text-right">{{ $grn->purchaseOrder->order_date->format('Y-m-d') }}</span>

                    <span class="text-gray-500">PO Status</span>
                    <span class="inline-flex items-center self-end px-2.5 py-0.5 text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-200 rounded-full">
                        {{ $grn->purchaseOrder->status->label() }}
                    </span>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Supplier</h3>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
                        <span class="text-amber-700 text-sm font-bold">{{ strtoupper(substr($grn->purchaseOrder->supplier->name, 0, 1)) }}</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-gray-900">{{ $grn->purchaseOrder->supplier->name }}</p>
                        <p class="text-xs text-gray-400">{{ $grn->purchaseOrder->supplier->type }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-y-3 pt-3 border-t border-gray-100 text-xs">
                    <span class="text-gray-500">Payment Terms</span>
                    <span class="text-gray-800 font-medium text-right">{{ $grn->purchaseOrder->supplier->payment_terms }}</span>
                </div>
            </div>

            {{-- Landed Cost Allocation Explanation --}}
            <div class="bg-brand-50 border border-brand-100 rounded-2xl p-5 space-y-3">
                <h4 class="text-xs font-bold text-brand-900 uppercase tracking-wide">Landed Cost Allocation</h4>
                <p class="text-xs text-brand-700 leading-relaxed">
                    Any Transport and Labour costs entered here will be split proportionally across the received items based on their weight (received quantity).
                </p>
                <p class="text-xs text-brand-700 leading-relaxed font-semibold">
                    Upon approval by the Purchase Manager, stock batches and accounting journal entries will be generated automatically.
                </p>
            </div>
        </div>

    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const rows = document.querySelectorAll('.grn-item-row');

            function updateRowVariance(row) {
                const ordered = parseFloat(row.getAttribute('data-ordered')) || 0;
                const receivedInput = row.querySelector('.received-qty-input');
                const received = parseFloat(receivedInput.value) || 0;
                const variance = received - ordered;

                const badge = row.querySelector('.variance-badge');
                const varianceText = (variance >= 0 ? '+' : '') + variance.toFixed(3) + ' kg';
                
                badge.textContent = varianceText;

                // Adjust color classes
                badge.className = 'variance-badge inline-flex items-center text-xs font-semibold px-2.5 py-0.5 rounded-full';
                if (variance === 0) {
                    badge.classList.add('text-green-700', 'bg-green-50', 'border', 'border-green-200');
                } else if (variance < 0) {
                    badge.classList.add('text-amber-700', 'bg-amber-50', 'border', 'border-amber-200');
                } else {
                    badge.classList.add('text-blue-700', 'bg-blue-50', 'border', 'border-blue-200');
                }
            }

            rows.forEach(row => {
                const input = row.querySelector('.received-qty-input');
                input.addEventListener('input', () => updateRowVariance(row));
                // Initial update
                updateRowVariance(row);
            });
        });
    </script>
    @endpush

</x-layouts.app>
