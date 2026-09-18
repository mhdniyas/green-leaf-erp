@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop').' — Settlement Details')

@section('content')
@php
    $currentShopSlugOrId = $currentShop->slug ?: $currentShop->shop_id;
    $period = $settlementDetails['period'] ?? $financialReport['period'] ?? [];
    $summary = $settlementDetails['summary'] ?? [];
    $howCalculated = $settlementDetails['how_calculated'] ?? [];
    $obligations = $settlementDetails['obligations'] ?? [];
    $payments = $settlementDetails['payments'] ?? [];
    $allocations = $settlementDetails['allocations'] ?? [];

    $backToActionCenterUrl = route('admin.cashbook.shop.show', array_filter([
        'shop' => $currentShopSlugOrId,
        'month' => $month ?? request('month'),
        'period_mode' => $periodMode ?? request('period_mode') ?? request('view'),
        'date' => request('date') ?: request('day') ?: ($periodMode === 'day' ? ($periodStart ?? null) : null),
        'from' => request('from') ?: request('custom_from') ?: ($periodMode === 'custom' ? ($periodStart ?? null) : null),
        'to' => request('to') ?: request('custom_to') ?: ($periodMode === 'custom' ? ($periodEnd ?? null) : null),
    ]));

    $statusColor = $summary['status_color'] ?? 'emerald';
    $statusLabel = $summary['status_label'] ?? 'FULLY SETTLED';
@endphp

<div class="mx-auto max-w-7xl space-y-6 pb-16">
    <!-- Top Header Navigation -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
        <div class="flex items-center gap-3">
            <a href="{{ $backToActionCenterUrl }}"
               class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition"
               title="Back to Action Center">
                <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Back to Action Center</span>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-black text-slate-900 tracking-tight uppercase">
                        {{ $currentShop->name ?: 'Shop #'.$currentShop->shop_id }} — SETTLEMENT DETAILS
                    </h1>
                    <span class="text-xs font-bold px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-600 font-mono">
                        {{ $currentShop->code ?: 'SHOP' }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                    {{ $period['label'] ?? 'Settlement Period' }} &bull; {{ $period['formatted_range'] ?? '' }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.cashbook.settings.shop', $currentShopSlugOrId) }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-xs transition"
               title="Cashbook Settings">
                <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>Settings</span>
            </a>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION A: SETTLEMENT SUMMARY ────────────────────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="rounded-3xl border {{ $statusColor === 'amber' ? 'border-amber-200 bg-amber-50/40' : ($statusColor === 'sky' ? 'border-sky-200 bg-sky-50/40' : 'border-emerald-200 bg-emerald-50/40') }} p-6 shadow-xs space-y-5">
        <div class="flex items-center justify-between border-b border-slate-200/80 pb-3">
            <span class="text-xs font-black uppercase tracking-widest text-slate-800">
                Settlement Summary
            </span>
            <span class="inline-flex items-center rounded-md {{ $statusColor === 'amber' ? 'bg-amber-100 text-amber-900 border border-amber-300' : ($statusColor === 'sky' ? 'bg-sky-100 text-sky-900 border border-sky-300' : 'bg-emerald-100 text-emerald-900 border border-emerald-300') }} px-2.5 py-1 text-xs font-black uppercase tracking-wider">
                {{ $statusLabel }}
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <div class="rounded-2xl bg-white p-4 border border-slate-200/70 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Total Sales</span>
                <span class="mt-1 block font-mono text-xl font-bold text-slate-900">
                    ₹{{ number_format((float) ($summary['sales'] ?? 0), 2) }}
                </span>
            </div>

            <div class="rounded-2xl bg-white p-4 border border-slate-200/70 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Period Due</span>
                <a href="#how-period-due-was-calculated" class="mt-1 block font-mono text-xl font-bold text-slate-900 hover:text-emerald-700 transition">
                    ₹{{ number_format((float) ($summary['period_due'] ?? 0), 2) }}
                </a>
            </div>

            <div class="rounded-2xl bg-white p-4 border border-slate-200/70 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Payments Received</span>
                <a href="{{ route('admin.cashbook.shop.history.payments', ['shop' => $currentShopSlugOrId, 'month' => $month]) }}" class="mt-1 block font-mono text-xl font-bold text-slate-900 hover:text-emerald-700 transition">
                    ₹{{ number_format((float) ($summary['received'] ?? 0), 2) }}
                </a>
            </div>

            <div class="rounded-2xl bg-white p-4 border border-slate-200/70 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Allocated</span>
                <a href="{{ route('admin.cashbook.shop.history.allocations', ['shop' => $currentShopSlugOrId, 'month' => $month]) }}" class="mt-1 block font-mono text-xl font-bold text-emerald-700 hover:text-emerald-900 transition">
                    ₹{{ number_format((float) ($summary['allocated'] ?? 0), 2) }}
                </a>
            </div>

            <div class="rounded-2xl bg-white p-4 border border-slate-200/70 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Unallocated</span>
                <a href="{{ route('admin.cashbook.shop.history.payments', ['shop' => $currentShopSlugOrId, 'month' => $month]) }}" class="mt-1 block font-mono text-xl font-bold {{ (float) ($summary['unallocated'] ?? 0) > 0 ? 'text-amber-700' : 'text-slate-600' }} hover:text-amber-900 transition">
                    ₹{{ number_format((float) ($summary['unallocated'] ?? 0), 2) }}
                </a>
            </div>

            <div class="rounded-2xl bg-white p-4 border border-slate-200/70 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Pending Verification</span>
                <a href="{{ route('admin.cashbook.shop.history.banking', ['shop' => $currentShopSlugOrId, 'month' => $month]) }}" class="mt-1 block font-mono text-xl font-bold {{ (float) ($summary['pending_verification'] ?? 0) > 0 ? 'text-sky-700' : 'text-slate-600' }} hover:text-sky-900 transition">
                    ₹{{ number_format((float) ($summary['pending_verification'] ?? 0), 2) }}
                </a>
            </div>

            <div class="rounded-2xl bg-white p-4 border border-slate-200/70 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Floating Cheques</span>
                <a href="{{ route('admin.cashbook.shop.history.cheques', ['shop' => $currentShopSlugOrId, 'status' => 'floating']) }}" class="mt-1 block font-mono text-xl font-bold {{ (float) ($summary['floating_cheques'] ?? 0) > 0 ? 'text-purple-700' : 'text-slate-600' }} hover:text-purple-900 transition">
                    ₹{{ number_format((float) ($summary['floating_cheques'] ?? 0), 2) }}
                </a>
            </div>

            <div class="rounded-2xl bg-white p-4 border-2 {{ $statusColor === 'amber' ? 'border-amber-400 bg-amber-50/50' : ($statusColor === 'sky' ? 'border-sky-400 bg-sky-50/50' : 'border-emerald-400 bg-emerald-50/50') }} shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider {{ $statusColor === 'amber' ? 'text-amber-900' : ($statusColor === 'sky' ? 'text-sky-900' : 'text-emerald-900') }}">
                    Balance After Allocation
                </span>
                <span class="mt-1 block font-mono text-2xl font-black {{ $statusColor === 'amber' ? 'text-amber-950' : ($statusColor === 'sky' ? 'text-sky-950' : 'text-emerald-950') }}">
                    ₹{{ number_format((float) ($summary['remaining_due'] ?? 0), 2) }}
                </span>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION B: HOW PERIOD DUE WAS CALCULATED ────────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div id="how-period-due-was-calculated" class="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-4">
            <div>
                <h2 class="text-base font-black text-slate-900 uppercase tracking-tight">
                    How Period Due Was Calculated
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Source: <span class="font-semibold text-slate-700">{{ $howCalculated['relation_name'] ?? 'Company Payable Settlement' }}</span> (Configured in Cashbook Settings)
                </p>
            </div>
            <div class="text-right font-mono text-xs">
                <span class="text-slate-500">Period Due:</span>
                <span class="font-bold text-slate-900 text-sm ml-1">₹{{ number_format((float) ($howCalculated['formula_net'] ?? 0), 2) }}</span>
            </div>
        </div>

        @if(!empty($howCalculated['items']))
            <div class="overflow-x-auto rounded-2xl border border-slate-100">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">Component / Header</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3 text-center">Effect</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-right">Signed Impact</th>
                            <th class="px-4 py-3">Source Relation</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        @foreach($howCalculated['items'] as $item)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-4 py-3 font-bold text-slate-900">
                                    {{ $item['name'] }}
                                </td>
                                <td class="px-4 py-3 text-slate-500 capitalize">
                                    {{ str_replace('_', ' ', $item['category'] ?? 'General') }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase {{ $item['role'] === 'subtract' ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' }}">
                                        {{ $item['role'] === 'subtract' ? 'Subtract (-)' : 'Add (+)' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono">
                                    ₹{{ number_format((float) $item['amount'], 2) }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold {{ $item['role'] === 'subtract' ? 'text-rose-700' : 'text-emerald-700' }}">
                                    {{ $item['role_symbol'] }}₹{{ number_format((float) $item['amount'], 2) }}
                                </td>
                                <td class="px-4 py-3 text-slate-500 text-[11px]">
                                    {{ $item['source'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-50 border-t border-slate-200 font-mono text-xs font-bold text-slate-900">
                            <td colspan="3" class="px-4 py-3 uppercase tracking-wider text-slate-600">
                                Gross Additions (₹{{ number_format((float) ($howCalculated['gross_additions'] ?? 0), 2) }}) &minus; Gross Deductions (₹{{ number_format((float) ($howCalculated['gross_deductions'] ?? 0), 2) }})
                            </td>
                            <td colspan="2" class="px-4 py-3 text-right text-sm text-slate-950 font-black">
                                Period Due = ₹{{ number_format((float) ($howCalculated['formula_net'] ?? 0), 2) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="p-4 rounded-2xl bg-slate-50 text-slate-500 text-xs italic text-center">
                No custom settlement formula items configured. Period Due reflects raw settlement deltas or gross sales.
            </div>
        @endif
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION C: SETTLEMENT OBLIGATIONS ────────────────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-xs space-y-4" x-data="{ open: true }">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 cursor-pointer" @click="open = !open">
            <div>
                <h2 class="text-base font-black text-slate-900 uppercase tracking-tight flex items-center gap-2">
                    <span>Settlement Obligations</span>
                    <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-mono">
                        {{ count($obligations) }}
                    </span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Individual ledger entries and daily obligations generating Company Payable
                </p>
            </div>
            <button type="button" class="text-slate-400 hover:text-slate-600 transition">
                <svg class="w-5 h-5 transform transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
        </div>

        <div x-show="open" class="space-y-4">
            @if(!empty($obligations))
                <div class="overflow-x-auto rounded-2xl border border-slate-100">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Business Day</th>
                                <th class="px-4 py-3">Source / Category</th>
                                <th class="px-4 py-3">Description</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Allocated</th>
                                <th class="px-4 py-3 text-right">Remaining</th>
                                <th class="px-4 py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            @foreach($obligations as $ob)
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="px-4 py-3 font-mono text-slate-900 font-bold whitespace-nowrap">
                                        {{ $ob['date'] }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-500 whitespace-nowrap">
                                        {{ $ob['business_day'] }}
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-slate-900">
                                        {{ $ob['category'] }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 max-w-xs truncate">
                                        {{ $ob['description'] }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 whitespace-nowrap">
                                        ₹{{ number_format((float) $ob['amount'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase {{ $ob['status_color'] === 'emerald' ? 'bg-emerald-100 text-emerald-800' : ($ob['status_color'] === 'amber' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }}">
                                            {{ $ob['status'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-emerald-700 font-semibold whitespace-nowrap">
                                        ₹{{ number_format((float) $ob['allocated'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold {{ (float) $ob['remaining'] > 0 ? 'text-amber-800' : 'text-slate-500' }} whitespace-nowrap">
                                        ₹{{ number_format((float) $ob['remaining'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <a href="{{ route('admin.cashbook.transaction.show', $ob['id']) }}"
                                           class="inline-flex items-center px-2 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 text-slate-800 text-[11px] font-bold shadow-2xs transition">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-50 border-t border-slate-200 font-mono text-xs font-bold text-slate-900">
                                <td colspan="4" class="px-4 py-3 uppercase tracking-wider text-slate-600">
                                    Total Obligations ({{ count($obligations) }})
                                </td>
                                <td class="px-4 py-3 text-right font-black">
                                    ₹{{ number_format(collect($obligations)->sum('amount'), 2) }}
                                </td>
                                <td></td>
                                <td class="px-4 py-3 text-right font-black text-emerald-700">
                                    ₹{{ number_format(collect($obligations)->sum('allocated'), 2) }}
                                </td>
                                <td class="px-4 py-3 text-right font-black text-amber-800">
                                    ₹{{ number_format(collect($obligations)->sum('remaining'), 2) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="p-6 rounded-2xl bg-slate-50 text-slate-500 text-xs text-center">
                    No settlement obligations recorded in this period.
                </div>
            @endif
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION D: PAYMENTS RECEIVED ─────────────────────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-xs space-y-4" x-data="{ open: true }">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 cursor-pointer" @click="open = !open">
            <div>
                <h2 class="text-base font-black text-slate-900 uppercase tracking-tight flex items-center gap-2">
                    <span>Payments Received</span>
                    <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-mono">
                        {{ count($payments) }}
                    </span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Actual payments recorded as received from this shop
                </p>
            </div>
            <button type="button" class="text-slate-400 hover:text-slate-600 transition">
                <svg class="w-5 h-5 transform transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
        </div>

        <div x-show="open" class="space-y-4">
            @if(!empty($payments))
                <div class="overflow-x-auto rounded-2xl border border-slate-100">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Reference</th>
                                <th class="px-4 py-3">Payment Mode</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                                <th class="px-4 py-3 text-right">Allocated</th>
                                <th class="px-4 py-3 text-right">Unallocated</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3">Company Account</th>
                                <th class="px-4 py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            @foreach($payments as $p)
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="px-4 py-3 font-mono text-slate-900 font-bold whitespace-nowrap">
                                        {{ $p['date'] }}
                                    </td>
                                    <td class="px-4 py-3 font-mono font-semibold text-slate-800">
                                        {{ $p['reference'] }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-700 font-semibold">
                                        {{ $p['mode'] }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 whitespace-nowrap">
                                        ₹{{ number_format((float) $p['amount'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-emerald-700 font-semibold whitespace-nowrap">
                                        ₹{{ number_format((float) $p['allocated'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold {{ (float) $p['unallocated'] > 0 ? 'text-amber-800' : 'text-slate-500' }} whitespace-nowrap">
                                        ₹{{ number_format((float) $p['unallocated'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase {{ $p['status_color'] === 'emerald' ? 'bg-emerald-100 text-emerald-800' : ($p['status_color'] === 'amber' ? 'bg-amber-100 text-amber-800' : ($p['status_color'] === 'sky' ? 'bg-sky-100 text-sky-800' : 'bg-slate-100 text-slate-700')) }}">
                                            {{ $p['status'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ $p['company_account'] }}
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <a href="{{ route('admin.cashbook.shop.history.payments', ['shop' => $currentShopSlugOrId, 'search' => $p['reference'], 'month' => $month]) }}"
                                           class="inline-flex items-center px-2 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 text-slate-800 text-[11px] font-bold shadow-2xs transition">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-50 border-t border-slate-200 font-mono text-xs font-bold text-slate-900">
                                <td colspan="3" class="px-4 py-3 uppercase tracking-wider text-slate-600">
                                    Total Payments Received ({{ count($payments) }})
                                </td>
                                <td class="px-4 py-3 text-right font-black">
                                    ₹{{ number_format(collect($payments)->sum('amount'), 2) }}
                                </td>
                                <td class="px-4 py-3 text-right font-black text-emerald-700">
                                    ₹{{ number_format(collect($payments)->sum('allocated'), 2) }}
                                </td>
                                <td class="px-4 py-3 text-right font-black text-amber-800">
                                    ₹{{ number_format(collect($payments)->sum('unallocated'), 2) }}
                                </td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="p-6 rounded-2xl bg-slate-50 text-slate-500 text-xs text-center">
                    No payments received in this period.
                </div>
            @endif
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION E: ALLOCATION DETAILS ────────────────────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-xs space-y-4" x-data="{ open: true }">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 cursor-pointer" @click="open = !open">
            <div>
                <h2 class="text-base font-black text-slate-900 uppercase tracking-tight flex items-center gap-2">
                    <span>Allocation Details</span>
                    <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-mono">
                        {{ count($allocations) }}
                    </span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Authoritative allocations matching payments against specific settlement obligations
                </p>
            </div>
            <button type="button" class="text-slate-400 hover:text-slate-600 transition">
                <svg class="w-5 h-5 transform transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
        </div>

        <div x-show="open" class="space-y-4">
            @if(!empty($allocations))
                <div class="overflow-x-auto rounded-2xl border border-slate-100">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                <th class="px-4 py-3">Allocated At</th>
                                <th class="px-4 py-3">Payment Reference</th>
                                <th class="px-4 py-3">Business Day / Obligation</th>
                                <th class="px-4 py-3 text-right">Amount Allocated</th>
                                <th class="px-4 py-3">Allocated By</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            @foreach($allocations as $al)
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="px-4 py-3 font-mono text-slate-900 font-bold whitespace-nowrap">
                                        {{ $al['allocated_at'] }}
                                    </td>
                                    <td class="px-4 py-3 font-mono font-semibold text-slate-800">
                                        {{ $al['payment_reference'] }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-800 font-medium">
                                        {{ $al['obligation_label'] }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-emerald-700 whitespace-nowrap">
                                        ₹{{ number_format((float) $al['amount'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ $al['allocated_by'] }}
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-100 text-emerald-800">
                                            {{ $al['status'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <a href="{{ route('admin.cashbook.shop.history.allocations', ['shop' => $currentShopSlugOrId, 'search' => $al['payment_reference'], 'month' => $month]) }}"
                                           class="inline-flex items-center px-2 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 text-slate-800 text-[11px] font-bold shadow-2xs transition">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-50 border-t border-slate-200 font-mono text-xs font-bold text-slate-900">
                                <td colspan="3" class="px-4 py-3 uppercase tracking-wider text-slate-600">
                                    Total Allocated ({{ count($allocations) }})
                                </td>
                                <td class="px-4 py-3 text-right font-black text-emerald-700">
                                    ₹{{ number_format(collect($allocations)->sum('amount'), 2) }}
                                </td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="p-6 rounded-2xl bg-slate-50 text-slate-500 text-xs text-center">
                    No payment allocations recorded for this period.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
