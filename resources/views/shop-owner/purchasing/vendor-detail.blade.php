@extends('shop-owner.layouts.app')

@section('title', $supplier->name . ' - Purchase Drill Down')
@section('page_title', $supplier->name)
@section('page_description', 'Detailed purchase bills, item breakdowns, cash payments, and credit liabilities.')
@php($breadcrumbs = [
    ['label' => 'Purchasing', 'url' => route('shop-owner.purchasing.index')],
    ['label' => 'Vendor Report', 'url' => route('shop-owner.purchasing.reports.vendors')],
    ['label' => $supplier->name]
])

@section('content')
<div class="space-y-5">
    {{-- Top Action & Info Bar --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h2 class="text-xl font-black text-slate-950">{{ $supplier->name }}</h2>
                <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-mono font-bold text-slate-600">{{ $supplier->code }}</span>
            </div>
            <p class="text-xs font-semibold text-slate-500 mt-1">Shop: <strong class="text-slate-700">{{ $activeShop->name }}</strong> | Phone: {{ $supplier->phone ?? 'N/A' }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('shop-owner.purchasing.reports.vendors') }}" class="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-black text-slate-700 hover:bg-slate-50 transition">
                ← Back to Vendor Report
            </a>
            <a href="{{ route('shop-owner.purchasing.create', ['supplier_id' => $supplier->id]) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-black text-white shadow-xs hover:bg-emerald-700 transition">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                New Purchase from Vendor
            </a>
        </div>
    </div>

    {{-- Vendor Metrics --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Billed</p>
            <p class="mt-1 text-xl font-black text-slate-950 font-mono">₹{{ number_format($summary['total_amount'], 2) }}</p>
            <p class="mt-0.5 text-[10px] font-semibold text-slate-400">{{ $summary['total_invoices'] }} bills</p>
        </div>
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Cash Purchases</p>
            <p class="mt-1 text-xl font-black text-emerald-900 font-mono">₹{{ number_format($summary['cash_amount'], 2) }}</p>
            <p class="mt-0.5 text-[10px] font-semibold text-emerald-600">Settled</p>
        </div>
        <div class="rounded-2xl border border-amber-100 bg-amber-50/50 p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Credit Purchases</p>
            <p class="mt-1 text-xl font-black text-amber-900 font-mono">₹{{ number_format($summary['credit_amount'], 2) }}</p>
            <p class="mt-0.5 text-[10px] font-semibold text-amber-600">Shop Liabilities</p>
        </div>
        <div class="rounded-2xl border border-cyan-100 bg-cyan-50/50 p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-cyan-700">Credit Paid</p>
            <p class="mt-1 text-xl font-black text-cyan-900 font-mono">₹{{ number_format($summary['credit_paid'], 2) }}</p>
            <p class="mt-0.5 text-[10px] font-semibold text-cyan-600">Paid from Shop</p>
        </div>
        <div class="rounded-2xl border border-rose-100 bg-rose-50/50 p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-rose-700">Current Outstanding</p>
            <p class="mt-1 text-xl font-black text-rose-900 font-mono">₹{{ number_format($summary['credit_outstanding'], 2) }}</p>
            <p class="mt-0.5 text-[10px] font-semibold text-rose-600">Due to Vendor</p>
        </div>
    </div>

    {{-- Individual Purchase Bills List --}}
    <div class="space-y-4">
        <h3 class="text-sm font-black uppercase tracking-wider text-slate-800">Purchase Bills</h3>

        @forelse($invoices as $invoice)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-3">
                        <span class="font-mono text-sm font-black text-slate-950">{{ $invoice->invoice_number }}</span>
                        @if(strtolower($invoice->payment_method ?? '') === 'cash')
                            <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700 border border-emerald-200">Cash Purchase</span>
                        @else
                            <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-700 border border-amber-200">Credit Purchase</span>
                        @endif
                        <span class="text-xs text-slate-400 font-medium">Business Date: {{ $invoice->invoice_date?->format('d M Y') ?? '—' }}</span>
                    </div>
                    <div class="flex items-center gap-4 text-xs">
                        <div>
                            <span class="text-slate-400 font-bold">Total:</span>
                            <span class="font-mono font-black text-slate-950 text-sm">₹{{ number_format((float)$invoice->total_amount, 2) }}</span>
                        </div>
                        @if($invoice->shopVendorPayable)
                            <div>
                                <span class="text-slate-400 font-bold">Outstanding:</span>
                                <span class="font-mono font-black {{ (float)$invoice->shopVendorPayable->outstanding_amount > 0 ? 'text-rose-600' : 'text-emerald-600' }} text-sm">
                                    ₹{{ number_format((float)$invoice->shopVendorPayable->outstanding_amount, 2) }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Items Table --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Product</th>
                                <th class="px-3 py-2 text-right">Quantity</th>
                                <th class="px-3 py-2">Unit</th>
                                <th class="px-3 py-2 text-right">Rate</th>
                                <th class="px-3 py-2 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                            @foreach($invoice->items as $item)
                                <tr>
                                    <td class="px-3 py-2 font-bold text-slate-900">{{ $item->product?->name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ number_format((float)$item->quantity, 2) }}</td>
                                    <td class="px-3 py-2 font-bold text-slate-500">{{ $item->unit ?? 'kg' }}</td>
                                    <td class="px-3 py-2 text-right font-mono">₹{{ number_format((float)$item->unit_price, 2) }}</td>
                                    <td class="px-3 py-2 text-right font-mono font-bold text-slate-950">₹{{ number_format((float)$item->total_price, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-xs text-slate-400">
                No purchase bills found for this vendor.
            </div>
        @endforelse

        @if($invoices->hasPages())
            <div class="pt-2">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
