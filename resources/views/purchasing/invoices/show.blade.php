<x-layouts.app title="Purchase Invoice Details — {{ $invoice->invoice_number }}">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left column - Details & Linked Items --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Invoice Overview Card --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Invoice Number</h2>
                        <h1 class="text-xl font-bold text-gray-900 mt-1 font-mono">{{ $invoice->invoice_number }}</h1>
                    </div>
                    <div>
                        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Status</h2>
                        <span class="inline-flex items-center text-sm font-semibold border px-3 py-1 rounded-full {{ $invoice->status->color() }}">
                            {{ $invoice->status->label() }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-6 border-t border-gray-100 text-sm">
                    <div>
                        <span class="text-gray-500 block mb-1">Invoice Amount</span>
                        <span class="text-lg font-bold text-brand-700">INR {{ number_format($invoice->amount, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 block mb-1">Matched Date</span>
                        <span class="text-gray-900 font-medium">{{ $invoice->created_at->format('Y-m-d H:i') }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 block mb-1">Supplier Invoice Ref</span>
                        <span class="text-gray-900 font-medium font-mono">{{ $invoice->invoice_number }}</span>
                    </div>
                </div>

                @if($invoice->notes)
                <div class="pt-6 border-t border-gray-100">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Invoice Notes</h3>
                    <p class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{{ $invoice->notes }}</p>
                </div>
                @endif
            </div>

            {{-- Matched GRN Items Card --}}
            @if($invoice->goodsReceived)
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                    <h2 class="text-sm font-semibold text-gray-900">Matched Goods Received Note Items</h2>
                    <a href="{{ route('purchasing.grns.show', $invoice->goodsReceived) }}" class="text-xs font-bold text-brand-600 hover:underline font-mono">
                        {{ $invoice->goodsReceived->grn_number }}
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-gray-500 bg-gray-50/20 text-xs uppercase tracking-wide">
                                <th class="px-6 py-3 text-left font-semibold">Product</th>
                                <th class="px-6 py-3 text-right font-semibold">Received Qty</th>
                                <th class="px-6 py-3 text-right font-semibold">PO Unit Price</th>
                                <th class="px-6 py-3 text-right font-semibold">Line Material Cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @php
                                $expectedMaterialAmount = 0.00;
                            @endphp
                            @foreach($invoice->goodsReceived->items as $item)
                                @php
                                    $qty = (float) $item->received_qty;
                                    $price = (float) ($item->purchaseOrderItem?->unit_price ?? 0);
                                    $lineCost = $qty * $price;
                                    $expectedMaterialAmount += $lineCost;
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
                                    <td class="px-6 py-4 text-right text-gray-950 font-medium">
                                        {{ number_format($qty, 3) }} kg
                                    </td>
                                    <td class="px-6 py-4 text-right text-gray-600">
                                        INR {{ number_format($price, 4) }}
                                    </td>
                                    <td class="px-6 py-4 text-right font-semibold text-gray-900">
                                        INR {{ number_format($lineCost, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="bg-gray-50/50 font-semibold border-t border-gray-100">
                                <td colspan="3" class="px-6 py-4 text-right text-gray-500">Expected Materials Cost (excluding Landed Costs)</td>
                                <td class="px-6 py-4 text-right text-gray-900">INR {{ number_format($expectedMaterialAmount, 2) }}</td>
                            </tr>
                            <tr class="bg-gray-50/50 font-semibold">
                                <td colspan="3" class="px-6 py-4 text-right text-gray-500">GRN Landed Costs (Transport + Labour)</td>
                                <td class="px-6 py-4 text-right text-gray-900">INR {{ number_format((float) $invoice->goodsReceived->transport_cost + (float) $invoice->goodsReceived->labour_cost, 2) }}</td>
                            </tr>
                            <tr class="bg-brand-50/50 font-bold border-t border-brand-100">
                                <td colspan="3" class="px-6 py-4 text-right text-brand-900">Expected Invoice Total (Inc. Landed Costs)</td>
                                <td class="px-6 py-4 text-right text-brand-700 text-base">INR {{ number_format($expectedMaterialAmount + (float) $invoice->goodsReceived->transport_cost + (float) $invoice->goodsReceived->labour_cost, 2) }}</td>
                            </tr>
                            <tr class="bg-gray-50 font-bold border-t border-gray-200">
                                <td colspan="3" class="px-6 py-4 text-right text-gray-900">Actual Invoice Amount Registered</td>
                                <td class="px-6 py-4 text-right text-gray-900 text-base">INR {{ number_format($invoice->amount, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>

        {{-- Right column - Status Actions & References --}}
        <div class="space-y-6">
            {{-- Status Update Card --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Workflow Actions</h3>
                    <p class="text-xs text-gray-500 mt-1">Manage payment status transitions for this invoice.</p>
                </div>

                <div class="flex flex-col gap-2 pt-4 border-t border-gray-100">
                    @can('update', $invoice)
                        @if($invoice->status->value === 'pending')
                            <form method="POST" action="{{ route('purchasing.invoices.update-status', $invoice) }}">
                                @csrf
                                <input type="hidden" name="status" value="approved">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition-colors shadow-sm cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                    Approve for Payment
                                </button>
                            </form>
                        @endif

                        @if($invoice->status->value === 'pending' || $invoice->status->value === 'approved')
                            <form method="POST" action="{{ route('purchasing.invoices.update-status', $invoice) }}">
                                @csrf
                                <input type="hidden" name="status" value="paid">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-green-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-700 transition-colors shadow-sm cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-1.958-.59c-1.171-.879-1.171-2.303 0-3.182 1.172-.879 3.07-.879 4.242 0L15 9M9 5.25h6" />
                                    </svg>
                                    Mark as Paid
                                </button>
                            </form>
                        @endif

                        @if($invoice->status->value === 'approved' || $invoice->status->value === 'paid')
                            <form method="POST" action="{{ route('purchasing.invoices.update-status', $invoice) }}">
                                @csrf
                                <input type="hidden" name="status" value="pending">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-white border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors cursor-pointer">
                                    Revert to Pending
                                </button>
                            </form>
                        @endif
                    @endcan

                    <a href="{{ route('purchasing.invoices.index') }}" class="text-xs text-center text-gray-500 hover:text-gray-700 transition-colors mt-2">
                        ← Back to invoices
                    </a>
                </div>
            </div>

            {{-- Supplier summary Card --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Supplier</h3>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
                        <span class="text-amber-700 text-sm font-bold">{{ strtoupper(substr($invoice->supplier->name, 0, 1)) }}</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-gray-900">{{ $invoice->supplier->name }}</p>
                        <p class="text-xs text-gray-400">{{ $invoice->supplier->type }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-y-3 pt-3 border-t border-gray-100 text-xs">
                    <span class="text-gray-500">Payment Terms</span>
                    <span class="text-gray-800 font-medium text-right">{{ $invoice->supplier->payment_terms }}</span>
                </div>
            </div>

            {{-- PO Reference Summary --}}
            @if($invoice->goodsReceived && $invoice->goodsReceived->purchaseOrder)
            <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Purchase Order</h3>
                <div class="grid grid-cols-2 gap-y-3 text-sm">
                    <span class="text-gray-500">PO Number</span>
                    <a href="{{ route('purchasing.orders.show', $invoice->goodsReceived->purchaseOrder) }}" class="text-brand-600 font-mono font-bold text-right hover:underline">
                        {{ $invoice->goodsReceived->purchaseOrder->po_number }}
                    </a>

                    <span class="text-gray-500">PO Status</span>
                    <span class="inline-flex items-center self-end px-2.5 py-0.5 text-xs font-semibold text-green-700 bg-green-50 border border-green-200 rounded-full">
                        {{ $invoice->goodsReceived->purchaseOrder->status->label() }}
                    </span>
                </div>
            </div>
            @endif
        </div>

    </div>

</x-layouts.app>
