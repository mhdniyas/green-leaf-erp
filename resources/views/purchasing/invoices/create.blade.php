<x-layouts.app title="Create & Match Purchase Invoice">

    @php
        // Calculate expected invoice amount based on received quantities and PO unit prices
        $expectedAmount = 0.00;
        foreach ($grn->items as $item) {
            $expectedAmount += (float) $item->received_qty * (float) ($item->purchaseOrderItem?->unit_price ?? 0);
        }
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left column - Form --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">Invoice Details</h2>
                    <p class="text-xs text-gray-500 mt-0.5 font-medium">Record the supplier invoice details and match it to this Goods Received Note.</p>
                </div>

                <form method="POST" action="{{ route('purchasing.invoices.store') }}" class="p-6 space-y-6">
                    @csrf
                    <input type="hidden" name="goods_received_id" value="{{ $grn->id }}">
                    <input type="hidden" name="supplier_id" value="{{ $grn->purchaseOrder->supplier_id }}">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div class="space-y-1.5">
                            <label for="invoice_number" class="block text-sm font-medium text-gray-700">Invoice Number <span class="text-red-500">*</span></label>
                            <input id="invoice_number" name="invoice_number" type="text" required
                                   value="{{ old('invoice_number') }}"
                                   placeholder="e.g. INV-2026-0001"
                                   class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('invoice_number') border-red-300 @enderror">
                            @error('invoice_number') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="amount" class="block text-sm font-medium text-gray-700">Invoice Amount (INR) <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-medium">INR</span>
                                <input id="amount" name="amount" type="number" step="0.01" min="0.01" required
                                       value="{{ old('amount', number_format($expectedAmount, 2, '.', '')) }}"
                                       class="w-full border border-gray-200 rounded-xl pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('amount') border-red-300 @enderror">
                            </div>
                            <p class="text-[10px] text-gray-500 mt-1">Expected amount (material only): <span class="font-semibold">INR {{ number_format($expectedAmount, 2) }}</span></p>
                            @error('amount') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div class="space-y-1.5">
                            <label for="status" class="block text-sm font-medium text-gray-700">Invoice Status <span class="text-red-500">*</span></label>
                            <select id="status" name="status" required
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('status') border-red-300 @enderror">
                                <option value="pending" {{ old('status') === 'pending' ? 'selected' : '' }}>Pending Approval</option>
                                <option value="approved" {{ old('status') === 'approved' ? 'selected' : '' }}>Approved for Payment</option>
                                <option value="paid" {{ old('status') === 'paid' ? 'selected' : '' }}>Paid</option>
                            </select>
                            @error('status') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="notes" class="block text-sm font-medium text-gray-700">Invoice Notes (optional)</label>
                        <textarea id="notes" name="notes" rows="3"
                                  placeholder="e.g. Discrepancy details, payment terms negotiated, references..."
                                  class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 resize-none">{{ old('notes') }}</textarea>
                        @error('notes') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-3 pt-4 border-t border-gray-100">
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition-colors cursor-pointer">
                            Create & Match Invoice
                        </button>
                        <a href="{{ route('purchasing.grns.show', $grn) }}"
                           class="text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Right column - Details Summary --}}
        <div class="space-y-6">
            {{-- GRN Summary Card --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Matched GRN</h3>
                <div class="grid grid-cols-2 gap-y-3 text-sm">
                    <span class="text-gray-500">GRN Number</span>
                    <a href="{{ route('purchasing.grns.show', $grn) }}" class="text-brand-600 font-mono font-bold text-right hover:underline">
                        {{ $grn->grn_number }}
                    </a>

                    <span class="text-gray-500">Received Date</span>
                    <span class="text-gray-900 font-medium text-right">{{ $grn->received_at->format('Y-m-d') }}</span>

                    <span class="text-gray-500">PO Number</span>
                    <a href="{{ route('purchasing.orders.show', $grn->purchaseOrder) }}" class="text-brand-600 font-mono font-bold text-right hover:underline">
                        {{ $grn->purchaseOrder->po_number }}
                    </a>

                    <span class="text-gray-500">PO Total Amount</span>
                    <span class="text-gray-900 font-semibold text-right">INR {{ number_format($grn->purchaseOrder->total_amount, 2) }}</span>
                </div>
            </div>

            {{-- Supplier summary Card --}}
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

            {{-- Items Breakdown --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Received Items Details</h3>
                <div class="space-y-3 divide-y divide-gray-50 max-h-96 overflow-y-auto">
                    @foreach($grn->items as $item)
                        @php
                            $receivedQty = (float) $item->received_qty;
                            $unitPrice = (float) ($item->purchaseOrderItem?->unit_price ?? 0);
                            $materialCost = $receivedQty * $unitPrice;
                        @endphp
                        <div class="pt-3 first:pt-0">
                            <div class="flex justify-between text-sm">
                                <span class="font-medium text-gray-900">{{ $item->product->name }}</span>
                                <span class="font-semibold text-gray-900">INR {{ number_format($materialCost, 2) }}</span>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 mt-0.5">
                                <span>{{ number_format($receivedQty, 3) }} kg Received</span>
                                <span>@ INR {{ number_format($unitPrice, 4) }}/kg</span>
                            </div>
                        </div>
                    @endforeach
                    <div class="pt-3 flex justify-between font-bold text-sm text-brand-700">
                        <span>Expected Total Materials Cost</span>
                        <span>INR {{ number_format($expectedAmount, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

</x-layouts.app>
