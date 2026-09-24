@extends('admin.cashbook.layouts.app')

@section('title', 'Other Operating Expenses — Green Leaf Monthly Report')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-16" x-data>
    <!-- TOP HEADER -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="rounded-lg bg-purple-50 px-2.5 py-1 text-xs font-black text-purple-700">Operating Overheads</span>
                <span class="text-xs font-semibold text-slate-400">Cashbook Monthly Report</span>
            </div>
            <h1 class="mt-1 text-xl font-black tracking-tight text-slate-900 sm:text-2xl">
                Other Operating Expense Details
            </h1>
            <p class="text-xs font-medium text-slate-500">
                Category-wise breakdown and itemized transaction log across Shop Cashbook, Procurement, Purchaser Other, and Company expenses.
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
    @include('admin.cashbook.monthly-report.partials.period-filter', ['reportType' => 'other-expenses'])

    <!-- RECONCILIATION & READINESS STATUS -->
    @include('admin.cashbook.monthly-report.partials.reconciliation-badge')

    <!-- CATEGORY-WISE SUMMARY TABLE -->
    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs">
        <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:px-6 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-extrabold text-slate-900">Operating Expense Category Summary</h2>
                <p class="text-xs font-semibold text-slate-400">Aggregated original categories and their normalized headings</p>
            </div>
            <div class="text-right">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Operating</span>
                <div class="font-mono text-sm font-black text-slate-900">₹{{ number_format($total_operating_expenses, 2) }}</div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="border-b border-slate-100 bg-slate-50/50 text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3">Original Category</th>
                        <th class="px-4 py-3">Normalized Heading</th>
                        <th class="px-4 py-3">Source Channel</th>
                        <th class="px-3 py-3 text-right">Transactions</th>
                        <th class="px-4 py-3 text-right font-black text-slate-700">Amount (₹)</th>
                        <th class="px-5 py-3 text-right">% of Operating</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($summary_by_category as $cat)
                        <tr class="transition hover:bg-slate-50/80">
                            <td class="px-5 py-2.5 font-bold text-slate-900">{{ $cat['category_name'] }}</td>
                            <td class="px-4 py-2.5">
                                <span class="rounded-md bg-slate-100 px-2 py-0.5 font-semibold text-slate-700">
                                    {{ $cat['heading_label'] }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $cat['source_type'] }}</td>
                            <td class="px-3 py-2.5 text-right font-mono">{{ $cat['transaction_count'] }}</td>
                            <td class="px-4 py-2.5 text-right font-mono font-bold text-slate-900">{{ number_format($cat['amount'], 2) }}</td>
                            <td class="px-5 py-2.5 text-right font-mono text-slate-500">{{ number_format($cat['percentage'], 1) }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-6 text-center text-xs text-slate-400">
                                No operating expense categories found for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- DETAILED TRANSACTION LOG SECTION -->
    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs">
        <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:px-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-extrabold text-slate-900">Detailed Transaction Log</h2>
                <p class="text-xs font-semibold text-slate-400">Itemized entries with source, entity, and funding traceability</p>
            </div>

            <!-- SEARCH AND FILTER BAR -->
            <form method="GET" action="{{ route('admin.cashbook.monthly-report.other-expenses') }}" class="flex flex-wrap items-center gap-2">
                @foreach(request()->except(['search', 'heading', 'source_type', 'page']) as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach

                <select name="heading" onchange="this.form.submit()" class="rounded-xl border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-2xs">
                    <option value="">All Headings</option>
                    @foreach($headings_filter_options as $hCode => $hLabel)
                        <option value="{{ $hCode }}" {{ request('heading') === $hCode ? 'selected' : '' }}>{{ $hLabel }}</option>
                    @endforeach
                </select>

                <select name="source_type" onchange="this.form.submit()" class="rounded-xl border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-2xs">
                    <option value="">All Sources</option>
                    @foreach($sources_filter_options as $sKey => $sLabel)
                        <option value="{{ $sKey }}" {{ request('source_type') === $sKey ? 'selected' : '' }}>{{ $sLabel }}</option>
                    @endforeach
                </select>

                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search description, reference..."
                           class="w-48 rounded-xl border border-slate-200 bg-white pl-8 pr-3 py-1 text-xs font-medium text-slate-800 shadow-2xs focus:border-indigo-500 focus:outline-hidden focus:ring-1 focus:ring-indigo-500">
                    <i data-lucide="search" class="absolute left-2.5 top-1.5 h-3.5 w-3.5 text-slate-400"></i>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="border-b border-slate-100 bg-slate-50/50 text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3">Date</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Final Heading</th>
                        <th class="px-4 py-3">Source Channel</th>
                        <th class="px-4 py-3">Entity / Entity Name</th>
                        <th class="px-4 py-3">Description / Note</th>
                        <th class="px-3 py-3">Reference</th>
                        <th class="px-5 py-3 text-right font-black text-slate-900">Amount (₹)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($detailed_rows as $row)
                        <tr class="transition hover:bg-slate-50/80">
                            <td class="px-5 py-2.5 font-semibold text-slate-900 whitespace-nowrap">{{ $row['business_date'] }}</td>
                            <td class="px-4 py-2.5 font-bold text-slate-800">{{ $row['original_category'] }}</td>
                            <td class="px-4 py-2.5">
                                <span class="rounded-md bg-indigo-50 px-2 py-0.5 text-[10px] font-black text-indigo-700">
                                    {{ $row['heading_label'] }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-slate-500 text-[11px]">{{ $row['source_type'] }}</td>
                            <td class="px-4 py-2.5 font-semibold text-slate-900">{{ $row['entity_name'] }}</td>
                            <td class="px-4 py-2.5 text-slate-600 max-w-xs truncate" title="{{ $row['description'] }}">{{ $row['description'] }}</td>
                            <td class="px-3 py-2.5 font-mono text-[11px] text-slate-500">{{ $row['reference'] }}</td>
                            <td class="px-5 py-2.5 text-right font-mono font-black text-slate-900">{{ number_format($row['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-8 text-center text-xs text-slate-400">
                                No matching expense transactions found for the specified filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($detailed_rows->hasPages())
            <div class="border-t border-slate-100 bg-slate-50/60 p-4">
                {{ $detailed_rows->links() }}
            </div>
        @endif
    </div>

    <!-- DRILLDOWN MODAL -->
    @include('admin.cashbook.monthly-report.partials.drilldown-modal')
</div>
@endsection
