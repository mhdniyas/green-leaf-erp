@extends('shop-owner.layouts.app')

@section('title', 'Shop Purchases')
@section('page_title', 'Purchases')
@section('page_description', 'Manage shop purchases, supplier bills, cash/credit purchases, and vendor liabilities.')
@php($breadcrumbs = [['label' => 'Purchasing', 'url' => route('shop-owner.purchasing.index')], ['label' => 'History']])

@section('content')
<div class="space-y-5">
    {{-- Header Action & Summary Row --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-black text-slate-900">Purchase Invoices</h2>
            <p class="text-xs font-semibold text-slate-500">Shop: <strong class="text-slate-700">{{ $activeShop->name }}</strong> (Active Business Date: {{ $businessDate }})</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('shop-owner.purchasing.reports.vendors') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-black text-slate-700 shadow-xs hover:bg-slate-50 transition">
                <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                Vendor Report
            </a>
            <a href="{{ route('shop-owner.purchasing.create') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white shadow-xs hover:bg-emerald-700 transition">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                New Purchase
            </a>
        </div>
    </div>

    {{-- Metrics Cards --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Purchases</p>
            <p class="mt-1 text-xl font-black text-slate-950">₹{{ number_format($summary['total_amount'], 2) }}</p>
            <p class="mt-0.5 text-[11px] font-semibold text-slate-400">{{ $summary['total_invoices'] }} bills recorded</p>
        </div>
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Cash Purchases</p>
            <p class="mt-1 text-xl font-black text-emerald-900">₹{{ number_format($summary['cash_amount'], 2) }}</p>
            <p class="mt-0.5 text-[11px] font-semibold text-emerald-600">Settled via Shop Cashbook</p>
        </div>
        <div class="rounded-2xl border border-amber-100 bg-amber-50/50 p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Credit Purchases</p>
            <p class="mt-1 text-xl font-black text-amber-900">₹{{ number_format($summary['credit_amount'], 2) }}</p>
            <p class="mt-0.5 text-[11px] font-semibold text-amber-600">Shop Vendor Liabilities</p>
        </div>
        <div class="rounded-2xl border border-rose-100 bg-rose-50/50 p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-rose-700">Credit Outstanding</p>
            <p class="mt-1 text-xl font-black text-rose-900">₹{{ number_format($summary['credit_outstanding'], 2) }}</p>
            <p class="mt-0.5 text-[11px] font-semibold text-rose-600">Unpaid to Suppliers</p>
        </div>
    </div>

    {{-- Filters Card --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
        <form method="GET" action="{{ route('shop-owner.purchasing.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-5">
            <div>
                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">From Date</label>
                <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}" class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-800 focus:bg-white focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">To Date</label>
                <input type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}" class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-800 focus:bg-white focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Supplier</label>
                <select name="supplier_id" class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-800 focus:bg-white focus:outline-none">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(($filters['supplier_id'] ?? null) == $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Payment Method</label>
                <select name="payment_method" class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-800 focus:bg-white focus:outline-none">
                    <option value="">All Methods</option>
                    <option value="Cash" @selected(($filters['payment_method'] ?? null) === 'Cash')>Cash Only</option>
                    <option value="Credit" @selected(($filters['payment_method'] ?? null) === 'Credit')>Credit Only</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="h-9 flex-1 rounded-xl bg-slate-900 px-4 text-xs font-black text-white hover:bg-slate-800 transition">
                    Filter
                </button>
                <a href="{{ route('shop-owner.purchasing.index') }}" class="h-9 inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Invoice Table --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Invoice #</th>
                        <th class="px-4 py-3">Business Date</th>
                        <th class="px-4 py-3">Supplier</th>
                        <th class="px-4 py-3">Purchaser</th>
                        <th class="px-4 py-3">Method</th>
                        <th class="px-4 py-3 text-right">Items</th>
                        <th class="px-4 py-3 text-right">Total Amount</th>
                        <th class="px-4 py-3 text-right">Outstanding</th>
                        <th class="px-4 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                    @forelse($invoices as $invoice)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="px-4 py-3 font-mono font-bold text-slate-950">
                                {{ $invoice->invoice_number }}
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $invoice->invoice_date?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 font-bold text-slate-900">
                                {{ $invoice->supplier?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $invoice->purchaser?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @if(strtolower($invoice->payment_method ?? '') === 'cash')
                                    <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700 border border-emerald-200">Cash</span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-700 border border-amber-200">Credit</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono">
                                {{ $invoice->items_count ?? $invoice->items->count() }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-950">
                                ₹{{ number_format((float) $invoice->total_amount, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold">
                                @if($invoice->shopVendorPayable)
                                    <span class="{{ (float) $invoice->shopVendorPayable->outstanding_amount > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                                        ₹{{ number_format((float) $invoice->shopVendorPayable->outstanding_amount, 2) }}
                                    </span>
                                @else
                                    <span class="text-slate-400">₹0.00</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-700 capitalize">
                                    {{ $invoice->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-slate-400">
                                No shop purchase invoices found matching your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($invoices->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
