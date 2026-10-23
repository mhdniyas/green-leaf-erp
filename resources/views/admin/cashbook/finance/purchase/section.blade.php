@extends('admin.cashbook.layouts.app')

@php
    $labels = ['purchasers' => 'Purchasers', 'vendors' => 'Vendors', 'categories' => 'Categories', 'invoices' => 'Invoices'];
    $questions = [
        'purchasers' => 'Purchaser Procurement & Funding Status',
        'vendors' => 'Supplier Directory & Purchases Breakdown',
        'categories' => 'Product Categories & Volume Distribution',
        'invoices' => 'Purchase Invoices Ledger & Settlement Tracking',
    ];
    $activePurchaseTab = $section;
    $summary = $sectionData['summary'];
    $sectionRoute = 'admin.cashbook.finance.purchase.'.$section;
    $cardQuery = request()->except(['page', 'payment']);
    $sectionCardUrl = fn (string $payment = 'all'): string => route($sectionRoute, array_merge($cardQuery, ['payment' => $payment])).'#purchase-results';

    $cards = match($section) {
        'purchasers' => [
            ['Total Purchase', $summary->total_purchase, true, $sectionCardUrl(), 'emerald'],
            ['Cash Purchase', $summary->cash_purchase, true, $sectionCardUrl('cash'), 'teal'],
            ['Credit Purchase', $summary->credit_purchase, true, $sectionCardUrl('credit'), 'indigo'],
            ['Period Funding', $sectionData['rowSummary']->funding ?? 0, true, $sectionCardUrl(), 'sky'],
            ['Current Advance', $sectionData['rowSummary']->balance ?? 0, true, $sectionCardUrl(), 'purple'],
        ],
        'vendors' => [
            ['Total Vendors', $summary->vendor_count, false, $sectionCardUrl(), 'slate'],
            ['Total Purchase', $summary->total_purchase, true, $sectionCardUrl(), 'emerald'],
            ['Cash', $summary->cash_purchase, true, $sectionCardUrl('cash'), 'teal'],
            ['Credit', $summary->credit_purchase, true, $sectionCardUrl('credit'), 'indigo'],
            ['Outstanding', $summary->credit_outstanding, true, route('admin.cashbook.finance.vendor-credit'), 'rose'],
        ],
        'categories' => [
            ['Total Purchase', $summary->total_purchase, true, $sectionCardUrl(), 'emerald'],
            ['Categories', $summary->category_count, false, $sectionCardUrl(), 'indigo'],
            ['Vendors', $summary->vendor_count, false, route('admin.cashbook.finance.purchase.vendors', $cardQuery), 'teal'],
            ['Purchasers', $summary->purchaser_count, false, route('admin.cashbook.finance.purchase.purchasers', $cardQuery), 'sky'],
        ],
        default => [
            ['Total Purchase', $summary->total_purchase, true, $sectionCardUrl(), 'emerald'],
            ['Invoices', $summary->invoice_count, false, $sectionCardUrl(), 'slate'],
            ['Cash', $summary->cash_purchase, true, $sectionCardUrl('cash'), 'teal'],
            ['Credit', $summary->credit_purchase, true, $sectionCardUrl('credit'), 'indigo'],
            ['Outstanding', $summary->credit_outstanding, true, route('admin.cashbook.finance.vendor-credit'), 'rose'],
        ],
    };
@endphp

@section('title', $labels[$section].' — Purchase Finance')
@section('header_title')
    <i data-lucide="shopping-basket" class="h-5 w-5 text-emerald-600"></i> Purchase Finance
@endsection

@section('header_subtitle')
    {{ $labels[$section] }} &bull; Detailed Procurement Ledger
@endsection

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-16">
    <!-- TOP NAVIGATION & SUBSECTIONS -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        @include('admin.cashbook.finance.purchase._nav')
        @include('admin.cashbook.finance.purchase._dashboard-tabs')
    </div>

    <!-- SECTION HEADER -->
    <header class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                    <span class="text-xs font-black uppercase tracking-wider text-emerald-800">{{ $labels[$section] }}</span>
                </div>
                <h1 class="mt-1 text-xl sm:text-2xl font-black text-slate-950 tracking-tight">{{ $questions[$section] }}</h1>
                <p class="mt-1 text-xs font-bold text-slate-500 flex items-center gap-1.5">
                    <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
                    Period: <strong class="text-slate-900">{{ $filters['start_date'] }}</strong> to <strong class="text-slate-900">{{ $filters['end_date'] }}</strong>
                </p>
            </div>
            @if($section === 'vendors')
                <a href="{{ route('admin.cashbook.finance.vendor-credit') }}"
                   class="inline-flex items-center gap-1.5 rounded-2xl bg-emerald-700 px-4 py-2 text-xs font-black uppercase tracking-wider text-white shadow-xs hover:bg-emerald-800 transition">
                    <i data-lucide="truck" class="w-4 h-4"></i>
                    <span>Vendor Credit Payments</span>
                </a>
            @endif
        </div>

        @include('admin.cashbook.finance.purchase._section-filters')
    </header>

    <!-- SECTION SUMMARY CARDS -->
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-{{ count($cards) }}">
        @foreach($cards as [$label, $value, $money, $cardUrl, $color])
            @php
                $bgClass = match($color) {
                    'emerald' => 'border-emerald-100 bg-emerald-50/40 hover:bg-emerald-50/70 text-emerald-950',
                    'rose' => 'border-rose-100 bg-rose-50/40 hover:bg-rose-50/70 text-rose-950',
                    'indigo' => 'border-indigo-100 bg-indigo-50/40 hover:bg-indigo-50/70 text-indigo-950',
                    'teal' => 'border-teal-100 bg-teal-50/40 hover:bg-teal-50/70 text-teal-950',
                    'sky' => 'border-sky-100 bg-sky-50/40 hover:bg-sky-50/70 text-sky-950',
                    'purple' => 'border-purple-100 bg-purple-50/40 hover:bg-purple-50/70 text-purple-950',
                    default => 'border-slate-200 bg-slate-50/40 hover:bg-slate-50/70 text-slate-950',
                };
            @endphp
            <a href="{{ $cardUrl }}"
               class="rounded-2xl border p-4 shadow-xs transition {{ $bgClass }} flex flex-col justify-between group">
                <span class="flex items-center justify-between gap-2 text-[10px] font-black uppercase tracking-wider text-slate-500">
                    <span>{{ $label }}</span>
                    <i data-lucide="arrow-up-right" class="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-700"></i>
                </span>
                <strong class="mt-2 block font-mono text-xl sm:text-2xl font-black tabular-nums tracking-tight">
                    {{ $money ? '₹'.number_format((float) $value, 2) : number_format((int) $value) }}
                </strong>
            </a>
        @endforeach
    </section>

    <!-- DATA TABLE -->
    @if($section === 'invoices' && $sectionData['rows']->isEmpty())
        <section class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center shadow-xs">
            <p class="font-black text-slate-900">No purchase invoices match the selected filters.</p>
            <a href="{{ route('admin.cashbook.finance.purchase.invoices') }}"
               class="mt-4 inline-flex rounded-xl bg-emerald-700 px-4 py-2 text-xs font-black uppercase tracking-wider text-white shadow-xs hover:bg-emerald-800 transition">
                Clear Filters
            </a>
        </section>
    @else
        <section id="purchase-results" class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-4">
            <div class="hidden overflow-x-auto md:block"
                 x-data="{
                     sortCol: null,
                     sortAsc: true,
                     sortTable(colIndex, type = 'num') {
                         const table = (this.$el && this.$el.closest ? this.$el.closest('table') : null) || (this.$root ? this.$root.querySelector('table') : null);
                         if (!table) return;
                         const tbody = table.querySelector('tbody');
                         if (!tbody) return;
                         const rows = Array.from(tbody.querySelectorAll('tr:not(.no-sort)'));
                         if (rows.length <= 1) return;

                         if (this.sortCol === colIndex) {
                             this.sortAsc = !this.sortAsc;
                         } else {
                             this.sortCol = colIndex;
                             this.sortAsc = (type === 'date' || type === 'num' ? false : true);
                         }

                         rows.sort((a, b) => {
                             const cellA = a.children[colIndex];
                             const cellB = b.children[colIndex];
                             if (!cellA || !cellB) return 0;

                             let valA = cellA.getAttribute('data-value') ?? cellA.innerText.trim();
                             let valB = cellB.getAttribute('data-value') ?? cellB.innerText.trim();

                             if (type === 'date') {
                                 valA = cellA.getAttribute('data-date') || valA;
                                 valB = cellB.getAttribute('data-date') || valB;
                                 return this.sortAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
                             }

                             if (type === 'num') {
                                 const numA = parseFloat(valA.replace(/[^\d.-]/g, '')) || 0;
                                 const numB = parseFloat(valB.replace(/[^\d.-]/g, '')) || 0;
                                 return this.sortAsc ? numA - numB : numB - numA;
                             }

                             return this.sortAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
                         });

                         rows.forEach(row => tbody.appendChild(row));
                     }
                 }">
                <table class="w-full min-w-[58rem] text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500 rounded-xl select-none">
                        @if($section === 'purchasers')
                            <tr>
                                <th @click="sortTable(0, 'text')" class="p-3 cursor-pointer hover:bg-slate-100 transition" title="Sort by Purchaser">
                                    <div class="flex items-center gap-1">
                                        <span>Purchaser</span>
                                        <span class="text-[9px]" :class="sortCol === 0 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 0 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(1, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Total Purchase">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Total Purchase</span>
                                        <span class="text-[9px]" :class="sortCol === 1 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 1 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(2, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Cash">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Cash</span>
                                        <span class="text-[9px]" :class="sortCol === 2 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 2 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(3, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Credit">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Credit</span>
                                        <span class="text-[9px]" :class="sortCol === 3 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 3 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(4, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Period Funding">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Period Funding</span>
                                        <span class="text-[9px]" :class="sortCol === 4 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 4 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(5, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Period Used">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Period Used</span>
                                        <span class="text-[9px]" :class="sortCol === 5 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 5 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(6, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Transactions">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Transactions</span>
                                        <span class="text-[9px]" :class="sortCol === 6 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 6 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(7, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Current Advance">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Current Advance</span>
                                        <span class="text-[9px]" :class="sortCol === 7 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 7 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(8, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Invoices">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Invoices</span>
                                        <span class="text-[9px]" :class="sortCol === 8 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 8 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th class="p-3 text-center">Action</th>
                            </tr>
                        @endif
                        @if($section === 'vendors')
                            <tr>
                                <th @click="sortTable(0, 'text')" class="p-3 cursor-pointer hover:bg-slate-100 transition" title="Sort by Vendor">
                                    <div class="flex items-center gap-1">
                                        <span>Vendor</span>
                                        <span class="text-[9px]" :class="sortCol === 0 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 0 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th class="p-3">Categories</th>
                                <th @click="sortTable(2, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Invoices">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Invoices</span>
                                        <span class="text-[9px]" :class="sortCol === 2 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 2 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(3, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Cash">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Cash</span>
                                        <span class="text-[9px]" :class="sortCol === 3 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 3 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(4, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Credit">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Credit</span>
                                        <span class="text-[9px]" :class="sortCol === 4 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 4 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(5, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Outstanding">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Outstanding</span>
                                        <span class="text-[9px]" :class="sortCol === 5 ? 'text-rose-700 font-black' : 'text-slate-300'" x-text="sortCol === 5 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(6, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Total Purchase">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Total Purchase</span>
                                        <span class="text-[9px]" :class="sortCol === 6 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 6 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th class="p-3 text-center">Action</th>
                            </tr>
                        @endif
                        @if($section === 'categories')
                            <tr>
                                <th @click="sortTable(0, 'text')" class="p-3 cursor-pointer hover:bg-slate-100 transition" title="Sort by Category">
                                    <div class="flex items-center gap-1">
                                        <span>Category</span>
                                        <span class="text-[9px]" :class="sortCol === 0 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 0 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(1, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Purchase Value">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Purchase Value</span>
                                        <span class="text-[9px]" :class="sortCol === 1 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 1 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(2, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Cash">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Cash</span>
                                        <span class="text-[9px]" :class="sortCol === 2 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 2 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(3, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Credit">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Credit</span>
                                        <span class="text-[9px]" :class="sortCol === 3 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 3 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(4, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Vendors">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Vendors</span>
                                        <span class="text-[9px]" :class="sortCol === 4 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 4 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(5, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Purchasers">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Purchasers</span>
                                        <span class="text-[9px]" :class="sortCol === 5 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 5 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(6, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Invoices">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Invoices</span>
                                        <span class="text-[9px]" :class="sortCol === 6 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 6 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                            </tr>
                        @endif
                        @if($section === 'invoices')
                            <tr>
                                <th @click="sortTable(0, 'date')" class="p-3 cursor-pointer hover:bg-slate-100 transition" title="Sort by Date">
                                    <div class="flex items-center gap-1">
                                        <span>Date</span>
                                        <span class="text-[9px]" :class="sortCol === 0 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 0 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(1, 'text')" class="p-3 cursor-pointer hover:bg-slate-100 transition" title="Sort by Invoice">
                                    <div class="flex items-center gap-1">
                                        <span>Invoice</span>
                                        <span class="text-[9px]" :class="sortCol === 1 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 1 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(2, 'text')" class="p-3 cursor-pointer hover:bg-slate-100 transition" title="Sort by Vendor">
                                    <div class="flex items-center gap-1">
                                        <span>Vendor</span>
                                        <span class="text-[9px]" :class="sortCol === 2 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 2 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(3, 'text')" class="p-3 cursor-pointer hover:bg-slate-100 transition" title="Sort by Purchaser">
                                    <div class="flex items-center gap-1">
                                        <span>Purchaser</span>
                                        <span class="text-[9px]" :class="sortCol === 3 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 3 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(4, 'text')" class="p-3 cursor-pointer hover:bg-slate-100 transition" title="Sort by Categories">
                                    <div class="flex items-center gap-1">
                                        <span>Categories</span>
                                        <span class="text-[9px]" :class="sortCol === 4 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 4 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(5, 'text')" class="p-3 text-center cursor-pointer hover:bg-slate-100 transition" title="Sort by Payment">
                                    <div class="flex items-center justify-center gap-1">
                                        <span>Payment</span>
                                        <span class="text-[9px]" :class="sortCol === 5 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 5 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(6, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Amount">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Amount</span>
                                        <span class="text-[9px]" :class="sortCol === 6 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 6 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(7, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Paid">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Paid</span>
                                        <span class="text-[9px]" :class="sortCol === 7 ? 'text-emerald-700 font-black' : 'text-slate-300'" x-text="sortCol === 7 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th @click="sortTable(8, 'num')" class="p-3 text-right cursor-pointer hover:bg-slate-100 transition" title="Sort by Outstanding">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Outstanding</span>
                                        <span class="text-[9px]" :class="sortCol === 8 ? 'text-rose-700 font-black' : 'text-slate-300'" x-text="sortCol === 8 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                    </div>
                                </th>
                                <th class="p-3 text-center">Action</th>
                            </tr>
                        @endif
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($sectionData['rows'] as $row)
                            @include('admin.cashbook.finance.purchase._section-row', ['mobile' => false])
                        @empty
                            <tr>
                                <td colspan="10" class="p-8 text-center text-xs font-semibold text-slate-400">No matching purchase data.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Mobile View -->
            <div class="divide-y divide-slate-100 md:hidden">
                @forelse($sectionData['rows'] as $row)
                    @include('admin.cashbook.finance.purchase._section-row', ['mobile' => true])
                @empty
                    <p class="p-8 text-center text-xs font-semibold text-slate-400">No matching purchase data.</p>
                @endforelse
            </div>

            @if($sectionData['rows']->hasPages())
                <div class="border-t border-slate-100 pt-4">
                    {{ $sectionData['rows']->links() }}
                </div>
            @endif
        </section>
    @endif
</div>
@endsection
