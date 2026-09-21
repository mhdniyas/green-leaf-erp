@extends('admin.cashbook.layouts.app')

@section('title', 'Expense Report Matrix — Green Leaf Monthly Report')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-16" x-data>
    <!-- TOP HEADER -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-black text-indigo-700">Operating Overhead Matrix</span>
                <span class="text-xs font-semibold text-slate-400">Cashbook Monthly Report</span>
            </div>
            <h1 class="mt-1 text-xl font-black tracking-tight text-slate-900 sm:text-2xl">
                Daily Expense Matrix
            </h1>
            <p class="text-xs font-medium text-slate-500">
                Daily normalized operating expense matrix across Salary, Rent, Vehicle/Fuel, Food/Mess, and Others.
            </p>
        </div>

        <!-- TAB NAVIGATION -->
        <div class="inline-flex rounded-xl bg-slate-100 p-1 text-xs font-bold text-slate-600">
            <a href="{{ route('admin.cashbook.monthly-report.overview', request()->query()) }}"
               class="rounded-lg px-3 py-1.5 transition {{ request()->routeIs('admin.cashbook.monthly-report.overview') ? 'bg-white text-slate-900 shadow-xs' : 'hover:text-slate-900' }}">
                Overview
            </a>
            <a href="{{ route('admin.cashbook.monthly-report.sale-split', request()->query()) }}"
               class="rounded-lg px-3 py-1.5 transition {{ request()->routeIs('admin.cashbook.monthly-report.sale-split') ? 'bg-white text-slate-900 shadow-xs' : 'hover:text-slate-900' }}">
                Sale Split
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
    @include('admin.cashbook.monthly-report.partials.period-filter', ['reportType' => 'expense-report'])

    <!-- RECONCILIATION & READINESS STATUS -->
    @include('admin.cashbook.monthly-report.partials.reconciliation-badge')

    <!-- EXPENSE TILES SUMMARY -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Salary</span>
            <div class="mt-1 font-mono text-base font-black text-slate-900">₹{{ number_format($totals['salary'] ?? 0, 2) }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Rent</span>
            <div class="mt-1 font-mono text-base font-black text-slate-900">₹{{ number_format($totals['rent'] ?? 0, 2) }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Vehicle / Fuel</span>
            <div class="mt-1 font-mono text-base font-black text-slate-900">₹{{ number_format($totals['vehicle_fuel'] ?? 0, 2) }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Food / Mess</span>
            <div class="mt-1 font-mono text-base font-black text-slate-900">₹{{ number_format($totals['food_mess'] ?? 0, 2) }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Others</span>
            <div class="mt-1 font-mono text-base font-black text-slate-900">₹{{ number_format($totals['other_expense'] ?? 0, 2) }}</div>
        </div>
    </div>

    <!-- DAILY OPERATING EXPENSE MATRIX TABLE -->
    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs">
        <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:px-6 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-extrabold text-slate-900">Daily Normalized Heading Matrix</h2>
                <p class="text-xs font-semibold text-slate-400">Click any figure to inspect the original underlying entries</p>
            </div>
            <div class="text-right">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Operating</span>
                <div class="font-mono text-sm font-black text-rose-700">₹{{ number_format($total_operating_expenses, 2) }}</div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="border-b border-slate-100 bg-slate-50/50 text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3">Date</th>
                        <th class="px-4 py-3 text-right">Salary (₹)</th>
                        <th class="px-4 py-3 text-right">Rent (₹)</th>
                        <th class="px-4 py-3 text-right">Vehicle/Fuel (₹)</th>
                        <th class="px-4 py-3 text-right">Food/Mess (₹)</th>
                        <th class="px-4 py-3 text-right">Others (₹)</th>
                        <th class="px-5 py-3 text-right font-black text-slate-900">Daily Total (₹)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($daily_matrix as $d)
                        <tr class="transition hover:bg-slate-50/80">
                            <td class="px-5 py-2.5 font-semibold text-slate-900 whitespace-nowrap">{{ $d['formatted_date'] }}</td>
                            <td class="px-4 py-2.5 text-right font-mono">
                                <button type="button" @click="$dispatch('open-drilldown', { metric: 'salary', title: 'Salary Expenses', date: '{{ $d['date'] }}' })"
                                        class="{{ $d['salary'] > 0 ? 'text-indigo-600 hover:underline font-bold' : 'text-slate-400' }}">
                                    {{ number_format($d['salary'], 2) }}
                                </button>
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono">
                                <button type="button" @click="$dispatch('open-drilldown', { metric: 'rent', title: 'Rent Expenses', date: '{{ $d['date'] }}' })"
                                        class="{{ $d['rent'] > 0 ? 'text-indigo-600 hover:underline font-bold' : 'text-slate-400' }}">
                                    {{ number_format($d['rent'], 2) }}
                                </button>
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono">
                                <button type="button" @click="$dispatch('open-drilldown', { metric: 'vehicle_fuel', title: 'Vehicle & Fuel Expenses', date: '{{ $d['date'] }}' })"
                                        class="{{ $d['vehicle_fuel'] > 0 ? 'text-indigo-600 hover:underline font-bold' : 'text-slate-400' }}">
                                    {{ number_format($d['vehicle_fuel'], 2) }}
                                </button>
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono">
                                <button type="button" @click="$dispatch('open-drilldown', { metric: 'food_mess', title: 'Food & Mess Expenses', date: '{{ $d['date'] }}' })"
                                        class="{{ $d['food_mess'] > 0 ? 'text-indigo-600 hover:underline font-bold' : 'text-slate-400' }}">
                                    {{ number_format($d['food_mess'], 2) }}
                                </button>
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono">
                                <button type="button" @click="$dispatch('open-drilldown', { metric: 'other_expense', title: 'Other Expenses', date: '{{ $d['date'] }}' })"
                                        class="{{ $d['other_expense'] > 0 ? 'text-indigo-600 hover:underline font-bold' : 'text-slate-400' }}">
                                    {{ number_format($d['other_expense'], 2) }}
                                </button>
                            </td>
                            <td class="px-5 py-2.5 text-right font-mono font-black text-rose-700">
                                {{ number_format($d['total'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-8 text-center text-xs text-slate-400">
                                No expense records found for the selected period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <!-- PERIOD TOTALS FOOTER -->
                <tfoot class="border-t-2 border-slate-200 bg-slate-100/90 font-mono text-xs font-black text-slate-900">
                    <tr>
                        <td class="px-5 py-3 font-sans text-xs uppercase">Period Total</td>
                        <td class="px-4 py-3 text-right">{{ number_format($totals['salary'] ?? 0, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($totals['rent'] ?? 0, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($totals['vehicle_fuel'] ?? 0, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($totals['food_mess'] ?? 0, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($totals['other_expense'] ?? 0, 2) }}</td>
                        <td class="px-5 py-3 text-right text-rose-800">
                            ₹{{ number_format($total_operating_expenses, 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- DRILLDOWN MODAL -->
    @include('admin.cashbook.monthly-report.partials.drilldown-modal')
</div>
@endsection
