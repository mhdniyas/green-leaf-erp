@extends('admin.cashbook.layouts.app')

@section('content')
<div class="mx-auto max-w-4xl space-y-6 p-6">
    <a class="text-sm font-bold text-emerald-700" href="{{ route('admin.cashbook.finance.direct-sales') }}">Back</a>

    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-slate-900">Direct Sale Details</h1>
                <p class="mt-1 font-mono text-sm text-slate-500">DIRECT-SALE-{{ $sale->id }}</p>
            </div>
            <a class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-700" href="{{ route('admin.cashbook.finance.direct-sales.bill', $sale) }}" target="_blank">View Bill</a>
        </div>
        <div class="mt-4 grid gap-3 text-sm md:grid-cols-2">
            <p><span class="font-bold text-slate-500">Date:</span> {{ $sale->business_date->format('d M Y') }}</p>
            <p><span class="font-bold text-slate-500">Customer:</span> {{ $sale->customer_name ?: '-' }}</p>
            <p><span class="font-bold text-slate-500">Reference:</span> {{ $sale->reference ?: '-' }}</p>
            <p><span class="font-bold text-slate-500">Shop:</span> {{ $sale->shop?->name ?: 'Legacy amount-only sale' }}</p>
            <p><span class="font-bold text-slate-500">Amount:</span> Rs {{ number_format((float) $sale->amount, 2) }}</p>
            <p><span class="font-bold text-slate-500">Status:</span> {{ strtoupper($sale->sale_status ?? 'legacy') }}</p>
            <p><span class="font-bold text-slate-500">Payment:</span> {{ $sale->payment_method ? strtoupper($sale->payment_method) : 'Legacy amount-only' }}</p>
            <p><span class="font-bold text-slate-500">Company account:</span> {{ $sale->companyAccount?->name ?: '-' }}</p>
            <p><span class="font-bold text-slate-500">Journal:</span> {{ $sale->journalEntry?->formatted_reference ?: '-' }}</p>
            <p><span class="font-bold text-slate-500">Cashbook:</span> {{ $sale->cashbookMovement ? strtoupper($sale->cashbookMovement->status).($sale->cashbookMovement->is_finalized ? ' / FINALIZED' : '') : '-' }}</p>
            <p><span class="font-bold text-slate-500">Reconciliation:</span> {{ strtoupper($sale->reconciliation_status ?: 'legacy') }}</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="p-3">Product</th>
                    <th class="p-3">Warehouse</th>
                    <th class="p-3">Qty</th>
                    <th class="p-3">Base qty</th>
                    <th class="p-3">Rate</th>
                    <th class="p-3">Line total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sale->items as $item)
                    <tr class="border-t border-slate-100">
                        <td class="p-3">{{ $item->product?->name }}</td>
                        <td class="p-3">{{ $item->warehouse?->name }}</td>
                        <td class="p-3">{{ number_format((float) $item->quantity, 3) }} {{ strtoupper($item->unit) }}</td>
                        <td class="p-3">{{ number_format((float) $item->base_quantity, 3) }}</td>
                        <td class="p-3">Rs {{ number_format((float) $item->unit_rate, 2) }}</td>
                        <td class="p-3">Rs {{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td class="p-3 text-slate-500" colspan="6">Legacy amount-only sale. No item rows.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
