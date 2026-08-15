@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop #' . $currentShop->shop_id) . ' — Financial Drilldown')

@section('header_title')
    <i data-lucide="store" class="w-5 h-5 text-emerald-600"></i> {{ $currentShop->name ?: 'Shop #' . $currentShop->shop_id }} Drilldown
@endsection

@section('header_subtitle')
    Financial performance, category breakdowns, and daily transactions.
@endsection
@section('content')
    <div class="mx-auto max-w-[96rem] space-y-4">
        <!-- Top Hero Banner with Shop KPI Chips -->
        <section class="overflow-hidden rounded-2xl bg-white border border-slate-200 shadow-sm text-slate-800">
            <div class="bg-[linear-gradient(135deg,_#f8fafc_0%,_#f1f5f9_50%,_#ecfdf5_100%)] p-4 sm:p-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.16em] text-emerald-800 border border-emerald-200">{{ $currentShop->code ?: ('SHP-' . $currentShop->shop_id) }}</span>
                            <span class="text-[10px] font-bold text-slate-500">Shop Accounting Report</span>
                        </div>
                        <h1 class="mt-1 text-lg font-black tracking-tight sm:text-xl text-slate-900">{{ $currentShop->name ?: 'Shop #' . $currentShop->shop_id }}</h1>
                        <p class="text-xs font-semibold text-slate-600">
                            {{ Carbon\Carbon::parse($startDate)->format('d M Y') }}
                            @if ($startDate !== $endDate)
                                to {{ Carbon\Carbon::parse($endDate)->format('d M Y') }}
                            @endif
                        </p>
                    </div>

                    <!-- Timeframe Presets & Actions -->
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex flex-wrap items-center gap-1 rounded-xl border border-slate-200 bg-white p-1 shadow-xs">
                            <a href="{{ route('admin.cashbook.reports.shop', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'timeframe' => 'today']) }}" class="rounded-lg px-2.5 py-1 text-xs font-bold transition {{ $timeframe === 'today' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">Today</a>
                            <a href="{{ route('admin.cashbook.reports.shop', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'timeframe' => 'yesterday']) }}" class="rounded-lg px-2.5 py-1 text-xs font-bold transition {{ $timeframe === 'yesterday' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">Yesterday</a>
                            <a href="{{ route('admin.cashbook.reports.shop', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'timeframe' => 'weekly']) }}" class="rounded-lg px-2.5 py-1 text-xs font-bold transition {{ $timeframe === 'weekly' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">Week</a>
                            <a href="{{ route('admin.cashbook.reports.shop', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'timeframe' => 'monthly']) }}" class="rounded-lg px-2.5 py-1 text-xs font-bold transition {{ $timeframe === 'monthly' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">Month</a>
                        </div>
                        <a href="{{ route('admin.cashbook.reports.hub') }}" class="rounded-xl border border-slate-250 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition flex items-center gap-1.5 shadow-xs">
                            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                            <span>All Cards</span>
                        </a>
                        <a href="{{ route('admin.cashbook.reports.mobile-ledger', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'timeframe' => $timeframe, 'start_date' => $startDate, 'end_date' => $endDate]) }}" class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-black uppercase tracking-wider text-white hover:bg-emerald-500 transition flex items-center gap-1.5 shadow-xs">
                            <i data-lucide="book-open" class="w-3.5 h-3.5"></i>
                            <span>Ledger</span>
                        </a>
                    </div>
                </div>

                <!-- 4 Key KPI Metrics Cards -->
                <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
                    <div class="rounded-xl border border-slate-200 bg-white p-2.5 sm:p-3 shadow-xs">
                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-500">Total Sales</p>
                        <p class="mt-0.5 text-base font-black text-slate-900 sm:text-lg">₹{{ number_format($metrics['sales'], 2) }}</p>
                    </div>
                    <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-2.5 sm:p-3 shadow-xs">
                        <p class="text-[9px] font-black uppercase tracking-wider text-rose-600">Total Expenses</p>
                        <p class="mt-0.5 text-base font-black text-rose-700 sm:text-lg">₹{{ number_format($metrics['expense'], 2) }}</p>
                    </div>
                    <div class="rounded-xl border p-2.5 sm:p-3 shadow-xs {{ $metrics['net'] >= 0 ? 'bg-emerald-50/50 border-emerald-100' : 'bg-rose-50/50 border-rose-100' }}">
                        <p class="text-[9px] font-black uppercase tracking-wider {{ $metrics['net'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">Net Profit / Loss</p>
                        <p class="mt-0.5 text-base font-black sm:text-lg {{ $metrics['net'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                            ₹{{ number_format($metrics['net'], 2) }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-amber-100 bg-amber-50/40 p-2.5 sm:p-3 shadow-xs">
                        <p class="text-[9px] font-black uppercase tracking-wider text-amber-700">GL Bills / Purchases</p>
                        <p class="mt-0.5 text-base font-black text-amber-800 sm:text-lg">₹{{ number_format($metrics['gl_bills'], 2) }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Two Column Detailed Breakdown Layout -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <!-- Left: Category Breakdown Table (1 col) -->
            <div class="flex flex-col rounded-2xl border border-slate-200 bg-white shadow-xs lg:col-span-1">
                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-4 py-3">
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
                        <i data-lucide="pie-chart" class="w-3.5 h-3.5 text-slate-600"></i>
                        <span>Category Breakdown</span>
                    </h2>
                    <span class="text-[10px] font-bold text-slate-400">{{ count($metrics['categories']) }} Categories</span>
                </div>
                <div class="divide-y divide-slate-100 p-2 text-xs overflow-y-auto max-h-[460px]">
                    @forelse ($metrics['categories'] as $cat)
                        <div class="flex items-center justify-between py-2 px-2.5 hover:bg-slate-50 rounded-lg">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-bold text-slate-900">{{ $cat['category'] }}</p>
                                <p class="text-[10px] font-semibold text-slate-400 capitalize">{{ $cat['direction'] }} · {{ $cat['count'] }} entries</p>
                            </div>
                            <span class="font-black {{ $cat['direction'] === 'income' ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $cat['direction'] === 'income' ? '+' : '-' }}₹{{ number_format($cat['amount'], 2) }}
                            </span>
                        </div>
                    @empty
                        <p class="p-4 text-center text-xs font-semibold text-slate-400">No entries recorded in this period.</p>
                    @endforelse
                </div>
            </div>

            <!-- Right: Itemized Ledger Transactions Table (2 cols) -->
            <div class="flex flex-col rounded-2xl border border-slate-200 bg-white shadow-xs lg:col-span-2">
                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/70 px-4 py-3">
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
                        <i data-lucide="receipt" class="w-3.5 h-3.5 text-slate-600"></i>
                        <span>Daily Ledger Entries</span>
                    </h2>
                    <span class="text-[10px] font-bold text-slate-400">{{ $metrics['total_entries'] }} Transactions</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-100/70 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-3.5 py-2.5">Date</th>
                                <th class="px-3.5 py-2.5">Entry Type</th>
                                <th class="px-3.5 py-2.5">Source</th>
                                <th class="px-3.5 py-2.5">Notes</th>
                                <th class="px-3.5 py-2.5 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                            @forelse ($metrics['transactions'] as $tx)
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="whitespace-nowrap px-3.5 py-2.5 text-[11px] font-bold text-slate-500">
                                        {{ Carbon\Carbon::parse($tx->business_date)->format('d M') }}
                                    </td>
                                    <td class="px-3.5 py-2.5">
                                        <span class="font-bold text-slate-900">{{ $tx->entryType?->name ?: $tx->entry_type_code }}</span>
                                    </td>
                                    <td class="px-3.5 py-2.5 capitalize text-[10px] font-bold text-slate-600">
                                        {{ $tx->funding_source ?: 'none' }}
                                    </td>
                                    <td class="px-3.5 py-2.5 text-slate-500 truncate max-w-[180px]">
                                        {{ $tx->notes ?: '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-3.5 py-2.5 text-right font-black {{ ($tx->direction === 'income' || $tx->entryType?->category === 'income') ? 'text-emerald-700' : 'text-rose-700' }}">
                                        {{ ($tx->direction === 'income' || $tx->entryType?->category === 'income') ? '+' : '-' }}₹{{ number_format($tx->amount, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-xs font-semibold text-slate-400">
                                        No ledger transactions found for this date range.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
