@extends('purchase-manager.layouts.app')

@section('title', 'Goods Receipts')
@section('page_title', 'Goods Receipts')
@section('page_description', 'Check received quantities against purchase orders, view receiver-approved receipts, and track recheck requests.')

@section('content')
    {{-- Tabs to switch between Regular and Add-on GRNs --}}
    <div class="mb-5 flex gap-2 border-b border-slate-200 dark:border-slate-800 pb-px">
        <a href="{{ route('purchasing.grns.index', ['tab' => 'regular']) }}"
            class="px-4 py-2.5 text-xs font-black uppercase tracking-wider border-b-2 {{ $type === 'regular' ? 'border-cyan-600 text-cyan-600 border-solid' : 'border-transparent text-slate-405 hover:text-slate-600' }} transition no-underline">
            Regular Receipts
        </a>
        <a href="{{ route('purchasing.grns.index', ['tab' => 'addon']) }}"
            class="px-4 py-2.5 text-xs font-black uppercase tracking-wider border-b-2 {{ $type === 'addon' ? 'border-cyan-600 text-cyan-600 border-solid' : 'border-transparent text-slate-405 hover:text-slate-600' }} transition no-underline">
            Add-on Receipts
        </a>
    </div>

    <div class="purchase-manager-panel overflow-hidden">
        @if ($grns->isEmpty())
            <div class="p-5">
                <x-purchase-manager.components.empty-state
                    title="No goods receipts found"
                    description="{{ $type === 'addon' ? 'No add-on supplier receipts recorded.' : 'Create a goods receipt from a sent purchase order when stock reaches the warehouse.' }}"
                    :actionHref="route('purchasing.orders.index')"
                    actionLabel="Open Purchase Orders"
                />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                        <tr>
                            <th class="px-5 py-4">GRN Number</th>
                            <th class="px-5 py-4">PO Reference</th>
                            <th class="px-5 py-4">Supplier</th>
                            <th class="px-5 py-4">Received Date</th>
                            <th class="px-5 py-4">Received By</th>
                            <th class="px-5 py-4">Approved By</th>
                            <th class="px-5 py-4">Last Updated By</th>
                            <th class="px-5 py-4 text-center">Status</th>
                            <th class="px-5 py-4 text-right">Landed Cost</th>
                            <th class="px-5 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @foreach ($grns as $grn)
                            @php
                                $tone = $grn->status === 'approved' ? 'emerald' : ($grn->status === 'recheck_required' ? 'amber' : 'slate');
                            @endphp
                            <tr>
                                <td class="px-5 py-4 font-mono font-bold text-cyan-700"><a href="{{ route('purchasing.grns.show', $grn) }}">{{ $grn->grn_number }}</a></td>
                                <td class="px-5 py-4 font-mono text-slate-600"><a href="{{ route('purchasing.orders.show', $grn->purchaseOrder) }}">{{ $grn->purchaseOrder->po_number }}</a></td>
                                <td class="px-5 py-4 font-semibold text-slate-900">{{ $grn->purchaseOrder->supplier?->name ?? '—' }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $grn->received_at->format('Y-m-d') }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $grn->receivedBy?->name ?? '—' }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $grn->approvedBy?->name ?? '—' }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $grn->updatedBy?->name ?? '—' }}</td>
                                <td class="px-5 py-4 text-center"><x-purchase-manager.components.status-badge :label="str($grn->status ?? 'pending')->replace('_', ' ')->title()" :tone="$tone" /></td>
                                <td class="px-5 py-4 text-right font-bold text-slate-950">INR {{ number_format((float) $grn->transport_cost + (float) $grn->labour_cost, 2) }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <x-purchase-manager.components.action-button :href="route('purchasing.grns.show', $grn)" variant="secondary">View</x-purchase-manager.components.action-button>
                                        @if (! $grn->purchaseInvoices()->exists())
                                            @can('create', \App\Models\PurchaseInvoice::class)
                                                <x-purchase-manager.components.action-button :href="route('purchasing.invoices.create', ['goods_received' => $grn])" variant="primary">Match Invoice</x-purchase-manager.components.action-button>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($grns->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">
                    {{ $grns->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
