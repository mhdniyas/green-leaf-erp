@extends('shop-owner.layouts.app')

@section('title', 'Shop Vendor Purchase Report')
@section('page_title', 'Vendor Purchase Report')
@section('page_description', 'Summary of purchases, cash payments, credit liabilities, and outstanding balance by vendor.')
@php($breadcrumbs = [['label' => 'Purchasing', 'url' => route('shop-owner.purchasing.index')], ['label' => 'Vendor Report']])

@section('content')
<div class="space-y-5">
    {{-- Header Action Row --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-black text-slate-900">Vendor Purchase Summary</h2>
            <p class="text-xs font-semibold text-slate-500">Shop: <strong class="text-slate-700">{{ $activeShop->name }}</strong></p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('shop-owner.purchasing.index') }}" class="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-black text-slate-700 shadow-xs hover:bg-slate-50 transition">
                Purchase History
            </a>
            <a href="{{ route('shop-owner.purchasing.create') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-black text-white shadow-xs hover:bg-emerald-700 transition">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                New Purchase
            </a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Purchases</p>
            <p class="mt-1 text-xl font-black text-slate-950 font-mono">₹{{ number_format($reportData['totals']['total'], 2) }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Cash Total</p>
            <p class="mt-1 text-xl font-black text-emerald-900 font-mono">₹{{ number_format($reportData['totals']['cash'], 2) }}</p>
            <p class="mt-0.5 text-[10px] font-semibold text-emerald-600">Settled via Cashbook</p>
        </div>
        <div class="rounded-2xl border border-amber-100 bg-amber-50/50 p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Credit Total</p>
            <p class="mt-1 text-xl font-black text-amber-900 font-mono">₹{{ number_format($reportData['totals']['credit'], 2) }}</p>
            <p class="mt-0.5 text-[10px] font-semibold text-amber-600">Original Liability</p>
        </div>
        <div class="rounded-2xl border border-cyan-100 bg-cyan-50/50 p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-cyan-700">Credit Paid</p>
            <p class="mt-1 text-xl font-black text-cyan-900 font-mono">₹{{ number_format($reportData['totals']['credit_paid'], 2) }}</p>
            <p class="mt-0.5 text-[10px] font-semibold text-cyan-600">Shop Settlements</p>
        </div>
        <div class="rounded-2xl border border-rose-100 bg-rose-50/50 p-4 shadow-xs">
            <p class="text-[11px] font-bold uppercase tracking-wider text-rose-700">Outstanding</p>
            <p class="mt-1 text-xl font-black text-rose-900 font-mono">₹{{ number_format($reportData['totals']['outstanding'], 2) }}</p>
            <p class="mt-0.5 text-[10px] font-semibold text-rose-600">Shop Vendor Due</p>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
        <form method="GET" action="{{ route('shop-owner.purchasing.reports.vendors') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-5">
            <div>
                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Start Date</label>
                <input type="date" name="start_date" value="{{ $reportData['filters']['start_date'] ?? '' }}" class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-800 focus:bg-white focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">End Date</label>
                <input type="date" name="end_date" value="{{ $reportData['filters']['end_date'] ?? '' }}" class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-800 focus:bg-white focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Supplier</label>
                <select name="supplier_id" class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-800 focus:bg-white focus:outline-none">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}" @selected(($reportData['filters']['supplier_id'] ?? null) == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Payment Filter</label>
                <select name="payment_method" class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-800 focus:bg-white focus:outline-none">
                    <option value="all" @selected(($reportData['filters']['payment_method'] ?? 'all') === 'all')>All Purchases</option>
                    <option value="cash" @selected(($reportData['filters']['payment_method'] ?? null) === 'cash')>Cash Purchases Only</option>
                    <option value="credit" @selected(($reportData['filters']['payment_method'] ?? null) === 'credit')>Credit Purchases Only</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="h-9 flex-1 rounded-xl bg-slate-900 px-4 text-xs font-black text-white hover:bg-slate-800 transition">
                    Filter
                </button>
                <a href="{{ route('shop-owner.purchasing.reports.vendors') }}" class="h-9 inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Report Table --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Vendor</th>
                        <th class="px-4 py-3 text-right">Bills</th>
                        <th class="px-4 py-3 text-right">Cash</th>
                        <th class="px-4 py-3 text-right">Credit</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Credit Paid</th>
                        <th class="px-4 py-3 text-right">Outstanding</th>
                        <th class="px-4 py-3 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                    @forelse($reportData['rows'] as $row)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="px-4 py-3 font-bold text-slate-950">
                                <div>{{ $row['supplier_name'] }}</div>
                                <div class="text-[10px] font-normal text-slate-400">{{ $row['supplier_code'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-600">
                                {{ $row['bill_count'] }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-emerald-700">
                                ₹{{ number_format($row['cash_amount'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-amber-700">
                                ₹{{ number_format($row['credit_amount'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-950">
                                ₹{{ number_format($row['total_amount'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-cyan-700">
                                ₹{{ number_format($row['credit_paid'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold {{ $row['outstanding'] > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                                ₹{{ number_format($row['outstanding'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="{{ route('shop-owner.purchasing.reports.vendor-detail', array_merge(['supplier' => $row['supplier_id']], request()->except('page'))) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-50 transition">
                                    Drill Down
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-slate-400">
                                No vendor purchases found for the selected period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="border-t-2 border-slate-200 bg-slate-50 text-xs font-black">
                    <tr>
                        <td class="px-4 py-3 uppercase tracking-wider text-slate-600">Totals:</td>
                        <td class="px-4 py-3 text-right font-mono">{{ $reportData['totals']['bill_count'] }}</td>
                        <td class="px-4 py-3 text-right font-mono text-emerald-800">₹{{ number_format($reportData['totals']['cash'], 2) }}</td>
                        <td class="px-4 py-3 text-right font-mono text-amber-800">₹{{ number_format($reportData['totals']['credit'], 2) }}</td>
                        <td class="px-4 py-3 text-right font-mono text-slate-950">₹{{ number_format($reportData['totals']['total'], 2) }}</td>
                        <td class="px-4 py-3 text-right font-mono text-cyan-800">₹{{ number_format($reportData['totals']['credit_paid'], 2) }}</td>
                        <td class="px-4 py-3 text-right font-mono text-rose-800">₹{{ number_format($reportData['totals']['outstanding'], 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
