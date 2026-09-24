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
    @php
        $companyPayableSettingsUrl = !empty($howCalculated['public_uuid'])
            ? route('admin.cashbook.settings.shop.settlements.edit', [$currentShopSlugOrId, $howCalculated['public_uuid']])
            : route('admin.cashbook.settings.shop.settlements.index', $currentShopSlugOrId);
    @endphp
    <div id="how-period-due-was-calculated" class="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2.5 flex-wrap">
                    <h2 class="text-base font-black text-slate-900 uppercase tracking-tight">
                        How Period Due Was Calculated
                    </h2>
                    <a href="{{ $companyPayableSettingsUrl }}"
                       class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-bold transition shadow-2xs"
                       title="Open Company Payable Settings">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>Company Payable Settings</span>
                    </a>
                </div>
                <p class="text-xs text-slate-500">
                    Source: <span class="font-semibold text-slate-700">{{ $howCalculated['relation_name'] ?? 'Company Payable Settlement' }}</span> (Configured in Cashbook Settings)
                </p>
            </div>
            <div class="text-right font-mono text-xs shrink-0">
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
    <div class="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-xs space-y-4"
         x-data="{
             open: true,
             rawItems: @js($obligations),
             categoryFilter: 'all',
             statusFilter: 'all',
             searchQuery: '',
             sortCol: 'raw_date',
             sortAsc: true,
             formatCurrency(val) {
                 return '₹' + Number(val || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
             },
             sortBy(col) {
                 if (this.sortCol === col) {
                     this.sortAsc = !this.sortAsc;
                 } else {
                     this.sortCol = col;
                     this.sortAsc = (col === 'amount' || col === 'allocated' || col === 'remaining') ? false : true;
                 }
             },
             get categories() {
                 return [...new Set(this.rawItems.map(i => i.category))].filter(Boolean).sort();
             },
             get statuses() {
                 return [...new Set(this.rawItems.map(i => i.status))].filter(Boolean).sort();
             },
             get filteredItems() {
                 let items = this.rawItems.filter(item => {
                     if (this.categoryFilter !== 'all' && item.category !== this.categoryFilter) return false;
                     if (this.statusFilter !== 'all' && item.status !== this.statusFilter) return false;
                     if (this.searchQuery.trim() !== '') {
                         const q = this.searchQuery.toLowerCase();
                         const match = (item.date || '').toLowerCase().includes(q)
                             || (item.business_day || '').toLowerCase().includes(q)
                             || (item.category || '').toLowerCase().includes(q)
                             || (item.description || '').toLowerCase().includes(q)
                             || (item.reference_id || '').toString().toLowerCase().includes(q);
                         if (!match) return false;
                     }
                     return true;
                 });

                 return items.sort((a, b) => {
                     let valA = a[this.sortCol];
                     let valB = b[this.sortCol];
                     if (this.sortCol === 'amount' || this.sortCol === 'allocated' || this.sortCol === 'remaining') {
                         valA = parseFloat(valA) || 0;
                         valB = parseFloat(valB) || 0;
                     } else {
                         valA = (valA || '').toString().toLowerCase();
                         valB = (valB || '').toString().toLowerCase();
                     }
                     if (valA < valB) return this.sortAsc ? -1 : 1;
                     if (valA > valB) return this.sortAsc ? 1 : -1;
                     return 0;
                 });
             },
             get totalAmount() {
                 return this.filteredItems.reduce((sum, i) => sum + (parseFloat(i.amount) || 0), 0);
             },
             get totalAllocated() {
                 return this.filteredItems.reduce((sum, i) => sum + (parseFloat(i.allocated) || 0), 0);
             },
             get totalRemaining() {
                 return this.filteredItems.reduce((sum, i) => sum + (parseFloat(i.remaining) || 0), 0);
             },
             resetFilters() {
                 this.categoryFilter = 'all';
                 this.statusFilter = 'all';
                 this.searchQuery = '';
                 this.sortCol = 'raw_date';
                 this.sortAsc = true;
             }
         }">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 cursor-pointer" @click="open = !open">
            <div>
                <h2 class="text-base font-black text-slate-900 uppercase tracking-tight flex items-center gap-2">
                    <span>Settlement Obligations</span>
                    <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-mono">
                        <span x-text="filteredItems.length"></span><span x-show="filteredItems.length !== rawItems.length" x-text="' / ' + rawItems.length" class="text-slate-400"></span>
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
                <!-- Filter Controls Toolbar -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 bg-slate-50/80 p-3 rounded-2xl border border-slate-200/80 text-xs">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <!-- Category Filter -->
                        <div class="flex items-center gap-1.5">
                            <span class="font-bold text-slate-500">Category:</span>
                            <select x-model="categoryFilter"
                                    class="rounded-xl border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-800 shadow-2xs focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="all">All Categories</option>
                                <template x-for="cat in categories" :key="cat">
                                    <option :value="cat" x-text="cat"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div class="flex items-center gap-1.5">
                            <span class="font-bold text-slate-500">Status:</span>
                            <select x-model="statusFilter"
                                    class="rounded-xl border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-800 shadow-2xs focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="all">All Statuses</option>
                                <template x-for="st in statuses" :key="st">
                                    <option :value="st" x-text="st"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Reset Filters Button -->
                        <button type="button"
                                x-show="categoryFilter !== 'all' || statusFilter !== 'all' || searchQuery !== '' || sortCol !== 'raw_date' || !sortAsc"
                                @click="resetFilters()"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold transition">
                            <span>Reset</span>
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div class="relative w-full md:w-64">
                        <input type="text"
                               x-model="searchQuery"
                               placeholder="Search obligations..."
                               class="w-full rounded-xl border border-slate-200 bg-white pl-8 pr-3 py-1 text-xs font-bold text-slate-800 shadow-2xs focus:ring-indigo-500 focus:border-indigo-500">
                        <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto rounded-2xl border border-slate-100">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                <th class="px-4 py-3 cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('raw_date')">
                                    <div class="flex items-center gap-1">
                                        <span>Date</span>
                                        <span class="text-[10px]" :class="sortCol === 'raw_date' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'raw_date' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'raw_date' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'raw_date'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('business_day')">
                                    <div class="flex items-center gap-1">
                                        <span>Business Day</span>
                                        <span class="text-[10px]" :class="sortCol === 'business_day' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'business_day' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'business_day' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'business_day'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('category')">
                                    <div class="flex items-center gap-1">
                                        <span>Source / Category</span>
                                        <span class="text-[10px]" :class="sortCol === 'category' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'category' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'category' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'category'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('description')">
                                    <div class="flex items-center gap-1">
                                        <span>Description</span>
                                        <span class="text-[10px]" :class="sortCol === 'description' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'description' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'description' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'description'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-right cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('amount')">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Amount</span>
                                        <span class="text-[10px]" :class="sortCol === 'amount' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'amount' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'amount' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'amount'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-center cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('status')">
                                    <div class="flex items-center justify-center gap-1">
                                        <span>Status</span>
                                        <span class="text-[10px]" :class="sortCol === 'status' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'status' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'status' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'status'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-right cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('allocated')">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Allocated</span>
                                        <span class="text-[10px]" :class="sortCol === 'allocated' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'allocated' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'allocated' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'allocated'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-right cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('remaining')">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Remaining</span>
                                        <span class="text-[10px]" :class="sortCol === 'remaining' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'remaining' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'remaining' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'remaining'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            <template x-for="ob in filteredItems" :key="ob.id">
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="px-4 py-3 font-mono text-slate-900 font-bold whitespace-nowrap" x-text="ob.date"></td>
                                    <td class="px-4 py-3 text-slate-500 whitespace-nowrap" x-text="ob.business_day"></td>
                                    <td class="px-4 py-3 font-semibold text-slate-900" x-text="ob.category"></td>
                                    <td class="px-4 py-3 text-slate-600 max-w-xs truncate" x-text="ob.description"></td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 whitespace-nowrap" x-text="formatCurrency(ob.amount)"></td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase"
                                              :class="{
                                                  'bg-emerald-100 text-emerald-800': ob.status_color === 'emerald',
                                                  'bg-amber-100 text-amber-800': ob.status_color === 'amber',
                                                  'bg-slate-100 text-slate-700': ob.status_color !== 'emerald' && ob.status_color !== 'amber'
                                              }"
                                              x-text="ob.status">
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-emerald-700 font-semibold whitespace-nowrap" x-text="formatCurrency(ob.allocated)"></td>
                                    <td class="px-4 py-3 text-right font-mono font-bold whitespace-nowrap"
                                        :class="parseFloat(ob.remaining) > 0 ? 'text-amber-800' : 'text-slate-500'"
                                        x-text="formatCurrency(ob.remaining)"></td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <a :href="'{{ url('admin/cashbook/transactions') }}/' + ob.id"
                                           class="inline-flex items-center px-2 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 text-slate-800 text-[11px] font-bold shadow-2xs transition">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filteredItems.length === 0">
                                <td colspan="9" class="p-8 text-center text-slate-400 italic">
                                    No obligations match the selected filters.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-50 border-t border-slate-200 font-mono text-xs font-bold text-slate-900">
                                <td colspan="4" class="px-4 py-3 uppercase tracking-wider text-slate-600">
                                    <span>Total Obligations</span>
                                    <span class="font-normal text-slate-400 ml-1" x-text="'(' + filteredItems.length + ' records)'"></span>
                                </td>
                                <td class="px-4 py-3 text-right font-black" x-text="formatCurrency(totalAmount)"></td>
                                <td></td>
                                <td class="px-4 py-3 text-right font-black text-emerald-700" x-text="formatCurrency(totalAllocated)"></td>
                                <td class="px-4 py-3 text-right font-black text-amber-800" x-text="formatCurrency(totalRemaining)"></td>
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
    <div class="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-xs space-y-4"
         x-data="{
             open: true,
             rawItems: @js($payments),
             modeFilter: 'all',
             statusFilter: 'all',
             searchQuery: '',
             sortCol: 'raw_date',
             sortAsc: true,
             formatCurrency(val) {
                 return '₹' + Number(val || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
             },
             sortBy(col) {
                 if (this.sortCol === col) {
                     this.sortAsc = !this.sortAsc;
                 } else {
                     this.sortCol = col;
                     this.sortAsc = (col === 'amount' || col === 'allocated' || col === 'unallocated') ? false : true;
                 }
             },
             get modes() {
                 return [...new Set(this.rawItems.map(i => i.mode))].filter(Boolean).sort();
             },
             get statuses() {
                 return [...new Set(this.rawItems.map(i => i.status))].filter(Boolean).sort();
             },
             get filteredItems() {
                 let items = this.rawItems.filter(item => {
                     if (this.modeFilter !== 'all' && item.mode !== this.modeFilter) return false;
                     if (this.statusFilter !== 'all' && item.status !== this.statusFilter) return false;
                     if (this.searchQuery.trim() !== '') {
                         const q = this.searchQuery.toLowerCase();
                         const match = (item.date || '').toLowerCase().includes(q)
                             || (item.reference || '').toLowerCase().includes(q)
                             || (item.mode || '').toLowerCase().includes(q)
                             || (item.company_account || '').toLowerCase().includes(q)
                             || (item.cheque_number || '').toString().toLowerCase().includes(q);
                         if (!match) return false;
                     }
                     return true;
                 });

                 return items.sort((a, b) => {
                     let valA = a[this.sortCol];
                     let valB = b[this.sortCol];
                     if (this.sortCol === 'amount' || this.sortCol === 'allocated' || this.sortCol === 'unallocated') {
                         valA = parseFloat(valA) || 0;
                         valB = parseFloat(valB) || 0;
                     } else {
                         valA = (valA || '').toString().toLowerCase();
                         valB = (valB || '').toString().toLowerCase();
                     }
                     if (valA < valB) return this.sortAsc ? -1 : 1;
                     if (valA > valB) return this.sortAsc ? 1 : -1;
                     return 0;
                 });
             },
             get totalAmount() {
                 return this.filteredItems.reduce((sum, i) => sum + (parseFloat(i.amount) || 0), 0);
             },
             get totalAllocated() {
                 return this.filteredItems.reduce((sum, i) => sum + (parseFloat(i.allocated) || 0), 0);
             },
             get totalUnallocated() {
                 return this.filteredItems.reduce((sum, i) => sum + (parseFloat(i.unallocated) || 0), 0);
             },
             resetFilters() {
                 this.modeFilter = 'all';
                 this.statusFilter = 'all';
                 this.searchQuery = '';
                 this.sortCol = 'raw_date';
                 this.sortAsc = true;
             }
         }">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 cursor-pointer" @click="open = !open">
            <div>
                <h2 class="text-base font-black text-slate-900 uppercase tracking-tight flex items-center gap-2">
                    <span>Payments Received</span>
                    <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-mono">
                        <span x-text="filteredItems.length"></span><span x-show="filteredItems.length !== rawItems.length" x-text="' / ' + rawItems.length" class="text-slate-400"></span>
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
                <!-- Filter Controls Toolbar -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 bg-slate-50/80 p-3 rounded-2xl border border-slate-200/80 text-xs">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <!-- Mode Filter -->
                        <div class="flex items-center gap-1.5">
                            <span class="font-bold text-slate-500">Payment Mode:</span>
                            <select x-model="modeFilter"
                                    class="rounded-xl border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-800 shadow-2xs focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="all">All Modes</option>
                                <template x-for="m in modes" :key="m">
                                    <option :value="m" x-text="m"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div class="flex items-center gap-1.5">
                            <span class="font-bold text-slate-500">Status:</span>
                            <select x-model="statusFilter"
                                    class="rounded-xl border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-800 shadow-2xs focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="all">All Statuses</option>
                                <template x-for="st in statuses" :key="st">
                                    <option :value="st" x-text="st"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Reset Filters Button -->
                        <button type="button"
                                x-show="modeFilter !== 'all' || statusFilter !== 'all' || searchQuery !== '' || sortCol !== 'raw_date' || !sortAsc"
                                @click="resetFilters()"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold transition">
                            <span>Reset</span>
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div class="relative w-full md:w-64">
                        <input type="text"
                               x-model="searchQuery"
                               placeholder="Search payments..."
                               class="w-full rounded-xl border border-slate-200 bg-white pl-8 pr-3 py-1 text-xs font-bold text-slate-800 shadow-2xs focus:ring-indigo-500 focus:border-indigo-500">
                        <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto rounded-2xl border border-slate-100">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                <th class="px-4 py-3 cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('raw_date')">
                                    <div class="flex items-center gap-1">
                                        <span>Date</span>
                                        <span class="text-[10px]" :class="sortCol === 'raw_date' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'raw_date' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'raw_date' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'raw_date'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('reference')">
                                    <div class="flex items-center gap-1">
                                        <span>Reference</span>
                                        <span class="text-[10px]" :class="sortCol === 'reference' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'reference' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'reference' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'reference'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('mode')">
                                    <div class="flex items-center gap-1">
                                        <span>Payment Mode</span>
                                        <span class="text-[10px]" :class="sortCol === 'mode' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'mode' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'mode' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'mode'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-right cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('amount')">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Amount</span>
                                        <span class="text-[10px]" :class="sortCol === 'amount' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'amount' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'amount' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'amount'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-right cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('allocated')">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Allocated</span>
                                        <span class="text-[10px]" :class="sortCol === 'allocated' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'allocated' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'allocated' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'allocated'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-right cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('unallocated')">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Unallocated</span>
                                        <span class="text-[10px]" :class="sortCol === 'unallocated' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'unallocated' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'unallocated' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'unallocated'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-center cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('status')">
                                    <div class="flex items-center justify-center gap-1">
                                        <span>Status</span>
                                        <span class="text-[10px]" :class="sortCol === 'status' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'status' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'status' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'status'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('company_account')">
                                    <div class="flex items-center gap-1">
                                        <span>Company Account</span>
                                        <span class="text-[10px]" :class="sortCol === 'company_account' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'company_account' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'company_account' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'company_account'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            <template x-for="p in filteredItems" :key="p.id">
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="px-4 py-3 font-mono text-slate-900 font-bold whitespace-nowrap" x-text="p.date"></td>
                                    <td class="px-4 py-3 font-mono font-semibold text-slate-800" x-text="p.reference"></td>
                                    <td class="px-4 py-3 text-slate-700 font-semibold" x-text="p.mode"></td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 whitespace-nowrap" x-text="formatCurrency(p.amount)"></td>
                                    <td class="px-4 py-3 text-right font-mono text-emerald-700 font-semibold whitespace-nowrap" x-text="formatCurrency(p.allocated)"></td>
                                    <td class="px-4 py-3 text-right font-mono font-bold whitespace-nowrap"
                                        :class="parseFloat(p.unallocated) > 0 ? 'text-amber-800' : 'text-slate-500'"
                                        x-text="formatCurrency(p.unallocated)"></td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase"
                                              :class="{
                                                  'bg-emerald-100 text-emerald-800': p.status_color === 'emerald',
                                                  'bg-amber-100 text-amber-800': p.status_color === 'amber',
                                                  'bg-sky-100 text-sky-800': p.status_color === 'sky',
                                                  'bg-slate-100 text-slate-700': p.status_color !== 'emerald' && p.status_color !== 'amber' && p.status_color !== 'sky'
                                              }"
                                              x-text="p.status">
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600" x-text="p.company_account"></td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <a :href="'{{ route('admin.cashbook.shop.history.payments', ['shop' => $currentShopSlugOrId, 'month' => $month]) }}&search=' + encodeURIComponent(p.reference)"
                                           class="inline-flex items-center px-2 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 text-slate-800 text-[11px] font-bold shadow-2xs transition">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filteredItems.length === 0">
                                <td colspan="9" class="p-8 text-center text-slate-400 italic">
                                    No payments match the selected filters.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-50 border-t border-slate-200 font-mono text-xs font-bold text-slate-900">
                                <td colspan="3" class="px-4 py-3 uppercase tracking-wider text-slate-600">
                                    <span>Total Payments Received</span>
                                    <span class="font-normal text-slate-400 ml-1" x-text="'(' + filteredItems.length + ' records)'"></span>
                                </td>
                                <td class="px-4 py-3 text-right font-black" x-text="formatCurrency(totalAmount)"></td>
                                <td class="px-4 py-3 text-right font-black text-emerald-700" x-text="formatCurrency(totalAllocated)"></td>
                                <td class="px-4 py-3 text-right font-black text-amber-800" x-text="formatCurrency(totalUnallocated)"></td>
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
    <div class="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-xs space-y-4"
         x-data="{
             open: true,
             rawItems: @js($allocations),
             searchQuery: '',
             sortCol: 'allocated_at',
             sortAsc: false,
             formatCurrency(val) {
                 return '₹' + Number(val || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
             },
             sortBy(col) {
                 if (this.sortCol === col) {
                     this.sortAsc = !this.sortAsc;
                 } else {
                     this.sortCol = col;
                     this.sortAsc = (col === 'amount') ? false : true;
                 }
             },
             get filteredItems() {
                 let items = this.rawItems.filter(item => {
                     if (this.searchQuery.trim() !== '') {
                         const q = this.searchQuery.toLowerCase();
                         const match = (item.allocated_at || '').toLowerCase().includes(q)
                             || (item.payment_reference || '').toLowerCase().includes(q)
                             || (item.obligation_label || '').toLowerCase().includes(q)
                             || (item.allocated_by || '').toLowerCase().includes(q);
                         if (!match) return false;
                     }
                     return true;
                 });

                 return items.sort((a, b) => {
                     let valA = a[this.sortCol];
                     let valB = b[this.sortCol];
                     if (this.sortCol === 'amount') {
                         valA = parseFloat(valA) || 0;
                         valB = parseFloat(valB) || 0;
                     } else {
                         valA = (valA || '').toString().toLowerCase();
                         valB = (valB || '').toString().toLowerCase();
                     }
                     if (valA < valB) return this.sortAsc ? -1 : 1;
                     if (valA > valB) return this.sortAsc ? 1 : -1;
                     return 0;
                 });
             },
             get totalAmount() {
                 return this.filteredItems.reduce((sum, i) => sum + (parseFloat(i.amount) || 0), 0);
             }
         }">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 cursor-pointer" @click="open = !open">
            <div>
                <h2 class="text-base font-black text-slate-900 uppercase tracking-tight flex items-center gap-2">
                    <span>Allocation Details</span>
                    <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-mono">
                        <span x-text="filteredItems.length"></span><span x-show="filteredItems.length !== rawItems.length" x-text="' / ' + rawItems.length" class="text-slate-400"></span>
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
                <!-- Search Toolbar -->
                <div class="flex items-center justify-between gap-3 bg-slate-50/80 p-3 rounded-2xl border border-slate-200/80 text-xs">
                    <span class="text-slate-500 font-semibold">Filter allocations by payment reference, date, or obligation</span>
                    <div class="relative w-full md:w-64">
                        <input type="text"
                               x-model="searchQuery"
                               placeholder="Search allocations..."
                               class="w-full rounded-xl border border-slate-200 bg-white pl-8 pr-3 py-1 text-xs font-bold text-slate-800 shadow-2xs focus:ring-indigo-500 focus:border-indigo-500">
                        <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto rounded-2xl border border-slate-100">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                <th class="px-4 py-3 cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('allocated_at')">
                                    <div class="flex items-center gap-1">
                                        <span>Allocated At</span>
                                        <span class="text-[10px]" :class="sortCol === 'allocated_at' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'allocated_at' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'allocated_at' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'allocated_at'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('payment_reference')">
                                    <div class="flex items-center gap-1">
                                        <span>Payment Reference</span>
                                        <span class="text-[10px]" :class="sortCol === 'payment_reference' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'payment_reference' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'payment_reference' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'payment_reference'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('obligation_label')">
                                    <div class="flex items-center gap-1">
                                        <span>Business Day / Obligation</span>
                                        <span class="text-[10px]" :class="sortCol === 'obligation_label' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'obligation_label' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'obligation_label' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'obligation_label'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-right cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('amount')">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Amount Allocated</span>
                                        <span class="text-[10px]" :class="sortCol === 'amount' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'amount' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'amount' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'amount'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('allocated_by')">
                                    <div class="flex items-center gap-1">
                                        <span>Allocated By</span>
                                        <span class="text-[10px]" :class="sortCol === 'allocated_by' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'allocated_by' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'allocated_by' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'allocated_by'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-center cursor-pointer select-none hover:bg-slate-100 transition group" @click="sortBy('status')">
                                    <div class="flex items-center justify-center gap-1">
                                        <span>Status</span>
                                        <span class="text-[10px]" :class="sortCol === 'status' ? 'text-indigo-600 font-black' : 'text-slate-300 group-hover:text-slate-400'">
                                            <span x-show="sortCol === 'status' && sortAsc">▲</span>
                                            <span x-show="sortCol === 'status' && !sortAsc">▼</span>
                                            <span x-show="sortCol !== 'status'">↕</span>
                                        </span>
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            <template x-for="al in filteredItems" :key="al.id">
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="px-4 py-3 font-mono text-slate-900 font-bold whitespace-nowrap" x-text="al.allocated_at"></td>
                                    <td class="px-4 py-3 font-mono font-semibold text-slate-800" x-text="al.payment_reference"></td>
                                    <td class="px-4 py-3 text-slate-800 font-medium" x-text="al.obligation_label"></td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-emerald-700 whitespace-nowrap" x-text="formatCurrency(al.amount)"></td>
                                    <td class="px-4 py-3 text-slate-600" x-text="al.allocated_by"></td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-100 text-emerald-800" x-text="al.status"></span>
                                    </td>
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <a :href="'{{ route('admin.cashbook.shop.history.allocations', ['shop' => $currentShopSlugOrId, 'month' => $month]) }}&search=' + encodeURIComponent(al.payment_reference)"
                                           class="inline-flex items-center px-2 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 text-slate-800 text-[11px] font-bold shadow-2xs transition">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filteredItems.length === 0">
                                <td colspan="7" class="p-8 text-center text-slate-400 italic">
                                    No allocations match the search query.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-50 border-t border-slate-200 font-mono text-xs font-bold text-slate-900">
                                <td colspan="3" class="px-4 py-3 uppercase tracking-wider text-slate-600">
                                    <span>Total Allocated</span>
                                    <span class="font-normal text-slate-400 ml-1" x-text="'(' + filteredItems.length + ' records)'"></span>
                                </td>
                                <td class="px-4 py-3 text-right font-black text-emerald-700" x-text="formatCurrency(totalAmount)"></td>
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
