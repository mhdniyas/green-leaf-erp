@extends('purchase-manager.layouts.app')

@section('title', 'Supplier Bills')
@section('page_title', 'Supplier Bills')
@section('page_description', 'Track matched invoices, payment workflow, and supplier billing status from receipt to settlement.')

@section('content')
    <div class="purchase-manager-panel overflow-hidden">
        @if ($invoices->isEmpty())
            <div class="p-5">
                <x-purchase-manager.components.empty-state
                    title="No purchase invoices found"
                    description="Match a supplier invoice to a goods receipt note after warehouse verification is complete."
                    :actionHref="route('purchasing.grns.index')"
                    actionLabel="Open Goods Receipts"
                />
            </div>
        @else
            <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
                <table class="min-w-[900px] text-left">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Invoice Number</th>
                            <th class="px-5 py-4">Supplier</th>
                            <th class="px-5 py-4">GRN Reference</th>
                            <th class="px-5 py-4">Matched Date</th>
                            <th class="px-5 py-4 text-right">Amount</th>
                            <th class="px-5 py-4 text-right">Paid</th>
                            <th class="px-5 py-4 text-right">Balance</th>
                            <th class="px-5 py-4 text-center">Status</th>
                            <th class="px-5 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @foreach ($invoices as $invoice)
                            @php
                                $balance = max(0, round(((float) $invoice->amount - (float) $invoice->discount_amount) - (float) $invoice->paid_amount, 2));
                            @endphp
                            <tr>
                                <td class="px-5 py-4 font-mono font-bold text-cyan-700"><a href="{{ route('purchasing.invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td>
                                <td class="px-5 py-4 font-semibold text-slate-950">{{ $invoice->supplier?->name ?? '—' }}</td>
                                <td class="px-5 py-4 text-slate-600">
                                    @if ($invoice->goodsReceived)
                                        <a href="{{ route('purchasing.grns.show', $invoice->goodsReceived) }}" class="font-mono text-cyan-700">{{ $invoice->goodsReceived->grn_number }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-slate-600">{{ $invoice->created_at->format('Y-m-d') }}</td>
                                <td class="px-5 py-4 text-right font-bold text-slate-950">INR {{ number_format((float) $invoice->amount, 2) }}</td>
                                <td class="px-5 py-4 text-right font-bold text-emerald-700">₹{{ number_format((float) $invoice->paid_amount, 2) }}</td>
                                <td class="px-5 py-4 text-right font-bold {{ $balance > 0 ? 'text-amber-700' : 'text-slate-950' }}">₹{{ number_format($balance, 2) }}</td>
                                <td class="px-5 py-4 text-center"><span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-black uppercase tracking-[0.14em] {{ $invoice->status->color() }}">{{ $invoice->status->label() }}</span></td>
                                <td class="px-5 py-4 text-right">
                                    <x-purchase-manager.components.action-button :href="route('purchasing.invoices.show', $invoice)" variant="secondary">View</x-purchase-manager.components.action-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
