<x-layouts.app title="Goods Received Note Details — {{ $grn->grn_number }}">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left column - received items & notes --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Received Items Card --}}
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Received Items & Landed Cost Allocation</h2>
                    <span class="text-xs text-gray-500 font-mono">{{ $grn->items->count() }} items</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/50">
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Product</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Ordered Qty</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Received Qty</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Variance</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Allocated Landed</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Est. Total Cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @php
                                $totalReceived = $grn->items->sum(fn($i) => (float) $i->received_qty);
                                $totalPOQty = $grn->items->sum(fn($i) => (float) ($i->purchaseOrderItem?->quantity ?? 0));
                                $totalVariance = $totalReceived - $totalPOQty;
                                $totalEstCost = 0.00;
                            @endphp

                            @foreach($grn->items as $item)
                                @php
                                    $orderedQty = (float) ($item->purchaseOrderItem?->quantity ?? 0);
                                    $receivedQty = (float) $item->received_qty;
                                    $variance = (float) $item->variance;
                                    $unitPrice = (float) ($item->purchaseOrderItem?->unit_price ?? 0);
                                    $materialCost = $receivedQty * $unitPrice;

                                    // Proportional allocation logic matching RecordGoodsReceiptAction
                                    $allocatedTransport = 0.00;
                                    $allocatedLabour = 0.00;
                                    if ($totalReceived > 0) {
                                        $allocatedTransport = ($receivedQty / $totalReceived) * (float) $grn->transport_cost;
                                        $allocatedLabour = ($receivedQty / $totalReceived) * (float) $grn->labour_cost;
                                    }
                                    $allocatedLanded = $allocatedTransport + $allocatedLabour;
                                    $itemTotalCost = $materialCost + $allocatedLanded;
                                    $totalEstCost += $itemTotalCost;
                                @endphp
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-brand-100 flex items-center justify-center shrink-0">
                                                <span class="text-brand-700 text-xs font-bold">{{ strtoupper(substr($item->product->name, 0, 1)) }}</span>
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-900">{{ $item->product->name }}</p>
                                                <code class="text-[10px] font-mono text-gray-400">{{ $item->product->sku }}</code>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right text-gray-500 font-medium">
                                        {{ number_format($orderedQty, 3) }} kg
                                    </td>
                                    <td class="px-6 py-4 text-right text-gray-950 font-semibold">
                                        {{ number_format($receivedQty, 3) }} kg
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        @php
                                            if ($variance === 0.0) {
                                                $varianceClass = 'text-green-700 bg-green-50 border-green-200';
                                            } elseif ($variance < 0.0) {
                                                $varianceClass = 'text-amber-700 bg-amber-50 border-amber-200';
                                            } else {
                                                $varianceClass = 'text-blue-700 bg-blue-50 border-blue-200';
                                            }
                                        @endphp
                                        <span class="inline-flex items-center text-xs font-semibold border px-2 py-0.5 rounded-full {{ $varianceClass }}">
                                            {{ $variance >= 0 ? '+' : '' }}{{ number_format($variance, 3) }} kg
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-gray-600 font-medium">
                                        INR {{ number_format($allocatedLanded, 2) }}
                                        <div class="text-[10px] text-gray-400">
                                            T: INR {{ number_format($allocatedTransport, 2) }} | L: INR {{ number_format($allocatedLabour, 2) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right font-semibold text-gray-900">
                                        INR {{ number_format($itemTotalCost, 2) }}
                                        <div class="text-[10px] text-gray-400 font-normal">
                                            Mat: INR {{ number_format($materialCost, 2) }}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach

                            {{-- Totals --}}
                            <tr class="bg-gray-50/50 border-t border-gray-100 font-semibold">
                                <td class="px-6 py-4 text-gray-900">Total</td>
                                <td class="px-6 py-4 text-right text-gray-500">{{ number_format($totalPOQty, 3) }} kg</td>
                                <td class="px-6 py-4 text-right text-gray-900">{{ number_format($totalReceived, 3) }} kg</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center text-xs font-bold border px-2 py-0.5 rounded-full {{ $totalVariance >= 0 ? 'text-blue-700 bg-blue-50 border-blue-200' : 'text-amber-700 bg-amber-50 border-amber-200' }}">
                                        {{ $totalVariance >= 0 ? '+' : '' }}{{ number_format($totalVariance, 3) }} kg
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-gray-700">
                                    INR {{ number_format((float) $grn->transport_cost + (float) $grn->labour_cost, 2) }}
                                </td>
                                <td class="px-6 py-4 text-right text-lg text-brand-700 font-bold">
                                    INR {{ number_format($totalEstCost, 2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Notes Card --}}
            @if($grn->notes)
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Receipt Notes</h3>
                    <p class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{{ $grn->notes }}</p>
                </div>
            @endif
        </div>

        {{-- Right column - Summary & Actions --}}
        <div class="space-y-6">
            {{-- Invoice Matching Card --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Invoice Matching Status</h3>
                    @php
                        $invoice = $grn->purchaseInvoices()->first();
                    @endphp
                    @if($invoice)
                        <span class="inline-flex items-center text-sm font-semibold border px-3 py-1 rounded-full bg-green-50 text-green-700 border-green-200">
                            Matched
                        </span>
                        <div class="mt-4 text-sm text-gray-600">
                            Matched to Invoice <a href="{{ route('purchasing.invoices.show', $invoice) }}" class="font-mono font-bold text-brand-600 hover:underline">{{ $invoice->invoice_number }}</a>
                            for <span class="font-semibold text-gray-900">INR {{ number_format($invoice->amount, 2) }}</span>.
                        </div>
                    @else
                        <span class="inline-flex items-center text-sm font-semibold border px-3 py-1 rounded-full bg-amber-50 text-amber-700 border-amber-200">
                            Pending Invoice
                        </span>
                        <div class="mt-4 text-sm text-gray-500 leading-relaxed">
                            No supplier invoice has been matched against this goods receipt note yet.
                        </div>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="flex flex-col gap-2 pt-4 border-t border-gray-100">
                    @if(!$invoice)
                        @can('create', \App\Models\PurchaseInvoice::class)
                            <a href="{{ route('purchasing.invoices.create', ['goods_received_id' => $grn->id]) }}" 
                               class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Create & Match Invoice
                            </a>
                        @endcan
                    @else
                        <a href="{{ route('purchasing.invoices.show', $invoice) }}" 
                           class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-white border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                            View Purchase Invoice
                        </a>
                    @endif

                    <a href="{{ route('purchasing.grns.index') }}" class="text-xs text-center text-gray-500 hover:text-gray-700 transition-colors mt-2">
                        ← Back to goods receipts
                    </a>
                </div>
            </div>

            {{-- GRN Details Card --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Receipt Summary</h3>
                <div class="grid grid-cols-2 gap-y-3 text-sm">
                    <span class="text-gray-500">GRN Number</span>
                    <span class="text-gray-900 font-mono font-bold text-right">{{ $grn->grn_number }}</span>

                    <span class="text-gray-500">Received Date</span>
                    <span class="text-gray-900 font-medium text-right">{{ $grn->received_at->format('Y-m-d') }}</span>

                    <span class="text-gray-500">Received By</span>
                    <span class="text-gray-900 text-right">{{ $grn->receivedBy?->name ?? '—' }}</span>

                    <span class="text-gray-500">Purchase Order</span>
                    <a href="{{ route('purchasing.orders.show', $grn->purchaseOrder) }}" class="text-brand-600 font-mono font-bold text-right hover:underline">
                        {{ $grn->purchaseOrder->po_number }}
                    </a>
                </div>
            </div>

            {{-- Landed Costs Breakdown --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Landed Costs</h3>
                <div class="grid grid-cols-2 gap-y-3 text-sm">
                    <span class="text-gray-500">Transport Cost</span>
                    <span class="text-gray-900 font-semibold text-right">INR {{ number_format((float) $grn->transport_cost, 2) }}</span>
 
                    <span class="text-gray-500">Labour Cost</span>
                    <span class="text-gray-900 font-semibold text-right">INR {{ number_format((float) $grn->labour_cost, 2) }}</span>
 
                    <div class="col-span-2 border-t border-gray-100 pt-3 flex justify-between font-bold">
                        <span class="text-gray-900">Total Landed Cost</span>
                        <span class="text-brand-700">INR {{ number_format((float) $grn->transport_cost + (float) $grn->labour_cost, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Supplier Summary --}}
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
            </div>
        </div>

    </div>

</x-layouts.app>
