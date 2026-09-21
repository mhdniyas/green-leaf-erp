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
            <p class="text-xs font-medium text-slate-500">
                Daily comparison of sales and product purchase expenses for Fruits, Vegetables, Stationery, and Purchaser accountability.
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
    @include('admin.cashbook.monthly-report.partials.period-filter', ['reportType' => 'sale-split'])

    <!-- RECONCILIATION & READINESS STATUS -->
    @include('admin.cashbook.monthly-report.partials.reconciliation-badge')

    <!-- CATEGORY PERFORMANCE SUMMARY TILES -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <!-- FRUITS TILE -->
        <div class="rounded-2xl border border-amber-200/80 bg-amber-50/30 p-4 cursor-pointer transition hover:border-amber-300 hover:shadow-md"
             @click="$dispatch('open-drilldown', { metric: 'fruits_expense', title: 'Fruits Purchases Breakdown' })">
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

        <!-- VEGETABLES TILE -->
        <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/30 p-4 cursor-pointer transition hover:border-emerald-300 hover:shadow-md"
             @click="$dispatch('open-drilldown', { metric: 'vegetables_expense', title: 'Vegetables Purchases Breakdown' })">
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

        <!-- STATIONERY TILE -->
        <div class="rounded-2xl border border-blue-200/80 bg-blue-50/30 p-4 cursor-pointer transition hover:border-blue-300 hover:shadow-md"
             @click="$dispatch('open-drilldown', { metric: 'stationery_expense', title: 'Stationery Purchases Breakdown' })">
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

        <!-- OTHER EXPENSES TILE -->
        <div class="rounded-2xl border border-purple-200/80 bg-purple-50/30 p-4 cursor-pointer transition hover:border-purple-300 hover:shadow-md"
             @click="$dispatch('open-drilldown', { metric: 'other_expense', title: 'Operating Expenses Breakdown' })">
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
    </div>

    <!-- MAIN SALE SPLIT TABLE -->
    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs">
        <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:px-6">
            <h2 class="text-sm font-extrabold text-slate-900">Daily Category Matrix</h2>
            <p class="text-xs font-semibold text-slate-400">Side-by-side view of product sales, purchase expenses, and operating overheads</p>
        </div>

        <!-- DESKTOP TABLE -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <!-- Grouped Header Rows -->
                <thead class="border-b border-slate-200 bg-slate-50/90 text-center text-[10px] font-black uppercase tracking-wider text-slate-600">
                    <tr>
                        <th rowspan="2" class="border-r border-slate-200 px-4 py-2.5 text-left text-xs text-slate-900">Date</th>
                        <th colspan="2" class="border-r border-slate-200 bg-amber-50/50 px-3 py-1.5 text-amber-900">Fruits (₹)</th>
                        <th colspan="2" class="border-r border-slate-200 bg-emerald-50/50 px-3 py-1.5 text-emerald-900">Vegetables (₹)</th>
                        <th colspan="2" class="border-r border-slate-200 bg-blue-50/50 px-3 py-1.5 text-blue-900">Stationery (₹)</th>
                        <th rowspan="2" class="border-r border-slate-200 bg-purple-50/40 px-3 py-2.5 text-right text-purple-900">Other Exp (₹)</th>
                        <th rowspan="2" class="border-r border-slate-200 px-3 py-2.5 text-right font-black text-slate-900">Total Sales</th>
                        <th rowspan="2" class="border-r border-slate-200 px-3 py-2.5 text-right font-black text-rose-700">Total Exp</th>
                        <th rowspan="2" class="px-4 py-2.5 text-right font-black text-slate-900">Balance</th>
                    </tr>
                    <tr class="border-t border-slate-200 text-[10px] text-slate-500">
                        <th class="border-r border-slate-100 bg-amber-50/30 px-2.5 py-1.5 text-right font-bold">Sale</th>
                        <th class="border-r border-slate-200 bg-amber-50/30 px-2.5 py-1.5 text-right font-bold text-amber-800">Exp</th>
                        <th class="border-r border-slate-100 bg-emerald-50/30 px-2.5 py-1.5 text-right font-bold">Sale</th>
                        <th class="border-r border-slate-200 bg-emerald-50/30 px-2.5 py-1.5 text-right font-bold text-emerald-800">Exp</th>
                        <th class="border-r border-slate-100 bg-blue-50/30 px-2.5 py-1.5 text-right font-bold">Sale</th>
                        <th class="border-r border-slate-200 bg-blue-50/30 px-2.5 py-1.5 text-right font-bold text-blue-800">Exp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium font-mono text-[11px]">
                    @forelse($daily_rows as $d)
                        @php $isPos = $d['balance'] >= 0; @endphp
                        <tr class="transition hover:bg-slate-50/80">
                            <td class="border-r border-slate-100 px-4 py-2.5 font-sans font-semibold text-slate-900 whitespace-nowrap">
                                {{ $d['formatted_date'] }}
                            </td>
                            <!-- Fruits -->
                            <td class="border-r border-slate-100 px-2.5 py-2.5 text-right text-slate-800">{{ number_format($d['fruits_sale'], 2) }}</td>
                            <td class="border-r border-slate-200 px-2.5 py-2.5 text-right text-amber-800">{{ number_format($d['fruits_expense'], 2) }}</td>
                            <!-- Vegetables -->
                            <td class="border-r border-slate-100 px-2.5 py-2.5 text-right text-slate-800">{{ number_format($d['veg_sale'], 2) }}</td>
                            <td class="border-r border-slate-200 px-2.5 py-2.5 text-right text-emerald-800">{{ number_format($d['veg_expense'], 2) }}</td>
                            <!-- Stationery -->
                            <td class="border-r border-slate-100 px-2.5 py-2.5 text-right text-slate-800">{{ number_format($d['stationery_sale'], 2) }}</td>
                            <td class="border-r border-slate-200 px-2.5 py-2.5 text-right text-blue-800">{{ number_format($d['stationery_expense'], 2) }}</td>
                            <!-- Other Operating Expenses -->
                            <td class="border-r border-slate-200 px-3 py-2.5 text-right text-purple-900 font-semibold">{{ number_format($d['other_expenses'], 2) }}</td>
                            <!-- Totals & Balance -->
                            <td class="border-r border-slate-200 px-3 py-2.5 text-right font-bold text-slate-900">{{ number_format($d['total_sales'], 2) }}</td>
                            <td class="border-r border-slate-200 px-3 py-2.5 text-right font-bold text-rose-700">{{ number_format($d['total_expenses'], 2) }}</td>
                            <td class="px-4 py-2.5 text-right font-black {{ $isPos ? 'text-emerald-700' : 'text-rose-600' }}">
                                {{ $d['balance'] < 0 ? '-' : '' }}{{ number_format(abs($d['balance']), 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
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
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="border-b border-slate-100 bg-slate-50/50 text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3">Purchaser</th>
                        <th class="px-3 py-3 text-right">Opening (₹)</th>
                        <th class="px-3 py-3 text-right text-indigo-700">Cash Given (₹)</th>
                        <th class="px-3 py-3 text-right text-slate-700">Cash Purchases (₹)</th>
                        <th class="px-3 py-3 text-right text-slate-700">Expenses (₹)</th>
                        <th class="px-3 py-3 text-right text-slate-700">Cash Returned (₹)</th>
                        <th class="px-4 py-3 text-right font-black text-slate-900">Closing Position (₹)</th>
                        <th class="px-3 py-3 text-right text-slate-400">Credit Purchases (₹)</th>
                        <th class="px-4 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($purchaser_positions as $p)
                        <tr class="transition hover:bg-slate-50/80 cursor-pointer"
                            @click="$dispatch('open-drilldown', { metric: 'purchaser_timeline', title: 'Purchaser Timeline ({{ $p['purchaser_name'] }})', purchaserId: {{ $p['purchaser_id'] }} })">
                            <td class="px-5 py-3 font-semibold text-slate-900 flex items-center gap-2">
                                <i data-lucide="user" class="h-4 w-4 text-slate-400"></i>
                                <span>{{ $p['purchaser_name'] }}</span>
                            </td>
                            <td class="px-3 py-3 text-right font-mono">{{ number_format($p['opening_position'], 2) }}</td>
                            <td class="px-3 py-3 text-right font-mono font-bold text-indigo-700">{{ number_format($p['cash_given'], 2) }}</td>
                            <td class="px-3 py-3 text-right font-mono text-slate-800">{{ number_format($p['cash_purchases'], 2) }}</td>
                            <td class="px-3 py-3 text-right font-mono text-slate-800">{{ number_format($p['purchaser_expenses'], 2) }}</td>
                            <td class="px-3 py-3 text-right font-mono text-slate-800">{{ number_format($p['cash_returned'], 2) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-black {{ $p['closing_position'] >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                {{ $p['closing_position'] < 0 ? '-' : '' }}₹{{ number_format(abs($p['closing_position']), 2) }}
                            </td>
                            <td class="px-3 py-3 text-right font-mono text-slate-400">{{ number_format($p['credit_purchases'], 2) }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-black {{ $p['status_class'] }}">
                                    {{ $p['status'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
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
