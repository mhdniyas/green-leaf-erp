<x-layouts.app title="Goods Received Note Details — {{ $grn->grn_number }}">

    @if($grn->status === 'rejected')
        <div class="mb-6 bg-red-50 border border-red-200 text-red-800 rounded-2xl p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="space-y-1">
                <h3 class="text-base font-bold flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    Goods Received Note Rejected
                </h3>
                <p class="text-sm text-red-700">
                    <strong>Rejection Remarks:</strong> {{ $grn->rejection_remarks }}
                </p>
                <p class="text-xs text-red-600 font-medium">
                    Returned to warehouse for corrections. Click the edit button to update quantity and resubmit.
                </p>
            </div>
            @can('update', $grn)
                <a href="{{ route('purchasing.grns.edit', $grn) }}" class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-700 transition-colors shadow-sm whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                    </svg>
                    Edit & Correct GRN
                </a>
            @endcan
        </div>
    @endif

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
                    @can('approve', $grn)
                        <form method="POST" action="{{ route('purchasing.grns.approve', $grn) }}" class="w-full">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors shadow-sm cursor-pointer">
                                <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                                Approve GRN
                            </button>
                        </form>
                    @endcan

                    @can('reject', $grn)
                        <button type="button" onclick="document.getElementById('reject-modal').classList.remove('hidden')" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-red-50 border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-100 transition-colors cursor-pointer">
                            <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Reject GRN
                        </button>
                    @endcan

                    @if(!$invoice)
                        @can('create', \App\Models\PurchaseInvoice::class)
                            @if($grn->status === 'approved')
                                <a href="{{ route('purchasing.invoices.create', ['goods_received' => $grn]) }}" 
                                   class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    Create & Match Invoice
                                </a>
                            @endif
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
                    <span class="text-gray-500">Status</span>
                    <div class="text-right">
                        @if($grn->status === 'pending_approval')
                            <span class="inline-flex items-center text-xs font-semibold border px-2.5 py-0.5 rounded-full text-amber-700 bg-amber-50 border-amber-200">Pending Approval</span>
                        @elseif($grn->status === 'approved')
                            <span class="inline-flex items-center text-xs font-semibold border px-2.5 py-0.5 rounded-full text-green-700 bg-green-50 border-green-200">Approved</span>
                        @elseif($grn->status === 'rejected')
                            <span class="inline-flex items-center text-xs font-semibold border px-2.5 py-0.5 rounded-full text-red-700 bg-red-50 border-red-200">Rejected</span>
                        @else
                            <span class="inline-flex items-center text-xs font-semibold border px-2.5 py-0.5 rounded-full text-gray-700 bg-gray-50 border-gray-200">{{ ucfirst($grn->status) }}</span>
                        @endif
                    </div>

                    <span class="text-gray-500">GRN Number</span>
                    <span class="text-gray-900 font-mono font-bold text-right">{{ $grn->grn_number }}</span>

                    <span class="text-gray-500">Received Date</span>
                    <span class="text-gray-900 font-medium text-right">{{ $grn->received_at->format('Y-m-d') }}</span>

                    <span class="text-gray-500">Received By</span>
                    <span class="text-gray-950 text-right">{{ $grn->receivedBy?->name ?? '—' }}</span>

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

    {{-- Rejection Modal --}}
    <div id="reject-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="document.getElementById('reject-modal').classList.add('hidden')"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form method="POST" action="{{ route('purchasing.grns.reject', $grn) }}">
                    @csrf
                    <div class="bg-white px-6 pt-6 pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-bold text-gray-900" id="modal-title">Reject Goods Received Note</h3>
                                <div class="mt-3">
                                    <label for="remarks" class="block text-sm font-medium text-gray-700 mb-1">Rejection Remarks / Correction Remarks</label>
                                    <textarea id="remarks" name="remarks" rows="4" required class="w-full border-gray-300 rounded-xl text-sm p-3 focus:border-brand-500 focus:ring-brand-500 bg-gray-50" placeholder="Please specify why this GRN is rejected and what needs to be corrected..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-4 flex flex-row-reverse gap-2">
                        <button type="submit" class="inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-red-600 text-sm font-semibold text-white hover:bg-red-700 focus:outline-none cursor-pointer">
                            Reject & Return
                        </button>
                        <button type="button" onclick="document.getElementById('reject-modal').classList.add('hidden')" class="inline-flex justify-center rounded-xl border border-gray-200 shadow-sm px-4 py-2 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none cursor-pointer">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</x-layouts.app>
