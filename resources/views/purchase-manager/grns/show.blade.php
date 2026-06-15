@extends('purchase-manager.layouts.app')

@section('title', 'Goods Receipt Details')
@section('page_title', $grn->grn_number)
@section('page_description', 'Review received stock, quantity variance, landed costs, invoice matching, and recheck actions.')

@section('content')
    @php
        $invoice = $grn->purchaseInvoices()->first();
    @endphp

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-6">
            <div class="purchase-manager-panel overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-5">
                    <h2 class="text-lg font-black text-slate-950">Received Items</h2>
                </div>
                <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
                    <table class="min-w-[820px] text-left">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-5 py-4">Product</th>
                                <th class="px-5 py-4 text-right">Ordered</th>
                                <th class="px-5 py-4 text-right">Received</th>
                                <th class="px-5 py-4 text-center">Variance</th>
                                <th class="px-5 py-4 text-right">Allocated Landed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @php
                                $totalReceived = $grn->items->sum(fn ($item) => (float) $item->received_qty);
                            @endphp
                            @foreach ($grn->items as $item)
                                @php
                                    $orderedQty = (float) ($item->purchaseOrderItem?->quantity ?? 0);
                                    $receivedQty = (float) $item->received_qty;
                                    $variance = (float) $item->variance;
                                    $allocatedTransport = $totalReceived > 0 ? ($receivedQty / $totalReceived) * (float) $grn->transport_cost : 0;
                                    $allocatedLabour = $totalReceived > 0 ? ($receivedQty / $totalReceived) * (float) $grn->labour_cost : 0;
                                    $allocatedLanded = $allocatedTransport + $allocatedLabour;
                                    $tone = $variance === 0.0 ? 'emerald' : ($variance < 0 ? 'amber' : 'blue');
                                @endphp
                                <tr>
                                    <td class="px-5 py-4">
                                        <p class="font-bold text-slate-950">{{ $item->product->name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $item->product->sku }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-right text-slate-600">{{ number_format($orderedQty, 3) }} kg</td>
                                    <td class="px-5 py-4 text-right font-bold text-slate-950">{{ number_format($receivedQty, 3) }} kg</td>
                                    <td class="px-5 py-4 text-center"><x-purchase-manager.components.status-badge :label="($variance >= 0 ? '+' : '').number_format($variance, 3).' kg'" :tone="$tone" /></td>
                                    <td class="px-5 py-4 text-right font-semibold text-slate-950">INR {{ number_format($allocatedLanded, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($grn->notes)
                <div class="purchase-manager-panel p-5">
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Receipt Notes</p>
                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $grn->notes }}</p>
                </div>
            @endif
        </div>

        <aside class="space-y-5">
            <div class="purchase-manager-panel p-5">
                <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Workflow Actions</p>
                <div class="mt-4 flex flex-col gap-2">
                    @can('recheck', $grn)
                        <form method="POST" action="{{ route('purchasing.grns.recheck', $grn) }}">
                            @csrf
                            <input type="hidden" name="remarks" value="Admin requested a warehouse recheck.">
                            <x-purchase-manager.components.action-button type="submit" variant="soft-danger" class="w-full">Send For Recheck</x-purchase-manager.components.action-button>
                        </form>
                    @endcan
                    @can('update', $grn)
                        <x-purchase-manager.components.action-button :href="route('purchasing.grns.edit', $grn)" variant="secondary">Update And Resubmit</x-purchase-manager.components.action-button>
                    @endcan
                    @if (! $invoice && $grn->status === 'approved')
                        @can('create', \App\Models\PurchaseInvoice::class)
                            <x-purchase-manager.components.action-button :href="route('purchasing.invoices.create', ['goods_received' => $grn])" variant="primary">Create Invoice</x-purchase-manager.components.action-button>
                        @endcan
                    @elseif ($invoice)
                        <x-purchase-manager.components.action-button :href="route('purchasing.invoices.show', $invoice)" variant="secondary">View Invoice</x-purchase-manager.components.action-button>
                    @endif
                    <x-purchase-manager.components.action-button :href="route('purchasing.grns.index')" variant="secondary">Back to Receipts</x-purchase-manager.components.action-button>
                </div>
            </div>

            <div class="purchase-manager-panel p-5">
                <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Receipt Summary</p>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between"><span class="text-slate-500">Status</span><span class="font-semibold text-slate-950">{{ str($grn->status)->replace('_', ' ')->title() }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-slate-500">Received Date</span><span class="font-semibold text-slate-950">{{ $grn->received_at->format('Y-m-d') }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-slate-500">Received By</span><span class="font-semibold text-slate-950">{{ $grn->receivedBy?->name ?? '—' }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-slate-500">Approved By</span><span class="font-semibold text-slate-950">{{ $grn->approvedBy?->name ?? '—' }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-slate-500">Last Updated By</span><span class="font-semibold text-slate-950">{{ $grn->updatedBy?->name ?? '—' }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-slate-500">Transport</span><span class="font-semibold text-slate-950">INR {{ number_format((float) $grn->transport_cost, 2) }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-slate-500">Labour</span><span class="font-semibold text-slate-950">INR {{ number_format((float) $grn->labour_cost, 2) }}</span></div>
                </div>
            </div>

            @if ($grn->rejection_remarks)
                <div class="purchase-manager-panel p-5">
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-amber-700">Recheck Notes</p>
                    <p class="mt-3 text-sm font-semibold leading-6 text-amber-900">{{ $grn->rejection_remarks }}</p>
                </div>
            @endif

            <div class="purchase-manager-panel p-5">
                <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Purchase Order</p>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between"><span class="text-slate-500">PO Number</span><a href="{{ route('purchasing.orders.show', $grn->purchaseOrder) }}" class="font-mono font-bold text-cyan-700">{{ $grn->purchaseOrder->po_number }}</a></div>
                    <div class="flex items-center justify-between"><span class="text-slate-500">Supplier</span><span class="font-semibold text-slate-950">{{ $grn->purchaseOrder->supplier?->name ?? '—' }}</span></div>
                </div>
            </div>
        </aside>
    </div>
@endsection
