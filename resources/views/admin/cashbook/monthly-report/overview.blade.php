@extends('admin.cashbook.layouts.app')

@section('title', 'Green Leaf Monthly Report — Consolidated Overview')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-16" x-data>
    <!-- TOP HEADER -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-black text-emerald-700">Financial Consolidation</span>
                <span class="text-xs font-semibold text-slate-400">Cashbook Monthly Report</span>
            </div>
            <h1 class="mt-1 text-xl font-black tracking-tight text-slate-900 sm:text-2xl">
                Green Leaf Monthly Report
            </h1>
            <p class="text-xs font-medium text-slate-500">
                Consolidated overview combining client shops, direct sales, warehouse sales, purchases, and operating expenses.
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
    @include('admin.cashbook.monthly-report.partials.period-filter', ['reportType' => 'overview'])

    <!-- OVERVIEW METRIC CARDS -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <!-- TOTAL SALES CARD -->
        <div class="group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition hover:border-indigo-300 hover:shadow-md cursor-pointer"
             onclick="window.openReportDrilldown('total_sales', 'Total Sales Breakdown')">
            <div class="flex items-center justify-between text-xs font-bold text-slate-500">
                <span class="uppercase tracking-wider">Total Sales</span>
                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-black text-emerald-700">Revenue</span>
            </div>
            <div class="mt-3 flex items-baseline justify-between">
                <div class="font-mono text-2xl font-black tracking-tight text-slate-900">
                    ₹{{ number_format($summary['total_sales'], 2) }}
                </div>
                <i data-lucide="trending-up" class="h-5 w-5 text-emerald-500 opacity-70 group-hover:scale-110 transition"></i>
            </div>
            <div class="mt-3 border-t border-slate-100 pt-2.5 flex items-center justify-between text-[11px] font-semibold text-slate-500">
                <span>Client: <strong class="text-slate-800">₹{{ number_format($summary['client_sales'], 2) }}</strong></span>
                <span>Other: <strong class="text-slate-800">₹{{ number_format($summary['all_other_sales'], 2) }}</strong></span>
            </div>
        </div>

        <!-- TOTAL EXPENSES CARD -->
        <div class="group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition hover:border-rose-300 hover:shadow-md cursor-pointer"
             onclick="window.openReportDrilldown('total_expenses', 'Total Expenses Breakdown')">
            <div class="flex items-center justify-between text-xs font-bold text-slate-500">
                <span class="uppercase tracking-wider">Total Expenses</span>
                <span class="rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-black text-rose-700">Outflow</span>
            </div>
            <div class="mt-3 flex items-baseline justify-between">
                <div class="font-mono text-2xl font-black tracking-tight text-slate-900">
                    ₹{{ number_format($summary['total_expenses'], 2) }}
                </div>
                <i data-lucide="trending-down" class="h-5 w-5 text-rose-500 opacity-70 group-hover:scale-110 transition"></i>
            </div>
            <div class="mt-3 border-t border-slate-100 pt-2.5 flex items-center justify-between text-[11px] font-semibold text-slate-500">
                <span>Purchases: <strong class="text-slate-800">₹{{ number_format($summary['product_expenses'], 2) }}</strong></span>
                <span>Operating: <strong class="text-slate-800">₹{{ number_format($summary['operating_expenses'], 2) }}</strong></span>
            </div>
        </div>

        <!-- NET BALANCE CARD -->
        @php
            $isPositive = $summary['balance'] >= 0;
        @endphp
        <div class="group relative overflow-hidden rounded-2xl border {{ $isPositive ? 'border-emerald-200 bg-emerald-50/20' : 'border-rose-200 bg-rose-50/20' }} p-5 shadow-xs transition hover:shadow-md">
            <div class="flex items-center justify-between text-xs font-bold text-slate-500">
                <span class="uppercase tracking-wider">Net Balance</span>
                <span class="rounded-full px-2 py-0.5 text-[10px] font-black {{ $isPositive ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                    {{ $isPositive ? 'Surplus' : 'Deficit' }}
                </span>
            </div>
            <div class="mt-3 flex items-baseline justify-between">
                <div class="font-mono text-2xl font-black tracking-tight {{ $isPositive ? 'text-emerald-700' : 'text-rose-700' }}">
                    {{ $summary['balance'] < 0 ? '-' : '' }}₹{{ number_format(abs($summary['balance']), 2) }}
                </div>
                <i data-lucide="scale" class="h-5 w-5 {{ $isPositive ? 'text-emerald-600' : 'text-rose-600' }} opacity-80"></i>
            </div>
            <div class="mt-3 border-t border-slate-200/60 pt-2.5 text-[11px] font-semibold text-slate-500">
                Formula: <span class="font-mono text-slate-700">Total Sales (₹{{ number_format($summary['total_sales'], 2) }}) − Expenses (₹{{ number_format($summary['total_expenses'], 2) }})</span>
            </div>
        </div>
    </div>

    <!-- DAILY CONSOLIDATED TABLE -->
    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs">
        <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:px-6 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-extrabold text-slate-900">Daily Financial Performance</h2>
                <p class="text-xs font-semibold text-slate-400">Breakdown of Sales, Expenses, and Balance by date</p>
            </div>
            <span class="rounded-lg bg-slate-200/60 px-2.5 py-1 font-mono text-xs font-bold text-slate-700">
                {{ count($daily_rows) }} Days
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="border-b border-slate-100 bg-slate-50/50 text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3">Date</th>
                        <th class="px-4 py-3 text-right">Client Sales (₹)</th>
                        <th class="px-4 py-3 text-right">All Other Sales (₹)</th>
                        <th class="px-4 py-3 text-right font-black text-slate-700">Total Sales (₹)</th>
                        <th class="px-4 py-3 text-right">Expenses (₹)</th>
                        <th class="px-5 py-3 text-right font-black text-slate-900">Balance (₹)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($daily_rows as $row)
                        @php
                            $isDayPositive = $row['balance'] >= 0;
                        @endphp
                        <tr class="transition hover:bg-indigo-50/30">
                            <td class="px-5 py-3 font-semibold text-slate-900 whitespace-nowrap">
                                {{ $row['formatted_date'] }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono">
                                <button type="button"
                                        onclick="window.openReportDrilldown('client_sales', 'Client Sales ({{ $row['formatted_date'] }})', '{{ $row['date'] }}')"
                                        class="text-indigo-600 hover:underline cursor-pointer">
                                    {{ number_format($row['client_sales'], 2) }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right font-mono">
                                <button type="button"
                                        onclick="window.openReportDrilldown('all_other_sales', 'All Other Sales ({{ $row['formatted_date'] }})', '{{ $row['date'] }}')"
                                        class="text-indigo-600 hover:underline cursor-pointer">
                                    {{ number_format($row['all_other_sales'], 2) }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                                {{ number_format($row['total_sales'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-rose-600">
                                <button type="button"
                                        onclick="window.openReportDrilldown('total_expenses', 'Expenses ({{ $row['formatted_date'] }})', '{{ $row['date'] }}')"
                                        class="hover:underline cursor-pointer text-rose-600 font-semibold">
                                    {{ number_format($row['total_expenses'], 2) }}
                                </button>
                            </td>
                            <td class="px-5 py-3 text-right font-mono font-black {{ $isDayPositive ? 'text-emerald-700' : 'text-rose-600' }}">
                                {{ $row['balance'] < 0 ? '-' : '' }}{{ number_format(abs($row['balance']), 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-xs text-slate-400">
                                No activity records for the selected reporting period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <!-- STICKY PERIOD TOTALS FOOTER -->
                <tfoot class="border-t-2 border-slate-200 bg-slate-100/80 font-black text-slate-900">
                    <tr>
                        <td class="px-5 py-3.5 text-xs font-black uppercase">Period Total</td>
                        <td class="px-4 py-3.5 text-right font-mono text-xs">{{ number_format($summary['client_sales'], 2) }}</td>
                        <td class="px-4 py-3.5 text-right font-mono text-xs">{{ number_format($summary['all_other_sales'], 2) }}</td>
                        <td class="px-4 py-3.5 text-right font-mono text-xs text-slate-900">{{ number_format($summary['total_sales'], 2) }}</td>
                        <td class="px-4 py-3.5 text-right font-mono text-xs text-rose-700">{{ number_format($summary['total_expenses'], 2) }}</td>
                        <td class="px-5 py-3.5 text-right font-mono text-sm {{ $summary['balance'] >= 0 ? 'text-emerald-800' : 'text-rose-700' }}">
                            {{ $summary['balance'] < 0 ? '-' : '' }}₹{{ number_format(abs($summary['balance']), 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- CLIENT & SHOP FINANCIAL BREAKDOWN SECTION -->
    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs">
        <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:px-6">
            <h2 class="text-sm font-extrabold text-slate-900">Client &amp; Owned-Shop Financial Breakdown</h2>
            <p class="text-xs font-semibold text-slate-400">Detailed performance grouped by client and shop</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="border-b border-slate-100 bg-slate-50/50 text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3">Client / Shop</th>
                        <th class="px-4 py-3 text-right">Sales (₹)</th>
                        <th class="px-4 py-3 text-right">Purchase (₹)</th>
                        <th class="px-4 py-3 text-right">Operating Expense (₹)</th>
                        <th class="px-5 py-3 text-right font-black text-slate-900">Net Balance (₹)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($clients as $c)
                        <!-- Client Header Row -->
                        <tr class="bg-slate-50/60 font-black text-slate-900">
                            <td class="px-5 py-2.5 flex items-center gap-2">
                                <i data-lucide="building-2" class="h-4 w-4 text-indigo-600"></i>
                                <span>{{ $c['client_name'] }}</span>
                                <span class="rounded-md bg-indigo-100/60 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700">{{ count($c['shops']) }} shop(s)</span>
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono text-indigo-900">
                                <button type="button"
                                        onclick="window.openReportDrilldown('client_sales', '{{ addslashes($c['client_name']) }} — Sales', null, null, null, {{ $c['client_id'] }})"
                                        class="hover:underline font-bold text-indigo-900 cursor-pointer">
                                    ₹{{ number_format($c['sales'], 2) }}
                                </button>
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono text-slate-700">
                                <button type="button"
                                        onclick="window.openReportDrilldown('product_expenses', '{{ addslashes($c['client_name']) }} — Purchases', null, null, null, {{ $c['client_id'] }})"
                                        class="hover:underline font-bold text-slate-800 cursor-pointer">
                                    ₹{{ number_format($c['product_expenses'], 2) }}
                                </button>
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono text-slate-700">
                                <button type="button"
                                        onclick="window.openReportDrilldown('operating_expenses', '{{ addslashes($c['client_name']) }} — Operating Expenses', null, null, null, {{ $c['client_id'] }})"
                                        class="hover:underline font-bold text-slate-800 cursor-pointer">
                                    ₹{{ number_format($c['operating_expenses'], 2) }}
                                </button>
                            </td>
                            <td class="px-5 py-2.5 text-right font-mono {{ $c['balance'] >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                {{ $c['balance'] < 0 ? '-' : '' }}₹{{ number_format(abs($c['balance']), 2) }}
                            </td>
                        </tr>

                        <!-- Shop Rows for this Client -->
                        @foreach($c['shops'] as $s)
                            <tr class="transition hover:bg-slate-50/80 font-medium">
                                <td class="px-5 py-2 pl-10 text-slate-700 flex items-center gap-1.5">
                                    <i data-lucide="store" class="h-3.5 w-3.5 text-slate-400"></i>
                                    <span>{{ $s['shop_name'] }}</span>
                                    <span class="text-[10px] font-mono text-slate-400">({{ $s['shop_code'] }})</span>
                                </td>
                                <td class="px-4 py-2 text-right font-mono text-slate-800">
                                    <button type="button"
                                            onclick="window.openReportDrilldown('client_sales', '{{ addslashes($s['shop_name']) }} — Sales', null, null, {{ $s['shop_id'] }}, null)"
                                            class="hover:underline text-slate-800 cursor-pointer">
                                        {{ number_format($s['sales'], 2) }}
                                    </button>
                                </td>
                                <td class="px-4 py-2 text-right font-mono text-slate-600">
                                    <button type="button"
                                            onclick="window.openReportDrilldown('product_expenses', '{{ addslashes($s['shop_name']) }} — Purchases', null, null, {{ $s['shop_id'] }}, null)"
                                            class="hover:underline text-slate-700 cursor-pointer">
                                        {{ number_format($s['product_expenses'], 2) }}
                                    </button>
                                </td>
                                <td class="px-4 py-2 text-right font-mono text-slate-600">
                                    <button type="button"
                                            onclick="window.openReportDrilldown('operating_expenses', '{{ addslashes($s['shop_name']) }} — Operating Expenses', null, null, {{ $s['shop_id'] }}, null)"
                                            class="hover:underline text-indigo-600 font-semibold cursor-pointer">
                                        {{ number_format($s['operating_expenses'], 2) }}
                                    </button>
                                </td>
                                <td class="px-5 py-2 text-right font-mono font-bold {{ $s['balance'] >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                    {{ $s['balance'] < 0 ? '-' : '' }}{{ number_format(abs($s['balance']), 2) }}
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-6 text-center text-xs text-slate-400">
                                No client or owned shops found with configured activity.
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
