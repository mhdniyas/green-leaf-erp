@extends('admin.cashbook.layouts.app')

@section('title', 'Monthly Sale Split & Purchaser Position — Green Leaf')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-16" x-data>
    <!-- TOP HEADER -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-black text-indigo-700">Product Category Split</span>
                <span class="text-xs font-semibold text-slate-400">Cashbook Monthly Report</span>
            </div>
            <h1 class="mt-1 text-xl font-black tracking-tight text-slate-900 sm:text-2xl">
                Monthly Sale Split
            </h1>
            <div class="mt-2 flex items-center gap-2">
                <a href="{{ route('admin.cashbook.monthly-report.section-reports', array_merge(['month' => $period['month'] ?? request('month')], request()->query())) }}"
                   class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white shadow-xs transition hover:bg-indigo-700">
                    <i data-lucide="layout-grid" class="h-3.5 w-3.5"></i>
                    Section Reports
                </a>
            </div>
        </div>

        <!-- TAB NAVIGATION -->
        <div class="inline-flex rounded-xl bg-slate-100 p-1 text-xs font-bold text-slate-600">
            <a href="{{ route('admin.cashbook.monthly-report.overview', array_merge(['month' => $period['month'] ?? request('month')], request()->query())) }}"
               class="rounded-lg px-3 py-1.5 transition {{ request()->routeIs('admin.cashbook.monthly-report.overview') ? 'bg-white text-slate-900 shadow-xs' : 'hover:text-slate-900' }}">
                Overview
            </a>
            <a href="{{ route('admin.cashbook.monthly-report.sale-split', array_merge(['month' => $period['month'] ?? request('month')], request()->query())) }}"
               class="rounded-lg px-3 py-1.5 transition {{ request()->routeIs('admin.cashbook.monthly-report.sale-split') ? 'bg-white text-slate-900 shadow-xs' : 'hover:text-slate-900' }}">
                Sale Split
            </a>
            <a href="{{ route('admin.cashbook.monthly-report.section-reports', array_merge(['month' => $period['month'] ?? request('month')], request()->query())) }}"
               class="rounded-lg px-3 py-1.5 transition {{ request()->routeIs('admin.cashbook.monthly-report.section-reports*') ? 'bg-white text-slate-900 shadow-xs' : 'hover:text-slate-900' }}">
                Section Reports
            </a>
            <a href="{{ route('admin.cashbook.monthly-report.other-expenses', request()->query()) }}"
               class="rounded-lg px-3 py-1.5 transition {{ request()->routeIs('admin.cashbook.monthly-report.other-expenses') ? 'bg-white text-slate-900 shadow-xs' : 'hover:text-slate-900' }}">
                Other Expense
            </a>
            <a href="{{ route('admin.cashbook.monthly-report.expense-report', request()->query()) }}"
               class="rounded-lg px-3 py-1.5 transition {{ request()->routeIs('admin.cashbook.monthly-report.expense-report') ? 'bg-white text-slate-900 shadow-xs' : 'hover:text-slate-900' }}">
                Expense Report
            </a>
        </div>
    </div>

    <!-- SHARED PERIOD FILTER -->
    @include('admin.cashbook.monthly-report.partials.period-filter', ['reportType' => 'sale-split'])

    <!-- RECONCILIATION & READINESS STATUS -->
    @include('admin.cashbook.monthly-report.partials.reconciliation-badge')

    <!-- CATEGORY PERFORMANCE SUMMARY TILES -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <!-- FRUITS TILE -->
        <div class="rounded-2xl border border-amber-200/80 bg-amber-50/30 p-4 transition hover:border-amber-300 hover:shadow-md flex flex-col justify-between">
            <div class="cursor-pointer" @click="$dispatch('open-drilldown', { metric: 'fruits_expense', title: 'Fruits Purchases Breakdown' })">
                <div class="flex items-center justify-between text-xs font-bold text-amber-900">
                    <span>Fruits</span>
                    <i data-lucide="apple" class="h-4 w-4 text-amber-600"></i>
                </div>
                <div class="mt-2 text-sm font-black text-slate-900 font-mono">
                    ₹{{ number_format($summary['fruits_sale'], 2) }} <span class="text-[10px] font-normal text-slate-500">Sale</span>
                </div>
                <div class="mt-0.5 text-xs font-semibold text-amber-800 font-mono">
                    ₹{{ number_format($summary['fruits_expense'], 2) }} <span class="text-[10px] font-normal text-slate-500">Expense</span>
                </div>
            </div>
            <div class="mt-2.5 flex items-center justify-between border-t border-amber-200/60 pt-2 text-[11px]">
                <button type="button" @click="$dispatch('open-drilldown', { metric: 'fruits_expense', title: 'Fruits Purchases Breakdown' })" class="text-amber-800 hover:text-amber-950 font-semibold">
                    Breakdown
                </button>
                <a href="{{ route('admin.cashbook.monthly-report.section-reports', array_merge(['month' => $period['month'] ?? request('month'), 'section' => 'filter_1'], request()->query())) }}"
                   class="inline-flex items-center gap-1 font-bold text-amber-900 hover:text-amber-700">
                    <span>Section Report</span>
                    <i data-lucide="arrow-right" class="h-3 w-3"></i>
                </a>
            </div>
        </div>

        <!-- VEGETABLES TILE -->
        <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/30 p-4 transition hover:border-emerald-300 hover:shadow-md flex flex-col justify-between">
            <div class="cursor-pointer" @click="$dispatch('open-drilldown', { metric: 'vegetables_expense', title: 'Vegetables Purchases Breakdown' })">
                <div class="flex items-center justify-between text-xs font-bold text-emerald-900">
                    <span>Vegetables</span>
                    <i data-lucide="carrot" class="h-4 w-4 text-emerald-600"></i>
                </div>
                <div class="mt-2 text-sm font-black text-slate-900 font-mono">
                    ₹{{ number_format($summary['veg_sale'], 2) }} <span class="text-[10px] font-normal text-slate-500">Sale</span>
                </div>
                <div class="mt-0.5 text-xs font-semibold text-emerald-800 font-mono">
                    ₹{{ number_format($summary['veg_expense'], 2) }} <span class="text-[10px] font-normal text-slate-500">Expense</span>
                </div>
            </div>
            <div class="mt-2.5 flex items-center justify-between border-t border-emerald-200/60 pt-2 text-[11px]">
                <button type="button" @click="$dispatch('open-drilldown', { metric: 'vegetables_expense', title: 'Vegetables Purchases Breakdown' })" class="text-emerald-800 hover:text-emerald-950 font-semibold">
                    Breakdown
                </button>
                <a href="{{ route('admin.cashbook.monthly-report.section-reports', array_merge(['month' => $period['month'] ?? request('month'), 'section' => 'filter_2'], request()->query())) }}"
                   class="inline-flex items-center gap-1 font-bold text-emerald-900 hover:text-emerald-700">
                    <span>Section Report</span>
                    <i data-lucide="arrow-right" class="h-3 w-3"></i>
                </a>
            </div>
        </div>

        <!-- STATIONERY TILE -->
        <div class="rounded-2xl border border-blue-200/80 bg-blue-50/30 p-4 transition hover:border-blue-300 hover:shadow-md flex flex-col justify-between">
            <div class="cursor-pointer" @click="$dispatch('open-drilldown', { metric: 'stationery_expense', title: 'Stationery Purchases Breakdown' })">
                <div class="flex items-center justify-between text-xs font-bold text-blue-900">
                    <span>Stationery</span>
                    <i data-lucide="paperclip" class="h-4 w-4 text-blue-600"></i>
                </div>
                <div class="mt-2 text-sm font-black text-slate-900 font-mono">
                    ₹{{ number_format($summary['stationery_sale'], 2) }} <span class="text-[10px] font-normal text-slate-500">Sale</span>
                </div>
                <div class="mt-0.5 text-xs font-semibold text-blue-800 font-mono">
                    ₹{{ number_format($summary['stationery_expense'], 2) }} <span class="text-[10px] font-normal text-slate-500">Expense</span>
                </div>
            </div>
            <div class="mt-2.5 flex items-center justify-between border-t border-blue-200/60 pt-2 text-[11px]">
                <button type="button" @click="$dispatch('open-drilldown', { metric: 'stationery_expense', title: 'Stationery Purchases Breakdown' })" class="text-blue-800 hover:text-blue-950 font-semibold">
                    Breakdown
                </button>
                <a href="{{ route('admin.cashbook.monthly-report.section-reports', array_merge(['month' => $period['month'] ?? request('month'), 'section' => 'filter_3'], request()->query())) }}"
                   class="inline-flex items-center gap-1 font-bold text-blue-900 hover:text-blue-700">
                    <span>Section Report</span>
                    <i data-lucide="arrow-right" class="h-3 w-3"></i>
                </a>
            </div>
        </div>

        <!-- OTHER EXPENSES TILE -->
        <div class="rounded-2xl border border-purple-200/80 bg-purple-50/30 p-4 transition hover:border-purple-300 hover:shadow-md flex flex-col justify-between">
            <div class="cursor-pointer" @click="$dispatch('open-drilldown', { metric: 'other_expense', title: 'Operating Expenses Breakdown' })">
                <div class="flex items-center justify-between text-xs font-bold text-purple-900">
                    <span>Operating Exp</span>
                    <i data-lucide="receipt" class="h-4 w-4 text-purple-600"></i>
                </div>
                <div class="mt-2 text-sm font-black text-slate-900 font-mono">
                    ₹{{ number_format($summary['other_expenses'], 2) }}
                </div>
                <div class="mt-0.5 text-[11px] font-semibold text-purple-700">
                    Salary, Rent, Fuel, Mess, etc.
                </div>
            </div>
            <div class="mt-2.5 flex items-center justify-between border-t border-purple-200/60 pt-2 text-[11px]">
                <button type="button" @click="$dispatch('open-drilldown', { metric: 'other_expense', title: 'Operating Expenses Breakdown' })" class="text-purple-800 hover:text-purple-950 font-semibold">
                    Breakdown
                </button>
                <a href="{{ route('admin.cashbook.monthly-report.other-expenses', array_merge(['month' => $period['month'] ?? request('month')], request()->query())) }}"
                   class="inline-flex items-center gap-1 font-bold text-purple-900 hover:text-purple-700">
                    <span>Detailed Report</span>
                    <i data-lucide="arrow-right" class="h-3 w-3"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- MAIN SALE SPLIT TABLE -->
    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs">
        <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:px-6">
            <h2 class="text-sm font-extrabold text-slate-900">Daily Category Matrix</h2>
            <p class="text-xs font-semibold text-slate-400">Side-by-side view of product sales, purchase expenses, and operating overheads</p>
        </div>

        <!-- DESKTOP TABLE -->
        <div class="overflow-x-auto">
            <table x-data="{
                sortCol: null,
                sortAsc: true,
                sortTable(colIndex, type = 'num') {
                    const tbody = $el.querySelector('tbody');
                    const rows = Array.from(tbody.querySelectorAll('tr:not(.no-sort)'));
                    if (rows.length <= 1) return;

                    if (this.sortCol === colIndex) {
                        this.sortAsc = !this.sortAsc;
                    } else {
                        this.sortCol = colIndex;
                        this.sortAsc = (type === 'date' ? false : true);
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
            }" class="w-full text-left text-xs text-slate-600">
                <!-- Grouped Header Rows -->
                <thead class="border-b border-slate-200 bg-slate-50/90 text-center text-[10px] font-black uppercase tracking-wider text-slate-600">
                    <tr>
                        <th rowspan="2" @click="sortTable(0, 'date')" class="border-r border-slate-200 px-4 py-2.5 text-left text-xs text-slate-900 cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Date">
                            <div class="flex items-center gap-1.5">
                                <span>Date</span>
                                <span class="text-[10px]" :class="sortCol === 0 ? 'text-indigo-600 font-black' : 'text-slate-300'" x-text="sortCol === 0 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th colspan="2" class="border-r border-slate-200 bg-amber-50/50 px-3 py-1.5 text-amber-900">Fruits (₹)</th>
                        <th colspan="2" class="border-r border-slate-200 bg-emerald-50/50 px-3 py-1.5 text-emerald-900">Vegetables (₹)</th>
                        <th colspan="2" class="border-r border-slate-200 bg-blue-50/50 px-3 py-1.5 text-blue-900">Stationery (₹)</th>
                        <th rowspan="2" @click="sortTable(7, 'num')" class="border-r border-slate-200 bg-purple-50/40 px-3 py-2.5 text-right text-purple-900 cursor-pointer hover:bg-purple-100/60 transition select-none" title="Click to sort by Other Expenses">
                            <div class="flex items-center justify-end gap-1">
                                <span>Other Exp (₹)</span>
                                <span class="text-[10px]" :class="sortCol === 7 ? 'text-purple-700 font-black' : 'text-purple-300'" x-text="sortCol === 7 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th rowspan="2" @click="sortTable(8, 'num')" class="border-r border-slate-200 px-3 py-2.5 text-right font-black text-slate-900 cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Total Sales">
                            <div class="flex items-center justify-end gap-1">
                                <span>Total Sales</span>
                                <span class="text-[10px]" :class="sortCol === 8 ? 'text-indigo-600 font-black' : 'text-slate-300'" x-text="sortCol === 8 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th rowspan="2" @click="sortTable(9, 'num')" class="border-r border-slate-200 px-3 py-2.5 text-right font-black text-rose-700 cursor-pointer hover:bg-rose-50 transition select-none" title="Click to sort by Total Expenses">
                            <div class="flex items-center justify-end gap-1">
                                <span>Total Exp</span>
                                <span class="text-[10px]" :class="sortCol === 9 ? 'text-rose-600 font-black' : 'text-slate-300'" x-text="sortCol === 9 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th rowspan="2" @click="sortTable(10, 'num')" class="px-4 py-2.5 text-right font-black text-slate-900 cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Net Balance">
                            <div class="flex items-center justify-end gap-1">
                                <span>Balance</span>
                                <span class="text-[10px]" :class="sortCol === 10 ? 'text-indigo-600 font-black' : 'text-slate-300'" x-text="sortCol === 10 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                    </tr>
                    <tr class="border-t border-slate-200 text-[10px] text-slate-500">
                        <th @click="sortTable(1, 'num')" class="border-r border-slate-100 bg-amber-50/30 px-2.5 py-1.5 text-right font-bold cursor-pointer hover:bg-amber-100/60 transition select-none" title="Click to sort Fruits Sales">
                            <div class="flex items-center justify-end gap-1">
                                <span>Sale</span>
                                <span class="text-[9px]" :class="sortCol === 1 ? 'text-amber-700 font-black' : 'text-amber-300'" x-text="sortCol === 1 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable(2, 'num')" class="border-r border-slate-200 bg-amber-50/30 px-2.5 py-1.5 text-right font-bold text-amber-800 cursor-pointer hover:bg-amber-100/60 transition select-none" title="Click to sort Fruits Purchases">
                            <div class="flex items-center justify-end gap-1">
                                <span>Exp</span>
                                <span class="text-[9px]" :class="sortCol === 2 ? 'text-amber-800 font-black' : 'text-amber-300'" x-text="sortCol === 2 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable(3, 'num')" class="border-r border-slate-100 bg-emerald-50/30 px-2.5 py-1.5 text-right font-bold cursor-pointer hover:bg-emerald-100/60 transition select-none" title="Click to sort Vegetables Sales">
                            <div class="flex items-center justify-end gap-1">
                                <span>Sale</span>
                                <span class="text-[9px]" :class="sortCol === 3 ? 'text-emerald-700 font-black' : 'text-emerald-300'" x-text="sortCol === 3 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable(4, 'num')" class="border-r border-slate-200 bg-emerald-50/30 px-2.5 py-1.5 text-right font-bold text-emerald-800 cursor-pointer hover:bg-emerald-100/60 transition select-none" title="Click to sort Vegetables Purchases">
                            <div class="flex items-center justify-end gap-1">
                                <span>Exp</span>
                                <span class="text-[9px]" :class="sortCol === 4 ? 'text-emerald-800 font-black' : 'text-emerald-300'" x-text="sortCol === 4 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable(5, 'num')" class="border-r border-slate-100 bg-blue-50/30 px-2.5 py-1.5 text-right font-bold cursor-pointer hover:bg-blue-100/60 transition select-none" title="Click to sort Stationery Sales">
                            <div class="flex items-center justify-end gap-1">
                                <span>Sale</span>
                                <span class="text-[9px]" :class="sortCol === 5 ? 'text-blue-700 font-black' : 'text-blue-300'" x-text="sortCol === 5 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable(6, 'num')" class="border-r border-slate-200 bg-blue-50/30 px-2.5 py-1.5 text-right font-bold text-blue-800 cursor-pointer hover:bg-blue-100/60 transition select-none" title="Click to sort Stationery Purchases">
                            <div class="flex items-center justify-end gap-1">
                                <span>Exp</span>
                                <span class="text-[9px]" :class="sortCol === 6 ? 'text-blue-800 font-black' : 'text-blue-300'" x-text="sortCol === 6 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium font-mono text-[11px]">
                    @forelse($daily_rows as $d)
                        @php $isPos = $d['balance'] >= 0; @endphp
                        <tr class="transition hover:bg-slate-50/80">
                            <td data-date="{{ $d['date'] }}" class="border-r border-slate-100 px-4 py-2.5 font-sans font-semibold text-slate-900 whitespace-nowrap">
                                {{ $d['formatted_date'] }}
                            </td>
                            <!-- Fruits -->
                            <td data-value="{{ $d['fruits_sale'] }}" class="border-r border-slate-100 px-2.5 py-2.5 text-right text-slate-800">{{ number_format($d['fruits_sale'], 2) }}</td>
                            <td data-value="{{ $d['fruits_expense'] }}" class="border-r border-slate-200 px-2.5 py-2.5 text-right text-amber-800">{{ number_format($d['fruits_expense'], 2) }}</td>
                            <!-- Vegetables -->
                            <td data-value="{{ $d['veg_sale'] }}" class="border-r border-slate-100 px-2.5 py-2.5 text-right text-slate-800">{{ number_format($d['veg_sale'], 2) }}</td>
                            <td data-value="{{ $d['veg_expense'] }}" class="border-r border-slate-200 px-2.5 py-2.5 text-right text-emerald-800">{{ number_format($d['veg_expense'], 2) }}</td>
                            <!-- Stationery -->
                            <td data-value="{{ $d['stationery_sale'] }}" class="border-r border-slate-100 px-2.5 py-2.5 text-right text-slate-800">{{ number_format($d['stationery_sale'], 2) }}</td>
                            <td data-value="{{ $d['stationery_expense'] }}" class="border-r border-slate-200 px-2.5 py-2.5 text-right text-blue-800">{{ number_format($d['stationery_expense'], 2) }}</td>
                            <!-- Other Operating Expenses -->
                            <td data-value="{{ $d['other_expenses'] }}" class="border-r border-slate-200 px-3 py-2.5 text-right text-purple-900 font-semibold">{{ number_format($d['other_expenses'], 2) }}</td>
                            <!-- Totals & Balance -->
                            <td data-value="{{ $d['total_sales'] }}" class="border-r border-slate-200 px-3 py-2.5 text-right font-bold text-slate-900">{{ number_format($d['total_sales'], 2) }}</td>
                            <td data-value="{{ $d['total_expenses'] }}" class="border-r border-slate-200 px-3 py-2.5 text-right font-bold text-rose-700">{{ number_format($d['total_expenses'], 2) }}</td>
                            <td data-value="{{ $d['balance'] }}" class="px-4 py-2.5 text-right font-black {{ $isPos ? 'text-emerald-700' : 'text-rose-600' }}">
                                {{ $d['balance'] < 0 ? '-' : '' }}{{ number_format(abs($d['balance']), 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr class="no-sort">
                            <td colspan="11" class="px-5 py-8 text-center text-xs text-slate-400 font-sans">
                                No records found for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <!-- PERIOD TOTALS FOOTER -->
                <tfoot class="border-t-2 border-slate-200 bg-slate-100/90 font-mono text-[11px] font-black text-slate-900">
                    <tr>
                        <td class="border-r border-slate-200 px-4 py-3 font-sans text-xs uppercase">Period Total</td>
                        <td class="border-r border-slate-100 px-2.5 py-3 text-right">{{ number_format($summary['fruits_sale'], 2) }}</td>
                        <td class="border-r border-slate-200 px-2.5 py-3 text-right text-amber-900">{{ number_format($summary['fruits_expense'], 2) }}</td>
                        <td class="border-r border-slate-100 px-2.5 py-3 text-right">{{ number_format($summary['veg_sale'], 2) }}</td>
                        <td class="border-r border-slate-200 px-2.5 py-3 text-right text-emerald-900">{{ number_format($summary['veg_expense'], 2) }}</td>
                        <td class="border-r border-slate-100 px-2.5 py-3 text-right">{{ number_format($summary['stationery_sale'], 2) }}</td>
                        <td class="border-r border-slate-200 px-2.5 py-3 text-right text-blue-900">{{ number_format($summary['stationery_expense'], 2) }}</td>
                        <td class="border-r border-slate-200 px-3 py-3 text-right text-purple-900">{{ number_format($summary['other_expenses'], 2) }}</td>
                        <td class="border-r border-slate-200 px-3 py-3 text-right text-slate-900">{{ number_format($summary['total_sales'], 2) }}</td>
                        <td class="border-r border-slate-200 px-3 py-3 text-right text-rose-800">{{ number_format($summary['total_expenses'], 2) }}</td>
                        <td class="px-4 py-3 text-right font-black {{ $summary['balance'] >= 0 ? 'text-emerald-800' : 'text-rose-700' }}">
                            {{ $summary['balance'] < 0 ? '-' : '' }}₹{{ number_format(abs($summary['balance']), 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- PURCHASER CASH ACCOUNTABILITY SECTION -->
    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs">
        <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:px-6 flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded-md bg-blue-50 px-2 py-0.5 text-[10px] font-black text-blue-700">Accountability</span>
                    <h2 class="text-sm font-extrabold text-slate-900">Purchaser Cash Position</h2>
                </div>
                <p class="text-xs font-semibold text-slate-400">Opening balance, funding, purchases, advance expenses, returns, and net closing position</p>
            </div>
            <span class="rounded-lg bg-slate-200/60 px-2.5 py-1 text-xs font-bold text-slate-700 font-mono">
                {{ count($purchaser_positions) }} Purchasers
            </span>
        </div>

        <div class="overflow-x-auto">
            <table x-data="{
                sortCol: null,
                sortAsc: true,
                sortTable(colIndex, type = 'num') {
                    const tbody = $el.querySelector('tbody');
                    const rows = Array.from(tbody.querySelectorAll('tr:not(.no-sort)'));
                    if (rows.length <= 1) return;

                    if (this.sortCol === colIndex) {
                        this.sortAsc = !this.sortAsc;
                    } else {
                        this.sortCol = colIndex;
                        this.sortAsc = true;
                    }

                    rows.sort((a, b) => {
                        const cellA = a.children[colIndex];
                        const cellB = b.children[colIndex];
                        if (!cellA || !cellB) return 0;

                        let valA = cellA.getAttribute('data-value') ?? cellA.innerText.trim();
                        let valB = cellB.getAttribute('data-value') ?? cellB.innerText.trim();

                        if (type === 'num') {
                            const numA = parseFloat(valA.replace(/[^\d.-]/g, '')) || 0;
                            const numB = parseFloat(valB.replace(/[^\d.-]/g, '')) || 0;
                            return this.sortAsc ? numA - numB : numB - numA;
                        }

                        return this.sortAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
                    });

                    rows.forEach(row => tbody.appendChild(row));
                }
            }" class="w-full text-left text-xs text-slate-600">
                <thead class="border-b border-slate-100 bg-slate-50/50 text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                    <tr>
                        <th @click="sortTable(0, 'text')" class="px-5 py-3 cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Purchaser Name">
                            <div class="flex items-center gap-1">
                                <span>Purchaser</span>
                                <span class="text-[9px]" :class="sortCol === 0 ? 'text-indigo-600 font-black' : 'text-slate-300'" x-text="sortCol === 0 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable(1, 'num')" class="px-3 py-3 text-right cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Opening Balance">
                            <div class="flex items-center justify-end gap-1">
                                <span>Opening (₹)</span>
                                <span class="text-[9px]" :class="sortCol === 1 ? 'text-indigo-600 font-black' : 'text-slate-300'" x-text="sortCol === 1 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable(2, 'num')" class="px-3 py-3 text-right text-indigo-700 cursor-pointer hover:bg-indigo-50 transition select-none" title="Click to sort by Cash Given">
                            <div class="flex items-center justify-end gap-1">
                                <span>Cash Given (₹)</span>
                                <span class="text-[9px]" :class="sortCol === 2 ? 'text-indigo-700 font-black' : 'text-indigo-300'" x-text="sortCol === 2 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable(3, 'num')" class="px-3 py-3 text-right text-slate-700 cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Cash Purchases">
                            <div class="flex items-center justify-end gap-1">
                                <span>Cash Purchases (₹)</span>
                                <span class="text-[9px]" :class="sortCol === 3 ? 'text-indigo-600 font-black' : 'text-slate-300'" x-text="sortCol === 3 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable(4, 'num')" class="px-3 py-3 text-right text-slate-700 cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Expenses">
                            <div class="flex items-center justify-end gap-1">
                                <span>Expenses (₹)</span>
                                <span class="text-[9px]" :class="sortCol === 4 ? 'text-indigo-600 font-black' : 'text-slate-300'" x-text="sortCol === 4 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable(5, 'num')" class="px-3 py-3 text-right text-slate-700 cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Cash Returned">
                            <div class="flex items-center justify-end gap-1">
                                <span>Cash Returned (₹)</span>
                                <span class="text-[9px]" :class="sortCol === 5 ? 'text-indigo-600 font-black' : 'text-slate-300'" x-text="sortCol === 5 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable(6, 'num')" class="px-4 py-3 text-right font-black text-slate-900 cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Closing Position">
                            <div class="flex items-center justify-end gap-1">
                                <span>Closing Position (₹)</span>
                                <span class="text-[9px]" :class="sortCol === 6 ? 'text-indigo-600 font-black' : 'text-slate-300'" x-text="sortCol === 6 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable(7, 'num')" class="px-3 py-3 text-right text-slate-400 cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Credit Purchases">
                            <div class="flex items-center justify-end gap-1">
                                <span>Credit Purchases (₹)</span>
                                <span class="text-[9px]" :class="sortCol === 7 ? 'text-indigo-600 font-black' : 'text-slate-300'" x-text="sortCol === 7 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable(8, 'text')" class="px-4 py-3 text-center cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Status">
                            <div class="flex items-center justify-center gap-1">
                                <span>Status</span>
                                <span class="text-[9px]" :class="sortCol === 8 ? 'text-indigo-600 font-black' : 'text-slate-300'" x-text="sortCol === 8 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($purchaser_positions as $p)
                        <tr class="transition hover:bg-slate-50/80 cursor-pointer"
                            @click="$dispatch('open-drilldown', { metric: 'purchaser_timeline', title: 'Purchaser Timeline ({{ $p['purchaser_name'] }})', purchaserId: {{ $p['purchaser_id'] }} })">
                            <td data-value="{{ $p['purchaser_name'] }}" class="px-5 py-3 font-semibold text-slate-900 flex items-center gap-2">
                                <i data-lucide="user" class="h-4 w-4 text-slate-400"></i>
                                <span>{{ $p['purchaser_name'] }}</span>
                            </td>
                            <td data-value="{{ $p['opening_position'] }}" class="px-3 py-3 text-right font-mono">{{ number_format($p['opening_position'], 2) }}</td>
                            <td data-value="{{ $p['cash_given'] }}" class="px-3 py-3 text-right font-mono font-bold text-indigo-700">{{ number_format($p['cash_given'], 2) }}</td>
                            <td data-value="{{ $p['cash_purchases'] }}" class="px-3 py-3 text-right font-mono text-slate-800">{{ number_format($p['cash_purchases'], 2) }}</td>
                            <td data-value="{{ $p['purchaser_expenses'] }}" class="px-3 py-3 text-right font-mono text-slate-800">{{ number_format($p['purchaser_expenses'], 2) }}</td>
                            <td data-value="{{ $p['cash_returned'] }}" class="px-3 py-3 text-right font-mono text-slate-800">{{ number_format($p['cash_returned'], 2) }}</td>
                            <td data-value="{{ $p['closing_position'] }}" class="px-4 py-3 text-right font-mono font-black {{ $p['closing_position'] >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                {{ $p['closing_position'] < 0 ? '-' : '' }}₹{{ number_format(abs($p['closing_position']), 2) }}
                            </td>
                            <td data-value="{{ $p['credit_purchases'] }}" class="px-3 py-3 text-right font-mono text-slate-400">{{ number_format($p['credit_purchases'], 2) }}</td>
                            <td data-value="{{ $p['status'] ?? '' }}" class="px-4 py-3 text-center">
                                <span class="inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-black {{ $p['status_class'] }}">
                                    {{ $p['status'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr class="no-sort">
                            <td colspan="9" class="px-5 py-6 text-center text-xs text-slate-400">
                                No purchaser cash movements found for the selected period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- DRILLDOWN MODAL -->
    @include('admin.cashbook.monthly-report.partials.drilldown-modal')
</div>
@endsection
