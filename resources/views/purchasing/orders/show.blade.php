<x-layouts.app title="Purchase Order Details — {{ $order->po_number }}">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left column - items --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Items Card --}}
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Ordered Items</h2>
                    <span class="text-xs text-gray-500 font-mono">{{ $order->items->count() }} items</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/50">
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Product</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Quantity (kg)</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Unit Price</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($order->items as $item)
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
                                    {{ number_format($item->quantity, 3) }} kg
                                </td>
                                <td class="px-6 py-4 text-right text-gray-600">
                                    INR {{ number_format($item->unit_price, 4) }}
                                </td>
                                <td class="px-6 py-4 text-right font-semibold text-gray-900">
                                    INR {{ number_format($item->quantity * $item->unit_price, 2) }}
                                </td>
                            </tr>
                            @endforeach
                            {{-- Totals --}}
                            <tr class="bg-gray-50/50 border-t border-gray-100">
                                <td colspan="3" class="px-6 py-4 text-right font-medium text-gray-500">Total Amount</td>
                                <td class="px-6 py-4 text-right text-lg font-bold text-brand-700">
                                    INR {{ number_format($order->total_amount, 2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Notes Card --}}
            @if($order->notes)
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Order Notes</h3>
                <p class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{{ $order->notes }}</p>
            </div>
            @endif
        </div>

        {{-- Right column - metadata and actions --}}
        <div class="space-y-6">
            {{-- Status & Actions Card --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Order Status</h3>
                    @php
                        $status = $order->status;
                        if ($status->value === 'draft') {
                            $colorClass = 'text-gray-700 bg-gray-100 border-gray-200';
                        } elseif ($status->value === 'approved') {
                            $colorClass = 'text-blue-700 bg-blue-50 border-blue-200';
                        } elseif ($status->value === 'received') {
                            $colorClass = 'text-green-700 bg-green-50 border-green-200';
                        } else { // closed
                            $colorClass = 'text-gray-900 bg-gray-200 border-gray-300';
                        }
                    @endphp
                    <span class="inline-flex items-center text-sm font-semibold border px-3 py-1 rounded-full {{ $colorClass }}">
                        {{ $status->label() }}
                    </span>
                </div>

                {{-- Action buttons --}}
                <div class="flex flex-col gap-2 pt-4 border-t border-gray-100">
                    @if($status->value === 'draft')
                        @can('approve', $order)
                        <form method="POST" action="{{ route('purchasing.orders.approve', $order) }}">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm cursor-pointer">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Approve Order
                            </button>
                        </form>
                        @endcan

                        @can('update', $order)
                        <a href="{{ route('purchasing.orders.edit', $order) }}" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-white border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                            </svg>
                            Edit Order
                        </a>
                        @endcan

                        @can('delete', $order)
                        <form method="POST" action="{{ route('purchasing.orders.destroy', $order) }}" onsubmit="return confirm('Delete Purchase Order?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-red-50 border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-100 transition-colors cursor-pointer">
                                Delete Order
                            </button>
                        </form>
                        @endcan
                    @endif

                    @if($status->value === 'approved')
                        @can('purchasing.grn.create')
                        <a href="{{ route('purchasing.grns.create', ['purchase_order_id' => $order->id]) }}" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124l-.318-5.085a1.5 1.5 0 0 0-1.496-1.408h-2.483c-.767 0-1.42.545-1.5 1.3L12.5 14.25m0 0v-4.5m0 4.5h6.75m-6.75-4.5H8.25M6.75 8.25h.008v.008H6.75V8.25Zm.375 0a.375 0 1 1-.75 0 .375 0 0 1 .75 0Z" />
                            </svg>
                            Receive Goods (GRN)
                        </a>
                        @endcan
                    @endif

                    <a href="{{ route('purchasing.orders.index') }}" class="text-xs text-center text-gray-500 hover:text-gray-700 transition-colors mt-2">
                        ← Back to orders log
                    </a>
                </div>
            </div>

            {{-- Order Summary Card --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Order Details</h3>
                <div class="grid grid-cols-2 gap-y-3 text-sm">
                    <span class="text-gray-500">Order Date</span>
                    <span class="text-gray-900 font-medium text-right">{{ $order->order_date->format('Y-m-d') }}</span>

                    <span class="text-gray-500">PO Number</span>
                    <span class="text-gray-900 font-mono font-bold text-right">{{ $order->po_number }}</span>

                    <span class="text-gray-500">Created By</span>
                    <span class="text-gray-900 text-right">{{ $order->createdBy?->name ?? '—' }}</span>
                </div>
            </div>

            {{-- Supplier summary Card --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Supplier</h3>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
                        <span class="text-amber-700 text-sm font-bold">{{ strtoupper(substr($order->supplier->name, 0, 1)) }}</span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-gray-900">{{ $order->supplier->name }}</p>
                        <p class="text-xs text-gray-400">{{ $order->supplier->type }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-y-3 pt-3 border-t border-gray-100 text-xs">
                    <span class="text-gray-500">Payment Terms</span>
                    <span class="text-gray-800 font-medium text-right">{{ $order->supplier->payment_terms }}</span>

                    <span class="text-gray-500">Quality Score</span>
                    @php
                        $supplierScore = (float) $order->supplier->quality_score;
                        if ($supplierScore >= 90) {
                            $supplierColor = 'text-green-700';
                        } elseif ($supplierScore >= 75) {
                            $supplierColor = 'text-amber-700';
                        } else {
                            $supplierColor = 'text-red-700';
                        }
                    @endphp
                    <span class="font-bold text-right {{ $supplierColor }}">{{ number_format($supplierScore, 2) }} / 100</span>
                </div>
            </div>
        </div>

    </div>

</x-layouts.app>
