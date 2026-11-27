@extends('admin.cashbook.layouts.app')

@section('title', 'Shop Credit Note Report — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="bar-chart-3" class="w-5 h-5 text-emerald-600"></i> Shop Credit Note Report
@endsection

@section('header_subtitle')
    Audited Detailed (Shop &amp; Bill Wise) and Summary (Daily &amp; Shop Wise) Credit Note Reporting &amp; Exports.
@endsection

@section('content')
@php
    $overview = $report['overview'];
    $exportParams = array_filter([
        'timeframe' => $filters['timeframe'],
        'from_date' => $filters['from_date'],
        'to_date' => $filters['to_date'],
        'shop_id' => $filters['shop_id'],
    ], fn ($v): bool => $v !== null && $v !== '');
@endphp

<div x-data="{ exporting: false }" class="mx-auto max-w-[96rem] space-y-5">

    <!-- Top Sub-Nav Header -->
    @include('admin.cashbook.reports.shops.partials.header', ['activeTab' => 'credit-note-report'])

    <!-- Report Summary Cards (4 Cards) -->
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Total Credit Notes</span>
            <span class="mt-1 block font-mono text-xl font-bold text-slate-900">
                {{ number_format((int) $overview['total_credit_notes']) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-slate-500">Approved credit documents</span>
        </div>

        <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/40 p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-emerald-800">Total Credit Amount</span>
            <span class="mt-1 block font-mono text-xl font-bold text-emerald-700">
                ₹{{ number_format((float) $overview['total_credit_amount'], 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-emerald-600">{{ $overview['period_label'] }}</span>
        </div>

        <div class="rounded-2xl border border-blue-200/80 bg-blue-50/40 p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-blue-800">Total Affected Shops</span>
            <span class="mt-1 block font-mono text-xl font-bold text-blue-700">
                {{ number_format((int) $overview['total_affected_shops']) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-blue-600">Distinct shops in period</span>
        </div>

        <div class="rounded-2xl border border-indigo-200/80 bg-indigo-50/40 p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-indigo-800">Total Affected Invoices</span>
            <span class="mt-1 block font-mono text-xl font-bold text-indigo-700">
                {{ number_format((int) $overview['total_affected_invoices']) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-indigo-600">Distinct bills reconciled</span>
        </div>
    </div>

    <!-- Filters & Presets -->
    <div class="white-card rounded-3xl p-4 sm:p-5 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div class="inline-flex flex-wrap items-center gap-1 rounded-2xl bg-slate-100 p-1" role="group" aria-label="Date preset filters">
                @foreach ([
                    'today' => 'Today',
                    'yesterday' => 'Yesterday',
                    'this_week' => 'This Week',
                    'this_month' => 'This Month',
                    'custom' => 'Custom Period',
                ] as $presetKey => $presetLabel)
                    <a href="{{ route('admin.cashbook.reports.shops.credit-notes.report', ['timeframe' => $presetKey, 'shop_id' => $filters['shop_id'], 'report_type' => $filters['report_type'], 'from_date' => $presetKey === 'custom' ? $filters['from_date'] : null, 'to_date' => $presetKey === 'custom' ? $filters['to_date'] : null]) }}"
                        class="rounded-xl px-3 py-1.5 text-xs font-bold transition {{ $filters['timeframe'] === $presetKey ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                        {{ $presetLabel }}
                    </a>
                @endforeach
            </div>

            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                <span>Scope: <strong class="text-slate-800">{{ $overview['shop_name'] }}</strong></span>
                <span>&bull;</span>
                <span>Period: <strong class="text-slate-800">{{ $overview['period_label'] }}</strong></span>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.cashbook.reports.shops.credit-notes.report') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 items-end">
            <input type="hidden" name="timeframe" value="custom">
            <input type="hidden" name="report_type" value="{{ $filters['report_type'] }}">

            <div>
                <label for="from_date" class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">From Date</label>
                <input id="from_date" type="date" name="from_date" value="{{ $filters['from_date'] }}"
                    class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
            </div>

            <div>
                <label for="to_date" class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">To Date</label>
                <input id="to_date" type="date" name="to_date" value="{{ $filters['to_date'] }}"
                    class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
            </div>

            <div class="sm:col-span-2">
                <label for="shop_id" class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Shop</label>
                <select id="shop_id" name="shop_id"
                    class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">All Shops</option>
                    @foreach ($shops as $shop)
                        <option value="{{ $shop->id }}" @selected((int) ($filters['shop_id'] ?? 0) === (int) $shop->id)>
                            {{ $shop->name }} ({{ $shop->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="h-10 flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-4 text-xs font-bold text-white hover:bg-slate-800 transition cursor-pointer">
                    <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                    <span>Apply Filters</span>
                </button>
                <a href="{{ route('admin.cashbook.reports.shops.credit-notes.report') }}"
                    class="h-10 inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-50 transition"
                    title="Reset to Today">
                    <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                </a>
            </div>
        </form>

        <!-- Export Controls Bar -->
        <div class="grid gap-3 pt-2 border-t border-slate-100 lg:grid-cols-2">
            <!-- Detailed Exports -->
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <span class="block text-[10px] font-black uppercase tracking-wider text-slate-700">Detailed Report Exports</span>
                    <span class="block text-[10px] font-medium text-slate-500">Shop &amp; Bill Wise + Product Adjustments</span>
                </div>
                <div class="flex flex-wrap items-center gap-1.5">
                    <a href="{{ route('admin.cashbook.reports.shops.credit-notes.report.export.detailed.pdf', $exportParams) }}"
                        @click="exporting = true; setTimeout(() => exporting = false, 2000)"
                        class="inline-flex h-8 items-center gap-1 rounded-lg bg-rose-600 px-2.5 text-[11px] font-bold text-white hover:bg-rose-700 transition"
                        aria-label="Download Detailed PDF">
                        <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                        <span>PDF</span>
                    </a>
                    <a href="{{ route('admin.cashbook.reports.shops.credit-notes.report.export.detailed.excel', $exportParams) }}"
                        @click="exporting = true; setTimeout(() => exporting = false, 2000)"
                        class="inline-flex h-8 items-center gap-1 rounded-lg bg-emerald-600 px-2.5 text-[11px] font-bold text-white hover:bg-emerald-700 transition"
                        aria-label="Download Detailed Excel">
                        <i data-lucide="sheet" class="w-3.5 h-3.5"></i>
                        <span>Excel (.xlsx)</span>
                    </a>
                    <a href="{{ route('admin.cashbook.reports.shops.credit-notes.report.export.detailed.csv', $exportParams) }}"
                        @click="exporting = true; setTimeout(() => exporting = false, 2000)"
                        class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 text-[11px] font-bold text-slate-700 hover:bg-slate-100 transition"
                        aria-label="Download Detailed CSV Register">
                        <i data-lucide="file-spreadsheet" class="w-3.5 h-3.5"></i>
                        <span>CSV</span>
                    </a>
                    <a href="{{ route('admin.cashbook.reports.shops.credit-notes.report.export.detailed.csv', array_merge($exportParams, ['dataset' => 'bundle'])) }}"
                        class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 text-[11px] font-semibold text-slate-600 hover:bg-slate-100 transition"
                        title="Download Register + Product Adjustments + Invoice Reconciliation CSVs (ZIP)">
                        <span>All CSVs (ZIP)</span>
                    </a>
                </div>
            </div>

            <!-- Summary Exports -->
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <span class="block text-[10px] font-black uppercase tracking-wider text-slate-700">Summary Report Exports</span>
                    <span class="block text-[10px] font-medium text-slate-500">Daily, Shop-wise &amp; Daily Shop-wise</span>
                </div>
                <div class="flex flex-wrap items-center gap-1.5">
                    <a href="{{ route('admin.cashbook.reports.shops.credit-notes.report.export.summary.pdf', $exportParams) }}"
                        @click="exporting = true; setTimeout(() => exporting = false, 2000)"
                        class="inline-flex h-8 items-center gap-1 rounded-lg bg-rose-600 px-2.5 text-[11px] font-bold text-white hover:bg-rose-700 transition"
                        aria-label="Download Summary PDF">
                        <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                        <span>PDF</span>
                    </a>
                    <a href="{{ route('admin.cashbook.reports.shops.credit-notes.report.export.summary.excel', $exportParams) }}"
                        @click="exporting = true; setTimeout(() => exporting = false, 2000)"
                        class="inline-flex h-8 items-center gap-1 rounded-lg bg-emerald-600 px-2.5 text-[11px] font-bold text-white hover:bg-emerald-700 transition"
                        aria-label="Download Summary Excel">
                        <i data-lucide="sheet" class="w-3.5 h-3.5"></i>
                        <span>Excel (.xlsx)</span>
                    </a>
                    <a href="{{ route('admin.cashbook.reports.shops.credit-notes.report.export.summary.csv', $exportParams) }}"
                        @click="exporting = true; setTimeout(() => exporting = false, 2000)"
                        class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 text-[11px] font-bold text-slate-700 hover:bg-slate-100 transition"
                        aria-label="Download Summary CSV">
                        <i data-lucide="file-spreadsheet" class="w-3.5 h-3.5"></i>
                        <span>CSV</span>
                    </a>
                    <a href="{{ route('admin.cashbook.reports.shops.credit-notes.report.export.summary.csv', array_merge($exportParams, ['dataset' => 'bundle'])) }}"
                        class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 text-[11px] font-semibold text-slate-600 hover:bg-slate-100 transition"
                        title="Download Daily + Shop + Daily Shop-wise CSVs (ZIP)">
                        <span>All CSVs (ZIP)</span>
                    </a>
                </div>
            </div>
        </div>

        <div x-show="exporting" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-800 flex items-center gap-2">
            <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
            <span>Preparing your export download...</span>
        </div>
    </div>

    <!-- Report Type Switcher -->
    <div class="flex items-center justify-between">
        <div class="inline-flex items-center gap-1 rounded-2xl bg-slate-200/80 p-1">
            <a href="{{ route('admin.cashbook.reports.shops.credit-notes.report', array_merge($exportParams, ['report_type' => 'detailed'])) }}"
                class="rounded-xl px-4 py-2 text-xs font-extrabold transition {{ $filters['report_type'] === 'detailed' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                Detailed Report — Shop &amp; Bill Wise
            </a>
            <a href="{{ route('admin.cashbook.reports.shops.credit-notes.report', array_merge($exportParams, ['report_type' => 'summary'])) }}"
                class="rounded-xl px-4 py-2 text-xs font-extrabold transition {{ $filters['report_type'] === 'summary' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                Summary Report — Daily &amp; Shop Wise
            </a>
        </div>
    </div>

    @if ($filters['report_type'] === 'summary')
        <!-- SUMMARY REPORT VIEW -->
        <div class="space-y-5">
            <!-- Section B: Daily Summary -->
            <div class="white-card rounded-3xl overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-3.5 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-black text-slate-900">Section B — Daily Summary</h2>
                        <p class="text-[11px] font-medium text-slate-500">Every date in the selected period, including zero-credit dates.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3 text-right">Shops</th>
                                <th class="px-4 py-3 text-right">Bills</th>
                                <th class="px-4 py-3 text-right">Credit Notes</th>
                                <th class="px-4 py-3 text-right">Credit Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($report['daily_summary'] as $row)
                                <tr class="hover:bg-slate-50/70 {{ $row['credit_notes_count'] === 0 ? 'text-slate-400' : 'text-slate-800' }}">
                                    <td class="px-4 py-2.5 font-semibold">{{ $row['formatted_date'] }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono">{{ $row['shops_count'] }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono">{{ $row['bills_count'] }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono">{{ $row['credit_notes_count'] }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono font-bold {{ $row['credit_amount'] > 0 ? 'text-emerald-700' : '' }}">
                                        ₹{{ number_format((float) $row['credit_amount'], 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-emerald-50/70 border-t-2 border-emerald-200 font-black text-emerald-900">
                            <tr>
                                <td class="px-4 py-3">Grand Total</td>
                                <td class="px-4 py-3 text-right">—</td>
                                <td class="px-4 py-3 text-right">—</td>
                                <td class="px-4 py-3 text-right font-mono">{{ $overview['total_credit_notes'] }}</td>
                                <td class="px-4 py-3 text-right font-mono">₹{{ number_format((float) $overview['total_credit_amount'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Section C: Shop-wise Period Summary -->
            <div class="white-card rounded-3xl overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-3.5">
                    <h2 class="text-sm font-black text-slate-900">Section C — Shop-wise Period Summary</h2>
                    <p class="text-[11px] font-medium text-slate-500">Sorted by total credit amount descending.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Shop Code</th>
                                <th class="px-4 py-3">Shop Name</th>
                                <th class="px-4 py-3 text-right">Affected Bills</th>
                                <th class="px-4 py-3 text-right">Credit Notes</th>
                                <th class="px-4 py-3 text-right">Credit Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($report['shop_summary'] as $row)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="px-4 py-2.5 font-mono font-bold text-slate-700">{{ $row['shop_code'] }}</td>
                                    <td class="px-4 py-2.5 font-semibold text-slate-900">{{ $row['shop_name'] }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono">{{ $row['bills_count'] }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono">{{ $row['credit_notes_count'] }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono font-bold text-emerald-700">₹{{ number_format((float) $row['credit_amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-500 font-semibold">No approved credit notes found for the selected filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Section D: Daily Shop-wise Summary -->
            <div class="white-card rounded-3xl overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-3.5">
                    <h2 class="text-sm font-black text-slate-900">Section D — Daily Shop-wise Summary</h2>
                    <p class="text-[11px] font-medium text-slate-500">Daily breakdown by shop with daily subtotals and period grand total.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Shop Code</th>
                                <th class="px-4 py-3">Shop Name</th>
                                <th class="px-4 py-3 text-right">Bills</th>
                                <th class="px-4 py-3 text-right">Credit Notes</th>
                                <th class="px-4 py-3 text-right">Credit Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($report['daily_shop_grouped'] as $group)
                                @foreach ($group['rows'] as $row)
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="px-4 py-2.5 font-semibold text-slate-700">{{ $row['formatted_date'] }}</td>
                                        <td class="px-4 py-2.5 font-mono font-bold text-slate-600">{{ $row['shop_code'] }}</td>
                                        <td class="px-4 py-2.5 font-semibold text-slate-900">{{ $row['shop_name'] }}</td>
                                        <td class="px-4 py-2.5 text-right font-mono">{{ $row['bills_count'] }}</td>
                                        <td class="px-4 py-2.5 text-right font-mono">{{ $row['credit_notes_count'] }}</td>
                                        <td class="px-4 py-2.5 text-right font-mono font-bold text-emerald-700">₹{{ number_format((float) $row['credit_amount'], 2) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="bg-slate-100/80 font-bold text-slate-800">
                                    <td colspan="3" class="px-4 py-2">Daily Subtotal — {{ $group['formatted_date'] }}</td>
                                    <td class="px-4 py-2 text-right font-mono">{{ $group['subtotal_bills'] }}</td>
                                    <td class="px-4 py-2 text-right font-mono">{{ $group['subtotal_notes'] }}</td>
                                    <td class="px-4 py-2 text-right font-mono">₹{{ number_format((float) $group['subtotal_amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-slate-500 font-semibold">No daily shop-wise credit notes found for the selected filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-emerald-50/70 border-t-2 border-emerald-200 font-black text-emerald-900">
                            <tr>
                                <td colspan="3" class="px-4 py-3">Period Grand Total</td>
                                <td class="px-4 py-3 text-right font-mono">{{ $overview['total_affected_invoices'] }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ $overview['total_credit_notes'] }}</td>
                                <td class="px-4 py-3 text-right font-mono">₹{{ number_format((float) $overview['total_credit_amount'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    @else
        <!-- DETAILED REPORT VIEW (Date -> Shop -> Invoice -> Individual Credit Notes) -->
        <div class="space-y-5">
            @forelse ($report['date_groups'] as $dateGroup)
                <div class="white-card rounded-3xl overflow-hidden">
                    <!-- Date Header -->
                    <div class="bg-slate-900 text-white px-5 py-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <i data-lucide="calendar" class="w-4 h-4 text-emerald-400"></i>
                            <span class="text-sm font-black">{{ $dateGroup['formatted_date'] }}</span>
                        </div>
                        <div class="text-xs font-bold text-slate-200">
                            {{ $dateGroup['total_shops'] }} {{ Str::plural('Shop', $dateGroup['total_shops']) }}
                            &bull; {{ $dateGroup['total_bills'] }} {{ Str::plural('Bill', $dateGroup['total_bills']) }}
                            &bull; {{ $dateGroup['total_credit_notes'] }} {{ Str::plural('Credit Note', $dateGroup['total_credit_notes']) }}
                            &bull; Daily Subtotal: <span class="font-mono text-emerald-300">₹{{ number_format((float) $dateGroup['subtotal_amount'], 2) }}</span>
                        </div>
                    </div>

                    <div class="p-4 sm:p-5 space-y-5">
                        @foreach ($dateGroup['shops'] as $shopGroup)
                            <div class="rounded-2xl border border-slate-200 overflow-hidden">
                                <!-- Shop Header -->
                                <div class="bg-emerald-50/70 border-b border-emerald-200 px-4 py-2.5 flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs font-black text-emerald-900 bg-white border border-emerald-200 px-2 py-0.5 rounded-lg">{{ $shopGroup['shop_code'] }}</span>
                                        <span class="text-sm font-black text-emerald-950">{{ $shopGroup['shop_name'] }}</span>
                                    </div>
                                    <div class="text-xs font-bold text-emerald-800">
                                        Affected Bills: <span class="font-mono">{{ $shopGroup['total_affected_bills'] }}</span>
                                        &bull; Credit Notes: <span class="font-mono">{{ $shopGroup['total_credit_notes'] }}</span>
                                        &bull; Shop Subtotal: <span class="font-mono">₹{{ number_format((float) $shopGroup['subtotal_amount'], 2) }}</span>
                                    </div>
                                </div>

                                <div class="p-4 space-y-4 bg-white">
                                    @foreach ($shopGroup['invoices'] as $invGroup)
                                        <div class="rounded-xl border border-slate-200 p-4 space-y-3">
                                            <!-- Invoice Header -->
                                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2.5">
                                                <div class="flex flex-wrap items-center gap-3 text-xs">
                                                    <span class="font-mono font-black text-slate-900">{{ $invGroup['invoice_number'] }}</span>
                                                    @if (! $invGroup['is_unapplied'])
                                                        <span class="text-slate-500 font-semibold">Invoice Date: <strong class="text-slate-800">{{ $invGroup['formatted_invoice_date'] }}</strong></span>
                                                    @endif
                                                </div>
                                                <div class="text-xs font-bold text-slate-700">
                                                    Invoice Period Subtotal: <span class="font-mono text-emerald-700">₹{{ number_format((float) $invGroup['period_subtotal'], 2) }}</span>
                                                </div>
                                            </div>

                                            <!-- Individual Credit Notes -->
                                            @foreach ($invGroup['credit_notes'] as $cn)
                                                <div class="rounded-xl bg-slate-50/70 border border-slate-200/80 p-3 space-y-2.5">
                                                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <a href="{{ route('admin.cashbook.reports.shops.credit-notes.show', $cn['credit_note_number']) }}"
                                                                class="font-mono font-black text-indigo-600 hover:underline">
                                                                {{ $cn['credit_note_number'] }}
                                                            </a>
                                                            <span class="rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5 text-[10px] font-black uppercase">{{ $cn['status'] }}</span>
                                                            <span class="text-slate-500">Doc: <strong class="text-slate-700">{{ $cn['formatted_credit_note_date'] }}</strong></span>
                                                            <span class="text-slate-500">Applied: <strong class="text-slate-700">{{ $cn['applied_date'] }}</strong></span>
                                                            <span class="text-slate-500">Reason: <strong class="text-slate-800">{{ $cn['reason'] }}</strong></span>
                                                        </div>
                                                        <div class="flex items-center gap-3">
                                                            <span class="text-slate-500">By: <strong class="text-slate-700">{{ $cn['applied_by'] }}</strong></span>
                                                            <span class="font-mono font-black text-emerald-700">₹{{ number_format((float) $cn['amount'], 2) }}</span>
                                                        </div>
                                                    </div>

                                                    @if ($cn['notes'] !== '')
                                                        <p class="text-[11px] text-slate-600 italic">Notes: {{ $cn['notes'] }}</p>
                                                    @endif

                                                    <!-- Product-Level Adjustment Details -->
                                                    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
                                                        <table class="w-full text-left text-[11px]">
                                                            <thead class="bg-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-600">
                                                                <tr>
                                                                    <th class="px-3 py-2">Product Code</th>
                                                                    <th class="px-3 py-2">Product / Adjustment</th>
                                                                    <th class="px-3 py-2 text-right">Quantity</th>
                                                                    <th class="px-3 py-2 text-right">Original Rate</th>
                                                                    <th class="px-3 py-2 text-right">Corrected Rate</th>
                                                                    <th class="px-3 py-2 text-right">Difference</th>
                                                                    <th class="px-3 py-2 text-right">Credit Amount</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y divide-slate-100">
                                                                @foreach ($cn['items'] as $item)
                                                                    <tr>
                                                                        <td class="px-3 py-1.5 font-mono text-slate-600">{{ $item['product_code'] }}</td>
                                                                        <td class="px-3 py-1.5 font-semibold text-slate-800">
                                                                            {{ $item['product_name'] }}
                                                                            @if ($item['is_monetary_only'])
                                                                                <span class="text-slate-400 font-normal">({{ $item['reason'] }})</span>
                                                                            @endif
                                                                        </td>
                                                                        <td class="px-3 py-1.5 text-right font-mono">{{ $item['quantity'] !== null ? number_format((float) $item['quantity'], 2) . ' ' . $item['unit'] : '—' }}</td>
                                                                        <td class="px-3 py-1.5 text-right font-mono">{{ $item['original_rate'] !== null ? '₹' . number_format((float) $item['original_rate'], 2) : '—' }}</td>
                                                                        <td class="px-3 py-1.5 text-right font-mono">{{ $item['corrected_rate'] !== null ? '₹' . number_format((float) $item['corrected_rate'], 2) : '—' }}</td>
                                                                        <td class="px-3 py-1.5 text-right font-mono">{{ $item['difference'] !== null ? '₹' . number_format((float) $item['difference'], 2) : '—' }}</td>
                                                                        <td class="px-3 py-1.5 text-right font-mono font-bold text-emerald-700">₹{{ number_format((float) $item['credit_amount'], 2) }}</td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            @endforeach

                                            <!-- Bill Financial Reconciliation -->
                                            @if ($invGroup['reconciliation'])
                                                <div class="flex justify-end">
                                                    <div class="w-full sm:w-96 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                                        <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-2">Bill Financial Reconciliation</span>
                                                        <table class="w-full text-xs">
                                                            <tbody class="divide-y divide-slate-200/70">
                                                                <tr>
                                                                    <td class="py-1 text-slate-600">Original Invoice</td>
                                                                    <td class="py-1 text-right font-mono font-semibold text-slate-900">₹{{ number_format((float) $invGroup['reconciliation']['original_invoice'], 2) }}</td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="py-1 text-slate-600">Period Credits (Selected Range)</td>
                                                                    <td class="py-1 text-right font-mono font-semibold text-emerald-700">−₹{{ number_format((float) $invGroup['period_subtotal'], 2) }}</td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="py-1 text-slate-600">Total Credits (Cumulative)</td>
                                                                    <td class="py-1 text-right font-mono font-semibold text-emerald-700">−₹{{ number_format((float) $invGroup['reconciliation']['total_credits'], 2) }}</td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="py-1 text-slate-600">Total Debits (Cumulative)</td>
                                                                    <td class="py-1 text-right font-mono font-semibold text-amber-700">₹{{ number_format((float) $invGroup['reconciliation']['total_debits'], 2) }}</td>
                                                                </tr>
                                                                <tr class="font-black text-slate-950">
                                                                    <td class="pt-1.5">Effective Net Bill</td>
                                                                    <td class="pt-1.5 text-right font-mono text-emerald-800">₹{{ number_format((float) $invGroup['reconciliation']['effective_net_bill'], 2) }}</td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="white-card rounded-3xl p-10 text-center space-y-2">
                    <i data-lucide="file-search" class="w-10 h-10 text-slate-300 mx-auto"></i>
                    <h3 class="text-sm font-black text-slate-800">No Approved Credit Notes Found</h3>
                    <p class="text-xs font-medium text-slate-500">There are no active, finalized shop credit notes matching your selected date range and shop filter.</p>
                </div>
            @endforelse

            <!-- Period Grand Total Footer -->
            <div class="rounded-3xl bg-slate-900 text-white px-6 py-4 flex flex-wrap items-center justify-between gap-3 shadow-sm">
                <div>
                    <span class="block text-[10px] font-black uppercase tracking-wider text-emerald-400">Period Grand Total</span>
                    <span class="text-sm font-bold">{{ $overview['period_label'] }} &bull; {{ $overview['shop_name'] }}</span>
                </div>
                <div class="flex flex-wrap items-center gap-5 text-xs font-bold">
                    <span>Shops: <strong class="font-mono">{{ $overview['total_affected_shops'] }}</strong></span>
                    <span>Bills: <strong class="font-mono">{{ $overview['total_affected_invoices'] }}</strong></span>
                    <span>Credit Notes: <strong class="font-mono">{{ $overview['total_credit_notes'] }}</strong></span>
                    <span class="text-base font-mono font-black text-emerald-400">₹{{ number_format((float) $overview['total_credit_amount'], 2) }}</span>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
