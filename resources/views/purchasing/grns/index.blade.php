<x-layouts.app title="Goods Receipts (GRN)">

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Goods Received Notes Log</h2>
            <span class="text-xs text-gray-500">{{ $grns->total() }} GRNs</span>
        </div>

        @if($grns->isEmpty())
        <div class="py-16 text-center">
            <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.03 0 1.9.693 2.166 1.638m-7.377 0A48.536 48.536 0 0112 3.75c0 .08-.004.16-.01.238m-2.886 0c.385.023.77.05 1.154.08m-3.456 0A48.108 48.108 0 002.25 6.11v10.39a2.25 2.25 0 002.25 2.25h3" />
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-900">No goods receipts found</p>
            <p class="text-xs text-gray-500 mt-1">Select an approved Purchase Order from the PO log to receive goods.</p>
            @can('purchasing.order.view')
            <a href="{{ route('purchasing.orders.index') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm text-brand-600 font-medium hover:underline">
                View Purchase Orders →
            </a>
            @endcan
        </div>
        @else
        <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
            <table class="min-w-[860px] text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">GRN Number</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">PO Reference</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Supplier</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Received At</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Received By</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Landed Costs</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($grns as $grn)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4 font-mono font-bold text-brand-600">
                            <a href="{{ route('purchasing.grns.show', $grn) }}" class="hover:underline">
                                {{ $grn->grn_number }}
                            </a>
                        </td>
                        <td class="px-6 py-4 font-mono text-gray-600">
                            <a href="{{ route('purchasing.orders.show', $grn->purchaseOrder) }}" class="hover:underline hover:text-brand-600">
                                {{ $grn->purchaseOrder->po_number }}
                            </a>
                        </td>
                        <td class="px-6 py-4 text-gray-900 font-medium">
                            {{ $grn->purchaseOrder->supplier?->name ?? '—' }}
                        </td>
                        <td class="px-6 py-4 text-gray-600">
                            {{ $grn->received_at->format('Y-m-d') }}
                        </td>
                        <td class="px-6 py-4 text-gray-600">
                            {{ $grn->receivedBy?->name ?? '—' }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($grn->status === 'pending_approval')
                                <span class="inline-flex items-center text-xs font-semibold border px-2.5 py-0.5 rounded-full text-amber-700 bg-amber-50 border-amber-200">Pending Approval</span>
                            @elseif($grn->status === 'approved')
                                <span class="inline-flex items-center text-xs font-semibold border px-2.5 py-0.5 rounded-full text-green-700 bg-green-50 border-green-200">Approved</span>
                            @else
                                <span class="inline-flex items-center text-xs font-semibold border px-2.5 py-0.5 rounded-full text-gray-700 bg-gray-50 border-gray-200">{{ ucfirst($grn->status ?? 'Pending') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right font-medium text-gray-900">
                            INR {{ number_format((float) $grn->transport_cost + (float) $grn->labour_cost, 2) }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('purchasing.grns.show', $grn) }}"
                                   class="p-1.5 text-gray-400 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-colors"
                                   title="View GRN">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </a>

                                @php
                                    $hasInvoice = $grn->purchaseInvoices()->exists();
                                @endphp

                                @if(!$hasInvoice)
                                    @can('create', \App\Models\PurchaseInvoice::class)
                                    <a href="{{ route('purchasing.invoices.create', ['goods_received' => $grn]) }}"
                                       class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-brand-700 bg-brand-50 hover:bg-brand-100 rounded-lg transition-colors border border-brand-100 shadow-sm"
                                       title="Match Invoice">
                                        Match Invoice
                                    </a>
                                    @endcan
                                @else
                                    <span class="text-xs text-green-600 font-semibold px-2.5 py-1 rounded bg-green-50">Invoiced</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($grns->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $grns->withQueryString()->links() }}
        </div>
        @endif
        @endif
    </div>

</x-layouts.app>
