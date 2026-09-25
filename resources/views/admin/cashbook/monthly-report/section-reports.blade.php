@extends('admin.cashbook.layouts.app')

@section('title', 'Dynamic Cashbook Section Reports — Green Leaf')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-16" x-data="{ currentTab: '{{ $selected_section_key }}' }">
    <!-- TOP HEADER -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-black text-indigo-700">Dynamic Section Matrix</span>
                <span class="text-xs font-semibold text-slate-400">Cashbook Monthly Report</span>
            </div>
            <h1 class="mt-1 text-xl font-black tracking-tight text-slate-900 sm:text-2xl">
                Monthly Section Reports
            </h1>
            <p class="text-xs font-medium text-slate-500">
                Independent daily breakdown and accounting reconciliation for each configured Cashbook section.
            </p>
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
            <a href="{{ route('admin.cashbook.monthly-report.other-expenses', array_merge(['month' => $period['month'] ?? request('month')], request()->query())) }}"
               class="rounded-lg px-3 py-1.5 transition {{ request()->routeIs('admin.cashbook.monthly-report.other-expenses') ? 'bg-white text-slate-900 shadow-xs' : 'hover:text-slate-900' }}">
                Other Expense
            </a>
            <a href="{{ route('admin.cashbook.monthly-report.expense-report', array_merge(['month' => $period['month'] ?? request('month')], request()->query())) }}"
               class="rounded-lg px-3 py-1.5 transition {{ request()->routeIs('admin.cashbook.monthly-report.expense-report') ? 'bg-white text-slate-900 shadow-xs' : 'hover:text-slate-900' }}">
                Expense Report
            </a>
        </div>
    </div>

    <!-- SHARED PERIOD FILTER -->
    @include('admin.cashbook.monthly-report.partials.period-filter', ['reportType' => 'section-reports'])

    <!-- RECONCILIATION & READINESS STATUS -->
    @include('admin.cashbook.monthly-report.partials.reconciliation-badge')

    <!-- TOP-LEVEL EXPORTS & ACTIONS -->
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200/80 bg-white px-5 py-3 shadow-xs">
        <div class="flex items-center gap-2">
            <span class="flex h-2 w-2 rounded-full bg-emerald-500"></span>
            <span class="text-xs font-bold text-slate-700">
                {{ count($sections) }} Active Configured {{ Str::plural('Section', count($sections)) }}
            </span>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.cashbook.finance.purchase.purchaser-expenses', array_merge(['month' => $period['month'] ?? request('month')], request()->query())) }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700 shadow-2xs transition hover:bg-indigo-100 hover:text-indigo-900">
                <i data-lucide="users" class="h-3.5 w-3.5 text-indigo-600"></i>
                Purchaser Wise Report
            </a>
            <div class="h-4 w-px bg-slate-200"></div>
            <a href="{{ route('admin.cashbook.monthly-report.section-reports.export.csv', array_merge(['section' => request('section')], request()->query())) }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-emerald-700">
                <i data-lucide="file-spreadsheet" class="h-3.5 w-3.5 text-emerald-600"></i>
                CSV
            </a>
            <a href="{{ route('admin.cashbook.monthly-report.section-reports.export.excel', array_merge(['section' => request('section')], request()->query())) }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-blue-700">
                <i data-lucide="table" class="h-3.5 w-3.5 text-blue-600"></i>
                Excel
            </a>
            <a href="{{ route('admin.cashbook.monthly-report.section-reports.export.pdf', array_merge(['section' => request('section'), 'download' => 1], request()->query())) }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-rose-700">
                <i data-lucide="file-text" class="h-3.5 w-3.5 text-rose-600"></i>
                PDF
            </a>
            <a href="{{ route('admin.cashbook.monthly-report.section-reports.print', array_merge(['section' => request('section')], request()->query())) }}" target="_blank"
               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-indigo-700">
                <i data-lucide="printer" class="h-3.5 w-3.5 text-slate-500"></i>
                Print
            </a>
        </div>
    </div>

    <!-- DYNAMIC SUMMARY CARDS (DYNAMICALLY LOOPED FROM CASHBOOK SETTINGS) -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-{{ min(max(count($sections), 2), 4) }}">
        @foreach($sections as $secKey => $sec)
            @php
                $isTrading = ($sec['type'] === 'trading');
                $isPos = $isTrading ? ($sec['summary']['balance'] >= 0) : true;
            @endphp
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 cursor-pointer transition hover:border-indigo-300 hover:shadow-md"
                 :class="currentTab === '{{ $secKey }}' ? 'ring-2 ring-indigo-500 border-transparent bg-indigo-50/20' : ''"
                 @click="currentTab = (currentTab === '{{ $secKey }}' ? 'all' : '{{ $secKey }}')">
                <div class="flex items-center justify-between text-xs font-bold text-slate-900">
                    <span class="truncate">{{ $sec['name'] }}</span>
                    @if($isTrading)
                        <span class="rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700">Trading</span>
                    @else
                        <span class="rounded bg-purple-50 px-1.5 py-0.5 text-[10px] font-semibold text-purple-700">Expense</span>
                    @endif
                </div>

                @if($isTrading)
                    <div class="mt-2 text-sm font-black text-slate-900 font-mono">
                        ₹{{ number_format($sec['summary']['sales'], 2) }}
                        <span class="text-[10px] font-normal text-slate-400">Sale</span>
                    </div>
                    <div class="mt-0.5 text-xs font-semibold text-amber-700 font-mono">
                        ₹{{ number_format($sec['summary']['purchases'], 2) }}
                        <span class="text-[10px] font-normal text-slate-400">Purchase</span>
                    </div>
                    <div class="mt-1 text-xs font-bold font-mono {{ $isPos ? 'text-emerald-700' : 'text-rose-600' }}">
                        {{ $sec['summary']['balance'] < 0 ? '-' : '' }}₹{{ number_format(abs($sec['summary']['balance']), 2) }}
                        <span class="text-[10px] font-normal text-slate-400">Bal</span>
                    </div>
                @else
                    <div class="mt-2 text-sm font-black text-purple-900 font-mono">
                        ₹{{ number_format($sec['summary']['total_expenses'], 2) }}
                    </div>
                    <div class="mt-1 text-[11px] font-medium text-slate-500 truncate">
                        Salary, Rent, Fuel, Mess, etc.
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <!-- DYNAMIC SECTION NAV TABS -->
    <div class="flex flex-wrap items-center gap-1.5 border-b border-slate-200 pb-2">
        <button type="button" @click="currentTab = 'all'"
                class="rounded-xl px-3.5 py-1.5 text-xs font-extrabold transition"
                :class="currentTab === 'all' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
            All Sections
        </button>
        @foreach($sections as $secKey => $sec)
            <button type="button" @click="currentTab = '{{ $secKey }}'"
                    class="rounded-xl px-3.5 py-1.5 text-xs font-extrabold transition"
                    :class="currentTab === '{{ $secKey }}' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                {{ $sec['name'] }}
            </button>
        @endforeach
    </div>

    <!-- DYNAMIC SECTION TABLES (EACH SECTION GETS ITS OWN CLEAN REPORT BLOCK) -->
    <div class="space-y-8">
        @foreach($sections as $secKey => $sec)
            @php
                $isTrading = ($sec['type'] === 'trading');
            @endphp
            <div x-show="currentTab === 'all' || currentTab === '{{ $secKey }}'" x-cloak
                 class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs">
                <!-- Section Header Card -->
                <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-black text-slate-900">{{ $sec['name'] }}</h2>
                            @if($isTrading)
                                <span class="rounded-lg bg-emerald-50 px-2 py-0.5 text-[10px] font-black text-emerald-700">Product & Trading</span>
                            @else
                                <span class="rounded-lg bg-purple-50 px-2 py-0.5 text-[10px] font-black text-purple-700">Operating Overhead</span>
                            @endif
                        </div>
                        <p class="mt-0.5 text-xs font-medium text-slate-400">
                            {{ $isTrading ? 'Daily sales, purchase procurement, and net trading margins' : 'Consolidated operational expenses and business overheads' }}
                        </p>
                    </div>

                    <!-- Section Summary & Section-Specific Export -->
                    <div class="flex flex-wrap items-center gap-3">
                        @if($isTrading)
                            <div class="flex items-center gap-3 rounded-xl bg-white px-3 py-1.5 text-xs font-mono border border-slate-200/80 shadow-2xs">
                                <div>
                                    <span class="text-[10px] font-sans font-semibold text-slate-400">Sales:</span>
                                    <span class="font-bold text-slate-900">₹{{ number_format($sec['summary']['sales'], 2) }}</span>
                                </div>
                                <div class="h-3 w-px bg-slate-200"></div>
                                <div>
                                    <span class="text-[10px] font-sans font-semibold text-slate-400">Purchases:</span>
                                    <span class="font-bold text-amber-800">₹{{ number_format($sec['summary']['purchases'], 2) }}</span>
                                </div>
                                <div class="h-3 w-px bg-slate-200"></div>
                                <div>
                                    <span class="text-[10px] font-sans font-semibold text-slate-400">Bal:</span>
                                    <span class="font-black {{ $sec['summary']['balance'] >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                        {{ $sec['summary']['balance'] < 0 ? '-' : '' }}₹{{ number_format(abs($sec['summary']['balance']), 2) }}
                                    </span>
                                </div>
                            </div>
                        @else
                            <div class="flex items-center gap-2 rounded-xl bg-white px-3 py-1.5 text-xs font-mono border border-slate-200/80 shadow-2xs">
                                <span class="text-[10px] font-sans font-semibold text-slate-400">Total Expense:</span>
                                <span class="font-black text-purple-900">₹{{ number_format($sec['summary']['total_expenses'], 2) }}</span>
                            </div>
                        @endif

                        <!-- Section Export Actions -->
                        <div class="inline-flex rounded-lg bg-slate-100 p-0.5 text-[11px] font-bold text-slate-600">
                            <a href="{{ route('admin.cashbook.monthly-report.section-reports.export.csv', array_merge(['section' => $secKey], request()->query())) }}"
                               class="rounded px-2 py-1 transition hover:bg-white hover:text-emerald-700" title="Export CSV for {{ $sec['name'] }}">
                                CSV
                            </a>
                            <a href="{{ route('admin.cashbook.monthly-report.section-reports.export.excel', array_merge(['section' => $secKey], request()->query())) }}"
                               class="rounded px-2 py-1 transition hover:bg-white hover:text-blue-700" title="Export Excel for {{ $sec['name'] }}">
                                Excel
                            </a>
                            <a href="{{ route('admin.cashbook.monthly-report.section-reports.export.pdf', array_merge(['section' => $secKey, 'download' => 1], request()->query())) }}"
                               class="rounded px-2 py-1 transition hover:bg-white hover:text-rose-700" title="Download PDF for {{ $sec['name'] }}">
                                PDF
                            </a>
                            <a href="{{ route('admin.cashbook.monthly-report.section-reports.print', array_merge(['section' => $secKey], request()->query())) }}" target="_blank"
                               class="rounded px-2 py-1 transition hover:bg-white hover:text-indigo-700" title="Print {{ $sec['name'] }}">
                                Print
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Daily Table for This Section -->
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
                        @if($isTrading)
                            <thead class="border-b border-slate-200 bg-slate-50/90 text-center text-[10px] font-black uppercase tracking-wider text-slate-600">
                                <tr>
                                    <th @click="sortTable(0, 'date')" class="border-r border-slate-200 px-4 py-2.5 text-left text-xs text-slate-900 cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Date">
                                        <div class="flex items-center gap-1.5">
                                            <span>Date</span>
                                            <span class="text-[10px]" :class="sortCol === 0 ? 'text-indigo-600 font-black' : 'text-slate-300'" x-text="sortCol === 0 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                        </div>
                                    </th>
                                    <th @click="sortTable(1, 'num')" class="border-r border-slate-200 px-3 py-2.5 text-right text-slate-800 cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Sales">
                                        <div class="flex items-center justify-end gap-1">
                                            <span>Sale (₹)</span>
                                            <span class="text-[10px]" :class="sortCol === 1 ? 'text-indigo-600 font-black' : 'text-slate-300'" x-text="sortCol === 1 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                        </div>
                                    </th>
                                    <th @click="sortTable(2, 'num')" class="border-r border-slate-200 px-3 py-2.5 text-right text-amber-800 cursor-pointer hover:bg-amber-50 transition select-none" title="Click to sort by Purchases">
                                        <div class="flex items-center justify-end gap-1">
                                            <span>Purchase (₹)</span>
                                            <span class="text-[10px]" :class="sortCol === 2 ? 'text-amber-800 font-black' : 'text-amber-300'" x-text="sortCol === 2 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                        </div>
                                    </th>
                                    <th @click="sortTable(3, 'num')" class="px-4 py-2.5 text-right font-black text-slate-900 cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Net Balance">
                                        <div class="flex items-center justify-end gap-1">
                                            <span>Balance (₹)</span>
                                            <span class="text-[10px]" :class="sortCol === 3 ? 'text-indigo-600 font-black' : 'text-slate-300'" x-text="sortCol === 3 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium font-mono text-[11px]">
                                @forelse($sec['daily_rows'] as $d)
                                    @php $isPos = $d['balance'] >= 0; @endphp
                                    <tr class="transition hover:bg-slate-50/80">
                                        <td data-date="{{ $d['date'] }}" class="border-r border-slate-100 px-4 py-2.5 font-sans font-semibold text-slate-900 whitespace-nowrap">
                                            {{ $d['formatted_date'] }}
                                        </td>
                                        <td data-value="{{ $d['sale'] }}" class="border-r border-slate-100 px-3 py-2.5 text-right text-slate-800">
                                            {{ number_format($d['sale'], 2) }}
                                        </td>
                                        <td data-value="{{ $d['purchase'] }}" class="border-r border-slate-100 px-3 py-2.5 text-right text-amber-800">
                                            {{ number_format($d['purchase'], 2) }}
                                        </td>
                                        <td data-value="{{ $d['balance'] }}" class="px-4 py-2.5 text-right font-black {{ $isPos ? 'text-emerald-700' : 'text-rose-600' }}">
                                            {{ $d['balance'] < 0 ? '-' : '' }}{{ number_format(abs($d['balance']), 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="no-sort">
                                        <td colspan="4" class="px-5 py-8 text-center text-xs text-slate-400 font-sans">
                                            No daily records found for this period.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="border-t-2 border-slate-200 bg-slate-100/90 font-mono text-[11px] font-black text-slate-900">
                                <tr>
                                    <td class="border-r border-slate-200 px-4 py-3 font-sans text-xs uppercase">Monthly Total</td>
                                    <td class="border-r border-slate-100 px-3 py-3 text-right text-slate-900">
                                        {{ number_format($sec['summary']['sales'], 2) }}
                                    </td>
                                    <td class="border-r border-slate-100 px-3 py-3 text-right text-amber-900">
                                        {{ number_format($sec['summary']['purchases'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-black {{ $sec['summary']['balance'] >= 0 ? 'text-emerald-800' : 'text-rose-700' }}">
                                        {{ $sec['summary']['balance'] < 0 ? '-' : '' }}₹{{ number_format(abs($sec['summary']['balance']), 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        @else
                            <!-- Expense-only Daily Table -->
                            <thead class="border-b border-slate-200 bg-slate-50/90 text-[10px] font-black uppercase tracking-wider text-slate-600">
                                <tr>
                                    <th @click="sortTable(0, 'date')" class="border-r border-slate-200 px-4 py-2.5 text-left text-xs text-slate-900 cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Date">
                                        <div class="flex items-center gap-1.5">
                                            <span>Date</span>
                                            <span class="text-[10px]" :class="sortCol === 0 ? 'text-indigo-600 font-black' : 'text-slate-300'" x-text="sortCol === 0 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                        </div>
                                    </th>
                                    <th @click="sortTable(1, 'num')" class="px-4 py-2.5 text-right text-xs font-black text-purple-900 cursor-pointer hover:bg-purple-50 transition select-none" title="Click to sort by Operating Expense">
                                        <div class="flex items-center justify-end gap-1">
                                            <span>Operating Expense (₹)</span>
                                            <span class="text-[10px]" :class="sortCol === 1 ? 'text-purple-700 font-black' : 'text-purple-300'" x-text="sortCol === 1 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium font-mono text-[11px]">
                                @forelse($sec['daily_rows'] as $d)
                                    <tr class="transition hover:bg-slate-50/80">
                                        <td data-date="{{ $d['date'] }}" class="border-r border-slate-100 px-4 py-2.5 font-sans font-semibold text-slate-900 whitespace-nowrap">
                                            {{ $d['formatted_date'] }}
                                        </td>
                                        <td data-value="{{ $d['expense'] }}" class="px-4 py-2.5 text-right font-semibold text-purple-900">
                                            {{ number_format($d['expense'], 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="no-sort">
                                        <td colspan="2" class="px-5 py-8 text-center text-xs text-slate-400 font-sans">
                                            No expense records found for this period.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="border-t-2 border-slate-200 bg-slate-100/90 font-mono text-[11px] font-black text-slate-900">
                                <tr>
                                    <td class="border-r border-slate-200 px-4 py-3 font-sans text-xs uppercase">Monthly Total</td>
                                    <td class="px-4 py-3 text-right font-black text-purple-950">
                                        ₹{{ number_format($sec['summary']['total_expenses'], 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
