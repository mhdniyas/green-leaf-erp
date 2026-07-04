@extends('purchase-manager.layouts.app')

@section('title', 'Supplier Bill Details')
@section('page_title', $invoice->invoice_number)
@section('page_description', 'Review invoice matching, supplier billing amount, GRN linkage, and payment workflow actions.')

@section('content')
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-6">
            <div class="purchase-manager-panel p-5">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Invoice Number</p>
                        <p class="mt-2 font-mono text-2xl font-black text-slate-950">{{ $invoice->invoice_number }}</p>
                    </div>
                    <span class="inline-flex rounded-full border px-3 py-1 text-[11px] font-black uppercase tracking-[0.14em] {{ $invoice->status->color() }}">{{ $invoice->status->label() }}</span>
                </div>
                <div class="mt-5 grid gap-4 md:grid-cols-3">
                    <div><p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Amount</p><p class="mt-2 text-xl font-black text-slate-950">INR {{ number_format($invoice->amount, 2) }}</p></div>
                    <div><p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Matched Date</p><p class="mt-2 text-sm font-semibold text-slate-950">{{ $invoice->created_at->format('Y-m-d H:i') }}</p></div>
                    <div><p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Supplier</p><p class="mt-2 text-sm font-semibold text-slate-950">{{ $invoice->supplier->name }}</p></div>
                </div>
                @if ($invoice->notes)
                    <div class="mt-5 border-t border-slate-200 pt-5">
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Notes</p>
                        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $invoice->notes }}</p>
                    </div>
                @endif
            </div>

            @if ($invoice->goodsReceived)
                <div class="purchase-manager-panel overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-5">
                        <h2 class="text-lg font-black text-slate-950">Matched GRN Items</h2>
                    </div>
                    <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
                        <table class="min-w-[760px] text-left">
                            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                                <tr>
                                    <th class="px-5 py-4">Product</th>
                                    <th class="px-5 py-4 text-right">Received Qty</th>
                                    <th class="px-5 py-4 text-right">PO Unit Price</th>
                                    <th class="px-5 py-4 text-right">Line Material Cost</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @foreach ($invoice->goodsReceived->items as $item)
                                    @php
                                        $qty = (float) $item->received_qty;
                                        $price = (float) ($item->purchaseOrderItem?->unit_price ?? 0);
                                    @endphp
                                    <tr>
                                        <td class="px-5 py-4">
                                            <p class="font-bold text-slate-950">{{ $item->product->name }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $item->product->sku }}</p>
                                        </td>
                                        <td class="px-5 py-4 text-right font-semibold text-slate-950">{{ number_format($qty, 3) }} kg</td>
                                        <td class="px-5 py-4 text-right text-slate-600">INR {{ number_format($price, 4) }}</td>
                                        <td class="px-5 py-4 text-right font-bold text-slate-950">INR {{ number_format($qty * $price, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <aside class="space-y-5">
            <div class="purchase-manager-panel p-5">
                <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Workflow Actions</p>
                <div class="mt-4 flex flex-col gap-2">
                    @can('update', $invoice)
                        @if ($invoice->status->value === 'pending')
                            <form method="POST" action="{{ route('purchasing.invoices.update-status', $invoice) }}">
                                @csrf
                                <input type="hidden" name="status" value="approved">
                                <x-purchase-manager.components.action-button type="submit" variant="primary" class="w-full">Approve for Payment</x-purchase-manager.components.action-button>
                            </form>
                        @endif
                        @if (in_array($invoice->status->value, ['pending', 'approved']))
                            <form method="POST" action="{{ route('purchasing.invoices.update-status', $invoice) }}">
                                @csrf
                                <input type="hidden" name="status" value="paid">
                                <x-purchase-manager.components.action-button type="submit" variant="success" class="w-full">Mark as Paid</x-purchase-manager.components.action-button>
                            </form>
                        @endif
                        @if (in_array($invoice->status->value, ['approved', 'paid']))
                            <form method="POST" action="{{ route('purchasing.invoices.update-status', $invoice) }}">
                                @csrf
                                <input type="hidden" name="status" value="pending">
                                <x-purchase-manager.components.action-button type="submit" variant="secondary" class="w-full">Revert to Pending</x-purchase-manager.components.action-button>
                            </form>
                        @endif
                    @endcan
                    <x-purchase-manager.components.action-button :href="route('purchasing.invoices.index')" variant="secondary">Back to Invoices</x-purchase-manager.components.action-button>
                </div>
            </div>

            <div class="purchase-manager-panel p-5">
                <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">References</p>
                <div class="mt-4 space-y-3 text-sm">
                    @if ($invoice->goodsReceived)
                        <div class="flex items-center justify-between"><span class="text-slate-500">GRN</span><a href="{{ route('purchasing.grns.show', $invoice->goodsReceived) }}" class="font-mono font-bold text-cyan-700">{{ $invoice->goodsReceived->grn_number }}</a></div>
                    @endif
                    @if ($invoice->goodsReceived?->purchaseOrder)
                        <div class="flex items-center justify-between"><span class="text-slate-500">PO</span><a href="{{ route('purchasing.orders.show', $invoice->goodsReceived->purchaseOrder) }}" class="font-mono font-bold text-cyan-700">{{ $invoice->goodsReceived->purchaseOrder->po_number }}</a></div>
                    @endif
                    <div class="flex items-center justify-between"><span class="text-slate-500">Payment Terms</span><span class="font-semibold text-slate-950">{{ $invoice->supplier->payment_terms }}</span></div>
                </div>
            </div>
        </aside>
    </div>
@endsection
